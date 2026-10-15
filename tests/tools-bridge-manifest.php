<?php
/**
 * Puente de actualización de Tools (< 1.0.17) incluido en SEO: lee el índice de versiones de la última Release (sin
 * el límite de la API de GitHub), acepta sólo ZIPs de Tools del repositorio oficial, usa la API como respaldo,
 * mantiene a la vista la última versión encontrada si GitHub falla y «Buscar actualizaciones» descarga el índice de nuevo.
 */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'MINUTE_IN_SECONDS', 60 );
$cache = array(); $site_options = array(); $calls = array(); $responses = array();
function add_filter() {} function add_action() {}
function get_file_data() { return array( 'Version' => '1.0.15' ); }
function is_multisite() { return true; }
function is_network_admin() { return true; }
function current_user_can() { return true; }
function check_admin_referer() { return true; }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); return true; }
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
function get_bloginfo() { return '6.8'; }
function sanitize_key( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_query_arg( ...$args ) { $url = array_pop( $args ); $params = 1 === count( $args ) ? (array) $args[0] : array( $args[0] => $args[1] ); return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $params ); }
function wp_get_referer() { return false; }
function wp_update_plugins() { $GLOBALS['cache']['update_plugins'] = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ); }
function wp_die( $message = '' ) { throw new RuntimeException( 'wp_die: ' . $message ); }
// La redirección final de force_check() se intercepta para que la prueba no termine en exit.
class Bridge_Redirect extends RuntimeException {}
function wp_safe_redirect( $url ) { throw new Bridge_Redirect( $url ); }
require __DIR__ . '/../digitalisimo-seo/includes/class-tools-update-bridge.php';
$b = 'Digitalisimo_Tools_Update_Bridge';
$b::init();
function check_bridge( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function pending() { $t = Digitalisimo_Tools_Update_Bridge::inject( (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) ); return $t->response[ Digitalisimo_Tools_Update_Bridge::FILE ] ?? null; }
function manifest( $version, $url = null ) {
	$url = $url ?? 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-tools-' . $version . '.zip';
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.10.15.389', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389', 'plugins' => array( 'digitalisimo-tools' => array( 'version' => $version, 'url' => $url ), 'digitalisimo-seo' => array( 'version' => '1.0.224', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-seo-1.0.224.zip' ) ) ) ) );
}
$api = array( 'code' => 200, 'body' => json_encode( array( array( 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/x', 'assets' => array( array( 'name' => 'digitalisimo-tools-1.0.18.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/digitalisimo-tools-1.0.18.zip' ) ) ) ) ) );

// 1. (a) El índice de la última Release basta: no se consulta la API.
$responses = array( 'manifest' => manifest( '1.0.17' ), 'api' => $api );
$item = pending();
check_bridge( $item && '1.0.17' === $item->new_version && false !== strpos( $item->package, '/releases/download/v2026.10.15.389/digitalisimo-tools-1.0.17.zip' ) && 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389' === $item->url, 'El índice de la Release ofrece la versión nueva.' );
check_bridge( 1 === count( $calls ) && false !== strpos( $calls[0], '/releases/latest/download/digitalisimo-plugins.json' ), 'Sin consultar la API de GitHub.' );
check_bridge( '1.0.17' === ( $site_options[ $b::LAST_KNOWN ]['version'] ?? '' ), 'Debe recordar la última versión encontrada.' );
// 2. El índice se comparte: con la caché propia vencida no se vuelve a descargar.
delete_site_transient( $b::CACHE ); pending();
check_bridge( 1 === count( $calls ), 'El índice en caché sirve a la siguiente consulta.' );
// 3. (b) Un enlace que no es un ZIP de Tools en el repositorio oficial se rechaza y se usa la API.
foreach ( array( 'https://example.com/digitalisimo-tools-1.0.17.zip', 'https://github.com/otro/repo/releases/download/v1/digitalisimo-tools-1.0.17.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.17.zip' ) as $bad ) {
	$cache = array(); $calls = array(); $responses = array( 'manifest' => manifest( '1.0.17', $bad ), 'api' => $api );
	$item = pending();
	check_bridge( $item && '1.0.18' === $item->new_version && 2 === count( $calls ), 'Enlace ajeno rechazado (' . $bad . '); la API queda de respaldo.' );
}
// 4. (c) GitHub no responde o limita las consultas: se mantiene la última versión encontrada y se cachea poco.
$cache = array(); $calls = array(); $responses = array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item = pending();
check_bridge( $item && '1.0.18' === $item->new_version && 2 === count( $calls ), 'Con GitHub limitado sigue a la vista la última versión encontrada.' );
pending();
check_bridge( 2 === count( $calls ), 'La última conocida queda en caché en lugar de martillear GitHub.' );
// 5. «Buscar actualizaciones» vacía también el índice compartido y descarga el nuevo sin consultar la API.
$cache = array( 'update_plugins' => (object) array( 'checked' => array( 'tercero/plugin.php' => '1.0.0' ), 'response' => array(), 'no_update' => array() ), $b::MANIFEST_CACHE => json_decode( manifest( '1.0.17' )['body'], true ) );
$calls = array(); $responses = array( 'manifest' => manifest( '1.0.19' ) );
try { $b::force_check(); check_bridge( false, 'force_check() debe terminar en una redirección.' ); }
catch ( Bridge_Redirect $redirect ) { check_bridge( false !== strpos( $redirect->getMessage(), 'digitalisimo-tools-bridge-checked=1' ), 'La redirección vuelve a la lista de plugins con el aviso.' ); }
check_bridge( '1.0.19' === ( $cache['update_plugins']->response[ $b::FILE ]->new_version ?? '' ) && 1 === count( $calls ) && false === strpos( $calls[0], 'api.github.com' ), 'Buscar actualizaciones descarga el índice de nuevo sin consultar la API.' );
// 6. La versión instalada no se ofrece como actualización.
$cache = array(); $site_options = array(); $responses = array( 'manifest' => manifest( '1.0.15' ) );
check_bridge( null === pending(), 'La versión instalada no se ofrece como actualización.' );
echo "Puente de Tools: índice de la Release, respaldo por API y última conocida validados.\n";
