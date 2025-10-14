<?php
/**
 * Sauvegarde des settings de positionnement
 *
 * @package WcQualiopiFormation\Admin\Settings
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Admin\Settings;

use WcQualiopiFormation\Data\Store\PositioningConfigStore;
use WcQualiopiFormation\Form\GravityForms\PositioningHelper;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;
use WcQualiopiFormation\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe de sauvegarde de la configuration
 *
 * Responsabilité unique : Validation et sauvegarde config verdicts
 */
class PositioningSettingsSaver {

	/**
	 * Traite la sauvegarde de la configuration
	 *
	 * @return bool|WP_Error True si succès, WP_Error sinon.
	 */
	public function save_config() {
		// Vérifier les permissions
		if ( ! \current_user_can( Constants::CAP_MANAGE_SETTINGS ) ) {
			LoggingHelper::warning( '[PositioningSettingsSaver] Permissions insuffisantes', array(
				'user_id' => \get_current_user_id(),
			) );
			return new \WP_Error( 'forbidden', \__( 'Permissions insuffisantes', Constants::TEXT_DOMAIN ) );
		}

		// Vérifier le nonce
		if ( ! isset( $_POST['wcqf_positioning_nonce'] ) ||
			! \wp_verify_nonce( $_POST['wcqf_positioning_nonce'], 'wcqf_save_positioning' ) ) {
			LoggingHelper::warning( '[PositioningSettingsSaver] Nonce invalide' );
			return new \WP_Error( 'invalid_nonce', \__( 'Token de sécurité invalide', Constants::TEXT_DOMAIN ) );
		}

		// Récupérer l'ID du formulaire
		$form_id = isset( $_POST['wcqf_positioning_form_id'] ) ?
			(int) $_POST['wcqf_positioning_form_id'] :
			0;

		if ( $form_id === 0 ) {
			return new \WP_Error( 'invalid_form', \__( 'Formulaire invalide', Constants::TEXT_DOMAIN ) );
		}

		// Traiter ajout/modification/suppression verdict
		if ( isset( $_POST['wcqf_add_verdict'] ) ) {
			return $this->add_verdict( $form_id );
		}

		if ( isset( $_POST['wcqf_delete_verdict'] ) ) {
			$verdict_key = \sanitize_text_field( \wp_unslash( $_POST['wcqf_delete_verdict'] ) );
			return $this->delete_verdict( $form_id, $verdict_key );
		}

		// Sauvegarder la config des champs
		if ( isset( $_POST['wcqf_fields'] ) ) {
			return $this->save_fields_config( $form_id, $_POST['wcqf_fields'] );
		}

		return true;
	}

	/**
	 * Ajoute ou met à jour un verdict
	 *
	 * @param int $form_id ID du formulaire.
	 * @return bool|WP_Error True si succès.
	 */
	private function add_verdict( int $form_id ) {
		if ( ! isset( $_POST['wcqf_verdict'] ) || ! is_array( $_POST['wcqf_verdict'] ) ) {
			return new \WP_Error( 'missing_data', \__( 'Données manquantes', Constants::TEXT_DOMAIN ) );
		}

		// Détecter le mode édition
		$is_editing = isset( $_POST['wcqf_editing_verdict_key'] );
		$old_key    = $is_editing ? \sanitize_text_field( \wp_unslash( $_POST['wcqf_editing_verdict_key'] ) ) : '';

		// Valider les données
		$validated = $this->validate_verdict_data( $_POST['wcqf_verdict'] );
		if ( \is_wp_error( $validated ) ) {
			return $validated;
		}

		// Récupérer la config existante (wp_unslash pour enlever les slashes WordPress)
		$all_configs = \wp_unslash( \get_option( PositioningConfigStore::get_option_name(), array() ) );
		$key         = 'form_' . $form_id;

		if ( ! isset( $all_configs[ $key ] ) ) {
			$all_configs[ $key ] = array(
				'form_id' => $form_id,
				'verdicts' => array(),
			);
		}

		// Si mode édition, supprimer l'ancien verdict
		if ( $is_editing ) {
			$all_configs[ $key ]['verdicts'] = array_filter(
				$all_configs[ $key ]['verdicts'],
				function( $v ) use ( $old_key ) {
					return $v['verdict_key'] !== $old_key;
				}
			);
			$all_configs[ $key ]['verdicts'] = array_values( $all_configs[ $key ]['verdicts'] );
		}

		// Vérifier chevauchement
		$new_verdicts = $all_configs[ $key ]['verdicts'];
		$new_verdicts[] = $validated;

		if ( ! PositioningHelper::validate_no_overlap( $new_verdicts ) ) {
			return new \WP_Error(
				'overlap_detected',
				\__( 'Cette tranche chevauche un verdict existant', Constants::TEXT_DOMAIN )
			);
		}

		// Ajouter le verdict
		$all_configs[ $key ]['verdicts'][] = $validated;

		// Sauvegarder
		\update_option( PositioningConfigStore::get_option_name(), $all_configs );

		// Vider le cache
		PositioningConfigStore::clear_cache();

		LoggingHelper::info( '[PositioningSettingsSaver] Verdict ajouté', array(
			'form_id' => $form_id,
			'verdict' => $validated['verdict_key'],
		) );

		return true;
	}

