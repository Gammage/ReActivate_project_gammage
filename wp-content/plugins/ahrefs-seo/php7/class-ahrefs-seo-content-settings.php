<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Base class for content settings, implement options get/set.
 */
class Ahrefs_Seo_Content_Settings extends Ahrefs_Seo_Content {

	/**
	 * Set options using global parameters from Wizard form
	 *
	 * @global $_REQUEST
	 * @param bool $return_only Do not save values, just return pages & categories as associative array.
	 * @return null|array null on incorrect request, associative array with pages and categories.
	 */
	public function set_options_from_request( bool $return_only = false ) : ?array {
		$result = [];
		// phpcs:disable WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected,WordPress.Security.NonceVerification.Recommended -- we already checked nonce.
		if ( ! empty( $_REQUEST['ahrefs_audit_options'] ) ) { // assume that some nonce is already checked before this call.
			$waiting_weeks  = isset( $_REQUEST['waiting_weeks'] ) ? absint( $_REQUEST['waiting_weeks'] ) : self::DEFAULT_WAITING_WEEKS; // int value.
			$posts          = isset( $_REQUEST['post_category'] ) && is_array( $_REQUEST['post_category'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['post_category'] ) ) : []; // int values.
			$pages          = isset( $_REQUEST['pages'] ) && is_array( $_REQUEST['pages'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['pages'] ) ) : []; // int values.
			$pages_disabled = empty( $_REQUEST['pages_enabled'] );
			$posts_disabled = empty( $_REQUEST['posts_enabled'] );

			// phpcs:enable WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected,WordPress.Security.NonceVerification.Recommended

			if ( ! $return_only ) {
				$this->set_waiting_weeks( $waiting_weeks );
				$this->set_disabled_for( $pages_disabled, $posts_disabled );

				$this->set_pages_checked( $pages );
				$this->set_posts_categories_checked( $posts );
			}
			if ( ! $pages_disabled ) {
				$result['pages'] = $pages;
			}
			if ( ! $posts_disabled ) {
				$result['categories'] = $posts;
			}

			return $result;
		}
		return null;
	}

	/**
	 * Return not more 11 published pages.
	 *
	 * @return array<int, string> Key is post_id, value is post_title.
	 */
	public function get_pages_list() : array {
		$result = [];
		/**
		* @var \WP_Post[] we do not ask for ids.
		*/
		$pages = get_posts(
			[
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'posts_per_page' => 100, // If the site has more than 10 pages, lets not show any child items.
				'orderby'        => 'title',
				'order'          => 'asc',
			]
		);
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$result[ $page->ID ] = $page->post_title;
			}
		}

		return $result;
	}
}
