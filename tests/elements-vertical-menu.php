<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); private $attrs = array();
		public function get_id() { return 'vm1'; }
		public function get_settings_for_display() { return $this->settings; }
		public function add_link_attributes( $name, $link ) { $this->attrs[ $name ]['href'] = $link['url']; }
		public function add_render_attribute( $name, $key, $value ) { $this->attrs[ $name ][ $key ] = $value; }
		public function get_render_attribute_string( $name ) {
			$out = array();
			foreach ( $this->attrs[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"'; }
			return implode( ' ', $out );
		}
	}
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_menu( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function untrailingslashit( $v ) { return rtrim( $v, '/\\' ); }
	function home_url( $path = '' ) { return 'https://site.test' . $path; }
	function wp_unslash( $v ) { return $v; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-vertical-menu.php';
	$widget = new \Digitalisimo\Elements\Vertical_Menu_Widget();
	$render = static function () use ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	// Los niveles forman el árbol; un salto de nivel se corrige al inmediato siguiente.
	$tree = \Digitalisimo\Elements\Vertical_Menu_Widget::static_nodes( array(
		array( 'item_title' => 'A' ), array( 'item_title' => 'B' ), array( 'item_title' => 'B1', 'item_level' => '1' ),
		array( 'item_title' => 'B1a', 'item_level' => '3' ), array( 'item_title' => 'B2', 'item_level' => '1' ), array( 'item_title' => '' ), array( 'item_title' => 'C', 'item_level' => '0' ),
	) );
	check_menu( 3 === count( $tree ) && 'B' === $tree[1]['title'] && 2 === count( $tree[1]['children'] ) && 'B1a' === $tree[1]['children'][0]['children'][0]['title'], 'Los niveles deben anidarse bajo el elemento anterior.' );

	$_SERVER['REQUEST_URI'] = '/servicios/web/';
	$widget->settings = array( 'source' => 'static', 'mode' => 'collapse', 'open_current' => 'yes', 'nav_label' => 'Lateral', 'items' => array(
		array( '_id' => 'a1', 'item_title' => 'Inicio', 'item_link' => array( 'url' => 'https://site.test/' ) ),
		array( 'item_title' => 'Servicios <b>x</b>', 'item_link' => array( 'url' => 'https://site.test/servicios/' ) ),
		array( 'item_title' => 'Web', 'item_level' => '1', 'item_link' => array( 'url' => 'https://site.test/servicios/web' ), 'item_icon' => array( 'value' => 'fas fa-code' ) ),
	) );
	$html = $render();
	check_menu( str_contains( $html, '<nav class="digi-vertical-menu digi-vertical-menu--collapse" aria-label="Lateral"' ) && str_contains( $html, 'elementor-repeater-item-a1' ), 'Debe pintar la navegación con su nombre accesible.' );
	check_menu( str_contains( $html, 'aria-expanded="true" aria-controls="digi-vm-vm1-1"' ) && str_contains( $html, 'is-open' ) && str_contains( $html, 'aria-current="page"' ), 'La rama de la página actual se abre y marca la página.' );
	check_menu( str_contains( $html, 'aria-label="Submenú de Servicios x"' ) && ! str_contains( $html, '<b>' ) && str_contains( $html, 'href="https://site.test/servicios/"' ), 'El padre conserva su enlace y el submenú se abre con un botón con nombre.' );

	$widget->settings['mode'] = 'drill';
	$widget->settings['parent_toggles'] = 'yes';
	$drill = $render();
	check_menu( str_contains( $drill, 'data-digi-menu-back>Servicios x</button>' ) && str_contains( $drill, '<button type="button" class="digi-vertical-menu__link" aria-expanded="false"' ) && ! str_contains( $drill, 'href="https://site.test/servicios/"' ), 'En paneles cada submenú tiene «Atrás» y el padre sólo abre su panel.' );

	$widget->settings = array( 'source' => 'menu', 'menu' => '' );
	check_menu( '' === $render(), 'Sin menú elegido no se pinta nada.' );
	echo "DIGITALÍSIMO Elements: menú vertical validado.\n";
}
