<?php
/**
 * YousignIframeHandler - Gestionnaire d'iframe Yousign pour signature électronique
 *
 * RESPONSABILITÉ UNIQUE : Créer une procédure Yousign lors de la transition page 3→4
 * et injecter l'iframe de signature dans un champ HTML dédié.
 *
 * @package WcQualiopiFormation\Form\GravityForms
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\YousignConfigManager;
use WcQualiopiFormation\Helpers\ApiKeyManager;
use WcQualiopiFormation\Security\SessionManager;

/**
 * Class YousignIframeHandler
 *
 * Gère l'intégration Yousign pour signature électronique dans Gravity Forms :
 * - Écoute la transition page 3→4 (hook wcqf_page_transition)
 * - Crée une procédure de signature via API Yousign v3
 * - Stocke les IDs de procédure en session (TTL 1h)
 * - Injecte l'iframe de signature dans le champ HTML ID 34 (hook gform_field_content)
 */
class YousignIframeHandler {

	/**
	 * Page source (avant signature)
	 */
	private const SOURCE_PAGE = 3;

	/**
	 * Page cible (avec iframe signature)
	 */
	private const TARGET_PAGE = 4;

	/**
	 * ID du champ HTML où injecter l'iframe
	 */
	private const IFRAME_FIELD_ID = 34;

	/**
	 * TTL session pour les données procédure (1 heure)
	 */
	private const SESSION_TTL = 3600;

	/**
	 * Timeout API Yousign (30 secondes)
	 */
	private const API_TIMEOUT = 30;

	/**
	 * Instance YousignConfigManager
	 *
	 * @var YousignConfigManager
	 */
	private $config_manager;

	/**
	 * Instance ApiKeyManager
	 *
	 * @var ApiKeyManager
	 */
	private $api_key_manager;

	/**
	 * Constructeur
	 *
	 * @param YousignConfigManager $config_manager Gestionnaire de config Yousign.
	 * @param ApiKeyManager        $api_key_manager Gestionnaire de clés API.
	 */
	public function __construct( YousignConfigManager $config_manager, ApiKeyManager $api_key_manager ) {
		$this->config_manager  = $config_manager;
		$this->api_key_manager = $api_key_manager;

		LoggingHelper::info( '[YousignIframe] Handler initialized', array(
			'source_page' => self::SOURCE_PAGE,
			'target_page' => self::TARGET_PAGE,
			'iframe_field' => self::IFRAME_FIELD_ID,
		) );
	}

	/**
	 * Initialise les hooks WordPress
	 */
	public function init_hooks() {
		add_action( 'wcqf_page_transition', array( $this, 'handle_yousign_transition' ), 10, 1 );
		add_filter( 'gform_field_content', array( $this, 'inject_yousign_iframe' ), 10, 5 );

		LoggingHelper::debug( '[YousignIframe] Hooks registered' );
	}

	/**
	 * Gère la transition page 3→4 pour créer la procédure Yousign
	 *
	 * @param array $transition_data Données de transition du PageTransitionManager.
	 */
	public function handle_yousign_transition( array $transition_data ) {
		// Filtrer : uniquement transition 3→4 forward
		if ( $transition_data['from_page'] !== self::SOURCE_PAGE
			|| $transition_data['to_page'] !== self::TARGET_PAGE
			|| $transition_data['direction'] !== 'forward' ) {
			return;
		}

		LoggingHelper::info( '[YousignIframe] Transition detected', array(
			'form_id' => $transition_data['form_id'],
			'from' => $transition_data['from_page'],
			'to' => $transition_data['to_page'],
		) );

		// Vérifier si procédure déjà créée (idempotence)
		$session_key = $this->get_session_key( $transition_data['form_id'] );
		if ( SessionManager::has( $session_key ) ) {
			$existing_data = SessionManager::get( $session_key );
			LoggingHelper::debug( '[YousignIframe] SR already exists', array(
				'sr_id' => $existing_data['sr_id'] ?? null,
				'has_signature_link' => ! empty( $existing_data['signature_link'] ),
			) );
			return;
		}

		// Récupérer config Yousign pour ce formulaire
		$config = $this->config_manager->get_config( $transition_data['form_id'] );
		if ( ! $config ) {
			LoggingHelper::warning( '[YousignIframe] Config missing', array(
				'form_id' => $transition_data['form_id'],
			) );
			return;
		}

		LoggingHelper::debug( '[YousignIframe] Config retrieved', array(
			'template_id' => $config['template_id'],
			'mapping_count' => count( $config['mapping'] ),
		) );

		// Créer la procédure Yousign
		$this->create_yousign_procedure( $transition_data, $config );
	}

