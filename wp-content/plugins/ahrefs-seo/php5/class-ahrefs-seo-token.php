<?php

namespace ahrefs\AhrefsSeo;

/**
 * Ahrefs_Seo_Token class.
 * Save Ahrefs token and check is it valid, save that state.
 */
class Ahrefs_Seo_Token {

	/**
	 * Class instance.
	 *
	 * @var Ahrefs_Seo_Token
	 */
	private static $instance;
	/**
	 * Last API error.
	 *
	 * @var string
	 */
	private $error = '';
	/**
	 * Return the instance
	 *
	 * @return Ahrefs_Seo_Token
	 */
	public static function get() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Save token for future use.
	 *
	 * @param string $token Ahrefs token.
	 * @return void
	 */
	public function token_save( $token ) {
		update_option( 'ahrefs-seo-token', $token );
		// reset state.
		$this->token_state_set( false );
	}
	/**
	 * Return saved token value.
	 *
	 * @return string Ahrefs Token.
	 */
	public function token_get() {
		return get_option( 'ahrefs-seo-token', '' );
	}
	/**
	 * Return status of token.
	 * Use stored value.
	 *
	 * @return bool True if Ahrefs token is valid.
	 */
	public function token_state_ok() {
		return '' !== get_option( 'ahrefs-seo-token-state', '' );
	}
	/**
	 * Set state of token.
	 * Call action Ahrefs_Seo::ACTION_TOKEN_CHANGED.
	 *
	 * @param bool $ok Is Ahrefs token valid.
	 * @return bool Return the same value.
	 */
	private function token_state_set( $ok ) {
		$prev_value = get_option( 'ahrefs-seo-token-state', '' );
		update_option( 'ahrefs-seo-token-state', $ok ? '1' : '' );
		/**
		 * Call action with new and old token statuses
		 * Example: add_action( Ahrefs_Seo::ACTION_TOKEN_CHANGED, function( bool $new_ok, bool $old_ok ) { ... }, 10, 2 );.
		 */
		do_action( Ahrefs_Seo::ACTION_TOKEN_CHANGED, $ok, ! empty( $prev_value ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- false positive, constant has plugin prefix.
		return $ok;
	}
	/**
	 * Return a link to Ahrefs token for existing users.
	 *
	 * @return string Url.
	 */
	public function token_link() {
		$state = wp_generate_password( 20, false );
		return 'https://ahrefs.com/web/oauth/authorize?client_id=Ahrefs%20SEO%20Wordpress%20plugin&redirect_uri=https%3A%2F%2Fahrefs.com%2Fweb%2Fwp-plugin%2Fapi-token&response_type=code&scope=api&state=' . rawurlencode( $state );
	}
	/**
	 * Return a link to Ahrefs token for free users.
	 *
	 * @return string Url.
	 */
	public function token_free_link() {
		return 'https://ahrefs.com/web/wp-plugin/api-token?type=free&target=' . rawurlencode( Ahrefs_Seo::get_current_domain() );
	}
	/**
	 * Execute API request, check and set token status option.
	 *
	 * @return bool Is token valid.
	 */
	public function query_api_is_token_valid() {
		$api         = Ahrefs_Seo_Api::get();
		$result      = $this->token_state_set( $api->token_check() );
		$this->error = $api->get_last_error();
		return $result;
	}
	/**
	 * Return last error text if any.
	 *
	 * @return string Error or empty string.
	 */
	public function get_error() {
		return $this->error;
	}
	/**
	 * Remove existing token.
	 */
	public function disconnect() {
		$this->token_save( '' );
	}
}
