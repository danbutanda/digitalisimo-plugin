<?php
/**
 * Sonda del ensayo de WooCommerce en el MCP de DIGITALÍSIMO Elements (mu-plugin que copia setup.php).
 *
 * - Sin red hacia fuera: toda petición HTTP a otro host devuelve un error (WooCommerce no consulta sugerencias ni
 *   servicios durante el ensayo) y el correo no sale.
 * - Una pasarela de pago de prueba (`digi_wm_test`) con una clave de tipo `password` y un `identity_token` de texto,
 *   para comprobar el enmascarado.
 * - `?digi_wm=<paso>&token=<token>` (el token de /digi-probe/token.txt) prepara o lee el estado del sitio en curso:
 *   `ping`, `activate-woo` (activa WooCommerce en este sitio y crea el gerente), `store` (ajustes y pasarela con una
 *   clave aleatoria), `mode` y `netmode` (modo «WooCommerce en el MCP» del sitio o de la red), `secret` (la huella
 *   sha256 de la clave guardada: el valor nunca sale por aquí), `post` (estado de una entrada o un pedido), `option`
 *   (una opción de ajustes sin secretos), `log` (la bitácora tal cual) y `abilities` (abilities registradas).
 * Los secretos del ensayo (contraseñas de aplicación y la clave) van a /digi-probe/secrets.json, que el arnés borra.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_WM_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_WM_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);

// Avisos de PHP, `_doing_it_wrong()` y obsolescencias del MCP de tienda: a /digi-probe/php-errors.log (el arnés falla
// con cualquier línea); lo de otros plugins no cuenta. No depende de WP_DEBUG.
function digi_wm_ours( string $text ): bool {
	return 1 === preg_match( '#modules/digitalisimo-woo/mcp/|digitalisimo_woo_mcp|digitalisimo-woo/(products|catalog|orders|customers|coupons|store-settings|reports)|[>"\']digitalisimo-woo[<"\']|Woo\\\\Mcp\\\\#i', $text );
}
function digi_wm_php_error( string $line ) {
	file_put_contents( DIGI_WM_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' · ' . $line . "\n", FILE_APPEND );
}
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( digi_wm_ours( (string) $file ) || digi_wm_ours( (string) $message ) ) {
			digi_wm_php_error( "PHP $severity: $message en $file:$line" );
		}
		return false;
	}
);
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run' ) as $digi_wm_hook ) {
	add_action(
		$digi_wm_hook,
		static function ( ...$args ) use ( $digi_wm_hook ) {
			$text = $digi_wm_hook . ': ' . implode( ' · ', array_map( static function ( $a ) { return is_scalar( $a ) ? (string) $a : ''; }, $args ) );
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? (array) wp_debug_backtrace_summary( null, 0, false ) : array();
			if ( digi_wm_ours( $text ) || digi_wm_ours( implode( ',', $trace ) ) ) {
				digi_wm_php_error( $text );
			}
		},
		10,
		3
	);
}

// Nada sale del sitio durante el ensayo.
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_wm_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

// Pasarela de prueba con una clave secreta.
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		if ( ! class_exists( 'Digi_WM_Test_Gateway' ) ) {
			// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
			class Digi_WM_Test_Gateway extends WC_Payment_Gateway {
				public function __construct() {
					$this->id                 = 'digi_wm_test';
					$this->method_title       = 'Pago de prueba';
					$this->method_description = 'Pasarela del ensayo con una clave secreta.';
					$this->has_fields         = false;
					$this->form_fields        = array(
						'enabled'        => array( 'title' => 'Activar', 'type' => 'checkbox', 'label' => 'Activar', 'default' => 'no' ),
						'title'          => array( 'title' => 'Título', 'type' => 'text', 'default' => 'Pago de prueba' ),
						'api_key'        => array( 'title' => 'Clave secreta', 'type' => 'password', 'default' => '' ),
						'identity_token' => array( 'title' => 'Token de identidad', 'type' => 'text', 'default' => '' ),
					);
					$this->init_settings();
					$this->title   = $this->get_option( 'title' );
					$this->enabled = $this->get_option( 'enabled' );
				}
			}
		}
		add_filter(
			'woocommerce_payment_gateways',
			static function ( $gateways ) {
				$gateways[] = 'Digi_WM_Test_Gateway';
				return $gateways;
			}
		);
	},
	11
);

/** Respuesta JSON de la sonda y fin de la petición. */
function digi_wm_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

