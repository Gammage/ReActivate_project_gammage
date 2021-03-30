<?php

namespace ahrefs\AhrefsSeo;

use InvalidArgumentException;
use Google_Service_Exception;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
/**
 * Worker_Traffic class.
 * Load traffic details.
 *
 * @since 0.7.3
 */
class Worker_Traffic extends Worker_Any {

	const API_NAME       = 'ga';
	const WHAT_TO_UPDATE = 'traffic';
	/** @var float Delay after successful request to API */
	protected $pause_after_success = 2;
	/**
	 * @var int Load up to (number) items in same request.
	 */
	protected $items_at_once = 2;
	/**
	 * Run update for items in list
	 *
	 * @param int[] $post_ids Post ID list.
	 * @return bool False if rate limit error received and need to do pause.
	 */
	protected function update_posts( array $post_ids ) {
		$this->update_posts_info( $post_ids );
		return ! $this->has_rate_error;
	}
	/**
	 * Update posts with the traffic info from Analytics.
	 *
	 * @param int[] $post_ids Post ID list.
	 */
	public function update_posts_info( array $post_ids ) {
		$page_id_to_slug = [];
		$skipped_results = []; // errors: [ post_id => ['error' => message..]].
		$traffic_raw     = [];
		if ( is_null( $this->api ) || ! $this->api instanceof Ahrefs_Seo_Analytics ) {
			$this->api = Ahrefs_Seo_Analytics::get();
		}
		if ( $this->api->is_ua_set() ) {
			foreach ( $post_ids as $post_id ) {
				$url = get_permalink( $post_id );
				if ( ! empty( $url ) ) {
					$page_id_to_slug[ $post_id ] = str_replace( home_url(), '', (string) $url );
				} else {
					$message                     = sprintf( 'Post %d cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.', $post_id );
					$skipped_results[ $post_id ] = [ 'error' => $message ];
					Ahrefs_Seo_Errors::save_message( 'WordPress', $message, 'notice' );
				}
			}
			$traffic_raw = $this->load_traffic( $page_id_to_slug );
		} else {
			$message         = 'Analytics account is not connected.';
			$skipped_results = $this->prepare_answer( $post_ids, $message );
			Ahrefs_Seo_Errors::save_message( 'google', $message, 'error' );
		}
		$traffic_results = ! empty( $traffic_raw ) || ! empty( $skipped_results ) ? $this->calculate_traffic( array_merge( isset( $skipped_results ) ? $skipped_results : [], isset( $traffic_raw ) ? $traffic_raw : [] ), $page_id_to_slug ) : [];
		$this->update_traffic_values( $traffic_results );
	}
	/**
	 * Fill answers with error message
	 *
	 * @param int[]  $post_ids
	 * @param string $error_message
	 * @return array Index is slug, value is ['error' => $error_message].
	 */
	protected function prepare_answer( array $post_ids = null, $error_message ) {
		return is_null( $post_ids ) ? null : array_map(
			function ( $slug ) use ( $error_message ) {
				return [ 'error' => $error_message ];
			},
			array_flip( $post_ids )
		);
	}
	/**
	 * Load traffic info from API.
	 * Set has_rate_error if rate error received.
	 *
	 * @param array<int, string> $page_id_to_slug Associative array, page_id => url.
	 * @return array<string, array<string, mixed>>|null Results,
	 * associative array page_slug => [traffic details, as Google API returned],
	 * null on error.
	 */
	public function load_traffic( array $page_id_to_slug ) {
		$this->has_rate_error = false;
		$start_date           = date( 'Y-m-d', (int) strtotime( sprintf( '- %d week', Ahrefs_Seo_Data_Content::get()->get_waiting_weeks() ) ) );
		$end_date             = date( 'Y-m-d', time() );
		if ( is_null( $this->api ) || ! $this->api instanceof Ahrefs_Seo_Analytics ) {
			$this->api = Ahrefs_Seo_Analytics::get();
		}
		return $this->api->get_visitors_by_page( array_values( $page_id_to_slug ), $start_date, $end_date ); // @phpstan-ignore-line
	}
	/**
	 * Update is noindex value using loaded pages.
	 *
	 * @param array<string, array<string, mixed>> $traffic_details Associative array, page_slug => traffic results array.
	 * @param array<int, string>                  $post_id_to_slug Associative array, page_id => page slug.
	 * @return array<int, array<string, mixed>> Associave array post_id => array traffic results
	 */
	public function calculate_traffic( array $traffic_details, array $post_id_to_slug ) {
		$results         = [];
		$slug_to_post_id = array_flip( $post_id_to_slug );
		foreach ( $traffic_details as $slug => $values ) {
			$post_id = isset( $slug_to_post_id[ $slug ] ) ? $slug_to_post_id[ $slug ] : null;
			if ( ! is_null( $post_id ) ) {
				// days count using post publish date.
				$days_count    = $this->content_audit->get_time_period_for_post( (int) $post_id );
				$total         = -10;
				$total_month   = -10;
				$organic       = -10;
				$organic_month = -10;
				$error         = isset( $values['error'] ) ? $values['error'] : null;
				if ( empty( $error ) ) {
					$total         = isset( $values['total'] ) ? $values['total'] : 0;
					$organic       = isset( $values['Organic Search'] ) ? $values['Organic Search'] : 0;
					$total_month   = intval( round( $total / $days_count * 30 ) );
					$organic_month = intval( round( $organic / $days_count * 30 ) );
				}
				$results[ (int) $post_id ] = compact( 'total', 'organic', 'total_month', 'organic_month', 'error' );
			}
		}
		return $results;
	}
	/**
	 * Update traffic values
	 *
	 * @param array<int, array<string, mixed>> $results key is post ID, value is array [total, organic, total_month, organic_month, error].
	 * @return void
	 */
	protected function update_traffic_values( array $results ) {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s: %s', __METHOD__, wp_json_encode( $results ) ) );
		foreach ( $results as $post_id => $values ) {
			try {
				$this->content_audit->update_traffic_values( (int) $post_id, $values['total'], $values['organic'], $values['total_month'], $values['organic_month'], isset( $values['error'] ) ? $values['error'] : null );
			} catch ( \Error $e ) {
				$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on update_traffic' );
			} catch ( \Exception $e ) {
				Ahrefs_Seo::notify( $e, 'Unexpected error on update_traffic' );
				Ahrefs_Seo_Errors::save_message( 'WordPress', "Error while saving traffic result for post {$post_id}." );
			}
		}
	}
}
