<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Capacidades de la etapa F7b de DIGITALÍSIMO Elements (`docs/FUSION_OBJECTIVE.md`): acciones, consultas guardadas, filtros,
 * sobrescrituras, anidados, edición múltiple, etiquetas en el texto y formularios condicionales.
 *
 * Arranque del módulo: registra la autocarga acotada a `Digitalisimo\Elements\F7b\`, inicia la base (`BASE`: banderas,
 * usos, recorrido por lotes y bloqueo del borrado) y después cada submódulo de `BOOT` cuyo archivo exista. Cada
 * submódulo comprueba sus símbolos con `Module::requires()` en su `init()` y, si falta alguno, no registra nada (nunca
 * un error fatal). Con los contadores de `Flags` a 0, ningún submódulo engancha nada en el front: `Flags::active()`.
 *
 * Correspondencia de clases y archivos (como `Woo\Module`): minúsculas y `_` → `-`, con prefijo `class-` (o
 * `interface-`/`trait-`). `F7b\Hidden_Rule` vive en `class-hidden-rule.php`; `F7b\Inline_Tags\Module` en
 * `inline-tags/class-module.php`; `F7b\Kit\Parts\Saved_Queries` en `kit/parts/class-saved-queries.php`. Un nombre fuera
 * del espacio de nombres, o con otros caracteres, nunca llega al disco.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\F7b\\';

	/** Prefijos de archivo, en el orden en que se buscan. */
	const FILE_PREFIXES = array( 'class-', 'interface-', 'trait-' );

	/** Subespacios de nombres admitidos como mucho (`Kit\Parts\Saved_Queries`). */
	const MAX_DEPTH = 3;

	/** Base del módulo (frente A), siempre antes que los submódulos. */
	const BASE = array( 'Flags', 'Usages', 'Scan', 'Deletion' );

	/** Submódulos de los frentes B–K, en este orden. Uno cuyo archivo no exista se omite. */
	const BOOT = array(
		'Actions\\Module',
		'Queries\\Module',
		'Overrides\\Module',
		'Nested\\Module',
		'Editor\\Module',
		'Inline_Tags\\Module',
		'Forms\\Module',
		'Kit\\Module',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $autoloader = false;

	/** @var string Carpeta desde la que se cargan las clases del módulo, con barra final. */
	private static $dir = '';

	/** @var array<string,string[]> Símbolos que faltan, por submódulo. */
	private static $missing = array();

	/** @var bool */
	private static $notice = false;

	/** @var bool|null Petición del editor (memorizada); null sin calcular. */
	private static $editing = null;

	/** Punto de entrada, en `pro_elements_load_plugin_func()` detrás de `Woo\Module::init()`. Idempotente. */
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
	 * @param string        $dir  Carpeta del módulo.
	 * @param string[]|null $boot Para las pruebas: submódulos en lugar de `BOOT`.
	 * @return string[]
	 */
	public static function load( string $dir, ?array $boot = null ): array {
		self::$dir = rtrim( $dir, '/\\' ) . '/';
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return array();
		}
		$started = array();
		foreach ( array_merge( self::BASE, null === $boot ? self::BOOT : $boot ) as $class ) {
			if ( '' === self::file_for( self::NAMESPACE_PREFIX . $class ) ) {
				continue;
			}
			if ( self::call( $class, 'init' ) ) {
				$started[] = $class;
			}
		}
		return $started;
	}

	/**
	 * Guarda de un submódulo (contrato del brief): `true` si existen todos los símbolos; si no, anota lo que falta
	 * para el aviso del editor y devuelve `false`, y el submódulo no debe registrar nada.
	 *
	 * Un símbolo es una clase o interfaz (`Elementor\Controls_Manager`), un método (`Clase::metodo`), una función
	 * (`wp_register_style()`, o sin paréntesis si existe con ese nombre) o una constante (`const:ELEMENTOR_VERSION`).
	 * Ojo: las guardas corren en `plugins_loaded`; una clase que el núcleo declara en `init` (`Element_Base`,
	 * `Widget_Base`, `Repeater`…), o que hereda de una de ellas, no se puede comprobar aquí: su autocarga lanza
	 * `Error: Class "Elementor\Widget_Base" not found`. `symbol_exists()` lo captura y la cuenta como ausente (nunca un
	 * error fatal), y `elements-f7b-static.mjs` rechaza esos símbolos en las guardas.
	 *
	 * @param string[] $symbols Símbolos que usa el submódulo sin detección propia.
	 * @param string   $who     Nombre del submódulo (`actions`, `presentation`…).
	 */
	public static function requires( array $symbols, string $who ): bool {
		$missing = array();
		foreach ( $symbols as $symbol ) {
			if ( ! self::symbol_exists( (string) $symbol ) ) {
				$missing[] = (string) $symbol;
			}
		}
		if ( ! $missing ) {
			return true;
		}
		self::$missing[ '' === $who ? 'f7b' : $who ] = $missing;
		self::hook_notice();
		return false;
	}

	/**
	 * Existe la clase, la interfaz, el método (`Clase::metodo`), la función (`nombre()`) o la constante (`const:X`).
	 * Una autocarga que falla (una clase que hereda de otra que aún no existe) cuenta como ausente.
	 */
	public static function symbol_exists( string $symbol ): bool {
		$symbol = ltrim( trim( $symbol ), '\\' );
		if ( '' === $symbol ) {
			return false;
		}
		if ( 0 === strpos( $symbol, 'const:' ) ) {
			return defined( substr( $symbol, 6 ) );
		}
		if ( '()' === substr( $symbol, -2 ) ) {
			return function_exists( substr( $symbol, 0, -2 ) );
		}
		try {
			if ( false !== strpos( $symbol, '::' ) ) {
				list( $class, $method ) = explode( '::', $symbol, 2 );
				return ( class_exists( $class ) || interface_exists( $class ) ) && method_exists( $class, $method );
			}
			return class_exists( $symbol ) || interface_exists( $symbol ) || ( false === strpos( $symbol, '\\' ) && function_exists( $symbol ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/** @return array<string,string[]> Símbolos que faltan, por submódulo. */
	public static function missing(): array {
		return self::$missing;
	}

	/**
	 * ¿Es una petición del editor del constructor? El editor (`post.php?action=elementor`), su vista previa
	 * (`?elementor-preview=`), `admin-ajax.php?action=elementor_ajax` o la REST del constructor (`elementor/v1/…` o
	 * `elementor-pro/v1/…`). Sólo lee la petición: vale desde `plugins_loaded` y no hace consultas. Memorizada.
	 */
	public static function editing(): bool {
		if ( null === self::$editing ) {
			self::$editing = self::detect_editing();
		}
		return self::$editing;
	}

	/** Para las pruebas: fija el resultado de `editing()` (o lo vuelve a calcular con null). */
	public static function set_editing( ?bool $editing ): void {
		self::$editing = $editing;
	}

	private static function detect_editing(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sólo se lee para saber el contexto.
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		if ( 'elementor_ajax' === $action && ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || 'admin-ajax.php' === self::script() ) ) {
			return true;
		}
		if ( 'elementor' === ( isset( $_GET['action'] ) && is_string( $_GET['action'] ) ? $_GET['action'] : '' ) && 'post.php' === self::script() ) {
			return true;
		}
		$route = isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ? $_GET['rest_route'] : '';
		// phpcs:enable
		if ( '' !== $route && preg_match( '#^/?elementor(?:-pro)?/v1(?:/|$)#i', $route ) ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( '' === $uri ) {
			return false;
		}
		$path   = (string) strtok( $uri, '?' );
		$prefix = function_exists( 'rest_get_url_prefix' ) ? trim( (string) rest_get_url_prefix(), '/' ) : 'wp-json';
		return '' !== $prefix && 1 === preg_match( '#/' . preg_quote( $prefix, '#' ) . '/elementor(?:-pro)?/v1(?:/|$)#i', $path );
	}

	/** Archivo de la petición (`post.php`, `admin-ajax.php`…), de `$pagenow` o de `SCRIPT_NAME`. */
	private static function script(): string {
		if ( isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) && '' !== $GLOBALS['pagenow'] ) {
			return $GLOBALS['pagenow'];
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) && is_string( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '';
		return basename( $script );
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
			__( 'Algunas funciones de DIGITALÍSIMO Elements no están disponibles con el constructor instalado: %s. Los ajustes guardados se conservan y vuelven a aplicarse cuando el constructor las admite.', 'pro-elements' ),
			implode( ', ', array_keys( self::$missing ) )
		);
		$script = '(function(m){var s=function(){if(window.elementor&&elementor.notifications){elementor.notifications.showToast({message:m});}};'
			. 'if(window.elementor&&elementor.on){elementor.on("preview:loaded",s);}else{window.addEventListener("load",s);}})(' . wp_json_encode( $message ) . ');';
		wp_add_inline_script( 'elementor-editor', $script );
	}

	/**
	 * Llama a un método estático de una clase del módulo si la clase y el método existen. Un error al cargarla o al
	 * arrancarla no tumba la petición: el submódulo queda sin arrancar y el editor lo avisa como una guarda fallida.
	 */
	private static function call( string $class, string $method ): bool {
		$name = self::NAMESPACE_PREFIX . $class;
		try {
			if ( class_exists( $name ) && method_exists( $name, $method ) ) {
				call_user_func( array( $name, $method ) );
				return true;
			}
		} catch ( \Throwable $e ) {
			self::$missing[ strtolower( str_replace( '\\', '/', $class ) ) ] = array( get_class( $e ) . ': ' . $e->getMessage() );
			self::hook_notice();
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
	 * `F7b\Flags` → `class-flags.php`; `F7b\Inline_Tags\Module` → `inline-tags/class-module.php`.
	 */
	public static function file_for( string $class ): string {
		$class = ltrim( $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return '';
		}
		$parts = explode( '\\', substr( $class, strlen( self::NAMESPACE_PREFIX ) ) );
		if ( count( $parts ) > self::MAX_DEPTH + 1 ) {
			return '';
		}
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $part ) ) {
				return '';
			}
		}
		$name = self::slug( (string) array_pop( $parts ) );
		$dir  = self::dir();
		foreach ( $parts as $part ) {
			$dir .= self::slug( $part ) . '/';
		}
		foreach ( self::FILE_PREFIXES as $prefix ) {
			$file = $dir . $prefix . $name . '.php';
			if ( is_file( $file ) ) {
				return $file;
			}
		}
		return '';
	}

	/** `Inline_Tags` → `inline-tags`. */
	public static function slug( string $part ): string {
		return str_replace( '_', '-', strtolower( $part ) );
	}

	/** Carpeta del módulo (la de `load()`, o la de este archivo), con barra final. */
	public static function dir(): string {
		return '' === self::$dir ? __DIR__ . '/' : self::$dir;
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`, sin quitar la autocarga. */
	public static function reset(): void {
		self::$initialized = false;
		self::$missing     = array();
		self::$notice      = false;
		self::$editing     = null;
	}
}
