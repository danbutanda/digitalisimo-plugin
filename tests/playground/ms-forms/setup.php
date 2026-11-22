<?php
/**
 * Preparación de ms-forms.sh (paso runPHP del blueprint): una red con el subsitio «uno», DIGITALÍSIMO Hosting, Backups y
 * Migración activados en toda la red, sin loopback ni correo, y Namecheap simulado: responde «disponible» sólo con la
 * clave CLAVE-RED-PG (así la consulta de dominios de un sitio dice qué clave usa). Deja /digi-probe/setup.json; un fallo
 * queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	if ( ! is_multisite() ) throw new RuntimeException( 'El blueprint no habilitó la red.' );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	$cut = "<?php\n// ms-forms: sin loopback ni correo; Namecheap simulado.\n"
		. "add_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) {\n"
		. "\t\$host = (string) parse_url( (string) \$url, PHP_URL_HOST );\n"
		. "\tif ( 'api.namecheap.com' === \$host ) { \$ok = false !== strpos( (string) \$url, 'ApiKey=CLAVE-RED-PG' ); return array( 'headers' => array(), 'body' => '<ApiResponse><CommandResponse><DomainCheckResult Domain=\"x.com\" Available=\"' . ( \$ok ? 'true' : 'false' ) . '\"/></CommandResponse></ApiResponse>', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); }\n"
		. "\tif ( in_array( \$host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );\n"
		. "\treturn \$pre;\n}, 10, 3 );\n"
		. "add_filter( 'pre_wp_mail', '__return_true' );\n";
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-ms-forms.php', $cut ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo.' );
	foreach ( array( 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $plugin ) {
		$activated = activate_plugin( $plugin, '', true );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
	}
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	$row = get_network();
	$site = wp_insert_site( array( 'domain' => $row->domain, 'path' => $row->path . 'uno/', 'title' => 'Uno', 'user_id' => $admin->ID ) );
	if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'site' => $site, 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