	/**
	 * Crée une procédure de signature via API Yousign v3
	 * Workflow v3 : 1) Créer SR, 2) Activer SR
	 *
	 * @param array $transition_data Données de transition.
	 * @param array $config Configuration Yousign.
	 */
	private function create_yousign_procedure( array $transition_data, array $config ) {
		// Vérifier que les données de soumission existent
		if ( ! isset( $transition_data['submission_data'] ) ) {
			LoggingHelper::error( '[YousignIframe] Missing submission data', array(
				'form_id' => $transition_data['form_id'],
				'available_keys' => array_keys( $transition_data ),
			) );
			return;
		}

		// Extraire et valider les données utilisateur
		$user_data = $this->extract_user_data( $transition_data['submission_data'], $config['mapping'] );
		if ( ! $user_data ) {
			return;
		}

		// Récupérer la clé API Yousign
		$api_key = $this->api_key_manager->get_api_key( 'yousign' );
		if ( empty( $api_key ) ) {
			$this->log_error( 'YIF_005', 'API key missing', $transition_data['form_id'] );
			return;
		}

		// Construire le payload API
		$payload = $this->build_api_payload( $user_data, $config );

		// Étape 1 : Créer la Signature Request (draft)
		$sr_response = $this->create_signature_request( $payload );
		if ( ! $sr_response ) {
			return;
		}

		$sr_id = $sr_response['id'];

		// Étape 2 : Activer la Signature Request
		$activated_response = $this->activate_signature_request( $sr_id );
		if ( ! $activated_response ) {
			return;
		}

		// Stocker les données avec signature_link
		$this->store_procedure_data( $transition_data['form_id'], $activated_response, $config );
	}

	/**
	 * Extrait et valide les données utilisateur depuis la soumission
	 *
	 * @param array $submission_data Données de soumission GF.
	 * @param array $mapping Mapping des champs.
	 * @return array|false Données validées ou false si erreur.
	 */
	private function extract_user_data( array $submission_data, array $mapping ) {
		// Valider que le mapping contient les clés requises
		$required_keys = array( 'first_name', 'last_name', 'email' );
		$missing_keys = array();

		foreach ( $required_keys as $key ) {
			if ( ! isset( $mapping[ $key ] ) ) {
				$missing_keys[] = $key;
			}
		}

		if ( ! empty( $missing_keys ) ) {
			LoggingHelper::error( '[YousignIframe] Invalid mapping', array(
				'missing_keys' => $missing_keys,
				'available_keys' => array_keys( $mapping ),
			) );
			return false;
		}

		// Extraire les field IDs depuis le mapping
		$first_name_field = $mapping['first_name'];
		$last_name_field  = $mapping['last_name'];
		$email_field      = $mapping['email'];

		// Extraire les valeurs depuis submission_data
		$first_name = $submission_data[ $first_name_field ] ?? '';
		$last_name  = $submission_data[ $last_name_field ] ?? '';
		$email      = $submission_data[ $email_field ] ?? '';

		// Log des valeurs brutes AVANT sanitization
		LoggingHelper::debug( '[YousignIframe] Raw values extracted', array(
			'first_name_field' => $first_name_field,
			'first_name_value' => substr( $first_name, 0, 50 ),
			'first_name_type' => gettype( $first_name ),
			'last_name_field' => $last_name_field,
			'last_name_value' => substr( $last_name, 0, 50 ),
			'last_name_type' => gettype( $last_name ),
			'email_field' => $email_field,
			'email_value' => $email,
			'email_type' => gettype( $email ),
			'available_fields' => array_keys( $submission_data ),
		) );

		// Sanitization
		$first_name = sanitize_text_field( $first_name );
		$last_name  = sanitize_text_field( $last_name );
		$email      = sanitize_email( $email );

		// Validation
		if ( empty( $first_name ) || empty( $last_name ) ) {
			LoggingHelper::error( '[YousignIframe] Invalid data', array(
				'field' => 'name',
				'validation' => 'empty_name',
				'first_name_field' => $first_name_field,
				'last_name_field' => $last_name_field,
			) );
			return false;
		}

		if ( ! is_email( $email ) ) {
			LoggingHelper::error( '[YousignIframe] Invalid data', array(
				'field' => 'email',
				'validation' => 'is_email_failed',
				'email_field' => $email_field,
				'email_value' => substr( $email, 0, 20 ) . '...',
			) );
			return false;
		}

		LoggingHelper::debug( '[YousignIframe] User data extracted', array(
			'first_name_field' => $first_name_field,
			'last_name_field' => $last_name_field,
			'email_field' => $email_field,
			'has_first_name' => ! empty( $first_name ),
			'has_last_name' => ! empty( $last_name ),
			'has_email' => ! empty( $email ),
		) );

		return array(
			'firstName' => $first_name,
			'lastName'  => $last_name,
			'email'     => $email,
		);
	}

