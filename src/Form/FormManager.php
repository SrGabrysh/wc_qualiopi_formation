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
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\Siren\SirenAutocomplete;
use WcQualiopiFormation\Form\GravityForms\FieldInjector;
use WcQualiopiFormation\Form\GravityForms\SubmissionHandler;
use WcQualiopiFormation\Form\GravityForms\AjaxHandler;
use WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator;
use WcQualiopiFormation\Form\Tracking\TrackingManager;

/**
 * Classe d'orchestration du module Form
 *
 * Gère l'intégration avec Gravity Forms :
 * - Autocomplete SIRET via API Pappers
 * - Pré-remplissage automatique des champs
 * - Génération mentions légales
 * - Injection token HMAC
 * - Tracking des soumissions
 */
class FormManager {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

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
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;

		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Initialise les composants du module
	 */
	private function init_components() {
		// Module Siren (API Pappers + validation).
		$this->siren_autocomplete = new SirenAutocomplete( $this->logger );

		// Module Mentions Légales.
		$this->mentions_generator = new MentionsGenerator( $this->logger );

		// Module Gravity Forms.
		$this->field_injector      = new FieldInjector( $this->logger );
		$this->submission_handler  = new SubmissionHandler( $this->logger );
		$this->ajax_handler        = new AjaxHandler( $this->logger, $this->siren_autocomplete, $this->mentions_generator );

		// Module Tracking.
		$this->tracking_manager = new TrackingManager( $this->logger );

		$this->logger->info( 'Form modules initialized' );
	}

	/**
	 * Initialise les hooks WordPress
	 */
	private function init_hooks() {
		// Vérifier que Gravity Forms est actif.
		if ( ! class_exists( 'GFForms' ) ) {
			add_action( 'admin_notices', array( $this, 'show_gf_required_notice' ) );
			return;
		}

		// Hooks Gravity Forms.
		$this->field_injector->init_hooks();
		$this->submission_handler->init_hooks();
		$this->ajax_handler->init_hooks();
		$this->tracking_manager->init_hooks();

		$this->logger->info( 'Form hooks registered' );
	}

	/**
	 * Affiche une notice si Gravity Forms n'est pas actif
	 */
	public function show_gf_required_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'WC Qualiopi Formation (Module Form) requires Gravity Forms to be installed and active.',
			Constants::TEXT_DOMAIN
		);
		echo '</p></div>';
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
}

