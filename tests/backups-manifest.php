<?php
/** El manifiesto v3 prueba que una copia completa contiene exactamente los datos inventariados. */
$root = sys_get_temp_dir() . '/digitalisimo-full-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/wp-admin', 0700, true );
mkdir( $root . '/wp-includes', 0700, true );
mkdir( $root . '/wp-content/plugins', 0700, true );
define( 'ABSPATH', $root . '/' );
define( 'WP_CONTENT_DIR', $root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/wp-content/plugins' );
define( 'DB_NAME', 'wordpress_test' );
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.40' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) file_put_contents( $root . '/' . $name, 'Contenido de ' . $name );

class Digitalisimo_Full_Test_DB {
	public $base_prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public $fail_import = false;
	public $fail_next_import = false;
	public function get_col( $query ) { return array( 'wp_options' ); }
	public function get_row( $query, $format ) { return array( 'wp_options', 'CREATE TABLE `wp_options` (`id` bigint)' ); }
	public function get_results( $query, $format ) { $this->last_error = ''; return array( array( 'id' => 1 ) ); }
	public function db_version() { return '8.0'; }
	public function query( $sql ) { $this->queries[] = $sql; if ( $this->fail_import || $this->fail_next_import ) { $this->fail_next_import = false; $this->last_error = 'Fallo simulado'; return false; } $this->last_error = ''; return 1; }
}
$GLOBALS['wpdb'] = new Digitalisimo_Full_Test_DB();
$GLOBALS['backup_options'] = array();
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0700, true ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function home_url() { return 'https://example.test/'; }
function site_url() { return 'https://example.test/'; }
function get_bloginfo( $key ) { return '6.8'; }
function is_multisite() { return false; }
function wp_generate_uuid4() { static $n = 0; return sprintf( '%08x-bbbb-4ccc-8ddd-eeeeeeeeeeee', ++$n ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_option( $key, $default = false ) { return $GLOBALS['backup_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['backup_options'][ $key ] = $value; return true; }
function get_current_blog_id() { return 1; }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_basename( $path ) { return basename( $path ); }
function sanitize_title( $value ) { return preg_replace( '/[^a-z0-9-]/', '-', strtolower( $value ) ); }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';

$create = new ReflectionMethod( 'Digitalisimo_Backups', 'create_network' );
$verify = new ReflectionMethod( 'Digitalisimo_Backups', 'verify_zip_contents' );
if ( PHP_VERSION_ID < 80100 ) { $create->setAccessible( true ); $verify->setAccessible( true ); }
$file = $create->invoke( null );
$zip = new ZipArchive();
if ( true !== $zip->open( $file ) ) throw new RuntimeException( 'No se pudo abrir el ZIP completo.' );
$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
if ( 'digitalisimo-backup/v3' !== $manifest['format'] || 'installation' !== $manifest['scope'] || 6 !== $manifest['file_count'] || ! isset( $manifest['entries']['database.sql']['sha256'] ) ) throw new RuntimeException( 'El manifiesto no inventaría todos los archivos de prueba.' );
$verify->invoke( null, $zip );
$zip->close();

$restore = new ReflectionMethod( 'Digitalisimo_Backups', 'restore_digitalisimo_zip' );
if ( PHP_VERSION_ID < 80100 ) $restore->setAccessible( true );
$restore->invoke( null, $file );
$history = $GLOBALS['backup_options'][ Digitalisimo_Backups::HISTORY ] ?? array();
if ( 1 !== count( $history ) || empty( $history[0]['locked'] ) || 'pre-restore' !== $history[0]['policy'] || empty( $history[0]['sha256'] ) || ! is_file( dirname( $file ) . '/' . $history[0]['file'] ) ) throw new RuntimeException( 'La restauración no dejó un respaldo de seguridad bloqueado.' );
if ( ! $GLOBALS['wpdb']->queries ) throw new RuntimeException( 'La restauración válida no importó SQL.' );
$GLOBALS['wpdb']->fail_next_import = true;
try {
	$restore->invoke( null, $file );
	throw new RuntimeException( 'Se aceptó una importación SQL fallida.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'Se recuperaron la base de datos' ) ) throw $e;
}
$history = $GLOBALS['backup_options'][ Digitalisimo_Backups::HISTORY ] ?? array();
if ( 2 !== count( $history ) || empty( $history[0]['locked'] ) ) throw new RuntimeException( 'La reversión no conservó el respaldo previo.' );
$GLOBALS['wpdb']->fail_import = true;
try {
	$restore->invoke( null, $file );
	throw new RuntimeException( 'Se aceptó una importación SQL fallida.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'reversión también falló' ) ) throw $e;
}
$GLOBALS['wpdb']->fail_import = false;
$history = $GLOBALS['backup_options'][ Digitalisimo_Backups::HISTORY ] ?? array();
if ( 3 !== count( $history ) || empty( $history[0]['locked'] ) || ! is_file( dirname( $file ) . '/' . $history[0]['file'] ) ) throw new RuntimeException( 'El fallo SQL perdió el respaldo previo.' );

$incomplete = dirname( $file ) . '/incomplete-v3.zip';
copy( $file, $incomplete );
$zip->open( $incomplete );
$zip->deleteName( 'wordpress/wp-admin/index.php' );
unset( $manifest['entries']['wordpress/wp-admin/index.php'] );
$manifest['file_count']--;
$manifest['uncompressed_size'] -= strlen( 'Contenido de wp-admin/index.php' );
$zip->addFromString( 'manifest.json', wp_json_encode( $manifest ) );
$zip->close();
$zip->open( $incomplete );
try {
	$verify->invoke( null, $zip );
	throw new RuntimeException( 'Se aceptó un respaldo sin wp-admin/index.php y con manifiesto ajustado.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'archivo obligatorio' ) ) throw $e;
} finally {
	$zip->close();
}

$outside = sys_get_temp_dir() . '/digitalisimo-restore-outside-' . bin2hex( random_bytes( 4 ) );
mkdir( $outside, 0700 );
symlink( $outside, $root . '/wp-content/linked' );
$linked = dirname( $file ) . '/linked.zip';
$zip->open( $linked, ZipArchive::CREATE );
$zip->addFromString( 'wordpress/wp-content/linked/escape.php', '<?php echo 1;' );
$zip->close();
$zip->open( $linked );
$extract = new ReflectionMethod( 'Digitalisimo_Backups', 'extract_digitalisimo_files' );
if ( PHP_VERSION_ID < 80100 ) $extract->setAccessible( true );
try {
	$extract->invoke( null, $zip );
	throw new RuntimeException( 'La restauración siguió un enlace simbólico fuera de WordPress.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'enlace simbólico' ) ) throw $e;
} finally {
	$zip->close();
}
if ( file_exists( $outside . '/escape.php' ) ) throw new RuntimeException( 'La restauración escribió fuera de WordPress.' );
unlink( $root . '/wp-content/linked' );
rmdir( $outside );

$zip->open( $file );
$zip->addFromString( 'wordpress/wp-load.php', str_repeat( 'X', strlen( 'Contenido de wp-load.php' ) ) );
$zip->close();
$zip->open( $file );
try {
	$verify->invoke( null, $zip );
	throw new RuntimeException( 'Se aceptó una entrada alterada con CRC actualizado.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'SHA-256' ) ) throw $e;
} finally {
	$zip->close();
}

unlink( $file );
unlink( $incomplete );
unlink( $linked );
foreach ( $history as $record ) unlink( dirname( $file ) . '/' . $record['file'] );
unlink( dirname( $file ) . '/index.php' );
unlink( dirname( $file ) . '/.htaccess' );
rmdir( dirname( $file ) );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) unlink( $root . '/' . $name );
rmdir( $root . '/wp-admin' );
rmdir( $root . '/wp-includes' );
rmdir( $root . '/wp-content/plugins' );
rmdir( $root . '/wp-content' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: manifiesto, SHA-256 y respaldo de seguridad verificados.\n";
