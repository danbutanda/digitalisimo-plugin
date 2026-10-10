<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function add_link_attributes( $key, $link ) {
			$this->attributes[ $key ] = array( 'href' => $link['url'] );
			if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $key ]['target'] = '_blank'; }
			if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $key ]['rel'] = array( 'nofollow' ); }
		}
		public function add_render_attribute( $key, $attribute, $value ) { $this->attributes[ $key ][ $attribute ] = array_merge( (array) ( $this->attributes[ $key ][ $attribute ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $key ) {
			$out = array();
			foreach ( $this->attributes[ $key ] as $attribute => $value ) { $out[] = $attribute . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; }
			return implode( ' ', $out );
		}
	}
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function esc_url( $value ) { return esc_html( $value ); }
	function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
	function sanitize_html_class( $value ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_get_nav_menu_object( $id ) { return false; }
	function get_bloginfo( $value ) { return 'Sitio'; }
	function home_url( $path ) { return 'https://site.test' . $path; }
	function check_icon_navigation( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-icon-mobile-menu.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-icon-nav.php';
	$external = array( 'url' => 'https://external.test/', 'is_external' => true, 'nofollow' => true );
	$mobile = new \Digitalisimo\Elements\Icon_Mobile_Menu_Widget();
	$mobile->settings = array( 'menu_style' => 'style-3', 'menu_tooltip' => 'yes', 'menu_items' => array( array( '_id' => 'one', 'menu_text' => 'Externo', 'menu_icon' => array( 'value' => 'fas fa-star' ), 'link' => $external ) ) );
	ob_start(); (new \ReflectionMethod( $mobile, 'render' ))->invoke( $mobile ); $html = ob_get_clean();
	check_icon_navigation( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'data-tooltip="Externo"' ), 'El menú móvil debe proteger enlaces externos y mostrar su nombre.' );
	ob_start(); (new \ReflectionMethod( $mobile, 'content_template' ))->invoke( $mobile ); $editor = ob_get_clean();
	check_icon_navigation( str_contains( $editor, 'data-tooltip=' ) && str_contains( $editor, 'target="_blank"' ), 'El editor del menú móvil debe reflejar tooltip y destino externo.' );
	$nav = new \Digitalisimo\Elements\Icon_Nav_Widget();
	$nav->settings = array( 'iconnavs' => array( array( 'iconnav_title' => 'Externo', 'iconnav_icon' => array( 'value' => 'fas fa-star' ), 'iconnav_link' => $external ) ), 'navbar' => '0' );
	ob_start(); (new \ReflectionMethod( $nav, 'render' ))->invoke( $nav ); $html = ob_get_clean();
	check_icon_navigation( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'data-tooltip="Externo"' ), 'La navegación con iconos debe proteger el enlace y mostrar su nombre.' );
	ob_start(); (new \ReflectionMethod( $nav, 'content_template' ))->invoke( $nav ); $editor = ob_get_clean();
	check_icon_navigation( str_contains( $editor, 'settings.branding_image.url' ) && str_contains( $editor, 'data-tooltip=' ) && str_contains( $editor, 'target="_blank"' ), 'El editor de navegación debe mostrar marca y enlace como el frontend.' );
	echo "DIGITALÍSIMO Elements: navegación con iconos validada.\n";
}
