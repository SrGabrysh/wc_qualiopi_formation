<?php
/**
 * Test du traitement du payload API SIREN
 * Test AUTONOME avec données RÉELLES
 *
 * @package WcQualiopiFormation
 */

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Form\Siren\SirenAutocomplete;
use WcQualiopiFormation\Form\Siren\SirenValidator;
use WcQualiopiFormation\Form\MentionsLegales\MentionsGenerator;
use WcQualiopiFormation\Utils\Logger;

class SirenPayloadTest extends TestCase {

	private $logger;
	private $siren_autocomplete;
	private $validator;
	private $mentions_generator;

	/**
	 * Setup avant chaque test
	 */
	protected function setUp(): void {
		parent::setUp();
		
		$this->logger = Logger::get_instance();
		$this->siren_autocomplete = new SirenAutocomplete($this->logger);
		$this->validator = new SirenValidator();
		$this->mentions_generator = new MentionsGenerator($this->logger);
	}

	/**
	 * Test 1 : Parsing du payload établissement
	 */
	public function test_parse_payload_etablissement() {
		echo "\n=== TEST 1 : Parse payload établissement ===\n";

		// PAYLOAD RÉEL de l'API SIREN
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

		// Utiliser la réflexion pour tester la méthode privée
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
		echo "SIRET: " . $result['siret'] . "\n";
		echo "SIREN: " . $result['siren'] . "\n";
		echo "Dénomination: " . $result['denomination'] . "\n";
		echo "Adresse: " . $result['adresse_numero'] . " " . $result['adresse_voie'] . "\n";
		echo "Ville: " . $result['adresse_cp'] . " " . $result['adresse_ville'] . "\n";
		echo "Type entreprise: " . $result['type_entreprise'] . "\n";
		echo "Active: " . ($result['is_active'] ? 'OUI' : 'NON') . "\n";

		$this->assertEquals('81107469900034', $result['siret']);
		$this->assertEquals('811074699', $result['siren']);
		$this->assertEquals('POURQUOIBOUGER.COM', $result['denomination']);
		$this->assertEquals('123', $result['adresse_numero']);
		$this->assertEquals('RUE DE LA PAIX', $result['adresse_voie']);
		$this->assertEquals('75001', $result['adresse_cp']);
		$this->assertEquals('PARIS', $result['adresse_ville']);
		$this->assertTrue($result['is_active']);
		$this->assertNotEquals('inconnu', $result['type_entreprise']);
	}

	/**
	 * Test 2 : Détection type entreprise PM
	 */
	public function test_determine_type_entreprise_pm() {
		echo "\n=== TEST 2 : Détection type PM ===\n";

		$unite_legale = [
			'denomination' => 'POURQUOIBOUGER.COM',
			'nom' => null,
			'prenom' => null,
		];

		$type = $this->validator->determine_entreprise_type($unite_legale);

		echo "Type détecté: $type\n";
		echo "Attendu: pm\n";

		$this->assertEquals('pm', $type);
	}

	/**
	 * Test 3 : Détection type entreprise EI
	 */
	public function test_determine_type_entreprise_ei() {
		echo "\n=== TEST 3 : Détection type EI ===\n";

		$unite_legale = [
			'denomination' => null,
			'nom' => 'DUPONT',
			'prenom' => 'Jean',
		];

		$type = $this->validator->determine_entreprise_type($unite_legale);

		echo "Type détecté: $type\n";
		echo "Attendu: ei\n";

		$this->assertEquals('ei', $type);
	}

	/**
	 * Test 4 : Génération mentions légales PM
	 */
	public function test_generation_mentions_pm() {
		echo "\n=== TEST 4 : Génération mentions PM ===\n";

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

		$mentions = $this->mentions_generator->generate($company_data);

		echo "Mentions générées:\n";
		echo "$mentions\n";

		$this->assertStringContainsString('POURQUOIBOUGER.COM', $mentions);
		$this->assertStringContainsString('SARL', $mentions);
		$this->assertStringContainsString('811 074 699', $mentions);
		$this->assertStringContainsString('123 RUE DE LA PAIX', $mentions);
		$this->assertStringContainsString('75001 PARIS', $mentions);
	}

	/**
	 * Test 5 : Génération mentions légales EI
	 */
	public function test_generation_mentions_ei() {
		echo "\n=== TEST 5 : Génération mentions EI ===\n";

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

		$mentions = $this->mentions_generator->generate($company_data);

		echo "Mentions générées:\n";
		echo "$mentions\n";

		$this->assertStringContainsString('Jean DUPONT', $mentions);
		$this->assertStringContainsString('123 456 789 012 34', $mentions);
		$this->assertStringContainsString('45 AVENUE DES CHAMPS', $mentions);
		$this->assertStringContainsString('69000 LYON', $mentions);
	}
}




