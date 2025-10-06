<?php
/**
 * Secret Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration de la récupération des secrets
 * 
 * Gestionnaire de secrets et clés API selon la politique de sécurité :
 * - Priorité 1 : Variables d'environnement
 * - Priorité 2 : Constantes wp-config.php
 * - Priorité 3 : Options WordPress (si autorisé)
 *
 * @package WcQualiopiFormation\Security
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\Secret\SecretValidator;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class SecretManager (Manager)
 * 
 * Orchestrates secure secret retrieval
 */
class SecretManager {

	/**
	 * Nom des secrets connus
	 */
	const SECRET_HMAC_KEY       = 'WCQF_HMAC_KEY';
	const SECRET_ENCRYPTION_KEY = 'WCQF_ENCRYPTION_KEY';
	const SECRET_SIREN_API_KEY  = 'WCQF_SIREN_API_KEY';
	const SECRET_OPENAI_API_KEY = 'WCQF_OPENAI_API_KEY';

	/**
	 * Cache des secrets récupérés
	 *
	 * @var array<string, mixed>
	 */
	private static array $cache = array();

	/**
	 * Récupère un secret selon l'ordre de priorité
	 * 
	 * @param string $key          Nom du secret
	 * @param mixed  $default      Valeur par défaut si rien n'est trouvé
	 * @param bool   $allow_option Si true, autorise les options WordPress
	 * @param bool   $required     Si true, lève une exception si introuvable
	 * @return mixed La valeur du secret
	 * @throws Exception Si le secret est requis mais introuvable
	 */
	public static function get( string $key, $default = null, bool $allow_option = false, bool $required = false ) {
		// Vérifier le cache
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		// 1. Variable d'environnement (priorité maximale)
		$value = self::get_from_env( $key );
		if ( null !== $value ) {
			self::$cache[ $key ] = $value;
			return $value;
		}

		// 2. Constante PHP (définie dans wp-config.php)
		$value = self::get_from_constant( $key );
		if ( null !== $value ) {
			self::$cache[ $key ] = $value;
			return $value;
		}

		// 3. Option WordPress (si autorisé)
		if ( $allow_option ) {
			$value = self::get_from_option( $key );
			if ( null !== $value ) {
				// Warning si secret critique stocké en option
				if ( SecretValidator::is_critical( $key ) ) {
					error_log( sprintf(
						'WCQF Security Warning: Critical secret %s retrieved from WordPress option. Use environment variable or wp-config constant instead.',
						$key
					) );
				}
				self::$cache[ $key ] = $value;
				return $value;
			}
		}

		// Si requis et introuvable, lever une exception
		if ( $required ) {
			throw new Exception( sprintf(
				'Required secret "%s" not found. Please define it as environment variable, wp-config constant, or WordPress option.',
				$key
			) );
		}

		// Sinon, retourner la valeur par défaut
		return $default;
	}

	/**
	 * Check if secret exists
	 * 
	 * @param string $key          Secret key
	 * @param bool   $allow_option Allow WordPress options
	 * @return bool True if exists
	 */
	public static function has( string $key, bool $allow_option = false ): bool {
		$value = self::get( $key, null, $allow_option, false );
		return null !== $value;
	}

	/**
	 * Validate required secrets - DELEGATED
	 * 
	 * @param array $required_secrets Array of required secret keys
	 * @return array Array of missing secrets
	 */
	public static function validate_required_secrets( array $required_secrets ): array {
		return SecretValidator::validate_required( $required_secrets );
	}

	/**
	 * Validate key format - DELEGATED
	 * 
	 * @param string $key  Key to validate
	 * @param string $type Key type ('hmac', 'encryption', 'api')
	 * @return bool True if valid
	 */
	public static function validate_key_format( string $key, string $type = 'hmac' ): bool {
		return SecretValidator::validate_format( $key, $type );
	}

	/**
	 * Get sources status - DELEGATED
	 * 
	 * @param string $key Secret key
	 * @return array Status of each source
	 */
	public static function get_sources_status( string $key ): array {
		return SecretValidator::get_sources_status( $key );
	}

	/**
	 * Generate HMAC key
	 * 
	 * @return string Generated key (64 hex chars = 256 bits)
	 */
	public static function generate_hmac_key(): string {
		return bin2hex( random_bytes( 32 ) ); // 256 bits
	}

	/**
	 * Generate encryption key
	 * 
	 * @return string Generated key (64 hex chars = 256 bits)
	 */
	public static function generate_encryption_key(): string {
		return bin2hex( random_bytes( 32 ) ); // 256 bits
	}

	/**
	 * Mask secret for display
	 * 
	 * @param string $secret        Secret to mask
	 * @param int    $visible_start Number of visible chars at start
	 * @param int    $visible_end   Number of visible chars at end
	 * @return string Masked secret
	 */
	public static function mask_secret( string $secret, int $visible_start = 10, int $visible_end = 4 ): string {
		$length = strlen( $secret );

		if ( $length <= ( $visible_start + $visible_end ) ) {
			return str_repeat( '*', $length );
		}

		$start = substr( $secret, 0, $visible_start );
		$end   = substr( $secret, -$visible_end );
		$masked_length = $length - $visible_start - $visible_end;

		return $start . str_repeat( '*', $masked_length ) . $end;
	}

	/**
	 * Set secret for testing purposes
	 * 
	 * @param string $key   Secret key
	 * @param mixed  $value Secret value
	 */
	public static function set_for_testing( string $key, $value ): void {
		self::$cache[ $key ] = $value;
	}

	/**
	 * Clear cache
	 * 
	 * @param string|null $key Specific key to clear, or null for all
	 */
	public static function clear_cache( ?string $key = null ): void {
		if ( null === $key ) {
			self::$cache = array();
		} else {
			unset( self::$cache[ $key ] );
		}
	}

	/**
	 * Get secret from environment variable
	 * 
	 * @param string $key Secret key
	 * @return string|null Value or null
	 */
	private static function get_from_env( string $key ): ?string {
		$value = getenv( $key );
		return ( false !== $value && ! empty( $value ) ) ? $value : null;
	}

	/**
	 * Get secret from constant
	 * 
	 * @param string $key Secret key
	 * @return string|null Value or null
	 */
	private static function get_from_constant( string $key ): ?string {
		if ( defined( $key ) ) {
			$value = constant( $key );
			return ! empty( $value ) ? $value : null;
		}
		return null;
	}

	/**
	 * Get secret from WordPress option
	 * 
	 * @param string $key Secret key
	 * @return string|null Value or null
	 */
	private static function get_from_option( string $key ): ?string {
		$option_name = strtolower( $key );
		$value       = get_option( $option_name );
		return ! empty( $value ) ? $value : null;
	}
}
