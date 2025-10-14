<?php
/**
 * Rendu de l'onglet Test de positionnement
 *
 * @package WcQualiopiFormation\Admin\Settings
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Data\Store\PositioningConfigStore;
use WcQualiopiFormation\Admin\AdminUi;
use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de rendu de l'onglet Test de positionnement
 *
 * Responsabilité unique : Affichage interface configuration verdicts
 */
class PositioningTabRenderer {

	/**
	 * Instance PositioningConfigStore
	 *
	 * @var PositioningConfigStore
	 */
	private $config_store;

	/**
	 * Constructeur
	 *
	 * @param PositioningConfigStore $config_store Store de configuration.
	 */
	public function __construct( PositioningConfigStore $config_store ) {
		$this->config_store = $config_store;
	}

	/**
	 * Rendu complet de l'onglet
	 *
	 * @param array|null $editing_verdict Verdict en cours d'édition (null si ajout).
	 * @return void
	 */
	public function render( ?array $editing_verdict = null ): void {
		// Vérifier Gravity Forms
		if ( ! class_exists( 'GFForms' ) ) {
			echo AdminUi::notice(
				\__( 'Gravity Forms est requis pour configurer le test de positionnement.', Constants::TEXT_DOMAIN ),
				'error'
			);
			return;
		}

		// Déterminer le form_id actuel UNE SEULE FOIS
		$gravity_forms   = $this->get_gravity_forms_list();
		
		// Trouver le PREMIER VRAI formulaire (ID > 0), pas l'option "Sélectionnez"
		$default_form_id = 0;
		foreach ( $gravity_forms as $form_id => $form_title ) {
			if ( (int) $form_id > 0 ) {
				$default_form_id = (int) $form_id;
				break;
			}
		}
		
		// DEBUG: Log de tous les paramètres
		LoggingHelper::debug( '[PositioningTabRenderer] DEBUG form_id selection', array(
			'POST_wcqf_positioning_form_id' => isset( $_POST['wcqf_positioning_form_id'] ) ? $_POST['wcqf_positioning_form_id'] : 'NOT_SET',
			'editing_verdict_form_id' => ! empty( $editing_verdict['form_id'] ) ? $editing_verdict['form_id'] : 'NOT_SET',
			'default_form_id' => $default_form_id,
			'all_POST_keys' => array_keys( $_POST ),
			'gravity_forms_ids' => array_keys( $gravity_forms ),
		) );
		
		// Priorité : POST (action en cours) > verdict en édition > premier formulaire réel
		$current_form_id = 0;
		$source = 'NONE';
		
		if ( isset( $_POST['wcqf_positioning_form_id'] ) && (int) $_POST['wcqf_positioning_form_id'] > 0 ) {
			$current_form_id = (int) $_POST['wcqf_positioning_form_id'];
			$source = 'POST';
		} elseif ( ! empty( $editing_verdict['form_id'] ) && (int) $editing_verdict['form_id'] > 0 ) {
			$current_form_id = (int) $editing_verdict['form_id'];
			$source = 'EDITING_VERDICT';
		} elseif ( $default_form_id > 0 ) {
			$current_form_id = $default_form_id;
			$source = 'DEFAULT';
		}
		
		LoggingHelper::info( '[PositioningTabRenderer] Form ID sélectionné', array(
			'current_form_id' => $current_form_id,
			'source' => $source,
		) );

		echo '<div class="wrap wcqf-admin-wrap">';
		echo '<h1>' . \esc_html__( 'Configuration du Test de positionnement', Constants::TEXT_DOMAIN ) . '</h1>';

		// Message de succès si sauvegarde
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			echo AdminUi::notice(
				\__( 'Configuration enregistrée avec succès', Constants::TEXT_DOMAIN ),
				'success'
			);
		}

		// Formulaire
		echo '<form method="post" action="">';
		\wp_nonce_field( 'wcqf_save_positioning', 'wcqf_positioning_nonce' );

		// Sélection formulaire
		$this->render_form_selector( $current_form_id );

		// Liste des verdicts configurés
		$this->render_verdicts_list();

		// Formulaire ajout/modification verdict
		$this->render_verdict_form( $editing_verdict );

		// Configuration champs GF
		$this->render_fields_config( $current_form_id );

