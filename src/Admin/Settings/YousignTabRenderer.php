<?php
/**
 * YousignTabRenderer - Rendu de l'onglet Configuration Yousign
 *
 * RESPONSABILITÉ UNIQUE : Affichage de l'interface de configuration
 * des intégrations Yousign (mapping formulaires-templates)
 *
 * @package WcQualiopiFormation\Admin\Settings
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\YousignConfigManager;
use WcQualiopiFormation\Admin\AdminUi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YousignTabRenderer
 * Gère l'affichage de l'onglet Configuration Yousign
 */
class YousignTabRenderer {

	/**
	 * Instance du YousignConfigManager
	 *
	 * @var YousignConfigManager
	 */
	private $config_manager;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->config_manager = new YousignConfigManager();
	}

	/**
	 * Affiche le contenu de l'onglet Yousign
	 *
	 * @return void
	 */
	public function render() {
		// Vérifier que Gravity Forms est actif
		if ( ! \class_exists( 'GFAPI' ) ) {
			echo AdminUi::notice(
				\__( 'Gravity Forms doit être actif pour utiliser cette fonctionnalité.', Constants::TEXT_DOMAIN ),
				'warning'
			);
			LoggingHelper::warning( '[YousignTab] Gravity Forms inactif' );
			return;
		}

		// Récupérer les configurations existantes
		$configs = $this->config_manager->get_all_configs();

		// Récupérer la liste des formulaires Gravity Forms
		$forms = $this->get_gravity_forms_list();

		LoggingHelper::debug( '[YousignTab] Rendering tab', array(
			'configs_count' => count( $configs ),
			'forms_count' => count( $forms ),
		) );

		// Afficher l'introduction
		echo AdminUi::section_start( \__( 'Configuration Yousign', Constants::TEXT_DOMAIN ) );
		echo '<p class="wcqf-help">' . \esc_html__( 'Configurez l\'association entre les formulaires Gravity Forms et les templates de signature Yousign. Chaque formulaire peut être lié à un template Yousign avec un mapping JSON des champs.', Constants::TEXT_DOMAIN ) . '</p>';

		// Vérifier s'il y a des formulaires disponibles
		if ( empty( $forms ) ) {
			echo AdminUi::notice(
				\__( 'Aucun formulaire Gravity Forms disponible.', Constants::TEXT_DOMAIN ),
				'info'
			);
			echo AdminUi::section_end();
			return;
		}

		// Afficher le tableau de configuration
		$this->render_config_table( $configs, $forms );

		echo AdminUi::section_end();
	}

	/**
	 * Affiche le tableau de configuration
	 *
	 * @param array $configs Configurations existantes.
	 * @param array $forms Liste des formulaires Gravity Forms.
	 * @return void
	 */
	private function render_config_table( array $configs, array $forms ) {
		// En-têtes du tableau
		$headers = array(
			\__( 'Formulaire', Constants::TEXT_DOMAIN ),
			\__( 'ID Template Yousign', Constants::TEXT_DOMAIN ),
			\__( 'Mapping JSON', Constants::TEXT_DOMAIN ),
			\__( 'Actions', Constants::TEXT_DOMAIN ),
		);

		echo AdminUi::table_start( $headers );

		// Afficher les configurations existantes
		if ( ! empty( $configs ) ) {
			foreach ( $configs as $form_id => $config ) {
				$this->render_config_row( $form_id, $config, $forms );
			}
		}

		// Ligne pour ajouter une nouvelle configuration
		$this->render_new_config_row( $forms );

		echo AdminUi::table_end();
	}

	/**
	 * Affiche une ligne de configuration existante
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $config Configuration existante.
	 * @param array $forms Liste des formulaires disponibles.
	 * @return void
	 */
	private function render_config_row( int $form_id, array $config, array $forms ) {
		$form_name = $this->get_form_name( $form_id, $forms );
		$template_id = $config['template_id'] ?? '';
		$mapping_preview = $this->get_mapping_preview( $config['mapping'] ?? array() );

		// Dropdown formulaire
		$form_select = AdminUi::select(
			'wcqf_settings[yousign_configs][' . $form_id . '][form_id]',
			$forms,
			$form_id,
			array( 'class' => 'wcqf-select' )
		);

		// Input template ID
		$template_input = '<input type="text" 
			name="wcqf_settings[yousign_configs][' . \esc_attr( $form_id ) . '][template_id]" 
			value="' . \esc_attr( $template_id ) . '" 
			placeholder="' . \esc_attr__( 'UUID du template Yousign', Constants::TEXT_DOMAIN ) . '"
			class="regular-text" />';

		// Hidden input pour le mapping (sera géré par upload)
		$mapping_input = '<input type="hidden" 
			name="wcqf_settings[yousign_configs][' . \esc_attr( $form_id ) . '][mapping]" 
			value="' . \esc_attr( \wp_json_encode( $config['mapping'] ?? array() ) ) . '" />
			<small>' . \esc_html( $mapping_preview ) . '</small>';

		// Bouton supprimer
		$delete_btn = '<button type="submit" 
			name="wcqf_delete_yousign_config" 
			value="' . \esc_attr( $form_id ) . '" 
			class="wcqf-btn wcqf-btn--secondary">' 
			. \esc_html__( 'Supprimer', Constants::TEXT_DOMAIN ) . 
		'</button>';

		echo AdminUi::table_row( array(
			$form_select,
			$template_input,
			$mapping_input,
			$delete_btn,
		) );
	}

	/**
	 * Affiche la ligne pour ajouter une nouvelle configuration
	 *
	 * @param array $forms Liste des formulaires disponibles.
	 * @return void
	 */
	private function render_new_config_row( array $forms ) {
		// Dropdown formulaire
		// Utiliser + au lieu de array_merge pour préserver les clés numériques
		$select_options = array( '' => \__( '-- Sélectionner un formulaire --', Constants::TEXT_DOMAIN ) ) + $forms;

		LoggingHelper::debug( '[YousignTab] Select options for new config', array(
			'options_keys' => array_keys( $select_options ),
			'options' => $select_options,
		) );

		$form_select = AdminUi::select(
			'wcqf_settings[yousign_new][form_id]',
			$select_options,
			'',
			array( 'class' => 'wcqf-select' )
		);

		// Input template ID
		$template_input = '<input type="text" 
			name="wcqf_settings[yousign_new][template_id]" 
			placeholder="' . \esc_attr__( 'UUID du template Yousign', Constants::TEXT_DOMAIN ) . '"
			class="regular-text" />';

		// Upload JSON
		$json_upload = '<input type="file" 
			name="yousign_mapping_file" 
			accept=".json"
			class="wcqf-file-input" />
			<small>' . \esc_html__( 'Fichier JSON max 1MB', Constants::TEXT_DOMAIN ) . '</small>';

		// Bouton ajouter
		$add_btn = AdminUi::button(
			\__( 'Ajouter', Constants::TEXT_DOMAIN ),
			'primary',
			array( 'type' => 'submit', 'name' => 'wcqf_add_yousign_config' )
		);

		echo AdminUi::table_row( array(
			$form_select,
			$template_input,
			$json_upload,
			$add_btn,
		) );
	}

	/**
	 * Récupère la liste des formulaires Gravity Forms
	 *
	 * @return array Liste des formulaires [id => nom].
	 */
	private function get_gravity_forms_list(): array {
		if ( ! \class_exists( 'GFAPI' ) ) {
			return array();
		}

		$forms = \GFAPI::get_forms();
		$forms_list = array();

		foreach ( $forms as $form ) {
			$forms_list[ $form['id'] ] = $form['title'];
		}

		LoggingHelper::debug( '[YousignTab] Forms list retrieved', array(
			'count' => count( $forms_list ),
			'forms_ids' => array_keys( $forms_list ),
			'forms_list' => $forms_list,
		) );

		return $forms_list;
	}

	/**
	 * Récupère le nom d'un formulaire
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $forms Liste des formulaires.
	 * @return string Nom du formulaire.
	 */
	private function get_form_name( int $form_id, array $forms ): string {
		return $forms[ $form_id ] ?? \sprintf( \__( 'Formulaire #%d', Constants::TEXT_DOMAIN ), $form_id );
	}

	/**
	 * Génère un aperçu du mapping JSON
	 *
	 * @param array $mapping Mapping des champs.
	 * @return string Aperçu du mapping.
	 */
	private function get_mapping_preview( array $mapping ): string {
		if ( empty( $mapping ) ) {
			return \__( 'Aucun mapping', Constants::TEXT_DOMAIN );
		}

		$fields = \array_keys( $mapping );
		$count = count( $fields );

		if ( $count <= 3 ) {
			return \implode( ', ', $fields );
		}

		return \sprintf(
			\__( '%d champs : %s...', Constants::TEXT_DOMAIN ),
			$count,
			\implode( ', ', \array_slice( $fields, 0, 3 ) )
		);
	}
}

