<?php
/**
 * SettingsSaver - Gestionnaire de sauvegarde des paramètres
 *
 * @package WcQualiopiFormation\Admin\Settings
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Helpers\ApiKeyManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsSaver
 * Gère la sauvegarde et la validation des paramètres
 */
class SettingsSaver {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Mapping par défaut (référence)
	 *
	 * @var array
	 */
	private const DEFAULT_MAPPING = array(
		'siret'            => '1',
		'denomination'     => '12',
		'adresse'          => '8.1',
		'code_postal'      => '8.5',
		'ville'            => '8.3',
		'code_ape'         => '10',
		'libelle_ape'      => '11',
		'date_creation'    => '14',
		'statut_actif'     => '15',
		'mentions_legales' => '13',
		'prenom'           => '7.3',
		'nom'              => '7.6',
	);

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Traite la sauvegarde des paramètres
	 *
	 * @return void
	 */
	public function maybe_save_settings() {
		if ( ! isset( $_POST['wcqf_save_settings'] ) ) {
			return;
		}

		// Vérifier le nonce
		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['wcqf_settings_nonce'] ?? '' ) ), 'wcqf_save_settings' ) ) {
			\add_settings_error(
				'wcqf_settings',
				'nonce_error',
				\esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN )
			);
			return;
		}

		// Récupérer les settings existants
		$existing_settings = \get_option( Constants::OPTION_SETTINGS, array() );

		// Récupérer et valider les données
		$new_settings = $this->validate_and_sanitize_settings( $_POST['wcqf_settings'] ?? array() );

		// Fusionner avec les settings existants (préserver ce qui n'est pas dans le formulaire)
		$settings = array_merge( $existing_settings, $new_settings );

		// Sauvegarder
		$result = \update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result || ! empty( $new_settings ) ) {
			\add_settings_error(
				'wcqf_settings',
				'settings_saved',
				\esc_html__( 'Paramètres sauvegardés avec succès.', Constants::TEXT_DOMAIN ),
				'success'
			);

			$this->logger->info( '[SettingsSaver] Paramètres sauvegardés', array(
				'settings_keys' => array_keys( $settings ),
				'form_mappings_count' => count( $settings['form_mappings'] ?? array() ),
			) );
		} else {
			\add_settings_error(
				'wcqf_settings',
				'settings_error',
				\esc_html__( 'Erreur lors de la sauvegarde des paramètres.', Constants::TEXT_DOMAIN )
			);
		}
	}

	/**
	 * Valide et nettoie les paramètres
	 * [MODIFICATION 2025-10-07] Traitement des clés API avec ApiKeyManager
	 *
	 * @param array $raw_settings Paramètres bruts.
	 * @return array Paramètres validés et nettoyés.
	 */
	private function validate_and_sanitize_settings( $raw_settings ) {
		$settings = array();

		// [AJOUT 2025-10-07] Traiter les clés API via ApiKeyManager
		$api_key_manager = ApiKeyManager::get_instance( $this->logger );
		$providers = $api_key_manager->get_all_providers();

		foreach ( $providers as $provider_id => $provider_data ) {
			$field_name = 'api_key_' . $provider_id;
			
			if ( isset( $raw_settings[ $field_name ] ) ) {
				$api_key = \trim( SanitizationHelper::sanitize_text_field( $raw_settings[ $field_name ] ) );
				
				// Si clé non vide, la sauvegarder via ApiKeyManager (chiffrée)
				if ( ! empty( $api_key ) ) {
					$api_key_manager->set_api_key( $provider_id, $api_key );
					$this->logger->info( '[SettingsSaver] Cle API sauvegardee', array(
						'provider' => $provider_id,
					) );
				}
			}
		}

		// Options de suivi (si présentes)
		if ( isset( $raw_settings['enable_tracking'] ) ) {
			$settings['enable_tracking'] = ! empty( $raw_settings['enable_tracking'] );
		}

		// Logging (si présent)
		if ( isset( $raw_settings['enable_logging'] ) ) {
			$settings['enable_logging'] = ! empty( $raw_settings['enable_logging'] );
		}

		// Token TTL (si présent)
		if ( isset( $raw_settings['token_ttl_hours'] ) ) {
			$settings['token_ttl_hours'] = (int) $raw_settings['token_ttl_hours'];
		}

		// Session TTL (si présent)
		if ( isset( $raw_settings['session_ttl_minutes'] ) ) {
			$settings['session_ttl_minutes'] = (int) $raw_settings['session_ttl_minutes'];
		}

		// Autofill (si présent)
		if ( isset( $raw_settings['enable_autofill'] ) ) {
			$settings['enable_autofill'] = ! empty( $raw_settings['enable_autofill'] );
		}

		// Compliance (si présent)
		if ( isset( $raw_settings['enable_compliance'] ) ) {
			$settings['enable_compliance'] = ! empty( $raw_settings['enable_compliance'] );
		}

		// Mapping des formulaires (structure : form_mappings[form_id][field_key])
		if ( isset( $raw_settings['form_mappings'] ) && is_array( $raw_settings['form_mappings'] ) ) {
			$settings['form_mappings'] = $this->sanitize_form_mappings( $raw_settings['form_mappings'] );
		}

		return $settings;
	}

	/**
	 * Sanitize les mappings de formulaires
	 *
	 * @param array $form_mappings Mappings bruts.
	 * @return array Mappings sanitizés.
	 */
	private function sanitize_form_mappings( $form_mappings ) {
		$sanitized = array();

		foreach ( $form_mappings as $form_id => $mapping ) {
			$form_id = (int) $form_id;

			if ( $form_id <= 0 ) {
				continue;
			}

			// Vérifier si réinitialisation demandée
			if ( isset( $mapping['_reset'] ) && $mapping['_reset'] === '1' ) {
				$this->logger->info( '[SettingsSaver] Réinitialisation mapping', array( 'form_id' => $form_id ) );
				$sanitized[ $form_id ] = self::DEFAULT_MAPPING;
				continue;
			}

			// Sanitizer chaque champ du mapping
			$sanitized[ $form_id ] = array();

			foreach ( self::DEFAULT_MAPPING as $field_key => $default_value ) {
				if ( isset( $mapping[ $field_key ] ) ) {
					// Sanitizer la valeur (format : "1" ou "8.3" ou vide)
					$value = SanitizationHelper::sanitize_text_field( $mapping[ $field_key ] );
					$sanitized[ $form_id ][ $field_key ] = $value;
				} else {
					// Utiliser la valeur par défaut si non fournie
					$sanitized[ $form_id ][ $field_key ] = $default_value;
				}
			}
		}

		return $sanitized;
	}
}
