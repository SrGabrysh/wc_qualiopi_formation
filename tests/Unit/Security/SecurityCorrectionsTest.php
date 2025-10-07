<?php
/**
 * Tests unitaires pour les corrections de sécurité 2025-10-07
 * 
 * @package WcQualiopiFormation\Tests\Unit\Security
 */

namespace WcQualiopiFormation\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Admin\AdminManager;
use WcQualiopiFormation\Cart\CartGuard;
use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\SecurityHelper;
use WcQualiopiFormation\Utils\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecurityCorrectionsTest
 * 
 * Tests pour valider les corrections de sécurité appliquées
 */
class SecurityCorrectionsTest extends TestCase {

	/**
	 * @var Logger
	 */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		
		// Mock du logger
		$this->logger = $this->createMock( Logger::class );
		
		// Mock des fonctions WordPress nécessaires
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
				return true; // Mock pour les tests
			}
		}
		
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return $capability === Constants::CAP_MANAGE_SETTINGS;
			}
		}
		
		if ( ! function_exists( 'wp_die' ) ) {
			function wp_die( $message ) {
				throw new \Exception( $message );
			}
		}
	}

	/**
	 * Test CSRF protection dans AdminManager
	 */
	public function test_admin_manager_csrf_protection() {
		// Mock des superglobales
		$_GET['page'] = 'wcqf-settings';
		$_GET['tab'] = 'logs';
		$_POST['wcqf_logs_action'] = 'export_logs';
		$_POST['_wpnonce'] = 'valid_nonce';

		// Mock check_admin_referer pour retourner false (CSRF fail)
		$original_check_admin_referer = null;
		if ( function_exists( 'check_admin_referer' ) ) {
			$original_check_admin_referer = 'check_admin_referer';
		}
		
		// Redéfinir la fonction pour simuler un échec CSRF
		if ( ! function_exists( 'check_admin_referer' ) ) {
			function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
				return false; // Simuler échec CSRF
			}
		}

		$admin_manager = new AdminManager( $this->logger, null );

		// Vérifier que wp_die est appelé en cas d'échec CSRF
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Token de sécurité invalide.' );
		
		$admin_manager->handle_export_actions();
	}

	/**
	 * Test capabilities alignment
	 */
	public function test_capabilities_alignment() {
		// Test SecurityHelper avec capability par défaut
		$this->assertTrue( SecurityHelper::check_admin_capability() );
		
		// Test avec capability explicite
		$this->assertTrue( SecurityHelper::check_admin_capability( Constants::CAP_MANAGE_SETTINGS ) );
		
		// Test avec capability différente
		$this->assertFalse( SecurityHelper::check_admin_capability( 'invalid_capability' ) );
	}

	/**
	 * Test Store API checkout sans vérification read capability
	 */
	public function test_store_api_checkout_no_read_capability() {
		// Mock WP_REST_Request
		$request = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_route' )->willReturn( '/wc/store/checkout' );
		$request->method( 'get_method' )->willReturn( 'POST' );

		// Mock CartBlocker
		$cart_blocker = $this->createMock( \WcQualiopiFormation\Cart\CartBlocker::class );
		$cart_blocker->method( 'should_block_checkout' )->willReturn( false );

		// Mock CartRenderer
		$cart_renderer = $this->createMock( \WcQualiopiFormation\Cart\CartRenderer::class );

		// Mock CartAssets
		$cart_assets = $this->createMock( \WcQualiopiFormation\Cart\CartAssets::class );

		$cart_guard = new CartGuard( $this->logger, $cart_blocker, $cart_renderer, $cart_assets );

		// Test que la méthode ne retourne pas d'erreur 403 pour unauthorized
		$response = $cart_guard->intercept_store_api_checkout( null, null, $request );
		
		// Devrait retourner null (pas d'erreur) car should_block_checkout() retourne false
		$this->assertNull( $response );
	}

	/**
	 * Test Store API checkout avec blocage
	 */
	public function test_store_api_checkout_with_blocking() {
		// Mock WP_REST_Request
		$request = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_route' )->willReturn( '/wc/store/checkout' );
		$request->method( 'get_method' )->willReturn( 'POST' );

		// Mock CartBlocker qui bloque
		$cart_blocker = $this->createMock( \WcQualiopiFormation\Cart\CartBlocker::class );
		$cart_blocker->method( 'should_block_checkout' )->willReturn( true );

		// Mock CartRenderer
		$cart_renderer = $this->createMock( \WcQualiopiFormation\Cart\CartRenderer::class );

		// Mock CartAssets
		$cart_assets = $this->createMock( \WcQualiopiFormation\Cart\CartAssets::class );

		$cart_guard = new CartGuard( $this->logger, $cart_blocker, $cart_renderer, $cart_assets );

		// Test que la méthode retourne une erreur de blocage
		$response = $cart_guard->intercept_store_api_checkout( null, null, $request );
		
		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertEquals( 'wcqf_checkout_blocked', $response->get_error_code() );
		$this->assertEquals( 403, $response->get_error_data()['status'] );
	}

	/**
	 * Test versioning consistency
	 */
	public function test_versioning_consistency() {
		// Vérifier que WCQF_VERSION est défini
		$this->assertTrue( defined( 'WCQF_VERSION' ) );
		
		// Vérifier que Constants::VERSION est aligné
		$this->assertEquals( WCQF_VERSION, Constants::VERSION );
	}

	/**
	 * Test text domain consistency
	 */
	public function test_text_domain_consistency() {
		// Vérifier que Constants::TEXT_DOMAIN est 'wcqf'
		$this->assertEquals( 'wcqf', Constants::TEXT_DOMAIN );
	}

	/**
	 * Test API key migration removal
	 */
	public function test_api_key_migration_removal() {
		// Mock des fonctions WordPress
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				return false; // Migration pas encore effectuée
			}
		}
		
		if ( ! function_exists( 'add_action' ) ) {
			function add_action( $hook, $callback ) {
				// Mock - ne fait rien
			}
		}
		
		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				// Mock - ne fait rien
			}
		}

		// Le test vérifie que la méthode existe et peut être appelée sans erreur
		$plugin = \WcQualiopiFormation\Core\Plugin::instance();
		
		// Utiliser la réflexion pour accéder à la méthode privée
		$reflection = new \ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'migrate_api_keys' );
		$method->setAccessible( true );
		
		// Cette méthode ne devrait plus contenir de clé hardcodée
		$this->expectNotToPerformAssertions(); // Le test passe si aucune exception n'est levée
		$method->invoke( $plugin );
	}

	protected function tearDown(): void {
		parent::tearDown();
		
		// Nettoyer les superglobales
		unset( $_GET['page'], $_GET['tab'], $_POST['wcqf_logs_action'], $_POST['_wpnonce'] );
	}
}
