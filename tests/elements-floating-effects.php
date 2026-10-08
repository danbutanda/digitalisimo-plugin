<?php
namespace Elementor { class Controls_Manager { const SWITCHER = 'switcher'; const NUMBER = 'number'; } }
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	function wp_script_is( $handle, $state ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['scripts'][ $handle ] = compact( 'url', 'deps', 'version', 'footer' ); }
	function wp_enqueue_script( $handle ) { $GLOBALS['enqueued'][] = $handle; }
	function plugins_url( $path, $file ) { return 'https://example.test/plugins/digitalisimo-elements/' . $path; }
	class Fake_Floating_Element {
		public $settings = array();
		public $attributes = array();
		public $controls = array();
		public function get_settings( $key ) { return $this->settings[ $key ] ?? null; }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = $value; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-floating-effects.php';
	$extension = \Digitalisimo\Elements\Floating_Effects_Extension::class;
	$extension::init();
	foreach ( array( 'common', 'container', 'section', 'column' ) as $type ) {
		if ( ! isset( $GLOBALS['hooks'][ "elementor/element/{$type}/section_effects/after_section_start" ] ) ) { throw new \RuntimeException( 'Falta el control en ' . $type ); }
	}
	$element = new Fake_Floating_Element();
	$extension::controls( $element, array() );
	if ( ( $element->controls['digitalisimo_float_enable']['default'] ?? null ) !== '' ) { throw new \RuntimeException( 'Debe estar apagado por defecto.' ); }
	$extension::before_render( $element );
	if ( ! empty( $GLOBALS['enqueued'] ) ) { throw new \RuntimeException( 'Un elemento sin movimiento no debe cargar JS.' ); }
	$element->settings = array( 'digitalisimo_float_enable' => 'yes', 'digitalisimo_float_distance' => 999, 'digitalisimo_float_duration' => 0 );
	$extension::before_render( $element );
	if ( ( $element->attributes['_wrapper']['data-digi-float-distance'] ?? '' ) !== '40' || ( $element->attributes['_wrapper']['data-digi-float-duration'] ?? '' ) !== '5' ) { throw new \RuntimeException( 'Debe limitar distancia y aplicar duración segura.' ); }
	if ( ( $GLOBALS['enqueued'][0] ?? '' ) !== 'digitalisimo-floating-effects' || ! str_contains( $GLOBALS['scripts']['digitalisimo-floating-effects']['url'] ?? '', '/modules/digitalisimo-widgets/assets/js/floating-effects.js' ) || ! is_file( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/assets/js/floating-effects.js' ) ) { throw new \RuntimeException( 'El script sólo debe cargarse al activar el efecto.' ); }
	echo "DIGITALÍSIMO Elements: movimiento condicional validado.\n";
}
