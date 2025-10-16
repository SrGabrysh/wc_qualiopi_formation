# WC Qualiopi Formation

**Version:** 1.6.0

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

## 🔧 API Développeur

Le plugin expose plusieurs composants réutilisables pour étendre ses fonctionnalités.

### Composants principaux

- **CalculationRetriever** - Récupération de valeurs calculées dans Gravity Forms
- **PageTransitionHandler** - Gestion automatique des transitions de pages et détermination de parcours
- **FieldMapper** - Mapping entre champs Gravity Forms et données métier
- **LoggingHelper** - Système de logs structurés (JSON monoligne, compatible CloudWatch)

### Documentation technique complète

📖 **Consultez les guides détaillés** :

- [`docs/api/gravity-forms/page-transition-handler.md`](docs/api/gravity-forms/page-transition-handler.md) - Guide complet PageTransitionHandler
- [`docs/api/gravity-forms/calculation-retriever.md`](docs/api/gravity-forms/calculation-retriever.md) - Synthèse technique CalculationRetriever
- [`docs/api/gravity-forms/examples.md`](docs/api/gravity-forms/examples.md) - Exemples d'utilisation et intégration

### Action WordPress disponible

```php
// Écouter la complétion du test de positionnement
add_action( 'wcqf_test_completed', function( $score, $path, $submission_data, $form ) {
    // $score : 0-20 | $path : 'refused', 'reinforced', 'admitted'
}, 10, 4 );
```

**Voir la documentation technique pour les détails d'implémentation.**

## 📊 Système de Logs

Le plugin dispose d'un système de logs complet accessible depuis l'interface admin.

### Consultation des logs

**Interface admin** : Réglages → WC Qualiopi Formation → Onglet "Logs"

Fonctionnalités disponibles :

- Consultation des logs récents avec filtres (date, niveau)
- Export CSV pour analyse externe
- Vidage du fichier de logs

### Fichiers de logs

Les logs sont stockés dans : `/wp-content/uploads/wc-logs/wc-qualiopi-formation-*.log`

- ✅ Fichier dédié uniquement au plugin (pas de mélange)
- ✅ Rotation automatique quotidienne
- ✅ Nettoyage automatique après 30 jours

### Documentation complète

📖 **Consultez les guides détaillés** :

- [`docs/guides/viewing-logs.md`](docs/guides/viewing-logs.md) - Guide utilisateur : consulter les logs
- [`docs/architecture/logging-system.md`](docs/architecture/logging-system.md) - Architecture technique du système

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

Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique complet.

### Version 1.6.0 - 2025-10-16

- **Injection de 9 champs RO dans les documents Yousign** : Pré-remplissage automatique des contrats de formation
  - Données injectées : convention_id, noms complets, mentions légales, dates, totaux HT/TTC/TVA
  - Nouvelle classe `YousignDataCollector` pour centraliser la collection de données
  - Nouvelle méthode `CartBookingRetriever::get_cart_totals()` pour les totaux financiers
- **Correction bugs critiques** : Mentions légales et totaux panier maintenant correctement injectés dans Yousign
- **Architecture renforcée** : Séparation stricte des responsabilités entre collection et construction de payload

### Version 1.5.0 - 2025-10-16

- **Correction bug critique génération convention_id** : L'iframe Yousign s'affiche maintenant correctement
  - Le convention_id est généré directement depuis WooCommerce (session + panier) sans dépendre de la BDD
  - Réécriture complète de `generate_and_store_convention_id()` pour simplification architecturale
  - Nouvelle méthode `get_product_id_from_cart()` pour récupération robuste du product_id
  - Plus fiable et plus simple : génération toujours réussie

### Version 1.4.2 - 2025-10-15

- **Correction bug majeur iframe Yousign** : Interface de signature maintenant pleinement utilisable
  - L'iframe occupe 90% de l'écran (90vh) au lieu d'être confinée dans 306px × 150px
  - Suppression des barres de défilement imbriquées
  - Styles CSS avec spécificité maximale pour outrepasser les règles Gravity Forms
  - Séparation des responsabilités : suppression du style inline PHP au profit du CSS externe

### Version 1.4.0 - 2025-10-15

- **Refactorisation majeure de l'architecture Yousign** : Amélioration drastique de la maintenabilité
  - Module Yousign restructuré avec séparation stricte des responsabilités (Client, Payload, Handlers)
  - YousignIframeHandler réduit de 45% (638 → 352 lignes) et transformé en orchestrateur pur
  - Centralisation de l'extraction des données Gravity Forms (principe DRY/SSOT)
  - Réduction de 52% de la complexité, amélioration de la testabilité
  - Architecture 100% conforme aux standards du plugin

### Version 1.3.0 - 2025-10-15

- **Intégration Yousign API v3** : Signature électronique des contrats de formation
  - Workflow automatique CREATE → ACTIVATE → Injection iframe dans Gravity Forms
  - Support des templates Yousign avec placeholders dynamiques
  - Gestion des champs pré-remplis dans les PDFs (nom, prénom, email)
- Corrections case-sensitivity labels et logs d'idempotence
- Refactorisation endpoints API pour faciliter switch sandbox/production

### Version 1.2.1 - 2025-10-14

- Correction race condition dans la sauvegarde des clés API (SettingsSaver)
- Les clés API persistent maintenant correctement en base de données
- Affichage correct du placeholder "**\*\*\*\***" dans l'interface admin
- Ajout de logs détaillés pour faciliter le débogage

### Version 1.2.0 - 2025-10-14

- Ajout ButtonReplacementManager : Remplacement automatique du bouton "Suivant" par "Retour à l'accueil" pour les utilisateurs ayant échoué au test
- Hooks personnalisés pour personnalisation du texte et de l'URL de redirection
- Tests unitaires complets avec couverture exhaustive
- Corrections de linting et améliorations de sécurité

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
- ✅ Récupération valeurs calculées (CalculationRetriever) - Score de positionnement
- ✅ Gestion transitions de pages (PageTransitionHandler) - Détermination parcours formation

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

Dernière mise à jour : 2025-10-16
