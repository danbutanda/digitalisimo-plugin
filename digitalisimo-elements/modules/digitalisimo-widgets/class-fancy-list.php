<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Lista de tarjetas: reconstrucción funcional de Fancy List (Element Pack Pro, GPLv3). */
final class Fancy_List_Widget extends \Elementor\Widget_Base {
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
				echo '<a class="digi-fancy-list__card" ' . $this->get_render_attribute_string( $key ) . '>';
			} else { echo '<div class="digi-fancy-list__card">'; }
			if ( 'yes' === ( $settings['show_number_icon'] ?? '' ) ) { echo '<span class="digi-fancy-list__number" aria-hidden="true">' . (int) $number . '</span>'; }
			if ( ! empty( $img['url'] ) ) {
				$image_id = absint( $img['id'] ?? 0 );
				if ( $image_id ) {
					$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					echo wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'digi-fancy-list__image', 'alt' => $alt ? $alt : $title, 'loading' => 'lazy' ) );
				} else { echo '<img class="digi-fancy-list__image" src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">'; }
			}
			if ( '' !== $title || '' !== $details ) {
				echo '<span class="digi-fancy-list__content">';
				if ( '' !== $title ) { echo '<' . $tag . ' class="digi-fancy-list__title">' . esc_html( $title ) . '</' . $tag . '>'; }
				if ( '' !== $details ) { echo '<span class="digi-fancy-list__details">' . esc_html( $details ) . '</span>'; }
				echo '</span>';
			}
			if ( ! empty( $icon['value'] ) ) { echo '<span class="digi-fancy-list__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
			echo $linked ? '</a>' : '</div>';
			echo '</li>';
		}
		echo '</ul></div>';
	}
}
