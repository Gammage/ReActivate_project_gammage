<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

use \InvalidArgumentException as InvalidArgumentException;
use \Google_Service_Exception as Google_Service_Exception;
use \GuzzleHttp\Exception\ConnectException as GuzzleConnectException;

/**
 * Worker_Position class.
 * Load position from GSC.
 *
 * @since 0.7.3
 */
class Worker_Position extends Worker_GSC {

	protected const WHAT_TO_UPDATE = 'position';

	/**
	 * @var int Load up to (number) items in same request.
	 */
	protected $items_at_once = 3;

	/**
	 * Run update for items in list
	 *
	 * @param int[] $post_ids Post ID list.
	 * @return bool False if rate limit error received and need to do pause.
	 */
	protected function update_posts( array $post_ids ) : bool {
		$this->update_posts_info( $post_ids );

		return ! $this->has_rate_error;
	}
	/**
	 * Update post with position.
	 * Use time range from keywords.
	 *
	 * @param int[] $post_ids
	 * @param bool  $fast_update Do not load all details, but load keyword position only.
	 * @return void
	 */
	public function update_posts_info( array $post_ids, bool $fast_update = false ) : void {
		if ( ! is_null( $this->snapshot_id ) ) {
			$keywords   = Ahrefs_Seo_Keywords::get();
			$start_date = '';
			$end_date   = '';
			$error      = '';
			$keywords->get_time_period( $start_date, $end_date );

			$post_with_keyword = []; // [ post_id => keyword ].
			$url_with_keyword  = []; // [ url => keyword ].
			$post_to_url       = [];
			$skipped_items     = []; // post ID list.
			$by_post_id        = true;
			foreach ( $post_ids as $post_id ) {
				$current_keyword = $keywords->post_keyword_get( $this->snapshot_id, $post_id );
				$url             = apply_filters( 'ahrefs_seo_search_traffic_url', get_permalink( (int) $post_id ) );
				if ( ! empty( $current_keyword ) && ! empty( $url ) ) {
					$post_with_keyword[ $post_id ] = $current_keyword;
					$url_with_keyword[ "$url" ]    = $current_keyword;
					$post_to_url[ $post_id ]       = $url;
				} else {
					$skipped_items[] = $post_id;
				}
			}
			if ( ! empty( $post_with_keyword ) ) {
				// Note: do not use cached details, if we need to update positions here - we must query GSC API.
				// query using page slug.
				if ( ! $fast_update ) {
					$data_all = $keywords->load_position_value( $this->snapshot_id, $post_with_keyword );
				} else {
					$data_all   = $keywords->load_position_value_fast( $this->snapshot_id, $url_with_keyword );
					$by_post_id = false;
				}
				$results = [];
				foreach ( $post_ids as $post_id ) {
					if ( isset( $post_to_url[ $post_id ] ) ) { // otherwise it is a skipped item or API returned no results for it.
						$url = $post_to_url[ $post_id ];
						if ( $by_post_id ) {
							$row = is_array( $data_all ) ? ( $data_all[ $post_id ] ?? [] ) : [];
						} else {
							$row = is_array( $data_all ) ? ( $data_all[ $url ] ?? [] ) : [];
						}
						$data                = $row['pos'] ?? null;
						$error               = isset( $row['error'] ) && ( $row['error'] instanceof \Exception ) ? $row['error']->getMessage() : null;
						$results[ $post_id ] = [ $data, $error ];
						if ( ! is_null( $data ) ) {
							// update position.
							$this->content_audit->update_position_values( $post_id, $data );
							// update position in cached keywords data.
							$snapshot_id = $this->content_audit->get_snapshot_id();
							if ( ! is_null( $snapshot_id ) ) {
								$keywords->update_position_cache( $snapshot_id, $post_id, $row );
							}
						} elseif ( ! empty( $error ) ) { // some error.
							$this->content_audit->update_position_values( $post_id, -1, $error );
						} else {
							$this->content_audit->update_position_values( $post_id, Ahrefs_Seo_Data_Content::POSITION_MAX, sprintf( '[GSC] URL %s has no position info for keyword "%s"', $url, $post_with_keyword[ $post_id ] ) );
						}
						$this->set_pause( 2 * $this->pause_after_success ); // prevent rate error.
					} else {
						$skipped_items[] = $post_id;
					}
				}
				Ahrefs_Seo::breadcrumbs( 'update_post_info_position:' . wp_json_encode( $results ) );
			}

			// no keyword set.
			if ( ! empty( $skipped_items ) ) {
				$this->content_audit->post_positions_set_updated( array_unique( $skipped_items ) );
			}
		} else {
			$this->content_audit->post_positions_set_updated( $post_ids );
			// no snapshot is set mean error.
			$this->has_rate_error = true;
		}
	}
}
