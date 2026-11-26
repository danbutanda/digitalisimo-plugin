<?php
defined( 'ABSPATH' ) || exit;

/**
 * Caché del lado del sitio para las lecturas del Asistente IA (docs/ASSISTANT.md, «Caché»). Pedido del propietario
 * (2026-11-26): «agrega caché para optimizar recursos de tokens».
 *
 * Las lecturas que se repiten en la misma conversación o en pocos minutos y que no cambian solas (el contacto y la
 * identidad del sitio, la guía y las plantillas del kit de ventas, las categorías del catálogo, los ajustes de la
 * tienda, los esquemas y estilos del constructor, el texto de una página) se guardan TTL segundos en un transitorio DEL
 * SITIO, por persona (lo que devuelve depende de sus permisos) y con la «generación» del sitio en la clave: cualquier
 * cambio de contenido, de términos o de los ajustes que se leen sube la generación y lo guardado deja de usarse. Así no
 * se recalculan (y, con la caché de la API, el resultado idéntico tampoco rompe el prefijo de la conversación).
 *
 * Precios y existencias nunca: la herramienta de productos (y cualquier otra fuera de CACHEABLE) se consulta en vivo.
 * Sólo se guarda un resultado `ok` de una lectura (`read`), de hasta MAX_BYTES.
 */
final class Digitalisimo_AI_Assistant_Cache {
	const GEN       = 'digitalisimo_ai_assistant_cache_gen';
	const PREFIX    = 'dgac_';
	const TTL       = 300;
	const MAX_BYTES = 200000;
	/** Lecturas que se pueden guardar unos minutos (filtro `digitalisimo_ai_assistant_cacheable_tools`). */
	const CACHEABLE = array(
		'digitalisimo-ia/site-contact',
		'digitalisimo-ia/read-page-text',
		'digitalisimo-sales/guide',
		'digitalisimo-sales/list-templates',
		'digitalisimo-woo/catalog',
		'digitalisimo-woo/store-settings',
		'elementor/list-site-parts',
		'elementor/get-default-styles',
		'elementor/list-widget-schemas',
		'elementor/get-widget-schema',
		'elementor/list-components',
	);
	/** Nunca: precios y existencias se verifican en vivo. */
	const NEVER = array( 'digitalisimo-woo/products', 'digitalisimo-woo/orders', 'digitalisimo-woo/customers', 'digitalisimo-woo/reports', 'digitalisimo-woo/coupons' );

	public static function init() {
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'edited_term', 'created_term', 'delete_term', 'wp_update_nav_menu', 'switch_theme', 'elementor/core/files/clear_cache' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_change' ), 10, 1 );
		}
		add_action( 'updated_option', array( __CLASS__, 'on_option' ), 10, 1 );
	}

	/** ¿Esta herramienta se puede guardar unos minutos? */
	public static function cacheable( $ability ) {
		$ability = (string) $ability;
		if ( in_array( $ability, self::NEVER, true ) ) {
			return false;
		}
		return in_array( $ability, (array) apply_filters( 'digitalisimo_ai_assistant_cacheable_tools', self::CACHEABLE ), true );
	}

	/** La generación del sitio: cambia con cualquier cambio de contenido o de los ajustes que se leen. */
	public static function generation() {
		return (string) get_option( self::GEN, '0' );
	}

	public static function bump() {
		update_option( self::GEN, substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 ), false );
	}

	/** Un cambio de contenido (menos las revisiones, los autoguardados y las conversaciones del Asistente). */
	public static function on_change( $id = 0 ) {
		$post = is_numeric( $id ) && (int) $id > 0 && function_exists( 'get_post' ) ? get_post( (int) $id ) : null;
		if ( $post && in_array( (string) ( $post->post_type ?? '' ), array( 'revision', 'digitalisimo_ai_chat', 'customize_changeset', 'oembed_cache' ), true ) ) {
			return;
		}
		self::bump();
	}

	/** Ajustes que cambian lo que leen las herramientas guardadas (nunca los transitorios ni la propia generación). */
	public static function on_option( $option ) {
		$option = (string) $option;
		if ( preg_match( '/^(blogname|blogdescription|home|siteurl|page_on_front|show_on_front|sidebars_widgets|widget_[a-z0-9_-]+|theme_mods_[a-z0-9_-]+|site_logo|site_icon|elementor_active_kit|elementor_[a-z_]*conditions|woocommerce_(?!.*_transient)[a-z0-9_]+|digitalisimo_seo[a-z0-9_]*|digitalisimo_elements_[a-z0-9_]+)$/', $option ) ) {
			self::bump();
		}
	}

	private static function key( $ability, $input ) {
		$input = is_array( $input ) ? self::sorted( $input ) : $input;
		return self::PREFIX . md5( self::generation() . '|' . (int) get_current_user_id() . '|' . (string) $ability . '|' . wp_json_encode( $input ) );
	}

	/** La entrada con sus claves ordenadas: la misma consulta escrita en otro orden es la misma consulta. */
	private static function sorted( array $value ) {
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		foreach ( $value as $k => $v ) {
			if ( is_array( $v ) ) {
				$value[ $k ] = self::sorted( $v );
			}
		}
		return $value;
	}

	/**
	 * Una lectura: si es de las que se guardan y está guardada (misma persona, misma entrada, el sitio sin cambios), se
	 * devuelve lo guardado; si no, se ejecuta (Digitalisimo_AI_Assistant_Tools::run()) y se guarda.
	 */
	public static function run( array $entry, $input ) {
		$name = (string) $entry['name'];
		if ( ! self::cacheable( $name ) || ! (bool) apply_filters( 'digitalisimo_ai_assistant_site_cache', true, $name ) ) {
			return Digitalisimo_AI_Assistant_Tools::run( $entry, $input );
		}
		$key  = self::key( $name, $input );
		$hit  = get_transient( $key );
		if ( is_array( $hit ) && 'ok' === ( $hit['status'] ?? '' ) ) {
			$hit['cached'] = true;
			return $hit;
		}
		$result = Digitalisimo_AI_Assistant_Tools::run( $entry, $input );
		if ( 'ok' === ( $result['status'] ?? '' ) && strlen( (string) wp_json_encode( $result ) ) <= self::MAX_BYTES ) {
			set_transient( $key, $result, max( 30, (int) apply_filters( 'digitalisimo_ai_assistant_site_cache_ttl', self::TTL, $name ) ) );
		}
		return $result;
	}
}
