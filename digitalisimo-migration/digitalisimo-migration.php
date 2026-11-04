<?php
/**
 * Plugin Name: DIGITALÍSIMO Migración
 * Description: Migra un sitio o una red a DIGITALÍSIMO Elements con un solo botón: desactiva Elementor, Elementor Pro, PRO Elements, Element Pack (con su Addon) y, al final, Prime Slider; convierte el contenido de Element Pack y de Prime Slider, sus plantillas, fuentes e iconos, y el de los widgets fusionados, con copia del original, y deja un informe. Mientras tanto mantiene visibles las páginas creadas con Element Pack. Al terminar la migración se desinstala.
 * Version: 1.0.23
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-migration
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: digitalisimo-elements
 * Text Domain: digitalisimo-migration
 * Network: true
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.23' );
define( 'DIGITALISIMO_MIGRATION_FILE', __FILE__ );
define( 'DIGITALISIMO_MIGRATION_DIR', __DIR__ . '/' );

require_once DIGITALISIMO_MIGRATION_DIR . 'digitalisimo-ui/digitalisimo-ui.php';
require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-updater.php';
require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', array( 'Digitalisimo\\Migration\\Plugin', 'boot' ), 20 );
