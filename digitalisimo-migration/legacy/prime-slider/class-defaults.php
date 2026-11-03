<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

use Digitalisimo\Elements\Legacy\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Valores por defecto de Prime Slider (V.10.1, §3.E.0.2).
 *
 * Elementor no guarda los valores iguales a los de su control, tampoco en las filas de los repetidores
 * (`ELE/elementor/assets/js/editor-modules.js:1172-1197`): antes de traducir hay que completarlos.
 *
 * - **Con Prime Slider cargado y el widget registrado**, se leen de `$widget->get_controls()`: controles responsivos,
 *   campos y filas por defecto de los repetidores y controles de las *skins*, en la versión y el idioma instalados.
 * - **Si no** (marcadores, revisiones tras la retirada, pruebas), del archivo estático `defaults/<id>.php` del frente B
 *   (contrato C9), con dos marcadores: `{{ps_assets}}` (la carpeta `assets/` de Lite instalado, o el valor se vacía si
 *   no está) y `array( '__t' => 'Read More', 'd' => '<dominio>' )` (se traduce con el MO de `WP_LANG_DIR/plugins/` si
 *   existe; si no, queda literal).
 *
 * Una clave ausente toma su valor por defecto; un repetidor ausente, sus filas por defecto; un campo ausente de una fila,
 * el suyo. Los valores de varias partes (URL, imagen, medidas) se completan parte a parte, como hace Elementor. Una
 * imagen igual al marcador de posición de Elementor cuenta como «sin imagen». Se aceptan también documentos que
 * guardaron todos los valores.
 */
final class Defaults {
	/** Valores por defecto que nunca se rellenan (§3.E.0.3): apuntan a la carpeta del plugin y no se guardaban. */
	const SKIP = array(
		'prime-slider-turnar' => array( 'turnar_center_image' ),
	);

	/** Tipos de control sin valor (interfaz del panel). */
	const UI_TYPES = array( 'section', 'tab', 'tabs', 'raw_html', 'heading', 'divider', 'button', 'deprecated_notice', 'alert', 'notice', 'popover_toggle_end' );

	/** @var string Origen de los últimos valores: `registered`, `static` o `none`. */
	public static $last = 'none';

	/** @var bool El último relleno estático dejó algún texto sin traducir. */
	public static $literal = false;

	/** @var string|null Carpeta de los archivos estáticos (pruebas). */
	private static $dir = null;

	/** @var array<string, array|null> */
	private static $cache = array();

	/** @var array<string, object|false> */
	private static $mo = array();

	/** @var array|null */
	private static $installed = null;

	/* ---------------------------------------------------------------- Relleno */

	/** Ajustes con los valores por defecto completados. */
	public static function fill( string $id, array $settings ): array {
		$widget        = Maps::widget( $id );
		self::$literal = false;
		$spec          = self::registered( $widget );
		self::$last    = $spec ? 'registered' : 'none';
		if ( ! $spec ) {
			$spec       = self::data( $widget );
			self::$last = $spec ? 'static' : 'none';
		}
		if ( $spec ) {
			$settings = self::apply( $settings, $spec, self::SKIP[ $widget ] ?? array() );
		}
		return self::clear_placeholders( $settings );
	}

	/**
	 * Valores por defecto de un widget en el formato de C9 (`controls`, `repeaters`, `styles`), del widget registrado o
	 * del archivo estático. null si no hay ninguno.
	 */
	public static function spec( string $id ): ?array {
		$widget = Maps::widget( $id );
		return self::registered( $widget ) ?? self::data( $widget );
	}

	/** Valor por defecto de un control (null si no se conoce). */
	public static function control( string $id, string $key ) {
		$spec = self::spec( $id );
		return $spec['controls'][ $key ] ?? null;
	}

	/** Valor por defecto de un campo de un repetidor (null si no se conoce). */
	public static function field( string $id, string $repeater, string $field ) {
		$spec = self::spec( $id );
		return $spec['repeaters'][ $repeater ]['fields'][ $field ] ?? null;
	}

	/** Claves de los controles de estilo del widget (para contar lo que no tiene equivalente). */
	public static function style_keys( string $id ): array {
		$spec = self::spec( $id );
		return array_map( 'strval', array_keys( is_array( $spec['styles'] ?? null ) ? $spec['styles'] : array() ) );
	}

