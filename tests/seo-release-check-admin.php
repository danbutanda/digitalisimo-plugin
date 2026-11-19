<?php
/**
 * Escritorio sin esperas por el chequeo de versiones de SEO (auditoría admin-perf, 2026-11-18).
 * WordPress lee update_plugins en cada pantalla del escritorio (el contador del menú); con la caché de 10 minutos
 * vencida, esa lectura descargaba el índice de GitHub (hasta 3 s, y otros 3 s por la API si fallaba). Ahora:
 *   1. leer el transient no hace ninguna petición y sigue mostrando la última versión conocida;
 *   2. cuando WordPress consulta (pre_set_…) y en el cron, SEO sí descarga y detecta la Release nueva;
 *   3. en una red cada sitio con SEO programa el cron (aunque el principal no tenga SEO), pero con un candado de red
 *      sólo uno descarga el índice cada cinco minutos;
 *   4. el cron no vacía el índice compartido: si GitHub falla, los demás plugins conservan el que tenían.
 */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', __DIR__ . '/../digitalisimo-seo/digitalisimo-integrations.php' );
define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.200' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['transients'] = array(); $GLOBALS['site_options'] = array(); $GLOBALS['scheduled'] = array(); $GLOBALS['cleared'] = array();
$GLOBALS['requests'] = array(); $GLOBALS['github'] = 'ok'; $GLOBALS['multisite'] = false; $GLOBALS['main_site'] = true;
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $name ][ $priority ] = $callback; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['actions'][ $name ][ $priority ] = $callback; }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_event( $time, $schedule, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; return true; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['cleared'][] = $hook; unset( $GLOBALS['scheduled'][ $hook ] ); return 1; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_main_site() { return $GLOBALS['main_site']; }
function plugin_basename( $file ) { return 'digitalisimo-seo/digitalisimo-integrations.php'; }
function get_site_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site_options'][ $key ] = $value; return true; }
function wp_update_plugins() {}
function current_user_can() { return true; }
function check_admin_referer() { return true; }
function is_wp_error( $value ) { return false; }
function esc_url_raw( $url ) { return $url; }
function wp_doing_cron() { return ! empty( $GLOBALS['doing_cron'] ); }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = array( 'url' => $url, 'timeout' => $args['timeout'] ?? null );
	if ( 'ok' !== $GLOBALS['github'] ) return array( 'code' => 503, 'body' => '' );
	if ( false !== strpos( $url, 'api.github.com' ) ) return array( 'code' => 200, 'body' => '[]' );
	return array( 'code' => 200, 'body' => json_encode( array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v1', 'plugins' => array( 'digitalisimo-seo' => array( 'version' => $GLOBALS['remote_version'], 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-' . $GLOBALS['remote_version'] . '.zip' ) ) ) ) );
}
function fail( $message ) { fwrite( STDERR, "FALLA: $message\n" ); exit( 1 ); }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-release-check.php';
$class = Digitalisimo_SEO_Release_Check::class;
$file  = 'digitalisimo-seo/digitalisimo-integrations.php';
$class::init();
$read  = $GLOBALS['filters']['site_transient_update_plugins'][30] ?? null;
$write = $GLOBALS['filters']['pre_set_site_transient_update_plugins'][30] ?? null;
if ( ! is_callable( $read ) || ! is_callable( $write ) ) fail( 'SEO debe engancharse a la lectura y a la escritura de update_plugins.' );
$fresh = function() { return (object) array( 'checked' => array( 'digitalisimo-seo/digitalisimo-integrations.php' => '1.0.200' ), 'response' => array(), 'no_update' => array() ); };

// 1. Lectura en una pantalla del escritorio con todas las cachés vencidas y sin versión conocida: ninguna petición.
$GLOBALS['remote_version'] = '1.0.201';
$out = call_user_func( $read, $fresh() );
if ( $GLOBALS['requests'] ) fail( 'Leer update_plugins en una pantalla no debe consultar GitHub (hizo ' . count( $GLOBALS['requests'] ) . ' peticiones: ' . $GLOBALS['requests'][0]['url'] . ').' );
if ( isset( $out->response[ $file ] ) ) fail( 'Sin versión conocida, la lectura no inventa una actualización.' );
// Con la última versión conocida y la caché vencida, la lectura la sigue mostrando sin red.
$GLOBALS['site_options'][ $class::LAST_KNOWN ] = array( 'version' => '1.0.201', 'package' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.201.zip', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases' );
$out = call_user_func( $read, $fresh() );
if ( $GLOBALS['requests'] || '1.0.201' !== ( $out->response[ $file ]->new_version ?? '' ) ) fail( 'La lectura debe mostrar la última versión conocida sin consultar GitHub.' );
// Una versión conocida que no es mayor que la instalada no se anuncia.
$GLOBALS['site_options'][ $class::LAST_KNOWN ]['version'] = '1.0.200';
$out = call_user_func( $read, $fresh() );
if ( isset( $out->response[ $file ] ) ) fail( 'Una versión conocida igual a la instalada no es una actualización.' );

