<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Proxy de medios del contexto de importación (§5.4, CR-20, CC-2, D13).
 *
 * - Sirve desde la carpeta preparada las URL de `media.json` que estén bajo `uploads` o `home` del origen, y sólo si
 *   las pide el importador (imágenes de plantillas, WXR, fuentes e iconos, o el propio K). Con `stream` escribe el
 *   archivo en `filename` y responde con su `content-length` exacto: el WXR comprueba el tamaño (V-19).
 * - Bloquea, con los dos núcleos, los dominios del proveedor (`Digitalisimo_Elements_Vendor_Free::vendor_url()`,
 *   V-32) y `*.wordpress.org`.
 * - Lo demás sale dentro de los presupuestos de D13: tiempo por descarga, redirecciones, bytes por sesión y segundos
 *   de red acumulados en la petición (CV-23). Agotado un presupuesto, `WP_Error`.
 */
final class Media_Proxy {
	/** Clases y funciones del importador que pueden recibir un medio del paquete. */
	const IMPORTERS = array(
		'Elementor\\TemplateLibrary\\Classes\\Import_Images',
		'Elementor\\Core\\Utils\\ImportExport\\WP_Import',
		'ElementorPro\\Modules\\AssetsManager\\AssetTypes\\',
		'Digitalisimo\\Elements\\Import\\',
	);

	/** @var array[]|null URL original => `{ file, sha256, mime, bytes }`; null hasta que se lee. */
	private static $media = null;

	/** @var string Carpeta preparada real, con barra final. */
	private static $staged = '';

	/** @var string[] Prefijos del origen. */
	private static $prefixes = array();

	/** @var int Bytes ya descargados en sesiones anteriores. */
	private static $previous = 0;

	/** @var int Bytes descargados en esta petición. */
	private static $bytes = 0;

	/** @var float Segundos de red de esta petición. */
	private static $seconds = 0.0;

	/** @var float[] URL => inicio. */
	private static $started = array();

	/** @var array Límites vigentes. */
	private static $limits = array();

	/** @var float Inicio de la petición (o de la activación del contexto). */
	private static $request_start = 0.0;

	/** Prepara el proxy para una sesión (lo llama `Context::activate()`). */
	public static function reset( array $record ): void {
		self::$media    = null;
		self::$staged   = '';
		self::$bytes    = 0;
		self::$seconds  = 0.0;
		self::$started  = array();
		self::$limits   = Zip_Guard::limits();
		self::$request_start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) && is_numeric( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		self::$previous = isset( $record['downloaded'] ) ? (int) $record['downloaded'] : 0;
		self::$prefixes = array();
		foreach ( array( 'uploads', 'home' ) as $key ) {
			$prefix = isset( $record['origin'][ $key ] ) && is_string( $record['origin'][ $key ] ) ? rtrim( $record['origin'][ $key ], '/' ) : '';
			if ( '' !== $prefix ) {
				self::$prefixes[] = $prefix . '/';
			}
		}
		$dir = Session::staged_dir( $record );
		if ( null !== $dir ) {
			self::$staged = $dir;
		}
	}

	/** Bytes descargados en esta petición; vuelve a cero. */
	public static function take_downloaded(): int {
		$bytes       = self::$bytes;
		self::$bytes = 0;
		return $bytes;
	}

