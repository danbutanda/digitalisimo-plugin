<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Bitácora única de escrituras del MCP (decisión del dueño del 2026-11-27: acceso total, con rastro de quién, qué y
 * cuándo). Cubre toda ability que pueda escribir, de cualquier plugin (constructor, tienda, SEO, Hosting, Tools, IA
 * Tools), sin depender de que cada una lleve la suya:
 *
 * - `wp_register_ability_args` envuelve el `execute_callback` de cada ability que no se anota `readonly: true`; la
 *   envoltura anota la llamada ANTES de ejecutarla (`started`) y completa el resultado al terminar (también con un
 *   `WP_Error`, una respuesta `status: error` o `success: false`, o una excepción): si el proceso muere a medias, la
 *   entrada queda en `started`. WordPress comprueba el permiso antes de llamarla: un usuario sin la capacidad nunca
 *   llega aquí (lo rechaza la ability, y la tienda y Hosting lo anotan en su propia bitácora).
 * - El proxy del editor (`POST /elementor/v1/mcp-proxy`, que llama a las herramientas del constructor sin el
 *   `execute_callback` registrado) se anota aparte, con `rest_request_before/after_callbacks`.
 * - No se anotan las llamadas que sólo leen aunque la herramienta pueda escribir, y sólo si la herramienta lo declara:
 *   `dry_run: true` cuenta sólo en las que anuncian la simulación (`meta.digitalisimo.dry_run`), y una `action` de
 *   lectura (list, get, log…) sólo en las que la tienen en el `enum` de su esquema. Lo demás se anota siempre.
 * - `mcp-adapter/execute-ability` (el servidor genérico) no se anota: la ability que ejecuta se anota por sí misma.
 * - Cada entrada: fecha, usuario (ID y nombre de acceso), vía (Asistente del escritorio o cliente MCP), herramienta,
 *   acción, objeto (sólo IDs numéricos y nombres de recurso de la entrada) y resultado (`ok`, `started` o el código de
 *   error). Nunca valores, textos, contraseñas ni claves. La vía es `assistant` con el contexto del Asistente abierto o
 *   en una petición de sus rutas (`digitalisimo-ai/v1`, con sesión y sin contraseña de aplicación); si no, `mcp`.
 * - Límites conocidos: es una opción (dos escrituras simultáneas pueden perder una entrada) con tope de `MAX`.
 * - Opción sin carga automática, con tope de `MAX` entradas y `DAYS` días.
 * - Se consulta con la herramienta de lectura `digitalisimo-mcp/write-log` (en el cliente, `digitalisimo-mcp-write-log`),
 *   que exige `manage_options`.
 */
final class Write_Log {
	const OPTION   = 'digitalisimo_mcp_write_log';
	const MAX      = 2000;
	const DAYS     = 90;
	const ABILITY  = 'digitalisimo-mcp/write-log';
	const CATEGORY = 'digitalisimo-mcp';

	/** Acciones que sólo leen, en las herramientas que también escriben. */
	const READ_ACTIONS = array( 'list', 'get', 'read', 'search', 'status', 'log', 'changes', 'audit_products', 'quote', 'shipments', 'get_shipment', 'label', 'guide', 'sales', 'top_sellers', 'orders_totals', 'products_totals', 'customers_totals', 'coupons_totals', 'reviews_totals' );

	/** Claves de la entrada que identifican el objeto (sólo se guardan si son números o nombres cortos). */
	const OBJECT_KEYS = array( 'resource', 'post_id', 'id', 'product_id', 'variation_id', 'order_id', 'customer_id', 'coupon_id', 'term_id', 'attribute_id', 'review_id', 'zone_id', 'instance_id', 'rate_id', 'note_id', 'refund_id', 'change_id', 'document_id', 'template_id', 'parent_id', 'element_id' );

	/** Abilities que no se anotan: el ejecutor genérico del adaptador (la ability que ejecuta se anota sola). */
	const SKIP = array( 'mcp-adapter/execute-ability' );

	/** Ruta del proxy del editor y espacio de rutas del Asistente. */
	const PROXY_ROUTE     = '/elementor/v1/mcp-proxy';
	const ASSISTANT_ROUTE = '/digitalisimo-ai/v1/';

	/** @var bool */
	private static $initialized = false;

