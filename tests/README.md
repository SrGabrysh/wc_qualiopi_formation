# 🧪 Tests - wc_qualiopi_formation

## 📚 Documentation

**Documentation complète disponible :**

→ **[Environnement de Test](docs/Environnement%20de%20Test.md)** - Tout savoir sur Brain Monkey, Pest, Mockery  
→ **[README-QUICK-FILL](docs/README-QUICK-FILL.md)** - Scripts de pré-remplissage pour tests

---

## ⚡ Commandes rapides

### Tests unitaires (rapides, mocks)

```powershell
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest'"
```

### Tests d'intégration (WordPress réel)

```powershell
wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest --configuration=phpunit-integration.xml'"
```

---

## 📂 Structure

```
wc_qualiopi_formation/
├── Pest.php                      ← Bootstrap tests unitaires (Brain Monkey + Mockery)
├── phpunit.xml                   ← Config tests unitaires
├── phpunit-integration.xml       ← Config tests intégration
└── tests/
    ├── docs/                     ← 📚 Documentation complète
    │   ├── Environnement de Test.md
    │   └── README-QUICK-FILL.md
    ├── Unit/                     ← Tests unitaires (mocks, rapides)
    │   └── ExampleTest.php       ← ✅ Référence syntaxe validée (13 tests)
    ├── Integration/              ← Tests intégration (WordPress réel)
    │   ├── Data/
    │   ├── Form/
    │   ├── REST/
    │   └── ExampleIntegrationTest.php
    ├── E2E/                      ← Tests End-to-End (Python)
    └── bootstrap-integration.php ← Bootstrap intégration
```
