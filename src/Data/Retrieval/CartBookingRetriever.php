<?php
/**
 * Cart Booking Retriever
 * 
 * RESPONSABILITÉ UNIQUE : Récupération des données de réservation depuis le panier WooCommerce
 * 
 * @package WcQualiopiFormation\Data\Retrieval
 * @since 1.2.0
 */

namespace WcQualiopiFormation\Data\Retrieval;

use WcQualiopiFormation\Helpers\LoggingHelper;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe CartBookingRetriever
 * 
 * Principe SRP : Récupération données de réservation depuis panier WooCommerce UNIQUEMENT
 * 
 * @since 1.2.0
 */
class CartBookingRetriever {

	/**
	 * Get booking details from WooCommerce cart
	 * 
	 * Extracts booking product information directly from the WooCommerce cart session.
	 * This method is called during page transitions (3→4) to retrieve reservation data
	 * needed for Yousign contract generation.
	 * 
	 * @since 1.2.0
	 * @return array Array of booking details. Each item contains:
	 *               - product_id (int): Product ID
	 *               - product_name (string): Product name
	 *               - start_timestamp (int): Start timestamp
	 *               - end_timestamp (int): End timestamp
	 *               - start_paris (string): Formatted start date (DD-MM-YYYY)
	 *               - end_paris (string): Formatted end date (DD-MM-YYYY)
	 *               - resource_id (int): Resource ID (0 if none)
	 *               - resource_name (string): Resource name (empty if none)
	 *               - persons (array|int): Person data
	 */
	public static function get_booking_details(): array {
		// Verify WooCommerce availability
		if ( ! self::is_woocommerce_available() ) {
			return array();
		}

		// Get cart items
		$cart_items = \WC()->cart->get_cart();

		if ( empty( $cart_items ) ) {
			LoggingHelper::debug(
				'[CartBookingRetriever] Cart is empty',
				array( 'cart_count' => 0 )
			);
			return array();
		}

		LoggingHelper::debug(
			'[CartBookingRetriever] Processing cart items',
			array(
				'cart_count' => count( $cart_items ),
				'cart_keys'  => array_keys( $cart_items ),
			)
		);

		return self::extract_booking_details( $cart_items );
	}

	/**
	 * Extract booking details from cart items
	 * 
	 * @param array $cart_items Cart items from WooCommerce.
	 * @return array Array of booking details.
	 */
	private static function extract_booking_details( array $cart_items ): array {
		$booking_details = array();

		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			$booking_data = self::process_cart_item( $cart_item_key, $cart_item );

			if ( $booking_data ) {
				$booking_details[] = $booking_data;
			}
		}

		self::log_extraction_complete( count( $cart_items ), count( $booking_details ) );

