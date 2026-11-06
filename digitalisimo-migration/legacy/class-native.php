<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-masks.php';
require_once __DIR__ . '/class-context.php';
require_once __DIR__ . '/class-report.php';
require_once __DIR__ . '/class-demo-media.php';

/**
 * Conversión completa de un documento de Element Pack al formato propio.
 *
 * Cada widget heredado pasa a su widget de destino (propio de DIGITALÍSIMO Elements o de PRO
 * Elements) sólo con ajustes que ese widget conoce: los estilos que sólo existían en Element Pack se
 * vuelcan al CSS personalizado del elemento, y lo que el adaptador hacía en tiempo de ejecución pasa
 * a los rasgos propios del núcleo (categorías en Entradas, partes de productos, origen ACF,
 * diapositivas de plantilla). En cualquier elemento, las reglas de visibilidad, las figuras, el
 * enlace del elemento y los efectos de las extensiones pasan a sus ajustes propios o al CSS
 * personalizado, y las máscaras y separadores de Element Pack a copias propias (ver Masks). Tras
 * convertir, el documento no contiene identificadores de Element Pack y se pinta sólo con
 * DIGITALÍSIMO Elements.
 */
final class Native {
	const STYLE_SECTION = 'digitalisimo_legacy_style';

	/** Errores al calcular CSS durante la última conversión (para diagnóstico). */
	public static $errors = array();

	/** Claves de cualquier elemento que delatan datos heredados. */
	const ELEMENT_PREFIXES = array( 'ep_', 'element_pack_', 'bdt_' );

	/** Ajustes de elemento (no de widget): visibilidad, figuras, enlace y efectos de las extensiones. */
	const ELEMENT_KEYS = '/^(ep_display_conditions|ep_condition_|bdt_shape_builder_|element_pack_(wrapper_link|backdrop_filter|bf_|liquid_glass|tgb_|ris_)|ep_floating_effects_|ep_notation_)/';

	/**
	 * Modo restringido (V.3.4): un documento migrado por Migración ≤ 1.0.20 sólo recibe la conversión de 1.0.20 y las
	 * reparaciones de referencias (iconos, etiquetas dinámicas, marcadores); los efectos que F8 convierte por primera
	 * vez se informan (`core.residual`) y no se tocan. Lo fija Migration::convert_document().
	 */
	public static $restricted = false;

	/** Tipo de los marcadores de K (`digitalisimo-unconverted`). */
	const MARKER_WIDGET = 'digitalisimo-unconverted';

