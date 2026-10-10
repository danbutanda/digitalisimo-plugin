<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_id() { return 'oc1'; }
		public function get_settings_for_display() { return $this->settings; }
	}
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
	class Plugin {
		public $frontend;
		public static function instance() { $p = new self(); $p->frontend = new class() { public function get_builder_content_for_display( $id, $css ) { return '<p>Plantilla ' . $id . '</p>'; } }; return $p; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_oc( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><a><strong><em>' ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_type( $id ) { return 7 === $id ? 'elementor_library' : 'page'; }
	function get_post_status( $id ) { return 'publish'; }
	function is_active_sidebar( $id ) { return 'lateral' === $id; }
	function dynamic_sidebar( $id ) { echo '<section class="widget">Widgets</section>'; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-offcanvas.php';
	$widget = new \Digitalisimo\Elements\Offcanvas_Widget();
	$render = static function () use ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	$widget->settings = array( 'trigger' => 'button', 'button_text' => 'Menú <b>x</b>', 'button_icon' => array( 'value' => 'fas fa-bars' ), 'source' => 'template', 'template_id' => '7', 'content_before' => '<p>Antes<script>x</script></p>', 'close_button' => 'yes', 'close_on_overlay' => 'yes', 'close_on_escape' => '', 'side' => 'right', 'overlay' => 'yes' );
	$html = $render();
	check_oc( str_contains( $html, 'aria-controls="digi-offcanvas-oc1" aria-expanded="false"' ) && str_contains( $html, '<span class="digi-offcanvas__text">Menú x</span>' ), 'El botón debe controlar el panel con estado accesible.' );
	check_oc( str_contains( $html, 'role="dialog" aria-modal="true" aria-label="Menú x" hidden' ) && str_contains( $html, 'digi-offcanvas__panel--right' ) && str_contains( $html, '<p>Plantilla 7</p>' ), 'El panel es un diálogo oculto con la plantilla publicada.' );
	check_oc( str_contains( $html, '<div class="digi-offcanvas__before"><p>Antes' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, 'aria-label="Cerrar"' ) && str_contains( $html, '&quot;overlay&quot;:true,&quot;escape&quot;:false' ), 'Texto saneado, botón de cerrar con nombre y configuración de cierre.' );

	$widget->settings = array( 'trigger' => 'selector', 'trigger_selector' => '#abrir', 'source' => 'template', 'template_id' => '9' );
	$custom = $render();
	check_oc( ! str_contains( $custom, 'data-digi-offcanvas-open' ) && str_contains( $custom, '&quot;selector&quot;:&quot;#abrir&quot;' ) && ! str_contains( $custom, 'Plantilla 9' ) && str_contains( $custom, 'aria-label="Panel"' ), 'Con selector no hay botón propio y sólo se muestran plantillas de la biblioteca.' );

	$widget->settings = array( 'source' => 'sidebar', 'sidebar' => 'lateral', 'button_text' => 'Abrir' );
	check_oc( str_contains( $render(), '<section class="widget">Widgets</section>' ), 'La barra lateral activa se muestra en el panel.' );
	echo "DIGITALÍSIMO Elements: panel lateral validado.\n";
}
