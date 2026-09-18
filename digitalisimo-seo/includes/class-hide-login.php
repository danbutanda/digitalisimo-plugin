<?php
defined( 'ABSPATH' ) || exit;

/**
 * Oculta el acceso al escritorio tras una ruta privada.
 *
 * Con una ruta configurada, `wp-login.php`, `/wp-admin`, `/admin` y `/dashboard`
 * dejan de responder a las visitas sin sesión: el formulario de acceso se sirve
 * únicamente desde la ruta elegida. Con la sesión ya iniciada, `/wp-admin`
 * funciona exactamente igual que siempre.
 *
 * Salida de emergencia: definir `DIGITALISIMO_HIDE_LOGIN_DISABLE` como true en
 * wp-config.php restaura el acceso estándar sin tocar la base de datos.
 */
class Digitalisimo_Integrations_Hide_Login {
	const KEY = 'seo_hide_login_slug';

	/** Rutas que no pueden usarse porque son las que se quiere ocultar o las reserva WordPress. */
	private static $reserved = array( 'wp-admin', 'wp-login', 'wp-login-php', 'admin', 'dashboard', 'login', 'wp-content', 'wp-includes', 'wp-json', 'wp-signup', 'wp-activate', 'feed', 'robots-txt', 'sitemap', 'wp-sitemap-xml' );

	/** Acciones del formulario de acceso que deben seguir respondiendo sin sesión. */
	private static $public_actions = array( 'logout', 'rp', 'resetpass', 'postpass', 'lostpassword', 'confirmaction' );

	/** Se engancha al cargar el archivo principal: `plugins_loaded` es demasiado tarde para decidir la ruta. */
	public static function boot() {
		add_action( 'plugins_loaded', array( __CLASS__, 'prepare' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'serve' ), 1 );
	}

	/** Ruta privada activa, o cadena vacía cuando la función está desactivada. */
	public static function slug() {
		if ( defined( 'DIGITALISIMO_HIDE_LOGIN_DISABLE' ) && DIGITALISIMO_HIDE_LOGIN_DISABLE ) return '';
		$value = class_exists( 'Digitalisimo_Integrations_SEO_Resolver' )
			? Digitalisimo_Integrations_SEO_Resolver::option( self::KEY, '' )
			: ( ( (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() ) )[ self::KEY ] ?? '' );
		return is_string( $value ) ? self::sanitize_slug( $value ) : '';
	}

	/** Normaliza la ruta; devuelve cadena vacía si queda vacía o es una ruta reservada. */
	public static function sanitize_slug( $value ) {
		$slug = sanitize_title_with_dashes( remove_accents( (string) $value ) );
		return ( '' === $slug || in_array( $slug, self::$reserved, true ) ) ? '' : $slug;
	}

	/**
	 * Valida el campo del formulario. Vacío desactiva la función; una ruta
	 * inválida u ocupada conserva la anterior y avisa en pantalla.
	 */
	public static function sanitize_option( $input, $current ) {
		$current = (string) $current;
		if ( '' === trim( (string) $input ) ) return '';
		$slug = self::sanitize_slug( $input );
		if ( '' === $slug ) {
			add_settings_error( 'digitalisimo_integrations', 'digitalisimo_hide_login', __( 'La ruta privada de acceso no es válida o está reservada por WordPress. Se conservó la configuración anterior.', 'digitalisimo-integrations' ), 'error' );
			return $current;
		}
		if ( $slug !== $current && self::slug_taken( $slug ) ) {
			add_settings_error( 'digitalisimo_integrations', 'digitalisimo_hide_login', __( 'Ya existe contenido publicado en esa ruta. Elige otra para no dejarla inaccesible.', 'digitalisimo-integrations' ), 'error' );
			return $current;
		}
		return $slug;
	}

	/** Evita chocar con una entrada, página o término que ya use ese slug. */
	private static function slug_taken( $slug ) {
		if ( get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) ) ) return true;
		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) if ( get_term_by( 'slug', $slug, $taxonomy ) ) return true;
		return false;
	}

	/** URL pública del formulario de acceso con la ruta privada aplicada. */
	public static function login_url( $scheme = null ) {
		$slug = self::slug();
		if ( '' === $slug ) return site_url( 'wp-login.php', $scheme );
		$home = home_url( '/', $scheme );
		return get_option( 'permalink_structure' ) ? self::trailing( $home . $slug ) : $home . '?' . $slug;
	}

	/**
	 * Decide, antes de que WordPress resuelva la petición, si la URL pedida es
	 * el acceso oculto o un intento contra la ruta original.
	 */
	public static function prepare() {
		if ( '' === ( $slug = self::slug() ) ) return;
		global $pagenow;

		// WordPress redirige /admin, /dashboard y /login a wp-admin: eso delataría el escritorio.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		foreach ( array( 'site_url', 'network_site_url', 'wp_redirect', 'login_url', 'logout_url', 'lostpassword_url', 'register_url' ) as $filter ) {
			add_filter( $filter, array( __CLASS__, 'filter_url' ), 10, 1 );
		}

		$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		if ( ! is_admin() && ( false !== strpos( $uri, 'wp-login.php' ) || $path === untrailingslashit( site_url( 'wp-login', 'relative' ) ) ) ) {
			// La ruta original deja de existir: la petición se convierte en un 404 real.
			$_SERVER['REQUEST_URI'] = '/' . str_repeat( '-/', 10 );
			$pagenow                = 'index.php';
		} elseif ( $path === untrailingslashit( home_url( $slug, 'relative' ) ) || ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $slug ] ) ) ) {
			$pagenow = 'wp-login.php';
		}
	}

	/** Sirve el formulario en la ruta privada y bloquea el escritorio a quien no tiene sesión. */
	public static function serve() {
		if ( '' === self::slug() ) return;
		global $pagenow;

		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() && 'admin-post.php' !== $pagenow && ! in_array( self::requested_action(), self::$public_actions, true ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( 'wp-login.php' !== $pagenow ) return;

		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( get_option( 'permalink_structure' ) && $path !== self::trailing( $path ) ) {
			// Una sola URL canónica evita que la barra final genere un 404.
			wp_safe_redirect( self::login_url() . ( empty( $_SERVER['QUERY_STRING'] ) ? '' : '?' . $_SERVER['QUERY_STRING'] ) );
			exit;
		}

		global $error, $interim_login, $action, $user_login;
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/** Acción solicitada, ignorando valores no escalares que sólo buscan romper la comparación. */
	private static function requested_action() {
		$action = $_REQUEST['action'] ?? '';
		return is_scalar( $action ) ? (string) $action : '';
	}

	/** Reescribe cualquier URL que apunte a wp-login.php hacia la ruta privada. */
	public static function filter_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) || '' === self::slug() ) return $url;
		$target = self::login_url( is_ssl() ? 'https' : null );
		$parts  = explode( '?', $url, 2 );
		if ( ! isset( $parts[1] ) ) return $target;
		parse_str( $parts[1], $args );
		if ( isset( $args['login'] ) ) $args['login'] = rawurlencode( $args['login'] );
		return add_query_arg( $args, $target );
	}

	/** Respeta la preferencia de barra final del sitio. */
	private static function trailing( $value ) {
		$rewrite = $GLOBALS['wp_rewrite'] ?? null;
		return ( $rewrite && ! empty( $rewrite->use_trailing_slashes ) ) ? trailingslashit( $value ) : untrailingslashit( $value );
	}
}
