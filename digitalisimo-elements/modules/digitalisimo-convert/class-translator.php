<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Traduce los ajustes guardados de un widget de PRO a los de su widget propio con un mapa declarativo
 * (`maps/<widget de PRO>.php`). Mismas reglas que los traductores de DIGITALÍSIMO Migración, más los
 * colores y tipografías globales del kit (`__globals__`) y la cobertura obligatoria: **cada clave que el sitio guardó
 * se traduce, se conserva, se aproxima o se descarta con su motivo**; una clave que el mapa no declara se informa en
 * «Pendiente de revisar» (`pro.unmapped`) y no pasa al widget propio.
 *
 * Formato del mapa (todas las claves salvo `source` y `target` son opcionales):
 * - `source`, `target`: widget de PRO y widget propio.
 * - `status`: `ready` (validado: lo usan Migración y los kits) o `draft` (sólo las pruebas).
 * - `panel`: `hide` oculta del panel el widget de PRO cuando el mapa está `ready`.
 * - `defaults`: valores por defecto de los controles de PRO. Elementor no guarda lo que coincide con el default, así
 *   que se completan antes de traducir (en un control de valor múltiple, sólo las partes que faltan).
 * - `keep`: claves con el mismo nombre en el widget propio. `rename`: `de => a`. Las dos alcanzan las variantes por
 *   dispositivo (`_tablet`, `_mobile`…) y las de `__dynamic__` y `__globals__`.
 * - `groups`: prefijo de un control de grupo (tipografía, borde, sombra, fondo) `de => a`; con todas sus subclaves.
 * - `values`: `clave de destino => [antes => después]` o `callable( $valor, array $origen )`, también por dispositivo.
 * - `set`: valores fijos en el destino. `filter`: `callable( array $destino, array $origen, callable $informe ): array`,
 *   al final; `$informe( código, clave, motivo, sección )` deja un código en el informe (p. ej. `pro.dropped` en
 *   `dropped` para lo que sólo se descarta con el estado del sitio que convierte).
 * - `drop`: `clave => motivo`, pérdida declarada; se informa en «Descartado» si el sitio la cambió del default. Con el
 *   sufijo de un dispositivo (`align_tablet`), descarta sólo ese dispositivo aunque `keep` o `rename` conserven la clave.
 * - `approx`: `clave => motivo`, traducción aproximada (la hacen `rename`, `values` o `filter`); se informa en
 *   «Aproximado» si el sitio la cambió del default.
 * - `repeaters`: `clave => submapa` con `target`, `defaults`, `default_rows`, `keep`, `rename`, `values`, `drop` y
 *   `approx`; el `_id` de cada fila se conserva.
 * - `classes`: `.clase de PRO => .clase propia`, para el CSS personalizado del propio elemento.
 * - `img_classes`: `.clase propia => sufijo` de las clases que en PRO eran un fondo CSS y en el widget propio son (sufijo
 *   '') o contienen (p. ej. ` img`) un `<img>`: `background-size` y `background-position` del CSS personalizado pasan a
 *   `object-fit` y `object-position` del `<img>` (`background_to_object()`).
 *
 * Los ajustes comunes de Elementor y de DIGITALÍSIMO (pestaña Avanzado, visibilidad, movimiento, fijado, atributos,
 * condiciones, rasgos `digitalisimo_*`) pasan sin cambios.
 */
final class Translator {
	const DEVICES = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	/** Claves comunes a todo elemento: pasan tal cual. */
	const COMMON = array( 'custom_css', 'animation_duration', 'e_display_conditions', 'sticky', 'sticky_on', 'sticky_offset', 'sticky_effects_offset', 'sticky_anchor_link_offset', 'sticky_parent' );

	/** Prefijos de claves comunes. `_` cubre la pestaña Avanzado y el `_id` de las filas. */
	const COMMON_PREFIXES = array( '_', 'motion_fx_', 'hide_', 'digitalisimo_', 'sticky_' );

	/** Marca del widget de origen en los ajustes traducidos: un ajuste ya traducido no se traduce otra vez. */
	const MARKER = '_digitalisimo_pro';

