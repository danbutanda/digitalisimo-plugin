<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $links = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->links[ $name ], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SWITCHER = 'switcher'; const URL = 'url'; const SELECT = 'select'; const CHOOSE = 'choose'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function check_heading( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-advanced-heading.php';
	$widget = new \Digitalisimo\Elements\Advanced_Heading_Widget();
	check_heading( array( 'digitalisimo-advanced-heading' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El componente debe cargar sólo su CSS.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	foreach ( array( 'sub_heading', 'main_heading', 'split_main_heading', 'split_text', 'link', 'header_size', 'align', 'advanced_heading_visibility', 'advanced_heading', 'decoration_size', 'decoration_offset_x', 'decoration_offset_y', 'title_typography', 'sub_typography', 'split_typography', 'decoration_typography' ) as $control ) { check_heading( isset( $widget->controls[ $control ] ), 'Falta el control ' . $control ); }
	check_heading( '{{WRAPPER}} .digi-advanced-heading__title' === $widget->controls['title_typography']['selector'] && '{{WRAPPER}} .digi-advanced-heading__decoration' === $widget->controls['decoration_typography']['selector'], 'La tipografía debe limitarse a cada instancia del widget.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/advanced-heading.css' );
	check_heading( str_contains( $css, '--digi-heading-decoration-x:0px' ) && str_contains( $css, '--digi-heading-decoration-y:0px' ) && str_contains( $css, 'top:calc(50% + var(--digi-heading-decoration-y))' ), 'Los desplazamientos deben conservar el diseño original por defecto.' );
	check_heading( str_contains( $widget->controls['decoration_offset_x']['selectors']['{{WRAPPER}} .digi-advanced-heading'], '--digi-heading-decoration-x:' ), 'El editor debe generar el desplazamiento horizontal sólo para este widget.' );
	$widget->settings = array( 'sub_heading' => 'Antes', 'main_heading' => 'Agencia <script>x</script>', 'split_main_heading' => 'yes', 'split_text' => 'México', 'header_size' => 'h1', 'advanced_heading_visibility' => 'yes', 'advanced_heading' => 'Marca', 'link' => array( 'url' => 'https://example.test/' ) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_heading( 1 === substr_count( $html, '<h1 ' ) && 1 === substr_count( $html, '</h1>' ) && str_contains( $html, 'aria-hidden="true"' ), 'El título debe ser único y la decoración inaccesible.' );
	check_heading( str_contains( $html, '</span> <span class="digi-advanced-heading__split">' ), 'El fragmento destacado debe conservar un espacio legible.' );
	check_heading( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ) && str_contains( $html, 'href="https://example.test/"' ), 'Contenido y enlace deben escaparse.' );
	$widget->settings['header_size'] = 'script';
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_heading( str_contains( $html, '<h2 ' ) && ! str_contains( $html, '<script ' ), 'Una etiqueta inválida debe normalizarse.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_heading( str_contains( $editor, 'digi-advanced-heading__title' ) && str_contains( $editor, 'aria-hidden="true"' ), 'El editor debe mostrar la misma estructura semántica.' );
	echo "DIGITALÍSIMO Elements: Encabezado avanzado validado.\n";
}
