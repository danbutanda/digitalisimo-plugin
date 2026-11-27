<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * El editor con la marca propia y el paquete transformado (D-12 de docs/MARCA_PROPIA.md).
 *
 * El JS del paquete transformado (scripts/marca-empaquetar.mjs) ya habla con los nombres nuevos: pide
 * `admin-ajax.php?action=digitalisimo_ajax`, `/wp-json/digitalisimo/v1/…`, manda `digitalisimo_library`,
 * `digitalisimo_canvas` o `digitalisimo_post_lock`, y lee `DigitalisimoConfig`. El servidor sigue hablando por dentro
 * con los nombres del motor. Esta capa traduce en el borde, con las MISMAS reglas que el JS (Transform::str, con su
 * prueba de paridad tests/elements-marca-paridad.mjs):
 *
 * - **Lo que entra** (sólo con el paquete transformado y la marca en vigor, `Package::mode() === 'on'`):
 *   - la acción AJAX `digitalisimo_<x>` → `elementor_<x>` si el motor la registró y no existe una propia con ese nombre;
 *   - las rutas REST `digitalisimo…/…` → el espacio del motor que la produce (`Transform::str( $espacio )`), y las de
 *     tipos `wp/v2/digitalisimo_<x>`;
 *   - claves y valores de tipo identificador con su forma exacta en el vocabulario (`marca/vocabulario.json`, derivado
 *     del JS por scripts/marca-vocabulario.mjs) o en los tipos y plantillas registrados → su original. Nunca el texto
 *     libre ni el contenido de los elementos (`elements`): el contenido del cliente no cambia al guardarse;
 *   - el parámetro de la vista previa `dg-preview` → `elementor-preview` (también en el front: es la vista previa).
 * - **Lo que sale**: las respuestas REST de los espacios del motor y las de AJAX pasan por `Transform::json_out()` (el
 *   HTML por la capa, los identificadores y las URL por las reglas, el contenido de los elementos igual). La página del
 *   escritorio y del editor pasa por `Front_Layer::admin_buffer()`.
 *
 * El MCP viejo (`/elementor/mcp`) no se toca: su respuesta conserva los nombres viejos hasta su retiro (D-7).
 */
