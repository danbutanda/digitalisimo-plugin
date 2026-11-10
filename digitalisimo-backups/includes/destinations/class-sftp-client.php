<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente SFTP en PHP puro (I5): phpseclib 3 incluido con prefijo propio (`DigitalisimoBackups\Vendor\phpseclib3`, ver
 * scripts/vendor-phpseclib.sh), para los hostings sin la extensión ssh2. Usa OpenSSL y libsodium de PHP si están (AES,
 * ChaCha20, Curve25519, Ed25519); sin ellas, sus versiones en PHP, más lentas.
 *
 * - La clave del servidor se comprueba antes de enviar ninguna credencial: getServerPublicHostKey() valida la firma del
 *   intercambio con esa clave y aquí se compara su huella (SHA-256 de OpenSSH o SHA-1) con las configuradas. Si la clave
 *   que eligió la negociación no es una de ellas, se prueba con los demás tipos de clave del servidor (ED25519, ECDSA,
 *   RSA, DSA), cada uno en una conexión nueva: vale la de cualquier clave fijada, como varias líneas de known_hosts.
 * - Clave privada OpenSSH, PKCS#1, PKCS#8 o PuTTY, también cifrada con su frase; no hace falta la clave pública.
 * - Las transferencias van por paquetes SFTP (memoria acotada) con un aviso por paquete para el avance y la cancelación.
 */