	/**
	 * Convierte un elemento y sus hijos. `$parent` es el `elType` del padre (`section`, `column`, `container` o '' en la
	 * raíz). Un hijo convertido puede ser un fragmento (`elType` = `__fragment`): sus elementos ocupan su lugar.
	 *
	 * Las clases de los demás frentes se llaman sólo si existen (`class_exists`): sin ellas, la conversión es la de 1.0.20.
	 *
	 * @return array{0:array,1:int} elemento convertido y número de cambios.
	 */
	public static function element( array $element, $post_id, string $parent = '' ) {
		$changes  = 0;
		$post_id  = (int) $post_id;
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$type     = (string) ( $element['widgetType'] ?? '' );
		$widget   = 'widget' === ( $element['elType'] ?? '' );
		if ( $widget && Translator::is_prime_slider_id( $type ) && self::has( 'Prime_Slider\\Converter', 'element' ) ) {
			// Prime Slider: elemento, fragmento o null (sin cambio; el convertidor ya lo informó).
			$converted = Prime_Slider\Converter::element( $element, $post_id, $parent );
			if ( is_array( $converted ) ) {
				return array( $converted, 1 + self::count_children( $converted ) );
			}
			return array( $element, 0 );
		}
		if ( $widget && Translator::is_legacy_id( $type ) && Translator::map( $type ) ) {
			$target = Translator::target( $type );
			if ( false === self::target_ready( $target ) ) {
				// Destino no registrado en este sitio (F8-D8): sin convertir, en un marcador que el editor no borra.
				Report::add( $post_id, 'core.target_missing', array( 'widget' => $type, 'target' => $target ) );
				return array( self::wrap( $element ), 1 );
			}
			// Los ajustes de elemento (visibilidad, figuras, enlace, efectos) no pasan por el traductor del widget.
			$bag = array();
			foreach ( $settings as $key => $value ) {
				if ( preg_match( self::ELEMENT_KEYS, (string) $key ) || self::effect_key( (string) $key ) ) {
					$bag[ $key ] = $value;
					unset( $settings[ $key ] );
				}
			}
			// Sus colores globales y textos dinámicos también: widget() filtra esas bolsas por los controles del destino.
			foreach ( array( '__globals__', '__dynamic__' ) as $refs ) {
				foreach ( (array) ( $settings[ $refs ] ?? array() ) as $key => $value ) {
					if ( preg_match( self::ELEMENT_KEYS, (string) $key ) || self::effect_key( (string) $key ) ) {
						$bag[ $refs ][ $key ] = $value;
					}
				}
			}
			$element['settings'] = $settings;
			$element             = self::widget( $element, $post_id );
			if ( $bag ) {
				foreach ( array( '__globals__', '__dynamic__' ) as $refs ) {
					if ( isset( $bag[ $refs ] ) ) {
						$element['settings'][ $refs ] = (array) ( $element['settings'][ $refs ] ?? array() ) + $bag[ $refs ];
						unset( $bag[ $refs ] );
					}
				}
				$element['settings'] = $element['settings'] + $bag;
				$element             = self::effects( $element, $post_id );
				$element['settings'] = self::element_settings( $element, $post_id );
			}
			++$changes;
		} elseif ( $widget && Translator::is_legacy_id( $type ) && ! self::$restricted && self::can_wrap() ) {
			// Widget de Element Pack sin adaptador: en un marcador (`source: migration`) con su motivo. El editor no lo
			// borra al guardar, y K lo devuelve solo si Element Pack vuelve a estar activo.
			Report::add( $post_id, 'core.no_adapter', array( 'widget' => $type, 'note' => self::reason( $type ) ) );
			return array( self::wrap( $element ), 1 );
		} elseif ( ! self::$restricted && self::has( 'Effects\\Dispatcher', 'is_atomic' ) && Effects\Dispatcher::is_atomic( $element ) && self::effect_detect( $settings ) && ! self::other_legacy( $settings ) ) {
			// Elemento atómico (v4) con sólo extensiones de Element Pack: se informa (ext.v4) y no se toca.
			$element = self::effects( $element, $post_id );
		} elseif ( self::has_legacy_element_settings( $settings ) ) {
			$element             = self::effects( $element, $post_id );
			$element['settings'] = self::element_settings( $element, $post_id );
			++$changes;
		} elseif ( self::$restricted && self::effect_detect( $settings ) ) {
			Report::add( $post_id, 'core.residual', array( 'element' => trim( (string) ( $element['elType'] ?? '' ) . ' #' . (string) ( $element['id'] ?? '' ) ) ) );
		}
		// Máscaras y separadores con figuras de Element Pack, en cualquier elemento (también en los ya convertidos).
		if ( Masks::is_legacy( $element ) ) {
			$element['settings'] = Masks::convert( $element, self::$errors );
			$changes            += $changes ? 0 : 1;
		}
		// Referencias que no cambian el contenido editado: iconos y etiquetas dinámicas de Element Pack.
		foreach ( array( 'Platform\\Icons', 'Platform\\Dynamic_Tags' ) as $class ) {
			if ( self::has( $class, 'rewrite' ) ) {
				$rewritten = call_user_func( array( __NAMESPACE__ . '\\' . $class, 'rewrite' ), $element );
				if ( is_array( $rewritten ) && $rewritten !== $element ) {
					$element  = $rewritten;
					$changes += $changes ? 0 : 1;
				}
			}
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$children = array();
			$el_type  = (string) ( $element['elType'] ?? '' );
			foreach ( $element['elements'] as $child ) {
				if ( ! is_array( $child ) ) {
					$children[] = $child;
					continue;
				}
				list( $child, $child_changes ) = self::element( $child, $post_id, $el_type );
				$changes                     += $child_changes;
				// Un fragmento (Prime Slider con hermanos: redes, «Scroll Down») se aplana en el padre.
				if ( '__fragment' === ( $child['elType'] ?? '' ) ) {
					foreach ( (array) ( $child['elements'] ?? array() ) as $part ) {
						if ( is_array( $part ) ) {
							$children[] = $part;
						}
					}
					continue;
				}
				$children[] = $child;
			}
			$element['elements'] = $children;
		}
		return array( $element, $changes );
	}

	/** Lista de elementos de primer nivel (un documento) con los fragmentos aplanados. */
	public static function flatten( array $elements ) {
		$out = array();
		foreach ( $elements as $element ) {
			if ( is_array( $element ) && '__fragment' === ( $element['elType'] ?? '' ) ) {
				foreach ( (array) ( $element['elements'] ?? array() ) as $part ) {
					if ( is_array( $part ) ) {
						$out[] = $part;
					}
				}
				continue;
			}
			$out[] = $element;
		}
		return $out;
	}

	/** ¿Existe la clase de otro frente con ese método? (`Platform\Icons`, `Effects\Dispatcher`…) */
	private static function has( $class, $method ) {
		$class = __NAMESPACE__ . '\\' . $class;
		return class_exists( $class ) && method_exists( $class, $method );
	}

