<?php
/**
 * Cart Assets - Gestion des assets CSS/JS et debug
 * 
 * RESPONSABILITÉ UNIQUE : Charger CSS/JS et générer le debug JavaScript
 * Support WooCommerce Blocks via JavaScript
 * 
 * @package WcQualiopiFormation\Cart
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Cart;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Cart\Helpers\PageDetector;
use WcQualiopiFormation\Cart\Helpers\UrlGenerator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartAssets
 * 
 * Principe SRP : Une seule responsabilité = assets + debug
 */
class CartAssets {

	/**
	 * Charger les assets CSS/JS
	 * 
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Seulement sur la page panier
		if ( ! PageDetector::is_cart_page() ) {
			return;
		}

		// CSS pour l'UX
		$css_version = WCQF_VERSION;
		wp_enqueue_style(
			'wcqf-cart-guard',
			WCQF_PLUGIN_URL . 'assets/css/cart-guard.css',
			[],
			$css_version
		);
	}

	/**
	 * Modifier le bouton checkout via JavaScript (WooCommerce Blocks)
	 * 
	 * ✅ CETTE FONCTION FONCTIONNE avec WooCommerce Blocks !
	 * Utilise JavaScript pour modifier l'interface React côté client.
	 * 
	 * @param bool   $should_block Faut-il bloquer ?
	 * @param string $test_url     URL du test
	 * @return void
	 */
	public function modify_checkout_button_blocks( bool $should_block, string $test_url ): void {
		if ( ! PageDetector::is_cart_page() || ! $should_block || empty( $test_url ) ) {
			return;
		}

		// Script JavaScript pour modifier le bouton checkout
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			// Fonction pour modifier le bouton
			function modifyCheckoutButton() {
				// Sélecteurs pour différents types de boutons checkout
				const selectors = [
					'.wc-block-cart__submit-button',
					'.wc-block-checkout__actions_row .wc-block-components-checkout-place-order-button',
					'.checkout-button',
					'a[href*="checkout"], a[href*="commander"]',
					'.wc-proceed-to-checkout a'
				];
				
				let buttonFound = false;
				
				selectors.forEach(selector => {
					const buttons = document.querySelectorAll(selector);
					buttons.forEach(button => {
						if (button && !button.classList.contains('wcqf-modified')) {
							// Marquer comme modifié
							button.classList.add('wcqf-modified');
							
							// Modifier le texte
							if (button.textContent) {
								button.textContent = '🎯 Faire le test de positionnement';
							}
							
							// Modifier l'URL si c'est un lien
							if (button.tagName === 'A') {
								button.href = '<?php echo esc_js( $test_url ); ?>';
							}
							
							// Ajouter un style distinctif
							button.style.backgroundColor = '#ff9800';
							button.style.color = 'white';
							button.style.fontWeight = 'bold';
							
							buttonFound = true;
							console.log('WCQF: Bouton checkout modifié', button);
						}
					});
				});
				
				return buttonFound;
			}
			
			// Modifier immédiatement
			modifyCheckoutButton();
			
			// Observer les changements DOM pour les Blocks qui se chargent dynamiquement
			const observer = new MutationObserver(function(mutations) {
				let shouldCheck = false;
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
						shouldCheck = true;
					}
				});
				
				if (shouldCheck) {
					setTimeout(modifyCheckoutButton, 100);
				}
			});
			
			// Observer le body pour les changements
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
			
			// Vérifier périodiquement pendant les 10 premières secondes
			let checks = 0;
			const intervalId = setInterval(function() {
				checks++;
				modifyCheckoutButton();
				
				if (checks >= 20) { // 20 * 500ms = 10 secondes
					clearInterval(intervalId);
				}
			}, 500);
		});
		</script>
		<?php
	}

	/**
	 * Debug de l'état du panier (JavaScript console.log)
	 * 
	 * @param bool  $enforcement_enabled Enforcement activé ?
	 * @param bool  $should_block        Faut-il bloquer ?
	 * @param array $pending_tests       Tests en attente
	 * @param array $validation_details  Détails validation par produit
	 * @return void
	 */
	public function debug_cart_state( bool $enforcement_enabled, bool $should_block, array $pending_tests, array $validation_details ): void {
		if ( ! PageDetector::is_cart_page() ) {
			return;
		}

		$user_id = get_current_user_id();
		$cart_items = function_exists( 'WC' ) && WC() && WC()->cart ? WC()->cart->get_cart() : [];

		?>
		<script type="text/javascript">
			// Protection contre les chargements multiples
			if (!window.wcqfDebugLoaded) {
				window.wcqfDebugLoaded = true;
			
			console.log('=== WCQF CartGuard Debug DÉTAILLÉ ===');
			console.log('🔧 Configuration:');
			console.log('  - Enforcement enabled:', <?php echo $enforcement_enabled ? 'true' : 'false'; ?>);
			console.log('  - Should block checkout:', <?php echo $should_block ? 'true' : 'false'; ?>);
			console.log('  - User ID:', <?php echo $user_id; ?>);
			console.log('  - Is admin:', <?php echo current_user_can('administrator') ? 'true' : 'false'; ?>);
			
			console.log('🛒 Panier:');
			console.log('  - Cart items count:', <?php echo count( $cart_items ); ?>);
			console.log('  - Pending tests count:', <?php echo count( $pending_tests ); ?>);
			console.log('  - Pending tests:', <?php echo wp_json_encode( $pending_tests ); ?>);
			
			console.log('🔍 Détails validation par produit:');
			<?php foreach ( $validation_details as $detail ): ?>
			console.log('  📦 Produit <?php echo $detail['product_id']; ?>:');
			console.log('    - Has mapping: <?php echo $detail['has_mapping'] ? 'true' : 'false'; ?>');
			console.log('    - Mapping active: <?php echo $detail['mapping_active'] ? 'true' : 'false'; ?>');
			console.log('    - Is validated: <?php echo $detail['is_validated'] ? 'true' : 'false'; ?>');
			console.log('    - Page ID: <?php echo $detail['page_id'] ?? 'null'; ?>');
			<?php endforeach; ?>
			
			} // Fermeture du if (!window.wcqfDebugLoaded)
		</script>
		<?php
	}
}

