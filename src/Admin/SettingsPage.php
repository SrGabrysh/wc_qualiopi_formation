<?php
/**
 * SettingsPage - Page de configuration du plugin (orchestration uniquement)
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SecurityHelper;
use WcQualiopiFormation\Admin\Settings\GeneralTabRenderer;
use WcQualiopiFormation\Admin\Settings\MappingTabRenderer;
use WcQualiopiFormation\Admin\Settings\TrackingTabRenderer;
use WcQualiopiFormation\Admin\Settings\PositioningTabRenderer;
use WcQualiopiFormation\Admin\Settings\YousignTabRenderer;
use WcQualiopiFormation\Admin\Settings\PositioningSettingsSaver;
use WcQualiopiFormation\Admin\Settings\SettingsSaver;
use WcQualiopiFormation\Data\Store\PositioningConfigStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 * Orchestration de la page de configuration (responsabilité unique)
 */
class SettingsPage {

	/**
	 * Instance du Form Manager
	 *
	 * @var \WcQualiopiFormation\Form\FormManager
	 */
	private $form_manager;

/**
	 * Instance du renderer de logs
	 *
	 * @var LogsTabRenderer
	 */
	private $logs_renderer;

	/**
	 * Instance du renderer de l'onglet général
	 *
	 * @var GeneralTabRenderer
	 */
	private $general_renderer;

	/**
	 * Instance du renderer de l'onglet mapping
	 *
	 * @var MappingTabRenderer
	 */
	private $mapping_renderer;

	/**
	 * Instance du renderer de l'onglet tracking
	 *
	 * @var TrackingTabRenderer
	 */
	private $tracking_renderer;

	/**
	 * Instance du renderer de l'onglet positioning
	 *
	 * @var PositioningTabRenderer
	 */
	private $positioning_renderer;

	/**
	 * Instance du renderer de l'onglet yousign
	 *
	 * @var YousignTabRenderer
	 */
	private $yousign_renderer;

	/**
	 * Instance du gestionnaire de sauvegarde
	 *
	 * @var SettingsSaver
	 */
	private $settings_saver;

	/**
	 * Instance du gestionnaire de sauvegarde positioning
	 *
	 * @var PositioningSettingsSaver
	 */
	private $positioning_saver;

	/**
	 * Constructeur
	 *
	 * @param \WcQualiopiFormation\Form\FormManager $form_manager Instance du Form Manager.
	 */
	public function __construct( $form_manager ) {
		$this->form_manager = $form_manager;
		$this->logs_renderer = new LogsTabRenderer();
		$this->general_renderer = new GeneralTabRenderer();
		$this->mapping_renderer = new MappingTabRenderer( $form_manager );
		$this->tracking_renderer = new TrackingTabRenderer( $form_manager );
		$this->positioning_renderer = new PositioningTabRenderer( new PositioningConfigStore() );
		$this->yousign_renderer = new YousignTabRenderer();
		$this->settings_saver = new SettingsSaver();
		$this->positioning_saver = new PositioningSettingsSaver();
	}

