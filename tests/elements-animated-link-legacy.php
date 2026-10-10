<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		private $attrs = array();
		public function get_settings_for_display() { return $this->settings; }
		public function add_render_attribute( $name, $key, $value ) { $this->attrs[ $name ][ $key ] = is_array( $value ) ? implode( ' ', $value ) : $value; }
		public function add_link_attributes( $name, $link ) { $this->attrs[ $name ]['href'] = $link['url']; }
		public function get_render_attribute_string( $name ) {
			$values = array();
			foreach ( $this->attrs[ $name ] ?? array() as $key => $value ) $values[] = $key . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
			return implode( ' ', $values );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.27' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	function add_action( $name, $callback, $priority = 10 ) {}
	function plugins_url( $path, $file ) { return 'https://example.test/' . $path; }
	function wp_style_is( $handle, $state ) { return isset( $GLOBALS['styles'][ $handle ] ); }
	function wp_register_style( $handle, $url, $deps, $version ) { $GLOBALS['styles'][ $handle ] = $url; }
	function wp_script_is( $handle, $state ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['scripts'][ $handle ] = $url; }
	function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function check_legacy( $ok, $message ) { if ( ! $ok ) throw new \RuntimeException( $message ); }
	class Legacy_Manager {
		public $widgets = array();
		public function get_widget_types( $id ) { return $this->widgets[ $id ] ?? false; }
		public function register( $widget ) { $this->widgets[ $widget->get_name() ] = $widget; }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-registry.php';
	$manager = new Legacy_Manager();
	\Digitalisimo\Elements\Widget_Registry::widgets( $manager );
	\Digitalisimo\Elements\Widget_Registry::widgets( $manager );
	$own = count( preg_grep( '/^digitalisimo-/', array_keys( $manager->widgets ) ) );
	$adapters = array_diff( array_keys( $manager->widgets ), preg_grep( '/^digitalisimo-/', array_keys( $manager->widgets ) ) );
	check_legacy( 39 === $own && in_array( 'bdt-animated-link', $adapters, true ) && in_array( 'bdt-accordion', $adapters, true ), 'Sin Element Pack deben existir los treinta y nueve widgets nuevos y sus IDs legacy, una sola vez.' );
	check_legacy( $manager->widgets['bdt-animated-link'] instanceof \Digitalisimo\Elements\Legacy_Animated_Link_Widget, 'El ID legacy debe usar nuestra implementación.' );
	$widget = $manager->widgets['bdt-animated-link'];
	$widget->settings = array( 'link_style' => 'leda', 'link_text' => 'Ejemplo', 'link_url' => array( 'url' => 'https://example.test/' ) );
	$render = new \ReflectionMethod( $widget, 'render' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_legacy( str_contains( $html, 'digi-animated-link--leda' ) && str_contains( $html, 'bdt-ep-animated-link--leda' ), 'El alias debe conservar selectores nuevos y antiguos.' );
	check_legacy( str_contains( $html, 'href="https://example.test/"' ) && str_contains( $html, 'data-text="Ejemplo"' ), 'El alias debe leer los controles legacy sin convertir la base de datos.' );
	$manager->widgets['bdt-animated-link'] = new \Elementor\Widget_Base();
	\Digitalisimo\Elements\Widget_Registry::widgets( $manager );
	check_legacy( $manager->widgets['bdt-animated-link'] instanceof \Elementor\Widget_Base && ! ( $manager->widgets['bdt-animated-link'] instanceof \Digitalisimo\Elements\Legacy_Animated_Link_Widget ), 'Nunca reemplazar un ID ya registrado por otro plugin.' );
	echo "DIGITALÍSIMO Elements: compatibilidad de Animated Link sin colisiones.\n";
}
