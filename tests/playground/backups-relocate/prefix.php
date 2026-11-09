<?php
/**
 * Destino del ensayo (primer paso runPHP): instala otro WordPress con el prefijo `dst_` en la misma base de datos y deja
 * wp-config.php con ese prefijo. El que instaló Playground (`wp_`) se queda: es «otro WordPress» que la restauración no
 * puede tocar. Va antes de enableMultisite, que convierte en red la instalación con el prefijo nuevo.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$dir = '/digi-probe';
try {
	global $wpdb, $table_prefix;
	$config = ABSPATH . 'wp-config.php';
	$text = (string) file_get_contents( $config );
	$new = preg_replace( '/\$table_prefix\s*=\s*([\'"])[^\'"]*\1\s*;/', "\$table_prefix = 'dst_';", $text, 1, $count );
	if ( 1 !== $count ) throw new RuntimeException( 'wp-config.php no tiene una línea $table_prefix que cambiar.' );
	$home = home_url();
	$wpdb->set_prefix( 'dst_' );
	$table_prefix = 'dst_';
	unset( $GLOBALS['wp_roles'] );
	wp_cache_flush();
	$installed = wp_install( 'Destino', 'admin', 'admin@destino.test', true, '', 'password' );
	if ( empty( $installed['user_id'] ) ) throw new RuntimeException( 'wp_install() no creó el administrador con el prefijo dst_.' );
	update_option( 'siteurl', $home );
	update_option( 'home', $home );
	if ( false === file_put_contents( $config, $new ) ) throw new RuntimeException( 'No se pudo escribir wp-config.php.' );
	file_put_contents( $dir . '/prefix.json', wp_json_encode( array( 'prefix' => 'dst_', 'home' => $home, 'other_options' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_options' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
