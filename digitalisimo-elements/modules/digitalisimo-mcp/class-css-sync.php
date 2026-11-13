<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * El CSS de un documento al día tras cada escritura del MCP del constructor (`POST /wp-json/elementor/mcp`, también
 * el proxy `/elementor/v1/mcp-proxy` y la REST de abilities), sin editar nada de `elementor/` y sin borrar la caché de
 * todo el sitio. Detalle de la causa en `docs/ELEMENTS_MCP_CSS.md`.
 *
 * Causa (núcleo 4.3.5): los estilos locales atómicos se sirven desde `uploads/elementor/css/local-<id>-<contexto>-
 * <punto>.css`, con su validez en la opción `elementor_atomic_cache_validity__local` (hoja `[local, id, contexto]`).
 * Al guardar, `Atomic_Widget_Styles` (modules/atomic-widgets/styles/atomic-widget-styles.php) sólo invalida la hoja
 * «preview» si el documento guardado no está publicado (un borrador, o el autoguardado de una página publicada), y
 * `Publish_Document_Ability` (modules/mcp/abilities/publish-document-ability.php) publica o promueve el autoguardado sin
 * pasar por `Document::save()`, así que no invalida nada (ni la caché HTML del documento). La hoja «frontend» (la del
 * visitante y la del enlace firmado de `create-preview-link`) se genera en la primera visita y queda válida.
 * `Manage_Elements_Ability` lo tapa borrando la caché de todo el sitio; `build-composition` no.
 *
 * Qué hace, sólo mientras corre una herramienta del constructor (`elementor/…`):
 * - anota el documento vivo que cambió: guardado del propio documento (`elementor/document/after_save`, no el de un
 *   autoguardado), escritura de `_elementor_data` en él (la promoción de `publish-document`) o su paso a `publish`;
 * - al terminar la herramienta (o la petición) invalida, sólo de esos documentos, sus estilos locales y de clases
 *   globales en todos los contextos (los nodos `[local, id]` y `[global, id]` de `Cache_Validity`, la clase del núcleo),
 *   la lista de componentes que incrusta y su caché HTML (`_elementor_element_cache`): el siguiente render los regenera
 *   con un `ver` nuevo, como al guardar y publicar en el editor. Un cambio que sólo queda en el autoguardado de una
 *   página publicada no toca la publicada.
 * Los enlaces firmados de `create-preview-link` los atiende `Preview_Link`.
 * Lo que cambia el kit (clases globales, variables, estilos predeterminados) ya lo regenera el núcleo.
 */
final class Css_Sync {
	const ABILITY_PREFIX = 'elementor/';
	const PROXY_ROUTE    = '/elementor/v1/mcp-proxy';
	const VALIDITY_CLASS = 'Elementor\\Modules\\AtomicWidgets\\Styles\\CacheValidity\\Cache_Validity';

	/**
	 * Claves del núcleo que usa el módulo: nombre interno => clase y constante del núcleo, y el valor de 4.3.5 por si la
	 * clase no está (`tests/elements-mcp-css.php` comprueba que coinciden).
	 */
	const KEYS = array(
		'local'                  => array( 'Elementor\\Modules\\AtomicWidgets\\Styles\\Atomic_Widget_Styles', 'STYLES_KEY', 'local' ),
		'global'                 => array( 'Elementor\\Modules\\GlobalClasses\\Atomic_Global_Styles', 'STYLES_KEY', 'global' ),
		'global_related'         => array( 'Elementor\\Modules\\GlobalClasses\\Atomic_Global_Styles', 'RELATED_KEY', 'related' ),
		'global_related_reverse' => array( 'Elementor\\Modules\\GlobalClasses\\Atomic_Global_Styles', 'RELATED_REVERSE_KEY', 'related-reverse' ),
		'related'                => array( 'Elementor\\Modules\\Components\\Styles\\Component_Styles', 'CACHE_ROOT_KEY', 'component-styles-related-posts' ),
		'validity'               => array( 'Elementor\\Modules\\AtomicWidgets\\Styles\\CacheValidity\\Cache_Validity_Item', 'CACHE_KEY_PREFIX', 'elementor_atomic_cache_validity__' ),
		'html'                   => array( 'Elementor\\Core\\Base\\Document', 'CACHE_META_KEY', '_elementor_element_cache' ),
		'css'                    => array( 'Elementor\\Core\\Files\\CSS\\Post', 'META_KEY', '_elementor_css' ),
		'assets'                 => array( 'Elementor\\Core\\Base\\Elements_Iteration_Actions\\Assets', 'ASSETS_META_KEY', '_elementor_page_assets' ),
		'data'                   => array( 'Elementor\\Core\\Base\\Document', 'ELEMENTOR_DATA_META_KEY', '_elementor_data' ),
		'token'                  => array( 'Elementor\\Modules\\Mcp\\Preview\\Preview_Token', 'QUERY_ARG', 'elementor_preview_token' ),
	);

