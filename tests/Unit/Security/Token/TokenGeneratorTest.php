<?php

/**
 * Tests unitaires pour TokenGenerator
 * 
 * @package WcQualiopiFormation\Tests\Unit\Security\Token
 */

use WcQualiopiFormation\Security\Token\TokenGenerator;

// Mock wp_generate_password pour les tests
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        return str_repeat('a', $length);
    }
}

// Tests TokenGenerator (Pest v1 syntax)

test('generates a valid token with all components', function () {
        $user_id = 123;
        $product_id = 4017;
        $secret = 'test_secret_key_32_chars_long__';
        
        $token = TokenGenerator::generate($user_id, $product_id, $secret);
        
        expect($token)
            ->toBeString()
            ->toContain('.')
            ->not->toBeEmpty();
        
        $parts = explode('.', $token);
        expect($parts)->toHaveCount(2);
    });

test('generates different tokens with different nonces', function () {
        $user_id = 123;
        $product_id = 4017;
        $secret = 'test_secret_key';
        $timestamp = time();
        
        $token1 = TokenGenerator::generate($user_id, $product_id, $secret, $timestamp, 'nonce1');
        $token2 = TokenGenerator::generate($user_id, $product_id, $secret, $timestamp, 'nonce2');
        
        expect($token1)->not->toBe($token2);
    });test('can parse a valid token into components', function () {
        $token = 'payload123.signature456';
        
        $parsed = TokenGenerator::parse($token);
        
        expect($parsed)->toBeArray()
            ->toHaveKey('payload')
            ->toHaveKey('signature');
        
        expect($parsed['payload'])->toBe('payload123');
        expect($parsed['signature'])->toBe('signature456');
    });test('returns false when parsing invalid token without dot', function () {
        $invalid_token = 'no_dot_separator';
        
        $result = TokenGenerator::parse($invalid_token);
        
        expect($result)->toBeFalse();
    });test('returns false when parsing token with too many parts', function () {
        $invalid_token = 'part1.part2.part3';
        
        $result = TokenGenerator::parse($invalid_token);
        
        expect($result)->toBeFalse();
    });test('decodes payload correctly', function () {
        $user_id = 123;
        $product_id = 4017;
        $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
        $nonce = 'abc12345';
        
        $payload = "$user_id:$product_id:$timestamp:$nonce";
        $encoded = TokenGenerator::base64url_encode($payload);
        
        $decoded = TokenGenerator::decode_payload($encoded);
        
        expect($decoded)->toBeArray()
            ->toHaveKey('user_id')
            ->toHaveKey('product_id')
            ->toHaveKey('timestamp')
            ->toHaveKey('nonce');
        
        expect($decoded['user_id'])->toBe($user_id);
        expect($decoded['product_id'])->toBe($product_id);
        expect($decoded['timestamp'])->toBe($timestamp);
        expect($decoded['nonce'])->toBe($nonce);
    });test('returns false when decoding invalid payload format', function () {
        $invalid_payload = TokenGenerator::base64url_encode('only:two:parts');
        
        $result = TokenGenerator::decode_payload($invalid_payload);
        
        expect($result)->toBeFalse();
    });test('calculates consistent HMAC signatures', function () {
        $payload = 'test_payload';
        $secret = 'test_secret';
        
        $sig1 = TokenGenerator::calculate_signature($payload, $secret);
        $sig2 = TokenGenerator::calculate_signature($payload, $secret);
        
        expect($sig1)->toBe($sig2);
        expect($sig1)->toBeString();
        expect(strlen($sig1))->toBe(64); // SHA256 = 64 hex chars
    });test('generates different signatures for different payloads', function () {
        $secret = 'test_secret';
        
        $sig1 = TokenGenerator::calculate_signature('payload1', $secret);
        $sig2 = TokenGenerator::calculate_signature('payload2', $secret);
        
        expect($sig1)->not->toBe($sig2);
    });test('generates different signatures for different secrets', function () {
        $payload = 'test_payload';
        
        $sig1 = TokenGenerator::calculate_signature($payload, 'secret1');
        $sig2 = TokenGenerator::calculate_signature($payload, 'secret2');
        
        expect($sig1)->not->toBe($sig2);
    });test('verifies valid signatures', function () {
        $payload = 'test_payload';
        $secret = 'test_secret';
        
        $signature = TokenGenerator::calculate_signature($payload, $secret);
        $is_valid = TokenGenerator::verify_signature($payload, $signature, $secret);
        
        expect($is_valid)->toBeTrue();
    });test('rejects invalid signatures', function () {
        $payload = 'test_payload';
        $secret = 'test_secret';
        $wrong_signature = 'wrong_signature_123';
        
        $is_valid = TokenGenerator::verify_signature($payload, $wrong_signature, $secret);
        
        expect($is_valid)->toBeFalse();
    });test('rejects signatures with wrong secret', function () {
        $payload = 'test_payload';
        $signature = TokenGenerator::calculate_signature($payload, 'secret1');
        
        $is_valid = TokenGenerator::verify_signature($payload, $signature, 'secret2');
        
        expect($is_valid)->toBeFalse();
    });test('encodes and decodes base64url correctly', function () {
        $original = 'Hello World! This is a test with special chars: +/=';
        
        $encoded = TokenGenerator::base64url_encode($original);
        $decoded = TokenGenerator::base64url_decode($encoded);
        
        expect($decoded)->toBe($original);
        expect($encoded)->not->toContain('+');
        expect($encoded)->not->toContain('/');
        expect($encoded)->not->toContain('=');
    });test('detects expired tokens', function () {
        $old_timestamp = time() - 3600; // 1 hour ago
        $max_age = 1800; // 30 minutes
        
        $is_expired = TokenGenerator::is_expired($old_timestamp, $max_age);
        
        expect($is_expired)->toBeTrue();
    });test('detects valid (non-expired) tokens', function () {
        $recent_timestamp = time() - 600; // 10 minutes ago
        $max_age = 1800; // 30 minutes
        
        $is_expired = TokenGenerator::is_expired($recent_timestamp, $max_age);
        
        expect($is_expired)->toBeFalse();
    });test('detects token at exact expiration boundary', function () {
        $timestamp = time() - 1800; // Exactly 30 minutes ago
        $max_age = 1800; // 30 minutes
        
        // At boundary, should NOT be expired (strictly greater than)
        $is_expired = TokenGenerator::is_expired($timestamp, $max_age);
        
        expect($is_expired)->toBeFalse();
    });test('calculates token age correctly', function () {
        $five_minutes_ago = time() - 300;
        
        $age = TokenGenerator::get_age($five_minutes_ago);
        
        expect($age)->toBeGreaterThanOrEqual(300);
        expect($age)->toBeLessThan(310); // Allow 10 seconds margin
    });test('generates complete valid token that can be parsed and verified', function () {
        $user_id = 42;
        $product_id = 1337;
        $secret = 'super_secret_key_for_testing_123';
        $timestamp = time();
        $nonce = 'test_nonce';
        
        // Generate token
        $token = TokenGenerator::generate($user_id, $product_id, $secret, $timestamp, $nonce);
        
        // Parse token
        $parsed = TokenGenerator::parse($token);
        expect($parsed)->toBeArray();
        
        // Decode payload
        $decoded = TokenGenerator::decode_payload($parsed['payload']);
        expect($decoded)->toBeArray();
        expect($decoded['user_id'])->toBe($user_id);
        expect($decoded['product_id'])->toBe($product_id);
        expect($decoded['timestamp'])->toBe($timestamp);
        expect($decoded['nonce'])->toBe($nonce);
        
        // Verify signature
        $is_valid = TokenGenerator::verify_signature($parsed['payload'], $parsed['signature'], $secret);
        expect($is_valid)->toBeTrue();
    });

