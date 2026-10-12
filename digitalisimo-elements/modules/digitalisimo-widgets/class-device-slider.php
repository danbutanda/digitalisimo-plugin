<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-carousel-engine.php';

/**
 * Capturas en marco de dispositivo con el motor nativo compartido. Cada diapositiva puede ser una imagen,
 * un color, un vídeo MP4 o un vídeo de YouTube; los vídeos se reproducen silenciados y en bucle, con
 * controles para poder pausarlos.
 */
class Device_Slider_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-device-slider'; }
	public function get_title() { return 'Slider de dispositivos'; }
	public function get_icon() { return 'eicon-slider-device'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'dispositivo', 'capturas', 'slider', 'móvil' ); }
	public function get_style_depends() { return array( 'digitalisimo-carousel-engine', 'digitalisimo-device-slider' ); }
	public function get_script_depends() { return array( 'digitalisimo-carousel-engine' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_device', array( 'label' => 'Dispositivo y capturas' ) );
		$this->add_control( 'device_type', array( 'label' => 'Marco', 'type' => $c::SELECT, 'default' => 'desktop', 'options' => array( 'desktop' => 'Escritorio', 'tablet' => 'Tableta', 'mobile' => 'Móvil', 'browser' => 'Navegador' ) ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varias capturas', 'type' => $c::GALLERY ) );
		$items = new \Elementor\Repeater();
		$items->add_control( 'background', array( 'label' => 'Contenido', 'type' => $c::SELECT, 'default' => 'image', 'options' => array( 'image' => 'Imagen', 'color' => 'Color', 'video' => 'Vídeo MP4', 'youtube' => 'Vídeo de YouTube' ) ) );
		$items->add_control( 'image', array( 'label' => 'Captura', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'image' ) ) );
		$items->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'default' => '#14ABF4', 'condition' => array( 'background' => 'color' ), 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-device-slider__color' => 'background-color:{{VALUE}};' ) ) );
		$items->add_control( 'video', array( 'label' => 'Vídeo', 'type' => $c::MEDIA, 'media_types' => array( 'video' ), 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'video' ) ) );
		$items->add_control( 'youtube_url', array( 'label' => 'Dirección de YouTube', 'type' => $c::TEXT, 'placeholder' => 'https://youtu.be/…', 'dynamic' => array( 'active' => true ), 'condition' => array( 'background' => 'youtube' ) ) );
		$items->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$items->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'slides', array( 'label' => 'Capturas con datos individuales', 'type' => $c::REPEATER, 'fields' => $items->get_controls(), 'title_field' => '{{{ title }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de archivo', 'type' => $c::SELECT, 'default' => 'large', 'options' => array( 'medium' => 'Mediano', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => array( 'arrows' => 'Flechas', 'none' => 'Desactivada' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_device_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'width', array( 'label' => 'Ancho máximo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 200, 'max' => 1400 ) ), 'selectors' => array( '{{WRAPPER}} .digi-device-slider' => 'max-width:{{SIZE}}px;' ) ) );
		$this->add_control( 'frame_color', array( 'label' => 'Color del marco', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-device-slider__frame' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Identificador de 11 caracteres de un enlace de YouTube, o ''. */
	public static function youtube_id( $url ) {
		return preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})%i', (string) $url, $m ) ? $m[1] : '';
	}

	private static function items( $settings ) {
		$items = array();
		foreach ( is_array( $settings['gallery_images'] ?? null ) ? $settings['gallery_images'] : array() as $image ) { if ( is_array( $image ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) ) { $items[] = array( 'background' => 'image', 'image' => $image, 'title' => '', 'link' => array() ); } }
		foreach ( is_array( $settings['slides'] ?? null ) ? $settings['slides'] : array() as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$kind = in_array( $item['background'] ?? 'image', array( 'image', 'color', 'video', 'youtube' ), true ) ? ( $item['background'] ?? 'image' ) : 'image';
			$item['background'] = $kind;
			if ( 'color' === $kind
				|| ( 'image' === $kind && is_array( $item['image'] ?? null ) && ( ! empty( $item['image']['id'] ) || ! empty( $item['image']['url'] ) ) )
				|| ( 'video' === $kind && ! empty( $item['video']['url'] ) )
				|| ( 'youtube' === $kind && '' !== self::youtube_id( $item['youtube_url'] ?? '' ) ) ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = self::items( $s );
		if ( ! $items ) { return; }
		$type = in_array( $s['device_type'] ?? 'desktop', array( 'desktop', 'tablet', 'mobile', 'browser' ), true ) ? ( $s['device_type'] ?? 'desktop' ) : 'desktop';
		$size = in_array( $s['image_size'] ?? 'large', array( 'medium', 'large', 'full' ), true ) ? ( $s['image_size'] ?? 'large' ) : 'large';
		echo '<div class="digi-device-slider digi-device-slider--' . esc_attr( $type ) . '"><div class="digi-device-slider__frame">';
		Carousel_Engine::open( 'Capturas de dispositivo', count( $items ) );
		foreach ( $items as $index => $item ) {
			$title = trim( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) );
			$markup = '';
			if ( 'color' === $item['background'] ) {
				$markup = '<span class="digi-device-slider__color" aria-hidden="true"></span>';
			} elseif ( 'video' === $item['background'] ) {
				$markup = '<video class="digi-device-slider__video" src="' . esc_url( $item['video']['url'] ) . '" autoplay muted loop playsinline controls preload="metadata"' . ( '' !== $title ? ' aria-label="' . esc_attr( $title ) . '"' : '' ) . '></video>';
			} elseif ( 'youtube' === $item['background'] ) {
				$yt = self::youtube_id( $item['youtube_url'] ?? '' );
				$markup = '<iframe class="digi-device-slider__video" src="' . esc_url( 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&mute=1&loop=1&playlist=' . $yt . '&playsinline=1&rel=0' ) . '" title="' . esc_attr( '' !== $title ? $title : 'Vídeo de YouTube' ) . '" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen' . ( $index > 0 ? ' loading="lazy"' : '' ) . '></iframe>';
			} else {
				$image = $item['image']; $id = absint( $image['id'] ?? 0 );
				$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
				if ( ! $alt ) { $alt = $title; }
				$attrs = array( 'class' => 'digi-device-slider__image', 'alt' => $alt, 'decoding' => 'async' );
				if ( $index > 0 ) { $attrs['loading'] = 'lazy'; }
				$markup = $id ? wp_get_attachment_image( $id, $size, false, $attrs ) : '';
				if ( ! $markup && ! empty( $image['url'] ) ) { $markup = '<img class="digi-device-slider__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"' . ( $index > 0 ? ' loading="lazy"' : '' ) . '>'; }
			}
			if ( ! $markup ) { continue; }
			echo '<li class="digi-carousel__slide digi-device-slider__slide' . ( ! empty( $item['_id'] ) ? ' elementor-repeater-item-' . esc_attr( sanitize_html_class( (string) $item['_id'] ) ) : '' ) . '">';
			$link = is_array( $item['link'] ?? null ) ? $item['link'] : array();
			if ( ! empty( $link['url'] ) ) {
				$key = 'device_link_' . $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				// Un vídeo con controles no va dentro de un enlace: el enlace pasa al título.
				if ( in_array( $item['background'], array( 'video', 'youtube' ), true ) ) {
					echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construido y escapado arriba.
					if ( $title ) { echo '<a class="digi-device-slider__title" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $title ) . '</a>'; $title = ''; }
				} else {
					echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . $markup . '</a>';
				}
			}
			else { echo $markup; }
			if ( $title ) { echo '<span class="digi-device-slider__title">' . esc_html( $title ) . '</span>'; }
			echo '</li>';
		}
		Carousel_Engine::close( count( $items ) > 1 && 'none' !== ( $s['navigation'] ?? 'arrows' ) );
		echo '</div></div>';
	}

	protected function content_template() {
		?>
		<# var type = _.contains(['desktop','tablet','mobile','browser'], settings.device_type) ? settings.device_type : 'desktop';
		var ytId = function(url){ var m = String(url || '').match(/(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([A-Za-z0-9_-]{11})/i); return m ? m[1] : ''; };
		var items = (settings.gallery_images || []).map(function(image){ return { background:'image', image:image }; }).concat(settings.slides || []).filter(function(item){ if (!item) return false; var kind = item.background || 'image'; return kind === 'color' || (kind === 'image' && item.image && item.image.url) || (kind === 'video' && item.video && item.video.url) || (kind === 'youtube' && ytId(item.youtube_url)); });
		var linkRel = function(link){ return [ link && link.nofollow ? 'nofollow' : '', link && link.is_external ? 'noopener noreferrer' : '' ].filter(Boolean).join(' '); }; #>
		<# if ( items.length ) { #><div class="digi-device-slider digi-device-slider--{{ type }}"><div class="digi-device-slider__frame"><section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="Capturas de dispositivo" style="--digi-carousel-count:{{ items.length }}"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides"><# _.each(items,function(item){ var link = item.link || {}; var rel = linkRel(link); #><# var kind = item.background || 'image'; #><li class="digi-carousel__slide digi-device-slider__slide<# if (item._id) { #> elementor-repeater-item-{{ item._id }}<# } #>"><# if (kind === 'color') { #><span class="digi-device-slider__color" aria-hidden="true"></span><# } else if (kind === 'video') { #><video class="digi-device-slider__video" src="{{ item.video.url }}" autoplay muted loop playsinline controls preload="metadata"></video><# } else if (kind === 'youtube') { var yt = ytId(item.youtube_url); #><iframe class="digi-device-slider__video" src="https://www.youtube-nocookie.com/embed/{{ yt }}?autoplay=1&mute=1&loop=1&playlist={{ yt }}&playsinline=1&rel=0" title="{{ item.title || 'Vídeo de YouTube' }}" allowfullscreen></iframe><# } else { #><# if (link.url) { #><a href="{{ link.url }}"<# if (link.is_external) { #> target="_blank"<# } #><# if (rel) { #> rel="{{ rel }}"<# } #>><# } #><img class="digi-device-slider__image" src="{{ item.image.url }}" alt="{{ item.image.alt || item.title || '' }}"><# if (link.url) { #></a><# } #><# } #><# if (item.title) { #><span class="digi-device-slider__title">{{ item.title }}</span><# } #></li><# }); #></ul></div><# if (items.length > 1 && settings.navigation !== 'none') { #><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div><# } #></section></div></div><# } #>
		<?php
	}
}
