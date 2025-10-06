# 🔄 Guide de Synchronisation Windows → DDEV

## 🚨 Problème Identifié

**Votre script `Sync-ToDDEV.ps1` actuel fonctionne probablement correctement**, mais vous ne pouvez pas le vérifier. Le problème principal est le **manque de visibilité** sur l'état réel de la synchronisation.

## 📋 Solutions Disponibles

### 🎯 Solution Recommandée : Script Amélioré + Diagnostic

**C'est la meilleure approche pour votre situation actuelle (chemin avec espaces).**

#### 1. Utiliser le script de synchronisation amélioré

```powershell
.\Sync-ToDDEV-Ameliore.ps1
```

**Avantages :**

- ✅ Vérification des chemins avant copie
- ✅ Analyse précise des erreurs robocopy
- ✅ Vérification WSL après copie
- ✅ Conseils automatiques selon le résultat
- ✅ Options `-Force` et `-Verify`

#### 2. Diagnostiquer la synchronisation

```powershell
.\Diagnostic-Sync.ps1              # Test rapide (5 fichiers)
.\Diagnostic-Sync.ps1 -DeepCheck   # Test complet (10 fichiers)
```

**Ce que ça vérifie :**

- ✅ Présence des fichiers dans Windows et WSL
- ✅ Comparaison des timestamps (tolérance 1 minute)
- ✅ Comparaison des tailles de fichiers
- ✅ Rapport détaillé des problèmes

#### 3. Nettoyer les caches après sync

```powershell
.\Clear-Cache-DDEV.ps1
```

**Automatise :**

- 🔄 Redémarrage DDEV
- 🗑️ Vidage cache WordPress
- 🔄 Redémarrage PHP-FPM
- 🧹 Vidage OPcache PHP

---

## 🔧 Workflow Optimisé

### Développement Quotidien

1. **Éditer le code** (Cursor sur Windows)
2. **Synchroniser**
   ```powershell
   .\Sync-ToDDEV-Ameliore.ps1
   ```
3. **Nettoyer les caches**
   ```powershell
   .\Clear-Cache-DDEV.ps1
   ```
4. **Tester** : https://tb-wp-dev.ddev.site
5. **Vérifier les logs** : `ddev logs -f`

### Si Problème de Sync

1. **Diagnostiquer**
   ```powershell
   .\Diagnostic-Sync.ps1 -DeepCheck
   ```
2. **Réparer si nécessaire**
   ```powershell
   .\Sync-ToDDEV-Ameliore.ps1 -Force
   ```

---

## ⚙️ Options Alternatives

### Option B : Lien Symbolique (Solution Définitive)

**Recommandé si vous pouvez déplacer le projet**

```bash
# 1. Arrêter DDEV
ddev stop

# 2. Déplacer le projet vers un chemin SANS espaces
# Ex: E:\Dev\TB-Formation\wc_qualiopi_formation\

# 3. Créer le lien symbolique
ln -s "/mnt/e/Dev/TB-Formation/wc_qualiopi_formation" ~/projects/tb-wp-dev/web/wp-content/plugins/wc_qualiopi_formation

# 4. Redémarrer DDEV
ddev start
```

**Avantages :**

- ✅ Synchronisation **instantanée**
- ✅ Pas de script nécessaire
- ✅ Plus fiable à long terme

### Option C : Développement dans WSL

**Si vous préférez développer directement dans Ubuntu**

```bash
# Dans WSL Ubuntu
cd ~/projects/tb-wp-dev/web/wp-content/plugins/wc_qualiopi_formation
code .  # Ouvre VS Code avec Remote WSL
```

**Avantages :**

- ✅ Environnement Linux natif
- ✅ Pas de problème de synchronisation
- ✅ Accès direct aux outils Linux

---

## 🛠️ Scripts Créés

### 📁 `Diagnostic-Sync.ps1`

- Analyse la synchronisation en comparant Windows vs WSL
- Vérifie timestamps et tailles de fichiers
- Rapporte les problèmes et donne des recommandations

### 📁 `Sync-ToDDEV-Ameliore.ps1`

- Version améliorée de votre script actuel
- Meilleure gestion d'erreurs
- Vérification automatique après copie
- Options avancées (`-Force`, `-Verify`)

### 📁 `Clear-Cache-DDEV.ps1`

- Nettoie tous les caches après synchronisation
- Redémarre DDEV proprement
- Vérifie que le site répond

---

## ✅ Checklist de Validation

### Validation Rapide (30 secondes)

```powershell
# 1. Vérifier quelques fichiers clés
.\Diagnostic-Sync.ps1

# 2. Si OK, nettoyer les caches
.\Clear-Cache-DDEV.ps1

# 3. Tester dans le navigateur
#    - Hard refresh : Ctrl+Shift+R
#    - Vérifier dans l'admin WordPress
```

### Validation Approfondie

```powershell
# 1. Diagnostic complet
.\Diagnostic-Sync.ps1 -DeepCheck

# 2. Synchronisation forcée si nécessaire
.\Sync-ToDDEV-Ameliore.ps1 -Force

# 3. Nettoyage complet
.\Clear-Cache-DDEV.ps1

# 4. Vérification finale
ddev exec "ls -la web/wp-content/plugins/wc_qualiopi_formation/src/"
```

---

## 🚨 Dépannage

### Problème : Fichiers manquants dans WSL

```bash
# 1. Vérifier les permissions
wsl -d Ubuntu -e sh -c "ls -ld ~/projects/tb-wp-dev/web/wp-content/plugins/"

# 2. Vérifier l'espace disque
wsl -d Ubuntu -e df -h

# 3. Recréer le répertoire si nécessaire
wsl -d Ubuntu -e sh -c "rm -rf ~/projects/tb-wp-dev/web/wp-content/plugins/wc_qualiopi_formation && mkdir -p ~/projects/tb-wp-dev/web/wp-content/plugins/wc_qualiopi_formation"

# 4. Relancer la sync
.\Sync-ToDDEV-Ameliore.ps1 -Force
```

### Problème : robocopy échoue

```powershell
# 1. Tester l'accès WSL
Test-Path "\\wsl$\Ubuntu\home\gabrysh\"

# 2. Vérifier si WSL fonctionne
wsl -d Ubuntu -e echo "WSL OK"

# 3. Utiliser l'option -Force
.\Sync-ToDDEV-Ameliore.ps1 -Force
```

---

## 🎯 Recommandation Finale

### Pour Maintenant : Script Amélioré

**Utilisez les scripts créés** - ils résolvent votre problème de visibilité et rendent la synchronisation plus robuste.

### Pour Plus Tard : Lien Symbolique

**Si possible, déplacez le projet** vers un chemin sans espaces pour bénéficier de la synchronisation instantanée.

### Méthode de Travail

1. **Aujourd'hui** : Utilisez `Sync-ToDDEV-Ameliore.ps1` + diagnostic
2. **Cette semaine** : Testez le workflow et ajustez si nécessaire
3. **Plus tard** : Envisagez le déplacement du projet pour simplifier

---

## 📞 Support

Si vous rencontrez des problèmes avec ces scripts :

1. **Lancez le diagnostic** : `.\Diagnostic-Sync.ps1 -DeepCheck`
2. **Vérifiez les logs** : `ddev logs -f`
3. **Partagez les résultats** pour un dépannage plus poussé

**Ces outils devraient résoudre votre problème de confiance dans la synchronisation !** 🎉
