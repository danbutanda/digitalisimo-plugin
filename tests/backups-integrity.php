<?php
/** Verifica que errores SQL y paquetes incompletos impidan declarar un respaldo creado. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
class Digitalisimo_Test_DB {
	public $last_error = '';
	public $mode = 'ok';
	public function get_col( $sql ) { return 'missing-list' === $this->mode ? null : array( 'wp_posts' ); }
	public function get_row( $sql, $format ) { return 'missing-create' === $this->mode ? null : array( 'wp_posts', 'CREATE TABLE `wp_posts` (`ID` bigint)' ); }
	public function get_results( $sql, $format ) {
		if ( 'failed-select' === $this->mode ) { $this->last_error = 'falló'; return null; }
		$this->last_error = '';
		return array( array( 'ID' => 7 ) );
	}
}
$GLOBALS['wpdb'] = new Digitalisimo_Test_DB();
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
$sql_method = new ReflectionMethod( 'Digitalisimo_Backups', 'database_sql' );
if ( PHP_VERSION_ID < 80100 ) $sql_method->setAccessible( true );
foreach ( array( 'missing-list', 'missing-create', 'failed-select' ) as $mode ) {
	$GLOBALS['wpdb']->mode = $mode;
	try {
		$sql_method->invoke( null );
		throw new RuntimeException( 'La exportación SQL incompleta fue aceptada: ' . $mode );
	} catch ( Exception $e ) {
		if ( false === strpos( $e->getMessage(), 'tabla' ) && false === strpos( $e->getMessage(), 'tablas' ) ) throw $e;
	}
}
$GLOBALS['wpdb']->mode = 'ok';
$sql = $sql_method->invoke( null );
if ( false === strpos( $sql, 'CREATE TABLE `wp_posts`' ) || false === strpos( $sql, 'VALUES (\'7\')' ) ) throw new RuntimeException( 'Falta definición o contenido SQL.' );

$add_string = new ReflectionMethod( 'Digitalisimo_Backups', 'zip_add_string' );
$add_file = new ReflectionMethod( 'Digitalisimo_Backups', 'zip_add_file' );
$finish = new ReflectionMethod( 'Digitalisimo_Backups', 'finish_zip' );
if ( PHP_VERSION_ID < 80100 ) { $add_string->setAccessible( true ); $add_file->setAccessible( true ); $finish->setAccessible( true ); }
$root = sys_get_temp_dir() . '/digitalisimo-zip-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $root, 0700 );
$file = $root . '/valid.zip';
$zip = new ZipArchive();
if ( true !== $zip->open( $file, ZipArchive::CREATE ) ) throw new RuntimeException( 'No se pudo crear ZIP de prueba.' );
$add_string->invoke( null, $zip, 'database.sql', $sql );
$add_string->invoke( null, $zip, 'manifest.json', '{"scope":"network"}' );
$add_string->invoke( null, $zip, 'wordpress/index.php', '<?php echo 1;' );
$finish->invoke( null, $zip, $file );
if ( ! is_file( $file ) ) throw new RuntimeException( 'Se descartó un ZIP íntegro.' );

$incomplete = $root . '/incomplete.zip';
$zip = new ZipArchive();
$zip->open( $incomplete, ZipArchive::CREATE );
$add_string->invoke( null, $zip, 'database.sql', $sql );
try {
	$finish->invoke( null, $zip, $incomplete );
	throw new RuntimeException( 'Se aceptó un ZIP sin metadata.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'metadata' ) ) throw $e;
}
if ( is_file( $incomplete ) ) throw new RuntimeException( 'Quedó publicado un ZIP incompleto.' );
$partial = $root . '/partial.zip';
$zip = new ZipArchive();
$zip->open( $partial, ZipArchive::CREATE );
$add_string->invoke( null, $zip, 'database.sql', $sql );
$add_string->invoke( null, $zip, 'manifest.json', '{"scope":"site","type":"incremental"}' );
$zip->close();
$restore = new ReflectionMethod( 'Digitalisimo_Backups', 'restore_digitalisimo_zip' );
if ( PHP_VERSION_ID < 80100 ) $restore->setAccessible( true );
try {
	$restore->invoke( null, $partial );
	throw new RuntimeException( 'Una copia incremental fue aceptada como restauración completa.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'paquete parcial' ) ) throw $e;
}
unlink( $partial );
$unsafe = $root . '/unsafe.zip';
$zip = new ZipArchive();
$zip->open( $unsafe, ZipArchive::CREATE );
$zip->addFromString( 'wordpress/../escape.php', '<?php echo 1;' );
$zip->close();
$validate = new ReflectionMethod( 'Digitalisimo_Backups', 'validate_zip_entries' );
if ( PHP_VERSION_ID < 80100 ) $validate->setAccessible( true );
$zip = new ZipArchive();
$zip->open( $unsafe );
try {
	$validate->invoke( null, $zip );
	throw new RuntimeException( 'Se aceptó un archivo ZIP con ruta transversal.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'ruta no permitida' ) ) throw $e;
} finally {
	$zip->close();
}
unlink( $unsafe );
$zip = new ZipArchive();
$zip->open( $root . '/unreadable.zip', ZipArchive::CREATE );
try {
	$add_file->invoke( null, $zip, $root . '/missing.php', 'wordpress/missing.php' );
	throw new RuntimeException( 'Se aceptó un archivo fuente ausente.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'No se pudo agregar' ) ) throw $e;
}
$zip->close();
@unlink( $root . '/unreadable.zip' );
unlink( $file );
rmdir( $root );
echo "DIGITALÍSIMO Backups: SQL y ZIP incompletos se rechazan.\n";
