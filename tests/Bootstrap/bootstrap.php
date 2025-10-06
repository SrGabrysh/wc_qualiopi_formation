<?php
/**
 * Bootstrap pour les tests unitaires
 * Mock les fonctions WordPress essentielles
 * 
 * Note: vendor/autoload.php est chargé dans pest.php AVANT ce fichier
 */

// Mock WordPress functions minimales
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Constantes du plugin (si pas déjà définies)
if (!defined('WCQF_VERSION')) {
    define('WCQF_VERSION', '1.0.0');
}

if (!defined('WCQF_TEXT_DOMAIN')) {
    define('WCQF_TEXT_DOMAIN', 'wc-qualiopi-formation');
}

if (!defined('WCQF_PATH')) {
    define('WCQF_PATH', __DIR__ . '/../../');
}

if (!defined('WCQF_URL')) {
    define('WCQF_URL', 'http://localhost/wp-content/plugins/wc_qualiopi_formation/');
}

