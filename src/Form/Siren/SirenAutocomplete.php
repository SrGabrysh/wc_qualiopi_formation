<?php
/**
 * Gestion de l'autocomplete SIRET via API SIREN
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\SecretManager;
use WcQualiopiFormation\Helpers\ApiKeyManager;
use WcQualiopiFormation\Form\Siren\SirenValidator;
use WcQualiopiFormation\Form\Siren\SirenApiClient;
use WcQualiopiFormation\Form\Siren\SirenCache;
use WcQualiopiFormation\Form\Siren\SirenDataMerger;
use WcQualiopiFormation\Form\Siren\SirenErrorHandler;
use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de gestion de l'autocomplete SIRET
 *
 * Fonctionnalités :
 * - Appel API SIREN (établissement + unité légale)
 * - Cache résultats (transients WordPress 24h)
 * - Fusion données entreprise
 * - Détection type entreprise (PM/EI)
 */
class SirenAutocomplete {

	/**
	 * Instance du validator
	 *
	 * @var SirenValidator
	 */
	private $validator;

	/**
	 * Instance du client API
	 *
	 * @var SirenApiClient|null
	 */
	private $api_client;

	/**
	 * Instance du cache
	 *
	 * @var SirenCache
	 */
	private $cache;

	/**
	 * Instance du merger
	 *
	 * @var SirenDataMerger
	 */
	private $merger;

	/**
	 * Instance de l'error handler
	 *
	 * @var SirenErrorHandler
	 */
	private $error_handler;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->validator = new SirenValidator();
		$this->cache     = new SirenCache();
		$this->merger    = new SirenDataMerger( $this->validator );
		$this->error_handler = new SirenErrorHandler();

		LoggingHelper::debug( '[SirenAutocomplete] Initialized' );

