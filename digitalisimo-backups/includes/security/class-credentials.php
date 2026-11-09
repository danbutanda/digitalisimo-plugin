<?php
defined( 'ABSPATH' ) || exit;

/**
 * Credenciales de los destinos (contraseña SFTP, Client secret y Refresh token de Google Drive y OneDrive) cifradas en
 * reposo. Viven en la opción digitalisimo_backups_credentials (de la red en Multisite), que SÍ entra en el SQL de cada
 * respaldo: restaurar deja los destinos funcionando. Nunca se guardan en claro en digitalisimo_backups_settings
 * mientras wp-config.php defina AUTH_KEY y SECURE_AUTH_KEY propias (las mismas reglas que el llavero).
 *
 * Formato v1: {"v":1,"items":{"<campo>":{"kw":"<huella de la clave, 16 hex>","c":"<base64url(nonce ‖ cifrado)>"}}}.
 * Cada elemento es AEAD XChaCha20-Poly1305 (libsodium o el sodium_compat de WordPress) con un nonce de 24 bytes y el
 * dato adicional «DGBK1/credentials|v1|<campo>|<contexto>», donde el contexto son los datos de la conexión a la que va
 * el secreto (host, puerto, usuario y huella SSH para SFTP; el Client ID para Google Drive y OneDrive), cada uno como
 * «<longitud>:<valor>;». Un valor no se puede mover a otro campo ni a otro formato, y una escritura directa en la base de
 * datos que cambie el host o el Client ID no desvía el secreto: deja de abrirse y pide escribirlo. La clave es
 * K = BLAKE2b-256("DGBK1/credentials", clave = BLAKE2b-256(AUTH_KEY ‖ "\0" ‖ SECURE_AUTH_KEY)), con separación de
 * dominio distinta a la del llavero, y su huella kw = hex(BLAKE2b-128("DGBK1/credentials-id", clave = K))[0..16].
 * Cada elemento lleva su huella: si cambian las claves de wp-config.php, o el SQL viene de otro servidor y no se pudo
 * volver a cifrar, el campo queda «vuelve a escribirlo» y los demás siguen sirviendo. Nunca se cae al texto en claro.
 */
final class Digitalisimo_Backups_Credentials {
	const OPTION = 'digitalisimo_backups_credentials';
	const VERSION = 1;
	const FIELDS = array( 'sftp_password', 'google_client_secret', 'google_refresh_token', 'onedrive_client_secret', 'onedrive_refresh_token' );
	/** Campos de cada destino remoto. */
	const DESTINATIONS = array( 'sftp' => array( 'sftp_password' ), 'google' => array( 'google_client_secret', 'google_refresh_token' ), 'onedrive' => array( 'onedrive_client_secret', 'onedrive_refresh_token' ) );
	const MAX_VALUE = 16384;
	/** Longitud máxima de un secreto escrito (bytes): de sobra para una contraseña o un refresh token de OneDrive. */
	const MAX_SECRET = 8192;
	const LABELS = array( 'sftp_password' => 'La contraseña de SFTP', 'google_client_secret' => 'El Client secret de Google Drive', 'google_refresh_token' => 'El Refresh token de Google Drive', 'onedrive_client_secret' => 'El Client secret de OneDrive', 'onedrive_refresh_token' => 'El Refresh token de OneDrive' );

