<?php
/**
 * YousignClient - Client HTTP pour l'API Yousign v3
 *
 * RESPONSABILITÉ UNIQUE : Gérer toutes les communications HTTP avec l'API Yousign
 *
 * @package WcQualiopiFormation\Modules\Yousign\Client
 * @since 1.2.1
 */

namespace WcQualiopiFormation\Modules\Yousign\Client;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\ApiKeyManager;

/**
 * Class YousignClient
 *
 * Client HTTP pour interagir avec l'API Yousign v3.
 * Gère le workflow de signature électronique :
 * 1. Créer une Signature Request (draft)
 * 2. Activer la Signature Request
 */
class YousignClient {

	/**
	 * Timeout API Yousign (30 secondes)
	 */
	private const API_TIMEOUT = 30;

	/**
	 * Instance ApiKeyManager
	 *
	 * @var ApiKeyManager
	 */
	private $api_key_manager;

	/**
	 * Constructeur
	 *
	 * @param ApiKeyManager $api_key_manager Gestionnaire de clés API.
	 */
	public function __construct( ApiKeyManager $api_key_manager ) {
		$this->api_key_manager = $api_key_manager;

		LoggingHelper::debug( '[YousignClient] Client initialized' );
	}

	/**
	 * Crée une Signature Request via API Yousign v3 (étape 1)
	 *
	 * @param array $payload Payload JSON pour la création.
	 * @return array|false Réponse API ou false si erreur.
	 */
	public function create_signature_request( array $payload ) {
		$api_key  = $this->api_key_manager->get_api_key( 'yousign' );
		
		if ( empty( $api_key ) ) {
			LoggingHelper::critical( '[YousignClient] API key missing' );
			return false;
		}

		$endpoint = $this->get_api_endpoint();

		// Log de la requête
		LoggingHelper::debug( '[YousignClient] Creating SR', array(
			'endpoint'     => $endpoint,
			'payload_keys' => array_keys( $payload ),
		) );

		$response = \wp_remote_post( $endpoint, array(
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => \wp_json_encode( $payload ),
		) );

		if ( \is_wp_error( $response ) ) {
			LoggingHelper::critical( '[YousignClient] SR creation failed', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$http_code = \wp_remote_retrieve_response_code( $response );
		$body      = \wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( 201 !== $http_code ) {
			LoggingHelper::critical( '[YousignClient] SR creation error', array(
				'http_code'     => $http_code,
				'response_body' => substr( $body, 0, 500 ),
			) );
			return false;
		}

		LoggingHelper::info( '[YousignClient] SR created', array(
			'sr_id'  => $data['id'] ?? 'unknown',
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
	public function activate_signature_request( $sr_id ) {
		$api_key = $this->api_key_manager->get_api_key( 'yousign' );
		
		if ( empty( $api_key ) ) {
			LoggingHelper::critical( '[YousignClient] API key missing' );
			return false;
		}

		$endpoint = $this->get_base_api_url() . "/signature_requests/{$sr_id}/activate";

		// Log de l'activation
		LoggingHelper::debug( '[YousignClient] Activating SR', array(
			'sr_id'    => $sr_id,
			'endpoint' => $endpoint,
		) );

		$response = \wp_remote_post( $endpoint, array(
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
		) );

		if ( \is_wp_error( $response ) ) {
			LoggingHelper::critical( '[YousignClient] SR activation failed', array(
				'sr_id' => $sr_id,
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$http_code = \wp_remote_retrieve_response_code( $response );
		$body      = \wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( 200 !== $http_code && 201 !== $http_code ) {
			LoggingHelper::critical( '[YousignClient] SR activation error', array(
				'sr_id'         => $sr_id,
				'http_code'     => $http_code,
				'response_body' => substr( $body, 0, 500 ),
			) );
			return false;
		}

		LoggingHelper::info( '[YousignClient] SR activated', array(
			'sr_id'          => $sr_id,
			'status'         => $data['status'] ?? 'unknown',
			'has_signers'    => ! empty( $data['signers'] ),
			'signers_count'  => isset( $data['signers'] ) ? count( $data['signers'] ) : 0,
		) );

		return $data;
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
}

