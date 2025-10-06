#!/usr/bin/env python3
"""
Test E2E 001 : Workflow complet Cart Guard
Description : Teste le blocage du checkout si test non validé + déblocage après validation
"""

import sys
import os

# Ajouter le chemin du helper au PYTHONPATH
sys.path.append(os.path.join(os.path.dirname(__file__), ".."))

from helpers.test_framework import E2ETestFramework


class CartGuardWorkflowTest(E2ETestFramework):
    """Test du workflow complet Cart Guard"""

    def __init__(self):
        super().__init__(
            test_id="E2E_001",
            test_name="Cart Guard Workflow",
            description="Test du blocage checkout si test non validé + déblocage après validation",
        )

    def phase_1_configuration(self):
        """Phase 1 : Vérification configuration admin"""
        self.print_phase("Phase 1 : Configuration Admin")

        self.print_instruction(
            "1. Ouvrez https://tb-wp-dev.ddev.site/wp-admin dans votre navigateur",
            "2. Connectez-vous (admin / password)",
            "3. Vérifiez que le plugin wc_qualiopi_formation est actif",
        )

        # Vérification automatique : Plugin actif ?
        self.verify_ssh(
            "Plugin wc_qualiopi_formation actif ?",
            "cd ~/projects/tb-wp-dev && ddev wp plugin is-active wc_qualiopi_formation",
        )

        # Vérification mapping produit → test
        mapping = self.get_wp_option("wcqf_product_form_mapping")
        self.log_info(f"Mapping récupéré : {mapping}")

        if mapping and "4017" in str(mapping):
            self.log_success("Mapping trouvé pour produit 4017")
        else:
            self.log_warning(
                "Mapping non trouvé pour produit 4017 - Le test pourrait échouer"
            )

        self.wait_user_confirmation("Configuration vérifiée ?")

    def phase_2_test_blocage(self):
        """Phase 2 : Test du blocage checkout sans validation"""
        self.print_phase("Phase 2 : Test Blocage Checkout")

        self.print_instruction(
            "1. Ouvrez une fenêtre de navigation privée",
            "2. Allez sur https://tb-wp-dev.ddev.site",
            "3. Ajoutez un produit formation au panier (ID 4017 si configuré)",
            "4. Allez sur /panier/",
            "5. Observez le bouton 'Commander'",
        )

        # JavaScript à exécuter pour vérifier l'état
        js_check = """
// Vérifier l'état du bouton Commander
const checkoutBtn = document.querySelector('.wc-block-cart__submit-button, .checkout-button');
if (checkoutBtn) {
    console.log('✅ Bouton trouvé');
    console.log('Texte:', checkoutBtn.textContent);
    console.log('URL onclick:', checkoutBtn.getAttribute('onclick'));
    console.log('href:', checkoutBtn.getAttribute('href'));
} else {
    console.log('❌ Bouton checkout non trouvé');
}

// Vérifier notices WooCommerce
const notices = document.querySelectorAll('.woocommerce-message, .woocommerce-error, .woocommerce-info');
console.log('Notices:', notices.length);
notices.forEach(n => console.log('- ', n.textContent.trim()));
"""

        self.print_javascript_test(js_check)

        # Collecte observations utilisateur
        observations = self.collect_observations([
            "Le bouton 'Commander' est-il visible ?",
            "Le bouton dit-il 'Passer le test de positionnement' ?",
            "Y a-t-il un message expliquant le blocage ?",
        ])

        # Tentative de clic
        self.print_instruction(
            "6. Cliquez sur le bouton 'Commander' ou 'Passer le test de positionnement'",
            "7. Observez où vous êtes redirigé",
        )

        # Vérification redirection
        js_check_redirect = """
// Vérifier l'URL actuelle
console.log('URL actuelle:', window.location.href);
console.log('Contient /4267/ ?', window.location.href.includes('/4267/') || window.location.href.includes('test-positionnement'));
"""

        self.print_javascript_test(js_check_redirect)

        observations2 = self.collect_observations([
            "Avez-vous été redirigé vers la page de test de positionnement ?",
            "La redirection était-elle claire et rapide ?",
        ])

        self.wait_user_confirmation("Test de blocage terminé ?")

    def phase_3_test_deblocage(self):
        """Phase 3 : Test du déblocage après validation"""
        self.print_phase("Phase 3 : Test Déblocage Après Validation")

        self.log_info("Forçage de la validation du test via WP-CLI...")

        # Forcer validation pour user ID 1 et produit 4017
        result = self.execute_ssh_command(
            "Forcer validation test pour user 1",
            'cd ~/projects/tb-wp-dev && ddev wp eval \'update_user_meta(1, "wcqf_test_solved_4017", time()); echo "OK";\'',
        )

        if result["success"]:
            self.log_success("Test forcé comme validé pour user ID 1")
        else:
            self.log_error("Échec du forçage de validation")

        self.print_instruction(
            "1. Retournez sur /panier/ (en mode connecté avec admin)",
            "2. Rechargez la page (F5)",
            "3. Le bouton 'Commander' devrait maintenant pointer vers /commander/",
        )

        # JavaScript pour vérifier déblocage
        js_check_unlocked = """
// Vérifier que le bouton Commander fonctionne normalement
const checkoutBtn = document.querySelector('.wc-block-cart__submit-button, .checkout-button');
if (checkoutBtn) {
    console.log('Texte bouton:', checkoutBtn.textContent);
    console.log('href:', checkoutBtn.getAttribute('href'));
    console.log('Pointe vers /commander/ ?', checkoutBtn.getAttribute('href')?.includes('commander'));
}
"""

        self.print_javascript_test(js_check_unlocked)

        observations = self.collect_observations([
            "Le bouton affiche-t-il maintenant 'Commander' (texte normal) ?",
            "Le clic redirige-t-il vers /commander/ ?",
            "Le workflow de déblocage fonctionne-t-il correctement ?",
        ])

        self.wait_user_confirmation("Test de déblocage terminé ?")

    def generate_report(self):
        """Génère le rapport final"""
        report = {
            "test_id": self.test_id,
            "test_name": self.test_name,
            "duration": self.get_duration(),
            "phases": self.get_phases_summary(),
            "observations": self.get_all_observations(),
            "success_rate": self.calculate_success_rate(),
        }

        self.save_markdown_report(report)
        self.print_summary()

    def run(self):
        """Exécution principale du test"""
        try:
            print(f"\n🚀 Démarrage du test : {self.test_name}\n")
            print(f"📝 {self.description}\n")

            # Exécution des phases
            self.phase_1_configuration()
            self.phase_2_test_blocage()
            self.phase_3_test_deblocage()

            # Génération rapport
            self.generate_report()

            print("\n✅ Test terminé avec succès !")

        except KeyboardInterrupt:
            print("\n\n⚠️  Test interrompu par l'utilisateur")
            self.log_warning("Test interrompu manuellement")
            self.generate_report()

        except Exception as e:
            print(f"\n\n❌ Erreur durant le test : {str(e)}")
            self.log_error(f"Exception: {str(e)}")
            self.generate_report()
            raise


# Exécution
if __name__ == "__main__":
    test = CartGuardWorkflowTest()
    test.run()

