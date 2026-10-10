<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Barra fija con el avance de lectura de la página; decorativa para lectores de pantalla. */
class Reading_Progress_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-reading-progress'; }
	public function get_title() { return 'Progreso de lectura'; }
	public function get_icon() { return 'eicon-progress-tracker'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'lectura', 'progreso', 'scroll', 'barra' ); }
	public function get_style_depends() { return array( 'digitalisimo-reading-progress' ); }
	public function get_script_depends() { return array( 'digitalisimo-reading-progress' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content', array( 'label' => 'Progreso de lectura' ) );
		$this->add_control( 'position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'top', 'options' => array( 'top' => 'Arriba', 'bottom' => 'Abajo' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Barra', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-reading-progress' => '--digi-reading-color: {{VALUE}};' ) ) );
		$this->add_control( 'track_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-reading-progress' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'height', array( 'label' => 'Grosor', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 1, 'max' => 20 ) ), 'selectors' => array( '{{WRAPPER}} .digi-reading-progress' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$position = 'bottom' === ( $this->get_settings_for_display( 'position' ) ?? 'top' ) ? 'bottom' : 'top';
		echo '<div class="digi-reading-progress digi-reading-progress--' . esc_attr( $position ) . '" aria-hidden="true" data-digi-reading-progress><span class="digi-reading-progress__fill"></span></div>';
	}
}
