<?php
defined( 'ABSPATH' ) || exit;

/** Precargas WOFF2 locales, opt-in y limitadas al CSS usado por la página. */
class Digitalisimo_Integrations_Performance_Preloads {
	private static $core_urls = array();

	public static function init() {
		add_filter( 'wp_preload_resources', array( __CLASS__, 'remember_core' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'print_links' ), 7 );
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

	private static function url_key( $url ) {
		return strtok( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ), '?#' );
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
		$fonts = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
		$custom = get_option( Digitalisimo_Integrations_Performance_Migration::OPTION, array() );
		if ( empty( $fonts['scanned_at'] ) || empty( $custom['scanned_at'] ) ) return;
		$already = self::$core_urls;
		foreach ( (array) ( $custom['rows'] ?? array() ) as $row ) if ( 'publish' === ( $row['status'] ?? '' ) ) foreach ( (array) ( $row['preloads'] ?? array() ) as $url ) $already[] = self::url_key( $url );
		$permitted = Digitalisimo_Integrations_Performance_Font_Guard::permitted_urls( $fonts );
		$urls = self::candidates( $fonts, $mode, Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_paths' ), self::enqueued_css(), self::sanitize_limit( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_limit' ) ), $already, $permitted );
		foreach ( $urls as $url ) echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
	}
}
