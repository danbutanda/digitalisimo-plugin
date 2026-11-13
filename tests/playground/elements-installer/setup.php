<?php
/**
 * Preparación del ensayo del instalador (paso runPHP del blueprint de elements-installer.sh), según
 * /digi-probe/mode.json:
 *  - Límites de subida de un hosting típico en el php.ini de php-wasm: upload_max_filesize = 2M y post_max_size = 8M.
 *    PHP los lee al arrancar su runtime, así que se aplican cuando Playground lo rota (cada 400 peticiones; el arnés lo
 *    fuerza con peticiones a /digi-ping.php, que responde los valores en vigor).
 *  - mu-plugins/digi-installer-test.php (mu-plugin.php) y, en una red, el subsitio «uno».
 * Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
$dir = '/digi-probe';
try {
	$ini = '/internal/shared/php.ini';
	$text = (string) file_get_contents( $ini );
	foreach ( array( 'upload_max_filesize' => '2M', 'post_max_size' => '8M' ) as $key => $value ) {
		$text = preg_match( '/^' . $key . '\s*=/m', $text ) ? preg_replace( '/^' . $key . '\s*=.*$/m', $key . ' = ' . $value, $text ) : rtrim( $text ) . "\n" . $key . ' = ' . $value . "\n";
	}
	if ( false === file_put_contents( $ini, $text ) ) {
		throw new RuntimeException( 'No se escribió el php.ini de php-wasm.' );
	}
	file_put_contents( ABSPATH . 'digi-ping.php', '<?php echo ini_get( "upload_max_filesize" ), "|", ini_get( "post_max_size" );' );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/mu-plugin.php', WPMU_PLUGIN_DIR . '/digi-installer-test.php' ) ) {
		throw new RuntimeException( 'No se copió el mu-plugin del ensayo.' );
	}
	$sites = array();
	if ( is_multisite() ) {
		$network = get_network();
		$site = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'uno/', 'title' => 'Uno', 'user_id' => 1 ) );
		if ( is_wp_error( $site ) ) {
			throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		}
		$sites['uno'] = $site;
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => is_multisite(), 'sites' => $sites, 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
