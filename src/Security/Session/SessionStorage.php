<?php
/**
 * Session Storage
 * 
 * RESPONSABILITÉ UNIQUE : Opérations CRUD sur les sessions WooCommerce
 * 
 * @package WcQualiopiFormation\Security\Session
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security\Session;

use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe SessionStorage
 * 
 * Principe SRP : CRUD sessions UNIQUEMENT
 */
class SessionStorage {

	/**
	 * Session key prefix
	 */
	const SESSION_PREFIX = 'wcqf_';

	/**
	 * Set session value
	 * 
	 * @param string $key   Session key (without prefix)
	 * @param mixed  $value Value to store
	 * @param int    $ttl   Time to live in seconds (optional)
	 * @return bool Success status
	 */
	public static function set( string $key, $value, int $ttl = null ): bool {
		if ( ! self::is_session_available() ) {
			return false;
		}

		if ( null === $ttl ) {
			$ttl = Constants::SESSION_TTL_MINUTES * 60; // Convert minutes to seconds
		}

		$full_key = self::SESSION_PREFIX . $key;

		// Wrap value with metadata
		$session_data = array(
			'value'     => $value,
			'timestamp' => time(),
			'expires'   => time() + $ttl,
		);

		WC()->session->set( $full_key, $session_data );
		return true;
	}

	/**
	 * Get session value
	 * 
	 * @param string $key     Session key (without prefix)
	 * @param mixed  $default Default value if not found
	 * @return mixed Session value or default
	 */
	public static function get( string $key, $default = null ) {
		if ( ! self::is_session_available() ) {
			return $default;
		}

		$full_key     = self::SESSION_PREFIX . $key;
		$session_data = WC()->session->get( $full_key );

		if ( ! is_array( $session_data ) || ! isset( $session_data['value'] ) ) {
			return $default;
		}

		// Check expiration
		if ( isset( $session_data['expires'] ) && time() > $session_data['expires'] ) {
			self::delete( $key );
			return $default;
		}

		return $session_data['value'];
	}

	/**
	 * Delete session value
	 * 
	 * @param string $key Session key (without prefix)
	 * @return bool Success status
	 */
	public static function delete( string $key ): bool {
		if ( ! self::is_session_available() ) {
			return false;
		}

		$full_key = self::SESSION_PREFIX . $key;
		WC()->session->__unset( $full_key );
		return true;
	}

	/**
	 * Check if session key exists
	 * 
	 * @param string $key Session key (without prefix)
	 * @return bool True if exists
	 */
	public static function has( string $key ): bool {
		if ( ! self::is_session_available() ) {
			return false;
		}

		$full_key     = self::SESSION_PREFIX . $key;
		$session_data = WC()->session->get( $full_key );

		if ( ! is_array( $session_data ) || ! isset( $session_data['value'] ) ) {
			return false;
		}

		// Check expiration
		if ( isset( $session_data['expires'] ) && time() > $session_data['expires'] ) {
			self::delete( $key );
			return false;
		}

		return true;
	}

	/**
	 * Get session metadata
	 * 
	 * @param string $key Session key (without prefix)
	 * @return array|null Metadata or null if not found
	 */
	public static function get_metadata( string $key ): ?array {
		if ( ! self::is_session_available() ) {
			return null;
		}

		$full_key     = self::SESSION_PREFIX . $key;
		$session_data = WC()->session->get( $full_key );

		if ( ! is_array( $session_data ) ) {
			return null;
		}

		return array(
			'timestamp' => $session_data['timestamp'] ?? null,
			'expires'   => $session_data['expires'] ?? null,
			'ttl'       => isset( $session_data['expires'] ) 
				? max( 0, $session_data['expires'] - time() ) 
				: null,
		);
	}

	/**
	 * Extend session TTL
	 * 
	 * @param string $key            Session key (without prefix)
	 * @param int    $additional_ttl Additional TTL in seconds (optional)
	 * @return bool Success status
	 */
	public static function extend( string $key, int $additional_ttl = null ): bool {
		if ( ! self::is_session_available() ) {
			return false;
		}

		if ( null === $additional_ttl ) {
			$additional_ttl = Constants::SESSION_TTL_MINUTES * 60;
		}

		$full_key     = self::SESSION_PREFIX . $key;
		$session_data = WC()->session->get( $full_key );

		if ( ! is_array( $session_data ) || ! isset( $session_data['value'] ) ) {
			return false;
		}

		// Update expires time
		$session_data['expires'] = time() + $additional_ttl;
		WC()->session->set( $full_key, $session_data );

		return true;
	}

	/**
	 * Get all session keys with our prefix
	 * 
	 * @return array Array of session keys (without prefix)
	 */
	public static function get_all_keys(): array {
		if ( ! self::is_session_available() ) {
			return array();
		}

		$all_data = WC()->session->get_session_data();
		$keys     = array();

		foreach ( array_keys( $all_data ) as $key ) {
			if ( strpos( $key, self::SESSION_PREFIX ) === 0 ) {
				$keys[] = substr( $key, strlen( self::SESSION_PREFIX ) );
			}
		}

		return $keys;
	}

	/**
	 * Clear all sessions with our prefix
	 * 
	 * @return bool Success status
	 */
	public static function clear_all(): bool {
		if ( ! self::is_session_available() ) {
			return false;
		}

		$keys = self::get_all_keys();

		foreach ( $keys as $key ) {
			self::delete( $key );
		}

		return true;
	}

	/**
	 * Check if WooCommerce session is available
	 * 
	 * @return bool True if available
	 */
	private static function is_session_available(): bool {
		return function_exists( 'WC' ) && WC()->session !== null;
	}
}

