<?php
/**
 * Plantilla (`digitalisimo-template`), C9 de docs/PRO_WIDGETS_OBJECTIVE.md (contrato docs/pro/c9.md): el widget propio que
 * sustituye al «Template» de PRO (`modules/library/widgets/template.php`) y su mapa `maps/template.php`.
 *
 * - registro: nombre, título, categoría, módulo, sin hoja ni script;
 * - controles: se ejecutan los `register_controls()` de PRO y del propio con los mismos dobles y se comparan (sección,
 *   nombre, tipo, consulta);
 * - pintado: sólo una plantilla publicada, con el contenido del núcleo; sin bucles (la misma plantilla dentro de sí,
 *   directa o a través de otra Plantilla, o la que se está editando), con aviso sólo en el editor;
 * - importación: `template_id` pasa al ID nuevo (como el control de consulta de PRO);
 * - sobrescrituras de F7b: `F7b\Overrides` y los usos reconocen la Plantilla propia; referencias de kit de sus filas;
 * - conversión con `Converter::tree()` de ajustes reales (con sobrescrituras, `__dynamic__`, CSS personalizado): ningún
 *   `pro.unmapped`; guarda de `scripts/build-packages.sh`.
 *
 * Uso: php-wasm-cli tests/elements-pro-template.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la línea
 * final y por las líneas «FALLA».
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $sections = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public static function on_import_update_dynamic_content( array $config, array $data, $controls = null ): array { $config['parent'] = true; return $config; }
	}
	class Controls_Manager { const NUMBER = 'number'; const TEXT = 'text'; }
	class Plugin {
		public static $instance;
		public $editor;
		public $frontend;
		public $documents;
		public $experiments;
	}
}
namespace Elementor\Core\Base {
	class Document { const TYPE_META_KEY = '_elementor_template_type'; }
}
namespace Elementor\Modules\MarkdownRender {
	class Markdown_Renderer { public function render_elements_data( $data ) { return 'md:' . count( $data ); } }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_LIBRARY_TEMPLATE = 'library_template'; }
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['tp'] = array( 'edit' => false, 'status' => array(), 'current' => 0, 'rendered' => array() );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_post_status( $id ) { return $GLOBALS['tp']['status'][ (int) $id ] ?? false; }
	function add_action() {}
	function add_filter() {}
	function check_tp( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return $GLOBALS['tp']['edit']; } };
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return true; } };
	\Elementor\Plugin::$instance->documents   = new class() {
		public function get_document_types( $args = array() ) { return array( 'section' => 1, 'container' => 1, 'page' => 1 ); }
		public function get_current() { $id = $GLOBALS['tp']['current']; return $id ? new class( $id ) { private $id; public function __construct( $id ) { $this->id = $id; } public function get_main_id() { return $this->id; } } : null; }
		public function get( $id ) { return new class() { public function get_elements_data() { return array( 1, 2 ); } }; }
	};
	// El núcleo pinta la plantilla; la plantilla 30 contiene otra Plantilla que apunta a 31, y la 31, a la 30 (bucle).
	\Elementor\Plugin::$instance->frontend = new class() {
		public function get_builder_content_for_display( $id ) {
			$GLOBALS['tp']['rendered'][] = (int) $id;
			$inner = array( 30 => 31, 31 => 30, 32 => 32 );
			if ( isset( $inner[ $id ] ) && count( $GLOBALS['tp']['rendered'] ) < 10 ) {
				$w = new \Digitalisimo\Elements\Services\Widgets\Template();
				$w->settings = array( 'template_id' => (string) $inner[ $id ] );
				ob_start();
				( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
				return '<p>Plantilla ' . $id . '</p>' . ob_get_clean();
			}
			return '<p>Plantilla ' . $id . '</p>';
		}
	};

	require $root . 'modules/library/widgets/template.php';
	require $root . 'modules/digitalisimo-services/class-module.php';
	\Digitalisimo\Elements\Services\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Services\Module as Services;
	use Digitalisimo\Elements\Services\Widgets\Template;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;

	$render = static function ( array $settings ) {
		$w           = new Template();
		$w->settings = $settings;
		ob_start();
		( new ReflectionMethod( $w, 'render' ) )->invoke( $w );
		return (string) ob_get_clean();
	};
	$checks = 0;
	$check  = static function ( $ok, $message ) use ( &$checks ) {
		++$checks;
		check_tp( $ok, $message );
	};

	// 1. Registro.
	$check( Template::class === Services::load( 'digitalisimo-template' ), 'El módulo carga la Plantilla.' );
	$w = new Template();
	$check( 'digitalisimo-template' === $w->get_name() && 'Plantilla' === $w->get_title() && array( 'digitalisimo' ) === $w->get_categories() && 'Plantilla' === Services::TITLES['digitalisimo-template'], 'Nombre, título y categoría propios.' );
	$check( array() === $w->get_style_depends() && array() === $w->get_script_depends() && ! is_file( $root . Services::WIDGETS['digitalisimo-template']['css'] ) && ! is_file( $root . Services::WIDGETS['digitalisimo-template']['js'] ), 'Sin hoja ni script.' );
	$check( false === $w->is_reload_preview_required(), 'Como PRO, el editor no recarga la vista previa.' );

	// 2. Controles frente a PRO.
	$pro = new \ElementorPro\Modules\Library\Widgets\Template();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $w, 'register_controls' ) )->invoke( $w );
	$check( array_keys( $pro->sections ) === array_keys( $w->sections ) && array( 'section_template' ) === array_keys( $w->sections ), 'Misma sección (`section_template`, donde F7b añade las sobrescrituras).' );
	$check( array_keys( $pro->controls ) === array_keys( $w->controls ), 'Mismos controles: ' . implode( ', ', array_keys( $w->controls ) ) );
	foreach ( $pro->controls as $name => $args ) {
		$mine = $w->controls[ $name ];
		$check( $args['type'] === $mine['type'] && $args['label_block'] === $mine['label_block'] && $args['autocomplete'] === $mine['autocomplete'], "$name: mismo tipo, consulta y disposición." );
	}

	// 3. Pintado.
	$GLOBALS['tp']['status'] = array( 10 => 'publish', 11 => 'draft', 30 => 'publish', 31 => 'publish', 32 => 'publish' );
	$check( '' === $render( array() ) && '' === $render( array( 'template_id' => '' ) ) && '' === $render( array( 'template_id' => '11' ) ) && '' === $render( array( 'template_id' => '99' ) ), 'Sin plantilla, en borrador o inexistente: nada.' );
	$check( '<div class="digi-template"><p>Plantilla 10</p></div>' === $render( array( 'template_id' => '10' ) ), 'Una plantilla publicada se pinta con el contenido del núcleo: ' . $render( array( 'template_id' => '10' ) ) );
	$GLOBALS['tp']['rendered'] = array();
	$html = $render( array( 'template_id' => '30' ) );
	$check( array( 30, 31 ) === $GLOBALS['tp']['rendered'] && 2 === substr_count( $html, 'digi-template"' ) && false === strpos( $html, 'se incluye a sí misma' ), 'Bucle entre dos plantillas: cada una se pinta una vez y el ciclo se corta sin aviso fuera del editor: ' . json_encode( $GLOBALS['tp']['rendered'] ) );
	$GLOBALS['tp']['rendered'] = array();
	$GLOBALS['tp']['edit']     = true;
	$html = $render( array( 'template_id' => '32' ) );
	$check( array( 32 ) === $GLOBALS['tp']['rendered'] && false !== strpos( $html, 'se incluye a sí misma' ), 'Una plantilla que se incluye a sí misma no se repite, y el editor lo dice.' );
	$GLOBALS['tp']['rendered'] = array();
	$GLOBALS['tp']['current']  = 10;
	$check( false !== strpos( $render( array( 'template_id' => '10' ) ), 'se incluye a sí misma' ) && array() === $GLOBALS['tp']['rendered'], 'Al editar la plantilla 10, una Plantilla que apunta a la 10 no se pinta.' );
	$GLOBALS['tp']['edit']    = false;
	$GLOBALS['tp']['current'] = 0;
	$check( '' === $render( array( 'template_id' => '32' ) ) || false === strpos( $render( array( 'template_id' => '32' ) ), 'sí misma' ), 'Fuera del editor, sin aviso.' );
	$w->settings = array( 'template_id' => '10' );
	$check( 'md:2' === $w->render_markdown(), 'Markdown con el renderizador del núcleo.' );

	// 4. Importación.
	$conf = Template::on_import_update_dynamic_content( array( 'settings' => array( 'template_id' => '605' ) ), array( 'post_ids' => array( 605 => 900 ) ) );
	$check( 900 === $conf['settings']['template_id'] && true === $conf['parent'], 'Importación: template_id pasa al ID nuevo (y el núcleo traduce lo dinámico).' );
	$same = Template::on_import_update_dynamic_content( array( 'settings' => array( 'template_id' => '7' ) ), array( 'post_ids' => array( 605 => 900 ) ) );
	$check( '7' === $same['settings']['template_id'], 'Un ID sin traducción queda igual (como PRO).' );

	// 5. Sobrescrituras de F7b.
	$f7b = $root . 'modules/digitalisimo-f7b/';
	require $f7b . 'overrides/class-render.php';
	$check( in_array( 'digitalisimo-template', \Digitalisimo\Elements\F7b\Overrides\Render::WIDGETS, true ) && in_array( 'template', \Digitalisimo\Elements\F7b\Overrides\Render::WIDGETS, true ), 'Render de F7b reconoce las dos Plantillas.' );
	$is = new ReflectionMethod( \Digitalisimo\Elements\F7b\Overrides\Render::class, 'is_template' );
	if ( PHP_VERSION_ID < 80100 ) {
		$is->setAccessible( true );
	}
	$check( $is->invoke( null, new Template() ) && $is->invoke( null, $pro ), 'is_template(): la propia y la de PRO.' );
	$check( false !== strpos( (string) file_get_contents( $f7b . 'overrides/class-controls.php' ), "'elementor/element/digitalisimo-template/section_template/before_section_end'" ), 'Controles de F7b: el repetidor de sobrescrituras también en la Plantilla propia.' );
	$check( false !== strpos( (string) file_get_contents( $f7b . 'class-usages.php' ), "array( 'template', 'digitalisimo-template' )" ) && false !== strpos( (string) file_get_contents( $f7b . 'overrides/class-apply.php' ), "array( 'template', 'digitalisimo-template' )" ), 'Usos y recuento de instancias de F7b.' );
	$check( false !== strpos( (string) file_get_contents( $f7b . 'assets/js/overrides-editor.js' ), "'digitalisimo-template': 1" ), 'Script del editor de F7b.' );
	$kit = (string) file_get_contents( $f7b . 'kit/class-module.php' );
	foreach ( array( 'image', 'link.url', 'html' ) as $path ) {
		$check( false !== strpos( $kit, "array( 'element', 'digitalisimo-template', 'digitalisimo_overrides.*.{$path}'" ), "Referencia de kit digitalisimo_overrides.*.{$path} en la Plantilla propia." );
	}

	// 6. Conversión.
	$map = Registry::map( 'template', true );
	$check( is_array( $map ) && 'digitalisimo-template' === $map['target'] && 'hide' === $map['panel'], 'El mapa lleva al widget propio y oculta el de PRO.' );
	$settings = array(
		'template_id'            => '42',
		'digitalisimo_overrides' => array( array( '_id' => 'r1', 'slot' => 'titulo', 'kind' => 'text', 'text' => 'Hola' ) ),
		'__dynamic__'            => array( 'template_id' => '[elementor-tag id="x" name="post-id" settings="%7B%7D"]' ),
		'custom_css'             => 'selector .elementor-template { margin: 0; }',
		'_margin'                => array( 'top' => '4', 'unit' => 'px' ),
		'_css_classes'           => 'mia',
	);
	$log  = array();
	$tree = Converter::tree( array( array( 'id' => 'k', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 't1', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => $settings, 'elements' => array() ) ) ) ), $log, true );
	$out  = $tree[0]['elements'][0];
	$codes = array_column( $log[0]['codes'] ?? array(), 'code' );
	$check( 'digitalisimo-template' === $out['widgetType'] && 't1' === $out['id'] && '42' === $out['settings']['template_id'] && $settings['digitalisimo_overrides'] === $out['settings']['digitalisimo_overrides'], 'Mismo id, misma plantilla y sus sobrescrituras.' );
	$check( isset( $out['settings']['__dynamic__']['template_id'] ) && array( 'top' => '4', 'unit' => 'px' ) === $out['settings']['_margin'] && 'mia' === $out['settings']['_css_classes'], 'Etiqueta dinámica y pestaña Avanzado.' );
	$check( 'selector .digi-template { margin: 0; }' === $out['settings']['custom_css'] && ! in_array( 'pro.unmapped', $codes, true ) && in_array( 'pro.css', $codes, true ), 'CSS personalizado con la clase propia y ninguna clave sin declarar: ' . json_encode( $codes ) );
	$log2 = array();
	Converter::tree( array( array( 'id' => 't0', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => array(), 'elements' => array() ) ), $log2, true );
	$check( array() === $log2[0]['codes'], 'Por defecto, sin códigos.' );

	// 7. Guarda de build-packages.sh.
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-services/widgets/class-template.php', 'modules/digitalisimo-convert/maps/template.php', 'modules/digitalisimo-services/class-module.php' ) as $file ) {
		$check( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file no nombra lo prohibido." );
	}

	echo "elements-pro-template: OK ($checks comprobaciones: Plantilla propia, sobrescrituras de F7b y mapa).\n";
}
