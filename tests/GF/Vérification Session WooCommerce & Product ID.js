/**
 * QUICK FILL TEST POSITIONNEMENT - Pré-remplissage rapide du test de positionnement
 *
 * UTILISATION :
 * 1. Naviguer vers la page 15 du formulaire (test de positionnement)
 * 2. Ouvrir la console DevTools (F12)
 * 3. Copier-coller tout ce fichier
 * 4. Taper : quickFillTest()
 * 5. Vérifier que le score se calcule automatiquement
 */

// Fonction pour sélectionner une option radio
function selectRadio(fieldId, value) {
  const formId = 1; // ID du formulaire Gravity Forms
  const selector = `input[name="input_${fieldId}"][value="${value}"]`;
  const $radio = jQuery(selector);

  if ($radio.length) {
    $radio.prop("checked", true).trigger("change");
    console.log(`✓ ${fieldId} → ${value}`);
    return true;
  } else {
    console.error(`✗ Radio ${fieldId} avec valeur ${value} non trouvé`);
    return false;
  }
}

// Fonction pour vider toutes les réponses
function clearTest() {
  console.log("🧹 Nettoyage du test...");

  // Vider tous les champs radio du test en les désélectionnant
  const fieldIds = [
    "6",
    "16",
    "17",
    "18",
    "19",
    "20",
    "21",
    "22",
    "24",
    "25",
    "26",
  ];

  fieldIds.forEach((fieldId) => {
    const selector = `input[name="input_${fieldId}"]`;
    const $radios = jQuery(selector);

    if ($radios.length) {
      $radios.prop("checked", false).trigger("change");
      console.log(`✓ ${fieldId} → vidé`);
    } else {
      console.warn(`⚠ Champ ${fieldId} non trouvé`);
    }
  });
}

// Fonction pour obtenir le score calculé
function getScore() {
  const formId = 1;
  const selector = `#input_${formId}_27`;
  const $scoreField = jQuery(selector);

  if ($scoreField.length) {
    const score = $scoreField.val();
    console.log(`📊 Score actuel : ${score}`);
    return score;
  } else {
    console.error("✗ Champ score (ID 27) non trouvé");
    return null;
  }
}

// FONCTION PRINCIPALE : Remplissage du test de positionnement
function quickFillTest() {
  console.log("=".repeat(60));
  console.log("🚀 QUICK FILL TEST POSITIONNEMENT");
  console.log("=".repeat(60));

  clearTest();

  setTimeout(() => {
    // Réponses optimisées pour un score élevé (profil idéal)
    selectRadio("6", "Oui"); // Professionnel beauté : Oui
    selectRadio("16", "2"); // Réseaux sociaux : Régulièrement (2 points)
    selectRadio("17", "1"); // Niveau communication : Maîtrise bien (1 point)
    selectRadio("18", "2"); // Motivation : Communication authentique (2 points)
    selectRadio("19", "2"); // IA : Outil formidable (2 points)
    selectRadio("20", "2"); // Secteur : Esthétique/Coiffure/Bien-être (2 points)
    selectRadio("21", "2"); // Objectifs : Développer clientèle (2 points)
    selectRadio("22", "2"); // Outils numériques : Assez à l'aise (2 points)
    selectRadio("24", "2"); // Engagement : Très motivée (2 points)
    selectRadio("25", "2"); // Concurrence : Se sentir différente (2 points)
    selectRadio("26", "2"); // Recherche : Méthode structurée (2 points)

    console.log("");
    console.log("✅ Test pré-rempli !");
    console.log("📊 Score attendu : 21 points");

    // Vérifier le score après un délai
    setTimeout(() => {
      const score = getScore();
      if (score) {
        console.log(`🎯 Score calculé : ${score}/21`);
        if (parseInt(score) >= 18) {
          console.log("🌟 Excellent profil !");
        } else if (parseInt(score) >= 12) {
          console.log("👍 Bon profil");
        } else {
          console.log("⚠️ Profil à améliorer");
        }
      }
    }, 1000);
  }, 300);
}

// FONCTION ALTERNATIVE : Profil débutant
function quickFillTestDebutant() {
  console.log("=".repeat(60));
  console.log("🚀 QUICK FILL TEST - PROFIL DÉBUTANT");
  console.log("=".repeat(60));

  clearTest();

  setTimeout(() => {
    // Réponses pour un profil débutant (score moyen)
    selectRadio("6", "Oui"); // Professionnel beauté : Oui
    selectRadio("16", "1"); // Réseaux sociaux : Occasionnel (1 point)
    selectRadio("17", "2"); // Niveau communication : Bases mais manque méthode (2 points)
    selectRadio("18", "1"); // Motivation : Se différencier (1 point)
    selectRadio("19", "1"); // IA : Sceptique mais ouverte (1 point)
    selectRadio("20", "2"); // Secteur : Esthétique/Coiffure/Bien-être (2 points)
    selectRadio("21", "1"); // Objectifs : Améliorer communication (1 point)
    selectRadio("22", "2"); // Outils numériques : Moyennement à l'aise (2 points)
    selectRadio("24", "2"); // Engagement : Besoin accompagnement (2 points)
    selectRadio("25", "2"); // Concurrence : Se démarquer (2 points)
    selectRadio("26", "1"); // Recherche : Outils concrets (1 point)

    console.log("");
    console.log("✅ Test débutant pré-rempli !");
    console.log("📊 Score attendu : 15 points");

    setTimeout(() => {
      const score = getScore();
      if (score) {
        console.log(`🎯 Score calculé : ${score}/21`);
      }
    }, 1000);
  }, 300);
}