final class Digitalisimo_Backups_Sftp_Client {
	const NS = 'DigitalisimoBackups\\Vendor\\phpseclib3\\';
	/** Familias de tipos de clave del servidor que se prueban si la negociada no está fijada. */
	const HOSTKEY_FAMILIES = array(
		'ED25519' => array( 'ssh-ed25519' ),
		'ECDSA' => array( 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521' ),
		'RSA' => array( 'rsa-sha2-512', 'rsa-sha2-256', 'ssh-rsa' ),
		'DSA' => array( 'ssh-dss' ),
	);
	/** Bloque de cada lectura para comparar la copia remota (memoria acotada). */
	const BLOCK = 1048576;

	private $sftp = null;
	/** Claves que presentó el servidor: tipo => array( sha1, sha256 ). */
	private $presented = array();
	/** Huellas de la clave de la última conexión abierta. */
	private $last = array();

	/** Si el cliente incluido puede funcionar en este PHP. */
	public static function available() {
		// phpseclib no admite mbstring.func_overload (PHP < 8), que cambia strlen() y substr() en los paquetes.
		if ( PHP_VERSION_ID < 80000 && extension_loaded( 'mbstring' ) && ( (int) ini_get( 'mbstring.func_overload' ) & 2 ) ) return false;
		return is_file( dirname( __DIR__ ) . '/lib/phpseclib3/Net/SFTP.php' );
	}
	public static function load() {
		if ( ! self::available() ) throw new Digitalisimo_Backups_Permanent_Error( 'El cliente SFTP incluido no puede funcionar en este PHP (mbstring.func_overload activo) y la extensión ssh2 no está: actívala o desactiva mbstring.func_overload.' );
		require_once dirname( __DIR__ ) . '/lib/autoload.php';
	}

	/** Huellas de un blob de clave pública SSH: array( tipo, SHA-1 en 40 hexadecimales mayúsculas, «SHA256:…» ). */
	public static function key_fingerprints( $blob ) {
		$type = strlen( $blob ) > 4 ? (string) substr( $blob, 4, (int) unpack( 'N', substr( $blob, 0, 4 ) )[1] ) : '';
		return array( self::type_label( $type ), strtoupper( sha1( $blob ) ), 'SHA256:' . rtrim( base64_encode( hash( 'sha256', $blob, true ) ), '=' ) );
	}
	private static function type_label( $type ) {
		if ( 'ssh-ed25519' === $type ) return 'ED25519';
		if ( 0 === strpos( $type, 'ecdsa-sha2-' ) ) return 'ECDSA';
		if ( 'ssh-rsa' === $type ) return 'RSA';
		if ( 'ssh-dss' === $type ) return 'DSA';
		return '' !== $type ? preg_replace( '/[^a-z0-9@.-]/', '', $type ) : 'desconocido';
	}
	/**
	 * La clave privada sin su frase, en el formato que leen OpenSSH, libssh2 y phpseclib (PKCS#1 para RSA, ECDSA y DSA;
	 * OpenSSH para Ed25519), y su clave pública. Abrir una clave con frase en PHP puro cuesta segundos (bcrypt_pbkdf de
	 * OpenSSH): se hace una vez al guardar y la clave queda sólo en el almacén cifrado de credenciales, que ya guardaba
	 * la frase junto a ella (quien abriera el almacén tendría las dos). Devuelve array( privada, pública ).
	 */
	public static function unlock( $private, $passphrase ) {
		self::load();
		$key = self::private_key( $private, $passphrase );
		$ed25519 = false !== stripos( (string) $key->getPublicKey()->toString( 'OpenSSH' ), 'ssh-ed25519' );
		$plain = (string) $key->withPassword( false )->toString( $ed25519 ? 'OpenSSH' : 'PKCS1' );
		return array( trim( str_replace( array( "\r\n", "\r" ), "\n", $plain ) ), trim( (string) $key->getPublicKey()->toString( 'OpenSSH', array( 'comment' => 'digitalisimo-backups' ) ) ) );
	}
	/** Carga la clave privada; la frase equivocada o un formato desconocido dan un mensaje sin el contenido de la clave. */
	private static function private_key( $private, $passphrase ) {
		$loader = self::NS . 'Crypt\\PublicKeyLoader';
		try {
			$key = $loader::loadPrivateKey( (string) $private, '' !== (string) $passphrase ? (string) $passphrase : false );
		} catch ( Throwable $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( '' !== (string) $passphrase ? 'La clave privada SFTP no se pudo abrir: revisa que esté completa y que la frase sea la suya.' : 'La clave privada SFTP no se pudo abrir: revisa que esté completa; si está protegida con una frase, escríbela.' );
		}
		return $key;
	}

	/**
	 * Conecta, comprueba la huella de la clave del servidor y se autentica. $expected: huellas normalizadas
	 * (Digitalisimo_Backups_Destination_Sftp::fingerprints()). $auth: array( 'password' => … ) o array( 'key' => …,
	 * 'passphrase' => … ). Lanza Digitalisimo_Backups_Permanent_Error si la huella no coincide (con las claves que
	 * presentó el servidor) o la autenticación falla.
	 */
	public function connect( $host, $port, $user, array $expected, array $auth, $timeout = 20 ) {
		self::load();
		$this->presented = $this->last = array();
		$tried = array();
		$sftp = $this->open( $host, $port, $timeout, null, $tried );
		if ( ! $this->matches( $expected ) ) {
			$sftp = null;
			// Cada uno de los demás tipos de clave en una conexión nueva, restringida a ese tipo.
			foreach ( self::HOSTKEY_FAMILIES as $label => $algorithms ) {
				if ( isset( $tried[ $label ] ) ) continue;
				try {
					$candidate = $this->open( $host, $port, $timeout, $algorithms, $tried );
				} catch ( Digitalisimo_Backups_Sftp_No_Hostkey $e ) {
					continue; // El servidor no tiene una clave de ese tipo.
				}
				if ( $this->matches( $expected ) ) { $sftp = $candidate; break; }
				$candidate->disconnect();
			}
			if ( ! $sftp ) throw new Digitalisimo_Backups_Permanent_Error( $this->mismatch_text( $host, $port ) );
		}
		$credential = isset( $auth['key'] ) ? self::private_key( $auth['key'], (string) ( $auth['passphrase'] ?? '' ) ) : (string) ( $auth['password'] ?? '' );
		$logged = false;
		try {
			$logged = $sftp->login( (string) $user, $credential );
		} catch ( Throwable $e ) {
			$logged = false;
		}
		if ( ! $logged ) {
			$sftp->disconnect();
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo autenticar en SFTP.' );
		}
		$sftp->disableStatCache();
		$this->sftp = $sftp;
		return $this;
	}
	/** Una conexión hasta comprobar la firma de la clave del servidor; anota la clave que presentó. */
	private function open( $host, $port, $timeout, $algorithms, array &$tried ) {
		$class = self::NS . 'Net\\SFTP';
		$sftp = new $class( (string) $host, (int) $port, (int) $timeout );
		if ( $algorithms ) $sftp->setPreferredAlgorithms( array( 'hostkey' => $algorithms ) );
		try {
			$key = $sftp->getServerPublicHostKey();
		} catch ( Throwable $e ) {
			if ( $algorithms && false !== stripos( $e->getMessage(), 'host key' ) ) throw new Digitalisimo_Backups_Sftp_No_Hostkey( $e->getMessage() );
			throw new Exception( 'No se pudo conectar al servidor SFTP: ' . self::reason( $e ) );
		}
		if ( ! is_string( $key ) || false === strpos( $key, ' ' ) ) throw new Exception( 'No se pudo conectar al servidor SFTP: su clave no superó la comprobación de la firma.' );
		$blob = base64_decode( substr( $key, strpos( $key, ' ' ) + 1 ), true );
		if ( false === $blob || '' === $blob ) throw new Exception( 'No se pudo conectar al servidor SFTP: clave del servidor ilegible.' );
		list( $type, $sha1, $sha256 ) = self::key_fingerprints( $blob );
		$this->presented[ $type ] = $this->last = array( $sha1, $sha256 );
		foreach ( self::HOSTKEY_FAMILIES as $label => $members ) if ( $label === $type ) $tried[ $label ] = true;
		return $sftp;
	}
	/** Mensaje de una excepción de phpseclib sin datos de la sesión (nunca lleva credenciales: aún no se enviaron). */
	private static function reason( Throwable $e ) {
		$message = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $e->getMessage() );
		return '' !== trim( $message ) ? trim( substr( $message, 0, 200 ) ) : get_class( $e );
	}
	/** Si la última clave presentada es una de las fijadas. */
	private function matches( array $expected ) {
		$last = $this->last;
		if ( ! $last ) return false;
		foreach ( $expected as $fingerprint ) {
			$actual = 0 === strpos( $fingerprint, 'SHA256:' ) ? $last[1] : $last[0];
			if ( hash_equals( $fingerprint, $actual ) ) return true;
		}
		return false;
	}
	/** Ninguna clave del servidor coincide: cuáles presentó, con sus huellas, y cómo comprobarlas. */
	private function mismatch_text( $host, $port ) {
		$keys = array();
		foreach ( $this->presented as $type => $pair ) $keys[] = $type . ' ' . $pair[1] . ' (SHA-1: ' . implode( ':', str_split( strtolower( $pair[0] ), 2 ) ) . ')';
		return 'La huella de la clave SSH del servidor SFTP no coincide con la configurada. El servidor presentó ' . ( $keys ? implode( '; ', $keys ) : 'una clave de tipo desconocido' ) . ' (cliente SFTP incluido). Compruébalas en un equipo de confianza con ssh-keyscan -p ' . (int) $port . ' ' . $host . ' | ssh-keygen -l -E sha256 -f - y con el administrador del servidor por un canal independiente; si coinciden, pega la huella. Si no coincide, no la uses: alguien podría estar suplantando al servidor.';
	}
	/** Claves que presentó el servidor en la última conexión (tipo => array( SHA-1, SHA-256 )). */
	public function presented() { return $this->presented; }