	private static function apply( array $settings, array $spec, array $skip ): array {
		$repeaters = is_array( $spec['repeaters'] ?? null ) ? $spec['repeaters'] : array();
		foreach ( is_array( $spec['controls'] ?? null ) ? $spec['controls'] : array() as $key => $default ) {
			$key = (string) $key;
			if ( isset( $repeaters[ $key ] ) || in_array( $key, $skip, true ) ) {
				continue;
			}
			$settings = self::merge_value( $settings, $key, $default );
		}
		foreach ( $repeaters as $key => $repeater ) {
			$key = (string) $key;
			if ( in_array( $key, $skip, true ) || ! is_array( $repeater ) ) {
				continue;
			}
			$rows = array_key_exists( $key, $settings ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : ( is_array( $repeater['rows'] ?? null ) ? $repeater['rows'] : array() );
			$out  = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( is_array( $repeater['fields'] ?? null ) ? $repeater['fields'] : array() as $field => $default ) {
					$row = self::merge_value( $row, (string) $field, $default );
				}
				$out[] = $row;
			}
			$settings[ $key ] = $out;
		}
		return $settings;
	}

	/** Clave ausente → valor por defecto; valor de varias partes → completa las que faltan. */
	private static function merge_value( array $values, string $key, $default ): array {
		if ( null === $default ) {
			return $values;
		}
		if ( ! array_key_exists( $key, $values ) ) {
			$values[ $key ] = $default;
		} elseif ( is_array( $default ) && is_array( $values[ $key ] ) && $default && ! self::is_list( $default ) && ! self::is_list( $values[ $key ] ) ) {
			$values[ $key ] = array_merge( $default, $values[ $key ] );
		}
		return $values;
	}

