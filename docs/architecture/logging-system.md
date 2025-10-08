# Architecture du Système de Logs

**Version** : 1.1.0  
**Dernière mise à jour** : 8 octobre 2025

---

## 🎯 Vue d'ensemble

Le plugin utilise un système de logs dual pour :

- **Débogage développeur** : Logs détaillés en JSON
- **Interface admin** : Consultation facile des logs

---

## 📁 Composants

### LoggingHelper

**Fichier** : `src/Helpers/LoggingHelper.php`  
**Type** : Classe statique finale

#### Responsabilités

1. Écriture des logs dans 2 destinations
2. Enrichissement automatique (timestamp, user_id, IP, contexte)
3. Format JSON CloudWatch-like
4. Gestion des erreurs silencieuse

#### Niveaux de log

```php
LoggingHelper::debug()    // Débogage détaillé
LoggingHelper::info()     // Informations générales
LoggingHelper::notice()   // Événements notables
LoggingHelper::warning()  // Avertissements
LoggingHelper::error()    // Erreurs
LoggingHelper::critical() // Erreurs critiques
```

---

## 🔀 Destinations des logs

### 1. error_log (Backup)

**Destination** : `WP_DEBUG_LOG` ou `error_log` PHP standard  
**Format** : JSON une ligne  
**Usage** : Debug PHP global, backup

**Exemple** :

```json
{"timestamp":"2025-10-08T10:30:15+00:00","level":"info","message":"[CartGuard] Initialized","user_id":1,"ip":"127.0.0.1",...}
```

### 2. WooCommerce Logger (Principal)

**Destination** : `/wp-content/uploads/wc-logs/wc-qualiopi-formation-YYYY-MM-DD-hash.log`  
**Format** : Timestamp + niveau + JSON  
**Usage** : Interface admin, consultation facile

**Exemple** :

```
2025-10-08T10:30:15+00:00 INFO {"timestamp":"2025-10-08T10:30:15+00:00","level":"info","message":"[CartGuard] Initialized",...}
```

#### Avantages WooCommerce Logger

- ✅ Fichier dédié uniquement au plugin
- ✅ Rotation automatique (nouveau fichier chaque jour)
- ✅ Nettoyage automatique (configurable, défaut 30 jours)
- ✅ Compatible interface admin
- ✅ Pas de mélange avec autres logs

---

## 🏗️ Architecture technique

### Flux de logging

```
Plugin quelconque
    ↓
LoggingHelper::info('Message', ['context' => 'data'])
    ↓
    ├─→ build_record() → Enrichissement (user_id, IP, timestamp, etc.)
    ├─→ to_single_line_json() → Sérialisation JSON
    ├─→ error_log() → WP_DEBUG_LOG
    └─→ log_to_woocommerce() → wc-qualiopi-formation.log
```

### Méthode `log_to_woocommerce()`

**Sécurité** :

- Vérifie disponibilité WooCommerce (`wc_get_logger()`)
- Try-catch pour éviter blocage
- Fallback silencieux si échec

**Code** :

```php
private static function log_to_woocommerce( string $level, string $message ): void {
    if ( ! function_exists( 'wc_get_logger' ) ) {
        return; // WooCommerce non disponible
    }

    try {
        $wc_logger = wc_get_logger();
        $wc_logger->log(
            strtolower( $level ),
            $message,
            array( 'source' => 'wc-qualiopi-formation' )
        );
    } catch ( \Exception $e ) {
        // Silencieux - log déjà dans error_log
    }
}
```

---

## 📊 Format des logs

### Champs standards

