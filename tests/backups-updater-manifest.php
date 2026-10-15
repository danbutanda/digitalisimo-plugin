<?php
/**
 * Actualizador de Backups: lee el índice de versiones de la última Release (sin el límite de la API de GitHub),
 * acepta sólo ZIPs propios del repositorio oficial, usa la API como respaldo (pidiéndole suficientes publicaciones)
 * y, si GitHub no responde o limita las consultas, mantiene a la vista la última versión encontrada.
 */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.52' );
define( 'MINUTE_IN_SECONDS', 60 );
$cache = array(); $site_options = array(); $calls = array(); $responses = array();
function add_filter() {} function add_action() {}
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function get_site_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site_options'][ $key ] = $value; return true; }
function wp_remote_get( $url ) {
	$GLOBALS['calls'][] = $url;
	$kind = false !== strpos( $url, 'api.github.com' ) ? 'api' : 'manifest';
	return $GLOBALS['responses'][ $kind ] ?? array( 'code' => 404, 'body' => '' );
}
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function esc_url_raw( $url ) { return $url; }
function get_bloginfo() { return '7.1'; }
require __DIR__ . '/../digitalisimo-backups/includes/class-digitalisimo-updater.php';
$u = 'Digitalisimo_Backups_Updater';
function check_updater( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function pending() { $t = Digitalisimo_Backups_Updater::check( (object) array( 'checked' => array() ) ); return $t->response[ Digitalisimo_Backups_Updater::FILE ] ?? null; }
function manifest( $version, $url = null ) {
	$url = $url ?? 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-backups-' . $version . '.zip';
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.10.15.389', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389', 'plugins' => array( 'digitalisimo-backups' => array( 'version' => $version, 'url' => $url ), 'digitalisimo-seo' => array( 'version' => '1.0.224', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-seo-1.0.224.zip' ) ) ) ) );
}
$api = array( 'code' => 200, 'body' => json_encode( array( array( 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/x', 'assets' => array( array( 'name' => 'digitalisimo-backups-1.0.60.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/digitalisimo-backups-1.0.60.zip' ) ) ) ) ) );

// 1. El índice de la última Release basta: no se consulta la API.
$responses = array( 'manifest' => manifest( '1.0.53' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.0.53' === $item->new_version && false !== strpos( $item->package, '/releases/download/v2026.10.15.389/digitalisimo-backups-1.0.53.zip' ), 'El índice de la Release ofrece la versión nueva.' );
check_updater( 1 === count( $calls ) && false !== strpos( $calls[0], '/releases/latest/download/digitalisimo-plugins.json' ), 'Sin consultar la API de GitHub.' );
check_updater( '1.0.53' === ( get_site_option( $u::LAST_KNOWN )['version'] ?? '' ), 'La versión encontrada queda registrada como última conocida.' );

// 2. El índice se comparte: otra consulta (otro plugin o la caché propia vencida) no lo vuelve a descargar.
delete_site_transient( $u::CACHE_KEY );
pending();
check_updater( 1 === count( $calls ), 'El índice en caché sirve a la siguiente consulta.' );

// 3. Un enlace que no es un ZIP de este plugin en el repositorio oficial se rechaza y se usa la API (con suficientes publicaciones).
foreach ( array( 'https://example.com/digitalisimo-backups-1.0.53.zip', 'https://github.com/otro/repo/releases/download/v1/digitalisimo-backups-1.0.53.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.53.zip' ) as $bad ) {
	$u::clear(); $calls = array(); $responses = array( 'manifest' => manifest( '1.0.53', $bad ), 'api' => $api );
	$item = pending();
	check_updater( $item && '1.0.60' === $item->new_version && 2 === count( $calls ) && false !== strpos( $calls[1], 'api.github.com' ) && false !== strpos( $calls[1], 'per_page=100' ), 'Enlace ajeno rechazado (' . $bad . '); la API queda de respaldo.' );
}

// 4. Una versión que no es numérica tampoco se acepta del índice.
$u::clear(); $calls = array(); $responses = array( 'manifest' => manifest( '1.0.53-beta' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.0.60' === $item->new_version && 2 === count( $calls ), 'Una versión con formato ajeno se descarta y la API queda de respaldo.' );

// 5. GitHub no responde o limita las consultas: se mantiene la última versión encontrada.
$u::clear(); $calls = array(); $responses = array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item = pending();
check_updater( $item && '1.0.60' === $item->new_version && 2 === count( $calls ), 'Con GitHub limitado sigue a la vista la última versión encontrada.' );

// 6. «Buscar actualizaciones» vacía también el índice en caché.
$responses = array( 'manifest' => manifest( '1.0.70' ) );
set_site_transient( $u::MANIFEST_CACHE, json_decode( manifest( '1.0.53' )['body'], true ) );
$u::clear();
$item = pending();
check_updater( $item && '1.0.70' === $item->new_version, 'Buscar actualizaciones descarga el índice de nuevo.' );

// 7. Con la versión instalada al día no hay nada pendiente.
$u::clear(); $responses = array( 'manifest' => manifest( '1.0.52' ) ); $site_options = array();
check_updater( null === pending(), 'La versión instalada no se ofrece como actualización.' );
echo "DIGITALÍSIMO Backups: actualizador con índice de la Release validado.\n";
