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
	class Controls_Manager { const SELECT = 'select'; const TEXT = 'text'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'MINUTE_IN_SECONDS', 60 );
	function get_post_types( $args, $output ) { return array( 'post' => (object) array( 'name' => 'post', 'label' => 'Entradas', 'labels' => (object) array( 'name' => 'Entradas' ) ) ); }
	function get_post_type_object( $type ) { return 'post' === $type ? (object) array( 'public' => true ) : false; }
	function wp_count_posts( $type, $perm ) { $GLOBALS['count_posts_args'] = array( $type, $perm ); return (object) array( 'publish' => 17, 'draft' => 99 ); }
	function wp_count_comments() { return (object) array( 'approved' => 8, 'spam' => 200, 'moderated' => 100 ); }
	function get_current_blog_id() { return $GLOBALS['blog_id']; }
	function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
	function set_transient( $key, $value, $expiry ) { $GLOBALS['transients'][ $key ] = $value; }
	function count_users( $strategy, $blog_id ) { $GLOBALS['user_queries'][] = array( $strategy, $blog_id ); return array( 'total_users' => 10 * $blog_id ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function number_format_i18n( $number ) { return (string) $number; }
	function check_total( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-total-count.php';
	$widget = new \Digitalisimo\Elements\Total_Count_Widget();
	check_total( array( 'digitalisimo-total-count' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Debe cargar sólo CSS condicional.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_total( isset( $widget->controls['source'], $widget->controls['post_type'], $widget->controls['label'] ), 'Faltan controles.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'source' => 'post', 'post_type' => 'post', 'label' => 'Entradas <b>reales</b>' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_total( str_contains( $html, '>17</span>' ) && str_contains( $html, 'Entradas reales' ) && ! str_contains( $html, '<b>' ) && array( 'post', 'readable' ) === $GLOBALS['count_posts_args'], 'Sólo contar publicaciones y sanear la etiqueta.' );
	$widget->settings['post_type'] = 'private';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_total( str_contains( $html, '>0</span>' ), 'Un tipo no público no debe consultarse.' );
	$widget->settings['source'] = 'comment';
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_total( str_contains( $html, '>8</span>' ), 'Sólo contar comentarios aprobados.' );
	$widget->settings['source'] = 'user';
	$GLOBALS['blog_id'] = 1;
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_total( str_contains( $html, '>10</span>' ), 'Contar usuarios del primer sitio.' );
	$GLOBALS['blog_id'] = 2;
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_total( str_contains( $html, '>20</span>' ) && count( $GLOBALS['user_queries'] ) === 2 && array( 'time', 2 ) === $GLOBALS['user_queries'][1], 'Aislar conteos y caché por sitio.' );
	echo "DIGITALÍSIMO Elements: Total Count validado.\n";
}
