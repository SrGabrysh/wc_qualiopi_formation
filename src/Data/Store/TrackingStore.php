<?php
/**
 * Tracking Store
 * 
 * RESPONSABILITÉ UNIQUE : Opérations sur la table wp_wcqf_tracking
 * 
 * @package WcQualiopiFormation\Data\Store
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Store;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\SanitizationHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe TrackingStore
 * 
 * Principe SRP : Table tracking UNIQUEMENT
 */
class TrackingStore {

	/**
	 * Save tracking data
	 * 
	 * Stores form submission data in wp_wcqf_tracking
	 * 
	 * @param array $data Tracking data array
	 * @return int|false Insert ID or false on failure
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_TRACKING );

		// Validate required fields
		if ( empty( $data['form_id'] ) ) {
			return false;
		}

		// Prepare data
		$insert_data = array(
			'token'        => $data['token'] ?? '',
			'form_id'      => (int) $data['form_id'],
			'entry_id'     => isset( $data['entry_id'] ) ? (int) $data['entry_id'] : null,
			'user_id'      => isset( $data['user_id'] ) ? (int) $data['user_id'] : null,
			'siret'        => self::sanitize_siret( $data['siret'] ?? '' ),
			'company_name' => SanitizationHelper::sanitize_name( $data['company_name'] ?? '' ),
			'form_data'    => isset( $data['form_data'] ) ? self::validate_and_encode_json( $data['form_data'] ) : null,
			'submitted_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$table_name,
			$insert_data,
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$insert_id = $wpdb->insert_id;

		/**
		 * Fires after tracking data saved
		 * 
		 * @param int   $insert_id Insert ID
		 * @param array $data      Saved data
		 */
		do_action( 'wcqf_tracking_saved', $insert_id, $insert_data );

		return $insert_id;
	}

	/**
	 * Load tracking data
	 * 
	 * @param string $token Token identifier
	 * @return array|null Tracking data or null if not found
	 */
	public static function load( string $token ): ?array {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_TRACKING );

		$tracking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token = %s ORDER BY id DESC LIMIT 1",
				$token
			),
			ARRAY_A
		);

		if ( ! $tracking ) {
			return null;
		}

		// Decode JSON fields
		if ( ! empty( $tracking['form_data'] ) ) {
			$tracking['form_data'] = json_decode( $tracking['form_data'], true );
		}

		return $tracking;
	}

	/**
	 * Delete old tracking data
	 * 
	 * @param int $days_old Days threshold
	 * @return int Number of rows deleted
	 */
	public static function delete_old( int $days_old ): int {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_TRACKING );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				WHERE submitted_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
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

	/**
	 * Sanitize SIRET number
	 * 
	 * @param string $siret SIRET number
	 * @return string Sanitized SIRET (digits only, 14 chars)
	 */
	private static function sanitize_siret( string $siret ): string {
		// Remove all non-digit characters
		$siret = preg_replace( '/\D/', '', $siret );

		// Ensure 14 characters
		return substr( $siret, 0, 14 );
	}
}

