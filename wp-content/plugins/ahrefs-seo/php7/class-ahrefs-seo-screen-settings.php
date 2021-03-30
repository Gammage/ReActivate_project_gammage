<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Settings screen class.
 */
class Ahrefs_Seo_Screen_Settings extends Ahrefs_Seo_Screen {

	/**
	 * Tabs of the table.
	 *
	 * @var array
	 */
	private $tabs = [
		'content'     => 'Content Audit',
		'analytics'   => 'Google accounts',
		'account'     => 'Ahrefs account',
		'diagnostics' => 'Error diagnostics',
	];

	/**
	 * Updated message.
	 *
	 * @var string
	 */
	protected $updated = '';
	/**
	 * Error message, if any
	 *
	 * @var string
	 */
	protected $error = '';

	/**
	 * Current options
	 *
	 * @var Settings_Any|null
	 */
	protected $settings = null;

	/**
	 * Process get and post requests.
	 */
	public function process_post_data() : void {
		if ( isset( $_GET['disconnect-analytics'] ) || isset( $_GET['disconnect-ahrefs'] ) ) {
			Ahrefs_Seo_Analytics::maybe_disconnect( $this );
			Ahrefs_Seo_Api::maybe_disconnect( $this );
		}

		if ( ! empty( $_POST ) && check_admin_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			switch ( $this->get_current_tab() ) {
				case 'content':
					( new Content_Schedule() )->set_options_from_request();

					$content       = new Ahrefs_Seo_Content_Settings();
					$this->updated = ! is_null( $content->set_options_from_request() ) ? 'Updated.' : '';
					break;
				case 'analytics':
					$this->settings = new Settings_Google();
					$this->settings->apply_options( $this );
					break;
				case 'account':
					if ( isset( $_POST['ahrefs_step'] ) ) {
						if ( empty( $_POST['ahrefs_code'] ) ) {
							$this->error = 'Please enter your authorization code';
						} else {
							$updated      = false;
							$code         = sanitize_text_field( wp_unslash( $_POST['ahrefs_code'] ) );
							$ahrefs_token = Ahrefs_Seo_Token::get();
							$ahrefs_token->token_save( $code );
							$updated = $ahrefs_token->query_api_is_token_valid();
							if ( $updated ) {
								// reanalyze everything if new Ahrefs token value set.
								( new Snapshot() )->reset_backlinks_for_new_snapshot();
							} else {
								$this->error = $ahrefs_token->get_error() ?: 'The code is invalid';
							}
						}
					}
					break;
				case 'diagnostics':
					Ahrefs_Seo::allow_reports_set( ! empty( $_POST['allow_reports'] ) );
					$this->updated = 'Updated.';
					break;
			}
			if ( isset( $_REQUEST['return'] )
				&& ( ! isset( $_REQUEST['ahrefs_step'] ) || isset( $_REQUEST['ahrefs_step'] ) && 1 !== (int) $_REQUEST['ahrefs_step'] )
				&& ( ! isset( $_REQUEST['analytics_step'] ) || isset( $_REQUEST['analytics_step'] ) && 1 !== (int) $_REQUEST['analytics_step'] ) ) {
				// return back to initial page, if it is not a step 1 of Google or Ahrefs account settings.
				wp_safe_redirect( add_query_arg( 'updated', 'true', sanitize_text_field( wp_unslash( $_REQUEST['return'] ) ) ) );
				die();
			}
		}
	}

	/**
	 * Register AJAX handlers for Settings screen.
	 * Must be overwritten.
	 */
	public function register_ajax_handlers() : void {
		add_action( 'wp_ajax_ahrefs_seo_options_ga_detect', [ $this, 'ajax_options_ga_detect' ] );
		add_action( 'wp_ajax_ahrefs_seo_options_gsc_detect', [ $this, 'ajax_options_gsc_detect' ] );
	}

	/**
	 * Autodetect of ga account.
	 * Event on button click.
	 */
	public function ajax_options_ga_detect() : void {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$result = ( Ahrefs_Seo_Analytics::get()->find_recommended_ga_id() );
			wp_send_json_success( [ 'ga' => $result ] );
		}
	}

	/**
	 * Autodetect of gsc account.
	 * Event on button click.
	 */
	public function ajax_options_gsc_detect() : void {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$result = ( Ahrefs_Seo_Analytics::get()->find_recommended_gsc_id() );
			wp_send_json_success( [ 'gsc' => $result ] );
		}
	}

	/**
	 * Show content of Settings screen.
	 */
	public function show() : void {
		?>
		<!-- show loader -->
		<style type="text/css">#loader_while_accounts_loaded{position: absolute;top:40vh;max-width:800px;}</style>
		<div class="row-loader loader-transparent" id="loader_while_accounts_loaded"><div class="loader"></div></div>
		<?php
		echo '<!-- padding ' . str_pad( '', 10240, ' ' ) . ' -->'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		flush(); // show loader while settings screen loaded, will hide it using inline css at the end of settings block.

		$active_tab = $this->get_current_tab();
		switch ( $active_tab ) {
			case 'content':
				$vars                 = $this->get_template_vars();
				$vars['button_title'] = 'Save';
				$this->view->show( 'settings-content', 'Content audit', $vars, $this, 'settings' );
				break;
			case 'analytics':
				Content_Audit::audit_clean_scheduled_message();
				if ( is_null( $this->settings ) ) {
					$this->settings = new Settings_Google();
				}
				$this->settings->show_options( $this, $this->view );
				break;
			case 'account':
				Content_Audit::audit_clean_scheduled_message();
				$vars                    = $this->get_template_vars();
				$vars['error']           = $this->error;
				$vars['disconnect_link'] = 'settings';
				$this->view->show( 'settings-account', 'Ahrefs account', $vars, $this, 'settings' );
				break;
			case 'diagnostics':
				$updated = $this->updated;
				$this->view->show( 'settings-diagnostics', 'Error diagnostics', $this->get_template_vars(), $this, 'settings' );
				break;
		}
		?>
		<!-- hide accounts loader -->
		<style type="text/css">#loader_while_accounts_loaded{display:none;}</style>
		<?php
	}

	/**
	 * Get template variables for view call
	 *
	 * @return array<string, mixed>
	 */
	public function get_template_vars() : array {
		return [
			'header_class' => [ 'settings' ],
			'active_tab'   => $this->get_current_tab(),
			'tabs'         => $this->tabs,
			'updated'      => $this->updated,
		];
	}

	/**
	 * Get current tab using parameter from request
	 *
	 * @global $_REQUEST
	 * @return string
	 */
	private function get_current_tab() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- load parameters from opened page.
		if ( ! isset( $this->tabs[ $active_tab ] ) ) {
			$active_tab = 'content';
		}
		return $active_tab;
	}
}
