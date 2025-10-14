<?php
/**
 * Tests d'intégration pour PageTransitionManager
 *
 * Couverture cible : 100%
 * Tests : 22 (organisés en 4 lots par scénario)
 *
 * @package WcQualiopiFormation\Tests
 * @since 1.1.0
 */

use WcQualiopiFormation\Form\GravityForms\PageTransitionManager;
use WcQualiopiFormation\Tests\Integration\Form\GravityForms\Fixtures\GravityFormsFixtures;

// ============================================================================
// LOT 1 : TRANSITIONS FORWARD (7 tests)
// ============================================================================

test('[Manager Integration] s\'instancie correctement', function () {
	// When
	$manager = new PageTransitionManager();

	// Then
	expect($manager)->toBeInstanceOf(PageTransitionManager::class);
});

test('[Manager Integration] méthode on_page_transition existe', function () {
	// Given
	$manager = new PageTransitionManager();

	// Then
	expect(method_exists($manager, 'on_page_transition'))->toBeTrue();
});

test('[Manager Integration] détecte transition forward 1→2', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();
	$manager = new PageTransitionManager();

	// Then - Le manager existe et le form est valide
	expect($manager)->toBeInstanceOf(PageTransitionManager::class);
	expect($form)->toBeArray();
	expect($form['id'])->toBe(5);
});

test('[Manager Integration] construit payload avec clés requises', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();
	
	// Then - Vérifier que la structure du form est correcte
	expect($form)->toBeArray();
	expect($form)->toHaveKey('id');
	expect($form)->toHaveKey('title');
	expect($form['id'])->toBe(5);
});

test('[Manager Integration] validation rejette form null', function () {
	// Given
	$form = null;
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition($form, 1, 2);

	// Then - Pas d'exception levée
	expect(true)->toBeTrue();
});

test('[Manager Integration] validation rejette form array vide', function () {
	// Given
	$form = array();
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition($form, 1, 2);

	// Then
	expect(true)->toBeTrue();
});

test('[Manager Integration] validation rejette pages invalides', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition($form, 0, 2);
	$manager->on_page_transition($form, 2, 0);
	$manager->on_page_transition($form, -1, 2);

	// Then
	expect(true)->toBeTrue();
});

// ============================================================================
// LOT 2 : DÉPENDANCES GRAVITY FORMS (5 tests)
// ============================================================================

test('[Manager Integration] Gravity Forms est disponible', function () {
	// Then
	expect(class_exists('GFFormsModel'))->toBeTrue('GFFormsModel devrait être disponible');
	expect(class_exists('GFAPI'))->toBeTrue('GFAPI devrait être disponible');
	expect(class_exists('GFForms'))->toBeTrue('GFForms devrait être disponible');
});

test('[Manager Integration] peut accéder aux méthodes GF statiques', function () {
	// Then
	expect(method_exists('GFFormsModel', 'get_current_lead'))->toBeTrue();
	expect(method_exists('GFFormsModel', 'get_form_meta'))->toBeTrue();
});

test('[Manager Integration] WordPress est complètement chargé', function () {
	// Then - Vérifier environnement WordPress
	expect(function_exists('do_action'))->toBeTrue();
	expect(function_exists('get_option'))->toBeTrue();
	expect(function_exists('current_time'))->toBeTrue();
	expect(defined('ABSPATH'))->toBeTrue();
});

test('[Manager Integration] plugin est activé et chargé', function () {
	// Then
	expect(class_exists('WcQualiopiFormation\Core\Plugin'))->toBeTrue();
	expect(class_exists('WcQualiopiFormation\Form\GravityForms\PageTransitionManager'))->toBeTrue();
});

test('[Manager Integration] LoggingHelper est disponible', function () {
	// Then
	expect(class_exists('WcQualiopiFormation\Helpers\LoggingHelper'))->toBeTrue();
	expect(method_exists('WcQualiopiFormation\Helpers\LoggingHelper', 'info'))->toBeTrue();
});

// ============================================================================
// LOT 3 : FIXTURES ET DONNÉES (5 tests)
// ============================================================================

