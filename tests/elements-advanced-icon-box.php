<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = array( 'type' => $type ) + $args; }
	}
	class Controls_Manager {
		const SELECT = 'select', ICONS = 'icons', MEDIA = 'media', TEXT = 'text', SWITCHER = 'switcher', WYSIWYG = 'wysiwyg', URL = 'url', TAB_STYLE = 'style', CHOOSE = 'choose', SLIDER = 'slider', COLOR = 'color';
	}
	class Icons_Manager {
		public static function render_icon( $icon, $attributes ) { echo '<i class="test-star" aria-hidden="true"></i>'; }
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return preg_match( '~^(https?://|/)~', (string) $value ) ? (string) $value : ''; }
	function absint( $value ) { return abs( (int) $value ); }
	function get_post_meta( $id, $key, $single ) { return 'Logotipo accesible'; }
	function wp_get_attachment_image( $id, $size, $icon, $attributes ) { return 23 === $id ? '<img src="/media/logo.webp" alt="' . esc_attr( $attributes['alt'] ) . '" width="100" height="80">' : ''; }
	function wp_kses_post( $value ) { return strip_tags( $value, '<p><strong><em><a>' ); }
	function check_icon_box( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-advanced-icon-box.php';
	$widget = new \Digitalisimo\Elements\Advanced_Icon_Box_Widget();
	check_icon_box( 'digitalisimo-advanced-icon-box' === $widget->get_name() && array( 'digitalisimo-advanced-icon-box' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El widget debe usar sólo su CSS condicional.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	foreach ( array( 'icon_type', 'selected_icon', 'image', 'title_text', 'description_text', 'position', 'global_link', 'global_link_url', 'readmore_link', 'badge_text', 'text_align', 'icon_size', 'title_typography', 'subtitle_typography', 'description_typography', 'box_shadow' ) as $name ) {
		check_icon_box( isset( $widget->controls[ $name ] ), 'Falta control ' . $name );
	}
	check_icon_box( '{{WRAPPER}} .digi-advanced-icon-box__title' === $widget->controls['title_typography']['selector'] && '{{WRAPPER}} .digi-advanced-icon-box' === $widget->controls['box_shadow']['selector'], 'Los estilos opcionales deben afectar sólo a la instancia actual.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'icon_type' => 'icon', 'selected_icon' => array( 'value' => 'fas fa-star' ), 'title_text' => '<script>X</script>', 'title_size' => 'script', 'description_text' => '<p>Texto <strong>útil</strong></p><script>X</script>', 'readmore' => 'yes', 'readmore_text' => 'Más', 'readmore_link' => array( 'url' => 'https://example.test/detalle', 'is_external' => true ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_icon_box( str_contains( $html, '<h3 class="digi-advanced-icon-box__title">' ) && str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ), 'El título debe tener etiqueta segura y escapar texto.' );
	check_icon_box( str_contains( $html, 'test-star' ) && str_contains( $html, '<strong>útil</strong>' ) && str_contains( $html, 'rel="noopener noreferrer"' ), 'Deben conservarse el icono, HTML editorial permitido y enlace externo seguro.' );
	$widget->settings = array( 'icon_type' => 'image', 'image' => array( 'id' => 23, 'url' => 'javascript:alert(1)' ), 'title_text' => '', 'description_text' => '', 'global_link' => 'yes', 'global_link_url' => array( 'url' => 'https://example.test/tarjeta' ), 'badge' => 'yes', 'badge_text' => '<Oferta>', 'readmore' => 'yes', 'readmore_text' => 'Más', 'readmore_link' => array( 'url' => '/otro' ) );
	ob_start(); $render->invoke( $widget ); $html = ob_get_clean();
	check_icon_box( str_contains( $html, 'logo.webp' ) && str_contains( $html, 'Logotipo accesible' ) && ! str_contains( $html, 'javascript:' ), 'La imagen debe usar el adjunto y su ALT real.' );
	check_icon_box( 1 === substr_count( $html, '<a ' ) && str_contains( $html, 'digi-advanced-icon-box__overlay' ) && ! str_contains( $html, 'digi-advanced-icon-box__more' ), 'El enlace de toda la tarjeta no debe producir enlaces anidados.' );
	check_icon_box( str_contains( $html, '&lt;Oferta&gt;' ), 'El distintivo debe escapar HTML.' );
	$widget->settings = array( 'icon_type' => 'image', 'image' => array( 'id' => 0, 'url' => 'javascript:alert(1)' ), 'title_text' => '', 'description_text' => '' );
	ob_start(); $render->invoke( $widget ); $empty = ob_get_clean();
	check_icon_box( '' === $empty, 'Una tarjeta vacía no debe añadir nodos.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_icon_box( str_contains( $editor, 'safeUrl( settings.global_link_url )' ) && str_contains( $editor, 'elementor.helpers.renderIcon' ), 'El editor debe previsualizar icono y enlace seguros.' );
	check_icon_box( str_contains( $editor, 'linkRel( settings.global_link_url )' ) && str_contains( $editor, 'linkRel( settings.title_link_url )' ) && str_contains( $editor, 'linkRel( settings.readmore_link )' ), 'Los tres enlaces del editor deben conservar la política de destino y rel del frontend.' );
	check_icon_box( str_contains( $editor, 'aria-hidden="{{ settings.icon_type === \'image\' && !String( settings.title_text || \'\' ).trim() ? \'false\' : \'true\' }}"' ), 'Una imagen informativa sin título no debe ocultarse al lector de pantalla en la vista previa.' );
	echo "DIGITALÍSIMO Elements: Caja de icono avanzada validada.\n";
}
