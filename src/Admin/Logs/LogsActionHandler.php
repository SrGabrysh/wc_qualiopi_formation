<?php
/**
 * LogsActionHandler - Gestionnaire des actions sur les logs
 *
 * @package WcQualiopiFormation\Admin\Logs
 */

namespace WcQualiopiFormation\Admin\Logs;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Helpers\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsActionHandler
 * Gère les actions de suppression et d'export des logs
 */
class LogsActionHandler {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du data provider
	 *
	 * @var LogsDataProvider
	 */
	private $data_provider;

	/**
	 * Instance du filter manager
	 *
	 * @var LogsFilterManager
	 */
	private $filter_manager;

	/**
	 * Constructeur
	 *
	 * @param Logger            $logger Instance du logger.
	 * @param LogsDataProvider  $data_provider Instance du data provider.
	 * @param LogsFilterManager $filter_manager Instance du filter manager.
	 */
	public function __construct( Logger $logger, LogsDataProvider $data_provider, LogsFilterManager $filter_manager = null ) {
		$this->logger = $logger;
		$this->data_provider = $data_provider;
		$this->filter_manager = $filter_manager ?? new LogsFilterManager( $logger );
	}

	/**
	 * Traite les actions sur les logs
	 *
	 * @return void
	 */
	public function maybe_handle_actions() {
		if ( ! isset( $_POST['wcqf_logs_action'] ) ) {
			return;
		}

		$action = \sanitize_text_field( \wp_unslash( $_POST['wcqf_logs_action'] ) );

		switch ( $action ) {
			case 'clear_logs':
				$this->handle_clear_logs();
				break;
			case 'export_logs':
				$this->handle_export_logs();
				break;
		}
	}

