<?php
/**
 * Progress Tracker Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des opérations de progression
 * 
 * Tracks user progression through the formation tunnel
 * Delegates to specialized classes (Storage, Validator)
 *
 * @package WcQualiopiFormation\Data
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data;

use WcQualiopiFormation\Data\Progress\ProgressStorage;
use WcQualiopiFormation\Data\Progress\ProgressValidator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProgressTracker (Manager)
 * 
 * Orchestrates progress tracking operations
 */
class ProgressTracker {

	/**
	 * Start new progress entry - DELEGATED
	 * 
	 * @param int    $user_id      WordPress user ID
	 * @param int    $product_id   WooCommerce product ID
	 * @param string $cart_key     Cart item key (optional)
	 * @param array  $initial_data Initial collected data (optional)
	 * @return array|false Array with id and token, or false on failure
	 */
	public static function start_progress( int $user_id, int $product_id, string $cart_key = '', array $initial_data = array() ) {
		return ProgressStorage::start( $user_id, $product_id, $cart_key, $initial_data );
	}

	/**
	 * Update current step - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @param string $step  New step (must be valid step from Constants)
	 * @return bool Success status
	 */
	public static function update_step( string $token, string $step ): bool {
		return ProgressStorage::update_step( $token, $step );
	}

	/**
	 * Add collected data - DELEGATED
	 * 
	 * @param string $token      Token identifier
	 * @param string $data_key   Key for the data (e.g., 'personal', 'company', 'form')
	 * @param mixed  $data_value Value to store
	 * @return bool Success status
	 */
	public static function add_data( string $token, string $data_key, $data_value ): bool {
		return ProgressStorage::add_data( $token, $data_key, $data_value );
	}

	/**
	 * Get progress by token - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array|null Progress data or null if not found
	 */
	public static function get_progress( string $token ): ?array {
		return ProgressStorage::get( $token );
	}

	/**
	 * Mark progress as completed - DELEGATED
	 * 
	 * @param string $token    Token identifier
	 * @param int    $order_id Order ID (optional)
	 * @return bool Success status
	 */
	public static function complete( string $token, int $order_id = 0 ): bool {
		return ProgressStorage::complete( $token, $order_id );
	}

	/**
	 * Mark progress as abandoned - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return bool Success status
	 */
	public static function mark_abandoned( string $token ): bool {
		return ProgressStorage::mark_abandoned( $token );
	}

	/**
	 * Clean up abandoned progress entries - DELEGATED
	 * 
	 * @param int $hours_inactive Hours of inactivity threshold (default: 24)
	 * @return int Number of entries marked as abandoned
	 */
	public static function cleanup_abandoned( int $hours_inactive = 24 ): int {
		return ProgressValidator::cleanup_abandoned( $hours_inactive );
	}

	/**
	 * Get progress statistics - DELEGATED
	 * 
	 * @return array Statistics
	 */
	public static function get_stats(): array {
		return ProgressValidator::get_stats();
	}

	/**
	 * Check if progress is valid - DELEGATED
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if valid
	 */
	public static function is_valid( ?array $progress ): bool {
		return ProgressValidator::is_valid( $progress );
	}

	/**
	 * Check if progress is abandoned - DELEGATED
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if abandoned
	 */
	public static function is_abandoned( ?array $progress ): bool {
		return ProgressValidator::is_abandoned( $progress );
	}

	/**
	 * Check if progress is completed - DELEGATED
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if completed
	 */
	public static function is_completed( ?array $progress ): bool {
		return ProgressValidator::is_completed( $progress );
	}

	/**
	 * Check if progress is active - DELEGATED
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if active
	 */
	public static function is_active( ?array $progress ): bool {
		return ProgressValidator::is_active( $progress );
	}
}
