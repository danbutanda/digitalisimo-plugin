<?php
/**
 * Sonda del ensayo (GET /wp-content/digi-web/probe.php?token=…&do=state|drop-theme): permisos, drop-ins, caché, .htaccess,
 * tema y plugins activos del sitio, o borrar el tema del origen (para restaurar después sólo la base de datos). Lee sin
 * cargar WordPress lo que es de archivos, y con WordPress lo que es de la base de datos.
 */
$digi_token = trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) );
if ( '' === $digi_token || ! hash_equals( $digi_token, (string) ( $_GET['token'] ?? '' ) ) ) { http_response_code( 403 ); exit; }
$root = '/wordpress/';
$mode = function( $path ) { clearstatcache( true, $path ); return file_exists( $path ) ? sprintf( '%04o', fileperms( $path ) & 07777 ) : null; };
$remove = function( $path ) use ( &$remove ) { if ( is_dir( $path ) && ! is_link( $path ) ) { foreach ( scandir( $path ) as $n ) if ( '.' !== $n && '..' !== $n ) $remove( $path . '/' . $n ); rmdir( $path ); } elseif ( file_exists( $path ) ) unlink( $path ); };
header( 'Content-Type: application/json; charset=utf-8' );
if ( 'drop-theme' === ( $_GET['do'] ?? '' ) ) {
	$remove( $root . 'wp-content/themes/tema-origen' );
	echo json_encode( array( 'dropped' => ! file_exists( $root . 'wp-content/themes/tema-origen' ) ) );
	exit;
}
$files = array();
$list = function( $dir ) use ( &$list, &$files, $root ) { foreach ( is_dir( $dir ) ? scandir( $dir ) : array() as $n ) { if ( '.' === $n || '..' === $n ) continue; $p = $dir . '/' . $n; if ( is_dir( $p ) ) $list( $p ); else $files[] = substr( $p, strlen( $root ) ); } };
$list( $root . 'wp-content/cache' );
$list( $root . 'wp-content/et-cache' );
$out = array(
	'modes' => array(),
	'object_cache' => is_file( $root . 'wp-content/object-cache.php' ) ? (string) file_get_contents( $root . 'wp-content/object-cache.php' ) : null,
	'advanced_cache' => is_file( $root . 'wp-content/advanced-cache.php' ) ? (string) file_get_contents( $root . 'wp-content/advanced-cache.php' ) : null,
	'cache_files' => $files,
	'htaccess' => is_file( $root . '.htaccess' ) ? (string) file_get_contents( $root . '.htaccess' ) : null,
	'theme_dir' => is_dir( $root . 'wp-content/themes/tema-origen' ),
);
foreach ( array( 'wp-content/plugins/digi-demo-assets', 'wp-content/plugins/digi-demo-assets/assets', 'wp-content/plugins/digi-demo-assets/assets/demo.css', 'wp-content/plugins/digi-demo-assets/assets/demo.js', 'wp-content/themes/tema-origen', 'wp-content/themes/tema-origen/style.css' ) as $path ) $out['modes'][ $path ] = $mode( $root . $path );
foreach ( glob( $root . 'wp-content/uploads/*/*/foto-origen.png' ) ?: array() as $path ) { $out['modes'][ substr( $path, strlen( $root ) ) ] = $mode( $path ); $out['modes'][ substr( dirname( $path ), strlen( $root ) ) ] = $mode( dirname( $path ) ); }
// Lo de la base de datos, con WordPress (si un drop-in del origen rompe el sitio, esto muere y el ensayo lo ve).
require $root . 'wp-load.php';
$out['template'] = get_option( 'template' );
$out['stylesheet'] = get_option( 'stylesheet' );
$out['active_plugins'] = get_option( 'active_plugins' );
$out['permalink_structure'] = get_option( 'permalink_structure' );
echo json_encode( $out );
