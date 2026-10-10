<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->links[ $name ] = $link['url']; $this->attributes[ $name ] = array( 'href' => $link['url'] ); if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; } if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); } }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) { $out = array(); foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; } return implode( ' ', $out ); }
	}
	class Controls_Manager { const SELECT = 'select'; const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const SWITCHER = 'switcher'; const ICONS = 'icons'; const CHOOSE = 'choose'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return 'Fotografía de servicio'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="' . $attrs['class'] . '" src="https://site.test/foto.webp" alt="' . htmlspecialchars( $attrs['alt'], ENT_QUOTES, 'UTF-8' ) . '" srcset="https://site.test/foto.webp 800w">'; }
	function check_featured( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-featured-box.php';
	$widget = new \Digitalisimo\Elements\Featured_Box_Widget();
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_featured( isset( $widget->controls['layout'], $widget->controls['image'], $widget->controls['readmore_link'] ) && array() === $widget->get_script_depends(), 'Debe tener controles editoriales sin scripts.' );
	$widget->settings = array( 'layout' => 'overlay', 'content_position' => 'bottom-right', 'image' => array( 'id' => 10, 'url' => 'https://site.test/foto.webp' ), 'title_text' => 'Servicio <script>x</script>', 'title_size' => 'script', 'title_link_url' => array( 'url' => 'https://site.test/titulo', 'is_external' => true ), 'description_text' => 'Descripción <b>segura</b>', 'badge' => 'yes', 'badge_text' => 'Nuevo', 'readmore' => 'yes', 'readmore_text' => 'Ver', 'readmore_link' => array( 'url' => 'https://site.test/servicio', 'is_external' => true, 'nofollow' => true ), 'advanced_readmore_icon' => array( 'value' => 'fas fa-star' ) );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_featured( str_contains( $html, '<article class="digi-featured-box digi-featured-box--overlay digi-featured-box--bottom-right">' ) && str_contains( $html, '<h3 class="digi-featured-box__title">' ), 'Debe respetar diseño/posición y sanear la etiqueta.' );
	check_featured( str_contains( $html, 'srcset=' ) && str_contains( $html, 'alt="Fotografía de servicio"' ) && str_contains( $html, 'href="https://site.test/servicio"' ) && ! str_contains( $html, '<script' ), 'Debe preservar imagen responsiva y enlace, saneando el texto.' );
	check_featured( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'target="_blank"' ), 'Los enlaces externos deben proteger la pestaña.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_featured( str_contains( $editor, 'titleLink.url' ) && str_contains( $editor, 'settings.image.alt' ) && str_contains( $editor, 'elementor.helpers.renderIcon' ), 'El editor debe mostrar título enlazado, ALT e icono.' );
	$widget->settings['layout'] = 'split'; $widget->settings['skin_content_position'] = 'right'; $widget->settings['readmore_link'] = array(); $widget->settings['title_link_url'] = array();
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $split = ob_get_clean();
	check_featured( str_contains( $split, 'digi-featured-box--split digi-featured-box--right' ) && ! str_contains( $split, '<a ' ), 'El diseño dividido no debe crear enlaces vacíos.' );
	echo "DIGITALÍSIMO Elements: Caja destacada validada.\n";
}
