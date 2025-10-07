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
      // Champ 7.3 = Prénom, Champ 7.6 = Nom, Champ 9 = Téléphone, Champ 10 = Email
      const prenomValue = $("#input_" + formId + "_7_3").val() || "";
      const nomValue = $("#input_" + formId + "_7_6").val() || "";
      const telephoneValue = $("#input_" + formId + "_9").val() || "";
      const emailValue = $("#input_" + formId + "_10").val() || "";

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

      // Validation : Téléphone OBLIGATOIRE
      if (!telephoneValue || telephoneValue.trim() === "") {
        this.showMessage(
          $message,
          "⚠️ Veuillez renseigner le numéro de téléphone.",
          "error"
        );
        return;
      }

      // Validation : Email OBLIGATOIRE
      if (!emailValue || emailValue.trim() === "") {
        this.showMessage(
          $message,
          "⚠️ Veuillez renseigner l'adresse email.",
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
          telephone: telephoneValue,
          email: emailValue,
        },
        success: (response) => {
          if (response.success && response.data) {
            console.log("[WCQF] Réponse AJAX complète:", response.data);

            // Étape 1 : Remplir les champs API
            this.fillFormFields(formId, response.data);
            console.log("[WCQF] Champs API remplis:", response.data);

            // Étape 2 : RÉINJECTER les données formatées dans les champs
            if (response.data.representant) {
              console.log(
                "[WCQF] Réinjection données formatées:",
                response.data.representant
              );
              console.log(
                "[WCQF DEBUG] Contenu complet representant:",
                JSON.stringify(response.data.representant, null, 2)
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

              if (response.data.representant.telephone) {
                $("#input_" + formId + "_9").val(
                  response.data.representant.telephone
                );
                console.log(
                  "[WCQF] Téléphone réinjecté (E164):",
                  response.data.representant.telephone
                );
              }

              if (response.data.representant.email) {
                $("#input_" + formId + "_10").val(
                  response.data.representant.email
                );
                console.log(
                  "[WCQF] Email réinjecté (validé):",
                  response.data.representant.email
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
     * Surveille les changements sur les champs Prénom/Nom/Téléphone/Email
     */
    watchRepresentantFields: function (formId) {
      const self = this;
      let updateTimeout;

      // Surveillance nom/prénom pour mentions légales
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

      // Surveillance téléphone pour formatage temps réel
      $("#input_" + formId + "_9").on("blur", function () {
        const phoneValue = $(this).val();
        if (phoneValue && phoneValue.trim() !== "") {
          self.formatPhoneField($(this), phoneValue);
        }
      });

      // Surveillance email pour validation temps réel
      $("#input_" + formId + "_10").on("blur", function () {
        const emailValue = $(this).val();
        if (emailValue && emailValue.trim() !== "") {
          self.validateEmailField($(this), emailValue);
        }
      });
    },

    /**
     * Formate un champ téléphone côté client (feedback immédiat)
     */
    formatPhoneField: function ($field, phoneValue) {
      console.log("[WCQF Frontend] Formatage téléphone début", {
        phoneValue: phoneValue,
        fieldId: $field.attr("id"),
      });

      // Nettoyage : garder seulement les chiffres
      let cleaned = phoneValue.replace(/[^0-9]/g, "");

      console.log("[WCQF Frontend] Nettoyage téléphone", {
        original: phoneValue,
        cleaned: cleaned,
      });

      // Validation longueur et préfixe
      if (cleaned.length === 10 && /^0[1-9]/.test(cleaned)) {
        // Formatage E164 : +33 + numéro sans le 0
        let e164 = "+33" + cleaned.substring(1);
        $field.val(e164);

        console.log("[WCQF Frontend] Formatage E164 réussi", {
          original: phoneValue,
          e164: e164,
        });

        // Feedback visuel positif
        this.showFieldFeedback($field, "success", "Numéro formaté en E164");
      } else if (cleaned.length > 0) {
        console.warn("[WCQF Frontend] Formatage E164 échoué", {
          cleaned: cleaned,
          length: cleaned.length,
        });

        // Feedback visuel d'erreur
        this.showFieldFeedback(
          $field,
          "error",
          "Format invalide (10 chiffres requis)"
        );
      }
    },

    /**
     * Valide et formate un champ email côté client (feedback immédiat)
     */
    validateEmailField: function ($field, emailValue) {
      console.log("[WCQF Frontend] Validation email début", {
        emailValue: emailValue,
        fieldId: $field.attr("id"),
      });

      // Étape 1 : Formater en minuscules et supprimer espaces
      let formatted = emailValue.toLowerCase().trim();

      console.log("[WCQF Frontend] Formatage email", {
        original: emailValue,
        formatted: formatted,
      });

      // Étape 2 : Validation regex basique
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const isValid = emailRegex.test(formatted);

      console.log("[WCQF Frontend] Validation email résultat", {
        email: formatted,
        isValid: isValid,
      });

      if (isValid) {
        // Réinjecter l'email formaté (minuscules) dans le champ
        $field.val(formatted);

        console.log("[WCQF Frontend] Email formaté et réinjecté", {
          original: emailValue,
          formatted: formatted,
        });

        // Feedback visuel positif
        this.showFieldFeedback(
          $field,
          "success",
          "Email formaté en minuscules"
        );
      } else {
        // Feedback visuel d'erreur
        this.showFieldFeedback($field, "error", "Format email invalide");
      }
    },

    /**
     * Affiche un feedback visuel pour un champ
     */
    showFieldFeedback: function ($field, type, message) {
      console.log("[WCQF Frontend] Affichage feedback", {
        fieldId: $field.attr("id"),
        type: type,
        message: message,
      });

      // Supprimer les anciens feedbacks
      $field.siblings(".wcqf-field-feedback").remove();

      // Ajouter le nouveau feedback
      const $feedback = $(
        '<div class="wcqf-field-feedback wcqf-feedback-' +
          type +
          '">' +
          message +
          "</div>"
      );
      $field.after($feedback);

      // Auto-suppression après 3 secondes
      setTimeout(function () {
        $feedback.fadeOut(300, function () {
          $(this).remove();
        });
      }, 3000);
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
