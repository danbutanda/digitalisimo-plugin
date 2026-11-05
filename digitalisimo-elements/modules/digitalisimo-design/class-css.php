<?php
namespace Digitalisimo\Elements\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Utilidades CSS comunes de F7a: puras, sin estado y sin ganchos.
 *
 * Es la única validación de colores, longitudes y SVG del módulo: ningún submódulo escribe la suya (contrato A ↔
 * todos, §7.2 de la especificación).
 *
 * - `color()` acepta hexadecimal de 3, 4, 6 u 8 cifras, `rgb()`/`rgba()`/`hsl()`/`hsla()` con números,
 *   `transparent`, `currentColor` y `var(--e-global-color-<id>)`. Devuelve el valor recortado, sin cambiar sus
 *   mayúsculas (el editor lo repite igual en JS), o `null`.
 * - `global_color()` convierte `globals/colors?id=<id>` en `var(--e-global-color-<id>)`.
 * - `length()` valida un valor de SLIDER (`{ size, unit }`) o una cadena `10px` contra las unidades del control.
 * - `svg_data_uri()` sanea un SVG con el saneador del núcleo, aplica la lista de rechazo y el tope de 8 KB y lo
 *   devuelve como `url("data:image/svg+xml,…")` **codificado en porcentaje** (V.5): sin `;` ni base64, porque el
 *   analizador de reglas del núcleo (`Stylesheet::add_rules()` y `addRules` del editor) parte cada regla por `;`.
 * - `core_selectors()` lee los selectores de un control ya registrado en un elemento, para que una regla propia use
 *   exactamente los del núcleo (marcado optimizado y capa de efectos de movimiento incluidos).
 */
final class Css {
	/** Tope del SVG saneado y minimizado, en bytes. */
	const SVG_MAX = 8192;

	/** Tope del SVG recibido, antes de sanearlo: acota el trabajo del saneador. */
	const SVG_RAW_MAX = 65536;

	/**
	 * Lo que un SVG nunca puede contener, antes y después del saneado (3.3.4 de la especificación). Los `href` y
	 * `xlink:href` que no empiezan por `#` se comprueban aparte, en `svg_rejected()`.
	 */
	const SVG_DENIED = array( '<script', '<foreignobject', '<image', '<iframe', '<embed', '<object', '@import', 'url(', 'javascript:', '<!entity', '<?php' );

