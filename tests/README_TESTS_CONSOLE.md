# 🧪 Tests Console - Guide d'utilisation

## 📋 Vue d'ensemble

Script de tests JavaScript pour tester rapidement tous les scénarios de validation du formulaire Gravity Forms avec formatage téléphone/email.

**Version** : 1.1.0  
**Fichier** : `tests/console-tests.js`

---

## 🚀 Utilisation rapide

### 1. Ouvrir la console DevTools

- Appuyez sur **F12** ou **Ctrl+Shift+I**
- Allez dans l'onglet **Console**

### 2. Copier-coller le script

- Ouvrez le fichier `tests/console-tests.js`
- **Copiez tout le contenu** (Ctrl+A puis Ctrl+C)
- **Collez dans la console** (Ctrl+V)
- **Appuyez sur Entrée**

### 3. Lancer les tests

```javascript
// Afficher l'aide
WCQFTests.help();

// Lancer TOUS les tests (13 tests, ~30 secondes)
WCQFTests.runAll();

// Lancer uniquement les tests de validation (erreurs attendues)
WCQFTests.runValidationTests();

// Lancer uniquement les tests de succès
WCQFTests.runSuccessTests();

// Lancer un test spécifique
WCQFTests.test1_valid();
```

---

## 📝 Liste des tests

### ✅ Tests de succès (4)

| Test                       | Description             | Résultat attendu      |
| -------------------------- | ----------------------- | --------------------- |
| `test1_valid()`            | Tous les champs valides | Entreprise trouvée    |
| `test11_phone_e164()`      | Téléphone déjà en E164  | Accepté tel quel      |
| `test12_email_uppercase()` | Email en majuscules     | Formaté en minuscules |
| `test13_phone_formatted()` | Téléphone avec points   | Formaté en E164       |

### ❌ Tests de validation (9)

| Test                           | Description            | Message d'erreur attendu                       |
| ------------------------------ | ---------------------- | ---------------------------------------------- |
| `test2_phone_empty()`          | Téléphone vide         | ⚠️ Veuillez renseigner le numéro de téléphone. |
| `test3_email_empty()`          | Email vide             | ⚠️ Veuillez renseigner l'adresse email.        |
| `test4_name_empty()`           | Nom vide               | ⚠️ Veuillez renseigner le nom et le prénom...  |
| `test5_firstname_empty()`      | Prénom vide            | ⚠️ Veuillez renseigner le nom et le prénom...  |
| `test6_siret_invalid_format()` | SIRET format incorrect | Erreur SIRET invalide                          |
| `test7_siret_not_found()`      | SIRET inexistant       | Aucune entreprise trouvée                      |
| `test8_phone_invalid()`        | Téléphone trop court   | Le numéro doit contenir 10 chiffres            |
| `test9_email_invalid()`        | Email invalide         | Le format de l'email n'est pas valide          |
| `test10_name_with_numbers()`   | Nom avec chiffres      | Les chiffres ne sont pas autorisés             |

---

## ⚙️ Configuration

### Modifier le délai entre tests

```javascript
// Défaut : 2000ms (2 secondes)
WCQFTests.delay = 3000; // Passer à 3 secondes
```

### Modifier l'ID du formulaire

```javascript
// Défaut : 1
WCQFTests.formId = 2; // Si votre formulaire a l'ID 2
```

---

## 🔧 Utilitaires

```javascript
// Vider tous les champs
WCQFTests.clearAll();

// Remplir un champ spécifique
WCQFTests.fillField("9", "+33614287151"); // Téléphone
WCQFTests.fillField("10", "test@example.com"); // Email

// Cliquer sur "Vérifier SIRET"
WCQFTests.clickVerify();
```

---

## 📊 Exemple de sortie console

```
🚀 DÉMARRAGE DE LA SUITE DE TESTS WC QUALIOPI FORMATION v1.1.0
⏱️  Délai entre tests : 2000ms

============================================================
📋 TEST 1 : Tous les champs valides
============================================================
✓ Champ 1 rempli avec: 81107469900034
✓ Champ 7_3 rempli avec: Gabriel
✓ Champ 7_6 rempli avec: Duteurtre
✓ Champ 9 rempli avec: 0614287151
✓ Champ 10 rempli avec: gabriel.duteurtre@gmail.com
🔍 Clic sur "Vérifier SIRET"...
✅ Résultat attendu : Entreprise trouvée, tous les champs remplis

⏳ Attente de 2000ms avant le test suivant...

============================================================
📋 TEST 2 : Téléphone vide
============================================================
...
```

---

## 🎯 Données de test

### SIRET valides

- **81107469900034** : POURQUOIBOUGER.COM (existe, actif)
- **81107469900033** : Format valide mais entreprise n'existe pas

### Téléphones de test

- **0614287151** : Format national valide
- **+33614287151** : Format E164 valide
- **06.14.28.71.51** : Format avec points
- **0612** : Invalide (trop court)

### Emails de test

- **gabriel.duteurtre@gmail.com** : Valide
- **GABRIEL.DUTEURTRE@GMAIL.COM** : Valide (sera formaté en minuscules)
- **invalid-email** : Invalide

---

## 🐛 Dépannage

### Les champs ne se remplissent pas

- Vérifiez que vous êtes sur la bonne page avec le formulaire
- Vérifiez l'ID du formulaire : `WCQFTests.formId = X`

### Les tests ne s'exécutent pas

- Assurez-vous d'avoir collé **tout le script**
- Vérifiez qu'il n'y a pas d'erreurs JavaScript dans la console

### Le bouton "Vérifier SIRET" n'est pas trouvé

- Vérifiez que le plugin est bien activé
- Vérifiez que le formulaire a un mapping configuré

---

## 📚 Workflow recommandé

### 1. Test rapide après modification

```javascript
// Vider la console
console.clear();

// Tester uniquement les cas de succès
WCQFTests.runSuccessTests();
```

### 2. Test complet avant déploiement

```javascript
// Vider la console
console.clear();

// Tout tester (prend ~30 secondes)
WCQFTests.runAll();
```

### 3. Debug d'un problème spécifique

```javascript
// Vider la console
console.clear();

// Tester le scénario problématique
WCQFTests.test8_phone_invalid();

// Attendre le résultat, puis refaire le test avec des variations
WCQFTests.fillField("9", "061428");
WCQFTests.clickVerify();
```

---

## ✅ Checklist de validation

Avant de considérer les tests comme réussis, vérifiez :

- [ ] Tous les tests de succès passent (4/4)
- [ ] Tous les tests de validation affichent le bon message d'erreur (9/9)
- [ ] Les champs téléphone/email sont bien formatés automatiquement
- [ ] Aucune erreur JavaScript dans la console
- [ ] Les logs serveur montrent les bons messages (optionnel)

---

## 🔄 Mise à jour du script

Si vous modifiez la logique de validation :

1. Ouvrez `tests/console-tests.js`
2. Ajoutez un nouveau test avec `testXX_description: async function() { ... }`
3. Ajoutez-le à la liste dans `runAll()`
4. Rechargez le script dans la console

---

**Bon testing ! 🚀**
