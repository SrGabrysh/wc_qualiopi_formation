<?php
/**
 * Mapping Cache
 * 
 * RESPONSABILITÉ UNIQUE : Gestion du cache et chargement du mapping
 * 
 * @package WcQualiopiFormation\Utils\Mapping
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Utils\Mapping;

use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe MappingCache
 * 
 * Principe SRP : Cache et chargement UNIQUEMENT
 */
class MappingCache {

	/**
	 * Cache statique du mapping
	 *
	 * @var array|null
	 */
	private static $mapping_cache = null;

	/**
	 * Get cached mapping or load from database
	 * 
	 * @return array Mapping complet au format normalisé
	 */
	public static function get(): array {
		// Return cache if available
		if ( null !== self::$mapping_cache ) {
			return self::$mapping_cache;
		}

		// Load from database
		self::$mapping_cache = self::load_from_database();
		return self::$mapping_cache;
	}

	/**
	 * Load mapping from database with retro-compatibility
	 * 
	 * @return array Loaded mapping
	 */
	private static function load_from_database(): array {
		// Try new format first (wcqf_product_form_mapping)
		$new_format = get_option( Constants::OPTION_PRODUCT_FORM_MAPPING, array() );

		if ( ! empty( $new_format ) && is_array( $new_format ) ) {
			return self::normalize_simple_mapping( $new_format );
		}

		// Try old format (wcqs_testpos_mapping) for retro-compatibility
		$old_format = get_option( 'wcqs_testpos_mapping', array() );

		if ( ! empty( $old_format ) && is_array( $old_format ) ) {
			// Old format is already in correct format: product_{ID} => array
			return $old_format;
		}

		// No mapping found
		return array();
	}

	/**
	 * Normalize simple mapping to full format
	 * 
	 * @param array $simple_mapping Simple mapping {product_id} => {page_id}
	 * @return array Normalized mapping product_{ID} => array
	 */
	private static function normalize_simple_mapping( array $simple_mapping ): array {
		$normalized = array();

		foreach ( $simple_mapping as $product_id => $page_id ) {
			// Ignore non-numeric keys
			if ( ! is_numeric( $product_id ) ) {
				continue;
			}

			$product_id = (int) $product_id;
			$page_id    = (int) $page_id;

			if ( $product_id <= 0 || $page_id <= 0 ) {
				continue;
			}

			// Create full configuration
			$key                 = 'product_' . $product_id;
			$normalized[ $key ] = array(
				'page_id'     => $page_id,
				'active'      => true,
				'form_source' => 'gravityforms',
				'product_id'  => $product_id,
			);
		}

		return $normalized;
	}

	/**
	 * Clear cache
	 */
	public static function clear(): void {
		self::$mapping_cache = null;
	}

	/**
	 * Refresh cache by clearing and reloading
	 * 
	 * @return array Refreshed mapping
	 */
	public static function refresh(): array {
		self::clear();
		return self::get();
	}

	/**
	 * Check if cache is loaded
	 * 
	 * @return bool True if cache is loaded
	 */
	public static function is_cached(): bool {
		return null !== self::$mapping_cache;
	}

	/**
	 * Get cache size
	 * 
	 * @return int Number of products in cache
	 */
	public static function get_size(): int {
		if ( null === self::$mapping_cache ) {
			return 0;
		}
		return count( self::$mapping_cache );
	}

	/**
	 * Migrate old mapping to new format
	 * 
	 * @return bool True if migration was successful
	 */
	public static function migrate_old_mapping(): bool {
		// Check if old mapping exists
		$old_mapping = get_option( 'wcqs_testpos_mapping', null );

		if ( null === $old_mapping || ! is_array( $old_mapping ) ) {
			return false; // No old mapping to migrate
		}

		// Check if new mapping already exists
		$new_mapping = get_option( Constants::OPTION_PRODUCT_FORM_MAPPING, null );

		if ( null !== $new_mapping && ! empty( $new_mapping ) ) {
			return false; // New mapping already exists, don't override
		}

		// Convert old format to new simple format
		$simple_mapping = array();

		foreach ( $old_mapping as $key => $config ) {
			// Extract product ID from key (product_{ID})
			if ( ! preg_match( '/^product_(\d+)$/', $key, $matches ) ) {
				continue;
			}

			$product_id = (int) $matches[1];
			$page_id    = isset( $config['page_id'] ) ? (int) $config['page_id'] : 0;

			if ( $product_id > 0 && $page_id > 0 ) {
				$simple_mapping[ $product_id ] = $page_id;
			}
		}

		// Save new format
		if ( ! empty( $simple_mapping ) ) {
			update_option( Constants::OPTION_PRODUCT_FORM_MAPPING, $simple_mapping );
			self::clear(); // Clear cache to force reload
			return true;
		}

		return false;
	}
}

