<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * tooltip, global-badge y threed-text → rasgos «Información emergente» (`digitalisimo_tooltip*`), «Distintivo»
 * (`digitalisimo_badge*`) y «Relieve 3D» (`digitalisimo_relief*`) de DIGITALÍSIMO Elements (F8, §3.C.2; claves fijas en
 * V.14 C10 y C11, y en §19 las del relieve).
 *
 * Los estilos pasan 1:1 a los controles del rasgo, cada valor validado por tipo (un color de Element Pack con
 * `red;}…` no llega a ningún control): grupos Fondo, Borde, Sombra de caja, Sombra de texto y Tipografía, medidas y
 * deslizadores. Las referencias globales y dinámicas se trasladan a la clave de destino.
 */
final class Traits {
	/** Subclaves de cada grupo del núcleo y su validación. */
	const GROUPS = array(
		'background' => array( 'background' => 'enum:classic,gradient', 'color' => 'color', 'color_stop' => 'slider', 'color_b' => 'color', 'color_b_stop' => 'slider', 'gradient_type' => 'enum:linear,radial', 'gradient_angle' => 'slider', 'gradient_position' => 'enum:center center,center left,center right,top center,top left,top right,bottom center,bottom left,bottom right', 'image' => 'media', 'position' => 'enum:,center center,center left,center right,top center,top left,top right,bottom center,bottom left,bottom right,initial', 'xpos' => 'slider', 'ypos' => 'slider', 'attachment' => 'enum:,scroll,fixed', 'repeat' => 'enum:,no-repeat,repeat,repeat-x,repeat-y', 'size' => 'enum:,auto,cover,contain,initial', 'bg_width' => 'slider' ),
		'border'     => array( 'border' => 'enum:,none,solid,double,dotted,dashed,groove', 'width' => 'dimensions', 'color' => 'color' ),
		'box_shadow' => array( 'box_shadow_type' => 'enum:yes', 'box_shadow' => 'shadow', 'box_shadow_position' => 'enum:,inset' ),
		'text_shadow' => array( 'text_shadow_type' => 'enum:yes', 'text_shadow' => 'shadow' ),
		'typography' => array( 'typography' => 'enum:custom', 'font_family' => 'font', 'font_size' => 'slider', 'font_weight' => 'enum:,100,200,300,400,500,600,700,800,900,normal,bold', 'text_transform' => 'enum:,uppercase,lowercase,capitalize,none', 'font_style' => 'enum:,normal,italic,oblique', 'text_decoration' => 'enum:,underline,overline,line-through,none', 'line_height' => 'slider', 'letter_spacing' => 'slider', 'word_spacing' => 'slider' ),
	);

	/**
	 * Aspecto del globo de tippy.js 6 que Element Pack pintaba sin estilo guardado (tippy.all.min.js: `.tippy-box`,
	 * `.tippy-content`, `.tippy-arrow` y las opciones `maxWidth` y `offset`).
	 */
	const TIPPY = array(
		'background'  => '#333333',
		'color'       => '#ffffff',
		'arrow'       => '#333333',
		'padding'     => array( 'top' => '5', 'right' => '9', 'bottom' => '5', 'left' => '9', 'unit' => 'px', 'isLinked' => false ),
		'font_size'   => 14,
		'line_height' => 1.4,
		'max_width'   => 350,
		'offset'      => 10,
		// Element Pack restaba 10 px al ancho guardado: `max-width: calc({{SIZE}}{{UNIT}} - 10px) !important`.
		'width_minus' => 10,
	);

	/* ---------------------------------------------------------------- Información emergente */

