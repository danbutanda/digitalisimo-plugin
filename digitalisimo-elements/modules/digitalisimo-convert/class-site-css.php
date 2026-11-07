<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * CSS del sitio que nombra clases de los widgets de PRO (programa «sin widgets de PRO», corrección de fidelidad de C5 y
 * C6: `docs/pro/c5.md` y `docs/pro/c6.md`). El traductor (`Translator`) ya pasa a las clases propias el CSS personalizado
 * del elemento convertido; el resto del CSS del sitio (el del kit, el CSS adicional del Personalizador, el de la página y
 * el de otros elementos) seguía nombrando `.elementor-widget-posts .elementor-post__title`, `.elementor-widget-form`…, y
 * dejaba de aplicarse tras «Migrar». Aquí ese CSS gana, detrás de cada regla que nombra clases de PRO, la misma regla con
 * los selectores traducidos por los mapas listos (`classes` de cada mapa), así que los widgets propios reciben lo que
 * recibían los de PRO:
 *
 * - **Aditivo:** la regla original no cambia (sigue valiendo para un widget de PRO que no se convirtió, para el marcado
 *   del propio sitio que use esas clases y para cualquier otro lugar); las traducidas van después, marcadas con
 *   `MARK`, y sólo nombran clases propias.
 * - **Qué mapa:** un selector con el envoltorio de un widget de PRO (`.elementor-widget-<widget>`) se traduce sólo con el
 *   mapa de ese widget; uno sin envoltorio, con cada mapa que nombre alguna de sus clases (varias traducciones si varios
 *   widgets de PRO usaban la misma clase, como en PRO, donde la regla los alcanzaba a todos). Las declaraciones sólo
 *   cambian los nombres de variables CSS que el mapa traduce (`--cart-…` → `--mc-…`).
 * - **Idempotente y reversible:** antes de traducir se quitan las reglas marcadas de una pasada anterior (`strip()`), así
 *   que «Migrar» y un kit pueden repetirse sin duplicar nada, y `--revert` sólo quita las reglas marcadas, sin pisar lo
 *   que se editó después.
 * - Los bloques `@media`, `@supports`, `@container`, `@layer`, `@scope` y `@document` se recorren por dentro; las demás
 *   reglas `@` (`@font-face`, `@keyframes`, `@page`, `@import`…) y los comentarios quedan tal cual.
 *
 * Pura: no lee ni escribe la base de datos. La usan Migración (etapa `pro`) y los kits (`Import\Kits\Pro_Convert`).
 */
final class Site_Css {
	/** Marca de cada regla traducida (la siguiente regla tras el comentario es la traducción). */
	const MARK = '/*digitalisimo-pro*/';

	/** Reglas `@` cuyo bloque es una lista de reglas. */
	const NESTED = array( 'media', 'supports', 'container', 'layer', 'scope', 'document', '-moz-document' );

	/**
	 * Clases de los mapas listos: widget de PRO => `classes` del mapa (sólo los mapas que traducen alguna).
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function maps(): array {
		$out = array();
		foreach ( glob( __DIR__ . '/maps/*.php' ) ?: array() as $file ) {
			$name = basename( $file, '.php' );
			if ( '_' === $name[0] ) {
				continue;
			}
			// Un mapa que no puede cargarse en esta petición no impide traducir con los demás.
			try {
				$map = Registry::map( $name );
			} catch ( \Throwable $error ) {
				continue;
			}
			if ( is_array( $map ) && ! empty( $map['classes'] ) && is_array( $map['classes'] ) ) {
				$out[ (string) $map['source'] ] = $map['classes'];
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * CSS con las traducciones de las reglas que nombran clases de PRO (sin las de una pasada anterior).
	 *
	 * @param string     $css  CSS del sitio.
	 * @param array|null $maps Widget de PRO => clases; por defecto, los mapas listos.
	 */
	public static function rewrite( string $css, ?array $maps = null ): string {
		$maps = null === $maps ? self::maps() : $maps;
		$css  = self::strip( $css );
		if ( '' === trim( $css ) || ! $maps || ! self::any_key( $css, $maps ) ) {
			return $css;
		}
		return self::walk( $css, $maps, true );
	}

