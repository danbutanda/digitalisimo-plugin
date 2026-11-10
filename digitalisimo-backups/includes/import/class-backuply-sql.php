<?php
defined( 'ABSPATH' ) || exit;

/**
 * Traductor del `softsql.sql` de Backuply (I9) al SQL del exportador de Backups, por tramos y con memoria acotada.
 *
 * Backuply escribe un volcado al estilo de phpMyAdmin (`backuply_mysql_fn()` en su `backup_ins.php`): comentarios «--»,
 * `SET SQL_MODE`, `SET time_zone`, `/*!40101 SET … *\/`, `DROP TABLE IF EXISTS`, el `CREATE TABLE` de `SHOW CREATE TABLE`
 * (sin las claves foráneas, que van al final como `ALTER TABLE … ADD CONSTRAINT`), INSERT de varias filas sin lista de
 * columnas (cadenas con `''` o barra invertida, números sin comillas, NULL), vistas y, entre `DELIMITER`, disparadores,
 * eventos, funciones y procedimientos.
 *
 * El archivo es no confiable: nada de él se ejecuta aquí. Se lee con un analizador propio que entiende ese dialecto y
 * se vuelve a escribir con el formato del exportador (`DROP TABLE IF EXISTS`, `CREATE TABLE` y un INSERT por fila con
 * lista de columnas y cada valor como cadena de `Digitalisimo_Backups::sql_value()`), que el guardián SQL comprueba
 * otra vez al restaurar. Cada DROP y CREATE pasa ya aquí por el guardián (dialecto del exportador, sin confianza). Las
 * vistas, las rutinas, los disparadores, los eventos y las claves foráneas no se importan: se cuentan y se avisan.
 * Las filas de claves de Backups (`digitalisimo_backups_secret_*`) del sitio de origen tampoco.
 *
 * Por tramos: el punto de control está siempre en una frontera de sentencia (posición en el SQL de entrada, bytes ya
 * escritos, columnas de cada tabla y contadores); tras una muerte se trunca la salida y se sigue desde ahí.
 */
final class Digitalisimo_Backups_Backuply_Sql {
	const CHUNK = 1048576;
	const HEADER = "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n\n";
	const FOOTER = "SET foreign_key_checks = 1;\n";
	/** Lo que el lexer devuelve por un bloque entre DELIMITER que crea una rutina, un disparador o un evento. */
	const ROUTINE = "\0ROUTINE";

	private $in;
	private $base;
	private $length;
	private $out;
	private $state;
	private $guard;

