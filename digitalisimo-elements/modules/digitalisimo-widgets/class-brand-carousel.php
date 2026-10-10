<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-carousel-engine.php';

/** Carrusel de marcas con motor nativo compartido; referencia funcional Element Pack Pro 9.9.1. */
class Brand_Carousel_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-brand-carousel'; }
	public function get_title() { return 'Carrusel de marcas'; }
	public function get_icon() { return 'eicon-logo'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'marcas', 'logos', 'clientes', 'carrusel' ); }
	public function get_style_depends() { return array( 'digitalisimo-carousel-engine', 'digitalisimo-brand-carousel' ); }
	public function get_script_depends() { return array( 'digitalisimo-carousel-engine' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'ep_section_brand', array( 'label' => 'Marcas' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Logo', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'brand_name', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'link', array( 'label' => 'URL', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'website_link_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'brand_items', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'brand_name' => 'Marca 1' ), array( 'brand_name' => 'Marca 2' ), array( 'brand_name' => 'Marca 3' ) ), 'title_field' => '{{{ brand_name }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_additional_settings', array( 'label' => 'Carrusel' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Marcas visibles', 'type' => $c::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-carousel' => '--digi-carousel-visible: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-carousel' => '--digi-carousel-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => array( 'arrows' => 'Flechas', 'none' => 'Desactivada' ) ) );
		$this->add_control( 'item_match_height', array( 'label' => 'Igualar altura', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'show_brand_name', array( 'label' => 'Mostrar nombre', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'brand_html_tag', array( 'label' => 'Etiqueta del nombre', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'span' => 'span' ), 'condition' => array( 'show_brand_name' => 'yes' ) ) );
		$this->add_control( 'show_website_link', array( 'label' => 'Mostrar texto del enlace', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_brand_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'item_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-brand-carousel__card' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-brand-carousel__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'name_color', array( 'label' => 'Color del nombre', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-brand-carousel__name' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = isset( $settings['brand_items'] ) && is_array( $settings['brand_items'] ) ? $settings['brand_items'] : array();
		if ( ! $items ) { return; }
		$tag = in_array( $settings['brand_html_tag'] ?? '', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $settings['brand_html_tag'] : 'h3';
		$size = in_array( $settings['image_size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $settings['image_size'] : 'medium';
		$cards = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$name = trim( (string) ( $item['brand_name'] ?? '' ) );
			$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();
			if ( '' !== $name || ! empty( $image['url'] ) ) { $cards[] = $item; }
		}
		if ( ! $cards ) { return; }
		$navigation = 'none' !== ( $settings['navigation'] ?? 'arrows' ) && count( $cards ) > 1;
		echo '<div class="digi-brand-carousel">';
		Carousel_Engine::open( 'Carrusel de marcas', count( $cards ) );
		foreach ( $cards as $index => $item ) {
			$name = trim( (string) ( $item['brand_name'] ?? '' ) );
			$image = isset( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();
			$link = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
			$linked = ! empty( $link['url'] );
			$match = 'yes' === ( $settings['item_match_height'] ?? 'yes' ) ? ' digi-brand-carousel__card--stretch' : '';
			echo '<li class="digi-carousel__slide digi-brand-carousel__slide">';
			if ( $linked ) {
				$key = 'brand_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				echo '<a class="digi-brand-carousel__card' . $match . '" ' . $this->get_render_attribute_string( $key ) . '>';
			} else { echo '<div class="digi-brand-carousel__card' . $match . '">'; }
			if ( ! empty( $image['url'] ) ) {
				$image_id = absint( $image['id'] ?? 0 );
				$alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
				$alt = $alt ? $alt : $name;
				$attrs = array( 'class' => 'digi-brand-carousel__image', 'alt' => $alt );
				$image_html = $image_id ? wp_get_attachment_image( $image_id, $size, false, $attrs ) : '';
				if ( $image_html ) { echo $image_html; }
				else { echo '<img class="digi-brand-carousel__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '">'; }
			}
			if ( 'yes' === ( $settings['show_brand_name'] ?? 'yes' ) && '' !== $name ) { echo '<' . $tag . ' class="digi-brand-carousel__name">' . esc_html( $name ) . '</' . $tag . '>'; }
			$website = trim( (string) ( $item['website_link_text'] ?? '' ) );
			if ( $linked && 'yes' === ( $settings['show_website_link'] ?? 'yes' ) && '' !== $website ) { echo '<span class="digi-brand-carousel__website">' . esc_html( $website ) . '</span>'; }
			echo $linked ? '</a>' : '</div>';
			echo '</li>';
		}
		Carousel_Engine::close( $navigation );
		echo '</div>';
	}

	protected function content_template() {
		?>
		<#
		var titleTag = _.contains( ['h2','h3','h4','h5','h6','div','span'], settings.brand_html_tag ) ? settings.brand_html_tag : 'h3';
		var cards = _.filter( settings.brand_items || [], function( item ) { return item && ( item.brand_name || ( item.image && item.image.url ) ); } );
		#>
		<# if ( cards.length ) { #>
		<div class="digi-brand-carousel"><section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="Carrusel de marcas" style="--digi-carousel-count:{{ cards.length }}">
		<div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides">
		<# _.each( cards, function( item ) {
			var href = item.link && item.link.url ? item.link.url : '';
			var rel = item.link ? [item.link.is_external ? 'noopener noreferrer' : '', item.link.nofollow ? 'nofollow' : ''].join(' ').trim() : '';
			var match = settings.item_match_height === 'yes' || settings.item_match_height == null ? ' digi-brand-carousel__card--stretch' : '';
		#>
		<li class="digi-carousel__slide digi-brand-carousel__slide">
		<# if ( href ) { #><a class="digi-brand-carousel__card{{ match }}" href="{{ href }}" <# if ( item.link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #>><# } else { #><div class="digi-brand-carousel__card{{ match }}"><# } #>
		<# if ( item.image && item.image.url ) { #><img class="digi-brand-carousel__image" src="{{ item.image.url }}" alt="{{ item.brand_name || '' }}"><# } #>
		<# if ( settings.show_brand_name !== '' && item.brand_name ) { #><{{{ titleTag }}} class="digi-brand-carousel__name">{{ item.brand_name }}</{{{ titleTag }}}><# } #>
		<# if ( href && settings.show_website_link !== '' && item.website_link_text ) { #><span class="digi-brand-carousel__website">{{ item.website_link_text }}</span><# } #>
		<# if ( href ) { #></a><# } else { #></div><# } #>
		</li>
		<# } ); #>
		</ul></div>
		<# if ( settings.navigation !== 'none' && cards.length > 1 ) { #><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div><# } #>
		</section></div>
		<# } #>
		<?php
	}
}
