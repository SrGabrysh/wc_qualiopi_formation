/**
 * JavaScript Frontend - WC Qualiopi Formation (Module Form)
 *
 * @package WcQualiopiFormation
 */

(function ($) {
  "use strict";

  const WCQFFormFrontend = {
    /**
     * Initialisation
     */
    init: function () {
      this.bindEvents();
      this.initRepresentantWatchers();
    },

    /**
     * Initialise la surveillance des champs Prénom/Nom
     */
    initRepresentantWatchers: function () {
      const self = this;
      // Surveiller tous les formulaires contenant un bouton de vérification
      $(".wcqf-form-verify-button").each(function () {
        const formId = $(this).data("form-id");
        if (formId) {
          self.watchRepresentantFields(formId);
        }
      });
    },

    /**
     * Attache les événements
     */
    bindEvents: function () {
      $(document).on(
        "click",
        ".wcqf-form-verify-button",
        this.handleVerifyClick.bind(this)
      );
      $(document).on(
        "change",
        'input[id^="input_"]',
        this.detectManualEdit.bind(this)
      );
    },

    /**
     * Gère le clic sur le bouton "Vérifier"
     */
    handleVerifyClick: function (e) {
      e.preventDefault();

      const $button = $(e.currentTarget);
      const formId = $button.data("form-id");
      const fieldId = $button.data("field-id");
      const nonce = $button.data("nonce");

      const $container = $button.closest(".wcqf-form-verify-container");
      const $loader = $container.find(".wcqf-form-loader");
      const $message = $container.find(".wcqf-form-message");

      // Récupérer la valeur du SIRET
      const siretValue = $(
        "#input_" + formId + "_" + String(fieldId).replace(".", "_")
      ).val();

      if (!siretValue || siretValue.trim() === "") {
        this.showMessage(
          $message,
          wcqfFormData.messages.error_invalid,
          "error"
        );
        return;
      }

      // Récupérer les données du représentant depuis les champs spécifiques
      // Champ 7.3 = Prénom, Champ 7.6 = Nom
      const prenomValue = $("#input_" + formId + "_7_3").val() || "";
      const nomValue = $("#input_" + formId + "_7_6").val() || "";

      // Validation : Nom et Prénom sont OBLIGATOIRES
      if (
        !nomValue ||
        nomValue.trim() === "" ||
        !prenomValue ||
        prenomValue.trim() === ""
      ) {
        this.showMessage(
          $message,
          wcqfFormData.messages.error_representant_required,
          "error"
        );
        return;
      }

      // Validation : Refuser les chiffres dans nom et prénom
      if (/\d/.test(nomValue) || /\d/.test(prenomValue)) {
        this.showMessage(
          $message,
          wcqfFormData.messages.error_representant_invalid,
          "error"
        );
        return;
      }

      // Désactiver le bouton et afficher le loader
      $button.prop("disabled", true);
      $loader.show();
      $message.empty();

      // Appel AJAX
      $.ajax({
        url: wcqfFormData.ajax_url,
        type: "POST",
        data: {
          action: "wcqf_verify_siret",
          nonce: nonce,
          form_id: formId,
          siret: siretValue,
          prenom: prenomValue,
          nom: nomValue,
        },
        success: (response) => {
          if (response.success && response.data) {
            console.log("[WCQF] Réponse AJAX complète:", response.data);

            // Étape 1 : Remplir les champs API
            this.fillFormFields(formId, response.data);
            console.log("[WCQF] Champs API remplis:", response.data);

            // Étape 2 : RÉINJECTER les noms/prénoms FORMATÉS dans les champs
            if (response.data.representant) {
              console.log(
                "[WCQF] Réinjection noms formatés:",
                response.data.representant
              );

              if (response.data.representant.prenom) {
                $("#input_" + formId + "_7_3").val(
                  response.data.representant.prenom
                );
                console.log(
                  "[WCQF] Prénom réinjecté:",
                  response.data.representant.prenom
                );
              }

              if (response.data.representant.nom) {
                $("#input_" + formId + "_7_6").val(
                  response.data.representant.nom
                );
                console.log(
                  "[WCQF] Nom réinjecté:",
                  response.data.representant.nom
                );
              }

              // Étape 3 : METTRE À JOUR les mentions légales avec les noms formatés
              this.updateMentionsWithRepresentant(formId);
              console.log("[WCQF] Mentions légales mises à jour");
            }

            this.showMessage(
              $message,
              response.data.message,
              response.data.est_actif ? "success" : "warning"
            );

            // Marquer le formulaire comme vérifié
            this.markAsVerified(formId, siretValue);

            // Avertissement si entreprise inactive
            if (!response.data.est_actif) {
              this.showMessage(
                $message,
                wcqfFormData.messages.warning_inactive,
                "warning",
                true
              );
            }
          } else {
            this.showMessage(
              $message,
              response.data.message || wcqfFormData.messages.error_api,
              "error"
            );
          }
        },
        error: (xhr) => {
          let errorMsg = wcqfFormData.messages.error_api;

          // Priorité 1 : Lire le message d'erreur du backend si disponible
          if (
            xhr.responseJSON &&
            xhr.responseJSON.data &&
            xhr.responseJSON.data.message
          ) {
            errorMsg = xhr.responseJSON.data.message;
          }
          // Priorité 2 : Messages par défaut selon code HTTP
          else if (xhr.status === 404) {
            errorMsg = wcqfFormData.messages.error_not_found;
          } else if (xhr.status === 408 || xhr.statusText === "timeout") {
            errorMsg = wcqfFormData.messages.error_timeout;
          }

          this.showMessage($message, errorMsg, "error");
        },
        complete: () => {
          $button.prop("disabled", false);
          $loader.hide();
        },
      });
    },

    /**
     * Remplit les champs du formulaire avec les données
     */
    fillFormFields: function (formId, data) {
      $.each(data, (fieldId, value) => {
        const $field = $("#input_" + formId + "_" + fieldId.replace(".", "_"));
        if ($field.length) {
          $field.val(value).trigger("change");
          $field.addClass("wcqf-form-auto-filled");
        }
      });
    },

    /**
     * Met à jour les mentions légales avec le nom du représentant
     */
    updateMentionsWithRepresentant: function (formId) {
      const prenomValue = $("#input_" + formId + "_7_3").val() || "";
      const nomValue = $("#input_" + formId + "_7_6").val() || "";
      const $mentionsField = $("#input_" + formId + "_13"); // Champ mentions légales

      console.log("[WCQF] updateMentionsWithRepresentant appelé", {
        formId: formId,
        prenom: prenomValue,
        nom: nomValue,
        mentionsFieldExists: $mentionsField.length > 0,
      });

      // Ne pas mettre à jour si les champs nom/prénom sont vides
      if (!prenomValue || !nomValue) {
        console.log("[WCQF] Champs nom/prénom vides, pas de mise à jour");
        return;
      }

      if ($mentionsField.length) {
        let mentions = $mentionsField.val() || "";
        console.log("[WCQF] Mentions AVANT remplacement:", mentions);

        // Format : "NOM Prénom" (selon spécification)
        const representant = nomValue + " " + prenomValue;
        console.log("[WCQF] Représentant formaté:", representant);

        // Remplacer {REPRESENTANT} par le nom complet si le placeholder existe
        if (mentions.includes("{REPRESENTANT}")) {
          mentions = mentions.replace("{REPRESENTANT}", representant);
          console.log("[WCQF] Mentions APRÈS remplacement:", mentions);
          $mentionsField.val(mentions).trigger("change");
        } else {
          console.log(
            "[WCQF] Placeholder {REPRESENTANT} non trouvé dans les mentions"
          );
        }
      } else {
        console.warn("[WCQF] Champ mentions légales non trouvé");
      }
    },

    /**
     * Surveille les changements sur les champs Prénom/Nom
     */
    watchRepresentantFields: function (formId) {
      const self = this;
      let updateTimeout;

      $("#input_" + formId + "_7_3, #input_" + formId + "_7_6").on(
        "change blur",
        function () {
          // Délai pour éviter les appels trop fréquents
          clearTimeout(updateTimeout);
          updateTimeout = setTimeout(() => {
            self.updateMentionsWithRepresentant(formId);

            // Afficher un avertissement si modification après vérification SIRET
            const $form = $("#gform_" + formId);
            const isVerified =
              $form.find('input[name="wcqf_verified_' + formId + '"]').length >
              0;

            if (isVerified) {
              const $container = $form.find(".wcqf-form-verify-container");
              const $message = $container.find(".wcqf-form-message");

              self.showMessage(
                $message,
                wcqfFormData.messages.warning_representant_modified,
                "warning"
              );
            }
          }, 300); // Délai de 300ms
        }
      );
    },

    /**
     * Affiche un message
     */
    showMessage: function ($container, message, type, append = false) {
      const cssClass = "wcqf-form-message-" + type;
      const icon = type === "success" ? "✓" : type === "warning" ? "⚠" : "✗";

      const $msg = $("<div>")
        .addClass("wcqf-form-message-box " + cssClass)
        .html('<span class="icon">' + icon + "</span> " + message);

      if (append) {
        $container.append($msg);
      } else {
        $container.html($msg);
      }
    },

    /**
     * Marque le formulaire comme vérifié
     */
    markAsVerified: function (formId, siret) {
      // Ajouter des champs cachés pour la validation côté serveur
      const $form = $("#gform_" + formId);

      // Supprimer les anciens champs cachés
      $form.find('input[name^="wcqf_verified_"]').remove();

      // Ajouter les nouveaux
      $form.append(
        $("<input>").attr({
          type: "hidden",
          name: "wcqf_verified_" + formId,
          value: "1",
        })
      );

      $form.append(
        $("<input>").attr({
          type: "hidden",
          name: "wcqf_verified_siret_" + formId,
          value: siret,
        })
      );
    },

    /**
     * Détecte les modifications manuelles
     */
    detectManualEdit: function (e) {
      const $field = $(e.currentTarget);

      if ($field.hasClass("wcqf-form-auto-filled")) {
        $field.removeClass("wcqf-form-auto-filled");
        $field.addClass("wcqf-form-manually-edited");

        // Afficher un avertissement
        const $warning = $('<small class="wcqf-form-edit-warning">').text(
          wcqfFormData.messages.warning_modified
        );

        if (!$field.next(".wcqf-form-edit-warning").length) {
          $field.after($warning);
        }
      }
    },
  };

  // Initialisation au chargement du DOM
  $(document).ready(() => {
    WCQFFormFrontend.init();
  });
})(jQuery);
