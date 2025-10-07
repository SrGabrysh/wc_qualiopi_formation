# WC Qualiopi Formation

**Plugin WordPress unifié pour tunnel de formation Qualiopi avec pré-remplissage checkout automatique**

## 📋 Description

Ce plugin fusionne deux plugins précédents (`wc_qualiopi_steps` et `gravity_forms_siren_autocomplete`) pour créer un tunnel de formation complet et conforme Qualiopi, avec suivi end-to-end et pré-remplissage automatique du checkout WooCommerce.

### Fonctionnalités principales

- ✅ **Blocage intelligent du panier** : Test de positionnement obligatoire avant commande
- ✅ **Token HMAC sécurisé** : Suivi de l'utilisateur sur tout le parcours
- ✅ **Formulaire Gravity Forms** : SIRET autocomplete + mentions légales automatiques
- ✅ **Pré-remplissage checkout** : Aucune ressaisie pour le client = meilleur taux de conversion
- ✅ **Conformité Qualiopi** : Traçabilité complète avec logs d'audit
- ✅ **Base de données unifiée** : Stockage centralisé de toutes les données

## 🎨 UI Kit Admin

### Principe

Tous les écrans admin du plugin utilisent le helper `AdminUi` pour garantir cohérence visuelle et maintenabilité.

### Composants disponibles

#### Sections

```php
echo AdminUi::section_start('Titre de section', 'optional-id');
// Contenu
echo AdminUi::section_end();
```

#### Champs

```php
$input = '<input type="text" name="field" class="regular-text" />';
echo AdminUi::field_row('Label', $input, 'Texte d\'aide optionnel');
```

#### Boutons

```php
echo AdminUi::button('Label', 'primary'); // ou 'secondary'
echo AdminUi::button('Enregistrer', 'primary', ['type' => 'submit', 'name' => 'save']);
```

#### Notices

```php
echo AdminUi::notice('Message de succès', 'success'); // success|info|warning|error
```

#### Selects

```php
$options = ['value1' => 'Label 1', 'value2' => 'Label 2'];
echo AdminUi::select('field_name', $options, 'value1');
```

#### Tables

```php
echo AdminUi::table_start(['Colonne 1', 'Colonne 2']);
echo AdminUi::table_row(['Cellule 1', 'Cellule 2']);
echo AdminUi::table_end();
```

### Styles personnalisés

Modifier les tokens CSS dans `assets/css/admin.css` :

- `--wcqf-primary` : Couleur primaire
- `--wcqf-spacing-md` : Espacement standard

## 🔧 Prérequis

- **PHP** : 8.1 ou supérieur
- **WordPress** : 5.8 ou supérieur
- **WooCommerce** : 7.0 ou supérieur
- **Gravity Forms** : 2.7 ou supérieur (licence requise)
- **MySQL** : 5.7 ou supérieur

## 📦 Installation

1. **Télécharger le plugin** dans `wp-content/plugins/wc_qualiopi_formation/`

2. **Installer les dépendances Composer** :

```bash
cd wp-content/plugins/wc_qualiopi_formation
composer install --no-dev --optimize-autoloader
```

3. **Activer le plugin** depuis l'admin WordPress

4. **Vérifier la création des tables** :

- `wp_wcqf_progress` - Suivi progression utilisateur
- `wp_wcqf_tracking` - Tracking formulaires
- `wp_wcqf_audit` - Logs audit Qualiopi

## 🏗️ Architecture

```
wc_qualiopi_formation/
├── src/
│   ├── Core/              # Initialisation & configuration
│   ├── Security/          # Token HMAC & sessions
│   ├── Data/              # Suivi progression & stockage
│   ├── Cart/              # Blocage panier
│   ├── Form/              # Gravity Forms & SIRET
│   ├── Checkout/          # Pré-remplissage checkout
│   ├── Compliance/        # Conformité Qualiopi
│   └── Admin/             # Interface admin
├── assets/                # CSS, JS, images
├── languages/             # Traductions
└── tests/                 # Tests unitaires & E2E
```

## 🎯 Flux utilisateur

