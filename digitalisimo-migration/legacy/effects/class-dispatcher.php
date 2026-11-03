<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

// Sólo fuera de WordPress (pruebas sin dobles de traducción), como en Legacy\Report.
if ( ! function_exists( '__' ) && ! function_exists( __NAMESPACE__ . '\\__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

require_once __DIR__ . '/class-css-value.php';
require_once __DIR__ . '/class-values.php';
require_once __DIR__ . '/class-activation.php';
require_once __DIR__ . '/class-out.php';
require_once __DIR__ . '/class-transform.php';
require_once __DIR__ . '/class-motion.php';
require_once __DIR__ . '/class-sticky.php';
require_once __DIR__ . '/class-css.php';
require_once __DIR__ . '/class-expand.php';
require_once __DIR__ . '/class-traits.php';
require_once __DIR__ . '/class-page.php';

/**
 * Extensiones de Element Pack en los elementos (F8, §3.C y V.9): detección, conversión y limpieza.
 *
 * Contrato con el frente A (V.14 C3): `Native::element()` llama a `element()` por cada elemento en el que `detect()`
 * da verdadero, **antes** de traducir el widget y de que `element_settings()` limpie las claves con prefijo y las
 * bolsas `__globals__`/`__dynamic__`. `element()` no recorre los hijos (lo hace `Native`) y no tiene efectos fuera
 * del elemento: sólo anota en `Report` (que en el editor lo descarta). `is_key()` dice qué claves son de una
 * extensión (para la bolsa `ELEMENT_KEYS` de los widgets con mapa) y `describe()` cuenta, sin escribir nada, lo que
 * la conversión anotaría (análisis previo).
 *
 * Cada extensión se trata como dice `keys.php`: convertida (`ext.converted.*`, con sus pérdidas `ext.loss.*`),
 * apagada en Element Pack (`ext.disabled`), sin efecto donde estaba (`ext.inert`), descartada (sus claves se
 * retiran; `Dropped` la informa con `ext.dropped.*` leyendo la copia) o ambigua (claves sin prefijo sin ninguna señal
 * de Element Pack: `ext.ambiguous`, no se toca). Un elemento atómico (v4) con claves de Element Pack se informa con
 * `ext.v4` y no se toca.
 */
final class Dispatcher {
	/** Prefijos de las claves heredadas (los de `Native::ELEMENT_PREFIXES`). */
	const PREFIXES = array( 'ep_', 'element_pack_', 'bdt_' );

	/** @var array|null keys.php */
	private static $keys = null;
	/** @var array<int,bool> señal de Element Pack por documento. */
	private static $signals = array();
	/** @var bool|null Element Pack instalado en este sitio (por petición). */
	private static $installed = null;
	/** @var bool|null señal fijada (pruebas o quien ya la conoce). */
	private static $forced = null;

	/* ---------------------------------------------------------------- Tabla */

	/** Las 28 extensiones (`keys.php`). */
	public static function keys(): array {
		if ( null === self::$keys ) {
			$keys       = include __DIR__ . '/keys.php';
			self::$keys = is_array( $keys ) ? $keys : array();
		}
		return self::$keys;
	}

	/** Nombre visible de una extensión. */
	public static function label( string $slug ): string {
		return (string) ( self::keys()[ $slug ]['label'] ?? $slug );
	}

	/** Parte de código del informe de una extensión (`transform-effects` → `transform_effects`). */
	public static function code( string $slug ): string {
		return str_replace( '-', '_', $slug );
	}

	/** Valor por defecto de una clave de la extensión (Elementor no lo guarda). */
	public static function default_of( string $slug, string $key ) {
		return self::keys()[ $slug ]['defaults'][ $key ] ?? null;
	}

	/* ---------------------------------------------------------------- Detección */

	/** ¿La clave es de alguna de las 28 extensiones? Sin las genéricas de image-hover (sólo cuentan en `image`). */
	public static function is_key( string $key ): bool {
		return null !== self::extension_of( $key );
	}

	/** Extensión a la que pertenece una clave, o null. */
	public static function extension_of( string $key, bool $generic = false ): ?string {
		foreach ( self::keys() as $slug => $spec ) {
			if ( self::matches( $key, (array) ( $spec['prefixes'] ?? array() ), false ) ) {
				return $slug;
			}
			if ( ( $generic || empty( $spec['generic'] ) ) && self::matches( $key, (array) ( $spec['bare'] ?? array() ), true ) ) {
				return $slug;
			}
		}
		return null;
	}

	/** ¿La clave casa con la lista? Prefijos de nombre; en `bare`, nombre exacto (o con sufijo responsivo) o prefijo con `_` final. */
	private static function matches( string $key, array $list, bool $bare ): bool {
		foreach ( $list as $item ) {
			$item = (string) $item;
			if ( ! $bare ) {
				if ( 0 === strpos( $key, $item ) ) {
					return true;
				}
				continue;
			}
			if ( '_' === substr( $item, -1 ) ? 0 === strpos( $key, $item ) : ( $key === $item || 1 === preg_match( '/^' . preg_quote( $item, '/' ) . '_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', $key ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ¿Hay algo que convertir en estos ajustes? Una clave con prefijo de alguna extensión, o el interruptor sin prefijo
	 * de una (`section_sticky_on`…) con una señal de Element Pack (V.9).
	 */
	public static function detect( array $settings ): bool {
		foreach ( array_keys( $settings ) as $key ) {
			$key = (string) $key;
			foreach ( self::keys() as $spec ) {
				if ( 'page' !== ( $spec['scope'][0] ?? '' ) && self::matches( $key, (array) ( $spec['prefixes'] ?? array() ), false ) ) {
					return true;
				}
			}
		}
		$signal = null;
		foreach ( self::keys() as $spec ) {
			if ( 'page' === ( $spec['scope'][0] ?? '' ) || empty( $spec['bare'] ) || ! empty( $spec['generic'] ) ) {
				continue;
			}
			foreach ( (array) $spec['switch'] as $switch ) {
				if ( array_key_exists( $switch, $settings ) && Values::used( $settings[ $switch ] ) ) {
					$signal = null === $signal ? self::signal( self::context_post() ) : $signal;
					if ( $signal ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * ¿Cuentan las claves sin prefijo en este documento? Con Element Pack (Pro o Lite) instalado en el sitio
	 * (`Retire::installed()`), o con otra señal suya en el documento: una clave con prefijo, un widget `bdt-` o una copia
	 * de una conversión anterior (C2-18).
	 */
	public static function signal( int $post_id ): bool {
		if ( null !== self::$forced ) {
			return self::$forced;
		}
		if ( self::ep_installed() ) {
			return true;
		}
		if ( $post_id <= 0 || ! function_exists( 'get_metadata' ) ) {
			return false;
		}
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		$key  = $blog . ':' . $post_id;
		if ( ! isset( self::$signals[ $key ] ) ) {
			$raw    = get_metadata( 'post', $post_id, '_elementor_data', true );
			$raw    = is_string( $raw ) ? $raw : ( is_array( $raw ) ? (string) json_encode( $raw ) : '' );
			$signal = (bool) preg_match( '/"(ep_|element_pack_|bdt_)|"widgetType":"bdt-/', $raw );
			foreach ( array( '_digitalisimo_ep_backup', '_digitalisimo_ep_backup_page' ) as $meta ) {
				$signal = $signal || '' !== (string) get_metadata( 'post', $post_id, $meta, true );
			}
			if ( count( self::$signals ) > 500 ) {
				self::$signals = array();
			}
			self::$signals[ $key ] = $signal;
		}
		return self::$signals[ $key ];
	}

	/** Fija la señal (true/false) o vuelve a calcularla (null). Pruebas, o la etapa que ya la conoce. */
	public static function force_signal( ?bool $signal ): void {
		self::$forced  = $signal;
		self::$signals = array();
		self::$installed = null;
	}

	/** ¿Hay Element Pack (Pro o Lite) instalado en el sitio? */
	private static function ep_installed(): bool {
		if ( null === self::$installed ) {
			self::$installed = false;
			$retire          = 'Digitalisimo\\Elements\\Legacy\\Retire';
			if ( class_exists( $retire ) && method_exists( $retire, 'installed' ) ) {
				foreach ( (array) $retire::installed() as $info ) {
					if ( ! empty( $info['element_pack'] ) ) {
						self::$installed = true;
						break;
					}
				}
			}
		}
		return self::$installed;
	}

	/** Documento de la conversión en curso (`Context`, del frente A). */
	private static function context_post(): int {
		$context = 'Digitalisimo\\Elements\\Legacy\\Context';
		return class_exists( $context, false ) ? (int) $context::post_id() : 0;
	}

	/* ---------------------------------------------------------------- Widgets */

	/** Tipo de widget efectivo: el destino de su mapa si es un widget heredado con mapa. */
	public static function effective_widget( string $type ): string {
		$translator = 'Digitalisimo\\Elements\\Legacy\\Translator';
		if ( '' !== $type && class_exists( $translator, false ) && $translator::is_legacy_id( $type ) ) {
			$target = (string) $translator::target( $type );
			return '' !== $target ? $target : $type;
		}
		return $type;
	}

	/**
	 * ¿El widget de destino pinta `.elementor-widget-container`? `Native::inner_wrapper()` (A) si existe; si no, el
	 * widget registrado (`has_widget_inner_wrapper()`); null si no se sabe.
	 */
	public static function inner_wrapper( string $widget ): ?bool {
		$native = 'Digitalisimo\\Elements\\Legacy\\Native';
		if ( class_exists( $native, false ) && method_exists( $native, 'inner_wrapper' ) ) {
			return $native::inner_wrapper( $widget );
		}
		static $cache = array();
		if ( ! array_key_exists( $widget, $cache ) ) {
			$cache[ $widget ] = null;
			if ( '' !== $widget && class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
				$instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
				if ( is_object( $instance ) && method_exists( $instance, 'has_widget_inner_wrapper' ) ) {
					$cache[ $widget ] = (bool) $instance->has_widget_inner_wrapper();
				}
			}
		}
		return $cache[ $widget ];
	}

	/** ¿Es un elemento atómico (v4)? `elType` o `widgetType` con prefijo `e-`. */
	public static function is_atomic( array $element ): bool {
		$el = (string) ( $element['elType'] ?? '' );
		return 0 === strpos( $el, 'e-' ) || ( 'widget' === $el && 0 === strpos( (string) ( $element['widgetType'] ?? '' ), 'e-' ) );
	}

	/* ---------------------------------------------------------------- Conversión */

	/**
	 * Convierte las extensiones de un elemento (sin sus hijos) y anota el resultado en el informe.
	 *
	 * @return array el elemento convertido.
	 */
	public static function element( array $element, int $post_id ): array {
		list( $element, $items ) = self::convert( $element, $post_id );
		$report = 'Digitalisimo\\Elements\\Legacy\\Report';
		if ( $items && class_exists( $report ) ) {
			foreach ( $items as $item ) {
				$report::add( $post_id, $item[0], $item[1] );
			}
		}
		return $element;
	}

	/**
	 * Lo que la conversión anotaría en una lista de elementos y sus hijos, sin escribir nada: código => recuento.
	 *
	 * @return array<string,int>
	 */
	public static function describe( array $elements ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			if ( self::detect( $settings ) || self::ambiguous( $settings ) || ( self::is_atomic( $element ) && self::has_any( $settings ) ) ) {
				list( , $items ) = self::convert( $element, self::context_post() );
				foreach ( $items as $item ) {
					$out[ $item[0] ] = ( $out[ $item[0] ] ?? 0 ) + 1;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				foreach ( self::describe( $element['elements'] ) as $code => $count ) {
					$out[ $code ] = ( $out[ $code ] ?? 0 ) + $count;
				}
			}
		}
		ksort( $out );
		return $out;
	}

	/** ¿Hay un interruptor sin prefijo encendido sin señal de Element Pack? (se informa y no se toca) */
	public static function ambiguous( array $settings ): bool {
		foreach ( self::keys() as $spec ) {
			if ( empty( $spec['bare'] ) || ! empty( $spec['generic'] ) || 'page' === ( $spec['scope'][0] ?? '' ) ) {
				continue;
			}
			foreach ( (array) $spec['switch'] as $switch ) {
				if ( array_key_exists( $switch, $settings ) && Values::used( $settings[ $switch ] ) && ! self::has_prefixed( $settings ) && ! self::signal( self::context_post() ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** ¿Alguna clave de las 28 (con prefijo, o sin él)? */
	private static function has_any( array $settings ): bool {
		foreach ( array_keys( $settings ) as $key ) {
			if ( null !== self::extension_of( (string) $key, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Alguna clave con prefijo heredado? (señal de Element Pack en el propio elemento) */
	private static function has_prefixed( array $settings ): bool {
		foreach ( array_keys( $settings ) as $key ) {
			foreach ( self::PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Conversión pura de un elemento: el elemento resultante y las anotaciones.
	 *
	 * @return array{0:array,1:array}
	 */
	private static function convert( array $element, int $post_id ): array {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$out      = new Out( $element, $post_id );
		if ( self::is_atomic( $element ) ) {
			$seen = array();
			foreach ( array_keys( $settings ) as $key ) {
				$slug = self::extension_of( (string) $key, true );
				if ( null !== $slug && ! isset( $seen[ $slug ] ) && Values::used( $settings[ $key ] ) ) {
					$seen[ $slug ] = true;
					$out->note( 'ext.v4', array( 'type' => self::label( $slug ) ) );
				}
			}
			return array( $element, $out->items );
		}
		$bare_ok = self::has_prefixed( $settings ) || self::signal( $post_id );
		$el_type = (string) ( $element['elType'] ?? 'widget' );
		$widget  = 'widget' === $el_type ? (string) ( $element['widgetType'] ?? '' ) : '';
		$changed = false;
		foreach ( self::keys() as $slug => $spec ) {
			if ( 'page' === ( $spec['scope'][0] ?? '' ) ) {
				continue;
			}
			$generic_ok = empty( $spec['generic'] ) || self::in_widgets( $spec, $widget );
			$prefixed   = self::present( $settings, (array) ( $spec['prefixes'] ?? array() ), false );
			$bare       = $generic_ok && self::present( $settings, (array) ( $spec['bare'] ?? array() ), true );
			if ( ! $prefixed && ! $bare ) {
				continue;
			}
			$out->slug   = $slug;
			$switch_used = false;
			$switch_seen = false;
			foreach ( (array) $spec['switch'] as $switch ) {
				$is_bare      = self::matches( $switch, (array) ( $spec['bare'] ?? array() ), true );
				$switch_seen  = $switch_seen || array_key_exists( $switch, $settings );
				$switch_used  = $switch_used || ( array_key_exists( $switch, $settings ) && Values::used( $settings[ $switch ] ) && ( ! $is_bare || ( $bare_ok && $generic_ok ) ) );
				if ( $is_bare && ! $bare_ok && array_key_exists( $switch, $settings ) && Values::used( $settings[ $switch ] ) && ! $prefixed ) {
					$out->note( 'ext.ambiguous', array( 'type' => self::label( $slug ), 'name' => $switch ) );
				}
			}
			if ( ! $prefixed && ! $bare_ok ) {
				// Sólo claves sin prefijo y ninguna señal de Element Pack: pueden ser de otro plugin. No se tocan.
				continue;
			}
			if ( $switch_used && 'dropped' !== $spec['treatment'] ) {
				if ( ! self::in_scope( $spec, $el_type, $widget ) ) {
					$out->note( 'ext.inert', array( 'type' => self::label( $slug ) ) );
				} elseif ( ! Activation::enabled( $slug ) ) {
					$out->note( 'ext.disabled', array( 'type' => self::label( $slug ) ) );
				} elseif ( ! empty( $spec['handler'] ) ) {
					list( $class, $method ) = explode( '::', (string) $spec['handler'] );
					$class                  = __NAMESPACE__ . '\\' . $class;
					$before                 = count( $out->items );
					$done                   = (bool) call_user_func( array( $class, $method ), $out );
					if ( $done ) {
						array_splice( $out->items, $before, 0, array( array( 'ext.converted.' . self::code( $slug ), array( 'element' => $out->label() ) ) ) );
					}
				}
			}
			self::clean( $out, $spec, $switch_seen && $bare_ok && $generic_ok );
			$changed = true;
		}
		if ( ! $changed ) {
			return array( $element, $out->items );
		}
		$s = $out->s;
		if ( $out->css ) {
			$s['custom_css'] = trim( ( isset( $s['custom_css'] ) && is_string( $s['custom_css'] ) && '' !== trim( $s['custom_css'] ) ? $s['custom_css'] . "\n" : '' ) . implode( "\n", $out->css ) );
		}
		$element['settings'] = $s;
		if ( $out->prepend ) {
			$element['elements'] = array_merge( $out->prepend, is_array( $element['elements'] ?? null ) ? $element['elements'] : array() );
		}
		return array( $element, $out->items );
	}

	/** ¿Hay en los ajustes alguna clave de la lista? */
	private static function present( array $settings, array $list, bool $bare ): bool {
		if ( ! $list ) {
			return false;
		}
		foreach ( array_keys( $settings ) as $key ) {
			if ( self::matches( (string) $key, $list, $bare ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿El widget está en la lista `widgets` de la extensión (por su tipo o por el destino de su mapa)? */
	private static function in_widgets( array $spec, string $widget ): bool {
		if ( empty( $spec['widgets'] ) ) {
			return true;
		}
		return in_array( $widget, (array) $spec['widgets'], true ) || in_array( self::effective_widget( $widget ), (array) $spec['widgets'], true );
	}

	/** ¿La extensión actuaba en este elemento? */
	private static function in_scope( array $spec, string $el_type, string $widget ): bool {
		if ( ! in_array( $el_type, (array) $spec['scope'], true ) ) {
			return false;
		}
		return 'widget' !== $el_type || self::in_widgets( $spec, $widget );
	}

	/**
	 * Retira las claves de la extensión: las de prefijo siempre; las sin prefijo sólo si su interruptor está y hay señal
	 * de Element Pack. También sus entradas de `__globals__` y `__dynamic__`.
	 */
	private static function clean( Out $out, array $spec, bool $bare ): void {
		$prefixes = (array) ( $spec['prefixes'] ?? array() );
		$bares    = $bare ? (array) ( $spec['bare'] ?? array() ) : array();
		foreach ( array_keys( $out->s ) as $key ) {
			$key = (string) $key;
			if ( self::matches( $key, $prefixes, false ) || ( $bares && self::matches( $key, $bares, true ) ) ) {
				unset( $out->s[ $key ] );
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( ! isset( $out->s[ $bag ] ) || ! is_array( $out->s[ $bag ] ) ) {
				continue;
			}
			foreach ( array_keys( $out->s[ $bag ] ) as $key ) {
				$key = (string) $key;
				if ( self::matches( $key, $prefixes, false ) || ( $bares && self::matches( $key, $bares, true ) ) ) {
					unset( $out->s[ $bag ][ $key ] );
				}
			}
			if ( ! $out->s[ $bag ] ) {
				unset( $out->s[ $bag ] );
			}
		}
	}
}
