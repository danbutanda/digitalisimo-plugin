<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

use Digitalisimo\Elements\Legacy\Effects\Css_Value;

defined( 'ABSPATH' ) || exit;

/**
 * Familias de contenido estático de Prime Slider (frente E): A, B, C, D, H, I, J, K y M (`research-ps.md` §2.4,
 * §3.E.1-§3.E.2 de la especificación).
 *
 * El archivo se llama `class-static.php` (V.15), pero `Static` es una palabra reservada de PHP y no puede ser el nombre
 * de una clase: la clase es `Static_Families` y `Converter` la carga con `require_once`.
 *
 * Recibe los ajustes **rellenados, limpios y con las imágenes resueltas** y el mapa del widget, y devuelve el elemento
 * de destino o un fragmento (`elType` = `__fragment`) con hermanos; el elemento principal conserva el ID del original.
 * Las decisiones que dependen de las imágenes (familias C y H) se toman antes de resolverlas (`plan()`, C1-29).
 *
 * Lo que pierde cada widget se anota en `$codes` (`ps.loss.*`, `ps.h1`…); `Converter` lo pasa al informe.
 */
final class Static_Families {
	const FAMILIES = array( 'A', 'B', 'C', 'D', 'H', 'I', 'J', 'K', 'M' );

	const LOGOS    = 'digitalisimo-logos';
	const TABS     = 'digitalisimo-fancy-tabs';
	const MEDIA    = 'media-carousel';
	const SOCIAL   = 'social-icons';
	const SCROLL   = 'digitalisimo-scroll-button';
	const DEVICES  = array( '', '_tablet', '_mobile' );
	const HEADINGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/** Transiciones de Prime Slider → las tres del slider anidado (§3.E.2). */
	const EFFECTS = array(
		'slide'     => 'slide',
		'fade'      => 'fade',
		'scale'     => 'scale',
		'push'      => 'slide',
		'pull'      => 'slide',
		'cube'      => 'slide',
		'flip'      => 'slide',
		'coverflow' => 'slide',
		'creative'  => 'fade',
		'cards'     => 'fade',
		'shutters'  => 'fade',
		'slicer'    => 'fade',
		'tinder'    => 'fade',
		'gl'        => 'fade',
	);

	/** Estilos comunes de Prime Slider → controles nativos de cada parte (§3.E.0.7): [origen, destino, prefijo]. */
	const STYLES = array(
		'title'     => array( array( 'title_color', 'title_color', false ), array( 'title_typography_', 'typography_', true ) ),
		'sub_title' => array( array( 'sub_title_color', 'title_color', false ), array( 'sub_title_typography_', 'typography_', true ) ),
		'text'      => array( array( 'excerpt_color', 'text_color', false ), array( 'text_color', 'text_color', false ), array( 'excerpt_typography_', 'typography_', true ), array( 'text_typography_', 'typography_', true ), array( 'description_color', 'text_color', false ), array( 'desc_color', 'text_color', false ) ),
		'button'    => array(
			array( 'slide_button_text_color', 'button_text_color', false ),
			array( 'button_text_color', 'button_text_color', false ),
			array( 'link_btn_color', 'button_text_color', false ),
			array( 'slide_button_background_color', 'background_color', false ),
			array( 'button_background_color', 'background_color', false ),
			array( 'slide_button_typography_', 'typography_', true ),
			array( 'button_typography_', 'typography_', true ),
			array( 'slide_button_border_radius', 'border_radius', false ),
			array( 'slide_button_border_', 'border_', true ),
			array( 'slide_button_text_padding', 'text_padding', false ),
			array( 'button_text_padding', 'text_padding', false ),
		),
		'slider'    => array( array( 'arrows_color', 'nav_color', false ), array( 'arrows_background', 'nav_background', false ), array( 'arrows_background_color', 'nav_background', false ), array( 'arrows_size', 'nav_size', false ), array( 'pagination_size', 'dot_size', false ), array( 'dots_size', 'dot_size', false ) ),
	);

	/** @var array<int, array{0:string, 1:array}> Códigos de la última construcción. */
	public static $codes = array();

	/** @var array<string, bool> Claves de estilo del original que tienen equivalente. */
	public static $consumed = array();

	/** @var array Ajustes guardados del original (estilos y claves comunes). */
	private static $original = array();

	/* ---------------------------------------------------------------- Plan (antes de resolver las imágenes) */

	/**
	 * Decisiones que no pueden depender de si una imagen de demostración llega a copiarse (C1-29): en C, carrusel de
	 * logotipos sólo si cada fila tiene imagen; en H, carrusel de logotipos si hay títulos o textos visibles.
	 */
	public static function plan( string $id, array $map, array $s ): array {
		$family = (string) $map['family'];
		$rows   = self::rows( $map, $s );
		if ( 'C' === $family ) {
			$all = (bool) $rows;
			foreach ( $rows as $row ) {
				$all = $all && '' !== self::url( $row['image'] ?? null );
			}
			return array( 'logos' => $all && Builder::usable( self::LOGOS ) );
		}
		if ( 'H' === $family ) {
			$text = false;
			foreach ( $rows as $row ) {
				$text = $text || ( self::visible( $map, $s, 'title' ) && '' !== self::plain( $row['title'] ?? '' ) ) || ( self::visible( $map, $s, 'text' ) && '' !== self::plain( $row['text'] ?? '' ) );
			}
			return array( 'logos' => $text );
		}
		return array();
	}

	/* ---------------------------------------------------------------- Construcción */