	public static function tooltip( Out $out ): bool {
		$s   = $out->orig;
		$set = array( 'digitalisimo_tooltip' => 'yes' );
		$raw = Values::get( $s, 'element_pack_widget_tooltip_text', null );
		$raw = null === $raw ? Values::ep_text( 'This is Tooltip' ) : (string) $raw;
		$text = self::tooltip_text( $raw );
		$norm = static function ( string $t ): string {
			return trim( (string) preg_replace( '#<br\s*/?>#i', '<br>', $t ) );
		};
		if ( $norm( $text ) !== $norm( $raw ) ) {
			$out->loss( __( 'HTML fuera de strong, em, b, i y br (los enlaces se quitan)', 'digitalisimo-migration' ) );
		}
		$set['digitalisimo_tooltip_text'] = $text;
		$placement = (string) Values::get( $s, 'element_pack_widget_tooltip_placement', '' );
		$side      = explode( '-', $placement )[0];
		if ( in_array( $side, array( 'right', 'bottom', 'left' ), true ) ) {
			$set['digitalisimo_tooltip_placement'] = $side;
		}
		if ( false !== strpos( $placement, '-' ) || 0 === strpos( $placement, 'auto' ) ) {
			$out->loss( __( 'alineación inicio/fin/automática del globo', 'digitalisimo-migration' ) );
		}
		$trigger = (string) Values::get( $s, 'element_pack_widget_tooltip_trigger', '' );
		if ( 'click' === $trigger ) {
			$set['digitalisimo_tooltip_trigger'] = 'click';
		} elseif ( 'manual' === $trigger || '' !== trim( (string) Values::get( $s, 'element_pack_widget_tooltip_custom_trigger', '' ) ) ) {
			$out->loss( __( 'disparador por selector', 'digitalisimo-migration' ) );
		}
		$arrow = 'yes' === Values::get( $s, 'element_pack_widget_tooltip_arrow' );
		if ( $arrow ) {
			$set['digitalisimo_tooltip_arrow'] = 'yes';
		}
		// tippy.js recibía `offset: [x, y]` = [desplazamiento lateral, distancia] sólo si alguno de los dos tenía un tamaño
		// distinto de 0 (ep-tooltip.js: `(x.size || y.size) && (offset = [x || 0, y || 0])`); si no, su distancia por
		// defecto, 10 px. La distancia es la Y.
		$x = Values::size_of( $s, 'element_pack_widget_tooltip_x_offset' );
		$y = Values::size_of( $s, 'element_pack_widget_tooltip_y_offset' );
		if ( ( null !== $x && 0.0 !== $x ) || ( null !== $y && 0.0 !== $y ) ) {
			$distance = Css_Value::number( (float) $y, -1000, 1000 );
			if ( null !== $distance ) {
				$set['digitalisimo_tooltip_offset'] = array( 'unit' => 'px', 'size' => (float) $distance );
			}
			if ( null !== $x && abs( $x ) >= 0.5 ) {
				$out->loss( __( 'desplazamiento lateral del globo', 'digitalisimo-migration' ) );
			}
		} else {
			$set['digitalisimo_tooltip_offset'] = array( 'unit' => 'px', 'size' => (float) self::TIPPY['offset'] );
		}
		$set += self::colors( $s, array( 'element_pack_widget_tooltip_color' => 'digitalisimo_tooltip_color', 'element_pack_widget_tooltip_arrow_color' => 'digitalisimo_tooltip_arrow_color' ) );
		$set += self::dims( $s, 'element_pack_widget_tooltip_padding', 'digitalisimo_tooltip_padding' );
		$set += self::dims( $s, 'element_pack_widget_tooltip_border_radius', 'digitalisimo_tooltip_radius' );
		$align = Css_Value::enum( $out->get( 'element_pack_widget_tooltip_text_align' ), array( 'left', 'center', 'right', 'justify' ) );
		if ( null !== $align ) {
			$set['digitalisimo_tooltip_align'] = $align;
		}
		$set += self::group( $s, 'element_pack_widget_tooltip_background', 'digitalisimo_tooltip_bg', 'background' );
		$set += self::group( $s, 'element_pack_widget_tooltip_border', 'digitalisimo_tooltip_border', 'border' );
		$set += self::group( $s, 'element_pack_widget_tooltip_box_shadow', 'digitalisimo_tooltip_shadow', 'box_shadow' );
		$set += self::group( $s, 'element_pack_widget_tooltip_typography', 'digitalisimo_tooltip_typography', 'typography' );
		$carry = array(
			'element_pack_widget_tooltip_text'                      => 'digitalisimo_tooltip_text',
			'element_pack_widget_tooltip_color'                     => 'digitalisimo_tooltip_color',
			'element_pack_widget_tooltip_arrow_color'               => 'digitalisimo_tooltip_arrow_color',
			'element_pack_widget_tooltip_background_color'          => 'digitalisimo_tooltip_bg_color',
			'element_pack_widget_tooltip_background_color_b'        => 'digitalisimo_tooltip_bg_color_b',
			'element_pack_widget_tooltip_border_color'              => 'digitalisimo_tooltip_border_color',
			'element_pack_widget_tooltip_typography_typography'     => 'digitalisimo_tooltip_typography_typography',
		);
		// Aspecto por defecto de tippy.js donde Element Pack no guardó otro (como el distintivo con ep-global-badge.css):
		// sin él, el globo tomaría el aspecto propio del rasgo (colores del sistema, 256 px, 8 px de distancia).
		self::tooltip_look( $s, $set, $arrow, $carry );
		// El ancho va después de la tipografía: en em depende del tamaño de letra del globo.
		$set += self::tooltip_width( $out, $s, $set );
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		self::carry_all( $out, $carry );
		if ( 'yes' === Values::get( $s, 'element_pack_widget_tooltip_follow_cursor' ) ) {
			$out->loss( __( 'seguir el cursor', 'digitalisimo-migration' ) );
		}
		$animation = (string) Values::get( $s, 'element_pack_widget_tooltip_animation', '' );
		if ( '' !== $animation && 'fade' !== $animation ) {
			$out->loss( __( 'animaciones que no son fundido', 'digitalisimo-migration' ) );
		}
		return true;
	}

