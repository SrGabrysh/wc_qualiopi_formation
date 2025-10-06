#!/usr/bin/env python3
"""
Test E2E : Tests de non-régression
====================================

Vérifie que toutes les fonctionnalités existantes fonctionnent toujours.

Modules testés :
- CartGuard (blocage checkout)
- CartRestriction (limite 1 produit)
- FormManager (tracking GF)
- Tous les modules principaux
"""

import sys
from pathlib import Path

# Import framework
test_framework_path = Path(__file__).parent.parent / "helpers"
sys.path.insert(0, str(test_framework_path))

from test_framework import E2ETestFramework

# Configuration
config = {
    "test_name": "Non-Regression Test",
    "test_id": "E2E_003",
    "estimated_duration": "20 min",
    "modules_tested": ["CartGuard", "CartRestriction", "FormManager", "All modules"],
}

# Créer framework
test = E2ETestFramework(config)

# ========================================
# PHASE 1 : Vérifier activation plugin
# ========================================
test.print_phase(1, "Vérifier activation du plugin")

test.print_instruction("Le plugin doit être actif et chargé correctement")

test.ssh_command(
    """
ddev wp plugin list --name=wc_qualiopi_formation --format=json
"""
)

test.collect_observation(
    "Le plugin wc_qualiopi_formation est-il actif ? (Status: active)"
)

# ========================================
# PHASE 2 : Tester CartGuard
# ========================================
test.print_phase(2, "Tester CartGuard (blocage checkout)")

test.print_substep("2.1", "Vérifier que CartGuard est chargé")
test.ssh_command(
    """
ddev wp eval 'echo class_exists("WcQualiopiFormation\\\\Cart\\\\CartGuard") ? "✅ CartGuard exists" : "❌ CartGuard missing";'
"""
)

test.collect_observation("La classe CartGuard existe-t-elle ?")

test.print_substep("2.2", "Instructions manuelles : Test fonctionnel")
test.print_instruction(
    """
1. Ouvre https://tb-wp-dev.ddev.site/panier/ dans ton navigateur
2. Ajoute un produit au panier
3. Vérifie que le bouton checkout est remplacé par "Passer le test de positionnement"
4. Clique sur le bouton et vérifie la redirection
"""
)

test.collect_observation(
    "Le CartGuard fonctionne-t-il correctement ? (Bouton + Redirection)"
)

# ========================================
# PHASE 3 : Tester CartRestriction
# ========================================
test.print_phase(3, "Tester CartRestriction (limite 1 produit)")

test.print_substep("3.1", "Vérifier que CartRestriction est chargé")
test.ssh_command(
    """
ddev wp eval 'echo class_exists("WcQualiopiFormation\\\\Cart\\\\CartRestriction") ? "✅ CartRestriction exists" : "❌ CartRestriction missing";'
"""
)

test.collect_observation("La classe CartRestriction existe-t-elle ?")

test.print_substep("3.2", "Instructions manuelles : Test fonctionnel")
test.print_instruction(
    """
1. Ouvre https://tb-wp-dev.ddev.site/panier/ dans ton navigateur
2. Ajoute un produit au panier
3. Essaye d'ajouter un DEUXIÈME produit
4. Vérifie qu'un message d'erreur s'affiche et que le panier reste à 1 produit
"""
)

test.collect_observation(
    "Le CartRestriction fonctionne-t-il ? (Limite 1 produit enforced)"
)

# ========================================
# PHASE 4 : Tester FormManager
# ========================================
test.print_phase(4, "Tester FormManager (Gravity Forms integration)")

test.print_substep("4.1", "Vérifier que FormManager est chargé")
test.ssh_command(
    """
ddev wp eval 'echo class_exists("WcQualiopiFormation\\\\Form\\\\FormManager") ? "✅ FormManager exists" : "❌ FormManager missing";'
"""
)

test.collect_observation("La classe FormManager existe-t-elle ?")

test.print_substep("4.2", "Vérifier que les hooks Gravity Forms sont enregistrés")
test.ssh_command(
    """
ddev wp eval 'echo has_filter("gform_pre_render") ? "✅ gform_pre_render hook registered" : "❌ Hook missing";'
"""
)

test.collect_observation("Le hook gform_pre_render est-il enregistré ?")

test.ssh_command(
    """
ddev wp eval 'echo has_filter("gform_validation") ? "✅ gform_validation hook registered" : "❌ Hook missing";'
"""
)

test.collect_observation("Le hook gform_validation est-il enregistré ?")

test.ssh_command(
    """
ddev wp eval 'echo has_action("gform_after_submission") ? "✅ gform_after_submission hook registered" : "❌ Hook missing";'
"""
)

test.collect_observation("Le hook gform_after_submission est-il enregistré ?")

# ========================================
# PHASE 5 : Vérifier TrackingManager
# ========================================
test.print_phase(5, "Vérifier TrackingManager")

test.print_substep("5.1", "Vérifier table de tracking")
test.ssh_command(
    """
ddev wp db query "SHOW TABLES LIKE 'wp_wcqf_tracking'"
"""
)

test.collect_observation("La table wp_wcqf_tracking existe-t-elle ?")

test.print_substep("5.2", "Vérifier structure table tracking")
test.ssh_command(
    """
ddev wp db query "DESCRIBE wp_wcqf_tracking"
"""
)

