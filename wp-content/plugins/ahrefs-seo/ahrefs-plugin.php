<?php
/**
Plugin Name: Ahrefs SEO
Plugin URI: https://ahrefs.com/wordpress-seo-plugin
Description: Audit your content performance, improve your content quality & get more organic search traffic with the Ahrefs SEO plugin.
Author: Ahrefs
Author URI: https://ahrefs.com/
Version: 0.7.5
Requires at least: 5.0
Requires PHP: 5.5
 */

namespace ahrefs\AhrefsSeo;

define( 'AHREFS_SEO_VERSION', '0.7.5' );
define( 'AHREFS_SEO_RELEASE', 'production' );
define( 'AHREFS_SEO_DIR', dirname( __FILE__ ) );
if ( ! defined( 'AHREFS_SEO_URL' ) ) {
	define( 'AHREFS_SEO_URL', plugin_dir_url( __FILE__ ) );
}
define( 'AHREFS_SEO_IMAGES_URL', AHREFS_SEO_URL . 'assets/images/' );

// check minimal php version.
if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
	if ( file_exists( AHREFS_SEO_DIR . '/vendor/autoload.php' ) ) {
		require_once AHREFS_SEO_DIR . '/vendor/autoload.php';
	}

	// php 7.1 - static type declaration: strict mode enabled & return type declarations, include void return type.
	define( 'AHREFS_SEO_CLASSES', AHREFS_SEO_DIR . '/' . ( version_compare( PHP_VERSION, '7.1.0' ) >= 0 ? 'php7' : 'php5' ) );

	// autoload plugin classes.
	spl_autoload_register( // @phpstan-ignore-next-line -- can not use return type at php5.
		function( $class ) {
			$prefix = 'ahrefs\AhrefsSeo\\';
			$len    = strlen( $prefix );
			if ( 0 !== strncmp( $prefix, $class, $len ) ) {
				return;
			}
			$relative_class = strtolower( str_replace( '_', '-', substr( $class, $len ) ) );
			$file           = AHREFS_SEO_CLASSES . str_replace( '\\', '/', '/class-' . $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);

	register_activation_hook(
		__FILE__,
		function() {
			Ahrefs_Seo::plugin_activate();
		}
	);
	register_deactivation_hook(
		__FILE__,
		function() {
			Ahrefs_Seo::plugin_deactivate();
		}
	);

	Ahrefs_Seo::get();
}
