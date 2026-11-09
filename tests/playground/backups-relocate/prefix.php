<?php
/**
 * Destino del ensayo (primer paso runPHP): instala otro WordPress con el prefijo de /digi-probe/prefix.txt (por defecto
 * `dst_`) en la misma base de datos y deja wp-config.php con ese prefijo. El que instaló Playground (`wp_`) se queda: es
 * «otro WordPress» que la restauración no puede tocar. Va antes de enableMultisite, que convierte en red la instalación
 * con el prefijo nuevo. Con `wp_` no cambia nada.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$dir = '/digi-probe';
try {
	global $wpdb, $table_prefix;
	$target = trim( (string) @file_get_contents( $dir . '/prefix.txt' ) );
	if ( '' === $target ) $target = 'dst_';
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $target ) ) throw new RuntimeException( 'Prefijo de destino no válido.' );
	if ( $target === $wpdb->base_prefix ) {
		file_put_contents( $dir . '/prefix.json', wp_json_encode( array( 'prefix' => $target ) ) );
		return;
	}
	$config = ABSPATH . 'wp-config.php';
	$text = (string) file_get_contents( $config );
	$new = preg_replace( '/\$table_prefix\s*=\s*([\'"])[^\'"]*\1\s*;/', '$table_prefix = \'' . $target . '\';', $text, 1, $count );
	if ( 1 !== $count ) throw new RuntimeException( 'wp-config.php no tiene una línea $table_prefix que cambiar.' );
	$home = home_url();
	$wpdb->set_prefix( $target );
	$table_prefix = $target;
	unset( $GLOBALS['wp_roles'] );
	wp_cache_flush();
	$installed = wp_install( 'Destino', 'admin', 'admin@destino.test', true, '', 'password' );
	if ( empty( $installed['user_id'] ) ) throw new RuntimeException( 'wp_install() no creó el administrador con el prefijo ' . $target . '.' );
	update_option( 'siteurl', $home );
	update_option( 'home', $home );
	if ( false === file_put_contents( $config, $new ) ) throw new RuntimeException( 'No se pudo escribir wp-config.php.' );
	file_put_contents( $dir . '/prefix.json', wp_json_encode( array( 'prefix' => $target, 'home' => $home, 'other_options' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_options' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
