<?php
/**
 * Tests de sanitization callbacks
 * 
 * @package WcQualiopiFormation\Tests\Unit\Sanitization
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

class SanitizationCallbacksTest extends TestCase {
    
    private $security_bootstrap;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->security_bootstrap = SecurityBootstrap::instance();
    }
    
    /**
     * Test de sanitization des champs texte
     */
    public function test_sanitize_text_field(): void {
        $unsafe_inputs = [
            '<script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            '<img src=x onerror=alert("XSS")>',
            'Text with <b>HTML</b> tags',
            'Text with "quotes" and \'apostrophes\'',
            'Text with special chars: & < > " \'',
        ];
        
        foreach ($unsafe_inputs as $input) {
            $sanitized = sanitize_text_field($input);
            
            // Vérifier que les balises HTML sont supprimées
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('<img', $sanitized);
            $this->assertStringNotContainsString('<b>', $sanitized);
            
            // Vérifier que le texte de base est préservé
            $this->assertStringContainsString('Text', $sanitized);
        }
    }
    
    /**
     * Test de sanitization des emails
     */
    public function test_sanitize_email(): void {
        $unsafe_emails = [
            'test@example.com<script>alert("XSS")</script>',
            'test+tag@example.com',
            'test.email@example.com',
            'test@example.co.uk',
            'test@example-domain.com',
            'test@example.com?subject=test',
        ];
        
        foreach ($unsafe_emails as $email) {
            $sanitized = sanitize_email($email);
            
            // Vérifier que les balises HTML sont supprimées
            $this->assertStringNotContainsString('<script>', $sanitized);
            
            // Vérifier que l'email de base est préservé
            $this->assertStringContainsString('@example.com', $sanitized);
        }
    }
    
    /**
     * Test de sanitization des URLs
     */
    public function test_sanitize_url(): void {
        $unsafe_urls = [
            'https://example.com<script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            'data:text/html,<script>alert("XSS")</script>',
            'https://example.com/path?param=value',
            'https://example.com/path#fragment',
            'ftp://example.com/file.txt',
        ];
        
        foreach ($unsafe_urls as $url) {
            $sanitized = esc_url_raw($url);
            
            // Vérifier que les URLs malveillantes sont supprimées
            if (strpos($url, 'javascript:') === 0) {
                $this->assertEmpty($sanitized);
            } elseif (strpos($url, 'data:') === 0) {
                $this->assertEmpty($sanitized);
            } else {
                // Vérifier que les URLs valides sont préservées
                $this->assertStringContainsString('example.com', $sanitized);
            }
        }
    }
    
    /**
     * Test de sanitization des entiers
     */
    public function test_sanitize_integer(): void {
        $unsafe_integers = [
            '123<script>alert("XSS")</script>',
            '123.45',
            'abc123',
            '123abc',
            '123,456',
            '123 456',
        ];
        
        foreach ($unsafe_integers as $integer) {
            $sanitized = absint($integer);
            
            // Vérifier que seuls les entiers positifs sont retournés
            $this->assertIsInt($sanitized);
            $this->assertGreaterThanOrEqual(0, $sanitized);
        }
    }
    
    /**
     * Test de sanitization des nombres décimaux
     */
    public function test_sanitize_float(): void {
        $unsafe_floats = [
            '123.45<script>alert("XSS")</script>',
            '123.45.67',
            'abc123.45',
            '123.45abc',
            '123,45',
            '123 45.67',
        ];
        
        foreach ($unsafe_floats as $float) {
            $sanitized = floatval($float);
            
            // Vérifier que seuls les nombres valides sont retournés
            $this->assertIsFloat($sanitized);
        }
    }
    
    /**
     * Test de sanitization des booléens
     */
    public function test_sanitize_boolean(): void {
        $unsafe_booleans = [
            'true<script>alert("XSS")</script>',
            'false<script>alert("XSS")</script>',
            '1<script>alert("XSS")</script>',
            '0<script>alert("XSS")</script>',
            'yes<script>alert("XSS")</script>',
            'no<script>alert("XSS")</script>',
        ];
        
        foreach ($unsafe_booleans as $boolean) {
            $sanitized = rest_sanitize_boolean($boolean);
            
            // Vérifier que seuls les booléens valides sont retournés
            $this->assertIsBool($sanitized);
        }
    }
    
    /**
     * Test de sanitization des tableaux
     */
    public function test_sanitize_array(): void {
        $unsafe_arrays = [
            ['<script>alert("XSS")</script>', 'normal_text'],
            ['key' => '<script>alert("XSS")</script>', 'normal_key' => 'normal_value'],
            ['nested' => ['<script>alert("XSS")</script>', 'normal_text']],
        ];
        
        foreach ($unsafe_arrays as $array) {
            $sanitized = array_map('sanitize_text_field', $array);
            
            // Vérifier que les balises HTML sont supprimées
            foreach ($sanitized as $value) {
                if (is_string($value)) {
                    $this->assertStringNotContainsString('<script>', $value);
                }
            }
        }
    }
    
    /**
     * Test de sanitization des objets
     */
    public function test_sanitize_object(): void {
        $unsafe_object = (object) [
            'text' => '<script>alert("XSS")</script>',
            'email' => 'test@example.com<script>alert("XSS")</script>',
            'url' => 'https://example.com<script>alert("XSS")</script>',
            'integer' => '123<script>alert("XSS")</script>',
        ];
        
        $sanitized = (object) [
            'text' => sanitize_text_field($unsafe_object->text),
            'email' => sanitize_email($unsafe_object->email),
            'url' => esc_url_raw($unsafe_object->url),
            'integer' => absint($unsafe_object->integer),
        ];
        
        // Vérifier que les balises HTML sont supprimées
        $this->assertStringNotContainsString('<script>', $sanitized->text);
        $this->assertStringNotContainsString('<script>', $sanitized->email);
        $this->assertStringNotContainsString('<script>', $sanitized->url);
        
        // Vérifier que les types sont corrects
        $this->assertIsString($sanitized->text);
        $this->assertIsString($sanitized->email);
        $this->assertIsString($sanitized->url);
        $this->assertIsInt($sanitized->integer);
    }
    
    /**
     * Test de sanitization des données JSON
     */
    public function test_sanitize_json(): void {
        $unsafe_json = json_encode([
            'text' => '<script>alert("XSS")</script>',
            'email' => 'test@example.com<script>alert("XSS")</script>',
            'url' => 'https://example.com<script>alert("XSS")</script>',
        ]);
        
        $decoded = json_decode($unsafe_json, true);
        $sanitized = [
            'text' => sanitize_text_field($decoded['text']),
            'email' => sanitize_email($decoded['email']),
            'url' => esc_url_raw($decoded['url']),
        ];
        
        // Vérifier que les balises HTML sont supprimées
        $this->assertStringNotContainsString('<script>', $sanitized['text']);
        $this->assertStringNotContainsString('<script>', $sanitized['email']);
        $this->assertStringNotContainsString('<script>', $sanitized['url']);
    }
    
    /**
     * Test de sanitization des données CSV
     */
    public function test_sanitize_csv(): void {
        $unsafe_csv = 'field1,field2<script>alert("XSS")</script>,field3';
        
        $fields = explode(',', $unsafe_csv);
        $sanitized = array_map('sanitize_text_field', $fields);
        
        // Vérifier que les balises HTML sont supprimées
        foreach ($sanitized as $field) {
            $this->assertStringNotContainsString('<script>', $field);
        }
    }
    
    /**
     * Test de sanitization des données XML
     */
    public function test_sanitize_xml(): void {
        $unsafe_xml = '<root><field1>value1</field1><field2><script>alert("XSS")</script></field2></root>';
        
        // Simuler la sanitization XML
        $sanitized = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $unsafe_xml);
        
        // Vérifier que les balises script sont supprimées
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('<root>', $sanitized);
        $this->assertStringContainsString('<field1>', $sanitized);
    }
    
    /**
     * Test de sanitization des données Base64
     */
    public function test_sanitize_base64(): void {
        $unsafe_base64 = 'dGVzdA==<script>alert("XSS")</script>';
        
        // Simuler la sanitization Base64
        $sanitized = preg_replace('/[^A-Za-z0-9+\/=]/', '', $unsafe_base64);
        
        // Vérifier que les caractères non-Base64 sont supprimés
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('dGVzdA==', $sanitized);
    }
    
    /**
     * Test de sanitization des données avec encodage spécial
     */
    public function test_sanitize_special_encoding(): void {
        $unsafe_encodings = [
            'Text with &amp; HTML entities',
            'Text with &lt; HTML entities',
            'Text with &gt; HTML entities',
            'Text with &quot; HTML entities',
            'Text with &#39; HTML entities',
        ];
        
        foreach ($unsafe_encodings as $text) {
            $sanitized = sanitize_text_field($text);
            
            // Vérifier que les entités HTML sont préservées
            $this->assertStringContainsString('&amp;', $sanitized);
            $this->assertStringContainsString('&lt;', $sanitized);
            $this->assertStringContainsString('&gt;', $sanitized);
            $this->assertStringContainsString('&quot;', $sanitized);
            $this->assertStringContainsString('&#39;', $sanitized);
        }
    }
    
    /**
     * Test de sanitization des données avec caractères Unicode
     */
    public function test_sanitize_unicode(): void {
        $unsafe_unicode = [
            'Text with émojis 🚀',
            'Text with accents: café, naïve, résumé',
            'Text with symbols: ©, ®, ™',
            'Text with currency: €, £, ¥',
        ];
        
        foreach ($unsafe_unicode as $text) {
            $sanitized = sanitize_text_field($text);
            
            // Vérifier que les caractères Unicode sont préservés
            $this->assertStringContainsString('émojis', $sanitized);
            $this->assertStringContainsString('café', $sanitized);
            $this->assertStringContainsString('©', $sanitized);
            $this->assertStringContainsString('€', $sanitized);
        }
    }
    
    /**
     * Test de sanitization des données avec caractères de contrôle
     */
    public function test_sanitize_control_characters(): void {
        $unsafe_control = [
            "Text with\ttabs",
            "Text with\nnewlines",
            "Text with\rcarriage returns",
            "Text with\0null characters",
        ];
        
        foreach ($unsafe_control as $text) {
            $sanitized = sanitize_text_field($text);
            
            // Vérifier que les caractères de contrôle sont gérés
            $this->assertIsString($sanitized);
            $this->assertNotEmpty($sanitized);
        }
    }
    
    /**
     * Test de sanitization des données avec caractères de contrôle
     */
    public function test_sanitize_very_long_strings(): void {
        $very_long_string = str_repeat('a', 10000) . '<script>alert("XSS")</script>' . str_repeat('b', 10000);
        
        $sanitized = sanitize_text_field($very_long_string);
        
        // Vérifier que les très longues chaînes sont gérées
        $this->assertIsString($sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('a', $sanitized);
        $this->assertStringContainsString('b', $sanitized);
    }
}
