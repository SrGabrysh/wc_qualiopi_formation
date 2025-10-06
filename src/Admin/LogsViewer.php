<?php
/**
 * LogsViewer - Visualisation des logs
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Helpers\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsViewer
 * Interface de visualisation des logs
 */
class LogsViewer {

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
	 * Affiche la page de visualisation des logs
	 *
	 * @return void
	 */
	public function render() {
		// Vérifier les permissions.
		if ( ! SecurityHelper::check_admin_capability() ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', Constants::TEXT_DOMAIN ) );
		}

		// Traiter les actions.
		$this->maybe_handle_actions();

		// Récupérer les logs.
		$logs = $this->get_logs();

		?>
		<div class="wrap wcqf-logs">
			<h1><?php esc_html_e( 'Logs - Qualiopi Formation', Constants::TEXT_DOMAIN ); ?></h1>

			<div class="wcqf-logs-actions">
				<form method="post" style="display:inline-block;">
					<?php wp_nonce_field( 'wcqf_clear_logs', 'wcqf_logs_nonce' ); ?>
					<button type="submit" name="wcqf_clear_logs" class="button" 
						onclick="return confirm('<?php esc_attr_e( 'Êtes-vous sûr de vouloir vider les logs ?', Constants::TEXT_DOMAIN ); ?>');">
						<?php esc_html_e( 'Vider les logs', Constants::TEXT_DOMAIN ); ?>
					</button>
				</form>

				<form method="post" style="display:inline-block; margin-left:10px;">
					<?php wp_nonce_field( 'wcqf_export_logs', 'wcqf_logs_export_nonce' ); ?>
					<button type="submit" name="wcqf_export_logs" class="button">
						<?php esc_html_e( 'Exporter les logs', Constants::TEXT_DOMAIN ); ?>
					</button>
				</form>

				<button type="button" id="wcqf-refresh-logs" class="button" style="margin-left:10px;">
					<?php esc_html_e( 'Rafraîchir', Constants::TEXT_DOMAIN ); ?>
				</button>
			</div>

			<?php if ( empty( $logs ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'Aucun log disponible.', Constants::TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>
				<div class="wcqf-logs-container">
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width:150px;"><?php esc_html_e( 'Date/Heure', Constants::TEXT_DOMAIN ); ?></th>
								<th style="width:80px;"><?php esc_html_e( 'Niveau', Constants::TEXT_DOMAIN ); ?></th>
								<th><?php esc_html_e( 'Message', Constants::TEXT_DOMAIN ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Actions', Constants::TEXT_DOMAIN ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<?php $this->render_log_row( $log ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.wcqf-logs-container {
			margin-top: 20px;
			background: #fff;
			border: 1px solid #ccd0d4;
			padding: 20px;
		}
		.wcqf-logs-actions {
			margin: 20px 0;
		}
		.wcqf-log-level {
			display: inline-block;
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
		}
		.wcqf-log-level-debug { background: #f0f0f1; color: #646970; }
		.wcqf-log-level-info { background: #d1ecf1; color: #0c5460; }
		.wcqf-log-level-warning { background: #fff3cd; color: #856404; }
		.wcqf-log-level-error { background: #f8d7da; color: #721c24; }
		.wcqf-log-context {
			font-family: monospace;
			font-size: 12px;
			background: #f6f7f7;
			padding: 10px;
			margin-top: 10px;
			max-height: 200px;
			overflow: auto;
		}
		</style>
		<?php
	}

	/**
	 * Affiche une ligne de log
	 *
	 * @param array $log Données du log.
	 * @return void
	 */
	private function render_log_row( $log ) {
		$level = $log['level'] ?? 'info';
		$message = $log['message'] ?? '';
		$context = $log['context'] ?? array();
		$time = $log['time'] ?? '';

		?>
		<tr>
			<td><?php echo esc_html( $time ); ?></td>
			<td>
				<span class="wcqf-log-level wcqf-log-level-<?php echo esc_attr( $level ); ?>">
					<?php echo esc_html( $level ); ?>
				</span>
			</td>
			<td>
				<strong><?php echo esc_html( $message ); ?></strong>
				<?php if ( ! empty( $context ) ) : ?>
					<div class="wcqf-log-context">
						<pre><?php echo esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</div>
				<?php endif; ?>
			</td>
			<td>
				<button type="button" class="button button-small" 
					onclick="this.parentElement.previousElementSibling.querySelector('.wcqf-log-context').style.display = 
					this.parentElement.previousElementSibling.querySelector('.wcqf-log-context').style.display === 'none' ? 'block' : 'none';">
					<?php esc_html_e( 'Détails', Constants::TEXT_DOMAIN ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Récupère les logs depuis le fichier
	 *
	 * @param int $limit Nombre de logs à récupérer.
	 * @return array Logs.
	 */
	private function get_logs( $limit = 100 ) {
		$log_file = $this->logger->get_log_file();

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( false === $lines ) {
			return array();
		}

		// Prendre les dernières lignes.
		$lines = array_slice( array_reverse( $lines ), 0, $limit );

		$logs = array();

		foreach ( $lines as $line ) {
			$log = $this->parse_log_line( $line );
			if ( $log ) {
				$logs[] = $log;
			}
		}

		return $logs;
	}

	/**
	 * Parse une ligne de log
	 *
	 * @param string $line Ligne brute.
	 * @return array|false Log parsé ou false.
	 */
	private function parse_log_line( $line ) {
		// Format attendu: [2024-10-04 12:34:56] LEVEL: Message {"context":"data"}
		if ( ! preg_match( '/^\[([^\]]+)\]\s+(\w+):\s+(.+)$/', $line, $matches ) ) {
			return false;
		}

		$time    = $matches[1];
		$level   = strtolower( $matches[2] );
		$rest    = $matches[3];

		// Extraire message et contexte JSON.
		$message = $rest;
		$context = array();

		if ( preg_match( '/^(.+?)\s+(\{.+\})$/', $rest, $parts ) ) {
			$message = $parts[1];
			$context = json_decode( $parts[2], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$context = array();
			}
		}

		return array(
			'time'    => $time,
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		);
	}

	/**
	 * Traite les actions (vider, exporter)
	 *
	 * @return void
	 */
	private function maybe_handle_actions() {
		// Vider les logs.
		if ( isset( $_POST['wcqf_clear_logs'] ) ) {
			check_admin_referer( 'wcqf_clear_logs', 'wcqf_logs_nonce' );

			$log_file = $this->logger->get_log_file();
			if ( file_exists( $log_file ) ) {
				file_put_contents( $log_file, '' );
			}

			add_settings_error(
				'wcqf_messages',
				'logs_cleared',
				__( 'Logs vidés avec succès.', Constants::TEXT_DOMAIN ),
				'success'
			);
		}

		// Exporter les logs.
		if ( isset( $_POST['wcqf_export_logs'] ) ) {
			check_admin_referer( 'wcqf_export_logs', 'wcqf_logs_export_nonce' );

			$log_file = $this->logger->get_log_file();
			if ( file_exists( $log_file ) ) {
				header( 'Content-Type: text/plain' );
				header( 'Content-Disposition: attachment; filename="wcqf-logs-' . date( 'Y-m-d-H-i-s' ) . '.log"' );
				readfile( $log_file );
				exit;
			}
		}
	}
}




