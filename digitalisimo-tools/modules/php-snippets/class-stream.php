<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Envoltorio `digitalisimo-php://` de un solo uso y sólo lectura (§4.3).
 *
 * Rutas: `digitalisimo-php://site-<blog_id>/<id>@<h12>` y `digitalisimo-php://network-<network_id>/<id>@<h12>`.
 * - Sólo sirve lo que el Runner puso con put(), en modo `r` o `rb`, y nunca lee la petición.
 * - forget() borra la entrada al terminar el `include`: ni otro fragmento ni un plugin pueden leer después el código.
 * - Si `stream_wrapper_register` no existe o el esquema ya lo registró otro componente, register() devuelve false y no
 *   corre ningún fragmento: un envoltorio ajeno serviría código sin firma.
 * - Con el envoltorio, el modo de recuperación del núcleo responde `invalid_source` en lugar de pausar Tools, y OPcache
 *   no guarda el código.
 */
final class Stream {
	const SCHEME = 'digitalisimo-php';

	/** Contexto del flujo; PHP lo asigna y avisa si la propiedad no existe. */
	public $context;

	/** Ruta → código disponible para el siguiente `include`. */
	private static $code = array();
	private static $registered = false;
	/** '' (sin problema), 'disabled' (stream_wrapper_register no existe) o 'foreign' (otro componente tiene el esquema). */
	private static $problem = '';

	private $data = '';
	private $pos  = 0;

	/** Registra el envoltorio una vez por petición. false si no se puede o si el esquema es ajeno. */
	public static function register() {
		if ( self::$registered ) return true;
		if ( ! function_exists( 'stream_wrapper_register' ) || ! function_exists( 'stream_get_wrappers' ) ) {
			self::$problem = 'disabled';
			return false;
		}
		if ( in_array( self::SCHEME, stream_get_wrappers(), true ) || ! @stream_wrapper_register( self::SCHEME, __CLASS__ ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			self::$problem = 'foreign';
			return false;
		}
		self::$registered = true;
		self::$problem    = '';
		return true;
	}

	/** Motivo por el que no se registró: '', 'disabled' o 'foreign'. */
	public static function problem() {
		return self::$problem;
	}

	/** Mensaje para la pantalla cuando el servidor no permite ejecutar fragmentos (§7.5), o '' si no hay problema. */
	public static function problem_message() {
		if ( 'disabled' === self::$problem ) return 'Este servidor no permite ejecutar fragmentos PHP (stream_wrapper_register está deshabilitada).';
		if ( 'foreign' === self::$problem ) return 'Otro componente registró digitalisimo-php://: por seguridad no se ejecuta ningún fragmento.';
		return '';
	}

	/** Ruta de una versión: el `h12` dice qué versión falló y evita cualquier caché por ruta. */
	public static function path( $scope, $id, $hash ) {
		$owner = 'network' === $scope ? 'network-' . (int) get_current_network_id() : 'site-' . (int) get_current_blog_id();
		return self::SCHEME . '://' . $owner . '/' . $id . '@' . Store::h12( $hash );
	}

	/** Deja el código listo para un `include` y devuelve su ruta. */
	public static function put( $scope, $id, $code, $hash ) {
		$path                = self::path( $scope, $id, $hash );
		self::$code[ $path ] = (string) $code;
		return $path;
	}

	/** El código deja de ser legible por el envoltorio. */
	public static function forget( $path ) {
		unset( self::$code[ $path ] );
	}

	public function stream_open( $path, $mode, $options, &$opened_path ) {
		unset( $options );
		if ( ! in_array( $mode, array( 'r', 'rb' ), true ) || ! is_string( $path ) || ! isset( self::$code[ $path ] ) ) return false;
		$this->data  = self::$code[ $path ];
		$this->pos   = 0;
		$opened_path = $path;
		return true;
	}

	public function stream_read( $count ) {
		$chunk      = (string) substr( $this->data, $this->pos, max( 0, (int) $count ) );
		$this->pos += strlen( $chunk );
		return $chunk;
	}

	public function stream_eof() {
		return $this->pos >= strlen( $this->data );
	}

	public function stream_stat() {
		return self::stat( strlen( $this->data ) );
	}

	public function url_stat( $path, $flags ) {
		unset( $flags );
		return is_string( $path ) && isset( self::$code[ $path ] ) ? self::stat( strlen( self::$code[ $path ] ) ) : false;
	}

	public function stream_close() {
		$this->data = '';
		$this->pos  = 0;
	}

	public function stream_set_option( $option, $arg1, $arg2 ) {
		unset( $option, $arg1, $arg2 );
		return false;
	}

	/** Archivo regular de sólo lectura. */
	private static function stat( $size ) {
		$stat = array( 'dev' => 0, 'ino' => 0, 'mode' => 0100444, 'nlink' => 1, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => (int) $size, 'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1 );
		return array_merge( array_values( $stat ), $stat );
	}
}
