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
 * - Algoritmos (revisión H1 y P3): sólo cifrados autenticados o con MAC moderno (AES-GCM, ChaCha20-Poly1305 y
 *   aes128-ctr), intercambios y firmas sin los heredados (nada de ssh-dss, diffie-hellman-group1-sha1, hmac-md5 ni
 *   arcfour). phpseclib reconecta solo y repite el login cuando el primer paquete cifrado con arcfour256, aes192-ctr o
 *   aes256-ctr trae un largo inválido («bad key size fix»), y esa conexión nueva no comprobaba la clave del servidor: un
 *   atacante en medio que volteaba un bit recibía la contraseña o el respaldo. Ahora esos cifrados no se negocian, la
 *   reconexión automática queda desactivada antes del login y la clave del servidor se vuelve a comparar con la fijada
 *   tras el login, antes de cada operación y cada MiB transferido (también tras un nuevo intercambio de claves, cuya
 *   firma se valida otra vez); si cambió, se corta sin enviar nada más.
 */
final class Digitalisimo_Backups_Sftp_Client {
	const NS = 'DigitalisimoBackups\\Vendor\\phpseclib3\\';
	/** Familias de tipos de clave del servidor que se prueban si la negociada no está fijada. */
	const HOSTKEY_FAMILIES = array(
		'ED25519' => array( 'ssh-ed25519' ),
		'ECDSA' => array( 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521' ),
		'RSA' => array( 'rsa-sha2-512', 'rsa-sha2-256', 'ssh-rsa' ),
	);
	/** Cifrados que se negocian: ninguno de los que activan la reconexión automática de phpseclib (ver la cabecera). */
	const CIPHERS = array( 'aes128-gcm@openssh.com', 'aes256-gcm@openssh.com', 'chacha20-poly1305@openssh.com', 'aes128-ctr' );
	/** Intercambios de claves sin diffie-hellman-group1-sha1 (P3). */
	const KEX = array( 'curve25519-sha256', 'curve25519-sha256@libssh.org', 'ecdh-sha2-nistp256', 'ecdh-sha2-nistp384', 'ecdh-sha2-nistp521', 'diffie-hellman-group-exchange-sha256', 'diffie-hellman-group14-sha256', 'diffie-hellman-group16-sha512', 'diffie-hellman-group18-sha512', 'diffie-hellman-group14-sha1' );
	/** MAC (sólo cuentan con aes128-ctr) sin hmac-md5 ni las versiones de 96 bits. */
	const MACS = array( 'hmac-sha2-256-etm@openssh.com', 'hmac-sha2-512-etm@openssh.com', 'hmac-sha2-256', 'hmac-sha2-512', 'hmac-sha1-etm@openssh.com', 'hmac-sha1' );
	/** Sin OpenSSL, phpseclib cifra en PHP puro (unos 0,1 MiB/s): más allá de esto no se sube un respaldo (P2). */
	const SLOW_LIMIT = 8388608;
	/** @internal Sólo para las pruebas: fuerza o niega OpenSSL (null = lo que tenga este PHP). */
	public static $openssl = null;
	/** Bloque de cada lectura para comparar la copia remota (memoria acotada). */
	const BLOCK = 1048576;

	private $sftp = null;
	/** Blob de la clave del servidor comprobada al conectar y el hash del intercambio cuya firma se validó con ella. */
	private $pinned = '';
	private $exchange = '';
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
	/** Si este PHP cifra deprisa (OpenSSL): sin él, el cliente incluido sólo sirve para operaciones pequeñas. */
	public static function fast() { return null !== self::$openssl ? (bool) self::$openssl : extension_loaded( 'openssl' ); }
	/** Aviso para la pantalla cuando el cliente incluido sería lento ('' si no). */
	public static function slow_text() {
		return self::fast() ? '' : 'Este PHP no tiene la extensión OpenSSL: el cliente SFTP incluido cifraría en PHP puro, a unos 0,1 MiB/s, y no se usa para respaldos de más de ' . (int) ( self::SLOW_LIMIT / 1048576 ) . ' MiB. Activa OpenSSL en el hosting o usa la extensión ssh2.';
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
		// Sin reconexión automática: phpseclib nunca repite el login en otra conexión cuya clave no se comprobó.
		self::internals( $sftp, function() { $this->bad_key_size_fix = true; } );
		$this->sftp = $sftp;
		$this->pinned = $this->blob( $sftp );
		$this->exchange = self::internals( $sftp, function() { return (string) $this->exchange_hash; } );
		$logged = false;
		try {
			$logged = $sftp->login( (string) $user, $credential );
		} catch ( Throwable $e ) {
			$logged = false;
		}
		if ( ! $logged ) {
			$this->close();
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo autenticar en SFTP.' );
		}
		$this->guard();
		$sftp->disableStatCache();
		return $this;
	}
	/** Ejecuta $code dentro de la clase SSH2 de phpseclib (propiedades privadas: reconexión, clave del servidor). */
	private static function internals( $sftp, Closure $code ) {
		return Closure::bind( $code, $sftp, self::NS . 'Net\SSH2' )();
	}
	/** Blob de la clave del servidor de la sesión (los cierres se crean en métodos de instancia para poder enlazarlos). */
	private function blob( $sftp ) { return (string) self::internals( $sftp, function() { return (string) $this->server_public_host_key; } ); }
	/**
	 * La sesión sigue con la clave del servidor que se comprobó: si cambió (otra conexión, otro intercambio), se corta
	 * sin enviar nada más. Un intercambio de claves nuevo con la misma clave vuelve a validar su firma.
	 */
	public function guard() {
		$sftp = $this->sftp();
		$blob = $this->blob( $sftp );
		$exchange = (string) self::internals( $sftp, function() { return (string) $this->exchange_hash; } );
		$ok = '' !== $this->pinned && hash_equals( $this->pinned, $blob );
		if ( $ok && ! hash_equals( $this->exchange, $exchange ) ) {
			self::internals( $sftp, function() { $this->signature_validated = false; } );
			try {
				$ok = is_string( $sftp->getServerPublicHostKey() ) && hash_equals( $this->pinned, $this->blob( $sftp ) );
			} catch ( Throwable $e ) {
				$ok = false;
			}
			if ( $ok ) $this->exchange = $exchange;
		}
		if ( ! $ok ) {
			$this->close();
			throw new Digitalisimo_Backups_Permanent_Error( 'La clave SSH del servidor SFTP cambió durante la sesión: se cortó la conexión sin enviar nada más. Si el servidor cambió su clave, compruébala y pega la huella nueva; si no, alguien podría estar suplantándolo.' );
		}
	}
	/** Una conexión hasta comprobar la firma de la clave del servidor; anota la clave que presentó. */
	private function open( $host, $port, $timeout, $algorithms, array &$tried ) {
		$class = self::NS . 'Net\\SFTP';
		$sftp = new $class( (string) $host, (int) $port, (int) $timeout );
		$hostkeys = $algorithms ? $algorithms : array_merge( ...array_values( self::HOSTKEY_FAMILIES ) );
		$both = array( 'crypt' => self::CIPHERS, 'mac' => self::MACS, 'comp' => array( 'none' ) );
		$sftp->setPreferredAlgorithms( array( 'kex' => self::KEX, 'hostkey' => $hostkeys, 'client_to_server' => $both, 'server_to_client' => $both ) );
		try {
			$key = $sftp->getServerPublicHostKey();
		} catch ( Throwable $e ) {
			$message = (string) $e->getMessage();
			if ( $algorithms && false !== stripos( $message, 'host key' ) ) throw new Digitalisimo_Backups_Sftp_No_Hostkey( $message );
			if ( preg_match( '/No compatible .*(encryption|key exchange|message authentication|server host key)/i', $message ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor SFTP sólo ofrece algoritmos que el cliente incluido no usa por seguridad (cifrados AES-GCM, ChaCha20-Poly1305 o aes128-ctr; intercambios y firmas sin los heredados): ' . self::reason( $e ) . '. Habilítalos en el servidor o usa la extensión ssh2 de PHP.' );
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
	/** La sesión comprobada (guard()) para una operación. */
	private function checked() {
		$this->guard();
		return $this->sftp;
	}
	/** Sube $file a $remote por paquetes; $tick( $bytes ) tras cada uno (puede lanzar para cancelar). Devuelve los bytes enviados. */
	public function upload( $file, $remote, $tick = null ) {
		if ( ! self::fast() && (int) @filesize( $file ) > self::SLOW_LIMIT ) throw new Digitalisimo_Backups_Permanent_Error( self::slow_text() );
		$sent = 0;
		$last = 0;
		$class = self::NS . 'Net\\SFTP';
		$ok = $this->checked()->put( (string) $remote, (string) $file, $class::SOURCE_LOCAL_FILE, -1, -1, function( $bytes ) use ( &$sent, &$last, $tick ) {
			$sent = (int) $bytes;
			// Cada MiB: la clave del servidor sigue siendo la comprobada (también tras un intercambio de claves nuevo).
			if ( $sent - $last >= self::BLOCK ) { $last = $sent; $this->guard(); }
			if ( is_callable( $tick ) ) call_user_func( $tick, $sent );
		} );
		if ( ! $ok ) throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		return $sent;
	}
	/**
	 * Tramo de una subida reanudable (H20): escribe $file en $remote desde $offset (sin truncar) hasta $deadline o el
	 * final, por paquetes SFTP, y devuelve el desplazamiento alcanzado (lo que el servidor ya tiene). Siempre avanza al
	 * menos un bloque. $tick( $bytes ) tras cada MiB.
	 */
	public function upload_from( $file, $remote, $offset, $size, $deadline, $tick = null ) {
		if ( ! self::fast() && (int) $size > self::SLOW_LIMIT ) throw new Digitalisimo_Backups_Permanent_Error( self::slow_text() );
		$handle = @fopen( (string) $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir el ZIP local.' );
		if ( $offset > 0 && 0 !== fseek( $handle, (int) $offset ) ) { fclose( $handle ); throw new Exception( 'No se pudo continuar la lectura del ZIP local.' ); }
		$position = (int) $offset;
		$last = $position;
		$minimum = $position + self::BLOCK;
		$class = self::NS . 'Net\\SFTP';
		try {
			$ok = $this->checked()->put( (string) $remote, function( $length ) use ( $handle, &$position, &$last, $minimum, $size, $deadline, $tick ) {
				if ( $position >= $size || ( $position >= $minimum && microtime( true ) >= $deadline ) ) return null;
				$data = fread( $handle, (int) min( $length, $size - $position ) );
				if ( false === $data || '' === $data ) return null;
				$position += strlen( $data );
				if ( $position - $last >= self::BLOCK || $position >= $size ) {
					$last = $position;
					$this->guard();
					if ( is_callable( $tick ) ) call_user_func( $tick, $position );
				}
				return $data;
			}, $class::SOURCE_CALLBACK, (int) $offset );
		} finally {
			fclose( $handle );
		}
		if ( ! $ok ) throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		return $position;
	}
	/**
	 * Tramo de la verificación: compara la copia remota con el ZIP local bloque a bloque desde $offset hasta $deadline o
	 * el final. Devuelve hasta dónde coinciden; lanza si un bloque difiere (la copia no se publica).
	 */
	public function compare_from( $file, $remote, $offset, $size, $deadline, $tick = null ) {
		$handle = @fopen( (string) $file, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir el ZIP local.' );
		$position = (int) $offset;
		try {
			if ( $position > 0 && 0 !== fseek( $handle, $position ) ) throw new Exception( 'No se pudo continuar la lectura del ZIP local.' );
			$first = true;
			while ( $position < $size && ( $first || microtime( true ) < $deadline ) ) {
				$first = false;
				$length = (int) min( self::BLOCK, $size - $position );
				$local = (string) fread( $handle, $length );
				$copy = $this->checked()->get( (string) $remote, false, $position, $length );
				if ( false === $copy || strlen( $local ) !== $length || ! hash_equals( $local, (string) $copy ) ) throw new Digitalisimo_Backups_Sftp_Mismatch( 'La copia SFTP no coincide con el ZIP local.' );
				$position += $length;
				if ( is_callable( $tick ) ) call_user_func( $tick, $position );
			}
			// Al final, la copia remota no puede tener nada más.
			if ( $position >= $size && '' !== (string) $this->checked()->get( (string) $remote, false, $position, 1 ) ) throw new Digitalisimo_Backups_Sftp_Mismatch( 'La copia SFTP es más larga que el ZIP local.' );
		} finally {
			fclose( $handle );
		}
		return $position;
	}
	/** Descarga $remote a $target por paquetes (memoria acotada). */
	public function download( $remote, $target, $tick = null ) {
		if ( ! self::fast() && (int) $this->size( $remote ) > self::SLOW_LIMIT ) throw new Digitalisimo_Backups_Permanent_Error( self::slow_text() );
		$last = 0;
		$ok = $this->checked()->get( (string) $remote, (string) $target, 0, -1, function( $bytes ) use ( &$last, $tick ) {
			if ( (int) $bytes - $last >= self::BLOCK ) { $last = (int) $bytes; $this->guard(); }
			if ( is_callable( $tick ) ) call_user_func( $tick, (int) $bytes );
		} );
		if ( false === $ok ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
	}
	/** Lee la copia remota por bloques de BLOCK bytes y devuelve array( bytes, SHA-256 ). */
	public function hash( $remote ) {
		if ( ! self::fast() && (int) $this->size( $remote ) > self::SLOW_LIMIT ) throw new Digitalisimo_Backups_Permanent_Error( self::slow_text() );
		$context = hash_init( 'sha256' );
		$offset = 0;
		do {
			$block = $this->checked()->get( (string) $remote, false, $offset, self::BLOCK );
			if ( false === $block ) throw new Exception( 'No se pudo leer la copia SFTP para compararla.' );
			hash_update( $context, $block );
			$length = strlen( $block );
			$offset += $length;
		} while ( self::BLOCK === $length );
		return array( $offset, hash_final( $context ) );
	}
	public function size( $remote ) {
		$stat = $this->checked()->stat( (string) $remote );
		return is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : false;
	}
	public function rename( $from, $to ) { return (bool) $this->checked()->rename( (string) $from, (string) $to ); }
	/**
	 * Borra; true si se borró o ya no existe. Sólo «no existe» (SSH_FX_NO_SUCH_FILE) cuenta como borrado: un permiso
	 * denegado o un fallo genérico lanzan, aunque stat() tampoco pueda ver el archivo (revisión P1).
	 */
	public function delete( $remote ) {
		$sftp = $this->checked();
		if ( $sftp->delete( (string) $remote, false ) ) return true;
		$error = (string) $sftp->getLastSFTPError();
		if ( false !== strpos( $error, 'NO_SUCH_FILE' ) ) return true;
		if ( false !== $this->size( $remote ) ) return false;
		$error = (string) $sftp->getLastSFTPError();
		if ( false !== strpos( $error, 'NO_SUCH_FILE' ) ) return true;
		throw new Exception( 'No se pudo eliminar el archivo SFTP' . ( '' !== $error ? ' (' . preg_replace( '/[^A-Za-z0-9_: .-]/', '', substr( $error, 0, 120 ) ) . ')' : '' ) . '.' );
	}
	/** Archivos normales de una carpeta: nombre => tamaño. false si no se puede listar. */
	public function files( $directory ) {
		$list = $this->checked()->rawlist( (string) $directory );
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

/** La copia remota no coincide con el ZIP local: se descarta y la subida empieza de nuevo. */
final class Digitalisimo_Backups_Sftp_Mismatch extends Exception {}
/** El servidor no tiene una clave del tipo pedido (se prueba el siguiente). */
final class Digitalisimo_Backups_Sftp_No_Hostkey extends Exception {}