| Champ          | Type    | Description                       |
| -------------- | ------- | --------------------------------- |
| `timestamp`    | ISO8601 | Date/heure UTC                    |
| `level`        | string  | Niveau du log                     |
| `message`      | string  | Message principal                 |
| `channel`      | string  | Canal logique (défaut: wordpress) |
| `request_id`   | string  | ID unique de la requête           |
| `user_id`      | int     | ID utilisateur WordPress          |
| `ip`           | string  | IP du client                      |
| `php_version`  | string  | Version PHP                       |
| `wp_version`   | string  | Version WordPress                 |
| `site_url`     | string  | URL du site                       |
| `file`         | string  | Fichier source                    |
| `line`         | int     | Ligne source                      |
| `class`        | string  | Classe source                     |
| `method`       | string  | Méthode source                    |
| `duration_ms`  | float   | Durée depuis boot (ms)            |
| `memory_bytes` | int     | Mémoire utilisée                  |
| `context`      | object  | Contexte additionnel              |

### Exemple complet

```json
{
  "timestamp": "2025-10-08T10:30:15+00:00",
  "level": "info",
  "message": "[CartGuard] Initialized",
  "channel": "wordpress",
  "request_id": "req_671234abcd",
  "user_id": 1,
  "ip": "127.0.0.1",
  "php_version": "8.3.0",
  "wp_version": "6.8.3",
  "site_url": "https://example.com",
  "file": "/wp-content/plugins/wc_qualiopi_formation/src/Cart/CartGuard.php",
  "line": 87,
  "class": "WcQualiopiFormation\\Cart\\CartGuard",
  "method": "__construct",
  "duration_ms": 125.45,
  "memory_bytes": 12582912,
  "context": {
    "module": "cart",
    "action": "init"
  }
}
```

---

## 🔄 Rotation et nettoyage

### Automatique par WooCommerce

- **Nouveau fichier** : Chaque jour à minuit (UTC)
- **Nettoyage** : Suppression automatique après X jours (configurable)
- **Défaut** : Conservation 30 jours

### Configuration (optionnel)

Dans `wp-config.php` :

```php
// Personnaliser la durée de conservation (en jours)
define( 'WC_LOG_THRESHOLD', 'debug' ); // Niveau minimum
```

---

## 📈 Performance

### Impact par log

- **Enrichissement** : ~0.2ms
- **JSON encoding** : ~0.1ms
- **error_log()** : ~0.3ms
- **WooCommerce Logger** : ~0.5ms
- **TOTAL** : ~1.1ms par log

### Optimisations

- Utilisation de `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)` (léger)
- JSON sans pretty print (compact)
- Écriture asynchrone (bufferisée par PHP)
- Try-catch pour éviter blocage

---

## 🔒 Sécurité

### Sanitization

Tous les contextes sont nettoyés :

- Ressources → `(resource)`
- Objets → `__toString()` ou nom de classe
- Exceptions → Tableau structuré
- Callbacks → `(closure)`

### Données sensibles

⚠️ **Ne JAMAIS logger** :

- Mots de passe
- Tokens d'authentification
- Clés API en clair
- Données bancaires
- Informations personnelles non masquées

✅ **Bonnes pratiques** :

```php
// Masquer les données sensibles
$context['api_key'] = substr($api_key, 0, 4) . '***';
LoggingHelper::debug('API call', $context);
```

---

## 🧪 Tests

### Vérifier le logging

```php
// Test simple
LoggingHelper::info('Test log interface admin');

// Test avec contexte
LoggingHelper::info('Test avec contexte', [
    'user_id' => get_current_user_id(),
    'action' => 'test',
]);
```

### Vérifier les fichiers

```bash
# Dans DDEV
ddev ssh
ls -lh /var/www/html/web/wp-content/uploads/wc-logs/wc-qualiopi-formation-*.log
tail -f /var/www/html/web/wp-content/uploads/wc-logs/wc-qualiopi-formation-*.log
```

---

## ⚠️ Exceptions à la règle LoggingHelper

La règle générale est : **Tous les logs doivent utiliser LoggingHelper**.

Cependant, certains fichiers peuvent utiliser `error_log()` directement dans des cas spécifiques.

### ✅ Cas acceptés

#### 1. Warnings de sécurité critiques

