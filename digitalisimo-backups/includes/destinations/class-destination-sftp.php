<?php
defined( 'ABSPATH' ) || exit;

/**
 * SFTP con la extensión ssh2 o, sin ella, con el cliente en PHP incluido (phpseclib 3 con prefijo propio,
 * Digitalisimo_Backups_Sftp_Client; I5). «Motor» automático: la extensión si está y puede con la conexión (la huella
 * SHA-256 sólo si la calcula, la clave privada sólo con su clave pública); si no, el cliente incluido. La huella de la
 * clave del servidor es obligatoria y se compara antes de autenticarse: SHA-1 hexadecimal (la que ya se guardaba) o
 * SHA-256 en el formato de OpenSSH (`SHA256:…`). Contraseña o clave privada (con frase opcional). Escritura a
 * `<nombre>.part-<aleatorio>`, comparación SHA-256 leyendo la copia remota y `rename()` sólo después.
 *
 * La copia va por bloques de BLOCK bytes en las dos direcciones (H9 del ensayo E2): stream_copy_to_stream() mapeaba el ZIP
 * entero y se lo daba de una vez a la extensión, así que la memoria subía al tamaño del respaldo y con 64 MB el proceso
 * moría. Cada bloque se escribe entero (las escrituras cortas se repiten) y lo copiado se compara con el tamaño esperado.
 */
final class Digitalisimo_Backups_Destination_Sftp extends Digitalisimo_Backups_Destination {
	const TYPE = 'sftp';
	const PREFIX = 'sftp_';
	/** Bloque de cada lectura y escritura de una transferencia: memoria acotada con un respaldo de cualquier tamaño. */
	const BLOCK = 1048576;
	private $connection = null;
	private $sftp = null;
	private $key_files = array();
	/** Cliente incluido (phpseclib) ya autenticado. */
	private $client = null;

