<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); private $links = array();
		public function get_settings_for_display() { return $this->settings; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; }
		public function get_render_attribute_string( $name ) { return 'href="' . htmlspecialchars( $this->links[ $name ], ENT_QUOTES, 'UTF-8' ) . '"'; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function is_type_instance() { return array() === $this->settings; }
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
	function esc_url( $v ) { return esc_html( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-icon-mobile-menu.php';
	$widget = new \Digitalisimo\Elements\Icon_Mobile_Menu_Widget();
	check_icon_menu( array() === $widget->get_script_depends() && 'digitalisimo-icon-mobile-menu' === $widget->get_name() && false === $widget->show_in_panel() && array( 'digitalisimo-icon-nav', 'digitalisimo-icon-nav-bar' ) === $widget->get_style_depends(), 'El menú es un alias oculto de la navegación con iconos: sin scripts y, como instancia de registro, con las dos hojas.' );
	$widget->settings = \Digitalisimo\Elements\Icon_Mobile_Menu_Widget::upgrade_settings( array( 'menu_style' => 'style-4', 'menu_tooltip' => 'yes', 'menu_items' => array( array( '_id' => 'x1', 'menu_text' => 'Inicio <script>x</script>', 'menu_icon' => array( 'value' => 'fas fa-home' ), 'link' => array( 'url' => 'https://site.test/' ) ), array( 'menu_text' => 'Sin icono', 'link' => array( 'url' => 'https://site.test/error' ) ) ) ) );
	check_icon_menu( array( 'digitalisimo-icon-nav-bar' ) === $widget->get_style_depends(), 'En barra horizontal sólo carga la hoja de la barra.' );
	check_icon_menu( 'bar' === $widget->settings['layout'] && 'Inicio <script>x</script>' === $widget->settings['iconnavs'][0]['iconnav_title'] && 'fas fa-home' === $widget->settings['iconnavs'][0]['iconnav_icon']['value'] && 'https://site.test/' === $widget->settings['iconnavs'][0]['iconnav_link']['url'] && ! isset( $widget->settings['menu_items'] ), 'Los elementos del menú móvil pasan al repetidor de la navegación.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_icon_menu( str_contains( $html, '<nav class="digi-icon-nav digi-icon-nav--bar digi-icon-nav--style-4 digi-icon-mobile-menu digi-icon-mobile-menu--bar digi-icon-mobile-menu--style-4" aria-label="Menú de iconos"><ul class="digi-icon-nav__links digi-icon-mobile-menu__list">' ) && str_contains( $html, '<li class="digi-icon-nav__entry digi-icon-mobile-menu__entry elementor-repeater-item-x1"><a class="digi-icon-nav__link digi-icon-mobile-menu__item" href="https://site.test/" aria-label="Inicio x" data-tooltip="Inicio x">' ) && str_contains( $html, '<span class="digi-icon-nav__icon digi-icon-mobile-menu__icon">' ) && str_contains( $html, '<span class="digi-icon-nav__text digi-icon-mobile-menu__text">Inicio x</span>' ), 'Debe mostrar enlace y nombre accesible con las clases nuevas y las de antes.' );
	check_icon_menu( ! str_contains( $html, '<script>' ) && ! str_contains( $html, 'site.test/error' ) && str_contains( $html, 'data-tooltip="Inicio x"' ), 'Debe sanear textos y omitir iconos vacíos en el diseño compacto.' );
	$widget->settings = \Digitalisimo\Elements\Icon_Mobile_Menu_Widget::upgrade_settings( array( 'menu_style' => 'style-2', 'menu_items' => array( array( 'menu_text' => 'Servicio', 'menu_icon' => array() ) ) ) );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $plain = ob_get_clean();
	check_icon_menu( str_contains( $plain, 'digi-icon-mobile-menu--style-2' ) && str_contains( $plain, '<span class="digi-icon-nav__link digi-icon-mobile-menu__item">' ) && ! str_contains( $plain, 'href=' ) && ! str_contains( $plain, 'role="img"' ), 'Sin enlace debe verse texto, no una acción vacía.' );
	$rail = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/icon-nav.css' );
	$bar = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/icon-nav-bar.css' );
	check_icon_menu( str_contains( $bar, '.digi-icon-nav--bar .digi-icon-nav__links{display:flex' ) && str_contains( $bar, '.digi-icon-nav--style-4 .digi-icon-nav__link{width:44px' ) && ! str_contains( $bar, '__rail' ) && str_contains( $rail, '.digi-icon-nav__rail .digi-icon-nav__link[data-tooltip]' ) && ! str_contains( $rail, '--bar' ) && ! is_file( __DIR__ . '/../digitalisimo-elements/assets/css/icon-mobile-menu.css' ), 'La barra lateral y la barra horizontal tienen hojas separadas.' );
	echo "DIGITALÍSIMO Elements: menú móvil con iconos validado como alias de la navegación.\n";
}
