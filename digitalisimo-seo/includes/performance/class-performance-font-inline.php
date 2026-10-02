<?php
defined( 'ABSPATH' ) || exit;

/** Sustituye sólo dos CSS locales de Elementor tras validar y guardar sus @font-face por sitio. */
class Digitalisimo_Integrations_Performance_Font_Inline {
	const OPTION = 'digitalisimo_performance_font_inline_manifest';
	const CRON = 'digitalisimo_performance_rebuild_font_inline';
	const SCHEMA = 1;
	const TARGETS = array( 'sourceserif4.css', 'inter.css' );
	private static $printed = array();

	public static function init() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'replace_tag' ), 9000, 4 );
		add_action( self::CRON, array( __CLASS__, 'rebuild' ) );
		add_action( 'init', array( __CLASS__, 'schedule_missing' ), 32 );
		add_action( 'elementor/core/files/clear_cache', array( __CLASS__, 'schedule' ) );
		add_action( 'save_post_elementor_library', array( __CLASS__, 'schedule' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'meta_changed' ), 20, 4 );
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule' ) );
		add_action( 'update_option_digitalisimo_seo_network_inherit', array( __CLASS__, 'schedule' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Performance_Cache::GENERATION, array( __CLASS__, 'schedule' ) );
		if ( is_multisite() ) add_action( 'update_site_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule_network' ) );
	}

	public static function schedule_missing() {
		$manifest = get_option( self::OPTION, array() );
		if ( self::SCHEMA !== ( $manifest['schema'] ?? 0 ) ) self::schedule();
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 10, self::CRON );
	}

	public static function schedule_network() {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try { self::schedule(); } finally { restore_current_blog(); }
		}
	}

	public static function meta_changed( $meta_id, $post_id, $key, $value ) {
		if ( in_array( $key, array( '_elementor_data', '_elementor_page_settings', '_elementor_edit_mode' ), true ) ) self::schedule();
	}

	/** Admite URLs que Elementor generó dentro de uploads del sitio, nunca CSS remoto. */
	private static function font_url( $raw, $uploads ) {
		$raw = trim( $raw );
		$base = trailingslashit( (string) ( $uploads['baseurl'] ?? '' ) ) . 'elementor/google-fonts/fonts/';
		if ( 0 === strpos( $raw, '../fonts/' ) ) $name = substr( $raw, strlen( '../fonts/' ) );
		elseif ( 0 === strpos( $raw, $base ) ) $name = substr( $raw, strlen( $base ) );
		else return '';
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+\.woff2$/', $name ) || false !== strpos( $name, '..' ) ) return '';
		$url = $base . $name;
		return preg_match( '~^https?://[a-zA-Z0-9._%:/-]+$~', $url ) ? $url : '';
	}

	/** Devuelve bloques completos sin comentarios ni duplicados exactos; falla ante cualquier otra regla. */
	public static function compile( $css, $uploads ) {
		if ( ! is_string( $css ) || '' === $css || strlen( $css ) > 128 * 1024 || false !== strpos( $css, '<' ) ) return false;
		$clean = preg_replace( '~/\*.*?\*/~s', '', $css );
		if ( ! is_string( $clean ) || ! preg_match_all( '/@font-face\s*\{([^{}]*)\}/i', $clean, $matches, PREG_SET_ORDER ) || count( $matches ) > 256 ) return false;
		$leftovers = preg_replace( '/@font-face\s*\{[^{}]*\}/i', '', $clean );
		if ( ! is_string( $leftovers ) || '' !== trim( $leftovers ) ) return false;
		$blocks = array();
		$family = '';
		foreach ( $matches as $match ) {
			$block = $match[0];
			if ( ! preg_match( '/\bfont-family\s*:\s*([^;]+);/i', $block, $name ) || ! preg_match( '/\bfont-weight\s*:\s*([^;]+);/i', $block ) || ! preg_match( '/\bfont-style\s*:\s*([^;]+);/i', $block ) || ! preg_match( '/\bsrc\s*:/i', $block ) ) return false;
			$current_family = trim( $name[1], " \t\r\n'\"" );
			if ( ! preg_match( '/^[\p{L}\p{N} ._-]{1,80}$/u', $current_family ) || ( $family && strcasecmp( $family, $current_family ) ) ) return false;
			$family = $current_family;
			$urls = 0;
			$invalid = false;
			$block = preg_replace_callback( '/url\s*\(\s*([\'\"]?)([^\'\")]+)\1\s*\)/i', function( $found ) use ( $uploads, &$urls, &$invalid ) {
				++$urls;
				$url = self::font_url( $found[2], $uploads );
				if ( ! $url ) { $invalid = true; return $found[0]; }
				return 'url("' . $url . '")';
			}, $block );
			if ( $invalid || 1 !== $urls || ! is_string( $block ) || preg_match_all( '/url\s*\(/i', $block ) !== 1 ) return false;
			$key = hash( 'sha256', preg_replace( '/\s+/', '', $block ) );
			$blocks[ $key ] = $block;
		}
		if ( ! $blocks ) return false;
		return array( 'css' => implode( "\n", $blocks ), 'faces' => array_keys( $blocks ), 'count' => count( $blocks ) );
	}

	/** Sólo lee archivos locales durante cron o recálculo administrativo. */
	public static function rebuild() {
		$uploads = wp_upload_dir();
		$base = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$directory = $base ? realpath( trailingslashit( $base ) . 'elementor/google-fonts/css' ) : false;
		$manifest = array( 'schema' => self::SCHEMA, 'blog_id' => get_current_blog_id(), 'rows' => array(), 'built_at' => time() );
		if ( ! $base || ! $directory || 0 !== strpos( $directory, trailingslashit( $base ) ) ) { update_option( self::OPTION, $manifest, false ); return $manifest; }
		$candidates = array();
		foreach ( self::TARGETS as $name ) $candidates[] = array( $directory . '/' . $name, trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . $name, $name );
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Font_Guard' ) ) {
			$guard = Digitalisimo_Integrations_Performance_Font_Guard::manifest();
			foreach ( (array) ( $guard['rows'] ?? array() ) as $row ) {
				$name = preg_replace( '/^digitalisimo-[a-f0-9]{16}-/', '', basename( (string) ( $row['generated'] ?? '' ) ) );
				if ( ! empty( $row['valid'] ) && in_array( $name, self::TARGETS, true ) ) $candidates[] = array( $row['generated'], $row['url'] ?? '', $name );
			}
		}
		foreach ( $candidates as $candidate ) {
			list( $path, $url, $name ) = $candidate;
			$real = realpath( $path );
			$expected = trailingslashit( $uploads['baseurl'] ) . 'elementor/google-fonts/css/' . basename( $path );
			if ( ! $real || 0 !== strpos( $real, trailingslashit( $directory ) ) || $url !== $expected || ! is_readable( $real ) || filesize( $real ) > 128 * 1024 ) continue;
			$compiled = self::compile( file_get_contents( $real ), $uploads );
			if ( ! $compiled ) continue;
			$manifest['rows'][ $url ] = array( 'path' => $real, 'name' => $name, 'mtime' => filemtime( $real ), 'size' => filesize( $real ), 'css' => $compiled['css'], 'faces' => $compiled['faces'], 'count' => $compiled['count'] );
		}
		// Si las dos familias se solapan, se conservan ambas hojas externas.
		$groups = array();
		foreach ( $manifest['rows'] as $row ) foreach ( $row['faces'] as $face ) $groups[ $row['name'] ][ $face ] = true;
		if ( count( $groups ) === 2 && array_intersect( array_keys( $groups[ self::TARGETS[0] ] ), array_keys( $groups[ self::TARGETS[1] ] ) ) ) $manifest['rows'] = array();
		update_option( self::OPTION, $manifest, false );
		return $manifest;
	}

	/** La sustitución del tag es atómica: sólo elimina el link después de tener CSS íntegro. */
	public static function replace_tag( $html, $handle, $href, $media ) {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! doing_action( 'wp_head' ) || ! preg_match( '/^elementor-gf-local-(sourceserif4|inter)$/', (string) $handle ) || ! in_array( strtolower( (string) $media ), array( '', 'all', 'screen' ), true ) || ! preg_match( '/\brel=[\'\"]stylesheet[\'\"]/i', $html ) ) return $html;
		$manifest = get_option( self::OPTION, array() );
		$url = strtok( html_entity_decode( (string) $href, ENT_QUOTES, 'UTF-8' ), '?#' );
		$row = $manifest['rows'][ $url ] ?? array();
		if ( self::SCHEMA !== ( $manifest['schema'] ?? 0 ) || (int) get_current_blog_id() !== (int) ( $manifest['blog_id'] ?? 0 ) || ! $row || $row['name'] !== substr( $handle, strlen( 'elementor-gf-local-' ) ) . '.css' ) return $html;
		$path = $row['path'] ?? '';
		if ( ! $path || ! is_file( $path ) || (int) filemtime( $path ) !== (int) ( $row['mtime'] ?? -1 ) || (int) filesize( $path ) !== (int) ( $row['size'] ?? -1 ) || empty( $row['css'] ) ) return $html;
		$key = get_current_blog_id() . ':' . $url;
		if ( isset( self::$printed[ $key ] ) ) return '';
		self::$printed[ $key ] = true;
		return '<style id="' . esc_attr( $handle ) . '-inline">' . $row['css'] . '</style>' . "\n";
	}
}
