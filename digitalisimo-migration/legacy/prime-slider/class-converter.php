<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

use Digitalisimo\Elements\Legacy\Context;
use Digitalisimo\Elements\Legacy\Native;
use Digitalisimo\Elements\Legacy\Report;

defined( 'ABSPATH' ) || exit;

foreach ( array( 'maps', 'defaults', 'sanitizer', 'demo-media', 'builder', 'static' ) as $digitalisimo_ps_part ) {
	require_once __DIR__ . '/class-' . $digitalisimo_ps_part . '.php';
}
unset( $digitalisimo_ps_part );

/**
 * Único punto de entrada de la conversión de Prime Slider (contrato C15, §3.E.0): `Native` lo llama con cada widget
 * `prime-slider-*` y el filtro de marcadores de K, a través de `Native`, también.
 *
 * Tubería: mapa (`Maps`) → valores por defecto (`Defaults::fill()`) → limpieza del proveedor (`Sanitizer::clean()`)
 * → decisiones que no dependen de las imágenes (`Static_Families::plan()`) → imágenes de demostración
 * (`Demo_Media::resolve()`) → destino de la familia (`Static_Families::build()` en las de E; `Dynamic::build()` en
 * E, F, G y L, del frente F) → claves comunes (lista blanca de `Builder::common()`) y extensiones de Element Pack del
 * widget (por `Native::element()` sobre el principal) → informe.
 *
 * Devuelve el elemento convertido, un fragmento (`elType` = `__fragment`: hermanos antes y después del principal, que
 * conserva el ID del original), un marcador de K (descartes declarados, `source: migration`) o null (sin destino
 * ahora: el widget se queda como está y Prime Slider no se retira, V.10.6). En el editor (`Context::mode()` =
 * `editor`) la conversión es pura y devuelve sólo el principal: los hermanos se pierden (límite declarado) y los
 * descartes se quedan en su marcador.
 */
final class Converter {
	/** Familias del frente F (contrato C15: `Dynamic::FAMILIES`). */
	const DYNAMIC_FAMILIES = array( 'E', 'F', 'G', 'L' );

	const PATTERN = '/^prime-slider-[a-z0-9-]+$/';
	const MARKER  = '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve';
	const DYNAMIC = '\\Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Dynamic';

	/** Claves de las extensiones de Element Pack que convierte `Native` (como en cualquier elemento). */
	const LEGACY_PREFIXES = array( 'ep_', 'element_pack_', 'bdt_' );

	/** Extensiones de Prime Slider: descarte informado (§3.E.0.6). Sólo se leen dentro de widgets de Prime Slider. */
	const EXTENSIONS = array(
		'reveal_effects_enable' => 'ps.dropped.reveal',
		'animation_status'      => 'ps.dropped.advanced_animation',
		'ps_widget_abg'         => 'ps.dropped.adaptive_bg',
		'kenburns_animation'    => 'ps.dropped.kenburns',
	);

	/** @var array<int, array{0:string, 1:array}> Códigos de la última conversión (también en modo puro). */
	public static $last = array();

	/**
	 * Convierte un widget de Prime Slider.
	 *
	 * @param array  $element Widget guardado.
	 * @param int    $post_id Documento (0 si no se sabe).
	 * @param string $parent  `elType` del padre (`container`, `column`, `section` o '').
	 * @return array|null Elemento, fragmento, marcador o null.
	 */
	public static function element( array $element, int $post_id, string $parent ): ?array {
		self::$last = array();
		$type       = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' !== ( $element['elType'] ?? '' ) || 1 !== preg_match( self::PATTERN, $type ) ) {
			return null;
		}
		$codes  = array();
		$map    = Maps::get( $type );
		$editor = class_exists( Context::class, false ) && 'editor' === Context::mode();
		if ( ! $map ) {
			$codes[] = array( 'ps.blocked.no_target', array() );
			self::report( $post_id, $type, $codes );
			return null;
		}
		if ( 'DESCARTAR' === $map['decision'] ) {
			$codes[] = array( (string) ( $map['drop_code'] ?? 'ps.dropped.widget' ), array() );
			self::report( $post_id, $type, $codes );
			return $editor || ! class_exists( self::MARKER ) ? null : call_user_func( array( self::MARKER, 'marker' ), $element, 'unknown', 'migration' );
		}
		$original = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		Builder::seed( (string) ( $element['id'] ?? $type ) );

