<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/*
Do nothing if no 'new' snapshot exists.

1. If we have items with initial status (ACTION4_ANALYZING_INITIAL, ACTION4_OUT OF_SCOPE_INITIAL): run update for it.
function init_item() - copy existing detail and assign temporarily status.
Copy keyword, keyword_manual, is_approved_keyword, is_excluded, is_included from existing (current) snapshot (if it exists) to new item.
Assign new temporary status (ACTION4_ANALYZING or ACTION4_OUT_OF_SCOPE_ANALYZING):
if "is_include" = 1 then set ACTION4_ANALYZING.
if "is_exclude" = 1, then set ACTION4_OUT_OF_SCOPE_ANALYZING;
otherwise replace ACTION4_ANALYZING_INITIAL with ACTION4_ANALYZING, ACTION4_OUT_OF_SCOPE_INITIAL with ACTION4_OUT_OF_SCOPE_ANALYZING.

2. If no item with initial status exists - step 2.
Worker classes + function get_unprocessed_item_from_new()
Update items with any status and empty data (traffic, backlinks, keywords, position).

3. When all data is filled (traffic, backlinks, keywords, position) for all items:
detect all inactive items (and set corresponding status);
set ACTION4_ERROR_ANALYZING to all items with errors,
set real status for all ACTION4_OUT_OF_SCOPE_ANALYZING items (this way we exclude all inactive items),
set ACTION4_ANALYZING_FINAL to the rest (active) items,

4. calculate and save traffic median using items with ACTION4_ANALYZING_FINAL status only;

5. If traffic median value exists.
Update only items with ACTION4_ANALYZING_FINAL status.
fill recommended action for each item.

6. When no items with ACTION4_ANALYZING_FINAL exists:
update 'new' snapshot to 'current', and existing 'current' to 'old'.

Update progress:
items (ACTION4_ANALYZING, ACTION4_ANALYZING_INITIAL) without (traffic, backlinks, keywords, position) / ( 4 * total items) * 99%;
last percent mean update of statuses.
*/

/**
 * Class for content audit.
 *
 * This code always works with new snapshot.
 * Instance must be created after new snapshot created.
 */
class Content_Audit {

	private const TRANSIENT_NAME      = 'ahrefs-content-running7';
	private const TRANSIENT_NAME_CRON = 'ahrefs-content-running0';
	/** Max time allowed for single content update (seconds). Will exit after this time end. */
	private const MAX_UPDATE_TIME = 15;

	/** Is audit stopped? */
	private const OPTION_AUDIT_STOPPED = 'ahrefs-seo-audit-stopped';
	/** Reason, why it is not possible to run audit. */
	private const OPTION_AUDIT_STOP_REASON           = 'ahrefs-seo-audit-stop-reason';
	private const OPTION_AUDIT_STOP_REASON_SCHEDULED = 'ahrefs-seo-audit-stop-reason-scheduled';

	/**
	 * @var Snapshot
	 */
	protected $snapshot;
	/**
	 * ID of 'new' snapshot.
	 *
	 * @var int|null
	 */
	protected $snapshot_id = null;

	/** @var Ahrefs_Seo_Analytics|null */
	protected $analytics;

	/** @var Ahrefs_Seo_Api|null */
	protected $api;

	/** @var Ahrefs_Seo_Noindex|null */
	protected $noindex;

	/**
	 * Cached post
	 *
	 * @var \WP_Post|null
	 */
	private $post;

