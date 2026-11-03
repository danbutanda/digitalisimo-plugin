<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Traduce los ajustes guardados de un widget de PRO a los de su widget propio con un mapa declarativo
 * (`maps/<widget de PRO>.php`). Mismas reglas que el traductor de Element Pack de DIGITALÍSIMO Migración, más los
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
 * - `set`: valores fijos en el destino. `filter`: `callable( array $destino, array $origen ): array`, al final.
 * - `drop`: `clave => motivo`, pérdida declarada; se informa en «Descartado» si el sitio la cambió del default.
 * - `approx`: `clave => motivo`, traducción aproximada (la hacen `rename`, `values` o `filter`); se informa en
 *   «Aproximado» si el sitio la cambió del default.
 * - `repeaters`: `clave => submapa` con `target`, `defaults`, `default_rows`, `keep`, `rename`, `values`, `drop` y
 *   `approx`; el `_id` de cada fila se conserva.
 * - `classes`: `.clase de PRO => .clase propia`, para el CSS personalizado del propio elemento.
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
		if ( isset( $map['filter'] ) && is_callable( $map['filter'] ) ) {
			$out = (array) call_user_func( $map['filter'], $out, $settings );
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
				$target = self::target_key( (string) $key, $rename, $groups );
				if ( null !== $target ) {
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
			if ( isset( $drop[ $base ] ) ) {
				if ( $changed( $key ) ) {
					$codes[] = self::code( 'pro.dropped', $prefix . $base, (string) $drop[ $base ], 'dropped' );
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

	private static function code( string $code, string $key, string $reason, string $section ): array {
		return array( 'code' => $code, 'key' => $key, 'reason' => $reason, 'section' => $section );
	}
}
