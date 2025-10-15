<?php
/**
 * Orchestrateur principal du module Form
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Form\Siren\SirenAutocomplete;
use WcQualiopiFormation\Form\GravityForms\FieldInjector;
use WcQualiopiFormation\Form\GravityForms\SubmissionHandler;
use WcQualiopiFormation\Form\GravityForms\AjaxHandler;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Form\GravityForms\CalculationRetriever;
use WcQualiopiFormation\Form\GravityForms\PageTransitionManager;
use WcQualiopiFormation\Form\GravityForms\PageTransitionHandler;
use WcQualiopiFormation\Form\GravityForms\PositioningHelper;
use WcQualiopiFormation\Form\GravityForms\ResultsInjector;
use WcQualiopiFormation\Modules\Yousign\Handlers\YousignIframeHandler;
use WcQualiopiFormation\Modules\Yousign\Client\YousignClient;
use WcQualiopiFormation\Modules\Yousign\Payload\PayloadBuilder;
use WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator;
use WcQualiopiFormation\Form\Tracking\TrackingManager;
use WcQualiopiFormation\Form\Tracking\DataExtractor;
use WcQualiopiFormation\Data\Store\PositioningConfigStore;
use WcQualiopiFormation\Admin\ButtonReplacementManager;
use WcQualiopiFormation\Helpers\YousignConfigManager;
use WcQualiopiFormation\Helpers\ApiKeyManager;

/**
 * Classe d'orchestration du module Form
 *
 * Gère l'intégration avec Gravity Forms :
 * - Autocomplete SIRET via API SIREN officielle
 * - Pré-remplissage automatique des champs
 * - Génération mentions légales
 * - Injection token HMAC
 * - Tracking des soumissions
 */
class FormManager {

	/**
	 * Instance SirenAutocomplete
	 *
	 * @var SirenAutocomplete
	 */
	private $siren_autocomplete;

	/**
	 * Instance FieldInjector
	 *
	 * @var FieldInjector
	 */
	private $field_injector;

	/**
	 * Instance SubmissionHandler
	 *
	 * @var SubmissionHandler
	 */
	private $submission_handler;

	/**
	 * Instance AjaxHandler
	 *
	 * @var AjaxHandler
	 */
	private $ajax_handler;

	/**
	 * Instance MentionsGenerator
	 *
	 * @var MentionsGenerator
	 */
	private $mentions_generator;

	/**
	 * Instance TrackingManager
	 *
	 * @var TrackingManager
	 */
	private $tracking_manager;

	/**
	 * Instance FieldMapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Instance CalculationRetriever
	 *
	 * @var CalculationRetriever
	 */
	private $calculation_retriever;

	/**
	 * Instance PageTransitionManager
	 *
	 * @var PageTransitionManager
	 */
	private $page_transition_manager;

	/**
	 * Instance PageTransitionHandler
	 *
	 * @var PageTransitionHandler
	 */
	private $page_transition_handler;

	/**
	 * Instance ResultsInjector
	 *
	 * @var ResultsInjector
	 */
	private $results_injector;

	/**
	 * Instance YousignIframeHandler
	 *
	 * @var YousignIframeHandler
	 */
	private $yousign_iframe_handler;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		// Module Siren (API SIREN officielle + validation).
		$this->siren_autocomplete = new SirenAutocomplete();

		// Module Mentions Légales.
		$this->mentions_generator = new MentionsGenerator();

		// Module Gravity Forms - Mapping et récupération de valeurs.
		$this->field_mapper = new FieldMapper();
		$this->calculation_retriever = new CalculationRetriever( $this->field_mapper );

		// Module Gravity Forms - Gestion des transitions de pages.
		// Module optionnel : peut être désactivé via settings si nécessaire.
		if ( $this->is_page_transition_module_enabled() ) {
			// Manager global : détecte TOUTES les transitions et déclenche action WP.
			$this->page_transition_manager = new PageTransitionManager();
			// Handler spécialisé : écoute l'action et traite uniquement la transition 2→3.
			$this->page_transition_handler = new PageTransitionHandler( $this->calculation_retriever );
			
			LoggingHelper::info( '[FormManager] Module PageTransition activé' );
		} else {
			LoggingHelper::info( '[FormManager] Module PageTransition désactivé via settings' );
		}

		// Module Positioning - Test de positionnement.
		$positioning_config_store = new PositioningConfigStore();
		$positioning_helper       = new PositioningHelper( $positioning_config_store );
		$this->results_injector   = new ResultsInjector( $positioning_helper, $positioning_config_store );

		// Module Yousign - Signature électronique.
		$yousign_config_manager = new YousignConfigManager();
		$api_key_manager        = ApiKeyManager::get_instance();
		$yousign_client         = new YousignClient( $api_key_manager );
		$payload_builder        = new PayloadBuilder();
		$data_extractor         = new DataExtractor();
		
