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
		if ( 'off' === $mode ) return $resources;
		$manifest = self::manifest();
		if ( $mode !== ( $manifest['mode'] ?? '' ) ) return $resources;
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
		if ( 3 !== ( $manifest['schema'] ?? 0 ) && 'off' !== self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) ) ) self::rebuild();
	}

	/** Una actualización automática no ejecuta admin_init: sólo agenda el trabajo, nunca escanea en frontend. */
	public static function schedule_missing() {
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		if ( 'off' === $mode ) return;
		$manifest = get_option( self::OPTION, array() );
		if ( 3 !== ( $manifest['schema'] ?? 0 ) || $mode !== ( $manifest['mode'] ?? '' ) ) self::schedule();
	}

	/** Estado de sólo lectura para diagnosticar instalaciones automáticas sin publicar opciones en HTML. */
	public static function register_status_route() {
		register_rest_route( 'digitalisimo-seo/v1', '/performance/preloads', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'status_response' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
	) );
		register_rest_route( 'digitalisimo-seo/v1', '/performance/preloads/activate', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'activate_auto' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
	) );
	}

	/** Activa el modo automático sólo en el sitio autenticado; no modifica defaults de red. */
	public static function activate_auto( $request ) {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) $request->get_param( 'nonce' ), 'digitalisimo_performance_activate_preloads' ) ) return new WP_Error( 'digitalisimo_preload_forbidden', 'No autorizado.', array( 'status' => 403 ) );
		$settings = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		$settings['perf_preload_mode'] = self::sanitize_mode( 'auto' );
		$settings['perf_preload_limit'] = self::sanitize_limit( 2 );
		update_option( Digitalisimo_Integrations_Settings::OPTION, $settings, false );
		if ( is_multisite() ) {
			$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
			$inherit['perf_preload_mode'] = 0;
			$inherit['perf_preload_limit'] = 0;
			update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
		}
		self::rebuild();
		return self::status_response();
	}

	public static function status_response() {
		$manifest = get_option( self::OPTION, array() );
		$inventory = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
		return array(
			'site_id' => get_current_blog_id(),
			'activation_nonce' => wp_create_nonce( 'digitalisimo_performance_activate_preloads' ),
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

	/**
	 * Variantes a precargar en modo automático, en orden de prioridad.
	 *
	 * Salen de la tipografía crítica que declara el Kit de cada sitio. Si el Kit
	 * no declara ninguna, cada familia detectada en su variante regular, que es
	 * el valor por defecto de CSS: nunca se presupone una familia concreta.
	 */
	private static function auto_targets( $report ) {
		$targets = array();
		foreach ( (array) ( $report['critical'] ?? array() ) as $row ) {
			$family = strtolower( trim( (string) ( $row['family'] ?? '' ) ) );
			$weight = self::weight( $row['weight'] ?? '' );
			$style  = self::style( $row['style'] ?? '' );
			if ( '' === $family || null === $weight || null === $style ) continue;
			$targets[ $family . '|' . $weight . '|' . $style ] = array( 'family' => $family, 'weight' => $weight, 'style' => $style, 'role' => (string) ( $row['role'] ?? 'Kit' ) );
		}
		if ( $targets ) return array_values( $targets );
		foreach ( (array) ( $report['families'] ?? array() ) as $row ) {
			$family = strtolower( trim( (string) ( $row['family'] ?? '' ) ) );
			if ( '' !== $family ) $targets[ $family ] = array( 'family' => $family, 'weight' => '400', 'style' => 'normal', 'role' => 'Familia detectada' );
		}
		return array_values( $targets );
	}

	private static function weight( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value || 'normal' === $value ) return '400';
		if ( 'bold' === $value ) return '700';
		return preg_match( '/^[1-9]00$/', $value ) ? $value : null;
	}

	private static function style( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) return 'normal';
		return in_array( $value, array( 'normal', 'italic', 'oblique' ), true ) ? $value : null;
	}

	/** Un subconjunto latino cubre el texto inicial de casi cualquier sitio en español. */
	private static function range_score( $face ) {
		$range = strtoupper( (string) ( $face['unicode_range'] ?? '' ) );
		return false !== strpos( $range, 'U+0000-00FF' ) ? 2 : ( '' === $range ? 1 : 0 );
	}

	private static function matches_target( $face, $target ) {
		return strtolower( (string) ( $face['family'] ?? '' ) ) === $target['family']
			&& self::weight( $face['weight'] ?? '' ) === $target['weight']
			&& self::style( $face['style'] ?? '' ) === $target['style'];
	}

	/** Se calcula en administración o cron; un MISS de Redis no afecta el HTML. */
	public static function rebuild( $report = null ) {
		if ( null === $report ) {
			// Sin inventario recibido, recalcular todo para no dejar las copias de fuentes desfasadas.
			if ( method_exists( 'Digitalisimo_Integrations_Performance_Font_Guard', 'recalculate' ) ) {
				$result = Digitalisimo_Integrations_Performance_Font_Guard::recalculate();
				return $result['preloads'] ?? false;
			}
			$report = Digitalisimo_Integrations_Performance_Fonts::scan();
		}
		if ( ! is_array( $report ) || empty( $report['scanned_at'] ) ) return false;
		$mode      = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		$limit     = self::sanitize_limit( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_limit' ) );
		$base      = (string) ( $report['uploads_baseurl'] ?? '' );
		$manual    = array_fill_keys( explode( "\n", self::sanitize_paths( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_paths' ) ) ), true );
		$permitted = Digitalisimo_Integrations_Performance_Font_Guard::permitted_urls( $report );
		$eligible  = function( $face ) use ( $base, $permitted ) {
			$url = (string) ( $face['url'] ?? '' );
			return $base && 0 === strpos( $url, $base ) && preg_match( '~^elementor/google-fonts/fonts/[a-zA-Z0-9._/-]+\.woff2$~', substr( $url, strlen( $base ) ) ) && ! empty( $permitted[ $url ] );
		};
		$rows = array();
		$seen = array();
		if ( 'auto' === $mode ) {
			foreach ( self::auto_targets( $report ) as $target ) {
				$best = null;
				foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
					if ( ! $eligible( $face ) || ! self::matches_target( $face, $target ) ) continue;
					if ( ! $best || self::range_score( $face ) > self::range_score( $best ) ) $best = $face;
				}
				if ( ! $best || isset( $seen[ $best['url'] ] ) ) continue;
				$seen[ $best['url'] ] = true;
				$rows[] = array( 'url' => $best['url'], 'family' => $best['family'] ?? '', 'variant' => trim( (string) ( $best['weight'] ?? '' ) . ' ' . (string) ( $best['style'] ?? '' ) ), 'source' => 'Crítico · ' . $target['role'], 'css' => $best['css'] ?? '' );
				if ( count( $rows ) >= $limit ) break;
			}
		} elseif ( 'manual' === $mode ) {
			foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
				$url = (string) ( $face['url'] ?? '' );
				if ( ! $eligible( $face ) || empty( $manual[ substr( $url, strlen( $base ) ) ] ) || isset( $seen[ $url ] ) ) continue;
				$seen[ $url ] = true;
				$rows[] = array( 'url' => $url, 'family' => $face['family'] ?? '', 'variant' => trim( (string) ( $face['weight'] ?? '' ) . ' ' . (string) ( $face['style'] ?? '' ) ), 'source' => 'Ruta manual + CSS local', 'css' => $face['css'] ?? '' );
				if ( count( $rows ) >= $limit ) break;
			}
		}
		list( $rows, $errors ) = self::validate_rows( $rows, $permitted );
		$manifest = array( 'schema' => 3, 'mode' => $mode, 'rows' => $rows, 'validation' => $errors, 'generated_at' => current_time( 'mysql' ) );
		update_option( self::OPTION, $manifest, false );
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( 'preloads', $manifest, 3600 );
		return $manifest;
	}

	/** Cada preload debe ser una variante autorizada y aparecer una sola vez. */
	public static function validate_rows( $rows, $permitted ) {
		$valid  = array();
		$errors = array();
		$urls   = array();
		foreach ( (array) $rows as $row ) {
			$url = (string) ( $row['url'] ?? '' );
			$key = self::url_key( $url );
			if ( '' === $url || empty( $permitted[ $url ] ) ) { $errors[] = 'Preload de una variante no autorizada: ' . $url; continue; }
			if ( isset( $urls[ $key ] ) ) { $errors[] = 'Preload duplicado: ' . $url; continue; }
			$urls[ $key ] = true;
			$valid[] = $row;
		}
		return array( $valid, $errors );
	}

	/** Manifest persistente; la caché sólo ahorra lecturas y un MISS no cambia el HTML. */
	private static function manifest() {
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) {
			$found  = false;
			$cached = Digitalisimo_Integrations_Performance_Cache::get( 'preloads', $found );
			if ( $found && is_array( $cached ) ) return $cached;
		}
		$manifest = get_option( self::OPTION, array() );
		$manifest = is_array( $manifest ) ? $manifest : array();
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( 'preloads', $manifest, 3600 );
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
		$manual = array_fill_keys( preg_split( '/\r\n|\r|\n/', trim( (string) $paths ) ), true );
		$base = (string) ( $report['uploads_baseurl'] ?? '' );
		if ( ! $base ) return array();
		$usable = function( $face ) use ( $base, $queued, $permitted, &$already ) {
			$url = (string) ( $face['url'] ?? '' );
			if ( ! $url || ( null !== $permitted && empty( $permitted[ $url ] ) ) ) return false;
			if ( 0 !== strpos( $url, $base ) || ! preg_match( '~^elementor/google-fonts/fonts/[a-zA-Z0-9._/-]+\.woff2$~', substr( $url, strlen( $base ) ) ) ) return false;
			return ! empty( $queued[ $face['css'] ?? '' ] ) && ! in_array( self::url_key( $url ), $already, true );
		};
		$result = array();
		if ( 'manual' === $mode ) {
			foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
				if ( ! $usable( $face ) || empty( $manual[ substr( (string) $face['url'], strlen( $base ) ) ] ) ) continue;
				$result[] = $face['url'];
				$already[] = self::url_key( $face['url'] );
				if ( count( $result ) >= $limit ) break;
			}
			return $result;
		}
		if ( 'auto' !== $mode ) return $result;
		foreach ( self::auto_targets( $report ) as $target ) {
			foreach ( (array) ( $report['faces'] ?? array() ) as $face ) {
				if ( ! self::matches_target( $face, $target ) || ! $usable( $face ) ) continue;
				$result[] = $face['url'];
				$already[] = self::url_key( $face['url'] );
				break;
			}
			if ( count( $result ) >= $limit ) break;
		}
		return $result;
	}

	public static function print_links() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() ) return;
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' ) );
		if ( 'off' === $mode ) return;
		$manifest = self::manifest();
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
