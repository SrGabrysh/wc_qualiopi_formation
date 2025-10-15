<?php
/**
 * PayloadBuilder - Constructeur de payload pour l'API Yousign v3
 *
 * RESPONSABILITÉ UNIQUE : Construire les payloads JSON pour les appels API Yousign
 *
 * @package WcQualiopiFormation\Modules\Yousign\Payload
 * @since 1.2.1
 */

namespace WcQualiopiFormation\Modules\Yousign\Payload;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Class PayloadBuilder
 *
 * Construit les payloads JSON pour l'API Yousign v3.
 * Supporte le mode Template avec placeholders pour les signataires.
 */
class PayloadBuilder {

	/**
	 * Construit le payload pour créer une Signature Request (mode Template)
	 *
	 * API v3 : utilise template_id + template_placeholders, pas de members/documents
	 *
	 * @param array $user_data Données utilisateur validées (firstName, lastName, email).
	 * @param array $config Configuration Yousign (template_id, custom_experience_id, etc.).
	 * @return array Payload JSON prêt pour l'API.
	 */
	public function build_signature_request_payload( array $user_data, array $config ) {
		// Validation des données requises
		if ( ! $this->validate_user_data( $user_data ) ) {
			LoggingHelper::error( '[PayloadBuilder] Invalid user data', array(
				'user_data_keys' => array_keys( $user_data ),
			) );
			return array();
		}

		if ( ! $this->validate_config( $config ) ) {
			LoggingHelper::error( '[PayloadBuilder] Invalid config', array(
				'config_keys' => array_keys( $config ),
			) );
			return array();
		}

		// Construction du payload de base
		$payload = array(
			'name'          => __( 'Contrat de formation TB-Formation', Constants::TEXT_DOMAIN ),
			'delivery_mode' => 'none', // iframe mode
			'timezone'      => 'Europe/Paris',
		);

		// Custom Experience (ex-signature_ui_id en v2)
		if ( ! empty( $config['custom_experience_id'] ) ) {
			$payload['custom_experience_id'] = $config['custom_experience_id'];
		}

		// Mode Template : template_id + placeholders pour les signataires
		if ( ! empty( $config['template_id'] ) ) {
			$payload['template_id'] = $config['template_id'];
			$payload['template_placeholders'] = $this->build_template_placeholders( $user_data, $config );
		}

		LoggingHelper::debug( '[PayloadBuilder] Payload built', array(
			'has_template_id' => ! empty( $payload['template_id'] ),
			'has_custom_exp'  => ! empty( $payload['custom_experience_id'] ),
			'signers_count'   => isset( $payload['template_placeholders']['signers'] ) ? count( $payload['template_placeholders']['signers'] ) : 0,
		) );

		return $payload;
	}

	/**
	 * Construit les template_placeholders pour l'API Yousign v3
	 *
	 * @param array $user_data Données utilisateur.
	 * @param array $config Configuration Yousign.
	 * @return array Template placeholders.
	 */
	private function build_template_placeholders( array $user_data, array $config ) {
		$placeholders = array(
			'signers'               => $this->build_signers_placeholders( $user_data, $config ),
			'read_only_text_fields' => $this->build_readonly_fields( $user_data ),
		);

		return $placeholders;
	}

	/**
	 * Construit les placeholders pour les signataires
	 *
	 * Le label DOIT matcher exactement le placeholder signataire dans le template Yousign (case-sensitive!)
	 *
	 * @param array $user_data Données utilisateur.
	 * @param array $config Configuration Yousign.
	 * @return array Signers placeholders.
	 */
	private function build_signers_placeholders( array $user_data, array $config ) {
		return array(
			array(
				'label'                          => $config['template_signer_label'] ?? 'client',
				'signature_level'                => 'electronic_signature', // Niveau de signature requis
				'signature_authentication_mode'  => 'no_otp', // Mode d'authentification (no_otp ou otp_sms)
				'info'                           => array(
					'first_name' => $user_data['firstName'],
					'last_name'  => $user_data['lastName'],
					'email'      => $user_data['email'],
					'locale'     => 'fr',
				),
			),
		);
	}

	/**
	 * Construit les champs texte en lecture seule pour affichage dans le PDF
	 *
	 * Ces champs permettent d'afficher les données utilisateur dans le document PDF
	 * sans être des zones de signature.
	 *
	 * @param array $user_data Données utilisateur.
	 * @return array Read-only text fields.
	 */
	private function build_readonly_fields( array $user_data ) {
		return array(
			array( 'label' => 'first_name', 'text' => $user_data['firstName'] ),
			array( 'label' => 'last_name',  'text' => $user_data['lastName'] ),
			array( 'label' => 'email',      'text' => $user_data['email'] ),
		);
	}

	/**
	 * Valide les données utilisateur
	 *
	 * @param array $user_data Données à valider.
	 * @return bool True si valides, false sinon.
	 */
	private function validate_user_data( array $user_data ) {
		$required_keys = array( 'firstName', 'lastName', 'email' );

		foreach ( $required_keys as $key ) {
			if ( empty( $user_data[ $key ] ) ) {
				return false;
			}
		}

		// Valider l'email
		if ( ! \is_email( $user_data['email'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Valide la configuration Yousign
	 *
	 * @param array $config Configuration à valider.
	 * @return bool True si valide, false sinon.
	 */
	private function validate_config( array $config ) {
		// Le template_id est requis pour le mode Template
		if ( empty( $config['template_id'] ) ) {
			return false;
		}

		return true;
	}
}

