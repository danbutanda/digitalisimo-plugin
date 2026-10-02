<?php
defined( 'ABSPATH' ) || exit;

/** Diagnóstico administrativo de la política efectiva y la última captura pública. */
class Digitalisimo_Integrations_Performance_Debug {
	public static function font_label( $face, $report ) {
		$family = (string) ( $face['family'] ?? '' );
		if ( Digitalisimo_Integrations_Performance_Font_Guard::icon_family( $family ) ) return 'ICON FONT';
		if ( Digitalisimo_Integrations_Performance_Font_Guard::allows_face( $face, $report ) ) return 'PERMITIDO';
		return Digitalisimo_Integrations_Performance_Font_Guard::ready_for_face( $face, $report ) ? 'BLOQUEADO' : 'POLÍTICA PENDIENTE';
	}

	/** Clave de variante: familia, peso y estilo normalizados. */
	public static function variant_key( $family, $weight, $style ) {
		$weight = strtolower( trim( (string) $weight ) );
		if ( '' === $weight || 'normal' === $weight ) $weight = '400';
		if ( 'bold' === $weight ) $weight = '700';
		$style = strtolower( trim( (string) $style ) );
		return strtolower( trim( (string) $family ) ) . '|' . $weight . '|' . ( '' === $style ? 'normal' : $style );
	}

	/** Variantes elegidas para preload. Un manifest anterior sólo traía «400 normal». */
	public static function preloaded_variants( $manifest ) {
		$variants = array();
		foreach ( (array) ( $manifest['rows'] ?? array() ) as $row ) {
			$weight = $row['weight'] ?? '';
			$style  = $row['style'] ?? '';
			if ( '' === $weight && preg_match( '/^(\S+)\s*(\S*)$/', trim( (string) ( $row['variant'] ?? '' ) ), $m ) ) { $weight = $m[1]; $style = $m[2]; }
			$variants[ self::variant_key( $row['family'] ?? '', $weight, $style ) ] = true;
		}
		return $variants;
	}

	/**
	 * Estados combinables de una variante: el principal (PERMITIDO, BLOQUEADO,
	 * POLÍTICA PENDIENTE o ICON FONT) más FUENTE VARIABLE, EXCEPCIÓN MANUAL,
	 * CRÍTICO y PRELOAD. PRELOAD se marca por variante elegida, no por archivo:
	 * en una fuente variable todos los pesos comparten el mismo WOFF2.
	 */
	public static function font_states( $face, $report, $preloaded ) {
		$states = array( self::font_label( $face, $report ) );
		if ( 'ICON FONT' !== $states[0] ) {
			if ( Digitalisimo_Integrations_Performance_Font_Guard::is_variable( $face, $report ) ) $states[] = 'FUENTE VARIABLE';
			if ( Digitalisimo_Integrations_Performance_Font_Guard::is_exception( $face, $report ) ) $states[] = 'EXCEPCIÓN MANUAL';
			if ( Digitalisimo_Integrations_Performance_Font_Guard::is_critical( $face, $report ) ) $states[] = 'CRÍTICO';
		}
		if ( ! empty( $preloaded[ self::variant_key( $face['family'] ?? '', $face['weight'] ?? '', $face['style'] ?? '' ) ] ) ) $states[] = 'PRELOAD';
		return $states;
	}

