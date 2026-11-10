<?php
/**
 * Widget global propio (`digitalisimo-global`, C9, corrección de fidelidad: docs/pro/c9.md): «Migrar» y los kits pasan a
 * él las instancias del widget global de PRO, y conserva el vínculo con la plantilla como PRO.
 * - `Digitalisimo_Global` (modules/global-widget/widgets/digitalisimo-global.php) hereda del widget global de PRO: lee la
 *   plantilla de widget en cada pintado y pinta su elemento con el id de la instancia; dos páginas con la misma plantilla
 *   pintan lo mismo y, al editar la plantilla, las dos cambian (con la sustitución anterior se quedaban con la copia);
 * - selector `elementor-global-<plantilla>`, fuera del panel, exportación como el contenido de la plantilla, y ninguna
 *   plantilla cuyo contenido sea otra instancia global (de PRO o propia) se pinta (nunca un bucle);
 * - el módulo lo registra y, al guardar en el editor (que envía la instancia con el tipo de su plantilla y su
 *   `templateID`), las instancias que ya eran propias siguen siéndolo; las demás, como PRO (`global`).
 *
 * Uso: php-wasm-cli tests/elements-pro-global-link.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la
 * línea final.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Widget_Base {
		private $raw;
		public function __construct( $data = array(), $args = null ) { $this->raw = $data; }
		public function get_data( $key = null ) { return null === $key ? $this->raw : ( $this->raw[ $key ] ?? null ); }
		public function get_id() { return $this->raw['id'] ?? ''; }
		public function get_settings( $key = null ) { $s = $this->raw['settings'] ?? array(); return null === $key ? $s : ( $s[ $key ] ?? null ); }
		public function is_type_instance() { return ! $this->raw; }
		public function get_raw_data( $with = false ) { return $this->raw; }
	}
	class Plugin { public static $instance; }
}
namespace Elementor\Core\Base {
	class Document { const STATUS_DRAFT = 'draft'; const STATUS_AUTOSAVE = 'autosave'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Module_Base { public function get_component( $id ) { return null; } }
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function esc_html__( $t ) { return $t; }
	function is_wp_error( $x ) { return false; }
	function check_gl( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }
	$GLOBALS['gl'] = array( 'templates' => array(), 'meta' => array() );
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['gl']['meta'][ (int) $id ][ $key ] ?? ''; }

	/** El widget de la plantilla: pinta su título y el id con el que se creó. */
	class Fake_Heading extends \Elementor\Widget_Base {
		public function get_name() { return 'heading'; }
		public function render_content() { echo '[' . $this->get_id() . ':' . $this->get_settings( 'title' ) . ']'; }
	}
	\Elementor\Plugin::$instance = (object) array(
		'common'            => null,
		'templates_manager' => new class() {
			public function get_template_data( array $args ) {
				$content = $GLOBALS['gl']['templates'][ (int) $args['template_id'] ] ?? null;
				return null === $content ? array() : array( 'content' => $content );
			}
		},
		'widgets_manager'   => new class() {
			public function get_widget_types( $name = null ) {
				if ( 'heading' !== $name ) {
					return null;
				}
				return new class() {
					public function get_class_name() { return 'Fake_Heading'; }
					public function get_default_args() { return array(); }
					public function get_name() { return 'heading'; }
				};
			}
		},
		'experiments'       => new class() { public function is_feature_active( $f ) { return false; } },
		'db'                => new class() {
			public function iterate_data( $data, $callback ) {
				foreach ( $data as $i => $element ) {
					$element = $callback( $element );
					if ( ! empty( $element['elements'] ) ) {
						$element['elements'] = $this->iterate_data( $element['elements'], $callback );
					}
					$data[ $i ] = $element;
				}
				return $data;
			}
		},
	);

	$dir = __DIR__ . '/../digitalisimo-elements/modules/global-widget/';
	require $dir . 'widgets/global-widget.php';
	check_gl( is_file( $dir . 'widgets/digitalisimo-global.php' ), 'Existe el widget global propio (widgets/digitalisimo-global.php).' );
	require $dir . 'widgets/digitalisimo-global.php';
	use ElementorPro\Modules\GlobalWidget\Widgets\Digitalisimo_Global;

	$render = static function ( array $data ) {
		$widget = new Digitalisimo_Global( $data, array() );
		ob_start();
		$widget->render_content();
		return array( (string) ob_get_clean(), $widget );
	};
	$instance = static function ( string $id, int $template ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'digitalisimo-global', 'templateID' => $template, 'settings' => array( 'title' => 'copia vieja' ), 'elements' => array() );
	};

	// 1. Dos páginas con la misma plantilla pintan su contenido con el id de cada instancia.
	$GLOBALS['gl']['templates'][501] = array( array( 'id' => 'tpl', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Uno' ), 'elements' => array() ) );
	list( $a, $wa ) = $render( $instance( 'pag1aaa', 501 ) );
	list( $b )      = $render( $instance( 'pag2bbb', 501 ) );
	check_gl( '[pag1aaa:Uno]' === $a && '[pag2bbb:Uno]' === $b, 'Dos páginas pintan el contenido de la plantilla (no la copia guardada), con el id de cada instancia: ' . $a . ' ' . $b );
	check_gl( 'digitalisimo-global' === $wa->get_name() && '.elementor-global-501' === $wa->get_unique_selector() && false === $wa->show_in_panel(), 'Nombre propio, selector elementor-global-<plantilla> y fuera del panel.' );

	// 2. El vínculo: editar la plantilla cambia las dos páginas a la vez.
	$GLOBALS['gl']['templates'][501][0]['settings']['title'] = 'Dos';
	list( $a ) = $render( $instance( 'pag1aaa', 501 ) );
	list( $b ) = $render( $instance( 'pag2bbb', 501 ) );
	check_gl( '[pag1aaa:Dos]' === $a && '[pag2bbb:Dos]' === $b, 'Al editar la plantilla, las dos páginas cambian (como el widget global de PRO): ' . $a . ' ' . $b );
	check_gl( $GLOBALS['gl']['templates'][501][0] === ( new Digitalisimo_Global( $instance( 'pag1aaa', 501 ), array() ) )->on_export(), 'Se exporta como el contenido de su plantilla, como PRO.' );

	// 3. Nunca un bucle: una plantilla cuyo contenido es otra instancia global (de PRO o propia) no se pinta.
	$GLOBALS['gl']['templates'][502] = array( array( 'id' => 'x', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 502, 'settings' => array(), 'elements' => array() ) );
	$GLOBALS['gl']['templates'][503] = array( array( 'id' => 'y', 'elType' => 'widget', 'widgetType' => 'digitalisimo-global', 'templateID' => 503, 'settings' => array(), 'elements' => array() ) );
	check_gl( '' === $render( $instance( 'z1', 502 ) )[0] && '' === $render( $instance( 'z2', 503 ) )[0] && '' === $render( $instance( 'z3', 999 ) )[0], 'Sin plantilla, o con otra instancia global dentro (de PRO o propia), nada.' );

	// 4. El módulo lo registra y, al guardar en el editor, las instancias propias siguen siéndolo.
	$module = (string) file_get_contents( $dir . 'module.php' );
	check_gl( str_contains( $module, "'digitalisimo-global' => 'Digitalisimo_Global'," ), 'El módulo del widget global registra el propio.' );
	require $dir . 'module.php';
	$mod  = ( new ReflectionClass( \ElementorPro\Modules\GlobalWidget\Module::class ) )->newInstanceWithoutConstructor();
	$save = digi_test_accessible( new ReflectionMethod( $mod, 'get_document_data' ) );
	if ( PHP_VERSION_ID < 80100 ) {
		$save->setAccessible( true );
	}
	$doc = new class() {
		public function get_main_id() { return 77; }
		public function get_post() { return (object) array( 'ID' => 78 ); }
	};
	$GLOBALS['gl']['meta'][77]['_elementor_data'] = json_encode( array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array( $instance( 'pag1aaa', 501 ), array( 'id' => 'old0000', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 501 ) ) ) ) );
	// El editor envía cada instancia con el tipo de su plantilla y su templateID.
	$sent = array( 'elements' => array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array(
		array( 'id' => 'pag1aaa', 'elType' => 'widget', 'widgetType' => 'heading', 'templateID' => 501, 'settings' => array() ),
		array( 'id' => 'old0000', 'elType' => 'widget', 'widgetType' => 'heading', 'templateID' => 501, 'settings' => array() ),
		array( 'id' => 'new0000', 'elType' => 'widget', 'widgetType' => 'heading', 'templateID' => 501, 'settings' => array() ),
	) ) ) );
	$saved = $save->invoke( $mod, $sent, $doc )['elements'][0]['elements'];
	check_gl( 'digitalisimo-global' === $saved[0]['widgetType'] && 'global' === $saved[1]['widgetType'] && 'global' === $saved[2]['widgetType'] && 'heading' === $saved[0]['originalWidgetType'], 'Al guardar en el editor, la instancia propia sigue siendo propia; la de PRO y la nueva, como PRO: ' . json_encode( array_column( $saved, 'widgetType' ) ) );
	$again = $save->invoke( $mod, array( 'elements' => array( array( 'id' => 'k', 'elType' => 'widget', 'widgetType' => 'digitalisimo-global', 'templateID' => 501, 'originalWidgetType' => 'global' ) ) ), $doc )['elements'][0];
	check_gl( 'digitalisimo-global' === $again['widgetType'] && ! isset( $again['originalWidgetType'] ), 'Una instancia que llega ya como propia (un kit) lo sigue siendo y nunca toma un tipo global como original.' );

	echo "DIGITALÍSIMO Elements: widget global propio (vinculado a su plantilla, como PRO) validado.\n";
}
