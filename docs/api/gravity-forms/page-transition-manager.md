# PageTransitionManager - Documentation API

**Version** : 1.1.0  
**Namespace** : `WcQualiopiFormation\Form\GravityForms`  
**Fichier** : `src/Form/GravityForms/PageTransitionManager.php`

---

## 📋 Vue d'Ensemble

`PageTransitionManager` est un système de détection centralisée des transitions de pages Gravity Forms. Il permet de détecter **TOUTES** les transitions (forward et backward) et de déclencher une action WordPress pour permettre l'ajout de handlers personnalisés.

### Architecture Manager/Handlers

```
┌─────────────────────────────────────────────────────────────┐
│                    GRAVITY FORMS                            │
│              Hook: gform_post_paging                        │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ↓
┌─────────────────────────────────────────────────────────────┐
│           PageTransitionManager                             │
│  Responsabilité : Détecter + Préparer + Déclencher          │
└─────────────────────┬───────────────────────────────────────┘
                      │ do_action('wcqf_page_transition')
                      ↓
┌─────────────────────────────────────────────────────────────┐
│          Action WordPress : wcqf_page_transition            │
└──────────┬──────────┬──────────┬──────────┬─────────────────┘
           │          │          │          │
    ┌──────▼──┐  ┌───▼────┐ ┌───▼────┐ ┌──▼────────┐
    │Handler 1│  │Handler2│ │Handler3│ │Handler N  │
    └─────────┘  └────────┘ └────────┘ └───────────┘
```

---

## 🎯 Objectif

Fournir un point d'extension unique pour toutes les actions basées sur les transitions de pages Gravity Forms, sans dupliquer la logique de détection.

### Cas d'Usage

- Calculer le score d'un test de positionnement (page 2→3)
- Déclencher une API de signature électronique (page 4→5)
- Créer automatiquement un compte utilisateur (page 5→6)
- Sauvegarder les données intermédiaires en BDD
- Analytics de navigation dans le tunnel

---

## 🔌 Action WordPress

### `wcqf_page_transition`

**Description** : Action déclenchée à chaque transition de page Gravity Forms.

**Signature** :

```php
do_action( 'wcqf_page_transition', array $transition_data );
```

**Paramètres** :

| Paramètre          | Type    | Description                                                                        |
| ------------------ | ------- | ---------------------------------------------------------------------------------- |
| `$transition_data` | `array` | Tableau associatif contenant toutes les données de la transition (voir ci-dessous) |

---

## 📦 Structure du Payload

L'action `wcqf_page_transition` fournit un tableau associatif complet avec 11 champs :

```php
array(
    // Métadonnées formulaire
    'form_id'         => (int) ID du formulaire Gravity Forms,
    'form_title'      => (string) Titre du formulaire,

    // Métadonnées entrée
    'entry_id'        => (int) ID de l'entrée GF (unique par utilisateur),
    'token'           => (string) Token HMAC du plugin (champ 9999),

    // Navigation
    'from_page'       => (int) Numéro de la page source,
    'to_page'         => (int) Numéro de la page cible,
    'direction'       => (string) 'forward' (avancer) ou 'backward' (reculer),

    // Données complètes
    'submission_data' => (array) Toutes les données de soumission GF,
    'form'            => (array) Objet formulaire GF complet,

    // Contexte
    'timestamp'       => (string) Timestamp MySQL format (YYYY-MM-DD HH:MM:SS),
    'user_ip'         => (string) Adresse IP de l'utilisateur,
    'user_id'         => (int) ID utilisateur WordPress (0 si invité),
)
```

### Exemple de Payload

```php
array(
    'form_id'         => 5,
    'form_title'      => 'Formulaire d\'inscription Qualiopi',
    'entry_id'        => 123,
    'token'           => 'hmac_abc123xyz789',
    'from_page'       => 2,
    'to_page'         => 3,
    'direction'       => 'forward',
    'submission_data' => array(
        'id'   => 123,
        '27'   => '15',  // Score du test
        '9999' => 'hmac_abc123xyz789',
        // ... tous les autres champs
    ),
    'form'            => array(
        'id'     => 5,
        'title'  => 'Formulaire d\'inscription Qualiopi',
        'fields' => array( /* ... */ ),
        // ... objet GF complet
    ),
    'timestamp'       => '2025-10-09 14:30:00',
    'user_ip'         => '192.168.1.100',
    'user_id'         => 42,
)
```

