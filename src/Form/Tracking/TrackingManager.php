<?php
/**
 * Gestion du tracking des soumissions de formulaires
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Tracking;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\Tracking\DataExtractor;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Form\Tracking\TrackingStorage;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de gestion du tracking des soumissions
 *
 * Fonctionnalités :
 * - Enregistrement dans wp_wcqf_tracking
 * - Extraction données formulaire
 * - Anonymisation IP (RGPD)
 * - Métadonnées SIRET
 */
class TrackingManager {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du data extractor
	 *
	 * @var DataExtractor
	 */
	private $data_extractor;

	/**
	 * Instance du field mapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Instance du storage
	 *
	 * @var TrackingStorage
	 */
	private $storage;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger         = $logger;
		$this->data_extractor = new DataExtractor();
		$this->field_mapper   = new FieldMapper();
		$this->storage        = new TrackingStorage( $logger );

		LoggingHelper::log_db_operation( $this->logger, 'init', 'TrackingManager', 'storage_initialized' );
	}

	/**
	 * Initialise les hooks
	 */
	public function init_hooks() {
		// Vérifier si Gravity Forms est actif.
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Hook après soumission Gravity Forms (priorité 20 pour s'exécuter après SubmissionHandler).
		add_action( 'gform_after_submission', array( $this, 'capture_submission' ), 20, 2 );
	}

	/**
	 * Capture une soumission de formulaire
	 *
	 * @param array $entry Entrée Gravity Forms.
	 * @param array $form Formulaire Gravity Forms.
	 */
	public function capture_submission( $entry, $form ) {
		// Security: Validate inputs
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			LoggingHelper::log_validation_error( $this->logger, 'entry_form_data', array( 'entry' => $entry, 'form' => $form ), 'Invalid entry or form data provided' );
			return;
		}
		LoggingHelper::log_db_operation( $this->logger, 'capture', 'TrackingManager', 'submission_start', array(
			'form_id'  => $form['id'],
			'entry_id' => $entry['id'] ?? null,
		) );

		// Vérifier si le formulaire doit être tracké.
		if ( ! $this->should_track_form( $form['id'] ) ) {
			$this->logger->debug( '[TrackingManager] Formulaire non tracke, skip', array( 'form_id' => $form['id'] ) );
			return;
		}

		LoggingHelper::log_db_operation( $this->logger, 'track', 'TrackingManager', 'form_tracked', array(
			'form_id'  => $form['id'],
			'entry_id' => $entry['id'] ?? null,
		) );

		// Récupérer le token HMAC.
		$token = SanitizationHelper::sanitize_siret( rgar( $entry, '9999' ) );

		if ( empty( $token ) ) {
			LoggingHelper::log_validation_error( $this->logger, 'token', $form['id'], 'No token found in submission' );
			// Continuer quand même le tracking sans token.
		}

		// Extraire toutes les données structurées.
		$extracted_data = $this->data_extractor->extract( $entry, $form );

		// Préparer les données pour l'enregistrement.
		$data = array(
			'token'        => $token ?? null,
			'form_id'      => absint( $form['id'] ),
			'entry_id'     => absint( $entry['id'] ?? 0 ),
			'user_id'      => get_current_user_id(),
			'siret'        => SanitizationHelper::sanitize_siret( $extracted_data['company']['siret'] ?? null ),
			'company_name' => SanitizationHelper::sanitize_name( $extracted_data['company']['name'] ?? null ),
			'form_data'    => $this->serialize_extracted_data( $extracted_data ),
			'submitted_at' => current_time( 'mysql' ),
			'ip_address'   => $this->anonymize_ip( $entry['ip'] ?? '' ),
			'user_agent'   => SanitizationHelper::sanitize_name( substr( $entry['user_agent'] ?? '', 0, 255 ) ),
		);

		// Enregistrer en base de données.
		$this->save_submission( $token, $form['id'], $entry['id'], $data );
	}

	/**
	 * Enregistre une soumission dans la base de données
	 *
	 * @param string|null $token Token HMAC (peut être null).
	 * @param int         $form_id ID du formulaire.
	 * @param int         $entry_id ID de l'entrée GF.
	 * @param array       $data Données à enregistrer.
	 */
	public function save_submission( $token, $form_id, $entry_id, $data ) {
		// Security: Sanitize inputs
		$token = $token ? SanitizationHelper::sanitize_siret( $token ) : null;
		$form_id = absint( $form_id );
		$entry_id = absint( $entry_id );
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcqf_tracking';

		$insert_data = array(
			'token'        => $token,
			'form_id'      => $form_id,
			'entry_id'     => $entry_id,
			'user_id'      => $data['user_id'] ?? get_current_user_id(),
			'siret'        => $data['siret'] ?? null,
			'company_name' => $data['company_name'] ?? null,
			'form_data'    => $data['form_data'] ?? null,
			'submitted_at' => $data['submitted_at'] ?? current_time( 'mysql' ),
			'ip_address'   => $data['ip_address'] ?? null,
			'user_agent'   => $data['user_agent'] ?? null,
		);

		$formats = array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( $table_name, $insert_data, $formats );

		if ( false === $result ) {
			LoggingHelper::log_db_operation( $this->logger, 'error', 'TrackingManager', 'save_failed', array(
				'token'    => $token,
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'error'    => $wpdb->last_error,
			) );
			return false;
		}

		$insert_id = $wpdb->insert_id;

		LoggingHelper::log_db_operation( $this->logger, 'success', 'TrackingManager', 'submission_saved', array(
			'tracking_id' => $insert_id,
			'token'       => $token ? substr( $token, 0, 10 ) . '...' : 'N/A',
			'form_id'     => $form_id,
			'entry_id'    => $entry_id,
		) );

		return $insert_id;
	}

	/**
	 * Vérifie si un formulaire doit être tracké
	 *
	 * @param int $form_id ID du formulaire.
	 * @return bool True si doit être tracké.
	 */
	private function should_track_form( $form_id ) {
		// Vérifier si le formulaire a un mapping configuré.
		// Si un formulaire a un mapping, il doit être tracké.
		return $this->field_mapper->form_has_mapping( $form_id );
	}

	/**
	 * Anonymise une adresse IP (RGPD)
	 *
	 * @param string $ip Adresse IP.
	 * @return string IP anonymisée.
	 */
	private function anonymize_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '';
		}

		// Utiliser fonction WordPress si disponible (disponible depuis WP 4.9.6).
		if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return wp_privacy_anonymize_ip( $ip );
		}

		// Fallback manuel pour IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0'; // Anonymiser le dernier octet.
			return implode( '.', $parts );
		}

		// Fallback manuel pour IPv6.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			// Garder seulement les 4 premiers segments.
			$parts = array_slice( $parts, 0, 4 );
			return implode( ':', $parts ) . '::';
		}

		// Si format inconnu, retourner vide par sécurité.
		return '';
	}

	/**
	 * Sérialise les données extraites en JSON
	 *
	 * @param array $extracted_data Données extraites.
	 * @return string JSON encodé.
	 */
	private function serialize_extracted_data( $extracted_data ) {
		return wp_json_encode( $extracted_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Récupère toutes les soumissions d'un utilisateur
	 *
	 * @param int $user_id ID utilisateur.
	 * @return array Liste des soumissions.
	 */
	public function get_user_submissions( $user_id ) {
		// Security: Validate user permissions
		if ( ! current_user_can( 'read' ) && $user_id !== get_current_user_id() ) {
			return array();
		}

		// Security: Sanitize user ID
		$user_id = absint( $user_id );
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcqf_tracking';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY submitted_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Récupère une soumission par token
	 *
	 * @param string $token Token HMAC.
	 * @return array|null Données de soumission ou null.
	 */
	public function get_submission_by_token( $token ) {
		// Security: Sanitize token
		$token = SanitizationHelper::sanitize_siret( $token );
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcqf_tracking';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token = %s ORDER BY submitted_at DESC LIMIT 1",
				$token
			),
			ARRAY_A
		);

		return $result;
	}

	/**
	 * Récupère les statistiques de tracking
	 *
	 * @return array Statistiques.
	 */
	public function get_stats() {
		// Security: Check admin capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcqf_tracking';

		$stats = array(
			'total_submissions'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ),
			'unique_users'       => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE user_id > 0" ),
			'unique_companies'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT siret) FROM {$table_name} WHERE siret IS NOT NULL" ),
			'submissions_today'  => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE DATE(submitted_at) = %s",
					current_time( 'Y-m-d' )
				)
			),
			'submissions_7days'  => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE submitted_at >= DATE_SUB(%s, INTERVAL 7 DAY)",
					current_time( 'mysql' )
				)
			),
			'submissions_30days' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE submitted_at >= DATE_SUB(%s, INTERVAL 30 DAY)",
					current_time( 'mysql' )
				)
			),
		);

		return $stats;
	}
}
