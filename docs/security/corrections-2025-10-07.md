# 🔒 Corrections de Sécurité - 7 octobre 2025

**Version** : 1.0.2  
**Statut** : ✅ COMPLÉTÉ  
**Impact** : Sécurité renforcée + Cohérence améliorée

---

## 📋 Résumé des Corrections

### 🚨 Problèmes Bloquants Corrigés

#### 1. Vulnérabilité CSRF dans AdminManager

- **Problème** : `AdminManager::handle_export_actions()` lisait `$_POST` sans vérification nonce/capabilities
- **Risque** : POST forgé côté admin → exfiltration de logs
- **Correction** :
  - Ajout de `check_admin_referer('wcqf_admin_action', '_wpnonce')`
  - Vérification `current_user_can(Constants::CAP_MANAGE_SETTINGS)`
  - Logs de sécurité pour traçabilité
  - Filtres pour compatibilité descendante

#### 2. Clé API Hardcodée Supprimée

- **Problème** : `Plugin::migrate_api_keys()` contenait une clé SIREN en dur
- **Risque** : Divulgation de secret en code source
- **Correction** :
  - Suppression complète de la clé hardcodée
  - Migration vers `SecretManager`/`ApiKeyManager` exclusivement
  - Notice admin informative pour la nouvelle approche

---

### ⚠️ Problèmes Warning Corrigés

#### 3. Unification du Versioning

- **Problème** : `WCQF_VERSION=1.0.1` vs `Constants::VERSION=1.0.0`
- **Correction** :
  - `WCQF_VERSION` comme source unique de vérité (SSOT)
  - `Constants::VERSION` aligné sur `1.0.1` et marqué `@deprecated`
  - Remplacement progressif des usages par `WCQF_VERSION`

#### 4. Unification du Text Domain

- **Problème** : Header `wcqf` vs `Constants::TEXT_DOMAIN` `wc-qualiopi-formation`
- **Correction** :
  - Unification sur `'wcqf'` partout
  - Mise à jour de `Constants::TEXT_DOMAIN`
  - Remplacement des appels existants

#### 5. Alignement des Capabilities Admin

- **Problème** : `AdminManager` utilisait `manage_options` au lieu de `Constants::CAP_MANAGE_SETTINGS`
- **Correction** :
  - Alignement sur `Constants::CAP_MANAGE_SETTINGS` (`manage_woocommerce`)
  - Ajout de filtres pour compatibilité descendante
  - Correction dans `SecurityHelper`, `TrackingManager`, `CartGuard`

#### 6. Store API Checkout - Retrait Vérification Capability

- **Problème** : `current_user_can('read')` bloquait les invités sur checkout POST
- **Correction** :
  - Retrait de la vérification capability
  - Conservation de la logique `should_block_checkout()` uniquement
  - Correction dans `ProgressStorage` également

---

## 🧪 Tests de Validation

### Tests Unitaires Créés

- **Fichier** : `tests/Unit/Security/SecurityCorrectionsTest.php`
- **Couverture** :
  - Protection CSRF AdminManager
  - Alignement capabilities
  - Store API checkout (avec/sans blocage)
  - Cohérence versioning et text domain
  - Suppression migration clés hardcodées

### Tests Manuels Requis

#### 1. Interface Admin

```bash
# Tester l'export de logs avec/sans nonce
curl -X POST "https://site.local/wp-admin/options-general.php?page=wcqf-settings&tab=logs" \
  -d "wcqf_logs_action=export_logs" \
  -d "_wpnonce=INVALID_NONCE"
# Devrait retourner "Token de sécurité invalide"
```

#### 2. Checkout WooCommerce Blocks

```bash
# Tester checkout en tant qu'invité
# 1. Ajouter produit au panier
# 2. Aller sur checkout
# 3. Vérifier que le processus fonctionne (plus de blocage 403)
```

#### 3. Capabilities Admin

```bash
# Tester avec utilisateur ayant manage_woocommerce mais pas manage_options
# Devrait avoir accès aux réglages WC Qualiopi
```

---

## 📁 Fichiers Modifiés

### Corrections Critiques

- `src/Admin/AdminManager.php` - CSRF protection
- `src/Core/Plugin.php` - Suppression clé hardcodée
- `src/Core/Constants.php` - Unification version/text domain

### Alignements

- `src/Helpers/SecurityHelper.php` - Capabilities par défaut
- `src/Admin/AdminManager.php` - Menu capabilities
- `src/Form/Tracking/TrackingManager.php` - Stats capabilities
- `src/Cart/CartGuard.php` - Store API + capabilities
- `src/Data/Progress/ProgressStorage.php` - Permissions

### Text Domain

- `src/Admin/Logs/LogsFilterManager.php`
- `src/Helpers/AjaxHelper.php`
- `tests/Bootstrap/bootstrap.php`

### Versioning

- `src/Admin/AdminManager.php` - Assets versioning
- `src/Form/GravityForms/FieldInjector.php` - Assets versioning

### Tests

- `tests/Unit/Security/SecurityCorrectionsTest.php` - Nouveau

---

## 🔄 Compatibilité et Migration

### Filtres de Compatibilité Ajoutés

```php
// Pour surcharger les capabilities si besoin
add_filter( 'wcqf_admin_export_capability', function() { return 'manage_options'; } );
add_filter( 'wcqf_admin_menu_capability', function() { return 'manage_options'; } );
add_filter( 'wcqf_admin_early_action_capability', function() { return 'manage_options'; } );
```

### Migration Automatique

- **Versioning** : Automatique via `WCQF_VERSION`
- **Text Domain** : Automatique via `Constants::TEXT_DOMAIN`
- **Clés API** : Notice admin pour configuration manuelle

---

## 🚀 Déploiement

### Version 1.0.2 (Corrections Bloquantes)

- ✅ CSRF AdminManager
- ✅ Suppression clé hardcodée

### Version 1.0.3 (Alignements Warning)

- ✅ Unification versioning
- ✅ Unification text domain
- ✅ Alignement capabilities
- ✅ Store API checkout

### Validation Post-Déploiement

1. **Admin** : Tester export logs avec/sans nonce
2. **Frontend** : Tester checkout invité avec Blocks
3. **Permissions** : Vérifier accès avec `manage_woocommerce`
4. **Logs** : Vérifier absence d'erreurs de sécurité

---

## 📊 Impact Sécurité

### Avant Corrections

- ❌ CSRF possible sur export logs
- ❌ Clé API exposée en code source
- ❌ Incohérences versioning/text domain
- ❌ Capabilities hétérogènes
- ❌ Blocage invités sur checkout

### Après Corrections

- ✅ CSRF protégé (nonce + capabilities)
- ✅ Secrets centralisés et sécurisés
- ✅ Versioning unifié et cohérent
- ✅ Capabilities alignées et filtrables
- ✅ Checkout accessible aux invités

---

## 🎯 Prochaines Étapes

1. **Tests en Staging** : Validation complète des corrections
2. **Déploiement Production** : Version 1.0.2 puis 1.0.3
3. **Monitoring** : Surveillance des logs de sécurité
4. **Documentation** : Mise à jour du guide de sécurité

---

**✅ Toutes les corrections de sécurité ont été appliquées avec succès.**
