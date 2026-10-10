<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_shape( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_style_is() { return true; }
	function wp_script_is() { return true; }
	function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
	function wp_enqueue_script( $h ) { $GLOBALS['enqueued'][] = $h; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-legacy/class-shapes.php';
	use Digitalisimo\Elements\Legacy\Shapes;

	check_shape( '' === Shapes::markup( array( 'bdt_shape_builder_list' => array( array( 'shape_type' => 'circle' ) ) ), 'e1' ), 'Sin activar no hay figuras.' );
	$html = Shapes::markup( array( 'bdt_shape_builder_enable' => 'yes', 'bdt_shape_builder_list' => array(
		array( '_id' => 'a1', 'shape_type' => 'triangle', 'shape_color' => '#ff0000' ),
		array( '_id' => 'a2', 'shape_type' => 'oval', 'shape_fill_type' => 'gradient', 'shape_gradient_color_1' => 'red;}', 'shape_gradient_angle' => array( 'size' => 45 ), 'shape_builder_animation_popover' => 'yes', 'animation_name' => 'zoom-in<x>', 'animation_duration' => array( 'size' => 2 ) ),
		array( '_id' => 'a3', 'shape_type' => 'no-existe' ),
		array( '_id' => 'a4', 'shape_type' => 'custom', 'custom_shape_upload' => array( 'id' => 0 ) ),
	) ), 'e1' );
	check_shape( str_contains( $html, 'class="digi-legacy-shape elementor-repeater-item-a1" aria-hidden="true"' ) && str_contains( $html, '<g fill="#ff0000"><polygon points="10,10 90,10 50,90"/>' ), 'La figura usa su color y su repetidor para el CSS guardado.' );
	check_shape( str_contains( $html, 'url(#digi-shape-a2)' ) && str_contains( $html, 'stop-color="#08AEEC"' ) && str_contains( $html, 'rotate(45)' ), 'El degradado descarta colores inválidos y conserva el ángulo.' );
	check_shape( str_contains( $html, 'data-digi-shape-animation="zoom-inx"' ) && str_contains( $html, '--digi-shape-duration:2s' ) && in_array( 'digitalisimo-legacy-shapes', $GLOBALS['enqueued'], true ), 'La animación de entrada se marca y carga su script.' );
	check_shape( ! str_contains( $html, 'a3' ) && ! str_contains( $html, 'a4' ) && 7 === count( Shapes::shapes() ), 'Figuras desconocidas o SVG ausente no se pintan.' );
	echo "DIGITALÍSIMO Elements: figuras de Element Pack validadas.\n";
}
