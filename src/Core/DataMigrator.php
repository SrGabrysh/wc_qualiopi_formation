<?php
/**
 * Migration des données des anciens plugins
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Core;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Utils\Logger;

/**
 * Classe de migration des données
 *
 * Migre les données de :
 * - wc_qualiopi_steps
 * - gravity_forms_siren_autocomplete
 */
class DataMigrator {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Options à migrer depuis wc_qualiopi_steps
	 *
	 * @var array
	 */
	private $wcqs_options = array(
		'wcqs_flags'          => 'wcqf_flags',
		'wcqs_testpos_mapping' => 'wcqf_testpos_mapping',
		'wcqs_hmac_secret'    => 'wcqf_hmac_secret',
		'wcqs_hmac_secret_prev' => 'wcqf_hmac_secret_prev',
		'wcqs_key_version'    => 'wcqf_key_version',
		'wcqs_log_level'      => 'wcqf_log_level',
	);

	/**
	 * Options à migrer depuis gravity_forms_siren_autocomplete
	 *
	 * @var array
	 */
	private $gfsa_options = array(
		'gf_siren_settings' => 'wcqf_form_settings',
		'gf_siren_api_key'  => 'wcqf_siren_api_key',
	);

	/**
	 * Constructeur
	 */
	public function __construct() {
		$this->logger = Logger::get_instance();
	}

	/**
	 * Exécute la migration complète
	 *
	 * @return array Résultats de la migration
	 */
	public function run_migration() {
		$results = array(
			'success'          => true,
			'options_migrated' => 0,
			'tables_migrated'  => 0,
			'errors'           => array(),
			'warnings'         => array(),
		);

		// Vérifier si migration déjà effectuée
		if ( get_option( 'wcqf_migration_completed', false ) ) {
			$results['warnings'][] = 'Migration already completed';
			return $results;
		}

		$this->logger->info( 'Starting data migration' );

		// Migrer wc_qualiopi_steps
		if ( $this->plugin_was_active( 'wc_qualiopi_steps' ) ) {
			$wcqs_results = $this->migrate_wcqs_data();
			$results['options_migrated'] += $wcqs_results['options'];
			$results['tables_migrated'] += $wcqs_results['tables'];
			$results['errors'] = array_merge( $results['errors'], $wcqs_results['errors'] );
		}

		// Migrer gravity_forms_siren_autocomplete
		if ( $this->plugin_was_active( 'gravity_forms_siren_autocomplete' ) ) {
			$gfsa_results = $this->migrate_gfsa_data();
			$results['options_migrated'] += $gfsa_results['options'];
			$results['tables_migrated'] += $gfsa_results['tables'];
			$results['errors'] = array_merge( $results['errors'], $gfsa_results['errors'] );
		}

		// Marquer migration comme complétée
		if ( empty( $results['errors'] ) ) {
			update_option( 'wcqf_migration_completed', true );
			update_option( 'wcqf_migration_date', current_time( 'mysql' ) );
			$this->logger->info( 'Migration completed successfully', $results );
		} else {
			$results['success'] = false;
			$this->logger->error( 'Migration completed with errors', $results );
		}

		return $results;
	}

	/**
	 * Migre les données de wc_qualiopi_steps
	 *
	 * @return array Résultats
	 */
	private function migrate_wcqs_data() {
		$results = array(
			'options' => 0,
			'tables'  => 0,
			'errors'  => array(),
		);

		// Migrer options
		foreach ( $this->wcqs_options as $old_key => $new_key ) {
			$value = get_option( $old_key, null );

			if ( null !== $value ) {
				$updated = update_option( $new_key, $value, false );

				if ( $updated ) {
					$results['options']++;
					$this->logger->info( "Migrated option: {$old_key} → {$new_key}" );
				} else {
					$results['errors'][] = "Failed to migrate option: {$old_key}";
				}
			}
		}

		// Les tables wp_wcqs_* existent déjà avec le même nom dans le nouveau plugin
		// Pas besoin de migration

		return $results;
	}

