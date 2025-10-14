<?php
/**
 * ApiKeyManager - Gestionnaire centralisé et sécurisé des clés API
 *
 * Fonctionnalités :
 * - Stockage sécurisé avec chiffrement
 * - Support variables d'environnement (.env)
 * - Audit trail des modifications
 * - Gestion des endpoints d'API
 * - Logs partiels pour debug
 *
 * @package WcQualiopiFormation\Helpers
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ApiKeyManager
 * Gestion centralisée et sécurisée des clés API
 * RESPONSABILITÉ UNIQUE : Gérer les clés API de manière sécurisée
 */
class ApiKeyManager {
	
	/**
	 * Instance singleton
	 *
	 * @var ApiKeyManager|null
	 */
	private static $instance = null;

	/**
	 * Configuration des providers API
	 *
	 * @var array
	 */
	private const PROVIDERS = array(
		'siren' => array(
			'name'     => 'API SIREN INSEE',
			'endpoint' => 'https://api.insee.fr/entreprises/sirene/V3',
			'env_key'  => 'WCQF_SIREN_API_KEY',
		),
		'openai' => array(
			'name'     => 'OpenAI API',
			'endpoint' => 'https://api.openai.com/v1',
			'env_key'  => 'WCQF_OPENAI_API_KEY',
		),
		'anthropic' => array(
			'name'     => 'Anthropic Claude API',
			'endpoint' => 'https://api.anthropic.com/v1',
			'env_key'  => 'WCQF_ANTHROPIC_API_KEY',
		),
		'monday' => array(
			'name'     => 'Monday.com API',
			'endpoint' => 'https://api.monday.com/v2',
			'env_key'  => 'WCQF_MONDAY_API_KEY',
		),
		'yousign' => array(
			'name'     => 'Yousign API',
			'endpoint' => 'https://api.yousign.com/v3',
			'env_key'  => 'WCQF_YOUSIGN_API_KEY',
		),
	);

	/**
	 * Clé de chiffrement (salt WordPress)
	 *
	 * @var string
	 */
	private $encryption_key;

	/**
	 * Constructeur privé pour le pattern Singleton
	 */
	private function __construct() {
		$this->encryption_key = $this->get_encryption_key();
		
		LoggingHelper::debug( '[ApiKeyManager] Initialized', array(
			'providers_count' => count( self::PROVIDERS ),
			'providers' => array_keys( self::PROVIDERS ),
		) );
	}
	
	/**
	 * Récupère l'instance singleton
	 *
	 * @return ApiKeyManager
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Récupère une clé API
	 * Ordre de priorité : 1. Variable d'environnement, 2. Base de données
	 *
	 * @param string $provider Nom du provider (siren, openai, etc.).
	 * @return string|null Clé API ou null si non trouvée.
	 */
	public function get_api_key( $provider ) {
		if ( ! $this->is_valid_provider( $provider ) ) {
			LoggingHelper::warning( '[ApiKeyManager] Provider invalide', array( 'provider' => $provider ) );
			return null;
		}

		// 1. Essayer variable d'environnement (.env)
		$env_key = self::PROVIDERS[ $provider ]['env_key'];
		$env_value = \getenv( $env_key );
		
		if ( ! empty( $env_value ) ) {
			LoggingHelper::debug( '[ApiKeyManager] Cle API depuis .env', array(
				'provider' => $provider,
				'source' => 'environment',
				'key_preview' => $this->get_key_preview( $env_value ),
			) );
			return $env_value;
		}

		// 2. Récupérer depuis base de données (chiffrée)
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		$encrypted_key = $settings['api_keys'][ $provider ] ?? '';

		LoggingHelper::debug( '[ApiKeyManager] Recherche cle API en BDD', array(
			'provider' => $provider,
			'settings_keys' => array_keys( $settings ),
			'api_keys_present' => isset( $settings['api_keys'] ),
			'api_keys_count' => isset( $settings['api_keys'] ) ? count( $settings['api_keys'] ) : 0,
			'encrypted_key_length' => strlen( $encrypted_key ),
			'api_keys_structure' => isset( $settings['api_keys'] ) ? array_keys( $settings['api_keys'] ) : 'N/A',
		) );

		if ( empty( $encrypted_key ) ) {
			LoggingHelper::debug( '[ApiKeyManager] Aucune cle API trouvee', array( 
				'provider' => $provider,
				'api_keys_structure' => isset( $settings['api_keys'] ) ? array_keys( $settings['api_keys'] ) : 'N/A',
			) );
			return null;
		}

		// Déchiffrer la clé
		$decrypted_key = $this->decrypt( $encrypted_key );

		LoggingHelper::debug( '[ApiKeyManager] Cle API recup depuis BDD', array(
			'provider' => $provider,
			'source' => 'database',
			'key_preview' => $this->get_key_preview( $decrypted_key ),
		) );

		return $decrypted_key;
	}

