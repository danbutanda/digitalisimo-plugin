<?php
defined( 'ABSPATH' ) || exit;

/** Inventario de Custom Code de Elementor y posibles duplicados. Sólo lectura. */
class Digitalisimo_Integrations_Performance_Migration {
	const OPTION = 'digitalisimo_performance_custom_code_inventory';

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_scan_custom_code', array( __CLASS__, 'scan_action' ) );
		add_action( 'admin_post_digitalisimo_performance_prepare_migration', array( __CLASS__, 'prepare_action' ) );
		add_action( 'save_post_elementor_snippet', array( __CLASS__, 'invalidate' ) );
		add_action( 'trashed_post', array( __CLASS__, 'invalidate_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'invalidate_post' ) );
	}

	public static function invalidate_post( $id ) {
		if ( 'elementor_snippet' === get_post_type( $id ) ) self::invalidate();
	}

	public static function invalidate() {
		delete_option( self::OPTION );
		Digitalisimo_Integrations_Performance_Cache::delete( 'custom-code' );
	}

	public static function classify( $code ) {
		$code = (string) $code;
		preg_match_all( '/\b(?:GTM|GT|G)-[A-Z0-9]+\b/i', $code, $ids );
		preg_match_all( '/<link\b[^>]*\brel\s*=\s*[\'\"]preload[\'\"][^>]*>/i', $code, $links );
		$preloads = array();
		foreach ( $links[0] as $tag ) if ( preg_match( '/\bhref\s*=\s*[\'\"]([^\'\"]+)[\'\"]/i', $tag, $match ) ) $preloads[] = esc_url_raw( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
		return array(
			'tracking_ids' => array_values( array_unique( array_map( 'strtoupper', $ids[0] ) ) ),
			'preloads' => array_values( array_unique( array_filter( $preloads ) ) ),
			'technical_css' => (bool) preg_match( '/<style\b|elementor-image-carousel-caption/i', $code ),
			'gtag' => (bool) preg_match( '/\bgtag\s*\(|googletagmanager\.com\/gtag/i', $code ),
			'gtm' => (bool) preg_match( '/googletagmanager\.com\/gtm\.js|ns\.html\?id=GTM-/i', $code ),
		);
	}

	public static function scan() {
		$rows = array();
		if ( post_type_exists( 'elementor_snippet' ) ) {
			$ids = get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 100, 'fields' => 'ids' ) );
			foreach ( $ids as $id ) {
				$code = (string) get_post_meta( $id, '_elementor_code', true );
				$info = self::classify( $code );
				if ( ! $info['tracking_ids'] && ! $info['preloads'] && ! $info['technical_css'] ) continue;
				$rows[] = array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'status' => get_post_status( $id ), 'location' => sanitize_key( get_post_meta( $id, '_elementor_location', true ) ), 'tracking_ids' => $info['tracking_ids'], 'preloads' => $info['preloads'], 'technical_css' => $info['technical_css'], 'gtag' => $info['gtag'], 'gtm' => $info['gtm'] );
			}
		}
		$report = array( 'scanned_at' => current_time( 'mysql' ), 'rows' => $rows, 'site_kit' => self::site_kit_active() );
		update_option( self::OPTION, $report, false );
		Digitalisimo_Integrations_Performance_Cache::set( 'custom-code', $report, 3600 );
		return $report;
	}

	public static function site_kit_active() {
		$slug = 'google-site-kit/google-site-kit.php';
		$site = (array) get_option( 'active_plugins', array() );
		$network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		return in_array( $slug, $site, true ) || isset( $network[ $slug ] );
	}

	/** El inventario debe existir y no contener una implementación publicada equivalente. */
	public static function tracking_conflict( $ids ) {
		if ( self::site_kit_active() ) return 'Site Kit activo';
		$report = get_option( self::OPTION, array() );
		if ( empty( $report['scanned_at'] ) ) return 'Falta analizar Custom Code';
		foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
			if ( 'publish' !== ( $row['status'] ?? '' ) ) continue;
			if ( array_intersect( $ids, (array) ( $row['tracking_ids'] ?? array() ) ) ) return 'ID publicado en Elementor Custom Code';
		}
		return '';
	}

	/** Prepara sólo valores inequívocos del sitio; la activación queda bajo revisión humana. */
	public static function proposal( $custom, $fonts ) {
		$ids = array( 'gt' => array(), 'ga4' => array(), 'gtm' => array() );
		$paths = array();
		$base = (string) ( $fonts['uploads_baseurl'] ?? '' );
		$known = array();
		foreach ( (array) ( $fonts['faces'] ?? array() ) as $face ) if ( ! empty( $face['url'] ) ) $known[ $face['url'] ] = true;
		foreach ( (array) ( $custom['rows'] ?? array() ) as $row ) {
			if ( 'publish' !== ( $row['status'] ?? '' ) ) continue;
			foreach ( (array) ( $row['tracking_ids'] ?? array() ) as $id ) {
				$kind = 0 === strpos( $id, 'GTM-' ) ? 'gtm' : ( 0 === strpos( $id, 'GT-' ) ? 'gt' : 'ga4' );
				if ( Digitalisimo_Integrations_Performance_Tracking::sanitize_id( $id, $kind ) ) $ids[ $kind ][ $id ] = true;
			}
			foreach ( (array) ( $row['preloads'] ?? array() ) as $url ) if ( $base && 0 === strpos( $url, $base ) && isset( $known[ $url ] ) ) $paths[ substr( $url, strlen( $base ) ) ] = true;
		}
		$proposal = array();
		foreach ( array( 'gt' => 'perf_gt_id', 'ga4' => 'perf_ga4_id', 'gtm' => 'perf_gtm_id' ) as $kind => $key ) if ( 1 === count( $ids[ $kind ] ) ) $proposal[ $key ] = array_key_first( $ids[ $kind ] );
		if ( $paths ) $proposal['perf_preload_paths'] = Digitalisimo_Integrations_Performance_Preloads::sanitize_paths( implode( "\n", array_keys( $paths ) ) );
		return $proposal;
	}

	public static function prepare_action() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_prepare_migration_' . $site_id );
		$section = sanitize_key( $_POST['section'] ?? 'tracking' );
		if ( ! in_array( $section, array( 'tracking', 'preloads' ), true ) ) $section = 'tracking';
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$custom = get_option( self::OPTION, array() );
			$fonts = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
			if ( empty( $custom['scanned_at'] ) || empty( $fonts['scanned_at'] ) ) wp_die( 'Analiza primero Custom Code y fuentes del sitio.' );
			$proposal = self::proposal( $custom, $fonts );
			$options = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
			$applied = array();
			foreach ( $proposal as $key => $value ) {
				if ( isset( $options[ $key ] ) && '' !== (string) $options[ $key ] && 0 !== $options[ $key ] ) continue;
				$options[ $key ] = $value;
				$inherit[ $key ] = 0;
				$applied[] = $key;
			}
			if ( array_intersect( $applied, array( 'perf_gt_id', 'perf_ga4_id', 'perf_gtm_id' ) ) ) { $options['perf_tracking_enabled'] = 0; $inherit['perf_tracking_enabled'] = 0; }
			if ( in_array( 'perf_preload_paths', $applied, true ) ) { $options['perf_preload_mode'] = 'off'; $inherit['perf_preload_mode'] = 0; }
			update_option( Digitalisimo_Integrations_Settings::OPTION, $options );
			if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', $inherit );
		} finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=' . $section . '&site_id=' . $site_id . '&prepared=1' ) : admin_url( 'admin.php?page=digitalisimo-performance&section=' . $section . '&prepared=1' );
		wp_safe_redirect( $target );
		exit;
	}

	public static function scan_action() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_scan_custom_code_' . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { self::scan(); }
		finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=tracking&site_id=' . $site_id ) : admin_url( 'admin.php?page=digitalisimo-performance&section=tracking' );
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
			echo '<h2>Migrar Custom Code de Elementor</h2><p>Inventario de ' . esc_html( home_url( '/' ) ) . '. No modifica ni elimina Custom Code. Tras configurar cada función en Digitalísimo, comprueba su salida y desactiva manualmente el código equivalente para evitar duplicados.</p>';
			echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="digitalisimo_performance_scan_custom_code"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
			wp_nonce_field( 'digitalisimo_performance_scan_custom_code_' . $site_id );
			submit_button( 'Analizar Custom Code y Site Kit', 'secondary', 'submit', false );
			echo '</form>';
			if ( ! is_array( $report ) || empty( $report['scanned_at'] ) ) return;
			echo '<p>Último análisis: ' . esc_html( $report['scanned_at'] ) . ' · Site Kit: ' . ( ! empty( $report['site_kit'] ) ? 'activo' : 'no detectado' ) . '</p>';
			$fonts = get_option( Digitalisimo_Integrations_Performance_Fonts::OPTION, array() );
			if ( ! empty( $fonts['scanned_at'] ) ) {
				$proposal = self::proposal( $report, $fonts );
				if ( $proposal ) {
					echo '<p>Valores detectados para preparar: <code>' . esc_html( implode( ', ', array_keys( $proposal ) ) ) . '</code>. Sólo llena campos vacíos del sitio; tracking y preloads quedan apagados. Revisa los IDs, Site Kit y el consentimiento antes de activarlos. Los fragmentos originales permanecen publicados hasta que los desactives manualmente.</p>';
				$section = sanitize_key( $_GET['section'] ?? 'tracking' );
				if ( ! in_array( $section, array( 'tracking', 'preloads' ), true ) ) $section = 'tracking';
				echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="digitalisimo_performance_prepare_migration"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '"><input type="hidden" name="section" value="' . esc_attr( $section ) . '">';
				wp_nonce_field( 'digitalisimo_performance_prepare_migration_' . $site_id );
				submit_button( 'Preparar valores en SEO sin activarlos', 'secondary', 'submit', false );
				echo '</form>';
			}
			} else echo '<p>Analiza también Fuentes para importar sólo WOFF2 locales reconocidos.</p>';
			if ( ! empty( $report['site_kit'] ) ) echo '<div class="notice notice-warning inline"><p>Site Kit está activo. Revisa sus IDs antes de habilitar Google Tracking en Digitalísimo; no se desactiva automáticamente.</p></div>';
			echo '<table class="widefat striped"><thead><tr><th>Custom Code</th><th>Estado</th><th>Ubicación</th><th>Google IDs</th><th>Preloads</th><th>CSS técnico</th></tr></thead><tbody>';
			foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
				$edit = get_edit_post_link( $row['id'] );
				echo '<tr><td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $row['title'] ) . '</a>' : esc_html( $row['title'] ) ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['location'] ) . '</td><td>' . esc_html( implode( ', ', $row['tracking_ids'] ) ) . '</td><td>' . esc_html( implode( ', ', $row['preloads'] ) ) . '</td><td>' . ( $row['technical_css'] ? 'Detectado' : 'No' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} finally { if ( $switched ) restore_current_blog(); }
	}
}
