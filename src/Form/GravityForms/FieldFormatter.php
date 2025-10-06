<?php
/**
 * Formatage des données de champs Gravity Forms
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Form\GravityForms;

defined( 'ABSPATH' ) || exit;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Form\MentionsLegales\MentionsHelper;
use WcQualiopiFormation\Utils\Logger;

/**
 * Classe de formatage des données de champs
 */
class FieldFormatter {

	/**
	 * Instance du logger
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Formate l'adresse sans code postal et ville
	 *
	 * @param array $company_data Données entreprise.
	 * @return string Adresse formatée.
	 */
	public function format_adresse_sans_cp_ville( $company_data ) {
		$parts = array();

		if ( ! empty( $company_data['adresse_numero'] ) ) {
			$parts[] = $company_data['adresse_numero'];
		}

		if ( ! empty( $company_data['adresse_voie'] ) ) {
			$parts[] = $company_data['adresse_voie'];
		}

		if ( ! empty( $company_data['adresse_complement'] ) ) {
			$parts[] = $company_data['adresse_complement'];
		}

		$adresse = implode( ' ', $parts );

		$this->logger->debug( '[FieldFormatter] Adresse formatee', array(
			'parts_count' => count( $parts ),
			'result' => $adresse,
		) );

		return $adresse;
	}

	/**
	 * Convertit un type d'entreprise en libellé lisible
	 *
	 * @param string $type Type d'entreprise.
	 * @return string Libellé.
	 */
	public function get_type_entreprise_label( $type ) {
		$labels = array(
			'pm'                      => __( 'Personne morale', Constants::TEXT_DOMAIN ),
			'personne_morale'         => __( 'Personne morale', Constants::TEXT_DOMAIN ),
			'ei'                      => __( 'Entrepreneur individuel', Constants::TEXT_DOMAIN ),
			'entrepreneur_individuel' => __( 'Entrepreneur individuel', Constants::TEXT_DOMAIN ),
			'inconnu'                 => __( 'Inconnu', Constants::TEXT_DOMAIN ),
		);

		$label = $labels[ $type ] ?? $type;

		$this->logger->debug( '[FieldFormatter] Type entreprise converti', array(
			'type_raw' => $type,
			'label' => $label,
		) );

		return $label;
	}

	/**
	 * Formate le statut actif/inactif
	 *
	 * @param bool $is_active Statut actif.
	 * @return string Libellé localisé.
	 */
	public function format_statut_actif( $is_active ) {
		return $is_active 
			? __( 'Actif', Constants::TEXT_DOMAIN ) 
			: __( 'Inactif', Constants::TEXT_DOMAIN );
	}

	/**
	 * Formate la forme juridique
	 *
	 * @param string $forme_juridique Code ou libellé forme juridique.
	 * @return string Forme juridique formatée.
	 */
	public function format_forme_juridique( $forme_juridique ) {
		$forme_formatted = MentionsHelper::get_forme_juridique( $forme_juridique );
		return ! empty( $forme_formatted ) ? $forme_formatted : $forme_juridique;
	}
}

