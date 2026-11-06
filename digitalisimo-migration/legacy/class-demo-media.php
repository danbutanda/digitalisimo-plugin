<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Imágenes de demostración de Element Pack y de Prime Slider (F8-D17, §3.E.5, V.10.3, decisión 4 del propietario).
 *
 * Los valores por defecto de varios widgets apuntan a la carpeta del plugin (`…/plugins/bdthemes-prime-slider-lite/
 * assets/images/…`, `…/plugins/bdthemes-element-pack-lite/assets/images/coco-can.svg`), que da 404 en cuanto se borra.
 * Cada URL de un valor (guardado o rellenado) bajo la carpeta de un plugin de una familia conocida (instalado, activo o
 * no) se copia a la biblioteca de medios del sitio y el valor pasa a ser el adjunto (`{id, url}`), nunca la carpeta del
 * plugin. Nada fuera de `assets/images/` del plugin se copia. Familias: Prime Slider (`ps`, su convertidor) y Element
 * Pack (`ep`, `Native::widget()` tras traducir cada widget).
 *
 * - **Base:** `realpath( <carpeta del plugin>/assets/images )`; la ruta del archivo se descodifica (`%2e`…), no admite
 *   `..`, `.`, barras invertidas ni caracteres fuera de `[A-Za-z0-9._/-]`, y su `realpath` debe ser exactamente la
 *   ruta pedida dentro de la base (sin enlaces simbólicos).
 * - **Tipos:** `png`, `jpg`, `jpeg`, `gif`, `webp` y `svg`, comprobados con `wp_check_filetype_and_ext()`;
 *   `getimagesize()` en los de mapa de bits; 5 MB como mucho.
 * - **SVG:** sólo con la subida sin filtrar del constructor encendida, saneado con el saneador del núcleo **antes** de
 *   escribirse; si no, la referencia se vacía (K-D15).
 * - **Temporal:** `wp_tempnam()` (fuera de `uploads`), borrado en `finally`. Inserción con `media_handle_sideload()`,
 *   el tipo SVG y sin tamaños intermedios sólo durante la inserción.
 * - **Registro:** fila `demo` (clave = sha256 del original, con su familia) en `pending` antes de insertar y en `done`
 *   después; la meta `_digitalisimo_migration_demo` lleva el mismo sha256. Se deduplica por sitio (entre familias).
 * - **Pureza:** con `Context::pure()` (editor y análisis) nunca copia: reutiliza lo ya copiado (sólo lectura) y deja
 *   vacío lo demás. El análisis cuenta además en `$planned` lo que la tarea sí copiaría.
 * - **Ausente:** plugin borrado, archivo que falta o rechazado → vacío (`ps.demo.skipped` / `core.demo.skipped`).
 *
 * `Prime_Slider\Demo_Media` (su nombre de siempre) la extiende sin cambios.
 */
class Demo_Media {
	const EXTENSIONS = array(
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'svg'  => 'image/svg+xml',
	);
	const MAX_BYTES = 5242880;
	const META      = '_digitalisimo_migration_demo';
	const KIND      = 'demo';
	const REGISTRY  = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry';
	const SANITIZER = '\\Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer';

	/**
	 * Familias: carpetas de origen reconocidas aunque el plugin ya no esté instalado y archivos principales con los que
	 * se reconoce una copia instalada en otra carpeta (Prime Slider usa además su propia lista, por el nombre).
	 */
	const FAMILIES = array(
		'ps' => array( 'slugs' => '/^bdthemes-prime-slider(?:-lite)?$/', 'files' => array( 'bdthemes-prime-slider.php' ) ),
		'ep' => array( 'slugs' => '/^bdthemes-element-pack(?:-lite)?$/', 'files' => array( 'bdthemes-element-pack.php', 'bdthemes-element-pack-lite.php' ) ),
	);

	/** Marcador de la carpeta `assets/` de Element Pack en los valores por defecto de los mapas (`Translator`). */
	const EP_ASSETS = '{{ep_assets}}';

