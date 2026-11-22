<?php
/**
 * Sólo Playground: pone el drop-in compartido (object-cache.php de esta carpeta) y deja en su clave sin sal de
 * `alloptions` las opciones de «otro sitio» del mismo servidor (las de este con otra dirección y otro nombre), como si
 * otro WordPress con `wp_` y sin sal las hubiera guardado. Se incluye desde una petición sin el drop-in cargado (con
 * WordPress ya cargado), para que esta misma petición no las pise.
 */
global $wpdb;
@mkdir( '/digi-cache', 0777, true );
$all = array();
foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ) as $row ) {
	$all[ $row->option_name ] = $row->option_value;
}
$all['home']     = 'http://otro.test';
$all['blogname'] = 'Otro sitio del servidor';
file_put_contents( '/digi-cache/' . md5( $GLOBALS['table_prefix'] . 'options:alloptions' ) . '.txt', serialize( $all ) );
copy( __DIR__ . '/object-cache.php', WP_CONTENT_DIR . '/object-cache.php' );
