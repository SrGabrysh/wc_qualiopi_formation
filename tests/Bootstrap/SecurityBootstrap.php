<?php
/**
 * Bootstrap pour les tests de sécurité
 * 
 * @package WcQualiopiFormation\Tests\Bootstrap
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class SecurityBootstrap {
    
    private static $instance = null;
    private $security_config = [];
    
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_security_config();
        $this->setup_security_mocks();
        $this->init_wordpress_security();
    }
    
    /**
     * Configuration de sécurité pour les tests
     */
    private function init_security_config(): void {
        $this->security_config = [
            'nonce_lifetime' => 3600,
            'max_login_attempts' => 3,
            'session_timeout' => 1800,
            'csrf_protection' => true,
            'sql_injection_protection' => true,
            'xss_protection' => true,
            'file_upload_validation' => true,
            'rate_limiting' => true,
        ];
    }
    
    /**
     * Configuration des mocks de sécurité
     */
    private function setup_security_mocks(): void {
        // Mock pour les fonctions WordPress de sécurité
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
    }
    
    /**
     * Initialisation de la sécurité WordPress
     */
    private function init_wordpress_security(): void {
        // Configuration des constantes de sécurité
        if (!defined('NONCE_SALT')) {
            define('NONCE_SALT', 'test-nonce-salt-' . wp_rand());
        }
        
        if (!defined('AUTH_SALT')) {
            define('AUTH_SALT', 'test-auth-salt-' . wp_rand());
        }
        
        if (!defined('SECURE_AUTH_SALT')) {
            define('SECURE_AUTH_SALT', 'test-secure-auth-salt-' . wp_rand());
        }
        
        if (!defined('LOGGED_IN_SALT')) {
            define('LOGGED_IN_SALT', 'test-logged-in-salt-' . wp_rand());
        }
        
        // Configuration des hooks de sécurité
        add_action('init', [$this, 'init_security_hooks']);
    }
    
    /**
     * Hooks de sécurité pour les tests
     */
    public function init_security_hooks(): void {
        // Hook pour valider les nonces
        add_filter('wp_verify_nonce', [$this, 'validate_test_nonce'], 10, 2);
        
        // Hook pour valider les capacités utilisateur
        add_filter('user_has_cap', [$this, 'validate_test_capabilities'], 10, 3);
        
        // Hook pour la sanitization
        add_filter('sanitize_text_field', [$this, 'test_sanitize_text_field'], 10, 1);
        add_filter('sanitize_email', [$this, 'test_sanitize_email'], 10, 1);
        add_filter('sanitize_url', [$this, 'test_sanitize_url'], 10, 1);
    }
    
    /**
     * Mock pour wp_verify_nonce
     */
    public static function mock_wp_verify_nonce($nonce, $action = -1): bool {
        // En mode test, on accepte certains nonces prédéfinis
        $valid_nonces = [
            'test-admin-nonce' => 'admin_action',
            'test-user-nonce' => 'user_action',
            'test-form-nonce' => 'form_submission',
        ];
        
        return isset($valid_nonces[$nonce]) && $valid_nonces[$nonce] === $action;
    }
    
    /**
     * Mock pour wp_create_nonce
     */
    public static function mock_wp_create_nonce($action = -1): string {
        $nonce_map = [
            'admin_action' => 'test-admin-nonce',
            'user_action' => 'test-user-nonce',
            'form_submission' => 'test-form-nonce',
        ];
        
        return $nonce_map[$action] ?? 'test-default-nonce';
    }
    
    /**
     * Mock pour current_user_can
     */
    public static function mock_current_user_can($capability, $args = []): bool {
        // En mode test, on simule différents niveaux d'utilisateur
        $test_capabilities = [
            'manage_options' => true,
            'edit_posts' => true,
            'read' => true,
            'delete_posts' => false,
            'manage_woocommerce' => true,
        ];
        
        return $test_capabilities[$capability] ?? false;
    }
    
    /**
     * Mock pour check_admin_referer
     */
    public static function mock_check_admin_referer($action = -1, $query_arg = '_wpnonce'): bool {
        return self::mock_wp_verify_nonce($_REQUEST[$query_arg] ?? '', $action);
    }
    
    /**
     * Validation des nonces en mode test
     */
    public function validate_test_nonce($result, $nonce): bool {
        // Logique de validation spécifique aux tests
        return $result;
    }
    
    /**
     * Validation des capacités en mode test
     */
    public function validate_test_capabilities($allcaps, $caps, $args): array {
        // Logique de validation des capacités pour les tests
        return $allcaps;
    }
    
    /**
     * Test de sanitization pour les champs texte
     */
    public function test_sanitize_text_field($str): string {
        // Test de sanitization basique
        $str = wp_check_invalid_utf8($str);
        $str = trim($str);
        $str = stripslashes($str);
        return $str;
    }
    
    /**
     * Test de sanitization pour les emails
     */
    public function test_sanitize_email($email): string {
        return sanitize_email($email);
    }
    
    /**
     * Test de sanitization pour les URLs
     */
    public function test_sanitize_url($url): string {
        return esc_url_raw($url);
    }
    
    /**
     * Configuration de sécurité pour les tests
     */
    public function get_security_config(): array {
        return $this->security_config;
    }
    
    /**
     * Simulation d'attaques pour les tests
     */
    public function simulate_security_attack(string $type, array $payload = []): array {
        $attacks = [
            'sql_injection' => [
                "'; DROP TABLE wp_users; --",
                "' OR '1'='1",
                "'; INSERT INTO wp_options VALUES ('hack', 'value'); --",
            ],
            'xss' => [
                '<script>alert("XSS")</script>',
                'javascript:alert("XSS")',
                '<img src=x onerror=alert("XSS")>',
            ],
            'csrf' => [
                'fake_nonce',
                'invalid_action',
                'expired_nonce',
            ],
        ];
        
        return $attacks[$type] ?? [];
    }
    
    /**
     * Validation des requêtes SQL préparées
     */
    public function validate_prepared_query(string $query, array $args = []): bool {
        // Vérification que la requête utilise des placeholders
        $placeholder_count = substr_count($query, '%s') + substr_count($query, '%d') + substr_count($query, '%f');
        
        // Vérification que le nombre d'arguments correspond aux placeholders
        return $placeholder_count === count($args);
    }
    
    /**
     * Test de protection contre les injections SQL
     */
    public function test_sql_injection_protection(string $input): bool {
        $dangerous_patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+\'\w+\'\s*=\s*\'\w+\')/i',
            '/(\b(OR|AND)\s+\"\w+\"\s*=\s*\"\w+\")/i',
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false; // Injection détectée
            }
        }
        
        return true; // Sécurisé
    }
    
    /**
     * Test de protection contre les attaques XSS
     */
    public function test_xss_protection(string $input): bool {
        $xss_patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i',
        ];
        
        foreach ($xss_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false; // XSS détecté
            }
        }
        
        return true; // Sécurisé
    }
}

// Initialisation automatique
SecurityBootstrap::instance();
