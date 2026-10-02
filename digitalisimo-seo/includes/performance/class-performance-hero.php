<?php
defined( 'ABSPATH' ) || exit;

/** CSS del primer H1 de Elementor: se calcula fuera del frontend y se guarda por sitio y página. */
class Digitalisimo_Integrations_Performance_Hero {
	const CRON = 'digitalisimo_performance_rebuild_hero';
	const CRON_ALL = 'digitalisimo_performance_schedule_all_heroes';
	const GENERATION = 'digitalisimo_performance_hero_generation';
	const SCHEMA = 1;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 1 );
		add_action( self::CRON, array( __CLASS__, 'rebuild' ) );
		add_action( self::CRON_ALL, array( __CLASS__, 'schedule_pages' ) );
		add_action( 'save_post_page', array( __CLASS__, 'schedule' ) );
		add_action( 'save_post_elementor_library', array( __CLASS__, 'schedule_all' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'meta_changed' ), 20, 4 );
		add_action( 'elementor/core/files/clear_cache', array( __CLASS__, 'schedule_all' ) );
		add_action( 'update_option_elementor_active_kit', array( __CLASS__, 'schedule_all' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule_all' ) );
		add_action( 'update_option_digitalisimo_seo_network_inherit', array( __CLASS__, 'schedule_all' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Performance_Cache::GENERATION, array( __CLASS__, 'schedule_all' ) );
		if ( is_multisite() ) add_action( 'update_site_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'schedule_network' ) );
		add_action( 'admin_init', array( __CLASS__, 'bootstrap' ), 40 );
	}

	private static function key( $post_id ) { return 'digitalisimo_performance_hero_' . absint( $post_id ); }

	public static function schedule( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_next_scheduled( self::CRON, array( $post_id ) ) ) return;
		delete_option( self::key( $post_id ) );
		wp_schedule_single_event( time() + 10, self::CRON, array( $post_id ) );
	}

	public static function meta_changed( $meta_id, $post_id, $key, $value ) {
		if ( ! in_array( $key, array( '_elementor_data', '_elementor_edit_mode', '_elementor_page_settings' ), true ) ) return;
		if ( 'elementor_library' === get_post_type( $post_id ) ) self::schedule_all();
		elseif ( 'page' === get_post_type( $post_id ) ) self::schedule( $post_id );
	}

	public static function schedule_all() {
		update_option( self::GENERATION, max( 1, (int) get_option( self::GENERATION, 1 ) ) + 1, false );
		if ( ! wp_next_scheduled( self::CRON_ALL ) ) wp_schedule_single_event( time() + 10, self::CRON_ALL );
	}

	/** El recorrido de páginas ocurre únicamente en la cola, nunca durante el guardado. */
	public static function schedule_pages() {
		$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder' ) );
		foreach ( (array) $ids as $id ) self::schedule( $id );
	}

	public static function schedule_network() {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			try { self::schedule_all(); } finally { restore_current_blog(); }
		}
	}

	public static function bootstrap() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( ! get_option( 'digitalisimo_performance_hero_bootstrapped', false ) ) {
			update_option( 'digitalisimo_performance_hero_bootstrapped', self::SCHEMA, false );
			if ( is_multisite() && is_network_admin() ) self::schedule_network();
			else self::schedule_all();
		}
	}

	/** Nunca inspecciona Elementor ni lee archivos en una petición pública. */
	public static function print_css() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_hero_critical' ) || ! is_singular( 'page' ) ) return;
		$id = get_queried_object_id();
		$manifest = get_option( self::key( $id ), array() );
		if ( self::SCHEMA !== ( $manifest['schema'] ?? 0 ) || (int) get_current_blog_id() !== (int) ( $manifest['blog_id'] ?? 0 ) || (int) get_option( self::GENERATION, 1 ) !== (int) ( $manifest['generation'] ?? 0 ) || empty( $manifest['safe'] ) || empty( $manifest['css'] ) ) return;
		$post = get_post( $id );
		if ( ! $post || $post->post_modified_gmt !== ( $manifest['modified'] ?? '' ) ) return;
		echo '<style id="digitalisimo-critical-hero">' . $manifest['css'] . '</style>' . "\n"; // CSS validado al reconstruir.
	}

	public static function rebuild( $post_id ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );
		$manifest = array( 'schema' => self::SCHEMA, 'blog_id' => get_current_blog_id(), 'generation' => (int) get_option( self::GENERATION, 1 ), 'modified' => $post ? $post->post_modified_gmt : '', 'safe' => false, 'css' => '' );
		if ( $post && 'page' === $post->post_type && 'publish' === $post->post_status && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			$elements = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
			$ids = self::detect( $elements );
			if ( $ids ) {
				$uploads = wp_upload_dir();
				$base = trailingslashit( $uploads['basedir'] ?? '' ) . 'elementor/css/';
				$page_css = self::read_css( $base . 'post-' . $post_id . '.css' );
				$kit_id = absint( get_option( 'elementor_active_kit', 0 ) );
				$kit_css = $kit_id ? self::read_css( $base . 'post-' . $kit_id . '.css' ) : '';
				if ( $page_css && $kit_css ) {
					$css = self::compile( $page_css, $kit_css, $post_id, $kit_id, $ids );
					if ( $css ) { $manifest['safe'] = true; $manifest['css'] = $css; }
				}
			}
		}
		update_option( self::key( $post_id ), $manifest, false );
		return $manifest;
	}

	private static function read_css( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 300000 ) return '';
		$css = file_get_contents( $path );
		return is_string( $css ) ? $css : '';
	}

	/** Sólo acepta el H1 como primer widget del primer contenedor; las composiciones ambiguas no se optimizan. */
	public static function detect( $elements ) {
		if ( ! is_array( $elements ) || ! isset( $elements[0] ) || 'container' !== ( $elements[0]['elType'] ?? '' ) ) return array();
		$parent = $elements[0];
		$heading = $parent['elements'][0] ?? array();
		if ( 'widget' !== ( $heading['elType'] ?? '' ) || 'heading' !== ( $heading['widgetType'] ?? '' ) || 'h1' !== ( $heading['settings']['header_size'] ?? '' ) ) return array();
		$all = json_encode( $elements );
		if ( ! is_string( $all ) || preg_match_all( '/"header_size"\s*:\s*"h1"/i', $all ) !== 1 || preg_match( '/(?:<|\\\\u003c)h1\b/i', $all ) ) return array();
		foreach ( array( $parent, $heading ) as $element ) {
			$settings = (array) ( $element['settings'] ?? array() );
			foreach ( array( 'hide_desktop', 'hide_tablet', 'hide_mobile', 'animation', '_animation', 'custom_css' ) as $key ) if ( ! empty( $settings[ $key ] ) ) return array();
		}
		$parent_id = (string) ( $parent['id'] ?? '' );
		$heading_id = (string) ( $heading['id'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{5,10}$/i', $parent_id ) || ! preg_match( '/^[a-f0-9]{5,10}$/i', $heading_id ) ) return array();
		return array( 'parent' => $parent_id, 'heading' => $heading_id );
	}

	private static function declarations( $body, $allowed ) {
		$out = array();
		foreach ( explode( ';', $body ) as $part ) {
			$pair = explode( ':', $part, 2 );
			if ( 2 !== count( $pair ) ) continue;
			$name = strtolower( trim( $pair[0] ) );
			$value = trim( $pair[1] );
			if ( ! in_array( $name, $allowed, true ) || '' === $value || strlen( $value ) > 300 || preg_match( '/[<>{}]|@import|url\s*\(|expression\s*\(/i', $value ) ) continue;
			$out[ $name ] = $value;
		}
		return $out;
	}

	/** Recorre reglas y @media sin copiar declaraciones ajenas al H1. */
	private static function rules( $css, $callback, $media = '' ) {
		$css = preg_replace( '~/\*.*?\*/~s', '', $css );
		$offset = 0;
		$length = strlen( $css );
		while ( $offset < $length && false !== ( $open = strpos( $css, '{', $offset ) ) ) {
			$selector = trim( substr( $css, $offset, $open - $offset ) );
			$depth = 1;
			$quote = '';
			for ( $close = $open + 1; $close < $length && $depth; $close++ ) {
				$char = $css[ $close ];
				if ( $quote ) { if ( $char === $quote && ( 0 === $close || '\\' !== $css[ $close - 1 ] ) ) $quote = ''; continue; }
				if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
				if ( '{' === $char ) $depth++;
				elseif ( '}' === $char ) $depth--;
			}
			if ( $depth ) return;
			$body = substr( $css, $open + 1, $close - $open - 2 );
			if ( 0 === strpos( $selector, '@media' ) && preg_match( '/^@media\s*(\([^{}]+\))$/', $selector, $match ) && '' === $media ) self::rules( $body, $callback, $match[1] );
			elseif ( false === strpos( $selector, '@' ) && false === strpos( $body, '{' ) ) $callback( preg_replace( '/\s+/', ' ', $selector ), $body, $media );
			$offset = $close;
		}
	}

	public static function compile( $page_css, $kit_css, $post_id, $kit_id, $ids ) {
		$prefix = '.elementor-' . absint( $post_id ) . ' .elementor-element.elementor-element-';
		$parent = $prefix . $ids['parent'];
		$widget = $prefix . $ids['heading'];
		$title = $widget . ' .elementor-heading-title';
		$allowed = array(
			$parent => array( '--display', '--flex-direction', '--align-items', '--justify-content', '--content-width', '--width', '--min-height', '--flex-wrap', '--flex-wrap-mobile', '--container-widget-width', '--container-widget-height', '--container-widget-flex-grow', '--container-widget-align-self', '--padding-top', '--padding-right', '--padding-bottom', '--padding-left', '--margin-top', '--margin-right', '--margin-bottom', '--margin-left', 'display', 'width', 'max-width', 'min-height', 'padding', 'margin', 'align-items', 'justify-content', 'flex-direction' ),
			$widget => array( 'text-align', 'width', 'max-width', 'margin', 'padding', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' ),
			$title => array( 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'color', 'text-align', 'margin', 'padding', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' ),
		);
		$rules = array();
		$base_title = array();
		$base_parent = array();
		self::rules( $page_css, function( $selector, $body, $media ) use ( $allowed, $parent, $title, &$rules, &$base_title, &$base_parent ) {
			if ( ! isset( $allowed[ $selector ] ) ) return;
			$declarations = self::declarations( $body, $allowed[ $selector ] );
			if ( ! $declarations ) return;
			if ( '' === $media && $selector === $title ) $base_title = array_merge( $base_title, $declarations );
			if ( '' === $media && $selector === $parent ) $base_parent = array_merge( $base_parent, $declarations );
			$rule = $selector . '{';
			foreach ( $declarations as $name => $value ) $rule .= $name . ':' . $value . ';';
			$rule .= '}';
			$rules[] = $media ? '@media ' . $media . '{' . $rule . '}' : $rule;
		} );
		foreach ( array( 'font-family', 'font-size', 'font-weight', 'line-height', 'color' ) as $property ) if ( empty( $base_title[ $property ] ) ) return '';
		if ( empty( $base_parent['--display'] ) && empty( $base_parent['display'] ) ) return '';
		if ( ! $rules ) return '';
		$css = implode( '', array_unique( $rules ) );
		preg_match_all( '/var\(\s*(--e-global-[a-z0-9-]+)\s*(?:,|\))/i', $css, $matches );
		$vars = array_unique( $matches[1] );
		if ( $vars ) {
			$root = array();
			self::rules( $kit_css, function( $selector, $body, $media ) use ( $kit_id, &$root ) {
				if ( '' === $media && '.elementor-kit-' . absint( $kit_id ) === $selector ) {
					preg_match_all( '/(--e-global-[a-z0-9-]+)\s*:\s*([^;{}]+);?/i', $body, $found, PREG_SET_ORDER );
					foreach ( $found as $entry ) $root[ $entry[1] ] = trim( $entry[2] );
				}
			} );
			$declarations = '';
			foreach ( $vars as $name ) {
				$value = $root[ $name ] ?? '';
				if ( '' === $value || strlen( $value ) > 200 || preg_match( '/[<>{}]|url\s*\(|var\s*\(/i', $value ) ) return '';
				$declarations .= $name . ':' . $value . ';';
			}
			$css = '.elementor-kit-' . absint( $kit_id ) . '{' . $declarations . '}' . $css;
		}
		return strlen( $css ) <= 6000 ? $css : '';
	}
}
