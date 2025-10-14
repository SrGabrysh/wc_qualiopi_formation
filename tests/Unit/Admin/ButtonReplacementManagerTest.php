<?php
/**
 * Tests unitaires pour ButtonReplacementManager
 *
 * @package WcQualiopiFormation\Tests\Unit\Admin
 * @since 1.1.0
 */

namespace WcQualiopiFormation\Tests\Unit\Admin;

use WcQualiopiFormation\Admin\ButtonReplacementManager;
use WcQualiopiFormation\Security\SessionManager;
use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ButtonReplacementManagerTest
 * 
 * Tests unitaires pour la classe ButtonReplacementManager
 */
class ButtonReplacementManagerTest extends \WP_UnitTestCase {

	/**
	 * Test de remplacement du bouton pour utilisateur 'refused'
	 */
	public function test_replace_button_for_refused_user() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'button'
		);

		// Contenu HTML avec bouton "Suivant"
		$field_content = '<button type="button" class="gform_next_button">Suivant</button>';
		
		// Simuler un utilisateur avec statut 'refused'
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		$test_result = array(
			'score' => 5.0,
			'path' => 'refused',
			'verdict' => 'refused',
			'text' => 'Test non réussi',
			'timestamp' => time()
		);
		
		// Mock SessionManager::get()
		$this->mock_session_manager_get( $session_key, $test_result );

		// Appeler la méthode
		$result = ButtonReplacementManager::replace_next_button_for_refused( 
			$field_content, 
			$field, 
			'', 
			0, 
			$form_id 
		);

		// Vérifications
		$this->assertStringContainsString( 'Retour à l\'accueil', $result );
		$this->assertStringContainsString( 'gform_next_button', $result );
		$this->assertStringContainsString( 'onclick="window.location.href', $result );
		$this->assertStringNotContainsString( 'Suivant', $result );
	}

	/**
	 * Test de non-remplacement pour utilisateur 'reinforced'
	 */
	public function test_no_replace_for_reinforced_user() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'button'
		);

		// Contenu HTML avec bouton "Suivant"
		$field_content = '<button type="button" class="gform_next_button">Suivant</button>';
		
		// Simuler un utilisateur avec statut 'reinforced'
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		$test_result = array(
			'score' => 12.0,
			'path' => 'reinforced',
			'verdict' => 'reinforced',
			'text' => 'Test réussi avec renforcement',
			'timestamp' => time()
		);
		
		// Mock SessionManager::get()
		$this->mock_session_manager_get( $session_key, $test_result );

		// Appeler la méthode
		$result = ButtonReplacementManager::replace_next_button_for_refused( 
			$field_content, 
			$field, 
			'', 
			0, 
			$form_id 
		);

		// Vérifications - le contenu doit rester inchangé
		$this->assertEquals( $field_content, $result );
		$this->assertStringContainsString( 'Suivant', $result );
		$this->assertStringNotContainsString( 'Retour à l\'accueil', $result );
	}

	/**
	 * Test de non-remplacement pour utilisateur 'admitted'
	 */
	public function test_no_replace_for_admitted_user() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'button'
		);

		// Contenu HTML avec bouton "Suivant"
		$field_content = '<button type="button" class="gform_next_button">Suivant</button>';
		
		// Simuler un utilisateur avec statut 'admitted'
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		$test_result = array(
			'score' => 18.0,
			'path' => 'admitted',
			'verdict' => 'admitted',
			'text' => 'Test réussi',
			'timestamp' => time()
		);
		
		// Mock SessionManager::get()
		$this->mock_session_manager_get( $session_key, $test_result );

		// Appeler la méthode
		$result = ButtonReplacementManager::replace_next_button_for_refused( 
			$field_content, 
			$field, 
			'', 
			0, 
			$form_id 
		);

		// Vérifications - le contenu doit rester inchangé
		$this->assertEquals( $field_content, $result );
		$this->assertStringContainsString( 'Suivant', $result );
		$this->assertStringNotContainsString( 'Retour à l\'accueil', $result );
	}

	/**
	 * Test de non-remplacement si pas de bouton "Suivant"
	 */
	public function test_no_replace_if_no_next_button() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'text'
		);

		// Contenu HTML sans bouton "Suivant"
		$field_content = '<input type="text" name="field_1" />';
		
		// Simuler un utilisateur avec statut 'refused'
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		$test_result = array(
			'score' => 5.0,
			'path' => 'refused',
			'verdict' => 'refused',
			'text' => 'Test non réussi',
			'timestamp' => time()
		);
		
		// Mock SessionManager::get()
		$this->mock_session_manager_get( $session_key, $test_result );

		// Appeler la méthode
		$result = ButtonReplacementManager::replace_next_button_for_refused( 
			$field_content, 
			$field, 
			'', 
			0, 
			$form_id 
		);

		// Vérifications - le contenu doit rester inchangé
		$this->assertEquals( $field_content, $result );
	}

	/**
	 * Test de non-remplacement si pas de statut en session
	 */
	public function test_no_replace_if_no_status_in_session() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'button'
		);

		// Contenu HTML avec bouton "Suivant"
		$field_content = '<button type="button" class="gform_next_button">Suivant</button>';
		
		// Simuler un utilisateur sans statut en session
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		
		// Mock SessionManager::get() retournant null
		$this->mock_session_manager_get( $session_key, null );

		// Appeler la méthode
		$result = ButtonReplacementManager::replace_next_button_for_refused( 
			$field_content, 
			$field, 
			'', 
			0, 
			$form_id 
		);

		// Vérifications - le contenu doit rester inchangé
		$this->assertEquals( $field_content, $result );
	}

	/**
	 * Mock de SessionManager::get() pour les tests
	 * 
	 * @param string $key Clé de session
	 * @param mixed  $value Valeur à retourner
	 */
	private function mock_session_manager_get( string $key, $value ): void {
		// Utiliser une approche de mock avec une classe de test
		// Créer une classe de test qui étend SessionManager pour les tests
		$this->session_mock_data = array( $key => $value );
	}

	/**
	 * Données de session mockées pour les tests
	 * 
	 * @var array
	 */
	private $session_mock_data = array();

	/**
	 * Setup des tests - initialise les mocks
	 */
	public function setUp(): void {
		parent::setUp();
		$this->session_mock_data = array();
	}

	/**
	 * Test de remplacement du bouton pour utilisateur 'refused' avec mock fonctionnel
	 */
	public function test_replace_button_for_refused_user_with_mock() {
		// Mock du champ Gravity Forms
		$field = (object) array(
			'id' => 1,
			'type' => 'button'
		);

		// Contenu HTML avec bouton "Suivant"
		$field_content = '<button type="button" class="gform_next_button">Suivant</button>';
		
		// Simuler un utilisateur avec statut 'refused'
		$form_id = 1;
		$session_key = 'test_result_' . $form_id;
		$test_result = array(
			'score' => 5.0,
			'path' => 'refused',
			'verdict' => 'refused',
			'text' => 'Test non réussi',
			'timestamp' => time()
		);
		
		// Utiliser une approche de test avec une classe anonyme qui étend ButtonReplacementManager
		$test_manager = new class extends ButtonReplacementManager {
			private static $mock_data = array();
			
			public static function setMockData( array $data ): void {
				self::$mock_data = $data;
			}
			
			protected static function get_test_status( int $form_id ): ?string {
				$session_key = 'test_result_' . $form_id;
				$test_result = self::$mock_data[ $session_key ] ?? null;
				
				if ( ! is_array( $test_result ) || ! isset( $test_result['path'] ) ) {
					return null;
				}
				
				$status = \sanitize_text_field( $test_result['path'] );
				$valid_statuses = array( 'refused', 'reinforced', 'admitted' );
				
				return in_array( $status, $valid_statuses, true ) ? $status : null;
			}
		};
		
		$test_manager::setMockData( array( $session_key => $test_result ) );

		// Appeler la méthode publique statique
		$result = $test_manager::replace_next_button_for_refused( $field_content, $field, '', 0, $form_id );

		// Vérifications
		$this->assertStringContainsString( 'Retour à l\'accueil', $result );
		$this->assertStringContainsString( 'gform_next_button', $result );
		$this->assertStringContainsString( 'onclick="window.location.href', $result );
		$this->assertStringNotContainsString( 'Suivant', $result );
	}
}
