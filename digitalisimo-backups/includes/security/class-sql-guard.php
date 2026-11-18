<?php
defined( 'ABSPATH' ) || exit;

/**
 * Divide el SQL de un respaldo en sentencias, leyendo por fragmentos. Es el único divisor: el análisis previo y la
 * importación ven exactamente las mismas sentencias. Conserva la semántica que tuvo siempre la importación: comillas
 * ' " ` con escape por barra invertida, «;» fuera de comillas termina la sentencia y una sentencia que empieza por
 * «--» no se ejecuta.
 */
final class Digitalisimo_Backups_Sql_Reader {
	private $current = '';
	private $quote = '';
	private $escaped = false;
	/** Bytes ya leídos y, de cada sentencia que devolvió el último feed(), la posición justo después de su «;». */
	private $fed = 0;
	private $ends = array();

	public function feed( $chunk ) {
		$out = array();
		$this->ends = array();
		$length = strlen( $chunk );
		$i = 0;
		while ( $i < $length ) {
			if ( $this->escaped ) { $this->current .= $chunk[ $i++ ]; $this->escaped = false; continue; }
			if ( '' !== $this->quote ) {
				$run = strcspn( $chunk, '\\' . $this->quote, $i );
				$this->current .= substr( $chunk, $i, $run );
				$i += $run;
				if ( $i >= $length ) break;
				$char = $chunk[ $i++ ];
				$this->current .= $char;
				if ( '\\' === $char ) $this->escaped = true; else $this->quote = '';
				continue;
			}
			$run = strcspn( $chunk, "'\"`;", $i );
			$this->current .= substr( $chunk, $i, $run );
			$i += $run;
			if ( $i >= $length ) break;
			$char = $chunk[ $i++ ];
			if ( ';' === $char ) {
				$statement = trim( $this->current );
				if ( '' !== $statement && 0 !== strpos( $statement, '--' ) ) { $out[] = $statement; $this->ends[] = $this->fed + $i; }
				$this->current = '';
				continue;
			}
			$this->quote = $char;
			$this->current .= $char;
		}
		$this->fed += $length;
		return $out;
	}
	/**
	 * Posiciones (desde el primer byte leído) justo después del «;» de cada sentencia del último feed(). Una
	 * restauración por tramos sigue desde una de ellas con un lector nuevo: tras un «;» fuera de comillas no queda estado.
	 */
	public function ends() { return $this->ends; }
	public function finish() {
		if ( '' !== $this->quote ) throw new Exception( 'La base de datos del ZIP termina con una cadena SQL incompleta.' );
		$statement = trim( $this->current );
		$this->current = '';
		return '' !== $statement && 0 !== strpos( $statement, '--' ) ? array( $statement ) : array();
	}
}

/**
 * Lista blanca de sentencias. Dialecto «exporter»: lo que escribió cualquier versión de DIGITALÍSIMO Backups (SET NAMES,
 * SET foreign_key_checks, DROP TABLE IF EXISTS, CREATE TABLE e INSERT INTO con cadenas o NULL) sobre tablas del
 * manifiesto. Dialecto «mysqldump»: además lo que escriben UpdraftPlus y mysqldump, siempre como no confiable. En un
 * archivo no confiable CREATE TABLE no puede usar DATA/INDEX DIRECTORY, CONNECTION, UNION, motores remotos ni las
 * formas LIKE o SELECT. Se ejecuta dos veces: en seco antes de la copia previa y por sentencia al importar.
 */
final class Digitalisimo_Backups_Sql_Guard {
	const EXPORTER = 'exporter';
	const MYSQLDUMP = 'mysqldump';
	const SESSION_VARIABLES = array( 'FOREIGN_KEY_CHECKS', 'UNIQUE_CHECKS', 'SQL_MODE', 'SQL_NOTES', 'TIME_ZONE', 'CHARACTER_SET_CLIENT', 'CHARACTER_SET_RESULTS', 'COLLATION_CONNECTION', 'AUTOCOMMIT', 'SQL_QUOTE_SHOW_CREATE' );
	const SAFE_CHARSETS = array( 'UTF8MB4', 'UTF8', 'UTF8MB3', 'BINARY', 'LATIN1', 'ASCII' );
	const SENSITIVE = array( 'SQL_MODE', 'CHARACTER_SET_CLIENT', 'CHARACTER_SET_RESULTS', 'COLLATION_CONNECTION' );
	const REMOTE_ENGINES = array( 'FEDERATED', 'CONNECT', 'SPIDER', 'MERGE', 'MRG_MYISAM' );
	const CREATE_DENIED = array( 'DIRECTORY', 'CONNECTION', 'UNION', 'SELECT', 'LOAD_FILE', 'INTO', 'OUTFILE', 'DUMPFILE' );
	private $dialect;
	private $trusted;
	private $tables;
	private $prefix;
	private $count = 0;
	private $safe_vars = array();
	private $s = '';
	private $i = 0;
	private $n = 0;
	private $kind = '';
	private $excerpt_end = 0;
	/** Tablas que cambian de nombre al importar (nombre del respaldo => nombre nuevo) y tramos que cambian en la sentencia. */
	private $renames = array();
	private $spans = array();
	private $nested = false;
	/** Gancho que recibe cada INSERT comprobado (tabla, columnas y posición de cada literal) y devuelve tramos que cambian. */
	private $insert_hook = null;
	private $insert = null;
	/** Tablas que se comprueban pero no se ejecutan, y si la sentencia en curso no se ejecuta. */
	private $skips = array();
	private $skip_current = false;
	/** Con el renombrado en seco (verify y la pasada previa a la restauración), lo que no se puede renombrar se anota. */
	private $rename_strict = true;
	private $rename_problems = array();
	/** Compatibilidad entre servidores (Digitalisimo_Backups_Db_Compat): lo anotado, la sustitución y las vistas. */
	private $collecting = false;
	private $found = array();
	private $four_byte = false;
	private $compat = array();
	private $views_mode = '';
	private $declared_views = array();
	/** Con view_sources(): las tablas y vistas que puede leer una vista de un archivo sin firma (null: las del manifiesto). */
	private $view_sources = null;
	private $view_current = null;
	private $current_table = '';

	/** $tables: lista del manifiesto v3; sin ella, $prefix (si no es vacío) acota las tablas. */
	public function __construct( $dialect, $trusted, $tables = null, $prefix = '' ) {
		$this->dialect = self::MYSQLDUMP === $dialect ? self::MYSQLDUMP : self::EXPORTER;
		$this->trusted = (bool) $trusted && self::EXPORTER === $this->dialect;
		$this->tables = is_array( $tables ) ? array_fill_keys( array_map( 'strval', $tables ), true ) : null;
		$this->prefix = (string) $prefix;
	}
	/** Reglas de un respaldo propio según su manifiesto y su nivel de confianza. */
	public static function for_backup( $manifest, $trusted ) {
		$v3 = is_array( $manifest ) && 'digitalisimo-backup/v3' === ( $manifest['format'] ?? '' ) && is_array( $manifest['database_tables'] ?? null );
		return new self( self::EXPORTER, $trusted, $v3 ? $manifest['database_tables'] : null, $v3 || $trusted ? '' : (string) ( $GLOBALS['wpdb']->base_prefix ?? '' ) );
	}

	/**
	 * Al importar, las tablas de $map (nombre en el respaldo => nombre nuevo) se crean, vacían y llenan con su nombre
	 * nuevo, también en `REFERENCES` de una clave foránea. Se cambia el identificador exacto que leyó el análisis, nunca
	 * por búsqueda de texto. Una vista u otro objeto que no es una tabla, o una tabla dentro de un comentario
	 * ejecutable, se rechazan: no se puede asegurar su nombre nuevo.
	 */
	public function rename_tables( array $map, $strict = true ) {
		foreach ( $map as $from => $to ) if ( (string) $from !== (string) $to ) $this->renames[ (string) $from ] = (string) $to;
		$this->rename_strict = (bool) $strict;
		return $this;
	}
	/** Sin $strict (pasada en seco): lo que no se puede renombrar, en el orden en que apareció. */
	public function rename_problems() { return array_values( array_unique( $this->rename_problems ) ); }
	/** Estas tablas se comprueban como las demás, pero sus sentencias no se ejecutan (ni DROP, ni CREATE, ni INSERT). */
	public function skip_tables( array $tables ) {
		foreach ( $tables as $table ) $this->skips[ (string) $table ] = true;
		return $this;
	}
	/** La última sentencia comprobada no se ejecuta (tabla omitida o fila que el gancho descartó). */
	public function skipped() { return $this->skip_current; }
	private function cannot_rename( $why ) {
		if ( $this->rename_strict ) $this->reject( $why );
		$this->rename_problems[] = $why;
	}
	/**
	 * $hook( array $insert, string $sql ) recibe cada INSERT permitido: `table` (nombre en el respaldo), `columns` (o null
	 * sin lista de columnas) y `rows`, una lista por fila con, por valor, array( type => 'str', quote, start, end ) (el
	 * contenido entre comillas, tal como está en $sql), null para NULL o array( type => 'raw'|'other' ). Devuelve tramos
	 * array( inicio, fin, texto ) que reemplazan el contenido de esos literales en la sentencia que se ejecuta, o false
	 * para no ejecutarla.
	 */
	public function on_insert( callable $hook ) {
		$this->insert_hook = $hook;
		return $this;
	}
	/** La última sentencia comprobada, con los nombres de tabla y los literales cambiados ('' si no se ejecuta). */
	public function rewritten() {
		if ( $this->skip_current ) return '';
		if ( ! $this->spans ) return $this->s;
		$spans = $this->spans;
		usort( $spans, function( $a, $b ) { return $a[0] - $b[0]; } );
		// Una sola pasada: los tramos se copian en orden, sin rehacer la sentencia entera por cada uno.
		$parts = array();
		$at = 0;
		foreach ( $spans as $span ) {
			if ( $span[0] < $at || $span[0] > $span[1] ) throw new Exception( 'Cambios superpuestos en una sentencia del respaldo.' );
			$parts[] = substr( $this->s, $at, $span[0] - $at );
			$parts[] = $span[2];
			$at = $span[1];
		}
		$parts[] = substr( $this->s, $at );
		return implode( '', $parts );
	}

