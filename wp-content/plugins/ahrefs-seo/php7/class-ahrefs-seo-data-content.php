<?php
declare(strict_types=1);
namespace ahrefs\AhrefsSeo;

/**
 * Base class for content, implement options get and save.
 *
 * Call update_processed_items() when content options changed for re-analyze existing items.
 */
class Ahrefs_Seo_Data_Content extends Ahrefs_Seo_Content {
	/*
	Statuses are converted to actions at the function status_to_action_clause().
	Actions are converted to statuses at the function data_get_count_by_status().
	Please update both if their mapping changed.
	*/
	const STATUS_NOT    = 'not';
	const STATUS_NEW    = 'new';
	const STATUS_LOW    = 'low';
	const STATUS_WELL   = 'well';
	const STATUS_MANUAL = 'manual';
	const STATUS_ERROR  = 'error';
	const STATUS_ALL    = '';

	// same as tabs at Content audit table.
	const STATUS4_ALL_ANALYZED     = '';
	const STATUS4_WELL_PERFORMING  = 'well-performing';
	const STATUS4_UNDER_PERFORMING = 'under-performing';
	const STATUS4_DEADWEIHGT       = 'deadweight';
	const STATUS4_EXCLUDED         = 'excluded';

	/*
	* Possible 'action' field values: new v4 suggestions.
	*/
	const ACTION4_ADDED_SINCE_LAST       = 'added_since_last'; // item added after the snapshot created.
	const ACTION4_NOINDEX_PAGE           = 'noindex';
	const ACTION4_MANUALLY_EXCLUDED      = 'manually_excluded'; // manually excluded by user.
	const ACTION4_OUT_OF_SCOPE           = 'out_of_scope'; // item was out of scope, when snapshot created.
	const ACTION4_NEWLY_PUBLISHED        = 'newly_published';
	const ACTION4_ERROR_ANALYZING        = 'error_analyzing';
	const ACTION4_DO_NOTHING             = 'do_nothing';
	const ACTION4_UPDATE_YELLOW          = 'update_yellow';
	const ACTION4_MERGE                  = 'merge';
	const ACTION4_EXCLUDE                = 'exclude';
	const ACTION4_UPDATE_ORANGE          = 'update_orange';
	const ACTION4_DELETE                 = 'delete';
	const ACTION4_ANALYZING              = 'analyzing'; // item is analyzing, later this status will updated with one of permanent statuses.
	const ACTION4_ANALYZING_INITIAL      = 'analyzing_initial'; // status to use just when new snapshot created.
	const ACTION4_OUT_OF_SCOPE_INITIAL   = 'out_of_scope_initial'; // status to use just when new snapshot created.
	const ACTION4_ANALYZING_FINAL        = 'analyzing_final'; // status to use when detect all inactive items.
	const ACTION4_OUT_OF_SCOPE_ANALYZING = 'out_of_scope_analyzing'; // status to use when detect all inactive items.

	const POSITION_MAX = 1000000;

	private const OPTION_LAST_AUDIT_TIME = 'ahrefs-seo-content-audit-last-time';
	/** @var Ahrefs_Seo_Data_Content */
	private static $instance;

	/** @var int|null */
	private static $snapshot_id = null;