/** Secretos del ensayo (fuera de los informes; el arnés borra el archivo al terminar). */
function digi_wm_secret( string $key, $value ) {
	$file = DIGI_WM_DIR . '/secrets.json';
	$all  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
	$all  = is_array( $all ) ? $all : array();
	$all[ $key ] = $value;
	file_put_contents( $file, wp_json_encode( $all ) );
}

/** Crea (o reutiliza) un usuario con su rol en el sitio actual y una contraseña de aplicación. */
function digi_wm_user( string $login, string $role ): int {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$user = get_user_by( 'login', $login );
	$id   = $user ? (int) $user->ID : (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 24 ), 'user_email' => $login . '@example.test', 'role' => $role ) );
	if ( is_multisite() ) {
		add_user_to_blog( get_current_blog_id(), $id, $role );
	} else {
		( new WP_User( $id ) )->set_role( $role );
	}
	$created = WP_Application_Passwords::create_new_application_password( $id, array( 'name' => 'digi-wm-' . get_current_blog_id() ) );
	if ( ! is_wp_error( $created ) ) {
		digi_wm_secret( 'password:' . $login . ':' . get_current_blog_id(), $created[0] );
	}
	return $id;
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['digi_wm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_WM_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			digi_wm_out( array( 'error' => 'token' ), 403 );
		}
		$step = sanitize_key( wp_unslash( $_GET['digi_wm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$arg  = isset( $_GET['value'] ) ? sanitize_text_field( wp_unslash( $_GET['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		switch ( $step ) {
			case 'ping':
				digi_wm_out( array( 'ready' => true, 'blog' => get_current_blog_id(), 'woo' => class_exists( 'WooCommerce' ), 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) );
				break;
			case 'activate-woo':
				$result = activate_plugin( 'woocommerce/woocommerce.php' );
				if ( is_wp_error( $result ) ) {
					digi_wm_out( array( 'error' => $result->get_error_message() ), 500 );
				}
				digi_wm_out( array( 'active' => is_plugin_active( 'woocommerce/woocommerce.php' ) ) );
				break;
			case 'store':
				if ( ! class_exists( 'WooCommerce' ) ) {
					digi_wm_out( array( 'error' => 'sin WooCommerce' ), 500 );
				}
				foreach ( array(
					'woocommerce_coming_soon'                  => 'no',
					'woocommerce_default_country'              => 'MX:DF',
					'woocommerce_currency'                     => 'MXN',
					'woocommerce_store_city'                   => 'Ciudad de México',
					'woocommerce_calc_taxes'                   => 'yes',
					'woocommerce_enable_coupons'               => 'yes',
					'woocommerce_allow_tracking'               => 'no',
					'woocommerce_show_marketplace_suggestions' => 'no',
					'woocommerce_onboarding_profile'           => array( 'skipped' => true ),
					'woocommerce_bacs_settings'                => array( 'enabled' => 'yes', 'title' => 'Transferencia' ),
				) as $name => $value ) {
					update_option( $name, $value );
				}
				$secret = 'sk_test_' . wp_generate_password( 24, false );
				$token  = 'tok_' . wp_generate_password( 20, false );
				update_option( 'woocommerce_digi_wm_test_settings', array( 'enabled' => 'yes', 'title' => 'Pago de prueba', 'api_key' => $secret, 'identity_token' => $token ) );
				digi_wm_secret( 'gateway_key:' . get_current_blog_id(), $secret );
				digi_wm_secret( 'gateway_token:' . get_current_blog_id(), $token );
				update_option( 'elementor_mcp_enabled', true );
				digi_wm_out( array( 'store' => true ) );
				break;
			case 'users':
				// base: admin, editora y suscriptora (sin WooCommerce); shop: el gerente (rol de WooCommerce).
				$roles = 'shop' === $arg ? array( 'gerente' => 'shop_manager' ) : array( 'admin' => 'administrator', 'editora' => 'editor', 'suscriptora' => 'subscriber' );
				$users = array();
				foreach ( $roles as $login => $role ) {
					if ( ! get_role( $role ) ) {
						digi_wm_out( array( 'error' => 'falta el rol ' . $role ), 500 );
					}
					$users[ $login ] = digi_wm_user( $login, $role );
				}
				digi_wm_out( array( 'users' => $users ) );
				break;
			case 'mode':
				if ( 'inherit' === $arg ) {
					update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => 'read', 'own' => array() ), false );
				} else {
					update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => $arg, 'own' => array( 'mode' ) ), false );
				}
				digi_wm_out( array( 'mode' => \Digitalisimo\Elements\Woo\Mcp\Settings::mode(), 'origin' => \Digitalisimo\Elements\Woo\Mcp\Settings::origin() ) );
				break;
			case 'netmode':
				update_site_option( 'digitalisimo_elements_woo_mcp_defaults', array( 'mode' => $arg ) );
				digi_wm_out( array( 'network' => get_site_option( 'digitalisimo_elements_woo_mcp_defaults' ) ) );
				break;
			case 'secret':
				$settings = get_option( 'woocommerce_digi_wm_test_settings', array() );
				digi_wm_out( array( 'key_sha256' => hash( 'sha256', (string) ( $settings['api_key'] ?? '' ) ), 'token_sha256' => hash( 'sha256', (string) ( $settings['identity_token'] ?? '' ) ) ) );
				break;
			case 'post':
				$id    = absint( $arg );
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : false;
				digi_wm_out( array( 'post_status' => get_post_status( $id ), 'order_status' => $order ? $order->get_status() : null, 'refunded' => $order ? (float) $order->get_total_refunded() : null ) );
				break;
			case 'option':
				$allowed = array( 'woocommerce_store_city', 'woocommerce_currency', 'autoload' );
				if ( 'autoload' === $arg ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					digi_wm_out( array( 'autoload' => $wpdb->get_results( "SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name LIKE 'digitalisimo_elements_woo_mcp%'", ARRAY_A ) ) );
				}
				digi_wm_out( array( 'value' => in_array( $arg, $allowed, true ) ? get_option( $arg ) : null ) );
				break;
			case 'log':
				digi_wm_out( array( 'log' => get_option( 'digitalisimo_elements_woo_mcp_log', array() ) ) );
				break;
			case 'selftest':
				// El detector de avisos funciona: un aviso que nombra el MCP de tienda queda anotado (y se retira).
				@unlink( DIGI_WM_DIR . '/php-errors.log' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				trigger_error( 'digitalisimo_woo_mcp autoprueba del detector', E_USER_NOTICE ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				_doing_it_wrong( 'Digitalisimo\\Elements\\Woo\\Mcp\\Autoprueba', 'autoprueba', '1.0' );
				$log = (string) @file_get_contents( DIGI_WM_DIR . '/php-errors.log' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@unlink( DIGI_WM_DIR . '/php-errors.log' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				digi_wm_out( array( 'notice' => false !== strpos( $log, 'autoprueba del detector' ), 'doing_it_wrong' => false !== strpos( $log, 'doing_it_wrong_run' ) ) );
				break;
			case 'abilities':
				$names = function_exists( 'wp_get_abilities' ) ? array_map( static function ( $a ) { return $a->get_name(); }, wp_get_abilities() ) : array();
				digi_wm_out( array( 'abilities' => array_values( $names ) ) );
				break;
		}
		digi_wm_out( array( 'error' => 'paso desconocido' ), 400 );
	},
	99
);