	/** Recorre el SQL completo del ZIP sin ejecutar nada. */
	public function scan_zip( $zip ) {
		$stream = $zip->getStream( 'database.sql' );
		if ( ! $stream ) throw new Exception( 'No se pudo leer la base de datos del ZIP.' );
		try {
			return $this->scan( function() use ( $stream ) {
				if ( feof( $stream ) ) return null;
				$chunk = fread( $stream, 65536 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'La base de datos del ZIP está truncada.' );
				return $chunk;
			} );
		} finally {
			fclose( $stream );
		}
	}
	/** $read devuelve un fragmento, o null al terminar. $run recibe cada sentencia permitida. */
	public function scan( $read, $run = null ) {
		$reader = new Digitalisimo_Backups_Sql_Reader();
		$executed = 0;
		while ( null !== ( $chunk = call_user_func( $read ) ) ) {
			foreach ( $reader->feed( $chunk ) as $statement ) if ( $this->check( $statement ) ) { if ( $run && ! $this->skip_current ) call_user_func( $run, $this->rewritten() ); $executed++; }
		}
		foreach ( $reader->finish() as $statement ) if ( $this->check( $statement ) ) { if ( $run && ! $this->skip_current ) call_user_func( $run, $this->rewritten() ); $executed++; }
		if ( ! $executed ) throw new Exception( 'La base de datos del respaldo está vacía.' );
		return $executed;
	}

	/** Una restauración por tramos sigue contando desde la sentencia en la que se quedó (para los mensajes de error). */
	public function start_at( $count ) {
		$this->count = max( 0, (int) $count );
		return $this;
	}
	public function count() { return $this->count; }
	/** true si la sentencia se ejecuta, false si sólo trae comentarios; excepción si no está permitida. */
	public function check( $sql ) {
		$this->count++;
		$this->s = (string) $sql;
		$this->i = 0;
		$this->n = strlen( $this->s );
		$this->kind = '';
		$this->excerpt_end = 80;
		$this->spans = array();
		$this->insert = null;
		$this->nested = false;
		$this->skip_current = false;
		$this->view_current = null;
		$this->current_table = '';
		try {
			$allowed = $this->statement();
			if ( $allowed && $this->collecting ) $this->collect_statement();
			if ( $allowed && ! $this->skip_current && $this->insert_hook && null !== $this->insert ) {
				$spans = call_user_func( $this->insert_hook, $this->insert, $this->s );
				if ( false === $spans ) $this->skip_current = true;
				else foreach ( (array) $spans as $span ) $this->spans[] = $span;
			}
			return $allowed;
		} catch ( UnexpectedValueException $e ) {
			$excerpt = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', substr( $this->s, 0, min( 80, $this->excerpt_end ) ) );
			throw new Exception( 'La base de datos del respaldo trae una sentencia no permitida (sentencia ' . $this->count . ( $this->kind ? ', ' . $this->kind : '' ) . '): ' . $e->getMessage() . ' «' . $excerpt . ( $this->n > min( 80, $this->excerpt_end ) ? '…' : '' ) . '».' );
		}
	}
	private function reject( $why ) { throw new UnexpectedValueException( $why ); }

