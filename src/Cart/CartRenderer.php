<?php
/**
 * Cart Renderer - Affichage des notices et messages
 * 
 * RESPONSABILITÉ UNIQUE : Générer le HTML des notices et messages du panier
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
 * Classe CartRenderer
 * 
 * Principe SRP : Une seule responsabilité = affichage/rendering
 */
class CartRenderer {

	/**
	 * Constructeur
	 */
	public function __construct() {
	}

	/**
	 * Afficher le message de checkout bloqué
	 * 
	 * @return void
	 */
	public function render_blocked_checkout_message(): void {
		LoggingHelper::debug( 'CartRenderer: Rendering blocked checkout message' );
		?>
		<div class="wcqf-checkout-blocked" role="alert" aria-live="assertive">
			<div class="woocommerce-info">
				<strong><?php esc_html_e( 'Test de positionnement requis', 'wcqf' ); ?></strong>
				<p><?php esc_html_e( 'Vous devez d\'abord valider le test de positionnement pour poursuivre votre commande.', 'wcqf' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher la notice pour un test requis
	 * 
	 * @param array $test_info Informations du test
	 * @return void
	 */
	public function render_test_notice( array $test_info ): void {
		$product_name = esc_html( $test_info['product_name'] );
		$test_url = esc_url( $test_info['test_url'] );

		if ( empty( $test_url ) ) {
			// Fallback si URL du test non disponible
			$this->render_test_unavailable_notice( $product_name );
			return;
		}

		LoggingHelper::debug( "CartRenderer: Rendering test notice for product: {$product_name}" );
		?>
		<div class="wcqf-test-notice" role="alert" aria-live="polite">
			<div class="woocommerce-message">
				<strong><?php echo esc_html( sprintf( __( 'Test requis : %s', 'wcqf' ), $product_name ) ); ?></strong>
				<p><?php esc_html_e( 'Pour poursuivre, réalisez d\'abord le test de positionnement lié à cette formation.', 'wcqf' ); ?></p>
				<p>
					<a href="<?php echo $test_url; ?>" class="button wc-forward wcqf-test-cta">
						<?php esc_html_e( 'Passer le test', 'wcqf' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher une notice si le test n'est pas disponible
	 * 
	 * @param string $product_name Nom du produit
	 * @return void
	 */
	public function render_test_unavailable_notice( string $product_name ): void {
		LoggingHelper::warning( "CartRenderer: Test unavailable for product: {$product_name}" );
		?>
		<div class="wcqf-test-unavailable" role="alert" aria-live="assertive">
			<div class="woocommerce-error">
				<strong><?php echo esc_html( sprintf( __( 'Test temporairement indisponible : %s', 'wcqf' ), $product_name ) ); ?></strong>
				<p><?php esc_html_e( 'Le test de positionnement est temporairement indisponible. Contactez le support ou réessayez plus tard.', 'wcqf' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher toutes les notices pour les tests en attente
	 * 
	 * @param array $pending_tests Liste des tests en attente
	 * @return void
	 */
	public function render_all_test_notices( array $pending_tests ): void {
		if ( empty( $pending_tests ) ) {
			return;
		}

		LoggingHelper::info( 'CartRenderer: Rendering ' . count( $pending_tests ) . ' test notices' );

		foreach ( $pending_tests as $test_info ) {
			$this->render_test_notice( $test_info );
		}
	}

	/**
	 * Ajouter une notice WooCommerce serveur (compatible Blocks)
	 * 
	 * @param bool $should_block Faut-il bloquer ?
	 * @return void
	 */
	public function add_server_notice( bool $should_block ): void {
		if ( $should_block ) {
			wc_add_notice(
				__( 'Pour poursuivre, vous devez d\'abord réaliser le test de positionnement lié à cette formation.', 'wcqf' ),
				'notice'
			);
			LoggingHelper::debug( 'CartRenderer: Added blocking notice' );
		} else {
			// Message de succès si le test est validé
			wc_add_notice(
				__( '✅ Test de positionnement validé ! Vous pouvez maintenant procéder au paiement.', 'wcqf' ),
				'success'
			);
			LoggingHelper::debug( 'CartRenderer: Added success notice' );
		}
	}
}

