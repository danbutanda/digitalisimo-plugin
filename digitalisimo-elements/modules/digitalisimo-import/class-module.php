<?php
namespace Digitalisimo\Elements\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Importador de kits de DIGITALÍSIMO Elements (etapa K).
 *
 * No es otro sistema de kits: engancha el importador 3.0 del núcleo con filtros públicos y sustituye runners
 * por nombre. Este archivo fija lo que comparten todos los frentes: la autocarga acotada al espacio de nombres
 * `Digitalisimo\Elements\Import\`, los identificadores (§5.1 y §5.3), los nombres de los ganchos que K publica
 * (§5.6) y las formas válidas de las rutas del kit (§5.2). Los ganchos del importador (§7.1) los añade `init()`.
 *
 * Correspondencia de clases y archivos (relativos a esta carpeta): cada segmento del espacio de nombres es una
 * carpeta en minúsculas y con guiones, y la clase es `class-<nombre>.php` (o `interface-` o `trait-`). Por ejemplo,
 * `Kits\Parts\Element_Defaults` vive en `kits/parts/class-element-defaults.php` y `Kits\Part` en
 * `kits/interface-part.php`. La autocarga se registra al cargar el plugin, no al importar: las clases de los
 * runners vuelven a cargarse al deserializar la sesión del núcleo en cada petición de la importación.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Import\\';

	/** Prefijos de archivo que prueba la autocarga, en este orden. */
	const FILE_PREFIXES = array( 'class-', 'interface-', 'trait-' );

	// Identificadores (§5.1).
	const RUNNER       = 'digitalisimo';
	const SOURCE       = 'digitalisimo';
	const MANIFEST_KEY = 'digitalisimo';
	const KIT_FOLDER   = 'digitalisimo/';
	const FORMAT       = 1;
	const MODE_ADD     = 'add';
	const MODE_FRESH   = 'fresh';
	const WIDGET       = 'digitalisimo-unconverted';
	const PAGE         = 'digitalisimo-elements-kits';
	const CAPABILITY   = 'manage_options';
	const CLI_COMMAND  = 'digitalisimo-elements kit';

	// Acciones de admin-post de la pantalla «Kits» (§5.1).
	const ACTION_UPLOAD           = 'digitalisimo_elements_kit_upload';
	const ACTION_APPLY_SETTINGS   = 'digitalisimo_elements_kit_apply_settings';
	const ACTION_UNAPPLY_SETTINGS = 'digitalisimo_elements_kit_unapply_settings';
	const ACTION_PUBLISH          = 'digitalisimo_elements_kit_publish';
	const ACTION_REVERT           = 'digitalisimo_elements_kit_revert';

	// Opciones y metas (§5.3). Todas automáticas y sin pantalla de ajustes.
	const OPTION_SESSION_PREFIX         = 'digitalisimo_elements_kit_session_';
	const OPTION_UNCONVERTED            = 'digitalisimo_elements_unconverted';
	const USER_META_NOTICE              = 'digitalisimo_elements_kit_notice';
	const META_ORIGIN                   = '_digitalisimo_import_origin';
	const META_UNCONVERTED              = '_digitalisimo_unconverted';
	const META_ORIGINAL_TYPE            = '_digitalisimo_original_type';
	const KIT_META_UNCONVERTED_DEFAULTS = '_digitalisimo_unconverted_defaults';
	const CLEANUP_EVENT                 = 'digitalisimo_elements_kits_cleanup';
	/** Temporales, relativos a `wp_upload_dir()['basedir']`: `<TMP_DIR>/<64 hex>/`. */
	const TMP_DIR = 'digitalisimo-elements/tmp';

	// Ganchos que publica K (§5.6). Los archivos derivados de PRO usan el nombre literal, no esta constante.
	const FILTER_PARTS                = 'digitalisimo_elements_kit_parts';
	const FILTER_REFERENCES           = 'digitalisimo_elements_kit_references';
	const FILTER_MEDIA_LIMITS         = 'digitalisimo_elements_kit_media_limits';
	const FILTER_GUARD_LIMITS         = 'digitalisimo_elements_kit_guard_limits';
	const FILTER_EXISTING_DOCUMENT    = 'digitalisimo_elements_kit_existing_document';
	const ACTION_IMPORTED             = 'digitalisimo_elements_kit_imported';
	const FILTER_UNCONVERTED_CONVERT  = 'digitalisimo_elements_unconverted_convert';
	const FILTER_THEME_BUILDER_FIELDS = 'digitalisimo_elements_kit_theme_builder_fields';

	// Formas válidas de §5.2. Lo que no las cumple se rechaza en el guardián y nunca decide una ruta de disco.
	/** `file` de `media.json` y nombre bajo `digitalisimo/media/`. `css` y `eot` sólo los usan los conjuntos de iconos. */
	const MEDIA_FILE_PATTERN = '/^[0-9a-f]{64}\.(?:jpg|jpeg|png|gif|webp|avif|svg|mp4|webm|mp3|ogg|wav|woff|woff2|ttf|otf|eot|csv|pdf|json|css)$/';
	/** Claves de `files.json` y rutas bajo `digitalisimo/files/`. */
	const FILES_KEY_PATTERN = '/^(?:shapes|masks)\/[a-z0-9]+(?:-[a-z0-9]+)*\.svg$/';
	/** Claves de `urls.json`: `<tipo>:<id origen>`. */
	const URLS_KEY_PATTERN = '/^[a-z0-9_-]+:[0-9]+$/';
	/** Claves de `theme-builder.json`: `<id origen>`. */
	const THEME_BUILDER_KEY_PATTERN = '/^[0-9]+$/';

	/** @var bool */
	private static $autoloader = false;

	/** @var bool */
	private static $initialized = false;


	/**
	 * Punto de entrada del módulo. Idempotente.
	 *
	 * Registra los ganchos de §7.1 con callbacks por nombre de clase: ningún archivo del módulo se carga hasta que
	 * su gancho se dispara, así que un sitio que no importa sólo paga una comparación de cadena por petición REST y
	 * la lectura del contador de marcadores, que ya está en `alloptions`.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		$hooks = __NAMESPACE__ . '\\Kits\\Runners\\Import_Hooks';
		$base  = 'elementor/import-export-customization/';

		// Importador 3.0: sólo cuando el núcleo dispara sus procesos.
		add_action( $base . 'import-kit', array( $hooks, 'configure' ), 20 );
		add_action( $base . 'import-kit', array( $hooks, 'finish' ), PHP_INT_MAX );
		add_action( $base . 'import-kit/runner/after-run', array( $hooks, 'after_run' ), 10 );
		add_action( $base . 'export-kit', array( $hooks, 'export' ), 20 );
		add_action( $base . 'export-kit', array( $hooks, 'export_finish' ), PHP_INT_MAX );
		add_action( $base . 'revert-kit', array( $hooks, 'revert' ), 20 );
		add_action( $base . 'revert-kit', array( $hooks, 'revert_finish' ), PHP_INT_MAX );

		// Contexto por petición REST (una comparación de cadena) y kit preparado por la pantalla «Kits» (V-8).
		add_filter( 'rest_request_before_callbacks', array( __NAMESPACE__ . '\\Kits\\Context', 'rest_before' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __NAMESPACE__ . '\\Kits\\Context', 'rest_after' ), 10, 3 );
		add_filter( 'elementor/import/kit/result/' . self::SOURCE, array( __NAMESPACE__ . '\\Kits\\Session', 'kit_result' ), 10 );

		// Metas del kit que deben sobrevivir si el núcleo crea un kit nuevo.
		add_filter( 'elementor/kit/meta_to_preserve_on_kit_import', array( __NAMESPACE__ . '\\Kits\\Parts', 'meta_to_preserve' ), 10 );

		// Flujos heredados 2.0: sólo con el núcleo incluido (D19).
		if ( class_exists( '\Digitalisimo_Elements_Core' ) && \Digitalisimo_Elements_Core::bundled() ) {
			$legacy = __NAMESPACE__ . '\\Kits\\Legacy_Guard';
			add_action( 'admin_init', array( $legacy, 'ajax' ), 9 );
			add_action( 'admin_post_elementor_revert_kit', array( $legacy, 'revert' ), 1 );
			add_action( 'elementor/import-export/import-kit', array( $legacy, 'abort' ), 1 );
			add_action( 'elementor/import-export/export-kit', array( $legacy, 'abort' ), 1 );
			add_action( 'elementor/import-export/revert-kit', array( $legacy, 'abort_revert' ), 1 );
		}

		// Marcador `digitalisimo-unconverted` (D9, §10.3, frente D). Con marcadores en el sitio hacen falta todos sus
		// ganchos (`Preserve::boot()`); sin ellos, sólo el registro condicionado del widget y el filtro de plantillas
		// sueltas, que no cargan nada hasta dispararse (CR-21).
		if ( self::markers() > 0 ) {
			self::call( 'Kits\\Preserve', 'boot' );
		} else {
			add_action( 'elementor/widgets/register', self::lazy( 'Kits\\Preserve', 'register_widget' ), 20 );
			add_filter( 'elementor/template_library/sources/local/import/elements', self::lazy( 'Kits\\Preserve', 'filter_library_import', 0 ), 20 );
		}

		// Un ZIP de kit subido en «Plantillas → Importar» va al importador de kits (D12, frente D).
		add_action( 'wp_ajax_elementor_library_direct_actions', self::lazy( 'Kits\\Library', 'intercept' ), 5 );

		// Pantalla «Kits», sus acciones y la sección de Herramientas (frente F): sólo en la administración.
		add_action( self::CLEANUP_EVENT, array( __NAMESPACE__ . '\\Kits\\Session', 'cleanup' ) );
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			self::call( 'Kits\\Report_Page', 'init' );
		}

		// WP-CLI: guardas del flujo heredado y `wp digitalisimo-elements kit`.
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {
			Kits\Cli_Guard::register();
			self::call( 'Kits\\Cli', 'init' );
		}
	}

	/** Llama a `init()`/`boot()` de un frente si existe. */
	private static function call( string $class, string $method ): void {
		$class = __NAMESPACE__ . '\\' . $class;
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			call_user_func( array( $class, $method ) );
		}
	}

	/** Número de marcadores del sitio, leído de `alloptions` (sin consulta: la opción tiene autoload). */
	public static function markers(): int {
		if ( ! function_exists( 'wp_load_alloptions' ) ) {
			return 0;
		}
		$all = wp_load_alloptions();
		return isset( $all[ self::OPTION_UNCONVERTED ] ) ? (int) $all[ self::OPTION_UNCONVERTED ] : 0;
	}

	/**
	 * Callback que carga la clase del módulo sólo al dispararse el gancho y no hace nada si la clase o el método no
	 * existen (frentes que aún no están).
	 *
	 * @param string $class  Clase relativa al espacio de nombres del módulo.
	 * @param string $method Método estático.
	 * @param int    $return Índice del argumento que devuelve si no puede llamar (filtros), o -1 en acciones.
	 */
	public static function lazy( string $class, string $method, int $return = -1 ): \Closure {
		$class = __NAMESPACE__ . '\\' . $class;
		return function ( ...$args ) use ( $class, $method, $return ) {
			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				return call_user_func_array( array( $class, $method ), $args );
			}
			return $return >= 0 && array_key_exists( $return, $args ) ? $args[ $return ] : null;
		};
	}

	/** Registra la autocarga una sola vez. No engancha nada más. */
	public static function register_autoloader(): void {
		if ( self::$autoloader ) {
			return;
		}
		self::$autoloader = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Carga una clase de `Digitalisimo\Elements\Import\`; cualquier otro nombre se ignora sin tocar el disco.
	 *
	 * @param string $class Nombre completo de la clase.
	 */
	public static function autoload( $class ): void {
		$file = self::file_for( (string) $class );
		if ( '' !== $file ) {
			require_once $file;
		}
	}

	/** Archivo existente de una clase del módulo, o '' si no es del módulo o no existe. */
	public static function file_for( string $class ): string {
		foreach ( self::FILE_PREFIXES as $prefix ) {
			$relative = self::relative_path( $class, $prefix );
			if ( '' === $relative ) {
				return '';
			}
			$file = __DIR__ . '/' . $relative;
			if ( is_file( $file ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * Ruta relativa a esta carpeta que corresponde a una clase, sin comprobar que exista.
	 *
	 * Sólo acepta segmentos `[A-Za-z][A-Za-z0-9_]*`: un nombre con otros caracteres nunca llega al disco.
	 *
	 * @param string $class  Nombre completo de la clase.
	 * @param string $prefix `class-`, `interface-` o `trait-`.
	 */
	public static function relative_path( string $class, string $prefix = 'class-' ): string {
		$class = ltrim( $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) || ! in_array( $prefix, self::FILE_PREFIXES, true ) ) {
			return '';
		}
		$relative = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*(?:\\\\[A-Za-z][A-Za-z0-9_]*)*$/', $relative ) ) {
			return '';
		}
		$segments = array_map(
			function ( $segment ) {
				return str_replace( '_', '-', strtolower( $segment ) );
			},
			explode( '\\', $relative )
		);
		$name = array_pop( $segments );
		return ( $segments ? implode( '/', $segments ) . '/' : '' ) . $prefix . $name . '.php';
	}

	/** Carpeta del módulo, con barra final. */
	public static function dir(): string {
		return __DIR__ . '/';
	}

	/**
	 * ¿Es una ruta válida dentro de `digitalisimo/` para un archivo del kit (§5.2)?
	 *
	 * Sólo `media/<sha256>.<ext>` y `files/(shapes|masks)/<slug>.svg`. Es la misma forma en el ZIP, al exportar
	 * (`Export_Context::add_file()`) y en la carpeta preparada (`Import_Context::staged_path()`).
	 */
	public static function is_kit_file( string $path ): bool {
		if ( 0 === strpos( $path, 'media/' ) ) {
			return 1 === preg_match( self::MEDIA_FILE_PATTERN, substr( $path, 6 ) );
		}
		if ( 0 === strpos( $path, 'files/' ) ) {
			return 1 === preg_match( self::FILES_KEY_PATTERN, substr( $path, 6 ) );
		}
		return false;
	}

	/**
	 * Avisa de un uso incorrecto del contrato con `_doing_it_wrong()` (sólo se ve con WP_DEBUG).
	 *
	 * @param string $method  Método que detectó el error.
	 * @param string $message Mensaje en español.
	 */
	public static function doing_it_wrong( string $method, string $message ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong( $method, $message, defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** JSON compacto para mensajes de diagnóstico. */
	public static function describe( $value ): string {
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR )
			: json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		$json = is_string( $json ) ? $json : gettype( $value );
		if ( strlen( $json ) <= 300 ) {
			return $json;
		}
		return ( function_exists( 'mb_strcut' ) ? mb_strcut( $json, 0, 300, 'UTF-8' ) : substr( $json, 0, 300 ) ) . '…';
	}
}
