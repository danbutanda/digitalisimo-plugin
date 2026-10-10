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
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const COLOR = 'color'; const MEDIA = 'media'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	$GLOBALS['table_cache'] = array();
	$GLOBALS['table_reads'] = 0;
	function get_post_type( $id ) { return in_array( $id, array( 5, 6 ), true ) ? 'attachment' : 'post'; }
	function get_attached_file( $id ) { return $GLOBALS['table_files'][ $id ] ?? ''; }
	function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['table_uploads'] ); }
	function get_current_blog_id() { return $GLOBALS['table_blog_id'] ?? 1; }
	function get_transient( $key ) { return $GLOBALS['table_cache'][ $key ] ?? false; }
	function set_transient( $key, $value, $ttl ) { $GLOBALS['table_cache'][ $key ] = $value; $GLOBALS['table_reads']++; }
	function wp_strip_all_tags( $v ) { return strip_tags( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function check_table( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-table.php';
	$widget = new \Digitalisimo\Elements\Table_Widget();
	check_table( array( 'digitalisimo-table' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'La tabla debe cargar sólo su CSS.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_table( isset( $widget->controls['source'], $widget->controls['content'], $widget->controls['csv_file'], $widget->controls['delimiter'], $widget->controls['first_column_header'] ), 'Deben existir CSV manual y archivo de Medios con separador y encabezados.' );
	$widget->settings = array( 'caption' => 'Precios <script>mal</script>', 'content' => "Producto,Descripción,Precio\nServicio,\"Incluye diseño, soporte\",$100\nOtro,<script>alert(1)</script>,$200", 'first_column_header' => 'yes' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_table( str_contains( $html, '<caption>Precios mal</caption>' ) && 3 === substr_count( $html, '<th scope="col">' ), 'Debe construir título y encabezados reales.' );
	check_table( str_contains( $html, 'Incluye diseño, soporte' ) && 2 === substr_count( $html, '<th scope="row">' ), 'Debe leer comas entrecomilladas y encabezados de fila.' );
	check_table( ! str_contains( $html, '<script>' ) && str_contains( $html, 'digi-table-wrap' ) && str_contains( $html, 'tabindex="0"' ), 'Debe sanear datos y permitir desplazar con teclado.' );
	$widget->settings = array( 'content' => "Uno,Dos" );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget );
	check_table( '' === ob_get_clean(), 'Una tabla sin filas de datos no debe pintarse.' );
	$dir = sys_get_temp_dir() . '/digi-table-' . getmypid();
	mkdir( $dir );
	$GLOBALS['table_uploads'] = $dir;
	$GLOBALS['table_files'] = array( 5 => $dir . '/precios.csv', 6 => sys_get_temp_dir() . '/fuera-' . getmypid() . '.csv' );
	file_put_contents( $GLOBALS['table_files'][5], "Nombre,Precio\nServicio,100\n" );
	file_put_contents( $GLOBALS['table_files'][6], "Secreto,Valor\nNo,leer\n" );
	try {
		$widget->settings = array( 'source' => 'media', 'csv_file' => array( 'id' => 5 ), 'caption' => 'Precios' );
		ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $media = ob_get_clean();
		check_table( str_contains( $media, '<td>100</td>' ) && 1 === $GLOBALS['table_reads'], 'Debe leer el CSV local del sitio y almacenar sus filas.' );
		ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); ob_end_clean();
		check_table( 1 === $GLOBALS['table_reads'], 'La segunda solicitud debe usar la caché del archivo.' );
		$widget->settings['csv_file'] = array( 'id' => 6 );
		ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $outside = ob_get_clean();
		check_table( '' === $outside, 'Un adjunto que apunta fuera de los uploads del sitio debe rechazarse.' );
	} finally {
		unlink( $GLOBALS['table_files'][5] ); unlink( $GLOBALS['table_files'][6] ); rmdir( $dir );
	}
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_table( str_contains( $editor, 'var rows = []' ) && str_contains( $editor, 'digi-table-wrap' ) && str_contains( $editor, "settings.source === 'media'" ), 'El editor debe distinguir CSV manual y de Medios.' );
	echo "DIGITALÍSIMO Elements: Tabla CSV validada.\n";
}
