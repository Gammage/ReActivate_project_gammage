<?php

namespace ahrefs\AhrefsSeo;

/**
 * Ahrefs_Seo_View class.
 */
class Ahrefs_Seo_View {

	/** Handle of main JS file. */
	const AHREFS_JS_HANDLE = 'ahrefs-seo';
	const QUERY_VAR_LOCALS = '__ahrefs_seo_locals__';
	/**
	 * List of admin screens id
	 *
	 * @var string[]
	 */
	private $admin_screens = [];
	/**
	 * @var Ahrefs_Seo_Screen|Ahrefs_Seo_Screen_With_Table|null
	 */
	private $current_screen = null;
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'current_screen', [ $this, 'current_screen' ] );
	}
	/**
	 * Add a result of add_menu_page, add_submenu_page to list
	 *
	 * @param Ahrefs_Seo_Screen $screen
	 * @param string            $screen_id
	 * @return void
	 */
	public function add_admin_screen( Ahrefs_Seo_Screen $screen, $screen_id ) {
		// class name of a screen instance, without namespaces.
		$items                         = explode( '\\', get_class( $screen ) );
		$class                         = array_pop( $items );
		$this->admin_screens[ $class ] = $screen_id;
		$screen->set_screen_id( $screen_id );
	}
	/**
	 * Add scripts to plugin's pages and call custom actions.
	 * Also call action 'ahrefs_seo_process_data_' . $screen->id.
	 */
	public function current_screen() {
		if ( is_admin() ) {
			$screen = get_current_screen();
			$this->register_scripts();
			if ( $screen instanceof \WP_Screen && in_array( $screen->id, $this->admin_screens, true ) ) {
				do_action( 'ahrefs_seo_process_data_' . $screen->id );
				$this->add_scripts();
			}
		}
	}
	/**
	 * Is a plugin screen now.
	 *
	 * @param null|string $screen_name Plugin's current screen class name or null - for any plugin's screen.
	 * @return bool
	 */
	public function is_plugin_screen( $screen_name = null ) {
		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen instanceof \WP_Screen ) {
				if ( is_null( $screen_name ) ) { // any of plugin's screens.
					return in_array( $screen->id, $this->admin_screens, true );
				} else { // given screen class is currently viewed.
					return isset( $this->admin_screens[ $screen_name ] ) && $screen->id === $this->admin_screens[ $screen_name ];
				}
			}
		}
		return false;
	}
	/**
	 * Show a template
	 * It remove dependency on knowing the PHP var name that is used
	 * for passing variables to included template via query vars
	 *
	 * E.g.,
	 * ```php
	 * Ahrefs_Seo_View::get()->show( 'a/b', 'title', array(
	 *     'c' => 1,
	 *     'd' => 1,
	 * ), $screen, '' );
	 * // Now `__ahrefs_seo_locals__['c']` and `__ahrefs_seo_locals__['d']`
	 * // will be available in template `a/b.php`
	 * ```
	 *
	 * @see http://wordpress.stackexchange.com/a/176807/31766
	 * @see https://developer.wordpress.org/reference/functions/set_query_var/
	 * @see Ahrefs_Seo_View::get_template_variables()
	 * @see Ahrefs_Seo_View::show_part()
	 *
	 * @param string               $template Template file name, without '.php' extension.
	 * @param string               $title Page title.
	 * @param array<string, mixed> $template_variables Variables to be included into template, associative array [ name => value ].
	 * @param Ahrefs_Seo_Screen    $screen Instance of screen class, that is using view.
	 * @param string               $custom_header Custom header for using instead of standard header.php template.
	 * @return void
	 */
	public function show( $template, $title, array $template_variables, Ahrefs_Seo_Screen $screen, $custom_header = '' ) {
		$old_value                   = self::get_template_variables();
		$this->current_screen        = $screen;
		$template_variables['title'] = $title;
		// nonce variable for future checking.
		$template_variables['page_nonce'] = $screen->get_nonce_name();
		set_query_var( self::QUERY_VAR_LOCALS, $template_variables );
		// use passed to template variables.
		$templates_dir = __DIR__ . '/templates/';
		$headers_dir   = __DIR__ . '/templates/headers/';
		// include header, desired page template and footer.
		$header = '' !== $custom_header ? $custom_header : 'header';
		if ( file_exists( "{$headers_dir}{$header}.php" ) ) {
			require "{$headers_dir}{$header}.php";
		} else {
			require "{$headers_dir}header.php";
		}
		if ( in_array( $template, [ 'settings-account' ], true ) ) {
			$this->maybe_show_ahrefs_notices();
		}
		if ( file_exists( "{$templates_dir}{$template}.php" ) ) {
			require "{$templates_dir}{$template}.php";
		}
		require "{$headers_dir}footer.php";
		set_query_var( self::QUERY_VAR_LOCALS, $old_value );
	}
	/**
	 * Show template part.
	 * Template parts located at the 'parts' subdir of templates.
	 *
	 * @see Ahrefs_Seo_View::show()
	 * @see Ahrefs_Seo_View::get_template_variables()
	 *
	 * @param string               $template Template file name, without '.php' extension.
	 * @param array<string, mixed> $template_variables Variables to be included into template, associative array [ name => value ].
	 * @return bool
	 */
	public function show_part( $template, array $template_variables = [] ) {
		$old_value = self::get_template_variables();
		set_query_var( self::QUERY_VAR_LOCALS, $template_variables );
		// use passed to template variables.
		$templates_dir = __DIR__ . '/templates/parts/';
		if ( file_exists( "{$templates_dir}{$template}.php" ) ) {
			require "{$templates_dir}{$template}.php";
			set_query_var( self::QUERY_VAR_LOCALS, $old_value );
			return true;
		} else {
			Ahrefs_Seo::breadcrumbs( "Template part not found: [{$template}] {$templates_dir}{$template}.php" );
		}
		set_query_var( self::QUERY_VAR_LOCALS, $old_value );
		return false;
	}
	/**
	 * Return current screen if defined.
	 *
	 * @return Ahrefs_Seo_Screen|Ahrefs_Seo_Screen_With_Table|null
	 */
	public function get_ahrefs_screen() {
		return $this->current_screen;
	}
	/**
	 * Return template variables without need to know their exact names.
	 *
	 * E.g.,
	 * ```php
	 * <?php
	 * // (continuing from `show` or `show_part` method).
	 * $locals = Ahrefs_Seo_View::get_template_variables();
	 * ?>
	 * <h1>
	 *     The var <code>c</code> is
	 *     <code><?php echo esc_html( $locals['c'] ); ?></code>
	 * </h1>
	 *
	 * @see Ahrefs_Seo_View::show()
	 * @see Ahrefs_Seo_View::show_part()
	 *
	 * @return array<string, mixed>
	 */
	public static function get_template_variables() {
		return (array) get_query_var( self::QUERY_VAR_LOCALS, array() );
	}
	/**
	 * Register JS and CSS files.
	 */
	private function register_scripts() {
		wp_enqueue_script( 'jquery-validate', AHREFS_SEO_URL . 'assets/js/jquery.validate.min.js', [ 'jquery' ], AHREFS_SEO_VERSION, true );
		wp_register_script( 'datatables', AHREFS_SEO_URL . 'assets/js/datatables.min.js', [], AHREFS_SEO_VERSION, true );
		wp_register_script( 'ahrefs-seo-content', AHREFS_SEO_URL . 'assets/js/content.js', [ 'jquery', 'datatables', 'jquery-ui-tooltip' ], AHREFS_SEO_VERSION, true );
		wp_register_script( self::AHREFS_JS_HANDLE, AHREFS_SEO_URL . 'assets/js/ahrefs.js', [ 'jquery', 'jquery-validate', 'datatables', 'jquery-ui-tooltip' ], AHREFS_SEO_VERSION, true );
		wp_register_style( 'ahrefs-seo', AHREFS_SEO_URL . 'assets/css/ahrefs.css', [], AHREFS_SEO_VERSION );
	}
	/**
	 * Add JS and CSS files to plugin's admin screens.
	 */
	private function add_scripts() {
		add_thickbox();
		wp_enqueue_script( 'ahrefs-seo-content' );
		wp_enqueue_script( 'ahrefs-seo-backlinks' );
		wp_enqueue_script( 'ahrefs-seo-link-rules' );
		wp_enqueue_script( self::AHREFS_JS_HANDLE );
		wp_enqueue_style( 'ahrefs-seo' );
	}
	/**
	 * Check and show notice if ahrefs account is disconnected or over limit.
	 */
	public function maybe_show_ahrefs_notices() {
		$api = Ahrefs_Seo_Api::get();
		if ( $api->is_disconnected() ) {
			$this->show_part( 'notices/ahrefs-disconnected' );
		} elseif ( $api->is_limited_account() ) {
			$this->show_part( 'notices/ahrefs-limited' );
		}
	}
}
