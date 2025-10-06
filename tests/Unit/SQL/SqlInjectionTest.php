<?php
/**
 * Tests de protection contre les injections SQL
 * 
 * @package WcQualiopiFormation\Tests\Unit\SQL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Tests\Bootstrap\SecurityBootstrap;

class SqlInjectionTest extends TestCase {
    
    private $security_bootstrap;
    private $wpdb_mock;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->security_bootstrap = SecurityBootstrap::instance();
        
        // Mock de $wpdb
        $this->wpdb_mock = $this->createMock(stdClass::class);
        $this->wpdb_mock->prefix = 'wp_';
        $this->wpdb_mock->wcqf_progress = 'wp_wcqf_progress';
        $this->wpdb_mock->wcqf_tracking = 'wp_wcqf_tracking';
        $this->wpdb_mock->wcqf_cart_restrictions = 'wp_wcqf_cart_restrictions';
        
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
    }
    
    /**
     * Test de protection contre les injections SQL basiques
     */
    public function test_basic_sql_injection_protection(): void {
        $malicious_inputs = $this->security_bootstrap->simulate_security_attack('sql_injection');
        
        foreach ($malicious_inputs as $malicious_input) {
            // Test avec une requête SELECT
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $malicious_input);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$malicious_input}");
            $this->assertStringNotContainsString($malicious_input, $prepared);
            
            // Test avec une requête INSERT
            $query = "INSERT INTO {$this->wpdb_mock->wcqf_progress} (user_id, data) VALUES (%s, %s)";
            $prepared = $this->wpdb_mock->prepare($query, $malicious_input, 'test_data');
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$malicious_input}");
            $this->assertStringNotContainsString($malicious_input, $prepared);
            
            // Test avec une requête UPDATE
            $query = "UPDATE {$this->wpdb_mock->wcqf_progress} SET data = %s WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, 'test_data', $malicious_input);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$malicious_input}");
            $this->assertStringNotContainsString($malicious_input, $prepared);
            
            // Test avec une requête DELETE
            $query = "DELETE FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $malicious_input);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$malicious_input}");
            $this->assertStringNotContainsString($malicious_input, $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec UNION
     */
    public function test_union_sql_injection_protection(): void {
        $union_attacks = [
            "' UNION SELECT * FROM wp_users --",
            "' UNION SELECT password FROM wp_users --",
            "' UNION SELECT user_login FROM wp_users --",
            "' UNION SELECT user_email FROM wp_users --",
        ];
        
        foreach ($union_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('UNION', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec OR/AND
     */
    public function test_or_and_sql_injection_protection(): void {
        $or_and_attacks = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "' AND '1'='1",
            "' AND 1=1 --",
            "' OR 'a'='a",
            "' AND 'a'='a",
        ];
        
        foreach ($or_and_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString("'1'='1'", $prepared);
            $this->assertStringNotContainsString('1=1', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec DROP
     */
    public function test_drop_sql_injection_protection(): void {
        $drop_attacks = [
            "'; DROP TABLE wp_users; --",
            "'; DROP TABLE wp_posts; --",
            "'; DROP TABLE wp_options; --",
            "'; DROP DATABASE wordpress; --",
        ];
        
        foreach ($drop_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('DROP', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
            $this->assertStringNotContainsString('wp_posts', $prepared);
            $this->assertStringNotContainsString('wp_options', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec INSERT
     */
    public function test_insert_sql_injection_protection(): void {
        $insert_attacks = [
            "'; INSERT INTO wp_users (user_login, user_pass) VALUES ('hacker', 'password'); --",
            "'; INSERT INTO wp_options (option_name, option_value) VALUES ('hack', 'value'); --",
            "'; INSERT INTO wp_posts (post_title, post_content) VALUES ('Hack', 'Hacked'); --",
        ];
        
        foreach ($insert_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('INSERT', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
            $this->assertStringNotContainsString('wp_options', $prepared);
            $this->assertStringNotContainsString('wp_posts', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec UPDATE
     */
    public function test_update_sql_injection_protection(): void {
        $update_attacks = [
            "'; UPDATE wp_users SET user_pass = 'hacked' WHERE user_login = 'admin'; --",
            "'; UPDATE wp_options SET option_value = 'hacked' WHERE option_name = 'admin_email'; --",
            "'; UPDATE wp_posts SET post_content = 'Hacked' WHERE post_type = 'page'; --",
        ];
        
        foreach ($update_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('UPDATE', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
            $this->assertStringNotContainsString('wp_options', $prepared);
            $this->assertStringNotContainsString('wp_posts', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec DELETE
     */
    public function test_delete_sql_injection_protection(): void {
        $delete_attacks = [
            "'; DELETE FROM wp_users WHERE user_login = 'admin'; --",
            "'; DELETE FROM wp_options WHERE option_name = 'admin_email'; --",
            "'; DELETE FROM wp_posts WHERE post_type = 'page'; --",
        ];
        
        foreach ($delete_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('DELETE', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
            $this->assertStringNotContainsString('wp_options', $prepared);
            $this->assertStringNotContainsString('wp_posts', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec CREATE
     */
    public function test_create_sql_injection_protection(): void {
        $create_attacks = [
            "'; CREATE TABLE wp_hack (id INT, data TEXT); --",
            "'; CREATE DATABASE hack_db; --",
            "'; CREATE USER 'hacker'@'localhost' IDENTIFIED BY 'password'; --",
        ];
        
        foreach ($create_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('CREATE', $prepared);
            $this->assertStringNotContainsString('wp_hack', $prepared);
            $this->assertStringNotContainsString('hack_db', $prepared);
            $this->assertStringNotContainsString('hacker', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec ALTER
     */
    public function test_alter_sql_injection_protection(): void {
        $alter_attacks = [
            "'; ALTER TABLE wp_users ADD COLUMN hack TEXT; --",
            "'; ALTER TABLE wp_options MODIFY COLUMN option_value TEXT; --",
            "'; ALTER TABLE wp_posts DROP COLUMN post_content; --",
        ];
        
        foreach ($alter_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('ALTER', $prepared);
            $this->assertStringNotContainsString('wp_users', $prepared);
            $this->assertStringNotContainsString('wp_options', $prepared);
            $this->assertStringNotContainsString('wp_posts', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec EXEC
     */
    public function test_exec_sql_injection_protection(): void {
        $exec_attacks = [
            "'; EXEC xp_cmdshell 'dir'; --",
            "'; EXEC sp_configure 'show advanced options', 1; --",
            "'; EXEC xp_regwrite 'HKEY_LOCAL_MACHINE', 'SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Run', 'hack', 'REG_SZ', 'malware.exe'; --",
        ];
        
        foreach ($exec_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('EXEC', $prepared);
            $this->assertStringNotContainsString('xp_cmdshell', $prepared);
            $this->assertStringNotContainsString('sp_configure', $prepared);
            $this->assertStringNotContainsString('xp_regwrite', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des commentaires
     */
    public function test_comment_sql_injection_protection(): void {
        $comment_attacks = [
            "'; --",
            "'; #",
            "'; /*",
            "'; */",
            "'; /* comment */",
            "'; -- comment",
            "'; # comment",
        ];
        
        foreach ($comment_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString('--', $prepared);
            $this->assertStringNotContainsString('#', $prepared);
            $this->assertStringNotContainsString('/*', $prepared);
            $this->assertStringNotContainsString('*/', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des guillemets
     */
    public function test_quote_sql_injection_protection(): void {
        $quote_attacks = [
            "'; '",
            "'; \"",
            "'; `",
            "'; ';",
            "'; \";",
            "'; `;",
        ];
        
        foreach ($quote_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
            $this->assertStringNotContainsString("';", $prepared);
            $this->assertStringNotContainsString('";', $prepared);
            $this->assertStringNotContainsString('`;', $prepared);
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des caractères spéciaux
     */
    public function test_special_character_sql_injection_protection(): void {
        $special_char_attacks = [
            "'; %",
            "'; _",
            "'; [",
            "'; ]",
            "'; {",
            "'; }",
            "'; (",
            "'; )",
            "'; +",
            "'; -",
            "'; *",
            "'; /",
            "'; =",
            "'; <",
            "'; >",
            "'; !",
            "'; @",
            "'; $",
            "'; ^",
            "'; &",
            "'; |",
            "'; \\",
            "'; ?",
            "'; :",
            "'; ;",
        ];
        
        foreach ($special_char_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des caractères Unicode
     */
    public function test_unicode_sql_injection_protection(): void {
        $unicode_attacks = [
            "'; \u0000",
            "'; \u0001",
            "'; \u0002",
            "'; \u0003",
            "'; \u0004",
            "'; \u0005",
            "'; \u0006",
            "'; \u0007",
            "'; \u0008",
            "'; \u0009",
            "'; \u000A",
            "'; \u000B",
            "'; \u000C",
            "'; \u000D",
            "'; \u000E",
            "'; \u000F",
        ];
        
        foreach ($unicode_attacks as $attack) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $attack);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour: {$attack}");
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des chaînes très longues
     */
    public function test_long_string_sql_injection_protection(): void {
        $long_string = str_repeat("'; DROP TABLE wp_users; --", 1000);
        
        $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
        $prepared = $this->wpdb_mock->prepare($query, $long_string);
        
        $this->assertNotFalse($prepared, "Requête préparée échouée pour chaîne très longue");
        $this->assertStringNotContainsString('DROP', $prepared);
        $this->assertStringNotContainsString('wp_users', $prepared);
    }
    
    /**
     * Test de protection contre les injections SQL avec des chaînes vides
     */
    public function test_empty_string_sql_injection_protection(): void {
        $empty_strings = ['', ' ', '  ', '   '];
        
        foreach ($empty_strings as $empty_string) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $empty_string);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour chaîne vide");
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des valeurs NULL
     */
    public function test_null_sql_injection_protection(): void {
        $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
        $prepared = $this->wpdb_mock->prepare($query, null);
        
        $this->assertNotFalse($prepared, "Requête préparée échouée pour valeur NULL");
    }
    
    /**
     * Test de protection contre les injections SQL avec des valeurs booléennes
     */
    public function test_boolean_sql_injection_protection(): void {
        $boolean_values = [true, false, 1, 0, 'true', 'false', '1', '0'];
        
        foreach ($boolean_values as $boolean_value) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $boolean_value);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour valeur booléenne: {$boolean_value}");
        }
    }
    
    /**
     * Test de protection contre les injections SQL avec des valeurs numériques
     */
    public function test_numeric_sql_injection_protection(): void {
        $numeric_values = [123, 123.45, -123, -123.45, '123', '123.45', '-123', '-123.45'];
        
        foreach ($numeric_values as $numeric_value) {
            $query = "SELECT * FROM {$this->wpdb_mock->wcqf_progress} WHERE user_id = %s";
            $prepared = $this->wpdb_mock->prepare($query, $numeric_value);
            
            $this->assertNotFalse($prepared, "Requête préparée échouée pour valeur numérique: {$numeric_value}");
        }
    }
}
