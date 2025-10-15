# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

## [1.4.2] - 2025-10-15

### Fixed

- **Bug majeur d'affichage iframe Yousign** : Correction de l'iframe confinée dans un cadre de 306px × 150px
  - L'iframe occupe maintenant 90% de la hauteur de l'écran (90vh) avec une hauteur minimale de 750px
  - Suppression des barres de défilement imbriquées qui rendaient l'interface inutilisable
  - Ajout de styles CSS avec spécificité maximale (`#field_1_34`) pour outrepasser les règles Gravity Forms
- **Séparation des responsabilités** : Suppression du style inline dans YousignIframeHandler.php
  - Respect du principe de séparation PHP vs CSS
  - Amélioration de la maintenabilité du code
- **Identification du fichier CSS correct** : Correction du bug de modification du mauvais fichier CSS
  - Styles ajoutés dans `form-frontend.css` (fichier réellement chargé) au lieu de `frontend.css`
  - Mise à jour de la version du plugin pour forcer le rechargement du cache CSS

### Improved

- **Expérience utilisateur** : L'interface de signature électronique Yousign est maintenant pleinement utilisable
- **Architecture CSS** : Utilisation de l'ID direct avec spécificité CSS maximale pour garantir l'application des styles
- **Maintenabilité** : Documentation complète de la solution dans le rapport de mise en œuvre

## [1.4.0] - 2025-10-15

### Changed

- **Refactorisation majeure de l'architecture du module Yousign** pour améliorer la maintenabilité et faciliter les évolutions futures
  - Création de `src/Modules/Yousign/` avec séparation stricte par responsabilité (Client/, Payload/, Handlers/)
  - **YousignClient.php** (187 lignes) : Isolation complète de la communication HTTP avec API Yousign v3
  - **PayloadBuilder.php** (173 lignes) : Isolation de la construction des payloads JSON pour l'API
  - **YousignIframeHandler.php** : Refactorisation de 638 → 352 lignes (-45%), transformation en orchestrateur pur respectant le principe SRP
  - FormManager.php mis à jour avec injection de dépendances complète pour le module Yousign
