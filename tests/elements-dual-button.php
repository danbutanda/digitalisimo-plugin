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
        public function add_link_attributes( $name, $link ) {
            $this->attributes[ $name ] = array( 'href' => $link['url'] );
            if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; }
            if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); }
        }
        public function add_render_attribute( $name, $key, $value ) {
            $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value );
        }
        public function get_render_attribute_string( $name ) {
            $out = array();
            foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; }
            return implode( ' ', $out );
        }
    }
    class Controls_Manager { const TEXT = 'text'; const URL = 'url'; const ICONS = 'icons'; const SWITCHER = 'switcher'; const SELECT = 'select'; const TAB_STYLE = 'style'; const CHOOSE = 'choose'; const SLIDER = 'slider'; const COLOR = 'color'; }
    class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
    function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
    function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
    function check_dual( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
    require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-dual-button.php';
    $widget = new \Digitalisimo\Elements\Dual_Button_Widget();
    check_dual( array( 'digitalisimo-dual-button' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'El widget debe usar sólo su CSS.' );
    (new \ReflectionMethod( $widget, 'register_controls' ))->invoke( $widget );
    check_dual( isset( $widget->controls['button_a_link'], $widget->controls['button_b_link'], $widget->controls['button_a_icon'], $widget->controls['button_b_icon'] ), 'Faltan controles de los dos botones.' );
    $widget->settings = array(
        'button_a_text' => 'Conoce <script>más</script>',
        'button_a_link' => array( 'url' => 'https://example.test/a', 'is_external' => true, 'nofollow' => true ),
        'button_a_icon' => array( 'value' => 'fas fa-star' ),
        'button_b_text' => 'Contactar', 'button_b_link' => array( 'url' => '/contacto/' ),
        'show_middle_text' => 'yes', 'middle_text' => 'o',
    );
    ob_start(); (new \ReflectionMethod( $widget, 'render' ))->invoke( $widget ); $html = ob_get_clean();
    check_dual( 2 === substr_count( $html, '<a ' ) && str_contains( $html, 'rel="nofollow noopener noreferrer"' ) && str_contains( $html, 'target="_blank"' ), 'Los dos enlaces deben renderizarse y el externo proteger la pestaña.' );
    check_dual( str_contains( $html, 'Conoce más' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<i aria-hidden="true">' ), 'El texto debe ser seguro y el icono decorativo.' );
    ob_start(); (new \ReflectionMethod( $widget, 'content_template' ))->invoke( $widget ); $editor = ob_get_clean();
    check_dual( str_contains( $editor, 'elementor.helpers.renderIcon' ) && str_contains( $editor, 'settings.button_a_link.is_external' ) && str_contains( $editor, 'noopener noreferrer' ), 'La vista previa debe conservar iconos y atributos de enlaces.' );
    echo "DIGITALÍSIMO Elements: Botón doble validado.\n";
}
