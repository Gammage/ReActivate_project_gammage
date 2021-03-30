<?php

namespace ahrefs\AhrefsSeo;

/*
We can have 3 types of keywords by source (db ahrefs_seo_content table, keyword_source field):
- up to 10 proposed by gsc: "gsc" badge in popup dialog;
- up to 5 proposed by tf-idf: "tf-idf" badge;
- assigned manually: input field in popup dialog.

We run bulk keywords update:
- on initial wizard, at last step;
- on each audit (when new snapshot created);

We update keywords of each single post when keyword is not approved by user.

Time:
- When keywords received from GSC we do 2-3 requests (case A below) or 0-1 request (case B below);
- We are doing at pauses between requests (Ahrefs_Seo_Analytics::API_MIN_DELAY, counting from previous request started time);
- GSC can have a big delay before API returned data on first request (it seems it cacne results and next requests are much faster).

How is GSC working now?
A. Common update, when we load detail during content audit or receive updated keywords suggestions (on keywords popup opened):
When we load keyword suggestions from GSC we make up to 3 queries:
- get total clicks, positions and impressions - this required, as we must show values in percents;
- get top 10 results - what we show at the keywords popup. Search current keyword here, if not found then:
- get clicks, positions and impressions exactly for current keyword.

B, Fast update, hen user set new keyword:
- we try to load position from cached gsc result (from last query)
- if not found, then make single request using filter with current keyword and update position value (do not cache result).
This is working much faster, that common update (with 2 or 3 queries).
But we do not have impressions. clicks and percents values. If "Change" link is clicked again, we will load all the details and update keywords table with fresh results (using case A).


Summary:
We do NOT update keywords using automated results when they already set by user ("is_approved_keyword" is set).
GSC or TF-IDF can propose another keywords in different times (when GSC will have new click details or more new posts added for TF-IDF).

Note: we must add any inactive post (not added at the Wizard or created later) post to content table before assign keywords or use cached suggestions.
*/
/**
 * Wrapper class for keywords features.
 */
class Ahrefs_Seo_Keywords {

