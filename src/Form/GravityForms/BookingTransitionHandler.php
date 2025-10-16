<?php
/**
 * Booking Transition Handler
 * 
 * Handles page transition 3→4 to extract booking data from cart
 *
 * @package WcQualiopiFormation
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Data\Retrieval\BookingDataRetriever;

/**
 * Booking Transition Handler
 * 
 * Responsabilité unique : Traiter UNIQUEMENT la transition page 3 → 4
 * pour récupérer les données de réservation depuis le panier WooCommerce.
 * 
 * Architecture Manager/Handler (pattern du plugin) :
 * - PageTransitionManager détecte TOUTES les transitions
 * - BookingTransitionHandler écoute l'action et traite son cas spécifique
 * 
 * Fonctionnalités :
 * - Écoute l'action 'wcqf_page_transition' (déclenchée par PageTransitionManager)
 * - Filtre uniquement la transition 3→4 en direction forward
 * - Récupération des données de réservation depuis WC()->cart
 * - Déclenchement d'action WordPress 'wcqf_booking_data_retrieved'
 * 
 * @since 1.2.0
 */
class BookingTransitionHandler {

	/**
	 * Page source (infos entreprise)
	 * 
	 * ATTENTION : Ce sont les NUMÉROS DE PAGES réels (1, 2, 3...), 
	 * PAS les IDs des champs "page" de Gravity Forms !
	 *
	 * @var int
	 */
	private const SOURCE_PAGE = 3;

	/**
	 * Page cible (signature contrat)
	 * 
	 * ATTENTION : Ce sont les NUMÉROS DE PAGES réels (1, 2, 3...), 
	 * PAS les IDs des champs "page" de Gravity Forms !
	 *
	 * @var int
	 */
	private const TARGET_PAGE = 4;

	/**
	 * Constructeur
	 * 
	 * Note : init_hooks() doit être appelé explicitement depuis FormManager
	 * pour cohérence avec les autres handlers (convention du plugin).
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		LoggingHelper::info(
			'[BookingTransitionHandler] Handler initialized',
			array(
				'source_page' => self::SOURCE_PAGE,
				'target_page' => self::TARGET_PAGE,
			)
		);
	}

	/**
	 * Initialise les hooks WordPress
	 * 
	 * Écoute l'action wcqf_page_transition déclenchée par PageTransitionManager
	 * au lieu de s'abonner directement à gform_post_paging.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function init_hooks(): void {
		// Écouter l'action du Manager
		\add_action( 'wcqf_page_transition', array( $this, 'handle_booking_transition' ), 10, 1 );

		LoggingHelper::debug(
			'[BookingTransitionHandler] Hooks registered',
			array(
				'hook'        => 'wcqf_page_transition',
				'source_page' => self::SOURCE_PAGE,
				'target_page' => self::TARGET_PAGE,
			)
		);
	}

	/**
	 * Gère la transition spécifique pour extraction des données de réservation (page 3 → 4)
	 * 
	 * Écoute l'action wcqf_page_transition et filtre uniquement la transition
	 * qui nous intéresse (avant génération du contrat Yousign).
	 * 
	 * Simplifié : Le Manager a déjà validé le contexte et récupéré les données de soumission.
	 *
	 * @since 1.2.0
	 * @param array $transition_data Données de transition du Manager.
	 * @return void
	 */
	public function handle_booking_transition( array $transition_data ): void {
		// Filtrer : uniquement transition 3 → 4 en direction forward
		if ( $transition_data['from_page'] !== self::SOURCE_PAGE 
			|| $transition_data['to_page'] !== self::TARGET_PAGE
			|| $transition_data['direction'] !== 'forward' ) {
			// Ignorer silencieusement les autres transitions
			return;
		}

		LoggingHelper::info(
			'[BookingTransitionHandler] Booking transition detected',
			array(
				'form_id'    => $transition_data['form_id'],
				'entry_id'   => $transition_data['entry_id'],
				'transition' => sprintf( '%d -> %d', $transition_data['from_page'], $transition_data['to_page'] ),
			)
		);

		// Extraire les données de réservation depuis le panier
		$this->extract_and_broadcast_booking_data( $transition_data );
	}

	/**
	 * Extrait les données de réservation et déclenche l'action WordPress
	 * 
	 * Récupère les données de réservation depuis WC()->cart et déclenche
	 * l'action 'wcqf_booking_data_retrieved' pour que le module Yousign
	 * puisse les utiliser lors de la génération du contrat.
	 *
	 * @since 1.2.0
	 * @param array $transition_data Données de transition du Manager.
	 * @return void
	 */
	private function extract_and_broadcast_booking_data( array $transition_data ): void {
		LoggingHelper::info( '[BookingTransitionHandler] === DÉBUT extract_and_broadcast_booking_data ===' );

		// Extraire les données de réservation depuis le panier
		$booking_data = BookingDataRetriever::get_details_from_cart();

		if ( empty( $booking_data ) ) {
			LoggingHelper::warning(
				'[BookingTransitionHandler] No booking data found in cart',
				array(
					'form_id'  => $transition_data['form_id'],
					'entry_id' => $transition_data['entry_id'],
				)
			);
			return;
		}

		LoggingHelper::info(
			'[BookingTransitionHandler] Booking data extracted from cart',
			array(
				'form_id'             => $transition_data['form_id'],
				'entry_id'            => $transition_data['entry_id'],
				'booking_items_count' => count( $booking_data ),
				'booking_summary'     => $this->build_booking_summary( $booking_data ),
			)
		);

		/**
		 * Action déclenchée lorsque les données de réservation sont extraites du panier
		 * 
		 * Permet au module Yousign de récupérer les données pour pré-remplir
		 * les champs du contrat de formation.
		 * 
		 * @since 1.2.0
		 * @param array $booking_data     Array de données de réservation
		 * @param array $transition_data  Données complètes de transition
		 */
		\do_action( 'wcqf_booking_data_retrieved', $booking_data, $transition_data );

		LoggingHelper::debug( '[BookingTransitionHandler] Action wcqf_booking_data_retrieved triggered' );
		LoggingHelper::info( '[BookingTransitionHandler] === FIN extract_and_broadcast_booking_data ===' );
	}

	/**
	 * Construit un résumé des données de réservation pour logging
	 * 
	 * @param array $booking_data Données de réservation.
	 * @return array Résumé formaté.
	 */
	private function build_booking_summary( array $booking_data ): array {
		$summary = array();

		foreach ( $booking_data as $index => $booking ) {
			$summary[] = array(
				'index'        => $index,
				'product_id'   => $booking['product_id'] ?? 'N/A',
				'product_name' => $booking['product_name'] ?? 'N/A',
				'start_date'   => $booking['start_paris'] ?? 'N/A',
				'end_date'     => $booking['end_paris'] ?? 'N/A',
			);
		}

		return $summary;
	}
}

