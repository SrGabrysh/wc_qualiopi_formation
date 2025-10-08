<?php
/**
 * LogsTabRenderer - Rendu de l'onglet Logs dans la page de configuration
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SecurityHelper;
use WcQualiopiFormation\Admin\Logs\LogsDataProvider;
use WcQualiopiFormation\Admin\Logs\LogsActionHandler;
use WcQualiopiFormation\Admin\Logs\LogsFilterManager;
use WcQualiopiFormation\Admin\Logs\LogsFilterRenderer;
use WcQualiopiFormation\Admin\AdminUi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsTabRenderer
 * Gère l'affichage de l'onglet Logs (orchestration uniquement)
 * RESPONSABILITÉ UNIQUE : Rendu de l'interface utilisateur
 */
class LogsTabRenderer {

/**
	 * Instance du data provider
	 *
	 * @var LogsDataProvider
	 */
	private $data_provider;

	/**
	 * Instance du action handler
	 *
	 * @var LogsActionHandler
	 */
	private $action_handler;

	/**
	 * Instance du filter manager
	 *
	 * @var LogsFilterManager
	 */
	private $filter_manager;

	/**
	 * Instance du filter renderer
	 *
	 * @var LogsFilterRenderer
	 */
	private $filter_renderer;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->data_provider = new LogsDataProvider();
		$this->filter_manager = new LogsFilterManager();
		$this->action_handler = new LogsActionHandler( $this->data_provider, $this->filter_manager );
		$this->filter_renderer = new LogsFilterRenderer( $this->filter_manager );
	}

	/**
	 * Initialise l'onglet et traite les actions AVANT le rendering
	 * [AJOUT 2025-10-07] Doit être appelé AVANT render() pour éviter d'envoyer du HTML avant les headers d'export
	 *
	 * @return void
	 */
	public function init() {
		// Traiter les actions (export, clear) AVANT que le HTML ne commence
		$this->action_handler->maybe_handle_actions();
	}

	/**
	 * Affiche le contenu de l'onglet Logs
	 *
	 * @return void
	 */
	public function render() {
		// Vérifier les permissions
		if ( ! SecurityHelper::check_admin_capability() ) {
			\wp_die( \esc_html__( 'Vous n\'avez pas les permissions nécessaires.', Constants::TEXT_DOMAIN ) );
		}

		// Récupérer les paramètres de filtres
		$filter_params = $this->filter_manager->get_filter_params();

		// Récupérer les logs avec la limite demandée
		$logs = $this->data_provider->get_logs( $filter_params['limit'] );

		// Appliquer le filtre de date
		$logs = $this->filter_manager->apply_date_filter( $logs, $filter_params['date_filter'] );

		// Calculer les statistiques
		$stats = $this->filter_manager->get_filter_stats( $logs );

		?>
		<div class="wrap wcqf-logs-container">
			<h1><?php \esc_html_e( 'Logs du plugin', Constants::TEXT_DOMAIN ); ?></h1>

			<?php $this->render_settings_errors(); ?>

			<!-- Filtres -->
			<div class="wcqf-settings-section">
				<?php echo AdminUi::section_start( \esc_html__( 'Filtres', Constants::TEXT_DOMAIN ) ); ?>
				<?php $this->filter_renderer->render( $filter_params, $stats ); ?>
				<?php echo AdminUi::section_end(); ?>
			</div>

			<!-- Actions -->
			<div class="wcqf-settings-section">
				<?php echo AdminUi::section_start( \esc_html__( 'Actions sur les logs', Constants::TEXT_DOMAIN ) ); ?>
				
				<div class="wcqf-logs-actions">
					<?php $this->render_action_buttons(); ?>
				</div>

				<?php echo AdminUi::section_end(); ?>
			</div>

			<!-- Tableau des logs -->
			<div class="wcqf-settings-section">
				<?php echo AdminUi::section_start( \esc_html__( 'Logs récents', Constants::TEXT_DOMAIN ) ); ?>
				
				<?php if ( empty( $logs ) ) : ?>
					<p><?php \esc_html_e( 'Aucun log disponible.', Constants::TEXT_DOMAIN ); ?></p>
				<?php else : ?>
					<?php $this->render_logs_table( $logs ); ?>
				<?php endif; ?>

				<?php echo AdminUi::section_end(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Affiche les messages d'erreur/succès
	 *
	 * @return void
	 */
	private function render_settings_errors() {
		$errors = $this->action_handler->get_settings_errors();
		if ( ! empty( $errors ) ) {
			\settings_errors( 'wcqf_logs' );
		}
	}

	/**
	 * Affiche les boutons d'action
	 *
	 * @return void
	 */
	private function render_action_buttons() {
		?>
		<div class="wcqf-logs-actions-buttons">
			<form method="post">
				<?php \wp_nonce_field( 'wcqf_admin_action', '_wpnonce' ); ?>
				<input type="hidden" name="wcqf_logs_action" value="clear_logs">
				<button type="submit" class="button button-secondary" 
						onclick="return confirm('<?php \esc_attr_e( 'Êtes-vous sûr de vouloir supprimer tous les logs ?', Constants::TEXT_DOMAIN ); ?>')">
					<?php \esc_html_e( 'Vider les logs', Constants::TEXT_DOMAIN ); ?>
				</button>
			</form>

			<form method="post">
				<?php \wp_nonce_field( 'wcqf_admin_action', '_wpnonce' ); ?>
				<input type="hidden" name="wcqf_logs_action" value="export_logs">
				<?php echo AdminUi::button_primary( \esc_html__( 'Exporter les logs', Constants::TEXT_DOMAIN ) ); ?>
			</form>
		</div>

		<?php if ( $this->data_provider->log_file_exists() ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: taille du fichier */
					\esc_html__( 'Taille du fichier de log : %s', Constants::TEXT_DOMAIN ),
					\esc_html( $this->data_provider->format_file_size( $this->data_provider->get_log_file_size() ) )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Affiche le tableau des logs
	 *
	 * @param array $logs Liste des logs à afficher.
	 * @return void
	 */
	private function render_logs_table( $logs ) {
		?>
		<div class="wcqf-logs-table-container">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php \esc_html_e( 'Date/Heure', Constants::TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Niveau', Constants::TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Message', Constants::TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Contexte', Constants::TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo \esc_html( $this->format_timestamp( $log['timestamp'] ) ); ?></td>
							<td>
								<span class="wcqf-log-level wcqf-log-level-<?php echo \esc_attr( $log['level'] ); ?>">
									<?php echo \esc_html( strtoupper( $log['level'] ) ); ?>
								</span>
							</td>
							<td><?php echo \esc_html( $log['message'] ); ?></td>
							<td>
								<?php if ( ! empty( $log['context'] ) ) : ?>
									<details>
										<summary><?php \esc_html_e( 'Voir le contexte', Constants::TEXT_DOMAIN ); ?></summary>
										<pre><?php echo \esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT ) ); ?></pre>
									</details>
								<?php else : ?>
									<span class="description"><?php \esc_html_e( 'Aucun contexte', Constants::TEXT_DOMAIN ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Formate un timestamp
	 *
	 * @param string $timestamp Timestamp à formater.
	 * @return string Timestamp formaté.
	 */
	private function format_timestamp( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return \esc_html__( 'N/A', Constants::TEXT_DOMAIN );
		}

		// Essayer de parser le timestamp
		$date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $timestamp );
		if ( $date ) {
			return $date->format( 'd/m/Y H:i:s' );
		}

		// Fallback : afficher tel quel
		return \esc_html( $timestamp );
	}
}