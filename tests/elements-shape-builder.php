<?php
namespace Elementor { class Controls_Manager { const SWITCHER = 'switcher'; const SELECT = 'select'; const COLOR = 'color'; const NUMBER = 'number'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $hook ] = $callback; }
	function sanitize_hex_color( $value ) { return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : ''; }
	function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	class Fake_Shape_Heading {
		public $settings = array();
		public $controls = array();
		public function get_name() { return 'heading'; }
		public function get_settings( $key ) { return $this->settings[ $key ] ?? null; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Fake_Shape_Other extends Fake_Shape_Heading { public function get_name() { return 'image'; } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-shape-builder.php';
	$extension = \Digitalisimo\Elements\Shape_Builder_Extension::class;
	$extension::init();
	if ( ! isset( $GLOBALS['hooks']['elementor/element/heading/section_title_style/after_section_start'] ) || count( $GLOBALS['hooks'] ) !== 1 ) { throw new \RuntimeException( 'Los controles deben limitarse a Heading.' ); }
	$heading = new Fake_Shape_Heading();
	$extension::controls( $heading, array() );
	if ( ( $heading->controls['digitalisimo_shape_enable']['default'] ?? null ) !== '' ) { throw new \RuntimeException( 'Debe estar desactivado por defecto.' ); }
	if ( $extension::render_content( '<h2>Título</h2>', $heading ) !== '<h2>Título</h2>' ) { throw new \RuntimeException( 'No debe modificar el contenido inactivo.' ); }
	$heading->settings = array( 'digitalisimo_shape_enable' => 'yes', 'digitalisimo_shape_type' => 'triangle', 'digitalisimo_shape_color' => '#112233', 'digitalisimo_shape_size' => 900, 'digitalisimo_shape_gap' => -5 );
	$output = $extension::render_content( '<h2>Título</h2>', $heading );
	if ( ! str_contains( $output, '<h2>Título</h2><span aria-hidden="true"' ) || ! str_contains( $output, 'width:300px' ) || ! str_contains( $output, 'margin-inline-start:0px' ) || ! str_contains( $output, 'background:#112233' ) ) { throw new \RuntimeException( 'Debe generar una figura accesible con valores limitados.' ); }
	if ( $extension::render_content( '<img src="x">', new Fake_Shape_Other() ) !== '<img src="x">' ) { throw new \RuntimeException( 'No debe añadir marcado a otros widgets.' ); }
	echo "DIGITALÍSIMO Elements: figura decorativa segura validada.\n";
}
