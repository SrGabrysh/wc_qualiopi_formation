<?php
/**
 * Client pour les appels HTTP à l'API SIREN
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;

/**
 * Classe de communication avec l'API SIREN
 *
 * Responsabilités :
 * - Appels HTTP avec retry
 * - Gestion des erreurs API
 * - Logging des requêtes
 */
class SirenApiClient {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Clé API SIREN
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 * @param string $api_key Clé API SIREN.
	 */
	public function __construct( Logger $logger, $api_key ) {
		$this->logger  = $logger;
		
		// [MODIFICATION 2025-10-07] Correction : utiliser le paramètre au lieu de la valeur hardcodée
		// Ancienne version : $this->api_key = 'FlwM9Symg1SIox2WYRSN2vhRmCCwRXal'; (HARDCODÉ)
		// Nouvelle version : utilisation du paramètre $api_key (depuis ApiKeyManager)
		$this->api_key = $api_key;
		
		$this->logger->info( '[SirenApiClient] API Key configuree', array(
			'key_present' => ! empty( $api_key ),
			'key_length' => strlen( $api_key ),
			'key_preview' => ! empty( $api_key ) ? substr( $api_key, 0, 5 ) . '...' . substr( $api_key, -4 ) : '[empty]',
		) );
	}

	/**
	 * Récupère les données d'un établissement par son SIRET
	 *
	 * @param string $siret SIRET nettoyé.
	 * @return array|\WP_Error Données établissement ou erreur.
	 */
	public function get_etablissement( $siret ) {
		$endpoint = Constants::API_SIREN_ENDPOINT_ETAB . '/' . $siret;
		return $this->call_api( $endpoint );
	}

	/**
	 * Récupère les données d'une unité légale par son SIREN
	 *
	 * @param string $siren SIREN.
	 * @return array|\WP_Error Données unité légale ou erreur.
	 */
	public function get_unite_legale( $siren ) {
		$endpoint = Constants::API_SIREN_ENDPOINT_UL . '/' . $siren;
		return $this->call_api( $endpoint );
	}

	/**
	 * Effectue un appel à l'API SIREN avec retry
	 *
	 * @param string $endpoint L'endpoint de l'API.
	 * @return array|\WP_Error Les données de l'API ou une erreur.
	 */
	private function call_api( $endpoint ) {
		$url = Constants::API_SIREN_BASE_URL . $endpoint;

		$args = array(
			'headers' => array(
				'X-Client-Secret' => $this->api_key,
				'Accept'          => 'application/json',
			),
			'timeout' => Constants::API_SIREN_TIMEOUT,
		);

		// DEBUG : Log des paramètres de la requête
		$this->logger->info( '[DEBUG] Parametres requete API', array(
			'url' => $url,
			'api_key_length' => strlen( $this->api_key ),
			'api_key_start' => substr( $this->api_key, 0, 10 ) . '...',
			'headers' => array_keys( $args['headers'] ),
		) );

		$attempts = 0;

		while ( $attempts < Constants::API_SIREN_MAX_RETRIES ) {
			++$attempts;

			$this->logger->debug( "API call attempt {$attempts}/{Constants::API_SIREN_MAX_RETRIES}", array( 'url' => $url ) );

			$response = wp_remote_get( $url, $args );

			// Vérifier erreur réseau.
			if ( is_wp_error( $response ) ) {
				$this->logger->warning( "Attempt {$attempts} failed: " . $response->get_error_message() );

				if ( $attempts < Constants::API_SIREN_MAX_RETRIES ) {
					sleep( Constants::API_SIREN_RETRY_WAIT * $attempts );
					continue;
				}

				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = wp_remote_retrieve_body( $response );

			// Gérer les codes de statut.
			switch ( $status_code ) {
				case 200:
					// LOG PAYLOAD COMPLET
					$this->logger->info( '[API SIREN] PAYLOAD BRUT RECU', array(
						'endpoint' => $endpoint,
						'status' => $status_code,
						'body_length' => strlen( $body ),
						'body' => $body, // PAYLOAD COMPLET
					) );

					$data = json_decode( $body, true );

					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$this->logger->error( 'JSON decode error: ' . json_last_error_msg() );
						return new \WP_Error( 'json_decode_error', __( 'Erreur lors du décodage de la réponse API.', Constants::TEXT_DOMAIN ) );
					}

					$this->logger->info( '[API SIREN] PAYLOAD DECODE', array(
						'endpoint' => $endpoint,
						'data_keys' => array_keys( $data ),
						'data' => $data, // PAYLOAD DÉCODÉ
					) );

					return $data;

				case 400:
					$this->logger->error( "Invalid data (400): {$body}" );
					return new \WP_Error( 'invalid_data', __( 'Le SIRET fourni est invalide ou mal formaté.', Constants::TEXT_DOMAIN ) );

				case 404:
					$this->logger->warning( "Resource not found (404): {$body}" );
					return new \WP_Error( 'not_found', __( 'Aucune entreprise trouvée avec ce SIRET.', Constants::TEXT_DOMAIN ) );

				case 500:
				case 502:
				case 503:
					// Erreur serveur : retry.
					$this->logger->warning( "Server error ({$status_code}) - Attempt {$attempts}/{Constants::API_SIREN_MAX_RETRIES}" );

					if ( $attempts < Constants::API_SIREN_MAX_RETRIES ) {
						sleep( Constants::API_SIREN_RETRY_WAIT * $attempts );
						continue 2; // Continue la boucle while.
					}

					return new \WP_Error( 'server_error', __( 'Le service API SIREN est temporairement indisponible.', Constants::TEXT_DOMAIN ) );

				default:
					$this->logger->error( "API error ({$status_code}): {$body}" );
					return new \WP_Error( 'api_error', sprintf( __( 'Erreur API (%d).', Constants::TEXT_DOMAIN ), $status_code ) );
			}
		}

		return new \WP_Error( 'max_attempts_reached', __( 'Nombre maximum de tentatives atteint.', Constants::TEXT_DOMAIN ) );
	}
}

