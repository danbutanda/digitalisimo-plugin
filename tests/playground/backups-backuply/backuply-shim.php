<?php
/**
 * Ensayo de Backuply en Playground (backups-backuply.sh). Playground no tiene MySQL, así que la copia de la base de datos
 * de Backuply (`backuply_mysql_fn()` de su `backup_ins.php`, que habla con mysqli) no puede conectar. El ensayo cambia en
 * la copia de Backuply sólo la elección del controlador (`extension_loaded('mysqli')` → `digi_backuply_mysqli()`), así
 * que Backuply usa su rama sin mysqli (`mysql_*`, funciones que PHP 7 y 8 ya no traen), y este mu-plugin las da sobre
 * $wpdb (el SQLite de Playground). El volcado (comentarios, SET, DROP/CREATE, INSERT de varias filas, escapes, cortes
 * a los 50 000 caracteres), el tar, el gzip y el archivo de información los escribe el código de Backuply sin cambios.
 */
define( 'DIGI_BACKUPLY_SHIM', true );
function digi_backuply_mysqli() { return false; }

final class Digi_Backuply_Result {
	public $rows;
	public $names;
	public $types;
	public $table;
	public $at = 0;
	public function __construct( array $rows, array $names, array $types = array(), $table = '' ) { $this->rows = array_values( $rows ); $this->names = $names; $this->types = $types; $this->table = $table; }
}
function digi_backuply_result( array $rows, array $names, array $types = array(), $table = '' ) { return new Digi_Backuply_Result( $rows, $names, $types, $table ); }
function mysql_connect( $host = '', $user = '', $pass = '', $new = false ) { return 'digi-backuply-shim'; }
function mysql_select_db( $db, $link = null ) { return true; }
function mysql_error( $link = null ) { return $GLOBALS['wpdb']->last_error; }
function mysql_query( $sql, $link = null ) {
	global $wpdb;
	$sql = trim( (string) $sql );
	if ( preg_match( '/^SET\s/i', $sql ) ) return true;
	if ( preg_match( '/^SELECT VERSION\(\)/i', $sql ) ) return digi_backuply_result( array( array( '8.0.36-playground' ) ), array( 'VERSION()' ) );
	if ( preg_match( "/^SHOW TABLE STATUS FROM `[^`]+` WHERE COMMENT = 'VIEW'/i", $sql ) ) return digi_backuply_result( array(), array( 'Name' ) );
	if ( preg_match( '/^SHOW TABLES FROM /i', $sql ) ) return digi_backuply_result( array_map( function( $table ) { return array( $table ); }, (array) $wpdb->get_col( 'SHOW TABLES' ) ), array( 'Tables' ) );
	if ( preg_match( "/^SHOW TABLE STATUS FROM `[^`]+` LIKE '([^']+)'/i", $sql, $m ) ) return digi_backuply_result( array( array( $m[1], null ) ), array( 'Name', 'Auto_increment' ) );
	if ( preg_match( '/^SHOW CREATE TABLE `[^`]+`\.`([^`]+)`/i', $sql, $m ) ) {
		$row = $wpdb->get_row( 'SHOW CREATE TABLE `' . $m[1] . '`', ARRAY_N );
		return is_array( $row ) ? digi_backuply_result( array( array( $row[0], $row[1] ) ), array( 'Table', 'Create Table' ) ) : false;
	}
	if ( preg_match( '/^SHOW (?:TRIGGERS|EVENTS|FUNCTION STATUS|PROCEDURE STATUS)/i', $sql ) ) return digi_backuply_result( array(), array( 'Name' ) );
	if ( preg_match( '/^SELECT count\(\*\) FROM `[^`]+`\.`([^`]+)`(.*)$/is', $sql, $m ) ) return digi_backuply_result( array( array( (string) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $m[1] . '`' . str_replace( '"backuply_status"', "'backuply_status'", $m[2] ) ) ) ), array( 'count(*)' ) );
	if ( preg_match( '/^SELECT \* FROM `[^`]+`\.`([^`]+)`(.*)$/is', $sql, $m ) ) {
		$columns = (array) $wpdb->get_results( 'SHOW COLUMNS FROM `' . $m[1] . '`', ARRAY_A );
		$rows = $wpdb->get_results( 'SELECT * FROM `' . $m[1] . '`' . str_replace( '"backuply_status"', "'backuply_status'", $m[2] ), ARRAY_N );
		return digi_backuply_result( is_array( $rows ) ? $rows : array(), array_column( $columns, 'Field' ), array_column( $columns, 'Type' ), $m[1] );
	}
	error_log( 'digi-backuply-shim: consulta no prevista: ' . $sql );
	return false;
}
function digi_backuply_row( $result ) { return $result instanceof Digi_Backuply_Result && $result->at < count( $result->rows ) ? array_values( $result->rows[ $result->at++ ] ) : false; }
function mysql_fetch_row( $result ) { return digi_backuply_row( $result ); }
function mysql_fetch_assoc( $result ) { $row = digi_backuply_row( $result ); return false === $row ? false : array_combine( $result->names, $row ); }
function mysql_fetch_array( $result ) { $row = digi_backuply_row( $result ); return false === $row ? false : $row + array_combine( $result->names, $row ); }
function mysql_num_rows( $result ) { return $result instanceof Digi_Backuply_Result ? count( $result->rows ) : 0; }
function mysql_num_fields( $result ) { return $result instanceof Digi_Backuply_Result ? count( $result->names ) : 0; }
function mysql_fetch_field( $result, $i = 0 ) {
	$type = strtolower( (string) ( $result->types[ $i ] ?? '' ) );
	$numeric = (bool) preg_match( '/^(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|bit)\b/', $type );
	return (object) array( 'name' => $result->names[ $i ], 'table' => $result->table, 'numeric' => $numeric ? 1 : 0, 'blob' => preg_match( '/blob|text/', $type ) ? 1 : 0, 'type' => $numeric ? ( 0 === strpos( $type, 'bit' ) ? 'bit' : 'int' ) : ( false !== strpos( $type, 'timestamp' ) ? 'timestamp' : ( preg_match( '/blob|text/', $type ) ? 'blob' : 'string' ) ), 'max_length' => 0, 'length' => 0 );
}
function mysql_field_flags( $result, $i ) { return ''; }
function mysql_field_table( $result, $i ) { return $result->table; }
function mysql_field_name( $result, $i ) { return $result->names[ $i ]; }
function mysql_free_result( $result ) { return true; }
function mysql_close( $link = null ) { return true; }
// El nombre de la base de datos que Backuply exige para copiarla (en Playground, vacío).
add_action( 'plugins_loaded', function() { global $wpdb; if ( '' === (string) $wpdb->dbname ) $wpdb->dbname = 'wordpress'; if ( '' === (string) $wpdb->dbhost ) $wpdb->dbhost = 'localhost'; }, 0 );