---

## 💻 Exemples d'Utilisation

### Exemple 1 : Handler Basique

Écouter toutes les transitions et logger :

```php
<?php
add_action( 'wcqf_page_transition', 'mon_logger_transitions', 10, 1 );

function mon_logger_transitions( $transition_data ) {
    error_log( sprintf(
        'Transition détectée : Form %d, %d → %d (%s)',
        $transition_data['form_id'],
        $transition_data['from_page'],
        $transition_data['to_page'],
        $transition_data['direction']
    ) );
}
```

### Exemple 2 : Handler Spécifique (Page 2→3)

Traiter uniquement une transition spécifique :

```php
<?php
add_action( 'wcqf_page_transition', 'mon_handler_test_positionnement', 10, 1 );

function mon_handler_test_positionnement( $transition_data ) {
    // Filtrer : uniquement page 2 → 3 forward
    if ( $transition_data['from_page'] !== 2
         || $transition_data['to_page'] !== 3
         || $transition_data['direction'] !== 'forward' ) {
        return; // Ignorer les autres transitions
    }

    // Récupérer le score du test (champ ID 27)
    $score = $transition_data['submission_data']['27'] ?? 0;

    // Logique métier
    if ( $score >= 15 ) {
        // Admission directe
        do_action( 'mon_plugin_admission_acceptee', $transition_data );
    } else {
        // Refus ou accompagnement renforcé
        do_action( 'mon_plugin_admission_refusee', $transition_data );
    }
}
```

### Exemple 3 : Handler Multiple Transitions

Traiter plusieurs transitions dans un même handler :

```php
<?php
add_action( 'wcqf_page_transition', 'mon_handler_multiples', 10, 1 );

function mon_handler_multiples( $transition_data ) {
    // Ignorer backward (retour en arrière)
    if ( $transition_data['direction'] === 'backward' ) {
        return;
    }

    // Switch selon la page cible
    switch ( $transition_data['to_page'] ) {
        case 3:
            // Après le test de positionnement
            traiter_fin_test( $transition_data );
            break;

        case 4:
            // Page signature électronique
            declencher_yousign( $transition_data );
            break;

        case 5:
            // Dernière page : créer compte utilisateur
            creer_compte_utilisateur( $transition_data );
            break;
    }
}
```

### Exemple 4 : Sauvegarder en BDD

Enregistrer toutes les transitions pour analytics :

```php
<?php
add_action( 'wcqf_page_transition', 'sauvegarder_transition', 10, 1 );

function sauvegarder_transition( $transition_data ) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'wcqf_transitions',
        array(
            'form_id'   => $transition_data['form_id'],
            'entry_id'  => $transition_data['entry_id'],
            'from_page' => $transition_data['from_page'],
            'to_page'   => $transition_data['to_page'],
            'direction' => $transition_data['direction'],
            'user_ip'   => $transition_data['user_ip'],
            'user_id'   => $transition_data['user_id'],
            'timestamp' => $transition_data['timestamp'],
        ),
        array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
    );
}
```

---

## 🔐 Sécurité et Bonnes Pratiques

### 1. Sanitization des Données

**Le Manager sanitize déjà** les données sensibles, mais vous devez toujours valider et sanitizer dans votre handler :

```php
function mon_handler( $transition_data ) {
    // Valider
    if ( empty( $transition_data['entry_id'] ) ) {
        return;
    }

    // Sanitizer supplémentaire si nécessaire
    $user_email = sanitize_email( $transition_data['submission_data']['5'] ?? '' );
}
```

### 2. Fail-Safe

**Ne JAMAIS bloquer le formulaire** en cas d'erreur :

```php
function mon_handler( $transition_data ) {
    try {
        // Votre logique
    } catch ( Exception $e ) {
        // Logger l'erreur SANS stopper le formulaire
        error_log( 'Erreur handler : ' . $e->getMessage() );
        return; // Fail-safe : continuer silencieusement
    }
}
```

