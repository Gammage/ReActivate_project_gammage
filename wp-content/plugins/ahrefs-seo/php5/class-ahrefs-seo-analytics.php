<?php

namespace ahrefs\AhrefsSeo;

use Google_Client;
use InvalidArgumentException;
use Google_Service_Analytics;
use Google_Service_Webmasters;
use Google_Service_Analytics_Webproperty;
use Google_Service_Webmasters_SearchAnalyticsQueryRequest;
use Google_Service_Webmasters_SearchAnalyticsQueryResponse;
use Google_Service_Webmasters_ApiDataRow;
use Google_Service_Exception;
use Google_Service_Webmasters_SitesListResponse;
use Google_Service_GoogleAnalyticsAdmin;
use Google_Service_AnalyticsData;
use Google_Service_AnalyticsData_RunReportRequest;
use Google_Service_AnalyticsData_DateRange;
use Google_Service_AnalyticsData_Metric;
use Google_Service_AnalyticsData_Dimension;
use Google_Service_AnalyticsData_Entity;
use Google_Service_AnalyticsData_Filter;
use Google_Service_AnalyticsData_FilterExpression;
use Google_Service_AnalyticsData_StringFilter;
use Google_Service_AnalyticsData_BatchRunReportsRequest;
use Google_Service_AnalyticsReporting;
use Google_Service_AnalyticsReporting_DateRange;
use Google_Service_AnalyticsReporting_Metric;
use Google_Service_AnalyticsReporting_Dimension;
use Google_Service_AnalyticsReporting_ReportRequest;
use Google_Service_AnalyticsReporting_DimensionFilter;
use Google_Service_AnalyticsReporting_DimensionFilterClause;
use Google_Service_AnalyticsReporting_GetReportsRequest;
use Google_Task_Runner as Runner;
use Google_Http_Batch;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions as GuzzleRequestOptions;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
/**
 * Class for interacting with Google Analytics and Google Search Console API.
 */
class Ahrefs_Seo_Analytics extends Ahrefs_Seo_Abstract_Api {

