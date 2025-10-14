# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

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
