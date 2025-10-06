<?php
/**
 * Constants Class
 * 
 * Centralizes all plugin constants for better maintainability
 * Principle: Single Source of Truth (SSOT)
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
 * Class Constants
 * 
 * Provides centralized access to all plugin constants
 * No magic numbers - all values are named and documented
 */
final class Constants {

	/**
	 * Plugin version
	 */
	const VERSION = '1.0.0';

	/**
	 * Database table names
	 */
	const TABLE_PROGRESS = 'wcqf_progress';
	const TABLE_TRACKING = 'wcqf_tracking';
	const TABLE_AUDIT    = 'wcqf_audit';

	/**
	 * Progress steps
	 */
	const STEP_CART      = 'cart';
	const STEP_FORM      = 'form';
	const STEP_CHECKOUT  = 'checkout';
	const STEP_COMPLETED = 'completed';

	/**
	 * Token configuration
	 */
	const TOKEN_TTL_HOURS = 2; // Token expires after 2 hours
	const TOKEN_LENGTH    = 64; // Token length in bytes

	/**
	 * Session configuration
	 */
	const SESSION_KEY_TOKEN  = 'wcqf_token';
	const SESSION_TTL_MINUTES = 30; // Session expires after 30 minutes

	/**
	 * Option keys
	 */
	const OPTION_PRODUCT_FORM_MAPPING = 'wcqf_product_form_mapping';
	const OPTION_PLUGIN_VERSION       = 'wcqf_plugin_version';
	const OPTION_SETTINGS             = 'wcqf_settings';

	/**
	 * Secret names (for SecretManager)
	 * These should be defined as environment variables or constants in wp-config.php
	 */
	const SECRET_HMAC_KEY        = 'WCQF_HMAC_KEY';        // Required: HMAC token signing key
	const SECRET_ENCRYPTION_KEY  = 'WCQF_ENCRYPTION_KEY';  // Optional: Data encryption key
	const SECRET_SIREN_API_KEY   = 'WCQF_SIREN_API_KEY';   // Required if SIRET feature enabled
	const SECRET_OPENAI_API_KEY  = 'WCQF_OPENAI_API_KEY';  // Required if AI validation enabled

	/**
	 * Cache configuration
	 */
	const CACHE_PREFIX = 'wcqf_';

	/**
	 * Text domain for translations
	 */
	const TEXT_DOMAIN = 'wc-qualiopi-formation';

	/**
	 * API SIREN Configuration
	 */
	const API_SIREN_BASE_URL         = 'https://data.siren-api.fr';
	const API_SIREN_ENDPOINT_ETAB    = '/v3/etablissements';
	const API_SIREN_ENDPOINT_UL      = '/v3/unites_legales';
	const API_SIREN_TIMEOUT          = 10; // Timeout en secondes
	const API_SIREN_MAX_RETRIES      = 3;  // Nombre maximum de tentatives
	const API_SIREN_RETRY_WAIT       = 2;  // Attente entre tentatives (secondes)

	/**
	 * Audit event types
	 */
	const EVENT_CART_BLOCKED        = 'cart_blocked';
	const EVENT_TOKEN_GENERATED     = 'token_generated';
	const EVENT_FORM_STARTED        = 'form_started';
	const EVENT_FORM_SUBMITTED      = 'form_submitted';
	const EVENT_CHECKOUT_ACCESSED   = 'checkout_accessed';
	const EVENT_ORDER_CREATED       = 'order_created';

	/**
	 * Capability requirements
	 */
	const CAP_MANAGE_SETTINGS = 'manage_woocommerce';
	const CAP_VIEW_PROGRESS   = 'manage_woocommerce';

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @param string $table_name Table name without prefix
	 * @return string Full table name with prefix
	 */
	public static function get_table_name( string $table_name ): string {
		global $wpdb;
		return $wpdb->prefix . $table_name;
	}

	/**
	 * Get all progress step values
	 *
	 * @return array Array of valid step values
	 */
	public static function get_valid_steps(): array {
		return array(
			self::STEP_CART,
			self::STEP_FORM,
			self::STEP_CHECKOUT,
			self::STEP_COMPLETED,
		);
	}

	/**
	 * Get all audit event types
	 *
	 * @return array Array of valid event types
	 */
	public static function get_valid_event_types(): array {
		return array(
			self::EVENT_CART_BLOCKED,
			self::EVENT_TOKEN_GENERATED,
			self::EVENT_FORM_STARTED,
			self::EVENT_FORM_SUBMITTED,
			self::EVENT_CHECKOUT_ACCESSED,
			self::EVENT_ORDER_CREATED,
		);
	}

	/**
	 * Validate progress step
	 *
	 * @param string $step Step to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid_step( string $step ): bool {
		return in_array( $step, self::get_valid_steps(), true );
	}

	/**
	 * Validate event type
	 *
	 * @param string $event_type Event type to validate
	 * @return bool True if valid, false otherwise
	 */
	public static function is_valid_event_type( string $event_type ): bool {
		return in_array( $event_type, self::get_valid_event_types(), true );
	}
}


