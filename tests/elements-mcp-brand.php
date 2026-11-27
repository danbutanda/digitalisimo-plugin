<?php
/**
 * El MCP con nombre propio (docs/MARCA_PROPIA.md, fase 1) con el adaptador MCP REAL del núcleo incluido
 * (`elementor/vendor/wordpress/mcp-adapter` y `php-mcp-schema`) y el arranque real del servidor del núcleo
 * (`elementor-mcp-composer/src/Mcp/Server_Bootstrap.php` y `Registry.php`), sin tocarlos:
 * - B1 hay dos servidores: el del núcleo (`elementor/mcp`) y el nuestro (`digitalisimo/mcp`, «DIGITALÍSIMO MCP»), con
 *   las mismas herramientas, recursos y el mismo interruptor del MCP del sitio; sin él encendido, ninguno;
 * - B2 en el nuestro, `tools/list`, `resources/list` e `initialize` no contienen «elementor» (sin distinguir mayúsculas)
 *   en nombres, títulos, descripciones ni esquemas; ninguna herramienta choca con otra (`digitalisimo-sales-*`,
 *   `digitalisimo-woo-*` siguen igual);
 * - B3 en el del núcleo todo sigue como estaba (`elementor-create-page`, `elementor://…`), con la nota del retiro en
 *   `initialize` y cada uso contado sin datos personales;
 * - B4 al llamar una herramienta por el nuestro: la entrada acepta los dos nombres (`digitalisimo_library` y
 *   `elementor_library`, `_dg_*` y `_elementor_*`, `digitalisimo://` y `elementor://`) y la ability recibe los viejos;
 *   la salida sale renombrada (`digitalisimo_library`, `action=digitalisimo`, `_dg_*`, `digitalisimo-<herramienta>`,
 *   «DIGITALÍSIMO Elements»), los errores también, y los nombres propios de verdad (`_digitalisimo_sales_kit`,
 *   `digitalisimo_query`) no cambian;
 * - B5 los recursos se leen por `digitalisimo://` y su contenido sale renombrado;
 * - B6 el índice REST no lista la ruta vieja (D-7) y, con la marca propia en vigor, ningún espacio `elementor*` (D-8);
 * - B7 el retiro: sigue hasta el 2027-01-31; ese día se retira si lleva 14 días sin uso; si no, se prorroga 30 días una
 *   vez y al terminar se retira (sus rutas salen de `rest_endpoints`);
 * - B8 la pantalla «MCP» del núcleo entrega la configuración con la dirección y el nombre propios.
 *
 * Sin WordPress: dobles mínimos de sus funciones (ganchos con prioridad, opciones, `WP_Ability`, `WP_Error`,
 * `register_rest_route`). Falla con el código anterior: no existe `modules/digitalisimo-mcp/class-brand-server.php`.
 */

namespace Elementor\MCP\Composer\Admin {
	class McpSettingsController {
		public static $enabled = true;
		public static function is_enabled(): bool {
			return self::$enabled;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $no, $str, $file, $line ) {
			if ( false !== strpos( $file, 'digitalisimo-' ) || false !== strpos( $file, 'tests/' ) ) {
				echo "FALLA aviso de PHP: $str ($file:$line)\n";
				$GLOBALS['fails']++;
			}
			return true;
		}
	);

	$GLOBALS['fails']   = 0;
	$GLOBALS['checks']  = 0;
	$GLOBALS['hooks']   = array();
	$GLOBALS['current'] = array();
	$GLOBALS['done']    = array();
	$GLOBALS['options'] = array();
	$GLOBALS['routes']  = array();
	$GLOBALS['calls']   = array();

