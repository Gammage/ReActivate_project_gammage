<?php

declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * What is a current snapshot?
 * - it is a latest snapshot with 'current' status if exists;
 * - otherwise it is any latest snapshot (can be 'old' or 'new');
 * - if no snapshot records found (maybe corrupted DB?): create and initialize new snapshot.
 *
 * Update from previous versions (without snapshots, when no snapshots table exists before):
 * - create new snapshot with ID = 1 and assign this snapshot ID to all existing content items.
 *
 * When new snapshod added:
 * - add all published items from selected post categories and/or pages with initial status ACTION4_ANALYZING_INITIAL;
 * - add the rest of published items with initial out of scope status ACTION4_OUT_OF_SCOPE_INITIAL.
 *
 * Then run content audit for newly created snapshot.
 *
 * Finally, when all items updated:
 * - calculate traffic median;
 * - update status of snapshot from 'new' to 'current' (and previous 'current' to 'old').
 *
 * How to determine, do we need to update items or not?
 * - if we has snapshot with 'new' status - then we need to run update. Otherwise - no update needed.
 */

/**
 * Snapshot class.
 */
class Snapshot {

	private const TRANSIENT_CREATE_NEW = 'ahrefs_seo_snapshot_new_create';
	private const CACHE_GROUP          = 'ahrefs_seo_snapshot';

	const STATUS_NEW             = 'new';
	private const STATUS_CURRENT = 'current';
	private const STATUS_OLD     = 'old';


	/**
	 * Snapshot to show in UI, prefer 'current' status.
	 *
	 * @var int|null
	 */
	private $current_snapshot_id = null;

	/**
	 * Get current snapshot id.
	 * Create new snapshot if no snapshots available.
	 *
	 * @return int Snapshot ID.
	 */
	public function get_current_snapshot_id() : int {
		global $wpdb;
		if ( is_null( $this->current_snapshot_id ) ) {
			// get current snapshot.
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_id FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_status = %s ORDER BY snapshot_id DESC LIMIT 1", self::STATUS_CURRENT ) );
			if ( is_null( $result ) ) { // try to get new (incompleted) or old snapshot.
				$result = $wpdb->get_var( "SELECT snapshot_id FROM {$wpdb->ahrefs_snapshots} ORDER BY snapshot_id DESC LIMIT 1" );
			}
			if ( is_null( $result ) ) { // try to repair last snapshot.
				$result = $this->try_to_repair_last_snapshot();
			}
			if ( is_null( $result ) ) { // create new snapshot.
				$result = $this->create_new_snapshot();
			}
			$this->current_snapshot_id = (int) $result;
		}
		return $this->current_snapshot_id;
	}

	/**
	 * Try to repair snapshots. Search max snapshot_id from content table and create new snapshot with this ID.
	 *
	 * @return int|null Snapshot ID or null if nothing found.
	 */
	private function try_to_repair_last_snapshot() : ?int {
		global $wpdb;
		$id = $wpdb->get_var( "SELECT MAX(snapshot_id) FROM {$wpdb->ahrefs_snapshots}" );
		if ( ! is_null( $id ) && intval( $id ) > 0 ) {
			$id = intval( $id );
			$wpdb->insert(
				$wpdb->ahrefs_snapshots,
				[
					'snapshot_id'     => $id,
					'snapshot_status' => self::STATUS_NEW,
				]
			);
			return ! empty( $wpdb->insert_id ) ? $wpdb->insert_id : null;
		}
		return null;
	}