	/**
	 * @var float|null Minimal waiting time of workers, value in seconds or null if no pause set.
	 */
	protected $workers_waiting_time = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->snapshot    = new Snapshot();
		$this->snapshot_id = $this->snapshot->get_new_snapshot_id();
	}

	/**
	 * Run table update.
	 * Called on Wizard last step and using "ping" ajax call at the Content Audit screen.
	 *
	 * @param bool $run_from_cron
	 * @return bool true Did current run update something.
	 */
	public function update_table( bool $run_from_cron = false ) : bool {
		if ( is_null( $this->snapshot_id ) ) {
			return false; // nothing to update.
		}
		if ( Ahrefs_Seo_Errors::has_stop_error( false ) ) {
			return false;
		}
		$result         = false;
		$transient_name = self::TRANSIENT_NAME; // use same for cron and ping requests.
		$time           = microtime( true );
		Ahrefs_Seo::breadcrumbs( __METHOD__ );
		if ( ! get_transient( $transient_name ) ) {
			try {
				Ahrefs_Seo::set_time_limit( 300 ); // call it before set transient, because it can update transient time.
				set_transient( $transient_name, true, Ahrefs_Seo::transient_time() );

				try {
					// 1. update initial statuses.
					$initiated = true;
					while ( ! $this->update_initial_statuses() ) {
						if ( $this->maybe_finish( $time ) ) {
							$initiated = false;
							break;
						}
					}
					if ( ! $initiated ) {
						$result = true; // we have more tasks.
					} else {
						if ( $this->has_unprocessed_items() ) {
							$result = true; // return: we have tasks.
							Ahrefs_Seo::ignore_user_abort( true );

							$this->maybe_can_not_proceed();

							// 2. load details for 'analyzing' items.
							// create all workers, this will set max allowed execution time.
							$w_keywords  = ( new Worker_Keywords( $this, $this->get_analytics(), $run_from_cron ) );
							$w_backlinks = ( new Worker_Backlinks( $this, $this->get_api(), $run_from_cron ) );
							$w_traffic   = ( new Worker_Traffic( $this, $this->get_analytics(), $run_from_cron ) );
							$w_noindex   = ( new Worker_Noindex( $this, $this->get_noindex(), $run_from_cron ) );
							$w_position  = ( new Worker_Position( $this, $this->get_analytics(), $run_from_cron ) );

							$has_more_items = true;
							while ( $has_more_items && ! Ahrefs_Seo::should_finish() && ! $this->maybe_finish( $time ) ) {
								// run multi requests, one by one.
								$has_more_items = $w_keywords->execute();
								$has_more_items = $w_backlinks->execute() || $has_more_items;
								$has_more_items = $w_traffic->execute() || $has_more_items;
								$has_more_items = $w_noindex->execute() || $has_more_items;
								$has_more_items = $w_position->execute() || $has_more_items;
								$times          = array_filter(
									[ $w_keywords->get_waiting_seconds(), $w_backlinks->get_waiting_seconds(), $w_traffic->get_waiting_seconds(), $w_noindex->get_waiting_seconds(), $w_position->get_waiting_seconds() ],
									function( $value ) {
										return ! is_null( $value );
									}
								);
								if ( count( $times ) ) {
									$this->workers_waiting_time = min( $times );
								} else {
									$this->workers_waiting_time = null;
								}
								// is it make sense to sleep a bit and continue in current thread?
								if ( ! $has_more_items && ! is_null( $this->workers_waiting_time )
								&& ! Ahrefs_Seo::should_finish( intval( $this->workers_waiting_time + 10 ) )
								&& ( time() - $time + $this->workers_waiting_time <= self::MAX_UPDATE_TIME + 5 ) ) {
									$has_more_items = true;
									Ahrefs_Seo::usleep( intval( 20000 + 1000000 * $this->workers_waiting_time ) );
								}
							}
						} else {
							// we received all details for all analyzing items.
							// 3. update statuses of each inactive item.
							$post_id           = $this->get_unprocessed_item_id_from_prepared();
							$prepared_finished = is_null( $post_id );
							if ( ! $prepared_finished ) {
								$result = true; // return: we have tasks.
								while ( ! Ahrefs_Seo::should_finish() && ! $this->maybe_finish( $time ) ) {
									if ( ! is_null( $post_id ) ) {
										$this->set_recommended_action( $post_id, true );
									} else {
										$prepared_finished = true;
										break;
									}
									$post_id = $this->get_unprocessed_item_id_from_prepared();
								}
							} else {
								// 4. calculate and save traffic median.
								$this->maybe_update_snapshot_fields();
								if ( ! is_null( $this->get_traffic_median() ) ) {
									$result = true; // return: we have tasks.
									// 5. update statuses of each item.
									while ( ! Ahrefs_Seo::should_finish() && ! $this->maybe_finish( $time ) ) {
										$post_id = $this->get_unprocessed_item_id_from_finished();
										if ( ! is_null( $post_id ) ) {
											$this->set_recommended_action( $post_id );
										} else {
											// 6. set update is finished, update status of new snapshot.
											if ( ! is_null( $this->snapshot_id ) ) {
												$this->snapshot->set_finished( $this->snapshot_id );
												$result = false; // return: everything updated.
											}
											break;
										}
									}
								} else {
									Ahrefs_Seo::notify( new \Exception( 'Empty traffic median.' ) );
								}
							}
						}
					}
					// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				} catch ( \Error $e ) {
					$error = Ahrefs_Seo_Compatibility::on_type_error( $e, __METHOD__, __FILE__, 'Unexpected error on update_table' );
				} catch ( \Exception $e ) {
					// need to finish and return result.
					Ahrefs_Seo::notify( $e, 'Unexpected error on update_table' );
				}
			} finally {
				delete_transient( $transient_name );
			}
		}
		return $result;
	}

	/**
	 * Remove all post details from DB (content table only) for new snapshot, if 'new' snapshot already exists.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function audit_delete_post_details( int $post_id ) : void {
		global $wpdb;
		if ( ! is_null( $this->snapshot_id ) ) {
			$wpdb->delete(
				$wpdb->ahrefs_content,
				[
					'snapshot_id' => $this->snapshot_id,
					'post_id'     => $post_id,
				],
				[ '%d' ]
			);
		}
	}

	/**
	 * Finish work in allowed time (self::MAX_UPDATE_TIME seconds)
	 *
	 * @param float $initial_time Initial timestamp from microtime(true).
	 * @return bool More that allowed time used.
	 */
	protected function maybe_finish( float $initial_time ) : bool {
		return microtime( true ) - $initial_time >= self::MAX_UPDATE_TIME;
	}

	/**
	 * Return analytics instance
	 *
	 * @return Ahrefs_Seo_Analytics
	 */
	private function get_analytics() : Ahrefs_Seo_Analytics {
		if ( empty( $this->analytics ) ) {
			$this->analytics = Ahrefs_Seo_Analytics::get();
		}
		return $this->analytics;
	}
	/**
	 * Return api instance
	 *
	 * @return Ahrefs_Seo_Api
	 */
	private function get_api() : Ahrefs_Seo_Api {
		if ( empty( $this->api ) ) {
			$this->api = Ahrefs_Seo_Api::get();
		}
		return $this->api;
	}
	/**
	 * Return noindex instance
	 *
	 * @since 0.7.3
	 *
	 * @return Ahrefs_Seo_Noindex
	 */
	private function get_noindex() : Ahrefs_Seo_Noindex {
		if ( empty( $this->noindex ) ) {
			$this->noindex = new Ahrefs_Seo_Noindex();
		}
		return $this->noindex;
	}

	/**
	 * Does content audit require update?
	 *
	 * @return bool true if new snapshot exists?
	 */
	public function require_update() : bool {
		$snapshot     = new Snapshot();
		$snapshot_new = $snapshot->get_new_snapshot_id();
		return ! is_null( $snapshot_new );
	}

	/**
	 * Does content audit have unprocessed items.
	 * The logic of choice is the same as get_unprocessed_item_from_new used.
	 *
	 * @see self::get_unprocessed_item_from_new()
	 *
	 * @return bool true if it has some items pending, false if everything finished.
	 */
	public function has_unprocessed_items() : bool {
		global $wpdb;
		$additional_where   = [];
		$additional_where[] = 'organic is null';
		$additional_where[] = 'backlinks is null';
		$additional_where[] = '(keywords_need_update = 1)';
		$additional_where[] = '(position IS NULL OR position_need_update = 1)';
		$additional_where[] = 'is_noindex IS NULL';
		$additional_where   = ' AND (' . implode( ' OR ', $additional_where ) . ')';
		$sql                = $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d $additional_where", $this->snapshot_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count              = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		Ahrefs_Seo::breadcrumbs( __METHOD__ . ': ' . wp_json_encode( $count ) );
		return 0 !== $count;
	}

	/**
	 * Return percent value of unprocessed items.
	 *
	 * @return float 0 mean all is finished, 100 mean nothing proceed.
	 */
	public function content_get_unprocessed_percent() : float {
		global $wpdb;
		if ( is_null( $this->snapshot_id ) ) {
			return 0;
		}
		$count_all = absint( $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d", $this->snapshot_id ) ) );

		if ( 0 === $count_all ) {
			return 1;
		}
		$count_without_analytics = absint( $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND organic is null", $this->snapshot_id ) ) );
		$count_without_backlinks = absint( $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND backlinks is null", $this->snapshot_id ) ) );
		$count_without_position  = absint( $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND position is null", $this->snapshot_id ) ) );
		$count_without_noindex   = absint( $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND is_noindex is null", $this->snapshot_id ) ) );
		// from 100% to 1% pending, the last percent used for suggested actions assignment.
		return max( 1, floatval( ceil( 990 * ( $count_without_analytics + $count_without_backlinks * 4 + $count_without_position * 2 + $count_without_noindex ) / 8 / $count_all ) / 10 ) );
	}

	/**
	 * Copy keywords details from previous snapshot and change status to desired.
	 * Set is_excluded, keyword, keyword_manual, is_approved_keyword column using current snapshot.
	 * Update statuses, include active items from current snapshot, include 'is_included' items.
	 *
	 * @param int    $post_id
	 * @param int    $snapshot_id
	 * @param string $new_action
	 * @return void
	 */
	private function init_item( int $post_id, int $snapshot_id, string $new_action ) : void {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$data            = [];
		$format          = [];
		$previous_action = null;

		$current_snapshot_id = $this->snapshot->get_current_snapshot_id();
		if ( $snapshot_id !== $current_snapshot_id ) {
			// is there any data?
			$data = (array) $wpdb->get_row( $wpdb->prepare( "SELECT keyword, keyword_manual, is_approved_keyword, is_excluded, is_included, action, ignore_newly FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id = %d LIMIT 1", $current_snapshot_id, $post_id ), ARRAY_A );
			if ( $data ) { // also fill format placeholders for the data.
				if ( 'page' === get_post_type( $post_id ) ) { // Note: do not apply 'is_excluded' for pages.
					$data['is_excluded'] = 0;
				}
				$format          = [ '%s', '%s', '%d', '%d', '%d', '%d' ];
				$previous_action = $data['action'];
				unset( $data['action'] );
			}
		}

		if ( ! empty( $data['is_included'] ) ) {
			// include manually included items.
			$new_action = Ahrefs_Seo_Data_Content::ACTION4_ANALYZING;
		} elseif ( ! empty( $data['is_excluded'] ) ) {
			// exclude manually excluded items.
			$new_action = Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING;
		}

		$data['action'] = $new_action; // add new action to fields.
		$format[]       = '%s';
		Ahrefs_Seo::breadcrumbs( __METHOD__ . ': ' . wp_json_encode( $data ) );
		$wpdb->update(
			$wpdb->ahrefs_content,
			$data,
			[
				'snapshot_id' => $snapshot_id,
				'post_id'     => $post_id,
			],
			$format,
			[ '%d', '%d' ]
		);
	}

	/**
	 * Update initial statuses to common.
	 * Copy keywords and other details from current snapshot.
	 *
	 * @return bool Everything updated, no need to call again.
	 */
	protected function update_initial_statuses() : bool {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ );

		$items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, snapshot_id, action FROM {$wpdb->ahrefs_content} WHERE action = %s OR action = %s LIMIT 50", Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL ), ARRAY_A );
		if ( $items ) {
			foreach ( $items as $item ) {
				$new_action = Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL === $item['action'] ? Ahrefs_Seo_Data_Content::ACTION4_ANALYZING : Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING;
				$this->init_item( (int) $item['post_id'], (int) $item['snapshot_id'], $new_action );
			}
			return false;
		}
		return true;
	}

	/**
	 * Return post id of unprocessed item, where post status is 'analyzing' and any of 4 parameters is missing.
	 * Return post ids of unprocessed items, where post status is 'analyzing' and desired parameter what_to_return is missing.
	 * Exclude locked posts.
	 *
	 * @param string|null $what_to_return Null or one of 'traffic, 'backlinks', 'keywords', 'position', 'isnoindex'.
	 * @return array|int[] [post_id=>0 or 1, traffic=>0 or 1, backlinks=>0 or 1, keywords=>0 or 1] Post ID and what to update OR list of post ids.
	 */
	public function get_unprocessed_item_from_new( ?string $what_to_return = null ) : array {
		global $wpdb;

		$result           = [];
		$additional_where = [];
		if ( is_null( $what_to_return ) || 'traffic' === $what_to_return ) {
			$additional_where[] = 'organic IS NULL';
		}
		if ( is_null( $what_to_return ) || 'backlinks' === $what_to_return ) {
			$additional_where[] = 'backlinks IS NULL';
		}
		if ( is_null( $what_to_return ) || 'keywords' === $what_to_return ) {
			$additional_where[] = '(keywords_need_update = 1)';
		}
		if ( is_null( $what_to_return ) || 'position' === $what_to_return ) {
			$additional_where[] = '(position IS NULL OR position_need_update = 1) AND (keywords_need_update != 1)';
		}
		if ( is_null( $what_to_return ) || 'isnoindex' === $what_to_return ) {
			$additional_where[] = 'is_noindex IS NULL';
		}

		$additional_where = ' AND (' . implode( ' OR ', $additional_where ) . ')';
		$limit            = 10;
		$sql              = $wpdb->prepare( "SELECT post_id, ( organic IS NULL ) as traffic, (backlinks IS NULL) as backlinks, ( keywords_need_update = 1 ) as keywords, ( position IS NULL OR position_need_update = 1 ) as position, ( is_noindex IS NULL ) as noindex FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d $additional_where LIMIT %d", $this->snapshot_id, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data             = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $data ) ) {
			foreach ( $data as $key => $item ) {
				if ( 'publish' !== get_post_status( (int) $item['post_id'] ) ) {
					$this->delete_not_published_post( (int) $item['post_id'] );
					unset( $data[ $key ] );
				}
			}
			if ( ! empty( $data ) ) {
				$data = array_values( $data );
				if ( ! is_null( $what_to_return ) ) {
					$result = array_map(
						function( $item ) {
							return (int) $item['post_id'];
						},
						$data
					); // return all found results as post ID list, will filter using locked posts later.
				} else {
					$result = $data[ rand( 0, count( $data ) - 1 ) ]; // return some single non locked post.
				}
			} else { // we unset all posts from results...
				return $this->get_unprocessed_item_from_new( $what_to_return ); // call itself again and return result.
			}
		}
		Ahrefs_Seo::breadcrumbs( sprintf( '%s(%s): %s', __METHOD__, $what_to_return, wp_json_encode( $result ) ) );
		return $result;
	}

	/**
	 * Get unprocessed item with prepared fields.
	 *
	 * @return int|null post id or null.
	 */
	protected function get_unprocessed_item_id_from_prepared() : ?int {
		global $wpdb;
		// update "out of scope" or "analyzing" items with error or noindex status.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND ( action = %s OR action = %s ) AND ( total < 0 OR organic < 0 OR backlinks < 0 OR position < 0 OR is_noindex < 0 OR is_noindex = 1 ) LIMIT 1",
				$this->snapshot_id,
				Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
				Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING
			)
		);
		if ( is_null( $result ) ) {
			// "analyzing" items.
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND ( action = %s OR action = %s )", $this->snapshot_id, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING ) );
		}
		return ! is_null( $result ) ? (int) $result : null;
	}

	/**
	 * Get unprocessed item with prepared fields with ACTION4_ANALYZING_FINAL status
	 *
	 * @return int|null post id or null.
	 */
	protected function get_unprocessed_item_id_from_finished() : ?int {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND ( action = %s OR action = %s )", $this->snapshot_id, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING ) );
		return ! is_null( $result ) ? (int) $result : null;
	}

	/**
	 * Update recommended action for items with ACTION4_ANALYZED action status.
	 *
	 * Please update Ahrefs_Seo::CURRENT_CONTENT_RULES value after any changes.
	 *
	 * The priorities for statuses are.
	 * For any newly added item:
	 * - added since last audit - do not analyze it, until next audit or until user included it.
	 * For item beeing analyzed:
	 * - error analyzing - if any error happened and any data missing;
	 * - noindex
	 * - out of scope
	 * - manually excluded
	 * - newly published
	 * - any other status
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $set_only_inactive_items
	 * @return bool Was status of this post updated.
	 */
	protected function set_recommended_action( int $post_id, $set_only_inactive_items = false ) : bool {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( [ $post_id, $this->snapshot_id, $set_only_inactive_items ] ) );
		// load all the details.
		$sql  = $wpdb->prepare( "SELECT date(p.post_date) as created, c.action as action, total_month as total, organic_month as organic, backlinks, position, is_excluded, is_noindex, ignore_newly, inactive, p.post_status as post_status FROM {$wpdb->ahrefs_content} c, {$wpdb->posts} p WHERE c.snapshot_id = %d AND c.post_id = %d AND c.post_id = p.ID LIMIT 1", $this->snapshot_id, absint( $post_id ) );
		$data = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $data && 'publish' === $data['post_status'] ) {
			$_post            = get_post( $post_id );
			$action           = Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING;
			$no_total_traffic = is_null( $data['total'] );
			$traffic          = intval( $data['total'] ?: 0 );
			$organic_sessions = intval( $data['organic'] ?: 0 );
			$backlinks        = intval( $data['backlinks'] ?: 0 );
			$position         = floatval( $data['position'] ?: Ahrefs_Seo_Data_Content::POSITION_MAX );
			$published        = strtotime( $data['created'] );
			$ignore_newly     = (bool) $data['ignore_newly']; // ignore newly published.
			$noindex          = (int) $data['is_noindex'];
			$inactive_prev    = (bool) $data['inactive'] ? 1 : 0;

			$waiting_weeks     = Ahrefs_Seo_Data_Content::get()->get_waiting_weeks(); // time in weeks.
			$median_traffic    = $this->get_traffic_median();
			$has_same_keywords = null;

			if ( $no_total_traffic || $traffic < 0 || $organic_sessions < 0 || $backlinks < 0 || $position < 0 || $noindex < 0 ) { // were there any errors?
				$action = Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING;
			} elseif ( 1 === $noindex ) { // Possible values: -1 (can not detect), 0 (index), 1 (noindex).
				$action = Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE;
			} elseif ( (bool) $data['is_excluded'] ) {
				$action = Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED;
			} elseif ( in_array( (string) $data['action'], [ Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING ], true ) ) {
				$action = Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE;
			} else {
				if ( $published > strtotime( "- $waiting_weeks week" ) && ! $ignore_newly ) {
					$action = Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED;
				} elseif ( $position <= 3.5 ) {
					$action = Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING;
				} elseif ( $position <= 20 ) {
					// Target keyword of post != Target keyword of other post?
					$has_same_keywords = ! is_null( $this->snapshot_id ) ? Ahrefs_Seo_Advisor::get()->has_active_pages_with_same_keywords( $this->snapshot_id, $post_id ) : false;

					if ( $has_same_keywords ) {
						$action = Ahrefs_Seo_Data_Content::ACTION4_MERGE;
					} else {
						$action = Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW;
					}
				} else { // Position > 20.
					if ( $traffic >= $median_traffic && $median_traffic > 0 && ! ( defined( 'AHREFS_SEO_NO_GA' ) && AHREFS_SEO_NO_GA ) ) { // Traffic of URL >= Median of all traffic, ignore 'Exclude' when median traffic = 0.
						$action = Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE;
					} else { // Traffic of URL < Median of all traffic.
						if ( $backlinks > 0 ) {
							$action = Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE;
						} else {
							$action = Ahrefs_Seo_Data_Content::ACTION4_DELETE;
						}
					}
				}
			}

			$inactive = in_array( $action, [ Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE, Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL, Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST, Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING ], true ) ? 1 : 0;
			if ( $set_only_inactive_items && ! $inactive ) {
				$action = Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL;
			}
			Ahrefs_Seo::breadcrumbs(
				__METHOD__ . ': ' . wp_json_encode(
					[
						'post_id'       => $post_id,
						'action'        => $action,
						'inactive'      => $inactive,
						'inactive_prev' => $inactive_prev,
					]
				)
			);
			$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET action = %s, inactive = %d WHERE snapshot_id = %d AND post_id = %d", $action, $inactive, $this->snapshot_id, $post_id );
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// when some new post included into (excluded from) active scope from inactive, update any other active post with the same keyword too.
			if ( ! $set_only_inactive_items && $inactive_prev !== $inactive ) {
				$posts_reanalyze = ! is_null( $this->snapshot_id ) ? Ahrefs_Seo_Advisor::get()->find_active_pages_with_same_keyword( $this->snapshot_id, $post_id ) : null;
				if ( ! is_null( $posts_reanalyze ) && count( $posts_reanalyze ) ) {
					if ( ! empty( $posts_reanalyze ) ) {
						foreach ( $posts_reanalyze as $post_id ) {
							// update recommended action.
							$this->reanalyze_post( $post_id );
						}
					}
				}
			}

			return true;
		} else {
			// post exists in content audit table, but not exists in posts table; or post is not published.
			Ahrefs_Seo::breadcrumbs( sprintf( '%s: post %d not exists - remove from content table', __METHOD__, $post_id ) );
			$this->delete_not_published_post( $post_id );
		}

		return false;
	}

	/**
	 * Update post with position.
	 * Use time range from keywords.
	 *
	 * @param int[] $post_ids
	 * @param bool  $fast_update Do not load all details, but load keyword position only.
	 * @return void
	 */
	public function update_post_info_position( array $post_ids, bool $fast_update = false ) : void {
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		( new Worker_Position( $this, $this->analytics ) )->update_posts_info( $post_ids, $fast_update );
	}

	/**
	 * Save traffic values to content table.
	 *
	 * @param int         $post_id Post ID.
	 * @param int|null    $total Total traffic value, since post created/modified time.
	 * @param int         $organic Total organic traffic value, since post created/modified time.
	 * @param int|null    $total_month Monthly amount of total traffic.
	 * @param int         $organic_month Monthly amount of organic traffic.
	 * @param null|string $error Error message if any.
	 * @return void
	 */
	public function update_traffic_values( int $post_id, ?int $total, int $organic, ?int $total_month, int $organic_month, ?string $error = null ) : void {
		global $wpdb;
		if ( ! is_null( $total ) ) {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET total=%d, organic=%d, total_month=%d, organic_month=%d WHERE snapshot_id = %d AND post_id = %d", $total, $organic, $total_month, $organic_month, $this->snapshot_id, $post_id );
		} else {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET total=NULL, organic=%d, total_month=NULL, organic_month=%d WHERE snapshot_id = %d AND post_id = %d", $organic, $organic_month, $this->snapshot_id, $post_id );
		}
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_null( $error ) ) {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET error_traffic = %s WHERE snapshot_id = %d AND post_id = %d", "$error", $this->snapshot_id, $post_id );
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Save backlinks value to content table
	 *
	 * @param int         $post_id Post ID.
	 * @param int         $backlinks Backlinks value.
	 * @param null|string $error Error message if any.
	 * @return void
	 */
	public function update_backlinks_values( int $post_id, int $backlinks, ?string $error = null ) : void {
		global $wpdb;
		$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET backlinks=%d, error_backlinks = NULL WHERE snapshot_id = %d AND post_id = %d", $backlinks, $this->snapshot_id, $post_id );
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_null( $error ) ) {
			$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET error_backlinks = %s WHERE snapshot_id = %d AND post_id = %d", $error, $this->snapshot_id, $post_id );
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Save noindex value to content table
	 *
	 * @param int $post_id Post ID.
	 * @param int $noindex Noindex value value: -1, 0 or 1.
	 * @return void
	 */
	public function update_noindex_values( int $post_id, int $noindex ) : void {
		global $wpdb;
		$wpdb->update(
			$wpdb->ahrefs_content,
			[ 'is_noindex' => $noindex ],
			[
				'snapshot_id' => $this->snapshot_id,
				'post_id'     => $post_id,
			],
			[ '%d' ],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Save position value to content table.
	 * Reset 'position need update' flag.
	 *
	 * @param int         $post_id Post ID.
	 * @param float|null  $position Position value.
	 * @param null|string $error Error message if any.
	 * @return void
	 */
	public function update_position_values( int $post_id, ?float $position, ?string $error = null ) : void {
		global $wpdb;
		if ( is_null( $position ) ) { // is this an error or position not found?
			$position = empty( $error ) ? Ahrefs_Seo_Data_Content::POSITION_MAX : -1;
		}

		$wpdb->update(
			$wpdb->ahrefs_content,
			[
				'position'             => $position,
				'error_position'       => $error,
				'position_need_update' => 0,
			],
			[
				'snapshot_id' => $this->snapshot_id,
				'post_id'     => $post_id,
			],
			[ '%f', '%s', '%d' ],
			[ '%d', '%s' ]
		);
	}

	/**
	 * Set position value to max value and reset position update flag.
	 * Called when API returned results, but there is no result for current keyword.
	 *
	 * @param int[] $post_ids
	 * @return void
	 */
	public function post_positions_set_updated( array $post_ids ) : void {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET position = %f, position_need_update = 0 WHERE snapshot_id = %d AND post_id IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ')', Ahrefs_Seo_Data_Content::POSITION_MAX, $this->snapshot_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get time period for queries.
	 * assume range = 6 months.
	 * start date = today.
	 * finish date = today - waiting time (months) or post created/modified date.
	 *
	 * @since 0.7.3
	 *
	 * @param int $post_id Post ID.
	 * @return int      $days_count Return number of days between start and today dates.
	 */
	public function get_time_period_for_post( int $post_id ) : int {
		$waiting_time = Ahrefs_Seo_Data_Content::get()->get_waiting_weeks();
		$this_post    = $this->get_post( $post_id );
		$start_time   = ! is_null( $this_post ) ? strtotime( $this_post->post_date ) : 0;
		$latest_ago   = strtotime( sprintf( '- %d week', $waiting_time ) );
		if ( $start_time < $latest_ago ) {
			$start_time = $latest_ago;
		}
		$days_count = intval( round( ( time() - max( [ $start_time, $latest_ago ] ) ) / DAY_IN_SECONDS ) );
		return max( [ 1, $days_count ] );
	}

	/**
	 * Get time period for queries.
	 * assume range = 6 months.
	 * start date = today.
	 * finish date = today - waiting time (months) or post created/modified date.
	 *
	 * @param int|null $post_id Input parameter: post ID or null for default time period.
	 * @param string   $start_date Return staring date.
	 * @param string   $end_date Return end date.
	 * @param int      $days_count Return number of days between start and end dates.
	 * @return bool Success?
	 */
	private function get_time_period( ?int $post_id, string &$start_date, string &$end_date, int &$days_count ) : bool {
		$waiting_time = Ahrefs_Seo_Data_Content::get()->get_waiting_weeks();
		if ( ! is_null( $post_id ) ) {
			$post = $this->get_post( $post_id );
		} else {
			$post            = new \stdClass();
			$post->post_date = sprintf( '- %d week', $waiting_time );
		}
		if ( ! empty( $post ) ) {
			$start_time = strtotime( $post->post_date );
			$latest_ago = strtotime( sprintf( '- %d week', $waiting_time ) );
			$now        = time();
			if ( $start_time < $latest_ago ) {
				$start_time = $latest_ago;
			}
			if ( $start_time > $now ) {
				$start_time = $now;
			}
			$end_time   = $now;
			$start_date = date( 'Y-m-d', $start_time );
			$end_date   = date( 'Y-m-d', $end_time );
			$days_count = intval( round( ( $end_time - $start_time ) / DAY_IN_SECONDS ) );
			if ( $days_count < 1 ) {
				$days_count = 1;
			}
			return true;
		}
		return false;
	}


	/**
	 * Get post object using post id.
	 * Cache values.
	 *
	 * @param int $post_id
	 * @return null|\WP_Post
	 */
	protected function get_post( int $post_id ) : ?\WP_Post {
		if ( is_null( $this->post ) || ! is_null( $this->post ) && ( $this->post->ID !== $post_id ) ) {
			$result     = get_post( $post_id );
			$this->post = $result instanceof \WP_Post ? $result : null;
		}
		return $this->post;
	}

	/**
	 * Traffic median calculate using content table traffic.
	 * Exclude inactive () and out-of-scope items.
	 *
	 * @return float
	 */
	private function traffic_median_calculate() : float {
		global $wpdb;
		self::audit_clean_pause();
		// Note: do not include items with traffic error (total_month < 0) and excluded items (is_excluded = 1).
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT total_month as traffic FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND total_month >= 0 AND action != %s AND inactive = 0 AND is_excluded = 0", $this->snapshot_id, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE ) );
		Ahrefs_Seo::breadcrumbs( __METHOD__ . '(' . $this->snapshot_id . '): ' . wp_json_encode( $values ) );
		if ( empty( $values ) ) {
			return 0;
		}
		$values = array_map( 'intval', $values );
		sort( $values );
		$count  = count( $values );
		$middle = floor( ( $count - 1 ) / 2 );

		if ( $count % 2 ) {
			return $values[ $middle ];
		}
		return ( $values[ $middle ] + $values[ $middle + 1 ] ) / 2;
	}

	/**
	 * Return string with error description or null if API is ok.
	 *
	 * @return bool Some account is not connected.
	 */
	public function maybe_can_not_proceed() : bool {
		$no_ahrefs = $this->get_api()->is_disconnected() || $this->get_api()->is_limited_account( true );
		$no_gsc    = ! $this->get_analytics()->is_gsc_set();
		$no_ga     = ! $this->get_analytics()->is_ua_set();

		if ( $no_ahrefs || $no_gsc || $no_ga ) {
			$this->fill_with_errors( $no_ahrefs, $no_gsc, $no_ga );
			return true;
		}
		return false;
	}

	private function fill_with_errors( bool $no_ahrefs, bool $no_gsc, bool $no_ga ) : void {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . wp_json_encode( func_get_args() ) );
		if ( $no_ahrefs ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET backlinks = -10, error_backlinks = %s WHERE snapshot_id = %d AND backlinks IS NULL", $this->snapshot_id, 'Ahrefs account is not connected or limited' ) );
		}
		if ( $no_gsc ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET kw_gsc = NULL, position = -1, position_need_update = 0, error_position = %s WHERE snapshot_id = %d AND ( position IS NULL OR position_need_update = 1 )", 'Google Search Console is not connected', $this->snapshot_id ) );
		}
		if ( $no_ga ) {
			if ( $no_ahrefs ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET total = -1, total_month = -1, organic = -1, organic_month = -1, error_traffic = %s WHERE snapshot_id = %d AND ( total IS NULL OR organic IS NULL )", 'Google Analytics is not connected', $this->snapshot_id ) );
			} else { // organic traffic coming from Ahrefs.
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET total = -1, total_month = -1, error_traffic = %s WHERE snapshot_id = %d AND total IS NULL", 'Google Analytics is not connected', $this->snapshot_id ) );
			}
		}
	}

	/**
	 * Calculate traffic median and save into snapshot field.
	 *
	 * @return void
	 */
	private function maybe_update_snapshot_fields() : void {
		$result = $this->get_traffic_median();
		if ( is_null( $result ) && ! is_null( $this->snapshot_id ) ) {
			$result = $this->traffic_median_calculate();
			$this->snapshot->set_traffic_median( $this->snapshot_id, $result );
		}
	}

	/**
	 * Get traffic median
	 *
	 * @return float|null Madian value or null.
	 */
	protected function get_traffic_median() : ?float {
		$result = null;
		if ( ! is_null( $this->snapshot_id ) ) {
			$key    = "median{$this->snapshot_id}";
			$result = wp_cache_get( $key, 'ahrefs_seo_audit' );

			if ( is_null( $result ) || false === $result ) {
				$result = $this->snapshot->get_traffic_median( $this->snapshot_id );
				if ( ! is_null( $result ) ) {
					wp_cache_set( $key, $result, 'ahrefs_seo_audit', HOUR_IN_SECONDS );
				}
			}
		}
		return is_null( $result ) || false === $result ? null : floatval( $result );
	}

	/**
	 * Clear internal cached data
	 */
	public function clear_cache() : void {
		delete_transient( self::TRANSIENT_NAME );
	}

	/**
	 * Reset post errors at content table before update request
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $reset_traffic_error
	 * @param bool $reset_backlinks_error
	 * @param bool $reset_position_error
	 * @return void
	 */
	private function content_reset_post_errors( int $post_id, bool $reset_traffic_error = true, bool $reset_backlinks_error = true, bool $reset_position_error = true ) : void {
		global $wpdb;
		$updates = [];
		$format  = [];
		if ( $reset_traffic_error ) {
			$updates['error_traffic'] = null;
			$format[]                 = [ '%s' ];
		}
		if ( $reset_backlinks_error ) {
			$updates['error_backlinks'] = null;
			$format[]                   = [ '%s' ];
		}
		if ( $reset_position_error ) {
			$updates['error_position'] = null;
			$format[]                  = [ '%s' ];
		}
		if ( count( $updates ) ) {
			$wpdb->update(
				$wpdb->ahrefs_content,
				$updates,
				[
					'snapshot_id' => $this->snapshot_id,
					'post_id'     => $post_id,
				],
				$format,
				[ '%d', '%d' ]
			);
		}
	}

	/**
	 * Approve keyword and reset position info.
	 *
	 * @param int    $post_id
	 * @param string $approved_keyword
	 * @return void
	 */
	public function keyword_approve( int $post_id, string $approved_keyword ) : void {
		global $wpdb;
		if ( is_null( $this->snapshot_id ) ) {
			return; // nothing to update.
		}

		// is the same keyword?
		$keyword = Ahrefs_Seo_Keywords::get()->post_keyword_get( $this->snapshot_id, $post_id );
		$data    = [ 'is_approved_keyword' => 1 ];
		$format  = [ '%d' ];
		if ( $keyword !== $approved_keyword ) {
			$data   = [
				'keyword'              => $approved_keyword,
				'position'             => null,
				'error_position'       => null,
				'position_need_update' => 1,
			];
			$format = [ '%d', '%s', '%f', '%s', '%d' ];
		}

		$wpdb->update(
			$wpdb->ahrefs_content,
			$data,
			[
				'snapshot_id' => $this->snapshot_id,
				'post_id'     => $post_id,
			],
			$format,
			[ '%d', '%s' ]
		);
		( new Content_Tips_Content() )->on_keyword_approved( $this->snapshot_id );
	}

	/**
	 * Reanalyze a post using existing info.
	 * Do not make any requests
	 *
	 * @param int $post_id
	 * @return bool Success?
	 */
	public function reanalyze_post( int $post_id ) : bool {
		$result         = false;
		$median_traffic = $this->get_traffic_median();
		if ( ! is_null( $median_traffic ) ) {
			$this->set_recommended_action( $post_id );
			$result = true;
		}
		return $result;
	}


	/**
	 * Action when user clicked on "add to audit" or similar link, that start the audit of page.
	 * Include item into analysis, or update some fields and run analysis again.
	 *
	 * @param int[]|string[] $post_ids Post IDs to include into analysis.
	 * @return int[] Errors: list of posts ID, which can not be added.
	 */
	public function audit_include_posts( array $post_ids ) : array {
		global $wpdb;
		$errors_ids  = []; // result to return.
		$updated_ids = []; // will reset require_update flag of snapshot, if has some post here.
		if ( ! is_null( $this->snapshot_id ) ) {
			if ( $this->snapshot->get_new_snapshot_id() === $this->snapshot_id && $this->snapshot->get_current_snapshot_id() !== $this->snapshot_id ) {
				// if this is the 'new' snapshot (and current snapshot also exists): simply reinitialize a post using current snapshot.
				// this is a new snapshot, and has another (current) snapshot, to copy initial details from.
				foreach ( $post_ids as $post_id ) {
					// copy initial details from existing 'current' snapshot.
					$this->init_item( (int) $post_id, (int) $this->snapshot_id, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING );
					$updated_ids[] = $post_id;
				}
			} else {
				// Existing logic applied to current snapshot (being viewed by user), current or new (if no current exists).
				foreach ( $post_ids as $post_id ) {
					$post_id = (int) $post_id;
					/** @var \WP_Post|null */
					$post = get_post( $post_id );
					if ( empty( $post ) || 'publish' !== $post->post_status ) {
						$errors_ids[] = $post_id;
						$this->delete_not_published_post( $post_id );
					} else {
						$action = $wpdb->get_var( $wpdb->prepare( "SELECT action FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id = %d", $this->snapshot_id, $post_id ) ) ?? Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST;
						switch ( $action ) {
							case Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST:
								// The post is missing at the table, or has 'added since last audit'. Lets add it.
								$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ahrefs_content} ( snapshot_id, post_id, action, inactive ) VALUES ( %d, %d, %s, 1 )", $this->snapshot_id, $post_id, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL ) );
								$updated_ids[] = $post_id;
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING:
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL:
							case Ahrefs_Seo_Data_Content::ACTION4_DELETE:
							case Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING:
							case Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE:
							case Ahrefs_Seo_Data_Content::ACTION4_MERGE:
							case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE:
							case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW:
								// Can not add already included to audit post.
								$errors_ids[] = $post_id;
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL:
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL:
								// Initial status. Don't know what to do. Please try again later.
								$errors_ids[]  = $post_id; // Can not add already included to audit post.
								$updated_ids[] = $post_id; // but need to analyze it.
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE:
								// set is_noindex = null and will analyze after data received.
								$wpdb->update(
									$wpdb->ahrefs_content,
									[
										'action'     => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
										'is_noindex' => null,
									],
									[
										'snapshot_id' => $this->snapshot_id,
										'post_id'     => $post_id,
									],
									[ '%s', '%d', '%d' ],
									[ '%d', '%d' ]
								);
								$updated_ids[] = $post_id;
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED:
								// set ignore_newly = 1 and reanalyze immediately.
								$wpdb->update(
									$wpdb->ahrefs_content,
									[
										'action'       => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
										'ignore_newly' => 1,
									],
									[
										'snapshot_id' => $this->snapshot_id,
										'post_id'     => $post_id,
									],
									[ '%s', '%d', '%d' ],
									[ '%d', '%d' ]
								);
								$this->reanalyze_post( $post_id );
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE:
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING:
								// set is_included = 1 and reanalyze immediately.
								$wpdb->update(
									$wpdb->ahrefs_content,
									[
										'action'      => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
										'is_included' => 1,
									],
									[
										'snapshot_id' => $this->snapshot_id,
										'post_id'     => $post_id,
									],
									[ '%s', '%d', '%d' ],
									[ '%d', '%d' ]
								);
								$this->reanalyze_post( $post_id );
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED:
								// set is_excluded = 0, is_included = 1 (as we do not want to assign 'out of scope') and reanalyze immediately.
								$wpdb->update(
									$wpdb->ahrefs_content,
									[
										'action'      => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
										'is_excluded' => 0,
										'is_included' => 1,
									],
									[
										'snapshot_id' => $this->snapshot_id,
										'post_id'     => $post_id,
									],
									[ '%s', '%d', '%d', '%d' ],
									[ '%d', '%d' ]
								);
								$this->reanalyze_post( $post_id );
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING:
								// reset all fields and will analyze after data received.
								$this->reset_all_fields( $post_id );
								$updated_ids[] = $post_id;
								break;
							default:
								$errors_ids[] = $post_id;
						}
					}
				}
			}
			// and update everything (update a first item immediately, next items of 'new' snapshot will be updated later using heartbeat or content cron).
			if ( count( $updated_ids ) ) {
				$this->snapshot->set_require_update( (int) $this->snapshot_id ); // this will run Cron Content updates too.
				$this->update_table();
			}
		}
		return $errors_ids;
	}

	/**
	 * Delete not published post from content audit.
	 * It makes no sense to analyze non published post.
	 *
	 * @since 0.7.5
	 *
	 * @param int $post_id
	 * @return void
	 */
	private function delete_not_published_post( int $post_id ) : void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->ahrefs_content,
			[
				'post_id'     => $post_id,
				'snapshot_id' => $this->snapshot_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Action when user clicked on "exclude from audit" or similar link, that stop the audit of page.
	 * Exclude item from analysis.
	 * Can not exclude items with actions:
	 * ACTION4_ERROR_ANALYZING (has high priority),
	 * ACTION4_NOINDEX_PAGE (has high priority),
	 * ACTION4_OUT_OF_SCOPE (has high priority),
	 * ACTION4_MANUALLY_EXCLUDED (already excluded).
	 *
	 * @param int[]|string[] $post_ids Post IDs to include into analysis.
	 * @return int[] Errors: list of posts ID, which can not be stopped.
	 */
	public function audit_exclude_posts( array $post_ids ) : array {
		global $wpdb;
		$errors_ids  = []; // result to return.
		$updated_ids = []; // will reset require_update flag of snapshot, if has some post here.
		if ( ! is_null( $this->snapshot_id ) ) {
			if ( $this->snapshot->get_new_snapshot_id() === $this->snapshot_id && $this->snapshot->get_current_snapshot_id() !== $this->snapshot_id ) {
				// this is a new snapshot, and has another (current) snapshot, to copy initial details from.
				foreach ( $post_ids as $post_id ) {
					if ( 'publish' !== get_post_status( (int) $post_id ) ) {
						$this->delete_not_published_post( (int) $post_id );
					} else {
						// copy initial details from existing 'current' snapshot.
						$this->init_item( (int) $post_id, (int) $this->snapshot_id, Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE );
						$updated_ids[] = $post_id;
					}
				}
			} else {
				// this is a current or new snapshot (and current is not exists).
				foreach ( $post_ids as $post_id ) {
					$post_id = (int) $post_id;
					if ( 'publish' !== get_post_status( $post_id ) ) {
						$errors_ids[] = (int) $post_id;
						$this->delete_not_published_post( $post_id );
					} else {
						$action = $wpdb->get_var( $wpdb->prepare( "SELECT action FROM {$wpdb->ahrefs_content} WHERE snapshot_id = %d AND post_id = %d", $this->snapshot_id, $post_id ) );
						if ( is_null( $action ) ) {
							// add any missing item.
							$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ahrefs_content} ( snapshot_id, post_id, action ) VALUES ( %d, %d, %s )", $this->snapshot_id, $post_id, Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL ) );
							$action = Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST;
						}

						switch ( $action ) {
							case Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST:
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING:
							case Ahrefs_Seo_Data_Content::ACTION4_DELETE:
							case Ahrefs_Seo_Data_Content::ACTION4_DO_NOTHING:
							case Ahrefs_Seo_Data_Content::ACTION4_EXCLUDE:
							case Ahrefs_Seo_Data_Content::ACTION4_MERGE:
							case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_ORANGE:
							case Ahrefs_Seo_Data_Content::ACTION4_UPDATE_YELLOW:
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_INITIAL:
							case Ahrefs_Seo_Data_Content::ACTION4_ANALYZING_FINAL:
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_INITIAL:
							case Ahrefs_Seo_Data_Content::ACTION4_NEWLY_PUBLISHED:
								// set is_excluded, reset is_included, ignore_newly and reanalyze immediately using existing data.
								$wpdb->update(
									$wpdb->ahrefs_content,
									[
										'action'       => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
										'is_excluded'  => 1,
										'is_included'  => 0,
										'ignore_newly' => 0,
									],
									[
										'snapshot_id' => $this->snapshot_id,
										'post_id'     => $post_id,
									],
									[ '%s', '%d', '%d', '%d', '%d' ],
									[ '%d', '%d' ]
								);
								if ( Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST !== $action ) {
									$this->reanalyze_post( $post_id ); // update status immediately.
								}
								$updated_ids[] = $post_id;
								break;
							case Ahrefs_Seo_Data_Content::ACTION4_NOINDEX_PAGE:
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE:
							case Ahrefs_Seo_Data_Content::ACTION4_OUT_OF_SCOPE_ANALYZING:
							case Ahrefs_Seo_Data_Content::ACTION4_MANUALLY_EXCLUDED:
							case Ahrefs_Seo_Data_Content::ACTION4_ERROR_ANALYZING:
								$errors_ids[] = $post_id;
								break;
							default:
								$errors_ids[] = $post_id;
						}
					}
				}
			}
			// and update everything (update a first item immediately, next items of 'new' snapshot will be updated later using heartbeat or content cron).
			if ( count( $updated_ids ) ) {
				$this->snapshot->set_require_update( (int) $this->snapshot_id ); // this call will turn on Cron Content updates too.
				$this->update_table();
			}
		}
		return $errors_ids;
	}

	/**
	 * Reset all fields (inactive, traffic, backlinks, noindex, errors, keywords, position) of post, set action to analyzing.
	 * Used when we want to reanalyze post with some error.
	 *
	 * @param int $post_id
	 * @return bool Was an entry updated.
	 */
	protected function reset_all_fields( int $post_id ) : bool {
		global $wpdb;
		return (bool) $wpdb->update(
			$wpdb->ahrefs_content,
			[
				'action'               => Ahrefs_Seo_Data_Content::ACTION4_ANALYZING,
				'inactive'             => 0,
				'total'                => null,
				'total_month'          => null,
				'organic'              => null,
				'organic_month'        => null,
				'backlinks'            => null,
				'is_noindex'           => null,
				'error_traffic'        => null,
				'error_backlinks'      => null,
				'error_position'       => null,
				'position_need_update' => 1,
				'keywords_need_update' => 1,
				'kw_gsc'               => null,
				'kw_idf'               => null,
			],
			[
				'snapshot_id' => $this->snapshot_id,
				'post_id'     => $post_id,
			],
			[ '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Return snapshot ID for current content audit.
	 *
	 * @since 0.7.3
	 *
	 * @return null|int Snapshot ID.
	 */
	public function get_snapshot_id() : ?int {
		return $this->snapshot_id;
	}

	/**
	 * Get min waiting time before next content audit can run.
	 *
	 * @since 0.7.3
	 *
	 * @return float|null
	 */
	public function get_waiting_time() : ?float {
		return $this->workers_waiting_time;
	}

	/**
	 * Stop audit because of some permanent reason
	 *
	 * @since 0.7.5
	 *
	 * @param Message[] $messages
	 * @param bool|null $scheduled_audit These messages are about scheduled audit.
	 * @return void
	 */
	public static function audit_stop( array $messages, ?bool $scheduled_audit = null ) : void {
		$snapshot_id = ( new Content_Audit() )->get_snapshot_id();
		if ( $snapshot_id && is_null( $scheduled_audit ) ) {
			$scheduled_audit = ( new Snapshot() )->is_scheduled_audit( $snapshot_id );
		}

		if ( $scheduled_audit ) { // update message and save all messages.
			$time   = $snapshot_id ? ( new Snapshot() )->get_start_time( $snapshot_id ) : time();
			$prefix = sprintf( 'Your scheduled audit %s did not run as planned. ', ! empty( $time ) ? 'on ' . gmdate( 'd F Y', $time ) : '' );
			foreach ( $messages as $message ) {
				$message->add_message_prefix( $prefix );
			}
		} else { // save only compatibility errors for manual content audit.
			$messages = array_filter(
				$messages,
				function( $message ) {
					return $message instanceof Message_Tip_Incompatible || $message instanceof Message_Error || $message instanceof Message_Error_Single;
				}
			);
		}
		if ( $scheduled_audit ) {
			update_option( self::OPTION_AUDIT_STOP_REASON_SCHEDULED, $messages );
		} else {
			update_option( self::OPTION_AUDIT_STOP_REASON, $messages );
			update_option( self::OPTION_AUDIT_STOPPED, 1 );
		}
	}

	/**
	 * Is audit paused because of some permanent reason?
	 *
	 * @since 0.7.5
	 *
	 * @return bool
	 */
	public static function audit_is_paused() : bool {
		return ! empty( get_option( self::OPTION_AUDIT_STOPPED ) );
	}

	/**
	 * Get reasons of paused audit.
	 *
	 * @since 0.7.5
	 *
	 * @param bool $with_scheduled_audit Return scheduled audit messages too.
	 * @return Message[]|null
	 */
	public static function audit_get_paused_messages( bool $with_scheduled_audit = false ) : ?array {
		$results = [];
		$result  = get_option( self::OPTION_AUDIT_STOP_REASON );
		if ( is_array( $result ) ) {
			$results = $result;
		}
		if ( $with_scheduled_audit ) {
			$result = get_option( self::OPTION_AUDIT_STOP_REASON_SCHEDULED );
			if ( is_array( $result ) ) {
				$results = array_merge( $result, $results );
			}
		}
		$results = array_filter(
			$results,
			function( $item ) {
				return $item instanceof Message;
			}
		);
		return ! empty( $results ) ? $results : null;
	}

	/**
	 * Unpause audit
	 *
	 * @since 0.7.5
	 *
	 * @return void
	 */
	public static function audit_clean_pause() : void {
		self::audit_clean_scheduled_message();
		update_option( self::OPTION_AUDIT_STOP_REASON, null );
		update_option( self::OPTION_AUDIT_STOPPED, null );

		$snapshot_id = ( new Content_Audit() )->get_snapshot_id();
		if ( $snapshot_id ) {
			( new Snapshot() )->on_audit_clean_pause( $snapshot_id );
		}
	}
	/**
	 * Clean scheduled audit message
	 *
	 * @since 0.7.5
	 * @return void
	 */
	public static function audit_clean_scheduled_message() : void {
		update_option( self::OPTION_AUDIT_STOP_REASON_SCHEDULED, null );
	}

	/**
	 * Try to resume audit. Will check for compatibility issues
	 *
	 * @since 0.7.5
	 *
	 * @return bool True if audit resumed.
	 */
	public static function audit_resume() : bool {
		if ( Ahrefs_Seo_Errors::has_stop_error( true ) ) {
			return false;
		}
		if ( ! Ahrefs_Seo_Compatibility::quick_compatibility_check() ) { // it will set OPTION_AUDIT_STOP_REASON if fail.
			return false;
		}
		self::audit_clean_pause();
		return true;
	}

	/**
	 * Is audit delayed (some or all workers are paused)?
	 *
	 * @since 0.7.5
	 *
	 * @return bool True if delayed.
	 */
	public static function audit_is_delayed() : bool {
		return Worker_Any::get_max_waiting_time() > 35;
	}
}