	/** @var int Copiadas (nuevas) en la última resolución. */
	public static $copied = 0;
	/** @var int Reutilizadas en la última resolución. */
	public static $reused = 0;
	/** @var int Vaciadas en la última resolución. */
	public static $skipped = 0;
	/**
	 * @var int De las vaciadas, las que la tarea sí copiaría (sólo en el análisis, que nunca copia): quien informa las
	 * cuenta como copias previstas, no como imágenes perdidas.
	 */
	public static $planned = 0;

	/** @var array<string, int> sha256 → adjunto, en esta petición. */
	private static $done = array();

	/** Ajustes con cada URL de la carpeta del plugin sustituida por su copia (o vacía). */
	public static function resolve( string $id, array $settings ): array {
		self::$copied  = 0;
		self::$reused  = 0;
		self::$skipped = 0;
		self::$planned = 0;
		return self::walk( $settings );
	}

	private static function walk( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ) {
				if ( self::is_plugin_url( $value['url'] ) ) {
					$media          = self::media( $value['url'] );
					$values[ $key ] = $media ? array_merge( $value, $media ) : array_merge( $value, array( 'url' => '', 'id' => '' ) );
				}
				continue;
			}
			if ( is_array( $value ) ) {
				$values[ $key ] = self::walk( $value );
			} elseif ( is_string( $value ) && self::is_plugin_url( $value ) ) {
				$media          = self::media( $value );
				$values[ $key ] = $media ? $media['url'] : '';
			}
		}
		return $values;
	}

	/** ¿URL bajo la carpeta de una familia (por su nombre conocido o por la de un plugin suyo instalado)? */
	public static function is_plugin_url( string $url ): bool {
		return null !== self::parts( $url );
	}

	/** Carpeta del plugin, ruta relativa y familia de una URL de la carpeta de un plugin de demostración. */
	private static function parts( string $url ): ?array {
		$path = (string) strtok( trim( $url ), '?#' );
		if ( 1 !== preg_match( '#/plugins/([A-Za-z0-9._-]+)/(.+)$#', $path, $m ) ) {
			return null;
		}
		foreach ( self::FAMILIES as $family => $spec ) {
			if ( 1 === preg_match( $spec['slugs'], $m[1] ) || in_array( $m[1], array_column( self::installed( $family ), 'slug' ), true ) ) {
				return array( 'slug' => $m[1], 'rel' => $m[2], 'family' => $family );
			}
		}
		return null;
	}

	/**
	 * Plugins instalados (activos o no) de una familia: `{file, slug, dir, lite}`. Los de Prime Slider, de su lista
	 * (`Prime_Slider\Defaults::installed()`); los de Element Pack, por su archivo principal (Lite o Pro).
	 */
	public static function installed( string $family ): array {
		if ( 'ps' === $family ) {
			$class = __NAMESPACE__ . '\\Prime_Slider\\Defaults';
			return class_exists( $class ) ? (array) $class::installed() : array();
		}
		if ( ! isset( self::FAMILIES[ $family ] ) ) {
			return array();
		}
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			$file = (string) $file;
			$slug = dirname( $file );
			if ( '.' === $slug || 1 !== preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $slug ) || ! in_array( basename( $file ), self::FAMILIES[ $family ]['files'], true ) ) {
				continue;
			}
			$out[] = array(
				'file' => $file,
				'slug' => $slug,
				'dir'  => rtrim( WP_PLUGIN_DIR, '/' ) . '/' . $slug,
				'lite' => 1 === preg_match( '/-lite\.php$/', $file ) || 1 === preg_match( '/-lite$/', $slug ),
			);
		}
		return $out;
	}

	/**
	 * URL de `assets/` de Element Pack para los valores por defecto (`{{ep_assets}}`): la del Element Pack cargado o la
	 * de uno instalado (Lite primero, donde viven sus imágenes); '' si no hay ninguno.
	 */
	public static function ep_assets_url(): string {
		if ( defined( 'BDTEP_ASSETS_URL' ) && is_string( BDTEP_ASSETS_URL ) && '' !== BDTEP_ASSETS_URL ) {
			return rtrim( BDTEP_ASSETS_URL, '/' ) . '/';
		}
		$plugins = self::installed( 'ep' );
		usort( $plugins, static function ( $a, $b ) {
			return (int) $b['lite'] <=> (int) $a['lite'];
		} );
		foreach ( $plugins as $plugin ) {
			if ( function_exists( 'plugins_url' ) ) {
				return (string) plugins_url( 'assets/', rtrim( WP_PLUGIN_DIR, '/' ) . '/' . $plugin['file'] );
			}
		}
		return '';
	}

	/** Valor por defecto con `{{ep_assets}}` resuelto ('' si no hay Element Pack instalado: nunca una URL a medias). */
	public static function expand( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'expand' ), $value );
		}
		if ( ! is_string( $value ) || false === strpos( $value, self::EP_ASSETS ) ) {
			return $value;
		}
		$assets = self::ep_assets_url();
		return '' === $assets ? '' : str_replace( self::EP_ASSETS, $assets, $value );
	}

	/** Adjunto (`{id, url}`) de la imagen de una URL de demostración, o null si no se copia. */
	private static function media( string $url ): ?array {
		$file = self::locate( $url );
		if ( null === $file ) {
			++self::$skipped;
			return null;
		}
		$id = self::attach( $file, (string) ( self::parts( $url )['family'] ?? 'ps' ) );
		if ( ! $id ) {
			++self::$skipped;
			return null;
		}
		$new = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $id ) : '';
		return '' === $new ? null : array( 'id' => $id, 'url' => $new );
	}

	/**
	 * Ruta real y segura del archivo, o null. Sólo dentro de `assets/images/` de un plugin instalado de la familia de la
	 * URL; nunca una ruta tomada tal cual del dato.
	 */
	public static function locate( string $url ): ?string {
		$parts = self::parts( $url );
		if ( ! $parts ) {
			return null;
		}
		$rel = $parts['rel'];
		for ( $i = 0; $i < 3; $i++ ) {
			$decoded = rawurldecode( $rel );
			if ( $decoded === $rel ) {
				break;
			}
			$rel = $decoded;
		}
		if ( 0 !== strpos( $rel, 'assets/images/' ) ) {
			return null;
		}
		$rel = substr( $rel, strlen( 'assets/images/' ) );
		if ( '' === $rel || 1 !== preg_match( '#^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$#', $rel ) || 1 === preg_match( '#(^|/)\.#', $rel ) ) {
			return null;
		}
		$ext = strtolower( (string) pathinfo( $rel, PATHINFO_EXTENSION ) );
		if ( ! isset( self::EXTENSIONS[ $ext ] ) ) {
			return null;
		}
		// La carpeta de la URL primero; después, Lite (las imágenes viven allí) y el resto de los instalados de su familia.
		$plugins = self::installed( $parts['family'] );
		usort( $plugins, static function ( $a, $b ) use ( $parts ) {
			$score = static function ( $p ) use ( $parts ) {
				return ( $p['slug'] === $parts['slug'] ? 0 : 2 ) + ( $p['lite'] ? 0 : 1 );
			};
			return $score( $a ) <=> $score( $b );
		} );
		foreach ( $plugins as $plugin ) {
			$base = realpath( $plugin['dir'] . '/assets/images' );
			if ( false === $base || ! is_dir( $base ) ) {
				continue;
			}
			$path = $base . '/' . $rel;
			$real = realpath( $path );
			if ( false === $real || $real !== $path || 0 !== strpos( $real, $base . '/' ) || is_link( $path ) || ! is_file( $real ) ) {
				continue;
			}
			$size = filesize( $real );
			if ( false === $size || $size <= 0 || $size > self::MAX_BYTES ) {
				return null;
			}
			return $real;
		}
		return null;
	}

	/** Adjunto de un archivo de demostración: el ya copiado o uno nuevo (0 si no se puede o no se debe). */
	private static function attach( string $file, string $family = 'ps' ): int {
		$sha = hash_file( 'sha256', $file );
		if ( ! is_string( $sha ) || '' === $sha ) {
			return 0;
		}
		if ( isset( self::$done[ $sha ] ) ) {
			++self::$reused;
			return self::$done[ $sha ];
		}
		$existing = self::existing( $sha );
		if ( $existing ) {
			self::$done[ $sha ] = $existing;
			++self::$reused;
			return $existing;
		}
		if ( self::pure() ) {
			if ( 'analysis' === Context::mode() && self::copyable( $file ) ) {
				++self::$planned;
			}
			return 0;
		}
		$name = basename( $file );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$svg  = 'svg' === $ext;
		if ( ! self::type_ok( $file, $name, $ext ) ) {
			return 0;
		}
		$content = null;
		if ( $svg ) {
			if ( ! self::unfiltered_svg() ) {
				return 0;
			}
			$content = self::sanitize_svg( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local comprobado.
			if ( '' === $content ) {
				return 0;
			}
		}
		foreach ( array( 'file', 'media', 'image' ) as $include ) {
			if ( ! function_exists( 'media_handle_sideload' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/' . $include . '.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/' . $include . '.php';
			}
		}
		if ( ! function_exists( 'wp_tempnam' ) || ! function_exists( 'media_handle_sideload' ) ) {
			return 0;
		}
		$tmp = (string) wp_tempnam( $name );
		$id  = 0;
		try {
			$written = $svg ? false !== file_put_contents( $tmp, $content ) : copy( $file, $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporal propio.
			if ( ! $written ) {
				return 0;
			}
			self::registry( 'put', $sha, array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array( 'name' => $name, 'family' => $family ) ) );
			$mimes = static function ( $list ) {
				$list['svg'] = 'image/svg+xml';
				return $list;
			};
			$sizes = static function () {
				return array();
			};
			if ( $svg ) {
				add_filter( 'upload_mimes', $mimes, 99 );
			}
			add_filter( 'intermediate_image_sizes_advanced', $sizes, 99 );
			try {
				$result = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
			} finally {
				remove_filter( 'intermediate_image_sizes_advanced', $sizes, 99 );
				if ( $svg ) {
					remove_filter( 'upload_mimes', $mimes, 99 );
				}
			}
			if ( ! is_int( $result ) && ! ( is_numeric( $result ) && (int) $result > 0 ) ) {
				return 0;
			}
			$id = (int) $result;
			update_post_meta( $id, self::META, $sha );
			$stored = function_exists( 'get_attached_file' ) ? (string) get_attached_file( $id ) : '';
			$hash   = '' !== $stored && is_file( $stored ) ? (string) hash_file( 'sha256', $stored ) : '';
			self::registry( 'put', $sha, array( 'dest' => $id, 'hash' => $hash, 'state' => 'done', 'extra' => array( 'name' => $name, 'family' => $family ) ) );
			self::$done[ $sha ] = $id;
			++self::$copied;
		} finally {
			if ( '' !== $tmp && file_exists( $tmp ) ) {
				unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temporal propio.
			}
		}
		return $id;
	}

	/** Extensión y tipo reales del archivo, de la lista cerrada (y una imagen de verdad si es de mapa de bits). */
	private static function type_ok( string $file, string $name, string $ext ): bool {
		if ( ! isset( self::EXTENSIONS[ $ext ] ) || ! function_exists( 'wp_check_filetype_and_ext' ) ) {
			return false;
		}
		$check = wp_check_filetype_and_ext( $file, $name, self::EXTENSIONS );
		$type  = is_array( $check ) ? (string) ( $check['type'] ?? '' ) : '';
		$found = is_array( $check ) ? strtolower( (string) ( $check['ext'] ?? '' ) ) : '';
		if ( '' === $type || self::EXTENSIONS[ $ext ] !== $type || ( self::EXTENSIONS[ $found ] ?? '' ) !== $type ) {
			return false;
		}
		if ( 'svg' !== $ext ) {
			$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un archivo no válido devuelve false.
			if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) || ( $info['mime'] ?? '' ) !== $type ) {
				return false;
			}
		}
		return true;
	}

	/** ¿La tarea podría copiar el archivo (tipo comprobado y, si es SVG, subida sin filtrar y saneado posible)? */
	private static function copyable( string $file ): bool {
		$name = basename( $file );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! self::type_ok( $file, $name, $ext ) ) {
			return false;
		}
		return 'svg' !== $ext || ( self::unfiltered_svg() && '' !== self::sanitize_svg( (string) file_get_contents( $file ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local comprobado.
	}

	/** Subida sin filtrar del constructor (la opción del núcleo). */
	private static function unfiltered_svg(): bool {
		return function_exists( 'get_option' ) && (bool) get_option( 'elementor_unfiltered_files_upload' );
	}

	/** SVG saneado con el saneador del núcleo ('' si no se puede: falla cerrado). */
	private static function sanitize_svg( string $svg ): string {
		$class = self::SANITIZER;
		if ( '' === trim( $svg ) || ! class_exists( $class ) ) {
			return '';
		}
		try {
			$clean = ( new $class() )->sanitize( $svg );
		} catch ( \Throwable $e ) {
			return '';
		}
		return is_string( $clean ) && false !== stripos( $clean, '<svg' ) ? $clean : '';
	}

	/** Adjunto ya copiado con ese sha256 (registro o meta), sólo lectura. */
	private static function existing( string $sha ): int {
		$row = self::registry( 'get', $sha );
		if ( is_array( $row ) && 'done' === ( $row['state'] ?? '' ) && ! empty( $row['dest'] ) && self::is_attachment( (int) $row['dest'] ) ) {
			return (int) $row['dest'];
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'meta_key'         => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- una vez por imagen y sitio.
				'meta_value'       => $sha, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		$id = is_array( $ids ) && $ids ? (int) reset( $ids ) : 0;
		return $id && self::is_attachment( $id ) ? $id : 0;
	}

	private static function is_attachment( int $id ): bool {
		$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
		return is_object( $post ) && 'attachment' === ( $post->post_type ?? '' ) && 'trash' !== ( $post->post_status ?? '' );
	}

	/** ¿Conversión pura (editor o análisis)? */
	private static function pure(): bool {
		return class_exists( Context::class, false ) && Context::pure();
	}

	/** Llamada protegida al registro de A (C6). Nunca escribe en modo puro. */
	private static function registry( string $method, string $source, array $row = array() ) {
		$class = self::REGISTRY;
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return null;
		}
		if ( 'get' === $method ) {
			return $class::get( self::KIND, $source );
		}
		if ( self::pure() ) {
			return null;
		}
		return $class::$method( self::KIND, $source, $row );
	}

	/**
	 * `--revert` (`Runner::REVERT_EXTRA`, V.8): cada copia registrada que no cambió va a la papelera de medios si existe
	 * (`MEDIA_TRASH` y `EMPTY_TRASH_DAYS` > 0); si no, se queda en la biblioteca (nunca se borra para siempre). La fila
	 * del registro se olvida.
	 *
	 * @return array<int, array{0:string, 1:array}> códigos del informe.
	 */
	public static function revert(): array {
		$class = self::REGISTRY;
		$out   = array();
		if ( ! class_exists( $class ) || ! method_exists( $class, 'all' ) ) {
			return $out;
		}
		$trash = defined( 'MEDIA_TRASH' ) && MEDIA_TRASH && defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS > 0 && function_exists( 'wp_trash_post' );
		foreach ( (array) $class::all( self::KIND ) as $source => $row ) {
			$id = is_array( $row ) ? (int) ( $row['dest'] ?? 0 ) : 0;
			if ( $id && self::is_attachment( $id ) ) {
				$file = function_exists( 'get_attached_file' ) ? (string) get_attached_file( $id ) : '';
				$same = '' !== $file && is_file( $file ) && hash_file( 'sha256', $file ) === (string) ( $row['hash'] ?? '' );
				$ep = 'ep' === ( is_array( $row ) && is_array( $row['extra'] ?? null ) ? ( $row['extra']['family'] ?? '' ) : '' );
				if ( $same && $trash ) {
					wp_trash_post( $id );
					$out[] = array( $ep ? 'core.demo.reverted' : 'ps.demo.reverted', array( 'id' => $id ) );
				} else {
					$out[] = array( $ep ? 'core.demo.revert_kept' : 'ps.demo.revert_kept', array( 'id' => $id ) );
				}
			}
			if ( method_exists( $class, 'forget' ) ) {
				$class::forget( self::KIND, (string) $source );
			}
		}
		return $out;
	}

	/** Olvida lo copiado en esta petición (pruebas). */
	public static function reset(): void {
		self::$done    = array();
		self::$copied  = 0;
		self::$reused  = 0;
		self::$skipped = 0;
		self::$planned = 0;
	}
}
