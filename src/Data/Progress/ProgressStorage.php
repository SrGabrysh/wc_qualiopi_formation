<?php
/**
 * Progress Storage
 * 
 * RESPONSABILITÉ UNIQUE : Interactions avec la table wp_wcqf_progress
 * 
 * @package WcQualiopiFormation\Data\Progress
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Data\Progress;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Security\TokenManager;
use WcQualiopiFormation\Helpers\SanitizationHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ProgressStorage
 * 
 * Principe SRP : Accès BDD progress UNIQUEMENT
 */
class ProgressStorage {

	/**
	 * Start new progress entry
	 * 
	 * Creates entry in wp_wcqf_progress table
	 * 
	 * @param int    $user_id      WordPress user ID
	 * @param int    $product_id   WooCommerce product ID
	 * @param string $cart_key     Cart item key (optional)
	 * @param array  $initial_data Initial collected data (optional)
	 * @return array|false Array with id and token, or false on failure
	 */
	public static function start( int $user_id, int $product_id, string $cart_key = '', array $initial_data = array() ) {
		// Security: Validate user permissions - only check if different user
		if ( $user_id !== get_current_user_id() && ! current_user_can( Constants::CAP_MANAGE_SETTINGS ) ) {
			return false;
		}
		global $wpdb;

		// Generate token
		$token = TokenManager::generate( $user_id, $product_id );

		// Get session ID if available
		$session_id = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$session_id = WC()->session->get_customer_id();
		}

		// Get user info
		$ip_address = self::get_client_ip();
		$user_agent = SanitizationHelper::sanitize_name( $_SERVER['HTTP_USER_AGENT'] ?? '' );

		// Prepare initial data - sanitize before encoding
		$sanitized_initial_data = self::sanitize_array_data( $initial_data );
		$collected_data = ! empty( $sanitized_initial_data ) ? wp_json_encode( $sanitized_initial_data ) : null;

