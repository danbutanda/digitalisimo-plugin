<?php
defined( 'ABSPATH' ) || exit;

/** Precargas WOFF2 locales, opt-in y limitadas al CSS usado por la página. */
class Digitalisimo_Integrations_Performance_Preloads {
	const OPTION = 'digitalisimo_performance_preload_manifest';
	const CRON = 'digitalisimo_performance_rebuild_preloads';
	private static $core_urls = array();

	public static function init() {
		if ( function_exists( 'wp_preload_resources' ) && version_compare( get_bloginfo( 'version' ), '6.6', '>=' ) ) add_filter( 'wp_preload_resources', array( __CLASS__, 'inject_resources' ), 999 );
		else add_action( 'wp_head', array( __CLASS__, 'print_links' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'bootstrap' ), 30 );
		add_action( 'init', array( __CLASS__, 'schedule_missing' ), 30 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_status_route' ) );
		add_action( self::CRON, array( __CLASS__, 'rebuild' ) );
		add_action( 'save_post_elementor_library', array( __CLASS__, 'schedule' ) );
		add_action( 'elementor/core/files/clear_cache', array( __CLASS__, 'schedule' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'schedule_for_meta' ), 20, 4 );
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule' ) );
		add_action( 'update_option_digitalisimo_seo_network_inherit', array( __CLASS__, 'schedule' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Performance_Cache::GENERATION, array( __CLASS__, 'schedule' ) );
		if ( is_multisite() ) add_action( 'update_site_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule_network' ) );
	}

	public static function sanitize_mode( $value ) {
		return in_array( $value, array( 'off', 'auto', 'manual' ), true ) ? $value : 'off';
	}

	public static function sanitize_limit( $value ) {
		return min( 2, max( 1, absint( $value ) ) );
	}

	/** Las rutas son relativas a uploads del sitio, para que un default de red sea portable. */
	public static function sanitize_paths( $value ) {
		$paths = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$path = trim( $line );
			if ( preg_match( '~^elementor/google-fonts/fonts/[a-zA-Z0-9._/-]+\.woff2$~', $path ) && false === strpos( $path, '..' ) ) $paths[ $path ] = true;
			if ( count( $paths ) >= 20 ) break;
		}
		return implode( "\n", array_keys( $paths ) );
	}

	public static function remember_core( $resources ) {
		foreach ( (array) $resources as $resource ) if ( is_array( $resource ) && ! empty( $resource['href'] ) ) self::$core_urls[] = self::url_key( $resource['href'] );
		return $resources;
	}

	/** El core imprime este filtro al comienzo de wp_head y evita URLs repetidas. */
	public static function inject_resources( $resources ) {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() ) return $resources;
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		$manifest = get_option( self::OPTION, array() );
		if ( 'off' === $mode || $mode !== ( $manifest['mode'] ?? '' ) ) return $resources;
		self::remember_core( $resources );
		$seen = array_fill_keys( self::$core_urls, true );
		foreach ( (array) ( $manifest['rows'] ?? array() ) as $row ) {
			$url = (string) ( $row['url'] ?? '' );
			$key = self::url_key( $url );
			if ( ! $url || isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$resources[] = array( 'href' => $url, 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous', 'fetchpriority' => 'high' );
		}
		return $resources;
	}

	private static function url_key( $url ) {
		return strtok( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ), '?#' );
	}