	/**
	 * Completa `$set` con el aspecto de tippy.js donde Element Pack no guardó otro (o lo que guardó no es válido): fondo,
	 * color del texto y de la flecha, relleno y tipografía (14 px con interlineado 1,4). Nunca pisa un valor convertido ni
	 * una referencia global. Sin tipo de fondo, el núcleo no imprimía su color ni su global: el globo conservaba el #333
	 * de tippy, así que esas claves no se trasladan (`$carry` pierde las del fondo).
	 */
	private static function tooltip_look( array $s, array &$set, bool $arrow, array &$carry ): void {
		$type = (string) ( $set['digitalisimo_tooltip_bg_background'] ?? '' );
		if ( 'classic' !== $type && 'gradient' !== $type ) {
			foreach ( array_keys( $set ) as $key ) {
				if ( 0 === strpos( (string) $key, 'digitalisimo_tooltip_bg_' ) ) {
					unset( $set[ $key ] );
				}
			}
			$set['digitalisimo_tooltip_bg_background'] = 'classic';
			$set['digitalisimo_tooltip_bg_color']      = self::TIPPY['background'];
			unset( $carry['element_pack_widget_tooltip_background_color'], $carry['element_pack_widget_tooltip_background_color_b'] );
		} elseif ( 'classic' === $type && ! isset( $set['digitalisimo_tooltip_bg_color'] ) && ! Values::global_ref( $s, 'element_pack_widget_tooltip_background_color' ) ) {
			// Fondo clásico sin color (sólo una imagen): debajo seguía el #333 de tippy.
			$set['digitalisimo_tooltip_bg_color'] = self::TIPPY['background'];
		}
		if ( ! isset( $set['digitalisimo_tooltip_color'] ) && ! Values::global_ref( $s, 'element_pack_widget_tooltip_color' ) ) {
			$set['digitalisimo_tooltip_color'] = self::TIPPY['color'];
		}
		// La flecha de tippy es #333 aunque cambie el fondo (`.tippy-arrow{color:#333}`); la del rasgo toma el fondo.
		if ( $arrow && ! isset( $set['digitalisimo_tooltip_arrow_color'] ) && ! Values::global_ref( $s, 'element_pack_widget_tooltip_arrow_color' ) ) {
			$set['digitalisimo_tooltip_arrow_color'] = self::TIPPY['arrow'];
		}
		$padding = $set['digitalisimo_tooltip_padding'] ?? null;
		if ( ! is_array( $padding ) || '' === implode( '', array( $padding['top'] ?? '', $padding['right'] ?? '', $padding['bottom'] ?? '', $padding['left'] ?? '' ) ) ) {
			$set['digitalisimo_tooltip_padding'] = self::TIPPY['padding'];
		}
		// Una tipografía global manda entera, como en el distintivo; si no, sólo se completan las claves que faltan.
		if ( ! Values::global_ref( $s, 'element_pack_widget_tooltip_typography_typography' ) ) {
			$set += array(
				'digitalisimo_tooltip_typography_typography'  => 'custom',
				'digitalisimo_tooltip_typography_font_size'   => array( 'unit' => 'px', 'size' => (float) self::TIPPY['font_size'] ),
				'digitalisimo_tooltip_typography_line_height' => array( 'unit' => 'em', 'size' => (float) self::TIPPY['line_height'] ),
			);
		}
	}

	/**
	 * Ancho máximo del globo en cada dispositivo. Element Pack pintaba `calc(ANCHO - 10px)`: en px se restan 10; en em,
	 * 10 px en em del tamaño de letra del globo en ese dispositivo (si no es en px, se toma el de tippy, 14 px, y se
	 * declara la aproximación). Sin ancho de escritorio, el `maxWidth` de tippy.js (350 px).
	 *
	 * @param array $set Lo ya calculado del rasgo (con la tipografía de destino).
	 */
	private static function tooltip_width( Out $out, array $s, array $set ): array {
		$width  = array();
		$approx = false;
		foreach ( Values::responsive( $s, 'element_pack_widget_tooltip_width' ) as $device => $value ) {
			$clean = self::slider( $value, array( 'px', 'em' ) );
			if ( null === $clean ) {
				continue;
			}
			if ( 'px' === $clean['unit'] ) {
				$size = $clean['size'] - self::TIPPY['width_minus'];
			} else {
				$font = self::tooltip_font_px( $s, $set, $device );
				if ( null === $font ) {
					$font   = (float) self::TIPPY['font_size'];
					$approx = true;
				}
				$size = $clean['size'] - self::TIPPY['width_minus'] / $font;
			}
			$width[ Values::device_key( 'digitalisimo_tooltip_width', $device ) ] = array( 'unit' => $clean['unit'], 'size' => round( max( 0.0, $size ), 4 ) );
		}
		if ( ! isset( $width['digitalisimo_tooltip_width'] ) ) {
			$width['digitalisimo_tooltip_width'] = array( 'unit' => 'px', 'size' => (float) self::TIPPY['max_width'] );
		}
		if ( $approx ) {
			$out->loss( __( 'ancho máximo en em aproximado (el tamaño de letra del globo no es en px)', 'digitalisimo-migration' ) );
		}
		return $width;
	}