	/**
	 * Traduce los ajustes de un widget con su mapa.
	 *
	 * @return array{0: array, 1: array<int, array{code:string, key:string, reason:string, section:string}>} Ajustes y
	 *         códigos del informe.
	 */
	public static function translate( array $settings, array $map ): array {
		if ( isset( $settings[ self::MARKER ] ) ) {
			return array( $settings, array() );
		}
		$codes = array();
		$out   = self::level( $settings, $map, $codes, '' );
		foreach ( (array) ( $map['repeaters'] ?? array() ) as $key => $spec ) {
			$rows = array_key_exists( $key, $settings ) ? $settings[ $key ] : ( $spec['default_rows'] ?? array() );
			$items = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( is_array( $row ) ) {
					$items[] = self::level( $row, (array) $spec, $codes, $key . '.' );
				}
			}
			$out[ (string) ( $spec['target'] ?? $key ) ] = $items;
		}
		if ( ! empty( $map['classes'] ) && isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) && '' !== $out['custom_css'] ) {
			$css = self::rewrite_classes( $out['custom_css'], (array) $map['classes'] );
			if ( $css !== $out['custom_css'] ) {
				$out['custom_css'] = $css;
				$codes[]           = self::code( 'pro.css', 'custom_css', 'Clases del widget de PRO en el CSS personalizado del elemento, pasadas a las del widget propio.', 'converted' );
			}
		}
		if ( ! empty( $map['img_classes'] ) && isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) && '' !== $out['custom_css'] ) {
			$lost = false;
			$css  = self::background_to_object( $out['custom_css'], (array) $map['img_classes'], $lost );
			if ( $css !== $out['custom_css'] ) {
				$out['custom_css'] = $css;
				$reason            = 'El fondo de la imagen de PRO (`background-size`, `background-position`) pasa al `<img>` del widget propio (`object-fit`, `object-position`).';
				$found             = false;
				foreach ( $codes as $i => $code ) {
					if ( 'pro.css' === $code['code'] && 'custom_css' === $code['key'] ) {
						$codes[ $i ]['reason'] .= ' ' . $reason;
						$found                  = true;
					}
				}
				if ( ! $found ) {
					$codes[] = self::code( 'pro.css', 'custom_css', $reason, 'converted' );
				}
			}
			if ( $lost ) {
				$codes[] = self::code( 'pro.approx', 'custom_css', 'Un tamaño de fondo con medidas (`background-size` con longitudes o porcentajes), `background-position-x`/`-y`, varios fondos o el atajo `background` no tienen equivalente en el `<img>` del widget propio: quedan sobre su envoltorio, sin efecto.', 'approx' );
			}
		}
		if ( isset( $map['filter'] ) && is_callable( $map['filter'] ) ) {
			$report = static function ( string $code, string $key, string $reason, string $section ) use ( &$codes ): void {
				$codes[] = self::code( $code, $key, $reason, $section );
			};
			$out = (array) call_user_func( $map['filter'], $out, $settings, $report );
		}
		$out[ self::MARKER ] = (string) ( $map['source'] ?? '' );
		return array( $out, $codes );
	}

	/**
	 * Un nivel (el widget o una fila de repetidor): defaults, claves declaradas, comunes y no declaradas.
	 *
	 * @param array  $codes  Recibe los códigos del informe.
	 * @param string $prefix Prefijo de las claves en los códigos (`repetidor.` en una fila).
	 */
	private static function level( array $settings, array $spec, array &$codes, string $prefix ): array {
		$defaults = (array) ( $spec['defaults'] ?? array() );
		$changed  = static function ( $key ) use ( $settings, $defaults ) {
			return array_key_exists( $key, $settings ) && ( ! array_key_exists( $key, $defaults ) || $settings[ $key ] !== $defaults[ $key ] );
		};
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			} elseif ( is_array( $value ) && is_array( $settings[ $key ] ) && $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				// Como Elementor en los controles de valor múltiple (URL, imagen, icono, medidas): lo que falta, del default.
				$settings[ $key ] = array_merge( $value, $settings[ $key ] );
			}
		}
		$rename = (array) ( $spec['rename'] ?? array() );
		foreach ( (array) ( $spec['keep'] ?? array() ) as $key ) {
			$rename[ $key ] = $key;
		}
		$groups  = (array) ( $spec['groups'] ?? array() );
		$drop    = (array) ( $spec['drop'] ?? array() );
		$approx  = (array) ( $spec['approx'] ?? array() );
		$nested  = array_keys( (array) ( $spec['repeaters'] ?? array() ) );
		$out     = array();
		$handled = array();
		foreach ( $rename as $from => $to ) {
			foreach ( self::DEVICES as $device ) {
				if ( '' !== $device && isset( $drop[ $from . $device ] ) ) {
					continue; // Descartada sólo en este dispositivo: se informa abajo.
				}
				if ( array_key_exists( $from . $device, $settings ) ) {
					$out[ $to . $device ]          = $settings[ $from . $device ];
					$handled[ $from . $device ] = true;
				}
			}
		}
		foreach ( $groups as $from => $to ) {
			foreach ( $settings as $key => $value ) {
				if ( 0 === strpos( (string) $key, $from . '_' ) ) {
					$out[ $to . substr( (string) $key, strlen( $from ) ) ] = $value;
					$handled[ $key ] = true;
				}
			}
		}
		foreach ( array( '__dynamic__', '__globals__' ) as $bag ) {
			if ( empty( $settings[ $bag ] ) || ! is_array( $settings[ $bag ] ) ) {
				continue;
			}
			$moved = array();
			foreach ( $settings[ $bag ] as $key => $value ) {
				// Una clave descartada sólo en su dispositivo (`drop` con sufijo) no sigue a la conservada.
				$only   = ! isset( $drop[ self::base( (string) $key ) ] ) && isset( $drop[ (string) $key ] ) && self::base( (string) $key ) !== (string) $key;
				$target = $only ? null : self::target_key( (string) $key, $rename, $groups );
				if ( $only ) {
					$codes[] = self::code( 'pro.dropped', $prefix . $key, (string) $drop[ (string) $key ], 'dropped' );
				} elseif ( null !== $target ) {
					$moved[ $target ] = $value;
				} elseif ( self::common( (string) $key ) ) {
					$moved[ $key ] = $value;
				} elseif ( isset( $drop[ self::base( (string) $key ) ] ) ) {
					$codes[] = self::code( 'pro.dropped', $prefix . self::base( (string) $key ), (string) $drop[ self::base( (string) $key ) ], 'dropped' );
				} else {
					$codes[] = self::code( 'pro.unmapped', $prefix . $key, 'Valor dinámico o global de una clave que el mapa no declara.', 'review' );
				}
			}
			if ( $moved ) {
				$out[ $bag ] = $moved;
			}
			$handled[ $bag ] = true;
		}
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( isset( $handled[ $key ] ) || in_array( $key, $nested, true ) || self::MARKER === $key ) {
				continue;
			}
			$base = self::base( $key );
			if ( isset( $drop[ $base ] ) || isset( $drop[ $key ] ) ) {
				if ( $changed( $key ) ) {
					$why     = isset( $drop[ $base ] ) ? $base : $key;
					$codes[] = self::code( 'pro.dropped', $prefix . $why, (string) $drop[ $why ], 'dropped' );
				}
				continue;
			}
			if ( self::common( $key ) ) {
				$out[ $key ] = $value;
				continue;
			}
			if ( isset( $approx[ $base ] ) ) {
				continue; // La traduce `filter` con el origen; su código sale abajo si cambió.
			}
			$codes[] = self::code( 'pro.unmapped', $prefix . $key, 'Clave que el mapa no declara: no pasa al widget propio.', 'review' );
		}
		foreach ( $approx as $key => $reason ) {
			foreach ( self::DEVICES as $device ) {
				if ( $changed( $key . $device ) ) {
					$codes[] = self::code( 'pro.approx', $prefix . $key, (string) $reason, 'approx' );
					break;
				}
			}
		}
		foreach ( (array) ( $spec['values'] ?? array() ) as $key => $values ) {
			foreach ( self::DEVICES as $device ) {
				if ( ! array_key_exists( $key . $device, $out ) ) {
					continue;
				}
				if ( is_callable( $values ) ) {
					$out[ $key . $device ] = call_user_func( $values, $out[ $key . $device ], $settings );
				} elseif ( is_array( $values ) && is_scalar( $out[ $key . $device ] ) && array_key_exists( (string) $out[ $key . $device ], $values ) ) {
					$out[ $key . $device ] = $values[ (string) $out[ $key . $device ] ];
				}
			}
		}
		foreach ( (array) ( $spec['set'] ?? array() ) as $key => $value ) {
			$out[ $key ] = $value;
		}
		return $out;
	}

	/** Clave de destino de una clave de origen (con su dispositivo) por `rename`/`keep` o `groups`, o null. */
	private static function target_key( string $key, array $rename, array $groups ): ?string {
		foreach ( self::DEVICES as $device ) {
			if ( '' !== $device && substr( $key, -strlen( $device ) ) !== $device ) {
				continue;
			}
			$base = '' === $device ? $key : substr( $key, 0, -strlen( $device ) );
			if ( isset( $rename[ $base ] ) ) {
				return $rename[ $base ] . $device;
			}
		}
		foreach ( $groups as $from => $to ) {
			if ( 0 === strpos( $key, $from . '_' ) ) {
				return $to . substr( $key, strlen( $from ) );
			}
		}
		return null;
	}

	/** La clave sin el sufijo de dispositivo. */
	public static function base( string $key ): string {
		foreach ( self::DEVICES as $device ) {
			if ( '' !== $device && strlen( $key ) > strlen( $device ) && substr( $key, -strlen( $device ) ) === $device ) {
				return substr( $key, 0, -strlen( $device ) );
			}
		}
		return $key;
	}

	/** ¿Es una clave común de todo elemento? */
	public static function common( string $key ): bool {
		if ( in_array( $key, self::COMMON, true ) ) {
			return true;
		}
		foreach ( self::COMMON_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clases del widget de PRO en un CSS, por las del widget propio: sólo nombres de clase completos (no `-a` dentro
	 * de `.x-ab`), de los más largos a los más cortos.
	 */
	public static function rewrite_classes( string $css, array $classes ): string {
		uksort(
			$classes,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		foreach ( $classes as $from => $to ) {
			$css = (string) preg_replace( '/' . preg_quote( (string) $from, '/' ) . '(?![\w-])/', (string) $to, $css );
		}
		return $css;
	}

	/**
	 * Reglas del CSS (ya con las clases propias) cuyo selector termina en una clase que en PRO era un fondo CSS y en el
	 * widget propio es (o contiene) un `<img>`: tras cada una se añade la misma regla sobre el `<img>` (`$images`: clase =>
	 * sufijo del selector, '' si la clase está en el propio `<img>`) con `background-size` → `object-fit` (`cover`,
	 * `contain`; `auto` → `none`, como «Ajuste de la imagen») y `background-position` → `object-position` (misma
	 * sintaxis). La regla original queda intacta (en el envoltorio no tiene efecto). Lo que no tiene equivalente
	 * (medidas en el tamaño, `background-position-x`/`-y`, varios fondos, el atajo `background`, que en PRO devolvía el
	 * tamaño y la posición a sus valores iniciales) se marca en `$lost` y no se traduce. Los comentarios no pasan a la
	 * regla nueva.
	 *
	 * @param bool $lost Recibe `true` si alguna declaración no tiene equivalente.
	 */
	public static function background_to_object( string $css, array $images, bool &$lost = false ): string {
		$classes = array();
		foreach ( $images as $class => $suffix ) {
			if ( preg_match( '/^\.[A-Za-z_][\w-]*$/', (string) $class ) ) {
				$classes[ (string) $class ] = (string) $suffix;
			}
		}
		if ( ! $classes || false === stripos( $css, 'background' ) ) {
			return $css;
		}
		return (string) preg_replace_callback(
			'/([^{};]*)\{([^{}]*)\}/',
			static function ( array $m ) use ( $classes, &$lost ): string {
				$rule = $m[0];
				if ( false === stripos( $m[2], 'background' ) || '' === trim( $m[1] ) || '@' === ltrim( $m[1] )[0] ) {
					return $rule;
				}
				$targets = array();
				foreach ( self::split_selectors( $m[1] ) as $selector ) {
					// Sin comentarios: los completos y el final de uno abierto antes de la regla (`/* a{b} */ selector …`).
					$selector = trim( (string) preg_replace( array( '#/\*.*?\*/#s', '#^.*\*/#s' ), '', $selector ) );
					// El último compuesto (tras el último combinador): con la clase entera y sin pseudoelemento.
					$last = (string) preg_replace( '/^.*[\s>+~]/s', '', $selector );
					if ( false !== strpos( $last, '::' ) || preg_match( '/:(?:before|after)\b/i', $last ) ) {
						continue;
					}
					foreach ( $classes as $class => $suffix ) {
						if ( preg_match( '/' . preg_quote( $class, '/' ) . '(?![\w-])/', $last ) ) {
							$targets[] = $selector . $suffix;
							break;
						}
					}
				}
				if ( ! $targets ) {
					return $rule;
				}
				$decls = array();
				foreach ( explode( ';', (string) preg_replace( '#/\*.*?\*/#s', '', $m[2] ) ) as $declaration ) {
					if ( preg_match( '/^\s*background\s*:/i', $declaration ) ) {
						$lost = true; // El atajo: en PRO también fijaba el tamaño y la posición (a sus valores iniciales si no los nombra).
						continue;
					}
					if ( ! preg_match( '/^\s*(background-(?:size|position(?:-x|-y)?))\s*:\s*(.+?)\s*$/is', $declaration, $d ) ) {
						continue;
					}
					$important = (bool) preg_match( '/\s*!\s*important$/i', $d[2] );
					$value     = trim( (string) preg_replace( '/\s*!\s*important$/i', '', $d[2] ) );
					$name      = strtolower( $d[1] );
					if ( false !== strpos( $value, ',' ) || 'background-size' !== $name && 'background-position' !== $name ) {
						$lost = true;
						continue;
					}
					if ( 'background-size' === $name ) {
						$fit = array( 'cover' => 'cover', 'contain' => 'contain', 'auto' => 'none', 'auto auto' => 'none' );
						$key = strtolower( (string) preg_replace( '/\s+/', ' ', $value ) );
						if ( ! isset( $fit[ $key ] ) ) {
							$lost = true;
							continue;
						}
						$decls[] = 'object-fit:' . $fit[ $key ] . ( $important ? '!important' : '' );
					} else {
						$decls[] = 'object-position:' . $value . ( $important ? '!important' : '' );
					}
				}
				return $decls ? $rule . "\n" . implode( ',', $targets ) . '{' . implode( ';', $decls ) . '}' : $rule;
			},
			$css
		);
	}

	/** Selectores de una lista separada por comas (sin partir dentro de paréntesis ni corchetes). */
	private static function split_selectors( string $list ): array {
		$out   = array();
		$depth = 0;
		$cur   = '';
		$len   = strlen( $list );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $list[ $i ];
			if ( '(' === $ch || '[' === $ch ) {
				$depth++;
			} elseif ( ( ')' === $ch || ']' === $ch ) && $depth > 0 ) {
				$depth--;
			} elseif ( ',' === $ch && 0 === $depth ) {
				$out[] = $cur;
				$cur   = '';
				continue;
			}
			$cur .= $ch;
		}
		$out[] = $cur;
		return $out;
	}

	private static function code( string $code, string $key, string $reason, string $section ): array {
		return array( 'code' => $code, 'key' => $key, 'reason' => $reason, 'section' => $section );
	}
}
