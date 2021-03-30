<?php

namespace ahrefs\AhrefsSeo;

use ahrefs\AhrefsApiPhp\AhrefsAPI;
/**
 * Work with Ahrefs API.
 */
class Ahrefs_Seo_Api extends Ahrefs_Seo_Abstract_Api {

	const OPTION_ACCOUNT_IS_FREE   = 'ahrefs-seo-account-is-free';
	const OPTION_SUBSCRIPTION_INFO = 'ahrefs-seo-subscription-info';
	const OPTION_DOMAIN            = 'ahrefs-seo-domain';
	const TEXT_LIMITED_ACOUNT      = 'Limited Ahrefs account detected.';
	const TEXT_NO_TOKEN            = 'Ahrefs token is required.'; // Native message is 'API token is required.'.
	/** Allow query visitors once per seconds. */
	const API_MIN_DELAY = 0.5;
	/** @var float Time when last query to Ahrefs API run. */
	private $last_query_time = 0;
	/** @var Ahrefs_Seo_Api */
	private static $instance;
	/**
	 * Token instance
	 *
	 * @var Ahrefs_Seo_Token
	 */
	protected $token;
	/**
	 * Last error or empty string
	 *
	 * @var string
	 */
	protected $last_error = '';
	/**
	 * Return the instance
	 *
	 * $return Ahrefs_Seo_Api
	 */
	public static function get() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token = Ahrefs_Seo_Token::get();
	}
	/**
	 * Create an AhrefsAPI class instance
	 *
	 * @return AhrefsAPI
	 */
	private function get_ahrefs_api() {
		/**
		 * Create an AhrefsAPI class instance
		 *
		 * @param String $token APItoken from https://ahrefs.com/api/
		 * @param Boolean $debug Debug message
		 */
		$api = new AhrefsAPI( $this->token->token_get(), false );
		if ( method_exists( $api, 'useGuzzle' ) ) {
			$api->useGuzzle();
		}
		return $api;
	}
	/**
	 * Return last API error if any.
	 * Does not use option value.
	 *
	 * @return string Error message or empty string.
	 */
	public function get_last_error() {
		return $this->last_error;
	}
	/**
	 * Set last API error. Update option value too.
	 *
	 * @param string $message Error message or empty string.
	 * @param string $type 'error' or 'notice'.
	 * @return void
	 */
	protected function set_last_error( $message, $type ) {
		$this->last_error = $message;
		Ahrefs_Seo_Errors::save_message( 'ahrefs', $message, $type );
	}
	/**
	 * Check if current domain name changed.
	 *
	 * @param string $domain
	 * @return void
	 */
	protected function check_domain_updated( $domain ) {
		$current_domain = (string) get_option( self::OPTION_DOMAIN, '' );
		if ( $current_domain !== $domain ) {
			// call reset backlinks data on domain change action.
			do_action( Ahrefs_Seo::ACTION_DOMAIN_CHANGED, $domain, $current_domain ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- constant used plugin prefix.
			update_option( self::OPTION_DOMAIN, $domain );
		}
	}
	/**
	 * Check is token valid, set error message if any.
	 * Call API for result.
	 *
	 * @return bool true if token is valid.
	 */
	public function token_check() {
		$result = $this->get_subscription_info();
		return is_null( $result ) ? false : true;
	}
	/**
	 * Return array with details or null.
	 * Also set last API error.
	 *
	 * @param bool $use_cached_info if true - return cached info, if exists.
	 * @return null|array
	 */
	public function get_subscription_info( $use_cached_info = false ) {
		if ( $use_cached_info ) { // try to return cached info first.
			$value = get_option( self::OPTION_SUBSCRIPTION_INFO );
			if ( is_array( $value ) ) {
				return $value;
			}
		}
		$error       = 'Unknown error (get subscription info)';
		$token_value = $this->token->token_get();
		if ( empty( $token_value ) ) {
			$this->set_last_error( self::TEXT_NO_TOKEN, 'error' );
			return null;
		}
		Ahrefs_Seo_Errors::clean_messages( 'ahrefs' );
		try {
			// Create an AhrefsAPI class instance.
			$ahrefs = $this->get_ahrefs_api();
			$this->maybe_do_a_pause();
			$info = $ahrefs->get_subscription_info();
			do_action_ref_array( 'ahrefs_seo_api_subscription_info', [ &$info ] );
			if ( '' === $info ) {
				$error = 'Please try again later';
				/** @var array */
				$raw = $ahrefs->getCurlInfo();
				if ( is_array( $raw ) && is_array( $raw[0] ) && isset( $raw[0]['total_time'] ) && isset( $raw[0]['size_download'] ) && 0.0 === floatval( $raw[0]['size_download'] ) && isset( $raw[0]['http_code'] ) && 0 === $raw[0]['http_code'] ) {
					$error = 'Connection error';
				}
				Ahrefs_Seo::notify(
					new Ahrefs_Seo_Exception(
						'Ahrefs API get_subscription_info() returned empty result ' . wp_json_encode(
							[
								'token' => $token_value,
								'info'  => $info,
								'raw'   => $raw,
							]
						)
					),
					'Ahrefs API get_subscription_info empty'
				);
			} else {
				$data = json_decode( $info, true );
				if ( ! empty( $data ) && is_array( $data ) ) {
					if ( isset( $data['info'] ) ) {
						update_option( self::OPTION_SUBSCRIPTION_INFO, $data['info'] );
						return $data['info'];
					} elseif ( isset( $data['error'] ) ) {
						$error = $data['error'];
					} else {
						$error = 'API error, get_subscription_info, response [' . $info . ']';
					}
				}
			}
		} catch ( \Error $e ) {
			$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Ahrefs API get_subscription_info unexpected' );
		} catch ( \Exception $e ) {
			$error = $e->getMessage();
			Ahrefs_Seo::notify( $e, 'Ahrefs API get_subscription_info unexpected' );
		}
		if ( 'invalid token' === $error ) { // replace error message.
			if ( $this->token->token_get() ) {
				   Ahrefs_Seo_Token::get()->disconnect();
				   Ahrefs_Seo_Errors::save_message( 'ahrefs', 'Ahrefs account disconnected due to invalid token.', 'notice' );
				   $error = '';
			} else {
				$error = 'The code is invalid';
			}
		}
		if ( $error ) {
			$this->set_last_error( $error, 'error' );
		}
		return null;
	}
	/**
	 * Get backlinks count by url.
	 *
	 * @param string $url Source url.
	 * @param string $start_date Start date.
	 * @param string $end_date Finish date.
	 * @param int    $limit Limit of received from API rows.
	 * @return null|int Number of dofollow backlinks for given url.
	 */
	public function get_backlinks_count_by_url( $url, $start_date, $end_date, $limit = 1000 ) {
		if ( $this->is_disconnected() ) {
			$this->set_last_error( self::TEXT_NO_TOKEN, 'error' );
		} elseif ( $this->is_free_account() || ! $this->is_limited_account( true ) ) { // first check is for free account, because it is limited by default.
			$error  = '';
			$url    = apply_filters( 'ahrefs_seo_post_url', $url );
			$domain = wp_parse_url( $url, PHP_URL_HOST );
			if ( is_string( $domain ) ) {
				$this->check_domain_updated( $domain );
			}
			// remove scheme.
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( $scheme ) {
				$url = substr( $url, strlen( $scheme ) + 3 );
			}
			try {
				$ahrefs = $this->get_ahrefs_api();
				$ahrefs->set_target( $url )->mode_exact()->select( 'backlinks' )->set_output( 'json' ); // @phpstan-ignore-line -- methods are not defined, but __call used.
				$this->maybe_do_a_pause();
				$info = $ahrefs->get_metrics_extended();
				do_action_ref_array( 'ahrefs_seo_api_metrics_extended', [ &$info ] );
				if ( '' === $info ) {
					$error = 'Empty answer';
					/** @var array */
					$raw = $ahrefs->getCurlInfo();
					if ( is_array( $raw ) && is_array( $raw[0] ) && isset( $raw[0]['total_time'] ) && isset( $raw[0]['size_download'] ) && 0.0 === floatval( $raw[0]['size_download'] ) && isset( $raw[0]['http_code'] ) && 0 === $raw[0]['http_code'] ) {
						$error = 'Connection error';
					}
					$e = new Ahrefs_Seo_Exception(
						'Ahrefs API get_metrics_extended() returned empty result ' . wp_json_encode(
							[
								'info' => $info,
								'raw'  => $raw,
							]
						)
					);
					Ahrefs_Seo::notify( $e, 'Ahrefs API get_metrics_extended empty' );
					$this->on_error_received( $e, [ $url ] );
				} else {
					$data = json_decode( $info, true );
					if ( is_array( $data ) && isset( $data['metrics'] ) && is_array( $data['metrics'] ) && isset( $data['metrics']['backlinks'] ) ) {
						return intval( $data['metrics']['backlinks'] );
					} elseif ( is_array( $data ) && isset( $data['error'] ) ) {
						if ( is_string( $data['error'] ) ) {
							$error = $data['error'];
							$this->check_is_limited_error( $error );
						}
					} else {
						/** @var array */
						$raw   = $ahrefs->getCurlInfo();
						$error = 'Incorrect service answer: [' . $info . '] for page [ ' . $url . ' ]';
						$e     = new Ahrefs_Seo_Exception(
							'Ahrefs API get_metrics_extended() returned incorrect result ' . wp_json_encode(
								[
									'info' => $info,
									'raw'  => $raw,
								]
							)
						);
						Ahrefs_Seo::notify( $e, 'Ahrefs API get_metrics_extended incorrect' );
						$this->on_error_received( $e, [ $url ] );
					}
				}
			} catch ( \Error $e ) {
				$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Ahrefs API get_metrics_extended unexpected' );
				$this->on_error_received( $e, [ $url ] );
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
				Ahrefs_Seo::notify( $e, 'Ahrefs API get_metrics_extended unexpected' );
				$this->on_error_received( $e, [ $url ] );
			}
			if ( $error ) {
				$this->set_last_error( $error, 'error' );
			}
		} else {
			$this->set_last_error( self::TEXT_LIMITED_ACOUNT, 'error' );
		}
		return null;
	}
	/**
	 * Clear internal cached data
	 */
	public function clear_cache() {
		delete_option( self::OPTION_SUBSCRIPTION_INFO );
	}
	/**
	 * Check error message against limited account error
	 *
	 * @param string $error
	 * @return bool
	 */
	protected function check_is_limited_error( $error ) {
		if ( 'Integration limit is exceeded' === $error ) {
			return ! $this->is_free_account( false ) && $this->is_limited_account( true ); // is_limited_account() uses cached velues from uncached is_free_account() call.
		}
		return false;
	}
	/**
	 * There is no active Ahrefs account
	 *
	 * @return bool
	 */
	public function is_disconnected() {
		return ! $this->token->token_state_ok();
	}
	/**
	 * Current paid account has no rows left.
	 * Cached result uses latest values saved from get_subscription_info() call.
	 *
	 * @param bool $use_cached_subscription_info if true - use cached info, if exists, otherwise do an API request.
	 * @return bool
	 */
	public function is_limited_account( $use_cached_subscription_info = false ) {
		$info = $this->get_subscription_info( $use_cached_subscription_info ); // get cached or uncached subscription info.
		return is_array( $info ) && isset( $info['rows_left'] ) && 0 === $info['rows_left'] && 'No Subscription' !== $info['subscription'];
	}
	/**
	 * Current account is free
	 * Cached value updated on uncached function call only.
	 * Uncached call will call get_subscription_info() - and this may update is_limited_account() value.
	 *
	 * @param bool $use_cached_info true - return cached info (from option), false - query API, check, save to option and return result.
	 * @return bool true - account is free.
	 */
	public function is_free_account( $use_cached_info = true ) {
		static $result = null;
		if ( $use_cached_info ) {
			$value = get_option( self::OPTION_ACCOUNT_IS_FREE );
			return ! empty( $value );
		}
		if ( is_null( $result ) ) {
			$info   = $this->get_subscription_info(); // get fresh subscription info.
			$result = is_array( $info ) && isset( $info['subscription'] ) && 'no subscription' === strtolower( $info['subscription'] );
			// save result to cache.
			update_option( self::OPTION_ACCOUNT_IS_FREE, $result );
		}
		return $result;
	}
	/**
	 * Maybe disconnect Ahrefs using 'disconnect' link
	 *
	 * @param Ahrefs_Seo_Screen $screen
	 */
	public static function maybe_disconnect( Ahrefs_Seo_Screen $screen ) {
		if ( isset( $_GET['disconnect-ahrefs'] ) && check_admin_referer( $screen->get_nonce_name(), 'disconnect-ahrefs' ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			// disconnect Ahrefs.
			Ahrefs_Seo_Token::get()->disconnect();
			Ahrefs_Seo::get()->initialized_set( null );
			Ahrefs_Seo_Errors::clean_messages( 'ahrefs' );
			$params = [
				'page' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : Ahrefs_Seo::SLUG,
				'tab'  => isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : null,
				'step' => isset( $_GET['step'] ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : null,
			];
			wp_safe_redirect( remove_query_arg( [ 'disconnect-ahrefs' ], add_query_arg( $params, admin_url( 'admin.php' ) ) ) );
			die;
		}
	}
	/**
	 * Do a minimal delay between requests.
	 * Used to prevent API rate errors.
	 */
	private function maybe_do_a_pause() {
		$time_since = microtime( true ) - $this->last_query_time;
		if ( $time_since < self::API_MIN_DELAY && ! defined( 'AHREFS_SEO_IGNORE_DELAY' ) ) {
			Ahrefs_Seo::usleep( intval( ceil( self::API_MIN_DELAY - $time_since ) * 1000000 ) );
		}
		$this->last_query_time = microtime( true );
	}
	private function fail_counter_update( $increase = true ) {
		$value = max( 0, intval( get_option( 'ahrefs-seo-api-fail-counter', 0 ) ) + ( $increase ? 1 : -1 ) ); // increase or decrease current value.
		update_option( 'ahrefs-seo-api-fail-counter', $value );
	}
}
