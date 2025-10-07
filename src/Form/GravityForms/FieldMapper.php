<?php
/**
 * Mapping des champs Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\DataHelper;
use WcQualiopiFormation\Helpers\SiretFormatter;
use WcQualiopiFormation\Form\MentionsLegales\MentionsHelper;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\GravityForms\FieldFormatter;
use WcQualiopiFormation\Form\GravityForms\RepresentantExtractor;
use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de mapping des champs entre API SIREN et Gravity Forms
 *
 * Fonctionnalités :
 * - Récupération mapping configuré par formulaire
 * - Transformation données API → champs GF
 * - Extraction données représentant légal
 */
class FieldMapper {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du formatter
	 *
	 * @var FieldFormatter
	 */
	private $formatter;

	/**
	 * Instance de l'extracteur
	 *
	 * @var RepresentantExtractor
	 */
	private $extractor;

	/**
	 * Mapping par défaut des champs (Formulaire ID 1)
	 *
	 * @var array
	 */
	private const DEFAULT_MAPPING = array(
		'siret'            => '1',     // SIRET.
		'denomination'     => '12',    // Dénomination.
		'adresse'          => '8.1',   // Adresse (sans CP/Ville).
		'code_postal'      => '8.5',   // Code postal.
		'ville'            => '8.3',   // Ville.
		'code_ape'         => '10',    // Code APE.
		'libelle_ape'      => '11',    // Libellé APE.
		'date_creation'    => '14',    // Date de création.
		'statut_actif'     => '15',    // Statut actif/inactif.
		'mentions_legales' => '13',    // Mentions légales.
		'prenom'           => '7.3',   // Prénom représentant.
		'nom'              => '7.6',   // Nom représentant.
		'telephone'        => '9',     // Téléphone représentant (E164).
		'email'            => '10',    // Email représentant (RFC compliant).
	);

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->logger = Logger::get_instance();
		$this->formatter = new FieldFormatter( $this->logger );
		$this->extractor = new RepresentantExtractor( $this->logger );
	}

	/**
	 * Récupère le mapping configuré pour un formulaire
	 *
	 * @param int $form_id ID du formulaire.
	 * @return array Le mapping ou tableau vide.
	 */
	public function get_field_mapping( $form_id ) {
		$this->logger->debug( '[FieldMapper] get_field_mapping DEBUT', array( 'form_id' => $form_id ) );

		$settings = get_option( Constants::OPTION_SETTINGS, array() );
		$mappings = $settings['form_mappings'] ?? array();

		$this->logger->debug( '[FieldMapper] Settings recuperes', array(
			'has_form_mappings' => isset( $settings['form_mappings'] ),
			'mappings_count' => count( $mappings ),
		) );

		if ( isset( $mappings[ $form_id ] ) ) {
			LoggingHelper::log_mapping_operation( $this->logger, 'get', 'custom_mapping', 'found', array(
				'form_id' => $form_id,
				'mapping_keys' => array_keys( $mappings[ $form_id ] ),
			) );
			return $mappings[ $form_id ];
		}

		// Fallback sur mapping par défaut pour Form ID 1.
		if ( 1 === $form_id ) {
			LoggingHelper::log_mapping_operation( $this->logger, 'get', 'default_mapping', 'form_id_1' );
			return self::DEFAULT_MAPPING;
		}

		LoggingHelper::log_validation_error( $this->logger, 'mapping', $form_id, 'Aucun mapping trouvé pour ce formulaire' );
		return array();
	}

	/**
	 * Enregistre le mapping pour un formulaire
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $mapping Le mapping des champs.
	 * @return bool True si succès.
	 */
	public function save_field_mapping( $form_id, $mapping ) {
		LoggingHelper::log_mapping_operation( $this->logger, 'save', 'field_mapping', 'start', array(
			'form_id' => $form_id,
			'mapping_fields' => array_keys( $mapping ),
		) );

		$settings = get_option( Constants::OPTION_SETTINGS, array() );

		if ( ! isset( $settings['form_mappings'] ) ) {
			$settings['form_mappings'] = array();
		}

		$settings['form_mappings'][ $form_id ] = $mapping;

		$result = update_option( Constants::OPTION_SETTINGS, $settings );

		LoggingHelper::log_mapping_operation( $this->logger, 'save', 'field_mapping', 'success', array(
			'form_id' => $form_id,
			'success' => $result,
		) );

		return $result;
	}

	/**
	 * Transforme les données API en données de formulaire
	 *
	 * @param array  $company_data Données de l'entreprise depuis API.
	 * @param array  $mapping Mapping des champs.
	 * @param string $mentions_legales Mentions légales générées.
	 * @return array Données mappées pour le formulaire.
	 */
	public function map_data_to_fields( $company_data, $mapping, $mentions_legales = '' ) {
		LoggingHelper::log_mapping_operation( $this->logger, 'map', 'data_to_fields', 'start', array(
			'company_data_keys' => array_keys( $company_data ),
			'mapping_keys' => array_keys( $mapping ),
			'has_mentions' => ! empty( $mentions_legales ),
		) );

		$mapped_data = array();

		// Mapper les champs de base
		$mapped_data = $this->map_basic_fields( $company_data, $mapping, $mapped_data );

		// Mapper les champs d'adresse
		$mapped_data = $this->map_address_fields( $company_data, $mapping, $mapped_data );

		// Mapper les champs d'entreprise
		$mapped_data = $this->map_company_fields( $company_data, $mapping, $mapped_data );

		// Mapper les mentions légales
		$mapped_data = $this->map_mentions_legales( $mentions_legales, $mapping, $mapped_data );

		LoggingHelper::log_mapping_operation( $this->logger, 'map', 'data_to_fields', 'success', array(
			'mapped_fields_count' => count( $mapped_data ),
			'field_ids' => array_keys( $mapped_data ),
		) );

		return $mapped_data;
	}

	/**
	 * Mappe les champs de base (SIRET, dénomination, représentant)
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $mapping Mapping des champs.
	 * @param array $mapped_data Données déjà mappées.
	 * @return array Données mappées avec les champs de base.
	 */
	private function map_basic_fields( $company_data, $mapping, $mapped_data ) {
		// SIRET - Avec formatage.
		if ( ! empty( $mapping['siret'] ) && ! empty( $company_data['siret'] ) ) {
			$siret_formatted = SiretFormatter::format_siret( $company_data['siret'] );
			$mapped_data[ $mapping['siret'] ] = $siret_formatted;
			$this->logger->debug( '[FieldMapper] SIRET mappe', array(
				'field_id' => $mapping['siret'],
				'value' => $siret_formatted,
			) );
		}

		// Dénomination.
		if ( ! empty( $mapping['denomination'] ) && ! empty( $company_data['denomination'] ) ) {
			$mapped_data[ $mapping['denomination'] ] = $company_data['denomination'];
			$this->logger->debug( '[FieldMapper] Denomination mappee', array(
				'field_id' => $mapping['denomination'],
				'value' => $company_data['denomination'],
			) );
		}

		// Téléphone formaté E164 (depuis representant).
		$representant = $company_data['representant'] ?? array();
		if ( ! empty( $mapping['telephone'] ) && ! empty( $representant['telephone'] ) ) {
			$mapped_data[ $mapping['telephone'] ] = $representant['telephone'];
			$this->logger->debug( '[FieldMapper] Téléphone mappé', array(
				'field_id' => $mapping['telephone'],
				'value' => $representant['telephone'],
			) );
		}

		// Email validé RFC (depuis representant).
		if ( ! empty( $mapping['email'] ) && ! empty( $representant['email'] ) ) {
			$mapped_data[ $mapping['email'] ] = $representant['email'];
			$this->logger->debug( '[FieldMapper] Email mappé', array(
				'field_id' => $mapping['email'],
				'value' => $representant['email'],
			) );
		}

		return $mapped_data;
	}

	/**
	 * Mappe les champs d'adresse
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $mapping Mapping des champs.
	 * @param array $mapped_data Données déjà mappées.
	 * @return array Données mappées avec les champs d'adresse.
	 */
	private function map_address_fields( $company_data, $mapping, $mapped_data ) {
		// Adresse (sans CP/Ville).
		if ( ! empty( $mapping['adresse'] ) ) {
			$adresse = $this->formatter->format_adresse_sans_cp_ville( $company_data );
			$mapped_data[ $mapping['adresse'] ] = $adresse;
			$this->logger->debug( '[FieldMapper] Adresse mappee', array(
				'field_id' => $mapping['adresse'],
				'value' => $adresse,
			) );
		}

		// Code postal.
		if ( ! empty( $mapping['code_postal'] ) && ! empty( $company_data['adresse_cp'] ) ) {
			$mapped_data[ $mapping['code_postal'] ] = $company_data['adresse_cp'];
			$this->logger->debug( '[FieldMapper] Code postal mappe', array(
				'field_id' => $mapping['code_postal'],
				'value' => $company_data['adresse_cp'],
			) );
		}

		// Ville.
		if ( ! empty( $mapping['ville'] ) && ! empty( $company_data['adresse_ville'] ) ) {
			$mapped_data[ $mapping['ville'] ] = $company_data['adresse_ville'];
			$this->logger->debug( '[FieldMapper] Ville mappee', array(
				'field_id' => $mapping['ville'],
				'value' => $company_data['adresse_ville'],
			) );
		}

		return $mapped_data;
	}

	/**
	 * Mappe les champs d'entreprise (forme juridique, statut, type)
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $mapping Mapping des champs.
	 * @param array $mapped_data Données déjà mappées.
	 * @return array Données mappées avec les champs d'entreprise.
	 */
	private function map_company_fields( $company_data, $mapping, $mapped_data ) {
		// Forme juridique - Avec formatage via MentionsHelper.
		if ( ! empty( $mapping['forme_juridique'] ) && ! empty( $company_data['forme_juridique'] ) ) {
			$mapped_data[ $mapping['forme_juridique'] ] = $this->formatter->format_forme_juridique( $company_data['forme_juridique'] );
			$this->logger->debug( '[FieldMapper] Forme juridique mappee', array(
				'field_id' => $mapping['forme_juridique'],
				'raw' => $company_data['forme_juridique'],
			) );
		}

		// Code APE (pas fourni par API SIREN actuellement).
		if ( ! empty( $mapping['code_ape'] ) ) {
			$mapped_data[ $mapping['code_ape'] ] = ''; // TODO: Ajouter si disponible dans API.
			$this->logger->debug( '[FieldMapper] Code APE non disponible' );
		}

		// Libellé APE (pas fourni par API SIREN actuellement).
		if ( ! empty( $mapping['libelle_ape'] ) ) {
			$mapped_data[ $mapping['libelle_ape'] ] = ''; // TODO: Ajouter si disponible dans API.
			$this->logger->debug( '[FieldMapper] Libelle APE non disponible' );
		}

		// Date de création (pas fournie par API SIREN actuellement).
		if ( ! empty( $mapping['date_creation'] ) ) {
			$mapped_data[ $mapping['date_creation'] ] = ''; // TODO: Ajouter si disponible dans API.
			$this->logger->debug( '[FieldMapper] Date creation non disponible' );
		}

		// Statut actif/inactif.
		if ( ! empty( $mapping['statut_actif'] ) ) {
			$statut = $this->formatter->format_statut_actif( $company_data['is_active'] ?? true );
			$mapped_data[ $mapping['statut_actif'] ] = $statut;
			$this->logger->debug( '[FieldMapper] Statut actif mappe', array(
				'field_id' => $mapping['statut_actif'],
				'value' => $statut,
			) );
		}

		// Type d'entreprise.
		if ( ! empty( $mapping['type_entreprise'] ) ) {
			$type_label = $this->formatter->get_type_entreprise_label( $company_data['type_entreprise'] ?? '' );
			$mapped_data[ $mapping['type_entreprise'] ] = $type_label;
			$this->logger->debug( '[FieldMapper] Type entreprise mappe', array(
				'field_id' => $mapping['type_entreprise'],
				'type_label' => $type_label,
			) );
		}

		return $mapped_data;
	}

	/**
	 * Mappe les mentions légales
	 *
	 * @param string $mentions_legales Mentions légales générées.
	 * @param array $mapping Mapping des champs.
	 * @param array $mapped_data Données déjà mappées.
	 * @return array Données mappées avec les mentions légales.
	 */
	private function map_mentions_legales( $mentions_legales, $mapping, $mapped_data ) {
		// Mentions légales.
		if ( ! empty( $mapping['mentions_legales'] ) && ! empty( $mentions_legales ) ) {
			$mapped_data[ $mapping['mentions_legales'] ] = $mentions_legales;
			$this->logger->debug( '[FieldMapper] Mentions legales mappees', array(
				'field_id' => $mapping['mentions_legales'],
				'length' => strlen( $mentions_legales ),
			) );
		}

		return $mapped_data;
	}

	/**
	 * Récupère les données du représentant depuis le formulaire
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $entry Données de l'entrée (soumission).
	 * @return array ['prenom' => string, 'nom' => string].
	 */
	public function get_representant_data( $form_id, $entry ) {
		return $this->extractor->extract_from_entry( $form_id, $entry, $this->get_field_mapping( $form_id ) );
	}

	/**
	 * Vérifie si un formulaire a un mapping configuré
	 *
	 * @param int $form_id ID du formulaire.
	 * @return bool True si mappé.
	 */
	public function form_has_mapping( $form_id ) {
		$mapping = $this->get_field_mapping( $form_id );
		$has_mapping = ! empty( $mapping['siret'] );

		$this->logger->debug( '[FieldMapper] form_has_mapping', array(
			'form_id' => $form_id,
			'has_mapping' => $has_mapping,
		) );

		return $has_mapping;
	}

	/**
	 * Récupère l'ID du champ SIRET pour un formulaire
	 *
	 * @param int $form_id ID du formulaire.
	 * @return string|false L'ID du champ SIRET ou false.
	 */
	public function get_siret_field_id( $form_id ) {
		$mapping = $this->get_field_mapping( $form_id );

		if ( empty( $mapping ) ) {
			return false;
		}

		$siret_field_id = $mapping['siret'] ?? false;

		$this->logger->debug( '[FieldMapper] get_siret_field_id', array(
			'form_id' => $form_id,
			'siret_field_id' => $siret_field_id,
		) );

		return $siret_field_id;
	}

	/**
	 * Récupère tous les formulaires avec mapping configuré
	 *
	 * @return array IDs des formulaires.
	 */
	public function get_mapped_forms() {
		$settings = get_option( Constants::OPTION_SETTINGS, array() );
		$mappings = $settings['form_mappings'] ?? array();

		$form_ids = array_keys( $mappings );

		$this->logger->debug( '[FieldMapper] get_mapped_forms', array(
			'count' => count( $form_ids ),
			'form_ids' => $form_ids,
		) );

		return $form_ids;
	}
}
