<?php
/**
 * Sonda de tests/playground/admin-notices.sh (mu-plugin). Pone a este WordPress en el estado del aviso de Tools
 * «la caché de objetos está devolviendo datos de otro sitio» (Cache_Notice, estado `foreign`) sin escribir nada en
 * wp-config.php:
 *  - una sal ajena (`WP_CACHE_KEY_SALT` que no es de DIGITALÍSIMO): la sal de Tools no hace nada (`others()`);
 *  - la caché se declara externa y el filtro `digitalisimo_same_site_check` dice que da el `home` de otro sitio, cuyo
 *    nombre sale de la opción `digi_an_foreign` (cambiarla cambia la situación y, con ella, la huella del aviso).
 * Con `?digi_an=` (sólo un administrador): `host:<nombre>` cambia ese nombre y `age:<id>:<segundos>` envejece el cierre
 * del aviso <id> del usuario actual (user meta `digitalisimo_notice_<id>`). Contesta JSON y termina.
 */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) define( 'WP_CACHE_KEY_SALT', 'sal-ajena-de-prueba:' );
add_action( 'muplugins_loaded', static function () { wp_using_ext_object_cache( true ); } );
add_filter( 'digitalisimo_same_site_check', static function ( $result ) {
	$host = (string) get_option( 'digi_an_foreign', 'otro.test' );
	return array( 'ok' => false, 'foreign' => $host, 'cached' => 'https://' . $host, 'db' => home_url(), 'message' => 'la caché de objetos de este servidor está devolviendo datos de otro sitio (' . $host . '); define WP_CACHE_KEY_SALT por sitio o usa una base de caché distinta' );
} );
add_action( 'init', static function () {
	if ( ! isset( $_GET['digi_an'] ) || ! current_user_can( 'manage_options' ) ) return;
	$cmd  = explode( ':', (string) wp_unslash( $_GET['digi_an'] ) );
	$out  = array( 'cmd' => $cmd[0] );
	if ( 'host' === $cmd[0] ) $out['saved'] = update_option( 'digi_an_foreign', sanitize_text_field( $cmd[1] ?? 'otro.test' ) );
	if ( 'age' === $cmd[0] ) {
		$key = 'digitalisimo_notice_' . preg_replace( '/[^a-z0-9_-]/', '', (string) ( $cmd[1] ?? '' ) );
		$row = get_user_meta( get_current_user_id(), $key, true );
		if ( is_array( $row ) ) { $row['t'] -= (int) ( $cmd[2] ?? 0 ); update_user_meta( get_current_user_id(), $key, $row ); $out['aged'] = true; }
	}
	wp_send_json( $out );
}, 1 );
// Un error fatal queda anotado para el arnés.
register_shutdown_function( static function () {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( '/digi-an/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . "\n" . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'] . "\n\n", FILE_APPEND );
} );
