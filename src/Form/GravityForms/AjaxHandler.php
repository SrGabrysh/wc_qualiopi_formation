<?php
/**
 * Gestion des requêtes AJAX pour le module Form
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\Siren\SirenAutocomplete;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator;
use WcQualiopiFormation\Helpers\NameFormatter;
use WcQualiopiFormation\Helpers\ValidationHelper;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\AjaxHelper;

/**
 * Classe de gestion des requêtes AJAX
 *
 * Fonctionnalités :
 * - Vérification SIRET via AJAX
 * - Récupération données SIREN API
 * - Génération mentions légales
 * - Mapping données vers champs GF
 */
class AjaxHandler {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance SirenAutocomplete
	 *
	 * @var SirenAutocomplete
	 */
	private $siren_autocomplete;

	/**
	 * Instance FieldMapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Instance MentionsGenerator
	 *
	 * @var MentionsGenerator
	 */
	private $mentions_generator;

	/**
	 * Constructeur
	 *
	 * @param Logger              $logger Instance du logger.
	 * @param SirenAutocomplete   $siren_autocomplete Instance SirenAutocomplete.
	 * @param MentionsGenerator   $mentions_generator Instance MentionsGenerator.
	 */
	public function __construct( Logger $logger, SirenAutocomplete $siren_autocomplete, MentionsGenerator $mentions_generator ) {
		$this->logger             = $logger;
		$this->siren_autocomplete = $siren_autocomplete;
		$this->field_mapper       = new FieldMapper();
		$this->mentions_generator = $mentions_generator;
	}

	/**
	 * Initialise les hooks AJAX
	 */
	public function init_hooks() {
		// Hook AJAX pour les utilisateurs connectés et non connectés.
		add_action( 'wp_ajax_wcqf_verify_siret', array( $this, 'handle_verify_siret' ) );
		add_action( 'wp_ajax_nopriv_wcqf_verify_siret', array( $this, 'handle_verify_siret' ) );
		
		$this->logger->info( '[DEBUG AjaxHandler] Hooks AJAX enregistres pour wcqf_verify_siret' );
	}