		// Bouton soumettre (avec formnovalidate pour éviter la validation du formulaire de verdicts)
		echo AdminUi::button_primary(
			\__( 'Enregistrer la configuration', Constants::TEXT_DOMAIN ),
			'wcqf_save_fields',
			array( 'formnovalidate' => 'formnovalidate' )
		);

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Section : Sélection du formulaire
	 *
	 * @param int $current_form_id ID du formulaire actuellement sélectionné.
	 * @return void
	 */
	private function render_form_selector( int $current_form_id ): void {
		// Récupérer les formulaires GF
		$gravity_forms = $this->get_gravity_forms_list();

		echo AdminUi::section_start(
			\__( 'Sélection du formulaire', Constants::TEXT_DOMAIN ),
			'positioning-form-select'
		);

		echo AdminUi::field_row(
			\__( 'Formulaire Gravity Forms', Constants::TEXT_DOMAIN ),
			AdminUi::select(
				'wcqf_positioning_form_id',
				$gravity_forms,
				$current_form_id
			),
			\__( 'Sélectionnez le formulaire de test de positionnement', Constants::TEXT_DOMAIN )
		);

		echo AdminUi::section_end();
	}

	/**
	 * Section : Liste des verdicts configurés
	 *
	 * @return void
	 */
	private function render_verdicts_list(): void {
		// Récupérer TOUS les verdicts de TOUS les formulaires configurés
		$all_configs   = $this->config_store->get_all_configs();
		$gravity_forms = $this->get_gravity_forms_list();
		$total_verdicts = 0;
		$verdicts_display = array();

		// Préparer les données d'affichage
		foreach ( $all_configs as $key => $config ) {
			if ( empty( $config['verdicts'] ) ) {
				continue;
			}

			$form_id   = $config['form_id'] ?? 0;
			$form_name = $gravity_forms[ $form_id ] ?? sprintf( 'ID: %d', $form_id );

			foreach ( $config['verdicts'] as $verdict ) {
				$verdicts_display[] = array(
					'form_id'   => $form_id,
					'form_name' => $form_name,
					'verdict'   => $verdict,
				);
				$total_verdicts++;
			}
		}

		// Si aucun verdict, ne rien afficher
		if ( $total_verdicts === 0 ) {
			return;
		}

		echo AdminUi::section_start(
			sprintf(
				\__( 'Verdicts configurés (%d)', Constants::TEXT_DOMAIN ),
				$total_verdicts
			),
			'positioning-verdicts-list'
		);

		// Table des verdicts
		echo AdminUi::table_start( array(
			\__( 'Formulaire', Constants::TEXT_DOMAIN ),
			\__( 'Clé', Constants::TEXT_DOMAIN ),
			\__( 'Verdict', Constants::TEXT_DOMAIN ),
			\__( 'Tranche', Constants::TEXT_DOMAIN ),
			\__( 'Actions', Constants::TEXT_DOMAIN ),
		) );

		foreach ( $verdicts_display as $item ) {
			$verdict = $item['verdict'];
			$range = sprintf(
				'%d - %d points',
				$verdict['score_min'],
				$verdict['score_max']
			);

			$actions = sprintf(
				'<button type="submit" name="wcqf_edit_verdict" value="%s" class="button button-small" formnovalidate>%s</button>
				<button type="submit" name="wcqf_delete_verdict" value="%s" class="button button-small button-link-delete" formnovalidate onclick="return confirm(\'%s\');">%s</button>',
				\esc_attr( $verdict['verdict_key'] ),
				\esc_html__( 'Modifier', Constants::TEXT_DOMAIN ),
				\esc_attr( $verdict['verdict_key'] ),
				\esc_js( \__( 'Êtes-vous sûr de vouloir supprimer ce verdict ?', Constants::TEXT_DOMAIN ) ),
				\esc_html__( 'Supprimer', Constants::TEXT_DOMAIN )
			);

			echo AdminUi::table_row( array(
				'<strong>' . \esc_html( $item['form_name'] ) . '</strong>',
				'<code>' . \esc_html( $verdict['verdict_key'] ) . '</code>',
				\esc_html( $verdict['verdict_text'] ),
				\esc_html( $range ),
				$actions,
			) );
		}

		echo AdminUi::table_end();

		echo AdminUi::section_end();
	}