	/**
	 * Por qué un secreto escrito (o devuelto por OAuth) no se puede guardar; '' si se puede. Se guarda exactamente como
	 * llegó, sin recortar ni sanear: comillas, barras, <…>, %xx y espacios al borde forman parte del secreto. Sólo se
	 * rechazan lo que no es texto, lo que pasa de MAX_SECRET bytes, NUL y los caracteres de control (C0, DEL y C1).
	 */
	public static function secret_problem( $value ) {
		if ( ! is_string( $value ) ) return 'no es texto';
		if ( strlen( $value ) > self::MAX_SECRET ) return 'pasa de ' . self::MAX_SECRET . ' bytes';
		if ( preg_match( '/[\x00-\x1F\x7F]/', $value ) || 1 === @preg_match( '/[\x{80}-\x{9F}]/u', $value ) ) return 'contiene caracteres de control (como NUL, saltos de línea o tabuladores)';
		return '';
	}
	/** Nombre de un campo secreto para los mensajes (nunca su valor). */
	public static function label( $field ) { return self::LABELS[ $field ] ?? 'Una credencial'; }
	/**
	 * Un wp-config.php de un respaldo más grande no se analiza (sus salts quedan «vuelve a escribir»). Uno real ocupa de 3 a
	 * 10 KB; con 64 KiB, el tokenizador usa unos 18 MiB como mucho, así que uno hostil no agota la memoria.
	 */
	const CONFIG_MAX = 65536;
	/** Datos de la conexión que ata el dato adicional de cada secreto. */
	const CONTEXT = array( 'sftp_password' => array( 'sftp_host', 'sftp_port', 'sftp_user', 'sftp_fingerprint' ), 'google_client_secret' => array( 'google_client_id' ), 'google_refresh_token' => array( 'google_client_id' ), 'onedrive_client_secret' => array( 'onedrive_client_id' ), 'onedrive_refresh_token' => array( 'onedrive_client_id' ) );
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
	/**
	 * Datos de la conexión de $field en unos ajustes (los no secretos), normalizados como los guarda el formulario: puerto
	 * como entero y huella en mayúsculas sin dos puntos. Lo que no es texto ni número cuenta como vacío.
	 */
	public static function context( $field, array $settings ) {
		$out = '';
		foreach ( self::CONTEXT[ $field ] ?? array() as $name ) {
			$value = $settings[ $name ] ?? '';
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( 'sftp_port' === $name ) $value = (string) (int) $value;
			if ( 'sftp_fingerprint' === $name ) $value = strtoupper( str_replace( ':', '', trim( $value ) ) );
			$out .= strlen( $value ) . ':' . $value . ';';
		}
		return $out;
	}
	private static function aad( $field, array $settings ) { return 'DGBK1/credentials|v' . self::VERSION . '|' . $field . '|' . self::context( $field, $settings ); }
	public static function valid_field( $field ) { return is_string( $field ) && in_array( $field, self::FIELDS, true ); }