		/**
		 * Filter booking data retrieved from cart
		 * 
		 * @since 1.2.0
		 * @param array $booking_details Array of booking details
		 * @param array $cart_items      Raw cart items from WooCommerce
		 */
		return \apply_filters( 'wcqf_get_booking_data_from_cart', $booking_details, $cart_items );
	}

	/**
	 * Process a single cart item
	 * 
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item     Cart item data.
	 * @return array|null Booking data or null if not a booking item.
	 */
	private static function process_cart_item( string $cart_item_key, array $cart_item ): ?array {
		self::log_cart_item_analysis( $cart_item_key, $cart_item );

		// Check if this cart item has booking data
		if ( ! isset( $cart_item['booking'] ) || ! is_array( $cart_item['booking'] ) ) {
			LoggingHelper::debug(
				'[CartBookingRetriever] Cart item has no booking data, skipping',
				array( 'product_id' => $cart_item['product_id'] ?? 'N/A' )
			);
			return null;
		}

		return self::build_booking_data( $cart_item );
	}

	/**
	 * Build booking data from cart item
	 * 
	 * @param array $cart_item Cart item data.
	 * @return array Booking data.
	 */
	private static function build_booking_data( array $cart_item ): array {
		$booking    = $cart_item['booking'];
		$product_id = \absint( $cart_item['product_id'] ?? 0 );

		self::log_booking_structure( $product_id, $booking );

		// Extract product info
		$product      = \wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : '';

		// Extract timestamps
		$start_timestamp = TimestampExtractor::extract( $booking, 'start' );
		$end_timestamp   = TimestampExtractor::extract( $booking, 'end' );

		self::log_timestamp_extraction( $product_id, $start_timestamp, $end_timestamp, $booking );

		// Format dates
		$start_paris = $start_timestamp ? \wp_date( 'd-m-Y', $start_timestamp ) : '';
		$end_paris   = $end_timestamp ? \wp_date( 'd-m-Y', $end_timestamp ) : '';

		// Extract resource and persons
		$resource_id   = \absint( $booking['_resource_id'] ?? $booking['resource_id'] ?? 0 );
		$resource_name = self::get_resource_name( $resource_id );
		$persons       = $booking['_persons'] ?? $booking['persons'] ?? array();

		$booking_data = array(
			'product_id'       => $product_id,
			'product_name'     => $product_name,
			'start_timestamp'  => $start_timestamp,
			'end_timestamp'    => $end_timestamp,
			'start_paris'      => $start_paris,
			'end_paris'        => $end_paris,
			'resource_id'      => $resource_id,
			'resource_name'    => $resource_name,
			'persons'          => $persons,
		);

		self::log_booking_processed( $booking_data );

		return $booking_data;
	}

	/**
	 * Get resource name from resource ID
	 * 
	 * @param int $resource_id Resource ID.
	 * @return string Resource name or empty string.
	 */
	private static function get_resource_name( int $resource_id ): string {
		if ( $resource_id <= 0 ) {
			return '';
		}

		$resource_name = \get_the_title( $resource_id );
		return $resource_name ? $resource_name : '';
	}

	/**
	 * Check if WooCommerce is available
	 * 
	 * @return bool True if WooCommerce and cart are available.
	 */
	private static function is_woocommerce_available(): bool {
		if ( ! \function_exists( 'WC' ) || ! \WC()->cart ) {
			LoggingHelper::error(
				'[CartBookingRetriever] WooCommerce or cart not available',
				array(
					'wc_exists'   => \function_exists( 'WC' ),
					'cart_exists' => \function_exists( 'WC' ) && \WC()->cart ? true : false,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Log cart item analysis
	 * 
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item     Cart item data.
	 */
	private static function log_cart_item_analysis( string $cart_item_key, array $cart_item ): void {
		LoggingHelper::debug(
			'[CartBookingRetriever] Analyzing cart item',
			array(
				'cart_item_key'  => $cart_item_key,
				'product_id'     => $cart_item['product_id'] ?? 'N/A',
				'has_booking'    => isset( $cart_item['booking'] ),
				'cart_item_keys' => array_keys( $cart_item ),
			)
		);
	}

	/**
	 * Log booking structure discovered
	 * 
	 * @param int   $product_id Product ID.
	 * @param array $booking    Booking data.
	 */
	private static function log_booking_structure( int $product_id, array $booking ): void {
		LoggingHelper::debug(
			'[CartBookingRetriever] Booking data structure discovered',
			array(
				'product_id'   => $product_id,
				'booking_keys' => array_keys( $booking ),
				'booking_data' => $booking,
			)
		);
	}

	/**
	 * Log timestamp extraction
	 * 
	 * @param int   $product_id      Product ID.
	 * @param int   $start_timestamp Start timestamp.
	 * @param int   $end_timestamp   End timestamp.
	 * @param array $booking         Booking data.
	 */
	private static function log_timestamp_extraction( int $product_id, int $start_timestamp, int $end_timestamp, array $booking ): void {
		LoggingHelper::debug(
			'[CartBookingRetriever] Timestamps extracted',
			array(
				'product_id'      => $product_id,
				'start_timestamp' => $start_timestamp,
				'end_timestamp'   => $end_timestamp,
				'start_raw'       => $booking['_start_date'] ?? $booking['start'] ?? 'N/A',
				'end_raw'         => $booking['_end_date'] ?? $booking['end'] ?? 'N/A',
			)
		);
	}

	/**
	 * Log booking processed
	 * 
	 * @param array $booking_data Booking data.
	 */
	private static function log_booking_processed( array $booking_data ): void {
		LoggingHelper::info(
			'[CartBookingRetriever] Booking product processed',
			array(
				'product_id'   => $booking_data['product_id'],
				'product_name' => $booking_data['product_name'],
				'start_date'   => $booking_data['start_paris'],
				'end_date'     => $booking_data['end_paris'],
				'has_resource' => $booking_data['resource_id'] > 0,
			)
		);
	}

	/**
	 * Log extraction complete
	 * 
	 * @param int $total_items   Total cart items.
	 * @param int $booking_count Booking items found.
	 */
	private static function log_extraction_complete( int $total_items, int $booking_count ): void {
		LoggingHelper::debug(
			'[CartBookingRetriever] Cart analysis complete',
			array(
				'total_items'         => $total_items,
				'booking_items_found' => $booking_count,
			)
		);
	}

	/**
	 * Get cart totals (HT, TTC, and TVA)
	 * 
	 * Retrieves the total amounts from the WooCommerce cart.
	 * 
	 * @since 1.2.0
	 * @return array Array with 'total_ht', 'total_ttc', and 'tva' as formatted strings.
	 */
	public static function get_cart_totals(): array {
		// Verify WooCommerce availability
		if ( ! self::is_woocommerce_available() ) {
			self::log_woocommerce_unavailable();
			return array(
				'total_ht'  => '0,00 €',
				'total_ttc' => '0,00 €',
				'tva'       => '0,00 €',
			);
		}

		$cart = \WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			LoggingHelper::warning( '[CartBookingRetriever] Cart is empty for totals' );
			return array(
				'total_ht'  => '0,00 €',
				'total_ttc' => '0,00 €',
				'tva'       => '0,00 €',
			);
		}

		// Récupérer les totaux
		$subtotal_excl_tax = $cart->get_subtotal(); // Total HT
		$total_incl_tax    = $cart->get_total( 'raw' ); // Total TTC (raw = float)
		$tva               = $total_incl_tax - $subtotal_excl_tax; // TVA = TTC - HT

		// Formater en français (virgule décimale, espace milliers, symbole €)
		$total_ht_formatted  = self::format_price( $subtotal_excl_tax );
		$total_ttc_formatted = self::format_price( $total_incl_tax );
		$tva_formatted       = self::format_price( $tva );

		LoggingHelper::debug(
			'[CartBookingRetriever] Cart totals retrieved',
			array(
				'total_ht'  => $total_ht_formatted,
				'total_ttc' => $total_ttc_formatted,
				'tva'       => $tva_formatted,
			)
		);

		return array(
			'total_ht'  => $total_ht_formatted,
			'total_ttc' => $total_ttc_formatted,
			'tva'       => $tva_formatted,
		);
	}

	/**
	 * Format a price value to French format
	 * 
	 * Example: 1234.56 → "1 234,56 €"
	 * 
	 * @param float $price Price value.
	 * @return string Formatted price.
	 */
	private static function format_price( float $price ): string {
		// Format : 1 234,56 €
		return \number_format( $price, 2, ',', ' ' ) . ' €';
	}
}