	/**
	 * Migre les données de gravity_forms_siren_autocomplete
	 *
	 * @return array Résultats
	 */
	private function migrate_gfsa_data() {
		$results = array(
			'options' => 0,
			'tables'  => 0,
			'errors'  => array(),
		);

		// Migrer options
		foreach ( $this->gfsa_options as $old_key => $new_key ) {
			$value = get_option( $old_key, null );

			if ( null !== $value ) {
				// Traitement spécial pour gf_siren_settings
				if ( 'gf_siren_settings' === $old_key ) {
					$value = $this->transform_gfsa_settings( $value );
				}

				$updated = update_option( $new_key, $value, false );

				if ( $updated ) {
					$results['options']++;
					$this->logger->info( "Migrated option: {$old_key} → {$new_key}" );
				} else {
					$results['errors'][] = "Failed to migrate option: {$old_key}";
				}
			}
		}

		// Migrer table gf_siren_tracking vers wcqf_tracking
		$migrated = $this->migrate_gfsa_tracking_table();

		if ( $migrated ) {
			$results['tables']++;
		} else {
			$results['errors'][] = 'Failed to migrate tracking table';
		}

		return $results;
	}

	/**
	 * Transforme les settings de gf_siren en format wcqf
	 *
	 * @param array $old_settings Anciens settings
	 * @return array Nouveaux settings
	 */
	private function transform_gfsa_settings( $old_settings ) {
		$new_settings = array();

		// Migrer form_mappings
		if ( isset( $old_settings['form_mappings'] ) ) {
			$new_settings['form_mappings'] = $old_settings['form_mappings'];
		}

		// Migrer tracked_forms
		if ( isset( $old_settings['tracked_forms'] ) ) {
			$new_settings['tracked_forms'] = $old_settings['tracked_forms'];
		}

		// Migrer cache_duration
		if ( isset( $old_settings['cache_duration'] ) ) {
			$new_settings['cache_duration'] = $old_settings['cache_duration'];
		}

		return $new_settings;
	}

	/**
	 * Migre la table de tracking gf_siren
	 *
	 * @return bool Success
	 */
	private function migrate_gfsa_tracking_table() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'gf_siren_tracking';
		$new_table = $wpdb->prefix . 'wcqf_tracking';

		// Vérifier si ancienne table existe
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$old_table}'" ) === $old_table;

		if ( ! $table_exists ) {
			return true; // Pas de table à migrer
		}

		// Migrer les données
		$query = "INSERT INTO {$new_table} 
                  (form_id, entry_id, user_id, siret, company_name, form_data, submitted_at, ip_address, user_agent)
                  SELECT 
                      form_id, 
                      entry_id,
                      0 AS user_id,
                      NULL AS siret,
                      NULL AS company_name,
                      fields_data AS form_data,
                      created_at AS submitted_at,
                      user_ip AS ip_address,
                      user_agent
                  FROM {$old_table}
                  WHERE NOT EXISTS (
                      SELECT 1 FROM {$new_table} 
                      WHERE {$new_table}.entry_id = {$old_table}.entry_id
                  )";

		$result = $wpdb->query( $query );

		if ( false !== $result ) {
			$this->logger->info( "Migrated {$result} rows from {$old_table} to {$new_table}" );
			return true;
		}

		return false;
	}

	/**
	 * Vérifie si un plugin était actif
	 *
	 * @param string $plugin_slug Slug du plugin
	 * @return bool True si le plugin était actif
	 */
	private function plugin_was_active( $plugin_slug ) {
		// Vérifier si des options du plugin existent
		if ( 'wc_qualiopi_steps' === $plugin_slug ) {
			return get_option( 'wcqs_flags', null ) !== null;
		}

		if ( 'gravity_forms_siren_autocomplete' === $plugin_slug ) {
			return get_option( 'gf_siren_settings', null ) !== null;
		}

		return false;
	}

	/**
	 * Réinitialise la migration (pour tests)
	 *
	 * @return bool Success
	 */
	public function reset_migration() {
		delete_option( 'wcqf_migration_completed' );
		delete_option( 'wcqf_migration_date' );

		$this->logger->info( 'Migration reset' );

		return true;
	}

	/**
	 * Récupère le statut de la migration
	 *
	 * @return array Statut
	 */
	public function get_migration_status() {
		return array(
			'completed' => (bool) get_option( 'wcqf_migration_completed', false ),
			'date'      => get_option( 'wcqf_migration_date', null ),
		);
	}
}