		$filled = Defaults::fill( $type, $original );
		if ( 'static' === Defaults::$last ) {
			$codes[] = array( 'ps.defaults.static', Defaults::$literal ? array( 'note' => __( 'Textos por defecto sin traducción instalada: quedan en inglés.', 'digitalisimo-migration' ) ) : array() );
		}
		$filled = Sanitizer::clean( $type, $filled );
		if ( Sanitizer::$removed ) {
			$codes[] = array( 'ps.vendor_removed', array( 'count' => Sanitizer::$removed ) );
		}

		$filled = self::drop_unused( $map, $filled );

		if ( in_array( $map['family'], self::DYNAMIC_FAMILIES, true ) ) {
			$resolved = Demo_Media::resolve( $type, $filled );
			$built    = self::dynamic( $type, $resolved, $element, $post_id );
		} else {
			// Las decisiones de C y H, antes de resolver las imágenes (C1-29).
			$plan     = Static_Families::plan( $type, $map, $filled );
			$resolved = Demo_Media::resolve( $type, $filled );
			$built    = Static_Families::build( $type, $map, $resolved, $element, $post_id, $parent, $plan );
			$codes    = array_merge( $codes, Static_Families::$codes );
		}
		if ( Demo_Media::$copied ) {
			$codes[] = array( 'ps.demo.copied', array( 'count' => Demo_Media::$copied ) );
		}
		if ( Demo_Media::$skipped ) {
			$codes[] = array( 'ps.demo.skipped', array( 'count' => Demo_Media::$skipped ) );
		}
		if ( null === $built ) {
			$codes[] = array( 'ps.blocked.no_target', array( 'target' => (string) $map['target'] ) );
			self::report( $post_id, $type, $codes );
			return null;
		}
		if ( self::is_marker( $built ) ) {
			self::report( $post_id, $type, $codes );
			return $editor ? null : $built;
		}

		// El principal: el que conserva el ID del original (o el primero que no sea un hermano).
		$parts = '__fragment' === ( $built['elType'] ?? '' ) ? array_values( (array) ( $built['elements'] ?? array() ) ) : array( $built );
		$index = 0;
		foreach ( $parts as $i => $part ) {
			if ( is_array( $part ) && (string) ( $part['id'] ?? '' ) === (string) ( $element['id'] ?? "\0" ) ) {
				$index = $i;
				break;
			}
		}
		$main = self::finish_main( (array) $parts[ $index ], $original, $element, $post_id, $parent, $codes );
		foreach ( $parts as $i => $part ) {
			if ( $i !== $index && is_array( $part ) ) {
				$parts[ $i ]['settings'] = (array) ( $part['settings'] ?? array() ) + self::visibility( $original );
			}
		}
		$parts[ $index ] = $main;

		// Extensiones de Prime Slider sin equivalente (Ken Burns sobrevive sólo en el respaldo `slides`).
		foreach ( self::EXTENSIONS as $key => $code ) {
			if ( 'yes' === ( $filled[ $key ] ?? '' ) && ! ( 'kenburns_animation' === $key && Builder::FALLBACK === ( $main['widgetType'] ?? '' ) ) ) {
				$codes[] = array( $code, array() );
			}
		}
		$lost = self::lost_styles( $type, $original );
		if ( $lost ) {
			$codes[] = array( 'ps.loss.styles', array( 'count' => $lost ) );
		}
		$target  = (string) ( $main['widgetType'] ?? ( $main['elType'] ?? '' ) );
		$codes[] = array( 'ADAPTAR' === $map['decision'] ? 'ps.converted' : 'ps.approx', array( 'target' => $target ) );
		self::report( $post_id, $type, $codes );

