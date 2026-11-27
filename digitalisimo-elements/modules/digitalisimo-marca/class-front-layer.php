<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Capa de marca en el front (docs/MARCA_PROPIA.md, fase 2), sólo con la marca propia en vigor (`Toggle::active()`) y con
 * el espejo de assets en el paquete (`marca/motor/js/frontend.min.js`):
 * - el HTML de cada página del front pasa por `Transform::html()` (buffer desde `template_redirect`; nunca en el
 *   escritorio, el editor, su vista previa, la API, AJAX, los feeds ni la CLI; sólo `text/html`). Todo o nada: si la página
 *   pide un asset del núcleo que no tiene copia en el espejo, sale sin transformar;
 * - el CSS generado (`uploads/elementor/css/*.css` y `google-fonts/`) se copia transformado a
 *   `uploads/digitalisimo-elements/…` la primera vez que una página lo pide y cada vez que el original cambia; las fuentes
 *   se copian tal cual; las copias se borran con `elementor/core/files/clear_cache`;
 * - los fragmentos de la lista cerrada (`fragmentos.html` de reglas.json): las rutas REST con `rest_post_dispatch`, los
 *   fragmentos del carrito con `woocommerce_add_to_cart_fragments`, el Menú del carrito y las reseñas de Google con
 *   `digitalisimo_elements_marca_html`;
 * - alias: `admin-ajax.php?action=digitalisimo_<x>` atiende `elementor_<x>` (las acciones de `ajax.usados_por_el_front`)
 *   y `/wp-json/digitalisimo-pro/v1/…` atiende `/elementor-pro/v1/…` (lo piden el espejo y la configuración transformada).
 * Apagada, nada de esto corre y el sitio sirve los originales.
 */
