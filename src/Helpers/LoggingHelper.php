<?php
/**
 * LoggingHelper - Utilitaires de logging standardisés
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoggingHelper
 * Utilitaires pour standardiser les logs dans le plugin
 */
class LoggingHelper {

	/**
	 * Log standard pour les requêtes AJAX
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $action Action AJAX.
	 * @param array $data Données à logger.
	 * @param string $level Niveau de log (info, warning, error).
	 * @return void
	 */
	public static function log_ajax_request( $logger, $action, $data = array(), $level = 'info' ) {
		$context = array(
			'action' => $action,
			'data'   => $data,
			'timestamp' => current_time( 'mysql' ),
		);

		$message = sprintf( '[AJAX] %s', $action );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}

	/**
	 * Log standard pour les appels API
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $api_name Nom de l'API.
	 * @param string $endpoint Endpoint appelé.
	 * @param array $params Paramètres de l'appel.
	 * @param string $level Niveau de log.
	 * @return void
	 */
	public static function log_api_call( $logger, $api_name, $endpoint, $params = array(), $level = 'info' ) {
		$context = array(
			'api_name' => $api_name,
			'endpoint' => $endpoint,
			'params'   => $params,
			'timestamp' => current_time( 'mysql' ),
		);

		$message = sprintf( '[API] %s - %s', $api_name, $endpoint );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}

	/**
	 * Log standard pour les erreurs de validation
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $field Champ validé.
	 * @param mixed $value Valeur validée.
	 * @param string $error_message Message d'erreur.
	 * @return void
	 */
	public static function log_validation_error( $logger, $field, $value, $error_message ) {
		$context = array(
			'field' => $field,
			'value' => $value,
			'error' => $error_message,
			'timestamp' => current_time( 'mysql' ),
		);

		$message = sprintf( '[VALIDATION] Erreur sur le champ %s', $field );

		$logger->warning( $message, $context );
	}

	/**
	 * Log standard pour les opérations de cache
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $operation Opération (get, set, delete).
	 * @param string $key Clé du cache.
	 * @param mixed $value Valeur (pour set).
	 * @param string $level Niveau de log.
	 * @return void
	 */
	public static function log_cache_operation( $logger, $operation, $key, $value = null, $level = 'info' ) {
		$context = array(
			'operation' => $operation,
			'key'       => $key,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( 'set' === $operation && null !== $value ) {
			$context['value'] = $value;
		}

		$message = sprintf( '[CACHE] %s - %s', strtoupper( $operation ), $key );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}

	/**
	 * Log standard pour les opérations de base de données
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $operation Opération (select, insert, update, delete).
	 * @param string $table Table concernée.
	 * @param array $data Données (pour insert/update).
	 * @param string $level Niveau de log.
	 * @return void
	 */
	public static function log_db_operation( $logger, $operation, $table, $data = array(), $level = 'info' ) {
		$context = array(
			'operation' => $operation,
			'table'     => $table,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( ! empty( $data ) ) {
			$context['data'] = $data;
		}

		$message = sprintf( '[DB] %s - %s', strtoupper( $operation ), $table );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}

	/**
	 * Log standard pour les erreurs de formatage
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $formatter Type de formatter.
	 * @param mixed $input Données d'entrée.
	 * @param string $error_message Message d'erreur.
	 * @return void
	 */
	public static function log_formatting_error( $logger, $formatter, $input, $error_message ) {
		$context = array(
			'formatter' => $formatter,
			'input'     => $input,
			'error'     => $error_message,
			'timestamp' => current_time( 'mysql' ),
		);

		$message = sprintf( '[FORMAT] Erreur %s', $formatter );

		$logger->warning( $message, $context );
	}

	/**
	 * Log standard pour les opérations de mapping
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $operation Opération (map, validate, extract).
	 * @param string $source Source des données.
	 * @param string $target Cible du mapping.
	 * @param array $data Données mappées.
	 * @param string $level Niveau de log.
	 * @return void
	 */
	public static function log_mapping_operation( $logger, $operation, $source, $target, $data = array(), $level = 'info' ) {
		$context = array(
			'operation' => $operation,
			'source'    => $source,
			'target'    => $target,
			'timestamp' => current_time( 'mysql' ),
		);

		if ( ! empty( $data ) ) {
			$context['data'] = $data;
		}

		$message = sprintf( '[MAPPING] %s - %s → %s', strtoupper( $operation ), $source, $target );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}

	/**
	 * Log standard pour les performances
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $operation Opération mesurée.
	 * @param float $duration Durée en secondes.
	 * @param array $context Contexte supplémentaire.
	 * @return void
	 */
	public static function log_performance( $logger, $operation, $duration, $context = array() ) {
		$context = array_merge( $context, array(
			'operation' => $operation,
			'duration'  => $duration,
			'timestamp' => current_time( 'mysql' ),
		) );

		$message = sprintf( '[PERF] %s - %.3fs', $operation, $duration );

		$logger->info( $message, $context );
	}

	/**
	 * Log standard pour les hooks WordPress
	 *
	 * @param \WcQualiopiFormation\Utils\Logger $logger Instance du logger.
	 * @param string $hook Nom du hook.
	 * @param string $action Action (add, remove, do).
	 * @param array $context Contexte supplémentaire.
	 * @param string $level Niveau de log.
	 * @return void
	 */
	public static function log_wp_hook( $logger, $hook, $action, $context = array(), $level = 'info' ) {
		$context = array_merge( $context, array(
			'hook'      => $hook,
			'action'    => $action,
			'timestamp' => current_time( 'mysql' ),
		) );

		$message = sprintf( '[HOOK] %s - %s', strtoupper( $action ), $hook );

		switch ( $level ) {
			case 'warning':
				$logger->warning( $message, $context );
				break;
			case 'error':
				$logger->error( $message, $context );
				break;
			default:
				$logger->info( $message, $context );
		}
	}
}
