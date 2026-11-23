<?php
/**
 * Sonda del ensayo de los ajustes que escribe el MCP de DIGITALÍSIMO Elements (mu-plugin que copia setup.php).
 *
 * - Sin red hacia fuera y sin correo.
 * - `?digi_ms=<paso>&token=<token>` (el token de /digi-probe/token.txt): `ping`; `users` (contraseña de aplicación de la
 *   administradora en /digi-probe/secrets.json); `seed` (el kit activo con colores, tipografías, `__globals__`,
 *   botones, formularios, puntos de corte, identidad y páginas de WooCommerce guardados en su meta; una página publicada
 *   con un título, relleno, fondo y CSS; las opciones del sitio con valores distintos de los del kit; CSS de Elementor
 *   purgado); `state` (la meta de los ajustes del kit, de la página y de su autoguardado, leída de la base, sus
 *   elementos y las opciones vigiladas).
 * Los avisos de PHP del módulo propio (modules/digitalisimo-mcp/) van a /digi-probe/php-errors.log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_MS_DIR = '/digi-probe';
const DIGI_MS_OPTIONS = array( 'blogname', 'blogdescription', 'site_icon', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'elementor_woocommerce_purchase_summary_page_id', 'woocommerce_shop_page_id' );

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_MS_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( false !== strpos( (string) $file, 'modules/digitalisimo-mcp/' ) ) {
			file_put_contents( DIGI_MS_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n", FILE_APPEND );
		}
		return false;
	}
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_ms_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_ms_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

/** Lectura directa de la base (sin caché de objetos) de los ajustes guardados de una entrada. */
function digi_ms_meta( int $id ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_page_settings' LIMIT 1", $id ) );
	return null === $raw ? null : maybe_unserialize( $raw );
}

function digi_ms_kit_settings(): array {
	return array(
		'system_colors'                => array(
			array( '_id' => 'primary', 'title' => 'Principal', 'color' => '#1A2B3C' ),
			array( '_id' => 'secondary', 'title' => 'Secundario', 'color' => '#445566' ),
			array( '_id' => 'text', 'title' => 'Texto', 'color' => '#111111' ),
			array( '_id' => 'accent', 'title' => 'Acento', 'color' => '#FF6600' ),
		),
		'custom_colors'                => array( array( '_id' => 'a1b2c3d', 'title' => 'Marca', 'color' => '#00AA88' ) ),
		'system_typography'            => array(
			array( '_id' => 'primary', 'title' => 'Principal', 'typography_typography' => 'custom', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '700' ),
			array( '_id' => 'text', 'title' => 'Texto', 'typography_typography' => 'custom', 'typography_font_family' => 'Open Sans', 'typography_font_weight' => '400' ),
		),
		'custom_typography'            => array( array( '_id' => 'f0e1d2c', 'title' => 'Titulares', 'typography_typography' => 'custom', 'typography_font_family' => 'Playfair Display' ) ),
		'__globals__'                  => array(
			'button_text_color'       => 'globals/colors?id=secondary',
			'button_background_color' => 'globals/colors?id=accent',
			'body_color'              => 'globals/colors?id=text',
		),
		'button_border_radius'         => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ),
		'button_padding'               => array( 'unit' => 'px', 'top' => '14', 'right' => '28', 'bottom' => '14', 'left' => '28', 'isLinked' => false ),
		'form_field_border_color'      => '#CCCCCC',
		'form_field_background_color'  => '#FAFAFA',
		'body_background_background'   => 'classic',
		'body_background_color'        => '#FFFFFF',
		'container_width'              => array( 'unit' => 'px', 'size' => 1200, 'sizes' => array() ),
		'viewport_mobile'              => 600,
		'viewport_tablet'              => 900,
		'viewport_md'                  => 601,
		'viewport_lg'                  => 901,
		'site_name'                    => 'Nombre viejo guardado en el kit',
		'site_description'             => 'Descripción vieja del kit',
		'site_logo'                    => array( 'id' => 99, 'url' => '' ),
		'site_favicon'                 => array( 'id' => 98, 'url' => '' ),
		'woocommerce_cart_page_id'     => 11,
		'woocommerce_checkout_page_id' => 12,
		'woocommerce_myaccount_page_id' => 13,
		'woocommerce_terms_page_id'    => 14,
		'woocommerce_purchase_summary_page_id' => 15,
		'woocommerce_shop_page_id'     => 16,
		'custom_css'                   => '.viejo{color:red}',
	);
}

