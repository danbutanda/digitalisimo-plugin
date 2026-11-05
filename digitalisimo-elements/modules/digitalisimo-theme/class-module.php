<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Convivencia con el tema DIGITALÍSIMO (`digitalisimo-theme`).
 *
 * El asistente de configuración del constructor considera que el tema ya es el adecuado y no puede cambiarlo por
 * otro (ni por AJAX ni por su ruta REST): el tema sólo se cambia desde Apariencia. Cuando el tema avisa de que viene
 * de otro (`digitalisimo_theme_switched`), la cabecera y el pie dinámicos del tema anterior pasan a plantillas del
 * constructor de temas; al revertir (`digitalisimo_theme_reverted`) o al dejar el tema desde Apariencia
 * (`switch_theme`) esas plantillas se retiran. El tema se reconoce por su carpeta habitual o, si una copia vive en
 * otra carpeta, por su `Update URI`. La conversión vive en `class-hello-header-footer.php` y sólo se carga cuando
 * hace falta: en un sitio que no cambia de tema el coste es un filtro, un `rest_pre_dispatch` que sale en la primera
 * comparación y dos `admin_init` que leen una opción con carga automática.
 */
final class Theme_Compat {
	const TEMPLATE   = 'digitalisimo-theme';
	const UPDATE_URI = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
	const RECORD     = 'digitalisimo_theme_switch';
	const CRON       = 'digitalisimo_theme_convert_hello';
	const ROUTE      = '#^/elementor/v1/onboarding/install-theme/?$#';
	const SWITCHER   = 'digitalisimo_theme_switch_user';

	public static function init() {
		add_filter( 'elementor/onboarding/is_elementor_theme_active', array( __CLASS__, 'onboarding' ) );
		add_action( 'admin_init', array( __CLASS__, 'block_hello_activation' ), 9 ); // Antes del 10 de elementor/app/modules/onboarding/module.php.
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'block_hello_rest' ), 10, 3 );
		add_action( 'digitalisimo_theme_switched', array( __CLASS__, 'switched' ), 10, 2 );
		add_action( 'digitalisimo_theme_reverted', array( __CLASS__, 'reverted' ) );
		add_action( self::CRON, array( __CLASS__, 'convert' ) );
		add_action( 'admin_init', array( __CLASS__, 'retry_pending' ), 20 );
		add_action( 'switch_theme', array( __CLASS__, 'remember_switcher' ), 10, 2 );
		add_action( 'switch_theme', array( __CLASS__, 'left' ), 10, 3 );
	}

	/**
	 * Quién activó el tema. WordPress avisa al tema nuevo (`after_switch_theme`) en la petición siguiente, que puede ser
	 * de un visitante o del cron; la petición que cambia el tema sí tiene al administrador, y el tema todavía no está
	 * cargado en ella. La conversión lo usa como autor cuando el registro no trae usuario.
	 */
	public static function remember_switcher( $new_name = '', $new_theme = null ) {
		if ( ! self::is_theme( $new_theme ) ) {
			return;
		}
		$user = (int) get_current_user_id();
		if ( $user && current_user_can( 'edit_theme_options' ) ) {
			update_option( self::SWITCHER, $user, false );
		}
	}

	/**
	 * Al dejar el tema DIGITALÍSIMO por otro (Apariencia, el Personalizador o cualquier `switch_theme()`), las plantillas
	 * de cabecera y pie generadas se retiran como en la reversión y el registro queda en `reverted`. Pasar a un hijo
	 * del propio tema no es dejarlo. Idempotente: la reversión de WP-CLI marca `reverted` antes de cambiar de tema, y
	 * un registro ya revertido no se toca.
	 */
	public static function left( $new_name = '', $new_theme = null, $old_theme = null ) {
		if ( ! self::is_theme( $old_theme ) || self::is_theme( $new_theme ) ) {
			return;
		}
		$record = get_option( self::RECORD );
		if ( ! is_array( $record ) || ! $record || ! empty( $record['reverted'] ) ) {
			return;
		}
		self::load();
		Hello_Header_Footer::left( $record );
	}

	/**
	 * ¿Es `$theme` (o su padre) el tema DIGITALÍSIMO? Por su carpeta habitual o, en otra carpeta (una copia con otro
	 * nombre), por su `Update URI`.
	 */
	public static function is_theme( $theme ) {
		if ( ! $theme instanceof \WP_Theme ) {
			return false;
		}
		$template = (string) $theme->get_template();
		if ( self::TEMPLATE === $template ) {
			return true;
		}
		$parent = $template === (string) $theme->get_stylesheet() ? $theme : wp_get_theme( $template );
		return self::UPDATE_URI === (string) $parent->get( 'UpdateURI' );
	}

	/** ¿El tema activo (o el padre del activo) es el tema DIGITALÍSIMO, en su carpeta habitual o en otra? */
	public static function active() {
		static $by_uri = array();
		$template = (string) get_template();
		if ( self::TEMPLATE === $template ) {
			return true;
		}
		if ( '' === $template ) {
			return false;
		}
		if ( ! isset( $by_uri[ $template ] ) ) {
			$by_uri[ $template ] = self::UPDATE_URI === (string) wp_get_theme( $template )->get( 'UpdateURI' );
		}
		return $by_uri[ $template ];
	}

	/** Con el tema activo, el asistente lo da por bueno; en cualquier otro caso conserva lo que recibió. */
	public static function onboarding( $active ) {
		return self::active() ? true : $active;
	}

	public static function message() {
		return 'El tema DIGITALÍSIMO está activo: el tema se cambia desde Apariencia.';
	}

	/** El asistente antiguo activa el tema de origen por AJAX (`switch_theme`); con el tema propio activo, no. */
	public static function block_hello_activation() {
		if ( ! self::active() || ! wp_doing_ajax() ) {
			return;
		}
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- sólo se bloquea, nunca se actúa.
		if ( 'elementor_activate_hello_theme' === $action ) {
			wp_send_json_error( array( 'message' => self::message() ), 403 );
		}
	}

	/** El asistente nuevo instala y activa el tema de origen por REST; con el tema propio activo, no. */
	public static function block_hello_rest( $result, $server, $request ) {
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_method' ) || 'POST' !== $request->get_method() || ! self::active() ) {
			return $result;
		}
		return preg_match( self::ROUTE, (string) $request->get_route() )
			? new \WP_Error( 'digitalisimo_theme_active', self::message(), array( 'status' => 403 ) )
			: $result;
	}

	public static function switched( $record, $old = null ) {
		self::load();
		Hello_Header_Footer::switched( (array) $record, $old );
	}

	public static function reverted( $record ) {
		self::load();
		Hello_Header_Footer::reverted( (array) $record );
	}

	public static function convert() {
		self::load();
		return Hello_Header_Footer::convert();
	}

	/** Una conversión que quedó pendiente (visita anónima, cron que no corrió) se completa en la administración. */
	public static function retry_pending() {
		if ( ! self::active() || wp_doing_ajax() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$record = get_option( self::RECORD );
		$state  = is_array( $record ) && isset( $record['header_footer'] ) && is_array( $record['header_footer'] ) ? $record['header_footer'] : array();
		if ( ! empty( $state['pending'] ) && empty( $state['blocked'] ) ) {
			self::convert();
		}
	}

	private static function load() {
		require_once __DIR__ . '/class-hello-header-footer.php';
	}
}