	/**
	 * Una fila por variante: los subsets de unicode-range de una misma variante
	 * comparten estado, así que se agrupan. Se toma como representante el subset
	 * latino, que es el que se precarga.
	 */
	public static function group_faces( $faces ) {
		$groups = array();
		foreach ( (array) $faces as $face ) {
			$key = self::variant_key( $face['family'] ?? '', $face['weight'] ?? '', $face['style'] ?? '' );
			if ( ! isset( $groups[ $key ] ) ) $groups[ $key ] = array( 'face' => $face, 'subsets' => 0 );
			++$groups[ $key ]['subsets'];
			if ( false !== stripos( (string) ( $face['unicode_range'] ?? '' ), 'U+0000-00FF' ) ) $groups[ $key ]['face'] = $face;
		}
		return array_values( $groups );
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
			if ( ! Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( $captured, $site_id ) ) $captured = array();
			echo '<h2>Diagnóstico de ' . esc_html( home_url( '/' ) ) . '</h2><p>Datos guardados del análisis administrativo y de la última URL analizada. No mide solicitudes reales de red ni ejecuta cambios. Para actualizar la captura, usa «Analizar URL» en Estado.</p>';
			echo '<h3>Fuentes</h3>';
			$manifest = get_option( Digitalisimo_Integrations_Performance_Preloads::OPTION, array() );
			if ( empty( $fonts['scanned_at'] ) ) echo '<p>MISS · sin inventario de fuentes. Pulsa «Recalcular fuentes» en la sección Fuentes.</p>';
			else {
				$preloaded = self::preloaded_variants( $manifest );
				$guard = Digitalisimo_Integrations_Performance_Font_Guard::status();
				echo '<p>Último inventario: ' . esc_html( $fonts['scanned_at'] ) . ' · Copias CSS activas: ' . esc_html( $guard['copies'] ) . ( $guard['built_at'] ? ' · Generadas: ' . esc_html( $guard['built_at'] ) : '' ) . '</p>';
				echo '<p class="description">FUENTE VARIABLE: todos los pesos usan el mismo archivo. Esas reglas se conservan aunque el peso no se haya detectado, porque retirarlas no evita ninguna descarga y sí produce negritas sintéticas.</p>';
				echo '<table class="widefat striped"><thead><tr><th>Familia</th><th>Estilo</th><th>Peso</th><th>Estado</th><th>Archivo</th><th>Origen</th><th>Preload</th></tr></thead><tbody>';
				foreach ( self::group_faces( $fonts['faces'] ?? array() ) as $group ) {
					$face   = $group['face'];
					$states = self::font_states( $face, $fonts, $preloaded );
					$file   = ! empty( $face['url'] ) ? basename( (string) wp_parse_url( $face['url'], PHP_URL_PATH ) ) : ( ( $face['css'] ?? '' ) . ' · sin WOFF2 local' );
					if ( $group['subsets'] > 1 ) $file .= ' + ' . ( $group['subsets'] - 1 ) . ' subsets';
					echo '<tr><td>' . esc_html( $face['family'] ?? '' ) . '</td><td>' . esc_html( ( $face['style'] ?? '' ) ?: 'normal' ) . '</td><td>' . esc_html( ( $face['weight'] ?? '' ) ?: '400' ) . '</td><td>' . esc_html( implode( ' · ', $states ) ) . '</td><td><code>' . esc_html( $file ) . '</code></td><td>' . esc_html( Digitalisimo_Integrations_Performance_Font_Guard::origin( $face, $fonts ) ) . '</td><td>' . ( in_array( 'PRELOAD', $states, true ) ? 'Sí' : '—' ) . '</td></tr>';
				}
				echo '</tbody></table>';
				// Validación posterior a la generación: copias rechazadas y preloads descartados.
				$copies = get_option( Digitalisimo_Integrations_Performance_Font_Guard::OPTION, array() );
				$errors = array();
				foreach ( (array) ( $copies['rows'] ?? array() ) as $source => $row ) foreach ( (array) ( $row['errors'] ?? array() ) as $error ) $errors[] = basename( (string) $source ) . ': ' . $error;
				foreach ( (array) ( $manifest['validation'] ?? array() ) as $error ) $errors[] = 'Preloads: ' . $error;
				echo '<h4>Validación</h4>';
				if ( ! $errors ) echo '<p>Sin errores: cada CSS generado contiene exactamente las variantes autorizadas presentes en su original, sin duplicados, y cada preload pertenece a una variante autorizada.</p>';
				else { echo '<ul>'; foreach ( $errors as $error ) echo '<li>ERROR · ' . esc_html( $error ) . '</li>'; echo '</ul><p>Las copias con error no se sirven: el sitio recibe el CSS original.</p>'; }
			}
			$preload_mode = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' );
			echo '<h3>Preloads</h3><p>Modo efectivo: <strong>' . esc_html( $preload_mode ) . '</strong> · Manifest persistente: ' . esc_html( $manifest['generated_at'] ?? 'pendiente' ) . '. La posición corresponde al HTML de la última URL analizada.</p>';
			echo '<table class="widefat striped"><thead><tr><th>PRELOAD URL</th><th>Posición en HEAD</th><th>Familia</th><th>Variante</th><th>Origen de detección</th><th>Estado</th></tr></thead><tbody>';
			$delivery = (array) ( $captured['preload_delivery'] ?? array() );
			foreach ( (array) ( $manifest['rows'] ?? array() ) as $row ) {
				$actual = array();
				foreach ( $delivery as $item ) if ( ( $item['url'] ?? '' ) === ( $row['url'] ?? '' ) ) { $actual = $item; break; }
				echo '<tr><td><code>' . esc_html( $row['url'] ?? '' ) . '</code></td><td>' . esc_html( $actual['position'] ?? 'Sin captura' ) . '</td><td>' . esc_html( $row['family'] ?? '' ) . '</td><td>' . esc_html( $row['variant'] ?? '' ) . '</td><td>' . esc_html( $row['source'] ?? '' ) . '</td><td>' . esc_html( $actual['status'] ?? 'Sin captura' ) . '</td></tr>';
			}
			echo '</tbody></table>';
			$paths = (string) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_paths' );
			if ( $paths ) echo '<pre>' . esc_html( $paths ) . '</pre>';
			echo '<h3>CSS</h3><p>CSS configurado como diferido frente al HTML realmente entregado:</p>';
			if ( ! empty( $captured['css_delivery'] ) ) {
				echo '<table class="widefat striped"><thead><tr><th>HANDLE</th><th>URL</th><th>Estado</th></tr></thead><tbody>';
				foreach ( $captured['css_delivery'] as $row ) echo '<tr><td>' . esc_html( $row['handle'] ?? '' ) . '</td><td><code>' . esc_html( $row['url'] ?? '' ) . '</code></td><td>' . esc_html( $row['status'] ?? '' ) . '</td></tr>';
				echo '</tbody></table>';
			}
			if ( empty( $captured['resources'] ) ) echo '<p>Sin captura pública reciente de este sitio.</p>';
			else {
				echo '<table class="widefat striped"><thead><tr><th>Estado</th><th>Handle</th><th>Origen</th><th>Archivo</th></tr></thead><tbody>';
				foreach ( (array) $captured['resources'] as $row ) if ( 'CSS' === ( $row['type'] ?? '' ) ) echo '<tr><td>' . esc_html( $row['status'] ?? '' ) . '</td><td>' . esc_html( $row['handle'] ?? '' ) . '</td><td>' . esc_html( $row['origin'] ?? '' ) . '</td><td><code>' . esc_html( $row['src'] ?? '' ) . '</code></td></tr>';
				echo '</tbody></table>';
			}
			$ids = array_filter( array( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gt_id' ), Digitalisimo_Integrations_SEO_Resolver::option( 'perf_ga4_id' ), Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gtm_id' ) ) );
			$tracking_enabled = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_enabled' );
			$conflict = $tracking_enabled && $ids ? Digitalisimo_Integrations_Performance_Migration::tracking_conflict( $ids ) : '';
			echo '<h3>Tracking</h3><p>DIGITALÍSIMO: ' . ( $tracking_enabled ? 'configurado' : 'apagado' ) . ' · SITE KIT: ' . ( Digitalisimo_Integrations_Performance_Migration::site_kit_active() ? 'activo' : 'no detectado' ) . ' · ELEMENTOR: ' . ( ! empty( $custom['scanned_at'] ) ? 'inventariado' : 'sin inventario' ) . ' · DUPLICADO: ' . esc_html( $tracking_enabled && $ids ? ( $conflict ?: 'no detectado por estas comprobaciones' ) : 'sin evaluar (Digitalísimo apagado o sin IDs)' ) . '.</p>';
			if ( ! empty( $captured['resources'] ) ) foreach ( (array) $captured['resources'] as $row ) if ( preg_match( '#(?:googletagmanager\.com|google-analytics\.com|fonts\.googleapis\.com|fonts\.gstatic\.com)#i', $row['src'] ?? '' ) ) {
				echo '<p>' . ( false !== strpos( $row['src'], 'fonts.google' ) ? 'GOOGLE REMOTO' : 'SCRIPT EXTERNO · revisar origen' ) . ': <code>' . esc_html( $row['src'] ) . '</code>';
				if ( false !== stripos( $row['src'], 'fonts.googleapis.com/css' ) ) {
					$rewritten = Digitalisimo_Integrations_Performance_Font_Guard::preview_google_url( html_entity_decode( $row['src'], ENT_QUOTES, 'UTF-8' ) );
					if ( null === $rewritten ) echo '<br>Con la política vigente: sin whitelist generada todavía; se pide la URL original.';
					elseif ( false === $rewritten ) echo '<br>Con la política vigente: no queda ninguna familia autorizada; la hoja no se carga.';
					else echo '<br>Con la política vigente: <code>' . esc_html( $rewritten ) . '</code>';
				}
				echo '</p>';
			}
			$status = Digitalisimo_Integrations_Performance_Cache::status();
			$found = false;
			Digitalisimo_Integrations_Performance_Cache::get( 'fonts', $found );
			echo '<h3>Object Cache</h3><p>' . ( $found ? 'HIT' : 'MISS' ) . ' para inventario de fuentes · caché externa ' . ( $status['external'] ? 'activa' : 'inactiva' ) . ' · generación local ' . esc_html( get_option( Digitalisimo_Integrations_Performance_Cache::GENERATION, 1 ) ) . '. Una generación mayor que 1 indica INVALIDADO previo del grupo propio.</p>';
			if ( ! $network ) Digitalisimo_Integrations_Performance_Manager::render_log();
		} finally { if ( $switched ) restore_current_blog(); }
	}
}
