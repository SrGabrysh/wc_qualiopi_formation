<?php
/**
 * ValidationHelper - Utilitaires de validation communs
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ValidationHelper
 * Utilitaires pour valider les données communes
 */
class ValidationHelper {

	/**
	 * Valide les paramètres AJAX requis
	 *
	 * @param array $required_params Paramètres requis.
	 * @param array $data Données à valider.
	 * @return array ['valid' => bool, 'missing' => array, 'error' => string|null].
	 */
	public static function validate_ajax_params( $required_params, $data ) {
		$missing = array();

		foreach ( $required_params as $param ) {
			if ( ! isset( $data[ $param ] ) || empty( $data[ $param ] ) ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'valid'   => false,
				'missing' => $missing,
				'error'   => sprintf(
					'Paramètres manquants : %s',
					implode( ', ', $missing )
				),
			);
		}

		return array(
			'valid'   => true,
			'missing' => array(),
			'error'   => null,
		);
	}

	/**
	 * Valide un formulaire ID
	 *
	 * @param mixed $form_id ID du formulaire.
	 * @return array ['valid' => bool, 'form_id' => int, 'error' => string|null].
	 */
	public static function validate_form_id( $form_id ) {
		if ( empty( $form_id ) ) {
			return array(
				'valid'   => false,
				'form_id' => 0,
				'error'   => 'L\'ID du formulaire est requis.',
			);
		}

		$form_id = absint( $form_id );

		if ( $form_id <= 0 ) {
			return array(
				'valid'   => false,
				'form_id' => 0,
				'error'   => 'L\'ID du formulaire doit être un nombre positif.',
			);
		}

		return array(
			'valid'   => true,
			'form_id' => $form_id,
			'error'   => null,
		);
	}

	/**
	 * Valide un email
	 *
	 * @param string $email Email à valider.
	 * @return array ['valid' => bool, 'email' => string, 'error' => string|null].
	 */
	public static function validate_email( $email ) {
		if ( empty( $email ) ) {
			return array(
				'valid' => false,
				'email' => '',
				'error' => 'L\'email est requis.',
			);
		}

		$email = sanitize_email( $email );

		// Validation basique du format email
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return array(
				'valid' => false,
				'email' => $email,
				'error' => 'Format d\'email invalide.',
			);
		}

