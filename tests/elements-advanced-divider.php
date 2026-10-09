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
	}
	class Controls_Manager { const SELECT = 'select'; const MEDIA = 'media'; const SLIDER = 'slider'; const COLOR = 'color'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { $GLOBALS['divider_image_attrs'] = $attrs; return $id === 23 ? '<img src="https://example.test/media/separator.svg" width="1000" height="100" alt="" aria-hidden="true">' : ''; }
	function check_divider( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-advanced-divider.php';
	$widget = new \Digitalisimo\Elements\Advanced_Divider_Widget();
	check_divider( 'digitalisimo-advanced-divider' === $widget->get_name() && array( 'digitalisimo-advanced-divider' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El separador debe solicitar sólo su CSS.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_divider( isset( $widget->controls['divider_type'], $widget->controls['divider_image'], $widget->controls['divider_width'], $widget->controls['divider_stroke'], $widget->controls['divider_align'], $widget->controls['divider_circle_size'], $widget->controls['divider_circle_gap'] ), 'Faltan controles de diseño y tamaño.' );
	check_divider( isset( $widget->controls['divider_align']['selectors_dictionary']['right'] ), 'La alineación responsiva necesita valores CSS válidos.' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/advanced-divider.css' );
	check_divider( str_contains( $css, '--digi-divider-circle-size:14px' ) && str_contains( $css, '--digi-divider-circle-gap:8px' ) && str_contains( $css, 'width:var(--digi-divider-circle-size)' ), 'Los nuevos controles deben conservar las medidas actuales por defecto.' );
	check_divider( str_contains( $css, '.digi-advanced-divider--cross' ) && str_contains( $css, '.digi-advanced-divider--star' ), 'Las figuras nuevas deben tener estilos propios.' );
	$render = new \ReflectionMethod( $widget, 'render' );
	$widget->settings = array( 'divider_type' => 'circle' );
	ob_start(); $render->invoke( $widget ); $circle = ob_get_clean();
	check_divider( str_contains( $circle, 'digi-advanced-divider--circle' ) && str_contains( $circle, 'role="presentation"' ) && substr_count( $circle, '<span' ) === 3, 'El círculo debe ser decorativo y tener dos segmentos.' );
	foreach ( array( 'cross', 'star' ) as $type ) {
		$widget->settings = array( 'divider_type' => $type );
		ob_start(); $render->invoke( $widget ); $symbol = ob_get_clean();
		check_divider( str_contains( $symbol, 'digi-advanced-divider--' . $type ) && str_contains( $symbol, '<svg' ) && substr_count( $symbol, '<span' ) === 3 && ! str_contains( $symbol, '<script' ), 'Cruz y estrella deben mantener dos segmentos y SVG decorativo sin JS.' );
	}
	$widget->settings = array( 'divider_type' => 'wave' );
	ob_start(); $render->invoke( $widget ); $wave = ob_get_clean();
	check_divider( str_contains( $wave, '<svg' ) && str_contains( $wave, '<path' ) && ! str_contains( $wave, '<script' ), 'La onda debe ser SVG propio sin JS.' );
	$widget->settings = array( 'divider_type' => 'image', 'divider_image' => array( 'id' => 23, 'url' => 'javascript:alert(1)' ) );
	ob_start(); $render->invoke( $widget ); $image = ob_get_clean();
	check_divider( str_contains( $image, 'separator.svg' ) && str_contains( $image, 'alt=""' ) && ! str_contains( $image, 'javascript:' ), 'La imagen debe salir sólo de la biblioteca y ser decorativa.' );
	check_divider( ! isset( $GLOBALS['divider_image_attrs']['loading'] ) && ! isset( $GLOBALS['divider_image_attrs']['fetchpriority'] ), 'WordPress debe decidir loading y fetchpriority según la ubicación real de la imagen.' );
	$widget->settings['divider_image']['id'] = 0;
	ob_start(); $render->invoke( $widget ); $fallback = ob_get_clean();
	check_divider( str_contains( $fallback, 'digi-advanced-divider--line' ) && ! str_contains( $fallback, '<img' ), 'Una imagen ausente debe volver a una línea.' );
	$widget->settings = array( 'divider_type' => '" onclick="alert(1)' );
	ob_start(); $render->invoke( $widget ); $invalid = ob_get_clean();
	check_divider( str_contains( $invalid, 'digi-advanced-divider--line' ) && ! str_contains( $invalid, 'onclick' ), 'Un diseño inválido no debe inyectar atributos.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_divider( str_contains( $editor, 'digi-advanced-divider__wave' ) && str_contains( $editor, "type === 'cross' || type === 'star'" ) && str_contains( $editor, 'Number( settings.divider_image.id ) > 0 && settings.divider_image.url' ), 'La vista previa debe reflejar los símbolos y usar sólo imágenes que el frontend pueda recuperar de Medios.' );
	echo "DIGITALÍSIMO Elements: Separador avanzado validado.\n";
}
