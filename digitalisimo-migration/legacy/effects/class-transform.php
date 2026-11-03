<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * transform-effects → Transformar del núcleo (F8, §3.C.2).
 *
 * Widget: cada transformación de Element Pack (normal y al pasar el puntero) pasa a `_transform_*` del núcleo
 * (`element-base.php:870-1400`): traslación, rotación Z (y X/Y con `_transform_rotate_3d` y perspectiva de 1000 px),
 * escala (una sola si X = Y) y sesgo; la duración a `_transform_transition_hover` (300 ms si no se guardó, no los
 * 0,4 s del núcleo); el retardo (sólo al pasar el puntero, como en `ep-helper.css`) y la curva a `custom_css` sobre el
 * contenedor interno del widget, según el marcado optimizado.
 *
 * Sección y columna con «hover del padre» (`wrapper`): sus widgets descendientes con la extensión toman sus valores
 * de hover cuando el puntero está sobre el padre (`custom_css` del padre con las variables `--e-transform-*`). Element
 * Pack forzaba además `overflow` con `!important`: `hidden` pasa a `overflow` (sección) o a `custom_css` (columna),
 * y con `visible` se vacía el `overflow` nativo `hidden` de la sección, que no se veía.
 */
final class Transform {
	/** Partes de Element Pack: interruptor del popover, clave de Elements y variable del núcleo. */
	const PARTS = array(
		'translate' => array( 'toggle' => 'element_pack_translate_toggle_', 'popover' => '_transform_translate_popover', 'keys' => array( 'transx' => 'translateX', 'transy' => 'translateY' ), 'unit' => 'px' ),
		'rotate'    => array( 'toggle' => 'element_pack_rotate_toggle_', 'popover' => '_transform_rotate_popover', 'keys' => array( 'rotatez' => 'rotateZ', 'rotatex' => 'rotateX', 'rotatey' => 'rotateY' ), 'unit' => 'deg' ),
		'scale'     => array( 'toggle' => 'element_pack_scale_', 'popover' => '_transform_scale_popover', 'keys' => array( 'scalex' => 'scaleX', 'scaley' => 'scaleY' ), 'unit' => '' ),
		'skew'      => array( 'toggle' => 'element_pack_skew_', 'popover' => '_transform_skew_popover', 'keys' => array( 'skewx' => 'skewX', 'skewy' => 'skewY' ), 'unit' => 'deg' ),
	);

	public static function convert( Out $out ): bool {
		$type = $out->el_type();
		if ( 'widget' === $type ) {
			return 'yes' === Values::get( $out->orig, 'element_pack_widget_transform' ) ? self::widget( $out ) : false;
		}
		if ( in_array( $type, array( 'section', 'column' ), true ) ) {
			return self::parent( $out, $type );
		}
		return false;
	}

	/** Ajustes del núcleo para un widget, o null si choca algo. */
	private static function widget( Out $out ): bool {
		$set = array();
		foreach ( array( '' => 'normal', '_hover' => 'hover' ) as $tab => $state ) {
			foreach ( self::values( $out->orig, $state ) as $part => $axes ) {
				$set[ self::PARTS[ $part ]['popover'] . $tab ] = 'transform';
				foreach ( $axes as $device => $values ) {
					if ( 'scale' === $part ) {
						$x = $values['scaleX'] ?? 1.0;
						$y = $values['scaleY'] ?? 1.0;
						if ( abs( $x - $y ) < 0.0001 ) {
							$set[ Values::device_key( '_transform_scale_effect' . $tab, $device ) ] = array( 'unit' => 'px', 'size' => $x );
						} else {
							$set[ '_transform_keep_proportions' . $tab ] = '';
							$set[ Values::device_key( '_transform_scaleX_effect' . $tab, $device ) ] = array( 'unit' => 'px', 'size' => $x );
							$set[ Values::device_key( '_transform_scaleY_effect' . $tab, $device ) ] = array( 'unit' => 'px', 'size' => $y );
						}
						continue;
					}
					foreach ( $values as $axis => $number ) {
						$set[ Values::device_key( '_transform_' . $axis . '_effect' . $tab, $device ) ] = array( 'unit' => self::PARTS[ $part ]['unit'], 'size' => $number );
						if ( in_array( $axis, array( 'rotateX', 'rotateY' ), true ) ) {
							$set[ '_transform_rotate_3d' . $tab ]          = 'yes';
							$set[ '_transform_perspective_effect' . $tab ] = array( 'unit' => 'px', 'size' => 1000 );
						}
					}
				}
			}
		}
		$duration = Css_Value::number( Values::get( $out->orig, 'element_pack_widget_effect_transition_duration', '300' ), 0, 10000 );
		$set['_transform_transition_hover'] = array( 'unit' => 'ms', 'size' => null !== $duration ? (float) $duration : 300.0 );
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		$delay  = Css_Value::number( Values::get( $out->orig, 'element_pack_widget_effect_transition_delay', '' ), 0, 60000 );
		$easing = Values::get( $out->orig, 'element_pack_widget_effect_transition_easing', '' );
		if ( '' !== (string) $easing && 'ease-out' !== $easing ) {
			$curve = Css_Value::easing( $easing );
			if ( null !== $curve ) {
				$out->css( Out::rule( $out->inner(), array( 'transition-timing-function:' . $curve ) ) );
			}
		}
		if ( null !== $delay && '0' !== $delay ) {
			$out->css( Out::rule( $out->inner( ':hover' ), array( 'transition-delay:' . $delay . 'ms' ) ) );
		}
		$out->loss( __( 'orden de composición de las funciones de transformación', 'digitalisimo-migration' ) );
		if ( isset( $set['_transform_rotate_3d'] ) || isset( $set['_transform_rotate_3d_hover'] ) ) {
			$out->loss( __( 'perspectiva 3D (se aproxima con 1000 px)', 'digitalisimo-migration' ) );
		}
		return true;
	}

