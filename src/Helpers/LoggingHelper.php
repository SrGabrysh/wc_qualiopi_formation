<?php
/**
 * LoggingHelper - logs WordPress "CloudWatch-like" (JSON one-line)
 *
 * Stocke en JSON monoligne via error_log() (donc va dans WP_DEBUG_LOG si activé)
 * et reste compatible avec l'écosystème WP (aucune dépendance externe).
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoggingHelper {

	/** @var float|null */
	private static $t0 = null;

	/** Démarrer le timer le plus tôt possible (ex: plugins_loaded). */
	public static function boot(): void {
		if ( self::$t0 === null ) {
			self::$t0 = microtime( true );
		}
	}

	// API simple de niveau
	public static function debug( string $message, array $context = [] ): void { self::log('debug', $message, $context); }
	public static function info( string $message, array $context = [] ): void { self::log('info', $message, $context); }
	public static function notice( string $message, array $context = [] ): void { self::log('notice', $message, $context); }
	public static function warning( string $message, array $context = [] ): void { self::log('warning', $message, $context); }
	public static function error( string $message, array $context = [] ): void { self::log('error', $message, $context); }
	public static function critical( string $message, array $context = [] ): void { self::log('critical', $message, $context); }

	/**
	 * Log principal : JSON "une ligne" façon CloudWatch (mais local WP).
	 *
	 * Champs standard : timestamp, level, message, channel, context, request_id,
	 * user_id, ip, php_version, wp_version, site_url, file, line, class, method,
	 * duration_ms, memory_bytes.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		$record = self::build_record( $level, $message, $context );
		$json   = self::to_single_line_json( $record );

		// alimente error_log -> WP_DEBUG_LOG (si WP_DEBUG & WP_DEBUG_LOG actifs)
		@error_log( $json );
	}

	/** Construit l'enregistrement enrichi. */
	private static function build_record( string $level, string $message, array $context ): array {
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		// chrono & mémoire
		$durationMs = null;
		if ( self::$t0 !== null ) {
			$durationMs = round( ( microtime( true ) - self::$t0 ) * 1000, 2 );
		}

		// infos WordPress (sans crash si indisponibles)
		$wpVersion = defined('WP_VERSION') ? WP_VERSION : ( function_exists('get_bloginfo') ? get_bloginfo('version') : null );
		$siteUrl   = function_exists('site_url') ? site_url() : null;
		$userId    = function_exists('get_current_user_id') ? (int) get_current_user_id() : null;

		// IP client
		$ip = self::client_ip();

		// source (file/line/class/method) si fournie ou déduite
		$src = self::source_from_context_or_trace( $context );

		// channel logique (ex: plugin, module, feature) si fourni
		$channel = $context['channel'] ?? 'wordpress';

		// on nettoie le context (pas de ressources / closures / objets non sérialisables)
		$cleanContext = self::sanitize_context( $context );

		$base = [
			'timestamp'     => $now->format('c'),         // ISO8601 UTC
			'level'         => strtolower($level),
			'message'       => (string) $message,
			'channel'       => (string) $channel,

			// IDs & req
			'request_id'    => self::request_id(),
			'user_id'       => $userId,
			'ip'            => $ip,

			// runtime
			'php_version'   => PHP_VERSION,
			'wp_version'    => $wpVersion,
			'site_url'      => $siteUrl,

			// source
			'file'          => $src['file']   ?? null,
			'line'          => $src['line']   ?? null,
			'class'         => $src['class']  ?? null,
			'method'        => $src['method'] ?? null,

			// perf
			'duration_ms'   => $durationMs,
			'memory_bytes'  => memory_get_usage(true),

			// libre
			'context'       => $cleanContext ?: (object)[],
		];

		// supprime uniquement les null (garde 0/false)
		return array_filter($base, static fn($v) => $v !== null);
	}

	/** JSON monoligne, UTF-8 robuste. */
	private static function to_single_line_json( array $record ): string {
		$json = json_encode(
			$record,
			JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
		);

		// évite de casser la ligne de log
		return str_replace(["\r", "\n"], ' ', (string) $json);
	}

	/** Meilleure IP possible (XFF en premier). */
	private static function client_ip(): ?string {
		foreach (['HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $k) {
			if (!empty($_SERVER[$k])) {
				$parts = explode(',', (string) $_SERVER[$k]);
				return trim($parts[0]);
			}
		}
		return null;
	}

	/** Un request_id basique (HTTP header si dispo, sinon uniqid). */
	private static function request_id(): string {
		$rid = $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
		return $rid ? (string) $rid : uniqid('req_', true);
	}

	/** Source du log : depuis $context['source'] ou via backtrace légère. */
	private static function source_from_context_or_trace( array $context ): array {
		if (!empty($context['source']) && is_array($context['source'])) {
			return [
				'file'   => $context['source']['file']   ?? null,
				'line'   => $context['source']['line']   ?? null,
				'class'  => $context['source']['class']  ?? null,
				'method' => $context['source']['method'] ?? null,
			];
		}
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
		// on prend l'appelant direct (index 2 ou 3 selon PHP)
		$frame = $bt[2] ?? ($bt[3] ?? null);
		return [
			'file'   => $frame['file']   ?? null,
			'line'   => $frame['line']   ?? null,
			'class'  => $frame['class']  ?? null,
			'method' => $frame['function'] ?? null,
		];
	}

	/** Nettoyage du context pour JSON. */
	private static function sanitize_context( array $context ): array {
		unset($context['source']); // déjà extrait
		$clean = [];

		foreach ($context as $k => $v) {
			$clean[$k] = self::normalize_value($v);
		}
		return $clean;
	}

	private static function normalize_value($v) {
		if ($v instanceof \Throwable) {
			return [
				'type'    => get_class($v),
				'message' => $v->getMessage(),
				'code'    => $v->getCode(),
				'file'    => $v->getFile(),
				'line'    => $v->getLine(),
				'trace'   => self::trim_multiline($v->getTraceAsString()),
			];
		}
		if (is_resource($v)) {
			return '(resource)';
		}
		if ($v instanceof \DateTimeInterface) {
			return $v->format('c');
		}
		if (is_object($v)) {
			// tente la sérialisation simple
			if (method_exists($v, '__toString')) {
				return (string) $v;
			}
			return json_decode(json_encode($v, JSON_PARTIAL_OUTPUT_ON_ERROR), true);
		}
		if (is_array($v)) {
			$out = [];
			foreach ($v as $kk => $vv) {
				$out[$kk] = self::normalize_value($vv);
			}
			return $out;
		}
		if (is_string($v)) {
			return self::trim_multiline($v);
		}
		return $v;
	}

	private static function trim_multiline(string $s): string {
		$s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
		return mb_substr($s, 0, 20000); // évite les logs géants
	}

	// ===== MÉTHODES DE COMPATIBILITÉ AVEC L'ANCIEN API =====

	/**
	 * Log d'une requête AJAX (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $action Action AJAX.
	 * @param array  $data Données de la requête.
	 * @param string $level Niveau de log.
	 */
	public static function log_ajax_request( $logger, $action, $data = array(), $level = 'info' ) {
		$message = sprintf( '[AJAX] %s', $action );
		$context = array(
			'channel' => 'ajax',
			'action'  => $action,
			'data'    => $data,
		);

		self::log( $level, $message, $context );
	}

	/**
	 * Log d'un appel API (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $api_name Nom de l'API.
	 * @param string $endpoint Endpoint appelé.
	 * @param array  $params Paramètres de l'appel.
	 * @param string $level Niveau de log.
	 */
	public static function log_api_call( $logger, $api_name, $endpoint, $params = array(), $level = 'info' ) {
		$message = sprintf( '[API] %s - %s', $api_name, $endpoint );
		$context = array(
			'channel' => 'api',
			'api'     => $api_name,
			'endpoint' => $endpoint,
			'params'  => $params,
		);

		self::log( $level, $message, $context );
	}

	/**
	 * Log d'une erreur de validation (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $field Champ validé.
	 * @param mixed  $value Valeur validée.
	 * @param string $error_message Message d'erreur.
	 */
	public static function log_validation_error( $logger, $field, $value, $error_message ) {
		$message = sprintf( '[VALIDATION] Erreur sur le champ %s', $field );
		$context = array(
			'channel' => 'validation',
			'field'   => $field,
			'value'   => $value,
			'error'   => $error_message,
		);

		self::log( 'warning', $message, $context );
	}

	/**
	 * Log d'une opération de cache (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $operation Type d'opération (get, set, delete).
	 * @param string $key Clé du cache.
	 * @param mixed  $value Valeur (pour set).
	 * @param string $level Niveau de log.
	 */
	public static function log_cache_operation( $logger, $operation, $key, $value = null, $level = 'info' ) {
		$message = sprintf( '[CACHE] %s - %s', strtoupper( $operation ), $key );
		$context = array(
			'channel'   => 'cache',
			'operation' => $operation,
			'key'       => $key,
		);

		if ( $value !== null ) {
			$context['value'] = $value;
		}

		self::log( $level, $message, $context );
	}

	/**
	 * Log d'une opération de base de données (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $operation Type d'opération (insert, update, delete, select).
	 * @param string $table Table concernée.
	 * @param array  $data Données de l'opération.
	 * @param string $level Niveau de log.
	 */
	public static function log_db_operation( $logger, $operation, $table, $data = array(), $level = 'info' ) {
		$message = sprintf( '[DB] %s - %s', strtoupper( $operation ), $table );
		$context = array(
			'channel'   => 'database',
			'operation' => $operation,
			'table'     => $table,
			'data'      => $data,
		);

		self::log( $level, $message, $context );
	}

	/**
	 * Log d'une erreur de formatage (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $formatter Nom du formateur.
	 * @param mixed  $input Données d'entrée.
	 * @param string $error_message Message d'erreur.
	 */
	public static function log_formatting_error( $logger, $formatter, $input, $error_message ) {
		$message = sprintf( '[FORMAT] Erreur dans %s', $formatter );
		$context = array(
			'channel'   => 'formatting',
			'formatter' => $formatter,
			'input'     => $input,
			'error'     => $error_message,
		);

		self::log( 'warning', $message, $context );
	}

	/**
	 * Log d'une opération de mapping (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $operation Type d'opération.
	 * @param string $source Source des données.
	 * @param string $target Cible du mapping.
	 * @param array  $data Données mappées.
	 * @param string $level Niveau de log.
	 */
	public static function log_mapping_operation( $logger, $operation, $source, $target, $data = array(), $level = 'info' ) {
		$message = sprintf( '[MAPPING] %s - %s -> %s', $operation, $source, $target );
		$context = array(
			'channel'   => 'mapping',
			'operation' => $operation,
			'source'    => $source,
			'target'    => $target,
			'data'      => $data,
		);

		self::log( $level, $message, $context );
	}

	/**
	 * Log de performance (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $operation Opération mesurée.
	 * @param float  $duration Durée en secondes.
	 * @param array  $context Contexte additionnel.
	 */
	public static function log_performance( $logger, $operation, $duration, $context = array() ) {
		$message = sprintf( '[PERF] %s - %.3fs', $operation, $duration );
		$context['channel']   = 'performance';
		$context['operation'] = $operation;
		$context['duration']  = $duration;

		self::log( 'info', $message, $context );
	}

	/**
	 * Log d'un hook WordPress (compatibilité)
	 *
	 * @param mixed  $logger Instance du logger (ignoré, on utilise error_log).
	 * @param string $hook Nom du hook.
	 * @param string $action Action (add, remove, do).
	 * @param array  $context Contexte additionnel.
	 * @param string $level Niveau de log.
	 */
	public static function log_wp_hook( $logger, $hook, $action, $context = array(), $level = 'info' ) {
		$message = sprintf( '[HOOK] %s - %s', $action, $hook );
		$context['channel'] = 'wordpress';
		$context['hook']    = $hook;
		$context['action']  = $action;

		self::log( $level, $message, $context );
	}
}