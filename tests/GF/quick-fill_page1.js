/**
 * QUICK FILL - Pré-remplissage rapide du formulaire pour dev
 *
 * UTILISATION :
 * 1. Ouvrir la page du formulaire Gravity Forms
 * 2. Ouvrir la console DevTools (F12)
 * 3. Copier-coller tout ce fichier
 * 4. Taper : quickFill()
 * 5. Cliquer sur "Vérifier SIRET" (ou taper : clickVerify())
 */

// Fonction pour remplir un champ
function fillField(fieldId, value) {
  const formId = 1; // ID du formulaire Gravity Forms
  const selector = `#input_${formId}_${fieldId}`;
  const $field = jQuery(selector);

  if ($field.length) {
    $field.val(value).trigger("change");
    console.log(`✓ ${fieldId} → ${value}`);
    return true;
  } else {
    console.error(`✗ Champ ${fieldId} non trouvé`);
    return false;
  }
}

// Fonction pour vider tous les champs
function clearAll() {
  console.log("🧹 Nettoyage...");
  fillField("1", ""); // SIRET
  fillField("7_3", ""); // Prénom
  fillField("7_6", ""); // Nom
  fillField("9", ""); // Téléphone
  fillField("10", ""); // Email
}

// Fonction pour cliquer sur "Vérifier SIRET"
function clickVerify() {
  const $button = jQuery(".wcqf-form-verify-button");
  if ($button.length) {
    console.log('🔍 Clic sur "Vérifier SIRET"...');
    $button.click();
    return true;
  } else {
    console.error("✗ Bouton non trouvé");
    return false;
  }
}

// FONCTION PRINCIPALE : Remplissage rapide
function quickFill() {
  console.log("=".repeat(60));
  console.log("🚀 QUICK FILL - Remplissage automatique");
  console.log("=".repeat(60));

  clearAll();

  setTimeout(() => {
    // Données valides nécessitant reformatage
    fillField("1", "81107469900034"); // SIRET valide
    fillField("7_3", "gabriel"); // Prénom → Gabriel
    fillField("7_6", "duteurtre"); // Nom → Duteurtre
    fillField("9", "06 14 28 71 51"); // Tél → +33614287151
    fillField("10", "Gabriel.DUTEURTRE@Gmail.COM"); // Email → minuscules

    console.log("");
    console.log("✅ Formulaire pré-rempli !");
    console.log("👉 Cliquez sur 'Vérifier SIRET' ou tapez : clickVerify()");
  }, 300);
}

// Message de bienvenue
console.log("");
console.log("✅ Quick Fill chargé !");
console.log("🚀 Remplissage automatique en cours...");
console.log("");

// Exécution automatique avec délai pour s'assurer que le DOM est prêt
setTimeout(() => {
  quickFill();
  console.log("💡 Fonctions disponibles :");
  console.log("   - quickFill() : Remplir à nouveau");
  console.log("   - clickVerify() : Cliquer sur Vérifier SIRET");
  console.log("   - clearAll() : Vider tous les champs");
}, 500);
