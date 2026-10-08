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
	function check_icon_nav( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function wp_get_nav_menu_object( $id ) { return 7 === $id ? (object) array( 'term_id' => 7 ) : false; }
	function wp_nav_menu( $args ) { $GLOBALS['nav_menu_args'] = $args; echo '<ul class="digi-icon-nav__site-menu"><li><a href="/producto">Producto</a></li></ul>'; }
	function get_bloginfo( $key ) { return 'Sitio Dos'; }
	function home_url( $path ) { return 'https://sitio-dos.test' . $path; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img src="https://sitio-dos.test/logo.webp" alt="' . esc_attr( $attrs['alt'] ) . '">'; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-icon-nav.php';
	$widget = new \Digitalisimo\Elements\Icon_Nav_Widget();
	check_icon_nav( array() === $widget->get_script_depends(), 'La barra no debe cargar UIkit, Popper ni Tippy.' );
	$widget->settings = array( 'iconnav_position' => 'right', 'menu_text' => 'show_as_tooltip', 'show_branding' => 'yes', 'branding_image' => array( 'id' => 11 ), 'navbar' => '7', 'navbar_level' => '2', 'iconnavs' => array( array( 'iconnav_title' => 'Inicio <script>x</script>', 'iconnav_icon' => array( 'value' => 'fas fa-home' ), 'iconnav_link' => array( 'url' => 'https://sitio-dos.test/' ) ) ) );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_icon_nav( str_contains( $html, 'digi-icon-nav--right' ) && str_contains( $html, 'aria-label="Inicio x"' ) && str_contains( $html, 'https://sitio-dos.test/logo.webp' ), 'Debe mostrar enlaces accesibles y marca del sitio actual.' );
	check_icon_nav( str_contains( $html, '<details class="digi-icon-nav__menu">' ) && 2 === $GLOBALS['nav_menu_args']['depth'] && 7 === $GLOBALS['nav_menu_args']['menu'], 'El menú nativo debe limitar niveles y usar el menú del sitio actual.' );
	check_icon_nav( ! str_contains( $html, '<script>' ) && str_contains( $html, 'data-tooltip="Inicio x"' ), 'Debe sanear el texto externo y ofrecer nombre visible por foco.' );
	$widget->settings = array( 'navbar' => '999', 'iconnavs' => array() );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $empty = ob_get_clean();
	check_icon_nav( '' === $empty, 'Sin enlaces ni menú válido no debe pintar una barra vacía.' );
	echo "DIGITALÍSIMO Elements: navegación con iconos aislada por sitio.\n";
}
