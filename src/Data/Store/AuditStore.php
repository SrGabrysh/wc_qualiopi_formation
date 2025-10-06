<?php
/**
 * Audit Store
 * 
 * RESPONSABILITÉ UNIQUE : Opérations sur la table wp_wcqf_audit
 * 
 * @package WcQualiopiFormation\Data\Store
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Store;

use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe AuditStore
 * 
 * Principe SRP : Table audit UNIQUEMENT
 */
class AuditStore {

	/**
	 * Save audit log
	 * 
	 * Stores audit event in wp_wcqf_audit
	 * 
	 * @param string $token      Token identifier
	 * @param string $event_type Event type (must be valid type from Constants)
	 * @param array  $event_data Event data (optional)
	 * @return int|false Insert ID or false on failure
	 */
	public static function save( string $token, string $event_type, array $event_data = array() ) {
		// Validate event type
		if ( ! Constants::is_valid_event_type( $event_type ) ) {
			return false;
		}

		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_AUDIT );

		$insert_data = array(
			'token'      => $token,
			'event_type' => $event_type,
			'event_data' => ! empty( $event_data ) ? self::validate_and_encode_json( $event_data ) : null,
			'created_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$table_name,
			$insert_data,
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$insert_id = $wpdb->insert_id;

		/**
		 * Fires after audit log saved
		 * 
		 * @param int    $insert_id  Insert ID
		 * @param string $event_type Event type
		 * @param array  $event_data Event data
		 */
		do_action( 'wcqf_audit_saved', $insert_id, $event_type, $event_data );

		return $insert_id;
	}

	/**
	 * Load audit logs
	 * 
	 * @param string $token Token identifier
	 * @param int    $limit Maximum number of logs (default: 100)
	 * @return array Audit logs
	 */
	public static function load( string $token, int $limit = 100 ): array {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_AUDIT );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token = %s ORDER BY created_at ASC LIMIT %d",
				$token,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Decode JSON fields
		foreach ( $logs as &$log ) {
			if ( ! empty( $log['event_data'] ) ) {
				$log['event_data'] = json_decode( $log['event_data'], true );
			}
		}

		return $logs;
	}

	/**
	 * Delete old audit logs
	 * 
	 * @param int $days_old Days threshold
	 * @return int Number of rows deleted
	 */
	public static function delete_old( int $days_old ): int {
		global $wpdb;
		$table_audit    = Constants::get_table_name( Constants::TABLE_AUDIT );
		$table_progress = Constants::get_table_name( Constants::TABLE_PROGRESS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_audit} 
				WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
				AND token NOT IN (SELECT token FROM {$table_progress})",
				$days_old
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return (int) $result;
	}

	/**
	 * Validate and encode data to JSON
	 * 
	 * @param mixed $data Data to encode
	 * @return string|null JSON string or null on failure
	 */
	private static function validate_and_encode_json( $data ): ?string {
		if ( is_string( $data ) ) {
			// Already a string, verify it's valid JSON
			$decoded = json_decode( $data, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $data;
			}
			return null;
		}

		// Encode to JSON
		$json = wp_json_encode( $data );

		// Verify encoding succeeded
		if ( false === $json || json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $json;
	}
}

