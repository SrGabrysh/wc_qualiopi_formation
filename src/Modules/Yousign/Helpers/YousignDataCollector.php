<?php
/**
 * Yousign Data Collector
 * 
 * RESPONSABILITÉ UNIQUE : Collecter toutes les données nécessaires pour Yousign
 * 
 * @package WcQualiopiFormation\Modules\Yousign\Helpers
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Modules\Yousign\Helpers;

use WcQualiopiFormation\Form\Tracking\DataExtractor;
use WcQualiopiFormation\Data\Retrieval\BookingDataRetriever;
use WcQualiopiFormation\Data\Retrieval\CartBookingRetriever;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\NameFormatter;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe YousignDataCollector
 * 
 * Principe SRP : Collecter TOUTES les données pour payload Yousign UNIQUEMENT
 * 
 * @since 1.2.0
 */
class YousignDataCollector {

	/**
	 * Collecte toutes les données nécessaires pour le payload Yousign
	 * 
	 * Rassemble les données depuis multiples sources :
	 * - Données personnelles (firstName, lastName, email) depuis formulaire GF
	 * - Mentions légales (form field ID 13)
	 * - Dates de réservation (booking)
	 * - Date actuelle
	 * - Noms complets formatés
	 * 
	 * @param array  $submission_data Données de soumission GF.
	 * @param object $form            Objet formulaire GF.
	 * @param string $token           Token de session (pour form_data).
	 * @return array Données complètes pour Yousign.
	 */
	public static function collect_all_data( array $submission_data, $form, string $token ): array {
		LoggingHelper::debug(
			'[YousignDataCollector] Starting data collection',
			array(
				'has_submission_data' => ! empty( $submission_data ),
				'has_form'            => ! empty( $form ),
				'token_preview'       => substr( $token, 0, 10 ),
			)
		);

		// 1. Données personnelles depuis le formulaire GF en cours
		$data_extractor = new DataExtractor();
		$personal_data  = $data_extractor->extract_personal( $submission_data, $form );

		if ( empty( $personal_data ) ) {
			LoggingHelper::error(
				'[YousignDataCollector] Personal data not found in form submission',
				array( 'token_preview' => substr( $token, 0, 10 ) )
			);
			return array();
		}

		// 2. Mentions légales (field ID 13) - depuis submission actuelle
		$mentions_legales = self::get_mentions_legales( $submission_data );

		// 3. Dates de réservation (booking)
		$booking_dates = self::get_booking_dates();

		// 4. Date actuelle formatée
		$date_jour = self::get_current_date_formatted();

		// 5. Noms complets
		$full_names = self::build_full_names( $personal_data );

		// 6. Totaux du panier (HT, TTC, TVA)
		$cart_totals = self::get_cart_totals();

		// Construire le tableau de données complet
		$enriched_data = array_merge(
			$personal_data,
			$full_names,
			array(
				'mentions_legales' => $mentions_legales,
				'date_realisation' => $booking_dates['date_formatted'],
				'date_jour'        => $date_jour,
				'total_ht'         => $cart_totals['total_ht'],
				'total_ttc'        => $cart_totals['total_ttc'],
				'tva'              => $cart_totals['tva'],
			)
		);

		LoggingHelper::info(
			'[YousignDataCollector] Data collection complete',
			array(
				'has_personal_data'    => ! empty( $personal_data ),
				'has_mentions_legales' => ! empty( $mentions_legales ),
				'has_booking_dates'    => ! empty( $booking_dates['date_formatted'] ),
				'has_full_name'        => ! empty( $full_names['full_name'] ),
				'has_total_ht'         => ! empty( $cart_totals['total_ht'] ),
				'has_total_ttc'        => ! empty( $cart_totals['total_ttc'] ),
				'has_tva'              => ! empty( $cart_totals['tva'] ),
			)
		);

		return $enriched_data;
	}

	/**
	 * Récupère les mentions légales depuis le formulaire
	 * 
	 * Les mentions légales sont stockées dans le field ID 13 du Gravity Form.
	 * 
	 * @param array $submission_data Données de soumission GF actuelle.
	 * @return string Mentions légales (HTML) ou chaîne vide.
	 */
	private static function get_mentions_legales( array $submission_data ): string {
		// Field ID 13 = mentions_legales
		$mentions = $submission_data['13'] ?? '';

		if ( empty( $mentions ) ) {
			LoggingHelper::warning(
				'[YousignDataCollector] Mentions legales empty (field ID 13)',
				array( 'submission_data_keys' => array_keys( $submission_data ) )
			);
			return '';
		}

		// Sanitization : \wp_kses_post autorise le HTML sûr
		$mentions_clean = \wp_kses_post( $mentions );

		LoggingHelper::debug(
			'[YousignDataCollector] Mentions legales retrieved',
			array(
				'length'      => strlen( $mentions_clean ),
				'has_content' => ! empty( $mentions_clean ),
			)
		);

		return $mentions_clean;
	}

