<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array(); public $controls = array(); private $links = array(); private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
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
	class Controls_Manager { const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const ICONS = 'icons'; const CHOOSE = 'choose'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_meta( $id, $key, $single ) { return $id === 10 ? 'Imagen de prueba' : ''; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return '<img class="digi-custom-gallery__image" src="https://site.test/image.webp" alt="' . htmlspecialchars( $attrs['alt'], ENT_QUOTES, 'UTF-8' ) . '" srcset="https://site.test/image.webp 800w">'; }
	function wp_get_attachment_caption( $id ) { return 'Pie de Medios'; }
	function check_visual( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-custom-gallery.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-creative-button.php';
	$gallery = new \Digitalisimo\Elements\Custom_Gallery_Widget();
	$creative = new \Digitalisimo\Elements\Creative_Button_Widget();
	foreach ( array( $gallery, $creative ) as $widget ) { (new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget ); check_visual( array() === $widget->get_script_depends(), 'Los widgets no deben cargar JavaScript.' ); }
	$gallery->settings = array( 'gallery_images' => array( array( 'id' => 10, 'url' => 'https://site.test/image.webp', 'alt' => 'ALT original' ) ), 'gallery_items' => array( array( 'gallery_image' => array( 'url' => 'https://site.test/otra.webp' ), 'image_title' => 'Otro <script>x</script>', 'image_text' => 'Descripción', 'image_link' => array( 'url' => 'https://site.test/otro', 'is_external' => true, 'nofollow' => true ) ) ) );
	ob_start(); (new \ReflectionMethod( $gallery, 'render' ))->invoke( $gallery ); $html = ob_get_clean();
	check_visual( 2 === substr_count( $html, '<figure>' ) && str_contains( $html, 'srcset=' ) && str_contains( $html, 'alt="Imagen de prueba"' ), 'La galería debe usar Medios y su ALT sin perder srcset.' );
	check_visual( str_contains( $html, 'href="https://site.test/otro"' ) && ! str_contains( $html, '<script' ), 'El enlace individual y los textos deben ser seguros.' );
	check_visual( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ), 'El enlace externo protege la pestaña y conserva nofollow.' );
	ob_start(); (new \ReflectionMethod( $gallery, 'content_template' ))->invoke( $gallery ); $editor = ob_get_clean();
	check_visual( str_contains( $editor, 'item.image.alt' ) && str_contains( $editor, 'link.is_external' ) && str_contains( $editor, 'noopener noreferrer' ), 'El editor debe respetar ALT y atributos del enlace.' );
	$creative->settings = array( 'text' => 'Conocer <script>x</script>', 'link' => array( 'url' => 'https://site.test/contacto', 'is_external' => true, 'nofollow' => true ), 'icon' => array( 'value' => 'fas fa-star' ), 'effect' => 'lift' );
	ob_start(); (new \ReflectionMethod( $creative, 'render' ))->invoke( $creative ); $html = ob_get_clean();
	check_visual( str_contains( $html, 'digi-creative-button--lift' ) && str_contains( $html, 'href="https://site.test/contacto"' ) && ! str_contains( $html, '<script' ), 'Botón creativo debe sanear texto y enlace.' );
	check_visual( str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'aria-hidden="true"' ), 'Botón creativo debe proteger pestañas externas y mostrar su icono.' );
	ob_start(); (new \ReflectionMethod( $creative, 'content_template' ))->invoke( $creative ); $editor = ob_get_clean();
	check_visual( str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'link.is_external' ) && str_contains( $editor, 'noopener noreferrer' ), 'El editor debe mostrar icono y atributos del enlace.' );
	$creative->settings['link']['url'] = '';
	ob_start(); (new \ReflectionMethod( $creative, 'render' ))->invoke( $creative ); $html = ob_get_clean();
	check_visual( ! str_contains( $html, '<a ' ) && str_contains( $html, '<span class="digi-creative-button' ), 'Sin URL no debe existir enlace vacío.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/creative-button.css' );
	check_visual( str_contains( $css, 'prefers-reduced-motion' ) && str_contains( $css, ':focus-visible' ), 'Los efectos deben respetar movimiento reducido y foco.' );
	echo "DIGITALÍSIMO Elements: Galería y Botón creativo validados.\n";
}
