<?php
namespace Digitalisimo\Elements\Carousel\Widgets;

use Digitalisimo\Elements\Carousel\Base_Widget;
use Digitalisimo\Elements\Carousel\Engine;
use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Carrusel multimedia: imágenes y vídeos sobre el motor `digi-crs` (sin Swiper ni jQuery). Sustituye al Media Carousel
 * de PRO (`modules/carousel/widgets/media-carousel.php`, mapa `modules/digitalisimo-convert/maps/media-carousel.php`):
 * repite sus controles y los de su base con el mismo nombre, valores por defecto y condiciones.
 *
 * - **Presentaciones.** «Carrusel»; «Slideshow», un carrusel principal de una diapositiva y otro de miniaturas que lo
 *   sigue (`data-digi-sync`, eventos `digi-crs-go` y `digi-crs-change` del motor); «Coverflow», aproximada con la
 *   diapositiva actual centrada (sin el giro 3D de Swiper).
 * - **Imágenes** con `wp_get_attachment_image()` y el tamaño de `image_size` (`srcset`, `decoding`, `loading`; con
 *   «Carga diferida», `loading="lazy"` siempre). Un `<img>` con su `alt` en lugar del fondo CSS de PRO.
 * - **Enlaces y lightbox** con los atributos del lightbox del núcleo (`data-elementor-open-lightbox`,
 *   `…-lightbox-slideshow` y `…-lightbox-video`): el núcleo lo abre al pulsar, como con PRO. Un vídeo es su imagen
 *   con el icono de reproducir; el reproductor del servicio sólo se crea al abrir el lightbox. Sin imagen propia y con
 *   las fachadas aplicadas, la portada la sirve el sitio (`Front\Facade_Images`); el visitante nunca pide nada al
 *   servicio antes de pulsar.
 * - **Superposición** con el título, la leyenda o la descripción del adjunto, o un icono propio en SVG, y sus
 *   animaciones; aparece también con el foco del teclado (PRO sólo con el puntero).
 *
 * Deriva de PRO Elements (GPL): `modules/carousel/widgets/media-carousel.php` (controles, textos, enlaces y lightbox) y
 * `assets/css/widget-media-carousel.min.css` (aspecto por defecto).
 */
class Media_Carousel extends Base_Widget {
	/** Icono de reproducir (el del núcleo, en SVG en línea). */
	const PLAY = '<svg viewBox="0 0 1000 1000" aria-hidden="true" focusable="false"><path d="M838 162C746 71 633 25 500 25 371 25 258 71 163 162 71 254 25 367 25 500 25 633 71 746 163 837 254 929 367 979 500 979 633 979 746 933 838 837 929 746 975 633 975 500 975 367 929 254 838 162M808 192C892 279 933 379 933 500 933 621 892 725 808 808 725 892 621 938 500 938 379 938 279 896 196 808 113 725 67 621 67 500 67 379 108 279 196 192 279 108 383 62 500 62 621 62 721 108 808 192M438 392V642L642 517 438 392Z"/></svg>';

