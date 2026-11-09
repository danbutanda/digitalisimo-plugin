<?php
defined( 'ABSPATH' ) || exit;

/**
 * Credenciales de los destinos (contraseñas, claves privadas, Secret access keys, Client secret y tokens de OAuth de
 * SFTP, FTP/FTPS, WebDAV, Google Drive, OneDrive, Dropbox, Amazon S3 y compatibles y pCloud) cifradas en
 * reposo. Viven en la opción digitalisimo_backups_credentials (de la red en Multisite), que SÍ entra en el SQL de cada
 * respaldo: restaurar deja los destinos funcionando. Nunca se guardan en claro en digitalisimo_backups_settings
 * mientras wp-config.php defina AUTH_KEY y SECURE_AUTH_KEY propias (las mismas reglas que el llavero).
 *
 * Formato v1: {"v":1,"items":{"<campo>":{"kw":"<huella de la clave, 16 hex>","c":"<base64url(nonce ‖ cifrado)>"}}}.
 * Cada elemento es AEAD XChaCha20-Poly1305 (libsodium o el sodium_compat de WordPress) con un nonce de 24 bytes y el
 * dato adicional «DGBK1/credentials|v1|<campo>»: un valor no se puede mover a otro campo ni a otro formato. La clave es
 * K = BLAKE2b-256("DGBK1/credentials", clave = BLAKE2b-256(AUTH_KEY ‖ "\0" ‖ SECURE_AUTH_KEY)), con separación de
 * dominio distinta a la del llavero, y su huella kw = hex(BLAKE2b-128("DGBK1/credentials-id", clave = K))[0..16].
 * Cada elemento lleva su huella: si cambian las claves de wp-config.php, o el SQL viene de otro servidor y no se pudo
 * volver a cifrar, el campo queda «vuelve a escribirlo» y los demás siguen sirviendo. Nunca se cae al texto en claro.
 */
final class Digitalisimo_Backups_Credentials {
	const OPTION = 'digitalisimo_backups_credentials';
	const VERSION = 1;
	const FIELDS = array( 'sftp_password', 'google_client_secret', 'google_refresh_token', 'onedrive_client_secret', 'onedrive_refresh_token', 'sftp_private_key', 'sftp_key_passphrase', 'ftp_password', 'webdav_password', 'dropbox_refresh_token', 's3_secret_key', 's3compat_secret_key', 'pcloud_client_secret', 'pcloud_access_token' );
	/** Campos de cada destino remoto (la conexión principal de cada tipo). */
	const DESTINATIONS = array( 'sftp' => array( 'sftp_password', 'sftp_private_key', 'sftp_key_passphrase' ), 'google' => array( 'google_client_secret', 'google_refresh_token' ), 'onedrive' => array( 'onedrive_client_secret', 'onedrive_refresh_token' ), 'ftp' => array( 'ftp_password' ), 'webdav' => array( 'webdav_password' ), 'dropbox' => array( 'dropbox_refresh_token' ), 's3' => array( 's3_secret_key' ), 's3compat' => array( 's3compat_secret_key' ), 'pcloud' => array( 'pcloud_client_secret', 'pcloud_access_token' ) );
	/** Qué volver a escribir en cada tipo cuando sus credenciales no se abren. */
	const REENTRY = array( 'sftp' => 'vuelve a escribir la contraseña de SFTP (o su clave privada)', 'ftp' => 'vuelve a escribir la contraseña de FTP', 'webdav' => 'vuelve a escribir la contraseña de WebDAV', 'google' => 'vuelve a escribir el Client secret de Google Drive y conéctalo de nuevo', 'onedrive' => 'vuelve a escribir el Client secret de OneDrive y conéctalo de nuevo', 'dropbox' => 'conecta de nuevo Dropbox', 's3' => 'vuelve a escribir la Secret access key de Amazon S3', 's3compat' => 'vuelve a escribir la Secret access key del almacenamiento compatible con S3', 'pcloud' => 'vuelve a escribir el Client secret de pCloud y conéctalo de nuevo' );
	const LABELS = array( 'sftp' => 'SFTP', 'ftp' => 'FTP/FTPS', 'webdav' => 'WebDAV', 'google' => 'Google Drive', 'onedrive' => 'OneDrive', 'dropbox' => 'Dropbox', 's3' => 'Amazon S3', 's3compat' => 'S3 compatible', 'pcloud' => 'pCloud' );
	const MAX_VALUE = 16384;
	/** Un wp-config.php de un respaldo más grande no se analiza. */
	const CONFIG_MAX = 1048576;
	/** Valores candidatos por constante (un define dentro de un if, otro fuera…): cada combinación se comprueba por su huella. */
	const CONFIG_CANDIDATES = 4;

