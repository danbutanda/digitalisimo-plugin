<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const SELECT = 'select'; const NUMBER = 'number'; const SWITCHER = 'switcher'; const TEXT = 'text'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function get_taxonomies( $args, $output ) { return array( 'post_tag' => (object) array( 'name' => 'post_tag', 'label' => 'Etiquetas', 'labels' => (object) array( 'singular_name' => 'Etiqueta' ) ) ); }
	function get_taxonomy( $name ) { return 'post_tag' === $name ? (object) array( 'public' => true ) : ( 'private' === $name ? (object) array( 'public' => false ) : false ); }
	function get_terms( $args ) { $GLOBALS['tag_query'] = $args; return array( (object) array( 'name' => 'Marketing & IA', 'count' => 5 ), (object) array( 'name' => '<script>mal</script>SEO', 'count' => 2 ), (object) array( 'name' => 'Sin enlace', 'count' => 1 ) ); }
	function get_term_link( $term ) { return 'Sin enlace' === $term->name ? new \RuntimeException() : 'https://sitio.test/tag/' . rawurlencode( $term->name ); }
	function is_wp_error( $value ) { return $value instanceof \RuntimeException; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_tags( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-tags-cloud.php';
	$widget = new \Digitalisimo\Elements\Tags_Cloud_Widget();
	check_tags( array( 'digitalisimo-tags-cloud' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Sólo debe cargar CSS condicional.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_tags( isset( $widget->controls['taxonomy']['options']['post_tag'], $widget->controls['limit'], $widget->controls['orderby'] ), 'Debe listar taxonomías públicas y controles de consulta.' );
	$widget->settings = array( 'taxonomy' => 'post_tag', 'limit' => 999, 'orderby' => 'invalid', 'show_count' => 'yes', 'label' => 'Explorar <script>mal</script>' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_tags( 50 === $GLOBALS['tag_query']['number'] && 'count' === $GLOBALS['tag_query']['orderby'] && true === $GLOBALS['tag_query']['hide_empty'], 'La consulta debe limitarse a términos públicos con uso.' );
	check_tags( 2 === substr_count( $html, 'class="digi-tags-cloud__item"' ) && str_contains( $html, 'Marketing &amp; IA' ) && str_contains( $html, '(5)' ), 'Debe omitir enlaces inválidos y conservar nombres y conteos.' );
	check_tags( ! str_contains( $html, '<script>' ) && str_contains( $html, 'aria-label="Explorar mal"' ), 'Debe sanear nombres y etiqueta.' );
	$widget->settings['taxonomy'] = 'private';
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget );
	check_tags( '' === ob_get_clean(), 'No debe consultar taxonomías privadas.' );
	echo "DIGITALÍSIMO Elements: Nube de etiquetas validada.\n";
}
