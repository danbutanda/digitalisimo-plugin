<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_link_attributes( $name, $link ) { $this->attributes[ $name ] = array( 'href' => $link['url'] ); if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; } if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); } }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) { $out = array(); foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; } return implode( ' ', $out ); }
	}
	class Controls_Manager { const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const NUMBER = 'number'; const URL = 'url'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function get_post_meta( $id, $key, $single ) { return 7 === $id ? 'ALT original' : ''; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { return 7 === $id ? '<img class="' . $attrs['class'] . '" alt="' . esc_attr( $attrs['alt'] ) . '" srcset="...">' : ''; }
	function check_product( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-product-grid.php';
	$widget = new \Digitalisimo\Elements\Product_Grid_Widget();
	check_product( array( 'digitalisimo-product-grid' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'Debe usar sólo su CSS condicional.' );
	(new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
	check_product( isset( $widget->controls['product_items']['fields']['image'], $widget->controls['product_items']['fields']['rating_number'], $widget->controls['columns'], $widget->controls['row_gap'] ), 'Deben existir las fichas y los controles responsivos.' );
	$widget->settings = array( 'product_items' => array(
		array( 'title' => 'Producto & Co', 'image' => array( 'id' => 7, 'url' => 'https://example.test/uno.webp' ), 'price' => '$100', 'text' => 'Detalle <script>peligroso</script>', 'readmore_link' => array( 'url' => 'https://example.test/uno', 'is_external' => true, 'nofollow' => true ), 'button_text' => 'Comprar', 'rating_number' => 8, 'rating_count' => 3 ),
		array( 'title' => '', 'image' => array( 'url' => 'https://example.test/dos.webp', 'alt' => 'Imagen del producto' ), 'badge_text' => 'Nuevo', 'time' => 'Entrega rápida' ),
		array( 'title' => '', 'image' => array() ),
	), 'title_tag' => 'script' );
	ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
	check_product( 2 === substr_count( $html, '<li class="digi-product-grid__item">' ) && str_contains( $html, '<h3 class="digi-product-grid__title">' ), 'Debe omitir fichas vacías y sanear la etiqueta.' );
	check_product( str_contains( $html, 'srcset="..."' ) && str_contains( $html, 'alt=""' ) && str_contains( $html, 'alt="Imagen del producto"' ) && ! str_contains( $html, 'loading="' ), 'Debe conservar ALT y dejar la carga de imágenes al contexto.' );
	check_product( str_contains( $html, 'href="https://example.test/uno"' ) && str_contains( $html, 'Comprar' ) && ! str_contains( $html, 'href="#"' ), 'No debe crear enlaces falsos.' );
	check_product( str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'target="_blank"' ), 'El enlace externo debe proteger la pestaña.' );
	check_product( str_contains( $html, '5 / 5' ) && ! str_contains( $html, '8 / 5' ) && ! str_contains( $html, 'application/ld+json' ), 'La puntuación manual sólo se muestra como texto acotado.' );
	check_product( ! str_contains( $html, '<script>' ) && str_contains( $html, 'digi-product-grid__badge' ), 'Debe sanear el contenido.' );
	ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
	check_product( str_contains( $editor, 'digi-product-grid__list' ) && str_contains( $editor, 'settings.product_items' ) && str_contains( $editor, 'item.image.alt' ) && str_contains( $editor, 'rating_count' ) && str_contains( $editor, 'rel.join' ), 'El editor debe mostrar ALT, reseñas y enlaces de las fichas.' );
	echo "DIGITALÍSIMO Elements: Cuadrícula de productos validada.\n";
}