	private function statement() {
		$token = $this->next();
		if ( 'end' === $token[0] ) return false;
		if ( 'exec' === $token[0] ) {
			if ( self::MYSQLDUMP !== $this->dialect ) $this->reject( 'comentario ejecutable fuera de lo que escribe el exportador' );
			if ( self::unreachable( $token ) ) return $this->statement(); // Versión inalcanzable: es un comentario.
			$this->sub( $token[1], 'executable' );
			$this->end();
			return true;
		}
		if ( 'word' !== $token[0] ) $this->reject( 'no empieza por una instrucción' );
		$this->kind = $token[1];
		switch ( $token[1] ) {
			case 'SET': $this->set(); break;
			case 'DROP': $this->words( array( 'TABLE' ) ); $this->kind = 'DROP TABLE'; $this->words( array( 'IF', 'EXISTS' ) ); $this->table(); $this->end(); break;
			case 'CREATE': $this->create(); break;
			case 'INSERT': $this->insert(); break;
			default:
				if ( self::MYSQLDUMP === $this->dialect && $this->dump_statement( $token[1] ) ) break;
				$this->reject( 'tipo de sentencia no permitido en un respaldo' );
		}
		return true;
	}
	/**
	 * Un comentario ejecutable que ningún servidor ejecuta. /*M! sólo lo lee MariaDB, que entiende seis dígitos: desde
	 * 900000 es inalcanzable. /*! también lo lee MySQL, que puede tomar sólo los cinco primeros dígitos (900000 sería
	 * 9.0.0 seguido de «0 …»): sólo es inalcanzable si también lo son esos cinco (999990 a 999999).
	 */
	private static function unreachable( $token ) { return ! empty( $token[3] ) ? $token[2] >= 900000 : $token[2] >= 999990; }
	/** Contenido de un /*!NNNNN … *\/ del volcado: sólo SET o ALTER TABLE … KEYS. */
	private function sub( $content, $mode ) {
		$saved = array( $this->s, $this->i, $this->n );
		$this->s = $content;
		$this->i = 0;
		$this->n = strlen( $content );
		$this->nested = true;
		$token = $this->next();
		if ( 'word' === $token[0] && 'SET' === $token[1] ) { $this->kind = 'SET'; $this->set(); }
		elseif ( 'word' === $token[0] && 'ALTER' === $token[1] ) { $this->kind = 'ALTER TABLE'; $this->alter_keys(); }
		else $this->reject( 'comentario ejecutable no permitido' );
		$this->nested = false;
		list( $this->s, $this->i, $this->n ) = $saved;
	}
	private function dump_statement( $word ) {
		switch ( $word ) {
			case 'LOCK':
				$this->words( array( 'TABLES' ) );
				do {
					$this->table();
					$mode = $this->next();
					if ( 'word' !== $mode[0] || ! in_array( $mode[1], array( 'READ', 'WRITE' ), true ) ) $this->reject( 'bloqueo no reconocido' );
					if ( 'READ' === $mode[1] && $this->peek_word( 'LOCAL' ) ) $this->next();
				} while ( $this->accept_punct( ',' ) );
				$this->end();
				return true;
			case 'UNLOCK': $this->words( array( 'TABLES' ) ); $this->end(); return true;
			case 'ALTER': $this->kind = 'ALTER TABLE'; $this->alter_keys(); return true;
			case 'START': $this->words( array( 'TRANSACTION' ) ); $this->end(); return true;
			case 'COMMIT': $this->end(); return true;
		}
		return false;
	}
	private function alter_keys() {
		$this->words( array( 'TABLE' ) );
		$this->table();
		$action = $this->next();
		if ( 'word' !== $action[0] || ! in_array( $action[1], array( 'DISABLE', 'ENABLE' ), true ) ) $this->reject( 'ALTER TABLE sólo puede activar o desactivar índices' );
		$this->words( array( 'KEYS' ) );
		$this->end();
	}
	private function set() {
		$safe = self::EXPORTER === $this->dialect ? array( 'UTF8MB4', 'UTF8' ) : self::SAFE_CHARSETS;
		if ( $this->peek_word( 'NAMES' ) ) {
			$this->next();
			$charset = $this->next();
			if ( 'word' !== $charset[0] || ! in_array( $charset[1], $safe, true ) ) $this->reject( 'juego de caracteres no permitido' );
			$this->compat_value( 'charsets', $charset, $this->i );
			if ( $this->peek_word( 'COLLATE' ) ) { $this->next(); $collation = $this->next(); if ( 'word' !== $collation[0] || ! $this->safe_charset_prefix( $collation[1] ) ) $this->reject( 'intercalación no permitida' ); $this->compat_value( 'collations', $collation, $this->i ); }
			$this->end();
			return;
		}
		do {
			$target = $this->next();
			if ( self::MYSQLDUMP === $this->dialect && 'word' === $target[0] && in_array( $target[1], array( 'SESSION', 'LOCAL' ), true ) ) $target = $this->next();
			$user = '';
			if ( 'var' === $target[0] && 0 !== strpos( $target[1], '@@' ) ) {
				if ( self::MYSQLDUMP !== $this->dialect ) $this->reject( 'variable no permitida' );
				$user = $target[1];
				$name = '';
			} else {
				$name = 'var' === $target[0] ? preg_replace( '/^@@(?:SESSION\.|LOCAL\.)?/', '', $target[1] ) : ( 'word' === $target[0] ? $target[1] : '' );
				$allowed = self::EXPORTER === $this->dialect ? ( 'word' === $target[0] ? array( 'FOREIGN_KEY_CHECKS' ) : array() ) : self::SESSION_VARIABLES;
				if ( '' === $name || false !== strpos( $name, '.' ) || ! in_array( $name, $allowed, true ) ) $this->reject( 'variable no permitida' );
			}
			$this->expect_punct( '=' );
			$value = $this->next();
			if ( self::EXPORTER === $this->dialect ) {
				if ( 'num' !== $value[0] || ! in_array( $value[1], array( '0', '1' ), true ) ) $this->reject( 'valor no permitido' );
				continue;
			}
			if ( ! in_array( $value[0], array( 'str', 'num', 'word', 'var' ), true ) ) $this->reject( 'valor no permitido' );
			if ( 'var' === $value[0] && 0 === strpos( $value[1], '@@' ) && ! preg_match( '/^@@(?:SESSION\.|LOCAL\.|GLOBAL\.)?[A-Z0-9_]+$/D', $value[1] ) ) $this->reject( 'valor no permitido' );
			if ( '' !== $user ) { $this->safe_vars[ $user ] = 'var' === $value[0] && 0 === strpos( $value[1], '@@' ); continue; }
			// Lo que cambia cómo MySQL lee comillas y barras invertidas desincronizaría este análisis.
			if ( 'var' === $value[0] && 0 !== strpos( $value[1], '@@' ) ) { if ( in_array( $name, self::SENSITIVE, true ) && empty( $this->safe_vars[ $value[1] ] ) ) $this->reject( 'valor no permitido para ' . $name ); continue; }
			if ( 'SQL_MODE' === $name && ( 'str' !== $value[0] || preg_match( '/NO_BACKSLASH_ESCAPES|ANSI/i', substr( $this->s, $value[2], $value[3] - $value[2] ) ) ) ) $this->reject( 'modo SQL no permitido' );
			if ( in_array( $name, array( 'CHARACTER_SET_CLIENT', 'CHARACTER_SET_RESULTS' ), true ) && ( 'word' !== $value[0] || ! in_array( $value[1], $safe, true ) ) ) $this->reject( 'juego de caracteres no permitido' );
			if ( 'COLLATION_CONNECTION' === $name && ( 'word' !== $value[0] || ! $this->safe_charset_prefix( $value[1] ) ) ) $this->reject( 'intercalación no permitida' );
		} while ( self::MYSQLDUMP === $this->dialect && $this->accept_punct( ',' ) );
		$this->end();
	}
	private function safe_charset_prefix( $collation ) {
		foreach ( self::SAFE_CHARSETS as $charset ) if ( $collation === $charset || 0 === strpos( $collation, $charset . '_' ) ) return true;
		return false;
	}
	private function create() {
		$at = $this->i;
		$what = $this->next();
		if ( 'word' !== $what[0] || 'TABLE' !== $what[1] ) {
			// Una vista se comprueba y se deja para el final del cambio (Digitalisimo_Backups_Db_Compat): ni las tablas
			// temporales ni otro prefijo cambian a qué apunta.
			if ( '' !== $this->views_mode ) {
				$this->i = $at;
				if ( 'VIEW' === $this->peek_object() ) { $this->view(); return; }
				$this->next();
			}
			// Lo escribió SHOW CREATE TABLE de este servidor (por ejemplo, una vista). Con otro prefijo no se puede asegurar
			// a qué tablas apunta.
			if ( $this->trusted && $this->renames ) {
				$name = $this->peek_name();
				$this->cannot_rename( 'no se puede cambiar el prefijo de una vista u otro objeto que no es una tabla' . ( null === $name ? '' : ' («' . $name . '»)' ) );
			}
			if ( $this->trusted ) return;
			$this->reject( 'sólo se admite CREATE TABLE' );
		}
		$this->kind = 'CREATE TABLE';
		$table = $this->table();
		if ( $this->renames ) $this->references();
		if ( $this->collecting || $this->compat ) $this->compat_create( $table );
		if ( $this->trusted ) return;
		$open = $this->next();
		if ( 'punct' !== $open[0] || '(' !== $open[1] || $this->peek_word( 'LIKE' ) ) $this->reject( 'las formas LIKE o SELECT de CREATE TABLE no se admiten' );
		$this->create_body( false );
	}
	/** Recorre la definición sin interpretarla y rechaza las opciones que salen de la base de datos. */
	private function create_body( $inside_comment ) {
		$previous = '';
		while ( true ) {
			$token = $this->next();
			if ( 'end' === $token[0] ) return;
			if ( 'exec' === $token[0] ) {
				if ( $inside_comment ) $this->reject( 'comentario ejecutable anidado' );
				if ( self::unreachable( $token ) ) continue;
				if ( ! preg_match( '/^\s*(?:PARTITION\s+BY\b|INVISIBLE\s*$|DEFAULT\s+ENCRYPTION\b)/i', $token[1] ) ) $this->reject( 'comentario ejecutable no permitido en CREATE TABLE' );
				$saved = array( $this->s, $this->i, $this->n );
				$this->s = $token[1];
				$this->i = 0;
				$this->n = strlen( $token[1] );
				$this->create_body( true );
				list( $this->s, $this->i, $this->n ) = $saved;
				continue;
			}
			if ( 'var' === $token[0] ) $this->reject( 'variables en CREATE TABLE' );
			// Una clave foránea hacia otra base de datos (`REFERENCES otra.tabla`, con la base entre comillas invertidas o sin
			// ellas). Sólo tras REFERENCES: MariaDB escribe `DEFAULT nextval(\`base\`.\`secuencia\`)` con la base delante, y eso
			// no lee nada de otra base (las formas TABLE/VALUES/SELECT, que sí, se rechazan aparte).
			if ( 'REFERENCES' === $previous && in_array( $token[0], array( 'ident', 'word' ), true ) && $this->accept_punct( '.' ) ) $this->reject( 'otra base de datos dentro de CREATE TABLE no permitida en un archivo sin firma' );
			// MySQL 8.0.19+: `CREATE TABLE t (…) TABLE otra` y `… VALUES ROW(…)` llenan la tabla como CREATE … SELECT.
			// VALUES sólo vale en una partición (VALUES LESS THAN, VALUES IN).
			if ( 'word' === $token[0] && 'TABLE' === $token[1] ) $this->reject( 'la forma TABLE de CREATE TABLE no se admite en un archivo sin firma' );
			if ( 'word' === $token[0] && 'VALUES' === $token[1] && ! $this->peek_word( 'LESS' ) && ! $this->peek_word( 'IN' ) ) $this->reject( 'la forma VALUES de CREATE TABLE no se admite en un archivo sin firma' );
			// ENGINE admite un nombre o un texto: ENGINE='CONNECT' y ENGINE=`SPIDER` también eligen el motor.
			if ( in_array( $previous, array( 'ENGINE', 'TYPE' ), true ) && in_array( $token[0], array( 'str', 'ident' ), true ) ) {
				$engine = strtoupper( 'ident' === $token[0] ? $token[1] : substr( $this->s, $token[2], $token[3] - $token[2] ) );
				if ( ! preg_match( '/^[A-Z0-9_]+$/D', $engine ) || in_array( $engine, self::REMOTE_ENGINES, true ) ) $this->reject( 'motor ' . ( preg_match( '/^[A-Z0-9_]+$/D', $engine ) ? $engine : 'no reconocido' ) . ' no permitido en un archivo sin firma' );
			}
			if ( 'word' !== $token[0] ) { if ( 'punct' !== $token[0] || '=' !== $token[1] ) $previous = ''; continue; }
			if ( in_array( $token[1], self::CREATE_DENIED, true ) ) $this->reject( 'opción ' . $token[1] . ' no permitida en un archivo sin firma' );
			if ( in_array( $previous, array( 'ENGINE', 'TYPE' ), true ) && in_array( $token[1], self::REMOTE_ENGINES, true ) ) $this->reject( 'motor ' . $token[1] . ' no permitido en un archivo sin firma' );
			$previous = $token[1];
		}
	}
	/** Nombre de una vista u otro objeto (el identificador tras VIEW, TRIGGER…), sin mover la lectura; null si no lo hay. */
	private function peek_name() {
		$at = $this->i;
		$name = null;
		try {
			while ( true ) {
				$token = $this->next();
				if ( 'end' === $token[0] ) break;
				if ( 'word' === $token[0] && in_array( $token[1], array( 'VIEW', 'TRIGGER', 'FUNCTION', 'PROCEDURE', 'EVENT' ), true ) ) {
					$token = $this->next();
					if ( 'ident' === $token[0] ) $name = $token[1];
					break;
				}
			}
		} catch ( UnexpectedValueException $e ) {
			$name = null;
		}
		$this->i = $at;
		return $name;
	}
	/** Claves foráneas de un CREATE TABLE: `REFERENCES` a una tabla que cambia de nombre la apunta a su nombre nuevo. */
	private function references() {
		$at = $this->i;
		$expect = false;
		while ( true ) {
			$token = $this->next();
			if ( 'end' === $token[0] ) break;
			if ( 'exec' === $token[0] ) {
				if ( false !== stripos( $token[1], 'REFERENCES' ) ) $this->cannot_rename( 'clave foránea dentro de un comentario ejecutable' );
				continue;
			}
			if ( $expect && 'ident' === $token[0] && isset( $this->renames[ $token[1] ] ) ) $this->spans[] = array( $token[2], $token[3], '`' . str_replace( '`', '``', $this->renames[ $token[1] ] ) . '`' );
			$expect = 'word' === $token[0] && 'REFERENCES' === $token[1];
		}
		$this->i = $at;
	}
	private function insert() {
		if ( self::MYSQLDUMP === $this->dialect && $this->peek_word( 'IGNORE' ) ) $this->next();
		$this->words( array( 'INTO' ) );
		$this->excerpt_end = $this->i;
		$table = $this->table();
		$this->excerpt_end = $this->i;
		$record = null !== $this->insert_hook || $this->collecting;
		$columns = null;
		$rows = array();
		$token = $this->next();
		if ( 'punct' === $token[0] && '(' === $token[1] ) {
			$columns = array();
			do {
				$column = $this->next();
				if ( 'ident' !== $column[0] ) $this->reject( 'columna no reconocida' );
				$columns[] = $column[1];
			} while ( $this->accept_punct( ',' ) );
			$this->expect_punct( ')' );
			$token = $this->next();
		} elseif ( self::EXPORTER === $this->dialect ) {
			$this->reject( 'falta la lista de columnas' );
		}
		if ( 'word' !== $token[0] || ! in_array( $token[1], array( 'VALUES', 'VALUE' ), true ) ) $this->reject( 'INSERT sólo admite VALUES con valores literales' );
		// Columnas generadas de la tabla (un respaldo anterior trae su valor): DEFAULT, que es lo único que MySQL admite.
		$generated = array();
		$wanted = (array) ( $this->compat['generated'][ $table ] ?? array() );
		if ( $wanted && null !== $columns ) foreach ( $columns as $n => $column ) if ( in_array( $column, $wanted, true ) ) $generated[ $n ] = true;
		do {
			$this->expect_punct( '(' );
			$row = array();
			$n = 0;
			do {
				$from = $this->i;
				$value = $this->literal();
				if ( isset( $generated[ $n ] ) ) { $this->spans[] = array( $from, $this->i, ' DEFAULT' ); $value = array( 'type' => 'other' ); }
				if ( $record ) $row[] = $value;
				$n++;
			} while ( $this->accept_punct( ',' ) );
			$this->expect_punct( ')' );
			if ( $record ) $rows[] = $row;
		} while ( $this->accept_punct( ',' ) );
		$this->end();
		if ( $record ) $this->insert = array( 'table' => $table, 'columns' => $columns, 'rows' => $rows );
		if ( $this->collecting && null !== $columns ) $this->collect_insert( $table, $columns, $rows );
	}
	/** Comprueba un literal y lo describe para el gancho de INSERT (ver on_insert()). */
	private function literal() {
		$token = $this->next();
		if ( 'str' === $token[0] && "'" === $token[1] ) return array( 'type' => 'str', 'quote' => "'", 'start' => $token[2], 'end' => $token[3] );
		if ( 'word' === $token[0] && 'NULL' === $token[1] ) return null;
		if ( self::MYSQLDUMP === $this->dialect ) {
			if ( 'punct' === $token[0] && in_array( $token[1], array( '-', '+' ), true ) ) $token = $this->next();
			if ( 'num' === $token[0] ) return array( 'type' => 'raw', 'raw' => $token[1] );
			if ( in_array( $token[0], array( 'hex', 'bit' ), true ) ) return array( 'type' => 'other' );
			if ( 'word' === $token[0] && 0 === strpos( $token[1], '_' ) && preg_match( '/^_(?:BINARY|UTF8MB4|UTF8MB3|UTF8|LATIN1)$/D', $token[1] ) ) { $value = $this->next(); if ( ( 'str' === $value[0] && "'" === $value[1] ) || 'hex' === $value[0] ) return array( 'type' => 'other' ); }
		}
		$this->reject( 'INSERT sólo admite valores literales' );
	}
	private function table() {
		$token = $this->next();
		if ( 'ident' !== $token[0] ) $this->reject( 'la tabla debe ir entre comillas invertidas' );
		if ( $this->accept_punct( '.' ) ) $this->reject( 'no se admite otra base de datos' );
		$name = $token[1];
		if ( '' === $this->current_table ) $this->current_table = $name;
		if ( null !== $this->tables ) { if ( ! isset( $this->tables[ $name ] ) ) $this->reject( 'la tabla no figura en el manifiesto' ); }
		elseif ( '' !== $this->prefix && 0 !== strpos( $name, $this->prefix ) ) $this->reject( 'la tabla no usa el prefijo de este sitio' );
		if ( isset( $this->renames[ $name ] ) ) {
			if ( $this->nested ) $this->cannot_rename( 'no se puede cambiar el prefijo de una tabla dentro de un comentario ejecutable («' . $name . '»)' );
			else $this->spans[] = array( $token[2], $token[3], '`' . str_replace( '`', '``', $this->renames[ $name ] ) . '`' );
		}
		if ( isset( $this->skips[ $name ] ) ) $this->skip_current = true;
		return $name;
	}
	private function words( $words ) {
		foreach ( $words as $word ) { $token = $this->next(); if ( 'word' !== $token[0] || $word !== $token[1] ) $this->reject( 'se esperaba ' . $word ); }
	}
	private function end() { $token = $this->next(); if ( 'end' !== $token[0] ) $this->reject( 'texto de más al final' ); }
	private function peek_word( $word ) { $at = $this->i; $token = $this->next(); $this->i = $at; return 'word' === $token[0] && $word === $token[1]; }
	private function accept_punct( $char ) { $at = $this->i; $token = $this->next(); if ( 'punct' === $token[0] && $char === $token[1] ) return true; $this->i = $at; return false; }
	private function expect_punct( $char ) { if ( ! $this->accept_punct( $char ) ) $this->reject( 'se esperaba «' . $char . '»' ); }

