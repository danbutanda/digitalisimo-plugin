<?php
namespace Elementor { class Controls_Manager { const SWITCHER = 'switcher'; const SLIDER = 'slider'; const NUMBER = 'number'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function add_action( $hook, $callback, $priority, $accepted_args ) { $GLOBALS['hooks'][ $hook ] = array( $callback, $priority, $accepted_args ); }
	class Fake_Element {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-backdrop-filter.php';
	\Digitalisimo\Elements\Backdrop_Filter_Extension::init();
	foreach ( array( 'common/_section_background', 'container/section_background', 'section/section_background', 'column/section_style' ) as $place ) {
		if ( ! isset( $GLOBALS['hooks'][ 'elementor/element/' . $place . '/before_section_end' ] ) ) {
			throw new \RuntimeException( 'Falta el control para ' . $place );
		}
	}
	$element = new Fake_Element();
	\Digitalisimo\Elements\Backdrop_Filter_Extension::controls( $element, array() );
	$toggle = $element->controls['digitalisimo_backdrop_enable'] ?? array();
	if ( ( $toggle['default'] ?? null ) !== '' || ! str_contains( $toggle['selectors']['{{WRAPPER}}'] ?? '', '-webkit-backdrop-filter:' ) ) {
		throw new \RuntimeException( 'El efecto debe estar apagado por defecto y generar CSS compatible.' );
	}
	foreach ( array( 'blur', 'brightness', 'saturation' ) as $property ) {
		if ( ( $element->controls[ 'digitalisimo_backdrop_' . $property ]['condition']['digitalisimo_backdrop_enable'] ?? null ) !== 'yes' ) {
			throw new \RuntimeException( 'El ajuste ' . $property . ' sólo debe mostrarse con el efecto activo.' );
		}
	}
	echo "DIGITALÍSIMO Elements: filtro de fondo condicional validado.\n";
}
