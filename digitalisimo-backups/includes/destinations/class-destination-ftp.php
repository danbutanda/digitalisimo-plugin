<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente FTP/FTPS propio sobre sockets de PHP (RFC 959, RFC 2428, RFC 3659 y RFC 4217). No depende de la extensión
 * `ftp` (cuyo ftp_ssl_connect no verifica el certificado ni hace FTPS implícito) ni de cURL con FTP.
 *
 * - FTPS explícito: AUTH TLS (234), PBSZ 0 y PROT P antes de cualquier transferencia; FTPS implícito: TLS desde el
 *   primer byte (puerto 990). El canal de datos también va cifrado. TLS 1.2 o 1.3 con el certificado verificado contra
 *   las autoridades públicas o una CA propia, y el nombre del servidor comprobado.
 * - Modo pasivo (EPSV y, si no, PASV) conectando siempre al servidor de control (nunca a la IP que diga la respuesta,
 *   que tras un NAT suele ser privada y abriría un rebote) o activo (PORT/EPRT).
 * - Nunca se registra la contraseña: `$log` guarda los comandos con PASS enmascarado (sólo pruebas).
 */
final class Digitalisimo_Backups_Ftp_Client {
	/** Sólo pruebas: callable( $host, $port, $timeout ) → recurso de flujo. */
	public static $connector = null;
	/** Sólo pruebas: callable( $stream, $host ) → bool (activar TLS). */
	public static $tls = null;
	public $log = array();
	private $o;
	private $control = null;
	private $secure = false;
	private $features = null;

	public function __construct( array $options ) {
		$this->o = $options + array( 'host' => '', 'port' => 21, 'mode' => 'explicit', 'verify' => true, 'ca' => '', 'passive' => true, 'timeout' => 60 );
	}
	public function __destruct() { $this->close(); }

