<?php
defined( 'ABSPATH' ) || exit;

/**
 * Claves de los respaldos. Hay una clave raíz por instalación (32 bytes) que sale de la constante
 * DIGITALISIMO_BACKUPS_KEY o de una frase del administrador (PBKDF2-SHA256); de ella se derivan, con BLAKE2b y
 * separación de dominio, la huella pública, la clave del MAC del manifiesto y la de cada archivo cifrado.
 *
 * Ni la frase ni la clave vuelven nunca al navegador: la frase no se guarda y la clave raíz sólo se guarda envuelta
 * (secretbox) con una clave que sale de AUTH_KEY y SECURE_AUTH_KEY de wp-config.php. Las opciones con el prefijo
 * digitalisimo_backups_vault_ no entran en ningún respaldo: ningún respaldo contiene la clave que lo protege.
 */
final class Digitalisimo_Backups_Keys {
	const KEYRING = 'digitalisimo_backups_vault_keyring';
	const SECRET_PREFIX = 'digitalisimo_backups_vault_';
	const CONSTANT = 'DIGITALISIMO_BACKUPS_KEY';
	const KDF_ALG = 'pbkdf2-sha256';
	const KDF_ITERATIONS = 600000;
	const KDF_MIN_ITERATIONS = 100000;
	const KDF_MAX_ITERATIONS = 5000000;
	const PASSPHRASE_MIN = 16;
	const PASSPHRASE_MAX = 1024;
	const MAX_ATTEMPTS = 8;

	/** En Multisite el llavero y la configuración son de la red, como la carpeta del almacén. */
	public static function get( $name, $default = false ) { return is_multisite() ? get_site_option( $name, $default ) : get_option( $name, $default ); }
	public static function put( $name, $value ) { return is_multisite() ? update_site_option( $name, $value ) : update_option( $name, $value, false ); }

