/**
 * Script de debug pour tester les transitions de page Gravity Forms
 * À exécuter dans la console Chrome sur une page de formulaire
 */

console.log("🔍 DEBUG PAGE TRANSITIONS - Chargement...");

// Fonction pour surveiller les transitions de page
function debugPageTransitions() {
  console.log("📋 Surveillance des transitions de page activée");

  // Intercepter les clics sur les boutons "Suivant"
  const nextButtons = document.querySelectorAll(
    'input[type="submit"], button[type="submit"]'
  );
  console.log(`🎯 ${nextButtons.length} boutons de soumission trouvés`);

  nextButtons.forEach((button, index) => {
    button.addEventListener("click", function (e) {
      console.log(`🔄 Clic détecté sur bouton ${index + 1}:`, {
        text: this.value || this.textContent,
        form: this.closest("form")?.id || "unknown",
        page: getCurrentPage(),
      });
    });
  });

  // Surveiller les changements d'URL (si AJAX)
  let currentUrl = window.location.href;
  setInterval(() => {
    if (window.location.href !== currentUrl) {
      console.log("🌐 Changement d'URL détecté:", {
        from: currentUrl,
        to: window.location.href,
      });
      currentUrl = window.location.href;
    }
  }, 1000);

  // Surveiller les requêtes AJAX
  const originalFetch = window.fetch;
  window.fetch = function (...args) {
    console.log("🌐 Requête AJAX détectée:", args[0]);
    return originalFetch.apply(this, args);
  };

  // Surveiller les événements Gravity Forms
  if (typeof gform !== "undefined") {
    console.log("✅ Gravity Forms détecté");

    // Intercepter les événements de validation
    if (typeof gform.addAction === "function") {
      gform.addAction(
        "gform_post_paging",
        function (form, sourcePage, currentPage) {
          console.log("🎯 HOOK GRAVITY FORMS DÉTECTÉ:", {
            form_id: form.id,
            source_page: sourcePage,
            current_page: currentPage,
            timestamp: new Date().toISOString(),
          });
        }
      );
    }
  } else {
    console.log("❌ Gravity Forms non détecté");
  }
}

// Fonction pour déterminer la page actuelle
function getCurrentPage() {
  // Essayer différentes méthodes pour détecter la page
  const pageInput = document.querySelector(
    'input[name="gform_source_page_number"]'
  );
  if (pageInput) {
    return pageInput.value;
  }

  const pageIndicator = document.querySelector(
    ".gform_page_footer .gform_page_number"
  );
  if (pageIndicator) {
    return pageIndicator.textContent;
  }

  // Essayer de détecter via les classes CSS
  const currentPageDiv = document.querySelector(".gform_page.current");
  if (currentPageDiv) {
    const pageNumber = currentPageDiv.getAttribute("data-page");
    if (pageNumber) {
      return pageNumber;
    }
  }

  // Essayer via l'URL (fragment)
  const urlFragment = window.location.hash;
  if (urlFragment.includes("gf_")) {
    const match = urlFragment.match(/gf_(\d+)/);
    if (match) {
      return `form_${match[1]}`;
    }
  }

  return "unknown";
}

// Fonction pour forcer une transition de page (test)
function forcePageTransition() {
  console.log("🧪 Test de transition forcée...");

  const nextButton = document.querySelector(
    'input[type="submit"][value*="Suivant"], button[type="submit"]'
  );
  if (nextButton) {
    console.log("🎯 Bouton suivant trouvé, clic simulé");
    nextButton.click();
  } else {
    console.log("❌ Aucun bouton suivant trouvé");
  }
}

// Fonction pour afficher l'état du formulaire
function showFormState() {
  console.log("📊 ÉTAT DU FORMULAIRE:");
  console.log("- Page actuelle:", getCurrentPage());
  console.log("- URL:", window.location.href);

  // Trouver le vrai formulaire Gravity Forms
  const gravityForm = document.querySelector("form[id^='gform_']");
  const adminForm = document.querySelector("form#adminbarsearch");

  console.log("- Form ID:", gravityForm?.id || "unknown");
  console.log("- Admin Form détecté:", adminForm ? "✅" : "❌");
  console.log("- Gravity Forms:", typeof gform !== "undefined" ? "✅" : "❌");

  // Afficher les champs de la page actuelle
  const fields = document.querySelectorAll(".gfield");
  console.log(`- Champs visibles: ${fields.length}`);

  // Afficher seulement les premiers champs pour éviter le spam
  const fieldsToShow = Array.from(fields).slice(0, 10);
  fieldsToShow.forEach((field, index) => {
    const label = field.querySelector("label")?.textContent || "Sans label";
    const type =
      field.querySelector("input, select, textarea")?.type || "unknown";
    console.log(`  ${index + 1}. ${label} (${type})`);
  });

  if (fields.length > 10) {
    console.log(`  ... et ${fields.length - 10} autres champs`);
  }

  // Vérifier les pages du formulaire
  const pageInputs = document.querySelectorAll('input[name*="page"]');
  console.log(`- Inputs de page trouvés: ${pageInputs.length}`);
  pageInputs.forEach((input, index) => {
    console.log(`  ${index + 1}. ${input.name} = ${input.value}`);
  });
}

// Initialisation automatique
debugPageTransitions();

// Fonction pour tester les hooks PHP
function testPHPHooks() {
  console.log("🧪 Test des hooks PHP...");

  // Faire une requête AJAX pour déclencher les hooks
  const formData = new FormData();
  formData.append("action", "test_page_transition");
  formData.append("form_id", "1");
  formData.append("source_page", "15");
  formData.append("target_page", "30");

  fetch("/wp-admin/admin-ajax.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.text())
    .then((data) => {
      console.log("📡 Réponse du serveur:", data);
    })
    .catch((error) => {
      console.log("❌ Erreur AJAX:", error);
    });
}

// Messages d'aide
console.log("");
console.log("🔧 COMMANDES DISPONIBLES:");
console.log("- showFormState() : Afficher l'état du formulaire");
console.log("- forcePageTransition() : Forcer une transition de page");
console.log("- debugPageTransitions() : Réactiver la surveillance");
console.log("- testPHPHooks() : Tester les hooks PHP côté serveur");
console.log("");

// Afficher l'état initial
setTimeout(showFormState, 1000);
