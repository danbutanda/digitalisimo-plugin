<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Bitácora única de escrituras del MCP (decisión del dueño del 2026-11-27: acceso total, con rastro de quién, qué y
 * cuándo). Cubre toda ability que pueda escribir, de cualquier plugin (constructor, tienda, SEO, Hosting, Tools, IA
 * Tools), sin depender de que cada una lleve la suya:
 *
 * - `wp_register_ability_args` envuelve el `execute_callback` de cada ability que no se anota `readonly: true`; la
 *   envoltura anota la llamada al terminar (también con un `WP_Error` o una excepción). WordPress comprueba el permiso
 *   antes de llamarla: un usuario sin la capacidad nunca llega aquí (lo rechaza la ability, y la tienda y Hosting lo
 *   anotan en su propia bitácora).
 * - No se anotan las llamadas que sólo leen aunque la herramienta pueda escribir (`action` de lectura como list, get,
 *   log…, o `dry_run: true`).
 * - Cada entrada: fecha, usuario (ID y nombre de acceso), vía (Asistente del escritorio o cliente MCP), herramienta,
 *   acción, objeto (sólo IDs numéricos y nombres de recurso de la entrada) y resultado (`ok` o el código de error). Nunca
 *   valores, textos, contraseñas ni claves.
 * - Opción sin carga automática, con tope de `MAX` entradas y `DAYS` días.
 * - Se consulta con la herramienta de lectura `digitalisimo-mcp/write-log` (en el cliente, `digitalisimo-mcp-write-log`),
 *   que exige `manage_options`.
 */
final class Write_Log {
	const OPTION   = 'digitalisimo_mcp_write_log';
	const MAX      = 1000;
	const DAYS     = 90;
	const ABILITY  = 'digitalisimo-mcp/write-log';
	const CATEGORY = 'digitalisimo-mcp';

	/** Acciones que sólo leen, en las herramientas que también escriben. */
	const READ_ACTIONS = array( 'list', 'get', 'read', 'search', 'status', 'log', 'changes', 'audit_products', 'quote', 'shipments', 'get_shipment', 'label', 'guide', 'sales', 'top_sellers', 'orders_totals', 'products_totals', 'customers_totals', 'coupons_totals', 'reviews_totals' );

	/** Claves de la entrada que identifican el objeto (sólo se guardan si son números o nombres cortos). */
	const OBJECT_KEYS = array( 'resource', 'post_id', 'id', 'product_id', 'variation_id', 'order_id', 'customer_id', 'coupon_id', 'term_id', 'attribute_id', 'review_id', 'zone_id', 'instance_id', 'rate_id', 'note_id', 'refund_id', 'change_id', 'document_id', 'template_id', 'parent_id', 'element_id' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap' ), PHP_INT_MAX - 1, 2 );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_ability' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'server_tools' ) );
	}

	/** ¿La ability puede escribir? Todo lo que no se anota `readonly: true` estricto. */
	public static function may_write( $args ): bool {
		$annotations = is_array( $args ) && isset( $args['meta']['annotations'] ) && is_array( $args['meta']['annotations'] ) ? $args['meta']['annotations'] : array();
		return true !== ( $annotations['readonly'] ?? null ) && true !== ( $annotations['readOnlyHint'] ?? null );
	}

	/** `wp_register_ability_args`: envuelve la ejecución de las abilities que pueden escribir. */
	public static function wrap( $args, $name = '' ) {
		if ( ! is_array( $args ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) || self::ABILITY === $name || ! self::may_write( $args ) ) {
			return $args;
		}
		$inner                    = $args['execute_callback'];
		$args['execute_callback'] = static function ( ...$params ) use ( $inner, $name ) {
			$input = $params[0] ?? null;
			try {
				$result = call_user_func_array( $inner, $params );
			} catch ( \Throwable $e ) {
				self::record( (string) $name, $input, 'exception' );
				throw $e;
			}
			self::record( (string) $name, $input, is_wp_error( $result ) ? (string) $result->get_error_code() : 'ok' );
			return $result;
		};
		return $args;
	}

	/** ¿Esta llamada sólo lee? (acción de lectura o simulación). */
	public static function is_read_call( $input ): bool {
		if ( ! is_array( $input ) ) {
			return false;
		}
		if ( ! empty( $input['dry_run'] ) ) {
			return true;
		}
		return isset( $input['action'] ) && is_string( $input['action'] ) && in_array( $input['action'], self::READ_ACTIONS, true );
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

	/** Vía de la llamada: el Asistente de IA Tools (su contexto abierto) o un cliente MCP. */
	private static function via(): string {
		$context = 'Digitalisimo_AI_Assistant_Context';
		if ( class_exists( $context, false ) && method_exists( $context, 'current' ) && null !== call_user_func( array( $context, 'current' ) ) ) {
			return 'assistant';
		}
		return 'mcp';
	}

	/** Anota una escritura. */
	public static function record( string $ability, $input, string $result ): void {
		if ( self::is_read_call( $input ) || ! function_exists( 'get_option' ) ) {
			return;
		}
		$user  = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$entry = array(
			'time'    => time(),
			'user'    => $user && isset( $user->ID ) ? (int) $user->ID : 0,
			'login'   => $user && isset( $user->user_login ) ? (string) $user->user_login : '',
			'via'     => self::via(),
			'ability' => substr( $ability, 0, 100 ),
			'action'  => is_array( $input ) && isset( $input['action'] ) && is_string( $input['action'] ) && preg_match( '/^[a-z_-]{1,40}$/', $input['action'] ) ? $input['action'] : '',
			'object'  => self::object_of( $input ),
			'result'  => substr( '' !== $result ? $result : 'error', 0, 80 ),
		);
		$entries   = self::raw();
		$entries[] = $entry;
		$limit     = time() - self::DAYS * DAY_IN_SECONDS;
		$entries   = array_values( array_filter( $entries, static function ( $row ) use ( $limit ) { return is_array( $row ) && (int) ( $row['time'] ?? 0 ) >= $limit; } ) );
		$entries   = array_slice( $entries, -self::MAX );
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
			'description'         => 'Lista lo que cambiaron las herramientas de este sitio (constructor, tienda, SEO, Hosting, Tools e IA Tools), de lo más reciente a lo más antiguo: fecha, usuario, vía (assistant: el Asistente del escritorio; mcp: un cliente MCP), herramienta, acción, objeto (sólo IDs) y resultado (ok o el código de error). Sin valores ni datos personales. Filtros: ability (prefijo, p. ej. "digitalisimo-woo/"), via; paginada con page y per_page (≤ 200). Sólo lee.',
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
	}
}