	private static function count_children( array $element ) {
		$count = 0;
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			$count += is_array( $child ) ? 1 + self::count_children( $child ) : 0;
		}
		return $count;
	}

	/** ¿Claves con prefijo heredado que no son de las extensiones de C (visibilidad, máscaras…)? */
	private static function other_legacy( array $settings ) {
		foreach ( array_keys( $settings ) as $key ) {
			if ( preg_match( '/^(ep_|element_pack_|bdt_)/', (string) $key ) && ! self::effect_key( (string) $key ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Es una clave de una extensión que convierte el frente C (también sin prefijo)? */
	private static function effect_key( $key ) {
		return ! self::$restricted && self::has( 'Effects\\Dispatcher', 'is_key' ) && Effects\Dispatcher::is_key( $key );
	}

	/** ¿Tiene el elemento ajustes de las extensiones de C (con o sin prefijo)? */
	private static function effect_detect( array $settings ) {
		return self::has( 'Effects\\Dispatcher', 'detect' ) && Effects\Dispatcher::detect( $settings );
	}

	/** Las extensiones de C convierten y retiran lo suyo antes de element_settings() (no en modo restringido). */
	private static function effects( array $element, $post_id ) {
		if ( self::$restricted || ! self::has( 'Effects\\Dispatcher', 'element' ) ) {
			return $element;
		}
		$converted = Effects\Dispatcher::element( $element, (int) $post_id );
		return is_array( $converted ) ? $converted : $element;
	}

	/** ¿Puede envolverse en un marcador? Sólo con K cargado (sin Elementor, en las pruebas, el comportamiento de 1.0.20). */
	private static function can_wrap() {
		return class_exists( '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve' ) && class_exists( '\\Elementor\\Plugin' );
	}

	/** Marcador de K (`source: migration`) con el elemento original. */
	private static function wrap( array $element ) {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Import\\Kits\\Preserve' ) ) {
			return $element;
		}
		return \Digitalisimo\Elements\Import\Kits\Preserve::marker( $element, 'unknown', 'migration' );
	}

	/** Motivo de un widget sin adaptador (texto de unsupported.php). */
	private static function reason( $type ) {
		static $list = null;
		if ( null === $list ) {
			$file = __DIR__ . '/unsupported.php';
			$list = is_file( $file ) ? (array) require $file : array();
		}
		return (string) ( $list[ $type ] ?? '' );
	}

	/**
	 * ¿Está registrado el widget de destino en este sitio? null si no se puede saber (sin Elementor cargado: las pruebas;
	 * cuenta como disponible), false si falta.
	 */
	public static function target_ready( string $widget ): ?bool {
		if ( '' === $widget ) {
			return false;
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) || ! is_object( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		// Contenedores y elementos que no son widgets (fragmentos de Prime Slider) no se comprueban aquí.
		return (bool) \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
	}

	/**
	 * ¿El widget conserva el envoltorio interno `.elementor-widget-container`? null si no se sabe (V.9: el destino se
	 * elige entonces con :is() para los dos marcados).
	 */
	public static function inner_wrapper( string $widget ): ?bool {
		if ( true !== self::target_ready( $widget ) ) {
			return null;
		}
		$instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
		if ( ! is_object( $instance ) || ! method_exists( $instance, 'has_widget_inner_wrapper' ) ) {
			return null;
		}
		try {
			return (bool) $instance->has_widget_inner_wrapper();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** ¿El documento (o el elemento) contiene algo heredado? (Element Pack, Prime Slider o un marcador con ellos.) */
	public static function is_legacy( array $element ) {
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' === ( $element['elType'] ?? '' ) ) {
			if ( Translator::is_known_id( $type ) ) {
				return true;
			}
			if ( self::MARKER_WIDGET === $type ) {
				$groups = self::marker_groups( $element );
				return (bool) ( $groups['convertible'] || $groups['blocked'] );
			}
		}
		if ( self::has_legacy_element_settings( (array) ( $element['settings'] ?? array() ) ) || self::effect_detect( (array) ( $element['settings'] ?? array() ) ) || Masks::is_legacy( $element ) ) {
			return true;
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) && self::is_legacy( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ¿La conversión cambiaría algo en el elemento o en sus hijos? Espejo de element(): un widget sin adaptador se queda
	 * tal cual (o en su marcador), así que un documento que sólo conserva widgets así es heredado pero no convertible, y
	 * los lotes lo saltan. Un widget de Prime Slider cuenta si tiene mapa no descartado; un marcador, si `Markers` lo
	 * clasifica como convertible.
	 */
	public static function is_convertible( array $element ) {
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$type     = (string) ( $element['widgetType'] ?? '' );
		if ( 'widget' === ( $element['elType'] ?? '' ) ) {
			if ( Translator::is_legacy_id( $type ) && Translator::map( $type ) ) {
				return true;
			}
			if ( Translator::is_legacy_id( $type ) && self::can_wrap() && ! self::$restricted ) {
				return true;
			}
			if ( Translator::is_prime_slider_id( $type ) && self::prime_slider_convertible( $type ) ) {
				return true;
			}
			if ( self::MARKER_WIDGET === $type ) {
				return (bool) self::marker_groups( $element )['convertible'];
			}
		}
		if ( self::has_legacy_element_settings( $settings ) || ( ! self::$restricted && self::effect_detect( $settings ) ) || Masks::is_legacy( $element ) ) {
			return true;
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) && self::is_convertible( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/** Un widget de Prime Slider se convierte si su mapa existe y no es un descarte (Prime_Slider\Maps de E). */
	private static function prime_slider_convertible( $type ) {
		if ( ! self::has( 'Prime_Slider\\Maps', 'get' ) ) {
			return false;
		}
		$map = Prime_Slider\Maps::get( (string) $type );
		return is_array( $map ) && 'discard' !== ( $map['decision'] ?? '' ) && 'DESCARTAR' !== ( $map['decision'] ?? '' );
	}

	/** Clasificación de un marcador por `Markers` (I): sin la clase, ni convertible ni bloqueado (1.0.20). */
	private static function marker_groups( array $marker ) {
		$none = array( 'convertible' => array(), 'blocked' => array(), 'declared' => array() );
		if ( ! self::has( 'Markers', 'classify' ) ) {
			return $none;
		}
		$groups = Markers::classify( array( $marker ) );
		return is_array( $groups ) ? $groups + $none : $none;
	}

	private static function has_legacy_element_settings( array $settings ) {
		if ( ! self::$restricted && self::effect_detect( $settings ) ) {
			return true;
		}
		foreach ( array_keys( $settings ) as $key ) {
			foreach ( self::ELEMENT_PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------- Widgets */

	/**
	 * Ejecuta con todos los controles de estilo disponibles, como hace Elementor al generar el CSS:
	 * fuera de la administración los guarda aparte y no los devuelve.
	 */
	private static function with_style_controls( callable $callback ) {
		$performance = '\\Elementor\\Core\\Frontend\\Performance';
		$previous    = class_exists( $performance ) ? $performance::is_use_style_controls() : null;
		$breakpoints = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ? \Elementor\Plugin::$instance->breakpoints : null;
		$mode        = $breakpoints ? $breakpoints->get_responsive_control_duplication_mode() : null;
		if ( null !== $previous ) {
			$performance::set_use_style_controls( true );
		}
		if ( $breakpoints ) {
			$breakpoints->set_responsive_control_duplication_mode( 'on' );
		}
		try {
			return $callback();
		} finally {
			if ( null !== $previous ) {
				$performance::set_use_style_controls( $previous );
			}
			if ( $breakpoints ) {
				$breakpoints->set_responsive_control_duplication_mode( $mode );
			}
		}
	}

	private static function widget( array $element, $post_id ) {
		$type     = (string) $element['widgetType'];
		$map      = Translator::map( $type );
		$target   = (string) $map['target'];
		$settings = Translator::translate( $type, is_array( $element['settings'] ?? null ) ? $element['settings'] : array() );
		$settings = self::demo_media( $type, $settings, (int) $post_id );
		$css      = self::legacy_style_css( $element, $settings, $post_id );
		// La máscara de imagen del widget, sobre la imagen del widget de destino.
		$original = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$mask     = Masks::widget_css( $target, $original );
		if ( '' !== $mask ) {
			$css = trim( $css . "\n" . $mask );
		} elseif ( 'yes' === ( $original['image_mask_popover'] ?? '' ) ) {
			self::$errors[] = $type . ': máscara de imagen sin archivo, retirada';
		}
		$settings = self::nativize( $type, $target, $settings );
		$controls = self::target_controls( $target );
		$out      = array();
		foreach ( $settings as $key => $value ) {
			if ( Translator::MARKER === $key ) {
				continue;
			}
			if ( '__dynamic__' === $key || '__globals__' === $key ) {
				$value = is_array( $value ) ? ( null === $controls ? $value : array_intersect_key( $value, $controls ) ) : $value;
				if ( $value ) {
					$out[ $key ] = $value;
				}
				continue;
			}
			if ( null === $controls || isset( $controls[ $key ] ) || 0 === strpos( (string) $key, '_' ) ) {
				$out[ $key ] = null === $controls ? $value : self::rows( $value, $controls[ $key ] ?? null );
			}
		}
		if ( '' !== $css ) {
			$out['custom_css'] = trim( ( isset( $out['custom_css'] ) ? (string) $out['custom_css'] . "\n" : '' ) . $css );
		}
		$element['widgetType'] = $target;
		$element['settings']   = $out;
		return $element;
	}

	/**
	 * Imágenes de ejemplo de Element Pack (F8-D17, como las de Prime Slider): cada URL de su carpeta, guardada o rellenada
	 * por los valores por defecto (`{{ep_assets}}`), pasa a una copia en la biblioteca de medios del sitio, nunca a la
	 * carpeta del plugin. En el editor y en el análisis sólo se reutiliza lo ya copiado; el análisis informa como copia lo
	 * que la tarea copiaría. Un valor por defecto que no se copia (un SVG con la subida sin filtrar apagada) se queda con
	 * la imagen de ejemplo del constructor, la de la conversión anterior (`core.demo.placeholder`). Un documento migrado
	 * con 1.0.20 (modo restringido) conserva su conversión. Los recuentos de Demo_Media vuelven a los de antes: un
	 * convertidor que envuelva este widget (Prime Slider) lee los suyos.
	 */
	private static function demo_media( string $type, array $settings, int $post_id ): array {
		if ( self::$restricted ) {
			return $settings;
		}
		$before      = array( Demo_Media::$copied, Demo_Media::$reused, Demo_Media::$skipped, Demo_Media::$planned, Demo_Media::$placeholder );
		$settings    = Demo_Media::resolve( $type, $settings );
		$copied      = Demo_Media::$copied + Demo_Media::$planned;
		$skipped     = max( 0, Demo_Media::$skipped - Demo_Media::$planned );
		$placeholder = Demo_Media::$placeholder;
		list( Demo_Media::$copied, Demo_Media::$reused, Demo_Media::$skipped, Demo_Media::$planned, Demo_Media::$placeholder ) = $before;
		if ( $copied ) {
			Report::add( $post_id, 'core.demo.copied', array( 'widget' => $type, 'count' => $copied ) );
		}
		if ( $skipped ) {
			Report::add( $post_id, 'core.demo.skipped', array( 'widget' => $type, 'count' => $skipped ) );
		}
		if ( $placeholder ) {
			Report::add( $post_id, 'core.demo.placeholder', array( 'widget' => $type, 'count' => $placeholder ) );
		}
		return $settings;
	}

	/**
	 * Filas de un repetidor del widget de destino sin las claves que no son campos suyos, igual que los ajustes del
	 * widget: un campo que sólo tenía Element Pack (el texto «Copiado» de sus botones para compartir, por ejemplo) no
	 * llega a las filas del widget de PRO, y la etapa `pro` no lo informa después como clave sin declarar. Sin la lista
	 * de campos (Elementor sin cargar, un control que no es repetidor) el valor queda igual.
	 */
	private static function rows( $value, $control ) {
		if ( ! is_array( $value ) || ! is_array( $control ) || 'repeater' !== ( $control['type'] ?? '' ) || empty( $control['fields'] ) || ! is_array( $control['fields'] ) ) {
			return $value;
		}
		$fields = array();
		foreach ( $control['fields'] as $index => $field ) {
			$fields[ is_array( $field ) && isset( $field['name'] ) ? (string) $field['name'] : (string) $index ] = true;
		}
		foreach ( $value as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $row as $key => $item ) {
				if ( '__dynamic__' === $key || '__globals__' === $key ) {
					$row[ $key ] = is_array( $item ) ? array_intersect_key( $item, $fields ) : $item;
					if ( array() === $row[ $key ] ) {
						unset( $row[ $key ] );
					}
				} elseif ( ! isset( $fields[ $key ] ) && 0 !== strpos( (string) $key, '_' ) ) {
					unset( $row[ $key ] );
				}
			}
			$value[ $index ] = $row;
		}
		return $value;
	}

	/** Ajustes que el adaptador resolvía en tiempo de ejecución, en los nombres de los rasgos propios. */
	private static function nativize( $type, $target, array $s ) {
		if ( 'woocommerce-products' === $target ) {
			$parts = array_values( array_filter( explode( ',', (string) ( $s['digitalisimo_wc_parts'] ?? '' ) ) ) );
			$s['digitalisimo_wc_custom'] = 'yes';
			$s['digitalisimo_wc_parts']  = $parts;
			if ( ! empty( $s['posts_per_page'] ) ) {
				$s['digitalisimo_products_limit'] = (int) $s['posts_per_page'];
			}
			unset( $s['posts_per_page'] );
		}
		if ( in_array( $target, array( 'loop-grid', 'loop-carousel' ), true ) && ! empty( $s['template_id'] ) ) {
			$loop = self::loop_item( (int) $s['template_id'] );
			if ( $loop ) {
				$s['template_id'] = (string) $loop;
			}
		}
		if ( 'bdt-acf-list' === $type && is_array( $s['list_icon'] ?? null ) ) {
			$s['digitalisimo_acf_icon'] = $s['list_icon'];
		}
		if ( 'bdt-acf-slider' === $type ) {
			$s['digitalisimo_acf_button_text'] = 'yes' === ( $s['show_button'] ?? 'yes' ) ? (string) ( $s['button_text'] ?? '' ) : '';
		}
		return $s;
	}

	/** CSS de los controles de estilo que sólo tenía el adaptador, con `selector` en lugar del envoltorio. */
	private static function legacy_style_css( array $element, array $settings, $post_id ) {
		$type  = (string) $element['widgetType'];
		$class = class_exists( __NAMESPACE__ . '\\Adapters' ) ? ( Adapters::CLASSES[ $type ] ?? '' ) : '';
		if ( ! $class || ! class_exists( $class ) || ! class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) || ! Translator::styles( $type ) ) {
			return '';
		}
		return self::with_style_controls( static function () use ( $class, $element, $type, $settings, $post_id ) {
		try {
			$widget   = new $class( array( 'id' => (string) ( $element['id'] ?? 'x' ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() ), array() );
			$controls = array_filter( (array) $widget->get_controls(), static function ( $control ) {
				return self::STYLE_SECTION === ( $control['section'] ?? '' );
			} );
			if ( ! $controls ) {
				return '';
			}
			$file     = new \Elementor\Core\Files\CSS\Post( (int) $post_id );
			// Como al generar el CSS de la página: sólo controles de estilo cuyas condiciones se cumplen.
			$controls = $file->get_style_controls( $widget, $controls, $widget->get_parsed_dynamic_settings() );
			$file->add_controls_stack_style_rules( $widget, $controls, (array) $widget->get_settings(), array( '{{ID}}', '{{WRAPPER}}' ), array( $widget->get_id(), 'selector' ) );
			return trim( (string) $file->get_stylesheet() );
		} catch ( \Throwable $e ) {
			self::$errors[] = $type . ': ' . $e->getMessage();
			return '';
		}
		} );
	}

	/** Controles del widget de destino, o null si no está registrado en este sitio. */
	private static function target_controls( $target ) {
		static $cache = array();
		if ( ! array_key_exists( $target, $cache ) ) {
			$widget           = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ? \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $target ) : null;
			$cache[ $target ] = $widget ? self::with_style_controls( static function () use ( $widget ) {
				return (array) $widget->get_controls();
			} ) : null;
		}
		return $cache[ $target ];
	}

	/**
	 * Copia de una plantilla como «Loop Item» para el Loop Grid y el Loop Carousel nativos, que sólo
	 * pintan plantillas de ese tipo. Se crea una vez por plantilla y sitio, y se reutiliza.
	 */
	public static function loop_item( $template_id ) {
		$template = get_post( $template_id );
		if ( ! $template || 'elementor_library' !== $template->post_type ) {
			return 0;
		}
		if ( 'loop-item' === get_post_meta( $template_id, '_elementor_template_type', true ) ) {
			return $template_id;
		}
		$existing = (int) get_post_meta( $template_id, '_digitalisimo_loop_item', true );
		if ( $existing && get_post( $existing ) ) {
			return $existing;
		}
		$copy = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $template->post_title . ' (Loop)' ) );
		if ( ! $copy || is_wp_error( $copy ) ) {
			return 0;
		}
		foreach ( array( '_elementor_data', '_elementor_page_settings', '_elementor_version' ) as $meta ) {
			$value = get_post_meta( $template_id, $meta, true );
			if ( '' !== $value && null !== $value ) {
				update_post_meta( $copy, $meta, is_string( $value ) ? wp_slash( $value ) : $value );
			}
		}
		update_post_meta( $copy, '_elementor_edit_mode', 'builder' );
		update_post_meta( $copy, '_elementor_template_type', 'loop-item' );
		update_post_meta( $copy, '_elementor_source', 'post' );
		wp_set_object_terms( $copy, 'loop-item', 'elementor_library_type' );
		update_post_meta( $template_id, '_digitalisimo_loop_item', $copy );
		return $copy;
	}

	/* ---------------------------------------------------------------- Cualquier elemento */

	private static function element_settings( array $element, $post_id ) {
		$s   = (array) $element['settings'];
		$css = array();

		// Visibilidad: las reglas se conservan siempre; sólo se aplican si Element Pack las aplicaba en este sitio.
		$s = self::apply_visibility( $s );

		// Figuras.
		if ( isset( $s['bdt_shape_builder_enable'] ) || isset( $s['bdt_shape_builder_list'] ) ) {
			$s['digitalisimo_figures_enable'] = (string) ( $s['bdt_shape_builder_enable'] ?? '' );
			$figures                          = array();
			foreach ( (array) ( $s['bdt_shape_builder_list'] ?? array() ) as $row ) {
				if ( is_array( $row ) ) {
					if ( 'epblob' === ( $row['shape_type'] ?? '' ) ) {
						$row['shape_type'] = 'blob';
					}
					$figures[] = $row;
				}
			}
			$s['digitalisimo_figures'] = $figures;
		}

		// Enlace del elemento.
		if ( ! empty( $s['element_pack_wrapper_link']['url'] ) ) {
			$s['digitalisimo_element_link'] = $s['element_pack_wrapper_link'];
		}

		// Efectos que sólo eran CSS: van al CSS personalizado del elemento.
		$effects = self::effects_css( $element, $post_id );
		if ( '' !== $effects ) {
			$css[] = $effects;
		}

		foreach ( array_keys( $s ) as $key ) {
			foreach ( self::ELEMENT_PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					unset( $s[ $key ] );
				}
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( isset( $s[ $bag ] ) && is_array( $s[ $bag ] ) ) {
				foreach ( array_keys( $s[ $bag ] ) as $key ) {
					foreach ( self::ELEMENT_PREFIXES as $prefix ) {
						if ( 0 === strpos( (string) $key, $prefix ) ) {
							unset( $s[ $bag ][ $key ] );
						}
					}
				}
				if ( ! $s[ $bag ] ) {
					unset( $s[ $bag ] );
				}
			}
		}
		if ( $css ) {
			$s['custom_css'] = trim( ( isset( $s['custom_css'] ) ? (string) $s['custom_css'] . "\n" : '' ) . implode( "\n", $css ) );
		}
		return $s;
	}

	/**
	 * Reglas de visibilidad de Element Pack en el formato propio (claves `digitalisimo_visibility_*`). Las reglas nunca
	 * se pierden: con la extensión apagada expresamente en el sitio quedan guardadas pero desactivadas, como en Element
	 * Pack, y el administrador puede encenderlas desde el editor. Sin reglas heredadas devuelve una lista vacía.
	 */
	public static function visibility_settings( array $s ) {
		if ( ! isset( $s['ep_display_conditions_enable'] ) && ! isset( $s['ep_display_conditions'] ) ) {
			return array();
		}
		$rules = array();
		foreach ( (array) ( $s['ep_display_conditions'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rule = array();
			foreach ( $row as $key => $value ) {
				$rule[ 0 === strpos( (string) $key, 'ep_condition_' ) ? 'rule_' . substr( (string) $key, 13 ) : $key ] = $value;
			}
			if ( 'country' === ( $rule['rule_key'] ?? '' ) ) {
				$rule['rule_country_value'] = self::country_codes( $rule['rule_country_value'] ?? array() );
			}
			$rules[] = $rule;
		}
		$enabled = 'yes' === ( $s['ep_display_conditions_enable'] ?? '' ) && self::visibility_enabled_on_site();
		return array(
			'digitalisimo_visibility_enable'   => $enabled ? 'yes' : '',
			'digitalisimo_visibility_to'       => (string) ( $s['ep_display_conditions_to'] ?? 'show' ),
			'digitalisimo_visibility_relation' => (string) ( $s['ep_display_conditions_relation'] ?? 'all' ),
			'digitalisimo_visibility_rules'    => $rules,
		);
	}

	/**
	 * Aplica las reglas heredadas convertidas sobre los ajustes del elemento. Unas reglas propias ya presentes (de una
	 * conversión o reparación anterior, o pegadas con «Pegar estilo») sólo se sustituyen si las heredadas estaban activas.
	 */
	public static function apply_visibility( array $s ) {
		$legacy = self::visibility_settings( $s );
		if ( ! $legacy ) {
			return $s;
		}
		if ( 'yes' === $legacy['digitalisimo_visibility_enable'] || ! self::has_own_rules( $s ) ) {
			return $legacy + $s;
		}
		return $s;
	}

	/** ¿El elemento ya tiene reglas propias (`digitalisimo_visibility_*`)? */
	public static function has_own_rules( array $s ) {
		foreach ( array_keys( $s ) as $key ) {
			if ( 0 === strpos( (string) $key, 'digitalisimo_visibility_' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Como Legacy_Rules::enabled_on_site(): Element Pack trae la extensión activa por defecto; sólo un «off» guardado la apaga. */
	private static function visibility_enabled_on_site() {
		if ( class_exists( '\\Digitalisimo\\Elements\\Conditions\\Legacy_Rules' ) ) {
			return \Digitalisimo\Elements\Conditions\Legacy_Rules::enabled_on_site();
		}
		$options = get_option( 'element_pack_elementor_extend', null );
		return ! is_array( $options ) || ! isset( $options['visibility-controls'] ) || 'off' !== $options['visibility-controls'];
	}

	/** Países guardados como nombre en inglés por versiones antiguas: pasan a su código ISO. */
	private static function country_codes( $values ) {
		static $names = null;
		if ( null === $names ) {
			$names = array();
			foreach ( array( __DIR__ . '/conditions/countries-legacy.php' ) as $file ) {
				if ( is_file( $file ) ) {
					$names = (array) require $file;
					break;
				}
			}
		}
		$out = array();
		foreach ( (array) $values as $value ) {
			$value = trim( (string) $value );
			$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
			$code  = preg_match( '/^[a-z]{2}$/', $value ) ? $value : (string) ( $names[ $value ] ?? '' );
			if ( '' !== $code ) {
				$out[] = $code;
			}
		}
		return $out;
	}

	/** CSS de los efectos de las extensiones (filtro de fondo, degradado, sombra, flotación, anotación). */
	private static function effects_css( array $element, $post_id ) {
		$s    = (array) $element['settings'];
		$type = (string) ( $element['elType'] ?? 'widget' );
		$css  = '';
		// Variables y reglas que salían de los controles: se calculan con los mismos controles registrados.
		if ( class_exists( __NAMESPACE__ . '\\Extensions' ) && class_exists( '\\Elementor\\Plugin' ) ) {
			$vars = self::extension_controls_css( $element, $post_id );
			if ( '' !== $vars ) {
				$css .= $vars . "\n";
			}
		}
		if ( 'yes' === ( $s['element_pack_backdrop_filter'] ?? '' ) ) {
			if ( 'liquid_glass' === ( $s['element_pack_backdrop_filter_type'] ?? 'backdrop_filter' ) ) {
				$css .= 'selector{overflow:hidden;-webkit-backdrop-filter:blur(var(--digi-liquid-glass-blur,1px));backdrop-filter:blur(var(--digi-liquid-glass-blur,1px))}' . "\n";
			} else {
				$target = array( 'widget' => 'selector > .elementor-widget-container', 'column' => 'selector > .elementor-element-populated' )[ $type ] ?? 'selector';
				$filter = 'blur(var(--digi-backdrop-blur,0px)) brightness(var(--digi-backdrop-brightness,100%)) contrast(var(--digi-backdrop-contrast,1)) grayscale(var(--digi-backdrop-grayscale,0)) invert(var(--digi-backdrop-invert,0)) opacity(var(--digi-backdrop-opacity,1)) sepia(var(--digi-backdrop-sepia,0)) saturate(var(--digi-backdrop-saturate,1)) hue-rotate(var(--digi-backdrop-hue-rotate,0deg))';
				$css   .= $target . '{-webkit-backdrop-filter:' . $filter . ';backdrop-filter:' . $filter . '}' . "\n";
			}
		}
		if ( 'yes' === ( $s['element_pack_tgb_enable'] ?? '' ) ) {
			$css .= 'selector .elementor-heading-title,selector .elementor-widget-container>:is(h1,h2,h3,h4,h5,h6,p){-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}' . "\n";
		}
		if ( 'yes' === ( $s['ep_floating_effects_show'] ?? '' ) ) {
			$css .= '@keyframes digi-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}selector{animation:digi-float 3s ease-in-out infinite}@media (prefers-reduced-motion:reduce){selector{animation:none}}' . "\n";
		}
		if ( 'yes' === ( $s['ep_notation_active'] ?? '' ) ) {
			$text  = 'selector :is(.elementor-heading-title,.elementor-widget-container>p)';
			$color = 'var(--digi-notation-color,currentColor)';
			$rules = array(
				'underline'      => '{text-decoration:underline;text-decoration-color:' . $color . ';text-decoration-thickness:.12em;text-underline-offset:.18em}',
				'strike-through' => '{text-decoration:line-through;text-decoration-color:' . $color . ';text-decoration-thickness:.1em}',
				'crossed-off'    => '{text-decoration:line-through;text-decoration-color:' . $color . ';text-decoration-thickness:.1em}',
				'highlight'      => '{background:linear-gradient(transparent 55%,var(--digi-notation-color,#ffe066) 55%)}',
				'box'            => '{display:inline-block;box-shadow:0 0 0 .12em ' . $color . ';padding:0 .2em}',
				'circle'         => '{display:inline-block;box-shadow:0 0 0 .12em ' . $color . ';padding:0 .2em;border-radius:50%}',
				'bracket'        => '{display:inline-block;border-inline:.12em solid ' . $color . ';padding:0 .25em}',
			);
			$kind  = (string) ( $s['ep_notation_type'] ?? 'underline' );
			$css  .= $text . ( $rules[ $kind ] ?? $rules['underline'] ) . "\n";
		}
		return trim( $css );
	}

	/** CSS que generaban los controles de las extensiones (variables y sombra), con nombres propios. */
	private static function extension_controls_css( array $element, $post_id ) {
		$s = (array) $element['settings'];
		$active = false;
		foreach ( Extensions::SWITCHES as $switch ) {
			$active = $active || 'yes' === ( $s[ $switch ] ?? '' );
		}
		if ( ! $active ) {
			return '';
		}
		$css = self::with_style_controls( static function () use ( $element, $post_id ) {
		try {
			$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
			if ( ! $instance ) {
				return '';
			}
			$controls = array_filter( (array) $instance->get_controls(), static function ( $control ) {
				return Extensions::SECTION === ( $control['section'] ?? '' ) && ! empty( $control['selectors'] ?? $control['selector'] ?? null );
			} );
			if ( ! $controls ) {
				return '';
			}
			$file     = new \Elementor\Core\Files\CSS\Post( (int) $post_id );
			$controls = $file->get_style_controls( $instance, $controls, $instance->get_parsed_dynamic_settings() );
			$file->add_controls_stack_style_rules( $instance, $controls, (array) $instance->get_settings(), array( '{{ID}}', '{{WRAPPER}}' ), array( $instance->get_id(), 'selector' ), $instance->get_controls() );
			return trim( (string) $file->get_stylesheet() );
		} catch ( \Throwable $e ) {
			self::$errors[] = (string) ( $element['elType'] ?? '' ) . ': ' . $e->getMessage();
			return '';
		}
		} );
		return strtr( $css, array(
			'--ep-backdrop-filter-hue-rotate' => '--digi-backdrop-hue-rotate',
			'--ep-backdrop-filter-'           => '--digi-backdrop-',
			'--ep-liquid-glass-effects-blur'  => '--digi-liquid-glass-blur',
			'--digi-legacy-notation-color'    => '--digi-notation-color',
		) );
	}
}
