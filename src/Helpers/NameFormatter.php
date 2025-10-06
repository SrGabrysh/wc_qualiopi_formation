<?php
/**
 * NameFormatter - Formatage des noms et prénoms
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NameFormatter
 * Utilitaires pour formater correctement les noms et prénoms
 */
class NameFormatter {

	/**
	 * Formate un nom ou prénom selon les règles françaises (AVEC VALIDATION)
	 * Compatible avec le code source gravity_forms_siren_autocomplete
	 *
	 * @param string $input Le nom/prénom à formater.
	 * @return array ['value' => string, 'valid' => bool, 'error' => string|null].
	 */
	public static function format( $input ) {
		if ( empty( $input ) ) {
			return array(
				'value' => '',
				'valid' => false,
				'error' => 'Le champ ne peut pas être vide.',
			);
		}

		// Étape 1 : Validation (refus des chiffres).
		if ( preg_match( '/\d/', $input ) ) {
			return array(
				'value' => $input,
				'valid' => false,
				'error' => 'Les chiffres ne sont pas autorisés dans les noms et prénoms.',
			);
		}

		// Étape 2 : Nettoyage via clean() existant.
		$cleaned = self::clean( $input );

		// Étape 3 : Capitalisation première lettre.
		$formatted = self::capitalize_first( $cleaned );

		return array(
			'value' => $formatted,
			'valid' => true,
			'error' => null,
		);
	}

	/**
	 * Formate un prénom (première lettre majuscule, reste minuscule)
	 *
	 * @param string $prenom Prénom brut.
	 * @return string Prénom formaté.
	 */
	public static function format_prenom( $prenom ) {
		$result = self::format( $prenom );
		return $result['valid'] ? $result['value'] : '';
	}

	/**
	 * Formate un nom (tout en majuscules)
	 *
	 * @param string $nom Nom brut.
	 * @return string Nom formaté.
	 */
	public static function format_nom( $nom ) {
		$result = self::format( $nom );
		if ( ! $result['valid'] ) {
			return '';
		}
		
		// Pour les noms : tout en majuscules
		return mb_strtoupper( $result['value'], 'UTF-8' );
	}

	/**
	 * Formate un nom complet (Prénom NOM)
	 *
	 * @param string $prenom Prénom brut.
	 * @param string $nom Nom brut.
	 * @return string Nom complet formaté.
	 */
	public static function format_nom_complet( $prenom, $nom ) {
		$prenom_fmt = self::format_prenom( $prenom );
		$nom_fmt    = self::format_nom( $nom );

		$parts = array_filter( array( $prenom_fmt, $nom_fmt ) );

		return implode( ' ', $parts );
	}

	/**
	 * Formate pour mentions légales (NOM Prénom)
	 *
	 * @param string $prenom Prénom brut.
	 * @param string $nom Nom brut.
	 * @return string Format mentions légales.
	 */
	public static function format_mentions_legales( $prenom, $nom ) {
		$prenom_fmt = self::format_prenom( $prenom );
		$nom_fmt    = self::format_nom( $nom );

		$parts = array_filter( array( $nom_fmt, $prenom_fmt ) );

		return implode( ' ', $parts );
	}

	/**
	 * Détecte si un nom contient une particule (de, du, von, etc.)
	 *
	 * @param string $nom Nom à analyser.
	 * @return bool True si particule détectée.
	 */
	public static function has_particule( $nom ) {
		$particules = array( 'de', 'du', 'des', 'von', 'van', 'le', 'la' );
		$words      = explode( ' ', strtolower( trim( $nom ) ) );

		foreach ( $particules as $particule ) {
			if ( in_array( $particule, $words, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Formate un nom avec particule (de Gaulle, von Neumann)
	 *
	 * @param string $nom Nom complet avec particule.
	 * @return string Nom formaté.
	 */
	public static function format_nom_particule( $nom ) {
		if ( empty( $nom ) ) {
			return '';
		}

		$words = explode( ' ', trim( $nom ) );
		$formatted = array();

		foreach ( $words as $word ) {
			$word_lower = strtolower( $word );

			// Particules en minuscules.
			if ( in_array( $word_lower, array( 'de', 'du', 'des', 'le', 'la' ), true ) ) {
				$formatted[] = $word_lower;
			} else {
				$formatted[] = mb_strtoupper( $word, 'UTF-8' );
			}
		}

		return implode( ' ', $formatted );
	}

	/**
	 * Nettoie et normalise un nom/prénom
	 *
	 * @param string $name Nom/prénom brut.
	 * @return string Nom nettoyé.
	 */
	public static function clean( $name ) {
		if ( empty( $name ) ) {
			return '';
		}

		// Supprimer espaces multiples.
		$name = preg_replace( '/\s+/', ' ', $name );

		// Supprimer caractères spéciaux (sauf tirets, espaces, apostrophes).
		$name = preg_replace( '/[^a-zA-ZÀ-ÿ\s\'-]/', '', $name );

		return trim( $name );
	}

	/**
	 * Extrait le prénom et le nom d'un nom complet
	 *
	 * @param string $nom_complet Nom complet (Prénom NOM ou NOM Prénom).
	 * @return array ['prenom' => string, 'nom' => string].
	 */
	public static function split_nom_complet( $nom_complet ) {
		$nom_complet = self::clean( $nom_complet );

		if ( empty( $nom_complet ) ) {
			return array(
				'prenom' => '',
				'nom'    => '',
			);
		}

		$parts = explode( ' ', $nom_complet );

		// Si un seul mot, considérer comme nom.
		if ( count( $parts ) === 1 ) {
			return array(
				'prenom' => '',
				'nom'    => $parts[0],
			);
		}

		// Sinon, premier mot = prénom, reste = nom.
		$prenom = array_shift( $parts );
		$nom    = implode( ' ', $parts );

		return array(
			'prenom' => $prenom,
			'nom'    => $nom,
		);
	}

	/**
	 * Met la première lettre en majuscule (UTF-8 safe)
	 *
	 * @param string $string Chaîne à traiter.
	 * @return string Chaîne avec première lettre en majuscule.
	 */
	private static function capitalize_first( $string ) {
		if ( empty( $string ) ) {
			return '';
		}

		$first = mb_substr( $string, 0, 1, 'UTF-8' );
		$rest  = mb_substr( $string, 1, null, 'UTF-8' );

		return mb_strtoupper( $first, 'UTF-8' ) . $rest;
	}

	/**
	 * Formate les initiales (J.P. pour Jean-Pierre)
	 *
	 * @param string $prenom Prénom.
	 * @return string Initiales formatées.
	 */
	public static function get_initiales( $prenom ) {
		if ( empty( $prenom ) ) {
			return '';
		}

		$words = preg_split( '/[\s\-]+/', $prenom );
		$initiales = array();

		foreach ( $words as $word ) {
			$first = mb_substr( $word, 0, 1, 'UTF-8' );
			$initiales[] = mb_strtoupper( $first, 'UTF-8' ) . '.';
		}

		return implode( '', $initiales );
	}
}