		// Initialiser le client API si clé disponible.
		$api_key = $this->get_api_key();
		if ( ! empty( $api_key ) ) {
			$this->api_client = new SirenApiClient( $api_key );
			LoggingHelper::info( '[SirenAutocomplete] API client initialized' );
		} else {
			LoggingHelper::warning( '[SirenAutocomplete] API key missing' );
		}
	}

	/**
	 * Récupère la clé API SIREN
	 *
	 * [MODIFICATION 2025-10-07] Utilisation d'ApiKeyManager pour centraliser la gestion des clés API
	 * Ancienne version : multi-sources (wp-config, option, SecretManager)
	 * Nouvelle version : délégation à ApiKeyManager
	 *   - Gère automatiquement .env, BDD chiffrée, audit trail
	 *   - Ordre de priorité : 1. Variable d'environnement (.env), 2. Base de données (chiffrée)
	 *   - Logs sécurisés (aperçu partiel des clés)
	 *
	 * @return string|null La clé API ou null si non trouvée.
	 */
	private function get_api_key() {
		$api_key_manager = ApiKeyManager::get_instance();
		$api_key = $api_key_manager->get_api_key( 'siren' );

		if ( ! empty( $api_key ) ) {
			LoggingHelper::debug( '[SirenAutocomplete] API key retrieved from ApiKeyManager' );
		} else {
			LoggingHelper::warning( '[SirenAutocomplete] No SIREN API key found (check ApiKeyManager config)' );
		}

		return $api_key;
	}

	/**
	 * Récupère les données d'une entreprise par son SIRET
	 *
	 * Point d'entrée principal pour la vérification SIRET.
	 *
	 * @param string $siret Le numéro SIRET.
	 * @return array|\WP_Error Les données de l'entreprise ou une erreur.
	 */
	public function get_company_data( $siret ) {
		LoggingHelper::log_api_call( $this->logger, 'SIREN', 'get_company_data', array( 'siret' => $siret ) );

		// 1. Validation SIRET.
		$validation = $this->validator->validate_siret_complete( $siret );

		if ( ! $validation['valid'] ) {
			LoggingHelper::log_validation_error( $this->logger, 'siret', $siret, $validation['message'] );
			return new \WP_Error( 'invalid_siret', $validation['message'] );
		}

		$siret_cleaned = $validation['cleaned'];
		LoggingHelper::log_validation_error( $this->logger, 'siret', $siret_cleaned, 'SIRET valide et nettoyé' );

		// 2. Vérifier le cache.
		$cached_data = $this->cache->get( $siret_cleaned );
		if ( false !== $cached_data ) {
			LoggingHelper::log_cache_operation( $this->logger, 'hit', 'SIREN', array(
				'siret' => $siret_cleaned,
				'data_keys' => array_keys( $cached_data ),
			) );
			return $cached_data;
		}

		LoggingHelper::log_cache_operation( $this->logger, 'miss', 'SIREN', array( 'siret' => $siret_cleaned ) );

		// 3. Appel API SIREN.
		$company_data = $this->fetch_from_api( $siret_cleaned );

		if ( is_wp_error( $company_data ) ) {
			LoggingHelper::log_api_error( $this->logger, 'SIREN', $this->error_handler->format_error_for_log( $company_data ) );
			return $company_data;
		}

		// 4. Mettre en cache.
		$this->cache->set( $siret_cleaned, $company_data );
		LoggingHelper::log_cache_operation( $this->logger, 'set', 'SIREN', array( 'siret' => $siret_cleaned ) );

		LoggingHelper::log_api_call( $this->logger, 'SIREN', 'get_company_data_success', array(
			'siret' => $siret_cleaned,
			'denomination' => $company_data['denomination'] ?? 'N/A',
			'type' => $company_data['type_entreprise'] ?? 'N/A',
		) );

		return $company_data;
	}

	/**
	 * Récupère les données depuis l'API SIREN
	 *
	 * @param string $siret SIRET nettoyé.
	 * @return array|\WP_Error Données de l'entreprise ou erreur.
	 */
	private function fetch_from_api( $siret ) {
		LoggingHelper::log_api_call( $this->logger, 'SIREN', 'fetch_from_api', array( 'siret' => $siret ) );

		if ( null === $this->api_client ) {
			LoggingHelper::log_validation_error( $this->logger, 'api_client', 'missing', 'Cannot fetch data: API client not initialized (missing API key)' );
			return new \WP_Error( 'missing_api_key', __( 'La clé API SIREN n\'est pas configurée.', Constants::TEXT_DOMAIN ) );
		}

		// 1. Récupérer données établissement.
		LoggingHelper::log_api_call( $this->logger, 'SIREN', '/etablissement/' . $siret, array( 'siret' => $siret ) );
		$etablissement = $this->api_client->get_etablissement( $siret );
		
		if ( is_wp_error( $etablissement ) ) {
			$this->error_handler->log_error( 'etablissement', $etablissement );
			return $etablissement;
		}

		LoggingHelper::log_api_call( $this->logger, 'SIREN', 'etablissement_response', array(
			'keys' => array_keys( $etablissement ),
			'has_etablissement_key' => isset( $etablissement['etablissement'] ),
		) );

		// 2. Extraire SIREN et récupérer unité légale.
		$siren = $this->validator->extract_siren( $siret );
		LoggingHelper::log_api_call( $this->logger, 'SIREN', '/unite_legale/' . $siren, array( 'siren' => $siren ) );
		
		$unite_legale = $this->api_client->get_unite_legale( $siren );
		
		if ( is_wp_error( $unite_legale ) ) {
			$this->error_handler->log_error( 'unite_legale', $unite_legale );
			return $unite_legale;
		}

		LoggingHelper::log_api_call( $this->logger, 'SIREN', 'unite_legale_response', array(
			'keys' => array_keys( $unite_legale ),
			'has_unite_legale_key' => isset( $unite_legale['unite_legale'] ),
		) );

		// 3. Fusion des données.
		LoggingHelper::log_mapping_operation( $this->logger, 'merge', 'api_data', 'company_data', array( 'siret' => $siret, 'siren' => $siren ) );
		return $this->merger->merge( $etablissement, $unite_legale, $siret, $siren );
	}

	/**
	 * Vide le cache SIRET
	 *
	 * @return int Nombre d'entrées supprimées.
	 */
	public function clear_cache() {
		global $wpdb;

		$pattern = $wpdb->esc_like( '_transient_' . Constants::CACHE_PREFIX . 'siren_' ) . '%';
		$count   = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		LoggingHelper::log_cache_operation( $this->logger, 'clear', 'SIREN', array( 'count' => $count ) );

		return $count;
	}

	/**
	 * Teste la connexion à l'API SIREN
	 *
	 * @param string $test_siret SIRET de test.
	 * @return array ['success' => bool, 'message' => string].
	 */
	public function test_api_connection( $test_siret = '73282932000074' ) {
		if ( null === $this->api_client ) {
			return array(
				'success' => false,
				'message' => __( 'La clé API SIREN n\'est pas configurée.', Constants::TEXT_DOMAIN ),
			);
		}

		$result = $this->get_company_data( $test_siret );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connexion à l\'API SIREN réussie !', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Compte les entrées en cache
	 *
	 * @return int Nombre d'entrées en cache.
	 */
	public function get_cache_count() {
		global $wpdb;

		$pattern = $wpdb->esc_like( '_transient_' . Constants::CACHE_PREFIX . 'siren_' ) . '%';
		$count   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return (int) $count;
	}
}
