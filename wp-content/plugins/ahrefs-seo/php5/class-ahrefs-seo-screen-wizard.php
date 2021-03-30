<?php

namespace ahrefs\AhrefsSeo;

/**
 * Wizard screen class. Used at first plugin initialization.
 */
class Ahrefs_Seo_Screen_Wizard extends Ahrefs_Seo_Screen {

	const OPTION_WIZARD_1_STEP = 'ahrefs-seo-wizard-1-step';
	const OPTION_WIZARD_3_STEP = 'ahrefs-seo-wizard-3-step';
	/**
	 * Current step of Wizard.
	 *
	 * @var int
	 */
	private $current_step = 1;
	/**
	 * Current error (from applying ahrefs token or Analytics code) if any.
	 *
	 * @var string Error message,
	 */
	private $error = '';
	/**
	 * Current options
	 *
	 * @var Settings_Any|null
	 */
	protected $settings = null;
	/**
	 * Process get and post request, on page load.
	 */
	public function process_post_data() {
        // phpcs:disable WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- we load parameters.
		$this->current_step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		if ( $this->current_step <= 1 || $this->current_step > 4 ) {
			$this->current_step = 1;
		}
		// do not allow other steps until Ahrefs token is valid.
		if ( Ahrefs_Seo_Api::get()->is_disconnected() ) {
			$this->current_step = 1;
			if ( 1 !== intval( isset( $_GET['step'] ) ? $_GET['step'] : 1 ) ) { // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification -- we just load current step number, this must work when form is opened by link.
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => Ahrefs_Seo::SLUG,
							'step' => 1,
						],
						admin_url( 'admin.php' )
					)
				);
				die;
			}
		}
		// force progress loader view if it is already running.
		if ( get_option( Ahrefs_Seo::OPTION_IS_INITIALIZED_IN_PROGRESS, false ) ) {
			$this->current_step = 4;
			if ( 4 !== intval( isset( $_GET['step'] ) ? $_GET['step'] : 4 ) ) { // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification -- we just load current step number and change to 4 if it is different, this must work when form is opened by link.
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => Ahrefs_Seo::SLUG,
							'step' => 4,
						],
						admin_url( 'admin.php' )
					)
				);
				die;
			}
		}
		Ahrefs_Seo_Analytics::maybe_disconnect( $this );
		Ahrefs_Seo_Api::maybe_disconnect( $this );
		$this->maybe_skip_wizard();
		if ( ! empty( $_POST ) && check_admin_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
            // phpcs:disable WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- just load possible parameters.
			if ( 1 === $this->current_step ) {
				if ( isset( $_POST['ahrefs_step'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ahrefs_step'] ) ) ) {
					$ahrefs_token = Ahrefs_Seo_Token::get();
					if ( empty( $_POST['ahrefs_code'] ) ) {
						$ahrefs_token->token_save( '' );
						$this->error = 'Please enter your authorization code';
					} else {
						$code = sanitize_text_field( wp_unslash( $_POST['ahrefs_code'] ) );
						$ahrefs_token->token_save( $code );
						if ( ! $ahrefs_token->query_api_is_token_valid() ) {
							$this->error = $ahrefs_token->get_error() ?: 'The code is invalid';
						} else {
							Ahrefs_Seo::get()->initialized_set( true );
						}
					}
				}
				if ( isset( $_POST['ahrefs_step'] ) && '2' === $_POST['ahrefs_step'] ) {
					$this->set_step_and_reload( 2 );
				}
			} elseif ( 2 === $this->current_step ) {
				$this->settings = new Settings_Google();
				$this->settings->apply_options( $this );
			} elseif ( 3 === $this->current_step ) {
				$content = new Ahrefs_Seo_Content_Settings();
				if ( ! is_null( $content->set_options_from_request() ) ) {
					( new Content_Schedule() )->set_options_from_request();
					Ahrefs_Seo::allow_reports_set( ! empty( $_POST['allow_reports'] ) );
					( new Snapshot() )->create_new_snapshot();
					// table is filled, will always start from step 3.2.
					Ahrefs_Seo::get()->initialized_set( null, null, true );
					// reset API errors, if any: do not show errors happened before wizard on Backlinks or Content Audit screens.
					Ahrefs_Seo_Errors::clean_messages( 'ahrefs' );
					Ahrefs_Seo_Errors::clean_messages( 'google' );
					// initialize time limits for wizard execution time.
					Ahrefs_Seo_Cron::get()->start_tasks_content();
					// run cron content updates.
					$this->set_step_and_reload( 4 );
				}
				$this->maybe_finish();
			} elseif ( 4 === $this->current_step ) {
				if ( get_option( Ahrefs_Seo::OPTION_IS_INITIALIZED_WIZARD_COMPLETED ) ) {
					delete_option( Ahrefs_Seo::OPTION_IS_INITIALIZED_WIZARD_COMPLETED );
				}
				if ( isset( $_REQUEST['ahrefs_audit_skip_wizard'] ) ) {
					Ahrefs_Seo::get()->initialized_set( null, null, null, true ); // wizard update finished.
					( new Content_Tips_Content() )->on_wizard_skipped();
				}
				$this->maybe_finish();
			}
		}
		$this->maybe_finish();
        // phpcs:enable WordPress.VIP.SuperGlobalInputUsage.AccessDetected
	}
	/**
	 * Switch final step to another template
	 *
	 * @return void
	 */
	private function do_redirect_after_initialized() {
		// reset steps to initial.
		$this->set_step_1( false );
		// redirect to main screen.
		wp_safe_redirect( remove_query_arg( 'step', add_query_arg( [ 'page' => Ahrefs_Seo::SLUG ], admin_url( 'admin.php' ) ) ) );
		die;
	}
	/**
	 * Maybe finish Wizard and do a redirect to initialized content.
	 *
	 * @param bool $force Do that immediately.
	 */
	protected function maybe_finish( $force = false ) {
		if ( $force || $this->current_step >= 3 && Ahrefs_Seo::get()->initialized_wizard() ) {
			// already initialized, do redirect to the main screen.
			$this->do_redirect_after_initialized();
		}
	}
	/**
	 * Register AJAX handlers, required for Wizard screen.
	 */
	public function register_ajax_handlers() {
		add_action( 'wp_ajax_ahrefs_token', [ $this, 'ajax_ahrefs_token' ] );
		add_action( 'wp_ajax_ahrefs_seo_options_ga_detect', [ $this, 'ajax_options_ga_detect' ] );
		add_action( 'wp_ajax_ahrefs_seo_options_gsc_detect', [ $this, 'ajax_options_gsc_detect' ] );
	}
	public function show() {
		$header_class = [ 'setup-wizard' ];
		$is_valid     = true;
		$step         = $this->current_step;
		if ( 1 === $step ) {
			$error = $this->error;
			$this->view->show( 'setup-wizard-1', 'Connect Ahrefs to your website', compact( 'step', 'error', 'header_class' ), $this, 'wizard' );
		} elseif ( 2 === $step ) {
			if ( is_null( $this->settings ) ) {
				$this->settings = new Settings_Google();
			}
			$this->settings->show_options( $this, $this->view );
		} elseif ( 3 === $step ) {
			$header_class[] = 'wizard-step-3';
			$this->view->show( 'setup-wizard-3', 'Content audit', compact( 'step', 'header_class' ), $this, 'wizard' );
		} elseif ( 4 === $step ) {
			$step = 3;
			$this->view->show( 'setup-wizard-4', 'Content audit', compact( 'step', 'header_class' ), $this, 'wizard' );
		}
	}
	/**
	 * Set Wizard step to 1 and clear the token.
	 * Event on button click.
	 */
	public function ajax_ahrefs_token() {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$this->set_step_1( ! empty( $_POST['step'] ) ); // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected
			echo 'OK';
			die;
		}
	}
	/**
	 * Autodetect of ga account.
	 * Event on button click.
	 */
	public function ajax_options_ga_detect() {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$result = Ahrefs_Seo_Analytics::get()->find_recommended_ga_id();
			wp_send_json_success( [ 'ga' => $result ] );
		}
	}
	/**
	 * Autodetect of gsc account.
	 * Event on button click.
	 */
	public function ajax_options_gsc_detect() {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$result = Ahrefs_Seo_Analytics::get()->find_recommended_gsc_id();
			wp_send_json_success( [ 'gsc' => $result ] );
		}
	}
	/**
	 * Set current step, 1 to 4 and execute a redirect to new step's page.
	 *
	 * @param int $step Current step.
	 * @return void
	 */
	public function set_step_and_reload( $step ) {
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => Ahrefs_Seo::SLUG,
					'step' => $step,
				],
				admin_url( 'admin.php' )
			)
		);
		die;
	}
	/**
	 * Set step 2 part.
	 *
	 * @param bool $step_opened first or second part of step.
	 * @return void
	 */
	public function set_step_1( $step_opened ) {
		update_option( self::OPTION_WIZARD_1_STEP, $step_opened ? '1' : '' );
	}
	/**
	 * Reset state of internal options.
	 */
	public static function reset_state() {
		delete_option( self::OPTION_WIZARD_1_STEP );
		delete_option( self::OPTION_WIZARD_3_STEP );
	}
	/**
	 * Return link to skip the Wizard
	 *
	 * @return string
	 */
	protected function get_skip_wizard_link() {
		return add_query_arg(
			[
				'page' => Ahrefs_Seo::SLUG,
				'skip' => wp_create_nonce( $this->get_nonce_name() ),
			],
			admin_url( 'admin.php' )
		);
	}
	protected function maybe_skip_wizard() {
		if ( isset( $_GET['skip'] ) && current_user_can( Ahrefs_Seo::CAPABILITY ) && check_admin_referer( $this->get_nonce_name(), 'skip' ) ) { // phpcs:ignore: WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- we check nonce here.
			Ahrefs_Seo::get()->initialized_set( null, null, null, true, true );
			wp_safe_redirect( add_query_arg( 'page', Ahrefs_Seo::SLUG, admin_url( 'admin.php' ) ) );
			die;
		}
	}
	/**
	 * Get template variables for view call
	 *
	 * @return array<string, mixed>
	 */
	public function get_template_vars() {
		return [
			'header_class' => [ 'setup-wizard' ],
			'step'         => $this->current_step,
		];
	}
}