- **Centralisation de l'extraction des données Gravity Forms** pour éliminer la duplication de code
  - `DataExtractor::extract_personal()` rendue publique pour réutilisation par d'autres modules
  - Suppression de la méthode dupliquée `extract_user_data()` dans YousignIframeHandler
  - Application des principes DRY (Don't Repeat Yourself) et SSOT (Single Source of Truth)
  - Audit complet de réutilisation documenté dans `AUDIT_REUTILISATION_DATAEXTRACTOR.md`

### Improved

- **Réduction de 52% de la complexité cyclomatique** dans YousignIframeHandler (25 → 12)
- **Amélioration de la testabilité** : Injection de dépendances facilitant les tests unitaires et le mocking
- **Amélioration de la maintenabilité** : Séparation claire des responsabilités selon le principe SRP
- **Architecture 100% conforme** aux référentiels du plugin (limites de taille, responsabilité unique, modularité)

## [1.3.0] - 2025-10-15

### Added

- **YousignIframeHandler** : Intégration complète Yousign API v3 pour signature électronique des contrats de formation
  - Workflow automatique : CREATE → ACTIVATE → Injection iframe dans Gravity Forms
  - Support des templates Yousign avec placeholders dynamiques (signers + read_only_text_fields)
  - Injection automatique de l'iframe de signature dans champ HTML Gravity Forms
  - Gestion des champs pré-remplis dans les PDFs de contrat (nom, prénom, email)
- Hook `wcqf_page_transition` utilisé pour déclencher automatiquement la création de procédure Yousign

### Fixed

- Correction label placeholder case-sensitive pour compatibilité templates Yousign (`client` au lieu de `Client`)
- Logs d'idempotence cohérents avec API v3 (utilisation de `sr_id` au lieu de `procedure_id`)
- Refactorisation endpoints API centralisés via `get_base_api_url()` pour faciliter le switch sandbox/production

### Security

- Validation stricte des données utilisateur extraites de Gravity Forms avant envoi à Yousign
- Sanitization complète des inputs (nom, prénom, email)
- Gestion sécurisée des clés API Yousign via ApiKeyManager existant
- Sessions sécurisées pour stockage temporaire des signature_link

## [1.2.1] - 2025-10-14

### Fixed

- **Race condition dans SettingsSaver** : Correction du bug qui écrasait les clés API fraîchement sauvegardées lors de la fusion des settings
  - Récupération des settings déplacée APRÈS la sauvegarde des clés API par ApiKeyManager
  - Les clés API persistent maintenant correctement en base de données
  - L'interface admin affiche correctement le placeholder "**\*\*\*\***" pour les clés existantes
- Ajout de logs détaillés pour faciliter le débogage de la sauvegarde des clés API

## [1.2.0] - 2025-10-14

### Added

- ButtonReplacementManager : Système de remplacement automatique du bouton "Suivant" par "Retour à l'accueil" pour les utilisateurs ayant échoué au test de positionnement
- Hook `wcqf_button_replacement_filter` : Personnalisation du texte du bouton via filtres WordPress
- Hook `wcqf_homepage_url_filter` : Personnalisation de l'URL de redirection vers l'accueil
- Tests unitaires complets pour ButtonReplacementManager avec couverture exhaustive des cas de test

### Fixed

- Intégration automatique du ButtonReplacementManager dans FormManager
- Correction des erreurs de linting avec ajout des préfixes `\` pour les fonctions WordPress globales

### Security

- Validation stricte des statuts de test ('refused', 'reinforced', 'admitted')
- Sanitization et escaping complets des données utilisateur dans ButtonReplacementManager

## [1.0.0-dev.0] - 2025-10-07

**Phase de développement initiale - Jamais déployée en production**

### Added

#### Architecture & Structure

- Structure modulaire complète (src/Core, src/Modules, src/Admin, src/Helpers)
- Fusion des plugins wc_qualiopi_steps + gravity_forms_siren_autocomplete
- Réorganisation dev-tools/ au niveau projet (-42% fichiers)
- Création dossier docs/ organisé (implementation, architecture, security, guides)
- Choix framework de test : **Pest**

#### Sécurité

- Système de tokens HMAC (TokenManager)
- Gestion de sessions sécurisées (SessionManager)
- Gestionnaire de secrets (SecretManager)
- ApiKeyManager avec chiffrement
- Vérification nonce/capabilities complète

#### Formulaires & Validation

- Intégration Gravity Forms (SIRET, mentions légales)
- Formatage téléphone au format E164 (+33) via `PhoneFormatter.php`
- Validation email RFC-compliant via `SanitizationHelper::validate_email_rfc()`
- Feedback visuel temps réel (téléphone + email)
- Pré-remplissage checkout WooCommerce automatique
- Récupération valeurs calculées (CalculationRetriever) - Score de positionnement
- Gestion transitions de pages (PageTransitionHandler) - Détermination parcours formation

#### Logs & Monitoring

- Système de logs avancé (LoggingHelper)
- Interface admin pour consultation/export/suppression logs
- Traçabilité complète (SIRET, formatages, validations)
- Niveaux de logs (DEBUG, INFO, WARNING, ERROR)

#### UI/UX

- AdminUI helper pour interface cohérente
- Styles feedback animations (`.wcqf-field-feedback`)
- Interface admin moderne et responsive

#### Conformité Qualiopi

- Blocage panier (test de positionnement obligatoire)
- Token HMAC de suivi end-to-end
- Base de données unifiée
- Logs d'audit complets

### Changed

- Text domain unifié vers `wcqf`
- Versioning centralisé via constante `WCQF_VERSION`

### Removed

- Suppression clés API hardcodées pour sécurité

### Fixed

- N/A (version initiale)

### Security

- Implémentation politique de sécurité complète (voir SECURITY_POLICY.md)
- Échappement et sanitization systématiques
- Conformité WordPress Coding Standards

## [Prochaines versions]

### Roadmap vers 1.0.0 stable

- Tests complets (Pest)
- Validation en environnement staging
- Documentation utilisateur complète
- Release Candidate : 1.0.0-rc.1
- Release stable : 1.0.0

---

[Unreleased]: https://github.com/SrGabrysh/wc_qualiopi_formation/compare/v1.0.0-dev.0...HEAD
[1.0.0-dev.0]: https://github.com/SrGabrysh/wc_qualiopi_formation/releases/tag/v1.0.0-dev.0
