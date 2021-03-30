<?php
/**
 * The Schema module database operations.
 *
 * @since      1.4.3
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use RankMath\Helper;
use MyThemeShop\Helpers\Str;
use MyThemeShop\Database\Database;

defined( 'ABSPATH' ) || exit;

/**
 * DB class.
 */
class DB {

	/**
	 * Get query builder object.
	 *
	 * @param string $table Meta table name.
	 *
	 * @return Query_Builder
	 */
	private static function table( $table = 'postmeta' ) {
		return Database::table( $table );
	}

	/**
	 * Get all schemas.
	 *
	 * @param int    $object_id  Object ID.
	 * @param string $table      Meta table name.
	 *
	 * @return array
	 */
	public static function get_schemas( $object_id, $table = 'postmeta' ) {
		$key  = 'termmeta' === $table ? 'term_id' : 'post_id';
		$data = self::table( $table )
			->select( 'meta_id' )
			->select( 'meta_value' )
			->where( $key, $object_id )
			->whereLike( 'meta_key', 'rank_math_schema', '' )
			->get();

		$schemas = [];
		foreach ( $data as $schema ) {
			$id             = 'schema-' . $schema->meta_id;
			$schemas[ $id ] = maybe_unserialize( $schema->meta_value );
		}

		return $schemas;
	}

	/**
	 * Get all schemas.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array
	 */
	public static function get_schema_types( $post_id ) {
		$schemas = self::get_schemas( $post_id );
		if ( empty( $schemas ) ) {
			return false;
		}

		$types = wp_list_pluck( $schemas, '@type' );
		return implode( ', ', $types );
	}

	/**
	 * Get schema by shortcode id.
	 *
	 * @param  string $id Shortcode unique id.
	 * @return array
	 */
	public static function get_schema_by_shortcode_id( $id ) {
		$data = self::table()
			->select( 'post_id' )
			->select( 'meta_value' )
			->whereLike( 'meta_value', $id )
			->one();

		if ( ! empty( $data ) ) {
			return [
				'post_id' => $data->post_id,
				'schema'  => maybe_unserialize( $data->meta_value ),
			];
		}

		return false;
	}

	/**
	 * Get schema type for template.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function get_template_type( $post_id ) {
		$data = self::table()
			->select( 'meta_value' )
			->where( 'post_id', $post_id )
			->whereLike( 'meta_key', 'rank_math_schema', '' )
			->one();

		if ( empty( $data ) ) {
			return '';
		}

		$schema = maybe_unserialize( $data->meta_value );

		return [
			'type'   => $schema['@type'],
			'schema' => $schema,
		];
	}

	/**
	 * Delete Schema for template.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function delete_schema_data( $post_id ) {
		return self::table()->where( 'post_id', $post_id )->whereLike( 'meta_key', 'rank_math_schema_' )->delete();
	}

	/**
	 * Unpublish job posting when expired.
	 *
	 * @param JsonLD $jsonld  JsonLD Instance.
	 * @param array  $schemas Array of JSON-LD entity.
	 */
	public static function unpublish_jobposting_post( $jsonld, $schemas ) {
		if ( ! is_singular() ) {
			return;
		}

		$job_postings = array_map(
			function( $schema ) {
				return isset( $schema['@type'] ) && 'JobPosting' === $schema['@type'] ? $schema : false;
			},
			$schemas
		);

		if ( empty( $job_postings ) ) {
			return;
		}

		foreach ( $job_postings as $job_posting ) {
			if (
				empty( $job_posting['metadata']['unpublish'] ) ||
				'on' !== $job_posting['metadata']['unpublish'] ||
				empty( $job_posting['validThrough'] ) ||
				date_create( 'now' )->getTimestamp() < strtotime( $job_posting['validThrough'] )
			) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $jsonld->post_id,
					'post_status' => 'draft',
				]
			);

			break;
		}
	}
}
