<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce visual de DIGITALÍSIMO Elements (etapa F4-Woo de `docs/FUSION_OBJECTIVE.md`).
 *
 * Arranque del módulo: registra la autocarga acotada a `Digitalisimo\Elements\Woo\` y decide el modo.
 *
 * - **Sin WooCommerce, o con WooCommerce anterior a `MIN_WC`** (D3): `Inert::init()`, que actúa en las tres peticiones
 *   del editor (V.10.1) y en ninguna otra, y `Kit\Module::init_inactive()` (frente H), un solo filtro
 *   (`digitalisimo_elements_kit_parts`) que sólo corre cuando K arma sus partes, para que un kit importado sin
 *   WooCommerce informe `woocommerce.inactive`. En el front, REST, cron y WP-CLI no se registra nada más y no se hace
 *   ninguna consulta. Con WooCommerce antiguo, además, un aviso en la administración a quien puede activar plugins.
 * - **Con WooCommerce ≥ `MIN_WC`:** la base (`BASE`: recursos, REST, banderas, privacidad y registro de widgets) y
 *   después cada submódulo de `BOOT` cuyo archivo exista. Cada submódulo comprueba sus símbolos con
 *   `Module::requires()` en su `init()` y, si falta alguno, no registra nada (nunca un error fatal).
 *
 * Correspondencia de clases y archivos, como `Design\Module`: `Woo\Rest` vive en `class-rest.php`;
 * `Woo\Quick_View\Widget` en `quick-view/class-widget.php`; `Woo\Cart\Shipping_Bar_Widget` en
 * `cart/class-shipping-bar-widget.php`. Como mucho un subespacio de nombres; otro nombre nunca llega al disco.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Woo\\';

	/** Versión mínima de WooCommerce (mayor.menor). Sólo 11.2.1 está probado (D3, V.16.1). */
	const MIN_WC = '10.0';

	/** Base del módulo, siempre antes que los submódulos. */
	const BASE = array( 'Assets', 'Rest', 'Flags', 'Privacy', 'Registry' );

	/** Submódulos de los frentes B–F y H, en este orden. Uno cuyo archivo no exista se omite. */
	const BOOT = array(
		'Wishlist\\Module',
		'Quick_View\\Module',
		'Swatches\\Module',
		'Cart\\Module',
		'Filters\\Module',
		'Kit\\Module',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $autoloader = false;

	/** @var bool WooCommerce compatible y la base arrancada. */
	private static $active = false;

	/** @var string Carpeta desde la que se cargan las clases del módulo, con barra final. */
	private static $dir = '';

	/** @var array<string,string[]> Símbolos que faltan, por submódulo. */
	private static $missing = array();

	/** @var bool */
	private static $notice = false;

	/** @var array Para las pruebas: `woocommerce` (bool) y `wc_version` (string) en lugar de lo detectado. */
	private static $env = array();

	/** Punto de entrada, en `pro_elements_load_plugin_func()` detrás de `Design\Module::init()`. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::load( __DIR__ );
	}

	/**
	 * Arranca el módulo desde `$dir` y devuelve las clases cuyo `init()` se llamó (sin el prefijo del espacio de
	 * nombres). Las pruebas la usan con otra carpeta para comprobar que sin submódulos sólo arranca la base.
	 *
	 * @param string $dir Carpeta del módulo.
	 * @param array  $env Para las pruebas: `woocommerce` (bool) y `wc_version` (string) en lugar de lo detectado.
	 * @return string[]
	 */
	public static function load( string $dir, array $env = array() ): array {
		self::$env = $env;
		self::$dir = rtrim( $dir, '/\\' ) . '/';
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return array();
		}
		if ( ! self::woocommerce_ok() ) {
			if ( self::woocommerce_loaded() ) {
				self::hook_version_notice();
			}
			$started = array();
			if ( self::call( 'Inert', 'init' ) ) {
				$started[] = 'Inert';
			}
			// Kits sin WooCommerce: sólo el filtro de partes, que informa `woocommerce.inactive` (V.10.1).
			if ( '' !== self::file_for( self::NAMESPACE_PREFIX . 'Kit\\Module' ) && self::call( 'Kit\\Module', 'init_inactive' ) ) {
				$started[] = 'Kit\\Module::init_inactive';
			}
			return $started;
		}
		self::$active = true;
		$started      = array();
		foreach ( array_merge( self::BASE, self::BOOT ) as $class ) {
			if ( '' === self::file_for( self::NAMESPACE_PREFIX . $class ) ) {
				continue;
			}
			if ( self::call( $class, 'init' ) ) {
				$started[] = $class;
			}
		}
		return $started;
	}

	/** WooCommerce cargado y compatible (`WooCommerce`, `WC()` y `WC_VERSION` ≥ `MIN_WC`). */
	public static function woocommerce_ok(): bool {
		return self::woocommerce_loaded() && self::wc_version_supported( self::wc_version() );
	}

	/** WooCommerce cargado, sea cual sea su versión. */
	public static function woocommerce_loaded(): bool {
		if ( isset( self::$env['woocommerce'] ) ) {
			return (bool) self::$env['woocommerce'];
		}
		return class_exists( 'WooCommerce', false ) && function_exists( 'WC' ) && defined( 'WC_VERSION' );
	}

	/** Versión de WooCommerce cargada, o ''. */
	public static function wc_version(): string {
		if ( isset( self::$env['wc_version'] ) ) {
			return (string) self::$env['wc_version'];
		}
		return defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/** `$version` (`11.2.1`, `10.0.0-rc.1`…) es igual o posterior a `MIN_WC`, comparando mayor y menor. */
	public static function wc_version_supported( string $version ): bool {
		if ( ! preg_match( '/^(\d+)\.(\d+)/', $version, $found ) ) {
			return false;
		}
		list( $major, $minor ) = array_map( 'intval', explode( '.', self::MIN_WC ) );
		return (int) $found[1] > $major || ( (int) $found[1] === $major && (int) $found[2] >= $minor );
	}

	/** El módulo arrancó con WooCommerce compatible (y no en modo `Inert`). */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Guarda de un submódulo (D3): existen todos los símbolos. Con WooCommerce incompatible siempre es `false`.
	 *
	 * Un símbolo es una clase o interfaz (`WC_Shipping_Zones`), un método (`Clase::metodo`), una función
	 * (`wc_get_product_gallery_html()`) o una constante (`const:WC_ABSPATH`). Si falta alguno, se anota para el aviso
	 * del editor y se devuelve `false`: el submódulo no debe registrar nada.
	 *
	 * @param string   $feature Nombre del submódulo (`wishlist`, `quick-view`…).
	 * @param string[] $symbols Símbolos que usa sin detección propia.
	 */
	public static function requires( string $feature, array $symbols = array() ): bool {
		$missing = array();
		if ( ! self::woocommerce_ok() ) {
			$missing[] = 'WC_VERSION >= ' . self::MIN_WC;
		}
		foreach ( $symbols as $symbol ) {
			if ( ! self::symbol_exists( (string) $symbol ) ) {
				$missing[] = (string) $symbol;
			}
		}
		if ( ! $missing ) {
			return true;
		}
		self::$missing[ $feature ] = $missing;
		self::hook_notice();
		return false;
	}

	/** Existe la clase, la interfaz, el método (`Clase::metodo`), la función (`nombre()`) o la constante (`const:X`). */
	public static function symbol_exists( string $symbol ): bool {
		$symbol = ltrim( $symbol, '\\' );
		if ( '' === $symbol ) {
			return false;
		}
		if ( 0 === strpos( $symbol, 'const:' ) ) {
			return defined( substr( $symbol, 6 ) );
		}
		if ( '()' === substr( $symbol, -2 ) ) {
			return function_exists( substr( $symbol, 0, -2 ) );
		}
		if ( false !== strpos( $symbol, '::' ) ) {
			list( $class, $method ) = explode( '::', $symbol, 2 );
			return ( class_exists( $class ) || interface_exists( $class ) ) && method_exists( $class, $method );
		}
		return class_exists( $symbol ) || interface_exists( $symbol );
	}

	/** @return array<string,string[]> Símbolos que faltan, por submódulo. */
	public static function missing(): array {
		return self::$missing;
	}

	/** Registra el aviso del editor la primera vez que falla una guarda. */
	private static function hook_notice(): void {
		if ( self::$notice || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$notice = true;
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_notice' ) );
	}

	/** Aviso en el editor, sólo para quien puede administrar el sitio. */
	public static function editor_notice(): void {
		if ( ! self::$missing || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}
		$message = sprintf(
			/* translators: %s: lista de funciones. */
			__( 'Algunas funciones de WooCommerce de DIGITALÍSIMO Elements no están disponibles con la versión instalada de WooCommerce: %s. Los ajustes guardados se conservan.', 'pro-elements' ),
			implode( ', ', array_keys( self::$missing ) )
		);
		$script = '(function(m){var s=function(){if(window.elementor&&elementor.notifications){elementor.notifications.showToast({message:m});}};'
			. 'if(window.elementor&&elementor.on){elementor.on("preview:loaded",s);}else{window.addEventListener("load",s);}})(' . wp_json_encode( $message ) . ');';
		wp_add_inline_script( 'elementor-editor', $script );
	}

	/** WooCommerce anterior a `MIN_WC`: un aviso en la administración (sólo ahí se engancha). */
	private static function hook_version_notice(): void {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
		}
	}

	/** Aviso de versión, sólo para quien puede activar plugins. */
	public static function version_notice(): void {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) || '' === self::wc_version() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: versión mínima, 2: versión instalada. */
					__( 'Los widgets de WooCommerce de DIGITALÍSIMO Elements necesitan WooCommerce %1$s o posterior (versión instalada: %2$s). Sus ajustes se conservan y vuelven a aplicarse al actualizar WooCommerce.', 'pro-elements' ),
					self::MIN_WC,
					self::wc_version()
				)
			)
		);
	}

	/** Llama a un método estático de una clase del módulo si la clase y el método existen. */
	private static function call( string $class, string $method ): bool {
		$class = self::NAMESPACE_PREFIX . $class;
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			call_user_func( array( $class, $method ) );
			return true;
		}
		return false;
	}

	/** Registra la autocarga una sola vez. */
	public static function register_autoloader(): void {
		if ( '' === self::$dir ) {
			self::$dir = __DIR__ . '/';
		}
		if ( self::$autoloader ) {
			return;
		}
		self::$autoloader = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Carga una clase del módulo; cualquier otro nombre se ignora sin tocar el disco.
	 *
	 * @param string $class Nombre completo de la clase.
	 */
	public static function autoload( $class ): void {
		$file = self::file_for( (string) $class );
		if ( '' !== $file ) {
			require_once $file;
		}
	}

	/**
	 * Archivo existente de una clase del módulo, o '' si no es del módulo o no existe.
	 *
	 * `Woo\Rest` → `class-rest.php`; `Woo\Quick_View\Trigger` → `quick-view/class-trigger.php`.
	 */
	public static function file_for( string $class ): string {
		$class = ltrim( $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return '';
		}
		$name = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		if ( ! preg_match( '/^(?:([A-Za-z][A-Za-z0-9_]*)\\\\)?([A-Za-z][A-Za-z0-9_]*)$/', $name, $parts ) ) {
			return '';
		}
		$file = self::dir() . ( '' !== $parts[1] ? self::slug( $parts[1] ) . '/' : '' ) . 'class-' . self::slug( $parts[2] ) . '.php';
		return is_file( $file ) ? $file : '';
	}

	/** `Quick_View` → `quick-view`. */
	public static function slug( string $part ): string {
		return str_replace( '_', '-', strtolower( $part ) );
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`, sin quitar la autocarga. */
	public static function reset(): void {
		self::$initialized = false;
		self::$active      = false;
		self::$missing     = array();
		self::$notice      = false;
		self::$env         = array();
	}

	/** Carpeta del módulo (la de `load()`, o la de este archivo), con barra final. */
	public static function dir(): string {
		return '' === self::$dir ? __DIR__ . '/' : self::$dir;
	}
}