	public static function label() { return 'SFTP'; }
	public static function fields() {
		return array(
			'sftp_host' => array( 'label' => 'Host o IP', 'type' => 'host', 'required' => true, 'placeholder' => 'sftp.ejemplo.com' ),
			'sftp_port' => array( 'label' => 'Puerto', 'type' => 'number', 'default' => '22', 'min' => 1, 'max' => 65535 ),
			'sftp_user' => array( 'label' => 'Usuario', 'type' => 'text', 'required' => true ),
			'sftp_auth' => array( 'label' => 'Autenticación', 'type' => 'select', 'default' => 'password', 'options' => array( 'password' => 'Contraseña', 'key' => 'Clave privada SSH' ) ),
			'sftp_password' => array( 'label' => 'Contraseña', 'type' => 'password', 'secret' => true, 'show' => 'sftp_auth=password' ),
			'sftp_private_key' => array( 'label' => 'Clave privada', 'type' => 'textarea', 'secret' => true, 'show' => 'sftp_auth=key', 'help' => 'Pega la clave privada completa (OpenSSH, PEM o el .ppk de PuTTY). Se guarda cifrada en el almacén de credenciales y nunca vuelve al navegador; si tiene frase, se abre una vez al guardar.' ),
			'sftp_key_passphrase' => array( 'label' => 'Frase de la clave', 'type' => 'password', 'secret' => true, 'show' => 'sftp_auth=key', 'help' => 'Sólo si la clave privada está protegida con una frase.' ),
			'sftp_public_key' => array( 'label' => 'Clave pública', 'type' => 'textarea', 'show' => 'sftp_auth=key', 'help' => 'La línea de la clave pública (ssh-ed25519 AAAA… o ssh-rsa AAAA…) que está en authorized_keys del servidor. Si la dejas vacía, se calcula de la clave privada al guardar.' ),
			'sftp_path' => array( 'label' => 'Carpeta remota', 'type' => 'path', 'default' => '/' ),
			'sftp_fingerprint' => array( 'label' => 'Huella de la clave SSH del servidor', 'type' => 'text', 'help' => 'SHA-256 como la muestra ssh-keygen (SHA256:…) o SHA-1 («SHA1:…» de ssh-keygen -E sha1, o 40 caracteres hexadecimales con o sin dos puntos). Con la extensión ssh2, SHA-256 sólo si la calcula; el cliente incluido calcula las dos. Puedes pegar varias, una por cada clave del servidor (ED25519, ECDSA, RSA; también las líneas de ssh-keygen tal cual): vale la de la clave que presente. Confírmalas con el administrador del servidor por un canal independiente: sin huella no se envían respaldos.' ),
			'sftp_engine' => array( 'label' => 'Motor SFTP', 'type' => 'select', 'default' => 'auto', 'options' => array( 'auto' => 'Automático: la extensión ssh2 si este servidor la tiene; si no, el cliente incluido', 'phpseclib' => 'Cliente SFTP incluido (PHP puro, phpseclib)', 'ssh2' => 'Extensión ssh2 de PHP' ), 'help' => 'El cliente incluido no necesita ninguna extensión (casi ningún hosting compartido trae ssh2), calcula la huella SHA-256 y prueba todas las claves del servidor. Usa OpenSSL y libsodium de PHP si están.' ),
		);
	}
	public static function help() {
		// Revisión P2: sin OpenSSL el cliente incluido es inservible para respaldos de verdad; la pantalla lo dice.
		$client = self::client_class();
		$slow = $client::slow_text();
		return array_merge( '' !== $slow ? array( $slow ) : array(), self::help_lines() );
	}
	private static function help_lines() {
		return array( 'En un equipo de confianza: «ssh-keyscan -p 22 servidor | ssh-keygen -l -f -» muestra la huella SHA-256 de cada clave del servidor (una línea por tipo: ED25519, ECDSA, RSA); con «-E sha1», la SHA-1.', 'Sin la extensión ssh2 se usa el cliente SFTP incluido (PHP puro): calcula SHA-256 y SHA-1 y, si la clave que negocia no es la de la huella, prueba las demás claves del servidor. Con la extensión ssh2, ésta elige el tipo de clave al conectar (con libssh2 suele ser ECDSA aunque el servidor tenga ED25519) y SHA-256 sólo sirve si la calcula: pega las líneas de todas las claves o elige el cliente incluido.', 'Clave privada: OpenSSH, PEM (PKCS#1 o PKCS#8) o PuTTY, también protegida con una frase; se guarda cifrada en el almacén de credenciales y nunca vuelve al navegador.', 'La carpeta remota debe ser una ruta absoluta en la que el usuario pueda escribir.' );
	}
	public static function hosts( array $s ) { return array( (string) ( $s['sftp_host'] ?? '' ) ); }
	public static function ready( array $s ) {
		$auth = 'key' === ( $s['sftp_auth'] ?? 'password' ) ? ! empty( $s['sftp_private_key'] ) && ! empty( $s['sftp_public_key'] ) : ! empty( $s['sftp_password'] );
		return ! empty( $s['sftp_host'] ) && ! empty( $s['sftp_user'] ) && $auth && ! empty( $s['sftp_fingerprint'] );
	}
	public static function sanitize( array $input, array $old ) {
		$fingerprint = trim( (string) ( $input['sftp_fingerprint'] ?? '' ) );
		$normalized = self::fingerprints( $fingerprint );
		if ( '' !== $fingerprint && ! $normalized ) throw new Exception( 'La huella SFTP debe ser SHA-1 («SHA1:…» como la muestra ssh-keygen -E sha1, o 40 caracteres hexadecimales con o sin dos puntos) o SHA-256 (SHA256:… o 64 caracteres hexadecimales); puedes pegar varias, una por clave del servidor, o las líneas de ssh-keygen -l tal cual.' );
		$out = parent::sanitize( $input, $old );
		$out['sftp_fingerprint'] = implode( ' ', $normalized );
		$out['sftp_port'] = (string) min( 65535, max( 1, (int) ( $input['sftp_port'] ?? 22 ) ?: 22 ) );
		$public = trim( preg_replace( '/\s+/', ' ', (string) ( $input['sftp_public_key'] ?? '' ) ) );
		if ( '' !== $public && ! preg_match( '/^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-[a-z0-9@.-]+) [A-Za-z0-9+\/=]+( [^\s].*)?$/', $public ) ) throw new Exception( 'La clave pública SFTP debe ser una línea como «ssh-ed25519 AAAA… comentario».' );
		$private = (string) $out['sftp_private_key'];
		$putty = (bool) preg_match( '/^PuTTY-User-Key-File-[23]: [a-z0-9-]+\n.+Private-MAC: [a-f0-9]+$/s', $private );
		if ( '' !== $private && ! $putty && ! preg_match( '/^-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----\n.+\n-----END [A-Z0-9 ]*PRIVATE KEY-----$/s', $private ) ) throw new Exception( 'La clave privada SFTP debe pegarse completa, de «-----BEGIN … PRIVATE KEY-----» a «-----END … PRIVATE KEY-----» (o el archivo .ppk de PuTTY entero).' );
		// Una clave con frase se abre aquí, una vez: queda sin frase dentro del almacén cifrado (que ya guardaba las dos) y
		// cada conexión se ahorra los segundos de bcrypt_pbkdf en PHP. Sin la clave pública, se calcula de la privada (la
		// extensión ssh2 la necesita). Revisión H7: con frase, la frase equivocada se rechaza aunque se pegue la clave
		// pública. Revisión H8: un .ppk siempre se convierte (libssh2 no lee el formato de PuTTY). Sólo una clave sin frase
		// que trae su clave pública y que el cliente incluido no sabe leer se guarda tal cual, como antes.
		$passphrase = (string) ( $out['sftp_key_passphrase'] ?? '' );
		if ( '' !== $private && 'key' === ( $out['sftp_auth'] ?? '' ) && ( '' === $public || '' !== $passphrase || $putty ) ) {
			$client = self::client_class();
			if ( ! $client::available() ) {
				if ( $putty ) throw new Exception( 'Este PHP no puede convertir el archivo .ppk de PuTTY: expórtalo como clave OpenSSH con PuTTYgen («Conversions → Export OpenSSH key») y pégalo otra vez. No se cambió nada.' );
				if ( '' === $public ) throw new Exception( 'Pega también la clave pública SFTP: este PHP no puede calcularla de la privada.' );
			} else {
				try {
					list( $private, $derived ) = $client::unlock( $private, $passphrase );
				} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
					throw new Exception( $e->getMessage() . ' No se cambió nada.' );
				}
				$out['sftp_private_key'] = $private;
				$out['sftp_key_passphrase'] = '';
				if ( '' === $public ) $public = $derived;
			}
		}
		$out['sftp_public_key'] = $public;
		if ( '' !== (string) $out['sftp_path'] ) $out['sftp_path'] = self::clean_path( $out['sftp_path'] );
		return $out;
	}
	/** Huellas que se aceptan como mucho en el campo (una por clave del servidor). */
	const MAX_FINGERPRINTS = 8;
	/**
	 * SHA-1 → 40 hexadecimales en mayúsculas (como se guardaba; también desde el «SHA1:<base64>» de ssh-keygen -E sha1);
	 * SHA-256 → «SHA256:<base64 sin relleno>». '' si no es válida.
	 */
	public static function normalize_fingerprint( $fingerprint ) {
		$fingerprint = trim( (string) $fingerprint );
		if ( preg_match( '/^(?:[a-f0-9]{40}|(?:[a-f0-9]{2}:){19}[a-f0-9]{2})$/i', $fingerprint ) ) return strtoupper( str_replace( ':', '', $fingerprint ) );
		if ( preg_match( '/^SHA1:([A-Za-z0-9+\/]{27})=?$/', $fingerprint, $match ) ) {
			$raw = base64_decode( $match[1] . '=', true );
			return false !== $raw && 20 === strlen( $raw ) ? strtoupper( bin2hex( $raw ) ) : '';
		}
		if ( preg_match( '/^(?:[a-f0-9]{64}|(?:[a-f0-9]{2}:){31}[a-f0-9]{2})$/i', $fingerprint ) ) return 'SHA256:' . rtrim( base64_encode( hex2bin( str_replace( ':', '', $fingerprint ) ) ), '=' );
		if ( preg_match( '/^SHA256:([A-Za-z0-9+\/]{43})=?$/', $fingerprint, $match ) ) {
			$raw = base64_decode( $match[1] . '=', true );
			return false !== $raw && 32 === strlen( $raw ) ? 'SHA256:' . $match[1] : '';
		}
		return '';
	}
	/**
	 * Todas las huellas de un texto pegado, normalizadas y sin repetir: una o varias separadas por espacios, comas, punto y
	 * coma o saltos de línea, o las líneas de `ssh-keygen -l` tal cual («256 SHA1:… servidor (ECDSA)», también pegadas
	 * en un campo de una línea, que junta las líneas). array() si no hay ninguna, si sobra algo que parece una huella
	 * incompleta o si hay más de MAX_FINGERPRINTS.
	 */
	public static function fingerprints( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text || strlen( $text ) > 4096 ) return array();
		$found = array();
		$pattern = '/(?<![A-Za-z0-9+\/:])(?:SHA256:[A-Za-z0-9+\/]{43}=?|SHA1:[A-Za-z0-9+\/]{27}=?|(?:[a-f0-9]{2}:){31}[a-f0-9]{2}|(?:[a-f0-9]{2}:){19}[a-f0-9]{2}|[a-f0-9]{64}|[a-f0-9]{40})(?![A-Za-z0-9+\/:])/i';
		$rest = preg_replace_callback( $pattern, function( $match ) use ( &$found ) {
			$normalized = self::normalize_fingerprint( $match[0] );
			if ( '' === $normalized ) return $match[0];
			$found[ $normalized ] = true;
			return ' ';
		}, $text );
		// Lo que queda sólo puede ser el resto de una línea de ssh-keygen (bits, nombre del servidor, tipo); un algoritmo
		// sin su huella o una huella a medias es un error, no algo que se ignore.
		if ( null === $rest || preg_match( '/\b(?:SHA256|SHA1|MD5):|[a-f0-9]{16,}|(?:[a-f0-9]{2}:){8,}/i', $rest ) ) return array();
		return count( $found ) > self::MAX_FINGERPRINTS ? array() : array_keys( $found );
	}
	/** Tipo de clave que negoció la conexión: array( nombre para el mensaje, tipo de ssh-keyscan -t ), o tipo desconocido. */
	private static function host_key_type( $connection ) {
		$methods = function_exists( 'ssh2_methods_negotiated' ) ? @ssh2_methods_negotiated( $connection ) : null;
		$name = is_array( $methods ) ? strtolower( (string) ( $methods['hostkey'] ?? '' ) ) : '';
		if ( 0 === strpos( $name, 'ssh-ed25519' ) ) return array( 'ED25519', 'ed25519' );
		if ( 0 === strpos( $name, 'ecdsa-sha2-' ) ) return array( 'ECDSA (' . substr( $name, 11 ) . ')', 'ecdsa' );
		if ( in_array( $name, array( 'ssh-rsa', 'rsa-sha2-256', 'rsa-sha2-512' ), true ) ) return array( 'RSA', 'rsa' );
		if ( 'ssh-dss' === $name ) return array( 'DSA', 'dsa' );
		return array( '' !== $name ? preg_replace( '/[^a-z0-9@.-]/', '', $name ) : '', '' );
	}
	/** Mensaje de una huella que no coincide: qué clave presentó el servidor, su huella y cómo comprobarla. */
	private function mismatch_text( $connection, $sha1, $sha256 ) {
		list( $type, $scan ) = self::host_key_type( $connection );
		$colons = implode( ':', str_split( strtolower( (string) $sha1 ), 2 ) );
		$base64 = preg_match( '/^[A-F0-9]{40}$/', (string) $sha1 ) ? 'SHA1:' . rtrim( base64_encode( (string) hex2bin( (string) $sha1 ) ), '=' ) : '';
		$text = 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.';
		$text .= ' El servidor presentó ' . ( '' !== $type ? 'su clave ' . $type : 'una clave de tipo desconocido' ) . ' (la que negoció la extensión ssh2 de este servidor)';
		if ( '' !== $base64 ) $text .= ' con huella ' . $base64 . ' (SHA-1: ' . $colons . ')' . ( '' !== $sha256 ? ' y ' . $sha256 : '' );
		$port = (int) ( $this->s['sftp_port'] ?? 22 ) ?: 22;
		$text .= '. Compruébala en un equipo de confianza con ssh-keyscan' . ( '' !== $scan ? ' -t ' . $scan : '' ) . ' -p ' . $port . ' ' . (string) ( $this->s['sftp_host'] ?? '' ) . ' | ssh-keygen -l -E sha1 -f - y con el administrador del servidor por un canal independiente; si coincide, pégala (puedes pegar las de todas sus claves). Si no coincide, no la uses: alguien podría estar suplantando al servidor.';
		return $text;
	}

	/* ------------------------------------------------------------------ Conexión */

	/** El cliente incluido vive junto a este archivo: se carga aunque quien use la clase sólo haya cargado ésta. */
	private static function client_class() {
		if ( ! class_exists( 'Digitalisimo_Backups_Sftp_Client', false ) ) require_once __DIR__ . '/class-sftp-client.php';
		return 'Digitalisimo_Backups_Sftp_Client';
	}
	/** Si la extensión ssh2 de PHP está cargada. */
	private static function has_ssh2() { return function_exists( 'ssh2_connect' ) && function_exists( 'ssh2_fingerprint' ); }
	/**
	 * Motor de esta conexión: `ssh2` o `phpseclib` (el cliente incluido). Automático: la extensión sólo si está y puede
	 * con esta conexión (clave privada con su clave pública; alguna huella SHA-1 o una extensión que calcule SHA-256).
	 */
	public function engine() {
		$choice = (string) ( $this->s['sftp_engine'] ?? 'auto' );
		if ( 'ssh2' === $choice || 'phpseclib' === $choice ) return $choice;
		if ( ! self::has_ssh2() ) return 'phpseclib';
		// Con clave privada, la extensión sólo si la autentica en memoria (ssh2_auth_pubkey) o si la clave guardada sigue
		// cifrada con su frase (como antes de esta versión): nunca se escribe una clave sin cifrar en el disco (revisión
		// H6). Un .ppk de PuTTY nunca va a libssh2 (H8).
		if ( 'key' === ( $this->s['sftp_auth'] ?? 'password' ) && ( empty( $this->s['sftp_public_key'] ) || self::putty( (string) ( $this->s['sftp_private_key'] ?? '' ) ) || ! ( function_exists( 'ssh2_auth_pubkey' ) || $this->key_file_safe() ) ) ) return 'phpseclib';
		$expected = self::fingerprints( (string) ( $this->s['sftp_fingerprint'] ?? '' ) );
		$sha1 = array_filter( $expected, function( $fingerprint ) { return 0 !== strpos( $fingerprint, 'SHA256:' ); } );
		// Sin huella no se conecta con ninguno: el mensaje de siempre, el de la extensión.
		return ! $expected || $sha1 || defined( 'SSH2_FINGERPRINT_SHA256' ) ? 'ssh2' : 'phpseclib';
	}
	/** Si el texto es un archivo .ppk de PuTTY. */
	private static function putty( $private ) { return 1 === preg_match( '/^PuTTY-User-Key-File-[23]: /', ltrim( (string) $private ) ); }
	/**
	 * Si la clave privada guardada va cifrada con una frase (PEM «ENCRYPTED», PKCS#8 cifrado u OpenSSH con un cifrado
	 * distinto de «none»): sólo entonces puede ir a un archivo para ssh2_auth_pubkey_file(), porque el disco sólo ve el
	 * texto cifrado y la frase va aparte.
	 */
	public static function key_encrypted( $private ) {
		$private = trim( (string) $private );
		if ( false !== strpos( $private, '-----BEGIN ENCRYPTED PRIVATE KEY-----' ) || preg_match( '/^Proc-Type: 4,ENCRYPTED$/m', $private ) ) return true;
		if ( ! preg_match( '/^-----BEGIN OPENSSH PRIVATE KEY-----\s+([A-Za-z0-9+\/=\s]+)-----END OPENSSH PRIVATE KEY-----$/', $private, $match ) ) return false;
		$raw = base64_decode( preg_replace( '/\s+/', '', $match[1] ), true );
		if ( false === $raw || 0 !== strpos( $raw, "openssh-key-v1\0" ) || strlen( $raw ) < 19 ) return false;
		$length = unpack( 'N', substr( $raw, 15, 4 ) )[1];
		$cipher = (string) substr( $raw, 19, min( 64, $length ) );
		return '' !== $cipher && 'none' !== $cipher;
	}
	private function key_file_safe() { return function_exists( 'ssh2_auth_pubkey_file' ) && '' !== (string) ( $this->s['sftp_key_passphrase'] ?? '' ) && self::key_encrypted( (string) ( $this->s['sftp_private_key'] ?? '' ) ); }
	/** Comprobaciones comunes antes de conectar: credencial, servidor, usuario y huellas. Devuelve array( clave?, huellas ). */
	private function precheck( $engine ) {
		$s = $this->s;
		$key = 'key' === ( $s['sftp_auth'] ?? 'password' );
		$secret = $key ? ( $s['sftp_private_key'] ?? '' ) : ( $s['sftp_password'] ?? '' );
		if ( empty( $secret ) && class_exists( 'Digitalisimo_Backups' ) && '' !== ( $problem = Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		if ( 'ssh2' === $engine && ! self::has_ssh2() ) throw new Digitalisimo_Backups_Permanent_Error( 'Esta conexión SFTP usa la extensión ssh2 de PHP y este servidor no la tiene: elige el motor «Automático» o el cliente incluido.' );
		if ( empty( $s['sftp_host'] ) || empty( $s['sftp_user'] ) || empty( $secret ) || ( $key && 'ssh2' === $engine && empty( $s['sftp_public_key'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Completa la conexión SFTP para gestionar este respaldo.' );
		// Una o varias huellas (una por clave del servidor): vale la de la clave que presente. Cada una fija una clave,
		// como varias líneas de known_hosts para el mismo servidor; ninguna se aprende sola al conectar.
		$expected = self::fingerprints( (string) ( $s['sftp_fingerprint'] ?? '' ) );
		if ( ! $expected ) throw new Digitalisimo_Backups_Permanent_Error( 'ssh2' === $engine ? 'Configura y verifica la huella SHA-1 de la clave SSH del servidor antes de usar SFTP.' : 'Configura y verifica la huella de la clave SSH del servidor (SHA-256 o SHA-1) antes de usar SFTP.' );
		return array( $key, $expected );
	}
	/** Cliente incluido ya autenticado (la huella se comprueba antes de enviar ninguna credencial). */
	private function client() {
		if ( $this->client ) return $this->client;
		list( $key, $expected ) = $this->precheck( 'phpseclib' );
		$s = $this->s;
		$auth = $key ? array( 'key' => (string) $s['sftp_private_key'], 'passphrase' => (string) ( $s['sftp_key_passphrase'] ?? '' ) ) : array( 'password' => (string) $s['sftp_password'] );
		$timeout = (int) ( $this->context['timeout'] ?? 0 ) ?: 20;
		$class = self::client_class();
		$client = new $class();
		$client->connect( (string) $s['sftp_host'], (int) ( $s['sftp_port'] ?? 22 ) ?: 22, (string) $s['sftp_user'], $expected, $auth, $timeout );
		return $this->client = $client;
	}
	/** Cierra el cliente incluido (tras un fallo a mitad de una transferencia su sesión no se reutiliza). */
	private function drop_client() {
		if ( $this->client ) $this->client->close();
		$this->client = null;
	}
	private function lib() { return 'phpseclib' === $this->engine(); }

	public function prepare() { $this->connection(); }
	/** Conexión autenticada (la huella se comprueba antes de enviar ninguna credencial). */
	public function connection() {
		if ( $this->lib() ) return $this->client();
		if ( $this->connection ) return $this->connection;
		$s = $this->s;
		list( $key, $expected ) = $this->precheck( 'ssh2' );
		$can_sha256 = defined( 'SSH2_FINGERPRINT_SHA256' );
		$sha1_expected = array_values( array_filter( $expected, function( $fingerprint ) { return 0 !== strpos( $fingerprint, 'SHA256:' ); } ) );
		if ( ! $sha1_expected && ! $can_sha256 ) throw new Digitalisimo_Backups_Permanent_Error( 'La extensión ssh2 de este servidor no calcula huellas SHA-256: escribe la huella SHA-1 de la clave del servidor (ssh-keyscan -p ' . ( (int) $s['sftp_port'] ?: 22 ) . ' ' . $s['sftp_host'] . ' | ssh-keygen -l -E sha1 -f -).' );
		$connection = @ssh2_connect( $s['sftp_host'], (int) $s['sftp_port'] );
		if ( ! $connection ) throw new Exception( 'No se pudo conectar al servidor SFTP.' );
		$sha1 = @ssh2_fingerprint( $connection, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX );
		$sha1 = is_string( $sha1 ) && preg_match( '/^[a-f0-9]{40}$/i', $sha1 ) ? strtoupper( $sha1 ) : '';
		$sha256 = '';
		if ( $can_sha256 ) {
			$raw = @ssh2_fingerprint( $connection, constant( 'SSH2_FINGERPRINT_SHA256' ) | SSH2_FINGERPRINT_RAW );
			$sha256 = is_string( $raw ) && 32 === strlen( $raw ) ? 'SHA256:' . rtrim( base64_encode( $raw ), '=' ) : '';
		}
		$match = false;
		foreach ( $expected as $fingerprint ) {
			$actual = 0 === strpos( $fingerprint, 'SHA256:' ) ? $sha256 : $sha1;
			if ( '' !== $actual && hash_equals( $fingerprint, $actual ) ) $match = true;
		}
		if ( ! $match ) throw new Digitalisimo_Backups_Permanent_Error( $this->mismatch_text( $connection, $sha1, $sha256 ) );
		if ( ! ( $key ? $this->auth_key( $connection ) : @ssh2_auth_password( $connection, $s['sftp_user'], $s['sftp_password'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo autenticar en SFTP.' );
		return $this->connection = $connection;
	}
	/** Clave privada: en memoria si la extensión lo permite; si no, en archivos 0600 de la carpeta privada que se borran al momento. */
	private function auth_key( $connection ) {
		$s = $this->s;
		$passphrase = (string) ( $s['sftp_key_passphrase'] ?? '' );
		if ( self::putty( (string) $s['sftp_private_key'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La extensión ssh2 no lee claves .ppk de PuTTY: vuelve a guardar la clave (se convierte al guardar) o elige el cliente SFTP incluido.' );
		if ( function_exists( 'ssh2_auth_pubkey' ) ) return @ssh2_auth_pubkey( $connection, $s['sftp_user'], $s['sftp_public_key'], $s['sftp_private_key'], $passphrase );
		if ( ! function_exists( 'ssh2_auth_pubkey_file' ) ) return false;
		// Revisión H6: la clave sólo va a un archivo si sigue cifrada con su frase; sin cifrar, nunca toca el disco.
		if ( ! $this->key_file_safe() ) throw new Digitalisimo_Backups_Permanent_Error( 'La extensión ssh2 de este servidor sólo autentica con la clave privada en un archivo, y la clave guardada no lleva frase: para no escribirla sin cifrar en el disco, elige el motor «Automático» o el cliente SFTP incluido.' );
		$public = $this->temp_file( '.sftp-pub-' );
		$private = $this->temp_file( '.sftp-key-' );
		try {
			file_put_contents( $public, $s['sftp_public_key'] . "\n" );
			file_put_contents( $private, rtrim( $s['sftp_private_key'] ) . "\n" );
			return @ssh2_auth_pubkey_file( $connection, $s['sftp_user'], $public, $private, $passphrase );
		} finally {
			@unlink( $public );
			if ( is_file( $private ) ) { @file_put_contents( $private, str_repeat( "\0", strlen( (string) $s['sftp_private_key'] ) ) ); @unlink( $private ); }
		}
	}
	private function session() {
		if ( $this->sftp ) return $this->sftp;
		$connection = $this->connection(); // Antes de llamar a la extensión: sin ella, precheck() da el mensaje claro.
		$sftp = @ssh2_sftp( $connection );
		if ( ! $sftp ) throw new Exception( 'No se pudo iniciar SFTP en el servidor remoto.' );
		return $this->sftp = $sftp;
	}
	private function url( $path ) { return 'ssh2.sftp://' . intval( $this->session() ) . $path; }
	private function directory() {
		$directory = rtrim( (string) $this->s['sftp_path'], '/' );
		if ( '' === $directory ) $directory = '/';
		if ( '/' !== substr( $directory, 0, 1 ) ) throw new Exception( 'La carpeta SFTP debe ser una ruta absoluta.' );
		return $directory;
	}

	/* ------------------------------------------------------------------ Contrato */

	protected function upload( $file, $name, array $source ) {
		if ( $this->lib() ) return $this->lib_upload( $file, $name, $source );
		$sftp = $this->session();
		$remote = rtrim( $this->directory(), '/' ) . '/' . $name;
		$temporary = self::temporary_name( $remote );
		$this->save_state( array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => array( 'provider' => self::TYPE, 'path' => $temporary ) ) );
		$handle = @fopen( $file, 'rb' );
		$target = @fopen( $this->url( $temporary ), 'wb' );
		if ( ! $handle || ! $target ) {
			if ( $handle ) fclose( $handle );
			if ( $target ) fclose( $target );
			throw new Exception( 'No se pudo abrir el archivo local o el destino SFTP.' );
		}
		$copied = false;
		$flushed = false;
		try {
			$copied = self::copy_blocks( $handle, $target, function( $done ) use ( $source ) { $this->progress( $done, $source['size'] ); } );
			$flushed = false !== $copied && @fflush( $target );
		} catch ( Throwable $e ) {
			// Cancelado entre bloques (o un fallo del avance): el temporal no queda a medias en el servidor.
			fclose( $handle );
			fclose( $target );
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw $e;
		}
		fclose( $handle );
		$closed = fclose( $target );
		if ( false === $copied || $copied !== $source['size'] || ! $flushed || ! $closed ) {
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		}
		return array( 'provider' => self::TYPE, 'path' => $temporary );
	}
	/**
	 * Copia $in en $out (o sólo lo suma a $hash, con $out null) por bloques de BLOCK bytes y devuelve los bytes leídos, o
	 * false si una lectura o una escritura falla. Una escritura corta se completa; $tick( $hechos ) corre tras cada bloque
	 * (latido y cancelación del trabajo).
	 */
	private static function copy_blocks( $in, $out, $tick = null, $hash = null ) {
		$done = 0;
		$idle = 0;
		while ( ! feof( $in ) ) {
			$block = fread( $in, self::BLOCK );
			if ( false === $block ) return false;
			if ( '' === $block ) {
				if ( feof( $in ) ) break;
				if ( ++$idle > 50 ) return false;
				continue;
			}
			$idle = 0;
			$length = strlen( $block );
			if ( null !== $hash ) hash_update( $hash, $block );
			for ( $offset = 0; null !== $out && $offset < $length; $offset += $written ) {
				$written = @fwrite( $out, 0 === $offset ? $block : substr( $block, $offset ) );
				if ( false === $written || 0 === $written ) return false;
			}
			$done += $length;
			unset( $block );
			if ( is_callable( $tick ) ) call_user_func( $tick, $done );
		}
		return $done;
	}
	/** Cliente incluido: sube por paquetes SFTP con avance (y cancelación) cada MiB; un fallo a mitad retira el temporal. */
	private function lib_upload( $file, $name, array $source ) {
		$client = $this->client();
		$remote = rtrim( $this->directory(), '/' ) . '/' . $name;
		$temporary = self::temporary_name( $remote );
		$this->save_state( array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => array( 'provider' => self::TYPE, 'path' => $temporary ) ) );
		$last = 0;
		try {
			$sent = $client->upload( $file, $temporary, function( $done ) use ( &$last, $source ) {
				if ( $done - $last < self::BLOCK && $done < $source['size'] ) return;
				$last = $done;
				$this->progress( $done, $source['size'] );
			} );
		} catch ( Throwable $e ) {
			// Cancelado entre paquetes o la sesión se cortó: con otra sesión, el temporal no queda a medias en el servidor.
			$this->drop_client();
			try { $this->client()->delete( $temporary ); } catch ( Throwable $ignored ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			throw $e;
		}
		if ( $sent !== $source['size'] ) {
			$client->delete( $temporary );
			throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		}
		return array( 'provider' => self::TYPE, 'path' => $temporary );
	}
	/** Lee la copia remota completa por bloques y compara su tamaño y su SHA-256. */
	public function verify( array $remote, $file, array $source ) {
		if ( $this->lib() ) {
			list( $read, $hash ) = $this->client()->hash( $this->own( $remote ) );
			if ( $read !== $source['size'] || ! hash_equals( $source['sha256'], $hash ) ) throw new Exception( 'La copia SFTP no coincide con el ZIP local.' );
			return;
		}
		$handle = @fopen( $this->url( (string) $remote['path'] ), 'rb' );
		$hash = false;
		$read = 0;
		if ( $handle ) {
			@stream_set_chunk_size( $handle, self::BLOCK );
			$context = hash_init( 'sha256' );
			$read = self::copy_blocks( $handle, null, null, $context );
			fclose( $handle );
			$hash = false === $read ? false : hash_final( $context );
		}
		if ( false === $hash || $read !== $source['size'] || ! hash_equals( $source['sha256'], $hash ) ) throw new Exception( 'La copia SFTP no coincide con el ZIP local.' );
	}
	protected function publish( array $temp, $name ) {
		$final = rtrim( $this->directory(), '/' ) . '/' . $name;
		if ( $this->lib() ) {
			$client = $this->client();
			if ( ! $client->rename( $temp['path'], $final ) ) {
				$client->delete( $temp['path'] );
				throw new Exception( 'No se pudo publicar el respaldo verificado en SFTP.' );
			}
			return array( 'provider' => self::TYPE, 'path' => $final );
		}
		$sftp = $this->session();
		if ( ! @ssh2_sftp_rename( $sftp, $temp['path'], $final ) ) {
			@ssh2_sftp_unlink( $sftp, $temp['path'] );
			throw new Exception( 'No se pudo publicar el respaldo verificado en SFTP.' );
		}
		return array( 'provider' => self::TYPE, 'path' => $final );
	}
	private function own( array $remote ) { return self::inside( (string) ( $remote['path'] ?? '' ), rtrim( $this->directory(), '/' ), self::label() ); }
	/** Descarga por bloques a $target y comprueba que llegó el tamaño que informa el servidor. */
	public function get( array $remote, $target ) {
		$path = $this->own( $remote );
		$expected = $this->size( $remote );
		if ( $this->lib() ) {
			$last = 0;
			$this->client()->download( $path, $target, function( $done ) use ( &$last, $expected ) {
				if ( $expected <= 0 || $done - $last < self::BLOCK ) return;
				$last = $done;
				$this->progress( min( $done, $expected ), $expected, 'Descargados' );
			} );
			clearstatcache( true, $target );
			$copied = (int) @filesize( $target );
			if ( false !== $expected && $copied !== $expected ) throw new Exception( 'La descarga SFTP quedó incompleta (' . $copied . ' de ' . $expected . ' bytes).' );
			return;
		}
		$source = @fopen( $this->url( $path ), 'rb' );
		if ( ! $source ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
		@stream_set_chunk_size( $source, self::BLOCK );
		$out = @fopen( $target, 'wb' );
		if ( ! $out ) { fclose( $source ); throw new Exception( 'No se pudo preparar el archivo temporal de la descarga.' ); }
		try {
			$copied = self::copy_blocks( $source, $out, function( $done ) use ( $expected ) { if ( $expected > 0 ) $this->progress( min( $done, $expected ), $expected, 'Descargados' ); } );
			$flushed = false !== $copied && @fflush( $out );
		} finally {
			fclose( $source );
			fclose( $out );
		}
		if ( false === $copied || ! $flushed ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
		if ( false !== $expected && $copied !== $expected ) throw new Exception( 'La descarga SFTP quedó incompleta (' . $copied . ' de ' . $expected . ' bytes).' );
	}
	public function size( array $remote ) {
		$path = $this->own( $remote );
		if ( $this->lib() ) return $this->client()->size( $path );
		$stat = function_exists( 'ssh2_sftp_stat' ) ? @ssh2_sftp_stat( $this->session(), $path ) : @stat( $this->url( $path ) );
		return is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : false;
	}
	public function delete( array $remote ) {
		$path = $this->own( $remote );
		if ( $this->lib() ) {
			if ( $this->client()->delete( $path ) || false === $this->client()->size( $path ) ) return;
			throw new Exception( 'No se pudo eliminar el archivo SFTP.' );
		}
		$sftp = $this->session();
		if ( @ssh2_sftp_unlink( $sftp, $path ) ) return;
		if ( false === $this->size( $remote ) ) return;
		throw new Exception( 'No se pudo eliminar el archivo SFTP.' );
	}
	public function list() {
		$directory = $this->directory();
		if ( $this->lib() ) {
			$files = $this->client()->files( $directory );
			if ( false === $files ) throw new Exception( 'No se pudo listar la carpeta SFTP.' );
			$out = array();
			foreach ( $files as $entry => $size ) $out[] = array( 'name' => $entry, 'size' => $size, 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'path' => rtrim( $directory, '/' ) . '/' . $entry ) );
			return $out;
		}
		$handle = @opendir( $this->url( $directory ) );
		if ( ! $handle ) throw new Exception( 'No se pudo listar la carpeta SFTP.' );
		$out = array();
		try {
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( '.' === $entry || '..' === $entry ) continue;
				$path = rtrim( $directory, '/' ) . '/' . $entry;
				$size = $this->size( array( 'path' => $path ) );
				$out[] = array( 'name' => $entry, 'size' => false === $size ? 0 : $size, 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'path' => $path ) );
			}
		} finally {
			closedir( $handle );
		}
		return $out;
	}
}
