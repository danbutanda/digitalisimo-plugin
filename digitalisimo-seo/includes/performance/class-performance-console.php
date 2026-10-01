<?php
defined( 'ABSPATH' ) || exit;

/** Pantalla independiente de Rendimiento para sitio y red. */
class Digitalisimo_Integrations_Performance_Console {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'site_menu' ), 30 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 30 );
		add_action( 'admin_post_digitalisimo_performance_clear_cache', array( __CLASS__, 'clear_cache' ) );
	}

	public static function site_menu() {
		add_submenu_page( 'digitalisimo', 'Rendimiento', 'Rendimiento', 'manage_options', 'digitalisimo-performance', array( __CLASS__, 'site_page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'digitalisimo-network', 'Rendimiento de red', 'Rendimiento', 'manage_network_options', 'digitalisimo-network-performance', array( __CLASS__, 'network_page' ) );
	}

	private static function sections() {
		return array( 'status' => 'Estado', 'css' => 'CSS', 'fonts' => 'Fuentes', 'preloads' => 'Preloads', 'tracking' => 'Google Tracking', 'cache' => 'Redis/Object Cache', 'debug' => 'Debug' );
	}

	private static function page_url( $network, $section ) {
		return $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=' . $section ) : admin_url( 'admin.php?page=digitalisimo-performance&section=' . $section );
	}

	private static function navigation( $network, $active ) {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( self::sections() as $section => $label ) echo '<a class="nav-tab ' . ( $section === $active ? 'nav-tab-active' : '' ) . '" href="' . esc_url( self::page_url( $network, $section ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</h2>';
	}

	public static function site_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		self::render( false );
	}

	public static function network_page() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) return;
		self::render( true );
	}

	private static function render( $network ) {
		$section = sanitize_key( $_GET['section'] ?? 'status' );
		$sections = self::sections();
		if ( ! isset( $sections[ $section ] ) ) $section = 'status';
		echo '<div class="wrap digitalisimo-admin-shell"><h1>Rendimiento · Digitalísimo' . ( $network ? ' · Red' : '' ) . '</h1>';
		self::navigation( $network, $section );
		if ( 'status' === $section ) {
			echo '<p>Las optimizaciones nuevas permanecen desactivadas hasta validar el sitio. El modo seguro conserva recursos centrales de Elementor, Pro y WooCommerce.</p>';
			echo '<table class="widefat striped"><tbody><tr><th>Modo seguro efectivo</th><td>' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_safe_mode' ) ? 'Activo' : 'Desactivado' ) . '</td></tr><tr><th>CSS Gutenberg efectivo</th><td>' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gutenberg' ) ? 'Activado sólo en páginas Elementor elegibles' : 'Desactivado' ) . '</td></tr></tbody></table>';
			if ( $network ) self::network_sites();
		} elseif ( 'css' === $section ) self::settings( $network );
		elseif ( 'fonts' === $section ) Digitalisimo_Integrations_Performance_Fonts::render( $network );
		elseif ( 'preloads' === $section ) echo '<p>Los preloads se diagnostican; no se agregan automáticamente todavía.</p>';
		elseif ( 'tracking' === $section ) Digitalisimo_Integrations_Performance_Migration::render( $network );
		elseif ( 'cache' === $section ) self::cache_section( $network );
		else {
			echo '<p>El registro de descargas sólo se activa mediante <code>DIGITALISIMO_PERFORMANCE_DEBUG</code> o el filtro del mismo módulo.</p>';
			if ( ! $network ) Digitalisimo_Integrations_Performance_Manager::render_log();
		}
		if ( in_array( $section, array( 'status', 'css', 'fonts' ), true ) ) { Digitalisimo_Integrations_Asset_Diagnostics::render( $network ); Digitalisimo_Integrations_Performance_Measurements::render( $network ); }
		echo '</div>';
	}

	private static function settings( $network ) {
		if ( $network ) {
			echo '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=digitalisimo_save_network_seo' ) ) . '">';
			wp_nonce_field( 'digitalisimo_network_seo' );
			echo '<input type="hidden" name="digitalisimo_active_tab" value="performance"><input type="hidden" name="digitalisimo_return" value="performance"><table class="form-table">';
			Digitalisimo_Integrations_SEO_Suite::performance_network_fields();
		} else {
			echo '<form method="post" action="options.php">';
			settings_fields( 'digitalisimo_integrations' );
			echo '<table class="form-table">';
			Digitalisimo_Integrations_SEO_Suite::performance_site_fields();
		}
		echo '</table>';
		submit_button( 'Guardar rendimiento' );
		echo '</form>';
		if ( ! $network ) echo '<script>document.querySelectorAll(".digitalisimo-network-inherit").forEach(function(c){var f=document.getElementById(c.dataset.field);function s(){if(f)f.disabled=c.checked;}c.addEventListener("change",s);s();});</script>';
	}

	private static function cache_section( $network ) {
		$status = Digitalisimo_Integrations_Performance_Cache::status();
		echo '<h2>Object Cache</h2><table class="widefat striped"><tbody><tr><th>Caché externa</th><td>' . ( $status['external'] ? 'Activa' : 'Inactiva' ) . '</td></tr><tr><th>Drop-in object-cache.php</th><td>' . ( $status['dropin'] ? 'Presente' : 'Ausente' ) . '</td></tr><tr><th>Redis</th><td>' . ( $status['redis'] ? 'Detectado en el drop-in' : 'No detectado' ) . '</td></tr></tbody></table>';
		echo '<p>Digitalísimo usa sólo su grupo <code>digitalisimo_performance</code>. Esta acción no ejecuta <code>wp_cache_flush()</code> ni modifica Redis.</p>';
		self::clear_form( $network, get_current_blog_id() );
	}

	private static function clear_form( $network, $site_id ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_performance_clear_cache"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
		wp_nonce_field( 'digitalisimo_performance_clear_cache_' . $site_id );
		submit_button( 'Limpiar caché de rendimiento de este sitio', 'secondary', 'submit', false );
		echo '</form>';
	}

	private static function network_sites() {
		echo '<h2>Sitios de la red</h2><table class="widefat striped"><thead><tr><th>Sitio</th><th>CSS</th><th>Fuentes</th><th>Preloads</th><th>Tracking</th><th>Redis</th><th>Estado</th></tr></thead><tbody>';
		$cache = Digitalisimo_Integrations_Performance_Cache::status();
		$page = max( 1, absint( $_GET['site_page'] ?? 1 ) );
		foreach ( get_sites( array( 'number' => 50, 'offset' => ( $page - 1 ) * 50, 'orderby' => 'id' ) ) as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				$css = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gutenberg' ) ? 'Gutenberg contextual' : 'Sin limpieza';
				$font = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_font_swap' ) ? 'Swap' : 'Original';
				$safe = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_safe_mode' ) ? 'Modo seguro' : 'Modo avanzado';
				echo '<tr><td>' . esc_html( home_url( '/' ) ) . '</td><td>' . esc_html( $css ) . '</td><td><a href="' . esc_url( add_query_arg( 'site_id', (int) $site->blog_id, self::page_url( true, 'fonts' ) ) ) . '">' . esc_html( $font ) . '</a></td><td>Sin configurar</td><td><a href="' . esc_url( add_query_arg( 'site_id', (int) $site->blog_id, self::page_url( true, 'tracking' ) ) ) . '">Auditar</a></td><td>' . ( $cache['external'] ? 'Activo' : 'Inactivo' ) . '</td><td>' . esc_html( $safe ) . '</td></tr>';
			} finally { restore_current_blog(); }
		}
		echo '</tbody></table>';
		$total = (int) get_sites( array( 'count' => true ) );
		if ( $page > 1 ) echo '<a class="button" href="' . esc_url( add_query_arg( 'site_page', $page - 1, self::page_url( true, 'status' ) ) ) . '">Anterior</a> ';
		if ( $page * 50 < $total ) echo '<a class="button" href="' . esc_url( add_query_arg( 'site_page', $page + 1, self::page_url( true, 'status' ) ) ) . '">Siguiente</a>';
	}

	public static function clear_cache() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_clear_cache_' . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { Digitalisimo_Integrations_Performance_Cache::invalidate(); }
		finally { if ( $switched ) restore_current_blog(); }
		wp_safe_redirect( add_query_arg( 'cache_cleared', '1', self::page_url( $network, 'cache' ) ) );
		exit;
	}
}
