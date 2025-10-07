# 🚀 Quick Start - Formatage Téléphone/Email

## ✅ Implémentation complétée - Version 1.1.0

### Ce qui a été fait

**Nouveaux fichiers** :

- ✅ `src/Helpers/PhoneFormatter.php` - Formatage E164 téléphones

**Fichiers modifiés** :

- ✅ `src/Helpers/SanitizationHelper.php` - Validation email RFC
- ✅ `src/Form/GravityForms/AjaxHandler.php` - Récupération + formatage téléphone/email
- ✅ `assets/js/form-frontend.js` - Surveillance champs + feedback visuel
- ✅ `src/Form/GravityForms/FieldMapper.php` - Mapping champs 9 et 10
- ✅ `assets/css/frontend.css` - Styles feedback
- ✅ `wc_qualiopi_formation.php` - Version 1.1.0
- ✅ `README.md` - Changelog mis à jour

---

## 🎯 Fonctionnement

### 1. Utilisateur saisit téléphone/email

**Champs Gravity Forms** :

- Champ ID `9` : Téléphone
- Champ ID `10` : Email

**Formats acceptés téléphone** :

- `0612345678`
- `06.12.34.56.78`
- `06 12 34 56 78`
- `06-12-34-56-78`

### 2. Clic "Vérifier SIRET"

**JavaScript** :

- Récupère téléphone + email
- Envoie en AJAX avec SIRET + nom + prénom

**Serveur PHP** :

- Formate téléphone → E164 (`+33612345678`)
- Valide email → RFC compliant
- Retourne erreur si invalide
- Injecte dans `$representant`

### 3. Réinjection automatique

**JavaScript** :

- Remplit les champs avec données formatées
- Champ téléphone : `+33612345678`
- Champ email : `test@example.com`

### 4. Surveillance temps réel

**Au blur (sortie champ)** :

- Téléphone → Formatage E164 + feedback visuel
- Email → Validation regex + feedback visuel

**Messages feedback** :

- ✅ Vert : "Numéro formaté en E164" / "Format email valide"
- ❌ Rouge : "Format invalide (10 chiffres requis)" / "Format email invalide"

---

## 🧪 Tests rapides

### Test 1 : Téléphone valide

1. Saisir : `06 12 34 56 78`
2. Cliquer "Vérifier SIRET"
3. **Résultat attendu** : Champ affiche `+33612345678`

### Test 2 : Téléphone invalide

1. Saisir : `123`
2. Sortir du champ (blur)
3. **Résultat attendu** : Message rouge "Format invalide (10 chiffres requis)"

### Test 3 : Email valide

1. Saisir : `test@example.com`
2. Cliquer "Vérifier SIRET"
3. **Résultat attendu** : Email validé et réinjecté

### Test 4 : Email invalide

1. Saisir : `test@`
2. Sortir du champ (blur)
3. **Résultat attendu** : Message rouge "Format email invalide"

---

## 📋 Checklist déploiement

- [x] PhoneFormatter créé
- [x] SanitizationHelper étendu
- [x] AjaxHandler modifié
- [x] form-frontend.js modifié
- [x] FieldMapper modifié
- [x] Styles CSS ajoutés
- [x] Version incrémentée (1.1.0)
- [x] Changelog mis à jour
- [ ] Tests manuels sur DDEV
- [ ] Déploiement production

---

## 🚀 Déploiement

```powershell
# Depuis le dossier racine du projet
python git/deploy_complete.py
```

---

## 📞 En cas de problème

**Logs serveur** :

```bash
tail -f wp-content/debug.log | grep -E "\[PhoneFormatter\]|\[EmailValidator\]|\[AJAX\]"
```

**Logs client** :
Console JavaScript (F12) → Rechercher `[WCQF Frontend]`

**Support** : contact@tb-web.fr

---

**Prêt pour déploiement** ✅
