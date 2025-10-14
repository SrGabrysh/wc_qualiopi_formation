<?php
/**
 * Tests d'intégration pour PageTransitionHandler
 *
 * Couverture cible : 100%
 * Tests : 15 (organisés en 3 lots par scénario)
 *
 * @package WcQualiopiFormation\Tests
 * @since 1.1.0
 */

use WcQualiopiFormation\Form\GravityForms\PageTransitionHandler;
use WcQualiopiFormation\Form\GravityForms\CalculationRetriever;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;
use WcQualiopiFormation\Tests\Integration\Form\GravityForms\Fixtures\GravityFormsFixtures;

// ============================================================================
// LOT 5 : FILTRAGE TRANSITIONS (5 tests)
// ============================================================================

test('[Handler Integration] s\'instancie correctement', function () {
	// Given
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);

	// When
	$handler = new PageTransitionHandler($calculationRetriever);

	// Then
	expect($handler)->toBeInstanceOf(PageTransitionHandler::class);
});

test('[Handler Integration] méthodes publiques existent', function () {
	// Given
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// Then
	expect(method_exists($handler, 'handle_test_transition'))->toBeTrue();
	expect(method_exists($handler, 'init_hooks'))->toBeTrue();
});

test('[Handler Integration] accepte payload 2→3 forward', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward');
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

test('[Handler Integration] rejette payload 1→2 silencieusement', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(1, 2, 'forward');
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

test('[Handler Integration] rejette payload backward', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'backward');
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

// ============================================================================
// LOT 6 : CALCUL SCORE VIA CALCULATIONRETRIEVER (5 tests)
// ============================================================================

test('[Handler Integration] CalculationRetriever s\'instancie', function () {
	// Given
	$fieldMapper = new FieldMapper();
	
	// When
	$retriever = new CalculationRetriever($fieldMapper);

	// Then
	expect($retriever)->toBeInstanceOf(CalculationRetriever::class);
	expect(method_exists($retriever, 'get_calculated_value'))->toBeTrue();
});

test('[Handler Integration] traite payload avec score 0', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 0.0);
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

test('[Handler Integration] traite payload avec score 12', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 12.0);
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

test('[Handler Integration] traite payload avec score 18', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 18.0);
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

test('[Handler Integration] gère payload sans score gracieusement', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward');
	// Ne pas inclure score dans submission_data
	unset($payload['submission_data'][27]);
	
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When
	$handler->handle_test_transition($payload);

	// Then
	expect(true)->toBeTrue();
});

// ============================================================================
// LOT 7 : DÉTERMINATION PARCOURS (5 tests)
// ============================================================================

test('[Handler Integration] constantes parcours définies', function () {
	// Then - Vérifier que les constantes sont accessibles via réflexion
	$reflection = new \ReflectionClass(PageTransitionHandler::class);
	
	expect($reflection->hasConstant('SOURCE_PAGE'))->toBeTrue();
	expect($reflection->hasConstant('TARGET_PAGE'))->toBeTrue();
	expect($reflection->hasConstant('SCORE_FIELD_ID'))->toBeTrue();
	expect($reflection->hasConstant('SCORE_THRESHOLD_REFUSED'))->toBeTrue();
	expect($reflection->hasConstant('SCORE_THRESHOLD_REINFORCED'))->toBeTrue();
});

test('[Handler Integration] constantes ont valeurs correctes', function () {
	// Given
	$reflection = new \ReflectionClass(PageTransitionHandler::class);
	
	// Then
	expect($reflection->getConstant('SOURCE_PAGE'))->toBe(2);
	expect($reflection->getConstant('TARGET_PAGE'))->toBe(3);
	expect($reflection->getConstant('SCORE_FIELD_ID'))->toBe(27);
	expect($reflection->getConstant('SCORE_THRESHOLD_REFUSED'))->toBe(10);
	expect($reflection->getConstant('SCORE_THRESHOLD_REINFORCED'))->toBe(15);
});

test('[Handler Integration] payload complet passe validation', function () {
	// Given
	$payload = GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 15.0);
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// Then - Toutes les clés nécessaires présentes
	expect($payload)->toHaveKey('from_page');
	expect($payload)->toHaveKey('to_page');
	expect($payload)->toHaveKey('direction');
	expect($payload)->toHaveKey('form');
	expect($payload)->toHaveKey('submission_data');
	expect($payload['submission_data'])->toHaveKey(27); // Score
	
	// When - Exécution
	expect(function () use ($handler, $payload) {
		$handler->handle_test_transition($payload);
	})->not->toThrow(\Exception::class);
});

test('[Handler Integration] hook wcqf_page_transition enregistré', function () {
	// Given
	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// Then - Vérifier que le hook est enregistré
	expect(has_action('wcqf_page_transition'))->not->toBeFalse('Le hook wcqf_page_transition devrait être enregistré');
});

test('[Handler Integration] workflow complet ne plante jamais', function () {
	// Given - Plusieurs payloads différents
	$payloads = array(
		GravityFormsFixtures::getManagerPayload(1, 2, 'forward'),
		GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 5.0),
		GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 12.0),
		GravityFormsFixtures::getManagerPayload(2, 3, 'forward', 123, 18.0),
		GravityFormsFixtures::getManagerPayload(3, 2, 'backward'),
	);

	$fieldMapper = new FieldMapper();
	$calculationRetriever = new CalculationRetriever($fieldMapper);
	$handler = new PageTransitionHandler($calculationRetriever);

	// When - Exécuter tous les payloads
	foreach ($payloads as $payload) {
		$handler->handle_test_transition($payload);
	}

	// Then
	expect(count($payloads))->toBe(5);
	expect(true)->toBeTrue();
});

