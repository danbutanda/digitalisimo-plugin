<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fase «database» por tramos (T2). Cada tramo abre su instantánea (InnoDB: transacción REPEATABLE READ con instantánea
 * consistente; tablas no transaccionales: bloqueo de lectura; SQLite: su transacción) y exporta **tablas enteras**: una
 * tabla nunca se reparte entre dos instantáneas. Dentro de la tabla las filas salen por lotes en el orden de la clave
 * primaria (paginación por clave, o por desplazamiento si la clave es de un tipo que no se compara igual que se ordena),
 * siempre dentro de la misma instantánea. Entre tablas, el tramo se cierra al vencer su plazo; una tabla que no termina
 * antes del límite real de la petición se descarta (el SQL se trunca a su comienzo) y se repite entera en el tramo
 * siguiente. Si no cabe ni así, la fase falla diciendo qué tabla y que hay que respaldar con WP-CLI o el cron del sistema.
 *
 * Por qué tablas enteras: con una tabla partida entre instantáneas, una fila borrada y vuelta a crear entre dos tramos
 * (un transitorio que caduca: mismo `option_name`, `option_id` nuevo) salía dos veces y el SQL no se importaba (clave
 * única duplicada); un cambio de esquema a mitad dejaba INSERT con columnas que el CREATE no tenía. En una instantánea la
 * base garantiza las claves únicas y su esquema (MySQL no deja cambiar una tabla que una transacción abierta está leyendo).
 *
 * Coherencia: si toda la exportación cabe en un tramo (siempre en WP-CLI y en el cron del sistema por PHP CLI, sin plazo)
 * es la instantánea única de siempre (`database_consistency`: `transaction` o `lock`). Si no, cada tabla sale entera de
 * una instantánea y el manifiesto lo declara (`transaction-per-table` o `lock-per-table`), con un aviso visible en el
 * trabajo y en el historial: entre tablas puede haber diferencias de segundos o minutos.
 *
 * Al terminar, el SQL se comprueba por tablas antes de empaquetarlo: cada sentencia es de las que escribe el exportador,
 * cada INSERT nombra exactamente las columnas de su CREATE y ninguna clave primaria ni única se repite. Un SQL que no se
 * importaría no llega al ZIP: la exportación empieza de nuevo y, a la tercera vez, falla con el motivo.
 */
final class Digitalisimo_Backups_Sql_Export {
	const VERSION = 2;
	/** Bytes de SQL que un lote intenta leer de una vez (se adapta con el tamaño medio de las filas de cada tabla). */
	const BATCH_BYTES = 8388608;
	const HEADER = "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";
	const TRAILER = "SET foreign_key_checks = 1;\n";
	/** Veces que una tabla puede quedarse sin terminar en una petición antes de fallar pidiendo WP-CLI. */
	const MAX_ABANDONS = 3;
	/** Valores que la comprobación guarda como mucho por índice único y tabla (más allá, se fía de la instantánea). */
	const CHECK_SET_MAX = 200000;
	/** @var string Filas pendientes de escribir: menos llamadas al sistema que una escritura por fila. */
	private static $buffer = '';
	/** @var bool Este tramo pidió la lista de tablas (una tabla que falta no es un cambio entre tramos). */
	private static $listed = false;

