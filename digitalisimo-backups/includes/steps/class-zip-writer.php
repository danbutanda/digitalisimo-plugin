<?php
defined( 'ABSPATH' ) || exit;

/**
 * Escritor ZIP propio de sólo añadir (T2). ZipArchive escribe todo el archivo dentro de `close()`, un paso que no se
 * puede partir ni retomar, y reabrir un ZIP para añadirle entradas lo copia entero en cada cierre. Este escritor añade
 * cada entrada al final del archivo (cabecera local, datos y, al terminarla, su CRC y sus tamaños en la cabecera), así
 * que lo escrito antes de un punto de control nunca se reescribe: tras una muerte basta truncar al último punto de
 * control. Una entrada grande puede quedar suspendida entre dos tramos: sin comprimir se retoma en cualquier byte; con
 * deflate, el tramo termina con un vaciado completo (ZLIB_FULL_FLUSH) y el siguiente sigue con un compresor nuevo, que
 * produce el mismo flujo deflate válido. El estado de CRC-32 y SHA-256 se guarda serializado (PHP 8) o se reconstruye
 * leyendo lo ya escrito (PHP 7.4).
 *
 * El formato es el de siempre: entradas con método 0 o 8, nombres UTF-8 (bit 11), atributos Unix de archivo regular,
 * directorio central y, si hace falta (más de 65 535 entradas, 4 GiB o desplazamientos mayores), Zip64. El comentario
 * del archivo lleva la firma del manifiesto. ZipArchive (libzip) y las versiones anteriores del plugin lo leen igual.
 */
final class Digitalisimo_Backups_Zip_Writer {
	const CHUNK = 1048576;
	/** Desde este tamaño un archivo se guarda sin comprimir: deflate podría superar 4 GiB sin cabecera Zip64. */
	const STORE_FROM = 2147483648;
	const LIMIT32 = 0xFFFFFFFF;
	/** Formatos que ya vienen comprimidos: comprimirlos otra vez cuesta tiempo y no reduce nada. */
	const STORED = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'jxl', 'mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi', 'wmv', 'mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'flac', 'zip', 'gz', 'tgz', 'bz2', 'xz', 'lz', 'lzma', '7z', 'rar', 'zst', 'br', 'woff', 'woff2', 'jar', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'epub', 'apk', 'dgbk' );
	/** Sólo pruebas: escribe Zip64 aunque no haga falta. */
	public static $force_zip64 = false;
	/** Sólo pruebas: simula un PHP que no puede serializar el estado de un hash (7.4). */
	public static $no_hash_state = false;

	private $handle;
	private $path;
	private $size;
	private $entry = null;

