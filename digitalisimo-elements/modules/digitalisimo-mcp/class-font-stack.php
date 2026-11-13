<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * `font-family` con varias familias en los estilos atómicos (p. ej. `"Geist Mono", ui-monospace, monospace`, como lo
 * escribe el MCP con `build-composition`, `manage-elements` o `manage-classes`).
 *
 * El transformador del núcleo (`Font_Family_Transformer`, modules/atomic-widgets/props-resolver/transformers/styles/
 * font-family-transformer.php) modela una sola familia: si el valor no empieza y termina con comillas y tiene un
 * espacio, lo envuelve entero, y una lista con una familia entre comillas sale `""Geist Mono", ui-monospace,
 * monospace"`, una declaración que el navegador descarta. Aquí, sólo cuando el valor es una lista (comas fuera de
 * comillas y paréntesis), cada familia se escribe como en CSS: la que ya va entre comillas queda igual, la de varias
 * palabras se envuelve y la de una palabra (genérica, palabra clave, `var(…)`) queda sin comillas. Una sola familia
 * sigue en el transformador del núcleo, sin cambios. El valor guardado no se toca: lo ya escrito se corrige al
 * regenerarse su CSS.
 */
final class Font_Stack {
	const KEY      = 'font-family';
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** Idempotente: el registro de transformadores de estilos se arma la primera vez que se pinta CSS atómico. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/atomic-widgets/styles/transformers/register', array( __CLASS__, 'register' ), self::PRIORITY, 1 );
	}

	/** Envuelve el transformador de `font-family` del núcleo (registrado antes, con prioridad 10). */
	public static function register( $registry ): void {
		$base = 'Elementor\\Modules\\AtomicWidgets\\PropsResolver\\Transformer_Base';
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) || ! method_exists( $registry, 'get' ) || ! class_exists( $base ) ) {
			return;
		}
		require_once __DIR__ . '/class-font-family-transformer.php';
		$inner = $registry->get( self::KEY );
		$registry->register( self::KEY, new Font_Family_Transformer( $inner instanceof $base ? $inner : null ) );
	}

	/**
	 * Las familias de una lista separada por comas (fuera de comillas y paréntesis), sin espacios alrededor ni vacías.
	 *
	 * @return string[]
	 */
	public static function split( string $value ): array {
		$parts = array();
		$part  = '';
		$quote = '';
		$depth = 0;
		$len   = strlen( $value );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $value[ $i ];
			if ( '' !== $quote ) {
				$part .= $c;
				if ( '\\' === $c && $i + 1 < $len ) {
					$part .= $value[ ++$i ];
				} elseif ( $c === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c && $depth > 0 ) {
				--$depth;
			} elseif ( ',' === $c && 0 === $depth ) {
				$parts[] = $part;
				$part    = '';
				continue;
			}
			$part .= $c;
		}
		$parts[] = $part;
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	}

	/** Una familia como debe ir en una lista: con comillas sólo si tiene espacios y no las trae ni es una función. */
	public static function family( string $family ): string {
		$family = trim( $family );
		$first  = substr( $family, 0, 1 );
		if ( '' === $family || ( ( '"' === $first || "'" === $first ) && substr( $family, -1 ) === $first && strlen( $family ) > 1 ) ) {
			return $family;
		}
		if ( preg_match( '/^[A-Za-z-]+\(.*\)$/s', $family ) || ! preg_match( '/\s/', $family ) ) {
			return $family;
		}
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), preg_replace( '/\s+/', ' ', $family ) ) . '"';
	}

	/** La lista lista para CSS, o null si el valor no es una lista (una sola familia: la resuelve el núcleo). */
	public static function normalize( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$families = self::split( $value );
		if ( count( $families ) < 2 ) {
			return null;
		}
		return implode( ', ', array_map( array( __CLASS__, 'family' ), $families ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