final class Editor_Layer {
	/** @var array<string,string>|null forma transformada → original */
	private static $vocab = null;

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		// La vista previa del editor es una petición del front: se traduce al cargar Elements (lo llama Package::boot()).
		add_action( 'admin_init', array( __CLASS__, 'ajax_request' ), PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_request' ), 4, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest_response' ), 60, 3 );
		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat' ), PHP_INT_MIN, 2 );
		add_filter( 'rest_index', array( __CLASS__, 'rest_index' ), 30 );
	}

	/** Todas las cadenas y claves por las reglas (esquemas de la API: no hay contenido del cliente). */
	public static function schema_out( $value, int $depth = 0 ) {
		if ( is_string( $value ) ) {
			return Transform::str( $value );
		}
		if ( ! is_array( $value ) || $depth > 32 ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ is_string( $k ) ? Transform::str( $k ) : $k ] = self::schema_out( $v, $depth + 1 );
		}
		return $out;
	}

	/** El índice `/wp-json/`: los esquemas de las rutas que quedan (metas registradas del motor) con los nombres nuevos. */
	public static function rest_index( $response ) {
		if ( ! self::on() || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['routes'] ) ) {
			$data['routes'] = self::schema_out( $data['routes'] );
			$response->set_data( $data );
		}
		return $response;
	}

	/** Una meta `_digitalisimo_<x>` (o `_dg_<x>`) que entra → `_elementor_<x>` si el motor la registró para la API. */
	public static function meta_key_in( string $key ): string {
		foreach ( array( '_digitalisimo_', Map::META_OUT ) as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				$original = '_elementor_' . substr( $key, strlen( $prefix ) );
				return self::meta_registered( $original ) ? $original : $key;
			}
		}
		return $key;
	}

	private static function meta_registered( string $key ): bool {
		if ( ! function_exists( 'get_registered_meta_keys' ) ) {
			return false;
		}
		$types = function_exists( 'get_post_types' ) ? array_merge( array( '' ), array_keys( (array) get_post_types( array( 'show_in_rest' => true ) ) ) ) : array( '' );
		foreach ( $types as $type ) {
			if ( isset( get_registered_meta_keys( 'post', (string) $type )[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Una entrada o salida de `wp/v2` con `meta` y `template` (las metas del motor y sus plantillas de página). */
	private static function post_out( $item ) {
		if ( ! is_array( $item ) ) {
			return $item;
		}
		if ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) {
			$meta = array();
			foreach ( $item['meta'] as $k => $v ) {
				$meta[ is_string( $k ) ? Transform::str( $k ) : $k ] = $v;
			}
			$item['meta'] = $meta;
		}
		if ( isset( $item['template'] ) ) {
			$item['template'] = Exports::template_out( $item['template'] );
		}
		return $item;
	}

	/** ¿Habla el JS con los nombres nuevos en esta petición? */
	public static function on(): bool {
		return class_exists( Package::class ) && 'on' === Package::mode();
	}

	/** @return array<string,string> forma transformada → original (vocabulario del JS, tipos y plantillas registrados). */
	public static function vocab(): array {
		if ( null !== self::$vocab ) {
			return self::$vocab;
		}
		$dir  = dirname( __DIR__, 2 ) . '/marca/';
		$data = is_readable( $dir . 'vocabulario.php' ) ? include $dir . 'vocabulario.php' : json_decode( (string) @file_get_contents( $dir . 'vocabulario.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local del plugin.
		$map  = is_array( $data ) && isset( $data['vocabulario'] ) && is_array( $data['vocabulario'] ) ? $data['vocabulario'] : array();
		$extra = array( 'elementor_canvas', 'elementor_header_footer', 'elementor_theme', 'elementor_library' );
		if ( function_exists( 'get_post_types' ) ) {
			$extra = array_merge( $extra, array_filter( array_keys( (array) get_post_types() ), 'is_string' ), array_filter( array_keys( (array) get_taxonomies() ), 'is_string' ) );
		}
		foreach ( $extra as $name ) {
			if ( false !== stripos( $name, 'elementor' ) ) {
				$map[ Transform::str( $name ) ] = $name;
			}
		}
		// Sólo se memoriza cuando ya están los tipos (después de `init`).
		if ( function_exists( 'did_action' ) && did_action( 'init' ) ) {
			self::$vocab = $map;
		}
		return $map;
	}

	/** Para pruebas. */
	public static function set_vocab( ?array $vocab ): void {
		self::$vocab = $vocab;
	}

	/** Una cadena que entra: su original si es una forma exacta del vocabulario; el resto, igual. */
	public static function str_in( string $s ): string {
		if ( false === strpos( $s, 'digitalisimo' ) && false === strpos( $s, Transform::P ) && false === strpos( $s, 'Digitalisimo' ) ) {
			return $s;
		}
		$vocab = self::vocab();
		return isset( $vocab[ $s ] ) ? (string) $vocab[ $s ] : $s;
	}

	/** Datos que entran (de la petición o de su JSON): claves y valores exactos del vocabulario; `elements`, igual. */
	public static function json_in( $value, int $depth = 0 ) {
		if ( is_string( $value ) ) {
			return self::str_in( $value );
		}
		if ( ! is_array( $value ) || $depth > 64 ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$nk         = is_string( $k ) ? self::str_in( $k ) : $k;
			$out[ $nk ] = 'elements' === $nk ? $v : self::json_in( $v, $depth + 1 );
		}
		return $out;
	}

	/**
	 * admin-ajax.php, después de `admin_init` (todas las acciones ya registradas) y antes de despachar: la acción y sus
	 * datos con los nombres del motor. `actions` de `elementor_ajax` es JSON: se traduce por dentro.
	 */
	public static function ajax_request(): void {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() || ! self::on() ) {
			return;
		}
		foreach ( array( '_GET', '_POST', '_REQUEST' ) as $global ) {
			if ( empty( $GLOBALS[ $global ] ) || ! is_array( $GLOBALS[ $global ] ) ) {
				continue;
			}
			$GLOBALS[ $global ] = self::ajax_args( wp_unslash( $GLOBALS[ $global ] ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- traducción de los nombres nuevos antes de despachar.
			$GLOBALS[ $global ] = wp_slash( $GLOBALS[ $global ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * @param array<string,mixed> $args sin barras
	 * @return array<string,mixed>
	 */
	public static function ajax_args( array $args ): array {
		$action = isset( $args['action'] ) && is_string( $args['action'] ) ? $args['action'] : '';
		if ( 0 === strpos( $action, 'digitalisimo_' ) && ! has_action( 'wp_ajax_' . $action ) && ! has_action( 'wp_ajax_nopriv_' . $action ) ) {
			$original = 'elementor_' . substr( $action, 13 );
			if ( has_action( 'wp_ajax_' . $original ) || has_action( 'wp_ajax_nopriv_' . $original ) ) {
				$args['action'] = $original;
			}
		}
		$out = array();
		foreach ( $args as $k => $v ) {
			if ( 'action' === $k ) {
				$out[ $k ] = $v;
				continue;
			}
			$nk = is_string( $k ) ? self::str_in( $k ) : $k;
			if ( is_string( $v ) && '' !== $v && ( '{' === $v[0] || '[' === $v[0] ) && false !== stripos( $v, 'digitalisimo' ) . '' ) {
				$data = json_decode( $v, true );
				if ( is_array( $data ) ) {
					$json      = wp_json_encode( self::json_in( $data ) );
					$out[ $nk ] = is_string( $json ) ? $json : $v;
					continue;
				}
			}
			$out[ $nk ] = self::json_in( $v );
		}
		return $out;
	}

	/** `heartbeat_received`: las claves del editor (`digitalisimo_post_lock` → `elementor_post_lock`). */
	public static function heartbeat( $response, $data = array() ) {
		if ( self::on() && is_array( $data ) && isset( $_POST['data'] ) && is_array( $_POST['data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- heartbeat verifica su nonce.
			$_POST['data'] = self::json_in( $_POST['data'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		}
		return $response;
	}

	/** Espacios REST del motor y su forma transformada (`elementor/v1` → `digitalisimo/v1`). @return array<string,string> */
	/**
	 * Formas en que el JS transformado puede pedir un espacio: entero (`'elementor/v1'` → `digitalisimo/v1`) o armado
	 * por partes (`'elementor'` + `'/v1'` → `dg/v1`).
	 *
	 * @return array<string,string> forma pedida => espacio del motor
	 */
	public static function namespace_forms( array $namespaces ): array {
		$forms = array();
		foreach ( $namespaces as $ns => $whole ) {
			$forms[ $whole ] = $ns;
			$segs            = explode( '/', $ns );
			$first           = Transform::str( array_shift( $segs ) );
			$forms[ implode( '/', array_merge( array( $first ), $segs ) ) ] = $ns;
		}
		unset( $forms[''] );
		return $forms;
	}

	public static function namespaces( $server ): array {
		$out = array();
		if ( is_object( $server ) && method_exists( $server, 'get_namespaces' ) ) {
			foreach ( (array) $server->get_namespaces() as $ns ) {
				if ( is_string( $ns ) && false !== stripos( $ns, 'elementor' ) ) {
					$out[ $ns ] = Transform::str( $ns );
				}
			}
		}
		return $out;
	}

	/** La ruta transformada → la del motor (o la misma si no es una forma transformada). */
	public static function route_in( string $route, array $namespaces ): string {
		$forms = self::namespace_forms( $namespaces );
		// Las más largas primero: `digitalisimo-pro/v1` antes que `digitalisimo`.
		uksort(
			$forms,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		foreach ( $forms as $form => $ns ) {
			if ( (string) $form === $ns ) {
				continue;
			}
			$prefix = '/' . $form;
			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return '/' . $ns . substr( $route, strlen( $prefix ) );
			}
		}
		// `wp/v2/digitalisimo_library…` y los demás tipos y taxonomías.
		return (string) preg_replace_callback(
			'#(?<=/)(digitalisimo_[a-z0-9_]+)(?=/|$)#',
			static function ( $m ) {
				return self::str_in( $m[1] );
			},
			$route
		);
	}

	/**
	 * `rest_pre_dispatch`: con el paquete transformado y la marca en vigor, la ruta y los parámetros con los nombres del
	 * motor. Una ruta que ya existe con su nombre (las nuestras, `digitalisimo/mcp`) no se toca.
	 */
	public static function rest_request( $result, $server = null, $request = null ) {
		if ( null !== $result || ! self::on() || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}
		$route = (string) $request->get_route();
		// wp/v2: las metas del motor y la plantilla de página con los nombres nuevos (los dos valen).
		if ( 0 === strpos( $route, '/wp/v2/' ) && method_exists( $request, 'get_param' ) ) {
			$meta = $request->get_param( 'meta' );
			if ( is_array( $meta ) ) {
				$in = array();
				foreach ( $meta as $k => $v ) {
					$in[ is_string( $k ) ? self::meta_key_in( $k ) : $k ] = $v;
				}
				$request->set_param( 'meta', $in );
			}
			if ( is_string( $request->get_param( 'template' ) ) ) {
				$request->set_param( 'template', Exports::template_in( $request->get_param( 'template' ) ) );
			}
		}
		if ( ( false === strpos( $route, 'digitalisimo' ) && 0 !== strpos( $route, '/' . Transform::P . '/' ) && 0 !== strpos( $route, '/' . Transform::P . '-' ) ) || self::route_exists( $server, $route ) ) {
			return $result;
		}
		$original = self::route_in( $route, self::namespaces( $server ) );
		if ( $original === $route ) {
			return $result;
		}
		$request->set_route( $original );
		foreach ( array( 'set_query_params' => 'get_query_params', 'set_body_params' => 'get_body_params', 'set_url_params' => 'get_url_params' ) as $set => $get ) {
			if ( method_exists( $request, $get ) && method_exists( $request, $set ) ) {
				$request->$set( self::json_in( (array) $request->$get() ) );
			}
		}
		if ( method_exists( $request, 'get_json_params' ) && method_exists( $request, 'set_body' ) && is_array( $request->get_json_params() ) ) {
			$json = wp_json_encode( self::json_in( $request->get_json_params() ) );
			if ( is_string( $json ) ) {
				$request->set_body( $json );
			}
		}
		return is_object( $server ) && method_exists( $server, 'dispatch' ) ? $server->dispatch( $request ) : $result;
	}

	private static function route_exists( $server, string $route ): bool {
		if ( ! is_object( $server ) || ! method_exists( $server, 'get_routes' ) ) {
			return false;
		}
		foreach ( array_keys( (array) $server->get_routes() ) as $pattern ) {
			if ( preg_match( '@^' . $pattern . '$@i', $route ) ) {
				return true;
			}
		}
		return false;
	}

	/** `rest_post_dispatch`: las respuestas de los espacios del motor (salvo el MCP viejo) con los nombres nuevos. */
	public static function rest_response( $response, $server = null, $request = null ) {
		if ( ! self::on() || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( preg_match( '#^/elementor/mcp(/|$)#', $route ) ) {
			return $response;
		}
		// OPTIONS: el esquema de la ruta.
		if ( method_exists( $request, 'get_method' ) && 'OPTIONS' === $request->get_method() ) {
			$response->set_data( self::schema_out( $response->get_data() ) );
			return $response;
		}
		if ( preg_match( '#^/elementor[^/]*/|^/wp/v2/elementor_#', $route ) ) {
			$response->set_data( Transform::json_out( $response->get_data() ) );
		} elseif ( 0 === strpos( $route, '/wp/v2/' ) ) {
			$data = $response->get_data();
			$response->set_data( is_array( $data ) && isset( $data[0] ) ? array_map( array( __CLASS__, 'post_out' ), $data ) : self::post_out( $data ) );
		}
		return $response;
	}

	/** Parámetros de la vista previa del editor en el front: `dg-preview` → `elementor-preview`. */
	public static function preview_request(): void {
		foreach ( array( '_GET', '_REQUEST' ) as $global ) {
			if ( isset( $GLOBALS[ $global ][ Transform::P . '-preview' ] ) && ! isset( $GLOBALS[ $global ]['elementor-preview'] ) ) {
				$GLOBALS[ $global ]['elementor-preview'] = $GLOBALS[ $global ][ Transform::P . '-preview' ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}
}