		$this->yousign_iframe_handler = new YousignIframeHandler(
			$yousign_config_manager,
			$yousign_client,
			$payload_builder,
			$data_extractor
		);

		// Module Gravity Forms - Injection, soumission, AJAX.
		$this->field_injector      = new FieldInjector();
		$this->submission_handler  = new SubmissionHandler();
		$this->ajax_handler        = new AjaxHandler( $this->siren_autocomplete, $this->mentions_generator );

		// Module Tracking.
		$this->tracking_manager = new TrackingManager();

		// Module Button Replacement - Remplacement du bouton "Suivant" pour utilisateurs 'refused'
		ButtonReplacementManager::init_hooks();

		LoggingHelper::info( '[FormManager] Form modules initialized' );
	}

	/**
	 * Initialise les hooks WordPress
	 * 
	 * Convention du plugin : Tous les handlers/managers ont leur init_hooks()
	 * appelé explicitement depuis FormManager pour un contrôle centralisé.
	 * Cela permet l'initialisation conditionnelle et rend le code plus prévisible.
	 */
	private function init_hooks() {
		// Vérifier que Gravity Forms est actif.
		if ( ! class_exists( 'GFForms' ) ) {
			\add_action( 'admin_notices', array( $this, 'show_gf_required_notice' ) );
			return;
		}

		// Hooks Gravity Forms - Ordre d'initialisation
		$this->field_injector->init_hooks();
		$this->submission_handler->init_hooks();
		$this->ajax_handler->init_hooks();

		// PageTransitionHandler - init_hooks() appelé explicitement (convention)
		if ( $this->page_transition_handler !== null ) {
			$this->page_transition_handler->init_hooks();
		}

		// YousignIframeHandler - init_hooks() appelé explicitement (convention)
		$this->yousign_iframe_handler->init_hooks();

		$this->results_injector->init_hooks();
		$this->tracking_manager->init_hooks();

		LoggingHelper::info( '[FormManager] Form hooks registered' );
	}

	/**
	 * Affiche une notice si Gravity Forms n'est pas actif
	 */
	public function show_gf_required_notice() {
		echo '<div class="notice notice-error"><p>';
		echo \esc_html__(
			'WC Qualiopi Formation (Module Form) requires Gravity Forms to be installed and active.',
			Constants::TEXT_DOMAIN
		);
		echo '</p></div>';
	}

	/**
	 * Vérifie si le module PageTransition est activé
	 *
	 * Par défaut activé, peut être désactivé via option WordPress.
	 * Utile si conflit avec autre plugin GF ou pour environnement de test.
	 *
	 * @since 1.1.0
	 * @return bool True si le module est activé, false sinon.
	 */
	private function is_page_transition_module_enabled(): bool {
		/**
		 * Filtre pour désactiver le module PageTransition
		 *
		 * @since 1.1.0
		 * @param bool $enabled True pour activer, false pour désactiver.
		 */
		$enabled = \apply_filters( 'wcqf_enable_page_transition_module', true );

		// Optionnel : Permettre désactivation via option admin
		$settings = \get_option( 'wcqf_settings', array() );
		if ( isset( $settings['enable_page_transition_module'] ) ) {
			$enabled = (bool) $settings['enable_page_transition_module'];
		}

		return $enabled;
	}

	/**
	 * Récupère l'instance SirenAutocomplete
	 *
	 * @return SirenAutocomplete
	 */
	public function get_siren_autocomplete() {
		return $this->siren_autocomplete;
	}

	/**
	 * Récupère l'instance MentionsGenerator
	 *
	 * @return MentionsGenerator
	 */
	public function get_mentions_generator() {
		return $this->mentions_generator;
	}

	/**
	 * Récupère l'instance TrackingManager
	 *
	 * @return TrackingManager
	 */
	public function get_tracking_manager() {
		return $this->tracking_manager;
	}

	/**
	 * Récupère l'instance CalculationRetriever
	 *
	 * @return CalculationRetriever
	 */
	public function get_calculation_retriever() {
		return $this->calculation_retriever;
	}

	/**
	 * Récupère l'instance FieldMapper
	 *
	 * @return FieldMapper
	 */
	public function get_field_mapper() {
		return $this->field_mapper;
	}

	/**
	 * Récupère l'instance PageTransitionManager
	 *
	 * @since 1.1.0
	 * @return PageTransitionManager|null Null si le module est désactivé.
	 */
	public function get_page_transition_manager() {
		return $this->page_transition_manager ?? null;
	}

	/**
	 * Récupère l'instance PageTransitionHandler
	 *
	 * @since 1.1.0
	 * @return PageTransitionHandler|null Null si le module est désactivé.
	 */
	public function get_page_transition_handler() {
		return $this->page_transition_handler ?? null;
	}
}

