<?php
/**
 * AjaxHandler - Gestion des requêtes AJAX admin
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SecurityHelper;
use WcQualiopiFormation\Form\FormManager;
use WcQualiopiFormation\Helpers\SanitizationHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxHandler
 * Gestion des requêtes AJAX pour l'administration
 */
class AjaxHandler {

	/**
	 * Instance du Form Manager
	 *
	 * @var FormManager
	 */
	private $form_manager;

/**
	 * Constructeur
	 *
	 * @param FormManager $form_manager Instance du Form Manager.
	 */
	public function __construct( $form_manager ) {
		$this->form_manager = $form_manager;
	}

	/**
	 * Initialise les hooks AJAX
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Actions AJAX admin uniquement.
		add_action( 'wp_ajax_wcqf_test_api', array( $this, 'handle_test_api' ) );
		add_action( 'wp_ajax_wcqf_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'wp_ajax_wcqf_get_logs', array( $this, 'handle_get_logs' ) );
		add_action( 'wp_ajax_wcqf_export_logs', array( $this, 'handle_export_logs' ) );
		add_action( 'wp_ajax_wcqf_load_form_fields', array( $this, 'handle_load_form_fields' ) );
	}

	/**
	 * Gère le test de connexion à l'API
	 *
	 * @return void
	 */
	public function handle_test_api() {
		// Vérifier nonce et permissions.
		if ( ! $this->verify_admin_request() ) {
			SecurityHelper::ajax_error( __( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ), 403 );
		}

		// Tester la connexion via SirenAutocomplete.
		$siren_autocomplete = $this->form_manager->get_siren_autocomplete();

		if ( ! $siren_autocomplete ) {
			SecurityHelper::ajax_error( __( 'Module SIREN non disponible.', Constants::TEXT_DOMAIN ), 500 );
		}

		$result = $siren_autocomplete->test_api_connection();

		if ( is_wp_error( $result ) ) {
			SecurityHelper::ajax_error( $result->get_error_message(), 500 );
		}

		SecurityHelper::ajax_success( array(
			'message' => __( 'Connexion réussie !', Constants::TEXT_DOMAIN ),
			'data'    => $result,
		) );
	}

	/**
	 * Gère le vidage du cache
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		// Vérifier nonce et permissions.
		if ( ! $this->verify_admin_request() ) {
			SecurityHelper::ajax_error( __( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ), 403 );
		}

		$siren_autocomplete = $this->form_manager->get_siren_autocomplete();

		if ( ! $siren_autocomplete ) {
			SecurityHelper::ajax_error( __( 'Module SIREN non disponible.', Constants::TEXT_DOMAIN ), 500 );
		}

		$count = $siren_autocomplete->clear_cache();

		SecurityHelper::ajax_success( array(
			'message' => sprintf( __( '%d entrées supprimées du cache.', Constants::TEXT_DOMAIN ), $count ),
			'count'   => $count,
		) );
	}

	/**
	 * Récupère les logs (AJAX)
	 *
	 * @return void
	 */
	public function handle_get_logs() {
		// Vérifier nonce et permissions.
		if ( ! $this->verify_admin_request() ) {
			SecurityHelper::ajax_error( __( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ), 403 );
		}

        $log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			SecurityHelper::ajax_success( array(
				'logs' => array(),
			) );
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$lines = array_slice( array_reverse( $lines ), 0, 50 );

		SecurityHelper::ajax_success( array(
			'logs' => $lines,
		) );
	}

	/**
	 * Exporte les logs (AJAX)
	 *
	 * @return void
	 */
	public function handle_export_logs() {
		// Vérifier nonce et permissions.
		if ( ! $this->verify_admin_request() ) {
			SecurityHelper::ajax_error( __( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ), 403 );
		}

        $log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			SecurityHelper::ajax_error( __( 'Aucun log disponible.', Constants::TEXT_DOMAIN ), 404 );
		}

		$content = file_get_contents( $log_file );

		SecurityHelper::ajax_success( array(
			'content'  => $content,
			'filename' => 'wcqf-logs-' . date( 'Y-m-d-H-i-s' ) . '.log',
		) );
	}

	/**
	 * Charge les champs d'un formulaire Gravity Forms (AJAX)
	 *
	 * @return void
	 */
	public function handle_load_form_fields() {
		// Vérifier nonce et permissions.
		if ( ! $this->verify_admin_request() ) {
			SecurityHelper::ajax_error( __( 'Permissions insuffisantes.', Constants::TEXT_DOMAIN ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		if ( ! $form_id ) {
			SecurityHelper::ajax_error( __( 'ID de formulaire requis.', Constants::TEXT_DOMAIN ), 400 );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			SecurityHelper::ajax_error( __( 'Gravity Forms non disponible.', Constants::TEXT_DOMAIN ), 500 );
		}

		$form = \GFAPI::get_form( $form_id );

		if ( ! $form ) {
			SecurityHelper::ajax_error( __( 'Formulaire introuvable.', Constants::TEXT_DOMAIN ), 404 );
		}

		$fields = array();

		foreach ( $form['fields'] as $field ) {
			$fields[] = array(
				'id'    => $field->id,
				'label' => $field->label,
				'type'  => $field->type,
			);
		}

		SecurityHelper::ajax_success( array(
			'fields' => $fields,
		) );
	}

	/**
	 * Vérifie la requête admin (nonce + permissions)
	 *
	 * @return bool True si autorisé.
	 */
	private function verify_admin_request() {
		$nonce = isset( $_POST['nonce'] ) ? SanitizationHelper::sanitize_name( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! SecurityHelper::verify_nonce( $nonce, 'wcqf_admin_action' ) ) {
			return false;
		}

		if ( ! SecurityHelper::check_admin_capability() ) {
			return false;
		}

		return true;
	}
}




