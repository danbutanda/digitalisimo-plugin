<?php
/** SEO debe descubrir y anunciar una nueva Release sin usar el botón manual. */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', __DIR__ . '/../digitalisimo-seo/digitalisimo-integrations.php' );
define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '1.0.165' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['transients'] = array();
$GLOBALS['site_options'] = array();
$GLOBALS['release_version'] = '1.0.166';
$GLOBALS['requests'] = 0;
$GLOBALS['manifest_response'] = null;
$GLOBALS['scheduled'] = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][ $name ][ $priority ] = $callback; }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['actions'][ $name ][ $priority ] = $callback; }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_event( $time, $schedule, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; return true; }
function plugin_basename( $file ) { return 'digitalisimo-seo/digitalisimo-integrations.php'; }
function get_site_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function delete_site_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site_options'][ $key ] = $value; return true; }
function current_user_can() { return true; }
function is_multisite() { return false; }
function check_admin_referer() { return true; }
function wp_update_plugins() { $GLOBALS['transients']['update_plugins'] = (object) array( 'checked' => array( 'tercero/plugin.php' => '1.0.0' ), 'response' => array( 'tercero/plugin.php' => (object) array( 'new_version' => '2.0.0' ) ), 'no_update' => array() ); }
function wp_remote_get( $url, $args ) {
	// El índice de la Release se descarga como archivo, sin límite de consultas: sólo se cuentan las llamadas a api.github.com.
	if ( false === strpos( $url, 'api.github.com' ) ) return $GLOBALS['manifest_response'] ?? array( 'code' => 404, 'body' => '' );
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
if ( false !== $class::inject( false ) ) throw new RuntimeException( 'SEO no debe reemplazar una caché inexistente de WordPress.' );
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
if ( '2.0.0' !== $GLOBALS['transients']['update_plugins']->response['tercero/plugin.php']->new_version ) throw new RuntimeException( 'El cron no debe borrar actualizaciones de terceros.' );
$GLOBALS['response_code'] = 503;
// El aviso propio se retira del inventario: el cron sólo puede volver a anunciarlo desde la última versión conocida.
unset( $GLOBALS['transients']['update_plugins']->response[ $file ] );
$class::refresh();
if ( '1.0.167' !== ( $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version ?? '' ) ) throw new RuntimeException( 'Un fallo temporal de GitHub no debe borrar una actualización ya detectada.' );
$other = $class::uri_update( false, array(), 'otro-plugin/otro.php', array() );
if ( false !== $other ) throw new RuntimeException( 'SEO no debe alterar el actualizador de otro plugin.' );

// Índice de versiones adjunto a la última Release: SEO lo lee antes que la API de GitHub.
function seo_manifest( $version, $url = null ) {
	$url = $url ?? 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-seo-' . $version . '.zip';
	return array( 'code' => 200, 'body' => json_encode( array( 'tag' => 'v2026.10.15.389', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.15.389', 'plugins' => array( 'digitalisimo-seo' => array( 'version' => $version, 'url' => $url ), 'digitalisimo-tools' => array( 'version' => '1.0.28', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.15.389/digitalisimo-tools-1.0.28.zip' ) ) ) ) );
}
function seo_reset_caches() { delete_site_transient( Digitalisimo_SEO_Release_Check::CACHE_KEY ); delete_site_transient( Digitalisimo_SEO_Release_Check::MANIFEST_CACHE ); }
function seo_inject() { return Digitalisimo_SEO_Release_Check::inject( (object) array( 'checked' => array(), 'response' => array(), 'no_update' => array() ) ); }
$GLOBALS['response_code'] = 200;
// (a) Con un índice válido se usa su versión y no se consulta la API.
seo_reset_caches(); $GLOBALS['manifest_response'] = seo_manifest( '1.0.170' ); $before = $GLOBALS['requests'];
$detected = seo_inject();
if ( '1.0.170' !== ( $detected->response[ $file ]->new_version ?? '' ) || false === strpos( $detected->response[ $file ]->package, '/releases/download/v2026.10.15.389/digitalisimo-seo-1.0.170.zip' ) || $before !== $GLOBALS['requests'] ) throw new RuntimeException( 'El índice de la Release debe ofrecer la versión nueva sin consultar la API.' );
if ( '1.0.170' !== ( $GLOBALS['site_options'][ $class::LAST_KNOWN ]['version'] ?? '' ) ) throw new RuntimeException( 'Debe recordar la última versión encontrada.' );
// El cron descarga el índice de nuevo y descubre la Release siguiente sin la API.
$GLOBALS['manifest_response'] = seo_manifest( '1.0.171' );
$class::refresh();
if ( '1.0.171' !== $GLOBALS['transients']['update_plugins']->response[ $file ]->new_version || $before !== $GLOBALS['requests'] ) throw new RuntimeException( 'El cron debe renovar el índice de la Release sin consultar la API.' );
// (b) Un enlace ajeno (otro dominio, otro repositorio, otro slug) se rechaza y la API queda de respaldo.
$GLOBALS['release_version'] = '1.0.168';
foreach ( array( 'https://example.com/digitalisimo-seo-1.0.170.zip', 'https://github.com/otro/repo/releases/download/v1/digitalisimo-seo-1.0.170.zip', 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-tools-1.0.170.zip' ) as $bad ) {
	seo_reset_caches(); $GLOBALS['manifest_response'] = seo_manifest( '1.0.170', $bad ); $before = $GLOBALS['requests'];
	$detected = seo_inject();
	if ( '1.0.168' !== ( $detected->response[ $file ]->new_version ?? '' ) || $before + 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'Enlace ajeno rechazado (' . $bad . '): la API debe quedar de respaldo.' );
}
seo_reset_caches(); $GLOBALS['manifest_response'] = seo_manifest( '1.0.170-beta' ); $before = $GLOBALS['requests'];
$detected = seo_inject();
if ( '1.0.168' !== ( $detected->response[ $file ]->new_version ?? '' ) || $before + 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'Una versión que no es numérica se rechaza y la API queda de respaldo.' );
// (c) Índice ausente y API limitada: se mantiene la última versión conocida y se cachea poco para reintentar pronto.
seo_reset_caches(); $GLOBALS['manifest_response'] = null; $GLOBALS['response_code'] = 403; $before = $GLOBALS['requests'];
$detected = seo_inject();
if ( '1.0.168' !== ( $detected->response[ $file ]->new_version ?? '' ) || $before + 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'Con GitHub limitado debe seguir a la vista la última versión encontrada.' );
seo_inject();
if ( $before + 1 !== $GLOBALS['requests'] ) throw new RuntimeException( 'La última conocida debe quedar en caché en lugar de martillear la API.' );
// «Buscar actualizaciones» vacía también el índice compartido para descargarlo de nuevo.
$GLOBALS['transients'][ $class::MANIFEST_CACHE ] = json_decode( seo_manifest( '1.0.170' )['body'], true );
$class::clear_for_manual();
if ( isset( $GLOBALS['transients'][ $class::MANIFEST_CACHE ] ) || isset( $GLOBALS['transients'][ $class::CACHE_KEY ] ) ) throw new RuntimeException( 'Buscar actualizaciones debe vaciar el índice compartido y la caché propia.' );
echo "SEO: detección automática, índice de la Release, caché corta y cron de actualización correctos.\n";
