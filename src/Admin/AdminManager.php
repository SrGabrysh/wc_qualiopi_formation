<?php
/**
 * AdminManager - Orchestration du module Admin
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\FormManager;

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
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance de la page de configuration
	 *
	 * @var SettingsPage
	 */
	private $settings_page;

	/**
	 * Instance du visualiseur de logs
	 *
	 * @var LogsViewer
	 */
	private $logs_viewer;

	/**
	 * Instance du gestionnaire AJAX
	 *
	 * @var AjaxHandler
	 */
	private $ajax_handler;

	/**
	 * Constructeur
	 *
	 * @param Logger      $logger Instance du logger.
	 * @param FormManager $form_manager Instance du Form Manager.
	 */
	public function __construct( Logger $logger, FormManager $form_manager ) {
		$this->logger       = $logger;
		$this->form_manager = $form_manager;

		// Initialiser les composants admin.
		$this->settings_page = new SettingsPage( $this->form_manager, $this->logger );
		$this->logs_viewer   = new LogsViewer( $this->logger );
		$this->ajax_handler  = new AjaxHandler( $this->form_manager, $this->logger );
	}

	/**
	 * Initialise les hooks de l'administration
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Initialiser le gestionnaire AJAX.
		$this->ajax_handler->init_hooks();
	}

	/**
	 * Ajoute le menu dans l'administration WordPress
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		$capability  = 'manage_options';
		$parent_slug = 'options-general.php';

		// Page principale : Configuration.
		add_submenu_page(
			$parent_slug,
			__( 'Qualiopi Formation', Constants::TEXT_DOMAIN ),
			__( 'Qualiopi Formation', Constants::TEXT_DOMAIN ),
			$capability,
			'wcqf-settings',
			array( $this->settings_page, 'render' )
		);

		// Sous-page : Logs.
		add_submenu_page(
			$parent_slug,
			__( 'Logs - Qualiopi Formation', Constants::TEXT_DOMAIN ),
			__( 'Logs Qualiopi', Constants::TEXT_DOMAIN ),
			$capability,
			'wcqf-logs',
			array( $this->logs_viewer, 'render' )
		);
	}

	/**
	 * Enqueue les assets CSS/JS de l'administration
	 *
	 * @param string $hook Hook de la page courante.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Ne charger que sur nos pages.
		$our_pages = array(
			'settings_page_wcqf-settings',
			'settings_page_wcqf-logs',
		);

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		// Enqueue CSS admin.
		wp_enqueue_style(
			'wcqf-admin',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/admin.css',
			array(),
			Constants::VERSION
		);

		// Enqueue JS admin.
		wp_enqueue_script(
			'wcqf-admin',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/admin.js',
			array( 'jquery' ),
			Constants::VERSION,
			true
		);

		// Localiser le script.
		wp_localize_script(
			'wcqf-admin',
			'wcqfAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcqf_admin_action' ),
				'messages' => array(
					'test_success'   => __( 'Connexion réussie !', Constants::TEXT_DOMAIN ),
					'test_error'     => __( 'Erreur de connexion', Constants::TEXT_DOMAIN ),
					'cache_cleared'  => __( 'Cache vidé avec succès', Constants::TEXT_DOMAIN ),
					'confirm_delete' => __( 'Êtes-vous sûr ?', Constants::TEXT_DOMAIN ),
				),
			)
		);
	}
}




