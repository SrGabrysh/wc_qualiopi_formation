<?php
/**
 * Data Store Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des stores + utilitaires globaux
 * 
 * Handles data storage and validation
 * Delegates to specialized stores (Progress, Tracking, Audit)
 *
 * @package WcQualiopiFormation\Data
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Data\Store\ProgressStore;
use WcQualiopiFormation\Data\Store\TrackingStore;
use WcQualiopiFormation\Data\Store\AuditStore;
use WcQualiopiFormation\Helpers\SanitizationHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataStore (Manager)
 * 
 * Orchestrates data storage operations + global utilities
 */
class DataStore {

	/**
	 * Save tracking data - DELEGATED
	 * 
	 * @param array $data Tracking data array
	 * @return int|false Insert ID or false on failure
	 */
	public static function save_tracking( array $data ) {
		return TrackingStore::save( $data );
	}

	/**
	 * Load tracking data - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array|null Tracking data or null if not found
	 */
	public static function load_tracking( string $token ): ?array {
		return TrackingStore::load( $token );
	}

	/**
	 * Save audit log - DELEGATED
	 * 
	 * @param string $token      Token identifier
	 * @param string $event_type Event type
	 * @param array  $event_data Event data (optional)
	 * @return int|false Insert ID or false on failure
	 */
	public static function save_audit( string $token, string $event_type, array $event_data = array() ) {
		return AuditStore::save( $token, $event_type, $event_data );
	}

	/**
	 * Load audit logs - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @param int    $limit Maximum number of logs (default: 100)
	 * @return array Audit logs
	 */
	public static function load_audit_logs( string $token, int $limit = 100 ): array {
		return AuditStore::load( $token, $limit );
	}

	/**
	 * Delete old data (GDPR compliance)
	 * 
	 * Orchestrates deletion across all tables
	 * 
	 * @param int $days_old Days to keep (default: 1825 = 5 years for Qualiopi)
	 * @return array Number of rows deleted per table
	 */
	public static function delete_old_data( int $days_old = 1825 ): array {
		$deleted = array(
			'progress' => ProgressStore::delete_old( $days_old ),
			'tracking' => TrackingStore::delete_old( $days_old ),
			'audit'    => AuditStore::delete_old( $days_old ),
		);

		/**
		 * Fires after old data deleted
		 * 
		 * @param array $deleted  Number of rows deleted
		 * @param int   $days_old Days threshold
		 */
		do_action( 'wcqf_old_data_deleted', $deleted, $days_old );

		return $deleted;
	}

	/**
	 * Validate data structure
	 * 
	 * Checks if collected_data has expected structure
	 * GLOBAL UTILITY
	 * 
	 * @param array $data Data to validate
	 * @return bool True if valid structure
	 */
	public static function validate_structure( array $data ): bool {
		$required_keys = array( 'personal', 'company' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
				return false;
			}
		}

		// Validate personal data
		$required_personal = array( 'first_name', 'last_name', 'email' );
		foreach ( $required_personal as $field ) {
			if ( empty( $data['personal'][ $field ] ) ) {
				return false;
			}
		}

		// Validate company data
		if ( empty( $data['company']['name'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize collected data
	 * 
	 * Recursively sanitize array data
	 * GLOBAL UTILITY
	 * 
	 * @param array $data Data to sanitize
	 * @return array Sanitized data
	 */
	public static function sanitize_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$clean_key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = self::sanitize_data( $value );
			} elseif ( is_string( $value ) ) {
				// Sanitize based on context
				if ( strpos( $key, 'email' ) !== false ) {
					$sanitized[ $clean_key ] = sanitize_email( $value );
				} elseif ( strpos( $key, 'url' ) !== false ) {
					$sanitized[ $clean_key ] = esc_url_raw( $value );
				} else {
					$sanitized[ $clean_key ] = SanitizationHelper::sanitize_name( $value );
				}
			} else {
				$sanitized[ $clean_key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get database size statistics
	 * 
	 * GLOBAL UTILITY
	 * 
	 * @return array Table sizes in MB
	 */
	public static function get_database_stats(): array {
		global $wpdb;

		$table_progress = Constants::get_table_name( Constants::TABLE_PROGRESS );
		$table_tracking = Constants::get_table_name( Constants::TABLE_TRACKING );
		$table_audit    = Constants::get_table_name( Constants::TABLE_AUDIT );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					table_name as `table`,
					ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
					table_rows as row_count
				FROM information_schema.TABLES 
				WHERE table_schema = %s 
				AND table_name IN (%s, %s, %s)",
				DB_NAME,
				$table_progress,
				$table_tracking,
				$table_audit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $stats ?: array();
	}
}