	/** @var string[] Rutas REST en curso, anidadas (una herramienta de tienda hace peticiones internas): para la vía. */
	private static $routes = array();

	/** @var array Entradas del proxy abiertas en esta petición: id de la petición => id de la entrada. */
	private static $proxy = array();

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap' ), PHP_INT_MAX - 1, 2 );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_ability' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'server_tools' ) );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_rest' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_rest' ), 10, 3 );
	}

	/** ¿La ability puede escribir? Todo lo que no se anota `readonly: true` estricto. */
	public static function may_write( $args ): bool {
		$annotations = is_array( $args ) && isset( $args['meta']['annotations'] ) && is_array( $args['meta']['annotations'] ) ? $args['meta']['annotations'] : array();
		return true !== ( $annotations['readonly'] ?? null ) && true !== ( $annotations['readOnlyHint'] ?? null );
	}

	/** `wp_register_ability_args`: envuelve la ejecución de las abilities que pueden escribir. */
	public static function wrap( $args, $name = '' ) {
		if ( ! is_array( $args ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) || self::ABILITY === $name || in_array( $name, self::SKIP, true ) || ! self::may_write( $args ) ) {
			return $args;
		}
		$inner                    = $args['execute_callback'];
		$reads                    = self::declared_reads( $args );
		$args['execute_callback'] = static function ( ...$params ) use ( $inner, $name, $reads ) {
			$input = $params[0] ?? null;
			$id    = self::is_read_call( $input, $reads['dry_run'], $reads['actions'] ) ? '' : self::start( (string) $name, $input );
			try {
				$result = call_user_func_array( $inner, $params );
			} catch ( \Throwable $e ) {
				self::finish( $id, 'exception' );
				throw $e;
			}
			self::finish( $id, self::result_of( $result ) );
			return $result;
		};
		return $args;
	}

	/** Lo que la ability declara de lectura: si admite simulación y las acciones de su esquema. */
	public static function declared_reads( $args ): array {
		$enum = is_array( $args ) ? ( $args['input_schema']['properties']['action']['enum'] ?? array() ) : array();
		return array(
			'dry_run' => is_array( $args ) && true === ( $args['meta']['digitalisimo']['dry_run'] ?? null ),
			'actions' => is_array( $enum ) ? array_values( array_filter( $enum, 'is_string' ) ) : array(),
		);
	}

	/**
	 * ¿Esta llamada sólo lee? `dry_run` sólo si la ability declara la simulación; una acción de lectura sólo si está en
	 * el `enum` de su esquema (una ability de esquema abierto no se libra de la bitácora con `action: "get"`).
	 */
	public static function is_read_call( $input, bool $dry_run = false, array $actions = array() ): bool {
		if ( ! is_array( $input ) ) {
			return false;
		}
		if ( $dry_run && true === ( $input['dry_run'] ?? null ) ) {
			return true;
		}
		$action = $input['action'] ?? null;
		return is_string( $action ) && in_array( $action, self::READ_ACTIONS, true ) && in_array( $action, $actions, true );
	}

	/** Resultado de una ejecución: `ok` o el código del error (también `status: error` y `success: false`). */
	public static function result_of( $result ): string {
		if ( is_wp_error( $result ) ) {
			$code = (string) $result->get_error_code();
			return '' !== $code ? $code : 'error';
		}
		if ( is_array( $result ) && ( ( isset( $result['status'] ) && 'error' === $result['status'] ) || ( array_key_exists( 'success', $result ) && false === $result['success'] ) ) ) {
			return isset( $result['code'] ) && is_string( $result['code'] ) && preg_match( '/^[a-z0-9_-]{1,80}$/', $result['code'] ) ? $result['code'] : 'error';
		}
		return 'ok';
	}

	/** `rest_request_before_callbacks`: recuerda la ruta y abre la entrada de una escritura por el proxy del editor. */
	public static function before_rest( $response, $handler = null, $request = null ) {
		$route          = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		self::$routes[] = $route;
		if ( self::PROXY_ROUTE === $route && 'POST' === ( method_exists( $request, 'get_method' ) ? $request->get_method() : '' ) ) {
			$tool  = (string) $request->get_param( 'tool' );
			$name  = 'elementor/' . ( preg_match( '/^[a-z0-9-]{1,80}$/', $tool ) ? $tool : 'desconocida' );
			$input = $request->get_param( 'input' );
			$args  = array();
			if ( function_exists( 'wp_get_ability' ) ) {
				$ability = wp_get_ability( $name );
				$args    = $ability && method_exists( $ability, 'get_meta' ) ? array( 'meta' => (array) $ability->get_meta(), 'input_schema' => method_exists( $ability, 'get_input_schema' ) ? (array) $ability->get_input_schema() : array() ) : array();
			}
			$reads = self::declared_reads( $args );
			if ( ( ! $args || self::may_write( $args ) ) && ! self::is_read_call( $input, $reads['dry_run'], $reads['actions'] ) ) {
				self::$proxy[ spl_object_id( $request ) ] = self::start( $name, $input );
			}
		}
		return $response;
	}

	/** `rest_request_after_callbacks`: completa la entrada del proxy con su resultado. */
	public static function after_rest( $response, $handler = null, $request = null ) {
		$key = is_object( $request ) ? spl_object_id( $request ) : 0;
		if ( isset( self::$proxy[ $key ] ) ) {
			$result = 'ok';
			if ( is_wp_error( $response ) ) {
				$result = self::result_of( $response );
			} elseif ( is_object( $response ) && method_exists( $response, 'get_status' ) && (int) $response->get_status() >= 400 ) {
				$data   = method_exists( $response, 'get_data' ) ? $response->get_data() : null;
				$result = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) && preg_match( '/^[a-z0-9_-]{1,80}$/', $data['code'] ) ? $data['code'] : 'error';
			} elseif ( is_object( $response ) && method_exists( $response, 'get_data' ) ) {
				$result = self::result_of( $response->get_data() );
			}
			self::finish( self::$proxy[ $key ], $result );
			unset( self::$proxy[ $key ] );
		}
		array_pop( self::$routes );
		return $response;
	}

	/** El objeto de la llamada: sólo IDs numéricos y nombres cortos de la entrada (nunca valores ni textos). */
	public static function object_of( $input ): array {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( self::OBJECT_KEYS as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ];
			if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^\d{1,20}$/', $value ) ) ) {
				$out[ $key ] = (int) $value;
			} elseif ( 'resource' === $key && is_string( $value ) && preg_match( '/^[a-z_]{1,40}$/', $value ) ) {
				$out[ $key ] = $value;
			} elseif ( 'change_id' === $key && is_string( $value ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $value ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/** Vía de la llamada: el Asistente de IA Tools (su contexto abierto o una petición de sus rutas) o un cliente MCP. */
	private static function via(): string {
		$context = 'Digitalisimo_AI_Assistant_Context';
		if ( class_exists( $context, false ) && method_exists( $context, 'current' ) && null !== call_user_func( array( $context, 'current' ) ) ) {
			return 'assistant';
		}
		// Los borradores que el Asistente edita sin tarjeta corren sin contexto, pero en su ruta (con sesión, nunca con
		// una contraseña de aplicación: así la arma también el contexto).
		if ( self::$routes && 0 === strpos( (string) self::$routes[0], self::ASSISTANT_ROUTE ) && ( ! function_exists( 'rest_get_authenticated_app_password' ) || null === rest_get_authenticated_app_password() ) ) {
			return 'assistant';
		}
		return 'mcp';
	}

	/** Abre la entrada de una escritura (resultado `started`) y devuelve su id. */
	public static function start( string $ability, $input ): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}
		$user  = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$id    = substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 16 );
		$entry = array(
			'id'      => $id,
			'time'    => time(),
			'user'    => $user && isset( $user->ID ) ? (int) $user->ID : 0,
			'login'   => $user && isset( $user->user_login ) ? (string) $user->user_login : '',
			'via'     => self::via(),
			'ability' => substr( $ability, 0, 100 ),
			'action'  => is_array( $input ) && isset( $input['action'] ) && is_string( $input['action'] ) && preg_match( '/^[a-z_-]{1,40}$/', $input['action'] ) ? $input['action'] : '',
			'object'  => self::object_of( $input ),
			'result'  => 'started',
		);
		$entries   = self::raw();
		$entries[] = $entry;
		self::save( $entries );
		return $id;
	}

	/** Completa el resultado de una entrada abierta. */
	public static function finish( string $id, string $result ): void {
		if ( '' === $id ) {
			return;
		}
		$entries = self::raw();
		for ( $i = count( $entries ) - 1; $i >= 0; $i-- ) {
			if ( is_array( $entries[ $i ] ) && ( $entries[ $i ]['id'] ?? '' ) === $id ) {
				$entries[ $i ]['result'] = substr( '' !== $result ? $result : 'error', 0, 80 );
				self::save( $entries );
				return;
			}
		}
	}

	/** Anota una escritura completa de una vez (abre y cierra). */
	public static function record( string $ability, $input, string $result ): void {
		self::finish( self::start( $ability, $input ), $result );
	}

	/** Guarda con los topes de número y de días, sin carga automática. */
	private static function save( array $entries ): void {
		$limit   = time() - self::DAYS * DAY_IN_SECONDS;
		$entries = array_values( array_filter( $entries, static function ( $row ) use ( $limit ) { return is_array( $row ) && (int) ( $row['time'] ?? 0 ) >= $limit; } ) );
		$entries = array_slice( $entries, -self::MAX );
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $entries, '', false );
		} else {
			update_option( self::OPTION, $entries, false );
		}
	}

	private static function raw(): array {
		$entries = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		return is_array( $entries ) ? $entries : array();
	}

	/** Página de la bitácora, de la más reciente a la más antigua, con filtros opcionales. */
	public static function page( int $page = 1, int $per_page = 50, string $ability = '', string $via = '' ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 200, $per_page ) );
		$entries  = array_reverse( self::raw() );
		if ( '' !== $ability ) {
			$entries = array_values( array_filter( $entries, static function ( $row ) use ( $ability ) { return 0 === strpos( (string) ( $row['ability'] ?? '' ), $ability ); } ) );
		}
		if ( '' !== $via ) {
			$entries = array_values( array_filter( $entries, static function ( $row ) use ( $via ) { return ( $row['via'] ?? '' ) === $via; } ) );
		}
		$total = count( $entries );
		$items = array();
		foreach ( array_slice( $entries, ( $page - 1 ) * $per_page, $per_page ) as $row ) {
			$row['date'] = gmdate( 'c', (int) ( $row['time'] ?? 0 ) );
			$items[]     = $row;
		}
		return array(
			'entries'     => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'keeps'       => sprintf( 'Las últimas %d escrituras de los últimos %d días.', self::MAX, self::DAYS ),
		);
	}

	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'MCP de DIGITALÍSIMO',
				'description' => 'Bitácora de lo que cambian los asistentes conectados a este sitio.',
			)
		);
	}

	public static function register_ability(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::ABILITY ) ) ) {
			return;
		}
		wp_register_ability( self::ABILITY, self::definition() );
	}

	public static function definition(): array {
		return array(
			'label'               => 'Bitácora de escrituras del MCP',
			'description'         => 'Lista lo que cambiaron las herramientas de este sitio (constructor, tienda, SEO, Hosting, Tools e IA Tools), de lo más reciente a lo más antiguo: fecha, usuario, vía (assistant: el Asistente del escritorio; mcp: un cliente MCP), herramienta, acción, objeto (sólo IDs) y resultado (ok, started si no terminó, o el código de error). Sin valores ni contenidos. Filtros: ability (prefijo, p. ej. "digitalisimo-woo/"), via; paginada con page y per_page (≤ 200). Sólo lee.',
			'category'            => self::CATEGORY,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
					'ability'  => array( 'type' => 'string', 'maxLength' => 100 ),
					'via'      => array( 'type' => 'string', 'enum' => array( 'assistant', 'mcp' ) ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => array( __CLASS__, 'execute' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			),
		);
	}

	public static function permission( $input = null ): bool {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	public static function execute( $input = null ): array {
		$input = is_array( $input ) ? $input : array();
		return self::page( (int) ( $input['page'] ?? 1 ), (int) ( $input['per_page'] ?? 50 ), (string) ( $input['ability'] ?? '' ), (string) ( $input['via'] ?? '' ) );
	}

	/** `elementor/mcp/server/tools`: la herramienta en el servidor MCP del sitio. */
	public static function server_tools( $tools ): array {
		$tools   = is_array( $tools ) ? $tools : array();
		$tools[] = self::ABILITY;
		return array_values( array_unique( $tools ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$routes      = array();
		self::$proxy       = array();
	}
}
