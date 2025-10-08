<?php
/**
 * Extraction des données du représentant légal
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;

/**
 * Classe d'extraction des données représentant
 */
class RepresentantExtractor {

/**
	 * Constructeur
	 */
	public function __construct() {
	}

	/**
	 * Extrait les données du représentant depuis un formulaire
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $entry Données de l'entrée (soumission).
	 * @param array $mapping Mapping des champs.
	 * @return array ['prenom' => string, 'nom' => string].
	 */
	public function extract_from_entry( $form_id, $entry, $mapping ) {
		LoggingHelper::debug( '[RepresentantExtractor] extract_from_entry DEBUT', array( 'form_id' => $form_id ) );

		if ( empty( $mapping ) ) {
			LoggingHelper::warning( '[RepresentantExtractor] Aucun mapping, retour donnees vides' );
			return $this->get_empty_data();
		}

		$prenom = '';
		$nom    = '';

		if ( ! empty( $mapping['prenom'] ) && isset( $entry[ $mapping['prenom'] ] ) ) {
			$prenom = SanitizationHelper::sanitize_name( wp_unslash( $entry[ $mapping['prenom'] ] ) );
		}

		if ( ! empty( $mapping['nom'] ) && isset( $entry[ $mapping['nom'] ] ) ) {
			$nom = SanitizationHelper::sanitize_name( wp_unslash( $entry[ $mapping['nom'] ] ) );
		}

		LoggingHelper::info( '[RepresentantExtractor] Donnees extraites', array(
			'prenom' => $prenom,
			'nom' => $nom,
		) );

		return array(
			'prenom' => $prenom,
			'nom'    => $nom,
		);
	}

	/**
	 * Retourne des données vides
	 *
	 * @return array Structure vide.
	 */
	private function get_empty_data() {
		return array(
			'prenom' => '',
			'nom'    => '',
		);
	}
}

