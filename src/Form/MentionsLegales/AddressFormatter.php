<?php
/**
 * Formatage des adresses d'entreprise
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

defined( 'ABSPATH' ) || exit;

/**
 * Classe de formatage des adresses
 */
class AddressFormatter {

	/**
	 * Formate une adresse complète
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @return string Adresse formatée.
	 */
	public function format_complete( $company_data ) {
		$parts = array();

		if ( ! empty( $company_data['adresse_numero'] ) ) {
			$parts[] = $company_data['adresse_numero'];
		}

		if ( ! empty( $company_data['adresse_voie'] ) ) {
			$parts[] = $company_data['adresse_voie'];
		}

		$rue = implode( ' ', $parts );

		if ( ! empty( $company_data['adresse_complement'] ) ) {
			$rue .= ', ' . $company_data['adresse_complement'];
		}

		$cp_ville = array();
		if ( ! empty( $company_data['adresse_cp'] ) ) {
			$cp_ville[] = $company_data['adresse_cp'];
		}
		if ( ! empty( $company_data['adresse_ville'] ) ) {
			$cp_ville[] = $company_data['adresse_ville'];
		}

		$adresse_complete = $rue;
		if ( ! empty( $cp_ville ) ) {
			$adresse_complete .= ', ' . implode( ' ', $cp_ville );
		}

		$adresse_complete .= ', France';

		return $adresse_complete;
	}

	/**
	 * Formate une adresse sans code postal ni ville
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @return string Adresse formatée (rue uniquement).
	 */
	public function format_without_city( $company_data ) {
		$parts = array();

		if ( ! empty( $company_data['adresse_numero'] ) ) {
			$parts[] = $company_data['adresse_numero'];
		}

		if ( ! empty( $company_data['adresse_voie'] ) ) {
			$parts[] = $company_data['adresse_voie'];
		}

		if ( ! empty( $company_data['adresse_complement'] ) ) {
			$parts[] = $company_data['adresse_complement'];
		}

		return implode( ' ', $parts );
	}
}