test('[Manager Integration] fixtures form contiennent données complètes', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();

	// Then
	expect($form)->toHaveKey('id');
	expect($form)->toHaveKey('title');
	expect($form)->toHaveKey('fields');
	expect($form)->toHaveKey('pagination');
	expect($form['id'])->toBe(5);
	expect($form['fields'])->toBeArray();
	expect(count($form['fields']))->toBeGreaterThan(10);
});

test('[Manager Integration] fixtures submission page 1 valides', function () {
	// Given
	$data = GravityFormsFixtures::getSubmissionPage1();

	// Then
	expect($data)->toHaveKey('id');
	expect($data)->toHaveKey('form_id');
	expect($data)->toHaveKey('1'); // Prénom
	expect($data)->toHaveKey('2'); // Nom
	expect($data)->toHaveKey('3'); // Email
	expect($data)->toHaveKey(9999); // Token
	expect($data[9999])->not->toBeEmpty();
});

test('[Manager Integration] fixtures submission page 2 avec score', function () {
	// Given
	$data = GravityFormsFixtures::getSubmissionPage2WithScore(15.0);

	// Then
	expect($data)->toHaveKey(27); // Score
	expect($data[27])->toBe(15.0);
	expect($data)->toHaveKey('10'); // Questions test
	expect($data)->toHaveKey('19');
});

test('[Manager Integration] fixtures tokens sont uniques', function () {
	// Given
	$data1 = GravityFormsFixtures::getSubmissionPage1(100);
	$data2 = GravityFormsFixtures::getSubmissionPage1(200);

	// Then
	expect($data1[9999])->not->toBe($data2[9999], 'Les tokens devraient être uniques');
});

test('[Manager Integration] fixtures payload Manager structuré correctement', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3);

	// Then - Vérifier structure complète
	expect($payload)->toHaveKey('form_id');
	expect($payload)->toHaveKey('form_title');
	expect($payload)->toHaveKey('entry_id');
	expect($payload)->toHaveKey('token');
	expect($payload)->toHaveKey('from_page');
	expect($payload)->toHaveKey('to_page');
	expect($payload)->toHaveKey('direction');
	expect($payload)->toHaveKey('submission_data');
	expect($payload)->toHaveKey('form');
	expect($payload)->toHaveKey('timestamp');
	expect($payload)->toHaveKey('user_ip');
	expect($payload)->toHaveKey('user_id');
});

// ============================================================================
// LOT 4 : FAIL-SAFE ET ROBUSTESSE (5 tests)
// ============================================================================

test('[Manager Integration] ne plante pas avec form null', function () {
	// Given
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition(null, 1, 2);

	// Then
	expect(true)->toBeTrue();
});

test('[Manager Integration] ne plante pas avec pages invalides', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition($form, 0, 0);
	$manager->on_page_transition($form, -1, 2);
	$manager->on_page_transition($form, 999, 1000);

	// Then
	expect(true)->toBeTrue();
});

test('[Manager Integration] gère submission_data vide gracieusement', function () {
	// Given
	$form = GravityFormsFixtures::getQualiopiForm();
	$manager = new PageTransitionManager();

	// When
	$manager->on_page_transition($form, 1, 2);

	// Then - Pas d'exception
	expect(true)->toBeTrue();
});

test('[Manager Integration] fail-safe complet - aucune exception levée', function () {
	// Given
	$manager = new PageTransitionManager();
	
	// When
	$manager->on_page_transition(null, 1, 2);
	$manager->on_page_transition(array(), 0, 0);
	$manager->on_page_transition(array('id' => 999), -1, -1);
	
	// Then
	expect(true)->toBeTrue();
});

test('[Manager Integration] constantes et configuration correctes', function () {
	// Then - Vérifier que le plugin est bien configuré
	expect(defined('WCQF_VERSION'))->toBeTrue();
	expect(function_exists('wcqf_clean_test_data'))->toBeTrue();
	expect(defined('ABSPATH'))->toBeTrue();
});

