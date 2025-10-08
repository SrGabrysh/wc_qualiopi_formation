# Documentation technique - WC Qualiopi Formation

Documentation complète du plugin WordPress pour tunnel de formation Qualiopi.

## 📁 Structure de la documentation

```
docs/
├── api/                    # Documentation des composants réutilisables
│   └── gravity-forms/      # Intégration Gravity Forms
│       ├── README.md
│       ├── page-transition-handler.md
│       ├── calculation-retriever.md
│       └── examples.md
│
├── architecture/           # Architecture et corrections
│   ├── corrections-2025-10-07.md
│   └── logging-system.md
│
├── guides/                 # Guides pratiques
│   ├── synchronization.md
│   ├── testing-ddev.md
│   └── viewing-logs.md
│
├── implementation/         # Guides d'implémentation
│   ├── ajax-response-structure.md
│   ├── phone-email-v1.1.0.md
│   └── quick-start-phone-email.md
│
└── security/               # Sécurité et audits
    ├── audit-report.md
    ├── corrections-2025-10-07.md
    └── logs-fixes.md
```

## 🔧 API Développeur

### Gravity Forms

Composants pour étendre les fonctionnalités de Gravity Forms :

- **[PageTransitionHandler](api/gravity-forms/page-transition-handler.md)** - Gestion automatique des transitions de pages
- **[CalculationRetriever](api/gravity-forms/calculation-retriever.md)** - Récupération de valeurs calculées
- **[Exemples d'intégration](api/gravity-forms/examples.md)** - Cas d'usage pratiques

### Système de Logs

Documentation complète du système de logs :

- **[Architecture](architecture/logging-system.md)** - Architecture technique du système de logs
- **[Guide utilisateur](guides/viewing-logs.md)** - Consulter et analyser les logs

### Autres composants

Documentation à venir pour :

- FieldMapper
- SecurityHelper
- TokenManager

## 📚 Guides

### Utilisateur

- **[Consulter les logs](guides/viewing-logs.md)** - Guide complet interface admin logs

### Développement

- **[Synchronisation](guides/synchronization.md)** - Synchroniser le plugin vers DDEV
- **[Tests DDEV](guides/testing-ddev.md)** - Tester en environnement local

### Implémentation

- **[Formatage téléphone & email](implementation/phone-email-v1.1.0.md)** - Implémentation complète
- **[Quick Start](implementation/quick-start-phone-email.md)** - Guide rapide

## 🔐 Sécurité

- **[Audit de sécurité](security/audit-report.md)** - Rapport d'audit complet
- **[Corrections](security/corrections-2025-10-07.md)** - Corrections appliquées
- **[Logs fixes](security/logs-fixes.md)** - Corrections du système de logs

## 🏗️ Architecture

- **[Corrections architecture](architecture/corrections-2025-10-07.md)** - Modifications structurelles
- **[Système de logs](architecture/logging-system.md)** - Architecture du système de logs (LoggingHelper, WooCommerce Logger)

---

## 📖 Documentation principale

Consultez le [README principal](../README.md) du plugin pour :

- Installation
- Configuration
- Prérequis
- Vue d'ensemble des fonctionnalités

---

**Plugin** : wc_qualiopi_formation  
**Version** : 1.0.0-dev.0  
**Dernière mise à jour** : 8 octobre 2025
