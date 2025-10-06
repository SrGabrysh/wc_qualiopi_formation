<?php
/**
 * SiretFormatter - Formatage des numéros SIRET/SIREN
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SiretFormatter
 * Utilitaires pour formater et valider les numéros SIRET/SIREN
 */
class SiretFormatter {

	/**
	 * Formate un numéro SIRET (14 chiffres avec espaces)
	 *
	 * @param string $siret SIRET brut.
	 * @return string SIRET formaté.
	 */
	public static function format_siret( $siret ) {
		if ( empty( $siret ) ) {
			return '';
		}

		// Nettoyer le SIRET
		$clean_siret = self::clean_siret( $siret );

		// Vérifier la longueur
		if ( strlen( $clean_siret ) !== 14 ) {
			return $siret; // Retourner l'original si invalide
		}

		// Formater : 123 456 789 01234
		return substr( $clean_siret, 0, 3 ) . ' ' .
			   substr( $clean_siret, 3, 3 ) . ' ' .
			   substr( $clean_siret, 6, 3 ) . ' ' .
			   substr( $clean_siret, 9, 5 );
	}

	/**
	 * Formate un numéro SIREN (9 chiffres avec espaces)
	 *
	 * @param string $siren SIREN brut.
	 * @return string SIREN formaté.
	 */
	public static function format_siren( $siren ) {
		if ( empty( $siren ) ) {
			return '';
		}

		// Nettoyer le SIREN
		$clean_siren = self::clean_siren( $siren );

		// Vérifier la longueur
		if ( strlen( $clean_siren ) !== 9 ) {
			return $siren; // Retourner l'original si invalide
		}

		// Formater : 123 456 789
		return substr( $clean_siren, 0, 3 ) . ' ' .
			   substr( $clean_siren, 3, 3 ) . ' ' .
			   substr( $clean_siren, 6, 3 );
	}

	/**
	 * Nettoie un numéro SIRET (supprime espaces et caractères non numériques)
	 *
	 * @param string $siret SIRET brut.
	 * @return string SIRET nettoyé.
	 */
	public static function clean_siret( $siret ) {
		if ( empty( $siret ) ) {
			return '';
		}

		// Supprimer tous les caractères non numériques
		return preg_replace( '/[^0-9]/', '', $siret );
	}

	/**
	 * Nettoie un numéro SIREN (supprime espaces et caractères non numériques)
	 *
	 * @param string $siren SIREN brut.
	 * @return string SIREN nettoyé.
	 */
	public static function clean_siren( $siren ) {
		if ( empty( $siren ) ) {
			return '';
		}

		// Supprimer tous les caractères non numériques
		return preg_replace( '/[^0-9]/', '', $siren );
	}

	/**
	 * Valide le format d'un numéro SIRET
	 *
	 * @param string $siret SIRET à valider.
	 * @return bool True si valide.
	 */
	public static function is_valid_siret( $siret ) {
		if ( empty( $siret ) ) {
			return false;
		}

		$clean_siret = self::clean_siret( $siret );

		// SIRET doit faire exactement 14 chiffres
		if ( strlen( $clean_siret ) !== 14 ) {
			return false;
		}

		// Vérifier que ce sont bien des chiffres
		if ( ! ctype_digit( $clean_siret ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Valide le format d'un numéro SIREN
	 *
	 * @param string $siren SIREN à valider.
	 * @return bool True si valide.
	 */
	public static function is_valid_siren( $siren ) {
		if ( empty( $siren ) ) {
			return false;
		}

		$clean_siren = self::clean_siren( $siren );

		// SIREN doit faire exactement 9 chiffres
		if ( strlen( $clean_siren ) !== 9 ) {
			return false;
		}

		// Vérifier que ce sont bien des chiffres
		if ( ! ctype_digit( $clean_siren ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Extrait le SIREN d'un SIRET
	 *
	 * @param string $siret SIRET complet.
	 * @return string SIREN extrait.
	 */
	public static function extract_siren_from_siret( $siret ) {
		if ( empty( $siret ) ) {
			return '';
		}

		$clean_siret = self::clean_siret( $siret );

		// SIREN = 9 premiers chiffres du SIRET
		if ( strlen( $clean_siret ) >= 9 ) {
			return substr( $clean_siret, 0, 9 );
		}

		return '';
	}

	/**
	 * Extrait le numéro d'établissement d'un SIRET
	 *
	 * @param string $siret SIRET complet.
	 * @return string Numéro d'établissement.
	 */
	public static function extract_etablissement_from_siret( $siret ) {
		if ( empty( $siret ) ) {
			return '';
		}

		$clean_siret = self::clean_siret( $siret );

		// Numéro d'établissement = 5 derniers chiffres du SIRET
		if ( strlen( $clean_siret ) === 14 ) {
			return substr( $clean_siret, 9, 5 );
		}

		return '';
	}

	/**
	 * Formate un SIRET pour l'affichage (avec validation)
	 *
	 * @param string $siret SIRET brut.
	 * @return array ['formatted' => string, 'valid' => bool, 'error' => string|null].
	 */
	public static function format_siret_with_validation( $siret ) {
		if ( empty( $siret ) ) {
			return array(
				'formatted' => '',
				'valid'     => false,
				'error'     => 'Le SIRET ne peut pas être vide.',
			);
		}

		$clean_siret = self::clean_siret( $siret );

		if ( ! self::is_valid_siret( $clean_siret ) ) {
			return array(
				'formatted' => $siret,
				'valid'     => false,
				'error'     => 'Le SIRET doit contenir exactement 14 chiffres.',
			);
		}

		return array(
			'formatted' => self::format_siret( $clean_siret ),
			'valid'     => true,
			'error'     => null,
		);
	}

	/**
	 * Formate un SIREN pour l'affichage (avec validation)
	 *
	 * @param string $siren SIREN brut.
	 * @return array ['formatted' => string, 'valid' => bool, 'error' => string|null].
	 */
	public static function format_siren_with_validation( $siren ) {
		if ( empty( $siren ) ) {
			return array(
				'formatted' => '',
				'valid'     => false,
				'error'     => 'Le SIREN ne peut pas être vide.',
			);
		}

		$clean_siren = self::clean_siren( $siren );

		if ( ! self::is_valid_siren( $clean_siren ) ) {
			return array(
				'formatted' => $siren,
				'valid'     => false,
				'error'     => 'Le SIREN doit contenir exactement 9 chiffres.',
			);
		}

		return array(
			'formatted' => self::format_siren( $clean_siren ),
			'valid'     => true,
			'error'     => null,
		);
	}
}
