<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fase «database» por tramos (T2). Cada tramo abre su instantánea (InnoDB: transacción REPEATABLE READ con instantánea
 * consistente; tablas no transaccionales: bloqueo de lectura; SQLite: su transacción), avanza por tablas y, dentro de
 * cada tabla, por lotes ordenados por la clave primaria con paginación por clave (`WHERE clave > última`), y al vencer
 * su plazo cierra la instantánea y guarda el punto de control: tabla, última clave escrita y bytes del SQL. Tras una
 * muerte, el tramo siguiente trunca el SQL a esos bytes y sigue en la fila siguiente, sin repetir ni saltar filas.
 *
 * Coherencia: si la exportación cabe en un tramo (siempre en WP-CLI y en el cron del sistema por PHP CLI, que no tienen
 * plazo, y en la web cuando la base cabe en el presupuesto de una petición) es la misma instantánea única de siempre
 * (`database_consistency`: `transaction` o `lock`). Si necesita varios tramos, cada uno es coherente por dentro, las
 * filas salen en orden de clave sin duplicados ni huecos para las que no cambian, y el manifiesto lo declara
 * (`transaction-steps` o `lock-steps`) con un aviso en el trabajo: entre tramos no hay una instantánea común.
 */
final class Digitalisimo_Backups_Sql_Export {
	const VERSION = 1;
	/** Bytes de SQL que un lote intenta leer de una vez (se adapta con el tamaño medio de las filas de cada tabla). */
	const BATCH_BYTES = 8388608;
	const HEADER = "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";
	const TRAILER = "SET foreign_key_checks = 1;\n";
	/** @var string Filas pendientes de escribir: menos llamadas al sistema que una escritura por fila. */
	private static $buffer = '';

