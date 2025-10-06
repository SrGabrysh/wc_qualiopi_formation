<?php
/**
 * Validation des mentions légales
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;

/**
 * Classe de validation des mentions légales
 *
 * Fonctionnalités :
 * - Validation présence champs obligatoires
 * - Validation format mentions
 * - Vérification conformité Qualiopi
 */
class MentionsValidator {

	/**
	 * Champs obligatoires pour une personne morale
	 *
	 * @var array
	 */
	private const REQUIRED_FIELDS_PM = array(
		'denomination'     => 'Dénomination',
		'siege_social'     => 'Siège social',
		'rcs'              => 'RCS',
		'siren'            => 'SIREN',
	);

	/**
	 * Champs obligatoires pour un entrepreneur individuel
	 *
	 * @var array
	 */
	private const REQUIRED_FIELDS_EI = array(
		'nom_prenom'       => 'Nom et prénom',
		'adresse'          => 'Adresse',
		'siret'            => 'SIRET',
	);

	/**
	 * Valide que les mentions légales sont complètes
	 *
	 * @param string $mentions Mentions légales générées.
	 * @param string $type_entreprise Type d'entreprise.
	 * @return array ['valid' => bool, 'missing_fields' => array, 'message' => string].
	 */
	public function validate( $mentions, $type_entreprise ) {
		if ( empty( $mentions ) ) {
			return array(
				'valid'          => false,
				'missing_fields' => array(),
				'message'        => __( 'Les mentions légales sont vides.', Constants::TEXT_DOMAIN ),
			);
		}

		// Récupérer les champs requis selon le type.
		$required_fields = $this->get_required_fields( $type_entreprise );

		// Vérifier présence des champs obligatoires.
		$missing_fields = $this->check_required_fields( $mentions, $required_fields );

		if ( ! empty( $missing_fields ) ) {
			return array(
				'valid'          => false,
				'missing_fields' => $missing_fields,
				'message'        => sprintf(
					/* translators: %s: liste des champs manquants */
					__( 'Champs obligatoires manquants : %s', Constants::TEXT_DOMAIN ),
					implode( ', ', $missing_fields )
				),
			);
		}

		return array(
			'valid'          => true,
			'missing_fields' => array(),
			'message'        => __( 'Mentions légales valides.', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Récupère les champs requis selon le type d'entreprise
	 *
	 * @param string $type_entreprise Type d'entreprise.
	 * @return array Champs requis.
	 */
	private function get_required_fields( $type_entreprise ) {
		switch ( $type_entreprise ) {
			case 'personne_morale':
				return self::REQUIRED_FIELDS_PM;

			case 'entrepreneur_individuel':
				return self::REQUIRED_FIELDS_EI;

			default:
				return array(
					'siret' => 'SIRET',
				);
		}
	}

	/**
	 * Vérifie la présence des champs obligatoires dans les mentions
	 *
	 * @param string $mentions Mentions légales.
	 * @param array  $required_fields Champs requis.
	 * @return array Champs manquants (labels).
	 */
	private function check_required_fields( $mentions, $required_fields ) {
		$missing = array();

		foreach ( $required_fields as $field_key => $field_label ) {
			switch ( $field_key ) {
				case 'siren':
					// SIREN : format XXX XXX XXX (9 chiffres avec espaces).
					if ( ! preg_match( '/\d{3}\s\d{3}\s\d{3}/', $mentions ) ) {
						$missing[] = $field_label;
					}
					break;

				case 'siret':
					// SIRET : format XXX XXX XXX XXXXX (14 chiffres avec espaces).
					if ( ! preg_match( '/\d{3}\s\d{3}\s\d{3}\s\d{5}/', $mentions ) ) {
						$missing[] = $field_label;
					}
					break;

				case 'denomination':
					// Dénomination : au moins 2 caractères non vides au début.
					if ( ! preg_match( '/^[\p{L}\p{N}]{2,}/u', trim( $mentions ) ) ) {
						$missing[] = $field_label;
					}
					break;

				case 'siege_social':
					// Siège social : vérifier présence "siège social".
					if ( stripos( $mentions, 'siège social' ) === false && stripos( $mentions, 'situé au' ) === false ) {
						$missing[] = $field_label;
					}
					break;

				case 'rcs':
					// RCS : vérifier présence "Registre du Commerce".
					if ( stripos( $mentions, 'Registre du Commerce' ) === false ) {
						$missing[] = $field_label;
					}
					break;

				case 'nom_prenom':
					// Nom + Prénom : au moins un mot en majuscules suivi d'un mot.
					if ( ! preg_match( '/[A-Z]{2,}\s+[\p{L}]+/u', $mentions ) ) {
						$missing[] = $field_label;
					}
					break;

				case 'adresse':
					// Adresse : vérifier présence "au" ou "situé".
					if ( stripos( $mentions, ' au ' ) === false && stripos( $mentions, 'situé' ) === false && stripos( $mentions, 'demeurant' ) === false ) {
						$missing[] = $field_label;
					}
					break;

				default:
					// Champ inconnu : ignorer.
					break;
			}
		}

		return $missing;
	}

	/**
	 * Vérifie si les mentions sont conformes Qualiopi
	 *
	 * @param string $mentions Mentions légales.
	 * @param string $type_entreprise Type d'entreprise.
	 * @return bool True si conforme.
	 */
	public function is_qualiopi_compliant( $mentions, $type_entreprise = '' ) {
		// Vérifier que les mentions contiennent au minimum :
		// - SIREN ou SIRET.
		// - Adresse complète (rue, CP, ville).
		// - Forme juridique explicite (pour PM).

		$has_siren = preg_match( '/\d{3}\s\d{3}\s\d{3}/', $mentions );
		$has_siret = preg_match( '/\d{3}\s\d{3}\s\d{3}\s\d{5}/', $mentions );

		if ( ! $has_siren && ! $has_siret ) {
			return false;
		}

		// Vérifier présence d'une adresse (numéro + rue + ville).
		$has_address = ( stripos( $mentions, ' au ' ) !== false || stripos( $mentions, 'situé' ) !== false || stripos( $mentions, 'demeurant' ) !== false );

		if ( ! $has_address ) {
			return false;
		}

		// Si personne morale, vérifier présence RCS.
		if ( $type_entreprise === 'personne_morale' ) {
			$has_rcs = stripos( $mentions, 'Registre du Commerce' ) !== false;
			if ( ! $has_rcs ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extrait le SIREN depuis les mentions
	 *
	 * @param string $mentions Mentions légales.
	 * @return string|false SIREN extrait ou false.
	 */
	public function extract_siren( $mentions ) {
		if ( preg_match( '/(\d{3}\s\d{3}\s\d{3})/', $mentions, $matches ) ) {
			return str_replace( ' ', '', $matches[1] );
		}
		return false;
	}

	/**
	 * Extrait le SIRET depuis les mentions
	 *
	 * @param string $mentions Mentions légales.
	 * @return string|false SIRET extrait ou false.
	 */
	public function extract_siret( $mentions ) {
		if ( preg_match( '/(\d{3}\s\d{3}\s\d{3}\s\d{5})/', $mentions, $matches ) ) {
			return str_replace( ' ', '', $matches[1] );
		}
		return false;
	}

	/**
	 * Valide le format des mentions (longueur, caractères)
	 *
	 * @param string $mentions Mentions légales.
	 * @return array ['valid' => bool, 'message' => string].
	 */
	public function validate_format( $mentions ) {
		// Vérifier longueur minimale (au moins 50 caractères).
		if ( mb_strlen( $mentions ) < 50 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Les mentions légales sont trop courtes (minimum 50 caractères).', Constants::TEXT_DOMAIN ),
			);
		}

		// Vérifier longueur maximale (max 1000 caractères).
		if ( mb_strlen( $mentions ) > 1000 ) {
			return array(
				'valid'   => false,
				'message' => __( 'Les mentions légales sont trop longues (maximum 1000 caractères).', Constants::TEXT_DOMAIN ),
			);
		}

		// Vérifier présence de placeholders non remplacés.
		if ( preg_match( '/{[A-Z_]+}/', $mentions ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Les mentions légales contiennent des champs non remplis (placeholders).', Constants::TEXT_DOMAIN ),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'Format des mentions légales valide.', Constants::TEXT_DOMAIN ),
		);
	}
}
