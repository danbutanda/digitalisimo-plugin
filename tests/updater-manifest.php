<?php
/**
 * Actualizador compartido: lee el índice de versiones de la última Release (sin el límite de la API de GitHub),
 * acepta sólo ZIPs propios del repositorio oficial, usa la API como respaldo y, si GitHub no responde o limita
 * las consultas, mantiene a la vista el último índice encontrado. Se ejecuta con seo, hosting o ia.
 */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/sin-plugins-instalados' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$cases = array(
	'seo' => array( 'digitalisimo-seo/includes/class-digitalisimo-updater.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-seo' ),
	'hosting' => array( 'digitalisimo-hosting/includes/class-digitalisimo-updater.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-hosting' ),
	'ia' => array( 'digitalisimo.chatbot/includes/class-digitalisimo-updater.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-ia-tools' ),
);
$case = $cases[ $argv[1] ?? '' ] ?? null;
if ( ! $case ) throw new RuntimeException( 'Indica seo, hosting o ia.' );
list( $source, $file, $slug ) = $case;
$cache = array(); $ttl = array(); $site_options = array(); $calls = array(); $responses = array();
function plugin_basename( $file ) { return substr( $file, strlen( '/plugins/' ) ); }
function add_filter() {} function add_action() {}
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $expiration = 0 ) { $GLOBALS['cache'][ $key ] = $value; $GLOBALS['ttl'][ $key ] = $expiration; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function delete_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
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
function esc_url( $url ) { return $url; }
function esc_html( $text ) { return $text; }
function wp_kses_post( $text ) { return $text; }
function get_bloginfo() { return '6.8'; }
require __DIR__ . '/../' . $source;
$u = 'Digitalisimo_Updater';
$u::register( '/plugins/' . $file, '1.0.0', $slug );
function check_updater( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function pending() { $t = Digitalisimo_Updater::inject( (object) array( 'checked' => array() ) ); return $t->response[ $GLOBALS['file'] ] ?? null; }
/** Vacía la caché como tras actualizar un módulo propio (el mismo clear() que WordPress invoca). */
function clear_updater() { Digitalisimo_Updater::clear( null, array( 'action' => 'update', 'type' => 'plugin', 'plugins' => array( $GLOBALS['file'] ) ) ); }
function api_calls() { return count( array_filter( $GLOBALS['calls'], function( $url ) { return false !== strpos( $url, 'api.github.com' ); } ) ); }
/** Índice de la Release con los siete plugins; el módulo bajo prueba recibe la versión y el enlace indicados (o ninguna entrada con null). */
function manifest( $version, $url = null ) {
	$tag = 'v2026.10.15.389';
	$plugins = array();
	foreach ( array( 'digitalisimo-seo' => '1.0.224', 'digitalisimo-ia-tools' => '1.0.54', 'digitalisimo-hosting' => '1.0.25', 'digitalisimo-backups' => '1.0.52', 'digitalisimo-tools' => '1.0.28', 'digitalisimo-elements' => '4.3.0.142', 'digitalisimo-migration' => '1.0.8' ) as $name => $published ) {
		$plugins[ $name ] = array( 'version' => $published, 'file' => $name . '-' . $published . '.zip', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/' . $name . '-' . $published . '.zip' );
	}
	if ( null === $version ) unset( $plugins[ $GLOBALS['slug'] ] );
	else $plugins[ $GLOBALS['slug'] ] = array( 'version' => $version, 'file' => $GLOBALS['slug'] . '-' . $version . '.zip', 'url' => $url ?? 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/' . $GLOBALS['slug'] . '-' . $version . '.zip' );
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => $tag, 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/' . $tag, 'generated' => '2026-10-15T00:00:00Z', 'plugins' => $plugins ) ) );
}
$api = array( 'code' => 200, 'body' => json_encode( array( array( 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/x', 'body' => "Notas de la API\n\n**Full Changelog**: https://github.com/danbutanda/digitalisimo-plugin/compare/v1...x", 'assets' => array( array( 'name' => $slug . '-1.5.0.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/' . $slug . '-1.5.0.zip' ), array( 'name' => 'digitalisimo-backups-1.0.52.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/x/digitalisimo-backups-1.0.52.zip' ) ) ) ) ) );

// 1. El índice de la última Release basta: no se consulta la API y se recuerda como último índice conocido.
$responses = array( 'manifest' => manifest( '1.2.0' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.2.0' === $item->new_version && false !== strpos( $item->package, '/releases/download/v2026.10.15.389/' . $slug . '-1.2.0.zip' ) && 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389' === $item->url, 'El índice de la Release ofrece la versión nueva.' );
check_updater( 1 === count( $calls ) && false !== strpos( $calls[0], '/releases/latest/download/digitalisimo-plugins.json' ) && 0 === api_calls(), 'Sin consultar la API de GitHub.' );
check_updater( '1.2.0' === ( $site_options[ $u::LAST_KNOWN ][ $slug ]['version'] ?? '' ) && 7 === count( $site_options[ $u::LAST_KNOWN ] ), 'El índice completo queda como último conocido.' );
check_updater( 10 * MINUTE_IN_SECONDS === $ttl[ $u::CACHE_KEY ] && 10 * MINUTE_IN_SECONDS === $ttl[ $u::MANIFEST_CACHE ], 'El índice se vuelve a leer cada diez minutos.' );
$info = $u::info( null, 'plugin_information', (object) array( 'slug' => $slug ) );
// La ficha («Ver detalles») no enlaza a dominios ajenos: sin notas indica sólo la versión, y la página del plugin es la propia.
check_updater( is_object( $info ) && '1.2.0' === $info->version && false !== strpos( $info->sections['changelog'], '1.2.0' ) && false === strpos( $info->sections['changelog'], 'href=' ) && 'https://digitalisimo.mx' === $info->homepage, 'La ficha del plugin sale del índice y, sin notas, indica la versión sin enlazar a GitHub.' );

// 2. El índice se comparte: otra consulta (otro plugin o la caché propia vencida) no lo vuelve a descargar.
delete_site_transient( $u::CACHE_KEY );
pending();
check_updater( 1 === count( $calls ), 'El índice en caché sirve a la siguiente consulta.' );

// 3. Un enlace que no es un ZIP de este plugin en el repositorio oficial se rechaza y se usa la API.
foreach ( array( 'https://example.com/' . $slug . '-1.2.0.zip', 'https://github.com/otro/repo/releases/download/v1/' . $slug . '-1.2.0.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-otro-1.2.0.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/' . $slug . '-1.2.1.zip' ) as $bad ) {
	clear_updater();
	$calls = array(); $responses = array( 'manifest' => manifest( '1.2.0', $bad ), 'api' => $api );
	$item = pending();
	check_updater( $item && '1.5.0' === $item->new_version && 2 === count( $calls ) && 1 === api_calls(), 'Enlace ajeno rechazado (' . $bad . '); la API queda de respaldo.' );
}

// 4. Una versión mal formada o un índice que no cubre este módulo tampoco sirven: la API construye el índice completo.
foreach ( array( manifest( 'latest' ), manifest( null ), array( 'code' => 200, 'body' => '{"tag":"v1"}' ) ) as $useless ) {
	clear_updater();
	$calls = array(); $responses = array( 'manifest' => $useless, 'api' => $api );
	$item = pending();
	check_updater( $item && '1.5.0' === $item->new_version && 1 === api_calls(), 'Un índice sin entrada válida para este módulo deja paso a la API.' );
}
check_updater( 'Notas de la API' === $u::info( null, 'plugin_information', (object) array( 'slug' => $slug ) )->sections['changelog'], 'Las notas de la API se conservan en la ficha, sin las líneas con enlaces que añade GitHub.' );

// 5. GitHub no responde o limita las consultas: se mantiene el último índice encontrado y se reintenta pronto.
clear_updater();
$calls = array(); $responses = array( 'manifest' => array( 'code' => 404, 'body' => '' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item = pending();
check_updater( $item && '1.5.0' === $item->new_version, 'Con GitHub limitado sigue a la vista la última versión encontrada.' );
check_updater( 2 * MINUTE_IN_SECONDS === $ttl[ $u::CACHE_KEY ], 'Tras un fallo se reintenta a los dos minutos.' );

// 6. Tras actualizar un módulo propio se descarta también el índice en caché, y se descarga de nuevo.
$responses = array( 'manifest' => manifest( '1.3.0' ) );
set_site_transient( $u::MANIFEST_CACHE, json_decode( manifest( '1.2.0' )['body'], true ) );
$u::clear( null, array( 'action' => 'update', 'type' => 'plugin', 'plugins' => array( $file ) ) );
check_updater( ! isset( $cache[ $u::MANIFEST_CACHE ] ) && ! isset( $cache[ $u::CACHE_KEY ] ), 'Actualizar un módulo propio vacía la caché y el índice.' );
$item = pending();
check_updater( $item && '1.3.0' === $item->new_version, 'El índice se descarga de nuevo tras vaciar la caché.' );

// 7. Con la versión instalada al día no hay nada pendiente.
clear_updater(); $site_options = array(); $responses = array( 'manifest' => manifest( '1.0.0' ) );
$transient = $u::inject( (object) array( 'checked' => array() ) );
check_updater( ! isset( $transient->response[ $file ] ) && '1.0.0' === ( $transient->no_update[ $file ]->new_version ?? '' ), 'La versión instalada no se ofrece como actualización.' );

// 8. Otro módulo registrado sin entrada en el índice no invalida la de este: la API sólo completa lo que falta y, limitada, la entrada del índice sigue a la vista.
$u::register( '/plugins/digitalisimo-otro/digitalisimo-otro.php', '1.0.0', 'digitalisimo-otro' );
clear_updater(); $site_options = array(); $calls = array(); $responses = array( 'manifest' => manifest( '1.2.0' ), 'api' => array( 'code' => 403, 'body' => '{"message":"API rate limit exceeded"}' ) );
$item = pending();
check_updater( $item && '1.2.0' === $item->new_version && 1 === api_calls(), 'Un módulo registrado sin entrada en el índice no descarta la entrada válida de este módulo.' );
check_updater( 10 * MINUTE_IN_SECONDS === $ttl[ $u::CACHE_KEY ] && '1.2.0' === ( $site_options[ $u::LAST_KNOWN ][ $slug ]['version'] ?? '' ), 'El índice con entradas válidas se recuerda y se cachea diez minutos aunque la API falle.' );
clear_updater(); $calls = array(); $responses = array( 'manifest' => manifest( '1.2.0' ), 'api' => $api );
$item = pending();
check_updater( $item && '1.2.0' === $item->new_version && 1 === api_calls() && '1.0.52' === ( $site_options[ $u::LAST_KNOWN ]['digitalisimo-backups']['version'] ?? '' ), 'La API completa sólo los módulos que faltan sin sustituir las entradas del índice.' );
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
echo "Actualizador compartido $slug: índice de la Release validado.\n";