	/**
	 * Définit une clé API (chiffrée en base de données)
	 *
	 * @param string $provider Nom du provider.
	 * @param string $api_key Clé API en clair.
	 * @return bool True si succès, false sinon.
	 */
	public function set_api_key( $provider, $api_key ) {
		if ( ! $this->is_valid_provider( $provider ) ) {
			LoggingHelper::error( '[ApiKeyManager] Provider invalide pour set', array( 'provider' => $provider ) );
			return false;
		}

		// Valider la clé (non vide)
		if ( empty( $api_key ) ) {
			LoggingHelper::warning( '[ApiKeyManager] Tentative de set cle vide', array( 'provider' => $provider ) );
			return false;
		}

		// Chiffrer la clé
		$encrypted_key = $this->encrypt( $api_key );

		// Sauvegarder en base de données
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		// [CORRECTION 2025-10-14] S'assurer que la structure api_keys existe
		if ( ! isset( $settings['api_keys'] ) ) {
			$settings['api_keys'] = array();
		}
		
		$settings['api_keys'][ $provider ] = $encrypted_key;

		$result = \update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result ) {
			LoggingHelper::info( '[ApiKeyManager] Cle API sauvegardee', array(
				'provider' => $provider,
				'key_preview' => $this->get_key_preview( $api_key ),
				'api_keys_structure' => array_keys( $settings['api_keys'] ),
			) );

			// Audit trail
			$this->log_audit( $provider, 'set', $api_key );
		} else {
			LoggingHelper::error( '[ApiKeyManager] Echec sauvegarde cle API', array( 'provider' => $provider ) );
		}

