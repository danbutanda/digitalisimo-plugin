<?php
/** Desactiva y borra el plugin Elementor, como haría el administrador (también borra sus traducciones). */
// Los errores se muestran tal cual: el gestor de errores fatales de WordPress los ocultaría.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
// Si el paso falla, su último error queda en digi-core para poder leerlo.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-core/error-remove-elementor' . getenv( 'DIGI_CORE_PHASE' ) . '.json', json_encode( $error ) );
	}
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
deactivate_plugins( 'elementor/elementor.php', true, true );
$deleted = delete_plugins( array( 'elementor/elementor.php' ) );
file_put_contents( '/wordpress/wp-content/digi-core/removed.json', wp_json_encode( array(
	'deleted'     => true === $deleted,
	'still_there' => file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ),
	'global_lang' => array_map( 'basename', (array) glob( WP_LANG_DIR . '/plugins/elementor-*' ) ),
) ) );
