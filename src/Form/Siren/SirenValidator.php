<?php
/**
 * Validation des numéros SIRET
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;

/**
 * Classe de validation des numéros SIRET
 *
 * Fonctionnalités :
 * - Validation format SIRET (14 chiffres)
 * - Validation algorithme Luhn
 * - Extraction SIREN depuis SIRET
 * - Détermination type d'entreprise
 */
class SirenValidator {

	/**
	 * Valide un SIRET de manière complète
	 *
	 * @param string $siret SIRET à valider.
	 * @return array ['valid' => bool, 'cleaned' => string, 'message' => string].
	 */
	public function validate_siret_complete( $siret ) {
		// 1. Nettoyage.
		$siret_cleaned = $this->clean_siret( $siret );

		// 2. Validation format.
		if ( ! $this->validate_format( $siret_cleaned ) ) {
			return array(
				'valid'   => false,
				'cleaned' => $siret_cleaned,
				'message' => __( 'Le SIRET doit contenir exactement 14 chiffres.', Constants::TEXT_DOMAIN ),
			);
		}

		// 3. Validation algorithme Luhn.
		if ( ! $this->validate_luhn( $siret_cleaned ) ) {
			return array(
				'valid'   => false,
				'cleaned' => $siret_cleaned,
				'message' => __( 'Le numéro SIRET est invalide (échec algorithme Luhn).', Constants::TEXT_DOMAIN ),
			);
		}

		return array(
			'valid'   => true,
			'cleaned' => $siret_cleaned,
			'message' => __( 'SIRET valide.', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Nettoie un SIRET (supprime espaces, tirets, etc.)
	 *
	 * @param string $siret SIRET brut.
	 * @return string SIRET nettoyé.
	 */
	private function clean_siret( $siret ) {
		return preg_replace( '/[^0-9]/', '', $siret );
	}

	/**
	 * Valide le format d'un SIRET (14 chiffres)
	 *
	 * @param string $siret SIRET nettoyé.
	 * @return bool True si format valide.
	 */
	private function validate_format( $siret ) {
		return preg_match( '/^[0-9]{14}$/', $siret ) === 1;
	}

	/**
	 * Valide un SIRET avec l'algorithme Luhn
	 *
	 * @param string $siret SIRET nettoyé.
	 * @return bool True si valide.
	 */
	private function validate_luhn( $siret ) {
		$sum = 0;
		$len = strlen( $siret );

		for ( $i = 0; $i < $len; $i++ ) {
			$digit = (int) $siret[ $i ];

			// Doubler chaque chiffre en position paire.
			if ( $i % 2 === 0 ) {
				$digit *= 2;
				if ( $digit > 9 ) {
					$digit -= 9;
				}
			}

			$sum += $digit;
		}

		return ( $sum % 10 === 0 );
	}

	/**
	 * Extrait le SIREN depuis un SIRET
	 *
	 * @param string $siret SIRET nettoyé (14 chiffres).
	 * @return string SIREN (9 premiers chiffres).
	 */
	public function extract_siren( $siret ) {
		return substr( $siret, 0, 9 );
	}

	/**
	 * Détermine le type d'entreprise depuis les données unité légale
	 *
	 * Logique :
	 * - Personne morale (pm) si 'denomination' non vide
	 * - Entrepreneur individuel (ei) si 'nom' + 'prenom' non vides
	 * - Inconnu sinon
	 *
	 * @param array $unite_legale Données de l'unité légale.
	 * @return string Type d'entreprise (pm|ei|inconnu).
	 */
	public function determine_entreprise_type( $unite_legale ) {
		// DEBUG : Afficher la structure complète reçue
		error_log( '[WCQF DEBUG] unite_legale keys: ' . implode( ', ', array_keys( $unite_legale ) ) );
		error_log( '[WCQF DEBUG] unite_legale data: ' . json_encode( $unite_legale, JSON_UNESCAPED_UNICODE ) );
		
		// Personne morale : a une dénomination.
		if ( ! empty( $unite_legale['denomination'] ) ) {
			error_log( '[WCQF DEBUG] Type detecte: pm (denomination presente)' );
			return 'pm';
		}

		// Entrepreneur individuel : a un nom et un prénom.
		if ( ! empty( $unite_legale['nom'] ) && ! empty( $unite_legale['prenom'] ) ) {
			error_log( '[WCQF DEBUG] Type detecte: ei (nom+prenom presents)' );
			return 'ei';
		}

		// Type inconnu.
		error_log( '[WCQF DEBUG] Type inconnu - denomination: ' . ( $unite_legale['denomination'] ?? 'ABSENT' ) . ', nom: ' . ( $unite_legale['nom'] ?? 'ABSENT' ) . ', prenom: ' . ( $unite_legale['prenom'] ?? 'ABSENT' ) );
		return 'inconnu';
	}

	/**
	 * Vérifie si une entreprise est active
	 *
	 * @param array $unite_legale Données de l'unité légale.
	 * @return bool True si active.
	 */
	public function is_active( $unite_legale ) {
		// Vérifier le champ 'etat_administratif'.
		// Valeur attendue : 'actif' ou 'A' (selon l'API).
		$etat = $unite_legale['etat_administratif'] ?? '';

		return in_array( strtolower( $etat ), array( 'actif', 'a' ), true );
	}
}

