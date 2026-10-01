<?php
defined( 'ABSPATH' ) || exit;

/** Inventario administrativo de Kit y fuentes locales. No filtra fuentes en frontend. */
class Digitalisimo_Integrations_Performance_Fonts {
	const OPTION = 'digitalisimo_performance_font_inventory';

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_scan_fonts', array( __CLASS__, 'scan_action' ) );
	}

	private static function kit_settings() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) return array( 0, array() );
		$manager = \Elementor\Plugin::$instance->kits_manager;
		if ( ! method_exists( $manager, 'get_active_kit_for_frontend' ) ) return array( 0, array() );
		$kit = $manager->get_active_kit_for_frontend();
		if ( ! is_object( $kit ) || ! method_exists( $kit, 'get_id' ) ) return array( 0, array() );
		$id = absint( $kit->get_id() );
		$settings = method_exists( $kit, 'get_settings' ) ? $kit->get_settings() : array();
		if ( ! is_array( $settings ) || ! $settings ) $settings = get_post_meta( $id, '_elementor_page_settings', true );
		return array( $id, is_array( $settings ) ? $settings : array() );
	}

	/** Extrae familias y variantes, incluida la tipografía global; no presume que el Kit cubra todo el frontend. */
	public static function families_from_settings( $settings ) {
		$families = array();
		$walk = function( $node ) use ( &$walk, &$families ) {
			if ( ! is_array( $node ) ) return;
			foreach ( $node as $key => $value ) {
				if ( is_string( $key ) && preg_match( '/(?:^|_)font_family$/', $key ) && is_string( $value ) && '' !== trim( $value ) ) {
					$family = trim( $value );
					$base = substr( $key, 0, -strlen( 'font_family' ) );
					$weight = trim( (string) ( $node[ $base . 'font_weight' ] ?? '' ) );
					$style = trim( (string) ( $node[ $base . 'font_style' ] ?? '' ) );
					$index = strtolower( $family );
					if ( ! isset( $families[ $index ] ) ) $families[ $index ] = array( 'family' => $family, 'weights' => array(), 'styles' => array(), 'unknown_weight' => false, 'unknown_style' => false );
					if ( $weight ) $families[ $index ]['weights'][ $weight ] = true;
					else $families[ $index ]['unknown_weight'] = true;
					if ( $style ) $families[ $index ]['styles'][ $style ] = true;
					else $families[ $index ]['unknown_style'] = true;
				} elseif ( is_array( $value ) ) $walk( $value );
			}
		};
		$walk( (array) $settings );
		foreach ( $families as &$row ) { $row['weights'] = $row['unknown_weight'] ? array() : array_keys( $row['weights'] ); $row['styles'] = $row['unknown_style'] ? array() : array_keys( $row['styles'] ); unset( $row['unknown_weight'], $row['unknown_style'] ); }
		return array_values( $families );
	}

	/** Une el Kit con las tipografías configuradas en contenido Elementor publicado. */
	public static function merge_families( $groups ) {
		$merged = array();
		foreach ( $groups as $group ) foreach ( (array) $group as $row ) {
			$name = trim( (string) ( $row['family'] ?? '' ) );
			if ( '' === $name ) continue;
			$key = strtolower( $name );
			if ( ! isset( $merged[ $key ] ) ) $merged[ $key ] = array( 'family' => $name, 'weights' => array(), 'styles' => array(), 'unknown_weight' => false, 'unknown_style' => false );
			foreach ( array( 'weights' => 'unknown_weight', 'styles' => 'unknown_style' ) as $field => $unknown ) {
				$values = (array) ( $row[ $field ] ?? array() );
				if ( ! $values ) $merged[ $key ][ $unknown ] = true;
				foreach ( $values as $value ) {
					$value = strtolower( trim( (string) $value ) );
					if ( 'weights' === $field && 'normal' === $value ) $value = '400';
					if ( 'weights' === $field && 'bold' === $value ) $value = '700';
					if ( '' !== $value ) $merged[ $key ][ $field ][ $value ] = true;
				}
			}
		}
		foreach ( $merged as &$row ) { $row['weights'] = $row['unknown_weight'] ? array() : array_keys( $row['weights'] ); $row['styles'] = $row['unknown_style'] ? array() : array_keys( $row['styles'] ); unset( $row['unknown_weight'], $row['unknown_style'] ); }
		return array_values( $merged );
	}

	/** Escaneo acotado a 500 documentos; un inventario parcial nunca autoriza excluir familias desconocidas. */
	public static function content_families() {
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_types' ) ) return array( array(), 0, false );
		$types = array_values( array_unique( array_merge( array_values( get_post_types( array( 'public' => true ), 'names' ) ), array( 'elementor_library' ) ) ) );
		$groups = array(); $count = 0; $complete = true;
		for ( $page = 0; $page < 5; ++$page ) {
			$ids = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'meta_key' => '_elementor_edit_mode', 'meta_value' => 'builder', 'fields' => 'ids', 'posts_per_page' => 100, 'offset' => $page * 100, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => false ) );
			if ( ! is_array( $ids ) ) { $complete = false; break; }
			foreach ( $ids as $id ) {
				++$count;
				$data = get_post_meta( (int) $id, '_elementor_data', true );
				if ( ! is_string( $data ) || strlen( $data ) > 1024 * 1024 ) { $complete = false; continue; }
				if ( false !== stripos( $data, 'font-family' ) ) $complete = false; // CSS libre no se puede clasificar como una tipografía Elementor.
				$decoded = json_decode( $data, true );
				if ( ! is_array( $decoded ) ) { $complete = false; continue; }
				$groups[] = self::families_from_settings( $decoded );
				$settings = get_post_meta( (int) $id, '_elementor_page_settings', true );
				if ( is_array( $settings ) ) $groups[] = self::families_from_settings( $settings );
			}
			if ( count( $ids ) < 100 ) break;
			if ( 4 === $page ) $complete = false;
		}
		return array( self::merge_families( $groups ), $count, $complete );
	}

	/** Sólo lee CSS generado dentro de uploads del sitio actual, con límites estrictos. */
	private static function local_faces() {
		$uploads = wp_upload_dir();
		$root = realpath( $uploads['basedir'] ?? '' );
		$directory = realpath( trailingslashit( $uploads['basedir'] ?? '' ) . 'elementor/google-fonts/css' );
		if ( ! $root || ! $directory || 0 !== strpos( $directory, trailingslashit( $root ) ) ) return array();
		$files = glob( $directory . '/*.css' );
		if ( ! is_array( $files ) ) return array();
		$faces = array();
		$files = array_values( array_filter( $files, function( $path ) { return 0 !== strpos( basename( $path ), 'digitalisimo-' ); } ) );
		foreach ( array_slice( $files, 0, 50 ) as $path ) {
			$real = realpath( $path );
			if ( ! $real || 0 !== strpos( $real, trailingslashit( $directory ) ) || ! is_file( $real ) || ! is_readable( $real ) || filesize( $real ) > 512 * KB_IN_BYTES ) continue;
			$css = file_get_contents( $real );
			if ( ! is_string( $css ) || ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks ) ) continue;
			foreach ( array_slice( $blocks[1], 0, 300 ) as $block ) {
				if ( ! preg_match( '/font-family\s*:\s*([^;]+)/i', $block, $family ) ) continue;
				$row = array( 'family' => trim( $family[1], " \t\n\r\0\x0B'\"" ), 'weight' => '', 'style' => '', 'format' => '', 'unicode_range' => '', 'css' => basename( $real ), 'url' => '' );
				foreach ( array( 'font-weight' => 'weight', 'font-style' => 'style' ) as $property => $field ) if ( preg_match( '/' . $property . '\s*:\s*([^;]+)/i', $block, $match ) ) $row[ $field ] = trim( $match[1] );
				if ( preg_match( '/unicode-range\s*:\s*([^;]+)/i', $block, $range ) ) $row['unicode_range'] = trim( $range[1] );
				if ( preg_match( '/format\(\s*[\'\"]?([^\'\")]+)/i', $block, $format ) ) $row['format'] = trim( $format[1] );
				if ( preg_match( '/url\(\s*[\'\"]?([^\'\")]+\.woff2)(?:\?[^\'\")]+)?[\'\"]?\s*\)/i', $block, $source ) ) {
					$font_path = realpath( dirname( $real ) . '/' . $source[1] );
					if ( $font_path && 0 === strpos( $font_path, trailingslashit( $root ) ) && is_file( $font_path ) ) $row['url'] = trailingslashit( $uploads['baseurl'] ) . str_replace( DIRECTORY_SEPARATOR, '/', substr( $font_path, strlen( trailingslashit( $root ) ) ) );
				}
				$faces[] = $row;
				if ( count( $faces ) >= 500 ) break 2;
			}
		}
		return $faces;
	}

	public static function scan() {
		list( $kit_id, $settings ) = self::kit_settings();
		list( $content_families, $documents, $complete ) = self::content_families();
		$kit_families = self::families_from_settings( $settings );
		if ( false !== stripos( (string) json_encode( $settings ), 'font-family' ) ) $complete = false;
		if ( function_exists( 'wp_get_custom_css' ) && false !== stripos( (string) wp_get_custom_css(), 'font-family' ) ) $complete = false;
		if ( class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && false !== stripos( (string) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_custom_css' ), 'font-family' ) ) $complete = false;
		$uploads = wp_upload_dir();
		$faces = self::local_faces();
		$css_files = glob( trailingslashit( $uploads['basedir'] ?? '' ) . 'elementor/google-fonts/css/*.css' );
		if ( is_array( $css_files ) ) $css_files = array_filter( $css_files, function( $path ) { return 0 !== strpos( basename( $path ), 'digitalisimo-' ); } );
		if ( ( is_array( $css_files ) && count( $css_files ) > 50 ) || count( $faces ) >= 500 ) $complete = false;
		$report = array( 'kit_id' => $kit_id, 'kit_families' => $kit_families, 'families' => self::merge_families( array( $kit_families, $content_families ) ), 'documents' => $documents, 'complete' => $complete && $kit_id > 0 && $documents > 0, 'faces' => $faces, 'uploads_baseurl' => trailingslashit( $uploads['baseurl'] ?? '' ), 'scanned_at' => current_time( 'mysql' ) );
		update_option( self::OPTION, $report, false );
		Digitalisimo_Integrations_Performance_Cache::set( 'fonts', $report, 3600 );
		return $report;
	}

	public static function scan_action() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_scan_fonts_' . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { Digitalisimo_Integrations_Performance_Preloads::rebuild(); }
		finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=fonts&site_id=' . $site_id ) : admin_url( 'admin.php?page=digitalisimo-performance&section=fonts' );
		wp_safe_redirect( $target );
		exit;
	}

	public static function render( $network = false ) {
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$action = admin_url( 'admin-post.php' );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$report = get_option( self::OPTION, array() );
			echo '<h2>Fuentes detectadas</h2><p>Sitio: <code>' . esc_html( home_url( '/' ) ) . '</code>. Analiza el Kit y el contenido Elementor publicado, prepara copias optimizadas del CSS local y conserva los archivos originales. Las fuentes del tema o de servicios externos quedan fuera de esta herramienta.</p>';
			if ( isset( $_GET['font_guard_error'] ) ) echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['font_guard_error'] ) ) . '</p></div>';
			if ( isset( $_GET['font_guard_optimized'] ) ) echo '<div class="notice notice-success"><p>Copias preparadas para este sitio. Reglas de fuente omitidas: ' . esc_html( absint( $_GET['font_guard_optimized'] ) ) . '.</p></div>';
			echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="digitalisimo_performance_optimize_fonts"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
			wp_nonce_field( 'digitalisimo_performance_optimize_fonts_' . $site_id );
			submit_button( 'Detectar y optimizar fuentes', 'primary', 'submit', false );
			echo '</form>';
			if ( is_array( $report ) && ! empty( $report['scanned_at'] ) ) {
				$names = array();
				foreach ( (array) ( $report['families'] ?? array() ) as $row ) if ( ! empty( $row['family'] ) ) $names[] = $row['family'];
				echo '<p><strong>Familias detectadas:</strong> ' . esc_html( $names ? implode( ', ', $names ) : 'Ninguna' ) . ' · Documentos Elementor analizados: ' . esc_html( (int) ( $report['documents'] ?? 0 ) ) . '. ' . ( ! empty( $report['complete'] ) ? 'Inventario completo de documentos publicados.' : 'Inventario parcial: se conservan familias no identificadas.' ) . '</p>';
				$status = Digitalisimo_Integrations_Performance_Font_Guard::status();
				echo '<p><strong>Optimización vigente:</strong> ' . ( $status['copies'] ? esc_html( $status['copies'] . ' copias CSS; ' . $status['removed'] . ' reglas omitidas.' ) : 'Sin copias CSS activas para este sitio.' ) . '</p>';
			}
			echo '<details><summary>Ver inventario y opciones avanzadas</summary>';
			echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="digitalisimo_performance_scan_fonts"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
			wp_nonce_field( 'digitalisimo_performance_scan_fonts_' . $site_id );
			submit_button( 'Analizar Kit y fuentes locales', 'secondary', 'submit', false );
			echo '</form>';
			if ( ! is_array( $report ) || empty( $report['scanned_at'] ) ) { echo '</details>'; return; }
			echo '<p>Kit activo: ' . esc_html( (string) ( $report['kit_id'] ?? 0 ) ) . ' · Último análisis: ' . esc_html( $report['scanned_at'] ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th>Familia declarada en Kit o contenido</th><th>Pesos</th><th>Estilos</th></tr></thead><tbody>';
			foreach ( (array) ( $report['families'] ?? array() ) as $row ) echo '<tr><td>' . esc_html( $row['family'] ?? '' ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['weights'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['styles'] ?? array() ) ) ) . '</td></tr>';
			echo '</tbody></table><h3>Variantes en CSS local de Elementor</h3><p>Esta tabla muestra los archivos originales antes de optimizar. El botón principal prepara copias con las reglas seleccionadas automáticamente.</p>';
			$grouped = array();
			foreach ( (array) ( $report['faces'] ?? array() ) as $row ) {
				$key = strtolower( $row['family'] ?? '' ) . '|' . ( $row['css'] ?? '' );
				if ( ! isset( $grouped[ $key ] ) ) $grouped[ $key ] = array( 'family' => $row['family'], 'weights' => array(), 'styles' => array(), 'formats' => array(), 'css' => $row['css'] );
				foreach ( array( 'weight' => 'weights', 'style' => 'styles', 'format' => 'formats' ) as $field => $target ) if ( ! empty( $row[ $field ] ) ) $grouped[ $key ][ $target ][ $row[ $field ] ] = true;
			}
			echo '<table class="widefat striped"><thead><tr><th>Familia</th><th>Pesos</th><th>Estilos</th><th>Formato</th><th>CSS</th></tr></thead><tbody>';
			foreach ( $grouped as $row ) echo '<tr><td>' . esc_html( $row['family'] ) . '</td><td>' . esc_html( implode( ', ', array_keys( $row['weights'] ) ) ) . '</td><td>' . esc_html( implode( ', ', array_keys( $row['styles'] ) ) ) . '</td><td>' . esc_html( implode( ', ', array_keys( $row['formats'] ) ) ) . '</td><td>' . esc_html( $row['css'] ) . '</td></tr>';
			echo '</tbody></table>';
			echo '<h3>WOFF2 disponibles para precarga manual</h3><p>Copia la ruta relativa a uploads en la sección Preloads. Un archivo en esta lista no demuestra que sea crítico para todas las páginas.</p><ul>';
			$base = trailingslashit( wp_upload_dir()['baseurl'] ?? '' );
			foreach ( (array) ( $report['faces'] ?? array() ) as $face ) if ( ! empty( $face['url'] ) && 0 === strpos( $face['url'], $base ) ) echo '<li><code>' . esc_html( substr( $face['url'], strlen( $base ) ) ) . '</code> — ' . esc_html( $face['family'] ?? '' ) . ' ' . esc_html( $face['weight'] ?? '' ) . ' ' . esc_html( $face['style'] ?? '' ) . '</li>';
			echo '</ul>';
			Digitalisimo_Integrations_Performance_Font_Guard::render_build( $network, $site_id );
			echo '</details>';
		} finally { if ( $switched ) restore_current_blog(); }
	}
}
