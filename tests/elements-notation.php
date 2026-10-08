<?php
namespace Elementor { class Controls_Manager { const SELECT = 'select'; const COLOR = 'color'; const SLIDER = 'slider'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	class Fake_Notation_Heading {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-notation.php';
	$extension = \Digitalisimo\Elements\Notation_Extension::class;
	$extension::init();
	$hook = 'elementor/element/heading/section_title_style/after_section_start';
	if ( ! isset( $GLOBALS['hooks'][ $hook ] ) || count( $GLOBALS['hooks'] ) !== 1 ) { throw new \RuntimeException( 'La notación sólo debe añadirse a Heading.' ); }
	$heading = new Fake_Notation_Heading();
	$extension::controls( $heading, array() );
	if ( ( $heading->controls['digitalisimo_notation_line']['default'] ?? null ) !== '' ) { throw new \RuntimeException( 'Debe estar apagada por defecto.' ); }
	if ( count( $heading->controls['digitalisimo_notation_line']['options'] ?? array() ) !== 4 ) { throw new \RuntimeException( 'Faltan variantes de notación.' ); }
	if ( ! str_contains( implode( '', $heading->controls['digitalisimo_notation_line']['selectors'] ?? array() ), 'text-decoration-line:{{VALUE}}' ) ) { throw new \RuntimeException( 'Falta CSS nativo.' ); }
	echo "DIGITALÍSIMO Elements: notación tipográfica condicional validada.\n";
}
