<?php
/**
 * Test d'intégration SIMPLE pour PageTransitionManager
 * 
 * TEST PILOTE : Valider que l'environnement d'intégration fonctionne
 *
 * @package WcQualiopiFormation\Tests
 * @since 1.1.0
 */

use WcQualiopiFormation\Form\GravityForms\PageTransitionManager;

// ============================================================================
// TEST ULTRA-SIMPLE : Vérifier que ça marche
// ============================================================================

test('[Integration] Manager s\'instancie correctement', function () {
	// When
	$manager = new PageTransitionManager();

	// Then
	expect($manager)->toBeInstanceOf(PageTransitionManager::class);
});

test('[Integration] Gravity Forms est disponible', function () {
	// Then
	expect(class_exists('GFFormsModel'))->toBeTrue('Gravity Forms devrait être disponible');
	expect(class_exists('GFAPI'))->toBeTrue('GFAPI devrait être disponible');
});

test('[Integration] WordPress est chargé', function () {
	// Then
	expect(function_exists('get_bloginfo'))->toBeTrue();
	expect(function_exists('do_action'))->toBeTrue();
	expect(defined('ABSPATH'))->toBeTrue();
});

