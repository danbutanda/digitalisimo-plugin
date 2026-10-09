<?php
namespace Elementor { class Controls_Manager { const SWITCHER = 'switcher'; const COLOR = 'color'; const NUMBER = 'number'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	class Fake_Gradient_Heading {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-text-gradient.php';
	$extension = \Digitalisimo\Elements\Text_Gradient_Extension::class;
	$extension::init();
	if ( ! isset( $GLOBALS['hooks']['elementor/element/heading/section_title_style/after_section_start'] ) || count( $GLOBALS['hooks'] ) !== 1 ) { throw new \RuntimeException( 'El degradado sólo debe añadirse a Heading.' ); }
	$heading = new Fake_Gradient_Heading();
	$extension::controls( $heading, array() );
	if ( ( $heading->controls['digitalisimo_text_gradient_enable']['default'] ?? null ) !== '' ) { throw new \RuntimeException( 'Debe estar apagado por defecto.' ); }
	$css = implode( '', $heading->controls['digitalisimo_text_gradient_enable']['selectors'] ?? array() );
	if ( ! str_contains( $css, 'background-clip:text' ) || ! str_contains( $css, 'linear-gradient(' ) ) { throw new \RuntimeException( 'Debe usar CSS nativo sin script.' ); }
	if ( ( $heading->controls['digitalisimo_text_gradient_angle']['max'] ?? null ) !== 360 ) { throw new \RuntimeException( 'El ángulo debe estar limitado.' ); }
	echo "DIGITALÍSIMO Elements: degradado de texto opcional validado.\n";
}