**Fichiers concernés** : `SecretManager.php`, `TokenManager.php`

**Raison** : Les warnings de sécurité doivent être visibles même si LoggingHelper échoue ou si le plugin n'est pas complètement initialisé.

**Stratégie** : Double log (LoggingHelper + error_log)

```php
// Log structuré pour interface admin
LoggingHelper::critical( '[SecretManager] Critical secret in option', array(
    'secret_name'    => $key,
    'recommendation' => 'Use environment variable or wp-config constant'
) );

// Log brut pour garantir visibilité maximale
error_log( sprintf(
    'WCQF Security Warning: Critical secret %s retrieved from WordPress option.',
    $key
) );
```

**Justification** : Un warning de sécurité critique doit être visible dans **tous les cas**, même si :

- LoggingHelper n'est pas disponible
- WooCommerce Logger échoue
- Le plugin est partiellement chargé

#### 2. Activation/Désactivation du plugin

**Fichiers concernés** : `Activator.php`, `Deactivator.php`

**Raison** : L'activation se produit avant l'initialisation complète du plugin. LoggingHelper peut ne pas être disponible.

**Stratégie** : Double log (LoggingHelper + error_log)

```php
// Log structuré pour interface admin (si disponible)
LoggingHelper::info( '[Activator] Migration completed', array(
    'options_migrated' => $results['options_migrated'],
    'tables_migrated'  => $results['tables_migrated']
) );

// Log brut pour garantir visibilité (toujours disponible)
error_log( sprintf(
    '[WC Qualiopi Formation] Migration completed: %d options migrated',
    $results['options_migrated']
) );
```

**Justification** : Pendant l'activation, le plugin n'est pas encore initialisé. L'autoloader peut ne pas avoir chargé toutes les classes. `error_log()` est toujours disponible.

#### 3. LoggingHelper lui-même

**Fichier concerné** : `LoggingHelper.php`

**Raison** : Méta-logging (logging du système de logs)

**Justification** : LoggingHelper utilise `error_log()` en interne pour écrire les logs. C'est normal et attendu.

### ❌ Cas refusés

**Tous les autres cas doivent utiliser LoggingHelper** :

- ❌ Debug/Info dans le code métier → Utiliser `LoggingHelper::debug()` ou `LoggingHelper::info()`
- ❌ Logs applicatifs → Utiliser LoggingHelper
- ❌ Logs de validation → Utiliser LoggingHelper
- ❌ Logs de cache → Utiliser LoggingHelper
- ❌ Logs d'API → Utiliser LoggingHelper
- ❌ Tous les autres logs → Utiliser LoggingHelper

### 📋 Récapitulatif

| Cas                          | Fichiers                                  | Stratégie                              | Justification                |
| ---------------------------- | ----------------------------------------- | -------------------------------------- | ---------------------------- |
| **Sécurité critique**        | `SecretManager.php`<br>`TokenManager.php` | Double log (LoggingHelper + error_log) | Garantit visibilité maximale |
| **Activation/Désactivation** | `Activator.php`<br>`Deactivator.php`      | Double log (LoggingHelper + error_log) | Plugin pas encore initialisé |
| **Méta-logging**             | `LoggingHelper.php`                       | error_log uniquement                   | Log du système de logs       |
| **Tous les autres**          | Tous les autres fichiers                  | LoggingHelper uniquement               | Logs structurés et traçables |

---

## 📚 Références

- [Guide utilisateur : Consulter les logs](../guides/viewing-logs.md)
- [WooCommerce Logger Documentation](https://github.com/woocommerce/woocommerce/wiki/Logger)
- Code source : `src/Helpers/LoggingHelper.php`

---

**Historique** :

- v1.0.0 : Système initial avec error_log uniquement
- v1.1.0 (2025-10-08) : Ajout WooCommerce Logger pour interface admin
- v1.2.0 (2025-10-08) : Documentation des exceptions à la règle LoggingHelper