	/** AEAD XChaCha20-Poly1305 y BLAKE2b de libsodium o del sodium_compat de WordPress. */
	public static function available() {
		return Digitalisimo_Backups_Sodium::available() && function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) && function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' );
	}
	/**
	 * Clave de las credenciales a partir de los valores dados de AUTH_KEY y SECURE_AUTH_KEY (los de este servidor o los
	 * del wp-config.php de un respaldo). false si no son propios: menos de 16 bytes o el texto de ejemplo de WordPress.
	 */
	public static function key_from( $auth, $secure ) {
		if ( ! is_string( $auth ) || ! is_string( $secure ) ) return false;
		foreach ( array( $auth, $secure ) as $value ) if ( strlen( $value ) < 16 || 'put your unique phrase here' === $value ) return false;
		if ( ! self::available() ) return false;
		return sodium_crypto_generichash( 'DGBK1/credentials', sodium_crypto_generichash( $auth . "\0" . $secure, '', 32 ), 32 );
	}
	/** Clave de este servidor (sus constantes de wp-config.php), o false. */
	public static function key() {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_KEY' ) ) return false;
		return self::key_from( (string) constant( 'AUTH_KEY' ), (string) constant( 'SECURE_AUTH_KEY' ) );
	}
	/** Si este servidor puede cifrar las credenciales. */
	public static function can_encrypt() {
		$key = self::key();
		if ( false === $key ) return false;
		Digitalisimo_Backups_Sodium::memzero( $key );
		return true;
	}
	public static function key_id( $key ) { return substr( bin2hex( sodium_crypto_generichash( 'DGBK1/credentials-id', $key, 16 ) ), 0, 16 ); }
	private static function aad( $field ) { return 'DGBK1/credentials|v' . self::VERSION . '|' . $field; }
	/**
	 * Un campo de la conexión principal (`sftp_password`) o de una conexión adicional del mismo tipo
	 * (`sftp-0a1b2c3d/sftp_password`): el dato adicional incluye el nombre completo, así que un valor no se mueve de una
	 * conexión a otra.
	 */
	public static function valid_field( $field ) {
		if ( ! is_string( $field ) ) return false;
		if ( in_array( $field, self::FIELDS, true ) ) return true;
		return 1 === preg_match( '/^([a-z0-9]+)-[a-f0-9]{8}\/([a-z0-9_]+)$/', $field, $match ) && in_array( $match[2], self::DESTINATIONS[ $match[1] ] ?? array(), true );
	}
	/** Campos del almacén de un destino: los del tipo, o los de una conexión adicional con su prefijo. */
	public static function fields_of( $destination ) {
		$destination = (string) $destination;
		if ( isset( self::DESTINATIONS[ $destination ] ) ) return self::DESTINATIONS[ $destination ];
		if ( ! preg_match( '/^([a-z0-9]+)-[a-f0-9]{8}$/', $destination, $match ) || ! isset( self::DESTINATIONS[ $match[1] ] ) ) return array();
		return array_map( function( $field ) use ( $destination ) { return $destination . '/' . $field; }, self::DESTINATIONS[ $match[1] ] );
	}
	/** Retira del almacén estos campos (al borrar una conexión adicional). */
	public static function remove( array $fields ) {
		$store = self::raw();
		$changed = false;
		foreach ( $fields as $field ) if ( isset( $store['items'][ $field ] ) ) { unset( $store['items'][ $field ] ); $changed = true; }
		if ( $changed ) Digitalisimo_Backups_Keys::put( self::OPTION, array( 'v' => self::VERSION, 'items' => $store['items'] ) );
	}

	/** Un elemento cifrado del almacén para $field. */
	public static function seal( $key, $field, $value ) {
		if ( ! self::valid_field( $field ) ) throw new Exception( 'Campo de credencial desconocido.' );
		$nonce = random_bytes( 24 );
		return array( 'kw' => self::key_id( $key ), 'c' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( (string) $value, self::aad( $field ), $nonce, $key ) ) );
	}
	/** El texto de un elemento, o false si no es de esta clave, es de otro campo o fue alterado. */
	public static function open( $key, $field, $item ) {
		if ( ! self::valid_field( $field ) || ! is_array( $item ) || ! is_string( $item['kw'] ?? null ) || ! hash_equals( self::key_id( $key ), $item['kw'] ) ) return false;
		$raw = Digitalisimo_Backups_Keys::b64url_decode( $item['c'] ?? null );
		if ( false === $raw || strlen( $raw ) < 24 + 16 || strlen( $raw ) > 24 + 16 + self::MAX_VALUE ) return false;
		try {
			$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, 24 ), self::aad( $field ), substr( $raw, 0, 24 ), $key );
		} catch ( Throwable $e ) {
			return false;
		}
		return is_string( $plain ) && '' !== $plain ? $plain : false;
	}

	/** El almacén tal como está guardado (sólo campos conocidos); un formato desconocido se lee como elementos que no abren. */
	public static function raw() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
		$store = array( 'v' => self::VERSION, 'items' => array() );
		if ( ! is_array( $saved ) ) return $store;
		$known = self::VERSION === ( $saved['v'] ?? null );
		foreach ( (array) ( $saved['items'] ?? array() ) as $field => $item ) {
			if ( ! self::valid_field( $field ) || ! is_array( $item ) ) continue;
			$store['items'][ $field ] = $known ? array( 'kw' => (string) ( $item['kw'] ?? '' ), 'c' => (string) ( $item['c'] ?? '' ) ) : array( 'kw' => '', 'c' => '' );
		}
		return $store;
	}
	/** Los valores que este servidor abre: campo => texto. Sin clave propia no lee nada. */
	public static function read() {
		$key = self::key();
		if ( false === $key ) return array();
		$out = array();
		foreach ( self::raw()['items'] as $field => $item ) {
			$value = self::open( $key, $field, $item );
			if ( false !== $value ) $out[ $field ] = $value;
		}
		Digitalisimo_Backups_Sodium::memzero( $key );
		return $out;
	}
	/** Campos guardados que este servidor no abre (otras claves de wp-config.php, un valor alterado o movido de campo). */
	public static function locked() {
		$items = self::raw()['items'];
		if ( ! $items ) return array();
		return array_values( array_diff( array_keys( $items ), array_keys( self::read() ) ) );
	}

	/**
	 * Cifra y guarda estos valores (un valor vacío no cambia lo guardado) y comprueba que vuelven a abrirse. Devuelve
	 * false, sin escribir nada, si este servidor no puede cifrar (sin AUTH_KEY y SECURE_AUTH_KEY propias).
	 */
	public static function put( array $values ) {
		$key = self::key();
		if ( false === $key ) return false;
		try {
			$store = self::raw();
			$written = array();
			foreach ( $values as $field => $value ) {
				if ( ! self::valid_field( $field ) || ! is_string( $value ) || '' === $value ) continue;
				if ( strlen( $value ) > self::MAX_VALUE ) throw new Exception( 'Una credencial es demasiado larga para guardarla. No se cambió nada.' );
				$store['items'][ $field ] = self::seal( $key, $field, $value );
				$written[ $field ] = $value;
			}
			if ( ! $written ) return true;
			self::save( $store, $key, $written );
			return true;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
	}
	/** Guarda el almacén y comprueba, leyéndolo otra vez, que cada valor escrito se abre igual. */
	private static function save( array $store, $key, array $written ) {
		Digitalisimo_Backups_Keys::put( self::OPTION, array( 'v' => self::VERSION, 'items' => $store['items'] ) );
		$check = self::raw()['items'];
		foreach ( $written as $field => $value ) {
			$opened = self::open( $key, $field, $check[ $field ] ?? null );
			if ( false === $opened || ! hash_equals( $value, $opened ) ) throw new Exception( 'No se pudo comprobar una credencial cifrada; se conserva como estaba.' );
		}
	}

	/**
	 * Tras restaurar: los elementos cifrados con la clave de otro servidor ($origin, sacada del wp-config.php del respaldo)
	 * se vuelven a cifrar con la de este. Devuelve cuántos. Lo que ninguna clave abre se queda como está (pide escribirse).
	 */
	public static function reseal( array $origin ) {
		if ( ! $origin ) return 0;
		$key = self::key();
		if ( false === $key ) return 0;
		try {
			$current = self::key_id( $key );
			$store = self::raw();
			$written = array();
			foreach ( $store['items'] as $field => $item ) {
				if ( hash_equals( $current, $item['kw'] ) ) continue;
				foreach ( $origin as $old ) {
					$value = is_string( $old ) ? self::open( $old, $field, $item ) : false;
					if ( false === $value ) continue;
					$store['items'][ $field ] = self::seal( $key, $field, $value );
					$written[ $field ] = $value;
					break;
				}
			}
			if ( $written ) self::save( $store, $key, $written );
			return count( $written );
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
	}

	/**
	 * Claves de las credenciales del servidor de origen, desde el wp-config.php que lleva el propio respaldo (ya
	 * comprobado contra su manifiesto). Sólo se leen literales de cadena: nunca se ejecuta ni se incluye ese archivo.
	 * Devuelve huella => clave; vacío si no se pueden obtener (definidas con getenv(), en otro archivo…).
	 */
	public static function origin_keys( $zip ) {
		if ( ! $zip instanceof ZipArchive || ! self::available() ) return array();
		$stat = $zip->statName( 'wordpress/wp-config.php' );
		if ( ! is_array( $stat ) || (int) $stat['size'] > self::CONFIG_MAX ) return array();
		$php = $zip->getFromName( 'wordpress/wp-config.php' );
		return is_string( $php ) ? self::keys_from_config( $php ) : array();
	}
	public static function keys_from_config( $php ) {
		$salts = self::config_salts( $php );
		$keys = array();
		foreach ( array_slice( $salts['AUTH_KEY'], 0, self::CONFIG_CANDIDATES ) as $auth ) {
			foreach ( array_slice( $salts['SECURE_AUTH_KEY'], 0, self::CONFIG_CANDIDATES ) as $secure ) {
				$key = self::key_from( $auth, $secure );
				if ( false !== $key ) $keys[ self::key_id( $key ) ] = $key;
			}
		}
		return $keys;
	}
	/**
	 * Valores de `define( 'AUTH_KEY'|'SECURE_AUTH_KEY', '<literal>' )` con literales de cadena, leídos con el tokenizador
	 * de PHP. Una expresión (getenv(), una concatenación, otra constante) no es un literal y se ignora.
	 */
	public static function config_salts( $php ) {
		$found = array( 'AUTH_KEY' => array(), 'SECURE_AUTH_KEY' => array() );
		if ( ! is_string( $php ) || '' === $php || strlen( $php ) > self::CONFIG_MAX || ! function_exists( 'token_get_all' ) ) return $found;
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
		for ( $i = 0; $i + 5 < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || ! in_array( $token[0], $names, true ) || 'define' !== strtolower( ltrim( $token[1], '\\' ) ) ) continue;
			$before = $tokens[ $i - 1 ] ?? null;
			if ( is_array( $before ) && in_array( $before[0], $member, true ) ) continue;
			list( , $open, $name, $comma, $value, $close ) = array_slice( $tokens, $i, 6 );
			if ( '(' !== $open || ',' !== $comma || ! is_array( $name ) || T_CONSTANT_ENCAPSED_STRING !== $name[0] || ! is_array( $value ) || T_CONSTANT_ENCAPSED_STRING !== $value[0] ) continue;
			// Sólo el literal entero: define( 'X', 'v' ) o el antiguo define( 'X', 'v', true|false ).
			$flag = $tokens[ $i + 6 ] ?? null;
			if ( ')' !== $close && ! ( ',' === $close && is_array( $flag ) && in_array( strtolower( $flag[1] ), array( 'true', 'false' ), true ) && ')' === ( $tokens[ $i + 7 ] ?? null ) ) ) continue;
			$constant = self::literal( $name[1] );
			if ( null === $constant || ! isset( $found[ $constant ] ) ) continue;
			$literal = self::literal( $value[1] );
			if ( null !== $literal && ! in_array( $literal, $found[ $constant ], true ) ) $found[ $constant ][] = $literal;
		}
		return $found;
	}
	/** Valor de un literal de cadena de PHP con sus reglas de escape; null si no se puede leer con seguridad. */
	private static function literal( $token ) {
		if ( ! is_string( $token ) ) return null;
		if ( '' !== $token && ( 'b' === $token[0] || 'B' === $token[0] ) ) $token = substr( $token, 1 );
		if ( strlen( $token ) < 2 ) return null;
		$quote = $token[0];
		if ( substr( $token, -1 ) !== $quote ) return null;
		$body = substr( $token, 1, -1 );
		if ( "'" === $quote ) return preg_replace_callback( "/\\\\([\\\\'])/", function( $match ) { return $match[1]; }, $body );
		if ( '"' !== $quote ) return null;
		if ( false !== strpos( $body, '\\u{' ) ) return null;
		$simple = array( 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\x1b", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"' );
		return preg_replace_callback(
			'/\\\\(?:([nrtvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2}))/',
			function( $match ) use ( $simple ) {
				if ( isset( $match[1] ) && '' !== $match[1] ) return $simple[ $match[1] ];
				if ( isset( $match[2] ) && '' !== $match[2] ) return chr( octdec( $match[2] ) & 255 );
				return chr( hexdec( $match[3] ) );
			},
			$body
		);
	}

	/**
	 * Para el diario de una restauración: las claves de origen, cifradas con la de este servidor (nunca en claro). Si el
	 * proceso muere entre el SQL y el nuevo cifrado, la operación siguiente las recupera con from_journal().
	 */
	public static function journal( array $origin ) {
		if ( ! $origin ) return array();
		$key = self::key();
		if ( false === $key ) return array();
		$out = array( 'v' => 1, 'kw' => self::key_id( $key ), 'keys' => array() );
		foreach ( $origin as $old ) {
			if ( ! is_string( $old ) || 32 !== strlen( $old ) ) continue;
			$id = self::key_id( $old );
			$nonce = random_bytes( 24 );
			$out['keys'][ $id ] = Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $old, 'DGBK1/credentials-journal|' . $id, $nonce, $key ) );
		}
		Digitalisimo_Backups_Sodium::memzero( $key );
		return $out['keys'] ? $out : array();
	}
	public static function from_journal( $journal ) {
		if ( ! is_array( $journal ) || 1 !== ( $journal['v'] ?? null ) || ! is_array( $journal['keys'] ?? null ) ) return array();
		$key = self::key();
		if ( false === $key ) return array();
		$keys = array();
		if ( hash_equals( self::key_id( $key ), (string) ( $journal['kw'] ?? '' ) ) ) {
			foreach ( array_slice( $journal['keys'], 0, self::CONFIG_CANDIDATES * self::CONFIG_CANDIDATES, true ) as $id => $box ) {
				$raw = Digitalisimo_Backups_Keys::b64url_decode( $box );
				if ( ! is_string( $id ) || false === $raw || 24 + 16 + 32 !== strlen( $raw ) ) continue;
				try { $old = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, 24 ), 'DGBK1/credentials-journal|' . $id, substr( $raw, 0, 24 ), $key ); }
				catch ( Throwable $e ) { $old = false; }
				if ( is_string( $old ) && 32 === strlen( $old ) && hash_equals( $id, self::key_id( $old ) ) ) $keys[ $id ] = $old;
			}
		}
		Digitalisimo_Backups_Sodium::memzero( $key );
		return $keys;
	}
	public static function forget( array &$keys ) {
		foreach ( $keys as &$key ) Digitalisimo_Backups_Sodium::memzero( $key );
		unset( $key );
		$keys = array();
	}

	/** Destino al que pertenece un campo. */
	public static function destination_of( $field ) {
		if ( is_string( $field ) && preg_match( '/^([a-z0-9]+-[a-f0-9]{8})\//', $field, $match ) ) return $match[1];
		foreach ( self::DESTINATIONS as $destination => $fields ) if ( in_array( $field, $fields, true ) ) return $destination;
		return '';
	}
	/** Qué hay que hacer para volver a usar cada destino con credenciales que no se abren. */
	private static function reentry_text( array $destinations ) {
		$names = array();
		$actions = array();
		foreach ( $destinations as $destination ) {
			$type = preg_match( '/^([a-z0-9]+)-[a-f0-9]{8}$/', (string) $destination, $match ) ? $match[1] : (string) $destination;
			$label = self::LABELS[ $type ] ?? $type;
			// Una conexión adicional se nombra por su nombre en la pantalla.
			$name = $type !== $destination && class_exists( 'Digitalisimo_Backups_Destinations' ) ? Digitalisimo_Backups_Destinations::name_of( $destination ) : '';
			$names[] = '' !== $name ? $label . ' «' . $name . '»' : $label;
			$actions[] = ( self::REENTRY[ $type ] ?? 'vuelve a escribir sus credenciales' ) . ( '' !== $name ? ' (conexión «' . $name . '»)' : '' );
		}
		return 'Las credenciales guardadas de ' . implode( ' y ', $names ) . ' no se pueden abrir con las claves AUTH_KEY y SECURE_AUTH_KEY de este wp-config.php: cambiaron, o el sitio se restauró desde otro servidor sin poder leer las suyas. En Backups → Servidores, ' . implode( '; ', $actions ) . '. Mientras tanto no se copia ningún respaldo a ' . ( 1 === count( $names ) ? 'ese destino' : 'esos destinos' ) . '.';
	}
	/** Mensaje para un destino cuyas credenciales no abren ('' si no es el caso). $plain: campos que siguen en claro. */
	public static function destination_problem( $destination, array $plain = array() ) {
		$fields = self::fields_of( $destination );
		if ( ! $fields ) return '';
		$locked = array_diff( array_intersect( self::locked(), $fields ), $plain );
		return $locked ? self::reentry_text( array( $destination ) ) : '';
	}
	/**
	 * Estado público, sin ningún valor: 'none' (no hay credenciales), 'encrypted', 'unprotected' (en claro porque
	 * wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias) o 'needs_reentry' (alguna no se abre). $plain son los
	 * campos que siguen en claro en los ajustes. `notice` explica qué hacer ('' si no hay nada que hacer).
	 */
	public static function status( array $plain ) {
		$plain = array_values( array_intersect( self::FIELDS, $plain ) );
		$can = self::can_encrypt();
		$locked = array_values( array_diff( self::locked(), $plain ) );
		$stored = array_keys( self::raw()['items'] );
		$notices = array();
		if ( $locked ) {
			$destinations = array_values( array_unique( array_map( array( __CLASS__, 'destination_of' ), $locked ) ) );
			$notices[] = self::reentry_text( $destinations );
		}
		if ( $plain && $can ) $notices[] = 'Algunas credenciales de los destinos siguen sin cifrar en los ajustes: se cifran en la siguiente carga del escritorio.';
		if ( ! $can ) {
			$notices[] = $plain
				? 'Las credenciales de los destinos están guardadas sin cifrar porque wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias (o este servidor no tiene libsodium ni el sodium_compat de WordPress). Defínelas: se cifrarán solas en la siguiente carga del escritorio.'
				: 'wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias: las credenciales de un destino se guardarían sin cifrar. Defínelas antes de conectar un destino.';
		}
		if ( $locked ) $state = 'needs_reentry';
		elseif ( $plain && ! $can ) $state = 'unprotected';
		elseif ( $plain ) $state = 'pending';
		elseif ( $stored ) $state = 'encrypted';
		else $state = 'none';
		return array( 'state' => $state, 'locked' => $locked, 'plain' => $plain, 'notice' => implode( ' ', $notices ) );
	}
}