	/** Un elemento cifrado del almacén para $field, atado a la conexión que describen $settings. */
	public static function seal( $key, $field, $value, array $settings ) {
		if ( ! self::valid_field( $field ) ) throw new Exception( 'Campo de credencial desconocido.' );
		$nonce = random_bytes( 24 );
		return array( 'kw' => self::key_id( $key ), 'c' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( (string) $value, self::aad( $field, $settings ), $nonce, $key ) ) );
	}
	/** El texto de un elemento, o false si no es de esta clave, es de otro campo, de otra conexión o fue alterado. */
	public static function open( $key, $field, $item, array $settings ) {
		if ( ! self::valid_field( $field ) || ! is_array( $item ) || ! is_string( $item['kw'] ?? null ) || ! hash_equals( self::key_id( $key ), $item['kw'] ) ) return false;
		$raw = Digitalisimo_Backups_Keys::b64url_decode( $item['c'] ?? null );
		if ( false === $raw || strlen( $raw ) < 24 + 16 || strlen( $raw ) > 24 + 16 + self::MAX_VALUE ) return false;
		try {
			$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, 24 ), self::aad( $field, $settings ), substr( $raw, 0, 24 ), $key );
		} catch ( Throwable $e ) {
			return false;
		}
		return is_string( $plain ) && '' !== $plain ? $plain : false;
	}

	/**
	 * El almacén tal como está guardado (sólo campos conocidos). Un formato desconocido, o un elemento cuyos datos no son
	 * texto (un objeto de un SQL hostil o de una escritura directa), se lee como elemento que no abre: pide escribirse y
	 * nunca rompe la lectura de los ajustes ni la pantalla.
	 */
	public static function raw() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, null );
		if ( null === $saved ) $saved = self::adopt_site_store();
		$store = array( 'v' => self::VERSION, 'items' => array() );
		if ( ! is_array( $saved ) ) return $store;
		$known = self::VERSION === ( $saved['v'] ?? null );
		$items = $saved['items'] ?? array();
		foreach ( is_array( $items ) ? $items : array() as $field => $item ) {
			if ( ! self::valid_field( $field ) || ! is_array( $item ) ) continue;
			$kw = $item['kw'] ?? '';
			$c = $item['c'] ?? '';
			$store['items'][ $field ] = $known && is_string( $kw ) && is_string( $c ) ? array( 'kw' => $kw, 'c' => $c ) : array( 'kw' => '', 'c' => '' );
		}
		return $store;
	}
	/**
	 * Un sitio individual convertido en red: el almacén quedó en las opciones del sitio principal y la red lo busca en
	 * sitemeta. Se adopta (mismas salts, mismos datos de conexión): las credenciales siguen funcionando, nunca se pierden.
	 */
	private static function adopt_site_store() {
		if ( ! is_multisite() || ! is_main_site() ) return null;
		$site = get_option( self::OPTION, null );
		if ( ! is_array( $site ) ) return null;
		Digitalisimo_Backups_Keys::put( self::OPTION, $site );
		delete_option( self::OPTION );
		return $site;
	}
	/** Los valores que este servidor abre para la conexión de $settings: campo => texto. Sin clave propia no lee nada. */
	public static function read( array $settings ) {
		$key = self::key();
		if ( false === $key ) return array();
		$out = array();
		foreach ( self::raw()['items'] as $field => $item ) {
			$value = self::open( $key, $field, $item, $settings );
			if ( false !== $value ) $out[ $field ] = $value;
		}
		Digitalisimo_Backups_Sodium::memzero( $key );
		return $out;
	}
	/**
	 * Campos guardados que este servidor no abre para la conexión de $settings (otras claves de wp-config.php, otros datos
	 * de conexión, un valor alterado o movido de campo).
	 */
	public static function locked( array $settings ) {
		$items = self::raw()['items'];
		if ( ! $items ) return array();
		return array_values( array_diff( array_keys( $items ), array_keys( self::read( $settings ) ) ) );
	}
	/**
	 * «Olvidar» las credenciales de un destino que ya no se usa (también las que no se abren): retira sus elementos del
	 * almacén. No necesita la clave. Devuelve los campos retirados.
	 */
	public static function remove( array $fields ) {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, null );
		if ( ! is_array( $saved ) || ! is_array( $saved['items'] ?? null ) ) return array();
		$removed = array_values( array_intersect( array_keys( $saved['items'] ), array_filter( $fields, array( __CLASS__, 'valid_field' ) ) ) );
		if ( ! $removed ) return array();
		foreach ( $removed as $field ) unset( $saved['items'][ $field ] );
		Digitalisimo_Backups_Keys::put( self::OPTION, $saved );
		return $removed;
	}

	/**
	 * Cifra y guarda estos valores para la conexión de $settings (un valor vacío no cambia lo guardado) y comprueba que
	 * vuelven a abrirse. Devuelve false, sin escribir nada, si este servidor no puede cifrar (sin AUTH_KEY y
	 * SECURE_AUTH_KEY propias).
	 */
	public static function put( array $values, array $settings ) {
		$key = self::key();
		if ( false === $key ) return false;
		try {
			$store = self::raw();
			$written = array();
			foreach ( $values as $field => $value ) {
				if ( ! self::valid_field( $field ) || ! is_string( $value ) || '' === $value ) continue;
				if ( strlen( $value ) > self::MAX_VALUE ) throw new Exception( 'Una credencial es demasiado larga para guardarla. No se cambió nada.' );
				$store['items'][ $field ] = self::seal( $key, $field, $value, $settings );
				$written[ $field ] = $value;
			}
			if ( ! $written ) return true;
			self::save( $store, $key, $written, $settings );
			return true;
		} finally {
			Digitalisimo_Backups_Sodium::memzero( $key );
		}
	}
	/**
	 * Guarda el almacén y comprueba, leyéndolo otra vez, que cada valor escrito se abre igual. Si no, vuelve a dejar el
	 * almacén como estaba y avisa: quien llama no retira nada de los ajustes, así que ninguna credencial se pierde.
	 */
	private static function save( array $store, $key, array $written, array $settings ) {
		$before = Digitalisimo_Backups_Keys::get( self::OPTION, null );
		Digitalisimo_Backups_Keys::put( self::OPTION, array( 'v' => self::VERSION, 'items' => $store['items'] ) );
		$check = self::raw()['items'];
		foreach ( $written as $field => $value ) {
			$opened = self::open( $key, $field, $check[ $field ] ?? null, $settings );
			if ( false !== $opened && hash_equals( $value, $opened ) ) continue;
			Digitalisimo_Backups_Keys::put( self::OPTION, null === $before ? array( 'v' => self::VERSION, 'items' => array() ) : $before );
			throw new Exception( 'No se pudo comprobar una credencial cifrada; se conserva como estaba.' );
		}
	}

	/**
	 * Tras restaurar: los elementos cifrados con la clave de otro servidor ($origin, sacada del wp-config.php del respaldo)
	 * se vuelven a cifrar con la de este, para la conexión de los ajustes restaurados ($settings). Devuelve cuántos. Lo
	 * que ninguna clave abre se queda como está (pide escribirse).
	 */
	public static function reseal( array $origin, array $settings ) {
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
					$value = is_string( $old ) ? self::open( $old, $field, $item, $settings ) : false;
					if ( false === $value ) continue;
					$store['items'][ $field ] = self::seal( $key, $field, $value, $settings );
					$written[ $field ] = $value;
					break;
				}
			}
			if ( $written ) self::save( $store, $key, $written, $settings );
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
		// Se recorren los tokens sin copiarlos (saltando espacios y comentarios al vuelo): la memoria es la del tokenizador.
		$names = array( T_STRING );
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) $names[] = constant( 'T_NAME_FULLY_QUALIFIED' );
		$member = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST );
		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) $member[] = constant( 'T_NULLSAFE_OBJECT_OPERATOR' );
		$count = count( $tokens );
		$before = null;
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( self::insignificant( $token ) ) continue;
			$previous = $before;
			$before = $token;
			if ( ! is_array( $token ) || ! in_array( $token[0], $names, true ) || 'define' !== strtolower( ltrim( $token[1], '\\' ) ) ) continue;
			if ( is_array( $previous ) && in_array( $previous[0], $member, true ) ) continue;
			$j = $i;
			$open = self::next_token( $tokens, $count, $j );
			$name = self::next_token( $tokens, $count, $j );
			$comma = self::next_token( $tokens, $count, $j );
			$value = self::next_token( $tokens, $count, $j );
			$close = self::next_token( $tokens, $count, $j );
			if ( '(' !== $open || ',' !== $comma || ! is_array( $name ) || T_CONSTANT_ENCAPSED_STRING !== $name[0] || ! is_array( $value ) || T_CONSTANT_ENCAPSED_STRING !== $value[0] ) continue;
			// Sólo el literal entero: define( 'X', 'v' ) o el antiguo define( 'X', 'v', true|false ).
			if ( ',' === $close ) {
				$flag = self::next_token( $tokens, $count, $j );
				$close = is_array( $flag ) && in_array( strtolower( $flag[1] ), array( 'true', 'false' ), true ) ? self::next_token( $tokens, $count, $j ) : null;
			}
			if ( ')' !== $close ) continue;
			$constant = self::literal( $name[1] );
			if ( null === $constant || ! isset( $found[ $constant ] ) ) continue;
			$literal = self::literal( $value[1] );
			if ( null !== $literal && ! in_array( $literal, $found[ $constant ], true ) ) $found[ $constant ][] = $literal;
		}
		return $found;
	}
	private static function insignificant( $token ) { return is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ); }
	/** El siguiente token significativo después de $j (avanza $j); null al final. Los tokens no se copian. */
	private static function next_token( array $tokens, $count, &$j ) {
		for ( $j++; $j < $count; $j++ ) if ( ! self::insignificant( $tokens[ $j ] ) ) return $tokens[ $j ];
		return null;
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
		// Una lista y no un mapa por huella: una huella toda decimal sería una clave entera de PHP y de JSON.
		$out = array( 'v' => 1, 'kw' => self::key_id( $key ), 'keys' => array() );
		foreach ( $origin as $old ) {
			if ( ! is_string( $old ) || 32 !== strlen( $old ) ) continue;
			$id = self::key_id( $old );
			$nonce = random_bytes( 24 );
			$out['keys'][] = array( 'id' => $id, 'box' => Digitalisimo_Backups_Keys::b64url_encode( $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $old, 'DGBK1/credentials-journal|' . $id, $nonce, $key ) ) );
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
			foreach ( array_slice( array_values( $journal['keys'] ), 0, self::CONFIG_CANDIDATES * self::CONFIG_CANDIDATES ) as $entry ) {
				$id = is_array( $entry ) ? ( $entry['id'] ?? null ) : null;
				$raw = is_array( $entry ) ? Digitalisimo_Backups_Keys::b64url_decode( $entry['box'] ?? null ) : false;
				if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f]{16}$/D', $id ) || false === $raw || 24 + 16 + 32 !== strlen( $raw ) ) continue;
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
		foreach ( self::DESTINATIONS as $destination => $fields ) if ( in_array( $field, $fields, true ) ) return $destination;
		return '';
	}
	/** Qué hay que hacer para volver a usar cada destino con credenciales que no se abren. */
	private static function reentry_text( array $destinations ) {
		$labels = array( 'sftp' => 'SFTP', 'google' => 'Google Drive', 'onedrive' => 'OneDrive' );
		$names = array_map( function( $destination ) use ( $labels ) { return $labels[ $destination ]; }, $destinations );
		$actions = array();
		if ( in_array( 'sftp', $destinations, true ) ) $actions[] = 'vuelve a escribir la contraseña de SFTP';
		foreach ( array( 'google' => 'Google Drive', 'onedrive' => 'OneDrive' ) as $destination => $label ) if ( in_array( $destination, $destinations, true ) ) $actions[] = 'vuelve a escribir el Client secret de ' . $label . ' y conéctalo de nuevo';
		return 'Las credenciales guardadas de ' . implode( ' y ', $names ) . ' no se pueden abrir con las claves AUTH_KEY y SECURE_AUTH_KEY de este wp-config.php y los datos de su conexión: las claves cambiaron, el sitio se restauró desde otro servidor sin poder leer las suyas, o el host, el usuario, la huella o el Client ID cambiaron fuera de este formulario. En Backups → Servidores, ' . implode( '; ', $actions ) . '. Mientras tanto no se copia ningún respaldo a ' . ( 1 === count( $names ) ? 'ese destino' : 'esos destinos' ) . '.';
	}
	/**
	 * Mensaje para un destino cuyas credenciales no abren ('' si no es el caso). $plain: campos que siguen en claro;
	 * $settings: los ajustes guardados (los datos de cada conexión).
	 */
	public static function destination_problem( $destination, array $plain, array $settings ) {
		$fields = self::DESTINATIONS[ $destination ] ?? array();
		if ( ! $fields ) return '';
		$locked = array_diff( array_intersect( self::locked( $settings ), $fields ), $plain );
		return $locked ? self::reentry_text( array( $destination ) ) : '';
	}
	/**
	 * Estado público, sin ningún valor: 'none' (no hay credenciales), 'encrypted', 'unprotected' (en claro porque
	 * wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias) o 'needs_reentry' (alguna no se abre). $plain son los
	 * campos que siguen en claro en los ajustes y $settings los ajustes guardados (los datos de cada conexión). `notice`
	 * explica qué hacer ('' si no hay nada que hacer).
	 */
	public static function status( array $plain, array $settings ) {
		$plain = array_values( array_intersect( self::FIELDS, $plain ) );
		$can = self::can_encrypt();
		$locked = array_values( array_diff( self::locked( $settings ), $plain ) );
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
