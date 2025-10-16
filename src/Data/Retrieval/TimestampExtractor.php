<?php
/**
 * Timestamp Extractor
 * 
 * RESPONSABILITÉ UNIQUE : Extraction de timestamps depuis données de réservation
 * 
 * @package WcQualiopiFormation\Data\Retrieval
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Data\Retrieval;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe TimestampExtractor
 * 
 * Principe SRP : Extraction et conversion de timestamps UNIQUEMENT
 * Helper utilisé par CartBookingRetriever
 * 
 * @since 1.2.0
 */
class TimestampExtractor {

	/**
	 * Extract timestamp from booking data
	 * 
	 * Tries multiple possible key variations to find the timestamp value.
	 * Priority order based on WooCommerce Bookings standard format.
	 * 
	 * @param array  $booking Booking data array.
	 * @param string $key     Base key name ('start' or 'end').
	 * @return int Timestamp or 0 if not found.
	 */
	public static function extract( array $booking, string $key ): int {
		// Try WooCommerce Bookings standard format first: _start_date / _end_date
		$timestamp = self::try_wc_bookings_format( $booking, $key );
		if ( $timestamp > 0 ) {
			return $timestamp;
		}

		// Try direct key (start, end)
		$timestamp = self::try_direct_key( $booking, $key );
		if ( $timestamp > 0 ) {
			return $timestamp;
		}

		// Try with underscore prefix only
		$timestamp = self::try_underscore_prefix( $booking, $key );
		if ( $timestamp > 0 ) {
			return $timestamp;
		}

		// Try old meta key convention
		return self::try_legacy_format( $booking, $key );
	}

	/**
	 * Try WooCommerce Bookings standard format: _start_date / _end_date
	 * 
	 * @param array  $booking Booking data array.
	 * @param string $key     Base key name.
	 * @return int Timestamp or 0 if not found.
	 */
	private static function try_wc_bookings_format( array $booking, string $key ): int {
		$wc_bookings_key = '_' . $key . '_date';
		
		if ( ! isset( $booking[ $wc_bookings_key ] ) ) {
			return 0;
		}

		return self::parse_value( $booking[ $wc_bookings_key ] );
	}

	/**
	 * Try direct key (start, end)
	 * 
	 * @param array  $booking Booking data array.
	 * @param string $key     Base key name.
	 * @return int Timestamp or 0 if not found.
	 */
	private static function try_direct_key( array $booking, string $key ): int {
		if ( ! isset( $booking[ $key ] ) ) {
			return 0;
		}

		return self::parse_value( $booking[ $key ] );
	}

	/**
	 * Try with underscore prefix only (_start, _end)
	 * 
	 * @param array  $booking Booking data array.
	 * @param string $key     Base key name.
	 * @return int Timestamp or 0 if not found.
	 */
	private static function try_underscore_prefix( array $booking, string $key ): int {
		$prefixed_key = '_' . $key;
		
		if ( ! isset( $booking[ $prefixed_key ] ) ) {
			return 0;
		}

		return self::parse_value( $booking[ $prefixed_key ] );
	}

	/**
	 * Try old meta key convention (_booking_start, _booking_end)
	 * 
	 * @param array  $booking Booking data array.
	 * @param string $key     Base key name.
	 * @return int Timestamp or 0 if not found.
	 */
	private static function try_legacy_format( array $booking, string $key ): int {
		$old_meta_key = '_booking_' . $key;
		
		if ( ! isset( $booking[ $old_meta_key ] ) ) {
			return 0;
		}

		return \absint( $booking[ $old_meta_key ] );
	}

	/**
	 * Parse value to timestamp
	 * 
	 * Handles both numeric timestamps and date strings.
	 * 
	 * @param mixed $value Value to parse.
	 * @return int Timestamp or 0 if invalid.
	 */
	private static function parse_value( $value ): int {
		// If it's already a numeric timestamp
		if ( is_numeric( $value ) ) {
			return \absint( $value );
		}

		// If it's a date string, try to parse it
		if ( is_string( $value ) ) {
			$timestamp = \strtotime( $value );
			return $timestamp ? $timestamp : 0;
		}

		return 0;
	}
}

