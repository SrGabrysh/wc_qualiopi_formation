<?php
/**
 * Récupération de valeurs calculées Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;

/**
 * Classe de récupération de valeurs calculées
 *
 * Responsabilité unique : Récupérer les valeurs de champs calculés
 * dans un formulaire Gravity Forms, principalement utilisé pour
 * le score de positionnement (champ ID 27).
 *
 * Fonctionnalités :
 * - Récupération valeur calculée d'un champ spécifique
 * - Validation formulaire et entrée
 * - Gestion d'erreurs avec logging détaillé
 * - Support du système de mapping existant
 */
class CalculationRetriever {

	/**
	 * ID du champ de calcul par défaut (score de positionnement)
	 *
	 * @var int
	 */
	private const DEFAULT_CALCULATION_FIELD_ID = 27;

	/**
	 * Page source (d'où vient l'utilisateur)
	 *
	 * @var int
	 */
	private const SOURCE_PAGE_ID = 15;

	/**
	 * Page cible (où va l'utilisateur)
	 *
	 * @var int
	 */
	private const TARGET_PAGE_ID = 30;

	/**
	 * Instance du FieldMapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Constructeur
	 *
	 * @param FieldMapper $field_mapper Instance du FieldMapper.
	 */
	public function __construct( FieldMapper $field_mapper ) {
		$this->field_mapper = $field_mapper;
		
		LoggingHelper::info(
			'CalculationRetriever initialized',
			array( 'default_field_id' => self::DEFAULT_CALCULATION_FIELD_ID )
		);
	}

	/**
	 * Récupère la valeur calculée d'un champ
	 *
	 * Cette méthode est la méthode publique principale de la classe.
	 * Elle récupère la valeur d'un champ calculé dans un formulaire GF.
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $entry Données de l'entrée (soumission partielle ou complète).
	 * @param int   $field_id ID du champ de calcul (optionnel, défaut 27).
	 * @return float|false La valeur calculée ou false en cas d'erreur.
	 */
	public function get_calculated_value( $form_id, $entry, $field_id = self::DEFAULT_CALCULATION_FIELD_ID ) {
		LoggingHelper::info( '[CalculationRetriever] Début récupération valeur calculée', array(
			'form_id'  => $form_id,
			'field_id' => $field_id,
			'entry_id' => $entry['id'] ?? 'partial',
		) );

		// Validation des entrées
		if ( ! $this->validate_inputs( $form_id, $entry, $field_id ) ) {
			return false;
		}

		// Récupération du formulaire
		$form = $this->get_form( $form_id );
		if ( ! $form ) {
			return false;
		}

		// Extraction de la valeur du champ calculé
		$calculated_value = $this->extract_calculation_field( $form, $entry, $field_id );

		if ( $calculated_value === false ) {
			$this->log_error( 'Impossible d\'extraire la valeur calculée', array(
				'form_id'  => $form_id,
				'field_id' => $field_id,
			) );
			return false;
		}

		LoggingHelper::info( '[CalculationRetriever] Valeur calculée récupérée avec succès', array(
			'form_id'  => $form_id,
			'field_id' => $field_id,
			'value'    => $calculated_value,
		) );

		return $calculated_value;
	}

	/**
	 * Récupère la valeur calculée lors du passage de page
	 *
	 * Cette méthode est spécifique au tunnel Qualiopi.
	 * Elle vérifie que l'utilisateur vient bien de la page 15
	 * avant de récupérer le score de positionnement.
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $entry Données de l'entrée.
	 * @param int   $current_page Page actuelle.
	 * @param int   $target_page Page cible.
	 * @return float|false La valeur calculée ou false.
	 */
	public function get_value_on_page_transition( $form_id, $entry, $current_page, $target_page ) {
		LoggingHelper::debug( '[CalculationRetriever] Vérification transition de page', array(
			'form_id'      => $form_id,
			'current_page' => $current_page,
			'target_page'  => $target_page,
		) );

		// Vérifier que c'est la bonne transition (page 15 → page 30)
		if ( $current_page !== self::SOURCE_PAGE_ID || $target_page !== self::TARGET_PAGE_ID ) {
			LoggingHelper::debug( '[CalculationRetriever] Transition de page non concernée', array(
				'current_page'  => $current_page,
				'target_page'   => $target_page,
				'expected_from' => self::SOURCE_PAGE_ID,
				'expected_to'   => self::TARGET_PAGE_ID,
			) );
			return false;
		}

		// Récupérer la valeur calculée
		return $this->get_calculated_value( $form_id, $entry );
	}

