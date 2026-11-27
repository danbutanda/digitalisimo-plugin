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
		self::$index   = $index;
		self::$mode    = $mode;
		self::$entries = null;
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

	/** Con la marca apagada: `…/motor/assets/` del plugin → la carpeta de los originales (también con `\/`). */
	public static function off_urls( $out ) {
		if ( ! is_string( $out ) || '' === $out || false === strpos( $out, 'motor' ) || ! self::textual_response() ) {
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

	/**
	 * Al encender la marca propia, los originales desplegados se borran (los de toda versión). La carpeta es una por
	 * instalación (`wp-content/uploads/digitalisimo-elements/base-<versión>/`): en una red la comparten todos los sitios,
	 * así que sólo se borra si en todos la marca propia queda encendida (`network_all_on()`). Si después un sitio la apaga
	 * o queda bloqueado, su primera petición los vuelve a desplegar (`boot()`): borrar nunca deja un sitio roto.
	 */
	public static function remove_originals(): bool {
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! self::network_all_on() ) {
			return false;
		}
		foreach ( (array) glob( dirname( self::base()['dir'] ) . '/base-*', GLOB_ONLYDIR ) as $dir ) {
			self::rmdir( (string) $dir );
		}
		return true;
	}

	/**
	 * ¿Queda la marca propia en «on» en todos los sitios de la red? (valores en vigor de cada sitio: propio o heredado de
	 * la red). Con más de `$limit` sitios no se recorre y se responde que no: los originales se quedan.
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
			$all = 'on' === Toggle::values()['mode'];
			restore_current_blog();
			if ( ! $all ) {
				break;
			}
		}
		Toggle::reset();
		return $all;
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
