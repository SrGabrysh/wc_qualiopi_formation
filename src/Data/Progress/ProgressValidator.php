<?php
/**
 * Progress Validator
 * 
 * RESPONSABILITÉ UNIQUE : Validation et nettoyage des progressions
 * 
 * @package WcQualiopiFormation\Data\Progress
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Progress;

use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ProgressValidator
 * 
 * Principe SRP : Validation et nettoyage UNIQUEMENT
 */
class ProgressValidator {

	/**
	 * Clean up abandoned progress entries
	 * 
	 * Marks entries as abandoned if inactive for more than specified hours
	 * 
	 * @param int $hours_inactive Hours of inactivity threshold (default: 24)
	 * @return int Number of entries marked as abandoned
	 */
	public static function cleanup_abandoned( int $hours_inactive = 24 ): int {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} 
				SET is_abandoned = 1 
				WHERE is_completed = 0 
				AND is_abandoned = 0
				AND last_activity < DATE_SUB(NOW(), INTERVAL %d HOUR)",
				$hours_inactive
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return (int) $result;
	}

	/**
	 * Get progress statistics
	 * 
	 * @return array Statistics
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN is_abandoned = 1 THEN 1 ELSE 0 END) as abandoned,
				SUM(CASE WHEN is_completed = 0 AND is_abandoned = 0 THEN 1 ELSE 0 END) as in_progress
			FROM {$table_name}",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $stats ?: array(
			'total'       => 0,
			'completed'   => 0,
			'abandoned'   => 0,
			'in_progress' => 0,
		);
	}

	/**
	 * Check if progress is valid
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if valid
	 */
	public static function is_valid( ?array $progress ): bool {
		if ( ! $progress ) {
			return false;
		}

		// Check if completed
		if ( ! empty( $progress['is_completed'] ) ) {
			return true;
		}

		// Check if abandoned
		if ( ! empty( $progress['is_abandoned'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if progress is abandoned
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if abandoned
	 */
	public static function is_abandoned( ?array $progress ): bool {
		if ( ! $progress ) {
			return true;
		}

		return ! empty( $progress['is_abandoned'] );
	}

	/**
	 * Check if progress is completed
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if completed
	 */
	public static function is_completed( ?array $progress ): bool {
		if ( ! $progress ) {
			return false;
		}

		return ! empty( $progress['is_completed'] );
	}

	/**
	 * Check if progress is active (not completed and not abandoned)
	 * 
	 * @param array|null $progress Progress data
	 * @return bool True if active
	 */
	public static function is_active( ?array $progress ): bool {
		return self::is_valid( $progress ) && ! self::is_completed( $progress );
	}
}

