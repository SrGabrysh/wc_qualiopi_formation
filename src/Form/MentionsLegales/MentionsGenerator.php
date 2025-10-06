<?php
/**
 * Génération des mentions légales
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\MentionsLegales;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Utils\Logger;
use WcQualiopiFormation\Form\MentionsLegales\MentionsValidator;
use WcQualiopiFormation\Form\MentionsLegales\MentionsFormatter;
use WcQualiopiFormation\Helpers\NameFormatter;
use WcQualiopiFormation\Helpers\DataHelper;
use WcQualiopiFormation\Helpers\SiretFormatter;
use WcQualiopiFormation\Helpers\AddressFormatter as CommonAddressFormatter;
use WcQualiopiFormation\Form\MentionsLegales\AddressFormatter;
use WcQualiopiFormation\Form\MentionsLegales\JuridicalCodeConverter;
use WcQualiopiFormation\Form\MentionsLegales\EntrepriseTypeDetector;

/**
 * Classe de génération des mentions légales
 *
 * Fonctionnalités :
 * - Génération mentions selon type d'entreprise
 * - Formatage adresse complète
 * - Extraction forme juridique
 * - Template conforme Qualiopi
 */
class MentionsGenerator {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Instance du validator
	 *
	 * @var MentionsValidator
	 */
	private $validator;

	/**
	 * Instance du formatter
	 *
	 * @var MentionsFormatter
	 */
	private $formatter;

	/**
	 * Instance de l'address formatter
	 *
	 * @var AddressFormatter
	 */
	private $address_formatter;

	/**
	 * Instance du code converter
	 *
	 * @var JuridicalCodeConverter
	 */
	private $code_converter;

	/**
	 * Instance du type detector
	 *
	 * @var EntrepriseTypeDetector
	 */
	private $type_detector;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger    = $logger;
		$this->validator = new MentionsValidator();
		$this->formatter = new MentionsFormatter();
		$this->address_formatter = new AddressFormatter();
		$this->code_converter = new JuridicalCodeConverter();
		$this->type_detector = new EntrepriseTypeDetector();

