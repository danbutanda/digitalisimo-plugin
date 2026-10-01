<?php
defined( 'ABSPATH' ) || exit;

/** Diagnóstico administrativo de la política efectiva y la última captura pública. */
class Digitalisimo_Integrations_Performance_Debug {
	public static function font_label( $face, $report ) {
		$family = (string) ( $face['family'] ?? '' );
		if ( Digitalisimo_Integrations_Performance_Font_Guard::icon_family( $family ) ) return 'ICON FONT';
		return Digitalisimo_Integrations_Performance_Font_Guard::allows_face( $face, $report ) ? 'PERMITIDO' : 'BLOQUEADO';
	}

	public static function css_label( $row, $defer, $safe, $handles ) {
		$handle = (string) ( $row['handle'] ?? '' );
		$src = (string) ( $row['src'] ?? '' );
		if ( in_array( $handle, array( 'elementor-frontend', 'elementor-pro-frontend', 'widget-heading', 'widget-image-carousel', 'widget-nav-menu', 'widget-nested-tabs', 'widget-counter', 'widget-call-to-action' ), true ) || preg_match( '#/elementor(?:-pro)?/assets/css/frontend(?:\.min)?\.css#i', $src ) ) return 'CRÍTICO';
		if ( $defer && ! $safe && in_array( $handle, $handles, true ) && preg_match( '#/plugins/elementor(?:-pro)?/assets/css/widget-[a-z0-9-]+(?:\.min)?\.css#i', $src ) ) return 'DIFERIDO CONFIGURADO';
		if ( 'Revisar' === ( $row['status'] ?? '' ) ) return 'EXCLUIDO DEL CAMBIO';
		return 'NORMAL';
	}

	public static function render( $network = false ) {
		if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$captured = get_transient( 'digitalisimo_perf_result_' . get_current_user_id() );
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$fonts = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
			$custom = get_option( Digitalisimo_Integrations_Performance_Migration::OPTION, array() );
			$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			if ( ! is_array( $captured ) || wp_parse_url( $captured['url'] ?? '', PHP_URL_HOST ) !== $home_host ) $captured = array();
			echo '<h2>Diagnóstico de ' . esc_html( home_url( '/' ) ) . '</h2><p>Datos guardados del análisis administrativo y de la última URL analizada. No mide solicitudes reales de red ni ejecuta cambios. Para actualizar la captura, usa «Analizar URL» en Estado.</p>';
			echo '<h3>Fuentes</h3>';
			if ( empty( $fonts['scanned_at'] ) ) echo '<p>MISS · sin inventario de fuentes. Analiza el Kit y CSS local.</p>';
			else {
				echo '<p>ELEMENTOR LOCAL · último inventario: ' . esc_html( $fonts['scanned_at'] ) . '</p><table class="widefat striped"><thead><tr><th>Estado</th><th>Familia</th><th>Peso</th><th>Estilo</th><th>CSS</th></tr></thead><tbody>';
				foreach ( array_slice( (array) ( $fonts['faces'] ?? array() ), 0, 100 ) as $face ) echo '<tr><td>' . esc_html( self::font_label( $face, $fonts ) ) . '</td><td>' . esc_html( $face['family'] ?? '' ) . '</td><td>' . esc_html( $face['weight'] ?? '' ) . '</td><td>' . esc_html( $face['style'] ?? '' ) . '</td><td>' . esc_html( $face['css'] ?? '' ) . '</td></tr>';
				echo '</tbody></table>';
			}
			$preload_mode = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' );
			echo '<h3>Preloads</h3><p>Modo efectivo: <strong>' . esc_html( $preload_mode ) . '</strong>. PRELOAD significa configurado, no necesariamente impreso en toda URL. Cada página requiere CSS encolado y preflight de duplicados.</p>';
			$paths = (string) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_paths' );
			if ( $paths ) echo '<pre>' . esc_html( $paths ) . '</pre>';
			$defer = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' );
			$safe = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_safe_mode' );
			$handles = explode( "\n", Digitalisimo_Integrations_Performance_CSS::sanitize_handles( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer_handles' ) ) );
			echo '<h3>CSS</h3>';
			if ( empty( $captured['resources'] ) ) echo '<p>Sin captura pública reciente de este sitio.</p>';
			else {
				echo '<table class="widefat striped"><thead><tr><th>Estado</th><th>Handle</th><th>Origen</th><th>Archivo</th></tr></thead><tbody>';
				foreach ( (array) $captured['resources'] as $row ) if ( 'CSS' === ( $row['type'] ?? '' ) ) echo '<tr><td>' . esc_html( self::css_label( $row, $defer, $safe, $handles ) ) . '</td><td>' . esc_html( $row['handle'] ?? '' ) . '</td><td>' . esc_html( $row['origin'] ?? '' ) . '</td><td><code>' . esc_html( $row['src'] ?? '' ) . '</code></td></tr>';
				echo '</tbody></table>';
			}
			$ids = array_filter( array( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gt_id' ), Digitalisimo_Integrations_SEO_Resolver::option( 'perf_ga4_id' ), Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gtm_id' ) ) );
			$conflict = Digitalisimo_Integrations_Performance_Migration::tracking_conflict( $ids );
			echo '<h3>Tracking</h3><p>DIGITALÍSIMO: ' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_enabled' ) ? 'configurado' : 'apagado' ) . ' · SITE KIT: ' . ( Digitalisimo_Integrations_Performance_Migration::site_kit_active() ? 'activo' : 'no detectado' ) . ' · ELEMENTOR: ' . ( ! empty( $custom['scanned_at'] ) ? 'inventariado' : 'sin inventario' ) . ' · DUPLICADO: ' . esc_html( $conflict ?: 'no detectado por estas comprobaciones' ) . '.</p>';
			if ( ! empty( $captured['resources'] ) ) foreach ( (array) $captured['resources'] as $row ) if ( preg_match( '#(?:googletagmanager\.com|google-analytics\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)#i', $row['src'] ?? '' ) ) echo '<p>' . ( false !== strpos( $row['src'], 'fonts.google' ) ? 'GOOGLE REMOTO' : 'TEMA / PLUGIN · revisar tracking' ) . ': <code>' . esc_html( $row['src'] ) . '</code></p>';
			$status = Digitalisimo_Integrations_Performance_Cache::status();
			$found = false;
			Digitalisimo_Integrations_Performance_Cache::get( 'fonts', $found );
			echo '<h3>Object Cache</h3><p>' . ( $found ? 'HIT' : 'MISS' ) . ' para inventario de fuentes · caché externa ' . ( $status['external'] ? 'activa' : 'inactiva' ) . ' · generación local ' . esc_html( get_option( Digitalisimo_Integrations_Performance_Cache::GENERATION, 1 ) ) . '. Una generación mayor que 1 indica INVALIDADO previo del grupo propio.</p>';
			if ( ! $network ) Digitalisimo_Integrations_Performance_Manager::render_log();
		} finally { if ( $switched ) restore_current_blog(); }
	}
}
