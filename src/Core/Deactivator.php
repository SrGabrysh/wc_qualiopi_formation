<?php
/**
 * Deactivator Class
 * 
 * Handles plugin deactivation logic
 * Principle: Single Responsibility - Cleanup only
 *
 * @package WcQualiopiFormation\Core
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Core;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 * 
 * Handles plugin deactivation:
 * - Cleans up cron jobs
 * - Flushes rewrite rules
 * - Preserves data (Qualiopi compliance)
 */
final class Deactivator {

	/**
	 * Deactivate plugin
	 * 
	 * Called when plugin is deactivated
	 * Principle: KISS - Simple, safe deactivation
	 * 
	 * IMPORTANT: Does NOT delete data or tables
	 * Data must be preserved for Qualiopi compliance (5 years)
	 */
	public static function deactivate(): void {
		// Clean up scheduled cron jobs
		self::clear_scheduled_hooks();

		// Flush rewrite rules
		flush_rewrite_rules();

		/**
		 * Fires after plugin deactivation
		 */
		do_action( 'wcqf_deactivated' );
	}

	/**
	 * Clear all scheduled WordPress cron hooks
	 * 
	 * Prevents zombie cron jobs after deactivation
	 */
	private static function clear_scheduled_hooks(): void {
		// Clear daily cleanup cron
		$timestamp = wp_next_scheduled( 'wcqf_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wcqf_daily_cleanup' );
		}

		// Clear weekly report cron (if exists)
		$timestamp = wp_next_scheduled( 'wcqf_weekly_report' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wcqf_weekly_report' );
		}

		/**
		 * Allow third-party code to clear custom cron hooks
		 */
		do_action( 'wcqf_clear_cron_hooks' );
	}

	/**
	 * Uninstall plugin (called only on deletion, not deactivation)
	 * 
	 * This method should be called from uninstall.php, not on deactivation
	 * 
	 * WARNING: This deletes ALL data permanently
	 * Only use if Qualiopi retention period (5 years) has passed
	 */
	public static function uninstall(): void {
		global $wpdb;

		// Delete tables
		$table_progress = Constants::get_table_name( Constants::TABLE_PROGRESS );
		$table_tracking = Constants::get_table_name( Constants::TABLE_TRACKING );
		$table_audit    = Constants::get_table_name( Constants::TABLE_AUDIT );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_progress}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_tracking}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_audit}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		// Delete options
		delete_option( Constants::OPTION_PLUGIN_VERSION );
		delete_option( Constants::OPTION_SETTINGS );
		delete_option( Constants::OPTION_PRODUCT_FORM_MAPPING );
		delete_option( 'wcqf_db_version' );

		// Clean up transients
		delete_transient( 'wcqf_cache' );

		/**
		 * Fires after plugin uninstall
		 */
		do_action( 'wcqf_uninstalled' );
	}
}


