<?php
/**
 * TrackingTabRenderer - Rendu de l'onglet Suivi
 *
 * @package WcQualiopiFormation\Admin\Settings
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Admin\AdminUi;
use WcQualiopiFormation\Form\FormManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingTabRenderer
 * Gère l'affichage de l'onglet Suivi
 */
class TrackingTabRenderer {

	/**
	 * Instance du Form Manager
	 *
	 * @var FormManager
	 */
	private $form_manager;

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructeur
	 *
	 * @param FormManager $form_manager Instance du Form Manager.
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( FormManager $form_manager, Logger $logger ) {
		$this->form_manager = $form_manager;
		$this->logger = $logger;
	}

	/**
	 * Affiche le contenu de l'onglet Suivi
	 *
	 * @return void
	 */
	public function render() {
		$stats = $this->get_tracking_stats();

		?>
		<div class="wcqf-settings-section">
			<?php echo AdminUi::section_start( \esc_html__( 'Statistiques des validations SIREN', Constants::TEXT_DOMAIN ) ); ?>
			
			<p class="description">
				<?php \esc_html_e( 'Suivi des vérifications de numéros SIREN effectuées via les formulaires Gravity Forms.', Constants::TEXT_DOMAIN ); ?>
			</p>
			
			<div class="wcqf-stats-grid">
				<div class="wcqf-stat-box">
					<h3><?php echo \esc_html( $stats['total_submissions'] ?? 0 ); ?></h3>
					<p><?php \esc_html_e( 'Vérifications totales', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				
				<div class="wcqf-stat-box wcqf-stat-success">
					<h3><?php echo \esc_html( $stats['successful_validations'] ?? 0 ); ?></h3>
					<p><?php \esc_html_e( 'Validations réussies', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				
				<div class="wcqf-stat-box wcqf-stat-error">
					<h3><?php echo \esc_html( $stats['failed_validations'] ?? 0 ); ?></h3>
					<p><?php \esc_html_e( 'Validations échouées', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				
				<div class="wcqf-stat-box wcqf-stat-rate">
					<h3><?php echo \esc_html( $stats['success_rate'] ?? 0 ); ?>%</h3>
					<p><?php \esc_html_e( 'Taux de réussite', Constants::TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<?php echo AdminUi::section_end(); ?>
		</div>

		<div class="wcqf-settings-section">
			<?php echo AdminUi::section_start( \esc_html__( 'Dernières soumissions', Constants::TEXT_DOMAIN ) ); ?>
			
			<?php if ( empty( $stats['recent_submissions'] ) ) : ?>
				<p><?php \esc_html_e( 'Aucune soumission récente.', Constants::TEXT_DOMAIN ); ?></p>
			<?php else : ?>
				<?php 
				// Utiliser AdminUi pour le tableau
				echo AdminUi::table_start( array(
					\esc_html__( 'Date', Constants::TEXT_DOMAIN ),
					\esc_html__( 'Formulaire', Constants::TEXT_DOMAIN ),
					\esc_html__( 'SIREN', Constants::TEXT_DOMAIN ),
					\esc_html__( 'Statut', Constants::TEXT_DOMAIN )
				) );
				
				foreach ( $stats['recent_submissions'] as $submission ) {
					echo AdminUi::table_row( array(
						\esc_html( $submission['date'] ),
						\esc_html( $submission['form_title'] ),
						\esc_html( $submission['siren'] ),
						'<span class="wcqf-status wcqf-status-' . \esc_attr( $submission['status'] ) . '">' . 
						\esc_html( $submission['status_label'] ) . '</span>'
					) );
				}
				
				echo AdminUi::table_end();
				?>
			<?php endif; ?>

			<?php echo AdminUi::section_end(); ?>
		</div>
		<?php
	}

	/**
	 * Récupère les statistiques de suivi
	 *
	 * @return array Statistiques de suivi.
	 */
	private function get_tracking_stats() {
		// Récupérer les valeurs par défaut
		$default_stats = $this->get_default_stats();
		
		// Utiliser le FormManager pour récupérer les stats
		$tracking_manager = $this->form_manager->get_tracking_manager();
		
		if ( ! $tracking_manager ) {
			return $default_stats;
		}
		
		// Récupérer les stats depuis le TrackingManager et fusionner avec les valeurs par défaut
		$stats = $tracking_manager->get_stats();
		
		// Fusionner avec les valeurs par défaut pour éviter les clés manquantes
		return \array_merge( $default_stats, $stats );
	}

	/**
	 * Retourne les statistiques par défaut
	 *
	 * @return array Statistiques par défaut.
	 */
	private function get_default_stats() {
		return array(
			'total_submissions'      => 0,
			'successful_validations' => 0,
			'failed_validations'     => 0,
			'success_rate'           => 0,
			'recent_submissions'     => array(),
		);
	}

	/**
	 * Retourne le libellé d'un statut
	 *
	 * @param string $status Statut.
	 * @return string Libellé du statut.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'success' => \esc_html__( 'Succès', Constants::TEXT_DOMAIN ),
			'error' => \esc_html__( 'Erreur', Constants::TEXT_DOMAIN ),
			'pending' => \esc_html__( 'En attente', Constants::TEXT_DOMAIN ),
		);

		return $labels[ $status ] ?? \esc_html__( 'Inconnu', Constants::TEXT_DOMAIN );
	}
}
