# 🚨 Documentation CRITIQUE : Structure Réponse AJAX SIREN

**Version** : 1.0.0  
**Date** : 2025-10-06  
**Auteur** : TB-Web  
**Statut** : ⚠️ **NE JAMAIS MODIFIER SANS LIRE CE DOCUMENT**

---

## 📋 Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Structure de la réponse](#structure-de-la-réponse)
3. [Pièges critiques à éviter](#pièges-critiques-à-éviter)
4. [Mapping des champs](#mapping-des-champs)
5. [Débogage](#débogage)
6. [Tests obligatoires](#tests-obligatoires)

---

## Vue d'ensemble

### Fichiers concernés

**Backend** :

- `src/Form/GravityForms/AjaxHandler.php` (ligne 240-264)
- `src/Form/GravityForms/FieldMapper.php`
- `src/Helpers/AjaxHelper.php`

**Frontend** :

- `assets/js/form-frontend.js` (ligne 125-195)

### Flux de données

```
1. Utilisateur entre SIRET
   ↓
2. AJAX → AjaxHandler::handle_verify_siret()
   ↓
3. API SIREN → Récupération données entreprise
   ↓
4. FieldMapper → Mapping des données vers champs GF
   ↓
5. MentionsGenerator → Génération mentions légales
   ↓
6. Construction réponse AJAX (POINT CRITIQUE ⚠️)
   ↓
7. Frontend reçoit → Remplit champs formulaire
```

---

## Structure de la réponse

### ✅ Structure CORRECTE (actuelle)

**Backend** (`AjaxHandler.php` ligne 240-264) :

```php
// Préparer la réponse finale SANS double wrapping
// Structure attendue côté JS : response.data.xxx
// ATTENTION : Utiliser + au lieu de array_merge pour préserver les clés numériques !
$response = $mapped_data + array(
    'denomination'     => $company_data['denomination'] ?? '',
    'est_actif'        => $company_data['is_active'] ?? true,
    'type_entreprise'  => $company_data['type_entreprise'] ?? '',
    'message'          => $message,
    'representant'     => $representant,
);

// Appeler directement wp_send_json_success pour éviter le double wrapping
// Structure finale : {success: true, data: {champs + métadonnées}}
\wp_send_json_success( $response );
```

**Résultat côté frontend** :

```javascript
{
  success: true,
  data: {
    // Champs du formulaire (clés numériques préservées)
    '1': '811 074 699 00034',        // SIRET
    '12': 'POURQUOIBOUGER.COM',      // Dénomination
    '13': 'Mentions légales...',     // Mentions légales
    '8.1': '1 RUE DE GESVRES',       // Adresse
    '8.5': '60000',                  // Code postal
    '8.3': 'BEAUVAIS',               // Ville
    '10': '8559A',                   // Code APE
    '11': 'Autres enseignements',    // Libellé APE
    '14': '2015-01-01',              // Date création
    '15': 'Actif',                   // Statut

    // Métadonnées (clés string)
    'denomination': 'POURQUOIBOUGER.COM',
    'est_actif': true,
    'type_entreprise': 'pm',
    'message': 'Entreprise trouvée : POURQUOIBOUGER.COM',
    'representant': {
      'prenom': 'Jean',
      'nom': 'DUPONT'
    }
  }
}
```

**Frontend utilise** (`form-frontend.js` ligne 130) :

```javascript
// Remplir les champs du formulaire
this.fillFormFields(formId, response.data);

// Accéder aux métadonnées
const estActif = response.data.est_actif;
const denomination = response.data.denomination;
```

---

## Pièges critiques à éviter

### ⚠️ PIÈGE #1 : array_merge() réindexe les clés numériques

**❌ CODE INCORRECT** (cassera le mapping) :

```php
$response = array_merge(
    $mapped_data,  // ['1' => 'val', '12' => 'val', '13' => 'val']
    array(
        'denomination' => '...',
        'est_actif' => true
    )
);
```

**Résultat** :

```php
// array_merge() RÉINDEXE les clés numériques !
[
    0 => 'val',        // était '1'
    1 => 'val',        // était '12'
    2 => 'val',        // était '13'
    'denomination' => '...',
    'est_actif' => true
]
```

**Conséquence** :

- Les champs se remplissent aux mauvais endroits
- Mentions légales dans champ 6 au lieu de 13
- Formulaire cassé

**✅ CODE CORRECT** :

```php
$response = $mapped_data + array(
    'denomination' => '...',
    'est_actif' => true
);
```

**Résultat** :

```php
// Opérateur + PRÉSERVE les clés numériques !
[
    '1' => 'val',
    '12' => 'val',
    '13' => 'val',
    'denomination' => '...',
    'est_actif' => true
]
```

---

### ⚠️ PIÈGE #2 : Double wrapping avec AjaxHelper

**❌ CODE INCORRECT** (créera 3 niveaux de `data`) :

```php
$response = array(
    'data' => $mapped_data,
    'denomination' => '...',
);

AjaxHelper::send_success( $response, 'Message' );
```

**Résultat** :

```javascript
// WordPress ajoute automatiquement un niveau 'data'
{
  success: true,
  data: {           // Niveau 1 ajouté par wp_send_json_success()
    data: {         // Niveau 2 ajouté par AjaxHelper::send_success()
      data: {       // Niveau 3 dans $response
        '1': 'val'
      }
    }
  }
}
```

**Conséquence** :

- Frontend cherche dans `response.data.est_actif` → `undefined`
- Vraie valeur dans `response.data.data.data.est_actif` → impossible à trouver

**✅ CODE CORRECT** :

```php
$response = $mapped_data + array(
    'denomination' => '...',
);

// Appeler DIRECTEMENT wp_send_json_success()
\wp_send_json_success( $response );
```

---

### ⚠️ PIÈGE #3 : Mauvais accès aux données côté frontend

**Structure réponse** :

```javascript
{
  success: true,
  data: {
    '1': 'SIRET',
    'est_actif': true
  }
}
```

**❌ CODE INCORRECT** :

```javascript
// NE FONCTIONNE PAS
this.fillFormFields(formId, response.data.data); // undefined
const estActif = response.data.data.est_actif; // undefined
```

**✅ CODE CORRECT** :

```javascript
// Tout est dans response.data directement
this.fillFormFields(formId, response.data);
const estActif = response.data.est_actif;
```

---

## Mapping des champs

### Mapping par défaut (Formulaire ID 1)

**Défini dans** : `FieldMapper.php` ligne 58-71

```php
private const DEFAULT_MAPPING = array(
    'siret'            => '1',     // SIRET formaté
    'denomination'     => '12',    // Raison sociale
    'adresse'          => '8.1',   // Numéro + voie
    'code_postal'      => '8.5',   // Code postal
    'ville'            => '8.3',   // Ville
    'code_ape'         => '10',    // Code APE
    'libelle_ape'      => '11',    // Libellé APE
    'date_creation'    => '14',    // Date de création
    'statut_actif'     => '15',    // Actif/Inactif
    'mentions_legales' => '13',    // ⚠️ CRITIQUE : Mentions légales
    'prenom'           => '7.3',   // Prénom représentant
    'nom'              => '7.6',   // Nom représentant
);
```

### ⚠️ ATTENTION : Mentions légales

**Champ HTML** : `#input_1_13` (ID formulaire 1, champ 13)

**Placeholder** : `{REPRESENTANT}` doit être présent dans le template

**JavaScript** (`form-frontend.js` ligne 248) :

```javascript
const $mentionsField = $("#input_" + formId + "_13");
```

**Si le champ n'est PAS 13** :

1. Modifier `DEFAULT_MAPPING` dans `FieldMapper.php`
2. Modifier la ligne 248 dans `form-frontend.js`
3. Tester avec console.log pour vérifier

---

## Débogage

### Activer les logs de debug

**Console JavaScript** (F12) :

```javascript
// Logs déjà présents dans le code
[WCQF] Réponse AJAX complète: {...}
[WCQF] Champs API remplis: {...}
[WCQF DEBUG] est_actif reçu: true
[WCQF DEBUG] Type de est_actif: boolean
```

**Logs backend** (WooCommerce) :

```bash
# Local DDEV
tail -f ~/projects/tb-wp-dev/web/wp-content/uploads/wc-logs/wc-qualiopi-formation-*.log

# Production
ssh user@server
tail -f /path/to/wp-content/uploads/wc-logs/wc-qualiopi-formation-*.log
```

### Checklist de débogage

#### 1. Vérifier la structure de la réponse

**Console JavaScript** :

```javascript
// Après avoir cliqué sur "Vérifier SIRET"
// Regarder : [WCQF] Réponse AJAX complète

// ✅ BON : Clés numériques préservées
{
  '1': 'SIRET',
  '12': 'Denom',
  '13': 'Mentions',
  'est_actif': true
}

// ❌ MAUVAIS : Clés réindexées
{
  0: 'SIRET',
  1: 'Denom',
  2: 'Mentions',
  'est_actif': true
}
```

**Si réindexées** → Vérifier `AjaxHandler.php` ligne 243 :

- Doit utiliser `+` et non `array_merge()`

#### 2. Vérifier que `est_actif` est accessible

**Console JavaScript** :

```javascript
// Chercher : [WCQF DEBUG] est_actif reçu:

// ✅ BON
est_actif reçu: true

// ❌ MAUVAIS
est_actif reçu: undefined
```

**Si undefined** → Vérifier :

1. Backend utilise bien `\wp_send_json_success()` directement
2. Frontend accède à `response.data.est_actif` (pas `response.data.data`)

#### 3. Vérifier les mentions légales

**Console JavaScript** :

```javascript
// Chercher : [WCQF] Mentions AVANT remplacement:

// ✅ BON : Texte complet affiché
Mentions AVANT remplacement: "POURQUOIBOUGER.COM, dont le siège social..."

// ❌ MAUVAIS : Vide
Mentions AVANT remplacement:
```

**Si vide** → Vérifier :

1. Mapping : champ 13 correct dans `DEFAULT_MAPPING`
2. Clés préservées (pas de `array_merge()`)
3. JavaScript cherche bien `#input_1_13`

#### 4. Vérifier le remplacement du placeholder

**Console JavaScript** :

```javascript
// Chercher : Placeholder {REPRESENTANT}

// ✅ BON
[WCQF] Mentions APRÈS remplacement: "...représenté par DUPONT Jean..."

// ❌ MAUVAIS
[WCQF] Placeholder {REPRESENTANT} non trouvé dans les mentions
```

**Si non trouvé** → Vérifier :

1. Template mentions contient `{REPRESENTANT}`
2. Mentions bien remplies dans le champ
3. Format : "NOM Prénom" (ligne 268 `form-frontend.js`)

---

## Tests obligatoires

### Avant de déployer

#### Test 1 : Remplissage des champs

**Action** : Entrer SIRET `81107469900034`

**Vérifier** :

- [ ] Champ 1 (SIRET) : `811 074 699 00034`
- [ ] Champ 12 (Dénomination) : `POURQUOIBOUGER.COM`
- [ ] Champ 13 (Mentions) : Texte complet avec `{REPRESENTANT}`
- [ ] Champ 8.1 (Adresse) : `1 RUE DE GESVRES`
- [ ] Champ 8.5 (CP) : `60000`
- [ ] Champ 8.3 (Ville) : `BEAUVAIS`
- [ ] Champ 10 (Code APE) : valeur présente
- [ ] Champ 11 (Libellé APE) : valeur présente
- [ ] Champ 15 (Statut) : `Actif`

#### Test 2 : Statut entreprise active

**Action** : Entrer SIRET valide d'entreprise active

**Vérifier** :

- [ ] Message de succès (fond vert)
- [ ] **AUCUN** warning "entreprise inactive"
- [ ] Console : `est_actif reçu: true`
- [ ] Console : `Type de est_actif: boolean`

#### Test 3 : Replacement du représentant

**Action** :

1. Entrer SIRET
2. Modifier Prénom : `Jean`
3. Modifier Nom : `DUPONT`

**Vérifier** :

- [ ] Champ 13 (Mentions) mis à jour automatiquement
- [ ] `{REPRESENTANT}` remplacé par `DUPONT Jean`
- [ ] Console : `Mentions APRÈS remplacement:` avec texte complet

#### Test 4 : Entreprise inactive

**Action** : Entrer SIRET d'entreprise inactive (si disponible)

**Vérifier** :

- [ ] Message de warning (fond orange)
- [ ] Warning "⚠️ Cette entreprise est inactive."
- [ ] Console : `est_actif reçu: false`
- [ ] Champs quand même remplis

---

## Historique des bugs

### 2025-10-06 : Bug array_merge()

**Symptôme** : Mentions légales dans mauvais champ (6 au lieu de 13)

**Cause** : Utilisation de `array_merge()` qui réindexe les clés numériques

**Solution** : Remplacer par opérateur `+`

**Commit** : [hash]

**Fichiers modifiés** :

- `src/Form/GravityForms/AjaxHandler.php` (ligne 243)

### 2025-10-06 : Bug double wrapping

**Symptôme** : `est_actif` undefined côté frontend

**Cause** : Double wrapping avec `AjaxHelper::send_success()`

**Solution** : Appeler directement `\wp_send_json_success()`

**Commit** : [hash]

**Fichiers modifiés** :

- `src/Form/GravityForms/AjaxHandler.php` (ligne 264)
- `assets/js/form-frontend.js` (ligne 130, 168, 179, 186)

### 2025-10-06 : Bug warning entreprise inactive

**Symptôme** : Warning affiché même pour entreprise active

**Cause** : JavaScript cherchait `response.data.data.est_actif` au lieu de `response.data.est_actif`

**Solution** : Corriger accès dans JavaScript

**Commit** : [hash]

**Fichiers modifiés** :

- `assets/js/form-frontend.js` (ligne 168, 179, 186)

---

## ⚠️ Règles d'or

### Backend

1. **TOUJOURS** utiliser `+` pour fusionner `$mapped_data` avec métadonnées
2. **JAMAIS** utiliser `array_merge()` avec des clés numériques
3. **TOUJOURS** appeler `\wp_send_json_success()` directement
4. **JAMAIS** passer par `AjaxHelper::send_success()` pour cette réponse
5. **TOUJOURS** logger `'est_actif'` dans les logs pour vérifier

### Frontend

1. **TOUJOURS** accéder à `response.data` directement
2. **JAMAIS** utiliser `response.data.data` (sauf si structure change)
3. **TOUJOURS** vérifier `response.data.est_actif` pour le statut
4. **TOUJOURS** logger la réponse complète en debug
5. **TOUJOURS** tester avec console ouverte

### Tests

1. **TOUJOURS** tester avec un SIRET actif ET inactif
2. **TOUJOURS** vérifier que les champs se remplissent correctement
3. **TOUJOURS** vérifier le champ 13 (mentions légales)
4. **TOUJOURS** tester le remplacement de `{REPRESENTANT}`
5. **TOUJOURS** vérifier la console pour les erreurs

---

## 🆘 En cas de problème

### Le formulaire ne se remplit pas

1. Ouvrir console JavaScript (F12)
2. Chercher `[WCQF] Réponse AJAX complète`
3. Vérifier si clés numériques (`'1'`, `'12'`, etc.) ou réindexées (`0`, `1`, etc.)
4. Si réindexées → Bug `array_merge()` dans `AjaxHandler.php` ligne 243

### Warning "entreprise inactive" incorrect

1. Ouvrir console JavaScript (F12)
2. Chercher `[WCQF DEBUG] est_actif reçu:`
3. Si `undefined` → Problème d'accès dans `form-frontend.js`
4. Si `false` alors que devrait être `true` → Bug backend dans `SirenValidator.php`

### Mentions légales vides

1. Ouvrir console JavaScript (F12)
2. Chercher `[WCQF] Mentions AVANT remplacement:`
3. Si vide → Champ probablement mal mappé
4. Vérifier que mentions sont bien dans la réponse (ligne 127)
5. Vérifier clés préservées (pas de `array_merge()`)

### Placeholder {REPRESENTANT} non remplacé

1. Ouvrir console JavaScript (F12)
2. Chercher `Placeholder {REPRESENTANT} non trouvé`
3. Vérifier template mentions contient bien `{REPRESENTANT}`
4. Vérifier `MentionsGenerator.php` génère bien le placeholder

---

## 📚 Ressources

**Documentation PHP** :

- [array_merge()](https://www.php.net/manual/fr/function.array-merge.php)
- [Array union operator (+)](https://www.php.net/manual/fr/language.operators.array.php)

**Documentation WordPress** :

- [wp_send_json_success()](https://developer.wordpress.org/reference/functions/wp_send_json_success/)

**Logs** :

- WooCommerce Logger : `wp-content/uploads/wc-logs/`
- Console navigateur : F12 → Console

---

**📅 Dernière mise à jour** : 2025-10-06  
**✍️ Mainteneur** : TB-Web  
**⚠️ Statut** : Document critique - NE PAS SUPPRIMER
