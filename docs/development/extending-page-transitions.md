# Guide d'Extension - Page Transitions

**Version** : 1.1.0  
**Audience** : Développeurs WordPress souhaitant ajouter des handlers personnalisés

---

## 📋 Vue d'Ensemble

Ce guide vous explique comment créer vos propres **handlers de transitions** pour exécuter des actions personnalisées lors de la navigation dans un formulaire Gravity Forms multi-pages.

### Prérequis

- Connaissance de base de WordPress (actions/filtres)
- Connaissance de Gravity Forms
- PHP 8.0+
- Plugin `wc_qualiopi_formation` installé et activé

---

## 🏗️ Architecture Manager/Handlers

### Principe

Le système est basé sur une architecture **Manager/Handlers** :

1. **PageTransitionManager** (unique) : Détecte TOUTES les transitions
2. **Handlers** (multiples) : Écoutent l'action et traitent leur cas spécifique

### Avantages

✅ **Pas de duplication** : La logique de détection est centralisée  
✅ **Extensible** : Ajouter un handler = 3 lignes de code  
✅ **Isolé** : Chaque handler est indépendant  
✅ **Testable** : Handlers testables unitairement

---

## 🎯 Créer un Handler Personnalisé

### Étape 1 : Structure de Base

Créez votre classe dans un namespace approprié :

```php
<?php
/**
 * Mon Handler Personnalisé
 *
 * @package MonPlugin
 * @since 1.0.0
 */

namespace MonPlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Classe de gestion d'une transition spécifique
 *
 * Responsabilité unique : Traiter UNIQUEMENT la transition X → Y
 */
class MonHandlerTransition {

    /**
     * Page source
     *
     * @var int
     */
    private const SOURCE_PAGE = 4;

    /**
     * Page cible
     *
     * @var int
     */
    private const TARGET_PAGE = 5;

    /**
     * Constructeur
     */
    public function __construct() {
        $this->init_hooks();

        LoggingHelper::info( '[MonHandlerTransition] Initialized', array(
            'source_page' => self::SOURCE_PAGE,
            'target_page' => self::TARGET_PAGE,
        ) );
    }

    /**
     * Initialise les hooks WordPress
     *
     * @return void
     */
    private function init_hooks(): void {
        add_action( 'wcqf_page_transition', array( $this, 'handle_transition' ), 10, 1 );
    }

    /**
     * Gère la transition spécifique
     *
     * @param array $transition_data Données de transition du Manager.
     * @return void
     */
    public function handle_transition( array $transition_data ): void {
        // Filtrer : uniquement transition X → Y forward
        if ( $transition_data['from_page'] !== self::SOURCE_PAGE
             || $transition_data['to_page'] !== self::TARGET_PAGE
             || $transition_data['direction'] !== 'forward' ) {
            return; // Ignorer silencieusement
        }

        LoggingHelper::info( '[MonHandlerTransition] Transition détectée', array(
            'form_id'  => $transition_data['form_id'],
            'entry_id' => $transition_data['entry_id'],
        ) );

        // VOTRE LOGIQUE MÉTIER ICI
        $this->traiter_transition( $transition_data );
    }

    /**
     * Traite la transition
     *
     * @param array $transition_data Données de transition.
     * @return void
     */
    private function traiter_transition( array $transition_data ): void {
        try {
            // Votre logique ici
            $submission_data = $transition_data['submission_data'];
            $form            = $transition_data['form'];

            // Exemple : récupérer une valeur de champ
            $email = sanitize_email( $submission_data['5'] ?? '' );

            // Exemple : déclencher une action
            do_action( 'mon_plugin_action_custom', $email, $transition_data );

            LoggingHelper::info( '[MonHandlerTransition] Traitement terminé avec succès' );

        } catch ( \Exception $e ) {
            // Fail-safe : Ne JAMAIS bloquer le formulaire
            LoggingHelper::error( '[MonHandlerTransition] Erreur lors du traitement', array(
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
            ) );
        }
    }
}
```

---

## 📦 Exemples de Handlers

### Exemple 1 : Handler API Yousign

Déclencher une signature électronique à la page 4 :

```php
<?php
namespace MonPlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

class YousignTriggerHandler {

    private const SOURCE_PAGE = 4; // Page contrat
    private const TARGET_PAGE = 5; // Page confirmation

    private $yousign_client;

    public function __construct( $yousign_client ) {
        $this->yousign_client = $yousign_client;
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action( 'wcqf_page_transition', array( $this, 'handle_transition' ), 10, 1 );
    }

    public function handle_transition( array $transition_data ): void {
        // Filtrer transition 4 → 5 forward
        if ( $transition_data['from_page'] !== self::SOURCE_PAGE
             || $transition_data['to_page'] !== self::TARGET_PAGE
             || $transition_data['direction'] !== 'forward' ) {
            return;
        }

        LoggingHelper::info( '[YousignTriggerHandler] Contrat validé, déclenchement Yousign', array(
            'entry_id' => $transition_data['entry_id'],
        ) );

        // Programmer l'envoi Yousign en asynchrone
        as_schedule_single_action( time() + 10, 'mon_plugin_send_yousign', array(
            'entry_id'        => $transition_data['entry_id'],
            'user_email'      => $transition_data['submission_data']['5'] ?? '',
            'submission_data' => $transition_data['submission_data'],
        ) );
    }
}
```