	/** Sufijos de las variantes responsivas que guarda el núcleo (`Breakpoints\Manager`, todos los puntos de corte). */
	const DEVICES = array( 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' );

	/**
	 * Color CSS validado, o `null`.
	 *
	 * @param mixed $raw Valor recibido.
	 */
	public static function color( $raw ): ?string {
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$value = trim( $raw );
		if ( '' === $value || strlen( $value ) > 64 ) {
			return null;
		}
		if ( preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^(?:transparent|currentcolor)$/i', $value ) ) {
			return 'transparent' === strtolower( $value ) ? 'transparent' : 'currentColor';
		}
		if ( preg_match( '/^var\(--e-global-color-[a-z0-9]+\)$/', $value ) ) {
			return $value;
		}
		$number = '[-+]?(?:\d+\.?\d*|\.\d+)';
		$part   = $number . '(?:%|deg|rad|turn)?';
		$comma  = '/^(?:rgba?|hsla?)\(\s*' . $part . '\s*,\s*' . $part . '\s*,\s*' . $part . '(?:\s*,\s*' . $number . '%?)?\s*\)$/i';
		$space  = '/^(?:rgba?|hsla?)\(\s*' . $part . '\s+' . $part . '\s+' . $part . '(?:\s*\/\s*' . $number . '%?)?\s*\)$/i';
		if ( preg_match( $comma, $value ) || preg_match( $space, $value ) ) {
			return $value;
		}
		return null;
	}

	/**
	 * `var(--e-global-color-<id>)` del global de color de un control, o `null`.
	 *
	 * @param array  $globals El `__globals__` del elemento (o sus ajustes completos, que lo contienen).
	 * @param string $key     Nombre del control.
	 */
	public static function global_color( array $globals, string $key ): ?string {
		if ( isset( $globals['__globals__'] ) && is_array( $globals['__globals__'] ) ) {
			$globals = $globals['__globals__'];
		}
		$value = isset( $globals[ $key ] ) ? $globals[ $key ] : null;
		if ( ! is_string( $value ) || ! preg_match( '/^globals\/colors\?id=([a-z0-9]+)$/', $value, $found ) ) {
			return null;
		}
		return 'var(--e-global-color-' . $found[1] . ')';
	}

	/**
	 * Longitud CSS validada contra las unidades del control, o `null` si está vacía o no es válida.
	 *
	 * - `{ size, unit }` (SLIDER): `size` numérico y `unit` en `$units`. Con la unidad `custom` (y sólo si el control
	 *   la admite), `size` es una expresión que pasa `expression()`.
	 * - Número o cadena `10px`: la unidad (o '' sin unidad) debe estar en `$units`.
	 *
	 * El número se normaliza con `number()`: `1.50` → `1.5`.
	 *
	 * @param mixed    $value Valor recibido.
	 * @param string[] $units Unidades del control (`size_units`); '' admite números sin unidad.
	 */
	public static function length( $value, array $units ): ?string {
		$units = array_map( 'strval', $units );
		if ( is_array( $value ) ) {
			$size = isset( $value['size'] ) ? $value['size'] : '';
			$unit = isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : '';
			if ( '' === $size || null === $size || ! in_array( $unit, $units, true ) ) {
				return null;
			}
			if ( 'custom' === $unit ) {
				return is_string( $size ) ? self::expression( $size ) : ( is_int( $size ) || is_float( $size ) ? self::number( $size ) : null );
			}
			$number = self::number( $size );
			return null === $number ? null : $number . $unit;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return in_array( '', $units, true ) ? self::number( $value ) : null;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\s*([-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?)\s*([a-z%]*)\s*$/i', $value, $found ) ) {
			return null;
		}
		$unit = strtolower( $found[2] );
		if ( ! in_array( $unit, $units, true ) ) {
			return null;
		}
		$number = self::number( $found[1] );
		return null === $number ? null : $number . $unit;
	}

	/**
	 * Número con hasta 6 decimales y sin ceros de más (`1.50` → `1.5`, `-0` → `0`), o `null` si no es un número finito
	 * de valor absoluto ≤ 1e9. En JS: `String( Number( n.toFixed( 6 ) ) )` con `-0` → `0`.
	 *
	 * @param mixed $value Valor recibido.
	 */
	public static function number( $value ): ?string {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return null;
		}
		$float = (float) $value;
		if ( ! is_finite( $float ) || abs( $float ) > 1e9 ) {
			return null;
		}
		$text = floor( $float ) === $float ? (string) (int) $float : rtrim( rtrim( sprintf( '%.6F', $float ), '0' ), '.' );
		return '-0' === $text ? '0' : $text;
	}

	/**
	 * Expresión de la unidad `custom` (`calc(100% - 2rem)`, `var(--x)`, `clamp(…)`), o `null`.
	 *
	 * Sólo números, unidades, operadores, paréntesis, comas y `var(--nombre)`; sin comillas, barras inversas, `//`,
	 * `;`, llaves, `@`, `<` ni funciones que lean recursos.
	 *
	 * @param string $value Expresión recibida.
	 */
	public static function expression( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value || strlen( $value ) > 200 || ! preg_match( '/^[A-Za-z0-9 #%.,()+\-_*\/]*$/', $value ) || false !== strpos( $value, '//' ) ) {
			return null;
		}
		if ( preg_match_all( '/([A-Za-z-]+)\s*\(/', $value, $calls ) ) {
			foreach ( $calls[1] as $function ) {
				if ( ! in_array( strtolower( $function ), array( 'calc', 'min', 'max', 'clamp', 'var' ), true ) ) {
					return null;
				}
			}
		}
		return $value;
	}

	/**
	 * `url("data:image/svg+xml,…")` de un SVG saneado, o `null` si no supera el saneado, la lista de rechazo o el tope.
	 *
	 * Codificación (la repite `scripts/design-library.mjs`):
	 * 1. Saneado con `Elementor\Core\Utils\Svg\Svg_Sanitizer::sanitize()`; sin él, `null`.
	 * 2. Espacios minimizados: cada tramo de espacios pasa a uno solo, se quitan los que hay entre etiquetas y en los
	 *    extremos.
	 * 3. Si el SVG no tiene comillas simples, sus comillas dobles pasan a simples.
	 * 4. `rawurlencode()` (RFC 3986; en JS, `encodeURIComponent()` más `!'()*` → `%21 %27 %28 %29 %2A`): `;`, `#`,
	 *    `{`, `}`, `%` y las comillas quedan codificados, así que la regla nunca lleva `;`.
	 *
	 * @param string $svg SVG recibido.
	 */
	public static function svg_data_uri( string $svg ): ?string {
		$min = self::svg_clean( $svg );
		if ( null === $min ) {
			return null;
		}
		if ( false === strpos( $min, "'" ) ) {
			$min = str_replace( '"', "'", $min );
		}
		return 'url("data:image/svg+xml,' . rawurlencode( $min ) . '")';
	}

