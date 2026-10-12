<?php
/** Red con el plugin Elementor y DIGITALÍSIMO Elements activos en la red, y un subsitio. */
// Los errores se muestran tal cual: el gestor de errores fatales de WordPress los ocultaría.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
// Si el paso falla, su último error queda en digi-core para poder leerlo.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-core/error-setup' . getenv( 'DIGI_CORE_PHASE' ) . '.json', json_encode( $error ) );
	}
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
// El subsitio existe antes de activar los plugins, como en una red real: la activación en red da su kit a cada sitio.
if ( ! get_site( 2 ) ) {
	wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
}
foreach ( array( 'elementor/elementor.php', 'digitalisimo-elements/pro-elements.php' ) as $plugin ) {
	activate_plugin( $plugin, '', true );
}
update_option( 'WPLANG', 'es_MX' );
update_site_option( 'WPLANG', 'es_MX' );
foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	restore_current_blog();
}
