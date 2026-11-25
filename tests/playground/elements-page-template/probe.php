<?php
/**
 * Sonda del ensayo de la plantilla de página de DIGITALÍSIMO Elements (mu-plugin que copia setup.php).
 *
 * - Sin red hacia fuera y sin correo.
 * - `?digi_pt=<paso>&token=<token>` (el token de /digi-probe/token.txt): `ping`; `users` (contraseña de aplicación de
 *   la administradora en /digi-probe/secrets.json); `page` (una página nueva de Elements con `tpl` como plantilla,
 *   `status` y unos ajustes de página; sin autoguardado); `state&id=<id>` (la plantilla, el título, el slug, el estado
 *   y los ajustes de la página y de su autoguardado, leídos de la base, sin caché).
 * Los avisos de PHP de los módulos propios (modules/digitalisimo-*) van a /digi-probe/php-errors.log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_PT_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_PT_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( false !== strpos( (string) $file, 'modules/digitalisimo-' ) ) {
			file_put_contents( DIGI_PT_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n", FILE_APPEND );
		}
		return false;
	}
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_pt_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
/**
 * Quién intenta escribir `_wp_page_template` (para explicar una falla): cada intento queda en
 * /digi-probe/template-writes.log con la petición, el valor y la pila de llamadas, sin argumentos. Se anota con la
 * prioridad 1, antes de `Page_Template`, así que también salen los intentos que ésta descarta (los de una revisión).
 */
$digi_pt_template_log = static function ( $check, $object_id, $meta_key, $meta_value ) {
	if ( '_wp_page_template' === $meta_key ) {
		$stack = array();
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$stack[] = ( isset( $frame['class'] ) ? $frame['class'] . '::' : '' ) . ( $frame['function'] ?? '' ) . ( isset( $frame['line'] ) ? '@' . basename( (string) ( $frame['file'] ?? '' ) ) . ':' . $frame['line'] : '' );
		}
		file_put_contents( DIGI_PT_DIR . '/template-writes.log', ( $_SERVER['REQUEST_METHOD'] ?? '' ) . ' ' . ( $_SERVER['REQUEST_URI'] ?? '' ) . " · post $object_id ← " . wp_json_encode( $meta_value ) . "\n  " . implode( "\n  ", array_slice( $stack, 1, 30 ) ) . "\n", FILE_APPEND );
	}
	return $check;
};
add_filter( 'update_post_metadata', $digi_pt_template_log, 1, 4 );
add_filter( 'add_post_metadata', $digi_pt_template_log, 1, 4 );
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_pt_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

/** Una meta leída directo de la base (sin caché de objetos); `null` si no existe. */
function digi_pt_meta( int $id, string $key ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $id, $key ) );
	return null === $raw ? null : maybe_unserialize( $raw );
}

function digi_pt_state( int $id ): array {
	global $wpdb;
	$row      = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_status FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
	$admin    = get_user_by( 'login', 'admin' );
	$autosave = wp_get_post_autosave( $id, $admin->ID );
	$data     = (string) digi_pt_meta( $id, '_elementor_data' );
	return array(
		'id'            => $id,
		'title'         => $row['post_title'] ?? null,
		'slug'          => $row['post_name'] ?? null,
		'status'        => $row['post_status'] ?? null,
		'template'      => digi_pt_meta( $id, '_wp_page_template' ),
		'settings'      => digi_pt_meta( $id, '_elementor_page_settings' ),
		'data'          => $data,
		'autosave'      => $autosave ? (int) $autosave->ID : 0,
		'auto_template' => $autosave ? digi_pt_meta( (int) $autosave->ID, '_wp_page_template' ) : null,
		'auto_settings' => $autosave ? digi_pt_meta( (int) $autosave->ID, '_elementor_page_settings' ) : null,
		'auto_data'     => $autosave ? (string) digi_pt_meta( (int) $autosave->ID, '_elementor_data' ) : '',
		'templates'     => array_keys( wp_get_theme()->get_page_templates( get_post( $id ) ) ),
		'fix_loaded'    => class_exists( '\Digitalisimo\Elements\Mcp\Settings_Merge', false ),
	);
}

add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_pt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_PT_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			digi_pt_out( array( 'error' => 'token' ), 403 );
		}
		$step  = sanitize_key( wp_unslash( $_GET['digi_pt'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$admin = get_user_by( 'login', 'admin' );
		switch ( $step ) {
			case 'ping':
				digi_pt_out( array( 'ready' => true, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'abilities' => function_exists( 'wp_register_ability' ) ) );
				break;
			case 'users':
				require_once ABSPATH . 'wp-admin/includes/user.php';
				$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-pt' ) );
				if ( is_wp_error( $created ) ) {
					digi_pt_out( array( 'error' => $created->get_error_message() ), 500 );
				}
				file_put_contents( DIGI_PT_DIR . '/secrets.json', wp_json_encode( array( 'password:admin' => $created[0] ) ) );
				digi_pt_out( array( 'users' => array( 'admin' => $admin->ID ) ) );
				break;
			case 'page':
				wp_set_current_user( $admin->ID );
				$tpl    = isset( $_GET['tpl'] ) ? sanitize_key( wp_unslash( $_GET['tpl'] ) ) : 'elementor_canvas'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$status = isset( $_GET['status'] ) && 'draft' === $_GET['status'] ? 'draft' : 'publish'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$n      = (int) get_option( 'digi_pt_n', 0 ) + 1;
				update_option( 'digi_pt_n', $n, false );
				$id = (int) wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_status' => $status,
						'post_title'  => 'Landing del ensayo ' . $n,
						'post_name'   => 'landing-del-ensayo-' . $n,
					)
				);
				update_post_meta( $id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
				$data = array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'b2c3d4e', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola', 'header_size' => 'h2' ), 'elements' => array() ) ) ) );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
				update_post_meta( $id, '_elementor_page_settings', array( 'hide_title' => 'yes', 'background_background' => 'classic', 'background_color' => '#05070A' ) );
				update_post_meta( $id, '_wp_page_template', 'default' === $tpl ? 'default' : $tpl );
				digi_pt_out( digi_pt_state( $id ) );
				break;
			case 'stale':
				// Un autoguardado viejo: su plantilla vuelve a `default`, como uno creado antes del arreglo o cuando la
				// entrada aún tenía otra (escrita en la revisión, sin pasar por la entrada).
				$id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$autosave = wp_get_post_autosave( $id, $admin->ID );
				if ( $autosave ) {
					update_metadata( 'post', (int) $autosave->ID, '_wp_page_template', 'default' );
				}
				digi_pt_out( digi_pt_state( $id ) );
				break;
			case 'state':
				digi_pt_out( digi_pt_state( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
		}
		digi_pt_out( array( 'error' => 'paso desconocido' ), 400 );
	}
);
