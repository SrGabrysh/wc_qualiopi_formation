<?php
/**
 * Gestionnaire de transitions de pages Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de gestion de la transition du test de positionnement
 *
 * Responsabilité unique : Traiter UNIQUEMENT la transition page 2 → 3
 * pour récupérer le score de positionnement et déterminer le parcours de formation.
 *
 * Architecture Manager/Handler (depuis v1.1.0) :
 * - PageTransitionManager détecte TOUTES les transitions
 * - PageTransitionHandler écoute l'action et traite son cas spécifique
 *
 * Fonctionnalités :
 * - Écoute l'action 'wcqf_page_transition' (déclenchée par PageTransitionManager)
 * - Filtre uniquement la transition 2→3 en direction forward
 * - Récupération du score de positionnement (champ ID 27)
 * - Détermination du parcours de formation selon le score
 * - Déclenchement d'action WordPress 'wcqf_test_completed'
 */
class PageTransitionHandler {

	/**
	 * Page source (test de positionnement)
	 * 
	 * ATTENTION : Ce sont les NUMÉROS DE PAGES réels (1, 2, 3...), 
	 * PAS les IDs des champs "page" de Gravity Forms (15, 30...) !
	 *
	 * @var int
	 */
	private const SOURCE_PAGE = 2;

	/**
	 * Page cible (après le test)
	 * 
	 * ATTENTION : Ce sont les NUMÉROS DE PAGES réels (1, 2, 3...), 
	 * PAS les IDs des champs "page" de Gravity Forms (15, 30...) !
	 *
	 * @var int
	 */
	private const TARGET_PAGE = 3;

	/**
	 * ID du champ de calcul (score)
	 *
	 * @var int
	 */
	private const SCORE_FIELD_ID = 27;

	/**
	 * Seuils de score pour détermination du parcours
	 * Basé sur test_positionnement_revelation_digitale.md
	 * Score max : 20 points (10 questions × 2 points)
	 */
	private const SCORE_THRESHOLD_REFUSED = 10;      // < 10 : Refus d'inscription
	private const SCORE_THRESHOLD_REINFORCED = 15;   // 10-14 : Accompagnement renforcé

	/**
	 * Instance CalculationRetriever
	 *
	 * @var CalculationRetriever
	 */
	private $calculation_retriever;

	/**
	 * Constructeur
	 *
	 * Note : init_hooks() doit être appelé explicitement depuis FormManager
	 * pour cohérence avec les autres handlers (convention du plugin).
	 *
	 * @param CalculationRetriever $calculation_retriever Instance du retriever.
	 */
	public function __construct( CalculationRetriever $calculation_retriever ) {
		$this->calculation_retriever = $calculation_retriever;
		
		LoggingHelper::info(
			'PageTransitionHandler initialized',
			array(
				'source_page' => self::SOURCE_PAGE,
				'target_page' => self::TARGET_PAGE,
				'score_field' => self::SCORE_FIELD_ID,
			)
		);
	}

	/**
	 * Initialise les hooks WordPress
	 *
	 * Écoute l'action wcqf_page_transition déclenchée par PageTransitionManager
	 * au lieu de s'abonner directement à gform_post_paging.
	 *
	 * @since 1.1.0 Refactorisé pour utiliser PageTransitionManager
	 */
	public function init_hooks() {
		// Écouter l'action du Manager
		add_action( 'wcqf_page_transition', array( $this, 'handle_test_transition' ), 10, 1 );

		LoggingHelper::debug( '[PageTransitionHandler] Hooks enregistres', array(
			'hook'        => 'wcqf_page_transition',
			'source_page' => self::SOURCE_PAGE,
			'target_page' => self::TARGET_PAGE,
		) );
	}

	/**
	 * Gère la transition spécifique du test de positionnement (page 2 → 3)
	 *
	 * Écoute l'action wcqf_page_transition et filtre uniquement la transition
	 * qui nous intéresse (test de positionnement terminé).
	 *
	 * Simplifié depuis v1.1.0 : Le Manager a déjà validé le contexte et
	 * récupéré les données de soumission.
	 *
	 * @since 1.1.0 Refactorisé pour utiliser l'action du Manager
	 * @param array $transition_data Données de transition du Manager.
	 * @return void
	 */
	public function handle_test_transition( array $transition_data ): void {
		// Filtrer : uniquement transition 2 → 3 en direction forward
		if ( $transition_data['from_page'] !== self::SOURCE_PAGE 
			|| $transition_data['to_page'] !== self::TARGET_PAGE
			|| $transition_data['direction'] !== 'forward' ) {
			// Ignorer silencieusement les autres transitions
			return;
		}

		LoggingHelper::info( '[PageTransitionHandler] Test de positionnement termine', array(
			'form_id'    => $transition_data['form_id'],
			'entry_id'   => $transition_data['entry_id'],
			'transition' => sprintf( '%d -> %d', $transition_data['from_page'], $transition_data['to_page'] ),
		) );

		// Gérer la complétion du test
		$this->handle_test_completion( $transition_data );
	}

