<?php
/**
 * Fusion des données API SIREN
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de fusion des données établissement + unité légale
 */
class SirenDataMerger {

/**
	 * Instance du validator
	 *
	 * @var SirenValidator
	 */
	private $validator;

	/**
	 * Constructeur
	 *
	 * @param SirenValidator $validator Instance du validator.
	 */
	public function __construct( SirenValidator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Fusionne les données établissement + unité légale
	 *
	 * @param array  $etablissement_response Réponse API établissement.
	 * @param array  $unite_legale_response Réponse API unité légale.
	 * @param string $siret SIRET.
	 * @param string $siren SIREN.
	 * @return array Données fusionnées et normalisées.
	 */
	public function merge( $etablissement_response, $unite_legale_response, $siret, $siren ) {
		LoggingHelper::info( '[SirenDataMerger] merge DEBUT', array(
			'etablissement_keys' => array_keys( $etablissement_response ),
			'unite_legale_keys'  => array_keys( $unite_legale_response ),
		) );

		$etablissement = $this->extract_etablissement( $etablissement_response );
		$unite_legale  = $this->extract_unite_legale( $unite_legale_response );

		LoggingHelper::debug( '[SirenDataMerger] Donnees extraites', array(
			'etablissement_keys' => array_keys( $etablissement ),
			'unite_legale_keys'  => array_keys( $unite_legale ),
		) );

		$merged_data = $this->build_merged_data( $etablissement, $unite_legale, $siret, $siren );

		LoggingHelper::info( '[SirenDataMerger] merge TERMINE', array(
			'type_entreprise' => $merged_data['type_entreprise'],
			'denomination'    => $merged_data['denomination'],
		) );

		return $merged_data;
	}

	/**
	 * Extrait les données établissement de l'enveloppe API
	 *
	 * @param array $response Réponse API.
	 * @return array Données établissement.
	 */
	private function extract_etablissement( $response ) {
		return $response['etablissement'] ?? $response;
	}

	/**
	 * Extrait les données unité légale de l'enveloppe API
	 *
	 * @param array $response Réponse API.
	 * @return array Données unité légale.
	 */
	private function extract_unite_legale( $response ) {
		return $response['unite_legale'] ?? $response;
	}

	/**
	 * Construit les données fusionnées
	 *
	 * @param array  $etablissement Données établissement.
	 * @param array  $unite_legale Données unité légale.
	 * @param string $siret SIRET.
	 * @param string $siren SIREN.
	 * @return array Données fusionnées.
	 */
	private function build_merged_data( $etablissement, $unite_legale, $siret, $siren ) {
		return array(
			'siret'              => $siret,
			'siren'              => $siren,
			'denomination'       => $unite_legale['denomination'] ?? '',
			'nom'                => $unite_legale['nom'] ?? '',
			'prenom'             => $unite_legale['prenom'] ?? '',
			'forme_juridique'    => $unite_legale['categorie_juridique'] ?? '',
			'capital'            => $unite_legale['capital'] ?? null,
			'adresse_numero'     => $etablissement['numero_voie'] ?? '',
			'adresse_voie'       => $this->build_adresse_voie( $etablissement ),
			'adresse_complement' => $etablissement['complement_adresse'] ?? '',
			'adresse_cp'         => $etablissement['code_postal'] ?? '',
			'adresse_ville'      => $etablissement['libelle_commune'] ?? '',
			'etat_administratif' => $unite_legale['etat_administratif'] ?? 'actif',
			'type_entreprise'    => $this->validator->determine_entreprise_type( $unite_legale ),
			'is_active'          => $this->validator->is_active( $unite_legale ),
		);
	}

	/**
	 * Construit l'adresse complète de la voie
	 *
	 * @param array $etablissement Données établissement.
	 * @return string Adresse voie complète.
	 */
	private function build_adresse_voie( $etablissement ) {
		return trim(
			( $etablissement['type_voie'] ?? '' ) . ' ' . ( $etablissement['libelle_voie'] ?? '' )
		);
	}
}

