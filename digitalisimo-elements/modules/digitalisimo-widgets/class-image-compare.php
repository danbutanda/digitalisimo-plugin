<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Comparador antes/después con un control deslizante nativo (teclado y lectores de pantalla). */
class Image_Compare_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-image-compare'; }
	public function get_title() { return 'Comparador de imágenes'; }
	public function get_icon() { return 'eicon-image-before-after'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'antes', 'después', 'comparar', 'imagen' ); }
	public function get_style_depends() { return array( 'digitalisimo-image-compare' ); }
	public function get_script_depends() { return array( 'digitalisimo-image-compare' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_images', array( 'label' => 'Imágenes' ) );
		$this->add_control( 'before_image', array( 'label' => 'Antes', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'after_image', array( 'label' => 'Después', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'before_label', array( 'label' => 'Etiqueta de antes', 'type' => $c::TEXT, 'default' => 'Antes' ) );
		$this->add_control( 'after_label', array( 'label' => 'Etiqueta de después', 'type' => $c::TEXT, 'default' => 'Después' ) );
		$this->add_control( 'orientation', array( 'label' => 'Dirección', 'type' => $c::SELECT, 'default' => 'horizontal', 'options' => array( 'horizontal' => 'Horizontal', 'vertical' => 'Vertical' ) ) );
		$this->add_control( 'start', array( 'label' => 'Posición inicial (%)', 'type' => $c::NUMBER, 'default' => 50, 'min' => 0, 'max' => 100 ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Divisor', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'bar_color', array( 'label' => 'Color del divisor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-compare' => '--digi-compare-bar: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$before = (string) ( $s['before_image']['url'] ?? '' );
		$after  = (string) ( $s['after_image']['url'] ?? '' );
		if ( '' === $before || '' === $after ) { return; }
		$start  = max( 0, min( 100, (int) ( $s['start'] ?? 50 ) ) );
		$vert   = 'vertical' === ( $s['orientation'] ?? 'horizontal' );
		$bl     = trim( wp_strip_all_tags( (string) ( $s['before_label'] ?? '' ) ) );
		$al     = trim( wp_strip_all_tags( (string) ( $s['after_label'] ?? '' ) ) );
		echo '<div class="digi-compare digi-compare--' . ( $vert ? 'vertical' : 'horizontal' ) . '" style="--digi-compare-pos:' . esc_attr( (string) $start ) . '%" data-digi-compare>'
			. '<img class="digi-compare__after" src="' . esc_url( $after ) . '" alt="' . esc_attr( $al ) . '">'
			. '<img class="digi-compare__before" src="' . esc_url( $before ) . '" alt="' . esc_attr( $bl ) . '">'
			. ( '' !== $bl ? '<span class="digi-compare__label digi-compare__label--before" aria-hidden="true">' . esc_html( $bl ) . '</span>' : '' )
			. ( '' !== $al ? '<span class="digi-compare__label digi-compare__label--after" aria-hidden="true">' . esc_html( $al ) . '</span>' : '' )
			. '<input class="digi-compare__range" type="range" min="0" max="100" value="' . esc_attr( (string) $start ) . '" aria-label="' . esc_attr( 'Comparar ' . ( '' !== $bl ? $bl : 'antes' ) . ' y ' . ( '' !== $al ? $al : 'después' ) ) . '"' . ( $vert ? ' aria-orientation="vertical"' : '' ) . '>'
			. '</div>';
	}
}