	/**
	 * $in: el tar abierto; $base y $length: dónde empieza `softsql.sql` dentro de él y cuánto mide; $out: la salida ya
	 * truncada a `$state['out']`; $state: el punto de control (array() al empezar).
	 */
	public function __construct( $in, $base, $length, $out, array $state ) {
		$this->in = $in;
		$this->base = (int) $base;
		$this->length = (int) $length;
		$this->out = $out;
		$this->state = $state + array( 'pos' => 0, 'out' => 0, 'tables' => array(), 'order' => array(), 'rows' => 0, 'statements' => 0, 'notes' => array(), 'done' => false );
		$this->guard = new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, false );
	}
	public function state() { return $this->state; }

	/** Mayor sentencia de entrada que se acepta: una cuarta parte de la memoria libre, entre 1 y 64 MB. */
	public static function max_statement() {
		$limit = Digitalisimo_Backups_Step_Budget::memory_limit();
		$free = $limit ? $limit - memory_get_usage( true ) : 268435456;
		$max = (int) max( 1048576, min( 67108864, $free / 4 ) );
		return (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_import_max_statement', $max ) : $max );
	}

	/**
	 * Avanza hasta `$deadline` (o el tope de unidades de las pruebas). Devuelve true al terminar el SQL. Lanza
	 * Digitalisimo_Backups_Permanent_Error si el SQL no es un volcado de Backuply que se pueda importar.
	 */
	public function run( $deadline ) {
		if ( $this->state['done'] ) return true;
		if ( 0 === (int) $this->state['out'] ) $this->write( self::HEADER );
		if ( 0 !== fseek( $this->in, $this->base + (int) $this->state['pos'] ) ) throw new Exception( 'No se pudo leer el SQL de Backuply en el área de trabajo.' );
		$buffer = '';
		$at = 0;
		$scan = new Digitalisimo_Backups_Backuply_Sql_Lexer();
		$handled = 0;
		$max = self::max_statement();
		while ( true ) {
			$read = (int) $this->state['pos'] + strlen( $buffer );
			$eof = $read >= $this->length;
			$found = $scan->next( $buffer, $at, $eof );
			if ( null === $found ) {
				if ( $eof ) {
					if ( '' !== trim( $scan->pending( $buffer, $at ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del respaldo de Backuply termina con una sentencia incompleta: el archivo está truncado o dañado.' );
					break;
				}
				if ( strlen( $buffer ) - $at > $max ) throw new Digitalisimo_Backups_Permanent_Error( 'Una sentencia del SQL de Backuply (cerca del byte ' . ( (int) $this->state['pos'] + $at ) . ') mide más de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $max ) . ' y no cabe en la memoria de PHP. Importa este respaldo con WP-CLI (wp digitalisimo-backups backuply import), que puede tener más memoria.' );
				$chunk = fread( $this->in, (int) min( self::CHUNK, $this->length - $read ) );
				if ( false === $chunk || '' === $chunk ) throw new Exception( 'No se pudo leer el SQL de Backuply en el área de trabajo.' );
				$buffer .= $chunk;
				continue;
			}
			list( $statement, $end ) = $found;
			if ( '' !== $statement ) $this->handle( $statement );
			$this->state['statements']++;
			$handled++;
			// Frontera de sentencia: lo leído ya no hace falta y el punto de control puede quedarse aquí.
			$this->state['pos'] = (int) $this->state['pos'] + $end;
			$buffer = (string) substr( $buffer, $end );
			$at = 0;
			$scan->reset();
			if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $handled ) ) {
				$this->state['out'] = $this->flush();
				return false;
			}
		}
		if ( ! $this->state['tables'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del respaldo de Backuply no trae ninguna tabla.' );
		$this->write( self::FOOTER );
		$this->state['pos'] = $this->length;
		$this->state['out'] = $this->flush();
		$this->state['done'] = true;
		return true;
	}

	/** Bytes ya escritos y a salvo en el disco. */
	private function flush() {
		if ( ! fflush( $this->out ) ) throw new Exception( 'No se pudo escribir el SQL convertido; revisa el espacio libre del almacén privado.' );
		if ( function_exists( 'fsync' ) ) @fsync( $this->out );
		return (int) ftell( $this->out );
	}
	private function write( $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $this->out, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir el SQL convertido; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
	}
	private function note( $key ) { $this->state['notes'][ $key ] = (int) ( $this->state['notes'][ $key ] ?? 0 ) + 1; }
	private static function reject( $why, $sql ) {
		$excerpt = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', substr( (string) $sql, 0, 80 ) );
		throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del respaldo de Backuply trae una sentencia que no se puede importar (' . $why . '): «' . $excerpt . ( strlen( (string) $sql ) > 80 ? '…' : '' ) . '».' );
	}

	/** Una sentencia completa, sin sus comentarios «--», «#» ni «/* *\/» (los ejecutables «/*!» se conservan). */
	private function handle( $sql ) {
		if ( self::ROUTINE === $sql ) { $this->note( 'routines' ); return; }
		if ( '!' === ( $sql[0] ?? '' ) ) {
			// Lo que el lexer marca como «comentario ejecutable» al principio: sólo los SET de sesión del volcado.
			if ( preg_match( '/^!(?:M!)?[0-9]{5,6}\s+SET\s/i', $sql ) && false === strpos( substr( $sql, 1 ), '/*' ) ) { $this->note( 'session' ); return; }
			self::reject( 'comentario ejecutable no reconocido', $sql );
		}
		$t = new Digitalisimo_Backups_Backuply_Sql_Tokens( $sql );
		$first = $t->word();
		switch ( $first ) {
			case 'SET': $this->note( 'session' ); return;
			case 'LOCK':
			case 'UNLOCK':
			case 'START':
			case 'COMMIT': return;
			case 'DROP':
				$what = $t->word();
				if ( 'TABLE' === $what ) {
					if ( 'IF' === $t->word() && 'EXISTS' === $t->word() ) { $table = $t->ident(); if ( null !== $table && $t->end() ) return; }
					self::reject( 'DROP TABLE no reconocido', $sql );
				}
				if ( 'VIEW' === $what ) return; // La vista se cuenta en su CREATE.
				if ( in_array( $what, array( 'TRIGGER', 'EVENT', 'FUNCTION', 'PROCEDURE' ), true ) ) return; // La rutina se cuenta en su CREATE (entre DELIMITER).
				self::reject( 'DROP no reconocido', $sql );
				// No sigue.
			case 'CREATE':
				if ( 'TABLE' === $t->peek_word() ) { $t->word(); $this->create( $t, $sql ); return; }
				while ( null !== ( $word = $t->any_word() ) ) {
					if ( 'VIEW' === $word ) { $this->note( 'views' ); return; }
					if ( in_array( $word, array( 'TRIGGER', 'EVENT', 'FUNCTION', 'PROCEDURE' ), true ) ) { $this->note( 'routines' ); return; }
					if ( 'TABLE' === $word ) break;
				}
				self::reject( 'sólo se importan tablas', $sql );
				// No sigue.
			case 'ALTER':
				if ( 'TABLE' !== $t->word() || null === $t->ident() ) self::reject( 'ALTER no reconocido', $sql );
				$action = $t->word();
				if ( 'ADD' === $action && in_array( $t->peek_word(), array( 'CONSTRAINT', 'FOREIGN' ), true ) ) { $this->note( 'constraints' ); return; }
				if ( in_array( $action, array( 'DISABLE', 'ENABLE' ), true ) && 'KEYS' === $t->word() && $t->end() ) return;
				self::reject( 'ALTER TABLE sólo puede añadir claves foráneas', $sql );
				// No sigue.
			case 'INSERT':
				$this->insert( $sql );
				return;
			case 'DELIMITER':
				// Lo resuelve el lexer; uno suelto aquí es un volcado que no es el de Backuply.
				self::reject( 'DELIMITER fuera de lugar', $sql );
		}
		self::reject( 'tipo de sentencia no admitido', $sql );
	}

	/** CREATE TABLE: anota las columnas (lo que el INSERT de Backuply no nombra) y la reescribe con su DROP delante. */
	private function create( Digitalisimo_Backups_Backuply_Sql_Tokens $t, $sql ) {
		$table = $t->ident();
		if ( null === $table || $t->punct( '.' ) ) self::reject( 'la tabla debe ir entre comillas invertidas y sin otra base de datos', $sql );
		if ( ! self::valid_table( $table ) ) self::reject( 'nombre de tabla no válido', $sql );
		if ( isset( $this->state['tables'][ $table ] ) ) self::reject( 'la tabla ' . $table . ' se crea dos veces', $sql );
		if ( ! $t->punct( '(' ) ) self::reject( 'las formas LIKE o SELECT de CREATE TABLE no se admiten', $sql );
		$columns = $t->columns();
		if ( null === $columns || ! $columns ) self::reject( 'definición de columnas no reconocida', $sql );
		$drop = 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $table ) . '`';
		// El contador AUTO_INCREMENT que Backuply añade tras la definición sobra: MySQL lo recalcula al insertar.
		$create = rtrim( substr( $sql, 0, $t->position() ) . preg_replace( '/\s*\bAUTO_INCREMENT\s*=\s*[0-9]+/i', '', substr( $sql, $t->position() ) ) );
		try {
			$this->guard->check( $drop );
			$this->guard->check( $create );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
		}
		$this->state['tables'][ $table ] = $columns;
		$this->state['order'][] = $table;
		$this->write( $drop . ";\n" . $create . ";\n" );
	}
	private static function valid_table( $table ) { return '' !== $table && strlen( $table ) <= 64 && false === strpos( $table, "\0" ) && false === strpos( $table, '/' ) && false === strpos( $table, '\\' ) && false === strpos( $table, '.' ); }

	/** INSERT de Backuply (varias filas, sin columnas): un INSERT por fila con columnas y cadenas, como el exportador. */
	private function insert( $sql ) {
		$p = new Digitalisimo_Backups_Backuply_Sql_Tokens( $sql );
		$p->word();
		if ( 'IGNORE' === $p->peek_word() ) $p->word();
		if ( 'INTO' !== $p->word() ) self::reject( 'INSERT no reconocido', $sql );
		$table = $p->ident();
		if ( null === $table || $p->punct( '.' ) ) self::reject( 'la tabla debe ir entre comillas invertidas y sin otra base de datos', $sql );
		if ( ! isset( $this->state['tables'][ $table ] ) ) self::reject( 'INSERT en una tabla que el volcado no creó', $sql );
		$columns = $this->state['tables'][ $table ];
		if ( $p->punct( '(' ) ) {
			$named = array();
			do {
				$column = $p->ident();
				if ( null === $column ) self::reject( 'columna no reconocida', $sql );
				$named[] = $column;
			} while ( $p->punct( ',' ) );
			if ( ! $p->punct( ')' ) ) self::reject( 'lista de columnas no reconocida', $sql );
			foreach ( $named as $column ) if ( ! in_array( $column, $columns, true ) ) self::reject( 'la columna ' . $column . ' no existe en ' . $table, $sql );
			$columns = $named;
		}
		$keyword = $p->word();
		if ( 'VALUES' !== $keyword && 'VALUE' !== $keyword ) self::reject( 'INSERT sólo admite VALUES con valores literales', $sql );
		$head = 'INSERT INTO `' . str_replace( '`', '``', $table ) . '` (' . implode( ',', array_map( function( $column ) { return '`' . str_replace( '`', '``', $column ) . '`'; }, $columns ) ) . ') VALUES (';
		$secret = self::secret_column( $table, $columns );
		$buffer = '';
		do {
			if ( ! $p->punct( '(' ) ) self::reject( 'se esperaba «(»', $sql );
			$values = array();
			do {
				$value = $p->value();
				if ( false === $value ) self::reject( 'INSERT sólo admite cadenas, números o NULL', $sql );
				$values[] = $value;
			} while ( $p->punct( ',' ) );
			if ( ! $p->punct( ')' ) ) self::reject( 'se esperaba «)»', $sql );
			if ( count( $values ) !== count( $columns ) ) self::reject( 'una fila de ' . $table . ' tiene ' . count( $values ) . ' valores y la tabla ' . count( $columns ) . ' columnas', $sql );
			if ( null !== $secret && 0 === strpos( (string) $values[ $secret ], Digitalisimo_Backups_Keys::SECRET_PREFIX ) ) { $this->note( 'secrets' ); continue; }
			$buffer .= $head . implode( ',', array_map( array( 'Digitalisimo_Backups', 'sql_value' ), $values ) ) . ");\n";
			$this->state['rows']++;
			if ( strlen( $buffer ) >= 262144 ) { $this->write( $buffer ); $buffer = ''; }
		} while ( $p->punct( ',' ) );
		if ( ! $p->end() ) self::reject( 'texto de más al final', $sql );
		if ( '' !== $buffer ) $this->write( $buffer );
	}
	/** Columna con el nombre de la opción en `*options` y `*sitemeta` (para no importar las claves de Backups del origen). */
	private static function secret_column( $table, array $columns ) {
		if ( preg_match( '/options$/D', $table ) ) $name = 'option_name';
		elseif ( preg_match( '/sitemeta$/D', $table ) ) $name = 'meta_key';
		else return null;
		$index = array_search( $name, $columns, true );
		return false === $index ? null : (int) $index;
	}
}

/**
 * Corta el SQL de Backuply en sentencias. Entiende comillas ' " ` con barra invertida y con la comilla duplicada, los
 * comentarios «-- », «#» y «/* *\/» (que quita) y los ejecutables «/*!» (que conserva), y `DELIMITER`: lo que va entre
 * un `DELIMITER` distinto de «;» y el `DELIMITER ;` siguiente (disparadores, eventos y rutinas) se salta entero.
 * Trabaja sobre un búfer que crece: guarda dónde se quedó y no vuelve a leer lo ya visto.
 */
final class Digitalisimo_Backups_Backuply_Sql_Lexer {
	private $i = 0;
	private $mode = 0;
	private $clean = '';
	private $segment = 0;
	private $started = false;

	public function reset() {
		$this->i = 0;
		$this->mode = 0;
		$this->clean = '';
		$this->segment = 0;
		$this->started = false;
	}
	/** Lo que queda sin cerrar al final (para saber si el SQL termina a medias). */
	public function pending( $buffer, $at ) { return 4 === $this->mode || 5 === $this->mode ? $this->clean : $this->clean . substr( $buffer, $this->segment ); }

	/**
	 * Siguiente sentencia de `$buffer`: array( texto sin comentarios, posición justo después de su fin ) o null si hace
	 * falta leer más. Con `$eof`, el final del búfer cierra un comentario de línea.
	 */
	public function next( $buffer, $at, $eof ) {
		$n = strlen( $buffer );
		$i = max( $this->i, $at );
		while ( $i < $n ) {
			$c = $buffer[ $i ];
			switch ( $this->mode ) {
				case 0:
					if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c ) { $i++; if ( ! $this->started ) $this->segment = $i; break; }
					// Comentarios: no empiezan la sentencia (un DELIMITER tras un comentario sigue reconociéndose).
					$comment = 0;
					if ( '#' === $c ) $comment = 4;
					elseif ( '-' === $c ) {
						if ( $i + 1 >= $n || ( '-' === $buffer[ $i + 1 ] && $i + 2 >= $n ) ) { if ( ! $eof ) { $this->i = $i; return null; } }
						if ( '-' === ( $buffer[ $i + 1 ] ?? '' ) && ( $i + 2 >= $n || ord( $buffer[ $i + 2 ] ) <= 32 ) ) $comment = 4;
					} elseif ( '/' === $c ) {
						if ( ( $i + 1 >= $n || ( '*' === $buffer[ $i + 1 ] && $i + 2 >= $n ) ) && ! $eof ) { $this->i = $i; return null; }
						if ( '*' === ( $buffer[ $i + 1 ] ?? '' ) && '!' !== ( $buffer[ $i + 2 ] ?? '' ) ) $comment = 5;
					}
					if ( $comment ) {
						$this->clean .= substr( $buffer, $this->segment, $i - $this->segment ) . ' ';
						$this->mode = $comment;
						$i += 4 === $comment ? 1 : 2;
						break;
					}
					if ( ! $this->started && ( 'D' === $c || 'd' === $c ) ) {
						$line_end = strpos( $buffer, "\n", $i );
						if ( false === $line_end && ! $eof ) { $this->i = $i; return null; }
						$line = false === $line_end ? substr( $buffer, $i ) : substr( $buffer, $i, $line_end - $i );
						if ( preg_match( '/^DELIMITER\s+(\S+)\s*$/i', rtrim( $line, "\r" ), $m ) ) {
							$after = false === $line_end ? $n : $line_end + 1;
							if ( ';' === $m[1] ) { $this->i = $after; $this->segment = $after; return array( '', $after ); }
							// Rutina, disparador o evento: hasta la línea `DELIMITER ;` (se cuenta en su CREATE).
							if ( ! preg_match( '/\n[ \t]*DELIMITER[ \t]+;[ \t]*\r?(?:\n|$)/i', $buffer, $close, PREG_OFFSET_CAPTURE, $after ) ) {
								if ( ! $eof ) { $this->i = $i; return null; }
								throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del respaldo de Backuply abre un DELIMITER que nunca se cierra.' );
							}
							$body = substr( $buffer, $after, $close[0][1] - $after );
							$end = $close[0][1] + strlen( $close[0][0] );
							$this->i = $end;
							$this->segment = $end;
							return array( preg_match( '/\bCREATE\b/i', $body ) ? Digitalisimo_Backups_Backuply_Sql::ROUTINE : '', $end );
						}
					}
					$this->started = true;
					if ( "'" === $c ) { $this->mode = 1; $i++; break; }
					if ( '"' === $c ) { $this->mode = 2; $i++; break; }
					if ( '`' === $c ) { $this->mode = 3; $i++; break; }
					if ( '/' === $c && '*' === ( $buffer[ $i + 1 ] ?? '' ) ) { $this->mode = 6; $i += 3; break; } // «/*!»: se conserva.
					if ( ';' === $c ) {
						$statement = trim( $this->clean . substr( $buffer, $this->segment, $i - $this->segment ) );
						// Un ejecutable que abre la sentencia se marca con «!» para que el traductor lo reconozca.
						if ( 0 === strpos( $statement, '/*!' ) && '*/' === substr( $statement, -2 ) && 1 === substr_count( $statement, '*/' ) ) $statement = '!' . trim( substr( $statement, 3, -2 ) );
						$this->i = $i + 1;
						return array( $statement, $i + 1 );
					}
					$i++;
					break;
				case 1:
				case 2:
				case 3:
					$quote = 1 === $this->mode ? "'" : ( 2 === $this->mode ? '"' : '`' );
					$run = 3 === $this->mode ? strcspn( $buffer, '`', $i ) : strcspn( $buffer, '\\' . $quote, $i );
					$i += $run;
					if ( $i >= $n ) break;
					if ( '\\' === $buffer[ $i ] ) {
						if ( $i + 1 >= $n ) { $this->i = $i; return null; }
						$i += 2;
						break;
					}
					// Comilla: cierra, salvo que sea la duplicada ('' dentro de una cadena).
					if ( $i + 1 >= $n && ! $eof ) { $this->i = $i; return null; }
					if ( $i + 1 < $n && $quote === $buffer[ $i + 1 ] ) { $i += 2; break; }
					$this->mode = 0;
					$i++;
					break;
				case 4:
					$end = strpos( $buffer, "\n", $i );
					if ( false === $end ) { $i = $n; break; }
					$i = $end + 1;
					$this->segment = $i;
					$this->mode = 0;
					break;
				case 5:
				case 6:
					$end = strpos( $buffer, '*/', $i );
					if ( false === $end ) { $i = max( $i, $n - 1 ); $this->i = $i; return null; }
					$i = $end + 2;
					if ( 5 === $this->mode ) $this->segment = $i;
					$this->mode = 0;
					break;
			}
		}
		$this->i = $i;
		if ( $eof && 4 === $this->mode ) { $this->mode = 0; $this->segment = $n; }
		return null;
	}
}

/**
 * Lectura de una sentencia ya cortada (sin comentarios): palabras, identificadores entre comillas invertidas, signos y
 * valores literales de un INSERT. No interpreta nada más.
 */
final class Digitalisimo_Backups_Backuply_Sql_Tokens {
	private $s;
	private $i = 0;
	private $n;
	public function __construct( $sql ) { $this->s = (string) $sql; $this->n = strlen( $this->s ); }
	private function space() { $this->i += strspn( $this->s, " \t\r\n", $this->i ); }
	/** Siguiente palabra en mayúsculas, o null si lo que sigue no es una palabra. */
	public function word() {
		$this->space();
		if ( ! preg_match( '/\G[A-Za-z_][A-Za-z0-9_]*/', $this->s, $m, 0, $this->i ) ) return null;
		$this->i += strlen( $m[0] );
		return strtoupper( $m[0] );
	}
	public function peek_word() { $at = $this->i; $word = $this->word(); $this->i = $at; return $word; }
	/** Siguiente palabra en cualquier lugar (salta signos, cadenas e identificadores); null al final. */
	public function any_word() {
		while ( $this->i < $this->n ) {
			$word = $this->word();
			if ( null !== $word ) return $word;
			if ( $this->i >= $this->n ) return null;
			$c = $this->s[ $this->i ];
			if ( "'" === $c || '"' === $c ) { if ( false === $this->string() ) return null; continue; }
			if ( '`' === $c ) { if ( null === $this->ident() ) return null; continue; }
			$this->i++;
		}
		return null;
	}
	public function ident() {
		$this->space();
		if ( '`' !== ( $this->s[ $this->i ] ?? '' ) ) return null;
		$name = '';
		$i = $this->i + 1;
		while ( $i < $this->n ) {
			$end = strpos( $this->s, '`', $i );
			if ( false === $end ) return null;
			$name .= substr( $this->s, $i, $end - $i );
			if ( '`' === ( $this->s[ $end + 1 ] ?? '' ) ) { $name .= '`'; $i = $end + 2; continue; }
			$this->i = $end + 1;
			return $name;
		}
		return null;
	}
	public function punct( $char ) {
		$this->space();
		if ( $char !== ( $this->s[ $this->i ] ?? '' ) ) return false;
		$this->i++;
		return true;
	}
	public function end() { $this->space(); return $this->i >= $this->n; }
	public function position() { return $this->i; }

	/**
	 * Columnas de un CREATE TABLE ya abierto: elementos de primer nivel que empiezan por un identificador. null si la
	 * definición no cierra.
	 */
	public function columns() {
		$columns = array();
		$depth = 1;
		$fresh = true;
		while ( $this->i < $this->n ) {
			$this->space();
			if ( $this->i >= $this->n ) break;
			$c = $this->s[ $this->i ];
			if ( $fresh && '`' === $c && 1 === $depth ) {
				$name = $this->ident();
				if ( null === $name ) return null;
				$columns[] = $name;
				$fresh = false;
				continue;
			}
			$fresh = false;
			if ( "'" === $c || '"' === $c ) { if ( false === $this->string() ) return null; continue; }
			if ( '`' === $c ) { if ( null === $this->ident() ) return null; continue; }
			$this->i++;
			if ( '(' === $c ) $depth++;
			elseif ( ')' === $c ) { $depth--; if ( 0 === $depth ) return $columns; }
			elseif ( ',' === $c && 1 === $depth ) $fresh = true;
		}
		return null;
	}

	/**
	 * Valor literal de una fila: la cadena decodificada (escapes de MySQL y comilla duplicada), el número como texto,
	 * null para NULL o false si no es un literal admitido.
	 */
	public function value() {
		$this->space();
		$c = $this->s[ $this->i ] ?? '';
		if ( "'" === $c || '"' === $c ) return $this->string();
		if ( preg_match( '/\GNULL\b/i', $this->s, $m, 0, $this->i ) ) { $this->i += 4; return null; }
		if ( preg_match( '/\G[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?(?![A-Za-z0-9_])/', $this->s, $m, 0, $this->i ) ) { $this->i += strlen( $m[0] ); return $m[0]; }
		return false;
	}
	/** Cadena entre ' o ": barra invertida como en MySQL y la comilla duplicada. false si no cierra. */
	private function string() {
		$quote = $this->s[ $this->i ];
		$out = '';
		$i = $this->i + 1;
		$map = array( '0' => "\0", "'" => "'", '"' => '"', 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", '\\' => '\\', '%' => '\\%', '_' => '\\_' );
		while ( $i < $this->n ) {
			$run = strcspn( $this->s, '\\' . $quote, $i );
			$out .= substr( $this->s, $i, $run );
			$i += $run;
			if ( $i >= $this->n ) return false;
			if ( '\\' === $this->s[ $i ] ) {
				if ( $i + 1 >= $this->n ) return false;
				$next = $this->s[ $i + 1 ];
				$out .= $map[ $next ] ?? $next;
				$i += 2;
				continue;
			}
			if ( $quote === ( $this->s[ $i + 1 ] ?? '' ) ) { $out .= $quote; $i += 2; continue; }
			$this->i = $i + 1;
			return $out;
		}
		return false;
	}
}
