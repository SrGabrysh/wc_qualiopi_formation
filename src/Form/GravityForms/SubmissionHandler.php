<?php
/**
 * Gestion des soumissions de formulaires Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Data\ProgressTracker;
use WcQualiopiFormation\Form\Tracking\TrackingManager;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Security\TokenManager;

/**
 * Classe de gestion des soumissions de formulaires
 *
 * Fonctionnalités :
 * - Validation SIRET avant soumission
 * - Extraction données formulaire
 * - Enregistrement dans wp_wcqf_tracking
 * - Mise à jour progression (wp_wcqf_progress)
 * - Audit trail
 */
class SubmissionHandler {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du field mapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Instance du tracking manager
	 *
	 * @var TrackingManager
	 */
	private $tracking_manager;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger           = $logger;
		$this->field_mapper     = new FieldMapper();
		$this->tracking_manager = new TrackingManager( $logger );
	}

	/**
	 * Initialise les hooks Gravity Forms
	 */
	public function init_hooks() {
		// Vérifier si Gravity Forms est actif.
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Hook validation avant soumission.
		add_filter( 'gform_validation', array( $this, 'validate_submission' ) );

		// Hook après soumission.
		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Valide une soumission de formulaire
	 *
	 * @param array $validation_result Résultat de validation GF.
	 * @return array Résultat de validation modifié.
	 */
	public function validate_submission( $validation_result ) {
		$form = $validation_result['form'];

		// Vérifier si le formulaire doit être validé.
		if ( ! $this->should_validate_form( $form ) ) {
			return $validation_result;
		}

		// Récupérer le token depuis le formulaire.
		$token = rgar( $_POST, 'input_9999' );

		if ( empty( $token ) ) {
			$validation_result['is_valid'] = false;
			$this->logger->error( 'Token missing in form submission', array( 'form_id' => $form['id'] ) );

			// Ajouter message d'erreur global.
			$validation_result['form']['validation_message'] = __( 'Erreur de validation : token manquant. Veuillez recharger la page.', Constants::TEXT_DOMAIN );

			return $validation_result;
		}

		// Valider le token HMAC.
		$token_data = TokenManager::verify( $token );

		if ( false === $token_data ) {
			$validation_result['is_valid'] = false;
			$this->logger->error( 'Token validation failed', array( 'form_id' => $form['id'], 'token' => substr( $token, 0, 10 ) . '...' ) );

			// Ajouter message d'erreur global.
			$validation_result['form']['validation_message'] = __( 'Erreur de validation : token invalide ou expiré. Veuillez recharger la page.', Constants::TEXT_DOMAIN );

			return $validation_result;
		}

		// Valider le SIRET.
		$siret_field_id = $this->field_mapper->get_siret_field_id( $form['id'] );
		if ( false !== $siret_field_id ) {
			$siret = rgar( $_POST, 'input_' . str_replace( '.', '_', $siret_field_id ) );

			if ( ! $this->validate_siret_format( $siret ) ) {
				// Invalider le champ SIRET.
				foreach ( $form['fields'] as &$field ) {
					if ( (string) $field->id === (string) $siret_field_id ) {
						$field->failed_validation  = true;
						$field->validation_message = __( 'Le SIRET fourni est invalide (format incorrect ou clé de Luhn invalide).', Constants::TEXT_DOMAIN );
						break;
					}
				}

				$validation_result['is_valid'] = false;
				$validation_result['form']     = $form;

				$this->logger->warning( 'SIRET validation failed', array( 'siret' => $siret, 'form_id' => $form['id'] ) );
			}
		}

		$this->logger->info( 'Form validation completed', array(
			'form_id'  => $form['id'],
			'is_valid' => $validation_result['is_valid'],
		) );

		return $validation_result;
	}

	/**
	 * Gère une soumission de formulaire validée
	 *
	 * @param array $entry Entrée Gravity Forms.
	 * @param array $form Formulaire Gravity Forms.
	 */
	public function handle_submission( $entry, $form ) {
		// Récupérer le token HMAC.
		$token = rgar( $entry, '9999' ); // ID champ token.

		if ( empty( $token ) ) {
			$this->logger->error( 'No token found in form submission', array( 'form_id' => $form['id'] ) );
			return;
		}

		// 1. Extraire données.
		$data = $this->extract_form_data( $entry, $form );

		// 2. Tracking.
		$this->tracking_manager->save_submission( $token, $form['id'], $entry['id'], $data );

		// 3. Progression.
		$progress_tracker = ProgressTracker::get_instance();
		$progress_tracker->add_data( $token, array(
			'form_submission' => $data,
			'form_id'         => $form['id'],
			'entry_id'        => $entry['id'],
		) );
		$progress_tracker->update_step( $token, Constants::STEP_FORM );

		// 4. Audit (TODO: une fois AuditManager créé).

		$this->logger->info( 'Form submission handled successfully', array(
			'token'    => substr( $token, 0, 10 ) . '...',
			'form_id'  => $form['id'],
			'entry_id' => $entry['id'],
		) );
	}

	/**
	 * Extrait les données du formulaire
	 *
	 * @param array $entry Entrée GF.
	 * @param array $form Formulaire GF.
	 * @return array Données structurées.
	 */
	private function extract_form_data( $entry, $form ) {
		$mapping = $this->field_mapper->get_field_mapping( $form['id'] );

		$data = array(
			'personal' => array(),
			'company'  => array(),
			'test'     => array(),
			'metadata' => array(
				'form_id'        => $form['id'],
				'entry_id'       => $entry['id'],
				'submission_date' => current_time( 'mysql' ),
				'ip_address'     => rgar( $entry, 'ip' ),
				'user_agent'     => rgar( $entry, 'user_agent' ),
			),
		);

		// Extraire données personnelles (représentant).
		$representant = $this->field_mapper->get_representant_data( $form['id'], $entry );
		if ( ! empty( $representant['prenom'] ) || ! empty( $representant['nom'] ) ) {
			$data['personal'] = $representant;
		}

		// Extraire données entreprise.
		if ( ! empty( $mapping['siret'] ) ) {
			$data['company']['siret'] = rgar( $entry, $mapping['siret'] );
		}
		if ( ! empty( $mapping['denomination'] ) ) {
			$data['company']['denomination'] = rgar( $entry, $mapping['denomination'] );
		}

		// Extraire toutes les autres données du formulaire.
		foreach ( $form['fields'] as $field ) {
			$field_id = $field->id;

			// Ignorer le token.
			if ( 9999 === $field_id ) {
				continue;
			}

			// Ignorer les champs déjà extraits.
			if ( in_array( (string) $field_id, array_values( $mapping ), true ) ) {
				continue;
			}

			// Extraire la valeur.
			$value = rgar( $entry, (string) $field_id );

			if ( ! empty( $value ) ) {
				$data['test'][ 'field_' . $field_id ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Valide le format d'un SIRET
	 *
	 * @param string $siret SIRET à valider.
	 * @return bool True si valide.
	 */
	private function validate_siret_format( $siret ) {
		// Nettoyage.
		$siret = preg_replace( '/[^0-9]/', '', $siret );

		// Vérifier format (14 chiffres).
		if ( ! preg_match( '/^[0-9]{14}$/', $siret ) ) {
			return false;
		}

		// Algorithme Luhn.
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
	 * Vérifie si le formulaire doit être validé
	 *
	 * @param array $form Formulaire GF.
	 * @return bool True si doit valider.
	 */
	private function should_validate_form( $form ) {
		return $this->field_mapper->form_has_mapping( $form['id'] );
	}
}