```
1. Ajout formation au panier
  ↓
2. Génération token HMAC
  ↓
3. Redirection vers formulaire + token
  ↓
4. Remplissage formulaire (SIRET auto)
  ↓
5. Stockage données avec token
  ↓
6. Redirection checkout + token
  ↓
7. PRÉ-REMPLISSAGE automatique
  ↓
8. Paiement (données enrichies)
```

## 🔐 Sécurité

- Token HMAC avec expiration (2h)
- Sessions WooCommerce sécurisées
- Validation des données à chaque étape
- Échappement et sanitization systématiques
- Conformité WordPress Coding Standards

## 📖 Documentation

- [Architecture complète](Dev/Refactorisation/Architecture%20unifiée.md)
- [Roadmap du projet](Dev/Refactorisation/ROADMAP_FUSION_PLUGINS_QUALIOPI.md)
- [Rapport Phase 0](Dev/Phase_0_Audit/RAPPORT_PHASE_0_COMPLET.md)

## 🧪 Tests

```bash
# Tests unitaires
composer test

# Code standards
composer phpcs

# Auto-fix code
composer phpcbf
```

## 📝 Changelog

### Version 1.0.0-dev.0 (2025-10-07) - 🚧 DÉVELOPPEMENT

**Phase de développement initiale - Jamais déployée en production**

#### 🏗️ Architecture & Structure

- ✅ Structure modulaire complète (src/Core, src/Modules, src/Admin, src/Helpers)
- ✅ Fusion des plugins wc_qualiopi_steps + gravity_forms_siren_autocomplete
- ✅ Réorganisation dev-tools/ au niveau projet (-42% fichiers)
- ✅ Création dossier docs/ organisé (implementation, architecture, security, guides)
- ✅ Choix framework de test : **Pest**

#### 🔒 Sécurité

- ✅ Système de tokens HMAC (TokenManager)
- ✅ Gestion de sessions sécurisées (SessionManager)
- ✅ Gestionnaire de secrets (SecretManager)
- ✅ ApiKeyManager avec chiffrement
- ✅ Vérification nonce/capabilities complète
- ✅ Suppression clés API hardcodées

#### 📝 Formulaires & Validation

- ✅ Intégration Gravity Forms (SIRET, mentions légales)
- ✅ Formatage téléphone au format E164 (+33) - `PhoneFormatter.php`
- ✅ Validation email RFC-compliant - `SanitizationHelper::validate_email_rfc()`
- ✅ Feedback visuel temps réel (téléphone + email)
- ✅ Pré-remplissage checkout WooCommerce automatique

#### 🪵 Logs & Monitoring

- ✅ Système de logs avancé (LoggingHelper)
- ✅ Interface admin pour consultation/export/suppression logs
- ✅ Traçabilité complète (SIRET, formatages, validations)
- ✅ Niveaux de logs (DEBUG, INFO, WARNING, ERROR)

#### 🎨 UI/UX

- ✅ AdminUI helper pour interface cohérente
- ✅ Styles feedback animations (`.wcqf-field-feedback`)
- ✅ Interface admin moderne et responsive

#### 🌐 Compatibilité

- ✅ WooCommerce Blocks (Store API)
- ✅ WordPress 5.8+
- ✅ PHP 8.1+
- ✅ WooCommerce 7.0+

#### 📦 Conformité Qualiopi

- ✅ Blocage panier (test de positionnement obligatoire)
- ✅ Token HMAC de suivi end-to-end
- ✅ Base de données unifiée
- ✅ Logs d'audit complets

#### 🔧 Maintenance

- ✅ Text domain unifié : `wcqf`
- ✅ Versioning SSOT via `WCQF_VERSION`
- ✅ Composer PSR-4 autoloading
- ✅ SECURITY_POLICY.md complet

---

### 🚀 Prochaines étapes vers 1.0.0 stable

- [ ] Tests complets (Pest)
- [ ] Validation en environnement staging
- [ ] Documentation utilisateur complète
- [ ] Release Candidate : 1.0.0-rc.1
- [ ] Release stable : 1.0.0

## 📞 Support

- **Site web** : [https://tb-web.fr](https://tb-web.fr)
- **Email** : contact@tb-web.fr

## 📄 Licence

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

---

Dernière mise à jour : 2025-10-07