	/**
	 * Tamaño de letra en px del globo convertido en un dispositivo (heredado del más ancho, como en el núcleo), o null si
	 * no se conoce (tipografía global o en otra unidad).
	 */
	private static function tooltip_font_px( array $s, array $set, string $device ): ?float {
		if ( Values::global_ref( $s, 'element_pack_widget_tooltip_typography_typography' ) ) {
			return null;
		}
		$breakpoints = Values::breakpoints();
		$chain       = array( $device );
		if ( '' !== $device && 'max' === ( $breakpoints[ $device ][1] ?? 'max' ) ) {
			// Un dispositivo «max» hereda del más ancho que tenga valor (del más cercano al escritorio).
			$wider = array();
			foreach ( $breakpoints as $name => $spec ) {
				if ( $name === $device ) {
					break;
				}
				if ( 'max' === $spec[1] ) {
					$wider[] = $name;
				}
			}
			$chain = array_merge( $chain, array_reverse( $wider ) );
		}
		$chain[] = '';
		foreach ( array_unique( $chain ) as $name ) {
			$size = $set[ Values::device_key( 'digitalisimo_tooltip_typography_font_size', $name ) ] ?? null;
			if ( is_array( $size ) ) {
				return 'px' === ( $size['unit'] ?? '' ) && (float) ( $size['size'] ?? 0 ) > 0 ? (float) $size['size'] : null;
			}
		}
		return null;
	}

	/** Texto del globo con el HTML que admite el rasgo (`strong`, `em`, `b`, `i`, `br`), sin atributos. */
	public static function tooltip_text( string $text ): string {
		$allowed = array( 'strong' => array(), 'em' => array(), 'b' => array(), 'i' => array(), 'br' => array() );
		if ( function_exists( 'wp_kses' ) ) {
			return (string) wp_kses( $text, $allowed );
		}
		$text = strip_tags( $text, '<strong><em><b><i><br>' );
		return (string) preg_replace( '#<(/?)(strong|em|b|i|br)\b[^>]*>#i', '<$1$2>', $text );
	}

	/* ---------------------------------------------------------------- Distintivo */

