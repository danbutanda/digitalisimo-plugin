<?php
/** Un destino configurable dentro de la raíz web nunca debe aceptarse. */
$root = sys_get_temp_dir() . '/digitalisimo-storage-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/wp-content/plugins', 0700, true );
define( 'ABSPATH', $root . '/' );
define( 'WP_CONTENT_DIR', $root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/public-backups' );
$_SERVER['DOCUMENT_ROOT'] = $root;
function is_multisite() { return false; }
function get_option( $key, $default = false ) { return $default; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
$method = new ReflectionMethod( 'Digitalisimo_Backups', 'local_directory' );
if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
try {
	$method->invoke( null );
	throw new RuntimeException( 'Se aceptó una carpeta pública para almacenar respaldos.' );
} catch ( ReflectionException $e ) {
	throw $e;
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'dentro de una ruta pública' ) ) throw $e;
}
rmdir( $root . '/public-backups' );
rmdir( $root . '/wp-content/plugins' );
rmdir( $root . '/wp-content' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: se rechazó el almacenamiento bajo la raíz web.\n";