	/**
	 * Gère la complétion du test de positionnement
	 *
	 * Récupère le score calculé et détermine le parcours de formation.
	 *
	 * @since 1.1.0 Simplifié : les données sont déjà dans $transition_data
	 * @param array $transition_data Données de transition du Manager.
	 * @return void
	 */
	private function handle_test_completion( array $transition_data ): void {
		LoggingHelper::info( '[PageTransitionHandler] === DÉBUT handle_test_completion ===' );

		// Les données sont déjà validées par le Manager
		$form            = $transition_data['form'];
		$submission_data = $transition_data['submission_data'];

		LoggingHelper::debug( '[PageTransitionHandler] Données reçues du Manager', array(
			'form_id'        => $transition_data['form_id'],
			'entry_id'       => $transition_data['entry_id'],
			'fields_count'   => count( $submission_data ),
			'has_field_27'   => isset( $submission_data[ self::SCORE_FIELD_ID ] ),
			'field_27_value' => $submission_data[ self::SCORE_FIELD_ID ] ?? 'N/A',
		) );

		// Récupérer le score calculé via CalculationRetriever
		LoggingHelper::debug( '[PageTransitionHandler] Appel CalculationRetriever->get_calculated_value()...', array(
			'field_id' => self::SCORE_FIELD_ID,
		) );

		$score = $this->calculation_retriever->get_calculated_value(
			$transition_data['form_id'],
			$submission_data,
			self::SCORE_FIELD_ID
		);

		if ( $score === false ) {
			LoggingHelper::error( '[PageTransitionHandler] Echec recuperation du score', array(
				'form_id'  => $transition_data['form_id'],
				'field_id' => self::SCORE_FIELD_ID,
			) );
			return;
		}

		LoggingHelper::info( '[PageTransitionHandler] Score de positionnement recupere', array(
			'form_id'  => $transition_data['form_id'],
			'score'    => $score,
			'type'     => gettype( $score ),
			'field_id' => self::SCORE_FIELD_ID,
		) );

		// Déterminer le parcours de formation
		$this->determine_training_path( $score, $submission_data, $form );

		LoggingHelper::info( '[PageTransitionHandler] === FIN handle_test_completion ===' );
	}

	/**
	 * Détermine le parcours de formation selon le score
	 *
	 * Logique métier (test_positionnement_revelation_digitale.md) :
	 * - Score 0-9   : REFUS - "refused" - Ne correspond pas aux objectifs de la formation
	 * - Score 10-14 : ACCOMPAGNEMENT RENFORCÉ - "reinforced" - Bon potentiel, suivi personnalisé
	 * - Score 15-20 : ADMISSION DIRECTE - "admitted" - Profil parfaitement aligné
	 *
	 * @param float $score Score de positionnement (max 20 points).
	 * @param array $submission_data Données de soumission.
	 * @param array $form Formulaire GF.
	 */
	private function determine_training_path( $score, $submission_data, $form ) {
		// Déterminer le parcours selon les seuils
		$path = $this->get_path_from_score( $score );

		LoggingHelper::info( '[PageTransitionHandler] Parcours de formation déterminé', array(
			'form_id' => $form['id'],
			'score'   => $score,
			'path'    => $path,
		) );

		// Déclencher une action WordPress personnalisée pour permettre des extensions
		do_action( 'wcqf_test_completed', $score, $path, $submission_data, $form );

		LoggingHelper::debug( '[PageTransitionHandler] Action wcqf_test_completed déclenchée', array(
			'score' => $score,
			'path'  => $path,
		) );
	}

	/**
	 * Détermine le parcours depuis le score
	 *
	 * @param float $score Score de positionnement (0-20).
	 * @return string Parcours : 'refused', 'reinforced', 'admitted'.
	 */
	private function get_path_from_score( $score ) {
		// Score 0-9 : Refus d'inscription
		if ( $score < self::SCORE_THRESHOLD_REFUSED ) {
			return 'refused';
		}

		// Score 10-14 : Admission avec accompagnement renforcé
		if ( $score < self::SCORE_THRESHOLD_REINFORCED ) {
			return 'reinforced';
		}

		// Score 15-20 : Admission directe
		return 'admitted';
	}
}

