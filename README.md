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

### Version 1.0.0 (2025-10-02)

- ✨ Version initiale
- ✅ Fusion de wc_qualiopi_steps et gravity_forms_siren_autocomplete
- ✅ Pré-remplissage checkout opérationnel
- ✅ Conformité Qualiopi complète

## 📞 Support

- **Site web** : [https://tb-web.fr](https://tb-web.fr)
- **Email** : contact@tb-web.fr

## 📄 Licence

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)



---
Dernière mise à jour : 2025-10-06 13:25:58
