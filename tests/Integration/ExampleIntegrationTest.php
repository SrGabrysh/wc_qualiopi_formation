<?php
/**
 * Test d'Intégration - Exemple
 * 
 * Ce fichier montre comment créer un test d'intégration avec WordPress chargé.
 * 
 * DIFFÉRENCES avec les tests unitaires :
 * - ✅ WordPress est CHARGÉ (vraies fonctions)
 * - ✅ Base de données RÉELLE accessible
 * - ✅ Plugins WordPress disponibles (WooCommerce, Gravity Forms)
 * - ❌ Plus lent (30-60s pour 100 tests vs 1s pour les unitaires)
 * 
 * @package WcQualiopiFormation\Tests\Integration
 * @since 1.0.0
 */

declare(strict_types=1);

// ============================================================================
// TESTS D'EXEMPLE
// ============================================================================

test('WordPress est chargé et fonctionnel', function() {
    // Vérifier que WordPress est chargé
    expect(function_exists('wp_insert_post'))->toBeTrue();
    expect(function_exists('get_option'))->toBeTrue();
    expect(defined('ABSPATH'))->toBeTrue();
    
    // Vérifier la version de WordPress
    $wp_version = get_bloginfo('version');
    expect($wp_version)->toBeString();
    expect($wp_version)->toMatch('/^\d+\.\d+/'); // Format X.Y.Z
});

test('le plugin est activé dans WordPress', function() {
    // Vérifier que le plugin est activé
    $active_plugins = get_option('active_plugins');
    
    $plugin_active = false;
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'wc_qualiopi_formation') !== false) {
            $plugin_active = true;
            break;
        }
    }
    
    expect($plugin_active)->toBeTrue('Le plugin wc_qualiopi_formation doit être activé');
});

test('les constantes du plugin sont définies', function() {
    // Vérifier que les constantes du plugin existent
    expect(defined('WCQF_VERSION'))->toBeTrue();
    expect(defined('WCQF_PATH'))->toBeTrue();
    expect(defined('WCQF_URL'))->toBeTrue();
    
    // Vérifier les valeurs
    expect(WCQF_VERSION)->toBeString();
    expect(WCQF_PATH)->toBeString();
    expect(WCQF_URL)->toBeString();
});

test('peut créer et supprimer un utilisateur WordPress', function() {
    // Créer un utilisateur de test
    $user = wcqf_create_test_user('subscriber');
    
    expect($user)->toBeInstanceOf(WP_User::class);
    expect($user->ID)->toBeGreaterThan(0);
    expect($user->exists())->toBeTrue();
    
    // Vérifier le rôle
    expect($user->roles)->toContain('subscriber');
    
    // Vérifier qu'il est marqué comme utilisateur de test
    $is_test = get_user_meta($user->ID, '_wcqf_test_user', true);
    expect($is_test)->toBe('1');
    
    // Nettoyer
    wcqf_clean_test_data();
    
    // Vérifier que l'utilisateur a été supprimé
    $user_after = new WP_User($user->ID);
    expect($user_after->exists())->toBeFalse();
});

test('WooCommerce est disponible', function() {
    // Vérifier que WooCommerce est chargé
    expect(class_exists('WooCommerce'))->toBeTrue('WooCommerce doit être activé pour les tests d\'intégration');
    
    if (class_exists('WooCommerce')) {
        expect(function_exists('WC'))->toBeTrue();
        expect(WC())->toBeInstanceOf(WooCommerce::class);
        expect(WC()->version)->toBeString();
    }
})->skip(!class_exists('WooCommerce'), 'WooCommerce non activé');

test('peut créer un produit WooCommerce', function() {
    // Créer un produit de test
    $product = wcqf_create_test_product([
        'name' => 'Formation Test',
        'regular_price' => '299.00'
    ]);
    
    expect($product)->toBeInstanceOf(WC_Product::class);
    expect($product->get_id())->toBeGreaterThan(0);
    expect($product->get_name())->toContain('Formation Test');
    expect($product->get_regular_price())->toBe('299.00');
    
    // Vérifier qu'il est marqué comme produit de test
    $is_test = get_post_meta($product->get_id(), '_wcqf_test_product', true);
    expect($is_test)->toBe('1');
    
    // Nettoyer
    wcqf_clean_test_data();
    
    // Vérifier que le produit a été supprimé
    expect(wc_get_product($product->get_id()))->toBeFalse();
})->skip(!class_exists('WooCommerce'), 'WooCommerce non activé');

test('Gravity Forms est disponible', function() {
    // Vérifier que Gravity Forms est chargé
    expect(class_exists('GFForms'))->toBeTrue('Gravity Forms doit être activé pour les tests d\'intégration');
    expect(class_exists('GFAPI'))->toBeTrue();
})->skip(!class_exists('GFForms'), 'Gravity Forms non activé');

