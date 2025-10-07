# 🚀 Quick Fill - Remplissage rapide formulaire

**Fichier** : `tests/quick-fill.js`  
**Usage** : Pré-remplir automatiquement le formulaire Gravity Forms pendant le développement

---

## 📝 MODE D'EMPLOI

### 1. Ouvrir la page du formulaire
Aller sur : `https://tb-formation.fr/pages/test-positionnement/` (ou votre page avec le formulaire)

### 2. Ouvrir la console DevTools
Appuyer sur **F12** → Onglet **Console**

### 3. Copier-coller le script
Ouvrir le fichier `tests/quick-fill.js` et copier TOUT le contenu dans la console

### 4. Remplir le formulaire automatiquement
```javascript
quickFill()
```

### 5. Vérifier le SIRET
**Option A** : Cliquer manuellement sur le bouton "Vérifier SIRET"  
**Option B** : Dans la console :
```javascript
clickVerify()
```

---

## 🎯 DONNÉES PRÉ-REMPLIES

Le script remplit automatiquement avec des données **valides nécessitant reformatage** :

| Champ | Valeur saisie | Sera formaté en |
|-------|--------------|-----------------|
| **SIRET** | `81107469900034` | ✅ (valide et existant) |
| **Prénom** | `gabriel` | `Gabriel` (majuscule) |
| **Nom** | `duteurtre` | `Duteurtre` (majuscule) |
| **Téléphone** | `06 14 28 71 51` | `+33614287151` (E164) |
| **Email** | `Gabriel.DUTEURTRE@Gmail.COM` | `gabriel.duteurtre@gmail.com` (minuscules) |

---

## 🔧 FONCTIONS DISPONIBLES

### `quickFill()`
Remplit automatiquement tous les champs avec des données valides

### `clickVerify()`
Clique sur le bouton "Vérifier SIRET" automatiquement

### `clearAll()`
Vide tous les champs du formulaire

---

## 💡 WORKFLOW TYPIQUE

```javascript
// 1. Remplir le formulaire
quickFill()

// 2. Attendre 1 seconde (laisser les validations JS s'exécuter)

// 3. Vérifier le SIRET
clickVerify()

// 4. Observer les résultats dans la console + interface

// 5. Si besoin de tout vider :
clearAll()
```

---

## 🎓 UTILISATION RÉPÉTÉE

Pour ne pas re-copier à chaque fois :

### Bookmark Chrome/Firefox
1. Créer un nouveau marque-page
2. Nom : `Quick Fill`
3. URL :
```javascript
javascript:(function(){fetch('https://raw.githubusercontent.com/SrGabrysh/wc_qualiopi_formation/main/tests/quick-fill.js').then(r=>r.text()).then(eval);})()
```
4. Cliquer sur le bookmark pour charger le script

---

## 🐛 DÉPANNAGE

### Le script ne charge pas
- Vérifier que jQuery est chargé : `typeof jQuery` → doit retourner `"function"`
- Vérifier l'ID du formulaire : regarder dans le HTML l'ID du formulaire (doit être `1`)

### Les champs ne se remplissent pas
- Ouvrir la console et chercher les erreurs
- Vérifier que les IDs de champs sont corrects (7.3, 7.6, 9, 10)

### Le bouton ne se clique pas
- Vérifier la classe du bouton : `.wcqf-form-verify-button`
- Le bouton peut être masqué ou non affiché

---

**Créé le** : 7 octobre 2025  
**Version** : 1.0.0  
**Mis à jour** : 7 octobre 2025

