<?php
/**
 * AdminManager - Orchestration du module Admin
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Form\FormManager;
use WcQualiopiFormation\Admin\Logs\LogsActionHandler;
use WcQualiopiFormation\Admin\Logs\LogsDataProvider;
use WcQualiopiFormation\Admin\Logs\LogsFilterManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminManager
 * Orchestration de l'interface d'administration
 */
class AdminManager {

	/**
	 * Instance du Form Manager
	 *
	 * @var FormManager
	 */
	private $form_manager;

/**
	 * Instance de la page de configuration
	 *
	 * @var SettingsPage
	 */
	private $settings_page;


	/**
	 * Instance du gestionnaire AJAX
	 *
	 * @var AjaxHandler
	 */
	private $ajax_handler;

	/**
	 * Constructeur
	 *
	 * @param FormManager $form_manager Instance du Form Manager.
	 */
	public function __construct( FormManager $form_manager ) {
		$this->form_manager = $form_manager;

		// Initialiser les composants admin.
		$this->settings_page = new SettingsPage( $this->form_manager );
		$this->ajax_handler  = new AjaxHandler( $this->form_manager );
	}

	/**
	 * Initialise les hooks de l'administration
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		// [AJOUT 2025-10-07] Hook admin_init pour traiter les actions d'export AVANT tout rendering
		add_action( 'admin_init', array( $this, 'handle_early_actions' ) );
		add_action( 'init', array( $this, 'handle_export_actions' ), 1 );

		// Initialiser le gestionnaire AJAX.
		$this->ajax_handler->init_hooks();
	}

	/**
	 * Ajoute le menu dans l'administration WordPress
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// [CORRECTION 2025-10-07] Alignement sur Constants::CAP_MANAGE_SETTINGS avec filtre pour compatibilité
		$capability  = apply_filters( 'wcqf_admin_menu_capability', Constants::CAP_MANAGE_SETTINGS );
		$parent_slug = 'options-general.php';

		// Page principale : Configuration unifiée.
		\add_submenu_page(
			$parent_slug,
			__( 'Qualiopi Formation', Constants::TEXT_DOMAIN ),
			__( 'Qualiopi Formation', Constants::TEXT_DOMAIN ),
			$capability,
			'wcqf-settings',
			array( $this->settings_page, 'render' )
		);
	}

	/**
	 * Enqueue les assets CSS/JS de l'administration
	 *
	 * @param string $hook Hook de la page courante.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Ne charger que sur notre page unifiée.
		$our_pages = array(
			'settings_page_wcqf-settings',
		);

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		// Enqueue CSS admin.
		wp_enqueue_style(
			'wcqf-admin',
			\plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/admin.css',
			array(),
			WCQF_VERSION
		);

		// Enqueue JS admin.
		\wp_enqueue_script(
			'wcqf-admin',
			\plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/admin.js',
			array( 'jquery' ),
			WCQF_VERSION,
			true
		);

		// Localiser le script.
		\wp_localize_script(
			'wcqf-admin',
			'wcqfAdmin',
			array(
				'ajax_url' => \admin_url( 'admin-ajax.php' ),
				'nonce'    => \wp_create_nonce( 'wcqf_admin_action' ),
				'messages' => array(
					'test_success'   => __( 'Connexion réussie !', Constants::TEXT_DOMAIN ),
					'test_error'     => __( 'Erreur de connexion', Constants::TEXT_DOMAIN ),
					'cache_cleared'  => __( 'Cache vidé avec succès', Constants::TEXT_DOMAIN ),
					'confirm_delete' => __( 'Êtes-vous sûr ?', Constants::TEXT_DOMAIN ),
				),
			)
		);
	}

	/**
	 * Gère les actions d'export (appelé très tôt pour éviter les problèmes de headers)
	 *
	 * @return void
	 */
	public function handle_export_actions() {
		// Vérifier si nous sommes sur la page des logs avec une action d'export
		if ( ! isset( $_GET['page'] ) || 'wcqf-settings' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'logs' !== $_GET['tab'] ) {
			return;
		}

		// Vérifier si une action d'export est demandée
		if ( ! isset( $_POST['wcqf_logs_action'] ) || 'export_logs' !== $_POST['wcqf_logs_action'] ) {
			return;
		}

		// [CORRECTION 2025-10-07] Vérification CSRF et capabilities
		if ( ! check_admin_referer( 'wcqf_admin_action', '_wpnonce' ) ) {
			LoggingHelper::warning( '[AdminManager] CSRF check failed for export action' );
			wp_die( esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN ) );
		}

