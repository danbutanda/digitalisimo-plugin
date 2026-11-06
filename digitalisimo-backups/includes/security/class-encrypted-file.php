<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contenedor DGBK v1: el ZIP v3 ya verificado, cifrado entero como un flujo crypto_secretstream_xchacha20poly1305.
 *
 *   "DGBK" | 0x01 | 0x00 0x00 0x00 | u32be len(H) | H (JSON UTF-8, ≤ 16 KiB) | cabecera secretstream (24 B) | C_1 … C_n
 *
 * Cada C_i cifra chunk_size bytes del ZIP (el último, de 1 a chunk_size) más 17 de autenticación. El dato adicional
 * de C_1 es todo el prefijo, desde "DGBK" hasta el final de H: cambiar la cabecera rompe la autenticación. Las
 * etiquetas son MESSAGE y, en el último bloque, FINAL; tras FINAL debe llegar el fin del archivo. La clave de cada
 * archivo se deriva de la clave raíz y de un file_salt aleatorio de 32 bytes. Especificación completa en BACKUPS.md.
 */
final class Digitalisimo_Backups_Encrypted_File {
	const MAGIC = 'DGBK';
	const VERSION = 1;
	const FORMAT = 'digitalisimo-encrypted-backup/v1';
	const CIPHER = 'secretstream-xchacha20poly1305';
	const MAX_HEADER = 16384;
	const CHUNK = 1048576;
	const MIN_CHUNK = 4096;
	const MAX_CHUNK = 8388608;

