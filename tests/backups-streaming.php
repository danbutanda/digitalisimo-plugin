<?php
/** Exportación/importación SQL por lotes sin cargar todas las filas ni sentencias juntas. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
class Digitalisimo_Streaming_DB {
	public $last_error = '';
	public $queries = array();
	public $fail_second_batch = false;
	public $engine = 'InnoDB';
	public $no_primary = false;
	public function prepare( $query, $value ) { return str_replace( '%s', "'" . $value . "'", $query ); }
	public function get_row( $sql, $format ) { $this->last_error = ''; return false !== strpos( $sql, 'SHOW TABLE STATUS' ) ? array( 'Engine' => $this->engine ) : array( 'wp_posts', 'CREATE TABLE `wp_posts` (`ID` bigint)' ); }
	public function get_results( $sql, $format ) {
		$this->queries[] = $sql;
		if ( false !== strpos( $sql, 'SHOW INDEX' ) ) return $this->no_primary ? array() : array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'ID', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( $this->no_primary && 'SELECT * FROM `wp_posts`' === $sql ) return array_fill( 0, 501, array( 'ID' => 1 ) );
		if ( false !== strpos( $sql, 'LIMIT 0, 500' ) ) return array_fill( 0, 500, array( 'ID' => 1 ) );
		if ( $this->fail_second_batch ) { $this->last_error = 'Fallo simulado'; return null; }
		$this->last_error = '';
		return array( array( 'ID' => 501 ) );
	}
	public function query( $sql ) { $this->queries[] = $sql; return 1; }
}
$GLOBALS['wpdb'] = new Digitalisimo_Streaming_DB();
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
$export = new ReflectionMethod( 'Digitalisimo_Backups', 'database_sql_to_file' );
$import = new ReflectionMethod( 'Digitalisimo_Backups', 'import_zip_sql' );
if ( PHP_VERSION_ID < 80100 ) { $export->setAccessible( true ); $import->setAccessible( true ); }
$path = tempnam( sys_get_temp_dir(), 'backups-stream-' );
$export->invoke( null, array( 'wp_posts' ), $path );
$sql = file_get_contents( $path );
if ( 501 !== substr_count( $sql, 'INSERT INTO `wp_posts`' ) || ! in_array( 'START TRANSACTION WITH CONSISTENT SNAPSHOT', $GLOBALS['wpdb']->queries, true ) || ! in_array( 'COMMIT', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'El SQL no se exportó dentro de una instantánea consistente.' );
if ( ! in_array( 'SELECT * FROM `wp_posts` ORDER BY `ID` LIMIT 500, 500', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'No se solicitó el segundo lote con orden estable.' );
$GLOBALS['wpdb']->fail_second_batch = true;
try {
	$export->invoke( null, array( 'wp_posts' ), $path );
	throw new RuntimeException( 'Se aceptó un SQL cuyo segundo lote falló.' );
} catch ( Exception $e ) {
	if ( false === strpos( $e->getMessage(), 'No se pudieron exportar' ) ) throw $e;
}
if ( ! in_array( 'ROLLBACK', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'El fallo no cerró la transacción.' );
$GLOBALS['wpdb']->fail_second_batch = false;
$GLOBALS['wpdb']->engine = 'MyISAM';
$GLOBALS['wpdb']->queries = array();
$export->invoke( null, array( 'wp_posts' ), $path );
if ( ! in_array( 'LOCK TABLES `wp_posts` READ', $GLOBALS['wpdb']->queries, true ) || ! in_array( 'UNLOCK TABLES', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'La tabla no transaccional quedó sin bloqueo consistente.' );
$GLOBALS['wpdb']->engine = 'InnoDB';
$GLOBALS['wpdb']->no_primary = true;
$GLOBALS['wpdb']->queries = array();
$export->invoke( null, array( 'wp_posts' ), $path );
if ( 501 !== substr_count( file_get_contents( $path ), 'INSERT INTO `wp_posts`' ) || ! in_array( 'SELECT * FROM `wp_posts`', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'Una tabla sin clave primaria se paginó sin orden estable.' );
$zip_path = $path . '.zip';
$zip = new ZipArchive();
$zip->open( $zip_path, ZipArchive::CREATE );
$zip->addFromString( 'database.sql', "SET NAMES utf8mb4;\nINSERT INTO `wp_posts` (`ID`) VALUES ('" . str_repeat( 'x', 65530 ) . ";suffix');\n" );
$zip->close();
$zip->open( $zip_path );
$GLOBALS['wpdb']->queries = array();
$import->invoke( null, $zip );
if ( 2 !== count( $GLOBALS['wpdb']->queries ) || false === strpos( $GLOBALS['wpdb']->queries[1], ';suffix' ) ) throw new RuntimeException( 'La importación dividió una cadena SQL al cruzar el límite de lectura.' );
$zip->close();
define( 'DB_ENGINE', 'sqlite' );
class WP_SQLite_DB extends Digitalisimo_Streaming_DB {}
$GLOBALS['wpdb'] = new WP_SQLite_DB();
$export->invoke( null, array( 'wp_posts' ), $path );
if ( ! in_array( 'START TRANSACTION', $GLOBALS['wpdb']->queries, true ) || in_array( 'START TRANSACTION WITH CONSISTENT SNAPSHOT', $GLOBALS['wpdb']->queries, true ) || ! in_array( 'COMMIT', $GLOBALS['wpdb']->queries, true ) ) throw new RuntimeException( 'El drop-in SQLite no usó su transacción compatible.' );
unlink( $zip_path );
unlink( $path );
echo "DIGITALÍSIMO Backups: SQL exportado e importado por streaming.\n";
