<?php
/**
 * Plugin Name: WC Qualiopi Formation
 * Plugin URI: https://tb-web.fr/plugins/wc-qualiopi-formation
 * Description: Plugin unifié pour tunnel de formation Qualiopi avec pré-remplissage checkout et conformité complète
 * Version: 1.5.0
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * Author: TB-Web
 * Author URI: https://tb-web.fr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcqf
 * Domain Path: /languages
 *
 * WooCommerce requires at least: 7.0
 * WooCommerce tested up to: 8.5
 *
 * @package WcQualiopiFormation
 */

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants (SRP: Single Responsibility - Configuration)
define( 'WCQF_VERSION', '1.5.0' );
define( 'WCQF_PLUGIN_FILE', __FILE__ );
define( 'WCQF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCQF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCQF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements
define( 'WCQF_MIN_PHP_VERSION', '8.1' );
define( 'WCQF_MIN_WP_VERSION', '5.8' );
define( 'WCQF_MIN_WC_VERSION', '7.0' );

/**
 * Check minimum requirements before loading plugin
 * 
 * @return bool True if requirements met, false otherwise
 */
function wcqf_check_requirements() {
	$errors = array();

	// Check PHP version
	if ( version_compare( PHP_VERSION, WCQF_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'WC Qualiopi Formation requires PHP %1$s or higher. You are running %2$s.', 'wcqf' ),
			WCQF_MIN_PHP_VERSION,
			PHP_VERSION
		);
	}

	// Check WordPress version
	global $wp_version;
	if ( version_compare( $wp_version, WCQF_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'WC Qualiopi Formation requires WordPress %1$s or higher. You are running %2$s.', 'wcqf' ),
			WCQF_MIN_WP_VERSION,
			$wp_version
		);
	}

	// Check WooCommerce (check in active plugins list instead of class_exists)
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	$wc_active = is_plugin_active( 'woocommerce/woocommerce.php' );
	
	if ( ! $wc_active ) {
		$errors[] = __( 'WC Qualiopi Formation requires WooCommerce to be installed and active.', 'wcqf' );
	} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WCQF_MIN_WC_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
			__( 'WC Qualiopi Formation requires WooCommerce %1$s or higher. You are running %2$s.', 'wcqf' ),
			WCQF_MIN_WC_VERSION,
			WC_VERSION
		);
	}

	// Display errors and deactivate plugin if requirements not met
	if ( ! empty( $errors ) ) {
		add_action( 'admin_notices', function() use ( $errors ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>' . esc_html__( 'WC Qualiopi Formation Error:', 'wcqf' ) . '</strong><br>';
			echo esc_html( implode( '<br>', $errors ) );
			echo '</p></div>';
		} );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( WCQF_PLUGIN_BASENAME );

		return false;
	}

	return true;
}

// Check requirements before loading
if ( ! wcqf_check_requirements() ) {
	return;
}

// Load Composer autoloader
$autoloader = WCQF_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'WC Qualiopi Formation Error:', 'wcqf' ) . '</strong> ';
		echo esc_html__( 'Composer autoloader not found. Run "composer install" in the plugin directory.', 'wcqf' );
		echo '</p></div>';
	} );
	return;
}

// Activation hook
register_activation_hook( __FILE__, array( 'WcQualiopiFormation\Core\Activator', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'WcQualiopiFormation\Core\Deactivator', 'deactivate' ) );

/**
 * Initialize the plugin
 * 
 * Uses Singleton pattern to ensure single instance
 */
function wcqf_init() {
	// Initialiser le timer du LoggingHelper le plus tôt possible
	\WcQualiopiFormation\Helpers\LoggingHelper::boot();
	
	return \WcQualiopiFormation\Core\Plugin::instance();
}

// Initialize plugin after WordPress and WooCommerce are fully loaded
add_action( 'plugins_loaded', 'wcqf_init', 20 );

