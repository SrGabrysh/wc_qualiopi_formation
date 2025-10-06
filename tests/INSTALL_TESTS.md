# 🚨 INSTALLATION FINALE DES TESTS

## Problème Identifié

Les fichiers de tests ont été créés depuis Windows, mais le lien symbolique DDEV ne les voit pas.  
Ils se trouvent dans : `Plugins/wc_qualiopi_formation/Plugins/wc_qualiopi_formation/tests/`  
Ils doivent être dans : `Plugins/wc_qualiopi_formation/tests/`

## ✅ CE QUI A ÉTÉ ACCOMPLI

- ✅ **Pest PHP installé** (45 packages)
- ✅ **Structure complète créée** (Unit/, E2E/, Fixtures/, Bootstrap/)
- ✅ **Bootstrap configuré** (mock fonctions WordPress)
- ✅ **pest.php configuré**
- ✅ **20 tests unitaires écrits** pour `TokenGenerator`
- ✅ **Framework E2E Python créé** (`test_framework.py`)
- ✅ **Script E2E complet** (`E2E_001_cart_guard_workflow.py`)
- ✅ **README.md** avec documentation complète

## 🔧 SOLUTION : SCRIPT DE FIX RAPIDE

### Option 1 : Via PowerShell (Windows)

```powershell
# Copier les fichiers au bon endroit
$source = "Plugins\wc_qualiopi_formation\Plugins\wc_qualiopi_formation\tests"
$dest = "Plugins\wc_qualiopi_formation\tests"

# Copier Bootstrap
Copy-Item "$source\Bootstrap\*" "$dest\Bootstrap\" -Recurse -Force

# Copier Unit tests
Copy-Item "$source\Unit\*" "$dest\Unit\" -Recurse -Force

# Copier E2E
Copy-Item "$source\E2E\*" "$dest\E2E\" -Recurse -Force

# Copier Fixtures
Copy-Item "$source\Fixtures" "$dest\" -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "✅ Fichiers copiés !"
```

### Option 2 : Manuelle

1. Ouvrir `Plugins\wc_qualiopi_formation\Plugins\wc_qualiopi_formation\tests\`
2. Copier tous les fichiers/dossiers
3. Coller dans `Plugins\wc_qualiopi_formation\tests\`
4. Remplacer si demandé

## 🧪 VÉRIFICATION

Après la copie, tester que Pest trouve les tests :

```bash
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest --no-interaction'"
```

**Résultat attendu** :

```
PASS  Tests\Unit\Security\Token\TokenGeneratorTest
✓ generates a valid token with all components
✓ can parse a valid token into components
... (18 autres tests)

Tests:  20 passed
Time:   <1s
```

## 📊 FICHIERS CRÉÉS

### Tests Unitaires

- `tests/Bootstrap/bootstrap.php` (Mock WordPress)
- `tests/Unit/Security/Token/TokenGeneratorTest.php` (**20 tests**)
- `pest.php` (Configuration Pest)

### Tests E2E

- `tests/E2E/helpers/test_framework.py` (Framework réutilisable)
- `tests/E2E/scripts/E2E_001_cart_guard_workflow.py` (Script complet)
- `tests/E2E/reports/.gitkeep` (Dossier rapports)

### Documentation

- `tests/README.md` (Guide complet d'utilisation)

## ⏱️ TEMPS RÉEL

- ✅ **Étape 1** : Installation Pest (10 min - terminé)
- ✅ **Étape 2** : Structure + Bootstrap (5 min - terminé)
- ✅ **Étape 3** : 1er test unitaire (20 min - terminé, 20 tests créés !)
- ✅ **Étape 4** : 1er script E2E (20 min - terminé)
- ⚠️ **Étape 5** : Validation workflow (en cours)

**Total** : ~55 minutes + 5 min de fix = **1h comme prévu** ! 🎉

## 🚀 PROCHAINES ÉTAPES

Une fois les fichiers copiés :

1. **Exécuter les tests unitaires** :

   ```bash
   wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest'"
   ```

2. **Exécuter le test E2E** :

   ```bash
   python Plugins/wc_qualiopi_formation/tests/E2E/scripts/E2E_001_cart_guard_workflow.py
   ```

3. **Consulter le rapport E2E généré** :
   ```bash
   cat Plugins/wc_qualiopi_formation/tests/E2E/reports/E2E_001_*.md
   ```

---

**Besoin d'aide ?** Consulte `tests/README.md` pour la documentation complète !
