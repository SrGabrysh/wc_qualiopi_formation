<?php
/**
 * SettingsSaver - Gestionnaire de sauvegarde des paramètres
 *
 * @package WcQualiopiFormation\Admin\Settings
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
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
	 */
	public function __construct() {
	}

	/**
	 * Traite la sauvegarde des paramètres
	 * [CORRECTION 2025-10-14] Correction race condition clés API
	 *
	 * @return void
	 */
	public function maybe_save_settings() {
		// Déclencher sur toute soumission POST avec nonce valide
		// Plus robuste que de dépendre du bouton submit
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			return;
		}

		// Si le nonce n'est pas présent, ne pas traiter (soumission non liée à nos réglages)
		if ( empty( $_POST['wcqf_settings_nonce'] ) ) {
			return;
		}

		LoggingHelper::debug( '[SettingsSaver] Début traitement sauvegarde', array(
			'post_keys' => array_keys( $_POST ),
			'has_wcqf_settings' => isset( $_POST['wcqf_settings'] ),
			'wcqf_settings_keys' => isset( $_POST['wcqf_settings'] ) ? array_keys( $_POST['wcqf_settings'] ) : 'N/A',
		) );

		// Vérifier le nonce
		$nonce_field = \sanitize_text_field( \wp_unslash( $_POST['wcqf_settings_nonce'] ?? '' ) );
		$nonce_valid = \wp_verify_nonce( $nonce_field, 'wcqf_save_settings' );
		
		if ( ! $nonce_valid ) {
			LoggingHelper::error( '[SettingsSaver] Nonce invalide', array(
				'nonce_provided' => ! empty( $nonce_field ),
			) );
			
			\add_settings_error(
				'wcqf_settings',
				'nonce_error',
				\esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN )
			);
			return;
		}

		LoggingHelper::debug( '[SettingsSaver] Nonce validé avec succès' );

		// Récupérer et valider les données
		$raw_settings = $_POST['wcqf_settings'] ?? array();
		$new_settings = $this->validate_and_sanitize_settings( $raw_settings );

		LoggingHelper::debug( '[SettingsSaver] Settings validés et sanitizés', array(
			'new_settings_keys' => array_keys( $new_settings ),
		) );

		// [CORRECTION 2025-10-14] Récupérer les settings APRÈS la sauvegarde des clés API
		// pour éviter d'écraser les clés fraîchement sauvegardées par ApiKeyManager
		$existing_settings = \get_option( Constants::OPTION_SETTINGS, array() );

		LoggingHelper::debug( '[SettingsSaver] Settings existants récupérés (après clés API)', array(
			'existing_keys' => array_keys( $existing_settings ),
			'api_keys_count' => isset( $existing_settings['api_keys'] ) ? count( $existing_settings['api_keys'] ) : 0,
		) );

		// Fusionner avec les settings existants (préserver ce qui n'est pas dans le formulaire)
		// IMPORTANT: api_keys a déjà été mis à jour par ApiKeyManager dans validate_and_sanitize_settings()
		// donc $existing_settings contient déjà les nouvelles clés
		$settings = array_merge( $existing_settings, $new_settings );

		// Sauvegarder
		$result = \update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result || ! empty( $new_settings ) ) {
			LoggingHelper::info( '[SettingsSaver] Paramètres sauvegardés avec succès', array(
				'settings_keys' => array_keys( $settings ),
				'api_keys_count' => isset( $settings['api_keys'] ) ? count( $settings['api_keys'] ) : 0,
				'form_mappings_count' => count( $settings['form_mappings'] ?? array() ),
				'update_result' => $result ? 'updated' : 'unchanged',
			) );
			
			\add_settings_error(
				'wcqf_settings',
				'settings_saved',
				\esc_html__( 'Paramètres sauvegardés avec succès.', Constants::TEXT_DOMAIN ),
				'success'
			);
		} else {
			LoggingHelper::error( '[SettingsSaver] Échec sauvegarde paramètres' );
			
			\add_settings_error(
				'wcqf_settings',
				'settings_error',
				\esc_html__( 'Erreur lors de la sauvegarde des paramètres.', Constants::TEXT_DOMAIN )
			);
		}
	}

	/**
	 * Valide et nettoie les paramètres
	 * [MODIFICATION 2025-10-14] Traitement amélioré des clés API avec logs détaillés
	 *
	 * @param array $raw_settings Paramètres bruts.
	 * @return array Paramètres validés et nettoyés.
	 */
	private function validate_and_sanitize_settings( $raw_settings ) {
		$settings = array();

		LoggingHelper::debug( '[SettingsSaver] Début validation settings', array(
			'raw_settings_keys' => array_keys( $raw_settings ),
		) );

		// [MODIFICATION 2025-10-14] Traiter les clés API via ApiKeyManager
		$api_key_manager = ApiKeyManager::get_instance();
		$providers = $api_key_manager->get_all_providers();

		LoggingHelper::debug( '[SettingsSaver] Providers API disponibles', array(
			'providers' => array_keys( $providers ),
			'providers_count' => count( $providers ),
		) );

		$api_keys_processed = 0;
		$api_keys_saved = 0;
		$api_keys_skipped = 0;

		foreach ( $providers as $provider_id => $provider_data ) {
			$field_name = 'api_key_' . $provider_id;
			
			if ( isset( $raw_settings[ $field_name ] ) ) {
				$api_keys_processed++;
				$api_key = \trim( \sanitize_text_field( $raw_settings[ $field_name ] ) );
				
				LoggingHelper::debug( '[SettingsSaver] Traitement clé API', array(
					'provider' => $provider_id,
					'field_name' => $field_name,
					'key_length' => strlen( $api_key ),
					'is_placeholder' => $api_key === '********',
				) );
				
				// Si clé est le placeholder "********", ignorer (clé existante non modifiée)
				if ( $api_key === '********' ) {
					$api_keys_skipped++;
					LoggingHelper::debug( '[SettingsSaver] Clé API inchangée (placeholder)', array(
						'provider' => $provider_id,
					) );
					continue;
				}
				
				// Si clé non vide, la sauvegarder via ApiKeyManager (chiffrée)
				if ( ! empty( $api_key ) ) {
					$save_result = $api_key_manager->set_api_key( $provider_id, $api_key );
					
					if ( $save_result ) {
						$api_keys_saved++;
						LoggingHelper::info( '[SettingsSaver] Clé API sauvegardée avec succès', array(
							'provider' => $provider_id,
							'provider_name' => $provider_data['name'],
						) );
					} else {
						LoggingHelper::error( '[SettingsSaver] Échec sauvegarde clé API', array(
							'provider' => $provider_id,
						) );
					}
				} else {
					LoggingHelper::debug( '[SettingsSaver] Clé API vide, ignorée', array(
						'provider' => $provider_id,
					) );
				}
			}
		}

		LoggingHelper::info( '[SettingsSaver] Traitement clés API terminé', array(
			'processed' => $api_keys_processed,
			'saved' => $api_keys_saved,
			'skipped' => $api_keys_skipped,
		) );

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
				LoggingHelper::info( '[SettingsSaver] Réinitialisation mapping', array( 'form_id' => $form_id ) );
				$sanitized[ $form_id ] = self::DEFAULT_MAPPING;
				continue;
			}

			// Sanitizer chaque champ du mapping
			$sanitized[ $form_id ] = array();

			foreach ( self::DEFAULT_MAPPING as $field_key => $default_value ) {
				if ( isset( $mapping[ $field_key ] ) ) {
					// Sanitizer la valeur (format : "1" ou "8.3" ou vide)
					$value = \sanitize_text_field( $mapping[ $field_key ] );
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