	/** Medios del paquete preparados para la sesión. */
	public static function media(): array {
		if ( null === self::$media ) {
			self::$media = array();
			if ( '' !== self::$staged && is_file( self::$staged . 'media.json' ) ) {
				$data = json_decode( (string) file_get_contents( self::$staged . 'media.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo propio.
				foreach ( is_array( $data ) ? $data : array() as $url => $item ) {
					if ( is_string( $url ) && is_array( $item ) && isset( $item['file'] ) && is_string( $item['file'] ) && 1 === preg_match( Module::MEDIA_FILE_PATTERN, $item['file'] ) ) {
						self::$media[ $url ] = $item;
					}
				}
			}
		}
		return self::$media;
	}

	/** Archivo preparado de una URL del paquete, o null. */
	public static function local_file( string $url ): ?string {
		$media = self::media();
		if ( ! isset( $media[ $url ] ) || '' === self::$staged || ! self::under_origin( $url ) ) {
			return null;
		}
		$real = realpath( self::$staged . 'media/' . $media[ $url ]['file'] );
		if ( false === $real || ! is_file( $real ) || 0 !== strpos( str_replace( '\\', '/', $real ), self::$staged ) ) {
			return null;
		}
		return $real;
	}

	/** ¿Está la URL bajo `uploads` o `home` del origen? */
	private static function under_origin( string $url ): bool {
		foreach ( self::$prefixes as $prefix ) {
			if ( 0 === strpos( $url, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * `pre_http_request` (1).
	 *
	 * @param false|array|\WP_Error $pre  Respuesta previa.
	 * @param array                 $args Argumentos de la petición.
	 * @param string                $url  URL.
	 *
	 * @return false|array|\WP_Error
	 */
	public static function pre_http( $pre, $args = array(), $url = '' ) {
		if ( false !== $pre ) {
			return $pre;
		}
		$url = (string) $url;
		if ( self::blocked( $url ) ) {
			Context::report( 'media.blocked', array( 'host' => (string) wp_parse_url( $url, PHP_URL_HOST ) ), Part_Context::INFO );
			return new \WP_Error( 'digitalisimo_kit_blocked', 'Durante la importación de un kit no se conecta con servicios externos del constructor ni con wordpress.org.' );
		}
		$file = self::local_file( $url );
		if ( null !== $file && self::from_importer() ) {
			Context::tally( 'media.served' );
			return self::serve( $url, $file, is_array( $args ) ? $args : array() );
		}
		if ( self::$previous + self::$bytes >= self::limit( 'session_download' ) ) {
			Context::report( 'media.budget', array( 'url' => $url ), Part_Context::WARNING );
			return new \WP_Error( 'digitalisimo_kit_budget', 'Se alcanzó el máximo de descargas de esta importación.' );
		}
		if ( self::$seconds >= self::limit( 'request_network' ) || self::elapsed() >= self::limit( 'request_media' ) ) {
			// A13: lo que no cabe en el presupuesto de la petición queda «no descargado (tiempo)» en el informe. Cuenta
			// también el tiempo de la petición, no sólo el de red: tras cada descarga el núcleo procesa la imagen.
			Context::report( 'media.time', array( 'url' => $url ), Part_Context::WARNING );
			return new \WP_Error( 'digitalisimo_kit_time', 'No descargado (tiempo): se alcanzó el tiempo de red de esta petición.' );
		}
		self::$started[ $url ] = microtime( true );
		return false;
	}

	/** `http_request_args` (10): tiempo, redirecciones y tamaño por descarga (D13). */
	public static function http_args( $args, $url = '' ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$left                = max( 1, (int) ceil( min( self::limit( 'request_network' ) - self::$seconds, self::limit( 'request_media' ) - self::elapsed() ) ) );
		$args['timeout']     = min( isset( $args['timeout'] ) ? (float) $args['timeout'] : 5.0, (float) self::limit( 'download_timeout' ), (float) $left );
		$args['redirection'] = min( isset( $args['redirection'] ) ? (int) $args['redirection'] : 5, (int) self::limit( 'redirects' ) );
		$size                = (int) self::limit( 'file' );
		if ( empty( $args['limit_response_size'] ) || (int) $args['limit_response_size'] > $size ) {
			$args['limit_response_size'] = $size;
		}
		return $args;
	}

	/** `http_api_debug` (10): mide el tiempo y los bytes de lo que salió a la red. */
	public static function measure( $response, $context = '', $class = '', $args = array(), $url = '' ): void {
		$url = (string) $url;
		if ( 'response' !== $context || ! isset( self::$started[ $url ] ) ) {
			return;
		}
		self::$seconds += max( 0.0, microtime( true ) - self::$started[ $url ] );
		unset( self::$started[ $url ] );
		if ( is_array( $response ) ) {
			Context::tally( 'media.downloaded' );
			if ( ! empty( $response['filename'] ) && is_string( $response['filename'] ) && is_file( $response['filename'] ) ) {
				self::$bytes += (int) filesize( $response['filename'] );
			} elseif ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
				self::$bytes += strlen( $response['body'] );
			}
		}
	}

	/** `import_attachment_size_limit` (10): tope por archivo del WXR. */
	public static function file_limit( $limit = 0 ): int {
		$own = (int) self::limit( 'file' );
		return $limit > 0 ? min( (int) $limit, $own ) : $own;
	}

	/**
	 * Segundos desde el inicio de la petición. En WP-CLI toda la importación es un solo proceso sin tope de tiempo:
	 * allí sólo cuenta el tiempo de red.
	 */
	public static function elapsed(): float {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 0.0;
		}
		$start = self::$request_start > 0 ? self::$request_start : microtime( true );
		return max( 0.0, microtime( true ) - $start );
	}

	/**
	 * Fija el inicio de la petición.
	 *
	 * @internal Para pruebas.
	 */
	public static function set_request_start( float $start ): void {
		self::$request_start = $start;
	}

	/** Un límite de D13 (se leen al activar; sin activar, los vigentes). */
	private static function limit( string $key ): int {
		if ( ! self::$limits ) {
			self::$limits = Zip_Guard::limits();
		}
		return (int) self::$limits[ $key ];
	}

	/** ¿Va la URL a un dominio del proveedor o a `*.wordpress.org`? Vale con los dos núcleos. */
	public static function blocked( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		if ( 'wordpress.org' === $host || '.wordpress.org' === substr( $host, -14 ) ) {
			return true;
		}
		return class_exists( '\Digitalisimo_Elements_Vendor_Free' ) && \Digitalisimo_Elements_Vendor_Free::vendor_url( $url );
	}

	/** ¿Pide el medio el importador? Decide la pila de llamadas (CR-20). */
	private static function from_importer(): bool {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- sólo identifica al llamador.
			$class = isset( $frame['class'] ) ? (string) $frame['class'] : '';
			if ( '' === $class || __CLASS__ === $class ) {
				continue;
			}
			foreach ( self::IMPORTERS as $importer ) {
				if ( 0 === strpos( $class, $importer ) ) {
					return true;
				}
			}
			if ( isset( $frame['function'] ) && 'create_attachment_from_url' === $frame['function'] ) {
				return true;
			}
		}
		return false;
	}

	/** Respuesta con el archivo preparado: en `filename` si la petición es en flujo, o en el cuerpo. */
	private static function serve( string $url, string $file, array $args ) {
		$media = self::media()[ $url ];
		$bytes = (int) filesize( $file );
		$mime  = isset( $media['mime'] ) && is_string( $media['mime'] ) && '' !== $media['mime'] ? $media['mime'] : 'application/octet-stream';
		$body  = '';
		$path  = null;
		if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) && is_string( $args['filename'] ) ) {
			if ( ! copy( $file, $args['filename'] ) ) {
				return new \WP_Error( 'digitalisimo_kit_media', 'No se pudo copiar un medio del kit.' );
			}
			$path = $args['filename'];
		} else {
			$body = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo preparado propio.
		}
		return array(
			'headers'  => array(
				'content-type'   => $mime,
				'content-length' => (string) $bytes,
			),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $path,
		);
	}
}
