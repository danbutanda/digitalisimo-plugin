<?php
/**
 * Sonda del ensayo de UpdraftPlus (mu-plugin, en el origen y en el destino): sin loopback (un servidor de un solo proceso
 * se esperaría a sí mismo) ni correo, y `/?digi_ud=<qué>&token=<testigo>` con el testigo de /digi-probe/token.txt:
 *   backup  (origen) un respaldo completo con el código de UpdraftPlus 1.26.14 (boot_backup(), en esta petición) y la
 *           lista de sus piezas, con el final de su registro;
 *   state   nombre del sitio, dirección, entradas, plugins activos, filas del historial (sin rutas) y trabajos sin
 *           terminar; en una red, también sus sitios.
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'init', static function () {
	if ( empty( $_GET['digi_ud'] ) ) return;
	$token = trim( (string) @file_get_contents( '/digi-probe/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	$what = sanitize_key( (string) $_GET['digi_ud'] );
	$out = array();
	try {
		if ( 'backup' === $what ) {
			global $updraftplus;
			if ( ! is_object( $updraftplus ) ) throw new RuntimeException( 'UpdraftPlus no está cargado.' );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			$started = microtime( true );
			$out['booted'] = $updraftplus->boot_backup( 1, 1 );
			$out['seconds'] = round( microtime( true ) - $started, 1 );
			$dir = $updraftplus->backups_dir_location();
			$out['files'] = array();
			foreach ( (array) glob( $dir . '/*' ) as $path ) $out['files'][ basename( $path ) ] = (int) filesize( $path );
			$logs = glob( $dir . '/log.*.txt' );
			if ( $logs ) $out['log'] = substr( (string) file_get_contents( end( $logs ) ), -6000 );
		}
		wp_cache_flush();
		$history = array();
		if ( class_exists( 'Digitalisimo_Backups' ) ) foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$history[] = array( 'file' => (string) ( $row['file'] ?? '' ), 'origin' => (string) ( $row['origin'] ?? 'created' ), 'scope' => (string) ( $row['scope'] ?? '' ), 'import' => (array) ( $row['import'] ?? array() ), 'home' => (string) ( $row['upload']['home'] ?? '' ), 'sites' => (array) ( $row['sites'] ?? array() ) );
		}
		$sites = array();
		if ( is_multisite() ) foreach ( get_sites( array( 'number' => 50 ) ) as $site ) {
			switch_to_blog( $site->blog_id );
			$sites[ $site->blog_id ] = array( 'domain' => $site->domain, 'path' => $site->path, 'home' => get_option( 'home' ), 'blogname' => get_option( 'blogname' ), 'posts' => wp_list_pluck( get_posts( array( 'numberposts' => 10 ) ), 'post_title' ) );
			restore_current_blog();
		}
		$posts = get_posts( array( 'numberposts' => 10, 'post_status' => 'publish' ) );
		$out += array(
			'blogname' => get_option( 'blogname' ),
			'home' => get_option( 'home' ),
			'siteurl' => get_option( 'siteurl' ),
			'posts' => wp_list_pluck( $posts, 'post_title' ),
			'content' => $posts ? (string) $posts[0]->post_content : '',
			'active' => (array) get_option( 'active_plugins', array() ),
			'demo' => is_file( WP_PLUGIN_DIR . '/demo-origen/demo-origen.php' ),
			'mu' => is_file( WPMU_PLUGIN_DIR . '/mu-origen.php' ),
			'history' => $history,
			'sites' => $sites,
			'multisite' => is_multisite(),
			'jobs' => class_exists( 'Digitalisimo_Backups_Jobs' ) ? count( Digitalisimo_Backups_Jobs::active() ) : -1,
		);
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	nocache_headers();
	wp_send_json( $out );
}, 1 );