	/**
	 * Create new snapshot, fill the content table with items.
	 * Note: return existing new snapshot if it exists.
	 *
	 * @param bool $is_scheduled_audit Is sheduled audit, false - manually started.
	 * @return int|null Newly or already created Snapshot ID or null if error.
	 */
	public function create_new_snapshot( bool $is_scheduled_audit = false ) : ?int {
		global $wpdb;
		while ( get_transient( $this::TRANSIENT_CREATE_NEW ) ) {
			$snapshot_id = $this->get_new_snapshot_id();
			if ( ! is_null( $snapshot_id ) ) {
				return $snapshot_id;
			}
			Ahrefs_Seo::usleep( 50000 );
		}
		$snapshot_id = $this->get_new_snapshot_id();
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ' new snapshot exists? ' . ( is_null( $snapshot_id ) ? 'NULL' : $snapshot_id ) );
		if ( ! get_transient( $this::TRANSIENT_CREATE_NEW ) ) {
			set_transient( $this::TRANSIENT_CREATE_NEW, true, 10 );
			if ( is_null( $snapshot_id ) ) {
				$wpdb->insert(
					$wpdb->ahrefs_snapshots,
					[
						'snapshot_status' => self::STATUS_NEW,
						'time_start'      => current_time( 'mysql' ),
						'snapshot_type'   => $is_scheduled_audit ? 'scheduled' : 'manual',
					],
					[ '%s', '%d', '%s' ]
				);
				$snapshot_id = $wpdb->insert_id;
				if ( ! empty( $snapshot_id ) ) {
					wp_cache_delete( 'new_id', $this::CACHE_GROUP );
					// fill the content table with new details.
					$this->fill_content_table( $snapshot_id );

					Ahrefs_Seo_Cron::get()->start_tasks_content();// run cron content audit updates.
				} else {
					$error = 'New snapshot is empty';
					Ahrefs_Seo_Errors::save_message( 'general', "Can not start new content audit. $error", 'error' );
					Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( $error ) );
				}
			}
			delete_transient( $this::TRANSIENT_CREATE_NEW );

