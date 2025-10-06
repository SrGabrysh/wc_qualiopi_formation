#!/usr/bin/env python3
"""
Test E2E : Migration des données
===============================

Vérifie que la migration des données depuis les anciens plugins fonctionne correctement.

Modules testés :
- DataMigrator
- Migration wc_qualiopi_steps
- Migration gravity_forms_siren_autocomplete
"""

import sys
from pathlib import Path

# Import framework
test_framework_path = Path(__file__).parent.parent / "helpers"
sys.path.insert(0, str(test_framework_path))

from test_framework import E2ETestFramework

# Configuration
config = {
    "test_name": "Data Migration Test",
    "test_id": "E2E_002",
    "estimated_duration": "10 min",
    "modules_tested": ["DataMigrator", "wc_qualiopi_steps → wcqf", "gf_siren → wcqf"],
}

# Créer framework
test = E2ETestFramework(config)

# ========================================
# PHASE 1 : Préparer données de test
# ========================================
test.print_phase(1, "Préparation données de test")

test.print_instruction(
    "Nous allons créer des données de test pour simuler les anciens plugins"
)

# Créer options de test pour wc_qualiopi_steps
test.print_substep("1.1", "Créer options wc_qualiopi_steps de test")
test.ssh_command(
    """
ddev wp option add wcqs_flags '{"enforce_cart": true, "enable_logging": true}' --format=json
"""
)

test.ssh_command(
    """
ddev wp option add wcqs_testpos_mapping '{"4017": 4267}' --format=json
"""
)

test.ssh_command(
    """
ddev wp option add wcqs_hmac_secret 'test_secret_key_32_chars_long__' --autoload=no
"""
)

# Créer options de test pour gravity_forms_siren_autocomplete
test.print_substep("1.2", "Créer options gravity_forms_siren_autocomplete de test")
test.ssh_command(
    """
ddev wp option add gf_siren_settings '{"cache_duration": 86400, "form_mappings": {"1": {"siret": "1"}}, "tracked_forms": [1]}' --format=json
"""
)

test.ssh_command(
    """
ddev wp option add gf_siren_api_key 'test_api_key_1234567890' --autoload=no
"""
)

test.collect_observation(
    "Les options de test ont-elles été créées avec succès ? (Vérifier sortie SSH ci-dessus)"
)

# ========================================
# PHASE 2 : Réinitialiser migration
# ========================================
test.print_phase(2, "Réinitialiser migration (pour pouvoir tester)")

test.print_instruction("Supprimer le flag de migration complétée")

test.ssh_command(
    """
ddev wp option delete wcqf_migration_completed
"""
)

test.ssh_command(
    """
ddev wp option delete wcqf_migration_date
"""
)

test.collect_observation("Les flags de migration ont-ils été supprimés ?")

# ========================================
# PHASE 3 : Exécuter migration
# ========================================
test.print_phase(3, "Exécuter la migration")

test.print_instruction(
    "La migration s'exécutera automatiquement lors de l'activation du plugin"
)

test.ssh_command(
    """
ddev wp plugin deactivate wc_qualiopi_formation
"""
)

test.ssh_command(
    """
ddev wp plugin activate wc_qualiopi_formation
"""
)

test.collect_observation(
    "L'activation du plugin a-t-elle réussi sans erreur ? (Vérifier messages d'erreur)"
)

# ========================================
# PHASE 4 : Vérifier migration options wcqs
# ========================================
test.print_phase(4, "Vérifier migration options wc_qualiopi_steps")

test.print_substep("4.1", "Vérifier wcqs_flags → wcqf_flags")
test.ssh_command(
    """
ddev wp option get wcqf_flags --format=json
"""
)

test.collect_observation(
    'Les flags ont-ils été migrés correctement ? (Doit contenir "enforce_cart": true)'
)

test.print_substep("4.2", "Vérifier wcqs_testpos_mapping → wcqf_testpos_mapping")
test.ssh_command(
    """
ddev wp option get wcqf_testpos_mapping --format=json
"""
)

test.collect_observation(
    'Le mapping a-t-il été migré correctement ? (Doit contenir "4017": 4267)'
)

test.print_substep("4.3", "Vérifier wcqs_hmac_secret → wcqf_hmac_secret")
test.ssh_command(
    """
ddev wp option get wcqf_hmac_secret
"""
)

test.collect_observation(
    "Le secret HMAC a-t-il été migré correctement ? (Ne doit PAS être vide)"
)

# ========================================
# PHASE 5 : Vérifier migration options gfsa
# ========================================
test.print_phase(5, "Vérifier migration options gravity_forms_siren")

test.print_substep("5.1", "Vérifier gf_siren_settings → wcqf_form_settings")
test.ssh_command(
    """
ddev wp option get wcqf_form_settings --format=json
"""
)

test.collect_observation(
    "Les settings ont-ils été migrés ? (Doit contenir form_mappings et tracked_forms)"
)

test.print_substep("5.2", "Vérifier gf_siren_api_key → wcqf_siren_api_key")
test.ssh_command(
    """
ddev wp option get wcqf_siren_api_key
"""
)

test.collect_observation("La clé API a-t-elle été migrée ? (Ne doit PAS être vide)")

# ========================================
# PHASE 6 : Vérifier flag migration complétée
# ========================================
test.print_phase(6, "Vérifier que la migration est marquée comme complétée")

test.ssh_command(
    """
ddev wp option get wcqf_migration_completed
"""
)

test.collect_observation("La migration est-elle marquée comme complétée ? (Doit être '1')")

test.ssh_command(
    """
ddev wp option get wcqf_migration_date
"""
)

test.collect_observation(
    "La date de migration est-elle enregistrée ? (Format : YYYY-MM-DD HH:MM:SS)"
)

# ========================================
# PHASE 7 : Vérifier que les anciennes options existent toujours
# ========================================
test.print_phase(7, "Vérifier préservation des anciennes options")

test.print_instruction(
    "Les anciennes options ne doivent PAS être supprimées (rollback possible)"
)

test.ssh_command(
    """
ddev wp option get wcqs_flags --format=json
"""
)

test.collect_observation(
    "L'ancienne option wcqs_flags existe-t-elle toujours ? (Doit être présente)"
)

test.ssh_command(
    """
ddev wp option get gf_siren_settings --format=json
"""
)

test.collect_observation(
    "L'ancienne option gf_siren_settings existe-t-elle toujours ? (Doit être présente)"
)

# ========================================
# PHASE 8 : Nettoyage
# ========================================
test.print_phase(8, "Nettoyage des données de test")

test.print_instruction("Supprimer toutes les options de test créées")

test.ssh_command(
    """
ddev wp option delete wcqs_flags wcqs_testpos_mapping wcqs_hmac_secret wcqf_flags wcqf_testpos_mapping wcqf_hmac_secret gf_siren_settings gf_siren_api_key wcqf_form_settings wcqf_siren_api_key wcqf_migration_completed wcqf_migration_date
"""
)

test.collect_observation("Toutes les options de test ont-elles été supprimées ?")

# ========================================
# GÉNÉRATION RAPPORT
# ========================================
test.generate_report()