		return array(
			'valid' => true,
			'email' => $email,
			'error' => null,
		);
	}

	/**
	 * Valide un numéro de téléphone français
	 *
	 * @param string $phone Téléphone à valider.
	 * @return array ['valid' => bool, 'phone' => string, 'error' => string|null].
	 */
	public static function validate_phone( $phone ) {
		if ( empty( $phone ) ) {
			return array(
				'valid' => false,
				'phone' => '',
				'error' => 'Le numéro de téléphone est requis.',
			);
		}

		// Nettoyer le téléphone
		$clean_phone = preg_replace( '/[^0-9+]/', '', $phone );

		// Patterns de téléphone français
		$patterns = array(
			'/^0[1-9]\d{8}$/',           // 0123456789
			'/^\+33[1-9]\d{8}$/',        // +33123456789
			'/^33[1-9]\d{8}$/',          // 33123456789
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $clean_phone ) ) {
				return array(
					'valid' => true,
					'phone' => $clean_phone,
					'error' => null,
				);
			}
		}

		return array(
			'valid' => false,
			'phone' => $phone,
			'error' => 'Format de téléphone invalide.',
		);
	}

	/**
	 * Valide un nom/prénom
	 *
	 * @param string $name Nom/prénom à valider.
	 * @param string $field_name Nom du champ (pour l'erreur).
	 * @return array ['valid' => bool, 'name' => string, 'error' => string|null].
	 */
	public static function validate_name( $name, $field_name = 'Nom' ) {
		if ( empty( $name ) ) {
			return array(
				'valid' => false,
				'name'  => '',
				'error' => sprintf( 'Le %s est requis.', strtolower( $field_name ) ),
			);
		}

		// Nettoyer le nom
		$clean_name = trim( $name );

		// Vérifier la longueur
		if ( strlen( $clean_name ) < 2 ) {
			return array(
				'valid' => false,
				'name'  => $clean_name,
				'error' => sprintf( 'Le %s doit contenir au moins 2 caractères.', strtolower( $field_name ) ),
			);
		}

		// Vérifier qu'il n'y a pas de chiffres
		if ( preg_match( '/\d/', $clean_name ) ) {
			return array(
				'valid' => false,
				'name'  => $clean_name,
				'error' => sprintf( 'Le %s ne peut pas contenir de chiffres.', strtolower( $field_name ) ),
			);
		}

		return array(
			'valid' => true,
			'name'  => $clean_name,
			'error' => null,
		);
	}

	/**
	 * Valide un code postal français
	 *
	 * @param string $postal_code Code postal à valider.
	 * @return array ['valid' => bool, 'postal_code' => string, 'error' => string|null].
	 */
	public static function validate_postal_code( $postal_code ) {
		if ( empty( $postal_code ) ) {
			return array(
				'valid'       => false,
				'postal_code' => '',
				'error'       => 'Le code postal est requis.',
			);
		}

		$clean_postal = preg_replace( '/[^0-9]/', '', $postal_code );

		if ( ! AddressFormatter::is_valid_postal_code( $clean_postal ) ) {
			return array(
				'valid'       => false,
				'postal_code' => $postal_code,
				'error'       => 'Le code postal doit contenir 5 chiffres.',
			);
		}

		return array(
			'valid'       => true,
			'postal_code' => $clean_postal,
			'error'       => null,
		);
	}

	/**
	 * Valide un SIRET
	 *
	 * @param string $siret SIRET à valider.
	 * @return array ['valid' => bool, 'siret' => string, 'error' => string|null].
	 */
	public static function validate_siret( $siret ) {
		if ( empty( $siret ) ) {
			return array(
				'valid' => false,
				'siret' => '',
				'error' => 'Le SIRET est requis.',
			);
		}

		$clean_siret = SiretFormatter::clean_siret( $siret );

		if ( ! SiretFormatter::is_valid_siret( $clean_siret ) ) {
			return array(
				'valid' => false,
				'siret' => $siret,
				'error' => 'Le SIRET doit contenir exactement 14 chiffres.',
			);
		}

		return array(
			'valid' => true,
			'siret' => $clean_siret,
			'error' => null,
		);
	}

	/**
	 * Valide un SIREN
	 *
	 * @param string $siren SIREN à valider.
	 * @return array ['valid' => bool, 'siren' => string, 'error' => string|null].
	 */
	public static function validate_siren( $siren ) {
		if ( empty( $siren ) ) {
			return array(
				'valid' => false,
				'siren' => '',
				'error' => 'Le SIREN est requis.',
			);
		}

		$clean_siren = SiretFormatter::clean_siren( $siren );

		if ( ! SiretFormatter::is_valid_siren( $clean_siren ) ) {
			return array(
				'valid' => false,
				'siren' => $siren,
				'error' => 'Le SIREN doit contenir exactement 9 chiffres.',
			);
		}

		return array(
			'valid' => true,
			'siren' => $clean_siren,
			'error' => null,
		);
	}

	/**
	 * Valide un nonce WordPress
	 *
	 * @param string $nonce Nonce à valider.
	 * @param string $action Action associée au nonce.
	 * @return array ['valid' => bool, 'error' => string|null].
	 */
	public static function validate_nonce( $nonce, $action ) {
		if ( empty( $nonce ) ) {
			return array(
				'valid' => false,
				'error' => 'Token de sécurité manquant.',
			);
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return array(
				'valid' => false,
				'error' => 'Token de sécurité invalide.',
			);
		}

		return array(
			'valid' => true,
			'error' => null,
		);
	}

	/**
	 * Valide les permissions utilisateur
	 *
	 * @param string $capability Capabilité requise.
	 * @return array ['valid' => bool, 'error' => string|null].
	 */
	public static function validate_user_capability( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			return array(
				'valid' => false,
				'error' => 'Permissions insuffisantes.',
			);
		}

		return array(
			'valid' => true,
			'error' => null,
		);
	}
}
