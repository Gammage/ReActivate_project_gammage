<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

use \InvalidArgumentException as InvalidArgumentException;
use \Google_Service_Exception as Google_Service_Exception;
use \GuzzleHttp\Exception\ConnectException as GuzzleConnectException;

/**
 * Worker_GSC class.
 * Share same rate functions for everything, that query GSC.
 *
 * @since 0.7.3
 */
abstract class Worker_GSC extends Worker_Any {

	public const API_NAME = 'gsc';

	/** @var float Delay after successful request to API */
	protected $pause_after_success = 10;

	/**
	 * Fill answers with error message
	 *
	 * @param int[]  $post_ids
	 * @param string $error_message
	 * @return array Index is slug, value is ['error' => $error_message].
	 */
	protected function prepare_answer( ?array $post_ids, string $error_message ) : ?array {
		return is_null( $post_ids ) ? null : array_map(
			function( $slug ) use ( $error_message ) {
				return [ 'error' => $error_message ];
			},
			array_flip( $post_ids )
		);
	}

	/**
	 * Can not run now because of time restriction from API side
	 *
	 * @return bool True if on pause now.
	 */
	public function on_pause_now() : bool {
		$result = parent::on_pause_now();
		if ( $this->api instanceof Ahrefs_Seo_Analytics ) {
			if ( $this->api->is_gsc_paused() ) {
				$result = true; // if API in unavailable.
			} else {
				$this->api->set_gsc_paused( $result ); // set same status for API.
			}
		}
		return $result;
	}

	/**
	 * Callback for on rate error
	 *
	 * @param \Throwable          $e Error source.
	 * @param string[]|int[]|null $page_slugs_list
	 * @return void
	 */
	public function on_rate_error( \Throwable $e, ?array $page_slugs_list = [] ) : void {
		parent::on_rate_error( $e, $page_slugs_list );
		if ( $this->api instanceof Ahrefs_Seo_Analytics ) {
			$this->api->set_gsc_paused( true );
		}
	}
}
