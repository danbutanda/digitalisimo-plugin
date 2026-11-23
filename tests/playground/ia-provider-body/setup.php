<?php
/**
 * Preparación del ensayo del cuerpo que el Asistente IA manda a cada proveedor (paso runPHP del blueprint de
 * ia-provider-body.sh): activa WooCommerce, DIGITALÍSIMO Elements (con su MCP y el de WooCommerce en sólo lectura),
 * IA Tools y los demás plugins del repositorio, como un sitio de cliente con tienda. Las claves de IA son falsas: la
 * sonda contesta en lugar de los proveedores y nada sale a la red. Deja /digi-pb/ready.json; un fallo, setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-pb';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-pb-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	$active = array();
	$failed = array();
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-backups/digitalisimo-backups.php' ) as $plugin ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			$failed[ $plugin ] = 'no está montado';
			continue;
		}
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			$failed[ $plugin ] = $done->get_error_message();
		} else {
			$active[] = $plugin;
		}
	}
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) as $required ) {
		if ( ! in_array( $required, $active, true ) ) {
			throw new RuntimeException( 'No se activó ' . $required . ': ' . ( $failed[ $required ] ?? '?' ) );
		}
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => 'read', 'own' => array( 'mode' ) ), false );
	// Como plomerito: sólo Anthropic activo; OpenAI y Google se encienden en la sonda sólo para su propio cuerpo.
	update_option(
		'digitalisimo_ai_options',
		array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'sk-ant-pb-FAKEKEY-0123456789abcdefABCDEF', 'url' => '' ) ) ),
		false
	);
	file_put_contents( $dir . '/ready.json', wp_json_encode( array( 'active' => $active, 'failed' => $failed, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
