<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Movimiento opcional, aislado por elemento y cargado sólo cuando se utiliza. */
final class Floating_Effects_Extension {
	public static function init() {
		foreach ( array(
			'elementor/element/common/section_effects/after_section_start',
			'elementor/element/container/section_effects/after_section_start',
			'elementor/element/section/section_effects/after_section_start',
			'elementor/element/column/section_effects/after_section_start',
		) as $hook ) {
			add_action( $hook, array( __CLASS__, 'controls' ), 10, 2 );
		}
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'preview_script' ) );
	}

	public static function controls( $element, $args ) {
		$c = '\\Elementor\\Controls_Manager';
		$element->add_control( 'digitalisimo_float_enable', array(
			'label' => 'Movimiento flotante',
			'type' => $c::SWITCHER,
			'return_value' => 'yes',
			'default' => '',
			'separator' => 'before',
		) );
		$element->add_control( 'digitalisimo_float_distance', array(
			'label' => 'Distancia vertical (px)',
			'type' => $c::NUMBER,
			'min' => 1,
			'max' => 40,
			'default' => 12,
			'condition' => array( 'digitalisimo_float_enable' => 'yes' ),
		) );
		$element->add_control( 'digitalisimo_float_duration', array(
			'label' => 'Duración (segundos)',
			'type' => $c::NUMBER,
			'min' => 2,
			'max' => 12,
			'default' => 5,
			'condition' => array( 'digitalisimo_float_enable' => 'yes' ),
		) );
	}

	public static function scripts() {
		if ( ! wp_script_is( 'digitalisimo-floating-effects', 'registered' ) ) {
			wp_register_script( 'digitalisimo-floating-effects', plugins_url( 'modules/digitalisimo-widgets/assets/js/floating-effects.js', DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-frontend' ), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
	}

	public static function preview_script() {
		self::scripts();
		wp_enqueue_script( 'digitalisimo-floating-effects' );
	}

	public static function before_render( $element ) {
		if ( ! method_exists( $element, 'get_settings' ) || 'yes' !== $element->get_settings( 'digitalisimo_float_enable' ) ) { return; }
		$distance = (int) $element->get_settings( 'digitalisimo_float_distance' );
		$duration = (float) $element->get_settings( 'digitalisimo_float_duration' );
		$distance = max( 1, min( 40, $distance > 0 ? $distance : 12 ) );
		$duration = max( 2, min( 12, $duration > 0 ? $duration : 5 ) );
		$element->add_render_attribute( '_wrapper', 'data-digi-float', '1' );
		$element->add_render_attribute( '_wrapper', 'data-digi-float-distance', (string) $distance );
		$element->add_render_attribute( '_wrapper', 'data-digi-float-duration', (string) $duration );
		self::scripts();
		wp_enqueue_script( 'digitalisimo-floating-effects' );
	}
}
