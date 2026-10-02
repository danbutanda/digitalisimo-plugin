<?php
defined( 'ABSPATH' ) || exit;

/** Evidencia CSSOM del navegador, limitada al sitio, página y viewport exactos. */
class Digitalisimo_Integrations_Performance_CSS_Audit {
	const OPTION = 'digitalisimo_performance_css_audit';
	const SCHEMA = 1;
	const TTL = 3600;
	private static $page = null;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 120 );
		add_filter( 'wp_headers', array( __CLASS__, 'vary_cookie' ) );
		add_action( 'wp_ajax_nopriv_digitalisimo_css_audit', array( __CLASS__, 'receive' ) );
		add_action( 'wp_ajax_digitalisimo_css_audit', array( __CLASS__, 'receive' ) );
		add_action( 'save_post', array( __CLASS__, 'invalidate' ) );
		add_action( 'deleted_post', array( __CLASS__, 'invalidate' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'meta_changed' ), 20, 4 );
		add_action( 'switch_theme', array( __CLASS__, 'invalidate' ) );
		add_action( 'elementor/core/files/clear_cache', array( __CLASS__, 'invalidate' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'invalidate' ) );
		add_action( 'customize_save_after', array( __CLASS__, 'invalidate' ) );
		add_action( 'updated_option', array( __CLASS__, 'option_changed' ), 10, 1 );
		add_action( 'update_option_digitalisimo_seo_network_inherit', array( __CLASS__, 'invalidate' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'invalidate' ) );
		if ( is_multisite() ) add_action( 'update_site_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'invalidate_network' ) );
	}

	public static function invalidate() {
		delete_option( self::OPTION );
	}

	public static function meta_changed( $meta_id, $post_id, $key, $value ) {
		if ( 0 === strpos( (string) $key, '_elementor_' ) ) self::invalidate();
	}

	public static function option_changed( $name ) {
		if ( 0 === strpos( (string) $name, 'theme_mods_' ) || 0 === strpos( (string) $name, 'elementor_' ) ) self::invalidate();
	}

	public static function invalidate_network() {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try { self::invalidate(); } finally { restore_current_blog(); }
		}
	}

	private static function key_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || 'publish' !== $post->post_status || false !== strpos( $post->post_content, '[' ) ) return '';
		$data = (string) get_post_meta( $post_id, '_elementor_data', true );
		if ( false !== strpos( $data, '__dynamic__' ) || false !== strpos( $data, 'shortcode' ) ) return '';
		$url = get_permalink( $post_id );
		return $url ? hash( 'sha256', get_current_blog_id() . '|' . $url ) : '';
	}

	/** Sólo páginas singulares estáticas: archivos, parámetros y contenido dinámico son ambiguos. */
	public static function page() {
		if ( null !== self::$page ) return self::$page;
		self::$page = '';
		if ( ! is_singular() || ! empty( $_GET ) || is_user_logged_in() ) return '';
		$post_id = get_queried_object_id();
		self::$page = $post_id ? self::key_for_post( $post_id ) : '';
		return self::$page;
	}

	public static function view( $raw ) {
		if ( ! preg_match( '/^([0-9]{3,4})\.([0-9]{3,4})$/', (string) $raw, $matches ) ) return '';
		$width = (int) $matches[1]; $height = (int) $matches[2];
		return $width >= 240 && $height >= 240 && $width <= 4096 && $height <= 4096 ? $width . '.' . $height : '';
	}

	/** Firma barata del archivo local; si no puede mapearse con seguridad, se mantiene bloqueante. */
	public static function resource_signature( $url ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) return '';
		$root = realpath( WP_CONTENT_DIR );
		$prefix = rtrim( (string) wp_parse_url( content_url(), PHP_URL_PATH ), '/' ) . '/';
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $root || 0 !== strpos( $path, $prefix ) ) return '';
		$relative = rawurldecode( substr( $path, strlen( $prefix ) ) );
		if ( false !== strpos( $relative, '..' ) || false !== strpos( $relative, "\0" ) ) return '';
		$file = realpath( $root . '/' . $relative );
		if ( ! $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) return '';
		clearstatcache( true, $file );
		return (string) filemtime( $file ) . ':' . (string) filesize( $file );
	}

	public static function enqueue() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' ) || ! self::page() ) return;
		wp_enqueue_script( 'digitalisimo-css-audit', DIGITALISIMO_INTEGRATIONS_URL . 'assets/js/performance-css-audit.js', array(), DIGITALISIMO_INTEGRATIONS_VERSION, true );
		wp_add_inline_script( 'digitalisimo-css-audit', 'window.digitalisimoCssAudit=' . wp_json_encode( array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'digitalisimo_css_audit' ), 'page' => self::page() ) ) . ';', 'before' );
	}

	/** El HTML varía según el viewport medido en cookie; una caché HTTP debe separarlo. */
	public static function vary_cookie( $headers ) {
		if ( Digitalisimo_Integrations_Performance_Manager::frontend_safe() && Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' ) && self::page() ) {
			$vary = (string) ( $headers['Vary'] ?? '' );
			if ( ! preg_match( '/(?:^|,)\s*Cookie\s*(?:,|$)/i', $vary ) ) $headers['Vary'] = $vary ? $vary . ', Cookie' : 'Cookie';
		}
		return $headers;
	}

	public static function allowed( $handle, $href ) {
		if ( ! self::page() || ! self::view( $_COOKIE['digitalisimo_css_view'] ?? '' ) ) return false;
		$cache = (array) get_option( self::OPTION, array() );
		$row = $cache['pages'][ self::page() ][ self::view( $_COOKIE['digitalisimo_css_view'] ) ][ $handle ] ?? array();
		$signature = self::resource_signature( $href );
		return self::SCHEMA === ( $cache['schema'] ?? 0 ) && (int) get_current_blog_id() === (int) ( $cache['blog_id'] ?? 0 ) && (int) ( $row['passes'] ?? 0 ) >= 2 && ( $row['href'] ?? '' ) === esc_url_raw( $href ) && $signature && ( $row['signature'] ?? '' ) === $signature && time() - (int) ( $row['time'] ?? 0 ) < self::TTL;
	}

	/** Un reporte sólo habilita estilos ya encolados para esta página; los demás permanecen bloqueantes. */
	public static function receive() {
		if ( ! check_ajax_referer( 'digitalisimo_css_audit', 'nonce', false ) || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' ) ) wp_send_json_error( 'No autorizado.', 403 );
		$page = sanitize_text_field( is_scalar( $_POST['page'] ?? null ) ? (string) wp_unslash( $_POST['page'] ) : '' );
		$view = self::view( is_scalar( $_POST['view'] ?? null ) ? (string) wp_unslash( $_POST['view'] ) : '' );
		$referer = wp_get_referer();
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$post_id = $referer ? url_to_postid( $referer ) : 0;
		if ( ! $post_id && $referer && untrailingslashit( $referer ) === untrailingslashit( home_url( '/' ) ) ) $post_id = (int) get_option( 'page_on_front' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $page ) || ! $view || ! $referer || wp_parse_url( $referer, PHP_URL_HOST ) !== $home || ! $post_id || self::key_for_post( $post_id ) !== $page ) wp_send_json_error( 'Origen inválido.', 400 );
		$raw_rows = is_string( $_POST['rows'] ?? null ) ? wp_unslash( $_POST['rows'] ) : '';
		if ( strlen( $raw_rows ) > 60000 ) wp_send_json_error( 'Datos inválidos.', 400 );
		$input = json_decode( $raw_rows, true );
		if ( ! is_array( $input ) || count( $input ) > 100 ) wp_send_json_error( 'Datos inválidos.', 400 );
		$rows = array();
		foreach ( $input as $item ) {
			if ( ! is_array( $item ) ) continue;
			if ( ! is_string( $item['handle'] ?? null ) || ! is_string( $item['href'] ?? null ) ) continue;
			$handle = sanitize_key( $item['handle'] );
			$href = esc_url_raw( $item['href'] );
			if ( ! $handle || strlen( $handle ) > 100 || ! $href || strlen( $href ) > 500 || wp_parse_url( $href, PHP_URL_HOST ) !== $home ) continue;
			$signature = self::resource_signature( $href );
			if ( ! $signature ) continue;
			$rows[ $handle ] = array( 'href' => $href, 'signature' => $signature, 'safe' => ! empty( $item['safe'] ) ? 1 : 0, 'time' => time() );
		}
		$cache = (array) get_option( self::OPTION, array() );
		if ( self::SCHEMA !== ( $cache['schema'] ?? 0 ) || get_current_blog_id() !== ( $cache['blog_id'] ?? 0 ) ) $cache = array( 'schema' => self::SCHEMA, 'blog_id' => get_current_blog_id(), 'pages' => array() );
		$previous = $cache['pages'][ $page ][ $view ] ?? array();
		foreach ( $rows as $handle => &$row ) {
			$old = $previous[ $handle ] ?? array();
			$row['passes'] = $row['safe'] && ! empty( $old['safe'] ) && ( $old['href'] ?? '' ) === $row['href'] && ( $old['signature'] ?? '' ) === $row['signature'] && time() - (int) ( $old['time'] ?? 0 ) < self::TTL ? min( 2, 1 + (int) ( $old['passes'] ?? 1 ) ) : ( $row['safe'] ? 1 : 0 );
		}
		unset( $row );
		$cache['pages'][ $page ][ $view ] = array_merge( $previous, $rows );
		$widget_types = json_decode( (string) wp_unslash( $_POST['critical_widgets'] ?? '[]' ), true );
		$template_types = json_decode( (string) wp_unslash( $_POST['templates'] ?? '[]' ), true );
		$valid_types = function( $list, $limit ) {
			$types = array();
			foreach ( is_array( $list ) ? $list : array() as $type ) if ( is_string( $type ) && strlen( $type ) <= 100 ) $types[] = sanitize_key( $type );
			return array_slice( array_values( array_unique( array_filter( $types ) ) ), 0, $limit );
		};
		$cache['pages'][ $page ][ $view ]['_context'] = array( 'critical_widgets' => $valid_types( $widget_types, 100 ), 'templates' => $valid_types( $template_types, 20 ) );
		if ( count( $cache['pages'][ $page ] ) > 8 ) $cache['pages'][ $page ] = array_slice( $cache['pages'][ $page ], -8, null, true );
		if ( count( $cache['pages'] ) > 40 ) $cache['pages'] = array_slice( $cache['pages'], -40, null, true );
		update_option( self::OPTION, $cache, false );
		wp_send_json_success();
	}
}