		return $result;
	}

	/**
	 * Vérifie si une clé API existe
	 *
	 * @param string $provider Nom du provider.
	 * @return bool True si la clé existe, false sinon.
	 */
	public function has_api_key( $provider ) {
		$key = $this->get_api_key( $provider );
		$has_key = ! empty( $key );

		LoggingHelper::debug( '[ApiKeyManager] Verification presence cle', array(
			'provider' => $provider,
			'has_key' => $has_key,
		) );

		return $has_key;
	}

	/**
	 * Supprime une clé API
	 *
	 * @param string $provider Nom du provider.
	 * @return bool True si succès, false sinon.
	 */
	public function delete_api_key( $provider ) {
		if ( ! $this->is_valid_provider( $provider ) ) {
			return false;
		}

		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		
		if ( ! isset( $settings['api_keys'][ $provider ] ) ) {
			return true; // Déjà supprimée
		}

		unset( $settings['api_keys'][ $provider ] );
		$result = \update_option( Constants::OPTION_SETTINGS, $settings );

		if ( $result ) {
			LoggingHelper::info( '[ApiKeyManager] Cle API supprimee', array( 'provider' => $provider ) );
			$this->log_audit( $provider, 'delete' );
		}

		return $result;
	}

	/**
	 * Récupère l'endpoint d'une API
	 *
	 * @param string $provider Nom du provider.
	 * @return string|null Endpoint ou null si non trouvé.
	 */
	public function get_endpoint( $provider ) {
		if ( ! $this->is_valid_provider( $provider ) ) {
			return null;
		}

		$endpoint = self::PROVIDERS[ $provider ]['endpoint'];

		LoggingHelper::debug( '[ApiKeyManager] Endpoint recupere', array(
			'provider' => $provider,
			'endpoint' => $endpoint,
		) );

		return $endpoint;
	}

	/**
	 * Récupère le nom complet d'un provider
	 *
	 * @param string $provider Nom du provider.
	 * @return string|null Nom complet ou null.
	 */
	public function get_provider_name( $provider ) {
		return self::PROVIDERS[ $provider ]['name'] ?? null;
	}

	/**
	 * Liste tous les providers disponibles
	 *
	 * @return array Liste des providers avec leurs infos.
	 */
	public function get_all_providers() {
		return self::PROVIDERS;
	}

	/**
	 * Valide si un provider existe
	 *
	 * @param string $provider Nom du provider.
	 * @return bool True si valide, false sinon.
	 */
	private function is_valid_provider( $provider ) {
		return isset( self::PROVIDERS[ $provider ] );
	}

	/**
	 * Chiffre une clé API
	 *
	 * @param string $data Données à chiffrer.
	 * @return string Données chiffrées en base64.
	 */
	private function encrypt( $data ) {
		$iv = \openssl_random_pseudo_bytes( \openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$encrypted = \openssl_encrypt( $data, 'aes-256-cbc', $this->encryption_key, 0, $iv );
		
		// Stocker IV avec les données chiffrées
		$result = \base64_encode( $iv . '::' . $encrypted );

		LoggingHelper::debug( '[ApiKeyManager] Donnees chiffrees', array(
			'cipher' => 'aes-256-cbc',
			'iv_length' => strlen( $iv ),
		) );

		return $result;
	}

	/**
	 * Déchiffre une clé API
	 *
	 * @param string $data Données chiffrées.
	 * @return string Données déchiffrées.
	 */
	private function decrypt( $data ) {
		$data = \base64_decode( $data );
		
		if ( strpos( $data, '::' ) === false ) {
			LoggingHelper::warning( '[ApiKeyManager] Format de chiffrement invalide' );
			return '';
		}

		list( $iv, $encrypted ) = explode( '::', $data, 2 );
		$decrypted = \openssl_decrypt( $encrypted, 'aes-256-cbc', $this->encryption_key, 0, $iv );

		LoggingHelper::debug( '[ApiKeyManager] Donnees dechiffrees' );

		return $decrypted;
	}

	/**
	 * Récupère la clé de chiffrement
	 *
	 * @return string Clé de chiffrement.
	 */
	private function get_encryption_key() {
		// Utiliser le salt WordPress comme clé de base
		if ( defined( 'AUTH_KEY' ) ) {
			return \substr( \hash( 'sha256', AUTH_KEY ), 0, 32 );
		}

		// Fallback (ne devrait jamais arriver en production)
		LoggingHelper::warning( '[ApiKeyManager] AUTH_KEY non defini, utilisation fallback' );
		return \substr( \hash( 'sha256', 'wcqf-fallback-key' ), 0, 32 );
	}

	/**
	 * Génère un aperçu sécurisé d'une clé (premiers et derniers caractères)
	 *
	 * @param string $key Clé API.
	 * @return string Aperçu sécurisé (ex: "FlwM9...RXal").
	 */
	private function get_key_preview( $key ) {
		if ( empty( $key ) ) {
			return '[empty]';
		}

		$length = strlen( $key );
		
		if ( $length <= 10 ) {
			return '***'; // Clé trop courte, on masque tout
		}

		$start = \substr( $key, 0, 5 );
		$end = \substr( $key, -4 );

		return $start . '...' . $end;
	}

	/**
	 * Enregistre une entrée d'audit
	 *
	 * @param string      $provider Nom du provider.
	 * @param string      $action Action effectuée (set, delete).
	 * @param string|null $key Clé API (optionnel, pour aperçu).
	 * @return void
	 */
	private function log_audit( $provider, $action, $key = null ) {
		$audit_data = array(
			'timestamp' => \current_time( 'mysql' ),
			'user_id' => \get_current_user_id(),
			'user_login' => \wp_get_current_user()->user_login ?? 'system',
			'provider' => $provider,
			'action' => $action,
			'key_preview' => $key ? $this->get_key_preview( $key ) : null,
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
		);

		// Sauvegarder dans option dédiée (dernières 100 entrées)
		$audit_log = \get_option( 'wcqf_api_audit', array() );
		array_unshift( $audit_log, $audit_data );
		$audit_log = \array_slice( $audit_log, 0, 100 ); // Garder seulement 100 dernières entrées

		\update_option( 'wcqf_api_audit', $audit_log );

		LoggingHelper::info( '[ApiKeyManager] Audit trail enregistre', $audit_data );
	}

	/**
	 * Récupère l'historique d'audit
	 *
	 * @param int $limit Nombre d'entrées à récupérer.
	 * @return array Historique d'audit.
	 */
	public function get_audit_log( $limit = 50 ) {
		$audit_log = \get_option( 'wcqf_api_audit', array() );
		return \array_slice( $audit_log, 0, $limit );
	}

	/**
	 * Migre une clé API hardcodée vers la base de données
	 * Utilisé pour la transition douce lors de la mise à jour du plugin
	 *
	 * @param string $provider Nom du provider.
	 * @param string $hardcoded_key Clé actuellement hardcodée.
	 * @return bool True si migration effectuée, false si clé déjà existante.
	 */
	public function migrate_hardcoded_key( $provider, $hardcoded_key ) {
		// Si clé déjà présente, ne rien faire
		if ( $this->has_api_key( $provider ) ) {
			LoggingHelper::info( '[ApiKeyManager] Migration sautee, cle deja presente', array(
				'provider' => $provider,
			) );
			return false;
		}

		// Migrer la clé hardcodée
		$result = $this->set_api_key( $provider, $hardcoded_key );

		if ( $result ) {
			LoggingHelper::info( '[ApiKeyManager] Migration cle hardcodee vers BDD', array(
				'provider' => $provider,
				'key_preview' => $this->get_key_preview( $hardcoded_key ),
			) );
		}

		return $result;
	}
}

