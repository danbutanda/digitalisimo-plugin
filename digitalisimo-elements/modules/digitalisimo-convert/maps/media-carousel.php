<?php
/**
 * Media Carousel de PRO → Carrusel multimedia (`digitalisimo-media-carousel`,
 * modules/digitalisimo-carousel/widgets/class-media-carousel.php).
 *
 * El Carrusel multimedia repite los controles del de PRO y los de su base (`modules/carousel/widgets/base.php`) con el
 * mismo nombre, los mismos valores y las mismas condiciones, así que todo pasa con `keep`; el marcado y las clases son
 * propios (motor `digi-crs`, sin Swiper). Las aproximaciones son las del motor (efecto cubo como deslizamiento, carga
 * diferida nativa; el bucle es real desde la corrección de fidelidad) y la presentación «Coverflow», que se pinta con
 * la diapositiva actual centrada y sin el giro 3D. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_mc_placeholder = class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'get_placeholder_image_src' ) ? (string) \Elementor\Utils::get_placeholder_image_src() : '';
$digi_mc_row         = array(
	'type'               => 'image',
	'image'              => array( 'url' => $digi_mc_placeholder, 'id' => '' ),
	'image_link_to_type' => '',
	'image_link_to'      => array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
	'video'              => array( 'url' => '' ),
);
$digi_mc_classes = array(
	'.elementor-widget-media-carousel'          => '.elementor-widget-digitalisimo-media-carousel',
	'.elementor-main-swiper'                    => '.digi-media-carousel__main',
	'.elementor-thumbnails-swiper'              => '.digi-media-carousel__thumbs',
	'.elementor-carousel-image'                 => '.digi-media-carousel__image',
	'.elementor-carousel-image-overlay'         => '.digi-media-carousel__overlay',
	'.e-overlay-animation-fade'                 => '.digi-media-carousel__overlay--fade',
	'.e-overlay-animation-slide-up'             => '.digi-media-carousel__overlay--slide-up',
	'.e-overlay-animation-slide-down'           => '.digi-media-carousel__overlay--slide-down',
	'.e-overlay-animation-slide-right'          => '.digi-media-carousel__overlay--slide-right',
	'.e-overlay-animation-slide-left'           => '.digi-media-carousel__overlay--slide-left',
	'.e-overlay-animation-zoom-in'              => '.digi-media-carousel__overlay--zoom-in',
	'.elementor-custom-embed-play'              => '.digi-media-carousel__play',
	'.elementor-skin-carousel'                  => '.digi-media-carousel--skin-carousel',
	'.elementor-skin-slideshow'                 => '.digi-media-carousel--skin-slideshow',
	'.elementor-skin-coverflow'                 => '.digi-media-carousel--skin-coverflow',
);
// Clases comunes de los carruseles de PRO (Swiper, flechas y paginación) del motor propio, del frente E.
$digi_mc_common = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();

return array(
	'source'    => 'media-carousel',
	'target'    => 'digitalisimo-media-carousel',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Valores por defecto de PRO de los controles que se aproximan (para informar sólo lo que el sitio cambió) y de los
	// textos que PRO traduce al pintarlos.
	'defaults'  => array(
		'skin'           => 'carousel',
		'slides_name'    => function_exists( 'esc_html__' ) ? esc_html__( 'Slides', 'elementor-pro' ) : 'Slides',
		'effect'         => 'slide',
		'loop'           => 'yes',
		'lazyload'       => '',
		'autoplay'       => 'yes',
		'autoplay_speed' => 5000,
		'speed'          => 500,
	),
	'keep'      => array(
		// Base de los carruseles de PRO (modules/carousel/widgets/base.php).
		'slides_name',
		'effect',
		'slides_per_view',
		'slides_to_scroll',
		'height',
		'width',
		'show_arrows',
		'pagination',
		'speed',
		'autoplay',
		'autoplay_speed',
		'loop',
		'pause_on_hover',
		'pause_on_interaction',
		'lazyload',
		'space_between',
		'slide_background_color',
		'slide_border_size',
		'slide_border_radius',
		'slide_border_color',
		'slide_padding',
		'arrows_size',
		'arrows_color',
		'pagination_position',
		'pagination_gap',
		'pagination_size',
		'pagination_color_inactive',
		'pagination_color',
		// Carrusel multimedia.
		'skin',
		'image_fit',
		'play_icon_color',
		'play_icon_size',
		'overlay',
		'caption',
		'icon',
		'overlay_animation',
		'overlay_background_color',
		'overlay_color',
		'icon_size',
		'slideshow_height',
		'thumbs_ratio',
		'centered_slides',
		'slideshow_slides_per_view',
		'lightbox_color',
		'lightbox_ui_color',
		'lightbox_ui_hover_color',
		'lightbox_video_width',
	),
	// Grupos con el mismo prefijo: tamaño de imagen, sombra del icono de reproducción y tipografía del texto.
	'groups'    => array(
		'image_size'            => 'image_size',
		'play_icon_text_shadow' => 'play_icon_text_shadow',
		'caption_typography'    => 'caption_typography',
	),
	'approx'    => array(
		'skin'     => 'Sólo «Coverflow» se aproxima: se pinta con la diapositiva actual centrada y sin el giro ni la profundidad 3D de Swiper («Carrusel» y «Slideshow» no cambian).',
		'effect'   => 'El efecto «Cubo» se pinta como un deslizamiento de una diapositiva: el motor propio no tiene el giro 3D de Swiper.',
		'lazyload' => 'La carga diferida es la nativa del navegador (`loading="lazy"`) en lugar de la de Swiper, sin el indicador de carga.',
	),
	// Icono de reproducir: PRO pintaba `eicon-play` con `Icons_Manager`, y su tamaño y su sombra sólo apuntaban a la fuente
	// (`.elementor-custom-embed-play i`). Con los iconos como SVG en línea (`e_font_icon_svg`, activo por defecto desde
	// 3.17) el icono quedaba en 100 px con la sombra del núcleo, así que en un sitio con el experimento activo se descartan
	// (con su código en el informe); sin él, PRO los aplicaba a la fuente y el propio los aplica igual al SVG. El color sí
	// llegaba al SVG (`fill`).
	'filter'    => static function ( array $out, array $src, ?callable $report = null ): array {
		$plugin      = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$experiments = is_object( $plugin ) && isset( $plugin->experiments ) && is_object( $plugin->experiments ) ? $plugin->experiments : null;
		if ( ! $experiments || ! method_exists( $experiments, 'is_feature_active' ) || ! $experiments->is_feature_active( 'e_font_icon_svg' ) ) {
			return $out;
		}
		$found = array();
		$base  = static function ( string $key ): string {
			return 0 === strpos( $key, 'play_icon_text_shadow_' ) ? 'play_icon_text_shadow' : \Digitalisimo\Elements\Convert\Translator::base( $key );
		};
		foreach ( array( '', '__globals__', '__dynamic__' ) as $bag ) {
			$keys = array_keys( '' === $bag ? $out : (array) ( $out[ $bag ] ?? array() ) );
			foreach ( $keys as $key ) {
				$name = $base( (string) $key );
				if ( 'play_icon_size' !== $name && 'play_icon_text_shadow' !== $name ) {
					continue;
				}
				$found[ $name ] = true;
				if ( '' === $bag ) {
					unset( $out[ $key ] );
				} else {
					unset( $out[ $bag ][ $key ] );
				}
			}
			if ( '' !== $bag && isset( $out[ $bag ] ) && array() === $out[ $bag ] ) {
				unset( $out[ $bag ] );
			}
		}
		if ( $report ) {
			$reasons = array(
				'play_icon_size'        => 'PRO no aplicaba el tamaño del icono de reproducir con los iconos como SVG en línea (su control sólo alcanzaba a la fuente de iconos): el icono quedaba en 100 px, como en el propio sin el ajuste.',
				'play_icon_text_shadow' => 'PRO no aplicaba la sombra del icono de reproducir con los iconos como SVG en línea (su control sólo alcanzaba a la fuente de iconos): quedaba la sombra del núcleo, como en el propio sin el ajuste.',
			);
			foreach ( array_keys( $found ) as $key ) {
				$report( 'pro.dropped', $key, $reasons[ $key ], 'dropped' );
			}
		}
		return $out;
	},
	'repeaters' => array(
		'slides' => array(
			'target'       => 'slides',
			'defaults'     => array( 'type' => 'image', 'image_link_to_type' => '' ),
			'default_rows' => array_fill( 0, 5, $digi_mc_row ),
			'keep'         => array( 'type', 'image', 'image_link_to_type', 'image_link_to', 'video' ),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_arrows', 'heading_pagination', 'play_icon_title', 'thumbs_title' ),
	'classes'   => array_merge( $digi_mc_common, $digi_mc_classes ),
	// PRO pintaba la imagen como fondo de `.elementor-carousel-image`; la propia es un `<img>` dentro de su equivalente.
	'img_classes' => array( '.digi-media-carousel__image' => ' img' ),
);
