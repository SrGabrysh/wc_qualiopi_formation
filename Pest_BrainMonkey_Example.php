<?php

/**
 * EXEMPLE : Pest.php avec Brain Monkey
 * 
 * Ce fichier montre à quoi ressemblerait Pest.php avec Brain Monkey.
 * Comparez avec l'actuel Pest.php (600+ lignes) vs ceci (50 lignes).
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// ============================================================================
// CONFIGURATION PEST PHP + BRAIN MONKEY
// ============================================================================

// Utiliser Mockery avec PHPUnit/Pest
uses()->group('unit')->in('tests/Unit');

// Initialiser Brain Monkey avant chaque test
beforeEach(function () {
    Monkey\setUp();
});

// Nettoyer Brain Monkey après chaque test
afterEach(function () {
    Monkey\tearDown();
});

// ============================================================================
// HELPERS DE TEST
// ============================================================================

/**
 * Mock une fonction WordPress avec une valeur de retour
 */
function mock_wp_function(string $function_name, mixed $return_value): void
{
    Functions\when($function_name)->justReturn($return_value);
}

/**
 * Mock wp_verify_nonce pour les tests de sécurité
 */
function mock_wp_nonce(string $nonce, string $action, bool $valid = true): void
{
    Functions\expect('wp_verify_nonce')
        ->with($nonce, $action)
        ->andReturn($valid);
}

/**
 * Mock current_user_can pour les tests de permissions
 */
function mock_user_can(string $capability, bool $can = true): void
{
    Functions\when('current_user_can')
        ->with($capability)
        ->justReturn($can);
}

// ============================================================================
// EXEMPLE D'UTILISATION DANS UN TEST
// ============================================================================

/*

test('valide un nonce correct', function() {
    // Mock la fonction wp_verify_nonce
    Functions\expect('wp_verify_nonce')
        ->once()
        ->with('test-nonce', 'action')
        ->andReturn(true);
    
    // Tester
    $result = wp_verify_nonce('test-nonce', 'action');
    
    expect($result)->toBeTrue();
});

test('refuse un utilisateur sans permissions', function() {
    // Mock current_user_can
    Functions\expect('current_user_can')
        ->once()
        ->with('manage_options')
        ->andReturn(false);
    
    // Tester
    $can_manage = current_user_can('manage_options');
    
    expect($can_manage)->toBeFalse();
});

test('sanitize les données correctement', function() {
    // Mock sanitize_text_field
    Functions\expect('sanitize_text_field')
        ->once()
        ->with('<script>alert("xss")</script>')
        ->andReturn('alert("xss")');
    
    // Tester
    $safe_text = sanitize_text_field('<script>alert("xss")</script>');
    
    expect($safe_text)->toBe('alert("xss")');
});

*/

