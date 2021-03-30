<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for admin dashboard notices.
 */
class Ahrefs_Seo_Notice {

	/** Is admin notice hidden. This value set if user closed admin notice. This value reset every new day. */
	const OPTION_ADMIN_NOTICE_HIDE_MAIN = 'ahrefs-seo-admin-notice-hide-main';

	/** Is admin notice for no Analytics hidden. This value set if user closed admin notice. */
	const OPTION_ADMIN_NOTICE_SHOW_STOPPED = 'ahrefs-seo-admin-notice-show-stopped'; // wizard stopped and ahrefs rows limited.
	const OPTION_ADMIN_NOTICE_SHOW_TIMEOUT = 'ahrefs-seo-admin-notice-show-timeout'; // wizard stopped and time limit reached.

	private const OPTION_ADMIN_NOTICES = 'ahrefs-seo-admin-notices';

	/** @var Ahrefs_Seo_Notice */
	public static $instance;

	/** @var Ahrefs_Seo_View|null */
	private $view;

	/**
	 * Return the class instance
	 *
	 * @param Ahrefs_Seo_View|null $view View class instance.
	 * @return Ahrefs_Seo_Notice
	 */
	public static function get( ?Ahrefs_Seo_View $view = null ) : Ahrefs_Seo_Notice {
		if ( empty( self::$instance ) ) {
			self::$instance = new self( $view );
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Ahrefs_Seo_View $view View class instance.
	 */
	public function __construct( ?Ahrefs_Seo_View $view ) {
		$this->view = $view;
		add_action( 'admin_init', [ $this, 'on_admin_init' ] );
		add_action( Ahrefs_Seo::ACTION_TOKEN_CHANGED, [ $this, 'clear_cache' ] );

		add_action( 'wp_ajax_ahrefs_seo_notice_hide', [ $this, 'ajax_notice_hide' ] );
	}

	public function add( string $id, string $message ) : void {
		$messages = get_option( self::OPTION_ADMIN_NOTICES, [] );
		if ( ! is_array( $messages ) ) {
			$messages = [];
		}
		if ( ! isset( $messages[ $id ] ) ) {
			$messages[ $id ] = $message;
			update_option( self::OPTION_ADMIN_NOTICES, $messages );
		}
	}

	public function remove( string $id ) : void {
		$messages = get_option( self::OPTION_ADMIN_NOTICES, [] );
		if ( ! is_array( $messages ) ) {
			$messages = [];
		}
		if ( isset( $messages[ $id ] ) ) {
			unset( $messages[ $id ] );
			update_option( self::OPTION_ADMIN_NOTICES, $messages );
		}
	}

	public function maybe_show_admin_notices() : void {
		$messages = get_option( self::OPTION_ADMIN_NOTICES, [] );
		// do not show at own pages, only at Content Audit.
		if ( is_array( $messages ) && ! empty( $messages ) ) {
			foreach ( $messages as $id => $message ) {
				?>
				<div id="ahrefs_seo_notice" class="ahrefs_seo_notice updated error is-dismissible" data-type="<?php echo esc_attr( $id ); ?>">
					<p>
						<?php
						echo esc_html( $message );
						?>
					</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
				</div>
				<?php
			}
			$this->add_styles();
		}
	}

	/**
	 * Do we run out of Ahrefs credits/rows or out of time before all pages can get completed (content audit stopped).
	 *
	 * @param bool $is_stopped
	 * @param bool $stopped_by_timeout
	 * @return void
	 */
	public function set_audit_stopped( bool $is_stopped = true, bool $stopped_by_timeout = true ) : void {
		update_option( self::OPTION_ADMIN_NOTICE_SHOW_STOPPED, $is_stopped );
		update_option( self::OPTION_ADMIN_NOTICE_SHOW_TIMEOUT, $stopped_by_timeout );
	}

	/**
	 * Add admin notice callback if it is not disabled.
	 */
	public function on_admin_init() : void {
		// show only if allowed by notifications option + not disabled by dismiss button click.
		if ( get_option( self::OPTION_ADMIN_NOTICE_SHOW_STOPPED, false ) ) {
			add_action( 'admin_notices', [ $this, 'maybe_show_audit_stopped' ] );
		}
		add_action( 'admin_notices', [ $this, 'maybe_show_admin_notices' ] );
	}


	/**
	 * Callback. Show admin notice (at the content audit admin pages) if run out of Ahrefs credits/rows before all pages can get completed.
	 *
	 * @param bool $force_show Force show if true.
	 * @return void
	 */
	public function maybe_show_audit_stopped( $force_show = false ) : void {
		// do not show at own pages, only at Content Audit.
		if ( $force_show || ! is_null( $this->view ) && $this->view->is_plugin_screen( 'Ahrefs_Seo_Screen_Content' ) ) {
			?>
			<div id="ahrefs_seo_notice" class="ahrefs_seo_notice notice notice-warning is-dismissible" data-type="audit-stopped">
				<p>
					Oops! You ran out of Ahrefs API rows so some pages were not analysed. Check out the limits on <a href="https://ahrefs.com/api/profile" target="_blank">your API profile here</a>.
				</p>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
			</div>
			<?php
		}
		$this->add_styles();
	}

	/**
	 * Add inline css code.
	 */
	public function add_styles() : void {
		static $used = null;
		if ( is_null( $used ) ) {
			$used = true;
			?>
			<style type="text/css">
				#ahrefs_seo_notice {
					position: relative;
				}
				#ahrefs_seo_notice .diff1 {
					font-weight: bold;
					color: #13892b;
				}
				#ahrefs_seo_notice .diff-1 {
					font-weight: bold;
					color: #ff0000;
			}</style>
			<script type="text/javascript">
				( function( $ ){
					$( function(){
						if ( $( '.ahrefs_seo_notice, .ahrefs-content-tip' ).length ) {
							$( document ).on( 'click', '.ahrefs_seo_notice .notice-dismiss, .ahrefs-content-tip .notice-dismiss', function() {
								var type = $( this ).closest( '.ahrefs_seo_notice, .ahrefs-content-tip' ).data( 'type' ) || '';
								$.post(
									ajaxurl,
									{
										action: 'ahrefs_seo_notice_hide',
										type: type,
										_wpnonce : '<?php echo esc_js( wp_create_nonce( $this->get_nonce_name() ) ); ?>',
									}
								);
								$( this ).closest( '.ahrefs_seo_notice, .ahrefs-content-tip' ).hide( 'slow' );
							} )
						}
					})
				} )( jQuery );
			</script>
			<?php
		}
	}

	/**
	 * Ajax handler. Hide admin notice.
	 */
	public function ajax_notice_hide() : void {
		if ( check_ajax_referer( $this->get_nonce_name() ) && current_user_can( Ahrefs_Seo::CAPABILITY ) ) {
			$type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : 'main';
			switch ( $type ) {
				case 'audit-stopped':
					delete_option( self::OPTION_ADMIN_NOTICE_SHOW_STOPPED );
					break;
				case 'gsc-disconnect':
					Ahrefs_Seo_Analytics::get()->set_gsc_disconnect_reason( null );
					break;
				default:
					$this->remove( $type );
			}
			wp_send_json_success();
		}
	}

	/**
	 * Clear internal cached data
	 */
	public function clear_cache() : void {
	}

	/**
	 * Return action name of nonce for a page.
	 * Result based on actual children class name.
	 *
	 * @return string
	 */
	private function get_nonce_name() : string {
		return 'ahrefs_seo_notice';
	}
}
