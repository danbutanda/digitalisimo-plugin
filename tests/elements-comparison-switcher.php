<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function parse_text_editor( $content ) { return $content; }
		public function get_id() { return 'test-01'; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
		public function add_link_attributes( $name, $link ) {
			$this->links[ $name ] = $link['url'];
			$this->attributes[ $name ] = array( 'href' => $link['url'] );
			if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; }
			if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); }
		}
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) {
			$out = array();
			foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; }
			return implode( ' ', $out );
		}
	}
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const WYSIWYG = 'wysiwyg'; const URL = 'url'; const SWITCHER = 'switcher'; const REPEATER = 'repeater'; const COLOR = 'color'; const ICONS = 'icons'; const SELECT = 'select'; const SELECT2 = 'select2'; const MEDIA = 'media'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><strong><em><a>' ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $v ); }
	function check_compare( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-comparison-list.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-content-switcher.php';
	$table = new \Digitalisimo\Elements\Comparison_List_Widget();
	$switcher = new \Digitalisimo\Elements\Content_Switcher_Widget();
	foreach ( array( $table, $switcher ) as $widget ) { (new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget ); }
	check_compare( array() === $table->get_script_depends() && array( 'digitalisimo-content-switcher' ) === $switcher->get_script_depends() && 'digitalisimo-content-switcher' === $switcher->get_name() && false === $switcher->show_in_panel() && $switcher instanceof \Digitalisimo\Elements\Fancy_Tabs_Widget && array( 'digitalisimo-fancy-tabs' ) === $switcher->get_style_depends(), 'La tabla no necesita JS y el alternador es un alias oculto de Pestañas con su script y su hoja.' );
	$table->settings = array( 'comparison_list_title' => 'Funciones', 'comparison_header_list' => array( array( 'header_title' => 'Gratis', 'header_link' => array( 'url' => '' ), 'header_button_text' => 'Vacío' ), array( 'header_title' => 'Pro', 'header_active' => 'yes', 'header_button_text' => 'Comprar', 'header_link' => array( 'url' => 'https://example.test/pro', 'is_external' => true, 'nofollow' => true ) ) ), 'comparison_list' => array( array( 'title' => 'API <script>x</script>', 'description' => 'Detalle', 'feature_ability' => '0|1' ) ) );
	ob_start(); (new \ReflectionMethod( $table, 'render' ))->invoke( $table ); $html = ob_get_clean();
	check_compare( str_contains( $html, '<table>' ) && str_contains( $html, 'scope="col"' ) && str_contains( $html, 'scope="row"' ) && str_contains( $html, 'aria-label="Incluido"' ), 'La comparación debe ser una tabla semántica con estados legibles.' );
	check_compare( 1 === substr_count( $html, '<a ' ) && str_contains( $html, 'href="https://example.test/pro"' ) && ! str_contains( $html, '<script' ), 'Sólo enlaces válidos, sin HTML de entrada.' );
	check_compare( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ), 'Los enlaces externos conservan nofollow y protegen la pestaña.' );
	ob_start(); (new \ReflectionMethod( $table, 'content_template' ))->invoke( $table ); $editor = ob_get_clean();
	check_compare( str_contains( $editor, 'plan.header_button_text' ) && str_contains( $editor, 'link.is_external' ) && str_contains( $editor, 'noopener noreferrer' ), 'La vista previa debe mostrar el CTA y sus atributos.' );
	$switcher->settings = \Digitalisimo\Elements\Content_Switcher_Widget::upgrade_settings( array( 'switcher_items' => array( array( 'title' => 'Uno', 'content' => '<p>Primero</p>', 'switcher_icon' => array( 'value' => 'fas fa-star' ) ), array( 'title' => 'Dos', 'content' => '<p>Segundo</p><script>x</script>' ), array( 'content' => 'Sin título' ) ) ) );
	check_compare( 'switcher' === $switcher->settings['style'] && 'Uno' === $switcher->settings['tabs'][0]['tab_title'] && 'icon' === $switcher->settings['tabs'][0]['icon_type'] && 'none' === $switcher->settings['tabs'][1]['icon_type'] && 'Opción 3' === $switcher->settings['tabs'][2]['tab_title'] && ! isset( $switcher->settings['switcher_items'] ), 'Las opciones del alternador pasan a pestañas con estilo de botones y título de respaldo.' );
	$render = new \ReflectionMethod( $switcher, 'render' );
	ob_start(); $render->invoke( $switcher ); $html = ob_get_clean();
	check_compare( 3 === substr_count( $html, 'role="tab"' ) && 3 === substr_count( $html, 'role="tabpanel"' ) && str_contains( $html, '<button class="digi-fancy-tabs__tab digi-content-switcher__tab" type="button" id="digi-fancy-tabs-test-01-tab-0"' ) && str_contains( $html, 'aria-selected="true"' ) && str_contains( $html, '<div class="digi-fancy-tabs digi-fancy-tabs--switcher digi-content-switcher digi-content-switcher--switcher" data-digi-content-switcher' ) && str_contains( $html, '<div class="digi-fancy-tabs__panel digi-content-switcher__panel"' ) && str_contains( $html, '<span class="digi-fancy-tabs__icon" aria-hidden="true"><i aria-hidden="true"></i></span><span class="digi-fancy-tabs__label"><strong>Uno</strong></span>' ), 'Las pestañas deben estar relacionadas por IDs propios y conservar las clases del alternador.' );
	check_compare( str_contains( $html, 'hidden' ) && ! str_contains( $html, '<script' ) && str_contains( $html, '<noscript>' ), 'El panel inicial debe ser visible y existir fallback sin JS.' );
	ob_start(); $render->invoke( $switcher ); $second = ob_get_clean();
	check_compare( ! str_contains( $second, '<noscript>' ), 'El fallback CSS no debe duplicarse por instancia.' );
	ob_start(); (new \ReflectionMethod( $switcher, 'content_template' ))->invoke( $switcher ); $editor = ob_get_clean();
	check_compare( str_contains( $editor, 'data-digi-content-switcher' ) && str_contains( $editor, 'aria-controls=' ) && str_contains( $editor, 'aria-labelledby=' ) && str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'item.tab_content' ) && str_contains( $editor, '["digi-content-switcher",{' ), 'El editor debe mostrar todos los paneles, iconos y relaciones accesibles con las clases de antes.' );
	$switcher->settings = \Digitalisimo\Elements\Content_Switcher_Widget::upgrade_settings( array( 'switcher_items' => array( array( 'title' => 'Mensual', 'content_type' => 'link_section', 'link_target' => '#precios-mes' ), array( 'title' => 'Anual', 'content_type' => 'link_section', 'link_target' => 'precios-año', 'switcher_active' => 'yes' ) ) ) );
	ob_start(); $render->invoke( $switcher ); $linked = ob_get_clean();
	check_compare( str_contains( $linked, 'data-digi-target="precios-mes"' ) && str_contains( $linked, 'data-digi-selected="1"' ) && str_contains( $linked, 'id="digi-fancy-tabs-test-01-tab-1" role="tab" aria-controls="digi-fancy-tabs-test-01-panel-1" aria-selected="true"' ), 'Las opciones enlazadas a secciones y la activa inicial deben respetarse.' );
	check_compare( isset( $switcher->controls['tabs'] ) && 'hidden' === $switcher->controls['switcher_items']['type'], 'El repetidor de pestañas debe existir y las opciones de antes seguir con control oculto.' );
	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/content-switcher.js' );
	check_compare( str_contains( $js, 'data-digi-target' ) && str_contains( $js, 'data-digi-selected' ), 'El script debe alternar los elementos enlazados.' );
	check_compare( str_contains( $js, 'WeakSet' ) && str_contains( $js, 'ArrowRight' ) && str_contains( $js, 'ArrowLeft' ), 'El script debe aislar instancias y permitir navegación por teclado.' );
	echo "DIGITALÍSIMO Elements: Tabla comparativa y alternador (alias de Pestañas) validados.\n";
}
