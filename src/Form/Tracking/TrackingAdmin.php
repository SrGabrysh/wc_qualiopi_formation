<?php
/**
 * TrackingAdmin - Interface admin pour le tracking
 *
 * @package WcQualiopiFormation\Form\Tracking
 */

namespace WcQualiopiFormation\Form\Tracking;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingAdmin
 * Affichage des données de tracking dans l'admin WordPress
 */
class TrackingAdmin {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du storage
	 *
	 * @var TrackingStorage
	 */
	private $storage;

	/**
	 * Constructeur
	 *
	 * @param Logger          $logger Instance du logger.
	 * @param TrackingStorage $storage Instance du storage.
	 */
	public function __construct( Logger $logger, TrackingStorage $storage ) {
		$this->logger  = $logger;
		$this->storage = $storage;
	}

	/**
	 * Affiche les données de tracking pour un formulaire
	 *
	 * @param int $form_id ID du formulaire.
	 * @return void
	 */
	public function render_form_tracking( $form_id ) {
		$entries = $this->storage->get_by_form( $form_id, 50 );

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Aucune soumission enregistrée.', Constants::TEXT_DOMAIN ) . '</p>';
			return;
		}

		?>
		<div class="wcqf-settings-section">
			<?php echo \WcQualiopiFormation\Admin\AdminUi::section_start( __( 'Historique des soumissions', Constants::TEXT_DOMAIN ) ); ?>
			
			<div class="wcqf-tracking-table-wrapper">
				<?php
				echo \WcQualiopiFormation\Admin\AdminUi::table_start( array(
					__( 'Date', Constants::TEXT_DOMAIN ),
					__( 'Utilisateur', Constants::TEXT_DOMAIN ),
					__( 'Token', Constants::TEXT_DOMAIN ),
					__( 'Entreprise', Constants::TEXT_DOMAIN ),
					__( 'Actions', Constants::TEXT_DOMAIN )
				) );
				
				foreach ( $entries as $entry ) {
					$this->render_tracking_row( $entry );
				}
				
				echo \WcQualiopiFormation\Admin\AdminUi::table_end();
				?>
			</div>

			<?php echo \WcQualiopiFormation\Admin\AdminUi::section_end(); ?>
		</div>
		<?php
	}

	/**
	 * Affiche une ligne de tracking
	 *
	 * @param object $entry Entrée de tracking.
	 * @return void
	 */
	private function render_tracking_row( $entry ) {
		$company_data = json_decode( $entry->data_company, true );
		$company_name = $company_data['denomination'] ?? $company_data['nom'] ?? '-';

		?>
		<tr>
			<td><?php echo esc_html( $entry->created_at ); ?></td>
			<td>
				<?php
				if ( $entry->user_id ) {
					$user = get_userdata( $entry->user_id );
					echo $user ? esc_html( $user->display_name ) : 'ID ' . esc_html( $entry->user_id );
				} else {
					esc_html_e( 'Invité', Constants::TEXT_DOMAIN );
				}
				?>
			</td>
			<td><code><?php echo esc_html( substr( $entry->token, 0, 16 ) . '...' ); ?></code></td>
			<td><?php echo esc_html( $company_name ); ?></td>
			<td>
				<?php echo \WcQualiopiFormation\Admin\AdminUi::button( 
					__( 'Détails', Constants::TEXT_DOMAIN ), 
					'secondary', 
					array( 
						'type' => 'button',
						'class' => 'button-small',
						'onclick' => 'wcqfShowTrackingDetails(' . esc_attr( $entry->id ) . ')'
					) 
				); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Affiche les détails d'une entrée (AJAX)
	 *
	 * @param int $entry_id ID de l'entrée.
	 * @return array Données formatées.
	 */
	public function get_entry_details( $entry_id ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$entry_id
			)
		);

		if ( ! $entry ) {
			return array(
				'success' => false,
				'message' => __( 'Entrée introuvable.', Constants::TEXT_DOMAIN ),
			);
		}

		return array(
			'success'       => true,
			'token'         => $entry->token,
			'form_id'       => $entry->form_id,
			'entry_id'      => $entry->entry_id,
			'created_at'    => $entry->created_at,
			'data_personal' => json_decode( $entry->data_personal, true ),
			'data_company'  => json_decode( $entry->data_company, true ),
			'data_test'     => json_decode( $entry->data_test, true ),
			'data_metadata' => json_decode( $entry->data_metadata, true ),
		);
	}

	/**
	 * Affiche les statistiques de tracking
	 *
	 * @return void
	 */
	public function render_stats() {
		$stats = $this->storage->get_global_stats();

		?>
		<div class="wcqf-settings-section">
			<?php echo \WcQualiopiFormation\Admin\AdminUi::section_start( __( 'Statistiques', Constants::TEXT_DOMAIN ) ); ?>
			
			<div class="wcqf-tracking-stats">
				<div class="wcqf-stat-box">
					<h3><?php echo esc_html( $stats['total'] ); ?></h3>
					<p><?php esc_html_e( 'Total', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				<div class="wcqf-stat-box">
					<h3><?php echo esc_html( $stats['today'] ); ?></h3>
					<p><?php esc_html_e( 'Aujourd\'hui', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				<div class="wcqf-stat-box">
					<h3><?php echo esc_html( $stats['week'] ); ?></h3>
					<p><?php esc_html_e( '7 derniers jours', Constants::TEXT_DOMAIN ); ?></p>
				</div>
				<div class="wcqf-stat-box">
					<h3><?php echo esc_html( $stats['month'] ); ?></h3>
					<p><?php esc_html_e( '30 derniers jours', Constants::TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<?php echo \WcQualiopiFormation\Admin\AdminUi::section_end(); ?>
		</div>
		<?php
	}
}




