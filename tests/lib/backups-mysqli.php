<?php
/**
 * `$wpdb` sobre un servidor MySQL o MariaDB de verdad (mysqli por socket) para las pruebas de Backups que necesitan el
 * servidor real: intercalaciones, motores, vistas, columnas generadas y el modo SQL los decide el servidor, no la
 * prueba. Hace lo que hace wpdb al conectar (utf8mb4 y quitar del sql_mode los modos que WordPress quita) y nada más.
 * No depende de ninguna clase del plugin: la misma prueba corre contra versiones anteriores.
 */
class Bk_Mysqli_DB {
	public $base_prefix;
	public $prefix;
	public $last_error = '';
	public $queries = array();
	public $num_rows = 0;
	public $rows_affected = 0;
	public $insert_id = 0;
	public $dbh;
	private $suppress = false;

	public function __construct( $socket, $database, $prefix = 'wp_' ) {
		mysqli_report( MYSQLI_REPORT_OFF );
		$this->dbh = mysqli_init();
		if ( ! @$this->dbh->real_connect( null, 'root', '', $database, 0, $socket ) ) throw new RuntimeException( 'No se pudo conectar a ' . $socket . ': ' . mysqli_connect_error() );
		$this->dbh->set_charset( 'utf8mb4' );
		$this->base_prefix = $prefix;
		$this->prefix = $prefix;
		// Como wpdb::set_sql_mode(): sin los modos que WordPress no admite.
		$modes = array_filter( explode( ',', (string) $this->get_var( 'SELECT @@SESSION.sql_mode' ) ) );
		$modes = array_diff( $modes, array( 'NO_ZERO_DATE', 'ONLY_FULL_GROUP_BY', 'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'TRADITIONAL', 'ANSI' ) );
		$this->query( "SET SESSION sql_mode = '" . implode( ',', $modes ) . "'" );
	}
	public function db_version() { return preg_replace( '/[^0-9.].*/', '', preg_replace( '/^5\.5\.5-/', '', (string) $this->dbh->server_info ) ); }
	public function db_server_info() { return (string) $this->dbh->server_info; }
	public function suppress_errors( $suppress = true ) { $old = $this->suppress; $this->suppress = (bool) $suppress; return $old; }
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		$dbh = $this->dbh;
		return preg_replace_callback( '/%%|%[sdf]/', function( $m ) use ( &$args, $dbh ) {
			if ( '%%' === $m[0] ) return '%';
			$value = array_shift( $args );
			if ( '%d' === $m[0] ) return (string) (int) $value;
			if ( '%f' === $m[0] ) return (string) (float) $value;
			return "'" . mysqli_real_escape_string( $dbh, (string) $value ) . "'";
		}, $query );
	}
	private function run( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$result = mysqli_query( $this->dbh, $sql );
		if ( false === $result ) { $this->last_error = mysqli_error( $this->dbh ); return false; }
		if ( $result instanceof mysqli_result ) { $rows = $result->fetch_all( MYSQLI_ASSOC ); $result->free(); $this->num_rows = count( $rows ); return $rows; }
		$this->rows_affected = mysqli_affected_rows( $this->dbh );
		$this->insert_id = mysqli_insert_id( $this->dbh );
		return true;
	}
	public function query( $sql ) {
		$rows = $this->run( $sql );
		if ( false === $rows ) return false;
		return is_array( $rows ) ? count( $rows ) : $this->rows_affected;
	}
	public function get_results( $sql, $output = 'OBJECT' ) {
		$rows = $this->run( $sql );
		if ( false === $rows ) return null;
		$rows = is_array( $rows ) ? $rows : array();
		if ( 'ARRAY_A' === $output ) return $rows;
		if ( 'ARRAY_N' === $output ) return array_map( 'array_values', $rows );
		return array_map( function( $row ) { return (object) $row; }, $rows );
	}
	public function get_row( $sql, $output = 'OBJECT', $y = 0 ) { $rows = $this->get_results( $sql, $output ); return is_array( $rows ) ? ( $rows[ $y ] ?? null ) : null; }
	public function get_col( $sql = null, $x = 0 ) { return array_map( function( $row ) use ( $x ) { return array_values( $row )[ $x ] ?? null; }, (array) $this->get_results( $sql, 'ARRAY_A' ) ); }
	public function get_var( $sql = null, $x = 0, $y = 0 ) { $rows = $this->get_results( $sql, 'ARRAY_N' ); return is_array( $rows ) ? ( $rows[ $y ][ $x ] ?? null ) : null; }
	/** Ejecuta y falla la prueba si el servidor rechaza la sentencia (para preparar los datos). */
	public function must( $sql ) { if ( false === $this->query( $sql ) ) throw new RuntimeException( 'El servidor rechazó: ' . substr( $sql, 0, 160 ) . ' — ' . $this->last_error ); }
}