	public function connect( $user, $password ) {
		$this->control = $this->open( $this->o['host'], (int) $this->o['port'] );
		if ( 'implicit' === $this->o['mode'] ) $this->crypto( $this->control );
		$this->expect( $this->reply(), array( 220 ), 'el saludo del servidor' );
		if ( 'explicit' === $this->o['mode'] ) {
			$this->expect( $this->command( 'AUTH TLS' ), array( 234 ), 'AUTH TLS (el servidor no ofrece FTPS explícito)', true );
			$this->crypto( $this->control );
		}
		$this->secure = 'none' !== $this->o['mode'];
		$reply = $this->command( 'USER ' . $user );
		if ( 331 === $reply[0] ) $reply = $this->command( 'PASS ' . $password );
		if ( 230 !== $reply[0] ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor FTP rechazó el usuario o la contraseña (' . $reply[0] . ').' );
		if ( $this->secure ) {
			$this->expect( $this->command( 'PBSZ 0' ), array( 200 ), 'PBSZ 0', true );
			$this->expect( $this->command( 'PROT P' ), array( 200 ), 'PROT P (canal de datos cifrado)', true );
		}
		$this->expect( $this->command( 'TYPE I' ), array( 200 ), 'TYPE I' );
	}
	public function close() {
		if ( ! $this->control ) return;
		try { @fwrite( $this->control, "QUIT\r\n" ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		@fclose( $this->control );
		$this->control = null;
	}

	/* ------------------------------------------------------------------ Canal de control */

	private function open( $host, $port ) {
		if ( self::$connector ) {
			$stream = call_user_func( self::$connector, $host, $port, $this->o['timeout'] );
		} else {
			$target = ( false !== strpos( $host, ':' ) && '[' !== $host[0] ? '[' . $host . ']' : $host ) . ':' . $port;
			$stream = @stream_socket_client( 'tcp://' . $target, $errno, $error, $this->o['timeout'], STREAM_CLIENT_CONNECT, stream_context_create( array( 'ssl' => $this->ssl_options() ) ) );
			if ( ! $stream ) throw new Exception( 'No se pudo conectar con el servidor FTP ' . $host . ':' . $port . ( $error ? ' (' . $error . ')' : '' ) . '.' );
		}
		if ( ! is_resource( $stream ) ) throw new Exception( 'No se pudo conectar con el servidor FTP ' . $host . ':' . $port . '.' );
		stream_set_timeout( $stream, (int) $this->o['timeout'] );
		return $stream;
	}
	private function ssl_options() {
		$verify = ! empty( $this->o['verify'] );
		$options = array( 'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => ! $verify, 'peer_name' => trim( (string) $this->o['host'], '[]' ), 'SNI_enabled' => true, 'disable_compression' => true );
		$bundle = Digitalisimo_Backups_Http::ca_bundle( (string) $this->o['ca'] );
		if ( $verify && '' !== $bundle ) $options['cafile'] = $bundle;
		return $options;
	}
	private function crypto( $stream ) {
		if ( self::$tls ) $ok = call_user_func( self::$tls, $stream, $this->o['host'] );
		else {
			foreach ( $this->ssl_options() as $key => $value ) stream_context_set_option( $stream, 'ssl', $key, $value );
			$method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0 );
			$ok = @stream_socket_enable_crypto( $stream, true, $method );
		}
		if ( true !== $ok ) {
			$error = error_get_last();
			$detail = is_array( $error ) && false !== stripos( (string) $error['message'], 'certificate' ) ? ' El certificado del servidor no es válido para «' . $this->o['host'] . '» o no lo firma una autoridad conocida (puedes pegar su CA propia).' : '';
			throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo establecer TLS con el servidor FTP.' . $detail );
		}
	}
	/** Respuesta (también multilínea «123-…» hasta «123 …»): array( código, texto ). */
	private function reply() {
		$text = '';
		$code = 0;
		while ( true ) {
			$line = @fgets( $this->control, 8192 );
			if ( false === $line ) {
				$meta = @stream_get_meta_data( $this->control );
				throw new Exception( ! empty( $meta['timed_out'] ) ? 'El servidor FTP dejó de responder.' : 'El servidor FTP cerró la conexión.' );
			}
			$text .= $line;
			if ( ! $code && preg_match( '/^(\d{3})([ -])/', $line, $match ) ) {
				$code = (int) $match[1];
				if ( ' ' === $match[2] ) break;
				continue;
			}
			if ( $code && preg_match( '/^' . $code . ' /', $line ) ) break;
		}
		return array( $code, trim( $text ) );
	}
	public function command( $command ) {
		// Un CR, LF o NUL en un argumento (una ruta) inyectaría otro comando con la sesión autenticada.
		if ( preg_match( '/[\r\n\0]/', (string) $command ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Una ruta o un dato de FTP contiene caracteres de control; no se envía.' );
		$this->log[] = 0 === strpos( $command, 'PASS ' ) ? 'PASS ***' : $command;
		$this->write( $this->control, $command . "\r\n" );
		return $this->reply();
	}
	private function write( $stream, $data ) {
		$length = strlen( $data );
		for ( $written = 0; $written < $length; $written += $count ) {
			$count = @fwrite( $stream, substr( $data, $written, 65536 ) );
			if ( false === $count || 0 === $count ) throw new Exception( 'Se cortó la conexión con el servidor FTP.' );
		}
	}
	private function expect( array $reply, array $codes, $what, $permanent = false ) {
		if ( in_array( $reply[0], $codes, true ) ) return $reply;
		$message = 'El servidor FTP no aceptó ' . $what . ' (' . $reply[0] . ').';
		if ( $permanent ) throw new Digitalisimo_Backups_Permanent_Error( $message );
		throw new Exception( $message );
	}
	private function feature( $name ) {
		if ( null === $this->features ) {
			$reply = $this->command( 'FEAT' );
			$this->features = 211 === $reply[0] ? strtoupper( $reply[1] ) : '';
		}
		return false !== strpos( $this->features, strtoupper( $name ) );
	}

	/* ------------------------------------------------------------------ Canal de datos */

	/** Prepara la conexión de datos: pasiva (ya conectada) o activa (socket que espera al servidor). */
	private function prepare_data() {
		if ( ! empty( $this->o['passive'] ) ) {
			$reply = $this->command( 'EPSV' );
			if ( 229 === $reply[0] && preg_match( '/\(([!-~])\1\1(\d+)\1\)/', $reply[1], $match ) ) $port = (int) $match[2];
			else {
				$reply = $this->expect( $this->command( 'PASV' ), array( 227 ), 'el modo pasivo (PASV)' );
				if ( ! preg_match( '/(\d+),(\d+),(\d+),(\d+),(\d+),(\d+)/', $reply[1], $match ) ) throw new Exception( 'El servidor FTP respondió a PASV sin puerto.' );
				$port = (int) $match[5] * 256 + (int) $match[6];
			}
			if ( $port < 1 || $port > 65535 ) throw new Exception( 'El servidor FTP indicó un puerto de datos no válido.' );
			// Siempre al servidor de control: la IP de la respuesta puede ser privada (NAT) o de otro equipo (rebote).
			return array( 'stream' => $this->open( $this->o['host'], $port ) );
		}
		$local = (string) stream_socket_get_name( $this->control, false );
		$ip = preg_replace( '/:\d+$/', '', $local );
		$ip = trim( $ip, '[]' );
		$server = @stream_socket_server( 'tcp://' . ( false !== strpos( $ip, ':' ) ? '[' . $ip . ']' : $ip ) . ':0', $errno, $error );
		if ( ! $server ) throw new Exception( 'No se pudo abrir el puerto local para el modo activo de FTP.' );
		$name = (string) stream_socket_get_name( $server, false );
		$port = (int) substr( $name, strrpos( $name, ':' ) + 1 );
		if ( false !== strpos( $ip, ':' ) ) $this->expect( $this->command( 'EPRT |2|' . $ip . '|' . $port . '|' ), array( 200 ), 'el modo activo (EPRT)' );
		else $this->expect( $this->command( 'PORT ' . str_replace( '.', ',', $ip ) . ',' . intdiv( $port, 256 ) . ',' . ( $port % 256 ) ), array( 200 ), 'el modo activo (PORT)' );
		return array( 'server' => $server );
	}
	/** Tras la respuesta preliminar (125/150): acepta la conexión activa y activa TLS en el canal de datos. */
	private function data_ready( array $data ) {
		if ( isset( $data['server'] ) ) {
			$peer = '';
			$stream = @stream_socket_accept( $data['server'], (int) $this->o['timeout'], $peer );
			fclose( $data['server'] );
			if ( ! $stream ) throw new Exception( 'El servidor FTP no abrió la conexión de datos del modo activo (¿cortafuegos?). Prueba el modo pasivo.' );
			// Sólo el servidor de control: quien gane la carrera al puerto desde otro equipo no recibe ni envía el respaldo.
			if ( self::address( (string) $peer ) !== self::address( (string) stream_socket_get_name( $this->control, true ) ) ) {
				@fclose( $stream );
				throw new Exception( 'La conexión de datos del modo activo no vino del servidor FTP; se rechazó. Prueba el modo pasivo.' );
			}
			stream_set_timeout( $stream, (int) $this->o['timeout'] );
			$data['stream'] = $stream;
		}
		if ( $this->secure ) $this->crypto( $data['stream'] );
		return $data['stream'];
	}
	/** IP de un «ip:puerto» de un socket, normalizada (una IPv4 dentro de IPv6 vale como IPv4). */
	private static function address( $name ) {
		$ip = trim( (string) preg_replace( '/:\d+$/', '', $name ), '[]' );
		$bin = @inet_pton( $ip );
		if ( false === $bin || '' === $ip ) return $ip;
		if ( 16 === strlen( $bin ) && str_repeat( "\0", 10 ) . "\xff\xff" === substr( $bin, 0, 12 ) ) $bin = substr( $bin, 12 );
		return (string) inet_ntop( $bin );
	}
	private function abort_data( array $data ) {
		if ( isset( $data['stream'] ) && is_resource( $data['stream'] ) ) @fclose( $data['stream'] );
		if ( isset( $data['server'] ) && is_resource( $data['server'] ) ) @fclose( $data['server'] );
	}

	/* ------------------------------------------------------------------ Operaciones */

	/** STOR (o APPE desde $offset para reanudar) del archivo local; $progress( $hechos, $total ). */
	public function store( $remote, $file, $offset = 0, $progress = null ) {
		$handle = @fopen( $file, 'rb' );
		if ( ! $handle || ( $offset > 0 && 0 !== fseek( $handle, $offset ) ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' ); }
		$total = (int) filesize( $file );
		$data = $this->prepare_data();
		try {
			$reply = $this->command( ( $offset > 0 ? 'APPE ' : 'STOR ' ) . $remote );
			if ( ! in_array( $reply[0], array( 125, 150 ), true ) ) { $this->abort_data( $data ); $this->expect( $reply, array( 150 ), 'el envío del archivo' ); }
			$stream = $this->data_ready( $data );
			$done = $offset;
			while ( ! feof( $handle ) ) {
				$chunk = fread( $handle, 1048576 );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer el respaldo local para enviarlo.' );
				if ( '' === $chunk ) break;
				$this->write( $stream, $chunk );
				$done += strlen( $chunk );
				if ( is_callable( $progress ) ) call_user_func( $progress, $done, $total );
			}
			@fflush( $stream );
			fclose( $stream );
			unset( $data['stream'] );
		} catch ( Throwable $e ) {
			$this->abort_data( $data );
			throw $e;
		} finally {
			fclose( $handle );
		}
		$this->expect( $this->reply(), array( 226, 250 ), 'el cierre de la transferencia' );
	}
	/** RETR a un archivo local en streaming. */
	public function retrieve( $remote, $target, $progress = null ) {
		$out = @fopen( $target, 'wb' );
		if ( ! $out ) throw new Exception( 'No se pudo preparar el archivo temporal de la descarga.' );
		$data = $this->prepare_data();
		try {
			$reply = $this->command( 'RETR ' . $remote );
			if ( ! in_array( $reply[0], array( 125, 150 ), true ) ) { $this->abort_data( $data ); if ( 550 === $reply[0] ) throw new Exception( 'El respaldo ya no está en el servidor FTP.' ); $this->expect( $reply, array( 150 ), 'la descarga del archivo' ); }
			$stream = $this->data_ready( $data );
			$done = 0;
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk ) break;
				if ( '' === $chunk ) { $meta = stream_get_meta_data( $stream ); if ( ! empty( $meta['timed_out'] ) ) throw new Exception( 'El servidor FTP dejó de enviar datos.' ); continue; }
				if ( false === fwrite( $out, $chunk ) ) throw new Exception( 'No se pudo escribir la descarga en el almacén privado.' );
				$done += strlen( $chunk );
				if ( is_callable( $progress ) ) call_user_func( $progress, $done, 0 );
			}
			fclose( $stream );
			unset( $data['stream'] );
		} catch ( Throwable $e ) {
			$this->abort_data( $data );
			throw $e;
		} finally {
			fclose( $out );
		}
		$this->expect( $this->reply(), array( 226, 250 ), 'el cierre de la descarga' );
	}
	/** SIZE (RFC 3659): tamaño en bytes o false si no existe. */
	public function size( $remote ) {
		$reply = $this->command( 'SIZE ' . $remote );
		if ( 213 === $reply[0] && preg_match( '/^213\s+(\d+)/', $reply[1], $match ) ) return (int) $match[1];
		if ( 550 === $reply[0] ) return false;
		throw new Exception( 'El servidor FTP no informó el tamaño del archivo (' . $reply[0] . ').' );
	}
	public function delete( $remote ) {
		$reply = $this->command( 'DELE ' . $remote );
		if ( 250 === $reply[0] ) return true;
		if ( 550 === $reply[0] && false === $this->size( $remote ) ) return true;
		throw new Exception( 'El servidor FTP no borró el archivo (' . $reply[0] . ').' );
	}
	public function rename( $from, $to ) {
		$this->expect( $this->command( 'RNFR ' . $from ), array( 350 ), 'RNFR' );
		$this->expect( $this->command( 'RNTO ' . $to ), array( 250 ), 'RNTO' );
	}
	/** Crea la carpeta remota nivel por nivel (si ya existe, sigue). */
	public function mkdir_p( $path ) {
		$current = '';
		foreach ( array_filter( explode( '/', (string) $path ), 'strlen' ) as $part ) {
			$current .= '/' . $part;
			$reply = $this->command( 'MKD ' . $current );
			if ( 257 === $reply[0] ) continue;
			$check = $this->command( 'CWD ' . $current );
			if ( 250 !== $check[0] ) throw new Exception( 'El servidor FTP no creó la carpeta remota «' . $current . '» (' . $reply[0] . ').' );
		}
		$this->command( 'CWD /' );
	}
	/** Archivos de una carpeta: MLSD (con tamaño) o, si no lo admite, NLST y SIZE. */
	public function files( $directory ) {
		$mlsd = $this->feature( 'MLSD' );
		$data = $this->prepare_data();
		$reply = $this->command( ( $mlsd ? 'MLSD ' : 'NLST ' ) . $directory );
		if ( ! in_array( $reply[0], array( 125, 150 ), true ) ) {
			$this->abort_data( $data );
			if ( in_array( $reply[0], array( 450, 550 ), true ) ) return array();
			$this->expect( $reply, array( 150 ), 'el listado de la carpeta' );
		}
		$stream = $this->data_ready( $data );
		$raw = '';
		while ( ! feof( $stream ) ) { $chunk = fread( $stream, 65536 ); if ( false === $chunk ) break; if ( '' === $chunk ) { $meta = stream_get_meta_data( $stream ); if ( ! empty( $meta['timed_out'] ) ) break; continue; } $raw .= $chunk; if ( strlen( $raw ) > 16777216 ) break; }
		fclose( $stream );
		$this->expect( $this->reply(), array( 226, 250 ), 'el cierre del listado' );
		$out = array();
		foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
			if ( '' === trim( $line ) ) continue;
			if ( $mlsd ) {
				$parts = explode( ' ', $line, 2 );
				if ( 2 !== count( $parts ) || ! preg_match( '/(?:^|;)type=file(?:;|$)/i', $parts[0] ) ) continue;
				$size = preg_match( '/(?:^|;)size=(\d+)/i', $parts[0], $match ) ? (int) $match[1] : 0;
				$out[] = array( 'name' => $parts[1], 'size' => $size );
			} else {
				$name = basename( str_replace( '\\', '/', trim( $line ) ) );
				$size = $this->size( rtrim( $directory, '/' ) . '/' . $name );
				if ( false !== $size ) $out[] = array( 'name' => $name, 'size' => $size );
			}
		}
		return $out;
	}
}

/**
 * FTP y FTPS. FTPS explícito por defecto; FTPS implícito; FTP sin cifrar sólo eligiéndolo, con advertencia. Subida a
 * `<nombre>.part-<aleatorio>` (reanudable: un reintento pregunta su tamaño con SIZE y sigue con APPE), verificación
 * descargando y comparando SHA-256, y publicación con RNFR/RNTO.
 */
final class Digitalisimo_Backups_Destination_Ftp extends Digitalisimo_Backups_Destination {
	const TYPE = 'ftp';
	const PREFIX = 'ftp_';
	private $client = null;

	public static function label() { return 'FTP/FTPS'; }
	public static function fields() {
		return array(
			'ftp_host' => array( 'label' => 'Servidor', 'type' => 'host', 'placeholder' => 'ftp.ejemplo.com' ),
			'ftp_security' => array( 'label' => 'Cifrado', 'type' => 'select', 'default' => 'explicit', 'options' => array( 'explicit' => 'FTPS explícito (AUTH TLS) · recomendado', 'implicit' => 'FTPS implícito (TLS desde el inicio, normalmente puerto 990)', 'none' => 'FTP sin cifrar (inseguro)' ), 'warning' => 'Con FTP sin cifrar, la contraseña y los respaldos viajan en claro por la red: úsalo sólo en una red privada.' ),
			'ftp_port' => array( 'label' => 'Puerto', 'type' => 'number', 'default' => '21', 'min' => 1, 'max' => 65535, 'help' => '21 para FTP y FTPS explícito; 990 para FTPS implícito.' ),
			'ftp_user' => array( 'label' => 'Usuario', 'type' => 'text' ),
			'ftp_password' => array( 'label' => 'Contraseña', 'type' => 'password', 'secret' => true ),
			'ftp_path' => array( 'label' => 'Carpeta remota', 'type' => 'path', 'default' => '/digitalisimo-backups', 'help' => 'Ruta absoluta; se crea si no existe.' ),
			'ftp_passive' => array( 'label' => 'Modo de conexión', 'type' => 'select', 'default' => 'passive', 'options' => array( 'passive' => 'Pasivo (recomendado)', 'active' => 'Activo (el servidor se conecta a este hosting)' ) ),
			'ftp_ca' => array( 'label' => 'CA propia (PEM)', 'type' => 'textarea', 'show' => 'ftp_security=explicit|implicit', 'help' => 'Sólo si el servidor usa un certificado de una autoridad propia. Vacío: las autoridades públicas.' ),
			'ftp_insecure' => array( 'label' => 'Verificación TLS', 'type' => 'checkbox', 'text' => 'No verificar el certificado del servidor (inseguro)', 'show' => 'ftp_security=explicit|implicit', 'warning' => 'Sin verificar el certificado, cualquiera en la red puede hacerse pasar por el servidor y leer la contraseña y los respaldos. Usa mejor la CA propia.' ),
		);
	}
	public static function help() { return array( 'Pide a tu proveedor el servidor, el usuario y si ofrece FTPS explícito o implícito.', 'Si el servidor exige reutilizar la sesión TLS en el canal de datos (vsftpd con require_ssl_reuse=YES o ProFTPD sin NoSessionReuseRequired), desactiva esa opción para esta cuenta: PHP no puede reutilizarla.' ); }
	public static function hosts( array $s ) { return array( (string) ( $s['ftp_host'] ?? '' ) ); }
	public static function ready( array $s ) { return ! empty( $s['ftp_host'] ) && ! empty( $s['ftp_user'] ) && ! empty( $s['ftp_password'] ); }
	public static function sanitize( array $input, array $old ) {
		$out = parent::sanitize( $input, $old );
		if ( '' !== $out['ftp_host'] && ! self::valid_host( $out['ftp_host'] ) ) throw new Exception( 'El servidor FTP debe ser un nombre de host o una IP, sin ftp:// ni ruta.' );
		if ( '' === trim( (string) ( $input['ftp_port'] ?? '' ) ) ) $out['ftp_port'] = 'implicit' === $out['ftp_security'] ? '990' : '21';
		$out['ftp_path'] = self::clean_path( $out['ftp_path'], '/' );
		if ( ! self::valid_pem( $out['ftp_ca'] ) ) throw new Exception( 'La CA propia debe ser uno o más certificados PEM completos (-----BEGIN CERTIFICATE----- … -----END CERTIFICATE-----).' );
		return $out;
	}

	private function client() {
		if ( $this->client ) return $this->client;
		$s = $this->s;
		if ( ! self::ready( $s ) ) {
			$problem = class_exists( 'Digitalisimo_Backups' ) && empty( $s['ftp_password'] ) ? Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) : '';
			throw new Digitalisimo_Backups_Permanent_Error( '' !== $problem ? $problem : 'Completa el servidor, el usuario y la contraseña de FTP.' );
		}
		$client = new Digitalisimo_Backups_Ftp_Client( array( 'host' => (string) $s['ftp_host'], 'port' => (int) ( $s['ftp_port'] ?: 21 ), 'mode' => in_array( $s['ftp_security'] ?? '', array( 'explicit', 'implicit', 'none' ), true ) ? $s['ftp_security'] : 'explicit', 'verify' => empty( $s['ftp_insecure'] ), 'ca' => $this->ca_file( (string) ( $s['ftp_ca'] ?? '' ) ), 'passive' => 'active' !== ( $s['ftp_passive'] ?? 'passive' ), 'timeout' => (int) ( $this->context['timeout'] ?? 60 ) ?: 60 ) );
		$client->connect( (string) $s['ftp_user'], (string) $s['ftp_password'] );
		return $this->client = $client;
	}
	public function client_log() { return $this->client ? $this->client->log : array(); }
	private function directory() { return rtrim( self::clean_path( (string) ( $this->s['ftp_path'] ?? '/' ), '/' ), '/' ); }

	protected function upload( $file, $name, array $source ) {
		$client = $this->client();
		$state = $this->resumable_state( $source );
		$offset = 0;
		if ( ! empty( $state['temp']['path'] ) ) {
			$size = $client->size( (string) $state['temp']['path'] );
			if ( false !== $size && $size > 0 && $size <= $source['size'] ) $offset = $size;
			$temp = $state['temp'];
		} else {
			$client->mkdir_p( $this->directory() );
			$temp = array( 'provider' => self::TYPE, 'path' => $this->directory() . '/' . self::temporary_name( $name ) );
			$this->save_state( array( 'connection' => (string) $this->context['connection'], 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => $temp ) );
		}
		if ( $offset < $source['size'] || 0 === $source['size'] ) {
			try {
				$client->store( (string) $temp['path'], $file, $offset, function( $done, $total ) { $this->progress( $done, $total ); } );
			} catch ( Throwable $e ) {
				// El canal de control puede quedar con una respuesta pendiente: la conexión no se reutiliza.
				$client->close();
				$this->client = null;
				throw $e;
			}
		}
		return $temp;
	}
	protected function publish( array $temp, $name ) {
		$client = $this->client();
		$final = $this->directory() . '/' . $name;
		if ( false !== $client->size( $final ) ) { $this->discard_quietly( $temp ); throw new Exception( 'Ya existe en el servidor FTP un archivo con el nombre del respaldo; no se sobrescribe.' ); }
		$client->rename( (string) $temp['path'], $final );
		return array( 'provider' => self::TYPE, 'path' => $final );
	}
	private function own( array $remote ) { return self::inside( (string) ( $remote['path'] ?? '' ), $this->directory(), self::label() ); }
	public function get( array $remote, $target ) { $path = $this->own( $remote ); $this->client()->retrieve( $path, $target, function( $done ) {} ); }
	public function size( array $remote ) { $path = $this->own( $remote ); return $this->client()->size( $path ); }
	public function delete( array $remote ) { $path = $this->own( $remote ); $this->client()->delete( $path ); }
	public function list() {
		$out = array();
		foreach ( $this->client()->files( '' !== $this->directory() ? $this->directory() : '/' ) as $item ) $out[] = $item + array( 'remote' => array( 'provider' => self::TYPE, 'connection' => (string) $this->context['connection'], 'path' => $this->directory() . '/' . $item['name'] ) );
		return $out;
	}
}
