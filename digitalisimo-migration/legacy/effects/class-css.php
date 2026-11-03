<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Extensiones que pasan al CSS personalizado del elemento (`custom_css`, con `selector`): background-overlay,
 * equal-height, scroll-fill-effect, scroll-box, threed-text, image-hover-effects y animated-gradient-background
 * (F8, §3.C.2). Todo valor interpolado pasa por `Css_Value` (V.7) y cada bloque por `Css_Value::block()`.
 *
 * El CSS de image-hover-effects reproduce `EP/assets/css/ep-image-hover-effects.css` de Element Pack (GPL; atribución
 * en `docs/third-party-licenses.md`) con los selectores del elemento y movimiento reducido.
 */
final class Css {
	/* ---------------------------------------------------------------- background-overlay */

	public static function overlay( Out $out ): bool {
		$s     = $out->orig;
		$image = false;
		$bg    = self::background( $s, 'ep_background_overlay', $image );
		$hover = self::background( $s, 'ep_background_overlay_hover', $image );
		if ( $image ) {
			$out->note( 'ext.review.overlay_image' );
		}
		$base = array( 'content:""', 'position:absolute', 'pointer-events:none' );
		$by   = array( '' => $base );
		$by   = $out->by_device( 'ep_background_overlay_margin', static function ( $v ) {
			$d = Css_Value::dimensions( $v );
			return null === $d ? null : 'inset:' . $d;
		}, $by );
		if ( ! preg_grep( '/^inset:/', $by[''] ) ) {
			$by[''][] = 'inset:0';
		}
		if ( $bg ) {
			$by['']   = array_merge( $by[''], $bg );
			$opacity  = Css_Value::number( $out->get( 'ep_background_overlay_opacity' ), 0, 1 );
			$by['']   = null !== $opacity ? array_merge( $by[''], array( 'opacity:' . $opacity ) ) : $by[''];
		}
		$filter = self::filters( $s, 'ep_css_filters' );
		if ( null !== $filter ) {
			$by[''][] = 'filter:' . $filter;
		}
		$blend = Css_Value::enum( Values::get( $s, 'ep_overlay_blend_mode', '' ), self::BLEND );
		if ( null !== $blend ) {
			$by[''][] = 'mix-blend-mode:' . $blend;
		}
		$by = $out->by_device( 'ep_background_overlay_radius', static function ( $v ) {
			$d = Css_Value::dimensions( $v, array( 'px', 'em', '%' ) );
			return null === $d ? null : 'border-radius:' . $d;
		}, $by );
		$z = Css_Value::number( Values::get( $s, 'ep_background_overlay_zindex', '' ), -9999, 9999 );
		if ( null !== $z ) {
			$by[''][] = 'z-index:' . (int) $z;
		}
		if ( Values::dynamic_ref( $s, 'ep_background_overlay_zindex' ) ) {
			$out->loss( __( 'índice z dinámico', 'digitalisimo-migration' ) );
		}
		$duration = Css_Value::number( $out->get( 'ep_background_overlay_hover_transition_duration' ), 0, 10 );
		$by['']   = array_merge( $by[''], array( 'transition:all ' . ( $duration ?? '0.3' ) . 's ease-in-out' ) );
		$css      = 'selector{position:relative}' . Out::responsive( 'selector::before', $by );
		$hover_by = array();
		if ( $hover ) {
			$hover_by[''] = $hover;
			$opacity      = Css_Value::number( $out->get( 'ep_background_overlay_hover_opacity' ), 0, 1 );
			if ( null !== $opacity ) {
				$hover_by[''][] = 'opacity:' . $opacity;
			}
		}
		$hover_filter = self::filters( $s, 'ep_css_filters_hover' );
		if ( null !== $hover_filter ) {
			$hover_by[''][] = 'filter:' . $hover_filter;
		}
		$hover_by = $out->by_device( 'ep_background_overlay_hover_radius', static function ( $v ) {
			$d = Css_Value::dimensions( $v, array( 'px', 'em', '%' ) );
			return null === $d ? null : 'border-radius:' . $d;
		}, $hover_by );
		$css .= Out::responsive( 'selector:hover::before', $hover_by );
		$out->css( $css );
		$out->loss( __( 'edición con controles (queda en el CSS personalizado)', 'digitalisimo-migration' ) );
		return true;
	}

