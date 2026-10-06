<?php
/** El respaldo manual avisa antes de exportar si falta un archivo indispensable. */
$root = sys_get_temp_dir() . '/digitalisimo-manual-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public/wp-content/plugins', 0700, true );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
$_POST = array( 'nonce' => 'ok', 'context' => 'site', 'destination' => 'local' );
$GLOBALS['options'] = array();

function is_multisite() { return false; }
function get_current_user_id() { return 17; }
function get_current_blog_id() { return 1; }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function check_ajax_referer( $action, $name ) { if ( 'digitalisimo_backups_manual' !== $action || 'ok' !== $_POST[ $name ] ) throw new RuntimeException( 'Nonce inválido.' ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_generate_password() { return str_repeat( 'x', 40 ); }
function wp_send_json_error( $data, $status = 200 ) { throw new RuntimeException( 'ERROR:' . $status . ':' . $data['message'] ); }
function wp_send_json_success( $data ) { throw new RuntimeException( 'OK:' . json_encode( $data ) ); }

require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'Se inició un respaldo sin wp-config.php.' );
} catch ( RuntimeException $error ) {
	if ( false === strpos( $error->getMessage(), 'No se puede leer wp-config.php' ) ) throw $error;
}
if ( get_option( 'digitalisimo_backups_manual_job_17' ) ) throw new RuntimeException( 'Se creó un trabajo aunque falló la validación temprana.' );
file_put_contents( $root . '/wp-config.php', '<?php' );
try {
	Digitalisimo_Backups::manual_start();
	throw new RuntimeException( 'No se creó el trabajo después de corregir el requisito.' );
} catch ( RuntimeException $response ) {
	if ( 0 !== strpos( $response->getMessage(), 'OK:' ) ) throw $response;
}
$job = get_option( 'digitalisimo_backups_manual_job_17' );
if ( 'queued' !== ( $job['status'] ?? '' ) || 1 !== ( $job['blog_id'] ?? 0 ) || empty( $job['token'] ) ) throw new RuntimeException( 'El trabajo no conserva estado, token y sitio.' );
$_POST['token'] = $job['token'];
try {
	Digitalisimo_Backups::manual_status();
	throw new RuntimeException( 'No se devolvió el avance del trabajo.' );
} catch ( RuntimeException $response ) {
	if ( false === strpos( $response->getMessage(), '"status":"queued"' ) ) throw $response;
}
mkdir( ABSPATH . 'wp-admin' );
mkdir( ABSPATH . 'wp-includes' );
foreach ( array( 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $required ) file_put_contents( ABSPATH . $required, '<?php' );
$archive = new ZipArchive();
$probe = $root . '/private/config-probe.zip';
$archive->open( $probe, ZipArchive::CREATE );
$entries = array();
$add_files = new ReflectionMethod( 'Digitalisimo_Backups', 'add_full_files' );
if ( PHP_VERSION_ID < 80100 ) $add_files->setAccessible( true );
$add_files->invokeArgs( null, array( $archive, $root . '/private', &$entries ) );
$archive->close();
$archive->open( $probe );
if ( '<?php' !== $archive->getFromName( 'wordpress/wp-config.php' ) || ! isset( $entries['wordpress/wp-config.php'] ) ) throw new RuntimeException( 'No se incluyó wp-config.php ubicado sobre ABSPATH.' );
$archive->close();
unlink( $probe );
foreach ( array( 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $required ) unlink( ABSPATH . $required );
rmdir( ABSPATH . 'wp-admin' );
rmdir( ABSPATH . 'wp-includes' );
unlink( $root . '/wp-config.php' );
rmdir( $root . '/private' );
rmdir( $root . '/public/wp-content/plugins' );
rmdir( $root . '/public/wp-content' );
rmdir( $root . '/public' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: requisitos tempranos y estado de avance verificados.\n";
