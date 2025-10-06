# 🧪 Tests WC Qualiopi Formation

Documentation et guide d'utilisation des tests du plugin.

## 📁 Structure

```
tests/
├── Unit/                ← Tests unitaires (Pest PHP)
│   ├── Security/Token/  ← Tests pour TokenGenerator, etc.
│   ├── Data/Progress/   ← Tests pour ProgressValidator, etc.
│   ├── Cart/Helpers/    ← Tests pour PageDetector, UrlGenerator
│   └── Utils/Mapping/   ← Tests pour MappingCache
├── E2E/                 ← Tests End-to-End (Python)
│   ├── scripts/         ← Scripts de tests E2E
│   ├── reports/         ← Rapports générés automatiquement
│   └── helpers/         ← Framework E2E réutilisable
├── Fixtures/            ← Données de test (JSON)
└── Bootstrap/           ← Configuration tests unitaires
    └── bootstrap.php    ← Mock fonctions WordPress
```

## 🚀 Tests Unitaires (Pest PHP)

### Installation

Pest est déjà installé via Composer. Si vous devez réinstaller :

```bash
cd Plugins/wc_qualiopi_formation
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev composer require --dev pestphp/pest --working-dir=web/wp-content/plugins/wc_qualiopi_formation"
```

### Exécution

```bash
# Tous les tests
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest'"

# Tests spécifiques
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest tests/Unit/Security/'"

# Avec couverture de code
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest --coverage'"

# Mode verbose
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest -v'"
```

### Écrire un nouveau test

Créer un fichier dans `tests/Unit/<Module>/<NomTest>.php` :

```php
<?php

use WcQualiopiFormation\<Namespace>\<Classe>;

describe('<Classe>', function () {

    it('fait quelque chose', function () {
        $result = Classe::method();
        expect($result)->toBe('expected');
    });

});
```

## 🌍 Tests E2E (Python)

### Prérequis

- Python 3.8+
- WSL2 + DDEV configuré
- Plugin `wc_qualiopi_formation` installé sur DDEV

### Exécution

```bash
# Depuis le répertoire du projet (Windows)
cd "E:\Mon Drive\00 - Dev\01 - Codes\Sites web\TB-Formation\dev_plugin_wc_qualiopi_steps"

# Exécuter un test E2E
python Plugins/wc_qualiopi_formation/tests/E2E/scripts/E2E_001_cart_guard_workflow.py
```

### Workflow d'un Test E2E

1. **Instructions utilisateur** : Le script affiche ce que vous devez faire
2. **Vérifications automatiques** : Le script exécute des commandes SSH/WP-CLI
3. **JavaScript à tester** : Copier-coller dans la console du navigateur
4. **Observations** : Répondre aux questions posées
5. **Rapport final** : Généré automatiquement dans `tests/E2E/reports/`

### Créer un nouveau test E2E

```python
#!/usr/bin/env python3
import sys
import os
sys.path.append(os.path.join(os.path.dirname(__file__), ".."))
from helpers.test_framework import E2ETestFramework

class MonTest(E2ETestFramework):
    def __init__(self):
        super().__init__(
            test_id="E2E_XXX",
            test_name="Nom du test",
            description="Description"
        )

    def phase_1(self):
        self.print_phase("Ma Phase 1")
        self.print_instruction("Faites ceci...", "Puis cela...")
        self.verify_ssh("Vérif", "ddev wp ...")
        self.wait_user_confirmation("Phase OK ?")

    def run(self):
        self.phase_1()
        # ... autres phases
        self.generate_report()

if __name__ == "__main__":
    test = MonTest()
    test.run()
```

## 📊 Tests Disponibles

### Tests Unitaires

| Module       | Fichier                    | Tests | Description                  |
| ------------ | -------------------------- | ----- | ---------------------------- |
| **Security** | `TokenGeneratorTest.php`   | 20    | Génération/validation tokens |
| _À venir_    | `SessionValidatorTest.php` | -     | Validation sessions          |
| _À venir_    | `SecretValidatorTest.php`  | -     | Validation secrets           |
| **Cart**     | _À venir_                  | -     | Tests logique cart           |

**Total actuel** : **20 tests unitaires**

### Tests E2E

| ID      | Script                   | Description                         | Statut   |
| ------- | ------------------------ | ----------------------------------- | -------- |
| E2E_001 | `cart_guard_workflow.py` | Workflow blocage/déblocage checkout | ✅ Créé  |
| E2E_002 | _À venir_                | Limitation 1 produit max            | 📝 Prévu |
| E2E_003 | _À venir_                | Persistance sessions                | 📝 Prévu |

## 🐛 Débogage

### Tests Unitaires

```php
// Utiliser dd() pour dump and die
it('test debug', function () {
    $value = quelque_chose();
    dd($value); // Affiche et arrête
});

// Ou dump() pour continuer
it('test debug continue', function () {
    dump($value1);
    dump($value2);
    expect($value1)->toBe($value2);
});
```

### Tests E2E

Activer le mode debug dans le constructeur :

```python
def __init__(self):
    super().__init__(...)
    self.debug_mode = True  # Active les snapshots debug
```

## 📈 Résultats & Rapports

### Tests Unitaires

Les résultats s'affichent dans le terminal :

```
PASS  Tests\Unit\Security\Token\TokenGeneratorTest
✓ generates a valid token with all components
✓ can parse a valid token into components
...

Tests:  20 passed
Time:   0.52s
```

### Tests E2E

Les rapports sont générés dans `tests/E2E/reports/` au format Markdown :

```
E2E_001_20251003_193045.md
```

Contenu : Phases, observations, logs, recommandations, taux de succès.

## ✅ Checklist avant Commit

- [ ] Tests unitaires passent (`./vendor/bin/pest`)
- [ ] Tests E2E critiques passent (E2E_001)
- [ ] Pas de linter errors
- [ ] Documentation à jour si API changée

## 📚 Documentation Complète

Voir `Documentation/STRATEGIE_TESTS_WC_QUALIOPI_FORMATION.md` pour :

- Stratégie globale
- Roadmap des tests
- Guide de maintenance
- Intégration CI/CD (futur)

---

**Dernière mise à jour** : 3 octobre 2025
