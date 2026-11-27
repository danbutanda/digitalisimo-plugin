<?php
/**
 * Plugin Name: DIGITALÍSIMO Hosting
 * Description: Dominios (Namecheap y Dynadot), formularios de DIGITALÍSIMO Elements y flujo de hosting para Digitalisimo.
 * Version: 1.0.37
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-hosting
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_HOSTING_VERSION', '1.0.37' );
define( 'DIGITALISIMO_HOSTING_FILE', __FILE__ );
define( 'DIGITALISIMO_HOSTING_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/digitalisimo-ui/digitalisimo-ui.php';
require_once __DIR__ . '/includes/class-digitalisimo-notices.php';
require_once __DIR__ . '/includes/class-digitalisimo-core.php';
require_once __DIR__ . '/includes/class-hosting.php';
require_once __DIR__ . '/includes/class-hosting-products.php';
require_once __DIR__ . '/includes/class-hosting-checkout.php';
require_once __DIR__ . '/includes/class-hosting-webuzo.php';
require_once __DIR__ . '/includes/class-hosting-namecheap.php';
// Registradores: la interfaz, qué registrador atiende cada extensión y Dynadot (los carga también el de Namecheap).
require_once __DIR__ . '/includes/class-hosting-registrars.php';
require_once __DIR__ . '/includes/class-hosting-dynadot.php';
require_once __DIR__ . '/includes/class-hosting-pricing.php';
require_once __DIR__ . '/includes/class-hosting-provisioning.php';
require_once __DIR__ . '/includes/class-hosting-services-page.php';
require_once __DIR__ . '/includes/class-hosting-finder.php';
require_once __DIR__ . '/includes/class-digitalisimo-updater.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'hosting', 'Hosting', array( 'Digitalisimo_Hosting', 'page' ), array( 'Digitalisimo_Hosting', 'network_page' ) ); Digitalisimo_Updater::register( DIGITALISIMO_HOSTING_FILE, DIGITALISIMO_HOSTING_VERSION, 'digitalisimo-hosting' ); Digitalisimo_Hosting::init(); } );
