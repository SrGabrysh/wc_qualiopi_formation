<?php
/**
 * Company Data Retriever
 * 
 * RESPONSABILITÉ UNIQUE : Récupération des données entreprise (SIRET, adresse)
 * 
 * @package WcQualiopiFormation\Data\Retrieval
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Retrieval;

use WcQualiopiFormation\Data\ProgressTracker;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CompanyDataRetriever
 * 
 * Principe SRP : Données entreprise UNIQUEMENT
 */
class CompanyDataRetriever {

	/**
	 * Get company data for checkout
	 * 
	 * Extracts and formats company data (name, address, SIRET)
	 * 
	 * @param string $token Token identifier
	 * @return array Company data
	 */
	public static function get( string $token ): array {
		$collected_data = self::get_collected_data( $token );

		if ( ! $collected_data || ! isset( $collected_data['company'] ) ) {
			return array();
		}

		$company = $collected_data['company'];

		return array(
			'company_name' => $company['name'] ?? '',
			'siret'        => $company['siret'] ?? '',
			'address'      => $company['address'] ?? '',
			'address_2'    => $company['address_2'] ?? '',
			'city'         => $company['city'] ?? '',
			'postcode'     => $company['postal_code'] ?? '',
			'country'      => $company['country'] ?? 'FR',
			'state'        => $company['state'] ?? '',
			'legal_form'   => $company['legal_form'] ?? '',
		);
	}

	/**
	 * Get WooCommerce billing fields mapping for company data
	 * 
	 * @param array $company Company data
	 * @return array WC billing field mapping
	 */
	public static function get_wc_billing_mapping( array $company ): array {
		$mapping = array();

		if ( ! empty( $company['company_name'] ) ) {
			$mapping['billing_company'] = $company['company_name'];
		}

		if ( ! empty( $company['address'] ) ) {
			$mapping['billing_address_1'] = $company['address'];
		}

		if ( ! empty( $company['address_2'] ) ) {
			$mapping['billing_address_2'] = $company['address_2'];
		}

		if ( ! empty( $company['city'] ) ) {
			$mapping['billing_city'] = $company['city'];
		}

		if ( ! empty( $company['postcode'] ) ) {
			$mapping['billing_postcode'] = $company['postcode'];
		}

		if ( ! empty( $company['country'] ) ) {
			$mapping['billing_country'] = $company['country'];
		}

		if ( ! empty( $company['state'] ) ) {
			$mapping['billing_state'] = $company['state'];
		}

		return $mapping;
	}

	/**
	 * Check if company data is complete
	 * 
	 * @param string $token Token identifier
	 * @return bool True if complete
	 */
	public static function is_complete( string $token ): bool {
		$company = self::get( $token );

		$required_fields = array( 'company_name', 'address', 'city', 'postcode', 'country' );

		foreach ( $required_fields as $field ) {
			if ( empty( $company[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get missing company fields
	 * 
	 * @param string $token Token identifier
	 * @return array Missing fields with labels
	 */
	public static function get_missing_fields( string $token ): array {
		$company = self::get( $token );
		$missing = array();

		$required_fields = array(
			'company_name' => 'Nom de l\'entreprise',
			'address'      => 'Adresse',
			'city'         => 'Ville',
			'postcode'     => 'Code postal',
			'country'      => 'Pays',
		);

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $company[ $field ] ) ) {
				$missing[ $field ] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Get data quality score for company fields
	 * 
	 * @param string $token Token identifier
	 * @return array Array with 'filled' and 'total' counts
	 */
	public static function get_quality_score( string $token ): array {
		$company = self::get( $token );

		$fields = array( 'company_name', 'siret', 'address', 'city', 'postcode', 'country' );
		$filled = 0;

		foreach ( $fields as $field ) {
			$field_key = $field === 'postcode' ? 'postcode' : $field;
			if ( ! empty( $company[ $field_key ] ) ) {
				$filled++;
			}
		}

		return array(
			'filled' => $filled,
			'total'  => count( $fields ),
		);
	}

	/**
	 * Get collected data from progress
	 * 
	 * @param string $token Token identifier
	 * @return array|null Collected data or null
	 */
	private static function get_collected_data( string $token ): ?array {
		$progress = ProgressTracker::get_progress( $token );

		if ( ! $progress || empty( $progress['collected_data'] ) ) {
			return null;
		}

		return $progress['collected_data'];
	}
}

