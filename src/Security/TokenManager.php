<?php
/**
 * Token Manager
 * 
 * RESPONSABILITÉ UNIQUE : Orchestration des tokens + gestion des clés
 * 
 * Manages HMAC token generation and validation for user tracking
 * Delegates generation to TokenGenerator, manages secrets via SecretManager
 *
 * @package WcQualiopiFormation\Security
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\Token\TokenGenerator;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TokenManager (Manager)
 * 
 * Orchestrates token operations + key management
 */
class TokenManager {

	/**
	 * Secret names (matching SecretManager constants)
	 */
	const SECRET_HMAC_KEY = 'WCQF_HMAC_KEY';

	/**
	 * Option name for key version
	 */
	const KEY_VERSION_OPTION = 'wcqf_hmac_key_version';

	/**
	 * Option name for previous secret (rotation)
	 */
	const PREVIOUS_SECRET_OPTION = 'wcqf_hmac_secret_prev';

	/**
	 * Static cache to avoid multiple get_option() calls
	 */
	private static ?int $key_version_cache = null;
	private static ?string $previous_secret_cache = null;

	/**
	 * Generate HMAC token
	 * 
	 * Delegates to TokenGenerator with secret from SecretManager
	 * 
	 * @param int    $user_id    WordPress user ID
	 * @param int    $product_id WooCommerce product ID
	 * @param int    $timestamp  Unix timestamp (optional, defaults to current time)
	 * @param string $nonce      Random nonce (optional, auto-generated)
	 * @return string Complete HMAC token
	 */
	public static function generate( int $user_id, int $product_id, int $timestamp = null, string $nonce = '' ): string {
		$secret = self::get_secret();
		return TokenGenerator::generate( $user_id, $product_id, $secret, $timestamp, $nonce );
	}

	/**
	 * Validate HMAC token
	 * 
	 * Validates signature and checks expiration/user/product match
	 * Supports key rotation (tries current and previous secret)
	 * 
	 * @param string $token              Token to validate
	 * @param int    $expected_user_id   Expected user ID
	 * @param int    $expected_product_id Expected product ID
	 * @param int    $max_age            Maximum age in seconds (defaults to Constants::TOKEN_TTL_HOURS)
	 * @return array|false Array with decoded payload if valid, false otherwise
	 */
	public static function validate( string $token, int $expected_user_id, int $expected_product_id, int $max_age = null ) {
		// Use default TTL if not provided
		if ( null === $max_age ) {
			$max_age = Constants::TOKEN_TTL_HOURS * 3600;
		}

		// Parse token
		$parts = TokenGenerator::parse( $token );
		if ( false === $parts ) {
			return false;
		}

		$payload   = $parts['payload'];
		$signature = $parts['signature'];

		// Decode payload
		$decoded = TokenGenerator::decode_payload( $payload );
		if ( false === $decoded ) {
			return false;
		}

		// Verify signature (try current secret first)
		$secret       = self::get_secret();
		$is_valid_sig = TokenGenerator::verify_signature( $payload, $signature, $secret );

		// If current secret fails, try previous secret (key rotation support)
		if ( ! $is_valid_sig ) {
			$previous_secret = self::get_previous_secret();
			if ( $previous_secret ) {
				$is_valid_sig = TokenGenerator::verify_signature( $payload, $signature, $previous_secret );
			}
		}

		if ( ! $is_valid_sig ) {
			return false;
		}

		// Check expiration
		if ( TokenGenerator::is_expired( $decoded['timestamp'], $max_age ) ) {
			return false;
		}

		// Verify user ID matches
		if ( $decoded['user_id'] !== $expected_user_id ) {
			return false;
		}

		// Verify product ID matches
		if ( $decoded['product_id'] !== $expected_product_id ) {
			return false;
		}

		// Return decoded payload
		return $decoded;
	}

	/**
	 * Check if token is expired
	 * 
	 * @param string $token Token to check
	 * @return bool True if expired
	 */
	public static function is_expired( string $token ): bool {
		$parts = TokenGenerator::parse( $token );
		if ( false === $parts ) {
			return true;
		}

		$decoded = TokenGenerator::decode_payload( $parts['payload'] );
		if ( false === $decoded ) {
			return true;
		}

		$max_age = Constants::TOKEN_TTL_HOURS * 3600;
		return TokenGenerator::is_expired( $decoded['timestamp'], $max_age );
	}

	/**
	 * Rotate HMAC key
	 * 
	 * Generates new secret, saves previous one for TTL duration
	 * 
	 * @return bool Success status
	 */
	public static function rotate_key(): bool {
		$current_secret = self::get_secret();

		// Generate new secret
		$new_secret = SecretManager::generate_hmac_key();

		// Save current secret as previous
		update_option( self::PREVIOUS_SECRET_OPTION, $current_secret, false );

		// Update secret in SecretManager (via option as fallback)
		update_option( strtolower( self::SECRET_HMAC_KEY ), $new_secret, false );

		// Increment key version
		$version = self::get_key_version();
		update_option( self::KEY_VERSION_OPTION, $version + 1, false );

		// Clear cache
		self::clear_cache();
		SecretManager::clear_cache();

		return true;
	}

	/**
	 * Get key version
	 * 
	 * @return int Key version number
	 */
	public static function get_key_version(): int {
		if ( null === self::$key_version_cache ) {
			self::$key_version_cache = (int) get_option( self::KEY_VERSION_OPTION, 1 );
		}
		return self::$key_version_cache;
	}

	/**
	 * Clear internal cache
	 */
	public static function clear_cache(): void {
		self::$key_version_cache      = null;
		self::$previous_secret_cache = null;
	}

	/**
	 * Get HMAC secret
	 * 
	 * @return string Secret key
	 */
	private static function get_secret(): string {
		$secret = SecretManager::get( self::SECRET_HMAC_KEY, null, true );
		
		// If no secret exists, generate one (auto-configuration)
		if ( null === $secret ) {
			$secret = SecretManager::generate_hmac_key();
			update_option( strtolower( self::SECRET_HMAC_KEY ), $secret, false );
			error_log( 'WCQF Security Warning: HMAC key auto-generated. Define WCQF_HMAC_KEY in wp-config.php for better security.' );
		}
		
		return $secret;
	}

	/**
	 * Get previous HMAC secret (for key rotation)
	 * 
	 * @return string|null Previous secret or null
	 */
	private static function get_previous_secret(): ?string {
		if ( null === self::$previous_secret_cache ) {
			self::$previous_secret_cache = get_option( self::PREVIOUS_SECRET_OPTION, null );
		}
		return self::$previous_secret_cache;
	}
}
