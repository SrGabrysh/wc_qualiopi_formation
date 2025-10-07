<?php
/**
 * LogsDataProvider - Fournisseur de données pour les logs
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
 * Class LogsDataProvider
 * Gère la récupération et le parsing des logs
 */
class LogsDataProvider {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Récupère les logs récents
	 *
	 * @param int $limit Nombre maximum de logs à récupérer.
	 * @return array Liste des logs.
	 */
	public function get_logs( $limit = 100 ) {
		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$logs = array();
		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		// [CORRECTION 2025-10-07] file() peut retourner FALSE
		if ( false === $lines || empty( $lines ) ) {
			$this->logger->warning( '[LogsDataProvider] Impossible de lire le fichier de logs', array(
				'log_file' => $log_file,
				'file_exists' => file_exists( $log_file ),
				'is_readable' => is_readable( $log_file ),
				'filesize' => file_exists( $log_file ) ? filesize( $log_file ) : 0,
			) );
			return array();
		}

		$this->logger->debug( '[LogsDataProvider] Fichier de logs lu avec succès', array(
			'total_lines' => count( $lines ),
			'limit' => $limit,
		) );

		// Prendre les dernières lignes
		$lines = array_slice( $lines, -$limit );

		foreach ( $lines as $line ) {
			$parsed_log = $this->parse_log_line( $line );
			if ( $parsed_log ) {
				$logs[] = $parsed_log;
			}
		}

		return array_reverse( $logs );
	}

	/**
	 * Parse une ligne de log
	 *
	 * @param string $line Ligne de log à parser.
	 * @return array|null Données parsées ou null si échec.
	 */
	public function parse_log_line( $line ) {
		// Format WooCommerce : 2025-10-06T17:14:01+00:00 INFO [WCQF] Message {context}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2})\s+(\w+)\s+(.*)$/', $line, $matches ) ) {
			$timestamp = $matches[1];
			$level = strtolower( $matches[2] );
			$content = $matches[3];
			
			// Extraire le message et le contexte JSON si présent
			$message = $content;
			$context = array();
			
			// Chercher un JSON à la fin du message
			if ( preg_match( '/^(.*?)\s+(\{.+\})$/', $content, $content_matches ) ) {
				$message = $content_matches[1];
				$json_context = json_decode( $content_matches[2], true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$context = $json_context;
				}
			}
			
			return array(
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'context'   => $context,
			);
		}

		// Fallback: Parser l'ancien format
		if ( preg_match( '/^\[(.*?)\]\s+(.*?):\s+(.*)$/', $line, $matches ) ) {
			return array(
				'timestamp' => $matches[1],
				'level'     => $matches[2],
				'message'   => $matches[3],
				'context'   => array(),
			);
		}

		return null;
	}

	/**
	 * Obtient le chemin du fichier de log WooCommerce le plus récent
	 *
	 * @return string Chemin du fichier de log.
	 */
	public function get_log_file_path() {
		$log_dir = \WP_CONTENT_DIR . '/uploads/wc-logs/';
		
		if ( ! \is_dir( $log_dir ) ) {
			return '';
		}
		
		// Chercher tous les fichiers wc-qualiopi-formation-*.log
		$pattern = $log_dir . 'wc-qualiopi-formation-*.log';
		$files = \glob( $pattern );
		
		if ( empty( $files ) ) {
			return '';
		}
		
		// Trier par date de modification (le plus récent en premier)
		\usort( $files, function( $a, $b ) {
			return \filemtime( $b ) - \filemtime( $a );
		});
		
		// Retourner le fichier le plus récent
		return $files[0];
	}

	/**
	 * Lit le fichier de log complet
	 *
	 * @return string Contenu du fichier de log.
	 */
	public function read_log_file() {
		$log_file = $this->get_log_file_path();

		if ( empty( $log_file ) || ! file_exists( $log_file ) ) {
			return '';
		}

		return file_get_contents( $log_file );
	}

	/**
	 * Vérifie si le fichier de log existe
	 *
	 * @return bool True si le fichier existe.
	 */
	public function log_file_exists() {
		$log_file = $this->get_log_file_path();
		return ! empty( $log_file ) && file_exists( $log_file );
	}

	/**
	 * Obtient la taille du fichier de log
	 *
	 * @return int Taille en octets.
	 */
	public function get_log_file_size() {
		$log_file = $this->get_log_file_path();

		if ( empty( $log_file ) || ! file_exists( $log_file ) ) {
			return 0;
		}

		return filesize( $log_file );
	}

	/**
	 * Formate la taille du fichier
	 *
	 * @param int $size Taille en octets.
	 * @return string Taille formatée.
	 */
	public function format_file_size( $size ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$unit_index = 0;

		while ( $size >= 1024 && $unit_index < count( $units ) - 1 ) {
			$size /= 1024;
			$unit_index++;
		}

		return round( $size, 2 ) . ' ' . $units[ $unit_index ];
	}
}
