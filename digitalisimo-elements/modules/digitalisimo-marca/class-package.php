<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * El paquete transformado al empaquetar (docs/MARCA_PROPIA.md, opción A de DECISIONES; scripts/marca-empaquetar.mjs):
 * el ZIP publica sólo copias transformadas y renombradas de lo que se puede pedir por URL, y guarda los originales en
 * `marca/originales.php`, un archivo de datos que empieza con `<?php exit;` (pedido por URL no devuelve nada).
 *
 * - Marca propia EN VIGOR: las URL que el código registra con los nombres originales se renombran como en el paquete
 *   (`elementor` → `dg` en cada tramo de la ruta), en `plugins_url`, `style_loader_src` y `script_loader_src`.
 * - Marca APAGADA o BLOQUEADA (o el sitio aún sin decidir): los originales se despliegan una vez por versión en una
 *   carpeta de nombre neutro de la instalación, `uploads/digitalisimo-elements/base-<versión>/` (el árbol completo del
 *   núcleo sin PHP, con los originales donde cambiaron, y `p/` con los originales de PRO y de los módulos), y las URL
 *   del núcleo (`ELEMENTOR_URL`, por `plugins_url`) y de cada archivo cambiado apuntan ahí: el sitio funciona como hoy.
 *   Se despliega al actualizar (`upgrader_process_complete`, con la versión NUEVA leída del disco), al activar y, si
 *   falta, en la primera petición (con cerrojo, en una carpeta temporal que se cambia de una vez). Las carpetas de otras
 *   versiones y las de un sitio que enciende la marca no se borran enseguida: quedan marcadas (`.retirar`) y se borran
 *   un día después (las páginas en caché las siguen pidiendo), y al encender la marca se purgan las cachés conocidas.
 * - Mientras los originales no están (otra petición los está desplegando y no terminó en unos segundos, o el despliegue
 *   falló), la petición NO sirve el HTML original con los assets transformados (B-1 de la revisión F2): corre con las
 *   copias transformadas y la capa de marca (`fallback()`, coherente con lo publicado), sin caché de página. Un fallo
 *   queda anotado con espera creciente entre intentos, se avisa en el escritorio y en «Salud del sitio».
 * - Sin el paquete transformado (el repositorio, o el ZIP de siempre), nada de esto corre.
 * El modo se decide una sola vez por petición, al cargar Elements (antes que el núcleo calcule `ELEMENTOR_URL`), y es el
 * que usan después la capa del front, la del editor y `Toggle::active()` (I-4).
 */
final class Package {
	const BASE = 'digitalisimo-elements/base-';
	/** Último fallo del despliegue: versión, intentos fallidos, hora y motivo (sin carga automática). */
	const FAIL_OPTION = 'digitalisimo_elements_marca_despliegue';
	/** Una carpeta marcada para retirar se borra pasado este tiempo (segundos). */
	const RETIRE_AFTER = 86400;
	/** Cuánto espera una petición a que otra termine de desplegar (segundos). */
	const WAIT = 3.0;
	const CLEANUP_HOOK = 'digitalisimo_elements_marca_limpiar';

	/** @var bool la petición corre con las copias transformadas porque faltan los originales */
	private static $fallback = false;
	/** @var string motivo del último fallo de deploy() en esta petición */
	public static $error = '';

	/** @var array|null */
	private static $index = null;
	/** @var string|null `on`, `off` o `` (sin paquete transformado) */
	private static $mode = null;

	public static function plugin_dir(): string {
		return dirname( __DIR__, 2 );
	}

	public static function transformed(): bool {
		return is_readable( self::plugin_dir() . '/marca/indice.php' );
	}

	/** @return array<string,string|null> ruta original → ruta publicada (null: no se publica). */
	public static function index(): array {
		if ( null === self::$index ) {
			$data        = self::transformed() ? include self::plugin_dir() . '/marca/indice.php' : array();
			self::$index = is_array( $data ) ? $data : array();
		}
		return self::$index;
	}

	/** Para pruebas. */
	public static function reset( ?array $index = null, ?string $mode = null, bool $fallback = false ): void {
		self::$index    = $index;
		self::$mode     = $mode;
		self::$entries  = null;
		self::$fallback = $fallback;
		self::$error    = '';
	}

	/** ¿Ya se decidió el modo de esta petición? */
	public static function decided(): bool {
		return null !== self::$mode;
	}

	/** ¿Corre esta petición con las copias transformadas porque faltan los originales? (la marca NO está en vigor) */
	public static function fallback(): bool {
		return self::$fallback;
	}

