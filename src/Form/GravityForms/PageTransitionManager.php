<?php
/**
 * Manager global des transitions de pages Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de gestion centralisée des transitions de pages
 *
 * Responsabilité unique : Détecter TOUTES les transitions de pages Gravity Forms
 * et déclencher une action WordPress centralisée pour permettre l'ajout de handlers.
 *
 * Architecture Manager/Handlers :
 * - Le Manager détecte et prépare les données
 * - Les Handlers écoutent l'action et traitent leur cas spécifique
 *
 * @since 1.1.0
 */
class PageTransitionManager {

	/**
	 * Constructeur
	 * 
	 * Initialise automatiquement les hooks lors de l'instanciation.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->init_hooks();
		
		LoggingHelper::debug( '[PageTransitionManager] Initialized', array(
			'hook'     => 'gform_post_paging',
			'priority' => 10,
		) );
	}

	/**
	 * Initialise les hooks Gravity Forms
	 *
	 * Hook sur gform_post_paging pour détecter toutes les transitions.
	 * Priorité 10 (standard) pour permettre aux autres plugins de s'exécuter.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Hook principal : gform_post_paging
		// Déclenché après validation d'une page, avant affichage de la suivante
		add_action( 'gform_post_paging', array( $this, 'on_page_transition' ), 10, 3 );
		
		LoggingHelper::debug( '[PageTransitionManager] Hook registered', array(
			'hook'     => 'gform_post_paging',
			'priority' => 10,
		) );
	}

	/**
	 * Callback principal lors d'une transition de page
	 *
	 * Détecte toute transition de page GF, construit un payload complet
	 * et déclenche l'action WordPress 'wcqf_page_transition' pour permettre
	 * aux handlers de traiter leur cas spécifique.
	 *
	 * Stratégie fail-safe : Ne JAMAIS bloquer le formulaire en cas d'erreur.
	 *
	 * @since 1.1.0
	 * @param array $form           Formulaire Gravity Forms complet.
	 * @param int   $source_page    Numéro de la page source.
	 * @param int   $current_page   Numéro de la page cible.
	 * @return void
	 */
	public function on_page_transition( $form, $source_page, $current_page ): void {
		// Log debug pour vérifier si cette méthode est appelée
		LoggingHelper::debug( '[PageTransitionManager] on_page_transition called', array(
			'form_id'      => $form['id'] ?? 'N/A',
			'source_page'  => $source_page,
			'current_page' => $current_page,
			'has_gfformsmodel' => class_exists( 'GFFormsModel' ),
		) );
		
		try {
		// Validation du contexte (inclut déjà vérification GFFormsModel)
		if ( ! $this->validate_transition_context( $form, $source_page, $current_page ) ) {
			LoggingHelper::error( '[PageTransitionManager] Validation contexte echouee' );
			return;
		}

		// Récupérer les données de soumission en cours directement

			$submission_data = \GFFormsModel::get_current_lead();

			if ( ! is_array( $submission_data ) || empty( $submission_data ) ) {
				LoggingHelper::error( '[PageTransitionManager] Invalid submission data', array(
					'form_id' => $form['id'] ?? 'unknown',
					'type'    => gettype( $submission_data ),
				) );
				return;
			}

		// Déterminer la direction de navigation
		$direction = $this->get_direction( $source_page, $current_page );

		// ✅ LOG AJOUTÉ POUR DEBUG - Phase 1 - Extraction du token
		$token_field_id  = 9999; // ID du champ caché token.
		$token_from_form = $submission_data[ $token_field_id ] ?? '';

		LoggingHelper::info( '[PageTransitionManager] Token extraction from form', array(
			'form_id'            => $form['id'],
			'from_page'          => $source_page,
			'to_page'            => $current_page,
			'field_id_checked'   => $token_field_id,
			'field_exists'       => isset( $submission_data[ $token_field_id ] ),
			'token_empty'        => empty( $token_from_form ),
			'token_preview'      => $token_from_form ? substr( $token_from_form, 0, 20 ) . '...' : '(empty)',
			'submission_data_keys' => array_keys( $submission_data ),
		) );

	// Construire le payload complet
	$transition_data = $this->build_transition_data(
		$form,
		$submission_data,
		$source_page,
		$current_page,
		$direction
	);

		// ✅ LOG AJOUTÉ POUR DEBUG - Phase 1 - Validation transition_data
		LoggingHelper::info( '[PageTransitionManager] Transition data built', array(
			'form_id'            => $form['id'],
			'has_token_in_data'  => ! empty( $transition_data['token'] ),
			'token_value_preview' => ! empty( $transition_data['token'] )
				? substr( $transition_data['token'], 0, 20 ) . '...'
				: '(empty)',
		) );

		// Log de la transition détectée
		LoggingHelper::info( '[PageTransitionManager] Transition detected', array(
			'form_id'    => $transition_data['form_id'],
			'from_page'  => $transition_data['from_page'],
			'to_page'    => $transition_data['to_page'],
			'direction'  => $transition_data['direction'],
			'entry_id'   => $transition_data['entry_id'],
		) );

			// Log debug du payload construit
			LoggingHelper::debug( '[PageTransitionManager] Payload built', array(
				'payload_keys' => array_keys( $transition_data ),
				'data_size'    => count( $submission_data ),
				'has_token'    => ! empty( $transition_data['token'] ),
				'token_value'  => $transition_data['token'],
				'field_9999'   => $submission_data['9999'] ?? 'NOT_FOUND',
			) );

			/**
			 * Action déclenchée à chaque transition de page Gravity Forms
			 *
			 * Permet aux handlers d'écouter et de traiter leur cas spécifique.
			 *
			 * @since 1.1.0
			 * @param array $transition_data Données de transition complètes.
			 */
			do_action( 'wcqf_page_transition', $transition_data );

			LoggingHelper::debug( '[PageTransitionManager] Action wcqf_page_transition triggered' );

		} catch ( \Exception $e ) {
			// Fail-safe : Ne JAMAIS bloquer le formulaire
			LoggingHelper::critical( '[PageTransitionManager] Unexpected exception', array(
				'message' => $e->getMessage(),
				'code'    => $e->getCode(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'trace'   => $e->getTraceAsString(),
			) );
		}
	}

	/**
	 * Valide le contexte de transition
	 *
	 * Vérifie que toutes les données nécessaires sont présentes et valides.
	 * 
	 * Sécurité défensive : Vérifie les permissions minimales.
	 * Les invités (guests WooCommerce) sont autorisés car le formulaire
	 * est accessible en frontend. La sécurité repose sur Gravity Forms
	 * qui gère l'authentification et la validation des soumissions.
	 *
	 * @since 1.1.0
	 * @param array $form         Formulaire GF.
	 * @param int   $source_page  Page source.
	 * @param int   $current_page Page cible.
	 * @return bool True si le contexte est valide, false sinon.
	 */
	private function validate_transition_context( $form, $source_page, $current_page ): bool {
		// Sécurité défensive : Vérifier que l'utilisateur a au moins le droit de lecture
		// (Utilisateurs authentifiés + invités autorisés en frontend GF)
		if ( ! current_user_can( 'read' ) && ! is_user_logged_in() ) {
			// Permet aux invités (WooCommerce guests) de continuer
			// mais log si capacité 'read' absente pour utilisateurs authentifiés
			if ( is_user_logged_in() ) {
				LoggingHelper::warning( '[PageTransitionManager] User lacks read capability', array(
					'user_id' => get_current_user_id(),
					'form_id' => $form['id'] ?? 'unknown',
				) );
			}
		}

		// Vérifier que GFFormsModel existe
		if ( ! class_exists( 'GFFormsModel' ) ) {
			LoggingHelper::error( '[PageTransitionManager] GFFormsModel class not found' );
			return false;
		}

		// Vérifier que $form est un array non vide
		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			LoggingHelper::error( '[PageTransitionManager] Invalid form data', array(
				'type'     => gettype( $form ),
				'is_array' => is_array( $form ),
				'has_id'   => isset( $form['id'] ),
			) );
			return false;
		}

		// Vérifier que les numéros de pages sont valides
		$source_page  = absint( $source_page );
		$current_page = absint( $current_page );

		if ( $source_page <= 0 || $current_page <= 0 ) {
			LoggingHelper::warning( '[PageTransitionManager] Invalid page numbers', array(
				'form_id'      => $form['id'],
				'source_page'  => $source_page,
				'current_page' => $current_page,
			) );
			return false;
		}

		return true;
	}

