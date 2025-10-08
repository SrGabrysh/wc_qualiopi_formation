<?php
/**
 * Test de transition de page Gravity Forms
 * Simule une transition de page 15 vers 30 pour tester les hooks
 */

// Simuler l'environnement WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}

// Inclure WordPress
require_once ABSPATH . 'wp-config.php';

echo "=== TEST DE TRANSITION DE PAGE ===\n\n";

// Vérifier que notre plugin est chargé
if (!class_exists('WcQualiopiFormation\\Form\\GravityForms\\PageTransitionHandler')) {
    echo "❌ PageTransitionHandler non trouvé\n";
    exit(1);
}

echo "✅ PageTransitionHandler trouvé\n";

// Vérifier que Gravity Forms est actif
if (!class_exists('GFForms')) {
    echo "❌ Gravity Forms non actif\n";
    exit(1);
}

echo "✅ Gravity Forms actif\n";

// Créer une instance de test
try {
    $field_mapper = new WcQualiopiFormation\Form\GravityForms\FieldMapper();
    $calculation_retriever = new WcQualiopiFormation\Form\GravityForms\CalculationRetriever($field_mapper);
    $page_transition_handler = new WcQualiopiFormation\Form\GravityForms\PageTransitionHandler($calculation_retriever);
    
    echo "✅ Instances créées avec succès\n";
    
    // Vérifier les constantes
    $reflection = new ReflectionClass($page_transition_handler);
    $source_page = $reflection->getConstant('SOURCE_PAGE');
    $target_page = $reflection->getConstant('TARGET_PAGE');
    $score_field = $reflection->getConstant('SCORE_FIELD_ID');
    
    echo "📊 Constantes:\n";
    echo "   - SOURCE_PAGE: {$source_page}\n";
    echo "   - TARGET_PAGE: {$target_page}\n";
    echo "   - SCORE_FIELD_ID: {$score_field}\n";
    
    // Simuler un formulaire
    $mock_form = array(
        'id' => 1,
        'title' => 'Test Form',
        'pages' => array(
            1 => array('id' => 1),
            15 => array('id' => 15),
            30 => array('id' => 30)
        )
    );
    
    echo "\n🧪 Test de transition de page...\n";
    
    // Appeler directement la méthode de transition
    $page_transition_handler->on_page_transition($mock_form, $source_page, $target_page);
    
    echo "✅ Test de transition terminé\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== FIN DU TEST ===\n";
