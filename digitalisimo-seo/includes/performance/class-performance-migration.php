<?php
defined( 'ABSPATH' ) || exit;

/** Inventario de Custom Code de Elementor y posibles duplicados. Sólo lectura. */
class Digitalisimo_Integrations_Performance_Migration {
	const OPTION = 'digitalisimo_performance_custom_code_inventory';

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_scan_custom_code', array( __CLASS__, 'scan_action' ) );
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

	private static function site_kit_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		return is_plugin_active( 'google-site-kit/google-site-kit.php' ) || ( is_multisite() && is_plugin_active_for_network( 'google-site-kit/google-site-kit.php' ) );
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
