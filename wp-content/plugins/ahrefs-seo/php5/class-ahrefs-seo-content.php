<?php

namespace ahrefs\AhrefsSeo;

/**
 * Base class for content, implement options get and save.
 */
class Ahrefs_Seo_Content {

	const OPTION_DISABLED_PAGES = 'ahrefs-seo-content-disabled-pages';
	const OPTION_DISABLED_POSTS = 'ahrefs-seo-content-disabled-posts';
	const OPTION_POSTS_CAT      = 'ahrefs-seo-content-posts-cat';
	const OPTION_PAGES_CAT      = 'ahrefs-seo-content-pages-cat';
	const OPTION_WAITING_WEEKS  = 'ahrefs-seo-content-waiting-weeks';
	const DEFAULT_WAITING_WEEKS = 12;
	/**
	 * Get waiting time, before we analyze a post.
	 *
	 * @return int Number of weeks.
	 */
	public function get_waiting_weeks() {
		$result = absint( get_option( self::OPTION_WAITING_WEEKS, self::DEFAULT_WAITING_WEEKS ) );
		if ( $result < 1 ) {
			$result = 1;
		}
		return $result;
	}
	/**
	 * Get checked pages using option value
	 *
	 * @return string[]|null
	 */
	public function get_pages_checked() {
		$result = get_option( self::OPTION_PAGES_CAT, null );
		return is_array( $result ) ? $result : null;
	}
	/**
	 * Get checked post categories from option
	 *
	 * @return string[]|null
	 */
	public function get_posts_categories_checked() {
		$result = get_option( self::OPTION_POSTS_CAT, null );
		return is_array( $result ) ? $result : null;
	}
	public function is_disabled_for_pages() {
		$value = get_option( self::OPTION_DISABLED_PAGES );
		return ! empty( $value );
	}
	public function is_disabled_for_posts() {
		$value = get_option( self::OPTION_DISABLED_POSTS );
		return ! empty( $value );
	}
	protected function set_waiting_weeks( $waiting_weeks ) {
		update_option( self::OPTION_WAITING_WEEKS, max( 1, $waiting_weeks ) );
	}
	protected function set_disabled_for( $pages_disabled, $posts_disabled ) {
		update_option( self::OPTION_DISABLED_PAGES, $pages_disabled );
		update_option( self::OPTION_DISABLED_POSTS, $posts_disabled );
	}
	/**
	 * Set checked pages list
	 *
	 * @param string[] $values
	 * @return void
	 */
	protected function set_pages_checked( array $values = null ) {
		if ( ! is_null( $values ) ) {
			update_option( self::OPTION_PAGES_CAT, $values );
		} else {
			delete_option( self::OPTION_PAGES_CAT );
		}
	}
	/**
	 * @param string[] $values
	 * @return void
	 */
	protected function set_posts_categories_checked( array $values ) {
		update_option( self::OPTION_POSTS_CAT, $values );
	}
	/**
	 * Add pages to checked pages list
	 *
	 * @param string[] $post_ids
	 * @return void
	 */
	protected function pages_add_to_checked( array $post_ids ) {
		$enabled = ! empty( $this->get_pages_checked() ) ? $this->get_pages_checked() : [];
		$enabled = array_unique( array_merge( $enabled, $post_ids ) );
		$this->set_pages_checked( $enabled );
		if ( count( $enabled ) && $this->is_disabled_for_pages() ) { // enable.
			$this->set_disabled_for( false, $this->is_disabled_for_posts() );
		}
	}
	/**
	 * Remove pages from checked pages list
	 *
	 * @param string[] $post_ids
	 * @return void
	 */
	protected function pages_remove_from_checked( array $post_ids ) {
		$enabled = ! empty( $this->get_pages_checked() ) ? $this->get_pages_checked() : [];
		$enabled = array_unique( array_diff( $enabled, $post_ids ) );
		$this->set_pages_checked( $enabled );
		if ( ! count( $enabled ) && ! $this->is_disabled_for_pages() ) { // disable.
			$this->set_disabled_for( true, $this->is_disabled_for_posts() );
		}
	}
	/**
	 * Clear internal cached data
	 */
	public function clear_cache() {
		delete_option( self::OPTION_DISABLED_PAGES );
		delete_option( self::OPTION_DISABLED_POSTS );
		delete_option( self::OPTION_PAGES_CAT );
		delete_option( self::OPTION_POSTS_CAT );
	}
}
