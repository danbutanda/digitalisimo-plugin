<?php
/** Google Drive y OneDrive sólo aceptan una copia descargable e idéntica. */
$root = sys_get_temp_dir() . '/digitalisimo-cloud-test-' . bin2hex( random_bytes( 4 ) );
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
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
$source = $root . '/source.zip';
file_put_contents( $source, 'Copia integral de prueba' );
$GLOBALS['cloud_source'] = $source;
$GLOBALS['cloud_mode'] = 'correct';
function wp_remote_get( $url, $args ) {
	if ( empty( $args['stream'] ) || empty( $args['filename'] ) || false === strpos( $url, '/content' ) && false === strpos( $url, 'alt=media' ) ) throw new RuntimeException( 'La descarga remota no usa streaming o tiene una URL incorrecta.' );
	$contents = file_get_contents( $GLOBALS['cloud_source'] );
	if ( 'corrupt' === $GLOBALS['cloud_mode'] ) $contents[0] = 'X';
	if ( 'short' === $GLOBALS['cloud_mode'] ) $contents = substr( $contents, 0, -1 );
	file_put_contents( $args['filename'], $contents );
	return array( 'response' => array( 'code' => 'http-error' === $GLOBALS['cloud_mode'] ? 500 : 200 ) );
}
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
$verify = new ReflectionMethod( 'Digitalisimo_Backups', 'verify_cloud_copy' );
if ( PHP_VERSION_ID < 80100 ) $verify->setAccessible( true );
foreach ( array( 'google', 'onedrive' ) as $provider ) {
	$GLOBALS['cloud_mode'] = 'correct';
	$verify->invoke( null, $provider, 'remote-id', $source, 'token' );
	foreach ( array( 'corrupt', 'short', 'http-error' ) as $mode ) {
		$GLOBALS['cloud_mode'] = $mode;
		try { $verify->invoke( null, $provider, 'remote-id', $source, 'token' ); throw new RuntimeException( 'Se aceptó una copia remota inválida: ' . $provider . '/' . $mode ); }
		catch ( Exception $e ) { if ( false === strpos( $e->getMessage(), 'copia remota' ) && false === strpos( $e->getMessage(), 'descargar la copia' ) ) throw $e; }
	}
}
if ( glob( $root . '/private/.cloud-check-*' ) ) throw new RuntimeException( 'La verificación dejó archivos temporales.' );
unlink( $source );
rmdir( $root . '/private' );
rmdir( $root . '/public/wp-content/plugins' );
rmdir( $root . '/public/wp-content' );
rmdir( $root . '/public' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: copias Google y OneDrive verificadas por tamaño y SHA-256.\n";