	/** @var bool */
	private static $initialized = false;

	/** @var int Herramientas del constructor en curso (anidadas) en esta petición. */
	private static $depth = 0;

	/** @var array<int, true> Documentos vivos que cambiaron y aún no se refrescaron. */
	private static $pending = array();

	/**
	 * @var array<int, array{0: string, 1: bool}> Herramientas en curso (pila): nombre y si su `execute_callback`
	 * envuelto la tomó (entonces la cierra él, en su `finally`).
	 */
	private static $frames = array();

	/** @var array<int, true> Entradas que ya pasaron por `save_post` en este ámbito (la caché de página ya se enteró). */
	private static $saved = array();

	/** @var bool Mientras se refresca: lo que dispara el propio refresco (`wp_update_post()`) no se anota. */
	private static $flushing = false;

	/** @var array<string, string> Claves ya resueltas (ver `key()`). */
	private static $keys = array();

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'wrap_ability' ), PHP_INT_MAX, 2 );
		add_action( 'wp_before_execute_ability', array( __CLASS__, 'before_ability' ), 10, 1 );
		add_action( 'wp_after_execute_ability', array( __CLASS__, 'after_ability' ), 10, 1 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_rest' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_rest' ), 10, 3 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'document_saved' ), 100, 1 );
		add_action( 'added_post_meta', array( __CLASS__, 'meta_written' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'meta_written' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
		add_action( 'save_post', array( __CLASS__, 'post_saved' ), 10, 1 );
		add_action( 'shutdown', array( __CLASS__, 'shutdown' ), 0 );
	}

	/**
	 * `wp_register_ability_args` (Abilities API de WordPress 6.9), con la última prioridad (envuelve el callback que
	 * quede tras los demás filtros): el `execute_callback` de cada herramienta del constructor toma el ámbito y lo cierra
	 * en un `finally`, también si devuelve un `WP_Error` o lanza una excepción. `wp_after_execute_ability` no se dispara
	 * con un `WP_Error`: con sólo esos ganchos, en un proceso largo (el transporte STDIO del adaptador) el ámbito quedaba
	 * abierto y nada se refrescaba hasta cerrar la sesión. Si el envoltorio no llega a ejecutarse (otro código cambió el
	 * callback), `wp_before/after_execute_ability` abren y cierran el ámbito igual.
	 */
	public static function wrap_ability( $args, $name = '' ) {
		if ( ! self::is_builder_ability( $name ) || ! is_array( $args ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			return $args;
		}
		$inner                    = $args['execute_callback'];
		$args['execute_callback'] = static function ( ...$params ) use ( $inner, $name ) {
			self::claim( (string) $name );
			try {
				return call_user_func_array( $inner, $params );
			} finally {
				array_pop( self::$frames );
				self::leave();
			}
		};
		return $args;
	}

	/** `wp_before_execute_ability`: abre el ámbito de la herramienta (el envoltorio, si corre, lo toma y lo cierra). */
	public static function before_ability( $name ): void {
		if ( self::is_builder_ability( $name ) ) {
			self::$frames[] = array( (string) $name, false );
			++self::$depth;
		}
	}

	/** `wp_after_execute_ability`: cierra el ámbito que abrió `before_ability()` si el envoltorio no lo tomó. */
	public static function after_ability( $name ): void {
		if ( ! self::is_builder_ability( $name ) || empty( self::$frames ) ) {
			return;
		}
		$top = self::$frames[ count( self::$frames ) - 1 ];
		if ( $top[0] === $name && ! $top[1] ) {
			array_pop( self::$frames );
			self::leave();
		}
	}

	/** El envoltorio toma el ámbito que abrió `before_ability()` para esta herramienta, o abre uno propio. */
	private static function claim( string $name ): void {
		$last = count( self::$frames ) - 1;
		if ( $last >= 0 && self::$frames[ $last ][0] === $name && ! self::$frames[ $last ][1] ) {
			self::$frames[ $last ][1] = true;
			return;
		}
		self::$frames[] = array( $name, true );
		++self::$depth;
	}

	/** El proxy del editor ejecuta las herramientas sin la Abilities API: su ruta REST abre el mismo ámbito. */
	public static function before_rest( $response, $handler = null, $request = null ) {
		if ( self::is_proxy( $request ) ) {
			++self::$depth;
		}
		return $response;
	}

	public static function after_rest( $response, $handler = null, $request = null ) {
		if ( self::is_proxy( $request ) ) {
			self::leave();
		}
		return $response;
	}

	/** Un guardado del propio documento vivo (no el de su autoguardado: la publicada no cambia). */
	public static function document_saved( $document ): void {
		if ( ! self::$depth || self::$flushing || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! method_exists( $document, 'get_post' ) ) {
			return;
		}
		$post = $document->get_post();
		$main = (int) $document->get_main_id();
		if ( $main > 0 && is_object( $post ) && isset( $post->ID ) && (int) $post->ID === $main ) {
			self::$pending[ $main ] = true;
		}
	}

	/** `_elementor_data` escrito en un documento vivo (p. ej. la promoción del autoguardado de `publish-document`). */
	public static function meta_written( $meta_id, $object_id, $meta_key ): void {
		if ( ! self::$depth || self::$flushing || self::key( 'data' ) !== $meta_key ) {
			return;
		}
		$id = (int) $object_id;
		if ( $id > 0 && ! wp_is_post_revision( $id ) ) {
			self::$pending[ $id ] = true;
		}
	}

	/** Un documento que pasa a `publish` (el editor invalida todos los contextos al publicar). */
	public static function status_changed( $new_status, $old_status, $post ): void {
		if ( ! self::$depth || self::$flushing || 'publish' !== $new_status || $new_status === $old_status || ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}
		if ( ! wp_is_post_revision( (int) $post->ID ) ) {
			self::$pending[ (int) $post->ID ] = true;
		}
	}

	/** `save_post` de una entrada dentro del ámbito: WordPress ya avisó a las cachés de página. */
	public static function post_saved( $post_id ): void {
		if ( self::$depth && ! self::$flushing && (int) $post_id > 0 ) {
			self::$saved[ (int) $post_id ] = true;
		}
	}

	/** Refresca lo anotado (al cerrar el ámbito y, por si una herramienta falló a medias, al terminar la petición). */
	public static function flush(): void {
		if ( self::$flushing ) {
			return;
		}
		$ids            = array_keys( self::$pending );
		$saved          = self::$saved;
		self::$pending  = array();
		self::$saved    = array();
		self::$flushing = true;
		try {
			foreach ( $ids as $id ) {
				self::refresh( (int) $id, isset( $saved[ $id ] ) );
			}
		} finally {
			self::$flushing = false;
		}
	}

	/**
	 * Lo que el editor deja listo al guardar y publicar un documento: sus estilos locales y de clases globales en todos
	 * los contextos, la lista de componentes que incrusta y su caché HTML (se regeneran en el siguiente render). Y si la
	 * entrada está publicada y en este ámbito no pasó por `save_post` (la promoción del autoguardado de
	 * `publish-document` sólo copia metas), un `wp_update_post()` sin cambios: el guardado normal que el editor hace al
	 * publicar, con todos sus ganchos (`save_post`, `edit_post`, `post_updated`, `clean_post_cache`,
	 * `wp_after_insert_post`), que es lo que escuchan las cachés de página (LSCache, WP Rocket, W3TC, Cloudflare…) para
	 * purgar esa URL.
	 */
	public static function refresh( int $id, bool $notified = false ): void {
		if ( $id <= 0 ) {
			return;
		}
		self::invalidate_styles( $id );
		delete_post_meta( $id, self::key( 'html' ) );
		self::invalidate_related( $id );
		if ( ! $notified && 'publish' === get_post_status( $id ) && function_exists( 'wp_update_post' ) ) {
			wp_update_post( array( 'ID' => $id ) );
		}
	}

	public static function shutdown(): void {
		self::flush();
	}

	/**
	 * Hojas locales y de clases globales de la página, en todos los contextos y puntos: se marcan inválidas sin borrar
	 * sus archivos; el siguiente render las reescribe de forma atómica con un `ver` nuevo, y una respuesta ya enviada que
	 * las enlaza no se queda sin ellas.
	 */
	public static function invalidate_styles( int $id ): void {
		$class = self::VALIDITY_CLASS;
		if ( $id <= 0 || ! class_exists( $class ) ) {
			return;
		}
		$validity = new $class();
		foreach ( array( self::key( 'local' ), self::key( 'global' ) ) as $root ) {
			$validity->invalidate( array( $root, $id ) );
		}
	}

	/** La lista de componentes que incrusta la página (`Component_Styles`): se recalcula en el siguiente render. */
	public static function invalidate_related( int $id ): void {
		$class = self::VALIDITY_CLASS;
		if ( $id > 0 && class_exists( $class ) ) {
			( new $class() )->invalidate( array( self::key( 'related' ), (string) $id ) );
		}
	}

	/**
	 * Una clave del núcleo: su constante si la clase ya está cargada (no se fuerza su carga: con PHP 8.4+ alguna emite
	 * avisos de obsolescencia al compilarse), si no, la de 4.3.5, que la unitaria comprueba igual a la constante.
	 */
	public static function key( string $name ): string {
		if ( ! isset( self::KEYS[ $name ] ) ) {
			return '';
		}
		if ( ! isset( self::$keys[ $name ] ) ) {
			list( $class, $const, $fallback ) = self::KEYS[ $name ];
			if ( ! class_exists( $class, false ) ) {
				return $fallback;
			}
			self::$keys[ $name ] = defined( $class . '::' . $const ) ? (string) constant( $class . '::' . $const ) : $fallback;
		}
		return self::$keys[ $name ];
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized = false;
		self::$depth       = 0;
		self::$pending     = array();
		self::$frames      = array();
		self::$saved       = array();
		self::$flushing    = false;
		self::$keys        = array();
	}

	/** Para las pruebas: documentos anotados y herramientas en curso. */
	public static function state(): array {
		return array(
			'depth'   => self::$depth,
			'pending' => array_keys( self::$pending ),
			'preview' => class_exists( __NAMESPACE__ . '\\Preview_Link', false ) ? Preview_Link::state()['post'] : 0,
		);
	}

	private static function leave(): void {
		self::$depth = max( 0, self::$depth - 1 );
		if ( 0 === self::$depth ) {
			self::flush();
		}
	}

	private static function is_builder_ability( $name ): bool {
		return is_string( $name ) && 0 === strpos( $name, self::ABILITY_PREFIX );
	}

	private static function is_proxy( $request ): bool {
		return is_object( $request ) && method_exists( $request, 'get_route' ) && self::PROXY_ROUTE === $request->get_route()
			&& ( ! method_exists( $request, 'get_method' ) || 'POST' === $request->get_method() );
	}
}
