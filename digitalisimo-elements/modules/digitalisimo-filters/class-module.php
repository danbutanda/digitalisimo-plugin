<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Filtros del Loop Grid, módulo neutro (F7b, V.5 y C1-13). Carga siempre, con o sin WooCommerce, y **antes** que
 * `Woo\Module` (una línea en `pro_elements_load_plugin_func()`): el registro de tipos (`Types`), la base de los widgets
 * filtro (`Filter_Form`, `Filter_Widget`) y la consulta (`Query`) son los mismos para F4-Woo y para F7b.
 *
 * En `plugins_loaded` (antes de que PRO construya `loop-filter` en `elementor/init`, `init` 0):
 * - registra los cuatro tipos de F7b en `Types` (`digitalisimo_search`, `digitalisimo_meta`, `digitalisimo_orderby` y
 *   `digitalisimo_per_page`), con sus prefijos `digi_s_`, `digi_m_`, `digi_o_` y `digi_pp_`;
 * - registra sus cuatro widgets (`Registry`) y, en el editor, la lista de bucles y de claves (`Editor`);
 * - sin WooCommerce, registra el coordinador (`filters.js`), su hoja (`filters.css`) y `filters-form.js` con los mismos
 *   handles y argumentos que F4-Woo (con WooCommerce ya los registró F4-Woo antes, con prioridad menor, y aquí no se
 *   hace nada), y la hoja propia `digitalisimo-filters`. Sólo se registran: los encola el widget que los declara.
 *
 * Coste en el front: ninguna consulta. `pre_get_posts` sólo se engancha si la URL trae `digi_f` (destino «Archivo») y
 * `rest_request_before_callbacks` sólo en las peticiones REST.
 *
 * Autocarga acotada a `Digitalisimo\Elements\Filters\`: `Filters\Meta_Widget` → `class-meta-widget.php`.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Filters\\';

	/** Handles: los del coordinador y los formularios de F4-Woo (compartidos) y la hoja propia. */
	const COORDINATOR = 'digitalisimo-woo-filters';
	const FORM_HANDLE = 'digitalisimo-woo-filters-form';
	const STYLE       = 'digitalisimo-filters';

	/** Coordinador y hoja de F4-Woo, formularios y hoja propia (rutas relativas a la raíz del plugin). */
	const COORDINATOR_JS  = 'assets/js/filters.js';
	const COORDINATOR_CSS = 'assets/css/filters.css';
	const FORM_JS         = 'assets/js/filters-form.js';
	const STYLE_CSS       = 'modules/digitalisimo-filters/assets/f7b-filters.css';

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $autoloader = false;

	/** Punto de entrada, en `pro_elements_load_plugin_func()` antes de `Woo\Module::init()`. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		Types::register( Query::SEARCH, Query::PREFIXES[ Query::SEARCH ], array( Query::class, 'apply_search' ) );
		Types::register( Query::META, Query::PREFIXES[ Query::META ], array( Query::class, 'apply_meta' ) );
		Types::register( Query::ORDERBY, Query::PREFIXES[ Query::ORDERBY ], array( Query::class, 'apply_orderby' ) );
		Types::register( Query::PER_PAGE, Query::PREFIXES[ Query::PER_PAGE ], array( Query::class, 'apply_per_page' ) );
		Registry::init();
		Editor::init();
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ), 11 );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ), 11 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 6 );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se engancha; Query valida.
		if ( isset( $_GET[ Query::ARCHIVE_PARAM ] ) ) {
			add_action( 'pre_get_posts', array( Query::class, 'archive' ), 20 );
		}
	}

	/** `rest_api_init`: el documento del bucle en `refresh-loop`. */
	public static function rest(): void {
		add_filter( 'rest_request_before_callbacks', array( Query::class, 'rest_document' ), 10, 3 );
	}

	/**
	 * Registra (sin encolar) lo que aún no esté registrado: el coordinador, su hoja y `filters-form.js` con los handles y
	 * argumentos de F4-Woo, y la hoja propia. Idempotente.
	 */
	public static function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$root    = dirname( DIGITALISIMO_ELEMENTS_FILE ) . '/';
		if ( ! wp_style_is( self::COORDINATOR, 'registered' ) && is_file( $root . self::COORDINATOR_CSS ) ) {
			wp_register_style( self::COORDINATOR, plugins_url( self::COORDINATOR_CSS, DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
		}
		if ( ! wp_script_is( self::COORDINATOR, 'registered' ) && is_file( $root . self::COORDINATOR_JS ) ) {
			wp_register_script( self::COORDINATOR, plugins_url( self::COORDINATOR_JS, DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-frontend' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
		if ( ! wp_script_is( self::FORM_HANDLE, 'registered' ) && is_file( $root . self::FORM_JS ) ) {
			wp_register_script( self::FORM_HANDLE, plugins_url( self::FORM_JS, DIGITALISIMO_ELEMENTS_FILE ), array( self::COORDINATOR ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
		if ( ! wp_style_is( self::STYLE, 'registered' ) && is_file( $root . self::STYLE_CSS ) ) {
			wp_register_style( self::STYLE, plugins_url( self::STYLE_CSS, DIGITALISIMO_ELEMENTS_FILE ), array( self::COORDINATOR ), $version );
		}
	}

	/** Registra la autocarga una sola vez. */
	public static function register_autoloader(): void {
		if ( self::$autoloader ) {
			return;
		}
		self::$autoloader = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Carga una clase o un rasgo del módulo; cualquier otro nombre se ignora sin tocar el disco.
	 *
	 * @param string $class Nombre completo.
	 */
	public static function autoload( $class ): void {
		$file = self::file_for( (string) $class );
		if ( '' !== $file ) {
			require_once $file;
		}
	}

	/** Archivo existente de una clase del módulo (`Filters\Meta_Widget` → `class-meta-widget.php`), o ''. */
	public static function file_for( string $class ): string {
		$class = ltrim( $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return '';
		}
		$name = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $name ) ) {
			return '';
		}
		$file = self::dir() . 'class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		return is_file( $file ) ? $file : '';
	}

	/** Carpeta del módulo, con barra final. */
	public static function dir(): string {
		return __DIR__ . '/';
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
