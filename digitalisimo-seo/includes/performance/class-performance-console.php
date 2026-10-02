<?php
defined( 'ABSPATH' ) || exit;

/** Pantalla independiente de Rendimiento para sitio y red. */
class Digitalisimo_Integrations_Performance_Console {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'site_menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'legacy_redirect' ) );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 30 );
		add_action( 'admin_post_digitalisimo_performance_clear_cache', array( __CLASS__, 'clear_cache' ) );
	}

	public static function site_menu() {
		add_submenu_page( 'digitalisimo', 'Rendimiento y calidad', 'Rendimiento', 'manage_options', 'digitalisimo-performance', array( __CLASS__, 'site_page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'digitalisimo-network', 'Rendimiento y calidad de red', 'Rendimiento', 'manage_network_options', 'digitalisimo-network-performance', array( __CLASS__, 'network_page' ) );
	}

	/** Enlaza marcadores de SEO con la única consola de Rendimiento antes de enviar HTML. */
	public static function legacy_redirect() {
		$page = sanitize_key( $_GET['page'] ?? '' );
		$network = is_network_admin();
		$legacy_site = ! $network && 'digitalisimo-seo-performance' === $page;
		$legacy_network = $network && 'digitalisimo-network-seo' === $page && 'performance' === sanitize_key( $_GET['tab'] ?? '' );
		if ( ! $legacy_site && ! $legacy_network ) return;
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) return;
		wp_safe_redirect( self::page_url( $network, 'status' ) );
		exit;
	}

	private static function sections() {
		return array( 'audit' => 'Auditoría', 'tips' => 'Recomendaciones', 'images' => 'Imágenes', 'a11y' => 'Accesibilidad', 'seo' => 'SEO técnico', 'javascript' => 'JavaScript', 'css' => 'CSS', 'fonts' => 'Fuentes', 'preloads' => 'Preloads', 'status' => 'Diagnóstico de assets', 'measurements' => 'PageSpeed manual', 'tracking' => 'Google Tracking', 'cache' => 'Redis/Object Cache', 'debug' => 'Debug' );
	}

	private static function page_url( $network, $section ) {
		$url = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=' . $section ) : admin_url( 'admin.php?page=digitalisimo-performance&section=' . $section );
		$site_id = $network ? absint( $_GET['site_id'] ?? 0 ) : 0;
		if ( $site_id && get_site( $site_id ) ) $url = add_query_arg( 'site_id', $site_id, $url );
		if ( in_array( $section, array( 'status', 'measurements', 'audit', 'images', 'a11y', 'seo', 'css' ), true ) && ! empty( $_GET['performance_url'] ) ) {
			$selected_url = esc_url_raw( (string) wp_unslash( $_GET['performance_url'] ) );
			$resolved_site = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $selected_url, $network );
			if ( $resolved_site && ( ! $network || ! $site_id || $resolved_site === $site_id ) ) $url = add_query_arg( 'performance_url', $selected_url, $url );
		}
		return $url;
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
		$section = sanitize_key( $_GET['section'] ?? 'audit' );
		$sections = self::sections();
		if ( ! isset( $sections[ $section ] ) ) $section = 'audit';
		echo '<div class="wrap digitalisimo-admin-shell"><h1>Rendimiento y calidad · Digitalísimo' . ( $network ? ' · Red' : '' ) . '</h1>';
		self::navigation( $network, $section );
		if ( 'status' === $section ) {
			echo '<p>Las optimizaciones nuevas permanecen desactivadas hasta validar el sitio. El modo seguro conserva recursos centrales de Elementor, Pro y WooCommerce.</p>';
			echo '<table class="widefat striped"><tbody><tr><th>Modo seguro efectivo</th><td>' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_safe_mode' ) ? 'Activo' : 'Desactivado' ) . '</td></tr><tr><th>CSS Gutenberg efectivo</th><td>' . ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_gutenberg' ) ? 'Activado sólo en páginas Elementor elegibles' : 'Desactivado' ) . '</td></tr></tbody></table>';
			if ( $network ) self::network_sites();
		} elseif ( 'measurements' === $section ) Digitalisimo_Integrations_Performance_Measurements::render( $network );
		elseif ( 'audit' === $section ) Digitalisimo_Integrations_Quality_Audit::render_audit( $network );
		elseif ( 'tips' === $section ) self::recommendations();
		elseif ( 'a11y' === $section ) { self::settings( $network, 'a11y' ); Digitalisimo_Integrations_Quality_Audit::render_a11y( $network ); }
		elseif ( 'seo' === $section ) Digitalisimo_Integrations_Quality_Audit::render_seo( $network );
		elseif ( 'css' === $section ) { self::settings( $network, 'css' ); Digitalisimo_Integrations_Quality_Audit::render_css( $network ); Digitalisimo_Integrations_Performance_Migration::render( $network ); }
		elseif ( 'fonts' === $section ) { self::settings( $network, 'fonts' ); Digitalisimo_Integrations_Performance_Fonts::render( $network ); echo '<script>(function(){var mode=document.getElementById("perf_font_guard_mode")||document.getElementById("network_perf_font_guard_mode"),row=document.querySelector(".digitalisimo-font-manual-row");if(!mode||!row)return;function toggle(){row.hidden=mode.value==="off";}mode.addEventListener("change",toggle);toggle();})();</script>'; }
		elseif ( 'preloads' === $section ) { self::settings( $network, 'preloads' ); Digitalisimo_Integrations_Performance_Fonts::render( $network ); Digitalisimo_Integrations_Performance_Migration::render( $network ); }
		elseif ( 'javascript' === $section ) { self::settings( $network, 'javascript' ); Digitalisimo_Integrations_Performance_JavaScript::render( $network ); }
		elseif ( 'images' === $section ) { self::settings( $network, 'images' ); Digitalisimo_Integrations_Quality_Audit::render_images( $network ); Digitalisimo_Integrations_Quality_Audit::render_alt( $network ); Digitalisimo_Integrations_Performance_Images::render( $network ); }
		elseif ( 'tracking' === $section ) { self::settings( $network, 'tracking' ); Digitalisimo_Integrations_Performance_Tracking::render_site_kit( $network ); echo '<details><summary>Revisar Custom Code de Elementor</summary>'; Digitalisimo_Integrations_Performance_Migration::render( $network ); echo '</details>'; }
		elseif ( 'cache' === $section ) self::cache_section( $network );
		else Digitalisimo_Integrations_Performance_Debug::render( $network );
		if ( 'status' === $section ) Digitalisimo_Integrations_Asset_Diagnostics::render( $network );
		echo '</div>';
	}

	/** Buenas y malas prácticas al construir con WordPress y Elementor. Sólo informa. */
	private static function recommendations() {
		$groups = array(
			'Contenido y widgets' => array(
				array( true, 'Elimina los widgets que ya no se usan.' ),
				array( false, 'Ocultar elementos pesados en escritorio, tablet o móvil cuando ya no se necesitan: elimínalos.' ),
				array( false, 'Duplicar secciones completas sólo para mostrarlas en distintos dispositivos.' ),
				array( false, 'Widgets de Elementor innecesarios.' ),
				array( false, 'Enlaces con href="#" como destino final.' ),
			),
			'Carruseles y movimiento' => array(
				array( false, 'Demasiados carruseles o Swiper en una misma página.' ),
				array( true, 'Reduce los sliders con muchas imágenes.' ),
				array( false, 'Autoplay innecesario.' ),
				array( false, 'Demasiadas animaciones y efectos de movimiento.' ),
			),
			'Imágenes' => array(
				array( true, 'Usa imágenes WebP o AVIF cuando sea posible.' ),
				array( false, 'Subir imágenes mucho más grandes que su tamaño visible.' ),
				array( true, 'Define width y height en las imágenes.' ),
				array( true, 'Usa lazy load sólo en imágenes fuera de pantalla.' ),
				array( false, 'Aplicar lazy load a la imagen principal (hero o posible LCP).' ),
			),
			'Scripts, estilos y fuentes' => array(
				array( false, 'Plugins que cargan JS o CSS en páginas donde no se usan.' ),
				array( false, 'Duplicar scripts de Analytics, GTM o fuentes.' ),
				array( true, 'Mantén pocas fuentes y pocos pesos tipográficos.' ),
				array( false, 'CSS o JS personalizado innecesario.' ),
			),
			'Comprobación' => array(
				array( true, 'Revisa PageSpeed o Lighthouse después de cada cambio importante.' ),
				array( true, 'Prueba siempre en móvil, además de escritorio.' ),
			),
		);
		echo '<h2>Recomendaciones</h2><p>Buenas prácticas para construir páginas rápidas y claras con WordPress y Elementor. Esta pestaña sólo informa: no cambia nada del sitio.</p>';
		foreach ( $groups as $title => $items ) {
			echo '<h3>' . esc_html( $title ) . '</h3><ul style="list-style:none;margin-left:0">';
			foreach ( $items as $item ) echo '<li style="margin:6px 0"><span style="display:inline-block;min-width:118px;font-weight:600;color:' . ( $item[0] ? '#00a32a' : '#b26200' ) . '">' . ( $item[0] ? '✓ Recomendado' : '⚠ Evitar' ) . '</span> ' . esc_html( $item[1] ) . '</li>';
			echo '</ul>';
		}
	}

	private static function settings( $network, $section ) {
		if ( $network ) {
			echo '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=digitalisimo_save_network_seo' ) ) . '">';
			wp_nonce_field( 'digitalisimo_network_seo' );
			$site_id = absint( $_GET['site_id'] ?? 0 );
			echo '<input type="hidden" name="digitalisimo_active_tab" value="performance"><input type="hidden" name="digitalisimo_return" value="' . esc_attr( $section ) . '">';
			if ( $site_id && get_site( $site_id ) ) echo '<input type="hidden" name="digitalisimo_site_id" value="' . esc_attr( $site_id ) . '">';
			echo '<table class="form-table">';
			if ( 'tracking' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_tracking_network_fields();
			elseif ( 'fonts' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_font_network_fields();
			elseif ( 'preloads' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_preload_network_fields();
			elseif ( 'images' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_image_network_fields();
			elseif ( 'javascript' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_js_network_fields();
			elseif ( 'a11y' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_a11y_network_fields();
			else Digitalisimo_Integrations_SEO_Suite::performance_network_fields();
		} else {
			echo '<form method="post" action="options.php">';
			settings_fields( 'digitalisimo_integrations' );
			echo '<table class="form-table">';
			if ( 'tracking' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_tracking_site_fields();
			elseif ( 'fonts' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_font_site_fields();
			elseif ( 'preloads' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_preload_site_fields();
			elseif ( 'images' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_image_site_fields();
			elseif ( 'javascript' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_js_site_fields();
			elseif ( 'a11y' === $section ) Digitalisimo_Integrations_SEO_Suite::performance_a11y_site_fields();
			else Digitalisimo_Integrations_SEO_Suite::performance_site_fields();
		}
		echo '</table>';
		submit_button( 'Guardar rendimiento' );
		echo '</form>';
		if ( ! $network ) echo '<script>document.querySelectorAll(".digitalisimo-network-inherit").forEach(function(c){var f=document.getElementById(c.dataset.field);function s(){if(f)f.disabled=c.checked;}c.addEventListener("change",s);s();});</script>';
	}

	private static function cache_section( $network ) {
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$status = Digitalisimo_Integrations_Performance_Cache::status();
		echo '<h2>Object Cache</h2><p>Sitio seleccionado: ' . esc_html( get_home_url( $site_id, '/' ) ) . '</p><table class="widefat striped"><tbody><tr><th>Caché externa</th><td>' . ( $status['external'] ? 'Activa' : 'Inactiva' ) . '</td></tr><tr><th>Drop-in object-cache.php</th><td>' . ( $status['dropin'] ? 'Presente' : 'Ausente' ) . '</td></tr><tr><th>Redis</th><td>' . ( $status['redis'] ? 'Detectado en el drop-in' : 'No detectado' ) . '</td></tr></tbody></table>';
		echo '<p>Digitalísimo usa sólo su grupo <code>digitalisimo_performance</code>. Esta acción no ejecuta <code>wp_cache_flush()</code> ni modifica Redis.</p>';
		self::clear_form( $network, $site_id );
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
				$font = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_font_guard_mode' );
				$font = 'off' === $font ? ( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_font_swap' ) ? 'Swap' : 'Original' ) : 'Blindaje ' . ( array( 'auto' => 'seguro', 'strict' => 'estricto', 'manual' => 'manual' )[ $font ] ?? $font );
				$safe = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_safe_mode' ) ? 'Modo seguro' : 'Modo avanzado';
				$preload = Digitalisimo_Integrations_SEO_Resolver::option( 'perf_preload_mode' );
				echo '<tr><td><a href="' . esc_url( add_query_arg( 'site_id', (int) $site->blog_id, self::page_url( true, 'debug' ) ) ) . '">' . esc_html( home_url( '/' ) ) . '</a></td><td>' . esc_html( $css ) . '</td><td><a href="' . esc_url( add_query_arg( 'site_id', (int) $site->blog_id, self::page_url( true, 'fonts' ) ) ) . '">' . esc_html( $font ) . '</a></td><td>' . esc_html( $preload ) . '</td><td><a href="' . esc_url( add_query_arg( 'site_id', (int) $site->blog_id, self::page_url( true, 'tracking' ) ) ) . '">Auditar</a></td><td>' . ( $cache['external'] ? 'Activo' : 'Inactivo' ) . '</td><td>' . esc_html( $safe ) . '</td></tr>';
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
		$target = self::page_url( $network, 'cache' );
		if ( $network ) $target = add_query_arg( 'site_id', $site_id, $target );
		wp_safe_redirect( add_query_arg( 'cache_cleared', '1', $target ) );
		exit;
	}
}
