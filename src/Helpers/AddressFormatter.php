<?php
/**
 * AddressFormatter - Formatage des adresses
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AddressFormatter
 * Utilitaires pour formater correctement les adresses
 */
class AddressFormatter {

	/**
	 * Formate une adresse complète selon les standards français
	 *
	 * @param array $address_data Données d'adresse.
	 * @return string Adresse formatée.
	 */
	public static function format_complete_address( $address_data ) {
		if ( empty( $address_data ) || ! is_array( $address_data ) ) {
			return '';
		}

		$parts = array();

		// Numéro et type de voie
		if ( ! empty( $address_data['numero_voie'] ) ) {
			$parts[] = $address_data['numero_voie'];
		}

		if ( ! empty( $address_data['type_voie'] ) ) {
			$parts[] = $address_data['type_voie'];
		}

		if ( ! empty( $address_data['libelle_voie'] ) ) {
			$parts[] = $address_data['libelle_voie'];
		}

		// Complément d'adresse
		if ( ! empty( $address_data['complement_adresse'] ) ) {
			$parts[] = $address_data['complement_adresse'];
		}

		// Code postal et ville
		$postal_city = self::format_postal_city( $address_data );
		if ( ! empty( $postal_city ) ) {
			$parts[] = $postal_city;
		}

		return implode( ' ', array_filter( $parts ) );
	}

	/**
	 * Formate le code postal et la ville
	 *
	 * @param array $address_data Données d'adresse.
	 * @return string Code postal et ville formatés.
	 */
	public static function format_postal_city( $address_data ) {
		$postal_code = $address_data['code_postal'] ?? '';
		$city        = $address_data['libelle_commune'] ?? '';

		if ( empty( $postal_code ) && empty( $city ) ) {
			return '';
		}

		if ( empty( $postal_code ) ) {
			return $city;
		}

		if ( empty( $city ) ) {
			return $postal_code;
		}

		return $postal_code . ' ' . $city;
	}

	/**
	 * Nettoie et normalise une adresse
	 *
	 * @param string $address Adresse brute.
	 * @return string Adresse nettoyée.
	 */
	public static function clean_address( $address ) {
		if ( empty( $address ) ) {
			return '';
		}

		// Supprimer espaces multiples
		$address = preg_replace( '/\s+/', ' ', $address );

		// Supprimer caractères spéciaux non autorisés
		$address = preg_replace( '/[^\w\s\-\'\.]/', '', $address );

		return trim( $address );
	}

	/**
	 * Valide le format d'un code postal français
	 *
	 * @param string $postal_code Code postal.
	 * @return bool True si valide.
	 */
	public static function is_valid_postal_code( $postal_code ) {
		if ( empty( $postal_code ) ) {
			return false;
		}

		// Code postal français : 5 chiffres
		return preg_match( '/^\d{5}$/', $postal_code );
	}

	/**
	 * Extrait le code postal d'une adresse
	 *
	 * @param string $address Adresse complète.
	 * @return string Code postal extrait.
	 */
	public static function extract_postal_code( $address ) {
		if ( empty( $address ) ) {
			return '';
		}

		// Recherche pattern : 5 chiffres
		if ( preg_match( '/\b(\d{5})\b/', $address, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Extrait la ville d'une adresse
	 *
	 * @param string $address Adresse complète.
	 * @return string Ville extraite.
	 */
	public static function extract_city( $address ) {
		if ( empty( $address ) ) {
			return '';
		}

		// Supprimer le code postal pour extraire la ville
		$address_without_postal = preg_replace( '/\b\d{5}\b/', '', $address );
		$address_without_postal = trim( $address_without_postal );

		// Prendre le dernier mot (généralement la ville)
		$parts = explode( ' ', $address_without_postal );
		if ( ! empty( $parts ) ) {
			return end( $parts );
		}

		return '';
	}

	/**
	 * Formate une adresse pour les mentions légales
	 *
	 * @param array $address_data Données d'adresse.
	 * @return string Adresse formatée pour mentions légales.
	 */
	public static function format_for_mentions_legales( $address_data ) {
		$address = self::format_complete_address( $address_data );
		
		// Pour les mentions légales, on peut ajouter des retours à la ligne
		$postal_city = self::format_postal_city( $address_data );
		$street_part = str_replace( $postal_city, '', $address );
		$street_part = trim( $street_part );

		if ( ! empty( $street_part ) && ! empty( $postal_city ) ) {
			return $street_part . "\n" . $postal_city;
		}

		return $address;
	}
}
