<?php
/**
 * Configuration du champ SIRET dans Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;

/**
 * Classe de configuration du champ SIRET
 *
 * Fonctionnalités :
 * - Enregistrement du champ custom SIRET dans GF
 * - Ajout du bouton "Vérifier le SIRET"
 * - Enqueue des assets CSS/JS
 */
class SirenField {

	/**
	 * Initialise les hooks
	 */
	public function init_hooks() {
		// TODO: Implémenter hooks Gravity Forms
		// - gform_field_standard_settings
		// - gform_editor_js
		// - gform_enqueue_scripts
	}

	/**
	 * Enregistre les assets frontend
	 *
	 * @param array $form Formulaire Gravity Forms.
	 * @param bool  $is_ajax Si c'est un chargement AJAX.
	 */
	public function enqueue_frontend_assets( $form, $is_ajax ) {
		// TODO: Enqueue CSS/JS frontend
		// - Bouton "Vérifier le SIRET"
		// - AJAX handlers
		// - Styles formulaire
	}

	/**
	 * Enregistre les assets admin
	 */
	public function enqueue_admin_assets() {
		// TODO: Enqueue CSS/JS admin
		// - Éditeur de formulaire GF
		// - Configuration champ SIRET
	}

	/**
	 * Ajoute les paramètres du champ SIRET
	 *
	 * @param int $position Position du champ.
	 * @param int $form_id ID du formulaire.
	 */
	public function add_field_settings( $position, $form_id ) {
		// TODO: Ajouter paramètres custom pour champ SIRET
	}
}

