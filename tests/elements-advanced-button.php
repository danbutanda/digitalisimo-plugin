<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups = array();
		public function get_settings_for_display() { return $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'args' => $args ); }
	}
	class Controls_Manager { const TEXT = 'text'; const URL = 'url'; const SELECT = 'select'; const ICONS = 'icons'; const SWITCHER = 'switcher'; const SLIDER = 'slider'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<svg data-icon="' . htmlspecialchars( $icon['value'], ENT_QUOTES, 'UTF-8' ) . '"></svg>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return preg_match( '~^(https?://|/|#)~', (string) $value ) ? htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ) : ''; }
	function check_advanced_button( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-advanced-button.php';
	$widget = new \Digitalisimo\Elements\Advanced_Button_Widget();
	check_advanced_button( 'digitalisimo-advanced-button' === $widget->get_name() && array( 'digitalisimo-advanced-button' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El botón sólo debe cargar su CSS.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_advanced_button( isset( $widget->controls['text'], $widget->controls['link'], $widget->controls['button_icon'], $widget->controls['badge_text'], $widget->controls['button_effect'] ), 'Faltan controles de botón, icono o distintivo.' );
	check_advanced_button( isset( $widget->controls['button_border_style'], $widget->controls['button_border_width'], $widget->controls['button_border_color'], $widget->controls['button_width'], $widget->groups['advanced_button_typography'], $widget->groups['advanced_button_shadow'] ), 'Faltan controles visuales del botón.' );
	check_advanced_button( '' === $widget->controls['button_border_style']['default'] && str_contains( $widget->controls['button_border_width']['selectors']['{{WRAPPER}} .digi-advanced-button'], 'border-width:') && 'typography' === $widget->groups['advanced_button_typography']['type'], 'Los estilos nuevos deben conservar el diseño anterior hasta configurarse y generar CSS por widget.' );
	$widget->settings = array( 'text' => 'Comprar <ahora>', 'link' => array( 'url' => 'https://example.com/', 'is_external' => 'on', 'nofollow' => 'on' ), 'button_size' => 'lg', 'button_effect' => 'c', 'button_icon' => array( 'value' => 'star' ), 'icon_align' => 'left', 'show_button_badge' => 'yes', 'badge_text' => 'Oferta', 'badge_align' => 'right', 'button_css_id' => 'cta" onclick="x' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_advanced_button( str_contains( $html, 'href="https://example.com/"' ) && str_contains( $html, 'target="_blank"' ) && str_contains( $html, 'rel="noopener noreferrer nofollow"' ), 'El enlace externo debe conservar URL y atributos seguros.' );
	check_advanced_button( str_contains( $html, 'digi-advanced-button--effect-c' ) && str_contains( $html, 'data-icon="star"' ) && str_contains( $html, 'Oferta' ), 'Deben mostrarse efecto, icono y distintivo.' );
	check_advanced_button( ! str_contains( $html, '<ahora>' ) && ! str_contains( $html, 'onclick=' ) && str_contains( $html, 'Comprar &lt;ahora&gt;' ), 'El contenido y el ID deben escaparse.' );
	$widget->settings['icon_align'] = 'bottom';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $bottom = ob_get_clean();
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/advanced-button.css' );
	check_advanced_button( strpos( $bottom, 'digi-advanced-button__text' ) < strpos( $bottom, 'digi-advanced-button__icon' ) && str_contains( $css, '.digi-advanced-button--icon-bottom .digi-advanced-button__content{flex-direction:column}' ) && ! str_contains( $css, '.digi-advanced-button--icon-bottom .digi-advanced-button__content{flex-direction:column-reverse}' ), 'El icono inferior debe verse debajo del texto.' );
	$widget->settings['icon_align'] = 'left';
	$widget->settings['link']['url'] = 'https://example.com/?a=1&b=2';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $query = ob_get_clean();
	check_advanced_button( str_contains( $query, 'href="https://example.com/?a=1&amp;b=2"' ) && ! str_contains( $query, '&amp;amp;' ), 'La URL no debe escaparse dos veces.' );
	$widget->settings['link']['url'] = 'javascript:alert(1)';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $invalid = ob_get_clean();
	check_advanced_button( ! str_contains( $invalid, '<a ' ) && str_contains( $invalid, '<span class="digi-advanced-button' ), 'Un destino inseguro no debe ser interactivo.' );
	$widget->settings['text'] = '';
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $empty = ob_get_clean();
	check_advanced_button( '' === $empty, 'No debe pintarse un botón sin nombre accesible.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_advanced_button( str_contains( $editor, 'digi-advanced-button__content' ) && str_contains( $editor, 'settings.button_icon' ) && str_contains( $editor, 'target="_blank"' ) && str_contains( $editor, 'rel="{{ rel }}"' ) && str_contains( $editor, 'id="{{ buttonId }}"' ), 'La vista previa debe conservar texto, icono y atributos del enlace.' );
	echo "DIGITALÍSIMO Elements: Botón avanzado validado.\n";
}
