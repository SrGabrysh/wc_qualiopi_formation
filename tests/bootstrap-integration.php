<?php
/**
 * Bootstrap pour Tests d'Intégration - WC Qualiopi Formation
 * 
 * Ce fichier charge le VRAI WordPress pour les tests d'intégration.
 * Contrairement aux tests unitaires (qui utilisent des mocks), 
 * les tests d'intégration vérifient que le plugin fonctionne 
 * correctement avec WordPress complet.
 * 
 * @package WcQualiopiFormation\Tests
 * @since 1.0.0
 */

declare(strict_types=1);

// ============================================================================
// 0. MARQUEUR TESTS D'INTÉGRATION
// ============================================================================
// Définir AVANT tout pour que Pest.php ne charge pas les classes mock
if (!defined('WCQF_INTEGRATION_TESTS')) {
    define('WCQF_INTEGRATION_TESTS', true);
}

// ============================================================================
// 1. VÉRIFICATION ENVIRONNEMENT
// ============================================================================

if (!defined('ABSPATH')) {
    // Déterminer le chemin vers WordPress
    $wp_root = getenv('WP_ROOT');
    
    if (!$wp_root) {
        // Par défaut : DDEV
        $wp_root = '/var/www/html/web';
        
        // Si on est en local Windows/WSL
        if (!file_exists($wp_root . '/wp-load.php')) {
            $wp_root = dirname(dirname(dirname(dirname(__DIR__))));
        }
    }
    
    $wp_load = $wp_root . '/wp-load.php';
    
    if (!file_exists($wp_load)) {
        throw new Exception(
            "WordPress non trouvé !\n" .
            "Chemin testé : {$wp_load}\n" .
            "Solution :\n" .
            "  1. Exécuter depuis DDEV : ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && composer test:integration'\n" .
            "  2. Ou définir WP_ROOT : export WP_ROOT=/path/to/wordpress"
        );
    }
    
    echo "✓ WordPress trouvé : {$wp_load}\n";
}

// ============================================================================
// 2. CONFIGURATION WORDPRESS POUR TESTS
// ============================================================================

// Mode test
if (!defined('WP_TESTS_MODE')) {
    define('WP_TESTS_MODE', true);
}

// Désactiver l'envoi d'emails pendant les tests
if (!defined('WP_MAIL_DISABLED')) {
    define('WP_MAIL_DISABLED', true);
}

// Forcer le mode debug
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false); // Désactiver debug pour éviter warnings WooCommerce
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false); // Pas de logs pendant les tests
}

if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Désactiver les "doing_it_wrong" de WordPress pendant les tests
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
    define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
}

// Désactiver les thèmes
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// ============================================================================
// 3. CHARGEMENT WORDPRESS
// ============================================================================

echo "⏳ Chargement de WordPress...\n";

require_once $wp_load;

echo "✓ WordPress chargé (version " . get_bloginfo('version') . ")\n";

// Charger les fichiers WordPress additionnels nécessaires pour les tests
require_once ABSPATH . 'wp-admin/includes/user.php'; // Pour wp_delete_user()
require_once ABSPATH . 'wp-admin/includes/post.php'; // Pour wp_delete_post()

// Désactiver les warnings "_doing_it_wrong" pendant les tests
add_filter('doing_it_wrong_trigger_error', '__return_false', 10, 0);

// ============================================================================
// 4. VÉRIFICATION PLUGIN ACTIVÉ
// ============================================================================

$plugin_file = 'wc_qualiopi_formation/wc_qualiopi_formation.php';

if (!is_plugin_active($plugin_file)) {
    echo "⚠️  Plugin non activé, activation...\n";
    
    // Activer le plugin
    activate_plugin($plugin_file);
    
    if (is_plugin_active($plugin_file)) {
        echo "✓ Plugin activé\n";
    } else {
        throw new Exception("Impossible d'activer le plugin {$plugin_file}");
    }
} else {
    echo "✓ Plugin déjà activé\n";
}

// ============================================================================
// 5. VÉRIFICATION DÉPENDANCES
// ============================================================================

// Vérifier WooCommerce
if (!class_exists('WooCommerce')) {
    echo "⚠️  WooCommerce non activé (certains tests peuvent échouer)\n";
} else {
    echo "✓ WooCommerce actif (version " . WC()->version . ")\n";
}

// Vérifier Gravity Forms
if (!class_exists('GFForms')) {
    echo "⚠️  Gravity Forms non activé (certains tests peuvent échouer)\n";
} else {
    echo "✓ Gravity Forms actif\n";
}

// ============================================================================
// 6. CONFIGURATION BASE DE DONNÉES POUR TESTS
// ============================================================================

global $wpdb;

// Préfixe des tables de test
$test_prefix = $wpdb->prefix . 'test_';

// Créer des tables temporaires si nécessaire
// (Les tests d'intégration doivent nettoyer après eux)

echo "✓ Base de données prête (préfixe : {$wpdb->prefix})\n";

