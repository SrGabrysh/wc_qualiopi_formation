# 🔒 Politique de Sécurité - WC Qualiopi Formation

**Version :** 1.0.0  
**Dernière mise à jour :** 2025-10-03  
**Statut :** ✅ Obligatoire

---

## 🎯 Principe fondamental

**"Aucun secret ne doit jamais être exposé dans le code, les commits ou les logs"**

---

## 📋 Règles de gestion des clés API & secrets

### ❌ INTERDICTIONS ABSOLUES

1. **Clés en dur dans le code PHP**

   ```php
   // ❌ INTERDIT
   $api_key = 'sk_live_51abc123...';
   define('API_KEY', 'secret123');
   ```

2. **Clés dans les commits Git**

   - Pas de clés dans `.php`, `.js`, `.json`, `.env`
   - Utiliser `.gitignore` pour exclure fichiers sensibles
   - Scanner les commits avec `git-secrets` ou similaire

3. **Clés dans les logs**

   ```php
   // ❌ INTERDIT
   error_log('API Key: ' . $api_key);
   wc_get_logger()->debug('Token: ' . $token);
   ```

4. **Clés dans les réponses API/AJAX**
   ```php
   // ❌ INTERDIT
   wp_send_json(['api_key' => $api_key]);
   ```

---

## ✅ SOURCES AUTORISÉES (par ordre de priorité)

### 1️⃣ **Variables d'environnement** (priorité maximale)

**Méthode recommandée pour la production**

```php
// Dans le code
$api_key = SecretManager::get('TB_API_KEY');
```

**Configuration serveur :**

**Apache (.htaccess ou vhost) :**

```apache
SetEnv TB_API_KEY "sk_live_51abc123..."
SetEnv TB_SIREN_API_KEY "votre_cle_insee"
```

**Nginx (fastcgi_params) :**

```nginx
fastcgi_param TB_API_KEY "sk_live_51abc123...";
fastcgi_param TB_SIREN_API_KEY "votre_cle_insee";
```

**Docker (.env + docker-compose.yml) :**

```yaml
environment:
  - TB_API_KEY=sk_live_51abc123...
  - TB_SIREN_API_KEY=votre_cle_insee
```

---

### 2️⃣ **Constantes wp-config.php** (priorité haute)

**Méthode recommandée pour staging/dev**

```php
// Dans wp-config.php (AVANT require_once ABSPATH . 'wp-settings.php')

/**
 * WC Qualiopi Formation - Secrets
 *
 * ATTENTION : Ne JAMAIS commiter ce fichier avec des clés réelles
 */

// Clé HMAC pour les tokens (obligatoire)
define('WCQF_HMAC_KEY', 'votre-cle-64-caracteres-aleatoires-generate-with-wp_generate_password');

// API INSEE SIRENE (optionnel)
define('WCQF_SIREN_API_KEY', 'votre_cle_insee_api');

// API OpenAI pour validation IA (optionnel)
define('WCQF_OPENAI_API_KEY', 'sk-proj-...');

// Mode debug (optionnel)
define('WCQF_DEBUG_MODE', true);
```

---

### 3️⃣ **Options WordPress** (priorité basse, fallback)

**Pour les clés non critiques ou configurables via UI**

```php
// Récupération
$api_key = SecretManager::get('siren_api_key', null, true); // true = allow_option

// Stockage (UNIQUEMENT via interface admin sécurisée)
update_option('wcqf_siren_api_key', $api_key, false); // autoload=false
```

**⚠️ ATTENTION :** Les options BDD sont stockées en clair. Utiliser uniquement pour :

- Clés API non critiques (ex: API publiques)
- Environnements de développement
- Clés configurables par l'utilisateur via UI admin

---

## 🛠️ Implémentation avec SecretManager

### Utilisation de base

```php
use WcQualiopiFormation\Security\SecretManager;

// Récupération d'un secret (env var > constante > exception)
$hmac_key = SecretManager::get('WCQF_HMAC_KEY');

// Récupération avec fallback option BDD
$siren_key = SecretManager::get('WCQF_SIREN_API_KEY', null, true);

// Récupération avec valeur par défaut (pour dev uniquement)
$debug = SecretManager::get('WCQF_DEBUG_MODE', false, true);

// Vérifier existence sans lever exception
if (SecretManager::has('WCQF_OPENAI_API_KEY')) {
    $openai_key = SecretManager::get('WCQF_OPENAI_API_KEY');
}
```