	/** Nombre publicado de una ruta relativa al plugin (`elementor` → `dg` en cada tramo; `elementor/` → `motor/`). */
	public static function rename( string $rel ): string {
		$parts = explode( '/', $rel );
		foreach ( $parts as $i => $seg ) {
			if ( 0 === $i && ( 'elementor' === $seg || 'motor' === $seg ) ) {
				$parts[ $i ] = 'motor';
				continue;
			}
			$parts[ $i ] = (string) preg_replace_callback(
				'/elementor/i',
				static function ( $m ) {
					return 'E' === $m[0][0] ? 'Dg' : Transform::P;
				},
				$seg
			);
		}
		return implode( '/', $parts );
	}

	/** `on` (marca en vigor), `off` (originales desplegados) o `` (sin paquete transformado). */
	public static function mode(): string {
		if ( null === self::$mode ) {
			self::$mode = ! self::transformed() ? '' : ( Toggle::active() ? 'on' : 'off' );
		}
		return self::$mode;
	}

	public static function version(): string {
		return defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? (string) DIGITALISIMO_ELEMENTS_VERSION : 'x';
	}

	/** La versión del plugin que está en el disco (tras actualizar, la nueva, aunque corra el código de la anterior). */
	public static function disk_version(): string {
		$head = (string) @file_get_contents( self::plugin_dir() . '/pro-elements.php', false, null, 0, 8192 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
		return preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $head, $m ) ? $m[1] : self::version();
	}

	/** Carpeta y URL de los originales desplegados (de la instalación, una por versión). */
	public static function base( ?string $version = null ): array {
		$root = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/uploads/' : self::plugin_dir() . '/../../uploads/';
		$url  = function_exists( 'content_url' ) ? content_url( 'uploads/' ) : '/wp-content/uploads/';
		$name = self::BASE . preg_replace( '/[^0-9a-z.]/i', '', null === $version ? self::version() : $version );
		return array( 'dir' => $root . $name, 'url' => rtrim( $url, '/' ) . '/' . $name );
	}

	public static function deployed(): bool {
		return is_file( self::base()['dir'] . '/.listo' );
	}

	/** ¿La carpeta en uso quedó marcada para retirar (otro sitio de la red encendió la marca)? */
	private static function retiring(): bool {
		return is_file( self::base()['dir'] . '/.retirar' );
	}

