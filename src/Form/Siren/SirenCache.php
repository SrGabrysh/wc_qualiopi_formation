<?php
/**
 * SirenCache - Gestion du cache API SIREN
 *
 * @package WcQualiopiFormation\Form\Siren
 */

namespace WcQualiopiFormation\Form\Siren;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SirenCache
 * Gestion centralisée du cache pour l'API SIREN
 */
class SirenCache {

/**
	 * Préfixe pour les clés de cache
	 *
	 * @var string
	 */
	private $cache_prefix = 'wcqf_siren_';

	/**
	 * Constructeur
	 */
	public function __construct() {
	}

	/**
	 * Génère une clé de cache pour un SIRET
	 *
	 * @param string $siret SIRET.
	 * @return string Clé de cache.
	 */
	private function get_cache_key( $siret ) {
		return $this->cache_prefix . sanitize_key( $siret );
	}

	/**
	 * Récupère des données en cache
	 *
	 * @param string $siret SIRET.
	 * @return array|false Données en cache ou false si absent.
	 */
	public function get( $siret ) {
		$cache_key = $this->get_cache_key( $siret );
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			LoggingHelper::debug( 'Cache hit', array( 'siret' => $siret ) );
			return $cached;
		}

		LoggingHelper::debug( 'Cache miss', array( 'siret' => $siret ) );
		return false;
	}

	/**
	 * Enregistre des données en cache
	 *
	 * @param string $siret SIRET.
	 * @param array  $data Données à cacher.
	 * @param int    $duration Durée en secondes (défaut: constante API).
	 * @return bool True si succès.
	 */
	public function set( $siret, $data, $duration = null ) {
		if ( is_null( $duration ) ) {
			$duration = $this->get_cache_duration();
		}

		$cache_key = $this->get_cache_key( $siret );
		$result = set_transient( $cache_key, $data, $duration );

		if ( $result ) {
			LoggingHelper::debug( 'Cache set', array(
				'siret' => $siret,
				'duration' => $duration,
			) );
		}

		return $result;
	}

	/**
	 * Supprime une entrée du cache
	 *
	 * @param string $siret SIRET.
	 * @return bool True si supprimé.
	 */
	public function delete( $siret ) {
		$cache_key = $this->get_cache_key( $siret );
		$result = delete_transient( $cache_key );

		LoggingHelper::debug( 'Cache delete', array(
			'siret' => $siret,
			'success' => $result,
		) );

		return $result;
	}

	/**
	 * Vide tout le cache SIREN
	 *
	 * @return int Nombre d'entrées supprimées.
	 */
	public function flush_all() {
		global $wpdb;

		$pattern = $wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%';

		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		LoggingHelper::info( 'Cache flushed', array( 'count' => $count ) );

		return $count;
	}

	/**
	 * Récupère le nombre d'entrées en cache
	 *
	 * @return int Nombre d'entrées.
	 */
	public function get_cache_count() {
		global $wpdb;

		$pattern = $wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return (int) $count;
	}

	/**
	 * Récupère la durée de cache depuis les constantes
	 *
	 * @return int Durée en secondes.
	 */
	private function get_cache_duration() {
		if ( defined( 'WcQualiopiFormation\Core\Constants::API_SIREN_CACHE_DURATION' ) ) {
			return Constants::API_SIREN_CACHE_DURATION;
		}

		// Fallback: 24 heures.
		return 24 * HOUR_IN_SECONDS;
	}

	/**
	 * Vérifie si le cache est activé
	 *
	 * @return bool True si activé.
	 */
	public function is_enabled() {
		return apply_filters( 'wcqf_siren_cache_enabled', true );
	}

	/**
	 * Nettoie les caches expirés
	 *
	 * @return int Nombre d'entrées nettoyées.
	 */
	public function cleanup_expired() {
		global $wpdb;

		// WordPress nettoie automatiquement les transients expirés.
		// Cette méthode force le nettoyage.
		$count = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_{$this->cache_prefix}%'
			AND option_value < UNIX_TIMESTAMP()"
		);

		LoggingHelper::debug( 'Cache cleanup', array( 'count' => $count ) );

		return $count;
	}

	/**
	 * Récupère les statistiques du cache
	 *
	 * @return array Statistiques.
	 */
	public function get_stats() {
		return array(
			'count' => $this->get_cache_count(),
			'enabled' => $this->is_enabled(),
			'duration' => $this->get_cache_duration(),
			'prefix' => $this->cache_prefix,
		);
	}
}