### Messages d'erreur explicites

```php
try {
    $api_key = SecretManager::get('WCQF_SIREN_API_KEY');
} catch (Exception $e) {
    // Message clair pour l'administrateur
    wp_die(
        __('Configuration Error: WCQF_SIREN_API_KEY is not defined.', 'wcqf') . '<br><br>' .
        __('Please define it as an environment variable or in wp-config.php.', 'wcqf'),
        __('Missing API Key', 'wcqf'),
        ['back_link' => true]
    );
}
```

---

## 🔐 Secrets gérés par le plugin

| Secret                | Type              | Obligatoire         | Source recommandée      |
| --------------------- | ----------------- | ------------------- | ----------------------- |
| `WCQF_HMAC_KEY`       | String (64 chars) | ✅ Oui              | Env var ou wp-config    |
| `WCQF_SIREN_API_KEY`  | String            | ⚠️ Si SIRET activé  | Env var ou wp-config    |
| `WCQF_OPENAI_API_KEY` | String            | ⚠️ Si validation IA | Env var ou wp-config    |
| `WCQF_DEBUG_MODE`     | Boolean           | ❌ Non              | Option BDD ou wp-config |
| `WCQF_ENCRYPTION_KEY` | String (32 chars) | ❌ Non              | Env var ou wp-config    |

---

## 🧪 Génération de clés sécurisées

### Clé HMAC (64 caractères)

**Via WP-CLI :**

```bash
wp eval "echo wp_generate_password(64, true, true) . PHP_EOL;"
```

**Via PHP :**

```php
$hmac_key = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
```

**Via OpenSSL :**

```bash
openssl rand -hex 32
```

---

## 🔍 Validation & Tests

### Tests de sécurité obligatoires

```php
// Test 1 : Aucune clé en dur dans le code
grep -r "sk_live_" src/
grep -r "api_key.*=.*['\"]" src/

// Test 2 : Secrets non loggés
grep -r "error_log.*key" src/
grep -r "wc_get_logger.*secret" src/

// Test 3 : Secrets non exposés en JSON
grep -r "wp_send_json.*key" src/
```

### Audit de sécurité

```php
// Scanner les commits pour détecter des secrets
git secrets --scan
git secrets --scan-history

// Vérifier que .gitignore exclut les fichiers sensibles
cat .gitignore | grep -E "(\.env|wp-config|secrets)"
```

---

## 📝 Checklist de déploiement

Avant de déployer en production :

- [ ] Aucune clé en dur dans le code
- [ ] Variables d'environnement configurées sur le serveur
- [ ] wp-config.php avec `WCQF_HMAC_KEY` défini
- [ ] `.env` ajouté à `.gitignore`
- [ ] Logs ne contiennent aucun secret
- [ ] Réponses API/AJAX ne contiennent aucun secret
- [ ] Documentation admin mise à jour
- [ ] Tests de sécurité passés

---

## 🚨 Procédure en cas de fuite de clé

### Si une clé API est exposée :

1. **Révoquer immédiatement** la clé compromise
2. **Générer une nouvelle clé** avec le fournisseur
3. **Mettre à jour** toutes les configurations (env vars, wp-config)
4. **Auditer les logs** pour identifier l'usage frauduleux
5. **Nettoyer l'historique Git** si la clé a été commitée :
   ```bash
   git filter-branch --tree-filter 'git rm -f path/to/file' HEAD
   git push --force
   ```
6. **Documenter l'incident** dans un rapport

---

## 📚 Ressources & Standards

- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [The Twelve-Factor App - Config](https://12factor.net/config)
- [NIST Guidelines for Key Management](https://csrc.nist.gov/publications/detail/sp/800-57-part-1/rev-5/final)

---

## 🆘 Support

En cas de question sur la sécurité :

- **Email :** security@tb-web.fr
- **Documentation :** https://tb-web.fr/docs/wcqf/security

---

**Cette politique est obligatoire et doit être respectée pour toutes les contributions au plugin.**
