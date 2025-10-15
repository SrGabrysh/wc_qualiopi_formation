<?php
/**
 * YousignValidator - Validation des configurations Yousign
 *
 * RESPONSABILITÉ UNIQUE : Validation des données d'intégration Yousign
 * (template ID, JSON mapping, données de configuration)
 *
 * @package WcQualiopiFormation\Helpers
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YousignValidator
 * 
 * Valide les données des configurations Yousign
 */
class YousignValidator {

	/**
	 * Pattern regex UUID v4 pour template Yousign
	 */
	private const UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i';

	/**
	 * Valide un template ID Yousign (UUID v4)
	 *
	 * @param string $template_id ID du template.
	 * @return bool True si valide, false sinon.
	 */
	public function validate_template_id( string $template_id ): bool {
		$is_valid = (bool) \preg_match( self::UUID_PATTERN, $template_id );

		if ( ! $is_valid ) {
			LoggingHelper::debug( '[YousignValidator] Template ID invalide', array(
				'template_id' => $template_id,
				'expected_format' => 'UUID v4',
			) );
		}

		return $is_valid;
	}

	/**
	 * Valide et decode le contenu JSON d'un fichier mapping
	 *
	 * @param string $json_content Contenu JSON brut.
	 * @return array|\WP_Error Array décodé si valide, WP_Error sinon.
	 */
	public function validate_json_mapping( string $json_content ) {
		$decoded = \json_decode( $json_content, true );

		if ( \json_last_error() !== JSON_ERROR_NONE ) {
			$error_msg = \json_last_error_msg();
			LoggingHelper::warning( '[YousignValidator] JSON invalide', array(
				'error' => $error_msg,
				'content_preview' => \substr( $json_content, 0, 100 ),
			) );
			return new \WP_Error( 'YCFG_001', \__( 'Fichier JSON invalide', Constants::TEXT_DOMAIN ) );
		}

		if ( ! \is_array( $decoded ) ) {
			LoggingHelper::warning( '[YousignValidator] JSON doit être un objet/array', array(
				'type_received' => \gettype( $decoded ),
			) );
			return new \WP_Error( 'YCFG_001', \__( 'Fichier JSON invalide', Constants::TEXT_DOMAIN ) );
		}

		LoggingHelper::debug( '[YousignValidator] JSON validé', array(
			'fields_count' => count( $decoded ),
		) );

		return $decoded;
	}

	/**
	 * Valide les données de configuration complètes
	 *
	 * @param int    $form_id ID du formulaire Gravity Forms.
	 * @param string $template_id ID du template Yousign.
	 * @param array  $mapping Mapping des champs.
	 * @return true|\WP_Error True si valide, WP_Error sinon.
	 */
	public function validate_config_data( int $form_id, string $template_id, array $mapping ) {
		// Vérifier que le formulaire existe
		if ( \class_exists( 'GFAPI' ) ) {
			$form = \GFAPI::get_form( $form_id );
			if ( ! $form ) {
				return new \WP_Error( 'YCFG_003', \__( 'Formulaire Gravity Forms introuvable', Constants::TEXT_DOMAIN ) );
			}
		}

	// Valider le template ID
	if ( ! $this->validate_template_id( $template_id ) ) {
		return new \WP_Error( 'YCFG_002', \__( 'Template ID format invalide', Constants::TEXT_DOMAIN ) );
	}

	// Valider le mapping (doit être un array, peut être vide si pas de placeholders)
	if ( ! \is_array( $mapping ) ) {
		return new \WP_Error( 'YCFG_001', \__( 'Mapping invalide', Constants::TEXT_DOMAIN ) );
	}

	// Hook de validation personnalisée
		$custom_validation = \apply_filters( 'wcqf_yousign_config_validation', true, $form_id, $template_id, $mapping );
		if ( is_wp_error( $custom_validation ) ) {
			return $custom_validation;
		}

		LoggingHelper::debug( '[YousignValidator] Validation complète réussie', array(
			'form_id' => $form_id,
			'template_id' => $template_id,
			'mapping_fields' => count( $mapping ),
		) );

		return true;
	}

	/**
	 * Sanitize les données de configuration
	 *
	 * @param array $raw_data Données brutes.
	 * @return array Données sanitizées.
	 */
	public function sanitize_config_data( array $raw_data ): array {
		$sanitized = array();

		// Sanitize form_id
		if ( isset( $raw_data['form_id'] ) ) {
			$sanitized['form_id'] = \absint( $raw_data['form_id'] );
		}

		// Sanitize template_id
		if ( isset( $raw_data['template_id'] ) ) {
			$sanitized['template_id'] = \sanitize_text_field( $raw_data['template_id'] );
		}

		// Sanitize mapping (array de strings)
		if ( isset( $raw_data['mapping'] ) && \is_array( $raw_data['mapping'] ) ) {
			$sanitized['mapping'] = array();
			foreach ( $raw_data['mapping'] as $key => $value ) {
				$sanitized['mapping'][ \sanitize_text_field( $key ) ] = \sanitize_text_field( $value );
			}
		}

		LoggingHelper::debug( '[YousignValidator] Données sanitizées', array(
			'original_keys' => \array_keys( $raw_data ),
			'sanitized_keys' => \array_keys( $sanitized ),
		) );

		return $sanitized;
	}
}

