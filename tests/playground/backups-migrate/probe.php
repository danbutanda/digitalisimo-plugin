<?php
/**
 * Sonda del ensayo de migración, servida en /wp-content/digi-web/probe.php con el token del arnés: lo que el ensayo no
 * puede ver por HTTP sin ser administrador. `fingerprint` (huella del contenido: opciones sin las de Backups, entradas,
 * metas, usuarios y archivos de wp-content), `state` (claves de migración y su registro, historial con la marca de la
 * migración, trabajos, la última reubicación) y `secrets` (si alguna opción o archivo del almacén contiene alguno de
 * los textos que el ensayo envía en POST).
 */
require dirname( __DIR__, 2 ) . '/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
$token = trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) );
if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { http_response_code( 403 ); echo '{"error":"token"}'; exit; }
global $wpdb;
$what = (string) ( $_GET['what'] ?? 'state' );
$out = array();
if ( 'fingerprint' === $what ) {
	$skip = "option_name LIKE 'digitalisimo\\_backups\\_%' OR option_name LIKE '\\_transient%' OR option_name LIKE '\\_site\\_transient%' OR option_name IN ('cron','rewrite_rules','recently_activated')";
	$tables = $wpdb->get_col( 'SHOW TABLES' );
	foreach ( $tables as $table ) {
		$where = preg_match( '/options$/', $table ) ? ' WHERE NOT (' . $skip . ')' : ( preg_match( '/sitemeta$/', $table ) ? " WHERE meta_key NOT LIKE 'digitalisimo\\_backups\\_%' AND meta_key NOT LIKE '\\_site\\_transient%'" : ( preg_match( '/usermeta$/', $table ) ? " WHERE meta_key <> 'session_tokens'" : '' ) );
		$rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '`' . $where, ARRAY_A );
		$out['tables'][ $table ] = hash( 'sha256', wp_json_encode( $rows ) );
		// Las opciones, una por una: si algo cambia, el ensayo dice cuál.
		if ( preg_match( '/options$/', $table ) ) foreach ( (array) $rows as $row ) $out['options'][ $table . ':' . $row['option_name'] ] = hash( 'sha256', (string) $row['option_value'] );
	}
	$files = array();
	$root = WP_CONTENT_DIR;
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		$name = substr( $file->getPathname(), strlen( $root ) );
		if ( 0 === strpos( $name, '/digi-web' ) || 0 === strpos( $name, '/plugins/digitalisimo-elements' ) || 0 === strpos( $name, '/database' ) ) continue;
		$files[ $name ] = hash_file( 'sha256', $file->getPathname() );
	}
	ksort( $files );
	$out['files'] = $files;
	$out['file_count'] = count( $files );
} elseif ( 'secrets' === $what ) {
	$needles = json_decode( (string) file_get_contents( 'php://input' ), true );
	$haystack = wp_json_encode( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}", ARRAY_A ) );
	if ( is_multisite() ) $haystack .= wp_json_encode( $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta}", ARRAY_A ) );
	$store = defined( 'DIGITALISIMO_BACKUPS_STORAGE_DIR' ) ? DIGITALISIMO_BACKUPS_STORAGE_DIR : '';
	if ( $store && is_dir( $store ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $store, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) if ( preg_match( '/\.(json|jsonl|key)$/', $file->getFilename() ) ) $haystack .= (string) file_get_contents( $file->getPathname() );
	}
	$found = array();
	foreach ( (array) $needles as $i => $needle ) if ( is_string( $needle ) && strlen( $needle ) >= 16 && false !== strpos( $haystack, $needle ) ) $found[] = $i;
	$out['found'] = $found;
} else {
	$out['keys'] = Digitalisimo_Backups_Migrate::keys();
	$out['log'] = Digitalisimo_Backups_Migrate::public_log( 50 );
	$out['history'] = array_map( function( $row ) { return array( 'file' => $row['file'] ?? '', 'policy' => $row['policy'] ?? '', 'locked' => ! empty( $row['locked'] ), 'migration' => $row['migration'] ?? null ); }, (array) get_option( 'digitalisimo_backups_history', array() ) );
	$out['undo'] = Digitalisimo_Backups_Migrate::undo_target();
	$out['jobs'] = array_map( array( 'Digitalisimo_Backups_Jobs', 'view' ), Digitalisimo_Backups_Jobs::all() );
	$out['home'] = home_url();
	$out['blogname'] = get_option( 'blogname' );
	$out['relocation'] = get_option( 'digitalisimo_backups_last_relocation', null );
	$out['users'] = array_map( function( $user ) { return $user->user_login; }, get_users( array( 'blog_id' => 0 ) ) );
	if ( is_multisite() ) $out['sites'] = array_map( function( $site ) { return array( 'id' => (int) $site->blog_id, 'domain' => $site->domain, 'path' => $site->path ); }, get_sites() );
}
echo wp_json_encode( $out );
