<?php
/**
 * Cart Blocker - Logique de blocage du checkout
 * 
 * RESPONSABILITÉ UNIQUE : Déterminer si le checkout doit être bloqué
 * Analyse les produits du panier et leurs tests requis
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Utils\Mapping;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Cart\Helpers\UrlGenerator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartBlocker
 * 
 * Principe SRP : Une seule responsabilité = logique de blocage
 */
class CartBlocker {

/**
	 * Instance du validateur de tests
	 * 
	 * @var TestValidator
	 */
	private $test_validator;

	/**
	 * Constructeur
	 * 
	 * @param TestValidator $test_validator Instance du validateur
	 */
	public function __construct( TestValidator $test_validator ) {
		$this->test_validator = $test_validator;
	}

	/**
	 * Déterminer si le checkout doit être bloqué
	 * 
	 * @return bool True si blocage nécessaire
	 */
	public function should_block_checkout(): bool {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			LoggingHelper::debug( 'CartBlocker: WooCommerce not available' );
			return false;
		}

		if ( WC()->cart->is_empty() ) {
			LoggingHelper::debug( 'CartBlocker: Cart is empty' );
			return false;
		}

		$pending_tests = $this->get_pending_tests_info();
		$should_block = ! empty( $pending_tests );

		LoggingHelper::info(
			$should_block ? 'CartBlocker: Blocking checkout' : 'CartBlocker: Allowing checkout',
			[ 'pending_tests' => count( $pending_tests ) ]
		);

		return $should_block;
	}

	/**
	 * Obtenir les informations sur les tests en attente
	 * 
	 * @return array Tableau des tests requis non validés
	 */
	public function get_pending_tests_info(): array {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			return [];
		}

		$pending_tests = [];
		$current_user_id = get_current_user_id();
		$cart_items = WC()->cart->get_cart();

		LoggingHelper::debug( 'CartBlocker: Checking ' . count( $cart_items ) . ' cart items' );

		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];

			// Vérifier si ce produit nécessite un test
			$mapping = Mapping::get_for_product( $product_id );

			if ( ! $mapping || empty( $mapping['active'] ) ) {
				LoggingHelper::debug( "CartBlocker: Product {$product_id} - no mapping or inactive" );
				continue;
			}

			// Vérifier si le test est déjà validé
			if ( $this->test_validator->is_test_validated( $current_user_id, $product_id ) ) {
				LoggingHelper::debug( "CartBlocker: Product {$product_id} - test already validated" );
				continue;
			}

			// Ajouter aux tests en attente
			$test_info = $this->build_test_info( $product_id, $cart_item, $cart_item_key, $mapping );
			$pending_tests[] = $test_info;

			LoggingHelper::info( "CartBlocker: Product {$product_id} - added to pending tests" );
		}

		LoggingHelper::info( 'CartBlocker: Found ' . count( $pending_tests ) . ' pending tests' );
		return $pending_tests;
	}

	/**
	 * Construire les informations d'un test requis
	 * 
	 * @param int    $product_id    ID du produit
	 * @param array  $cart_item     Item du panier
	 * @param string $cart_item_key Clé de l'item
	 * @param array  $mapping       Configuration du mapping
	 * @return array Informations du test
	 */
	private function build_test_info( int $product_id, array $cart_item, string $cart_item_key, array $mapping ): array {
		return [
			'product_id'    => $product_id,
			'product_name'  => $cart_item['data']->get_name(),
			'test_url'      => UrlGenerator::get_test_url( $product_id, $cart_item_key ),
			'cart_item_key' => $cart_item_key,
			'mapping'       => $mapping,
		];
	}

	/**
	 * Vérifier si un produit spécifique nécessite un test
	 * 
	 * @param int $product_id ID du produit
	 * @return bool True si test requis
	 */
	public function product_requires_test( int $product_id ): bool {
		return Mapping::has_active_test( $product_id );
	}

	/**
	 * Obtenir le nombre de tests en attente
	 * 
	 * @return int Nombre de tests en attente
	 */
	public function count_pending_tests(): int {
		return count( $this->get_pending_tests_info() );
	}
}

