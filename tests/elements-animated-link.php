<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = is_array( $value ) ? implode( ' ', $value ) : $value; }
		public function add_link_attributes( $name, $link ) { $this->attributes[ $name ]['href'] = $link['url']; }
		public function get_render_attribute_string( $name ) {
			$output = array();
			foreach ( $this->attributes[ $name ] ?? array() as $key => $value ) {
				$output[] = $key . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $output );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.27' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['animated_hooks'][ $name ][ $priority ][] = $callback; }
	function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['animated_filters'][ $name ][ $priority ][] = $callback; }
	function plugins_url( $path, $file ) { return 'https://example.test/plugins/' . basename( dirname( $file ) ) . '/' . $path; }
	function wp_style_is( $handle, $state ) { return isset( $GLOBALS['animated_styles'][ $handle ] ); }
	function wp_register_style( $handle, $url, $deps, $version ) { $GLOBALS['animated_styles'][ $handle ] = compact( 'url', 'deps', 'version' ); }
	function wp_script_is( $handle, $state ) { return isset( $GLOBALS['animated_scripts'][ $handle ] ); }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['animated_scripts'][ $handle ] = compact( 'url', 'deps', 'version', 'footer' ); }
	function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	class Animated_Manager {
		public $widgets = array();
		public $categories = array();
		public function get_categories() { return $this->categories; }
		public function add_category( $id, $value ) { $this->categories[ $id ] = $value; }
		public function get_widget_types( $id ) { return $this->widgets[ $id ] ?? false; }
		public function register( $widget ) { $this->widgets[ $widget->get_name() ] = $widget; }
	}
	function check_animated( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-registry.php';
	$manager = new Animated_Manager();
	\Digitalisimo\Elements\Widget_Registry::init();
	\Digitalisimo\Elements\Widget_Registry::category( $manager );
	\Digitalisimo\Elements\Widget_Registry::widgets( $manager );
	\Digitalisimo\Elements\Widget_Registry::widgets( $manager );
	check_animated( 51 === count( $manager->widgets ) && isset( $manager->widgets['digitalisimo-logos'], $manager->widgets['digitalisimo-brand-carousel'], $manager->widgets['digitalisimo-creative-button'] ) && false === $manager->widgets['digitalisimo-brand-carousel']->show_in_panel() && ! method_exists( $manager->widgets['digitalisimo-logos'], 'show_in_panel' ), 'Cada widget se registra una sola vez; los alias quedan registrados y fuera del panel.' );
	check_animated( 63 === count( $GLOBALS['animated_styles'] ) && isset( $GLOBALS['animated_styles']['digitalisimo-animated-link-metis'], $GLOBALS['animated_styles']['digitalisimo-icon-nav-bar'], $GLOBALS['animated_styles']['digitalisimo-advanced-button-effects'] ) && ! isset( $GLOBALS['animated_styles']['digitalisimo-brand-carousel'], $GLOBALS['animated_styles']['digitalisimo-logo-grid'] ) && isset( $GLOBALS['animated_styles']['digitalisimo-logos'], $GLOBALS['animated_styles']['digitalisimo-chart'], $GLOBALS['animated_styles']['digitalisimo-map'], $GLOBALS['animated_styles']['digitalisimo-marquee'], $GLOBALS['animated_styles']['digitalisimo-timeline'], $GLOBALS['animated_styles']['digitalisimo-audio-player'], $GLOBALS['animated_styles']['digitalisimo-progress-bars'], $GLOBALS['animated_styles']['digitalisimo-offcanvas'], $GLOBALS['animated_styles']['digitalisimo-vertical-menu'], $GLOBALS['animated_styles']['digitalisimo-video-player'], $GLOBALS['animated_styles']['digitalisimo-user-register'], $GLOBALS['animated_styles']['digitalisimo-total-count'], $GLOBALS['animated_styles']['digitalisimo-tags-cloud'], $GLOBALS['animated_styles']['digitalisimo-table'], $GLOBALS['animated_styles']['digitalisimo-qr-code'], $GLOBALS['animated_styles']['digitalisimo-product-grid'], $GLOBALS['animated_styles']['digitalisimo-advanced-divider'], $GLOBALS['animated_styles']['digitalisimo-advanced-icon-box'], $GLOBALS['animated_styles']['digitalisimo-animated-heading'], $GLOBALS['animated_styles']['digitalisimo-breadcrumbs'], $GLOBALS['animated_styles']['digitalisimo-dual-button'], $GLOBALS['animated_styles']['digitalisimo-call-out'], $GLOBALS['animated_styles']['digitalisimo-comparison-list'], $GLOBALS['animated_styles']['digitalisimo-custom-gallery'], $GLOBALS['animated_styles']['digitalisimo-device-slider'], $GLOBALS['animated_styles']['digitalisimo-fancy-card'], $GLOBALS['animated_styles']['digitalisimo-fancy-icons'], $GLOBALS['animated_styles']['digitalisimo-fancy-slider'], $GLOBALS['animated_styles']['digitalisimo-fancy-tabs'], $GLOBALS['animated_styles']['digitalisimo-featured-box'], $GLOBALS['animated_styles']['digitalisimo-google-reviews'], $GLOBALS['animated_styles']['digitalisimo-icon-nav'], $GLOBALS['animated_styles']['digitalisimo-notification'] ), 'Deben registrarse los estilos propios (sin los alias, que heredan) y el motor compartido sin encolarlos.' );
	check_animated( 16 === count( $GLOBALS['animated_scripts'] ) && isset( $GLOBALS['animated_scripts']['digitalisimo-marquee'], $GLOBALS['animated_scripts']['digitalisimo-table'], $GLOBALS['animated_scripts']['digitalisimo-video-player'], $GLOBALS['animated_scripts']['digitalisimo-image-compare'], $GLOBALS['animated_scripts']['digitalisimo-business-hours'], $GLOBALS['animated_scripts']['digitalisimo-reading-progress'], $GLOBALS['animated_scripts']['digitalisimo-offcanvas'], $GLOBALS['animated_scripts']['digitalisimo-vertical-menu'], $GLOBALS['animated_scripts']['digitalisimo-qr-vendor'], $GLOBALS['animated_scripts']['digitalisimo-qr-code'], $GLOBALS['animated_scripts']['digitalisimo-carousel-engine'], $GLOBALS['animated_scripts']['digitalisimo-scroll-button'], $GLOBALS['animated_scripts']['digitalisimo-animated-heading'], $GLOBALS['animated_scripts']['digitalisimo-content-switcher'], $GLOBALS['animated_scripts']['digitalisimo-google-reviews'], $GLOBALS['animated_scripts']['digitalisimo-notification'] ), 'Sólo se registran los scripts condicionales propios.' );
	$style = $GLOBALS['animated_styles']['digitalisimo-animated-link'];
	check_animated( 'https://example.test/plugins/digitalisimo-elements/assets/css/animated-link.css' === $style['url'], 'El CSS debe pertenecer a Elements.' );
	check_animated( DIGITALISIMO_ELEMENTS_VERSION === $style['version'], 'El CSS debe invalidarse con la versión del plugin.' );
	$widget = $manager->widgets['digitalisimo-animated-link'];
	check_animated( 16 === count( $widget->get_style_depends() ) && 'digitalisimo-animated-link' === $widget->get_style_depends()[0] && array() === $widget->get_script_depends(), 'La instancia de registro declara la base y los quince estilos; sin JS ni UIkit.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'link_text' => '<script>alert(1)</script>', 'link_style' => 'carme', 'link_url' => array( 'url' => 'https://example.test/' ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_animated( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ), 'El texto debe escaparse.' );
	check_animated( str_contains( $html, 'href="https://example.test/"' ) && str_contains( $html, 'aria-hidden="true"' ), 'El enlace y el SVG decorativo deben ser válidos.' );
	$widget = new \Digitalisimo\Elements\Animated_Link_Widget();
	$widget->settings = array( 'link_text' => 'Sin URL', 'link_style' => 'invalid', 'link_url' => array( 'url' => '' ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_animated( str_starts_with( $html, '<span ' ) && str_contains( $html, 'digi-animated-link--metis' ) && ! str_contains( $html, 'href=' ), 'Sin URL debe salir texto no interactivo y un estilo seguro.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/animated-link.css' );
	foreach ( glob( __DIR__ . '/../digitalisimo-elements/assets/css/animated-link/*.css' ) as $variant_file ) { $css .= file_get_contents( $variant_file ); }
	foreach ( array( 'carpo', 'carme', 'dia', 'eirene', 'elara', 'ersa', 'helike', 'herse', 'io', 'iocaste', 'kale', 'leda', 'metis', 'mneme', 'thebe' ) as $variant ) {
		check_animated( str_contains( $css, 'digi-animated-link--' . $variant ), 'Falta la variante ' . $variant );
	}
	check_animated( str_contains( $css, 'prefers-reduced-motion' ) && str_contains( $css, ':focus-visible' ) && ! str_contains( $css, 'bdt-uikit' ), 'El CSS debe tener foco y reducir movimiento sin UIkit.' );
	echo "DIGITALÍSIMO Elements: Enlace animado registrado y renderizado de forma aislada.\n";
}