	/**
	 * Section : Formulaire ajout/modification verdict
	 *
	 * @param array|null $editing Verdict en cours d'édition (null si ajout).
	 * @return void
	 */
	private function render_verdict_form( ?array $editing = null ): void {
		$is_editing = ! empty( $editing );
		$title      = $is_editing ? \__( 'Modifier le verdict', Constants::TEXT_DOMAIN ) : \__( 'Ajouter un verdict', Constants::TEXT_DOMAIN );

		echo AdminUi::section_start( $title, 'positioning-verdict-form' );

		// Champ caché pour le mode édition
		if ( $is_editing ) {
			echo '<input type="hidden" name="wcqf_editing_verdict_key" value="' . \esc_attr( $editing['verdict_key'] ) . '">';
		}

		// Clé du verdict (dropdown)
		$verdict_keys = array(
			''           => \__( '-- Sélectionnez un verdict --', Constants::TEXT_DOMAIN ),
			'admitted'   => \__( 'admitted (Admission directe)', Constants::TEXT_DOMAIN ),
			'reinforced' => \__( 'reinforced (Accompagnement renforcé)', Constants::TEXT_DOMAIN ),
			'refused'    => \__( 'refused (Refus)', Constants::TEXT_DOMAIN ),
		);

		$selected_key = $is_editing ? $editing['verdict_key'] : '';

		$verdict_key_tooltip = '<strong>Clés prédéfinies :</strong><br>'
			. '• <code>admitted</code> : Candidat admis directement<br>'
			. '• <code>reinforced</code> : Admission avec accompagnement<br>'
			. '• <code>refused</code> : Candidature refusée<br><br>'
			. 'Ces clés déterminent le style visuel du verdict (couleur, icône).';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Clé du verdict', Constants::TEXT_DOMAIN ),
			AdminUi::select( 'wcqf_verdict[key]', $verdict_keys, $selected_key, array( 'required' => 'required' ) ),
			\__( 'Identifiant technique du verdict', Constants::TEXT_DOMAIN ),
			$verdict_key_tooltip
		);

		// Texte du verdict
		$verdict_text = $is_editing ? $editing['verdict_text'] : '';

