<?php
/**
 * Activator Class
 * 
 * Handles plugin activation logic
 * Principle: Single Responsibility - Installation only
 *
 * @package WcQualiopiFormation\Core
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Core;

use WcQualiopiFormation\Helpers\LoggingHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 * 
 * Handles plugin activation:
 * - Creates database tables
 * - Sets default options
 * - Checks requirements
 */
final class Activator {

	/**
	 * Activate plugin
	 * 
	 * Called when plugin is activated
	 * Principle: KISS - Simple, clear activation process
	 */
	public static function activate(): void {
		// Verify WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_die(
				esc_html__( 'WC Qualiopi Formation requires WooCommerce to be installed and active.', 'wcqf' ),
				esc_html__( 'Plugin Activation Error', 'wcqf' ),
				array( 'back_link' => true )
			);
		}

		// Create database tables
		self::create_tables();

		// Set default options
		self::set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();

		/**
		 * Fires after plugin activation
		 */
		do_action( 'wcqf_activated' );
	}

	/**
	 * Create plugin database tables
	 * 
	 * Uses WordPress dbDelta for reliable table creation
	 * Principle: Declarative over Imperative
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table 1: Progress tracking (main table)
		$table_progress = Constants::get_table_name( Constants::TABLE_PROGRESS );
		
		$sql_progress = "CREATE TABLE IF NOT EXISTS {$table_progress} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			token VARCHAR(255) UNIQUE NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			session_id VARCHAR(255),
			product_id BIGINT UNSIGNED NOT NULL,
			cart_key VARCHAR(255),
			current_step VARCHAR(50) NOT NULL,
			last_activity DATETIME NOT NULL,
			started_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			collected_data LONGTEXT,
			metadata LONGTEXT,
			is_completed BOOLEAN DEFAULT 0,
			is_abandoned BOOLEAN DEFAULT 0,
			order_id BIGINT UNSIGNED NULL,
			ip_address VARCHAR(45),
			user_agent TEXT,
			INDEX idx_token (token),
			INDEX idx_user_id (user_id),
			INDEX idx_product_id (product_id),
			INDEX idx_step (current_step),
			INDEX idx_activity (last_activity)
		) {$charset_collate};";

		// Table 2: Form tracking
		$table_tracking = Constants::get_table_name( Constants::TABLE_TRACKING );
		
		$sql_tracking = "CREATE TABLE IF NOT EXISTS {$table_tracking} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			token VARCHAR(255),
			form_id INT NOT NULL,
			entry_id BIGINT UNSIGNED,
			user_id BIGINT UNSIGNED,
			siret VARCHAR(14),
			company_name VARCHAR(255),
			form_data LONGTEXT,
			submitted_at DATETIME NOT NULL,
			INDEX idx_token (token),
			INDEX idx_form_id (form_id),
			INDEX idx_entry_id (entry_id)
		) {$charset_collate};";

		// Table 3: Audit logs (Qualiopi compliance)
		$table_audit = Constants::get_table_name( Constants::TABLE_AUDIT );
		
		$sql_audit = "CREATE TABLE IF NOT EXISTS {$table_audit} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			token VARCHAR(255) NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			event_data LONGTEXT,
			created_at DATETIME NOT NULL,
			INDEX idx_token (token),
			INDEX idx_event_type (event_type),
			INDEX idx_created_at (created_at)
		) {$charset_collate};";

		// Execute SQL with dbDelta for safe table creation
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		dbDelta( $sql_progress );
		dbDelta( $sql_tracking );
		dbDelta( $sql_audit );

		// Store database version for future migrations
		update_option( 'wcqf_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options
	 * 
	 * Principle: Convention over Configuration
	 * Sensible defaults reduce configuration burden
	 */
	private static function set_default_options(): void {
		// Set plugin version
		if ( ! get_option( Constants::OPTION_PLUGIN_VERSION ) ) {
			update_option( Constants::OPTION_PLUGIN_VERSION, WCQF_VERSION );
		}

		// Set default settings
		$default_settings = array(
			'enable_logging'       => true,
			'token_ttl_hours'      => Constants::TOKEN_TTL_HOURS,
			'session_ttl_minutes'  => Constants::SESSION_TTL_MINUTES,
			'enable_autofill'      => true,
			'enable_compliance'    => true,
		);

		if ( ! get_option( Constants::OPTION_SETTINGS ) ) {
			update_option( Constants::OPTION_SETTINGS, $default_settings );
		}

		// Initialize empty product-form mapping
		if ( ! get_option( Constants::OPTION_PRODUCT_FORM_MAPPING ) ) {
			update_option( Constants::OPTION_PRODUCT_FORM_MAPPING, array() );
		}

		// Run data migration from old plugins
		self::run_data_migration();
	}

	/**
	 * Run data migration from old plugins
	 * 
	 * Migrates data from:
	 * - wc_qualiopi_steps
	 * - gravity_forms_siren_autocomplete
	 * 
	 * Principle: Backward compatibility + data preservation
	 */
	private static function run_data_migration(): void {
		$migrator = new DataMigrator();
		
		// Run migration
		$results = $migrator->run_migration();
		
		// Log results (double log pour garantir visibilité pendant activation)
		if ( $results['success'] ) {
			// Log structuré pour interface admin
			LoggingHelper::info( '[Activator] Migration completed', array(
				'options_migrated' => $results['options_migrated'],
				'tables_migrated'  => $results['tables_migrated']
			) );
			// Log brut pour debug.log (garantit visibilité même si plugin pas encore initialisé)
			error_log( sprintf(
				'[WC Qualiopi Formation] Migration completed: %d options, %d tables migrated',
				$results['options_migrated'],
				$results['tables_migrated']
			) );
		} else {
			// Log structuré pour interface admin
			LoggingHelper::error( '[Activator] Migration completed with errors', array(
				'errors' => $results['errors']
			) );
			// Log brut pour debug.log
			error_log( sprintf(
				'[WC Qualiopi Formation] Migration completed with errors: %s',
				implode( ', ', $results['errors'] )
			) );
		}
		
		// Note: Old data is preserved, not deleted
		// Allows rollback if needed
	}

	/**
	 * Verify requirements are met
	 * 
	 * @return bool True if all requirements met
	 */
	private static function check_requirements(): bool {
		// Check PHP version
		if ( version_compare( PHP_VERSION, WCQF_MIN_PHP_VERSION, '<' ) ) {
			return false;
		}

		// Check WordPress version
		global $wp_version;
		if ( version_compare( $wp_version, WCQF_MIN_WP_VERSION, '<' ) ) {
			return false;
		}

		// Check WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Check WooCommerce version
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WCQF_MIN_WC_VERSION, '<' ) ) {
			return false;
		}

		return true;
	}
}


