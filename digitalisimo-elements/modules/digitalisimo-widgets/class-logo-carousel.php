<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-carousel-engine.php';

/** Logos en carrusel: selección múltiple o elementos con enlace individual. */
final class Logo_Carousel_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-logo-carousel'; }
	public function get_title() { return 'Carrusel de logotipos'; }
	public function get_icon() { return 'eicon-logo'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'logo', 'clientes', 'marcas', 'carrusel' ); }
	public function get_style_depends() { return array( 'digitalisimo-carousel-engine', 'digitalisimo-logo-carousel' ); }
	public function get_script_depends() { return array( 'digitalisimo-carousel-engine' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_logos', array( 'label' => 'Logotipos' ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varios logotipos', 'type' => $c::GALLERY, 'description' => 'Las imágenes seleccionadas se muestran primero. Sus textos alternativos se toman de Medios.' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Logotipo', 'type' => $c::MEDIA ) );
		$repeater->add_control( 'name', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL ) );
		$this->add_control( 'logo_items', array( 'label' => 'Logotipos con enlace individual', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ name }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_carousel', array( 'label' => 'Carrusel' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Logotipos visibles', 'type' => $c::SELECT, 'default' => '4', 'tablet_default' => '3', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-carousel' => '--digi-carousel-visible: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-carousel' => '--digi-carousel-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Altura máxima', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 50, 'max' => 500 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logo-carousel__image' => 'max-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => array( 'arrows' => 'Flechas', 'none' => 'Desactivada' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = array();
		foreach ( array( 'gallery_images', 'logo_items' ) as $key ) {
			foreach ( is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array() as $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : $item;
				if ( empty( $image['url'] ) && ! empty( $image['id'] ) ) { $image['url'] = wp_get_attachment_url( absint( $image['id'] ) ); }
				if ( ! empty( $image['url'] ) ) { $items[] = array( 'image' => $image, 'name' => (string) ( $item['name'] ?? '' ), 'link' => $item['link'] ?? array() ); }
			}
		}
		if ( ! $items ) { return; }
		$size = in_array( $settings['image_size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $settings['image_size'] : 'medium';
		echo '<div class="digi-logo-carousel">';
		Carousel_Engine::open( 'Carrusel de logotipos', count( $items ) );
		foreach ( $items as $index => $item ) {
			$image = $item['image'];
			$id = absint( $image['id'] ?? 0 );
			$alt = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
			if ( '' === $alt ) { $alt = trim( $item['name'] ); }
			if ( '' === $alt && ! empty( $image['alt'] ) ) { $alt = trim( (string) $image['alt'] ); }
			if ( '' === $alt && $id ) { $alt = trim( (string) get_the_title( $id ) ); }
			$attrs = array( 'class' => 'digi-logo-carousel__image', 'alt' => $alt );
			if ( $index > 0 ) { $attrs['loading'] = 'lazy'; }
			$html = $id ? wp_get_attachment_image( $id, $size, false, $attrs ) : '';
			if ( ! $html ) { $html = '<img class="digi-logo-carousel__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '"' . ( $index > 0 ? ' loading="lazy"' : '' ) . '>'; }
			$link = is_array( $item['link'] ) ? $item['link'] : array();
			$linked = ! empty( $link['url'] ) && '' !== $alt;
			echo '<li class="digi-carousel__slide digi-logo-carousel__slide">';
			if ( $linked ) { $key = 'logo_link_' . $index; $this->add_link_attributes( $key, $link ); echo '<a class="digi-logo-carousel__card" ' . $this->get_render_attribute_string( $key ) . '>'; }
			else { echo '<div class="digi-logo-carousel__card">'; }
			echo $html;
			echo $linked ? '</a>' : '</div>';
			echo '</li>';
		}
		Carousel_Engine::close( 'none' !== ( $settings['navigation'] ?? 'arrows' ) && count( $items ) > 1 );
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var images = _.map( settings.gallery_images || [], function( image ) { return { image: image }; } ).concat( settings.logo_items || [] ); #>
		<# if ( images.length ) { #>
		<div class="digi-logo-carousel"><section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="Carrusel de logotipos" style="--digi-carousel-count:{{ images.length }}"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides">
		<# _.each( images, function( item ) { if ( ! item.image || ! item.image.url ) return; var alt = item.name || item.image.alt || ''; var href = alt && item.link && item.link.url ? item.link.url : ''; #>
		<li class="digi-carousel__slide digi-logo-carousel__slide"><# if ( href ) { #><a class="digi-logo-carousel__card" href="{{ href }}" <# if ( item.link.is_external ) { #>target="_blank" rel="noopener noreferrer"<# } #>><# } else { #><div class="digi-logo-carousel__card"><# } #><img class="digi-logo-carousel__image" src="{{ item.image.url }}" alt="{{ alt }}" loading="lazy"><# if ( href ) { #></a><# } else { #></div><# } #></li>
		<# } ); #>
		</ul></div><# if ( settings.navigation !== 'none' && images.length > 1 ) { #><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div><# } #></section></div>
		<# } #>
		<?php
	}
}
