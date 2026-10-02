<?php
namespace Digitalisimo\Tools;

defined( 'ABSPATH' ) || exit;

final class Elementor_Slider_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-slider-optimizado'; }
	public function get_title() { return 'Slider Optimizado'; }
	public function get_icon() { return 'eicon-slider-album'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'digitalisimo', 'slider', 'logos', 'banner', 'marquee' ); }
	public function get_style_depends() { return array( 'digitalisimo-slider-optimizado' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'content', array( 'label' => 'Contenido' ) );
		$this->add_control( 'mode', array( 'label' => 'Modo', 'type' => $c::SELECT, 'default' => 'multiple', 'options' => array( 'multiple' => 'Logos / imágenes múltiples', 'repeat' => 'Imagen repetida / banner' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ) ) );
		$repeater->add_control( 'alt', array( 'label' => 'Texto alternativo', 'type' => $c::TEXT, 'label_block' => true ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace opcional', 'type' => $c::URL, 'options' => array( 'url', 'is_external', 'nofollow' ), 'label_block' => true ) );
		$this->add_control( 'images', array( 'label' => 'Imágenes', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ alt || "Imagen" }}}', 'condition' => array( 'mode' => 'multiple' ) ) );
		$this->add_control( 'repeat_image', array( 'label' => 'Imagen / banner', 'type' => $c::MEDIA, 'condition' => array( 'mode' => 'repeat' ) ) );
		$this->add_control( 'repeat_alt', array( 'label' => 'Texto alternativo', 'type' => $c::TEXT, 'condition' => array( 'mode' => 'repeat' ) ) );
		$this->add_control( 'repeat_count', array( 'label' => 'Repeticiones', 'type' => $c::NUMBER, 'min' => 1, 'max' => 100, 'default' => 10, 'condition' => array( 'mode' => 'repeat' ) ) );
		$this->add_control( 'mirror', array( 'label' => 'Reflejar elementos alternados', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '', 'description' => 'Útil para unir ondas sin cortes.' ) );
		$this->add_control( 'label', array( 'label' => 'Nombre accesible del componente', 'type' => $c::TEXT, 'placeholder' => 'Logotipos de clientes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'movement', array( 'label' => 'Movimiento' ) );
		$this->add_control( 'animation', array( 'label' => 'Animación', 'type' => $c::SELECT, 'default' => 'continuous', 'options' => array( 'continuous' => 'Continua', 'off' => 'Desactivada' ) ) );
		$this->add_control( 'direction', array( 'label' => 'Dirección', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Izquierda', 'right' => 'Derecha' ), 'condition' => array( 'animation' => 'continuous' ) ) );
		$this->add_control( 'speed', array( 'label' => 'Duración de un recorrido (segundos)', 'type' => $c::NUMBER, 'default' => 30, 'min' => 1, 'max' => 600, 'condition' => array( 'animation' => 'continuous' ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => '--digi-duration: {{VALUE}}s;' ) ) );
		$this->add_control( 'infinite', array( 'label' => 'Loop infinito', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'animation' => 'continuous' ) ) );
		$this->add_control( 'pause', array( 'label' => 'Pausar al pasar el mouse', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '', 'condition' => array( 'animation' => 'continuous' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'visible', array( 'label' => 'Elementos visibles', 'type' => $c::NUMBER, 'min' => 1, 'max' => 30, 'default' => 10, 'tablet_default' => 8, 'mobile_default' => 5, 'selectors' => array( '{{WRAPPER}} .digi-slider' => '--digi-visible: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'default' => array( 'size' => 16, 'unit' => 'px' ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => '--digi-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'max_width', array( 'label' => 'Ancho máximo por elemento', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 20, 'max' => 1000 ) ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => '--digi-max-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'width', array( 'label' => 'Ancho del slider', 'type' => $c::SLIDER, 'size_units' => array( '%', 'px', 'vw' ), 'range' => array( '%' => array( 'min' => 1, 'max' => 100 ), 'px' => array( 'min' => 50, 'max' => 2000 ) ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => 'width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'height_mode', array( 'label' => 'Altura', 'type' => $c::SELECT, 'default' => 'auto', 'options' => array( 'auto' => 'Automática', 'fixed' => 'Personalizada' ) ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Altura personalizada', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 10, 'max' => 1000 ) ), 'condition' => array( 'height_mode' => 'fixed' ), 'selectors' => array( '{{WRAPPER}} .digi-slider' => '--digi-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'object_fit', array( 'label' => 'Ajuste de imagen', 'type' => $c::SELECT, 'default' => 'contain', 'options' => array( 'contain' => 'Contain (logos)', 'cover' => 'Cover (banner)', 'fill' => 'Fill' ), 'selectors' => array( '{{WRAPPER}} .digi-slider img' => 'object-fit: {{VALUE}};' ) ) );
		$this->add_control( 'natural_width', array( 'label' => 'Ancho natural de imagen', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '', 'condition' => array( 'mode' => 'repeat' ) ) );
		$this->add_control( 'align', array( 'label' => 'Alineación vertical', 'type' => $c::SELECT, 'default' => 'center', 'options' => array( 'start' => 'Arriba', 'center' => 'Centro', 'end' => 'Abajo' ), 'selectors' => array( '{{WRAPPER}} .digi-slider__group' => 'align-items: {{VALUE}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radio de borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-slider img' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'images_style', array( 'label' => 'Carga de imágenes', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño registrado', 'type' => $c::SELECT, 'default' => 'large', 'options' => $this->image_sizes() ) );
		$this->add_control( 'loading', array( 'label' => 'Carga', 'type' => $c::SELECT, 'default' => 'auto', 'options' => array( 'auto' => 'Automática', 'lazy' => 'Lazy', 'eager' => 'Eager' ) ) );
		$this->add_control( 'priority', array( 'label' => 'Prioridad', 'type' => $c::SELECT, 'default' => 'auto', 'options' => array( 'auto' => 'Automática', 'high' => 'Alta', 'low' => 'Baja' ) ) );
		$this->end_controls_section();
	}

	private function image_sizes() {
		$sizes = array( 'full' => 'Original' );
		foreach ( get_intermediate_image_sizes() as $name ) $sizes[ $name ] = $name;
		return $sizes;
	}

	private function image( $item, $settings, $decorative, $index ) {
		$id = absint( $item['image']['id'] ?? 0 );
		if ( ! $id ) return '';
		$alt = $decorative ? '' : trim( (string) ( $item['alt'] ?? '' ) );
		if ( ! $decorative && '' === $alt ) $alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$size = sanitize_key( $settings['image_size'] ?? 'large' );
		if ( ! in_array( $size, get_intermediate_image_sizes(), true ) && 'full' !== $size ) $size = 'large';
		$attrs = array( 'alt' => $alt, 'decoding' => 'async' );
		$loading = $settings['loading'] ?? 'auto';
		$priority = $settings['priority'] ?? 'auto';
		if ( 'high' === $priority && ! $decorative && 0 === $index ) $loading = 'eager';
		if ( in_array( $loading, array( 'lazy', 'eager' ), true ) ) $attrs['loading'] = $decorative && 'eager' === $loading ? 'lazy' : $loading;
		if ( ! $decorative && ( 'low' === $priority || ( 'high' === $priority && 0 === $index ) ) ) $attrs['fetchpriority'] = $priority;
		$attrs['sizes'] = '(max-width: 767px) 20vw, (max-width: 1024px) 13vw, 10vw';
		$html = wp_get_attachment_image( $id, $size, false, $attrs );
		if ( ! $html ) return '';
		$link = $item['link'] ?? array();
		$url = $decorative ? '' : esc_url( $link['url'] ?? '' );
		if ( $url ) {
			$rel = array();
			if ( ! empty( $link['nofollow'] ) ) $rel[] = 'nofollow';
			if ( ! empty( $link['is_external'] ) ) $rel[] = 'noopener';
			$html = '<a href="' . $url . '"' . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . '>' . $html . '</a>';
		}
		return $html;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = array();
		if ( 'repeat' === ( $s['mode'] ?? 'multiple' ) ) {
			$id = absint( $s['repeat_image']['id'] ?? 0 );
			if ( $id ) {
				$count = min( 100, max( 1, absint( $s['repeat_count'] ?? 10 ) ) );
				$items = array_fill( 0, $count, array( 'image' => $s['repeat_image'], 'alt' => $s['repeat_alt'] ?? '' ) );
			}
		} else {
			foreach ( (array) ( $s['images'] ?? array() ) as $item ) if ( absint( $item['image']['id'] ?? 0 ) ) $items[] = $item;
		}
		if ( ! $items ) return;
		$accessible_count = 'repeat' === ( $s['mode'] ?? 'multiple' ) ? 1 : count( $items );
		$animate = 'continuous' === ( $s['animation'] ?? 'continuous' );
		$infinite = $animate && 'yes' === ( $s['infinite'] ?? 'yes' );
		$mirror = 'yes' === ( $s['mirror'] ?? '' );
		// Una serie par conserva la alternancia de espejos en la unión del loop.
		if ( $infinite ) {
			$minimum = min( 30, max( 10, absint( $s['visible'] ?? 10 ), absint( $s['visible_tablet'] ?? 8 ), absint( $s['visible_mobile'] ?? 5 ) ) );
			$source = $items;
			while ( count( $items ) < $minimum || ( $mirror && count( $items ) % 2 ) ) $items[] = $source[ count( $items ) % count( $source ) ];
		}
		$classes = array( 'digi-slider' );
		if ( $animate ) $classes[] = $infinite ? 'digi-slider--infinite' : 'digi-slider--once';
		if ( 'right' === ( $s['direction'] ?? 'left' ) ) $classes[] = 'digi-slider--right';
		if ( 'yes' === ( $s['pause'] ?? '' ) ) $classes[] = 'digi-slider--pause';
		if ( 'yes' === ( $s['natural_width'] ?? '' ) && 'repeat' === ( $s['mode'] ?? '' ) ) $classes[] = 'digi-slider--natural';
		if ( 'fixed' === ( $s['height_mode'] ?? 'auto' ) ) $classes[] = 'digi-slider--fixed';
		$label = trim( (string) ( $s['label'] ?? '' ) );
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $label ? ' role="group" aria-label="' . esc_attr( $label ) . '"' : '' ) . '><div class="digi-slider__viewport"><div class="digi-slider__track">';
		$this->group( $items, $s, $mirror, false, $accessible_count );
		if ( $infinite ) $this->group( $items, $s, $mirror, true, 0 );
		echo '</div></div></div>';
	}

	private function group( $items, $settings, $mirror, $decorative, $accessible_count ) {
		echo '<div class="digi-slider__group"' . ( $decorative ? ' aria-hidden="true"' : '' ) . '>';
		foreach ( $items as $index => $item ) {
			$item_decorative = $decorative || $index >= $accessible_count;
			$html = $this->image( $item, $settings, $item_decorative, $index );
			if ( ! $html ) continue;
			echo '<div class="digi-slider__item' . ( $mirror && $index % 2 ? ' digi-slider__item--mirror' : '' ) . '"' . ( $item_decorative ? ' aria-hidden="true"' : '' ) . '>' . $html . '</div>';
		}
		echo '</div>';
	}
}