	/** Abre el ZIP del área de trabajo y lo deja exactamente en `$committed` bytes (lo posterior es de un tramo muerto). */
	public function __construct( $path, $committed ) {
		$committed = (int) $committed;
		clearstatcache( true, $path );
		if ( is_link( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El ZIP del área de trabajo no puede ser un enlace simbólico.' );
		$exists = is_file( $path );
		if ( $committed > 0 && ( ! $exists || (int) filesize( $path ) < $committed ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'El ZIP del área de trabajo es más corto que su último punto de control.' );
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir el ZIP en el área de trabajo.' );
		if ( ! $exists && DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		if ( ! ftruncate( $handle, $committed ) || 0 !== fseek( $handle, $committed ) ) {
			fclose( $handle );
			throw new Exception( 'No se pudo preparar el ZIP del área de trabajo.' );
		}
		$this->handle = $handle;
		$this->path = $path;
		$this->size = $committed;
	}
	public function size() { return $this->size; }
	public function path() { return $this->path; }
	public function in_entry() { return null !== $this->entry; }
	/** Bytes ya escritos de la entrada abierta (sin la cabecera). */
	public function entry_written() { return $this->entry ? (int) $this->entry['z'] : 0; }
	public function entry_read() { return $this->entry ? (int) $this->entry['raw'] : 0; }

	/** Vacía a disco lo escrito: lo que se anote en el punto de control ya no se puede perder. */
	public function commit() {
		if ( ! fflush( $this->handle ) ) throw new Exception( 'No se pudo completar la escritura del ZIP en el área de trabajo.' );
		if ( function_exists( 'fsync' ) ) @fsync( $this->handle );
		return $this->size;
	}
	public function close() {
		if ( $this->handle ) fclose( $this->handle );
		$this->handle = null;
	}

	/** Método de una entrada: sin comprimir lo ya comprimido, lo enorme o si falta zlib. */
	public static function method_for( $name, $size ) {
		if ( ! function_exists( 'deflate_init' ) || $size >= self::STORE_FROM ) return 0;
		$extension = strtolower( (string) pathinfo( (string) $name, PATHINFO_EXTENSION ) );
		return in_array( $extension, self::STORED, true ) ? 0 : 8;
	}
	/** Una entrada puede quedar a medias entre tramos si su estado de CRC y SHA-256 se puede guardar o reconstruir. */
	public static function hash_state_supported() { return ! self::$no_hash_state && PHP_VERSION_ID >= 80000; }

	/**
	 * Abre una entrada: escribe su cabecera local con CRC y tamaños en cero (Zip64 si el archivo llega a 4 GiB) y
	 * prepara CRC-32, SHA-256 y, con método 8, el compresor.
	 */
	public function begin( $name, $mtime, $mode, $size, $method ) {
		if ( $this->entry ) throw new LogicException( 'Ya hay una entrada abierta en el ZIP.' );
		$name = (string) $name;
		if ( '' === $name || strlen( $name ) > 0xFFFF ) throw new Exception( 'Nombre de entrada no válido en el ZIP.' );
		$zip64 = self::$force_zip64 || $size >= self::LIMIT32;
		$flags = preg_match( '/[\x80-\xff]/', $name ) ? 0x0800 : 0;
		list( $time, $date ) = self::dos_time( $mtime );
		$extra = $zip64 ? pack( 'vv', 1, 16 ) . str_repeat( "\0", 16 ) : '';
		$header = "PK\x03\x04" . pack( 'vvvvvVVVvv', $zip64 ? 45 : 20, $flags, $method, $time, $date, 0, $zip64 ? self::LIMIT32 : 0, $zip64 ? self::LIMIT32 : 0, strlen( $name ), strlen( $extra ) ) . $name . $extra;
		$offset = $this->size;
		$this->write( $header );
		$this->entry = array(
			'n' => $name,
			'o' => $offset,
			'p' => $this->size,
			'm' => (int) $method,
			'f' => $flags,
			't' => $time,
			'd' => $date,
			'a' => ( ( 0100000 | ( (int) $mode & 0777 ?: 0644 ) ) << 16 ),
			'x' => $zip64,
			'raw' => 0,
			'z' => 0,
			'b' => array(),
		);
		$this->start_streams();
	}
	/** Añade bytes del archivo a la entrada abierta. */
	public function data( $chunk ) {
		if ( '' === $chunk ) return;
		$entry = &$this->entry;
		hash_update( $entry['crc'], $chunk );
		hash_update( $entry['sha'], $chunk );
		$entry['raw'] += strlen( $chunk );
		$out = $entry['deflate'] ? deflate_add( $entry['deflate'], $chunk, ZLIB_NO_FLUSH ) : $chunk;
		if ( false === $out ) throw new Exception( 'No se pudo comprimir ' . $entry['n'] . '.' );
		$this->write( $out );
		$entry['z'] += strlen( $out );
	}
	/**
	 * Deja la entrada a medias para el siguiente tramo con sus últimos bytes (`$last`, no vacío: deflate_add() no vacía
	 * nada con una entrada vacía salvo al terminar): los comprime con un vaciado completo, que acaba en un límite de
	 * bloque, y devuelve el estado de la entrada (sin recursos) para el punto de control.
	 */
	public function suspend( $last ) {
		if ( '' === (string) $last ) throw new LogicException( 'Una entrada sólo se suspende con bytes por escribir.' );
		$entry = &$this->entry;
		hash_update( $entry['crc'], $last );
		hash_update( $entry['sha'], $last );
		$entry['raw'] += strlen( $last );
		$out = $entry['deflate'] ? deflate_add( $entry['deflate'], $last, ZLIB_FULL_FLUSH ) : $last;
		if ( false === $out ) throw new Exception( 'No se pudo comprimir ' . $entry['n'] . '.' );
		$this->write( $out );
		$entry['z'] += strlen( $out );
		if ( $entry['deflate'] ) $entry['b'][] = array( $entry['z'], $entry['raw'] );
		$state = $entry;
		unset( $state['crc'], $state['sha'], $state['deflate'] );
		$state['hc'] = self::export_hash( $entry['crc'] );
		$state['hs'] = self::export_hash( $entry['sha'] );
		unset( $entry );
		$this->entry = null;
		return $state;
	}
	/** Retoma una entrada suspendida: el ZIP ya está truncado a su último byte escrito (`p` + `z`). */
	public function resume( array $state ) {
		if ( $this->entry ) throw new LogicException( 'Ya hay una entrada abierta en el ZIP.' );
		foreach ( array( 'n', 'o', 'p', 'm', 'f', 't', 'd', 'a', 'x', 'raw', 'z', 'b' ) as $key ) if ( ! array_key_exists( $key, $state ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'La entrada a medias del ZIP no tiene un estado completo.' );
		if ( (int) $state['p'] + (int) $state['z'] !== $this->size ) throw new Digitalisimo_Backups_Resume_Invalid( 'La entrada a medias no termina donde termina el ZIP.' );
		$this->check_local_header( $state );
		$this->entry = array_intersect_key( $state, array_flip( array( 'n', 'o', 'p', 'm', 'f', 't', 'd', 'a', 'x', 'raw', 'z', 'b' ) ) );
		$crc = self::import_hash( $state['hc'] ?? null );
		$sha = self::import_hash( $state['hs'] ?? null );
		if ( ! $crc || ! $sha ) {
			list( $crc, $sha, $raw ) = self::hash_region( $this->path, $state['p'], (int) $state['m'], 0, (int) $state['z'] );
			if ( $raw !== (int) $state['raw'] ) throw new Digitalisimo_Backups_Resume_Invalid( 'Lo escrito de la entrada a medias no coincide con su punto de control.' );
		}
		$this->entry['crc'] = $crc;
		$this->entry['sha'] = $sha;
		$this->entry['deflate'] = $this->entry['m'] ? self::deflater() : null;
	}
	/** Cierra la entrada: termina el flujo, completa CRC y tamaños en la cabecera local y devuelve su registro. */
	public function finish() {
		$entry = &$this->entry;
		if ( $entry['deflate'] ) {
			$out = deflate_add( $entry['deflate'], '', ZLIB_FINISH );
			if ( false === $out ) throw new Exception( 'No se pudo comprimir ' . $entry['n'] . '.' );
			$this->write( $out );
			$entry['z'] += strlen( $out );
		}
		$crc = hash_final( $entry['crc'] );
		$sha = hash_final( $entry['sha'] );
		if ( ! $entry['x'] && ( $entry['raw'] >= self::LIMIT32 || $entry['z'] >= self::LIMIT32 ) ) throw new Exception( 'El archivo ' . $entry['n'] . ' creció más allá de 4 GiB mientras se leía; se volverá a intentar.' );
		$patch = strrev( hex2bin( $crc ) ) . pack( 'VV', $entry['x'] ? self::LIMIT32 : $entry['z'], $entry['x'] ? self::LIMIT32 : $entry['raw'] );
		$this->patch( $entry['o'] + 14, $patch );
		if ( $entry['x'] ) $this->patch( $entry['o'] + 30 + strlen( $entry['n'] ) + 4, self::u64( $entry['raw'] ) . self::u64( $entry['z'] ) );
		$record = array( 'n' => $entry['n'], 'o' => $entry['o'], 'm' => $entry['m'], 'f' => $entry['f'], 't' => $entry['t'], 'd' => $entry['d'], 'a' => $entry['a'], 'x' => $entry['x'], 'c' => $crc, 'z' => $entry['z'], 's' => $entry['raw'], 'h' => $sha );
		if ( $entry['b'] ) $record['b'] = $entry['b'];
		unset( $entry );
		$this->entry = null;
		return $record;
	}
	/** Entrada completa desde una cadena (el manifiesto se escribe por partes con begin/data/finish). */
	public function add_string( $name, $bytes, $mtime ) {
		$this->begin( $name, $mtime, 0644, strlen( $bytes ), self::method_for( 'x.txt', strlen( $bytes ) ) );
		$this->data( $bytes );
		return $this->finish();
	}

	/**
	 * Directorio central y fin de archivo. `$records` es un callable que entrega cada registro en el orden de las
	 * entradas. Zip64 sólo si hace falta: más de 65 535 entradas o tamaños y desplazamientos de 4 GiB.
	 */
	public function close_archive( callable $records, $comment ) {
		$comment = (string) $comment;
		if ( strlen( $comment ) > 0xFFFF ) throw new Exception( 'El comentario del ZIP es demasiado largo.' );
		$start = $this->size;
		$count = 0;
		foreach ( $records() as $record ) {
			$this->write( self::central_header( $record ) );
			$count++;
		}
		$length = $this->size - $start;
		if ( self::$force_zip64 || $count >= 0xFFFF || $start >= self::LIMIT32 || $length >= self::LIMIT32 ) {
			$record64 = $this->size;
			$this->write( "PK\x06\x06" . self::u64( 44 ) . pack( 'vvVV', ( 3 << 8 ) | 45, 45, 0, 0 ) . self::u64( $count ) . self::u64( $count ) . self::u64( $length ) . self::u64( $start ) );
			$this->write( "PK\x06\x07" . pack( 'V', 0 ) . self::u64( $record64 ) . pack( 'V', 1 ) );
		}
		$this->write( "PK\x05\x06" . pack( 'vvvvVVv', 0, 0, min( $count, 0xFFFF ), min( $count, 0xFFFF ), min( $length, self::LIMIT32 ), min( $start, self::LIMIT32 ), strlen( $comment ) ) . $comment );
		return $count;
	}

	/** Cabecera del directorio central de un registro; los mismos campos que su cabecera local. */
	public static function central_header( array $record ) {
		$name = (string) $record['n'];
		$size = (int) $record['s'];
		$comp = (int) $record['z'];
		$offset = (int) $record['o'];
		$wide = ! empty( $record['x'] ) || self::$force_zip64;
		$fields = '';
		$size32 = $size;
		$comp32 = $comp;
		$offset32 = $offset;
		if ( $wide || $size >= self::LIMIT32 ) { $fields .= self::u64( $size ); $size32 = self::LIMIT32; }
		if ( $wide || $comp >= self::LIMIT32 ) { $fields .= self::u64( $comp ); $comp32 = self::LIMIT32; }
		if ( self::$force_zip64 || $offset >= self::LIMIT32 ) { $fields .= self::u64( $offset ); $offset32 = self::LIMIT32; }
		$extra = '' === $fields ? '' : pack( 'vv', 1, strlen( $fields ) ) . $fields;
		$version = '' === $extra ? 20 : 45;
		return "PK\x01\x02" . pack( 'vvvvvv', ( 3 << 8 ) | $version, $version, (int) $record['f'], (int) $record['m'], (int) $record['t'], (int) $record['d'] ) . strrev( hex2bin( (string) $record['c'] ) ) . pack( 'VVvvvvvVV', $comp32, $size32, strlen( $name ), strlen( $extra ), 0, 0, 0, (int) $record['a'], $offset32 ) . $name . $extra;
	}
	/** Cabecera local que corresponde a un registro terminado (para comprobarla al retomar o verificar). */
	public static function local_header( array $record ) {
		$zip64 = ! empty( $record['x'] );
		$extra = $zip64 ? pack( 'vv', 1, 16 ) . self::u64( (int) $record['s'] ) . self::u64( (int) $record['z'] ) : '';
		return "PK\x03\x04" . pack( 'vvvvv', $zip64 ? 45 : 20, (int) $record['f'], (int) $record['m'], (int) $record['t'], (int) $record['d'] ) . strrev( hex2bin( (string) $record['c'] ) ) . pack( 'VVvv', $zip64 ? self::LIMIT32 : (int) $record['z'], $zip64 ? self::LIMIT32 : (int) $record['s'], strlen( (string) $record['n'] ), strlen( $extra ) ) . $record['n'] . $extra;
	}
	/** Inicio de los datos de una entrada a partir de su registro. */
	public static function data_offset( array $record ) { return (int) $record['o'] + 30 + strlen( (string) $record['n'] ) + ( empty( $record['x'] ) ? 0 : 20 ); }

	/**
	 * CRC-32 y SHA-256 de los datos de una entrada entre dos desplazamientos de lo escrito. `$from` es 0 o un límite de
	 * bloque (donde empezó un compresor nuevo tras un vaciado completo): desde ahí se descomprime con un descompresor
	 * nuevo. Devuelve [contexto CRC, contexto SHA, bytes del archivo]; con `$contexts` continúa unos ya empezados.
	 */
	public static function hash_region( $path, $data_offset, $method, $from, $to, $contexts = null ) {
		$crc = $contexts ? $contexts[0] : hash_init( 'crc32b' );
		$sha = $contexts ? $contexts[1] : hash_init( 'sha256' );
		$raw = 0;
		$in = @fopen( $path, 'rb' );
		if ( ! $in ) throw new Exception( 'No se pudo leer el ZIP del área de trabajo.' );
		try {
			if ( 0 !== fseek( $in, (int) $data_offset + (int) $from ) ) throw new Exception( 'No se pudo situar la lectura del ZIP.' );
			$position = (int) $from;
			$inflate = $method ? inflate_init( ZLIB_ENCODING_RAW ) : null;
			while ( $position < $to ) {
				$chunk = fread( $in, (int) min( self::CHUNK, $to - $position ) );
				if ( false === $chunk || '' === $chunk ) throw new Exception( 'El ZIP del área de trabajo está truncado.' );
				$position += strlen( $chunk );
				$out = $inflate ? @inflate_add( $inflate, $chunk, ZLIB_SYNC_FLUSH ) : $chunk;
				if ( false === $out ) throw new Exception( 'Los datos comprimidos del ZIP están dañados.' );
				hash_update( $crc, $out );
				hash_update( $sha, $out );
				$raw += strlen( $out );
			}
		} finally {
			fclose( $in );
		}
		return array( $crc, $sha, $raw );
	}

	public static function dos_time( $timestamp ) {
		$parts = array_map( 'intval', explode( ' ', gmdate( 'Y n j G i s', max( 315532800, min( 4354819199, (int) $timestamp ) ) ) ) );
		return array( ( $parts[3] << 11 ) | ( $parts[4] << 5 ) | ( $parts[5] >> 1 ), ( ( $parts[0] - 1980 ) << 9 ) | ( $parts[1] << 5 ) | $parts[2] );
	}
	public static function u64( $value ) {
		$value = (int) $value;
		return PHP_INT_SIZE >= 8 ? pack( 'P', $value ) : pack( 'VV', $value, 0 );
	}
	private static function export_hash( $context ) {
		if ( ! self::hash_state_supported() ) return null;
		try {
			return base64_encode( serialize( $context ) );
		} catch ( Throwable $e ) {
			return null;
		}
	}
	private static function import_hash( $state ) {
		if ( ! is_string( $state ) || '' === $state || ! self::hash_state_supported() ) return null;
		try {
			$context = @unserialize( (string) base64_decode( $state, true ), array( 'allowed_classes' => array( 'HashContext' ) ) );
		} catch ( Throwable $e ) {
			return null;
		}
		return $context instanceof HashContext ? $context : null;
	}
	private static function deflater() {
		$level = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_zip_level', 6 ) : 6;
		$context = deflate_init( ZLIB_ENCODING_RAW, array( 'level' => max( 1, min( 9, $level ) ) ) );
		if ( false === $context ) throw new Exception( 'No se pudo iniciar la compresión del ZIP.' );
		return $context;
	}
	private function start_streams() {
		$this->entry['crc'] = hash_init( 'crc32b' );
		$this->entry['sha'] = hash_init( 'sha256' );
		$this->entry['deflate'] = $this->entry['m'] ? self::deflater() : null;
	}
	/** Al retomar, la cabecera local escrita es la de la entrada del punto de control. */
	private function check_local_header( array $state ) {
		$expected = "PK\x03\x04";
		$in = @fopen( $this->path, 'rb' );
		$bytes = false;
		if ( $in ) {
			if ( 0 === fseek( $in, (int) $state['o'] ) ) $bytes = fread( $in, 30 + strlen( (string) $state['n'] ) );
			fclose( $in );
		}
		if ( ! is_string( $bytes ) || $expected !== substr( $bytes, 0, 4 ) || substr( $bytes, 30 ) !== (string) $state['n'] || (int) $state['m'] !== unpack( 'v', substr( $bytes, 8, 2 ) )[1] ) throw new Digitalisimo_Backups_Resume_Invalid( 'La cabecera de la entrada a medias no coincide con su punto de control.' );
	}
	private function patch( $offset, $bytes ) {
		if ( 0 !== fseek( $this->handle, (int) $offset ) || strlen( $bytes ) !== fwrite( $this->handle, $bytes ) || 0 !== fseek( $this->handle, $this->size ) ) throw new Exception( 'No se pudo completar la cabecera de una entrada del ZIP.' );
	}
	private function write( $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $this->handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir el ZIP en el área de trabajo; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
		$this->size += $length;
	}
}
