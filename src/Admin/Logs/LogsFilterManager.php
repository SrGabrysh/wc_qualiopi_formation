<?php
/**
 * LogsFilterManager - Gestion de la logique de filtrage des logs
 *
 * @package WcQualiopiFormation\Admin\Logs
 */

namespace WcQualiopiFormation\Admin\Logs;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsFilterManager
 * Gère la validation et l'application des filtres sur les logs
 * RESPONSABILITÉ UNIQUE : Logique de filtrage
 */
class LogsFilterManager {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Niveaux de logs autorisés
	 *
	 * @var array
	 */
	private const ALLOWED_LEVELS = array( 'debug', 'info', 'warning', 'error', 'critical' );

	/**
	 * Périodes de temps prédéfinies (en secondes)
	 *
	 * @var array
	 */
	private const TIME_PERIODS = array(
		'5min'  => 300,      // 5 minutes
		'15min' => 900,      // 15 minutes
		'1h'    => 3600,     // 1 heure
		'24h'   => 86400,    // 24 heures
		'7d'    => 604800,   // 7 jours
		'30d'   => 2592000,  // 30 jours
		'all'   => 0,        // Tous les logs
	);

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Récupère et valide les paramètres de filtres depuis $_GET
	 *
	 * @return array Paramètres de filtres validés.
	 */
	public function get_filter_params() {
		$date_filter = isset( $_GET['wcqf_date_filter'] ) ? \sanitize_text_field( \wp_unslash( $_GET['wcqf_date_filter'] ) ) : 'all';
		$level_filter_raw = isset( $_GET['wcqf_level_filter'] ) ? \wp_unslash( $_GET['wcqf_level_filter'] ) : array();
		$limit = isset( $_GET['wcqf_limit'] ) ? \absint( $_GET['wcqf_limit'] ) : 100;

		// Valider la période de temps
		if ( ! array_key_exists( $date_filter, self::TIME_PERIODS ) ) {
			$date_filter = 'all';
		}

		// Valider les niveaux (tableau PHP des checkboxes)
		$levels = array();
		if ( is_array( $level_filter_raw ) && ! empty( $level_filter_raw ) ) {
			$levels = array_filter(
				array_map( 'sanitize_text_field', $level_filter_raw ),
				function( $level ) {
					return in_array( $level, self::ALLOWED_LEVELS, true );
				}
			);
			$levels = array_values( $levels ); // Réindexer le tableau
		}

		// Valider la limite (100, 200, 500)
		$allowed_limits = array( 100, 200, 500 );
		if ( ! in_array( $limit, $allowed_limits, true ) ) {
			$limit = 100;
		}

		$params = array(
			'date_filter'  => $date_filter,
			'level_filter' => $levels,
			'limit'        => $limit,
		);

		$this->logger->debug( '[LogsFilterManager] Paramètres de filtres validés', $params );

		return $params;
	}

	/**
	 * Applique le filtre de date sur les logs
	 *
	 * @param array  $logs Liste des logs.
	 * @param string $date_filter Période de temps.
	 * @return array Logs filtrés.
	 */
	public function apply_date_filter( $logs, $date_filter ) {
		if ( 'all' === $date_filter || empty( $logs ) ) {
			return $logs;
		}

		$period_seconds = self::TIME_PERIODS[ $date_filter ] ?? 0;
		if ( 0 === $period_seconds ) {
			return $logs;
		}

		$cutoff_time = time() - $period_seconds;
		$filtered_logs = array();

		foreach ( $logs as $log ) {
			$log_timestamp = $this->parse_timestamp( $log['timestamp'] );
			if ( $log_timestamp && $log_timestamp >= $cutoff_time ) {
				$filtered_logs[] = $log;
			}
		}

		$this->logger->debug(
			'[LogsFilterManager] Filtre date appliqué',
			array(
				'period'        => $date_filter,
				'total_logs'    => count( $logs ),
				'filtered_logs' => count( $filtered_logs ),
			)
		);

		return $filtered_logs;
	}

