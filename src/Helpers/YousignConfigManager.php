<?php
/**
 * YousignConfigManager - Gestionnaire de configurations Yousign
 *
 * RESPONSABILITÉ UNIQUE : Gestion des configurations d'intégration Yousign
 * (validation, sauvegarde, récupération des mappings formulaires-templates)
 *
 * @package WcQualiopiFormation\Helpers
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\YousignValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YousignConfigManager
 * 
 * Gère les configurations d'intégration entre Gravity Forms et Yousign
 */
class YousignConfigManager {

	/**
	 * Instance du validator
	 *
	 * @var YousignValidator
	 */
	private $validator;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->validator = new YousignValidator();
	}

	/**
	 * Sauvegarde une configuration Yousign
	 *
	 * @param int    $form_id ID du formulaire Gravity Forms.
	 * @param string $template_id ID du template Yousign (UUID).
	 * @param array  $mapping Mapping des champs JSON.
	 * @return bool|\WP_Error True si succès, WP_Error sinon.
	 */
	public function save_config( int $form_id, string $template_id, array $mapping ) {
		// Valider les entrées via YousignValidator
		$validation = $this->validator->validate_config_data( $form_id, $template_id, $mapping );
		if ( is_wp_error( $validation ) ) {
			LoggingHelper::warning( '[YousignConfig] Validation échouée', array(
				'form_id' => $form_id,
				'error_code' => $validation->get_error_code(),
				'error_message' => $validation->get_error_message(),
			) );
			return $validation;
		}

		// Récupérer les settings existants
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		// Initialiser la structure si nécessaire
		if ( ! isset( $settings['yousign_configs'] ) ) {
			$settings['yousign_configs'] = array();
		}

		// Sauvegarder la configuration (clé unique = form_id)
		$settings['yousign_configs'][ $form_id ] = array(
			'template_id' => $template_id,
			'mapping' => $mapping,
		);

		// Persister en base
		$result = \update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result ) {
			LoggingHelper::info( '[YousignConfig] Config sauvegardée', array(
				'form_id' => $form_id,
				'template_id' => $template_id,
				'mapping_fields_count' => count( $mapping ),
			) );

			// Hook après sauvegarde
			\do_action( 'wcqf_yousign_config_saved', $form_id, $template_id, $mapping );

			return true;
		} else {
			LoggingHelper::error( '[YousignConfig] Échec sauvegarde', array(
				'form_id' => $form_id,
				'reason' => 'database_error',
			) );
			return new \WP_Error( 'YCFG_004', \__( 'Échec sauvegarde configuration', Constants::TEXT_DOMAIN ) );
		}
	}

	/**
	 * Récupère une configuration Yousign pour un formulaire
	 *
	 * @param int $form_id ID du formulaire Gravity Forms.
	 * @return array|null Configuration ou null si inexistante.
	 */
	public function get_config( int $form_id ): ?array {
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		if ( isset( $settings['yousign_configs'][ $form_id ] ) ) {
			LoggingHelper::debug( '[YousignConfig] Config récupérée', array(
				'form_id' => $form_id,
			) );
			return $settings['yousign_configs'][ $form_id ];
		}

		LoggingHelper::debug( '[YousignConfig] Config non trouvée', array(
			'form_id' => $form_id,
		) );
		return null;
	}

	/**
	 * Récupère toutes les configurations Yousign
	 *
	 * @return array Liste des configurations indexées par form_id.
	 */
	public function get_all_configs(): array {
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		$configs = $settings['yousign_configs'] ?? array();

		LoggingHelper::debug( '[YousignConfig] Toutes configs récupérées', array(
			'configs_count' => count( $configs ),
		) );

		return $configs;
	}

	/**
	 * Supprime une configuration Yousign
	 *
	 * @param int $form_id ID du formulaire Gravity Forms.
	 * @return bool True si succès, false sinon.
	 */
	public function delete_config( int $form_id ): bool {
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		if ( isset( $settings['yousign_configs'][ $form_id ] ) ) {
			unset( $settings['yousign_configs'][ $form_id ] );
			$result = \update_option( Constants::OPTION_SETTINGS, $settings );

			if ( $result ) {
				LoggingHelper::info( '[YousignConfig] Config supprimée', array(
					'form_id' => $form_id,
				) );
				return true;
			}
		}

		LoggingHelper::warning( '[YousignConfig] Échec suppression config', array(
			'form_id' => $form_id,
		) );
		return false;
	}

	/**
	 * Valide un template ID Yousign (UUID v4)
	 * Délègue au YousignValidator
	 *
	 * @param string $template_id ID du template.
	 * @return bool True si valide, false sinon.
	 */
	public function validate_template_id( string $template_id ): bool {
		return $this->validator->validate_template_id( $template_id );
	}

	/**
	 * Valide et decode le contenu JSON d'un fichier mapping
	 * Délègue au YousignValidator
	 *
	 * @param string $json_content Contenu JSON brut.
	 * @return array|\WP_Error Array décodé si valide, WP_Error sinon.
	 */
	public function validate_json_mapping( string $json_content ) {
		return $this->validator->validate_json_mapping( $json_content );
	}

	/**
	 * Sanitize les données de configuration
	 * Délègue au YousignValidator
	 *
	 * @param array $raw_data Données brutes.
	 * @return array Données sanitizées.
	 */
	public function sanitize_config_data( array $raw_data ): array {
		return $this->validator->sanitize_config_data( $raw_data );
	}
}

