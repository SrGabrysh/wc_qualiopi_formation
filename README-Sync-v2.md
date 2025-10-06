# 🚀 Sync-ToDDEV-Ameliore.ps1 v2.2

## 📋 Nouvelles Fonctionnalités

### ✅ **1. Configuration Externe**

- **Fichier** : `.sync-config.json`
- **Avantage** : Réutilisable pour différents projets
- **Fallback** : Configuration par défaut si fichier absent

### ✅ **2. Mode Dry-Run**

- **Paramètre** : `-WhatIf`
- **Fonction** : Simulation sans modification réelle
- **Affichage** : Liste des fichiers qui seraient synchronisés

### ✅ **3. Logging Structuré**

- **Niveaux** : Error, Warning, Info, Debug, Verbose
- **Paramètre** : `-LogLevel` (défaut: Info)
- **Fichiers** : `./logs/sync-YYYYMMDD.log`
- **Rotation** : Conservation des 10 derniers logs

### ✅ **4. Vérification Intelligente**

- **Source** : Fichiers critiques depuis la configuration
- **Option** : Checksums MD5 (si activé)
- **Base** : Fichiers réellement synchronisés par Robocopy

### ✅ **5. Mode Quiet Cohérent**

- **Paramètre** : `-Quiet`
- **Effet** : Affichage minimal, logs complets
- **Application** : Globale dans tout le script

---

## 🎯 Utilisation

### **Mode Standard**

```powershell
.\Sync-ToDDEV-Ameliore.ps1
```

### **Mode Simulation**

```powershell
.\Sync-ToDDEV-Ameliore.ps1 -WhatIf
```

### **Mode Debug**

```powershell
.\Sync-ToDDEV-Ameliore.ps1 -LogLevel Debug
```

### **Mode Silencieux**

```powershell
.\Sync-ToDDEV-Ameliore.ps1 -Quiet
```

### **Surcharge de Configuration**

```powershell
.\Sync-ToDDEV-Ameliore.ps1 -Force -ClearCache -RestartDDEV
```

---

## ⚙️ Configuration (.sync-config.json)

```json
{
  "paths": {
    "source": ".",
    "target": "\\\\wsl$\\Ubuntu\\home\\gabrysh\\projects\\tb-wp-dev\\web\\wp-content\\plugins\\wc_qualiopi_formation"
  },
  "robocopy": {
    "excludeDirectories": ["node_modules", ".git"],
    "excludeFiles": ["*.md", "*.bat", "*.ps1", "sync_*.py", "*.log"],
    "options": ["/E", "/NJH", "/NJS", "/NDL", "/NP"]
  },
  "verification": {
    "criticalFiles": [
      "wc_qualiopi_formation.php",
      "src/Core/Plugin.php",
      "assets/js/form-frontend.js"
    ],
    "enableChecksums": false
  },
  "defaults": {
    "force": true,
    "verify": true,
    "clearCache": true,
    "restartDDEV": false,
    "installDeps": false,
    "quiet": false
  }
}
```

---

## 📊 Paramètres Disponibles

| Paramètre      | Type   | Défaut              | Description               |
| -------------- | ------ | ------------------- | ------------------------- |
| `-ConfigFile`  | String | `.sync-config.json` | Fichier de configuration  |
| `-WhatIf`      | Switch | `$false`            | Mode simulation (dry-run) |
| `-LogLevel`    | String | `Info`              | Niveau de logging         |
| `-Force`       | Switch | Config              | Mode force (MIRROR)       |
| `-Verify`      | Switch | Config              | Vérification post-sync    |
| `-ClearCache`  | Switch | Config              | Nettoyage des caches      |
| `-RestartDDEV` | Switch | Config              | Redémarrage DDEV          |
| `-InstallDeps` | Switch | Config              | Installation dépendances  |
| `-Quiet`       | Switch | Config              | Mode silencieux           |

---

## 🔧 Personnalisation

### **Pour un Nouveau Projet**

1. Copier `.sync-config.json`
2. Modifier les chemins `source` et `target`
3. Ajuster les exclusions si nécessaire
4. Modifier les fichiers critiques à vérifier

### **Niveaux de Logging**

- **Error** : Erreurs critiques uniquement
- **Warning** : Erreurs + avertissements
- **Info** : Informations générales (défaut)
- **Debug** : Détails techniques
- **Verbose** : Tous les détails

---

## 📁 Structure des Logs

```
logs/
├── sync-20251006.log    # Log du jour
├── sync-20251005.log    # Log précédent
└── ...
```

**Format des logs :**

```
[2025-10-06 11:26:27] [Info] Message d'information
[2025-10-06 11:26:27] [Debug] Détail technique
[2025-10-06 11:26:27] [Warning] Avertissement
[2025-10-06 11:26:27] [Error] Erreur critique
```

---

## 🎉 Avantages v2.2

1. ✅ **Réutilisable** : Configuration externe
2. ✅ **Sécurisé** : Mode dry-run + validation des chemins + liste blanche WSL
3. ✅ **Traçable** : Logging optimisé avec flush périodique
4. ✅ **Intelligent** : Vérification adaptée + codes Robocopy complets
5. ✅ **Flexible** : Paramètres configurables
6. ✅ **Professionnel** : Gestion d'erreurs standardisée
7. ✅ **Sécurisé** : Protection contre l'exécution concurrente
8. ✅ **Performant** : Logging en batch + constantes nommées
9. ✅ **Robuste** : Validation stricte des chemins + commandes DDEV réutilisables
10. ✅ **Cohérent** : Mode WhatIf complet + nettoyage optimisé

---

## 🚀 Prochaines Versions

**v2.1 (prévue) :**

- Mode surveillance (-Watch)
- Sauvegardes automatiques
- Hooks pré/post synchronisation
- Support multi-projets

**v2.2 (prévue) :**

- Interface graphique
- Notifications Windows
- Rapports détaillés
- Synchronisation bidirectionnelle