	/** Modos de fusión del núcleo. */
	const BLEND = array( 'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'saturation', 'color', 'difference', 'exclusion', 'hue', 'luminosity' );

	/**
	 * Declaraciones del grupo Fondo del núcleo (clásico con color; degradado reconstruido). La imagen nunca se escribe
	 * (una URL absoluta en el CSS generado): `$image` lo dice.
	 */
	public static function background( array $s, string $prefix, bool &$image ): array {
		$type = Values::get( $s, $prefix . '_background', '' );
		if ( 'classic' === $type ) {
			$out   = array();
			$color = Values::color( $s, $prefix . '_color' );
			if ( null !== $color ) {
				$out[] = 'background-color:' . $color;
			}
			$img = Values::get( $s, $prefix . '_image', array() );
			if ( is_array( $img ) && '' !== trim( (string) ( $img['url'] ?? '' ) ) ) {
				$image = true;
			}
			return $out;
		}
		if ( 'gradient' === $type ) {
			$gradient = self::gradient_of( $s, $prefix );
			return null === $gradient ? array() : array( 'background-color:transparent', 'background-image:' . $gradient );
		}
		return array();
	}

	/** Degradado del grupo Fondo (colores, paradas, tipo, ángulo y posición), o null. */
	public static function gradient_of( array $s, string $prefix ): ?string {
		$a = Values::color( $s, $prefix . '_color' );
		$b = Values::color( $s, $prefix . '_color_b', '#f2295b' );
		if ( null === $a || null === $b ) {
			return null;
		}
		$stop_a = Values::get( $s, $prefix . '_color_stop', array( 'unit' => '%', 'size' => 0 ) );
		$stop_b = Values::get( $s, $prefix . '_color_b_stop', array( 'unit' => '%', 'size' => 100 ) );
		return Css_Value::gradient( array(
			'type'     => Values::get( $s, $prefix . '_gradient_type', 'linear' ),
			'angle'    => Values::get( $s, $prefix . '_gradient_angle', array( 'unit' => 'deg', 'size' => 180 ) ),
			'position' => Values::get( $s, $prefix . '_gradient_position', 'center center' ),
			'stops'    => array( array( $a, $stop_a ), array( $b, $stop_b ) ),
		) );
	}

	/** Filtros CSS del grupo del núcleo (`<prefijo>_css_filter = custom`). */
	private static function filters( array $s, string $prefix ): ?string {
		if ( 'custom' !== Values::get( $s, $prefix . '_css_filter', '' ) ) {
			return null;
		}
		$parts = array();
		foreach ( array( 'brightness' => array( '%', 100 ), 'contrast' => array( '%', 100 ), 'saturate' => array( '%', 100 ), 'blur' => array( 'px', 0 ), 'hue' => array( 'deg', 0 ) ) as $name => $spec ) {
			$value  = Values::size_of( $s, $prefix . '_' . $name, $spec[1] );
			$number = null === $value ? null : Css_Value::number( $value, -360, 1000 );
			if ( null !== $number ) {
				$parts[] = ( 'hue' === $name ? 'hue-rotate' : $name ) . '(' . $number . $spec[0] . ')';
			}
		}
		return $parts ? implode( ' ', $parts ) : null;
	}

	/* ---------------------------------------------------------------- equal-height */

	public static function equal_height( Out $out ): bool {
		$mode = (string) $out->get( 'section_equal_height_selector' );
		if ( 'column' === $mode ) {
			if ( 'container' === $out->el_type() ) {
				return $out->set_all( array( 'flex_align_items' => 'stretch' ) );
			}
			return true; // Las columnas de una sección ya se estiran (flex del núcleo).
		}
		if ( 'widgets' === $mode ) {
			$out->css(
				'selector :is(.e-con,.e-con-inner):has(> .elementor-widget:only-child) > .elementor-widget{flex-grow:1}'
				. 'selector .elementor-widget-wrap:has(> .elementor-widget:only-child){align-content:stretch}'
				. 'selector :is(.e-con,.e-con-inner,.elementor-widget-wrap) > .elementor-widget:only-child > .elementor-widget-container{height:100%}'
			);
			if ( self::many_widgets( (array) ( $out->element['elements'] ?? array() ) ) ) {
				$out->loss( __( 'igualar por filas cuando una columna tiene varios widgets', 'digitalisimo-migration' ) );
			}
			return true;
		}
		if ( 'custom' === $mode && '' === trim( (string) Values::get( $out->orig, 'section_equal_height_custom_selector', '' ) ) ) {
			$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'equal-height' ) ) );
			return false;
		}
		$out->loss( __( 'modos de primer, segundo y tercer hijo y selector propio', 'digitalisimo-migration' ) );
		return false;
	}

	/** ¿Alguna columna o contenedor hijo tiene más de un widget? */
	private static function many_widgets( array $children ): bool {
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || 'widget' === ( $child['elType'] ?? '' ) ) {
				continue;
			}
			$widgets = 0;
			foreach ( (array) ( $child['elements'] ?? array() ) as $grand ) {
				$widgets += is_array( $grand ) && 'widget' === ( $grand['elType'] ?? '' ) ? 1 : 0;
			}
			if ( $widgets > 1 ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------- scroll-fill-effect y threed-text */

	/** Selector del texto: título del núcleo, título avanzado propio o párrafos del editor de texto. */
	private static function text_target( Out $out ): string {
		$widget = $out->widget();
		if ( 'heading' === $widget ) {
			return 'selector .elementor-heading-title';
		}
		if ( 'digitalisimo-advanced-heading' === $widget ) {
			return 'selector .digi-advanced-heading__main';
		}
		$inner = Dispatcher::inner_wrapper( $widget );
		$tags  = ':is(h1,h2,h3,h4,h5,h6,p,div)';
		if ( true === $inner ) {
			return 'selector > .elementor-widget-container > ' . $tags;
		}
		if ( false === $inner ) {
			return 'selector > ' . $tags;
		}
		return ':is(selector > .elementor-widget-container > ' . $tags . ', selector > ' . $tags . ':not(.elementor-widget-container))';
	}

	public static function scroll_fill( Out $out ): bool {
		$s    = $out->orig;
		$type = (string) $out->get( 'ep_widget_sf_fx_animation_type' );
		$t    = self::text_target( $out );
		if ( 'scaling_gradient_text' === $type ) {
			$start = Values::size_of( $s, 'ep_widget_sf_fx_sg_start_scale', 2 ) ?? 2;
			$end   = Values::size_of( $s, 'ep_widget_sf_fx_sg_end_scale', 1 ) ?? 1;
			$o0    = Values::size_of( $s, 'ep_widget_sf_fx_sg_start_opacity', 0 ) ?? 0;
			$o1    = Values::size_of( $s, 'ep_widget_sf_fx_sg_end_opacity', 1 ) ?? 1;
			$set   = array(
				'motion_fx_motion_fx_scrolling' => 'yes',
				'motion_fx_scale_effect'        => 'yes',
				'motion_fx_scale_direction'     => $end < $start ? 'in-out' : 'out-in',
				'motion_fx_scale_speed'         => array( 'unit' => 'px', 'size' => max( 1, min( 10, round( abs( $end - $start ) * 4, 1 ) ) ) ),
				'motion_fx_scale_range'         => array( 'sizes' => array( 'start' => 0, 'end' => 50 ), 'unit' => '%' ),
			);
			if ( abs( $o1 - $o0 ) > 0.01 ) {
				$set['motion_fx_opacity_effect']    = 'yes';
				$set['motion_fx_opacity_direction'] = $o1 > $o0 ? 'out-in' : 'in-out';
				$set['motion_fx_opacity_level']     = array( 'unit' => 'px', 'size' => 10 );
				$set['motion_fx_opacity_range']     = array( 'sizes' => array( 'start' => 0, 'end' => 50 ), 'unit' => '%' );
			}
			if ( ! $out->set_all( $set ) ) {
				return false;
			}
			$out->loss( __( 'animación letra a letra y cambio de color', 'digitalisimo-migration' ) );
			return true;
		}
		if ( 'reveal_gradient_mask' === $type ) {
			$out->css(
				'@supports (animation-timeline: view()){@media (prefers-reduced-motion:no-preference){'
				. $t . '{-webkit-mask-image:linear-gradient(90deg,#000 45%,transparent 55%);mask-image:linear-gradient(90deg,#000 45%,transparent 55%);-webkit-mask-size:250% 100%;mask-size:250% 100%;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;animation:digi-sfx-mask linear both;animation-timeline:view();animation-range:entry 0% cover 50%}'
				. '}}@keyframes digi-sfx-mask{from{-webkit-mask-position:100% 0;mask-position:100% 0}to{-webkit-mask-position:0 0;mask-position:0 0}}'
			);
			$out->loss( __( 'máscara con el color de fondo y disparadores escritos a mano', 'digitalisimo-migration' ) );
			return true;
		}
		$base     = Values::color( $s, 'ep_widget_sf_fx_base_color', Dispatcher::default_of( 'scroll-fill-effect', 'ep_widget_sf_fx_base_color' ) );
		$gradient = 'gradient' === Values::get( $s, 'ep_widget_sf_fx_fill_bg_background', '' ) ? self::gradient_of( $s, 'ep_widget_sf_fx_fill_bg' ) : null;
		$decls    = array( 'display:inline' );
		if ( null !== $base ) {
			$decls[] = '-webkit-text-fill-color:' . $base;
		}
		if ( null === $gradient ) {
			$out->css( Out::rule( $t, $decls ) );
			return true;
		}
		$decls = array_merge( $decls, array( 'background-image:' . $gradient, 'background-repeat:no-repeat', 'background-size:100% 100%', '-webkit-background-clip:text', 'background-clip:text' ) );
		$out->css(
			Out::rule( $t, $decls )
			. '@supports (animation-timeline: view()){@media (prefers-reduced-motion:no-preference){' . $t . '{background-size:0% 100%;animation:digi-sfx-fill linear both;animation-timeline:view();animation-range:entry 0% cover 50%}}}'
			. '@keyframes digi-sfx-fill{to{background-size:100% 100%}}'
		);
		$out->loss( __( 'sin animation-timeline (Firefox), el texto se ve ya relleno', 'digitalisimo-migration' ) );
		return true;
	}

	public static function threed( Out $out ): bool {
		$s      = $out->orig;
		$depth  = $out->get( 'ep_threed_text_depth' );
		$unit   = is_array( $depth ) && in_array( $depth['unit'] ?? 'px', array( 'px', 'em', 'rem', '%' ), true ) ? ( $depth['unit'] ?? 'px' ) : 'px';
		$size   = is_array( $depth ) && is_numeric( $depth['size'] ?? null ) ? (float) $depth['size'] : 30.0;
		if ( '%' === $unit ) {
			// Sin referencia de tamaño: el porcentaje del texto se aproxima en em.
			$unit = 'em';
			$size = $size / 100;
		}
		$layers = (int) max( 1, min( 100, (int) ( Css_Value::number( $out->get( 'ep_threed_text_layers' ), 0, 100 ) ?? 8 ) ) );
		$color  = Values::color( $s, 'ep_threed_text_depth_color' ) ?? 'currentColor';
		$fade   = 'yes' === $out->get( 'ep_threed_text_fade' );
		$step   = $size / $layers;
		$shadow = array();
		for ( $i = 1; $i <= $layers; $i++ ) {
			$pct      = $fade ? (int) round( 100 - ( 90 * ( $i - 1 ) / max( 1, $layers ) ) ) : 100;
			$paint    = 100 === $pct ? $color : 'color-mix(in srgb,' . $color . ' ' . $pct . '%,transparent)';
			$shadow[] = '0 ' . Css_Value::format( $step * $i ) . $unit . ' 0 ' . $paint;
		}
		$selector = 'heading' === $out->widget() ? 'selector .elementor-heading-title' : 'selector .digi-advanced-heading__main';
		$out->css( Out::rule( $selector, array( 'text-shadow:' . implode( ',', $shadow ) ) ) );
		$event = (string) $out->get( 'ep_threed_text_event' );
		if ( 'pointer' === $event ) {
			$rotation = Values::size_of( $s, 'ep_threed_text_event_rotation', array( 'size' => 35 ) ) ?? 35;
			$set      = array(
				'motion_fx_motion_fx_mouse' => 'yes',
				'motion_fx_tilt_effect'     => 'yes',
				'motion_fx_tilt_direction'  => 'reverse' === $out->get( 'ep_threed_text_event_direction' ) ? 'negative' : '',
				'motion_fx_tilt_speed'      => array( 'unit' => 'px', 'size' => max( 0.1, min( 10, round( abs( $rotation ) / 10, 1 ) ) ) ),
			);
			if ( ! $out->set_all( $set ) ) {
				return false;
			}
		} elseif ( in_array( $event, array( 'scroll', 'scrollX', 'scrollY' ), true ) ) {
			$out->loss( __( 'giro al desplazar', 'digitalisimo-migration' ) );
		}
		$out->loss( __( '3D real con perspectiva (se aproxima con sombras de texto)', 'digitalisimo-migration' ) );
		return true;
	}

	/* ---------------------------------------------------------------- scroll-box */

	public static function scroll_box( Out $out ): bool {
		$s   = $out->orig;
		$box = $out->by_device( 'ep_scroll_box_max_height', static function ( $v ) {
			$size = Css_Value::size( $v, array( 'px', '%', 'em', 'rem', 'vh' ) );
			return null === $size ? null : 'max-height:' . $size;
		} );
		$box['']     = array_merge( (array) ( $box[''] ?? array() ), array( 'overflow-y:scroll!important', 'overflow-x:hidden!important' ) );
		$bar         = $out->by_device( 'ep_scroll_box_width', static function ( $v ) {
			$size = Css_Value::size( $v, array( 'px' ) );
			return null === $size ? null : 'width:' . $size;
		} );
		$radius      = static function ( $v ) {
			$size = Css_Value::size( $v, array( 'px', '%' ) );
			return null === $size ? null : 'border-radius:' . $size;
		};
		$bar         = $out->by_device( 'ep_scroll_box_border_radius', $radius, $bar );
		$thumb       = array( '' => array( 'background-color:#babac0', 'border-radius:16px' ) );
		$track       = array( '' => array( '-webkit-box-shadow:inset 0 0 2px rgba(0,0,0,.3)', 'border-radius:16px' ) );
		$image       = false;
		$thumb['']   = array_merge( $thumb[''], self::background( $s, 'ep_scroll_box_thumb_color', $image ) );
		$track['']   = array_merge( $track[''], self::background( $s, 'ep_scroll_box_track_color', $image ) );
		foreach ( array( 'thumb' => 'ep_scroll_box_thumb_shadow', 'track' => 'ep_scroll_box_track_shadow' ) as $part => $prefix ) {
			if ( '' !== (string) Values::get( $s, $prefix . '_box_shadow_type', '' ) ) {
				$shadow = Css_Value::shadow( Values::get( $s, $prefix . '_box_shadow', array() ), (string) Values::get( $s, $prefix . '_box_shadow_position', '' ) );
				if ( null !== $shadow ) {
					${$part}[''][] = 'box-shadow:' . $shadow;
				}
			}
		}
		$thumb = $out->by_device( 'ep_scroll_box_border_radius', $radius, $thumb );
		$track = $out->by_device( 'ep_scroll_box_border_radius', $radius, $track );
		$style = Css_Value::enum( Values::get( $s, 'ep_scroll_box_border_type', '' ), array( 'solid', 'double', 'dotted', 'dashed', 'groove' ) );
		if ( null !== $style ) {
			$track[''][] = 'border-style:' . $style;
			$track       = $out->by_device( 'ep_scroll_box_border_width', static function ( $v ) {
				$d = Css_Value::dimensions( $v );
				return null === $d ? null : 'border-width:' . $d;
			}, $track );
		}
		$border_color = Values::color( $s, 'ep_scroll_box_border_color' );
		if ( null !== $border_color ) {
			$track[''][] = 'border-color:' . $border_color;
		}
		// Firefox no tiene `::-webkit-scrollbar`: barra fina y sus colores si son simples.
		$thumb_color = 'classic' === Values::get( $s, 'ep_scroll_box_thumb_color_background', '' ) ? ( Values::color( $s, 'ep_scroll_box_thumb_color_color' ) ?? '#babac0' ) : '#babac0';
		$track_color = 'classic' === Values::get( $s, 'ep_scroll_box_track_color_background', '' ) ? ( Values::color( $s, 'ep_scroll_box_track_color_color' ) ?? 'transparent' ) : 'transparent';
		$width       = Values::size_of( $s, 'ep_scroll_box_width', 12 ) ?? 12;
		$box['']     = array_merge( $box[''], array( 'scrollbar-width:' . ( $width <= 8 ? 'thin' : 'auto' ), 'scrollbar-color:' . $thumb_color . ' ' . $track_color ) );
		// Donde existe `::-webkit-scrollbar`, `scrollbar-color` lo anularía (Chromium 121+): sólo para los demás.
		$out->css(
			Out::responsive( 'selector', $box )
			. '@supports selector(::-webkit-scrollbar){selector{scrollbar-width:auto;scrollbar-color:auto}}'
			. Out::responsive( 'selector::-webkit-scrollbar', $bar )
			. Out::responsive( 'selector::-webkit-scrollbar-thumb', $thumb )
			. Out::responsive( 'selector::-webkit-scrollbar-track', $track )
		);
		return true;
	}

	/* ---------------------------------------------------------------- image-hover-effects */

	/** CSS de Element Pack por efecto, con `W` en lugar de la clase de la extensión (GPL; ver docs/third-party-licenses.md). */
	const HOVER_BASE = 'W img{max-inline-size:100%;block-size:auto;object-fit:cover;transition:all .5s ease}W{overflow:hidden;position:relative}W::before,W::after{position:absolute;content:"";transition:all .5s ease}';

	const HOVER = array(
		'1' => 'W::before,W::after{z-index:3}W::before{inset-block-start:50%;inset-inline-start:0;inset-inline-end:0;inset-block-end:50%}W:hover img{transform:scale(1.1)}W::after{inset-block-start:0;inset-inline-start:50%;inset-inline-end:50%;inset-block-end:0}W:hover::before{inset-block-start:0;inset-block-end:0}W:hover::after{inset-inline-start:0;inset-inline-end:0}',
		'2' => 'W::after{inset-inline-start:50%;opacity:1;padding-block-start:100%;inset-block-start:50%;transform:translate(-50%, -50%) rotate(45deg) scale(0);inline-size:100%}W:hover::after{opacity:0;transform:translate(-50%, -50%) rotate(45deg) scale(1)}W:hover img{transform:scale(1.1)}',
		'3' => 'W::before,W::after{inset-block-end:20px;inset-inline-start:20px;opacity:0;inset-inline-end:20px;inset-block-start:20px;z-index:1;background:unset !important}W::before{transform:scaleX(0);border-block-end:2px solid;border-block-start:2px solid}W:hover img{transform:scale(1.1)}W::after{transform:scaleY(0);border-inline-start:2px solid;border-inline-end:2px solid}W:hover::before,W:hover::after{opacity:1;transform:scale(1)}',
		'4' => 'W::before{inset-block-start:0;inset-inline-start:50%;inline-size:120%;block-size:100px;transition:all .6s linear;z-index:1;transform:scale3d(1.9, 1.4, 1) rotate3d(0, 0, 1, 45deg) translate(0, -150%);backface-visibility:hidden;pointer-events:none}W:hover img{transform:scale(1.1)}W:hover::before{transform:scale3d(1.9, 1.4, 1) rotate3d(0, 0, 1, 45deg) translate(0, 200%);inset-inline-start:-100%;inset-block-start:100%}',
		'5' => 'W::after{inset-block-start:30px;inset-inline-end:50px;inset-block-end:30px;inset-inline-start:50px;border-inline-end:2px solid;border-inline-start:2px solid;transform:scale(1, 0);transform-origin:100% 0;opacity:0;z-index:1}W:hover img{transform:scale(1.1)}W::before{inset-block-start:50px;inset-inline-end:30px;inset-block-end:50px;inset-inline-start:30px;border-block-start:2px solid;border-block-end:2px solid;opacity:0;transform:scale(0, 1);transform-origin:0 0;z-index:1}W::after,W::before{background:unset !important}W:hover::after,W:hover::before{opacity:1;transform:scale(1)}',
		'6' => 'W::after{inset-block-start:30px;inset-inline-end:30px;inset-block-end:30px;inset-inline-start:30px;border:1px solid;opacity:0;transform:scale(0);background:unset !important}W:hover img{transform:scale(1.1)}W:hover::after{opacity:1;transform:scale(1)}',
	);

	public static function image_hover( Out $out ): bool {
		$s      = $out->orig;
		$effect = Css_Value::enum( (string) $out->get( 'hover_effects' ), array_keys( self::HOVER ) ) ?? '1';
		$css    = str_replace( 'W', 'selector', self::HOVER_BASE . self::HOVER[ $effect ] );
		$decls  = array();
		$color  = Values::color( $s, 'effects_color', Dispatcher::default_of( 'image-hover-effects', 'effects_color' ) );
		if ( null !== $color ) {
			$decls[] = 'background:' . $color;
			$decls[] = 'border-color:' . $color;
		}
		if ( in_array( $effect, array( '3', '5', '6' ), true ) ) {
			$width = Css_Value::size( Values::get( $s, 'effets_width', '' ), array( 'px', '%' ) );
			if ( null !== $width ) {
				$decls[] = 'border-width:' . $width;
			}
		}
		$duration = Css_Value::size( Values::get( $s, 'effects_duration', '' ), array( 'ms' ), 'ms', 0, 60000 );
		if ( null !== $duration ) {
			$decls[] = 'transition-duration:' . $duration;
		}
		$css .= Out::rule( 'selector::before,selector::after', $decls );
		$css .= '@media (prefers-reduced-motion:reduce){selector img,selector::before,selector::after{transition:none}}';
		$out->css( $css );
		return true;
	}

	/* ---------------------------------------------------------------- animated-gradient-background */

	public static function gradient( Out $out ): bool {
		$s      = $out->orig;
		$rows   = Values::get( $s, 'element_pack_agbg_color_list', Dispatcher::default_of( 'animated-gradient-background', 'element_pack_agbg_color_list' ) );
		$colors = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			foreach ( array( 'start_color', 'end_color' ) as $key ) {
				$color = is_array( $row ) ? Css_Value::color( $row[ $key ] ?? '' ) : null;
				if ( null !== $color ) {
					$colors[] = $color;
				}
			}
		}
		$pairs = max( 1, is_array( $rows ) ? count( $rows ) : 1 );
		if ( count( $colors ) < 2 ) {
			$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'animated-gradient-background' ) ) );
			return false;
		}
		$direction = (string) $out->get( 'element_pack_agbg_direction' );
		$stops     = implode( ',', $colors );
		$image     = 'radial' === $direction ? 'radial-gradient(circle,' . $stops . ')' : 'linear-gradient(' . ( array( 'left-right' => '90deg', 'top-bottom' => '180deg' )[ $direction ] ?? '135deg' ) . ',' . $stops . ')';
		$speed     = Values::size_of( $s, 'element_pack_agbg_transitionSpeed', array( 'size' => 5500 ) ) ?? 5500;
		$duration  = (int) max( 100, min( 600000, round( $speed * $pairs ) ) );
		$decls     = array( 'content:""', 'position:absolute', 'inset:0', 'z-index:-1', 'pointer-events:none', 'background-image:' . $image, 'background-size:400% 400%', 'animation:digi-agbg ' . $duration . 'ms ease-in-out infinite alternate' );
		// El modo de fusión sólo se aplicaba con un fondo del núcleo en el elemento (condición del control).
		if ( '' !== (string) ( $s['background_background'] ?? '' ) ) {
			$blend = Css_Value::enum( $out->get( 'element_pack_agbg_blending_mode' ), self::BLEND );
			if ( null !== $blend && 'normal' !== $blend ) {
				$decls[] = 'mix-blend-mode:' . $blend;
			}
		}
		// `::after`: `::before` es la superposición del contenedor. Con `isolation` la capa -1 queda sobre el fondo del
		// propio elemento y bajo su contenido, como el lienzo de Element Pack (su primer hijo).
		$out->css(
			'selector{position:relative;overflow:hidden;isolation:isolate}'
			. Out::rule( 'selector::after', $decls )
			. '@keyframes digi-agbg{0%{background-position:0% 50%}100%{background-position:100% 50%}}'
			. '@media (prefers-reduced-motion:reduce){selector::after{animation:none}}'
		);
		$out->loss( __( 'interpolación de color entre pares y pausa fuera de pantalla', 'digitalisimo-migration' ) );
		return true;
	}
}
