<?php
namespace Elementor {
	class Controls_Manager { const TAB_ADVANCED = 'advanced'; const RAW_HTML = 'raw_html'; const URL = 'url'; const SWITCHER = 'switcher'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Plugin { public static $instance; public $editor; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	$GLOBALS['digi'] = array( 'hooks' => array(), 'styles' => array(), 'edit' => false );
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['digi']['edit']; } };
	function add_action( $hook, $callback ) { $GLOBALS['digi']['hooks'][] = $hook; }
	function wp_style_is( $handle ) { return isset( $GLOBALS['digi']['styles'][ $handle ] ); }
	function wp_register_style( $handle ) { $GLOBALS['digi']['styles'][ $handle ] = 'registered'; }
	function wp_enqueue_style( $handle ) { $GLOBALS['digi']['styles'][ $handle ] = 'enqueued'; }
	function plugins_url( $path ) { return 'https://s.test/' . $path; }
	function esc_url( $url, $protocols = null ) { return preg_match( '#^(https?|tel|mailto|sms):#', (string) $url ) ? htmlspecialchars( $url, ENT_QUOTES ) : ''; }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
	function check_ext( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	class Fake_Element {
		public $settings = array(), $controls = array(), $sections = array();
		public function get_settings() { return $this->settings; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; }
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args + array( 'group' => $type ); }
	}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-legacy/class-extensions.php';
	use Digitalisimo\Elements\Legacy\Extensions;

	Extensions::init();
	check_ext( in_array( 'elementor/element/common/_section_style/after_section_end', $GLOBALS['digi']['hooks'], true ) && in_array( 'elementor/frontend/after_render', $GLOBALS['digi']['hooks'], true ), 'Sin Element Pack deben registrarse controles y render.' );
	$element = new Fake_Element();
	Extensions::register_controls( $element );
	$section = $element->sections[ Extensions::SECTION ];
	check_ext( 'or' === $section['conditions']['relation'] && 6 === count( $section['conditions']['terms'] ), 'La sección sólo aparece con alguna extensión en uso.' );
	check_ext( 'bdt-backdrop-filter-' === $element->controls['element_pack_backdrop_filter']['prefix_class'] && '--ep-backdrop-filter-blur: {{SIZE}}px;' === $element->controls['element_pack_bf_blur']['selectors']['{{WRAPPER}}'], 'Backdrop Filter repite clase y variables de Element Pack.' );
	check_ext( false !== strpos( $element->controls['element_pack_ris_blur']['selectors']['{{WRAPPER}} img'], 'element_pack_ris_x.SIZE' ), 'La sombra combina los ajustes guardados en drop-shadow.' );

	$element->settings = array( 'element_pack_wrapper_link' => array( 'url' => 'https://a.test/x', 'is_external' => 'on' ) );
	ob_start();
	Extensions::before_render( $element );
	echo '<div class="elementor-element" style="width:10px"><h2>Hola</h2></div>';
	Extensions::after_render( $element );
	$html = ob_get_clean();
	check_ext( false !== strpos( $html, '<div data-digi-legacy-link class="elementor-element" style="width:10px"><a class="digi-legacy-element-link" href="https://a.test/x"' ) && false !== strpos( $html, 'target="_blank" rel="noopener noreferrer"' ) && 'enqueued' === $GLOBALS['digi']['styles'][ Extensions::STYLE ], 'Wrapper Link cubre el elemento con un enlace y carga su CSS.' );

	$element->settings = array( 'element_pack_wrapper_link' => array( 'url' => 'javascript:alert(1)' ) );
	ob_start();
	Extensions::before_render( $element );
	echo '<div>x</div>';
	Extensions::after_render( $element );
	check_ext( '<div>x</div>' === ob_get_clean(), 'Un enlace inválido no se inserta.' );

	$GLOBALS['digi']['hooks'] = array();
	define( 'BDTEP_VER', '9.9.1' );
	Extensions::init();
	check_ext( array() === $GLOBALS['digi']['hooks'], 'Con Element Pack activo no se compite por sus extensiones.' );
	echo "DIGITALÍSIMO Elements: extensiones heredadas de Element Pack validadas.\n";
}
