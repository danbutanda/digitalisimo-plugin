<?php
namespace Elementor { class Controls_Manager { const TAB_STYLE = 'style'; const SWITCHER = 'switcher'; const NUMBER = 'number'; const COLOR = 'color'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	class Fake_Shadow_Widget {
		public $name;
		public $controls = array();
		public $sections = array();
		public function __construct( $name ) { $this->name = $name; }
		public function get_name() { return $this->name; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-image-shadow.php';
	$extension = \Digitalisimo\Elements\Image_Shadow_Extension::class;
	$extension::init();
	$other = new Fake_Shadow_Widget( 'heading' );
	$extension::controls( $other, array() );
	if ( $other->controls || $other->sections ) { throw new \RuntimeException( 'No debe afectar otros widgets.' ); }
	$image = new Fake_Shadow_Widget( 'image' );
	$extension::controls( $image, array() );
	if ( ( $image->controls['digitalisimo_image_shadow_enable']['default'] ?? null ) !== '' ) { throw new \RuntimeException( 'Debe estar apagado por defecto.' ); }
	$css = implode( '', $image->controls['digitalisimo_image_shadow_enable']['selectors'] ?? array() );
	if ( ! str_contains( $css, 'drop-shadow(' ) || str_contains( $css, 'box-shadow:' ) ) { throw new \RuntimeException( 'Debe usar la silueta real de la imagen.' ); }
	if ( ( $image->controls['digitalisimo_image_shadow_blur']['max'] ?? null ) !== 100 ) { throw new \RuntimeException( 'El desenfoque debe estar limitado.' ); }
	echo "DIGITALÍSIMO Elements: sombra de silueta condicional validada.\n";
}
