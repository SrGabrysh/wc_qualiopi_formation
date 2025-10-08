/**
 * Debug JavaScript - Transitions de pages Gravity Forms
 *
 * Ce fichier trace TOUS les événements liés aux changements de pages
 * dans Gravity Forms pour diagnostiquer les problèmes de détection côté serveur.
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  const WCQFPageDebug = {
    /**
     * Configuration
     */
    config: {
      formId: 1, // ID du formulaire à surveiller
      sourcePage: 2, // Page source attendue (NUMÉRO DE PAGE, pas ID de champ !)
      targetPage: 3, // Page cible attendue (NUMÉRO DE PAGE, pas ID de champ !)
      scoreFieldId: 27, // ID du champ de score
    },

    /**
     * Clé localStorage pour sauvegarder les logs
     */
    storageKey: "wcqf_debug_logs",

    /**
     * Initialisation
     */
    init: function () {
      console.log(
        "%c[WCQF DEBUG] 🚀 Système de traçage des transitions de pages activé",
        "background: #4CAF50; color: white; padding: 5px 10px; font-weight: bold;"
      );
      console.log("[WCQF DEBUG] Configuration:", this.config);
      console.log(
        "%c[WCQF DEBUG] 💡 TIP: Activez 'Preserve log' dans DevTools pour garder les logs après rechargement",
        "background: #FF9800; color: white; padding: 3px 8px;"
      );

      // Afficher les logs précédents s'ils existent
      this.displayPreviousLogs();

      this.bindEvents();
      this.hookGravityFormsEvents();
      this.displayCurrentPageInfo();
      this.monitorFormSubmission();
    },

    /**
     * Sauvegarde un log critique dans localStorage
     */
    saveCriticalLog: function (message, data) {
      try {
        const logs = JSON.parse(localStorage.getItem(this.storageKey) || "[]");
        logs.push({
          timestamp: new Date().toISOString(),
          message: message,
          data: data,
        });

        // Garder seulement les 20 derniers logs
        if (logs.length > 20) {
          logs.shift();
        }

        localStorage.setItem(this.storageKey, JSON.stringify(logs));
      } catch (e) {
        console.warn(
          "[WCQF DEBUG] Impossible de sauvegarder dans localStorage:",
          e
        );
      }
    },

    /**
     * Affiche les logs précédents
     */
    displayPreviousLogs: function () {
      try {
        const logs = JSON.parse(localStorage.getItem(this.storageKey) || "[]");
        if (logs.length > 0) {
          console.log(
            "%c[WCQF DEBUG] 📜 Logs précédents (depuis le dernier rechargement) :",
            "background: #673AB7; color: white; padding: 3px 8px;"
          );
          logs.forEach(function (log) {
            console.log("[" + log.timestamp + "] " + log.message, log.data);
          });
        }
      } catch (e) {
        // Silently fail
      }
    },

    /**
     * Efface les logs sauvegardés
     */
    clearSavedLogs: function () {
      localStorage.removeItem(this.storageKey);
      console.log(
        "%c[WCQF DEBUG] 🗑️ Logs sauvegardés effacés",
        "background: #9E9E9E; color: white; padding: 3px 8px;"
      );
    },

    /**
     * Affiche les informations sur la page courante
     */
    displayCurrentPageInfo: function () {
      const self = this;
      const $form = $("#gform_" + this.config.formId);

      if ($form.length === 0) {
        console.warn(
          "[WCQF DEBUG] ⚠️ Formulaire #" +
            this.config.formId +
            " non trouvé sur cette page"
        );
        return;
      }

      // Récupérer la page courante
      const $currentPageInput = $form.find(
        'input[name="gform_source_page_number_' + this.config.formId + '"]'
      );
      const currentPage = $currentPageInput.val();

      console.log(
        "%c[WCQF DEBUG] 📄 Informations formulaire",
        "background: #2196F3; color: white; padding: 3px 8px;"
      );
      console.log("Form ID:", this.config.formId);
      console.log("Page actuelle:", currentPage);
      console.log("Page source attendue:", this.config.sourcePage);
      console.log("Page cible attendue:", this.config.targetPage);

      // Vérifier si on est sur la page source
      if (parseInt(currentPage) === this.config.sourcePage) {
        console.log(
          "%c[WCQF DEBUG] ✅ VOUS ÊTES SUR LA PAGE SOURCE (" +
            this.config.sourcePage +
            ")",
          "background: #FF9800; color: white; padding: 5px 10px; font-weight: bold;"
        );
        console.log(
          "[WCQF DEBUG] 👉 Cliquez sur 'Suivant' pour déclencher la transition vers la page",
          this.config.targetPage
        );

        // Vérifier la valeur du champ score
        this.displayScoreValue();
      }
    },

    /**
     * Affiche la valeur du champ score
     */
    displayScoreValue: function () {
      const $scoreField = $(
        "#input_" + this.config.formId + "_" + this.config.scoreFieldId
      );

      if ($scoreField.length > 0) {
        const scoreValue = $scoreField.val();
        console.log(
          "%c[WCQF DEBUG] 🎯 Valeur du champ score (ID " +
            this.config.scoreFieldId +
            "): " +
            scoreValue,
          "background: #9C27B0; color: white; padding: 3px 8px;"
        );

        if (!scoreValue || scoreValue === "") {
          console.warn(
            "[WCQF DEBUG] ⚠️ Le champ score est VIDE ! Le calcul n'a peut-être pas été effectué."
          );
        }
      } else {
        console.warn(
          "[WCQF DEBUG] ⚠️ Champ score (ID " +
            this.config.scoreFieldId +
            ") non trouvé sur cette page"
        );
      }
    },

    /**
     * Attache les événements aux boutons Gravity Forms
     */
    bindEvents: function () {
      const self = this;

      // Intercepter TOUS les clics sur les boutons de pagination
      $(document).on(
        "click",
        "#gform_" + this.config.formId + " .gform_next_button",
        function (e) {
          const $form = $(this).closest("form");
          const $currentPageInput = $form.find(
            'input[name="gform_source_page_number_' + self.config.formId + '"]'
          );
          const $targetPageInput = $form.find(
            'input[name="gform_target_page_number_' + self.config.formId + '"]'
          );

          const sourcePage = $currentPageInput.val();
          const targetPage = $targetPageInput.val();

          console.log(
            "%c[WCQF DEBUG] 🖱️ CLIC DÉTECTÉ sur bouton 'Suivant'",
            "background: #F44336; color: white; padding: 5px 10px; font-weight: bold;"
          );
          console.log("[WCQF DEBUG] Timestamp:", new Date().toISOString());
          console.log("[WCQF DEBUG] Page source:", sourcePage);
          console.log("[WCQF DEBUG] Page cible:", targetPage);

          // Sauvegarder ce clic dans localStorage
          self.saveCriticalLog("🖱️ CLIC sur Suivant", {
            sourcePage: sourcePage,
            targetPage: targetPage,
          });

          // Vérifier si c'est la transition attendue
          if (
            parseInt(sourcePage) === self.config.sourcePage &&
            parseInt(targetPage) === self.config.targetPage
          ) {
            console.log(
              "%c[WCQF DEBUG] ✅ TRANSITION CRITIQUE DÉTECTÉE ! (" +
                sourcePage +
                " → " +
                targetPage +
                ")",
              "background: #4CAF50; color: white; padding: 8px 15px; font-weight: bold; font-size: 14px;"
            );

            // Sauvegarder la transition critique
            self.saveCriticalLog("✅ TRANSITION CRITIQUE", {
              transition: sourcePage + " → " + targetPage,
              scoreFieldId: self.config.scoreFieldId,
            });

            // Afficher la valeur du score
            self.displayScoreValue();

            // Afficher toutes les données du formulaire
            self.displayFormData($form);
          } else {
            console.log(
              "[WCQF DEBUG] ℹ️ Transition standard (" +
                sourcePage +
                " → " +
                targetPage +
                ")"
            );
          }
        }
      );

      // Intercepter les clics sur "Précédent"
      $(document).on(
        "click",
        "#gform_" + this.config.formId + " .gform_previous_button",
        function (e) {
          console.log(
            "%c[WCQF DEBUG] ⬅️ Clic sur 'Précédent'",
            "background: #607D8B; color: white; padding: 3px 8px;"
          );
        }
      );
    },

    /**
     * Hook dans les événements Gravity Forms natifs
     */
    hookGravityFormsEvents: function () {
      const self = this;

      // Événement avant soumission de page
      $(document).on(
        "gform_page_loaded",
        function (event, formId, currentPage) {
          if (parseInt(formId) === self.config.formId) {
            console.log(
              "%c[WCQF DEBUG] 📄 Événement gform_page_loaded",
              "background: #00BCD4; color: white; padding: 3px 8px;"
            );
            console.log("[WCQF DEBUG] Form ID:", formId);
            console.log("[WCQF DEBUG] Page chargée:", currentPage);
          }
        }
      );

      // Événement après validation de formulaire
      $(document).on(
        "gform_post_render",
        function (event, formId, currentPage) {
          if (parseInt(formId) === self.config.formId) {
            console.log(
              "%c[WCQF DEBUG] 🎨 Événement gform_post_render",
              "background: #3F51B5; color: white; padding: 3px 8px;"
            );
            console.log("[WCQF DEBUG] Form ID:", formId);
            console.log("[WCQF DEBUG] Page courante:", currentPage);

            // Ré-afficher les infos après chaque rendu
            self.displayCurrentPageInfo();
          }
        }
      );
    },

    /**
     * Surveille la soumission du formulaire
     */
    monitorFormSubmission: function () {
      const self = this;

      $(document).on("submit", "#gform_" + this.config.formId, function (e) {
        const $form = $(this);
        const $currentPageInput = $form.find(
          'input[name="gform_source_page_number_' + self.config.formId + '"]'
        );
        const $targetPageInput = $form.find(
          'input[name="gform_target_page_number_' + self.config.formId + '"]'
        );

        const sourcePage = $currentPageInput.val();
        const targetPage = $targetPageInput.val();

        console.log(
          "%c[WCQF DEBUG] 📤 SOUMISSION FORMULAIRE",
          "background: #E91E63; color: white; padding: 5px 10px; font-weight: bold;"
        );
        console.log("[WCQF DEBUG] Timestamp:", new Date().toISOString());
        console.log("[WCQF DEBUG] Form ID:", self.config.formId);
        console.log("[WCQF DEBUG] Page source:", sourcePage);
        console.log("[WCQF DEBUG] Page cible:", targetPage);
        console.log("[WCQF DEBUG] URL:", window.location.href);

        // Sauvegarder la soumission
        self.saveCriticalLog("📤 SOUMISSION", {
          formId: self.config.formId,
          sourcePage: sourcePage,
          targetPage: targetPage,
          url: window.location.href,
        });

        // Vérifier si c'est la transition attendue
        if (
          parseInt(sourcePage) === self.config.sourcePage &&
          parseInt(targetPage) === self.config.targetPage
        ) {
          console.log(
            "%c[WCQF DEBUG] 🎯 SOUMISSION DE LA TRANSITION CRITIQUE DÉTECTÉE !",
            "background: #FF5722; color: white; padding: 8px 15px; font-weight: bold; font-size: 14px;"
          );
          console.log(
            "[WCQF DEBUG] 👉 Le hook PHP 'gform_post_paging' devrait se déclencher côté serveur maintenant !"
          );

          // Sauvegarder la soumission critique
          self.saveCriticalLog("🎯 SOUMISSION CRITIQUE", {
            transition: sourcePage + " → " + targetPage,
            message: "Hook PHP gform_post_paging devrait se déclencher",
          });

          // Afficher toutes les données soumises
          self.displayFormData($form);
        }
      });
    },

    /**
     * Affiche toutes les données du formulaire
     */
    displayFormData: function ($form) {
      const formData = $form.serializeArray();

      console.group(
        "%c[WCQF DEBUG] 📊 Données du formulaire",
        "background: #795548; color: white; padding: 3px 8px;"
      );

      // Filtrer et afficher seulement les champs pertinents
      const relevantFields = formData.filter(function (field) {
        // Garder les champs de navigation et le champ score
        return (
          field.name.includes("gform_source_page_number") ||
          field.name.includes("gform_target_page_number") ||
          field.name.includes("input_27") ||
          field.name.includes("is_submit_") ||
          field.name.includes("save")
        );
      });

      console.table(relevantFields);
      console.log("[WCQF DEBUG] Nombre total de champs:", formData.length);

      // Chercher spécifiquement le champ score
      const scoreField = formData.find(function (field) {
        return field.name === "input_27";
      });

      if (scoreField) {
        console.log(
          "%c[WCQF DEBUG] Score trouvé: " + scoreField.value,
          "background: #4CAF50; color: white; padding: 5px 10px; font-weight: bold;"
        );
      } else {
        console.warn(
          "[WCQF DEBUG] ⚠️ Champ score (input_27) NON TROUVÉ dans les données soumises !"
        );
      }

      console.groupEnd();
    },
  };

  // Initialisation au chargement du DOM
  $(document).ready(function () {
    WCQFPageDebug.init();
  });

  // Exposer les fonctions utiles globalement pour la console
  window.WCQFDebug = {
    clearLogs: function () {
      WCQFPageDebug.clearSavedLogs();
    },
    showLogs: function () {
      WCQFPageDebug.displayPreviousLogs();
    },
    help: function () {
      console.log(
        "%c[WCQF DEBUG] 📖 Commandes disponibles",
        "background: #2196F3; color: white; padding: 5px 10px; font-weight: bold;"
      );
      console.log("WCQFDebug.clearLogs()  - Efface les logs sauvegardés");
      console.log("WCQFDebug.showLogs()   - Affiche les logs sauvegardés");
      console.log("WCQFDebug.help()       - Affiche cette aide");
    },
  };

  // Afficher l'aide au démarrage
  console.log(
    "%c[WCQF DEBUG] 💡 Tapez WCQFDebug.help() pour voir les commandes disponibles",
    "background: #009688; color: white; padding: 3px 8px;"
  );
})(jQuery);
