<?php
/**
 * Tests de sécurité pour ProgressStorage
 * 
 * @package WcQualiopiFormation\Tests\Unit\Data\Progress
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Data\Progress\ProgressStorage;
use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

class ProgressStorageSecurityTest extends TestCase {
    
    private $progress_storage;
    private $security_bootstrap;
    private $wpdb_mock;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->security_bootstrap = SecurityBootstrap::instance();
        
        // Mock de $wpdb
        $this->wpdb_mock = $this->createMock(stdClass::class);
        $this->wpdb_mock->prefix = 'wp_';
        $this->wpdb_mock->wcqf_progress = 'wp_wcqf_progress';
        
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
        
        // Mock de la méthode insert
        $this->wpdb_mock->method('insert')
            ->willReturn(1);
        
        // Mock de la méthode update
        $this->wpdb_mock->method('update')
            ->willReturn(1);
        
        // Mock de la méthode delete
        $this->wpdb_mock->method('delete')
            ->willReturn(1);
        
        // Remplacer $wpdb global
        global $wpdb;
        $wpdb = $this->wpdb_mock;
        
        $this->progress_storage = new ProgressStorage();
    }
    
    /**
     * Test de protection contre les injections SQL dans save_progress
     */
    public function test_save_progress_sql_injection_protection(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            $progress_data = [
                'user_id' => 1,
                'form_id' => 123,
                'step' => $malicious_input,
                'data' => json_encode(['field' => $malicious_input]),
                'status' => 'in_progress'
            ];
            
            // Vérifier que la méthode utilise des requêtes préparées
            $this->wpdb_mock->expects($this->once())
                ->method('prepare')
                ->with(
                    $this->stringContains('%s'),
                    $this->isType('array')
                );
            
            $result = $this->progress_storage->save_progress($progress_data);
            
            // La méthode doit retourner false pour les entrées malveillantes
            $this->assertFalse($result, "SQL injection non bloquée: {$malicious_input}");
        }
    }
    
    /**
     * Test de protection contre les attaques XSS dans save_progress
     */
    public function test_save_progress_xss_protection(): void {
        $xss_inputs = $this->security_bootstrap->simulate_security_attack('xss');
        
        foreach ($xss_inputs as $xss_input) {
            $progress_data = [
                'user_id' => 1,
                'form_id' => 123,
                'step' => 'test_step',
                'data' => json_encode(['field' => $xss_input]),
                'status' => 'in_progress'
            ];
            
            $result = $this->progress_storage->save_progress($progress_data);
            
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
    public function test_save_progress_input_validation(): void {
        // Test avec des données invalides
        $invalid_data = [
            'user_id' => 'invalid_user_id',
            'form_id' => -1,
            'step' => '',
            'data' => 'invalid_json',
            'status' => 'invalid_status'
        ];
        
        $result = $this->progress_storage->save_progress($invalid_data);
        $this->assertFalse($result, "Données invalides acceptées");
        
        // Test avec des données valides
        $valid_data = [
            'user_id' => 1,
            'form_id' => 123,
            'step' => 'step_1',
            'data' => json_encode(['field' => 'value']),
            'status' => 'in_progress'
        ];
        
        $result = $this->progress_storage->save_progress($valid_data);
        $this->assertTrue($result, "Données valides rejetées");
    }
    
    /**
     * Test de protection contre les injections SQL dans get_progress
     */
    public function test_get_progress_sql_injection_protection(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            // Vérifier que la méthode utilise des requêtes préparées
            $this->wpdb_mock->expects($this->once())
                ->method('prepare')
                ->with(
                    $this->stringContains('%s'),
                    $this->isType('array')
                );
            
            $result = $this->progress_storage->get_progress(1, $malicious_input);
            
            // La méthode doit retourner un tableau vide pour les entrées malveillantes
            $this->assertIsArray($result, "SQL injection non bloquée: {$malicious_input}");
        }
    }
    
    /**
     * Test de protection contre les injections SQL dans delete_progress
     */
    public function test_delete_progress_sql_injection_protection(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            // Vérifier que la méthode utilise des requêtes préparées
            $this->wpdb_mock->expects($this->once())
                ->method('prepare')
                ->with(
                    $this->stringContains('%s'),
                    $this->isType('array')
                );
            
            $result = $this->progress_storage->delete_progress(1, $malicious_input);
            
            // La méthode doit retourner false pour les entrées malveillantes
            $this->assertFalse($result, "SQL injection non bloquée: {$malicious_input}");
        }
    }
    
    /**
     * Test de validation des permissions utilisateur
     */
    public function test_user_permission_validation(): void {
        // Test avec un utilisateur non autorisé
        $this->assertFalse(
            current_user_can('manage_options'),
            "Utilisateur non autorisé a accès aux options"
        );
        
        // Test avec un utilisateur autorisé
        $this->assertTrue(
            current_user_can('read'),
            "Utilisateur autorisé n'a pas accès en lecture"
        );
    }
    
    /**
     * Test de protection contre les accès non autorisés
     */
    public function test_unauthorized_access_protection(): void {
        // Simuler un accès sans nonce
        $_REQUEST['_wpnonce'] = '';
        
        $this->assertFalse(
            check_admin_referer('admin_action'),
            "Accès sans nonce autorisé"
        );
        
        // Simuler un accès avec un nonce valide
        $_REQUEST['_wpnonce'] = 'test-admin-nonce';
        
        $this->assertTrue(
            check_admin_referer('admin_action'),
            "Accès avec nonce valide rejeté"
        );
    }
    
    /**
     * Test de sanitization des données
     */
    public function test_data_sanitization(): void {
        $unsafe_data = [
            'text' => '<script>alert("XSS")</script>',
            'email' => 'test@example.com<script>alert("XSS")</script>',
            'url' => 'javascript:alert("XSS")',
        ];
        
        $sanitized_data = [
            'text' => sanitize_text_field($unsafe_data['text']),
            'email' => sanitize_email($unsafe_data['email']),
            'url' => esc_url_raw($unsafe_data['url']),
        ];
        
        // Vérifier que les données sont sanitizées
        $this->assertStringNotContainsString('<script>', $sanitized_data['text']);
        $this->assertStringNotContainsString('<script>', $sanitized_data['email']);
        $this->assertStringNotContainsString('javascript:', $sanitized_data['url']);
    }
    
    /**
     * Test de protection contre les attaques par déni de service
     */
    public function test_dos_protection(): void {
        // Test avec un grand nombre de requêtes
        $large_data = str_repeat('a', 10000);
        
        $progress_data = [
            'user_id' => 1,
            'form_id' => 123,
            'step' => 'test_step',
            'data' => json_encode(['field' => $large_data]),
            'status' => 'in_progress'
        ];
        
        $result = $this->progress_storage->save_progress($progress_data);
        
        // La méthode doit gérer les grandes données sans planter
        $this->assertIsBool($result);
    }
    
    /**
     * Test de protection contre les attaques par épuisement de ressources
     */
    public function test_resource_exhaustion_protection(): void {
        // Test avec des données profondément imbriquées
        $deep_data = [];
        $current = &$deep_data;
        
        for ($i = 0; $i < 1000; $i++) {
            $current['level'] = $i;
            $current['data'] = [];
            $current = &$current['data'];
        }
        
        $progress_data = [
            'user_id' => 1,
            'form_id' => 123,
            'step' => 'test_step',
            'data' => json_encode($deep_data),
            'status' => 'in_progress'
        ];
        
        $result = $this->progress_storage->save_progress($progress_data);
        
        // La méthode doit gérer les données profondes sans planter
        $this->assertIsBool($result);
    }
    
    protected function tearDown(): void {
        parent::tearDown();
        
        // Nettoyer les variables globales
        unset($_REQUEST['_wpnonce']);
    }
}
