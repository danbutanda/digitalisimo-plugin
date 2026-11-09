<?php
defined( 'ABSPATH' ) || exit;

/**
 * Contrato común de los destinos remotos.
 *
 * - put( $archivo, $nombre ): sube por fragmentos a un nombre temporal, verifica y sólo entonces publica con el nombre
 *   final. Devuelve el recibo que guarda el historial (`provider`, `connection`, `path`/`id`/`key`… y `sha256`).
 * - get( $recibo, $destino ): descarga en streaming a un archivo (memoria constante).
 * - size( $recibo ): tamaño remoto en bytes, o false si no existe (HEAD, PROPFIND, SIZE, metadatos).
 * - delete( $recibo ): borra; un archivo que ya no existe no es un error.
 * - list(): respaldos del destino (`name`, `size` y el recibo para get/delete).
 * - verify( $recibo, $archivo, $origen ): descarga y compara tamaño y SHA-256; un destino puede usar en su lugar una
 *   suma que el proveedor garantice (Dropbox content_hash, pCloud checksumfile).
 * - test(): sube, verifica, descarga, comprueba y borra un archivo pequeño («Probar conexión»).
 * - abandon(): al fallar o cancelarse un trabajo, retira lo que su subida dejó a medias (temporal, multipart, sesión).
 *
 * Un destino recibe la configuración ya resuelta (con los secretos descifrados en memoria) y un contexto: carpeta
 * privada para temporales (`temp_dir`), avance y cancelación (`progress`) y estado reanudable (`state`, en el área del
 * trabajo). Nunca registra un secreto, una cabecera de autorización ni una URL firmada.
 */
abstract class Digitalisimo_Backups_Destination {
	/** Identificador del tipo (sftp, ftp, webdav, google, onedrive, dropbox, s3, s3compat, pcloud). */
	const TYPE = '';
	/** Prefijo de sus claves de configuración. */
	const PREFIX = '';
	/** Fragmento por defecto de las subidas por partes. */
	const CHUNK = 8388608;

	protected $s;
	protected $context;

	public function __construct( array $settings, array $context = array() ) {
		$this->s = $settings;
		$this->context = $context + array( 'temp_dir' => '', 'progress' => null, 'state' => null, 'connection' => static::TYPE );
	}

	/* ------------------------------------------------------------------ Descripción del tipo */

