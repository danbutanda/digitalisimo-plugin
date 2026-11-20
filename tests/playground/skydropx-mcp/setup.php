<?php
/**
 * Preparación del ensayo de Skydropx en el MCP (paso runPHP del blueprint de skydropx-mcp.sh): instala la sonda
 * (mu-plugin) y activa WooCommerce y DIGITALÍSIMO Elements. Skydropx NO se activa aquí: el ensayo comprueba primero que
 * sin él la herramienta no existe y lo activa después con `?digi_sk=activate-sky` (con WooCommerce ya cargado, como lo
 * exige su activación). Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-sk-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	foreach ( array( 'woocommerce/woocommerce.php', 'skydropx/skydropx.php' ) as $plugin ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			throw new RuntimeException( "No está montado $plugin." );
		}
	}
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php' ) as $plugin ) {
		$activated = activate_plugin( $plugin );
		if ( is_wp_error( $activated ) ) {
			throw new RuntimeException( "No se activó $plugin: " . $activated->get_error_message() );
		}
	}
	update_option( 'elementor_mcp_enabled', true );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