	public static function b64url_encode( $binary ) { return rtrim( strtr( base64_encode( $binary ), '+/', '-_' ), '=' ); }
	public static function b64url_decode( $text, $bytes = null ) {
		if ( ! is_string( $text ) || ! preg_match( '/^[A-Za-z0-9_-]*$/D', $text ) ) return false;
		$binary = base64_decode( strtr( $text, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $text ) % 4 ) % 4 ), true );
		if ( false === $binary || ( null !== $bytes && strlen( $binary ) !== $bytes ) ) return false;
		return $binary;
	}
	private static function h( $message, $key, $length ) { return sodium_crypto_generichash( $message, $key, $length ); }
	public static function key_id( $root ) { return bin2hex( self::h( 'DGBK1/key-id', $root, 16 ) ); }
	public static function mac_key( $root ) { return self::h( 'DGBK1/manifest-mac', $root, 32 ); }
	public static function file_key( $root, $salt ) { return self::h( 'DGBK1/file|' . $salt, $root, 32 ); }
	public static function short( $key_id ) { return substr( (string) $key_id, 0, 8 ); }

	/** Base64 (estándar o URL) de exactamente 32 bytes; false si no lo es. Nunca se repite el valor en un mensaje. */
	public static function parse_raw_key( $value ) {
		if ( ! is_string( $value ) ) return false;
		$value = trim( $value );
		if ( ! preg_match( '/^[A-Za-z0-9+\/_-]{43}=?$/D', $value ) ) return false;
		$binary = base64_decode( strtr( rtrim( $value, '=' ), '-_', '+/' ) . '=', true );
		return false !== $binary && 32 === strlen( $binary ) ? $binary : false;
	}
	/** 'none', 'valid' o 'invalid'. */
	public static function constant_state() {
		if ( ! defined( self::CONSTANT ) ) return 'none';
		return false === self::parse_raw_key( constant( self::CONSTANT ) ) ? 'invalid' : 'valid';
	}
	private static function constant_key() { return 'valid' === self::constant_state() ? self::parse_raw_key( constant( self::CONSTANT ) ) : false; }

	public static function valid_kdf( $kdf ) {
		return is_array( $kdf ) && self::KDF_ALG === ( $kdf['alg'] ?? '' ) && is_int( $kdf['iterations'] ?? null ) && $kdf['iterations'] >= self::KDF_MIN_ITERATIONS && $kdf['iterations'] <= self::KDF_MAX_ITERATIONS && false !== self::b64url_decode( $kdf['salt'] ?? null, 16 );
	}
	/** Descriptor público de una clave: viaja en el manifiesto y en la cabecera cifrada. */
	public static function descriptor( $key_id, $source, $kdf = null ) { return array( 'id' => $key_id, 'source' => $source, 'kdf' => 'passphrase' === $source ? $kdf : null ); }
	public static function valid_descriptor( $descriptor ) {
		if ( ! is_array( $descriptor ) || ! is_string( $descriptor['id'] ?? null ) || ! preg_match( '/^[0-9a-f]{32}$/D', $descriptor['id'] ) ) return false;
		if ( 'wp-config' === ( $descriptor['source'] ?? '' ) ) return null === ( $descriptor['kdf'] ?? null );
		return 'passphrase' === ( $descriptor['source'] ?? '' ) && self::valid_kdf( $descriptor['kdf'] ?? null );
	}
	public static function valid_passphrase( $passphrase ) { return is_string( $passphrase ) && strlen( $passphrase ) >= self::PASSPHRASE_MIN && strlen( $passphrase ) <= self::PASSPHRASE_MAX; }
	/** Valida los parámetros antes de derivar: un archivo hostil no puede pedir un cálculo ilimitado. */
	public static function derive( $passphrase, $kdf ) {
		if ( ! self::valid_kdf( $kdf ) || ! self::valid_passphrase( $passphrase ) ) return false;
		return hash_pbkdf2( 'sha256', $passphrase, self::b64url_decode( $kdf['salt'], 16 ), $kdf['iterations'], 32, true );
	}
	public static function new_kdf() { return array( 'alg' => self::KDF_ALG, 'iterations' => self::KDF_ITERATIONS, 'salt' => self::b64url_encode( random_bytes( 16 ) ) ); }

	/** Clave que envuelve el llavero; false si wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias. */
	public static function wrap_key() {
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY' ) as $name ) {
			if ( ! defined( $name ) ) return false;
			$value = (string) constant( $name );
			if ( strlen( $value ) < 16 || 'put your unique phrase here' === $value ) return false;
		}
		return self::h( 'DGBK1/keyring-wrap', self::h( AUTH_KEY . "\0" . SECURE_AUTH_KEY, '', 32 ), 32 );
	}
	public static function wrap_id( $wrap_key ) { return substr( bin2hex( self::h( 'DGBK1/kw-id', $wrap_key, 16 ) ), 0, 16 ); }
	private static function wrap( $root ) {
		$wrap_key = self::wrap_key();
		if ( false === $wrap_key ) throw new Exception( 'Para guardar una frase, wp-config.php debe definir AUTH_KEY y SECURE_AUTH_KEY propias. También puedes definir DIGITALISIMO_BACKUPS_KEY.' );
		$nonce = random_bytes( 24 );
		return array( 'kw_id' => self::wrap_id( $wrap_key ), 'box' => self::b64url_encode( $nonce . sodium_crypto_secretbox( $root, $nonce, $wrap_key ) ) );
	}
	/** La clave raíz de una entrada del llavero, o false si las claves de wp-config.php cambiaron. */
	public static function unwrap( $key_id, $entry ) {
		$wrap_key = self::wrap_key();
		if ( false === $wrap_key || ! is_array( $entry['wrap'] ?? null ) || self::wrap_id( $wrap_key ) !== ( $entry['wrap']['kw_id'] ?? '' ) ) return false;
		$raw = self::b64url_decode( $entry['wrap']['box'] ?? null );
		if ( false === $raw || strlen( $raw ) !== 24 + 16 + 32 ) return false;
		try { $root = sodium_crypto_secretbox_open( substr( $raw, 24 ), substr( $raw, 0, 24 ), $wrap_key ); }
		catch ( Throwable $e ) { return false; }
		return is_string( $root ) && 32 === strlen( $root ) && hash_equals( (string) $key_id, self::key_id( $root ) ) ? $root : false;
	}

	public static function keyring() {
		$saved = self::get( self::KEYRING, array() );
		$ring = array( 'version' => 1, 'active' => '', 'keys' => array() );
		if ( ! is_array( $saved ) ) return $ring;
		foreach ( (array) ( $saved['keys'] ?? array() ) as $key_id => $entry ) {
			if ( is_string( $key_id ) && preg_match( '/^[0-9a-f]{32}$/D', $key_id ) && is_array( $entry ) && 'passphrase' === ( $entry['source'] ?? '' ) && self::valid_kdf( $entry['kdf'] ?? null ) ) $ring['keys'][ $key_id ] = $entry;
		}
		$active = (string) ( $saved['active'] ?? '' );
		if ( isset( $ring['keys'][ $active ] ) ) $ring['active'] = $active;
		return $ring;
	}
	private static function save_keyring( $ring ) { self::put( self::KEYRING, $ring ); }
	/** 'active', 'retired' o 'needs_passphrase' (las claves de wp-config.php cambiaron). */
	public static function entry_state( $key_id, $entry, $ring ) {
		$wrap_key = self::wrap_key();
		if ( false === $wrap_key || self::wrap_id( $wrap_key ) !== ( $entry['wrap']['kw_id'] ?? '' ) ) return 'needs_passphrase';
		return $key_id === $ring['active'] ? 'active' : 'retired';
	}

	/**
	 * Guarda una frase. Si corresponde a una clave del llavero (mismo salt y misma huella), la vuelve a envolver y la
	 * activa; si no, crea una clave nueva y retira la activa sin borrarla.
	 */
	public static function set_passphrase( $passphrase ) {
		if ( ! self::valid_passphrase( $passphrase ) ) throw new Exception( 'La frase debe tener entre ' . self::PASSPHRASE_MIN . ' y ' . self::PASSPHRASE_MAX . ' bytes.' );
		if ( false === self::wrap_key() ) throw new Exception( 'Para guardar una frase, wp-config.php debe definir AUTH_KEY y SECURE_AUTH_KEY propias. También puedes definir DIGITALISIMO_BACKUPS_KEY.' );
		$ring = self::keyring();
		$candidates = array();
		if ( $ring['active'] ) $candidates[] = $ring['active'];
		foreach ( $ring['keys'] as $key_id => $entry ) if ( 'needs_passphrase' === self::entry_state( $key_id, $entry, $ring ) ) $candidates[] = $key_id;
		foreach ( $ring['keys'] as $key_id => $entry ) $candidates[] = $key_id;
		foreach ( array_slice( array_values( array_unique( $candidates ) ), 0, self::MAX_ATTEMPTS ) as $key_id ) {
			$root = self::derive( $passphrase, $ring['keys'][ $key_id ]['kdf'] );
			if ( false === $root || ! hash_equals( $key_id, self::key_id( $root ) ) ) continue;
			$was = self::entry_state( $key_id, $ring['keys'][ $key_id ], $ring );
			if ( 'needs_passphrase' === $was ) $ring['keys'][ $key_id ]['wrap'] = self::wrap( $root );
			self::activate( $ring, $key_id );
			self::save_keyring( $ring );
			Digitalisimo_Backups_Sodium::memzero( $root );
			return array( 'result' => 'active' === $was ? 'unchanged' : ( 'needs_passphrase' === $was ? 'rewrapped' : 'reactivated' ), 'key_id' => $key_id );
		}
		$kdf = self::new_kdf();
		$root = self::derive( $passphrase, $kdf );
		$key_id = self::key_id( $root );
		$ring['keys'][ $key_id ] = array( 'source' => 'passphrase', 'kdf' => $kdf, 'wrap' => self::wrap( $root ), 'created' => time(), 'retired' => 0 );
		self::activate( $ring, $key_id );
		self::save_keyring( $ring );
		Digitalisimo_Backups_Sodium::memzero( $root );
		return array( 'result' => 'created', 'key_id' => $key_id );
	}
	/** Tras restaurar en otro servidor: la frase escrita pasa al llavero y firma y cifra lo nuevo. */
	public static function adopt( $root, $descriptor ) {
		if ( ! self::valid_descriptor( $descriptor ) || 'passphrase' !== $descriptor['source'] || ! hash_equals( $descriptor['id'], self::key_id( $root ) ) ) throw new Exception( 'Sólo una frase puede guardarse en el llavero; una clave de wp-config.php se define en ese archivo.' );
		$ring = self::keyring();
		if ( ! isset( $ring['keys'][ $descriptor['id'] ] ) || 'needs_passphrase' === self::entry_state( $descriptor['id'], $ring['keys'][ $descriptor['id'] ], $ring ) ) {
			$ring['keys'][ $descriptor['id'] ] = array( 'source' => 'passphrase', 'kdf' => $descriptor['kdf'], 'wrap' => self::wrap( $root ), 'created' => (int) ( $ring['keys'][ $descriptor['id'] ]['created'] ?? time() ), 'retired' => 0 );
		}
		self::activate( $ring, $descriptor['id'] );
		self::save_keyring( $ring );
		return $descriptor['id'];
	}
	private static function activate( &$ring, $key_id ) {
		if ( $ring['active'] && $ring['active'] !== $key_id && isset( $ring['keys'][ $ring['active'] ] ) ) $ring['keys'][ $ring['active'] ]['retired'] = time();
		$ring['keys'][ $key_id ]['retired'] = 0;
		$ring['active'] = $key_id;
	}
	/** Quita una clave retirada. La activa no se quita: primero se cambia la frase. */
	public static function remove( $key_id ) {
		$ring = self::keyring();
		if ( ! isset( $ring['keys'][ $key_id ] ) ) throw new Exception( 'Esa clave ya no está en el llavero.' );
		if ( $key_id === $ring['active'] ) throw new Exception( 'La clave activa no se puede quitar. Cambia antes la frase.' );
		unset( $ring['keys'][ $key_id ] );
		self::save_keyring( $ring );
	}

	/**
	 * La clave con la que se firma y cifra lo nuevo: array( root, descriptor ), null si no hay ninguna, o una
	 * excepción si hay una configurada que no se puede usar (constante inválida o frase que hay que volver a escribir).
	 */
	public static function active() {
		$state = self::constant_state();
		if ( 'invalid' === $state ) throw new Exception( 'La constante DIGITALISIMO_BACKUPS_KEY de wp-config.php no es válida (debe ser base64 de 32 bytes). Corrígela: mientras esté definida no se crean respaldos sin cifrar.' );
		if ( ! Digitalisimo_Backups_Sodium::available() ) {
			if ( 'valid' === $state ) throw new Exception( 'Este servidor no puede cifrar: faltan funciones de libsodium (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . ').' );
			return null;
		}
		if ( 'valid' === $state ) {
			$root = self::constant_key();
			return array( $root, self::descriptor( self::key_id( $root ), 'wp-config' ) );
		}
		$ring = self::keyring();
		if ( ! $ring['active'] ) return null;
		$root = self::unwrap( $ring['active'], $ring['keys'][ $ring['active'] ] );
		if ( false === $root ) throw new Exception( 'La frase de los respaldos (clave ' . self::short( $ring['active'] ) . ') no se puede abrir: las claves de wp-config.php cambiaron o se interrumpió una restauración. Vuelve a escribirla en Backups → Seguridad.' );
		return array( $root, self::descriptor( $ring['active'], 'passphrase', $ring['keys'][ $ring['active'] ]['kdf'] ) );
	}
	/** Descriptor público de una clave que este sitio conoce por su huella (constante o llavero), o null. Nunca la clave. */
	public static function descriptor_by_id( $key_id ) {
		if ( ! is_string( $key_id ) || ! preg_match( '/^[0-9a-f]{32}$/D', $key_id ) ) return null;
		$constant = self::constant_key();
		if ( false !== $constant ) {
			$match = hash_equals( $key_id, self::key_id( $constant ) );
			Digitalisimo_Backups_Sodium::memzero( $constant );
			if ( $match ) return self::descriptor( $key_id, 'wp-config' );
		}
		$ring = self::keyring();
		return isset( $ring['keys'][ $key_id ] ) ? self::descriptor( $key_id, 'passphrase', $ring['keys'][ $key_id ]['kdf'] ) : null;
	}
	/** Una clave que este sitio conoce por su huella (constante o llavero), o false. */
	public static function resolve_by_id( $key_id ) {
		if ( ! is_string( $key_id ) || ! preg_match( '/^[0-9a-f]{32}$/D', $key_id ) || ! Digitalisimo_Backups_Sodium::available() ) return false;
		$constant = self::constant_key();
		if ( false !== $constant && hash_equals( $key_id, self::key_id( $constant ) ) ) return $constant;
		$ring = self::keyring();
		return isset( $ring['keys'][ $key_id ] ) ? self::unwrap( $key_id, $ring['keys'][ $key_id ] ) : false;
	}
	/**
	 * Si este sitio abre la clave sin pedir la frase: la constante, o una entrada del llavero envuelta con las claves
	 * actuales de wp-config.php. No desenvuelve nada; sólo sirve para avisar en la interfaz.
	 */
	public static function can_open( $key_id ) {
		if ( ! is_string( $key_id ) || ! preg_match( '/^[0-9a-f]{32}$/D', $key_id ) || ! Digitalisimo_Backups_Sodium::available() ) return false;
		$constant = self::constant_key();
		if ( false !== $constant && hash_equals( $key_id, self::key_id( $constant ) ) ) return true;
		$ring = self::keyring();
		return isset( $ring['keys'][ $key_id ] ) && 'needs_passphrase' !== self::entry_state( $key_id, $ring['keys'][ $key_id ], $ring );
	}
	/** Una frase o una clave escrita al restaurar; false si no corresponde a la huella del respaldo. */
	public static function resolve_with_secret( $descriptor, $secret ) {
		if ( ! self::valid_descriptor( $descriptor ) || ! is_string( $secret ) || '' === $secret || ! Digitalisimo_Backups_Sodium::available() ) return false;
		$raw = self::parse_raw_key( $secret );
		if ( false !== $raw && hash_equals( $descriptor['id'], self::key_id( $raw ) ) ) return $raw;
		if ( 'passphrase' !== $descriptor['source'] ) return false;
		$root = self::derive( $secret, $descriptor['kdf'] );
		return false !== $root && hash_equals( $descriptor['id'], self::key_id( $root ) ) ? $root : false;
	}

	/** Lo que el diario de una restauración guarda en disco: huellas, parámetros de cada frase y la seguridad. Nunca una clave ni su envoltura. */
	public static function public_snapshot() {
		$ring = self::keyring();
		$keys = array();
		foreach ( $ring['keys'] as $key_id => $entry ) $keys[ $key_id ] = array( 'source' => 'passphrase', 'kdf' => $entry['kdf'], 'created' => (int) ( $entry['created'] ?? 0 ), 'retired' => (int) ( $entry['retired'] ?? 0 ) );
		$security = self::get( Digitalisimo_Backups_Security::OPTION, array() );
		$security = is_array( $security ) ? $security : array();
		return array( 'keys' => $keys, 'active' => $ring['active'], 'security' => array( 'encrypt' => ! empty( $security['encrypt'] ), 'require_signed' => ! empty( $security['require_signed'] ) ) );
	}
	/**
	 * Tras una restauración interrumpida: añade al llavero las claves que faltan, sin envoltura (piden la frase, que
	 * vuelve a abrir la misma clave porque sus parámetros están aquí), y nunca deja la seguridad más débil que antes.
	 * Una clave que el llavero ya tiene se conserva con su envoltura.
	 */
	public static function recover_public( $journal ) {
		$current = self::get( self::KEYRING, null );
		$ring = is_array( $current ) ? $current : array( 'version' => 1, 'active' => '', 'keys' => array() );
		$ring['keys'] = is_array( $ring['keys'] ?? null ) ? $ring['keys'] : array();
		$changed = ! is_array( $current );
		foreach ( (array) ( $journal['keys'] ?? array() ) as $key_id => $entry ) {
			if ( ! is_string( $key_id ) || ! preg_match( '/^[0-9a-f]{32}$/D', $key_id ) || isset( $ring['keys'][ $key_id ] ) || ! is_array( $entry ) || ! self::valid_kdf( $entry['kdf'] ?? null ) ) continue;
			$ring['keys'][ $key_id ] = array( 'source' => 'passphrase', 'kdf' => $entry['kdf'], 'created' => (int) ( $entry['created'] ?? 0 ), 'retired' => (int) ( $entry['retired'] ?? 0 ) );
			$changed = true;
		}
		$active = (string) ( $journal['active'] ?? '' );
		if ( empty( $ring['active'] ) && '' !== $active && isset( $ring['keys'][ $active ] ) ) { $ring['active'] = $active; $changed = true; }
		if ( $changed && $ring['keys'] ) self::put( self::KEYRING, $ring );
		$old = is_array( $journal['security'] ?? null ) ? $journal['security'] : array();
		$new = self::get( Digitalisimo_Backups_Security::OPTION, array() );
		$new = is_array( $new ) ? $new : array();
		if ( $old || $new ) self::put( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => ! empty( $old['encrypt'] ) || ! empty( $new['encrypt'] ), 'require_signed' => ! empty( $old['require_signed'] ) || ! empty( $new['require_signed'] ) ) );
	}
	/** Lo que una restauración no puede llevarse: el SQL restaurado reemplaza options y sitemeta. */
	public static function snapshot() { return array( 'keyring' => self::get( self::KEYRING, null ), 'security' => self::get( Digitalisimo_Backups_Security::OPTION, null ) ); }
	/** Une los llaveros por huella y nunca deja la protección más débil que antes de restaurar. */
	public static function restore_snapshot( $before ) {
		$current = self::get( self::KEYRING, null );
		$ring = is_array( $before['keyring'] ?? null ) ? $before['keyring'] : ( is_array( $current ) ? $current : null );
		if ( is_array( $ring ) ) {
			$ring['keys'] = (array) ( $ring['keys'] ?? array() );
			if ( is_array( $current ) ) foreach ( (array) ( $current['keys'] ?? array() ) as $key_id => $entry ) if ( ! isset( $ring['keys'][ $key_id ] ) ) $ring['keys'][ $key_id ] = $entry;
			if ( empty( $ring['active'] ) && is_array( $current ) && ! empty( $current['active'] ) ) $ring['active'] = $current['active'];
			self::put( self::KEYRING, $ring );
		}
		$old = is_array( $before['security'] ?? null ) ? $before['security'] : array();
		$new = self::get( Digitalisimo_Backups_Security::OPTION, array() );
		$new = is_array( $new ) ? $new : array();
		if ( $old || $new ) self::put( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => ! empty( $old['encrypt'] ) || ! empty( $new['encrypt'] ), 'require_signed' => ! empty( $old['require_signed'] ) || ! empty( $new['require_signed'] ) ) );
	}
}
