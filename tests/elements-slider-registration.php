<?php
namespace Elementor {
	class Widget_Base {
		public function get_name() { return 'legacy'; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.27' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['slider_styles'] = array();
	$GLOBALS['wp_styles'] = (object) array( 'registered' => array() );
	$GLOBALS['wp_scripts'] = (object) array( 'registered' => array() );
	function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['slider_hooks'][ $name ][ $priority ][] = $callback; }
	function plugins_url( $path, $file ) { return 'https://example.test/plugins/' . basename( dirname( $file ) ) . '/' . $path; }
	function wp_style_is( $handle, $status ) { return isset( $GLOBALS['wp_styles']->registered[ $handle ] ); }
	function wp_deregister_style( $handle ) { unset( $GLOBALS['wp_styles']->registered[ $handle ] ); }
	function wp_register_style( $handle, $url, $deps, $version ) { $GLOBALS['wp_styles']->registered[ $handle ] = (object) array( 'src' => $url, 'ver' => $version ); }
	function wp_script_is( $handle, $status ) { return isset( $GLOBALS['wp_scripts']->registered[ $handle ] ); }
	function wp_deregister_script( $handle ) { unset( $GLOBALS['wp_scripts']->registered[ $handle ] ); }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['wp_scripts']->registered[ $handle ] = (object) array( 'src' => $url, 'deps' => $deps, 'ver' => $version, 'footer' => $footer ); }
	class Slider_Manager {
		public $widgets = array();
		public $categories = array();
		public function get_categories() { return $this->categories; }
		public function add_category( $name, $args ) { $this->categories[ $name ] = $args; }
		public function get_widget_types( $name ) { return isset( $this->widgets[ $name ] ) ? $this->widgets[ $name ] : false; }
		public function unregister( $name ) { unset( $this->widgets[ $name ] ); }
		public function register( $widget ) { $this->widgets[ $widget->get_name() ] = $widget; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-slider/class-module.php';
	$manager = new Slider_Manager();
	$manager->categories['digitalisimo'] = array( 'title' => 'DIGITALÍSIMO' );
	$manager->widgets['digitalisimo-slider-optimizado'] = new \Elementor\Widget_Base();
	wp_register_style( 'digitalisimo-slider-optimizado', 'https://example.test/plugins/digitalisimo-tools/assets/slider-optimizado.css', array(), '1.0.24' );
	wp_register_script( 'digitalisimo-slider-optimizado', 'https://example.test/plugins/digitalisimo-tools/assets/slider.js', array(), '1.0.24', true );
	\Digitalisimo\Elements\Elementor_Slider::init();
	\Digitalisimo\Elements\Elementor_Slider::category( $manager );
	\Digitalisimo\Elements\Elementor_Slider::widget( $manager );
	$widget = $manager->get_widget_types( 'digitalisimo-slider-optimizado' );
	if ( ! $widget instanceof \Digitalisimo\Elements\Elementor_Slider_Widget || 1 !== count( $manager->widgets ) ) throw new \RuntimeException( 'Elements debe reemplazar el widget antiguo sin duplicarlo.' );
	if ( array( 'digitalisimo-slider-optimizado' ) !== $widget->get_script_depends() ) throw new \RuntimeException( 'La preparación de imágenes debe cargarse sólo al usar el slider.' );
	if ( 'DIGITALÍSIMO' !== $manager->categories['digitalisimo']['title'] || 1 !== count( $manager->categories ) ) throw new \RuntimeException( 'La categoría existente debe conservarse sin duplicar.' );
	$style = $GLOBALS['wp_styles']->registered['digitalisimo-slider-optimizado'];
	if ( false === strpos( $style->src, '/digitalisimo-elements/assets/css/slider-optimizado.css' ) || DIGITALISIMO_ELEMENTS_VERSION !== $style->ver ) throw new \RuntimeException( 'El CSS debe servirse desde Elements incluso con Tools anterior instalado.' );
	\Digitalisimo\Elements\Elementor_Slider::style();
	if ( 1 !== count( $GLOBALS['wp_styles']->registered ) ) throw new \RuntimeException( 'El estilo no debe duplicarse.' );
	$script = $GLOBALS['wp_scripts']->registered['digitalisimo-slider-optimizado'] ?? null;
	if ( ! $script || false === strpos( $script->src, '/digitalisimo-elements/assets/js/slider-optimizado.js' ) || DIGITALISIMO_ELEMENTS_VERSION !== $script->ver || array( 'elementor-frontend' ) !== $script->deps ) throw new \RuntimeException( 'La preparación de imágenes debe depender de Elementor y servirse desde Elements.' );
	\Digitalisimo\Elements\Elementor_Slider::script();
	if ( 1 !== count( $GLOBALS['wp_scripts']->registered ) ) throw new \RuntimeException( 'El script no debe duplicarse.' );
	echo "DIGITALÍSIMO Elements: Slider Optimizado migrado sin duplicados.\n";
}