	public static function badge( Out $out ): bool {
		$s   = $out->orig;
		$set = array( 'digitalisimo_badge' => 'yes' );
		$text = Values::get( $s, 'ep_global_badge_text', null );
		$set['digitalisimo_badge_text'] = null === $text ? Values::ep_text( 'New' ) : wp_strip_all_tags_text( (string) $text );
		// Esquina física de Element Pack → esquina lógica del rasgo (en RTL, la derecha es el inicio).
		$h   = 'left' === $out->get( 'ep_global_badge_horizontal' ) ? 'left' : 'right';
		$v   = 'bottom' === $out->get( 'ep_global_badge_vertical' ) ? 'bottom' : 'top';
		$rtl = function_exists( 'is_rtl' ) && is_rtl();
		$end = ( 'right' === $h ) !== $rtl;
		$corner = $v . '-' . ( $end ? 'end' : 'start' );
		if ( 'top-end' !== $corner ) {
			$set['digitalisimo_badge_corner'] = $corner;
		}
		// Element Pack movía el distintivo con `translate(x, y)` desde su esquina (`margin: 0`, ep-global-badge.css);
		// el rasgo lo separa de la esquina hacia dentro: a la derecha o abajo, el signo cambia.
		foreach ( array( 'x' => 'right' === $h, 'y' => 'bottom' === $v ) as $axis => $flip ) {
			foreach ( Values::responsive( $s, 'ep_global_badge_offset_' . $axis ) as $device => $value ) {
				$size = Css_Value::size( $value, array( 'px', '%', 'em' ), 'px', -10000, 10000 );
				if ( null === $size || ! preg_match( '/^(-?[0-9.]+)([a-z%]+)$/', $size, $m ) ) {
					continue;
				}
				$set[ Values::device_key( 'digitalisimo_badge_offset_' . $axis, $device ) ] = array( 'unit' => $m[2], 'size' => $flip ? -1 * (float) $m[1] : (float) $m[1] );
			}
		}
		foreach ( Values::responsive( $s, 'ep_global_badge_rotate', Dispatcher::default_of( 'global-badge', 'ep_global_badge_rotate' ) ) as $device => $value ) {
			$deg = Css_Value::number( is_array( $value ) ? ( $value['size'] ?? null ) : $value, -360, 360 );
			if ( null !== $deg ) {
				$set[ Values::device_key( 'digitalisimo_badge_rotate', $device ) ] = array( 'unit' => 'deg', 'size' => (float) $deg );
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_global_badge_overflow_hidden' ) ) {
			$set['digitalisimo_badge_overflow'] = 'yes';
		}
		if ( 'custom-layout' === $out->get( 'ep_global_badge_style' ) && 'yes' === Values::get( $s, 'ep_global_badge_clip_path' ) ) {
			$clip = Css_Value::clip( Values::get( $s, 'ep_global_badge_clip_path_value', 'polygon(0% 0%, 100% 1%, 88% 49%, 100% 100%, 0% 100%)' ) );
			if ( null !== $clip ) {
				$set['digitalisimo_badge_shape'] = 'custom';
				$set['digitalisimo_badge_clip']  = $clip;
			} else {
				$out->loss( __( 'recorte con una forma que el distintivo no admite', 'digitalisimo-migration' ) );
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_global_badge_icon' ) ) {
			if ( 'lottie' === $out->get( 'ep_global_badge_icon_type' ) ) {
				$out->loss( __( 'icono animado (Lottie): el distintivo queda sin icono', 'digitalisimo-migration' ) );
			} else {
				$icon = self::icon( $out->get( 'ep_global_badge_icon_selected' ) );
				if ( null !== $icon ) {
					$set['digitalisimo_badge_icon']          = $icon;
					$set['digitalisimo_badge_icon_position'] = 'right' === $out->get( 'ep_global_badge_icon_position' ) ? 'end' : 'start';
				}
				foreach ( array( 'ep_global_badge_icon_color', 'ep_global_badge_icon_size', 'ep_global_badge_icon_gap' ) as $key ) {
					if ( array_key_exists( $key, $s ) ) {
						$out->loss( __( 'color, tamaño y separación del icono', 'digitalisimo-migration' ) );
						break;
					}
				}
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_global_badge_svg_layer' ) && '' !== trim( (string) Values::get( $s, 'ep_global_badge_svg_code', '' ) ) ) {
			$out->loss( __( 'capa SVG en código', 'digitalisimo-migration' ) );
		}
		if ( 'yes' === Values::get( $s, 'ep_global_badge_floating' ) ) {
			if ( 'yes' === Values::get( $s, 'ep_global_badge_floating_translate_toggle' ) ) {
				$set['digitalisimo_badge_float'] = 'yes';
			}
			foreach ( array( 'rotate', 'opacity', 'blur' ) as $kind ) {
				if ( 'yes' === Values::get( $s, 'ep_global_badge_floating_' . $kind . '_toggle' ) ) {
					$out->loss( __( 'flotación con giro, opacidad o desenfoque', 'digitalisimo-migration' ) );
					break;
				}
			}
		}
		// Estilo por defecto de Element Pack (ep-global-badge.css) donde no se guardó otro: fondo azul, texto blanco en
		// mayúsculas de 12 px, relleno 8/16 px y ancho mínimo de 100 px (`ep_global_badge_size`).
		$bg = self::group( $s, 'ep_global_badge_background', 'digitalisimo_badge_bg', 'background' );
		$set += $bg ? $bg : array( 'digitalisimo_badge_bg_background' => 'classic', 'digitalisimo_badge_bg_color' => '#1e87f0' );
		$color = Values::color( $s, 'ep_global_badge_text_color' );
		$set['digitalisimo_badge_color'] = null !== $color && 0 !== strpos( $color, 'var(' ) ? $color : ( null === $color ? '#ffffff' : '' );
		if ( '' === $set['digitalisimo_badge_color'] ) {
			unset( $set['digitalisimo_badge_color'] );
		}
		$padding = self::dims( $s, 'ep_global_badge_padding', 'digitalisimo_badge_padding' );
		$set    += $padding ? $padding : array( 'digitalisimo_badge_padding' => array( 'top' => '8', 'right' => '16', 'bottom' => '8', 'left' => '16', 'unit' => 'px', 'isLinked' => false ) );
		$set    += self::dims( $s, 'ep_global_badge_border_radius', 'digitalisimo_badge_radius' );
		$min     = self::sliders( $s, 'ep_global_badge_size', 'digitalisimo_badge_min_width', array( 'px' ) );
		$set    += $min ? $min : array( 'digitalisimo_badge_min_width' => array( 'unit' => 'px', 'size' => 100 ) );
		$set    += self::group( $s, 'ep_global_badge_border', 'digitalisimo_badge_border', 'border' );
		$set    += self::group( $s, 'ep_global_badge_box_shadow', 'digitalisimo_badge_shadow', 'box_shadow' );
		$set    += self::group( $s, 'ep_global_badge_text_shadow', 'digitalisimo_badge_text_shadow', 'text_shadow' );
		$type    = self::group( $s, 'ep_global_badge_typography', 'digitalisimo_badge_typography', 'typography' );
		if ( ! Values::global_ref( $s, 'ep_global_badge_typography_typography' ) ) {
			$type += array(
				'digitalisimo_badge_typography_typography'     => 'custom',
				'digitalisimo_badge_typography_font_size'      => array( 'unit' => 'px', 'size' => 12 ),
				'digitalisimo_badge_typography_font_weight'    => '700',
				'digitalisimo_badge_typography_line_height'    => array( 'unit' => 'em', 'size' => 1.2 ),
				'digitalisimo_badge_typography_text_transform' => 'uppercase',
				'digitalisimo_badge_typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.04 ),
			);
		}
		$set += $type;
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		self::carry_all( $out, array(
			'ep_global_badge_text'                  => 'digitalisimo_badge_text',
			'ep_global_badge_text_color'            => 'digitalisimo_badge_color',
			'ep_global_badge_background_color'      => 'digitalisimo_badge_bg_color',
			'ep_global_badge_background_color_b'    => 'digitalisimo_badge_bg_color_b',
			'ep_global_badge_border_color'          => 'digitalisimo_badge_border_color',
			'ep_global_badge_typography_typography' => 'digitalisimo_badge_typography_typography',
		) );
		return true;
	}

	/** Icono de la biblioteca (`{value, library}`) con nombres de clase y biblioteca válidos; los SVG subidos, por ID. */
	private static function icon( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$library = (string) ( $value['library'] ?? '' );
		if ( 'svg' === $library && is_array( $value['value'] ?? null ) ) {
			$id  = (int) ( $value['value']['id'] ?? 0 );
			$url = (string) ( $value['value']['url'] ?? '' );
			return $id > 0 && preg_match( '#^(https?:)?//|^/#', $url ) ? array( 'value' => array( 'id' => $id, 'url' => $url ), 'library' => 'svg' ) : null;
		}
		$class = (string) ( $value['value'] ?? '' );
		return preg_match( '/^[a-z0-9_ -]{1,80}$/i', $class ) && preg_match( '/^[a-z0-9_-]{1,40}$/i', $library ) ? array( 'value' => $class, 'library' => $library ) : null;
	}

	/* ---------------------------------------------------------------- Relieve 3D */

	/** Unidades de la profundidad del rasgo «Relieve 3D» y su tope (`Text_Relief::DEPTH_UNITS` de Elements). */
	const RELIEF_DEPTH = array(
		'px'  => 1000,
		'em'  => 100,
		'rem' => 100,
	);

	/** Evento de Element Pack (ztext.js) → giro del rasgo. */
	const RELIEF_EVENTS = array(
		'pointer' => 'pointer',
		'scroll'  => 'scroll',
		'scrollX' => 'scroll-x',
		'scrollY' => 'scroll-y',
	);

	/**
	 * threed-text → rasgo «Relieve 3D» (`digitalisimo_relief*`) de DIGITALÍSIMO Elements, con la geometría de ztext.js que
	 * pintaba Element Pack: N capas (la del texto incluida) desplazadas `profundidad/2 − i·profundidad/N` en Z, la
	 * perspectiva en el título y, con el desvanecido, la copia i a `(1 − i/N)/2`. Los valores son los que el gestor de
	 * Element Pack (ep-threed-text.js) pasaba a ztext:
	 *
	 * - Profundidad, capas y perspectiva sólo con un tamaño distinto de 0; si no, 30 px, 8 capas y 500 px.
	 * - Una profundidad en % rompía ztext (sólo lee unidades de letras) y el título se veía plano: no se enciende.
	 * - El color de la profundidad sólo pintaba las copias (su global se traslada); sin él, el del texto.
	 * - El giro (en grados) y su sentido, sólo con un evento; un giro sin número no giraba.
	 * - Sólo en el Encabezado del núcleo: el script buscaba `.elementor-heading-title` o `.bdt-main-heading-inner`, y el
	 *   Encabezado avanzado de Element Pack 10 no pinta ninguno de los dos, así que allí el título se veía plano.
	 *
	 * El rasgo no gira con el movimiento reducido del sistema (Element Pack sí giraba).
	 */
	public static function threed( Out $out ): bool {
		$s = $out->orig;
		if ( 'heading' !== (string) ( $out->element['widgetType'] ?? '' ) ) {
			$out->loss( __( 'en el Encabezado avanzado, Element Pack 10 no pintaba el texto en 3D (su script no encontraba el título): queda plano, como se veía', 'digitalisimo-migration' ) );
			return true;
		}
		$depth = $out->get( 'ep_threed_text_depth' );
		$size  = is_array( $depth ) ? Css_Value::number( $depth['size'] ?? null ) : null;
		$size  = null === $size ? 0.0 : (float) $size;
		$unit  = 'px';
		if ( 0.0 === $size ) {
			$size = 30.0;
		} else {
			$unit = is_array( $depth ) && isset( $depth['unit'] ) && is_scalar( $depth['unit'] ) && '' !== (string) $depth['unit'] ? (string) $depth['unit'] : 'px';
			if ( ! isset( self::RELIEF_DEPTH[ $unit ] ) ) {
				$out->loss( __( 'profundidad en %, que el texto en 3D de Element Pack no pintaba: el título queda plano, como se veía', 'digitalisimo-migration' ) );
				return true;
			}
			if ( $size < 0 ) {
				$out->loss( __( 'profundidad negativa (las copias iban delante del texto): se usa su valor absoluto', 'digitalisimo-migration' ) );
				$size = abs( $size );
			}
			$size = min( $size, (float) self::RELIEF_DEPTH[ $unit ] );
		}
		$layers      = Css_Value::number( $out->get( 'ep_threed_text_layers' ) );
		$layers      = null === $layers || (float) $layers <= 0 ? 8 : (int) min( 100, ceil( (float) $layers ) );
		$perspective = $out->get( 'ep_threed_text_perspective' );
		$perspective = Css_Value::number( is_array( $perspective ) ? ( $perspective['size'] ?? null ) : $perspective );
		$set         = array(
			'digitalisimo_relief'             => 'yes',
			'digitalisimo_relief_depth'       => array( 'unit' => $unit, 'size' => round( $size, 4 ) ),
			'digitalisimo_relief_layers'      => $layers,
			'digitalisimo_relief_perspective' => array( 'unit' => 'px', 'size' => null === $perspective || (float) $perspective <= 0 ? 500.0 : (float) $perspective ),
		);
		if ( 'yes' === $out->get( 'ep_threed_text_fade' ) ) {
			$set['digitalisimo_relief_fade'] = 'yes';
		}
		$set  += self::colors( $s, array( 'ep_threed_text_depth_color' => 'digitalisimo_relief_color' ) );
		$event = $out->get( 'ep_threed_text_event' );
		if ( is_string( $event ) && isset( self::RELIEF_EVENTS[ $event ] ) ) {
			$rotation = $out->get( 'ep_threed_text_event_rotation' );
			$rotation = Css_Value::number( is_array( $rotation ) ? ( $rotation['size'] ?? null ) : $rotation );
			if ( null !== $rotation ) {
				$set['digitalisimo_relief_event']    = self::RELIEF_EVENTS[ $event ];
				$set['digitalisimo_relief_rotation'] = array( 'unit' => 'deg', 'size' => max( -360.0, min( 360.0, (float) $rotation ) ) );
				if ( 'reverse' === $out->get( 'ep_threed_text_event_direction' ) ) {
					$set['digitalisimo_relief_direction'] = 'reverse';
				}
			}
		}
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		self::carry_all( $out, array( 'ep_threed_text_depth_color' => 'digitalisimo_relief_color' ) );
		if ( ! self::relief_ready() ) {
			// Las claves quedan guardadas y se encienden solas al actualizar Elements; hasta entonces, el informe lo dice.
			$out->loss( __( 'este sitio tiene un DIGITALÍSIMO Elements sin el rasgo «Relieve 3D»: el título se ve plano hasta actualizarlo (la conversión ya queda guardada)', 'digitalisimo-migration' ) );
		}
		return true;
	}

	/** @var bool|null Pruebas: fuerza la detección del rasgo «Relieve 3D» (null: se detecta). */
	public static $relief = null;

	/**
	 * ¿Tiene el DIGITALÍSIMO Elements cargado el rasgo «Relieve 3D» (F8 §19)? Sin Elements cargado (las pruebas unitarias)
	 * se da por hecho, como `Runner::requirements()`.
	 */
	private static function relief_ready(): bool {
		if ( null !== self::$relief ) {
			return (bool) self::$relief;
		}
		return ! defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) || class_exists( '\\Digitalisimo\\Elements\\Text_Relief', false );
	}

	/* ---------------------------------------------------------------- Copia validada */

	/** Colores validados (sin valor con un global: lo lleva `carry`). */
	private static function colors( array $s, array $map ): array {
		$out = array();
		foreach ( $map as $from => $to ) {
			if ( array_key_exists( $from, $s ) ) {
				$color = Css_Value::color( Values::unwrap( $s[ $from ] ) );
				if ( null !== $color ) {
					$out[ $to ] = $color;
				}
			}
		}
		return $out;
	}

	/** Deslizador responsivo validado. */
	private static function sliders( array $s, string $from, string $to, array $units ): array {
		$out = array();
		foreach ( Values::responsive( $s, $from ) as $device => $value ) {
			$clean = self::slider( $value, $units );
			if ( null !== $clean ) {
				$out[ Values::device_key( $to, $device ) ] = $clean;
			}
		}
		return $out;
	}

	/** Medidas (DIMENSIONS) responsivas validadas. */
	private static function dims( array $s, string $from, string $to ): array {
		$out = array();
		foreach ( Values::responsive( $s, $from ) as $device => $value ) {
			$clean = self::dimension( $value );
			if ( null !== $clean ) {
				$out[ Values::device_key( $to, $device ) ] = $clean;
			}
		}
		return $out;
	}

	/** Copia validada de un grupo del núcleo de `$from_*` a `$to_*` (también sus variantes responsivas). */
	public static function group( array $s, string $from, string $to, string $kind ): array {
		$out  = array();
		$subs = self::GROUPS[ $kind ] ?? array();
		foreach ( $s as $key => $value ) {
			$key = (string) $key;
			if ( 0 !== strpos( $key, $from . '_' ) ) {
				continue;
			}
			$rest   = substr( $key, strlen( $from ) + 1 );
			$device = '';
			if ( preg_match( '/^(.+)_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', $rest, $m ) && isset( $subs[ $m[1] ] ) ) {
				$rest   = $m[1];
				$device = $m[2];
			}
			if ( ! isset( $subs[ $rest ] ) ) {
				continue;
			}
			$clean = self::valid( Values::unwrap( $value ), $subs[ $rest ] );
			if ( null !== $clean ) {
				$out[ Values::device_key( $to . '_' . $rest, $device ) ] = $clean;
			}
		}
		return $out;
	}

	/** Valor validado según su tipo de subclave, o null. */
	private static function valid( $value, string $rule ) {
		if ( 0 === strpos( $rule, 'enum:' ) ) {
			$allowed = explode( ',', substr( $rule, 5 ) );
			return is_scalar( $value ) && in_array( (string) $value, $allowed, true ) && '' !== (string) $value ? (string) $value : null;
		}
		switch ( $rule ) {
			case 'color':
				return Css_Value::color( $value );
			case 'slider':
				return self::slider( $value, array( 'px', 'em', 'rem', '%', 'vw', 'vh', 'deg' ) );
			case 'dimensions':
				return self::dimension( $value );
			case 'shadow':
				if ( ! is_array( $value ) ) {
					return null;
				}
				$clean = array();
				foreach ( array( 'horizontal', 'vertical', 'blur', 'spread' ) as $part ) {
					if ( isset( $value[ $part ] ) ) {
						if ( null === Css_Value::number( $value[ $part ], -1000, 1000 ) ) {
							return null;
						}
						$clean[ $part ] = (float) $value[ $part ];
					}
				}
				$color = Css_Value::color( $value['color'] ?? '' );
				if ( null === $color ) {
					return null;
				}
				$clean['color'] = $color;
				return $clean;
			case 'font':
				return Css_Value::font_family( $value );
			case 'media':
				$url = is_array( $value ) ? (string) ( $value['url'] ?? '' ) : '';
				return '' !== $url && preg_match( '#^((https?:)?//|/)[^\s"\'()<>\\\\]+$#', $url ) ? array( 'id' => (int) ( $value['id'] ?? 0 ), 'url' => $url ) : null;
		}
		return null;
	}

	/** Deslizador `{unit, size}` con número y unidad de la lista (sin unidad «custom»). */
	private static function slider( $value, array $units ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$unit = isset( $value['unit'] ) && is_scalar( $value['unit'] ) && '' !== (string) $value['unit'] ? (string) $value['unit'] : 'px';
		$size = $value['size'] ?? null;
		if ( ! in_array( $unit, $units, true ) || null === Css_Value::number( $size ) ) {
			return null;
		}
		return array( 'unit' => $unit, 'size' => (float) $size );
	}

	/** Medidas `{top, right, bottom, left, unit, isLinked}` con números y unidad válida. */
	private static function dimension( $value ): ?array {
		if ( ! is_array( $value ) || null === Css_Value::dimensions( $value ) ) {
			return null;
		}
		$clean = array( 'unit' => (string) ( $value['unit'] ?? 'px' ) );
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$clean[ $side ] = isset( $value[ $side ] ) && '' !== $value[ $side ] ? (string) (float) $value[ $side ] : '';
		}
		$clean['isLinked'] = ! empty( $value['isLinked'] );
		return $clean;
	}

	/** Traslada las referencias globales y dinámicas. */
	private static function carry_all( Out $out, array $map ): void {
		foreach ( $map as $from => $to ) {
			$out->carry( $from, $to );
		}
	}
}

/** Texto plano (wp_strip_all_tags si existe). */
function wp_strip_all_tags_text( string $text ): string {
	return function_exists( 'wp_strip_all_tags' ) ? (string) wp_strip_all_tags( $text ) : strip_tags( $text );
}