final class Front_Layer {
	const FRAGMENT_ROUTES = array( '#^/elementor-pro/v1/(refresh-loop|refresh-search|collection-loop/loop-preview)#', '#^/digitalisimo-elements/v1/woo/(quick-view|wishlist)#' );

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 0 );
		// Escritorio, editor y AJAX (D-12): sólo con el paquete transformado, donde el JS del editor ya va renombrado.
		add_action( 'admin_init', array( __CLASS__, 'admin_start' ), PHP_INT_MIN );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_alias' ), 5, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest_fragments' ), 50, 3 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragments' ), 999 );
		add_filter( 'digitalisimo_elements_marca_html', array( __CLASS__, 'fragment' ) );
		add_action( 'elementor/core/files/clear_cache', array( __CLASS__, 'clear_copies' ) );
	}

	/** Carpeta del plugin. */
	private static function plugin_dir(): string {
		return dirname( __DIR__, 2 );
	}

	/** Carpeta del espejo (filtrable para las pruebas). */
	public static function mirror_dir(): string {
		return (string) apply_filters( 'digitalisimo_elements_marca_mirror_dir', self::plugin_dir() . '/marca' );
	}

	/** ¿Trae el paquete el espejo? */
	public static function mirror_ready(): bool {
		if ( class_exists( Package::class ) && Package::transformed() ) {
			return 'on' === Package::mode();
		}
		return is_readable( self::mirror_dir() . '/motor/js/frontend.min.js' );
	}

	/** ¿Corre la capa en esta petición? */
	public static function on(): bool {
		return Toggle::active() && self::mirror_ready();
	}

	public static function start(): void {
		if ( ! self::on() || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || is_feed() || is_robots() ) {
			return;
		}
		// La vista previa del editor sólo con el paquete transformado (su JS ya va renombrado, D-12); con el espejo de
		// prueba, no.
		$packaged = class_exists( Package::class ) && Package::transformed();
		if ( ( ! $packaged && isset( $_GET['elementor-preview'] ) ) || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si la capa corre.
			return;
		}
		ob_start( array( __CLASS__, 'buffer' ) );
	}

	/**
	 * Escritorio y editor con la marca en vigor y el paquete transformado: la página por `Transform::html()` (con sus
	 * plantillas `text/template`) y las respuestas JSON de AJAX con su HTML. Nunca descargas, subidas ni admin-post.
	 */
	public static function admin_start(): void {
		if ( ! class_exists( Package::class ) || 'on' !== Package::mode() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		global $pagenow;
		if ( in_array( (string) $pagenow, array( 'admin-post.php', 'async-upload.php', 'export.php', 'media-upload.php' ), true ) ) {
			return;
		}
		ob_start( wp_doing_ajax() ? array( __CLASS__, 'ajax_buffer' ) : array( __CLASS__, 'admin_buffer' ) );
	}

	public static function admin_buffer( $html ) {
		return is_string( $html ) && '' !== $html && self::is_html() ? Transform::html( $html ) : $html;
	}

	/** Respuestas AJAX: si son JSON, cada cadena con HTML pasa por la capa; las demás salen igual. */
	public static function ajax_buffer( $out ) {
		if ( ! is_string( $out ) || false === stripos( $out, 'elementor' ) ) {
			return $out;
		}
		$data = json_decode( $out, true );
		if ( null === $data && 'null' !== trim( $out ) ) {
			return false !== strpos( $out, '<' ) ? Transform::html( $out ) : $out;
		}
		// Los datos con las mismas reglas que el JS transformado (D-12): HTML por la capa, identificadores y URL por las
		// reglas, el contenido de los elementos igual.
		$json = wp_json_encode( Transform::json_out( $data ) );
		return is_string( $json ) ? $json : $out;
	}

	/** El buffer de la página. */
	public static function buffer( $html ) {
		if ( ! is_string( $html ) || '' === $html || ! self::is_html() ) {
			return $html;
		}
		return self::page( $html );
	}

	/** ¿La respuesta es HTML? (sin Content-Type declarado, WordPress sirve HTML). */
	private static function is_html(): bool {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) ) {
				return false !== stripos( $header, 'text/html' );
			}
		}
		return true;
	}

	/** Una página completa: todo o nada. */
	public static function page( string $html ): string {
		if ( ! self::assets_covered( $html ) ) {
			return $html;
		}
		$out = Transform::html( $html );
		self::ensure_copies( $out );
		return $out;
	}

	/** ¿Cada asset del núcleo que pide la página tiene copia en el espejo? */
	public static function assets_covered( string $html ): bool {
		if ( ! preg_match_all( '#/plugins/digitalisimo-elements/(?:elementor|motor)/assets/([^"\'?\s)\\\\]+\.(?:css|js))#', $html, $m ) ) {
			return true;
		}
		$packaged = class_exists( Package::class ) && Package::transformed();
		foreach ( array_unique( $m[1] ) as $rel ) {
			$copy = $packaged ? Package::plugin_dir() . '/' . Package::rename( 'motor/assets/' . $rel ) : self::mirror_dir() . '/motor/' . preg_replace( '/elementor/i', Transform::P, $rel );
			if ( ! is_readable( $copy ) ) {
				return false;
			}
		}
		return true;
	}

	/** Copias transformadas del CSS generado que la página pide. */
	public static function ensure_copies( string $html ): void {
		if ( ! preg_match_all( '#/uploads/digitalisimo-elements/(css|google-fonts)/([\w./-]+?\.css)#', $html, $m, PREG_SET_ORDER ) || ! function_exists( 'wp_get_upload_dir' ) ) {
			return;
		}
		$base = (string) wp_get_upload_dir()['basedir'];
		foreach ( $m as $hit ) {
			if ( false !== strpos( $hit[2], '..' ) ) {
				continue;
			}
			self::copy( $base . '/elementor/' . $hit[1] . '/' . $hit[2], $base . '/digitalisimo-elements/' . $hit[1] . '/' . $hit[2] );
			if ( 'google-fonts' === $hit[1] ) {
				self::copy_fonts( $base . '/elementor/google-fonts', $base . '/digitalisimo-elements/google-fonts' );
			}
		}
	}

	/** Copia transformada de una hoja, sólo si el original es más nuevo. Escribe con temporal y rename. */
	public static function copy( string $src, string $dst ): bool {
		if ( ! is_readable( $src ) ) {
			return false;
		}
		if ( is_file( $dst ) && filemtime( $dst ) >= filemtime( $src ) ) {
			return true;
		}
		if ( function_exists( 'wp_mkdir_p' ) ? ! wp_mkdir_p( dirname( $dst ) ) : ! ( is_dir( dirname( $dst ) ) || mkdir( dirname( $dst ), 0755, true ) ) ) {
			return false;
		}
		$tmp = $dst . '.' . getmypid() . '.tmp';
		if ( false === file_put_contents( $tmp, Transform::css( (string) file_get_contents( $src ) ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
		return rename( $tmp, $dst );
	}

	/** Las fuentes de Google locales: binarios, tal cual, con el nombre de la carpeta nuevo. */
	private static function copy_fonts( string $src, string $dst ): void {
		$src_fonts = $src . '/fonts';
		if ( ! is_dir( $src_fonts ) ) {
			return;
		}
		foreach ( (array) glob( $src_fonts . '/*' ) as $file ) {
			$target = $dst . '/fonts/' . basename( (string) $file );
			if ( is_file( (string) $file ) && ! is_file( $target ) ) {
				if ( function_exists( 'wp_mkdir_p' ) ) {
					wp_mkdir_p( dirname( $target ) );
				}
				copy( (string) $file, $target );
			}
		}
	}

	/** Al vaciar la caché de CSS del constructor, las copias también. */
	public static function clear_copies(): void {
		if ( ! function_exists( 'wp_get_upload_dir' ) ) {
			return;
		}
		$dir = (string) wp_get_upload_dir()['basedir'] . '/digitalisimo-elements/css';
		foreach ( (array) glob( $dir . '/*.css' ) as $file ) {
			@unlink( (string) $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** Un fragmento HTML suelto (AJAX propio). */
	public static function fragment( $html ) {
		if ( ! self::on() ) {
			return $html;
		}
		return is_string( $html ) ? Transform::html( $html ) : Transform::deep_html( $html );
	}

	public static function cart_fragments( $fragments ) {
		return is_array( $fragments ) && self::on() ? Transform::deep_html( $fragments ) : $fragments;
	}

	/** Respuestas REST de la lista cerrada. */
	public static function rest_fragments( $response, $server = null, $request = null ) {
		if ( ! self::on() || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		foreach ( self::FRAGMENT_ROUTES as $pattern ) {
			if ( preg_match( $pattern, $route ) ) {
				$response->set_data( Transform::deep_html( $response->get_data() ) );
				break;
			}
		}
		return $response;
	}

	/** `/digitalisimo-pro/v1/…` se atiende como `/elementor-pro/v1/…` (con los mismos permisos y nonces). */
	public static function rest_alias( $result, $server = null, $request = null ) {
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $server ) || ! method_exists( $server, 'dispatch' ) ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/digitalisimo-pro/v1/' ) ) {
			return $result;
		}
		$request->set_route( '/elementor-pro/v1/' . substr( $route, strlen( '/digitalisimo-pro/v1/' ) ) );
		return $server->dispatch( $request );
	}

	/**
	 * `admin-ajax.php?action=digitalisimo_<x>` → `elementor_<x>`, sólo para las acciones del front de reglas.json (y sus
	 * formas `wp_ajax_nopriv_`); lo llama `Admin_Urls::translate_request()`.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function ajax_args( array $args ): array {
		$action = isset( $args['action'] ) && is_string( $args['action'] ) ? $args['action'] : '';
		if ( 0 !== strpos( $action, 'digitalisimo_' ) ) {
			return $args;
		}
		$original = 'elementor_' . substr( $action, 13 );
		if ( in_array( $original, (array) ( Transform::rules()['ajax']['usados_por_el_front'] ?? array() ), true ) ) {
			$args['action'] = $original;
		}
		return $args;
	}
}
