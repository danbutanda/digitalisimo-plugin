<?php
/**
 * Plugin Name: DIGITALÍSIMO Tools
 * Description: Herramientas internas de administración y automatización para redes WordPress.
 * Version: 1.0.37
 * Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-tools
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: digitalisimo-tools
 * Network: true
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_TOOLS_VERSION', '1.0.37' );
define( 'DIGITALISIMO_TOOLS_FILE', __FILE__ );
define( 'DIGITALISIMO_TOOLS_DIR', __DIR__ . '/' );
require_once DIGITALISIMO_TOOLS_DIR . 'digitalisimo-ui/digitalisimo-ui.php';

foreach ( array( 'includes/class-plugin.php', 'includes/class-updater.php', 'includes/class-plugin-catalog.php', 'modules/site-options/class-hide-login.php', 'modules/site-options/class-site-options.php', 'modules/php-snippets/class-store.php', 'modules/php-snippets/class-stream.php', 'modules/php-snippets/class-guard.php', 'modules/php-snippets/class-runner.php', 'modules/php-snippets/class-tester.php', 'modules/php-snippets/class-import.php', 'modules/php-snippets/class-admin.php', 'modules/class-media-svg.php', 'modules/class-media-webp.php', 'modules/elementor-network-templates/class-registry.php', 'modules/elementor-network-templates/class-id-mapper.php', 'modules/elementor-network-templates/class-dependency-resolver.php', 'modules/elementor-network-templates/class-elementor-adapter.php', 'modules/elementor-network-templates/class-lock.php', 'modules/elementor-network-templates/class-logger.php', 'modules/elementor-network-templates/class-sync.php', 'modules/elementor-network-templates/class-network-admin.php', 'modules/elementor-network-templates/class-module.php', 'includes/class-theme-updates.php', 'includes/class-theme-catalog.php' ) as $file ) require_once DIGITALISIMO_TOOLS_DIR . $file;
// Catálogo Digitalisimo → Plugins cuando SEO no está activo (si lo está, manda el suyo).
\Digitalisimo\Tools\Plugin_Catalog::init();
// La ruta privada de acceso debe decidirse antes de `plugins_loaded`.
Digitalisimo\Tools\Site_Options::boot();
// Código PHP: los fragmentos corren en after_setup_theme; el enlace de rescate, en plugins_loaded:2.
Digitalisimo\Tools\Php_Snippets\Runner::boot();
register_activation_hook( __FILE__, array( 'Digitalisimo\\Tools\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'Digitalisimo\\Tools\\Plugin', 'boot' ) );
