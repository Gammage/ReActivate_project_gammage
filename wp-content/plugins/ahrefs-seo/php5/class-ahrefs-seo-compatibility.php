<?php

namespace ahrefs\AhrefsSeo;

/**
 * Compatibility with other plugins class.
 *
 * Predict errors: quick compatibility check:
 * - run before content audit start,
 * - on each Wizard page,
 * - on on Ahrefs, Google accounts pages.
 * Catch compatibility errors:
 * - during content audit;
 * - on API calls.
 *
 * On compability error the Content audit is paused.
 * We save reason of error (plugins or theme) and check, are these plugins active or not.
 * If at least one is inactive: try to unpause Content audit.
 *
 * @since 0.7.4
 */
class Ahrefs_Seo_Compatibility {

	/**
	 * @var string|null Full message with last error found or empty string.
	 */
	private static $last_message = null;
	/**
	 * @var string[]|null
	 */
	private static $plugins_list;
	/**
	 * @var string[]|null
	 */
	private static $themes_list;
	/**
	 * @var string[]|null
	 */
	private static $files_list;
	/**
	 * @var string[]
	 */
	private static $plugins_slugs = [];
	/**
	 * @var string[]
	 */
	private static $theme_slugs = [];
	/** @var string[] */
	private static $displayed_messages = [];
	/** @var string */
	private static $type = 'tip-compatibility';
	/**
	 * Check required classes and libraries.
	 * Will stop Content audit on incompatibility.
	 * DO NOT save any 'compatibility' message.
	 *
	 * @return bool True - no issues found.
	 */
	public static function quick_compatibility_check() {
		self::$plugins_slugs = [];
		self::$theme_slugs   = [];
		$error               = '';
		$result              = null;
		$incorrect_classes   = [];
		self::$last_message  = '';
		$classes             = [ '\\Google_Client', '\\GuzzleHttp\\Client', '\\Google\\Auth\\OAuth2', '\\Google\\Auth\\Middleware\\AuthTokenMiddleware', '\\GuzzleHttp\\Utils' ];
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class ) ) {
				$error = "Required class \"{$class}\" not exists";
			}
		}
		if ( '' === $error ) {
			if ( version_compare( \Google_Client::LIBVER, '2.0', '<' ) ) { // @phpstan-ignore-line
				$error               = 'Too old version of Google Client';
				$incorrect_classes[] = '\\Google_Client';
			}
			if ( version_compare( \GuzzleHttp\Client::VERSION, '6.3', '<' ) ) { // @phpstan-ignore-line
				$error               = 'Too old version of Guzzle HTTP Client';
				$incorrect_classes[] = '\\GuzzleHttp\\Client';
			}
			if ( class_exists( '\\ReflectionClass' ) ) { // check parameters type.
				// check Google Auth.
				try {
					$rclass      = new \ReflectionClass( '\\Google\\Auth\\Middleware\\AuthTokenMiddleware' );
					$constructor = $rclass->getConstructor();
					if ( ! is_null( $constructor ) ) {
						/** @var \ReflectionParameter[] $parameters */
						$parameters = $constructor->getParameters();
						if ( count( $parameters ) < 3 || ! $parameters[1]->isCallable() ) {
							$error               = 'Too old version of Google Auth';
							$incorrect_classes[] = '\\Google\\Auth\\Middleware\\AuthTokenMiddleware';
						}
					}
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- do not report.
				}
				if ( ! class_exists( '\\Google_Service_AnalyticsData' ) ) {
					$error = 'Too old version of Google Services';
					// This class added 2020-09-10, check what is an incorrect library source using older class.
					$incorrect_classes[] = '\\Google_Service_AnalyticsData';
					$incorrect_classes[] = '\\Google_Service_AnalyticsReporting';
				}
			}
			// check Guzzle Utils.
			if ( ! method_exists( '\\GuzzleHttp\\Utils', 'idnUriConvert' ) ) {
				$error               = 'Incorrect version of Guzzle Utils';
				$incorrect_classes[] = '\\GuzzleHttp\\Utils';
			}
			// check Guzzle Utils.
			if ( ! defined( '\\GuzzleHttp\\RequestOptions::READ_TIMEOUT' ) ) {
				$error               = 'Incorrect version of Guzzle RequestOptions';
				$incorrect_classes[] = '\\GuzzleHttp\\RequestOptions';
			}
		}
		if ( ! empty( $incorrect_classes ) && class_exists( '\\ReflectionClass' ) ) {
			$trace = [];
			// fill trace with path of files, where classes defined.
			foreach ( $incorrect_classes as $incorrect_class ) {
				if ( class_exists( $incorrect_class ) ) {
					try {
						$reflection = new \ReflectionClass( $incorrect_class );
						$trace[]    = [
							'file'     => $reflection->getFileName(),
							'function' => '',
						]; // file, where this class defined.
					} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- do not report.
					}
				}
			}
			// find a plugin or theme.
			self::$plugins_list = [];
			self::$themes_list  = [];
			self::$files_list   = [];
			$files              = self::analyze_stack( $trace, __METHOD__, __FILE__ );
			$sources            = [];
			foreach ( $files as $_file ) {
				self::search_source( $_file, self::$plugins_list, self::$themes_list, self::$files_list );
			}
			$result = ! empty( self::return_error_message( self::$plugins_list, self::$themes_list, self::$files_list, $error ) ) ? self::return_error_message( self::$plugins_list, self::$themes_list, self::$files_list, $error ) : $error;
			// alternative error message if no source detected, but incompatibility exists.
			self::$last_message = $result;
		}
		if ( '' !== $error ) {
			$reason = self::get_current_incompatibility();
			if ( ! is_null( $reason ) ) {
				Ahrefs_Seo::notify( new Ahrefs_Seo_Compatibility_Exception( $reason->get_text() ) );
			}
			Content_Audit::audit_stop( $reason ? [ $reason ] : [] );
		}
		return '' === $error;
	}
	/**
	 * Handle Error, search reason in plugins or themes, submit report.
	 * Save 'compatibility tip' or 'general tip' message.
	 *
	 * @param \Error      $e Catched PHP Error.
	 * @param string      $current_method Method where error was catched.
	 * @param string      $current_file File where error was catched.
	 * @param null|string $type
	 * @return string User friendly error reason.
	 */
	public static function on_type_error( \Error $e, $current_method, $current_file, $type = null ) {
		self::$plugins_slugs = [];
		self::$theme_slugs   = [];
		$result              = 'Unexpected error';
		self::$type          = 'error';
		try {
			$file          = $e->getFile();
			$file_relative = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $file ) ); // path inside WordPress root dir.
			$line                = $e->getLine();
			$message             = $e->getMessage();
			$error_type          = isset( $type ) ? $type : get_class( $e );
			self::$plugins_slugs = [];
			self::$theme_slugs   = [];
			self::$plugins_list  = [];
			self::$themes_list   = [];
			self::$files_list    = [];
			$common_reason       = sprintf( '"%s" %s in file %s [%d]: %s', $error_type, false === stripos( $error_type, 'error' ) ? 'error' : '', $file_relative, $line, $message );
			$trace               = $e->getTrace();
			// maybe wrong version of some class used?
			if ( class_exists( '\\ReflectionClass' ) ) {
				$real_classes = [];
				$classes      = [ 'GuzzleHttp\\Client', 'Google\\Auth\\OAuth2', 'Google\\Client', 'GuzzleHttp\\Utils' ];
				foreach ( $classes as $class_name ) {
					if ( strpos( $message, $class_name ) ) {
						if ( preg_match( sprintf( '!(%s.*?)::!', str_replace( '\\', '\\\\', $class_name ) ), $message, $m ) ) {
							$real_classes[] = $m[1];
						}
					}
				}
				if ( strpos( $message, 'ON_STATS' ) || strpos( $message, 'READ_TIMEOUT' ) ) {
					$real_classes[] = 'GuzzleHttp\\RequestOptions';
				}
				if ( count( $real_classes ) ) {
					foreach ( $real_classes as $real_class ) {
						if ( class_exists( '\\' . $real_class ) ) {
							try {
								$reflection = new \ReflectionClass( $real_class );
								array_unshift(
									$trace,
									[
										'file'     => $reflection->getFileName(),
										'function' => '',
									]
								); // file, where this class defined.
							} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- do not report.
							}
						}
					}
				}
			}
			array_unshift(
				$trace,
				[
					'file'     => $file,
					'function' => '',
				]
			); // add original error position too.
			$files   = self::analyze_stack( $trace, $current_method, $current_file );
			$sources = [];
			foreach ( $files as $_file ) {
				self::search_source( $_file, self::$plugins_list, self::$themes_list, self::$files_list );
			}
			$result = self::return_error_message( self::$plugins_list, self::$themes_list, self::$files_list, $common_reason );
			if ( is_null( $result ) ) {
				$result = sprintf( 'Unexpected "%s" error in file %s [%d]: %s', $error_type, $file_relative, $line, $message ); // default message.
				Ahrefs_Seo::notify( $e, $error_type ); // source error only.
			} else {
				Ahrefs_Seo::notify( new Ahrefs_Seo_Compatibility_Exception( $result . ' ' . $common_reason, 0, $e ), $error_type ); // some incompatibility found in other plugin or theme. Report with source reason.
			}
		} catch ( \Exception $ee ) {
			Ahrefs_Seo::notify( $ee, 'error at handler' );
			Ahrefs_Seo::notify( $e, 'initial error' );
		}
		self::$last_message = $result;
		$reason             = self::get_current_incompatibility();
		Content_Audit::audit_stop( $reason ? [ $reason ] : [] );
		return $result;
	}
	/**
	 * Set that message displayed
	 *
	 * @param string $message
	 * @return void
	 */
	public static function set_message_displayed( $message = '' ) {
		self::$displayed_messages[] = $message;
	}
	/**
	 * Clean message if it already displayed
	 *
	 * @param string $message New error message, in-out parameter.
	 * @return bool True if message filtered (already displayed).
	 */
	public static function filter_messages( &$message ) {
		if ( count( self::$displayed_messages ) ) {
			foreach ( self::$displayed_messages as $full_text ) {
				if ( false !== stripos( $full_text, $message ) ) {
					$message = '';
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * Return path of files, called before the Error was catched.
	 *
	 * @param array  $trace
	 * @param string $method
	 * @param string $file
	 * @return string[] List of directories from trace, where error happened.
	 */
	private static function analyze_stack( array $trace, $method, $file ) {
		$result = [];
		$file   = wp_normalize_path( $file );
		foreach ( $trace as $item ) {
			if ( isset( $item['file'] ) ) {
				$path = wp_normalize_path( $item['file'] );
				if ( isset( $item['class'] ) && ( $file === $path && $method === $item['class'] . '::' . $item['function'] || false !== strpos( $path, 'class-wp-hook.php' ) && 'ajax_content_ping' === $item['function'] && 'ahrefs\\AhrefsSeo\\Ahrefs_Seo_Screen_Content' === $item['class'] || false !== strpos( $path, 'class-wp-hook.php' ) && 'ajax_progress' === $item['function'] && 'ahrefs\\AhrefsSeo\\Ahrefs_Seo_Data_Wizard' === $item['class'] || false !== strpos( $path, 'class-cron-any.php' ) && 'run_task' === $item['function'] && 'ahrefs\\AhrefsSeo\\Cron_Any' === $item['class'] || false !== strpos( $path, 'class-ahrefs-seo-view.php' ) && 'show' === $item['function'] && 'ahrefs\\AhrefsSeo\\Ahrefs_Seo_View' === $item['class'] ) ) {
					break;
				} else {
					$result[] = $path;
				}
			}
		}
		return array_values( array_unique( $result ) );
	}
	/**
	 * Search is file a part of another plugin or theme. Fill lists with result.
	 *
	 * @param string   $file
	 * @param string[] $plugins_list
	 * @param string[] $themes_list
	 * @param string[] $files_list
	 * @return void
	 */
	protected static function search_source( $file, array &$plugins_list, array &$themes_list, array &$files_list ) {
		static $themes_path = null;
		if ( is_null( $themes_path ) ) {
			$themes_path = dirname( wp_normalize_path( get_template_directory() ) );
		}
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			$files_list[] = $file;
			return;
		}
		$plugin_base = plugin_basename( $file );
		if ( strlen( $plugin_base ) < strlen( $file ) ) { // conflict inside plugins dir.
			$path = explode( '/', $plugin_base );
			if ( basename( AHREFS_SEO_DIR ) === $path[0] ) {
				return; // exclude self.
			}
			$all_plugins   = get_option( 'active_plugins' ) ?: [];
			$plugins_found = [];
			if ( 1 === count( $path ) ) { // plugin at the root, without individual folder.
				$plugin_name   = $path[0];
				$plugins_found = array_filter(
					$all_plugins,
					function ( $value ) use ( $plugin_name ) {
						return $value === $plugin_name;
					}
				);
			} else {
				$plugin_dir    = $path[0];
				$plugins_found = array_filter(
					$all_plugins,
					function ( $value ) use ( $plugin_dir ) {
						$path = explode( '/', $value );
						return $path[0] === $plugin_dir;
					}
				);
			}
			if ( is_array( $plugins_found ) && count( $plugins_found ) ) {
				if ( ! function_exists( 'get_plugin_data' ) ) { // already defined, if called after admin_init.
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( $plugins_found as $plugin_slug ) {
					self::$plugins_slugs[] = $plugin_slug;
				}
				array_walk(
					$plugins_found,
					function ( $plugin_slug ) use ( &$plugins_list ) {
						$fields         = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_slug );
						$plugins_list[] = ( isset( $fields['Name'] ) ? $fields['Name'] : $plugin_slug ) . ( ! empty( $fields['Version'] ) ? sprintf( ' version %s', $fields['Version'] ) : '' );
					}
				);
				return;
			}
		} else {
			$file_path_normalized = wp_normalize_path( $file );
			if ( 0 === strpos( $file_path_normalized, $themes_path ) ) {
				$file_path_normalized = substr( $file_path_normalized, strlen( $themes_path ) + 1 );
				$path                 = explode( '/', $file_path_normalized );
				$theme_found          = wp_get_theme( $path[0] );
				if ( $theme_found->exists() ) {
					self::$theme_slugs[] = $path[0];
					$version             = is_string( $theme_found->get( 'Version' ) ) ? $theme_found->get( 'Version' ) : '';
					$author              = is_string( $theme_found->get( 'Author' ) ) ? $theme_found->get( 'Author' ) : '';
					$name                = is_string( $theme_found->get( 'Name' ) ) ? $theme_found->get( 'Name' ) : "{$path[0]}";
					$themes_list[]       = sprintf( '"%s"%s%s', "{$name}", $version ? " version {$version}" : '', $author ? " by {$author}" : '' );
					return;
				}
			}
		}
		$files_list[] = $file;
	}
	/**
	 * Find error reason in other plugin or theme or file.
	 *
	 * @param string[] $plugins_list
	 * @param string[] $themes_list
	 * @param string[] $files_list
	 * @param string   $common_reason
	 * @return string|null
	 */
	protected static function return_error_message( $plugins_list, $themes_list, $files_list, $common_reason ) {
		$result     = null;
		self::$type = 'tip-compatibility';
		if ( count( $plugins_list ) ) {
			$plugins_list = array_unique( $plugins_list );
			$result       = sprintf( _n( 'We’ve scanned your WordPress environment and discovered %s which is conflicting with the Ahrefs SEO plugin. Please pause the plugin by deactivating it before running content audit again.', 'We’ve scanned your WordPress environment and discovered %s which are conflicting with the Ahrefs SEO plugin. Please pause these plugins by deactivating them before running content audit again.', count( $plugins_list ) ), implode( ', ', $plugins_list ) );
		} elseif ( count( $themes_list ) ) {
			$themes_list = array_unique( $themes_list );
			$result      = sprintf( 'We’ve scanned your WordPress environment and discovered %s, which is incompatible with the Ahrefs SEO plugin. Please switch the theme before running content audit again.', implode( ', ', $themes_list ) );
		} elseif ( count( $files_list ) ) {
			$files_list = array_map(
				function ( $value ) {
					return str_replace( ABSPATH, '', $value ); // remove part of path before WordPress root dir.
				},
				$files_list
			);
			$result = sprintf( _n( 'File %s has code incompatible with Ahrefs SEO plugin.', 'One of files %s have code incompatible with Ahrefs SEO plugin.', count( $files_list ) ), implode( ',', $files_list ) );
		} elseif ( $common_reason ) {
			self::$type = 'error';
			$result     = sprintf( '%s', $common_reason );
		}
		return $result;
	}
	/**
	 * Recheck saved incompability error.
	 *
	 * @since 0.7.5
	 *
	 * @return bool
	 */
	public static function recheck_saved_incompatibility() {
		/** @var Message[]|Message_Tip_Incompatible[]|null */
		$messages = Content_Audit::audit_get_paused_messages();
		if ( ! is_null( $messages ) ) {
			foreach ( $messages as $message ) {
				if ( $message instanceof Message_Tip_Incompatible ) { // maybe compatibility issue was resolved?
					/** @var Message_Tip_Incompatible $message */
					$plugins = $message->get_plugins();
					$themes  = $message->get_themes();
					// check if one of plugins or themes is inactive.
					if ( count( $plugins ) + count( $themes ) && count( $plugins ) + count( $themes ) > self::count_active_plugins( $plugins ) + self::count_active_themes( $themes ) ) {
						self::quick_compatibility_check();
						Content_Audit::audit_resume();
						break;
					}
				}
			}
		}
		return empty( self::$last_message );
	}
	/**
	 * Get last compability instance if exists
	 *
	 * @since 0.7.5
	 *
	 * @return Message|null
	 */
	public static function get_current_incompatibility() {
		return self::get_incompatible_message( self::$plugins_slugs, self::$theme_slugs, isset( self::$last_message ) ? self::$last_message : '' );
	}
	protected static function get_incompatible_message( array $plugins, array $themes, $message ) {
		if ( count( $plugins ) + count( $themes ) > 0 || '' !== $message ) {
			$title   = 'Incompatibility Found';
			$buttons = [];
			if ( count( $plugins ) ) {
				$title     = 'Incompatible Plugins Found';
				$buttons[] = 'plugins';
			} elseif ( count( $themes ) ) {
				$title     = 'Incompatible Theme Found';
				$buttons[] = 'themes';
			}
			$fields = [
				'type'    => self::$type,
				'title'   => $title,
				'message' => $message,
				'buttons' => $buttons,
				'plugins' => $plugins,
				'themes'  => $themes,
			];
			return Message::create( $fields );
		}
		return null;
	}
	/**
	 * Count number of active plugins from given list
	 *
	 * @since 0.7.5
	 *
	 * @param string[] $plugins Plugin slugs, same format as option "active_plugins" used.
	 * @return int
	 */
	protected static function count_active_plugins( array $plugins ) {
		$result      = 0;
		$all_plugins = get_option( 'active_plugins' ) ?: [];
		foreach ( $plugins as $plugin_slug ) {
			if ( in_array( $plugin_slug, $all_plugins, true ) ) {
				$result++;
			}
		}
		return $result;
	}
	/**
	 * Count number of active themes from given list
	 *
	 * @since 0.7.5
	 *
	 * @param string[] $themes Theme slugs.
	 * @return int
	 */
	protected static function count_active_themes( array $themes ) {
		$result = 0;
		if ( count( $themes ) ) {
			foreach ( $themes as $theme_slug ) {
				$theme_found = wp_get_theme( $theme_slug );
				if ( $theme_found->exists() && $theme_found->get_stylesheet() === wp_get_theme()->get_stylesheet() ) {
					$result++;
				}
			}
		}
		return $result;
	}
}
