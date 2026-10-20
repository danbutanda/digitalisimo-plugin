<?php
/**
 * Actualizador de Migración: lee el índice de versiones de la última Release (sin el límite de la API de GitHub),
 * acepta sólo ZIPs propios del repositorio oficial, usa la API como respaldo y, si GitHub no responde o limita
 * las consultas, mantiene a la vista la última versión encontrada.
 */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.0' );
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
require __DIR__ . '/../digitalisimo-migration/includes/class-updater.php';
$u = 'Digitalisimo\\Migration\\Updater';
function check_updater( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function pending() { $t = Digitalisimo\Migration\Updater::check( (object) array( 'checked' => array() ) ); return $t->response[ Digitalisimo\Migration\Updater::FILE ] ?? null; }
/** Entrada del tema en el índice (clave «themes»): los actualizadores de plugins sólo leen «plugins» y no deben cambiar con ella. */
function theme_index( $tag = 'v2026.10.15.389' ) { return array( 'digitalisimo-theme' => array( 'version' => '1.0.0', 'file' => 'digitalisimo-theme-1.0.0.zip', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/digitalisimo-theme-1.0.0.zip', 'requires' => '6.8', 'requires_php' => '7.4' ) ); }
function manifest( $version, $url = null ) {
	$url = $url ?? 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-migration-' . $version . '.zip';
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.10.15.389', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389', 'themes' => theme_index(), 'plugins' => array( 'digitalisimo-migration' => array( 'version' => $version, 'url' => $url ), 'digitalisimo-seo' => array( 'version' => '1.0.224', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-seo-1.0.224.zip' ) ) ) ) );
}
$api = array( 'code' => 200, 'body' => json_encode( array( array( 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/x', 'assets' => array( array( 'name' => 'digitalisimo-theme-1.0.0.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/digitalisimo-theme-1.0.0.zip' ), array( 'name' => 'digitalisimo-migration-1.0.9.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/digitalisimo-migration-1.0.9.zip' ) ) ) ) ) );

// 1. El índice de la última Release basta: no se consulta la API.
$responses = array( 'manifest' => manifest( '1.0.1' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.0.1' === $item->new_version && false !== strpos( $item->package, '/releases/download/v2026.10.15.389/digitalisimo-migration-1.0.1.zip' ), 'El índice de la Release ofrece la versión nueva.' );
check_updater( 1 === count( $calls ) && false !== strpos( $calls[0], '/releases/latest/download/digitalisimo-plugins.json' ), 'Sin consultar la API de GitHub.' );
check_updater( '1.0.1' === ( get_site_option( $u::LAST_KNOWN )['version'] ?? '' ), 'La versión encontrada queda registrada como última conocida.' );

// 2. El índice se comparte: otra consulta (otro plugin o la caché propia vencida) no lo vuelve a descargar.
delete_site_transient( $u::CACHE_KEY );
pending();
check_updater( 1 === count( $calls ), 'El índice en caché sirve a la siguiente consulta.' );

// 3. Un enlace que no es un ZIP de este plugin en el repositorio oficial se rechaza y se usa la API.
foreach ( array( 'https://example.com/digitalisimo-migration-1.0.1.zip', 'https://github.com/otro/repo/releases/download/v1/digitalisimo-migration-1.0.1.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.1.zip' ) as $bad ) {
	$u::clear(); $calls = array(); $responses = array( 'manifest' => manifest( '1.0.1', $bad ), 'api' => $api );
	$item = pending();
	check_updater( $item && '1.0.9' === $item->new_version && 2 === count( $calls ) && false !== strpos( $calls[1], 'api.github.com' ), 'Enlace ajeno rechazado (' . $bad . '); la API queda de respaldo.' );
}

// 4. Una versión que no es numérica tampoco se acepta del índice.
$u::clear(); $calls = array(); $responses = array( 'manifest' => manifest( '1.0.1-beta' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.0.9' === $item->new_version && 2 === count( $calls ), 'Una versión con formato ajeno se descarta y la API queda de respaldo.' );

// 5. GitHub no responde o limita las consultas: se mantiene la última versión encontrada.
$u::clear(); $calls = array(); $responses = array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item = pending();
check_updater( $item && '1.0.9' === $item->new_version && 2 === count( $calls ), 'Con GitHub limitado sigue a la vista la última versión encontrada.' );

// 6. «Buscar actualizaciones» vacía también el índice en caché.
$responses = array( 'manifest' => manifest( '1.0.20' ) );
set_site_transient( $u::MANIFEST_CACHE, json_decode( manifest( '1.0.1' )['body'], true ) );
$u::clear();
$item = pending();
check_updater( $item && '1.0.20' === $item->new_version, 'Buscar actualizaciones descarga el índice de nuevo.' );

// 7. Con la versión instalada al día no hay nada pendiente.
$u::clear(); $responses = array( 'manifest' => manifest( '1.0.0' ) ); $site_options = array();
check_updater( null === pending(), 'La versión instalada no se ofrece como actualización.' );
// «Buscar actualizaciones de nuevo» de WordPress vacía la caché propia y el índice compartido; sin force-check no toca nada.
$_GET['force-check'] = '1';
set_site_transient( $u::MANIFEST_CACHE, array( 'x' => 1 ) );
set_site_transient( $u::CACHE_KEY, array( 'y' => 1 ) );
$u::native_force_check();
check_updater( ! isset( $GLOBALS['cache'][ $u::MANIFEST_CACHE ] ) && ! isset( $GLOBALS['cache'][ $u::CACHE_KEY ] ), '«Buscar actualizaciones de nuevo» vacía la caché y el índice.' );
unset( $_GET['force-check'] );
set_site_transient( $u::MANIFEST_CACHE, array( 'x' => 1 ) );
$u::native_force_check();
check_updater( isset( $GLOBALS['cache'][ $u::MANIFEST_CACHE ] ), 'Sin force-check la caché se conserva.' );
check_updater( false !== strpos( (string) file_get_contents( ( new ReflectionClass( $u ) )->getFileName() ), "add_action( 'load-update-core.php', array( __CLASS__, 'native_force_check' ), 1 )" ), 'El actualizador escucha la pantalla de actualizaciones antes que WordPress.' );
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } }
// «Ver detalles»: la ficha la da el actualizador, sin enlaces a GitHub (si no, WordPress la pide a wordpress.org).
$info = $u::info( false, 'plugin_information', (object) array( 'slug' => 'digitalisimo-migration' ) );
check_updater( is_object( $info ) && 'digitalisimo-migration' === $info->slug && '' !== $info->version && 'https://digitalisimo.mx' === $info->homepage && false === stripos( $info->homepage . implode( '', $info->sections ), 'github' ), 'La ficha de «Ver detalles» es propia y no enlaza a GitHub.' );
check_updater( 'ajena' === $u::info( 'ajena', 'plugin_information', (object) array( 'slug' => 'otro-plugin' ) ) && 'x' === $u::info( 'x', 'query_plugins', (object) array( 'slug' => 'digitalisimo-migration' ) ), 'La ficha de otros plugins y otras consultas no se toca.' );
echo "DIGITALÍSIMO Migración: actualizador con índice de la Release validado.\n";
