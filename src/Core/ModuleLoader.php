<?php
/**
 * Module Loader
 * 
 * RESPONSABILITÉ UNIQUE : Chargement et gestion des modules du plugin
 * 
 * @package WcQualiopiFormation\Core
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Core;

use WcQualiopiFormation\Admin\AdminManager;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe ModuleLoader
 * 
 * Principe SRP : Chargement des modules UNIQUEMENT
 */
class ModuleLoader {

	/**
	 * Loaded modules
	 *
	 * @var array
	 */
	private array $modules = array();

	/**
	 * Load all modules
	 * 
	 * Initializes modules based on context (frontend/admin)
	 */
	public function load_modules(): void {
		$this->load_shared_modules(); // Modules chargés partout (Form, etc.)
		$this->load_frontend_modules();
		$this->load_admin_modules();
		$this->apply_filters();
	}

	/**
	 * Load frontend modules
	 * 
	 * Only loaded on frontend when WooCommerce is active
	 */
	private function load_frontend_modules(): void {
		// Only on frontend
		if ( is_admin() || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Cart Guard - Blocage du checkout si test non validé (singleton)
		$this->modules['cart_guard'] = \WcQualiopiFormation\Cart\CartGuard::get_instance();

		// Cart Restriction - Limitation à 1 produit max (singleton)
		$this->modules['cart_restriction'] = \WcQualiopiFormation\Cart\CartRestriction::get_instance();

		// Checkout modules (à venir Phase 5)
		// $this->modules['checkout'] = new \WcQualiopiFormation\Checkout\CheckoutAutofill();
	}

	/**
	 * Load admin modules
	 * 
	 * Only loaded in admin
	 */
	private function load_admin_modules(): void {
		// Only in admin
		if ( ! is_admin() ) {
			return;
		}

		$logger = \WcQualiopiFormation\Utils\Logger::get_instance();
		$logger->info( '[ModuleLoader] load_admin_modules DEBUT' );

		// Récupérer FormManager depuis les modules partagés
		$form_manager = $this->modules['form'] ?? null;

		if ( $form_manager ) {
			$admin_manager = new AdminManager( $logger, $form_manager );
			$admin_manager->init_hooks();
			$this->modules['admin'] = $admin_manager;
			$logger->info( '[ModuleLoader] AdminManager initialise avec succes' );
		} else {
			$logger->warning( '[ModuleLoader] FormManager non disponible, AdminManager non charge' );
		}
	}

	/**
	 * Load shared modules (frontend + admin)
	 * 
	 * Ces modules doivent être chargés partout car ils gèrent des hooks qui peuvent
	 * s'exécuter aussi bien en frontend (soumissions GF) qu'en admin (édition GF).
	 */
	private function load_shared_modules(): void {
		// Logger instance (Singleton)
		$logger = \WcQualiopiFormation\Utils\Logger::get_instance();

		// Form Manager - Gestion Gravity Forms + SIRET + Tracking
		// Chargé partout car Gravity Forms peut être soumis en frontend ET admin
		if ( class_exists( 'GFForms' ) ) {
			$this->modules['form'] = new \WcQualiopiFormation\Form\FormManager( $logger );
		}
	}

	/**
	 * Apply WordPress filters to modules
	 * 
	 * Allows third-party code to modify loaded modules
	 */
	private function apply_filters(): void {
		/**
		 * Allow third-party code to add custom modules
		 * 
		 * @param array        $modules Current modules array
		 * @param ModuleLoader $loader  ModuleLoader instance
		 */
		$this->modules = apply_filters( 'wcqf_modules', $this->modules, $this );
	}

	/**
	 * Get module instance
	 *
	 * @param string $module_name Module name
	 * @return object|null Module instance or null if not found
	 */
	public function get_module( string $module_name ) {
		return $this->modules[ $module_name ] ?? null;
	}

	/**
	 * Check if module is loaded
	 *
	 * @param string $module_name Module name
	 * @return bool True if module is loaded
	 */
	public function has_module( string $module_name ): bool {
		return isset( $this->modules[ $module_name ] );
	}

	/**
	 * Get all loaded modules
	 *
	 * @return array Array of loaded modules
	 */
	public function get_all_modules(): array {
		return $this->modules;
	}

	/**
	 * Get module count
	 *
	 * @return int Number of loaded modules
	 */
	public function get_module_count(): int {
		return count( $this->modules );
	}

	/**
	 * Get loaded module names
	 *
	 * @return array Array of module names
	 */
	public function get_module_names(): array {
		return array_keys( $this->modules );
	}
}