	abstract public static function label();
	/**
	 * Campos guiados: clave => array( label, type: text|number|password|select|checkbox|textarea|host|path|url,
	 * options (select), default, required, secret, help, show (condición «campo=valor|valor»), pattern, placeholder ).
	 */
	abstract public static function fields();
	/** Explicación para la pantalla: qué crear en el proveedor y dónde se obtiene cada dato. */
	public static function help() { return array(); }
	/** Claves secretas del tipo (van cifradas al almacén de credenciales). */
	public static function secrets() {
		return array_keys( array_filter( static::fields(), function( $field ) { return ! empty( $field['secret'] ); } ) );
	}
	/** Valores por defecto de la conexión. */
	public static function defaults() {
		$out = array( static::PREFIX . 'name' => static::label() );
		foreach ( static::fields() as $key => $field ) $out[ $key ] = isset( $field['default'] ) ? (string) $field['default'] : '';
		return $out;
	}
	/** Si la configuración tiene todo lo necesario para enviar respaldos (sin conectar). */
	abstract public static function ready( array $s );
	/** OAuth: el proveedor da el token al conectar, no se escribe. */
	public static function oauth() { return false; }
	/**
	 * Valida y normaliza lo enviado por el formulario. Un secreto vacío conserva el guardado. Lanza Exception con un
	 * mensaje para la pantalla si algo no es válido.
	 */
	public static function sanitize( array $input, array $old ) {
		$out = array( static::PREFIX . 'name' => self::text( $input[ static::PREFIX . 'name' ] ?? '' ) );
		if ( '' === $out[ static::PREFIX . 'name' ] ) $out[ static::PREFIX . 'name' ] = static::label();
		foreach ( static::fields() as $key => $field ) {
			$raw = $input[ $key ] ?? null;
			$type = $field['type'] ?? 'text';
			if ( ! empty( $field['secret'] ) ) {
				// Un secreto se guarda exactamente como se escribió (sin recortar ni sanear); vacío o sólo espacios conserva
				// el guardado. Una clave privada (textarea) sólo normaliza sus saltos de línea.
				$label = class_exists( 'Digitalisimo_Backups_Credentials' ) ? Digitalisimo_Backups_Credentials::label( $key ) : 'Una credencial';
				if ( null !== $raw && ! is_string( $raw ) ) throw new Exception( $label . ' no se guardó: no es texto. Corrígelo y vuelve a guardar; no se cambió nada.' );
				$value = (string) $raw;
				if ( 'textarea' === $type ) $value = trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
				if ( '' === trim( $value ) ) { $out[ $key ] = (string) ( $old[ $key ] ?? '' ); continue; }
				$problem = class_exists( 'Digitalisimo_Backups_Credentials' ) ? Digitalisimo_Backups_Credentials::secret_problem( $value, $key ) : '';
				if ( '' !== $problem ) throw new Exception( $label . ' no se guardó: ' . $problem . '. Corrígelo y vuelve a guardar; no se cambió nada.' );
				$out[ $key ] = $value;
				continue;
			}
			if ( ! empty( $field['readonly'] ) ) { $out[ $key ] = (string) ( $old[ $key ] ?? ( $field['default'] ?? '' ) ); continue; }
			switch ( $type ) {
				case 'checkbox':
					$out[ $key ] = ! empty( $raw ) ? '1' : '';
					break;
				case 'number':
					$number = '' === trim( (string) $raw ) ? (int) ( $field['default'] ?? 0 ) : (int) $raw;
					$out[ $key ] = (string) min( (int) ( $field['max'] ?? PHP_INT_MAX ), max( (int) ( $field['min'] ?? 0 ), $number ) );
					break;
				case 'select':
					$options = self::flat_options( (array) ( $field['options'] ?? array() ) );
					$value = is_string( $raw ) ? $raw : '';
					$out[ $key ] = isset( $options[ $value ] ) ? $value : (string) ( $field['default'] ?? key( $options ) );
					break;
				case 'textarea':
					$out[ $key ] = is_string( $raw ) ? trim( str_replace( "\r\n", "\n", $raw ) ) : '';
					break;
				default:
					$out[ $key ] = self::text( $raw );
			}
		}
		return $out;
	}
	/** Opciones de un select con o sin grupos (`grupo => array( valor => etiqueta )`). */
	public static function flat_options( array $options ) {
		$flat = array();
		foreach ( $options as $value => $label ) {
			if ( is_array( $label ) ) { foreach ( $label as $inner => $text ) $flat[ (string) $inner ] = $text; continue; }
			$flat[ (string) $value ] = $label;
		}
		return $flat;
	}
	protected static function text( $value ) {
		if ( ! is_string( $value ) ) return '';
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
	}
	/** Nombre de host o IP sin esquema, ruta ni puerto. */
	public static function valid_host( $host ) {
		$host = (string) $host;
		if ( '' === $host || strlen( $host ) > 253 ) return false;
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) return true;
		if ( '[' === $host[0] && ']' === substr( $host, -1 ) && filter_var( substr( $host, 1, -1 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) return true;
		return (bool) preg_match( '/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$/', $host );
	}
	/** Certificado o cadena PEM pegados en la pantalla: sólo bloques CERTIFICATE completos. */
	public static function valid_pem( $pem ) {
		$pem = trim( (string) $pem );
		if ( '' === $pem ) return true;
		if ( strlen( $pem ) > 65536 ) return false;
		$count = preg_match_all( '/-----BEGIN CERTIFICATE-----\s+[A-Za-z0-9+\/=\s]+-----END CERTIFICATE-----/', $pem, $matches );
		return $count > 0 && '' === trim( preg_replace( '/-----BEGIN CERTIFICATE-----\s+[A-Za-z0-9+\/=\s]+-----END CERTIFICATE-----/', '', $pem ) );
	}
	/** Ruta remota absoluta y normalizada: sin «..», sin barras repetidas, sin barra final (salvo la raíz). */
	public static function clean_path( $path, $default = '/' ) {
		$path = str_replace( '\\', '/', trim( (string) $path ) );
		if ( '' === $path ) $path = $default;
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) continue;
			if ( '..' === $part || false !== strpos( $part, "\0" ) ) throw new Exception( 'La carpeta remota no puede contener «..».' );
			$parts[] = $part;
		}
		return '/' . implode( '/', $parts );
	}

	/* ------------------------------------------------------------------ Contrato */

	/**
	 * Sube, verifica y publica. La copia temporal que no supera la verificación se retira del destino y la publicación
	 * sólo ocurre después de verificar.
	 */
	public function put( $file, $name = '' ) {
		$name = '' !== (string) $name ? (string) $name : self::basename( $file );
		$source = $this->source( $file );
		$temp = $this->upload( $file, $name, $source );
		try {
			$this->verify( $temp, $file, $source );
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			throw $e;
		} catch ( Exception $e ) {
			$this->discard_quietly( $temp );
			$this->forget_state();
			throw $e;
		}
		$remote = $this->publish( $temp, $name );
		$this->forget_state();
		return $remote + array( 'provider' => static::TYPE, 'connection' => (string) $this->context['connection'], 'sha256' => $source['sha256'], 'size' => $source['size'] );
	}
	/** Sube el archivo con un nombre temporal y devuelve su recibo provisional. */
	abstract protected function upload( $file, $name, array $source );
	/** Publica el temporal verificado con el nombre final y devuelve el recibo definitivo. */
	abstract protected function publish( array $temp, $name );
	abstract public function get( array $remote, $target );
	abstract public function size( array $remote );
	abstract public function delete( array $remote );
	abstract public function list();

	/** Verificación por defecto: descarga a un temporal privado y compara tamaño y SHA-256. */
	public function verify( array $remote, $file, array $source ) {
		$temp = $this->temp_file( '.verify-' );
		try {
			$this->get( $remote, $temp );
			clearstatcache( true, $temp );
			$size = @filesize( $temp );
			$hash = @hash_file( 'sha256', $temp );
			if ( false === $size || false === $hash || (int) $size !== (int) $source['size'] || ! hash_equals( $source['sha256'], $hash ) ) throw new Exception( 'La copia en ' . static::label() . ' no coincide con el respaldo local; no se registró como respaldo válido.' );
		} finally {
			@unlink( $temp );
		}
	}

	/** Prueba de conexión: sube un archivo pequeño, lo verifica, lo descarga y lo borra. Devuelve los pasos hechos. */
	public function test() {
		$steps = array();
		$file = $this->temp_file( '.connection-test-' );
		$name = 'digitalisimo-backups-prueba-' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 4 ) ) . '.txt';
		$remote = null;
		try {
			file_put_contents( $file, 'Prueba de conexión de DIGITALÍSIMO Backups ' . bin2hex( random_bytes( 32 ) ) . "\n" . str_repeat( 'D', 4096 ) . "\n" );
			$remote = $this->put( $file, $name );
			$steps[] = 'Subido con nombre temporal, verificado y publicado.';
			$size = $this->size( $remote );
			if ( (int) $size !== (int) filesize( $file ) ) throw new Exception( 'El destino no informa el tamaño correcto del archivo de prueba.' );
			$back = $this->temp_file( '.connection-back-' );
			try {
				$this->get( $remote, $back );
				if ( ! hash_equals( (string) hash_file( 'sha256', $file ), (string) hash_file( 'sha256', $back ) ) ) throw new Exception( 'El archivo de prueba descargado no coincide con el enviado.' );
			} finally {
				@unlink( $back );
			}
			$steps[] = 'Descargado y comparado por SHA-256.';
			$listed = false;
			foreach ( $this->list() as $item ) if ( (string) ( $item['name'] ?? '' ) === $name ) $listed = true;
			if ( ! $listed ) throw new Exception( 'El archivo de prueba no aparece al listar la carpeta remota.' );
			$steps[] = 'Aparece al listar la carpeta.';
			$this->delete( $remote );
			$remote = null;
			$steps[] = 'Borrado del destino.';
		} finally {
			if ( $remote ) $this->discard_quietly( $remote );
			@unlink( $file );
		}
		return $steps;
	}

	/** Retira lo que una subida interrumpida dejó en el destino (según el estado guardado). */
	public function abandon() {
		$state = $this->state();
		if ( ! empty( $state['temp'] ) && is_array( $state['temp'] ) ) $this->discard_quietly( $state['temp'] );
		$this->forget_state();
	}

	/* ------------------------------------------------------------------ Ayudas */

	/** Tamaño y SHA-256 del archivo local, calculados una vez. */
	protected function source( $file ) {
		$size = @filesize( $file );
		$hash = @hash_file( 'sha256', $file );
		if ( false === $size || false === $hash ) throw new Exception( 'No se puede verificar el ZIP local antes de subirlo.' );
		return array( 'size' => (int) $size, 'sha256' => $hash, 'mtime' => (int) @filemtime( $file ) );
	}
	protected function discard_quietly( array $remote ) {
		try { $this->delete( $remote ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	protected static function temporary_name( $name ) { return $name . '.part-' . bin2hex( random_bytes( 6 ) ); }
	protected static function basename( $path ) { return function_exists( 'wp_basename' ) ? wp_basename( $path ) : basename( (string) $path ); }
	/** Carpeta privada para temporales (el almacén de respaldos); se resuelve al primer uso. */
	protected function temp_dir() {
		$dir = $this->context['temp_dir'];
		if ( $dir instanceof Closure ) $dir = $this->context['temp_dir'] = (string) call_user_func( $dir );
		return (string) $dir;
	}
	protected function temp_file( $prefix ) {
		$dir = $this->temp_dir();
		$temp = '' !== $dir ? @tempnam( $dir, $prefix ) : false;
		if ( false === $temp ) throw new Exception( 'No se pudo preparar un archivo temporal privado para el destino.' );
		@chmod( $temp, 0600 );
		return $temp;
	}
	/** Avance de la transferencia (90–99 % del trabajo) con latido y cancelación del motor de trabajos. */
	protected function progress( $done, $total, $verb = 'Transferidos' ) {
		if ( ! is_callable( $this->context['progress'] ) ) return;
		call_user_func( $this->context['progress'], 90 + (int) floor( 9 * $done / max( 1, $total ) ), $verb . ' ' . self::bytes( $done ) . ' de ' . self::bytes( $total ) . '…' );
	}
	protected static function bytes( $bytes ) { return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : round( $bytes / 1048576, 1 ) . ' MB'; }
	/** Estado reanudable de la subida en curso (en el área del trabajo, nunca en la base de datos). */
	protected function state() { return $this->context['state'] instanceof Digitalisimo_Backups_Transfer_State ? $this->context['state']->get() : array(); }
	protected function save_state( array $state ) { if ( $this->context['state'] instanceof Digitalisimo_Backups_Transfer_State ) $this->context['state']->set( $state + array( 'type' => static::TYPE ) ); }
	protected function forget_state() { if ( $this->context['state'] instanceof Digitalisimo_Backups_Transfer_State ) $this->context['state']->clear(); }
	/** Estado guardado sólo si es de este tipo, de esta conexión y del mismo archivo (tamaño, fecha y SHA-256). */
	protected function resumable_state( array $source ) {
		$state = $this->state();
		if ( ( $state['type'] ?? '' ) !== static::TYPE || ( $state['connection'] ?? '' ) !== (string) $this->context['connection'] ) return array();
		if ( (int) ( $state['size'] ?? -1 ) !== $source['size'] || ! hash_equals( (string) ( $state['sha256'] ?? '' ), $source['sha256'] ) ) return array();
		return $state;
	}
	/** CA propia de la conexión escrita en la carpeta privada (para cURL y la API HTTP), o ''. */
	protected function ca_file( $pem ) {
		$pem = trim( (string) $pem );
		if ( '' === $pem ) return '';
		$dir = $this->temp_dir();
		if ( '' === $dir ) throw new Exception( 'No hay carpeta privada para la CA propia del destino.' );
		$path = rtrim( $dir, '/\\' ) . '/.ca-' . substr( hash( 'sha256', $pem ), 0, 16 ) . '.pem';
		if ( ! is_file( $path ) || (string) @file_get_contents( $path ) !== $pem . "\n" ) {
			if ( false === @file_put_contents( $path, $pem . "\n", LOCK_EX ) ) throw new Exception( 'No se pudo preparar la CA propia del destino.' );
			@chmod( $path, 0600 );
		}
		return $path;
	}
}

/**
 * Estado reanudable de una transferencia en el área del trabajo (`jobs/<uuid>/staging/transfer.json`, 0600, escritura
 * atómica). Guarda identificadores de la subida (multipart, sesión, nombre temporal, partes confirmadas), nunca un
 * secreto ni una URL firmada.
 */
final class Digitalisimo_Backups_Transfer_State {
	private $path;
	public function __construct( $path ) { $this->path = (string) $path; }
	public function path() { return $this->path; }
	public function get() {
		if ( '' === $this->path || ! is_file( $this->path ) || is_link( $this->path ) ) return array();
		$data = json_decode( (string) @file_get_contents( $this->path ), true );
		return is_array( $data ) ? $data : array();
	}
	public function set( array $state ) {
		if ( '' === $this->path ) return;
		$dir = dirname( $this->path );
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo guardar el estado de la transferencia.' );
		$temp = $this->path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		$json = json_encode( $state, JSON_UNESCAPED_SLASHES );
		if ( false === $json || false === @file_put_contents( $temp, $json, LOCK_EX ) ) { @unlink( $temp ); throw new Exception( 'No se pudo guardar el estado de la transferencia.' ); }
		@chmod( $temp, 0600 );
		if ( ! @rename( $temp, $this->path ) ) { @unlink( $temp ); throw new Exception( 'No se pudo guardar el estado de la transferencia.' ); }
	}
	public function clear() { if ( '' !== $this->path ) @unlink( $this->path ); }
}
