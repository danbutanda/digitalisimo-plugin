<?php
/** SEO debe descubrir y anunciar una nueva Release sin usar el botón manual. */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', __DIR__ . '/../digitalisimo-seo/digitalisimo-integrations.php' );
define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.165' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['transients'] = array();
$GLOBALS['release_version'] = '1.0.166';
$GLOBALS['requests'] = 0;
$GLOBALS['scheduled'] = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $name ][ $priority ] = $callback; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['actions'][ $name ][ $priority ] = $callback; }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_event( $time, $schedule, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; return true; }
function plugin_basename( $file ) { return 'digitalisimo-seo/digitalisimo-integrations.php'; }
function get_site_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function wp_remote_get( $url, $args ) {
	$GLOBALS['requests']++;
	return array( 'code' => $GLOBALS['response_code'] ?? 200, 'body' => json_encode( array( array( 'draft' => false, 'prerelease' => false, 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest', 'assets' => array( array( 'name' => 'digitalisimo-seo-' . $GLOBALS['release_version'] . '.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/latest/digitalisimo-seo-' . $GLOBALS['release_version'] . '.zip' ) ) ) ) ) );
}
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url_raw( $url ) { return $url; }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-release-check.php';
$class = Digitalisimo_SEO_Release_Check::class;
$class::init();
if ( empty( $GLOBALS['scheduled'][ $class::HOOK ] ) ) throw new RuntimeException( 'Debe programar la comprobación automática.' );
$file = 'digitalisimo-seo/digitalisimo-integrations.php';
$stale = (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array( $file => (object) array( 'new_version' => '1.0.165' ) ) );
$detected = $class::inject( $stale );
if ( '1.0.166' !== $detected->response[ $file ]->new_version || isset( $detected->no_update[ $file ] ) || 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'Debe anunciar automáticamente una Release superior.' );
$class::inject( $detected );
if ( 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'La consulta debe reutilizar la caché durante cinco minutos.' );
$GLOBALS['release_version'] = '1.0.167';
$class::refresh();
if ( '1.0.167' !== $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version || 2 !== $GLOBALS['requests'] ) throw new RuntimeException( 'El cron debe descubrir una Release nueva y renovar el aviso sin WordPress.org.' );
$GLOBALS['response_code'] = 503;
$class::refresh();
if ( '1.0.167' !== $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version ) throw new RuntimeException( 'Un fallo temporal de GitHub no debe borrar una actualización ya detectada.' );
$other = $class::uri_update( false, array(), 'otro-plugin/otro.php', array() );
if ( false !== $other ) throw new RuntimeException( 'SEO no debe alterar el actualizador de otro plugin.' );
echo "SEO: detección automática, caché corta y cron de actualización correctos.\n";
