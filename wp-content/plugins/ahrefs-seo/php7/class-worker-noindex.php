<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Worker_Noindex class.
 * Load is noindex details.
 *
 * @since 0.7.3
 */
class Worker_Noindex extends Worker_Any {

	public const API_NAME          = 'noindex';
	protected const WHAT_TO_UPDATE = 'isnoindex';

	/**
	 * @var int Load up to 2 pages in same request.
	 */
	protected $items_at_once = 2;

	/** @var float Delay after successful request to API */
	protected $pause_after_success = 5;

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
	 * Update post with the is noindex.
	 *
	 * @param int[] $post_ids
	 *
	 * @return void
	 */
	public function update_posts_info( array $post_ids ) : void {
		$page_id_to_url_list = [];
		foreach ( $post_ids as $post_id ) {
			$page_id_to_url_list[ $post_id ] = (string) get_permalink( $post_id );
		}

		if ( is_null( $this->api ) || ! ( $this->api instanceof Ahrefs_Seo_Noindex ) ) {
			$this->api = new Ahrefs_Seo_Noindex();
		}

		$results = $this->api->is_noindex( $page_id_to_url_list );

		$this->update_noindex_values( $results );
	}

	protected function update_noindex_values( array $results ) : void {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s: %s', __METHOD__, wp_json_encode( $results ) ) );
		foreach ( $results as $post_id => $is_noindex ) {
			try {
				$this->content_audit->update_noindex_values( (int) $post_id, (int) $is_noindex );
			} catch ( \Error $e ) {
				$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on update_noindex' );
			} catch ( \Exception $e ) {
				Ahrefs_Seo::notify( $e, 'Unexpected error on update_noindex' );
				Ahrefs_Seo_Errors::save_message( 'noindex', "Error while saving 'is noindex' result for post {$post_id}.", 'notice' );
			}
		}
	}

}
