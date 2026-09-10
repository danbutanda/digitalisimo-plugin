<?php
/**
 * Plugin Name: Digitalisimo Hosting
 * Description: Dominios, Namecheap, formularios Elementor y flujo de hosting para Digitalisimo.
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_HOSTING_VERSION', '1.0.3' );
define( 'DIGITALISIMO_HOSTING_FILE', __FILE__ );
define( 'DIGITALISIMO_HOSTING_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/class-digitalisimo-core.php';
require_once __DIR__ . '/includes/class-hosting.php';
require_once __DIR__ . '/includes/class-hosting-updater.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'hosting', 'Hosting', array( 'Digitalisimo_Hosting', 'page' ), array( 'Digitalisimo_Hosting', 'network_page' ) ); Digitalisimo_Hosting_Updater::init(); Digitalisimo_Hosting::init(); } );
