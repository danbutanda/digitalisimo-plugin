<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Marquesina de textos, imágenes o titulares de entradas en movimiento continuo. Tiene botón de
 * pausa (WCAG 2.2.2), se detiene al enfocar y no se mueve con «reducir movimiento».
 */
class Marquee_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-marquee'; }
	public function get_title() { return 'Marquesina'; }
	public function get_icon() { return 'eicon-animation-text'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'marquesina', 'ticker', 'noticias', 'logos', 'movimiento' ); }
	public function get_style_depends() { return array( 'digitalisimo-marquee' ); }
	public function get_script_depends() { return array( 'digitalisimo-marquee' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_items', array( 'label' => 'Contenido' ) );
		$this->add_control( 'source', array( 'label' => 'Origen', 'type' => $c::SELECT, 'default' => 'items', 'options' => array( 'items' => 'Elementos escritos', 'posts' => 'Últimas entradas' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Elemento', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'items', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'text' => 'Primer elemento' ), array( 'text' => 'Segundo elemento' ) ), 'title_field' => '{{{ text }}}', 'condition' => array( 'source' => 'items' ) ) );
		$this->add_control( 'posts_count', array( 'label' => 'Número de entradas', 'type' => $c::NUMBER, 'default' => 5, 'min' => 1, 'max' => 30, 'condition' => array( 'source' => 'posts' ) ) );
		$this->add_control( 'label', array( 'label' => 'Rótulo', 'type' => $c::TEXT, 'default' => '' ) );
		$this->add_control( 'duration', array( 'label' => 'Duración de una vuelta (s)', 'type' => $c::NUMBER, 'default' => 30, 'min' => 5, 'max' => 300 ) );
		$this->add_control( 'direction', array( 'label' => 'Dirección', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Hacia la izquierda', 'right' => 'Hacia la derecha' ) ) );
		$this->add_control( 'pause_on_hover', array( 'label' => 'Pausar al pasar el cursor', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Marquesina', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .digi-marquee' => '--digi-marquee-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'image_height', array( 'label' => 'Alto de las imágenes', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 16, 'max' => 200 ) ), 'selectors' => array( '{{WRAPPER}} .digi-marquee__image' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-marquee' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-marquee' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'label_background', array( 'label' => 'Fondo del rótulo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-marquee__label' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .digi-marquee' ) );
		$this->end_controls_section();
	}

	/** Elementos `{text, image, link}` desde la lista o las últimas entradas publicadas. */
	private function entries( array $s ) {
		if ( 'posts' === ( $s['source'] ?? 'items' ) ) {
			$entries = array();
			foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 30, (int) ( $s['posts_count'] ?? 5 ) ) ), 'no_found_rows' => true, 'ignore_sticky_posts' => true ) ) as $post ) {
				$entries[] = array( 'text' => get_the_title( $post ), 'image' => array(), 'link' => array( 'url' => get_permalink( $post ) ) );
			}
			return $entries;
		}
		return is_array( $s['items'] ?? null ) ? $s['items'] : array();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = '';
		foreach ( $this->entries( $s ) as $index => $entry ) {
			if ( ! is_array( $entry ) ) { continue; }
			$text  = trim( wp_strip_all_tags( (string) ( $entry['text'] ?? '' ) ) );
			$image = (string) ( $entry['image']['url'] ?? '' );
			if ( '' === $text && '' === $image ) { continue; }
			$inner = ( '' !== $image ? '<img class="digi-marquee__image" src="' . esc_url( $image ) . '" alt="' . esc_attr( $text ) . '" loading="lazy">' : '' ) . ( '' !== $text && '' === $image ? '<span class="digi-marquee__text">' . esc_html( $text ) . '</span>' : '' );
			$url   = (string) ( $entry['link']['url'] ?? '' );
			if ( '' !== $url ) {
				$external = ! empty( $entry['link']['is_external'] );
				$inner    = '<a href="' . esc_url( $url ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . $inner . '</a>';
			}
			$items .= '<li class="digi-marquee__item">' . $inner . '</li>';
		}
		if ( '' === $items ) { return; }
		$label    = trim( wp_strip_all_tags( (string) ( $s['label'] ?? '' ) ) );
		$duration = max( 5, min( 300, (int) ( $s['duration'] ?? 30 ) ) );
		$classes  = 'digi-marquee digi-marquee--' . ( 'right' === ( $s['direction'] ?? 'left' ) ? 'right' : 'left' ) . ( 'yes' === ( $s['pause_on_hover'] ?? 'yes' ) ? ' digi-marquee--hover-pause' : '' );
		echo '<div class="' . esc_attr( $classes ) . '" style="--digi-marquee-duration:' . esc_attr( (string) $duration ) . 's" role="region" aria-label="' . esc_attr( '' !== $label ? $label : 'Marquesina' ) . '" data-digi-marquee>'
			. ( '' !== $label ? '<span class="digi-marquee__label">' . esc_html( $label ) . '</span>' : '' )
			. '<div class="digi-marquee__viewport"><div class="digi-marquee__track"><ul class="digi-marquee__list">' . $items . '</ul><ul class="digi-marquee__list" aria-hidden="true" inert>' . $items . '</ul></div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- elementos escapados al construirse.
			. '<button type="button" class="digi-marquee__toggle" aria-pressed="false" data-digi-marquee-toggle><span class="digi-marquee__toggle-text">Pausar</span></button>'
			. '</div>';
	}
}
