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
 *   Se despliega al actualizar (`upgrader_process_complete`), al activar y, si falta, en la primera petición (con
 *   cerrojo). Al encender la marca propia en un sitio individual se borra; en una red, desde la red.
 * - Sin el paquete transformado (el repositorio, o el ZIP de siempre), nada de esto corre.
 * El modo se decide una sola vez por petición, al cargar Elements (antes que el núcleo calcule `ELEMENTOR_URL`).
 */
final class Package {
	const BASE = 'digitalisimo-elements/base-';

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
	public static function reset( ?array $index = null, ?string $mode = null ): void {
		self::$index = $index;
		self::$mode  = $mode;
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

	/** Carpeta y URL de los originales desplegados (de la instalación, una por versión). */
	public static function base(): array {
		$root = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/uploads/' : self::plugin_dir() . '/../../uploads/';
		$url  = function_exists( 'content_url' ) ? content_url( 'uploads/' ) : '/wp-content/uploads/';
		$name = self::BASE . preg_replace( '/[^0-9a-z.]/i', '', self::version() );
		return array( 'dir' => $root . $name, 'url' => rtrim( $url, '/' ) . '/' . $name );
	}

	public static function deployed(): bool {
		return is_file( self::base()['dir'] . '/.listo' );
	}

	/** Engancha los filtros de URL; lo llama pro-elements.php antes de cargar el núcleo. */
	public static function boot(): void {
		static $done = false;
		if ( $done || '' === self::mode() ) {
			return;
		}
		$done = true;
		if ( 'off' === self::mode() && ! self::deployed() ) {
			self::deploy();
		}
		add_filter( 'plugins_url', array( __CLASS__, 'plugins_url' ), 1, 3 );
		add_filter( 'style_loader_src', array( __CLASS__, 'asset_src' ), 1 );
		add_filter( 'script_loader_src', array( __CLASS__, 'asset_src' ), 1 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'translation_file' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_update' ), 20, 2 );
		// El JS transformado abre la vista previa del editor con `dg-preview` (D-12): se traduce antes de que el núcleo
		// mire la petición.
		if ( 'on' === self::mode() && class_exists( Editor_Layer::class ) ) {
			Editor_Layer::preview_request();
		}
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

	/** Lee un original del archivo de datos. */
	public static function original( string $rel ): ?string {
		$archive = self::plugin_dir() . '/marca/originales.php';
		$h       = @fopen( $archive, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions
		if ( ! $h ) {
			return null;
		}
		fgets( $h );
		$entries = json_decode( (string) fgets( $h ), true );
		$start   = ftell( $h );
		$out     = null;
		foreach ( (array) $entries as $e ) {
			if ( $rel === $e[0] ) {
				fseek( $h, $start + (int) $e[1] );
				$data = gzinflate( (string) fread( $h, (int) $e[2] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$out  = false !== $data && hash( 'sha256', $data ) === $e[4] ? $data : null;
				break;
			}
		}
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $out;
	}

	/**
	 * Despliega los originales (una vez por versión, con cerrojo): el árbol del núcleo sin PHP ni idiomas, con el original
	 * donde el paquete lo transformó, y `p/` con los originales de PRO y de los módulos. Escribe en una carpeta temporal y
	 * la cambia de una vez. Devuelve si quedó desplegado.
	 */
	public static function deploy(): bool {
		if ( ! self::transformed() ) {
			return false;
		}
		$base = self::base();
		if ( is_file( $base['dir'] . '/.listo' ) ) {
			return true;
		}
		$lock = $base['dir'] . '.lock';
		if ( ! is_dir( dirname( $lock ) ) && ! @mkdir( dirname( $lock ), 0755, true ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}
		if ( ! @mkdir( $lock, 0755 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- el cerrojo: otra petición ya despliega.
			if ( is_dir( $lock ) && time() - (int) filemtime( $lock ) > 600 ) {
				@rmdir( $lock ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- cerrojo abandonado.
			}
			return false;
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
				$data     = isset( $published[ $rel ] ) ? self::original( $original ) : (string) file_get_contents( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$ok       = $ok && null !== $data && self::write( $tmp . '/' . $original, (string) $data );
			}
			foreach ( self::index() as $original => $new ) {
				if ( 0 === strpos( $original, 'motor/' ) || ! preg_match( '/\.(css|js|svg|json|woff2?|ttf|eot|png|jpe?g|gif|webp)$/i', $original ) ) {
					continue;
				}
				$data = self::original( $original );
				if ( null !== $data && preg_match( '/\.css$/i', $original ) ) {
					$data = self::absolute_urls( $data, $original );
				}
				$ok = $ok && null !== $data && self::write( $tmp . '/p/' . $original, $data );
			}
			$ok = $ok && false !== file_put_contents( $tmp . '/.listo', self::version() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $ok ) {
				self::rmdir( $base['dir'] );
				$ok = rename( $tmp, $base['dir'] );
			}
		} catch ( \Throwable $e ) {
			$ok = false;
		}
		if ( ! $ok ) {
			self::rmdir( $tmp );
		}
		@rmdir( $lock ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $ok;
	}

	/** En la copia de una hoja de PRO o de un módulo, los url() relativos apuntan al plugin (o a otra copia). */
	private static function absolute_urls( string $css, string $original ): string {
		$plugin = self::plugin_url();
		$base   = self::base()['url'];
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

	/** Tras actualizar Elements: los originales de la versión nueva quedan listos antes de la primera visita. */
	public static function after_update( $upgrader = null, $extra = array() ) {
		if ( is_array( $extra ) && 'plugin' === ( $extra['type'] ?? '' ) ) {
			self::deploy();
		}
	}

	/** Al encender la marca propia en un sitio individual, los originales desplegados se borran (los de toda versión). */
	public static function remove_originals(): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return;
		}
		foreach ( (array) glob( dirname( self::base()['dir'] ) . '/base-*', GLOB_ONLYDIR ) as $dir ) {
			self::rmdir( (string) $dir );
		}
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