	/**
	 * @var array
	 */
	private $all_posts = [];
	/**
	 * @var array
	 */
	private $all_keywords = [];
	/**
	 * @var Ahrefs_Seo_Keywords
	 */
	private static $instance;
	/**
	 * @var array<int, string>
	 */
	private $keywords_source_cache = [];
	/** Allow create new keywords once per seconds. */
	const KEY_MIN_DELAY = 0.05;
	/** @var float Time when last keyword created. */
	private $last_query_time = 0;
	/**
	 * @var Ahrefs_Seo_Data_Content
	 */
	private $data_content;
	/**
	 * @var Ahrefs_Seo_Analytics
	 */
	private $analytics;
	/**
	 * Return the instance
	 *
	 * @param Ahrefs_Seo_Analytics|null $analytics Analytics instance for use in requests to GSC.
	 * @return Ahrefs_Seo_Keywords
	 */
	public static function get( Ahrefs_Seo_Analytics $analytics = null ) {
		if ( empty( self::$instance ) ) {
			self::$instance = new self( Ahrefs_Seo_Data_Content::get() );
		}
		if ( ! is_null( $analytics ) ) {
			self::$instance->analytics = $analytics;
		}
		if ( is_null( self::$instance->analytics ) ) { // @phpstan-ignore-line
			self::$instance->analytics = Ahrefs_Seo_Analytics::get();
		}
		return self::$instance;
	}
	public function __construct( Ahrefs_Seo_Data_Content $data_content ) {
		$this->data_content = $data_content;
	}
	/**
	 * Get keywords assigned to post
	 *
	 * @param int $snapshot_id
	 * @param int $post_id
	 * @return string|null Assigned keyword if any.
	 */
	public function post_keyword_get( $snapshot_id, $post_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT keyword FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d", $post_id, $snapshot_id ) );
	}
	public function post_keyword_manual_get( $snapshot_id, $post_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT keyword_manual FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d", $post_id, $snapshot_id ) );
	}
	/**
	 * Is this post require keywords update?
	 *
	 * @param int $snapshot_id
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function post_keywords_need_update( $snapshot_id, $post_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT keywords_need_update FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d LIMIT 1", $post_id, $snapshot_id ) );
		return ! empty( $result );
	}
	/**
	 * Is this post keywords is approved?
	 *
	 * @param int $snapshot_id
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function post_keywords_is_approved( $snapshot_id, $post_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT is_approved_keyword FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d LIMIT 1", $post_id, $snapshot_id ) );
		return ! empty( $result );
	}
	/**
	 * Reset keywords need update flag.
	 *
	 * @param int $snapshot_id
	 * @param int $post_id
	 * @return void
	 */
	public function post_keywords_set_updated( $snapshot_id, $post_id ) {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%d)', __METHOD__, $post_id ) );
		$sql = $wpdb->prepare( "UPDATE {$wpdb->ahrefs_content} SET keywords_need_update = 0 WHERE post_id = %d AND snapshot_id = %d", $post_id, $snapshot_id );
		if ( 0 === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->maybe_add_post_and_retry_query( $snapshot_id, $post_id, $sql );
		}
	}
	/**
	 * Set post keywords (selected and user keyword).
	 * Update position (using cached results from GSC) or set a flag for an update.
	 * Set flag for keywords update if keyword is null.
	 * Will update article recommended action.
	 *
	 * @param int         $snapshot_id Snapshot id.
	 * @param int         $post_id Post ID.
	 * @param string|null $keyword Post keyword.
	 * @param string|null $keyword_manual Keywords from manual input field.
	 * @param bool        $reanalyze_everything Update action for current post and other active posts with same keywords (before and after update).
	 * @return bool
	 */
	public function post_keywords_set( $snapshot_id, $post_id, $keyword = null, $keyword_manual = null, $reanalyze_everything = true ) {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( sprintf( '%s (%d) (%d) (%s) (%s)', __METHOD__, $snapshot_id, $post_id, $keyword, $keyword_manual ) );
		if ( ! is_null( $keyword ) && strlen( $keyword ) > 191 ) {
			$keyword = function_exists( 'mb_substr' ) ? mb_substr( $keyword, 0, 191 ) : substr( $keyword, 0, 191 );
		}
		if ( 'publish' === get_post_status( $post_id ) ) { // only for existing and published posts.
			$values                    = [];
			$place_holders             = [];
			$position                  = null; // position value for newly set keyword.
			$keyword_old               = $this->post_keyword_get( $snapshot_id, $post_id );
			$update_same_keyword_posts = $reanalyze_everything && $keyword_old !== $keyword;
			// need to reanalyze posts with same keyword as current (using keyword before update).
			$posts_reanalyze_old = $update_same_keyword_posts ? Ahrefs_Seo_Advisor::get()->find_active_pages_with_same_keyword( $snapshot_id, $post_id ) : [];
			if ( ! is_null( $keyword ) ) {
				// try to update position with fresh value, if exists in cached data.
				$position = $this->load_position_from_cache( $snapshot_id, $post_id, $keyword );
			}
			$fields = [
				'keyword'              => $keyword,
				'position'             => $position,
				'position_need_update' => is_null( $position ) ? 1 : 0,
				'keywords_need_update' => is_null( $keyword ) ? 1 : 0,
			];
			$format = [ '%s', '%f', '%d', '%d' ];
			if ( ! is_null( $keyword_manual ) ) {
				$fields['keyword_manual'] = $keyword_manual;
				$format[]                 = '%s';
			}
			$query_result = $wpdb->update(
				$wpdb->ahrefs_content,
				$fields,
				[
					'post_id'     => $post_id,
					'snapshot_id' => $snapshot_id,
				],
				$format,
				[ '%d', '%d' ]
			);
			if ( 0 === $query_result ) {
				$this->maybe_add_post_and_retry_query( $snapshot_id, $post_id, $wpdb->last_query );
			} elseif ( false === $query_result ) {
				$this->post_keywords_set_updated( $snapshot_id, $post_id );
				Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( sprintf( 'Query failed (keywords set) (%s) (%s) (%s) (%s)', wp_json_encode( func_get_args() ), wp_json_encode( $fields ), $wpdb->last_query, $wpdb->last_error ) ) );
			}
			$content_audit = new Content_Audit_Current( $snapshot_id );
			if ( is_null( $position ) && $reanalyze_everything ) {
				// update position immediately using fast update only when reanalyze_everything required. Because this additional API call may be a reason of possible rate error.
				$content_audit->update_post_info_position( [ $post_id ], true );
			}
			if ( $reanalyze_everything ) {
				// update recommended action.
				$content_audit->reanalyze_post( $post_id );
			}
			// need to reanalyze posts with same keyword as current (using keyword after update).
			$posts_reanalyze_new = $update_same_keyword_posts && '' !== $keyword ? Ahrefs_Seo_Advisor::get()->find_active_pages_with_same_keyword( $snapshot_id, $post_id ) : [];
			$posts_reanalyze     = array_merge( isset( $posts_reanalyze_old ) ? $posts_reanalyze_old : [], isset( $posts_reanalyze_new ) ? $posts_reanalyze_new : [] );
			if ( ! empty( $posts_reanalyze ) ) {
				$posts_reanalyze = array_unique( $posts_reanalyze );
				foreach ( $posts_reanalyze as $post_id ) {
					// update recommended action.
					$content_audit->reanalyze_post( $post_id );
				}
			}
			return true;
		}
		return false;
	}
	/**
	 * Check, add new inactive post to content table and retry initial sql query.
	 *
	 * Newly added posts or initially inactive posts are missing at the content table.
	 * Must add post before use it, otherwise update keywords or cached suggestions do not work.
	 *
	 * @param int    $snapshot_id
	 * @param int    $post_id
	 * @param string $sql
	 *
	 * @return void
	 */
	private function maybe_add_post_and_retry_query( $snapshot_id, $post_id, $sql ) {
		global $wpdb;
		Ahrefs_Seo::breadcrumbs( __METHOD__ . " ({$snapshot_id}) ({$post_id}) ({$sql})" );
		// maybe this post was not added to content table at all?
		if ( is_null( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d LIMIT 1", $post_id, $snapshot_id ) ) ) ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ahrefs_content} ( snapshot_id, post_id, action, inactive ) VALUES ( %d, %d, %s, 1 ) ON DUPLICATE KEY UPDATE action = %s, inactive = 1", $snapshot_id, $post_id, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST, Ahrefs_Seo_Data_Content::ACTION4_ADDED_SINCE_LAST ) );
			// retry query.
			if ( '' !== $sql && 0 === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- we already tried to execute this sql code before, this time we just retry it.
				Ahrefs_Seo::notify( new \Exception( sprintf( 'SQL query failed again (%s)', $sql ) ) );
			}
		}
	}
	/**
	 *
	 * @param int $post_id
	 * @param int $keywords_limit
	 */
	private function create_single_post_keywords( $post_id, $keywords_limit = 50 ) {
		$data = $this->create_posts_keywords( [ $post_id ] );
		return isset( $data[ $post_id ] ) ? $data[ $post_id ] : null;
	}
	/**
	 * Create keywords for posts using TF-IDF, works without GSC access.
	 *
	 * @param int[] $posts_ids
	 * @param int   $keywords_limit How many keywords return for each post.
	 *
	 * @return array<int, null|array<string, string|float>> Associative array [ post_id => [[ q => keyword, f => feature_score ],... ] ] or null.
	 */
	private function create_posts_keywords( array $posts_ids, $keywords_limit = 10 ) {
		$result = [];
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		unset( $this->all_keywords );
		$this->maybe_do_a_pause();
		// run research.
		$keywords_search    = new Keywords_Search( $posts_ids, $keywords_limit );
		$this->all_keywords = $keywords_search->get_all_keywords();
		unset( $keywords_search );
		foreach ( $posts_ids as $post_id ) {
			$result[ $post_id ] = isset( $this->all_keywords[ $post_id ] ) ? $this->all_keywords[ $post_id ] : null;
		}
		return $result;
	}
	/**
	 * Do a minimal delay between requests.
	 * Used to prevent 504 errors.
	 */
	private function maybe_do_a_pause() {
		$time_since = microtime( true ) - $this->last_query_time;
		if ( $time_since < self::KEY_MIN_DELAY && ! defined( 'AHREFS_SEO_IGNORE_DELAY' ) ) {
			Ahrefs_Seo::usleep( intval( ceil( self::KEY_MIN_DELAY - $time_since ) * 1000000 ) );
		}
		$this->last_query_time = microtime( true );
	}
	/**
	 * Update position of current keyword in cached gsc data.
	 *
	 * @since 0.7.4
	 *
	 * @param int   $snapshot_id
	 * @param int   $post_id
	 * @param array $row
	 * @return void
	 */
	public function update_position_cache( $snapshot_id, $post_id, array $row ) {
		$cached = $this->get_cached_detail_for_posts( $snapshot_id, [ $post_id ] );
		if ( is_array( $cached[ $post_id ] ) ) {
			$kw_gsc = isset( $cached[ $post_id ]['keywords'] ) ? $cached[ $post_id ]['keywords'] : [];
			$kw_idf = $cached[ $post_id ]['keywords2'];
			if ( ! isset( $kw_gsc['result'] ) ) {
				$kw_gsc['result'] = [];
			}
			$found = false;
			foreach ( $kw_gsc['result'] as $key => $item ) {
				if ( $item['query'] === $row['query'] ) {
					$kw_gsc['result'][ $key ] = $row;
					$found                    = true;
					break;
				}
			}
			if ( ! $found ) {
				$kw_gsc['result'][] = $row;
			}
			$this->set_cached_suggestions( $snapshot_id, $post_id, $kw_gsc, $kw_idf );
		}
	}
	/**
	 * Get cached details for post
	 *
	 * @param int   $snapshot_id
	 * @param int[] $post_ids
	 *
	 * @return array<int, array<string, mixed>|null>
	 */
	protected function get_cached_detail_for_posts( $snapshot_id, array $post_ids ) {
		$results = [];
		foreach ( $post_ids as $post_id ) {
			$result = null;
			if ( 'publish' === get_post_status( $post_id ) ) { // only for existing and published posts.
				$result = [
					'keyword'        => $this->post_keyword_get( $snapshot_id, $post_id ),
					'keyword_manual' => $this->post_keyword_manual_get( $snapshot_id, $post_id ),
				];
				list($result['keywords'], $result['keywords2']) = $this->get_cached_suggestions( $snapshot_id, $post_id );
			}
			$results[ $post_id ] = $result;
		}
		return $results;
	}
	/**
	 * Get cached keywords suggestions (results returned by GSC or created by idf).
	 *
	 * @param int $snapshot_id
	 * @param int $post_id
	 * @return array<array|null>
	 */
	private function get_cached_suggestions( $snapshot_id, $post_id ) {
		global $wpdb;
		$fields = $wpdb->get_row( $wpdb->prepare( "SELECT kw_gsc, kw_idf from {$wpdb->ahrefs_content} WHERE post_id = %d AND snapshot_id = %d ", $post_id, $snapshot_id ), ARRAY_A );
		if ( ! empty( $fields ) ) {
			return [ is_null( $fields['kw_gsc'] ) ? null : json_decode( $fields['kw_gsc'], true ), is_null( $fields['kw_idf'] ) ? null : json_decode( $fields['kw_idf'], true ) ];
		}
		return [ null, null ];
	}
	/**
	 * Set cached suggestions
	 *
	 * @param int   $snapshot_id
	 * @param int   $post_id
	 * @param array $keywords
	 * @param array $keywords2
	 * @return void
	 */
	private function set_cached_suggestions( $snapshot_id, $post_id, array $keywords = null, array $keywords2 = null ) {
		global $wpdb;
		if ( isset( $keywords['error'] ) ) {
			unset( $keywords['error'] ); // it make no sense to cache errors.
		}
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->ahrefs_content} SET kw_gsc = %s, kw_idf = %s, updated=updated WHERE post_id = %d AND snapshot_id = %d ", // do not change 'updated' value.
			wp_json_encode( $keywords ),
			wp_json_encode( $keywords2 ),
			$post_id,
			$snapshot_id
		);
		if ( 0 === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->maybe_add_post_and_retry_query( $snapshot_id, $post_id, $sql );
		}
	}
	/**
	 * Get current keyword and keywords data from GSC or TF-IDF.
	 *
	 * @param int   $snapshot_id
	 * @param int[] $post_ids Post ID list.
	 * @param int   $limit
	 * @param bool  $without_totals Do not make additional query for total values.
	 * @param bool  $gsc_only Do not use tf-idf.
	 *
	 * @return array<int, mixed> [ post_id => [ results ]] or [ post_id => [error => string] ] on error.
	 */
	public function get_full_detail_for_posts( $snapshot_id, array $post_ids, $limit = null, $without_totals = false, $gsc_only = false ) {
		Ahrefs_Seo::breadcrumbs( sprintf( '%s %s', __METHOD__, wp_json_encode( func_get_args() ) ) );
		$start_date = '';
		$end_date   = '';
		$this->get_time_period( $start_date, $end_date );
		$days_count       = 0;
		$results          = [];
		$requests         = [];
		$request_keywords = [];
		foreach ( $post_ids as $post_id ) {
			$result = [
				'id'             => $post_id,
				'keyword'        => null,
				'keyword_manual' => null,
			]; // return the post field value back.
			if ( 'publish' === get_post_status( $post_id ) ) { // only for existing and published posts.
				$result['keyword']        = $this->post_keyword_get( $snapshot_id, $post_id );
				$result['keyword_manual'] = $this->post_keyword_manual_get( $snapshot_id, $post_id );
				$url                      = apply_filters( 'ahrefs_seo_search_traffic_url', get_permalink( $post_id ) );
				$result['params']         = [ $url, $start_date, $end_date ];
				if ( $this->analytics->is_gsc_set() ) {
					$requests[ $post_id ]         = $url;
					$request_keywords[ $post_id ] = $result['keyword'] ?: null;
				} else {
					$error           = 'Google Search Console is not connected.';
					$result['error'] = $error;
					$result['messages']['Google Search Console API error'] = $error;
				}
				$result['keywords2'] = $this->create_single_post_keywords( $post_id ); // do not apply limit here.
				$this->set_cached_suggestions( $snapshot_id, $post_id, isset( $result['keywords'] ) ? $result['keywords'] : null, isset( $result['keywords2'] ) ? $result['keywords2'] : null );
				$result['url'] = $url;
			} else {
				$result['error'] = 'This post cannot be found. It is possible that you’ve archived the post or changed the post ID. Please reload the page & try again.';
			}
			$results[ $post_id ] = $result;
		}
		// make single call for all pending urls.
		if ( ! empty( $requests ) ) {
			$clicks = $this->analytics->get_clicks_and_impressions_by_urls( $requests, $start_date, $end_date, $limit, $without_totals, $request_keywords );
			// update results for each post id.
			foreach ( $requests as $post_id => $url ) {
				$results[ $post_id ]['keywords'] = is_array( $clicks ) ? isset( $clicks[ $post_id ] ) ? $clicks[ $post_id ] : null : null;
				if ( empty( $results[ $post_id ] ) && ! $gsc_only ) { // call it only if allowed.
					$error = $this->analytics->get_message();
					if ( $error ) {
						$results[ $post_id ]['error']                                       = $error;
						$results[ $post_id ]['messages']['Google Search Console API error'] = $error;
					}
				}
				// set fresh cached values.
				$this->set_cached_suggestions( $snapshot_id, $post_id, isset( $results[ $post_id ]['keywords'] ) ? $results[ $post_id ]['keywords'] : null, isset( $results[ $post_id ]['keywords2'] ) ? $results[ $post_id ]['keywords2'] : null );
			}
		}
		return $results;
	}
	/**
	 * Fill keyword row using cached GSC results
	 *
	 * @param array $cached_results
	 * @param array $keyword_row
	 * @return void
	 */
	private function fill_using_cached_gsc_results( array $cached_results, array &$keyword_row ) {
		$keyword = $keyword_row[1];
		if ( ! empty( $cached_results ) && ! is_null( $keyword ) ) {
			foreach ( $cached_results as $item ) {
				if ( isset( $item['query'] ) && 0 === strcasecmp( $keyword, $item['query'] ) ) {
					$keyword_row[3] = isset( $item['pos'] ) ? $item['pos'] : null;
					$keyword_row[4] = isset( $item['clicks'] ) ? $item['clicks'] : null;
					$keyword_row[5] = isset( $item['impr'] ) ? $item['impr'] : null;
				}
			}
		}
	}
	/**
	 * Return the same data, as 'keywords-list' template expected.
	 *
	 * @param int  $snapshot_id
	 * @param int  $post_id
	 * @param bool $use_cached_data
	 * @return array<string, mixed>
	 */
	public function get_suggestions( $snapshot_id, $post_id, $use_cached_data = false ) {
		$errors                = null;
		$keywords              = [];
		$data                  = ! $use_cached_data ? $this->get_full_detail_for_posts( $snapshot_id, [ $post_id ] ) : $this->get_cached_detail_for_posts( $snapshot_id, [ $post_id ] );
		$data                  = isset( $data[ $post_id ] ) ? $data[ $post_id ] : null;
		$source_gsc            = false; // source of report is GSC data.
		$total_clicks          = 0;
		$total_impr            = 0;
		$keyword_manual        = isset( $data['keyword_manual'] ) ? $data['keyword_manual'] : null;
		$cached_positions      = [];
		$no_suggestions_found  = false;
		$no_suggestions_cached = false;
		$fallback_to_idf       = ! empty( $data['keywords'] ) && empty( $data['keywords']['result'] );
		$keyword               = isset( $data['keyword'] ) ? $data['keyword'] : '';
		if ( ! empty( $data['keywords'] ) && ! empty( $data['keywords']['result'] ) ) {
			$cached_positions           = $data['keywords']['result'];
			$data['keywords']['result'] = array_slice( $data['keywords']['result'], 0, 10 ); // use only top 10 items, ordered by clicks.
			$source_gsc   = true;
			$total_clicks = ! empty( $data['keywords']['total_clicks'] ) ? $data['keywords']['total_clicks'] : 1;
			$total_impr   = ! empty( $data['keywords']['total_impr'] ) ? $data['keywords']['total_impr'] : 1;
			foreach ( $data['keywords']['result'] as $item ) {
				if ( isset( $item['query'] ) && is_string( $item['query'] ) ) {
					$keywords[] = [ 0 === strcasecmp( $item['query'], $keyword ), $item['query'], 'gsc', $item['pos'], $item['clicks'], $item['impr'] ];
				}
			}
		}
		// is some keyword already selected? We want to select only single item.
		$item_selected = array_sum(
			array_map(
				function ( $item ) {
					return $item[0] ? 1 : 0;
				},
				$keywords
			)
		) > 0;
		if ( ! empty( $data['keywords2'] ) ) {
			if ( 0 === $total_clicks ) {
				$total_clicks = 1;
			}
			if ( 0 === $total_impr ) {
				$total_impr = 1;
			}
			$data['keywords2'] = array_slice( $data['keywords2'], 0, 5 ); // use only top 5 items.
			foreach ( $data['keywords2'] as $item ) {
				$item = [ ! $item_selected && 0 === strcasecmp( $item['q'], $keyword ), $item['q'], 'tf-idf', null, '-', '-' ];
				$this->fill_using_cached_gsc_results( $cached_positions, $item );
				$keywords[] = $item;
			}
		}
		$item_selected = array_sum(
			array_map(
				function ( $item ) {
					return $item[0] ? 1 : 0;
				},
				$keywords
			)
		) > 0;
		// do we have currently selected item somehere? Keyword is set, but we did not find it at GSC or TF-IDF suggestions and it is not a manual keyword too...
		if ( ! is_null( $keyword ) && ! $item_selected && ( is_null( $keyword_manual ) || 0 !== strcasecmp( $keyword_manual, $keyword ) ) ) {
			// replace manual keyword by selected keyword.
			$keyword_manual = $keyword;
		}
		$item = [ ! $item_selected && ! is_null( $keyword_manual ) && 0 === strcasecmp( $keyword_manual, $keyword ), isset( $keyword_manual ) ? $keyword_manual : '', 'manual', null, '-', '-' ];
		$this->fill_using_cached_gsc_results( $cached_positions, $item );
		$keywords[] = $item;
		if ( empty( $keywords ) ) {
			// show errors.
			if ( $data && ! empty( $data['messages'] ) ) {
				$errors = $data['messages'];
			} else {
				// no keyword suggestions found.
				$no_suggestions_found = true;
			}
		}
		if ( ( ! isset( $data['keywords'] ) || is_null( $data['keywords'] ) ) && ( ! isset( $data['keywords2'] ) || is_null( $data['keywords2'] ) ) ) {
			$no_suggestions_cached = true;
		}
		if ( ! $use_cached_data && ! empty( $data['error'] ) ) {
			$errors = $data['error'];
		}
		return [
			'post_id'      => $post_id,
			'keyword'      => ! is_null( $data ) ? $data['keyword'] : [], // value returned from from cache can be null.
			'keywords'     => $keywords,
			'total_clicks' => $total_clicks,
			'total_impr'   => $total_impr,
			'errors'       => $errors,
		];
	}
	/**
	 * Get time period
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @return void
	 */
	public function get_time_period( &$start_date, &$end_date ) {
		$start_date = date( 'Y-m-d', strtotime( sprintf( '- 3 month' ) ) );
		$end_date   = date( 'Y-m-d' );
	}
	/**
	 * Load position of keyword.
	 *
	 * @param int                   $snapshot_id
	 * @param array<string, string> $url_with_keyword [ url => keyword ] pairs.
	 * @return null|array<string, array<string,mixed>> [ url => [pos=>float, error=>?]] pairs or null if error.
	 */
	public function load_position_value_fast( $snapshot_id, array $url_with_keyword ) {
		$rows = $this->analytics->get_position_fast( $url_with_keyword );
		return $rows;
	}
	/**
	 * Return position for URL and current keyword.
	 * Load top 10 results for the page and result for current keyword.
	 *
	 * @param int                $snapshot_id
	 * @param array<int, string> $post_with_keyword [ post_id => keyword ] pairs.
	 * @return null|array<int, array<string,mixed>> [ post_id => [pos=>float, error=>?]] pairs or null if error.
	 */
	public function load_position_value( $snapshot_id, array $post_with_keyword ) {
		$result   = null;
		$data_all = $this->get_full_detail_for_posts( $snapshot_id, array_keys( $post_with_keyword ), 10, true, true );
		foreach ( $post_with_keyword as $post_id => $current_keyword ) {
			$current_keyword_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $current_keyword ) : strtolower( $current_keyword );
			$data                  = isset( $data_all[ $post_id ] ) ? $data_all[ $post_id ] : null;
			if ( is_array( $data ) && ! empty( $data['keywords'] ) && ! empty( $data['keywords']['result'] ) ) {
				foreach ( $data['keywords']['result'] as $item ) {
					if ( isset( $item['query'] ) && is_string( $item['query'] ) && ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $item['query'] ) : strtolower( $item['query'] ) ) === $current_keyword_lower ) {
						$result[ $post_id ] = $item;
						break;
					}
				}
			}
		}
		return $result;
	}
	/**
	 * Load position of keyword from GSC cache
	 *
	 * @param int    $snapshot_id
	 * @param int    $post_id
	 * @param string $current_keyword
	 * @return float|null Position or null.
	 */
	public function load_position_from_cache( $snapshot_id, $post_id, $current_keyword ) {
		$result                = null;
		list($kw_gsc, $tf_idf) = $this->get_cached_suggestions( $snapshot_id, $post_id );
		if ( ! is_null( $kw_gsc ) && ! empty( $kw_gsc['result'] ) && is_array( $kw_gsc['result'] ) ) {
			$keyword_lower = strtolower( $current_keyword );
			foreach ( $kw_gsc['result'] as $item ) {
				if ( isset( $item['query'] ) && strtolower( $item['query'] ) === $keyword_lower ) {
					$result = isset( $item['pos'] ) ? floatval( $item['pos'] ) : null;
				}
			}
		}
		return $result;
	}
}
