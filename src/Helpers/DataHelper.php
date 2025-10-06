<?php
/**
 * DataHelper - Utilitaires pour manipulation de données
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\SanitizationHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataHelper
 * Utilitaires pour extraction et formatage de données
 */
class DataHelper {

	/**
	 * Extrait une valeur d'un tableau avec fallback
	 *
	 * @param array  $data Tableau de données.
	 * @param string $key Clé à extraire.
	 * @param mixed  $default Valeur par défaut.
	 * @return mixed Valeur extraite ou default.
	 */
	public static function extract_field_value( $data, $key, $default = '' ) {
		if ( ! is_array( $data ) ) {
			return $default;
		}

		if ( isset( $data[ $key ] ) ) {
			$value = $data[ $key ];

			// Si la valeur est un tableau, essayer de récupérer la première valeur.
			if ( is_array( $value ) && ! empty( $value ) ) {
				return reset( $value );
			}

			return $value;
		}

		return $default;
	}

	/**
	 * Formate une date au format français
	 *
	 * @param string $date Date ISO (YYYY-MM-DD).
	 * @param string $format Format de sortie.
	 * @return string Date formatée ou chaîne vide.
	 */
	public static function format_date( $date, $format = 'd/m/Y' ) {
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = strtotime( $date );

		if ( false === $timestamp ) {
			return '';
		}

		return date( $format, $timestamp );
	}

	/**
	 * Formate un numéro de téléphone français
	 *
	 * @param string $phone Numéro brut.
	 * @return string Numéro formaté.
	 */
	public static function format_phone( $phone ) {
		$phone = preg_replace( '/[^0-9]/', '', $phone );

		if ( strlen( $phone ) === 10 ) {
			return implode( ' ', str_split( $phone, 2 ) );
		}

		return $phone;
	}

	/**
	 * Nettoie un SIRET (supprime espaces, tirets, etc.)
	 *
	 * @param string $siret SIRET brut.
	 * @return string SIRET nettoyé (14 chiffres).
	 */
	public static function clean_siret( $siret ) {
		return preg_replace( '/[^0-9]/', '', $siret );
	}

	/**
	 * Formate un SIRET pour affichage (XXX XXX XXX XXXXX)
	 *
	 * @param string $siret SIRET brut.
	 * @return string SIRET formaté.
	 */
	public static function format_siret( $siret ) {
		$clean = self::clean_siret( $siret );

		if ( strlen( $clean ) !== 14 ) {
			return $siret;
		}

		return substr( $clean, 0, 3 ) . ' ' .
		       substr( $clean, 3, 3 ) . ' ' .
		       substr( $clean, 6, 3 ) . ' ' .
		       substr( $clean, 9, 5 );
	}

	/**
	 * Formate un SIREN pour affichage (XXX XXX XXX)
	 *
	 * @param string $siren SIREN brut.
	 * @return string SIREN formaté.
	 */
	public static function format_siren( $siren ) {
		$clean = preg_replace( '/[^0-9]/', '', $siren );

		if ( strlen( $clean ) !== 9 ) {
			return $siren;
		}

		return substr( $clean, 0, 3 ) . ' ' .
		       substr( $clean, 3, 3 ) . ' ' .
		       substr( $clean, 6, 3 );
	}

	/**
	 * Sanitize une chaîne pour stockage BDD
	 *
	 * @param string $value Valeur brute.
	 * @return string Valeur nettoyée.
	 */
	public static function sanitize_text( $value ) {
		return SanitizationHelper::sanitize_name( trim( $value ) );
	}

	/**
	 * Sanitize un email
	 *
	 * @param string $email Email brut.
	 * @return string Email nettoyé.
	 */
	public static function sanitize_email( $email ) {
		return sanitize_email( trim( $email ) );
	}

	/**
	 * Vérifie si une chaîne est vide (null, '', '   ')
	 *
	 * @param mixed $value Valeur à tester.
	 * @return bool True si vide.
	 */
	public static function is_empty( $value ) {
		if ( is_null( $value ) ) {
			return true;
		}

		if ( is_string( $value ) && trim( $value ) === '' ) {
			return true;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Encode un tableau en JSON sécurisé
	 *
	 * @param array $data Données à encoder.
	 * @return string JSON ou chaîne vide si erreur.
	 */
	public static function encode_json( $data ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return '';
		}

		return $json;
	}

	/**
	 * Décode un JSON en tableau
	 *
	 * @param string $json JSON à décoder.
	 * @return array Tableau ou array vide si erreur.
	 */
	public static function decode_json( $json ) {
		if ( empty( $json ) || ! is_string( $json ) ) {
			return array();
		}

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array();
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Tronque un texte à une longueur maximale
	 *
	 * @param string $text Texte brut.
	 * @param int    $length Longueur max.
	 * @param string $suffix Suffixe si tronqué (ex: '...').
	 * @return string Texte tronqué.
	 */
	public static function truncate( $text, $length = 100, $suffix = '...' ) {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		return substr( $text, 0, $length ) . $suffix;
	}

	/**
	 * Génère un slug unique à partir d'un texte
	 *
	 * @param string $text Texte source.
	 * @return string Slug.
	 */
	public static function generate_slug( $text ) {
		return sanitize_title( $text );
	}

	/**
	 * Vérifie si une valeur est un email valide
	 *
	 * @param string $email Email à vérifier.
	 * @return bool True si valide.
	 */
	public static function is_valid_email( $email ) {
		return is_email( $email ) !== false;
	}

	/**
	 * Convertit une chaîne en booléen
	 *
	 * @param mixed $value Valeur à convertir.
	 * @return bool Booléen.
	 */
	public static function to_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( 'true', '1', 'yes', 'oui', 'on' ), true );
		}

		return (bool) $value;
	}
}