	/** Siguiente token: array( tipo, valor [, versión] ). Los comentarios que MySQL ignora cuentan como espacio. */
	private function next() {
		$s = $this->s;
		$n = $this->n;
		while ( true ) {
			$this->i += strspn( $s, " \t\r\n\f\v", $this->i );
			if ( $this->i >= $n ) return array( 'end', '' );
			$c = $s[ $this->i ];
			if ( '#' === $c || ( '-' === $c && '-' === ( $s[ $this->i + 1 ] ?? '' ) && ( $this->i + 2 >= $n || ord( $s[ $this->i + 2 ] ) <= 32 || 127 === ord( $s[ $this->i + 2 ] ) ) ) ) {
				$end = strpos( $s, "\n", $this->i );
				$this->i = false === $end ? $n : $end + 1;
				continue;
			}
			if ( '/' === $c && '*' === ( $s[ $this->i + 1 ] ?? '' ) ) {
				$end = strpos( $s, '*/', $this->i + 2 );
				if ( false === $end ) $this->reject( 'comentario sin cerrar' );
				$body = substr( $s, $this->i + 2, $end - $this->i - 2 );
				$this->i = $end + 2;
				if ( preg_match( '/^(M?)!([0-9]{5,6})?/', $body, $match ) ) return array( 'exec', substr( $body, strlen( $match[0] ) ), isset( $match[2] ) ? (int) $match[2] : 0, 'M' === $match[1] );
				if ( '' !== $body && '+' === $body[0] ) $this->reject( 'indicaciones del optimizador no permitidas' );
				continue;
			}
			break;
		}
		$start = $this->i;
		if ( "'" === $c || '"' === $c ) {
			$this->i++;
			while ( true ) {
				$this->i += strcspn( $s, '\\' . $c, $this->i );
				if ( $this->i >= $n ) $this->reject( 'cadena sin cerrar' );
				if ( '\\' === $s[ $this->i ] ) { $this->i += 2; continue; }
				if ( $c === ( $s[ $this->i + 1 ] ?? '' ) ) { $this->i += 2; continue; }
				$this->i++;
				return array( 'str', $c, $start + 1, $this->i - 1 );
			}
		}
		if ( '`' === $c ) {
			$name = '';
			$this->i++;
			while ( true ) {
				$run = strcspn( $s, '`\\', $this->i );
				$name .= substr( $s, $this->i, $run );
				$this->i += $run;
				if ( $this->i >= $n ) $this->reject( 'identificador sin cerrar' );
				if ( '\\' === $s[ $this->i ] ) $this->reject( 'barra invertida en un identificador' );
				if ( '`' === ( $s[ $this->i + 1 ] ?? '' ) ) { $name .= '`'; $this->i += 2; continue; }
				$this->i++;
				if ( strlen( $name ) > 192 ) $this->reject( 'identificador demasiado largo' );
				return array( 'ident', $name, $start, $this->i );
			}
		}
		if ( preg_match( '/\G(?:0x[0-9A-Fa-f]+|[Xx]\'[0-9A-Fa-f]*\')/', $s, $match, 0, $start ) ) { $this->i += strlen( $match[0] ); return array( 'hex', '' ); }
		if ( preg_match( '/\G(?:0b[01]+|[Bb]\'[01]*\')/', $s, $match, 0, $start ) ) { $this->i += strlen( $match[0] ); return array( 'bit', '' ); }
		if ( preg_match( '/\G(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?(?![A-Za-z_$])/', $s, $match, 0, $start ) ) { $this->i += strlen( $match[0] ); return array( 'num', $match[0] ); }
		if ( preg_match( '/\G[A-Za-z_$][A-Za-z0-9_$]*/', $s, $match, 0, $start ) ) { $this->i += strlen( $match[0] ); return array( 'word', strtoupper( $match[0] ) ); }
		if ( '@' === $c && preg_match( '/\G@@?(?:`[^`]{1,64}`|[A-Za-z0-9_$]+(?:\.[A-Za-z0-9_$]+)?)/', $s, $match, 0, $start ) ) { $this->i += strlen( $match[0] ); return array( 'var', strtoupper( $match[0] ) ); }
		$this->i++;
		return array( 'punct', $c );
	}

