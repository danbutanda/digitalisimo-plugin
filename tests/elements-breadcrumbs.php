<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const SWITCHER = 'switcher'; const SELECT = 'select'; const CHOOSE = 'choose'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['digi_context'] = 'front';
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
	function is_wp_error( $v ) { return false; }
	function home_url( $path ) { return 'https://site.example.test/subsite' . $path; }
	function is_front_page() { return 'front' === $GLOBALS['digi_context']; }
	function is_home() { return 'blog' === $GLOBALS['digi_context']; }
	function is_singular() { return in_array( $GLOBALS['digi_context'], array( 'page', 'post', 'product' ), true ); }
	function is_category() { return 'category' === $GLOBALS['digi_context']; }
	function is_tag() { return false; }
	function is_tax() { return false; }
	function is_post_type_archive() { return false; }
	function is_search() { return false; }
	function is_404() { return false; }
	function is_archive() { return false; }
	function get_queried_object() { return 'category' === $GLOBALS['digi_context'] ? (object) array( 'term_id' => 3, 'taxonomy' => 'category', 'name' => 'Noticias' ) : (object) array( 'ID' => 7 ); }
	function get_post_type( $post ) { return $GLOBALS['digi_context']; }
	function get_post_ancestors( $post ) { return array( 2 ); }
	function get_ancestors( $id, $taxonomy, $type ) { return array( 1 ); }
	function get_term( $id, $taxonomy ) { return (object) array( 'term_id' => 1, 'taxonomy' => $taxonomy, 'name' => 'Categoría padre' ); }
	function get_term_link( $term ) { return 'https://site.example.test/subsite/category/' . $term->term_id . '/'; }
	function get_option( $key ) { return 0; }
	function get_the_terms( $post, $taxonomy ) { return array( (object) array( 'term_id' => 3, 'taxonomy' => 'category', 'name' => 'Noticias' ) ); }
	function get_the_title( $id ) { return 2 === $id ? 'Acerca de' : 'Página <script>x</script>'; }
	function get_permalink( $id ) { return 'https://site.example.test/subsite/acerca/'; }
	function get_post_type_object( $type ) { return (object) array( 'has_archive' => true, 'labels' => (object) array( 'name' => 'Productos' ) ); }
	function get_post_type_archive_link( $type ) { return 'https://site.example.test/subsite/productos/'; }
	function check_breadcrumbs( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-breadcrumbs.php';
	$widget = new \Digitalisimo\Elements\Breadcrumbs_Widget();
	check_breadcrumbs( array( 'digitalisimo-breadcrumbs' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'La ruta sólo requiere CSS condicional.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	foreach ( array( 'home_text', 'show_home_only', 'separator', 'align' ) as $control ) { check_breadcrumbs( isset( $widget->controls[ $control ] ), 'Falta el control ' . $control ); }
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'home_text' => 'Inicio', 'separator' => 'chevron' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_breadcrumbs( '' === $html, 'La portada no debe mostrar una ruta vacía por defecto.' );
	$GLOBALS['digi_context'] = 'page';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_breadcrumbs( str_contains( $html, 'href="https://site.example.test/subsite/"' ) && str_contains( $html, 'href="https://site.example.test/subsite/acerca/"' ) && str_contains( $html, 'aria-current="page"' ), 'La ruta debe respetar URLs y jerarquía del sitio actual.' );
	check_breadcrumbs( ! str_contains( $html, '<script' ) && ! str_contains( $html, 'application/ld+json' ), 'No debe publicar HTML inseguro ni un schema duplicado.' );
	$GLOBALS['digi_context'] = 'product';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_breadcrumbs( str_contains( $html, '/productos/' ) && str_contains( $html, 'Productos' ), 'El CPT debe usar su archivo y permalink propios.' );
	$GLOBALS['digi_context'] = 'category';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_breadcrumbs( str_contains( $html, 'Categoría padre' ) && str_contains( $html, 'Noticias' ), 'La taxonomía debe mostrar su jerarquía.' );
	echo "DIGITALÍSIMO Elements: Ruta de navegación por sitio validada.\n";
}
