<?php
/**
 * Preparación del ensayo de la tanda B de Elements (paso runPHP del blueprint de elements-tanda-b.sh): sonda
 * (mu-plugin), DIGITALÍSIMO Elements, DIGITALÍSIMO SEO y WooCommerce activos, el tema DIGITALÍSIMO, el MCP del sitio y
 * los elementos atómicos encendidos. El contenido (producto, cabecera y pie globales, páginas) lo crea la sonda en una
 * petición normal (`?digi_tb=seed`), con todo cargado. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-tb-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) as $plugin ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			throw new RuntimeException( 'Falta montado: ' . $plugin );
		}
		$activated = activate_plugin( $plugin );
		if ( is_wp_error( $activated ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
		}
	}
	if ( ! file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		throw new RuntimeException( 'Falta el tema digitalisimo-theme montado.' );
	}
	switch_theme( 'digitalisimo-theme' );
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_experiment-e_atomic_elements', 'active' );
	update_option( 'elementor_onboarded', true );
	update_option( 'woocommerce_coming_soon', 'no' );
	update_option( 'permalink_structure', '' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
