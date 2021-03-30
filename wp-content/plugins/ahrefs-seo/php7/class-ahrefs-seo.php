<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Main class of Ahrefs Seo plugin.
 */
class Ahrefs_Seo {

	/** Menu slug for plugin. */
	const SLUG = 'ahrefs';
	/** Menu slug for Content Audit submenu. */
	const SLUG_CONTENT = 'ahrefs';
	/** Menu slug for Settings submenu. */
	const SLUG_SETTINGS = 'ahrefs-settings';
	/** Capability for plugin using */
	const CAPABILITY            = 'activate_plugins'; // Administrator.
	const ACTION_DOMAIN_CHANGED = 'ahrefs_seo_domain_changed';
	const ACTION_TOKEN_CHANGED  = 'ahrefs_seo_token_changed';
	/** Version of plugin, used for running update when current version changed. */
	const OPTION_PLUGIN_VERSION = 'ahrefs-seo-plugin-version';
	/** Version of database, used for running update when current version changed. */
	const OPTION_TABLE_VERSION = 'ahrefs-seo-db-version';
	/** Version of Content Audit rules, used for running update when current version changed. */
	const OPTION_CONTENT_RULES_VERSION = 'ahrefs-seo-content-rules-version';
	const OPTION_LAST_HASH             = 'ahrefs-seo-last-hash';

	/** Table name, without prefix. */
	const TABLE_CONTENT   = 'ahrefs_seo_content';
	const TABLE_SNAPSHOTS = 'ahrefs_seo_snapshots';

	/**
	* Ahrefs key is submitted and correct.
	*/
	const OPTION_IS_INITIALIZED = 'ahrefs-seo-is-initialized1';
	/**
	* Analytics code is submitted and correct.
	*/
	const OPTION_IS_INITIALIZED_ANALYTICS = 'ahrefs-seo-is-initialized2';
	/**
	* Analysis started: user is seeing step 3.2 with progress bar instead of steps 1-2.
	*/
	const OPTION_IS_INITIALIZED_IN_PROGRESS = 'ahrefs-seo-is-initialized21';
	/**
	* Load or do not load wizard screens.
	* Turned on after initial backlinks tranfer and content analysis completed.
	*/
	const OPTION_IS_INITIALIZED_FIRST_TIME = 'ahrefs-seo-is-initialized3';
	/**
	* Load or do not load wizard screen ajax stuff.
	* Turned off after any normal page opened first time.
	* (It turned on a bit later then previous option, because we want that already opened wizard page receive updates using ajax).
	*/
	const OPTION_IS_INITIALIZED_WIZARD_COMPLETED = 'ahrefs-seo-is-initialized4';

	/**
	* Allow to send error diagnostic reports to Ahrefs.
	*
	* Help us improve your plugin experience by automatically sending diagnostic reports to our server when an error occurs.
	* This will help with plugin stability and other improvements.
	* We take privacy seriously - we do not send any other information regarding your website when an error does not occur.
	*/
	const OPTION_ALLOW_REPORTS = 'ahrefs-seo-allow-reports';
	/**
	* Current database version. Increased when database tables structure changed.
	*/
	const CURRENT_TABLE_VERSION = '62'; // last published version is '59'.
	/**
	* Current Content Audit rules version. Increased when rules changed.
	*/
	const CURRENT_CONTENT_RULES = '4';

	/**
	 * This class instance.
	 *
	 * @var Ahrefs_Seo|null
	 */
	private static $instance = null;

	/**
	 * What is a source of thread: ping, wizard, fast, scheduled.
	 *
	 * @var string|null
	 */
	private static $thread_source = null;

	/**
	 * View class instance.
	 *
	 * @var Ahrefs_Seo_View
	 */
	private $view;

	/**
	 * Token class instance.
	 *
	 * @var Ahrefs_Seo_Token
	 */
	private $token;
	/**
	 * Wizard screen instance.
	 *
	 * @var Ahrefs_Seo_Screen_Wizard
	 */
	private $wizard;
	/**
	 * Content screen instance.
	 *
	 * @var Ahrefs_Seo_Screen_Content
	 */
	private $content;
	/**
	 * Settings screen instance.
	 *
	 * @var Ahrefs_Seo_Screen_Settings
	 * */
	private $settings;

