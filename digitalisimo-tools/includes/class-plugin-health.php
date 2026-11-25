<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * Salud de plugins: un plugin «activo» que no cargó.
 *
 * Caso del 23-nov (digitalisimo.lat): WooCommerce 11.3.1 figuraba activo y, a la vez, no había tipo `product` ni rutas
 * `wc/v3`; después volvió solo. Revisados todos los filtros de carga de plugins de DIGITALÍSIMO (`option_active_plugins`,
 * `pre_option_active_plugins`, `site_option_active_sitewide_plugins`, mu-plugins, Código PHP, el mantenimiento y el
 * WordPress mínimo de Backups, el modo de rescate): el único que deja fuera plugins es el WordPress mínimo de una
 * restauración de Backups en curso (`.maintenance` propio, sólo `wp-cron.php` y la pantalla de Backups), a propósito.
 * Lo visto sin sesión era la API guardada por la caché de página (ver Rest_Cache).
 *
 * Para que la próxima vez se vea en el sitio, sin suponer: se compara lo que la base de datos dice que está activo
 * (`active_plugins` del sitio y, en una red, `active_sitewide_plugins`, leídos directo de la tabla) con lo que de verdad
 * cargó esta petición (`get_included_files()`, tras `plugins_loaded`), y se dice por qué falta:
 *  - `filtered`: la caché da la lista completa, pero un filtro la recorta (se nombra quién: archivo y línea);
 *  - `cache`: la caché de objetos da otra lista que la base (vieja o de otro sitio);
 *  - `missing`: el archivo no existe o WordPress no lo acepta (actualización a medias, carpeta renombrada);
 *  - `not_loaded`: está en la lista y su archivo existe, pero esta petición no lo incluyó;
 *  - `marker`: cargó pero no hizo lo que siempre hace (WooCommerce sin el tipo `product` o sin `wc/v3`; Elementor sin
 *    `elementor/loaded`).
 * Es barato y nunca corre en la portada: una consulta y un `get_included_files()` sólo en el Escritorio, Plugins y las
 * pantallas Digitalisimo de quien gestiona plugins, en «Salud del sitio», en la función del MCP y, en segundo plano, en
 * la comprobación de actualizaciones de plugins del cron de WordPress (`wp_update_plugins`, dos veces al día: no agrega
 * ningún evento). Lo que ve el cron (o el MCP) se guarda sólo si hay un problema (`digitalisimo_tools_plugin_health`,
 * sin carga automática) y el Escritorio lo dice durante tres días aunque ya se haya arreglado solo.
 *
 * En el MCP: la Ability `digitalisimo-tools/plugin-health` (sólo lectura, `activate_plugins`), pública para MCP y, con
 * el MCP de DIGITALÍSIMO Elements, en `/wp-json/elementor/mcp` (`digitalisimo-tools-plugin-health`). Dice también si la
 * API pública puede salir de una caché de página (Rest_Cache::status()).
 */
