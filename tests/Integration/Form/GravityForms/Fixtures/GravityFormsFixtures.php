<?php
/**
 * Fixtures réalistes pour tests d'intégration Gravity Forms
 *
 * Données conformes au formulaire Qualiopi réel (~50 champs, 5 pages).
 *
 * @package WcQualiopiFormation\Tests
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Tests\Integration\Form\GravityForms\Fixtures;

/**
 * Classe de génération de fixtures Gravity Forms réalistes
 */
class GravityFormsFixtures {

	/**
	 * ID du formulaire Qualiopi
	 */
	private const FORM_ID = 5;

	/**
	 * Champs clés
	 */
	private const FIELD_TOKEN = 9999;
	private const FIELD_SCORE = 27;

	/**
	 * Retourne le formulaire Qualiopi complet (structure GF)
	 *
	 * @return array Formulaire avec 5 pages, ~50 champs.
	 */
	public static function getQualiopiForm(): array {
		return array(
			'id'          => self::FORM_ID,
			'title'       => 'Formation Révélation Digitale Qualiopi',
			'description' => 'Formulaire d\'inscription avec test de positionnement',
			'is_active'   => '1',
			'fields'      => self::getFormFields(),
			'pagination'  => array(
				'pages'                       => array( 'Page 1', 'Page 2', 'Page 3', 'Page 4', 'Page 5' ),
				'style'                       => 'percentage',
				'backgroundColor'             => '#0E76FE',
				'color'                       => '#FFFFFF',
				'progressbar_completion_text' => 'Complet',
			),
		);
	}

	/**
	 * Retourne tous les champs du formulaire
	 *
	 * @return array Champs GF (simplified).
	 */
	private static function getFormFields(): array {
		return array(
			// Page 1 : Informations personnelles
			array( 'id' => 1, 'label' => 'Prénom', 'type' => 'text', 'pageNumber' => 1 ),
			array( 'id' => 2, 'label' => 'Nom', 'type' => 'text', 'pageNumber' => 1 ),
			array( 'id' => 3, 'label' => 'Email', 'type' => 'email', 'pageNumber' => 1 ),
			array( 'id' => 4, 'label' => 'Téléphone', 'type' => 'phone', 'pageNumber' => 1 ),
			array( 'id' => 5, 'label' => 'Adresse', 'type' => 'address', 'pageNumber' => 1 ),

			// Page 2 : Test de positionnement (10 questions × 2 points)
			array( 'id' => 10, 'label' => 'Question 1', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 11, 'label' => 'Question 2', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 12, 'label' => 'Question 3', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 13, 'label' => 'Question 4', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 14, 'label' => 'Question 5', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 15, 'label' => 'Question 6', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 16, 'label' => 'Question 7', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 17, 'label' => 'Question 8', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 18, 'label' => 'Question 9', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => 19, 'label' => 'Question 10', 'type' => 'radio', 'pageNumber' => 2 ),
			array( 'id' => self::FIELD_SCORE, 'label' => 'Score', 'type' => 'calculation', 'pageNumber' => 2 ),

			// Page 3 : Informations financières
			array( 'id' => 30, 'label' => 'Financement', 'type' => 'select', 'pageNumber' => 3 ),
			array( 'id' => 31, 'label' => 'CPF', 'type' => 'checkbox', 'pageNumber' => 3 ),

			// Page 4 : Signature Yousign
			array( 'id' => 40, 'label' => 'Signature électronique', 'type' => 'fileupload', 'pageNumber' => 4 ),

			// Page 5 : Validation finale
			array( 'id' => 50, 'label' => 'Confirmation', 'type' => 'checkbox', 'pageNumber' => 5 ),

			// Champ token HMAC (caché)
			array( 'id' => self::FIELD_TOKEN, 'label' => 'Token', 'type' => 'hidden', 'pageNumber' => 1 ),
		);
	}

	/**
	 * Retourne les données de soumission Page 1 (informations personnelles)
	 *
	 * @param int $entry_id Entry ID (défaut: 123).
	 * @return array Submission data GF.
	 */
	public static function getSubmissionPage1( int $entry_id = 123 ): array {
		return array(
			'id'                   => $entry_id,
			'form_id'              => self::FORM_ID,
			'date_created'         => '2025-10-10 14:30:00',
			'is_starred'           => 0,
			'is_read'              => 0,
			'ip'                   => '192.168.1.100',
			'source_url'           => 'https://tb-formation.fr/inscription',
			'user_agent'           => 'Mozilla/5.0...',
			'status'               => 'active',
			'created_by'           => 1,

			// Champs Page 1
			'1'                    => 'Jean',
			'2'                    => 'Dupont',
			'3'                    => 'jean.dupont@example.com',
			'4'                    => '0612345678',
			'5'                    => '123 Rue de la Paix, 75001 Paris',

			// Token HMAC
			self::FIELD_TOKEN      => self::generateToken( $entry_id ),
		);
	}