	/** Decide por los bytes mágicos, nunca por la extensión. */
	public static function is_encrypted( $path ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) return false;
		$magic = fread( $handle, 4 );
		fclose( $handle );
		return self::MAGIC === $magic;
	}
	private static function valid_chunk( $chunk ) { return is_int( $chunk ) && $chunk >= self::MIN_CHUNK && $chunk <= self::MAX_CHUNK && 0 === ( $chunk & ( $chunk - 1 ) ); }
	private static function read_exact( $handle, $length, $message ) {
		$data = '';
		while ( strlen( $data ) < $length ) {
			$part = fread( $handle, $length - strlen( $data ) );
			if ( false === $part || '' === $part ) throw new Exception( $message );
			$data .= $part;
		}
		return $data;
	}
	private static function write_all( $handle, $data ) {
		$length = strlen( $data );
		for ( $offset = 0; $offset < $length; $offset += $written ) {
			$written = fwrite( $handle, 0 === $offset ? $data : substr( $data, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir el respaldo cifrado completo: revisa el espacio libre del almacén.' );
		}
	}
	public static function expected_size( $prefix_length, $inner_size, $chunk ) { return $prefix_length + Digitalisimo_Backups_Sodium::HEADERBYTES + $inner_size + intdiv( $inner_size + $chunk - 1, $chunk ) * Digitalisimo_Backups_Sodium::ABYTES; }

	/** Lee y valida la cabecera sin clave. Rechaza parámetros fuera de rango antes de reservar memoria o derivar. */
	public static function read_header( $path ) {
		// La caché de stat() de PHP puede guardar el tamaño de una versión anterior del mismo archivo.
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) ) throw new Exception( 'El respaldo cifrado no es un archivo normal.' );
		$size = @filesize( $path );
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle || false === $size ) throw new Exception( 'No se pudo abrir el respaldo cifrado.' );
		try {
			$damaged = 'El respaldo cifrado está incompleto o dañado.';
			$fixed = self::read_exact( $handle, 12, $damaged );
			if ( self::MAGIC !== substr( $fixed, 0, 4 ) || self::VERSION !== ord( $fixed[4] ) || "\0\0\0" !== substr( $fixed, 5, 3 ) ) throw new Exception( 'El archivo no es un respaldo cifrado de DIGITALÍSIMO compatible (DGBK v1).' );
			$length = unpack( 'N', substr( $fixed, 8, 4 ) )[1];
			if ( $length < 2 || $length > self::MAX_HEADER ) throw new Exception( 'La cabecera del respaldo cifrado no es válida.' );
			$json = self::read_exact( $handle, $length, $damaged );
			$header = json_decode( $json, true, 16 );
			if ( ! is_array( $header ) || ! self::valid_header( $header ) ) throw new Exception( 'La cabecera del respaldo cifrado no es válida.' );
			$stream_header = self::read_exact( $handle, Digitalisimo_Backups_Sodium::HEADERBYTES, $damaged );
		} finally {
			fclose( $handle );
		}
		$prefix = $fixed . $json;
		$expected = self::expected_size( strlen( $prefix ), $header['inner_size'], $header['chunk_size'] );
		if ( $size < $expected ) throw new Exception( 'El respaldo cifrado está incompleto: faltan ' . ( $expected - $size ) . ' bytes.' );
		if ( $size > $expected ) throw new Exception( 'El respaldo cifrado tiene ' . ( $size - $expected ) . ' bytes de más: fue modificado o está dañado.' );
		return array( 'header' => $header, 'prefix' => $prefix, 'stream_header' => $stream_header, 'offset' => strlen( $prefix ) + Digitalisimo_Backups_Sodium::HEADERBYTES, 'chunks' => intdiv( $header['inner_size'] + $header['chunk_size'] - 1, $header['chunk_size'] ), 'size' => $size );
	}
	private static function valid_header( $h ) {
		return self::FORMAT === ( $h['format'] ?? '' )
			&& is_string( $h['backup_id'] ?? null ) && preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $h['backup_id'] )
			&& is_string( $h['created_at'] ?? null ) && strlen( $h['created_at'] ) <= 64
			&& is_string( $h['plugin_version'] ?? null ) && strlen( $h['plugin_version'] ) <= 32
			&& 'installation' === ( $h['scope'] ?? '' ) && is_bool( $h['multisite'] ?? null )
			&& is_int( $h['inner_size'] ?? null ) && $h['inner_size'] >= 1 && $h['inner_size'] <= PHP_INT_MAX / 2
			&& is_string( $h['inner_sha256'] ?? null ) && preg_match( '/^[0-9a-f]{64}$/D', $h['inner_sha256'] )
			&& self::CIPHER === ( $h['cipher'] ?? '' ) && self::valid_chunk( $h['chunk_size'] ?? null )
			&& false !== Digitalisimo_Backups_Keys::b64url_decode( $h['file_salt'] ?? null, 32 )
			&& Digitalisimo_Backups_Keys::valid_descriptor( $h['key'] ?? null );
	}

	/**
	 * Cifra $source en $target (que no debe existir) con memoria constante. Comprueba que el ZIP no cambió durante el
	 * cifrado. Devuelve la cabecera.
	 */
	public static function encrypt( $source, $target, $root, $descriptor, $meta, $chunk = self::CHUNK, $progress = null ) {
		if ( ! self::valid_chunk( $chunk ) ) throw new Exception( 'Tamaño de bloque de cifrado no válido.' );
		if ( ! Digitalisimo_Backups_Keys::valid_descriptor( $descriptor ) || ! hash_equals( $descriptor['id'], Digitalisimo_Backups_Keys::key_id( $root ) ) ) throw new Exception( 'La clave de cifrado no corresponde a su descriptor.' );
		clearstatcache( true, $source );
		$size = @filesize( $source );
		$sha = @hash_file( 'sha256', $source );
		if ( ! $size || false === $sha ) throw new Exception( 'No se pudo leer el ZIP verificado para cifrarlo.' );
		$salt = random_bytes( 32 );
		$header = array( 'format' => self::FORMAT, 'backup_id' => (string) $meta['backup_id'], 'created_at' => (string) $meta['created_at'], 'plugin_version' => (string) $meta['plugin_version'], 'scope' => 'installation', 'multisite' => (bool) $meta['multisite'], 'inner_size' => $size, 'inner_sha256' => $sha, 'cipher' => self::CIPHER, 'chunk_size' => $chunk, 'file_salt' => Digitalisimo_Backups_Keys::b64url_encode( $salt ), 'key' => $descriptor );
		if ( ! self::valid_header( $header ) ) throw new Exception( 'La cabecera del respaldo cifrado no es válida.' );
		$json = json_encode( $header, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_HEADER ) throw new Exception( 'La cabecera del respaldo cifrado no es válida.' );
		$prefix = self::MAGIC . chr( self::VERSION ) . "\0\0\0" . pack( 'N', strlen( $json ) ) . $json;
		$key = Digitalisimo_Backups_Keys::file_key( $root, $salt );
		$input = @fopen( $source, 'rb' );
		$output = @fopen( $target, 'xb' );
		if ( ! $input || ! $output ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); throw new Exception( 'No se pudo preparar el respaldo cifrado.' ); }
		try {
			list( $state, $stream_header ) = sodium_crypto_secretstream_xchacha20poly1305_init_push( $key );
			self::write_all( $output, $prefix . $stream_header );
			$hash = hash_init( 'sha256' );
			$remaining = $size;
			$index = 0;
			while ( $remaining > 0 ) {
				$plain = self::read_exact( $input, min( $chunk, $remaining ), 'El ZIP cambió durante el cifrado.' );
				$remaining -= strlen( $plain );
				hash_update( $hash, $plain );
				self::write_all( $output, sodium_crypto_secretstream_xchacha20poly1305_push( $state, $plain, 0 === $index ? $prefix : '', 0 === $remaining ? Digitalisimo_Backups_Sodium::TAG_FINAL : Digitalisimo_Backups_Sodium::TAG_MESSAGE ) );
				$index++;
				if ( $progress ) call_user_func( $progress, $size - $remaining, $size );
			}
			if ( '' !== fread( $input, 1 ) || ! hash_equals( $sha, hash_final( $hash ) ) ) throw new Exception( 'El ZIP cambió durante el cifrado.' );
			if ( ! fflush( $output ) ) throw new Exception( 'No se pudo completar el respaldo cifrado.' );
		} catch ( Throwable $e ) {
			fclose( $input );
			fclose( $output );
			@unlink( $target );
			throw $e instanceof Exception ? $e : new Exception( 'No se pudo cifrar el respaldo: ' . $e->getMessage(), 0, $e );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
			if ( isset( $state ) ) Digitalisimo_Backups_Sodium::memzero( $state );
		}
		fclose( $input );
		if ( ! fclose( $output ) ) { @unlink( $target ); throw new Exception( 'No se pudo completar el respaldo cifrado.' ); }
		clearstatcache( true, $target );
		if ( @filesize( $target ) !== self::expected_size( strlen( $prefix ), $size, $chunk ) ) { @unlink( $target ); throw new Exception( 'El respaldo cifrado no tiene el tamaño esperado.' ); }
		return $header;
	}

	/**
	 * Descifra verificando cada bloque, el orden, la etiqueta FINAL, el fin del archivo y el SHA-256 interior. Sin
	 * $target sólo verifica. Si algo falla, borra lo escrito.
	 */
	public static function decrypt( $path, $root, $target = null, $parsed = null, $progress = null ) {
		$parsed = $parsed ?: self::read_header( $path );
		$header = $parsed['header'];
		if ( ! hash_equals( $header['key']['id'], Digitalisimo_Backups_Keys::key_id( $root ) ) ) throw new Exception( 'Este respaldo está cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $header['key']['id'] ) . ', no con la escrita.' );
		$damaged = 'El respaldo cifrado fue modificado o está dañado: no se puede descifrar.';
		$key = Digitalisimo_Backups_Keys::file_key( $root, Digitalisimo_Backups_Keys::b64url_decode( $header['file_salt'], 32 ) );
		$input = @fopen( $path, 'rb' );
		$output = null === $target ? null : @fopen( $target, 'xb' );
		if ( ! $input || ( null !== $target && ! $output ) ) { if ( $input ) fclose( $input ); if ( $output ) fclose( $output ); throw new Exception( 'No se pudo preparar el descifrado del respaldo.' ); }
		try {
			try { $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull( $parsed['stream_header'], $key ); }
			catch ( Throwable $e ) { throw new Exception( $damaged ); }
			if ( 0 !== fseek( $input, $parsed['offset'] ) ) throw new Exception( $damaged );
			$hash = hash_init( 'sha256' );
			$remaining = $header['inner_size'];
			for ( $index = 0; $index < $parsed['chunks']; $index++ ) {
				$length = min( $header['chunk_size'], $remaining );
				$cipher = self::read_exact( $input, $length + Digitalisimo_Backups_Sodium::ABYTES, 'El respaldo cifrado está incompleto.' );
				try { $result = sodium_crypto_secretstream_xchacha20poly1305_pull( $state, $cipher, 0 === $index ? $parsed['prefix'] : '' ); }
				catch ( Throwable $e ) { $result = false; }
				$final = $index === $parsed['chunks'] - 1;
				if ( ! is_array( $result ) || strlen( $result[0] ) !== $length || ( $final ? Digitalisimo_Backups_Sodium::TAG_FINAL : Digitalisimo_Backups_Sodium::TAG_MESSAGE ) !== (int) $result[1] ) throw new Exception( $damaged );
				hash_update( $hash, $result[0] );
				if ( $output ) self::write_all( $output, $result[0] );
				$remaining -= $length;
				if ( $progress ) call_user_func( $progress, $header['inner_size'] - $remaining, $header['inner_size'] );
			}
			if ( '' !== fread( $input, 1 ) ) throw new Exception( $damaged );
			if ( ! hash_equals( $header['inner_sha256'], hash_final( $hash ) ) ) throw new Exception( $damaged );
			if ( $output && ! fflush( $output ) ) throw new Exception( 'No se pudo escribir el respaldo descifrado.' );
		} catch ( Throwable $e ) {
			fclose( $input );
			if ( $output ) { fclose( $output ); @unlink( $target ); }
			throw $e instanceof Exception ? $e : new Exception( $damaged, 0, $e );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
			if ( isset( $state ) ) Digitalisimo_Backups_Sodium::memzero( $state );
		}
		fclose( $input );
		if ( $output && ! fclose( $output ) ) { @unlink( $target ); throw new Exception( 'No se pudo escribir el respaldo descifrado.' ); }
		return $header;
	}
}
