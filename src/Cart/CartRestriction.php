<?php
/**
 * Cart Restriction - Manager principal
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration de la restriction à 1 produit max
 * 
 * Fusionné depuis wc_checkout_guard (v1.1.1)
 * Simplifié et adapté pour wc_qualiopi_formation
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Helpers\LoggingHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartRestriction
 * 
 * Principe SRP : Orchestration UNIQUEMENT
 * Délègue au Handler et Renderer
 */
class CartRestriction {

	/**
	 * Instance unique (singleton)
	 * 
	 * @var CartRestriction|null
	 */
	private static $instance = null;

/**
	 * Instance du handler
	 * 
	 * @var CartRestrictionHandler
	 */
	private $handler;

	/**
	 * Instance du renderer
	 * 
	 * @var CartRestrictionRenderer
	 */
	private $renderer;

	/**
	 * Configuration du module
	 * 
	 * @var array
	 */
	private $config;

	/**
	 * Obtenir l'instance unique
	 * 
	 * @return CartRestriction
	 */
	public static function get_instance(): CartRestriction {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur privé (singleton)
	 */
	private function __construct() {
		$this->config = $this->get_default_config();

		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Configuration par défaut
	 * 
	 * @return array
	 */
	private function get_default_config(): array {
		return [
			'max_qty'        => 1,
			'error_message'  => __( 'Erreur : Votre panier contient %d formations. Vous ne pouvez commander qu\'une formation à la fois pour des raisons de suivi et de conformité. Veuillez supprimer les formations excédentaires.', 'wcqf' ),
			'notice_key'     => 'wcqf_qty_limit',
			'cart_url'       => 'panier',
			'checkout_page'  => 'commander',
		];
	}

	/**
	 * Initialiser les composants
	 */
	private function init_components(): void {
		$this->handler  = new CartRestrictionHandler( $this->config );
		$this->renderer = new CartRestrictionRenderer( $this->config );
	}

	/**
	 * Initialiser les hooks WordPress
	 */
	private function init_hooks(): void {
		LoggingHelper::info( 'CartRestriction: Initializing hooks...' );

		// 1) Redirection checkout si >1 produit
		add_action( 'template_redirect', [ $this->handler, 'redirect_checkout_if_too_many' ], 20 );

		// 2) Bloquer validation checkout (Blocks)
		add_filter( 'woocommerce_store_api_cart_errors', [ $this->handler, 'block_checkout_blocks' ], 10, 2 );

		// 3) Bloquer validation checkout (Legacy)
		add_action( 'woocommerce_after_checkout_validation', [ $this->handler, 'block_checkout_legacy' ], 10, 2 );

		// 4) Afficher notice sur le panier
		add_action( 'woocommerce_before_cart', [ $this->renderer, 'maybe_show_cart_notice' ], 10 );

		// 5) Nettoyer notices obsolètes
		add_action( 'woocommerce_after_cart_item_quantity_update', [ $this->handler, 'cleanup_notices_if_valid' ], 20, 4 );
		add_action( 'woocommerce_cart_item_removed', [ $this->handler, 'cleanup_notices_if_valid' ], 20, 2 );

		LoggingHelper::info( 'CartRestriction: Hooks registered' );
	}

	/**
	 * Vérifier si le panier est valide (≤ max_qty)
	 * 
	 * @return bool
	 */
	public function is_cart_valid(): bool {
		return $this->handler->is_cart_valid();
	}

	/**
	 * Obtenir la quantité actuelle du panier
	 * 
	 * @return int
	 */
	public function get_cart_quantity(): int {
		return $this->handler->get_cart_quantity();
	}

	/**
	 * Obtenir les statistiques du module
	 * 
	 * @return array
	 */
	public function get_stats(): array {
		return [
			'max_qty_allowed'  => $this->config['max_qty'],
			'current_cart_qty' => $this->get_cart_quantity(),
			'is_cart_valid'    => $this->is_cart_valid(),
		];
	}

	/**
	 * Mettre à jour la configuration
	 * 
	 * @param array $new_config Nouvelle configuration
	 */
	public function update_config( array $new_config ): void {
		$this->config = array_merge( $this->config, $new_config );
		$this->handler->update_config( $this->config );
		$this->renderer->update_config( $this->config );
	}
}

