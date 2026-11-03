<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Lectura de los ajustes heredados de un elemento (F8, §3.C.1).
 *
 * - Desenvuelve los valores v4 (`{"$$type":…,"value":…}`), así un interruptor atómico apagado deja de contar como
 *   usado (también en `Dropped::used()`).
 * - Da los valores por defecto que Elementor no guarda (los de `keys.php`).
 * - Resuelve los colores globales (`__globals__` → `var(--e-global-color-<id>)`) y los textos dinámicos
 *   (`__dynamic__`) **antes** de que `Native::element_settings()` limpie esas bolsas.
 * - Conoce los puntos de corte activos del sitio para las variantes responsivas y sus `@media`.
 */
final class Values {
	/** Sufijos responsivos de Elementor, del más ancho al más estrecho (sin el escritorio). */
	const DEVICES = array( 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' );

	/** Puntos de corte por omisión del núcleo: valor y dirección. */
	const BREAKPOINTS = array(
		'widescreen'   => array( 2400, 'min' ),
		'laptop'       => array( 1366, 'max' ),
		'tablet_extra' => array( 1200, 'max' ),
		'tablet'       => array( 1024, 'max' ),
		'mobile_extra' => array( 880, 'max' ),
		'mobile'       => array( 767, 'max' ),
	);

	/** Puntos de corte activos (pruebas). */
	private static $breakpoints = null;

	/** Desenvuelve un valor v4 (`{"$$type": …, "value": …}`), también anidado. */
	public static function unwrap( $value ) {
		$depth = 0;
		while ( is_array( $value ) && array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) && $depth++ < 8 ) {
			$value = $value['value'];
		}
		return $value;
	}

	/** ¿Tiene valor? Un interruptor encendido, un texto o un repetidor con filas; nunca vacío, «no» ni `false`. */
	public static function used( $value ): bool {
		$value = self::unwrap( $value );
		if ( is_array( $value ) ) {
			return (bool) $value;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value ) {
			return false;
		}
		$value = trim( (string) $value );
		return '' !== $value && 'no' !== $value && 'false' !== $value;
	}

	/** Valor guardado (desenvuelto) o el valor por defecto de la extensión. */
	public static function get( array $s, string $key, $default = null ) {
		return array_key_exists( $key, $s ) ? self::unwrap( $s[ $key ] ) : $default;
	}

	/** Tamaño de un deslizador guardado (`{size}`) o null. */
	public static function size_of( array $s, string $key, $default = null ) {
		$value = self::get( $s, $key, $default );
		if ( is_array( $value ) ) {
			$value = $value['size'] ?? null;
		}
		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Color de un control: el global (`__globals__[clave]` = `globals/colors?id=x` → `var(--e-global-color-x)`) manda
	 * sobre el valor guardado, como en el núcleo. Validado; null si no hay o no es un color.
	 */
	public static function color( array $s, string $key, $default = null ): ?string {
		$global = $s['__globals__'][ $key ] ?? null;
		if ( is_string( $global ) && preg_match( '#^globals/colors\?id=([a-zA-Z0-9_-]{1,40})$#', $global, $m ) ) {
			return 'var(--e-global-color-' . $m[1] . ')';
		}
		$value = self::get( $s, $key, $default );
		return null === $value || '' === $value ? null : Css_Value::color( $value );
	}

	/** Referencia global guardada para una clave (`globals/…`), o null. */
	public static function global_ref( array $s, string $key ): ?string {
		$global = $s['__globals__'][ $key ] ?? null;
		return is_string( $global ) && preg_match( '#^globals/(colors|typography)\?id=[a-zA-Z0-9_-]{1,40}$#', $global ) ? $global : null;
	}

	/** Etiqueta dinámica guardada para una clave (`[elementor-tag …]`), o null. */
	public static function dynamic_ref( array $s, string $key ): ?string {
		$tag = $s['__dynamic__'][ $key ] ?? null;
		return is_string( $tag ) && 0 === strpos( ltrim( $tag ), '[elementor-tag' ) ? $tag : null;
	}

	/**
	 * Texto por defecto de Element Pack en el idioma del sitio: con su dominio de textos cargado (Element Pack activo
	 * durante la conversión) se ve lo mismo que veía el visitante; si no, el original.
	 */
	public static function ep_text( string $text ): string {
		return function_exists( 'translate' ) ? (string) translate( $text, 'bdthemes-element-pack' ) : $text;
	}

	/* ---------------------------------------------------------------- Responsivo */

	/** Puntos de corte activos del sitio: `dispositivo => [valor, 'max'|'min']`, del más ancho al más estrecho. */
	public static function breakpoints(): array {
		if ( null !== self::$breakpoints ) {
			return self::$breakpoints;
		}
		$out = array();
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
			try {
				foreach ( (array) \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $name => $breakpoint ) {
					if ( isset( self::BREAKPOINTS[ $name ] ) && is_object( $breakpoint ) && method_exists( $breakpoint, 'get_value' ) ) {
						$out[ $name ] = array( (int) $breakpoint->get_value(), 'min' === ( method_exists( $breakpoint, 'get_direction' ) ? $breakpoint->get_direction() : 'max' ) ? 'min' : 'max' );
					}
				}
			} catch ( \Throwable $e ) {
				$out = array();
			}
		}
		if ( ! $out ) {
			$out = array( 'tablet' => self::BREAKPOINTS['tablet'], 'mobile' => self::BREAKPOINTS['mobile'] );
		}
		$sorted = array();
		foreach ( self::DEVICES as $device ) {
			if ( isset( $out[ $device ] ) ) {
				$sorted[ $device ] = $out[ $device ];
			}
		}
		return $sorted;
	}

