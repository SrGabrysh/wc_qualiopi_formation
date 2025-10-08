<?php
/**
 * MappingTabRenderer - Rendu de l'onglet Mapping
 *
 * @package WcQualiopiFormation\Admin\Settings
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Admin\AdminUi;
use WcQualiopiFormation\Form\FormManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MappingTabRenderer
 * Gère l'affichage de l'onglet Mapping avec sélection du formulaire
 */
class MappingTabRenderer {

	/**
	 * Instance du Form Manager
	 *
	 * @var FormManager
	 */
	private $form_manager;

/**
	 * Mapping par défaut (référence depuis FieldMapper)
	 *
	 * @var array
	 */
	private const DEFAULT_MAPPING = array(
		'siret'            => '1',     // SIRET formaté.
		'denomination'     => '12',    // Raison sociale.
		'adresse'          => '8.1',   // Numéro + voie.
		'code_postal'      => '8.5',   // Code postal.
		'ville'            => '8.3',   // Ville.
		'code_ape'         => '10',    // Code APE.
		'libelle_ape'      => '11',    // Libellé APE.
		'date_creation'    => '14',    // Date de création.
		'statut_actif'     => '15',    // Actif/Inactif.
		'mentions_legales' => '13',    // ⚠️ CRITIQUE : Mentions légales.
		'prenom'           => '7.3',   // Prénom représentant.
		'nom'              => '7.6',   // Nom représentant.
	);

	/**
	 * Labels des champs
	 *
	 * @var array
	 */
	private const FIELD_LABELS = array(
		'siret'            => 'SIRET',
		'denomination'     => 'Dénomination / Raison sociale',
		'adresse'          => 'Adresse (numéro et voie)',
		'code_postal'      => 'Code postal',
		'ville'            => 'Ville',
		'code_ape'         => 'Code APE',
		'libelle_ape'      => 'Libellé APE',
		'date_creation'    => 'Date de création',
		'statut_actif'     => 'Statut (Actif/Inactif)',
		'mentions_legales' => '⚠️ Mentions légales (CRITIQUE)',
		'prenom'           => 'Prénom du représentant',
		'nom'              => 'Nom du représentant',
	);

	/**
	 * Descriptions des champs
	 *
	 * @var array
	 */
	private const FIELD_DESCRIPTIONS = array(
		'siret'            => 'Champ où sera injecté le SIRET formaté (ex: 811 074 699 00034)',
		'denomination'     => 'Champ où sera injectée la raison sociale de l\'entreprise',
		'adresse'          => 'Champ pour l\'adresse (numéro et nom de voie, sans CP/Ville)',
		'code_postal'      => 'Champ pour le code postal',
		'ville'            => 'Champ pour la ville',
		'code_ape'         => 'Champ pour le code APE/NAF',
		'libelle_ape'      => 'Champ pour le libellé complet de l\'activité',
		'date_creation'    => 'Champ pour la date de création de l\'entreprise',
		'statut_actif'     => 'Champ indiquant si l\'entreprise est active ou inactive',
		'mentions_legales' => '⚠️ CRITIQUE : Champ HTML contenant les mentions légales générées automatiquement',
		'prenom'           => 'Champ pour le prénom du représentant légal',
		'nom'              => 'Champ pour le nom du représentant légal',
	);

	/**
	 * Constructeur
	 *
	 * @param FormManager $form_manager Instance du Form Manager.
	 */
	public function __construct( $form_manager ) {
		$this->form_manager = $form_manager;
	}