	/* ------------------------------------------------------------------ Compatibilidad entre servidores (Db_Compat) */

	/**
	 * Pasada en seco: anota de cada CREATE TABLE los juegos de caracteres, intercalaciones, motores, opciones de tabla del
	 * otro servidor y columnas generadas; la sentencia más grande; las vistas; y de las opciones, los plugins y temas
	 * activos. $options: `four_byte` (anotar las tablas con caracteres de 4 bytes).
	 */
	public function collect( array $options = array() ) {
		$this->collecting = true;
		$this->four_byte = ! empty( $options['four_byte'] );
		return $this;
	}
	public function found() { return $this->found; }
	/**
	 * Importación: aplica la decisión de Digitalisimo_Backups_Db_Compat::plan() sobre los tokens exactos de cada CREATE
	 * TABLE (`charsets`, `collations`, `engines`, `drop_options`, `persistent`) y de cada INSERT (`generated`: DEFAULT).
	 */
	public function compat( array $plan ) {
		$this->compat = $plan;
		return $this;
	}
	/**
	 * Vistas: 'defer' las comprueba y no las ejecuta (se crean al final del cambio); 'rewrite' las devuelve listas para
	 * crearlas (CREATE OR REPLACE, sin DEFINER, SQL SECURITY INVOKER y los nombres de rename_tables()). $declared: las
	 * vistas que declara el manifiesto, además de su lista de tablas (un respaldo anterior las listaba ahí).
	 */
	public function views( $mode, array $declared = array() ) {
		$this->views_mode = in_array( $mode, array( 'defer', 'rewrite' ), true ) ? $mode : '';
		$this->declared_views = array_fill_keys( array_map( 'strval', $declared ), true );
		return $this;
	}
	/**
	 * Al crear las vistas al final del cambio: las únicas tablas y vistas que puede leer una vista de un archivo sin firma,
	 * las que trae de verdad esa restauración (sus tablas y las vistas con su sentencia en el respaldo), por su nombre en
	 * el respaldo. Sin esto, las del manifiesto (database_tables y las vistas declaradas).
	 */
	public function view_sources( array $names ) {
		$this->view_sources = array_fill_keys( array_map( 'strval', $names ), true );
		return $this;
	}
	/** Nombre de la vista de la última sentencia comprobada, o null. */
	public function last_view() { return $this->view_current; }

	/** Un valor de juego de caracteres o intercalación ($kind) que la decisión sustituye. $end: dónde termina el token. */
	private function compat_value( $kind, array $token, $end ) {
		$text = $this->token_text( $token, $end );
		if ( null === $text ) return;
		$name = strtolower( $text[2] );
		if ( isset( $this->compat[ $kind ][ $name ] ) ) $this->spans[] = array( $text[0], $text[1], (string) $this->compat[ $kind ][ $name ] );
	}
	/** Inicio, fin y texto original de un token de valor (palabra, identificador o cadena), o null. */
	private function token_text( array $token, $end ) {
		if ( 'word' === $token[0] ) return array( $end - strlen( $token[1] ), $end, substr( $this->s, $end - strlen( $token[1] ), strlen( $token[1] ) ) );
		if ( 'ident' === $token[0] ) return array( $token[2], $token[3], $token[1] );
		if ( 'str' === $token[0] ) return array( $token[2] - 1, $token[3] + 1, substr( $this->s, $token[2], $token[3] - $token[2] ) );
		if ( 'num' === $token[0] ) return array( $end - strlen( $token[1] ), $end, $token[1] );
		return null;
	}
	/**
	 * Recorre la definición de un CREATE TABLE sin interpretarla (en un archivo confiable no se recorre para comprobarla)
	 * y anota o sustituye. Lo que no se puede leer no cambia nada: create_body() dirá por qué en un archivo no confiable.
	 */
	private function compat_create( $table ) {
		$at = $this->i;
		$keep = count( $this->spans );
		try {
			$this->compat_walk( (string) $table );
		} catch ( UnexpectedValueException $e ) {
			array_splice( $this->spans, $keep );
		}
		$this->i = $at;
	}
	private function compat_walk( $table ) {
		$depth = 0;
		$opened = false;
		$element = false;
		$column = null;
		$generated = false;
		$expect = '';
		$drop = null;
		$engine_mapped = false;
		$row_format = null;
		$found = array();
		while ( true ) {
			$token = $this->next();
			if ( 'end' === $token[0] ) break;
			$end = $this->i;
			if ( 'exec' === $token[0] ) continue; // Particiones y opciones condicionales: no se miran (límite declarado).
			if ( 'punct' === $token[0] ) {
				if ( '=' === $token[1] ) continue;
				$expect = '';
				if ( '(' === $token[1] ) {
					$depth++;
					if ( 1 === $depth && ! $opened ) { $opened = true; $element = true; }
				} elseif ( ')' === $token[1] ) {
					if ( 1 === $depth && $generated && null !== $column ) $found['generated'][] = $column;
					if ( 1 === $depth ) { $column = null; $generated = false; }
					$depth = max( 0, $depth - 1 );
				} elseif ( ',' === $token[1] && 1 === $depth ) {
					if ( $generated && null !== $column ) $found['generated'][] = $column;
					$element = true;
					$column = null;
					$generated = false;
				}
				continue;
			}
			if ( $element && 1 === $depth ) {
				$element = false;
				if ( 'ident' === $token[0] ) $column = $token[1];
				continue;
			}
			if ( '' !== $expect ) {
				$text = $this->token_text( $token, $end );
				$kind = $expect;
				$expect = '';
				if ( null === $text ) continue;
				if ( 'charsets' === $kind || 'collations' === $kind ) {
					$name = strtolower( $text[2] );
					if ( preg_match( '/^[a-z0-9_]{1,64}$/D', $name ) ) $found[ $kind ][ $name ] = true;
					if ( isset( $this->compat[ $kind ][ $name ] ) ) $this->spans[] = array( $text[0], $text[1], (string) $this->compat[ $kind ][ $name ] );
				} elseif ( 'engines' === $kind ) {
					$name = strtoupper( $text[2] );
					if ( preg_match( '/^[A-Z0-9_]{1,64}$/D', $name ) ) $found['engines'][ $name ] = true;
					if ( isset( $this->compat['engines'][ $name ] ) ) { $this->spans[] = array( $text[0], $text[1], (string) $this->compat['engines'][ $name ] ); $engine_mapped = true; }
				} elseif ( 'drop' === $kind && null !== $drop ) {
					$this->spans[] = array( $drop, $text[1], '' );
					$drop = null;
				} elseif ( 'row_format' === $kind && 'PAGE' === strtoupper( $text[2] ) && is_array( $row_format ) ) {
					$row_format = array( $row_format[0], $text[1], true );
				}
				continue;
			}
			if ( 'word' !== $token[0] ) continue;
			$word = $token[1];
			$start = $end - strlen( $word );
			if ( 'CHARSET' === $word ) { $expect = 'charsets'; continue; }
			if ( 'CHARACTER' === $word && $this->peek_word( 'SET' ) ) { $this->next(); $expect = 'charsets'; continue; }
			if ( 'COLLATE' === $word ) { $expect = 'collations'; continue; }
			if ( 0 === $depth && $opened ) {
				if ( 'ENGINE' === $word || 'TYPE' === $word ) { $expect = 'engines'; continue; }
				if ( 'ROW_FORMAT' === $word ) { $expect = 'row_format'; $row_format = array( $start, $end, false ); continue; }
				if ( in_array( $word, Digitalisimo_Backups_Db_Compat::MARIADB_OPTIONS, true ) || in_array( $word, Digitalisimo_Backups_Db_Compat::MYSQL_OPTIONS, true ) ) {
					$found['options'][ $word ] = true;
					if ( in_array( $word, (array) ( $this->compat['drop_options'] ?? array() ), true ) ) { $drop = $start; $expect = 'drop'; }
				}
				continue;
			}
			if ( 1 === $depth && null !== $column ) {
				if ( 'GENERATED' === $word ) $generated = true;
				elseif ( 'AS' === $word && $this->accept_punct( '(' ) ) { $generated = true; $depth++; }
				elseif ( 'PERSISTENT' === $word && $generated ) {
					$found['persistent'] = true;
					if ( ! empty( $this->compat['persistent'] ) ) $this->spans[] = array( $start, $end, 'STORED' );
				}
			}
		}
		// ROW_FORMAT=PAGE es de Aria: con otro motor no se entiende.
		if ( $engine_mapped && is_array( $row_format ) && $row_format[2] ) $this->spans[] = array( $row_format[0], $row_format[1], '' );
		if ( ! $this->collecting ) return;
		foreach ( array( 'charsets', 'collations', 'engines', 'options' ) as $kind ) foreach ( array_keys( (array) ( $found[ $kind ] ?? array() ) ) as $name ) if ( count( (array) ( $this->found[ $kind ][ $name ] ?? array() ) ) < 20 ) $this->found[ $kind ][ $name ][] = $table;
		if ( ! empty( $found['generated'] ) ) $this->found['generated'][ $table ] = array_values( array_unique( $found['generated'] ) );
		if ( ! empty( $found['persistent'] ) ) $this->found['persistent'] = (int) ( $this->found['persistent'] ?? 0 ) + 1;
	}
	/** La sentencia más grande (una fila) y, sin utf8mb4 en el destino, las tablas con caracteres de 4 bytes. */
	private function collect_statement() {
		if ( 'INSERT' !== $this->kind || $this->skip_current ) return;
		$length = $this->n;
		if ( $length > (int) ( $this->found['largest'][0] ?? 0 ) ) $this->found['largest'] = array( $length, $this->current_table );
		if ( $this->four_byte && ! isset( $this->found['four_byte'][ $this->current_table ] ) && preg_match( '/[\xF0-\xF4][\x80-\xBF]{3}/', $this->s ) ) $this->found['four_byte'][ $this->current_table ] = true;
	}
	/** De las opciones del respaldo: los plugins activos (también los de la red) y el tema, para los avisos de versión. */
	private function collect_insert( $table, array $columns, array $rows ) {
		$value = function( $row, $index ) {
			$item = $row[ $index ] ?? null;
			if ( ! is_array( $item ) || 'str' !== ( $item['type'] ?? '' ) ) return null;
			return strtr( substr( $this->s, $item['start'], $item['end'] - $item['start'] ), array( '\\\\' => '\\', '\\0' => "\0", '\\n' => "\n", '\\r' => "\r", '\\Z' => "\x1a", "\\'" => "'", '\\"' => '"', "''" => "'" ) );
		};
		if ( '_options' === substr( $table, -8 ) ) { $name = array_search( 'option_name', $columns, true ); $data = array_search( 'option_value', $columns, true ); }
		elseif ( '_sitemeta' === substr( $table, -9 ) ) { $name = array_search( 'meta_key', $columns, true ); $data = array_search( 'meta_value', $columns, true ); }
		else return;
		if ( false === $name || false === $data ) return;
		foreach ( $rows as $row ) {
			$key = $value( $row, $name );
			if ( ! in_array( $key, array( 'active_plugins', 'active_sitewide_plugins', 'template', 'stylesheet' ), true ) ) continue;
			$text = (string) $value( $row, $data );
			if ( 'template' === $key || 'stylesheet' === $key ) {
				if ( preg_match( '/^[A-Za-z0-9._-]{1,100}$/D', $text ) && count( (array) ( $this->found['themes'] ?? array() ) ) < 20 ) $this->found['themes'][ $text ] = true;
				continue;
			}
			// Sin unserialize(): sólo las cadenas que nombran un archivo PHP de plugin.
			if ( preg_match_all( '/s:[0-9]+:"([^"]{1,200}\.php)"/', substr( $text, 0, 65536 ), $m ) ) foreach ( $m[1] as $file ) if ( count( (array) ( $this->found['plugins'] ?? array() ) ) < 200 ) $this->found['plugins'][ $file ] = true;
		}
	}

