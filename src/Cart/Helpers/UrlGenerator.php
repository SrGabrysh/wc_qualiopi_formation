<?php
/**
 * URL Generator - Génération des URLs de test
 * 
 * RESPONSABILITÉ UNIQUE : Créer les URLs vers les pages de test
 * 
 * @package WcQualiopiFormation\Cart\Helpers
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart\Helpers;

use WcQualiopiFormation\Utils\Mapping;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe UrlGenerator
 * 
 * Principe SRP : Une seule responsabilité = génération d'URLs
 */
class UrlGenerator {

	/**
	 * Obtenir l'URL du test pour un produit spécifique
	 * 
	 * @param int    $product_id    ID du produit
	 * @param string $cart_item_key Clé de l'item dans le panier
	 * @return string URL du test ou chaîne vide si non configuré
	 */
	public static function get_test_url( int $product_id, string $cart_item_key ): string {
		$mapping = Mapping::get_for_product( $product_id );
		
		if ( ! $mapping || empty( $mapping['page_id'] ) ) {
			return '';
		}
		
		$test_page_url = get_permalink( $mapping['page_id'] );
		if ( ! $test_page_url ) {
			return '';
		}
		
		// Ajouter les paramètres nécessaires
		$params = [
			'wcqf_product_id' => $product_id,
			'wcqf_cart_key'   => $cart_item_key,
			'wcqf_return'     => urlencode( wc_get_cart_url() ),
		];
		
		return add_query_arg( $params, $test_page_url );
	}

	/**
	 * Obtenir l'URL du test pour le premier produit du panier
	 * 
	 * Helper pour redirection rapide
	 * 
	 * @return string|null URL du test ou null si panier vide
	 */
	public static function get_test_url_for_cart(): ?string {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}
		
		$cart_items = WC()->cart->get_cart();
		$first_item = reset( $cart_items );
		
		if ( ! $first_item || empty( $first_item['product_id'] ) ) {
			return null;
		}
		
		$product_id = (int) $first_item['product_id'];
		$mapping = Mapping::get_for_product( $product_id );
		
		if ( ! $mapping || empty( $mapping['active'] ) || empty( $mapping['page_id'] ) ) {
			return null;
		}
		
		$url = get_permalink( (int) $mapping['page_id'] );
		return $url ?: null;
	}

	/**
	 * Filtrer l'URL de checkout pour rediriger vers le test
	 * 
	 * @param string $checkout_url URL de checkout actuelle
	 * @param bool   $should_block Faut-il bloquer ?
	 * @return string URL (modifiée ou originale)
	 */
	public static function filter_checkout_url( string $checkout_url, bool $should_block ): string {
		if ( ! $should_block ) {
			return $checkout_url;
		}
		
		$test_url = self::get_test_url_for_cart();
		return $test_url ?: $checkout_url;
	}
}