	/**
	 * Retourne les données de soumission Page 2 avec score
	 *
	 * @param float $score    Score de positionnement (0-20).
	 * @param int   $entry_id Entry ID.
	 * @return array Submission data avec score calculé.
	 */
	public static function getSubmissionPage2WithScore( float $score, int $entry_id = 123 ): array {
		$data = self::getSubmissionPage1( $entry_id );

		// Ajouter les réponses du test (questions 10-19)
		$data['10'] = 'Réponse 1';
		$data['11'] = 'Réponse 2';
		$data['12'] = 'Réponse 3';
		$data['13'] = 'Réponse 4';
		$data['14'] = 'Réponse 5';
		$data['15'] = 'Réponse 6';
		$data['16'] = 'Réponse 7';
		$data['17'] = 'Réponse 8';
		$data['18'] = 'Réponse 9';
		$data['19'] = 'Réponse 10';

		// Score calculé (champ 27)
		$data[ self::FIELD_SCORE ] = $score;

		return $data;
	}

	/**
	 * Retourne les données de soumission Page 3 (informations financières)
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Submission data.
	 */
	public static function getSubmissionPage3( int $entry_id = 123 ): array {
		$data = self::getSubmissionPage2WithScore( 15.0, $entry_id );

		// Ajouter champs Page 3
		$data['30'] = 'CPF';
		$data['31'] = array( '1' );

		return $data;
	}

	/**
	 * Retourne les données de soumission Page 4 (signature)
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Submission data.
	 */
	public static function getSubmissionPage4( int $entry_id = 123 ): array {
		$data = self::getSubmissionPage3( $entry_id );

		// Ajouter champs Page 4
		$data['40'] = 'https://yousign.com/signature/abc123';

		return $data;
	}

	/**
	 * Retourne les données de soumission Page 5 (validation finale)
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Submission data complète.
	 */
	public static function getSubmissionPage5( int $entry_id = 123 ): array {
		$data = self::getSubmissionPage4( $entry_id );

		// Ajouter champs Page 5
		$data['50'] = array( '1' );

		return $data;
	}

	/**
	 * Retourne un payload Manager complet (pour tests Handler)
	 *
	 * @param int    $from_page Page source.
	 * @param int    $to_page   Page cible.
	 * @param string $direction Direction ('forward'|'backward').
	 * @param int    $entry_id  Entry ID.
	 * @param float  $score     Score (si transition 2→3).
	 * @return array Payload structuré.
	 */
	public static function getManagerPayload(
		int $from_page,
		int $to_page,
		string $direction = 'forward',
		int $entry_id = 123,
		float $score = 15.0
	): array {
		$form            = self::getQualiopiForm();
		$submission_data = self::getSubmissionDataForPage( $from_page, $score, $entry_id );

		return array(
			// Métadonnées formulaire
			'form_id'         => $form['id'],
			'form_title'      => $form['title'],

			// Métadonnées entrée
			'entry_id'        => $entry_id,
			'token'           => $submission_data[ self::FIELD_TOKEN ],

			// Navigation
			'from_page'       => $from_page,
			'to_page'         => $to_page,
			'direction'       => $direction,

			// Données complètes
			'submission_data' => $submission_data,
			'form'            => $form,

			// Contexte
			'timestamp'       => '2025-10-10 14:35:00',
			'user_ip'         => '192.168.1.100',
			'user_id'         => 1,
		);
	}

	/**
	 * Retourne les données de soumission selon la page
	 *
	 * @param int   $page     Numéro de page.
	 * @param float $score    Score (si page >= 2).
	 * @param int   $entry_id Entry ID.
	 * @return array Submission data.
	 */
	private static function getSubmissionDataForPage( int $page, float $score, int $entry_id ): array {
		switch ( $page ) {
			case 1:
				return self::getSubmissionPage1( $entry_id );
			case 2:
				return self::getSubmissionPage2WithScore( $score, $entry_id );
			case 3:
				return self::getSubmissionPage3( $entry_id );
			case 4:
				return self::getSubmissionPage4( $entry_id );
			case 5:
				return self::getSubmissionPage5( $entry_id );
			default:
				return self::getSubmissionPage1( $entry_id );
		}
	}

	/**
	 * Génère un token HMAC réaliste
	 *
	 * @param int $entry_id Entry ID.
	 * @return string Token.
	 */
	private static function generateToken( int $entry_id ): string {
		return hash_hmac( 'sha256', 'entry_' . $entry_id . '_form_' . self::FORM_ID . '_' . time(), 'test_secret_key' );
	}
}

