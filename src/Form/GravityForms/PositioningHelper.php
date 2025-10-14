<?php
/**
 * Helper logique métier pour les verdicts de positionnement
 *
 * @package WcQualiopiFormation\Form\GravityForms
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

use WcQualiopiFormation\Data\Store\PositioningConfigStore;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de logique métier des verdicts et tranches de scores
 *
 * Responsabilité unique : Détermination du verdict selon le score
 */
class PositioningHelper {

	/**
	 * Instance PositioningConfigStore
	 *
	 * @var PositioningConfigStore
	 */
	private $config_store;

	/**
	 * Constructeur
	 *
	 * @param PositioningConfigStore $config_store Store de configuration.
	 */
	public function __construct( PositioningConfigStore $config_store ) {
		$this->config_store = $config_store;
	}

	/**
	 * Détermine le verdict selon le score et la config du formulaire
	 *
	 * @param float $score Score calculé (0-20).
	 * @param int   $form_id ID du formulaire Gravity Forms.
	 * @return array {
	 *     Verdict déterminé avec texte.
	 *
	 *     @type string $verdict Clé du verdict (ex: 'admitted', 'refused').
	 *     @type string $text    Texte du verdict configuré.
	 * }
	 */
	public function determine_verdict( float $score, int $form_id ): array {
		$config = $this->config_store->get_form_config( $form_id );

		// Pas de config trouvée
		if ( ! $config || empty( $config['verdicts'] ) ) {
			LoggingHelper::warning( '[PositioningHelper] Pas de config pour ce formulaire', array(
				'form_id' => $form_id,
				'score'   => $score,
			) );

			return array(
				'verdict' => 'unknown',
				'text'    => \__( 'Configuration manquante', Constants::TEXT_DOMAIN ),
			);
		}

		// Parcourir les verdicts configurés pour trouver la tranche correspondante
		foreach ( $config['verdicts'] as $verdict_config ) {
			$min = (float) $verdict_config['score_min'];
			$max = (float) $verdict_config['score_max'];

			// Le score est dans cette tranche (inclus)
			if ( $score >= $min && $score <= $max ) {
				LoggingHelper::info( '[PositioningHelper] Verdict trouvé', array(
					'form_id' => $form_id,
					'score'   => $score,
					'range'   => sprintf( '%s-%s', $min, $max ),
					'verdict' => $verdict_config['verdict_key'],
				) );

				return array(
					'verdict' => $verdict_config['verdict_key'],
					'text'    => $verdict_config['verdict_text'],
				);
			}
		}

		// Aucune tranche ne correspond au score
		LoggingHelper::error( '[PositioningHelper] Score hors de toutes les tranches configurées', array(
			'form_id'        => $form_id,
			'score'          => $score,
			'verdicts_count' => count( $config['verdicts'] ),
		) );

		return array(
			'verdict' => 'error',
			'text'    => \__( 'Score hors plage configurée', Constants::TEXT_DOMAIN ),
		);
	}

	/**
	 * Trouve la tranche correspondant au score (méthode utilitaire)
	 *
	 * @param float $score Score calculé.
	 * @param array $ranges Tableau de tranches avec min/max.
	 * @return array|null Tranche trouvée ou null.
	 */
	private function find_matching_range( float $score, array $ranges ): ?array {
		foreach ( $ranges as $range ) {
			$min = (float) $range['score_min'];
			$max = (float) $range['score_max'];

			if ( $score >= $min && $score <= $max ) {
				return $range;
			}
		}

		return null;
	}

	/**
	 * Valide qu'une tranche de score est correcte
	 *
	 * @param int $min Score minimum.
	 * @param int $max Score maximum.
	 * @return bool True si valide, false sinon.
	 */
	public static function validate_range( int $min, int $max ): bool {
		// Min et max doivent être positifs
		if ( $min < 0 || $max < 0 ) {
			return false;
		}

		// Min doit être inférieur ou égal à max
		if ( $min > $max ) {
			return false;
		}

		return true;
	}

	/**
	 * Vérifie qu'il n'y a pas de chevauchement entre tranches
	 *
	 * @param array $ranges Tableau de tranches à vérifier.
	 * @return bool True si pas de chevauchement, false sinon.
	 */
	public static function validate_no_overlap( array $ranges ): bool {
		$count = count( $ranges );

		// Comparer chaque tranche avec les autres
		for ( $i = 0; $i < $count; $i++ ) {
			$range_a_min = (float) $ranges[ $i ]['score_min'];
			$range_a_max = (float) $ranges[ $i ]['score_max'];

			for ( $j = $i + 1; $j < $count; $j++ ) {
				$range_b_min = (float) $ranges[ $j ]['score_min'];
				$range_b_max = (float) $ranges[ $j ]['score_max'];

				// Vérifier chevauchement
				// A et B se chevauchent si :
				// - A.min <= B.max ET A.max >= B.min
				if ( $range_a_min <= $range_b_max && $range_a_max >= $range_b_min ) {
					LoggingHelper::warning( '[PositioningHelper] Chevauchement de tranches détecté', array(
						'range_a' => sprintf( '%s-%s', $range_a_min, $range_a_max ),
						'range_b' => sprintf( '%s-%s', $range_b_min, $range_b_max ),
					) );

					return false;
				}
			}
		}

		return true;
	}
}
