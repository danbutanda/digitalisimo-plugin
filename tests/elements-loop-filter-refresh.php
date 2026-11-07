<?php
/**
 * Refresco sin recargar de un Filtro de taxonomías de PRO que apunta a la Cuadrícula de bucle propia (C5,
 * docs/pro/c5.md, «Correcciones de fidelidad»): el punto REST `elementor-pro/v1/refresh-loop`
 * (`modules/loop-filter/data/endpoints/refresh-loop.php`) admite, fuera del editor, una `loop-grid` de PRO y una
 * `digitalisimo-loop-grid` publicadas (que consulta con el mismo grupo de PRO y pinta su `.elementor-widget-container`),
 * y sigue rechazando cualquier otro widget, una entrada sin publicar o un id que no está en el documento. En el editor,
 * como en PRO, decide `is_editable_by_current_user()`. Con el código anterior la Cuadrícula propia recibía un rechazo y el
 * Filtro de PRO no la refrescaba (pasa con un kit de PRO importado con el Filtro propio desactivado en el Gestor de
 * elementos).
 *
 * Uso: php-wasm-cli tests/elements-loop-filter-refresh.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la
 * línea final.
 */
namespace ElementorPro\Core\Data {
	class Controller { public function get_namespace() { return 'elementor-pro/v1'; } }
}
namespace ElementorPro\Core\Data\Interfaces {
	interface Endpoint {}
}
namespace ElementorPro\Core {
	class Utils { public static function create_widget_instance_from_db( $post_id, $widget_id ) { return null; } }
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return $GLOBALS['lf']['elementor']; }
		public static function instance() { return null; }
	}
}
namespace ElementorPro\Modules\LoopFilter {
	class Module {}
}
namespace Elementor {
	class Widget_Base {}
	class Utils {
		public static function find_element_recursive( $elements, $id ) {
			foreach ( $elements as $element ) {
				if ( $id === $element['id'] ) {
					return $element;
				}
				$found = self::find_element_recursive( $element['elements'] ?? array(), $id );
				if ( $found ) {
					return $found;
				}
			}
			return false;
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	class WP_REST_Server { const CREATABLE = 'POST'; }
	class WP_REST_Request {
		private $params;
		public function __construct( array $params ) { $this->params = $params; }
		public function get_params() { return $this->params; }
	}
	$GLOBALS['lf'] = array( 'routes' => array(), 'editable' => false, 'status' => 'publish' );
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['lf']['routes'][ $ns . '/' . $route ] = $args; }
	function get_post( $id ) { return (object) array( 'ID' => $id, 'post_status' => $GLOBALS['lf']['status'] ); }
	function check_lf( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	// Un documento con un Filtro de taxonomías de PRO, una Cuadrícula de PRO, una propia y unas Entradas.
	$GLOBALS['lf']['elementor'] = (object) array(
		'documents' => new class() {
			public function get( $id ) {
				return new class() {
					public function is_editable_by_current_user() { return $GLOBALS['lf']['editable']; }
					public function get_elements_data() {
						$w = static function ( $id, $type ) { return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => array(), 'elements' => array() ); };
						return array( array( 'id' => 'c000001', 'elType' => 'container', 'elements' => array( $w( 'f000001', 'taxonomy-filter' ), $w( 'g000001', 'loop-grid' ), $w( 'g000002', 'digitalisimo-loop-grid' ), $w( 'p000001', 'posts' ) ) ) );
					}
				};
			}
		},
	);

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'core/data/endpoints/base.php';
	require $root . 'core/data/endpoints/refresh-base.php';
	require $root . 'modules/loop-filter/data/endpoints/refresh-loop.php';
	new \ElementorPro\Modules\LoopFilter\Data\Endpoints\Refresh_Loop( new \ElementorPro\Core\Data\Controller() );
	$route = $GLOBALS['lf']['routes']['elementor-pro/v1/refresh-loop'][0] ?? null;
	check_lf( is_array( $route ) && is_callable( $route['permission_callback'] ), 'El punto REST refresh-loop se registra con su permiso.' );
	$allowed = static function ( $widget_id ) use ( $route ) {
		$endpoint_fresh = $route['permission_callback'];
		return $endpoint_fresh( new WP_REST_Request( array( 'post_id' => 77, 'widget_id' => $widget_id, 'widget_filters' => array() ) ) );
	};

	check_lf( true === $allowed( 'g000001' ), 'La Cuadrícula de bucle de PRO, publicada: se refresca (como siempre).' );
	check_lf( true === $allowed( 'g000002' ), 'La Cuadrícula de bucle propia, publicada: también se refresca.' );
	check_lf( false === $allowed( 'p000001' ) && false === $allowed( 'f000001' ) && false === $allowed( 'x000009' ), 'Cualquier otro widget o un id que no está en el documento: rechazo.' );
	$GLOBALS['lf']['status'] = 'draft';
	check_lf( false === $allowed( 'g000002' ) && false === $allowed( 'g000001' ), 'En una entrada sin publicar, fuera del editor: rechazo.' );

	echo "DIGITALÍSIMO Elements: refresh-loop admite la Cuadrícula de bucle propia (y sólo las cuadrículas publicadas) validado.\n";
}
