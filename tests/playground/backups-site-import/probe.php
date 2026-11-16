<?php
/**
 * Sonda del ensayo «importar un sitio en una red» (mu-plugin de la red): sin loopback ni correo, y
 * `/?digi_si=<qué>&token=<testigo>` con el testigo de /digi-probe/token.txt:
 *   state  cada sitio de la red (dirección, nombre, plugins activos, tema, temas habilitados, entradas con su autor,
 *          comentarios, medios con su SHA-256 y la huella de sus tablas sin lo que cambia solo: cron, transitorios y la
 *          configuración de Backups) y los usuarios de la red (hash de su contraseña, correo, metas globales y su rol en
 *          cada sitio);
 *   auth   wp_authenticate() de `login` con `pass` (el ensayo también entra por wp-login.php);
 *   fault  con `on=1`, la próxima importación falla justo después de fundir los usuarios (el gancho de las pruebas).
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( 'red.test', '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_action( 'digitalisimo_backups_site_import_merged', static function () {
	if ( is_file( '/digi-probe/fault.txt' ) ) {
		@unlink( '/digi-probe/fault.txt' );
		throw new Digitalisimo_Backups_Permanent_Error( 'Falla inyectada por el ensayo justo después de fundir los usuarios.' );
	}
} );
function digi_si_print( $table, $volatile = false ) {
	global $wpdb;
	$where = $volatile ? " WHERE option_name NOT LIKE '%transient%' AND option_name NOT IN ('cron','rewrite_rules','recently_activated','recovery_keys') AND option_name NOT LIKE 'digitalisimo_backups_%' AND option_name NOT LIKE '%\\_last\\_checked'" : '';
	$rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '`' . $where . ' ORDER BY 1', ARRAY_A );
	return hash( 'sha256', wp_json_encode( $rows ) );
}
function digi_si_files( $dir, $main = false ) {
	$out = array();
	if ( ! is_dir( $dir ) ) return $out;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$relative = substr( $file->getPathname(), strlen( $dir ) + 1 );
		// Los medios del principal no incluyen los de los demás sitios (uploads/sites/N).
		if ( $file->isFile() && ! ( $main && 0 === strpos( $relative, 'sites/' ) ) ) $out[ $relative ] = hash_file( 'sha256', $file->getPathname() );
	}
	ksort( $out );
	return $out;
}
add_action( 'init', static function () {
	if ( empty( $_GET['digi_si'] ) ) return;
	$token = trim( (string) @file_get_contents( '/digi-probe/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	global $wpdb;
	$what = sanitize_key( (string) $_GET['digi_si'] );
	$out = array();
	try {
		if ( 'fault' === $what ) {
			if ( ! empty( $_GET['on'] ) ) file_put_contents( '/digi-probe/fault.txt', '1' );
			else @unlink( '/digi-probe/fault.txt' );
			$out['fault'] = is_file( '/digi-probe/fault.txt' );
		}
		if ( 'auth' === $what ) {
			$user = wp_authenticate( wp_unslash( (string) ( $_GET['login'] ?? '' ) ), wp_unslash( (string) ( $_GET['pass'] ?? '' ) ) );
			$out['ok'] = ! is_wp_error( $user );
			$out['id'] = is_wp_error( $user ) ? 0 : (int) $user->ID;
		}
		if ( 'state' === $what ) {
			wp_cache_flush();
			$tables = (array) $wpdb->get_col( 'SHOW TABLES' );
			foreach ( get_sites( array( 'number' => 50 ) ) as $site ) {
				$id = (int) $site->blog_id;
				switch_to_blog( $id );
				$prefix = $wpdb->get_blog_prefix( $id );
				$print = array();
				foreach ( $tables as $table ) {
					if ( 0 !== strpos( $table, $prefix ) ) continue;
					$rest = substr( $table, strlen( $prefix ) );
					if ( 1 === $id && ( preg_match( '/^[0-9]+_/', $rest ) || in_array( $rest, array( 'users', 'usermeta', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log' ), true ) || false !== strpos( $rest, 'dgbk' ) ) ) continue;
					$print[ $rest ] = digi_si_print( $table, 'options' === $rest );
				}
				ksort( $print );
				$posts = array();
				foreach ( get_posts( array( 'numberposts' => 50, 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish' ) ) as $post ) $posts[ $post->post_title ] = (string) ( get_userdata( (int) $post->post_author )->user_login ?? '' );
				$comments = array();
				foreach ( get_comments( array( 'number' => 50 ) ) as $comment ) $comments[] = (string) ( $comment->user_id ? ( get_userdata( (int) $comment->user_id )->user_login ?? '?' ) : '' );
				$uploads = wp_upload_dir( null, false );
				$out['sites'][ $id ] = array(
					'domain' => $site->domain, 'path' => $site->path,
					'blogname' => get_option( 'blogname' ), 'home' => get_option( 'home' ), 'siteurl' => get_option( 'siteurl' ),
					'active_plugins' => array_values( (array) get_option( 'active_plugins', array() ) ),
					'stylesheet' => get_option( 'stylesheet' ), 'allowedthemes' => array_keys( (array) get_option( 'allowedthemes', array() ) ),
					'upload_path' => (string) get_option( 'upload_path' ), 'uploads_dir' => str_replace( ABSPATH, '', (string) $uploads['basedir'] ), 'uploads_url' => (string) $uploads['baseurl'],
					'backups_options' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$prefix}options` WHERE option_name LIKE 'digitalisimo\\_backups\\_%'" ),
					'posts' => $posts, 'comments' => $comments, 'content' => (string) $wpdb->get_var( "SELECT post_content FROM `{$prefix}posts` WHERE post_title = 'Entrada de la autora del origen' LIMIT 1" ),
					'files' => digi_si_files( (string) $uploads['basedir'], 1 === $id ), 'tables' => $print,
				);
				restore_current_blog();
			}
			$users = array();
			foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->users} ORDER BY ID", ARRAY_A ) as $row ) {
				$meta = array();
				$roles = array();
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d ORDER BY meta_key, umeta_id", $row['ID'] ), ARRAY_A ) as $m ) {
					if ( preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(?:([0-9]+)_)?capabilities$/', $m['meta_key'], $c ) ) $roles[ (int) ( '' === ( $c[1] ?? '' ) ? 1 : $c[1] ) ] = array_keys( (array) maybe_unserialize( $m['meta_value'] ) );
					elseif ( 'session_tokens' !== $m['meta_key'] ) $meta[ $m['meta_key'] ] = $m['meta_value'];
				}
				ksort( $roles );
				$users[ $row['user_login'] ] = array( 'id' => (int) $row['ID'], 'email' => $row['user_email'], 'pass' => hash( 'sha256', $row['user_pass'] ), 'roles' => $roles, 'meta' => hash( 'sha256', wp_json_encode( array_filter( $meta, static function ( $key ) use ( $wpdb ) { return ! preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '[0-9]+_/', $key ); }, ARRAY_FILTER_USE_KEY ) ) ), 'super' => is_super_admin( (int) $row['ID'] ) );
			}
			$out['users'] = $users;
			$out['tables'] = $tables;
			$out['jobs'] = class_exists( 'Digitalisimo_Backups_Jobs' ) ? count( Digitalisimo_Backups_Jobs::active() ) : -1;
			$out['maintenance'] = is_file( ABSPATH . '.maintenance' );
			$out['history'] = array_map( static function ( $row ) { return array( 'file' => (string) ( $row['file'] ?? '' ), 'scope' => (string) ( $row['scope'] ?? '' ), 'origin' => (string) ( $row['origin'] ?? '' ), 'locked' => ! empty( $row['locked'] ), 'site_copy' => (int) ( $row['site_copy']['site_id'] ?? 0 ) ); }, array_values( (array) get_site_option( Digitalisimo_Backups::HISTORY, get_option( Digitalisimo_Backups::HISTORY, array() ) ) ) );
		}
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
	}
	nocache_headers();
	wp_send_json( $out );
}, 1 );
