<?php

namespace ahrefs\AhrefsSeo;

/**
 * Abstract class for Screen.
 */
abstract class Ahrefs_Seo_Screen {

	/**
	 * View class instance.
	 *
	 * @var Ahrefs_Seo_View
	 */
	protected $view;
	/**
	 * Ahrefs token instance.
	 *
	 * @var Ahrefs_Seo_Token
	 */
	protected $token;
	/**
	 * Current (WordPress's) screen id.
	 *
	 * @var string
	 */
	protected $screen_id;
	/**
	 * Constructor
	 *
	 * @param Ahrefs_Seo_View $view View instance.
	 */
	public function __construct( Ahrefs_Seo_View $view ) {
		$this->view  = $view;
		$this->token = Ahrefs_Seo_Token::get();
		$this->register_ajax_handlers(); // called during "init" action.
		if ( ! defined( 'DOING_AJAX' ) || defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
			add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ], 100 );
			add_filter( 'update_footer', [ $this, 'update_footer' ], 100 );
		}
	}
	/**
	 * Return action name of nonce for a page.
	 * Result based on actual children class name.
	 *
	 * @return string
	 */
	public function get_nonce_name() {
		$class = strtolower( get_called_class() );
		$pos   = strrpos( $class, '\\' );
		if ( $pos ) {
			$class = substr( $class, $pos + 1 );
		}
		return 'ahrefs_' . $class;
	}
	/**
	 * Return name of nonce for a page.
	 * Static method.
	 *
	 * @return string
	 */
	public static function get_nonce_name_static() {
		$class = strtolower( get_called_class() );
		$pos   = strrpos( $class, '\\' );
		if ( $pos ) {
			$class = substr( $class, $pos + 1 );
		}
		return 'ahrefs_' . $class;
	}
	/**
	 * Set screen id of admin page for this screen.
	 * Register 'process_post_data' method as action.
	 *
	 * @param string $screen_id Screen id.
	 */
	public function set_screen_id( $screen_id ) {
		$this->screen_id = $screen_id;
		add_action( 'ahrefs_seo_process_data_' . $screen_id, [ $this, 'process_post_data' ] );
	}
	/**
	 * Process post request from a page if any
	 */
	public function process_post_data() {
	}
	/**
	 * Register ajax handlers if any
	 */
	abstract public function register_ajax_handlers();
	/**
	 * Show a page
	 */
	abstract public function show();
	/**
	 * Add our footer template to footer.
	 *
	 * @param null|string $text Default content.
	 * @return null|string Final content.
	 */
	public function admin_footer_text( $text = '' ) {
		$screen = get_current_screen();
		if ( ! is_null( $screen ) && $screen->id === $this->screen_id ) {
			ob_start();
			$this->view->show_part( 'footer-text' );
			$text = (string) ob_get_clean();
		}
		return $text;
	}
	/**
	 * Remove text from footer on plugin's admin pages.
	 *
	 * @param null|string $text Default text.
	 * @return null|string Final text.
	 */
	public function update_footer( $text = '' ) {
		$screen = get_current_screen();
		if ( ! is_null( $screen ) && $screen->id === $this->screen_id ) {
			$text = '';
		}
		return $text;
	}
	/**
	 * Get template variables for view call
	 *
	 * @return array<string, mixed>
	 */
	public function get_template_vars() {
		return [];
	}
}
