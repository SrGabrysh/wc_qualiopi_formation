<?php
/**
 * Extraction des données de formulaires Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Tracking;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Helpers\SanitizationHelper;

/**
 * Classe d'extraction des données de formulaires
 *
 * Fonctionnalités :
 * - Extraction SIRET
 * - Extraction nom d'entreprise
 * - Extraction données personnelles
 * - Extraction réponses test positionnement
 * - Sérialisation données formulaire complet
 */
class DataExtractor {

	/**
	 * Instance du field mapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->field_mapper = new FieldMapper();
	}

	/**
	 * Extrait toutes les données d'un formulaire
	 *
	 * @param array $entry Entrée Gravity Forms.
	 * @param array $form Formulaire Gravity Forms.
	 * @return array Données structurées.
	 */
	public function extract( $entry, $form ) {
		// Security: Validate inputs
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return array();
		}
		return array(
			'personal' => $this->extract_personal( $entry, $form ),
			'company'  => $this->extract_company( $entry, $form ),
			'test'     => $this->extract_test_answers( $entry, $form ),
			'metadata' => $this->extract_metadata( $entry ),
			'fields'   => $this->extract_all_fields_structured( $entry, $form ),
		);
	}

	/**
	 * Extrait les données personnelles
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return array Données personnelles.
	 */
	public function extract_personal( $entry, $form ) {
		$mapping      = $this->field_mapper->get_field_mapping( $form['id'] );
		$representant = $this->field_mapper->get_representant_data( $form['id'], $entry );

		return array(
			'first_name' => $representant['prenom'] ?? '',
			'last_name'  => $representant['nom'] ?? '',
			'email'      => $this->extract_email( $entry, $form ),
			'phone'      => $this->extract_phone( $entry, $form ),
			'title'      => $representant['titre'] ?? '',
		);
	}

	/**
	 * Extrait les données d'entreprise
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return array Données entreprise.
	 */
	private function extract_company( $entry, $form ) {
		$mapping = $this->field_mapper->get_field_mapping( $form['id'] );

		$siret_field_id = $mapping['siret'] ?? null;
		$siret          = $siret_field_id ? SanitizationHelper::sanitize_siret( rgar( $entry, $siret_field_id ) ) : null;

		$denomination_field_id = $mapping['denomination'] ?? null;
		$denomination          = $denomination_field_id ? SanitizationHelper::sanitize_name( rgar( $entry, $denomination_field_id ) ) : null;

		return array(
			'siret'           => $siret,
			'name'            => $denomination,
			'address'         => rgar( $entry, $mapping['adresse'] ?? '' ),
			'postal_code'     => rgar( $entry, $mapping['code_postal'] ?? '' ),
			'city'            => rgar( $entry, $mapping['ville'] ?? '' ),
			'forme_juridique' => rgar( $entry, $mapping['forme_juridique'] ?? '' ),
			'capital'         => rgar( $entry, $mapping['capital'] ?? '' ),
		);
	}

	/**
	 * Extrait les réponses au test de positionnement
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return array Réponses au test.
	 */
	private function extract_test_answers( $entry, $form ) {
		$answers = array();

		if ( empty( $form['fields'] ) ) {
			return $answers;
		}

		foreach ( $form['fields'] as $field ) {
			// Identifier les champs de type quiz, radio, checkbox, select.
			if ( ! isset( $field->id ) || ! isset( $field->type ) ) {
				continue;
			}

			$field_type = $field->type;
			$field_id   = (string) $field->id;

			// Champs pertinents pour les tests.
			$test_field_types = array( 'quiz', 'radio', 'checkbox', 'select', 'survey' );

			if ( in_array( $field_type, $test_field_types, true ) ) {
				$field_label = (string) ( $field->label ?? '' );
				$field_value = rgar( $entry, $field_id );

				$answers[] = array(
					'field_id'    => $field_id,
					'field_type'  => $field_type,
					'field_label' => $field_label,
					'value'       => $field_value,
				);
			}
		}

		return $answers;
	}

	/**
	 * Extrait les métadonnées
	 *
	 * @param array $entry Entrée GF.
	 * @return array Métadonnées.
	 */
	private function extract_metadata( $entry ) {
		return array(
			'ip'         => $entry['ip'] ?? '',
			'user_agent' => $entry['user_agent'] ?? '',
			'source_url' => $entry['source_url'] ?? '',
			'created_at' => $entry['date_created'] ?? '',
			'entry_id'   => $entry['id'] ?? 0,
			'is_starred' => ! empty( $entry['is_starred'] ),
			'is_read'    => ! empty( $entry['is_read'] ),
		);
	}

	/**
	 * Extrait tous les champs du formulaire de manière structurée
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return array Champs structurés.
	 */
	private function extract_all_fields_structured( $entry, $form ) {
		$fields_data = array();

		if ( empty( $form['fields'] ) ) {
			return $fields_data;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! isset( $field->id ) ) {
				continue;
			}

			$field_id    = (string) $field->id;
			$field_label = (string) ( $field->label ?? '' );
			$field_type  = (string) ( $field->type ?? '' );
			$field_value = rgar( $entry, $field_id );

			// Ne pas inclure les champs cachés de token.
			if ( '9999' === $field_id ) {
				continue;
			}

			$fields_data[] = array(
				'field_id'    => $field_id,
				'field_label' => $field_label,
				'field_type'  => $field_type,
				'value'       => $this->sanitize_field_value( $field_value ),
			);
		}

		return $fields_data;
	}

	/**
	 * Extrait le SIRET depuis un formulaire
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return string|null SIRET ou null.
	 */
	public function extract_siret( $entry, $form ) {
		// Security: Validate inputs
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return null;
		}
		$mapping = $this->field_mapper->get_field_mapping( $form['id'] );

		if ( empty( $mapping['siret'] ) ) {
			return null;
		}

		$siret = rgar( $entry, $mapping['siret'] );

		return SanitizationHelper::sanitize_siret( $siret );
	}

	/**
	 * Extrait le nom d'entreprise depuis un formulaire
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return string|null Nom d'entreprise ou null.
	 */
	public function extract_company_name( $entry, $form ) {
		// Security: Validate inputs
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return null;
		}
		$mapping = $this->field_mapper->get_field_mapping( $form['id'] );

		if ( empty( $mapping['denomination'] ) ) {
			return null;
		}

		$denomination = rgar( $entry, $mapping['denomination'] );

		return SanitizationHelper::sanitize_name( $denomination );
	}

	/**
	 * Extrait l'email depuis un formulaire
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return string|null Email ou null.
	 */
	private function extract_email( $entry, $form ) {
		// Chercher d'abord dans les champs mappés.
		$mapping = $this->field_mapper->get_field_mapping( $form['id'] );

		// Chercher un champ email dans le mapping.
		foreach ( $form['fields'] as $field ) {
			if ( 'email' === $field->type && isset( $field->id ) ) {
				$email = rgar( $entry, (string) $field->id );
				if ( ! empty( $email ) ) {
					return SanitizationHelper::sanitize_post_email( 'email', $email );
				}
			}
		}

		return null;
	}

	/**
	 * Extrait le téléphone depuis un formulaire
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return string|null Téléphone ou null.
	 */
	private function extract_phone( $entry, $form ) {
		// Chercher un champ phone dans le formulaire.
		foreach ( $form['fields'] as $field ) {
			if ( 'phone' === $field->type && isset( $field->id ) ) {
				$phone = rgar( $entry, (string) $field->id );
				if ( ! empty( $phone ) ) {
					return SanitizationHelper::sanitize_phone( $phone );
				}
			}
		}

		return null;
	}

	/**
	 * Extrait tous les champs du formulaire au format JSON
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return string JSON des champs.
	 */
	public function extract_all_fields( $entry, $form ) {
		// Security: Validate inputs
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return wp_json_encode( array() );
		}
		$fields_data = $this->extract_all_fields_structured( $entry, $form );

		return wp_json_encode( $fields_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Sanitize une valeur de champ
	 *
	 * @param mixed $value Valeur à sanitizer.
	 * @return mixed Valeur sanitizée.
	 */
	private function sanitize_field_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize_field_value' ), $value );
		}

		if ( is_object( $value ) ) {
			return (object) array_map( array( $this, 'sanitize_field_value' ), (array) $value );
		}

		return SanitizationHelper::sanitize_name( (string) $value );
	}
}
