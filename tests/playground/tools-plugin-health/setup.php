<?php
/** Plugins de prueba: todos activos; después se borra el archivo de digi-borrado y se instala el mu-plugin que filtra. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugins = array(
	'digi-bien'     => '',
	'digi-filtrado' => '',
	'digi-tarde'    => '',
	'digi-borrado'  => '',
	'digi-marca'    => '', // Debería registrar el tipo digi_cosa y no lo hace.
	'digi-extra'    => '', // Para activar y desactivar en el paso de la purga.
);
foreach ( $plugins as $slug => $code ) {
	@mkdir( WP_PLUGIN_DIR . "/$slug", 0777, true );
	file_put_contents( WP_PLUGIN_DIR . "/$slug/$slug.php", "<?php\n/**\n * Plugin Name: Prueba $slug\n */\n$code\n" );
}
wp_cache_delete( 'plugins', 'plugins' );
foreach ( array_diff( array_keys( $plugins ), array( 'digi-extra' ) ) as $slug ) {
	$r = activate_plugin( "$slug/$slug.php" );
	if ( is_wp_error( $r ) ) { echo 'FALLA activar ' . $slug . ': ' . $r->get_error_message(); exit( 1 ); }
}
unlink( WP_PLUGIN_DIR . '/digi-borrado/digi-borrado.php' );
@mkdir( WPMU_PLUGIN_DIR, 0777, true );
copy( '/digi-ph/mu-plugin.txt', WPMU_PLUGIN_DIR . '/digi-ph.php' );
