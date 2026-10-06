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
	class Controls_Manager { const URL = 'url'; const SELECT = 'select'; const TEXT = 'text'; const SLIDER = 'slider'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_url_raw( $url, $protocols ) { return $url; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_document( $ok, $message ) { if ( ! $ok ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-document-viewer.php';
	$widget = new \Digitalisimo\Elements\Document_Viewer_Widget();
	check_document( array( 'digitalisimo-document-viewer' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El visor sólo debe cargar su CSS.' );
	$controls = new \ReflectionMethod( $widget, 'register_controls' );
	$controls->invoke( $widget );
	check_document( isset( $widget->controls['file_source'], $widget->controls['viewer_type'], $widget->controls['document_height'] ), 'Deben existir URL, visor y altura responsiva.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'file_source' => array( 'url' => 'javascript:alert(1)' ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( '' === $html, 'Se debe rechazar un esquema distinto de HTTP(S).' );
	$widget->settings = array( 'file_source' => array( 'url' => 'https://alice:secret@example.test/file.pdf' ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( '' === $html, 'Se deben rechazar credenciales incrustadas en la URL.' );
	$widget->settings = array( 'file_source' => array( 'url' => 'https://example.com/manual.pdf' ), 'viewer_type' => 'browser', 'frame_title' => 'Manual "principal"' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( str_contains( $html, 'src="https://example.com/manual.pdf"' ) && str_contains( $html, 'loading="lazy"' ) && str_contains( $html, 'title="Manual &quot;principal&quot;"' ), 'El visor nativo debe ser accesible, seguro y diferido.' );
	check_document( str_contains( $html, 'rel="noopener noreferrer"' ), 'Debe existir un enlace alternativo seguro.' );
	$widget->settings = array( 'file_source' => array( 'url' => 'https://docs.google.com/document/d/AbC_123/edit' ), 'viewer_type' => 'google_docs' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( str_contains( $html, 'src="https://docs.google.com/document/d/AbC_123/preview"' ), 'Los documentos de Google deben abrirse en modo preview.' );
	$widget->settings = array( 'file_source' => array( 'url' => 'https://127.0.0.1/private.pdf' ), 'viewer_type' => 'google_docs' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( str_contains( $html, 'src="https://127.0.0.1/private.pdf"' ) && ! str_contains( $html, 'docs.google.com' ), 'Un documento privado no se debe enviar al visor de Google.' );
	$widget->settings = array( 'file_source' => array( 'url' => 'https://example.com/public.pdf' ), 'viewer_type' => 'google_docs' );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_document( str_contains( $html, 'docs.google.com/gview?embedded=1&amp;url=' ), 'El visor remoto debe codificar la URL fuente.' );
	echo "DIGITALÍSIMO Elements: Visor de documentos validado.\n";
}
