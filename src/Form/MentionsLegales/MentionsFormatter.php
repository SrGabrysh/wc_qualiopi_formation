<?php
/**
 * MentionsFormatter - Formatage avancé des mentions légales
 *
 * @package WcQualiopiFormation\Form\MentionsLegales
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\DataHelper;
use WcQualiopiFormation\Helpers\NameFormatter;
use WcQualiopiFormation\Helpers\SiretFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MentionsFormatter
 * Formatage spécifique des mentions selon le type d'entreprise
 */
class MentionsFormatter {

	/**
	 * Formate les mentions pour une société à capital
	 *
	 * @param array $company_data Données entreprise.
	 * @param string $forme_juridique Forme juridique.
	 * @param array $representant_data Données représentant.
	 * @param bool $include_titre Inclure le titre du représentant.
	 * @return string Mentions formatées.
	 */
	public function format_societe_capital( $company_data, $forme_juridique, $representant_data = array(), $include_titre = false ) {
		$parts = array();

		// Dénomination + forme juridique.
		if ( ! empty( $company_data['denomination'] ) ) {
			$parts[] = $company_data['denomination'];
			
			if ( ! empty( $forme_juridique ) ) {
				$parts[] = $forme_juridique;
			}
		}

		// Capital social.
		if ( ! empty( $company_data['capital'] ) ) {
			$capital_fmt = number_format( (float) $company_data['capital'], 2, ',', ' ' );
			$parts[] = 'au capital de ' . $capital_fmt . ' €';
		}

		// Représentant légal si fourni.
		if ( ! empty( $representant_data['prenom'] ) && ! empty( $representant_data['nom'] ) ) {
			$titre = $include_titre ? $this->get_titre_representant( $forme_juridique ) : '';
			$nom_complet = NameFormatter::format_mentions_legales(
				$representant_data['prenom'],
				$representant_data['nom']
			);

			if ( ! empty( $titre ) ) {
				$parts[] = 'représentée par ' . $titre . ' ' . $nom_complet;
			} else {
				$parts[] = 'représentée par ' . $nom_complet;
			}
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Formate les mentions pour une personne morale
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Mentions formatées.
	 */
	public function format_personne_morale( $company_data ) {
		$parts = array();

		// Dénomination.
		if ( ! empty( $company_data['denomination'] ) ) {
			$parts[] = $company_data['denomination'];
		}

		// Forme juridique.
		if ( ! empty( $company_data['forme_juridique'] ) ) {
			$forme = MentionsHelper::get_forme_juridique( $company_data['forme_juridique'] );
			if ( ! empty( $forme ) ) {
				$parts[] = $forme;
			}
		}

		// SIREN.
		if ( ! empty( $company_data['siren'] ) ) {
			$siren_fmt = SiretFormatter::format_siren( $company_data['siren'] );
			$parts[] = 'SIREN ' . $siren_fmt;
		}

		// Adresse complète.
		$adresse = $this->format_adresse_complete( $company_data );
		if ( ! empty( $adresse ) ) {
			$parts[] = 'située ' . $adresse;
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Formate les mentions pour un entrepreneur individuel
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Mentions formatées.
	 */
	public function format_entrepreneur_individuel( $company_data ) {
		$parts = array();

		// Nom et prénom.
		if ( ! empty( $company_data['nom'] ) && ! empty( $company_data['prenom'] ) ) {
			$nom_complet = NameFormatter::format_mentions_legales(
				$company_data['prenom'],
				$company_data['nom']
			);
			$parts[] = $nom_complet;
		}

		// Dénomination commerciale si présente.
		if ( ! empty( $company_data['denomination'] ) ) {
			$parts[] = 'exerçant sous le nom commercial "' . $company_data['denomination'] . '"';
		}

		// SIRET.
		if ( ! empty( $company_data['siret'] ) ) {
			$siret_fmt = SiretFormatter::format_siret( $company_data['siret'] );
			$parts[] = 'SIRET ' . $siret_fmt;
		}

		// Adresse complète.
		$adresse = $this->format_adresse_complete( $company_data );
		if ( ! empty( $adresse ) ) {
			$parts[] = 'domicilié(e) ' . $adresse;
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Formate les mentions fallback (données incomplètes)
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Mentions formatées.
	 */
	public function format_fallback( $company_data ) {
		$parts = array();

		// Dénomination ou nom.
		if ( ! empty( $company_data['denomination'] ) ) {
			$parts[] = $company_data['denomination'];
		} elseif ( ! empty( $company_data['nom'] ) ) {
			$parts[] = $company_data['nom'];
		}

		// SIRET ou SIREN.
		if ( ! empty( $company_data['siret'] ) ) {
			$siret_fmt = SiretFormatter::format_siret( $company_data['siret'] );
			$parts[] = 'SIRET ' . $siret_fmt;
		} elseif ( ! empty( $company_data['siren'] ) ) {
			$siren_fmt = SiretFormatter::format_siren( $company_data['siren'] );
			$parts[] = 'SIREN ' . $siren_fmt;
		}

		// Adresse si disponible.
		$adresse = $this->format_adresse_complete( $company_data );
		if ( ! empty( $adresse ) ) {
			$parts[] = $adresse;
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Formate une adresse complète
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Adresse formatée.
	 */
	private function format_adresse_complete( $company_data ) {
		$parts = array();

		// Numéro de voie.
		if ( ! empty( $company_data['adresse_numero'] ) ) {
			$parts[] = $company_data['adresse_numero'];
		}

		// Voie.
		if ( ! empty( $company_data['adresse_voie'] ) ) {
			$parts[] = $company_data['adresse_voie'];
		}

		// Complément.
		if ( ! empty( $company_data['adresse_complement'] ) ) {
			$parts[] = $company_data['adresse_complement'];
		}

		// Code postal + Ville.
		$cp_ville = array();
		if ( ! empty( $company_data['adresse_cp'] ) ) {
			$cp_ville[] = $company_data['adresse_cp'];
		}
		if ( ! empty( $company_data['adresse_ville'] ) ) {
			$cp_ville[] = $company_data['adresse_ville'];
		}

		if ( ! empty( $cp_ville ) ) {
			$parts[] = implode( ' ', $cp_ville );
		}

		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Détermine le titre du représentant selon la forme juridique
	 *
	 * @param string $forme_juridique Forme juridique.
	 * @return string Titre (Gérant, Président, etc.).
	 */
	private function get_titre_representant( $forme_juridique ) {
		$forme_lower = strtolower( $forme_juridique );

		$titres = array(
			'sarl'   => 'Monsieur/Madame',
			'eurl'   => 'Monsieur/Madame',
			'sas'    => 'le Président',
			'sasu'   => 'le Président',
			'sa'     => 'le Président',
			'sci'    => 'le Gérant',
			'snc'    => 'le Gérant',
		);

		foreach ( $titres as $forme => $titre ) {
			if ( strpos( $forme_lower, $forme ) !== false ) {
				return $titre;
			}
		}

		return 'le représentant légal';
	}
}