function digi_ms_page_settings(): array {
	return array(
		'hide_title'            => '',
		'padding'               => array( 'unit' => 'px', 'top' => '40', 'right' => '0', 'bottom' => '40', 'left' => '0', 'isLinked' => false ),
		'background_background' => 'classic',
		'background_color'      => '#F4F4F4',
		'custom_css'            => 'selector .titulo > span { content: "azul"; }',
		'__globals__'           => array( 'background_color' => 'globals/colors?id=primary' ),
	);
}

function digi_ms_state(): array {
	$kit     = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
	$page    = (int) get_option( 'digi_ms_page', 0 );
	$options = array();
	foreach ( DIGI_MS_OPTIONS as $name ) {
		wp_cache_delete( $name, 'options' );
		$options[ $name ] = get_option( $name, null );
	}
	$mods     = get_option( 'theme_mods_' . get_option( 'stylesheet' ), array() );
	$admin    = get_user_by( 'login', 'admin' );
	$autosave = $page ? wp_get_post_autosave( $page, $admin->ID ) : false;
	global $wpdb;
	return array(
		'kit'        => $kit,
		'page'       => $page,
		'kit_meta'   => digi_ms_meta( $kit ),
		'page_meta'  => digi_ms_meta( $page ),
		'options'    => $options,
		'logo'       => is_array( $mods ) ? ( $mods['custom_logo'] ?? null ) : null,
		'autosave'   => $autosave ? (int) $autosave->ID : 0,
		'auto_meta'  => $autosave ? digi_ms_meta( (int) $autosave->ID ) : null,
		'auto_time'  => $autosave ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $autosave->ID ) ) : '',
		'page_time'  => $page ? (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $page ) ) : '',
		'page_data'  => (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1", $page ) ),
		'woo_tab'    => class_exists( 'WooCommerce', false ),
		'fix_loaded' => class_exists( '\Digitalisimo\Elements\Mcp\Settings_Merge', false ),
	);
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['digi_ms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_MS_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			digi_ms_out( array( 'error' => 'token' ), 403 );
		}
		$step  = sanitize_key( wp_unslash( $_GET['digi_ms'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$admin = get_user_by( 'login', 'admin' );
		switch ( $step ) {
			case 'ping':
				digi_ms_out( array( 'ready' => true, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'abilities' => function_exists( 'wp_register_ability' ) ) );
				break;
			case 'users':
				require_once ABSPATH . 'wp-admin/includes/user.php';
				$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-ms' ) );
				if ( is_wp_error( $created ) ) {
					digi_ms_out( array( 'error' => $created->get_error_message() ), 500 );
				}
				file_put_contents( DIGI_MS_DIR . '/secrets.json', wp_json_encode( array( 'password:admin' => $created[0] ) ) );
				digi_ms_out( array( 'users' => array( 'admin' => $admin->ID ) ) );
				break;
			case 'seed':
				wp_set_current_user( $admin->ID );
				$kit  = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
				$page = (int) get_option( 'digi_ms_page', 0 );
				if ( ! $page || ! get_post( $page ) ) {
					$page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página del ensayo' ) );
					update_post_meta( $page, '_elementor_edit_mode', 'builder' );
					update_post_meta( $page, '_elementor_template_type', 'wp-page' );
					update_post_meta( $page, '_elementor_version', ELEMENTOR_VERSION );
					$data = array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'b2c3d4e', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola', 'header_size' => 'h2' ), 'elements' => array() ) ) ) );
					update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
					update_option( 'digi_ms_page', $page, false );
				}
				// Primero las opciones: Elementor copia blogname y blogdescription al kit cuando cambian.
				$values = array( 'Sitio del ensayo', 'Lema del ensayo', '41', '101', '102', '103', '104', '105', '106' );
				foreach ( DIGI_MS_OPTIONS as $i => $name ) {
					update_option( $name, $values[ $i ] );
				}
				set_theme_mod( 'custom_logo', 42 );
				update_post_meta( $kit, '_elementor_page_settings', wp_slash( digi_ms_kit_settings() ) );
				update_post_meta( $page, '_elementor_page_settings', wp_slash( digi_ms_page_settings() ) );
				foreach ( array( $kit, $page ) as $id ) {
					$autosave = wp_get_post_autosave( $id, $admin->ID );
					if ( $autosave ) {
						wp_delete_post_revision( $autosave->ID );
					}
				}
				\Elementor\Plugin::$instance->files_manager->clear_cache();
				digi_ms_out( digi_ms_state() );
				break;
			case 'state':
				digi_ms_out( digi_ms_state() );
				break;
		}
		digi_ms_out( array( 'error' => 'paso desconocido' ), 400 );
	}
);