	/**
	 * @var null|\Bugsnag\Client
	 */
	protected static $bugsnag;

	/**
	 * @var float
	 */
	private static $time_start;
	/**
	 * @var float
	 */
	private static $time_limit;
	/**
	 * Fatal error, plugin is not working.
	 *
	 * @var string
	 */
	private static $fatal_error = '';

	/**
	 * @var string Last reported error hash.
	 */
	private static $last_hash = '';

	/**
	 * Return the plugin instance
	 *
	 * @return Ahrefs_Seo
	 */
	public static function get() : Ahrefs_Seo {
		if ( ! self::$instance ) {
			self::$time_start = microtime( true );
			self::use_time_limit();

			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		try {
			$this->define_tables();
			$this->init();
		} catch ( \Error $e ) {
			$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on initialization' );
		} catch ( \Exception $e ) {
			self::notify( $e, 'Unexpected error on initialization' );
			Ahrefs_Seo_Errors::save_message( 'general', 'Unexpected error on initialization ' . $e->getMessage(), 'error' ); // show error to user if we can't submit it.
		}
	}

	/**
	 * Initialize plugin
	 */
	private function init() : void {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		if ( is_admin() ) {
			add_action( 'init', [ $this, 'init_screens' ] );
			add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		}
		add_action( 'init', [ $this, 'init_cron' ] );
		add_action( 'plugins_loaded', [ $this, 'quick_updates_check' ] );

		add_action( self::ACTION_TOKEN_CHANGED, [ $this, 'clear_caches_on_events' ] );
		add_action( self::ACTION_DOMAIN_CHANGED, [ $this, 'clear_caches_on_events' ] );
		// initialize earlier.
		if ( self::allow_reports() && ( ! defined( 'AHREFS_SEO_BUGSNAG_OFF' ) || ! AHREFS_SEO_BUGSNAG_OFF ) ) {
			self::$bugsnag = Ahrefs_Seo_Bugsnag::get()->create_client();
			\Bugsnag\Handler::register( self::$bugsnag );
		}

		if ( $this->initialized_get() ) {
			if ( is_admin() ) {
				add_action( 'admin_init', [ $this, 'add_post_columns' ] );
				add_action( 'init', [ $this, 'add_table_and_post_actions' ] );
			}

			// initialize Content Audit hooks.
			Content_Hooks::get();
		}
	}

	/**
	 * Initialize cron jobs.
	 */
	public function init_cron() : void {
		if ( empty( $this::$fatal_error ) ) {
			if ( ! $this->initialized_get() ) {
				// allow cron jobs once Wizard update is in progress.
				if ( get_option( self::OPTION_IS_INITIALIZED_IN_PROGRESS ) ) {
					Ahrefs_Seo_Cron::get();
				}
			} else {
				Ahrefs_Seo_Cron::get();
			}
		}
	}

	/**
	 * Initialize admin part
	 */
	public function admin_init() : void {
		$this->token = Ahrefs_Seo_Token::get();
		if ( ! get_option( self::OPTION_IS_INITIALIZED_WIZARD_COMPLETED ) ) {
			Ahrefs_Seo_Data_Wizard::get(); // ajax handlers for wizard progress.
		}
		Ahrefs_Seo_Notice::get( $this->view );
	}

	/**
	 * Initialize screens
	 */
	public function init_screens() : void {
		$this->view = new Ahrefs_Seo_View();
		if ( ! empty( $this::$fatal_error ) ) {
			$this->settings = new Ahrefs_Seo_Screen_Settings( $this->view );
		} elseif ( ! $this->initialized_get() ) {
			$this->wizard = new Ahrefs_Seo_Screen_Wizard( $this->view );
		} else {
			$this->content  = new Ahrefs_Seo_Screen_Content( $this->view );
			$this->settings = new Ahrefs_Seo_Screen_Settings( $this->view );
		}
	}

	/**
	 * Called on plugin activation.
	 * Add cron tasks.
	 */
	public static function plugin_activate() : void {
		Ahrefs_Seo_Cron::get()->add_tasks();
	}

	/**
	 * Called on plugin deactivation.
	 * Remove cron tasks.
	 */
	public static function plugin_deactivate() : void {
		Ahrefs_Seo_Cron::get()->remove_tasks();
	}

	/**
	 * Add items to admin menu
	 */
	public function add_admin_menu() : void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="21" height="20" viewBox="0 0 21 20"><defs><path id="a" d="M2 4H0V2h2V0h8.005v2H12v12H2.003v-1.998H0V7.997h2l.006-1.994h5.997V2H2v2zm6.003 3.997h-4V12h4V7.997z"/></defs><g fill="none" fill-rule="evenodd" transform="translate(5 3)"><mask id="b" fill="#fff"><use xlink:href="#a"/></mask><use fill="#9EA3A8" xlink:href="#a"/><g fill="#FFF" mask="url(#b)"><path d="M-45-44H55V56H-45z"/></g></g></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( ! empty( $this::$fatal_error ) ) {
			$this->view->add_admin_screen( $this->settings, add_menu_page( 'Ahrefs SEO for WordPress', 'Ahrefs SEO', self::CAPABILITY, self::SLUG, [ $this, 'error_menu' ], $icon, 81 ) );
			return;
		}

		if ( ! $this->initialized_get() ) {
			// show wizard.
			$this->view->add_admin_screen( $this->wizard, add_menu_page( 'Ahrefs SEO for WordPress', 'Ahrefs SEO', self::CAPABILITY, self::SLUG, [ $this, 'wizard_menu' ], $icon, 81 ) );
		} else {
			if ( get_option( self::OPTION_IS_INITIALIZED_FIRST_TIME ) && ! get_option( self::OPTION_IS_INITIALIZED_WIZARD_COMPLETED ) ) {
				$this->initialized_set( null, null, null, null, true );
			}

			// subpages + settings.
			$this->view->add_admin_screen( $this->content, add_menu_page( 'Ahrefs SEO for WordPress', 'Ahrefs SEO', self::CAPABILITY, self::SLUG, array( $this, 'content_menu' ), $icon, 81 ) );
			$page = add_submenu_page( self::SLUG, 'Content Audit', 'Content Audit', self::CAPABILITY, self::SLUG_CONTENT, array( $this, 'content_menu' ) );
			if ( $page ) {
				$this->view->add_admin_screen( $this->content, $page );
			}
			$page = add_submenu_page( self::SLUG, 'Ahrefs SEO for WordPress', 'Settings', self::CAPABILITY, self::SLUG_SETTINGS, array( $this, 'settings_menu' ) );
			if ( $page ) {
				$this->view->add_admin_screen( $this->settings, $page );
			}
		}
	}