	/**
	 * Supprime un verdict
	 *
	 * @param int    $form_id     ID du formulaire.
	 * @param string $verdict_key Clé du verdict.
	 * @return bool|WP_Error True si succès.
	 */
	private function delete_verdict( int $form_id, string $verdict_key ) {
		$all_configs = \wp_unslash( \get_option( PositioningConfigStore::get_option_name(), array() ) );
		$key         = 'form_' . $form_id;

		if ( ! isset( $all_configs[ $key ]['verdicts'] ) ) {
			return new \WP_Error( 'not_found', \__( 'Configuration non trouvée', Constants::TEXT_DOMAIN ) );
		}

		// Filtrer pour retirer le verdict
		$all_configs[ $key ]['verdicts'] = array_filter(
			$all_configs[ $key ]['verdicts'],
			function( $v ) use ( $verdict_key ) {
				return $v['verdict_key'] !== $verdict_key;
			}
		);

		// Ré-indexer le tableau
		$all_configs[ $key ]['verdicts'] = array_values( $all_configs[ $key ]['verdicts'] );

		// Sauvegarder
		\update_option( PositioningConfigStore::get_option_name(), $all_configs );

		// Vider le cache
		PositioningConfigStore::clear_cache();

		LoggingHelper::info( '[PositioningSettingsSaver] Verdict supprimé', array(
			'form_id' => $form_id,
			'verdict' => $verdict_key,
		) );

		return true;
	}

	/**
	 * Sauvegarde la configuration des champs
	 *
	 * @param int   $form_id ID du formulaire.
	 * @param array $fields  Données des champs.
	 * @return bool|WP_Error True si succès.
	 */
	private function save_fields_config( int $form_id, array $fields ) {
		$all_configs = \wp_unslash( \get_option( PositioningConfigStore::get_option_name(), array() ) );
		$key         = 'form_' . $form_id;

		if ( ! isset( $all_configs[ $key ] ) ) {
			$all_configs[ $key ] = array(
				'form_id'  => $form_id,
				'verdicts' => array(),
			);
		}

		// Sauvegarder les IDs de champs
		$all_configs[ $key ]['result_field_id']     = isset( $fields['result_field_id'] ) ?
			(int) $fields['result_field_id'] : 31;
		$all_configs[ $key ]['score_field_id']      = isset( $fields['score_field_id'] ) ?
			(int) $fields['score_field_id'] : 27;
		$all_configs[ $key ]['first_name_field_id'] = isset( $fields['first_name_field_id'] ) ?
			\sanitize_text_field( $fields['first_name_field_id'] ) : '7.3';
		$all_configs[ $key ]['score_title_template'] = isset( $fields['score_title_template'] ) ?
			\sanitize_text_field( \wp_unslash( $fields['score_title_template'] ) ) : 'Félicitations {{prenom}}, votre score : {{score}}/20';

		// Sauvegarder
		\update_option( PositioningConfigStore::get_option_name(), $all_configs );

		// Vider le cache
		PositioningConfigStore::clear_cache();

		LoggingHelper::info( '[PositioningSettingsSaver] Configuration champs sauvegardée', array(
			'form_id' => $form_id,
		) );

		return true;
	}

