<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Class for recommendations about relevant pages and internal links.
 */
class Ahrefs_Seo_Advisor {

	/**
	 * @var Ahrefs_Seo_Advisor
	 */
	private static $instance;

	/**
	 * @var Ahrefs_Seo_Keywords
	 */
	private $data_keywords;

	/**
	 * Return the instance
	 *
	 * @return Ahrefs_Seo_Advisor
	 */
	public static function get() : Ahrefs_Seo_Advisor {
		if ( empty( self::$instance ) ) {
			self::$instance = new self( Ahrefs_Seo_Keywords::get() );
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @param Ahrefs_Seo_Keywords $keywords
	 */
	public function __construct( Ahrefs_Seo_Keywords $keywords ) {
		$this->data_keywords = $keywords;
	}

	/**
	 * Get well performing relevant pages.
	 * Use "Well performing" folder.
	 *
	 * @param int $snapshot_id
	 * @param int $post_id Source post id.
	 * @param int $limit
	 * @return null|array<string> Array ( post_id => title ) or null.
	 */
	public function find_relevant_top_performing_pages( int $snapshot_id, int $post_id, int $limit = 20 ) : ?array {
		$result = [];
		$data   = $this->find_posts_with_same_keys( $snapshot_id, $post_id, [ Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING ], $limit );
		if ( ! empty( $data ) ) {
			$data = array_flip( $data );
			foreach ( $data as $post_id => &$value ) {
				$value = get_the_title( $post_id );
			}
			return $data;
		}
		return null;
	}

	/**
	 * Get low performing relevant pages.
	 * Use "Underperforming" and "Deadweight" folders.
	 *
	 * @param int $snapshot_id
	 * @param int $post_id Source post id.
	 * @param int $limit
	 *
	 * @return null|array<string> Array ( post_id => title ) or null.
	 */
	public function find_relevant_under_performing_pages( int $snapshot_id, int $post_id, int $limit = 20 ) : ?array {
		$result = [];
		$data   = $this->find_posts_with_same_keys(
			$snapshot_id,
			$post_id,
			[
				Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW,
				Ahrefs_Seo_Data_Content::ACTION4_MERGE,
				Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE,
				Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE,
				Ahrefs_Seo_Data_Content::ACTION4_DELETE,
			],
			$limit,
			false
		);
		if ( ! empty( $data ) ) {
			$data = array_flip( $data );
			foreach ( $data as $post_id => &$value ) {
				$value = get_the_title( $post_id );
			}
			return $data;
		}
		return null;
	}

	/**
	 * Find active pages with the same keyword.
	 *
	 * @param int $snapshot_id
	 * @param int $post_id     Search posts with same keyword as this.
	 * @param int $limit
	 * @return int[]|null Post id list if any or empty result.
	 */
	public function find_active_pages_with_same_keyword( int $snapshot_id, int $post_id, int $limit = 1000 ) : ?array {
		return $this->find_posts_with_same_keys(
			$snapshot_id,
			$post_id,
			[
				Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING,
				Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW,
				Ahrefs_Seo_Data_Content::ACTION4_MERGE,
				Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE,
				Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE,
				Ahrefs_Seo_Data_Content::ACTION4_DELETE,
				Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL, // also active status on last step of each audit.
			],
			$limit,
			false
		);
	}

	/**
	 * Has active pages (not from "Excluded" or "Not analyzed") with same keyword?
	 *
	 * @param int $snapshot_id
	 * @param int $post_id
	 * @return bool
	 */
	public function has_active_pages_with_same_keywords( int $snapshot_id, int $post_id ) : bool {
		$data = $this->find_active_pages_with_same_keyword( $snapshot_id, $post_id, 1 );
		return ! empty( $data );
	}

	/**
	 * Find posts with same keys.
	 * Do not include inactive items.
	 *
	 * @param int      $snapshot_id
	 * @param int      $post_id
	 * @param string[] $actions_filter List of Ahrefs_Seo_Data_Content::ACTION_xxx constants.
	 * @param int      $limit
	 * @param bool     $traffic_order_desc
	 *
	 * @return int[]|null array of post_id.
	 */
	private function find_posts_with_same_keys( int $snapshot_id, int $post_id, array $actions_filter = [], $limit = 100, bool $traffic_order_desc = true ) : ?array {
		global $wpdb;
		// post keywords.
		$key = Ahrefs_Seo_Keywords::get()->post_keyword_get( $snapshot_id, $post_id );
		if ( empty( $key ) ) {
			return null;
		}
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%d) (%d) (%s)', __METHOD__, $snapshot_id, $post_id, $key ) );

		$placeholders_actions = '';
		if ( ! empty( $actions_filter ) ) {
			$placeholders_actions = ' AND action IN ( ' . implode( ',', array_fill( 0, count( $actions_filter ), '%s' ) ) . ' )';
		}
		$sql   = $wpdb->prepare( "SELECT post_id, keyword, action, organic_month as traffic FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id <> %d $placeholders_actions AND inactive = 0 AND keyword = %s LIMIT %d", array_merge( [ $snapshot_id, $post_id ], $actions_filter, [ $key, $limit ] ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( empty( $items ) ) {
			return null;
		}

		$data = $items;
		usort(
			$data,
			function( $a, $b ) use ( $traffic_order_desc ) {
				// then order by social monthly traffic.
				return $traffic_order_desc ? ( intval( $b['traffic'] ) <=> intval( $a['traffic'] ) ) : ( intval( $a['traffic'] ) <=> intval( $b['traffic'] ) );
			}
		);

		return array_slice(
			array_map(
				function( $row ) {
					return intval( $row['post_id'] );
				},
				$data
			),
			0,
			$limit
		);
	}

	/**
	 * Find posts and menus which contain desired url
	 *
	 * @param int $post_id
	 * @param int $limit
	 * @return array<array<string, string>> Array of ('url'=>..., 'title'=>...).
	 */
	public function find_internal_links( int $post_id, int $limit = 1000 ) : array {
		global $wpdb;
		$result = [];
		$url    = get_permalink( $post_id );

		// search in posts, pages and other CPT.
		$search = '%' . $wpdb->esc_like( $url ) . '%';
		$pages  = $wpdb->get_results( $wpdb->prepare( "SELECT ID as id, post_title as title FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' LIMIT %d", $search, $limit ), ARRAY_A );

		if ( $pages ) {
			array_walk(
				$pages,
				function( $item, $key ) use ( &$result ) {
					$link = get_edit_post_link( $item['id'] );
					if ( is_string( $link ) ) {
						$result[] = [
							'url'   => $link,
							'title' => $item['title'],
						];
					}
				}
			);
		}
		// search in menus.
		$menus = wp_get_nav_menus();
		if ( $menus ) {
			/** @var \WP_Term $menu */
			foreach ( $menus as $menu ) {
				$menu_items = wp_get_nav_menu_items( $menu->term_id );

				$count = 0;
				foreach ( (array) $menu_items as $menu_item ) {
					if ( $menu_item instanceof \WP_Post && property_exists( $menu_item, 'url' ) && $url === $menu_item->url ) { // @phpstan-ignore-line -- This is not exactly WP_Post, but instance filled with 'url' and some other properties.
						$count++;
					}
				}
				if ( $count > 0 ) {
					$result[] = [
						'url'   => add_query_arg( 'menu', $menu->term_id, admin_url( 'nav-menus.php' ) ),
						'title' => $menu->name . sprintf( _n( ' (menu with %d link)', ' (menu with %d links)', $count ), $count ),
					];
				}
			}
		}
		return $result;
	}
}
