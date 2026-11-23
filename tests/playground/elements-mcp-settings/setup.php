<?php
/**
 * Preparación del ensayo de los ajustes que escribe el MCP (paso runPHP del blueprint de elements-mcp-settings.sh).
 * Instala la sonda (mu-plugin), activa DIGITALÍSIMO Elements (y WooCommerce si está montado o instalado) con el MCP del
 * sitio, y deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-ms-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	$woo = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
	if ( $woo && ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$activated = activate_plugin( 'woocommerce/woocommerce.php' );
		if ( is_wp_error( $activated ) ) {
			throw new RuntimeException( 'No se activó WooCommerce: ' . $activated->get_error_message() );
		}
	}
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_experiment-e_atomic_elements', 'active' );
	update_option( 'permalink_structure', '' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION, 'woocommerce' => $woo ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