		// [CORRECTION 2025-10-07] Vérifier capabilities avec filtre pour compatibilité
		$required_capability = apply_filters( 'wcqf_admin_export_capability', Constants::CAP_MANAGE_SETTINGS );
		if ( ! current_user_can( $required_capability ) ) {
			LoggingHelper::warning( '[AdminManager] Insufficient capabilities for export action', array(
				'user_id' => get_current_user_id(),
				'required_capability' => $required_capability,
			) );
			wp_die( esc_html__( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ) );
		}

		LoggingHelper::info( '[AdminManager] Action d\'export détectée, traitement immédiat' );

		// Initialiser les dépendances nécessaires
		$data_provider = new LogsDataProvider();
		$filter_manager = new LogsFilterManager();
		
		// Initialiser et traiter l'export immédiatement
		$logs_action_handler = new LogsActionHandler( $data_provider, $filter_manager );
		$logs_action_handler->handle_export_logs();
	}

	/**
	 * Gère les actions précoces (export, clear logs) AVANT tout rendering
	 * [AJOUT 2025-10-07] Appelé via hook admin_init pour intercepter les actions AVANT que WordPress ne commence à rendre
	 *
	 * @return void
	 */
	public function handle_early_actions() {
		// [DEBUG 2025-10-07] Log pour comprendre pourquoi l'export ne fonctionne pas
		LoggingHelper::debug( '[AdminManager] handle_early_actions appelé', array(
			'GET_page' => $_GET['page'] ?? 'non défini',
			'GET_tab' => $_GET['tab'] ?? 'non défini',
			'POST_wcqf_logs_action' => $_POST['wcqf_logs_action'] ?? 'non défini',
			'POST_keys' => array_keys( $_POST ),
		) );

		// Vérifier si nous sommes sur la page des logs
		if ( ! isset( $_GET['page'] ) || 'wcqf-settings' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'logs' !== $_GET['tab'] ) {
			return;
		}

		// Vérifier si une action est demandée (sauf export qui est géré par handle_export_actions)
		if ( ! isset( $_POST['wcqf_logs_action'] ) || 'export_logs' === $_POST['wcqf_logs_action'] ) {
			return;
		}

		// [CORRECTION 2025-10-07] Vérification CSRF et capabilities
		if ( ! check_admin_referer( 'wcqf_admin_action', '_wpnonce' ) ) {
			LoggingHelper::warning( '[AdminManager] CSRF check failed for early action' );
			wp_die( esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN ) );
		}

		// [CORRECTION 2025-10-07] Vérifier capabilities avec filtre pour compatibilité
		$required_capability = apply_filters( 'wcqf_admin_early_action_capability', Constants::CAP_MANAGE_SETTINGS );
		if ( ! current_user_can( $required_capability ) ) {
			LoggingHelper::warning( '[AdminManager] Insufficient capabilities for early action', array(
				'user_id' => get_current_user_id(),
				'required_capability' => $required_capability,
			) );
			wp_die( esc_html__( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ) );
		}

		LoggingHelper::info( '[AdminManager] Action logs détectée, initialisation LogsTabRenderer', array(
			'action' => $_POST['wcqf_logs_action'],
		) );

		// Initialiser les composants nécessaires pour traiter l'action
		$logs_tab_renderer = new LogsTabRenderer();
		
		// Traiter l'action (clear logs uniquement)
		$logs_tab_renderer->init();
	}
}




