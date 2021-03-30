<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Create or update DB structure when plugin activating or DB version changed.
 */
class Ahrefs_Seo_Db {

	/**
	 * Create or update DB tables
	 *
	 * @param int $previous_version
	 * @return bool Successful update
	 */
	public static function create_table( int $previous_version ) : bool {
		global $wpdb;
		$result          = true;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_content_exists  = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->ahrefs_content}';" );
		$unique_post_id_exists = false;
		if ( $table_content_exists ) {
			$info                  = $wpdb->get_row( "SHOW INDEX FROM {$wpdb->ahrefs_content} where key_name = 'post_id'", ARRAY_A );
			$unique_post_id_exists = empty( $info['Non_unique'] );
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ahrefs_seo_keywords';" )
		|| $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ahrefs_seo_backlinks';" )
		|| $unique_post_id_exists ) {
			$previous_version = 1; // force update if old table exists or has old indexes.
		}

		if ( $table_content_exists ) {
			$len = $wpdb->get_col_length( $wpdb->ahrefs_content, 'keyword' );
			if ( ! empty( $len ) && is_array( $len ) && isset( $len['length'] ) && $len['length'] > 191 ) { // need to update column length.
				$wpdb->query( "ALTER TABLE {$wpdb->ahrefs_content} CHANGE `keyword` `keyword` VARCHAR(191) DEFAULT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- this is plugin table.
				$wpdb->flush();
			}
		}
		if ( $previous_version > 0 && $previous_version < 57 ) { // do a full reset and run wizard again.
			self::reset_before_v0_7();
		}

		$sql = "CREATE TABLE {$wpdb->ahrefs_content} (
		`post_id` bigint(20) unsigned NOT NULL,
		`snapshot_id` int(10) unsigned NOT NULL DEFAULT 0,
		`total` int(10) DEFAULT NULL,
		`total_month` int(10) DEFAULT NULL,
		`organic` int(10) DEFAULT NULL,
		`organic_month` int(10) DEFAULT NULL,
		`backlinks` int(10) DEFAULT NULL,
		`position` float DEFAULT NULL,
		`action` enum('added_since_last','noindex','manually_excluded','out_of_scope','newly_published','error_analyzing','do_nothing','update_yellow','merge','exclude','update_orange','delete','analyzing','analyzing_initial','out_of_scope_initial','analyzing_final','out_of_scope_analyzing') NOT NULL DEFAULT 'added_since_last',
		`inactive` tinyint(1) NOT NULL DEFAULT '0',
		`is_excluded` tinyint(1) NOT NULL DEFAULT '0',
		`is_included` tinyint(1) NOT NULL DEFAULT '0',
		`is_noindex` tinyint(1) DEFAULT NULL,
		`ignore_newly` tinyint(1) NOT NULL DEFAULT '0',
		`is_approved_keyword` tinyint(1) NOT NULL DEFAULT '0',
		`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		`tries` smallint(5) unsigned NOT NULL DEFAULT '0',
		`error_traffic` text NULL DEFAULT NULL,
		`error_backlinks` text NULL DEFAULT NULL,
		`error_position` text NULL DEFAULT NULL,
		`keyword` varchar(191) DEFAULT NULL,
		`keyword_manual` varchar(191) DEFAULT NULL,
		`position_need_update` tinyint(1) NOT NULL DEFAULT '1',
		`keywords_need_update` tinyint(1) NOT NULL DEFAULT '1',
		`kw_gsc` text DEFAULT NULL,
		`kw_idf` text DEFAULT NULL,
		KEY `post_id` (`post_id`),
		UNIQUE KEY `snapshot_and_post_id` (`snapshot_id`, `post_id`),
		KEY `snapshot_id` (`snapshot_id`),
		KEY `keyword` (`keyword`),
		KEY `inactive` (`inactive`),
		KEY `action` (`action`)
		) $charset_collate;";
		$s   = dbDelta( $sql );
		if ( ! empty( $wpdb->last_error ) && ! self::retry_table_update( $sql ) ) {
			$message = 'Unable to create or update Content table.';
			$error   = $wpdb->last_error;
			Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( sprintf( '%s [%s] [%s] [%s]', $message, $error, wp_json_encode( $sql ), wp_json_encode( $s ) ) ) );
			Ahrefs_Seo_Errors::save_message( 'database', "$message $error", 'error' );
			Ahrefs_Seo::set_fatal_error( 'Fatal error on DB tables update. ' . $error );
			return false;
		}

		$sql = "CREATE TABLE {$wpdb->ahrefs_snapshots} (
		`snapshot_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`time_start` datetime,
		`time_end` datetime DEFAULT NULL,
		`snapshot_status` enum('new','current','old') NOT NULL DEFAULT 'new',
		`traffic_median` float DEFAULT NULL,
		`quick_update_traffic_allowed` tinyint(1) NOT NULL DEFAULT '1',
		`snapshot_version` tinyint(1) unsigned NOT NULL DEFAULT 1,
		`require_update` tinyint(1) unsigned NOT NULL DEFAULT 0,
		`rules_version` tinyint(1) unsigned NOT NULL DEFAULT 4,
		`snapshot_type` enum('manual','scheduled','scheduled_restarted','manual_finished','scheduled_finished') NOT NULL DEFAULT 'manual',
		PRIMARY KEY  (`snapshot_id`),
		KEY `snapshot_status` (`snapshot_status`)
		) $charset_collate;";
		$s   = dbDelta( $sql );
		if ( ! empty( $wpdb->last_error ) && ! self::retry_table_update( $sql ) ) {
			$message = 'Unable to create or update Snapshots table.';
			$error   = $wpdb->last_error;
			Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( sprintf( '%s [%s] [%s] [%s]', $message, $error, wp_json_encode( $sql ), wp_json_encode( $s ) ) ) );
			Ahrefs_Seo_Errors::save_message( 'database', "$message $error", 'error' );
			Ahrefs_Seo::set_fatal_error( 'Fatal error on DB tables update. ' . $error );
			$result = false;
		}
		return $result;
	}

	/**
	 * Retry table update
	 *
	 * @since 0.7.4
	 *
	 * @param string $sql
	 * @return bool
	 */
	private static function retry_table_update( string $sql ) : bool {
		global $wpdb;
		$last_error      = $wpdb->last_error;
		$charset_collate = $wpdb->get_charset_collate();
		if ( strpos( $last_error, "[Unknown character set: 'utf']" ) ) {
			$original_sql = $sql;
			$sql          = str_replace( 'utf-8', 'utf8mb4', $original_sql );
			$s            = dbDelta( $sql );
			Ahrefs_Seo::breadcrumbs( 'Additional SQL error: ' . ( $wpdb->last_error ? "{$wpdb->last_error} [$sql]" : 'None' ) );
			if ( ! empty( $wpdb->last_error ) ) {
				Ahrefs_Seo::notify( new Ahrefs_Seo_Exception( sprintf( '%s [%s] [%s] [%s]', 'Create table failed', $wpdb->last_error, wp_json_encode( $sql ), wp_json_encode( $s ) ) ) );
				$sql = str_replace( $charset_collate, '', $original_sql ); // do not use it at all.
				dbDelta( $sql );
				Ahrefs_Seo::breadcrumbs( 'Additional SQL error: ' . ( $wpdb->last_error ? "{$wpdb->last_error} [$sql]" : 'None' ) );
				return empty( $wpdb->last_error );
			}
		} elseif ( strpos( $last_error, 'Deadlock found when trying to get lock' ) ) {
			Ahrefs_Seo::usleep( 750000 );
			dbDelta( $sql );
			return empty( $wpdb->last_error );
		}
		return false;
	}

	/**
	 * Do a full reset of options and tables.
	 * Will run Wizard again.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	private static function reset_before_v0_7() : void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		// remove old tables.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ahrefs_seo_keywords';" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}ahrefs_seo_keywords" );
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ahrefs_seo_blacklist';" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}ahrefs_seo_blacklist" );
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}ahrefs_seo_backlinks';" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->prefix}ahrefs_seo_backlinks" );
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->ahrefs_content}';" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->ahrefs_content}" );
		}
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->ahrefs_snapshots}';" ) ) {
			$wpdb->query( "DROP TABLE {$wpdb->ahrefs_snapshots}" );
		}
		self::remove_pre_v0_7_options();
		self::reset_google_accounts();
		self::force_wizard_run();
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Remove old options from New Backlinks screen.
	 *
	 * @since 0.7
	 *
	 * @return void
	 */
	private static function remove_pre_v0_7_options() : void {
		$options        = [
			// blacklist.
			'ahrefs-seo-blacklist-has-new',
			'ahrefs-seo-blacklist-last-blacklisted-items',
			'ahrefs-seo-blacklist-last-blacklisted-count',
			// new backlinks.
			'ahrefs-seo-last-backlinks-retrieval',
			'ahrefs-seo-backlinks-low_memory',
			'ahrefs-seo-update-backlink-interval',
			'ahrefs-seo-update-backlink-update-links-count',
			'ahrefs-seo-update-backlinks-has-new-links',
			'ahrefs-seo-links-last-time-scheduled',
			'ahrefs-seo-update-backlink-wizard-finished',
			'ahrefs-seo-update-backlink-i-from',
			'ahrefs-seo-update-backlink-i-to',
			'ahrefs-seo-update-backlink-d-from',
			'ahrefs-seo-update-backlink-d-to',
			'ahrefs-seo-update-backlink-d-last',
			// notice.
			'ahrefs-seo-count-prev-time',
			'ahrefs-seo-has-new-links',
			'ahrefs-seo-admin-notice-hide-gsc',
			// wizard.
			'ahrefs-seo-wizard-audit-start',
		];
		$all_dofollow   = [ '', 'dofollow', 'nofollow' ];
		$all_group_mode = [ '', 'similar', 'domain' ];
		// fill all possible notice combinations.
		foreach ( $all_dofollow as $dofollow ) {
			foreach ( $all_group_mode as $group_mode ) {
				$options[] = self::links_count_key( true, $dofollow, $group_mode ); // key_today.
				$options[] = self::links_count_key( false, $dofollow, $group_mode ); // key_yesterday.
				$options[] = self::links_count_key( null, $dofollow, $group_mode ); // key_difference.
			}
		}
		array_walk(
			$options,
			function( $value ) {
				delete_option( $value );
			}
		);
	}

	/**
	 * Reset currently selected Google GA and GCS accounts.
	 * Do not reset existing tokens.
	 *
	 * @since 0.7.1
	 *
	 * @return void
	 */
	private static function reset_google_accounts() : void {
		$analytics = Ahrefs_Seo_Analytics::get();
		if ( $analytics->is_token_set() ) {
			$analytics->set_ua( '', '', '', '' );
			wp_cache_flush();
		}
	}

	/**
	 * Helper function, create name of option using option type
	 *
	 * @param bool   $is_today
	 * @param string $dofollow
	 * @param string $group_mode
	 * @return string
	 */
	private static function links_count_key( ?bool $is_today, string $dofollow, string $group_mode ) : string {
		$today = is_null( $is_today ) ? 'diff' : ( $is_today ? 'curr' : 'prev' );
		return 'ahrefs-seo-count-' . implode( '-', [ $today, $dofollow ?: 'all', $group_mode ?: 'all' ] );
	}

	/**
	 * Force Wizard run
	 *
	 * @since 0.7
	 */
	protected static function force_wizard_run() : void {
		$options = [ '1', '2', '21', '3', '4' ];
		array_walk(
			$options,
			function( string $option ) : void {
				delete_option( "ahrefs-seo-is-initialized$option" );
			}
		);
		delete_option( 'ahrefs-seo-wizard-1-step' );
	}

}