	/**
	 * Handler for Wizard menu item.
	 * It used until Wizard will be finished.
	 */
	public function wizard_menu() : void {
		$this->wizard->show();
	}

	/**
	 * Handler for Settings menu item
	 */
	public function settings_menu() : void {
		$this->settings->show();
	}


	/**
	 * Handler for Content Audit menu item
	 */
	public function content_menu() : void {
		$this->content->show();
	}

	/**
	 * Handler for Error menu item
	 */
	public function error_menu() : void {
		$this->view->show(
			'fatal',
			'Content Audit Error',
			[
				'error'        => $this::$fatal_error,
				'header_class' => [ 'content' ],
			],
			$this->settings,
			'error'
		);
	}

	/**
	 * Clear vary caches.
	 */
	public function clear_caches_on_events() : void {
		// need to call clear cache for those instances, because they may be not initialized before.
		Ahrefs_Seo_Api::get()->clear_cache();
	}

	/**
	 * Check DB version and run update if current version is different from version value saved at the DB.
	 * Do same for Content Audit rules.
	 */
	public function quick_updates_check() : void {
		try {
			if ( get_option( self::OPTION_TABLE_VERSION ) !== self::CURRENT_TABLE_VERSION ) {
				$previous_version = intval( get_option( self::OPTION_TABLE_VERSION, 0 ) );
				if ( Ahrefs_Seo_Db::create_table( $previous_version ) ) {
					update_option( self::OPTION_TABLE_VERSION, self::CURRENT_TABLE_VERSION );
				} else {
					return;
				}
			}
			if ( get_option( self::OPTION_CONTENT_RULES_VERSION ) !== self::CURRENT_CONTENT_RULES ) {
				Ahrefs_Seo_Data_Content::get()->update_options( (int) get_option( self::OPTION_CONTENT_RULES_VERSION ) );
				// run new analysis on rules version update.
				if ( (int) get_option( self::OPTION_CONTENT_RULES_VERSION ) > 4 ) {
					if ( Ahrefs_Seo_Compatibility::quick_compatibility_check() ) {
						( new Snapshot() )->create_new_snapshot();
					}
				}
				update_option( self::OPTION_CONTENT_RULES_VERSION, self::CURRENT_CONTENT_RULES );
			}
		} catch ( \Error $e ) {
			$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on quick_updates_check' );
		} catch ( \Exception $e ) {
			self::notify( $e, 'Unexpected error on quick_updates_check' );
		}
	}