### 3. Performance

**Opérations lourdes en asynchrone** :

```php
function mon_handler( $transition_data ) {
    // ✅ BON : Programmer une tâche asynchrone
    as_schedule_single_action( time() + 10, 'mon_action_async', array( $transition_data ) );

    // ❌ MAUVAIS : Appel API synchrone long
    // wp_remote_post( 'https://api-lente.com/endpoint', ... ); // Bloque le formulaire !
}
```

### 4. Priorité des Hooks

**Ordre d'exécution** :

- Priorité **5** : Handlers critiques (validation, blocage si nécessaire)
- Priorité **10** (défaut) : Handlers normaux (logique métier)
- Priorité **20** : Handlers secondaires (analytics, logs)

```php
add_action( 'wcqf_page_transition', 'handler_critique', 5, 1 );
add_action( 'wcqf_page_transition', 'handler_normal', 10, 1 );
add_action( 'wcqf_page_transition', 'handler_analytics', 20, 1 );
```

---

## 🧪 Tests

### Tester votre Handler

```php
// Simuler une transition pour tests
$test_transition_data = array(
    'form_id'         => 5,
    'entry_id'        => 999,
    'from_page'       => 2,
    'to_page'         => 3,
    'direction'       => 'forward',
    'submission_data' => array( '27' => '15' ),
    'form'            => array( 'id' => 5 ),
    'timestamp'       => current_time( 'mysql' ),
    'user_ip'         => '127.0.0.1',
    'user_id'         => 1,
);

do_action( 'wcqf_page_transition', $test_transition_data );
```

---

## 🔄 Hooks Disponibles

### Actions

| Hook                   | Paramètres                                                        | Description                                           |
| ---------------------- | ----------------------------------------------------------------- | ----------------------------------------------------- |
| `wcqf_page_transition` | `array $transition_data`                                          | Déclenché à chaque transition de page GF              |
| `wcqf_test_completed`  | `float $score, string $path, array $submission_data, array $form` | Déclenché après calcul score test 2→3 (Handler dédié) |

### Filtres

Aucun filtre pour l'instant. Le système est basé sur des actions pour permettre l'ajout de handlers sans modifier le comportement existant.

---

## 📊 Observabilité

### Logs Automatiques

Le Manager log automatiquement chaque transition :

```
[INFO] [PageTransitionManager] Transition detected
  form_id: 5
  from_page: 2
  to_page: 3
  direction: forward
  entry_id: 123

[DEBUG] [PageTransitionManager] Payload built
  payload_keys: ['form_id', 'entry_id', ...]
  data_size: 15
  has_token: true
```

### Vérifier les Logs

```bash
# DDEV
ddev exec tail -f web/wp-content/debug.log | grep "PageTransitionManager"

# Production
tail -f wp-content/debug.log | grep "PageTransitionManager"
```

---

## ❓ FAQ

### Comment bloquer une transition ?

**Réponse** : Ce n'est pas l'objectif du Manager. Pour bloquer une transition, utilisez les hooks Gravity Forms (`gform_validation`) ou les règles conditionnelles.

### Puis-je modifier les données avant qu'elles soient passées aux autres handlers ?

**Réponse** : Non, le payload est en lecture seule. Si vous devez modifier des données, utilisez les hooks GF natifs ou créez votre propre système.

### Que se passe-t-il si mon handler plante ?

**Réponse** : Le Manager a un système **fail-safe** : toute exception est loggée (CRITICAL) mais ne bloque PAS le formulaire.

### Comment désactiver le Manager ?

**Réponse** : Ne pas instancier `PageTransitionManager` dans `FormManager.php`. Une option admin est prévue en phase 2.

---

## 📚 Voir Aussi

- [Guide d'extension des transitions](../../development/extending-page-transitions.md)
- [PageTransitionHandler (exemple de handler)](../../../src/Form/GravityForms/PageTransitionHandler.php)
- [CalculationRetriever (récupération scores GF)](../../../src/Form/GravityForms/CalculationRetriever.php)

---

**Version** : 1.0  
**Dernière mise à jour** : 09/10/2025  
**Auteur** : TB-Web
