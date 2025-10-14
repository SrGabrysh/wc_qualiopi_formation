<?php
/**
 * Tests d'intégration - ProgressStorage
 * 
 * Tests réels avec WordPress chargé et base de données réelle.
 * Valide que le système de sauvegarde de progression fonctionne
 * correctement avec l'environnement WordPress complet.
 * 
 * @package WcQualiopiFormation\Tests\Integration\Data
 * @since 1.0.0
 */

declare(strict_types=1);

use WcQualiopiFormation\Data\Progress\ProgressStorage;

// ============================================================================
// TESTS DE SAUVEGARDE
// ============================================================================

test('sauvegarde la progression utilisateur dans la BDD WordPress réelle', function() {
    // Arrange : Créer un utilisateur et un produit de test
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product([
        'name' => 'Formation Test Intégration',
        'regular_price' => '299.00'
    ]);
    
    $progress_data = [
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'form_id' => 1,
        'page_number' => 2,
        'score' => 75,
        'status' => 'in_progress',
        'form_data' => json_encode([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@example.com'
        ])
    ];
    
    // Act : Sauvegarder la progression
    $storage = new ProgressStorage();
    $result = $storage->save_progress($progress_data);
    
    // Assert : Vérifier que la sauvegarde a réussi
    expect($result)->toBeTrue();
    
    // Vérifier directement dans la BDD WordPress réelle
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcqf_progress';
    
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d AND product_id = %d",
        $user->ID,
        $product->get_id()
    ));
    
    // Assertions détaillées
    expect($row)->not->toBeNull('La progression doit être enregistrée en BDD');
    expect($row->user_id)->toBe((string)$user->ID);
    expect($row->product_id)->toBe((string)$product->get_id());
    expect($row->form_id)->toBe('1');
    expect($row->page_number)->toBe('2');
    expect($row->score)->toBe('75');
    expect($row->status)->toBe('in_progress');
    expect($row->form_data)->toContain('Dupont');
    expect($row->form_data)->toContain('jean.dupont@example.com');
    
    // Vérifier les timestamps
    expect($row->created_at)->not->toBeNull();
    expect($row->updated_at)->not->toBeNull();
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