	/**
	 * Détermine la direction de navigation
	 *
	 * Compare les numéros de pages pour déterminer si l'utilisateur
	 * avance (forward) ou recule (backward) dans le formulaire.
	 *
	 * @since 1.1.0
	 * @param int $from Page source.
	 * @param int $to   Page cible.
	 * @return string 'forward' si to > from, 'backward' si to < from.
	 */
	private function get_direction( int $from, int $to ): string {
		return $to > $from ? 'forward' : 'backward';
	}

	/**
	 * Construit le payload complet de transition
	 *
	 * Compile toutes les informations nécessaires pour les handlers.
	 *
	 * @since 1.1.0
	 * @param array  $form            Formulaire GF.
	 * @param array  $submission_data Données de soumission.
	 * @param int    $source_page     Page source.
	 * @param int    $current_page    Page cible.
	 * @param string $direction       Direction navigation.
	 * @return array {
	 *     Payload structuré de transition.
	 *
	 *     @type int    $form_id         ID du formulaire GF.
	 *     @type string $form_title      Titre du formulaire.
	 *     @type int    $entry_id        ID de l'entrée GF.
	 *     @type string $token           Token HMAC du plugin (champ 9999).
	 *     @type int    $from_page       Numéro page source.
	 *     @type int    $to_page         Numéro page cible.
	 *     @type string $direction       'forward' ou 'backward'.
	 *     @type array  $submission_data Données complètes de soumission.
	 *     @type array  $form            Formulaire GF complet.
	 *     @type string $timestamp       Timestamp MySQL (format Y-m-d H:i:s).
	 *     @type string $user_ip         IP du client (ou 'unknown').
	 *     @type int    $user_id         ID utilisateur WP (0 si invité).
	 * }
	 */
	private function build_transition_data( $form, $submission_data, $source_page, $current_page, $direction ): array {
		// Sanitization des numéros de pages
		$source_page  = absint( $source_page );
		$current_page = absint( $current_page );

		// Récupération de l'IP client (sanitized)
		$user_ip = 'unknown';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$user_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Construction du payload
		return array(
			// Métadonnées formulaire
			'form_id'    => absint( $form['id'] ),
			'form_title' => sanitize_text_field( $form['title'] ?? 'N/A' ),

			// Métadonnées entrée
			'entry_id'   => absint( $submission_data['id'] ?? 0 ),
			'token'      => sanitize_text_field( $submission_data['9999'] ?? '' ),

			// Navigation
			'from_page'  => $source_page,
			'to_page'    => $current_page,
			'direction'  => sanitize_text_field( $direction ),

			// Données complètes (pour handlers qui en ont besoin)
			'submission_data' => $submission_data,
			'form'            => $form,

			// Contexte
			'timestamp'  => current_time( 'mysql' ),
			'user_ip'    => $user_ip,
			'user_id'    => get_current_user_id(),
		);
	}
}

