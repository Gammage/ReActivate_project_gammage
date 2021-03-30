<?php

namespace ahrefs\AhrefsSeo;

/**
 * Worker_Traffic class.
 * Load traffic details.
 *
 * @since 0.7.3
 */
class Worker_Backlinks extends Worker_Any {

	const API_NAME       = 'ahrefs';
	const WHAT_TO_UPDATE = 'backlinks';
	/**
	 * @var int Load up to (number) items in same request. Ahrefs API does not support bulk requests.
	 */
	protected $items_at_once = 1;
	/**
	 * Run update for items in list
	 *
	 * @param int[] $post_ids Post ID list.
	 * @return bool False if rate limit error received and need to do pause.
	 */
	protected function update_posts( array $post_ids ) {
		if ( is_null( $this->api ) || ! $this->api instanceof Ahrefs_Seo_Api ) {
			$this->api = Ahrefs_Seo_Api::get();
		}
		$this->update_posts_info( $post_ids );
		return ! $this->has_rate_error;
	}
	/**
	 * Update post with the info from Ahrefs
	 *
	 * @param int[] $post_ids
	 *
	 * @return void
	 */
	public function update_posts_info( array $post_ids ) {
		if ( is_null( $this->api ) || ! $this->api instanceof Ahrefs_Seo_Api ) {
			$this->api = Ahrefs_Seo_Api::get();
		}
		$api = $this->api;
		if ( $api->is_disconnected() ) {
			$message = 'Ahrefs account is not connected.';
			$this->set_backlinks_error( $post_ids, $message );
			Ahrefs_Seo_Errors::save_message( 'ahrefs', $message, 'error' );
			return;
		}
		$start_date = date( 'Y-m-d', (int) strtotime( sprintf( '- %d week', Ahrefs_Seo_Data_Content::get()->get_waiting_weeks() ) ) );
		$end_date   = date( 'Y-m-d', time() );
		foreach ( $post_ids as $post_id ) {
			$url = get_permalink( $post_id );
			if ( ! empty( $url ) ) {
				// query using page slug.
				$data = $url ? $api->get_backlinks_count_by_url( $url, $start_date, $end_date ) : 0;
				if ( ! is_null( $data ) ) {
					// update.
					$this->content_audit->update_backlinks_values( $post_id, $data );
				} else { // some error.
					$error = $api->get_last_error();
					$this->content_audit->update_backlinks_values( $post_id, -1, $error );
					Ahrefs_Seo_Errors::save_message( 'ahrefs', $error, 'error' );
				}
			} else {
				$this->content_audit->update_backlinks_values( $post_id, -1, 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.' );
			}
		}
	}
	/**
	 * Set error in DB for posts.
	 *
	 * @param int[]  $post_ids
	 * @param string $message
	 * @return void
	 */
	protected function set_backlinks_error( array $post_ids, $message ) {
		foreach ( $post_ids as $post_id ) {
			$this->content_audit->update_backlinks_values( (int) $post_id, -1, $message );
		}
	}
}
