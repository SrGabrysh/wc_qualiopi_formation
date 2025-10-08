<?php
/**
 * Cart Restriction Renderer - Affichage des notices
 * 
 * RESPONSABILITÉ UNIQUE : Afficher les messages d'erreur dans le panier
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
 * Classe CartRestrictionRenderer
 * 
 * Principe SRP : Affichage UNIQUEMENT
 */
class CartRestrictionRenderer {

	/**
	 * Configuration
	 * 
	 * @var array
	 */
	private $config;

	/**
	 * Constructeur
	 * 
	 * @param array $config Configuration
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Afficher une notice sur la page panier si nécessaire
	 */
	public function maybe_show_cart_notice(): void {
		// Vérifier si on est sur la page panier
		if ( ! $this->is_cart_page() ) {
			return;
		}

		// Vérifier la quantité du panier
		$cart_qty = $this->get_cart_quantity();

		if ( $cart_qty <= $this->config['max_qty'] ) {
			// Panier valide - pas de notice
			return;
		}

		// Panier invalide - afficher l'erreur
		$this->render_error_notice( $cart_qty );
	}

	/**
	 * Rendre la notice d'erreur
	 * 
	 * @param int $cart_qty Quantité dans le panier
	 */
	private function render_error_notice( int $cart_qty ): void {
		$message = sprintf( $this->config['error_message'], $cart_qty );

		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $message, 'error' );
		} else {
			// Fallback HTML
			$this->render_fallback_notice( $message );
		}

		LoggingHelper::debug( 'CartRestriction: Rendered error notice', [
			'cart_qty' => $cart_qty,
		] );
	}

	/**
	 * Rendre une notice HTML simple (fallback)
	 * 
	 * @param string $message Message à afficher
	 */
	private function render_fallback_notice( string $message ): void {
		?>
		<div class="woocommerce-error wcqf-cart-restriction-error" role="alert">
			<?php echo wp_kses_post( $message ); ?>
		</div>
		<?php
	}

	/**
	 * Vérifier si on est sur la page panier
	 * 
	 * @return bool
	 */
	private function is_cart_page(): bool {
		// Test WordPress standard
		if ( function_exists( 'is_cart' ) && is_cart() ) {
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
	 * Obtenir la quantité actuelle du panier
	 * 
	 * @return int
	 */
	private function get_cart_quantity(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		return (int) WC()->cart->get_cart_contents_count();
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

