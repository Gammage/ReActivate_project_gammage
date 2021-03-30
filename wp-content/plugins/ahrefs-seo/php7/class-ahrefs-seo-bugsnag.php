<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

use \Google_Client as Google_Client;

/**
 * Class for interacting with Bugsnag.
 */
class Ahrefs_Seo_Bugsnag {

	/**
	 * @var string
	 */
	private $api_key = '476ca398513aa4c0b66b02a9cbd0bed4';

	/**
	 * @var Ahrefs_Seo_Bugsnag
	 */
	private static $instance;

	public static function get() : Ahrefs_Seo_Bugsnag {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function create_client() : \Bugsnag\Client {
		$client = \Bugsnag\Client::make( $this->api_key );
		$client->setAppVersion( AHREFS_SEO_VERSION );
		$client->setReleaseStage( AHREFS_SEO_RELEASE );

		try {
			$client->registerCallback(
				function ( $report ) {
					$frames              = $report->getStacktrace()->getFrames();
					$found_in_stacktrace = false;
					foreach ( $frames as &$frame ) {
						if ( false !== strpos( $frame['file'], 'ahrefs-seo' ) && false === strpos( $frame['file'], 'ahrefs-seo' . DIRECTORY_SEPARATOR . 'vendor' ) ) {
							$found_in_stacktrace = true;
							break;
						}
					}

					if ( ! $found_in_stacktrace ) {
						return false;
					}
					$original_error = $report->getOriginalError();
					if ( is_array( $original_error ) && isset( $original_error['code'] ) && isset( $original_error['file'] ) && E_DEPRECATED === $original_error['code'] && strpos( $original_error['file'], 'vendor' . DIRECTORY_SEPARATOR . 'google' ) ) {
						return false;
					}
					$tokens  = null;
					$list    = null;
					$version = null;
					try {
						$tokens    = Ahrefs_Seo_Analytics::OPTION_TOKENS;
						$analytics = Ahrefs_Seo_Analytics::get();
						$list      = $analytics->load_gsc_accounts_list( true ); // cached value.
						$version   = class_exists( '\\GuzzleHttp\\Client' ) ? \GuzzleHttp\Client::VERSION : '';
					} catch ( \Exception $e ) {
						$report->setMetaData( [ 'error' => [ 'exception' => $e ] ] );
					} catch ( \Error $e ) {
						$report->setMetaData( [ 'error' => [ 'error' => $e ] ] );
					}
					/** @var \Bugsnag\Report $report */
					$report->setMetaData(
						[
							'info' => [
								'google-client'  => Google_Client::LIBVER,
								'guzzle-client'  => $version,
								'db-tables'      => Ahrefs_Seo::CURRENT_TABLE_VERSION,
								'content-rules'  => Ahrefs_Seo::CURRENT_CONTENT_RULES,
								'transient-time' => Ahrefs_Seo::transient_time(),
								'gsc-list'       => wp_json_encode( $list ), // cached value.
								'domain'         => Ahrefs_Seo::get_current_domain(),
								'url'            => home_url(),
								'wpurl'          => site_url(),
								'wordpress'      => get_bloginfo( 'version' ),
								'abspath'        => ABSPATH,
								'multisite'      => is_multisite() ? 'Yes' : 'No',
								'blog'           => get_current_blog_id(),
								'uid'            => get_current_user_id(),
								'ga-options'     => ! is_null( $tokens ) ? get_option( $tokens, '' ) : null,
								'thread'         => Ahrefs_Seo::thread_id(),
							],
						]
					);
					$report->setUser(
						[
							'id' => Ahrefs_Seo::get_current_domain(),
						]
					);
				}
			);
			$client->setStripPathRegex( sprintf( '/^%s[\\/\\\\]/', preg_quote( rtrim( ABSPATH, '\\/' ), '/' ) ) );
		} catch ( \Exception $e ) {
			$client->notifyException( $e );
		}
		return $client;
	}
}
