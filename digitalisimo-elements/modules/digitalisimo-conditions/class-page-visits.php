<?php
namespace Digitalisimo\Elements\Conditions;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-conditions-guard.php';

/**
 * Páginas visitadas para la condición «Visitó una página» (`digitalisimo_page_visit`, V.4.3).
 *
 * - **Cookie del sitio.** `digi_seen` en un sitio único y `digi_seen_<blog_id>` en una red, con la ruta de
 *   `home_url( '/' )`: en una red de subdirectorios ningún sitio lee ni escribe la lista de otro. Guarda hasta 50 IDs
 *   (`MAX_SEEN`) en base 36 separados por puntos, 30 días, `SameSite=Lax` y `Secure` si el sitio va por HTTPS. Sólo la
 *   escribe un script en línea (menos de 400 B) en las vistas individuales que alguna condición publicada rastrea; el
 *   servidor no escribe ninguna.
 * - **Consentimiento.** El script sólo se imprime con la WP Consent API o con la opción «Registrar visitas sin gestor
 *   de consentimiento» (`page_visits_no_consent`, sitio → red → apagada). Escribe sólo si la API da el consentimiento
 *   `preferences` (sin la API, si la opción lo permite) y escucha sus cambios. Sin escritura, «visitó» no se cumple.
 * - **Índice.** La opción del sitio `digitalisimo_elements_tracked_pages` (carga automática, existe siempre) guarda
 *   `{ docs: { documento: [ids] }, union: [ids] }`, con la unión (≤ 200) calculada al escribir. Sólo cuentan documentos
 *   publicados construidos con el editor (de la biblioteca, sólo las plantillas con condiciones), nunca revisiones,
 *   borradores ni marcadores. No hay meta por documento, así que nada viaja en el WXR de un kit.
 * - **Purga.** Una página que entra en la unión o sale de ella se purga (`clean_post_cache()` y la acción
 *   `litespeed_purge_post` de LiteSpeed Cache), para que su copia en caché gane o pierda el script. Las demás cachés de
 *   página necesitan una purga manual.
 *
 * La condición sólo llama a `seen()`. Esta clase no conoce la condición: sólo su nombre.
 */
final class Page_Visits {
	const COOKIE      = 'digi_seen';
	const OPTION      = 'digitalisimo_elements_tracked_pages';
	const CONDITION   = 'digitalisimo_page_visit';
	const SETTING     = 'page_visits_no_consent';
	const FILTER      = 'digitalisimo_elements_page_visit_tracking';
	const SCRIPT_ID   = 'digitalisimo-page-visit';
	const MAX_SEEN    = 50;
	const MAX_TRACKED = 200;
	const MAX_AGE     = 2592000;
	const PATTERN     = '/^[0-9a-z]{1,8}(\.[0-9a-z]{1,8}){0,49}$/';

