# 🏗️ Architecture des Corrections de Sécurité - 7 octobre 2025

## 📊 Diagramme des Corrections Appliquées

```
┌─────────────────────────────────────────────────────────────────┐
│                    CORRECTIONS DE SÉCURITÉ                     │
│                        Version 1.0.2                           │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   BLOQUANT #1   │    │   BLOQUANT #2   │    │   WARNING #1    │
│                 │    │                 │    │                 │
│ AdminManager    │    │ Plugin.php      │    │ Constants.php   │
│ CSRF Protection │    │ Suppression     │    │ Versioning      │
│                 │    │ Clé Hardcodée   │    │ Unification     │
│ ✅ check_admin_ │    │                 │    │                 │
│    referer()    │    │ ✅ Migration    │    │ ✅ WCQF_VERSION │
│ ✅ current_user │    │    supprimée    │    │    = SSOT       │
│    _can()       │    │ ✅ Notice admin │    │ ✅ Constants::  │
│ ✅ Filtres      │    │    informative  │    │    VERSION      │
│    compat       │    │                 │    │    aligné       │
└─────────────────┘    └─────────────────┘    └─────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WARNING #2    │    │   WARNING #3    │    │   WARNING #4    │
│                 │    │                 │    │                 │
│ Constants.php   │    │ SecurityHelper  │    │ CartGuard.php   │
│ Text Domain     │    │ + AdminManager  │    │ Store API       │
│ Unification     │    │ Capabilities    │    │ Checkout Fix    │
│                 │    │                 │    │                 │
│ ✅ TEXT_DOMAIN  │    │ ✅ CAP_MANAGE_  │    │ ✅ Retrait      │
│    = 'wcqf'     │    │    SETTINGS     │    │    current_user │
│ ✅ Remplacement │    │ ✅ Filtres      │    │    _can('read') │
│    dans tous    │    │    compat       │    │ ✅ should_block │
│    les fichiers │    │ ✅ TrackingMgr  │    │    _checkout()  │
│                 │    │ ✅ CartGuard    │    │    uniquement   │
└─────────────────┘    └─────────────────┘    └─────────────────┘

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WARNING #5    │    │   TESTS         │    │   VALIDATION    │
│                 │    │                 │    │                 │
│ ProgressStorage │    │ Security        │    │ Scripts +       │
│ Permissions     │    │ Corrections     │    │ Documentation   │
│                 │    │ Test.php        │    │                 │
│ ✅ current_user │    │                 │    │ ✅ Tests        │
│    _can()       │    │ ✅ CSRF tests   │    │    unitaires    │
│    amélioré     │    │ ✅ Capabilities │    │ ✅ Script       │
│ ✅ CAP_MANAGE_  │    │ ✅ Store API    │    │    validation   │
│    SETTINGS     │    │ ✅ Versioning   │    │ ✅ Documentation│
│                 │    │ ✅ Text domain  │    │    complète     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🔄 Flux de Sécurité Avant/Après

### AVANT (Vulnérable)

```
Admin Export Request
        ↓
❌ Pas de vérification nonce
❌ Pas de vérification capability
        ↓
Export direct des logs
        ↓
🚨 Risque CSRF
```

### APRÈS (Sécurisé)

```
Admin Export Request
        ↓
✅ check_admin_referer('wcqf_admin_action')
✅ current_user_can(Constants::CAP_MANAGE_SETTINGS)
        ↓
Logs de sécurité
        ↓
