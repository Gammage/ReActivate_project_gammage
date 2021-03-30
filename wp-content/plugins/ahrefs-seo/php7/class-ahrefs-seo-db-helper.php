<?php
declare(strict_types=1);

namespace ahrefs\AhrefsSeo;

/**
 * Helper class for some DB requests.
 */
class Ahrefs_Seo_Db_Helper {
	/**
	 * Check and return updated items from given list.
	 * Note: snapshot_id passed using additional_where parameter.
	 *
	 * @param string          $table_alias 'ahrefs_content' or 'ahrefs_link_rules'.
	 * @param string          $field_id 'post_id' or 'rule_id'.
	 * @param array<int, int> $items Associative array ( (int)rule_id => (int)ver ).
	 * @param string          $additional_where
	 * @return int[] Array of rule_id with updates (ver value changed).
	 */
	public static function get_updated_items( string $table_alias, string $field_id, array $items, string $additional_where = '' ) : array {
		global $wpdb;
		$ids         = array_map( 'absint', array_keys( $items ) );
		$updated_ids = [];

		$placeholder = array_fill( 0, count( $ids ), '%d' );
		$sql         = $wpdb->prepare( "SELECT {$field_id}, UNIX_TIMESTAMP(updated) as 'ver' FROM {$wpdb->$table_alias} WHERE {$field_id} in ( " . implode( ', ', $placeholder ) . ')' . ( $additional_where ? " AND $additional_where" : '' ), $ids ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data        = $wpdb->get_results( $sql, ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// Search ids with updated ver.
		foreach ( (array) $data as $row ) {
			$id  = intval( $row[0] );
			$ver = intval( $row[1] );
			if ( $ver !== $items[ $id ] ) {
				$updated_ids[] = $id;
			}
		}
		return $updated_ids;
	}

	/**
	 * @param int    $snapshot_id
	 * @param string $type
	 * @param string $category
	 * @param string $search_string
	 * @return array<\stdClass> results as OBJECT
	 */
	public static function content_data_get_clear_months( int $snapshot_id, string $type, string $category, string $search_string = '' ) : array {
		global $wpdb;
		$additional_where   = [ " AND p.post_status = 'publish' " ];
		$additional_where[] = "AND ( p.post_type IN ('post','page') )";
		if ( '' !== $search_string ) {
			$search             = '%' . $wpdb->esc_like( $search_string ) . '%';
			$additional_where[] = $wpdb->prepare(
				' AND p.post_title LIKE %s ',
				$search
			);
		}

		$additional_where = implode( ' ', $additional_where );

		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month FROM {$wpdb->ahrefs_content} as c, {$wpdb->posts} as p WHERE snapshot_id = %d AND c.post_id = p.ID $additional_where ORDER BY post_date DESC", $snapshot_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