test('peut créer un formulaire Gravity Forms', function() {
    // Créer un formulaire de test
    $form_id = wcqf_create_test_form('Test Positionnement');
    
    expect($form_id)->toBeInt();
    expect($form_id)->toBeGreaterThan(0);
    
    // Récupérer le formulaire
    $form = GFAPI::get_form($form_id);
    expect($form)->toBeArray();
    expect($form['title'])->toContain('Test Positionnement');
    expect($form['fields'])->toBeArray();
    expect(count($form['fields']))->toBeGreaterThan(0);
    
    // Nettoyer
    wcqf_delete_test_form($form_id);
    
    // Vérifier que le formulaire a été supprimé
    $form_after = GFAPI::get_form($form_id);
    expect($form_after)->toBeFalse();
})->skip(!class_exists('GFAPI'), 'Gravity Forms non activé');

test('peut accéder à la base de données WordPress', function() {
    global $wpdb;
    
    // Vérifier que $wpdb est disponible
    expect($wpdb)->toBeInstanceOf(wpdb::class);
    expect($wpdb->prefix)->toBeString();
    expect($wpdb->dbname)->toBeString();
    
    // Test d'une requête simple
    $result = $wpdb->get_var("SELECT 1");
    expect($result)->toBe('1');
    
    // Vérifier les tables du plugin
    $progress_table = $wpdb->prefix . 'wcqf_progress';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$progress_table}'");
    expect($table_exists)->toBe($progress_table, 'La table wcqf_progress doit exister');
});

// ============================================================================
// EXEMPLE DE TEST D'INTÉGRATION COMPLET
// ============================================================================

test('scénario complet : créer utilisateur, produit et commande', function() {
    // 1. Créer un utilisateur
    $user = wcqf_create_test_user('customer');
    expect($user->exists())->toBeTrue();
    
    // 2. Se connecter en tant que cet utilisateur
    wp_set_current_user($user->ID);
    expect(is_user_logged_in())->toBeTrue();
    expect(get_current_user_id())->toBe($user->ID);
    
    // 3. Créer un produit
    $product = wcqf_create_test_product([
        'name' => 'Formation Complète',
        'regular_price' => '499.00'
    ]);
    expect($product->get_id())->toBeGreaterThan(0);
    
    // 4. Créer une commande
    $order = wc_create_order();
    $order->add_product($product, 1);
    $order->set_customer_id($user->ID);
    $order->calculate_totals();
    $order->save();
    
    // 5. Vérifications
    expect($order->get_id())->toBeGreaterThan(0);
    expect($order->get_customer_id())->toBe($user->ID);
    expect($order->get_total())->toBe('499.00');
    
    $items = $order->get_items();
    expect(count($items))->toBe(1);
    
    // 6. Nettoyer
    $order->delete(true);
    wcqf_clean_test_data();
    
    // Se déconnecter
    wp_set_current_user(0);
    expect(is_user_logged_in())->toBeFalse();
    
})->skip(!class_exists('WooCommerce'), 'WooCommerce non activé');

// ============================================================================
// NOTES POUR CRÉER VOS PROPRES TESTS D'INTÉGRATION
// ============================================================================

/*
 * 1. STRUCTURE D'UN TEST D'INTÉGRATION
 * 
 * test('description du test', function() {
 *     // Arrange : Préparer les données
 *     $user = wcqf_create_test_user();
 *     
 *     // Act : Exécuter l'action à tester
 *     $result = votre_fonction_plugin($user->ID);
 *     
 *     // Assert : Vérifier le résultat
 *     expect($result)->toBeTrue();
 *     
 *     // Clean : Nettoyer (optionnel, fait automatiquement après chaque test)
 *     wcqf_clean_test_data();
 * });
 * 
 * 
 * 2. HELPERS DISPONIBLES
 * 
 * - wcqf_create_test_user($role = 'customer') : Créer un utilisateur
 * - wcqf_create_test_product($args = []) : Créer un produit WooCommerce
 * - wcqf_create_test_form($title) : Créer un formulaire Gravity Forms
 * - wcqf_delete_test_form($form_id) : Supprimer un formulaire
 * - wcqf_clean_test_data() : Nettoyer toutes les données de test
 * 
 * 
 * 3. TESTS CONDITIONNELS
 * 
 * Pour les tests qui nécessitent un plugin spécifique :
 * 
 * test('mon test WooCommerce', function() {
 *     // ...
 * })->skip(!class_exists('WooCommerce'), 'WooCommerce requis');
 * 
 * 
 * 4. BONNES PRATIQUES
 * 
 * - Toujours nettoyer après vos tests (fait automatiquement)
 * - Utiliser des IDs > 9000 pour les utilisateurs de test
 * - Marquer vos données avec _wcqf_test_* meta keys
 * - Ne jamais modifier les données de production
 * - Tester un seul concept par test
 * 
 * 
 * 5. LANCER LES TESTS D'INTÉGRATION
 * 
 * Dans DDEV :
 * ddev exec "cd web/wp-content/plugins/wc_qualiopi_formation && composer test:integration"
 * 
 * Ou directement :
 * ddev exec "cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest --configuration=phpunit-integration.xml"
 */

