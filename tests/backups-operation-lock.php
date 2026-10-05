<?php
/** Dos operaciones de la misma instalación no pueden solaparse. */
$root = sys_get_temp_dir() . '/digitalisimo-operation-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public/wp-content/plugins', 0700, true );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
function is_multisite() { return false; }
function get_option( $key, $default = false ) { return $default; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
$acquire = new ReflectionMethod( 'Digitalisimo_Backups', 'acquire_operation_lock' );
$release = new ReflectionMethod( 'Digitalisimo_Backups', 'release_operation_lock' );
if ( PHP_VERSION_ID < 80100 ) { $acquire->setAccessible( true ); $release->setAccessible( true ); }
$first = $acquire->invoke( null );
try {
	try {
		$second = $acquire->invoke( null );
		$release->invoke( null, $second );
		throw new RuntimeException( 'Se permitió una segunda operación simultánea.' );
	} catch ( Exception $e ) {
		if ( false === strpos( $e->getMessage(), 'Ya hay un respaldo' ) ) throw $e;
	}
} finally { $release->invoke( null, $first ); }
$third = $acquire->invoke( null );
$release->invoke( null, $third );
unlink( $root . '/private/.operation.lock' );
rmdir( $root . '/private' );
rmdir( $root . '/public/wp-content/plugins' );
rmdir( $root . '/public/wp-content' );
rmdir( $root . '/public' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: bloqueo exclusivo y liberación verificados.\n";
