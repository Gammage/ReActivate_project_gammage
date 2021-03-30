<?php

namespace ahrefs\AhrefsSeo;

use InvalidArgumentException;
use Google_Service_Exception;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
/**
 * Worker_Keywords class.
 * Load Keywords from GSC, create suggested keywords using TF-IDF.
 * If current keyword's position found - save it. If not - it will be update later by Worker Position
 *
 * @since 0.7.3
 */
class Worker_Keywords extends Worker_GSC {

	const WHAT_TO_UPDATE = 'keywords';
	/**
	 * @var int Load up to (number) items in same request. Will do x2 requests to GSC.
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
	 * Update default keyword for posts if.
	 * Update only if post does not have assigned by GSC or user (manual) keywords.
	 *
	 * @param int[] $post_ids Post ID list.
	 *
	 * @return void
	 */
	public function update_posts_info( array $post_ids ) {
		if ( ! is_null( $this->snapshot_id ) ) {
			if ( is_null( $this->api ) || ! $this->api instanceof Ahrefs_Seo_Analytics ) {
				$this->api = Ahrefs_Seo_Analytics::get();
			}
			$keywords       = Ahrefs_Seo_Keywords::get( $this->api );
			$data_all_posts = $keywords->get_full_detail_for_posts( $this->snapshot_id, $post_ids, 10 );
			foreach ( $post_ids as $post_id ) {
				$suggested_keyword = null;
				$data              = isset( $data_all_posts[ $post_id ] ) ? $data_all_posts[ $post_id ] : null;
				if ( ! empty( $data['keywords'] ) && ! empty( $data['keywords']['result'] ) ) {
					// choose best position.
					usort(
						$data['keywords']['result'],
						function ( $a, $b ) {
							$pos_a = isset( $a['pos'] ) ? $a['pos'] : 10000;
							$pos_b = isset( $b['pos'] ) ? $b['pos'] : 10000;
							return $pos_a - $pos_b;
						}
					);
					$suggested_keyword = isset( $data['keywords']['result'][0] ) && isset( $data['keywords']['result'][0]['query'] ) ? $data['keywords']['result'][0]['query'] : null;
				}
				if ( is_null( $suggested_keyword ) && ! empty( $data['keywords2'] ) ) {
					$keyword = array_map(
						function ( $item ) {
							return $item['q'];
						},
						array_slice( $data['keywords2'], 0, 1 )
					);
					if ( count( $keyword ) ) {
						$suggested_keyword = $keyword[0];
					}
				}
				if ( ! is_null( $suggested_keyword ) && ! $keywords->post_keywords_is_approved( $this->snapshot_id, $post_id ) ) { // do not overwrite approved keyword!
					$keywords->post_keywords_set( $this->snapshot_id, $post_id, $suggested_keyword, null, false );
				} else { // no results, save this and do not call updates more.
					// just reset update flag.
					$keywords->post_keywords_set_updated( $this->snapshot_id, $post_id );
				}
			}
		} else {
			// no snapshot is set mean error.
			$this->has_rate_error = true;
		}
	}
}