	/**
	 * Valores de un estado (`normal`|`hover`): parte => dispositivo => eje => número. Sólo las partes con su popover
	 * encendido y algún valor válido.
	 */
	public static function values( array $s, string $state ): array {
		$out = array();
		foreach ( self::PARTS as $part => $spec ) {
			if ( 'yes' !== Values::get( $s, $spec['toggle'] . $state ) ) {
				continue;
			}
			foreach ( $spec['keys'] as $ep => $axis ) {
				$key = 'element_pack_widget_effect_' . $ep . '_' . $state;
				foreach ( Values::responsive( $s, $key ) as $device => $value ) {
					$number = Css_Value::number( is_array( $value ) ? ( $value['size'] ?? null ) : $value, 'scale' === $part ? 0 : -1000, 'scale' === $part ? 10 : 1000 );
					if ( null !== $number ) {
						$out[ $part ][ $device ][ $axis ] = (float) $number;
					}
				}
			}
		}
		return $out;
	}

	/** Sección o columna: hover del padre y desbordamiento. */
	private static function parent( Out $out, string $type ): bool {
		$switch = 'section' === $type ? 'element_pack_section_transform' : 'element_pack_column_transform';
		if ( 'wrapper' !== Values::get( $out->orig, $switch ) ) {
			return false;
		}
		$overflow = Values::get( $out->orig, 'section' === $type ? 'element_pack_section_overflow' : 'element_pack_column_overflow', 'visible' );
		if ( 'hidden' === $overflow ) {
			if ( 'section' === $type ) {
				if ( ! $out->set_all( array( 'overflow' => 'hidden' ) ) ) {
					return false;
				}
			} else {
				$out->css( 'selector{overflow:hidden}' );
			}
		} elseif ( 'section' === $type && 'hidden' === ( $out->s['overflow'] ?? '' ) ) {
			// Element Pack forzaba `overflow: visible !important` sobre el nativo: se conserva lo que se veía.
			$out->s['overflow'] = '';
		}
		$rules = '';
		foreach ( self::descendants( (array) ( $out->element['elements'] ?? array() ) ) as $child ) {
			$rules .= self::hover_rule( $child );
		}
		if ( '' !== $rules ) {
			$out->css( $rules );
		}
		return true;
	}

	/** Widgets descendientes con la extensión encendida. */
	private static function descendants( array $elements ): array {
		$out = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$s = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			if ( 'widget' === ( $element['elType'] ?? '' ) && 'yes' === Values::get( $s, 'element_pack_widget_transform' ) ) {
				$out[] = $element;
			}
			$out = array_merge( $out, self::descendants( (array) ( $element['elements'] ?? array() ) ) );
		}
		return $out;
	}

	/** `selector:hover .elementor-element-<hijo> > contenedor{--e-transform-…}` con los valores de hover del hijo. */
	private static function hover_rule( array $child ): string {
		$id = (string) ( $child['id'] ?? '' );
		if ( ! preg_match( '/^[a-zA-Z0-9]{1,20}$/', $id ) ) {
			return '';
		}
		$s       = is_array( $child['settings'] ?? null ) ? $child['settings'] : array();
		$by      = array();
		$wrapper = Dispatcher::inner_wrapper( Dispatcher::effective_widget( (string) ( $child['widgetType'] ?? '' ) ) );
		foreach ( self::values( $s, 'hover' ) as $part => $devices ) {
			foreach ( $devices as $device => $axes ) {
				if ( 'scale' === $part ) {
					$x = $axes['scaleX'] ?? 1.0;
					$y = $axes['scaleY'] ?? 1.0;
					$by[ $device ][] = '--e-transform-scaleX:' . Css_Value::format( $x );
					$by[ $device ][] = '--e-transform-scaleY:' . Css_Value::format( $y );
					continue;
				}
				foreach ( $axes as $axis => $number ) {
					$by[ $device ][] = '--e-transform-' . $axis . ':' . Css_Value::format( $number ) . self::PARTS[ $part ]['unit'];
					if ( in_array( $axis, array( 'rotateX', 'rotateY' ), true ) ) {
						$by[ $device ][] = '--e-transform-perspective:1000px';
					}
				}
			}
		}
		if ( ! $by ) {
			return '';
		}
		$base = 'selector:hover .elementor-element-' . $id;
		if ( true === $wrapper ) {
			$selector = $base . ' > .elementor-widget-container';
		} elseif ( false === $wrapper ) {
			$selector = $base;
		} else {
			$selector = ':is(' . $base . ' > .elementor-widget-container, ' . $base . ':not(:has(> .elementor-widget-container)))';
		}
		foreach ( $by as $device => $decls ) {
			$by[ $device ] = array_values( array_unique( $decls ) );
		}
		return Out::responsive( $selector, $by );
	}
}