	const OPTION_TOKENS                = 'ahrefs-seo-oauth2-tokens';
	const OPTION_LAST_ERROR            = 'ahrefs-seo-analytics-last-error';
	const OPTION_HAS_ACCOUNT_GA        = 'ahrefs-seo-has-analytics-account'; // has GA or GA4 profiles to select from.
	const OPTION_HAS_ACCOUNT_GA_RAW    = 'ahrefs-seo-has-analytics-account-raw'; // has account, note: account may not have any profile.
	const OPTION_HAS_ACCOUNT_GSC       = 'ahrefs-seo-has-gsc-account';
	const OPTION_GSC_SITES             = 'ahrefs-seo-has-gsc-sites';
	const OPTION_GSC_DISCONNECT_REASON = 'ahrefs-seo-has-gsc-disconnect-reason';
	/** Allow send queries once per second. */
	const API_MIN_DELAY        = 2.5;
	const SCOPE_ANALYTICS      = 'https://www.googleapis.com/auth/analytics.readonly';
	const SCOPE_SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';
	const GSC_KEYWORDS_LIMIT   = 10;
	/**
	 * Load page size for traffic requests.
	 */
	const QUERY_TRAFFIC_PER_PAGE = 20;
	/**
	 * Load first 100 results (pages) and search existing page slugs here.
	 */
	const QUERY_DETECT_GA_LIMIT = 100;
	/**
	 * Load first 1000 results (search phrases) and search existing page slugs here.
	 */
	const QUERY_DETECT_GSC_LIMIT = 1000;
	/**
	 * Page size for account details loading.
	 */
	const QUERY_LIST_GA_ACCOUNTS_PAGE_SIZE = 100;
	/** @var float[] Time when last visitors query to GA, GA4 or GSC run. */
	private $last_query_time = [];
	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected $message = '';
	/**
	 * @var array
	 */
	protected $service_error = [];
	/**
	 * @var string
	 */
	private $api_user = '';
	/**
	 * @var null|\Psr\Log\AbstractLogger
	 */
	private $logger;
	/**
	 * @var Google_Client|null
	 */
	private $client = null;
	/**
	 * @var array
	 */
	private $default_config = [
		// OAuth2 Settings, you can get these keys at https://code.google.com/apis/console .
		'oauth2_client_id'     => '616074445976-gce92a0p1ptkrgj6rl0jdpk7povts56a.apps.googleusercontent.com',
		'oauth2_client_secret' => 'JpBej-3XMNqXhGdRpgpSc7Y4',
	];
	/**
	 * @var null|string
	 */
	protected $token;
	/**
	 * @var string
	 */
	protected $ua_id = '';
	/**
	 * @var string
	 */
	protected $ua_name = '';
	/**
	 * @var string
	 */
	protected $ua_url = '';
	/**
	 * @var string
	 */
	protected $gsc_site = '';
	/**
	 * User's account (profiles) list for GA is not empty.
	 * Null if unknown.
	 *
	 * @var null|bool
	 */
	protected $has_ga_account;
	/**
	 * User has at least single GA account. This is not mean, that user has any accessible profile.
	 * Null if unknown.
	 *
	 * @var null|bool
	 */
	protected $has_ga_account_raw;
	/**
	 * User's account (profiles) list for GSC is not empty.
	 * Null if unknown.
	 *
	 * @var null|bool
	 */
	protected $has_gsc_account;
	/**
	 * Cached accounts (profiles) list for GA.
	 * Used for choice at Google accounts page.
	 *
	 * @var array|null
	 */
	protected $accounts_ga;
	/**
	 * Cached accounts (profiles) list for GA4.
	 * Used for choice at Google accounts page.
	 *
	 * @var array|null
	 */
	protected $accounts_ga4;
	/**
	 * Cached accounts list for GA.
	 * Used for choice at Google accounts page.
	 *
	 * @var array|null
	 */
	protected $accounts_ga_raw;
	/**
	 * Cached accounts list for GA4.
	 * Used for choice at Google accounts page.
	 *
	 * @var array|null
	 */
	protected $accounts_ga4_raw;
	/**
	 * Cached accounts list for GSC.
	 *
	 * @var array|null
	 */
	protected $accounts_gsc;
	/**
	 * @var Message|null
	 */
	private $disconnect_reason = null;
	/**
	 * Paused because last request returned rate error.
	 *
	 * @var bool
	 */
	private $gsc_paused = false;
	/** @var Ahrefs_Seo_Analytics */
	private static $instance;
	/**
	 * Return the instance
	 *
	 * @return Ahrefs_Seo_Analytics
	 */
	public static function get() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	public function __construct() {
		$this->api_user           = substr( 'w' . implode( '-', [ get_current_user_id(), get_current_blog_id(), ! empty( wp_parse_url( get_site_url(), PHP_URL_HOST ) ) ? wp_parse_url( get_site_url(), PHP_URL_HOST ) : '' ] ), 0, 40 );
		$this->has_ga_account     = get_option( self::OPTION_HAS_ACCOUNT_GA, null );
		$this->has_ga_account_raw = get_option( self::OPTION_HAS_ACCOUNT_GA_RAW, null );
		$this->has_gsc_account    = get_option( self::OPTION_HAS_ACCOUNT_GSC, null );
		$this->tokens_load();
	}
	/**
	 * Token is correct.
	 * Analytics and/or Search console enabled by scope credentials and user.
	 *
	 * @return bool
	 */
	public function is_token_set() {
		return ! empty( $this->token );
	}
	public function get_api_user() {
		return (string) $this->api_user;
	}
	private function get_token_scope_as_string() {
		if ( ! empty( $this->token ) ) {
			$token_data = is_string( $this->token ) ? json_decode( $this->token, true ) : $this->token; // accept both string and array.
			if ( is_array( $token_data ) && isset( $token_data['scope'] ) && is_string( $token_data['scope'] ) ) {
				return $token_data['scope'];
			}
		}
		return '';
	}
	/**
	 * Access to Google Analytics allowed and accounts (profiles) list is not empty
	 *
	 * @param bool $force_detection
	 *
	 * @return bool
	 */
	public function is_analytics_enabled( $force_detection = false ) {
		if ( ( $force_detection || is_null( $this->has_ga_account ) ) && false !== strpos( $this->get_token_scope_as_string(), self::SCOPE_ANALYTICS ) ) {
			$accounts_ga_all          = $this->load_accounts_list();
			$this->has_ga_account     = ! empty( $accounts_ga_all );
			$this->has_ga_account_raw = ! empty( $this->accounts_ga_raw ) || ! empty( $this->accounts_ga4_raw );
			update_option( self::OPTION_HAS_ACCOUNT_GA, $this->has_ga_account );
			update_option( self::OPTION_HAS_ACCOUNT_GA_RAW, $this->has_ga_account_raw );
		}
		return false !== strpos( $this->get_token_scope_as_string(), self::SCOPE_ANALYTICS ) && $this->has_ga_account || defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA;
	}
	/**
	 * User has at least single GA account.
	 * Cached result.
	 *
	 * @since 0.7.1
	 *
	 * @return bool
	 */
	public function is_analytics_has_accounts() {
		return ! empty( $this->has_ga_account_raw );
	}
	/**
	 * Access to Google Search Console allowed and accounts list is not empty
	 *
	 * @param bool $force_detection
	 *
	 * @return bool
	 */
	public function is_gsc_enabled( $force_detection = false ) {
		if ( ( $force_detection || is_null( $this->has_gsc_account ) ) && false !== strpos( $this->get_token_scope_as_string(), self::SCOPE_SEARCH_CONSOLE ) ) {
			if ( is_null( $this->accounts_gsc ) ) { // no existing value from another service call.
				$this->accounts_gsc = $this->load_gsc_accounts_list();
			}
			$this->has_gsc_account = ! empty( $this->accounts_gsc );
			update_option( self::OPTION_HAS_ACCOUNT_GSC, $this->has_gsc_account );
		}
		return false !== strpos( $this->get_token_scope_as_string(), self::SCOPE_SEARCH_CONSOLE ) && $this->has_gsc_account;
	}
	/**
	 * Access to GA enabled and account set in plugin options.
	 *
	 * @return bool
	 */
	public function is_ua_set() {
		return '' !== $this->ua_id && $this->is_analytics_enabled();
	}
	/**
	 * Access to GSC enabled and site set in plugin options.
	 *
	 * @return bool
	 */
	public function is_gsc_set() {
		if ( '' !== $this->gsc_site && $this->is_gsc_enabled() ) {
			return true;
		}
		return false;
	}
	/**
	 * Check that GSC used correct domain and set disconnect reason.
	 * If GSC site URL selected is not the same as WordPress site or GA profile  - should be treated as GSC not connected.
	 *
	 * @return bool False on error.
	 */
	private function gsc_check_domain() {
		$site_domain = $this->get_clean_domain();
		// single or multiple domains.
		$analytics_domains = $this->is_ua_set() ? array_map( [ $this, 'get_clean_domain' ], explode( '|', $this->ua_url ) ) : [];
		$gsc_domain        = $this->get_clean_domain( $this->gsc_site );
		$result            = ! empty( $analytics_domain ) && in_array( $gsc_domain, $analytics_domains, true ) || $site_domain === $gsc_domain;
		if ( ! $result ) {
			$this->set_gsc_disconnect_reason( sprintf( 'Google Search Console has an invalid domain (current domain: %s, selected: %s).', $site_domain, $gsc_domain ), null, false );
			return false;
		} else {
			// check credentials is not "siteUnverifiedUser".
			if ( '' !== $gsc_domain ) {
				$list = $this->load_gsc_accounts_list();
				foreach ( $list as $item ) {
					if ( $this->gsc_site === (string) $item['site'] && 'siteUnverifiedUser' === $item['level'] ) {
						$this->set_gsc_disconnect_reason( sprintf( 'Google Search Console has an invalid permission level (%s).', $item['level'] ), null, false );
						return false;
					}
				}
			}
		}
		return true;
	}
	/**
	 * Return GA selected ID
	 *
	 * @return string
	 */
	public function get_ua_id() {
		return $this->ua_id;
	}
	/**
	 * Return GSC selected site
	 *
	 * @return string
	 */
	public function get_gsc_site() {
		return $this->gsc_site;
	}
	/**
	 * Return GA selected name
	 *
	 * @return string
	 */
	public function get_ua_name() {
		return $this->ua_name;
	}
	/**
	 * Return GA selected url
	 *
	 * @return string
	 */
	public function get_ua_url() {
		return $this->ua_url;
	}
	/**
	 * Set GA and GSC accounts
	 *
	 * @param string $ua_id
	 * @param string $ua_name
	 * @param string $ua_url
	 * @param string $gsc_site
	 * @return void
	 */
	public function set_ua( $ua_id, $ua_name, $ua_url, $gsc_site = '' ) {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%s) (%s) (%s) (%s)', __METHOD__, $ua_id, $ua_name, $ua_url, $gsc_site ) );
		$is_gsc_updated = $this->get_gsc_site() !== $gsc_site;
		$is_ga_updated  = $this->get_ua_id() !== $ua_id;
		$token          = $this->token ?: null;
		update_option( self::OPTION_TOKENS, compact( 'token', 'ua_id', 'ua_name', 'ua_url', 'gsc_site' ) );
		$this->tokens_load();
		if ( $is_gsc_updated ) {
			$this->set_gsc_disconnect_reason( null ); // reset any error.
			if ( '' !== $gsc_site ) { // do not check if site is empty.
				if ( ! $this->gsc_check_domain() ) { // set error if domain is incorrect.
					$gsc_site = ''; // ... and reset gsc account.
					update_option( self::OPTION_TOKENS, compact( 'token', 'ua_id', 'ua_name', 'ua_url', 'gsc_site' ) );
					$this->tokens_load();
				}
			}
		}
		if ( $is_ga_updated ) {
			$this->set_ga_disconnect_reason( null ); // reset any error.
		}
	}
	/**
	 * Check that currently selected GA account has same domain in website property as current site has.
	 * Ignore empty value.
	 *
	 * @param string|null $ua_url Check current GA account if null.
	 * @return bool|null Null if nothing to check
	 */
	public function is_ga_account_correct( $ua_url = null ) {
		if ( is_null( $ua_url ) ) {
			$ua_url = $this->ua_url; // use current account.
		}
		if ( '' === $ua_url ) {
			return null; // nothing to check.
		}
		$domain = strtolower( Ahrefs_Seo::get_current_domain() );
		if ( 0 === strpos( $domain, 'www.' ) ) { // remove www. prefix from domain.
			$domain = substr( $domain, 4 );
		}
		$sites = explode( '|', $ua_url );
		foreach ( $sites as $site_url ) {
			$_website = strtolower( (string) wp_parse_url( $site_url, PHP_URL_HOST ) );
			if ( 0 === strpos( $_website, 'www.' ) ) { // remove www. prefix from domain.
				$_website = substr( $_website, 4 );
			}
			if ( $_website === $domain ) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Check that currently selected GSC account has same domain as current site has.
	 *
	 * @return bool|null Null if nothing to check
	 */
	public function is_gsc_account_correct() {
		if ( empty( $this->gsc_site ) ) {
			return null;
		}
		$domain   = $this->get_clean_domain();
		$_website = $this->get_clean_domain( (string) $this->gsc_site );
		return $_website === $domain;
	}
	/**
	 * Return Google Client static instance, until token is same.
	 *
	 * @return Google_Client
	 */
	private function create_client() {
		static $last_token = null;
		static $client     = null;
		// load fresh tokens.
		$this->tokens_load();
		if ( is_null( $client ) || $last_token !== $this->token ) {
			$client_id     = $this->default_config['oauth2_client_id'];
			$client_secret = $this->default_config['oauth2_client_secret'];
			$redirect_uri  = 'urn:ietf:wg:oauth:2.0:oob';
			$scopes        = [ self::SCOPE_ANALYTICS, self::SCOPE_SEARCH_CONSOLE ];
			$config        = [
				'retry'     => [
					'retries'       => 3,
					'initial_delay' => 0,
				],
				'retry_map' => array(
					'500'                   => Runner::TASK_RETRY_ALWAYS,
					'503'                   => Runner::TASK_RETRY_ALWAYS,
					'rateLimitExceeded'     => Runner::TASK_RETRY_ALWAYS,
					'userRateLimitExceeded' => Runner::TASK_RETRY_ALWAYS,
					6                       => Runner::TASK_RETRY_ALWAYS, // CURLE_COULDNT_RESOLVE_HOST.
					7                       => Runner::TASK_RETRY_ALWAYS, // CURLE_COULDNT_CONNECT.
					28                      => Runner::TASK_RETRY_ALWAYS, // CURLE_OPERATION_TIMEOUTED.
					35                      => Runner::TASK_RETRY_ALWAYS, // CURLE_SSL_CONNECT_ERROR.
					52                      => Runner::TASK_RETRY_ALWAYS, // CURLE_GOT_NOTHING.
					'quotaExceeded'         => Runner::TASK_RETRY_NEVER,
					'internalServerError'   => Runner::TASK_RETRY_NEVER,
					'backendError'          => Runner::TASK_RETRY_NEVER,
				),
			];
			$client        = new Google_Client( $config );
			// request offline access token.
			$client->setAccessType( 'offline' );
			$client->setClientSecret( $client_secret );
			$client->setScopes( $scopes );
			$client->setRedirectUri( $redirect_uri );
			$client->setClientId( $client_id );
			$client->setTokenCallback( [ $this, 'token_callback' ] );
			$client->setApplicationName( 'ahrefs-seo/' . AHREFS_SEO_VERSION . '-' . AHREFS_SEO_RELEASE );
			$this->client = $client; // cache Client instance.
			$path         = $this::get_cert_path();
			if ( ! empty( $path ) ) { // recreate http client with updated verify path in config.
				$http_client                              = $client->getHttpClient();
				$config                                   = $http_client->getConfig();
				$options[ GuzzleRequestOptions::VERIFY ]  = $path;
				$options[ GuzzleRequestOptions::TIMEOUT ] = 120;
				$options[ GuzzleRequestOptions::CONNECT_TIMEOUT ] = 15;
				$client->setHttpClient( $this->get_http_client( $options ) );
			}
			Ahrefs_Seo::breadcrumbs( sprintf( '%s Google Client version: %s, current token: %s', __METHOD__, $client->getLibraryVersion(), wp_json_encode( $this->token ) ) );
			if ( ! empty( $this->token ) ) {
				$client->setAccessToken( $this->token );
			}
			$last_token = $this->token;
		}
		// clean old logged data each time when new client required.
		if ( class_exists( '\\Psr\\Log\\AbstractLogger', true ) ) {
			// google api client v2.
			$this->logger = new Logger();
			$client->setLogger( $this->logger );
		}
		return $client;
	}
	/**
	 * Return new Guzzle HTTP client instance
	 *
	 * @since 0.7.3
	 *
	 * @param array<string, mixed> $options
	 * @return GuzzleClientInterface
	 */
	protected function get_http_client( array $options ) {
		return new GuzzleClient( $options );
	}
	/**
	 * Called on token update. Callback.
	 * Update the in-memory access token and save it.
	 *
	 * @since 0.7.2
	 *
	 * @param string $cache_key
	 * @param string $access_token
	 * @return void
	 */
	public function token_callback( $cache_key, $access_token ) {
		// Note: callback, do not use parameter types.
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		if ( ! is_null( $this->client ) ) {
			$token = $this->client->getAccessToken();
			// similar as default handler, but do not overwrite refresh token and scope.
			$token['access_token'] = (string) $access_token;
			$token['expires_in']   = 3600; // Google default.
			$token['created']      = time();
			$this->client->setAccessToken( $token );
			$this->tokens_save( $token );
		}
	}
	/**
	 * Get the ca bundle path if one exists.
	 *
	 * @since 0.7.2
	 *
	 * @return string|null
	 */
	public static function get_cert_path() {
		if ( version_compare( PHP_VERSION, '5.3.2' ) < 0 ) {
			return null;
		}
		return realpath( CaBundle::getSystemCaRootBundlePath() ) ?: null;
	}
	/**
	 * Return url for OAuth2, where user will see a code
	 *
	 * @return string
	 */
	public function get_oauth2_url() {
		try {
			$client = $this->create_client();
			return $client->createAuthUrl();
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		}
		return '#error-happened';
	}
	/**
	 * Check received code.
	 * Update options if it is ok.
	 *
	 * @param string $code
	 * @return bool
	 */
	public function check_token( $code ) {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%s)', __METHOD__, wp_json_encode( $code ) ) );
		try {
			$client = $this->create_client();
			if ( $this->is_token_set() ) {
				// another token exists? Disconnect it.
				$this->disconnect();
				$this->set_message( '' );
				// recreate client.
				$client = $this->create_client();
			}
			$client->authenticate( $code );
			$token = $client->getAccessToken();
			if ( ! empty( $token ) ) {
				Ahrefs_Seo::breadcrumbs( sprintf( '%s: (%s)', __METHOD__, wp_json_encode( $token ) ) );
				$this->tokens_save( $token );
			} else { // no error, but code was wrong.
				return false;
			}
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		} catch ( InvalidArgumentException $e ) {
			$this->set_message( $this->extract_message( $e ), $e );
			return false;
		} catch ( \Exception $e ) {
			$this->set_message( $this->extract_message( $e ), $e );
			return false;
		}
		return true;
	}
	/**
	 * Save token.
	 *
	 * @param string|array $token
	 * @return void
	 */
	private function tokens_save( $token ) {
		// note: do not use parameter type, may be string or array.
		if ( is_array( $token ) ) { // support for tokens from Google API client v2.
			$token = wp_json_encode( $token );
		}
		$ua_id    = $this->ua_id ?: '';
		$ua_name  = $this->ua_name ?: '';
		$ua_url   = $this->ua_url ?: '';
		$gsc_site = empty( $this->gsc_site ) ? '' : $this->gsc_site;
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%s) [(%s) (%s) (%s) (%s)]', __METHOD__, $token, $ua_id, $ua_name, $ua_url, $gsc_site ) );
		update_option( self::OPTION_TOKENS, compact( 'token', 'ua_id', 'ua_name', 'ua_url', 'gsc_site' ) );
		$this->tokens_load();
	}
	private function tokens_load() {
		static $prev_value = null;
		$data              = get_option( self::OPTION_TOKENS, [] );
		if ( $prev_value !== $data ) {
			Ahrefs_Seo::breadcrumbs( sprintf( '%s:%s ', __METHOD__, wp_json_encode( $data ) ) );
			if ( isset( $data['token'] ) && is_array( $data['token'] ) ) { // support for tokens from Google API client v2.
				$data['token'] = (string) wp_json_encode( $this->token );
			}
			$this->token    = isset( $data['token'] ) ? $data['token'] : '';
			$this->ua_id    = isset( $data['ua_id'] ) ? $data['ua_id'] : '';
			$this->ua_name  = isset( $data['ua_name'] ) ? $data['ua_name'] : '';
			$this->ua_url   = isset( $data['ua_url'] ) ? $data['ua_url'] : '';
			$this->gsc_site = isset( $data['gsc_site'] ) ? $data['gsc_site'] : '';
			$prev_value     = $data;
		}
	}
	/**
	 * Return array with ua accounts list
	 *
	 * @return array<array>
	 */
	public function load_accounts_list() {
		$result = [];
		try {
			// mix ga4 with ga.
			$ga4  = $this->load_accounts_list_ga4();
			$ga   = $this->load_accounts_list_ga();
			$data = array_merge( $ga, $ga4 );
			// sort results.
			usort(
				$data,
				function ( $a, $b ) {
					// order by account name.
					$diff = strcasecmp( $a['account_name'], $b['account_name'] );
					if ( 0 !== $diff ) {
						return $diff;
					}
					// then order by name.
					return strcasecmp( $a['name'], $b['name'] );
				}
			);
			// split by account, profile.
			foreach ( $data as $item ) {
				$account      = $item['account'];
				$account_name = $item['account_name'];
				$ua_id        = $item['ua_id'];
				$name         = $item['name'];
				$website      = $item['website'];
				if ( ! isset( $result[ $account ] ) ) {
					$result[ $account ] = [
						'account' => $account,
						'label'   => $account_name,
						'values'  => [],
					];
				}
				if ( ! isset( $result[ $account ]['values'][ $name ] ) ) {
					$result[ $account ]['values'][ $name ] = [];
				}
				$new_item = [
					'ua_id'   => $ua_id,
					'website' => $website,
				];
				$type     = null;
				if ( isset( $item['view'] ) ) {
					$type             = 'views';
					$new_item['view'] = $item['view'];
				} elseif ( isset( $item['stream'] ) ) {
					$type               = 'streams';
					$new_item['stream'] = $item['stream'];
				}
				if ( ! is_null( $type ) && ! isset( $result[ $account ]['values'][ $name ][ $type ] ) ) {
					$result[ $account ]['values'][ $name ][ $type ] = [];
				}
				$result[ $account ]['values'][ $name ][ $type ][] = $new_item;
			}
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'load GA accounts list' );
			$this->set_message( $message );
		}
		return $result;
	}
	/**
	 * Return array with ua accounts list from Google Analytics Admin API
	 *
	 * @since 0.7.3
	 *
	 * @return array<array>
	 */
	protected function load_accounts_list_ga4() {
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( is_array( $this->accounts_ga4 ) ) { // cached results from last call.
			return $this->accounts_ga4;
		}
		if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
			return [];
		}
		$result     = [];
		$accounts   = [];
		$properties = [];
		try {
			$client = $this->create_client();
			$admin  = new Google_Service_GoogleAnalyticsAdmin( $client );
			// accounts and properties list.
			$next_list = '';
			do {
				$params = [ 'pageSize' => $this::QUERY_LIST_GA_ACCOUNTS_PAGE_SIZE ];
				if ( ! empty( $next_list ) ) {
					$params['pageToken'] = $next_list;
				}
				$account_summaries = $admin->accountSummaries->listAccountSummaries( $params );
				$_accounts         = $account_summaries->getAccountSummaries();
				if ( count( $_accounts ) ) {
					foreach ( $_accounts as $_account ) {
						$account_name              = $_account->getAccount();
						$accounts[ $account_name ] = $_account->getDisplayName();
						$_properties               = $_account->getPropertySummaries();
						if ( count( $_properties ) ) {
							foreach ( $_properties as $_property ) {
								$properties[ $_property['property'] ] = [
									'account' => $account_name,
									'label'   => $_property['displayName'],
								];
							}
						}
					}
				}
				$next_list = $account_summaries->getNextPageToken();
			} while ( ! empty( $next_list ) );
			$this->accounts_ga4_raw = $accounts;
			// get web streams for each property: need website urls.
			$streams  = []; // index is property id, value is array with data url.
			$requests = []; // Pending requests, [ property_id => next page token ].
			try {
				$client->setUseBatch( true );
				// prepare all initial requests.
				foreach ( $properties as $_property_id => $_values ) {
					$requests[ $_property_id ] = '';
				}
				while ( ! empty( $requests ) ) {
					$pieces = array_splice( $requests, 0, 5 ); // execute up to 5 requests at once.
					$batch = $admin->createBatch();
					foreach ( $pieces as $_property_id => $next_page ) {
						$params = [ 'pageSize' => $this::QUERY_LIST_GA_ACCOUNTS_PAGE_SIZE ];
						if ( ! empty( $next_page ) ) {
							$params['pageToken'] = $next_page;
						}
						$request = $admin->properties_webDataStreams->listPropertiesWebDataStreams( "{$_property_id}", $params );
						$batch->add( $request, $_property_id );
					}
					$responses = [];
					try {
						$responses = $batch->execute();
						do_action_ref_array( 'ahrefs_seo_api_list_ga4', [ &$responses ] );
					} catch ( \Exception $e ) { // catch all errors.
						$this->set_message( $this->extract_message( $e ), $e );
						$this->on_error_received( $e );
						throw $e;
					}
					foreach ( $responses as $_property_id => $streams_list ) {
						$_property_id = str_replace( 'response-', '', $_property_id );
						$_streams     = $streams_list->getWebDataStreams();
						if ( count( $_streams ) ) {
							foreach ( $_streams as $_stream ) {
								if ( ! isset( $streams[ "{$_property_id}" ] ) ) {
									$streams[ "{$_property_id}" ] = [];
								}
								$streams[ "{$_property_id}" ][] = [
									'uri'   => $_stream['defaultUri'],
									'label' => $_stream['displayName'],
								];
							}
						}
						$next_list = $streams_list->getNextPageToken();
						if ( ! empty( $next_list ) ) {
							$requests[ "{$_property_id}" ] = $next_list;
						}
					}
				}
			} finally {
				$client->setUseBatch( false );
			}
			if ( ! empty( $accounts ) && ! empty( $properties ) ) {
				foreach ( $properties as $property_id => $value ) {
					$account_id     = (string) $value['account'];
					$account_number = isset( explode( '/', $account_id, 2 )[1] ) ? explode( '/', $account_id, 2 )[1] : '';
					$property_label = $value['label'];
					$account_label  = isset( $accounts[ $account_id ] ) ? $accounts[ $account_id ] : '---';
					if ( ! empty( $streams[ $property_id ] ) ) {
						foreach ( $streams[ $property_id ] as $stream ) {
							$uri          = $stream['uri'];
							$stream_label = $stream['label'];
							$result[]     = [
								'ua_id'        => $property_id,
								'account'      => $account_number,
								'account_name' => $account_label,
								'name'         => $property_label,
								'stream'       => $stream_label,
								'website'      => $uri,
							];
						}
					}
				}
			}
			$this->accounts_ga4 = $result;
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		} catch ( Google_Service_Exception $e ) {
			Ahrefs_Seo::breadcrumbs( 'Events ' . wp_json_encode( $this->get_logged_events() ) );
			Ahrefs_Seo::notify( $e );
			$this->set_message( $this->extract_message( $e, 'GA4 list accounts failed.' ) );
		} catch ( \Exception $e ) {
			$this->set_message( $this->extract_message( $e, 'GA4 list accounts failed.' ), $e );
		}
		return $result;
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	/**
	 * Return array with ua accounts list from Google Analytics Management API
	 *
	 * @since 0.7.3
	 *
	 * @return array<array>
	 */
	protected function load_accounts_list_ga() {
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( is_array( $this->accounts_ga ) ) { // cached results from last call.
			return $this->accounts_ga;
		}
		if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
			return [
				[
					'ua_id'        => 'AHREFS_SEO_NO_GA',
					'account'      => 'AHREFS_SEO_NO_GA',
					'account_name' => 'AHREFS_SEO_NO_GA',
					'name'         => 'AHREFS_SEO_NO_GA',
					'view'         => 'default',
					'website'      => 'https://' . Ahrefs_Seo::get_current_domain(),
				],
			];
		}
		$result = [];
		// do this call earlier, maybe it is no sence to make another calls if no accounts.
		try {
			$client    = $this->create_client();
			$analytics = new Google_Service_Analytics( $client );
			$ua_list   = $analytics->management_webproperties->listManagementWebproperties( '~all' );
			do_action_ref_array( 'ahrefs_seo_api_list_ga_webproperties', [ &$ua_list ] );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			return [];
		} catch ( \Exception $e ) {
			$this->handle_exception( $e, false, true, false ); // do not save message.
			$this->set_message( $this->extract_message( $e, 'GA list accounts failed.' ) );
			return [];
		}
		if ( empty( $ua_list ) ) {
			return [];
		}
		$data = $ua_list->getItems();
		try {
			$accounts_list = $analytics->management_accounts->listManagementAccounts();
			do_action_ref_array( 'ahrefs_seo_api_list_ga_accounts', [ &$accounts_list ] );
		} catch ( \Exception $e ) {
			$this->handle_exception( $e );
			$accounts_list = null;
		}
		$accounts = [];
		if ( ! empty( $accounts_list ) ) {
			foreach ( $accounts_list->getItems() as $account ) {
				$accounts[ $account->getId() ] = $account->getName();
			}
			$this->accounts_ga_raw = array_values( $accounts );
		}
		/*
		Workaround to extract defaultProfileId, which some of the older GA accounts lack
		*/
		try {
			$profiles_list = $analytics->management_profiles->listManagementProfiles( '~all', '~all' );
			do_action_ref_array( 'ahrefs_seo_api_list_ga_profiles', [ &$profiles_list ] );
		} catch ( \Exception $e ) {
			$this->handle_exception( $e );
			$profiles_list = null;
		}
		$profiles_groups = [];
		if ( ! empty( $profiles_list ) ) {
			foreach ( $profiles_list->getItems() as $profile ) {
				$_web_property_id = $profile->getWebPropertyId();
				if ( ! isset( $profiles_groups[ $_web_property_id ] ) ) {
					$profiles_groups[ $_web_property_id ] = [];
				}
				$profiles_groups[ $_web_property_id ][] = [
					'id'      => $profile->getId(),
					'name'    => $profile->getName(),
					'website' => $profile->getWebsiteUrl(),
				];
			}
		}
		if ( ! empty( $data ) ) {
			/** @var Google_Service_Analytics_Webproperty $item */
			foreach ( $data as $item ) {
				if ( isset( $profiles_groups[ $item->id ] ) ) {
					foreach ( $profiles_groups[ $item->id ] as $_profile ) {
						$result[] = [
							'ua_id'        => $_profile['id'],
							'account'      => $item->accountId,
							'account_name' => isset( $accounts[ $item->accountId ] ) ? $accounts[ $item->accountId ] : '---',
							'name'         => $item->name,
							'view'         => $_profile['name'],
							'website'      => $_profile['website'],
						];
					}
				} else {
					// fill default choice.
					$result[] = [
						'ua_id'        => $item->defaultProfileId,
						'account'      => $item->accountId,
						'account_name' => isset( $accounts[ $item->accountId ] ) ? $accounts[ $item->accountId ] : '---',
						'name'         => $item->name,
						'view'         => 'default',
						'website'      => $item->websiteUrl,
					];
				}
			}
		}
		$this->accounts_ga = $result;
		return $result;
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	/**
	 * GA account: find items with the current domain and do some queries here
	 * Set found account as selected.
	 *
	 * @return string|null
	 */
	public function find_recommended_ga_id() {
		if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
			return 'AHREFS_SEO_NO_GA';
		}
		$list = $this->load_accounts_list();
		// recommended results, with the same domain in websiteUrl.
		$recommended = [];
		$details     = [];
		$domain      = strtolower( Ahrefs_Seo::get_current_domain() );
		if ( 0 === strpos( $domain, 'www.' ) ) { // remove www. prefix from domain.
			$domain = substr( $domain, 4 );
		}
		foreach ( $list as $account ) {
			if ( ! empty( $account['values'] ) ) {
				foreach ( $account['values'] as $property_name => $item ) {
					if ( isset( $item['views'] ) && count( $item['views'] ) ) {
						foreach ( $item['views'] as $view ) {
							if ( $this->is_ga_account_correct( $view['website'] ) ) {
								$recommended[]             = $view['ua_id'];
								$details[ $view['ua_id'] ] = [
									'name'    => $view['view'],
									'website' => $view['website'],
								];
							}
						}
					}
					if ( isset( $item['streams'] ) && count( $item['streams'] ) ) {
						$ua_id    = $item['streams'][0]['ua_id'];
						$websites = implode(
							'|',
							array_map(
								function ( $stream ) {
									return $stream['website'];
								},
								$item['streams']
							)
						);
						if ( $this->is_ga_account_correct( $websites ) ) {
							$recommended[]     = $ua_id;
							$details[ $ua_id ] = [
								'name'    => $property_name,
								'website' => $websites,
							];
						}
					}
				}
			}
		}
		if ( ! count( $recommended ) ) {
			return null;
		}
		$counts = $this->check_ga_using_top_traffic_pages( $recommended );
		if ( is_null( $counts ) ) {
			return null;
		}
		arsort( $counts );
		reset( $counts );
		$ua_id = key( $counts );
		// set this account.
		if ( isset( $details[ $ua_id ] ) ) {
			$value = $details[ $ua_id ];
			wp_cache_flush();
			$this->tokens_load();
			$this->set_ua( "{$ua_id}", $value['name'], $value['website'], $this->gsc_site );
		}
		return (string) $ua_id;
	}
	/**
	 * GSC account: find items with the current domain and do some queries here.
	 * Set found account as selected.
	 *
	 * @return string|null
	 */
	public function find_recommended_gsc_id() {
		$this->set_gsc_disconnect_reason( null ); // clean any previous error.
		$list = $this->load_gsc_accounts_list();
		// recommended results, with the same domain in websiteUrl.
		$recommended = [];
		$domain      = $this->get_clean_domain();
		foreach ( $list as $item ) {
			$_website = $this->get_clean_domain( (string) $item['site'] );
			if ( $_website === $domain && 'siteUnverifiedUser' !== $item['level'] ) {
				$recommended[] = $item['site'];
			}
		}
		if ( ! count( $recommended ) ) {
			return null;
		}
		$counts = [];
		foreach ( $recommended as $site ) {
			$counts[ $site ] = $this->check_gsc_using_bulk_results( $site );
		}
		arsort( $counts );
		reset( $counts );
		$site = key( $counts );
		// set this account.
		wp_cache_flush();
		$this->tokens_load();
		$this->set_ua( $this->ua_id, $this->ua_name, $this->ua_url, "{$site}" );
		return (string) $site;
	}
	/**
	 * Return number of pages found in GA or GA4 account.
	 *
	 * @param string[] $ua_ids
	 * @return null|array<string, int|null> Index is ua_id, value is number of found pages.
	 */
	private function check_ga_using_top_traffic_pages( array $ua_ids ) {
		if ( ! $this->is_analytics_enabled() ) {
			return null;
		}
		$results    = [];
		$start_date = date( 'Y-m-d', time() - 3 * MONTH_IN_SECONDS );
		$end_date   = date( 'Y-m-d' );
		$ua_ids_ga  = [];
		$ua_ids_ga4 = [];
		foreach ( $ua_ids as $ua_id ) {
			if ( 0 === strpos( $ua_id, 'properties/' ) ) {
				$ua_ids_ga4[] = $ua_id;
			} else {
				$ua_ids_ga[] = $ua_id;
			}
		}
		if ( count( $ua_ids_ga ) ) {
			$results = $this->get_found_pages_by_ua_id_ga( $ua_ids_ga, $start_date, $end_date );
		}
		if ( count( $ua_ids_ga4 ) ) {
			$results = $results + $this->get_found_pages_by_ua_id_ga4( $ua_ids_ga4, $start_date, $end_date ); // save indexes.
		}
		return $results;
	}
	/**
	 * Check GSC accounts, return number of pages with results.
	 *
	 * @param string $gsc_site
	 * @return int|null
	 */
	private function check_gsc_using_bulk_results( $gsc_site ) {
		if ( ! $this->is_gsc_enabled() ) {
			return null;
		}
		if ( ! $this->is_gsc_enabled() ) {
			$this->set_message( 'Google Search Console disconnected.' );
			$this->service_error = [ [ 'reason' => 'internal-no-token' ] ];
			return null;
		}
		$result     = [];
		$start_date = date( 'Y-m-d', time() - 3 * MONTH_IN_SECONDS );
		$end_date   = date( 'Y-m-d' );
		$parameters = [
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'dimensions' => [ 'page' ],
			'rowLimit'   => $this::QUERY_DETECT_GSC_LIMIT,
			'startRow'   => 0,
		];
		try {
			$client             = $this->create_client();
			$service_webmasters = new Google_Service_Webmasters( $client );
			// https://developers.google.com/webmaster-tools/search-console-api-original/v3/searchanalytics/query .
			$post_body = new Google_Service_Webmasters_SearchAnalyticsQueryRequest( $parameters );
			$this->maybe_do_a_pause( 'gsc' );
			/**
			 * @var \Google_Service_Webmasters_SearchAnalyticsQueryResponse $response_total
			 */
			$response_total = $service_webmasters->searchanalytics->query( $gsc_site, $post_body );
			$this->maybe_do_a_pause( 'gsc', true );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			return null;
		} catch ( \Exception $e ) {
			$this->maybe_do_a_pause( 'gsc', true );
			// do not handle error, no need to show it or disconnect an account.
			return null;
		}
		$result = []; // page urls, received from GSC.
		if ( ! empty( $response_total ) ) {
			/**
			 * @var \Google_Service_Webmasters_ApiDataRow $row
			 */
			foreach ( $response_total->getRows() as $row ) {
				if ( $row instanceof Google_Service_Webmasters_ApiDataRow ) {
					if ( $row->getClicks() > 0 ) { // use only pages with traffic > 0.
						// key[0] is a page url.
						$result[] = ! empty( wp_parse_url( isset( $row->getKeys()[0] ) ? $row->getKeys()[0] : '', PHP_URL_PATH ) ) ? wp_parse_url( isset( $row->getKeys()[0] ) ? $row->getKeys()[0] : '', PHP_URL_PATH ) : '';
					}
				}
			}
		}
		$result = array_unique( $result );
		$count  = 0;
		if ( count( $result ) ) {
			array_walk(
				$result,
				function ( $slug ) use ( &$count ) {
					$post = get_page_by_path( $slug, OBJECT, [ 'post', 'page' ] );
					if ( $post instanceof \WP_Post ) {
						$count++;
					}
				}
			);
		}
		return $count;
	}
	/**
	 * Return array with Google Search Console accounts list
	 *
	 * @param bool $cached_only
	 * @return array<array>
	 */
	public function load_gsc_accounts_list( $cached_only = false ) {
        // phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.NotSnakeCase
		if ( is_array( $this->accounts_gsc ) ) { // cached results from last call.
			return $this->accounts_gsc;
		}
		if ( $cached_only ) {
			return (array) json_decode( '' . get_option( self::OPTION_GSC_SITES, '' ), true );
		}
		$result = [];
		try {
			$client             = $this->create_client();
			$service_webmasters = new Google_Service_Webmasters( $client );
			/**
			 * @var Google_Service_Webmasters_SitesListResponse
			 */
			$sites_list = $service_webmasters->sites->listSites();
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			return [];
		} catch ( \Exception $e ) {
			$this->handle_exception( $e, false, true, false ); // do not save message.
			$this->set_message( $this->extract_message( $e, 'GSC list accounts failed.' ) );
			return [];
		}
		if ( ! empty( $sites_list ) ) {
			/**
			 * @var \Google_Service_Webmasters_WmxSite $account
			 */
			foreach ( $sites_list->getSiteEntry() as $account ) {
				$url = $account->getSiteUrl();
				if ( ! is_null( $url ) ) {
					$result[] = [
						'site'   => $url ? $url : '---',
						'domain' => wp_parse_url( $url, PHP_URL_HOST ) ? strtolower( wp_parse_url( $url, PHP_URL_HOST ) ) : '---',
						'scheme' => wp_parse_url( $url, PHP_URL_SCHEME ) ? strtolower( wp_parse_url( $url, PHP_URL_SCHEME ) ) : '---',
						'level'  => $account->getPermissionLevel(),
					];
				}
			}
		}
		// sort results.
		usort(
			$result,
			function ( $a, $b ) {
				// order by account name.
				$diff = $a['domain'] < $b['domain'] ? -1 : ( $a['domain'] == $b['domain'] ? 0 : 1 );
				if ( 0 !== $diff ) {
					return $diff;
				}
				// then order by name.
				return $a['scheme'] < $b['scheme'] ? -1 : ( $a['scheme'] == $b['scheme'] ? 0 : 1 );
			}
		);
		$this->accounts_gsc = $result;
		update_option( self::OPTION_GSC_SITES, wp_json_encode( $this->accounts_gsc ) );
		return $result;
        // phpcs:enable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar,WordPress.NamingConventions.ValidVariableName.NotSnakeCase
	}
	/**
	 * Get last error message from Analytics API.
	 *
	 * @param bool $return_and_clear_saved_message true - return and clear message from option, false - return current message.
	 * @return string Error message or empty string.
	 */
	public function get_message( $return_and_clear_saved_message = false ) {
		if ( $return_and_clear_saved_message ) {
			$error = '' . get_option( self::OPTION_LAST_ERROR, '' );
			if ( '' !== $error ) {
				$this->set_message( '' );
			}
			return $error;
		}
		return $this->message;
	}
	/**
	 * Set error message. Submit report if Exception parameter is set.
	 * Save 'google notice' message.
	 *
	 * @param string|null     $message Message, null if no need to save.
	 * @param \Exception|null $e
	 * @param string|null     $request
	 * @param string          $type 'notice', 'error', 'error-single'.
	 * @return void
	 */
	public function set_message( $message = null, \Exception $e = null, $request = null, $type = 'error' ) {
		if ( ! is_null( $message ) ) {
			if ( '' !== $message ) {
				Ahrefs_Seo_Errors::save_message( 'google', $message, $type );
			} else { // clean messages.
				Ahrefs_Seo_Errors::clean_messages( 'google' );
			}
			$this->message = $message;
			update_option( self::OPTION_LAST_ERROR, $message );
		}
		if ( ! is_null( $e ) ) {
			Ahrefs_Seo::breadcrumbs( 'Events ' . wp_json_encode( $this->get_logged_events() ) . ( ! empty( $request ) ? "\nRequest: " . $request : '' ) );
			Ahrefs_Seo::notify( $e );
		}
	}
	/**
	 * Return service error or empty array
	 *
	 * @return array<array>
	 */
	public function get_service_error() {
		return (array) $this->service_error;
	}
	/**
	 * Remove existing token.
	 */
	public function disconnect() {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s', __METHOD__ ) );
		$this->tokens_load();
		if ( ! empty( $this->token ) && ! defined( 'AHREFS_SEO_PRESERVE_TOKEN' ) ) {
			try {
				$client = $this->create_client();
				$client->revokeToken( $client->getAccessToken() );
			} catch ( \Error $e ) {
				$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
				$this->set_message( $message );
			} catch ( \Exception $e ) {
				Ahrefs_Seo::breadcrumbs( 'Events ' . wp_json_encode( $this->get_logged_events() ) );
				Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage() );
			}
			$this->set_message( 'Google account disconnected.' );
		}
		delete_option( self::OPTION_TOKENS );
		delete_option( self::OPTION_HAS_ACCOUNT_GA );
		delete_option( self::OPTION_HAS_ACCOUNT_GA_RAW );
		delete_option( self::OPTION_HAS_ACCOUNT_GSC );
		delete_option( self::OPTION_GSC_SITES );
		delete_option( self::OPTION_GSC_DISCONNECT_REASON );
		wp_cache_flush();
		$this->token              = null;
		$this->ua_id              = '';
		$this->ua_name            = '';
		$this->ua_url             = '';
		$this->gsc_site           = '';
		$this->accounts_ga_raw    = null;
		$this->accounts_ga        = null;
		$this->accounts_ga4       = null;
		$this->accounts_ga4_raw   = null;
		$this->accounts_gsc       = null;
		$this->has_ga_account     = null;
		$this->has_ga_account_raw = null;
		$this->has_gsc_account    = null;
	}
	/**
	 * Get visitors traffic by type for page
	 *
	 * @param array<int|string, string>|null $page_slugs Page url starting with '/'.
	 * @param string                         $start_date
	 * @param string                         $end_date
	 * @param null|int                       $max_results
	 * @param null|string                    $ua_id
	 *
	 * @return array<int|string, array<string, mixed>> Array, 'slug' => [ traffic type => visitors number].
	 */
	public function get_visitors_by_page( array $page_slugs = null, $start_date, $end_date, $max_results = null, $ua_id = null ) {
		// is Analytics enabled?
		if ( ! $this->is_analytics_enabled() ) {
			$this->set_message( 'Analytics disconnected.' );
			$this->service_error = [ [ 'reason' => 'internal-no-token' ] ];
			return null;
		}
		if ( is_null( $ua_id ) && ! $this->is_ua_set() ) {
			$this->set_message( 'Please choose Analytics profile.' );
			$this->service_error = [ [ 'reason' => 'internal-no-profile' ] ];
			return null;
		}
		if ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) {
			if ( is_null( $page_slugs ) ) {
				return [];
			} else {
				$result = [];
				foreach ( $page_slugs as $page_slug ) {
					$result[ $page_slug ] = apply_filters(
						'ahrefs_seo_no_ga_visitors_by_page',
						[
							'total'          => 10,
							'Organic Search' => 5,
						],
						$page_slug,
						$start_date,
						$end_date
					);
				}
				return $result;
			}
		}
		$result = null;
		if ( 0 === strpos( is_null( $ua_id ) ? $this->ua_id : $ua_id, 'properties/' ) ) {
			$result = $this->get_visitors_by_page_ga4( $page_slugs, $start_date, $end_date, $max_results, $ua_id );
		} else {
			$result = $this->get_visitors_by_page_ga( $page_slugs, $start_date, $end_date, $max_results, $ua_id );
		}
		// add total => 0 to each missing slug.
		if ( ! is_null( $result ) && ! is_null( $page_slugs ) ) {
			foreach ( $page_slugs as $_slug ) {
				if ( ! isset( $result[ $_slug ] ) ) {
					$result[ $_slug ] = [ 'total' => 0 ];
				}
			}
		}
		Ahrefs_Seo::breadcrumbs( 'get_visitors_by_page: ' . wp_json_encode( $page_slugs ) . ' results: ' . wp_json_encode( $result ) );
		return $result;
	}
    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	/**
	 * Get visitors traffic by type for page for GA property, use Google Analytics Reporting API version 4.
	 *
	 * @since 0.7.3
	 *
	 * @param array<int|string, string>|null $page_slugs_list Page url starting with '/'.
	 * @param string                         $start_date
	 * @param string                         $end_date
	 * @param null|int                       $max_results
	 * @param null|string                    $ua_id
	 *
	 * @return array<int|string, array<string, mixed>> Array, 'slug' => [ traffic type => visitors number].
	 */
	public function get_visitors_by_page_ga( array $page_slugs_list = null, $start_date, $end_date, $max_results = null, $ua_id = null ) {
		$result = [];
		$data   = [];
		try {
			$client             = $this->create_client();
			$analyticsreporting = new Google_Service_AnalyticsReporting( $client );
			$continue           = true;
			if ( is_null( $ua_id ) ) {
				$ua_id = $this->ua_id;
			}
			$page_slugs = is_null( $page_slugs_list ) ? [ null ] : $page_slugs_list; // receive pages info without slug filter.
			$per_page      = is_null( $max_results ) ? $this::QUERY_TRAFFIC_PER_PAGE : $max_results;
			$pages_to_load = array_map(
				function ( $slug ) {
					return [
						'slug'       => $slug,
						'next_token' => null,
					]; // later we will add next_token or remove item from the list.
				},
				$page_slugs
			);
			do {
				try {
					$requests = []; // up to 5 requests allowed.
					$data     = null;
					// analytics parameters.
					$params = [ 'quotaUser' => $this->get_api_user() ];
					// get results from google analytics.
					try {
						$this->maybe_do_a_pause( 'ga' );
						foreach ( $pages_to_load as $page_to_load ) {
							$page_slug  = $page_to_load['slug'];
							$next_token = isset( $page_to_load['next_token'] ) ? $page_to_load['next_token'] : null;
							// Create the DateRange object.
							$dateRange = new Google_Service_AnalyticsReporting_DateRange();
							$dateRange->setStartDate( $start_date );
							$dateRange->setEndDate( $end_date );
							// Create the Metrics object.
							$metric1 = new Google_Service_AnalyticsReporting_Metric();
							$metric1->setExpression( 'ga:uniquePageviews' );
							// Create the Dimensions object.
							$dimension1 = new Google_Service_AnalyticsReporting_Dimension();
							$dimension1->setName( 'ga:pagePath' );
							/** @link https://ga-dev-tools.appspot.com/dimensions-metrics-explorer/#ga:channelGrouping */
							$dimension2 = new Google_Service_AnalyticsReporting_Dimension();
							$dimension2->setName( 'ga:channelGrouping' );
							// Create the ReportRequest object.
							$request = new Google_Service_AnalyticsReporting_ReportRequest();
							if ( ! is_null( $page_slug ) ) {
								// Create the DimensionFilter.
								$dimensionFilter = new Google_Service_AnalyticsReporting_DimensionFilter();
								$dimensionFilter->setDimensionName( 'ga:pagePath' );
								$dimensionFilter->setOperator( 'EXACT' );
								$dimensionFilter->setExpressions( array( $page_slug ) );
								// Create the DimensionFilterClauses.
								$dimensionFilterClause = new Google_Service_AnalyticsReporting_DimensionFilterClause();
								$dimensionFilterClause->setFilters( array( $dimensionFilter ) );
								$request->setDimensionFilterClauses( array( $dimensionFilterClause ) );
							}
							$request->setViewId( $ua_id );
							$request->setDateRanges( $dateRange );
							$request->setDimensions( array( $dimension1, $dimension2 ) );
							$request->setMetrics( array( $metric1 ) );
							$request->setPageSize( $per_page );
							if ( ! empty( $next_token ) ) {
								$request->setPageToken( $next_token );
							}
							$requests[] = $request;
						}
						$body = new Google_Service_AnalyticsReporting_GetReportsRequest();
						$body->setReportRequests( $requests );
						$data = $analyticsreporting->reports->batchGet( $body, $params );
						do_action_ref_array( 'ahrefs_seo_api_visitors_by_page_ga', [ &$data ] );
						$this->maybe_do_a_pause( 'ga', true );
					} catch ( Google_Service_Exception $e ) { // catch recoverable errors.
						$this->maybe_do_a_pause( 'ga', true );
						$this->service_error = $e->getErrors();
						$this->handle_exception( $e );
						$this->on_error_received( $e, $page_slugs_list );
						throw $e;
					} catch ( GuzzleRequestException $e ) { // catch recoverable errors.
						$this->maybe_do_a_pause( 'ga', true );
						$this->handle_exception( $e );
						$this->on_error_received( $e, $page_slugs_list );
						throw $e;
					}
					$continue = false;
					if ( ! is_null( $data ) ) {
						$reports = $data->getReports();
						if ( ! empty( $reports ) ) {
							foreach ( $reports as $index => $report ) {
								$data_items                            = $report->getData();
								$pages_to_load[ $index ]['next_token'] = $report->getNextPageToken();
								// load details from rows.
								$rows = $data_items->getRows();
								if ( ! empty( $rows ) ) {
									foreach ( $rows as $row ) {
										list($_slug, $_type) = $row->getDimensions(); // page slug + traffic type.
										$_metrics            = $row->getMetrics();
										$_traffic_count      = isset( $_metrics[0]->getValues()[0] ) ? $_metrics[0]->getValues()[0] : 0;
										if ( ! isset( $result[ $_slug ] ) ) {
											$result[ $_slug ] = [];
										}
										if ( ! isset( $result[ $_slug ][ "{$_type}" ] ) ) {
											$result[ $_slug ][ "{$_type}" ] = (int) $_traffic_count;
											$result[ $_slug ]['total']      = (int) $_traffic_count + ( isset( $result[ $_slug ]['total'] ) ? $result[ $_slug ]['total'] : 0 );
										} else {
											$result[ $_slug ][ "{$_type}" ] += (int) $_traffic_count;
											$result[ $_slug ]['total']      += (int) $_traffic_count;
										}
									}
								}
								if ( ! is_null( $max_results ) && ( count( $rows ) >= $max_results || count( $result ) >= $max_results ) ) {
									$pages_to_load[ $index ]['next_token'] = null; // do not load more.
								}
							}
						} else {
							$pages_to_load = [];
						}
					} else {
						$pages_to_load = [];
					}
					// remove finished pages (without next_token) from load list.
					$pages_to_load = array_values(
						array_filter(
							$pages_to_load,
							function ( $value ) {
								return ! empty( $value['next_token'] );
							}
						)
					);
				} catch ( \Error $e ) {
					$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
					$this->set_message( $message );
				} catch ( \Exception $e ) {
					$this->handle_exception( $e, true );
					return $this->prepare_answer( $page_slugs_list, 'connection_error' );
				}
				// load until any next page exists, but load only first page with results for the generic request without page ($page_slugs_list is null).
			} while ( ! empty( $pages_to_load ) && ! is_null( $page_slugs_list ) && ! is_null( $data ) );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		}
		return $result;
	}
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	/**
	 * Get visitors traffic by type for page for GA4 property, use Google Analytics Data API
	 *
	 * @since 0.7.3
	 *
	 * @param null|array<int|string, string> $page_slugs_list Page url starting with '/'.
	 * @param string                         $start_date
	 * @param string                         $end_date
	 * @param null|int                       $max_results
	 * @param null|string                    $ua_id
	 *
	 * @return array<int|string, array<string, mixed>> Array, 'slug' => [ traffic type => visitors number].
	 */
	protected function get_visitors_by_page_ga4( array $page_slugs_list = null, $start_date, $end_date, $max_results = null, $ua_id = null ) {
		$result = [];
		try {
			$client     = $this->create_client();
			$data       = [];
			$analytics4 = new Google_Service_AnalyticsData( $client );
			$continue   = true;
			if ( is_null( $ua_id ) ) {
				$ua_id = $this->ua_id;
			}
			// numeric part only.
			$property_id = str_replace( 'properties/', '', $ua_id );
			$page_slugs  = is_null( $page_slugs_list ) ? [ null ] : $page_slugs_list; // receive pages info without slug filter.
			$per_page      = is_null( $max_results ) ? $this::QUERY_TRAFFIC_PER_PAGE : $max_results;
			$pages_to_load = array_map(
				function ( $slug ) {
					return [
						'slug'   => $slug,
						'offset' => 0,
					]; // later we will add next_token or remove item from the list.
				},
				$page_slugs
			);
			do {
				try {
					$rows = [];
					$data = null;
					// analytics additional parameters.
					$params = [ 'quotaUser' => $this->get_api_user() ];
					// get results from GA4.
					try {
						$this->maybe_do_a_pause( 'ga4' );
						$requests = [];
						foreach ( $pages_to_load as $page_to_load ) {
							$page_slug = $page_to_load['slug'];
							$offset    = $page_to_load['offset'];
							// Create the DateRange object.
							$dateRange = new Google_Service_AnalyticsData_DateRange();
							$dateRange->setStartDate( $start_date );
							$dateRange->setEndDate( $end_date );
							// Create the Metrics object.
							/** @link https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#metrics */
							$metric = new Google_Service_AnalyticsData_Metric();
							$metric->setName( 'screenPageViews' ); // "ga:uniquePageviews"
							// Create the Dimension object.
							/** @link https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#dimensions */
							$dimension1 = new Google_Service_AnalyticsData_Dimension();
							$dimension1->setName( 'pagePath' ); // "ga:pagePath".
							$dimension2 = new Google_Service_AnalyticsData_Dimension();
							$dimension2->setName( 'sessionDefaultChannelGrouping' ); // "ga:channelGrouping".
							$entity = new Google_Service_AnalyticsData_Entity();
							$entity->setPropertyId( $property_id ); // only numeric part.
							// Create the ReportRequest object.
							$request = new Google_Service_AnalyticsData_RunReportRequest();
							$request->setEntity( $entity );
							$request->setDateRanges( $dateRange );
							$request->setMetrics( array( $metric ) );
							$request->setDimensions( array( $dimension1, $dimension2 ) );
							$request->setLimit( $per_page );
							$request->setOffset( $offset );
							if ( ! is_null( $page_slug ) ) { // request for specified url.
								$string_filter = new Google_Service_AnalyticsData_StringFilter();
								$string_filter->setValue( $page_slug );
								$string_filter->setMatchType( 'EXACT' );
								$filter = new Google_Service_AnalyticsData_Filter();
								$filter->setFieldName( 'pagePath' );
								$filter->setStringFilter( $string_filter );
								$dimension_filter = new Google_Service_AnalyticsData_FilterExpression();
								$dimension_filter->setFilter( $filter );
								$request->setDimensionFilter( $dimension_filter );
							}
							$requests[] = $request;
						}
						$batch = new Google_Service_AnalyticsData_BatchRunReportsRequest();
						$batch->setRequests( $requests );
						$entity = new Google_Service_AnalyticsData_Entity();
						$entity->setPropertyId( $property_id ); // only numeric part.
						$batch->setEntity( $entity );
						$data = $analytics4->v1alpha->batchRunReports( $batch, $params );
						do_action_ref_array( 'ahrefs_seo_api_visitors_by_page_ga4', [ &$data ] );
						$this->maybe_do_a_pause( 'ga4', true );
					} catch ( Google_Service_Exception $e ) { // catch recoverable errors.
						$this->maybe_do_a_pause( 'ga4', true );
						$this->service_error = $e->getErrors();
						$this->handle_exception( $e );
						$this->on_error_received( $e, $page_slugs_list );
						throw $e;
					} catch ( GuzzleConnectException $e ) { // catch recoverable errors.
						$this->maybe_do_a_pause( 'ga4', true );
						$this->set_message( $this->extract_message( $e ), $e, (string) wp_json_encode( isset( $request ) ? $request : null ) );
						$this->on_error_received( $e, $page_slugs_list );
						throw $e;
					}
					if ( ! is_null( $data ) ) {
						$reports = $data->getReports();
						if ( ! empty( $reports ) ) {
							foreach ( $reports as $index => $report ) {
								$continue = false;
								$rows     = $report->getRows();
								$totals   = $report->getRowCount();
								if ( ! empty( $rows ) ) {
									foreach ( $rows as $row ) {
										$dimensions     = $row->getDimensionValues(); // page slug + traffic type.
										$_slug          = $dimensions[0]->getValue();
										$_type          = $dimensions[1]->getValue();
										$_metrics       = $row->getMetricValues();
										$_traffic_count = (int) ( ! empty( $_metrics[0]->getValue() ) ? $_metrics[0]->getValue() : 0 );
										if ( ! isset( $result[ $_slug ] ) ) {
											$result[ $_slug ] = [];
										}
										if ( ! isset( $result[ $_slug ][ "{$_type}" ] ) ) {
											$result[ $_slug ][ "{$_type}" ] = $_traffic_count;
											$result[ $_slug ]['total']      = (int) $_traffic_count + ( isset( $result[ $_slug ]['total'] ) ? $result[ $_slug ]['total'] : 0 );
										} else {
											$result[ $_slug ][ "{$_type}" ] += $_traffic_count;
											$result[ $_slug ]['total']      += $_traffic_count;
										}
									}
									$continue = count( $rows ) === $per_page && ( is_null( $max_results ) || count( $rows ) < $max_results ) && $pages_to_load[ $index ]['offset'] + count( $rows ) < $totals;
								}
								if ( ! is_null( $max_results ) && ( count( $rows ) >= $max_results || count( $result ) >= $max_results ) && $continue ) {
									$pages_to_load[ $index ]['offset'] += count( $rows );
								} else {
									$pages_to_load[ $index ]['offset'] = null; // do not load more.
								}
							}
						} else {
							$pages_to_load = [];
						}
					} else {
						$pages_to_load = [];
					}
					// remove finished pages (without next_token) from load list.
					$pages_to_load = array_values(
						array_filter(
							$pages_to_load,
							function ( $value ) {
								return ! empty( $value['next_token'] );
							}
						)
					);
				} catch ( \Error $e ) {
					$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
					$this->set_message( $message );
				} catch ( \Exception $e ) {
					$this->handle_exception( $e, true );
					return $this->prepare_answer( $page_slugs_list, 'connection_error' );
				}
				if ( is_null( $page_slugs_list ) ) { // load only first page with results for the generic request without page.
					break;
				}
				// load until any next page exists, but load only first page with results for the generic request without page ($page_slugs_list is null).
			} while ( ! empty( $pages_to_load ) && ! is_null( $page_slugs_list ) );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		}
		return $result;
	}
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	/**
	 * Get visitors traffic by type for page for GA property.
	 *
	 * @since 0.7.3
	 *
	 * @param string[] $ua_ids
	 * @param string   $start_date
	 * @param string   $end_date
	 *
	 * @return array<string, null|int> Array, [ ua_id => pages_found ].
	 */
	private function get_found_pages_by_ua_id_ga( array $ua_ids, $start_date, $end_date ) {
		$results = [];
		try {
			$client = $this->create_client();
			$client->setUseBatch( true );
			$analyticsreporting = new Google_Service_AnalyticsReporting( $client );
			$per_page           = $this::QUERY_DETECT_GA_LIMIT; // used as per page parameter, but really we load first page only.
			do { // for ua_ids parts.
				$ua_id_list = array_splice( $ua_ids, 0, 5 ); // max 5 requests per batch.
				$result = [];
				$data   = [];
				try {
					$data = null;
					// analytics parameters.
					$params = [ 'quotaUser' => $this->get_api_user() ];
					// get results from google analytics.
					try {
						$this->maybe_do_a_pause( 'ga' );
						$batch = new Google_Http_Batch( $client, false, $analyticsreporting->rootUrl, $analyticsreporting->batchPath );
						$this->maybe_do_a_pause( 'ga', true );
						foreach ( $ua_id_list as $ua_id ) {
							// Create the DateRange object.
							$dateRange = new Google_Service_AnalyticsReporting_DateRange();
							$dateRange->setStartDate( $start_date );
							$dateRange->setEndDate( $end_date );
							// Create the Metrics object.
							$metric1 = new Google_Service_AnalyticsReporting_Metric();
							$metric1->setExpression( 'ga:uniquePageviews' );
							// Create the Dimensions object.
							$dimension1 = new Google_Service_AnalyticsReporting_Dimension();
							$dimension1->setName( 'ga:pagePath' );
							/** @link https://ga-dev-tools.appspot.com/dimensions-metrics-explorer/#ga:channelGrouping */
							$dimension2 = new Google_Service_AnalyticsReporting_Dimension();
							$dimension2->setName( 'ga:channelGrouping' );
							// Create the ReportRequest object.
							$request = new Google_Service_AnalyticsReporting_ReportRequest();
							$request->setViewId( $ua_id );
							$request->setDateRanges( $dateRange );
							$request->setDimensions( array( $dimension1, $dimension2 ) );
							$request->setMetrics( array( $metric1 ) );
							$request->setPageSize( $per_page );
							$body = new Google_Service_AnalyticsReporting_GetReportsRequest();
							$body->setReportRequests( [ $request ] );
							$prepared_queries = $analyticsreporting->reports->batchGet( $body, $params );
							$batch->add( $prepared_queries, $ua_id );
						}
						$data = $batch->execute();
					} catch ( Google_Service_Exception $e ) { // try to continue, but report error.
						Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
						Ahrefs_Seo::notify( $e, 'autodetect ga' );
					} catch ( GuzzleConnectException $e ) { // try to continue, but report error.
						Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
						Ahrefs_Seo::notify( $e, 'autodetect ga' );
					}
					$continue = false;
					if ( ! is_null( $data ) ) {
						foreach ( $data as $index => $values ) {
							$index = str_replace( 'response-', '', $index );
							if ( $values instanceof \Exception ) {
								$results[ "{$index}" ] = null;
								continue;
							}
							$reports = $values->getReports();
							if ( ! empty( $reports ) ) {
								foreach ( $reports as $key => $report ) {
									$data_items = $report->getData();
									// load details from rows.
									$rows = $data_items->getRows();
									if ( ! empty( $rows ) ) {
										foreach ( $rows as $row ) {
											// if we here - the traffic at page is not empty.
											list($_slug, $_type) = $row->getDimensions(); // page slug + traffic type.
											if ( ! isset( $result[ $_slug ] ) ) {
												$result[ $_slug ] = true;
											}
										}
									}
									$count = 0;
									if ( ! empty( $result ) ) {
										$result = array_keys( $result );
										array_walk(
											$result,
											function ( $slug ) use ( &$count ) {
												$post = get_page_by_path( $slug, OBJECT, [ 'post', 'page' ] );
												if ( $post instanceof \WP_Post ) {
													$count++;
												}
											}
										);
									}
									$results[ "{$index}" ] = $count;
								}
							}
						}
					}
				} catch ( \Error $e ) {
					$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
					$this->set_message( $message );
				} catch ( \Exception $e ) {
					$this->handle_exception( $e, true );
					return $results;
				}
				// load until any next page exists, but load only first page with results for the generic request without page ($page_slugs_list is null).
			} while ( ! empty( $ua_ids ) );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		} finally {
			if ( ! empty( $client ) ) {
				$client->setUseBatch( false );
			}
		}
		return $results;
	}
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	/**
	 * Get visitors traffic by type for page for GA4 property.
	 *
	 * @since 0.7.3
	 *
	 * @param string[] $ua_ids
	 * @param string   $start_date
	 * @param string   $end_date
	 *
	 * @return array<string, null|int> Array, [ ua_id => pages_found ].
	 */
	public function get_found_pages_by_ua_id_ga4( array $ua_ids, $start_date, $end_date ) {
		$results = [];
		try {
			$client = $this->create_client();
			$client->setUseBatch( true );
			$analytics4 = new Google_Service_AnalyticsData( $client );
			$per_page   = $this::QUERY_DETECT_GA_LIMIT;
			do { // for ua_ids parts.
				$ua_id_list = array_splice( $ua_ids, 0, 5 ); // max 5 requests per batch.
				$result = [];
				$data   = [];
				try {
					$data = null;
					// analytics parameters.
					$params = [ 'quotaUser' => $this->get_api_user() ];
					// get results from google analytics.
					try {
						$this->maybe_do_a_pause( 'ga4' );
						$batch = new Google_Http_Batch( $client, false, $analytics4->rootUrl, $analytics4->batchPath );
						$this->maybe_do_a_pause( 'ga4', true );
						foreach ( $ua_id_list as $ua_id ) {
							// Create the DateRange object.
							$dateRange = new Google_Service_AnalyticsData_DateRange();
							$dateRange->setStartDate( $start_date );
							$dateRange->setEndDate( $end_date );
							// Create the Metrics object.
							/** @link https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#metrics */
							$metric = new Google_Service_AnalyticsData_Metric();
							$metric->setName( 'screenPageViews' ); // "ga:uniquePageviews"
							// Create the Dimension object.
							/** @link https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema#dimensions */
							$dimension1 = new Google_Service_AnalyticsData_Dimension();
							$dimension1->setName( 'pagePath' ); // "ga:pagePath".
							$dimension2 = new Google_Service_AnalyticsData_Dimension();
							$dimension2->setName( 'sessionDefaultChannelGrouping' ); // "ga:channelGrouping".
							$entity = new Google_Service_AnalyticsData_Entity();
							$entity->setPropertyId( str_replace( 'properties/', '', $ua_id ) ); // only numeric part.
							// Create the ReportRequest object.
							$request = new Google_Service_AnalyticsData_RunReportRequest();
							$request->setEntity( $entity );
							$request->setDateRanges( $dateRange );
							$request->setMetrics( array( $metric ) );
							$request->setDimensions( array( $dimension1, $dimension2 ) );
							$request->setLimit( $per_page );
							$request->setOffset( 1 );
							$query = $analytics4->v1alpha->runReport( $request, $params );
							$batch->add( $query, $ua_id );
						}
						$data = $batch->execute();
					} catch ( Google_Service_Exception $e ) { // try to continue, but report error.
						Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
						Ahrefs_Seo::notify( $e, 'autodetect ga4' );
					} catch ( GuzzleConnectException $e ) { // try to continue, but report error.
						Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
						Ahrefs_Seo::notify( $e, 'autodetect ga4' );
					}
					$continue = false;
					if ( ! is_null( $data ) ) {
						foreach ( $data as $index => $report ) {
							$index = str_replace( 'response-', '', $index );
							if ( $report instanceof \Exception ) {
								$results[ "{$index}" ] = null;
								continue;
							}
							$result = [];
							$rows   = $report->getRows();
							$count  = 0;
							if ( ! empty( $rows ) ) {
								foreach ( $rows as $row ) {
									$dimensions = $row->getDimensionValues(); // page slug + traffic type.
									$_slug      = $dimensions[0]->getValue();
									if ( ! isset( $result[ $_slug ] ) ) {
										$result[ $_slug ] = true;
									}
								}
								if ( ! empty( $result ) ) {
									$result = array_keys( $result );
									array_walk(
										$result,
										function ( $slug ) use ( &$count ) {
											$post = get_page_by_path( $slug, OBJECT, [ 'post', 'page' ] );
											if ( $post instanceof \WP_Post ) {
												$count++;
											}
										}
									);
								}
							}
							$results[ "{$index}" ] = $count;
						}
					}
				} catch ( \Error $e ) {
					$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
					$this->set_message( $message );
				} catch ( \Exception $e ) {
					$this->handle_exception( $e, true );
					return $results;
				}
				// load until any next page exists, but load only first page with results for the generic request without page ($page_slugs_list is null).
			} while ( ! empty( $ua_ids ) );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		} finally {
			if ( ! empty( $client ) ) {
				$client->setUseBatch( false );
			}
		}
		return $results;
	}
    // phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	/**
	 * Fill answers with error message
	 *
	 * @since 0.7.3
	 *
	 * @param int[]|string[] $page_slugs_list
	 * @param string         $error_message
	 * @return array Index is slug, value is ['error' => $error_message].
	 */
	protected function prepare_answer( array $page_slugs_list = null, $error_message ) {
		return is_null( $page_slugs_list ) ? null : array_map(
			function ( $slug ) use ( $error_message ) {
				return [ 'error' => $error_message ];
			},
			array_flip( $page_slugs_list )
		);
	}
	/**
	 * Maybe disconnect Google using 'disconnect' link.
	 * Static function.
	 *
	 * @param Ahrefs_Seo_Screen $screen
	 */
	public static function maybe_disconnect( Ahrefs_Seo_Screen $screen ) {
		if ( isset( $_GET['disconnect-analytics'] ) && check_admin_referer( $screen->get_nonce_name(), 'disconnect-analytics' ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			// disconnect Analytics.
			self::get()->disconnect();
			Ahrefs_Seo::get()->initialized_set( null, false );
			// show notice if any of Analytics settings changed.
			$params = [
				'page' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : Ahrefs_Seo::SLUG,
				'tab'  => isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : null,
				'step' => isset( $_GET['step'] ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : null,
			];
			wp_safe_redirect( remove_query_arg( [ 'disconnect-analytics' ], add_query_arg( $params, admin_url( 'admin.php' ) ) ) );
			die;
		}
	}
	/**
	 * Try to refresh current access token using refresh token.
	 * Disconnect Analytics on invalid_grant error or if no refresh token exists.
	 *
	 * @return bool Was the token updated
	 */
	private function try_to_refresh_token() {
		// try to update current token.
		try {
			$client = $this->create_client();
			Ahrefs_Seo::breadcrumbs( sprintf( '%s: %s', __METHOD__, wp_json_encode( $client->getAccessToken() ) ) );
			$refresh = $client->getRefreshToken();
			if ( $refresh ) {
				$_token           = $client->getAccessToken();
				$created_time_old = isset( $_token['created'] ) ? $_token['created'] : 0;
				$client->refreshToken( $refresh );
				$_token           = $client->getAccessToken();
				$created_time_new = isset( $_token['created'] ) ? $_token['created'] : 0;
				if ( $created_time_new === $created_time_old ) {
					$this->disconnect();
					$this->set_message( 'Google account disconnected due to invalid token.' );
				}
				return true;
			} else {
				$this->disconnect();
			}
		} catch ( \LogicException $e ) {
			$this->disconnect();
			Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
			Ahrefs_Seo::notify( $e, 'token refresh' );
		} catch ( GuzzleClientException $e ) { // todo: merge both exceptions after migrating to php 7.1+.
			$this->disconnect();
			Ahrefs_Seo_Errors::save_message( 'google', $e->getMessage(), 'notice' );
			Ahrefs_Seo::notify( $e, 'token refresh' );
		} catch ( Google_Service_Exception $e ) {
			$errors = $e->getErrors();
			$this->disconnect();
			if ( is_array( $errors ) && count( $errors ) && ( 401 === $e->getCode() && isset( $errors[0]['reason'] ) && 'authError' === $errors[0]['reason'] ) ) {
				$message = 'Google account disconnected due to invalid token.';
				$this->set_message( $message, $e );
			} else {
				$message = 'There was an additional Google Auth error while refresh token ' . $e->getCode() . ':' . $e->getMessage();
				$this->set_message( $this->get_message() . ' ' . $message, $e );
			}
		} catch ( \Exception $e ) {
			$this->disconnect();
			$message = 'There was an additional error while refresh token ' . $e->getCode() . ':' . $e->getMessage();
			$this->set_message( $this->get_message() . ' ' . $message, $e );
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
		}
		return false;
	}
	/**
	 * Handle exception, set error message, maybe refresh token or disconnect on invalid token
	 *
	 * @param \Exception $e
	 * @param bool       $set_service_error Set internal variable with error message.
	 * @param bool       $is_gsc
	 * @param bool       $save_message
	 *
	 * @return void
	 */
	private function handle_exception( \Exception $e, $set_service_error = false, $is_gsc = false, $save_message = true ) {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( [ (string) $e, $set_service_error, $is_gsc ] ) );
		if ( $e instanceof Google_Service_Exception ) {
			$no_report = false;
			if ( $set_service_error ) {
				$this->service_error = $e->getErrors();
			}
			$error = json_decode( $e->getMessage(), true );
			if ( is_array( $error ) && isset( $error['error'] ) && in_array( $error['error'], [ 'invalid_grant', 'unauthorized_client' ], true ) ) {
				// tokens are invalid.
				do_action( 'ahrefs_seo_analylics_token_disconnect' );
				$this->disconnect();
				$this->set_message( '' ); // clean possible error message.
				$this->set_gsc_disconnect_reason( 'Your Google account has been disconnected because token has been expired or revoked.' );
			} elseif ( 403 === $e->getCode() ) {
				$errors = $e->getErrors();
				if ( is_array( $errors ) && 0 < count( $errors ) && isset( $errors[0]['reason'] ) ) {
					$reason = $errors[0]['reason'];
					if ( 'forbidden' === $reason ) {
						if ( $is_gsc && $this->gsc_site ) { // if was not disconnected before.
							$site = preg_match( "/site '([^']+)'/", isset( $errors[0]['message'] ) ? $errors[0]['message'] : $e->getMessage(), $m ) ? $m[1] : $this->gsc_site;
							// get site from message.
							$message = sprintf( 'Your Google account has been disconnected because you don’t have the required permission for %s site.', empty( $site ) ? 'this' : $site );
							$this->set_gsc_disconnect_reason( $message );
							$this->set_message( $message, $e ); // this will submit error.
							$no_report = true;
						} else {
							$ga      = $this->get_ua_id();
							$message = sprintf( 'Your Google account has been disconnected because you don’t have the sufficient permissions for %s profile.', empty( $ga ) ? 'this' : $ga );
							$this->set_ga_disconnect_reason( $message );
						}
					} elseif ( 'insufficientPermissions' === $reason && isset( $errors[0]['message'] ) && 'User does not have any Google Analytics account.' === $errors[0]['message'] ) {
						$message = 'Google Search Console account does not exists.';
						$this->set_message( $message );
						$no_report = true;
					}
				}
			} elseif ( 401 === $e->getCode() ) {
				$this->try_to_refresh_token();
			}
			if ( ! $no_report ) {
				$this->set_message( $this->extract_message( $e ), $e );
			}
		} elseif ( $e instanceof GuzzleRequestException ) { // GuzzleConnectException and GuzzleClientException.
			if ( strpos( $e->getMessage(), '"error"' ) && ( strpos( $e->getMessage(), '"invalid_grant"' ) || strpos( $e->getMessage(), '"invalid_token"' ) ) ) {
				do_action( 'ahrefs_seo_analylics_token_disconnect' );
				$this->disconnect();
				$this->set_gsc_disconnect_reason( 'Your Google account has been disconnected because token has been expired or revoked.' );
			}
			Ahrefs_Seo::notify( $e );
		} else { // \Exception.
			Ahrefs_Seo::breadcrumbs( 'Events ' . wp_json_encode( $this->get_logged_events() ) );
			if ( $save_message ) {
				$this->set_message( $this->extract_message( $e ), $e );
			}
			Ahrefs_Seo::notify( $e );
		}
	}
	/**
	 * Do a minimal delay between requests.
	 * Used to prevent API rate errors.
	 *
	 * @param string $what_api What API used: 'ga', 'ga4' or 'gsc'.
	 * @param bool   $request_just_finished Do not pause, just set request time.
	 * @return void
	 */
	protected function maybe_do_a_pause( $what_api, $request_just_finished = false ) {
		if ( ! $request_just_finished ) {
			$time_since = microtime( true ) - ( isset( $this->last_query_time[ $what_api ] ) ? $this->last_query_time[ $what_api ] : 0 );
			if ( $time_since < self::API_MIN_DELAY && ! defined( 'AHREFS_SEO_IGNORE_DELAY' ) ) {
				$pause = intval( ceil( ( self::API_MIN_DELAY - $time_since ) * 1000000 ) );
				Ahrefs_Seo::breadcrumbs( sprintf( '%s(%s): %d', __METHOD__, $what_api, $pause ) );
				Ahrefs_Seo::usleep( $pause );
			}
		}
		$this->last_query_time[ $what_api ] = microtime( true );
	}
	/**
	 * Prepare query to GSC.
	 *
	 * @since 0.7.3
	 *
	 * @param string                    $key Key for responses array.
	 * @param Google_Http_Batch         $batch Batch instance.
	 * @param Google_Service_Webmasters $service_webmasters Google Service Webmasters instance.
	 * @param string                    $gsc_site Site for query.
	 * @param array                     $parameters Other parameters for query.
	 *
	 * @return void
	 */
	private function prepare_gsc_query( $key, Google_Http_Batch &$batch, Google_Service_Webmasters $service_webmasters, $gsc_site, array $parameters ) {
		$result = [];
		try {
			$post_body = new Google_Service_Webmasters_SearchAnalyticsQueryRequest( $parameters );
			$request   = $service_webmasters->searchanalytics->query( $gsc_site, $post_body, [ 'quotaUser' => $this->get_api_user() ] );
			$batch->add( $request, $key );
		} catch ( \Exception $e ) {
			$this->handle_exception( $e, false, true );
			return;
		}
	}
	/**
	 * Parse results of request.
	 *
	 * @since 0.7.3
	 *
	 * @param Google_Service_Webmasters_SearchAnalyticsQueryResponse $response
	 * @return array<array<string, mixed>>
	 */
	protected function parse_gsc_response( Google_Service_Webmasters_SearchAnalyticsQueryResponse $response = null ) {
		$result = [];
		if ( ! empty( $response ) ) {
			/**
			 * @var \Google_Service_Webmasters_ApiDataRow $row
			 */
			foreach ( $response->getRows() as $row ) {
				if ( $row instanceof Google_Service_Webmasters_ApiDataRow ) {
					$keys     = $row->getKeys();
					$clicks   = $row->getClicks();
					$impr     = $row->getImpressions();
					$position = $row->getPosition();
					$result[] = [
						'query'  => $keys[0],
						'impr'   => $impr,
						'clicks' => $clicks,
						'pos'    => $position,
					];
				}
			}
		}
		return $result;
	}
	/**
	 * Load keywords for url from GSC
	 *
	 * @param array<int, string>           $urls
	 * @param string                       $start_date
	 * @param string                       $end_date
	 * @param int|null                     $limit
	 * @param bool                         $without_totals Do not make additional query for total values.
	 * @param array<int, string|null>|null $current_keywords Current keyword of post, if is set. Indexes are same as $urls parameter used.
	 *
	 * @return array<int, array>|null Array with details [same index as was in $urls => results] or null on error.
	 */
	public function get_clicks_and_impressions_by_urls( array $urls, $start_date = null, $end_date = null, $limit = null, $without_totals = false, array $current_keywords = null ) {
		// is GSC enabled?
		if ( ! $this->is_gsc_enabled() ) {
			$this->set_message( 'Google Search Console disconnected.' );
			$this->service_error = [ [ 'reason' => 'internal-no-token' ] ];
			return null;
		}
		if ( ! $this->is_gsc_set() ) {
			$this->set_message( 'Please choose Google Search Console site.' );
			$this->service_error = [ [ 'reason' => 'internal-no-profile' ] ];
			return null;
		}
		$results          = [];
		$unknown_keywords = [];
		$url_to_key       = [];
		$time_wait        = 0;
		$time_query_1     = 0;
		$limit            = isset( $limit ) ? $limit : self::GSC_KEYWORDS_LIMIT;
		$responses        = null;
		try {
			$client             = $this->create_client();
			$service_webmasters = new Google_Service_Webmasters( $client );
			$batch              = $service_webmasters->createBatch();
			$client->setUseBatch( true );
			foreach ( $urls as $key => $url ) {
				// request must use same scheme, as site parameter has.
				if ( false === strpos( $this->gsc_site, 'sc-domain:' ) && false === strpos( $url, $this->gsc_site ) ) {
					$scheme_current  = explode( '://', $url, 2 );
					$scheme_required = explode( '://', $this->gsc_site, 2 );
					if ( 2 === count( $scheme_current ) && 2 === count( $scheme_required ) ) {
						$url = $scheme_required[0] . '://' . $scheme_current[1];
					}
				}
				$parameters = [
					'startDate'             => $start_date,
					'endDate'               => $end_date,
					'dimensions'            => [], // without any values.
					'dimensionFilterGroups' => [
						[
							'filters' => [
								[
									'dimension'  => 'page',
									'expression' => $url,
								],
							],
						],
					],
					'rowLimit'              => $limit,
					'startRow'              => 0,
				];
				// Total clicks, positions, impressions.
				if ( ! $without_totals ) {
					$this->prepare_gsc_query( "{$key}-total", $batch, $service_webmasters, $this->gsc_site, array_merge( $parameters, [ 'dimensions' => [ 'page' ] ] ) );
				}
				// Top 10 clicks, positions, impressions.
				$this->prepare_gsc_query( "{$key}-q", $batch, $service_webmasters, $this->gsc_site, array_merge( $parameters, [ 'dimensions' => [ 'query', 'page' ] ] ) );
			}
			$e = null;
			try {
				// execute requests.
				$time0 = microtime( true );
				$this->maybe_do_a_pause( 'gsc' );
				$time_wait += microtime( true ) - $time0;
				$time0      = microtime( true );
				$responses  = $batch->execute();
				do_action_ref_array( 'ahrefs_seo_api_clicks_and_impressions', [ &$responses, $urls ] );
				$time_query_1 += microtime( true ) - $time0;
			} catch ( Google_Service_Exception $e ) { // catch forbidden error.
				$this->handle_exception( $e, false, true );
				$this->on_error_received( $e, $urls );
			} catch ( \Error $e ) {
				$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
				$this->set_message( $message );
				$this->on_error_received( $e, $urls );
			} catch ( \Exception $e ) { // catch any errors.
				$this->set_message( $this->extract_message( $e ), $e );
				$this->on_error_received( $e, $urls );
			}
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
			$e = new Ahrefs_Seo_Exception( $message, 0, $e );
			$this->on_error_received( $e, $urls );
		} finally {
			if ( ! empty( $client ) ) {
				$client->setUseBatch( false );
			}
		}
		if ( is_null( $responses ) ) {
			if ( empty( $e ) ) {
				$e = new Ahrefs_Seo_Exception( 'GSC returned empty response.' );
			}
			// Nothing received - exit earlier.
			return array_map(
				function ( $value ) use ( $e ) {
					return [ 'error' => $e ];
				},
				$urls
			);
		}
		// parse requests.
		foreach ( $urls as $key => $url ) {
			$result       = [];
			$total_clicks = 0;
			$total_impr   = 0;
			$total_filled = false;
			if ( ! $without_totals ) {
				$answer = isset( $responses[ "response-{$key}-total" ] ) ? $responses[ "response-{$key}-total" ] : null;
				if ( $answer instanceof Google_Service_Exception ) { // catch forbidden error.
					$message = $this->extract_message( $answer );
					$this->handle_exception( $answer, false, true );
					$this->on_error_received( $answer, $urls );
					$this->gsc_paused = true; // do not make additional requests.
					continue;
				} elseif ( $answer instanceof \Exception ) {
					$results[ $key ] = [ 'error' => $answer ];
					$this->on_error_received( $answer, [ $url ] );
					Ahrefs_Seo::notify( $answer, 'gsc get_clicks_and_impressions single' );
					Ahrefs_Seo_Errors::save_message( 'google', $this->extract_message( $answer ), 'error' );
					$this->gsc_paused = true; // do not make additional requests.
					continue;
				}
				$response_total = $this->parse_gsc_response( $answer );
				if ( ! empty( $response_total ) ) {
					foreach ( $response_total as $row ) {
						// save total clickes & impressions.
						$total_clicks = $row['clicks'];
						$total_impr   = $row['impr'];
						$total_filled = true;
						break;
					}
				}
			}
			$answer = isset( $responses[ "response-{$key}-q" ] ) ? $responses[ "response-{$key}-q" ] : null;
			if ( $answer instanceof Google_Service_Exception ) { // catch forbidden error.
				$message = $this->extract_message( $answer );
				$this->handle_exception( $answer, false, true );
				$this->on_error_received( $answer, [ $url ] );
				$this->gsc_paused = true; // do not make additional requests.
				continue;
			} elseif ( $answer instanceof \Exception ) {
				$results[ $key ] = [ 'error' => $answer ];
				$this->on_error_received( $answer, [ $url ] );
				Ahrefs_Seo::notify( $answer, 'gsc get_clicks_and_impressions single' );
				Ahrefs_Seo_Errors::save_message( 'google', $this->extract_message( $answer ), 'error' );
				$this->gsc_paused = true; // do not make additional requests.
				continue;
			}
			$response        = $this->parse_gsc_response( $answer );
			$current_keyword = isset( $current_keywords[ $key ] ) ? $current_keywords[ $key ] : null;
			$keyword_found   = is_null( $current_keyword ); // no keyword set at all.
			$current_keyword_lower = null;
			if ( ! is_null( $current_keyword ) ) {
				$current_keyword_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $current_keyword ) : strtolower( $current_keyword );
			}
			if ( ! empty( $response ) ) {
				foreach ( $response as $row ) {
					$result[] = $row;
					$keyword  = $row['query'];
					if ( ! $keyword_found ) {
						$keyword_found = ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword ) : strtolower( $keyword ) ) === $current_keyword_lower;
					}
					if ( ! $total_filled ) {
						// count total clickes & impressions.
						$total_clicks += $row['clicks'];
						$total_impr   += $row['impr'];
					}
				}
			}
			// individual request for current keyword's clicks, position, impressions.
			if ( ! $keyword_found && is_array( $current_keywords ) && ! empty( $current_keywords[ $key ] ) ) {
				$unknown_keywords[ $urls[ $key ] ] = $current_keywords[ $key ];
				$url_to_key[ $urls[ $key ] ]       = $key;
			}
			$results[ $key ] = compact( 'total_clicks', 'total_impr', 'result' );
		}
		if ( ! empty( $unknown_keywords ) && ! $this->gsc_paused ) {
			// make additional request and load details for current keywords.
			$additional = $this->get_position_fast( $unknown_keywords );
			if ( ! empty( $additional ) ) {
				foreach ( $additional as $url => $row ) {
					$key                         = $url_to_key[ $url ];
					$results[ $key ]['result'][] = $row;
				}
			}
		}
		$total_clicks = array_map(
			function ( $values ) {
				return isset( $values['total_clicks'] ) ? $values['total_clicks'] : null;
			},
			$results
		);
		Ahrefs_Seo::breadcrumbs( sprintf( 'get_clicks_and_impressions_by_urls(%s) (%s) (%s) (%d): wait: %1.3fsec, query:  %1.3fsec. Total clicks: %s', wp_json_encode( $urls ), $start_date, $end_date, $limit, $time_wait, $time_query_1, wp_json_encode( $total_clicks ) ) );
		return $results;
	}
	/**
	 * Load position of keyword.
	 *
	 * @param array<string, string> $url_with_keyword [ url => keyword ] pairs.
	 * @return null|array<string, array<string, mixed>> [ url => position] pairs or null if error.
	 */
	public function get_position_fast( array $url_with_keyword ) {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$result     = null;
		$start_date = date( 'Y-m-d', strtotime( sprintf( '- 3 month' ) ) );
		$end_date   = date( 'Y-m-d' );
		// is GSC enabled?
		if ( ! $this->is_gsc_enabled() ) {
			$this->set_message( 'Google Search Console disconnected.' );
			$this->service_error = [ [ 'reason' => 'internal-no-token' ] ];
			return null;
		}
		if ( ! $this->is_gsc_set() ) {
			$this->set_message( 'Please choose Google Search Console site.' );
			$this->service_error = [ [ 'reason' => 'internal-no-profile' ] ];
			return null;
		}
		$results      = [];
		$time_wait    = 0;
		$time_query_3 = 0;
		$url_to_key   = []; // [ url => key ].
		try {
			$client             = $this->create_client();
			$service_webmasters = new Google_Service_Webmasters( $client );
			$batch              = $service_webmasters->createBatch();
			$client->setUseBatch( true );
			$key = 0;
			foreach ( $url_with_keyword as $url => $current_keyword ) {
				$key++;
				$url_to_key[ $url ] = $key;
				// request must use same scheme, as site parameter has.
				if ( false === strpos( $this->gsc_site, 'sc-domain:' ) && false === strpos( $url, $this->gsc_site ) ) {
					$scheme_current  = explode( '://', $url, 2 );
					$scheme_required = explode( '://', $this->gsc_site, 2 );
					if ( 2 === count( $scheme_current ) && 2 === count( $scheme_required ) ) {
						$url = $scheme_required[0] . '://' . $scheme_current[1];
					}
				}
				$parameters = [
					'startDate'             => $start_date,
					'endDate'               => $end_date,
					'dimensions'            => [ 'query', 'page' ],
					'dimensionFilterGroups' => [
						[
							'filters' => [
								[
									'dimension'  => 'page',
									'expression' => $url,
								],
								[
									'dimension'  => 'query',
									'expression' => $current_keyword,
								],
							],
						],
					],
					'rowLimit'              => 1,
					'startRow'              => 0,
				];
				// prepare request and load details for current keyword.
				$this->prepare_gsc_query( "{$key}-f", $batch, $service_webmasters, $this->gsc_site, $parameters );
			}
			try {
				// execute requests.
				$time0 = microtime( true );
				$this->maybe_do_a_pause( 'gsc' );
				$time_wait += microtime( true ) - $time0;
				$time0      = microtime( true );
				$responses  = $batch->execute();
				do_action_ref_array( 'ahrefs_seo_api_position_fast', [ &$responses ] );
				$time_query_3 += microtime( true ) - $time0;
			} catch ( \Exception $e ) { // catch all errors.
				$this->on_error_received( $e, array_keys( $url_with_keyword ) );
				$this->handle_exception( $e );
				return null; // exit without success.
			}
		} catch ( \Error $e ) {
			$message = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__ );
			$this->set_message( $message );
			return null;
		} finally {
			if ( ! empty( $client ) ) {
				$client->setUseBatch( false );
			}
		}
		foreach ( $url_with_keyword as $url => $current_keyword ) {
			$key    = $url_to_key[ $url ];
			$answer = isset( $responses[ "response-{$key}-f" ] ) ? $responses[ "response-{$key}-f" ] : null;
			if ( $answer instanceof Google_Service_Exception ) { // catch forbidden error.
				$message = $this->extract_message( $answer );
				$this->handle_exception( $answer, false, true );
				$this->on_error_received( $answer, [ $url ] );
				$this->gsc_paused = true; // do not make additional requests.
				continue;
			} elseif ( $answer instanceof \Exception ) {
				$results[ $url ] = [ 'error' => $answer ];
				$this->on_error_received( $answer, [ $url ] );
				Ahrefs_Seo::notify( $answer, 'get_position_fast single' );
				Ahrefs_Seo_Errors::save_message( 'google', $this->extract_message( $answer ), 'error' );
				continue;
			}
			$response = $this->parse_gsc_response( $answer );
			if ( ! empty( $response ) ) {
				foreach ( $response as $row ) {
					$results[ $url ] = $row; // only 1 row was loaded.
					break;
				}
			}
		}
		return $results;
	}
	/**
	 * Return lowercase domain name without 'www.'.
	 *
	 * @param null|string $url If null - return domain of current site.
	 *        Examples: http://www.example.com/ (for a URL-prefix property) or sc-domain:example.com (for a Domain property).
	 * @return string
	 */
	private function get_clean_domain( $url = null ) {
		$result = '';
		if ( is_null( $url ) ) {
			$result = strtolower( Ahrefs_Seo::get_current_domain() );
		} else {
			$result = 0 !== strpos( $url, 'sc-domain:' ) ? wp_parse_url( $url, PHP_URL_HOST ) : substr( $url, strlen( 'sc-domain:' ) ); // url or string "sc-domain:".
			$result = is_string( $result ) ? strtolower( $result ) : ''; // wp_parse_url may return null.
		}
		if ( 0 === strpos( $result, 'www.' ) ) {
			$result = substr( $result, 4 );
		}
		return $result;
	}
	/**
	 * Get disconnect reason for GCS if any.
	 *
	 * @return Message|null Null if not disconnected.
	 */
	public function get_gsc_disconnect_reason() {
		if ( is_null( $this->disconnect_reason ) ) {
			$json                    = get_option( self::OPTION_GSC_DISCONNECT_REASON, null );
			$this->disconnect_reason = ! is_null( $json ) ? Message::load_json( $json ) : null;
		}
		return $this->disconnect_reason;
	}
	/**
	 * Set disconnect reason for GCS if any.
	 *
	 * @param string|null  $string Null if not disconnected.
	 * @param Message|null $message
	 * @param bool         $reset_gsc_account
	 * @return void
	 */
	public function set_gsc_disconnect_reason( $string = null, Message $message = null, $reset_gsc_account = true ) {
		if ( $reset_gsc_account && ( ! is_null( $string ) || ! is_null( $message ) ) ) {
			$this->set_ua( $this->ua_id, $this->ua_name, $this->ua_url, '' );
		}
		if ( is_null( $message ) && ! is_null( $string ) ) {
			$message = Message::gsc_disconnected( $string );
		}
		update_option( self::OPTION_GSC_DISCONNECT_REASON, ! is_null( $message ) ? $message->save_json() : null );
		$this->disconnect_reason = $message;
	}
	/**
	 * Set disconnect reason for GA if any.
	 *
	 * @since 0.7.5
	 *
	 * @param string|null  $string Null if not disconnected.
	 * @param Message|null $message
	 * @return void
	 */
	public function set_ga_disconnect_reason( $string = null, Message $message = null ) {
		if ( ! is_null( $string ) || ! is_null( $message ) ) {
			$this->set_ua( '', '', '', $this->gsc_site );
		}
		if ( is_null( $message ) && ! is_null( $string ) ) {
			$message = Message::gsc_disconnected( $string, false );
		}
		update_option( self::OPTION_GSC_DISCONNECT_REASON, ! is_null( $message ) ? $message->save_json() : null );
		$this->disconnect_reason = $message;
	}
	/**
	 * Get logged events from API requests.
	 *
	 * @since 0.7.1
	 *
	 * @return array<array>|null Null if no logging method available.
	 */
	protected function get_logged_events() {
		return ! is_null( $this->logger ) && $this->logger instanceof Logger ? $this->logger->get_events() : null;
	}
	/**
	 * Requests to GSC API are paused
	 *
	 * @since 0.7.4
	 *
	 * @param bool $is_paused
	 * @return void
	 */
	public function set_gsc_paused( $is_paused ) {
		$this->gsc_paused = $is_paused;
	}
	/**
	 * Is request to GSC API paused?
	 *
	 * @since 0.7.4
	 *
	 * @return bool
	 */
	public function is_gsc_paused() {
		return $this->gsc_paused;
	}
	/**
	 * Extract human readable message from exception
	 *
	 * @since 0.7.4
	 *
	 * @param \Exception  $e
	 * @param string|null $default_message
	 * @param bool        $skip_disconnected_message
	 * @return string|null
	 */
	protected function extract_message( \Exception $e, $default_message = null, $skip_disconnected_message = true ) {
		$result = isset( $default_message ) ? $default_message : $e->getMessage();
		if ( $e instanceof Google_Service_Exception ) {
			$errors = $e->getErrors();
			if ( is_array( $errors ) && count( $errors ) && isset( $errors[0]['message'] ) && isset( $errors[0]['reason'] ) ) {
				if ( $skip_disconnected_message && in_array( $errors[0]['reason'], [ 'userRateLimitExceeded', 'rateLimitExceeded', 'quotaExceeded', 'internalError', 'forbidden' ], true ) ) {
					/** @see Worker_Any::on_rate_error() */
					$result = null; // no need to save and show this error, because other tip displayed.
				} else {
					$reason = preg_replace( '/(?<! )[A-Z]/', ' $0', $errors[0]['reason'] ); // camel case to words with a space as separator.
					$result = sprintf( '%s. %s', ucfirst( $reason ), $errors[0]['message'] );
				}
			} else {
				if ( false !== stripos( $e->getMessage(), 'The server encountered a temporary error' ) || false !== stripos( $e->getMessage(), 'Error 404' ) ) {
					$result = null; // no need to save and show this error, because other tip displayed.
				}
			}
		} elseif ( $e instanceof GuzzleConnectException ) {
			$error = $e->getMessage();
			if ( false !== stripos( $error, 'could not resolve' ) ) { // "cURL error 6: Could not resolve host: www.googleapis.com (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)".
				$result = sprintf( '%s. %s', 'Connection error', 'Could not resolve host.' );
			} elseif ( false !== stripos( $error, 'connection timed out' ) ) { // "cURL error 7: Failed to connect to analyticsreporting.googleapis.com port 443: Connection timed out (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)".
				$result = sprintf( '%s. %s', 'Connection error', 'Connection timed out.' );
			} elseif ( false !== stripos( $error, 'operation timed out' ) ) { // "cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)".
				$result = sprintf( '%s. %s', 'Connection error', 'Operation timed out.' );
			} elseif ( false !== stripos( $error, 'Failed to connect' ) ) { // "cURL error 28: Operation timed out after 120001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)".
				$result = sprintf( '%s. %s', 'Connection error', 'Failed to connect.' );
			}
		} elseif ( $e instanceof \GuzzleHttp\Exception\RequestException ) {
			$error = $e->getMessage();
		}
		return $result;
	}
}