	/**
	 * Gère la requête AJAX de vérification SIRET
	 */
	public function handle_verify_siret() {
		LoggingHelper::log_ajax_request( $this->logger, 'handle_verify_siret', array(
			'_POST_keys' => array_keys( $_POST ),
			'_POST' => $_POST,
		) );
		
		// Vérification nonce.
		$nonce_validation = ValidationHelper::validate_nonce( $_POST['nonce'] ?? '', 'wcqf_verify_siret' );
		if ( ! $nonce_validation['valid'] ) {
			LoggingHelper::log_validation_error( $this->logger, 'nonce', $_POST['nonce'] ?? '', $nonce_validation['error'] );
			AjaxHelper::send_nonce_error( $nonce_validation['error'] );
		}

		// Récupérer et valider les paramètres.
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$siret   = isset( $_POST['siret'] ) ? sanitize_text_field( wp_unslash( $_POST['siret'] ) ) : '';
		$prenom  = isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '';
		$nom     = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';

		LoggingHelper::log_ajax_request( $this->logger, 'parametres_extraits', array(
			'form_id' => $form_id,
			'siret' => $siret,
			'prenom' => $prenom,
			'nom' => $nom,
		) );

		// Validation des paramètres requis
		$required_params = array( 'form_id', 'siret', 'nom', 'prenom' );
		$params_data = array(
			'form_id' => $form_id,
			'siret' => $siret,
			'nom' => $nom,
			'prenom' => $prenom,
		);

		$params_validation = ValidationHelper::validate_ajax_params( $required_params, $params_data );
		if ( ! $params_validation['valid'] ) {
			LoggingHelper::log_validation_error( $this->logger, 'ajax_params', $params_data, $params_validation['error'] );
			AjaxHelper::send_missing_params_error( $params_validation['missing'], $params_validation['error'] );
		}

		// FORMATAGE ET VALIDATION NOMS/PRÉNOMS (même logique que code source).
		$nom_result = NameFormatter::format( $nom );
		$prenom_result = NameFormatter::format( $prenom );

		if ( ! $nom_result['valid'] ) {
			$this->logger->warning( '[AJAX] Nom invalide', array(
				'nom' => $nom,
				'error' => $nom_result['error'],
			) );
			AjaxHelper::send_validation_error( 'nom', $nom_result['error'] );
		}

		if ( ! $prenom_result['valid'] ) {
			$this->logger->warning( '[AJAX] Prenom invalide', array(
				'prenom' => $prenom,
				'error' => $prenom_result['error'],
			) );
			AjaxHelper::send_validation_error( 'prenom', $prenom_result['error'] );
		}

		// Appliquer le formatage spécifique (nom en majuscules, prénom première lettre)
		$nom_formate = NameFormatter::format_nom( $nom );
		$prenom_formate = NameFormatter::format_prenom( $prenom );

		$this->logger->info( '[AJAX] Noms/prenoms valides et formates', array(
			'prenom_avant' => $prenom,
			'prenom_apres' => $prenom_formate,
			'nom_avant' => $nom,
			'nom_apres' => $nom_formate,
		) );

		// Vérifier que le formulaire a un mapping.
		if ( ! $this->field_mapper->form_has_mapping( $form_id ) ) {
			AjaxHelper::send_error( __( 'Ce formulaire n\'est pas configuré pour la vérification SIRET.', Constants::TEXT_DOMAIN ), 'form_not_configured' );
		}

		// Appeler l'API SIREN.
		$this->logger->info( '[AJAX] Appel SirenAutocomplete::get_company_data', array(
			'siret' => $siret,
		) );

		$company_data = $this->siren_autocomplete->get_company_data( $siret );

		if ( is_wp_error( $company_data ) ) {
			$this->logger->error( '[AJAX] ERREUR SIREN API', array(
				'siret'   => $siret,
				'error_code' => $company_data->get_error_code(),
				'error_message'   => $company_data->get_error_message(),
			) );

			AjaxHelper::send_api_error( 'SIREN', $company_data->get_error_message() );
		}

		$this->logger->info( '[AJAX] DONNEES ENTREPRISE RECUPEREES', array(
			'siret' => $siret,
			'company_data_keys' => array_keys( $company_data ),
			'company_data' => $company_data,
		) );

		// Récupérer le mapping du formulaire.
		$mapping = $this->field_mapper->get_field_mapping( $form_id );
		
		if ( false === $mapping ) {
			$this->logger->error( '[AJAX] ERREUR: Aucun mapping trouve pour le formulaire', array( 'form_id' => $form_id ) );
			AjaxHelper::send_error( __( 'Aucun mapping configuré pour ce formulaire.', Constants::TEXT_DOMAIN ), 'no_mapping' );
		}

		$this->logger->info( '[AJAX] Mapping recupere', array(
			'form_id' => $form_id,
			'mapping_keys' => array_keys( $mapping ),
		) );

		// Préparer les données du représentant (AVEC NOMS FORMATÉS ET VALIDÉS).
		$representant = array(
			'prenom' => $prenom_formate,
			'nom'    => $nom_formate,
		);

		$this->logger->info( '[AJAX] Representant prepare', array(
			'representant' => $representant,
		) );

		// Générer les mentions légales.
		$this->logger->info( '[AJAX] Appel MentionsGenerator::generate', array(
			'company_data' => $company_data,
			'representant' => $representant,
		) );

		$mentions = $this->mentions_generator->generate( $company_data, $representant );

		$this->logger->info( '[AJAX] MENTIONS LEGALES GENEREES', array(
			'mentions_length' => strlen( $mentions ),
			'mentions' => $mentions,
		) );

		// Mapper les données vers les champs du formulaire (avec mentions).
		$mapped_data = $this->field_mapper->map_data_to_fields( $company_data, $mapping, $mentions );

		$this->logger->info( '[AJAX] DONNEES MAPPEES FINALES', array(
			'mapped_data_keys' => array_keys( $mapped_data ),
			'mapped_data' => $mapped_data,
		) );

		// Message de succès.
		$message = sprintf(
			__( 'Entreprise trouvée : %s', Constants::TEXT_DOMAIN ),
			$company_data['denomination'] ?? $company_data['nom'] . ' ' . $company_data['prenom']
		);

		// Préparer la réponse finale SANS double wrapping
		// Structure attendue côté JS : response.data.xxx
		// ATTENTION : Utiliser + au lieu de array_merge pour préserver les clés numériques !
		$response = $mapped_data + array(
			'denomination'     => $company_data['denomination'] ?? '',
			'est_actif'        => $company_data['is_active'] ?? true,
			'type_entreprise'  => $company_data['type_entreprise'] ?? '',
			'message'          => $message,
			'representant'     => $representant, // NOMS FORMATÉS pour réinjection frontend.
		);

		$this->logger->info( '[AJAX] Reponse finale envoyee au frontend', array(
			'has_representant' => ! empty( $response['representant'] ),
			'representant_prenom' => $response['representant']['prenom'] ?? '',
			'representant_nom' => $response['representant']['nom'] ?? '',
			'mapped_data_keys' => array_keys( $mapped_data ),
		) );

		// Appeler directement wp_send_json_success pour éviter le double wrapping
		// Structure finale : {success: true, data: {champs + métadonnées}}
		\wp_send_json_success( $response );
	}
}

