<?php
defined( 'ABSPATH' ) || exit;

/**
 * Índice en disco de las entradas del manifiesto v3, por posición en el ZIP: registros fijos de 41 bytes (marca,
 * tamaño de 64 bits y SHA-256 binario) en un archivo, así que comprobar 17 000 o 2 000 000 de entradas cuesta la misma
 * memoria. La posición sale de `ZipArchive::locateName()` (tabla interna de libzip, fuera de `memory_limit`). Una entrada
 * del manifiesto que el ZIP no tiene, o dos que apuntan a la misma, rechazan el respaldo.
 *
 * Sin ruta usa `php://temp` (se borra al cerrarse); con ruta, el archivo queda para los pasos siguientes de un trabajo
 * de restauración.
 */
final class Digitalisimo_Backups_Manifest_Index {
	const RECORD = 41;
	/** @var resource */
	private $handle;
	private $count;

	private function __construct( $handle, $count ) {
		$this->handle = $handle;
		$this->count = (int) $count;
	}
	/** Índice vacío para $count posiciones (un archivo nuevo, o php://temp sin ruta). */
	public static function create( $count, $path = null ) {
		$handle = null === $path ? @fopen( 'php://temp/maxmemory:1048576', 'w+b' ) : @fopen( $path, 'w+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo preparar el índice del manifiesto.' );
		if ( null !== $path && DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		$index = new self( $handle, $count );
		$index->zero();
		return $index;
	}
	/** Índice ya construido de un trabajo (paso siguiente de una restauración). */
	public static function open( $path, $count ) {
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || (int) filesize( $path ) !== (int) $count * self::RECORD ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice del manifiesto ya no está completo; se volverá a leer el manifiesto.', 'inspect' );
		$handle = @fopen( $path, 'r+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir el índice del manifiesto.' );
		return new self( $handle, $count );
	}
	public function close() {
		if ( is_resource( $this->handle ) ) fclose( $this->handle );
		$this->handle = null;
	}
	private function zero() {
		$block = str_repeat( "\0", self::RECORD * 1024 );
		$left = $this->count;
		while ( $left > 0 ) {
			$n = min( 1024, $left );
			if ( self::RECORD * $n !== fwrite( $this->handle, 1024 === $n ? $block : str_repeat( "\0", self::RECORD * $n ) ) ) throw new Exception( 'No se pudo preparar el índice del manifiesto; revisa el espacio libre.' );
			$left -= $n;
		}
		fflush( $this->handle );
	}
	/**
	 * Anota la entrada de la posición $at ($sha256 en hexadecimal; null, una entrada que nunca coincide). Dos entradas en
	 * la misma posición rechazan el manifiesto.
	 */
	public function put( $at, $size, $sha256 ) {
		if ( $at < 0 || $at >= $this->count ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
		fseek( $this->handle, $at * self::RECORD );
		$flag = fread( $this->handle, 1 );
		if ( "\0" !== $flag ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
		fseek( $this->handle, $at * self::RECORD );
		$record = null === $sha256 ? "\2" . str_repeat( "\0", self::RECORD - 1 ) : "\1" . pack( 'J', max( 0, (int) $size ) ) . hex2bin( $sha256 );
		if ( self::RECORD !== fwrite( $this->handle, $record ) ) throw new Exception( 'No se pudo escribir el índice del manifiesto.' );
	}
	/** array( size, sha256 ) de la posición $at; false si el manifiesto la nombra con datos que nunca coinciden; null si no la nombra. */
	public function get( $at ) {
		if ( $at < 0 || $at >= $this->count ) return null;
		fseek( $this->handle, $at * self::RECORD );
		$record = fread( $this->handle, self::RECORD );
		if ( ! is_string( $record ) || self::RECORD !== strlen( $record ) || "\0" === $record[0] ) return null;
		if ( "\1" !== $record[0] ) return false;
		$size = unpack( 'J', substr( $record, 1, 8 ) );
		return array( 'size' => (int) $size[1], 'sha256' => bin2hex( substr( $record, 9, 32 ) ) );
	}
	public function flush() { if ( is_resource( $this->handle ) ) fflush( $this->handle ); }

	/** Una entrada del manifiesto v3 con el esquema estricto (la misma regla que Archive_Inspector::manifest()). */
	public static function valid_entry( $entry ) {
		return is_array( $entry ) && is_int( $entry['size'] ?? null ) && $entry['size'] >= 0 && is_string( $entry['sha256'] ?? null ) && 1 === preg_match( '/^[0-9a-f]{64}$/D', $entry['sha256'] );
	}

	/**
	 * Lee manifest.json de $zip por partes y, si trae un objeto `entries`, lo vuelca en un índice nuevo ($path, o
	 * php://temp). Con $strict, cada entrada debe cumplir el esquema v3 (si no, la excepción de siempre); sin él, una
	 * entrada mal formada queda anotada como que nunca coincide y la comparación con el ZIP la rechaza con su nombre.
	 * Devuelve array( 'data' => primer nivel sin `entries`, 'entries' => número de entradas o null, 'unmatched' =>
	 * entradas que el ZIP no tiene, 'index' => índice o null ).
	 */
	public static function build( $zip, $path = null, $strict = true ) {
		$index = self::create( (int) $zip->numFiles, $path );
		$used = false;
		$unmatched = 0;
		try {
			$read = Digitalisimo_Backups_Manifest_Stream::read( $zip, function( $name, $entry ) use ( $zip, $index, $strict, &$used, &$unmatched ) {
				$used = true;
				if ( $strict && ! self::valid_entry( $entry ) ) throw new Exception( 'El manifiesto del respaldo no es válido: entrada ' . self::shown( $name ) . '.' );
				$at = $zip->locateName( (string) $name );
				if ( false === $at ) { $unmatched++; return; }
				// Como la comparación de siempre: tamaño entero (o texto numérico) y SHA-256 en minúsculas.
				$valid = is_array( $entry ) && is_numeric( $entry['size'] ?? null ) && is_string( $entry['sha256'] ?? null ) && preg_match( '/^[0-9a-f]{64}$/D', $entry['sha256'] );
				$index->put( (int) $at, $valid ? (int) $entry['size'] : 0, $valid ? $entry['sha256'] : null );
			} );
		} catch ( Throwable $e ) {
			$index->close();
			if ( null !== $path ) @unlink( $path );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		$index->flush();
		if ( null === $read['entries'] && ! $used ) {
			$index->close();
			if ( null !== $path ) @unlink( $path );
			$index = null;
		}
		return array( 'data' => $read['data'], 'entries' => $read['entries'], 'unmatched' => $unmatched, 'index' => $index );
	}
	private static function shown( $name ) {
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $name );
		return strlen( $name ) > 120 ? substr( $name, 0, 117 ) . '…' : $name;
	}
}
