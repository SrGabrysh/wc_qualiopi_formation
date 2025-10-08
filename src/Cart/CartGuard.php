<?php
/**
 * ⚠️ WARNING CRITIQUE: TB-Formation utilise WooCommerce BLOCKS
 * Les hooks classiques woocommerce_proceed_to_checkout, etc. ne fonctionnent PAS.
 * Utiliser JavaScript + Store API + template_redirect pour les modifications.
 * 
 * CartGuard - Manager principal du blocage panier
 * 
 * RESPONSABILITÉ : Orchestration des hooks et coordination des modules
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Core\Plugin;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Utils\Mapping;
use WcQualiopiFormation\Cart\Helpers\PageDetector;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Cart\Helpers\UrlGenerator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartGuard
 * 
 * Principe SRP : Orchestration UNIQUEMENT (délègue aux autres classes)
 */
class CartGuard {

	/**
	 * Instance unique (singleton)
	 * 
	 * @var CartGuard|null
	 */
	private static $instance = null;

/**
	 * Instance du validateur de tests
	 * 
	 * @var TestValidator
	 */
	private $test_validator;

	/**
	 * Instance du blocker
	 * 
	 * @var CartBlocker
	 */
	private $cart_blocker;

	/**
	 * Instance du renderer
	 * 
	 * @var CartRenderer
	 */
	private $cart_renderer;

	/**
	 * Instance du gestionnaire d'assets
	 * 
	 * @var CartAssets
	 */
	private $cart_assets;

	/**
	 * Obtenir l'instance unique
	 * 
	 * @return CartGuard
	 */
	public static function get_instance(): CartGuard {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructeur privé (singleton)
	 */
	private function __construct() {
		$this->test_validator = new TestValidator();
		$this->cart_blocker = new CartBlocker( $this->test_validator );
		$this->cart_renderer = new CartRenderer();
		$this->cart_assets = new CartAssets();

		$this->init_hooks();
	}

	/**
	 * Initialiser les hooks WooCommerce
	 * CORRECTION EXPERTS: Hooks multiples + garde serveur universelle
	 */
	private function init_hooks(): void {
		LoggingHelper::log_wp_hook( null, 'CartGuard', 'init_hooks' );

		// 1) Garde universelle par redirection (classique + Blocks)
		add_action( 'template_redirect', [ $this, 'guard_template_redirect' ], 0 );

		// 2) Hooks WooCommerce classiques (fallback)
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_replace_checkout_button' ], 5 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_add_test_notice' ], 10 );

		// 3) Hooks alternatifs plus fiables
		add_action( 'woocommerce_cart_actions', [ $this, 'maybe_add_test_notice' ], 10 );
		add_action( 'woocommerce_before_cart_totals', [ $this, 'maybe_add_test_notice_before_totals' ], 5 );

		// 4) Intercepter Store API (Checkout Block)
		add_filter( 'rest_request_before_callbacks', [ $this, 'intercept_store_api_checkout' ], 5, 3 );

		// 5) Notices serveur (compatibles Blocks)
		add_action( 'wp', [ $this, 'maybe_add_server_notice' ] );

		// 6) Filtrer URL checkout (classique)
		add_filter( 'woocommerce_get_checkout_url', [ $this, 'filter_checkout_url_when_blocked' ], 10, 1 );

		// Styles et scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Debug hook pour vérifier l'exécution
		add_action( 'wp_footer', [ $this, 'debug_cart_state' ] );

		// Modification du bouton pour WooCommerce Blocks (JavaScript)
		add_action( 'wp_footer', [ $this, 'modify_checkout_button_blocks' ] );

