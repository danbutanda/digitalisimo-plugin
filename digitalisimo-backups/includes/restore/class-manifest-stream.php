<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lectura de `manifest.json` por fragmentos, con memoria constante (H1: con 64 MB, `json_decode()` del manifiesto de un
 * sitio de ~17 000 archivos agotaba la memoria al restaurar y al verificar la copia previa).
 *
 * Recorre el objeto de primer nivel sin cargarlo entero: cada valor de primer nivel se decodifica con `json_decode()`
 * de su texto exacto (un valor repetido: gana el último, como en `json_decode()`), salvo `entries`, cuyos miembros se
 * entregan de uno en uno a `$on_entry( $nombre, $entrada )`. La estructura (llaves, comas, dos puntos, cadenas y el final
 * del archivo) se comprueba como lo haría `json_decode()`; lo que éste rechazaría se rechaza. Dos claves `entries` se
 * rechazan (con `json_decode()` la segunda borraría la primera). `$on_bytes` recibe cada fragmento tal cual se leyó, para
 * calcular el MAC de la firma por partes. Un valor de primer nivel que no es `entries` no puede pasar de 16 MiB.
 */
final class Digitalisimo_Backups_Manifest_Stream {
	const CHUNK = 1048576;
	const MAX_VALUE = 16777216;
	/** Profundidad de json_decode() para un valor de primer nivel (el manifiesto entero se leía con 64). */
	const DEPTH = 63;

	private $stream;
	private $buf = '';
	private $pos = 0;
	private $eof = false;
	private $read = 0;
	private $on_bytes;

