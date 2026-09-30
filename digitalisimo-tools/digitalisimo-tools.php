<?php
/**
 * Plugin Name: DIGITALÍSIMO Tools
 * Description: Herramientas internas de administración y automatización para redes WordPress.
 * Version: 1.0.14
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-tools
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: digitalisimo-tools
 * Network: true
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_TOOLS_VERSION', '1.0.14' );
define( 'DIGITALISIMO_TOOLS_FILE', __FILE__ );
define( 'DIGITALISIMO_TOOLS_DIR', __DIR__ . '/' );

foreach ( array( 'includes/class-plugin.php', 'includes/class-updater.php', 'modules/class-media-webp.php', 'modules/elementor-network-templates/class-registry.php', 'modules/elementor-network-templates/class-id-mapper.php', 'modules/elementor-network-templates/class-dependency-resolver.php', 'modules/elementor-network-templates/class-elementor-adapter.php', 'modules/elementor-network-templates/class-lock.php', 'modules/elementor-network-templates/class-logger.php', 'modules/elementor-network-templates/class-sync.php', 'modules/elementor-network-templates/class-network-admin.php', 'modules/elementor-network-templates/class-module.php' ) as $file ) require_once DIGITALISIMO_TOOLS_DIR . $file;
register_activation_hook( __FILE__, array( 'Digitalisimo\\Tools\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'Digitalisimo\\Tools\\Plugin', 'boot' ) );