### Exemple 2 : Handler Création Compte Utilisateur

Créer automatiquement un compte WordPress :

```php
<?php
namespace MonPlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

class UserAccountCreatorHandler {

    private const SOURCE_PAGE = 5; // Dernière page
    private const TARGET_PAGE = 6; // Page de confirmation finale (ou soumission)

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action( 'wcqf_page_transition', array( $this, 'handle_transition' ), 10, 1 );
    }

    public function handle_transition( array $transition_data ): void {
        // Filtrer transition 5 → 6 forward
        if ( $transition_data['from_page'] !== self::SOURCE_PAGE
             || $transition_data['to_page'] !== self::TARGET_PAGE
             || $transition_data['direction'] !== 'forward' ) {
            return;
        }

        $submission_data = $transition_data['submission_data'];

        // Récupérer email et nom
        $email      = sanitize_email( $submission_data['5'] ?? '' );
        $first_name = sanitize_text_field( $submission_data['1.3'] ?? '' );
        $last_name  = sanitize_text_field( $submission_data['1.6'] ?? '' );

        // Vérifier si utilisateur existe déjà
        if ( email_exists( $email ) ) {
            LoggingHelper::warning( '[UserAccountCreatorHandler] Email déjà existant', array(
                'email' => $email,
            ) );
            return;
        }

        // Créer le compte
        $user_id = wp_create_user( $email, wp_generate_password(), $email );

        if ( is_wp_error( $user_id ) ) {
            LoggingHelper::error( '[UserAccountCreatorHandler] Échec création compte', array(
                'email'   => $email,
                'message' => $user_id->get_error_message(),
            ) );
            return;
        }

        // Mettre à jour les infos
        wp_update_user( array(
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => 'customer', // Rôle WooCommerce
        ) );

        LoggingHelper::info( '[UserAccountCreatorHandler] Compte créé avec succès', array(
            'user_id' => $user_id,
            'email'   => $email,
        ) );

        // Envoyer email de bienvenue (asynchrone)
        as_schedule_single_action( time() + 5, 'mon_plugin_send_welcome_email', array(
            'user_id' => $user_id,
        ) );
    }
}
```

### Exemple 3 : Handler Analytics

Sauvegarder toutes les transitions en BDD :

```php
<?php
namespace MonPlugin\Handlers;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Helpers\LoggingHelper;

class TransitionAnalyticsHandler {

    public function __construct() {
        $this->init_hooks();
        $this->create_table_if_not_exists();
    }

    private function init_hooks(): void {
        // Priorité 20 (après les handlers métier)
        add_action( 'wcqf_page_transition', array( $this, 'save_transition' ), 20, 1 );
    }

    public function save_transition( array $transition_data ): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wcqf_transitions';

        $result = $wpdb->insert(
            $table_name,
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

        if ( $result === false ) {
            LoggingHelper::error( '[TransitionAnalyticsHandler] Échec sauvegarde BDD', array(
                'error' => $wpdb->last_error,
            ) );
        }
    }

    private function create_table_if_not_exists(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'wcqf_transitions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id int(11) NOT NULL,
            entry_id int(11) NOT NULL,
            from_page int(11) NOT NULL,
            to_page int(11) NOT NULL,
            direction varchar(10) NOT NULL,
            user_ip varchar(100) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY entry_id (entry_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
```

---

## 🔌 Intégration dans votre Plugin

### Option 1 : Dans FormManager (recommandé)

Si vous étendez le plugin `wc_qualiopi_formation` :

```php
// Dans src/Form/FormManager.php

public function __construct() {
    // ... code existant ...

    // Initialiser votre handler personnalisé
    $this->mon_handler = new MonHandlerTransition();
}
```

### Option 2 : Plugin Séparé

Si vous créez un plugin complémentaire :

```php
<?php
/**
 * Plugin Name: Mon Extension Qualiopi
 * Description: Handlers personnalisés pour transitions GF
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Attendre que le plugin principal soit chargé
add_action( 'plugins_loaded', function() {
    // Vérifier que le Manager existe
    if ( ! class_exists( 'WcQualiopiFormation\Form\GravityForms\PageTransitionManager' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>Le plugin wc_qualiopi_formation doit être activé.</p></div>';
        } );
        return;
    }

    // Charger votre autoloader
    require_once __DIR__ . '/vendor/autoload.php';

    // Initialiser vos handlers
    new MonPlugin\Handlers\YousignTriggerHandler( $yousign_client );
    new MonPlugin\Handlers\UserAccountCreatorHandler();
    new MonPlugin\Handlers\TransitionAnalyticsHandler();
} );
```

