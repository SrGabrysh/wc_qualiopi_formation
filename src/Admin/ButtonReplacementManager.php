<?php
/**
 * Button Replacement Manager
 * 
 * RESPONSABILITÉ UNIQUE : Remplacement du bouton "Suivant" par "Retour à l'accueil" 
 * pour les utilisateurs ayant échoué au test de positionnement (statut 'refused')
 *
 * @package WcQualiopiFormation\Admin
 * @since 1.1.0
 * 
 * @diagramme_architecture
 * 
 * ```mermaid
 * sequenceDiagram
 *     participant User as Utilisateur
 *     participant GF as Gravity Forms
 *     participant BRM as ButtonReplacementManager
 *     participant SM as SessionManager
 *     participant LH as LoggingHelper
 * 
 *     User->>GF: Accès page 3 avec résultats
 *     GF->>BRM: gform_field_content (form_id, field_content)
 *     BRM->>BRM: Vérifier présence .gform_next_button
 *     BRM->>SM: get('test_result_' + form_id)
 *     SM-->>BRM: statut ('refused' | 'reinforced' | 'admitted')
 * 
 *     alt Statut = 'refused'
 *         BRM->>BRM: Remplacer HTML bouton
 *         BRM->>BRM: Injecter JavaScript redirection
 *         BRM->>LH: info("Button replaced for refused user")
 *         BRM-->>GF: HTML modifié avec "Retour à l'accueil"
 *         GF-->>User: Affichage bouton "Retour à l'accueil"
 *         User->>User: Clic sur bouton
 *         User->>User: Redirection vers accueil
 *     else Statut != 'refused'
 *         BRM->>LH: debug("Button replacement skipped")
 *         BRM-->>GF: HTML inchangé
 *         GF-->>User: Affichage bouton "Suivant" normal
 *     end
 * ```
 * 
 * @end_diagramme_architecture
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ButtonReplacementManager
 * 
 * Gère le remplacement conditionnel du bouton "Suivant" par "Retour à l'accueil"
 * pour les utilisateurs avec statut 'refused' sur la page 3 des formulaires Gravity Forms
 */
class ButtonReplacementManager {

	/**
	 * Remplace le bouton "Envoyer" par "Retour à l'accueil" pour les utilisateurs 'refused'
	 * 
	 * Hook: gform_submit_button (hook spécifique Gravity Forms pour le bouton de soumission)
	 * 
	 * @param string $button_html  HTML du bouton de soumission
	 * @param array  $form         Formulaire Gravity Forms complet
	 * @return string HTML modifié avec bouton "Retour à l'accueil" si statut 'refused', sinon bouton inchangé
	 */
	public static function replace_submit_button_for_refused( string $button_html, array $form ): string {
		$form_id = (int) $form['id'];

		LoggingHelper::debug( '[ButtonReplacement] Hook gform_submit_button déclenché', array(
			'form_id'         => $form_id,
			'button_preview'  => substr( $button_html, 0, 100 ),
			'current_page'    => \GFFormDisplay::get_current_page( $form_id ),
			'total_pages'     => \GFFormDisplay::get_max_page_number( $form ),
		) );

		// Récupérer le statut du test depuis la session
		$test_status = self::get_test_status( $form_id );
		
		LoggingHelper::debug( '[ButtonReplacement] Statut récupéré depuis session', array(
			'form_id'     => $form_id,
			'test_status' => $test_status,
		) );

		// Si pas de statut ou statut différent de 'refused', retourner le bouton inchangé
		if ( $test_status !== 'refused' ) {
			LoggingHelper::debug( '[ButtonReplacement] Remplacement ignoré - statut non-refused', array(
				'form_id'     => $form_id,
				'test_status' => $test_status,
				'action'      => 'skipped',
			) );
			return $button_html;
		}

		// Créer le nouveau bouton "Retour à l'accueil"
		$new_button = self::create_home_redirect_button();

		LoggingHelper::info( '[ButtonReplacement] Bouton remplacé avec succès', array(
			'form_id'      => $form_id,
			'test_status'  => $test_status,
			'action'       => 'replaced',
			'new_button'   => substr( $new_button, 0, 100 ),
		) );

		return $new_button;
	}

