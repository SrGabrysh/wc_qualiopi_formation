# Sync-ToDDEV-Ameliore.ps1 v2.2 - Changelog

**Date** : 6 octobre 2025  
**Statut** : ✅ Corrections critiques complétées + Fonctionnalités avancées en cours

---

## 🔧 Corrections Critiques Effectuées

### 1. ✅ Niveau de log non initialisé

**Problème** : `$script:LogLevel` n'était pas positionné, filtrage incohérent des messages.

**Solution** :

```powershell
# Initialisation correcte dans Initialize-Logging
$script:LogLevel = if ($RequestedLogLevel) { $RequestedLogLevel } else { $Config.logging.defaultLevel }
```

### 2. ✅ Numérotation incohérente des étapes

**Problème** : Messages affichaient `[ETAPE 1/4]…[ETAPE 4/4]` alors qu'il existe 5 étapes.

**Solution** : Corrigé vers `[ETAPE X/5]` pour toutes les étapes.

### 3. ✅ Branche WSL mal structurée

**Problème** : Actions DDEV lancées même si WSL indisponible.

**Solution** :

```powershell
if (-not $wslTest) {
    Write-Log "WSL ne repond pas" "Error"
    Write-Log "Nettoyage des caches DDEV impossible sans WSL" "Warning"
} else {
    # Toutes les actions DDEV dans le else
}
```

### 4. ✅ Version incohérente

**Problème** : Entête annonçait "Version 2.0" alors que les logs parlent de "v2.2".

**Solution** : Alignement sur v2.2 partout.

### 5. ✅ Mode dry-run non opérationnel

**Problème** : `-WhatIf` déclaré mais jamais appliqué (`/L` avec robocopy manquant).

**Solution** :

```powershell
# Robocopy simulation
if ($WhatIf) {
    $RobocopyArgs += "/L"
    Write-Log "MODE SIMULATION (DRY-RUN)" "Warning"
}

# Composer simulation
if ($WhatIf) {
    Write-Log "[SIMULATION] Executerait : composer install ..." "Info"
} else {
    # Vraie installation
}

# DDEV simulation
if ($WhatIf) {
    Write-Log "[SIMULATION] Operations DDEV qui seraient executees" "Info"
} else {
    # Vraies commandes DDEV
}
```

### 6. ✅ Nettoyage des logs incomplet

**Problème** : Config expose `maxLogFiles` mais logique supprime selon l'âge uniquement.

**Solution** :

```powershell
# Suppression basée sur la date (30 jours)
Get-ChildItem -Path $logDir -Filter "sync-*.log" |
    Where-Object { $_.LastWriteTime -lt $cutoffDate } |
    Remove-Item -Force

# Suppression basée sur le nombre (garder les N plus récents)
$logFiles = Get-ChildItem -Path $logDir -Filter "sync-*.log" |
    Sort-Object LastWriteTime -Descending

if ($logFiles.Count -gt $maxFiles) {
    $logFiles | Select-Object -Skip $maxFiles | Remove-Item -Force
}
```

---

## 🚀 Fonctionnalités Avancées Implémentées

### 1. ✅ Mode miroir optionnel

**Config** : `sync.mode = "standard" | "mirror"`

```powershell
$syncMode = $Config.sync.mode

if ($syncMode -eq "mirror" -or $script:Force) {
    $RobocopyArgs += "/MIR"
    Write-Log "Mode MIRROR - Synchronisation complete avec suppression" "Warning"
} else {
    $RobocopyArgs += "/IS"
    Write-Log "Mode STANDARD - Copie incrementale" "Debug"
}
```

### 2. ✅ Composer contexte sélectionnable

**Config** : `dependencies.context = "windows" | "ddev"`

```powershell
if ($composerContext -eq "ddev") {
    # Exécution dans DDEV
    Invoke-DDEVCommand -Command "exec 'cd plugin && composer install'"
} else {
    # Exécution Windows locale
    & "composer" "install" $composerOptions
}
```

---

## 📊 Configuration Améliorée (.sync-config.json v2.2)

```json
{
  "version": "2.2",

  "sync": {
    "mode": "standard", // "standard" ou "mirror"
    "parseRobocopyOutput": true
  },

  "verification": {
    "http": {
      "enabled": true,
      "endpoint": "/wp-json",
      "expectedStatus": 200
    }
  },

  "dependencies": {
    "context": "windows", // "windows" ou "ddev"
    "composerOptions": ["--no-dev", "--optimize-autoloader"]
  },

  "hooks": {
    "preSync": [],
    "postSync": []
  },

  "ddev": {
    "php": {
      "reloadOpcache": true
    }
  }
}
```

---

## 🔄 Fonctionnalités En Cours d'Implémentation

### ⏳ 1. Parser sortie robocopy

Extraction des fichiers réellement copiés/supprimés/ignorés.

### ⏳ 2. Smoke test HTTP

Validation fonctionnelle du site après sync via `Invoke-WebRequest`.

### ⏳ 3. Système de hooks

Exécution de commandes pré/post sync (build, flush, etc.).

### ⏳ 4. Rapport final JSON

Récapitulatif structuré pour intégration outils externes.

---

## 📈 Métriques

| Métrique            | Avant              | Après v2.2        |
| ------------------- | ------------------ | ----------------- |
| Problèmes critiques | 6                  | 0 ✅              |
| Mode dry-run        | ❌ Non fonctionnel | ✅ Complet        |
| Contexte Composer   | Windows uniquement | Windows + DDEV ✅ |
| Mode miroir         | Force uniquement   | Config + Force ✅ |
| Nettoyage logs      | Date uniquement    | Date + Nombre ✅  |
| Gestion WSL         | Bugée              | Robuste ✅        |

---

## ✅ Tests Effectués

```powershell
# Test 1 : Mode WhatIf basique
.\Sync-ToDDEV-Ameliore.ps1 -WhatIf
# ✅ Robocopy /L activé, aucune modification

# Test 2 : Mode WhatIf complet
.\Sync-ToDDEV-Ameliore.ps1 -WhatIf -InstallDeps -ClearCache -Verify
# ✅ Toutes les simulations affichées

# Test 3 : Niveau de log
.\Sync-ToDDEV-Ameliore.ps1 -LogLevel Debug -Verify
# ✅ Logs détaillés corrects

# Test 4 : Composer contexte
# Config: dependencies.context = "windows"
# ✅ Installation locale Windows correcte
```

---

## 🎯 Prochaines Étapes

1. **Smoke test HTTP** : Validation `/wp-json` après sync
2. **Parsing robocopy** : Liste détaillée des fichiers synchronisés
3. **Hooks pré/post** : Automatisation tâches périphériques
4. **Rapport JSON** : Export structuré pour monitoring

---

## 📝 Notes de Déploiement

- **Compatible** avec l'ancienne config (fallback sur valeurs par défaut)
- **Rétrocompatible** avec scripts existants
- **WhatIf recommandé** pour premier test après mise à jour
- **Mode mirror** nécessite prudence (suppression fichiers)

**Version stable** : v2.2  
**Prochaine version** : v2.3 (avec hooks + rapport JSON)
