<?php
/**
 * Cart Restriction Handler - Logique de validation et blocage
 * 
 * RESPONSABILITÉ UNIQUE : Traiter les validations et bloquer le checkout
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Utils\Logger;
use WP_Error;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartRestrictionHandler
 * 
 * Principe SRP : Logique de validation UNIQUEMENT
 */
class CartRestrictionHandler {

	/**
	 * Configuration
	 * 
	 * @var array
	 */
	private $config;

	/**
	 * Logger
	 * 
	 * @var Logger
	 */
	private $logger;

	/**
	 * Flag pour éviter les doublons d'erreur
	 * 
	 * @var bool
	 */
	private $error_added = false;

	/**
	 * Constructeur
	 * 
	 * @param array  $config Configuration
	 * @param Logger $logger Instance du logger
	 */
	public function __construct( array $config, Logger $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Obtenir la quantité actuelle du panier
	 * 
	 * @return int
	 */
	public function get_cart_quantity(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		return (int) WC()->cart->get_cart_contents_count();
	}

	/**
	 * Vérifier si le panier est valide
	 * 
	 * @return bool
	 */
	public function is_cart_valid(): bool {
		$qty = $this->get_cart_quantity();
		return $qty <= $this->config['max_qty'];
	}

	/**
	 * Rediriger le checkout vers le panier si trop d'articles
	 */
	public function redirect_checkout_if_too_many(): void {
		// Seulement sur frontend
		if ( is_admin() ) {
			return;
		}

		// Seulement sur checkout
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		// Ne pas perturber REST/AJAX
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}

		// Vérifier le panier
		if ( $this->is_cart_valid() ) {
			return;
		}

		// Panier invalide → Redirection
		$cart_url = $this->get_cart_url();
		$message  = $this->get_error_message();

		if ( function_exists( 'wc_add_notice' ) ) {
			$message_with_link = sprintf(
				'%s <a href="%s">%s</a>',
				$message,
				esc_url( $cart_url ),
				__( 'Accéder au panier', 'wcqf' )
			);
			wc_add_notice( $message_with_link, 'error' );
		}

		$this->logger->info( 'CartRestriction: Redirecting to cart', [
			'cart_qty' => $this->get_cart_quantity(),
			'max_qty'  => $this->config['max_qty'],
		] );

		wp_safe_redirect( $cart_url );
		exit;
	}

	/**
	 * Bloquer checkout pour WooCommerce Blocks
	 * 
	 * @param WP_Error $errors  Erreurs existantes
	 * @param mixed    $request Requête
	 * @return WP_Error
	 */
	public function block_checkout_blocks( $errors, $request = null ) {
		// Éviter les doublons
		if ( $this->error_added ) {
			return $errors;
		}

		// Vérifier le panier
		if ( $this->is_cart_valid() ) {
			return $errors;
		}

		// Ajouter l'erreur
		if ( $errors instanceof WP_Error ) {
			$message = $this->get_error_message();
			$errors->add( $this->config['notice_key'], $message, 400 );
			$this->error_added = true;

			$this->logger->info( 'CartRestriction: Blocking checkout (Blocks)', [
				'cart_qty' => $this->get_cart_quantity(),
			] );
		}

		return $errors;
	}

	/**
	 * Bloquer checkout pour Legacy WooCommerce
	 * 
	 * @param array    $data   Données du checkout
	 * @param WP_Error $errors Erreurs
	 */
	public function block_checkout_legacy( $data, $errors ): void {
		// Vérifier le panier
		if ( $this->is_cart_valid() ) {
			return;
		}

		// Ajouter l'erreur
		if ( $errors instanceof WP_Error ) {
			$message = $this->get_error_message();
			$errors->add( $this->config['notice_key'], $message );

			$this->logger->info( 'CartRestriction: Blocking checkout (Legacy)', [
				'cart_qty' => $this->get_cart_quantity(),
			] );
		}
	}

	/**
	 * Nettoyer les notices si le panier devient valide
	 * 
	 * @param mixed ...$args Arguments du hook
	 */
	public function cleanup_notices_if_valid( ...$args ): void {
		// Si le panier est maintenant valide, nettoyer les notices d'erreur
		if ( $this->is_cart_valid() ) {
			$this->clear_restriction_notices();
		}
	}

	/**
	 * Nettoyer les notices de restriction
	 */
	private function clear_restriction_notices(): void {
		if ( ! function_exists( 'wc_get_notices' ) ) {
			return;
		}

		$notices = wc_get_notices( 'error' );

		if ( empty( $notices ) ) {
			return;
		}

		// Filtrer les notices
		$filtered = [];
		$removed  = 0;

		foreach ( $notices as $notice ) {
			$notice_text = is_array( $notice ) ? $notice['notice'] : $notice;

			if ( $this->is_restriction_notice( $notice_text ) ) {
				$removed++;
				continue;
			}

			$filtered[] = $notice;
		}

		// Si des notices ont été retirées, nettoyer et remettre
		if ( $removed > 0 ) {
			wc_clear_notices();

			foreach ( $filtered as $notice ) {
				$notice_text = is_array( $notice ) ? $notice['notice'] : $notice;
				wc_add_notice( $notice_text, 'error' );
			}

			$this->logger->debug( 'CartRestriction: Cleaned restriction notices', [
				'removed_count' => $removed,
			] );
		}
	}

	/**
	 * Vérifier si une notice est liée à la restriction
	 * 
	 * @param string $notice_text Texte de la notice
	 * @return bool
	 */
	private function is_restriction_notice( string $notice_text ): bool {
		$keywords = [
			'formations',
			'formation à la fois',
			'conformité',
			'formations excédentaires',
			$this->config['notice_key'],
		];

		foreach ( $keywords as $keyword ) {
			if ( stripos( $notice_text, $keyword ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Obtenir l'URL du panier
	 * 
	 * @return string
	 */
	private function get_cart_url(): string {
		return function_exists( 'wc_get_cart_url' ) 
			? wc_get_cart_url() 
			: home_url( '/' . $this->config['cart_url'] );
	}

	/**
	 * Obtenir le message d'erreur
	 * 
	 * @return string
	 */
	private function get_error_message(): string {
		$qty = $this->get_cart_quantity();
		return sprintf( $this->config['error_message'], $qty );
	}

	/**
	 * Mettre à jour la configuration
	 * 
	 * @param array $config Nouvelle configuration
	 */
	public function update_config( array $config ): void {
		$this->config = $config;
	}
}