	/** Un tramo de la fase «database»: devuelve el resultado de la fase para el motor de trabajos. */
	public static function phase( array $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$warnings = array();
		$cursor = self::resumable( $job, $staging );
		if ( ! $cursor ) {
			if ( ! empty( $job['checkpoint']['database']['resume'] ) ) $warnings[] = 'La exportación de la base de datos no se pudo retomar desde su punto de control y empezó de nuevo.';
			$cursor = self::fresh( $staging );
		}
		$started = microtime( true );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'database', $cursor );
		$retry = (int) ( $job['attempts']['database'] ?? 0 ) > 0;
		if ( $retry && ! empty( $cursor['batch'] ) ) $cursor['batch'] = max( 1, (int) floor( $cursor['batch'] / 2 ) );
		$deadline = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'] );
		Digitalisimo_Backups_Jobs::progress( 3, 0 === (int) $cursor['size'] ? 'Exportando la base de datos…' : 'Retomando la exportación de la base de datos…', true );
		try {
			$complete = self::run( $staging, $cursor, $deadline, $units );
		} catch ( Digitalisimo_Backups_Resume_Invalid $e ) {
			self::remove( $staging );
			return array( 'next' => 'database', 'checkpoint' => array(), 'message' => 'Exportando la base de datos otra vez desde el principio…', 'warnings' => array( $e->getMessage() . ' La exportación empieza de nuevo.' ) );
		}
		$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] );
		if ( ! $complete ) {
			$count = count( $cursor['tables'] );
			$table = $cursor['tables'][ min( $cursor['table'], $count - 1 ) ];
			return array( 'next' => 'database', 'checkpoint' => array( 'resume' => $cursor ), 'message' => 'Exportando la base de datos: tabla ' . min( $count, $cursor['table'] + 1 ) . ' de ' . $count . ' (' . $table . '), ' . number_format( (int) $cursor['total'], 0, ',', '.' ) . ' filas…', 'warnings' => $warnings );
		}
		$part = $staging . '/' . $cursor['file'];
		$final = $staging . '/' . substr( $cursor['file'], 0, -5 );
		if ( ! @rename( $part, $final ) ) throw new Exception( 'No se pudo cerrar el SQL exportado en el área de trabajo.' );
		$size = @filesize( $final );
		$hash = @hash_file( 'sha256', $final );
		if ( false === $size || false === $hash ) throw new Exception( 'No se pudo verificar el SQL exportado.' );
		$segments = (int) $cursor['segments'];
		if ( $segments > 1 ) $warnings[] = 'La base de datos se exportó en ' . $segments . ' tramos (' . number_format( (int) $cursor['total'], 0, ',', '.' ) . ' filas): cada tramo es coherente, pero no hay una instantánea única de toda la base. Para una sola instantánea, ejecuta el respaldo con WP-CLI o con el cron del sistema.';
		return array(
			'next' => 'pack',
			'checkpoint' => array( 'file' => basename( $final ), 'size' => (int) $size, 'sha256' => $hash, 'tables' => $cursor['tables'], 'mode' => $segments > 1 ? $cursor['mode'] . '-steps' : $cursor['mode'], 'at' => $cursor['at'], 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $final ), 'segments' => $segments, 'rows' => (int) $cursor['total'], 'last' => $cursor['last'] ),
			'message' => 'Base de datos exportada; empaquetando archivos…',
			'warnings' => $warnings,
		);
	}

	/** Punto de control utilizable: mismo servidor (el proceso anterior murió: su cerrojo está libre) y SQL presente. */
	private static function resumable( array $job, $staging ) {
		$cursor = $job['checkpoint']['database']['resume'] ?? null;
		if ( ! is_array( $cursor ) || self::VERSION !== (int) ( $cursor['v'] ?? 0 ) || ( $cursor['host'] ?? '' ) !== Digitalisimo_Backups_Jobs::host() ) return null;
		foreach ( array( 'file', 'tables', 'at', 'mode', 'table', 'started', 'keys', 'key', 'rows', 'size', 'segments', 'total' ) as $key ) if ( ! array_key_exists( $key, $cursor ) ) return null;
		if ( ! is_string( $cursor['file'] ) || ! preg_match( '/^database\.[A-Za-z0-9]{8}\.sql\.part$/D', $cursor['file'] ) || ! is_array( $cursor['tables'] ) || ! $cursor['tables'] ) return null;
		$path = $staging . '/' . $cursor['file'];
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || (int) filesize( $path ) < (int) $cursor['size'] ) return null;
		return $cursor;
	}
	private static function fresh( $staging ) {
		global $wpdb;
		self::remove( $staging );
		$at = gmdate( 'c' );
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) || ! $tables ) throw new Exception( 'No se pudo obtener la lista de tablas de la base de datos.' );
		return array( 'v' => self::VERSION, 'host' => Digitalisimo_Backups_Jobs::host(), 'file' => 'database.' . Digitalisimo_Backups_Jobs::current_owner_tag() . '.sql.part', 'tables' => array_values( array_map( 'strval', $tables ) ), 'at' => $at, 'mode' => null, 'table' => 0, 'started' => false, 'keys' => null, 'key' => null, 'rows' => 0, 'size' => 0, 'segments' => 0, 'total' => 0, 'batch' => 0, 'scale' => 1.0 );
	}
	private static function remove( $staging ) {
		foreach ( (array) @scandir( $staging ) as $name ) {
			if ( 0 !== strpos( (string) $name, 'database.' ) ) continue;
			$path = $staging . '/' . $name;
			if ( is_link( $path ) || is_file( $path ) ) @unlink( $path );
		}
	}

	/** Exporta dentro de una instantánea hasta terminar o hasta el plazo; deja el SQL vaciado a disco. */
	private static function run( $staging, array &$cursor, $deadline, &$units ) {
		$units = 0;
		$path = $staging . '/' . $cursor['file'];
		$out = @fopen( $path, 'c+b' );
		if ( ! $out ) throw new Exception( 'No se pudo abrir el archivo SQL temporal.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		self::$buffer = '';
		$mode = '';
		$complete = false;
		$ok = false;
		try {
			if ( ! ftruncate( $out, (int) $cursor['size'] ) || 0 !== fseek( $out, (int) $cursor['size'] ) ) throw new Exception( 'No se pudo preparar el SQL temporal.' );
			$mode = self::begin( $cursor );
			$cursor['segments']++;
			if ( 0 === (int) $cursor['size'] ) self::write( $out, self::HEADER );
			$count = count( $cursor['tables'] );
			while ( $cursor['table'] < $count ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) break;
				$table = (string) $cursor['tables'][ $cursor['table'] ];
				if ( ! $cursor['started'] ) self::start_table( $out, $table, $cursor );
				if ( self::rows( $out, $table, $cursor, $deadline, $units ) ) {
					self::write( $out, "\n" );
					$cursor['table']++;
					$cursor['started'] = false;
					$cursor['keys'] = null;
					$cursor['key'] = null;
					$cursor['rows'] = 0;
					$cursor['batch'] = 0;
				}
			}
			if ( $cursor['table'] >= $count ) {
				self::write( $out, self::TRAILER );
				$complete = true;
			}
			self::flush( $out );
			if ( ! fflush( $out ) ) throw new Exception( 'No se pudo completar el SQL temporal.' );
			if ( function_exists( 'fsync' ) ) @fsync( $out );
			$position = ftell( $out );
			if ( false === $position ) throw new Exception( 'No se pudo medir el SQL temporal.' );
			$cursor['size'] = (int) $position;
			$ok = true;
		} finally {
			self::$buffer = '';
			try {
				if ( '' !== $mode ) self::end( $mode, $ok );
			} finally {
				fclose( $out );
			}
		}
		return $complete;
	}

	/** Abre la instantánea del tramo. El motor de las tablas se mira una vez y queda en el punto de control. */
	private static function begin( array &$cursor ) {
		global $wpdb;
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) && 'WP_SQLite_DB' === get_class( $wpdb ) ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new Exception( 'No se pudo iniciar una transacción consistente de SQLite.' );
			$cursor['mode'] = 'transaction';
			return 'transaction';
		}
		if ( null === $cursor['mode'] ) {
			$transactional = true;
			foreach ( $cursor['tables'] as $table ) {
				$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ), ARRAY_A );
				if ( ! is_array( $status ) || empty( $status['Engine'] ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudo determinar el motor de la tabla ' . $table . '; no se creará un respaldo sin consistencia.' );
				if ( 'innodb' !== strtolower( $status['Engine'] ) ) $transactional = false;
			}
			$cursor['mode'] = $transactional ? 'transaction' : 'lock';
		}
		if ( 'transaction' === $cursor['mode'] ) {
			if ( false === $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) || false === $wpdb->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' ) ) throw new Exception( 'No se pudo iniciar una instantánea consistente de la base de datos.' );
			return 'transaction';
		}
		$locks = array_map( function( $table ) { return '`' . str_replace( '`', '``', $table ) . '` READ'; }, $cursor['tables'] );
		if ( false === $wpdb->query( 'LOCK TABLES ' . implode( ', ', $locks ) ) ) throw new Exception( 'No se pudieron bloquear las tablas no transaccionales durante el respaldo.' );
		return 'lock';
	}
	/** La instantánea sólo lee: cerrar con COMMIT o ROLLBACK no cambia nada; nunca queda abierta tras el tramo. */
	private static function end( $mode, $ok ) {
		global $wpdb;
		$command = 'lock' === $mode ? 'UNLOCK TABLES' : ( $ok ? 'COMMIT' : 'ROLLBACK' );
		if ( false === $wpdb->query( $command ) && $ok ) throw new Exception( 'No se pudo cerrar la instantánea consistente de la base de datos.' );
	}

	/** Definición de la tabla y su clave primaria (columnas, en orden, y si se comparan como número, binario o texto). */
	private static function start_table( $out, $table, array &$cursor ) {
		global $wpdb;
		$count = count( $cursor['tables'] );
		Digitalisimo_Backups_Jobs::progress( 5 + (int) floor( 33 * $cursor['table'] / $count ), 'Exportando tabla ' . ( $cursor['table'] + 1 ) . ' de ' . $count . ': ' . $table );
		$escaped = str_replace( '`', '``', $table );
		$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $escaped . '`', ARRAY_N );
		if ( ! is_array( $create ) || empty( $create[1] ) || ! empty( $wpdb->last_error ) ) {
			// En un tramo retomado, una tabla que ya no existe invalida la lista de tablas: se empieza otra vez.
			if ( $cursor['segments'] > 1 ) throw new Digitalisimo_Backups_Resume_Invalid( 'La tabla ' . $table . ' cambió o desapareció durante la exportación.' );
			throw new Exception( 'No se pudo exportar la definición de la tabla ' . $table . '.' );
		}
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $escaped . '`', ARRAY_A );
		if ( ! is_array( $indexes ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron analizar los índices de la tabla ' . $table . '.' );
		$primary = array();
		foreach ( $indexes as $index ) if ( 'PRIMARY' === ( $index['Key_name'] ?? '' ) && isset( $index['Column_name'] ) ) $primary[ (int) ( $index['Seq_in_index'] ?? 1 ) ] = (string) $index['Column_name'];
		ksort( $primary );
		$keys = array();
		foreach ( $primary as $column ) $keys[] = array( $column, self::kind( (string) $create[1], $column ) );
		self::write( $out, 'DROP TABLE IF EXISTS `' . $escaped . "`;\n" . $create[1] . ";\n" );
		$cursor['started'] = true;
		$cursor['keys'] = $keys;
		$cursor['key'] = null;
		$cursor['rows'] = 0;
		$cursor['batch'] = 0;
	}
	/** Tipo de comparación de una columna según su definición: entero, número, binario o texto. */
	private static function kind( $create, $column ) {
		if ( ! preg_match( '/(?:^|[(,])\\s*`' . preg_quote( str_replace( '`', '``', $column ), '/' ) . '`\\s+([A-Za-z]+)/m', $create, $match ) ) return 's';
		$type = strtolower( $match[1] );
		if ( in_array( $type, array( 'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' ), true ) ) return 'i';
		if ( in_array( $type, array( 'decimal', 'numeric', 'float', 'double', 'real' ), true ) ) return 'n';
		if ( in_array( $type, array( 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit' ), true ) ) return 'x';
		return 's';
	}

	/**
	 * Lotes de filas de la tabla en curso hasta terminarla (true) o hasta el plazo (false). Sin clave primaria la tabla
	 * no se puede retomar a medias: se exporta entera en este tramo.
	 */
	private static function rows( $out, $table, array &$cursor, $deadline, &$units ) {
		global $wpdb;
		$escaped = '`' . str_replace( '`', '``', $table ) . '`';
		$streaming = class_exists( 'mysqli' ) && isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli;
		$keys = (array) $cursor['keys'];
		$count = count( $cursor['tables'] );
		$percent = 5 + (int) floor( 33 * $cursor['table'] / $count );
		if ( ! $keys ) {
			$fetched = self::fetch( $out, $table, 'SELECT * FROM ' . $escaped, $cursor, $streaming, INF );
			$units++;
			Digitalisimo_Backups_Jobs::progress( $percent, 'Tabla ' . $table . ': ' . $fetched['rows'] . ' filas exportadas' );
			return true;
		}
		$order = implode( ', ', array_map( function( $key ) { return '`' . str_replace( '`', '``', $key[0] ) . '`'; }, $keys ) );
		while ( true ) {
			if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return false;
			$limit = self::batch( $cursor, $streaming );
			$query = 'SELECT * FROM ' . $escaped . self::after( $keys, $cursor['key'], $streaming ) . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
			$fetched = self::fetch( $out, $table, $query, $cursor, $streaming, $deadline );
			$units++;
			// Un controlador que no respeta LIMIT repetiría filas para siempre: mejor fallar con el motivo.
			if ( $fetched['rows'] > $limit ) throw new Exception( 'La base de datos devolvió más filas de las pedidas al exportar ' . $table . '; su controlador no permite exportar por tramos.' );
			self::adapt( $cursor, $fetched, $streaming );
			Digitalisimo_Backups_Jobs::progress( $percent, 'Tabla ' . $table . ': ' . $cursor['rows'] . ' filas exportadas' );
			if ( $fetched['stopped'] ) return false;
			if ( $fetched['rows'] < $limit ) return true;
		}
	}
	/** Una consulta: escribe sus filas y deja la clave de la última en el punto de control. */
	private static function fetch( $out, $table, $query, array &$cursor, $streaming, $deadline ) {
		global $wpdb;
		$rows = 0;
		$bytes = 0;
		$stopped = false;
		if ( $streaming ) {
			$result = @mysqli_query( $wpdb->dbh, $query, MYSQLI_USE_RESULT );
			if ( false === $result ) throw new Exception( 'No se pudo leer la tabla ' . $table . ' en modo streaming.' );
			try {
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$bytes += self::row( $out, $table, $row, $cursor );
					$rows++;
					// Sólo con clave primaria se puede cortar a mitad de lote: la siguiente consulta sigue por la clave.
					if ( 0 === $rows % 200 && $cursor['keys'] && ( microtime( true ) >= $deadline || Digitalisimo_Backups_Step_Budget::memory_headroom() <= 0 ) ) { $stopped = true; break; }
				}
				if ( ! $stopped && mysqli_errno( $wpdb->dbh ) ) throw new Exception( 'Se interrumpió la lectura de la tabla ' . $table . '.' );
			} finally {
				mysqli_free_result( $result );
			}
		} else {
			$results = $wpdb->get_results( $query, ARRAY_A );
			if ( ! is_array( $results ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron exportar los datos de la tabla ' . $table . '.' );
			foreach ( $results as $index => $row ) {
				$bytes += self::row( $out, $table, $row, $cursor );
				unset( $results[ $index ] );
				$rows++;
			}
		}
		return array( 'rows' => $rows, 'bytes' => $bytes, 'stopped' => $stopped );
	}
	/** Escribe una fila (igual que el exportador de un solo paso) y anota su clave. */
	private static function row( $out, $table, array $row, array &$cursor ) {
		$key = array();
		foreach ( (array) $cursor['keys'] as $column ) {
			if ( ! isset( $row[ $column[0] ] ) ) throw new Exception( 'La tabla ' . $table . ' devolvió una fila sin su clave primaria.' );
			$key[] = self::key_part( (string) $row[ $column[0] ], $column[1] );
		}
		if ( $key ) $cursor['key'] = $key;
		$cursor['rows']++;
		$cursor['total']++;
		if ( Digitalisimo_Backups::secret_row( $table, $row ) ) return 0;
		$columns = array();
		$values = array();
		$length = 0;
		foreach ( $row as $column => $value ) {
			$columns[] = '`' . str_replace( '`', '``', $column ) . '`';
			$length += null === $value ? 4 : strlen( (string) $value );
		}
		$prefix = 'INSERT INTO `' . str_replace( '`', '``', $table ) . '` (' . implode( ',', $columns ) . ') VALUES (';
		if ( $length < 1048576 ) {
			foreach ( $row as $value ) $values[] = Digitalisimo_Backups::sql_value( $value );
			$line = $prefix . implode( ',', $values ) . ");\n";
			self::write( $out, $line );
			return strlen( $line );
		}
		// Fila grande: valor a valor, sin juntar toda la sentencia en memoria (mismos bytes).
		self::write( $out, $prefix );
		$written = strlen( $prefix );
		$first = true;
		foreach ( $row as $value ) {
			$piece = ( $first ? '' : ',' ) . Digitalisimo_Backups::sql_value( $value );
			$first = false;
			self::write( $out, $piece );
			$written += strlen( $piece );
			unset( $piece );
		}
		self::write( $out, ");\n" );
		return $written + 3;
	}
	/** Valor de una columna de la clave, guardable en JSON: número, binario en hexadecimal o texto (base64 si no es UTF-8). */
	private static function key_part( $value, $kind ) {
		if ( 'i' === $kind && preg_match( '/^-?[0-9]+$/D', $value ) ) return array( 'n', $value );
		if ( 'n' === $kind && preg_match( '/^-?[0-9]+(?:\.[0-9]+)?(?:[eE][-+]?[0-9]+)?$/D', $value ) ) return array( 'n', $value );
		if ( 'x' === $kind ) return array( 'x', bin2hex( $value ) );
		return preg_match( '//u', $value ) ? array( 's', $value ) : array( 'b', base64_encode( $value ) );
	}
	/** Condición de paginación por clave: filas estrictamente posteriores a la última escrita, en el orden de la clave. */
	private static function after( array $keys, $key, $streaming ) {
		if ( ! is_array( $key ) || count( $key ) !== count( $keys ) ) return '';
		$literals = array();
		foreach ( $key as $part ) $literals[] = self::literal( $part, $streaming );
		$any = array();
		foreach ( $keys as $i => $column ) {
			$all = array();
			for ( $j = 0; $j < $i; $j++ ) $all[] = '`' . str_replace( '`', '``', $keys[ $j ][0] ) . '` = ' . $literals[ $j ];
			$all[] = '`' . str_replace( '`', '``', $column[0] ) . '` > ' . $literals[ $i ];
			$any[] = '(' . implode( ' AND ', $all ) . ')';
		}
		return ' WHERE ' . implode( ' OR ', $any );
	}
	private static function literal( $part, $streaming ) {
		global $wpdb;
		list( $type, $value ) = $part;
		if ( 'n' === $type && preg_match( '/^-?[0-9]+(?:\.[0-9]+)?(?:[eE][-+]?[0-9]+)?$/D', (string) $value ) ) return (string) $value;
		if ( 'x' === $type && preg_match( '/^(?:[0-9a-f]{2})*$/D', (string) $value ) ) return "X'" . $value . "'";
		$bytes = 'b' === $type ? (string) base64_decode( (string) $value ) : (string) $value;
		// Con mysqli, el escape de la propia conexión (respeta NO_BACKSLASH_ESCAPES); sin él, el del exportador.
		if ( $streaming ) return "'" . mysqli_real_escape_string( $wpdb->dbh, $bytes ) . "'";
		return Digitalisimo_Backups::sql_value( $bytes );
	}
	/** Filas por consulta: prudente al empezar cada tabla, luego según el tamaño medio de sus filas y la memoria libre. */
	private static function batch( array &$cursor, $streaming ) {
		$fixed = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_db_batch', 0 ) : 0;
		if ( $fixed > 0 ) return $fixed;
		if ( empty( $cursor['batch'] ) ) $cursor['batch'] = $streaming ? 1000 : 100;
		return max( 1, (int) $cursor['batch'] );
	}
	private static function adapt( array &$cursor, array $fetched, $streaming ) {
		if ( $fetched['rows'] <= 0 ) return;
		$average = max( 1, (int) ceil( $fetched['bytes'] / $fetched['rows'] ) );
		$target = (int) min( self::BATCH_BYTES, max( 65536, Digitalisimo_Backups_Step_Budget::memory_headroom() / 4 ) );
		$wanted = (int) floor( $target / $average * min( 1.0, (float) ( $cursor['scale'] ?? 1.0 ) ) );
		$cursor['batch'] = max( 1, min( $streaming ? 50000 : 5000, $wanted, max( 10, 2 * (int) $cursor['batch'] ) ) );
	}

	private static function write( $handle, $sql ) {
		if ( strlen( $sql ) >= 262144 ) {
			// Un valor grande va directo, sin copiarlo al búfer.
			self::flush( $handle );
			self::put( $handle, $sql );
			return;
		}
		self::$buffer .= $sql;
		if ( strlen( self::$buffer ) >= 262144 ) self::flush( $handle );
	}
	private static function flush( $handle ) {
		if ( '' === self::$buffer ) return;
		self::put( $handle, self::$buffer );
		self::$buffer = '';
	}
	private static function put( $handle, $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir el SQL temporal completo.' );
			$offset += $written;
		}
	}
}
