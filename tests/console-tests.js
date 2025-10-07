/**
 * Tests Console DevTools - WC Qualiopi Formation v1.1.0
 *
 * UTILISATION :
 * 1. Ouvrir la console DevTools (F12)
 * 2. Copier-coller tout ce script
 * 3. Exécuter les tests avec : WCQFTests.runAll()
 * 4. Ou tester un scénario spécifique : WCQFTests.test1_valid()
 */

const WCQFTests = {
  // Configuration
  formId: 1, // ID du formulaire Gravity Forms
  delay: 2000, // Délai entre chaque test (ms)

  /**
   * Utilitaire : Remplir un champ
   */
  fillField: function (fieldId, value) {
    const selector = `#input_${this.formId}_${fieldId}`;
    const $field = jQuery(selector);
    if ($field.length) {
      $field.val(value).trigger("change");
      console.log(`✓ Champ ${fieldId} rempli avec: ${value}`);
      return true;
    } else {
      console.error(`✗ Champ ${fieldId} non trouvé`);
      return false;
    }
  },

  /**
   * Utilitaire : Vider tous les champs
   */
  clearAll: function () {
    console.log("🧹 Nettoyage de tous les champs...");
    this.fillField("1", ""); // SIRET
    this.fillField("7_3", ""); // Prénom
    this.fillField("7_6", ""); // Nom
    this.fillField("9", ""); // Téléphone
    this.fillField("10", ""); // Email
  },

  /**
   * Utilitaire : Cliquer sur "Vérifier SIRET"
   */
  clickVerify: function () {
    const $button = jQuery(".wcqf-form-verify-button");
    if ($button.length) {
      console.log('🔍 Clic sur "Vérifier SIRET"...');
      $button.click();
      return true;
    } else {
      console.error('✗ Bouton "Vérifier SIRET" non trouvé');
      return false;
    }
  },

  /**
   * Utilitaire : Afficher un séparateur
   */
  separator: function (title) {
    console.log("\n" + "=".repeat(60));
    console.log(`📋 ${title}`);
    console.log("=".repeat(60));
  },

  /**
   * Utilitaire : Attendre
   */
  wait: function (ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  },

  // ============================================================================
  // TESTS
  // ============================================================================

  /**
   * TEST 1 : Tous les champs valides - SIRET valide et existant
   */
  test1_valid: async function () {
    this.separator("TEST 1 : Tous les champs valides");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034"); // SIRET valide et existant
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      "✅ Résultat attendu : Entreprise trouvée, tous les champs remplis"
    );
  },

  /**
   * TEST 2 : Téléphone vide
   */
  test2_phone_empty: async function () {
    this.separator("TEST 2 : Téléphone vide");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    // Téléphone vide
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "⚠️ Veuillez renseigner le numéro de téléphone."'
    );
  },

  /**
   * TEST 3 : Email vide
   */
  test3_email_empty: async function () {
    this.separator("TEST 3 : Email vide");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    // Email vide

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "⚠️ Veuillez renseigner l\'adresse email."'
    );
  },

  /**
   * TEST 4 : Nom vide
   */
  test4_name_empty: async function () {
    this.separator("TEST 4 : Nom vide");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    // Nom vide
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "⚠️ Veuillez renseigner le nom et le prénom du représentant avant de vérifier le SIRET."'
    );
  },

  /**
   * TEST 5 : Prénom vide
   */
  test5_firstname_empty: async function () {
    this.separator("TEST 5 : Prénom vide");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    // Prénom vide
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "⚠️ Veuillez renseigner le nom et le prénom du représentant avant de vérifier le SIRET."'
    );
  },

  /**
   * TEST 6 : SIRET invalide (format incorrect)
   */
  test6_siret_invalid_format: async function () {
    this.separator("TEST 6 : SIRET invalide (format incorrect)");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "123"); // SIRET trop court
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log("❌ Résultat attendu : Message d'erreur SIRET invalide");
  },

  /**
   * TEST 7 : SIRET valide mais entreprise inexistante
   */
  test7_siret_not_found: async function () {
    this.separator("TEST 7 : SIRET valide mais entreprise inexistante");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900033"); // SIRET valide (Luhn) mais entreprise n'existe pas
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "Aucune entreprise trouvée avec ce SIRET."'
    );
  },

  /**
   * TEST 8 : Téléphone invalide (trop court)
   */
  test8_phone_invalid: async function () {
    this.separator("TEST 8 : Téléphone invalide (trop court)");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0612"); // Téléphone trop court
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "Le numéro de téléphone doit contenir exactement 10 chiffres."'
    );
  },

  /**
   * TEST 9 : Email invalide
   */
  test9_email_invalid: async function () {
    this.separator("TEST 9 : Email invalide");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "invalid-email"); // Email invalide

    await this.wait(500);
    this.clickVerify();

    console.log(
      "❌ Résultat attendu : Message \"Le format de l'adresse email n'est pas valide.\""
    );
  },

  /**
   * TEST 10 : Nom avec chiffres (invalide)
   */
  test10_name_with_numbers: async function () {
    this.separator("TEST 10 : Nom avec chiffres (invalide)");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre123"); // Nom avec chiffres
    this.fillField("9", "0614287151");
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      '❌ Résultat attendu : Message "Les chiffres ne sont pas autorisés dans les noms et prénoms."'
    );
  },

  /**
   * TEST 11 : Téléphone déjà au format E164
   */
  test11_phone_e164: async function () {
    this.separator("TEST 11 : Téléphone déjà au format E164");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "+33614287151"); // Déjà en E164
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log("✅ Résultat attendu : Accepté tel quel, pas de re-formatage");
  },

  /**
   * TEST 12 : Email en majuscules
   */
  test12_email_uppercase: async function () {
    this.separator("TEST 12 : Email en majuscules");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "0614287151");
    this.fillField("10", "GABRIEL.DUTEURTRE@GMAIL.COM"); // Majuscules

    await this.wait(500);
    this.clickVerify();

    console.log(
      "✅ Résultat attendu : Email formaté en minuscules automatiquement"
    );
  },

  /**
   * TEST 13 : Téléphone avec espaces et points
   */
  test13_phone_formatted: async function () {
    this.separator("TEST 13 : Téléphone avec espaces et points");
    this.clearAll();
    await this.wait(500);

    this.fillField("1", "81107469900034");
    this.fillField("7_3", "Gabriel");
    this.fillField("7_6", "Duteurtre");
    this.fillField("9", "06.14.28.71.51"); // Avec points
    this.fillField("10", "gabriel.duteurtre@gmail.com");

    await this.wait(500);
    this.clickVerify();

    console.log(
      "✅ Résultat attendu : Nettoyé et formaté en E164 (+33614287151)"
    );
  },

  // ============================================================================
  // EXÉCUTION DE TOUS LES TESTS
  // ============================================================================

  /**
   * Exécuter tous les tests séquentiellement
   */
  runAll: async function () {
    console.clear();
    console.log(
      "🚀 DÉMARRAGE DE LA SUITE DE TESTS WC QUALIOPI FORMATION v1.1.0"
    );
    console.log("⏱️  Délai entre tests : " + this.delay + "ms");
    console.log("");

    const tests = [
      "test1_valid",
      "test2_phone_empty",
      "test3_email_empty",
      "test4_name_empty",
      "test5_firstname_empty",
      "test6_siret_invalid_format",
      "test7_siret_not_found",
      "test8_phone_invalid",
      "test9_email_invalid",
      "test10_name_with_numbers",
      "test11_phone_e164",
      "test12_email_uppercase",
      "test13_phone_formatted",
    ];

    for (let i = 0; i < tests.length; i++) {
      await this[tests[i]]();
      if (i < tests.length - 1) {
        console.log(
          `\n⏳ Attente de ${this.delay}ms avant le test suivant...\n`
        );
        await this.wait(this.delay);
      }
    }

    console.log("\n" + "=".repeat(60));
    console.log("✅ TOUS LES TESTS SONT TERMINÉS");
    console.log("=".repeat(60));
    console.log(
      "\n💡 Consultez les résultats dans la console et les messages à l'écran"
    );
  },

  /**
   * Exécuter uniquement les tests de validation (qui doivent échouer)
   */
  runValidationTests: async function () {
    console.clear();
    console.log("🧪 TESTS DE VALIDATION (Erreurs attendues)");
    console.log("");

    const tests = [
      "test2_phone_empty",
      "test3_email_empty",
      "test4_name_empty",
      "test5_firstname_empty",
      "test6_siret_invalid_format",
      "test8_phone_invalid",
      "test9_email_invalid",
      "test10_name_with_numbers",
    ];

    for (let i = 0; i < tests.length; i++) {
      await this[tests[i]]();
      if (i < tests.length - 1) {
        console.log(`\n⏳ Attente de ${this.delay}ms...\n`);
        await this.wait(this.delay);
      }
    }

    console.log("\n✅ TESTS DE VALIDATION TERMINÉS");
  },

  /**
   * Exécuter uniquement les tests de succès
   */
  runSuccessTests: async function () {
    console.clear();
    console.log("✅ TESTS DE SUCCÈS");
    console.log("");

    const tests = [
      "test1_valid",
      "test11_phone_e164",
      "test12_email_uppercase",
      "test13_phone_formatted",
    ];

    for (let i = 0; i < tests.length; i++) {
      await this[tests[i]]();
      if (i < tests.length - 1) {
        console.log(`\n⏳ Attente de ${this.delay}ms...\n`);
        await this.wait(this.delay);
      }
    }

    console.log("\n✅ TESTS DE SUCCÈS TERMINÉS");
  },

  /**
   * QUICK FILL : Remplir rapidement avec des données valides nécessitant reformatage
   * Utile pour dev : remplit le formulaire sans tout retaper
   */
  quickFill: async function () {
    this.separator("QUICK FILL : Remplissage rapide pour dev");
    this.clearAll();
    await this.wait(500);

    // Données valides mais nécessitant reformatage
    this.fillField("1", "81107469900034"); // SIRET valide et existant
    this.fillField("7_3", "gabriel"); // Prénom minuscule → sera formaté
    this.fillField("7_6", "duteurtre"); // Nom minuscule → sera formaté
    this.fillField("9", "06 14 28 71 51"); // Téléphone avec espaces → sera formaté en E164
    this.fillField("10", "Gabriel.DUTEURTRE@Gmail.COM"); // Email majuscules → sera mis en minuscules

    console.log("📝 Formulaire pré-rempli avec des données valides");
    console.log("✅ Prêt pour tester - Cliquez sur 'Vérifier SIRET' manuellement");
    console.log(
      "💡 Ou lancez : WCQFTests.clickVerify() pour vérifier automatiquement"
    );
  },

  /**
   * Afficher l'aide
   */
  help: function () {
    console.log("📖 AIDE - WC QUALIOPI FORMATION TESTS");
    console.log("");
    console.log("🚀 UTILITAIRE DEV :");
    console.log("  WCQFTests.quickFill()           - Remplir formulaire rapidement");
    console.log("");
    console.log("COMMANDES DISPONIBLES :");
    console.log("  WCQFTests.runAll()              - Exécuter tous les tests");
    console.log(
      "  WCQFTests.runValidationTests()  - Tests de validation (erreurs)"
    );
    console.log(
      "  WCQFTests.runSuccessTests()     - Tests de succès uniquement"
    );
    console.log("");
    console.log("TESTS INDIVIDUELS :");
    console.log("  WCQFTests.test1_valid()         - Tous les champs valides");
    console.log("  WCQFTests.test2_phone_empty()   - Téléphone vide");
    console.log("  WCQFTests.test3_email_empty()   - Email vide");
    console.log("  WCQFTests.test4_name_empty()    - Nom vide");
    console.log("  WCQFTests.test5_firstname_empty() - Prénom vide");
    console.log("  WCQFTests.test6_siret_invalid_format() - SIRET invalide");
    console.log("  WCQFTests.test7_siret_not_found() - SIRET inexistant");
    console.log("  WCQFTests.test8_phone_invalid() - Téléphone invalide");
    console.log("  WCQFTests.test9_email_invalid() - Email invalide");
    console.log("  WCQFTests.test10_name_with_numbers() - Nom avec chiffres");
    console.log("  WCQFTests.test11_phone_e164()   - Téléphone en E164");
    console.log("  WCQFTests.test12_email_uppercase() - Email majuscules");
    console.log("  WCQFTests.test13_phone_formatted() - Téléphone formaté");
    console.log("");
    console.log("UTILITAIRES :");
    console.log("  WCQFTests.clearAll()            - Vider tous les champs");
    console.log("  WCQFTests.delay = 3000          - Modifier le délai (ms)");
    console.log("  WCQFTests.help()                - Afficher cette aide");
  },
};

// Message de bienvenue
console.log("✅ Tests WC Qualiopi Formation v1.1.0 chargés !");
console.log("💡 Tapez WCQFTests.help() pour voir les commandes disponibles");
console.log("🚀 Tapez WCQFTests.runAll() pour lancer tous les tests");