Export autorisé ou rejeté
```

## 🏛️ Architecture des Modules Corrigés

```
┌─────────────────────────────────────────────────────────────────┐
│                        PLUGIN CORE                             │
├─────────────────────────────────────────────────────────────────┤
│  Plugin.php                                                     │
│  ├─ ✅ migrate_api_keys() - Suppression clé hardcodée          │
│  └─ ✅ Versioning unifié (WCQF_VERSION)                        │
│                                                                 │
│  Constants.php                                                  │
│  ├─ ✅ VERSION = '1.0.1' (aligné)                              │
│  ├─ ✅ TEXT_DOMAIN = 'wcqf' (unifié)                           │
│  └─ ✅ CAP_MANAGE_SETTINGS = 'manage_woocommerce'              │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        ADMIN MODULE                            │
├─────────────────────────────────────────────────────────────────┤
│  AdminManager.php                                               │
│  ├─ ✅ handle_export_actions() - CSRF protection               │
│  ├─ ✅ handle_early_actions() - CSRF protection                │
│  ├─ ✅ add_admin_menu() - Capabilities alignées                │
│  └─ ✅ Assets versioning (WCQF_VERSION)                        │
│                                                                 │
│  SecurityHelper.php                                             │
│  ├─ ✅ check_admin_capability() - Default Constants::CAP       │
│  └─ ✅ Filtres de compatibilité                                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        CART MODULE                             │
├─────────────────────────────────────────────────────────────────┤
│  CartGuard.php                                                  │
│  ├─ ✅ intercept_store_api_checkout() - Retrait current_user   │
│  ├─ ✅ force_test_validation() - Capabilities alignées         │
│  └─ ✅ Store API sans blocage invités                          │
│                                                                 │
│  ProgressStorage.php                                            │
│  ├─ ✅ start() - Permissions améliorées                        │
│  └─ ✅ CAP_MANAGE_SETTINGS pour admin                          │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        FORM MODULE                             │
├─────────────────────────────────────────────────────────────────┤
│  TrackingManager.php                                            │
│  ├─ ✅ get_stats() - Capabilities alignées                     │
│  └─ ✅ Constants::CAP_MANAGE_SETTINGS                          │
│                                                                 │
│  FieldInjector.php                                              │
│  └─ ✅ Assets versioning (WCQF_VERSION)                        │
└─────────────────────────────────────────────────────────────────┘
```

## 🔐 Matrice de Sécurité

| Composant        | Avant             | Après                 | Protection      |
| ---------------- | ----------------- | --------------------- | --------------- |
| **Admin Export** | ❌ CSRF           | ✅ Nonce + Capability | CSRF + Auth     |
| **API Keys**     | ❌ Hardcodée      | ✅ SecretManager      | Confidentialité |
| **Store API**    | ❌ Bloque invités | ✅ Logique métier     | Fonctionnalité  |
| **Capabilities** | ❌ Hétérogènes    | ✅ Unifiées + Filtres | Cohérence       |
| **Versioning**   | ❌ Incohérent     | ✅ SSOT               | Maintenance     |
| **Text Domain**  | ❌ Mixte          | ✅ Unifié             | i18n            |

## 🎯 Points de Contrôle

### 1. Validation CSRF

```php
// Avant
$_POST['wcqf_logs_action'] // ❌ Pas de vérification

// Après
check_admin_referer('wcqf_admin_action', '_wpnonce') // ✅ CSRF
current_user_can(Constants::CAP_MANAGE_SETTINGS)     // ✅ Auth
```

### 2. Gestion des Secrets

```php
// Avant
$hardcoded_key = 'FlwM9Symg1SIox2WYRSN2vhRmCCwRXal'; // ❌ Exposé

// Après
SecretManager::get('SECRET_NAME') // ✅ Sécurisé
ApiKeyManager::get_api_key('provider') // ✅ Chiffré
```

### 3. Store API Checkout

```php
// Avant
if (!current_user_can('read')) { // ❌ Bloque invités
    return new WP_Error('unauthorized');
}

// Après
if ($this->cart_blocker->should_block_checkout()) { // ✅ Logique métier
    return new WP_Error('checkout_blocked');
}
```

## 🚀 Impact sur l'Architecture

### Améliorations

- **Sécurité** : CSRF, secrets, permissions
- **Cohérence** : Versioning, text domain, capabilities
- **Maintenabilité** : Tests, documentation, validation
- **Compatibilité** : Filtres WordPress, hooks

### Prérequis

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 8.1+

### Dépendances

- SecretManager pour clés API
- Constants pour cohérence
- Tests unitaires pour validation

---

**✅ Architecture sécurisée et cohérente après corrections**
