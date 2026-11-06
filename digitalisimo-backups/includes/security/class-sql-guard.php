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

	public function feed( $chunk ) {
		$out = array();
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
				if ( '' !== $statement && 0 !== strpos( $statement, '--' ) ) $out[] = $statement;
				$this->current = '';
				continue;
			}
			$this->quote = $char;
			$this->current .= $char;
		}
		return $out;
	}
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
			foreach ( $reader->feed( $chunk ) as $statement ) if ( $this->check( $statement ) ) { if ( $run ) call_user_func( $run, $statement ); $executed++; }
		}
		foreach ( $reader->finish() as $statement ) if ( $this->check( $statement ) ) { if ( $run ) call_user_func( $run, $statement ); $executed++; }
		if ( ! $executed ) throw new Exception( 'La base de datos del respaldo está vacía.' );
		return $executed;
	}

	/** true si la sentencia se ejecuta, false si sólo trae comentarios; excepción si no está permitida. */
	public function check( $sql ) {
		$this->count++;
		$this->s = (string) $sql;
		$this->i = 0;
		$this->n = strlen( $this->s );
		$this->kind = '';
		$this->excerpt_end = 80;
		try {
			return $this->statement();
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
			if ( $token[2] >= 900000 ) return $this->statement(); // Versión inalcanzable: es un comentario.
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
	/** Contenido de un /*!NNNNN … *\/ del volcado: sólo SET o ALTER TABLE … KEYS. */
	private function sub( $content, $mode ) {
		$saved = array( $this->s, $this->i, $this->n );
		$this->s = $content;
		$this->i = 0;
		$this->n = strlen( $content );
		$token = $this->next();
		if ( 'word' === $token[0] && 'SET' === $token[1] ) { $this->kind = 'SET'; $this->set(); }
		elseif ( 'word' === $token[0] && 'ALTER' === $token[1] ) { $this->kind = 'ALTER TABLE'; $this->alter_keys(); }
		else $this->reject( 'comentario ejecutable no permitido' );
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
			if ( $this->peek_word( 'COLLATE' ) ) { $this->next(); $collation = $this->next(); if ( 'word' !== $collation[0] || ! $this->safe_charset_prefix( $collation[1] ) ) $this->reject( 'intercalación no permitida' ); }
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
		$what = $this->next();
		if ( 'word' !== $what[0] || 'TABLE' !== $what[1] ) {
			if ( $this->trusted ) return; // Lo escribió SHOW CREATE TABLE de este servidor (por ejemplo, una vista).
			$this->reject( 'sólo se admite CREATE TABLE' );
		}
		$this->kind = 'CREATE TABLE';
		$this->table();
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
				if ( $token[2] >= 900000 ) continue;
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
			if ( 'word' !== $token[0] ) { if ( 'punct' !== $token[0] || '=' !== $token[1] ) $previous = ''; continue; }
			if ( in_array( $token[1], self::CREATE_DENIED, true ) ) $this->reject( 'opción ' . $token[1] . ' no permitida en un archivo sin firma' );
			if ( in_array( $previous, array( 'ENGINE', 'TYPE' ), true ) && in_array( $token[1], self::REMOTE_ENGINES, true ) ) $this->reject( 'motor ' . $token[1] . ' no permitido en un archivo sin firma' );
			$previous = $token[1];
		}
	}
	private function insert() {
		if ( self::MYSQLDUMP === $this->dialect && $this->peek_word( 'IGNORE' ) ) $this->next();
		$this->words( array( 'INTO' ) );
		$this->excerpt_end = $this->i;
		$this->table();
		$this->excerpt_end = $this->i;
		$token = $this->next();
		if ( 'punct' === $token[0] && '(' === $token[1] ) {
			do {
				$column = $this->next();
				if ( 'ident' !== $column[0] ) $this->reject( 'columna no reconocida' );
			} while ( $this->accept_punct( ',' ) );
			$this->expect_punct( ')' );
			$token = $this->next();
		} elseif ( self::EXPORTER === $this->dialect ) {
			$this->reject( 'falta la lista de columnas' );
		}
		if ( 'word' !== $token[0] || ! in_array( $token[1], array( 'VALUES', 'VALUE' ), true ) ) $this->reject( 'INSERT sólo admite VALUES con valores literales' );
		do {
			$this->expect_punct( '(' );
			do { $this->literal(); } while ( $this->accept_punct( ',' ) );
			$this->expect_punct( ')' );
		} while ( $this->accept_punct( ',' ) );
		$this->end();
	}
	private function literal() {
		$token = $this->next();
		if ( 'str' === $token[0] && "'" === $token[1] ) return;
		if ( 'word' === $token[0] && 'NULL' === $token[1] ) return;
		if ( self::MYSQLDUMP === $this->dialect ) {
			if ( 'punct' === $token[0] && in_array( $token[1], array( '-', '+' ), true ) ) $token = $this->next();
			if ( in_array( $token[0], array( 'num', 'hex', 'bit' ), true ) ) return;
			if ( 'word' === $token[0] && 0 === strpos( $token[1], '_' ) && preg_match( '/^_(?:BINARY|UTF8MB4|UTF8MB3|UTF8|LATIN1)$/D', $token[1] ) ) { $value = $this->next(); if ( ( 'str' === $value[0] && "'" === $value[1] ) || 'hex' === $value[0] ) return; }
		}
		$this->reject( 'INSERT sólo admite valores literales' );
	}
	private function table() {
		$token = $this->next();
		if ( 'ident' !== $token[0] ) $this->reject( 'la tabla debe ir entre comillas invertidas' );
		if ( $this->accept_punct( '.' ) ) $this->reject( 'no se admite otra base de datos' );
		$name = $token[1];
		if ( null !== $this->tables ) { if ( ! isset( $this->tables[ $name ] ) ) $this->reject( 'la tabla no figura en el manifiesto' ); }
		elseif ( '' !== $this->prefix && 0 !== strpos( $name, $this->prefix ) ) $this->reject( 'la tabla no usa el prefijo de este sitio' );
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
			if ( '#' === $c || ( '-' === $c && '-' === ( $s[ $this->i + 1 ] ?? '' ) && ( $this->i + 2 >= $n || ord( $s[ $this->i + 2 ] ) <= 32 ) ) ) {
				$end = strpos( $s, "\n", $this->i );
				$this->i = false === $end ? $n : $end + 1;
				continue;
			}
			if ( '/' === $c && '*' === ( $s[ $this->i + 1 ] ?? '' ) ) {
				$end = strpos( $s, '*/', $this->i + 2 );
				if ( false === $end ) $this->reject( 'comentario sin cerrar' );
				$body = substr( $s, $this->i + 2, $end - $this->i - 2 );
				$this->i = $end + 2;
				if ( preg_match( '/^(M?)!([0-9]{5,6})?/', $body, $match ) ) return array( 'exec', substr( $body, strlen( $match[0] ) ), isset( $match[2] ) ? (int) $match[2] : 0 );
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
				return array( 'ident', $name );
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
}
