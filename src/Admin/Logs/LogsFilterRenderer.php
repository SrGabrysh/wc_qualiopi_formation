<?php
/**
 * LogsFilterRenderer - Rendu de l'interface utilisateur des filtres
 *
 * @package WcQualiopiFormation\Admin\Logs
 */

namespace WcQualiopiFormation\Admin\Logs;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Admin\AdminUi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LogsFilterRenderer
 * Gère l'affichage de l'interface des filtres
 * RESPONSABILITÉ UNIQUE : Rendu UI des filtres
 */
class LogsFilterRenderer {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

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
	 * @param LogsFilterManager $filter_manager Instance du filter manager.
	 */
	public function __construct( Logger $logger, LogsFilterManager $filter_manager ) {
		$this->logger = $logger;
		$this->filter_manager = $filter_manager;
	}

	/**
	 * Affiche la section des filtres
	 *
	 * @param array $filter_params Paramètres de filtres actifs.
	 * @param array $stats Statistiques des logs.
	 * @return void
	 */
	public function render( $filter_params, $stats ) {
		// Utiliser AdminUi pour le rendu unifié
		echo AdminUi::section_start( 'Filtres des logs' );
		
		// Formulaire de filtres
		?>
		<form method="get" id="wcqf-filter-form" class="wcqf-filter-form">
			<input type="hidden" name="page" value="wcqf-settings">
			<input type="hidden" name="tab" value="logs">
			
			<div class="wcqf-filter-row">
				<!-- Filtre Date -->
				<div class="wcqf-filter-group">
					<label for="wcqf_date_filter">
						<?php \esc_html_e( 'Période :', Constants::TEXT_DOMAIN ); ?>
					</label>
					<?php $this->render_date_dropdown( $filter_params['date_filter'] ); ?>
				</div>

				<!-- Filtre Limite -->
				<div class="wcqf-filter-group">
					<label for="wcqf_limit">
						<?php \esc_html_e( 'Nombre de logs :', Constants::TEXT_DOMAIN ); ?>
					</label>
					<?php $this->render_limit_dropdown( $filter_params['limit'] ); ?>
				</div>

				<!-- Filtre Niveau -->
				<div class="wcqf-filter-group wcqf-filter-levels">
					<label><?php \esc_html_e( 'Niveaux :', Constants::TEXT_DOMAIN ); ?></label>
					<?php $this->render_level_checkboxes( $filter_params['level_filter'] ); ?>
				</div>

				<!-- Boutons -->
				<div class="wcqf-filter-actions">
					<?php echo AdminUi::button_primary( \esc_html__( 'Appliquer', Constants::TEXT_DOMAIN ) ); ?>
					<?php echo AdminUi::button( \esc_html__( 'Réinitialiser', Constants::TEXT_DOMAIN ), 'secondary', array( 'href' => '?page=wcqf-settings&tab=logs' ) ); ?>
				</div>
			</div>
		</form>

		<!-- Statistiques -->
		<?php $this->render_stats( $stats, $filter_params ); ?>
		<?php
		
		echo AdminUi::section_end();

		$this->enqueue_filter_js( $filter_params );
	}

	/**
	 * Affiche le dropdown de sélection de période
	 *
	 * @param string $selected Période sélectionnée.
	 * @return void
	 */
	private function render_date_dropdown( $selected ) {
		$periods = $this->filter_manager->get_time_periods();
		?>
		<select name="wcqf_date_filter" id="wcqf_date_filter" class="regular-text">
			<?php foreach ( $periods as $value => $label ) : ?>
				<option value="<?php echo \esc_attr( $value ); ?>" <?php \selected( $selected, $value ); ?>>
					<?php echo \esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Affiche le dropdown de sélection de limite
	 *
	 * @param int $selected Limite sélectionnée.
	 * @return void
	 */
	private function render_limit_dropdown( $selected ) {
		$limits = $this->filter_manager->get_log_limits();
		?>
		<select name="wcqf_limit" id="wcqf_limit" class="regular-text">
			<?php foreach ( $limits as $value => $label ) : ?>
				<option value="<?php echo \esc_attr( $value ); ?>" <?php \selected( $selected, $value ); ?>>
					<?php echo \esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Affiche les checkboxes de sélection de niveau
	 *
	 * @param array $selected Niveaux sélectionnés.
	 * @return void
	 */
	private function render_level_checkboxes( $selected ) {
		$levels = $this->filter_manager->get_log_levels();
		?>
		<div class="wcqf-level-checkboxes">
			<?php foreach ( $levels as $value => $label ) : ?>
				<label class="wcqf-level-checkbox">
					<input 
						type="checkbox" 
						name="wcqf_level_filter[]" 
						value="<?php echo \esc_attr( $value ); ?>"
						class="wcqf-level-filter-checkbox"
						data-level="<?php echo \esc_attr( $value ); ?>"
						<?php \checked( in_array( $value, $selected, true ) ); ?>
					>
					<span><?php echo \esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Affiche les statistiques des logs
	 *
	 * @param array $stats Statistiques des logs.
	 * @param array $filter_params Paramètres de filtres actifs.
	 * @return void
	 */
	private function render_stats( $stats, $filter_params ) {
		?>
		<div class="wcqf-filter-stats">
			<div class="wcqf-stats-row">
				<div class="wcqf-stat-item wcqf-stat-total">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Total affiché :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-total-count"><?php echo \esc_html( $stats['total'] ); ?></span>
				</div>
				
				<?php if ( $stats['debug'] > 0 ) : ?>
				<div class="wcqf-stat-item wcqf-stat-debug" data-level="debug">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Debug :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-debug-count"><?php echo \esc_html( $stats['debug'] ); ?></span>
				</div>
				<?php endif; ?>
				
				<?php if ( $stats['info'] > 0 ) : ?>
				<div class="wcqf-stat-item wcqf-stat-info" data-level="info">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Info :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-info-count"><?php echo \esc_html( $stats['info'] ); ?></span>
				</div>
				<?php endif; ?>
				
				<?php if ( $stats['warning'] > 0 ) : ?>
				<div class="wcqf-stat-item wcqf-stat-warning" data-level="warning">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Warning :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-warning-count"><?php echo \esc_html( $stats['warning'] ); ?></span>
				</div>
				<?php endif; ?>
				
				<?php if ( $stats['error'] > 0 ) : ?>
				<div class="wcqf-stat-item wcqf-stat-error" data-level="error">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Error :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-error-count"><?php echo \esc_html( $stats['error'] ); ?></span>
				</div>
				<?php endif; ?>
				
				<?php if ( $stats['critical'] > 0 ) : ?>
				<div class="wcqf-stat-item wcqf-stat-critical" data-level="critical">
					<span class="wcqf-stat-label"><?php \esc_html_e( 'Critical :', Constants::TEXT_DOMAIN ); ?></span>
					<span class="wcqf-stat-value" id="wcqf-critical-count"><?php echo \esc_html( $stats['critical'] ); ?></span>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Injecte le JavaScript pour le filtrage temps réel
	 *
	 * @param array $filter_params Paramètres de filtres actifs.
	 * @return void
	 */
	private function enqueue_filter_js( $filter_params ) {
		// Le JavaScript est maintenant dans assets/js/admin.js
		// On peut ajouter des données PHP si nécessaire via wp_localize_script
		// dans le futur si besoin de passer des paramètres dynamiques
	}
}