	/**
	 * Gère la suppression des logs
	 *
	 * @return void
	 */
	private function handle_clear_logs() {
		// [CORRECTION 2025-10-07] Vérifier le nonce avec la nouvelle approche unifiée
		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'wcqf_admin_action' ) ) {
			\add_settings_error(
				'wcqf_logs',
				'nonce_error',
				\esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN )
			);
			return;
		}

		// [CORRECTION 2025-10-07] Vérifier les capabilities
		if ( ! \current_user_can( Constants::CAP_MANAGE_SETTINGS ) ) {
			\add_settings_error(
				'wcqf_logs',
				'capability_error',
				\esc_html__( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN )
			);
			return;
		}


		$log_file = $this->data_provider->get_log_file_path();

		if ( ! empty( $log_file ) && \file_exists( $log_file ) ) {
			// Créer une sauvegarde avant suppression
			$log_dir = \dirname( $log_file );
			$backup_file = $log_dir . '/wc-qualiopi-formation-backup-' . \date( 'Y-m-d-H-i-s' ) . '.log';
			\copy( $log_file, $backup_file );

			// Vider le fichier
			\file_put_contents( $log_file, '' );

			\add_settings_error(
				'wcqf_logs',
				'logs_cleared',
				\esc_html__( 'Les logs ont été supprimés avec succès.', Constants::TEXT_DOMAIN ),
				'success'
			);

			$this->logger->info( 'Logs cleared by admin', array( 'backup_file' => $backup_file ) );
		} else {
			\add_settings_error(
				'wcqf_logs',
				'no_logs',
				\esc_html__( 'Aucun fichier de log trouvé.', Constants::TEXT_DOMAIN )
			);
		}
	}

	/**
	 * Gère l'export des logs (avec filtres actifs)
	 *
	 * @return void
	 */
	public function handle_export_logs() {
		// [DEBUG 2025-10-07] Log pour comprendre si cette fonction est appelée
		$this->logger->info( '[LogsActionHandler] handle_export_logs appelé' );

		// [CORRECTION 2025-10-07] Vérifier le nonce avec la nouvelle approche unifiée
		$nonce = \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$nonce_valid = \wp_verify_nonce( $nonce, 'wcqf_admin_action' );

		$this->logger->debug( '[LogsActionHandler] Vérification nonce', array(
			'nonce_present' => ! empty( $nonce ),
			'nonce_valid' => $nonce_valid,
		) );

		if ( ! $nonce_valid ) {
			\add_settings_error(
				'wcqf_logs',
				'nonce_error',
				\esc_html__( 'Token de sécurité invalide.', Constants::TEXT_DOMAIN )
			);
			$this->logger->warning( '[LogsActionHandler] Export échoué : nonce invalide' );
			return;
		}

		// [CORRECTION 2025-10-07] Vérifier les capabilities
		if ( ! \current_user_can( Constants::CAP_MANAGE_SETTINGS ) ) {
			$this->logger->warning( '[LogsActionHandler] Export échoué : permissions insuffisantes' );
			\add_settings_error(
				'wcqf_logs',
				'capability_error',
				\esc_html__( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN )
			);
			return;
		}

		$this->logger->debug( '[LogsActionHandler] Permissions OK, récupération des logs' );

		// Récupérer les paramètres de filtres depuis l'URL
		$filter_params = $this->filter_manager->get_filter_params();

		// [CORRECTION 2025-10-07] Passer le limit des filtres à get_logs()
		// Récupérer les logs avec la limite configurée dans les filtres
		$logs = $this->data_provider->get_logs( $filter_params['limit'] );
		$this->logger->debug( '[LogsActionHandler] Logs récupérés avant filtres', array(
			'total_logs' => count( $logs ),
			'limit_requested' => $filter_params['limit'],
		) );

		$logs = $this->filter_manager->apply_date_filter( $logs, $filter_params['date_filter'] );
		$this->logger->debug( '[LogsActionHandler] Logs après filtre date', array(
			'total_logs' => count( $logs ),
			'date_filter' => $filter_params['date_filter'],
		) );
		
		if ( ! empty( $filter_params['level_filter'] ) ) {
			$logs = $this->filter_manager->apply_level_filter( $logs, $filter_params['level_filter'] );
			$this->logger->debug( '[LogsActionHandler] Logs après filtre level', array(
				'total_logs' => count( $logs ),
				'level_filter' => $filter_params['level_filter'],
			) );
		}

		if ( empty( $logs ) ) {
			$this->logger->warning( '[LogsActionHandler] Export échoué : aucun log après filtrage' );
			\add_settings_error(
				'wcqf_logs',
				'no_logs',
				\esc_html__( 'Aucun log à exporter avec les filtres actifs.', Constants::TEXT_DOMAIN )
			);
			return;
		}

		$this->logger->info( '[LogsActionHandler] Export en cours', array(
			'total_logs' => count( $logs ),
		) );

		// Générer le nom du fichier d'export (avec indication des filtres)
		$filename_parts = array( 'wc-qualiopi-formation-logs' );
		
		if ( 'all' !== $filter_params['date_filter'] ) {
			$filename_parts[] = $filter_params['date_filter'];
		}
		
		if ( ! empty( $filter_params['level_filter'] ) ) {
			$filename_parts[] = implode( '-', $filter_params['level_filter'] );
		}
		
		$filename_parts[] = \date( 'Y-m-d-H-i-s' );
		$export_filename = implode( '-', $filename_parts ) . '.log';

		// Convertir les logs en texte
		$log_content = $this->format_logs_for_export( $logs );

		// [DEBUG 2025-10-07] Log avant envoi des headers
		$this->logger->info( '[LogsActionHandler] Envoi des headers d\'export', array(
			'export_filename' => $export_filename,
			'total_logs' => count( $logs ),
			'content_length' => strlen( $log_content ),
		) );

		// Headers pour le téléchargement
		\header( 'Content-Type: text/plain; charset=utf-8' );
		\header( 'Content-Disposition: attachment; filename="' . $export_filename . '"' );
		\header( 'Content-Length: ' . strlen( $log_content ) );

		// Envoyer le contenu et terminer l'exécution
		echo $log_content;
		
		$this->logger->info(
			'[LogsActionHandler] Logs exportés avec succès',
			array(
				'export_filename' => $export_filename,
				'total_logs'      => count( $logs ),
				'filters_applied' => $filter_params,
			)
		);

		// Terminer l'exécution proprement
		exit;
	}

	/**
	 * Formate les logs pour l'export
	 *
	 * @param array $logs Liste des logs.
	 * @return string Logs formatés.
	 */
	private function format_logs_for_export( $logs ) {
		$output = array();
		
		foreach ( $logs as $log ) {
			$line = sprintf(
				'%s %s %s',
				$log['timestamp'],
				\strtoupper( $log['level'] ),
				$log['message']
			);
			
			if ( ! empty( $log['context'] ) ) {
				$line .= ' ' . \wp_json_encode( $log['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			
			$output[] = $line;
		}
		
		return implode( "\n", $output );
	}

	/**
	 * Vérifie si une action est en cours
	 *
	 * @param string $action Action à vérifier.
	 * @return bool True si l'action est en cours.
	 */
	public function is_action_in_progress( $action ) {
		return isset( $_POST['wcqf_logs_action'] ) && \sanitize_text_field( \wp_unslash( $_POST['wcqf_logs_action'] ) ) === $action;
	}

	/**
	 * Obtient les messages d'erreur/succès
	 *
	 * @return array Messages d'erreur.
	 */
	public function get_settings_errors() {
		return \get_settings_errors( 'wcqf_logs' );
	}
}
