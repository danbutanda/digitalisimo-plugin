<?php
/**
 * Plugin Name: DIGITALÍSIMO Migración
 * Description: Migra un sitio o una red a DIGITALÍSIMO con un solo botón: respalda todo con DIGITALÍSIMO Backups, instala y activa los plugins DIGITALÍSIMO que reemplazan a los del sitio (Elements en lugar de Elementor, Elementor Pro, PRO Elements, Element Pack y Prime Slider; SEO en lugar de Rank Math o Yoast; Backups en lugar de UpdraftPlus o Backuply), convierte el contenido e importa sus datos, cambia al tema DIGITALÍSIMO donde es seguro y, si todo salió bien, borra lo reemplazado. Funciona instalado solo. Al terminar la migración se desinstala.
 * Version: 1.0.36
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-migration
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: digitalisimo-migration
 * Network: true
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.36' );
define( 'DIGITALISIMO_MIGRATION_FILE', __FILE__ );
define( 'DIGITALISIMO_MIGRATION_DIR', __DIR__ . '/' );

require_once DIGITALISIMO_MIGRATION_DIR . 'digitalisimo-ui/digitalisimo-ui.php';
require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-updater.php';
require_once DIGITALISIMO_MIGRATION_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', array( 'Digitalisimo\\Migration\\Plugin', 'boot' ), 20 );
