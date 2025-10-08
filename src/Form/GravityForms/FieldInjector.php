<?php
/**
 * Injection de champs et boutons dans Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\TokenManager;
use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;

/**
 * Classe d'injection de champs dans les formulaires
 *
 * Fonctionnalités :
 * - Injection token HMAC (champ caché)
 * - Injection bouton "Vérifier le SIRET"
 * - Enqueue assets CSS/JS
 */
class FieldInjector {

	/**
	 * Instance du field mapper
	 *
	 * @var FieldMapper
	 */
	private $field_mapper;

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->field_mapper = new FieldMapper();
	}

	/**
	 * Initialise les hooks Gravity Forms
	 */
	public function init_hooks() {
		// Vérifier si Gravity Forms est actif.
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Hook pour injection token HMAC.
		add_filter( 'gform_pre_render', array( $this, 'inject_token_field' ) );

		// Hook pour injection bouton "Vérifier".
		add_filter( 'gform_field_content', array( $this, 'inject_verify_button' ), 10, 5 );

		// Hook pour enqueue assets.
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 2 );
	}

	/**
	 * Injecte le champ token HMAC dans le formulaire
	 *
	 * @param array $form Formulaire Gravity Forms.
	 * @return array Formulaire modifié.
	 */
	public function inject_token_field( $form ) {
		// Vérifier si le formulaire doit avoir un token.
		if ( ! $this->should_inject_token( $form ) ) {
			return $form;
		}

		// Récupérer ou créer le token.
		$token = $this->get_or_create_token();

		if ( empty( $token ) ) {
			LoggingHelper::error( 'Failed to generate token for form', array( 'form_id' => $form['id'] ) );
			return $form;
		}

		// Créer le champ caché pour le token.
		$token_field = array(
			'type'         => 'hidden',
			'id'           => 9999, // ID très élevé pour éviter conflits.
			'label'        => 'WCQF Token',
			'adminLabel'   => 'Token HMAC',
			'cssClass'     => 'wcqf-token-field',
			'defaultValue' => $token,
		);

		// Ajouter le champ au formulaire.
		$form['fields'][] = \GF_Fields::create( $token_field );

		LoggingHelper::info( 'Token field injected', array( 'form_id' => $form['id'], 'token' => substr( $token, 0, 10 ) . '...' ) );

		return $form;
	}

	/**
	 * Injecte le bouton "Vérifier le SIRET" après le champ SIRET
	 *
	 * @param string $field_content Contenu HTML du champ.
	 * @param object $field Objet champ Gravity Forms.
	 * @param mixed  $value Valeur du champ.
	 * @param int    $entry_id ID de l'entrée (0 si nouveau).
	 * @param int    $form_id ID du formulaire.
	 * @return string Contenu HTML modifié.
	 */
	public function inject_verify_button( $field_content, $field, $value, $entry_id, $form_id ) {
		// Vérifier si le formulaire a un mapping.
		if ( ! $this->field_mapper->form_has_mapping( $form_id ) ) {
			return $field_content;
		}

		// Récupérer l'ID du champ SIRET mappé.
		$siret_field_id = $this->field_mapper->get_siret_field_id( $form_id );

		// Vérifier si c'est le champ SIRET.
		if ( false === $siret_field_id || (string) $field->id !== (string) $siret_field_id ) {
			return $field_content;
		}

		// Générer le bouton de vérification.
		$button_html = $this->get_verify_button_html( $form_id, $field->id );

		// Injecter le bouton après le champ.
		$field_content .= $button_html;

		return $field_content;
	}

	/**
	 * Génère le HTML du bouton de vérification
	 *
	 * @param int    $form_id ID du formulaire.
	 * @param string $field_id ID du champ.
	 * @return string HTML du bouton.
	 */
	private function get_verify_button_html( $form_id, $field_id ) {
		$nonce = wp_create_nonce( 'wcqf_verify_siret' );

		$button_text = apply_filters( 'wcqf_verify_button_text', __( 'Vérifier le SIRET', Constants::TEXT_DOMAIN ), $form_id );

		$html  = '<div class="wcqf-form-verify-container">';
		$html .= sprintf(
			'<button type="button" class="wcqf-form-verify-button" data-form-id="%d" data-field-id="%s" data-nonce="%s">%s</button>',
			esc_attr( $form_id ),
			esc_attr( $field_id ),
			esc_attr( $nonce ),
			esc_html( $button_text )
		);
		$html .= '<span class="wcqf-form-loader" style="display:none;">⏳</span>';
		$html .= '<div class="wcqf-form-message"></div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Enqueue les assets CSS/JS pour le formulaire
	 *
	 * @param array $form Formulaire Gravity Forms.
	 * @param bool  $is_ajax Si le formulaire utilise AJAX.
	 */
	public function enqueue_assets( $form, $is_ajax ) {
		$form_id = $form['id'] ?? 0;

		// Vérifier si le formulaire a un mapping.
		if ( ! $this->field_mapper->form_has_mapping( $form_id ) ) {
			return;
		}

		// Enqueue CSS frontend.
		wp_enqueue_style(
			'wcqf-form-frontend',
			plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets/css/form-frontend.css',
			array(),
			WCQF_VERSION
		);

		// Enqueue JS frontend.
		wp_enqueue_script(
			'wcqf-form-frontend',
			plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets/js/form-frontend.js',
			array( 'jquery' ),
			WCQF_VERSION,
			true
		);

		// Enqueue JS debug pour transitions de pages (développement).
		wp_enqueue_script(
			'wcqf-page-debug',
			plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets/js/page-transition-debug.js',
			array( 'jquery' ),
			WCQF_VERSION,
			true
		);

		// Localiser le script avec les données nécessaires.
		wp_localize_script(
			'wcqf-form-frontend',
			'wcqfFormData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcqf_verify_siret' ),
				'form_id'  => $form_id,
				'messages' => $this->get_frontend_messages(),
			)
		);
	}

	/**
	 * Récupère les messages frontend traduisibles
	 *
	 * @return array Messages.
	 */
	private function get_frontend_messages() {
		return array(
			'verifying'                       => __( 'Vérification en cours...', Constants::TEXT_DOMAIN ),
			'success'                         => __( 'Entreprise trouvée : %s', Constants::TEXT_DOMAIN ),
			'error_invalid'                   => __( 'Le SIRET fourni est invalide (format ou clé de vérification incorrecte).', Constants::TEXT_DOMAIN ),
			'error_not_found'                 => __( 'Aucune entreprise trouvée avec ce SIRET.', Constants::TEXT_DOMAIN ),
			'error_api'                       => __( 'Erreur lors de la vérification. Veuillez réessayer.', Constants::TEXT_DOMAIN ),
			'error_timeout'                   => __( 'La vérification a pris trop de temps. Veuillez réessayer.', Constants::TEXT_DOMAIN ),
			'error_representant_required'     => __( '⚠️ Veuillez renseigner le nom et le prénom du représentant avant de vérifier le SIRET.', Constants::TEXT_DOMAIN ),
			'error_representant_invalid'      => __( 'Les chiffres ne sont pas autorisés dans les noms et prénoms.', Constants::TEXT_DOMAIN ),
			'warning_inactive'                => __( '⚠️ Cette entreprise est inactive.', Constants::TEXT_DOMAIN ),
			'warning_modified'                => __( 'Vous avez modifié les données vérifiées.', Constants::TEXT_DOMAIN ),
			'warning_representant_modified'   => __( 'Vous avez modifié les données du représentant.', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Vérifie si le token doit être injecté dans le formulaire
	 *
	 * @param array $form Formulaire Gravity Forms.
	 * @return bool True si doit injecter.
	 */
	private function should_inject_token( $form ) {
		// Injecter le token si le formulaire a un mapping.
		return $this->field_mapper->form_has_mapping( $form['id'] );
	}

	/**
	 * Récupère ou crée un token HMAC
	 *
	 * @return string Token HMAC.
	 */
	private function get_or_create_token() {
		// Récupérer token existant depuis session (méthode statique).
		$session_token = SessionManager::get( Constants::SESSION_KEY_TOKEN );

		if ( ! empty( $session_token ) ) {
			return $session_token;
		}

		// Créer nouveau token.
		$user_id    = get_current_user_id();
		$product_id = $this->get_product_id_from_cart();

		$token = TokenManager::generate( $user_id, $product_id );

		// Enregistrer en session (méthode statique).
		SessionManager::set( Constants::SESSION_KEY_TOKEN, $token, Constants::SESSION_TTL_MINUTES * MINUTE_IN_SECONDS );

		LoggingHelper::info( 'New token created', array(
			'user_id'    => $user_id,
			'product_id' => $product_id,
		) );

		return $token;
	}

	/**
	 * Récupère le product_id depuis le panier WooCommerce
	 *
	 * @return int Product ID ou 0.
	 */
	private function get_product_id_from_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$cart_items = WC()->cart->get_cart();

		if ( empty( $cart_items ) ) {
			return 0;
		}

		// Récupérer le premier produit du panier.
		$first_item = reset( $cart_items );
		return $first_item['product_id'] ?? 0;
	}
}
