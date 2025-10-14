<?php
/**
 * Injecteur de résultats de test de positionnement
 *
 * @package WcQualiopiFormation\Form\GravityForms
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

use WcQualiopiFormation\Data\Store\PositioningConfigStore;
use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe d'injection des résultats dans le champ HTML
 *
 * Responsabilité unique : Afficher le résultat du test dans le champ HTML ID 31
 */
class ResultsInjector {

	/**
	 * Instance PositioningHelper
	 *
	 * @var PositioningHelper
	 */
	private $positioning_helper;

	/**
	 * Instance PositioningConfigStore
	 *
	 * @var PositioningConfigStore
	 */
	private $config_store;

	/**
	 * Constructeur
	 *
	 * @param PositioningHelper      $helper Instance du helper.
	 * @param PositioningConfigStore $store  Instance du store.
	 */
	public function __construct( PositioningHelper $helper, PositioningConfigStore $store ) {
		$this->positioning_helper = $helper;
		$this->config_store       = $store;
	}

	/**
	 * Initialise les hooks WordPress/Gravity Forms
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		// Écouter l'action déclenché par PageTransitionHandler
		add_action( 'wcqf_test_completed', array( $this, 'store_result' ), 10, 4 );

		// Hook pour injecter le HTML dans le champ
		add_filter( 'gform_field_content', array( $this, 'inject_result_field' ), 10, 5 );

		LoggingHelper::info( '[ResultsInjector] Hooks initialisés' );
	}

	/**
	 * Stocke le résultat en session quand le test est complété
	 *
	 * @param float $score           Score calculé.
	 * @param string $path           Parcours déterminé ('refused', 'reinforced', 'admitted').
	 * @param array  $submission     Données de soumission.
	 * @param array  $form           Formulaire Gravity Forms.
	 * @return void
	 */
	public function store_result( float $score, string $path, array $submission, array $form ): void {
		$form_id = (int) $form['id'];

		// Déterminer le verdict via le helper
		$verdict_data = $this->positioning_helper->determine_verdict( $score, $form_id );

		// Stocker en session
		$session_key = 'test_result_' . $form_id;
		$data        = array(
			'score'     => $score,
			'path'      => $path,
			'verdict'   => $verdict_data['verdict'],
			'text'      => $verdict_data['text'],
			'timestamp' => time(),
		);

		SessionManager::set( $session_key, $data, Constants::SESSION_TTL_MINUTES * MINUTE_IN_SECONDS );

		LoggingHelper::info( '[ResultsInjector] Résultat stocké en session', array(
			'form_id'     => $form_id,
			'score'       => $score,
			'path'        => $path,
			'verdict'     => $verdict_data['verdict'],
			'session_key' => $session_key,
		) );
	}

	/**
	 * Injecte le résultat dans le champ HTML
	 *
	 * Hook : gform_field_content
	 *
	 * @param string $field_content  Contenu du champ actuel.
	 * @param object $field          Objet champ Gravity Forms.
	 * @param mixed  $value          Valeur du champ.
	 * @param int    $entry_id       ID de l'entrée (0 si soumission en cours).
	 * @param int    $form_id        ID du formulaire.
	 * @return string Contenu du champ (modifié si champ de résultat).
	 */
	public function inject_result_field( $field_content, $field, $value, $entry_id, $form_id ) {
		// Vérifier que c'est un formulaire configuré
		$config = $this->config_store->get_form_config( $form_id );
		if ( ! $config ) {
			return $field_content;
		}

		// Vérifier que c'est le bon champ (HTML configuré pour les résultats)
		$result_field_id = $config['result_field_id'] ?? 0;
		if ( (int) $field->id !== (int) $result_field_id ) {
			return $field_content;
		}

		// Récupérer le résultat depuis la session
		$result = $this->get_stored_result( $form_id );

		if ( ! $result ) {
			LoggingHelper::debug( '[ResultsInjector] Aucun résultat en session', array(
				'form_id'  => $form_id,
				'field_id' => $field->id,
			) );
			return $field_content;
		}

		// Extraire le prénom depuis l'entry
		$first_name = $this->extract_first_name( $form_id, $config );

		// Générer le HTML
		$html = $this->format_result_html(
			$form_id,
			$result['score'],
			$result['verdict'],
			$result['text'],
			$first_name
		);

		LoggingHelper::info( '[ResultsInjector] Résultat affiché avec succès', array(
			'form_id'    => $form_id,
			'field_id'   => $field->id,
			'score'      => $result['score'],
			'verdict'    => $result['verdict'],
			'first_name' => $first_name,
		) );

		return $html;
	}