	/** Un tramo de la fase «database»: devuelve el resultado de la fase para el motor de trabajos. */
	public static function phase( array $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$warnings = array();
		$previous = (array) ( $job['checkpoint']['database'] ?? array() );
		$restarts = (int) ( $previous['resume']['restarts'] ?? $previous['restarts'] ?? 0 );
		$reason = (string) ( $previous['resume']['reason'] ?? $previous['reason'] ?? '' );
		self::$listed = false;
		$cursor = self::resumable( $job, $staging );
		if ( ! $cursor ) {
			if ( ! empty( $previous['resume'] ) ) { $warnings[] = 'La exportación de la base de datos no se pudo retomar desde su punto de control y empezó de nuevo.'; $restarts++; }
			// Empezar de nuevo cuenta como avance (reinicia los intentos): a la tercera vez es un fallo que gasta intentos.
			if ( $restarts >= 3 ) throw new Exception( 'La exportación de la base de datos empezó de nuevo tres veces sin poder terminar' . ( '' !== $reason ? ': ' . $reason : '; revisa el registro del trabajo.' ) );
			$cursor = self::fresh( $staging );
			$cursor['restarts'] = $restarts;
			$cursor['reason'] = $reason;
		}
		$started = microtime( true );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'database', $cursor );
		$soft = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'] );
		$hard = Digitalisimo_Backups_Step_Budget::hard_deadline( $cursor['scale'] );
		$units = 0;
		try {
			if ( 'export' === $cursor['stage'] ) {
				// Antes de cada instantánea, lo que quedara en claro en los ajustes (lo que reescribe 1.0.60 tras volver desde
				// rollback/) se cifra, como en el exportador de un paso: es barato si ya no queda nada en claro.
				Digitalisimo_Backups::migrate_credentials();
				Digitalisimo_Backups_Jobs::progress( 3, 0 === (int) $cursor['size'] ? 'Exportando la base de datos…' : 'Retomando la exportación de la base de datos…', true );
				if ( self::run( $staging, $cursor, $soft, $hard, $units ) ) $cursor['stage'] = 'check';
			}
			if ( 'check' === $cursor['stage'] && ! ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $soft, $units ) ) ) {
				if ( self::check( $staging, $cursor, $soft, $units ) ) return self::finish( $staging, $cursor, $started, $units, $warnings );
			}
		} catch ( Digitalisimo_Backups_Resume_Invalid $e ) {
			self::remove( $staging );
			return array( 'next' => 'database', 'checkpoint' => array( 'restarts' => $restarts + 1, 'reason' => $e->getMessage() ), 'message' => 'Exportando la base de datos otra vez desde el principio…', 'warnings' => array( $e->getMessage() . ' La exportación empieza de nuevo.' ) );
		}
		$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] );
		$count = count( $cursor['tables'] );
		$message = 'check' === $cursor['stage']
			? 'Comprobando el SQL exportado: tabla ' . min( $count, (int) $cursor['check']['table'] + 1 ) . ' de ' . $count . '…'
			: 'Exportando la base de datos: tabla ' . min( $count, $cursor['table'] + 1 ) . ' de ' . $count . ' (' . $cursor['tables'][ min( $cursor['table'], $count - 1 ) ] . '), ' . number_format( (int) $cursor['total'], 0, ',', '.' ) . ' filas…';
		return array( 'next' => 'database', 'checkpoint' => array( 'resume' => $cursor ), 'message' => $message, 'warnings' => $warnings );
	}

	/** SQL comprobado: se cierra con su nombre final y el punto de control que necesita «pack». */
	private static function finish( $staging, array $cursor, $started, $units, array $warnings ) {
		$part = $staging . '/' . $cursor['file'];
		$final = $staging . '/' . substr( $cursor['file'], 0, -5 );
		if ( ! @rename( $part, $final ) ) throw new Exception( 'No se pudo cerrar el SQL exportado en el área de trabajo.' );
		$size = @filesize( $final );
		$hash = @hash_file( 'sha256', $final );
		if ( false === $size || false === $hash ) throw new Exception( 'No se pudo verificar el SQL exportado.' );
		$snapshots = (int) $cursor['snapshots'];
		$per_table = $snapshots > 1;
		if ( $per_table ) $warnings[] = array( 'notice' => 'database_per_table', 'text' => 'La base de datos se exportó en ' . $snapshots . ' tramos: cada tabla salió entera de una sola instantánea, pero no hay una instantánea común a todas (entre tablas puede haber diferencias de segundos o minutos). Para una instantánea única, respalda con WP-CLI o con el cron del sistema.' );
		return array(
			'next' => 'pack',
			'checkpoint' => array( 'file' => basename( $final ), 'size' => (int) $size, 'sha256' => $hash, 'tables' => $cursor['tables'], 'mode' => $per_table ? $cursor['mode'] . '-per-table' : $cursor['mode'], 'at' => $cursor['at'], 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $final ), 'segments' => $snapshots, 'rows' => (int) $cursor['total'], 'checked' => true, 'unchecked' => (array) ( $cursor['check']['unchecked'] ?? array() ), 'last' => Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] ) ),
			'message' => 'Base de datos exportada y comprobada; empaquetando archivos…',
			'warnings' => $warnings,
		);
	}

	/** Punto de control utilizable: mismo servidor (el proceso anterior murió: su cerrojo está libre) y SQL presente. */
	private static function resumable( array $job, $staging ) {
		$cursor = $job['checkpoint']['database']['resume'] ?? null;
		if ( ! is_array( $cursor ) || self::VERSION !== (int) ( $cursor['v'] ?? 0 ) || ( $cursor['host'] ?? '' ) !== Digitalisimo_Backups_Jobs::host() ) return null;
		foreach ( array( 'stage', 'file', 'tables', 'at', 'mode', 'table', 'size', 'snapshots', 'total', 'abandons', 'check' ) as $key ) if ( ! array_key_exists( $key, $cursor ) ) return null;
		if ( ! in_array( $cursor['stage'], array( 'export', 'check' ), true ) || ! is_string( $cursor['file'] ) || ! preg_match( '/^database\.[A-Za-z0-9]{8}\.sql\.part$/D', $cursor['file'] ) || ! is_array( $cursor['tables'] ) || ! $cursor['tables'] ) return null;
		$path = $staging . '/' . $cursor['file'];
		// Una muerte justo después de dar al SQL comprobado su nombre final: vuelve a su nombre de trabajo.
		if ( ! file_exists( $path ) && is_file( substr( $path, 0, -5 ) ) && ! is_link( substr( $path, 0, -5 ) ) ) @rename( substr( $path, 0, -5 ), $path );
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
		// La lista entera: así se ve también si una tabla es de otra instalación que comparte la base (wp_ y wp_dev_).
		$tables = Digitalisimo_Backups::exportable_tables( array_map( 'strval', $tables ) );
		if ( ! $tables ) throw new Exception( 'No se pudo obtener la lista de tablas de la base de datos.' );
		self::$listed = true;
		return array( 'v' => self::VERSION, 'host' => Digitalisimo_Backups_Jobs::host(), 'stage' => 'export', 'file' => 'database.' . Digitalisimo_Backups_Jobs::current_owner_tag() . '.sql.part', 'tables' => $tables, 'at' => $at, 'mode' => null, 'table' => 0, 'rows' => 0, 'size' => 0, 'snapshots' => 0, 'total' => 0, 'batch' => 0, 'abandons' => array(), 'check' => array( 'offset' => 0, 'table' => 0, 'unchecked' => array() ), 'scale' => 1.0 );
	}
	/**
	 * Las tablas temporales de una restauración que murió a mitad (`<prefijo>dgbkr_*`, las que se importaban, y
	 * `<prefijo>dgbko_*`, las que se apartaban) no son del sitio: respaldarlas las restauraría como tablas sueltas. La
	 * regla es la de la restauración por pasos (`Digitalisimo_Backups::exportable_tables()`), la misma en las dos exportaciones.
	 */
	private static function exportable( $table ) {
		return array() !== Digitalisimo_Backups::exportable_tables( array( (string) $table ) );
	}
	private static function remove( $staging ) {
		foreach ( (array) @scandir( $staging ) as $name ) {
			if ( 0 !== strpos( (string) $name, 'database.' ) ) continue;
			$path = $staging . '/' . $name;
			if ( is_link( $path ) || is_file( $path ) ) @unlink( $path );
		}
	}

	/**
	 * Exporta tablas enteras dentro de una instantánea: entre tablas hasta el plazo del tramo (`$soft`); una tabla en curso
	 * hasta el límite real de la petición (`$hard`), y si no termina se descarta. Deja el SQL vaciado a disco.
	 */
	private static function run( $staging, array &$cursor, $soft, $hard, &$units ) {
		$path = $staging . '/' . $cursor['file'];
		$out = @fopen( $path, 'c+b' );
		if ( ! $out ) throw new Exception( 'No se pudo abrir el archivo SQL temporal.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		self::$buffer = '';
		$mode = '';
		$complete = false;
		$ok = false;
		$done = 0;
		try {
			if ( ! ftruncate( $out, (int) $cursor['size'] ) || 0 !== fseek( $out, (int) $cursor['size'] ) ) throw new Exception( 'No se pudo preparar el SQL temporal.' );
			try {
				$mode = self::begin( $cursor );
			} catch ( Exception $e ) {
				// En un tramo retomado, no poder abrir la instantánea (una tabla de la lista ya no existe) invalida la lista.
				if ( ! self::$listed ) throw new Digitalisimo_Backups_Resume_Invalid( 'No se pudo abrir la instantánea con la lista de tablas del primer tramo (' . $e->getMessage() . ').' );
				throw $e;
			}
			if ( 0 === (int) $cursor['size'] ) self::write( $out, self::HEADER );
			$count = count( $cursor['tables'] );
			while ( $cursor['table'] < $count ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $soft, $units, true ) ) break;
				self::flush( $out );
				$start = ftell( $out );
				$began = microtime( true );
				$table = (string) $cursor['tables'][ $cursor['table'] ];
				if ( self::table( $out, $table, $cursor, $hard ) ) {
					self::write( $out, "\n" );
					$cursor['table']++;
					$cursor['rows'] = 0;
					$cursor['batch'] = 0;
					$units++;
					$done++;
					continue;
				}
				// No terminó antes del límite de la petición: se descarta y se repite entera en el tramo siguiente.
				self::$buffer = '';
				if ( false === $start || ! ftruncate( $out, $start ) || 0 !== fseek( $out, $start ) ) throw new Exception( 'No se pudo descartar la tabla ' . $table . ' a medio exportar.' );
				$cursor['total'] -= (int) $cursor['rows'];
				// Sólo cuenta contra la tabla un corte con la petición entera: primera del tramo, a escala completa (tras una
				// muerte el tramo trabaja menos y vuelve a 1 en pocos tramos) y sin un tick que empezó tarde.
				$fair = 0 === $units && (float) $cursor['scale'] >= 1.0 && ( $hard - $began ) >= Digitalisimo_Backups_Step_Budget::table_min_seconds();
				if ( $fair ) $cursor['abandons'][ $table ] = (int) ( $cursor['abandons'][ $table ] ?? 0 ) + 1;
				if ( (int) ( $cursor['abandons'][ $table ] ?? 0 ) >= self::MAX_ABANDONS ) throw new Digitalisimo_Backups_Permanent_Error( 'La tabla ' . $table . ' no cabe en una petición de este servidor (se cortó ' . self::MAX_ABANDONS . ' veces, la última tras ' . number_format( (int) $cursor['rows'], 0, ',', '.' ) . ' filas): no se puede exportar sin partir su instantánea, y partida podría no importarse. Ejecuta el respaldo con WP-CLI o con el cron del sistema por PHP CLI, que no tienen plazo.' );
				$cursor['rows'] = 0;
				$cursor['batch'] = 0;
				$units++;
				break;
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
			if ( $done ) $cursor['snapshots']++;
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

	/**
	 * Una tabla entera dentro de la instantánea del tramo: definición, clave primaria y lotes de filas. Devuelve false si
	 * llegó el límite de la petición (o casi no queda memoria) antes de terminarla.
	 */
	private static function table( $out, $table, array &$cursor, $hard ) {
		global $wpdb;
		$count = count( $cursor['tables'] );
		$percent = 5 + (int) floor( 33 * $cursor['table'] / $count );
		Digitalisimo_Backups_Jobs::progress( $percent, 'Exportando tabla ' . ( $cursor['table'] + 1 ) . ' de ' . $count . ': ' . $table );
		$escaped = str_replace( '`', '``', $table );
		$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $escaped . '`', ARRAY_N );
		if ( ! is_array( $create ) || empty( $create[1] ) || ! empty( $wpdb->last_error ) ) {
			// En un tramo retomado, una tabla que ya no existe invalida la lista de tablas: se empieza otra vez.
			if ( ! self::$listed ) throw new Digitalisimo_Backups_Resume_Invalid( 'La tabla ' . $table . ' cambió o desapareció durante la exportación.' );
			throw new Exception( 'No se pudo exportar la definición de la tabla ' . $table . '.' );
		}
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $escaped . '`', ARRAY_A );
		if ( ! is_array( $indexes ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron analizar los índices de la tabla ' . $table . '.' );
		$primary = array();
		foreach ( $indexes as $index ) if ( 'PRIMARY' === ( $index['Key_name'] ?? '' ) && isset( $index['Column_name'] ) ) $primary[ (int) ( $index['Seq_in_index'] ?? 1 ) ] = (string) $index['Column_name'];
		ksort( $primary );
		$keys = array();
		foreach ( $primary as $column ) $keys[] = array( $column, self::kind( (string) $create[1], $column ) );
		// Clave que no se compara igual que se ordena (ENUM, SET, FLOAT, TIMESTAMP…): por desplazamiento, que en una
		// instantánea es exacto.
		$by_offset = (bool) array_filter( $keys, function( $key ) { return 'o' === $key[1]; } );
		self::write( $out, 'DROP TABLE IF EXISTS `' . $escaped . "`;\n" . $create[1] . ";\n" );
		$cursor['rows'] = 0;
		$cursor['batch'] = 0;
		$streaming = class_exists( 'mysqli' ) && isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli;
		$escaped_table = '`' . $escaped . '`';
		if ( ! $keys ) {
			$fetched = self::fetch( $out, $table, 'SELECT * FROM ' . $escaped_table, $keys, $cursor, $streaming, $hard );
			return ! $fetched['stopped'];
		}
		$order = implode( ', ', array_map( function( $key ) { return '`' . str_replace( '`', '``', $key[0] ) . '`'; }, $keys ) );
		$last = null;
		$offset = 0;
		while ( true ) {
			if ( microtime( true ) >= $hard || Digitalisimo_Backups_Step_Budget::memory_critical() ) return false;
			$limit = self::batch( $cursor, $streaming );
			$query = 'SELECT * FROM ' . $escaped_table . ( $by_offset ? '' : self::after( $keys, $last, $streaming ) ) . ' ORDER BY ' . $order . ' LIMIT ' . ( $by_offset ? $offset . ', ' : '' ) . $limit;
			$fetched = self::fetch( $out, $table, $query, $keys, $cursor, $streaming, $hard );
			if ( $fetched['stopped'] ) return false;
			// Un controlador que no respeta LIMIT repetiría filas para siempre: mejor fallar con el motivo.
			if ( $fetched['rows'] > $limit ) throw new Exception( 'La base de datos devolvió más filas de las pedidas al exportar ' . $table . '; su controlador no permite exportar por lotes.' );
			$last = $fetched['last'];
			$offset += $fetched['rows'];
			self::adapt( $cursor, $fetched, $streaming );
			Digitalisimo_Backups_Jobs::progress( $percent, 'Tabla ' . $table . ': ' . $cursor['rows'] . ' filas exportadas' );
			if ( $fetched['rows'] < $limit ) return true;
		}
	}
	/** Tipo de comparación de una columna según su definición: entero, número exacto, binario, texto u otro (desplazamiento). */
	private static function kind( $create, $column ) {
		if ( ! preg_match( '/(?:^|[(,])\\s*`' . preg_quote( str_replace( '`', '``', $column ), '/' ) . '`\\s+([A-Za-z]+)/m', $create, $match ) ) return 's';
		$type = strtolower( $match[1] );
		if ( in_array( $type, array( 'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' ), true ) ) return 'i';
		if ( in_array( $type, array( 'decimal', 'numeric' ), true ) ) return 'n';
		if ( in_array( $type, array( 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' ), true ) ) return 'x';
		if ( in_array( $type, array( 'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'date', 'datetime', 'time', 'year' ), true ) ) return 's';
		return 'o';
	}
	/** Una consulta: escribe sus filas y devuelve cuántas, sus bytes y la clave de la última. */
	private static function fetch( $out, $table, $query, array $keys, array &$cursor, $streaming, $hard ) {
		global $wpdb;
		$rows = 0;
		$bytes = 0;
		$stopped = false;
		$last = null;
		if ( $streaming ) {
			$result = @mysqli_query( $wpdb->dbh, $query, MYSQLI_USE_RESULT );
			if ( false === $result ) throw new Exception( 'No se pudo leer la tabla ' . $table . ' en modo streaming.' );
			try {
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$last = self::key_of( $table, $row, $keys );
					$bytes += self::row( $out, $table, $row, $cursor );
					$rows++;
					if ( 0 === $rows % 200 && ( microtime( true ) >= $hard || Digitalisimo_Backups_Step_Budget::memory_critical() ) ) { $stopped = true; break; }
				}
				if ( ! $stopped && mysqli_errno( $wpdb->dbh ) ) throw new Exception( 'Se interrumpió la lectura de la tabla ' . $table . '.' );
			} finally {
				mysqli_free_result( $result );
			}
		} else {
			$results = $wpdb->get_results( $query, ARRAY_A );
			if ( ! is_array( $results ) || ! empty( $wpdb->last_error ) ) throw new Exception( 'No se pudieron exportar los datos de la tabla ' . $table . '.' );
			foreach ( $results as $index => $row ) {
				$last = self::key_of( $table, $row, $keys );
				$bytes += self::row( $out, $table, $row, $cursor );
				unset( $results[ $index ] );
				$rows++;
			}
		}
		return array( 'rows' => $rows, 'bytes' => $bytes, 'stopped' => $stopped, 'last' => $last );
	}
	private static function key_of( $table, array $row, array $keys ) {
		$key = array();
		foreach ( $keys as $column ) {
			if ( ! isset( $row[ $column[0] ] ) ) throw new Exception( 'La tabla ' . $table . ' devolvió una fila sin su clave primaria.' );
			$key[] = self::key_part( (string) $row[ $column[0] ], $column[1] );
		}
		return $key ?: null;
	}
	/** Escribe una fila (igual que el exportador de un solo paso). */
	private static function row( $out, $table, array $row, array &$cursor ) {
		$cursor['rows']++;
		$cursor['total']++;
		if ( Digitalisimo_Backups::secret_row( $table, $row ) ) return 0;
		$row = Digitalisimo_Backups::export_row( $table, $row ); // Ningún secreto de destino en claro (S4), como el exportador de un paso.
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
	/** Valor de una columna de la clave: número, binario en hexadecimal o texto (base64 si no es UTF-8). */
	private static function key_part( $value, $kind ) {
		if ( 'i' === $kind && preg_match( '/^-?[0-9]+$/D', $value ) ) return array( 'n', $value );
		if ( 'n' === $kind && preg_match( '/^-?[0-9]+(?:\.[0-9]+)?$/D', $value ) ) return array( 'n', $value );
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
		if ( 'n' === $type && preg_match( '/^-?[0-9]+(?:\.[0-9]+)?$/D', (string) $value ) ) return (string) $value;
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
		// Tras una muerte (escala menor que 1) el primer lote de cada tabla también es más pequeño.
		if ( empty( $cursor['batch'] ) ) $cursor['batch'] = max( 10, (int) floor( ( $streaming ? 1000 : 100 ) * min( 1.0, (float) ( $cursor['scale'] ?? 1.0 ) ) ) );
		return max( 1, (int) $cursor['batch'] );
	}
	private static function adapt( array &$cursor, array $fetched, $streaming ) {
		if ( $fetched['rows'] <= 0 ) return;
		$average = max( 1, (int) ceil( $fetched['bytes'] / $fetched['rows'] ) );
		$target = (int) min( self::BATCH_BYTES, max( 65536, Digitalisimo_Backups_Step_Budget::memory_headroom() / 4 ) );
		$wanted = (int) floor( $target / $average * min( 1.0, (float) ( $cursor['scale'] ?? 1.0 ) ) );
		$cursor['batch'] = max( 1, min( $streaming ? 50000 : 5000, $wanted, max( 10, 2 * (int) $cursor['batch'] ) ) );
	}

	/* ------------------------------------------------------------------ Comprobación del SQL */

	/**
	 * Comprueba el SQL exportado tabla a tabla, desde su punto de control: sólo las sentencias que escribe el exportador,
	 * cada INSERT con exactamente las columnas de su CREATE y ningún valor repetido en la clave primaria ni en una clave
	 * única. Devuelve true al llegar al final. Un SQL que no se importaría empieza la exportación de nuevo.
	 */
	private static function check( $staging, array &$cursor, $soft, &$units ) {
		$path = $staging . '/' . $cursor['file'];
		$in = @fopen( $path, 'rb' );
		if ( ! $in ) throw new Exception( 'No se pudo leer el SQL exportado para comprobarlo.' );
		$state = (array) $cursor['check'];
		$count = count( $cursor['tables'] );
		try {
			if ( 0 === (int) $state['offset'] ) {
				if ( self::HEADER !== fread( $in, strlen( self::HEADER ) ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'El SQL exportado no empieza como lo escribe el exportador.' );
				$state['offset'] = strlen( self::HEADER );
			}
			if ( 0 !== fseek( $in, (int) $state['offset'] ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'El SQL exportado es más corto que su punto de comprobación.' );
			while ( (int) $state['table'] < $count ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $soft, $units ) ) break;
				$table = (string) $cursor['tables'][ (int) $state['table'] ];
				Digitalisimo_Backups_Jobs::progress( 38, 'Comprobando el SQL exportado: tabla ' . ( (int) $state['table'] + 1 ) . ' de ' . $count . ': ' . $table );
				$unchecked = self::check_table( $in, $table );
				if ( $unchecked ) $state['unchecked'][] = $table;
				$state['table'] = (int) $state['table'] + 1;
				$state['offset'] = (int) ftell( $in );
				$units++;
			}
			$done = (int) $state['table'] >= $count;
			if ( $done && self::TRAILER !== stream_get_contents( $in ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'El SQL exportado no termina como lo escribe el exportador.' );
		} finally {
			fclose( $in );
		}
		$cursor['check'] = $state;
		return $done;
	}
	/** Una tabla del SQL. Devuelve true si algún índice único tenía demasiadas filas para comprobarlo entero. */
	private static function check_table( $in, $table ) {
		$quoted = str_replace( '`', '``', $table );
		$fail = function( $what ) use ( $table ) { return new Digitalisimo_Backups_Resume_Invalid( 'El SQL exportado no se podría importar: ' . $what . ' en la tabla ' . $table . '.' ); };
		if ( 'DROP TABLE IF EXISTS `' . $quoted . "`;\n" !== fgets( $in ) ) throw $fail( 'falta su DROP TABLE o está fuera de orden' );
		$create = '';
		do {
			$line = fgets( $in );
			if ( false === $line ) throw $fail( 'el CREATE TABLE está incompleto' );
			$create .= $line;
		} while ( ";\n" !== substr( $line, -2 ) );
		if ( 0 !== strpos( $create, 'CREATE TABLE `' . $quoted . '`' ) ) throw $fail( 'falta su CREATE TABLE' );
		preg_match_all( '/^\s+`((?:[^`]|``)+)`\s/m', $create, $match );
		$columns = array_map( function( $name ) { return str_replace( '``', '`', $name ); }, $match[1] );
		// Clave primaria y claves únicas (con su longitud de prefijo, si la tiene).
		$indexes = array();
		if ( preg_match_all( '/^\s+(PRIMARY KEY|UNIQUE (?:KEY|INDEX) `(?:[^`]|``)+`)\s*\(([^\n]+)\)/m', $create, $keys ) ) {
			foreach ( $keys[2] as $k => $definition ) {
				preg_match_all( '/`((?:[^`]|``)+)`(?:\((\d+)\))?/', $definition, $parts, PREG_SET_ORDER );
				$index = array();
				foreach ( $parts as $part ) {
					$position = array_search( str_replace( '``', '`', $part[1] ), $columns, true );
					if ( false === $position ) { $index = array(); break; }
					$index[] = array( $position, isset( $part[2] ) ? (int) $part[2] : 0 );
				}
				// Sólo la clave primaria sale en orden (las filas se leen por ella): un entero solo se comprueba creciente.
				if ( $index ) $indexes[] = array( 'columns' => $index, 'seen' => array(), 'int' => 'PRIMARY KEY' === $keys[1][ $k ] && 1 === count( $index ) && 'i' === self::kind( $create, $columns[ $index[0][0] ] ), 'last' => null, 'off' => false );
			}
		}
		$wanted = array();
		foreach ( $indexes as $index ) foreach ( $index['columns'] as $column ) $wanted[ $column[0] ] = true;
		$head = 'INSERT INTO `' . $quoted . '` (';
		$expected = $columns ? implode( ',', array_map( function( $name ) { return '`' . str_replace( '`', '``', $name ) . '`'; }, $columns ) ) : null;
		$limit = self::CHECK_SET_MAX;
		$unchecked = false;
		while ( false !== ( $line = fgets( $in ) ) ) {
			if ( "\n" === $line ) return $unchecked;
			if ( 0 !== strpos( $line, $head ) || ");\n" !== substr( $line, -3 ) ) throw $fail( 'hay una sentencia que el exportador no escribe' );
			$close = strpos( $line, ') VALUES (', strlen( $head ) );
			if ( false === $close ) throw $fail( 'hay un INSERT mal formado' );
			$named = substr( $line, strlen( $head ), $close - strlen( $head ) );
			if ( null !== $expected && $named !== $expected ) throw $fail( 'un INSERT nombra columnas que no son las de su CREATE TABLE (' . substr( $named, 0, 200 ) . ')' );
			list( $values, $captured ) = self::values( $line, $close + 10, $wanted );
			if ( null === $values || ( $columns && $values !== count( $columns ) ) ) throw $fail( 'un INSERT no tiene un valor por columna' );
			foreach ( $indexes as $i => &$index ) {
				if ( $index['off'] ) continue;
				$parts = array();
				foreach ( $index['columns'] as $column ) {
					$value = $captured[ $column[0] ] ?? null;
					if ( null === $value ) continue 2; // Una clave única admite varios NULL.
					$parts[] = $column[1] > 0 ? self::prefix( $value, $column[1] ) : $value;
				}
				if ( $index['int'] && preg_match( '/^-?[0-9]+$/D', $parts[0] ) ) {
					// Entero solo: estrictamente creciente, sin guardar los valores.
					if ( null !== $index['last'] && self::compare_integers( $parts[0], $index['last'] ) <= 0 ) throw $fail( 'la clave primaria ' . $parts[0] . ' se repite o sale fuera de orden' );
					$index['last'] = $parts[0];
					continue;
				}
				$hash = md5( implode( "\0", $parts ), true );
				if ( isset( $index['seen'][ $hash ] ) ) throw $fail( 'un valor de una clave única se repite (' . substr( implode( ', ', $parts ), 0, 120 ) . ')' );
				$index['seen'][ $hash ] = true;
				if ( count( $index['seen'] ) >= $limit || Digitalisimo_Backups_Step_Budget::memory_headroom() <= 0 ) {
					$index['seen'] = array();
					$index['off'] = true;
					$unchecked = true;
				}
			}
			unset( $index );
		}
		throw $fail( 'el SQL termina a mitad de la tabla' );
	}
	/**
	 * Recorre los valores de un INSERT del exportador (NULL o cadenas entre comillas con sus escapes) y devuelve cuántos
	 * hay y el texto sin las comillas de los que se piden. null si el INSERT está mal formado.
	 */
	private static function values( $line, $position, array $wanted ) {
		$end = strlen( $line ) - 3;
		$index = 0;
		$captured = array();
		while ( true ) {
			if ( 'N' === ( $line[ $position ] ?? '' ) && 0 === substr_compare( $line, 'NULL', $position, 4 ) ) {
				$position += 4;
			} elseif ( "'" === ( $line[ $position ] ?? '' ) ) {
				$start = $position + 1;
				$cursor = $start;
				while ( true ) {
					$cursor += strcspn( $line, "\\'", $cursor );
					if ( $cursor >= $end ) return array( null, array() );
					if ( '\\' === $line[ $cursor ] ) { $cursor += 2; continue; }
					break;
				}
				if ( isset( $wanted[ $index ] ) ) $captured[ $index ] = substr( $line, $start, $cursor - $start );
				$position = $cursor + 1;
			} else {
				return array( null, array() );
			}
			$index++;
			if ( $position === $end ) return array( $index, $captured );
			if ( ',' !== ( $line[ $position ] ?? '' ) ) return array( null, array() );
			$position++;
		}
	}
	/** Los primeros caracteres de un valor escapado, para una clave única con prefijo. */
	private static function prefix( $escaped, $length ) {
		$value = strtr( $escaped, array( '\\\\' => '\\', '\\0' => "\0", '\\n' => "\n", '\\r' => "\r", '\\Z' => "\x1a", "\\'" => "'" ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
	}
	private static function compare_integers( $a, $b ) {
		$negative_a = '-' === $a[0];
		$negative_b = '-' === $b[0];
		if ( $negative_a !== $negative_b ) return $negative_a ? -1 : 1;
		$a = ltrim( $a, '-' );
		$b = ltrim( $b, '-' );
		$order = strlen( $a ) <=> strlen( $b ) ?: strcmp( $a, $b );
		return $negative_a ? -$order : $order;
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