final class Plugin_Health {
	/** Último problema visto en segundo plano (cron o MCP): `{at, context, problems}`, sin carga automática. */
	const OPTION   = 'digitalisimo_tools_plugin_health';
	const ABILITY  = 'digitalisimo-tools/plugin-health';
	const CATEGORY = 'digitalisimo-tools';
	/** Cuánto se recuerda en el Escritorio un problema visto en segundo plano que ya no está. */
	const RECENT = 259200;

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
		add_action( 'wp_update_plugins', array( __CLASS__, 'background' ), 99 );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_ability' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'server_tools' ) );
	}

	/**
	 * Marcas conocidas de que un plugin cargó de verdad: `post_type` (tras `init`), `rest` (namespace, sólo si el
	 * servidor REST ya se armó en esta petición) y `action` (un gancho que dispara al cargar).
	 */
	public static function markers() {
		$markers = array(
			'woocommerce/woocommerce.php' => array( 'post_type' => 'product', 'rest' => 'wc/v3' ),
			'elementor/elementor.php'     => array( 'action' => 'elementor/loaded' ),
		);
		return (array) apply_filters( 'digitalisimo_tools_plugin_health_markers', $markers );
	}

	/**
	 * Los plugins activos que no cargaron en esta petición: lista de `{plugin, name, reason, detail}` ('' si todo bien).
	 * Las fuentes se pueden pasar (pruebas); por defecto, la base de datos, la caché y los archivos incluidos.
	 */
	public static function check( array $sources = array() ) {
		if ( ! did_action( 'plugins_loaded' ) ) return array();
		$network   = function_exists( 'is_multisite' ) && is_multisite();
		$stored    = array_key_exists( 'stored', $sources ) ? (array) $sources['stored'] : self::stored( $network );
		$visible   = array_key_exists( 'visible', $sources ) ? (array) $sources['visible'] : self::visible( $network );
		$ext       = array_key_exists( 'ext', $sources ) ? (bool) $sources['ext'] : ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() );
		$cached    = array_key_exists( 'cached', $sources ) ? (array) $sources['cached'] : self::cached( $network, $ext );
		$included  = array_key_exists( 'included', $sources ) ? (array) $sources['included'] : array_flip( get_included_files() );
		$markers   = self::markers();
		$problems  = array();
		$recovery  = function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode();
		$paused    = $recovery && function_exists( 'wp_paused_plugins' ) ? (array) wp_paused_plugins()->get_all() : array();
		foreach ( array_unique( array_map( 'strval', $stored ) ) as $file ) {
			if ( '' === $file || isset( $paused[ dirname( $file ) ] ) || isset( $paused[ $file ] ) ) continue;
			$path = rtrim( WP_PLUGIN_DIR, '/\\' ) . '/' . $file;
			if ( ! in_array( $file, $visible, true ) ) {
				if ( in_array( $file, $cached, true ) ) {
					$problems[] = self::problem( $file, 'filtered', 'Está activo en la base de datos, pero un filtro lo quita de la lista de plugins activos en esta petición' . self::filters_text() . '. Si lo hace a propósito un plugin que organiza qué se carga en cada pantalla, cierra este aviso.' );
				} elseif ( $ext ) {
					$problems[] = self::problem( $file, 'cache', 'Está activo en la base de datos, pero la caché de objetos da una lista de plugins activos vieja o de otro sitio: WordPress no lo carga mientras la caché diga eso.' );
				}
				// Sin caché persistente, la lista de esta petición salió de la base al arrancar: si no lo trae, otra petición
				// lo acaba de activar (no es un problema).
				continue;
			}
			if ( ( function_exists( 'validate_file' ) && 0 !== validate_file( $file ) ) || '.php' !== substr( $file, -4 ) || ! is_file( $path ) ) {
				$problems[] = self::problem( $file, 'missing', 'Figura activo, pero su archivo (' . $file . ') no existe o WordPress no lo acepta: una actualización a medias o una carpeta renombrada.' );
				continue;
			}
			$real = realpath( $path );
			if ( ! isset( $included[ $path ] ) && ( false === $real || ! isset( $included[ $real ] ) ) ) {
				$problems[] = self::problem( $file, 'not_loaded', 'Figura activo y su archivo existe, pero esta petición no lo cargó: la lista que usó WordPress al arrancar no lo traía (caché de objetos o un filtro que sólo actúa al arrancar).' );
				continue;
			}
			$marker = self::marker_problem( $file, $markers );
			if ( '' !== $marker ) $problems[] = self::problem( $file, 'marker', $marker );
		}
		return $problems;
	}

	/** Lo que la base de datos dice que está activo (sin caché ni filtros). */
	private static function stored( $network ) {
		global $wpdb;
		$out = array();
		if ( ! is_object( $wpdb ) ) return $out;
		$site = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'active_plugins' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- la lectura sin caché es el objeto de la comprobación.
		$out  = array_values( (array) maybe_unserialize( (string) $site ) );
		if ( $network ) {
			$wide = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1", 'active_sitewide_plugins', (int) get_current_network_id() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$out  = array_merge( $out, array_keys( (array) maybe_unserialize( (string) $wide ) ) );
		}
		return array_values( array_filter( array_map( 'strval', $out ), 'strlen' ) );
	}

	/** Lo que WordPress ve ahora (caché y filtros), lo que usa wp_get_active_and_valid_plugins(). */
	private static function visible( $network ) {
		$out = (array) get_option( 'active_plugins', array() );
		if ( $network ) $out = array_merge( $out, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		return array_values( array_map( 'strval', $out ) );
	}

	/**
	 * La lista de la caché, sin filtros: `alloptions` (o la clave suelta) y la de la red. Con caché de objetos persistente
	 * se vuelve a leer de ella y no de la copia de esta petición (si otra petición acaba de activar el plugin, no es un
	 * problema); sin ella, la copia de esta petición, que salió de la base al arrancar.
	 */
	private static function cached( $network, $ext ) {
		$all  = $ext ? wp_cache_get( 'alloptions', 'options', true ) : wp_load_alloptions();
		$site = is_array( $all ) && array_key_exists( 'active_plugins', $all ) ? $all['active_plugins'] : wp_cache_get( 'active_plugins', 'options', $ext );
		$out  = false === $site ? array() : array_values( (array) maybe_unserialize( $site ) );
		if ( $network ) {
			$wide = wp_cache_get( (int) get_current_network_id() . ':active_sitewide_plugins', 'site-options', $ext );
			if ( false !== $wide ) $out = array_merge( $out, array_keys( (array) maybe_unserialize( $wide ) ) );
		}
		return array_values( array_map( 'strval', $out ) );
	}

	/** Quién filtra la lista de plugins activos: « (filtro de …)» con archivo y línea, o ''. */
	private static function filters_text() {
		$who = self::filters();
		return $who ? ' (' . implode( '; ', $who ) . ')' : '';
	}

	/** Las funciones enganchadas a los filtros de la lista de plugins activos, descritas por su archivo y línea. */
	public static function filters() {
		global $wp_filter;
		$out = array();
		foreach ( array( 'pre_option_active_plugins', 'option_active_plugins', 'pre_site_option_active_sitewide_plugins', 'site_option_active_sitewide_plugins' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || empty( $wp_filter[ $hook ]->callbacks ) ) continue;
			foreach ( (array) $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( (array) $callbacks as $callback ) $out[] = $hook . ': ' . self::describe( $callback['function'] ?? null );
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function describe( $callback ) {
		try {
			if ( is_string( $callback ) && false === strpos( $callback, '::' ) ) $ref = new \ReflectionFunction( $callback );
			elseif ( is_string( $callback ) ) $ref = new \ReflectionMethod( $callback );
			elseif ( is_array( $callback ) && 2 === count( $callback ) ) $ref = new \ReflectionMethod( is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0], (string) $callback[1] );
			elseif ( $callback instanceof \Closure ) $ref = new \ReflectionFunction( $callback );
			elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) $ref = new \ReflectionMethod( $callback, '__invoke' );
			else return 'función desconocida';
		} catch ( \ReflectionException $e ) {
			return 'función desconocida';
		}
		$file = (string) $ref->getFileName();
		if ( '' === $file ) return $ref->getName();
		$root = defined( 'ABSPATH' ) ? rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/' : '';
		$file = str_replace( '\\', '/', $file );
		if ( '' !== $root && 0 === strpos( $file, $root ) ) $file = substr( $file, strlen( $root ) );
		return $file . ':' . (int) $ref->getStartLine();
	}

	private static function marker_problem( $file, array $markers ) {
		// Activado en esta misma petición (`activate_plugin()` lo incluye después de `init`): todavía no registró nada.
		if ( empty( $markers[ $file ] ) || ! is_array( $markers[ $file ] ) || did_action( 'activate_' . $file ) ) return '';
		$m = $markers[ $file ];
		if ( ! empty( $m['action'] ) && ! did_action( (string) $m['action'] ) ) return 'Su archivo cargó, pero no llegó a arrancar (no disparó «' . $m['action'] . '»).';
		if ( ! empty( $m['post_type'] ) && did_action( 'init' ) && function_exists( 'post_type_exists' ) && ! post_type_exists( (string) $m['post_type'] ) ) return 'Su archivo cargó, pero no registró el tipo de contenido «' . $m['post_type'] . '».';
		if ( ! empty( $m['rest'] ) && did_action( 'rest_api_init' ) && function_exists( 'rest_get_server' ) && ! in_array( (string) $m['rest'], (array) rest_get_server()->get_namespaces(), true ) ) return 'Su archivo cargó, pero no registró las rutas «' . $m['rest'] . '» de la API.';
		return '';
	}

	private static function problem( $file, $reason, $detail ) {
		return array( 'plugin' => $file, 'name' => self::name( $file ), 'reason' => $reason, 'detail' => $detail );
	}

	private static function name( $file ) {
		$path = rtrim( WP_PLUGIN_DIR, '/\\' ) . '/' . $file;
		if ( is_file( $path ) ) {
			if ( ! function_exists( 'get_plugin_data' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
			if ( function_exists( 'get_plugin_data' ) ) {
				$data = get_plugin_data( $path, false, false );
				if ( ! empty( $data['Name'] ) ) return (string) $data['Name'];
			}
		}
		return $file;
	}

	/** Texto de una lista de problemas: «WooCommerce (woocommerce/woocommerce.php): …». */
	private static function text( array $problems ) {
		$out = array();
		foreach ( $problems as $p ) $out[] = ( $p['name'] !== $p['plugin'] ? $p['name'] . ' (' . $p['plugin'] . ')' : $p['plugin'] ) . ': ' . $p['detail'];
		return $out;
	}

	/** Lo último que vio el segundo plano, si es reciente. */
	public static function last_seen() {
		$seen = get_option( self::OPTION, array() );
		if ( ! is_array( $seen ) || empty( $seen['problems'] ) || time() - (int) ( $seen['at'] ?? 0 ) > self::RECENT ) return array();
		return $seen;
	}

	/** Guarda lo visto en segundo plano sólo si hay un problema (ninguna escritura en un sitio sano). */
	private static function remember( array $problems, $context ) {
		if ( ! $problems ) return;
		update_option( self::OPTION, array( 'at' => time(), 'context' => (string) $context, 'problems' => $problems ), false );
	}

	/** `wp_update_plugins` (cron de WordPress, dos veces al día): la misma comprobación, en una petición sin escritorio. */
	public static function background() {
		if ( ! function_exists( 'wp_doing_cron' ) || ! wp_doing_cron() ) return;
		self::remember( self::check(), 'cron' );
	}

	private static function cap() {
		return function_exists( 'is_network_admin' ) && is_network_admin() ? 'manage_network_plugins' : 'activate_plugins';
	}

	private static function screen_wanted() {
		if ( ! current_user_can( self::cap() ) ) return false;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se muestra el aviso.
		return in_array( $id, array( 'dashboard', 'dashboard-network', 'plugins', 'plugins-network' ), true ) || 0 === strpos( $page, 'digitalisimo' );
	}

	/** Estado para el aviso y «Salud del sitio»: '' (bien), 'now' (ahora) o 'recent' (lo vio el segundo plano). */
	public static function status() {
		$now = self::check();
		if ( $now ) return array( 'state' => 'now', 'problems' => $now, 'message' => 'Hay plugins activos que no cargaron en esta petición. ' . implode( ' ', self::text( $now ) ) );
		$seen = self::last_seen();
		if ( $seen ) {
			$when = function_exists( 'wp_date' ) ? wp_date( 'j/m/Y H:i', (int) $seen['at'] ) : gmdate( 'Y-m-d H:i', (int) $seen['at'] );
			$where = 'mcp' === $seen['context'] ? 'una petición del MCP' : 'una tarea de fondo de WordPress (cron)';
			return array( 'state' => 'recent', 'problems' => (array) $seen['problems'], 'message' => 'El ' . $when . ', en ' . $where . ', había plugins activos que no cargaron; ahora sí cargan. ' . implode( ' ', self::text( (array) $seen['problems'] ) ) . ' Si vuelve a pasar, revisa la caché de objetos y los mu-plugins del servidor.' );
		}
		return array( 'state' => '', 'problems' => array(), 'message' => '' );
	}

	public static function notice() {
		if ( ! self::screen_wanted() || ! class_exists( '\\Digitalisimo_Notices' ) ) return;
		$status = self::status();
		if ( '' === $status['state'] ) return;
		$hash = array( $status['state'] );
		foreach ( $status['problems'] as $p ) $hash[] = $p['plugin'] . ':' . $p['reason'];
		\Digitalisimo_Notices::show( \Digitalisimo_Notices::site( 'tools-plugin-health' ), self::serious( $status ) ? 'error' : 'warning', '<p><strong>DIGITALÍSIMO:</strong> ' . esc_html( $status['message'] ) . '</p>', array( 'hash' => $hash, 'cap' => self::cap() ) );
	}

	/**
	 * ¿Error o advertencia? Un plugin que sólo falta porque un filtro lo quita puede ser a propósito (un organizador de
	 * carga como Perfmatters o Plugin Organizer): advertencia, que se cierra por 7 días. Lo demás, error.
	 */
	private static function serious( array $status ) {
		if ( 'now' !== $status['state'] ) return false;
		foreach ( $status['problems'] as $p ) if ( 'filtered' !== $p['reason'] ) return true;
		return false;
	}

	public static function tests( $tests ) {
		$tests['direct']['digitalisimo_plugin_health'] = array( 'label' => 'Los plugins activos cargan', 'test' => array( __CLASS__, 'health' ) );
		return $tests;
	}

	public static function health() {
		$status = self::status();
		$result = array(
			'label'       => 'Todos los plugins activos cargan',
			'status'      => 'good',
			'badge'       => array( 'label' => 'DIGITALÍSIMO', 'color' => 'blue' ),
			'description' => '<p>' . esc_html( 'Cada plugin activo en la base de datos cargó en esta petición.' ) . '</p>',
			'actions'     => '',
			'test'        => 'digitalisimo_plugin_health',
		);
		if ( 'now' === $status['state'] ) {
			$result['label']  = 'Hay plugins activos que no cargaron';
			$result['status'] = self::serious( $status ) ? 'critical' : 'recommended';
		} elseif ( 'recent' === $status['state'] ) {
			$result['label']  = 'Hace poco hubo plugins activos que no cargaron';
			$result['status'] = 'recommended';
		}
		if ( '' !== $status['state'] ) $result['description'] = '<p>' . esc_html( $status['message'] ) . '</p>';
		return $result;
	}

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) || ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) ) return;
		wp_register_ability_category( self::CATEGORY, array( 'label' => 'DIGITALÍSIMO Tools', 'description' => 'Diagnóstico de la administración de este sitio.' ) );
	}

	public static function register_ability() {
		if ( ! function_exists( 'wp_register_ability' ) || ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::ABILITY ) ) ) return;
		wp_register_ability( self::ABILITY, self::definition() );
	}

	public static function definition() {
		$problem = array( 'type' => 'object', 'properties' => array( 'plugin' => array( 'type' => 'string' ), 'name' => array( 'type' => 'string' ), 'reason' => array( 'type' => 'string', 'enum' => array( 'filtered', 'cache', 'missing', 'not_loaded', 'marker' ) ), 'detail' => array( 'type' => 'string' ) ) );
		return array(
			'label'               => 'Salud de los plugins',
			'description'         => 'Comprueba que cada plugin activo en la base de datos cargó de verdad en esta petición (WooCommerce con su tipo product y sus rutas wc/v3, Elementor arrancado) y dice por qué falta uno: un filtro, la caché de objetos, el archivo que no existe o que no se incluyó. Úsala cuando las herramientas o rutas de un plugin activo no aparecen. Incluye lo último que vio el cron y si la API pública puede salir de una caché de página (LiteSpeed). No cambia nada.',
			'category'            => self::CATEGORY,
			'input_schema'        => array( 'type' => 'object', 'default' => array(), 'properties' => array(), 'additionalProperties' => false ),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'ok'         => array( 'type' => 'boolean' ),
					'site'       => array( 'type' => 'string' ),
					'problems'   => array( 'type' => 'array', 'items' => $problem ),
					'background' => array( 'type' => 'object' ),
					'rest_cache' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => array( __CLASS__, 'execute' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			),
		);
	}

	public static function permission( $input = null ) {
		return is_user_logged_in() && current_user_can( 'activate_plugins' );
	}

	public static function execute( $input = null ) {
		$problems = self::check();
		self::remember( $problems, 'mcp' );
		$seen = self::last_seen();
		return array(
			'ok'         => ! $problems,
			'site'       => home_url( '/' ),
			'problems'   => $problems,
			'background' => $seen ? array( 'at' => gmdate( 'c', (int) $seen['at'] ), 'context' => (string) $seen['context'], 'problems' => (array) $seen['problems'] ) : (object) array(),
			'rest_cache' => class_exists( __NAMESPACE__ . '\\Rest_Cache', false ) ? Rest_Cache::status() : (object) array(),
		);
	}

	/** El servidor MCP de DIGITALÍSIMO Elements la ofrece junto a las suyas. */
	public static function server_tools( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		if ( function_exists( 'wp_register_ability' ) && ! in_array( self::ABILITY, $tools, true ) ) $tools[] = self::ABILITY;
		return $tools;
	}
}
