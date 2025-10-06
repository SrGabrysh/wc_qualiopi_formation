<?php
/**
 * Pest Configuration File for Security Tests
 */

// Mock WordPress environment for security tests
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for security tests
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return SecurityBootstrap::mock_wp_verify_nonce($nonce, $action);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return SecurityBootstrap::mock_wp_create_nonce($action);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return SecurityBootstrap::mock_current_user_can($capability, $args);
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        return SecurityBootstrap::mock_check_admin_referer($action, $query_arg);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $bootstrap = SecurityBootstrap::instance();
        return $bootstrap->test_sanitize_text_field($str);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        $bootstrap = SecurityBootstrap::instance();
        return $bootstrap->test_sanitize_email($email);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        $bootstrap = SecurityBootstrap::instance();
        return $bootstrap->test_sanitize_url($url);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('rest_sanitize_boolean')) {
    function rest_sanitize_boolean($value) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($string) {
        return $string;
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = 0, $max = 0) {
        return rand($min, $max);
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id) {
        // Mock function
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock function
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock function
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10) {
        // Mock function
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10) {
        // Mock function
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // Mock function
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        // Mock function
        return $value;
    }
}

// Mock global $wpdb
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
    $wpdb->wcqf_progress = 'wp_wcqf_progress';
    $wpdb->wcqf_tracking = 'wp_wcqf_tracking';
    $wpdb->wcqf_cart_restrictions = 'wp_wcqf_cart_restrictions';
}

// Include our security bootstrap only if not already included
if (!class_exists('SecurityBootstrap')) {
    require_once __DIR__ . '/tests/Bootstrap/SecurityBootstrap.php';
}

// Pest uses
uses()->in('tests');
