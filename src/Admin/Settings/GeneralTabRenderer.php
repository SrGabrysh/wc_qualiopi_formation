<?php
/**
 * GeneralTabRenderer - Rendu de l'onglet Configuration
 *
 * @package WcQualiopiFormation\Admin\Settings
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Helpers\ConfigFieldBuilder;
use WcQualiopiFormation\Helpers\ApiKeyManager;
use WcQualiopiFormation\Admin\AdminUi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeneralTabRenderer
 * Gère l'affichage de l'onglet Configuration
 */
class GeneralTabRenderer {

/**
	 * Constructeur
	 */
	public function __construct() {
	}

	/**
	 * Affiche le contenu de l'onglet Configuration
	 * [MODIFICATION 2025-10-07] Utilisation de ConfigFieldBuilder et ApiKeyManager
	 *
	 * @return void
	 */
	public function render() {
		// Récupérer les settings
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		// Initialiser ApiKeyManager et ConfigFieldBuilder
		$api_key_manager = ApiKeyManager::get_instance();
		$field_builder = new ConfigFieldBuilder();

		// Section : Clés API
		$field_builder->add_section(
			'api_keys',
			\__( 'Clés API', Constants::TEXT_DOMAIN ),
			\__( 'Configurez les clés API pour les services externes (stockage sécurisé avec chiffrement AES-256).', Constants::TEXT_DOMAIN )
		);

		// Ajouter les champs pour chaque provider
		$providers = $api_key_manager->get_all_providers();
		foreach ( $providers as $provider_id => $provider_data ) {
			$current_key = $settings['api_keys'][ $provider_id ] ?? '';
			
			$field_builder->add_field(
				'api_keys',
				'api_key_' . $provider_id,
				'password',
				$provider_data['name'],
				array(
					'description' => sprintf(
						\__( 'Endpoint : %s', Constants::TEXT_DOMAIN ),
						$provider_data['endpoint']
					),
					'placeholder' => \__( 'Entrez votre clé API', Constants::TEXT_DOMAIN ),
					'class' => 'regular-text',
				)
			);
		}

		// Section : Options de suivi
		$field_builder->add_section(
			'tracking',
			\__( 'Options de suivi', Constants::TEXT_DOMAIN ),
			\__( 'Configurez le suivi des soumissions de formulaires Gravity Forms.', Constants::TEXT_DOMAIN )
		);

		$field_builder->add_field(
			'tracking',
			'enable_tracking',
			'checkbox',
			\__( 'Activer le suivi', Constants::TEXT_DOMAIN ),
			array(
				'checkbox_label' => \__( 'Activer le suivi des soumissions de formulaires', Constants::TEXT_DOMAIN ),
				'description' => \__( 'Permet de suivre les soumissions et validations SIREN.', Constants::TEXT_DOMAIN ),
			)
		);

		// Préparer les valeurs actuelles
		$values = array(
			'enable_tracking' => $settings['enable_tracking'] ?? 1,
		);

		// Ajouter les valeurs des clés API (déchiffrées pour affichage)
		foreach ( $providers as $provider_id => $provider_data ) {
			$api_key = $api_key_manager->get_api_key( $provider_id );
			$values[ 'api_key_' . $provider_id ] = $api_key ?? '';
		}

		// Rendre les sections
		?>
		<div class="wcqf-settings-section">
			<?php $field_builder->render_section( 'api_keys', $values ); ?>
		</div>

		<div class="wcqf-settings-section">
			<?php $field_builder->render_section( 'tracking', $values ); ?>
		</div>
		<?php

		LoggingHelper::debug( '[GeneralTabRenderer] Configuration rendue', array(
			'providers_count' => count( $providers ),
			'tracking_enabled' => $values['enable_tracking'],
		) );
	}
}
