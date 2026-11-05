<?php
namespace Digitalisimo\Elements\Design\Presets;

use Digitalisimo\Elements\Design\Css;

defined( 'ABSPATH' ) || exit;

/**
 * Aplanado de los presets en el CSS del documento que los usa (V.2.3 de la especificación).
 *
 * En `elementor/element/before_parse_css` (`elementor/core/files/css/post.php:304`), que corre antes de
 * `render_element_global_styles()` y `render_element_styles()` y antes de cualquier `get_parsed_dynamic_settings()`
 * del elemento (el elemento se crea de sus datos dentro de `render_css()`), para cada elemento con
 * `digitalisimo_preset` de un preset existente, no inerte, del mismo tipo y que hoy supera `Store::sanitize_settings()`:
 *
 * 1. se toman las claves del preset y se quitan las de los controles que el elemento **guarda** en cualquier variante
 *    responsiva, en sus ajustes, en `__globals__` o en `__dynamic__` (R-M10: un control es local o del preset);
 * 2. las que quedan se escriben con `set_settings()` sobre los ajustes en memoria (que ya llevan los valores por
 *    defecto), y su `__globals__` se fusiona sin pisar el del elemento;
 * 3. `reset_render_state()` descarta lo que el elemento hubiera calculado antes.
 *
 * Así el núcleo genera el CSS del elemento con sus propios selectores (RTL, `.woocommerce`, `{{ID}}`) y con el valor
 * del preset donde antes imprimía el valor por defecto local (V-B1). Nada se guarda: ni `_elementor_data` ni el HTML
 * cambian, y la caché de elementos no se toca. En el CSS dinámico (`Dynamic_CSS`, sólo los valores `__dynamic__`) no
 * hace nada. Un documento sin vínculos no lee la opción.
 */
final class Compiler {
	/** @var bool */
	private static $initialized = false;

	/** @var array<string,array|null> Ajustes validados por slug, tipo y huella en esta petición (`null` = no se aplica). */
	private static $validated = array();

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/element/before_parse_css', array( __CLASS__, 'flatten' ), 10, 2 );
	}

	/**
	 * @param mixed $post_css Archivo CSS que se genera.
	 * @param mixed $element  Elemento.
	 */
	public static function flatten( $post_css, $element ): void {
		if ( ! $element instanceof \Elementor\Element_Base || $post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS ) {
			return;
		}
		$local = $element->get_data( 'settings' );
		$local = is_array( $local ) ? $local : array();
		$slug  = isset( $local[ Controls::CONTROL ] ) && is_string( $local[ Controls::CONTROL ] ) ? $local[ Controls::CONTROL ] : '';
		if ( '' === $slug ) {
			return;
		}
		$preset = Store::get( $slug );
		if ( null === $preset || ! empty( $preset['inert'] ) || $preset['type'] !== $element->get_name() ) {
			return;
		}
		$clean = self::validated( $slug );
		if ( ! $clean ) {
			return;
		}
		// Dentro de `parse_content()` los controles de estilo ya son visibles; fuera (otro llamador), se hacen visibles aquí.
		list( $controls, $settings ) = Css::with_style_controls(
			function () use ( $element ) {
				return array( $element->get_controls(), $element->get_settings() );
			}
		);
		$fill = self::fill( $clean, $local, $controls );
		if ( ! $fill['values'] && ! $fill['globals'] ) {
			return;
		}
		foreach ( $fill['values'] as $key => $value ) {
			$settings[ $key ] = $value;
		}
		if ( $fill['globals'] ) {
			$settings['__globals__'] = array_merge( $fill['globals'], isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ? array_filter( $settings['__globals__'] ) : array() );
		}
		$element->set_settings( $settings );
		if ( method_exists( $element, 'reset_render_state' ) ) {
			$element->reset_render_state();
		}
	}

	/**
	 * Lo que el preset aporta a un elemento: sus valores y globales de los controles que el elemento no guarda.
	 *
	 * @param array $preset   Ajustes validados del preset.
	 * @param array $local    Ajustes guardados del elemento (`get_data( 'settings' )`).
	 * @param array $controls Controles del elemento.
	 * @return array `{ values, globals }`.
	 */
	public static function fill( array $preset, array $local, array $controls ): array {
		$own = array();
		foreach ( array( $local, self::filled( $local, '__globals__' ), self::filled( $local, '__dynamic__' ) ) as $source ) {
			foreach ( array_keys( $source ) as $key ) {
				if ( '__globals__' === $key || '__dynamic__' === $key ) {
					continue;
				}
				$root         = Store::root( (string) $key, $controls );
				$own[ null === $root ? (string) $key : $root ] = true;
			}
		}
		$values  = array();
		$globals = array();
		foreach ( $preset as $key => $value ) {
			if ( '__globals__' === $key || '__dynamic__' === $key ) {
				continue;
			}
			$root = Store::root( (string) $key, $controls );
			if ( null !== $root && ! isset( $own[ $root ] ) ) {
				$values[ $key ] = $value;
			}
		}
		foreach ( self::filled( $preset, '__globals__' ) as $key => $value ) {
			$root = Store::root( (string) $key, $controls );
			if ( null !== $root && ! isset( $own[ $root ] ) ) {
				$globals[ $key ] = $value;
			}
		}
		return array( 'values' => $values, 'globals' => $globals );
	}

	/**
	 * Ajustes del preset que hoy pasan `Store::sanitize_settings()` (V-I3), una vez por petición, o `null` si no
	 * existe, es inerte o no pasa el saneado: entonces no se aplica y el editor lo marca «con error».
	 */
	public static function validated( string $slug ): ?array {
		$preset = Store::get( $slug );
		if ( null === $preset || ! empty( $preset['inert'] ) || ! is_array( $preset['settings'] ) || ! $preset['settings'] ) {
			return null;
		}
		$key = $slug . '|' . $preset['type'] . '|' . Store::hash( $preset['settings'] );
		if ( ! array_key_exists( $key, self::$validated ) ) {
			$sanitized               = Store::sanitize_settings( $preset['type'], $preset['settings'] );
			self::$validated[ $key ] = $sanitized ? $sanitized : null;
		}
		return self::$validated[ $key ];
	}

	/** Olvida lo validado en esta petición. */
	public static function reset_cache(): void {
		self::$validated = array();
	}

	/** Las entradas no vacías de `$settings[ $key ]`. */
	private static function filled( array $settings, string $key ): array {
		return isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? array_filter(
			$settings[ $key ],
			function ( $value ) {
				return null !== $value && '' !== $value && array() !== $value;
			}
		) : array();
	}
}