	/**
	 * Construit le payload pour l'API Yousign v3 (mode Template)
	 * API v3 : utilise template_id + template_placeholders, pas de members/documents
	 *
	 * @param array $user_data Données utilisateur validées.
	 * @param array $config Configuration Yousign.
	 * @return array Payload JSON.
	 */
	private function build_api_payload( array $user_data, array $config ) {
		$payload = array(
			'name'          => __( 'Contrat de formation TB-Formation', Constants::TEXT_DOMAIN ),
			'delivery_mode' => 'none', // iframe mode
			'timezone'      => 'Europe/Paris',
		);

		// Custom Experience (ex-signature_ui_id en v2)
		if ( ! empty( $config['custom_experience_id'] ) ) {
			$payload['custom_experience_id'] = $config['custom_experience_id'];
		}

		// Mode Template : template_id + placeholders pour les signataires
		if ( ! empty( $config['template_id'] ) ) {
			$payload['template_id'] = $config['template_id'];

			// Remplacement du Placeholder Signer (label EXACT du template)
			// Le label DOIT matcher le placeholder signataire dans le template (case-sensitive!)
			$payload['template_placeholders'] = array(
				'signers' => array(
					array(
						'label'                          => $config['template_signer_label'] ?? 'client',
						'signature_level'                => 'electronic_signature', // Niveau de signature requis
						'signature_authentication_mode'  => 'no_otp', // Mode d'authentification (no_otp ou otp_sms)
						'info'                           => array(
							'first_name' => $user_data['firstName'],
							'last_name'  => $user_data['lastName'],
							'email'      => $user_data['email'],
							'locale'     => 'fr',
						),
					),
				),
				// Champs texte en lecture seule pour affichage dans le PDF
				'read_only_text_fields' => array(
					array( 'label' => 'first_name', 'text' => $user_data['firstName'] ),
					array( 'label' => 'last_name',  'text' => $user_data['lastName'] ),
					array( 'label' => 'email',      'text' => $user_data['email'] ),
				),
			);
		}

		return $payload;
	}

