<?php
/**
 * SecurityHelper - Utilitaires de sécurité
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\AjaxHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecurityHelper
 * Utilitaires pour sécurité (nonces, sanitization, etc.)
 */
class SecurityHelper {

	/**
	 * Crée un nonce pour une action
	 *
	 * @param string $action Action spécifique (défaut: 'wcqf_verify_siret').
	 * @return string Nonce.
	 */
	public static function create_nonce( $action = 'wcqf_verify_siret' ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Vérifie un nonce
	 *
	 * @param string $nonce Nonce à vérifier.
	 * @param string $action Action associée.
	 * @return bool True si valide.
	 */
	public static function verify_nonce( $nonce, $action = 'wcqf_verify_siret' ) {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Vérifie les capabilities admin
	 *
	 * @param string $capability Capability requise (défaut: 'manage_options').
	 * @return bool True si autorisé.
	 */
	public static function check_admin_capability( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Sanitize une entrée de formulaire
	 *
	 * @param mixed $input Entrée brute.
	 * @return string Entrée nettoyée.
	 */
	public static function sanitize_input( $input ) {
		if ( is_array( $input ) ) {
			return array_map( array( self::class, 'sanitize_input' ), $input );
		}

		return SanitizationHelper::sanitize_name( wp_unslash( $input ) );
	}

	/**
	 * Sanitize un textarea
	 *
	 * @param string $input Texte brut.
	 * @return string Texte nettoyé.
	 */
	public static function sanitize_textarea( $input ) {
		return sanitize_textarea_field( wp_unslash( $input ) );
	}

	/**
	 * Sanitize une URL
	 *
	 * @param string $url URL brute.
	 * @return string URL nettoyée.
	 */
	public static function sanitize_url( $url ) {
		return esc_url_raw( $url );
	}

	/**
	 * Échappe du HTML pour affichage
	 *
	 * @param string $text Texte brut.
	 * @return string Texte échappé.
	 */
	public static function escape_html( $text ) {
		return esc_html( $text );
	}

	/**
	 * Échappe un attribut HTML
	 *
	 * @param string $attr Attribut brut.
	 * @return string Attribut échappé.
	 */
	public static function escape_attr( $attr ) {
		return esc_attr( $attr );
	}

	/**
	 * Génère un token aléatoire sécurisé
	 *
	 * @param int $length Longueur du token.
	 * @return string Token.
	 */
	public static function generate_token( $length = 32 ) {
		return bin2hex( random_bytes( $length / 2 ) );
	}

	/**
	 * Hash une valeur avec WordPress salt
	 *
	 * @param string $value Valeur à hasher.
	 * @return string Hash.
	 */
	public static function hash_value( $value ) {
		return wp_hash( $value );
	}

	/**
	 * Vérifie si la requête est AJAX
	 *
	 * @return bool True si AJAX.
	 */
	public static function is_ajax() {
		return wp_doing_ajax();
	}

	/**
	 * Vérifie si la requête est en admin
	 *
	 * @return bool True si admin.
	 */
	public static function is_admin() {
		return is_admin();
	}

	/**
	 * Termine une requête AJAX avec erreur
	 *
	 * @param string $message Message d'erreur.
	 * @param int    $code Code HTTP (défaut: 403).
	 * @return void
	 */
	public static function ajax_error( $message, $code = 403 ) {
		AjaxHelper::send_error( $message, 'security_error' );
	}

	/**
	 * Termine une requête AJAX avec succès
	 *
	 * @param array $data Données de réponse.
	 * @return void
	 */
	public static function ajax_success( $data = array() ) {
		AjaxHelper::send_success( $data );
	}

	/**
	 * Masque partiellement une valeur sensible (email, clé API)
	 *
	 * @param string $value Valeur à masquer.
	 * @param int    $visible_chars Nombre de caractères visibles au début/fin.
	 * @return string Valeur masquée.
	 */
	public static function mask_sensitive( $value, $visible_chars = 4 ) {
		$length = strlen( $value );

		if ( $length <= $visible_chars * 2 ) {
			return str_repeat( '*', $length );
		}

		$start = substr( $value, 0, $visible_chars );
		$end   = substr( $value, -$visible_chars );
		$middle = str_repeat( '*', $length - ( $visible_chars * 2 ) );

		return $start . $middle . $end;
	}
}




