<?php
defined( 'ABSPATH' ) || exit;

/** Captura en una petición pública separada la cola real de WordPress. */
class Digitalisimo_Integrations_Asset_Diagnostics {
	const QUERY = 'digitalisimo_perf_probe';
	private static $collecting = false;
	private static $buffer_level = 0;
	private static $resources = array();
	private static $widgets = array();

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_diagnostic', array( __CLASS__, 'run' ) );
		add_action( 'template_redirect', array( __CLASS__, 'begin_probe' ), 0 );
		add_action( 'wp_print_styles', array( __CLASS__, 'capture' ), 25 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'capture' ), 25 );
		add_action( 'shutdown', array( __CLASS__, 'finish_probe' ), 0 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'capture_widget' ) );
	}

	public static function render( $network = false ) {
		if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$default = $network ? network_home_url( '/' ) : home_url( '/' );
		$url = (string) ( $_GET['performance_url'] ?? $default );
		$result = get_transient( 'digitalisimo_perf_result_' . get_current_user_id() );
		echo '<h2>Diagnóstico de assets</h2><p>Consulta una URL pública de este sitio o, en la red, de un sitio de la red. Se capturan los handles encolados por WordPress; no se retira ningún recurso durante el diagnóstico.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'digitalisimo_performance_diagnostic' );
		echo '<input type="hidden" name="action" value="digitalisimo_performance_diagnostic"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '">';
		echo '<label for="digitalisimo_performance_url">URL del sitio</label> <input class="regular-text" type="url" id="digitalisimo_performance_url" name="url" value="' . esc_attr( $url ) . '" required> ';
		submit_button( 'Analizar URL', 'secondary', 'submit', false );
		echo '</form>';
		if ( ! is_array( $result ) ) return;
		if ( ! empty( $result['error'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $result['error'] ) . '</p></div>'; return; }
		echo '<p><strong>Resultado:</strong> ' . esc_html( $result['url'] ?? '' ) . ' · ' . count( $result['resources'] ?? array() ) . ' recursos encolados</p>';
		echo '<div class="table-responsive"><table class="widefat striped"><thead><tr><th>Recurso</th><th>Handle</th><th>Tipo</th><th>Origen</th><th>Dependencias</th><th>Posición</th><th>Versión</th><th>Estado</th></tr></thead><tbody>';
		foreach ( (array) ( $result['resources'] ?? array() ) as $row ) {
			echo '<tr><td><code>' . esc_html( $row['src'] ?? '' ) . '</code></td><td>' . esc_html( $row['handle'] ?? '' ) . '</td><td>' . esc_html( $row['type'] ?? '' ) . '</td><td>' . esc_html( $row['origin'] ?? '' ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['deps'] ?? array() ) ) ) . '</td><td>' . esc_html( $row['position'] ?? '' ) . '</td><td>' . esc_html( (string) ( $row['version'] ?? '' ) ) . '</td><td>' . esc_html( $row['status'] ?? '' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		$widgets = (array) ( $result['widgets'] ?? array() );
		echo '<h3>Elementor y Swiper</h3>';
		if ( ! $widgets ) echo '<p>No se capturaron widgets Elementor en esta URL. Esto no demuestra que Swiper sea prescindible para otros componentes.</p>';
		else {
			echo '<table class="widefat striped"><thead><tr><th>Widget</th><th>Dependencias JS</th><th>Dependencias CSS</th></tr></thead><tbody>';
			foreach ( $widgets as $widget ) echo '<tr><td>' . esc_html( $widget['name'] ?? '' ) . '</td><td>' . esc_html( implode( ', ', (array) ( $widget['scripts'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $widget['styles'] ?? array() ) ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '<p>' . ( ! empty( $result['swiper_required'] ) ? 'Swiper está declarado por un widget de esta página: conservarlo.' : 'No se encontró una dependencia declarada de Swiper en los widgets capturados. Sólo es una recomendación de revisión; no se descarga automáticamente.' ) . '</p>';
		echo '<h3>WooCommerce</h3><p>' . ( ! empty( $result['woocommerce_required'] ) ? 'Esta URL usa un contexto o widget WooCommerce: conservar assets de carrito, formularios y fragmentos.' : 'No se detectó contexto WooCommerce en esta URL. El minicart global y los shortcodes requieren revisión antes de cualquier descarga.' ) . '</p>';
	}

	public static function run() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_performance_diagnostic' );
		$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		$site_id = self::site_for_url( $url, $network );
		$result = array( 'error' => 'La URL debe pertenecer a este sitio o a un sitio de la red.' );
		if ( $site_id ) {
			$token = strtolower( wp_generate_password( 32, false, false ) );
			set_site_transient( 'digitalisimo_perf_probe_' . $token, array( 'site_id' => $site_id ), 2 * MINUTE_IN_SECONDS );
			$probe_url = add_query_arg( self::QUERY, $token, $url );
			$response = wp_safe_remote_get( $probe_url, array( 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 2 * MB_IN_BYTES, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
			if ( is_wp_error( $response ) ) $result = array( 'error' => $response->get_error_message() );
			elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) $result = array( 'error' => 'La URL respondió HTTP ' . wp_remote_retrieve_response_code( $response ) . '.' );
			else {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				$result = is_array( $data ) && ! empty( $data['digitalisimo_performance'] ) ? array( 'url' => $url, 'resources' => (array) ( $data['resources'] ?? array() ), 'widgets' => (array) ( $data['widgets'] ?? array() ), 'swiper_required' => ! empty( $data['swiper_required'] ), 'woocommerce_required' => ! empty( $data['woocommerce_required'] ) ) : array( 'error' => 'El sitio no devolvió la captura de assets. Comprueba la URL, caché de página o acceso HTTP interno.' );
			}
			delete_site_transient( 'digitalisimo_perf_probe_' . $token );
		}
		set_transient( 'digitalisimo_perf_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-seo&tab=performance' ) : admin_url( 'admin.php?page=digitalisimo-seo-performance' );
		wp_safe_redirect( $target );
		exit;
	}

	private static function site_for_url( $url, $network ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) return 0;
		$site_id = get_current_blog_id();
		if ( $network ) {
			$site = get_site_by_path( $parts['host'], $parts['path'] ?? '/' );
			if ( ! $site ) return 0;
			$site_id = (int) $site->blog_id;
		}
		$home = wp_parse_url( get_home_url( $site_id, '/' ) );
		if ( ! is_array( $home ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ?? '' ) || ( $parts['scheme'] ?? '' ) !== ( $home['scheme'] ?? '' ) || (int) ( $parts['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 ) ) return 0;
		$base = trailingslashit( $home['path'] ?? '/' );
		$path = $parts['path'] ?? '/';
		if ( 0 !== strpos( trailingslashit( $path ), $base ) ) return 0;
		$relative = ltrim( substr( $path, strlen( $base ) - 1 ), '/' );
		if ( preg_match( '#^(wp-admin/|wp-login\.php|wp-json/)#i', $relative ) ) return 0;
		return $site_id;
	}

	public static function begin_probe() {
		$token = sanitize_key( $_GET[ self::QUERY ] ?? '' );
		if ( ! $token || is_admin() || is_feed() || is_preview() ) return;
		$probe = get_site_transient( 'digitalisimo_perf_probe_' . $token );
		if ( ! is_array( $probe ) || (int) ( $probe['site_id'] ?? 0 ) !== get_current_blog_id() ) return;
		self::$collecting = true;
		self::$buffer_level = ob_get_level();
		ob_start();
	}

	public static function capture() {
		if ( ! self::$collecting ) return;
		self::capture_registry( wp_styles(), 'CSS' );
		self::capture_registry( wp_scripts(), 'JS' );
	}

	/** Elementor informa las dependencias de cada widget realmente renderizado. */
	public static function capture_widget( $widget ) {
		if ( ! self::$collecting || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) return;
		$name = (string) $widget->get_name();
		$scripts = method_exists( $widget, 'get_script_depends' ) ? (array) $widget->get_script_depends() : array();
		$styles = method_exists( $widget, 'get_style_depends' ) ? (array) $widget->get_style_depends() : array();
		self::$widgets[ $name ] = array( 'name' => $name, 'scripts' => $scripts, 'styles' => $styles );
	}

	private static function capture_registry( $registry, $type ) {
		foreach ( (array) $registry->queue as $handle ) {
			self::capture_handle( $registry, $type, $handle, false, array() );
		}
	}

	private static function capture_handle( $registry, $type, $handle, $dependency, $seen ) {
		if ( isset( $seen[ $handle ] ) ) return;
		$asset = $registry->registered[ $handle ] ?? null;
		if ( ! $asset ) return;
		$seen[ $handle ] = true;
		foreach ( (array) $asset->deps as $required ) self::capture_handle( $registry, $type, $required, true, $seen );
		$key = $type . ':' . $handle;
		if ( ! isset( self::$resources[ $key ] ) || ! $dependency ) {
			self::$resources[ $key ] = array(
				'handle' => $handle,
				'type' => $type,
				'src' => (string) $asset->src,
				'origin' => self::origin( (string) $asset->src ),
				'deps' => (array) $asset->deps,
				'position' => 'JS' === $type && ! empty( $registry->groups[ $handle ] ) ? 'Footer' : 'Header',
				'version' => (string) $asset->ver,
				'status' => $dependency ? 'Dependencia' : self::status( $handle, (string) $asset->src, $type ),
			);
		}
	}

	private static function origin( $src ) {
		if ( preg_match( '#^https?://#i', $src ) && wp_parse_url( $src, PHP_URL_HOST ) !== wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) return 'Externo';
		if ( false !== strpos( $src, '/plugins/elementor-pro/' ) ) return 'Elementor Pro';
		if ( false !== strpos( $src, '/plugins/elementor/' ) ) return 'Elementor';
		if ( false !== strpos( $src, '/plugins/woocommerce/' ) ) return 'WooCommerce';
		if ( false !== strpos( $src, '/plugins/digitalisimo-' ) ) return 'DIGITALÍSIMO';
		if ( false !== strpos( $src, '/themes/' ) ) return 'Tema';
		if ( false !== strpos( $src, '/wp-includes/' ) || false !== strpos( $src, '/wp-admin/' ) ) return 'WordPress';
		if ( false !== strpos( $src, '/plugins/' ) ) return 'Plugin';
		return 'Desconocido';
	}

	private static function status( $handle, $src, $type ) {
		if ( 'CSS' === $type && in_array( $handle, array( 'wp-block-library', 'wp-block-library-theme' ), true ) && Digitalisimo_Integrations_Performance_Manager::plain_elementor_page() ) return 'Revisar';
		if ( preg_match( '/^(jquery|wp-|elementor-frontend|elementor-pro-frontend|swiper)/', $handle ) ) return 'Crítico';
		if ( false !== strpos( $src, '/plugins/elementor' ) ) return 'Elementor';
		if ( false !== strpos( $src, '/plugins/woocommerce/' ) ) return 'Mantener';
		return 'Desconocido';
	}

	public static function finish_probe() {
		if ( ! self::$collecting ) return;
		self::capture();
		while ( ob_get_level() > self::$buffer_level ) ob_end_clean();
		if ( ! headers_sent() ) header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		$swiper_required = false;
		$woocommerce_required = false;
		foreach ( self::$widgets as $widget ) {
			foreach ( array_merge( $widget['scripts'], $widget['styles'] ) as $handle ) if ( false !== strpos( $handle, 'swiper' ) ) $swiper_required = true;
			if ( false !== strpos( $widget['name'], 'woocommerce' ) || false !== strpos( $widget['name'], 'cart' ) ) $woocommerce_required = true;
		}
		if ( class_exists( 'WooCommerce' ) ) {
			foreach ( array( 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' ) as $function ) if ( function_exists( $function ) && call_user_func( $function ) ) $woocommerce_required = true;
		}
		echo wp_json_encode( array( 'digitalisimo_performance' => true, 'resources' => array_values( self::$resources ), 'widgets' => array_values( self::$widgets ), 'swiper_required' => $swiper_required, 'woocommerce_required' => $woocommerce_required ) );
	}
}
