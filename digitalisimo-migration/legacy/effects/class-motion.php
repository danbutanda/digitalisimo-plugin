<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * Movimiento: parallax-effects, background-parallax e image-parallax a los efectos de movimiento del núcleo
 * (`ELE/modules/motion-fx/`), y reveal-effects a la animación de entrada (`_animation`) (F8, §3.C.2).
 */
final class Motion {
	/** Media de UIkit (`@s`, `@m`, `@l`, `@xl`) → ancho mínimo. */
	const MEDIA = array( '@s' => 640, '@m' => 960, '@l' => 1200, '@xl' => 1600 );

	/** Recorrido completo de la pantalla, como el de UIkit de entrar a salir. */
	const FULL_RANGE = array( 'sizes' => array( 'start' => 0, 'end' => 100 ), 'unit' => '%' );

	/* ---------------------------------------------------------------- parallax-effects */

	public static function parallax( Out $out ): bool {
		if ( 'column' === $out->el_type() ) {
			// Element Pack registraba los controles en columnas, pero ni encolaba ni ejecutaba el efecto en ellas.
			$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'parallax-effects' ) ) );
			return false;
		}
		$s   = $out->orig;
		$set = array( 'motion_fx_motion_fx_scrolling' => 'yes' );
		$any = false;
		$off = array();
		foreach ( array( 'y' => 'translateY', 'x' => 'translateX' ) as $axis => $effect ) {
			if ( 'yes' !== Values::get( $s, 'ep_parallax_effects_' . $axis ) ) {
				continue;
			}
			$pair = self::pair( $out, $axis );
			if ( null === $pair ) {
				continue;
			}
			list( $a, $b ) = $pair;
			$set[ 'motion_fx_' . $effect . '_effect' ]        = 'yes';
			$set[ 'motion_fx_' . $effect . '_speed' ]         = array( 'unit' => 'px', 'size' => self::clamp( abs( $b - $a ) / 100, 0, 10 ) );
			$set[ 'motion_fx_' . $effect . '_direction' ]     = $b < $a ? '' : 'negative';
			$set[ 'motion_fx_' . $effect . '_affectedRange' ] = self::range( $s );
			$mid = ( $a + $b ) / 2;
			if ( abs( $mid ) >= 0.5 ) {
				$off[ $effect ] = $mid;
			}
			$any = true;
		}
		foreach ( $off as $effect => $mid ) {
			$set['_transform_translate_popover']              = 'transform';
			$set[ '_transform_' . $effect . '_effect' ] = array( 'unit' => 'px', 'size' => round( $mid, 1 ) );
		}
		if ( 'yes' === Values::get( $s, 'ep_parallax_effects_opacity_toggole' ) ) {
			$mode = Values::get( $s, 'ep_parallax_effects_opacity' );
			if ( in_array( $mode, array( 'htov', 'vtoh' ), true ) ) {
				$set['motion_fx_opacity_effect']    = 'yes';
				$set['motion_fx_opacity_direction'] = 'htov' === $mode ? 'out-in' : 'in-out';
				$set['motion_fx_opacity_level']     = array( 'unit' => 'px', 'size' => 10 );
				$set['motion_fx_opacity_range']     = self::FULL_RANGE;
				$any                                = true;
			}
			if ( 'yes' === Values::get( $s, 'ep_parallax_effects_opacity_custom_show' ) ) {
				$out->loss( __( 'opacidad con valores propios', 'digitalisimo-migration' ) );
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_parallax_effects_blur' ) ) {
			$a = (float) ( Values::size_of( $s, 'ep_parallax_effects_blur_start' ) ?? 0 );
			$b = (float) ( Values::size_of( $s, 'ep_parallax_effects_blur_end' ) ?? 0 );
			if ( $a > 0 || $b > 0 ) {
				$set['motion_fx_blur_effect']    = 'yes';
				$set['motion_fx_blur_direction'] = $a >= $b ? 'out-in' : 'in-out';
				$set['motion_fx_blur_level']     = array( 'unit' => 'px', 'size' => self::clamp( max( $a, $b ), 1, 15 ) );
				$set['motion_fx_blur_range']     = self::FULL_RANGE;
				$any                             = true;
				if ( $a > 0 && $b > 0 ) {
					$out->loss( __( 'desenfoque con dos extremos distintos de cero', 'digitalisimo-migration' ) );
				}
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_parallax_effects_rotate' ) ) {
			$a = Values::size_of( $s, 'ep_parallax_effects_rotate_start' );
			$b = Values::size_of( $s, 'ep_parallax_effects_rotate_end' );
			if ( null !== $a || null !== $b ) {
				$a = (float) $a;
				$b = (float) $b;
				$set['motion_fx_rotateZ_effect']        = 'yes';
				$set['motion_fx_rotateZ_speed']         = array( 'unit' => 'px', 'size' => self::clamp( abs( $b - $a ) / 100, 0, 10 ) );
				$set['motion_fx_rotateZ_direction']     = $b < $a ? '' : 'negative';
				$set['motion_fx_rotateZ_affectedRange'] = self::range( $s );
				if ( abs( ( $a + $b ) / 2 ) >= 0.5 ) {
					$set['_transform_rotate_popover']  = 'transform';
					$set['_transform_rotateZ_effect'] = array( 'unit' => 'deg', 'size' => round( ( $a + $b ) / 2, 1 ) );
				}
				$any = true;
			}
		}
		if ( 'yes' === Values::get( $s, 'ep_parallax_effects_scale' ) ) {
			$a = (float) ( Values::size_of( $out->orig, 'ep_parallax_effects_scale_start', Dispatcher::default_of( 'parallax-effects', 'ep_parallax_effects_scale_start' ) ) ?? 1 );
			$b = (float) ( Values::size_of( $out->orig, 'ep_parallax_effects_scale_end', Dispatcher::default_of( 'parallax-effects', 'ep_parallax_effects_scale_end' ) ) ?? 1 );
			if ( $a > 0 && abs( $b - $a ) > 0.0001 ) {
				$set['motion_fx_scale_effect']    = 'yes';
				$set['motion_fx_scale_direction'] = 'out-in';
				$set['motion_fx_scale_speed']     = array( 'unit' => 'px', 'size' => self::clamp( 10 * ( $b / $a - 1 ), -10, 10 ) );
				$set['motion_fx_scale_range']     = self::FULL_RANGE;
				if ( abs( $a - 1 ) > 0.0001 ) {
					$set['_transform_scale_popover'] = 'transform';
					$set['_transform_scale_effect']  = array( 'unit' => 'px', 'size' => round( $a, 2 ) );
				}
				$any = true;
			}
		}
		if ( ! $any ) {
			$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'parallax-effects' ) ) );
			return false;
		}
		$media = Values::get( $s, 'ep_parallax_effects_media_query', '' );
		if ( isset( self::MEDIA[ $media ] ) ) {
			$devices = array();
			foreach ( Values::devices() as $device ) {
				if ( Values::device_max( $device ) >= self::MEDIA[ $media ] ) {
					$devices[] = $device;
				}
			}
			$set['motion_fx_devices'] = $devices;
		}
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		foreach ( array( 'hue' => __( 'tono', 'digitalisimo-migration' ), 'sepia' => __( 'sepia', 'digitalisimo-migration' ), 'easing' => __( 'curva del movimiento', 'digitalisimo-migration' ), 'transition' => __( 'transición CSS', 'digitalisimo-migration' ) ) as $key => $what ) {
			if ( Values::used( $s[ 'ep_parallax_effects_' . $key ] ?? '' ) ) {
				$out->loss( $what );
			}
		}
		if ( 'section' === Values::get( $out->orig, 'ep_parallax_effects_target', 'self' ) ) {
			$out->loss( __( 'movimiento relativo a la sección', 'digitalisimo-migration' ) );
		}
		return true;
	}

	/** Extremos [a, b] en px de un eje; null con vw o sin valores. Las paradas propias usan la primera y la última. */
	private static function pair( Out $out, string $axis ): ?array {
		$s = $out->orig;
		if ( 'yes' === Values::get( $s, 'ep_parallax_effects_' . $axis . '_custom_show' ) ) {
			$stops = array_values( array_filter( array_map( 'trim', explode( ',', (string) Values::get( $s, 'ep_parallax_effects_' . $axis . '_custom_value', '' ) ) ), 'strlen' ) );
			$nums  = array();
			foreach ( $stops as $stop ) {
				if ( ! preg_match( '/^(-?\d+(?:\.\d+)?)\s*(px)?$/', $stop, $m ) ) {
					$out->loss( __( 'valores en vw y paradas múltiples', 'digitalisimo-migration' ) );
					return null;
				}
				$nums[] = (float) $m[1];
			}
			if ( count( $nums ) < 2 ) {
				return null;
			}
			if ( count( $nums ) > 2 ) {
				$out->loss( __( 'valores en vw y paradas múltiples', 'digitalisimo-migration' ) );
			}
			return array( $nums[0], $nums[ count( $nums ) - 1 ] );
		}
		$start = $out->get( 'ep_parallax_effects_' . $axis . '_start' );
		$end   = $out->get( 'ep_parallax_effects_' . $axis . '_end' );
		foreach ( array( $start, $end ) as $value ) {
			if ( is_array( $value ) && 'vw' === ( $value['unit'] ?? 'px' ) ) {
				$out->loss( __( 'valores en vw y paradas múltiples', 'digitalisimo-migration' ) );
				return null;
			}
		}
		$a = is_array( $start ) && is_numeric( $start['size'] ?? null ) ? (float) $start['size'] : null;
		$b = is_array( $end ) && is_numeric( $end['size'] ?? null ) ? (float) $end['size'] : null;
		if ( null === $a && null === $b ) {
			return null;
		}
		return array( (float) $a, (float) $b );
	}

	/** Recorrido de la pantalla: `viewport_start`/`_end` en % si se guardaron; si no, 0–100. */
	private static function range( array $s ): array {
		if ( 'yes' !== Values::get( $s, 'ep_parallax_effects_viewport' ) ) {
			return self::FULL_RANGE;
		}
		$start = Values::get( $s, 'ep_parallax_effects_viewport_start', '' );
		$end   = Values::get( $s, 'ep_parallax_effects_viewport_end', '' );
		if ( is_scalar( $start ) && is_scalar( $end ) && preg_match( '/^\s*(\d+(?:\.\d+)?)\s*%\s*$/', (string) $start, $a ) && preg_match( '/^\s*(\d+(?:\.\d+)?)\s*%\s*$/', (string) $end, $b ) ) {
			return array( 'sizes' => array( 'start' => self::clamp( (float) $a[1], 0, 100 ), 'end' => self::clamp( (float) $b[1], 0, 100 ) ), 'unit' => '%' );
		}
		return self::FULL_RANGE;
	}

	/* ---------------------------------------------------------------- background-parallax */

	public static function background( Out $out ): bool {
		$s = $out->orig;
		if ( 'classic' !== ( $s['background_background'] ?? '' ) || empty( $s['background_image']['url'] ) ) {
			// El movimiento del fondo del núcleo exige un fondo clásico con imagen; sin imagen no se movía nada visible.
			$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'background-parallax' ) ) );
			if ( 'yes' === Values::get( $s, 'ep_parallax_bg_colors' ) ) {
				$out->loss( __( 'colores de fondo y borde al desplazar', 'digitalisimo-migration' ) );
			}
			return false;
		}
		$set = array( 'background_motion_fx_motion_fx_scrolling' => 'yes' );
		foreach ( array( 'section_parallax_value' => 'translateY', 'section_parallax_x_value' => 'translateX' ) as $key => $effect ) {
			$value = Values::size_of( $s, $key, Dispatcher::default_of( 'background-parallax', $key ) );
			if ( null === $value || abs( $value ) < 0.5 ) {
				continue;
			}
			$set[ 'background_motion_fx_' . $effect . '_effect' ]        = 'yes';
			$set[ 'background_motion_fx_' . $effect . '_direction' ]     = $value < 0 ? '' : 'negative';
			$set[ 'background_motion_fx_' . $effect . '_speed' ]         = array( 'unit' => 'px', 'size' => self::clamp( round( abs( $value ) / 60, 1 ), 0.1, 10 ) );
			$set[ 'background_motion_fx_' . $effect . '_affectedRange' ] = self::FULL_RANGE;
		}
		if ( 1 === count( $set ) || ! $out->set_all( $set ) ) {
			return false;
		}
		$out->loss( __( 'amplitud exacta en px (se supone una altura de 600 px)', 'digitalisimo-migration' ) );
		if ( 'yes' === Values::get( $s, 'ep_parallax_bg_colors' ) ) {
			$out->loss( __( 'colores de fondo y borde al desplazar', 'digitalisimo-migration' ) );
		}
		return true;
	}

	/* ---------------------------------------------------------------- image-parallax */

	public static function layers( Out $out ): bool {
		$s      = $out->orig;
		$layers = array();
		foreach ( (array) Values::get( $s, 'section_parallax_elements', array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$image = $row['section_parallax_image'] ?? null;
			$url   = is_array( $image ) && is_string( $image['url'] ?? null ) ? trim( $image['url'] ) : '';
			if ( '' === $url || ! preg_match( '#^(https?:)?//|^/#', $url ) ) {
				continue;
			}
			$layers[] = array(
				'image' => array( 'id' => (int) ( $image['id'] ?? 0 ), 'url' => $url ),
				'depth' => is_numeric( $row['section_parallax_depth'] ?? null ) ? (float) $row['section_parallax_depth'] : 0.1,
				'x'     => is_numeric( $row['section_parallax_bgp_x'] ?? null ) ? self::clamp( (float) $row['section_parallax_bgp_x'], 0, 100 ) : 50,
				'y'     => is_numeric( $row['section_parallax_bgp_y'] ?? null ) ? self::clamp( (float) $row['section_parallax_bgp_y'], 0, 100 ) : 50,
				'size'  => Css_Value::enum( $row['section_parallax_bg_size'] ?? 'cover', array( 'auto', 'cover', 'contain' ) ) ?? 'cover',
			);
		}
		$colors = 'yes' === Values::get( $s, 'element_pack_sbgc_parallax_show' ) || 'yes' === Values::get( $s, 'element_pack_sbc_parallax_show' );
		if ( ! $layers ) {
			if ( $colors ) {
				$out->loss( __( 'colores al desplazar', 'digitalisimo-migration' ) );
			} else {
				$out->note( 'ext.inert', array( 'type' => Dispatcher::label( 'image-parallax' ) ) );
			}
			return false;
		}
		if ( 'container' === $out->el_type() ) {
			foreach ( $layers as $index => $layer ) {
				$out->prepend[] = self::layer_container( $out, $layer, $index );
			}
			$out->css( 'selector{isolation:isolate}' );
		} else {
			if ( count( $layers ) > 1 || ! empty( $s['background_image']['url'] ) ) {
				$out->note( 'ext.review.layers', array( 'count' => count( $layers ) ) );
				return false;
			}
			$layer = $layers[0];
			$set   = array(
				'background_background'                          => 'classic',
				'background_image'                               => $layer['image'],
				'background_position'                            => 'initial',
				'background_xpos'                                => array( 'unit' => '%', 'size' => $layer['x'] ),
				'background_ypos'                                => array( 'unit' => '%', 'size' => $layer['y'] ),
				'background_size'                                => $layer['size'],
				'background_motion_fx_motion_fx_mouse'           => 'yes',
				'background_motion_fx_mouseTrack_effect'         => 'yes',
				'background_motion_fx_mouseTrack_speed'          => array( 'unit' => 'px', 'size' => self::depth_speed( $layer['depth'] ) ),
			);
			if ( 'classic' !== ( $out->s['background_background'] ?? 'classic' ) && ! Out::blank( $out->s['background_background'] ?? '' ) ) {
				$out->note( 'ext.conflict', array( 'type' => Dispatcher::label( 'image-parallax' ), 'name' => 'background_background' ) );
				return false;
			}
			unset( $out->s['background_background'] );
			if ( ! $out->set_all( $set ) ) {
				return false;
			}
		}
		$mode = Values::get( $s, 'section_parallax_mode', '' );
		if ( in_array( $mode, array( 'clip', 'hover' ), true ) ) {
			$out->loss( __( 'modos recorte y sólo al pasar el puntero', 'digitalisimo-migration' ) );
		}
		if ( $colors ) {
			$out->loss( __( 'colores al desplazar', 'digitalisimo-migration' ) );
		}
		$out->loss( __( 'giroscopio y fricción', 'digitalisimo-migration' ) );
		return true;
	}

	/** Un contenedor hijo, absoluto y bajo el contenido, con la imagen de la capa y el movimiento con el ratón. */
	private static function layer_container( Out $out, array $layer, int $index ): array {
		$id = substr( md5( $out->id() . '|' . $index . '|' . $layer['image']['url'] ), 0, 7 );
		return array(
			'id'       => $id,
			'elType'   => 'container',
			'isInner'  => true,
			'settings' => array(
				'content_width'                   => 'full',
				'position'                        => 'absolute',
				'_offset_orientation_h'           => 'start',
				'_offset_x'                       => array( 'unit' => 'px', 'size' => 0 ),
				'_offset_orientation_v'           => 'start',
				'_offset_y'                       => array( 'unit' => 'px', 'size' => 0 ),
				'width'                           => array( 'unit' => '%', 'size' => 100 ),
				'min_height'                      => array( 'unit' => '%', 'size' => 100 ),
				'z_index'                         => -1,
				'background_background'           => 'classic',
				'background_image'                => $layer['image'],
				'background_position'             => 'initial',
				'background_xpos'                 => array( 'unit' => '%', 'size' => $layer['x'] ),
				'background_ypos'                 => array( 'unit' => '%', 'size' => $layer['y'] ),
				'background_size'                 => $layer['size'],
				'motion_fx_motion_fx_mouse'       => 'yes',
				'motion_fx_mouseTrack_effect'     => 'yes',
				'motion_fx_mouseTrack_speed'      => array( 'unit' => 'px', 'size' => self::depth_speed( $layer['depth'] ) ),
				'css_classes'                     => 'digi-legacy-layer',
				'custom_css'                      => 'selector{pointer-events:none;inset:0}',
			),
			'elements' => array(),
		);
	}

	/** Profundidad de parallax.js (0–1) → velocidad del seguimiento del ratón del núcleo (0,1–10). */
	private static function depth_speed( float $depth ): float {
		return self::clamp( round( $depth * 10, 1 ), 0.1, 10 );
	}

	/* ---------------------------------------------------------------- reveal-effects */

	public static function reveal( Out $out ): bool {
		$map       = array( 'lr' => 'fadeInLeft', 'rl' => 'fadeInRight', 'tb' => 'fadeInDown', 'bt' => 'fadeInUp', 'c' => 'zoomIn' );
		$direction = (string) $out->get( 'element_pack_reveal_effects_direction' );
		$animation = $map[ $direction ] ?? 'fadeInLeft';
		$speed     = Values::size_of( $out->orig, 'element_pack_reveal_effects_speed', Dispatcher::default_of( 'reveal-effects', 'element_pack_reveal_effects_speed' ) ) ?? 5;
		$ms        = $speed * 100;
		$duration  = $ms <= 900 ? 'fast' : ( $ms <= 1600 ? '' : 'slow' );
		$set       = array( '_animation' => $animation );
		if ( '' !== $duration ) {
			$set['animation_duration'] = $duration;
		}
		if ( ! $out->set_all( $set ) ) {
			return false;
		}
		$out->loss( __( 'barrido con bloque de color, curva y umbral de aparición', 'digitalisimo-migration' ) );
		return true;
	}

	private static function clamp( float $value, float $min, float $max ): float {
		return max( $min, min( $max, round( $value, 2 ) ) );
	}
}