test('récupère la progression depuis la BDD WordPress réelle', function() {
    // Arrange : Créer et sauvegarder une progression
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product();
    
    $storage = new ProgressStorage();
    $storage->save_progress([
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'score' => 85,
        'status' => 'completed',
        'form_data' => json_encode(['test' => 'data'])
    ]);
    
    // Act : Récupérer la progression
    $progress = $storage->get_progress($user->ID, $product->get_id());
    
    // Assert : Vérifier les données récupérées
    expect($progress)->not->toBeNull();
    expect($progress)->toBeArray();
    expect($progress['user_id'])->toBe($user->ID);
    expect($progress['product_id'])->toBe($product->get_id());
    expect($progress['score'])->toBe(85);
    expect($progress['status'])->toBe('completed');
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

test('met à jour une progression existante dans la BDD', function() {
    // Arrange : Créer une progression initiale
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product();
    
    $storage = new ProgressStorage();
    
    // Première sauvegarde
    $storage->save_progress([
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'score' => 50,
        'page_number' => 1,
        'status' => 'in_progress'
    ]);
    
    // Récupérer le timestamp de création
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcqf_progress';
    $created_at_before = $wpdb->get_var($wpdb->prepare(
        "SELECT created_at FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    
    // Attendre 1 seconde pour voir la différence de timestamp
    sleep(1);
    
    // Act : Mettre à jour la progression
    $storage->save_progress([
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'score' => 90,
        'page_number' => 3,
        'status' => 'completed'
    ]);
    
    // Assert : Vérifier la mise à jour
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    
    expect($row->score)->toBe('90', 'Le score doit être mis à jour');
    expect($row->page_number)->toBe('3', 'La page doit être mise à jour');
    expect($row->status)->toBe('completed', 'Le statut doit être mis à jour');
    
    // Vérifier que created_at n'a pas changé
    expect($row->created_at)->toBe($created_at_before, 'created_at ne doit pas changer');
    
    // Vérifier que updated_at a changé
    expect($row->updated_at)->not->toBe($row->created_at, 'updated_at doit être différent de created_at');
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

// ============================================================================
// TESTS DE VALIDATION
// ============================================================================

test('ne sauvegarde pas de progression avec des données invalides', function() {
    // Arrange : Données invalides (user_id manquant)
    $product = wcqf_create_test_product();
    
    $invalid_data = [
        'product_id' => $product->get_id(),
        'score' => 75,
        // user_id manquant !
    ];
    
    // Act & Assert : La sauvegarde doit échouer
    $storage = new ProgressStorage();
    $result = $storage->save_progress($invalid_data);
    
    expect($result)->toBeFalse('La sauvegarde doit échouer avec des données invalides');
    
    // Vérifier qu'aucune ligne n'a été créée en BDD
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcqf_progress';
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE product_id = %d",
        $product->get_id()
    ));
    
    expect($count)->toBe('0', 'Aucune ligne ne doit être créée avec des données invalides');
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

test('sanitize les données avant sauvegarde en BDD', function() {
    // Arrange : Créer des données avec du contenu potentiellement dangereux
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product();
    
    $dangerous_data = [
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'form_data' => json_encode([
            'nom' => '<script>alert("XSS")</script>Dupont',
            'email' => 'test@example.com\'; DROP TABLE wp_users; --'
        ])
    ];
    
    // Act : Sauvegarder
    $storage = new ProgressStorage();
    $result = $storage->save_progress($dangerous_data);
    
    expect($result)->toBeTrue();
    
    // Assert : Vérifier que les données sont sanitizées
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcqf_progress';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    
    // Le script doit être échappé ou retiré
    expect($row->form_data)->not->toContain('<script>');
    expect($row->form_data)->not->toContain('DROP TABLE');
    
    // La table users doit toujours exister (pas supprimée par injection SQL)
    $users_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}users'");
    expect($users_table_exists)->not->toBeNull('La table users ne doit pas être supprimée');
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

// ============================================================================
// TESTS DE SUPPRESSION
// ============================================================================

test('supprime la progression d\'un utilisateur de la BDD', function() {
    // Arrange : Créer une progression
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product();
    
    $storage = new ProgressStorage();
    $storage->save_progress([
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'score' => 75
    ]);
    
    // Vérifier que la progression existe
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcqf_progress';
    $count_before = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    expect($count_before)->toBe('1');
    
    // Act : Supprimer la progression
    $result = $storage->delete_progress($user->ID, $product->get_id());
    
    // Assert : Vérifier la suppression
    expect($result)->toBeTrue();
    
    $count_after = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    expect($count_after)->toBe('0', 'La progression doit être supprimée de la BDD');
    
})->skip(!class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'ProgressStorage non disponible');

// ============================================================================
// TESTS AVEC WOOCOMMERCE
// ============================================================================

test('sauvegarde la progression pour un produit WooCommerce réel', function() {
    // Arrange : Créer un produit WooCommerce avec des métadonnées
    $user = wcqf_create_test_user('customer');
    $product = wcqf_create_test_product([
        'name' => 'Formation PHP Avancée',
        'regular_price' => '499.00',
        'type' => 'simple'
    ]);
    
    // Ajouter des métadonnées au produit
    update_post_meta($product->get_id(), '_wcqf_requires_test', 'yes');
    update_post_meta($product->get_id(), '_wcqf_test_passing_score', '70');
    
    // Act : Sauvegarder la progression
    $storage = new ProgressStorage();
    $result = $storage->save_progress([
        'user_id' => $user->ID,
        'product_id' => $product->get_id(),
        'score' => 85,
        'status' => 'completed'
    ]);
    
    // Assert : Vérifier la sauvegarde
    expect($result)->toBeTrue();
    
    // Récupérer et vérifier
    $progress = $storage->get_progress($user->ID, $product->get_id());
    expect($progress['product_id'])->toBe($product->get_id());
    
    // Vérifier que le produit existe toujours et a ses métadonnées
    $product_after = wc_get_product($product->get_id());
    expect($product_after)->not->toBeFalse();
    expect($product_after->get_name())->toBe('Formation PHP Avancée');
    expect(get_post_meta($product->get_id(), '_wcqf_requires_test', true))->toBe('yes');
    
})->skip(!class_exists('WooCommerce') || !class_exists('WcQualiopiFormation\Data\Progress\ProgressStorage'), 'WooCommerce ou ProgressStorage non disponible');

// ============================================================================
// NOTES POUR LES DÉVELOPPEURS
// ============================================================================

/*
 * Ce fichier démontre les tests d'intégration réels avec :
 * 
 * ✅ WordPress chargé complet
 * ✅ Base de données réelle (lecture/écriture)
 * ✅ Plugins WordPress actifs (WooCommerce)
 * ✅ Vérifications directes en BDD avec $wpdb
 * ✅ Tests de sanitization et sécurité
 * ✅ Nettoyage automatique après chaque test
 * 
 * DIFFÉRENCES avec les tests unitaires :
 * - Unitaires : Mocks, rapides (1s), logique isolée
 * - Intégration : WordPress réel, lents (30-60s), BDD réelle
 * 
 * QUAND LES LANCER :
 * - Pendant dev : Tests unitaires (feedback instant)
 * - Avant commit : Tests intégration (validation complète)
 * 
 * COMMANDES :
 * - composer test:integration
 * - .\dev-tools\testing\run-integration-tests.ps1
 * - ./dev-tools/testing/run-integration-tests.sh
 */