test.collect_observation(
    "La table a-t-elle les bonnes colonnes ? (token, form_id, entry_id, siret, company_name, form_data, submitted_at)"
)

# ========================================
# PHASE 6 : Vérifier SirenAutocomplete
# ========================================
test.print_phase(6, "Vérifier SirenAutocomplete")

test.print_substep("6.1", "Vérifier que SirenAutocomplete existe")
test.ssh_command(
    """
ddev wp eval 'echo class_exists("WcQualiopiFormation\\\\Form\\\\Siren\\\\SirenAutocomplete") ? "✅ SirenAutocomplete exists" : "❌ Missing";'
"""
)

test.collect_observation("La classe SirenAutocomplete existe-t-elle ?")

test.print_substep("6.2", "Vérifier que l'API key peut être récupérée")
test.ssh_command(
    """
ddev wp eval 'use WcQualiopiFormation\\Form\\Siren\\SirenAutocomplete; use WcQualiopiFormation\\Utils\\Logger; $logger = new Logger(); $siren = new SirenAutocomplete($logger); echo "✅ SirenAutocomplete instantiated successfully";'
"""
)

test.collect_observation(
    "SirenAutocomplete peut-elle être instanciée sans erreur ?"
)

# ========================================
# PHASE 7 : Vérifier MentionsGenerator
# ========================================
test.print_phase(7, "Vérifier MentionsGenerator")

test.print_substep("7.1", "Vérifier que MentionsGenerator existe")
test.ssh_command(
    """
ddev wp eval 'echo class_exists("WcQualiopiFormation\\\\Form\\\\MentionsLegales\\\\MentionsGenerator") ? "✅ MentionsGenerator exists" : "❌ Missing";'
"""
)

test.collect_observation("La classe MentionsGenerator existe-t-elle ?")

test.print_substep("7.2", "Vérifier instantiation")
test.ssh_command(
    """
ddev wp eval 'use WcQualiopiFormation\\Form\\MentionsLegales\\MentionsGenerator; use WcQualiopiFormation\\Utils\\Logger; $logger = new Logger(); $mentions = new MentionsGenerator($logger); echo "✅ MentionsGenerator instantiated successfully";'
"""
)

test.collect_observation("MentionsGenerator peut-elle être instanciée sans erreur ?")

# ========================================
# PHASE 8 : Vérifier les constantes
# ========================================
test.print_phase(8, "Vérifier les constantes du plugin")

test.ssh_command(
    """
ddev wp eval 'use WcQualiopiFormation\\Core\\Constants; echo "TEXT_DOMAIN: " . Constants::TEXT_DOMAIN . "\\n"; echo "TABLE_TRACKING: " . Constants::TABLE_TRACKING . "\\n"; echo "API_SIREN_BASE_URL: " . Constants::API_SIREN_BASE_URL;'
"""
)

test.collect_observation(
    "Les constantes sont-elles correctement définies ? (TEXT_DOMAIN, TABLE_TRACKING, API_SIREN_BASE_URL)"
)

# ========================================
# PHASE 9 : Test Logger
# ========================================
test.print_phase(9, "Tester le système de logging")

test.ssh_command(
    """
ddev wp eval 'use WcQualiopiFormation\\Utils\\Logger; $logger = new Logger(); $logger->info("Test non-regression", ["test_id" => "E2E_003"]); echo "✅ Log written successfully";'
"""
)

test.collect_observation("Le logger peut-il écrire un log sans erreur ?")

# ========================================
# PHASE 10 : Résumé des modules
# ========================================
test.print_phase(10, "Résumé de tous les modules chargés")

test.print_instruction("Vérifions que tous les modules principaux sont bien chargés")

test.ssh_command(
    """
ddev wp eval '
$modules = [
    "CartGuard" => "WcQualiopiFormation\\\\Cart\\\\CartGuard",
    "CartRestriction" => "WcQualiopiFormation\\\\Cart\\\\CartRestriction",
    "FormManager" => "WcQualiopiFormation\\\\Form\\\\FormManager",
    "SirenAutocomplete" => "WcQualiopiFormation\\\\Form\\\\Siren\\\\SirenAutocomplete",
    "MentionsGenerator" => "WcQualiopiFormation\\\\Form\\\\MentionsLegales\\\\MentionsGenerator",
    "TrackingManager" => "WcQualiopiFormation\\\\Form\\\\Tracking\\\\TrackingManager",
    "DataExtractor" => "WcQualiopiFormation\\\\Form\\\\Tracking\\\\DataExtractor",
    "TokenManager" => "WcQualiopiFormation\\\\Security\\\\TokenManager",
    "SessionManager" => "WcQualiopiFormation\\\\Security\\\\SessionManager",
    "Logger" => "WcQualiopiFormation\\\\Utils\\\\Logger"
];

foreach ($modules as $name => $class) {
    $status = class_exists($class) ? "✅" : "❌";
    echo "{$status} {$name}\\n";
}
'
"""
)

test.collect_observation(
    "Tous les modules principaux sont-ils présents ? (Tous doivent avoir ✅)"
)

# ========================================
# GÉNÉRATION RAPPORT
# ========================================
test.generate_report()

