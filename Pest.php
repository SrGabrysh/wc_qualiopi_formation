<?php

/**
 * Pest Configuration - Brain Monkey + Mockery
 * 
 * Configuration minimale pour tests unitaires WordPress
 * 
 * @package WcQualiopiFormation\Tests
 * @since 1.1.0
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// =============================================================================
// CONSTANTES WORDPRESS
// =============================================================================

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/tests/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// =============================================================================
// CONSTANTES DU PLUGIN
// =============================================================================

if (!defined('WCQF_VERSION')) {
    define('WCQF_VERSION', '1.1.0-dev.0');
}

if (!defined('WCQF_TEXT_DOMAIN')) {
    define('WCQF_TEXT_DOMAIN', 'wcqf');
}

if (!defined('WCQF_PATH')) {
    define('WCQF_PATH', __DIR__ . '/');
}

if (!defined('WCQF_URL')) {
    define('WCQF_URL', 'http://localhost/wp-content/plugins/wc_qualiopi_formation/');
}

if (!defined('WCQF_ASSETS_URL')) {
    define('WCQF_ASSETS_URL', WCQF_URL . 'assets/');
}

if (!defined('WCQF_DEBUG_MODE')) {
    define('WCQF_DEBUG_MODE', true);
}

// =============================================================================
// BRAIN MONKEY SETUP
// =============================================================================

uses()->beforeEach(function() {
    Monkey\setUp();
    
    // ========================================================================
    // MOCKS PAR DÉFAUT : Fonctions WordPress courantes
    // ========================================================================
    
    // Sanitization
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_js')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    Functions\when('absint')->alias(fn($val) => abs((int)$val));
    
    // Nonces
    Functions\when('wp_verify_nonce')->justReturn(true);
    Functions\when('wp_create_nonce')->justReturn('test_nonce');
    Functions\when('wp_nonce_url')->returnArg();
    
    // Permissions
    Functions\when('is_admin')->justReturn(false);
    Functions\when('current_user_can')->justReturn(true);
    Functions\when('get_current_user_id')->justReturn(1);
    
    // Options
    Functions\when('get_option')->justReturn(false);
    Functions\when('update_option')->justReturn(true);
    Functions\when('delete_option')->justReturn(true);
    Functions\when('add_option')->justReturn(true);
    
    // Hooks
    Functions\when('add_action')->justReturn(true);
    Functions\when('add_filter')->justReturn(true);
    Functions\when('do_action')->justReturn(null);
    Functions\when('apply_filters')->returnArg();
    Functions\when('has_action')->justReturn(false);
    Functions\when('has_filter')->justReturn(false);
    Functions\when('remove_action')->justReturn(true);
    Functions\when('remove_filter')->justReturn(true);
    
    // WP Error
    Functions\when('is_wp_error')->justReturn(false);
    
    // Time
    Functions\when('current_time')->justReturn('2025-10-09 10:00:00');
    
    // WordPress Info
    Functions\when('get_bloginfo')->justReturn('6.8.3');
    Functions\when('site_url')->justReturn('https://test.local');
    Functions\when('is_user_logged_in')->justReturn(true);
    
    // Class checks
    Functions\when('class_exists')->justReturn(true);
    Functions\when('function_exists')->justReturn(true);
    
    // Translations
    Functions\when('__')->returnArg();
    Functions\when('_e')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    
})->in('tests');

uses()->afterEach(function() {
    Monkey\tearDown();
    if (class_exists('Mockery')) {
        Mockery::close();
    }
})->in('tests');

// Utiliser le trait Mockery pour l'intégration PHPUnit
uses(MockeryPHPUnitIntegration::class)->in('tests');

// =============================================================================
// CONFIGURATION PAR TYPE DE TEST
// =============================================================================

uses()->in('tests/Unit');
uses()->in('tests/Integration');

// =============================================================================
// CLASSES MOCK (GRAVITY FORMS, WOOCOMMERCE)
// =============================================================================
// Note: Les stubs/mocks de classes GF/WC sont désactivés pour éviter les conflits
//       avec les tests d'intégration (où WordPress charge les vraies classes).
//       Les tests unitaires purs nécessitent une approche différente.

// =============================================================================
// HELPERS GLOBAUX
// =============================================================================

/**
 * Crée un mock wpdb de base
 */
function mockWpdb(): object {
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
    $wpdb->wcqf_progress = 'wp_wcqf_progress';
    $wpdb->wcqf_tracking = 'wp_wcqf_tracking';
    $wpdb->wcqf_cart_restrictions = 'wp_wcqf_cart_restrictions';
    return $wpdb;
}

/**
 * Crée un mock WP_Error
 */
function mockWpError(string $code, string $message): object {
    $error = new stdClass();
    $error->errors = [$code => [$message]];
    $error->error_data = [];
    return $error;
}

/**
 * Vérifie si c'est un WP_Error
 */
function isWpError($thing): bool {
    return is_object($thing) && isset($thing->errors);
}
