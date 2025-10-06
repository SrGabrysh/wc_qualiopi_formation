<?php
/**
 * Progress Store
 * 
 * RESPONSABILITÉ UNIQUE : Nettoyage et maintenance de la table wp_wcqf_progress
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
 * Classe ProgressStore
 * 
 * Principe SRP : Maintenance table progress UNIQUEMENT
 * Note : Les opérations CRUD sont dans Data/Progress/ProgressStorage.php
 */
class ProgressStore {

	/**
	 * Delete old progress data
	 * 
	 * Only deletes completed entries not linked to orders
	 * 
	 * @param int $days_old Days threshold
	 * @return int Number of rows deleted
	 */
	public static function delete_old( int $days_old ): int {
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} 
				WHERE is_completed = 1 
				AND order_id IS NULL 
				AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days_old
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return (int) $result;
	}

	/**
	 * Get progress count statistics
	 * 
	 * @return array Count statistics
	 */
	public static function get_count_stats(): array {
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
}