	/**
	 * Elemento de destino, fragmento o null (sin destino posible).
	 *
	 * @param string $id       `widgetType` de Prime Slider.
	 * @param array  $map      Mapa del widget.
	 * @param array  $s        Ajustes rellenados, limpios y resueltos.
	 * @param array  $element  Widget original (con sus ajustes guardados).
	 * @param int    $post_id  Documento.
	 * @param string $parent   `elType` del padre (`container`, `column`… o '').
	 * @param array  $plan     De `plan()`.
	 */
	public static function build( string $id, array $map, array $s, array $element, int $post_id, string $parent, array $plan ): ?array {
		self::$codes    = array();
		self::$consumed = array();
		self::$original = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		foreach ( (array) ( $map['losses'] ?? array() ) as $loss ) {
			self::code( 'ps.loss.feature', array( 'name' => (string) $loss ) );
		}
		switch ( (string) $map['family'] ) {
			case 'C':
				$main = ! empty( $plan['logos'] ) ? self::logos( $map, $s, $post_id ) : self::slider( $map, $s, $post_id );
				break;
			case 'H':
				$main = ! empty( $plan['logos'] ) && Builder::usable( self::LOGOS ) ? self::logos( $map, $s, $post_id ) : self::media( $map, $s );
				break;
			case 'J':
				$main = self::tabs( $map, $s );
				break;
			case 'K':
				$main = 'container' === $parent && Builder::usable( 'container' ) ? self::screens( $map, $s ) : self::slider( $map, $s, $post_id );
				break;
			case 'M':
				return null;
			default:
				$main = self::slider( $map, $s, $post_id );
		}
		if ( null === $main ) {
			return null;
		}
		$main['id'] = (string) ( $element['id'] ?? $main['id'] );
		$before     = self::before( $map, $s );
		$after      = self::after( $map, $s );
		if ( ! $before && ! $after ) {
			return $main;
		}
		self::code( 'ps.siblings', array( 'count' => count( $before ) + count( $after ) ) );
		return array( 'elType' => '__fragment', 'elements' => array_merge( $before, array( $main ), $after ) );
	}

	/* ---------------------------------------------------------------- Filas */

