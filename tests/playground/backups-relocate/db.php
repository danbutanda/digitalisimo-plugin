<?php
/**
 * Consulta de la base de datos del destino para el ensayo (GET /wp-content/digi-web/db.php?token=…&needle=…): tablas,
 * cuántas filas de cada tabla del prefijo de este sitio mencionan `needle` (sin contar guid, que se cuenta aparte) y lo
 * que importa de la red y del otro WordPress con prefijo wp_. Sólo lectura.
 */
$digi_token = trim( (string) @file_get_contents( __DIR__ . '/token.txt' ) );
if ( '' === $digi_token || ! hash_equals( $digi_token, (string) ( $_GET['token'] ?? '' ) ) ) { http_response_code( 403 ); exit; }
define( 'SHORTINIT', false );
require '/wordpress/wp-load.php';
global $wpdb;
$needle = (string) ( $_GET['needle'] ?? '' );
$out = array( 'prefix' => $wpdb->base_prefix, 'tables' => $wpdb->get_col( 'SHOW TABLES' ), 'hits' => array(), 'guid' => 0 );
foreach ( $out['tables'] as $table ) {
	if ( 0 !== strpos( $table, $wpdb->base_prefix ) ) continue;
	foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A ) as $column ) {
		if ( ! preg_match( '/char|text|blob|json/i', (string) $column['Type'] ) ) continue;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column['Field'] . '` LIKE %s', '%' . $wpdb->esc_like( $needle ) . '%' ) );
		if ( ! $count ) continue;
		if ( 'guid' === $column['Field'] ) { $out['guid'] += $count; continue; }
		$values = $wpdb->get_col( $wpdb->prepare( 'SELECT `' . $column['Field'] . '` FROM `' . $table . '` WHERE `' . $column['Field'] . '` LIKE %s LIMIT 3', '%' . $wpdb->esc_like( $needle ) . '%' ) );
		$out['hits'][ $table . '.' . $column['Field'] ] = array( 'count' => $count, 'sample' => array_map( function( $v ) use ( $needle ) { $at = stripos( $v, $needle ); return substr( $v, max( 0, $at - 60 ), 160 ); }, $values ) );
	}
}
if ( in_array( 'wp_options', $out['tables'], true ) ) $out['other_options'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_options' );
if ( is_multisite() ) $out['blogs'] = $wpdb->get_results( 'SELECT blog_id, domain, path FROM ' . $wpdb->blogs, ARRAY_A );
$out['user_roles'] = (bool) get_option( $wpdb->base_prefix . 'user_roles' );
$out['last_relocation'] = get_option( 'digitalisimo_backups_last_relocation' );
header( 'Content-Type: application/json; charset=utf-8' );
echo wp_json_encode( $out );
