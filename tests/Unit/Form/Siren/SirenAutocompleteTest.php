<?php
/**
 * Tests unitaires SirenAutocomplete
 * Test du traitement du payload API SIREN
 *
 * @package WcQualiopiFormation
 */

use WcQualiopiFormation\Form\Siren\SirenAutocomplete;
use WcQualiopiFormation\Utils\Logger;

describe('SirenAutocomplete - Traitement payload API', function() {

	beforeEach(function() {
		// Mock WordPress functions
		if (!function_exists('get_transient')) {
			function get_transient($key) { return false; }
		}
		if (!function_exists('set_transient')) {
			function set_transient($key, $value, $ttl) { return true; }
		}
		if (!function_exists('delete_transient')) {
			function delete_transient($key) { return true; }
		}

		$this->logger = Logger::get_instance();
		$this->siren_autocomplete = new SirenAutocomplete($this->logger);
	});

	it('parse correctement le payload etablissement', function() {
		// PAYLOAD RÉEL de l'API SIREN pour SIRET 81107469900034
		$etablissement_response = [
			'etablissement' => [
				'siret' => '81107469900034',
				'siren' => '811074699',
				'adresse' => [
					'numero' => '123',
					'voie' => 'RUE DE LA PAIX',
					'complement' => '',
					'code_postal' => '75001',
					'ville' => 'PARIS',
				],
			]
		];

		$unite_legale_response = [
			'unite_legale' => [
				'siren' => '811074699',
				'denomination' => 'POURQUOIBOUGER.COM',
				'nom' => null,
				'prenom' => null,
				'forme_juridique' => 'SARL',
				'etat_administratif' => 'A',
				'capital' => 10000,
			]
		];

		// Utiliser la réflexion pour accéder à la méthode privée merge_company_data
		$reflection = new ReflectionClass($this->siren_autocomplete);
		$method = $reflection->getMethod('merge_company_data');
		$method->setAccessible(true);

		$result = $method->invoke(
			$this->siren_autocomplete,
			$etablissement_response,
			$unite_legale_response,
			'81107469900034',
			'811074699'
		);

		// ASSERTIONS
		expect($result)->toBeArray();
		expect($result['siret'])->toBe('81107469900034');
		expect($result['siren'])->toBe('811074699');
		expect($result['denomination'])->toBe('POURQUOIBOUGER.COM');
		expect($result['adresse_numero'])->toBe('123');
		expect($result['adresse_voie'])->toBe('RUE DE LA PAIX');
		expect($result['adresse_cp'])->toBe('75001');
		expect($result['adresse_ville'])->toBe('PARIS');
		expect($result['forme_juridique'])->toBe('SARL');
		expect($result['is_active'])->toBeTrue();
		expect($result['type_entreprise'])->not->toBe('inconnu');
	});

	it('détermine correctement le type entreprise PM', function() {
		$unite_legale = [
			'denomination' => 'POURQUOIBOUGER.COM',
			'nom' => null,
			'prenom' => null,
		];

		$reflection = new ReflectionClass($this->siren_autocomplete);
		
		// Accéder au validator
		$validator_property = $reflection->getProperty('validator');
		$validator_property->setAccessible(true);
		$validator = $validator_property->getValue($this->siren_autocomplete);

		$validator_reflection = new ReflectionClass($validator);
		$method = $validator_reflection->getMethod('determine_entreprise_type');
		$method->setAccessible(true);

		$type = $method->invoke($validator, $unite_legale);

		// ASSERTION : doit détecter PM (Personne Morale)
		expect($type)->toBe('pm');
	});

	it('détermine correctement le type entreprise EI', function() {
		$unite_legale = [
			'denomination' => null,
			'nom' => 'DUPONT',
			'prenom' => 'Jean',
		];

		$reflection = new ReflectionClass($this->siren_autocomplete);
		
		// Accéder au validator
		$validator_property = $reflection->getProperty('validator');
		$validator_property->setAccessible(true);
		$validator = $validator_property->getValue($this->siren_autocomplete);

		$validator_reflection = new ReflectionClass($validator);
		$method = $validator_reflection->getMethod('determine_entreprise_type');
		$method->setAccessible(true);

		$type = $method->invoke($validator, $unite_legale);

		// ASSERTION : doit détecter EI (Entrepreneur Individuel)
		expect($type)->toBe('ei');
	});

	it('génère les mentions légales pour PM', function() {
		$company_data = [
			'siret' => '81107469900034',
			'siren' => '811074699',
			'denomination' => 'POURQUOIBOUGER.COM',
			'adresse_numero' => '123',
			'adresse_voie' => 'RUE DE LA PAIX',
			'adresse_complement' => '',
			'adresse_cp' => '75001',
			'adresse_ville' => 'PARIS',
			'forme_juridique' => 'SARL',
			'type_entreprise' => 'pm',
			'is_active' => true,
		];

		// Créer une instance de MentionsGenerator
		$mentions_generator = new \WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator($this->logger);
		$mentions = $mentions_generator->generate($company_data);

		// ASSERTIONS
		expect($mentions)->toBeString();
		expect($mentions)->toContain('POURQUOIBOUGER.COM');
		expect($mentions)->toContain('SARL');
		expect($mentions)->toContain('811 074 699');
		expect($mentions)->toContain('123 RUE DE LA PAIX');
		expect($mentions)->toContain('75001 PARIS');
	});

	it('génère les mentions légales pour EI', function() {
		$company_data = [
			'siret' => '12345678901234',
			'siren' => '123456789',
			'nom' => 'DUPONT',
			'prenom' => 'Jean',
			'adresse_numero' => '45',
			'adresse_voie' => 'AVENUE DES CHAMPS',
			'adresse_complement' => '',
			'adresse_cp' => '69000',
			'adresse_ville' => 'LYON',
			'forme_juridique' => 'Entrepreneur individuel',
			'type_entreprise' => 'ei',
			'is_active' => true,
		];

		// Créer une instance de MentionsGenerator
		$mentions_generator = new \WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator($this->logger);
		$mentions = $mentions_generator->generate($company_data);

		// ASSERTIONS
		expect($mentions)->toBeString();
		expect($mentions)->toContain('Jean DUPONT');
		expect($mentions)->toContain('123 456 789 012 34');
		expect($mentions)->toContain('45 AVENUE DES CHAMPS');
		expect($mentions)->toContain('69000 LYON');
	});
});




