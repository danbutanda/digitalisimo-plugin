<?php
defined( 'ABSPATH' ) || exit;

/**
 * Informe de diferencias de `wp-config.php` entre un respaldo y este servidor (I10).
 *
 * La restauración nunca escribe `wp-config.php`: las constantes del origen (límites de memoria, caché, depuración,
 * licencias, DIGITALISIMO_BACKUPS_KEY…) no viajan. Este informe dice cuáles define el respaldo y aquí no, cuáles define
 * este servidor y el respaldo no, y cuáles tienen otro valor, antes de confirmar (`verify`, la subida) y después de
 * restaurar, para que el administrador decida si las copia a mano.
 *
 * Los dos archivos se leen con el tokenizador de PHP (sólo `define( 'NOMBRE', … )` en el archivo; nunca se ejecutan
 * ni se incluyen). Nunca se muestra el valor de una constante secreta (contraseñas, claves, sales, tokens, credenciales
 * de la base de datos) ni una ruta del servidor: sólo que es distinta. Un valor que no es un literal (getenv(), otra
 * constante, una concatenación) se informa como «expresión».
 */
final class Digitalisimo_Backups_Config_Diff {
	const MAX = 1048576;
	const MAX_ITEMS = 200;

	/** Constantes de un wp-config.php: nombre => array( literal, value, source ). */
	public static function parse( $php ) {
		$found = array();
		if ( ! is_string( $php ) || '' === $php || strlen( $php ) > self::MAX || ! function_exists( 'token_get_all' ) ) return $found;
		try {
			$tokens = token_get_all( $php );
		} catch ( Throwable $e ) {
			return $found;
		}
		$skip = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
		$tokens = array_values( array_filter( $tokens, function( $token ) use ( $skip ) { return ! is_array( $token ) || ! in_array( $token[0], $skip, true ); } ) );
		$names = array( T_STRING );
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) $names[] = constant( 'T_NAME_FULLY_QUALIFIED' );
		$member = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST );
		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) $member[] = constant( 'T_NULLSAFE_OBJECT_OPERATOR' );
		$count = count( $tokens );
		for ( $i = 0; $i + 3 < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || ! in_array( $token[0], $names, true ) || 'define' !== strtolower( ltrim( $token[1], '\\' ) ) ) continue;
			$before = $tokens[ $i - 1 ] ?? null;
			if ( is_array( $before ) && in_array( $before[0], $member, true ) ) continue;
			if ( '(' !== $tokens[ $i + 1 ] || ! is_array( $tokens[ $i + 2 ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $i + 2 ][0] || ',' !== $tokens[ $i + 3 ] ) continue;
			$name = self::string_literal( $tokens[ $i + 2 ][1] );
			if ( null === $name || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,127}$/', $name ) || isset( $found[ $name ] ) ) continue;
			// El valor: lo que hay hasta la coma o el paréntesis que cierra el define (a la misma profundidad).
			$value = array();
			$depth = 0;
			for ( $j = $i + 4; $j < $count; $j++ ) {
				$part = $tokens[ $j ];
				if ( in_array( $part, array( '(', '[', '{' ), true ) ) $depth++;
				if ( in_array( $part, array( ')', ']', '}' ), true ) ) { if ( 0 === $depth ) break; $depth--; }
				if ( ',' === $part && 0 === $depth ) break;
				$value[] = $part;
			}
			$found[ $name ] = self::value( $value );
			if ( count( $found ) >= self::MAX_ITEMS ) break;
		}
		return $found;
	}
	/** Un valor de define(): literal (cadena, número, true, false o null) o expresión. */
	private static function value( array $tokens ) {
		$source = implode( '', array_map( function( $token ) { return is_array( $token ) ? $token[1] : $token; }, $tokens ) );
		$sign = '';
		if ( 2 === count( $tokens ) && in_array( $tokens[0], array( '-', '+' ), true ) ) { $sign = '-' === $tokens[0] ? '-' : ''; $tokens = array( $tokens[1] ); }
		if ( 1 === count( $tokens ) && is_array( $tokens[0] ) ) {
			list( $type, $text ) = $tokens[0];
			if ( T_CONSTANT_ENCAPSED_STRING === $type && '' === $sign ) {
				$string = self::string_literal( $text );
				if ( null !== $string ) return array( 'literal' => true, 'value' => $string, 'source' => $source );
			}
			if ( T_LNUMBER === $type || T_DNUMBER === $type ) return array( 'literal' => true, 'value' => $sign . strtolower( str_replace( '_', '', $text ) ), 'source' => $source );
			if ( T_STRING === $type && '' === $sign && in_array( strtolower( $text ), array( 'true', 'false', 'null' ), true ) ) return array( 'literal' => true, 'value' => strtolower( $text ), 'source' => $source );
		}
		return array( 'literal' => false, 'value' => null, 'source' => preg_replace( '/\s+/', '', $source ) );
	}
	/** Literal de cadena de PHP sin interpolación; null si no se puede leer con seguridad. */
	private static function string_literal( $token ) {
		if ( ! is_string( $token ) || strlen( $token ) < 2 ) return null;
		$quote = $token[0];
		if ( substr( $token, -1 ) !== $quote || ! in_array( $quote, array( "'", '"' ), true ) ) return null;
		$body = substr( $token, 1, -1 );
		if ( "'" === $quote ) return preg_replace( "/\\\\([\\\\'])/", '$1', $body );
		if ( false !== strpos( $body, '$' ) || false !== strpos( $body, '\\u{' ) ) return null;
		return stripcslashes( $body );
	}

	/** Constantes cuyo valor nunca se muestra: contraseñas, claves, sales, tokens y la conexión a la base de datos. */
	public static function secret( $name ) {
		$name = strtoupper( (string) $name );
		if ( 0 === strpos( $name, 'DB_' ) && ! in_array( $name, array( 'DB_CHARSET', 'DB_COLLATE' ), true ) ) return true;
		return (bool) preg_match( '/(?:PASS|KEY|SALT|SECRET|TOKEN|AUTH|NONCE|CREDENTIAL|LICEN[CS]E|PRIVATE|SIGN)/', $name );
	}
	/** Valor apto para mostrarse: nunca secreto ni ruta del servidor; null si se oculta. */
	private static function shown( $name, array $item ) {
		if ( empty( $item['literal'] ) || self::secret( $name ) ) return null;
		$value = (string) $item['value'];
		if ( preg_match( '#^(?:/|~|[A-Za-z]:[\\\\/]|\\\\\\\\)#', $value ) || false !== strpos( $value, '\\' ) ) return null;
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80 ) : substr( $value, 0, 80 );
	}
	/** Por qué se oculta un valor (o null si se muestra). */
	private static function hidden_reason( $name, array $item ) {
		if ( empty( $item['literal'] ) ) return 'expression';
		if ( self::secret( $name ) ) return 'secret';
		return null === self::shown( $name, $item ) ? 'path' : null;
	}

	/**
	 * Diferencias del respaldo frente a este servidor: `backup_only` (el respaldo la define y aquí no), `server_only`
	 * (este servidor la define y el respaldo no) y `different` (otro valor u otra expresión). Cada una con su nombre,
	 * si es secreta y, cuando se pueden mostrar, los dos valores; si no, por qué se ocultan.
	 */
	public static function compare( $backup_php, $server_php ) {
		$backup = self::parse( $backup_php );
		$server = self::parse( $server_php );
		$names = array_unique( array_merge( array_keys( $backup ), array_keys( $server ) ) );
		sort( $names );
		$out = array();
		foreach ( $names as $name ) {
			$in_backup = isset( $backup[ $name ] );
			$in_server = isset( $server[ $name ] );
			if ( $in_backup && $in_server ) {
				$a = $backup[ $name ];
				$b = $server[ $name ];
				$same = $a['literal'] && $b['literal'] ? (string) $a['value'] === (string) $b['value'] : ( ! $a['literal'] && ! $b['literal'] && $a['source'] === $b['source'] );
				if ( $same ) continue;
				$change = 'different';
			} else {
				$change = $in_backup ? 'backup_only' : 'server_only';
			}
			$row = array( 'name' => $name, 'change' => $change, 'secret' => self::secret( $name ) );
			if ( $in_backup ) { $row['backup'] = self::shown( $name, $backup[ $name ] ); $row['backup_hidden'] = self::hidden_reason( $name, $backup[ $name ] ); }
			if ( $in_server ) { $row['server'] = self::shown( $name, $server[ $name ] ); $row['server_hidden'] = self::hidden_reason( $name, $server[ $name ] ); }
			$out[] = $row;
		}
		return $out;
	}
	/** Informe de un respaldo abierto contra el wp-config.php que carga este servidor. Nunca lanza. */
	public static function for_zip( $zip, $server_config ) {
		$empty = array( 'available' => false, 'differences' => array(), 'text' => '' );
		try {
			if ( ! $zip instanceof ZipArchive ) return $empty;
			$stat = $zip->statName( 'wordpress/wp-config.php' );
			if ( ! is_array( $stat ) || (int) $stat['size'] > self::MAX ) return $empty;
			$backup = $zip->getFromName( 'wordpress/wp-config.php' );
			$server = is_string( $server_config ) && is_file( $server_config ) && (int) @filesize( $server_config ) <= self::MAX ? @file_get_contents( $server_config ) : false;
			if ( ! is_string( $backup ) || ! is_string( $server ) ) return $empty;
			$differences = self::compare( $backup, $server );
			return array( 'available' => true, 'differences' => $differences, 'text' => self::text( $differences ) );
		} catch ( Throwable $e ) {
			return $empty;
		}
	}
	/** Resumen en una línea con los nombres (para avisos y correos: nunca un valor). '' si no hay diferencias. */
	public static function text( array $differences ) {
		if ( ! $differences ) return '';
		$groups = array( 'backup_only' => array(), 'server_only' => array(), 'different' => array() );
		foreach ( $differences as $row ) $groups[ $row['change'] ][] = $row['name'];
		$parts = array();
		if ( $groups['backup_only'] ) $parts[] = 'el respaldo define y este servidor no: ' . implode( ', ', $groups['backup_only'] );
		if ( $groups['server_only'] ) $parts[] = 'este servidor define y el respaldo no: ' . implode( ', ', $groups['server_only'] );
		if ( $groups['different'] ) $parts[] = 'con otro valor: ' . implode( ', ', $groups['different'] );
		return 'wp-config.php no se restaura y difiere del del respaldo (' . implode( '; ', $parts ) . '). Revisa si este sitio necesita esas constantes.';
	}
	/** Una línea por diferencia, con los valores que se pueden mostrar (WP-CLI y la pantalla). */
	public static function lines( array $differences ) {
		$reasons = array( 'secret' => 'valor oculto', 'path' => 'ruta del servidor', 'expression' => 'expresión' );
		$value = function( $row, $side ) use ( $reasons ) { return null !== ( $row[ $side ] ?? null ) ? var_export( (string) $row[ $side ], true ) : '(' . ( $reasons[ $row[ $side . '_hidden' ] ?? '' ] ?? 'oculto' ) . ')'; };
		$out = array();
		foreach ( $differences as $row ) {
			if ( 'backup_only' === $row['change'] ) $out[] = $row['name'] . ': sólo en el respaldo = ' . $value( $row, 'backup' );
			elseif ( 'server_only' === $row['change'] ) $out[] = $row['name'] . ': sólo en este servidor = ' . $value( $row, 'server' );
			else $out[] = $row['name'] . ': respaldo ' . $value( $row, 'backup' ) . ', este servidor ' . $value( $row, 'server' );
		}
		return $out;
	}
}
