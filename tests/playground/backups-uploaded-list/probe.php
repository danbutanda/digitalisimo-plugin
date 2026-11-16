<?php
/**
 * Sonda del ensayo «lo subido queda en la lista» (mu-plugin): sin loopback (un servidor de un solo proceso se esperaría a
 * sí mismo) ni correo, y `/?digi_uplist=<qué>&token=<testigo>` con el testigo de /digi-probe/token.txt:
 *   state       nombre del sitio, dirección, entradas, plugins de ejemplo, filas del historial (sin rutas), respaldo
 *               reciente válido y trabajos sin terminar;
 *   passphrase  guarda una frase de los respaldos (firma los nuevos con la clave de este sitio);
 *   set         cambia el nombre del sitio y, con `demo`, el plugin de ejemplo del sitio A (para restaurar por partes).
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'init', static function () {
	if ( empty( $_GET['digi_uplist'] ) ) return;
	$token = trim( (string) @file_get_contents( '/digi-probe/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	$what = sanitize_key( (string) $_GET['digi_uplist'] );
	$out = array();
	try {
		if ( 'passphrase' === $what ) {
			$set = Digitalisimo_Backups_Keys::set_passphrase( 'frase de los respaldos del sitio B para el ensayo de subidos' );
			$out['key'] = substr( (string) $set['key_id'], 0, 8 );
		}
		if ( 'set' === $what ) {
			if ( isset( $_GET['blogname'] ) ) update_option( 'blogname', sanitize_text_field( wp_unslash( (string) $_GET['blogname'] ) ) );
			if ( isset( $_GET['demo'] ) && is_dir( WP_PLUGIN_DIR . '/demo-a' ) ) file_put_contents( WP_PLUGIN_DIR . '/demo-a/demo-a.php', "<?php\n/* Plugin Name: Demo del sitio A */\n// " . sanitize_text_field( wp_unslash( (string) $_GET['demo'] ) ) . "\n" );
		}
		wp_cache_flush();
		$history = array();
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$history[] = array( 'file' => (string) ( $row['file'] ?? '' ), 'date' => (string) ( $row['date'] ?? '' ), 'size' => (int) ( $row['size'] ?? 0 ), 'origin' => (string) ( $row['origin'] ?? 'created' ), 'policy' => (string) ( $row['policy'] ?? '' ), 'signed' => ! empty( $row['signed'] ), 'same_site' => ! empty( $row['upload']['same_site'] ), 'home' => (string) ( $row['upload']['home'] ?? '' ) );
		}
		$posts = array_map( static function ( $post ) { return $post->post_title; }, get_posts( array( 'numberposts' => 10, 'post_status' => 'publish' ) ) );
		$demo = array();
		foreach ( array( 'a', 'b' ) as $site ) { $path = WP_PLUGIN_DIR . '/demo-' . $site . '/demo-' . $site . '.php'; $demo[ $site ] = is_file( $path ) ? (string) file_get_contents( $path ) : null; }
		$fresh = class_exists( 'Digitalisimo_Backups_Pre_Update' ) ? Digitalisimo_Backups_Pre_Update::fresh( 24 ) : null;
		$jobs = class_exists( 'Digitalisimo_Backups_Jobs' ) ? count( Digitalisimo_Backups_Jobs::active() ) : -1;
		$out += array( 'blogname' => get_option( 'blogname' ), 'home' => get_option( 'home' ), 'posts' => $posts, 'demo' => $demo, 'history' => $history, 'fresh' => $fresh ? $fresh['file'] : '', 'jobs' => $jobs );
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage();
	}
	nocache_headers();
	wp_send_json( $out );
}, 1 );
