<?php
/**
 * Test Validator - Validation des tests de positionnement
 * 
 * RESPONSABILITÉ UNIQUE : Vérifier si un utilisateur a validé un test
 * Vérifie : Session WooCommerce + User Meta
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Utils\Logger;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe TestValidator
 * 
 * Principe SRP : Une seule responsabilité = validation des tests
 */
class TestValidator {

	/**
	 * Instance du logger
	 * 
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructeur
	 * 
	 * @param Logger $logger Instance du logger
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Vérifier si un test est validé pour un utilisateur/produit
	 * 
	 * Vérifie dans l'ordre :
	 * 1. Session WooCommerce (prioritaire)
	 * 2. User Meta (toutes les variantes possibles)
	 * 
	 * @param int $user_id    ID de l'utilisateur
	 * @param int $product_id ID du produit
	 * @return bool True si test validé
	 */
	public function is_test_validated( int $user_id, int $product_id ): bool {
		$this->logger->debug( "TestValidator: Checking validation for user {$user_id}, product {$product_id}" );

		// 1. Vérification de la session WooCommerce EN PREMIER
		$session_validated = SessionManager::is_solved( $product_id );
		
		if ( $session_validated ) {
			$this->logger->info( "TestValidator: Product {$product_id} validated via SESSION" );
			return true;
		}

		// 2. Vérification user meta (TOUTES les variantes possibles)
		if ( $user_id > 0 ) {
			$is_validated_meta = $this->check_user_meta_validation( $user_id, $product_id );
			
			if ( $is_validated_meta ) {
				$this->logger->info( "TestValidator: Product {$product_id} validated via USER META" );
				return true;
			}
		}

		$this->logger->info( "TestValidator: Product {$product_id} NOT validated" );
		return false;
	}

	/**
	 * Vérifier la validation via user meta
	 * 
	 * Vérifie toutes les clés meta possibles (rétro-compatibilité)
	 * 
	 * @param int $user_id    ID de l'utilisateur
	 * @param int $product_id ID du produit
	 * @return bool True si validé via meta
	 */
	private function check_user_meta_validation( int $user_id, int $product_id ): bool {
		// Toutes les clés meta possibles (ancien + nouveau format)
		$meta_keys = [
			"_wcqf_testpos_ok_{$product_id}",       // Nouveau format
			"_wcqf_testpos_validated_{$product_id}", // Nouveau format alternatif
			"_wcqs_testpos_ok_{$product_id}",       // Ancien format (rétro-compatibilité)
			"_wcqs_testpos_validated_{$product_id}", // Ancien format alternatif
			"_wcqs_test_{$product_id}",             // Très ancien format
			"_qualiopi_test_{$product_id}",         // Autre variante
		];

		foreach ( $meta_keys as $meta_key ) {
			$meta_value = get_user_meta( $user_id, $meta_key, true );

			if ( ! empty( $meta_value ) ) {
				$this->logger->debug( "TestValidator: Found meta {$meta_key}" );

				// Si c'est une date ISO, vérifier qu'elle n'est pas expirée
				if ( $this->is_date_string( $meta_value ) ) {
					if ( $this->is_validation_expired( $meta_value, $user_id, $meta_key ) ) {
						continue; // Essayer la prochaine clé
					}
				}

				// Validation trouvée et valide
				return true;
			}
		}

		return false;
	}

	/**
	 * Vérifier si une chaîne est une date
	 * 
	 * @param mixed $value Valeur à tester
	 * @return bool True si c'est une date
	 */
	private function is_date_string( $value ): bool {
		return is_string( $value ) && strtotime( $value ) !== false;
	}

	/**
	 * Vérifier si une validation est expirée (>24h)
	 * 
	 * @param string $meta_value    Date de validation
	 * @param int    $user_id       ID utilisateur
	 * @param string $meta_key      Clé meta
	 * @return bool True si expirée
	 */
	private function is_validation_expired( string $meta_value, int $user_id, string $meta_key ): bool {
		$validation_time = strtotime( $meta_value );
		$is_expired = ( time() - $validation_time ) >= DAY_IN_SECONDS;

		if ( $is_expired ) {
			// Nettoyer la meta expirée
			delete_user_meta( $user_id, $meta_key );
			$this->logger->info( "TestValidator: Expired meta {$meta_key} deleted" );
			return true;
		}

		return false;
	}

	/**
	 * Forcer la validation d'un test (pour tests unitaires)
	 * 
	 * @param int  $user_id    ID utilisateur
	 * @param int  $product_id ID produit
	 * @param bool $validated  État de validation
	 * @return void
	 */
	public function force_validation( int $user_id, int $product_id, bool $validated = true ): void {
		if ( $validated ) {
			// Marquer en session
			SessionManager::set_solved( $product_id, 30 );
			
			// Marquer en user meta
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, "_wcqf_testpos_ok_{$product_id}", current_time( 'c' ) );
			}
			
			$this->logger->info( "TestValidator: Forced validation for user {$user_id}, product {$product_id}" );
		} else {
			// Supprimer de la session
			SessionManager::unset_solved( $product_id );
			
			// Supprimer user meta
			if ( $user_id > 0 ) {
				delete_user_meta( $user_id, "_wcqf_testpos_ok_{$product_id}" );
			}
			
			$this->logger->info( "TestValidator: Cleared validation for user {$user_id}, product {$product_id}" );
		}
	}
}