		if ( $editor || 1 === count( $parts ) ) {
			return $main;
		}
		return array( 'elType' => '__fragment', 'elements' => $parts );
	}

	/**
	 * Quita lo que el destino no usa (`drop` del mapa y `drop_fields` de sus filas) antes de resolver las imágenes: así
	 * nunca se copia a Medios una imagen de demostración que no se va a ver (la cara trasera de `cards-stack`…).
	 */
	private static function drop_unused( array $map, array $s ): array {
		foreach ( (array) ( $map['drop'] ?? array() ) as $key ) {
			unset( $s[ $key ] );
		}
		$rows = (string) ( $map['rows'] ?? '' );
		if ( '' !== $rows && ! empty( $map['drop_fields'] ) && is_array( $s[ $rows ] ?? null ) ) {
			foreach ( $s[ $rows ] as $index => $row ) {
				if ( is_array( $row ) ) {
					foreach ( (array) $map['drop_fields'] as $field ) {
						unset( $s[ $rows ][ $index ][ $field ] );
					}
				}
			}
		}
		return $s;
	}

	/** Claves comunes, CSS del original y extensiones de Element Pack del widget sobre el elemento principal. */
	private static function finish_main( array $main, array $original, array $element, int $post_id, string $parent, array &$codes ): array {
		$settings = (array) ( $main['settings'] ?? array() );
		foreach ( Builder::common( $original, true ) as $key => $value ) {
			if ( 'custom_css' === $key ) {
				$own = trim( (string) $value );
				if ( '' !== $own ) {
					$settings['custom_css'] = trim( ( isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] . "\n" : '' ) . $own );
					if ( 1 === preg_match( '/\bbdt-|prime-slider/i', $own ) ) {
						$codes[] = array( 'ps.review.custom_css', array() );
					}
				}
				continue;
			}
			if ( '__globals__' === $key || '__dynamic__' === $key ) {
				$settings[ $key ] = (array) ( $settings[ $key ] ?? array() ) + (array) $value;
				continue;
			}
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}
		// Extensiones de Element Pack aplicadas al widget de Prime Slider: las convierte Native como en cualquier elemento.
		$legacy = array();
		foreach ( $original as $key => $value ) {
			$key = (string) $key;
			if ( self::legacy_key( $key ) && ! array_key_exists( $key, $settings ) ) {
				$legacy[ $key ] = $value;
			}
		}
		$main['settings'] = $settings + $legacy;
		if ( $legacy && class_exists( Native::class, false ) && method_exists( Native::class, 'element' ) ) {
			try {
				$converted = Native::element( $main, $post_id, $parent );
				if ( is_array( $converted[0] ?? null ) ) {
					$main = $converted[0];
				}
			} catch ( \Throwable $e ) {
				$codes[] = array( 'ps.review.extensions', array() );
			}
		}
		return $main;
	}

	/** ¿Clave de una extensión de Element Pack (con prefijo, o sin él si `Effects\Dispatcher` la reconoce)? */
	private static function legacy_key( string $key ): bool {
		foreach ( self::LEGACY_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}
		$dispatcher = '\\Digitalisimo\\Elements\\Legacy\\Effects\\Dispatcher';
		return class_exists( $dispatcher ) && method_exists( $dispatcher, 'is_key' ) && ! Builder::is_common( $key ) && $dispatcher::is_key( $key );
	}

	/** Visibilidad responsiva del original, para los hermanos (sin márgenes ni posición: no se duplican). */
	private static function visibility( array $original ): array {
		return array_intersect_key( Builder::common( $original, false ), array_flip( array( 'hide_desktop', 'hide_tablet', 'hide_mobile' ) ) );
	}

	/** Ajustes de estilo guardados que no tienen equivalente (`ps.loss.styles`). */
	private static function lost_styles( string $type, array $original ): int {
		$styles = Defaults::style_keys( $type );
		if ( ! $styles ) {
			return 0;
		}
		$count = 0;
		foreach ( array_keys( $original ) as $key ) {
			$key = (string) $key;
			if ( isset( Static_Families::$consumed[ $key ] ) || Builder::is_common( $key ) ) {
				continue;
			}
			foreach ( $styles as $style ) {
				if ( $key === $style || 0 === strpos( $key, $style . '_' ) ) {
					++$count;
					break;
				}
			}
		}
		return $count;
	}

	/** Familias de F (C15): `Dynamic::build()`; sin la clase, sin destino. */
	private static function dynamic( string $type, array $settings, array $element, int $post_id ): ?array {
		$class = self::DYNAMIC;
		if ( ! class_exists( $class ) && is_file( __DIR__ . '/class-dynamic.php' ) ) {
			require_once __DIR__ . '/class-dynamic.php';
		}
		if ( ! class_exists( $class ) || ! method_exists( $class, 'build' ) ) {
			return null;
		}
		$built = $class::build( $type, $settings, $element, $post_id );
		return is_array( $built ) ? $built : null;
	}

	/** Pasa los códigos al informe (nunca en modo puro: el informe ya los descarta en el editor). */
	private static function report( int $post_id, string $type, array $codes ): void {
		self::$last = $codes;
		if ( ( class_exists( Context::class, false ) && Context::pure() ) || ! class_exists( Report::class, false ) ) {
			return;
		}
		foreach ( $codes as $code ) {
			Report::add( $post_id, (string) $code[0], array( 'widget' => $type ) + (array) $code[1] );
		}
	}

	/**
	 * Análisis previo (sin escribir nada): códigos que dejaría la conversión de cada widget de Prime Slider del árbol,
	 * con su recuento.
	 *
	 * @param array $element Elemento (o lista de elementos).
	 * @return array<string, int>
	 */
	public static function describe( array $element ): array {
		$out = array();
		if ( ! isset( $element['elType'] ) ) {
			foreach ( $element as $child ) {
				if ( is_array( $child ) ) {
					foreach ( self::describe( $child ) as $code => $count ) {
						$out[ $code ] = ( $out[ $code ] ?? 0 ) + $count;
					}
				}
			}
			return $out;
		}
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' === $element['elType'] && 1 === preg_match( self::PATTERN, $type ) ) {
			$map  = Maps::get( $type );
			$code = 'ps.blocked.no_target';
			if ( $map && 'DESCARTAR' === $map['decision'] ) {
				$code = (string) ( $map['drop_code'] ?? 'ps.dropped.widget' );
			} elseif ( $map && in_array( $map['family'], self::DYNAMIC_FAMILIES, true ) ) {
				$code = 'ADAPTAR' === $map['decision'] ? 'ps.converted' : 'ps.approx';
			} elseif ( $map ) {
				$primary  = Builder::NESTED === $map['target'] ? Builder::usable( Builder::NESTED ) : ( 'container' === $map['target'] || Builder::usable( (string) $map['target'] ) );
				$fallback = '' !== $map['fallback'] && Builder::usable( (string) $map['fallback'] );
				if ( $primary || $fallback ) {
					$code = 'ADAPTAR' === $map['decision'] ? 'ps.converted' : 'ps.approx';
				}
				if ( ! $primary && $fallback ) {
					$out['ps.fallback.slides'] = ( $out['ps.fallback.slides'] ?? 0 ) + 1;
				}
			}
			$out[ $code ] = ( $out[ $code ] ?? 0 ) + 1;
			$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			foreach ( self::EXTENSIONS as $key => $drop ) {
				if ( 'yes' === ( $settings[ $key ] ?? '' ) ) {
					$out[ $drop ] = ( $out[ $drop ] ?? 0 ) + 1;
				}
			}
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) ) {
				foreach ( self::describe( $child ) as $code => $count ) {
					$out[ $code ] = ( $out[ $code ] ?? 0 ) + $count;
				}
			}
		}
		return $out;
	}

	/** ¿Es un marcador de K (`digitalisimo-unconverted`)? Sin K cargado, por su forma. */
	private static function is_marker( array $element ): bool {
		if ( 'digitalisimo-unconverted' !== ( $element['widgetType'] ?? '' ) ) {
			return false;
		}
		$class = self::MARKER;
		return ! class_exists( $class ) || null !== $class::info( $element );
	}
}