	public static function schedule_for_meta( $meta_id, $post_id, $key, $value ) {
		if ( in_array( $key, array( '_elementor_data', '_elementor_page_settings', '_elementor_edit_mode' ), true ) ) self::schedule();
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 10, self::CRON );
	}

	public static function schedule_network() {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
			switch_to_blog( (int) $site_id );
			try { self::schedule(); } finally { restore_current_blog(); }
		}
	}

	public static function bootstrap() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$manifest = get_option( self::OPTION, array() );
		if ( 2 !== ( $manifest['schema'] ?? 0 ) && 'off' !== self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) ) ) self::rebuild();
	}

	/** Una actualización automática no ejecuta admin_init: sólo agenda el trabajo, nunca escanea en frontend. */
	public static function schedule_missing() {
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		if ( 'off' === $mode ) return;
		$manifest = get_option( self::OPTION, array() );
		if ( 2 !== ( $manifest['schema'] ?? 0 ) || $mode !== ( $manifest['mode'] ?? '' ) ) self::schedule();
	}

	/** Estado de sólo lectura para diagnosticar instalaciones automáticas sin publicar opciones en HTML. */
	public static function register_status_route() {
		register_rest_route( 'digitalisimo-seo/v1', '/performance/preloads', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'status_response' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
	) );
	}

	public static function status_response() {
		$manifest = get_option( self::OPTION, array() );
		$inventory = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
		return array(
			'site_id' => get_current_blog_id(),
			'mode' => self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) ),
			'limit' => self::sanitize_limit( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_limit' ) ),
			'manifest_schema' => (int) ( $manifest['schema'] ?? 0 ),
			'manifest_mode' => (string) ( $manifest['mode'] ?? '' ),
			'manifest_generated_at' => (string) ( $manifest['generated_at'] ?? '' ),
			'rows' => (array) ( $manifest['rows'] ?? array() ),
			'inventory_scanned_at' => (string) ( $inventory['scanned_at'] ?? '' ),
			'kit_id' => (int) ( $inventory['kit_id'] ?? 0 ),
			'families' => array_values( array_filter( array_map( function( $row ) { return (string) ( $row['family'] ?? '' ); }, (array) ( $inventory['families'] ?? array() ) ) ) ),
			'faces_count' => count( (array) ( $inventory['faces'] ?? array() ) ),
			'next_rebuild' => wp_next_scheduled( self::CRON ) ?: 0,
		);
	}

	/** Se calcula en administración o cron; un MISS de Redis no afecta el HTML. */
	public static function rebuild() {
		$report = Digitalisimo_Integrations_Performance_Fonts::scan();
		if ( ! is_array( $report ) || empty( $report['scanned_at'] ) ) return false;
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		$base = (string) ( $report['uploads_baseurl'] ?? '' );
		$families = array();
		foreach ( (array) ( $report['families'] ?? array() ) as $family ) $families[ strtolower( (string) ( $family['family'] ?? '' ) ) ] = true;
		$manual = array_fill_keys( explode( "\n", self::sanitize_paths( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_paths' ) ) ), true );
		$permitted = Digitalisimo_Integrations_Performance_Font_Guard::permitted_urls( $report );
		$selected = array();
		foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
			$url = (string) ( $face['url'] ?? '' );
			if ( 'off' === $mode || ! $base || 0 !== strpos( $url, $base ) || ! preg_match( '~^elementor/google-fonts/fonts/[a-zA-Z0-9._/-]+\.woff2$~', substr( $url, strlen( $base ) ) ) || empty( $permitted[ $url ] ) ) continue;
			$family = strtolower( (string) ( $face['family'] ?? '' ) );
			if ( 'auto' === $mode && ( empty( $families[ $family ] ) || ! in_array( strtolower( (string) ( $face['weight'] ?? '' ) ), array( '400', 'normal' ), true ) || 'italic' === strtolower( (string) ( $face['style'] ?? '' ) ) ) ) continue;
			if ( 'manual' === $mode && empty( $manual[ substr( $url, strlen( $base ) ) ] ) ) continue;
			$range = strtoupper( (string) ( $face['unicode_range'] ?? '' ) );
			$score = false !== strpos( $range, 'U+0000-00FF' ) ? 2 : ( '' === $range ? 1 : 0 );
			$key = 'auto' === $mode ? $family : $url;
			if ( ! isset( $selected[ $key ] ) || $score > $selected[ $key ]['score'] ) $selected[ $key ] = array( 'url' => $url, 'family' => $face['family'] ?? '', 'variant' => trim( (string) ( $face['weight'] ?? '' ) . ' ' . (string) ( $face['style'] ?? '' ) ), 'source' => 'auto' === $mode ? 'Kit Elementor + CSS local' : 'Ruta manual + CSS local', 'css' => $face['css'] ?? '', 'score' => $score );
		}
		$rows = array(); $seen = array();
		foreach ( $selected as $row ) {
			if ( isset( $seen[ $row['url'] ] ) ) continue;
			$seen[ $row['url'] ] = true;
			unset( $row['score'] );
			$rows[] = $row;
			if ( count( $rows ) >= self::sanitize_limit( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_limit' ) ) ) break;
		}
		$manifest = array( 'schema' => 2, 'mode' => $mode, 'rows' => $rows, 'generated_at' => current_time( 'mysql' ) );
		update_option( self::OPTION, $manifest, false );
		return $manifest;
	}

	private static function enqueued_css() {
		$styles = wp_styles();
		$files = array();
		if ( ! $styles ) return $files;
		foreach ( (array) $styles->queue as $handle ) {
			$src = $styles->registered[ $handle ]->src ?? '';
			if ( preg_match( '~(?:^|/)elementor/google-fonts/css/([a-zA-Z0-9._-]+\.css)(?:\?|$)~', (string) $src, $match ) ) $files[ $match[1] ] = true;
		}
		return $files;
	}

	/** Sólo datos precalculados; no consulta Elementor ni el filesystem en frontend. */
	public static function candidates( $report, $mode, $paths, $queued, $limit, $already, $permitted = null ) {
		$families = array();
		foreach ( (array) ( $report['families'] ?? array() ) as $family ) $families[ strtolower( (string) ( $family['family'] ?? '' ) ) ] = true;
		$manual = array_fill_keys( preg_split( '/\r\n|\r|\n/', trim( (string) $paths ) ), true );
		$base = (string) ( $report['uploads_baseurl'] ?? '' );
		if ( ! $base ) return array();
		$result = array();
		foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
			$url = (string) ( $face['url'] ?? '' );
			if ( null !== $permitted && empty( $permitted[ $url ] ) ) continue;
			if ( ! $url || 0 !== strpos( $url, $base ) || ! preg_match( '~^elementor/google-fonts/fonts/[a-zA-Z0-9._/-]+\.woff2$~', substr( $url, strlen( $base ) ) ) ) continue;
			if ( empty( $queued[ $face['css'] ?? '' ] ) || in_array( self::url_key( $url ), $already, true ) ) continue;
			$path = substr( $url, strlen( $base ) );
			if ( 'manual' === $mode && empty( $manual[ $path ] ) ) continue;
			if ( 'auto' === $mode && ( empty( $families[ strtolower( (string) ( $face['family'] ?? '' ) ) ] ) || ! in_array( (string) ( $face['weight'] ?? '' ), array( '400', 'normal' ), true ) || 'italic' === ( $face['style'] ?? '' ) ) ) continue;
			$result[] = $url;
			$already[] = self::url_key( $url );
			if ( count( $result ) >= $limit ) break;
		}
		return $result;
	}

	public static function print_links() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() ) return;
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		if ( 'off' === $mode ) return;
		$manifest = get_option( self::OPTION, array() );
		if ( $mode !== ( $manifest['mode'] ?? '' ) ) return;
		$already = array_fill_keys( self::$core_urls, true );
		foreach ( (array) ( $manifest['rows'] ?? array() ) as $row ) {
			$url = (string) ( $row['url'] ?? '' );
			$key = self::url_key( $url );
			if ( ! $url || isset( $already[ $key ] ) ) continue;
			$already[ $key ] = true;
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="font/woff2" crossorigin fetchpriority="high">' . "\n";
		}
	}
}
