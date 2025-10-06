<?php
/**
 * Système de logging centralisé pour WC Qualiopi Formation
 * 
 * TOUS les logs du plugin passent par cette classe
 * Compatible WooCommerce Logging system
 *
 * @package WcQualiopiFormation\Utils
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Utils;

use WcQualiopiFormation\Core\Constants;

// Security: Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Logger
 * 
 * Logs vers WooCommerce et error_log selon le niveau
 */
class Logger {
	
	/**
	 * Instance singleton
	 * 
	 * @var Logger|null
	 */
	private static $instance = null;
	
	/**
	 * Logger WooCommerce
	 * 
	 * @var \WC_Logger|null
	 */
	private $wc_logger = null;
	
	/**
	 * Niveau de log minimum (DEBUG, INFO, WARNING, ERROR, CRITICAL)
	 * 
	 * @var string
	 */
	private $min_level = 'DEBUG';
	
	/**
	 * Niveaux de log avec priorités
	 * 
	 * @var array
	 */
	private const LEVELS = [
		'DEBUG'    => 0,
		'INFO'     => 1,
		'WARNING'  => 2,
		'ERROR'    => 3,
		'CRITICAL' => 4,
	];
	
	/**
	 * Constructeur privé (singleton)
	 */
	private function __construct() {
		// Initialiser le logger WooCommerce si disponible
		if ( function_exists( 'wc_get_logger' ) ) {
			$this->wc_logger = wc_get_logger();
		}
	}
	
	/**
	 * Obtenir l'instance unique
	 * 
	 * @return Logger
	 */
	public static function get_instance(): Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Vérifie si un niveau de log doit être enregistré
	 * 
	 * @param string $level Niveau du log
	 * @return bool True si le log doit être enregistré
	 */
	private function should_log( string $level ): bool {
		$level_priority = self::LEVELS[ $level ] ?? 0;
		$min_priority = self::LEVELS[ $this->min_level ] ?? 0;
		
		return $level_priority >= $min_priority;
	}
	
	/**
	 * Log brut vers WooCommerce et error_log
	 * 
	 * @param string $level   Niveau du log
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	private function log_raw( string $level, string $message, array $context = [] ): void {
		if ( ! $this->should_log( $level ) ) {
			return;
		}
		
		// Préfixer avec WCQF
		$prefixed_message = '[WCQF] ' . $message;
		
		// Si contexte, ajouter en JSON
		if ( ! empty( $context ) ) {
			$prefixed_message .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		
		// Log vers WooCommerce si disponible
		if ( $this->wc_logger ) {
			$source = 'wc-qualiopi-formation';
			
			switch ( $level ) {
				case 'DEBUG':
					$this->wc_logger->debug( $prefixed_message, [ 'source' => $source ] );
					break;
				case 'INFO':
					$this->wc_logger->info( $prefixed_message, [ 'source' => $source ] );
					break;
				case 'WARNING':
					$this->wc_logger->warning( $prefixed_message, [ 'source' => $source ] );
					break;
				case 'ERROR':
					$this->wc_logger->error( $prefixed_message, [ 'source' => $source ] );
					break;
				case 'CRITICAL':
					$this->wc_logger->critical( $prefixed_message, [ 'source' => $source ] );
					break;
			}
		}
		
		// Toujours logger dans error_log pour les niveaux WARNING et supérieurs
		if ( in_array( $level, [ 'WARNING', 'ERROR', 'CRITICAL' ], true ) ) {
			error_log( $prefixed_message );
		}
	}
	
	/**
	 * Log DEBUG
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log_raw( 'DEBUG', $message, $context );
	}
	
	/**
	 * Log INFO
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log_raw( 'INFO', $message, $context );
	}
	
	/**
	 * Log WARNING
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log_raw( 'WARNING', $message, $context );
	}
	
	/**
	 * Log ERROR
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log_raw( 'ERROR', $message, $context );
	}
	
	/**
	 * Log CRITICAL
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function critical( string $message, array $context = [] ): void {
		$this->log_raw( 'CRITICAL', $message, $context );
	}
	
	/**
	 * Alias de debug() pour compatibilité avec l'ancien plugin
	 * 
	 * @param string $message Message du log
	 * @param array  $context Contexte additionnel
	 * @return void
	 */
	public function trace( string $message, array $context = [] ): void {
		$this->debug( $message, $context );
	}
	
	/**
	 * Définit le niveau minimum de log
	 * 
	 * @param string $level Niveau minimum (DEBUG, INFO, WARNING, ERROR, CRITICAL)
	 * @return void
	 */
	public function set_min_level( string $level ): void {
		if ( isset( self::LEVELS[ $level ] ) ) {
			$this->min_level = $level;
		}
	}
}

