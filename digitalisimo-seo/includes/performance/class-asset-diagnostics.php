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
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$default = get_home_url( $site_id, '/' );
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
		echo '<h3>Fuentes locales</h3><p>Familias, pesos y formatos encontrados en hojas CSS locales encoladas. El uso above-the-fold requiere inspección visual o medición de navegador; aquí no se infiere.</p>';
		$fonts = (array) ( $result['fonts'] ?? array() );
		if ( ! $fonts ) echo '<p>No se localizaron reglas @font-face en las hojas locales inspeccionadas.</p>';
		else {
			echo '<table class="widefat striped"><thead><tr><th>Familia</th><th>Pesos</th><th>Formato</th><th>font-display</th><th>CSS</th><th>Peso CSS</th></tr></thead><tbody>';
			foreach ( $fonts as $font ) echo '<tr><td>' . esc_html( $font['family'] ?? '' ) . '</td><td>' . esc_html( implode( ', ', (array) ( $font['weights'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $font['formats'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $font['display'] ?? array() ) ) ) . '</td><td><code>' . esc_html( $font['css'] ?? '' ) . '</code></td><td>' . esc_html( size_format( (int) ( $font['bytes'] ?? 0 ) ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '<h3>Imágenes</h3><p>Revisión de atributos publicados, sin modificar archivos. El tamaño visual real y el ahorro potencial requieren medición de navegador.</p>';
		$images = (array) ( $result['images'] ?? array() );
		if ( ! $images ) echo '<p>No se encontraron imágenes HTML en esta URL.</p>';
		else {
			echo '<table class="widefat striped"><thead><tr><th>Imagen</th><th>width × height</th><th>srcset</th><th>sizes</th><th>Recomendación</th></tr></thead><tbody>';
			foreach ( $images as $image ) echo '<tr><td><code>' . esc_html( $image['src'] ?? '' ) . '</code></td><td>' . esc_html( $image['width'] ?? '—' ) . ' × ' . esc_html( $image['height'] ?? '—' ) . '</td><td>' . ( ! empty( $image['srcset'] ) ? 'Sí' : 'No' ) . '</td><td>' . ( ! empty( $image['sizes'] ) ? 'Sí' : 'No' ) . '</td><td>' . esc_html( $image['recommendation'] ?? '' ) . '</td></tr>';
			echo '</tbody></table>';
		}
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
				$result = is_array( $data ) && ! empty( $data['digitalisimo_performance'] ) ? array( 'site_id' => $site_id, 'url' => $url, 'resources' => (array) ( $data['resources'] ?? array() ), 'widgets' => (array) ( $data['widgets'] ?? array() ), 'fonts' => (array) ( $data['fonts'] ?? array() ), 'images' => (array) ( $data['images'] ?? array() ), 'swiper_required' => ! empty( $data['swiper_required'] ), 'woocommerce_required' => ! empty( $data['woocommerce_required'] ) ) : array( 'error' => 'El sitio no devolvió la captura de assets. Comprueba la URL, caché de página o acceso HTTP interno.' );
			}
			delete_site_transient( 'digitalisimo_perf_probe_' . $token );
		}
		set_transient( 'digitalisimo_perf_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=status' ) : admin_url( 'admin.php?page=digitalisimo-performance&section=status' );
		if ( $network && $site_id ) $target = add_query_arg( 'site_id', $site_id, $target );
		wp_safe_redirect( $target );
		exit;
	}

	/** Una captura de la red nunca se muestra como si perteneciera a otro subsitio. */
	public static function capture_belongs_to_site( $result, $site_id ) {
		if ( ! is_array( $result ) || empty( $result['url'] ) ) return false;
		$resolved = self::site_for_url( $result['url'], is_multisite() );
		return $resolved === (int) $site_id && ( ! isset( $result['site_id'] ) || (int) $result['site_id'] === (int) $site_id );
	}

	public static function site_for_url( $url, $network ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) return 0;
		$site_id = get_current_blog_id();
		if ( is_multisite() ) {
			$site = get_site_by_path( $parts['host'], $parts['path'] ?? '/' );
			if ( ! $site ) return 0;
			if ( ! $network && (int) $site->blog_id !== $site_id ) return 0;
			if ( $network ) $site_id = (int) $site->blog_id;
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
		if ( preg_match( '#/plugins/([^/]+)/#', $src, $plugin ) ) return 'Plugin: ' . sanitize_key( $plugin[1] );
		return 'Desconocido';
	}

	private static function status( $handle, $src, $type ) {
		if ( 'CSS' === $type && in_array( $handle, array( 'wp-block-library', 'wp-block-library-theme' ), true ) && Digitalisimo_Integrations_Performance_Manager::plain_elementor_page() ) return 'Revisar';
		if ( preg_match( '/^(jquery|wp-|elementor-frontend|elementor-pro-frontend|swiper)/', $handle ) ) return 'Crítico';
		if ( false !== strpos( $src, '/plugins/elementor' ) ) return 'Elementor';
		if ( false !== strpos( $src, '/plugins/woocommerce/' ) ) return 'Mantener';
		return 'Desconocido';
	}

	/** Sólo inspecciona CSS local dentro de uploads o wp-content, nunca URLs arbitrarias. */
	private static function local_css_path( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['path'] ) || strtolower( pathinfo( $parsed['path'], PATHINFO_EXTENSION ) ) !== 'css' ) return '';
		if ( isset( $parsed['host'] ) && strtolower( $parsed['host'] ) !== strtolower( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) return '';
		$uploads = wp_upload_dir();
		$roots = array( array( $uploads['baseurl'] ?? '', $uploads['basedir'] ?? '' ), array( content_url(), WP_CONTENT_DIR ) );
		foreach ( $roots as $root ) {
			$base = wp_parse_url( $root[0], PHP_URL_PATH );
			if ( ! $base || 0 !== strpos( $parsed['path'], trailingslashit( $base ) ) ) continue;
			$relative = rawurldecode( substr( $parsed['path'], strlen( trailingslashit( $base ) ) ) );
			$directory = realpath( $root[1] );
			$path = realpath( trailingslashit( $root[1] ) . $relative );
			if ( $directory && $path && 0 === strpos( $path, trailingslashit( $directory ) ) && is_file( $path ) && is_readable( $path ) && filesize( $path ) <= 512 * KB_IN_BYTES ) return $path;
		}
		return '';
	}

	private static function inspect_fonts() {
		$fonts = array();
		foreach ( self::$resources as $row ) {
			if ( 'CSS' !== $row['type'] ) continue;
			$path = self::local_css_path( $row['src'] );
			if ( ! $path ) continue;
			$css = file_get_contents( $path );
			if ( false === $css || ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $faces ) ) continue;
			foreach ( array_slice( $faces[1], 0, 300 ) as $face ) {
				if ( ! preg_match( '/font-family\s*:\s*([^;]+)/i', $face, $family_match ) ) continue;
				$family = trim( $family_match[1], " \t\n\r\0\x0B'\"" );
				$key = md5( $row['src'] . '|' . $family );
				if ( ! isset( $fonts[ $key ] ) ) $fonts[ $key ] = array( 'family' => $family, 'weights' => array(), 'formats' => array(), 'display' => array(), 'css' => $row['src'], 'bytes' => filesize( $path ) );
				foreach ( array( 'font-weight' => 'weights', 'format' => 'formats', 'font-display' => 'display' ) as $property => $field ) {
				$pattern = 'format' === $property ? '/format\(\s*[\'\"]?([^\'\")]+)/i' : '/' . $property . '\s*:\s*([^;]+)/i';
				if ( preg_match( $pattern, $face, $match ) ) $fonts[ $key ][ $field ][ trim( $match[1] ) ] = true;
				elseif ( 'font-display' === $property ) $fonts[ $key ][ $field ]['No declarado'] = true;
				}
			}
		}
		foreach ( $fonts as &$font ) foreach ( array( 'weights', 'formats', 'display' ) as $field ) $font[ $field ] = array_keys( $font[ $field ] );
		return array_values( $fonts );
	}

	/** Complementa la cola con recursos externos o preloads escritos directamente en HTML. */
	private static function inspect_html_resources( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) > 2 * MB_IN_BYTES ) return;
		if ( preg_match_all( '/<script\b[^>]*\bsrc\s*=\s*[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $html, $scripts ) ) {
			foreach ( $scripts[1] as $src ) {
				$src = html_entity_decode( $src, ENT_QUOTES, 'UTF-8' );
				if ( 'Externo' !== self::origin( $src ) ) continue;
				$known = false;
				foreach ( self::$resources as $resource ) if ( $resource['src'] === $src ) { $known = true; break; }
				if ( $known ) continue;
				$key = 'external:' . md5( $src );
				self::$resources[ $key ] = array( 'handle' => 'Sin handle', 'type' => 'Script externo', 'src' => $src, 'origin' => 'Externo', 'deps' => array(), 'position' => 'HTML', 'version' => '', 'status' => 'Revisar' );
			}
		}
		if ( preg_match_all( '/<link\b[^>]*>/i', $html, $links ) ) {
			foreach ( $links[0] as $tag ) {
				if ( ! preg_match( '/\bas\s*=\s*[\'\"]font[\'\"]/i', $tag ) || ! preg_match( '/\bhref\s*=\s*[\'\"]([^\'\"]+)[\'\"]/i', $tag, $href ) ) continue;
				$src = html_entity_decode( $href[1], ENT_QUOTES, 'UTF-8' );
				self::$resources[ 'font:' . md5( $src ) ] = array( 'handle' => 'Preload', 'type' => 'Fuente', 'src' => $src, 'origin' => self::origin( $src ), 'deps' => array(), 'position' => 'Header', 'version' => '', 'status' => 'Mantener' );
			}
		}
	}

	/** Sólo lee atributos que el HTML ya publica; no toca adjuntos ni metadatos. */
	private static function inspect_html_images( $html ) {
		$images = array();
		if ( ! is_string( $html ) || strlen( $html ) > 2 * MB_IN_BYTES || ! preg_match_all( '/<img\b[^>]*>/i', $html, $tags ) ) return $images;
		foreach ( array_slice( $tags[0], 0, 100 ) as $tag ) {
			$attributes = array();
			foreach ( array( 'src', 'width', 'height', 'srcset', 'sizes' ) as $name ) if ( preg_match( '/\b' . $name . '\s*=\s*[\'\"]([^\'\"]*)[\'\"]/i', $tag, $match ) ) $attributes[ $name ] = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
			if ( empty( $attributes['src'] ) ) continue;
			$recommendation = empty( $attributes['width'] ) || empty( $attributes['height'] ) ? 'Revisar dimensiones reales del adjunto' : ( empty( $attributes['srcset'] ) || empty( $attributes['sizes'] ) ? 'Revisar entrega responsiva' : 'Mantener; comprobar tamaño visual en navegador' );
			$images[] = array( 'src' => $attributes['src'], 'width' => $attributes['width'] ?? '', 'height' => $attributes['height'] ?? '', 'srcset' => ! empty( $attributes['srcset'] ), 'sizes' => ! empty( $attributes['sizes'] ), 'recommendation' => $recommendation );
		}
		return $images;
	}

	private static function swiper_dependency( $handle, $registry, $seen = array() ) {
		if ( false !== strpos( $handle, 'swiper' ) ) return true;
		if ( isset( $seen[ $handle ] ) || ! isset( $registry->registered[ $handle ] ) ) return false;
		$seen[ $handle ] = true;
		foreach ( (array) $registry->registered[ $handle ]->deps as $dependency ) if ( self::swiper_dependency( $dependency, $registry, $seen ) ) return true;
		return false;
	}

	/** Las plantillas pueden incluir un minicart o bloques WooCommerce fuera del contenido principal. */
	private static function woocommerce_markup( $html ) {
		if ( ! is_string( $html ) ) return false;
		return (bool) preg_match( '/\b(?:wc-block-[a-z0-9-]+|woocommerce-(?:cart|checkout|account|products?|mini-cart|menu-cart)|widget_shopping_cart|add_to_cart_button|single_add_to_cart_button)\b/i', $html );
	}

	public static function finish_probe() {
		if ( ! self::$collecting ) return;
		self::capture();
		$html = ob_get_contents();
		self::inspect_html_resources( $html );
		$images = self::inspect_html_images( $html );
		while ( ob_get_level() > self::$buffer_level ) ob_end_clean();
		if ( ! headers_sent() ) header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		$swiper_required = false;
		$woocommerce_required = false;
		foreach ( self::$widgets as $widget ) {
			if ( preg_match( '/carousel|slider|slides|testimonial/i', $widget['name'] ) ) $swiper_required = true;
			foreach ( $widget['scripts'] as $handle ) if ( self::swiper_dependency( $handle, wp_scripts() ) ) $swiper_required = true;
			foreach ( $widget['styles'] as $handle ) if ( self::swiper_dependency( $handle, wp_styles() ) ) $swiper_required = true;
			if ( false !== strpos( $widget['name'], 'woocommerce' ) || false !== strpos( $widget['name'], 'cart' ) ) $woocommerce_required = true;
		}
		if ( is_string( $html ) && preg_match( '/class\s*=\s*[\'\"][^\'\"]*\bswiper\b/i', $html ) ) $swiper_required = true;
		if ( self::woocommerce_markup( $html ) ) $woocommerce_required = true;
		if ( class_exists( 'WooCommerce' ) ) {
			foreach ( array( 'is_woocommerce', 'is_cart', 'is_checkout', 'is_account_page' ) as $function ) if ( function_exists( $function ) && call_user_func( $function ) ) $woocommerce_required = true;
		}
		echo wp_json_encode( array( 'digitalisimo_performance' => true, 'resources' => array_values( self::$resources ), 'widgets' => array_values( self::$widgets ), 'fonts' => self::inspect_fonts(), 'images' => $images, 'swiper_required' => $swiper_required, 'woocommerce_required' => $woocommerce_required ) );
	}
}
