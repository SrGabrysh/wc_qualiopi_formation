<?php
/**
 * PhoneFormatter - Formatage des numéros de téléphone
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PhoneFormatter
 * Utilitaires pour formater les numéros de téléphone au format E164
 * 
 * Pattern identique à NameFormatter pour cohérence architecture
 */
class PhoneFormatter {

	/**
	 * Formate un numéro de téléphone français au format E164 (AVEC VALIDATION)
	 * 
	 * Supporte les formats d'entrée :
	 * - 0612345678
	 * - 06.12.34.56.78
	 * - 06 12 34 56 78
	 * - 06-12-34-56-78
	 * - +33612345678 (déjà au format E164)
	 *
	 * @param string $phone Numéro de téléphone brut.
	 * @return array ['value' => string, 'valid' => bool, 'error' => string|null]
	 */
	public static function format( $phone ) {
		LoggingHelper::debug( '[PhoneFormatter] Début formatage', array(
			'input' => $phone,
			'input_length' => strlen( $phone ),
		) );

		if ( empty( $phone ) ) {
			LoggingHelper::warning( '[PhoneFormatter] Téléphone vide', array(
				'input' => $phone,
			) );
			return array(
				'value' => '',
				'valid' => false,
				'error' => 'Le numéro de téléphone ne peut pas être vide.',
			);
		}

		// Étape 0 : Détecter si déjà au format E164 français (+33...)
		if ( preg_match( '/^\+33[1-9]\d{8}$/', $phone ) ) {
			LoggingHelper::info( '[PhoneFormatter] Déjà au format E164, validation OK', array(
				'phone' => $phone,
			) );
			return array(
				'value' => $phone,
				'valid' => true,
				'error' => null,
			);
		}

		// Étape 1 : Nettoyage - garder seulement les chiffres
		$cleaned = preg_replace( '/[^0-9]/', '', $phone );

		LoggingHelper::debug( '[PhoneFormatter] Nettoyage effectué', array(
			'original' => $phone,
			'cleaned' => $cleaned,
			'cleaned_length' => strlen( $cleaned ),
			'removed_chars' => strlen( $phone ) - strlen( $cleaned ),
		) );

		// Étape 2 : Validation longueur (10 chiffres pour France)
		if ( strlen( $cleaned ) !== 10 ) {
			LoggingHelper::warning( '[PhoneFormatter] Longueur invalide', array(
				'cleaned' => $cleaned,
				'length' => strlen( $cleaned ),
				'expected' => 10,
			) );
			return array(
				'value' => $phone,
				'valid' => false,
				'error' => 'Le numéro de téléphone doit contenir exactement 10 chiffres.',
			);
		}

		// Étape 3 : Validation préfixe (doit commencer par 0 suivi de 1-9)
		if ( ! preg_match( '/^0[1-9]/', $cleaned ) ) {
			LoggingHelper::warning( '[PhoneFormatter] Préfixe invalide', array(
				'cleaned' => $cleaned,
				'first_two_chars' => substr( $cleaned, 0, 2 ),
			) );
			return array(
				'value' => $phone,
				'valid' => false,
				'error' => 'Le numéro de téléphone doit commencer par 0 suivi d\'un chiffre de 1 à 9.',
			);
		}

		// Étape 4 : Formatage E164 : +33 + numéro sans le 0 initial
		$e164 = '+33' . substr( $cleaned, 1 );

		LoggingHelper::info( '[PhoneFormatter] Formatage E164 réussi', array(
			'original' => $phone,
			'cleaned' => $cleaned,
			'e164' => $e164,
		) );

		return array(
			'value' => $e164,
			'valid' => true,
			'error' => null,
		);
	}

	/**
	 * Formate un téléphone (méthode simple pour compatibilité)
	 *
	 * @param string $phone Numéro de téléphone brut.
	 * @return string Numéro formaté E164 ou chaîne vide si invalide.
	 */
	public static function format_phone( $phone ) {
		$result = self::format( $phone );
		return $result['valid'] ? $result['value'] : '';
	}

	/**
	 * Valide un numéro de téléphone français
	 *
	 * @param string $phone Numéro de téléphone à valider.
	 * @return bool True si valide, false sinon.
	 */
	public static function is_valid_french_phone( $phone ) {
		$result = self::format( $phone );
		return $result['valid'];
	}

	/**
	 * Nettoie un numéro de téléphone (supprime caractères non numériques)
	 *
	 * @param string $phone Numéro de téléphone brut.
	 * @return string Numéro nettoyé (chiffres uniquement).
	 */
	public static function clean( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}

		// Garder seulement les chiffres et le signe +
		$cleaned = preg_replace( '/[^0-9+]/', '', $phone );

		return trim( $cleaned );
	}

	/**
	 * Convertit un numéro E164 en format national français (affichage)
	 * 
	 * Exemple : +33612345678 → 06 12 34 56 78
	 *
	 * @param string $e164 Numéro au format E164.
	 * @return string Numéro au format national français.
	 */
	public static function e164_to_national( $e164 ) {
		if ( empty( $e164 ) ) {
			return '';
		}

		// Retirer le préfixe +33
		$national = preg_replace( '/^\+33/', '0', $e164 );

		// Formater avec espaces : 06 12 34 56 78
		if ( strlen( $national ) === 10 ) {
			return substr( $national, 0, 2 ) . ' ' . 
			       substr( $national, 2, 2 ) . ' ' . 
			       substr( $national, 4, 2 ) . ' ' . 
			       substr( $national, 6, 2 ) . ' ' . 
			       substr( $national, 8, 2 );
		}

		return $national;
	}
}