	/**
	 * SVG saneado y minimizado (pasos 1 y 2 de `svg_data_uri()`), o `null`. Lo usan la máscara y el patrón para
	 * escribir la copia saneada en `uploads/digitalisimo-elements/masks/`.
	 *
	 * @param string $svg SVG recibido.
	 */
	public static function svg_clean( string $svg ): ?string {
		if ( '' === trim( $svg ) || strlen( $svg ) > self::SVG_RAW_MAX || self::svg_rejected( $svg ) ) {
			return null;
		}
		$sanitizer = 'Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer';
		if ( ! class_exists( $sanitizer ) || ! method_exists( $sanitizer, 'sanitize' ) ) {
			return null;
		}
		try {
			$clean = ( new $sanitizer() )->sanitize( $svg );
		} catch ( \Throwable $error ) {
			return null;
		}
		if ( ! is_string( $clean ) || '' === trim( $clean ) ) {
			return null;
		}
		$min = trim( (string) preg_replace( array( '/>\s+</', '/\s+/' ), array( '><', ' ' ), $clean ) );
		if ( '' === $min || 0 !== strpos( $min, '<svg' ) || strlen( $min ) > self::SVG_MAX || self::svg_rejected( $min ) ) {
			return null;
		}
		return $min;
	}

	/**
	 * El SVG contiene algo de la lista de rechazo: etiquetas o funciones prohibidas, atributos `on*` o un `href` /
	 * `xlink:href` que no empieza por `#`.
	 *
	 * @param string $svg SVG (crudo o saneado).
	 */
	public static function svg_rejected( string $svg ): bool {
		$lower = strtolower( $svg );
		if ( false !== strpos( $lower, "\0" ) ) {
			return true;
		}
		foreach ( self::SVG_DENIED as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return true;
			}
		}
		if ( preg_match( '/[\s"\'\/]on[a-z]+\s*=/i', $svg ) ) {
			return true;
		}
		if ( preg_match_all( '/(?:^|[\s"\'\/])(?:xlink:)?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $svg, $links, PREG_SET_ORDER ) ) {
			foreach ( $links as $link ) {
				$target = trim( implode( '', array_slice( $link, 1 ) ) );
				if ( '' === $target || '#' !== $target[0] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Selectores del control `$control` ya registrado en `$el`: las claves de su `selectors`.
	 *
	 * Sin `$wrapper`, `{{WRAPPER}}` queda sin sustituir (contrato de §7.2). Con una cadena, se sustituye
	 * `{{WRAPPER}}`; con un array, se aplica tal cual como tabla de sustitución (`[ '{{WRAPPER}}' => …, '{{ID}}' => … ]`).
	 * Lee también los controles de estilo que el núcleo aparta fuera del editor (`Performance::set_use_style_controls()`).
	 *
	 * @param \Elementor\Controls_Stack $el      Elemento.
	 * @param string                    $control Nombre del control.
	 * @param string|array|null         $wrapper Sustitución opcional.
	 * @return string[]
	 */
	public static function core_selectors( \Elementor\Controls_Stack $el, string $control, $wrapper = null ): array {
		$data = self::with_style_controls(
			function () use ( $el, $control ) {
				return $el->get_controls( $control );
			}
		);
		if ( ! is_array( $data ) || empty( $data['selectors'] ) || ! is_array( $data['selectors'] ) ) {
			return array();
		}
		$selectors = array_map( 'strval', array_keys( $data['selectors'] ) );
		if ( is_string( $wrapper ) ) {
			$wrapper = array( '{{WRAPPER}}' => $wrapper );
		}
		if ( is_array( $wrapper ) && $wrapper ) {
			$selectors = array_map(
				function ( $selector ) use ( $wrapper ) {
					return strtr( $selector, $wrapper );
				},
				$selectors
			);
		}
		return $selectors;
	}

	/**
	 * Ejecuta `$callback` con los controles de estilo visibles (`Performance::set_use_style_controls( true )`) y
	 * restaura después el valor anterior, también si lanza una excepción. Sin `Performance`, lo ejecuta tal cual.
	 *
	 * @param callable $callback Función sin argumentos.
	 * @return mixed Lo que devuelva `$callback`.
	 */
	public static function with_style_controls( callable $callback ) {
		$performance = 'Elementor\\Core\\Frontend\\Performance';
		if ( ! method_exists( $performance, 'set_use_style_controls' ) || ! method_exists( $performance, 'is_use_style_controls' ) ) {
			return $callback();
		}
		$previous = (bool) $performance::is_use_style_controls();
		$performance::set_use_style_controls( true );
		try {
			return $callback();
		} finally {
			$performance::set_use_style_controls( $previous );
		}
	}
}
