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
	 * @param array  $user_data     Données utilisateur validées (firstName, lastName, email).
	 * @param array  $config        Configuration Yousign (template_id, custom_experience_id, etc.).
	 * @param string $convention_id Identifiant unique de la convention (optionnel).
	 * @return array Payload JSON prêt pour l'API.
	 */
	public function build_signature_request_payload( array $user_data, array $config, string $convention_id = '' ) {
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
			$payload['template_placeholders'] = $this->build_template_placeholders( $user_data, $config, $convention_id );
		}

		LoggingHelper::debug( '[PayloadBuilder] Payload built', array(
			'has_template_id'   => ! empty( $payload['template_id'] ),
			'has_custom_exp'    => ! empty( $payload['custom_experience_id'] ),
			'has_convention_id' => ! empty( $convention_id ),
			'signers_count'     => isset( $payload['template_placeholders']['signers'] ) ? count( $payload['template_placeholders']['signers'] ) : 0,
			'read_only_count'   => isset( $payload['template_placeholders']['read_only_text_fields'] ) ? count( $payload['template_placeholders']['read_only_text_fields'] ) : 0,
		) );

		return $payload;
	}

	/**
	 * Construit les template_placeholders pour l'API Yousign v3
	 *
	 * @param array  $user_data     Données utilisateur.
	 * @param array  $config        Configuration Yousign.
	 * @param string $convention_id Identifiant de convention (optionnel).
	 * @return array Template placeholders.
	 */
	private function build_template_placeholders( array $user_data, array $config, string $convention_id = '' ): array {
		$placeholders = array(
			'signers'               => $this->build_signers_placeholders( $user_data, $config ),
			'read_only_text_fields' => $this->build_readonly_fields( $user_data, $convention_id ),
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
	 * IMPORTANT : Seuls les champs présents dans le template Yousign doivent être injectés.
	 * Liste des champs RO du template : convention_id, mentions_legales, full_name,
	 * full_name_stagiaire, date_realisation, date_jour
	 *
	 * @param array  $user_data     Données utilisateur enrichies (YousignDataCollector).
	 * @param string $convention_id Identifiant de convention (optionnel).
	 * @return array Read-only text fields.
	 */
	private function build_readonly_fields( array $user_data, string $convention_id = '' ): array {
		// IMPORTANT : Yousign API exige que TOUS les placeholders RO définis dans le template
		// soient remplis, même avec des valeurs vides. Ne pas utiliser de conditions if (!empty())
		
		$fields = array(
			// Convention ID
			array(
				'label' => 'convention_id',
				'text'  => $convention_id,
			),
			// Noms complets formatés
			array(
				'label' => 'full_name',
				'text'  => $user_data['full_name'] ?? '',
			),
			array(
				'label' => 'full_name_stagiaire',
				'text'  => $user_data['full_name_stagiaire'] ?? '',
			),
			// Mentions légales (HTML autorisé)
			array(
				'label' => 'mentions_legales',
				'text'  => $user_data['mentions_legales'] ?? '',
			),
			// Date de réalisation (date de la formation)
			array(
				'label' => 'date_realisation',
				'text'  => $user_data['date_realisation'] ?? '',
			),
			// Date du jour (date de signature)
			array(
				'label' => 'date_jour',
				'text'  => $user_data['date_jour'] ?? '',
			),
			// Total HT
			array(
				'label' => 'total_ht',
				'text'  => $user_data['total_ht'] ?? '0,00 €',
			),
			// Total TTC
			array(
				'label' => 'total_ttc',
				'text'  => $user_data['total_ttc'] ?? '0,00 €',
			),
			// TVA
			array(
				'label' => 'tva',
				'text'  => $user_data['tva'] ?? '0,00 €',
			),
		);

		LoggingHelper::debug( '[PayloadBuilder] Read-only fields built', array(
			'fields_count'          => count( $fields ),
			'has_convention_id'     => ! empty( $convention_id ),
			'has_full_name'         => ! empty( $user_data['full_name'] ),
			'has_mentions_legales'  => ! empty( $user_data['mentions_legales'] ),
			'has_date_realisation'  => ! empty( $user_data['date_realisation'] ),
			'has_date_jour'         => ! empty( $user_data['date_jour'] ),
			'has_total_ht'          => ! empty( $user_data['total_ht'] ),
			'has_total_ttc'         => ! empty( $user_data['total_ttc'] ),
			'has_tva'               => ! empty( $user_data['tva'] ),
		) );

		return $fields;
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
		if ( ! is_email( $user_data['email'] ) ) {
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

