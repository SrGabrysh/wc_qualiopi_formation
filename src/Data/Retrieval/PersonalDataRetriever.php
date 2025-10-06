<?php
/**
 * Personal Data Retriever
 * 
 * RESPONSABILITÉ UNIQUE : Récupération des données personnelles (nom, email, phone)
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
 * Classe PersonalDataRetriever
 * 
 * Principe SRP : Données personnelles UNIQUEMENT
 */
class PersonalDataRetriever {

	/**
	 * Get personal data for checkout
	 * 
	 * Extracts and formats personal data (name, email, phone)
	 * 
	 * @param string $token Token identifier
	 * @return array Personal data
	 */
	public static function get( string $token ): array {
		$collected_data = self::get_collected_data( $token );

		if ( ! $collected_data || ! isset( $collected_data['personal'] ) ) {
			return array();
		}

		$personal = $collected_data['personal'];

		return array(
			'first_name' => $personal['first_name'] ?? '',
			'last_name'  => $personal['last_name'] ?? '',
			'email'      => $personal['email'] ?? '',
			'phone'      => $personal['phone'] ?? '',
		);
	}

	/**
	 * Get WooCommerce billing fields mapping for personal data
	 * 
	 * @param array $personal Personal data
	 * @return array WC billing field mapping
	 */
	public static function get_wc_billing_mapping( array $personal ): array {
		$mapping = array();

		if ( ! empty( $personal['first_name'] ) ) {
			$mapping['billing_first_name'] = $personal['first_name'];
		}

		if ( ! empty( $personal['last_name'] ) ) {
			$mapping['billing_last_name'] = $personal['last_name'];
		}

		if ( ! empty( $personal['email'] ) ) {
			$mapping['billing_email'] = $personal['email'];
		}

		if ( ! empty( $personal['phone'] ) ) {
			$mapping['billing_phone'] = $personal['phone'];
		}

		return $mapping;
	}

	/**
	 * Check if personal data is complete
	 * 
	 * @param string $token Token identifier
	 * @return bool True if complete
	 */
	public static function is_complete( string $token ): bool {
		$personal = self::get( $token );

		$required_fields = array( 'first_name', 'last_name', 'email' );

		foreach ( $required_fields as $field ) {
			if ( empty( $personal[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get missing personal fields
	 * 
	 * @param string $token Token identifier
	 * @return array Missing fields with labels
	 */
	public static function get_missing_fields( string $token ): array {
		$personal = self::get( $token );
		$missing  = array();

		$required_fields = array(
			'first_name' => 'Prénom',
			'last_name'  => 'Nom',
			'email'      => 'Email',
		);

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $personal[ $field ] ) ) {
				$missing[ $field ] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Get data quality score for personal fields
	 * 
	 * @param string $token Token identifier
	 * @return array Array with 'filled' and 'total' counts
	 */
	public static function get_quality_score( string $token ): array {
		$personal = self::get( $token );

		$fields = array( 'first_name', 'last_name', 'email', 'phone' );
		$filled = 0;

		foreach ( $fields as $field ) {
			if ( ! empty( $personal[ $field ] ) ) {
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