	/** Iconos de la superposición (los mismos dibujos que PRO pintaba con la fuente de iconos), en SVG en línea. */
	const ICONS = array(
		'search-plus' => array( 512, 'M304 192v32c0 6.6-5.4 12-12 12h-56v56c0 6.6-5.4 12-12 12h-32c-6.6 0-12-5.4-12-12v-56h-56c-6.6 0-12-5.4-12-12v-32c0-6.6 5.4-12 12-12h56v-56c0-6.6 5.4-12 12-12h32c6.6 0 12 5.4 12 12v56h56c6.6 0 12 5.4 12 12zm201 284.7L476.7 505c-9.4 9.4-24.6 9.4-33.9 0L343 405.3c-4.5-4.5-7-10.6-7-17V372c-35.3 27.6-79.7 44-128 44C93.1 416 0 322.9 0 208S93.1 0 208 0s208 93.1 208 208c0 48.3-16.4 92.7-44 128h16.3c6.4 0 12.5 2.5 17 7l99.7 99.7c9.3 9.4 9.3 24.6 0 34zM344 208c0-75.2-60.8-136-136-136S72 132.8 72 208s60.8 136 136 136 136-60.8 136-136z' ),
		'plus-circle' => array( 512, 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm144 276c0 6.6-5.4 12-12 12h-92v92c0 6.6-5.4 12-12 12h-56c-6.6 0-12-5.4-12-12v-92h-92c-6.6 0-12-5.4-12-12v-56c0-6.6 5.4-12 12-12h92v-92c0-6.6 5.4-12 12-12h56c6.6 0 12 5.4 12 12v92h92c6.6 0 12 5.4 12 12v56z' ),
		'eye'         => array( 576, 'M572.52 241.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400a144 144 0 1 1 144-144 143.93 143.93 0 0 1-144 144zm0-240a95.31 95.31 0 0 0-25.31 3.79 47.85 47.85 0 0 1-66.9 66.9A95.78 95.78 0 1 0 288 160z' ),
		'link'        => array( 512, 'M326.612 185.391c59.747 59.809 58.927 155.698.36 214.59-.11.12-.24.25-.36.37l-67.2 67.2c-59.27 59.27-155.699 59.262-214.96 0-59.27-59.26-59.27-155.7 0-214.96l37.106-37.106c9.84-9.84 26.786-3.3 27.294 10.606.648 17.722 3.826 35.527 9.69 52.721 1.986 5.822.567 12.262-3.783 16.612l-13.087 13.087c-28.026 28.026-28.905 73.66-1.155 101.96 28.024 28.579 74.086 28.749 102.325.51l67.2-67.19c28.191-28.191 28.073-73.757 0-101.83-3.701-3.694-7.429-6.564-10.341-8.569a16.037 16.037 0 0 1-6.947-12.606c-.396-10.567 3.348-21.456 11.698-29.806l21.054-21.055c5.521-5.521 14.182-6.199 20.584-1.731a152.482 152.482 0 0 1 20.522 17.197zM467.547 44.449c-59.261-59.262-155.69-59.27-214.96 0l-67.2 67.2c-.12.12-.25.25-.36.37-58.566 58.892-59.387 154.781.36 214.59a152.454 152.454 0 0 0 20.521 17.196c6.402 4.468 15.064 3.789 20.584-1.731l21.054-21.055c8.35-8.35 12.094-19.239 11.698-29.806a16.037 16.037 0 0 0-6.947-12.606c-2.912-2.005-6.64-4.875-10.341-8.569-28.073-28.073-28.191-73.639 0-101.83l67.2-67.19c28.239-28.239 74.3-28.069 102.325.51 27.75 28.3 26.872 73.934-1.155 101.96l-13.087 13.087c-4.35 4.35-5.769 10.79-3.783 16.612 5.864 17.194 9.042 34.999 9.69 52.721.509 13.906 17.454 20.446 27.294 10.606l37.106-37.106c59.271-59.259 59.271-155.699.001-214.959z' ),
	);

	public function get_name() { return 'digitalisimo-media-carousel'; }
	public function get_title() { return esc_html__( 'Carrusel multimedia', 'pro-elements' ); }
	public function get_icon() { return 'eicon-media-carousel'; }
	public function get_keywords() { return array( 'carrusel', 'multimedia', 'imagen', 'vídeo', 'galería', 'lightbox', 'slideshow', 'media', 'carousel' ); }

	protected function register_controls() {
		parent::register_controls();

		$this->start_controls_section( 'section_lightbox_style', array( 'label' => esc_html__( 'Lightbox', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		// El lightbox es el del núcleo: sus selectores son los de su ventana.
		$this->add_control( 'lightbox_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '#elementor-lightbox-slideshow-{{ID}}' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'lightbox_ui_color', array( 'label' => esc_html__( 'Color de los controles', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '#elementor-lightbox-slideshow-{{ID}} .dialog-lightbox-close-button, #elementor-lightbox-slideshow-{{ID}} .elementor-swiper-button' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'lightbox_ui_hover_color', array( 'label' => esc_html__( 'Color de los controles al pasar el cursor', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '#elementor-lightbox-slideshow-{{ID}} .dialog-lightbox-close-button:hover, #elementor-lightbox-slideshow-{{ID}} .elementor-swiper-button:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control(
			'lightbox_video_width',
			array(
				'label'      => esc_html__( 'Ancho del vídeo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'unit' => '%' ),
				'range'      => array( '%' => array( 'min' => 50 ) ),
				'selectors'  => array( '#elementor-lightbox-slideshow-{{ID}} .elementor-video-container' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		$this->add_injections();
		$this->update_controls();
	}

	protected function add_repeater_controls( Repeater $repeater ) {
		$repeater->add_control(
			'type',
			array(
				'type'    => Controls_Manager::CHOOSE,
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'default' => 'image',
				'options' => array(
					'image' => array( 'title' => esc_html__( 'Imagen', 'pro-elements' ), 'icon' => 'eicon-image-bold' ),
					'video' => array( 'title' => esc_html__( 'Vídeo', 'pro-elements' ), 'icon' => 'eicon-video-camera' ),
				),
				'toggle'  => false,
			)
		);
		$repeater->add_control( 'image', array( 'label' => esc_html__( 'Imagen', 'pro-elements' ), 'type' => Controls_Manager::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control(
			'image_link_to_type',
			array(
				'label'     => esc_html__( 'Enlace', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''       => esc_html__( 'Ninguno', 'pro-elements' ),
					'file'   => esc_html__( 'Archivo multimedia', 'pro-elements' ),
					'custom' => esc_html__( 'URL personalizada', 'pro-elements' ),
				),
				'condition' => array( 'type' => 'image' ),
			)
		);
		$repeater->add_control(
			'image_link_to',
			array(
				'type'          => Controls_Manager::URL,
				'dynamic'       => array( 'active' => true ),
				'show_external' => 'true',
				'condition'     => array( 'type' => 'image', 'image_link_to_type' => 'custom' ),
				'show_label'    => false,
			)
		);
		$repeater->add_control(
			'video',
			array(
				'label'       => esc_html__( 'Enlace del vídeo', 'pro-elements' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => esc_html__( 'Escribe el enlace del vídeo', 'pro-elements' ),
				'description' => esc_html__( 'Enlace de YouTube o Vimeo', 'pro-elements' ),
				'options'     => false,
				'condition'   => array( 'type' => 'video' ),
			)
		);
	}

	protected function get_default_slides_count() {
		return 5;
	}

	protected function get_repeater_defaults() {
		$placeholder = class_exists( '\\Elementor\\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';
		return array_fill( 0, $this->get_default_slides_count(), array( 'image' => array( 'url' => $placeholder ) ) );
	}

	/**
	 * Carrusel principal y, en la presentación «Slideshow» con más de una diapositiva, el de miniaturas que lo sigue
	 * (como PRO: el principal sin paginación; las miniaturas sin flechas, paginación, enlaces, iconos ni superposición).
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['slides'] ) || ! is_array( $settings['slides'] ) ) {
			return;
		}
		$id    = 'digi-mc-' . $this->get_id();
		$count = count( $settings['slides'] );
		$show  = 'slideshow' === ( $settings['skin'] ?? 'carousel' ) && $count > 1;
		$this->print_slider( array( 'container_class' => 'digi-media-carousel digi-media-carousel__main', 'engine' => self::engine_args( $settings, false, $show ? $id : '' ) ) + $settings );
		if ( $show ) {
			$this->print_slider( array( 'container_class' => 'digi-media-carousel digi-media-carousel__thumbs', 'engine' => self::engine_args( $settings, true, $id ), 'thumbs_slider' => true, 'video_play_icon' => false ) + $settings );
		}
	}

	/**
	 * Argumentos de `Engine::open()` propios de cada presentación, que se suman a los de la base (`slider_args()`), como
	 * las opciones de Swiper que calculaba PRO: «Coverflow» centrado; «Slideshow» sin paginación en el principal, y
	 * las miniaturas sin flechas, paginación ni reproducción automática, centradas con «Diapositivas centradas» y
	 * siguiendo al principal (`sync`).
	 *
	 * @param bool   $thumbs Carrusel de miniaturas.
	 * @param string $id     En «Slideshow», id del principal.
	 */
	public static function engine_args( array $settings, bool $thumbs, string $id ): array {
		$skin = (string) ( $settings['skin'] ?? 'carousel' );
		$args = array(
			'center'     => 'coverflow' === $skin,
			'dir'        => function_exists( 'is_rtl' ) && is_rtl() ? 'rtl' : '',
			// Como PRO (`Math.min( slidesCount, slidesPerView )`): con menos diapositivas que las visibles, la hoja las
			// ensancha hasta llenar la pista.
			'attributes' => array( 'data-digi-count' => (string) count( (array) ( $settings['slides'] ?? array() ) ) ),
		);
		if ( 'coverflow' === $skin ) {
			$args['effect'] = 'slide';
		}
		if ( 'slideshow' === $skin ) {
			$args['pagination'] = '';
		}
		if ( '' !== $id && ! $thumbs ) {
			$args['id'] = $id;
		}
		if ( $thumbs ) {
			/* translators: %s: nombre del carrusel. */
			$args['label']    = sprintf( esc_html__( 'Miniaturas: %s', 'pro-elements' ), (string) ( $settings['slides_name'] ?? '' ) );
			$args['arrows']   = false;
			$args['autoplay'] = 0;
			$args['effect']   = 'slide';
			$args['center']   = 'yes' === ( $settings['centered_slides'] ?? '' );
			$args['sync']     = '#' . $id;
		}
		return $args;
	}

	/**
	 * Una diapositiva: el enlace (lightbox del núcleo o URL personalizada) con la imagen, el icono de reproducir de un
	 * vídeo y la superposición. Las miniaturas no llevan enlace, icono ni superposición: pulsarlas mueve el principal.
	 */
	protected function print_slide( array $slide, array $settings, $element_key ) {
		$thumbs  = ! empty( $settings['thumbs_slider'] );
		$type    = 'video' === ( $slide['type'] ?? 'image' ) ? 'video' : 'image';
		$video   = 'video' === $type ? trim( (string) ( $slide['video']['url'] ?? '' ) ) : '';
		$link_to = $thumbs ? '' : $this->link_to( $slide, $type );
		$image   = $this->media_image( $slide, $settings, $type, $video );
		$inner   = '<div class="digi-media-carousel__image">' . $image;
		if ( 'video' === $type && ! $thumbs ) {
			$inner .= '<span class="digi-media-carousel__play">' . self::PLAY . '<span class="elementor-screen-only">' . esc_html__( 'Reproducir el vídeo', 'pro-elements' ) . '</span></span>';
		}
		$inner  .= '</div>';
		$inner  .= $thumbs ? '' : $this->overlay( $slide, $settings );
		if ( '' === $link_to ) {
			echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio escapado.
			return;
		}
		$key = $element_key . '_link';
		$this->add_render_attribute( $key, 'class', 'digi-media-carousel__link' );
		if ( 'image' === $type && 'custom' === ( $slide['image_link_to_type'] ?? '' ) ) {
			$this->add_link_attributes( $key, (array) ( $slide['image_link_to'] ?? array() ) );
			if ( ! empty( $slide['image_link_to']['is_external'] ) ) {
				$this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) );
			}
		} else {
			$this->add_render_attribute( $key, 'href', esc_url( $link_to ) );
			$this->add_lightbox_data_attributes( $key, $slide['image']['id'] ?? null, 'yes', $this->get_id() );
			if ( self::edit_mode() ) {
				$this->add_render_attribute( $key, 'class', 'elementor-clickable' );
			}
		}
		$embed = '' !== $video ? self::lightbox_video( $video ) : '';
		if ( '' !== $embed ) {
			$this->add_render_attribute( $key, 'data-elementor-lightbox-video', $embed );
		}
		echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos y marcado propios escapados.
	}

	/** Destino del enlace como en PRO: un vídeo abre su imagen en el lightbox (o `#`); una imagen, el archivo o la URL. */
	private function link_to( array $slide, string $type ): string {
		$image = (string) ( $slide['image']['url'] ?? '' );
		if ( 'video' === $type ) {
			return '' !== $image ? $image : '#';
		}
		$link = (string) ( $slide['image_link_to_type'] ?? '' );
		if ( '' === $link ) {
			return '';
		}
		return 'custom' === $link ? (string) ( $slide['image_link_to']['url'] ?? '' ) : $image;
	}

	/**
	 * Imagen de la diapositiva (`get_slide_image()` de la base: el adjunto con el tamaño de `image_size` y `srcset`, o
	 * la URL guardada) con el texto alternativo del adjunto; en un vídeo sin imagen y con las fachadas aplicadas, la
	 * portada servida por el sitio.
	 */
	private function media_image( array $slide, array $settings, string $type, string $video ): string {
		$attrs = array( 'class' => 'digi-media-carousel__img', 'alt' => self::alt( $slide ) );
		$html  = $this->get_slide_image( $slide, $settings, $attrs );
		if ( '' !== $html || 'video' !== $type || '' === $video ) {
			return $html;
		}
		$url = self::facade_image( $video );
		if ( '' === $url ) {
			return '';
		}
		$slide['image'] = array( 'url' => $url, 'id' => '' );
		return $this->get_slide_image( $slide, $settings, array( 'class' => 'digi-media-carousel__img', 'alt' => '' ) );
	}

	/** Texto alternativo: el del adjunto (como PRO, que lo ponía en `aria-label`). */
	private static function alt( array $slide ): string {
		$image = (array) ( $slide['image'] ?? array() );
		if ( class_exists( '\\Elementor\\Control_Media' ) ) {
			return trim( (string) \Elementor\Control_Media::get_image_alt( $image ) );
		}
		return trim( (string) ( $image['alt'] ?? '' ) );
	}

	/** Superposición con el texto del adjunto o el icono elegido (no en «Slideshow», como en PRO). */
	private function overlay( array $slide, array $settings ): string {
		$overlay = (string) ( $settings['overlay'] ?? '' );
		if ( '' === $overlay || 'slideshow' === ( $settings['skin'] ?? 'carousel' ) ) {
			return '';
		}
		$animation = (string) ( $settings['overlay_animation'] ?? 'fade' );
		$animation = in_array( $animation, array( 'fade', 'slide-up', 'slide-down', 'slide-right', 'slide-left', 'zoom-in' ), true ) ? $animation : 'fade';
		$content   = 'text' === $overlay ? wp_kses_post( $this->caption( $slide ) ) : self::icon( (string) ( $settings['icon'] ?? 'search-plus' ) );
		return '<div class="digi-media-carousel__overlay digi-media-carousel__overlay--' . esc_attr( $animation ) . '">' . $content . '</div>';
	}

	/** Título, leyenda o descripción del adjunto (sólo de una imagen adjunta: PRO también leía otras entradas). */
	private function caption( array $slide ): string {
		$type = (string) $this->get_settings( 'caption' );
		$id   = (int) ( $slide['image']['id'] ?? 0 );
		if ( '' === $type || ! $id || ! function_exists( 'get_post' ) ) {
			return '';
		}
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type || 0 !== strpos( (string) $post->post_mime_type, 'image/' ) ) {
			return '';
		}
		if ( 'caption' === $type ) {
			return (string) $post->post_excerpt;
		}
		return 'title' === $type ? (string) $post->post_title : (string) $post->post_content;
	}

	/** Icono de la superposición en SVG en línea (decorativo: el enlace lleva el nombre de la imagen). */
	public static function icon( string $name ): string {
		$icon = self::ICONS[ $name ] ?? self::ICONS['search-plus'];
		return '<svg viewBox="0 0 ' . (int) $icon[0] . ' 512" aria-hidden="true" focusable="false"><path d="' . esc_attr( $icon[1] ) . '"/></svg>';
	}

	/**
	 * Dirección del reproductor que el lightbox del núcleo crea al abrirse, con las mismas opciones que PRO
	 * (`autoplay`, sin vídeos relacionados ni controles) y el dominio sin cookies de YouTube. '' si no es un servicio
	 * que el núcleo sepa incrustar.
	 */
	public static function lightbox_video( string $url ): string {
		if ( ! class_exists( '\\Elementor\\Embed' ) ) {
			return '';
		}
		$embed = (string) \Elementor\Embed::get_embed_url( $url, array( 'autoplay' => 1, 'rel' => 0, 'controls' => 0 ), array( 'privacy' => true ) );
		return 'https' === strtolower( (string) wp_parse_url( $embed, PHP_URL_SCHEME ) ) ? $embed : '';
	}

	/** Portada de un vídeo sin imagen propia, servida por el sitio (sólo con las fachadas aplicadas), o ''. */
	private static function facade_image( string $video ): string {
		$job = self::facade_job( $video );
		return $job && \Digitalisimo\Elements\Front\Facades::active() ? \Digitalisimo\Elements\Front\Facade_Images::url( $job ) : '';
	}

	/** Trabajo de la portada de un enlace de vídeo, o []. */
	private static function facade_job( string $video ): array {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) || ! class_exists( '\\Digitalisimo\\Elements\\Video_Player_Widget' ) ) {
			return array();
		}
		$parts = \Digitalisimo\Elements\Video_Player_Widget::embed_parts( $video );
		return $parts ? \Digitalisimo\Elements\Front\Facade_Images::video( (string) $parts['service'], (string) $parts['id'] ) : array();
	}

	/**
	 * Portadas que pintan los vídeos sin imagen propia (para `Front\Facades::dynamic()`: mientras falte alguna, el
	 * widget no entra en la caché de elementos).
	 */
	public static function facade_jobs( array $settings ): array {
		$jobs = array();
		foreach ( (array) ( $settings['slides'] ?? array() ) as $slide ) {
			if ( is_array( $slide ) && 'video' === ( $slide['type'] ?? '' ) && '' === (string) ( $slide['image']['url'] ?? '' ) ) {
				$job = self::facade_job( (string) ( $slide['video']['url'] ?? '' ) );
				if ( $job ) {
					$jobs[] = $job;
				}
			}
		}
		return $jobs;
	}

	private static function edit_mode(): bool {
		return class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/** Controles propios del Carrusel multimedia, en las mismas posiciones que en PRO. */
	private function add_injections() {
		$this->start_injection( array( 'type' => 'section', 'at' => 'start', 'of' => 'section_slides' ) );
		$this->add_control(
			'skin',
			array(
				'label'              => esc_html__( 'Presentación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'carousel',
				'options'            => array(
					'carousel'  => esc_html__( 'Carrusel', 'pro-elements' ),
					'slideshow' => esc_html__( 'Slideshow', 'pro-elements' ),
					'coverflow' => esc_html__( 'Coverflow', 'pro-elements' ),
				),
				'prefix_class'       => 'digi-media-carousel--skin-',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);
		$this->end_injection();

		$this->start_injection( array( 'of' => 'image_size_custom_dimension' ) );
		$this->add_control(
			'image_fit',
			array(
				'label'                => esc_html__( 'Ajuste de la imagen', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'default'              => '',
				'options'              => array(
					''        => esc_html__( 'Cubrir', 'pro-elements' ),
					'contain' => esc_html__( 'Contener', 'pro-elements' ),
					'auto'    => esc_html__( 'Automático', 'pro-elements' ),
				),
				'selectors_dictionary' => array( 'auto' => 'none' ),
				'selectors'            => array( '{{WRAPPER}} .digi-media-carousel__main .digi-media-carousel__img' => 'object-fit: {{VALUE}}' ),
			)
		);
		$this->end_injection();

		$this->start_injection( array( 'of' => 'pagination_color' ) );
		$this->add_control( 'play_icon_title', array( 'label' => esc_html__( 'Icono de reproducir', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'play_icon_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-media-carousel__play svg' => 'fill: {{VALUE}}' ) ) );
		$this->add_responsive_control(
			'play_icon_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'min' => 20, 'max' => 150 ),
					'em'  => array( 'min' => 2, 'max' => 15 ),
					'rem' => array( 'min' => 2, 'max' => 15 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-media-carousel__play' => 'font-size: {{SIZE}}{{UNIT}}' ),
			)
		);
		// La sombra de PRO (`text-shadow` de la fuente de iconos), como `drop-shadow` del icono SVG.
		$this->add_group_control(
			\Elementor\Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'play_icon_text_shadow',
				'selector'       => '{{WRAPPER}} .digi-media-carousel__play',
				'fields_options' => array(
					'text_shadow_type' => array( 'label' => esc_html__( 'Sombra', 'pro-elements' ) ),
					'text_shadow'      => array( 'selectors' => array( '{{SELECTOR}}' => '--digi-mc-play-shadow: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}});' ) ),
				),
			)
		);
		$this->end_injection();

		$this->start_injection( array( 'of' => 'pause_on_interaction' ) );
		$this->add_control(
			'overlay',
			array(
				'label'     => esc_html__( 'Superposición', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''     => esc_html__( 'Ninguna', 'pro-elements' ),
					'text' => esc_html__( 'Texto', 'pro-elements' ),
					'icon' => esc_html__( 'Icono', 'pro-elements' ),
				),
				'condition' => array( 'skin!' => 'slideshow' ),
				'separator' => 'before',
			)
		);
		$this->add_control(
			'caption',
			array(
				'label'     => esc_html__( 'Texto', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'title',
				'options'   => array(
					'title'       => esc_html__( 'Título', 'pro-elements' ),
					'caption'     => esc_html__( 'Leyenda', 'pro-elements' ),
					'description' => esc_html__( 'Descripción', 'pro-elements' ),
				),
				'condition' => array( 'skin!' => 'slideshow', 'overlay' => 'text' ),
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'     => esc_html__( 'Icono', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'search-plus',
				'options'   => array(
					'search-plus' => array( 'title' => esc_html__( 'Ampliar', 'pro-elements' ), 'icon' => 'eicon-search-bold' ),
					'plus-circle' => array( 'title' => esc_html__( 'Más', 'pro-elements' ), 'icon' => 'eicon-plus-circle' ),
					'eye'         => array( 'title' => esc_html__( 'Ver', 'pro-elements' ), 'icon' => 'eicon-preview-medium' ),
					'link'        => array( 'title' => esc_html__( 'Enlace', 'pro-elements' ), 'icon' => 'eicon-link' ),
				),
				'condition' => array( 'skin!' => 'slideshow', 'overlay' => 'icon' ),
			)
		);
		$this->add_control(
			'overlay_animation',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'        => esc_html__( 'Fundido', 'pro-elements' ),
					'slide-up'    => esc_html__( 'Deslizar hacia arriba', 'pro-elements' ),
					'slide-down'  => esc_html__( 'Deslizar hacia abajo', 'pro-elements' ),
					'slide-right' => esc_html__( 'Deslizar a la derecha', 'pro-elements' ),
					'slide-left'  => esc_html__( 'Deslizar a la izquierda', 'pro-elements' ),
					'zoom-in'     => esc_html__( 'Acercar', 'pro-elements' ),
				),
				'condition' => array( 'skin!' => 'slideshow', 'overlay!' => '' ),
			)
		);
		$this->end_injection();

		$this->start_injection( array( 'type' => 'section', 'of' => 'section_navigation' ) );
		$this->start_controls_section(
			'section_overlay',
			array(
				'label'     => esc_html__( 'Superposición', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'skin!' => 'slideshow', 'overlay!' => '' ),
			)
		);
		$this->add_control( 'overlay_background_color', array( 'label' => esc_html__( 'Color de fondo', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-media-carousel__overlay' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'overlay_color', array( 'label' => esc_html__( 'Color del texto', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-media-carousel__overlay' => '--digi-mc-overlay-color: {{VALUE}};' ) ) );
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'caption_typography',
				'global'    => array( 'default' => 'globals/typography?id=accent' ),
				'selector'  => '{{WRAPPER}} .digi-media-carousel__overlay',
				'condition' => array( 'overlay' => 'text' ),
			)
		);
		$this->add_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-media-carousel__overlay' => '--digi-mc-icon-size: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'overlay' => 'icon' ),
			)
		);
		$this->end_controls_section();
		$this->end_injection();

		$this->start_injection( array( 'of' => 'effect' ) );
		$this->add_responsive_control(
			'slideshow_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array( 'px' => array( 'min' => 20, 'max' => 1000 ) ),
				'selectors'  => array( '{{WRAPPER}} .digi-media-carousel__main' => '--digi-crs-height: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'skin' => 'slideshow' ),
			)
		);
		$this->add_control( 'thumbs_title', array( 'label' => esc_html__( 'Miniaturas', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'condition' => array( 'skin' => 'slideshow' ) ) );
		$this->end_injection();

		$this->start_injection( array( 'of' => 'slides_per_view' ) );
		$this->add_control(
			'thumbs_ratio',
			array(
				'label'                => esc_html__( 'Proporción', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array( '169' => '16:9', '219' => '21:9', '43' => '4:3', '11' => '1:1' ),
				'selectors_dictionary' => array( '169' => '16 / 9', '219' => '21 / 9', '43' => '4 / 3', '11' => '1 / 1' ),
				'default'              => '219',
				'condition'            => array( 'skin' => 'slideshow' ),
				'selectors'            => array( '{{WRAPPER}} .digi-media-carousel__thumbs .digi-media-carousel__image' => 'aspect-ratio: {{VALUE}}' ),
			)
		);
		$this->add_control( 'centered_slides', array( 'label' => esc_html__( 'Diapositivas centradas', 'pro-elements' ), 'type' => Controls_Manager::SWITCHER, 'condition' => array( 'skin' => 'slideshow' ), 'frontend_available' => true ) );
		$this->end_injection();

		$this->start_injection( array( 'of' => 'slides_per_view' ) );
		$per_view = range( 1, 10 );
		$this->add_responsive_control(
			'slideshow_slides_per_view',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Diapositivas visibles', 'pro-elements' ),
				'options'            => array( '' => esc_html__( 'Predeterminado', 'pro-elements' ) ) + array_combine( $per_view, $per_view ),
				// Los de PRO cuando el valor era «Predeterminado»: 5 miniaturas (4 en tableta, 3 en móvil).
				'widescreen_default'   => '5',
				'desktop_default'      => '5',
				'laptop_default'       => '5',
				'tablet_extra_default' => '5',
				'tablet_default'       => '4',
				'mobile_extra_default' => '4',
				'mobile_default'       => '3',
				'condition'          => array( 'skin' => 'slideshow' ),
				'selectors'          => array( '{{WRAPPER}} .digi-media-carousel__thumbs' => '--digi-crs-view: {{VALUE}};' ),
				'frontend_available' => true,
			)
		);
		$this->end_injection();
	}

	/** Las mismas condiciones que PRO añadía a los controles de la base. */
	private function update_controls() {
		foreach ( array( 'slides_to_scroll', 'pagination', 'heading_pagination', 'pagination_gap', 'pagination_size', 'pagination_position', 'pagination_color' ) as $control ) {
			$this->update_control( $control, array( 'condition' => array( 'skin!' => 'slideshow' ) ), array( 'recursive' => true ) );
		}
		foreach ( array( 'width', 'height', 'slides_per_view' ) as $control ) {
			$this->update_responsive_control( $control, array( 'condition' => array( 'skin!' => 'slideshow' ) ), array( 'recursive' => true ) );
		}
		// PRO usaba 3 diapositivas visibles (2 en tableta, 1 en móvil) cuando un dispositivo quedaba en «Predeterminado»;
		// aquí son los valores por defecto de cada dispositivo, que el núcleo pinta en su consulta de medios.
		// Lo mismo con «Diapositivas por paso» (1 en cada dispositivo que no lo fija). Así un valor del escritorio no se
		// hereda en la tableta ni en el móvil, como en PRO.
		$per_view = array( 'widescreen' => '3', 'desktop' => '3', 'laptop' => '3', 'tablet_extra' => '3', 'tablet' => '2', 'mobile_extra' => '2', 'mobile' => '1' );
		$view     = array();
		$scroll   = array();
		foreach ( self::active_devices() as $device ) {
			$view[ $device . '_default' ] = $per_view[ $device ] ?? '3';
			if ( 'desktop' !== $device ) {
				$scroll[ $device . '_default' ] = '1';
			}
		}
		$this->update_responsive_control( 'slides_per_view', $view );
		if ( $scroll ) {
			$this->update_responsive_control( 'slides_to_scroll', $scroll );
		}
		$this->update_responsive_control(
			'space_between',
			array(
				'selectors'   => array(
					'{{WRAPPER}}' => '--digi-crs-gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.digi-media-carousel--skin-slideshow .digi-media-carousel__main' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
				'render_type' => 'ui',
			)
		);
		$this->update_control( 'effect', array( 'condition' => array( 'skin!' => 'coverflow' ) ) );
	}

	public function get_group_name() {
		return 'carousel';
	}
}
