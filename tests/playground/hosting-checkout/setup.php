<?php
/**
 * Preparación del ensayo del dominio antes de pagar y de Hosting en el MCP (paso runPHP del blueprint de
 * hosting-checkout.sh): instala la sonda (mu-plugin) y activa WooCommerce, DIGITALÍSIMO Hosting y DIGITALÍSIMO Elements
 * con el MCP del sitio y el editor atómico (e_atomic_elements y el formulario atómico). La tienda la arma la sonda
 * (`?digi_hc=store`). Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-hc-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-elements/pro-elements.php' ) as $plugin ) {
		if ( is_plugin_active( $plugin ) ) continue;
		$r = activate_plugin( $plugin );
		if ( is_wp_error( $r ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $r->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_experiment-e_atomic_elements', 'active' );
	update_option( 'elementor_experiment-e_pro_atomic_form', 'active' );
	update_option( 'permalink_structure', '' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