// ============================================================================
// 7. HELPERS POUR TESTS D'INTÉGRATION
// ============================================================================

/**
 * Nettoyer la base de données après un test
 */
function wcqf_clean_test_data() {
    global $wpdb;
    
    // Supprimer les données de test du plugin
    $wpdb->query("DELETE FROM {$wpdb->prefix}wcqf_progress WHERE user_id >= 9000");
    $wpdb->query("DELETE FROM {$wpdb->prefix}wcqf_tracking WHERE user_id >= 9000");
    
    // Supprimer les utilisateurs de test
    $test_users = get_users(['meta_key' => '_wcqf_test_user', 'meta_value' => '1']);
    foreach ($test_users as $user) {
        wp_delete_user($user->ID);
    }
    
    // Supprimer les produits de test
    $test_products = get_posts([
        'post_type' => 'product',
        'meta_key' => '_wcqf_test_product',
        'meta_value' => '1',
        'posts_per_page' => -1
    ]);
    foreach ($test_products as $product) {
        wp_delete_post($product->ID, true);
    }
}

/**
 * Créer un utilisateur de test
 */
function wcqf_create_test_user($role = 'customer') {
    $user_id = wp_create_user(
        'test_user_' . uniqid(),
        'test_password_' . wp_generate_password(),
        'test_' . uniqid() . '@example.com'
    );
    
    if (is_wp_error($user_id)) {
        throw new Exception('Impossible de créer un utilisateur de test : ' . $user_id->get_error_message());
    }
    
    $user = new WP_User($user_id);
    $user->set_role($role);
    
    // Marquer comme utilisateur de test
    update_user_meta($user_id, '_wcqf_test_user', '1');
    
    return $user;
}

/**
 * Créer un produit WooCommerce de test
 */
function wcqf_create_test_product($args = []) {
    if (!class_exists('WC_Product')) {
        throw new Exception('WooCommerce n\'est pas actif');
    }
    
    $defaults = [
        'name' => 'Produit Test ' . uniqid(),
        'status' => 'publish',
        'regular_price' => '100.00',
        'type' => 'simple'
    ];
    
    $args = array_merge($defaults, $args);
    
    $product = new WC_Product_Simple();
    $product->set_name($args['name']);
    $product->set_status($args['status']);
    $product->set_regular_price($args['regular_price']);
    $product->save();
    
    // Marquer comme produit de test
    update_post_meta($product->get_id(), '_wcqf_test_product', '1');
    
    return $product;
}

/**
 * Créer un formulaire Gravity Forms de test
 */
function wcqf_create_test_form($title = 'Form Test') {
    if (!class_exists('GFAPI')) {
        throw new Exception('Gravity Forms n\'est pas actif');
    }
    
    $form = [
        'title' => $title . ' ' . uniqid(),
        'fields' => [
            [
                'type' => 'text',
                'id' => 1,
                'label' => 'Nom'
            ],
            [
                'type' => 'email',
                'id' => 2,
                'label' => 'Email'
            ]
        ]
    ];
    
    $form_id = GFAPI::add_form($form);
    
    if (is_wp_error($form_id)) {
        throw new Exception('Impossible de créer un formulaire de test : ' . $form_id->get_error_message());
    }
    
    return $form_id;
}

/**
 * Supprimer un formulaire Gravity Forms de test
 */
function wcqf_delete_test_form($form_id) {
    if (class_exists('GFAPI')) {
        GFAPI::delete_form($form_id);
    }
}

// ============================================================================
// 8. MOCK DES FONCTIONS SI NÉCESSAIRE
// ============================================================================

// Override wp_mail pour éviter l'envoi d'emails pendant les tests
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        // Log au lieu d'envoyer
        error_log("[TEST EMAIL] To: {$to} | Subject: {$subject}");
        return true;
    }
}

// ============================================================================
// 9. CONFIGURATION PEST POUR INTÉGRATION
// ============================================================================

// Charger Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Configuration Pest spécifique aux tests d'intégration
if (function_exists('uses')) {
    uses()
        ->beforeEach(function () {
            // Reset global state avant chaque test
            wcqf_clean_test_data();
        })
        ->afterEach(function () {
            // Nettoyer après chaque test
            wcqf_clean_test_data();
        })
        ->in('Integration');
}

// ============================================================================
// 10. INFORMATIONS DE DEBUG
// ============================================================================

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧪 ENVIRONNEMENT DE TEST D'INTÉGRATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "WordPress     : " . get_bloginfo('version') . "\n";
echo "PHP           : " . PHP_VERSION . "\n";
echo "Plugin        : wc_qualiopi_formation\n";
echo "WooCommerce   : " . (class_exists('WooCommerce') ? WC()->version : '❌ Non actif') . "\n";
echo "Gravity Forms : " . (class_exists('GFForms') ? '✓ Actif' : '❌ Non actif') . "\n";
echo "Base de données : {$wpdb->dbname} (préfixe: {$wpdb->prefix})\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// ============================================================================
// FIN DU BOOTSTRAP
// ============================================================================

