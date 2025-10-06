<?php
/**
 * Détection et classification des types d'entreprise
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

defined( 'ABSPATH' ) || exit;

/**
 * Classe de détection du type d'entreprise
 */
class EntrepriseTypeDetector {

	/**
	 * Formes juridiques considérées comme sociétés à capital
	 *
	 * @var array
	 */
	private const SOCIETES_CAPITAL = array( 'SARL', 'EURL', 'SELARL', 'SAS', 'SASU', 'SA' );

	/**
	 * Vérifie si une forme juridique est une société à capital
	 *
	 * @param string $forme_juridique Forme juridique.
	 * @return bool True si société à capital.
	 */
	public function is_societe_capital( $forme_juridique ) {
		return in_array( strtoupper( $forme_juridique ), self::SOCIETES_CAPITAL, true );
	}

	/**
	 * Détermine le type d'entreprise à partir des données
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Type (pm|ei|inconnu).
	 */
	public function determine_type( $company_data ) {
		if ( ! empty( $company_data['denomination'] ) ) {
			return 'pm';
		}

		if ( ! empty( $company_data['nom'] ) && ! empty( $company_data['prenom'] ) ) {
			return 'ei';
		}

		return 'inconnu';
	}
}

