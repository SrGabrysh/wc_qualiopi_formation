<?php
/**
 * MentionsHelper - Helpers pour extraction de données des mentions
 *
 * @package WcQualiopiFormation\Form\MentionsLegales
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\DataHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MentionsHelper
 * Utilitaires pour extraire et formater les données des mentions légales
 */
class MentionsHelper {

	/**
	 * Extrait l'adresse sans code postal et ville
	 *
	 * @param array $etablissement Données établissement.
	 * @return string Adresse (numéro + voie + complément).
	 */
	public static function get_adresse_sans_cp_ville( $etablissement ) {
		$parts = array();

		// Numéro de voie.
		$numero = DataHelper::extract_field_value( $etablissement, 'numero_voie', '' );
		if ( ! empty( $numero ) ) {
			$parts[] = $numero;
		}

		// Type de voie + libellé (ex: "Rue de la Paix").
		$type_voie = DataHelper::extract_field_value( $etablissement, 'type_voie', '' );
		$libelle_voie = DataHelper::extract_field_value( $etablissement, 'libelle_voie', '' );

		if ( ! empty( $type_voie ) && ! empty( $libelle_voie ) ) {
			$parts[] = trim( $type_voie . ' ' . $libelle_voie );
		} elseif ( ! empty( $libelle_voie ) ) {
			$parts[] = $libelle_voie;
		}

		// Complément d'adresse.
		$complement = DataHelper::extract_field_value( $etablissement, 'complement_adresse', '' );
		if ( ! empty( $complement ) ) {
			$parts[] = $complement;
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Extrait le code postal
	 *
	 * @param array $etablissement Données établissement.
	 * @return string Code postal.
	 */
	public static function get_code_postal( $etablissement ) {
		return DataHelper::extract_field_value( $etablissement, 'code_postal', '' );
	}

	/**
	 * Extrait la ville
	 *
	 * @param array $etablissement Données établissement.
	 * @return string Ville.
	 */
	public static function get_ville( $etablissement ) {
		return DataHelper::extract_field_value( $etablissement, 'libelle_commune', '' );
	}

	/**
	 * Extrait et formate la forme juridique
	 *
	 * @param string $categorie_juridique Code catégorie juridique.
	 * @return string Forme juridique lisible.
	 */
	public static function get_forme_juridique( $categorie_juridique ) {
		if ( empty( $categorie_juridique ) ) {
			return '';
		}

		// Mapping des codes vers formes juridiques courantes.
		$mapping = array(
			'5499' => 'SARL',
			'5710' => 'SAS',
			'5720' => 'SASU',
			'5505' => 'SA',
			'5498' => 'EURL',
			'6540' => 'SCI',
			'1000' => 'Entrepreneur individuel',
		);

		if ( isset( $mapping[ $categorie_juridique ] ) ) {
			return $mapping[ $categorie_juridique ];
		}

		// Si non trouvé, retourner le code brut.
		return $categorie_juridique;
	}

	/**
	 * Extrait la dénomination de l'entreprise
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Dénomination.
	 */
	public static function get_denomination( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'denomination', '' );
	}

	/**
	 * Extrait le nom (pour EI)
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Nom.
	 */
	public static function get_nom( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'nom', '' );
	}

	/**
	 * Extrait le prénom (pour EI)
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Prénom.
	 */
	public static function get_prenom( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'prenom', '' );
	}

	/**
	 * Extrait le capital social
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return float|null Capital ou null.
	 */
	public static function get_capital( $unite_legale ) {
		$capital = DataHelper::extract_field_value( $unite_legale, 'capital', null );
		
		if ( is_null( $capital ) ) {
			return null;
		}

		return (float) $capital;
	}

	/**
	 * Vérifie si la forme juridique est une société à capital
	 *
	 * @param string $forme_juridique Forme juridique.
	 * @return bool True si société à capital.
	 */
	public static function is_societe_capital( $forme_juridique ) {
		$formes_capital = array( 'SARL', 'EURL', 'SAS', 'SASU', 'SA' );
		$forme_upper = strtoupper( trim( $forme_juridique ) );

		foreach ( $formes_capital as $forme ) {
			if ( strpos( $forme_upper, $forme ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Construit une adresse complète formatée
	 *
	 * @param array $etablissement Données établissement.
	 * @return string Adresse complète.
	 */
	public static function get_adresse_complete( $etablissement ) {
		$parts = array();

		// Adresse sans CP/Ville.
		$adresse = self::get_adresse_sans_cp_ville( $etablissement );
		if ( ! empty( $adresse ) ) {
			$parts[] = $adresse;
		}

		// CP + Ville.
		$cp = self::get_code_postal( $etablissement );
		$ville = self::get_ville( $etablissement );

		if ( ! empty( $cp ) && ! empty( $ville ) ) {
			$parts[] = $cp . ' ' . $ville;
		} elseif ( ! empty( $ville ) ) {
			$parts[] = $ville;
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Extrait le code APE
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Code APE.
	 */
	public static function get_code_ape( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'activite_principale', '' );
	}

	/**
	 * Extrait le libellé APE
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Libellé APE.
	 */
	public static function get_libelle_ape( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'nomenclature_activite_principale', '' );
	}

	/**
	 * Extrait la date de création
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return string Date de création (ISO).
	 */
	public static function get_date_creation( $unite_legale ) {
		return DataHelper::extract_field_value( $unite_legale, 'date_creation', '' );
	}

	/**
	 * Vérifie si l'entreprise est active
	 *
	 * @param array $unite_legale Données unité légale.
	 * @return bool True si active.
	 */
	public static function is_active( $unite_legale ) {
		$etat = DataHelper::extract_field_value( $unite_legale, 'etat_administratif', 'A' );
		return $etat === 'A' || $etat === 'actif';
	}
}