	/** Fija los puntos de corte activos (pruebas); null vuelve a leerlos. */
	public static function set_breakpoints( ?array $breakpoints ): void {
		self::$breakpoints = $breakpoints;
	}

	/** Dispositivos activos (para `sticky_on` y `motion_fx_devices`), del más ancho al más estrecho, con el escritorio. */
	public static function devices(): array {
		$out = array();
		foreach ( self::breakpoints() as $device => $spec ) {
			if ( 'min' === $spec[1] ) {
				$out[] = $device;
			}
		}
		$out[] = 'desktop';
		foreach ( self::breakpoints() as $device => $spec ) {
			if ( 'max' === $spec[1] ) {
				$out[] = $device;
			}
		}
		return $out;
	}

	/** Ancho máximo que cubre cada dispositivo activo (el escritorio llega hasta el primer «min» o sin límite). */
	public static function device_max( string $device ): int {
		$bps = self::breakpoints();
		if ( 'desktop' === $device ) {
			foreach ( $bps as $spec ) {
				if ( 'min' === $spec[1] ) {
					return $spec[0] - 1;
				}
			}
			return PHP_INT_MAX;
		}
		if ( isset( $bps[ $device ] ) && 'min' === $bps[ $device ][1] ) {
			return PHP_INT_MAX;
		}
		return isset( $bps[ $device ] ) ? $bps[ $device ][0] : 0;
	}

	/** `@media` de un dispositivo activo (vacío para el escritorio). */
	public static function media( string $device ): string {
		$bps = self::breakpoints();
		if ( ! isset( $bps[ $device ] ) ) {
			return '';
		}
		return '@media (' . ( 'min' === $bps[ $device ][1] ? 'min' : 'max' ) . '-width:' . (int) $bps[ $device ][0] . 'px)';
	}

	/**
	 * Valores de un control responsivo guardados: `'' => escritorio`, y cada dispositivo activo con valor. Sin valor de
	 * escritorio se usa `$default`.
	 */
	public static function responsive( array $s, string $key, $default = null ): array {
		$out = array();
		$desk = self::get( $s, $key, $default );
		if ( null !== $desk && '' !== $desk ) {
			$out[''] = $desk;
		}
		foreach ( array_keys( self::breakpoints() ) as $device ) {
			if ( array_key_exists( $key . '_' . $device, $s ) ) {
				$value = self::unwrap( $s[ $key . '_' . $device ] );
				if ( null !== $value && '' !== $value && ( ! is_array( $value ) || '' !== (string) ( $value['size'] ?? 'x' ) ) ) {
					$out[ $device ] = $value;
				}
			}
		}
		return $out;
	}

	/** Clave responsiva de un dispositivo (`''` → la clave tal cual). */
	public static function device_key( string $key, string $device ): string {
		return '' === $device ? $key : $key . '_' . $device;
	}
}
