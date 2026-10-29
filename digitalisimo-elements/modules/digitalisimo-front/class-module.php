<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Rendimiento del front de DIGITALÍSIMO Elements (F-front, Fase 2 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`).
 *
 * Arranque del módulo: registra la autocarga acotada a `Digitalisimo\Elements\Front\` y llama `init()` de cada clase
 * que exista. Sin clases, nada cambia: cada frente se comporta como hoy mientras su archivo no esté.
 *
 * - `Settings` (opciones de sitio y red) y `Transition` (valor aplicado, evento y cerrojo) son el contrato que leen
 *   la puerta, los valores por defecto del núcleo y el cargador de librerías: lo único que esos frentes leen en el
 *   front es `Transition::applied()`.
 * - `Transition`, `Core_Defaults`, `Pro_Gate`, `Lazy_Libs` y `Lazy_Videos` se inician en cada petición; `Core_Defaults` necesita
 *   engancharse antes de `init` 0, por eso el módulo arranca en `pro_elements_load_plugin_func()` (`plugins_loaded`).
 * - `Settings_Page` (pantalla «Rendimiento» y sus acciones de `admin-post.php`) sólo en la administración.
 * - `Icon_Scan` no tiene ganchos: la cargan la transición y la pantalla cuando la necesitan.
 *
 * Correspondencia de clases y archivos: `Pro_Gate` vive en `class-pro-gate.php`, `Settings_Page` en
 * `class-settings-page.php`. Un nombre fuera del espacio de nombres o con otros caracteres nunca llega al disco.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Front\\';

	/** Clases que se inician en cada petición, en este orden (la transición primero: las demás leen su valor). */
	const BOOT = array( 'Transition', 'Core_Defaults', 'Pro_Gate', 'Lazy_Libs', 'Lazy_Videos' );

	/** Clases que sólo se inician en la administración. */
	const ADMIN = array( 'Settings_Page' );

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $autoloader = false;

	/** Punto de entrada. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::register_autoloader();
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		foreach ( self::BOOT as $class ) {
			self::call( $class, 'init' );
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			foreach ( self::ADMIN as $class ) {
				self::call( $class, 'init' );
			}
		}
	}

	/** Llama a un método estático de una clase del módulo si la clase y el método existen. */
	private static function call( string $class, string $method ): void {
		$class = self::NAMESPACE_PREFIX . $class;
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			call_user_func( array( $class, $method ) );
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

	/** Archivo existente de una clase del módulo, o '' si no es del módulo o no existe. */
	public static function file_for( string $class ): string {
		$class = ltrim( $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return '';
		}
		$name = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $name ) ) {
			return '';
		}
		$file = __DIR__ . '/class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		return is_file( $file ) ? $file : '';
	}

	/** Carpeta del módulo, con barra final. */
	public static function dir(): string {
		return __DIR__ . '/';
	}
}
