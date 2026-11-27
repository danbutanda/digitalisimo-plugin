<?php
/** El escritorio de un administrador: qué ve la salud de plugins, el aviso, «Salud del sitio» y la función del MCP. */
define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
$H = 'Digitalisimo\\Tools\\Plugin_Health';
$out = array( 'loaded' => class_exists( $H ) );
if ( $out['loaded'] ) {
	$out['check'] = $H::check();
	ob_start(); $H::notice(); $out['notice'] = ob_get_clean();
	$out['health'] = $H::health()['status'];
	$out['ability'] = null;
	if ( function_exists( 'wp_get_ability' ) ) {
		$ability = wp_get_ability( $H::ABILITY );
		$out['ability'] = $ability ? $ability->execute( array() ) : 'sin registrar';
	}
	$out['option'] = get_option( $H::OPTION );
}
file_put_contents( '/digi-ph/check.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
