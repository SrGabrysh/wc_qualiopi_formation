<?php
/**
 * Booking Data Retriever
 * 
 * RESPONSABILITÉ UNIQUE : Récupération des données de réservation et formulaires
 * 
 * @package WcQualiopiFormation\Data\Retrieval
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Retrieval;

use WcQualiopiFormation\Data\ProgressTracker;
use WcQualiopiFormation\Data\DataStore;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe BookingDataRetriever
 * 
 * Principe SRP : Données de réservation et formulaires UNIQUEMENT
 */
class BookingDataRetriever {

	/**
	 * Get booking data
	 * 
	 * Retrieves session booking information
	 * 
	 * @param string $token Token identifier
	 * @return array Booking data (session_date, session_time)
	 */
	public static function get_booking( string $token ): array {
		$collected_data = self::get_collected_data( $token );

		if ( ! $collected_data || ! isset( $collected_data['booking'] ) ) {
			return array();
		}

		return $collected_data['booking'];
	}

	/**
	 * Get test answers
	 * 
	 * Extracts test answers from collected data
	 * 
	 * @param string $token Token identifier
	 * @return array Test answers
	 */
	public static function get_test_answers( string $token ): array {
		$collected_data = self::get_collected_data( $token );

		if ( ! $collected_data || ! isset( $collected_data['test_answers'] ) ) {
			return array();
		}

		return $collected_data['test_answers'];
	}

	/**
	 * Get form submission data
	 * 
	 * Retrieves form data from tracking table
	 * 
	 * @param string $token Token identifier
	 * @return array|null Form data or null if not found
	 */
	public static function get_form_data( string $token ): ?array {
		$tracking = DataStore::load_tracking( $token );

		if ( ! $tracking || empty( $tracking['form_data'] ) ) {
			return null;
		}

		return $tracking['form_data'];
	}

	/**
	 * Get complete profile for order meta
	 * 
	 * Compiles all collected data for order meta storage
	 * 
	 * @param string $token Token identifier
	 * @return array Complete profile data
	 */
	public static function get_complete_profile( string $token ): array {
		$progress = ProgressTracker::get_progress( $token );

		if ( ! $progress ) {
			return array();
		}

		$profile = array(
			'token'          => $token,
			'progress_id'    => $progress['id'] ?? 0,
			'user_id'        => $progress['user_id'] ?? 0,
			'product_id'     => $progress['product_id'] ?? 0,
			'started_at'     => $progress['started_at'] ?? '',
			'completed_at'   => $progress['completed_at'] ?? '',
			'collected_data' => $progress['collected_data'] ?? array(),
			'audit_trail'    => DataStore::load_audit_logs( $token, 50 ),
		);

		/**
		 * Filter complete profile data
		 * 
		 * @param array  $profile Complete profile
		 * @param string $token   Token identifier
		 */
		return \apply_filters( 'wcqf_complete_profile', $profile, $token );
	}

	/**
	 * Check if booking data exists
	 * 
	 * @param string $token Token identifier
	 * @return bool True if booking data exists
	 */
	public static function has_booking( string $token ): bool {
		$booking = self::get_booking( $token );
		return ! empty( $booking );
	}

	/**
	 * Check if test answers exist
	 * 
	 * @param string $token Token identifier
	 * @return bool True if test answers exist
	 */
	public static function has_test_answers( string $token ): bool {
		$answers = self::get_test_answers( $token );
		return ! empty( $answers );
	}

	/**
	 * Get collected data from progress
	 * 
	 * @param string $token Token identifier
	 * @return array|null Collected data or null
	 */
	private static function get_collected_data( string $token ): ?array {
		$progress = ProgressTracker::get_progress( $token );

		if ( ! $progress || empty( $progress['collected_data'] ) ) {
			return null;
		}

		return $progress['collected_data'];
	}

	/**
	 * Get booking details from WooCommerce cart
	 * 
	 * Delegates to CartBookingRetriever for cart-based booking data retrieval.
	 * This method is kept for backward compatibility.
	 * 
	 * @since 1.2.0
	 * @deprecated 1.2.0 Use CartBookingRetriever::get_booking_details() instead
	 * @return array Array of booking details
	 */
	public static function get_details_from_cart(): array {
		return CartBookingRetriever::get_booking_details();
	}
}

