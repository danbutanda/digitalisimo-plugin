<?php
defined( 'ABSPATH' ) || exit;

/**
 * SFTP (extensión ssh2). La huella de la clave del servidor es obligatoria y se compara antes de autenticarse: SHA-1
 * hexadecimal (la que ya se guardaba) o SHA-256 en el formato de OpenSSH (`SHA256:…`) si la extensión la calcula.
 * Contraseña o clave privada (con frase opcional). Escritura a `<nombre>.part-<aleatorio>`, comparación SHA-256 leyendo
 * la copia remota y `rename()` sólo después.
 */
final class Digitalisimo_Backups_Destination_Sftp extends Digitalisimo_Backups_Destination {
	const TYPE = 'sftp';
	const PREFIX = 'sftp_';
	private $connection = null;
	private $sftp = null;
	private $key_files = array();

	public static function label() { return 'SFTP'; }
	public static function fields() {
		return array(
			'sftp_host' => array( 'label' => 'Host o IP', 'type' => 'host', 'required' => true, 'placeholder' => 'sftp.ejemplo.com' ),
			'sftp_port' => array( 'label' => 'Puerto', 'type' => 'number', 'default' => '22', 'min' => 1, 'max' => 65535 ),
			'sftp_user' => array( 'label' => 'Usuario', 'type' => 'text', 'required' => true ),
			'sftp_auth' => array( 'label' => 'Autenticación', 'type' => 'select', 'default' => 'password', 'options' => array( 'password' => 'Contraseña', 'key' => 'Clave privada SSH' ) ),
			'sftp_password' => array( 'label' => 'Contraseña', 'type' => 'password', 'secret' => true, 'show' => 'sftp_auth=password' ),
			'sftp_private_key' => array( 'label' => 'Clave privada', 'type' => 'textarea', 'secret' => true, 'show' => 'sftp_auth=key', 'help' => 'Pega la clave privada completa (OpenSSH o PEM). Se guarda cifrada y nunca vuelve al navegador.' ),
			'sftp_key_passphrase' => array( 'label' => 'Frase de la clave', 'type' => 'password', 'secret' => true, 'show' => 'sftp_auth=key', 'help' => 'Sólo si la clave privada está protegida con una frase.' ),
			'sftp_public_key' => array( 'label' => 'Clave pública', 'type' => 'textarea', 'show' => 'sftp_auth=key', 'help' => 'La línea de la clave pública (ssh-ed25519 AAAA… o ssh-rsa AAAA…) que está en authorized_keys del servidor.' ),
			'sftp_path' => array( 'label' => 'Carpeta remota', 'type' => 'path', 'default' => '/' ),
			'sftp_fingerprint' => array( 'label' => 'Huella de la clave SSH del servidor', 'type' => 'text', 'help' => 'SHA-256 como la muestra OpenSSH (SHA256:…) o SHA-1 hexadecimal de 40 caracteres, con o sin dos puntos. Confírmala con el administrador del servidor por un canal independiente: sin ella no se envían respaldos.' ),
		);
	}
	public static function help() {
		return array( 'En el servidor: ssh-keyscan -t ed25519 servidor > k; ssh-keygen -l -E sha256 -f k muestra la huella SHA-256 (con -E sha1, la SHA-1).', 'La carpeta remota debe ser una ruta absoluta en la que el usuario pueda escribir.' );
	}
	public static function hosts( array $s ) { return array( (string) ( $s['sftp_host'] ?? '' ) ); }
	public static function ready( array $s ) {
		$auth = 'key' === ( $s['sftp_auth'] ?? 'password' ) ? ! empty( $s['sftp_private_key'] ) && ! empty( $s['sftp_public_key'] ) : ! empty( $s['sftp_password'] );
		return ! empty( $s['sftp_host'] ) && ! empty( $s['sftp_user'] ) && $auth && ! empty( $s['sftp_fingerprint'] );
	}
	public static function sanitize( array $input, array $old ) {
		$fingerprint = trim( (string) ( $input['sftp_fingerprint'] ?? '' ) );
		$normalized = self::normalize_fingerprint( $fingerprint );
		if ( '' !== $fingerprint && '' === $normalized ) throw new Exception( 'La huella SFTP debe ser SHA-256 (SHA256:… como la muestra OpenSSH, o 64 caracteres hexadecimales) o SHA-1 de 40 caracteres hexadecimales, con o sin dos puntos.' );
		$out = parent::sanitize( $input, $old );
		$out['sftp_fingerprint'] = $normalized;
		$out['sftp_port'] = (string) min( 65535, max( 1, (int) ( $input['sftp_port'] ?? 22 ) ?: 22 ) );
		$public = trim( preg_replace( '/\s+/', ' ', (string) ( $input['sftp_public_key'] ?? '' ) ) );
		if ( '' !== $public && ! preg_match( '/^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-[a-z0-9@.-]+) [A-Za-z0-9+\/=]+( [^\s].*)?$/', $public ) ) throw new Exception( 'La clave pública SFTP debe ser una línea como «ssh-ed25519 AAAA… comentario».' );
		$out['sftp_public_key'] = $public;
		$private = (string) $out['sftp_private_key'];
		if ( '' !== $private && ! preg_match( '/^-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----\n.+\n-----END [A-Z0-9 ]*PRIVATE KEY-----$/s', $private ) ) throw new Exception( 'La clave privada SFTP debe pegarse completa, de «-----BEGIN … PRIVATE KEY-----» a «-----END … PRIVATE KEY-----».' );
		if ( '' !== (string) $out['sftp_path'] ) $out['sftp_path'] = self::clean_path( $out['sftp_path'] );
		return $out;
	}
	/** SHA-1 → 40 hexadecimales en mayúsculas (como se guardaba); SHA-256 → «SHA256:<base64 sin relleno>». '' si no es válida. */
	public static function normalize_fingerprint( $fingerprint ) {
		$fingerprint = trim( (string) $fingerprint );
		if ( preg_match( '/^(?:[a-f0-9]{40}|(?:[a-f0-9]{2}:){19}[a-f0-9]{2})$/i', $fingerprint ) ) return strtoupper( str_replace( ':', '', $fingerprint ) );
		if ( preg_match( '/^(?:[a-f0-9]{64}|(?:[a-f0-9]{2}:){31}[a-f0-9]{2})$/i', $fingerprint ) ) return 'SHA256:' . rtrim( base64_encode( hex2bin( str_replace( ':', '', $fingerprint ) ) ), '=' );
		if ( preg_match( '/^SHA256:([A-Za-z0-9+\/]{43})=?$/', $fingerprint, $match ) ) {
			$raw = base64_decode( $match[1] . '=', true );
			return false !== $raw && 32 === strlen( $raw ) ? 'SHA256:' . $match[1] : '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ Conexión */

	/** Conexión autenticada (la huella se comprueba antes de enviar ninguna credencial). */
	public function connection() {
		if ( $this->connection ) return $this->connection;
		$s = $this->s;
		$key = 'key' === ( $s['sftp_auth'] ?? 'password' );
		$secret = $key ? ( $s['sftp_private_key'] ?? '' ) : ( $s['sftp_password'] ?? '' );
		if ( empty( $secret ) && class_exists( 'Digitalisimo_Backups' ) && '' !== ( $problem = Digitalisimo_Backups::destination_problem( (string) $this->context['connection'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
		if ( ! function_exists( 'ssh2_connect' ) || ! function_exists( 'ssh2_fingerprint' ) || empty( $s['sftp_host'] ) || empty( $s['sftp_user'] ) || empty( $secret ) || ( $key && empty( $s['sftp_public_key'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Completa la conexión SFTP para gestionar este respaldo.' );
		$expected = self::normalize_fingerprint( (string) ( $s['sftp_fingerprint'] ?? '' ) );
		if ( '' === $expected ) throw new Digitalisimo_Backups_Permanent_Error( 'Configura y verifica la huella SHA-1 de la clave SSH del servidor antes de usar SFTP.' );
		$sha256 = 0 === strpos( $expected, 'SHA256:' );
		if ( $sha256 && ! defined( 'SSH2_FINGERPRINT_SHA256' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La extensión ssh2 de este servidor no calcula huellas SHA-256: escribe la huella SHA-1 de la clave del servidor (ssh-keygen -l -E sha1 -f …).' );
		$connection = @ssh2_connect( $s['sftp_host'], (int) $s['sftp_port'] );
		if ( ! $connection ) throw new Exception( 'No se pudo conectar al servidor SFTP.' );
		if ( $sha256 ) {
			$raw = @ssh2_fingerprint( $connection, constant( 'SSH2_FINGERPRINT_SHA256' ) | SSH2_FINGERPRINT_RAW );
			$actual = is_string( $raw ) ? 'SHA256:' . rtrim( base64_encode( $raw ), '=' ) : '';
			$match = '' !== $actual && hash_equals( $expected, $actual );
		} else {
			$actual = @ssh2_fingerprint( $connection, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX );
			$match = is_string( $actual ) && hash_equals( $expected, strtoupper( $actual ) );
		}
		if ( ! $match ) throw new Digitalisimo_Backups_Permanent_Error( 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' );
		if ( ! ( $key ? $this->auth_key( $connection ) : @ssh2_auth_password( $connection, $s['sftp_user'], $s['sftp_password'] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo autenticar en SFTP.' );
		return $this->connection = $connection;
	}
	/** Clave privada: en memoria si la extensión lo permite; si no, en archivos 0600 de la carpeta privada que se borran al momento. */
	private function auth_key( $connection ) {
		$s = $this->s;
		$passphrase = (string) ( $s['sftp_key_passphrase'] ?? '' );
		if ( function_exists( 'ssh2_auth_pubkey' ) ) return @ssh2_auth_pubkey( $connection, $s['sftp_user'], $s['sftp_public_key'], $s['sftp_private_key'], $passphrase );
		if ( ! function_exists( 'ssh2_auth_pubkey_file' ) ) return false;
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
		$sftp = @ssh2_sftp( $this->connection() );
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
		try {
			$copied = @stream_copy_to_stream( $handle, $target );
			$flushed = @fflush( $target );
		} finally {
			fclose( $handle );
			fclose( $target );
		}
		if ( false === $copied || $copied !== $source['size'] || ! $flushed ) {
			@ssh2_sftp_unlink( $sftp, $temporary );
			throw new Exception( 'La transferencia SFTP quedó incompleta.' );
		}
		return array( 'provider' => self::TYPE, 'path' => $temporary );
	}
	/** Lee la copia remota completa y compara su SHA-256 (también el tamaño, por la longitud leída). */
	public function verify( array $remote, $file, array $source ) {
		$hash = @hash_file( 'sha256', $this->url( (string) $remote['path'] ) );
		if ( false === $hash || ! hash_equals( $source['sha256'], $hash ) ) throw new Exception( 'La copia SFTP no coincide con el ZIP local.' );
	}
	protected function publish( array $temp, $name ) {
		$sftp = $this->session();
		$final = rtrim( $this->directory(), '/' ) . '/' . $name;
		if ( ! @ssh2_sftp_rename( $sftp, $temp['path'], $final ) ) {
			@ssh2_sftp_unlink( $sftp, $temp['path'] );
			throw new Exception( 'No se pudo publicar el respaldo verificado en SFTP.' );
		}
		return array( 'provider' => self::TYPE, 'path' => $final );
	}
	private function own( array $remote ) { return self::inside( (string) ( $remote['path'] ?? '' ), rtrim( $this->directory(), '/' ), self::label() ); }
	public function get( array $remote, $target ) {
		$source = @fopen( $this->url( $this->own( $remote ) ), 'rb' );
		if ( ! $source ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
		$out = @fopen( $target, 'wb' );
		if ( ! $out ) { fclose( $source ); throw new Exception( 'No se pudo preparar el archivo temporal de la descarga.' ); }
		try {
			$copied = @stream_copy_to_stream( $source, $out );
		} finally {
			fclose( $source );
			fclose( $out );
		}
		if ( false === $copied ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
	}
	/** B1b: lectura desde una posición (`fseek` del flujo SFTP), por bloques de 1 MiB (memoria constante). */
	public function ranged() { return true; }
	public function get_range( array $remote, $target, $offset, $length ) {
		$source = @fopen( $this->url( $this->own( $remote ) ), 'rb' );
		if ( ! $source ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
		$out = @fopen( $target, 'wb' );
		if ( ! $out ) { fclose( $source ); throw new Exception( 'No se pudo preparar el archivo temporal de la descarga.' ); }
		$done = 0;
		try {
			if ( (int) $offset > 0 && 0 !== @fseek( $source, (int) $offset ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor SFTP no permite reanudar la lectura a mitad del respaldo.' );
			while ( $done < (int) $length && ! feof( $source ) ) {
				$chunk = fread( $source, (int) min( 1048576, (int) $length - $done ) );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer el respaldo SFTP.' );
				if ( '' === $chunk ) break;
				if ( strlen( $chunk ) !== fwrite( $out, $chunk ) ) throw new Exception( 'No se pudo escribir la descarga en el almacén privado.' );
				$done += strlen( $chunk );
			}
		} finally {
			fclose( $source );
			fclose( $out );
		}
		if ( $done <= 0 ) throw new Exception( 'El respaldo SFTP es más corto de lo que se listó (cambió en el destino).' );
		return $done;
	}
	public function size( array $remote ) {
		$path = $this->own( $remote );
		$stat = function_exists( 'ssh2_sftp_stat' ) ? @ssh2_sftp_stat( $this->session(), $path ) : @stat( $this->url( $path ) );
		return is_array( $stat ) && isset( $stat['size'] ) ? (int) $stat['size'] : false;
	}
	public function delete( array $remote ) {
		$path = $this->own( $remote );
		$sftp = $this->session();
		if ( @ssh2_sftp_unlink( $sftp, $path ) ) return;
		if ( false === $this->size( $remote ) ) return;
		throw new Exception( 'No se pudo eliminar el archivo SFTP.' );
	}
	public function list() {
		$directory = $this->directory();
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