	/**
	 * Valide les données d'un verdict
	 *
	 * @param array $data Données à valider.
	 * @return array|WP_Error Données validées ou erreur.
	 */
	private function validate_verdict_data( array $data ) {
		$errors = array();

		// Clé requise
		if ( empty( $data['key'] ) ) {
			$errors[] = \__( 'La clé du verdict est requise', Constants::TEXT_DOMAIN );
		} elseif ( ! preg_match( '/^[a-z_]+$/', $data['key'] ) ) {
			$errors[] = \__( 'La clé doit contenir uniquement des lettres minuscules et underscores', Constants::TEXT_DOMAIN );
		}

		// Texte requis
		if ( empty( $data['text'] ) ) {
			$errors[] = \__( 'Le texte du verdict est requis', Constants::TEXT_DOMAIN );
		}

		// Tranches valides
		$min = isset( $data['min'] ) ? (int) $data['min'] : 0;
		$max = isset( $data['max'] ) ? (int) $data['max'] : 0;

		if ( $min < 0 || $max < 0 ) {
			$errors[] = \__( 'Les valeurs min/max doivent être positives', Constants::TEXT_DOMAIN );
		}

		if ( $min > $max ) {
			$errors[] = \__( 'Le minimum doit être inférieur ou égal au maximum', Constants::TEXT_DOMAIN );
		}

		if ( ! PositioningHelper::validate_range( $min, $max ) ) {
			$errors[] = \__( 'Tranche de score invalide', Constants::TEXT_DOMAIN );
		}

		// Retourner erreurs ou données validées
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'invalid_verdict', implode( '<br>', $errors ) );
		}

		// DEBUG: Log avant et après sanitization
		$raw_text = $data['text'];
		$sanitized_text = \sanitize_textarea_field( \wp_unslash( $data['text'] ) );
		
		LoggingHelper::debug( '[PositioningSettingsSaver] DEBUG Sanitization verdict_text', array(
			'raw_text'       => $raw_text,
			'after_unslash'  => \wp_unslash( $data['text'] ),
			'sanitized_text' => $sanitized_text,
			'has_backslash_raw' => strpos( $raw_text, '\\' ) !== false,
			'has_backslash_sanitized' => strpos( $sanitized_text, '\\' ) !== false,
		) );

		return array(
			'verdict_key'  => \sanitize_key( $data['key'] ),
			'verdict_text' => $sanitized_text,
			'score_min'    => $min,
			'score_max'    => $max,
		);
	}

	/**
	 * Récupère un verdict pour édition
	 *
	 * @param string $verdict_key Clé du verdict à éditer.
	 * @return array|null Données du verdict (avec form_id ajouté) ou null si non trouvé.
	 */
	public function get_verdict_for_edit( string $verdict_key ): ?array {
		$all_configs = \wp_unslash( \get_option( PositioningConfigStore::get_option_name(), array() ) );

		// Chercher le verdict dans TOUS les formulaires
		foreach ( $all_configs as $form_key => $config ) {
			if ( ! isset( $config['verdicts'] ) ) {
				continue;
			}

			foreach ( $config['verdicts'] as $verdict ) {
				if ( $verdict['verdict_key'] === $verdict_key ) {
					// Ajouter le form_id au verdict pour savoir où il vient
					$verdict['form_id'] = $config['form_id'];
					
					LoggingHelper::info( '[PositioningSettingsSaver] Verdict récupéré pour édition', array(
						'form_id'     => $config['form_id'],
						'verdict_key' => $verdict_key,
					) );
					
					return $verdict;
				}
			}
		}

		LoggingHelper::warning( '[PositioningSettingsSaver] Verdict non trouvé pour édition', array(
			'verdict_key' => $verdict_key,
		) );

		return null;
	}
}
