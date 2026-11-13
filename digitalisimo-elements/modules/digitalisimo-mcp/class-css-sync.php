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
 * Y en cada apertura de un enlace firmado válido de `create-preview-link` (una instantánea que el núcleo pinta con las
 * hojas y la caché HTML de la página), lo mismo antes de pintar (la instantánea sale con su propio CSS) y al terminar
 * (la página no se queda con el CSS ni el HTML de la instantánea).
 * Lo que cambia el kit (clases globales, variables, estilos predeterminados) ya lo regenera el núcleo.
 */
final class Css_Sync {
	const ABILITY_PREFIX = 'elementor/';
	const PROXY_ROUTE    = '/elementor/v1/mcp-proxy';
	const DATA_META      = '_elementor_data';
	const HTML_CACHE     = '_elementor_element_cache';
	const STYLE_ROOTS    = array( 'local', 'global' );
	const RELATED_ROOT   = 'component-styles-related-posts';
	const VALIDITY_CLASS = 'Elementor\\Modules\\AtomicWidgets\\Styles\\CacheValidity\\Cache_Validity';
	const TOKEN_CLASS    = 'Elementor\\Modules\\Mcp\\Preview\\Preview_Token';
	const TOKEN_ARG      = 'elementor_preview_token';

	/** @var bool */
	private static $initialized = false;

	/** @var int Herramientas del constructor en curso (anidadas) en esta petición. */
	private static $depth = 0;

	/** @var array<int, true> Documentos vivos que cambiaron y aún no se refrescaron. */
	private static $pending = array();

	/** @var int Página de un enlace firmado abierto en esta petición. */
	private static $preview = 0;

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'wp_before_execute_ability', array( __CLASS__, 'before_ability' ), 10, 1 );
		add_action( 'wp_after_execute_ability', array( __CLASS__, 'after_ability' ), 10, 1 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_rest' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_rest' ), 10, 3 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'document_saved' ), 100, 1 );
		add_action( 'added_post_meta', array( __CLASS__, 'meta_written' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'meta_written' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
		add_action( 'parse_request', array( __CLASS__, 'preview_request' ), 2 );
		add_action( 'shutdown', array( __CLASS__, 'shutdown' ), 0 );
	}

	/** `wp_before_execute_ability` (Abilities API de WordPress 6.9): empieza una herramienta del constructor. */
	public static function before_ability( $name ): void {
		if ( self::is_builder_ability( $name ) ) {
			++self::$depth;
		}
	}

	/** `wp_after_execute_ability`: termina bien; al cerrar la última se refresca lo anotado. */
	public static function after_ability( $name ): void {
		if ( self::is_builder_ability( $name ) ) {
			self::leave();
		}
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
		if ( ! self::$depth || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! method_exists( $document, 'get_post' ) ) {
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
		if ( ! self::$depth || self::DATA_META !== $meta_key ) {
			return;
		}
		$id = (int) $object_id;
		if ( $id > 0 && ! wp_is_post_revision( $id ) ) {
			self::$pending[ $id ] = true;
		}
	}

	/** Un documento que pasa a `publish` (el editor invalida todos los contextos al publicar). */
	public static function status_changed( $new_status, $old_status, $post ): void {
		if ( ! self::$depth || 'publish' !== $new_status || $new_status === $old_status || ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}
		if ( ! wp_is_post_revision( (int) $post->ID ) ) {
			self::$pending[ (int) $post->ID ] = true;
		}
	}

	/** Refresca lo anotado (al cerrar el ámbito y, por si una herramienta falló a medias, al terminar la petición). */
	public static function flush(): void {
		$ids           = array_keys( self::$pending );
		self::$pending = array();
		foreach ( $ids as $id ) {
			self::refresh( (int) $id );
		}
	}

	/**
	 * Lo que el editor deja listo al guardar y publicar un documento: sus estilos locales y de clases globales en todos
	 * los contextos, la lista de componentes que incrusta y su caché HTML. Se regeneran en el siguiente render.
	 */
	public static function refresh( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		self::clear_view( $id );
		$class = self::VALIDITY_CLASS;
		if ( class_exists( $class ) ) {
			( new $class() )->invalidate( array( self::RELATED_ROOT, (string) $id ) );
		}
	}

	/**
	 * `parse_request` 2 (el manejador del núcleo va en 1): un enlace firmado válido pinta una instantánea con las hojas
	 * y la caché HTML de la página; se invalidan antes (la instantánea sale con su CSS) y al terminar.
	 */
	public static function preview_request( $wp = null ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el enlace lleva su propio token firmado.
		$raw = isset( $_GET[ self::TOKEN_ARG ] ) && is_string( $_GET[ self::TOKEN_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_ARG ] ) ) : '';
		if ( '' === $raw ) {
			return;
		}
		$id = self::preview_post( $raw );
		if ( $id > 0 ) {
			self::$preview = $id;
			self::clear_view( $id );
		}
	}

	/** La página de un token válido (firma, vigencia y revisión de esa página, como el núcleo), o 0. */
	public static function preview_post( string $raw ): int {
		$class = self::TOKEN_CLASS;
		if ( ! class_exists( $class ) || ! method_exists( $class, 'decode' ) || ! method_exists( $class, 'secret' ) || ! method_exists( $class, 'is_expired' ) ) {
			return 0;
		}
		$claims = $class::decode( $raw, $class::secret() );
		if ( ! is_array( $claims ) || $class::is_expired( $claims, time() ) ) {
			return 0;
		}
		$id       = isset( $claims['post_id'] ) ? (int) $claims['post_id'] : 0;
		$revision = isset( $claims['revision_id'] ) ? get_post( (int) $claims['revision_id'] ) : null;
		if ( $id <= 0 || ! $revision || 'revision' !== $revision->post_type || (int) $revision->post_parent !== $id ) {
			return 0;
		}
		return $id;
	}

	public static function shutdown(): void {
		self::flush();
		if ( self::$preview ) {
			$id            = self::$preview;
			self::$preview = 0;
			self::clear_view( $id );
		}
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized = false;
		self::$depth       = 0;
		self::$pending     = array();
		self::$preview     = 0;
	}

	/** Para las pruebas: documentos anotados y herramientas en curso. */
	public static function state(): array {
		return array(
			'depth'   => self::$depth,
			'pending' => array_keys( self::$pending ),
			'preview' => self::$preview,
		);
	}

	private static function leave(): void {
		self::$depth = max( 0, self::$depth - 1 );
		if ( 0 === self::$depth ) {
			self::flush();
		}
	}

	/**
	 * Hojas locales y de clases globales de la página (todos los contextos) y su caché HTML. Las hojas se marcan
	 * inválidas sin borrar sus archivos: el siguiente render las reescribe de forma atómica con un `ver` nuevo, y una
	 * respuesta ya enviada que las enlaza (la del propio enlace firmado) no se queda sin ellas.
	 */
	private static function clear_view( int $id ): void {
		$class = self::VALIDITY_CLASS;
		if ( class_exists( $class ) ) {
			$validity = new $class();
			foreach ( self::STYLE_ROOTS as $root ) {
				$validity->invalidate( array( $root, $id ) );
			}
		}
		delete_post_meta( $id, self::HTML_CACHE );
	}

	private static function is_builder_ability( $name ): bool {
		return is_string( $name ) && 0 === strpos( $name, self::ABILITY_PREFIX );
	}

	private static function is_proxy( $request ): bool {
		return is_object( $request ) && method_exists( $request, 'get_route' ) && self::PROXY_ROUTE === $request->get_route()
			&& ( ! method_exists( $request, 'get_method' ) || 'POST' === $request->get_method() );
	}
}
