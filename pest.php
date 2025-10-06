<?php
/**
 * Pest Configuration File
 */

// Autoload WordPress
$abspath = getenv('DDEV_DOCROOT') ?: '/var/www/html/web/';
if (!defined('ABSPATH')) {
	define('ABSPATH', rtrim($abspath, '/') . '/');
}

require_once ABSPATH . 'wp-load.php';

// Pest uses
uses()->in('tests');