			Content_Audit::audit_clean_pause(); // clean any previous pause and allow content audit run.
		}
		return $snapshot_id;
	}

	/**
	 * Get new snapshot ID, if exists.
	 *
	 * @return null|int 'new' snapshot ID if exists or null.
	 */
	public function get_new_snapshot_id() : ?int {
		global $wpdb;
		$result = wp_cache_get( 'new_id', $this::CACHE_GROUP );
		if ( ! is_null( $result ) && ! is_int( $result ) ) {
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_id FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_status = %s LIMIT 1", self::STATUS_NEW ) );
			if ( is_null( $result ) && preg_match( "/Table.*?{$wpdb->ahrefs_snapshots}.*?doesn't exist/i", $wpdb->last_error ) ) {
				$last_error = $wpdb->last_error;
				// recreate tables.
				$success = Ahrefs_Seo_Db::create_table( 1 );
				Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( sprintf( 'Recreate tables as snapshots table non exists: %s on [%s] [%s]', ( $success ? 'success' : 'ERROR' ), $last_error, $wpdb->last_error ) ), 'Table not exists' );
				$result = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_id FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_status = %s LIMIT 1", self::STATUS_NEW ) );
			}
			$result = ! is_null( $result ) ? (int) $result : null;
			wp_cache_set( 'new_id', $result, $this::CACHE_GROUP, HOUR_IN_SECONDS );
		}
		return $result;
	}

	/**
	 * Fill contents table using initial settings (posts and pages) from wizard.
	 * Add posts using categories and pages as 'analyzing_initial'.
	 * Add other posts and pages as 'out_of_scope_initial'.
	 *
	 * @param int $new_snapshot_id
	 * @return void
	 */
	protected function fill_content_table( int $new_snapshot_id ) : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$content  = Ahrefs_Seo_Data_Content::get();
		$posts_on = ! $content->is_disabled_for_posts();
		$pages_on = ! $content->is_disabled_for_pages();

		// 1. add pages using options.
		if ( $pages_on ) {
			$params   = [
				'post_type'           => 'page',
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
			];
			$page_ids = $content->get_pages_checked();
			if ( ! empty( $page_ids ) ) {
				// include pages from $page_ids array only.
				$params['post__in'] = $page_ids;
				$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL );
			}

			// add other pages as out_of_scope_initial.
			unset( $params['post__in'] );
			if ( ! empty( $page_ids ) ) {
				$params['post__not_in'] = $page_ids;
			}
			$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL );
		} else {
			// add all pages as out_of_scope_initial.
			$params = [
				'post_type'           => 'page',
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
			];
			$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL );
		}

		// 2. add posts using options.
		if ( $posts_on ) {
			// array of id categories.
			$categories = $content->get_posts_categories_checked() ?? [];
			$params     = [
				'post_type'   => 'post',
				'post_status' => 'publish',
			];
			if ( ! empty( $categories ) ) {
				$params['cat'] = array_map( 'intval', $categories );
				$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL );
			}

			// add other posts as out_of_scope_initial.
			if ( ! empty( $params['cat'] ) ) {
				$params['category__not_in'] = array_map( 'intval', $categories );
			}
			unset( $params['cat'] );
			$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL );
		} else {
			// add all posts as out_of_scope_initial.
			$params = [
				'post_type'   => 'post',
				'post_status' => 'publish',
			];
			$this->add_items_by_clause( $new_snapshot_id, $params, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL );
		}
	}

	/**
	 * Add new posts/pages to Content table using given parameters fot get_posts() search call
	 *
	 * @param int                  $snapshot_id
	 * @param array<string, mixed> $params Parameters array for get_posts() call.
	 * @param string               $action
	 * @return int[] List of post ID.
	 */
	private function add_items_by_clause( int $snapshot_id, array $params, string $action ) : array {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		global $wpdb;
		$results                  = [];
		$paged                    = 1;
		$limit                    = 100;
		$params['orderby']        = 'date'; // add newest posts at the begin.
		$params['order']          = 'DESC';
		$params['fields']         = 'ids';
		$params['posts_per_page'] = $limit;
		do {
			$params['paged'] = $paged++;
			/** @var int[] we query for posts id. */
			$data = get_posts( $params );
			if ( ! empty( $data ) ) {
				$query         = "INSERT INTO {$wpdb->ahrefs_content} ( snapshot_id, post_id, action ) VALUES ";
				$values        = [];
				$place_holders = [];

				foreach ( $data as $post_id ) {
					array_push( $values, $snapshot_id, (int) $post_id, $action );
					$place_holders[] = '( %d, %d, %s )';
					$results[]       = (int) $post_id;
				}
				$sql = $query . implode( ', ', $place_holders ) . $wpdb->prepare( ' ON DUPLICATE KEY UPDATE action = %s, total = NULL, organic = NULL, total_month = NULL, organic_month = NULL, backlinks = NULL', $action );
				$wpdb->query( $wpdb->prepare( "$sql", $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		} while ( count( $data ) === $limit ); // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- we increment count of $data.
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ': ' . wp_json_encode( $results ) );
		return array_values( $results );
	}

	/**
	 * Reset keywords and positions if snapshot with 'new' status exists.
	 * Called after GSC account updated.
	 *
	 * @return void
	 */
	public function reset_keywords_and_position_for_new_snapshot() : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$new_snapshot_id = $this->get_new_snapshot_id();
		if ( ! is_null( $new_snapshot_id ) ) {
			( new Content_Db() )->reset_gsc_info( $new_snapshot_id );
		}
	}

	/**
	 * Reset GA details if snapshot with 'new' status exists.
	 * Called after GA account updated.
	 * Enable quick update for traffic.
	 *
	 * @return void
	 */
	public function reset_ga_for_new_snapshot() : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$new_snapshot_id = $this->get_new_snapshot_id();
		if ( ! is_null( $new_snapshot_id ) ) {
			( new Content_Db() )->reset_ga_info( $new_snapshot_id );
			$this->set_quick_update_allowed( $new_snapshot_id, true );
		}
	}

	/**
	 * Reset Ahrefs details if snapshot with 'new' status exists.
	 * Called after Ahrefs token updated.
	 *
	 * @return void
	 */
	public function reset_backlinks_for_new_snapshot() : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$new_snapshot_id = $this->get_new_snapshot_id();
		if ( ! is_null( $new_snapshot_id ) ) {
			( new Content_Db() )->reset_backlinks_info( $new_snapshot_id );
		}
	}

	/**
	 * Approve items from current and maybe new (if already exists) snapshots
	 *
	 * @param int[]|string[] $post_ids
	 * @return void
	 */
	public function analysis_approve_items( array $post_ids ) : void {
		$new_snapshot_id     = $this->get_new_snapshot_id();
		$current_snapshot_id = Ahrefs_Seo_Data_Content::snapshot_context_get();
		$data                = Ahrefs_Seo_Data_Content::get();
		$content_audit       = new Content_Audit();

		foreach ( $post_ids as $post_id ) {
			$post_id = intval( $post_id );
			$data->keyword_approve( $post_id );
			if ( ! is_null( $new_snapshot_id ) && $new_snapshot_id !== $current_snapshot_id ) {
				// approve the same keyword, as current snapshot has!
				$content_audit->keyword_approve( $post_id, Ahrefs_Seo_Keywords::get()->post_keyword_get( $current_snapshot_id, $post_id ) ?? '' );
			}
		}
	}

	/**
	 * Is quick update for traffic allowed
	 *
	 * @param int $snapshot_id
	 * @return bool
	 */
	public function get_quick_update_allowed( int $snapshot_id ) : bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT quick_update_traffic_allowed FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %d", $snapshot_id ) );
	}

	/**
	 * Set is quick update for traffic allowed
	 *
	 * @param int  $snapshot_id
	 * @param bool $is_allowed
	 * @return void
	 */
	public function set_quick_update_allowed( int $snapshot_id, bool $is_allowed ) : void {
		global $wpdb;
		$wpdb->update( $wpdb->ahrefs_snapshots, [ 'quick_update_traffic_allowed' => $is_allowed ? 1 : 0 ], [ 'snapshot_id' => $snapshot_id ], [ '%d' ], [ '%d' ] );
	}

	/**
	 * Return traffic median, if is set.
	 * Skip inactive items.
	 *
	 * @param int $snapshot_id
	 * @return float|null Traffic median or null.
	 */
	public function get_traffic_median( int $snapshot_id ) : ?float {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT traffic_median FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %d", $snapshot_id ) );
		return ! is_null( $result ) ? floatval( $result ) : null;
	}

	/**
	 * Set traffic median for snapshot
	 *
	 * @param int   $snapshot_id
	 * @param float $traffic_median
	 * @return void
	 */
	public function set_traffic_median( int $snapshot_id, float $traffic_median ) : void {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$wpdb->update( $wpdb->ahrefs_snapshots, [ 'traffic_median' => $traffic_median ], [ 'snapshot_id' => $snapshot_id ], [ '%f' ], [ '%d' ] );
	}

	/**
	 * Reset 'require_update'. For new snapshot: any 'current' became 'old', then update 'new' snapshot to 'current'.
	 * Called when content audit is ready.
	 *
	 * @param int $new_snapshot_id
	 * @return void
	 */
	public function set_finished( int $new_snapshot_id ) : void {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ': ' . wp_json_encode( $new_snapshot_id ) );

		$snapshot_id = $this->get_new_snapshot_id();
		$type        = $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_type FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %s", $snapshot_id ) );
		$type        = 'manual' === $type ? 'manual_finished' : 'scheduled_finished';
		// if snapshot from parameter is not 'new'.
		if ( $new_snapshot_id !== $snapshot_id ) {
			// set require_update = 1 for snapshot from parameter.
			$wpdb->update(
				$wpdb->ahrefs_snapshots,
				[
					'require_update' => 0,
					'snapshot_type'  => $type,
				],
				[ 'snapshot_id' => $new_snapshot_id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
			return; // no need for snapshot status updating.
		}

		// update 'new' snapshot.
		if ( ! is_null( $snapshot_id ) ) {
			// any 'current' snapshot became 'old'.
			$wpdb->update( $wpdb->ahrefs_snapshots, [ 'snapshot_status' => self::STATUS_OLD ], [ 'snapshot_status' => self::STATUS_CURRENT ], [ '%s' ], [ '%s' ] );
			$this->current_snapshot_id = null; // reset cached value.
			// this new snapshot became 'current' and not require update.
			$wpdb->update(
				$wpdb->ahrefs_snapshots,
				[
					'snapshot_status' => self::STATUS_CURRENT,
					'time_end'        => current_time( 'mysql' ),
					'require_update'  => 0,
				],
				[ 'snapshot_id' => $snapshot_id ],
				[ '%s', '%s', '%d' ],
				[ '%d' ]
			);
			wp_cache_delete( 'new_id', $this::CACHE_GROUP );
			Ahrefs_Seo_Data_Content::get()->set_last_audit_time( time() );
		}
		( new Content_Tips_Content() )->on_snapshot_created( $snapshot_id );
		( new Content_Tips_Popup() )->on_snapshot_created( $snapshot_id );
	}

	/**
	 * Get snapshot info
	 *
	 * @param int $snapshot_id
	 * @return array<string, string>
	 */
	public function get_snapshot_info( int $snapshot_id ) : array {
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare( "SELECT time_end FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %d", $snapshot_id ), ARRAY_A );
	}

	/**
	 * Is update required? Not used for new snapshots.
	 *
	 * @param int $snapshot_id
	 * @return bool
	 */
	public function is_require_update( int $snapshot_id ) : bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT require_update FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %d", $snapshot_id ) );
	}

	/**
	 * Set that snapshot require update.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return bool Success.
	 */
	public function set_require_update( int $snapshot_id ) : bool {
		global $wpdb;
		Ahrefs_Seo_Cron::get()->start_tasks_content();// run cron content audit updates.
		return false !== $wpdb->update( $wpdb->ahrefs_snapshots, [ 'require_update' => 1 ], [ 'snapshot_id' => $snapshot_id ], [ '%d' ], [ '%s' ] );
	}

	/**
	 * Both snapshots exists: current and new.
	 *
	 * @return bool
	 */
	public function has_current_and_new_snapshots() : bool {
		$current = $this->get_current_snapshot_id();
		$new     = $this->get_new_snapshot_id();
		return ! is_null( $new ) && $new !== $current;
	}

	/**
	 * Add post to snapshot as ACTION4_ADDED_SINCE_LAST.
	 *
	 * @param int $snapshot_id
	 * @param int $post_id
	 * @return void
	 */
	public function add_post_as_added_since_last( int $snapshot_id, int $post_id ) : void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ahrefs_content} ( snapshot_id, post_id, action ) VALUES ( %d, %d, %s ) ON DUPLICATE KEY UPDATE action = %s", $snapshot_id, $post_id, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST ) );
	}

	/**
	 * Is audit scheduled?
	 *
	 * @since 0.7.5
	 *
	 * @param int $snapshot_id
	 * @return bool True - sheduled audit, false - manually started audit or restarted audit of any type.
	 */
	public function is_scheduled_audit( int $snapshot_id ) : bool {
		global $wpdb;
		return 'scheduled' === $wpdb->get_var( $wpdb->prepare( "SELECT snapshot_type FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %s", $snapshot_id ) );
	}

	/**
	 * Get start time of audit.
	 *
	 * @since 0.7.5
	 *
	 * @param int $snapshot_id
	 * @return int Timestamp
	 */
	public function get_start_time( int $snapshot_id ) : int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT UNIX_TIMESTAMP(time_start) FROM {$wpdb->ahrefs_snapshots} WHERE snapshot_id = %s", $snapshot_id ) );
	}

	/**
	 * Update type of snapshot, restarted by user from scheduled.
	 *
	 * @since 0.7.5
	 *
	 * @param int $snapshot_id
	 * @return void
	 */
	public function on_audit_clean_pause( int $snapshot_id ) : void {
		global $wpdb;
		if ( $this->is_scheduled_audit( $snapshot_id ) ) {
			$wpdb->update( $wpdb->ahrefs_snapshots, [ 'snapshot_type' => 'scheduled_restarted' ], [ 'snapshot_id' => $snapshot_id ], [ '%s' ], [ '%d' ] );
		}
	}
}
