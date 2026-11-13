<?php
/**
 * Preparación del ensayo del CSS tras las escrituras del MCP (paso runPHP del blueprint de elements-mcp-css.sh).
 * Instala la sonda (mu-plugin), activa DIGITALÍSIMO Elements con el editor atómico (V4) y el MCP del sitio, y deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-mc-probe.php' ) ) {
		throw new RuntimeException( 'No se copió la sonda.' );
	}
	$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
	if ( is_wp_error( $activated ) ) {
		throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	update_option( 'elementor_mcp_enabled', true );
	// Editor atómico (V4), como un sitio nuevo de 4.x; y enlaces permanentes simples (?page_id=).
	update_option( 'elementor_experiment-e_atomic_elements', 'active' );
	update_option( 'permalink_structure', '' );
	file_put_contents(
		$dir . '/setup.json',
		wp_json_encode( array( 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) )
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