// FONCTION ALTERNATIVE : Profil réticent
function quickFillTestRetisant() {
  console.log("=".repeat(60));
  console.log("🚀 QUICK FILL TEST - PROFIL RÉTICENT");
  console.log("=".repeat(60));

  clearTest();

  setTimeout(() => {
    // Réponses pour un profil réticent (score faible)
    selectRadio("6", "Oui"); // Professionnel beauté : Oui
    selectRadio("16", "0"); // Réseaux sociaux : Pas intéressée (0 point)
    selectRadio("17", "0"); // Niveau communication : Pas d'intérêt (0 point)
    selectRadio("18", "1"); // Motivation : Améliorer visibilité (1 point)
    selectRadio("19", "0"); // IA : Préfère manuel (0 point)
    selectRadio("20", "1"); // Secteur : Connexe (1 point)
    selectRadio("21", "0"); // Objectifs : Pas d'objectifs précis (0 point)
    selectRadio("22", "0"); // Outils numériques : Peu à l'aise (0 point)
    selectRadio("24", "1"); // Engagement : Peu de temps (1 point)
    selectRadio("25", "0"); // Concurrence : Pas d'intérêt (0 point)
    selectRadio("26", "0"); // Recherche : Ne sait pas (0 point)

    console.log("");
    console.log("✅ Test réticent pré-rempli !");
    console.log("📊 Score attendu : 4 points");

    setTimeout(() => {
      const score = getScore();
      if (score) {
        console.log(`🎯 Score calculé : ${score}/21`);
      }
    }, 1000);
  }, 300);
}

// Fonction de diagnostic
function diagnoseForm() {
  console.log("=".repeat(60));
  console.log("🔍 DIAGNOSTIC DU FORMULAIRE");
  console.log("=".repeat(60));

  // Vérifier la présence des champs
  const fieldIds = [
    "6",
    "16",
    "17",
    "18",
    "19",
    "20",
    "21",
    "22",
    "24",
    "25",
    "26",
    "27",
  ];

  fieldIds.forEach((fieldId) => {
    const selector = `input[name="input_${fieldId}"], #input_1_${fieldId}`;
    const $elements = jQuery(selector);

    if ($elements.length) {
      console.log(
        `✓ Champ ${fieldId} : ${$elements.length} élément(s) trouvé(s)`
      );
    } else {
      console.error(`✗ Champ ${fieldId} : NON TROUVÉ`);
    }
  });

  // Vérifier le formulaire Gravity Forms
  const $form = jQuery("form[id*='gform']");
  if ($form.length) {
    console.log(`✓ Formulaire Gravity Forms trouvé : ${$form.attr("id")}`);
  } else {
    console.error("✗ Aucun formulaire Gravity Forms trouvé");
  }

  console.log("=".repeat(60));
}

// Fonction d'analyse détaillée des valeurs
function analyzeScore() {
  console.log("=".repeat(60));
  console.log("🔍 ANALYSE DÉTAILLÉE DU SCORE");
  console.log("=".repeat(60));

  const calculationFields = [
    "16",
    "17",
    "18",
    "19",
    "20",
    "21",
    "22",
    "24",
    "25",
    "26",
  ];
  let totalCalculated = 0;
  let details = [];

  calculationFields.forEach((fieldId) => {
    const selector = `input[name="input_${fieldId}"]:checked`;
    const $checked = jQuery(selector);

    if ($checked.length) {
      const value = $checked.val();
      const numericValue = parseFloat(value) || 0;
      totalCalculated += numericValue;
      details.push(`Champ ${fieldId}: "${value}" = ${numericValue} points`);
    } else {
      details.push(`Champ ${fieldId}: NON SÉLECTIONNÉ = 0 points`);
    }
  });

  // Afficher le détail
  details.forEach((detail) => console.log(`  ${detail}`));

  console.log("");
  console.log(`📊 TOTAL CALCULÉ : ${totalCalculated} points`);

  // Vérifier le score affiché
  const score = getScore();
  if (score) {
    console.log(`📊 SCORE AFFICHÉ : ${score} points`);
    const difference = parseInt(score) - totalCalculated;
    if (difference !== 0) {
      console.log(
        `⚠️  DÉCALAGE : ${difference > 0 ? "+" : ""}${difference} points`
      );
      console.log("🔍 Le décalage peut venir de :");
      console.log("   - Champ 6 (professionnel beauté) ajouté automatiquement");
      console.log("   - Valeurs par défaut non nulles");
      console.log("   - Calculs supplémentaires du plugin");
    } else {
      console.log("✅ Score cohérent !");
    }
  }

  console.log("=".repeat(60));
}

// Message de bienvenue
console.log("");
console.log("✅ Quick Fill Test Positionnement chargé !");
console.log("📝 Tapez : quickFillTest() - Profil idéal (21 pts)");
console.log("📝 Tapez : quickFillTestDebutant() - Profil débutant (16 pts)");
console.log("📝 Tapez : quickFillTestRetisant() - Profil réticent (4 pts)");
console.log("🧹 Vider : clearTest()");
console.log("📊 Voir score : getScore()");
console.log("🔍 Diagnostic : diagnoseForm()");
console.log("🔬 Analyser score : analyzeScore()");
console.log("");