	/**
	 * Set vary initialized statuses to on or off.
	 * Update status only if not null.
	 *
	 * @param null|bool $is_initialized_ahrefs Is ahrefs initialized (has valid token).
	 * @param null|bool $is_initialized_analytics Is analytics initialized (has valid token AND ua_id profile chosen).
	 * @param null|bool $analysis_in_progress Analysis started: user is seeing step 3.2 with progress bar.
	 * @param null|bool $is_initialized_first_time Load or do not load wizard screens.
	 * Turned off after initial backlinks tranfer and content analysis completed.
	 * @param null|bool $is_initialized_wizard_completed Load or do not load wizard screen ajax stuff.
	 * Turned off after any normal page opened first time.
	 * (It turned on a bit longer then previous option, because we want that already opened wizard page receive updates using ajax).
	 * @return void
	 */
	public function initialized_set( ?bool $is_initialized_ahrefs, ?bool $is_initialized_analytics = null, ?bool $analysis_in_progress = null, ?bool $is_initialized_first_time = null, ?bool $is_initialized_wizard_completed = null ) : void {
		self::breadcrumbs( sprintf( 'initialized_set(%s)', wp_json_encode( func_get_args() ) ) );

		if ( ! is_null( $is_initialized_ahrefs ) ) {
			update_option( self::OPTION_IS_INITIALIZED, $is_initialized_ahrefs );
			if ( ! $is_initialized_ahrefs ) {
				// reset current token.
				$this->token->token_save( '' );
			}
		}
		if ( ! is_null( $is_initialized_analytics ) ) {
			update_option( self::OPTION_IS_INITIALIZED_ANALYTICS, $is_initialized_analytics );
			if ( ! $is_initialized_analytics ) {
				// reset current token if any.
				Ahrefs_Seo_Analytics::get()->disconnect();
			}
		}
		if ( ! is_null( $analysis_in_progress ) ) {
			update_option( self::OPTION_IS_INITIALIZED_IN_PROGRESS, $analysis_in_progress );
		}
		if ( ! is_null( $is_initialized_first_time ) ) {
			update_option( self::OPTION_IS_INITIALIZED_FIRST_TIME, $is_initialized_first_time );
		}
		if ( ! is_null( $is_initialized_wizard_completed ) ) {
			update_option( self::OPTION_IS_INITIALIZED_WIZARD_COMPLETED, $is_initialized_wizard_completed );
		}
	}

	/**
	 * Is Wizard already initialized?
	 * No need to show it again if already did.
	 *
	 * @return bool
	 */
	public function initialized_wizard() : bool {
		$value = get_option( self::OPTION_IS_INITIALIZED_FIRST_TIME );
		return ! empty( $value );
	}

	/**
	 * Is plugin initialized
	 *
	 * @return bool
	 */
	public function initialized_get() : bool {
		static $result = null;
		// function called twice, must return same result.
		if ( is_null( $result ) ) {
			$value2 = get_option( self::OPTION_IS_INITIALIZED_FIRST_TIME );
			$result = ! empty( $value2 );
		}
		return $result;
	}

