<?php
namespace Digitalisimo\Elements\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Capacidades de diseño de DIGITALÍSIMO Elements (etapa F7a de `docs/FUSION_OBJECTIVE.md`).
 *
 * Arranque del módulo: registra la autocarga acotada a `Digitalisimo\Elements\Design\` y llama `init()` de cada
 * submódulo cuyo archivo exista. Sin submódulos, nada cambia: no se registra ningún gancho.
 *
 * - Correspondencia de clases y archivos: `Design\Css` vive en `class-css.php`; `Design\Presets\Kit_Tab` en
 *   `presets/class-kit-tab.php`; `Design\Theme_Builder\Priority` en `theme-builder/class-priority.php`. Un nombre
 *   fuera del espacio de nombres o con otros caracteres nunca llega al disco.
 * - El cerrojo compartido `Digitalisimo\Elements\Lock` (`class-lock.php`) no depende del módulo: se carga con
 *   `require_once` donde se usa.
 * - Guardas (V.9 de la especificación): cada submódulo llama a `Module::requires()` con los símbolos del núcleo que
 *   usa. Si falta alguno, o el núcleo es anterior a 4.3, el submódulo no registra controles ni ganchos, los valores
 *   guardados quedan inertes y el editor avisa sólo a quien puede administrar. Nunca un error fatal.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Design\\';

	/** Versión mínima del núcleo (mayor.menor) para la que se escribieron los submódulos. */
	const MIN_CORE = '4.3';

	/**
	 * Submódulos que se inician en cada petición, en este orden. Cada uno comprueba sus guardas en su `init()`.
	 * `Backgrounds\Library` no está: la cargan la máscara y el patrón cuando la necesitan.
	 */
	const BOOT = array(
		'Presets\\Module',
		'Backgrounds\\Gradient',
		'Backgrounds\\Mask',
		'Backgrounds\\Pattern',
		'Sticky\\Sticky_State',
		'Document\\Document_Controls',
		'Theme_Builder\\Priority',
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

	/** Punto de entrada, en `pro_elements_load_plugin_func()` detrás de `Front\Module::init()`. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::load( __DIR__ );
	}

	/**
	 * Inicia los submódulos de `$dir` que existan y devuelve los que arrancaron. La usa `init()` con la carpeta del
	 * módulo; las pruebas, con otra para comprobar que sin submódulos no se registra nada.
	 *
	 * @param string $dir Carpeta del módulo.
	 * @return string[] Clases (sin el prefijo del espacio de nombres) cuyo `init()` se llamó.
	 */
	public static function load( string $dir ): array {
		self::$dir = rtrim( $dir, '/\\' ) . '/';
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return array();
		}
		$started = array();
		foreach ( self::BOOT as $class ) {
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
	 * Guarda de un submódulo: el núcleo es 4.3 o posterior y existen todos los símbolos.
	 *
	 * Un símbolo es una clase o interfaz (`Elementor\Core\Files\CSS\Post`), un método (`Clase::metodo`) o una función
	 * (`wp_add_inline_script()`). Si falta alguno, se anota para el aviso del editor y se devuelve `false`: el submódulo
	 * no debe registrar nada.
	 *
	 * @param string   $feature Nombre del submódulo (`presets`, `backgrounds`…).
	 * @param string[] $symbols Símbolos que usa.
	 */
	public static function requires( string $feature, array $symbols = array() ): bool {
		$missing = array();
		if ( ! self::core_supported() ) {
			$missing[] = 'ELEMENTOR_VERSION >= ' . self::MIN_CORE;
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

	/** El núcleo cargado es 4.3 o posterior (sólo mayor y menor, como `pro_elements_compare_major_version()`). */
	public static function core_supported(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && self::version_supported( (string) ELEMENTOR_VERSION );
	}

	/** `$version` (`4.3.5`, `4.3.0-beta1`…) es igual o posterior a `MIN_CORE`, comparando mayor y menor. */
	public static function version_supported( string $version ): bool {
		if ( ! preg_match( '/^(\d+)\.(\d+)/', $version, $found ) ) {
			return false;
		}
		list( $major, $minor ) = array_map( 'intval', explode( '.', self::MIN_CORE ) );
		return (int) $found[1] > $major || ( (int) $found[1] === $major && (int) $found[2] >= $minor );
	}

	/** Existe la clase, la interfaz, el método (`Clase::metodo`) o la función (`nombre()`). */
	public static function symbol_exists( string $symbol ): bool {
		$symbol = ltrim( $symbol, '\\' );
		if ( '' === $symbol ) {
			return false;
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
			/* translators: %s: lista de capacidades. */
			__( 'Algunas capacidades de diseño de DIGITALÍSIMO Elements no están disponibles con el constructor instalado: %s. Los valores guardados se conservan y vuelven a aplicarse cuando el constructor las admite.', 'pro-elements' ),
			implode( ', ', array_keys( self::$missing ) )
		);
		$script = '(function(m){var s=function(){if(window.elementor&&elementor.notifications){elementor.notifications.showToast({message:m});}};'
			. 'if(window.elementor&&elementor.on){elementor.on("preview:loaded",s);}else{window.addEventListener("load",s);}})(' . wp_json_encode( $message ) . ');';
		wp_add_inline_script( 'elementor-editor', $script );
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
	 * `Design\Presets\Kit_Tab` → `presets/class-kit-tab.php`; `Design\Theme_Builder\Priority` →
	 * `theme-builder/class-priority.php`. Como mucho un subespacio de nombres.
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
		$slug = function ( string $part ): string {
			return str_replace( '_', '-', strtolower( $part ) );
		};
		$dir  = '' === self::$dir ? __DIR__ . '/' : self::$dir;
		$file = $dir . ( '' !== $parts[1] ? $slug( $parts[1] ) . '/' : '' ) . 'class-' . $slug( $parts[2] ) . '.php';
		return is_file( $file ) ? $file : '';
	}

	/** Carpeta del módulo, con barra final. */
	public static function dir(): string {
		return __DIR__ . '/';
	}
}
