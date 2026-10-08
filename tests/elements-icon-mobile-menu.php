<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); private $links = array();
		public function get_settings_for_display() { return $this->settings; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->links[ $name ], ENT_QUOTES, 'UTF-8' ) . '"'; }
	}
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_icon_menu( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-icon-mobile-menu.php';
	$widget = new \Digitalisimo\Elements\Icon_Mobile_Menu_Widget();
	check_icon_menu( array() === $widget->get_script_depends(), 'El menú no debe cargar Popper ni Tippy.' );
	$widget->settings = array( 'menu_style' => 'style-4', 'menu_tooltip' => 'yes', 'menu_items' => array( array( '_id' => 'x1', 'menu_text' => 'Inicio <script>x</script>', 'menu_icon' => array( 'value' => 'fas fa-home' ), 'link' => array( 'url' => 'https://site.test/' ) ), array( 'menu_text' => 'Sin icono', 'link' => array( 'url' => 'https://site.test/error' ) ) ) );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_icon_menu( str_contains( $html, '<nav ' ) && str_contains( $html, 'aria-label="Inicio x"' ) && str_contains( $html, 'href="https://site.test/"' ), 'Debe mostrar enlace y nombre accesible.' );
	check_icon_menu( ! str_contains( $html, '<script>' ) && ! str_contains( $html, 'site.test/error' ) && str_contains( $html, 'data-tooltip="Inicio x"' ), 'Debe sanear textos y omitir iconos vacíos en el diseño compacto.' );
	$widget->settings = array( 'menu_style' => 'style-2', 'menu_items' => array( array( 'menu_text' => 'Servicio', 'menu_icon' => array() ) ) );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $plain = ob_get_clean();
	check_icon_menu( str_contains( $plain, 'digi-icon-mobile-menu--style-2' ) && str_contains( $plain, '<span class="digi-icon-mobile-menu__item">' ) && ! str_contains( $plain, 'href=' ), 'Sin enlace debe verse texto, no una acción vacía.' );
	echo "DIGITALÍSIMO Elements: menú móvil con iconos validado.\n";
}
