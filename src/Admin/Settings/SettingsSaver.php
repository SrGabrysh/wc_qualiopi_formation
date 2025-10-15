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
use WcQualiopiFormation\Helpers\YousignConfigManager;

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

	// Vérifier si une action Yousign a déjà ajouté un message de succès
	$existing_errors = \get_settings_errors( 'wcqf_settings' );
	$yousign_action_processed = false;
	foreach ( $existing_errors as $error ) {
		if ( in_array( $error['code'], array( 'yousign_saved', 'yousign_deleted' ) ) ) {
			$yousign_action_processed = true;
			LoggingHelper::info( '[SettingsSaver] Action Yousign déjà traitée, skip sauvegarde settings', array(
				'action_code' => $error['code'],
			) );
			break;
		}
	}

	// Si une action Yousign a été traitée, ne pas continuer (elle a déjà géré la sauvegarde et les messages)
	if ( $yousign_action_processed ) {
		return;
	}

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

		// Configurations Yousign
		$this->process_yousign_configs( $raw_settings );

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

	/**
	 * Traite les configurations Yousign (ajout, modification, suppression)
	 *
	 * @param array $raw_settings Settings bruts du formulaire.
	 * @return void
	 */
	private function process_yousign_configs( array $raw_settings ) {
		$yousign_manager = new YousignConfigManager();

		LoggingHelper::debug( '[SettingsSaver] Traitement configs Yousign', array(
			'has_delete_btn' => isset( $_POST['wcqf_delete_yousign_config'] ),
			'has_add_btn' => isset( $_POST['wcqf_add_yousign_config'] ),
			'has_yousign_new' => isset( $raw_settings['yousign_new'] ),
			'has_yousign_configs' => isset( $raw_settings['yousign_configs'] ),
		) );

		// Traiter la suppression d'une configuration
		if ( isset( $_POST['wcqf_delete_yousign_config'] ) ) {
			$form_id = \absint( $_POST['wcqf_delete_yousign_config'] );
			$result = $yousign_manager->delete_config( $form_id );

			if ( $result ) {
				\add_settings_error(
					'wcqf_settings',
					'yousign_deleted',
					\__( 'Configuration Yousign supprimée avec succès.', Constants::TEXT_DOMAIN ),
					'success'
				);
			}
			return;
		}

		// Traiter l'ajout d'une nouvelle configuration
		// Déclencher si yousign_new contient des données (peu importe le bouton)
		if ( ! empty( $raw_settings['yousign_new'] ) ) {
			LoggingHelper::debug( '[SettingsSaver] Branche ajout Yousign déclenchée', array(
				'yousign_new_data' => $raw_settings['yousign_new'],
			) );
			$this->add_yousign_config( $raw_settings['yousign_new'], $yousign_manager );
			return;
		}

		// Traiter les modifications de configurations existantes
		if ( isset( $raw_settings['yousign_configs'] ) && \is_array( $raw_settings['yousign_configs'] ) ) {
			LoggingHelper::debug( '[SettingsSaver] Branche mise à jour Yousign déclenchée', array(
				'configs_count' => count( $raw_settings['yousign_configs'] ),
			) );
			$this->update_yousign_configs( $raw_settings['yousign_configs'], $yousign_manager );
		} else {
			LoggingHelper::debug( '[SettingsSaver] Aucune action Yousign déclenchée', array(
				'has_yousign_new' => isset( $raw_settings['yousign_new'] ),
				'has_yousign_configs' => isset( $raw_settings['yousign_configs'] ),
			) );
		}
	}

	/**
	 * Ajoute une nouvelle configuration Yousign
	 *
	 * @param array                $new_config Données de la nouvelle config.
	 * @param YousignConfigManager $manager Instance du manager.
	 * @return void
	 */
	private function add_yousign_config( array $new_config, YousignConfigManager $manager ) {
		// LOG: Voir les données AVANT sanitization
		LoggingHelper::debug( '[SettingsSaver] AVANT sanitization', array(
			'new_config_keys' => array_keys( $new_config ),
			'new_config_values' => $new_config,
		) );

		// Sanitizer les données
		$sanitized = $manager->sanitize_config_data( $new_config );

		// LOG: Voir les données APRÈS sanitization
		LoggingHelper::debug( '[SettingsSaver] APRÈS sanitization', array(
			'sanitized_keys' => array_keys( $sanitized ),
			'sanitized_values' => $sanitized,
			'form_id_empty' => empty( $sanitized['form_id'] ),
			'template_id_empty' => empty( $sanitized['template_id'] ),
		) );

		// Vérifier que les champs requis sont présents
		if ( empty( $sanitized['form_id'] ) || empty( $sanitized['template_id'] ) ) {
			\add_settings_error(
				'wcqf_settings',
				'yousign_missing_fields',
				\__( 'Formulaire et Template ID sont requis.', Constants::TEXT_DOMAIN ),
				'error'
			);
			return;
		}

		// Traiter l'upload du fichier JSON
		$mapping = $this->handle_json_upload();
		if ( is_wp_error( $mapping ) ) {
			\add_settings_error(
				'wcqf_settings',
				'yousign_json_error',
				$mapping->get_error_message(),
				'error'
			);
			return;
		}

		// Sauvegarder la configuration
		$result = $manager->save_config(
			$sanitized['form_id'],
			$sanitized['template_id'],
			$mapping
		);

		if ( is_wp_error( $result ) ) {
			\add_settings_error(
				'wcqf_settings',
				'yousign_save_error',
				$result->get_error_message(),
				'error'
			);
		} else {
			\add_settings_error(
				'wcqf_settings',
				'yousign_saved',
				\__( 'Configuration Yousign ajoutée avec succès.', Constants::TEXT_DOMAIN ),
				'success'
			);
		}
	}

	/**
	 * Met à jour les configurations Yousign existantes
	 *
	 * @param array                $configs Configurations à mettre à jour.
	 * @param YousignConfigManager $manager Instance du manager.
	 * @return void
	 */
	private function update_yousign_configs( array $configs, YousignConfigManager $manager ) {
		$updated_count = 0;

		foreach ( $configs as $form_id => $config ) {
			$sanitized = $manager->sanitize_config_data( $config );

			if ( empty( $sanitized['form_id'] ) || empty( $sanitized['template_id'] ) ) {
				continue;
			}

			// Conserver le mapping existant si non modifié
			$existing_config = $manager->get_config( (int) $form_id );
			$mapping = $existing_config['mapping'] ?? array();

			// Sauvegarder la configuration mise à jour
			$result = $manager->save_config(
				$sanitized['form_id'],
				$sanitized['template_id'],
				$mapping
			);

			if ( ! is_wp_error( $result ) ) {
				$updated_count++;
			}
		}

		if ( $updated_count > 0 ) {
			LoggingHelper::info( '[SettingsSaver] Configs Yousign mises à jour', array(
				'updated_count' => $updated_count,
			) );
		}
	}

	/**
	 * Gère l'upload du fichier JSON mapping
	 *
	 * @return array|\WP_Error Array mapping si succès (ou vide si pas de fichier), WP_Error si erreur.
	 */
	private function handle_json_upload() {
		// Si aucun fichier uploadé, retourner un tableau vide (mapping optionnel)
		if ( empty( $_FILES['yousign_mapping_file']['name'] ) ) {
			LoggingHelper::debug( '[SettingsSaver] Aucun fichier JSON uploadé - mapping vide' );
			return array(); // Mapping optionnel
		}

		$file = $_FILES['yousign_mapping_file'];

		// Vérifier la taille du fichier (max 1MB)
		if ( $file['size'] > 1048576 ) {
			LoggingHelper::warning( '[SettingsSaver] Fichier JSON trop volumineux', array(
				'size' => $file['size'],
				'max_size' => 1048576,
			) );
			return new \WP_Error( 'YCFG_005', \__( 'Fichier trop volumineux (max 1MB).', Constants::TEXT_DOMAIN ) );
		}

		// Vérifier l'extension
		$file_ext = \pathinfo( $file['name'], PATHINFO_EXTENSION );
		if ( \strtolower( $file_ext ) !== 'json' ) {
			return new \WP_Error( 'YCFG_001', \__( 'Le fichier doit être au format JSON.', Constants::TEXT_DOMAIN ) );
		}

		// Lire le contenu du fichier
		$json_content = \file_get_contents( $file['tmp_name'] );

		// Valider le JSON
		$yousign_manager = new YousignConfigManager();
		$mapping = $yousign_manager->validate_json_mapping( $json_content );

		if ( is_wp_error( $mapping ) ) {
			return $mapping;
		}

		LoggingHelper::info( '[SettingsSaver] JSON mapping validé', array(
			'file_name' => $file['name'],
			'fields_count' => count( $mapping ),
		) );

		return $mapping;
	}
}