	/**
	 * Lee manifest.json de $zip. Devuelve array( 'data' => claves de primer nivel sin `entries`, 'entries' => número de
	 * miembros de `entries` (null si no es un objeto), 'size' => bytes leídos ). Lanza Exception si no es JSON válido o
	 * no es un objeto, con el mensaje de siempre.
	 */
	public static function read( $zip, $on_entry = null, $on_bytes = null ) {
		$stat = $zip->statName( 'manifest.json' );
		if ( ! $stat ) throw new Exception( 'Falta el manifiesto del respaldo completo.' );
		if ( (int) $stat['size'] > Digitalisimo_Backups_Manifest_Auth::MAX_MANIFEST ) throw new Exception( 'El manifiesto del respaldo supera el tamaño permitido.' );
		$stream = $zip->getStream( 'manifest.json' );
		if ( ! $stream ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		$reader = new self( $stream, $on_bytes );
		try {
			$result = $reader->document( $on_entry );
		} finally {
			fclose( $stream );
		}
		if ( $reader->read !== (int) $stat['size'] ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		return $result + array( 'size' => $reader->read );
	}

	private function __construct( $stream, $on_bytes ) {
		$this->stream = $stream;
		$this->on_bytes = $on_bytes;
	}
	private static function invalid() { return new Exception( 'El manifiesto del respaldo no es JSON válido.' ); }

	private function document( $on_entry ) {
		$data = array();
		$entries = null;
		$this->ws();
		if ( '{' !== $this->peek() ) throw self::invalid();
		$this->pos++;
		$this->ws();
		if ( '}' === $this->peek() ) {
			$this->pos++;
		} else {
			while ( true ) {
				$key = $this->key();
				$this->ws();
				if ( '{' === $this->peek() && 'entries' === $key ) {
					if ( null !== $entries ) throw new Exception( 'El manifiesto del respaldo no es válido: entradas repetidas.' );
					$entries = $this->entries( $on_entry );
					unset( $data['entries'] );
				} else {
					if ( 'entries' === $key ) {
						if ( null !== $entries ) throw new Exception( 'El manifiesto del respaldo no es válido: entradas repetidas.' );
						$entries = -1;
					}
					$raw = $this->value_raw();
					$value = json_decode( $raw, true, self::DEPTH );
					if ( null === $value && 'null' !== $raw ) throw self::invalid();
					$data[ $key ] = $value;
				}
				$this->ws();
				$char = $this->next();
				if ( '}' === $char ) break;
				if ( ',' !== $char ) throw self::invalid();
				$this->ws();
			}
		}
		$this->ws();
		if ( '' !== $this->peek() ) throw self::invalid();
		return array( 'data' => $data, 'entries' => null === $entries || $entries < 0 ? null : $entries );
	}
	/** Miembros de `entries`, de uno en uno; devuelve cuántos hubo. */
	private function entries( $on_entry ) {
		$count = 0;
		$this->pos++;
		$this->ws();
		if ( '}' === $this->peek() ) { $this->pos++; return 0; }
		while ( true ) {
			$name = $this->key();
			$this->ws();
			$raw = $this->value_raw();
			$entry = json_decode( $raw, true, self::DEPTH - 1 );
			if ( null === $entry && 'null' !== $raw ) throw self::invalid();
			if ( $on_entry ) call_user_func( $on_entry, $name, $entry );
			$count++;
			$this->ws();
			$char = $this->next();
			if ( '}' === $char ) return $count;
			if ( ',' !== $char ) throw self::invalid();
			$this->ws();
		}
	}
	/** Una clave de objeto seguida de «:». */
	private function key() {
		if ( '"' !== $this->peek() ) throw self::invalid();
		$key = json_decode( $this->string_raw(), true );
		if ( ! is_string( $key ) ) throw self::invalid();
		$this->ws();
		if ( ':' !== $this->next() ) throw self::invalid();
		return $key;
	}
	/** Texto exacto de un valor JSON (cadena, objeto, lista o escalar); json_decode() lo valida después. */
	private function value_raw() {
		$char = $this->peek();
		if ( '"' === $char ) return $this->string_raw();
		if ( '{' === $char || '[' === $char ) return $this->nested_raw();
		$start = $this->pos;
		while ( true ) {
			$run = strcspn( $this->buf, ",}] \t\n\r", $this->pos );
			$this->pos += $run;
			if ( $this->pos < strlen( $this->buf ) || $this->eof ) break;
			$this->fill( $start );
			$start = 0;
		}
		$raw = substr( $this->buf, $start, $this->pos - $start );
		if ( '' === $raw ) throw self::invalid();
		return $raw;
	}
	private function string_raw() {
		$start = $this->pos;
		$this->pos++;
		$escaped = false;
		while ( true ) {
			$length = strlen( $this->buf );
			while ( $this->pos < $length ) {
				if ( $escaped ) { $escaped = false; $this->pos++; continue; }
				$run = strcspn( $this->buf, '"\\', $this->pos );
				$this->pos += $run;
				if ( $this->pos >= $length ) break;
				if ( '\\' === $this->buf[ $this->pos ] ) { $escaped = true; $this->pos++; continue; }
				$this->pos++;
				$raw = substr( $this->buf, $start, $this->pos - $start );
				if ( strlen( $raw ) > self::MAX_VALUE ) throw new Exception( 'El manifiesto del respaldo no es válido: un valor demasiado grande.' );
				return $raw;
			}
			if ( $this->eof ) throw self::invalid();
			if ( $this->pos - $start > self::MAX_VALUE ) throw new Exception( 'El manifiesto del respaldo no es válido: un valor demasiado grande.' );
			$offset = $this->pos - $start;
			$this->fill( $start );
			$start = 0;
			$this->pos = $offset;
		}
	}
	private function nested_raw() {
		$start = $this->pos;
		$depth = 0;
		$quoted = false;
		$escaped = false;
		while ( true ) {
			$length = strlen( $this->buf );
			while ( $this->pos < $length ) {
				if ( $quoted ) {
					if ( $escaped ) { $escaped = false; $this->pos++; continue; }
					$run = strcspn( $this->buf, '"\\', $this->pos );
					$this->pos += $run;
					if ( $this->pos >= $length ) break;
					$char = $this->buf[ $this->pos++ ];
					if ( '\\' === $char ) $escaped = true; else $quoted = false;
					continue;
				}
				$run = strcspn( $this->buf, '"{}[]', $this->pos );
				$this->pos += $run;
				if ( $this->pos >= $length ) break;
				$char = $this->buf[ $this->pos++ ];
				if ( '"' === $char ) { $quoted = true; continue; }
				if ( '{' === $char || '[' === $char ) { $depth++; continue; }
				$depth--;
				if ( $depth < 0 ) throw self::invalid();
				if ( 0 === $depth ) {
					$raw = substr( $this->buf, $start, $this->pos - $start );
					if ( strlen( $raw ) > self::MAX_VALUE ) throw new Exception( 'El manifiesto del respaldo no es válido: un valor demasiado grande.' );
					return $raw;
				}
			}
			if ( $this->eof ) throw self::invalid();
			if ( $this->pos - $start > self::MAX_VALUE ) throw new Exception( 'El manifiesto del respaldo no es válido: un valor demasiado grande.' );
			$offset = $this->pos - $start;
			$this->fill( $start );
			$start = 0;
			$this->pos = $offset;
		}
	}
	/** Espacios de JSON: espacio, tabulador, salto de línea y retorno. */
	private function ws() {
		while ( true ) {
			$this->pos += strspn( $this->buf, " \t\n\r", $this->pos );
			if ( $this->pos < strlen( $this->buf ) || $this->eof ) return;
			$this->fill( $this->pos );
		}
	}
	private function peek() {
		if ( $this->pos >= strlen( $this->buf ) && ! $this->eof ) $this->fill( $this->pos );
		return $this->pos < strlen( $this->buf ) ? $this->buf[ $this->pos ] : '';
	}
	private function next() {
		$char = $this->peek();
		if ( '' !== $char ) $this->pos++;
		return $char;
	}
	/** Descarta lo anterior a $keep y añade el siguiente fragmento del flujo. */
	private function fill( $keep ) {
		$this->buf = (string) substr( $this->buf, $keep );
		$this->pos = max( 0, $this->pos - $keep );
		if ( $this->eof ) return;
		$chunk = fread( $this->stream, self::CHUNK );
		if ( false === $chunk ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		if ( '' === $chunk ) {
			if ( feof( $this->stream ) ) $this->eof = true;
			else throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
			return;
		}
		$this->read += strlen( $chunk );
		if ( $this->on_bytes ) call_user_func( $this->on_bytes, $chunk );
		$this->buf .= $chunk;
		if ( feof( $this->stream ) ) $this->eof = true;
	}
}
