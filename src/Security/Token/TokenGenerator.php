<?php
/**
 * Token Generator
 * 
 * RESPONSABILITÉ UNIQUE : Génération et encodage de tokens HMAC
 * 
 * @package WcQualiopiFormation\Security\Token
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Security\Token;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe TokenGenerator
 * 
 * Principe SRP : Génération de tokens UNIQUEMENT
 */
class TokenGenerator {

	/**
	 * Generate HMAC token
	 * 
	 * @param int    $user_id    WordPress user ID
	 * @param int    $product_id WooCommerce product ID
	 * @param string $secret     HMAC secret key
	 * @param int    $timestamp  Unix timestamp (optional, defaults to current time)
	 * @param string $nonce      Random nonce (optional, auto-generated)
	 * @return string Complete HMAC token
	 */
	public static function generate( int $user_id, int $product_id, string $secret, int $timestamp = null, string $nonce = '' ): string {
		// Use current time if not provided
		if ( null === $timestamp ) {
			$timestamp = time();
		}

		// Generate nonce if not provided
		if ( empty( $nonce ) ) {
			$nonce = wp_generate_password( 8, false );
		}

		// Build payload: user_id:product_id:timestamp:nonce
		$payload = sprintf( '%d:%d:%d:%s', $user_id, $product_id, $timestamp, $nonce );

		// Encode payload in base64url (URL-safe)
		$encoded_payload = self::base64url_encode( $payload );

		// Generate HMAC signature
		$signature = hash_hmac( 'sha256', $encoded_payload, $secret );

		// Return token: payload.signature
		return $encoded_payload . '.' . $signature;
	}

	/**
	 * Parse token into components
	 * 
	 * @param string $token Token to parse
	 * @return array|false Array with 'payload' and 'signature', or false on error
	 */
	public static function parse( string $token ) {
		$parts = explode( '.', $token );

		if ( count( $parts ) !== 2 ) {
			return false;
		}

		return array(
			'payload'   => $parts[0],
			'signature' => $parts[1],
		);
	}

	/**
	 * Decode payload from token
	 * 
	 * @param string $encoded_payload Encoded payload
	 * @return array|false Array with user_id, product_id, timestamp, nonce, or false on error
	 */
	public static function decode_payload( string $encoded_payload ) {
		$decoded = self::base64url_decode( $encoded_payload );

		if ( false === $decoded ) {
			return false;
		}

		$parts = explode( ':', $decoded );

		if ( count( $parts ) !== 4 ) {
			return false;
		}

		return array(
			'user_id'    => (int) $parts[0],
			'product_id' => (int) $parts[1],
			'timestamp'  => (int) $parts[2],
			'nonce'      => $parts[3],
		);
	}

	/**
	 * Calculate HMAC signature
	 * 
	 * @param string $payload Payload to sign
	 * @param string $secret  HMAC secret key
	 * @return string HMAC signature
	 */
	public static function calculate_signature( string $payload, string $secret ): string {
		return hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Verify signature
	 * 
	 * @param string $payload   Payload
	 * @param string $signature Signature to verify
	 * @param string $secret    HMAC secret key
	 * @return bool True if signature is valid
	 */
	public static function verify_signature( string $payload, string $signature, string $secret ): bool {
		$expected_signature = self::calculate_signature( $payload, $secret );
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Encode string to base64url (URL-safe base64)
	 * 
	 * @param string $data Data to encode
	 * @return string Base64url encoded string
	 */
	public static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode base64url string
	 * 
	 * @param string $data Base64url encoded string
	 * @return string|false Decoded string or false on failure
	 */
	public static function base64url_decode( string $data ) {
		$remainder = strlen( $data ) % 4;

		if ( $remainder ) {
			$padlen = 4 - $remainder;
			$data  .= str_repeat( '=', $padlen );
		}

		return base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Check if token is expired
	 * 
	 * @param int $timestamp Token timestamp
	 * @param int $max_age   Maximum age in seconds
	 * @return bool True if expired
	 */
	public static function is_expired( int $timestamp, int $max_age ): bool {
		return ( time() - $timestamp ) > $max_age;
	}

	/**
	 * Get token age in seconds
	 * 
	 * @param int $timestamp Token timestamp
	 * @return int Age in seconds
	 */
	public static function get_age( int $timestamp ): int {
		return time() - $timestamp;
	}
}

