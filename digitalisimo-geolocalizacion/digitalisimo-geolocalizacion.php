<?php
/**
 * Plugin Name: Digitalisimo Geolocalización
 * Description: Módulo IPinfo y ubicación para Digitalisimo.
 * Version: 1.0.12
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_GEOLOCATION_VERSION', '1.0.12' ); define( 'DIGITALISIMO_GEOLOCATION_FILE', __FILE__ ); define( 'DIGITALISIMO_GEOLOCATION_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/class-digitalisimo-core.php'; require_once __DIR__ . '/includes/class-module-settings.php'; require_once __DIR__ . '/includes/class-geolocation-admin.php'; require_once __DIR__ . '/includes/class-digitalisimo-updater.php'; require_once __DIR__ . '/includes/class-ipinfo.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'geolocalizacion', 'Geolocalización', array( 'Digitalisimo_Geolocation_Admin', 'page' ), array( 'Digitalisimo_Geolocation_Admin', 'network_page' ) ); Digitalisimo_Updater::register( DIGITALISIMO_GEOLOCATION_FILE, DIGITALISIMO_GEOLOCATION_VERSION, 'digitalisimo-geolocalizacion' ); Digitalisimo_Integrations_IPInfo::init(); } );