	/**
	 * Affiche la page de configuration
	 *
	 * @return void
	 */
	public function render() {
		// Vérifier les permissions
		if ( ! SecurityHelper::check_admin_capability() ) {
			\wp_die( \esc_html__( 'Vous n\'avez pas les permissions nécessaires.', Constants::TEXT_DOMAIN ) );
		}

		// Déterminer l'onglet actif
		$active_tab = $this->get_active_tab();

		// [SUPPRIMÉ 2025-10-07] L'initialisation des logs est maintenant gérée par AdminManager::handle_early_actions()
		// via le hook admin_init, AVANT que cette méthode ne soit appelée

		// Traiter la sauvegarde des paramètres
		$this->settings_saver->maybe_save_settings();

		?>
		<div class="wrap wcqf-settings-page">
			<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>

			<?php \settings_errors( 'wcqf_settings' ); ?>

			<!-- Navigation par onglets -->
			<nav class="nav-tab-wrapper wcqf-nav-tabs">
				<a href="?page=wcqf-settings&tab=general" 
				   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php \esc_html_e( 'Configuration', Constants::TEXT_DOMAIN ); ?>
				</a>
				<a href="?page=wcqf-settings&tab=mapping" 
				   class="nav-tab <?php echo $active_tab === 'mapping' ? 'nav-tab-active' : ''; ?>">
					<?php \esc_html_e( 'Mapping', Constants::TEXT_DOMAIN ); ?>
				</a>
				<a href="?page=wcqf-settings&tab=tracking" 
				   class="nav-tab <?php echo $active_tab === 'tracking' ? 'nav-tab-active' : ''; ?>">
					<?php \esc_html_e( 'Statistiques', Constants::TEXT_DOMAIN ); ?>
				</a>
			<a href="?page=wcqf-settings&tab=positioning" 
			   class="nav-tab <?php echo $active_tab === 'positioning' ? 'nav-tab-active' : ''; ?>">
				<?php \esc_html_e( 'Test de positionnement', Constants::TEXT_DOMAIN ); ?>
			</a>
			<?php if ( \class_exists( 'GFAPI' ) ) : ?>
				<a href="?page=wcqf-settings&tab=yousign" 
				   class="nav-tab <?php echo $active_tab === 'yousign' ? 'nav-tab-active' : ''; ?>">
					<?php \esc_html_e( 'Yousign', Constants::TEXT_DOMAIN ); ?>
				</a>
			<?php endif; ?>
			<a href="?page=wcqf-settings&tab=logs" 
			   class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
				<?php \esc_html_e( 'Logs', Constants::TEXT_DOMAIN ); ?>
			</a>
			</nav>

			<!-- Contenu des onglets -->
			<div class="wcqf-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'general':
						$this->render_general_tab();
						break;
					case 'mapping':
						$this->render_mapping_tab();
						break;
					case 'tracking':
						$this->render_tracking_tab();
						break;
					case 'positioning':
						$this->render_positioning_tab();
						break;
					case 'yousign':
						$this->render_yousign_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					default:
						$this->render_general_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Affiche l'onglet Configuration
	 *
	 * @return void
	 */
	private function render_general_tab() {
		?>
		<form method="post" action="">
			<?php \wp_nonce_field( 'wcqf_save_settings', 'wcqf_settings_nonce' ); ?>
			
			<?php $this->general_renderer->render(); ?>
			
			<?php echo AdminUi::button_primary( \esc_html__( 'Sauvegarder les paramètres', Constants::TEXT_DOMAIN ), 'wcqf_save_settings' ); ?>
		</form>
		<?php
	}

	/**
	 * Affiche l'onglet Mapping
	 *
	 * @return void
	 */
	private function render_mapping_tab() {
		?>
		<form method="post" action="">
			<?php \wp_nonce_field( 'wcqf_save_settings', 'wcqf_settings_nonce' ); ?>
			
			<?php $this->mapping_renderer->render(); ?>
			
			<?php echo AdminUi::button_primary( \esc_html__( 'Sauvegarder le mapping', Constants::TEXT_DOMAIN ), 'wcqf_save_settings' ); ?>
		</form>
		<?php
	}

	/**
	 * Affiche l'onglet Suivi
	 *
	 * @return void
	 */
	private function render_tracking_tab() {
		$this->tracking_renderer->render();
	}

	/**
	 * Affiche l'onglet Test de positionnement
	 *
	 * @return void
	 */
	private function render_positioning_tab() {
		$editing_verdict = null;
		
		// Traiter la sauvegarde si demandée
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$result = $this->positioning_saver->save_config();
			if ( \is_wp_error( $result ) ) {
				echo AdminUi::notice( $result->get_error_message(), 'error' );
			} elseif ( $result === true ) {
				echo AdminUi::notice(
					\__( 'Configuration enregistrée avec succès', Constants::TEXT_DOMAIN ),
					'success'
				);
			}
		}
		
		// Récupérer le verdict à éditer si demandé
		if ( isset( $_POST['wcqf_edit_verdict'] ) ) {
			$editing_verdict = $this->positioning_saver->get_verdict_for_edit( 
				\sanitize_text_field( \wp_unslash( $_POST['wcqf_edit_verdict'] ) )
			);
		}

		// Afficher l'onglet
		$this->positioning_renderer->render( $editing_verdict );
	}

	/**
	 * Affiche l'onglet Yousign
	 *
	 * @return void
	 */
	private function render_yousign_tab() {
		// Vérifier que Gravity Forms est actif
		if ( ! \class_exists( 'GFAPI' ) ) {
			LoggingHelper::warning( '[SettingsPage] Tentative accès onglet Yousign sans Gravity Forms' );
			echo AdminUi::notice(
				\__( 'Gravity Forms doit être actif pour accéder à cet onglet.', Constants::TEXT_DOMAIN ),
				'error'
			);
			return;
		}

		?>
		<form method="post" action="" enctype="multipart/form-data">
			<?php \wp_nonce_field( 'wcqf_save_settings', 'wcqf_settings_nonce' ); ?>
			
			<?php $this->yousign_renderer->render(); ?>
			
			<?php echo AdminUi::button_primary( \esc_html__( 'Sauvegarder les configurations', Constants::TEXT_DOMAIN ), 'wcqf_save_settings' ); ?>
		</form>
		<?php
	}

	/**
	 * Affiche l'onglet Logs
	 *
	 * @return void
	 */
	private function render_logs_tab() {
		$this->logs_renderer->render();
	}

	/**
	 * Détermine l'onglet actif
	 *
	 * @return string Onglet actif.
	 */
	private function get_active_tab() {
		$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'general' ) );
		$allowed_tabs = array( 'general', 'mapping', 'tracking', 'positioning', 'logs' );
		
		// Ajouter l'onglet Yousign uniquement si Gravity Forms est actif
		if ( \class_exists( 'GFAPI' ) ) {
			$allowed_tabs[] = 'yousign';
		}
		
		return in_array( $tab, $allowed_tabs, true ) ? $tab : 'general';
	}
}