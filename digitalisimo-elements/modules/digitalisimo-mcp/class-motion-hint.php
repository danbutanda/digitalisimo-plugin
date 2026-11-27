<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Cuando a una herramienta de estilos del MCP del constructor le llega movimiento (`animation`, `animation-*` o
 * `@keyframes` en el CSS de un elemento, una clase o un estilo por defecto) o su respuesta avisa que lo descartó, la
 * respuesta dice qué usar en su lugar: la marquesina atómica `e-digitalisimo-marquee` para textos que se desplazan (con
 * pausa y «reducir movimiento»), o las interacciones del elemento para entradas y efectos. Un estilo de elemento no
 * puede declarar `@keyframes` (es una regla de primer nivel), así que `animation` no tiene qué mover; según la licencia
 * del sitio, ni siquiera se guarda (va al CSS propio del estilo). Caso digitalisimo.lat (2026-11-24): la franja de
 * clientes acabó con `@keyframes` en el CSS propio de la página.
 *
 * Sólo añade texto a la respuesta: no cambia qué se guarda ni toca `elementor/`. Se engancha envolviendo el
 * `execute_callback` de las herramientas que reciben CSS (`wp_register_ability_args`, como `Page_Template`).
 */
final class Motion_Hint {
	/** Herramientas que aplican CSS a elementos, clases o estilos por defecto. */
	const ABILITIES = array(
		'elementor/build-composition',
		'elementor/manage-elements',
		'elementor/manage-classes',
		'elementor/manage-default-styles',
		'elementor/manage-component',
	);

	/** Una declaración de movimiento dentro del CSS que el núcleo cita al descartarlo. */
	const PATTERN = '/(?:^|[\s;{,:])(?:-webkit-)?animation(?:-[a-z-]+)?\s*:|@(?:-webkit-)?keyframes\b/i';

	const HINT = 'DIGITALÍSIMO: el estilo de un elemento no puede declarar @keyframes, así que animation no tiene qué mover (y en algunos sitios ni se guarda). Para textos que se desplazan en bucle usa el widget atómico «e-digitalisimo-marquee» (Marquesina: un elemento por renglón en items, separator, accent, duration y direction; trae botón de pausa y respeta prefers-reduced-motion) en lugar de CSS propio de la página. Para entradas o efectos al aparecer, usa las interacciones del elemento.';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap_ability' ), PHP_INT_MAX, 2 );
	}

	/** Envuelve la ejecución de las herramientas de `ABILITIES`; las demás pasan igual. */
	public static function wrap_ability( $args, $name = '' ) {
		if ( ! in_array( $name, self::ABILITIES, true ) || ! is_array( $args ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			return $args;
		}
		$inner                    = $args['execute_callback'];
		$args['execute_callback'] = static function ( ...$params ) use ( $inner ) {
			return self::annotate( call_user_func_array( $inner, $params ), $params[0] ?? null );
		};
		return $args;
	}

	/**
	 * La respuesta con la pista si el CSS pedido trae movimiento o algún aviso cita movimiento descartado: al final de
	 * `warnings` (lista) o, si la respuesta no la trae, en `hints`. Sin movimiento, un error o algo que no es un arreglo
	 * salen igual.
	 *
	 * @param mixed $result Respuesta de la herramienta.
	 * @param mixed $input  Entrada de la herramienta (sólo se leen sus CSS: claves `style`, `styles` y `css`).
	 */
	public static function annotate( $result, $input = null ) {
		if ( ! is_array( $result ) || ! ( self::mentions_motion( $result ) || self::input_motion( $input, 0, false ) ) ) {
			return $result;
		}
		if ( isset( $result['warnings'] ) && is_array( $result['warnings'] ) && array_values( $result['warnings'] ) === $result['warnings'] ) {
			if ( ! in_array( self::HINT, $result['warnings'], true ) ) {
				$result['warnings'][] = self::HINT;
			}
			return $result;
		}
		$hints           = isset( $result['hints'] ) && is_array( $result['hints'] ) ? $result['hints'] : array();
		$hints[]         = self::HINT;
		$result['hints'] = array_values( array_unique( $hints ) );
		return $result;
	}

	/** ¿Algún aviso (texto en `warnings`, `errors` o `results`, a cualquier profundidad) cita movimiento? */
	public static function mentions_motion( array $result ): bool {
		foreach ( array( 'warnings', 'errors', 'results', 'message' ) as $key ) {
			if ( isset( $result[ $key ] ) && self::scan( $result[ $key ], 0 ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Trae el CSS de la entrada (bajo `style`, `styles` o `css`, a cualquier profundidad) una declaración de movimiento? */
	public static function input_motion( $value, int $depth, bool $css ): bool {
		if ( is_string( $value ) ) {
			return $css && 1 === preg_match( self::PATTERN, $value );
		}
		if ( ! is_array( $value ) || $depth > 6 ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( self::input_motion( $item, $depth + 1, $css || in_array( $key, array( 'style', 'styles', 'css' ), true ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function scan( $value, int $depth ): bool {
		if ( is_string( $value ) ) {
			return 1 === preg_match( self::PATTERN, $value );
		}
		if ( ! is_array( $value ) || $depth > 6 ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( self::scan( $item, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}
}
