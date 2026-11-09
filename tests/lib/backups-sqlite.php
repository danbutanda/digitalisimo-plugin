<?php
/**
 * `$wpdb` sobre SQLite (pdo_sqlite) para las pruebas de Backups que necesitan una base de datos de verdad: exportar con
 * el motor de trabajos real, restaurar con su importación y leer después lo que quedó. Traduce lo poco de MySQL que usan
 * el exportador, la importación y la reubicación (SHOW TABLES/CREATE TABLE/TABLE STATUS/INDEX/COLUMNS, SET, START
 * TRANSACTION, LOCK TABLES) y ejecuta los INSERT del exportador (literales con barras invertidas de MySQL) con valores
 * enlazados. No depende de ninguna clase del plugin: la misma prueba corre contra versiones anteriores.
 */
class Bk_Sqlite_DB {
	public $base_prefix;
	public $prefix;
	public $last_error = '';
	public $queries = array();
	public $num_rows = 0;
	public $rows_affected = 0;
	public $pdo;
	/** Una sentencia que contiene este texto falla (simula un error de la base de datos). */
	public $fail_on = '';

	public function __construct( $path, $prefix = 'wp_' ) {
		$this->pdo = new PDO( 'sqlite:' . $path );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->base_prefix = $prefix;
		$this->prefix = $prefix;
	}
	public function db_version() { return '8.0.36'; }
	public function suppress_errors( $suppress = true ) { return false; }
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	/** Como wpdb::prepare(): %s, %d, %f y %% con argumentos sueltos o en un array. */
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		$pdo = $this->pdo;
		return preg_replace_callback( '/%%|%[sdf]/', function( $m ) use ( &$args, $pdo ) {
			if ( '%%' === $m[0] ) return '%';
			$value = array_shift( $args );
			if ( '%d' === $m[0] ) return (string) (int) $value;
			if ( '%f' === $m[0] ) return (string) (float) $value;
			return $pdo->quote( (string) $value );
		}, $query );
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$this->rows_affected = 0;
		if ( '' !== $this->fail_on && false !== strpos( $sql, $this->fail_on ) ) { $this->last_error = 'Fallo simulado'; return false; }
		try {
			$rows = $this->run( $sql );
		} catch ( Throwable $e ) {
			$this->last_error = $e->getMessage();
			return false;
		}
		if ( is_array( $rows ) ) { $this->num_rows = count( $rows ); return $this->num_rows; }
		return $this->rows_affected;
	}
	public function get_results( $sql, $output = 'OBJECT' ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		try {
			$rows = $this->run( $sql );
		} catch ( Throwable $e ) {
			$this->last_error = $e->getMessage();
			return null;
		}
		$rows = is_array( $rows ) ? $rows : array();
		if ( 'ARRAY_A' === $output ) return $rows;
		if ( 'ARRAY_N' === $output ) return array_map( 'array_values', $rows );
		return array_map( function( $row ) { return (object) $row; }, $rows );
	}
	public function get_row( $sql, $output = 'OBJECT', $y = 0 ) { $rows = $this->get_results( $sql, $output ); return $rows[ $y ] ?? null; }
	public function get_col( $sql = null, $x = 0 ) { return array_map( function( $row ) use ( $x ) { return array_values( $row )[ $x ] ?? null; }, (array) $this->get_results( $sql, 'ARRAY_A' ) ); }
	public function get_var( $sql = null, $x = 0, $y = 0 ) { $rows = $this->get_results( $sql, 'ARRAY_N' ); return $rows[ $y ][ $x ] ?? null; }

	/** Filas (SELECT y SHOW) o null (el resto, con rows_affected). */
	private function run( $sql ) {
		$sql = trim( (string) $sql );
		if ( preg_match( '/^SHOW TABLES(?:\s+LIKE\s+\'((?:[^\'\\\\]|\\\\.)*)\')?$/i', $sql, $m ) ) {
			$rows = $this->pdo->query( "SELECT name AS t FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite!_%' ESCAPE '!' ORDER BY name" )->fetchAll( PDO::FETCH_ASSOC );
			if ( isset( $m[1] ) ) {
				$pattern = '/^' . strtr( preg_quote( stripslashes( str_replace( array( '\\_', '\\%' ), array( "\x01", "\x02" ), $m[1] ) ), '/' ), array( '%' => '.*', '_' => '.', "\x01" => '_', "\x02" => '%' ) ) . '$/D';
				$rows = array_values( array_filter( $rows, function( $row ) use ( $pattern ) { return preg_match( $pattern, $row['t'] ); } ) );
			}
			return array_map( function( $row ) { return array( 'Tables_in_wordpress' => $row['t'] ); }, $rows );
		}
		if ( preg_match( '/^SHOW CREATE TABLE `((?:[^`]|``)+)`$/i', $sql, $m ) ) {
			$table = str_replace( '``', '`', $m[1] );
			$statement = $this->pdo->prepare( "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?" );
			$statement->execute( array( $table ) );
			$create = $statement->fetchColumn();
			if ( false === $create ) throw new RuntimeException( "Table '" . $table . "' doesn't exist" );
			return array( array( 'Table' => $table, 'Create Table' => $create ) );
		}
		if ( preg_match( '/^SHOW TABLE STATUS WHERE Name = /i', $sql ) ) return array( array( 'Name' => 'x', 'Engine' => 'InnoDB' ) );
		if ( preg_match( '/^SHOW (?:FULL )?COLUMNS FROM `((?:[^`]|``)+)`$/i', $sql, $m ) ) {
			$out = array();
			foreach ( $this->info( str_replace( '``', '`', $m[1] ) ) as $column ) $out[] = array( 'Field' => $column['name'], 'Type' => strtolower( (string) $column['type'] ), 'Null' => $column['notnull'] ? 'NO' : 'YES', 'Key' => $column['pk'] ? 'PRI' : '', 'Default' => $column['dflt_value'], 'Extra' => '' );
			return $out;
		}
		if ( preg_match( '/^SHOW INDEX FROM `((?:[^`]|``)+)`$/i', $sql, $m ) ) {
			$out = array();
			foreach ( $this->info( str_replace( '``', '`', $m[1] ) ) as $column ) if ( $column['pk'] ) $out[] = array( 'Key_name' => 'PRIMARY', 'Column_name' => $column['name'], 'Non_unique' => 0, 'Seq_in_index' => $column['pk'] );
			return $out;
		}
		if ( preg_match( '/^(?:SET\s|LOCK TABLES|UNLOCK TABLES)/i', $sql ) ) return null;
		if ( preg_match( '/^START TRANSACTION/i', $sql ) ) { if ( ! $this->pdo->inTransaction() ) $this->pdo->beginTransaction(); return null; }
		if ( preg_match( '/^(COMMIT|ROLLBACK)$/i', $sql, $m ) ) { if ( $this->pdo->inTransaction() ) ( 'COMMIT' === strtoupper( $m[1] ) ? $this->pdo->commit() : $this->pdo->rollBack() ); return null; }
		if ( preg_match( '/^INSERT INTO `((?:[^`]|``)+)`\s*\(([^)]*)\)\s*VALUES\s*/i', $sql, $m ) ) return $this->insert( str_replace( '``', '`', $m[1] ), $m[2], substr( $sql, strlen( $m[0] ) ) );
		if ( preg_match( '/^(?:SELECT|PRAGMA)\b/i', $sql ) ) return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
		$this->rows_affected = (int) $this->pdo->exec( $sql );
		return null;
	}
	private function info( $table ) {
		$rows = $this->pdo->query( 'PRAGMA table_info(' . $this->pdo->quote( $table ) . ')' )->fetchAll( PDO::FETCH_ASSOC );
		if ( ! $rows ) throw new RuntimeException( "Table '" . $table . "' doesn't exist" );
		return $rows;
	}
	/** INSERT del exportador: literales '…' con escapes de MySQL o NULL, una o varias filas, con valores enlazados. */
	private function insert( $table, $columns, $values ) {
		preg_match_all( '/`((?:[^`]|``)+)`/', $columns, $names );
		$names = array_map( function( $name ) { return str_replace( '``', '`', $name ); }, $names[1] );
		$rows = array();
		$i = 0;
		$n = strlen( $values );
		while ( $i < $n ) {
			while ( $i < $n && ( ctype_space( $values[ $i ] ) || ',' === $values[ $i ] ) ) $i++;
			if ( $i >= $n ) break;
			if ( '(' !== $values[ $i ] ) throw new RuntimeException( 'INSERT no reconocido por la prueba.' );
			$i++;
			$row = array();
			while ( true ) {
				while ( ctype_space( $values[ $i ] ) ) $i++;
				if ( "'" === $values[ $i ] ) {
					$value = '';
					$i++;
					while ( "'" !== $values[ $i ] ) {
						if ( '\\' === $values[ $i ] ) {
							$next = $values[ $i + 1 ];
							$map = array( '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", 'b' => "\x08" );
							$value .= $map[ $next ] ?? $next;
							$i += 2;
							continue;
						}
						$value .= $values[ $i++ ];
					}
					$i++;
					$row[] = $value;
				} elseif ( 0 === strncasecmp( substr( $values, $i, 4 ), 'NULL', 4 ) ) {
					$row[] = null;
					$i += 4;
				} else {
					throw new RuntimeException( 'Valor de INSERT no reconocido por la prueba.' );
				}
				while ( ctype_space( $values[ $i ] ) ) $i++;
				if ( ',' === $values[ $i ] ) { $i++; continue; }
				if ( ')' === $values[ $i ] ) { $i++; break; }
				throw new RuntimeException( 'INSERT mal formado para la prueba.' );
			}
			$rows[] = $row;
		}
		$statement = $this->pdo->prepare( 'INSERT INTO "' . str_replace( '"', '""', $table ) . '" (' . implode( ', ', array_map( function( $c ) { return '"' . str_replace( '"', '""', $c ) . '"'; }, $names ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $names ), '?' ) ) . ')' );
		foreach ( $rows as $row ) $statement->execute( $row );
		$this->rows_affected = count( $rows );
		return null;
	}
	/** Ayudas de la prueba: filas de una tabla y tablas existentes. */
	public function rows( $table, $where = '1 = 1' ) { return $this->pdo->query( 'SELECT * FROM "' . str_replace( '"', '""', $table ) . '" WHERE ' . $where )->fetchAll( PDO::FETCH_ASSOC ); }
	public function value( $table, $column, $where ) { $rows = $this->rows( $table, $where ); return $rows ? $rows[0][ $column ] : null; }
	public function names() { return $this->pdo->query( "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name" )->fetchAll( PDO::FETCH_COLUMN ); }
	public function create_sql( $table ) { $s = $this->pdo->prepare( "SELECT sql FROM sqlite_master WHERE name = ?" ); $s->execute( array( $table ) ); return (string) $s->fetchColumn(); }
	public function seed( $table, array $rows ) {
		foreach ( $rows as $row ) {
			$statement = $this->pdo->prepare( 'INSERT INTO "' . $table . '" (' . implode( ', ', array_map( function( $c ) { return '"' . $c . '"'; }, array_keys( $row ) ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $row ), '?' ) ) . ')' );
			$statement->execute( array_values( $row ) );
		}
	}
}
