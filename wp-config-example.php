<?php
/**
 * WC Qualiopi Formation - Configuration Example for wp-config.php
 * 
 * Add these lines to your wp-config.php file (BEFORE the "That's all, stop editing!" line)
 * 
 * IMPORTANT: Replace placeholder values with your actual keys
 * SECURITY: Never commit wp-config.php to version control
 * 
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

// ============================================================================
// REQUIRED: HMAC Token Signing Key (OBLIGATOIRE)
// ============================================================================
// Used to sign and verify user tokens throughout the funnel
// Generate with: wp eval "echo wp_generate_password(64, true, true);"
// Or with: openssl rand -hex 32

define( 'WCQF_HMAC_KEY', 'REPLACE_WITH_64_RANDOM_CHARACTERS_FROM_GENERATOR' );


// ============================================================================
// OPTIONAL: INSEE SIRENE API Key (si fonctionnalité SIRET activée)
// ============================================================================
// Required only if you enable SIRET autocomplete feature
// Get your key from: https://api.insee.fr/catalogue/

// define( 'WCQF_SIREN_API_KEY', 'your_insee_api_key_here' );


// ============================================================================
// OPTIONAL: OpenAI API Key (si validation IA activée)
// ============================================================================
// Required only if you enable AI-powered test validation
// Get your key from: https://platform.openai.com/api-keys

// define( 'WCQF_OPENAI_API_KEY', 'sk-proj-your-openai-key-here' );


// ============================================================================
// OPTIONAL: Encryption Key (pour chiffrement données sensibles)
// ============================================================================
// Used to encrypt sensitive data before storing in database
// Generate with: php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"

// define( 'WCQF_ENCRYPTION_KEY', 'your_32_char_hex_encryption_key_here' );


// ============================================================================
// OPTIONAL: Debug Mode (développement uniquement)
// ============================================================================
// Enable verbose logging for development/debugging
// NEVER enable in production

// define( 'WCQF_DEBUG_MODE', true );


// ============================================================================
// RECOMMENDED: WordPress Debug Settings (pour développement)
// ============================================================================
// Enable WordPress debug logging (useful for troubleshooting)

/*
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );      // Log to wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false ); // Don't display errors on screen
define( 'SCRIPT_DEBUG', true );      // Use non-minified JS/CSS
*/


// ============================================================================
// EXAMPLE: Complete Production Configuration
// ============================================================================

/*
// --- WC Qualiopi Formation Secrets ---
define( 'WCQF_HMAC_KEY', 'a1b2c3d4e5f6...your_64_char_key_here...x7y8z9' );
define( 'WCQF_SIREN_API_KEY', 'your_insee_api_key_here' );
define( 'WCQF_OPENAI_API_KEY', 'sk-proj-your-openai-key-here' );
define( 'WCQF_ENCRYPTION_KEY', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6' );

// --- WordPress Security ---
define( 'DISALLOW_FILE_EDIT', true ); // Disable plugin/theme editor
define( 'WP_AUTO_UPDATE_CORE', 'minor' ); // Auto-update minor versions
*/


// ============================================================================
// NOTES
// ============================================================================

/**
 * Priority Order for Secret Resolution:
 * 1. Environment Variable (highest priority)
 * 2. Constant (wp-config.php) - recommended for most cases
 * 3. WordPress Option (lowest priority, auto-generated fallback)
 * 
 * For production environments, use:
 * - Environment variables (via SetEnv, docker-compose, etc.)
 * - OR constants in wp-config.php (this file)
 * 
 * For development environments, you can let the plugin auto-generate keys
 * (stored as WordPress options), but you'll see warnings in logs.
 */

/**
 * Security Best Practices:
 * - NEVER commit wp-config.php to Git
 * - Use different keys for dev/staging/production
 * - Rotate HMAC key periodically (plugin supports N-1 key rotation)
 * - Store backups of keys in secure password manager
 * - Use environment variables for containerized deployments
 */

/**
 * Key Generation Commands:
 * 
 * # HMAC Key (64 characters)
 * wp eval "echo wp_generate_password(64, true, true) . PHP_EOL;"
 * 
 * # OR with OpenSSL
 * openssl rand -hex 32
 * 
 * # Encryption Key (32 characters hexadecimal)
 * php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
 * 
 * # OR with OpenSSL
 * openssl rand -hex 16
 */

