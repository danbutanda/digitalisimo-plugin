<?php
/** Desactiva el plugin Elementor en la red sin borrarlo: el estado habitual tras pasar al núcleo incluido. */
// Los errores se muestran tal cual: el gestor de errores fatales de WordPress los ocultaría.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
// Si el paso falla, su último error queda en digi-core para poder leerlo.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-core/error-deactivate-elementor.json', json_encode( $error ) );
	}
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'elementor/elementor.php', true, true );
