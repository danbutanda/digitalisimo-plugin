<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_id() { return 'widget-1'; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; $this->attributes[ $name ] = array( 'href' => $link['url'] ); if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; } if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); } }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) { $out = array(); foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; } return implode( ' ', $out ); }
	}
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Controls_Manager { const SELECT = 'select'; const TEXT = 'text'; const WYSIWYG = 'wysiwyg'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const ICONS = 'icons'; const COLOR = 'color'; const SLIDER = 'slider'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><strong><em><br>' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="' . $attrs['class'] . '" src="https://site.test/icono.webp" alt="">'; }
	function check_fancy_tabs( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-fancy-tabs.php';
	$widget = new \Digitalisimo\Elements\Fancy_Tabs_Widget();
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_fancy_tabs( isset( $widget->controls['tabs']['fields']['content_type'], $widget->controls['tabs']['fields']['active'], $widget->controls['style'], $widget->controls['columns'] ) && array( 'digitalisimo-content-switcher' ) === $widget->get_script_depends(), 'Debe ofrecer tipo de contenido, pestaña activa y estilo, y reutilizar el script de pestañas.' );
	$widget->settings = array( 'tabs' => array(
		array( 'tab_title' => 'Primera', 'tab_sub_title' => 'Uno', 'tab_content' => '<p>Contenido</p><script>x</script>', 'icon_type' => 'image', 'image' => array( 'id' => 10, 'url' => 'https://site.test/icono.webp' ), 'tabs_button' => 'Leer', 'button_link' => array( 'url' => 'https://site.test/leer', 'is_external' => true, 'nofollow' => true ) ),
		array( 'tab_title' => 'Segunda', 'tab_content' => '<p>Segundo</p>', 'icon_type' => 'icon', 'selected_icon' => array( 'value' => 'fas fa-star' ) ),
		array( 'tab_title' => '', 'tab_content' => 'Sin título' ),
	) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_fancy_tabs( 2 === substr_count( $html, 'role="tab"' ) && 2 === substr_count( $html, 'role="tabpanel"' ) && str_contains( $html, '<div class="digi-fancy-tabs digi-fancy-tabs--cards" data-digi-content-switcher data-digi-selected="0">' ) && str_contains( $html, '<div class="digi-fancy-tabs__panels">' ), 'Debe renderizar dos pestañas y sus paneles relacionados con el estilo de tarjetas.' );
	check_fancy_tabs( str_contains( $html, 'aria-controls="digi-fancy-tabs-widget-1-panel-0"' ) && str_contains( $html, 'id="digi-fancy-tabs-widget-1-panel-0"' ), 'Los identificadores ARIA deben corresponder.' );
	check_fancy_tabs( str_contains( $html, 'src="https://site.test/icono.webp" alt=""' ) && str_contains( $html, 'href="https://site.test/leer"' ) && ! str_contains( $html, '<script' ), 'La imagen decorativa, el enlace y el contenido saneado deben salir correctamente.' );
	check_fancy_tabs( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && ! str_contains( $html, 'loading="lazy"' ), 'El enlace externo debe proteger la pestaña y WordPress decidir la carga de la imagen.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_fancy_tabs( str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'aria-controls=' ) && str_contains( $editor, 'aria-labelledby=' ) && str_contains( $editor, 'item.tab_content' ), 'El editor debe mostrar todos los paneles, iconos y relaciones ARIA.' );
	check_fancy_tabs( str_contains( $html, '<noscript>' ), 'El contenido debe seguir disponible sin JavaScript.' );
	$widget->settings = array( 'style' => 'switcher', 'tabs' => array( array( 'tab_title' => 'Mensual', 'content_type' => 'link_section', 'link_target' => '#precios-mes' ), array( 'tab_title' => 'Anual', 'content_type' => 'link_section', 'link_target' => 'precios-año', 'active' => 'yes' ) ) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $linked = ob_get_clean();
	check_fancy_tabs( str_contains( $linked, 'digi-fancy-tabs--switcher' ) && str_contains( $linked, 'data-digi-selected="1"' ) && str_contains( $linked, 'data-digi-target="precios-mes"' ) && str_contains( $linked, 'id="digi-fancy-tabs-widget-1-tab-1" role="tab" aria-controls="digi-fancy-tabs-widget-1-panel-1" aria-selected="true"' ), 'Las pestañas enlazadas a secciones y la activa inicial se respetan en el estilo de botones.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/fancy-tabs.css' );
	check_fancy_tabs( str_contains( $css, '.digi-fancy-tabs--switcher .digi-fancy-tabs__tabs{display:flex' ) && str_contains( $css, '.digi-fancy-tabs--switcher .digi-fancy-tabs__tab[aria-selected=true]' ) && ! is_file( __DIR__ . '/../digitalisimo-elements/assets/css/content-switcher.css' ), 'La hoja de pestañas cubre los dos estilos.' );
	echo "DIGITALÍSIMO Elements: Pestañas validadas en sus dos estilos.\n";
}