	/* ------------------------------------------------------------------ Operaciones */

	private function sftp() {
		if ( ! $this->sftp ) throw new Exception( 'La conexión SFTP no está abierta.' );
		return $this->sftp;
	}
	/** Sube $file a $remote por paquetes; $tick( $bytes ) tras cada uno (puede lanzar para cancelar). Devuelve los bytes enviados. */
	public function upload( $file, $remote, $tick = null ) {
		$sent = 0;
		$class = self::NS . 'Net\\SFTP';
		$ok = $this->sftp()->put( (string) $remote, (string) $file, $class::SOURCE_LOCAL_FILE, -1, -1, function( $bytes ) use ( &$sent, $tick ) {
			$sent = (int) $bytes;
			if ( is_callable( $tick ) ) call_user_func( $tick, $sent );
		} );
		if ( ! $ok ) throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		return $sent;
	}
	/** Descarga $remote a $target por paquetes (memoria acotada). */
	public function download( $remote, $target, $tick = null ) {
		$ok = $this->sftp()->get( (string) $remote, (string) $target, 0, -1, is_callable( $tick ) ? function( $bytes ) use ( $tick ) { call_user_func( $tick, (int) $bytes ); } : null );
		if ( false === $ok ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
	}
	/** Lee la copia remota por bloques de BLOCK bytes y devuelve array( bytes, SHA-256 ). */
	public function hash( $remote ) {
		$context = hash_init( 'sha256' );
		$offset = 0;
		do {
			$block = $this->sftp()->get( (string) $remote, false, $offset, self::BLOCK );
			if ( false === $block ) throw new Exception( 'No se pudo leer la copia SFTP para compararla.' );
			hash_update( $context, $block );
			$length = strlen( $block );
			$offset += $length;
		} while ( self::BLOCK === $length );
		return array( $offset, hash_final( $context ) );
	}
	public function size( $remote ) {
		$stat = $this->sftp()->stat( (string) $remote );
		return is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : false;
	}
	public function rename( $from, $to ) { return (bool) $this->sftp()->rename( (string) $from, (string) $to ); }
	public function delete( $remote ) { return (bool) $this->sftp()->delete( (string) $remote, false ); }
	/** Archivos normales de una carpeta: nombre => tamaño. false si no se puede listar. */
	public function files( $directory ) {
		$list = $this->sftp()->rawlist( (string) $directory );
		if ( ! is_array( $list ) ) return false;
		$out = array();
		foreach ( $list as $name => $attributes ) {
			if ( '.' === $name || '..' === $name || ! is_array( $attributes ) ) continue;
			if ( isset( $attributes['type'] ) && 1 !== (int) $attributes['type'] ) continue; // Sólo archivos normales.
			$out[ (string) $name ] = (int) ( $attributes['size'] ?? 0 );
		}
		return $out;
	}
	public function close() {
		if ( $this->sftp ) { try { $this->sftp->disconnect(); } catch ( Throwable $e ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$this->sftp = null;
	}
}

/** El servidor no tiene una clave del tipo pedido (se prueba el siguiente). */
final class Digitalisimo_Backups_Sftp_No_Hostkey extends Exception {}