	/** La clase de objeto de un CREATE que no es TABLE (VIEW, TRIGGER, FUNCTION…), sin mover la lectura; '' si no se sabe. */
	private function peek_object() {
		$at = $this->i;
		$kind = '';
		try {
			for ( $k = 0; $k < 40; $k++ ) {
				$token = $this->next();
				if ( 'end' === $token[0] ) break;
				if ( 'word' === $token[0] && in_array( $token[1], array( 'VIEW', 'TRIGGER', 'FUNCTION', 'PROCEDURE', 'EVENT', 'TABLE', 'AS' ), true ) ) { $kind = $token[1]; break; }
			}
		} catch ( UnexpectedValueException $e ) {
			$kind = '';
		}
		$this->i = $at;
		return $kind;
	}
	/**
	 * CREATE [OR REPLACE] [ALGORITHM = …] [DEFINER = …] [SQL SECURITY …] VIEW `nombre` [(columnas)] AS SELECT …
	 * [WITH … CHECK OPTION]. En un archivo no confiable el SELECT sólo puede usar palabras y funciones de una lista
	 * cerrada, nunca variables, comentarios ejecutables, otra base de datos ni tablas fuera del respaldo.
	 */
	private function view() {
		$this->kind = 'CREATE VIEW';
		$create_end = $this->i;
		$replace = false;
		$security = null;
		$view_word = 0;
		while ( true ) {
			$token = $this->next();
			$end = $this->i;
			if ( 'word' !== $token[0] ) $this->reject( 'vista no reconocida' );
			if ( 'OR' === $token[1] ) { $this->words( array( 'REPLACE' ) ); $replace = true; continue; }
			if ( 'ALGORITHM' === $token[1] ) {
				$this->expect_punct( '=' );
				$value = $this->next();
				if ( 'word' !== $value[0] || ! in_array( $value[1], array( 'UNDEFINED', 'MERGE', 'TEMPTABLE' ), true ) ) $this->reject( 'algoritmo de vista no reconocido' );
				continue;
			}
			if ( 'DEFINER' === $token[1] ) {
				$from = $end - strlen( 'DEFINER' );
				$this->expect_punct( '=' );
				$user = $this->next();
				if ( 'word' === $user[0] && 'CURRENT_USER' === $user[1] ) { if ( $this->accept_punct( '(' ) ) $this->expect_punct( ')' ); }
				elseif ( in_array( $user[0], array( 'ident', 'str', 'word' ), true ) ) {
					// Sin «@servidor» (MariaDB con --skip-grant-tables escribe DEFINER=``), la palabra siguiente es de la vista.
					$before_host = $this->i;
					$host = $this->next();
					if ( 'punct' === $host[0] && '@' === $host[1] ) { $host = $this->next(); if ( ! in_array( $host[0], array( 'ident', 'str', 'word' ), true ) ) $this->reject( 'DEFINER no reconocido' ); }
					elseif ( 'word' === $host[0] && in_array( $host[1], array( 'SQL', 'VIEW' ), true ) ) $this->i = $before_host;
					elseif ( 'var' !== $host[0] || 0 === strpos( $host[1], '@@' ) ) $this->reject( 'DEFINER no reconocido' );
				} else $this->reject( 'DEFINER no reconocido' );
				if ( 'rewrite' === $this->views_mode ) $this->spans[] = array( $from, $this->i + strspn( $this->s, " \t\r\n", $this->i ), '' );
				continue;
			}
			if ( 'SQL' === $token[1] ) {
				$this->words( array( 'SECURITY' ) );
				$value = $this->next();
				if ( 'word' !== $value[0] || ! in_array( $value[1], array( 'DEFINER', 'INVOKER' ), true ) ) $this->reject( 'SQL SECURITY no reconocido' );
				$security = array( $this->i - strlen( $value[1] ), $this->i );
				continue;
			}
			if ( 'VIEW' === $token[1] ) { $view_word = $end - 4; break; }
			$this->reject( 'vista no reconocida' );
		}
		$name = $this->next();
		if ( 'ident' !== $name[0] ) $this->reject( 'la vista debe ir entre comillas invertidas' );
		if ( $this->accept_punct( '.' ) ) $this->reject( 'no se admite otra base de datos' );
		$view = $name[1];
		if ( ! isset( $this->declared_views[ $view ] ) ) {
			if ( null !== $this->tables ) { if ( ! isset( $this->tables[ $view ] ) ) $this->reject( 'la vista no figura en el manifiesto' ); }
			elseif ( '' !== $this->prefix && 0 !== strpos( $view, $this->prefix ) ) $this->reject( 'la vista no usa el prefijo de este sitio' );
		}
		if ( 'rewrite' === $this->views_mode ) {
			if ( ! $replace ) $this->spans[] = array( $create_end, $create_end, ' OR REPLACE' );
			if ( null !== $security ) $this->spans[] = array( $security[0], $security[1], 'INVOKER' );
			else $this->spans[] = array( $view_word, $view_word, 'SQL SECURITY INVOKER ' );
			if ( isset( $this->renames[ $view ] ) ) $this->spans[] = array( $name[2], $name[3], '`' . str_replace( '`', '``', $this->renames[ $view ] ) . '`' );
		}
		if ( $this->accept_punct( '(' ) ) {
			do { $column = $this->next(); if ( 'ident' !== $column[0] ) $this->reject( 'columna de la vista no reconocida' ); } while ( $this->accept_punct( ',' ) );
			$this->expect_punct( ')' );
		}
		$this->words( array( 'AS' ) );
		$this->view_body();
		$this->view_current = $view;
		if ( 'defer' === $this->views_mode ) $this->skip_current = true;
		if ( $this->collecting ) $this->found['views'][ $view ] = true;
	}
	/** Palabras que puede usar el SELECT de una vista de un archivo no confiable (sin las funciones, que van aparte). */
	const VIEW_WORDS = array( 'SELECT', 'DISTINCT', 'DISTINCTROW', 'ALL', 'AS', 'FROM', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'OUTER', 'CROSS', 'STRAIGHT_JOIN', 'NATURAL', 'ON', 'USING', 'WHERE', 'AND', 'OR', 'XOR', 'NOT', 'IS', 'NULL', 'TRUE', 'FALSE', 'UNKNOWN', 'IN', 'EXISTS', 'LIKE', 'ESCAPE', 'BETWEEN', 'REGEXP', 'RLIKE', 'GROUP', 'BY', 'ORDER', 'ASC', 'DESC', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'DIV', 'MOD', 'INTERVAL', 'MICROSECOND', 'SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR', 'DAY_HOUR', 'DAY_MINUTE', 'DAY_SECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'MINUTE_SECOND', 'YEAR_MONTH', 'WITH', 'ROLLUP', 'COLLATE', 'BINARY', 'CHAR', 'CHARACTER', 'CHARSET', 'SET', 'SIGNED', 'UNSIGNED', 'INTEGER', 'INT', 'DECIMAL', 'DOUBLE', 'FLOAT', 'REAL', 'DATE', 'DATETIME', 'TIME', 'TIMESTAMP', 'JSON', 'NCHAR', 'VARCHAR', 'SEPARATOR', 'OVER', 'PARTITION', 'ROWS', 'RANGE', 'PRECEDING', 'FOLLOWING', 'CURRENT', 'ROW', 'UNBOUNDED', 'WINDOW', 'ANY', 'SOME', 'SOUNDS', 'CASCADED', 'LOCAL', 'CHECK', 'OPTION', 'LEADING', 'TRAILING', 'BOTH', 'FOR', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'LOCALTIME', 'LOCALTIMESTAMP', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'USING', 'PATH', 'COLUMNS', 'NESTED', 'ORDINALITY', 'ERROR', 'EMPTY', 'DEFAULT', 'RETURNING' );
	/** Funciones que puede llamar esa vista: de texto, números, fechas, JSON, control y agregados; nada que lea fuera de la base, espere o bloquee. */
	const VIEW_FUNCTIONS = array( 'COUNT', 'SUM', 'MIN', 'MAX', 'AVG', 'GROUP_CONCAT', 'STD', 'STDDEV', 'VARIANCE', 'BIT_AND', 'BIT_OR', 'BIT_XOR', 'ROW_NUMBER', 'RANK', 'DENSE_RANK', 'LAG', 'LEAD', 'FIRST_VALUE', 'LAST_VALUE', 'NTILE', 'CONCAT', 'CONCAT_WS', 'IFNULL', 'NULLIF', 'COALESCE', 'IF', 'ISNULL', 'GREATEST', 'LEAST', 'SUBSTRING', 'SUBSTR', 'MID', 'SUBSTRING_INDEX', 'LEFT', 'RIGHT', 'LENGTH', 'CHAR_LENGTH', 'CHARACTER_LENGTH', 'OCTET_LENGTH', 'BIT_LENGTH', 'LOWER', 'UPPER', 'LCASE', 'UCASE', 'TRIM', 'LTRIM', 'RTRIM', 'REPLACE', 'LOCATE', 'POSITION', 'INSTR', 'LPAD', 'RPAD', 'REVERSE', 'REPEAT', 'SPACE', 'FORMAT', 'CAST', 'CONVERT', 'HEX', 'UNHEX', 'ASCII', 'ORD', 'CHAR', 'FIELD', 'FIND_IN_SET', 'ELT', 'STRCMP', 'SOUNDEX', 'QUOTE', 'INSERT', 'TO_BASE64', 'FROM_BASE64', 'MD5', 'SHA1', 'SHA2', 'CRC32', 'ROUND', 'TRUNCATE', 'FLOOR', 'CEIL', 'CEILING', 'ABS', 'SIGN', 'MOD', 'POW', 'POWER', 'SQRT', 'EXP', 'LN', 'LOG', 'LOG2', 'LOG10', 'PI', 'DEGREES', 'RADIANS', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'ATAN2', 'COT', 'CONV', 'BIN', 'OCT', 'DATE', 'TIME', 'TIMESTAMP', 'YEAR', 'MONTH', 'DAY', 'DAYOFMONTH', 'DAYOFWEEK', 'DAYOFYEAR', 'WEEKDAY', 'WEEK', 'WEEKOFYEAR', 'YEARWEEK', 'QUARTER', 'HOUR', 'MINUTE', 'SECOND', 'MICROSECOND', 'DAYNAME', 'MONTHNAME', 'NOW', 'CURDATE', 'CURTIME', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'SYSDATE', 'DATE_FORMAT', 'TIME_FORMAT', 'DATE_ADD', 'DATE_SUB', 'ADDDATE', 'SUBDATE', 'ADDTIME', 'SUBTIME', 'DATEDIFF', 'TIMEDIFF', 'TIMESTAMPDIFF', 'TIMESTAMPADD', 'FROM_UNIXTIME', 'UNIX_TIMESTAMP', 'STR_TO_DATE', 'FROM_DAYS', 'TO_DAYS', 'TO_SECONDS', 'SEC_TO_TIME', 'TIME_TO_SEC', 'MAKEDATE', 'MAKETIME', 'LAST_DAY', 'EXTRACT', 'PERIOD_ADD', 'PERIOD_DIFF', 'CONVERT_TZ', 'JSON_EXTRACT', 'JSON_UNQUOTE', 'JSON_VALUE', 'JSON_OBJECT', 'JSON_ARRAY', 'JSON_CONTAINS', 'JSON_CONTAINS_PATH', 'JSON_KEYS', 'JSON_LENGTH', 'JSON_TYPE', 'JSON_VALID', 'JSON_QUOTE', 'JSON_SEARCH', 'JSON_ARRAYAGG', 'JSON_OBJECTAGG', 'JSON_TABLE', 'JSON_MERGE_PATCH', 'JSON_MERGE_PRESERVE', 'JSON_SET', 'JSON_INSERT', 'JSON_REPLACE', 'JSON_REMOVE', 'INET_ATON', 'INET_NTOA', 'INET6_ATON', 'INET6_NTOA', 'BIT_COUNT', 'COLLATION', 'CHARSET', 'COERCIBILITY', 'VALUES', 'ROW', 'ANY_VALUE' );
	/**
	 * El SELECT de una vista: en un archivo confiable sólo se lee (y renombra); en uno no confiable, además, se comprueba.
	 *
	 * Revisión de 1.0.80 (H1): se decide por la posición en la sintaxis, no por nombres. `a`.`b` es base.tabla donde se
	 * nombra una tabla (tras FROM, JOIN, STRAIGHT_JOIN o una coma de la lista del FROM, también dentro de una
	 * subconsulta, una tabla derivada o una lista entre paréntesis) y tabla.columna en una expresión. Por eso, en un
	 * archivo sin firma:
	 * - donde va una tabla sólo vale un identificador sin cualificar que sea una tabla del respaldo (o una vista suya), una
	 *   tabla derivada, una lista entre paréntesis o JSON_TABLE; nunca un nombre con otra base delante, aunque la vista
	 *   tenga un alias con ese nombre o el manifiesto (que en un archivo sin firma escribe su autor) lo liste;
	 * - en una expresión, `a`.`b` sólo si `a` es una tabla o un alias que la misma vista define donde van las tablas;
	 *   nunca tres partes, `a`.`b`( (una función de otra base) ni `nombre`( (una función por su nombre entre comillas
	 *   invertidas, que MySQL busca también entre las funciones instaladas en el servidor);
	 * - con view_sources() (al crearla al final del cambio), las tablas y vistas que trae de verdad esa restauración.
	 * Lo que no se reconoce se rechaza: así una forma que este análisis no conozca nunca abre la puerta.
	 */
	private function view_body() {
		$strict = ! $this->trusted;
		$prefix = $this->prefix;
		$listed = null !== $this->view_sources ? $this->view_sources : ( null === $this->tables ? null : $this->tables + $this->declared_views );
		$readable = function( $name ) use ( $listed, $prefix ) { return null === $listed ? ( '' === $prefix || 0 === strpos( $name, $prefix ) ) : isset( $listed[ $name ] ); };
		// Por cada nivel de paréntesis: si ya tuvo su SELECT, si está en la lista del FROM, si lo siguiente es una tabla,
		// si puede venir su alias (1: tras una tabla; 2: tras AS) y si el nivel es él mismo una tabla (derivada, una lista
		// de tablas o JSON_TABLE), cuyo alias viene tras cerrarlo.
		$fresh = array( 'select' => false, 'from' => false, 'expect' => false, 'alias' => 0, 'ref' => false, 'json' => false );
		$levels = array( $fresh );
		$defined = array();
		$qualifiers = array();
		$previous = null;
		$before = null;
		$after_value = false;
		while ( true ) {
			$token = $this->next();
			if ( 'end' === $token[0] ) break;
			if ( 'ident' === $token[0] && isset( $this->renames[ $token[1] ] ) && 'rewrite' === $this->views_mode ) $this->spans[] = array( $token[2], $token[3], '`' . str_replace( '`', '``', $this->renames[ $token[1] ] ) . '`' );
			if ( $strict ) {
				if ( 'exec' === $token[0] ) $this->reject( 'comentario ejecutable en una vista de un archivo sin firma' );
				if ( 'var' === $token[0] ) $this->reject( 'variables en una vista de un archivo sin firma' );
				$top = count( $levels ) - 1;
				$handled = false;
				// El alias de una tabla: tras ella (o tras AS), un identificador.
				if ( 2 === $levels[ $top ]['alias'] ) {
					if ( 'ident' !== $token[0] ) $this->reject( 'alias no reconocido en una vista de un archivo sin firma' );
					$defined[ $token[1] ] = true;
					$levels[ $top ]['alias'] = 0;
					$handled = true;
				} elseif ( 1 === $levels[ $top ]['alias'] ) {
					$levels[ $top ]['alias'] = 0;
					if ( 'word' === $token[0] && 'AS' === $token[1] ) { $levels[ $top ]['alias'] = 2; $handled = true; }
					elseif ( 'ident' === $token[0] ) { $defined[ $token[1] ] = true; $handled = true; }
				}
				// Donde va una tabla.
				if ( ! $handled && $levels[ $top ]['expect'] ) {
					$levels[ $top ]['expect'] = false;
					$handled = true;
					if ( 'ident' === $token[0] ) {
						$at = $this->i;
						$next = $this->next();
						$this->i = $at;
						if ( ( 'punct' === $next[0] && '.' === $next[1] ) || ( 'num' === $next[0] && '.' === $next[1][0] ) ) $this->reject( 'otra base de datos en una vista de un archivo sin firma («' . $token[1] . '.…» donde va una tabla)' );
						if ( ! $readable( $token[1] ) ) $this->reject( 'una tabla que no está en el respaldo («' . $token[1] . '») en una vista de un archivo sin firma' );
						$defined[ $token[1] ] = true;
						$levels[ $top ]['alias'] = 1;
					} elseif ( 'punct' === $token[0] && '(' === $token[1] ) {
						// Una tabla derivada (una subconsulta) o una lista de tablas entre paréntesis: lo dice lo primero que sigue.
						$at = $this->i;
						do { $first = $this->next(); } while ( 'punct' === $first[0] && '(' === $first[1] );
						$this->i = $at;
						$list = ! ( 'word' === $first[0] && in_array( $first[1], array( 'SELECT', 'WITH', 'VALUES', 'TABLE' ), true ) );
						$levels[] = array( 'from' => $list, 'expect' => $list, 'ref' => true ) + $fresh;
					} elseif ( 'word' === $token[0] && 'JSON_TABLE' === $token[1] ) {
						$levels[ $top ]['json'] = true;
					} else {
						$this->reject( 'se esperaba una tabla del respaldo en una vista de un archivo sin firma' );
					}
				}
				if ( ! $handled ) {
					if ( 'punct' === $token[0] ) {
						if ( '(' === $token[1] ) {
							$levels[] = array( 'ref' => $levels[ $top ]['json'] ) + $fresh;
							$levels[ $top ]['json'] = false;
						} elseif ( ')' === $token[1] ) {
							if ( 0 === $top ) $this->reject( 'paréntesis sin abrir en una vista de un archivo sin firma' );
							$closed = array_pop( $levels );
							$levels[ $top - 1 ]['alias'] = $closed['ref'] ? 1 : 0;
						} elseif ( ',' === $token[1] ) {
							if ( $levels[ $top ]['from'] ) $levels[ $top ]['expect'] = true;
						} elseif ( '.' === $token[1] ) {
							// tabla.columna (o tabla.*): la primera parte, un identificador; nunca tres partes ni una función.
							if ( 'ident' !== ( $previous[0] ?? '' ) ) $this->reject( 'nombre cualificado no reconocido en una vista de un archivo sin firma' );
							if ( 'punct' === ( $before[0] ?? '' ) && '.' === $before[1] ) $this->reject( 'otra base de datos en una vista de un archivo sin firma' );
							$at = $this->i;
							$second = $this->next();
							$third = $this->next();
							$this->i = $at;
							if ( 'ident' !== $second[0] && ! ( 'punct' === $second[0] && '*' === $second[1] ) ) $this->reject( 'nombre cualificado no reconocido en una vista de un archivo sin firma' );
							if ( ( 'punct' === $third[0] && in_array( $third[1], array( '.', '(' ), true ) ) || ( 'num' === $third[0] && '.' === $third[1][0] ) ) $this->reject( 'otra base de datos en una vista de un archivo sin firma' );
							$qualifiers[ $previous[1] ] = true;
						}
					} elseif ( 'num' === $token[0] && '.' === $token[1][0] && 'ident' === ( $previous[0] ?? '' ) ) {
						$this->reject( 'otra base de datos en una vista de un archivo sin firma' );
					} elseif ( 'ident' === $token[0] ) {
						if ( $this->accept_punct( '(' ) ) $this->reject( 'función «' . $token[1] . '» no permitida en una vista de un archivo sin firma' );
					} elseif ( 'word' === $token[0] ) {
						if ( ! $after_value ) {
							$call = $this->accept_punct( '(' );
							if ( $call ) $this->i--;
							$word = $token[1];
							if ( ! in_array( $word, self::VIEW_FUNCTIONS, true ) && ! in_array( $word, self::VIEW_WORDS, true ) && ( $call || ! preg_match( '/^(?:N|_(?:UTF8MB4|UTF8MB3|UTF8|LATIN1|BINARY|ASCII))$/D', $word ) ) ) $this->reject( ( $call ? 'función ' : 'palabra ' ) . $word . ' no permitida en una vista de un archivo sin firma' );
						}
						$word = $token[1];
						if ( 'SELECT' === $word ) {
							$levels[ $top ]['select'] = true;
							$levels[ $top ]['from'] = false;
						} elseif ( 'FROM' === $word && $levels[ $top ]['select'] ) {
							// El FROM de un SELECT de este nivel (el de EXTRACT, TRIM o SUBSTRING va en el paréntesis de la función).
							$levels[ $top ]['from'] = true;
							$levels[ $top ]['expect'] = true;
						} elseif ( 'JOIN' === $word || ( 'STRAIGHT_JOIN' === $word && $levels[ $top ]['from'] ) ) {
							$levels[ $top ]['expect'] = true;
						} elseif ( in_array( $word, array( 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'WINDOW', 'UNION', 'INTERSECT', 'EXCEPT', 'WITH', 'INTO', 'FOR', 'PROCEDURE', 'LOCK' ), true ) ) {
							// Termina la lista del FROM (una coma de GROUP BY u ORDER BY no nombra una tabla). ON y USING no: tras
							// su condición, una coma sigue la lista.
							$levels[ $top ]['from'] = false;
						}
					}
				}
			}
			// Tras COLLATE, CHARSET o CHARACTER SET viene un nombre (de intercalación o juego de caracteres), no una palabra clave.
			$after_value = ( 'word' === $token[0] && in_array( $token[1], array( 'COLLATE', 'CHARSET', 'USING' ), true ) ) || ( 'word' === $token[0] && 'SET' === $token[1] && 'word' === ( $previous[0] ?? '' ) && 'CHARACTER' === $previous[1] );
			$before = $previous;
			$previous = $token;
		}
		if ( ! $strict ) return;
		if ( count( $levels ) > 1 || $levels[0]['expect'] || 2 === $levels[0]['alias'] ) $this->reject( 'vista incompleta en un archivo sin firma' );
		foreach ( array_keys( $qualifiers ) as $name ) if ( ! isset( $defined[ (string) $name ] ) ) $this->reject( 'nombre cualificado («' . $name . '») que no es una tabla ni un alias de la vista en un archivo sin firma' );
	}
}