		// Insert into database
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		$result = $wpdb->insert(
			$table_name,
			array(
				'token'          => $token,
				'user_id'        => $user_id,
				'session_id'     => $session_id,
				'product_id'     => $product_id,
				'cart_key'       => $cart_key,
				'current_step'   => Constants::STEP_CART,
				'last_activity'  => current_time( 'mysql' ),
				'started_at'     => current_time( 'mysql' ),
				'collected_data' => $collected_data,
				'ip_address'     => $ip_address,
				'user_agent'     => $user_agent,
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$progress_id = $wpdb->insert_id;

		/**
		 * Fires after progress started
		 * 
		 * @param int    $progress_id Progress ID
		 * @param string $token       Generated token
		 * @param int    $user_id     User ID
		 * @param int    $product_id  Product ID
		 */
		do_action( 'wcqf_progress_started', $progress_id, $token, $user_id, $product_id );

		return array(
			'id'    => $progress_id,
			'token' => $token,
		);
	}

	/**
	 * Update current step
	 * 
	 * @param string $token Token identifier
	 * @param string $step  New step (must be valid step from Constants)
	 * @return bool Success status
	 */
	public static function update_step( string $token, string $step ): bool {
		// Security: Validate token format
		$token = SanitizationHelper::sanitize_siret( $token );
		$step = SanitizationHelper::sanitize_name( $step );
		
		// Validate step
		if ( ! Constants::is_valid_step( $step ) ) {
			return false;
		}

		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		$result = $wpdb->update(
			$table_name,
			array(
				'current_step'  => $step,
				'last_activity' => current_time( 'mysql' ),
			),
			array( 'token' => $token ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		/**
		 * Fires after step updated
		 * 
		 * @param string $token Token
		 * @param string $step  New step
		 */
		do_action( 'wcqf_progress_step_updated', $token, $step );

		return true;
	}

	/**
	 * Add collected data
	 * 
	 * Merges new data with existing collected_data JSON
	 * 
	 * @param string $token      Token identifier
	 * @param string $data_key   Key for the data (e.g., 'personal', 'company', 'form')
	 * @param mixed  $data_value Value to store
	 * @return bool Success status
	 */
	public static function add_data( string $token, string $data_key, $data_value ): bool {
		// Security: Sanitize inputs
		$token = SanitizationHelper::sanitize_siret( $token );
		$data_key = sanitize_key( $data_key );
		$data_value = self::sanitize_data_value( $data_value );
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		// Get current data
		$current_data_json = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT collected_data FROM {$table_name} WHERE token = %s",
				$token
			)
		);

		// Decode existing data
		$current_data = array();
		if ( ! empty( $current_data_json ) ) {
			$decoded = json_decode( $current_data_json, true );
			if ( is_array( $decoded ) ) {
				$current_data = $decoded;
			}
		}

		// Merge new data
		$current_data[ $data_key ] = $data_value;

		// Encode back to JSON
		$updated_data_json = wp_json_encode( $current_data );

		// Update database
		$result = $wpdb->update(
			$table_name,
			array(
				'collected_data' => $updated_data_json,
				'last_activity'  => current_time( 'mysql' ),
			),
			array( 'token' => $token ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		/**
		 * Fires after data added
		 * 
		 * @param string $token      Token
		 * @param string $data_key   Data key
		 * @param mixed  $data_value Data value
		 */
		do_action( 'wcqf_progress_data_added', $token, $data_key, $data_value );

		return true;
	}

	/**
	 * Get progress by token
	 * 
	 * @param string $token Token identifier
	 * @return array|null Progress data or null if not found
	 */
	public static function get( string $token ): ?array {
		// Security: Sanitize token
		$token = SanitizationHelper::sanitize_siret( $token );
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		$progress = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE token = %s",
				$token
			),
			ARRAY_A
		);

		if ( ! $progress ) {
			return null;
		}

		// Decode JSON fields
		if ( ! empty( $progress['collected_data'] ) ) {
			$progress['collected_data'] = json_decode( $progress['collected_data'], true );
		}

		if ( ! empty( $progress['metadata'] ) ) {
			$progress['metadata'] = json_decode( $progress['metadata'], true );
		}

		return $progress;
	}

	/**
	 * Mark progress as completed
	 * 
	 * @param string $token    Token identifier
	 * @param int    $order_id Order ID (optional)
	 * @return bool Success status
	 */
	public static function complete( string $token, int $order_id = 0 ): bool {
		// Security: Sanitize inputs
		$token = SanitizationHelper::sanitize_siret( $token );
		$order_id = absint( $order_id );
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		$update_data = array(
			'current_step'  => Constants::STEP_COMPLETED,
			'is_completed'  => 1,
			'completed_at'  => current_time( 'mysql' ),
			'last_activity' => current_time( 'mysql' ),
		);

		if ( $order_id > 0 ) {
			$update_data['order_id'] = $order_id;
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'token' => $token ),
			array( '%s', '%d', '%s', '%s', '%d' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		/**
		 * Fires after progress completed
		 * 
		 * @param string $token    Token
		 * @param int    $order_id Order ID
		 */
		do_action( 'wcqf_progress_completed', $token, $order_id );

		return true;
	}

	/**
	 * Mark progress as abandoned
	 * 
	 * @param string $token Token identifier
	 * @return bool Success status
	 */
	public static function mark_abandoned( string $token ): bool {
		// Security: Sanitize token
		$token = SanitizationHelper::sanitize_siret( $token );
		global $wpdb;
		$table_name = Constants::get_table_name( Constants::TABLE_PROGRESS );

		$result = $wpdb->update(
			$table_name,
			array(
				'is_abandoned'  => 1,
				'last_activity' => current_time( 'mysql' ),
			),
			array( 'token' => $token ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		return false !== $result;
	}


	/**
	 * Get client IP address
	 * 
	 * @return string IP address
	 */
	private static function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx
			'HTTP_X_FORWARDED_FOR',  // Proxy
			'REMOTE_ADDR',           // Direct
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = SanitizationHelper::sanitize_name( wp_unslash( $_SERVER[ $key ] ) );

				// Handle multiple IPs (take first)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}

				// Validate IP
				if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
					return trim( $ip );
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Sanitize array data recursively
	 * 
	 * @param array $data Data to sanitize
	 * @return array Sanitized data
	 */
	private static function sanitize_array_data( array $data ): array {
		$sanitized = array();
		
		foreach ( $data as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			
			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = self::sanitize_array_data( $value );
			} else {
				$sanitized[ $sanitized_key ] = self::sanitize_data_value( $value );
			}
		}
		
		return $sanitized;
	}

	/**
	 * Sanitize data value based on type
	 * 
	 * @param mixed $value Value to sanitize
	 * @return mixed Sanitized value
	 */
	private static function sanitize_data_value( $value ) {
		if ( is_string( $value ) ) {
			return SanitizationHelper::sanitize_name( $value );
		} elseif ( is_numeric( $value ) ) {
			return is_float( $value ) ? floatval( $value ) : intval( $value );
		} elseif ( is_bool( $value ) ) {
			return (bool) $value;
		} elseif ( is_array( $value ) ) {
			return self::sanitize_array_data( $value );
		}
		
		return $value;
	}
}

