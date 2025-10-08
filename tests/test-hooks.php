<?php
/**
 * Test des hooks Gravity Forms
 * Vérifie que les hooks sont bien enregistrés
 */

// Simuler l'environnement WordPress minimal
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}

// Inclure WordPress minimal
require_once ABSPATH . 'wp-config.php';

echo "=== TEST DES HOOKS GRAVITY FORMS ===\n\n";

// Vérifier que Gravity Forms est actif
if (!class_exists('GFForms')) {
    echo "❌ Gravity Forms non actif\n";
    exit(1);
}

echo "✅ Gravity Forms actif\n";

// Vérifier les hooks enregistrés
global $wp_filter;

$hooks_to_check = [
    'gform_post_paging' => 'Transition de page',
    'gform_after_submission' => 'Après soumission',
    'gform_pre_submission' => 'Avant soumission'
];

echo "\n📋 HOOKS GRAVITY FORMS ENREGISTRÉS:\n";
echo str_repeat("-", 50) . "\n";

foreach ($hooks_to_check as $hook => $description) {
    if (isset($wp_filter[$hook])) {
        $callbacks = $wp_filter[$hook]->callbacks;
        $count = 0;
        
        foreach ($callbacks as $priority => $priority_callbacks) {
            $count += count($priority_callbacks);
        }
        
        echo "✅ {$hook}: {$count} callback(s) - {$description}\n";
        
        // Afficher les détails des callbacks
        foreach ($callbacks as $priority => $priority_callbacks) {
            foreach ($priority_callbacks as $callback_id => $callback) {
                if (is_array($callback['function'])) {
                    $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                    $method = $callback['function'][1];
                    echo "   - Priorité {$priority}: {$class}::{$method}\n";
                } else {
                    echo "   - Priorité {$priority}: {$callback['function']}\n";
                }
            }
        }
    } else {
        echo "❌ {$hook}: Aucun callback - {$description}\n";
    }
}

// Vérifier spécifiquement notre hook
echo "\n🎯 RECHERCHE SPÉCIFIQUE - gform_post_paging:\n";
echo str_repeat("-", 50) . "\n";

if (isset($wp_filter['gform_post_paging'])) {
    $callbacks = $wp_filter['gform_post_paging']->callbacks;
    
    foreach ($callbacks as $priority => $priority_callbacks) {
        foreach ($priority_callbacks as $callback_id => $callback) {
            if (is_array($callback['function'])) {
                $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                $method = $callback['function'][1];
                
                if (strpos($class, 'PageTransitionHandler') !== false) {
                    echo "✅ TROUVÉ: {$class}::{$method} (priorité {$priority})\n";
                } else {
                    echo "ℹ️  Autre: {$class}::{$method} (priorité {$priority})\n";
                }
            }
        }
    }
} else {
    echo "❌ Aucun hook gform_post_paging trouvé\n";
}

echo "\n=== FIN DU TEST ===\n";