	/**
	 * Filas normalizadas: `sub_title`, `title`, `title_link`, `text`, `button_text`, `button_link`, `image`, `color`,
	 * `video`, `template_id`, `kenburns`, `play` y los campos de la familia K.
	 */
	public static function rows( array $map, array $s ): array {
		$key  = (string) ( $map['rows'] ?? 'slides' );
		$out  = array();
		$rows = is_array( $s[ $key ] ?? null ) ? $s[ $key ] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$n = array( '_id' => (string) ( $row['_id'] ?? '' ) );
			foreach ( (array) ( $map['fields'] ?? array() ) as $name => $source ) {
				foreach ( (array) $source as $candidate ) {
					if ( array_key_exists( $candidate, $row ) && null !== $row[ $candidate ] && '' !== $row[ $candidate ] ) {
						$n[ $name ] = $row[ $candidate ];
						break;
					}
				}
			}
			foreach ( (array) ( $map['widget_fields'] ?? array() ) as $name => $source ) {
				if ( ! isset( $n[ $name ] ) && isset( $s[ $source ] ) && '' !== $s[ $source ] ) {
					$n[ $name ] = $s[ $source ];
				}
			}
			if ( ! empty( $map['background'] ) ) {
				// `general`: fondo de color, imagen, vídeo o YouTube por fila.
				$type = (string) ( $row['background'] ?? 'color' );
				if ( 'image' === $type ) {
					$n['image'] = $row['image'] ?? null;
				} elseif ( 'video' === $type || 'youtube' === $type ) {
					$n['video'] = self::url( $row[ 'video' === $type ? 'video_link' : 'youtube_link' ] ?? '' );
					unset( $n['image'] );
				} else {
					$n['color'] = (string) ( $row['color'] ?? '' );
					unset( $n['image'] );
				}
			}
			if ( ! empty( $map['halves'] ) ) {
				// `multiscroll`: fondo de cada mitad (color o imagen).
				foreach ( array( 'left', 'right' ) as $side ) {
					$n[ $side ] = array(
						'type'  => (string) ( $row[ $side . '_background' ] ?? 'color' ),
						'color' => $row[ $side . '_background_color' ] ?? '',
						'image' => $row[ $side . '_background_image' ] ?? null,
					);
				}
			}
			if ( ! empty( $map['play'] ) ) {
				$n['play'] = self::play_link( (array) $map['play'], $row );
			}
			if ( 'yes' === ( $s['kenburns_animation'] ?? '' ) ) {
				$n['kenburns'] = 'yes' === ( $s['kenburns_reverse'] ?? '' ) ? 'out' : 'in';
			}
			$out[] = $n;
		}
		return $out;
	}

	/** Enlace del botón de reproducción (`isolate`, `coddle`, `pagepiling`) si no es de demostración. */
	private static function play_link( array $play, array $row ): array {
		$type = (string) ( $row[ $play['type'] ?? '' ] ?? ( $play['default'] ?? 'youtube' ) );
		$key  = (string) ( $play['keys'][ $type ] ?? ( $play['key'] ?? '' ) );
		$url  = self::url( $row[ $key ] ?? '' );
		return '' === $url ? array() : array( 'type' => $type, 'url' => $url );
	}

	/* ---------------------------------------------------------------- Slider anidado (A, B, D, I y K en columna) */

	private static function slider( array $map, array $s, int $post_id ): ?array {
		$rows     = self::rows( $map, $s );
		$settings = self::slider_settings( $map, $s, count( $rows ), $post_id );
		if ( Builder::usable( Builder::NESTED ) ) {
			$children = array();
			$titles   = array();
			foreach ( $rows as $i => $row ) {
				$children[] = Builder::container( self::slide_settings( $map, $s, $row, $i + 1 ), self::slide_widgets( $map, $s, $row ) );
				$titles[]   = array( 'slide_title' => self::plain( $row['label'] ?? ( $row['title'] ?? '' ) ) );
			}
			$settings['slides'] = $titles;
			return Builder::nested_slider( $settings, $children );
		}
		if ( ! Builder::usable( Builder::FALLBACK ) ) {
			return null;
		}
		self::code( 'ps.fallback.slides', array( 'target' => Builder::FALLBACK ) );
		foreach ( $rows as $row ) {
			if ( ! empty( $row['video'] ) ) {
				self::code( 'ps.loss.video_background', array() );
				break;
			}
		}
		$extra = array(
			'title_tag' => self::tag( $map, $s, 'title' ),
			'align'     => (string) ( $s[ $map['align'] ?? 'content_alignment' ] ?? '' ),
		);
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['color']         = self::color( $row['color'] ?? '' ) ?? '';
			$rows[ $index ]['overlay_color'] = self::overlay( $map, $s )['color'] ?? '';
			if ( 'front' === ( $map['image'] ?? 'background' ) && ! empty( $row['image'] ) ) {
				$rows[ $index ]['image'] = $row['image'];
			}
		}
		return Builder::slides_fallback( $settings + $extra, $rows );
	}

	/** Ajustes del slider anidado (§3.E.2). */
	public static function slider_settings( array $map, array $s, int $count, int $post_id ): array {
		$out = array( 'effect' => self::effect( $map, $s ) );
		$speed = self::number( $s[ $map['speed'] ?? 'speed' ] ?? null );
		if ( 'slide' !== $out['effect'] && null !== $speed ) {
			$out['speed'] = (int) max( 0, min( 3000, $speed ) );
		}
		$auto            = (array) ( $map['autoplay'] ?? array() );
		$on              = (string) ( $s[ $auto['on'] ?? 'autoplay' ] ?? '' );
		$out['autoplay'] = 'yes' === $on ? 'yes' : '';
		$delay           = null;
		foreach ( (array) ( $auto['delay'] ?? array( 'autoplay_interval', 'autoplay_speed', 'autoplay_delay' ) ) as $key ) {
			$delay = self::number( $s[ $key ] ?? null );
			if ( null !== $delay ) {
				break;
			}
		}
		if ( null !== $delay ) {
			$clamped = (int) max( 2000, min( 20000, $delay ) );
			if ( $clamped !== (int) $delay && 'yes' === $out['autoplay'] ) {
				self::code( 'ps.loss.delay_clamped', array( 'count' => (int) $delay ) );
			}
			$out['autoplay_delay'] = $clamped;
		}
		$hover                 = (string) ( $s[ $auto['hover'] ?? '' ] ?? ( $s['pause_on_hover'] ?? ( $s['pauseonhover'] ?? '' ) ) );
		$out['pause_on_hover'] = 'yes' === $hover ? 'yes' : '';
		$loop                  = $auto['loop'] ?? ( array_key_exists( 'finite', $s ) ? 'finite' : 'loop' );
		$out['loop']           = false === $loop ? ( $map['loop_default'] ?? 'yes' ) : ( 'yes' === ( $s[ $loop ] ?? '' ) ? 'yes' : '' );

		$nav    = (array) ( $map['nav'] ?? array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ) );
		// `true`: el origen las pintaba siempre (botones «anterior» y «siguiente» sin interruptor).
		$arrows = true === ( $nav['arrows'] ?? null ) || ( is_string( $nav['arrows'] ?? null ) && self::flag( $s, $nav['arrows'] ) );
		$dots   = true === ( $nav['dots'] ?? null ) || ( is_string( $nav['dots'] ?? null ) && self::flag( $s, $nav['dots'] ) );
		$counter = ! empty( $nav['fraction'] ) && 'yes' === ( $s[ $nav['fraction'] ] ?? '' );
		if ( $counter ) {
			$out['counter'] = 'yes';
			if ( ! $dots ) {
				$arrows = true;
			}
		}
		if ( $arrows && $dots ) {
			$out['navigation'] = 'both';
		} elseif ( $arrows ) {
			$out['navigation'] = 'arrows';
		} else {
			$out['navigation'] = 'dots';
			if ( ! $dots && $count > 1 ) {
				self::code( 'ps.loss.nav_none', array() );
			}
		}
		if ( 'dots' !== $out['navigation'] && self::sides( $map, $s ) ) {
			$out['arrows_position'] = 'sides';
		}
		$out = array_merge( $out, self::height( $map, $s ) );
		$out = array_merge( $out, self::copy_styles( 'slider', $s ) );
		$label = self::document_title( $post_id );
		if ( '' !== $label ) {
			$out['carousel_label'] = $label;
		}
		return $out;
	}

	/** Transición del destino. */
	private static function effect( array $map, array $s ): string {
		if ( isset( $map['effect'] ) && in_array( $map['effect'], array( 'slide', 'fade', 'scale' ), true ) ) {
			return (string) $map['effect'];
		}
		$key    = (string) ( $map['effect_key'] ?? 'swiper_effect' );
		$source = strtolower( (string) ( $s[ $key ] ?? 'slide' ) );
		$effect = self::EFFECTS[ $source ] ?? 'slide';
		if ( ! in_array( $source, array( 'slide', 'fade', 'scale' ), true ) ) {
			self::code( 'ps.loss.effect', array( 'type' => $source ) );
		}
		return $effect;
	}

	/** ¿Flechas a los lados? Por el mapa o por las posiciones de `custom` (centro a izquierda y derecha). */
	private static function sides( array $map, array $s ): bool {
		if ( 'sides' === ( $map['arrows'] ?? '' ) ) {
			return true;
		}
		if ( 'positions' === ( $map['arrows'] ?? '' ) ) {
			return 'center-left' === ( $s['arrows_prev_position'] ?? '' ) && 'center-right' === ( $s['arrows_next_position'] ?? '' );
		}
		return false;
	}

	/** Altura: `slider_height` responsivo, o la proporción de UIkit en el CSS del slider. */
	private static function height( array $map, array $s ): array {
		$spec = (array) ( $map['height'] ?? array() );
		$out  = array();
		if ( ! empty( $spec['ratio'] ) ) {
			if ( 'yes' === ( $s['enable_height'] ?? '' ) && ! empty( $s['viewport_height']['size'] ) ) {
				foreach ( self::DEVICES as $device ) {
					$value = $s[ 'viewport_height' . $device ] ?? null;
					if ( self::slider_value( $value ) ) {
						$out[ 'slider_height' . $device ] = self::slider_value( $value );
					}
				}
				return $out;
			}
			$w   = (int) ( $s['slider_size_ratio']['width'] ?? 0 );
			$h   = (int) ( $s['slider_size_ratio']['height'] ?? 0 );
			$min = (int) ( $s['slider_min_height']['size'] ?? 0 );
			$w   = $w > 0 && $h > 0 ? $w : 16;
			$h   = $w > 0 && $h > 0 && (int) ( $s['slider_size_ratio']['height'] ?? 0 ) > 0 ? $h : 9;
			$min = $min > 0 ? $min : (int) ( $spec['min'] ?? 0 );
			$css = 'selector .digi-nslider__track{aspect-ratio:' . $w . '/' . $h . ( $min > 0 ? ';min-height:' . $min . 'px' : '' ) . '}';
			if ( ! class_exists( Css_Value::class ) || Css_Value::block( $css ) ) {
				$out['custom_css'] = $css;
			}
			return $out;
		}
		foreach ( (array) ( $spec['keys'] ?? array( 'item_height', 'slider_height', 'slider_item_height' ) ) as $key ) {
			if ( ! self::slider_value( $s[ $key ] ?? null ) ) {
				continue;
			}
			foreach ( self::DEVICES as $device ) {
				$value = self::slider_value( $s[ $key . $device ] ?? null );
				if ( $value ) {
					$out[ 'slider_height' . $device ] = $value;
				}
			}
			break;
		}
		return $out;
	}

	/** SLIDER válido `{unit, size}` con una unidad del destino, o null. */
	private static function slider_value( $value ): ?array {
		if ( ! is_array( $value ) || ! isset( $value['size'] ) || '' === $value['size'] || ! is_numeric( $value['size'] ) || (float) $value['size'] <= 0 ) {
			return null;
		}
		$unit = (string) ( $value['unit'] ?? 'px' );
		$unit = in_array( $unit, array( 'px', 'vh', 'em', 'rem' ), true ) ? $unit : 'px';
		return array( 'unit' => $unit, 'size' => 0 + $value['size'], 'sizes' => array() );
	}

	/** Ajustes de cada contenedor hijo (§3.E.2). */
	private static function slide_settings( array $map, array $s, array $row, int $i ): array {
		$out = array(
			'_title'               => Builder::slide_title( $i ),
			'content_width'        => 'full',
			'flex_direction'       => 'column',
			'flex_justify_content' => 'center',
		);
		$align = self::align( $s[ $map['align'] ?? 'content_alignment' ] ?? '' );
		if ( '' !== $align ) {
			$out['flex_align_items'] = array( 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end' )[ $align ];
		}
		$image = 'background' === ( $map['image'] ?? 'background' ) ? self::media_value( $row['image'] ?? null ) : null;
		$color = self::color( $row['color'] ?? '' );
		if ( $image ) {
			$out['background_background'] = 'classic';
			$out['background_image']      = $image;
			$out['background_size']       = 'cover';
			$out['background_position']   = 'center center';
		} elseif ( ! empty( $row['video'] ) ) {
			$out['background_background'] = 'video';
			$out['background_video_link'] = (string) $row['video'];
		} elseif ( null !== $color ) {
			$out['background_background'] = 'classic';
			$out['background_color']      = $color;
		}
		$overlay = self::overlay( $map, $s );
		if ( $overlay ) {
			$out['background_overlay_background'] = 'classic';
			$out['background_overlay_color']      = $overlay['color'];
			if ( isset( $overlay['blend'] ) ) {
				$out['overlay_blend_mode'] = $overlay['blend'];
			}
		}
		return $out;
	}

	/** Superposición (`overlay` background o blend con su color), o array vacío. */
	private static function overlay( array $map, array $s ): array {
		if ( empty( $map['overlay'] ) ) {
			return array();
		}
		$type  = (string) ( $s['overlay'] ?? ( $s['overlay_type'] ?? 'none' ) );
		$color = self::color( $s['overlay_color'] ?? '' );
		if ( ! in_array( $type, array( 'background', 'blend' ), true ) || null === $color ) {
			return array();
		}
		$out = array( 'color' => $color );
		if ( 'blend' === $type ) {
			$blend = (string) ( $s['blend_type'] ?? 'multiply' );
			if ( in_array( $blend, array( 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'saturation', 'color', 'difference', 'exclusion', 'hue', 'luminosity' ), true ) ) {
				$out['blend'] = $blend;
			}
		}
		return $out;
	}

	/** Widgets de una diapositiva, en el orden de origen (§3.E.2). */
	public static function slide_widgets( array $map, array $s, array $row ): array {
		$out   = array();
		$align = self::align( $s[ $map['align'] ?? 'content_alignment' ] ?? '' );
		foreach ( array( 'sub_title', 'title' ) as $part ) {
			$text = trim( (string) ( is_scalar( $row[ $part ] ?? null ) ? $row[ $part ] : '' ) );
			if ( '' === $text ) {
				continue;
			}
			$tag      = self::tag( $map, $s, $part );
			$settings = array( 'title' => $text, 'header_size' => $tag );
			if ( 'title' === $part && '' !== self::url( $row['title_link'] ?? null ) ) {
				$settings['link'] = self::link( $row['title_link'] );
			}
			if ( '' !== $align ) {
				$settings['align'] = $align;
			}
			if ( 'h1' === $tag ) {
				self::code( 'ps.h1', array() );
			}
			$out[] = Builder::widget( 'heading', self::hidden( $map, $s, $part, $settings + self::copy_styles( $part, $s ) ) );
		}
		$text = (string) ( is_scalar( $row['text'] ?? null ) ? $row['text'] : '' );
		if ( '' !== trim( $text ) ) {
			$settings = array( 'editor' => $text );
			if ( '' !== $align ) {
				$settings['align'] = $align;
			}
			$settings = self::hidden( $map, $s, 'text', $settings + self::copy_styles( 'text', $s ) );
			foreach ( (array) ( $s['text_hide_on'] ?? array() ) as $device ) {
				if ( in_array( $device, array( 'desktop', 'tablet', 'mobile' ), true ) ) {
					$settings[ 'hide_' . $device ] = 'hidden-' . $device;
				}
			}
			$out[] = Builder::widget( 'text-editor', $settings );
		}
		$label = trim( (string) ( is_scalar( $row['button_text'] ?? null ) ? $row['button_text'] : '' ) );
		if ( '' !== $label && '' !== self::url( $row['button_link'] ?? null ) ) {
			$settings = array( 'text' => $label, 'link' => self::link( $row['button_link'] ) );
			if ( '' !== $align ) {
				$settings['align'] = array( 'start' => 'left', 'center' => 'center', 'end' => 'right' )[ $align ];
			}
			$out[] = Builder::widget( 'button', self::hidden( $map, $s, 'button', $settings + self::copy_styles( 'button', $s ) ) );
		}
		if ( ! empty( $row['play'] ) && self::flag( $s, (string) ( $map['play']['show'] ?? '' ) ) ) {
			$texts = array(
				'google_map' => __( 'Ver el mapa', 'digitalisimo-migration' ),
				'website'    => __( 'Visitar el sitio', 'digitalisimo-migration' ),
			);
			$out[] = Builder::widget(
				'button',
				array(
					'text'          => $texts[ $row['play']['type'] ] ?? __( 'Reproducir el vídeo', 'digitalisimo-migration' ),
					'link'          => array( 'url' => $row['play']['url'], 'is_external' => 'on', 'nofollow' => '' ),
					'selected_icon' => array( 'value' => 'fas fa-play', 'library' => 'fa-solid' ),
				)
			);
			self::code( 'ps.loss.feature', array( 'name' => __( 'ventana emergente del botón de reproducción', 'digitalisimo-migration' ) ) );
		}
		$image = self::media_value( $row['image'] ?? null );
		if ( 'front' === ( $map['image'] ?? 'background' ) && $image ) {
			$out[] = Builder::widget( 'image', self::hidden( $map, $s, 'image', array( 'image' => $image, 'image_size' => 'full' ) ) );
		}
		if ( array_key_exists( 'template_id', $row ) || ! empty( $map['templates'] ) ) {
			$template = absint( $row['template_id'] ?? 0 );
			if ( $template ) {
				$out[] = Builder::widget( 'template', array( 'template_id' => (string) $template ) );
			} else {
				self::code( 'ps.review.no_template', array() );
			}
		}
		return $out;
	}

	/** Parte oculta en origen (`show_* = ''`): se genera igual, oculta en los tres dispositivos (no se pierden datos). */
	private static function hidden( array $map, array $s, string $part, array $settings ): array {
		$key = (string) ( $map['show'][ $part ] ?? '' );
		if ( '' !== $key && ! self::flag( $s, $key, true ) ) {
			foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
				$settings[ 'hide_' . $device ] = 'hidden-' . $device;
			}
		}
		return $settings;
	}

	/** ¿Se ve la parte en origen? (para decidir el destino de H). */
	private static function visible( array $map, array $s, string $part ): bool {
		$key = (string) ( $map['show'][ $part ] ?? '' );
		return '' === $key || self::flag( $s, $key, true );
	}

	/** Etiqueta de un título (h1 por defecto en Prime Slider), de una lista cerrada. */
	private static function tag( array $map, array $s, string $part ): string {
		$key = (string) ( $map['tags'][ $part ] ?? '' );
		$tag = strtolower( (string) ( '' !== $key ? ( $s[ $key ] ?? '' ) : '' ) );
		if ( ! in_array( $tag, self::HEADINGS, true ) ) {
			$tag = (string) ( $map['tag_defaults'][ $part ] ?? ( 'title' === $part ? 'h2' : 'h4' ) );
		}
		return $tag;
	}

	/* ---------------------------------------------------------------- Carrusel de logotipos (C y H con texto) */

	private static function logos( array $map, array $s, int $post_id ): ?array {
		if ( ! Builder::usable( self::LOGOS ) ) {
			return null;
		}
		$items = array();
		$texts = false;
		foreach ( self::rows( $map, $s ) as $row ) {
			$link        = '' !== self::url( $row['title_link'] ?? null ) ? $row['title_link'] : ( $row['button_link'] ?? array() );
			$description = self::plain( $row['text'] ?? '' );
			$description = '' !== $description ? $description : self::plain( $row['sub_title'] ?? '' );
			$item        = array(
				'_id'         => Builder::id(),
				'image'       => self::media_value( $row['image'] ?? null ) ?? array( 'url' => '', 'id' => '' ),
				'name'        => self::plain( $row['title'] ?? '' ),
				'description' => $description,
				'caption'     => 'yes',
			);
			if ( '' !== self::url( $link ) ) {
				$item['link'] = self::link( $link );
			}
			if ( '' !== self::plain( $row['button_text'] ?? '' ) && '' !== self::url( $link ) ) {
				$item['link_text'] = self::plain( $row['button_text'] );
				$texts             = true;
			}
			$items[] = $item;
		}
		$settings = array(
			'layout'           => 'carousel',
			'items'            => $items,
			'image_size'       => 'large',
			'show_name'        => self::visible( $map, $s, 'title' ) ? 'yes' : '',
			'show_description' => self::visible( $map, $s, 'text' ) ? 'yes' : '',
			'show_link_text'   => $texts ? 'yes' : '',
			'navigation'       => true === ( $map['nav']['arrows'] ?? null ) || self::flag( $s, is_string( $map['nav']['arrows'] ?? null ) ? $map['nav']['arrows'] : 'show_navigation_arrows', true ) ? 'arrows' : 'none',
		);
		$label = self::document_title( $post_id );
		if ( '' !== $label ) {
			$settings['label'] = $label;
		}
		$columns = (array) ( $map['columns'] ?? array() );
		foreach ( self::DEVICES as $device ) {
			$key   = (string) ( $columns['key'] ?? 'columns' ) . $device;
			$value = $s[ $key ] ?? null;
			$value = is_array( $value ) ? ( $value['size'] ?? null ) : $value;
			if ( null !== $value && '' !== $value && is_numeric( $value ) ) {
				$settings[ 'columns' . $device ] = (string) max( 1, min( 6, (int) round( (float) $value ) ) );
			} elseif ( '' === $device && isset( $columns['default'] ) ) {
				$settings['columns'] = (string) $columns['default'];
			}
		}
		$gap = $s[ (string) ( $columns['gap'] ?? 'item_gap' ) ] ?? null;
		if ( is_array( $gap ) && isset( $gap['size'] ) && is_numeric( $gap['size'] ) ) {
			$settings['gap'] = array( 'unit' => 'px', 'size' => max( 0, min( 100, (int) $gap['size'] ) ), 'sizes' => array() );
		}
		return Builder::widget( self::LOGOS, $settings );
	}

	/* ---------------------------------------------------------------- Sólo imágenes (H) */

	private static function media( array $map, array $s ): ?array {
		if ( ! Builder::usable( self::MEDIA ) ) {
			return null;
		}
		$slides = array();
		foreach ( self::rows( $map, $s ) as $row ) {
			$image = self::media_value( $row['image'] ?? null );
			if ( ! $image ) {
				continue;
			}
			$slide = array( '_id' => Builder::id(), 'type' => 'image', 'image' => $image, 'image_link_to_type' => '' );
			$link  = '' !== self::url( $row['title_link'] ?? null ) ? $row['title_link'] : null;
			if ( $link ) {
				$slide['image_link_to_type'] = 'custom';
				$slide['image_link_to']      = self::link( $link );
			}
			$slides[] = $slide;
		}
		$auto  = (array) ( $map['autoplay'] ?? array() );
		$delay = null;
		foreach ( (array) ( $auto['delay'] ?? array( 'autoplay_delay', 'autoplay_speed' ) ) as $key ) {
			$delay = self::number( $s[ $key ] ?? null );
			if ( null !== $delay ) {
				break;
			}
		}
		$settings = array(
			'skin'           => (string) ( $map['skin'] ?? 'carousel' ),
			'slides'         => $slides,
			'effect'         => 'slide',
			'autoplay'       => 'yes' === ( $s[ $auto['on'] ?? 'autoplay' ] ?? '' ) ? 'yes' : '',
			'loop'           => 'yes' === ( $s[ $auto['loop'] ?? 'loop' ] ?? '' ) ? 'yes' : '',
			'pause_on_hover' => 'yes' === ( $s[ $auto['hover'] ?? 'pause_on_hover' ] ?? '' ) ? 'yes' : '',
			'show_arrows'    => true === ( $map['nav']['arrows'] ?? null ) || self::flag( $s, is_string( $map['nav']['arrows'] ?? null ) ? $map['nav']['arrows'] : '' ) ? 'yes' : '',
			'pagination'     => true === ( $map['nav']['dots'] ?? null ) || self::flag( $s, is_string( $map['nav']['dots'] ?? null ) ? $map['nav']['dots'] : '' ) ? 'bullets' : '',
		);
		if ( null !== $delay ) {
			$settings['autoplay_speed'] = (int) max( 1000, min( 20000, $delay ) );
		}
		$speed = self::number( $s[ $map['speed'] ?? 'speed' ] ?? null );
		if ( null !== $speed ) {
			$settings['speed'] = (int) max( 0, min( 3000, $speed ) );
		}
		$per_view = self::number( $s[ $map['per_view'] ?? 'slides_per_view' ] ?? null );
		if ( null !== $per_view && 'carousel' === $settings['skin'] ) {
			$settings['slides_per_view'] = (string) max( 1, min( 10, (int) round( $per_view ) ) );
		}
		return Builder::widget( self::MEDIA, $settings );
	}

	/* ---------------------------------------------------------------- Pestañas (J) */

	private static function tabs( array $map, array $s ): ?array {
		if ( ! Builder::usable( self::TABS ) ) {
			return null;
		}
		$tabs   = array();
		$active = (int) ( $s[ $map['active'] ?? '' ] ?? 1 );
		foreach ( self::rows( $map, $s ) as $index => $row ) {
			$image   = self::media_value( $row['image'] ?? null );
			$content = $image ? '<p><img src="' . esc_url( $image['url'] ) . '" alt=""' . ( ! empty( $image['id'] ) ? ' class="wp-image-' . absint( $image['id'] ) . '"' : '' ) . ' /></p>' : '';
			$content .= (string) ( is_scalar( $row['text'] ?? null ) ? $row['text'] : '' );
			$tab      = array(
				'_id'          => Builder::id(),
				'tab_title'    => self::plain( $row['title'] ?? '' ),
				'icon_type'    => 'none',
				'content_type' => 'content',
				'tab_content'  => $content,
			);
			if ( '' !== self::plain( $row['sub_title'] ?? '' ) ) {
				$tab['tab_sub_title'] = self::plain( $row['sub_title'] );
			}
			if ( '' !== self::url( $row['title_link'] ?? null ) && '' !== $tab['tab_title'] ) {
				$tab['tabs_button'] = $tab['tab_title'];
				$tab['button_link'] = self::link( $row['title_link'] );
			}
			if ( $index + 1 === $active ) {
				$tab['active'] = 'yes';
			}
			$tabs[] = $tab;
		}
		$settings = array( 'style' => 'cards', 'tabs' => $tabs );
		$columns  = self::number( $s[ $map['columns']['key'] ?? 'columns' ] ?? null );
		if ( null !== $columns ) {
			$settings['columns'] = (string) max( 1, min( 6, (int) $columns ) );
		}
		return Builder::widget( self::TABS, $settings );
	}

	/* ---------------------------------------------------------------- Pantalla completa en un contenedor (K) */

	private static function screens( array $map, array $s ): array {
		$children = array();
		foreach ( self::rows( $map, $s ) as $i => $row ) {
			$settings = array(
				'_title'               => Builder::slide_title( $i + 1 ),
				'content_width'        => 'full',
				'min_height'           => array( 'unit' => 'vh', 'size' => 100, 'sizes' => array() ),
				'flex_direction'       => 'column',
				'flex_justify_content' => 'center',
			);
			$widgets = self::slide_widgets( array( 'image' => 'none' ) + $map, $s, $row );
			if ( ! empty( $map['halves'] ) ) {
				// `multiscroll`: dos mitades en fila (texto a la izquierda, imagen a la derecha) con sus fondos.
				$left  = self::half( $row['left'] ?? array(), $widgets );
				$image = self::media_value( $row['image'] ?? null );
				$right = self::half( $row['right'] ?? array(), $image ? array( Builder::widget( 'image', array( 'image' => $image, 'image_size' => 'full' ) ) ) : array() );
				$settings['flex_direction'] = 'row';
				$children[]                 = Builder::container( $settings, array( $left, $right ) );
				continue;
			}
			$image = self::media_value( $row['image'] ?? null );
			if ( $image ) {
				$settings['background_background'] = 'classic';
				$settings['background_image']      = $image;
				$settings['background_size']       = 'cover';
			}
			$children[] = Builder::container( $settings, $widgets );
		}
		return Builder::container( array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0' ) ), $children );
	}

	/** Mitad de `multiscroll` con su fondo de color o imagen. */
	private static function half( array $background, array $widgets ): array {
		$settings = array( 'content_width' => 'full', 'width' => array( 'unit' => '%', 'size' => 50, 'sizes' => array() ), 'flex_direction' => 'column', 'flex_justify_content' => 'center' );
		$image    = self::media_value( $background['image'] ?? null );
		$color    = self::color( $background['color'] ?? '' );
		if ( 'image' === ( $background['type'] ?? '' ) && $image ) {
			$settings['background_background'] = 'classic';
			$settings['background_image']      = $image;
			$settings['background_size']       = 'cover';
		} elseif ( null !== $color ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $color;
		}
		return Builder::container( $settings, $widgets );
	}

	/* ---------------------------------------------------------------- Hermanos (F8-D16) */

	/** Widgets antes del principal: el contenido fijo de `reveal`. */
	private static function before( array $map, array $s ): array {
		$out    = array();
		$static = (array) ( $map['static'] ?? array() );
		if ( ! $static || ! self::flag( $s, (string) ( $static['show'] ?? '' ) ) ) {
			return $out;
		}
		$title = trim( (string) ( $s[ $static['title'] ?? '' ] ?? '' ) );
		$text  = (string) ( $s[ $static['text'] ?? '' ] ?? '' );
		if ( '' !== $title ) {
			$out[] = Builder::widget( 'heading', array( 'title' => $title, 'header_size' => 'h2' ) );
		}
		if ( '' !== trim( $text ) ) {
			$out[] = Builder::widget( 'text-editor', array( 'editor' => $text ) );
		}
		if ( $out ) {
			self::code( 'ps.reveal.static', array( 'count' => count( $out ) ) );
		}
		return $out;
	}

	/** Widgets después del principal: redes sociales (sin las del proveedor) y «Scroll Down». */
	private static function after( array $map, array $s ): array {
		$out    = array();
		$social = (array) ( $map['social'] ?? array() );
		if ( $social && self::flag( $s, (string) ( $social['show'] ?? '' ) ) && Builder::usable( self::SOCIAL ) ) {
			$list = array();
			foreach ( (array) ( $s[ $social['list'] ?? 'social_link_list' ] ?? array() ) as $row ) {
				$url = is_array( $row ) ? self::url( $row['social_icon_link'] ?? null ) : '';
				if ( '' === $url || Sanitizer::vendor_profile( $url ) ) {
					continue;
				}
				$icon   = is_array( $row['social_icon'] ?? null ) && ! empty( $row['social_icon']['value'] ) ? $row['social_icon'] : self::icon_for( $url );
				$list[] = array(
					'_id'         => Builder::id(),
					'social_icon' => $icon,
					'link'        => self::link( $row['social_icon_link'] ),
				);
			}
			if ( $list ) {
				$out[] = Builder::widget( self::SOCIAL, array( 'social_icon_list' => $list ) );
			}
		}
		$scroll = (array) ( $map['scroll'] ?? array() );
		if ( $scroll && self::flag( $s, (string) ( $scroll['show'] ?? '' ) ) && Builder::usable( self::SCROLL ) ) {
			$section = trim( (string) ( $s[ $scroll['section'] ?? 'section_id' ] ?? '' ) );
			$section = ltrim( $section, '#' );
			if ( '' !== $section && 'my-header' !== $section && 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,80}$/', $section ) ) {
				$settings = array( 'section_id' => $section );
				$text     = trim( (string) ( $s[ $scroll['text'] ?? 'scroll_button_text' ] ?? '' ) );
				if ( '' !== $text ) {
					$settings['scroll_button_text'] = $text;
				}
				$icon = $s[ $scroll['icon'] ?? '' ] ?? null;
				if ( is_array( $icon ) && ! empty( $icon['value'] ) && is_string( $icon['value'] ) ) {
					$settings['button_icon'] = array( 'value' => $icon['value'], 'library' => (string) ( $icon['library'] ?? '' ) );
				}
				foreach ( array( 'duration', 'offset' ) as $key ) {
					$value = $s[ $key ] ?? null;
					if ( is_array( $value ) && isset( $value['size'] ) && is_numeric( $value['size'] ) ) {
						$settings[ $key ] = array( 'unit' => 'px', 'size' => 0 + $value['size'] );
					}
				}
				$out[] = Builder::widget( self::SCROLL, $settings );
			}
		}
		return $out;
	}

	/** Icono de Font Awesome por el dominio del enlace (sin icono guardado). */
	private static function icon_for( string $url ): array {
		$host  = strtolower( (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( 0 === strpos( $url, '//' ) ? 'https:' . $url : $url, PHP_URL_HOST ) : parse_url( $url, PHP_URL_HOST ) ) );
		$icons = array(
			'facebook'  => 'fab fa-facebook-f',
			'twitter'   => 'fab fa-x-twitter',
			'x.com'     => 'fab fa-x-twitter',
			'instagram' => 'fab fa-instagram',
			'linkedin'  => 'fab fa-linkedin-in',
			'youtube'   => 'fab fa-youtube',
			'pinterest' => 'fab fa-pinterest-p',
			'tiktok'    => 'fab fa-tiktok',
			'github'    => 'fab fa-github',
			'dribbble'  => 'fab fa-dribbble',
			'behance'   => 'fab fa-behance',
			'vimeo'     => 'fab fa-vimeo-v',
			'whatsapp'  => 'fab fa-whatsapp',
			'telegram'  => 'fab fa-telegram',
		);
		foreach ( $icons as $needle => $icon ) {
			if ( false !== strpos( $host, $needle ) ) {
				return array( 'value' => $icon, 'library' => 'fa-brands' );
			}
		}
		return array( 'value' => 'fas fa-link', 'library' => 'fa-solid' );
	}

	/* ---------------------------------------------------------------- Estilos */

	/** Estilos guardados en el original que tienen equivalente en la parte (§3.E.0.7), con sus globales. */
	private static function copy_styles( string $part, array $s ): array {
		$out = array();
		foreach ( self::STYLES[ $part ] ?? array() as $rule ) {
			list( $from, $to, $prefix ) = $rule;
			foreach ( self::$original as $key => $value ) {
				$key = (string) $key;
				if ( $prefix ? 0 !== strpos( $key, $from ) : ! self::same_base( $key, $from ) ) {
					continue;
				}
				$target = $prefix ? $to . substr( $key, strlen( $from ) ) : $to . substr( $key, strlen( $from ) );
				if ( isset( $out[ $target ] ) ) {
					continue;
				}
				if ( false !== strpos( $to, 'color' ) || 'nav_background' === $to ) {
					$value = self::color( $value );
					if ( null === $value ) {
						continue;
					}
				} elseif ( is_string( $value ) && class_exists( Css_Value::class ) && '' !== $value && ! Css_Value::safe( $value ) ) {
					continue;
				}
				$out[ $target ]          = $value;
				self::$consumed[ $key ] = true;
			}
			if ( isset( self::$original['__globals__'] ) && is_array( self::$original['__globals__'] ) ) {
				foreach ( self::$original['__globals__'] as $key => $value ) {
					$key = (string) $key;
					if ( $prefix ? 0 === strpos( $key, $from ) : $key === $from ) {
						$out['__globals__'][ $to . substr( $key, strlen( $from ) ) ] = $value;
						self::$consumed[ $key ] = true;
					}
				}
			}
		}
		return $out;
	}

	/** ¿La clave es `$base` o una variante responsiva suya? */
	private static function same_base( string $key, string $base ): bool {
		return $key === $base || 1 === preg_match( '/^' . preg_quote( $base, '/' ) . '_(?:widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', $key );
	}

	/* ---------------------------------------------------------------- Utilidades */

	/** Anota un código para el informe (sin repetir). */
	public static function code( string $code, array $detail ): void {
		foreach ( self::$codes as $item ) {
			if ( $item[0] === $code && $item[1] === $detail ) {
				return;
			}
		}
		self::$codes[] = array( $code, $detail );
	}

	/** ¿Interruptor encendido? Ausente: `$missing`. */
	private static function flag( array $s, string $key, bool $missing = false ): bool {
		if ( '' === $key ) {
			return $missing;
		}
		return array_key_exists( $key, $s ) ? 'yes' === $s[ $key ] : $missing;
	}

	/** URL de un enlace, una imagen o una cadena. */
	public static function url( $value ): string {
		$url = is_array( $value ) ? ( $value['url'] ?? '' ) : $value;
		return is_string( $url ) ? trim( $url ) : '';
	}

	/** Enlace de Elementor con sus partes (sin atributos personalizados que no sean texto). */
	private static function link( $value ): array {
		$value = is_array( $value ) ? $value : array( 'url' => (string) $value );
		$out   = array(
			'url'         => self::url( $value ),
			'is_external' => ! empty( $value['is_external'] ) ? 'on' : '',
			'nofollow'    => ! empty( $value['nofollow'] ) ? 'on' : '',
		);
		if ( isset( $value['custom_attributes'] ) && is_string( $value['custom_attributes'] ) && '' !== $value['custom_attributes'] ) {
			$out['custom_attributes'] = $value['custom_attributes'];
		}
		return $out;
	}

	/** MEDIA `{id, url}` (null sin imagen). */
	private static function media_value( $value ): ?array {
		if ( ! is_array( $value ) || '' === self::url( $value ) ) {
			return null;
		}
		$out = array( 'url' => self::url( $value ), 'id' => isset( $value['id'] ) && '' !== $value['id'] ? absint( $value['id'] ) : '' );
		if ( isset( $value['alt'] ) && is_string( $value['alt'] ) ) {
			$out['alt'] = $value['alt'];
		}
		$out['source'] = 'library';
		return $out;
	}

	/** Color validado (Css_Value o, sin él, la misma forma: hexadecimal, rgb/hsl, `var(--…)`), o null. */
	private static function color( $value ): ?string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		if ( class_exists( Css_Value::class ) ) {
			return Css_Value::color( $value );
		}
		$value = trim( $value );
		return 1 === preg_match( '/^(#[0-9a-f]{3,8}|(?:rgba?|hsla?)\(\s*[0-9.,%\s]+\)|var\(--[a-z0-9_-]+\)|currentcolor|transparent)$/i', $value ) ? $value : null;
	}

	/** Alineación de Prime Slider (left/center/right) → start/center/end. */
	private static function align( $value ): string {
		return array( 'left' => 'start', 'center' => 'center', 'right' => 'end', 'start' => 'start', 'end' => 'end' )[ is_string( $value ) ? $value : '' ] ?? '';
	}

	/** Número de un valor (o de su `size`), o null. */
	private static function number( $value ): ?float {
		if ( is_array( $value ) ) {
			$value = $value['size'] ?? null;
		}
		return null !== $value && '' !== $value && is_numeric( $value ) ? (float) $value : null;
	}

	/** Texto plano (sin etiquetas). */
	private static function plain( $value ): string {
		return is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
	}

	/** Título del documento (o de su entrada si es una revisión) como nombre accesible ('' si no hay). */
	private static function document_title( int $post_id ): string {
		if ( $post_id <= 0 || ! function_exists( 'get_the_title' ) ) {
			return '';
		}
		$parent = function_exists( 'wp_is_post_revision' ) ? (int) wp_is_post_revision( $post_id ) : 0;
		return self::plain( get_the_title( $parent ? $parent : $post_id ) );
	}
}
