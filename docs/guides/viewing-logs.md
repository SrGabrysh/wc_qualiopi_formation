# Guide : Consulter les logs du plugin

**Public** : Administrateurs, développeurs  
**Difficulté** : Facile

---

## 🎯 Objectif

Apprendre à consulter et analyser les logs du plugin depuis l'interface d'administration WordPress.

---

## 📍 Accéder à l'interface logs

### Étape 1 : Connexion admin

1. Se connecter à l'administration WordPress
2. Aller dans **Réglages → WC Qualiopi Formation**
3. Cliquer sur l'onglet **"Logs"**

### Capture d'écran de navigation

```
Tableau de bord WordPress
  ↓
Réglages (menu gauche)
  ↓
WC Qualiopi Formation
  ↓
Onglet "Logs" (en haut)
```

---

## 📊 Comprendre l'interface

### Sections disponibles

#### 1️⃣ Filtres

Permet de filtrer les logs affichés :

- **Limite d'affichage** : 50, 100, 200, 500 logs
- **Période** : Aujourd'hui, Dernières 24h, 7 derniers jours, 30 derniers jours

**Exemple** : Afficher les 100 derniers logs des 7 derniers jours

#### 2️⃣ Actions sur les logs

Boutons disponibles :

- **Exporter en CSV** : Télécharge tous les logs filtrés
- **Vider les logs** : Supprime le fichier de logs actuel (⚠️ irréversible)

#### 3️⃣ Tableau des logs récents

Colonnes :

| Colonne    | Description            | Exemple                   |
| ---------- | ---------------------- | ------------------------- |
| Date/Heure | Timestamp du log       | `08/10/2025 10:30:15`     |
| Niveau     | Gravité du log         | `INFO`, `DEBUG`, `ERROR`  |
| Message    | Message principal      | `[CartGuard] Initialized` |
| Contexte   | Données additionnelles | Cliquer pour voir détails |

---

## 🔍 Interpréter les logs

### Niveaux de log

| Niveau       | Couleur     | Signification        | Action                  |
| ------------ | ----------- | -------------------- | ----------------------- |
| **DEBUG**    | Gris        | Débogage détaillé    | Normal en développement |
| **INFO**     | Bleu        | Information générale | Normal                  |
| **NOTICE**   | Bleu clair  | Événement notable    | Normal                  |
| **WARNING**  | Orange      | Avertissement        | Vérifier si récurrent   |
| **ERROR**    | Rouge       | Erreur               | **À corriger**          |
| **CRITICAL** | Rouge foncé | Erreur critique      | **Urgent à corriger**   |

### Messages courants

#### ✅ Logs normaux (INFO)

```
[ModuleLoader] AdminManager initialise avec succes
[FormManager] Form modules initialized
[CartGuard] Initialized
[SirenAutocomplete] API client initialized
```

**Action** : Aucune, fonctionnement normal

#### ⚠️ Avertissements (WARNING)

```
[SirenAutocomplete] API key missing
[LogsDataProvider] Impossible de lire le fichier de logs
```

**Action** : Vérifier la configuration

#### ❌ Erreurs (ERROR)

```
[SirenApiClient] API call failed: Connection timeout
[FormManager] Gravity Forms not available
```

**Action** : Corriger la configuration ou contacter le support

---

## 📤 Exporter les logs

### Format CSV

1. Cliquer sur **"Exporter en CSV"**
2. Le fichier se télécharge : `wc-qualiopi-formation-logs-YYYY-MM-DD-HH-MM-SS.csv`

### Contenu du fichier

```csv
timestamp,level,message,context
2025-10-08T10:30:15+00:00,info,[CartGuard] Initialized,"{""module"":""cart""}"
2025-10-08T10:30:16+00:00,debug,[FormManager] Init,"{""forms"":2}"
```

### Utilisation

- Ouvrir avec Excel, LibreOffice ou Google Sheets
- Analyser avec des outils de log (grep, awk, etc.)
- Partager avec le support technique

---

## 🗑️ Vider les logs

### ⚠️ ATTENTION

**Cette action est IRRÉVERSIBLE** !

Le bouton "Vider les logs" :

- Supprime le fichier de logs actuel
- Crée un nouveau fichier vide
- **Ne peut PAS être annulé**

### Quand l'utiliser ?

✅ **Situations appropriées** :

- Après avoir exporté les logs
- Fichier de logs trop volumineux
- Tests de développement terminés
- Nouvelle installation propre

❌ **À éviter** :

- Si vous enquêtez sur un problème
- Si le support technique vous a demandé les logs
- Sans avoir fait de backup/export

### Procédure recommandée

1. **Exporter en CSV** d'abord
2. Vérifier que le fichier CSV est téléchargé
3. Cliquer sur "Vider les logs"
4. Confirmer l'action dans la popup

---

## 🔧 Dépannage

### "Aucun log disponible"

**Causes possibles** :

1. Aucune activité récente sur le plugin
2. Fichier de logs vide ou manquant
3. WooCommerce non actif
4. Problème de permissions

**Solutions** :

1. Déclencher une action (rafraîchir une page, soumettre un formulaire)
2. Vérifier que WooCommerce est actif
3. Vérifier les permissions du dossier `/wp-content/uploads/wc-logs/`

### Logs non mis à jour

**Cause** : Cache du navigateur

**Solution** :

1. Rafraîchir la page (`Ctrl+F5` ou `Cmd+Shift+R`)
2. Vider le cache du navigateur

### Fichier de logs volumineux

Si le fichier de logs fait plusieurs Mo :

1. **Exporter en CSV** pour archivage
2. **Vider les logs** pour repartir sur un fichier propre
3. WooCommerce créera automatiquement un nouveau fichier

---

## 📁 Emplacement technique

### Fichiers de logs

**Chemin** : `/wp-content/uploads/wc-logs/`

**Nom** : `wc-qualiopi-formation-YYYY-MM-DD-hash.log`

**Exemple** : `wc-qualiopi-formation-2025-10-08-a1b2c3d4.log`

### Rotation automatique

WooCommerce crée automatiquement :

- **Nouveau fichier** : Chaque jour à minuit (UTC)
- **Nettoyage** : Suppression automatique après 30 jours (par défaut)

---

## 💡 Bonnes pratiques

### Pour les administrateurs

1. **Consulter régulièrement** : Vérifier les logs une fois par semaine
2. **Surveiller les erreurs** : Filtrer par niveau `ERROR` et `CRITICAL`
3. **Exporter avant de vider** : Toujours faire un backup
4. **Archiver** : Conserver les exports importants

### Pour les développeurs

1. **Filtrer par période** : Utiliser "Dernières 24h" pour le debug actif
2. **Augmenter la limite** : Passer à 500 logs pour une vue d'ensemble
3. **Analyser le contexte** : Cliquer sur "Voir le contexte" pour les détails
4. **Utiliser l'export** : Analyser les logs avec des outils externes

---

## 📚 Voir aussi

- [Architecture du système de logs](../architecture/logging-system.md)
- [API LoggingHelper](../api/logging-helper.md) _(à venir)_
- [Dépannage général](troubleshooting.md) _(à venir)_

---

**Besoin d'aide ?**

Si vous rencontrez un problème non documenté ici, consultez les logs et contactez le support technique avec l'export CSV.
