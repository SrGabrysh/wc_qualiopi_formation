<?php
/**
 * Store de configuration du test de positionnement
 *
 * @package WcQualiopiFormation\Data\Store
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Data\Store;

use WcQualiopiFormation\Helpers\LoggingHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de récupération et cache de la configuration des verdicts
 *
 * Responsabilité unique : Accès base de données pour la config de positionnement
 */
class PositioningConfigStore {

	/**
	 * Nom de l'option WordPress
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'wcqf_positioning_config';

	/**
	 * Cache en mémoire de la configuration
	 *
	 * @var array<int, array>
	 */
	private static $cache = array();

	/**
	 * Récupère la configuration pour un formulaire spécifique
	 *
	 * @param int $form_id ID du formulaire Gravity Forms.
	 * @return array|null Configuration du formulaire ou null si non trouvée.
	 */
	public function get_form_config( int $form_id ): ?array {
		// Vérifier le cache
		if ( isset( self::$cache[ $form_id ] ) ) {
			LoggingHelper::debug( '[PositioningConfigStore] Config depuis cache', array(
				'form_id' => $form_id,
			) );
			return self::$cache[ $form_id ];
		}

		// Récupérer depuis WordPress options et enlever les slashes ajoutés par WordPress
		$all_configs = \wp_unslash( \get_option( self::OPTION_NAME, array() ) );
		$key         = 'form_' . $form_id;

		$config = $all_configs[ $key ] ?? null;

		if ( $config ) {
			// Mettre en cache
			self::$cache[ $form_id ] = $config;

			LoggingHelper::info( '[PositioningConfigStore] Config récupérée depuis DB', array(
				'form_id'        => $form_id,
				'verdicts_count' => isset( $config['verdicts'] ) ? count( $config['verdicts'] ) : 0,
			) );
		} else {
			LoggingHelper::warning( '[PositioningConfigStore] Aucune config trouvée', array(
				'form_id' => $form_id,
				'key'     => $key,
			) );
		}

		return $config;
	}

	/**
	 * Récupère toutes les configurations de formulaires
	 *
	 * @return array Configuration complète de tous les formulaires.
	 */
	public function get_all_configs(): array {
		// Enlever les slashes ajoutés par WordPress
		$configs = \wp_unslash( \get_option( self::OPTION_NAME, array() ) );

		LoggingHelper::debug( '[PositioningConfigStore] Récupération de toutes les configs', array(
			'forms_count' => count( $configs ),
		) );

		return $configs;
	}

	/**
	 * Vérifie si un formulaire a une configuration
	 *
	 * @param int $form_id ID du formulaire Gravity Forms.
	 * @return bool True si configuré, false sinon.
	 */
	public function has_form_config( int $form_id ): bool {
		// Utiliser get_form_config pour bénéficier du cache
		$config = $this->get_form_config( $form_id );
		return $config !== null;
	}

	/**
	 * Nettoie le cache en mémoire
	 *
	 * Utile après sauvegarde de config pour forcer le rechargement.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$cache = array();

		LoggingHelper::debug( '[PositioningConfigStore] Cache vidé' );
	}

	/**
	 * Récupère le nom de l'option WordPress
	 *
	 * @return string Nom de l'option.
	 */
	public static function get_option_name(): string {
		return self::OPTION_NAME;
	}
}
