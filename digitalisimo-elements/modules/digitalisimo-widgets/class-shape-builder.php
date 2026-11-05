<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Figuras decorativas básicas; no incorpora SVG remoto ni motor de animación. */
final class Shape_Builder_Extension {
	private static $shapes = array(
		'circle' => 'circle(50%)',
		'diamond' => 'polygon(50% 0,100% 50%,50% 100%,0 50%)',
		'triangle' => 'polygon(50% 0,100% 100%,0 100%)',
	);

	public static function init() {
		add_action( 'elementor/element/heading/section_title_style/after_section_start', array( __CLASS__, 'controls' ), 20, 2 );
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 2 );
		}
	}

	public static function controls( $element, $args ) {
		$c = '\\Elementor\\Controls_Manager';
		$element->add_control( 'digitalisimo_shape_enable', array(
			'label' => 'Figura decorativa al final',
			'type' => $c::SWITCHER,
			'return_value' => 'yes',
			'default' => '',
			'separator' => 'before',
		) );
		$condition = array( 'digitalisimo_shape_enable' => 'yes' );
		$element->add_control( 'digitalisimo_shape_type', array(
			'label' => 'Forma',
			'type' => $c::SELECT,
			'options' => array( 'circle' => 'Círculo', 'diamond' => 'Rombo', 'triangle' => 'Triángulo' ),
			'default' => 'circle',
			'condition' => $condition,
		) );
		$element->add_control( 'digitalisimo_shape_color', array(
			'label' => 'Color',
			'type' => $c::COLOR,
			'default' => '#3f51ef',
			'condition' => $condition,
		) );
		foreach ( array( 'size' => array( 'Tamaño (px)', 8, 300, 48 ), 'gap' => array( 'Separación (px)', 0, 100, 12 ) ) as $key => $field ) {
			$element->add_control( 'digitalisimo_shape_' . $key, array(
				'label' => $field[0],
				'type' => $c::NUMBER,
				'min' => $field[1],
				'max' => $field[2],
				'default' => $field[3],
				'condition' => $condition,
			) );
		}
	}

	public static function render_content( $content, $widget ) {
		if ( ! is_string( $content ) || ! method_exists( $widget, 'get_name' ) || 'heading' !== $widget->get_name() || ! method_exists( $widget, 'get_settings' ) || 'yes' !== $widget->get_settings( 'digitalisimo_shape_enable' ) ) { return $content; }
		$shape = (string) $widget->get_settings( 'digitalisimo_shape_type' );
		if ( ! isset( self::$shapes[ $shape ] ) ) { $shape = 'circle'; }
		$color = sanitize_hex_color( (string) $widget->get_settings( 'digitalisimo_shape_color' ) );
		if ( ! $color ) { $color = '#3f51ef'; }
		$size = self::limit( $widget->get_settings( 'digitalisimo_shape_size' ), 8, 300, 48 );
		$gap = self::limit( $widget->get_settings( 'digitalisimo_shape_gap' ), 0, 100, 12 );
		$style = sprintf( 'display:inline-block;vertical-align:middle;margin-inline-start:%dpx;width:%dpx;height:%dpx;background:%s;clip-path:%s;pointer-events:none;', $gap, $size, $size, $color, self::$shapes[ $shape ] );
		return $content . '<span aria-hidden="true" class="digitalisimo-shape" style="' . esc_attr( $style ) . '"></span>';
	}

	private static function limit( $value, $min, $max, $fallback ) {
		return is_numeric( $value ) ? max( $min, min( $max, (int) $value ) ) : $fallback;
	}
}