	/**
	 * Affiche le contenu de l'onglet Mapping
	 *
	 * @return void
	 */
	public function render() {
		// Récupérer les settings
		$settings = \get_option( Constants::OPTION_SETTINGS, array() );
		$form_mappings = $settings['form_mappings'] ?? array();

		// Formulaire sélectionné (par défaut : 1)
		$selected_form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 1;

		// Mapping actuel pour ce formulaire (ou DEFAULT si non configuré)
		$current_mapping = $form_mappings[ $selected_form_id ] ?? self::DEFAULT_MAPPING;

		?>
		<div class="wcqf-settings-section">
			<?php echo AdminUi::section_start( \esc_html__( 'Mapping des champs Gravity Forms', Constants::TEXT_DOMAIN ) ); ?>
			
			<div class="wcqf-mapping-header">
				<h3>📋 Configuration du mapping</h3>
				<p class="description">
					<?php \esc_html_e( 'Configurez le mapping entre les champs de vos formulaires Gravity Forms et les données de l\'API SIREN.', Constants::TEXT_DOMAIN ); ?>
				</p>
				<p class="description">
					<strong>⚠️ Important :</strong> Le mapping par défaut correspond au <strong>Formulaire ID 1</strong>. 
					Si vous utilisez plusieurs formulaires, configurez le mapping pour chacun individuellement.
				</p>
			</div>

			<!-- Sélecteur de formulaire -->
			<div class="wcqf-form-selector">
				<h4>🎯 Sélectionner un formulaire</h4>
				<?php $this->render_form_selector( $selected_form_id ); ?>
			</div>

			<!-- Mapping des champs -->
			<h4>🔗 Configuration des champs</h4>
			<table class="form-table" role="presentation">
				<tbody>
					<?php $this->render_mapping_fields( $selected_form_id, $current_mapping ); ?>
				</tbody>
			</table>

			<!-- Bouton pour réinitialiser au mapping par défaut -->
			<div class="wcqf-reset-section">
				<h4>🔄 Réinitialiser</h4>
				<p>
					<?php \esc_html_e( 'Pour revenir au mapping par défaut (Formulaire ID 1), cliquez sur le bouton ci-dessous.', Constants::TEXT_DOMAIN ); ?>
				</p>
				<button type="button" class="button" onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mapping pour ce formulaire ?') && document.getElementById('reset_mapping_<?php echo \esc_attr( $selected_form_id ); ?>').value = '1' && this.form.submit();">
					🔄 Réinitialiser au mapping par défaut
				</button>
				<input type="hidden" id="reset_mapping_<?php echo \esc_attr( $selected_form_id ); ?>" name="wcqf_settings[form_mappings][<?php echo \esc_attr( $selected_form_id ); ?>][_reset]" value="0">
			</div>

			<?php echo AdminUi::section_end(); ?>
		</div>
		<?php
	}

	/**
	 * Affiche le sélecteur de formulaire Gravity Forms
	 *
	 * @param int $selected_form_id ID du formulaire sélectionné.
	 * @return void
	 */
	private function render_form_selector( $selected_form_id ) {
		if ( ! class_exists( '\\GFAPI' ) ) {
			echo '<p class="notice notice-error">';
			\esc_html_e( '⚠️ Gravity Forms n\'est pas installé ou activé.', Constants::TEXT_DOMAIN );
			echo '</p>';
			return;
		}

		$forms = \GFAPI::get_forms();
		
		if ( empty( $forms ) ) {
			echo '<p class="notice notice-warning">';
			\esc_html_e( '⚠️ Aucun formulaire Gravity Forms trouvé. Créez un formulaire avant de configurer le mapping.', Constants::TEXT_DOMAIN );
			echo '</p>';
			return;
		}

		echo '<select name="form_id" id="wcqf_form_selector" onchange="window.location.href=\'?page=wcqf-settings&tab=mapping&form_id=\' + this.value;">';
		
		foreach ( $forms as $form ) {
			printf(
				'<option value="%d" %s>%s (ID: %d)</option>',
				(int) $form['id'],
				selected( $selected_form_id, $form['id'], false ),
				\esc_html( $form['title'] ),
				(int) $form['id']
			);
		}
		
		echo '</select>';
	}

	/**
	 * Affiche les champs de mapping pour un formulaire
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $current_mapping Mapping actuel.
	 * @return void
	 */
	private function render_mapping_fields( $form_id, $current_mapping ) {
		foreach ( self::DEFAULT_MAPPING as $field_key => $default_value ) {
			$label = self::FIELD_LABELS[ $field_key ] ?? $field_key;
			$description = self::FIELD_DESCRIPTIONS[ $field_key ] ?? '';
			$current_value = $current_mapping[ $field_key ] ?? $default_value;

			// Nom du champ pour la sauvegarde
			$field_name = sprintf( 'wcqf_settings[form_mappings][%d][%s]', $form_id, $field_key );

			?>
			<tr>
				<th scope="row">
					<label for="wcqf_mapping_<?php echo \esc_attr( $field_key ); ?>">
						<?php echo \esc_html( $label ); ?>
					</label>
				</th>
				<td>
					<input 
						type="text" 
						id="wcqf_mapping_<?php echo \esc_attr( $field_key ); ?>" 
						name="<?php echo \esc_attr( $field_name ); ?>" 
						value="<?php echo \esc_attr( $current_value ); ?>" 
						class="regular-text"
						placeholder="<?php echo \esc_attr( $default_value ); ?>"
					/>
					<p class="description">
						<?php echo \esc_html( $description ); ?>
						<br>
						<strong>Valeur par défaut :</strong> <code><?php echo \esc_html( $default_value ); ?></code>
						<?php if ( $field_key === 'mentions_legales' ) : ?>
							<br>
							<span class="wcqf-mapping-warning">⚠️ <strong>ATTENTION :</strong> Ce champ doit être un champ HTML dans Gravity Forms. Il contiendra les mentions légales complètes.</span>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<?php
		}
	}
}
