<?php
/**
 * Preparación del ensayo de la venta automática de DIGITALÍSIMO Hosting (paso runPHP del blueprint de
 * hosting-provision.sh): instala la sonda (mu-plugin) y activa WooCommerce y DIGITALÍSIMO Hosting. La tienda la arma la
 * sonda (`?digi_hp=store`) en una petición nueva, con WooCommerce ya cargado. Deja /digi-probe/setup.json; un fallo
 * queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-hp-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-hosting/digitalisimo-hosting.php' ) as $plugin ) {
		$r = activate_plugin( $plugin );
		if ( is_wp_error( $r ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $r->get_error_message() );
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
