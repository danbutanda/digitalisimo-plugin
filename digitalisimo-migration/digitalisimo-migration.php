<?php
/**
 * Plugin Name: DIGITALÍSIMO Migración
 * Description: Mantiene visibles las páginas creadas con Element Pack Pro sin ese plugin y las convierte al formato propio de DIGITALÍSIMO Elements, sitio por sitio y con copia del original. Al terminar la migración se desinstala.
 * Version: 1.0.9
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-migration
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: digitalisimo-elements
 * Text Domain: digitalisimo-migration
 * Network: true
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.9' );
define( 'DIGITALISIMO_MIGRATION_FILE', __FILE__ );
define( 'DIGITALISIMO_MIGRATION_DIR', __DIR__ . '/' );

require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-updater.php';
require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', array( 'Digitalisimo\\Migration\\Plugin', 'boot' ), 20 );