	/** Engancha los filtros de URL; lo llama pro-elements.php antes de cargar el núcleo. */
	public static function boot(): void {
		static $done = false;
		if ( $done || '' === self::mode() ) {
			return;
		}
		$done = true;
		self::resolve();
		// Antes de `init` sin la lista de complementos en caché, la marca se decide «apagada» (I-4): se arma la lista
		// cuando todo cargó, para las peticiones siguientes.
		if ( class_exists( Addons::class ) && 'on' === Toggle::values()['mode'] && ! Toggle::values()['force'] && null === Addons::cached() ) {
			add_action( 'wp_loaded', array( Addons::class, 'detect' ) );
		}
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'health_tests' ) );
		add_filter( 'plugins_url', array( __CLASS__, 'plugins_url' ), 1, 3 );
		add_filter( 'style_loader_src', array( __CLASS__, 'asset_src' ), 1 );
		add_filter( 'script_loader_src', array( __CLASS__, 'asset_src' ), 1 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'translation_file' ), 20, 3 );
		add_filter( 'pre_load_script_translations', array( __CLASS__, 'original_translations' ), 10, 4 );
		add_filter( 'lang_dir_for_domain', array( __CLASS__, 'lang_dir' ), 20, 3 );
		add_filter( 'load_translation_file', array( __CLASS__, 'php_translation_file' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 20, 2 );
		// Marca apagada: el núcleo calcula ELEMENTOR_URL al incluirse (antes de este filtro), así que lo que arma con
		// `urls.assets` de su configuración (los trozos de webpack, las librerías) apuntaría a las copias transformadas.
		// La salida del front, del escritorio y de AJAX lleva la URL de los originales desplegados.
		if ( 'off' === self::mode() ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_off_buffer' ), 0 );
			add_action( 'admin_init', array( __CLASS__, 'start_off_buffer' ), PHP_INT_MIN );
		}
		// El JS transformado abre la vista previa del editor con `dg-preview` (D-12): se traduce antes de que el núcleo
		// mire la petición.
		if ( 'on' === self::mode() && class_exists( Editor_Layer::class ) ) {
			Editor_Layer::preview_request();
		}
	}

	/** Pantallas del escritorio que descargan, suben o redirigen: su salida no pasa por el búfer. */
	const OFF_SKIP = array( 'admin-post.php', 'async-upload.php', 'export.php', 'media-upload.php', 'download.php' );

	/**
	 * Marca apagada: que estén los originales. Si no se puede (otra petición los despliega y no terminó a tiempo, o el
	 * despliegue falló), nunca el HTML original con los assets transformados (B-1): la petición corre con las copias
	 * transformadas y la capa de marca, que son coherentes entre sí, sin caché de página.
	 */
	public static function resolve(): void {
		if ( 'off' !== self::mode() || ( self::deployed() && ! self::retiring() ) || self::ensure() ) {
			return;
		}
		self::$mode     = 'on';
		self::$fallback = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'send_headers', array( __CLASS__, 'no_cache' ), 0 );
			add_action( 'template_redirect', array( __CLASS__, 'no_cache' ), 0 );
		}
	}

	/**
	 * Que los originales estén: despliega si no hay un fallo reciente (espera creciente) y, si otra petición los está
	 * desplegando, espera unos segundos a que termine.
	 */
	public static function ensure(): bool {
		if ( self::in_backoff() ) {
			return false;
		}
		if ( self::deploy() ) {
			return true;
		}
		if ( 'cerrojo' !== self::$error ) {
			return false;
		}
		$until = microtime( true ) + self::WAIT;
		while ( microtime( true ) < $until ) {
			usleep( 100000 );
			clearstatcache();
			if ( self::deployed() ) {
				return true;
			}
		}
		return false;
	}

	/** Sin caché de página ni del navegador para una respuesta servida sin los originales. */
	public static function no_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'digitalisimo-elements: originales del constructor sin desplegar' );
		}
		if ( function_exists( 'nocache_headers' ) && ! headers_sent() ) {
			nocache_headers();
		}
	}

	/** @return array{version:string,fails:int,at:int,error:string} el último fallo anotado ('' si ninguno). */
	public static function failure(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::FAIL_OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'version' => (string) ( $raw['version'] ?? '' ),
			'fails'   => (int) ( $raw['fails'] ?? 0 ),
			'at'      => (int) ( $raw['at'] ?? 0 ),
			'error'   => (string) ( $raw['error'] ?? '' ),
		);
	}

	/** Espera tras el fallo n: 5 min, 10, 20… hasta 6 h. */
	public static function backoff( int $fails ): int {
		return (int) min( 21600, 300 * ( 2 ** max( 0, min( 7, $fails - 1 ) ) ) );
	}

	/** ¿Hay que esperar antes de reintentar? (un fallo reciente de esta misma versión) */
	public static function in_backoff( ?int $now = null ): bool {
		$f = self::failure();
		return $f['fails'] > 0 && $f['version'] === self::version() && ( $now ?? time() ) < $f['at'] + self::backoff( $f['fails'] );
	}

	private static function record_failure( string $version, string $error ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$f = self::failure();
		update_option(
			self::FAIL_OPTION,
			array(
				'version' => $version,
				'fails'   => $f['version'] === $version ? $f['fails'] + 1 : 1,
				'at'      => time(),
				'error'   => substr( $error, 0, 300 ),
			),
			false
		);
	}

	public static function start_off_buffer(): void {
		if ( ! self::deployed() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( in_array( (string) ( $GLOBALS['pagenow'] ?? '' ), self::OFF_SKIP, true ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'off_urls' ) );
	}

	/**
	 * ¿La respuesta es texto que puede llevar URL de assets (HTML, JSON, JavaScript, XML)? Una descarga (adjunto o tipo
	 * binario) sale tal cual. Sin Content-Type declarado, WordPress sirve HTML.
	 */
	public static function textual_response( ?array $headers = null ): bool {
		$headers = null === $headers ? ( function_exists( 'headers_list' ) ? headers_list() : array() ) : $headers;
		foreach ( $headers as $header ) {
			if ( 0 === stripos( $header, 'content-disposition:' ) && false !== stripos( $header, 'attachment' ) ) {
				return false;
			}
		}
		foreach ( $headers as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) ) {
				return (bool) preg_match( '#(text/html|json|javascript|xml)#i', $header );
			}
		}
		return true;
	}

	/**
	 * Con la marca apagada: `…/motor/assets/` del plugin → la carpeta de los originales (también con `\/`), y la palabra
	 * enmascarada de vuelta.
	 */
	public static function off_urls( $out ) {
		if ( ! is_string( $out ) || '' === $out || ! self::textual_response() ) {
			return $out;
		}
		// Lo que el paquete enmascara para el servidor (separadores SVG y twig: `&#101;lementor-…`) sale con la palabra,
		// como en el paquete de siempre (M-6 de la revisión F2): quien lea el HTML en el servidor (análisis de CSS usado,
		// cachés, otros plugins) ve lo mismo que hoy.
		if ( false !== strpos( $out, '&#101;lementor' ) ) {
			$out = str_replace( '&#101;lementor', 'elementor', $out );
		}
		if ( false === strpos( $out, 'motor' ) ) {
			return $out;
		}
		$from = self::plugin_url() . 'motor/assets/';
		$to   = self::base()['url'] . '/motor/assets/';
		return str_replace( array( $from, str_replace( '/', '\\/', $from ) ), array( $to, str_replace( '/', '\\/', $to ) ), $out );
	}

	/** URL base del plugin (…/plugins/<carpeta>/). */
	private static function plugin_url(): string {
		return function_exists( 'plugins_url' ) ? trailingslashit( plugins_url( '', self::plugin_dir() . '/pro-elements.php' ) ) : '';
	}

	public static function plugins_url( $url, $path = '', $plugin = '' ) {
		return is_string( $url ) ? self::map_url( $url ) : $url;
	}

	public static function asset_src( $src ) {
		return is_string( $src ) ? self::map_url( $src ) : $src;
	}

	/** Una URL del plugin según el modo. Las demás salen igual. */
	public static function map_url( string $url ): string {
		$mark = '/plugins/' . basename( self::plugin_dir() ) . '/';
		$pos  = strpos( $url, $mark );
		if ( false === $pos ) {
			return $url;
		}
		$head  = substr( $url, 0, $pos + strlen( $mark ) );
		$tail  = substr( $url, $pos + strlen( $mark ) );
		$query = '';
		if ( false !== ( $q = strcspn( $tail, '?#' ) ) && $q < strlen( $tail ) ) {
			$query = substr( $tail, $q );
			$tail  = substr( $tail, 0, $q );
		}
		$rel = 0 === strpos( $tail, 'elementor/' ) ? 'motor/' . substr( $tail, 10 ) : $tail;
		// El paquete no trae el JS ni el CSS sin minificar del núcleo que tienen su `.min` (SCRIPT_DEBUG): se sirve el `.min`.
		if ( 0 === strpos( $rel, 'motor/' ) && preg_match( '/(?<!\.min)\.(js|css)$/', $rel ) && array_key_exists( $rel, self::index() ) && null === self::index()[ $rel ] ) {
			$rel = (string) preg_replace( '/\.(js|css)$/', '.min.$1', $rel );
		}
		if ( 'on' === self::mode() ) {
			return $head . self::rename( $rel ) . $query;
		}
		if ( 'off' !== self::mode() || ! self::deployed() ) {
			return $url;
		}
		$base = self::base()['url'];
		if ( 'motor' === $rel || 0 === strpos( $rel, 'motor/' ) ) {
			return 0 === strpos( $rel, 'motor/' ) && ! preg_match( '/\.php$/', $rel ) ? $base . '/' . $rel . $query : $url;
		}
		return array_key_exists( $rel, self::index() ) ? $base . '/p/' . $rel . $query : $url;
	}

	/** Traducciones JSON de scripts del núcleo: el paquete las publica renombradas (`dg-<idioma>-<md5>.json`). */
	public static function translation_file( $file, $handle = '', $domain = '' ) {
		if ( ! is_string( $file ) || '' === $file || is_readable( $file ) ) {
			return $file;
		}
		$renamed = dirname( $file ) . '/' . self::rename( basename( $file ) );
		if ( is_readable( $renamed ) ) {
			return $renamed;
		}
		$own = self::plugin_dir() . '/motor/languages/' . self::rename( basename( $file ) );
		return is_readable( $own ) ? $own : $file;
	}

	/**
	 * Idiomas del núcleo en el paquete: `elementor-<idioma>.l10n.php` se publica como `dg-<idioma>.l10n.php` (D-8). La
	 * carpeta del núcleo vale para el dominio `elementor` si trae ese idioma con el nombre nuevo (el cargador del núcleo
	 * sólo busca el viejo).
	 */
	public static function lang_dir( $path, $domain = '', $locale = '' ) {
		if ( 'elementor' !== $domain || ! is_string( $locale ) || '' === $locale ) {
			return $path;
		}
		$own = self::plugin_dir() . '/motor/languages/';
		if ( ( $path && glob( rtrim( (string) $path, '/' ) . '/elementor-' . $locale . '.*' ) ) || ! is_readable( $own . self::rename( 'elementor-' . $locale . '.l10n.php' ) ) ) {
			return $path;
		}
		return $own;
	}

	/** `load_translation_file`: el `.l10n.php` del núcleo con su nombre publicado. */
	public static function php_translation_file( $file, $domain = '' ) {
		if ( ! is_string( $file ) || false === stripos( basename( $file ), 'elementor' ) || is_readable( $file ) ) {
			return $file;
		}
		$renamed = dirname( $file ) . '/' . self::rename( basename( $file ) );
		return is_readable( $renamed ) ? $renamed : $file;
	}

	/**
	 * Marca apagada: las traducciones de los scripts del núcleo salen del original (sus claves son las cadenas del JS
	 * original; la copia publicada las trae transformadas, como el JS transformado).
	 */
	public static function original_translations( $translations, $file = '', $handle = '', $domain = '' ) {
		if ( null !== $translations || 'off' !== self::mode() || ! is_string( $file ) || false === strpos( str_replace( '\\', '/', $file ), '/languages/' ) ) {
			return $translations;
		}
		$name = basename( $file );
		foreach ( self::index() as $original => $published ) {
			if ( is_string( $published ) && 0 === strpos( $original, 'motor/languages/' ) && ( basename( $published ) === $name || basename( $original ) === $name ) ) {
				$data = self::original( $original );
				return null !== $data ? $data : $translations;
			}
		}
		return $translations;
	}

	/** @var array|null entradas del archivo de originales (ruta => [desplazamiento, largo, tamaño, sha256]) */
	private static $entries = null;
	/** @var int */
	private static $entries_start = 0;

	/** Lee un original del archivo de datos (el índice del archivo se lee una vez por petición). */
	public static function original( string $rel ): ?string {
		$archive = self::plugin_dir() . '/marca/originales.php';
		$h       = @fopen( $archive, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
		if ( ! $h ) {
			return null;
		}
		if ( null === self::$entries ) {
			fgets( $h );
			self::$entries = array();
			foreach ( (array) json_decode( (string) fgets( $h ), true ) as $e ) {
				self::$entries[ $e[0] ] = $e;
			}
			self::$entries_start = (int) ftell( $h );
		}
		$out = null;
		if ( isset( self::$entries[ $rel ] ) ) {
			$e = self::$entries[ $rel ];
			fseek( $h, self::$entries_start + (int) $e[1] );
			$data = gzinflate( (string) fread( $h, (int) $e[2] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$out  = false !== $data && hash( 'sha256', $data ) === $e[4] ? $data : null;
		}
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $out;
	}

	/**
	 * Despliega los originales (una vez por versión, con cerrojo): el árbol del núcleo sin PHP ni idiomas, con el original
	 * donde el paquete lo transformó, y `p/` con los originales de PRO y de los módulos. Escribe en una carpeta temporal y
	 * la cambia de una vez (nadie ve una carpeta a medias). Se corta en el primer error, que queda anotado (espera
	 * creciente antes del siguiente intento). Tras desplegar, las carpetas de otras versiones quedan para retirar.
	 * Devuelve si quedó desplegado; si no, el motivo en `$error` (`cerrojo`: otra petición está desplegando).
	 */
	public static function deploy( ?string $version = null ): bool {
		self::$error = '';
		if ( ! self::transformed() ) {
			return false;
		}
		$version = null === $version ? self::version() : $version;
		$base    = self::base( $version );
		if ( is_file( $base['dir'] . '/.listo' ) ) {
			if ( is_file( $base['dir'] . '/.retirar' ) ) {
				@unlink( $base['dir'] . '/.retirar' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- vuelve a estar en uso.
			}
			return true;
		}
		$lock = $base['dir'] . '.lock';
		if ( ! is_dir( dirname( $lock ) ) && ! @mkdir( dirname( $lock ), 0755, true ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			self::$error = 'no se puede escribir en ' . dirname( $lock );
			self::record_failure( $version, self::$error );
			return false;
		}
		if ( ! @mkdir( $lock, 0755 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- el cerrojo: otra petición ya despliega.
			clearstatcache();
			$stale = is_dir( $lock ) && time() - (int) @filemtime( $lock ) > 600; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			// Un cerrojo abandonado (petición cortada) se quita y se toma; si no se puede crear, es que no hay permiso.
			if ( ! ( $stale ? @rmdir( $lock ) && @mkdir( $lock, 0755 ) : false ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_dir( $lock ) && ! $stale ) {
					self::$error = 'cerrojo';
				} else {
					self::$error = 'no se puede escribir en ' . dirname( $lock );
					self::record_failure( $version, self::$error );
				}
				return false;
			}
		}
		// Con el cerrojo nadie más despliega: las carpetas temporales que queden son de peticiones cortadas.
		foreach ( (array) glob( dirname( $base['dir'] ) . '/base-*.tmp-*', GLOB_ONLYDIR ) as $orphan ) {
			self::rmdir( (string) $orphan );
		}
		$tmp = $base['dir'] . '.tmp-' . getmypid();
		$ok  = true;
		try {
			$published = array_flip( array_filter( self::index(), 'is_string' ) );
			$plugin    = self::plugin_dir();
			$iterator  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $plugin . '/motor', \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				$rel = 'motor/' . str_replace( '\\', '/', substr( (string) $file, strlen( $plugin . '/motor/' ) ) );
				if ( preg_match( '/\.php$/', $rel ) || 0 === strpos( $rel, 'motor/languages/' ) ) {
					continue;
				}
				$original = $published[ $rel ] ?? $rel;
				$data     = isset( $published[ $rel ] ) ? self::original( $original ) : @file_get_contents( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged
				if ( null === $data || false === $data ) {
					throw new \RuntimeException( 'original dañado o ilegible: ' . $original );
				}
				if ( ! self::write( $tmp . '/' . $original, (string) $data ) ) {
					throw new \RuntimeException( 'no se pudo escribir ' . $original . ' (¿permisos o disco lleno?)' );
				}
			}
			foreach ( self::index() as $original => $new ) {
				if ( 0 === strpos( $original, 'motor/' ) || ! preg_match( '/\.(css|js|svg|json|woff2?|ttf|eot|png|jpe?g|gif|webp)$/i', $original ) ) {
					continue;
				}
				$data = self::original( $original );
				if ( null === $data ) {
					throw new \RuntimeException( 'original dañado o ilegible: ' . $original );
				}
				if ( preg_match( '/\.css$/i', $original ) ) {
					$data = self::absolute_urls( $data, $original, $base['url'] );
				}
				if ( ! self::write( $tmp . '/p/' . $original, $data ) ) {
					throw new \RuntimeException( 'no se pudo escribir ' . $original . ' (¿permisos o disco lleno?)' );
				}
			}
			if ( false === file_put_contents( $tmp . '/.listo', $version ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				throw new \RuntimeException( 'no se pudo escribir .listo' );
			}
			self::rmdir( $base['dir'] );
			if ( ! @rename( $tmp, $base['dir'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				throw new \RuntimeException( 'no se pudo mover la carpeta temporal a ' . $base['dir'] );
			}
		} catch ( \Throwable $e ) {
			$ok          = false;
			self::$error = $e->getMessage();
		}
		if ( ! $ok ) {
			self::rmdir( $tmp );
			self::record_failure( $version, self::$error );
		} else {
			if ( function_exists( 'delete_option' ) && '' !== self::failure()['version'] ) {
				delete_option( self::FAIL_OPTION );
			}
			self::retire_others( $base['dir'] );
		}
		@rmdir( $lock ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $ok;
	}

	/**
	 * Las carpetas de originales que no son `$keep` quedan para retirar (`.retirar` con la hora) y se borran las que ya
	 * cumplieron RETIRE_AFTER. Con `$keep` vacío, todas (al encender la marca).
	 */
	public static function retire_others( string $keep = '', ?int $now = null ): void {
		$now  = $now ?? time();
		$wait = false;
		foreach ( (array) glob( dirname( self::base()['dir'] ) . '/base-*', GLOB_ONLYDIR ) as $dir ) {
			$dir = (string) $dir;
			if ( $dir === $keep || preg_match( '/\.(tmp-\d+|lock)$/', $dir ) ) {
				continue;
			}
			$mark = $dir . '/.retirar';
			if ( ! is_file( $mark ) ) {
				@file_put_contents( $mark, (string) $now ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
				$wait = true;
			} elseif ( $now - (int) @file_get_contents( $mark ) >= self::RETIRE_AFTER ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
				self::rmdir( $dir );
			} else {
				$wait = true;
			}
		}
		if ( $wait && function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_single_event( $now + self::RETIRE_AFTER + 60, self::CLEANUP_HOOK );
		}
	}

	/** Evento programado: borra las carpetas marcadas que ya cumplieron su plazo (las que vuelven a estar en uso, no). */
	public static function cleanup( ?int $now = null ): void {
		$now  = $now ?? time();
		$wait = false;
		// Temporales de un despliegue cortado hace más de una hora (el que está en curso lleva su cerrojo y es reciente).
		foreach ( (array) glob( dirname( self::base()['dir'] ) . '/base-*.tmp-*', GLOB_ONLYDIR ) as $orphan ) {
			if ( $now - (int) @filemtime( (string) $orphan ) > 3600 ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::rmdir( (string) $orphan );
			}
		}
		foreach ( (array) glob( dirname( self::base()['dir'] ) . '/base-*/.retirar' ) as $mark ) {
			if ( $now - (int) @file_get_contents( (string) $mark ) >= self::RETIRE_AFTER ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
				self::rmdir( dirname( (string) $mark ) );
			} else {
				$wait = true;
			}
		}
		if ( $wait && function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( $now + 3600, self::CLEANUP_HOOK );
		}
	}

	/** En la copia de una hoja de PRO o de un módulo, los url() relativos apuntan al plugin (o a otra copia). */
	private static function absolute_urls( string $css, string $original, ?string $base = null ): string {
		$plugin = self::plugin_url();
		$base   = null === $base ? self::base()['url'] : $base;
		$index  = self::index();
		return (string) preg_replace_callback(
			'#url\(\s*([\'"]?)(?!data:|https?:|//|\#|/)([^\'")]+)\1\s*\)#i',
			static function ( $m ) use ( $original, $plugin, $base, $index ) {
				$path   = explode( '?', $m[2], 2 );
				$target = self::normalize( dirname( $original ) . '/' . $path[0] );
				$rest   = isset( $path[1] ) ? '?' . $path[1] : '';
				$url    = array_key_exists( $target, $index ) ? $base . '/p/' . $target : $plugin . $target;
				return 'url(' . $m[1] . $url . $rest . $m[1] . ')';
			},
			$css
		);
	}

	private static function normalize( string $path ): string {
		$out = array();
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $out );
			} elseif ( '' !== $seg && '.' !== $seg ) {
				$out[] = $seg;
			}
		}
		return implode( '/', $out );
	}

	private static function write( string $file, string $data ): bool {
		if ( ! is_dir( dirname( $file ) ) && ! @mkdir( dirname( $file ), 0755, true ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}
		return false !== file_put_contents( $file, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public static function rmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? @rmdir( (string) $f ) : @unlink( (string) $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Tras actualizar Elements: los originales de la versión NUEVA quedan listos antes de la primera visita (B-3). Este
	 * gancho corre con el código de la versión anterior, así que la versión se lee del disco y el índice y el archivo de
	 * originales se vuelven a leer. Las carpetas de las versiones anteriores quedan para retirar.
	 */
	public static function after_update( $upgrader = null, $extra = array() ) {
		if ( ! is_array( $extra ) || 'plugin' !== ( $extra['type'] ?? '' ) ) {
			return;
		}
		$mine    = basename( self::plugin_dir() ) . '/pro-elements.php';
		$plugins = isset( $extra['plugins'] ) ? (array) $extra['plugins'] : ( isset( $extra['plugin'] ) ? array( $extra['plugin'] ) : array() );
		if ( $plugins && ! in_array( $mine, $plugins, true ) ) {
			return;
		}
		self::$index   = null;
		self::$entries = null;
		if ( ! self::transformed() ) {
			return;
		}
		// Con la marca en vigor no hacen falta; en el respaldo (fallback) o apagada, sí.
		if ( 'on' === self::mode() && ! self::$fallback ) {
			return;
		}
		self::deploy( self::disk_version() );
	}

	/**
	 * Al encender la marca propia, los originales desplegados se borran (los de toda versión). La carpeta es una por
	 * instalación (`wp-content/uploads/digitalisimo-elements/base-<versión>/`): en una red la comparten todos los sitios,
	 * así que sólo se borra si en todos la marca propia queda encendida (`network_all_on()`). Si después un sitio la apaga
	 * o queda bloqueado, su primera petición los vuelve a desplegar (`boot()`): borrar nunca deja un sitio roto.
	 */
	public static function remove_originals( ?int $now = null ): bool {
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! self::network_all_on() ) {
			return false;
		}
		// No se borran enseguida (I-6): las páginas en caché de una caché de página o de un CDN siguen pidiendo esos
		// archivos. Se purgan las cachés conocidas y las carpetas se borran un día después (`cleanup()`).
		self::retire_others( '', $now );
		self::purge_caches();
		return true;
	}

	/** Purga las cachés de página conocidas (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache). */
	public static function purge_caches(): void {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all', 'digitalisimo-elements: marca propia' );
		}
		foreach ( array( 'rocket_clean_domain', 'w3tc_flush_all', 'wp_cache_clear_cache' ) as $fn ) {
			if ( function_exists( $fn ) ) {
				try {
					$fn();
				} catch ( \Throwable $e ) {
					continue;
				}
			}
		}
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && is_object( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
		}
	}

	/**
	 * ¿Queda la marca propia EN VIGOR en todos los sitios de la red? (estado de cada sitio: con su valor propio o heredado
	 * de la red, y ni bloqueada por complementos ni no disponible). Con más de `$limit` sitios no se recorre y se responde
	 * que no: los originales se quedan.
	 */
	public static function network_all_on( int $limit = 500 ): bool {
		if ( ! function_exists( 'get_sites' ) || ! function_exists( 'switch_to_blog' ) ) {
			return false;
		}
		$sites = get_sites(
			array(
				'fields'   => 'ids',
				'number'   => $limit + 1,
				'deleted'  => 0,
				'archived' => 0,
			)
		);
		if ( count( $sites ) > $limit ) {
			return false;
		}
		$all = true;
		foreach ( $sites as $id ) {
			switch_to_blog( (int) $id );
			Toggle::reset();
			// El estado en vigor, no sólo el valor guardado (I-5): bloqueada o no disponible, el sitio usa los originales.
			$all = 'on' === Toggle::status()['state'];
			restore_current_blog();
			if ( ! $all ) {
				break;
			}
		}
		Toggle::reset();
		return $all;
	}

	/** Aviso en el escritorio a quien puede arreglarlo: el despliegue de los originales falló. */
	public static function notice(): void {
		$f = self::failure();
		if ( $f['fails'] < 1 || $f['version'] !== self::version() || self::deployed() || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$text = '<p><strong>' . esc_html( 'DIGITALÍSIMO Elements no pudo preparar los archivos originales del constructor.' ) . '</strong> '
			. esc_html( 'Motivo: ' . $f['error'] . '. Mientras tanto el sitio funciona con su versión con marca propia y se reintenta solo cada cierto tiempo. Revisa los permisos y el espacio de wp-content/uploads.' ) . '</p>';
		// Con la X (AGENTS.md, avisos que se pueden quitar): cerrado, no vuelve hasta que cambie la situación (otra versión u
		// otro motivo de fallo; `snooze` 0).
		if ( class_exists( 'Digitalisimo_Notices' ) ) {
			\Digitalisimo_Notices::show( 'elements-marca-despliegue', 'error', $text, array( 'hash' => $f['version'] . '|' . $f['error'], 'cap' => 'manage_options', 'snooze' => 0 ) );
		} else {
			echo '<div class="notice notice-error is-dismissible">' . $text . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
		}
	}

	/** «Salud del sitio»: los originales desplegados y la huella del paquete. */
	public static function health_tests( $tests ) {
		if ( is_array( $tests ) ) {
			$tests['direct']['digitalisimo_elements_marca_paquete'] = array(
				'label' => 'Archivos del constructor de DIGITALÍSIMO Elements',
				'test'  => array( __CLASS__, 'health' ),
			);
		}
		return $tests;
	}

	/** @return array resultado de «Salud del sitio» */
	public static function health(): array {
		$f      = self::failure();
		$tree   = self::tree_ok();
		$status = 'good';
		$lines  = array();
		if ( 'off' === self::mode() || self::$fallback ) {
			if ( self::deployed() ) {
				$lines[] = 'Los archivos originales del constructor están listos en ' . self::base()['dir'] . '.';
			} else {
				$status  = 'critical';
				$lines[] = 'Faltan los archivos originales del constructor: el sitio se muestra con su versión con marca propia. ' . ( $f['fails'] ? 'Último error: ' . $f['error'] . '.' : 'Se preparan en la próxima visita.' );
			}
		}
		if ( false === $tree ) {
			$status  = 'critical';
			$lines[] = 'Los archivos del plugin no coinciden con los del paquete publicado (alguien los cambió): vuelve a instalar DIGITALÍSIMO Elements.';
		} elseif ( true === $tree ) {
			$lines[] = 'Los archivos del plugin coinciden con los del paquete publicado.';
		}
		return array(
			'label'       => 'critical' === $status ? 'Hay un problema con los archivos del constructor' : 'Los archivos del constructor están bien',
			'status'      => $status,
			'badge'       => array( 'label' => 'DIGITALÍSIMO', 'color' => 'critical' === $status ? 'red' : 'blue' ),
			'description' => '<p>' . esc_html( implode( ' ', $lines ) ) . '</p>',
			'test'        => 'digitalisimo_elements_marca_paquete',
		);
	}

	/** ¿El árbol publicado es el del paquete? (huella de marca/arbol.php). Lo usa «Salud del sitio». */
	public static function tree_ok(): ?bool {
		$file = self::plugin_dir() . '/marca/arbol.php';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$want = include $file;
		$root = self::plugin_dir();
		$list = array();
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$rel = str_replace( '\\', '/', substr( (string) $f, strlen( $root ) + 1 ) );
			if ( 'marca/arbol.php' !== $rel ) {
				$list[ $rel ] = hash_file( 'sha256', (string) $f );
			}
		}
		ksort( $list, SORT_STRING );
		$text = '';
		foreach ( $list as $rel => $sum ) {
			$text .= $sum . '  ' . $rel . "\n";
		}
		return is_array( $want ) && hash( 'sha256', $text ) === ( $want['tree_sha256'] ?? '' ) && count( $list ) === (int) ( $want['files'] ?? -1 );
	}
}
