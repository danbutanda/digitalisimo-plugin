<?php
defined( 'ABSPATH' ) || exit;

/**
 * Firma (HMAC-SHA512/256) de los bytes exactos de manifest.json. El manifiesto fija tamaño y SHA-256 de todas las
 * entradas y la verificación rechaza entradas sobrantes o ausentes, así que la firma cubre todo el respaldo.
 *
 * El bloque «auth» va dentro del manifiesto (formato digitalisimo-backup/v3, sin cambios) y el MAC en el comentario
 * del ZIP: la versión 1.0.58 ignora los dos y sigue restaurando un respaldo firmado sin cifrar.
 */
final class Digitalisimo_Backups_Manifest_Auth {
	const PREFIX = 'DIGITALISIMO-AUTH/1';
	const ALG = 'hmac-sha512256';
	const MAX_MANIFEST = 268435456;

	public static function block( $descriptor, $encrypted ) { return array( 'version' => 1, 'alg' => self::ALG, 'key' => $descriptor, 'encrypted' => (bool) $encrypted ); }
	private static function message( $key_id, $manifest ) { return self::PREFIX . "\n" . $key_id . "\n" . $manifest; }
	public static function comment( $root, $manifest ) {
		$key_id = Digitalisimo_Backups_Keys::key_id( $root );
		return self::PREFIX . ' ' . $key_id . ' ' . Digitalisimo_Backups_Keys::b64url_encode( sodium_crypto_auth( self::message( $key_id, $manifest ), Digitalisimo_Backups_Keys::mac_key( $root ) ) );
	}
	/** Bytes de manifest.json con tope de tamaño, o null si el ZIP no lo trae. */
	public static function read_manifest( $zip ) {
		$stat = $zip->statName( 'manifest.json' );
		if ( ! $stat ) return null;
		if ( (int) $stat['size'] > self::MAX_MANIFEST ) throw new Exception( 'El manifiesto del respaldo supera el tamaño permitido.' );
		$bytes = $zip->getFromName( 'manifest.json' );
		if ( ! is_string( $bytes ) || strlen( $bytes ) !== (int) $stat['size'] ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		return $bytes;
	}

	/**
	 * Estado de la firma: signed, needs_key, wrong_key, invalid o unsigned. $known_root fuerza la clave (verificación
	 * tras crear o tras descifrar); si no, se busca la clave por su huella y, si no se conoce, se prueba $secret.
	 */
	public static function verify( $zip, $secret = '', $known_root = null ) {
		// Por partes y con memoria constante (H1): el bloque `auth` sale de la lectura del primer nivel y el MAC se calcula
		// sobre los bytes exactos leídos por fragmentos (HMAC-SHA-512 truncado a 256 bits, el crypto_auth de libsodium).
		$stat = $zip->statName( 'manifest.json' );
		if ( ! $stat ) return array( 'state' => 'unsigned' );
		if ( (int) $stat['size'] > self::MAX_MANIFEST ) throw new Exception( 'El manifiesto del respaldo supera el tamaño permitido.' );
		try {
			$data = Digitalisimo_Backups_Manifest_Stream::read( $zip )['data'];
		} catch ( Exception $e ) {
			if ( false !== strpos( $e->getMessage(), 'No se pudo leer' ) ) throw $e;
			return array( 'state' => 'unsigned' ); // La verificación del contenido lo rechaza con su mensaje.
		}
		if ( ! array_key_exists( 'auth', $data ) ) return array( 'state' => 'unsigned' );
		$auth = $data['auth'];
		if ( ! is_array( $auth ) || 1 !== ( $auth['version'] ?? null ) || self::ALG !== ( $auth['alg'] ?? '' ) || ! is_bool( $auth['encrypted'] ?? null ) || ! Digitalisimo_Backups_Keys::valid_descriptor( $auth['key'] ?? null ) ) return array( 'state' => 'invalid', 'reason' => 'El bloque de firma del manifiesto no es válido.' );
		$descriptor = $auth['key'];
		$comment = $zip->getArchiveComment();
		if ( ! is_string( $comment ) || ! preg_match( '/^DIGITALISIMO-AUTH\/1 ([0-9a-f]{32}) ([A-Za-z0-9_-]{43})$/D', $comment, $match ) || $match[1] !== $descriptor['id'] ) return array( 'state' => 'invalid', 'key' => $descriptor, 'reason' => 'El manifiesto declara una firma que el archivo no trae o que no corresponde.' );
		$mac = Digitalisimo_Backups_Keys::b64url_decode( $match[2], 32 );
		if ( false === $mac ) return array( 'state' => 'invalid', 'key' => $descriptor, 'reason' => 'La firma del archivo está mal formada.' );
		if ( ! Digitalisimo_Backups_Sodium::available() ) return array( 'state' => 'needs_key', 'key' => $descriptor, 'reason' => 'Este servidor no puede comprobar firmas: faltan funciones de libsodium.' );
		$via = 'known';
		$root = $known_root;
		if ( null === $root ) {
			$via = 'keyring';
			$root = Digitalisimo_Backups_Keys::resolve_by_id( $descriptor['id'] );
			if ( false === $root && '' !== (string) $secret ) {
				$via = 'secret';
				$root = Digitalisimo_Backups_Keys::resolve_with_secret( $descriptor, (string) $secret );
				if ( false === $root ) return array( 'state' => 'wrong_key', 'key' => $descriptor );
			}
			if ( false === $root ) return array( 'state' => 'needs_key', 'key' => $descriptor );
		}
		if ( ! hash_equals( $descriptor['id'], Digitalisimo_Backups_Keys::key_id( $root ) ) ) return array( 'state' => 'wrong_key', 'key' => $descriptor );
		try { $valid = hash_equals( self::stream_mac( $zip, $descriptor['id'], Digitalisimo_Backups_Keys::mac_key( $root ) ), $mac ); }
		catch ( Throwable $e ) { $valid = false; }
		if ( ! $valid ) return array( 'state' => 'invalid', 'key' => $descriptor, 'reason' => 'La firma no coincide: el respaldo fue modificado o está dañado.' );
		return array( 'state' => 'signed', 'key' => $descriptor, 'encrypted' => $auth['encrypted'], 'root' => $root, 'via' => $via, 'manifest' => $data );
	}
	/** MAC de los bytes exactos de manifest.json leídos por fragmentos: el mismo valor que comment() (crypto_auth). */
	public static function stream_mac( $zip, $key_id, $mac_key ) {
		$stat = $zip->statName( 'manifest.json' );
		$stream = $stat ? $zip->getStream( 'manifest.json' ) : false;
		if ( ! $stream ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		$hmac = hash_init( 'sha512', HASH_HMAC, $mac_key );
		hash_update( $hmac, self::PREFIX . "\n" . $key_id . "\n" );
		$bytes = 0;
		try {
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
				$bytes += strlen( $chunk );
				hash_update( $hmac, $chunk );
			}
		} finally {
			fclose( $stream );
		}
		if ( $bytes !== (int) $stat['size'] ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		return substr( hash_final( $hmac, true ), 0, 32 );
	}
}
