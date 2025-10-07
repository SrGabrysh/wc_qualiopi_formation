<?php
/**
 * SanitizationHelper - Utilitaires de sanitization
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SanitizationHelper
 * Utilitaires pour standardiser la sanitization des données
 */
class SanitizationHelper {

	/**
	 * Sanitise un champ texte depuis $_POST
	 *
	 * @param string $key Clé du champ dans $_POST.
	 * @param string $default Valeur par défaut.
	 * @return string Valeur sanitizée.
	 */
	public static function sanitize_post_text( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Sanitise un champ email depuis $_POST
	 *
	 * @param string $key Clé du champ dans $_POST.
	 * @param string $default Valeur par défaut.
	 * @return string Email sanitizé.
	 */
	public static function sanitize_post_email( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_email( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Sanitise un champ entier depuis $_POST
	 *
	 * @param string $key Clé du champ dans $_POST.
	 * @param int $default Valeur par défaut.
	 * @return int Valeur entière sanitizée.
	 */
	public static function sanitize_post_int( $key, $default = 0 ) {
		return isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : $default;
	}

	/**
	 * Sanitise un champ textearea depuis $_POST
	 *
	 * @param string $key Clé du champ dans $_POST.
	 * @param string $default Valeur par défaut.
	 * @return string Textearea sanitizé.
	 */
	public static function sanitize_post_textarea( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? \sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Sanitise un champ URL depuis $_POST
	 *
	 * @param string $key Clé du champ dans $_POST.
	 * @param string $default Valeur par défaut.
	 * @return string URL sanitizée.
	 */
	public static function sanitize_post_url( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Sanitise un champ texte depuis $_GET
	 *
	 * @param string $key Clé du champ dans $_GET.
	 * @param string $default Valeur par défaut.
	 * @return string Valeur sanitizée.
	 */
	public static function sanitize_get_text( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	/**
	 * Sanitise un champ entier depuis $_GET
	 *
	 * @param string $key Clé du champ dans $_GET.
	 * @param int $default Valeur par défaut.
	 * @return int Valeur entière sanitizée.
	 */
	public static function sanitize_get_int( $key, $default = 0 ) {
		return isset( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : $default;
	}

	/**
	 * Sanitise un tableau de données
	 *
	 * @param array $data Données à sanitizer.
	 * @param array $allowed_keys Clés autorisées (optionnel).
	 * @return array Données sanitizées.
	 */
	public static function sanitize_array( $data, $allowed_keys = array() ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Si des clés autorisées sont spécifiées, vérifier
			if ( ! empty( $allowed_keys ) && ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			// Sanitizer selon le type
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = absint( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = self::sanitize_array( $value, $allowed_keys );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitise un nom/prénom
	 *
	 * @param string $name Nom/prénom à sanitizer.
	 * @return string Nom/prénom sanitizé.
	 */
	public static function sanitize_name( $name ) {
		$sanitized = sanitize_text_field( $name );
		
		// Supprimer les chiffres
		$sanitized = preg_replace( '/\d/', '', $sanitized );
		
		// Supprimer les caractères spéciaux sauf espaces, tirets, apostrophes
		$sanitized = preg_replace( '/[^a-zA-ZÀ-ÿ\s\'-]/', '', $sanitized );
		
		// Supprimer les espaces multiples
		$sanitized = preg_replace( '/\s+/', ' ', $sanitized );
		
		return trim( $sanitized );
	}

	/**
	 * Sanitise un numéro SIRET/SIREN
	 *
	 * @param string $number Numéro à sanitizer.
	 * @return string Numéro sanitizé (chiffres uniquement).
	 */
	public static function sanitize_siret( $number ) {
		// Supprimer tous les caractères non numériques
		return preg_replace( '/[^0-9]/', '', $number );
	}

	/**
	 * Sanitise un code postal
	 *
	 * @param string $postal_code Code postal à sanitizer.
	 * @return string Code postal sanitizé.
	 */
	public static function sanitize_postal_code( $postal_code ) {
		// Garder seulement les chiffres
		$sanitized = preg_replace( '/[^0-9]/', '', $postal_code );
		
		// Limiter à 5 chiffres
		return substr( $sanitized, 0, 5 );
	}

	/**
	 * Sanitise un numéro de téléphone
	 *
	 * @param string $phone Téléphone à sanitizer.
	 * @return string Téléphone sanitizé.
	 */
	public static function sanitize_phone( $phone ) {
		// Garder seulement les chiffres, + et espaces
		$sanitized = preg_replace( '/[^0-9+\s]/', '', $phone );
		
		// Supprimer les espaces multiples
		$sanitized = preg_replace( '/\s+/', ' ', $sanitized );
		
		return trim( $sanitized );
	}

	/**
	 * Sanitise une adresse
	 *
	 * @param string $address Adresse à sanitizer.
	 * @return string Adresse sanitizée.
	 */
	public static function sanitize_address( $address ) {
		$sanitized = sanitize_text_field( $address );
		
		// Supprimer les caractères spéciaux non autorisés
		$sanitized = preg_replace( '/[^\w\s\-\'\.]/', '', $sanitized );
		
		// Supprimer les espaces multiples
		$sanitized = preg_replace( '/\s+/', ' ', $sanitized );
		
		return trim( $sanitized );
	}

	/**
	 * Sanitise un texte pour les logs (sans données sensibles)
	 *
	 * @param string $text Texte à sanitizer.
	 * @return string Texte sanitizé pour les logs.
	 */
	public static function sanitize_for_log( $text ) {
		// Masquer les emails
		$text = preg_replace( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', '[EMAIL_MASQUÉ]', $text );
		
		// Masquer les numéros de téléphone
		$text = preg_replace( '/(\+33|0)[1-9](\d{8})/', '[TÉLÉPHONE_MASQUÉ]', $text );
		
		// Masquer les SIRET/SIREN
		$text = preg_replace( '/\b\d{9}\b/', '[SIREN_MASQUÉ]', $text );
		$text = preg_replace( '/\b\d{14}\b/', '[SIRET_MASQUÉ]', $text );
		
		return $text;
	}

	/**
	 * Valide un email selon les standards RFC (WordPress native) - AVEC VALIDATION
	 * Pattern identique à PhoneFormatter pour cohérence architecture
	 *
	 * @param string $email Adresse email brute.
	 * @return array ['value' => string, 'valid' => bool, 'error' => string|null]
	 */
	public static function validate_email_rfc( $email ) {
		LoggingHelper::debug( '[EmailValidator] Début validation RFC', array(
			'input' => $email,
			'input_length' => strlen( $email ),
		) );

		if ( empty( $email ) ) {
			LoggingHelper::warning( '[EmailValidator] Email vide', array(
				'input' => $email,
			) );
			return array(
				'value' => '',
				'valid' => false,
				'error' => 'L\'adresse email ne peut pas être vide.',
			);
		}

		// Étape 1 : Sanitization WordPress (convertit en minuscules automatiquement)
		$sanitized = sanitize_email( $email );

		// Étape 1.5 : Forcer minuscules + trim (au cas où)
		$sanitized = strtolower( trim( $sanitized ) );

		LoggingHelper::debug( '[EmailValidator] Sanitization WordPress', array(
			'original' => $email,
			'sanitized' => $sanitized,
			'changed' => $email !== $sanitized,
			'is_lowercase' => $sanitized === strtolower( $sanitized ),
		) );

		if ( empty( $sanitized ) ) {
			LoggingHelper::warning( '[EmailValidator] Email invalidé par sanitization', array(
				'original' => $email,
				'sanitized' => $sanitized,
			) );
			return array(
				'value' => $email,
				'valid' => false,
				'error' => 'L\'adresse email contient des caractères non autorisés.',
			);
		}

		// Étape 2 : Validation RFC via WordPress
		$is_valid = \is_email( $sanitized );

		LoggingHelper::debug( '[EmailValidator] Validation is_email()', array(
			'email' => $sanitized,
			'is_valid' => $is_valid,
		) );

		if ( ! $is_valid ) {
			LoggingHelper::warning( '[EmailValidator] Email invalide selon RFC', array(
				'email' => $sanitized,
			) );
			return array(
				'value' => $email,
				'valid' => false,
				'error' => 'Le format de l\'adresse email n\'est pas valide.',
			);
		}

		LoggingHelper::info( '[EmailValidator] Validation RFC réussie', array(
			'original' => $email,
			'sanitized' => $sanitized,
		) );

		return array(
			'value' => $sanitized,
			'valid' => true,
			'error' => null,
		);
	}
}
