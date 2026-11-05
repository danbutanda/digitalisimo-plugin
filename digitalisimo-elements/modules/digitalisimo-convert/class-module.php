<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Sustitución de los widgets de PRO por widgets propios (`docs/PRO_WIDGETS_OBJECTIVE.md`). Arranque: autocarga acotada
 * a `Digitalisimo\Elements\Convert\` y el filtro que oculta del panel los widgets ya sustituidos. Nada corre en el
 * front: el catálogo, los mapas y la conversión sólo se cargan cuando DIGITALÍSIMO Migración, el importador de kits o
 * el editor los piden.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Convert\\';

	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'elementor/document/config', array( __NAMESPACE__ . '\\Panel', 'document_config' ), 10, 2 );
		}
	}

	/** Carga una clase del módulo; cualquier otro nombre se ignora sin tocar el disco. */
	public static function autoload( $class ): void {
		$class = ltrim( (string) $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}
		$name = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $name ) ) {
			return;
		}
		$file = __DIR__ . '/class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
}