	/**
	 * Crée le nouveau bouton "Retour à l'accueil" avec redirection JavaScript
	 * 
	 * Simplifié : Crée directement le HTML du bouton sans parser l'ancien bouton
	 * 
	 * @return string HTML du bouton personnalisé
	 */
	private static function create_home_redirect_button(): string {
		// Récupérer le texte du bouton via le hook personnalisé
		$button_text = \apply_filters( 
			'wcqf_button_replacement_filter', 
			\__( 'Retour à l\'accueil', Constants::TEXT_DOMAIN )
		);

		// Récupérer l'URL de redirection via le hook personnalisé
		$homepage_url = \apply_filters( 
			'wcqf_homepage_url_filter', 
			\home_url()
		);

		// Échapper les valeurs pour la sécurité
		$button_text_escaped = \esc_html( $button_text );
		$homepage_url_escaped = \esc_attr( $homepage_url );

		LoggingHelper::debug( '[ButtonReplacement] Création du nouveau bouton', array(
			'button_text' => $button_text_escaped,
			'redirect_url' => $homepage_url_escaped,
		) );

		// Créer le bouton avec redirection JavaScript
		return sprintf(
			'<button type="button" class="gform_button button" onclick="window.location.href=\'%s\'; return false;">%s</button>',
			$homepage_url_escaped,
			$button_text_escaped
		);
	}

	/**
	 * Récupère le statut du test depuis la session
	 * 
	 * @param int $form_id ID du formulaire
	 * @return string|null Statut du test ('refused', 'reinforced', 'admitted') ou null
	 */
	private static function get_test_status( int $form_id ): ?string {
		$session_key = 'test_result_' . $form_id;
		
		LoggingHelper::debug( '[ButtonReplacement] Tentative de récupération du statut', array(
			'form_id'     => $form_id,
			'session_key' => $session_key,
		) );
		
		$test_result = SessionManager::get( $session_key );

		LoggingHelper::debug( '[ButtonReplacement] Données session récupérées', array(
			'form_id'     => $form_id,
			'session_key' => $session_key,
			'result_type' => gettype( $test_result ),
			'result_data' => $test_result,
		) );

		if ( ! is_array( $test_result ) || ! isset( $test_result['path'] ) ) {
			LoggingHelper::warning( '[ButtonReplacement] Données session invalides ou absentes', array(
				'form_id'     => $form_id,
				'session_key' => $session_key,
				'is_array'    => is_array( $test_result ),
				'has_path'    => isset( $test_result['path'] ) ? 'yes' : 'no',
			) );
			return null;
		}

		// Sanitizer le statut récupéré
		$status = \sanitize_text_field( $test_result['path'] );
		
		// Valider que le statut est dans la liste des valeurs autorisées
		$valid_statuses = array( 'refused', 'reinforced', 'admitted' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			LoggingHelper::warning( '[ButtonReplacement] Statut invalide détecté', array(
				'form_id'        => $form_id,
				'invalid_status' => $status,
				'valid_statuses' => $valid_statuses,
			) );
			return null;
		}

		LoggingHelper::debug( '[ButtonReplacement] Statut valide récupéré', array(
			'form_id' => $form_id,
			'status'  => $status,
		) );

		return $status;
	}


	/**
	 * Initialise les hooks WordPress
	 * 
	 * @return void
	 */
	public static function init_hooks(): void {
		// Vérifier si le module est activé
		if ( ! self::is_module_enabled() ) {
			LoggingHelper::info( '[ButtonReplacement] Module désactivé via settings' );
			return;
		}

		// Enregistrer le hook gform_submit_button (hook spécifique pour le bouton "Envoyer")
		// Ce hook est appelé sur la dernière page du formulaire pour remplacer le bouton de soumission
		\add_filter( 'gform_submit_button', array( __CLASS__, 'replace_submit_button_for_refused' ), 10, 2 );

		LoggingHelper::info( '[ButtonReplacement] Hook gform_submit_button enregistré avec priorité 10', array(
			'hook'     => 'gform_submit_button',
			'callback' => 'replace_submit_button_for_refused',
			'priority' => 10,
		) );
	}

	/**
	 * Vérifie si le module ButtonReplacement est activé
	 * 
	 * @return bool True si le module est activé, false sinon
	 */
	private static function is_module_enabled(): bool {
		/**
		 * Filtre pour désactiver le module ButtonReplacement
		 *
		 * @since 1.1.0
		 * @param bool $enabled True pour activer, false pour désactiver
		 */
		$enabled = \apply_filters( 'wcqf_enable_button_replacement_module', true );

		// Optionnel : Permettre désactivation via option admin
		$settings = \get_option( 'wcqf_settings', array() );
		if ( isset( $settings['enable_button_replacement_module'] ) ) {
			$enabled = (bool) $settings['enable_button_replacement_module'];
		}

		return $enabled;
	}
}