	/**
	 * Script del navegador, sin los argumentos: documento, nombre con `=`, ID en base 36, atributos de la cookie (ruta,
	 * 30 días, `SameSite=Lax` y `Secure` si el sitio va por HTTPS) y si se escribe sin la API. Lee la cookie, quita lo
	 * que no es un ID válido y el ID actual, lo pone al final, guarda los 50 más recientes y no pide nada a la red. Sin
	 * comillas simples ni barras invertidas: se lee igual en PHP y en JS (`tests/elements-page-visits.mjs`).
	 */
	const SCRIPT = '((d,n,i,a,o,s=()=>d.cookie=n+[...((d.cookie.match("(^| )"+n+"([^;]+)")||0)[2]||"").split(".").filter(x=>/^[0-9a-z]{1,8}$/.test(x)&&x!=i),i].slice(-50).join(".")+a,k=()=>(self.wp_has_consent||(_=>o))("preferences")&&s())=>{k();d.addEventListener("wp_listen_for_consent_change",k)})';

	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'wp_footer', array( __CLASS__, 'print_script' ), 20 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'on_document_saved' ), 10, 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ), 10, 1 );
		add_action( 'digitalisimo_elements_kit_imported', array( __CLASS__, 'reindex_all' ), 10, 0 );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'init', array( __CLASS__, 'register_cookie_info' ) );
		if ( defined( 'DIGITALISIMO_ELEMENTS_FILE' ) && function_exists( 'plugin_basename' ) ) {
			// La WP Consent API lista los plugins que no declaran que la respetan; este sí.
			add_filter( 'wp_consent_api_registered_' . plugin_basename( DIGITALISIMO_ELEMENTS_FILE ), '__return_true' );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Cookie.

	/** `digi_seen` en un sitio único; `digi_seen_<blog_id>` en una red. */
	public static function cookie_name(): string {
		return function_exists( 'is_multisite' ) && is_multisite() ? self::COOKIE . '_' . (int) get_current_blog_id() : self::COOKIE;
	}

	/** Ruta de `home_url( '/' )` del sitio en curso, con barra final y sólo con caracteres seguros. */
	public static function cookie_path(): string {
		$path = function_exists( 'home_url' ) ? wp_parse_url( home_url( '/' ), PHP_URL_PATH ) : '/';
		$path = trim( preg_replace( '#[^A-Za-z0-9/_.~%-]#', '', is_string( $path ) ? $path : '' ), '/' );
		return '' === $path ? '/' : '/' . $path . '/';
	}

	/** IDs de la cookie del sitio, validados con el patrón estricto; una cookie dañada no da ninguno. */
	public static function seen(): array {
		$name = self::cookie_name();
		$raw  = isset( $_COOKIE[ $name ] ) && is_string( $_COOKIE[ $name ] ) ? (string) wp_unslash( $_COOKIE[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida con PATTERN.
		if ( '' === $raw || ! preg_match( self::PATTERN, $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( explode( '.', $raw ) as $part ) {
			$id = (int) base_convert( $part, 36, 10 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Script.

	public static function print_script(): void {
		$id = self::current();
		if ( $id <= 0 || ! function_exists( 'wp_print_inline_script_tag' ) ) {
			return;
		}
		wp_print_inline_script_tag( self::script( $id ), array( 'id' => self::SCRIPT_ID ) );
	}

	/**
	 * ID de la vista individual que esta petición rastrea, o 0. Nunca en la administración, la página de entradas, la
	 * tienda, los feeds, la vista previa ni el editor; nunca con el filtro `digitalisimo_elements_page_visit_tracking` en
	 * `false`; nunca sin la WP Consent API ni la opción. Un sitio sin el índice no hace ninguna consulta.
	 */
	public static function current(): int {
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || ! is_singular() || is_home() || is_feed() || is_embed() || is_preview() ) {
			return 0;
		}
		if ( ( function_exists( 'is_shop' ) && is_shop() ) || isset( $_GET['elementor-preview'] ) || self::in_editor() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo detecta la vista previa.
			return 0;
		}
		$id = (int) get_queried_object_id();
		if ( $id <= 0 || ! in_array( $id, self::tracked(), true ) ) {
			return 0;
		}
		if ( ! apply_filters( self::FILTER, true, $id ) ) {
			return 0;
		}
		return function_exists( 'wp_has_consent' ) || self::without_consent() ? $id : 0;
	}

	/** El script con sus argumentos para un ID. */
	public static function script( int $id ): string {
		$attrs = ';path=' . self::cookie_path() . ';max-age=' . self::MAX_AGE . ';SameSite=Lax' . ( self::secure() ? ';Secure' : '' );
		return self::SCRIPT . '(document,' . Condition_Groups::json( self::cookie_name() . '=' ) . ',' . Condition_Groups::json( base_convert( (string) $id, 10, 36 ) ) . ',' . Condition_Groups::json( $attrs ) . ',' . ( self::without_consent() ? '!0' : '!1' ) . ')';
	}

	/** ¿Va el sitio por HTTPS? La petición o la dirección del sitio: una copia en caché sirve a las dos. */
	public static function secure(): bool {
		if ( function_exists( 'is_ssl' ) && is_ssl() ) {
			return true;
		}
		return function_exists( 'home_url' ) && 'https' === wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
	}

	/** «Registrar visitas sin gestor de consentimiento» del sitio (sitio → red → apagada). */
	public static function without_consent(): bool {
		$settings = '\\Digitalisimo\\Elements\\Front\\Settings';
		return class_exists( $settings ) && method_exists( $settings, 'get' ) && (bool) call_user_func( array( $settings, 'get' ), self::SETTING );
	}

	private static function in_editor(): bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		$editor = isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode();
		return $editor || ( isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Índice.

	/** Páginas rastreadas del sitio (la unión). `[]` sin leer nada si la opción no está entre las de carga automática. */
	public static function tracked(): array {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( ! is_array( $all ) || ! array_key_exists( self::OPTION, $all ) ) {
			return array();
		}
		return self::stored()['union'];
	}

	/** Opción normalizada: `docs` (documento → IDs) y `union`, sólo enteros positivos y con los topes. */
	public static function stored(): array {
		$raw  = get_option( self::OPTION, array() );
		$raw  = is_array( $raw ) ? $raw : array();
		$docs = array();
		foreach ( isset( $raw['docs'] ) && is_array( $raw['docs'] ) ? $raw['docs'] : array() as $doc => $ids ) {
			$ids = self::ints( $ids );
			if ( (int) $doc > 0 && $ids ) {
				$docs[ (int) $doc ] = $ids;
			}
		}
		return array(
			'docs'  => $docs,
			'union' => self::ints( isset( $raw['union'] ) ? $raw['union'] : array() ),
		);
	}

	/**
	 * Páginas que rastrea un documento: las de cada «Visitó una página» de sus elementos, en `e_display_conditions` y en
	 * el prop v4 (con el transformador de PRO si existe). Los marcadores de K no cuentan: no se pintan.
	 *
	 * @param mixed $document Documento, entrada, ID o lista de elementos.
	 */
	public static function index( $document ): array {
		$ids = array();
		self::collect( self::elements_of( $document ), $ids );
		return array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_TRACKED );
	}

	/** ¿Cuenta este documento para la unión? Publicado, construido con el editor y, en la biblioteca, con condiciones. */
	public static function counts( $post ): bool {
		if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_status, $post->post_type ) ) {
			return false;
		}
		if ( 'publish' !== $post->post_status || 'revision' === $post->post_type ) {
			return false;
		}
		if ( 'builder' !== get_post_meta( (int) $post->ID, '_elementor_edit_mode', true ) ) {
			return false;
		}
		return 'elementor_library' !== $post->post_type || ! empty( get_post_meta( (int) $post->ID, '_elementor_conditions', true ) );
	}

	/** Recalcula la entrada de un documento y, si cambia la unión, la escribe y purga lo que entra o sale. */
	public static function reindex_document( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$post   = get_post( $post_id );
		$stored = self::stored();
		$docs   = $stored['docs'];
		$ids    = $post && self::counts( $post ) ? self::index( $post_id ) : array();
		if ( $ids ) {
			$docs[ $post_id ] = $ids;
		} else {
			unset( $docs[ $post_id ] );
		}
		self::write( $docs, $stored );
	}

	/** Reconstruye el índice entero (tras importar un kit): una consulta por los documentos publicados que nombran la condición. */
	public static function reindex_all(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}
		$sql  = $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE m.meta_key = %s AND p.post_status = %s AND p.post_type <> %s AND m.meta_value LIKE %s",
			'_elementor_data',
			'publish',
			'revision',
			'%' . $wpdb->esc_like( self::CONDITION ) . '%'
		);
		$docs = array();
		foreach ( (array) $wpdb->get_col( $sql ) as $id ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- preparada arriba.
			$id   = (int) $id;
			$post = get_post( $id );
			$ids  = $post && self::counts( $post ) ? self::index( $id ) : array();
			if ( $ids ) {
				$docs[ $id ] = $ids;
			}
		}
		self::write( $docs, self::stored() );
	}

	public static function on_document_saved( $document ): void {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}
		$post = method_exists( $document, 'get_post' ) ? $document->get_post() : null;
		if ( is_object( $post ) && isset( $post->post_type ) && 'revision' === $post->post_type ) {
			return; // Un autoguardado no cambia lo publicado.
		}
		self::reindex_document( (int) $document->get_main_id() );
	}

	/** Al publicar, despublicar o mandar a la papelera. Una actualización sin cambio de estado la recoge `after_save`. */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( $new_status === $old_status || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
			return;
		}
		if ( ! is_object( $post ) || ! isset( $post->ID ) || ( isset( $post->post_type ) && 'revision' === $post->post_type ) ) {
			return;
		}
		self::reindex_document( (int) $post->ID );
	}

	public static function on_deleted( $post_id ): void {
		$stored = self::stored();
		if ( isset( $stored['docs'][ (int) $post_id ] ) ) {
			$docs = $stored['docs'];
			unset( $docs[ (int) $post_id ] );
			self::write( $docs, $stored );
		}
	}

	/** En la administración: crea la opción si falta (existe siempre) y declara las cookies en la política de privacidad. */
	public static function admin_init(): void {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( ! is_array( $all ) || ! array_key_exists( self::OPTION, $all ) ) {
			if ( false === get_option( self::OPTION, false ) ) {
				add_option( self::OPTION, array(), '', true );
			} elseif ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( self::OPTION, true );
			}
		}
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'DIGITALÍSIMO Elements', self::privacy_text() );
		}
	}

	/** Con la WP Consent API: la cookie de visitas, en la categoría `preferences`. */
	public static function register_cookie_info(): void {
		if ( function_exists( 'wp_add_cookie_info' ) ) {
			wp_add_cookie_info( self::cookie_name(), 'DIGITALÍSIMO Elements', 'preferences', '30 días', 'Recordar qué páginas del sitio abrió el visitante para la condición «Visitó una página».', 'Números de páginas del sitio' );
		}
	}

	/**
	 * Texto sugerido para la política de privacidad: el único de Elements (D22). Las cookies de las condiciones y lo que
	 * añaden otros módulos por el filtro `digitalisimo_elements_privacy_items` (la lista de deseos de F4-Woo, V.4.5).
	 */
	public static function privacy_text(): string {
		$items = array(
			'<strong>digi_seen</strong> (en una red, <strong>digi_seen_</strong> seguido del número del sitio): las páginas de este sitio que el visitante abrió, guardadas como números, para la condición «Visitó una página». Dura 30 días y sólo se escribe con el consentimiento de preferencias del visitante (WP Consent API) o si el administrador lo permite sin gestor de consentimiento.',
			'<strong>digi_visitor_tz</strong>: la zona horaria del navegador, para las condiciones por país. Dura 30 días.',
			'<strong>digi_session</strong>: un identificador aleatorio de la sesión del navegador, para contar sesiones. Se borra al cerrar el navegador.',
			'<strong>digi-visit-count-…</strong>: cuántas veces vio el visitante un elemento, para las reglas de visibilidad que lo piden. Se borra al cerrar el navegador.',
			'<strong>_digi_…</strong>: en cuántas sesiones vio el visitante un elemento, para las reglas de visibilidad que lo piden. Dura un año.',
		);
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_privacy_items', $items ) : $items;
		if ( is_array( $filtered ) ) {
			$items = array_values( array_filter( array_map( 'strval', array_filter( $filtered, 'is_scalar' ) ), 'strlen' ) );
		}
		$html  = '<p>DIGITALÍSIMO Elements no envía datos de los visitantes a terceros. Según las funciones que use este sitio, guarda en el navegador del visitante (cookies o almacenamiento local) o en su cuenta los datos que se indican, y sólo cuando la función los necesita:</p><ul><li>' . implode( '</li><li>', $items ) . '</li></ul>';
		$html .= '<p>Las condiciones de visualización deciden qué se muestra, no quién puede leerlo: no protegen contenido.</p>';
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Internos.

	/** Escribe los documentos con su unión si algo cambió y purga las páginas que entran o salen de ella. */
	private static function write( array $docs, array $stored ): void {
		ksort( $docs );
		$union = array();
		foreach ( $docs as $ids ) {
			$union = array_merge( $union, $ids );
		}
		$union = array_slice( array_values( array_unique( $union ) ), 0, self::MAX_TRACKED );
		$next  = array(
			'docs'  => $docs,
			'union' => $union,
		);
		$prev = $stored;
		ksort( $prev['docs'] );
		if ( $next === $prev && self::exists() ) {
			return;
		}
		// Sin documentos rastreados vuelve a ser `[]`, el valor con que se crea: revertir una importación o quitar la
		// última condición deja la opción como estaba, byte a byte.
		update_option( self::OPTION, $docs ? $next : array(), true );
		$changed = array_values( array_unique( array_merge( array_diff( $union, $stored['union'] ), array_diff( $stored['union'], $union ) ) ) );
		foreach ( $changed as $id ) {
			if ( function_exists( 'clean_post_cache' ) ) {
				clean_post_cache( (int) $id );
			}
			do_action( 'litespeed_purge_post', (int) $id );
		}
	}

	private static function exists(): bool {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		return is_array( $all ) && array_key_exists( self::OPTION, $all );
	}

	/** Enteros positivos únicos, con el tope de la unión. */
	private static function ints( $value ): array {
		$out = array();
		foreach ( (array) $value as $item ) {
			$item = is_scalar( $item ) ? (int) $item : 0;
			if ( $item > 0 ) {
				$out[] = $item;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, self::MAX_TRACKED );
	}

	/** @param mixed $document Lista de elementos, documento (`get_main_id()`), entrada o ID. */
	private static function elements_of( $document ): array {
		if ( is_array( $document ) ) {
			return $document;
		}
		$id = 0;
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			$id = (int) $document->get_main_id();
		} elseif ( is_object( $document ) && isset( $document->ID ) ) {
			$id = (int) $document->ID;
		} elseif ( is_numeric( $document ) ) {
			$id = (int) $document;
		}
		if ( $id <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $id, '_elementor_data', true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function collect( array $elements, array &$ids ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || ( isset( $element['widgetType'] ) && Conditions_Guard::MARKER_WIDGET === $element['widgetType'] ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			foreach ( self::groups( $settings ) as $group ) {
				foreach ( (array) $group as $condition ) {
					if ( ! is_array( $condition ) || ! isset( $condition['condition'] ) || self::CONDITION !== $condition['condition'] ) {
						continue;
					}
					foreach ( (array) ( isset( $condition['pages'] ) ? $condition['pages'] : array() ) as $page ) {
						$page = is_array( $page ) ? ( isset( $page['id'] ) ? $page['id'] : 0 ) : $page;
						$page = is_scalar( $page ) ? (int) $page : 0;
						if ( $page > 0 ) {
							$ids[] = $page;
						}
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect( $element['elements'], $ids );
			}
		}
	}

	/** Grupos v3 y v4 de un elemento que nombra la condición; sin ese texto no se decodifica nada. */
	private static function groups( array $settings ): array {
		$groups = array();
		if ( ! empty( $settings[ Condition_Groups::V3 ] ) && false !== strpos( Condition_Groups::json( $settings[ Condition_Groups::V3 ] ), self::CONDITION ) ) {
			$groups = array_merge( $groups, (array) Condition_Groups::read( $settings, Condition_Groups::V3 ) );
		}
		if ( ! empty( $settings[ Condition_Groups::V4 ] ) && false !== strpos( Condition_Groups::json( $settings[ Condition_Groups::V4 ] ), self::CONDITION ) ) {
			$groups = array_merge( $groups, self::v4_groups( $settings ) );
		}
		return $groups;
	}

	/** El prop v4 con el transformador de PRO (`extract_from_settings()`), y si no está o falla, leído aquí. */
	private static function v4_groups( array $settings ): array {
		$transformer = '\\ElementorPro\\Modules\\AtomicWidgets\\Transformers\\Display_Conditions';
		if ( class_exists( $transformer ) && method_exists( $transformer, 'extract_from_settings' ) ) {
			try {
				$json    = call_user_func( array( $transformer, 'extract_from_settings' ), $settings );
				$decoded = is_array( $json ) && isset( $json[0] ) && is_string( $json[0] ) ? json_decode( $json[0], true ) : null;
				if ( is_array( $decoded ) && $decoded ) {
					return array_values( array_filter( $decoded, 'is_array' ) );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- se lee a mano.
			}
		}
		return (array) Condition_Groups::read( $settings, Condition_Groups::V4 );
	}
}
