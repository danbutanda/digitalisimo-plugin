<?php
/**
 * Preparación del ensayo de WooCommerce en el MCP (paso runPHP del blueprint de woo-mcp.sh). Instala la sonda
 * (mu-plugin), activa DIGITALÍSIMO Elements (en toda la red si es Multisite) y, en una red, crea el sitio 2 (con
 * tienda) y el sitio 3 (sin WooCommerce). WooCommerce NO se activa aquí: el ensayo comprueba primero que sin él el
 * servidor MCP no ofrece herramientas de tienda y lo activa después, sitio a sitio, con `?digi_wm=activate-woo`.
 * Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$network = is_multisite();
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-wm-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		throw new RuntimeException( 'WooCommerce no está montado en wp-content/plugins/woocommerce.' );
	}
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php', '', $network );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	$sites = array( 1 );
	if ( $network ) {
		$admin = get_user_by( 'login', 'admin' );
		$row   = get_network();
		foreach ( array( 'dos' => 'Dos', 'tres' => 'Tres' ) as $path => $title ) {
			$site = wp_insert_site( array( 'domain' => $row->domain, 'path' => $row->path . $path . '/', 'title' => $title, 'user_id' => $admin->ID ) );
			if ( is_wp_error( $site ) ) {
				throw new RuntimeException( 'No se creó el sitio ' . $path . ': ' . $site->get_error_message() );
			}
			switch_to_blog( $site );
			update_option( 'elementor_mcp_enabled', true );
			restore_current_blog();
			$sites[] = (int) $site;
		}
	}
	file_put_contents(
		$dir . '/setup.json',
		wp_json_encode(
			array(
				'network'  => $network,
				'sites'    => $sites,
				'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION,
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
