<?php
/**
 * Main Plugin Class
 * 
 * RESPONSABILITÉ UNIQUE : Bootstrap du plugin + orchestration générale
 * 
 * @package WcQualiopiFormation\Core
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Core;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin (Bootstrap)
 * 
 * Singleton pattern + orchestration minimale
 */
class Plugin {

	/**
	 * Single instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Module loader instance
	 *
	 * @var ModuleLoader
	 */
	private ModuleLoader $module_loader;

	/**
	 * Get singleton instance
	 *
	 * @return Plugin Instance
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor (private for singleton)
	 */
	private function __construct() {
		$this->version = WCQF_VERSION;
		$this->init();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize plugin
	 * 
	 * Principle: Separation of Concerns
	 * Each initialization step has its own method
	 */
	private function init(): void {
		$this->load_textdomain();
		$this->init_modules();
		$this->register_hooks();
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wcqf',
			false,
			dirname( plugin_basename( WCQF_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Initialize modules - DELEGATED
	 */
	public function init_modules(): void {
		$this->module_loader = new ModuleLoader();
		$this->module_loader->load_modules();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks(): void {
		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Version check and update
		add_action( 'admin_init', array( $this, 'check_version' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets(): void {
		// Only load on relevant pages (performance optimization)
		if ( ! is_cart() && ! is_checkout() && ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'wcqf-frontend',
			WCQF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'wcqf-frontend',
			WCQF_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			'wcqf-frontend',
			'wcqfData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wcqf_nonce' ),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on plugin settings page
		if ( 'woocommerce_page_wcqf-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wcqf-admin',
			WCQF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'wcqf-admin',
			WCQF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Check plugin version and run update routines if needed
	 */
	public function check_version(): void {
		$installed_version = get_option( Constants::OPTION_PLUGIN_VERSION );

		if ( $installed_version !== $this->version ) {
			$this->update_version( $installed_version, $this->version );
		}
	}

	/**
	 * Update plugin version
	 *
	 * @param string|false $old_version Old version
	 * @param string       $new_version New version
	 */
	private function update_version( $old_version, string $new_version ): void {
		// Run update routines based on version
		// Example: if ( version_compare( $old_version, '1.1.0', '<' ) ) { ... }

		// Update version in database
		update_option( Constants::OPTION_PLUGIN_VERSION, $new_version );

		/**
		 * Fires after plugin version update
		 *
		 * @param string|false $old_version Previous version
		 * @param string       $new_version New version
		 */
		do_action( 'wcqf_version_updated', $old_version, $new_version );
	}

	/**
	 * Get module instance - DELEGATED
	 *
	 * @param string $module_name Module name
	 * @return object|null Module instance or null if not found
	 */
	public function get_module( string $module_name ) {
		return $this->module_loader->get_module( $module_name );
	}

	/**
	 * Get plugin version
	 *
	 * @return string Version number
	 */
	public function get_version(): string {
		return $this->version;
	}

	/**
	 * Get module loader
	 *
	 * @return ModuleLoader Module loader instance
	 */
	public function get_module_loader(): ModuleLoader {
		return $this->module_loader;
	}
}
