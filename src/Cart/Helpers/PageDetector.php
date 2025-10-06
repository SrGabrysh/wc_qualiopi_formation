<?php
/**
 * Page Detector - Détection des pages WooCommerce
 * 
 * RESPONSABILITÉ UNIQUE : Identifier si on est sur cart ou checkout
 * Support des URLs françaises (/panier/, /commander/)
 * 
 * @package WcQualiopiFormation\Cart\Helpers
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart\Helpers;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe PageDetector
 * 
 * Principe SRP : Une seule responsabilité = détection de pages
 */
class PageDetector {

	/**
	 * Vérifier si nous sommes sur la page panier
	 * 
	 * Support de l'URL française /panier/ et anglaise /cart/
	 * 
	 * NOTE: TB-Formation utilise des URLs françaises :
	 * - Panier : /panier/ (au lieu de /cart/)
	 * - Checkout : /commander/ (au lieu de /checkout/)
	 * 
	 * @return bool True si page panier
	 */
	public static function is_cart_page(): bool {
		// Test WordPress standard
		if ( is_cart() ) {
			return true;
		}
		
		// Test alternatif pour URL française /panier/
		if ( function_exists( 'wc_get_page_id' ) ) {
			$cart_page_id = wc_get_page_id( 'cart' );
			if ( $cart_page_id && is_page( $cart_page_id ) ) {
				return true;
			}
		}
		
		// Test par URL pour /panier/ ou /cart/
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/panier/' ) !== false || strpos( $request_uri, '/cart/' ) !== false ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Vérifier si nous sommes sur la page checkout
	 * 
	 * Support de l'URL française /commander/ et anglaise /checkout/
	 * 
	 * NOTE: TB-Formation utilise /commander/ au lieu de /checkout/
	 * 
	 * @return bool True si page checkout
	 */
	public static function is_checkout_page(): bool {
		// Test WordPress standard
		if ( is_checkout() ) {
			return true;
		}
		
		// Test alternatif pour URL française /commander/
		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			if ( $checkout_page_id && is_page( $checkout_page_id ) ) {
				return true;
			}
		}
		
		// Test par URL pour /commander/ ou /checkout/
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/commander/' ) !== false || strpos( $request_uri, '/checkout/' ) !== false ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Vérifier si nous sommes en contexte admin, cron ou AJAX
	 * 
	 * @return bool True si contexte non-frontend
	 */
	public static function is_backend_context(): bool {
		return is_admin() || wp_doing_cron() || wp_doing_ajax();
	}
}

