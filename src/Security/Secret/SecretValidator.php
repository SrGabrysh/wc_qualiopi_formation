<?php
/**
 * Secret Validator
 * 
 * RESPONSABILITÉ UNIQUE : Validation des secrets et clés API
 * 
 * @package WcQualiopiFormation\Security\Secret
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security\Secret;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe SecretValidator
 * 
 * Principe SRP : Validation de secrets UNIQUEMENT
 */
class SecretValidator {

	/**
	 * Validate required secrets
	 * 
	 * @param array $required_secrets Array of required secret keys
	 * @return array Array of missing secrets
	 */
	public static function validate_required( array $required_secrets ): array {
		$missing = array();

		foreach ( $required_secrets as $key ) {
			// Use SecretManager::has() to check existence
			if ( ! \WcQualiopiFormation\Security\SecretManager::has( $key, true ) ) {
				$missing[] = $key;
			}
		}

		return $missing;
	}

	/**
	 * Validate key format
	 * 
	 * @param string $key  Key to validate
	 * @param string $type Key type ('hmac', 'encryption', 'api')
	 * @return bool True if valid
	 */
	public static function validate_format( string $key, string $type = 'hmac' ): bool {
		if ( empty( $key ) ) {
			return false;
		}

		switch ( $type ) {
			case 'hmac':
			case 'encryption':
				// HMAC/encryption keys should be at least 32 characters (256 bits)
				return strlen( $key ) >= 32;

			case 'api':
				// API keys should be at least 16 characters
				return strlen( $key ) >= 16;

			default:
				return false;
		}
	}

	/**
	 * Get sources status for a secret
	 * 
	 * @param string $key Secret key
	 * @return array Status of each source
	 */
	public static function get_sources_status( string $key ): array {
		return array(
			'env_var'  => self::exists_in_env( $key ),
			'constant' => self::exists_in_constant( $key ),
			'option'   => self::exists_in_option( $key ),
		);
	}

	/**
	 * Check if secret exists in environment variable
	 * 
	 * @param string $key Secret key
	 * @return bool True if exists
	 */
	private static function exists_in_env( string $key ): bool {
		$value = getenv( $key );
		return $value !== false && ! empty( $value );
	}

	/**
	 * Check if secret exists as constant
	 * 
	 * @param string $key Secret key
	 * @return bool True if exists
	 */
	private static function exists_in_constant( string $key ): bool {
		return defined( $key ) && ! empty( constant( $key ) );
	}

	/**
	 * Check if secret exists in WordPress options
	 * 
	 * @param string $key Secret key
	 * @return bool True if exists
	 */
	private static function exists_in_option( string $key ): bool {
		$option_name = self::key_to_option_name( $key );
		$value       = get_option( $option_name );
		return ! empty( $value );
	}

	/**
	 * Convert secret key to option name
	 * 
	 * @param string $key Secret key (e.g., 'WCQF_HMAC_KEY')
	 * @return string Option name (e.g., 'wcqf_hmac_key')
	 */
	private static function key_to_option_name( string $key ): string {
		return strtolower( $key );
	}

	/**
	 * Check if a key is critical
	 * 
	 * @param string $key Secret key
	 * @return bool True if critical
	 */
	public static function is_critical( string $key ): bool {
		$critical_secrets = array(
			'WCQF_HMAC_KEY',
			'WCQF_ENCRYPTION_KEY',
			'WCQF_SIREN_API_KEY',
			'WCQF_OPENAI_API_KEY',
		);

		return in_array( $key, $critical_secrets, true );
	}

	/**
	 * Get all critical secrets
	 * 
	 * @return array Array of critical secret keys
	 */
	public static function get_critical_secrets(): array {
		return array(
			'WCQF_HMAC_KEY',
			'WCQF_ENCRYPTION_KEY',
			'WCQF_SIREN_API_KEY',
			'WCQF_OPENAI_API_KEY',
		);
	}
}

