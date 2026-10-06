<?php
/**
 * Plugin Name: Digitalisimo Backups
 * Description: Respaldos completos de WordPress hacia almacenamiento local, SFTP, Google Drive u OneDrive.
 * Version: 1.0.49
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-backups
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.49' );
define( 'DIGITALISIMO_BACKUPS_FILE', __FILE__ );
require_once __DIR__ . '/includes/class-digitalisimo-core.php';
require_once __DIR__ . '/includes/class-digitalisimo-updater.php';
require_once __DIR__ . '/includes/class-backups.php';
add_action( 'plugins_loaded', function() {
	Digitalisimo_Backups_Core::boot();
	Digitalisimo_Backups_Updater::register();
	Digitalisimo_Backups::init();
} );
