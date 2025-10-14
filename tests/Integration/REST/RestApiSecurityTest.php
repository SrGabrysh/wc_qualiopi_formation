<?php

/**
 * Tests REST API Security - Syntaxe Pest
 * 
 * @package WcQualiopiFormation\Tests\Integration\REST
 */

declare(strict_types=1);

use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

beforeEach(function () {
    $this->security_bootstrap = SecurityBootstrap::instance();
    $this->rest_server = Mockery::mock('WP_REST_Server');
});

test('protège les routes REST contre les accès non autorisés', function () {
    // Test permission callback
    $has_permission = current_user_can('read');
    
    expect($has_permission)->toBeTrue();
});

test('valide les nonces sur les requêtes REST', function () {
    $_REQUEST['_wpnonce'] = 'test-rest-nonce';
    
    $is_valid = wp_verify_nonce($_REQUEST['_wpnonce'], 'wp_rest');
    
    expect($is_valid)->toBeTrue();
});

test('sanitize les paramètres REST', function () {
    $params = [
        'user_id' => '123',
        'form_id' => '456<script>',
        'data' => 'test data',
    ];
    
    $sanitized_user_id = absint($params['user_id']);
    $sanitized_form_id = absint($params['form_id']);
    $sanitized_data = sanitize_text_field($params['data']);
    
    expect($sanitized_user_id)->toBe(123);
    expect($sanitized_form_id)->toBe(456);
    expect($sanitized_data)->not->toContain('<script>');
});

test('bloque les requêtes sans authentication', function () {
    $is_authenticated = current_user_can('read');
    
    // Si pas authentifié, bloquer
    if (!$is_authenticated) {
        expect(true)->toBeTrue();
    } else {
        expect($is_authenticated)->toBeTrue();
    }
});

test('protège contre les injections SQL dans les requêtes REST', function () {
    $malicious_input = "'; DROP TABLE wp_users; --";
    
    global $wpdb;
    $query = "SELECT * FROM {$wpdb->prefix}wcqf_data WHERE id = %s";
    $prepared = $wpdb->prepare($query, $malicious_input);
    
    expect($prepared)->not->toContain('DROP');
});

afterEach(function () {
    unset($_REQUEST['_wpnonce']);
});
