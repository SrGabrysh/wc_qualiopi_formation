<?php
/**
 * Data Retriever Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des retrievers spécialisés
 * 
 * Retrieves collected data for checkout autofill
 * Delegates to specialized retrievers (Personal, Company, Booking)
 *
 * @package WcQualiopiFormation\Data
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Data\Retrieval\PersonalDataRetriever;
use WcQualiopiFormation\Data\Retrieval\CompanyDataRetriever;
use WcQualiopiFormation\Data\Retrieval\BookingDataRetriever;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataRetriever (Manager)
 * 
 * Orchestrates specialized data retrievers
 */
class DataRetriever {

	/**
	 * Get collected data by token
	 * 
	 * @param string $token Token identifier
	 * @return array|null Collected data or null if not found
	 */
	public static function get_collected_data( string $token ): ?array {
		$progress = ProgressTracker::get_progress( $token );

		if ( ! $progress || empty( $progress['collected_data'] ) ) {
			return null;
		}

		return $progress['collected_data'];
	}

	/**
	 * Get personal data - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array Personal data
	 */
	public static function get_personal_data( string $token ): array {
		return PersonalDataRetriever::get( $token );
	}

	/**
	 * Get company data - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array Company data
	 */
	public static function get_company_data( string $token ): array {
		return CompanyDataRetriever::get( $token );
	}

	/**
	 * Get booking data - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array Booking data
	 */
	public static function get_booking_data( string $token ): array {
		return BookingDataRetriever::get_booking( $token );
	}

	/**
	 * Get form data - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array|null Form data
	 */
	public static function get_form_data( string $token ): ?array {
		return BookingDataRetriever::get_form_data( $token );
	}

	/**
	 * Get test answers - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array Test answers
	 */
	public static function get_test_answers( string $token ): array {
		return BookingDataRetriever::get_test_answers( $token );
	}

	/**
	 * Get complete profile - DELEGATED
	 * 
	 * @param string $token Token identifier
	 * @return array Complete profile
	 */
	public static function get_complete_profile( string $token ): array {
		return BookingDataRetriever::get_complete_profile( $token );
	}

	/**
	 * Get WooCommerce checkout field mapping
	 * 
	 * Maps collected data to WooCommerce checkout fields
	 * Combines personal and company mappings
	 * 
	 * @param string $token Token identifier
	 * @return array Array of WC field => value
	 */
	public static function get_wc_checkout_mapping( string $token ): array {
		$personal = PersonalDataRetriever::get( $token );
		$company  = CompanyDataRetriever::get( $token );

		// Combine personal and company mappings
		$mapping = array_merge(
			PersonalDataRetriever::get_wc_billing_mapping( $personal ),
			CompanyDataRetriever::get_wc_billing_mapping( $company )
		);

		/**
		 * Filter checkout field mapping
		 * 
		 * @param array  $mapping WC field mapping
		 * @param string $token   Token identifier
		 * @param array  $personal Personal data
		 * @param array  $company Company data
		 */
		return apply_filters( 'wcqf_checkout_field_mapping', $mapping, $token, $personal, $company );
	}

	/**
	 * Check if data is complete for checkout
	 * 
	 * Verifies both personal and company data completeness
	 * 
	 * @param string $token Token identifier
	 * @return bool True if complete
	 */
	public static function is_complete_for_checkout( string $token ): bool {
		$personal_complete = PersonalDataRetriever::is_complete( $token );
		$company_complete  = CompanyDataRetriever::is_complete( $token );

		return $personal_complete && $company_complete;
	}

	/**
	 * Get missing required fields
	 * 
	 * Returns list of missing required fields for checkout
	 * Combines personal and company missing fields
	 * 
	 * @param string $token Token identifier
	 * @return array Array of missing field paths
	 */
	public static function get_missing_fields( string $token ): array {
		$personal_missing = PersonalDataRetriever::get_missing_fields( $token );
		$company_missing  = CompanyDataRetriever::get_missing_fields( $token );

		return array_merge( $personal_missing, $company_missing );
	}

	/**
	 * Get data quality score
	 * 
	 * Calculates completeness score (0-100)
	 * Combines personal and company quality scores
	 * 
	 * @param string $token Token identifier
	 * @return int Quality score (0-100)
	 */
	public static function get_data_quality_score( string $token ): int {
		$personal_score = PersonalDataRetriever::get_quality_score( $token );
		$company_score  = CompanyDataRetriever::get_quality_score( $token );

		$total_fields  = $personal_score['total'] + $company_score['total'];
		$filled_fields = $personal_score['filled'] + $company_score['filled'];

		if ( $total_fields === 0 ) {
			return 0;
		}

		return (int) round( ( $filled_fields / $total_fields ) * 100 );
	}

	/**
	 * Get progress summary
	 * 
	 * Returns human-readable progress summary
	 * 
	 * @param string $token Token identifier
	 * @return array Progress summary
	 */
	public static function get_progress_summary( string $token ): array {
		$progress = ProgressTracker::get_progress( $token );

		if ( ! $progress ) {
			return array(
				'status'         => 'not_found',
				'message'        => 'Aucune progression trouvée',
				'current_step'   => '',
				'data_quality'   => 0,
				'is_complete'    => false,
				'missing_fields' => array(),
			);
		}

		$step_labels = array(
			Constants::STEP_CART      => 'Panier',
			Constants::STEP_FORM      => 'Formulaire',
			Constants::STEP_CHECKOUT  => 'Paiement',
			Constants::STEP_COMPLETED => 'Terminé',
		);

		return array(
			'status'             => $progress['is_completed'] ? 'completed' : 'in_progress',
			'message'            => $progress['is_completed'] ? 'Parcours terminé' : 'En cours',
			'current_step'       => $progress['current_step'],
			'current_step_label' => $step_labels[ $progress['current_step'] ] ?? 'Inconnu',
			'data_quality'       => self::get_data_quality_score( $token ),
			'is_complete'        => self::is_complete_for_checkout( $token ),
			'missing_fields'     => self::get_missing_fields( $token ),
			'started_at'         => $progress['started_at'],
			'last_activity'      => $progress['last_activity'],
		);
	}
}
