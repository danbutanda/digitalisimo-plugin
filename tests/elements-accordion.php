<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_id() { return 'test123'; }
		public function parse_text_editor( $text ) { return $text; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const WYSIWYG = 'wysiwyg'; const REPEATER = 'repeater'; const NUMBER = 'number'; const SWITCHER = 'switcher'; const SELECT = 'select'; const SELECT2 = 'select2'; const ICONS = 'icons'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<svg data-icon="' . htmlspecialchars( $icon['value'], ENT_QUOTES, 'UTF-8' ) . '"></svg>'; } }
	class Plugin { public $frontend; public static function instance() { static $instance; if ( ! $instance ) { $instance = new self(); $instance->frontend = new Frontend(); } return $instance; } }
	class Frontend { public function get_builder_content_for_display( $id, $css ) { return '<p>Plantilla ' . $id . '</p>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function wp_kses_post( $value ) { return strip_tags( (string) $value, '<p><strong>' ); }
	function absint( $value ) { return abs( (int) $value ); }
	function is_admin() { return true; }
	$GLOBALS['accordion_blog'] = 7;
	function get_current_blog_id() { return $GLOBALS['accordion_blog']; }
	function post_type_exists( $type ) { return in_array( $type, array( 'elementor_library', 'ae_global_templates' ), true ); }
	function get_posts( $args ) { $GLOBALS['accordion_template_queries'] = ( $GLOBALS['accordion_template_queries'] ?? 0 ) + 1; if ( 'publish' !== $args['post_status'] || 200 !== $args['posts_per_page'] ) { throw new \RuntimeException( 'La consulta de plantillas debe limitarse a publicadas.' ); } return 'elementor_library' === $args['post_type'] ? array( (object) array( 'ID' => 42 + 10 * ( get_current_blog_id() - 7 ) ) ) : array( (object) array( 'ID' => 44 + 10 * ( get_current_blog_id() - 7 ) ) ); }
	function get_the_title( $id ) { return 'Plantilla ' . $id; }
	function get_post_type( $id ) { return in_array( $id, array( 42, 43 ), true ) ? 'elementor_library' : ( 44 === $id ? 'ae_global_templates' : 'post' ); }
	function get_post_status( $id ) { return 43 === $id ? 'private' : 'publish'; }
	function check_accordion( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-accordion.php';
	$widget = new \Digitalisimo\Elements\Accordion_Widget();
	check_accordion( 'digitalisimo-accordion' === $widget->get_name() && array( 'digitalisimo-accordion' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El acordeón sólo debe requerir su CSS.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_accordion( isset( $widget->controls['tabs']['fields']['tab_title'], $widget->controls['tabs']['fields']['tab_content'], $widget->controls['active_item'], $widget->controls['multiple'], $widget->controls['open_all_initially'] ), 'Faltan controles principales.' );
	check_accordion( isset( $widget->controls['tabs']['fields']['source'], $widget->controls['tabs']['fields']['template_id'], $widget->controls['tabs']['fields']['anywhere_id'], $widget->controls['tabs']['fields']['repeater_icon'], $widget->controls['accordion_icon'] ), 'Faltan controles de plantillas e iconos.' );
	check_accordion( array( 42 => 'Plantilla 42' ) === $widget->controls['tabs']['fields']['template_id']['options'] && array( 44 => 'Plantilla 44' ) === $widget->controls['tabs']['fields']['anywhere_id']['options'], 'Los selectores deben listar sólo las plantillas publicadas del sitio actual.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_accordion( 2 === $GLOBALS['accordion_template_queries'], 'Las consultas de plantillas deben compartirse entre instancias en cada sitio.' );
	$GLOBALS['accordion_blog'] = 8;
	$other_site = new \Digitalisimo\Elements\Accordion_Widget();
	( new \ReflectionMethod( $other_site, 'register_controls' ) )->invoke( $other_site );
	check_accordion( array( 52 => 'Plantilla 52' ) === $other_site->controls['tabs']['fields']['template_id']['options'] && 4 === $GLOBALS['accordion_template_queries'], 'Las opciones de plantillas de otro sitio de la red no deben reutilizarse.' );
	$GLOBALS['accordion_blog'] = 7;
	$widget->settings = array( 'tabs' => array( array( 'tab_title' => 'Pregunta <script>', 'tab_content' => '<p>Respuesta <strong>válida</strong></p><script>alert(1)</script>' ), array( 'tab_title' => 'Segunda', 'tab_content' => '<p>Otra</p>' ), array( 'tab_title' => '', 'tab_content' => '' ) ), 'active_item' => 1, 'multiple' => '', 'title_html_tag' => 'script' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_accordion( 2 === substr_count( $html, '<details ' ) && 2 === preg_match_all( '/name="digi-accordion-test123-1"/', $html ) && 1 === substr_count( $html, ' open' ), 'Deben renderizarse dos elementos con grupo exclusivo y apertura inicial.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $second_instance = ob_get_clean();
	check_accordion( str_contains( $second_instance, 'name="digi-accordion-test123-2"' ) && ! str_contains( $second_instance, 'name="digi-accordion-test123-1"' ), 'Dos instancias del mismo widget no deben compartir grupo.' );
	check_accordion( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<strong>válida</strong>' ), 'El título y contenido deben pasar por escape y sanitización.' );
	check_accordion( str_contains( $html, '<span class="digi-accordion__label digi-accordion__title">' ), 'La etiqueta inválida debe usar span.' );
	$widget->settings['tabs'] = array_merge( array( array( 'tab_title' => '', 'tab_content' => '' ) ), $widget->settings['tabs'] );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $after_empty = ob_get_clean();
	check_accordion( 2 === substr_count( $after_empty, '<details ' ) && 1 === substr_count( $after_empty, ' open>' ), 'El elemento abierto se cuenta entre los paneles visibles, aunque haya filas vacías.' );
	array_shift( $widget->settings['tabs'] );
	$widget->settings['title_html_tag'] = 'h3';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $headings = ob_get_clean();
	check_accordion( str_contains( $headings, '<summary class="digi-accordion__summary"><h3 class="digi-accordion__label digi-accordion__title">' ) && ! str_contains( $headings, '<span class="digi-accordion__label"><h3' ), 'Los encabezados deben ser hijos directos de summary, no de un span.' );
	$widget->settings['title_html_tag'] = 'span';
	$widget->settings['multiple'] = 'yes';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $multi = ob_get_clean();
	check_accordion( ! str_contains( $multi, ' name=' ), 'El modo múltiple no debe agrupar details.' );
	$widget->settings['open_all_initially'] = 'yes';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $all_open = ob_get_clean();
	check_accordion( 2 === substr_count( $all_open, '<details ' ) && 2 === substr_count( $all_open, ' open>' ), 'La apertura múltiple puede iniciar con todos los paneles abiertos.' );
	$widget->settings['multiple'] = '';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $exclusive_open = ob_get_clean();
	check_accordion( 1 === substr_count( $exclusive_open, ' open>' ), 'Una configuración heredada de apertura total no debe abrir varios paneles en modo exclusivo.' );
	$widget->settings['multiple'] = 'yes';
	$widget->settings['tabs'] = array( array( 'tab_title' => 'Elementor', 'source' => 'elementor', 'template_id' => 42, 'repeater_icon' => array( 'value' => 'star' ) ), array( 'tab_title' => 'Privada', 'source' => 'elementor', 'template_id' => 43 ), array( 'tab_title' => 'Anywhere', 'source' => 'anywhere', 'anywhere_id' => 44 ) );
	$widget->settings['show_custom_icon'] = 'yes';
	$widget->settings['accordion_icon'] = array( 'value' => 'plus' );
	$widget->settings['accordion_active_icon'] = array( 'value' => 'minus' );
	$widget->settings['icon_align'] = 'left';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $templates = ob_get_clean();
	check_accordion( str_contains( $templates, 'Plantilla 42' ) && str_contains( $templates, 'Plantilla 44' ) && ! str_contains( $templates, 'Plantilla 43' ) && 2 === substr_count( $templates, '<details ' ), 'Sólo se pueden mostrar paneles con plantillas publicadas del tipo correcto.' );
	check_accordion( str_contains( $templates, 'digi-accordion--icon-left' ) && str_contains( $templates, 'data-icon="star"' ) && str_contains( $templates, 'data-icon="plus"' ) && str_contains( $templates, 'data-icon="minus"' ), 'Deben conservarse los iconos nativos de Elementor.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_accordion( str_contains( $editor, 'digi-accordion__summary' ) && str_contains( $editor, 'tab_content' ) && str_contains( $editor, 'openAll || active === visible' ) && str_contains( $editor, '<{{ tag }} class="digi-accordion__label digi-accordion__title">' ), 'La plantilla de Elementor debe conservar elementos, apertura y encabezados válidos.' );
	echo "DIGITALÍSIMO Elements: Acordeón nativo validado.\n";
}
