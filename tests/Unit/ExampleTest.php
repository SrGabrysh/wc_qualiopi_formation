<?php

/**
 * Test Infrastructure - Brain Monkey + Mockery
 *
 * Tests de validation de l'infrastructure
 * 
 * @package WcQualiopiFormation\Tests\Unit
 */

use Brain\Monkey\Functions;

// =============================================================================
// TESTS BRAIN MONKEY BASIQUES (qui fonctionnent)
// =============================================================================

test('Brain Monkey - mock simple avec justReturn', function() {
    Functions\when('sanitize_text_field')->justReturn('clean');
    expect(sanitize_text_field('dirty'))->toBe('clean');
});

test('Brain Monkey - mock avec returnArg', function() {
    Functions\when('esc_html')->returnArg();
    expect(esc_html('Test'))->toBe('Test');
});

test('Brain Monkey - mock avec alias', function() {
    Functions\when('absint')->alias(fn($val) => abs((int)$val));
    expect(absint(-42))->toBe(42);
});

test('Brain Monkey - mock get_option', function() {
    Functions\when('get_option')->justReturn('test_value');
    expect(get_option('my_option'))->toBe('test_value');
});

test('Brain Monkey - mock update_option', function() {
    Functions\when('update_option')->justReturn(true);
    expect(update_option('my_option', 'value'))->toBeTrue();
});

test('Brain Monkey - mock wp_verify_nonce', function() {
    Functions\when('wp_verify_nonce')->justReturn(true);
    expect(wp_verify_nonce('test_nonce', 'action'))->toBeTrue();
});

test('Brain Monkey - mock current_user_can', function() {
    Functions\when('current_user_can')->justReturn(true);
    expect(current_user_can('manage_options'))->toBeTrue();
});

test('Brain Monkey - mock add_action', function() {
    Functions\when('add_action')->justReturn(true);
    expect(add_action('init', 'my_function'))->toBeTrue();
});

test('Brain Monkey - mock add_filter', function() {
    Functions\when('add_filter')->justReturn(true);
    expect(add_filter('the_content', 'my_filter'))->toBeTrue();
});

test('Brain Monkey - mock do_action', function() {
    Functions\when('do_action')->justReturn(null);
    expect(do_action('my_action', 'arg1', 'arg2'))->toBeNull();
});

// =============================================================================
// TESTS HELPERS GLOBAUX
// =============================================================================

test('Helper - mockWpdb fonctionne', function() {
    $wpdb = mockWpdb();
    expect($wpdb->prefix)->toBe('wp_');
    expect($wpdb->wcqf_progress)->toBe('wp_wcqf_progress');
});

test('Helper - mockWpError fonctionne', function() {
    $error = mockWpError('test_error', 'Test message');
    expect($error->errors)->toHaveKey('test_error');
    expect($error->errors['test_error'][0])->toBe('Test message');
});

test('Helper - isWpError fonctionne', function() {
    $error = mockWpError('code', 'message');
    expect(isWpError($error))->toBeTrue();
    expect(isWpError('not an error'))->toBeFalse();
});