	private static function is_list( array $value ): bool {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/** Una imagen igual al marcador de posición de Elementor es «sin imagen», en cualquier nivel. */
	private static function clear_placeholders( array $values ): array {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$values[ $key ] = self::is_placeholder( $value ) ? array( 'url' => '', 'id' => '' ) : self::clear_placeholders( $value );
			}
		}
		return $values;
	}

	/** ¿Es una MEDIA con la imagen de marcador de posición de Elementor? */
	public static function is_placeholder( $media ): bool {
		if ( ! is_array( $media ) || ! isset( $media['url'] ) || ! is_string( $media['url'] ) || '' === $media['url'] || ! empty( $media['id'] ) ) {
			return false;
		}
		$url = $media['url'];
		if ( class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'get_placeholder_image_src' ) && $url === \Elementor\Utils::get_placeholder_image_src() ) {
			return true;
		}
		return 1 === preg_match( '#/elementor/assets/images/placeholder\.png$#', (string) strtok( $url, '?#' ) );
	}

	/* ---------------------------------------------------------------- Widget registrado */

	/** Valores del widget registrado, sólo con Prime Slider cargado. */
	private static function registered( string $widget ): ?array {
		if ( '' === $widget || ! self::prime_slider_active() || ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		$key = 'registered:' . $widget;
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}
		self::$cache[ $key ] = null;
		$instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
		if ( ! is_object( $instance ) || ! method_exists( $instance, 'get_controls' ) ) {
			return null;
		}
		try {
			$controls = self::with_style_controls( static function () use ( $instance ) {
				return (array) $instance->get_controls();
			} );
		} catch ( \Throwable $e ) {
			return null;
		}
		$spec = array( 'controls' => array(), 'repeaters' => array(), 'styles' => array() );
		foreach ( $controls as $name => $control ) {
			$name = (string) ( is_array( $control ) && isset( $control['name'] ) ? $control['name'] : $name );
			$type = is_array( $control ) ? (string) ( $control['type'] ?? '' ) : '';
			if ( '' === $name || in_array( $type, self::UI_TYPES, true ) ) {
				continue;
			}
			if ( 'style' === ( $control['tab'] ?? '' ) ) {
				$spec['styles'][ $name ] = array( 'type' => $type );
			}
			if ( 'repeater' === $type ) {
				$fields = array();
				foreach ( (array) ( $control['fields'] ?? array() ) as $field_name => $field ) {
					$field_name = (string) ( is_array( $field ) && isset( $field['name'] ) ? $field['name'] : $field_name );
					if ( is_array( $field ) && array_key_exists( 'default', $field ) && ! in_array( (string) ( $field['type'] ?? '' ), self::UI_TYPES, true ) ) {
						$fields[ $field_name ] = $field['default'];
					}
				}
				$spec['repeaters'][ $name ] = array( 'fields' => $fields, 'rows' => is_array( $control['default'] ?? null ) ? $control['default'] : array() );
				$spec['controls'][ $name ]  = $spec['repeaters'][ $name ]['rows'];
				continue;
			}
			if ( array_key_exists( 'default', $control ) ) {
				$spec['controls'][ $name ] = $control['default'];
			}
		}
		self::$cache[ $key ] = $spec;
		return $spec;
	}

	/** Prime Slider cargado (Lite define `BDTPS_CORE_VER`; Pro no carga sin Lite). */
	public static function prime_slider_active(): bool {
		if ( class_exists( Translator::class, false ) && method_exists( Translator::class, 'prime_slider_active' ) ) {
			return (bool) Translator::prime_slider_active();
		}
		return defined( 'BDTPS_CORE_VER' );
	}

	/** Como Elementor al generar el CSS: con los controles de estilo y los responsivos duplicados. */
	private static function with_style_controls( callable $callback ) {
		$performance = '\\Elementor\\Core\\Frontend\\Performance';
		$previous    = class_exists( $performance ) && method_exists( $performance, 'is_use_style_controls' ) ? $performance::is_use_style_controls() : null;
		$breakpoints = isset( \Elementor\Plugin::$instance->breakpoints ) && is_object( \Elementor\Plugin::$instance->breakpoints ) ? \Elementor\Plugin::$instance->breakpoints : null;
		$mode        = $breakpoints && method_exists( $breakpoints, 'get_responsive_control_duplication_mode' ) ? $breakpoints->get_responsive_control_duplication_mode() : null;
		if ( null !== $previous ) {
			$performance::set_use_style_controls( true );
		}
		if ( null !== $mode ) {
			$breakpoints->set_responsive_control_duplication_mode( 'on' );
		}
		try {
			return $callback();
		} finally {
			if ( null !== $previous ) {
				$performance::set_use_style_controls( $previous );
			}
			if ( null !== $mode ) {
				$breakpoints->set_responsive_control_duplication_mode( $mode );
			}
		}
	}

	/* ---------------------------------------------------------------- Archivo estático (B, C9) */

	/** Carpeta de los archivos estáticos (`prime-slider/defaults/`). */
	public static function dir(): string {
		return null !== self::$dir ? self::$dir : __DIR__ . '/defaults/';
	}

	/** Otra carpeta de valores estáticos (sólo pruebas; null vuelve a la del plugin). */
	public static function use_dir( ?string $dir ): void {
		self::$dir   = null === $dir ? null : rtrim( $dir, '/' ) . '/';
		self::$cache = array();
	}

	/** Valores del archivo estático, con los marcadores resueltos. */
	private static function data( string $widget ): ?array {
		if ( '' === $widget ) {
			return null;
		}
		$key = 'static:' . $widget;
		if ( array_key_exists( $key, self::$cache ) ) {
			if ( self::$cache[ $key ] && ! empty( self::$cache[ $key ]['_literal'] ) ) {
				self::$literal = true;
			}
			return self::$cache[ $key ];
		}
		self::$cache[ $key ] = null;
		$short = Maps::short( $widget );
		foreach ( array( $widget, $short ) as $name ) {
			$file = self::dir() . $name . '.php';
			if ( ! is_file( $file ) ) {
				continue;
			}
			$raw = include $file;
			if ( ! is_array( $raw ) ) {
				break;
			}
			$literal = false;
			$spec    = array(
				'controls'  => self::expand( is_array( $raw['controls'] ?? null ) ? $raw['controls'] : array(), $literal ),
				'repeaters' => array(),
				'styles'    => is_array( $raw['styles'] ?? null ) ? $raw['styles'] : array(),
			);
			foreach ( is_array( $raw['repeaters'] ?? null ) ? $raw['repeaters'] : array() as $repeater => $data ) {
				if ( is_array( $data ) ) {
					$spec['repeaters'][ (string) $repeater ] = array(
						'fields' => self::expand( is_array( $data['fields'] ?? null ) ? $data['fields'] : array(), $literal ),
						'rows'   => array_values( self::expand( is_array( $data['rows'] ?? null ) ? $data['rows'] : array(), $literal ) ),
					);
				}
			}
			$spec['_literal']    = $literal;
			self::$literal       = self::$literal || $literal;
			self::$cache[ $key ] = $spec;
			break;
		}
		return self::$cache[ $key ];
	}

	/** Resuelve `{{ps_assets}}` y `{"__t": …}` en cualquier nivel. */
	private static function expand( array $values, bool &$literal ): array {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) && array_key_exists( '__t', $value ) ) {
				$values[ $key ] = self::translate( (string) $value['__t'], (string) ( $value['d'] ?? '' ), $literal );
			} elseif ( is_array( $value ) ) {
				$values[ $key ] = self::expand( $value, $literal );
			} elseif ( is_string( $value ) && false !== strpos( $value, '{{ps_assets}}' ) ) {
				$assets         = self::assets_url();
				$values[ $key ] = '' === $assets ? '' : str_replace( '{{ps_assets}}', $assets, $value );
			}
		}
		return $values;
	}

	/** Texto del valor por defecto en el idioma del sitio, con el MO del plugin si existe. */
	private static function translate( string $text, string $domain, bool &$literal ): string {
		$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US' );
		if ( '' === $text || '' === $locale || 'en_US' === $locale ) {
			return $text;
		}
		$mo = self::mo( $domain, $locale );
		if ( ! $mo ) {
			$literal = true;
			return $text;
		}
		$translated = (string) $mo->translate( $text );
		if ( $translated === $text ) {
			$literal = true;
		}
		return $translated;
	}

	/** MO de un dominio de plugin en `WP_LANG_DIR/plugins/` (false si no hay). */
	private static function mo( string $domain, string $locale ) {
		$key = $domain . '|' . $locale;
		if ( isset( self::$mo[ $key ] ) ) {
			return self::$mo[ $key ];
		}
		self::$mo[ $key ] = false;
		if ( 1 !== preg_match( '/^[a-z0-9-]{1,80}$/', $domain ) || 1 !== preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]{2,8})*$/', $locale ) || ! defined( 'WP_LANG_DIR' ) ) {
			return false;
		}
		$file = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo';
		if ( ! class_exists( '\\MO' ) && defined( 'ABSPATH' ) && defined( 'WPINC' ) && is_file( ABSPATH . WPINC . '/pomo/mo.php' ) ) {
			require_once ABSPATH . WPINC . '/pomo/mo.php';
		}
		if ( ! is_file( $file ) || ! class_exists( '\\MO' ) ) {
			return false;
		}
		$mo = new \MO();
		if ( $mo->import_from_file( $file ) ) {
			self::$mo[ $key ] = $mo;
		}
		return self::$mo[ $key ];
	}

	/* ---------------------------------------------------------------- Plugins instalados */

	/**
	 * Prime Slider instalado, activo o no (por `get_plugins()`): archivo, carpeta y si es Lite (donde están las imágenes).
	 *
	 * @return array<int, array{file:string, slug:string, dir:string, lite:bool}>
	 */
	public static function installed(): array {
		if ( null !== self::$installed ) {
			return self::$installed;
		}
		self::$installed = array();
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
			return self::$installed;
		}
		foreach ( (array) get_plugins() as $file => $data ) {
			$file = (string) $file;
			$name = is_array( $data ) ? (string) ( $data['Name'] ?? '' ) : '';
			$slug = dirname( $file );
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $slug ) ) {
				continue;
			}
			if ( 'bdthemes-prime-slider.php' !== basename( $file ) && 1 !== preg_match( '/^Prime Slider\b/i', $name ) ) {
				continue;
			}
			self::$installed[] = array(
				'file' => $file,
				'slug' => $slug,
				'dir'  => rtrim( WP_PLUGIN_DIR, '/' ) . '/' . $slug,
				// «Prime Slider» es Lite; «Prime Slider Pro», Pro. Una carpeta `…-lite` también es Lite.
				'lite' => 1 === preg_match( '/^Prime Slider\s*$/i', $name ) || 1 === preg_match( '/-lite$/', $slug ),
			);
		}
		return self::$installed;
	}

	/** URL de `assets/` de Lite instalado ('' si no está). */
	public static function assets_url(): string {
		foreach ( self::installed() as $plugin ) {
			if ( $plugin['lite'] && function_exists( 'plugins_url' ) ) {
				return (string) plugins_url( 'assets/', rtrim( WP_PLUGIN_DIR, '/' ) . '/' . $plugin['file'] );
			}
		}
		return '';
	}

	/** Olvida lo calculado (pruebas). */
	public static function reset(): void {
		self::$cache     = array();
		self::$mo        = array();
		self::$installed = null;
		self::$last      = 'none';
		self::$literal   = false;
	}
}
