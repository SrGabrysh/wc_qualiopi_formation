<?php
/**
 * Session Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des sessions + méthodes de convenance
 * 
 * Manages WooCommerce sessions for backup token storage
 * Delegates to specialized classes (SessionStorage, SessionValidator)
 *
 * @package WcQualiopiFormation\Security
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\Session\SessionStorage;
use WcQualiopiFormation\Security\Session\SessionValidator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SessionManager (Manager)
 * 
 * Orchestrates session operations + convenience methods for token and test tracking
 */
class SessionManager {

	/**
	 * Set session value - DELEGATED
	 * 
	 * @param string $key   Session key (without prefix)
	 * @param mixed  $value Value to store
	 * @param int    $ttl   Time to live in seconds (optional)
	 * @return bool Success status
	 */
	public static function set( string $key, $value, int $ttl = null ): bool {
		return SessionStorage::set( $key, $value, $ttl );
	}

	/**
	 * Get session value - DELEGATED
	 * 
	 * @param string $key     Session key (without prefix)
	 * @param mixed  $default Default value if not found
	 * @return mixed Session value or default
	 */
	public static function get( string $key, $default = null ) {
		return SessionStorage::get( $key, $default );
	}

	/**
	 * Delete session value - DELEGATED
	 * 
	 * @param string $key Session key (without prefix)
	 * @return bool Success status
	 */
	public static function delete( string $key ): bool {
		return SessionStorage::delete( $key );
	}

	/**
	 * Check if session key exists - DELEGATED
	 * 
	 * @param string $key Session key (without prefix)
	 * @return bool True if exists
	 */
	public static function has( string $key ): bool {
		return SessionStorage::has( $key );
	}

	/**
	 * Get session metadata - DELEGATED
	 * 
	 * @param string $key Session key (without prefix)
	 * @return array|null Metadata or null if not found
	 */
	public static function get_metadata( string $key ): ?array {
		return SessionStorage::get_metadata( $key );
	}

	/**
	 * Extend session TTL - DELEGATED
	 * 
	 * @param string $key            Session key (without prefix)
	 * @param int    $additional_ttl Additional TTL in seconds (optional)
	 * @return bool Success status
	 */
	public static function extend( string $key, int $additional_ttl = null ): bool {
		return SessionStorage::extend( $key, $additional_ttl );
	}

	/**
	 * Cleanup expired sessions - DELEGATED
	 * 
	 * @return int Number of sessions cleaned
	 */
	public static function cleanup_expired(): int {
		return SessionValidator::cleanup_expired();
	}

	/**
	 * Get all sessions - DELEGATED
	 * 
	 * @return array Array of sessions with metadata
	 */
	public static function get_all_sessions(): array {
		return SessionValidator::get_all_sessions();
	}

	/**
	 * Get session statistics - DELEGATED
	 * 
	 * @return array Session stats
	 */
	public static function get_stats(): array {
		return SessionValidator::get_stats();
	}

	/**
	 * Clear all sessions - DELEGATED
	 * 
	 * @return bool Success status
	 */
	public static function clear_all(): bool {
		return SessionStorage::clear_all();
	}

	// ============================================================
	// CONVENIENCE METHODS - Token & Test Tracking
	// ============================================================

	/**
	 * Store HMAC token in session
	 * 
	 * Convenience method for token storage
	 * 
	 * @param string $token HMAC token
	 * @param int    $ttl   TTL in seconds (optional)
	 * @return bool Success status
	 */
	public static function store_token( string $token, int $ttl = null ): bool {
		return self::set( 'token', $token, $ttl );
	}

	/**
	 * Get HMAC token from session
	 * 
	 * Convenience method for token retrieval
	 * 
	 * @return string|null Token or null if not found
	 */
	public static function get_token(): ?string {
		return self::get( 'token' );
	}

	/**
	 * Delete HMAC token from session
	 * 
	 * Convenience method for token deletion
	 * 
	 * @return bool Success status
	 */
	public static function delete_token(): bool {
		return self::delete( 'token' );
	}

	/**
	 * Mark test as solved for a product
	 * 
	 * Convenience method for test tracking
	 * 
	 * @param int $product_id Product ID
	 * @param int $ttl        TTL in seconds (optional)
	 * @return bool Success status
	 */
	public static function set_solved( int $product_id, int $ttl = null ): bool {
		$key = 'test_solved_' . $product_id;
		return self::set( $key, true, $ttl );
	}

	/**
	 * Check if test is solved for a product
	 * 
	 * Convenience method for test checking
	 * 
	 * @param int $product_id Product ID
	 * @return bool True if solved
	 */
	public static function is_solved( int $product_id ): bool {
		$key = 'test_solved_' . $product_id;
		return (bool) self::get( $key, false );
	}

	/**
	 * Remove solved status for a product
	 * 
	 * Convenience method for test reset
	 * 
	 * @param int $product_id Product ID
	 * @return bool Success status
	 */
	public static function unset_solved( int $product_id ): bool {
		$key = 'test_solved_' . $product_id;
		return self::delete( $key );
	}
}