// 2. WordPress consulta actualizaciones (pre_set): SEO descarga el índice y detecta la Release nueva.
$GLOBALS['remote_version'] = '1.0.202';
$out = call_user_func( $write, $fresh() );
if ( 1 !== count( $GLOBALS['requests'] ) || '1.0.202' !== ( $out->response[ $file ]->new_version ?? '' ) ) fail( 'Al consultar WordPress, SEO debe descargar el índice y anunciar 1.0.202.' );
if ( 3 !== $GLOBALS['requests'][0]['timeout'] ) fail( 'Fuera del cron la descarga espera como mucho 3 s.' );
// Las lecturas siguientes usan esa caché, sin red.
$out = call_user_func( $read, $fresh() );
if ( 1 !== count( $GLOBALS['requests'] ) || '1.0.202' !== ( $out->response[ $file ]->new_version ?? '' ) ) fail( 'La lectura debe usar la caché recién descargada.' );

// 4. El cron renueva el índice sin vaciar antes el compartido; si GitHub falla, el índice compartido se conserva.
$GLOBALS['remote_version'] = '1.0.203'; $GLOBALS['doing_cron'] = true;
$GLOBALS['transients']['update_plugins'] = $fresh();
// La vuelta del cron llega minutos después: el índice ya no es «reciente» (4 min) para la descarga forzada.
$forward = function () use ( $class ) { unset( $GLOBALS['transients'][ $class::MANIFEST_FRESH ], $GLOBALS['transients'][ $class::MANIFEST_RETRY ], $GLOBALS['transients'][ $class::API_RETRY ] ); };
$forward();
$class::refresh();
if ( '1.0.203' !== ( $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version ?? '' ) ) fail( 'El cron debe descubrir 1.0.203.' );
if ( 10 !== end( $GLOBALS['requests'] )['timeout'] ) fail( 'En el cron la descarga puede esperar 10 s.' );
$shared = $GLOBALS['transients'][ $class::MANIFEST_CACHE ] ?? null;
if ( ! is_array( $shared ) ) fail( 'El cron deja el índice descargado en la caché compartida.' );
$GLOBALS['github'] = 'down';
$forward();
$class::refresh();
if ( ( $GLOBALS['transients'][ $class::MANIFEST_CACHE ] ?? null ) !== $shared ) fail( 'Con GitHub caído, el cron no debe vaciar el índice compartido que usan los demás plugins.' );
if ( '1.0.203' !== ( $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version ?? '' ) ) fail( 'Con GitHub caído se conserva la actualización ya detectada.' );
if ( empty( $GLOBALS['transients'][ $class::MANIFEST_RETRY ] ) ) fail( 'Con GitHub caído el cron deja la espera compartida de dos minutos para los demás plugins.' );
$GLOBALS['doing_cron'] = false; $GLOBALS['github'] = 'ok';

// 3. Red: un subsitio programa el cron aunque el principal no tenga SEO; dos crons seguidos descargan una sola vez.
$GLOBALS['multisite'] = true; $GLOBALS['main_site'] = false; $GLOBALS['scheduled'] = array(); $GLOBALS['doing_cron'] = true;
$class::init();
if ( empty( $GLOBALS['scheduled'][ $class::HOOK ] ) ) fail( 'Un subsitio con SEO debe programar el cron aunque el sitio principal no lo tenga.' );
unset( $GLOBALS['transients'][ $class::LOCK ] ); $forward();
$before = count( $GLOBALS['requests'] );
$class::refresh(); $class::refresh(); $class::refresh();
if ( 1 !== count( $GLOBALS['requests'] ) - $before ) fail( 'En una red, varios crons seguidos (uno por sitio) deben descargar el índice una sola vez: ' . ( count( $GLOBALS['requests'] ) - $before ) );
unset( $GLOBALS['transients'][ $class::LOCK ] ); $forward(); // el candado y el índice reciente vencen a la vez (4 min)
$class::refresh();
if ( 2 !== count( $GLOBALS['requests'] ) - $before ) fail( 'Vencido el candado, el siguiente cron vuelve a descargar.' );
$GLOBALS['doing_cron'] = false;
echo "SEO: el escritorio no espera a GitHub, el cron renueva sin vaciar el índice compartido y en red descarga un solo sitio a la vez.\n";