	/**
	 * Return the instance
	 *
	 * $return Ahrefs_Seo_Data_Content
	 */
	public static function get() : Ahrefs_Seo_Data_Content {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set snapshot ID for all requests
	 *
	 * @param int $snapshot_id
	 */
	public static function snapshot_context_set( int $snapshot_id ) : void {
		self::$snapshot_id = $snapshot_id;
	}

	/**
	 * Get snapshot ID for all requests.
	 * This is "current" snapshot by default.
	 *
	 * @return int
	 */
	public static function snapshot_context_get() : int {
		if ( is_null( self::$snapshot_id ) ) {
			self::$snapshot_id = ( new Snapshot() )->get_current_snapshot_id();
		}
		return self::$snapshot_id;
	}

	/**
	 * Return post action
	 *
	 * @param int $post_id
	 * @return string Action or empty string.
	 */
	public function get_post_action( int $post_id ) : string {
		global $wpdb;
		$snapshot_id = self::snapshot_context_get();
		$result      = $wpdb->get_var( $wpdb->prepare( "SELECT action FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id = %d", $snapshot_id, $post_id ) );
		return $result ?? self::ACTION4_ADDED_SINCE_LAST;
	}

	/**
	 * Include post to analysis.
	 * Include in analysis can only be applied to items in the ‘Excluded’ folder.
	 * Will add post to content table (as ACTION4_ADDED_SINCE_LAST), if not exists.
	 *
	 * @param int[] $post_ids
	 * @return int[] Can not include these items.
	 */
	public function posts_include( array $post_ids ) : array {
		global $wpdb;
		// update currently displayed snapshot (either 'current' or 'new', if no current snapshot exists).
		$result = ( new Content_Audit_Current( $this->snapshot_context_get() ) )->audit_include_posts( $post_ids );
		if ( ( new Snapshot() )->has_current_and_new_snapshots() ) {
			// also update 'new' snapshot.
			( new Content_Audit() )->audit_include_posts( $post_ids );
		}

		$pages = [];
		foreach ( $post_ids as $post_id ) {
			if ( 'page' === get_post_type( $post_id ) ) {
				$pages[] = "$post_id";
			}
		}
		if ( count( $pages ) ) {
			$this->pages_add_to_checked( $pages );
		}
		return $result;
	}

	/**
	 * Exclude post from analysis.
	 * Exclude from analysis can be applied to items from ‘Well performing’, ‘Under performing’ & ‘Deadweight’.
	 * Will add post to content table (as ACTION4_ADDED_SINCE_LAST), if not exists.
	 *
	 * @param int[] $post_ids
	 * @return int[] Can not exclude these items.
	 */
	public function posts_exclude( array $post_ids ) : array {
		global $wpdb;
		// update currently displayed snapshot (either 'current' or 'new', if no current snapshot exists).
		$result = ( new Content_Audit_Current( $this->snapshot_context_get() ) )->audit_exclude_posts( $post_ids );
		if ( ( new Snapshot() )->has_current_and_new_snapshots() ) {
			// also update 'new' snapshot.
			( new Content_Audit() )->audit_exclude_posts( $post_ids );
		}
		$pages = [];
		foreach ( $post_ids as $post_id ) {
			if ( 'page' === get_post_type( $post_id ) ) {
				$pages[] = "$post_id";
			}
		}
		if ( count( $pages ) ) {
			$this->pages_remove_from_checked( $pages );
		}
		return $result;
	}

	/**
	 * Convert statuses to actions for the where part
	 *
	 * @param string   $status
	 * @param string[] $additional_where
	 * @return string
	 */
	private function status_to_action_clause( string $status, array $additional_where ) : string {
		global $wpdb;
		switch ( $status ) {
			case self::STATUS4_ALL_ANALYZED:
				return $wpdb->prepare( 'AND ( action = %s || action = %s  || action = %s  || action = %s  || action = %s  || action = %s || action = %s || action = %s || action = %s )', self::ACTION4_DO_NOTHING, self::ACTION4_UPDATE_YELLOW, self::ACTION4_MERGE, self::ACTION4_EXCLUDE, self::ACTION4_UPDATE_ORANGE, self::ACTION4_DELETE, self::ACTION4_ANALYZING, self::ACTION4_ANALYZING_INITIAL, self::ACTION4_ANALYZING_FINAL );
			case self::STATUS4_WELL_PERFORMING:
				return $wpdb->prepare( 'AND ( action = %s )', self::ACTION4_DO_NOTHING );
			case self::STATUS4_UNDER_PERFORMING:
				return $wpdb->prepare( 'AND ( action = %s || action = %s )', self::ACTION4_UPDATE_YELLOW, self::ACTION4_MERGE );
			case self::STATUS4_DEADWEIHGT:
				return $wpdb->prepare( 'AND ( action = %s || action = %s || action = %s )', self::ACTION4_EXCLUDE, self::ACTION4_UPDATE_ORANGE, self::ACTION4_DELETE );
			case self::STATUS4_EXCLUDED:
				$current_where = implode( ' ', $additional_where );
				// Note: need to include items, missing in content table: duplicate current where clause with empty snapshot_id and action.
				return $wpdb->prepare( "AND ( action = %s || action = %s || action = %s  || action = %s || action = %s || action = %s || action = %s || action = %s ) OR snapshot_id IS NULL $current_where AND action IS NULL", self::ACTION4_NOINDEX_PAGE, self::ACTION4_MANUALLY_EXCLUDED, self::ACTION4_OUT_OF_SCOPE, self::ACTION4_OUT_OF_SCOPE_INITIAL, self::ACTION4_OUT_OF_SCOPE_ANALYZING, self::ACTION4_NEWLY_PUBLISHED, self::ACTION4_ADDED_SINCE_LAST, self::ACTION4_ERROR_ANALYZING ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return '';
	}

	/**
	 * Create string for using in SQL WHERE using filters
	 *
	 * @param array<string, int|string|array> $filters May include filters with indexes: 'cat' category id, 'post_type' with values page or post, 'page_id' int value, 's' search string.
	 * @return string
	 */
	private function apply_filters_to_where( array $filters ) : string {
		global $wpdb;
		$additional_where = [];
		$category         = $filters['cat'];
		if ( ! empty( $category ) ) {
			$additional_where[] = $wpdb->prepare(
				"AND r.object_id = p.ID
				AND tt.term_taxonomy_id = r.term_taxonomy_id
				AND tt.taxonomy = 'category'
				AND tt.term_id = t.term_id
				AND t.term_id = %d",
				$category
			);
		}
		if ( ! empty( $filters['post_type'] ) ) {
			$additional_where[] = $wpdb->prepare(
				'AND p.post_type = %s',
				$filters['post_type']
			);
		}
		if ( ! empty( $filters['page_id'] ) ) {
			$additional_where[] = $wpdb->prepare(
				'AND p.ID = %d',
				$filters['page_id']
			);
		}
		if ( ! empty( $filters['author'] ) ) {
			$additional_where[] = $wpdb->prepare(
				'AND p.post_author = %d',
				$filters['author']
			);
		}
		if ( '' !== $filters['s'] ) {
			$search             = '%' . $wpdb->esc_like( $filters['s'] ) . '%';
			$additional_where[] = $wpdb->prepare(
				' AND p.post_title LIKE %s ',
				$search
			);
		}
		if ( isset( $filters['keywords'] ) && '' !== $filters['keywords'] ) {
			switch ( intval( $filters['keywords'] ) ) {
				case 2: // No keyword detected.
					$additional_where[] = 'AND ( c.is_approved_keyword = 0 OR c.is_approved_keyword IS NULL ) AND ( c.keyword = "" OR c.keyword IS NULL )';
					break;
				case 1: // Approved.
					$additional_where[] = 'AND c.is_approved_keyword = 1';
					break;
				case 0: // Suggested.
					$additional_where[] = 'AND c.is_approved_keyword = 0 AND c.keyword <> ""';
					break;
			}
		}
		return implode( ' ', $additional_where );
	}

	/**
	 * Create string for using in SQL FROM using filters
	 *
	 * @param array $filters May include filters with indexes: 'cat' category id, 'post_type' with values page or post, 'page_id' int value, 's' search string.
	 * @return string
	 */
	private function apply_filters_to_from( array $filters ) : string {
		global $wpdb;
		$additional_from = [];
		$category        = $filters['cat'];
		if ( ! empty( $category ) ) {
			$additional_from[] = ", {$wpdb->term_relationships} r,
			{$wpdb->term_taxonomy} tt,
			{$wpdb->terms} t ";
		}
		return implode( ' ', $additional_from );
	}

	/**
	 * Get content table items
	 *
	 * @param string                          $type Status, one of self::STATUS_xxx constants.
	 * @param string                          $date Date with Year and Month as 'YYYYMM'.
	 * @param array<string, int|string|array> $filters May include filters with indexes: 'cat' category id, 'post_type' with values page or post, 'page_id' int value, 's' search string.
	 * @param int                             $start
	 * @param int                             $per_page
	 * @param string                          $orderby
	 * @param string                          $order
	 * @return array<\stdClass>
	 */
	public function data_get_clear( string $type, string $date, array $filters, int $start = 0, int $per_page = 10, string $orderby = 'post_date', string $order = 'asc' ) : array {
		global $wpdb;
		$snapshot_id        = self::snapshot_context_get();
		$orderby            = sanitize_sql_orderby( "$orderby $order" );
		$additional_from    = [ $this->apply_filters_to_from( $filters ) ];
		$additional_where   = [ $this->apply_filters_to_where( $filters ) ];
		$additional_where[] = "AND p.post_status = 'publish'";
		$additional_where[] = "AND ( p.post_type IN ('post','page') )";
		$post_ids           = [];

		if ( ! empty( $date ) ) {
			$additional_where[] = $wpdb->prepare( ' AND concat(YEAR( post_date ), MONTH( post_date )) = %s ', $date );
		}
		// must be last call for additional where.
		$additional_where[] = $this->status_to_action_clause( $type, $additional_where );

		$additional_from  = implode( ' ', $additional_from );
		$additional_where = implode( ' ', $additional_where );

		$sql    = $wpdb->prepare( "SELECT p.ID as post_id, p.post_title as title, p.post_author as author, p.post_type as post_type, date(p.post_date) as created, total_month as 'total', organic_month as 'organic', backlinks, position, keyword, is_approved_keyword, action, UNIX_TIMESTAMP(c.updated) as 'ver' FROM {$wpdb->ahrefs_content} c RIGHT JOIN {$wpdb->posts} p ON p.ID = c.post_id $additional_from WHERE snapshot_id = %d $additional_where " . ( $orderby ? ' ORDER BY ' . $orderby : '' ) . ' LIMIT %d, %d', $snapshot_id, absint( $start ), absint( $per_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_results( $sql, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->append_categories( $result );

		return $result;
	}

	/**
	 * Return count of items
	 *
	 * @param string                    $type Status, one of self::STATUS_xxx constants.
	 * @param string                    $date Date with Year and Month as 'YYYYMM'.
	 * @param array<string, int|string> $filters May include filters with indexes: 'cat' category id, 'post_type' with values page or post, 'page_id' int value, 's' search string.
	 * @return int
	 */
	public function data_get_clear_count( string $type, string $date, array $filters ) : int {
		global $wpdb;
		$snapshot_id        = self::snapshot_context_get();
		$additional_from    = [ $this->apply_filters_to_from( $filters ) ];
		$additional_where   = [ $this->apply_filters_to_where( $filters ) ];
		$additional_where[] = "AND p.post_status = 'publish'";
		$additional_where[] = "AND ( p.post_type IN ('post','page') )";

		if ( ! empty( $date ) ) {
			$additional_where[] = $wpdb->prepare( 'AND concat(YEAR( post_date ), MONTH( post_date )) = %s ', $date );
		}
		// must be last call for additional where.
		$additional_where[] = $this->status_to_action_clause( $type, $additional_where );

		$additional_from  = implode( ' ', $additional_from );
		$additional_where = implode( ' ', $additional_where );

		$sql = $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} c RIGHT JOIN {$wpdb->posts} p ON p.ID = c.post_id $additional_from WHERE snapshot_id = %d $additional_where", $snapshot_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Append categories and keywords details to result.
	 *
	 * @param \stdClass[] $result Result from get_results at content audit table.
	 * @return void
	 */
	private function append_categories( array &$result ) : void {
		$post_ids = array_map(
			function( $row ) {
				return intval( $row->post_id );
			},
			$result
		);

		// add categories to found items.
		foreach ( $result as $key => &$item ) {
			$item->categories = []; // this index will have array with links to category, but we will handle it and use Categories select dropdown.
			$cats             = get_the_category( $item->post_id );
			foreach ( $cats as $cat ) {
				$item->categories[] = sprintf(
					'<a href="%s" class="ahrefs-cat-link">%s</a>',
					add_query_arg(
						[
							'page' => Ahrefs_Seo::SLUG_CONTENT,
							'cat'  => 'cat-' . $cat->term_id,
						],
						admin_url( 'admin.php' )
					),
					$cat->name
				);
			}
		}
	}

	/**
	 * Check and return updated items from given list.
	 *
	 * @param array<int, int> $items Associative array ( (int)post_id => (int)ver ).
	 * @return int[] Array of post_id with updates (ver value changed).
	 */
	public function get_updated_items( array $items ) : array {
		$snapshot_id = self::snapshot_context_get();
		return Ahrefs_Seo_Db_Helper::get_updated_items( 'ahrefs_content', 'post_id', $items, " snapshot_id = $snapshot_id " );
	}

	/**
	 * Get content table items using their post ids.
	 *
	 * @param int[] $post_ids
	 * @return array<\stdClass>
	 * @see data_get_clear()
	 */
	public function data_get_by_ids( array $post_ids ) : array {
		global $wpdb;
		$snapshot_id        = self::snapshot_context_get();
		$additional_where   = [];
		$additional_where[] = "AND p.post_status = 'publish'";
		$additional_where[] = "AND ( p.post_type IN ('post','page') )";

		$placeholder        = array_fill( 0, count( $post_ids ), '%d' );
		$additional_where[] = $wpdb->prepare( 'AND post_id in ( ' . implode( ', ', $placeholder ) . ')', $post_ids ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared
		$additional_where   = implode( ' ', $additional_where );

		$sql    = $wpdb->prepare( "SELECT p.ID as post_id, p.post_title as title, p.post_author as author, p.post_type as post_type, date(p.post_date) as created, total_month as 'total', organic_month as 'organic', backlinks, position, keyword, is_approved_keyword, action, UNIX_TIMESTAMP(c.updated) as 'ver' FROM {$wpdb->ahrefs_content} c RIGHT JOIN {$wpdb->posts} p ON p.ID = c.post_id WHERE c.snapshot_id = %d $additional_where LIMIT %d ", $snapshot_id, count( $post_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_results( $sql, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->append_categories( $result );

		return $result;
	}

	/**
	 * Return number of items by status, for published posts&pages only
	 *
	 * @return array<string, int> index is status, value is count.
	 */
	public function data_get_count_by_status() : array {
		$data           = self::data_get_count_by_action();
		$count_in_queue = ( $data[ self::ACTION4_ANALYZING ] ?? 0 ) + ( $data[ self::ACTION4_ANALYZING_INITIAL ] ?? 0 ) + ( $data[ self::ACTION4_ANALYZING_FINAL ] ?? 0 );

		$result                               = [
			self::STATUS4_WELL_PERFORMING  => ( $data[ self::ACTION4_DO_NOTHING ] ?? 0 ),
			self::STATUS4_UNDER_PERFORMING => ( $data[ self::ACTION4_UPDATE_YELLOW ] ?? 0 ) + ( $data[ self::ACTION4_MERGE ] ?? 0 ),
			self::STATUS4_DEADWEIHGT       => ( $data[ self::ACTION4_EXCLUDE ] ?? 0 ) + ( $data[ self::ACTION4_UPDATE_ORANGE ] ?? 0 ) + ( $data[ self::ACTION4_DELETE ] ?? 0 ),
			self::STATUS4_EXCLUDED         => ( $data[ self::ACTION4_NOINDEX_PAGE ] ?? 0 ) + ( $data[ self::ACTION4_MANUALLY_EXCLUDED ] ?? 0 ) + ( $data[ self::ACTION4_OUT_OF_SCOPE ] ?? 0 ) + ( $data[ self::ACTION4_OUT_OF_SCOPE_INITIAL ] ?? 0 ) + ( $data[ self::ACTION4_OUT_OF_SCOPE_ANALYZING ] ?? 0 ) + ( $data[ self::ACTION4_NEWLY_PUBLISHED ] ?? 0 ) + ( $data[ self::ACTION4_ADDED_SINCE_LAST ] ?? 0 ) + ( $data[ self::ACTION4_ERROR_ANALYZING ] ?? 0 ),
		];
		$result[ self::STATUS4_ALL_ANALYZED ] = $count_in_queue + $result[ self::STATUS4_WELL_PERFORMING ] + $result[ self::STATUS4_UNDER_PERFORMING ] + $result[ self::STATUS4_DEADWEIHGT ];
		return $result;
	}

	/**
	 * Return count of items by action for chart
	 *
	 * @return int[] Associative array [chart action => int].
	 */
	public static function get_statuses_for_charts() : array {
		$data = self::get()->data_get_count_by_status();
		return [
			Ahrefs_Seo_Charts::CHART_WELL_PERFORMING => $data[ self::STATUS4_WELL_PERFORMING ],
			Ahrefs_Seo_Charts::CHART_UNDERPERFORMING => $data[ self::STATUS4_UNDER_PERFORMING ],
			Ahrefs_Seo_Charts::CHART_DEADWEIGHT      => $data[ self::STATUS4_DEADWEIHGT ],
			Ahrefs_Seo_Charts::CHART_EXCLUDED        => $data[ self::STATUS4_EXCLUDED ],
		];
	}

	/**
	 * Return count by action for published posts&pages only.
	 * Static method.
	 *
	 * @return array<string, int> Key is action, value is count.
	 */
	public static function data_get_count_by_action() : array {
		global $wpdb;
		$result      = [];
		$snapshot_id = self::snapshot_context_get();
		$data        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.action, count(p.ID) as number
				FROM {$wpdb->ahrefs_content} c RIGHT JOIN {$wpdb->posts} p ON c.post_id = p.ID
				WHERE
				c.snapshot_id = %d AND p.post_status = 'publish' AND ( p.post_type IN ('post','page') )
				OR
				c.snapshot_id IS NULL AND p.post_status = 'publish' AND ( p.post_type IN ('post','page') )
				GROUP BY c.action",
				$snapshot_id
			),
			ARRAY_A
		);

		if ( is_array( $data ) && count( $data ) ) {
			foreach ( $data as $row ) {
				$current_action                     = $row['action'] ?? self::ACTION4_ADDED_SINCE_LAST; // Items never added (action is null) to content audit will have ACTION4_ADDED_SINCE_LAST.
				$result[ (string) $current_action ] = (int) $row['number'] + ( $result[ (string) $current_action ] ?? 0 );
			}
		}

		return $result;
	}

	/**
	 * Get estimated rows count for Content Audit update
	 *
	 * @param null|array<string, int[]|string[]> $estimate_pages_and_categories Associative array ['pages'=>pages id, 'categories'=>categories id] or null - use saved in content audit table values.
	 * @param null|bool                          $with_analytics_on Null - autodetect, is Google Analytics on or off now, true/false - use exactly value.
	 * @param bool                               $initial_setup Is this a wizard screen.
	 * @param int|null                           $waiting_time
	 * @return int
	 */
	public function get_estimate_rows( ?array $estimate_pages_and_categories = null, ?bool $with_analytics_on = null, $initial_setup = false, ?int $waiting_time = null ) : int {
		return 0;
	}

	/**
	 * Remove all post details from DB (content table only) for current snapshot and new snapshot.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function delete_post_details( int $post_id ) : void {
		global $wpdb;
		$snapshot_id = ( new Snapshot() )->get_current_snapshot_id();
		$wpdb->delete(
			$wpdb->ahrefs_content,
			[
				'snapshot_id' => $snapshot_id,
				'post_id'     => $post_id,
			],
			[ '%d' ]
		);
		( new Content_Audit() )->audit_delete_post_details( (int) $post_id );
	}

	/**
	 * Add restored post as ACTION4_ADDED_SINCE_LAST to current and new snapshot.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function restore_post_as_added_since_last( int $post_id ) : void {
		global $wpdb;
		$snapshot = new Snapshot();
		// update current snapshot.
		$snapshot->add_post_as_added_since_last( $this->snapshot_context_get(), $post_id );
		// update new snapshot, if exists.
		if ( $snapshot->has_current_and_new_snapshots() ) {
			$snapshot_id = $snapshot->get_new_snapshot_id();
			if ( ! is_null( $snapshot_id ) ) {
				$snapshot->add_post_as_added_since_last( $snapshot_id, $post_id );
			}
		}
	}

	/**
	 * Convert options from previous versions to current. Delete obsolete options.
	 *
	 * @param int $old_version
	 * @return void
	 */
	public function update_options( int $old_version ) : void {
		$old_options = [];
		if ( (int) $old_version < 4 ) {
			$waiting_time_months = get_option( 'ahrefs-seo-content-waiting-time', null );
			if ( ! is_null( $waiting_time_months ) ) {
				update_option( self::OPTION_WAITING_WEEKS, 4 * absint( $waiting_time_months ) );
			}
			$old_options = [
				'ahrefs-seo-content-count-visitors',
				'ahrefs-seo-content-count-organic',
				'ahrefs-seo-content-min-backlinks',
				'ahrefs-seo-content-waiting-time',
			];
		}
		if ( count( $old_options ) ) {
			array_walk(
				$old_options,
				function( string $option ) {
					if ( ! is_null( get_option( $option, null ) ) ) {
						delete_option( $option );
					}
				}
			);
		}
	}

	/**
	 * Return backlinks for post.
	 *
	 * @param int $post_id
	 * @return int|null
	 */
	public function content_get_backlinks_for_post( int $post_id ) : ?int {
		global $wpdb;
		$snapshot_id = $this->snapshot_context_get();
		$result      = $wpdb->get_var( $wpdb->prepare( "SELECT backlinks FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d", $post_id, $snapshot_id ) );
		return is_null( $result ) ? null : (int) $result;
	}

	/**
	 * Return string, when last content audit was completed
	 *
	 * @return string
	 */
	private function get_last_updated_time() : string {
		$time = $this->get_last_audit_time();
		if ( ! empty( $time ) ) {
			return 'Last update: ' . date( 'j M Y, D', (int) $time ); // 'Last update: 28 Sep 2020, Mon'.
		}
		return ( new Content_Audit() )->require_update() ? 'Update in progress' : '';
	}

	public function get_statictics() : array {
		$content_audit = new Content_Audit();
		$in_progress   = $content_audit->require_update();
		$percents      = $in_progress ? 100 - $content_audit->content_get_unprocessed_percent() : 100;

		return [
			'in_progress' => $in_progress,
			'percents'    => $percents,
			'last_time'   => $this->get_last_updated_time(),
		];
	}

	/**
	 * Approve keyword of post
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function keyword_approve( int $post_id ) : void {
		global $wpdb;
		$snapshot_id = self::snapshot_context_get();
		$wpdb->update(
			$wpdb->ahrefs_content,
			[ 'is_approved_keyword' => 1 ],
			[
				'snapshot_id' => $snapshot_id,
				'post_id'     => (int) $post_id,
			],
			[ '%d' ],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Is post keyword approved?
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function is_keyword_approved( int $post_id ) : bool {
		global $wpdb;
		$snapshot_id = self::snapshot_context_get();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT is_approved_keyword FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id = %d", $snapshot_id, $post_id ) );
	}

	/**
	 * Return last audit time
	 *
	 * @return int|null Null if no audit completed.
	 */
	public function get_last_audit_time() : ?int {
		$result = get_option( self::OPTION_LAST_AUDIT_TIME );
		return is_numeric( $result ) ? (int) $result : null;
	}

	/**
	 * Set last audit time
	 *
	 * @param int $time
	 * @return void
	 */
	public function set_last_audit_time( int $time ) : void {
		update_option( self::OPTION_LAST_AUDIT_TIME, $time );
	}

	/**
	 * Currently viewed snapshot is updating now.
	 *
	 * @return bool
	 */
	public function is_updating_now() : bool {
		$snapshot_id = $this->snapshot_context_get();
		$new_id      = ( new Snapshot() )->get_new_snapshot_id();

		return $snapshot_id === $new_id;
	}
}
