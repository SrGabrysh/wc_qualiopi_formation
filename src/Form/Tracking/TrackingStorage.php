<?php
/**
 * TrackingStorage - Stockage BDD pour le tracking
 *
 * @package WcQualiopiFormation\Form\Tracking
 */

namespace WcQualiopiFormation\Form\Tracking;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Helpers\SanitizationHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingStorage
 * Gestion du stockage des données de tracking
 */
class TrackingStorage {

/**
	 * Constructeur
	 */
	public function __construct() {
	}

	/**
	 * Insère une nouvelle entrée de tracking
	 *
	 * @param array $data Données à insérer.
	 * @return int|false ID de l'entrée insérée ou false.
	 */
	public function insert( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$defaults = array(
			'token'          => '',
			'form_id'        => 0,
			'entry_id'       => 0,
			'user_id'        => get_current_user_id(),
			'ip_address'     => $this->get_client_ip(),
			'user_agent'     => $this->get_user_agent(),
			'session_id'     => '',
			'data_personal'  => '',
			'data_company'   => '',
			'data_test'      => '',
			'data_metadata'  => '',
			'data_full'      => '',
			'created_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( $table, $data, array(
			'%s', // token
			'%d', // form_id
			'%d', // entry_id
			'%d', // user_id
			'%s', // ip_address
			'%s', // user_agent
			'%s', // session_id
			'%s', // data_personal
			'%s', // data_company
			'%s', // data_test
			'%s', // data_metadata
			'%s', // data_full
			'%s', // created_at
		) );

		if ( false === $result ) {
			LoggingHelper::error( 'Tracking insert failed', array(
				'error' => $wpdb->last_error,
			) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Récupère une entrée par token
	 *
	 * @param string $token Token.
	 * @return object|null Entrée ou null.
	 */
	public function get_by_token( $token ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE token = %s ORDER BY created_at DESC LIMIT 1",
				$token
			)
		);

		return $result;
	}

	/**
	 * Récupère toutes les entrées d'un utilisateur
	 *
	 * @param int $user_id ID utilisateur.
	 * @return array Entrées.
	 */
	public function get_by_user( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);

		return $results;
	}

	/**
	 * Récupère les entrées d'un formulaire
	 *
	 * @param int $form_id ID formulaire.
	 * @param int $limit Limite.
	 * @return array Entrées.
	 */
	public function get_by_form( $form_id, $limit = 100 ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE form_id = %d ORDER BY created_at DESC LIMIT %d",
				$form_id,
				$limit
			)
		);

		return $results;
	}

	/**
	 * Compte le nombre d'entrées
	 *
	 * @param array $filters Filtres (form_id, user_id, etc.).
	 * @return int Nombre d'entrées.
	 */
	public function count( $filters = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;
		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['form_id'] ) ) {
			$where[] = 'form_id = %d';
			$values[] = $filters['form_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$values[] = $filters['user_id'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Supprime les entrées anciennes
	 *
	 * @param int $days Nombre de jours à conserver.
	 * @return int Nombre d'entrées supprimées.
	 */
	public function cleanup_old( $days = 90 ) {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		LoggingHelper::info( 'Tracking cleanup', array(
			'deleted' => $count,
			'days' => $days,
		) );

		return $count;
	}

	/**
	 * Récupère l'IP du client (anonymisée)
	 *
	 * @return string IP anonymisée.
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = SanitizationHelper::sanitize_name( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = SanitizationHelper::sanitize_name( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = SanitizationHelper::sanitize_name( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Anonymiser l'IP (RGPD).
		return wp_privacy_anonymize_ip( $ip );
	}

	/**
	 * Récupère le user agent
	 *
	 * @return string User agent.
	 */
	private function get_user_agent() {
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return substr( SanitizationHelper::sanitize_name( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 );
		}

		return '';
	}

	/**
	 * Récupère les statistiques globales
	 *
	 * @return array Statistiques.
	 */
	public function get_global_stats() {
		global $wpdb;

		$table = $wpdb->prefix . Constants::TABLE_TRACKING;

		$stats = array(
			'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'today' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()" ),
			'week' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
			'month' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" ),
		);

		return $stats;
	}
}




