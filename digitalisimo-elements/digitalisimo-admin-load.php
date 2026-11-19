<?php
/**
 * Lo que el constructor cuesta en cada petición sin que haga falta (auditoría del escritorio, 2026-11-18;
 * tests/playground/admin-perf.sh lo mide y tests/elements-admin-load.php lo comprueba).
 *
 * 1. Opciones leídas una a una. El núcleo y lo derivado de PRO leen en cada petición (escritorio, AJAX, REST, cron y
 *    portada) unas 50 opciones propias que en casi todos los sitios no existen: los experimentos que nadie guardó,
 *    ajustes nunca tocados, avisos. Sin una caché persistente cada lectura que falla es una consulta. Al final de la
 *    petición se anotan las que faltaron (la caché `notoptions` de WordPress, sólo las de prefijo del constructor) y
 *    en las siguientes se piden juntas con wp_prime_option_caches() antes de cargar el núcleo: una consulta en lugar
 *    de unas 50, con los mismos valores (las que existen se cargan en la caché de la petición; las que no, quedan
 *    como «no existe», igual que tras su consulta individual). La lista se rehace al cambiar de versión.
 * 2. El MCP del navegador del núcleo (`elementor-capabilities-mcp`, con sus dependencias unos 1,8 MB de JavaScript)
 *    se encola en todas las pantallas de la administración para Angie (un servicio del proveedor, fuera con
 *    DIGITALÍSIMO Elements) y para los navegadores con WebMCP (`navigator.modelContext`). Con el núcleo incluido se
 *    carga a demanda: un cargador mínimo lo trae, en el mismo orden y con sus datos, sólo si el navegador expone
 *    WebMCP. El editor del constructor no cambia (sus paquetes se cargan por su propia lista). Con el plugin Elementor
 *    original activo, manda él y el MCP se encola como siempre.
 * 3. Las hojas de Roboto desde Google Fonts que el núcleo encola en toda la administración (una petición a un tercero
 *    desde el navegador en cada pantalla) no se cargan con el núcleo incluido: esa interfaz usa la fuente del sistema.
 */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Elements_Admin_Load {
	/** Opciones que faltaron en peticiones anteriores: `{v: versión, names: string[]}`, con carga automática. */
	const OPTION = 'digitalisimo_elements_option_prime';

	/** Prefijos de las opciones del constructor y de Elements (nunca transitorios ni opciones de otros). */
	const PREFIX = '/^(?:_?elementor[_-]|elementor-pro_|_elementor_pro_|digitalisimo_elements_)[A-Za-z0-9_.:-]{1,150}$/';

	/** Tope de nombres anotados: un nombre que dependa de un ID no hace crecer la lista sin fin. */
	const MAX = 200;

	/** Paquete del MCP del navegador que el núcleo encola en toda la administración. */
	const MCP = 'elementor-v2-elementor-capabilities-mcp';

	/** @var bool El paquete se retiró de la cola en esta petición y el cargador lo traerá a demanda. */
	private static $deferred = false;

	public static function init() {
		self::prime();
		add_action( 'shutdown', array( __CLASS__, 'learn' ), 0 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'defer_browser_mcp' ), 21 );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_browser_mcp_loader' ), 50 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'drop_remote_fonts' ), 100 );
	}

	/**
	 * Hojas de fuentes remotas que el núcleo encola en la administración: Roboto desde Google Fonts en todas las
	 * pantallas (`elementor-one-admin-fonts`) y en las del constructor (`elementor-admin-top-bar-fonts`). Cada pantalla
	 * pedía dos archivos a un tercero desde el navegador del administrador; sin ellas, esa interfaz usa la fuente del
	 * sistema. Sólo con el núcleo incluido (con el plugin Elementor original, manda él).
	 */
	const REMOTE_FONTS = array( 'elementor-one-admin-fonts', 'elementor-admin-top-bar-fonts' );

	public static function drop_remote_fonts() {
		if ( ! class_exists( 'Digitalisimo_Elements_Core' ) || ! Digitalisimo_Elements_Core::bundled() ) {
			return array();
		}
		$dropped = array();
		foreach ( self::REMOTE_FONTS as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				wp_dequeue_style( $handle );
				$dropped[] = $handle;
			}
		}
		return $dropped;
	}

	/**
	 * Versión de la lista: la de Elements, que trae el núcleo incluido (se conoce antes de cargarlo). Con el plugin
	 * Elementor original activo, una lista de otra versión sólo pide de más alguna opción y se completa sola.
	 */
	public static function version() {
		return defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? (string) DIGITALISIMO_ELEMENTS_VERSION : '';
	}

	/** Lista anotada, válida sólo para esta versión. */
	public static function names( $stored = null ) {
		$stored = null === $stored && function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : $stored;
		if ( ! is_array( $stored ) || ( $stored['v'] ?? '' ) !== self::version() || ! is_array( $stored['names'] ?? null ) ) {
			return array();
		}
		return array_values( array_filter( $stored['names'], array( __CLASS__, 'valid' ) ) );
	}

	/**
	 * Nombre que puede anotarse: del constructor o de Elements y sin un número de dos o más cifras (los nombres por
	 * entrada o por ID, como `elementor_atomic_styles_fonts-local-338-frontend`, llenarían la lista sin servir a otra
	 * petición).
	 */
	public static function valid( $name ) {
		return is_string( $name ) && self::OPTION !== $name && 1 === preg_match( self::PREFIX, $name ) && 0 === preg_match( '/\d{2,}/', $name );
	}

	/**
	 * ¿Aporta algo en esta petición? Con una caché de objetos persistente (Redis, Memcached) WordPress ya guarda las
	 * opciones inexistentes entre peticiones: ni se piden ni se anotan.
	 */
	public static function enabled() {
		return ! ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) && ! ( function_exists( 'wp_installing' ) && wp_installing() );
	}

	/** Antes de cargar el núcleo: las opciones anotadas, en una sola consulta. */
	public static function prime() {
		if ( ! self::enabled() ) {
			return array();
		}
		$names = self::names();
		if ( $names && function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( $names );
		}
		return $names;
	}

	/**
	 * Lista siguiente: las anotadas que siguen sin existir (la precarga las dejó en `notoptions`; una que ya existe sale
	 * de la lista y no se vuelve a pedir en cada petición) más las que esta petición buscó sin encontrar. Devuelve la
	 * lista o null si no cambia. Pura: la usan learn() y la prueba.
	 *
	 * @param array      $stored  Valor guardado de OPTION.
	 * @param array|bool $missing Caché `notoptions` (nombre => true).
	 */
	public static function merge( $stored, $missing, $version ) {
		if ( ! is_array( $missing ) ) {
			return null; // Sin la caché de opciones ausentes no se sabe nada: la lista no cambia.
		}
		$missing = array_filter( array_map( 'strval', array_keys( $missing ) ), array( __CLASS__, 'valid' ) );
		$known   = is_array( $stored ) && ( $stored['v'] ?? '' ) === $version && is_array( $stored['names'] ?? null ) ? array_values( array_filter( $stored['names'], array( __CLASS__, 'valid' ) ) ) : array();
		$kept    = array_values( array_intersect( $known, $missing ) );
		$names   = array_slice( array_values( array_unique( array_merge( $kept, array_diff( $missing, $kept ) ) ) ), 0, self::MAX );
		sort( $names );
		$before = $known;
		sort( $before );
		return $names === $before && is_array( $stored ) && ( $stored['v'] ?? '' ) === $version ? null : ( $names || $known ? array( 'v' => $version, 'names' => $names ) : null );
	}

	/**
	 * ¿Se anota en esta petición? Sólo en la administración, AJAX, REST y cron (las visitas del front no escriben) y
	 * en el sitio propio de la petición (nunca dentro de un switch_to_blog() sin restaurar).
	 */
	public static function learning() {
		$context = ( function_exists( 'is_admin' ) && is_admin() ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI );
		return $context && ! ( function_exists( 'ms_is_switched' ) && ms_is_switched() );
	}

	/** `shutdown`: anota lo que faltó (sólo con el constructor cargado y fuera de la instalación). */
	public static function learn() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || ! self::enabled() || ! self::learning() || ! function_exists( 'wp_cache_get' ) ) {
			return;
		}
		$stored = get_option( self::OPTION, null );
		$next   = self::merge( $stored, wp_cache_get( 'notoptions', 'options' ), self::version() );
		if ( null !== $next && $next !== $stored ) {
			update_option( self::OPTION, $next, true );
		}
	}

	// ----------------------------------------------------------------------------------------------- MCP del navegador

	/** Al desactivar Elements: la lista es una caché, se borra (en toda la red si se desactiva en la red). */
	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				delete_blog_option( (int) $site_id, self::OPTION );
			}
			return;
		}
		delete_option( self::OPTION );
	}

	/**
	 * ¿Se carga siempre? Con Angie (servicio del proveedor que el administrador instale), reconocido por su constante,
	 * por su clase o por la carpeta de su plugin activo, o con el filtro.
	 */
	private static function eager() {
		$angie   = defined( 'ANGIE_VERSION' ) || defined( 'ANGIE_PLUGIN_FILE' ) || class_exists( 'Angie\\Plugin', false ) || class_exists( 'Angie\\Angie', false );
		$network = function_exists( 'is_multisite' ) && is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		foreach ( array_merge( (array) get_option( 'active_plugins', array() ), $network ) as $file ) {
			$angie = $angie || 0 === strpos( (string) $file, 'angie/' );
		}
		return (bool) apply_filters( 'digitalisimo_elements_browser_mcp_eager', $angie );
	}

	/** `admin_enqueue_scripts` (21, tras el 20 del núcleo): fuera de la cola; lo traerá el cargador si hace falta. */
	public static function defer_browser_mcp() {
		self::$deferred = false;
		if ( ! class_exists( 'Digitalisimo_Elements_Core' ) || ! Digitalisimo_Elements_Core::bundled() || ! wp_script_is( self::MCP, 'enqueued' ) || self::eager() ) {
			return;
		}
		wp_dequeue_script( self::MCP );
		self::$deferred = true;
	}

	/**
	 * Orden de carga de un paquete y sus dependencias (primero las dependencias), sin las ya impresas. Pura.
	 *
	 * @param array<string,string[]> $graph manejador => dependencias.
	 * @param string[]               $done  Manejadores ya impresos o encolados.
	 */
	public static function queue( array $graph, array $done, $root ) {
		$order = array();
		$seen  = array_fill_keys( $done, true );
		$visit = static function ( $handle ) use ( &$visit, &$order, &$seen, $graph ) {
			if ( isset( $seen[ $handle ] ) || ! isset( $graph[ $handle ] ) ) {
				return;
			}
			$seen[ $handle ] = true;
			foreach ( (array) $graph[ $handle ] as $dep ) {
				$visit( (string) $dep );
			}
			$order[] = $handle;
		};
		$visit( (string) $root );
		return $order;
	}

	/** Cada paquete pendiente con su dirección y sus datos en línea (traducciones, localizados, antes y después). */
	public static function entries() {
		$scripts = wp_scripts();
		$graph   = array();
		foreach ( $scripts->registered as $handle => $item ) {
			$graph[ $handle ] = (array) $item->deps;
		}
		$entries = array();
		foreach ( self::queue( $graph, array_merge( (array) $scripts->done, (array) $scripts->queue ), self::MCP ) as $handle ) {
			$item = $scripts->registered[ $handle ];
			$src  = is_string( $item->src ) ? $item->src : '';
			if ( '' !== $src ) {
				if ( ! preg_match( '#^(https?:)?//#', $src ) && ( '' === $scripts->content_url || 0 !== strpos( $src, $scripts->content_url ) ) ) {
					$src = $scripts->base_url . $src;
				}
				$ver = null === $item->ver ? '' : ( $item->ver ? $item->ver : $scripts->default_version );
				$src = (string) apply_filters( 'script_loader_src', '' !== $ver ? add_query_arg( 'ver', $ver, $src ) : $src, $handle );
			}
			$before = (string) $scripts->print_translations( $handle, false ) . "\n" . (string) $scripts->print_extra_script( $handle, false ) . "\n" . (string) $scripts->get_inline_script_data( $handle, 'before' );
			$entries[] = array( 'h' => $handle, 'src' => $src, 'before' => trim( $before ), 'after' => (string) $scripts->get_inline_script_data( $handle, 'after' ) );
		}
		return $entries;
	}

	/** Pie de la administración: el cargador, sólo si se retiró el paquete. */
	public static function print_browser_mcp_loader() {
		if ( ! self::$deferred || ! function_exists( 'wp_scripts' ) ) {
			return;
		}
		$entries = self::entries();
		if ( ! $entries ) {
			return;
		}
		// Por wp_print_inline_script_tag(): los plugins de CSP con nonce pueden marcarlo como cualquier script en línea.
		wp_print_inline_script_tag( self::loader_js( $entries ), array( 'id' => 'digitalisimo-elements-browser-mcp' ) );
	}

	/** Trae los paquetes en orden (cada uno tras el anterior, con sus datos antes y después) si hay WebMCP. */
	public static function loader_js( array $entries ) {
		return '(function(q){if(!((typeof navigator!=="undefined"&&navigator.modelContext)||(typeof document!=="undefined"&&document.modelContext))){return;}'
			. 'function run(c){if(c){var s=document.createElement("script");s.text=c;document.body.appendChild(s);}}'
			. 'function next(){var e=q.shift();if(!e){return;}run(e.before);if(!e.src){run(e.after);next();return;}'
			. 'var s=document.createElement("script");s.src=e.src;s.id=e.h+"-js";s.onload=function(){run(e.after);next();};s.onerror=next;document.body.appendChild(s);}'
			. 'next();})(' . wp_json_encode( $entries, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . ');';
	}
}