	/**
	 * Add post columns to posts and pages.
	 */
	public function add_post_columns() : void {
		// Add the custom columns to the post and page post types.
		add_filter( 'manage_post_posts_columns', [ $this, 'set_custom_columns' ] );
		add_filter( 'manage_page_posts_columns', [ $this, 'set_custom_columns' ] );
		// Add the data to the custom columns for the post and page post types.
		add_action( 'manage_post_posts_custom_column', [ $this, 'custom_column' ], 10, 2 );
		add_action( 'manage_page_posts_custom_column', [ $this, 'custom_column' ], 10, 2 );
	}

	/**
	 * Add custom column for posts and pages screens
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function set_custom_columns( $columns ) {
		// Callback. Do not use parameter types.
		$columns['ahrefs_organic'] = 'Organic traffic';

		return $columns;
	}

	/**
	 * Show content for post custom columns.
	 * Monthly amount of organic traffic + total amount of organic traffic from post created/modified time.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function custom_column( $column, $post_id ) : void {
		switch ( $column ) {
			case 'ahrefs_organic':
				/** @var null|\WP_Post */
				$post    = get_post( $post_id );
				$traffic = ( new Content_Db() )->content_get_organic_traffic_data_for_post( intval( $post_id ) );
				if ( is_null( $post ) || ( 0 === $post->ID ) || 'publish' !== $post->post_status || is_null( $traffic ) || ( is_null( $traffic['organic_month'] ) && is_null( $traffic['organic_total'] ) ) ) {
					?>
					<div class="ahrefs-traffic-wrapper">
						<span aria-hidden="true">—</span>
						<span class="screen-reader-text">No info</span>
					</div>
					<?php
				} else {
					?>
					<div class="ahrefs-traffic-wrapper">
						<span class="ahrefs_traffic_month"><span class="ahrefs_value"><?php echo esc_html( $traffic['organic_month'] ?? '—' ); ?></span> /month</span>
						<span class="ahrefs_traffic_total">Total: <span class="ahrefs_value"><?php echo esc_html( $traffic['organic_total'] ?? '—' ); ?></span></span>
					</div>
					<?php
				}
				$this->add_admin_css_posts();
				break;
		}
	}

	/**
	 * Add styles for post columns
	 */
	private function add_admin_css_posts() : void {
		static $added = null;
		if ( is_null( $added ) ) {
			$added = true;
			?>
			<style type="text/css">
				.ahrefs-traffic-wrapper .ahrefs_traffic_month, .ahrefs-traffic-wrapper .ahrefs_traffic_total {
					font-family: Arial;
					color: #555555;
				}
				.ahrefs-traffic-wrapper .ahrefs_traffic_month {
					font-family: Arial;
					color: #555555;
					display: block;
				}
				.ahrefs-traffic-wrapper .ahrefs_traffic_month .ahrefs_value {
					font-size: large;
				}
				.ahrefs-traffic-wrapper .ahrefs_value {
					white-space: pre;
				}
			</style>
			<?php
		}
	}

	/**
	 * Callback. Required for saving per page options.
	 *
	 * @param bool|int|string $status   Whether to save or skip saving the screen option value.
	 * @param string          $option The option name.
	 * @param int             $value  The number of rows to use.
	 * @return bool|int|string
	 */
	public function option_filter( $status, $option, $value ) {
		// callback, do not use parameter types.
		if ( 'ahrefs_seo_table_content_per_page' === $option ) {
			return intval( $value );
		}
		return $status;
	}


	/**
	 * Add filter for backlinks and content audit tables 'per page' option save.
	 * Add filter for catch posts status change.
	 *
	 * @return void
	 */
	public function add_table_and_post_actions() : void {
		add_filter( 'set-screen-option', [ $this, 'option_filter' ], 10, 3 );
		add_filter( 'set_screen_option_ahrefs_seo_table_content_per_page', [ $this, 'option_filter' ], 10, 3 );
	}

	/**
	 * Register custom tables within $wpdb object.
	 */
	private function define_tables() : void {
		global $wpdb;

		// List of tables without prefixes [ name for use inside $wpdb => real table name ].
		$tables = array(
			'ahrefs_content'   => self::TABLE_CONTENT,
			'ahrefs_snapshots' => self::TABLE_SNAPSHOTS,
		);

		foreach ( $tables as $name => $table ) {
			$wpdb->$name    = $wpdb->prefix . $table;
			$wpdb->tables[] = $table;
		}
	}

	public static function should_finish( ?int $seconds_to_end = null, ?int $percents_to_end = null ) : bool {
		if ( ! is_null( $percents_to_end ) ) {
			$seconds_to_end = ( 100 - $percents_to_end ) / 100.0 * self::$time_limit;
			if ( $seconds_to_end > 10 ) {
				$seconds_to_end = 10;
			}
		}
		if ( is_null( $seconds_to_end ) ) {
			$seconds_to_end = self::$time_limit > 30 ? 10 : 5;
		}
		return microtime( true ) - self::$time_start >= self::$time_limit - $seconds_to_end;
	}

	public static function transient_time() : int {
		return intval( self::$time_limit );
	}

	/**
	 * Set fatal error reason
	 *
	 * @param string $reason
	 * @return void
	 */
	public static function set_fatal_error( string $reason ) : void {
		self::$fatal_error = $reason;
	}

	/**
	 * Return available memory in Mb.
	 *
	 * @return float
	 */
	public static function get_available_memory() : float {
		$limit = strtoupper( (string) ini_get( 'memory_limit' ) );
		if ( '-1' === $limit ) {
			$limit = '100M'; // assume 100M.
		}
		$mem_limit = (int) preg_replace_callback(
			'/(\-?\d+)(.?)/',
			function ( $m ) {
				if ( $m[2] ) {
					$pos = strpos( 'BKMG', $m[2] );
					if ( false !== $pos ) {
						return $m[1] * pow( 1024, $pos );
					}
				}
				return $m[1];
			},
			$limit
		);

		$usage = memory_get_usage();
		return ( $mem_limit - $usage ) / 1024.0 / 1024.0;
	}

	private static function use_time_limit() : void {
		self::$time_limit = intval( ini_get( 'max_execution_time' ) );
		$expected_limit   = self::is_doing_real_cron() ? 300 : 15;
		if ( self::$time_limit <= 0 ) {
			self::$time_limit = $expected_limit;
		}
		if ( self::$time_limit > $expected_limit ) {
			self::$time_limit = $expected_limit;
		}
		if ( $expected_limit > self::$time_limit ) {
			self::set_time_limit( $expected_limit );
		}
	}

	/**
	 * A real cron job is running now.
	 *
	 * @return bool
	 */
	public static function is_doing_real_cron() : bool {
		return wp_doing_cron() && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	/**
	 * Check if set_time_limit allowed and call it.
	 * Update internal time limit value.
	 * Try to avoid possible php warning "set_time_limit() has been disabled for security reasons".
	 *
	 * @param int $seconds
	 *
	 * @return bool
	 */
	public static function set_time_limit( int $seconds ) : bool {
		if ( $seconds > self::$time_limit ) { // no need to decrease allowed time.
			if ( function_exists( 'set_time_limit' ) && ! self::function_disabled( 'set_time_limit' ) ) {
				if ( @set_time_limit( $seconds ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the checking is passed, but function call may produce warning: "set_time_limit(): Cannot set max execution time limit due to system policy" anyway.
					self::$time_start = microtime( true );
					self::use_time_limit();
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Delay execution in microseconds, update time management.
	 *
	 * @since 0.7.3
	 *
	 * @param int $micro_seconds
	 * @return void
	 */
	public static function usleep( int $micro_seconds ) : void {
		usleep( $micro_seconds );
		if ( ( 'scheduled' === self::$thread_source || 'fast' === self::$thread_source ) && self::is_doing_real_cron() ) { // do not increase allowed time for requests, coming directly from user.
			self::$time_limit += $micro_seconds / 1000000;
		}
	}

	/**
	 * Check if ignore_user_abort allowed and call it.
	 * Try to avoid possible php warning "ignore_user_abort() has been disabled for security reasons".
	 *
	 * @param bool $ignore
	 *
	 * @return void
	 */
	public static function ignore_user_abort( bool $ignore ) : void {
		if ( function_exists( 'ignore_user_abort' ) && ! self::function_disabled( 'ignore_user_abort' ) ) {
			ignore_user_abort( $ignore );
		}
	}

	private static function function_disabled( string $function_name ) : bool {
		$disabled = explode( ',', (string) ini_get( 'disable_functions' ) );
		return in_array( $function_name, $disabled, true );
	}

	/**
	 * Set breadcrumbs for current code execution.
	 *
	 * @param string $string
	 * @param bool   $is_error
	 *
	 * @return void
	 */
	public static function breadcrumbs( string $string, bool $is_error = false ) : void {
		if ( ! is_null( self::$bugsnag ) ) {
			self::$bugsnag->leaveBreadcrumb( $string, $is_error ? \Bugsnag\Breadcrumbs\Breadcrumb::ERROR_TYPE : \Bugsnag\Breadcrumbs\Breadcrumb::MANUAL_TYPE );
		} else {
			if ( defined( 'AHREFS_SEO_RELEASE' ) && 'development' === AHREFS_SEO_RELEASE && ! defined( 'AHREFS_SEO_SILENT' ) ) {
				error_log( '** ' . self::thread_id() . ( $is_error ? " Error $string" : " Log $string" ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	/**
	 * Notify (if allowed) about exception happened.
	 *
	 * @param \Throwable  $e Exception to report.
	 * @param null|string $type
	 *
	 * @return void
	 */
	public static function notify( \Throwable $e, ?string $type = null ) : void {
		$hash = ( (string) $e );
		if ( $hash === self::$last_hash ) { // do not report same error twice.
			return;
		}
		self::$last_hash = $hash;
		$events          = get_option( self::OPTION_LAST_HASH, [] );
		$count           = 0;
		if ( is_array( $events ) && isset( $events['hash'] ) && $events['hash'] === $hash ) {
			$count = absint( $events['count'] );
			if ( $count > 5 && 0 !== $count % 100 ) { // do not report same error many times.
				return;
			}
		}
		if ( $count >= 5 ) {
				self::breadcrumbs( sprintf( 'No more reports (repeated %d times).', $count ) );
		}
		update_option(
			self::OPTION_LAST_HASH,
			[
				'hash'  => $hash,
				'count' => ++$count,
			]
		);

		if ( ! is_null( self::$bugsnag ) ) {
			self::$bugsnag->notifyException(
				$e,
				function( $report ) use ( $type ) {
					if ( ! is_null( $type ) ) {
						/** @var \Bugsnag\Report $report */
						$report->addMetaData( [ 'type' => [ 'type' => $type ] ] );
					}
					return $report;
				}
			);
		} elseif ( defined( 'AHREFS_SEO_RELEASE' ) && ! defined( 'AHREFS_SEO_SILENT' ) ) {
			error_log( sprintf( '** Exception %s', (string) $e ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Set is reports allowed.
	 *
	 * @param bool $enable
	 *
	 * @return void
	 */
	public static function allow_reports_set( bool $enable = true ) : void {
		update_option( self::OPTION_ALLOW_REPORTS, $enable );
	}

	/**
	 * Is reports allowed?
	 *
	 * @return bool
	 */
	public static function allow_reports() : bool {
		return (bool) get_option( self::OPTION_ALLOW_REPORTS, false );
	}

	/**
	 * Get current domain.
	 *
	 * @return string
	 */
	public static function get_current_domain() : string {
		$domain = apply_filters( 'ahrefs_seo_domain', wp_parse_url( get_site_url(), PHP_URL_HOST ) ?? '' );
		return is_string( $domain ) ? $domain : '';
	}

	/**
	 * Return unique id for current thread
	 *
	 * @since 0.7.3
	 *
	 * @param string|null $source
	 * @return string
	 */
	public static function thread_id( ?string $source = null ) : string {
		static $result = null;
		if ( ! is_null( $source ) && is_null( self::$thread_source ) ) {
			self::$thread_source = $source;
		}
		if ( is_null( $result ) ) {
			$result = self::$time_start . '-' . rand( 1000000, 9999999 ) . ( wp_doing_cron() ? ( self::is_doing_real_cron() ? 'cron' : 'wpcron' ) : 'user' );
		}
		return $result . ( is_null( self::$thread_source ) ? '' : '-' . self::$thread_source ) . self::$time_limit;
	}

	/**
	 * @return Ahrefs_Seo_View
	 */
	public function get_view() : Ahrefs_Seo_View {
		return $this->view;
	}
}
