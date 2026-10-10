<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-carousel-engine.php';

/** Diapositivas editoriales con imagen, texto y enlaces, usando el carrusel compartido. */
final class Fancy_Slider_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-fancy-slider'; }
	public function get_title() { return 'Slider destacado'; }
	public function get_icon() { return 'eicon-slides'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'slider', 'destacado', 'imagen', 'contenido' ); }
	public function get_style_depends() { return array( 'digitalisimo-carousel-engine', 'digitalisimo-fancy-slider' ); }
	public function get_script_depends() { return array( 'digitalisimo-carousel-engine' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_slides', array( 'label' => 'Diapositivas' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'sub_title', array( 'label' => 'Subtítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'title_link', array( 'label' => 'Enlace del título', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::WYSIWYG, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'slide_image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'slide_button', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'button_link', array( 'label' => 'Enlace del botón', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'slides', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ title }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_options', array( 'label' => 'Presentación' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'large', 'options' => array( 'medium' => 'Mediano', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'image_position', array( 'label' => 'Posición de imagen', 'type' => $c::SELECT, 'default' => 'right', 'options' => array( 'left' => 'Izquierda', 'right' => 'Derecha' ) ) );
		$this->add_control( 'title_tags', array( 'label' => 'Etiqueta de título', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'Div' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => array( 'arrows' => 'Flechas', 'none' => 'Desactivada' ) ) );
		foreach ( array( 'show_subtitle' => 'Mostrar subtítulo', 'show_title' => 'Mostrar título', 'show_description' => 'Mostrar descripción', 'show_button' => 'Mostrar botón', 'show_slide_image' => 'Mostrar imagen' ) as $key => $label ) {
			$this->add_control( $key, array( 'label' => $label, 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		}
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'text_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-slider__content' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-slider__slide' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => 'Color de título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-slider__title' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno del texto', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-slider__content' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function has_content( $item ) {
		return is_array( $item ) && ( ! empty( $item['title'] ) || ! empty( $item['sub_title'] ) || ! empty( $item['description'] ) || ! empty( $item['slide_image']['url'] ) || ! empty( $item['slide_image']['id'] ) );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$slides = array_values( array_filter( is_array( $s['slides'] ?? null ) ? $s['slides'] : array(), array( self::class, 'has_content' ) ) );
		if ( ! $slides ) { return; }
		$size = in_array( $s['image_size'] ?? 'large', array( 'medium', 'large', 'full' ), true ) ? ( $s['image_size'] ?? 'large' ) : 'large';
		$tag = in_array( $s['title_tags'] ?? 'h2', array( 'h2', 'h3', 'h4', 'div' ), true ) ? ( $s['title_tags'] ?? 'h2' ) : 'h2';
		$position = 'left' === ( $s['image_position'] ?? 'right' ) ? 'left' : 'right';
		echo '<div class="digi-fancy-slider digi-fancy-slider--image-' . esc_attr( $position ) . '">';
		Carousel_Engine::open( 'Contenido destacado', count( $slides ) );
		foreach ( $slides as $index => $slide ) {
			$title = trim( wp_strip_all_tags( (string) ( $slide['title'] ?? '' ) ) );
			$subtitle = trim( wp_strip_all_tags( (string) ( $slide['sub_title'] ?? '' ) ) );
			$description = (string) ( $slide['description'] ?? '' );
			echo '<li class="digi-carousel__slide digi-fancy-slider__slide"><div class="digi-fancy-slider__content">';
			if ( 'yes' === ( $s['show_subtitle'] ?? 'yes' ) && '' !== $subtitle ) { echo '<span class="digi-fancy-slider__subtitle">' . esc_html( $subtitle ) . '</span>'; }
			if ( 'yes' === ( $s['show_title'] ?? 'yes' ) && '' !== $title ) {
				echo '<' . $tag . ' class="digi-fancy-slider__title">';
				$link = is_array( $slide['title_link'] ?? null ) ? $slide['title_link'] : array();
				if ( ! empty( $link['url'] ) ) { $key = 'fancy_title_' . $index; $this->add_link_attributes( $key, $link ); if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); } echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $title ) . '</a>'; }
				else { echo esc_html( $title ); }
				echo '</' . $tag . '>';
			}
			if ( 'yes' === ( $s['show_description'] ?? 'yes' ) && '' !== trim( wp_strip_all_tags( $description ) ) ) { echo '<div class="digi-fancy-slider__description">' . wp_kses_post( $description ) . '</div>'; }
			$button = trim( wp_strip_all_tags( (string) ( $slide['slide_button'] ?? '' ) ) );
			$link = is_array( $slide['button_link'] ?? null ) ? $slide['button_link'] : array();
			if ( 'yes' === ( $s['show_button'] ?? 'yes' ) && '' !== $button && ! empty( $link['url'] ) ) { $key = 'fancy_button_' . $index; $this->add_link_attributes( $key, $link ); if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); } echo '<a class="digi-fancy-slider__button" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $button ) . '</a>'; }
			echo '</div>';
			$image = is_array( $slide['slide_image'] ?? null ) ? $slide['slide_image'] : array();
			if ( 'yes' === ( $s['show_slide_image'] ?? 'yes' ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) ) {
				$id = absint( $image['id'] ?? 0 );
				$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
				$attrs = array( 'class' => 'digi-fancy-slider__image', 'alt' => $alt, 'decoding' => 'async' );
				if ( $index > 0 ) { $attrs['loading'] = 'lazy'; }
				$markup = $id ? wp_get_attachment_image( $id, $size, false, $attrs ) : '';
				if ( ! $markup && ! empty( $image['url'] ) ) { $markup = '<img class="digi-fancy-slider__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"' . ( $index > 0 ? ' loading="lazy"' : '' ) . '>'; }
				if ( $markup ) { echo '<div class="digi-fancy-slider__media">' . $markup . '</div>'; }
			}
			echo '</li>';
		}
		Carousel_Engine::close( count( $slides ) > 1 && 'none' !== ( $s['navigation'] ?? 'arrows' ) );
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var slides = (settings.slides || []).filter(function(item){ return item && (item.title || item.sub_title || item.description || (item.slide_image && item.slide_image.url)); });
		var position = settings.image_position === 'left' ? 'left' : 'right'; var tag = _.contains(['h2','h3','h4','div'],settings.title_tags) ? settings.title_tags : 'h2';
		var linkRel = function(link){ return [ link && link.nofollow ? 'nofollow' : '', link && link.is_external ? 'noopener noreferrer' : '' ].filter(Boolean).join(' '); }; #>
		<# if ( slides.length ) { #><div class="digi-fancy-slider digi-fancy-slider--image-{{ position }}"><section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="Contenido destacado" style="--digi-carousel-count:{{ slides.length }}"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides"><# _.each(slides,function(item){ var titleLink = item.title_link || {}; var buttonLink = item.button_link || {}; var titleRel = linkRel(titleLink); var buttonRel = linkRel(buttonLink); #><li class="digi-carousel__slide digi-fancy-slider__slide"><div class="digi-fancy-slider__content"><# if ( settings.show_subtitle !== 'no' && item.sub_title ) { #><span class="digi-fancy-slider__subtitle">{{ item.sub_title }}</span><# } #><# if ( settings.show_title !== 'no' && item.title ) { #><{{{ tag }}} class="digi-fancy-slider__title"><# if (titleLink.url) { #><a href="{{ titleLink.url }}"<# if (titleLink.is_external) { #> target="_blank"<# } #><# if (titleRel) { #> rel="{{ titleRel }}"<# } #>><# } #>{{ item.title }}<# if (titleLink.url) { #></a><# } #></{{{ tag }}}><# } #><# if ( settings.show_description !== 'no' && item.description ) { #><div class="digi-fancy-slider__description">{{{ item.description }}}</div><# } #><# if ( settings.show_button !== 'no' && item.slide_button && buttonLink.url ) { #><a class="digi-fancy-slider__button" href="{{ buttonLink.url }}"<# if (buttonLink.is_external) { #> target="_blank"<# } #><# if (buttonRel) { #> rel="{{ buttonRel }}"<# } #>>{{ item.slide_button }}</a><# } #></div><# if ( settings.show_slide_image !== 'no' && item.slide_image && item.slide_image.url ) { #><div class="digi-fancy-slider__media"><img class="digi-fancy-slider__image" src="{{ item.slide_image.url }}" alt="{{ item.slide_image.alt || '' }}"></div><# } #></li><# }); #></ul></div><# if (slides.length > 1 && settings.navigation !== 'none') { #><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div><# } #></section></div><# } #>
		<?php
	}
}
