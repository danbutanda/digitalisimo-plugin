<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Único validador de los valores que la migración interpola en CSS (F8, V.7).
 *
 * Los valores de los controles de Element Pack y Prime Slider (colores, tamaños, sombras, degradados, recortes,
 * retardos…) se escriben como texto dentro de `custom_css`, que se imprime en `<style>` o en el archivo CSS del
 * documento. Quien editó la página sin `unfiltered_html` pudo guardar en un color `red;}body{display:none}`: el núcleo
 * quita `</style><script>` al guardar, pero no esa inyección. Por eso ningún valor llega al CSS sin pasar por aquí:
 *
 * - por tipo: color (la expresión de `class-figures.php` de Elements: hexadecimal, rgb/hsl, `var(--…)`,
 *   `currentColor`, más `transparent`), número con una unidad de una lista cerrada, enumeración cerrada, degradado
 *   reconstruido desde partes validadas; nunca `url()`;
 * - rechazo de `<`, `{`, `}`, `;`, `\`, `@`, `url(` y `expression(` dentro de cualquier valor;
 * - comprobación final de cada bloque generado (`block()`): sin `</`, `@import`, `expression(`, `javascript:`,
 *   `behavior:` ni `-moz-binding`.
 *
 * Todo devuelve el valor normalizado o null (el valor no se escribe). Lo usan los convertidores de extensiones
 * (frente C), Prime Slider (E: `aspect-ratio`, CSS del slider) y la plataforma (H: código global).
 */
final class Css_Value {
	/** Unidades admitidas por omisión. */
	const UNITS = array( 'px', 'em', 'rem', '%', 'vw', 'vh', 'deg', 'ms', 's' );

	/** Palabras clave de color admitidas además de las formas numéricas. */
	const COLOR_WORDS = array( 'currentColor', 'currentcolor', 'transparent' );

	/** Fragmentos que nunca pueden aparecer dentro de un valor. */
	const FORBIDDEN = array( '<', '>', '{', '}', ';', '\\', '@', 'url(', 'expression(', 'javascript:', '/*', '*/' );

	/** Fragmentos que nunca pueden aparecer en un bloque generado. */
	const BLOCK_FORBIDDEN = array( '</', '@import', 'expression(', 'javascript:', 'behavior:', '-moz-binding', '@\\' );

	/** ¿El texto puede ir dentro de una declaración? Sin llaves, punto y coma, barras, arrobas, `url(` ni `expression(`. */
	public static function safe( $value ): bool {
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		$value = strtolower( (string) $value );
		if ( '' === trim( $value ) || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return false;
		}
		foreach ( self::FORBIDDEN as $needle ) {
			if ( false !== strpos( $value, $needle ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Color de Elementor: hexadecimal de 3 a 8 cifras, `rgb()`/`rgba()`/`hsl()`/`hsla()` con números, `var(--nombre)`,
	 * `currentColor` o `transparent`. Cualquier otra cosa, null.
	 */
	public static function color( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( '' === $value || ! self::safe( $value ) ) {
			return null;
		}
		if ( in_array( $value, self::COLOR_WORDS, true ) ) {
			return 'currentcolor' === $value ? 'currentColor' : $value;
		}
		return preg_match( '/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,\s%deg]+\)|var\(--[a-zA-Z0-9_-]+\))$/', $value ) ? $value : null;
	}

	/**
	 * Número sin unidad (escala, opacidad, índice z, columnas…), opcionalmente acotado. Acepta el valor de un deslizador
	 * de Elementor (`{size}`).
	 */
	public static function number( $value, ?float $min = null, ?float $max = null ): ?string {
		if ( is_array( $value ) ) {
			$value = $value['size'] ?? null;
		}
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( ! preg_match( '/^-?(\d+(\.\d+)?|\.\d+)$/', $value ) ) {
			return null;
		}
		$number = (float) $value;
		if ( ( null !== $min && $number < $min ) || ( null !== $max && $number > $max ) ) {
			return null;
		}
		return self::format( $number );
	}

	/**
	 * Número con unidad de una lista cerrada: `12px`, `1.5em`, `-30deg`. Acepta un deslizador de Elementor
	 * (`{unit, size}`) o un texto. Sin unidad, la de `$default_unit` (si está en la lista). Acotado si se pide.
	 */
	public static function size( $value, array $units = self::UNITS, string $default_unit = 'px', ?float $min = null, ?float $max = null ): ?string {
		$units = array_values( array_intersect( $units, self::UNITS ) );
		if ( is_array( $value ) ) {
			$size = $value['size'] ?? null;
			$unit = isset( $value['unit'] ) && is_scalar( $value['unit'] ) && '' !== (string) $value['unit'] ? (string) $value['unit'] : $default_unit;
		} elseif ( is_scalar( $value ) && ! is_bool( $value ) && preg_match( '/^\s*(-?(?:\d+(?:\.\d+)?|\.\d+))\s*([a-z%]*)\s*$/i', (string) $value, $m ) ) {
			$size = $m[1];
			$unit = '' !== $m[2] ? strtolower( $m[2] ) : $default_unit;
		} else {
			return null;
		}
		$number = self::number( $size, $min, $max );
		if ( null === $number || ! in_array( $unit, $units, true ) ) {
			return null;
		}
		return $number . $unit;
	}

	/** Valor de una enumeración cerrada (comparación exacta). */
	public static function enum( $value, array $allowed ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = (string) $value;
		return in_array( $value, array_map( 'strval', $allowed ), true ) && self::safe( $value ) ? $value : null;
	}

	/**
	 * Medidas de un control DIMENSIONS (`{top, right, bottom, left, unit}`) como `T R B L`; null si alguna parte no es
	 * un número válido. Las partes vacías valen 0, como en el núcleo.
	 */
	public static function dimensions( $value, array $units = array( 'px', 'em', 'rem', '%', 'vw', 'vh' ) ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$unit  = isset( $value['unit'] ) && is_scalar( $value['unit'] ) && '' !== (string) $value['unit'] ? (string) $value['unit'] : 'px';
		$parts = array();
		$any   = false;
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$raw = $value[ $side ] ?? '';
			if ( '' === $raw || null === $raw ) {
				$parts[] = '0';
				continue;
			}
			$size = self::size( array( 'size' => $raw, 'unit' => $unit ), $units );
			if ( null === $size ) {
				return null;
			}
			$any     = true;
			$parts[] = (float) $raw === 0.0 ? '0' : $size;
		}
		return $any ? implode( ' ', $parts ) : null;
	}

	/**
	 * Sombra de caja del grupo del núcleo (`{horizontal, vertical, blur, spread, color}` y posición `''`|`inset`).
	 */
	public static function shadow( $value, string $position = '' ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$color = self::color( $value['color'] ?? 'rgba(0,0,0,0.5)' );
		$parts = array();
		foreach ( array( 'horizontal' => 0, 'vertical' => 0, 'blur' => 10, 'spread' => 0 ) as $key => $default ) {
			$number = self::number( $value[ $key ] ?? $default );
			if ( null === $number ) {
				return null;
			}
			$parts[] = '0' === $number ? '0' : $number . 'px';
		}
		if ( null === $color ) {
			return null;
		}
		return ( 'inset' === $position ? 'inset ' : '' ) . implode( ' ', $parts ) . ' ' . $color;
	}

	/** Sombra de texto del grupo del núcleo (`{horizontal, vertical, blur, color}`). */
	public static function text_shadow( $value ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$color = self::color( $value['color'] ?? 'rgba(0,0,0,0.3)' );
		$parts = array();
		foreach ( array( 'horizontal' => 0, 'vertical' => 0, 'blur' => 10 ) as $key => $default ) {
			$number = self::number( $value[ $key ] ?? $default );
			if ( null === $number ) {
				return null;
			}
			$parts[] = '0' === $number ? '0' : $number . 'px';
		}
		return null === $color ? null : implode( ' ', $parts ) . ' ' . $color;
	}

	/**
	 * Degradado reconstruido desde partes validadas:
	 * `{ type: linear|radial, angle: <grados>, position: <palabras>, stops: [ [color, posición %|null], … ] }`.
	 * Con menos de dos colores válidos, null. Nunca copia el texto del origen.
	 */
	public static function gradient( array $parts ): ?string {
		$type  = 'radial' === ( $parts['type'] ?? 'linear' ) ? 'radial' : 'linear';
		$stops = array();
		foreach ( (array) ( $parts['stops'] ?? array() ) as $stop ) {
			$stop  = (array) $stop;
			$color = self::color( $stop[0] ?? null );
			if ( null === $color ) {
				return null;
			}
			$at = null;
			if ( isset( $stop[1] ) && null !== $stop[1] && '' !== $stop[1] ) {
				$at = self::size( $stop[1], array( '%', 'px' ), '%' );
				if ( null === $at ) {
					return null;
				}
			}
			$stops[] = $color . ( null !== $at ? ' ' . $at : '' );
		}
		if ( count( $stops ) < 2 ) {
			return null;
		}
		if ( 'radial' === $type ) {
			$position = self::enum( $parts['position'] ?? 'center center', array( 'center center', 'center left', 'center right', 'top center', 'top left', 'top right', 'bottom center', 'bottom left', 'bottom right' ) );
			return 'radial-gradient(at ' . ( $position ?? 'center center' ) . ', ' . implode( ', ', $stops ) . ')';
		}
		$angle = self::size( $parts['angle'] ?? '180deg', array( 'deg' ), 'deg', -360, 360 );
		return 'linear-gradient(' . ( $angle ?? '180deg' ) . ', ' . implode( ', ', $stops ) . ')';
	}

	/** Curva de tiempo: palabra clave o `cubic-bezier()` con cuatro números. */
	public static function easing( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end' ), true ) ) {
			return $value;
		}
		return preg_match( '/^cubic-bezier\(\s*-?[0-9.]+\s*,\s*-?[0-9.]+\s*,\s*-?[0-9.]+\s*,\s*-?[0-9.]+\s*\)$/', $value ) ? preg_replace( '/\s+/', '', $value ) : null;
	}

	/**
	 * Forma de recorte (`clip-path`): sólo `polygon()`, `inset()`, `circle()` y `ellipse()` con números, `%`, `px`, `em`,
	 * comas, espacios y las palabras `at`/`round`, hasta 200 caracteres.
	 */
	public static function clip( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		if ( '' === $value || strlen( $value ) > 200 || ! self::safe( $value ) ) {
			return null;
		}
		if ( ! preg_match( '/^(polygon|inset|circle|ellipse)\(([^()]*)\)$/', $value, $m ) ) {
			return null;
		}
		$inner = (string) preg_replace( '/\b(at|round)\b|(?<=\d)(px|em)\b/', ' ', $m[2] );
		return preg_match( '/^[0-9.%\s,-]*$/', $inner ) && preg_match( '/\d/', $inner ) ? $value : null;
	}

	/** Nombre de familia tipográfica: letras, cifras, espacios y guiones. */
	public static function font_family( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' !== $value && strlen( $value ) <= 80 && preg_match( '/^[\p{L}0-9 _-]+$/u', $value ) ? $value : null;
	}

	/** Nombre de una animación o clase generada por la migración (`[a-z][a-z0-9-]*`). */
	public static function ident( $value ): ?string {
		return is_scalar( $value ) && preg_match( '/^[a-z][a-z0-9_-]{0,40}$/', (string) $value ) ? (string) $value : null;
	}

	/**
	 * Comprobación final de un bloque CSS generado (o del código global que valida H): sin cierre de etiqueta,
	 * `@import`, `expression(`, `javascript:`, `behavior:`, `-moz-binding` ni reglas con barra invertida, y con las
	 * llaves equilibradas. Los comentarios no esconden nada: se comprueba también sin ellos.
	 */
	public static function block( string $css ): bool {
		$lower    = strtolower( $css );
		$stripped = (string) preg_replace( '#/\*.*?\*/#s', '', $lower );
		foreach ( array( $lower, $stripped, preg_replace( '/\s+/', '', $stripped ) ) as $text ) {
			foreach ( self::BLOCK_FORBIDDEN as $needle ) {
				if ( false !== strpos( (string) $text, $needle ) ) {
					return false;
				}
			}
		}
		if ( preg_match( '/[\x00-\x08\x0B\x0E-\x1F]/', $css ) ) {
			return false;
		}
		$depth = 0;
		$len   = strlen( $stripped );
		for ( $i = 0; $i < $len; $i++ ) {
			if ( '{' === $stripped[ $i ] ) {
				++$depth;
			} elseif ( '}' === $stripped[ $i ] ) {
				if ( --$depth < 0 ) {
					return false;
				}
			}
		}
		return 0 === $depth;
	}

	/** Número sin ceros sobrantes ni notación científica. */
	public static function format( float $number ): string {
		$text = rtrim( rtrim( sprintf( '%.4F', $number ), '0' ), '.' );
		return '-0' === $text || '' === $text ? '0' : $text;
	}
}
