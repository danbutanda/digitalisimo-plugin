<?php
/**
 * Sonda del ensayo «los administradores de este sitio siguen entrando» (mu-plugin): sin loopback (un servidor de un solo
 * proceso se esperaría a sí mismo) ni correo, y `/?digi_kadm=state&token=<testigo>` con el testigo de
 * /digi-probe/token.txt: nombre del sitio, dirección, entradas y páginas publicadas, los usuarios con sus roles, si son
 * superadministradores y las claves de sus permisos (sólo nombres de usuario: nunca un correo ni un hash), en una red
 * `site_admins` tal como está en `sitemeta`, y los trabajos sin terminar.
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'init', static function () {
	if ( empty( $_GET['digi_kadm'] ) ) return;
	$token = trim( (string) @file_get_contents( '/digi-probe/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	$out = array();
	try {
		wp_cache_flush();
		$users = array();
		foreach ( get_users( array( 'blog_id' => 0, 'orderby' => 'login' ) ) as $user ) $users[ $user->user_login ] = array( 'id' => (int) $user->ID, 'roles' => array_values( (array) get_userdata( $user->ID )->roles ) );
		$posts = array_map( static function ( $post ) { return $post->post_title; }, get_posts( array( 'numberposts' => 20, 'post_status' => 'publish', 'post_type' => array( 'post', 'page' ) ) ) );
		$jobs = class_exists( 'Digitalisimo_Backups_Jobs' ) ? count( Digitalisimo_Backups_Jobs::active() ) : -1;
		$out = array( 'blogname' => get_option( 'blogname' ), 'home' => get_option( 'home' ), 'posts' => $posts, 'users' => $users, 'jobs' => $jobs );
		// En una red: `site_admins` leído de `sitemeta` (no de la caché) y, de cada usuario, si es superadministrador y las
		// claves de sus permisos (`<prefijo>capabilities`, `<prefijo>user_level`), para ver que no queda el prefijo del respaldo.
		global $wpdb;
		$out['prefix'] = $wpdb->base_prefix;
		if ( is_multisite() ) {
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", 'site_admins', get_current_network_id() ) );
			$out['site_admins'] = array_values( (array) maybe_unserialize( (string) $raw ) );
			$out['blogs'] = array_map( 'intval', $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} ORDER BY blog_id" ) );
		}
		foreach ( $users as $login => $info ) {
			$out['users'][ $login ]['super'] = is_multisite() && is_super_admin( $info['id'] );
			$out['users'][ $login ]['caps'] = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND ( meta_key LIKE %s OR meta_key LIKE %s ) ORDER BY meta_key", $info['id'], '%capabilities', '%user_level' ) );
		}
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage();
	}
	nocache_headers();
	wp_send_json( $out );
}, 1 );
