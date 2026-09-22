<?php
/**
 * Plugin Name: Digitalisimo Ecommerce
 * Description: Módulo de dominios, Namecheap, hosting y checkout para Digitalisimo.
 * Version: 1.0.16
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_ECOMMERCE_VERSION', '1.0.16' ); define( 'DIGITALISIMO_ECOMMERCE_FILE', __FILE__ ); define( 'DIGITALISIMO_ECOMMERCE_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/class-digitalisimo-core.php'; require_once __DIR__ . '/includes/class-module-settings.php'; require_once __DIR__ . '/includes/class-ecommerce-admin.php'; require_once __DIR__ . '/includes/class-digitalisimo-updater.php'; require_once __DIR__ . '/includes/class-woocommerce.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'ecommerce', 'Ecommerce', array( 'Digitalisimo_Ecommerce_Admin', 'page' ), array( 'Digitalisimo_Ecommerce_Admin', 'network_page' ) ); Digitalisimo_Updater::register( DIGITALISIMO_ECOMMERCE_FILE, DIGITALISIMO_ECOMMERCE_VERSION, 'digitalisimo-ecommerce' ); Digitalisimo_Integrations_WooCommerce::init(); } );
register_activation_hook( __FILE__, function() { add_option( 'digitalisimo_integrations_options', array( 'enable_woocommerce' => 1 ) ); } );