	/** CSS sin las reglas que añadió `rewrite()` (para `--revert` y antes de volver a traducir). */
	public static function strip( string $css ): string {
		if ( false === strpos( $css, self::MARK ) ) {
			return $css;
		}
		return self::walk( $css, array(), false );
	}

	/** ¿Lleva el CSS reglas añadidas por `rewrite()`? */
	public static function marked( string $css ): bool {
		return false !== strpos( $css, self::MARK );
	}

	/**
	 * `custom_css` de cada elemento de un árbol (a cualquier profundidad) con las traducciones.
	 *
	 * @return array{0: array, 1: int} Árbol y número de elementos cuyo CSS cambió.
	 */
	public static function tree( array $elements, ?array $maps = null, int $depth = 0 ): array {
		$maps    = null === $maps ? self::maps() : $maps;
		$changed = 0;
		if ( $depth > 256 ) {
			return array( $elements, 0 );
		}
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings']['custom_css'] ) && is_string( $element['settings']['custom_css'] ) && '' !== $element['settings']['custom_css'] ) {
				$css = self::rewrite( $element['settings']['custom_css'], $maps );
				if ( $css !== $element['settings']['custom_css'] ) {
					$element['settings']['custom_css'] = $css;
					++$changed;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				list( $element['elements'], $inner ) = self::tree( $element['elements'], $maps, $depth + 1 );
				$changed                            += $inner;
			}
			$elements[ $index ] = $element;
		}
		return array( $elements, $changed );
	}

	/** ¿Nombra el CSS alguna clase de un mapa? (filtro rápido) */
	private static function any_key( string $css, array $maps ): bool {
		foreach ( $maps as $classes ) {
			foreach ( array_keys( $classes ) as $from ) {
				if ( false !== strpos( $css, (string) $from ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Recorre una lista de reglas. Con `$translate`, añade las traducciones; siempre quita las reglas marcadas.
	 */
	private static function walk( string $css, array $maps, bool $translate ): string {
		$out  = '';
		$i    = 0;
		$n    = strlen( $css );
		$skip = false;
		while ( $i < $n ) {
			$c = $css[ $i ];
			// Comentarios: la marca hace saltar la regla siguiente.
			if ( '/' === $c && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $css, '*/', $i + 2 );
				$end = false === $end ? $n : $end + 2;
				$com = substr( $css, $i, $end - $i );
				if ( self::MARK === $com ) {
					$skip = true;
				} else {
					$out .= $com;
				}
				$i = $end;
				continue;
			}
			if ( ctype_space( $c ) ) {
				if ( ! $skip ) {
					$out .= $c;
				}
				++$i;
				continue;
			}
			// Preludio hasta `{` o `;` fuera de cadenas, comentarios y paréntesis.
			list( $prelude, $stop, $i ) = self::until( $css, $i, array( '{', ';' ) );
			if ( ';' === $stop || '' === $stop ) {
				if ( ! $skip ) {
					$out .= $prelude . $stop;
				}
				$skip = false;
				continue;
			}
			list( $body, $i ) = self::block( $css, $i );
			if ( $skip ) {
				$skip = false;
				continue;
			}
			$head = ltrim( $prelude );
			if ( '@' === ( $head[0] ?? '' ) ) {
				$name = strtolower( (string) preg_replace( '/^@([\w-]+).*$/s', '$1', $head ) );
				$out .= $prelude . '{' . ( in_array( $name, self::NESTED, true ) ? self::walk( $body, $maps, $translate ) : $body ) . '}';
				continue;
			}
			$out .= $prelude . '{' . $body . '}';
			if ( $translate ) {
				$out .= self::translations( $prelude, $body, $maps );
			}
		}
		return $out;
	}

	/** Las reglas traducidas de una regla de estilo (una por grupo de declaraciones), cada una con la marca. */
	private static function translations( string $prelude, string $body, array $maps ): string {
		$selectors = self::split( $prelude );
		$groups    = array();
		$seen      = array_flip( array_map( 'trim', $selectors ) );
		foreach ( $selectors as $selector ) {
			$selector = trim( $selector );
			if ( '' === $selector ) {
				continue;
			}
			$scoped = array();
			foreach ( array_keys( $maps ) as $source ) {
				if ( preg_match( '/\.elementor-widget-' . preg_quote( (string) $source, '/' ) . '(?![\w-])/', $selector ) ) {
					$scoped[] = $source;
				}
			}
			foreach ( $scoped ? $scoped : array_keys( $maps ) as $source ) {
				$classes = array();
				foreach ( $maps[ $source ] as $from => $to ) {
					if ( '.' === ( (string) $from )[0] ) {
						$classes[ $from ] = $to;
					}
				}
				$done = Translator::rewrite_classes( $selector, $classes );
				if ( $done === $selector || isset( $seen[ $done ] ) ) {
					continue;
				}
				$seen[ $done ] = true;
				$vars          = array();
				foreach ( $maps[ $source ] as $from => $to ) {
					if ( 0 === strpos( (string) $from, '--' ) ) {
						$vars[ $from ] = $to;
					}
				}
				$decl                       = $vars ? Translator::rewrite_classes( $body, $vars ) : $body;
				$groups[ $decl ]            = $groups[ $decl ] ?? array();
				$groups[ $decl ][]          = $done;
			}
		}
		$out = '';
		foreach ( $groups as $decl => $list ) {
			$out .= self::MARK . implode( ',', $list ) . '{' . $decl . '}';
		}
		return $out;
	}

	/**
	 * Texto desde `$i` hasta el primer carácter de `$stops` fuera de cadenas, comentarios y paréntesis.
	 *
	 * @return array{0: string, 1: string, 2: int} Texto, carácter de parada ('' al final) y posición tras él.
	 */
	private static function until( string $css, int $i, array $stops ): array {
		$n     = strlen( $css );
		$start = $i;
		$depth = 0;
		while ( $i < $n ) {
			$c = $css[ $i ];
			if ( '"' === $c || "'" === $c ) {
				$i = self::string_end( $css, $i );
				continue;
			}
			if ( '/' === $c && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $css, '*/', $i + 2 );
				$i   = false === $end ? $n : $end + 2;
				continue;
			}
			if ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ( ')' === $c || ']' === $c ) && $depth > 0 ) {
				--$depth;
			} elseif ( 0 === $depth && in_array( $c, $stops, true ) ) {
				return array( substr( $css, $start, $i - $start ), $c, $i + 1 );
			}
			++$i;
		}
		return array( substr( $css, $start ), '', $n );
	}

	/**
	 * Contenido de un bloque cuya `{` ya se leyó, hasta su `}` (con bloques anidados).
	 *
	 * @return array{0: string, 1: int} Contenido y posición tras la `}`.
	 */
	private static function block( string $css, int $i ): array {
		$n     = strlen( $css );
		$start = $i;
		$depth = 1;
		while ( $i < $n ) {
			$c = $css[ $i ];
			if ( '"' === $c || "'" === $c ) {
				$i = self::string_end( $css, $i );
				continue;
			}
			if ( '/' === $c && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $css, '*/', $i + 2 );
				$i   = false === $end ? $n : $end + 2;
				continue;
			}
			if ( '{' === $c ) {
				++$depth;
			} elseif ( '}' === $c ) {
				--$depth;
				if ( 0 === $depth ) {
					return array( substr( $css, $start, $i - $start ), $i + 1 );
				}
			}
			++$i;
		}
		return array( substr( $css, $start ), $n );
	}

	/** Posición tras el cierre de la cadena que empieza en `$i`. */
	private static function string_end( string $css, int $i ): int {
		$q = $css[ $i ];
		$n = strlen( $css );
		for ( ++$i; $i < $n; ++$i ) {
			if ( '\\' === $css[ $i ] ) {
				++$i;
				continue;
			}
			if ( $q === $css[ $i ] ) {
				return $i + 1;
			}
		}
		return $n;
	}

	/** Selectores de una lista, separados por las comas de primer nivel. */
	private static function split( string $prelude ): array {
		$out = array();
		$i   = 0;
		$n   = strlen( $prelude );
		while ( $i <= $n ) {
			list( $part, $stop, $i ) = self::until( $prelude, $i, array( ',' ) );
			$out[]                   = $part;
			if ( '' === $stop ) {
				break;
			}
		}
		return $out;
	}
}
