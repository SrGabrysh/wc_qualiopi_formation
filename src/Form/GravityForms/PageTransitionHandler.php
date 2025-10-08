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
 * Classe de gestion des transitions de pages
 *
 * Responsabilité unique : Gérer les passages de pages dans les formulaires GF
 * et récupérer le score de positionnement pour déterminer le parcours de formation.
 *
 * Fonctionnalités :
 * - Hook sur le passage de page GF (gform_post_paging)
 * - Récupération du score de positionnement (champ ID 27)
 * - Détermination du parcours de formation selon le score
 * - Déclenchement d'actions WordPress personnalisées
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
		
		$this->init_hooks();
	}

	/**
	 * Initialise les hooks Gravity Forms
	 */
	public function init_hooks() {
		// Hook après validation de page (avant affichage page suivante)
		add_action( 'gform_post_paging', array( $this, 'on_page_transition' ), 10, 3 );

		LoggingHelper::info( '[PageTransitionHandler] Hooks enregistrés', array(
			'hook'        => 'gform_post_paging',
			'source_page' => self::SOURCE_PAGE,
			'target_page' => self::TARGET_PAGE,
		) );
	}

	/**
	 * Appelé lors du passage d'une page à l'autre
	 *
	 * Hook Gravity Forms : gform_post_paging
	 * Déclenché après validation d'une page, avant affichage de la suivante.
	 *
	 * @param array $form Formulaire GF complet.
	 * @param int   $source_page_number Numéro de la page source.
	 * @param int   $current_page_number Numéro de la page cible.
	 */
	public function on_page_transition( $form, $source_page_number, $current_page_number ) {
		// Convertir en int pour éviter les problèmes de comparaison string vs int
		$source_page_number  = (int) $source_page_number;
		$current_page_number = (int) $current_page_number;
		
		// Log détaillé de TOUTES les transitions pour diagnostic
		LoggingHelper::info( '[PageTransitionHandler] ===== TRANSITION DE PAGE DÉTECTÉE =====', array(
			'timestamp'     => current_time( 'mysql' ),
			'form_id'       => $form['id'],
			'form_title'    => $form['title'] ?? 'N/A',
			'from_page'     => $source_page_number,
			'to_page'       => $current_page_number,
			'expected_from' => self::SOURCE_PAGE,
			'expected_to'   => self::TARGET_PAGE,
			'is_match'      => ( $source_page_number === self::SOURCE_PAGE && $current_page_number === self::TARGET_PAGE ),
			'user_id'       => get_current_user_id(),
			'user_ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
		) );

		// Vérifier si c'est la transition qui nous intéresse (2 → 3)
		if ( $source_page_number !== self::SOURCE_PAGE || $current_page_number !== self::TARGET_PAGE ) {
			LoggingHelper::debug( '[PageTransitionHandler] Transition non concernée, ignorée', array(
				'reason' => sprintf(
					'Source page %d != %d OU Target page %d != %d',
					$source_page_number,
					self::SOURCE_PAGE,
					$current_page_number,
					self::TARGET_PAGE
				),
			) );
			return;
		}

		LoggingHelper::warning( '[PageTransitionHandler] 🎯 TRANSITION CRITIQUE DÉTECTÉE ! Test de positionnement terminé', array(
			'form_id'    => $form['id'],
			'transition' => sprintf( '%d → %d', $source_page_number, $current_page_number ),
		) );

		// Gérer la complétion du test
		$this->handle_test_completion( $form );
	}

	/**
	 * Gère la complétion du test de positionnement
	 *
	 * Récupère le score calculé et détermine le parcours de formation.
	 *
	 * @param array $form Formulaire GF.
	 */
	private function handle_test_completion( $form ) {
		LoggingHelper::info( '[PageTransitionHandler] === DÉBUT handle_test_completion ===' );

		// Récupérer les données de soumission en cours (partielle)
		if ( ! class_exists( 'GFFormsModel' ) ) {
			LoggingHelper::error( '[PageTransitionHandler] ❌ GFFormsModel non disponible' );
			return;
		}

		LoggingHelper::debug( '[PageTransitionHandler] GFFormsModel disponible, récupération lead...' );
		$submission_data = \GFFormsModel::get_current_lead();

		if ( ! $submission_data || ! is_array( $submission_data ) ) {
			LoggingHelper::error( '[PageTransitionHandler] ❌ Impossible de récupérer les données de soumission', array(
				'form_id'         => $form['id'],
				'submission_data' => $submission_data,
				'type'            => gettype( $submission_data ),
			) );
			return;
		}

		LoggingHelper::info( '[PageTransitionHandler] ✅ Données de soumission récupérées', array(
			'form_id'         => $form['id'],
			'entry_id'        => $submission_data['id'] ?? 'partial',
			'fields_count'    => count( $submission_data ),
			'has_field_27'    => isset( $submission_data[ self::SCORE_FIELD_ID ] ),
			'field_27_value'  => $submission_data[ self::SCORE_FIELD_ID ] ?? 'N/A',
		) );

		// Récupérer le score calculé via CalculationRetriever
		LoggingHelper::debug( '[PageTransitionHandler] Appel CalculationRetriever->get_calculated_value()...', array(
			'field_id' => self::SCORE_FIELD_ID,
		) );

		$score = $this->calculation_retriever->get_calculated_value(
			$form['id'],
			$submission_data,
			self::SCORE_FIELD_ID
		);

		if ( $score === false ) {
			LoggingHelper::error( '[PageTransitionHandler] ❌ Échec récupération du score', array(
				'form_id'  => $form['id'],
				'field_id' => self::SCORE_FIELD_ID,
			) );
			return;
		}

		LoggingHelper::warning( '[PageTransitionHandler] ✅✅✅ SCORE DE POSITIONNEMENT RÉCUPÉRÉ !', array(
			'form_id'  => $form['id'],
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