	/**
	 * Récupère les dates de réservation depuis le panier
	 * 
	 * Utilise CartBookingRetriever pour extraire les dates depuis WooCommerce cart.
	 * 
	 * @return array Array avec 'date_formatted' (ex: "20 octobre 2025").
	 */
	private static function get_booking_dates(): array {
		$booking_details = CartBookingRetriever::get_booking_details();

		if ( empty( $booking_details ) ) {
			LoggingHelper::warning(
				'[YousignDataCollector] No booking details found in cart',
				array()
			);
			return array( 'date_formatted' => '' );
		}

		// Prendre la première réservation (cas standard : 1 produit booking par commande)
		$first_booking = $booking_details[0];

		// Le champ 'date' de WC Bookings est déjà formaté ("20 octobre 2025")
		// Il se trouve dans la structure $cart_item['booking']['date']
		$date_formatted = '';

		if ( ! empty( $first_booking['start_paris'] ) ) {
			// Convertir "DD-MM-YYYY" → "DD mois YYYY"
			$date_formatted = self::format_french_date( $first_booking['start_paris'] );
		}

		LoggingHelper::debug(
			'[YousignDataCollector] Booking dates retrieved',
			array(
				'booking_count'  => count( $booking_details ),
				'date_formatted' => $date_formatted,
				'product_id'     => $first_booking['product_id'] ?? 'N/A',
			)
		);

		return array( 'date_formatted' => $date_formatted );
	}

	/**
	 * Récupère la date actuelle formatée en français
	 * 
	 * Format : "16 octobre 2025" (même format que date_realisation).
	 * 
	 * @return string Date actuelle formatée.
	 */
	private static function get_current_date_formatted(): string {
		// Utiliser wp_date pour respecter le timezone WordPress
		$date_jour = wp_date( 'j F Y' ); // Format: "16 octobre 2025"

		LoggingHelper::debug(
			'[YousignDataCollector] Current date formatted',
			array( 'date_jour' => $date_jour )
		);

		return $date_jour;
	}

	/**
	 * Construit les noms complets formatés
	 * 
	 * Génère :
	 * - full_name : "Prénom NOM"
	 * - full_name_stagiaire : "Prénom NOM" (identique)
	 * 
	 * @param array $personal_data Données personnelles (firstName, lastName).
	 * @return array Array avec 'full_name' et 'full_name_stagiaire'.
	 */
	private static function build_full_names( array $personal_data ): array {
		$first_name = $personal_data['first_name'] ?? '';
		$last_name  = $personal_data['last_name'] ?? '';

		// Format : "Prénom NOM"
		$full_name = trim( $first_name . ' ' . strtoupper( $last_name ) );

		if ( empty( $full_name ) ) {
			LoggingHelper::warning(
				'[YousignDataCollector] Could not build full name',
				array(
					'has_first_name' => ! empty( $first_name ),
					'has_last_name'  => ! empty( $last_name ),
				)
			);
		}

		LoggingHelper::debug(
			'[YousignDataCollector] Full names built',
			array(
				'full_name'           => $full_name,
				'full_name_stagiaire' => $full_name,
			)
		);

		return array(
			'full_name'           => $full_name,
			'full_name_stagiaire' => $full_name,
		);
	}

	/**
	 * Formate une date depuis "DD-MM-YYYY" vers "DD mois YYYY"
	 * 
	 * Exemple : "20-10-2025" → "20 octobre 2025"
	 * 
	 * @param string $date_paris Date au format "DD-MM-YYYY".
	 * @return string Date formatée en français.
	 */
	private static function format_french_date( string $date_paris ): string {
		if ( empty( $date_paris ) ) {
			return '';
		}

		// Parser "DD-MM-YYYY"
		$parts = explode( '-', $date_paris );
		if ( count( $parts ) !== 3 ) {
			LoggingHelper::warning(
				'[YousignDataCollector] Invalid date format',
				array( 'date_paris' => $date_paris )
			);
			return $date_paris;
		}

		list( $day, $month, $year ) = $parts;

		// Créer timestamp
		$timestamp = mktime( 0, 0, 0, (int) $month, (int) $day, (int) $year );

		// Formater en français avec wp_date
		return \wp_date( 'j F Y', $timestamp );
	}

	/**
	 * Récupère les totaux du panier (HT, TTC et TVA)
	 * 
	 * Utilise CartBookingRetriever pour extraire les montants depuis WooCommerce cart.
	 * 
	 * @return array Array avec 'total_ht', 'total_ttc' et 'tva' formatés (ex: "150,00 €").
	 */
	private static function get_cart_totals(): array {
		$totals = CartBookingRetriever::get_cart_totals();

		LoggingHelper::debug(
			'[YousignDataCollector] Cart totals retrieved',
			array(
				'total_ht'  => $totals['total_ht'],
				'total_ttc' => $totals['total_ttc'],
				'tva'       => $totals['tva'],
			)
		);

		return $totals;
	}
}

