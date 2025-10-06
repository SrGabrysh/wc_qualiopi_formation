<?php
/**
 * Conversion des codes de catégories juridiques
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

defined( 'ABSPATH' ) || exit;

/**
 * Classe de conversion des codes juridiques
 */
class JuridicalCodeConverter {

	/**
	 * Mapping codes catégorie → formes juridiques
	 *
	 * @var array
	 */
	private const FORMES_JURIDIQUES = array(
		'5710' => 'SAS',
		'5720' => 'SASU',
		'5499' => 'SA',
		'5410' => 'SARL',
		'5422' => 'EURL',
		'5498' => 'SELARL',
		'5306' => 'SCI',
		'5385' => 'SNC',
		'5370' => 'SCOP',
		'1000' => 'Entrepreneur individuel',
	);

	/**
	 * Titres représentants selon forme juridique
	 *
	 * @var array
	 */
	private const TITRES_REPRESENTANTS = array(
		'SARL'    => 'Gérant',
		'EURL'    => 'Gérant',
		'SELARL'  => 'Gérant',
		'SAS'     => 'Président',
		'SASU'    => 'Président',
		'SA'      => 'Directeur Général',
	);

	/**
	 * Convertit un code catégorie juridique en libellé
	 *
	 * @param string $code Code catégorie juridique (ex: "5710").
	 * @return string Libellé (ex: "SAS") ou code si non trouvé.
	 */
	public function convert_code_to_label( $code ) {
		if ( empty( $code ) || ! ctype_digit( $code ) ) {
			return $code;
		}

		return self::FORMES_JURIDIQUES[ $code ] ?? $code;
	}

	/**
	 * Récupère le titre du représentant selon la forme juridique
	 *
	 * @param string $forme_juridique Forme juridique.
	 * @return string Titre (Gérant, Président, etc.).
	 */
	public function get_titre_representant( $forme_juridique ) {
		$forme_upper = strtoupper( $forme_juridique );
		return self::TITRES_REPRESENTANTS[ $forme_upper ] ?? '{TITRE}';
	}
}