	/** JSON legible de un resultado, con los textos que son JSON ya decodificados (el adaptador los anida). */
	function flat( $value ) {
		$walk = static function ( $v ) use ( &$walk ) {
			if ( is_string( $v ) && '' !== $v && ( '{' === $v[0] || '[' === $v[0] ) ) {
				$d = json_decode( $v, true );
				return null === $d ? $v : $walk( $d );
			}
			return is_array( $v ) ? array_map( $walk, $v ) : $v;
		};
		return json_encode( $walk( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	function check( $ok, $what, $detail = '' ) {
		$GLOBALS['checks']++;
		if ( $ok ) {
			echo "ok · $what\n";
		} else {
			$GLOBALS['fails']++;
			echo "FALLA · $what" . ( '' !== $detail ? ' · ' . substr( is_string( $detail ) ? $detail : json_encode( $detail ), 0, 900 ) : '' ) . "\n";
		}
	}

	// --- Ganchos con prioridad, que admiten añadir callbacks mientras corre la acción (como WP_Hook).
	function add_filter( $tag, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['hooks'][ $tag ][ $prio ][] = array( $cb, $args );
		return true;
	}
	function add_action( $tag, $cb, $prio = 10, $args = 1 ) {
		return add_filter( $tag, $cb, $prio, $args );
	}
	function has_filter( $tag ) {
		return ! empty( $GLOBALS['hooks'][ $tag ] );
	}
	function apply_filters( $tag, $value, ...$rest ) {
		return run_hooks( $tag, false, $value, ...$rest );
	}
	/** Corre los callbacks de un gancho en orden de prioridad; en una acción, los argumentos no encadenan. */
	function run_hooks( $tag, $action, $value, ...$rest ) {
		if ( empty( $GLOBALS['hooks'][ $tag ] ) ) {
			return $value;
		}
		$GLOBALS['current'][] = $tag;
		$ran                  = array();
		do {
			$prios = array_keys( $GLOBALS['hooks'][ $tag ] );
			sort( $prios );
			$next = null;
			foreach ( $prios as $p ) {
				foreach ( $GLOBALS['hooks'][ $tag ][ $p ] as $i => $h ) {
					if ( ! isset( $ran[ $p ][ $i ] ) ) {
						$next = array( $p, $i, $h );
						break 2;
					}
				}
			}
			if ( $next ) {
				$ran[ $next[0] ][ $next[1] ] = true;
				$result                      = call_user_func_array( $next[2][0], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $next[2][1] ) ) );
				if ( ! $action ) {
					$value = $result;
				}
			}
		} while ( $next );
		array_pop( $GLOBALS['current'] );
		return $value;
	}
	function do_action( $tag, ...$args ) {
		$GLOBALS['done'][ $tag ] = ( $GLOBALS['done'][ $tag ] ?? 0 ) + 1;
		run_hooks( $tag, true, $args[0] ?? null, ...array_slice( $args, 1 ) );
	}
	function did_action( $tag ) {
		return $GLOBALS['done'][ $tag ] ?? 0;
	}
	function doing_action( $tag ) {
		return in_array( $tag, $GLOBALS['current'], true );
	}
	function _doing_it_wrong( $fn, $msg, $v ) {
		echo "FALLA _doing_it_wrong: $fn $msg\n";
		$GLOBALS['fails']++;
	}
	function __( $t, $d = '' ) {
		return $t;
	}
	function esc_html__( $t, $d = '' ) {
		return $t;
	}
	function esc_html( $t ) {
		return $t;
	}
	function wp_json_encode( $v, $f = 0 ) {
		return json_encode( $v, $f );
	}
	function get_option( $k, $d = false ) {
		return $GLOBALS['options'][ $k ] ?? $d;
	}
	function update_option( $k, $v, $a = null ) {
		$GLOBALS['options'][ $k ] = $v;
		return true;
	}
	function is_multisite() {
		return false;
	}
	function rest_url( $p = '' ) {
		return 'http://ensayo.test/wp-json/' . ltrim( $p, '/' );
	}
	function register_rest_route( $ns, $route, $args = array() ) {
		$GLOBALS['routes'][] = '/' . trim( $ns, '/' ) . '/' . trim( $route, '/' );
		return true;
	}
	function is_user_logged_in() {
		return true;
	}
	function current_user_can( $c ) {
		return true;
	}
	function get_current_user_id() {
		return 1;
	}
	function wp_register_ability( $n, $a ) {
		return true;
	}
	function get_post_types() {
		return array( 'post' => 'post', 'page' => 'page', 'elementor_library' => 'elementor_library', 'digitalisimo_query' => 'digitalisimo_query', 'e-floating-buttons' => 'e-floating-buttons' );
	}
	function get_taxonomies() {
		return array( 'category' => 'category', 'elementor_library_type' => 'elementor_library_type', 'elementor_library_category' => 'elementor_library_category' );
	}

	class WP_Error {
		private $errors = array();
		private $data   = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->add( $code, $message, $data );
			}
		}
		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][] = $message;
			if ( '' !== $data ) {
				$this->data[ $code ] = $data;
			}
		}
		public function get_error_codes() {
			return array_keys( $this->errors );
		}
		public function get_error_code() {
			return $this->get_error_codes()[0] ?? '';
		}
		public function get_error_messages( $code = '' ) {
			return '' === $code ? array_merge( ...array_values( $this->errors ) ) : ( $this->errors[ $code ] ?? array() );
		}
		public function get_error_message( $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;
			return $this->errors[ $code ][0] ?? '';
		}
		public function get_error_data( $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;
			return $this->data[ $code ] ?? null;
		}
	}
	function is_wp_error( $v ) {
		return $v instanceof WP_Error;
	}

	/** `WP_Ability` de WordPress 6.9 en lo que usa el adaptador. */
	class WP_Ability {
		public $name;
		public $args;
		public function __construct( $name, $args ) {
			$this->name = $name;
			$this->args = $args;
		}
		public function get_name() {
			return $this->name;
		}
		public function get_label() {
			return $this->args['label'] ?? '';
		}
		public function get_description() {
			return $this->args['description'] ?? '';
		}
		public function get_category() {
			return 'elementor';
		}
		public function get_input_schema() {
			return $this->args['input_schema'] ?? array();
		}
		public function get_output_schema() {
			return $this->args['output_schema'] ?? array();
		}
		public function get_meta() {
			return $this->args['meta'] ?? array();
		}
		public function check_permissions( $input = null ) {
			return true;
		}
		public function execute( $input = null ) {
			$GLOBALS['calls'][] = array( $this->name, $input );
			return call_user_func( $this->args['execute'], $input );
		}
	}
	$GLOBALS['abilities'] = array();
	function wp_get_ability( $name ) {
		return $GLOBALS['abilities'][ $name ] ?? null;
	}
	function wp_get_abilities() {
		return array_values( $GLOBALS['abilities'] );
	}

	class WP_REST_Response {
		private $data;
		public function __construct( $data = null ) {
			$this->data = $data;
		}
		public function get_data() {
			return $this->data;
		}
		public function set_data( $d ) {
			$this->data = $d;
		}
	}
	class WP_REST_Request {
		private $route;
		public function __construct( $route ) {
			$this->route = $route;
		}
		public function get_route() {
			return $this->route;
		}
	}

	// --- Autocarga del adaptador y del esquema reales del núcleo incluido.
	$vendor = dirname( __DIR__ ) . '/digitalisimo-elements/elementor/vendor';
	spl_autoload_register(
		static function ( $class ) use ( $vendor ) {
			foreach ( array( 'WP\\McpSchema\\' => '/wordpress/php-mcp-schema/src/', 'WP\\MCP\\' => '/wordpress/mcp-adapter/includes/' ) as $prefix => $dir ) {
				if ( 0 === strpos( $class, $prefix ) ) {
					$file = $vendor . $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
					if ( is_file( $file ) ) {
						require $file;
					}
					return;
				}
			}
		}
	);
	require $vendor . '/elementor/elementor-mcp-composer/src/Mcp/Registry.php';
	require $vendor . '/elementor/elementor-mcp-composer/src/Mcp/Server_Bootstrap.php';

	$el = dirname( __DIR__ ) . '/digitalisimo-elements';
	foreach ( array( '/modules/digitalisimo-marca/class-map.php', '/modules/digitalisimo-mcp/class-brand-server.php', '/modules/digitalisimo-mcp/class-legacy-route.php', '/modules/digitalisimo-marca/class-addons.php', '/modules/digitalisimo-marca/class-toggle.php', '/modules/digitalisimo-marca/class-rest-index.php' ) as $file ) {
		if ( ! is_file( $el . $file ) ) {
			echo "FALLA no existe $file (código anterior: no hay MCP con nombre propio)\n";
			exit( 1 );
		}
		require_once $el . $file;
	}

	use Digitalisimo\Elements\Marca\Map;
	use Digitalisimo\Elements\Mcp\Brand_Server;
	use Digitalisimo\Elements\Mcp\Legacy_Route;
	use Elementor\MCP\Composer\Mcp\Registry;

	// --- Abilities de ensayo: del núcleo (`elementor/*`), un recurso y dos propias.
	$make = static function ( $name, $args ) {
		$GLOBALS['abilities'][ $name ] = new WP_Ability( $name, $args );
	};
	$make(
		'elementor/create-page',
		array(
			'label'        => 'Create Elementor Page',
			'description'  => 'Creates an Elementor page or template (post type elementor_library). Use elementor/list-posts first and read elementor://widgets/schema/{type}. Saved in _elementor_data.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'title'     => array( 'type' => 'string', 'description' => 'Title shown in Elementor.' ),
					'post_type' => array( 'type' => 'string', 'enum' => array( 'page', 'elementor_library' ) ),
					'meta'      => array( 'type' => 'object' ),
					'uri'       => array( 'type' => 'string' ),
				),
			),
			'meta'         => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ) ),
			'execute'      => static function ( $input ) {
				return array(
					'post_id'   => 7,
					'post_type' => 'elementor_library',
					'edit_url'  => 'http://ensayo.test/wp-admin/post.php?post=7&action=elementor',
					'settings'  => 'http://ensayo.test/wp-admin/admin.php?page=elementor-settings',
					'meta'      => array( '_elementor_edit_mode' => 'builder', '_digitalisimo_sales_kit' => array( 'hash' => 'x' ) ),
					'next'      => 'Now call elementor/get-page-structure or elementor-list-posts.',
					'message'   => 'Page created with Elementor.',
					'query'     => 'digitalisimo_query',
					'image'     => 'http://ensayo.test/wp-content/uploads/2026/11/mi-banner.jpg',
					'content'   => array( 'title' => 'Cómo usar Elementor en tu sitio', 'settings' => array( 'text' => 'Diseñado con Elementor Pro' ) ),
				);
			},
		)
	);
	$make(
		'elementor/list-posts',
		array(
			'label'       => 'List posts',
			'description' => 'Lists posts, pages and elementor_library templates.',
			'meta'        => array( 'annotations' => array( 'readonly' => true ) ),
			'execute'     => static function ( $input ) {
				return new WP_Error( 'elementor_v4_required', 'Elementor v4 is required for elementor_library.' );
			},
		)
	);
	$make(
		'elementor/get-page-structure',
		array(
			'label'       => 'Get page structure',
			'description' => 'Reads the structure.',
			'execute'     => static function ( $input ) {
				return array();
			},
		)
	);
	$make(
		'elementor/global-variables',
		array(
			'label'       => 'Global variables',
			'description' => 'Elementor global variables.',
			'meta'        => array( 'mcp' => array( 'type' => 'resource', 'uri' => 'elementor://global-variables', 'mimeType' => 'application/json' ) ),
			'execute'     => static function ( $input ) {
				return array( 'text' => 'Variables (see elementor://global-variables and elementor/list-posts).', 'uri' => 'elementor://global-variables', 'mimeType' => 'text/plain' );
			},
		)
	);
	$make(
		'elementor/wordpress-best-practices',
		array(
			'label'       => 'Best practices',
			'description' => 'Elementor guide.',
			'meta'        => array( 'mcp' => array( 'type' => 'resource', 'uri' => 'elementor://wordpress/best-practices', 'mimeType' => 'text/markdown' ) ),
			'execute'     => static function ( $input ) {
				return array( 'text' => 'Build pages with Elementor: call elementor/create-page.', 'uri' => 'elementor://wordpress/best-practices', 'mimeType' => 'text/markdown' );
			},
		)
	);
	$make(
		'digitalisimo-sales/create-page',
		array(
			'label'       => 'Crear página de venta',
			'description' => 'Crea un borrador del kit de ventas.',
			'execute'     => static function ( $input ) {
				return array( 'meta' => array( '_digitalisimo_sales_kit' => 1 ) );
			},
		)
	);
	$make(
		'digitalisimo-woo/products',
		array(
			'label'       => 'Productos',
			'description' => 'Productos de la tienda.',
			'execute'     => static function ( $input ) {
				return array( 'ok' => true );
			},
		)
	);

	$registry = Registry::instance();
	$registry->register_tools( array( 'elementor/create-page', 'elementor/list-posts', 'elementor/get-page-structure', 'digitalisimo-sales/create-page' ) );
	$registry->register_resources( array( 'elementor/global-variables', 'elementor/wordpress-best-practices' ) );
	add_filter(
		'elementor/mcp/server/tools',
		static function ( $tools ) {
			$tools[] = 'digitalisimo-woo/products';
			return $tools;
		}
	);
	add_filter( 'mcp_adapter_create_default_server', '__return_false_' );
	function __return_false_() {
		return false;
	}

	new \Elementor\MCP\Composer\Mcp\Server_Bootstrap();
	Brand_Server::init();
	Legacy_Route::init();
	do_action( 'rest_api_init' );

	$adapter = \WP\MCP\Core\McpAdapter::instance();
	$ours    = $adapter->get_server( Brand_Server::ID );
	$legacy  = $adapter->get_server( Legacy_Route::ID );

	// B1 -----------------------------------------------------------------------------------------------------------------
	check( null !== $legacy, 'B1 el servidor del núcleo sigue (elementor-mcp-server)' );
	check( null !== $ours, 'B1 existe el servidor con nombre propio (digitalisimo-mcp-server)' );
	if ( ! $ours || ! $legacy ) {
		echo "$GLOBALS[fails] fallos de $GLOBALS[checks]\n";
		exit( 1 );
	}
	check( in_array( '/digitalisimo/mcp', $GLOBALS['routes'], true ), 'B1 ruta /wp-json/digitalisimo/mcp registrada', $GLOBALS['routes'] );
	check( in_array( '/elementor/mcp', $GLOBALS['routes'], true ), 'B1 la ruta vieja /wp-json/elementor/mcp sigue registrada' );
	check( 'DIGITALÍSIMO MCP' === $ours->get_server_name(), 'B1 nombre «DIGITALÍSIMO MCP»' );
	check( count( $ours->get_tools() ) === count( $legacy->get_tools() ) && 5 === count( $ours->get_tools() ), 'B1 las mismas herramientas en los dos (5)', array( count( $ours->get_tools() ), count( $legacy->get_tools() ) ) );
	check( count( $ours->get_resources() ) === count( $legacy->get_resources() ) && 2 === count( $ours->get_resources() ), 'B1 los mismos recursos en los dos (2)' );
	check( array() === Brand_Server::$collisions, 'B2 ningún nombre con marca choca con otra herramienta', Brand_Server::$collisions );

	// B2 -----------------------------------------------------------------------------------------------------------------
	$tools_handler = new \WP\MCP\Handlers\Tools\ToolsHandler( $ours );
	$list          = $tools_handler->list_tools()->toArray();
	$json          = json_encode( $list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$names         = array_map(
		static function ( $t ) {
			return $t['name'];
		},
		$list['tools']
	);
	sort( $names );
	check( array( 'digitalisimo-create-page', 'digitalisimo-get-page-structure', 'digitalisimo-list-posts', 'digitalisimo-sales-create-page', 'digitalisimo-woo-products' ) === $names, 'B2 herramientas digitalisimo-* (propias sin cambio)', $names );
	check( false === stripos( $json, 'elementor' ), 'B2 tools/list sin «elementor» en nombres, títulos, descripciones ni esquemas', $json );
	check( false !== strpos( $json, 'digitalisimo_library' ) && false !== strpos( $json, 'digitalisimo-list-posts' ) && false !== strpos( $json, 'digitalisimo://widgets/schema/{type}' ) && false !== strpos( $json, '_dg_data' ) && false !== strpos( $json, 'DIGITALÍSIMO Elements' ), 'B2 la descripción y el esquema con los nombres nuevos', $json );
	$res_handler = new \WP\MCP\Handlers\Resources\ResourcesHandler( $ours );
	$rlist       = json_encode( $res_handler->list_resources()->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	check( false === stripos( $rlist, 'elementor' ) && false !== strpos( $rlist, 'digitalisimo://global-variables' ), 'B2 resources/list con digitalisimo:// y sin «elementor»', $rlist );
	$init = json_encode( ( new \WP\MCP\Handlers\Initialize\InitializeHandler( $ours ) )->handle( '2025-06-18' )->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	check( false === stripos( $init, 'elementor' ) && false !== strpos( $init, 'DIGITALÍSIMO MCP' ), 'B2 initialize sin «elementor»', $init );

	// B3 -----------------------------------------------------------------------------------------------------------------
	$old_list  = ( new \WP\MCP\Handlers\Tools\ToolsHandler( $legacy ) )->list_tools()->toArray();
	$old_names = array_map(
		static function ( $t ) {
			return $t['name'];
		},
		$old_list['tools']
	);
	check( in_array( 'elementor-create-page', $old_names, true ) && in_array( 'digitalisimo-sales-create-page', $old_names, true ), 'B3 el servidor viejo conserva elementor-create-page', $old_names );
	$old_json = json_encode( $old_list, JSON_UNESCAPED_SLASHES );
	check( false !== strpos( $old_json, 'elementor_library' ) && false === strpos( $old_json, 'digitalisimo_library' ), 'B3 el servidor viejo no cambia sus descripciones' );
	$old_res = json_encode( ( new \WP\MCP\Handlers\Resources\ResourcesHandler( $legacy ) )->list_resources()->toArray(), JSON_UNESCAPED_SLASHES );
	check( false !== strpos( $old_res, 'elementor://global-variables' ), 'B3 el servidor viejo conserva elementor://' );
	$GLOBALS['options'] = array();
	$old_init           = ( new \WP\MCP\Handlers\Initialize\InitializeHandler( $legacy ) )->handle( '2025-06-18' )->toArray();
	check( false !== strpos( (string) ( $old_init['instructions'] ?? '' ), 'digitalisimo/mcp' ) && false !== strpos( (string) $old_init['instructions'], '2027-01-31' ), 'B3 initialize del viejo avisa la ruta nueva y la fecha de retiro', $old_init['instructions'] ?? '' );
	$r = ( new \WP\MCP\Handlers\Tools\ToolsHandler( $legacy ) )->call_tool( array( 'name' => 'elementor-create-page', 'arguments' => array( 'title' => 'x' ) ) );
	$r = json_encode( $r->toArray(), JSON_UNESCAPED_SLASHES );
	check( false !== strpos( $r, 'action=elementor' ) && false !== strpos( $r, '_elementor_edit_mode' ), 'B3 la respuesta del viejo no cambia', $r );
	$state = Legacy_Route::state();
	check( 1 === ( $state['tools']['elementor-create-page'] ?? 0 ) && 1 === ( $state['tools']['initialize'] ?? 0 ) && gmdate( 'Y-m-d' ) === $state['last'], 'B3 el uso del viejo queda contado (herramienta, initialize y fecha)', $state );
	check( array( 'last', 'tools', 'extended' ) === array_keys( $GLOBALS['options'][ Legacy_Route::OPTION ] ), 'B3 la cuenta no guarda usuarios, IP ni argumentos', $GLOBALS['options'][ Legacy_Route::OPTION ] );
	( new \WP\MCP\Handlers\Tools\ToolsHandler( $ours ) )->call_tool( array( 'name' => 'digitalisimo-list-posts', 'arguments' => array() ) );
	check( ! isset( Legacy_Route::state()['tools']['digitalisimo-list-posts'] ), 'B3 una llamada por la ruta nueva no cuenta como uso del alias' );

	// B4 -----------------------------------------------------------------------------------------------------------------
	$GLOBALS['calls'] = array();
	$call             = ( new \WP\MCP\Handlers\Tools\ToolsHandler( $ours ) )->call_tool(
		array(
			'name'      => 'digitalisimo-create-page',
			'arguments' => array(
				'title'     => 'Hola',
				'post_type' => 'digitalisimo_library',
				'meta'      => array( '_dg_page_settings' => array( 'a' => 1 ), '_digitalisimo_actions' => 'x', 'digitalisimo_query' => 'q' ),
				'uri'       => 'digitalisimo://global-variables',
			),
		)
	);
	$input = $GLOBALS['calls'][0][1] ?? array();
	check( 'elementor_library' === ( $input['post_type'] ?? '' ), 'B4 la entrada digitalisimo_library llega como elementor_library', $input );
	check( isset( $input['meta']['_elementor_page_settings'] ) && 'x' === ( $input['meta']['_digitalisimo_actions'] ?? '' ) && 'q' === ( $input['meta']['digitalisimo_query'] ?? '' ), 'B4 _dg_* llega como _elementor_*; los nombres propios no cambian', $input );
	check( 'elementor://global-variables' === ( $input['uri'] ?? '' ), 'B4 digitalisimo:// llega como elementor://', $input );
	$out = $call->toArray();
	$txt = flat( $out );
	$own = array( 'Cómo usar Elementor en tu sitio', 'Diseñado con Elementor Pro' );
	$esc = array_map(
		static function ( $t ) {
			return trim( json_encode( $t ), '"' );
		},
		$own
	);
	check( false === stripos( str_replace( array_merge( $own, $esc ), '', $txt ), 'elementor' ), 'B4 la respuesta no contiene «elementor» fuera del contenido del cliente', $txt );
	check( false !== strpos( $txt, $own[0] ) && false !== strpos( $txt, $own[1] ), 'B4 el contenido del cliente que nombra Elementor sale tal cual (un agente que lo vuelve a guardar no lo cambia)', $txt );
	foreach ( array( '"post_type":"digitalisimo_library"', 'action=digitalisimo', 'page=digitalisimo-motor-settings', '_dg_edit_mode', '_digitalisimo_sales_kit', 'digitalisimo-get-page-structure', 'digitalisimo-list-posts', 'DIGITALÍSIMO Elements', 'digitalisimo_query', 'mi-banner.jpg' ) as $needle ) {
		check( false !== strpos( $txt, $needle ), "B4 la respuesta lleva «{$needle}»" );
	}
	$GLOBALS['calls'] = array();
	( new \WP\MCP\Handlers\Tools\ToolsHandler( $ours ) )->call_tool( array( 'name' => 'digitalisimo-create-page', 'arguments' => array( 'post_type' => 'elementor_library' ) ) );
	check( 'elementor_library' === ( $GLOBALS['calls'][0][1]['post_type'] ?? '' ), 'B4 el nombre viejo también vale en la entrada' );
	$err = flat( ( new \WP\MCP\Handlers\Tools\ToolsHandler( $ours ) )->call_tool( array( 'name' => 'digitalisimo-list-posts', 'arguments' => array() ) )->toArray() );
	check( false === stripos( $err, 'elementor' ) && false !== strpos( $err, 'digitalisimo_library' ), 'B4 un error sale renombrado', $err );
	$missing = ( new \WP\MCP\Handlers\Tools\ToolsHandler( $ours ) )->call_tool( array( 'name' => 'elementor-create-page', 'arguments' => array() ) );
	check( false !== strpos( json_encode( $missing->toArray() ), 'not found' ) || false !== stripos( json_encode( $missing->toArray() ), 'error' ), 'B4 en la ruta nueva no se publican los nombres viejos' );

	// B5 -----------------------------------------------------------------------------------------------------------------
	$read = ( new \WP\MCP\Handlers\Resources\ResourcesHandler( $ours ) )->read_resource( array( 'uri' => 'digitalisimo://global-variables' ) );
	$rtxt = flat( $read->toArray() );
	check( false === stripos( $rtxt, 'elementor' ) && false !== strpos( $rtxt, 'digitalisimo://global-variables' ) && false !== strpos( $rtxt, 'digitalisimo-list-posts' ), 'B5 el recurso se lee por digitalisimo:// y sale con los identificadores renombrados', $rtxt );
	$guide = flat( ( new \WP\MCP\Handlers\Resources\ResourcesHandler( $ours ) )->read_resource( array( 'uri' => 'digitalisimo://wordpress/best-practices' ) )->toArray() );
	check( false === stripos( $guide, 'elementor' ) && false !== strpos( $guide, 'DIGITALÍSIMO Elements' ) && false !== strpos( $guide, 'digitalisimo-create-page' ), 'B5 una guía fija del motor sale con la marca', $guide );

	// B6 -----------------------------------------------------------------------------------------------------------------
	$index = new WP_REST_Response(
		array(
			'namespaces' => array( 'wp/v2', 'elementor', 'elementor/v1', 'elementor-pro/v1', 'digitalisimo', 'elementor-mcp-composer/v1' ),
			'routes'     => array(
				'/'                  => array( 'namespace' => '' ),
				'/elementor/mcp'     => array( 'namespace' => 'elementor' ),
				'/elementor/v1/x'    => array( 'namespace' => 'elementor/v1' ),
				'/digitalisimo/mcp'  => array( 'namespace' => 'digitalisimo' ),
				'/wp/v2/elementor_library' => array( 'namespace' => 'wp/v2' ),
			),
		)
	);
	$after = apply_filters( 'rest_index', $index )->get_data();
	check( ! isset( $after['routes']['/elementor/mcp'] ) && ! in_array( 'elementor', $after['namespaces'], true ) && isset( $after['routes']['/digitalisimo/mcp'] ), 'B6 el índice REST no lista la ruta vieja del MCP (D-7)', $after );
	check( isset( $after['routes']['/elementor/v1/x'] ), 'B6 sin la marca propia, el resto del índice no cambia' );
	$strip = \Digitalisimo\Elements\Marca\Rest_Index::strip( $index )->get_data();
	$sj    = json_encode( $strip );
	check( false === stripos( $sj, 'elementor' ) && isset( $strip['routes']['/digitalisimo/mcp'] ) && in_array( 'wp/v2', $strip['namespaces'], true ), 'B6 con la marca propia, ningún espacio ni ruta elementor* en el índice (D-8)', $sj );

	// B7 -----------------------------------------------------------------------------------------------------------------
	$at = static function ( $date ) {
		return strtotime( $date . ' 12:00:00 UTC' );
	};
	$GLOBALS['options'] = array();
	check( ! Legacy_Route::retired( $at( '2027-01-30' ) ) && '2027-01-31' === Legacy_Route::until( $at( '2027-01-30' ) ), 'B7 antes del 2027-01-31 sigue' );
	check( Legacy_Route::retired( $at( '2027-01-31' ) ), 'B7 el 2027-01-31 sin usos se retira' );
	$GLOBALS['options'] = array( Legacy_Route::OPTION => array( 'last' => '2027-01-10', 'tools' => array(), 'extended' => '' ) );
	check( Legacy_Route::retired( $at( '2027-01-31' ) ), 'B7 con el último uso 21 días antes se retira' );
	$GLOBALS['options'] = array( Legacy_Route::OPTION => array( 'last' => '2027-01-25', 'tools' => array(), 'extended' => '' ) );
	check( ! Legacy_Route::retired( $at( '2027-01-31' ) ) && '2027-03-02' === Legacy_Route::state()['extended'], 'B7 usada en los últimos 14 días: prórroga de 30 días anotada', Legacy_Route::state() );
	Legacy_Route::touch( 'elementor-create-page', $at( '2027-02-20' ) );
	check( ! Legacy_Route::retired( $at( '2027-02-20' ) ) && Legacy_Route::retired( $at( '2027-03-02' ) ), 'B7 la prórroga es una sola: al terminar se retira aunque se siga usando' );
	$endpoints = array( '/elementor/mcp' => array(), '/elementor/v1/x' => array(), '/digitalisimo/mcp' => array() );
	$GLOBALS['options'] = array();
	check( 3 === count( Legacy_Route::retire( $endpoints ) ), 'B7 antes de la fecha, rest_endpoints no cambia' );

	// B8 -----------------------------------------------------------------------------------------------------------------
	$cred = new WP_REST_Response( array( 'success' => true, 'data' => array( 'serverUrl' => rest_url( 'elementor/mcp/' ), 'serverName' => 'mi-sitio-elementor', 'username' => 'admin' ) ) );
	$cred = Brand_Server::credentials( $cred, null, new WP_REST_Request( '/elementor-mcp-composer/v1/mcp-credentials' ) )->get_data();
	check( 'http://ensayo.test/wp-json/digitalisimo/mcp/' === $cred['data']['serverUrl'] && 'mi-sitio-digitalisimo' === $cred['data']['serverName'], 'B8 la pantalla «MCP» entrega la dirección y el nombre propios', $cred );

	// B1 (interruptor) -----------------------------------------------------------------------------------------------------
	\Elementor\MCP\Composer\Admin\McpSettingsController::$enabled = false;
	$fake = new class() {
		public $created = array();
		public function create_server( ...$a ) {
			$this->created[] = $a[0];
			return $this;
		}
		public function get_server( $id ) {
			return null;
		}
	};
	Brand_Server::register( $fake );
	check( array() === $fake->created, 'B1 con el MCP del sitio apagado no se crea el servidor con nombre propio' );

	echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
	exit( $GLOBALS['fails'] ? 1 : 0 );
}
