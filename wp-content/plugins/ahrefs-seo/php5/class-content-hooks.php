<?php

namespace ahrefs\AhrefsSeo;

/**
 * Implement hooks for Content audit.
 */
class Content_Hooks {

	/** @var Content_Hooks */
	private static $instance;
	/**
	 * Return the instance
	 *
	 * @return Content_Hooks
	 */
	public static function get() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'trashed_post', [ $this, 'exclude_trashed_post_from_audit' ] );
		add_action( 'deleted_post', [ $this, 'exclude_deleted_post_from_audit' ] );
		add_action( 'untrashed_post', [ $this, 'add_untrashed_post_post_to_audit' ] );
		add_action( Ahrefs_Seo::ACTION_TOKEN_CHANGED, [ $this, 'clean_backlinks_in_new_snapshot' ] );
		add_action( Ahrefs_Seo::ACTION_DOMAIN_CHANGED, [ $this, 'clean_backlinks_in_new_snapshot' ] );
	}
	/**
	 * Action hook. Exclude post from Content Audit active list when post or page trashed.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function exclude_trashed_post_from_audit( $post_id ) {
		// Note: Callback, do not use parameter types.
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && ( 'post' === $post->post_type || 'page' === $post->post_type ) ) {
			Ahrefs_Seo_Data_Content::get()->posts_exclude( [ $post->ID ] );
		}
	}
	/**
	 * Action hook. Exclude post from Content Audit active list when post or page trashed.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function exclude_deleted_post_from_audit( $post_id ) {
		// Note: Callback, do not use parameter types.
		Ahrefs_Seo_Data_Content::get()->delete_post_details( (int) $post_id );
	}
	/**
	 * @param int $post_id
	 * @return void
	 */
	public function add_untrashed_post_post_to_audit( $post_id ) {
		// Note: Callback, do not use parameter types.
		Ahrefs_Seo_Data_Content::get()->restore_post_as_added_since_last( (int) $post_id );
	}
	/**
	 * Clean backlinks details in new snapshot, if exists.
	 *
	 * @return void
	 */
	public function clean_backlinks_in_new_snapshot() {
		( new Snapshot() )->reset_backlinks_for_new_snapshot();
	}
}
