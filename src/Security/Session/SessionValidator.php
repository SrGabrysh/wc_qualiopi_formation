<?php
/**
 * Session Validator
 * 
 * RESPONSABILITÉ UNIQUE : Validation et maintenance des sessions
 * 
 * @package WcQualiopiFormation\Security\Session
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security\Session;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe SessionValidator
 * 
 * Principe SRP : Validation et maintenance UNIQUEMENT
 */
class SessionValidator {

	/**
	 * Cleanup expired sessions
	 * 
	 * @return int Number of sessions cleaned
	 */
	public static function cleanup_expired(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return 0;
		}

		$keys    = SessionStorage::get_all_keys();
		$cleaned = 0;

		foreach ( $keys as $key ) {
			$metadata = SessionStorage::get_metadata( $key );

			// If metadata exists and session is expired
			if ( $metadata && isset( $metadata['expires'] ) && time() > $metadata['expires'] ) {
				SessionStorage::delete( $key );
				$cleaned++;
			}
		}

		return $cleaned;
	}

	/**
	 * Get all sessions with metadata
	 * 
	 * @return array Array of sessions with metadata
	 */
	public static function get_all_sessions(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}

		$keys     = SessionStorage::get_all_keys();
		$sessions = array();

		foreach ( $keys as $key ) {
			$metadata = SessionStorage::get_metadata( $key );
			$value    = SessionStorage::get( $key );

			$sessions[ $key ] = array(
				'value'      => $value,
				'timestamp'  => $metadata['timestamp'] ?? null,
				'expires'    => $metadata['expires'] ?? null,
				'ttl'        => $metadata['ttl'] ?? null,
				'is_expired' => isset( $metadata['expires'] ) && time() > $metadata['expires'],
			);
		}

		return $sessions;
	}

	/**
	 * Get session statistics
	 * 
	 * @return array Session stats
	 */
	public static function get_stats(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array(
				'total'   => 0,
				'active'  => 0,
				'expired' => 0,
			);
		}

		$sessions = self::get_all_sessions();
		$total    = count( $sessions );
		$expired  = 0;

		foreach ( $sessions as $session ) {
			if ( $session['is_expired'] ) {
				$expired++;
			}
		}

		return array(
			'total'   => $total,
			'active'  => $total - $expired,
			'expired' => $expired,
		);
	}

	/**
	 * Check if session is valid (not expired)
	 * 
	 * @param string $key Session key (without prefix)
	 * @return bool True if valid
	 */
	public static function is_valid( string $key ): bool {
		$metadata = SessionStorage::get_metadata( $key );

		if ( ! $metadata ) {
			return false;
		}

		if ( isset( $metadata['expires'] ) && time() > $metadata['expires'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Get time until expiration
	 * 
	 * @param string $key Session key (without prefix)
	 * @return int|null Seconds until expiration, or null if not found
	 */
	public static function get_ttl( string $key ): ?int {
		$metadata = SessionStorage::get_metadata( $key );

		if ( ! $metadata || ! isset( $metadata['ttl'] ) ) {
			return null;
		}

		return $metadata['ttl'];
	}

	/**
	 * Check if session will expire soon
	 * 
	 * @param string $key       Session key (without prefix)
	 * @param int    $threshold Threshold in seconds (default: 5 minutes)
	 * @return bool True if expiring soon
	 */
	public static function is_expiring_soon( string $key, int $threshold = 300 ): bool {
		$ttl = self::get_ttl( $key );

		if ( null === $ttl ) {
			return false;
		}

		return $ttl > 0 && $ttl <= $threshold;
	}
}

