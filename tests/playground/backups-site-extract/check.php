<?php
/**
 * Lo que el sitio restaurado guarda, para check.mjs (una petición normal del sitio, con el token del arnés): direcciones,
 * prefijo y tablas, plugins y tema, usuarios con sus roles y si su contraseña de la red abre, contenido, medios,
 * configuración de Backups del destino y los restos del dominio de la red en la base de datos.
 */
require '/wordpress/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
if ( ! hash_equals( trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) ), (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit( '{}' ); }
global $wpdb;
$state = json_decode( (string) @file_get_contents( '/digi-probe/state.json' ), true );
$out = array( 'home' => get_option( 'home' ), 'siteurl' => get_option( 'siteurl' ), 'prefix' => $wpdb->prefix, 'blogname' => get_option( 'blogname' ), 'plugins' => (array) get_option( 'active_plugins' ), 'stylesheet' => get_option( 'stylesheet' ) );
$out['tables'] = array_values( (array) $wpdb->get_col( 'SHOW TABLES' ) );
$out['users'] = array();
foreach ( get_users() as $u ) $out['users'][ $u->user_login ] = array( 'roles' => array_values( $u->roles ), 'password' => isset( $state['passwords'][ $u->user_login ] ) && wp_check_password( $state['passwords'][ $u->user_login ], $u->user_pass, $u->ID ) );
$out['posts'] = array();
foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) $out['posts'][ $p->ID ] = array( 'title' => $p->post_title, 'type' => $p->post_type, 'author' => ( get_userdata( $p->post_author ) ?: (object) array( 'user_login' => '' ) )->user_login, 'content' => $p->post_content, 'url' => get_permalink( $p ) );
$out['media_url'] = isset( $state['media'] ) ? wp_get_attachment_url( (int) $state['media'] ) : '';
$out['page_url'] = isset( $state['page'] ) ? get_permalink( (int) $state['page'] ) : '';
$uploads = wp_upload_dir();
$out['uploads'] = array( 'basedir' => $uploads['basedir'], 'baseurl' => $uploads['baseurl'], 'files' => array() );
if ( is_dir( $uploads['basedir'] ) ) foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads['basedir'], FilesystemIterator::SKIP_DOTS ) ) as $f ) if ( $f->isFile() && false === strpos( $f->getPathname(), '/elementor/css/' ) ) $out['uploads']['files'][ substr( $f->getPathname(), strlen( $uploads['basedir'] ) + 1 ) ] = hash_file( 'sha256', $f->getPathname() );
$out['backups'] = array( 'settings' => get_option( 'digitalisimo_backups_settings' ), 'history' => array_column( (array) get_option( 'digitalisimo_backups_history', array() ), 'policy' ), 'relocation' => get_option( 'digitalisimo_backups_last_relocation' ) ? true : false );
$out['tools'] = get_option( 'digitalisimo_tools_site_options' );
// Restos del sitio de la red: cada valor que nombra su dirección (red.test/dos) o sus medios en la red (uploads/sites/2),
// salvo guid y el informe de la reubicación (que nombra el origen a propósito). Los enlaces a la portada de la red y los
// correos @red.test son lo declarado: se cuentan aparte.
$out['leftovers'] = array();
$out['declared'] = array();
foreach ( $out['tables'] as $table ) {
	$columns = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A );
	foreach ( (array) $columns as $column ) {
		$name = $column['Field'];
		if ( 'guid' === $name ) continue;
		$rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '` WHERE `' . $name . "` LIKE '%red.test%'", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( 'digitalisimo_backups_last_relocation' === ( $row['option_name'] ?? '' ) ) continue;
			$value = (string) $row[ $name ];
			$plain = str_replace( '\\/', '/', $value );
			$hit = false;
			foreach ( array( 'red.test/dos', 'uploads/sites/2' ) as $needle ) if ( false !== ( $at = strpos( $plain, $needle ) ) ) { $out['leftovers'][] = $table . '.' . $name . ': ' . substr( $plain, max( 0, $at - 60 ), 160 ); $hit = true; break; }
			if ( ! $hit ) $out['declared'][] = $table . '.' . $name . ': ' . substr( $plain, max( 0, strpos( $plain, 'red.test' ) - 40 ), 100 );
		}
	}
}
echo wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
