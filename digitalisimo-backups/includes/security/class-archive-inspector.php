<?php
defined( 'ABSPATH' ) || exit;

/**
 * Validación de archivos que pueden venir de fuera: sin efectos secundarios, antes de la copia previa y antes de
 * cualquier consulta o escritura. Garantiza que nada salga de la raíz de WordPress ni de sus tablas, que no agote el
 * disco y que no traiga entradas ocultas; no garantiza que el contenido sea benigno.
 */
final class Digitalisimo_Backups_Archive_Inspector {
	const MAX_NAME = 4096;
	const MAX_SEGMENT = 255;
	const MARGIN = 67108864;
	const ROOT_FILES = array( 'manifest.json', 'database.sql', 'migration.json', 'MIGRAR-A-WORDPRESS-UNICO.txt' );

	public static function max_entries() { return defined( 'DIGITALISIMO_BACKUPS_MAX_ENTRIES' ) ? max( 1, (int) DIGITALISIMO_BACKUPS_MAX_ENTRIES ) : 2000000; }
	private static function shown( $name ) {
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $name );
		return strlen( $name ) > 120 ? substr( $name, 0, 117 ) . '…' : $name;
	}

	/**
	 * Revisa nombres, tipos, métodos y tamaños declarados de todas las entradas. $layout es 'digitalisimo' (raíces
	 * del respaldo propio) o 'loose' (piezas de UpdraftPlus). Un respaldo confiable conserva la conversión de «\» a
	 * «/»; uno no confiable con «\» se rechaza.
	 */
	public static function entries( $zip, $trusted, $layout = 'digitalisimo' ) {
		$count = (int) $zip->numFiles;
		if ( $count > self::max_entries() ) throw new Exception( 'El ZIP tiene ' . $count . ' entradas; el máximo permitido es ' . self::max_entries() . ' (DIGITALISIMO_BACKUPS_MAX_ENTRIES).' );
		// Memoria acotada (H1): los duplicados exactos los reconoce la tabla de nombres de libzip (fuera de memory_limit) y
		// sólo se guardan los nombres con «\\». Las colisiones de mayúsculas sólo importan en un disco que no las distingue:
		// con pocas entradas, un entero de 64 bits por nombre en minúsculas en memoria (como siempre, sin sondear el disco);
		// con muchas, se sondea el disco primero y, si no distingue mayúsculas, la tabla va en un archivo temporal, así que
		// 100 000 o 2 000 000 de entradas caben en 64 MB (revisión, M4).
		$converted = array();
		$folded = $count <= self::FOLD_IN_MEMORY ? array() : null;
		$table = null;
		if ( null === $folded && self::case_insensitive( ABSPATH ) ) $table = self::fold_table( $count );
		$collisions = false;
		$declared = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! is_array( $stat ) || ! isset( $stat['name'], $stat['size'] ) ) throw new Exception( 'Una entrada ZIP no tiene metadatos completos.' );
			$raw = (string) $stat['name'];
			if ( '' === $raw || strlen( $raw ) > self::MAX_NAME || false !== strpos( $raw, "\0" ) ) throw new Exception( 'El ZIP contiene una ruta no permitida: ' . self::shown( $raw ) );
			if ( false !== strpos( $raw, '\\' ) && ! $trusted ) throw new Exception( 'El ZIP contiene una ruta no permitida (barra invertida en un archivo sin firma): ' . self::shown( $raw ) );
			$name = str_replace( '\\', '/', $raw );
			$directory = '/' === substr( $name, -1 );
			$body = $directory ? substr( $name, 0, -1 ) : $name;
			if ( '' === $body || '/' === $name[0] || preg_match( '/^[A-Za-z]:/', $name ) ) throw new Exception( 'El ZIP contiene una ruta no permitida: ' . self::shown( $raw ) );
			$segments = explode( '/', $body );
			foreach ( $segments as $segment ) if ( '' === $segment || '.' === $segment || '..' === $segment || strlen( $segment ) > self::MAX_SEGMENT ) throw new Exception( 'El ZIP contiene una ruta no permitida: ' . self::shown( $raw ) );
			if ( 'digitalisimo' === $layout && ! ( 'wordpress' === $segments[0] && ( count( $segments ) > 1 || $directory ) ) && ( $directory || count( $segments ) > 1 || ! in_array( $body, self::ROOT_FILES, true ) ) ) throw new Exception( 'El ZIP contiene una entrada fuera de las raíces de un respaldo de DIGITALÍSIMO: ' . self::shown( $raw ) );
			if ( method_exists( $zip, 'getExternalAttributesIndex' ) && $zip->getExternalAttributesIndex( $i, $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys ) {
				$type = ( $attributes >> 16 ) & 0170000;
				if ( ! in_array( $type, array( 0, 0100000, 040000 ), true ) ) throw new Exception( 'El ZIP contiene un enlace simbólico o un archivo especial: ' . self::shown( $raw ) );
			}
			if ( 0 !== (int) ( $stat['encryption_method'] ?? 0 ) ) throw new Exception( 'El ZIP contiene una entrada cifrada que no se puede verificar: ' . self::shown( $raw ) );
			if ( ! in_array( (int) ( $stat['comp_method'] ?? 0 ), array( 0, 8 ), true ) ) throw new Exception( 'El ZIP usa un método de compresión no admitido en ' . self::shown( $raw ) . '.' );
			if ( $zip->locateName( $raw ) !== $i ) throw new Exception( 'El ZIP contiene nombres de archivo duplicados: ' . self::shown( $raw ) );
			if ( $name !== $raw ) {
				$other = $zip->locateName( $name );
				if ( false !== $other && $other !== $i || isset( $converted[ $name ] ) ) throw new Exception( 'El ZIP contiene nombres de archivo duplicados: ' . self::shown( $raw ) );
				$converted[ $name ] = true;
			} elseif ( isset( $converted[ $name ] ) ) {
				throw new Exception( 'El ZIP contiene nombres de archivo duplicados: ' . self::shown( $raw ) );
			}
			if ( null !== $folded ) {
				$lower = self::key64( strtolower( $body ) );
				$exact = md5( $body, true );
				if ( isset( $folded[ $lower ] ) && $folded[ $lower ] !== $exact ) $collisions = true;
				$folded[ $lower ] = $exact;
			} elseif ( null !== $table && self::fold_collides( $table, $zip, $i, $body ) ) {
				$collisions = true;
			}
			if ( ! $directory ) $declared += max( 0, (int) $stat['size'] );
		}
		if ( $table ) fclose( $table['handle'] );
		if ( $collisions && ( null === $folded || self::case_insensitive( ABSPATH ) ) ) throw new Exception( 'El ZIP contiene archivos que sólo se distinguen por mayúsculas y el disco de destino no las distingue.' );
		return array( 'count' => $count, 'declared' => $declared );
	}
	/** Entradas hasta las que las colisiones de mayúsculas se buscan en memoria (unos 2 MB). */
	const FOLD_IN_MEMORY = 20000;
	/** Tabla hash en un archivo temporal: registros de 12 bytes (8 del MD5 del nombre en minúsculas y la posición + 1). */
	private static function fold_table( $count ) {
		$slots = 1;
		while ( $slots < 2 * max( 1, (int) $count ) ) $slots <<= 1;
		$handle = tmpfile();
		if ( ! $handle || ! ftruncate( $handle, $slots * 12 ) ) throw new Exception( 'No se pudo preparar la comprobación de nombres del ZIP en un archivo temporal.' );
		return array( 'handle' => $handle, 'slots' => $slots );
	}
	/** Anota la entrada $index en la tabla; true si otra entrada tiene el mismo nombre salvo mayúsculas. */
	private static function fold_collides( array $table, $zip, $index, $body ) {
		$lower = strtolower( $body );
		$key = substr( md5( $lower, true ), 0, 8 );
		$slot = unpack( 'N', $key )[1] & ( $table['slots'] - 1 );
		for ( $probes = 0; $probes < $table['slots']; $probes++ ) {
			fseek( $table['handle'], $slot * 12 );
			$record = (string) fread( $table['handle'], 12 );
			$other = 12 === strlen( $record ) ? unpack( 'N', substr( $record, 8, 4 ) )[1] : 0;
			if ( 0 === $other ) {
				fseek( $table['handle'], $slot * 12 );
				fwrite( $table['handle'], $key . pack( 'N', $index + 1 ) );
				return false;
			}
			if ( substr( $record, 0, 8 ) === $key ) {
				$name = rtrim( str_replace( '\\', '/', (string) $zip->getNameIndex( $other - 1 ) ), '/' );
				if ( strtolower( $name ) === $lower ) return $name !== $body;
			}
			$slot = ( $slot + 1 ) & ( $table['slots'] - 1 );
		}
		return false;
	}
	/** Entero de 64 bits de un nombre (clave compacta de un array; una colisión sólo puede rechazar de más). */
	private static function key64( $text ) {
		$value = unpack( 'J', substr( md5( $text, true ), 0, 8 ) );
		return (int) $value[1];
	}
	/** Sonda del sistema de archivos de destino; sólo se usa si hay nombres que chocan sin mayúsculas. */
	public static function case_insensitive( $directory ) {
		if ( function_exists( 'apply_filters' ) ) {
			$forced = apply_filters( 'digitalisimo_backups_case_insensitive', null, $directory );
			if ( null !== $forced ) return (bool) $forced;
		}
		$base = rtrim( $directory, '/\\' ) . '/.digitalisimo-case-' . bin2hex( random_bytes( 6 ) ) . '-';
		if ( false === @file_put_contents( $base . 'a', '' ) ) return true; // Sin sonda se falla cerrado.
		clearstatcache();
		$insensitive = file_exists( $base . 'A' );
		@unlink( $base . 'a' );
		return $insensitive;
	}

	/**
	 * Esquema estricto del manifiesto v3; devuelve el primer nivel del manifiesto decodificado, sin `entries` (con
	 * `entries_count`). Se lee por partes con memoria constante (Digitalisimo_Backups_Manifest_Stream): con 64 MB, el
	 * manifiesto de un sitio de ~17 000 archivos no cabía decodificado entero (H1). Las entradas se comprueban de una en una
	 * con la misma regla; la comparación con el contenido del ZIP la hace la verificación del contenido.
	 */
	public static function manifest( $zip ) {
		if ( ! $zip->statName( 'manifest.json' ) ) throw new Exception( 'Falta el manifiesto del respaldo completo.' );
		$read = Digitalisimo_Backups_Manifest_Stream::read( $zip, function( $name, $entry ) {
			if ( ! Digitalisimo_Backups_Manifest_Index::valid_entry( $entry ) ) throw new Exception( 'El manifiesto del respaldo no es válido: entrada ' . self::shown( $name ) . '.' );
		} );
		$data = $read['data'];
		if ( ! is_array( $data ) ) throw new Exception( 'El manifiesto del respaldo no es JSON válido.' );
		$invalid = function( $what ) { return new Exception( 'El manifiesto del respaldo no es válido: ' . $what . '.' ); };
		if ( ! is_string( $data['backup_id'] ?? null ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $data['backup_id'] ) ) throw $invalid( 'identificador' );
		if ( ! is_string( $data['table_prefix'] ?? null ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $data['table_prefix'] ) ) throw $invalid( 'prefijo de tablas' );
		if ( ! is_array( $data['database_tables'] ?? null ) || array_values( $data['database_tables'] ) !== $data['database_tables'] ) throw $invalid( 'lista de tablas' );
		foreach ( $data['database_tables'] as $table ) if ( ! is_string( $table ) || '' === $table || strlen( $table ) > 192 || false !== strpos( $table, "\0" ) ) throw $invalid( 'nombre de tabla' );
		$scheme = is_string( $data['home_url'] ?? null ) ? strtolower( (string) parse_url( $data['home_url'], PHP_URL_SCHEME ) ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) throw $invalid( 'URL del sitio' );
		if ( isset( $data['multisite'] ) && ! is_bool( $data['multisite'] ) ) throw $invalid( 'tipo de instalación' );
		if ( ! is_int( $data['file_count'] ?? null ) || $data['file_count'] < 0 || ! is_int( $data['uncompressed_size'] ?? null ) || $data['uncompressed_size'] < 0 ) throw $invalid( 'totales' );
		// Antes, `entries` que no era un objeto se recorría como lista: un escalar (null, texto) no tenía entradas que revisar.
		if ( array_key_exists( 'entries', $data ) && is_array( $data['entries'] ) ) {
			foreach ( $data['entries'] as $name => $entry ) if ( ! Digitalisimo_Backups_Manifest_Index::valid_entry( $entry ) ) throw $invalid( 'entrada ' . self::shown( $name ) );
		}
		unset( $data['entries'] );
		$data['entries_count'] = null === $read['entries'] ? 0 : (int) $read['entries'];
		return $data;
	}

	/**
	 * Espacio en el disco de destino: lo que crecen los archivos que se sobrescriben o se crean, más el temporal del
	 * mayor archivo y un margen. Si el servidor no informa el espacio libre, se sigue: la restaurabilidad manda.
	 */
	public static function space( $zip, $root, $prefix = 'wordpress/', $skip = array( 'wp-config.php' ) ) {
		$root = rtrim( wp_normalize_path( $root ), '/' ) . '/';
		$growth = 0;
		$largest = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = str_replace( '\\', '/', (string) $stat['name'] );
			if ( '/' === substr( $name, -1 ) || ( '' !== $prefix && 0 !== strpos( $name, $prefix ) ) ) continue;
			$relative = substr( $name, strlen( $prefix ) );
			if ( in_array( $relative, $skip, true ) ) continue;
			$size = max( 0, (int) $stat['size'] );
			$existing = is_file( $root . $relative ) ? (int) @filesize( $root . $relative ) : 0;
			$growth += max( 0, $size - $existing );
			$largest = max( $largest, $size );
		}
		$needed = $growth + $largest + self::MARGIN;
		$free = self::free_space( $root );
		if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio suficiente para restaurar: se necesitan ' . self::megabytes( $needed ) . ' libres en el disco de WordPress y hay ' . self::megabytes( $free ) . '.' );
		return array( 'needed' => $needed, 'free' => $free );
	}
	/** Bytes libres, o null si el servidor deshabilitó disk_free_space(). */
	public static function free_space( $path ) {
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( $path ) : false;
		$free = false === $free ? null : (float) $free;
		return function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_free_space', $free, $path ) : $free;
	}
	public static function megabytes( $bytes ) { return number_format( $bytes / 1048576, 1, ',', '.' ) . ' MB'; }
}
