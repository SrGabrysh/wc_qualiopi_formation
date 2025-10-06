<?php
/**
 * Mapping Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des requêtes de mapping
 * 
 * Gestion du mapping produit → page de test avec rétro-compatibilité
 *
 * @package WcQualiopiFormation\Utils
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Utils;

use WcQualiopiFormation\Utils\Mapping\MappingCache;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Mapping (Manager)
 * 
 * Orchestrates mapping queries and delegates caching
 */
class Mapping {

	/**
	 * Get complete mapping - DELEGATED
	 * 
	 * @return array Complete mapping in normalized format
	 */
	public static function get_mapping(): array {
		return MappingCache::get();
	}

	/**
	 * Get mapping configuration for a specific product
	 * 
	 * @param int $product_id Product ID
	 * @return array|null Configuration array or null if not found
	 */
	public static function get_for_product( int $product_id ): ?array {
		$mapping = self::get_mapping();
		$key     = 'product_' . $product_id;

		if ( ! isset( $mapping[ $key ] ) ) {
			return null;
		}

		$config = $mapping[ $key ];

		// Ensure product_id is set
		if ( ! isset( $config['product_id'] ) ) {
			$config['product_id'] = $product_id;
		}

		return $config;
	}

	/**
	 * Check if product has an active test
	 * 
	 * @param int $product_id Product ID
	 * @return bool True if product has active test
	 */
	public static function has_active_test( int $product_id ): bool {
		$config = self::get_for_product( $product_id );

		if ( null === $config ) {
			return false;
		}

		// Check if active (default: true if not specified)
		$is_active = $config['active'] ?? true;

		// Check if page exists
		$page_id = $config['page_id'] ?? 0;

		return $is_active && $page_id > 0 && get_post_status( $page_id ) === 'publish';
	}

	/**
	 * Get test URL for a product
	 * 
	 * @param int   $product_id   Product ID
	 * @param array $query_params Additional query parameters (optional)
	 * @return string|null Test URL or null if not found
	 */
	public static function get_test_url( int $product_id, array $query_params = array() ): ?string {
		$config = self::get_for_product( $product_id );

		if ( null === $config ) {
			return null;
		}

		$page_id = $config['page_id'] ?? 0;

		if ( $page_id <= 0 ) {
			return null;
		}

		$url = get_permalink( $page_id );

		if ( ! $url ) {
			return null;
		}

		// Add query parameters if provided
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		return $url;
	}

	/**
	 * Get all products with mapping
	 * 
	 * @param bool $active_only Return only active products (default: false)
	 * @return array Array of product IDs
	 */
	public static function get_all_products( bool $active_only = false ): array {
		$mapping     = self::get_mapping();
		$product_ids = array();

		foreach ( $mapping as $key => $config ) {
			// Extract product ID from key
			if ( ! preg_match( '/^product_(\d+)$/', $key, $matches ) ) {
				continue;
			}

			$product_id = (int) $matches[1];

			// Filter by active status if requested
			if ( $active_only ) {
				$is_active = $config['active'] ?? true;
				if ( ! $is_active ) {
					continue;
				}
			}

			$product_ids[] = $product_id;
		}

		return $product_ids;
	}

	/**
	 * Clear cache - DELEGATED
	 */
	public static function clear_cache(): void {
		MappingCache::clear();
	}

	/**
	 * Refresh cache - DELEGATED
	 * 
	 * @return array Refreshed mapping
	 */
	public static function refresh_cache(): array {
		return MappingCache::refresh();
	}

	/**
	 * Migrate old mapping - DELEGATED
	 * 
	 * @return bool True if migration successful
	 */
	public static function migrate_old_mapping(): bool {
		return MappingCache::migrate_old_mapping();
	}

	/**
	 * Check if product has mapping (active or not)
	 * 
	 * @param int $product_id Product ID
	 * @return bool True if product has mapping
	 */
	public static function has_mapping( int $product_id ): bool {
		return null !== self::get_for_product( $product_id );
	}

	/**
	 * Get page ID for product
	 * 
	 * @param int $product_id Product ID
	 * @return int|null Page ID or null if not found
	 */
	public static function get_page_id( int $product_id ): ?int {
		$config = self::get_for_product( $product_id );

		if ( null === $config ) {
			return null;
		}

		$page_id = $config['page_id'] ?? 0;

		return $page_id > 0 ? $page_id : null;
	}

	/**
	 * Get form source for product
	 * 
	 * @param int $product_id Product ID
	 * @return string|null Form source or null if not found
	 */
	public static function get_form_source( int $product_id ): ?string {
		$config = self::get_for_product( $product_id );

		if ( null === $config ) {
			return null;
		}

		return $config['form_source'] ?? 'gravityforms';
	}
}
