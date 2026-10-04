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
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.38' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) file_put_contents( $root . '/' . $name, 'Contenido de ' . $name );

class Digitalisimo_Full_Test_DB {
	public $base_prefix = 'wp_';
	public $last_error = '';
	public function get_col( $query ) { return array( 'wp_options' ); }
	public function get_row( $query, $format ) { return array( 'wp_options', 'CREATE TABLE `wp_options` (`id` bigint)' ); }
	public function get_results( $query, $format ) { return array( array( 'id' => 1 ) ); }
	public function db_version() { return '8.0'; }
}
$GLOBALS['wpdb'] = new Digitalisimo_Full_Test_DB();
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0700, true ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function home_url() { return 'https://example.test/'; }
function site_url() { return 'https://example.test/'; }
function get_bloginfo( $key ) { return '6.8'; }
function is_multisite() { return false; }
function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
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
unlink( dirname( $file ) . '/index.php' );
unlink( dirname( $file ) . '/.htaccess' );
rmdir( dirname( $file ) );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) unlink( $root . '/' . $name );
rmdir( $root . '/wp-admin' );
rmdir( $root . '/wp-includes' );
rmdir( $root . '/wp-content/plugins' );
rmdir( $root . '/wp-content' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: manifiesto completo y SHA-256 verificados.\n";
