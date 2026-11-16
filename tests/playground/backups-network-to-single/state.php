<?php
/**
 * Lo que guarda el WordPress de destino, para drive.mjs (una petición normal, con el testigo del ensayo): direcciones,
 * tablas, plugins activos y carpetas de plugins y temas, tema, usuarios con sus roles y si su contraseña de la red abre,
 * entradas, medios con su SHA-256 y restos del sitio de la red en la base de datos. Vive en wp-content/digi-web, fuera de
 * lo que una restauración gestiona.
 */
require '/wordpress/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
if ( ! hash_equals( trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) ), (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit( '{}' ); }
global $wpdb;
wp_cache_flush();
$network = json_decode( (string) @file_get_contents( '/digi-probe/state.json' ), true );
$out = array( 'home' => get_option( 'home' ), 'siteurl' => get_option( 'siteurl' ), 'prefix' => $wpdb->prefix, 'blogname' => get_option( 'blogname' ), 'plugins' => (array) get_option( 'active_plugins' ), 'stylesheet' => get_option( 'stylesheet' ), 'template' => get_option( 'template' ), 'multisite' => is_multisite() );
$out['tables'] = array_values( (array) $wpdb->get_col( 'SHOW TABLES' ) );
sort( $out['tables'] );
$out['users'] = array();
foreach ( get_users() as $u ) $out['users'][ $u->user_login ] = array( 'roles' => array_values( $u->roles ), 'network_password' => isset( $network['passwords'][ $u->user_login ] ) && wp_check_password( $network['passwords'][ $u->user_login ], $u->user_pass, $u->ID ) );
$out['posts'] = array();
foreach ( get_posts( array( 'post_type' => array( 'post', 'page', 'attachment' ), 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) $out['posts'][ $p->ID ] = $p->post_title;
$uploads = wp_upload_dir();
$out['uploads'] = array( 'basedir' => $uploads['basedir'], 'baseurl' => $uploads['baseurl'], 'files' => array() );
if ( is_dir( $uploads['basedir'] ) ) foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads['basedir'], FilesystemIterator::SKIP_DOTS ) ) as $f ) if ( $f->isFile() ) $out['uploads']['files'][ substr( $f->getPathname(), strlen( $uploads['basedir'] ) + 1 ) ] = hash_file( 'sha256', $f->getPathname() );
ksort( $out['uploads']['files'] );
foreach ( array( 'plugin_dirs' => WP_PLUGIN_DIR, 'theme_dirs' => get_theme_root(), 'mu_plugins' => WPMU_PLUGIN_DIR ) as $key => $path ) { $names = array_values( array_diff( (array) @scandir( $path ), array( '.', '..', 'index.php' ) ) ); sort( $names ); $out[ $key ] = $names; }
$out['jobs'] = class_exists( 'Digitalisimo_Backups_Jobs' ) ? count( Digitalisimo_Backups_Jobs::active() ) : -1;
$out['history'] = array_map( static function ( $row ) { return array( 'file' => (string) ( $row['file'] ?? '' ), 'policy' => (string) ( $row['policy'] ?? '' ), 'origin' => (string) ( $row['origin'] ?? '' ), 'scope' => (string) ( $row['scope'] ?? '' ) ); }, (array) get_option( 'digitalisimo_backups_history', array() ) );
// Restos de la red: valores que nombran red.test (salvo guid, el informe de la reubicación y la lista de respaldos, que la nombran a propósito).
$out['leftovers'] = array();
foreach ( $out['tables'] as $table ) {
	foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A ) as $column ) {
		$name = $column['Field'];
		if ( 'guid' === $name ) continue;
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM `' . $table . '` WHERE `' . $name . "` LIKE '%red.test%'", ARRAY_A ) as $row ) {
			if ( 0 === strpos( (string) ( $row['option_name'] ?? '' ), 'digitalisimo_backups_' ) ) continue;
			$value = str_replace( '\\/', '/', (string) $row[ $name ] );
			if ( false !== strpos( $value, '@red.test' ) && false === strpos( $value, '//red.test' ) ) continue; // un correo
			$out['leftovers'][] = $table . '.' . $name . ': ' . substr( $value, max( 0, strpos( $value, 'red.test' ) - 60 ), 160 );
		}
	}
}
echo wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