	/**
	 * Applique le filtre de niveau sur les logs (côté PHP si nécessaire)
	 * Note: Le filtrage par niveau se fait principalement côté JS en temps réel
	 *
	 * @param array $logs Liste des logs.
	 * @param array $level_filter Niveaux sélectionnés.
	 * @return array Logs filtrés.
	 */
	public function apply_level_filter( $logs, $level_filter ) {
		if ( empty( $level_filter ) || empty( $logs ) ) {
			return $logs;
		}

		$filtered_logs = array_filter(
			$logs,
			function( $log ) use ( $level_filter ) {
				return in_array( strtolower( $log['level'] ), $level_filter, true );
			}
		);

		return array_values( $filtered_logs );
	}

	/**
	 * Calcule les statistiques des logs filtrés
	 *
	 * @param array $logs Liste des logs.
	 * @return array Statistiques par niveau.
	 */
	public function get_filter_stats( $logs ) {
		$stats = array(
			'total'    => count( $logs ),
			'debug'    => 0,
			'info'     => 0,
			'warning'  => 0,
			'error'    => 0,
			'critical' => 0,
		);

		foreach ( $logs as $log ) {
			$level = strtolower( $log['level'] );
			if ( isset( $stats[ $level ] ) ) {
				$stats[ $level ]++;
			}
		}

		return $stats;
	}

	/**
	 * Parse un timestamp ISO 8601 en timestamp Unix
	 *
	 * @param string $timestamp Timestamp à parser.
	 * @return int|false Timestamp Unix ou false.
	 */
	private function parse_timestamp( $timestamp ) {
		// Format WooCommerce : 2025-10-06T17:14:01+00:00
		$date = \DateTime::createFromFormat( 'Y-m-d\TH:i:sP', $timestamp );
		if ( $date ) {
			return $date->getTimestamp();
		}

		// Fallback : essayer d'autres formats
		$date = \strtotime( $timestamp );
		return $date !== false ? $date : false;
	}

	/**
	 * Obtient les périodes de temps disponibles
	 *
	 * @return array Périodes avec labels.
	 */
	public function get_time_periods() {
		return array(
			'5min'  => \__( '5 dernières minutes', Constants::TEXT_DOMAIN ),
			'15min' => \__( '15 dernières minutes', Constants::TEXT_DOMAIN ),
			'1h'    => \__( '1 heure', Constants::TEXT_DOMAIN ),
			'24h'   => \__( '24 heures', Constants::TEXT_DOMAIN ),
			'7d'    => \__( '7 jours', Constants::TEXT_DOMAIN ),
			'30d'   => \__( '30 jours', Constants::TEXT_DOMAIN ),
			'all'   => \__( 'Tout afficher', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Obtient les niveaux de logs disponibles
	 *
	 * @return array Niveaux avec labels.
	 */
	public function get_log_levels() {
		return array(
			'debug'    => \__( 'Debug', Constants::TEXT_DOMAIN ),
			'info'     => \__( 'Info', Constants::TEXT_DOMAIN ),
			'warning'  => \__( 'Warning', Constants::TEXT_DOMAIN ),
			'error'    => \__( 'Error', Constants::TEXT_DOMAIN ),
			'critical' => \__( 'Critical', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Vérifie si des filtres sont actifs
	 *
	 * @param array $filter_params Paramètres de filtres.
	 * @return bool True si des filtres sont actifs.
	 */
	public function has_active_filters( $filter_params ) {
		return ( 'all' !== $filter_params['date_filter'] ) || ! empty( $filter_params['level_filter'] );
	}

	/**
	 * Obtient les limites de logs disponibles
	 *
	 * @return array Limites disponibles.
	 */
	public function get_log_limits() {
		return array(
			100 => \__( '100 logs', Constants::TEXT_DOMAIN ),
			200 => \__( '200 logs', Constants::TEXT_DOMAIN ),
			500 => \__( '500 logs', Constants::TEXT_DOMAIN ),
		);
	}
}