	/**
	 * Crée une Signature Request via API Yousign v3 (étape 1)
	 *
	 * @param array $payload Payload JSON pour la création.
	 * @return array|false Réponse API ou false si erreur.
	 */
	private function create_signature_request( array $payload ) {
		$api_key  = $this->api_key_manager->get_api_key( 'yousign' );
		$endpoint = $this->get_api_endpoint(); // Utilise la méthode centralisée

		// Log de la requête
		LoggingHelper::debug( '[YousignIframe] Creating SR', array(
			'endpoint'     => $endpoint,
			'payload_keys' => array_keys( $payload ),
		) );

		$response = wp_remote_post( $endpoint, array(
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			LoggingHelper::critical( '[YousignIframe] SR creation failed', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code !== 201 ) {
			LoggingHelper::critical( '[YousignIframe] SR creation error', array(
				'http_code'     => $http_code,
				'response_body' => substr( $body, 0, 500 ),
			) );
			return false;
		}

		LoggingHelper::info( '[YousignIframe] SR created', array(
			'sr_id'  => $data['id'],
			'status' => $data['status'] ?? 'unknown',
		) );

		return $data;
	}

	/**
	 * Active une Signature Request via API Yousign v3 (étape 2)
	 *
	 * @param string $sr_id ID de la Signature Request.
	 * @return array|false Réponse API ou false si erreur.
	 */
	private function activate_signature_request( $sr_id ) {
		$api_key  = $this->api_key_manager->get_api_key( 'yousign' );
		$endpoint = $this->get_base_api_url() . "/signature_requests/{$sr_id}/activate";

		// Log de l'activation
		LoggingHelper::debug( '[YousignIframe] Activating SR', array(
			'sr_id'    => $sr_id,
			'endpoint' => $endpoint,
		) );

		$response = wp_remote_post( $endpoint, array(
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
		) );

		if ( is_wp_error( $response ) ) {
			LoggingHelper::critical( '[YousignIframe] SR activation failed', array(
				'sr_id' => $sr_id,
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code !== 200 && $http_code !== 201 ) {
			LoggingHelper::critical( '[YousignIframe] SR activation error', array(
				'sr_id'         => $sr_id,
				'http_code'     => $http_code,
				'response_body' => substr( $body, 0, 500 ),
			) );
			return false;
		}

		LoggingHelper::info( '[YousignIframe] SR activated', array(
			'sr_id'          => $sr_id,
			'status'         => $data['status'] ?? 'unknown',
			'has_signers'    => ! empty( $data['signers'] ),
			'signers_count'  => isset( $data['signers'] ) ? count( $data['signers'] ) : 0,
		) );

		return $data;
	}

	/**
	 * Stocke les données de procédure en session
	 * API v3 : stocke signature_link depuis response.signers[0]
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $api_response Réponse API Yousign (après activation).
	 * @param array $config Configuration Yousign.
	 */
	private function store_procedure_data( $form_id, $api_response, $config ) {
		// Récupérer le signature_link du premier signer
		$signature_link = '';
		if ( ! empty( $api_response['signers'][0]['signature_link'] ) ) {
			$signature_link = $api_response['signers'][0]['signature_link'];
		}

		$session_key  = $this->get_session_key( $form_id );
		$session_data = array(
			'sr_id'          => $api_response['id'],
			'signature_link' => $signature_link,
			'timestamp'      => time(),
		);

		SessionManager::set( $session_key, $session_data, self::SESSION_TTL );

		LoggingHelper::debug( '[YousignIframe] Session stored', array(
			'form_id'        => $form_id,
			'sr_id'          => $api_response['id'],
			'has_signature_link' => ! empty( $signature_link ),
			'ttl'            => self::SESSION_TTL,
		) );
	}

	/**
	 * Injecte l'iframe Yousign dans le champ HTML
	 *
	 * @param string $field_content Contenu du champ.
	 * @param object $field Objet field GF.
	 * @param mixed  $value Valeur du champ.
	 * @param int    $entry_id ID de l'entrée.
	 * @param int    $form_id ID du formulaire.
	 * @return string Contenu modifié.
	 */
	public function inject_yousign_iframe( $field_content, $field, $value, $entry_id, $form_id ) {
		// Filtrer : uniquement champ HTML ID 34
		if ( $field->id !== self::IFRAME_FIELD_ID || $field->type !== 'html' ) {
			return $field_content;
		}

		// Récupérer les données de procédure depuis la session
		$session_key = $this->get_session_key( $form_id );
		$proc_data   = SessionManager::get( $session_key );

		if ( ! $proc_data || empty( $proc_data['signature_link'] ) ) {
			LoggingHelper::debug( '[YousignIframe] Session empty or no signature_link', array(
				'form_id'  => $form_id,
				'field_id' => $field->id,
				'has_proc_data' => ! empty( $proc_data ),
			) );
			return $this->get_loading_message();
		}

		LoggingHelper::debug( '[YousignIframe] Iframe injected', array(
			'field_id'   => $field->id,
			'sr_id'      => $proc_data['sr_id'] ?? 'unknown',
		) );

		return $this->render_iframe( $proc_data['signature_link'] );
	}

	/**
	 * Rend le HTML de l'iframe avec le SDK Yousign v3
	 * Utilise le signature_link fourni par l'API après activation
	 *
	 * @param string $signature_link Lien de signature Yousign.
	 * @return string HTML avec SDK Yousign.
	 */
	private function render_iframe( $signature_link ) {
		// Échapper le signature_link pour JavaScript
		$signature_link_json = wp_json_encode( $signature_link, JSON_HEX_TAG | JSON_HEX_AMP );

		return sprintf(
			'<div id="yousign_iframe" style="width:100%%;height:720px"></div>
			<script src="https://cdn.yousign.tech/iframe-sdk-1.6.0.min.js" integrity="sha384-/7MD1voOOzWVz7FmgeMwmmd1DO85Mo0PkkxdYd9j2wDGzGDGRG/phgnL0c9Xyy52" crossorigin="anonymous"></script>
			<script>
			  (function(){
			    const yousign = new Yousign({
			      signatureLink: %s,
			      iframeContainerId: "yousign_iframe",
			      isSandbox: true
			    });
			  })();
			</script>',
			$signature_link_json
		);
	}

	/**
	 * Retourne le message de chargement
	 *
	 * @return string HTML du message.
	 */
	private function get_loading_message() {
		return '<p>' . esc_html__( 'Document en cours de préparation...', Constants::TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Récupère l'URL de base de l'API Yousign v3
	 *
	 * @return string URL de base (sans endpoint spécifique).
	 */
	private function get_base_api_url() {
		// TEMPORAIRE : Forcer sandbox pour développement
		// TODO : Récupérer depuis config Yousign (champ environment)
		// API v3 utilise le domaine .app (PAS .com qui est v2)
		return 'https://api-sandbox.yousign.app/v3';
	}

	/**
	 * Récupère l'endpoint complet pour créer une SR
	 *
	 * @return string URL complète.
	 */
	private function get_api_endpoint() {
		return $this->get_base_api_url() . '/signature_requests';
	}

	/**
	 * Récupère l'URL de base de l'iframe selon l'environnement
	 *
	 * @return string URL de base.
	 */
	private function get_iframe_base_url() {
		// TEMPORAIRE : Forcer sandbox pour développement
		// TODO : Récupérer depuis config Yousign (champ environment)
		return 'https://staging-app.yousign.app';
	}

	/**
	 * Récupère la clé de session pour un formulaire
	 *
	 * @param int $form_id ID du formulaire.
	 * @return string Clé de session.
	 */
	private function get_session_key( $form_id ) {
		return 'yousign_procedure_' . $form_id;
	}

	/**
	 * Masque une URL pour les logs (sécurité)
	 *
	 * @param string $url URL complète.
	 * @return string URL masquée.
	 */
	private function mask_url( $url ) {
		$parsed = wp_parse_url( $url );
		return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . '?...';
	}

	/**
	 * Log une erreur avec message utilisateur
	 *
	 * @param string $code Code d'erreur.
	 * @param string $message Message d'erreur.
	 * @param int    $form_id ID du formulaire.
	 */
	private function log_error( $code, $message, $form_id ) {
		LoggingHelper::critical( '[YousignIframe] Error', array(
			'form_id' => $form_id,
			'code'    => $code,
			'message' => $message,
		) );
	}

	/**
	 * Log une erreur API Yousign
	 *
	 * @param int   $http_code Code HTTP.
	 * @param array $data Réponse API.
	 * @param int   $form_id ID du formulaire.
	 */
	private function log_api_error( $http_code, $data, $form_id ) {
		LoggingHelper::critical( '[YousignIframe] API error', array(
			'form_id'   => $form_id,
			'http_code' => $http_code,
			'error'     => $data['error'] ?? 'unknown',
			'message'   => $data['message'] ?? 'unknown',
		) );
	}
}

