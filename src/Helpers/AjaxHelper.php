<?php
/**
 * AjaxHelper - Utilitaires pour les réponses AJAX
 *
 * @package WcQualiopiFormation\Helpers
 */

namespace WcQualiopiFormation\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxHelper
 * Utilitaires pour standardiser les réponses AJAX
 */
class AjaxHelper {

	/**
	 * Envoie une réponse d'erreur AJAX standardisée
	 *
	 * @param string $message Message d'erreur.
	 * @param string $code Code d'erreur (optionnel).
	 * @param array $data Données supplémentaires (optionnel).
	 * @return void
	 */
	public static function send_error( $message, $code = 'error', $data = array() ) {
		$response = array(
			'message' => $message,
		);

		if ( ! empty( $data ) ) {
			$response['data'] = $data;
		}

		wp_send_json_error( $response );
	}

	/**
	 * Envoie une réponse de succès AJAX standardisée
	 *
	 * @param array $data Données de succès.
	 * @param string $message Message de succès (optionnel).
	 * @return void
	 */
	public static function send_success( $data, $message = '' ) {
		$response = array(
			'data' => $data,
		);

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Envoie une réponse d'erreur de validation
	 *
	 * @param string $field Champ en erreur.
	 * @param string $error_message Message d'erreur.
	 * @param array $data Données supplémentaires (optionnel).
	 * @return void
	 */
	public static function send_validation_error( $field, $error_message, $data = array() ) {
		$response = array(
			'message' => $error_message,
			'field' => $field,
		);

		if ( ! empty( $data ) ) {
			$response['data'] = $data;
		}

		wp_send_json_error( $response );
	}

	/**
	 * Envoie une réponse d'erreur de permissions
	 *
	 * @param string $message Message d'erreur (optionnel).
	 * @return void
	 */
	public static function send_permission_error( $message = '' ) {
		$default_message = __( 'Permissions insuffisantes.', 'wc-qualiopi-formation' );
		$final_message = ! empty( $message ) ? $message : $default_message;

		wp_send_json_error( array(
			'message' => $final_message,
			'code' => 'permission_denied',
		) );
	}

	/**
	 * Envoie une réponse d'erreur de nonce
	 *
	 * @param string $message Message d'erreur (optionnel).
	 * @return void
	 */
	public static function send_nonce_error( $message = '' ) {
		$default_message = __( 'Token de sécurité invalide.', 'wc-qualiopi-formation' );
		$final_message = ! empty( $message ) ? $message : $default_message;

		wp_send_json_error( array(
			'message' => $final_message,
			'code' => 'invalid_nonce',
		) );
	}

	/**
	 * Envoie une réponse d'erreur de paramètres manquants
	 *
	 * @param array $missing_params Paramètres manquants.
	 * @param string $message Message d'erreur (optionnel).
	 * @return void
	 */
	public static function send_missing_params_error( $missing_params, $message = '' ) {
		$default_message = sprintf(
			__( 'Paramètres manquants : %s', 'wc-qualiopi-formation' ),
			implode( ', ', $missing_params )
		);
		$final_message = ! empty( $message ) ? $message : $default_message;

		wp_send_json_error( array(
			'message' => $final_message,
			'missing_params' => $missing_params,
			'code' => 'missing_params',
		) );
	}

	/**
	 * Envoie une réponse de succès avec message
	 *
	 * @param string $message Message de succès.
	 * @param array $data Données supplémentaires (optionnel).
	 * @return void
	 */
	public static function send_success_message( $message, $data = array() ) {
		$response = array(
			'message' => $message,
		);

		if ( ! empty( $data ) ) {
			$response['data'] = $data;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Envoie une réponse d'erreur d'API
	 *
	 * @param string $api_name Nom de l'API.
	 * @param string $error_message Message d'erreur de l'API.
	 * @param string $code Code d'erreur (optionnel).
	 * @return void
	 */
	public static function send_api_error( $api_name, $error_message, $code = 'api_error' ) {
		wp_send_json_error( array(
			'message' => sprintf(
				__( 'Erreur API %s : %s', 'wc-qualiopi-formation' ),
				$api_name,
				$error_message
			),
			'api_name' => $api_name,
			'api_error' => $error_message,
			'code' => $code,
		) );
	}
}
