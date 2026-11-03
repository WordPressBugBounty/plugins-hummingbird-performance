<?php
/**
 * Background Process Status for Hummingbird
 *
 * @package Hummingbird\Core\Modules\Background
 */

namespace Hummingbird\Core\Modules\Background;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TODO: use this in places where we are currently using mutex
 */
class Thread_Safe_Options {

	public function delete_option( $option_id ) {
		return $this->delete( $option_id );
	}

	public function delete_site_option( $option_id ) {
		return $this->delete( $option_id, true );
	}

	private function delete( $option_id, $site_option = false ) {
		global $wpdb;

		list( $table, $column ) = $this->get_table_columns( $site_option );

		return $wpdb->delete( $table, array(
			$column => $option_id,
		), '%s' );
	}

	public function get_option( $option_id, $default = false ) {
		return $this->get_value_from_db( $option_id, $default );
	}

	/**
	 * Thread safe version of get_site_option, queries the database directly to prevent use of cached values
	 *
	 * @param $option_id string
	 * @param $default
	 *
	 * @return false|mixed
	 */
	public function get_site_option( $option_id, $default = false ) {
		return $this->get_value_from_db( $option_id, $default, true );
	}

	/**
	 * Read the stored value without decoding or using the option cache.
	 *
	 * @param string $option_id   Option name.
	 * @param bool   $site_option Whether to use the existing site-option mapping.
	 * @return string|null|\WP_Error Null when the option does not exist.
	 */
	private function get_raw_value_from_db( $option_id, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column, $key_column ) = $this->get_table_columns( $site_option );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read current data without the option cache; identifiers come from get_table_columns().
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$value_column}
				 FROM {$table}
				 WHERE {$column} = %s
				 ORDER BY {$key_column} ASC
				 LIMIT 1",
				$option_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( '' !== $wpdb->last_error ) {
			return new \WP_Error( 'wphb_option_read_failed', 'Could not read the stored option.' );
		}

		return $value;
	}

	/**
	 * Read a generic option, preserving the existing fallback behavior.
	 *
	 * @param string $option_id   Option name.
	 * @param mixed  $default     Default value.
	 * @param bool   $site_option Whether to use the existing site-option mapping.
	 * @return mixed
	 */
	private function get_value_from_db( $option_id, $default, $site_option = false ) {
		$value = $this->get_raw_value_from_db( $option_id, $site_option );

		// Preserve the behavior expected by existing queue and status callers.
		if ( is_wp_error( $value ) || empty( $value ) ) {
			return $default;
		}

		$decoded = json_decode( $value, true );

		return JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
	}

	public function add_data( $option_id, $key, $data ) {
		return $this->json_set_object( $option_id, $key, $data );
	}

	public function add_data_in_site_option( $option_id, $key, $data ) {
		return $this->json_set_object( $option_id, $key, $data, true );
	}

	public function remove_data( $option_id, $key ) {
		return $this->json_remove( $option_id, $key );
	}

	private function json_set_object( $option_id, $key, $data, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		$json_object = [];
		foreach ( $data as $data_key => $value ) {
			$json_object[] = $wpdb->prepare( is_int( $value ) ? "%s, %d" : "%s, %s", $data_key, $value );
		}
		$json_object_string = implode( ',', $json_object );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_SET({$value_column}, '$.\"$key\"', JSON_OBJECT({$json_object_string}))
			WHERE {$column} = '$option_id';
		" );
	}

	private function json_remove( $option_id, $key, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_REMOVE({$value_column}, '$.\"$key\"')
			WHERE {$column} = '$option_id';
		" );
	}

	public function append_to_array( $option_id, $values ) {
		return $this->json_array_append_scalars( $option_id, $values );
	}

	/**
	 * Atomically append one object using Smush's JSON_Object_Array append pattern.
	 *
	 * @param string $option_id Option name.
	 * @param array  $object    Associative array to append.
	 * @return int|false Affected rows or false on error.
	 */
	public function append_object_to_array( $option_id, array $object ) {
		global $wpdb;

		$json_string = wp_json_encode( $object );

		if ( false === $json_string ) {
			return false;
		}

		list( $table, $column, $value_column ) = $this->get_table_columns( false );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic writes; identifiers come from get_table_columns() and values are prepared.
		// Match Smush's JSON_Data initialization without changing scalar-array callers.
		$initialized = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (`{$column}`, `{$value_column}`)
				 VALUES (%s, '[]')
				 ON DUPLICATE KEY UPDATE
				 `{$value_column}` = IF(JSON_VALID(`{$value_column}`), `{$value_column}`, '[]')",
				$option_id
			)
		);

		if ( false === $initialized ) {
			return false;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET {$value_column} = JSON_ARRAY_APPEND({$value_column}, '$', JSON_EXTRACT(CONCAT('[', %s, ']'), '$[0]'))
				 WHERE {$column} = %s",
				$json_string,
				$option_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result;
	}

	/**
	 * Replace the stored object array.
	 *
	 * @param string $option_id Option name.
	 * @param array  $objects   Objects to store; non-sequential keys are re-indexed.
	 * @return int|false Affected rows or false on error.
	 */
	public function unsafe_replace_object_array( $option_id, array $objects ) {
		global $wpdb;

		$json = wp_json_encode( array_values( $objects ) );

		if ( false === $json ) {
			return false;
		}

		list( $table, $column, $value_column ) = $this->get_table_columns( false );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Snapshot replacement; identifiers come from get_table_columns() and values are prepared.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (`{$column}`, `{$value_column}`)
				 VALUES (%s, %s)
				 ON DUPLICATE KEY UPDATE `{$value_column}` = %s",
				$option_id,
				$json,
				$json
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result;
	}

	/**
	 * Read an object array directly from the database.
	 *
	 * @param string   $option_id Option name.
	 * @param int|null $limit     Optional maximum valid number of objects.
	 * @return array|\WP_Error Empty array when the option does not exist.
	 */
	public function get_object_array( $option_id, $limit = null ) {
		if ( ( null !== $limit && ( ! is_int( $limit ) || $limit < 1 ) ) || ! is_string( $option_id ) || '' === $option_id ) {
			return new \WP_Error( 'wphb_invalid_read', 'Invalid object-array read arguments.' );
		}

		$json = $this->get_raw_value_from_db( $option_id );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( null === $json ) {
			return array();
		}

		$objects = json_decode( $json );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $objects ) || ( null !== $limit && count( $objects ) > $limit ) ) {
			return new \WP_Error( 'wphb_invalid_object_array', 'The stored object array is invalid.' );
		}

		foreach ( $objects as $object ) {
			if ( ! $object instanceof \stdClass || ! get_object_vars( $object ) ) {
				return new \WP_Error( 'wphb_invalid_object_array', 'The stored array contains an invalid object.' );
			}
		}

		// Return associative arrays after validating the JSON object types.
		return json_decode( $json, true );
	}

	public function add_to_array_in_site_option( $option_id, $values ) {
		return $this->json_array_append_scalars( $option_id, $values, true );
	}

	private function json_array_append_scalars( $option_id, $values, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_array_option( $table, $column, $option_id, $value_column );

		$json_values = [];
		foreach ( $values as $value ) {
			$json_values[] = $wpdb->prepare( is_int( $value ) ? "'$', %d" : "'$', %s", $value );
		}
		$json_values_string = implode( ',', $json_values );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = JSON_ARRAY_APPEND({$value_column}, {$json_values_string})
			WHERE {$column} = '$option_id';
		" );
	}

	/**
	 * Acknowledge the current task without recreating a deleted queue.
	 *
	 * Compares the first item as a plain string, because JSON_CONTAINS() does not match URLs on MariaDB.
	 *
	 * @param string $option_id Queue option name.
	 * @param mixed  $expected Task at the front of the queue. The worker only stores scalars.
	 * @return int|false Affected rows, or false on error.
	 */
	public function remove_first_from_array( $option_id, $expected ) {
		global $wpdb;
		if ( ! is_scalar( $expected ) ) {
			return false;
		}
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = JSON_REMOVE(option_value, '$[0]')
				 WHERE option_name = %s
				 AND BINARY JSON_UNQUOTE(JSON_EXTRACT(option_value, '$[0]')) = %s",
				$option_id,
				$expected
			)
		);
	}

	public function remove_from_array( $option_id, $value ) {
		return $this->json_array_remove_scalars( $option_id, $value );
	}

	public function remove_from_array_in_site_option( $option_id, $value ) {
		return $this->json_array_remove_scalars( $option_id, $value, true );
	}

	private function json_array_remove_scalars( $option_id, $value, $site_option = false ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		$initialized = $this->initialize_json_array_option( $table, $column, $option_id, $value_column );

		$json_value = $wpdb->prepare( is_int( $value ) ? "%d" : "%s", $value );

		return $wpdb->query( "
			UPDATE {$table}
			SET {$value_column} = IF(
			    JSON_SEARCH({$value_column}, 'one', {$json_value}, NULL, '$') IS NOT NULL,
			    JSON_REMOVE({$value_column}, JSON_UNQUOTE(JSON_SEARCH({$value_column}, 'one', {$json_value}, NULL, '$'))),
			    {$value_column}
			)
			WHERE {$column} = '$option_id';
		" );
	}

	public function set_values( $option_id, $associative_array ) {
		return $this->set_json_values( $option_id, $associative_array );
	}

	public function set_values_in_site_option( $option_id, $associative_array ) {
		return $this->set_json_values( $option_id, $associative_array, true );
	}

	public function get_value( $option_id, $key, $default = false ) {
		$values = $this->get_option( $option_id );
		$values = empty( $values ) ? array() : $values;

		return isset( $values[ $key ] ) ? $values[ $key ] : $default;
	}

	public function get_value_from_site_option( $option_id, $key, $default = false ) {
		$values = $this->get_site_option( $option_id );
		$values = empty( $values ) ? array() : $values;

		return isset( $values[ $key ] ) ? $values[ $key ] : $default;
	}

	private function set_json_values( $option_id, $associative_array, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $associative_array, $site_option, function ( $value_column, $key, $value ) {
			global $wpdb;

			return $wpdb->prepare( "%s, %s", "$.\"$key\"", $value );
		} );
	}

	public function increment_values( $option_id, $keys ) {
		return $this->increment_json_values( $option_id, $keys );
	}

	public function increment_values_in_site_option( $option_id, $keys ) {
		return $this->increment_json_values( $option_id, $keys, true );
	}

	private function increment_json_values( $option_id, $keys, $site_option = false ) {
		$values = [];
		foreach ( $keys as $key ) {
			$values[ $key ] = 1;
		}

		return $this->add_to_values( $option_id, $values, $site_option );
	}

	public function add_to_values( $option_id, $values, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $values, $site_option, function ( $value_column, $key, $addend ) {
			global $wpdb;

			return $wpdb->prepare( "%s, CAST(JSON_UNQUOTE(IFNULL(JSON_EXTRACT($value_column, %s), 0)) + %d AS SIGNED)", "$.\"$key\"", "$.\"$key\"", $addend );
		} );
	}

	public function decrement_values( $option_id, $keys ) {
		return $this->decrement_json_values( $option_id, $keys );
	}

	public function decrement_values_in_site_option( $option_id, $keys ) {
		return $this->decrement_json_values( $option_id, $keys, true );
	}

	private function decrement_json_values( $option_id, $keys, $site_option = false ) {
		$values = [];
		foreach ( $keys as $key ) {
			$values[ $key ] = 1;
		}

		return $this->subtract_from_values( $option_id, $values, $site_option );
	}

	public function subtract_from_values( $option_id, $values, $site_option = false ) {
		return $this->run_json_set_query( $option_id, $values, $site_option, function ( $value_column, $key, $subtrahend ) {
			global $wpdb;

			return $wpdb->prepare( "%s, CAST(JSON_UNQUOTE(IFNULL(JSON_EXTRACT($value_column, %s), 0)) - %d AS SIGNED)", "$.\"$key\"", "$.\"$key\"", $subtrahend );
		} );
	}

	/**
	 * @param $site_option
	 *
	 * @return array
	 */
	private function get_table_columns( $site_option ): array {
		global $wpdb;

		$table        = $wpdb->options;
		$column       = 'option_name';
		$value_column = 'option_value';
		$key_column   = 'option_id';

		if ( $site_option && is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$value_column = 'meta_value';
			$key_column   = 'meta_id';
		}

		return array( $table, $column, $value_column, $key_column );
	}

	private function run_json_set_query( $option_id, $values, $site_option, $prepare_single_value_query ) {
		global $wpdb;

		list( $table, $column, $value_column ) = $this->get_table_columns( $site_option );

		// Try to initialize the option first
		$initialized = $this->initialize_json_object_option( $table, $column, $option_id, $value_column );

		$set_values = [];
		foreach ( $values as $key => $value ) {
			$set_values[] = call_user_func( $prepare_single_value_query, $value_column, $key, $value );
		}
		$set = implode( ', ', $set_values );

		$query = "
				UPDATE {$table}
				SET $value_column = JSON_SET($value_column, $set)
				WHERE {$column} = %s;
		";

		return $wpdb->query( $wpdb->prepare( $query, $option_id ) );
	}

	private function initialize_json_object_option( $table, $column, $option_id, $value_column ) {
		global $wpdb;

		return $wpdb->query( "
			INSERT IGNORE INTO {$table}
			SET `$column` = '$option_id',
				`$value_column` = '{}';
		" );
	}

	private function initialize_json_array_option( $table, $column, $option_id, $value_column ) {
		global $wpdb;

		return $wpdb->query( "
			INSERT IGNORE INTO {$table}
			SET `$column` = '$option_id',
				`$value_column` = '[]';
		" );
	}
}