---

## ✅ Checklist Handler

Avant de déployer votre handler, vérifiez :

### Code

- [ ] **Protection ABSPATH** en tête de fichier
- [ ] **Namespace** cohérent avec votre plugin
- [ ] **Filtrage** strict de la transition (from_page, to_page, direction)
- [ ] **Sanitization** des données de `$transition_data['submission_data']`
- [ ] **Fail-safe** : try/catch avec log, ne jamais bloquer le formulaire
- [ ] **Logs** : LoggingHelper pour INFO/DEBUG/ERROR

### Performance

- [ ] **Opérations lourdes** en asynchrone (Action Scheduler / WP Cron)
- [ ] **Pas d'appels API** synchrones longs
- [ ] **Priorité hook** appropriée (5=critique, 10=normal, 20=secondaire)

### Sécurité

- [ ] **Validation** des données avant utilisation
- [ ] **Capabilities** si nécessaire (`current_user_can()`)
- [ ] **Nonces** si actions AJAX/formulaires

### Tests

- [ ] **Tests unitaires** pour votre handler
- [ ] **Tests manuels** sur DDEV
- [ ] **Tests utilisateurs simultanés** (pas de mélange données)

---

## 🧪 Tester votre Handler

### Test Unitaire

```php
<?php
namespace MonPlugin\Tests;

use PHPUnit\Framework\TestCase;
use MonPlugin\Handlers\MonHandlerTransition;

class MonHandlerTransitionTest extends TestCase {

    public function test_handles_specific_transition() {
        // Arrange
        $handler = new MonHandlerTransition();

        $transition_data = array(
            'from_page' => 4,
            'to_page'   => 5,
            'direction' => 'forward',
            'form_id'   => 5,
            'entry_id'  => 123,
            'submission_data' => array( /* ... */ ),
        );

        // Act
        $handler->handle_transition( $transition_data );

        // Assert
        // Vérifier que votre action a bien été déclenchée
        $this->assertTrue( did_action( 'mon_plugin_action_custom' ) > 0 );
    }

    public function test_ignores_other_transitions() {
        // Arrange
        $handler = new MonHandlerTransition();

        $transition_data = array(
            'from_page' => 1, // Autre transition
            'to_page'   => 2,
            'direction' => 'forward',
        );

        // Act
        $handler->handle_transition( $transition_data );

        // Assert
        // Vérifier que votre action N'a PAS été déclenchée
        $this->assertEquals( 0, did_action( 'mon_plugin_action_custom' ) );
    }
}
```

### Test Manuel (DDEV)

```bash
# 1. Activer logs debug
ddev exec wp config set WP_DEBUG true --raw
ddev exec wp config set WP_DEBUG_LOG true --raw

# 2. Observer les logs en temps réel
ddev logs -f

# 3. Remplir le formulaire dans le navigateur
# 4. Vérifier les logs de votre handler
```

---

## ❓ FAQ

### Mon handler ne se déclenche pas, pourquoi ?

1. Vérifier que `PageTransitionManager` est bien instancié
2. Vérifier que votre filtre (from_page, to_page) correspond à la transition réelle
3. Vérifier les logs : `grep "PageTransitionManager" debug.log`
4. Tester avec un handler minimal (juste un `error_log()`)

### Puis-je bloquer une transition dans mon handler ?

Non, ce n'est pas l'objectif du système. Pour bloquer, utilisez les hooks GF natifs (`gform_validation`, `gform_pre_submission_filter`).

### Comment gérer plusieurs formulaires ?

Filtrer aussi sur `form_id` :

```php
if ( $transition_data['form_id'] !== 5 ) {
    return; // Ignorer les autres formulaires
}
```

### Comment accéder à des champs spécifiques ?

Via `$transition_data['submission_data']` avec l'ID du champ :

```php
$email = sanitize_email( $transition_data['submission_data']['5'] ?? '' );
$score = floatval( $transition_data['submission_data']['27'] ?? 0 );
```

---

## 📚 Ressources

- [Documentation API PageTransitionManager](../api/gravity-forms/page-transition-manager.md)
- [Gravity Forms Hooks Reference](https://docs.gravityforms.com/category/developers/hooks/)
- [Action Scheduler (async)](https://actionscheduler.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

---

## 🤝 Contribuer

Vous avez créé un handler réutilisable ? Partagez-le !

- Créez une issue GitHub
- Proposez une PR avec documentation
- Ajoutez des tests unitaires

---

**Version** : 1.0  
**Dernière mise à jour** : 09/10/2025  
**Auteur** : TB-Web
