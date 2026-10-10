<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Lista de tarjetas: reconstrucción funcional de Fancy List (Element Pack Pro, GPLv3). */
class Fancy_List_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-fancy-list'; }
	public function get_title() { return 'Lista destacada'; }
	public function get_icon() { return 'eicon-bullet-list'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'lista', 'tarjetas', 'iconos', 'fancy list' ); }
	public function get_style_depends() { return array( 'digitalisimo-fancy-list' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_layout', array( 'label' => 'Lista destacada' ) );
		$this->add_control( 'layout_style', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'style-1', 'options' => array( 'style-1' => 'Cuadrícula', 'style-2' => 'Profundidad', 'style-3' => 'Énfasis' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'text', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true, 'default' => 'Elemento' ) );
		$repeater->add_control( 'text_details', array( 'label' => 'Descripción', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'list_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$repeater->add_control( 'img', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'icon_list', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'text' => 'Elemento 1' ), array( 'text' => 'Elemento 2' ), array( 'text' => 'Elemento 3' ) ), 'title_field' => '{{{ text }}}' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_options', array( 'label' => 'Opciones' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '1', 'tablet_default' => '1', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__items' => 'grid-template-columns: repeat({{VALUE}}, minmax(0,1fr));' ) ) );
		$this->add_responsive_control( 'list_item_space_between', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__items' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'show_number_icon', array( 'label' => 'Mostrar números', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'title_tags', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h4', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'div', 'span' => 'span' ) ) );
		$this->add_control( 'content_position', array( 'label' => 'Posición del contenido', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Izquierda', 'right' => 'Derecha' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_item_style', array( 'label' => 'Tarjetas', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'list_item_bg_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__card' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'list_item_hover_bg_color', array( 'label' => 'Fondo al pasar', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} a.digi-fancy-list__card:hover, {{WRAPPER}} a.digi-fancy-list__card:focus-visible' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'list_item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'list_item_border_radius', array( 'label' => 'Radio de borde', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_text_style', array( 'label' => 'Texto', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .digi-fancy-list__title' ) );
		$this->add_control( 'des_text_color', array( 'label' => 'Color de la descripción', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__details' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'sub_title_typography', 'selector' => '{{WRAPPER}} .digi-fancy-list__details' ) );
		$this->add_control( 'title_color_hover', array( 'label' => 'Título al pasar', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} a.digi-fancy-list__card:hover .digi-fancy-list__title, {{WRAPPER}} a.digi-fancy-list__card:focus-visible .digi-fancy-list__title' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_media_style', array( 'label' => 'Imagen e iconos', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'image_size', array( 'label' => 'Ancho de imagen', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 16, 'max' => 320 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__image' => 'width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => 'Color del icono', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__icon' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'right_icon_bg_color', array( 'label' => 'Fondo del icono', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__icon' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'number_icon_color', array( 'label' => 'Color del número', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__number' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'icon_bg_color', array( 'label' => 'Fondo del número', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-list__number' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ? $settings['icon_list'] : array();
		if ( ! $items ) { return; }
		$style = in_array( $settings['layout_style'] ?? '', array( 'style-1', 'style-2', 'style-3' ), true ) ? $settings['layout_style'] : 'style-1';
		$tag = in_array( $settings['title_tags'] ?? '', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $settings['title_tags'] : 'h4';
		$right = 'right' === ( $settings['content_position'] ?? 'left' );
		$number = 0;
		echo '<div class="digi-fancy-list digi-fancy-list--' . esc_attr( $style ) . ( $right ? ' digi-fancy-list--reverse' : '' ) . '"><ul class="digi-fancy-list__items">';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$title = trim( (string) ( $item['text'] ?? '' ) );
			$details = trim( (string) ( $item['text_details'] ?? '' ) );
			$img = isset( $item['img'] ) && is_array( $item['img'] ) ? $item['img'] : array();
			$icon = isset( $item['list_icon'] ) && is_array( $item['list_icon'] ) ? $item['list_icon'] : array();
			if ( '' === $title && '' === $details && empty( $img['url'] ) && empty( $icon['value'] ) ) { continue; }
			++$number;
			$link = isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array();
			$linked = ! empty( $link['url'] );
			echo '<li class="digi-fancy-list__item">';
			if ( $linked ) {
				$key = 'fancy_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				echo '<a class="digi-fancy-list__card" ' . $this->get_render_attribute_string( $key ) . '>';
			} else { echo '<div class="digi-fancy-list__card">'; }
			if ( 'yes' === ( $settings['show_number_icon'] ?? '' ) ) { echo '<span class="digi-fancy-list__number" aria-hidden="true">' . (int) $number . '</span>'; }
			if ( ! empty( $img['url'] ) ) {
				$image_id = absint( $img['id'] ?? 0 );
				if ( $image_id ) {
					$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					$image_html = wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'digi-fancy-list__image', 'alt' => $alt ? $alt : $title ) );
					if ( $image_html ) { echo $image_html; }
					else { echo '<img class="digi-fancy-list__image" src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $title ) . '">'; }
				} else { echo '<img class="digi-fancy-list__image" src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $title ) . '">'; }
			}
			if ( '' !== $title || '' !== $details ) {
				echo '<div class="digi-fancy-list__content">';
				if ( '' !== $title ) { echo '<' . $tag . ' class="digi-fancy-list__title">' . esc_html( $title ) . '</' . $tag . '>'; }
				if ( '' !== $details ) { echo '<span class="digi-fancy-list__details">' . esc_html( $details ) . '</span>'; }
				echo '</div>';
			}
			if ( ! empty( $icon['value'] ) ) { echo '<span class="digi-fancy-list__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
			echo $linked ? '</a>' : '</div>';
			echo '</li>';
		}
		echo '</ul></div>';
	}

	protected function content_template() {
		?>
		<#
		var style = _.contains( ['style-1', 'style-2', 'style-3'], settings.layout_style ) ? settings.layout_style : 'style-1';
		var tag = _.contains( ['h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span'], settings.title_tags ) ? settings.title_tags : 'h4';
		var reverse = settings.content_position === 'right' ? ' digi-fancy-list--reverse' : '';
		var number = 0;
		#>
		<div class="digi-fancy-list digi-fancy-list--{{ style }}{{ reverse }}"><ul class="digi-fancy-list__items">
		<# _.each( settings.icon_list || [], function( item ) {
			if ( ! item ) return;
			var title = item.text || '';
			var details = item.text_details || '';
			var img = item.img && item.img.url ? item.img.url : '';
			var icon = item.list_icon && item.list_icon.value;
			if ( ! title && ! details && ! img && ! icon ) return;
			var href = item.link && item.link.url ? item.link.url : '';
			var rel = item.link ? [item.link.is_external ? 'noopener noreferrer' : '', item.link.nofollow ? 'nofollow' : ''].join(' ').trim() : '';
			number++;
		#>
		<li class="digi-fancy-list__item">
		<# if ( href ) { #><a class="digi-fancy-list__card" href="{{ href }}" <# if ( item.link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #>><# } else { #><div class="digi-fancy-list__card"><# } #>
		<# if ( settings.show_number_icon === 'yes' ) { #><span class="digi-fancy-list__number" aria-hidden="true">{{ number }}</span><# } #>
		<# if ( img ) { #><img class="digi-fancy-list__image" src="{{ img }}" alt="{{ item.img.alt || title }}"><# } #>
		<# if ( title || details ) { #><div class="digi-fancy-list__content"><# if ( title ) { #><{{{ tag }}} class="digi-fancy-list__title">{{ title }}</{{{ tag }}}><# } #><# if ( details ) { #><span class="digi-fancy-list__details">{{ details }}</span><# } #></div><# } #>
		<# if ( icon ) { var renderedIcon = elementor.helpers.renderIcon( view, item.list_icon, { 'aria-hidden': true }, 'i', 'object' ); if ( renderedIcon && renderedIcon.rendered ) { #><span class="digi-fancy-list__icon" aria-hidden="true">{{{ renderedIcon.value }}}</span><# } } #>
		<# if ( href ) { #></a><# } else { #></div><# } #>
		</li>
		<# } ); #>
		</ul></div>
		<?php
	}
}
