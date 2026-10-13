<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-masks.php';

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
	 * Convierte un elemento y sus hijos.
	 *
	 * @return array{0:array,1:int} elemento convertido y número de cambios.
	 */
	public static function element( array $element, $post_id ) {
		$changes  = 0;
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		if ( 'widget' === ( $element['elType'] ?? '' ) && Translator::is_legacy_id( (string) ( $element['widgetType'] ?? '' ) ) && Translator::map( (string) $element['widgetType'] ) ) {
			// Los ajustes de elemento (visibilidad, figuras, enlace, efectos) no pasan por el traductor del widget.
			$bag = array();
			foreach ( $settings as $key => $value ) {
				if ( preg_match( self::ELEMENT_KEYS, (string) $key ) ) {
					$bag[ $key ] = $value;
					unset( $settings[ $key ] );
				}
			}
			$element['settings'] = $settings;
			$element             = self::widget( $element, $post_id );
			if ( $bag ) {
				$element['settings'] = self::element_settings( array( 'settings' => $element['settings'] + $bag ) + $element, $post_id );
			}
			++$changes;
		} elseif ( self::has_legacy_element_settings( $settings ) ) {
			$element['settings'] = self::element_settings( $element, $post_id );
			++$changes;
		}
		// Máscaras y separadores con figuras de Element Pack, en cualquier elemento (también en los ya convertidos).
		if ( Masks::is_legacy( $element ) ) {
			$element['settings'] = Masks::convert( $element, self::$errors );
			$changes            += $changes ? 0 : 1;
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $index => $child ) {
				if ( is_array( $child ) ) {
					list( $element['elements'][ $index ], $child_changes ) = self::element( $child, $post_id );
					$changes += $child_changes;
				}
			}
		}
		return array( $element, $changes );
	}

	/** ¿El documento (o el elemento) contiene algo heredado? */
	public static function is_legacy( array $element ) {
		if ( 'widget' === ( $element['elType'] ?? '' ) && Translator::is_legacy_id( (string) ( $element['widgetType'] ?? '' ) ) ) {
			return true;
		}
		if ( self::has_legacy_element_settings( (array) ( $element['settings'] ?? array() ) ) || Masks::is_legacy( $element ) ) {
			return true;
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) && self::is_legacy( $child ) ) {
				return true;
			}
		}
		return false;
	}

	private static function has_legacy_element_settings( array $settings ) {
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
				$out[ $key ] = $value;
			}
		}
		if ( '' !== $css ) {
			$out['custom_css'] = trim( ( isset( $out['custom_css'] ) ? (string) $out['custom_css'] . "\n" : '' ) . $css );
		}
		$element['widgetType'] = $target;
		$element['settings']   = $out;
		return $element;
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
			$widget           = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $target ) : null;
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

		// Visibilidad: sólo si Element Pack la aplicaba en este sitio.
		if ( isset( $s['ep_display_conditions_enable'] ) || isset( $s['ep_display_conditions'] ) ) {
			if ( 'yes' === ( $s['ep_display_conditions_enable'] ?? '' ) && self::visibility_enabled_on_site() ) {
				$s['digitalisimo_visibility_enable']   = 'yes';
				$s['digitalisimo_visibility_to']       = (string) ( $s['ep_display_conditions_to'] ?? 'show' );
				$s['digitalisimo_visibility_relation'] = (string) ( $s['ep_display_conditions_relation'] ?? 'all' );
				$rules                                  = array();
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
				$s['digitalisimo_visibility_rules'] = $rules;
			}
		}

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

	private static function visibility_enabled_on_site() {
		$options = get_option( 'element_pack_elementor_extend', null );
		return ! is_array( $options ) || ( isset( $options['visibility-controls'] ) && 'on' === $options['visibility-controls'] );
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
			$value = strtolower( trim( (string) $value ) );
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
