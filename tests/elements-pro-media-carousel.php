<?php
/**
 * Carrusel multimedia (`digitalisimo-media-carousel`, C1 de docs/PRO_WIDGETS_OBJECTIVE.md, frente M de docs/pro/c1.md),
 * el widget propio que sustituye al Media Carousel de PRO, y su mapa `modules/digitalisimo-convert/maps/media-carousel.php`:
 * - registro y recursos (motor `digi-crs` y su hoja, ≤ 4 KB, sin script propio, movimiento reducido, foco visible, sin
 *   `--e-global-*`, `url()` ni clases del proveedor);
 * - un control propio por cada control de PRO (del widget y de su base `carousel/widgets/base.php`), con el mismo
 *   nombre, valor por defecto, opciones y condiciones; cobertura del mapa;
 * - marcado del servidor de las tres presentaciones (carrusel, slideshow con miniaturas sincronizadas, coverflow), los
 *   enlaces y el lightbox del núcleo, la superposición, la carga diferida y el escape;
 * - vídeo: imagen del sitio con icono de reproducir y el reproductor sólo en `data-elementor-lightbox-video` (ningún
 *   iframe, ninguna imagen ni recurso de terceros en el marcado); sin imagen, la portada de la fachada del sitio;
 * - conversión con `Converter::tree()` de ajustes reales de PRO (por defecto, slideshow, coverflow, vídeo, estilos,
 *   `__dynamic__`, `__globals__`, CSS personalizado con clases de PRO): ningún `pro.unmapped`, cada clave cae en un
 *   control propio y lo convertido se pinta.
 */
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style';
	}
	class Repeater {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Widget_Base {
		public $settings   = array();
		public $controls   = array();
		public $groups     = array();
		public $sections   = array();
		public $injections = array();
		public $id         = 'abc1234';
		private $attrs     = array();
		private $section   = '';
		public function get_id() { return $this->id; }
		public function get_settings( $key = null ) { $all = $this->get_settings_for_display(); return null === $key ? $all : ( $all[ $key ] ?? null ); }
		public function get_settings_for_display() {
			$defaults = array();
			foreach ( $this->controls as $name => $control ) {
				if ( array_key_exists( 'default', $control ) ) {
					$defaults[ $name ] = $control['default'];
				}
				foreach ( array( 'tablet', 'mobile' ) as $device ) {
					if ( array_key_exists( $device . '_default', $control ) ) {
						$defaults[ $name . '_' . $device ] = $control[ $device . '_default' ];
					}
				}
			}
			foreach ( $this->groups as $name => $group ) {
				if ( 'image-size' === $group['type'] ) {
					$defaults[ $name . '_size' ] = $group['default'] ?? 'full';
				}
			}
			return $this->settings + $defaults;
		}
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() {}
		public function start_injection( $args ) { $this->injections[] = $args; }
		public function end_injection() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section ); }
		public function add_responsive_control( $name, $args ) {
			if ( isset( $args['default'] ) && ! isset( $args['desktop_default'] ) ) {
				$args['desktop_default'] = $args['default'];
			}
			if ( isset( $args['desktop_default'] ) ) {
				$args['default'] = $args['desktop_default'];
			}
			$this->add_control( $name, $args + array( 'responsive' => true ) );
		}
		public function update_control( $name, $args, $options = array() ) {
			if ( ! empty( $options['recursive'] ) ) {
				$this->controls[ $name ] = array_replace_recursive( $this->controls[ $name ], $args );
			} else {
				$this->controls[ $name ] = array_merge( $this->controls[ $name ], $args );
			}
		}
		public function update_responsive_control( $name, $args, $options = array() ) {
			if ( isset( $args['desktop_default'] ) ) {
				$args['default'] = $args['desktop_default'];
			}
			$this->update_control( $name, $args, $options );
		}
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v, $overwrite );
				}
				return $this;
			}
			$value = is_array( $value ) ? $value : array( $value );
			$this->attrs[ $element ][ $key ] = $overwrite ? $value : array_merge( $this->attrs[ $element ][ $key ] ?? array(), $value );
			return $this;
		}
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . esc_attr( implode( ' ', $values ) ) . '"';
			}
			return implode( ' ', $out );
		}
		public function add_link_attributes( $element, array $url ) {
			$this->add_render_attribute( $element, 'href', (string) ( $url['url'] ?? '' ) );
			if ( ! empty( $url['is_external'] ) ) {
				$this->add_render_attribute( $element, 'target', '_blank' );
			}
			if ( ! empty( $url['nofollow'] ) ) {
				$this->add_render_attribute( $element, 'rel', 'nofollow' );
			}
			return $this;
		}
		public function add_lightbox_data_attributes( $element, $id = null, $key = null, $group = null ) {
			$attrs = array( 'data-elementor-open-lightbox' => 'yes' );
			if ( $group ) {
				$attrs['data-elementor-lightbox-slideshow'] = $group;
			}
			if ( $id && isset( $GLOBALS['mc_posts'][ $id ] ) ) {
				$attrs['data-elementor-lightbox-title'] = $GLOBALS['mc_posts'][ $id ]->post_title;
			}
			$attrs['data-e-action-hash'] = '#elementor-action%3Aaction%3Dlightbox';
			return $this->add_render_attribute( $element, $attrs );
		}
	}
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_src( $id, $key, $settings ) { return $id ? 'https://sitio.test/wp-content/uploads/custom-' . $id . '.png' : ''; }
	}
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Utils { public static function get_placeholder_image_src() { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png'; } }
	class Control_Media {
		public static function get_image_alt( $image ) {
			$post = ! empty( $image['id'] ) ? ( $GLOBALS['mc_posts'][ $image['id'] ] ?? null ) : null;
			return $post ? ( '' !== $post->alt ? $post->alt : $post->post_title ) : (string) ( $image['alt'] ?? '' );
		}
	}
	class Embed {
		public static function get_embed_url( $url, array $params = array(), array $options = array() ) {
			if ( preg_match( '~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
				return 'https://www.youtube' . ( ! empty( $options['privacy'] ) ? '-nocookie' : '' ) . '.com/embed/' . $m[1] . '?feature=oembed&' . http_build_query( $params );
			}
			if ( preg_match( '~vimeo\.com/(\d+)~', $url, $m ) ) {
				return 'https://player.vimeo.com/video/' . $m[1] . '?' . http_build_query( $params );
			}
			return null;
		}
	}
}
namespace Digitalisimo\Elements {
	class Video_Player_Widget {
		public static function embed_parts( $url ) {
			return preg_match( '~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ? array( 'service' => 'youtube', 'id' => $m[1] ) : array();
		}
	}
}
namespace Digitalisimo\Elements\Front {
	class Facades { public static function active() { return ! empty( $GLOBALS['mc_facades'] ); } }
	class Facade_Images {
		public static function video( $service, $id ) { return array( 'kind' => 'video', 'service' => $service, 'id' => $id ); }
		public static function url( array $job ) { return 'https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/' . $job['service'] . '-' . $job['id'] . '.webp'; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['mc_posts'] = array(
		11 => (object) array( 'ID' => 11, 'post_type' => 'attachment', 'post_mime_type' => 'image/png', 'post_title' => 'Faro al amanecer', 'post_excerpt' => 'Leyenda del faro', 'post_content' => 'Descripción <script>x()</script><em>larga</em>', 'alt' => 'Un faro' ),
		12 => (object) array( 'ID' => 12, 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_title' => 'Bosque', 'post_excerpt' => '', 'post_content' => '', 'alt' => '' ),
		13 => (object) array( 'ID' => 13, 'post_type' => 'page', 'post_mime_type' => '', 'post_title' => 'Página privada', 'post_excerpt' => 'secreto', 'post_content' => '', 'alt' => '' ),
	);
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|#)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function is_rtl() { return ! empty( $GLOBALS['mc_rtl'] ); }
	function get_post( $id ) { return $GLOBALS['mc_posts'][ $id ] ?? null; }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		if ( ! isset( $GLOBALS['mc_posts'][ $id ] ) || 'attachment' !== $GLOBALS['mc_posts'][ $id ]->post_type ) {
			return '';
		}
		$attr += array( 'loading' => 'lazy' );
		$out = '<img width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-' . $id . '-' . $size . '.png"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return $out . ' srcset="https://sitio.test/wp-content/uploads/img-' . $id . '-768.png 768w" sizes="(max-width: 1200px) 100vw, 1200px">';
	}
	function check_mc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-carousel/class-engine.php';
	require $root . 'modules/digitalisimo-carousel/class-module.php';
	require $root . 'modules/digitalisimo-carousel/class-base-widget.php';
	require $root . 'modules/digitalisimo-carousel/widgets/class-media-carousel.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Carousel\Module as Carousel_Module;
	use Digitalisimo\Elements\Carousel\Widgets\Media_Carousel;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$widget = static function ( array $settings = array() ) {
		$w = new Media_Carousel();
		( new ReflectionMethod( $w, 'register_controls' ) )->invoke( $w );
		$w->settings = $settings;
		return $w;
	};
	$render = static function ( array $settings ) use ( $widget ) {
		$w = $widget( $settings );
		ob_start();
		( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
		return (string) ob_get_clean();
	};
	$img = static function ( $n, array $extra = array() ) {
		return $extra + array( '_id' => 'r' . $n, 'image' => array( 'id' => $n, 'url' => 'https://sitio.test/wp-content/uploads/img-' . $n . '.png' ) );
	};
	$count = static function ( $needle, $html ) { return substr_count( $html, $needle ); };

	// 1. Registro y recursos.
	$w = $widget();
	check_mc( 'digitalisimo-media-carousel' === $w->get_name() && 'Carrusel multimedia' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories(), 'Nombre, título y categoría propios.' );
	check_mc( array( 'digitalisimo-carousel', 'digitalisimo-media-carousel' ) === $w->get_style_depends() && array( 'digitalisimo-carousel' ) === $w->get_script_depends(), 'Sólo el motor y su hoja; sin script propio: ' . implode( ',', $w->get_style_depends() ) . ' / ' . implode( ',', $w->get_script_depends() ) );
	check_mc( 'widgets/class-media-carousel.php' === Carousel_Module::WIDGETS['digitalisimo-media-carousel']['file'] && Media_Carousel::class === Carousel_Module::WIDGETS['digitalisimo-media-carousel']['class'], 'El módulo de carruseles registra la clase.' );
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-carousel/assets/css/media-carousel.css' );
	check_mc( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_mc( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, 'elementor' ) && ! str_contains( $css, 'swiper' ), 'La hoja no usa el kit, url(), ni clases del proveedor o de Swiper.' );
	check_mc( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^}]*\.digi-media-carousel__overlay[^}]*\{transition:none\}\}/', $css ) && str_contains( $css, '.digi-media-carousel__link:focus-visible{outline:2px solid' ), 'Movimiento reducido en las transiciones y foco visible propio del enlace.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_mc( array() === array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), array( '#fff' ) ), 'Sólo los colores que PRO usaba por defecto: ' . implode( ', ', $colors[0] ) );
	foreach ( array( 'rgba(105,114,125,.8)', 'rgba(0,0,0,.3)', '--digi-crs-height:230px', '--digi-crs-height:450px', 'aspect-ratio:21/9', 'font-size:100px', 'padding:20px', '21px' ) as $look ) {
		check_mc( str_contains( $css, $look ), "La hoja reproduce el aspecto de PRO ($look)." );
	}
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-carousel/widgets/class-media-carousel.php' );
	check_mc( ! preg_match( '/jquery\(|[\x27"]swiper|(?<!elementor-)swiper-|wp_enqueue_script|Icons_Manager|<iframe|fa-solid|font-awesome/i', $source ), 'Sin jQuery, Swiper, iframes ni fuentes de iconos (las clases del lightbox del núcleo sí).' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-carousel/widgets/class-media-carousel.php', 'modules/digitalisimo-carousel/assets/css/media-carousel.css', 'modules/digitalisimo-convert/maps/media-carousel.php' ) as $file ) {
		check_mc( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo que la guarda del empaquetado prohíbe." );
	}

	// 2. Un control propio por cada control de PRO (widget y base), con el mismo valor por defecto, opciones y condiciones.
	$map = require $root . 'modules/digitalisimo-convert/maps/media-carousel.php';
	$pro = (string) file_get_contents( $root . 'modules/carousel/widgets/media-carousel.php' ) . (string) file_get_contents( $root . 'modules/carousel/widgets/base.php' );
	preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $pro, $pro_controls );
	preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $pro, $pro_groups );
	$repeater_fields = array_keys( $w->controls['slides']['fields'] );
	$declared        = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ), $map['repeaters']['slides']['keep'] );
	foreach ( $pro_controls[1] as $name ) {
		check_mc( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
		check_mc( isset( $w->controls[ $name ] ) || in_array( $name, $repeater_fields, true ), "$name: sin control propio." );
	}
	foreach ( $pro_groups[1] as $name ) {
		check_mc( isset( $map['groups'][ $name ], $w->groups[ $map['groups'][ $name ] ] ), "$name: sin grupo propio." );
	}
	foreach ( $map['keep'] as $name ) {
		check_mc( isset( $w->controls[ $name ] ), "keep $name: no es un control del Carrusel multimedia." );
	}
	foreach ( $map['ui'] as $name ) {
		check_mc( 'heading' === $w->controls[ $name ]['type'], "$name: encabezado del panel." );
	}
	$pro_unique = array_unique( $pro_controls[1] );
	check_mc( count( $pro_unique ) + count( $pro_groups[1] ) === count( $w->controls ) + count( $w->groups ) + count( $repeater_fields ), 'Exactamente un control por cada control de PRO (' . count( $pro_unique ) . ' + ' . count( $pro_groups[1] ) . ' grupos).' );
	check_mc( array( 'type', 'image', 'image_link_to_type', 'image_link_to', 'video' ) === $repeater_fields, 'Campos del repetidor en el orden de PRO.' );
	$def = static function ( $name ) use ( $w ) { return $w->controls[ $name ]['default'] ?? null; };
	foreach ( array( 'skin' => 'carousel', 'effect' => 'slide', 'show_arrows' => 'yes', 'pagination' => 'bullets', 'speed' => 500, 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'loop' => 'yes', 'pause_on_hover' => 'yes', 'pause_on_interaction' => 'yes', 'pagination_position' => 'outside', 'image_fit' => '', 'overlay' => '', 'caption' => 'title', 'icon' => 'search-plus', 'overlay_animation' => 'fade', 'thumbs_ratio' => '219' ) as $name => $value ) {
		check_mc( $value === $def( $name ), "$name: el valor por defecto es el de PRO." );
	}
	check_mc( array( 'size' => 20 ) === $def( 'arrows_size' ) && array( 'unit' => '%' ) === $def( 'width' ) && array( 'unit' => '%' ) === $def( 'lightbox_video_width' ) && 10 === $w->controls['space_between']['desktop_default']['size'] && 'full' === $w->groups['image_size']['default'], 'Flechas de 20, ancho en %, separación de 10 y tamaño de imagen completo, como en PRO.' );
	check_mc( '3' === $def( 'slides_per_view' ) && '2' === $w->controls['slides_per_view']['tablet_default'] && '1' === $w->controls['slides_per_view']['mobile_default'] && '5' === $def( 'slideshow_slides_per_view' ) && '4' === $w->controls['slideshow_slides_per_view']['tablet_default'] && '3' === $w->controls['slideshow_slides_per_view']['mobile_default'], 'Diapositivas visibles por dispositivo: las que PRO usaba con «Predeterminado» (3/2/1 y 5/4/3 en las miniaturas).' );
	check_mc( 5 === count( $def( 'slides' ) ) && str_ends_with( $def( 'slides' )[0]['image']['url'], 'placeholder.png' ), 'Cinco diapositivas con la imagen de muestra, como PRO.' );
	$opts = static function ( $name ) use ( $w ) { return array_keys( $w->controls[ $name ]['options'] ); };
	check_mc( array( 'carousel', 'slideshow', 'coverflow' ) === $opts( 'skin' ) && array( 'slide', 'fade', 'cube' ) === $opts( 'effect' ) && array( '', 'bullets', 'fraction', 'progressbar' ) === $opts( 'pagination' ) && array( '', 'contain', 'auto' ) === $opts( 'image_fit' ) && array( '', 'text', 'icon' ) === $opts( 'overlay' ) && array( 'title', 'caption', 'description' ) === $opts( 'caption' ) && array( 'search-plus', 'plus-circle', 'eye', 'link' ) === $opts( 'icon' ) && array( 'fade', 'slide-up', 'slide-down', 'slide-right', 'slide-left', 'zoom-in' ) === $opts( 'overlay_animation' ) && array( 169, 219, 43, 11 ) === $opts( 'thumbs_ratio' ) && array( 'outside', 'inside' ) === $opts( 'pagination_position' ), 'Las mismas opciones que PRO: el mapa no traduce valores.' );
	check_mc( array( 'image', 'video' ) === array_keys( $w->controls['slides']['fields']['type']['options'] ) && array( '', 'file', 'custom' ) === array_keys( $w->controls['slides']['fields']['image_link_to_type']['options'] ) && 'image' === $w->controls['slides']['fields']['type']['default'], 'Tipo y enlace de cada diapositiva como en PRO.' );
	$cond = static function ( $name ) use ( $w ) { return $w->controls[ $name ]['condition'] ?? array(); };
	$conditions = array(
		'slides_per_view'           => array( 'effect' => 'slide', 'skin!' => 'slideshow' ),
		'slides_to_scroll'          => array( 'effect' => 'slide', 'skin!' => 'slideshow' ),
		'height'                    => array( 'skin!' => 'slideshow' ),
		'width'                     => array( 'skin!' => 'slideshow' ),
		'pagination'                => array( 'skin!' => 'slideshow' ),
		'heading_pagination'        => array( 'pagination!' => '', 'skin!' => 'slideshow' ),
		'pagination_position'       => array( 'pagination!' => '', 'skin!' => 'slideshow' ),
		'pagination_gap'            => array( 'pagination' => 'bullets', 'skin!' => 'slideshow' ),
		'pagination_size'           => array( 'pagination!' => '', 'skin!' => 'slideshow' ),
		'pagination_color'          => array( 'pagination!' => '', 'skin!' => 'slideshow' ),
		'pagination_color_inactive' => array( 'pagination!' => '' ),
		'effect'                    => array( 'skin!' => 'coverflow' ),
		'autoplay_speed'            => array( 'autoplay' => 'yes' ),
		'overlay'                   => array( 'skin!' => 'slideshow' ),
		'caption'                   => array( 'skin!' => 'slideshow', 'overlay' => 'text' ),
		'icon'                      => array( 'skin!' => 'slideshow', 'overlay' => 'icon' ),
		'overlay_animation'         => array( 'skin!' => 'slideshow', 'overlay!' => '' ),
		'caption_typography'        => null,
		'icon_size'                 => array( 'overlay' => 'icon' ),
		'slideshow_height'          => array( 'skin' => 'slideshow' ),
		'thumbs_title'              => array( 'skin' => 'slideshow' ),
		'thumbs_ratio'              => array( 'skin' => 'slideshow' ),
		'centered_slides'           => array( 'skin' => 'slideshow' ),
		'slideshow_slides_per_view' => array( 'skin' => 'slideshow' ),
		'play_icon_color'           => array(),
		'skin'                      => array(),
	);
	foreach ( $conditions as $name => $expected ) {
		if ( null === $expected ) {
			check_mc( array( 'overlay' => 'text' ) === $w->groups[ $name ]['condition'], "$name: condición de PRO." );
			continue;
		}
		check_mc( $expected == $cond( $name ), "$name: condición de PRO (" . json_encode( $cond( $name ) ) . ').' ); // phpcs:ignore -- comparación de arreglos sin orden.
	}
	check_mc( array( 'skin!' => 'slideshow', 'overlay!' => '' ) === $w->sections['section_overlay']['condition'] && 'style' === $w->sections['section_lightbox_style']['tab'], 'Secciones de la superposición y del lightbox como en PRO.' );
	check_mc( array( 'type' => 'image', 'image_link_to_type' => 'custom' ) === $w->controls['slides']['fields']['image_link_to']['condition'] && array( 'type' => 'video' ) === $w->controls['slides']['fields']['video']['condition'], 'Condiciones de los campos del repetidor como en PRO.' );
	check_mc( 'digi-media-carousel--skin-' === $w->controls['skin']['prefix_class'] && 'template' === $w->controls['skin']['render_type'], 'La presentación es una clase del envoltorio y repinta el widget.' );
	check_mc( isset( $w->controls['space_between']['selectors']['{{WRAPPER}}'], $w->controls['space_between']['selectors']['{{WRAPPER}}.digi-media-carousel--skin-slideshow .digi-media-carousel__main'] ), 'La separación sigue fijando el hueco del motor y, en «Slideshow», el margen bajo el principal.' );
	foreach ( array( 'lightbox_color', 'lightbox_ui_color', 'lightbox_ui_hover_color', 'lightbox_video_width' ) as $name ) {
		check_mc( str_contains( implode( '', array_keys( $w->controls[ $name ]['selectors'] ) ), '#elementor-lightbox-slideshow-{{ID}}' ), "$name: aplica a la ventana del lightbox del núcleo." );
	}
	check_mc( 'globals/typography?id=accent' === $w->groups['caption_typography']['global']['default'], 'Tipografía global de acento en el texto de la superposición, como en PRO.' );
	check_mc( str_contains( (string) json_encode( $w->groups['play_icon_text_shadow']['fields_options'] ), '--digi-mc-play-shadow: drop-shadow(' ) && str_contains( $css, 'var(--digi-mc-play-shadow,' ), 'La sombra del icono de reproducir llega al SVG como drop-shadow.' );
	// Cada clase propia del CSS personalizado convertido existe en el marcado, en la hoja o como clase del envoltorio.
	$markup = $render( array( 'skin' => 'slideshow', 'slides' => array( $img( 11, array( 'type' => 'video', 'video' => array( 'url' => 'https://youtu.be/aqz-KE-bpKQ' ) ) ), $img( 12 ) ) ) ) . $render( array( 'overlay' => 'icon', 'pagination' => 'progressbar', 'slides' => array( $img( 11, array( 'image_link_to_type' => 'file' ) ), $img( 12 ) ) ) ) . $render( array( 'pagination' => 'fraction', 'slides' => array( $img( 11 ), $img( 12 ) ) ) );
	foreach ( $map['classes'] as $from => $to ) {
		$class = substr( $to, 1 );
		check_mc( str_contains( $markup, $class ) || str_contains( $css, $to ) || 0 === strpos( $class, 'digi-media-carousel--skin-' ) || 'elementor-widget-digitalisimo-media-carousel' === $class || 0 === strpos( $class, 'digi-crs' ), "La clase $to (de $from) no existe en el Carrusel multimedia." );
	}

	// 3. Marcado del servidor: carrusel por defecto.
	$html = $render( array() );
	check_mc( 1 === $count( '<section ', $html ) && str_contains( $html, 'class="digi-crs digi-crs--pag-out digi-media-carousel digi-media-carousel__main"' ) && str_contains( $html, 'aria-label="Diapositivas"' ) && str_contains( $html, 'data-digi-autoplay="5000" data-digi-hover="1" data-digi-interaction="1" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="bullets"' ), 'Carrusel por defecto: motor con autoplay, bucle, pausa, puntos fuera: ' . substr( $html, 0, 400 ) );
	check_mc( 5 === $count( 'class="digi-crs__slide"', $html ) && 5 === $count( 'class="digi-crs__dot"', $html ) && 1 === $count( 'digi-crs__prev', $html ) && 1 === $count( 'digi-crs__pause', $html ) && 5 === $count( 'placeholder.png', $html ) && 0 === $count( '<a ', $html ) && ! str_contains( $html, 'digi-crs--center' ), 'Cinco diapositivas con la imagen de muestra, sin enlaces, con flechas, puntos y pausa.' );
	check_mc( 5 === $count( '<img src="https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png" alt="" decoding="async" class="digi-media-carousel__img">', $html ), 'Imagen sin adjunto: `<img>` con su URL, `decoding` y texto alternativo vacío.' );
	check_mc( ! preg_match( '/swiper|elementor-main-swiper|elementor-carousel-image|e-overlay-animation/', $html ), 'Ninguna clase de PRO ni de Swiper en el marcado.' );

	// Imágenes adjuntas, enlaces y lightbox del núcleo.
	$html = $render( array( 'autoplay' => '', 'slides' => array( $img( 11, array( 'image_link_to_type' => 'file' ) ), $img( 12, array( 'image_link_to_type' => 'custom', 'image_link_to' => array( 'url' => 'https://otro.test/x?a=1&b="2"', 'is_external' => 'on', 'nofollow' => 'on' ) ) ), $img( 11 ) ) ) );
	check_mc( str_contains( $html, '<a class="digi-media-carousel__link" href="https://sitio.test/wp-content/uploads/img-11.png" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="abc1234" data-elementor-lightbox-title="Faro al amanecer" data-e-action-hash="#elementor-action%3Aaction%3Dlightbox">' ), 'Enlace al archivo: lightbox del núcleo agrupado por el widget: ' . $html );
	check_mc( str_contains( $html, '<a class="digi-media-carousel__link" href="https://otro.test/x?a=1&amp;b=&quot;2&quot;" target="_blank" rel="nofollow noopener noreferrer">' ), 'URL personalizada externa con nofollow, noopener y escape.' );
	check_mc( 2 === $count( '<a ', $html ) && str_contains( $html, 'width="1200" height="800" src="https://sitio.test/wp-content/uploads/img-11-full.png" alt="Un faro" decoding="async" class="digi-media-carousel__img" loading="lazy" srcset=' ) && str_contains( $html, 'alt="Bosque"' ), 'Adjuntos con wp_get_attachment_image(): tamaño, srcset y el texto alternativo del adjunto (o su título).' );
	check_mc( ! str_contains( $html, 'data-digi-autoplay' ) && ! str_contains( $html, 'digi-crs__pause' ) && str_contains( $html, 'aria-live="polite"' ), 'Sin autoplay no hay pausa y la región viva es cortés.' );
	$medium = $render( array( 'lazyload' => 'yes', 'image_size_size' => 'medium', 'slides' => array( $img( 11 ), $img( 12 ) ) ) );
	check_mc( str_contains( $medium, 'img-11-medium.png' ) && 2 === $count( 'loading="lazy"', $medium ), 'Tamaño de image_size y carga diferida nativa.' );
	$custom = $render( array( 'image_size_size' => 'custom', 'image_size_custom_dimension' => array( 'width' => '300', 'height' => '200' ), 'lazyload' => 'yes', 'slides' => array( $img( 11 ) ) ) );
	check_mc( str_contains( $custom, 'src="https://sitio.test/wp-content/uploads/custom-11.png"' ) && str_contains( $custom, 'width="300"' ) && str_contains( $custom, 'loading="lazy"' ), 'Tamaño personalizado: la imagen recortada con sus medidas.' );

	// Una sola diapositiva, fundido, cubo, RTL.
	$one = $render( array( 'slides' => array( $img( 11 ) ) ) );
	check_mc( ! str_contains( $one, 'digi-crs__prev' ) && ! str_contains( $one, 'digi-crs__pagination' ) && ! str_contains( $one, 'digi-crs__pause' ) && 1 === $count( 'digi-crs__slide', $one ), 'Una diapositiva: sin flechas, paginación ni pausa.' );
	check_mc( str_contains( $render( array( 'effect' => 'fade', 'slides' => array( $img( 11 ), $img( 12 ) ) ) ), 'data-digi-effect="fade"' ) && str_contains( $render( array( 'effect' => 'cube', 'slides' => array( $img( 11 ), $img( 12 ) ) ) ), 'data-digi-effect="slide"' ), 'Fundido como fundido; cubo, aproximado como deslizamiento.' );
	$GLOBALS['mc_rtl'] = true;
	check_mc( str_contains( $render( array( 'slides' => array( $img( 11 ), $img( 12 ) ) ) ), '<div class="digi-crs__track" aria-live="off" dir="rtl">' ), 'En RTL, la pista lleva dir="rtl".' );
	$GLOBALS['mc_rtl'] = false;

	// Slideshow: principal y miniaturas sincronizadas.
	$html = $render( array( 'skin' => 'slideshow', 'pagination' => 'bullets', 'overlay' => 'icon', 'slides' => array( $img( 11, array( 'image_link_to_type' => 'file' ) ), $img( 12, array( 'type' => 'video', 'video' => array( 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) ) ), $img( 11 ) ) ) );
	$parts = explode( '</section>', $html );
	check_mc( 3 === count( $parts ) && '' === $parts[2], 'Slideshow: dos carruseles.' );
	list( $main, $thumbs ) = $parts;
	check_mc( str_contains( $main, 'class="digi-crs digi-media-carousel digi-media-carousel__main"' ) && str_contains( $main, 'id="digi-mc-abc1234"' ) && ! str_contains( $main, 'digi-crs__pagination' ) && str_contains( $main, 'digi-crs__prev' ) && str_contains( $main, 'data-digi-autoplay="5000"' ) && ! str_contains( $main, 'digi-media-carousel__overlay' ), 'Principal: con id, flechas y autoplay; sin paginación ni superposición (como PRO).' );
	check_mc( str_contains( $thumbs, 'class="digi-crs digi-media-carousel digi-media-carousel__thumbs"' ) && str_contains( $thumbs, 'data-digi-sync="#digi-mc-abc1234"' ) && str_contains( $thumbs, 'aria-label="Miniaturas: Diapositivas"' ) && ! str_contains( $thumbs, 'digi-crs__prev' ) && ! str_contains( $thumbs, 'digi-crs__pagination' ) && ! str_contains( $thumbs, 'data-digi-autoplay' ) && ! str_contains( $thumbs, 'data-digi-center' ) && ! str_contains( $thumbs, ' id=' ), 'Miniaturas: siguen al principal, sin flechas, paginación, autoplay ni centrado.' );
	check_mc( 3 === $count( 'digi-crs__slide', $thumbs ) && ! str_contains( $thumbs, '<a ' ) && ! str_contains( $thumbs, 'digi-media-carousel__play' ) && 3 === $count( '<img ', $thumbs ), 'Miniaturas: la imagen sola, sin enlaces ni icono de reproducir.' );
	check_mc( 2 === $count( '<a ', $main ) && str_contains( $main, 'data-elementor-lightbox-video="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?feature=oembed&amp;autoplay=1&amp;rel=0&amp;controls=0"' ), 'Principal: enlaces y lightbox (también del vídeo).' );
	check_mc( str_contains( $render( array( 'skin' => 'slideshow', 'centered_slides' => 'yes', 'slides' => array( $img( 11 ), $img( 12 ) ) ) ), 'digi-media-carousel__thumbs" aria-roledescription="carrusel" aria-label="Miniaturas: Diapositivas" data-digi-loop="1" data-digi-effect="slide" data-digi-center="1" data-digi-sync=' ), 'Miniaturas centradas con «Diapositivas centradas».' );
	check_mc( 1 === $count( '<section ', $render( array( 'skin' => 'slideshow', 'slides' => array( $img( 11 ) ) ) ) ), 'Slideshow de una diapositiva: sin miniaturas, como PRO.' );

	// Coverflow (aproximado: centrado).
	$html = $render( array( 'skin' => 'coverflow', 'effect' => 'fade', 'slides' => array( $img( 11 ), $img( 12 ), $img( 11 ) ) ) );
	check_mc( str_contains( $html, 'digi-crs--center' ) && str_contains( $html, 'data-digi-center="1"' ) && str_contains( $html, 'data-digi-effect="slide"' ) && str_contains( $html, 'data-digi-pagination="bullets"' ), 'Coverflow: centrado, desliza (el efecto no aplica) y con paginación.' );

	// Vídeo: imagen del sitio, icono, reproductor sólo en el atributo del lightbox.
	$html = $render( array( 'autoplay' => '', 'slides' => array( $img( 11, array( 'type' => 'video', 'video' => array( 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) ) ), array( '_id' => 'v2', 'type' => 'video', 'image' => array( 'url' => '', 'id' => '' ), 'video' => array( 'url' => 'https://vimeo.com/76979871' ) ), array( '_id' => 'v3', 'type' => 'video', 'image' => array( 'url' => '', 'id' => '' ), 'video' => array( 'url' => 'https://youtu.be/aqz-KE-bpKQ' ) ) ) ) );
	check_mc( 3 === $count( 'class="digi-media-carousel__play"', $html ) && 3 === $count( '<span class="elementor-screen-only">Reproducir el vídeo</span>', $html ) && 3 === $count( Media_Carousel::PLAY, $html ), 'Cada vídeo lleva el icono de reproducir (SVG propio) con texto oculto.' );
	check_mc( str_contains( $html, 'href="https://sitio.test/wp-content/uploads/img-11.png" data-elementor-open-lightbox="yes"' ) && 2 === $count( 'href="#" data-elementor-open-lightbox="yes"', $html ) && str_contains( $html, 'data-elementor-lightbox-video="https://player.vimeo.com/video/76979871?autoplay=1&amp;rel=0&amp;controls=0"' ), 'Un vídeo abre el lightbox desde su imagen (o `#` sin ella), como PRO.' );
	preg_match_all( '/\s(?:src|srcset|href|poster|data-src)="([^"]*)"/', $html, $urls );
	foreach ( $urls[1] as $url ) {
		check_mc( '#' === $url || 0 === strpos( $url, 'https://sitio.test/' ), "Vídeo: ninguna petición a terceros en el marcado ($url)." );
	}
	check_mc( ! preg_match( '/<iframe|<video|<script|<link|preconnect|i\.ytimg|vumbnail|youtube\.com\/embed/i', $html ), 'Vídeo: sin iframe, preconexión ni portada del servicio antes del clic.' );
	check_mc( 1 === $count( '<img ', $html ), 'Sin imagen propia ni fachadas, el vídeo no pinta imagen (como PRO).' );
	$GLOBALS['mc_facades'] = true;
	$html = $render( array( 'autoplay' => '', 'slides' => array( array( '_id' => 'v3', 'type' => 'video', 'image' => array( 'url' => '', 'id' => '' ), 'video' => array( 'url' => 'https://youtu.be/aqz-KE-bpKQ' ) ) ) ) );
	check_mc( str_contains( $html, '<img src="https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/youtube-aqz-KE-bpKQ.webp" alt="" decoding="async" class="digi-media-carousel__img">' ), 'Con las fachadas, un vídeo sin imagen muestra la portada servida por el sitio.' );
	check_mc( array( array( 'kind' => 'video', 'service' => 'youtube', 'id' => 'aqz-KE-bpKQ' ) ) === Media_Carousel::facade_jobs( array( 'slides' => array( $img( 11, array( 'type' => 'video', 'video' => array( 'url' => 'https://youtu.be/dQw4w9WgXcQ' ) ) ), array( 'type' => 'video', 'image' => array( 'url' => '' ), 'video' => array( 'url' => 'https://youtu.be/aqz-KE-bpKQ' ) ) ) ) ), 'facade_jobs(): sólo los vídeos sin imagen propia.' );
	$GLOBALS['mc_facades'] = false;

	// Superposición: texto del adjunto, icono, animaciones y escape.
	$html = $render( array( 'overlay' => 'text', 'caption' => 'title', 'overlay_animation' => 'slide-up', 'slides' => array( $img( 11 ), $img( 12 ), $img( 13 ) ) ) );
	check_mc( str_contains( $html, '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--slide-up">Faro al amanecer</div>' ) && str_contains( $html, '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--slide-up">Bosque</div>' ) && str_contains( $html, '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--slide-up"></div>' ) && ! str_contains( $html, '>Página privada</div>' ), 'Superposición con el título del adjunto; nunca el de una entrada que no es una imagen.' );
	check_mc( str_contains( $render( array( 'overlay' => 'text', 'caption' => 'caption', 'slides' => array( $img( 11 ), $img( 13 ) ) ) ), '>Leyenda del faro</div>' ) && ! str_contains( $render( array( 'overlay' => 'text', 'caption' => 'caption', 'slides' => array( $img( 11 ), $img( 13 ) ) ) ), 'secreto' ), 'Leyenda del adjunto; la de una entrada que no es imagen no se muestra.' );
	$desc = $render( array( 'overlay' => 'text', 'caption' => 'description', 'slides' => array( $img( 11 ) ) ) );
	check_mc( str_contains( $desc, '>Descripción <em>larga</em></div>' ) && ! str_contains( $desc, '<script' ), 'Descripción con el HTML permitido.' );
	$icon = $render( array( 'overlay' => 'icon', 'icon' => 'eye', 'overlay_animation' => 'bogus', 'slides' => array( $img( 11 ), $img( 12 ) ) ) );
	check_mc( 2 === $count( '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--fade">' . Media_Carousel::icon( 'eye' ) . '</div>', $icon ) && str_contains( Media_Carousel::icon( 'eye' ), 'viewBox="0 0 576 512" aria-hidden="true"' ), 'Icono propio decorativo; una animación desconocida cae en «fade».' );
	foreach ( array( 'fade', 'slide-up', 'slide-down', 'slide-right', 'slide-left', 'zoom-in' ) as $animation ) {
		check_mc( str_contains( $css, '.digi-media-carousel__overlay--' . $animation . '{' ), "La hoja anima la superposición «{$animation}»." );
	}
	check_mc( str_contains( $css, ':not(:hover):not(:focus-within) .digi-media-carousel__overlay--fade{opacity:0}' ), 'La superposición aparece con el puntero o con el foco del teclado.' );
	$evil = $render( array( 'slides_name' => '"><script>alert(1)</script>', 'slides' => array( array( '_id' => 'e', 'image' => array( 'url' => 'javascript:alert(1)' ), 'image_link_to_type' => 'file' ), $img( 12 ) ) ) );
	check_mc( ! str_contains( $evil, '<script' ) && str_contains( $evil, 'aria-label="&quot;&gt;&lt;script&gt;' ) && ! str_contains( $evil, 'javascript:' ), 'Nombre del carrusel y URL escapados.' );

	// 4. Conversión de Media Carousel de PRO con el mapa (borrador: sólo pruebas).
	Registry::reset();
	check_mc( null !== Registry::map( 'media-carousel', true ) && 'draft' === $map['status'] && 'hide' === $map['panel'] && 'digitalisimo-media-carousel' === $map['target'], 'Mapa en borrador (el coordinador lo pasa a ready tras el A/B), oculta el widget de PRO.' );
	$own    = $widget();
	$fields = array_keys( $own->controls['slides']['fields'] );
	$lands  = static function ( $key, $in_row = false ) use ( $own, $fields ) {
		$base = Translator::base( (string) $key );
		if ( Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		if ( $in_row ) {
			return in_array( $base, $fields, true );
		}
		if ( isset( $own->controls[ $base ] ) ) {
			return true;
		}
		foreach ( array_keys( $own->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$rows  = array( $img( 11, array( 'image_link_to_type' => 'file' ) ), $img( 12, array( 'image_link_to_type' => 'custom', 'image_link_to' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ) ) ), array( '_id' => 'v1', 'type' => 'video', 'image' => array( 'id' => 11, 'url' => 'https://sitio.test/wp-content/uploads/img-11.png' ), 'video' => array( 'url' => 'https://youtu.be/aqz-KE-bpKQ' ) ) );
	$cases = array(
		'defaults'  => array(),
		'carousel'  => array( 'slides' => $rows, 'slides_name' => 'Galería', 'slides_per_view' => '4', 'slides_per_view_tablet' => '2', 'slides_to_scroll' => '2', 'height' => array( 'unit' => 'px', 'size' => 300, 'sizes' => array() ), 'height_mobile' => array( 'unit' => 'px', 'size' => 200, 'sizes' => array() ), 'width' => array( 'unit' => '%', 'size' => 90, 'sizes' => array() ), 'show_arrows' => '', 'pagination' => 'fraction', 'pagination_position' => 'inside', 'speed' => 800, 'autoplay' => '', 'image_size_size' => 'medium_large', 'image_fit' => 'contain', 'space_between' => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ), 'space_between_mobile' => array( 'unit' => 'px', 'size' => 0, 'sizes' => array() ), 'slide_background_color' => '#111111', 'slide_border_size' => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ), 'slide_border_radius' => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ), 'slide_border_color' => '#eeeeee', 'slide_padding' => array( 'unit' => 'px', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => true ), 'arrows_size' => array( 'unit' => 'px', 'size' => 32, 'sizes' => array() ), 'arrows_color' => '#ff0000', 'pagination_gap' => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ), 'pagination_size' => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ), 'pagination_color_inactive' => '#cccccc', 'pagination_color' => '#000000' ),
		'slideshow' => array( 'skin' => 'slideshow', 'slides' => $rows, 'slideshow_height' => array( 'unit' => 'px', 'size' => 500, 'sizes' => array() ), 'slideshow_height_mobile' => array( 'unit' => 'vh', 'size' => 40, 'sizes' => array() ), 'thumbs_ratio' => '43', 'centered_slides' => 'yes', 'slideshow_slides_per_view' => '6', 'slideshow_slides_per_view_mobile' => '3', 'loop' => '', 'effect' => 'fade', 'lazyload' => 'yes' ),
		'coverflow' => array( 'skin' => 'coverflow', 'slides' => $rows, 'overlay' => 'text', 'caption' => 'caption', 'overlay_animation' => 'zoom-in', 'overlay_background_color' => 'rgba(0,0,0,.5)', 'overlay_color' => '#ffffff', 'caption_typography_typography' => 'custom', 'caption_typography_font_size' => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ), 'caption_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ) ),
		'video'     => array( 'slides' => $rows, 'effect' => 'cube', 'overlay' => 'icon', 'icon' => 'link', 'icon_size' => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ), 'play_icon_color' => '#ffd166', 'play_icon_size' => array( 'unit' => 'px', 'size' => 60, 'sizes' => array() ), 'play_icon_size_mobile' => array( 'unit' => 'px', 'size' => 40, 'sizes' => array() ), 'play_icon_text_shadow_text_shadow_type' => 'yes', 'play_icon_text_shadow_text_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 8, 'color' => 'rgba(0,0,0,.6)' ), 'lightbox_color' => '#000000', 'lightbox_ui_color' => '#ffffff', 'lightbox_ui_hover_color' => '#ffd166', 'lightbox_video_width' => array( 'unit' => '%', 'size' => 80, 'sizes' => array() ), 'autoplay_speed' => 3000, 'pause_on_hover' => '', 'pause_on_interaction' => '' ),
		'dynamic'   => array(
			'slides'      => array(
				array( '_id' => 'd1', 'image' => array( 'id' => '', 'url' => '' ), 'image_link_to_type' => 'custom', '__dynamic__' => array( 'image' => '[elementor-tag id="a1" name="post-featured-image" settings="%7B%7D"]', 'image_link_to' => '[elementor-tag id="a2" name="post-url" settings="%7B%7D"]' ) ),
				array( '_id' => 'd2', 'type' => 'video', '__dynamic__' => array( 'video' => '[elementor-tag id="a3" name="acf-url" settings="%7B%7D"]' ) ),
			),
			'__dynamic__' => array( 'slides_name' => '[elementor-tag id="a4" name="post-title" settings="%7B%7D"]' ),
		),
		'globals'   => array( 'slides' => $rows, 'overlay' => 'text', '__globals__' => array( 'caption_typography_typography' => 'globals/typography?id=secondary', 'overlay_background_color' => 'globals/colors?id=primary', 'overlay_color' => 'globals/colors?id=accent', 'pagination_color' => 'globals/colors?id=accent', 'pagination_color_inactive' => 'globals/colors?id=text', 'arrows_color' => 'globals/colors?id=secondary', 'slide_background_color' => 'globals/colors?id=text', 'play_icon_color' => 'globals/colors?id=primary', 'lightbox_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=text' ) ),
		'css'       => array( 'slides' => $rows, 'custom_css' => "selector .swiper-slide{border-radius:16px}\nselector .elementor-carousel-image{filter:grayscale(1)}\nselector.elementor-skin-carousel .elementor-main-swiper{height:260px}\nselector .elementor-carousel-image-overlay.e-overlay-animation-fade{opacity:.5}\nselector .elementor-custom-embed-play svg{fill:red}\n.elementor-widget-media-carousel .elementor-thumbnails-swiper{margin:0}", '_css_classes' => 'mi-galeria', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'media-carousel', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_mc( count( $cases ) === count( $log ), 'Se convierte cada Media Carousel del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_mc( 'digitalisimo-media-carousel' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa al Carrusel multimedia con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_mc( $lands( $key ), $element['id'] . ": la clave $key no cae en un control del Carrusel multimedia." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_mc( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control del Carrusel multimedia." );
			}
		}
		foreach ( (array) ( $element['settings']['slides'] ?? array() ) as $row ) {
			foreach ( $row as $key => $value ) {
				check_mc( $lands( $key, true ), $element['id'] . ": la clave slides.$key no cae en un campo del repetidor." );
			}
			foreach ( array_keys( $row['__dynamic__'] ?? array() ) as $key ) {
				check_mc( $lands( $key, true ), $element['id'] . ": slides.__dynamic__.$key no cae en un campo del repetidor." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	sort( $codes );
	// `skin` se informa en toda presentación distinta de «Carrusel» (el traductor no distingue valores): el motivo dice
	// que sólo «Coverflow» se aproxima.
	$expected = array( 'coverflow:pro.approx:skin', 'css:pro.css:custom_css', 'slideshow:pro.approx:effect', 'slideshow:pro.approx:lazyload', 'slideshow:pro.approx:loop', 'slideshow:pro.approx:skin', 'video:pro.approx:effect' );
	check_mc( $expected === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo las aproximaciones declaradas y el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_mc( 5 === count( $d['slides'] ) && str_ends_with( (string) $d['slides'][0]['image']['url'], 'placeholder.png' ) && 'image' === $d['slides'][0]['type'] && 'carousel' === $d['skin'] && 'Slides' === $d['slides_name'] && 'media-carousel' === $d['_digitalisimo_pro'], 'Un Media Carousel sin cambios llega con las cinco imágenes de muestra y el nombre de PRO.' );
	$painted = $render( $d );
	check_mc( 5 === $count( 'placeholder.png', $painted ) && str_contains( $painted, 'aria-label="Slides"' ) && str_contains( $painted, 'data-digi-autoplay="5000"' ), 'Lo convertido por defecto se pinta como PRO.' );
	$c = $out['carousel'];
	check_mc( 'Galería' === $c['slides_name'] && '4' === $c['slides_per_view'] && '2' === $c['slides_per_view_tablet'] && '2' === $c['slides_to_scroll'] && 200 === $c['height_mobile']['size'] && 90 === $c['width']['size'] && '' === $c['show_arrows'] && 'fraction' === $c['pagination'] && 'inside' === $c['pagination_position'] && 800 === $c['speed'] && 'medium_large' === $c['image_size_size'] && 'contain' === $c['image_fit'] && 0 === $c['space_between_mobile']['size'] && '#111111' === $c['slide_background_color'] && '2' === $c['slide_border_size']['top'] && 8 === $c['slide_border_radius']['size'] && 32 === $c['arrows_size']['size'] && '#ff0000' === $c['arrows_color'] && 8 === $c['pagination_gap']['size'] && 10 === $c['pagination_size']['size'] && '#cccccc' === $c['pagination_color_inactive'], 'Carrusel: vista, paso, alturas, ancho, navegación, imagen y estilos pasan a su control.' );
	$painted = $render( $c );
	check_mc( str_contains( $painted, 'aria-label="Galería"' ) && ! str_contains( $painted, 'digi-crs__prev' ) && str_contains( $painted, 'data-digi-pagination="fraction"' ) && ! str_contains( $painted, 'digi-crs--pag-out' ) && str_contains( $painted, '--digi-crs-speed:800ms' ) && str_contains( $painted, 'img-11-medium_large.png' ) && str_contains( $painted, 'href="https://a.test/"' ) && str_contains( $painted, 'data-elementor-lightbox-video="https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ' ), 'Lo convertido se pinta: nombre, sin flechas, fracción dentro, velocidad, tamaño, enlaces y vídeo.' );
	check_mc( 3 === count( $c['slides'] ) && 'r11' === $c['slides'][0]['_id'] && 'file' === $c['slides'][0]['image_link_to_type'] && 'https://a.test/' === $c['slides'][1]['image_link_to']['url'] && 'video' === $c['slides'][2]['type'] && 'https://youtu.be/aqz-KE-bpKQ' === $c['slides'][2]['video']['url'], 'Filas del repetidor con su _id, tipo, enlace y vídeo.' );
	$s = $out['slideshow'];
	check_mc( 'slideshow' === $s['skin'] && 500 === $s['slideshow_height']['size'] && 'vh' === $s['slideshow_height_mobile']['unit'] && '43' === $s['thumbs_ratio'] && 'yes' === $s['centered_slides'] && '6' === $s['slideshow_slides_per_view'] && '3' === $s['slideshow_slides_per_view_mobile'] && '' === $s['loop'] && 'fade' === $s['effect'] && 'yes' === $s['lazyload'], 'Slideshow: altura, miniaturas, proporción, centrado, bucle, efecto y carga diferida.' );
	$painted = $render( $s );
	check_mc( 2 === $count( '<section ', $painted ) && str_contains( $painted, 'data-digi-sync="#digi-mc-abc1234"' ) && str_contains( $painted, 'data-digi-center="1"' ) && str_contains( $painted, 'data-digi-effect="fade"' ) && ! str_contains( $painted, 'data-digi-loop' ), 'Slideshow convertido: principal con fundido sin bucle y miniaturas centradas sincronizadas.' );
	$cf = $out['coverflow'];
	check_mc( 'coverflow' === $cf['skin'] && 'text' === $cf['overlay'] && 'caption' === $cf['caption'] && 'zoom-in' === $cf['overlay_animation'] && 'rgba(0,0,0,.5)' === $cf['overlay_background_color'] && '#ffffff' === $cf['overlay_color'] && 'custom' === $cf['caption_typography_typography'] && 14 === $cf['caption_typography_font_size_mobile']['size'], 'Coverflow: superposición de texto, animación, colores y tipografía.' );
	check_mc( str_contains( $render( $cf ), '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--zoom-in">Leyenda del faro</div>' ), 'Coverflow convertido: la leyenda del adjunto en la superposición.' );
	$v = $out['video'];
	check_mc( 'cube' === $v['effect'] && 'icon' === $v['overlay'] && 'link' === $v['icon'] && 30 === $v['icon_size']['size'] && '#ffd166' === $v['play_icon_color'] && 40 === $v['play_icon_size_mobile']['size'] && 'yes' === $v['play_icon_text_shadow_text_shadow_type'] && 8 === $v['play_icon_text_shadow_text_shadow']['blur'] && '#000000' === $v['lightbox_color'] && '#ffd166' === $v['lightbox_ui_hover_color'] && 80 === $v['lightbox_video_width']['size'] && 3000 === $v['autoplay_speed'] && '' === $v['pause_on_hover'] && '' === $v['pause_on_interaction'], 'Vídeo: efecto, icono, icono de reproducir con sombra, lightbox y autoplay.' );
	$painted = $render( $v );
	check_mc( str_contains( $painted, 'data-digi-autoplay="3000" data-digi-loop="1"' ) && ! str_contains( $painted, 'data-digi-hover' ) && 3 === $count( Media_Carousel::icon( 'link' ), $painted ), 'Vídeo convertido: autoplay sin pausas y el icono «enlace» en la superposición.' );
	$dy = $out['dynamic'];
	check_mc( array( 'slides_name' => '[elementor-tag id="a4" name="post-title" settings="%7B%7D"]' ) === $dy['__dynamic__'] && array( 'image', 'image_link_to' ) === array_keys( $dy['slides'][0]['__dynamic__'] ) && array( 'video' ) === array_keys( $dy['slides'][1]['__dynamic__'] ) && 'video' === $dy['slides'][1]['type'] && 'image' === $dy['slides'][0]['type'], 'Las etiquetas dinámicas siguen a su control, también en las filas.' );
	check_mc( array( 'caption_typography_typography' => 'globals/typography?id=secondary', 'overlay_background_color' => 'globals/colors?id=primary', 'overlay_color' => 'globals/colors?id=accent', 'pagination_color' => 'globals/colors?id=accent', 'pagination_color_inactive' => 'globals/colors?id=text', 'arrows_color' => 'globals/colors?id=secondary', 'slide_background_color' => 'globals/colors?id=text', 'play_icon_color' => 'globals/colors?id=primary', 'lightbox_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	check_mc( "selector .digi-crs__slide{border-radius:16px}\nselector .digi-media-carousel__image{filter:grayscale(1)}\nselector.digi-media-carousel--skin-carousel .digi-media-carousel__main{height:260px}\nselector .digi-media-carousel__overlay.digi-media-carousel__overlay--fade{opacity:.5}\nselector .digi-media-carousel__play svg{fill:red}\n.elementor-widget-digitalisimo-media-carousel .digi-media-carousel__thumbs{margin:0}" === $out['css']['custom_css'] && 'mi-galeria' === $out['css']['_css_classes'] && '10' === $out['css']['_margin']['top'], 'CSS personalizado con las clases propias; Avanzado intacto: ' . $out['css']['custom_css'] );
	check_mc( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Carrusel multimedia (' . count( $w->controls ) . ' controles, ' . count( $repeater_fields ) . ' campos del repetidor, ' . count( $w->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, sin script propio) y su mapa desde el Media Carousel de PRO (' . count( $cases ) . " casos) validados.\n";
}