		LoggingHelper::log_wp_hook( null, 'CartGuard', 'hooks_registered' );
	}

	/**
	 * Vérifier si l'enforcement du panier est activé
	 * 
	 * @return bool
	 */
	private function is_cart_enforcement_enabled(): bool {
		// Récupérer directement depuis les options WordPress
		$flags = get_option( 'wcqf_flags', array( 'enforce_cart' => true ) );
		return ! empty( $flags['enforce_cart'] );
	}

	/**
	 * Garde universelle par redirection (Expert #1)
	 */
	public function guard_template_redirect(): void {
		// Security: Check nonce for admin actions
		if ( is_admin() && ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'cart_guard_action' ) ) {
			wp_die( __( 'Security check failed', 'wcqf' ) );
		}

		if ( PageDetector::is_backend_context() ) {
			return;
		}

		if ( ! $this->cart_blocker->should_block_checkout() ) {
			return;
		}

		// Si on est sur le checkout, empêcher l'accès direct
		if ( PageDetector::is_checkout_page() ) {
			$test_url = UrlGenerator::get_test_url_for_cart();
			if ( $test_url ) {
				wp_safe_redirect( esc_url_raw( $test_url ) );
				exit;
			}
		}
	}

	/**
	 * Remplacer le bouton checkout si nécessaire
	 */
	public function maybe_replace_checkout_button(): void {
		if ( ! $this->is_cart_enforcement_enabled() ) {
			return;
		}

		if ( ! $this->cart_blocker->should_block_checkout() ) {
			return;
		}

		LoggingHelper::log_wp_hook( null, 'CartGuard', 'blocking_checkout' );

		// Supprimer le bouton checkout par défaut
		remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );

		// Ajouter notre message personnalisé à la place
		add_action( 'woocommerce_proceed_to_checkout', [ $this->cart_renderer, 'render_blocked_checkout_message' ], 20 );
	}

	/**
	 * Ajouter la notice de test requis
	 */
	public function maybe_add_test_notice(): void {
		if ( ! $this->is_cart_enforcement_enabled() ) {
			return;
		}

		$pending_tests = $this->cart_blocker->get_pending_tests_info();

		if ( empty( $pending_tests ) ) {
			return;
		}

		$this->cart_renderer->render_all_test_notices( $pending_tests );
	}

	/**
	 * Hook alternatif avant les totaux (Expert #2)
	 */
	public function maybe_add_test_notice_before_totals(): void {
		$this->maybe_add_test_notice();
	}

	/**
* Interception Store API pour WooCommerce Blocks (Expert #1)
	 */
	public function intercept_store_api_checkout( $response, $handler, \WP_REST_Request $request ) {
		// Security: Validate request
		$route = SanitizationHelper::sanitize_name( $request->get_route() );
		$method = SanitizationHelper::sanitize_name( $request->get_method() );

		// Routes Checkout Store API
		if ( $method === 'POST' && preg_match( '#^/wc/store(/v[0-9]+)?/checkout$#', $route ) ) {
			// [CORRECTION 2025-10-07] Retirer la vérification current_user_can('read') qui bloque les invités
			// La logique de blocage should_block_checkout() suffit pour contrôler l'accès

			if ( $this->cart_blocker->should_block_checkout() ) {
				return new \WP_Error(
					'wcqf_checkout_blocked',
					__( 'Le test de positionnement doit être validé avant le paiement.', Constants::TEXT_DOMAIN ),
					[ 'status' => 403 ]
				);
			}
		}

		return $response;
	}

	/**
	 * Notices serveur compatibles Blocks (Expert #1)
	 */
	public function maybe_add_server_notice(): void {
		if ( ! PageDetector::is_cart_page() && ! PageDetector::is_checkout_page() ) {
			return;
		}

		$should_block = $this->cart_blocker->should_block_checkout();
		$this->cart_renderer->add_server_notice( $should_block );
	}

	/**
	 * Filtrer URL checkout (Expert #1)
	 */
	public function filter_checkout_url_when_blocked( string $url ): string {
		// Security: Sanitize URL
		$url = esc_url_raw( $url );
		$should_block = $this->cart_blocker->should_block_checkout();
		return UrlGenerator::filter_checkout_url( $url, $should_block );
	}

	/**
	 * Charger les assets CSS/JS
	 */
	public function enqueue_assets(): void {
		$this->cart_assets->enqueue_assets();
	}

	/**
	 * Modifier le bouton checkout via JavaScript pour WooCommerce Blocks
	 */
	public function modify_checkout_button_blocks(): void {
		$should_block = $this->cart_blocker->should_block_checkout();
		$test_url = UrlGenerator::get_test_url_for_cart() ?? '';
		
		$this->cart_assets->modify_checkout_button_blocks( $should_block, $test_url );
	}

	/**
	 * Debug de l'état du panier
	 */
	public function debug_cart_state(): void {
		$enforcement_enabled = $this->is_cart_enforcement_enabled();
		$should_block = $this->cart_blocker->should_block_checkout();
		$pending_tests = $this->cart_blocker->get_pending_tests_info();
		
		// Préparer les détails de validation
		$validation_details = $this->build_validation_details();

		$this->cart_assets->debug_cart_state( $enforcement_enabled, $should_block, $pending_tests, $validation_details );
	}

	/**
	 * Construire les détails de validation pour debug
	 * 
	 * @return array Détails de validation par produit
	 */
	private function build_validation_details(): array {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
			return [];
		}

		$validation_details = [];
		$cart_items = WC()->cart->get_cart();
		$user_id = get_current_user_id();

		foreach ( $cart_items as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$mapping = Mapping::get_for_product( $product_id );
			$is_validated = $this->test_validator->is_test_validated( $user_id, $product_id );

			$validation_details[] = [
				'product_id'     => $product_id,
				'has_mapping'    => ! empty( $mapping ),
				'mapping_active' => ! empty( $mapping['active'] ),
				'is_validated'   => $is_validated,
				'page_id'        => $mapping['page_id'] ?? null,
			];
		}

		return $validation_details;
	}

	/**
	 * Forcer la validation d'un test (pour tests unitaires)
	 * 
	 * @param int  $user_id    ID utilisateur
	 * @param int  $product_id ID produit
	 * @param bool $validated  État de validation
	 * @return void
	 */
	public function force_test_validation( int $user_id, int $product_id, bool $validated = true ): void {
		// Security: Check admin capabilities
		if ( ! current_user_can( Constants::CAP_MANAGE_SETTINGS ) ) {
			wp_die( __( 'Insufficient permissions', Constants::TEXT_DOMAIN ) );
		}

		// Security: Sanitize inputs
		$user_id = absint( $user_id );
		$product_id = absint( $product_id );
		$validated = (bool) $validated;

		$this->test_validator->force_validation( $user_id, $product_id, $validated );
	}

	/**
	 * Vider le cache (pour tests)
	 */
	public function clear_cache(): void {
		Mapping::clear_cache();
		LoggingHelper::log_cache_operation( null, 'clear', 'CartGuard' );
	}
}