	/**
	 * Valide les entrées de la méthode principale
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $entry Données de l'entrée.
	 * @param int   $field_id ID du champ.
	 * @return bool True si valide, false sinon.
	 */
	private function validate_inputs( $form_id, $entry, $field_id ) {
		// Validation form_id
		if ( ! is_int( $form_id ) || $form_id <= 0 ) {
			$this->log_error( 'ID de formulaire invalide', array( 'form_id' => $form_id ) );
			return false;
		}

		// Validation entry
		if ( ! is_array( $entry ) || empty( $entry ) ) {
			$this->log_error( 'Données d\'entrée invalides', array( 'form_id' => $form_id ) );
			return false;
		}

		// Validation field_id
		if ( ! is_int( $field_id ) || $field_id <= 0 ) {
			$this->log_error( 'ID de champ invalide', array(
				'form_id'  => $form_id,
				'field_id' => $field_id,
			) );
			return false;
		}

		return true;
	}

	/**
	 * Récupère le formulaire Gravity Forms
	 *
	 * @param int $form_id ID du formulaire.
	 * @return array|false Le formulaire ou false.
	 */
	private function get_form( $form_id ) {
		// Vérifier que Gravity Forms est disponible
		if ( ! class_exists( 'GFAPI' ) ) {
			$this->log_error( 'Gravity Forms non disponible' );
			return false;
		}

		// Récupérer le formulaire
		$form = \GFAPI::get_form( $form_id );

		if ( ! $form || \is_wp_error( $form ) ) {
			$this->log_error( 'Formulaire non trouvé', array( 'form_id' => $form_id ) );
			return false;
		}

		// Vérifier que le formulaire a un mapping
		if ( ! $this->field_mapper->form_has_mapping( $form_id ) ) {
			LoggingHelper::warning( '[CalculationRetriever] Formulaire sans mapping', array(
				'form_id' => $form_id,
			) );
			// On continue quand même pour les formulaires avec mapping par défaut
		}

		return $form;
	}

	/**
	 * Extrait la valeur du champ de calcul
	 *
	 * @param array $form Formulaire GF.
	 * @param array $entry Données de l'entrée.
	 * @param int   $field_id ID du champ de calcul.
	 * @return float|false La valeur calculée ou false.
	 */
	private function extract_calculation_field( $form, $entry, $field_id ) {
		// Rechercher le champ dans le formulaire
		$field = $this->find_field_by_id( $form, $field_id );

		if ( ! $field ) {
			$this->log_error( 'Champ de calcul non trouvé dans le formulaire', array(
				'form_id'  => $form['id'],
				'field_id' => $field_id,
			) );
			return false;
		}

		// Vérifier que c'est bien un champ de type "calculation"
		if ( ! isset( $field->type ) || $field->type !== 'number' ) {
			LoggingHelper::debug( '[CalculationRetriever] Type de champ', array(
				'field_id'   => $field_id,
				'field_type' => $field->type ?? 'unknown',
				'note'       => 'Le champ peut être de type "number" avec une formule de calcul',
			) );
		}

		// Récupérer la valeur depuis l'entrée
		$field_key = (string) $field_id;
		$raw_value = \rgar( $entry, $field_key );

		LoggingHelper::debug( '[CalculationRetriever] Extraction valeur brute', array(
			'field_id'  => $field_id,
			'raw_value' => $raw_value,
			'value_type' => gettype( $raw_value ),
		) );

		if ( $raw_value === null || $raw_value === '' ) {
			$this->log_error( 'Valeur calculée vide ou non disponible', array(
				'form_id'  => $form['id'],
				'field_id' => $field_id,
				'raw_value' => $raw_value,
			) );
			return false;
		}

		// Convertir en float et valider
		$calculated_value = $this->sanitize_and_validate_number( $raw_value );

		if ( $calculated_value === false ) {
			$this->log_error( 'Impossible de convertir la valeur en nombre', array(
				'form_id'   => $form['id'],
				'field_id'  => $field_id,
				'raw_value' => $raw_value,
			) );
			return false;
		}

		return $calculated_value;
	}

	/**
	 * Recherche un champ par son ID dans le formulaire
	 *
	 * @param array $form Formulaire GF.
	 * @param int   $field_id ID du champ recherché.
	 * @return object|false Le champ ou false.
	 */
	private function find_field_by_id( $form, $field_id ) {
		if ( empty( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->id ) && (int) $field->id === $field_id ) {
				return $field;
			}
		}

		return false;
	}

	/**
	 * Sanitize et valide une valeur numérique
	 *
	 * @param mixed $value Valeur à sanitizer.
	 * @return float|false La valeur numérique ou false.
	 */
	private function sanitize_and_validate_number( $value ) {
		// Supprimer les espaces
		$value = trim( (string) $value );

		// Vérifier que c'est un nombre valide
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		// Convertir en float
		$float_value = (float) $value;

		// Vérifier que la valeur est positive ou nulle
		if ( $float_value < 0 ) {
			LoggingHelper::warning( '[CalculationRetriever] Valeur négative détectée', array(
				'value' => $float_value,
			) );
		}

		return $float_value;
	}

	/**
	 * Log une erreur
	 *
	 * @param string $message Message d'erreur.
	 * @param array  $context Contexte additionnel.
	 */
	private function log_error( $message, $context = array() ) {
		LoggingHelper::error( '[CalculationRetriever] ' . $message, array_merge( array(
			'channel' => 'calculation',
		), $context ) );
	}
}

