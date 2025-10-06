<?php
/**
 * Gestion centralisée des erreurs API SIREN
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\Siren;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;

/**
 * Classe de gestion des erreurs API SIREN
 */
class SirenErrorHandler {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Logue une erreur API avec contexte
	 *
	 * @param string    $context Contexte de l'erreur (etablissement, unite_legale).
	 * @param \WP_Error $error Erreur WordPress.
	 * @return void
	 */
	public function log_error( $context, $error ) {
		$this->logger->error( "[SirenErrorHandler] Erreur API {$context}", array(
			'context'       => $context,
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message(),
			'error_data'    => $error->get_error_data(),
		) );
	}

	/**
	 * Formate une erreur pour les logs
	 *
	 * @param \WP_Error $error Erreur WordPress.
	 * @return array Erreur formatée.
	 */
	public function format_error_for_log( $error ) {
		return array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'data'    => $error->get_error_data(),
		);
	}

	/**
	 * Formate un message d'erreur pour l'utilisateur
	 *
	 * @param \WP_Error $error Erreur WordPress.
	 * @return string Message formaté.
	 */
	public function format_user_message( $error ) {
		$code = $error->get_error_code();

		$messages = array(
			'invalid_siret'      => __( 'Le numéro SIRET est invalide.', Constants::TEXT_DOMAIN ),
			'not_found'          => __( 'Aucune entreprise trouvée avec ce SIRET.', Constants::TEXT_DOMAIN ),
			'server_error'       => __( 'Le service API SIREN est temporairement indisponible. Veuillez réessayer dans quelques instants.', Constants::TEXT_DOMAIN ),
			'missing_api_key'    => __( 'La clé API SIREN n\'est pas configurée.', Constants::TEXT_DOMAIN ),
			'json_decode_error'  => __( 'Erreur lors du décodage de la réponse API.', Constants::TEXT_DOMAIN ),
		);

		return $messages[ $code ] ?? $error->get_error_message();
	}

	/**
	 * Détermine si une erreur doit entraîner un retry
	 *
	 * @param \WP_Error $error Erreur WordPress.
	 * @return bool True si retry recommandé.
	 */
	public function should_retry( $error ) {
		$retry_codes = array( 'server_error', 'timeout', 'http_request_failed' );
		return in_array( $error->get_error_code(), $retry_codes, true );
	}
}