	/**
	 * Récupère le résultat depuis la session
	 *
	 * @param int $form_id ID du formulaire.
	 * @return array|null Données du résultat ou null.
	 */
	private function get_stored_result( int $form_id ): ?array {
		$session_key = 'test_result_' . $form_id;
		$result      = SessionManager::get( $session_key );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Extrait le prénom depuis la soumission en cours
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $config  Configuration du formulaire.
	 * @return string Prénom ou chaîne vide.
	 */
	private function extract_first_name( int $form_id, array $config ): string {
		// Récupérer la soumission en cours
		if ( ! class_exists( 'GFFormsModel' ) ) {
			return '';
		}

		$submission = \GFFormsModel::get_current_lead();
		if ( ! $submission || ! is_array( $submission ) ) {
			return '';
		}

		// Récupérer l'ID du champ prénom depuis la config
		$first_name_field_id = $config['first_name_field_id'] ?? '7.3';

		// Extraire la valeur
		$first_name = $submission[ $first_name_field_id ] ?? '';

		return \sanitize_text_field( $first_name );
	}

	/**
	 * Formate le HTML du résultat
	 *
	 * @param int    $form_id     ID du formulaire.
	 * @param float  $score       Score calculé.
	 * @param string $verdict     Clé du verdict.
	 * @param string $verdict_text Texte du verdict.
	 * @param string $first_name  Prénom de l'utilisateur.
	 * @return string HTML formaté.
	 */
	private function format_result_html( int $form_id, float $score, string $verdict, string $verdict_text, string $first_name ): string {
		$verdict_class = $this->get_verdict_class( $verdict );
		$verdict_icon  = $this->get_verdict_icon( $verdict );

		// Remplacer les placeholders dans le texte du verdict
		$verdict_text = $this->replace_placeholders( $verdict_text, $first_name, $score );

		// Récupérer le template de titre depuis la config (ou fallback)
		$config         = $this->config_store->get_form_config( $form_id );
		$title_template = $config['score_title_template'] ?? 'Félicitations {{prenom}}, votre score : {{score}}/20';
		
		// Remplacer les placeholders dans le titre
		$greeting = $this->replace_placeholders( $title_template, $first_name, $score );

		// Préparer le texte du verdict : convertir sauts de ligne et autoriser HTML basique
		$verdict_text_formatted = \wp_kses_post( \nl2br( $verdict_text, false ) );

		$html = sprintf(
			'<div class="wcqf-test-result %s">
				<div class="wcqf-test-result__score">
					<span class="wcqf-test-result__emoji">🎯</span>
					<span class="wcqf-test-result__label">%s <strong>%s/20</strong></span>
				</div>
				<div class="wcqf-test-result__verdict">
					%s
					<span class="wcqf-verdict-text">%s</span>
				</div>
			</div>',
			\esc_attr( $verdict_class ),
			$greeting,
			\esc_html( $score ),
			$verdict_icon,
			$verdict_text_formatted
		);

		return $html;
	}

	/**
	 * Remplace les placeholders dans le texte du verdict
	 *
	 * Placeholders disponibles :
	 * - {{prenom}} : Prénom de l'utilisateur
	 * - {{score}} : Score obtenu
	 * - {{score_max}} : Score maximum (20)
	 *
	 * @param string $text       Texte avec placeholders.
	 * @param string $first_name Prénom de l'utilisateur.
	 * @param float  $score      Score calculé.
	 * @return string Texte avec placeholders remplacés.
	 */
	private function replace_placeholders( string $text, string $first_name, float $score ): string {
		$replacements = array(
			'{{prenom}}'    => ! empty( $first_name ) ? $first_name : '',
			'{{score}}'     => (string) $score,
			'{{score_max}}' => '20',
		);

		// Remplacer tous les placeholders
		$text = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$text
		);

		LoggingHelper::debug( '[ResultsInjector] Placeholders remplacés', array(
			'original_length' => strlen( $text ),
			'first_name'      => $first_name,
			'score'           => $score,
		) );

		return $text;
	}

	/**
	 * Retourne l'icône SVG selon le verdict
	 *
	 * @param string $verdict Clé du verdict.
	 * @return string SVG inline.
	 */
	private function get_verdict_icon( string $verdict ): string {
		$icons = array(
			'admitted'   => '<svg class="wcqf-verdict-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
			'reinforced' => '<svg class="wcqf-verdict-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
			'refused'    => '<svg class="wcqf-verdict-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
			'error'      => '<svg class="wcqf-verdict-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
		);

		return $icons[ $verdict ] ?? $icons['error'];
	}

	/**
	 * Retourne la classe CSS selon le verdict
	 *
	 * @param string $verdict Clé du verdict.
	 * @return string Classe CSS.
	 */
	private function get_verdict_class( string $verdict ): string {
		$classes = array(
			'admitted'   => 'wcqf-verdict--admitted',
			'reinforced' => 'wcqf-verdict--reinforced',
			'refused'    => 'wcqf-verdict--refused',
			'error'      => 'wcqf-test-result--error',
			'unknown'    => 'wcqf-test-result--error',
		);

		return $classes[ $verdict ] ?? 'wcqf-test-result--error';
	}
}
