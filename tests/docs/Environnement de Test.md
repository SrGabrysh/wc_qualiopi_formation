# Environnement de Test - wc_qualiopi_formation

**Framework** : Pest PHP + Brain Monkey + Mockery  
**Date** : 10 octobre 2025

---

## Infrastructure

### Fichiers de configuration

- `Pest.php` (226 lignes) - Bootstrap complet Brain Monkey + Mockery
- `patchwork.json` - Configuration fonctions PHP natives mockables
- `phpunit.xml` - Configuration tests unitaires
- `phpunit-integration.xml` - Configuration tests intégration

### Dépendances installées

- **Pest PHP** 2.x - Framework de tests
- **Brain Monkey** 2.6.2 - Mocks fonctions WordPress
- **Mockery** 1.6.12 - Mocks classes PHP
- **PHPUnit** 9.5 - Moteur sous-jacent

---

## Pest.php

**Rôle** : Bootstrap automatique pour tous les tests.

**Contenu** :

- Constantes WordPress + Plugin
- Brain Monkey `setUp()` dans `beforeEach()`
- **50+ mocks WordPress automatiques** (sanitize, nonces, options, hooks, etc.)
- Mockery `close()` dans `afterEach()`
- 3 helpers globaux : `mockWpdb()`, `mockWpError()`, `isWpError()`

**Utilisation** : Automatique, rien à faire. Toutes les fonctions WordPress courantes sont déjà mockées.

**Note** : Les classes WC/GF ne sont PAS mockées globalement (pour éviter conflits avec tests d'intégration). Utiliser Mockery au besoin.

---

## patchwork.json

**Rôle** : Permet à Brain Monkey de mocker les fonctions PHP natives.

**Fonctions mockables** :

- `class_exists`, `function_exists`, `method_exists`
- `time`, `date`
- `interface_exists`, `trait_exists`, `is_callable`

**Utilisation** : Automatique.

---

## Brain Monkey

**Usage** : Mocker les fonctions WordPress.

**Mocks automatiques** (déjà configurés dans `Pest.php`) :

- Sanitization : `sanitize_text_field`, `esc_html`, `esc_attr`, `esc_url`, etc.
- Nonces : `wp_verify_nonce`, `wp_create_nonce`
- Permissions : `is_admin`, `current_user_can`
- Options : `get_option`, `update_option`, `delete_option`, `add_option`
- Hooks : `add_action`, `add_filter`, `do_action`, `apply_filters`
- Time : `current_time`
- Translations : `__`, `_e`, `esc_html__`, `esc_attr__`

**Syntaxe override** (si besoin de remplacer un mock) :

```php
use Brain\Monkey\Functions;

Functions\when('get_option')->justReturn('custom_value');
Functions\when('current_user_can')->justReturn(false);
```

**Référence** : `tests/Unit/ExampleTest.php` (13 tests validés).

---

## Mockery

**Usage** : Mocker les classes PHP.

**Syntaxe** :

```php
use Mockery;

$mock = Mockery::mock(MaClasse::class);
$mock->shouldReceive('method')->once()->andReturn(true);
```

**Note** : `Mockery::close()` est géré automatiquement dans `Pest.php`.

---

## Classes WC/GF - Mocking Manuel

**Les classes WooCommerce et Gravity Forms ne sont PAS mockées automatiquement** pour éviter les conflits avec les tests d'intégration.

**Pour les tests unitaires qui en ont besoin, utiliser Mockery** :

```php
use Mockery;

// Mock Gravity Forms
$gfMock = Mockery::mock('overload:GFFormsModel');
$gfMock->shouldReceive('get_current_lead')->andReturn([]);
$gfMock->shouldReceive('get_form_meta')->with(123)->andReturn(['title' => 'Test Form']);

// Mock WooCommerce Product
$productMock = Mockery::mock('WC_Product');
$productMock->shouldReceive('get_id')->andReturn(1);
$productMock->shouldReceive('get_name')->andReturn('Test Product');
$productMock->shouldReceive('get_price')->andReturn('99.99');

// Mock WooCommerce Order
$orderMock = Mockery::mock('WC_Order');
$orderMock->shouldReceive('get_id')->andReturn(1);
$orderMock->shouldReceive('get_total')->andReturn('99.99');
```

**Pour les tests d'intégration** : Les vraies classes WC/GF sont chargées automatiquement par WordPress.

---

## Helpers Globaux

**Définis dans Pest.php, disponibles partout** :

```php
// Mock wpdb
$wpdb = mockWpdb();
// Retourne: stdClass avec prefix, tables, etc.

// Mock WP_Error
$error = mockWpError('code', 'message');

// Vérifier WP_Error
if (isWpError($result)) { ... }
```

---

## ExampleTest.php

**Rôle** : 13 tests de référence montrant la syntaxe Brain Monkey validée.

**Contenu** :

- Mocks simples : `justReturn()`, `returnArg()`, `alias()`
- Mocks fonctions WordPress : `get_option`, `wp_verify_nonce`, `add_action`, etc.
- Tests helpers globaux : `mockWpdb()`, `mockWpError()`, `isWpError()`

**Utilisation** : **Copier cette syntaxe exactement** pour nouveaux tests.

---

## Structure Tests

```
tests/
├── Unit/              ← Brain Monkey (mocks WordPress)
│   └── ExampleTest.php  ← RÉFÉRENCE SYNTAXE
├── Integration/       ← WordPress réel chargé
└── docs/
    ├── ENVIRONNEMENT.md   ← Ce fichier
    └── README-QUICK-FILL.md
```

---

## Workflow Création Test

1. Copier la syntaxe de `ExampleTest.php`
2. Créer `tests/Unit/[Module]/[Classe]Test.php`
3. **50+ fonctions WordPress déjà mockées** → pas besoin de les configurer
4. Override un mock si besoin : `Functions\when('get_option')->justReturn('value')`
5. Mocker classes PHP avec Mockery (WC/GF non incluses par défaut)
6. Sync : `.\dev-tools\sync\Sync-ToDDEV-Ameliore.ps1`
7. Test : `ddev exec 'vendor/bin/pest tests/Unit'`

### Commandes

```bash
# Tests unitaires (rapides, mocks)
ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && vendor/bin/pest tests/Unit'

# Tests d'intégration (WordPress réel)
ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && vendor/bin/pest --configuration=phpunit-integration.xml'

# Tous les tests
ddev exec 'cd web/wp-content/plugins/wc_qualiopi_formation && composer test:all'
```

---

**L'essentiel pour démarrer rapidement.**
