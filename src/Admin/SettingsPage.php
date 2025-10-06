<?php
/**
 * SettingsPage - Page de configuration du plugin
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Helpers\SecurityHelper;
use WcQualiopiFormation\Form\FormManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 * Gestion de la page de configuration
 */
class SettingsPage {

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
	 * @param Logger      $logger Instance du logger.
	 */
	public function __construct( FormManager $form_manager, Logger $logger ) {
		$this->form_manager = $form_manager;
		$this->logger       = $logger;
	}

	/**
	 * Affiche la page de configuration
	 *
	 * @return void
	 */
	public function render() {
		// Vérifier les permissions.
		if ( ! SecurityHelper::check_admin_capability() ) {
			wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires.', Constants::TEXT_DOMAIN ) );
		}

		// Traiter la sauvegarde si nécessaire.
		$this->maybe_save_settings();

		// Récupérer les paramètres actuels.
		$settings = get_option( 'wcqf_settings', array() );

		// Déterminer l'onglet actif.
		$active_tab = isset( $_GET['tab'] ) ? SanitizationHelper::sanitize_name( wp_unslash( $_GET['tab'] ) ) : 'general';

		?>
		<div class="wrap wcqf-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'wcqf_messages' ); ?>

			<nav class="nav-tab-wrapper">
				<a href="?page=wcqf-settings&tab=general" 
					class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Configuration générale', Constants::TEXT_DOMAIN ); ?>
				</a>
				<a href="?page=wcqf-settings&tab=mapping" 
					class="nav-tab <?php echo 'mapping' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Mapping des champs', Constants::TEXT_DOMAIN ); ?>
				</a>
				<a href="?page=wcqf-settings&tab=tracking" 
					class="nav-tab <?php echo 'tracking' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Suivi des soumissions', Constants::TEXT_DOMAIN ); ?>
				</a>
			</nav>

			<?php if ( 'tracking' === $active_tab ) : ?>
				<?php $this->render_tracking_tab(); ?>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'wcqf_save_settings', 'wcqf_settings_nonce' ); ?>

					<?php if ( 'general' === $active_tab ) : ?>
						<?php $this->render_general_tab( $settings ); ?>
					<?php elseif ( 'mapping' === $active_tab ) : ?>
						<?php $this->render_mapping_tab( $settings ); ?>
					<?php endif; ?>

					<p class="submit">
						<button type="submit" name="wcqf_save" class="button button-primary">
							<?php esc_html_e( 'Enregistrer les paramètres', Constants::TEXT_DOMAIN ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Affiche l'onglet de configuration générale
	 *
	 * @param array $settings Paramètres actuels.
	 * @return void
	 */
	private function render_general_tab( $settings ) {
		$api_key      = get_option( 'wcqf_siren_api_key', '' );
		$has_constant = defined( 'WCQF_SIREN_API_KEY' );

		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="api_key"><?php esc_html_e( 'Clé API SIREN', Constants::TEXT_DOMAIN ); ?></label>
					</th>
					<td>
						<?php if ( $has_constant ) : ?>
							<p class="description">
								<?php esc_html_e( '✅ La clé API est définie dans wp-config.php (recommandé)', Constants::TEXT_DOMAIN ); ?>
							</p>
						<?php else : ?>
							<input type="password" id="api_key" name="wcqf[api_key]" 
								value="<?php echo esc_attr( $api_key ); ?>" 
								class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Clé API pour accéder à l\'API SIREN', Constants::TEXT_DOMAIN ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Test de connexion', Constants::TEXT_DOMAIN ); ?>
					</th>
					<td>
						<button type="button" id="wcqf-test-api" class="button">
							<?php esc_html_e( 'Tester la connexion API', Constants::TEXT_DOMAIN ); ?>
						</button>
						<span id="wcqf-test-result"></span>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Cache', Constants::TEXT_DOMAIN ); ?>
					</th>
					<td>
						<button type="button" id="wcqf-clear-cache" class="button">
							<?php esc_html_e( 'Vider le cache SIREN', Constants::TEXT_DOMAIN ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Supprime toutes les données en cache de l\'API SIREN', Constants::TEXT_DOMAIN ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Affiche l'onglet de mapping des champs
	 *
	 * @param array $settings Paramètres actuels.
	 * @return void
	 */
	private function render_mapping_tab( $settings ) {
		$forms = $this->get_gravity_forms_list();

		?>
		<div class="wcqf-mapping-section">
			<h2><?php esc_html_e( 'Configuration du mapping des champs', Constants::TEXT_DOMAIN ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Sélectionnez le formulaire Gravity Forms à utiliser pour l\'autocomplete SIREN.', Constants::TEXT_DOMAIN ); ?>
			</p>

			<?php if ( empty( $forms ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Aucun formulaire Gravity Forms trouvé.', Constants::TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="gf_form_id"><?php esc_html_e( 'Formulaire Gravity Forms', Constants::TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<select name="wcqf[gf_form_id]" id="gf_form_id">
								<option value=""><?php esc_html_e( '-- Sélectionner --', Constants::TEXT_DOMAIN ); ?></option>
								<?php foreach ( $forms as $form ) : ?>
									<option value="<?php echo esc_attr( $form['id'] ); ?>"
										<?php selected( $settings['gf_form_id'] ?? '', $form['id'] ); ?>>
										<?php echo esc_html( $form['title'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Affiche l'onglet de tracking
	 *
	 * @return void
	 */
	private function render_tracking_tab() {
		$tracking_manager = $this->form_manager->get_tracking_manager();
		
		if ( $tracking_manager ) {
			$stats = $tracking_manager->get_stats();
			?>
			<div class="wcqf-tracking-stats">
				<h2><?php esc_html_e( 'Statistiques de tracking', Constants::TEXT_DOMAIN ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Total des soumissions : %d', Constants::TEXT_DOMAIN ), $stats['total'] ) ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Sauvegarde les paramètres
	 *
	 * @return void
	 */
	private function maybe_save_settings() {
		if ( ! isset( $_POST['wcqf_save'] ) ) {
			return;
		}

		check_admin_referer( 'wcqf_save_settings', 'wcqf_settings_nonce' );

		$settings = isset( $_POST['wcqf'] ) ? $_POST['wcqf'] : array();

		// Sanitize.
		$settings = array_map( array( 'WcQualiopiFormation\Helpers\SecurityHelper', 'sanitize_input' ), $settings );

		// Sauvegarder la clé API séparément.
		if ( isset( $settings['api_key'] ) && ! empty( $settings['api_key'] ) ) {
			update_option( 'wcqf_siren_api_key', $settings['api_key'] );
			unset( $settings['api_key'] );
		}

		// Sauvegarder les autres paramètres.
		update_option( 'wcqf_settings', $settings );

		add_settings_error(
			'wcqf_messages',
			'wcqf_message',
			__( 'Paramètres enregistrés avec succès.', Constants::TEXT_DOMAIN ),
			'success'
		);
	}

	/**
	 * Récupère la liste des formulaires Gravity Forms
	 *
	 * @return array Liste des formulaires.
	 */
	private function get_gravity_forms_list() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$forms = \GFAPI::get_forms();

		return array_map( function( $form ) {
			return array(
				'id'    => $form['id'],
				'title' => $form['title'],
			);
		}, $forms );
	}
}




