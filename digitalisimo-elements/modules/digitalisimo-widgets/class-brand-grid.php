<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Cuadrícula de marcas: datos repetibles, imágenes responsivas y presentación sin JS. */
class Brand_Grid_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-brand-grid'; }
	public function get_title() { return 'Cuadrícula de marcas'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'marcas', 'logos', 'clientes', 'grid' ); }
	public function get_style_depends() { return array( 'digitalisimo-brand-grid' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_brand_items', array( 'label' => 'Marcas' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Logo', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'brand_name', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'link', array( 'label' => 'URL', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'website_link_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'brand_items', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'brand_name' => 'Marca 1' ), array( 'brand_name' => 'Marca 2' ), array( 'brand_name' => 'Marca 3' ) ), 'title_field' => '{{{ brand_name }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_brand_layout', array( 'label' => 'Cuadrícula' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->add_responsive_control( 'column_gap', array( 'label' => 'Separación horizontal', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__list' => 'column-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'row_gap', array( 'label' => 'Separación vertical', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__list' => 'row-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'brand_event', array( 'label' => 'Mostrar detalles', 'type' => $c::SELECT, 'default' => 'always', 'options' => array( 'always' => 'Siempre', 'hover-item' => 'Al apuntar o enfocar', 'click' => 'Al abrir' ) ) );
		$this->add_control( 'show_brand_name', array( 'label' => 'Mostrar nombre', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'brand_html_tag', array( 'label' => 'Etiqueta del nombre', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div', 'span' => 'Span' ), 'condition' => array( 'show_brand_name' => 'yes' ) ) );
		$this->add_control( 'show_website_link', array( 'label' => 'Mostrar enlace', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_brand_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'item_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__item' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__item' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'name_color', array( 'label' => 'Color del nombre', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-brand-grid__name' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function name_tag( $value ) {
		return in_array( $value, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $value : 'h3';
	}

	private static function valid_items( $items ) {
		if ( ! is_array( $items ) ) { return array(); }
		return array_values( array_filter( $items, static function ( $item ) {
			return is_array( $item ) && ( '' !== trim( (string) ( $item['brand_name'] ?? '' ) ) || ( is_array( $item['image'] ?? null ) && ! empty( $item['image']['url'] ) ) );
		} ) );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = self::valid_items( $settings['brand_items'] ?? array() );
		if ( ! $items ) { return; }
		$mode = in_array( $settings['brand_event'] ?? '', array( 'always', 'hover-item', 'click' ), true ) ? $settings['brand_event'] : 'always';
		$tag = self::name_tag( $settings['brand_html_tag'] ?? 'h3' );
		$size = in_array( $settings['image_size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $settings['image_size'] : 'medium';
		echo '<div class="digi-brand-grid digi-brand-grid--' . esc_attr( $mode ) . '"><ul class="digi-brand-grid__list">';
		foreach ( $items as $index => $item ) {
			$name = trim( (string) ( $item['brand_name'] ?? '' ) );
			$image = is_array( $item['image'] ?? null ) ? $item['image'] : array();
			$link = is_array( $item['link'] ?? null ) ? $item['link'] : array();
			$has_link = 'yes' === ( $settings['show_website_link'] ?? 'yes' ) && ! empty( $link['url'] );
			$show_name = 'yes' === ( $settings['show_brand_name'] ?? 'yes' ) && '' !== $name;
			$alt = $name;
			if ( ! empty( $image['id'] ) ) { $stored_alt = get_post_meta( absint( $image['id'] ), '_wp_attachment_image_alt', true ); if ( $stored_alt ) { $alt = $stored_alt; } }
			if ( $show_name && 'click' !== $mode ) { $alt = ''; }
			echo '<li class="digi-brand-grid__item">';
			if ( 'click' === $mode ) { echo '<details class="digi-brand-grid__details"><summary class="digi-brand-grid__summary" aria-label="' . esc_attr( '' !== $name ? $name : 'Mostrar marca' ) . '">'; }
			echo '<div class="digi-brand-grid__image">';
			if ( ! empty( $image['url'] ) ) {
				$attrs = array( 'alt' => $alt, 'class' => 'digi-brand-grid__logo', 'decoding' => 'async' );
				$html = ! empty( $image['id'] ) ? wp_get_attachment_image( absint( $image['id'] ), $size, false, $attrs ) : '';
				if ( $html ) { echo $html; }
				else { echo '<img class="digi-brand-grid__logo" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async">'; }
			}
			echo '</div>';
			if ( 'click' === $mode ) { echo '</summary>'; }
			echo '<div class="digi-brand-grid__content">';
			if ( $show_name ) { echo '<' . $tag . ' class="digi-brand-grid__name">' . esc_html( $name ) . '</' . $tag . '>'; }
			if ( $has_link ) {
				$text = trim( (string) ( $item['website_link_text'] ?? '' ) );
				if ( '' === $text ) { $text = '' !== $name ? 'Visitar ' . $name : 'Visitar sitio'; }
				$key = 'brand_grid_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				echo '<a class="digi-brand-grid__link" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $text ) . '</a>';
			}
			echo '</div>';
			if ( 'click' === $mode ) { echo '</details>'; }
			echo '</li>';
		}
		echo '</ul></div>';
	}

	protected function content_template() {
		?>
		<# var mode = _.contains( ['always','hover-item','click'], settings.brand_event ) ? settings.brand_event : 'always';
		var tag = _.contains( ['h2','h3','h4','h5','h6','div','span'], settings.brand_html_tag ) ? settings.brand_html_tag : 'h3';
		var items = _.filter( settings.brand_items || [], function( item ) { return item && ( item.brand_name || ( item.image && item.image.url ) ); } ); #>
		<# if ( items.length ) { #><div class="digi-brand-grid digi-brand-grid--{{ mode }}"><ul class="digi-brand-grid__list">
		<# _.each( items, function( item ) { var name = item.brand_name || ''; var showName = settings.show_brand_name !== '' && name; var link = item.link && item.link.url && settings.show_website_link !== '' ? item.link : null; var linkText = item.website_link_text || ( name ? 'Visitar ' + name : 'Visitar sitio' ); var rel = link ? [link.is_external ? 'noopener noreferrer' : '', link.nofollow ? 'nofollow' : ''].join(' ').trim() : ''; #>
		<li class="digi-brand-grid__item"><# if ( mode === 'click' ) { #><details class="digi-brand-grid__details"><summary class="digi-brand-grid__summary" aria-label="{{ name || 'Mostrar marca' }}"><# } #>
		<div class="digi-brand-grid__image"><# if ( item.image && item.image.url ) { #><img class="digi-brand-grid__logo" src="{{ item.image.url }}" alt="{{ showName && mode !== 'click' ? '' : name }}" decoding="async"><# } #></div>
		<# if ( mode === 'click' ) { #></summary><# } #><div class="digi-brand-grid__content">
		<# if ( showName ) { #><{{{ tag }}} class="digi-brand-grid__name">{{ name }}</{{{ tag }}}><# } #>
		<# if ( link ) { #><a class="digi-brand-grid__link" href="{{ link.url }}" <# if ( link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #>>{{ linkText }}</a><# } #>
		</div><# if ( mode === 'click' ) { #></details><# } #></li>
		<# } ); #></ul></div><# } #>
		<?php
	}
}