		$this->logger->info( '[MentionsGenerator] __construct DEBUT - Formatter initialise' );
	}

	/**
	 * Génère les mentions légales à partir des données entreprise
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $representant_data Données du représentant (prénom, nom).
	 * @return string Mentions légales formatées.
	 */
	public function generate( $company_data, $representant_data = array() ) {
		$this->logger->info( '[MentionsGenerator] generate DEBUT', array(
			'type' => $company_data['type_entreprise'] ?? 'N/A',
			'has_representant' => ! empty( $representant_data ),
			'company_keys' => array_keys( $company_data ),
		) );

		if ( ! is_array( $company_data ) ) {
			$this->logger->error( '[MentionsGenerator] ERREUR: Donnees entreprise invalides' );
			return '';
		}

		$type_entreprise = $company_data['type_entreprise'] ?? 'inconnu';

		$this->logger->info( 'Generating mentions legales', array( 'type' => $type_entreprise ) );

		$mentions = '';

		switch ( $type_entreprise ) {
			case 'pm': // Personne morale
			case 'personne_morale': // Rétro-compatibilité
				$mentions = $this->generate_for_personne_morale( $company_data, $representant_data );
				break;

			case 'ei': // Entrepreneur individuel
			case 'entrepreneur_individuel': // Rétro-compatibilité
				$mentions = $this->generate_for_entrepreneur_individuel( $company_data );
				break;

			default:
				$this->logger->warning( 'Unknown company type, using fallback', array( 'type' => $type_entreprise ) );
				$mentions = $this->generate_fallback( $company_data );
				break;
		}

		// Filtre pour personnalisation.
		$mentions = apply_filters( 'wcqf_mentions_legales', $mentions, $company_data, $representant_data );

		$this->logger->info( 'Mentions legales generated successfully' );

		return $mentions;
	}

	/**
	 * Génère les mentions pour une personne morale
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $representant_data Données du représentant.
	 * @return string Mentions légales.
	 */
	private function generate_for_personne_morale( $company_data, $representant_data ) {
		// Extraire et formater les données de base
		$formatted_data = $this->extract_personne_morale_data( $company_data, $representant_data );
		
		// Construire les mentions de base
		$mentions = $this->build_personne_morale_base_mentions( $formatted_data );
		
		// Ajouter les informations de représentation si applicable
		$mentions = $this->add_representation_info( $mentions, $formatted_data );

		$this->logger->info( '[MentionsGenerator] Mentions generees', array(
			'mentions' => $mentions,
		) );

		return $mentions;
	}

	/**
	 * Extrait et formate les données pour une personne morale
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @param array $representant_data Données du représentant.
	 * @return array Données formatées.
	 */
	private function extract_personne_morale_data( $company_data, $representant_data ) {
		$denomination  = $company_data['denomination'] ?? '';
		$forme_juridique_code = $company_data['forme_juridique'] ?? '';
		$forme_juridique = $this->code_converter->convert_code_to_label( $forme_juridique_code );
		$adresse       = $this->address_formatter->format_complete( $company_data );
		$siren         = $company_data['siren'] ?? '';
		$siren_formate = SiretFormatter::format_siren( $siren );
		$ville         = $company_data['adresse_ville'] ?? '';

		$prenom_representant = $representant_data['prenom'] ?? '';
		$nom_representant    = $representant_data['nom'] ?? '';
		$representant        = ! empty( $prenom_representant ) && ! empty( $nom_representant )
			? "{$nom_representant} {$prenom_representant}"
			: '{REPRESENTANT}';

		$this->logger->info( '[MentionsGenerator] Donnees representant', array(
			'prenom' => $prenom_representant,
			'nom' => $nom_representant,
			'representant' => $representant,
			'forme_juridique_code' => $forme_juridique_code,
			'forme_juridique_libelle' => $forme_juridique,
			'is_societe_capital' => $this->type_detector->is_societe_capital( $forme_juridique ),
		) );

		return array(
			'denomination' => $denomination,
			'forme_juridique' => $forme_juridique,
			'adresse' => $adresse,
			'siren_formate' => $siren_formate,
			'ville' => $ville,
			'representant' => $representant,
			'is_societe_capital' => $this->type_detector->is_societe_capital( $forme_juridique ),
		);
	}

	/**
	 * Construit les mentions de base pour une personne morale
	 *
	 * @param array $data Données formatées.
	 * @return string Mentions de base.
	 */
	private function build_personne_morale_base_mentions( $data ) {
		$mentions = "{$data['denomination']}, ";
		$mentions .= 'dont le siège social est situé au ';
		$mentions .= "{$data['adresse']}, ";
		$mentions .= "immatriculée au Registre du Commerce et des Sociétés de {$data['ville']} ";
		$mentions .= "sous le numéro {$data['siren_formate']} ";

		return $mentions;
	}

	/**
	 * Ajoute les informations de représentation aux mentions
	 *
	 * @param string $mentions Mentions de base.
	 * @param array $data Données formatées.
	 * @return string Mentions complètes.
	 */
	private function add_representation_info( $mentions, $data ) {
		if ( $data['is_societe_capital'] ) {
			$mentions .= "représentée par {$data['representant']} agissant et ayant les pouvoirs nécessaires";
			$this->logger->info( '[MentionsGenerator] Representant ajoute aux mentions' );
		} else {
			$this->logger->warning( '[MentionsGenerator] Forme juridique ne correspond pas a une societe a capital' );
		}

		$mentions .= '.';

		return $mentions;
	}

	/**
	 * Génère les mentions pour un entrepreneur individuel
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @return string Mentions légales.
	 */
	private function generate_for_entrepreneur_individuel( $company_data ) {
		$nom           = strtoupper( $company_data['nom'] ?? '' );
		$prenom        = $company_data['prenom'] ?? '';
		$adresse       = $this->address_formatter->format_complete( $company_data );
		$siret         = $company_data['siret'] ?? '';
		$siret_formate = SiretFormatter::format_siret( $siret );

		$mentions = "{$nom} {$prenom}, ";
		$mentions .= "demeurant au {$adresse}, ";
		$mentions .= "immatriculé au répertoire des entreprises et établissements de l'INSEE ";
		$mentions .= "sous le numéro {$siret_formate}, agissant en sa qualité d'Entrepreneur individuel.";

		return $mentions;
	}

	/**
	 * Génère les mentions en mode fallback
	 *
	 * @param array $company_data Données de l'entreprise.
	 * @return string Mentions légales minimales.
	 */
	private function generate_fallback( $company_data ) {
		$denomination = $company_data['denomination'] ?? '';

		if ( empty( $denomination ) ) {
			$nom    = $company_data['nom'] ?? '';
			$prenom = $company_data['prenom'] ?? '';
			$denomination = trim( "{$prenom} {$nom}" );
		}

		$adresse       = $this->address_formatter->format_complete( $company_data );
		$siret         = $company_data['siret'] ?? '';
		$siret_formate = SiretFormatter::format_siret( $siret );

		$mentions = "{$denomination}, ";
		$mentions .= "situé au {$adresse}, ";
		$mentions .= "immatriculé sous le numéro SIRET {$siret_formate}.";

		return $mentions;
	}
}