		$verdict_text_tooltip = '<strong>Placeholders disponibles :</strong><br>'
			. '• <code>{{prenom}}</code> : Prénom de l\'utilisateur<br>'
			. '• <code>{{score}}</code> : Score obtenu<br>'
			. '• <code>{{score_max}}</code> : Score maximum (20)<br><br>'
			. '<strong>Exemple :</strong><br>'
			. '"Félicitations <code>{{prenom}}</code> ! Vous avez obtenu <code>{{score}}</code>/<code>{{score_max}}</code> points."';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Texte du verdict', Constants::TEXT_DOMAIN ),
			'<textarea name="wcqf_verdict[text]" rows="4" class="large-text" required placeholder="' . \esc_attr__( 'Félicitations {{prenom}} ! Votre profil...', Constants::TEXT_DOMAIN ) . '">' . \esc_textarea( $verdict_text ) . '</textarea>',
			\__( 'Message personnalisé affiché à l\'utilisateur', Constants::TEXT_DOMAIN ),
			$verdict_text_tooltip
		);

		// Tranche de score
		$min_score = $is_editing ? $editing['score_min'] : 0;
		$max_score = $is_editing ? $editing['score_max'] : 20;

		$score_range_tooltip = '<strong>Configuration des tranches :</strong><br>'
			. '• Les tranches doivent couvrir <strong>TOUTE</strong> la plage de 0 à 20<br>'
			. '• Elles ne doivent <strong>PAS se chevaucher</strong><br>'
			. '• Min et Max sont <strong>inclus</strong><br><br>'
			. '<strong>Exemple pour 3 verdicts :</strong><br>'
			. '• Admis : 15-20 points<br>'
			. '• Renforcé : 10-14 points<br>'
			. '• Refusé : 0-9 points';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Tranche de score', Constants::TEXT_DOMAIN ),
			'<label>Min: <input type="number" name="wcqf_verdict[min]" value="' . \esc_attr( $min_score ) . '" min="0" max="20" style="width:80px" required></label>
			<label style="margin-left:15px">Max: <input type="number" name="wcqf_verdict[max]" value="' . \esc_attr( $max_score ) . '" min="0" max="20" style="width:80px" required></label>',
			\__( 'Score minimum et maximum pour ce verdict (valeurs incluses)', Constants::TEXT_DOMAIN ),
			$score_range_tooltip
		);

		// Bouton soumettre (change selon le mode)
		$button_label = $is_editing ? \__( 'Mettre à jour le verdict', Constants::TEXT_DOMAIN ) : \__( 'Ajouter ce verdict', Constants::TEXT_DOMAIN );
		echo AdminUi::button_primary( $button_label, 'wcqf_add_verdict' );

		echo AdminUi::section_end();
	}

	/**
	 * Section : Configuration des champs Gravity Forms
	 *
	 * @param int $current_form_id ID du formulaire actuellement sélectionné.
	 * @return void
	 */
	private function render_fields_config( int $current_form_id ): void {
		echo AdminUi::section_start(
			\__( 'Configuration des champs', Constants::TEXT_DOMAIN ),
			'positioning-fields-config'
		);

		// Template du titre de score
		$score_title_tooltip = '<strong>Texte du titre du score :</strong><br>'
			. 'Personnalisez le message affiché au-dessus du verdict.<br><br>'
			. '<strong>Placeholders disponibles :</strong><br>'
			. '• <code>{{prenom}}</code> : Prénom de l\'utilisateur<br>'
			. '• <code>{{score}}</code> : Score obtenu (ex: 19)<br><br>'
			. '<strong>Exemples :</strong><br>'
			. '• <code>Félicitations {{prenom}}, votre score : {{score}}/20</code><br>'
			. '• <code>Bonjour {{prenom}}, vous avez obtenu {{score}}/20 points</code>';

		// Récupérer la config actuelle
		$current_config  = $current_form_id > 0 ? $this->config_store->get_form_config( $current_form_id ) : null;
		$score_title     = $current_config['score_title_template'] ?? 'Félicitations {{prenom}}, votre score : {{score}}/20';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Titre du score', Constants::TEXT_DOMAIN ),
			'<input type="text" name="wcqf_fields[score_title_template]" value="' . \esc_attr( $score_title ) . '" class="large-text" placeholder="Félicitations {{prenom}}, votre score : {{score}}/20">',
			\__( 'Template du message de score (utilisez {{prenom}} et {{score}})', Constants::TEXT_DOMAIN ),
			$score_title_tooltip
		);

		// ID champ HTML résultat
		$result_field_tooltip = '<strong>Champ HTML :</strong><br>'
			. 'Ce champ affichera le résultat du test avec :<br>'
			. '• Le score obtenu<br>'
			. '• Le verdict personnalisé<br>'
			. '• L\'icône et la couleur appropriés<br><br>'
			. '<strong>Comment trouver l\'ID ?</strong><br>'
			. 'Dans Gravity Forms, éditez votre formulaire et notez l\'ID du champ HTML (généralement sur la page 3).';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Champ résultat (HTML)', Constants::TEXT_DOMAIN ),
			'<input type="number" name="wcqf_fields[result_field_id]" value="31" min="1" class="small-text">',
			\__( 'ID du champ HTML Gravity Forms où afficher le résultat', Constants::TEXT_DOMAIN ),
			$result_field_tooltip
		);

		// ID champ score
		$score_field_tooltip = '<strong>Champ Number avec formule :</strong><br>'
			. 'Ce champ doit contenir une formule de calcul qui additionne les points des réponses.<br><br>'
			. '<strong>Exemple de formule :</strong><br>'
			. '<code>{Question 1:2} + {Question 2:3} + ...</code><br><br>'
			. 'Ce champ doit être <strong>caché</strong> (l\'utilisateur ne le voit pas).';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Champ score (Number)', Constants::TEXT_DOMAIN ),
			'<input type="number" name="wcqf_fields[score_field_id]" value="27" min="1" class="small-text">',
			\__( 'ID du champ Number avec formule de calcul du score', Constants::TEXT_DOMAIN ),
			$score_field_tooltip
		);

		// ID sous-champ prénom
		$firstname_field_tooltip = '<strong>Sous-champ Name :</strong><br>'
			. 'Le champ "Name" de Gravity Forms est composé de plusieurs sous-champs :<br>'
			. '• <code>X.3</code> : Prénom (First Name)<br>'
			. '• <code>X.6</code> : Nom (Last Name)<br><br>'
			. '<strong>Exemple :</strong><br>'
			. 'Si votre champ Name a l\'ID <strong>7</strong>, le prénom sera <code>7.3</code>';

		echo AdminUi::field_row_with_tooltip(
			\__( 'Champ prénom (Name)', Constants::TEXT_DOMAIN ),
			'<input type="text" name="wcqf_fields[first_name_field_id]" value="7.3" class="small-text">',
			\__( 'ID du sous-champ contenant le prénom (format : X.3)', Constants::TEXT_DOMAIN ),
			$firstname_field_tooltip
		);

		echo AdminUi::section_end();
	}

	/**
	 * Récupère la liste des formulaires Gravity Forms
	 *
	 * @return array Liste des formulaires (id => titre).
	 */
	private function get_gravity_forms_list(): array {
		if ( ! class_exists( 'GFFormsModel' ) ) {
			return array( 0 => \__( 'Gravity Forms non disponible', Constants::TEXT_DOMAIN ) );
		}

		$forms = \GFFormsModel::get_forms();
		$list  = array( 0 => \__( 'Sélectionnez un formulaire', Constants::TEXT_DOMAIN ) );

		foreach ( $forms as $form ) {
			$list[ $form->id ] = sprintf(
				'#%d - %s',
				$form->id,
				$form->title
			);
		}

		return $list;
	}
}
