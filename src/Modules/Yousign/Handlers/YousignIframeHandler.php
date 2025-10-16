<?php
/**
 * YousignIframeHandler - Orchestrateur de signature électronique Yousign
 *
 * RESPONSABILITÉ UNIQUE : Orchestrer le workflow de signature Yousign lors des transitions Gravity Forms
 *
 * @package WcQualiopiFormation\Modules\Yousign\Handlers
 * @since 1.2.1
 */

namespace WcQualiopiFormation\Modules\Yousign\Handlers;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\YousignConfigManager;
use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Modules\Yousign\Client\YousignClient;
use WcQualiopiFormation\Modules\Yousign\Payload\PayloadBuilder;
use WcQualiopiFormation\Modules\Yousign\Helpers\YousignDataCollector;
use WcQualiopiFormation\Form\Tracking\DataExtractor;
use WcQualiopiFormation\Data\ProgressTracker;

/**
 * Class YousignIframeHandler
 *
 * Orchestre l'intégration Yousign pour signature électronique dans Gravity Forms :
 * - Écoute la transition page 3→4 (hook wcqf_page_transition)
 * - Génère un convention_id unique basé sur la session WooCommerce
 * - Crée une procédure de signature via YousignClient
 * - Stocke les IDs de procédure en session (TTL 1h)
 * - Injecte l'iframe de signature dans le champ HTML ID 34
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
	 * Instance YousignConfigManager
	 *
	 * @var YousignConfigManager
	 */
	private $config_manager;

	/**
	 * Instance YousignClient
	 *
	 * @var YousignClient
	 */
	private $yousign_client;

	/**
	 * Instance PayloadBuilder
	 *
	 * @var PayloadBuilder
	 */
	private $payload_builder;

	/**
	 * Instance DataExtractor
	 *
	 * @var DataExtractor
	 */
	private $data_extractor;

	/**
	 * Constructeur
	 *
	 * @param YousignConfigManager $config_manager Gestionnaire de config Yousign.
	 * @param YousignClient        $yousign_client Client HTTP Yousign.
	 * @param PayloadBuilder       $payload_builder Constructeur de payloads.
	 * @param DataExtractor        $data_extractor Extracteur de données GF.
	 */
	public function __construct(
		YousignConfigManager $config_manager,
		YousignClient $yousign_client,
		PayloadBuilder $payload_builder,
		DataExtractor $data_extractor
	) {
		$this->config_manager  = $config_manager;
		$this->yousign_client  = $yousign_client;
		$this->payload_builder = $payload_builder;
		$this->data_extractor  = $data_extractor;

		LoggingHelper::info( '[YousignIframe] Handler initialized', array(
			'source_page'  => self::SOURCE_PAGE,
			'target_page'  => self::TARGET_PAGE,
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
			'from'    => $transition_data['from_page'],
			'to'      => $transition_data['to_page'],
		) );

		// Vérifier si procédure déjà créée (idempotence)
		$session_key = $this->get_session_key( $transition_data['form_id'] );
		if ( SessionManager::has( $session_key ) ) {
			$existing_data = SessionManager::get( $session_key );
			LoggingHelper::debug( '[YousignIframe] SR already exists', array(
				'sr_id'              => $existing_data['sr_id'] ?? null,
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
			'template_id'   => $config['template_id'],
			'mapping_count' => count( $config['mapping'] ),
		) );

		// Créer la procédure Yousign
		$this->create_yousign_procedure( $transition_data, $config );
	}

	/**
	 * Crée une procédure de signature via YousignClient
	 * Workflow v3 : 1) Créer SR, 2) Activer SR
	 *
	 * @param array $transition_data Données de transition.
	 * @param array $config Configuration Yousign.
	 */
	private function create_yousign_procedure( array $transition_data, array $config ) {
		// Vérifier que les données de soumission existent
		if ( ! isset( $transition_data['submission_data'] ) || ! isset( $transition_data['form'] ) ) {
			LoggingHelper::error( '[YousignIframe] Missing submission data or form', array(
				'form_id'        => $transition_data['form_id'],
				'available_keys' => array_keys( $transition_data ),
			) );
			return;
		}

		// Récupérer le token depuis la session WordPress
		$token = SessionManager::get( Constants::SESSION_KEY_TOKEN );
		if ( empty( $token ) ) {
			LoggingHelper::error( '[YousignIframe] No token in session', array(
				'form_id'         => $transition_data['form_id'],
				'session_checked' => Constants::SESSION_KEY_TOKEN,
			) );
			return;
		}

		// Collecter TOUTES les données nécessaires pour Yousign via DataCollector
		$enriched_data = YousignDataCollector::collect_all_data(
			$transition_data['submission_data'],
			$transition_data['form'],
			$token
		);

		if ( empty( $enriched_data ) ) {
			LoggingHelper::error( '[YousignIframe] Could not collect Yousign data', array(
				'form_id'       => $transition_data['form_id'],
				'token_preview' => substr( $token, 0, 10 ),
			) );
			return;
		}

		// Valider les données de base (obligatoires pour l'API)
		if ( empty( $enriched_data['first_name'] ) || empty( $enriched_data['last_name'] ) || ! is_email( $enriched_data['email'] ) ) {
			LoggingHelper::error( '[YousignIframe] Invalid base data after collection', array(
				'has_first_name' => ! empty( $enriched_data['first_name'] ),
				'has_last_name'  => ! empty( $enriched_data['last_name'] ),
				'has_email'      => ! empty( $enriched_data['email'] ),
			) );
			return;
		}

		// Adapter les données au format API Yousign
		$user_data = array(
			'firstName'            => $enriched_data['first_name'],
			'lastName'             => $enriched_data['last_name'],
			'email'                => $enriched_data['email'],
			'full_name'            => $enriched_data['full_name'] ?? '',
			'full_name_stagiaire'  => $enriched_data['full_name_stagiaire'] ?? '',
			'mentions_legales'     => $enriched_data['mentions_legales'] ?? '',
			'date_realisation'     => $enriched_data['date_realisation'] ?? '',
			'date_jour'            => $enriched_data['date_jour'] ?? '',
			'total_ht'             => $enriched_data['total_ht'] ?? '0,00 €',
			'total_ttc'            => $enriched_data['total_ttc'] ?? '0,00 €',
			'tva'                  => $enriched_data['tva'] ?? '0,00 €',
		);

		LoggingHelper::debug( '[YousignIframe] User data prepared for API', array(
			'has_first_name'       => ! empty( $user_data['firstName'] ),
			'has_last_name'        => ! empty( $user_data['lastName'] ),
			'has_email'            => ! empty( $user_data['email'] ),
			'has_full_name'        => ! empty( $user_data['full_name'] ),
			'has_mentions_legales' => ! empty( $user_data['mentions_legales'] ),
			'has_date_realisation' => ! empty( $user_data['date_realisation'] ),
			'has_total_ht'         => ! empty( $user_data['total_ht'] ),
			'has_total_ttc'        => ! empty( $user_data['total_ttc'] ),
			'has_tva'              => ! empty( $user_data['tva'] ),
		) );

		// Générer le convention_id
		$convention_id = $this->generate_and_store_convention_id( $token );

		if ( ! $convention_id ) {
			LoggingHelper::warning( '[YousignIframe] Could not generate convention_id, continuing without it', array(
				'token_preview' => substr( $token, 0, 10 ),
			) );
			// Non-bloquant : on continue sans convention_id (forcer string vide)
			$convention_id = '';
		}

		// Construire le payload via PayloadBuilder (avec convention_id)
		$payload = $this->payload_builder->build_signature_request_payload( $user_data, $config, $convention_id );
		if ( empty( $payload ) ) {
			LoggingHelper::error( '[YousignIframe] Payload build failed', array(
				'form_id' => $transition_data['form_id'],
			) );
			return;
		}

		// Étape 1 : Créer la Signature Request via YousignClient
		$sr_response = $this->yousign_client->create_signature_request( $payload );
		if ( ! $sr_response ) {
			return;
		}

		$sr_id = $sr_response['id'];

		// Étape 2 : Activer la Signature Request via YousignClient
		$activated_response = $this->yousign_client->activate_signature_request( $sr_id );
		if ( ! $activated_response ) {
			return;
		}

		// Stocker les données avec signature_link
		$this->store_procedure_data( $transition_data['form_id'], $activated_response );
	}

	/**
	 * Stocke les données de procédure en session
	 * API v3 : stocke signature_link depuis response.signers[0]
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $api_response Réponse API Yousign (après activation).
	 */
	private function store_procedure_data( $form_id, $api_response ) {
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
			'form_id'            => $form_id,
			'sr_id'              => $api_response['id'],
			'has_signature_link' => ! empty( $signature_link ),
			'ttl'                => self::SESSION_TTL,
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
				'form_id'       => $form_id,
				'field_id'      => $field->id,
				'has_proc_data' => ! empty( $proc_data ),
			) );
			return $this->get_loading_message();
		}

		LoggingHelper::debug( '[YousignIframe] Iframe injected', array(
			'field_id' => $field->id,
			'sr_id'    => $proc_data['sr_id'] ?? 'unknown',
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
			'<div id="yousign_iframe"></div>
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
	 * Récupère la clé de session pour un formulaire
	 *
	 * @param int $form_id ID du formulaire.
	 * @return string Clé de session.
	 */
	private function get_session_key( $form_id ) {
		return 'yousign_procedure_' . $form_id;
	}

	/**
	 * Génère le convention_id basé sur la session WooCommerce (GÉNÉRATION DIRECTE)
	 * 
	 * Format : {session_id}_{product_id}_{unix_timestamp}
	 * Fallback utilisateurs connectés : user_{user_id}_{product_id}_{unix_timestamp}
	 * 
	 * Note : Ne dépend PAS de la progression en BDD (données récupérées directement)
	 * 
	 * @param string $token Token HMAC (non utilisé, conservé pour compatibilité)
	 * @return string|null Convention ID généré ou null si erreur
	 */
	private function generate_and_store_convention_id( string $token ): ?string {
		LoggingHelper::debug( '[YousignIframe] Starting convention_id generation (direct)', array(
			'token_preview' => substr( $token, 0, 10 ),
		) );

		// 1. Récupérer session_id depuis WooCommerce (directement, pas depuis BDD)
		$session_id = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$session_id = WC()->session->get_customer_id();
		}

		// 2. Récupérer product_id depuis le panier (directement, pas depuis BDD)
		$product_id = $this->get_product_id_from_cart();

		// 3. Validation du product_id
		if ( empty( $product_id ) ) {
			LoggingHelper::error( '[YousignIframe] Missing product_id for convention_id', array(
				'cart_empty' => function_exists( 'WC' ) && WC()->cart ? WC()->cart->is_empty() : true,
			) );
			return null;
		}

		// 4. Fallback pour utilisateurs connectés (si session WC vide)
		if ( empty( $session_id ) ) {
			$user_id = get_current_user_id();
			if ( ! empty( $user_id ) ) {
				$session_id = 'user_' . $user_id;
				LoggingHelper::debug( '[YousignIframe] Using user_id fallback for convention_id', array(
					'user_id'     => $user_id,
					'fallback_id' => $session_id,
				) );
			} else {
				LoggingHelper::error( '[YousignIframe] No session_id or user_id available', array(
					'wc_session_exists' => function_exists( 'WC' ) && WC()->session,
					'user_logged_in'    => is_user_logged_in(),
				) );
				return null;
			}
		}

		// 5. Générer le convention_id
		$timestamp     = time();
		$convention_id = sprintf( '%s_%d_%d', $session_id, $product_id, $timestamp );

		// 6. Log de succès (pas de stockage en BDD car pas de progression)
		LoggingHelper::info( '[YousignIframe] Convention ID generated successfully', array(
			'convention_id'     => $convention_id,
			'product_id'        => $product_id,
			'session_id_preview' => substr( $session_id, 0, 15 ) . '...',
			'timestamp'         => $timestamp,
			'source'            => empty( WC()->session ) ? 'user_id' : 'wc_session',
		) );

		return $convention_id;
	}

	/**
	 * Récupère le product_id depuis le panier WooCommerce
	 *
	 * @return int Product ID ou 0 si panier vide
	 */
	private function get_product_id_from_cart(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$cart_items = WC()->cart->get_cart();
		if ( empty( $cart_items ) ) {
			return 0;
		}

		// Récupérer le premier produit du panier
		$first_item = reset( $cart_items );
		return $first_item['product_id'] ?? 0;
	}
}

