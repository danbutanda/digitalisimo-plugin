<?php
/** Backups anuncia su Release, muestra el enlace y renueva la consulta sin otro plugin. */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.47' );
define( 'DIGITALISIMO_BACKUPS_FILE', '/plugins/digitalisimo-backups/digitalisimo-backups.php' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['updater_hooks'] = array();
$GLOBALS['updater_cache'] = array();
$GLOBALS['updater_network'] = true;
$GLOBALS['updater_network_admin'] = true;
$GLOBALS['updater_checks'] = 0;
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['updater_hooks'][ $hook ] = $callback; }
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['updater_hooks'][ $hook ] = $callback; }
function plugin_basename( $file ) { return 'digitalisimo-backups/digitalisimo-backups.php'; }
function is_multisite() { return $GLOBALS['updater_network']; }
function is_main_site() { return true; }
function is_network_admin() { return $GLOBALS['updater_network_admin']; }
function current_user_can( $cap ) { return in_array( $cap, array( 'manage_network_plugins', 'update_plugins' ), true ); }
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_event( $when, $interval, $hook ) { $GLOBALS['updater_scheduled'] = array( $interval, $hook ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( (array) $args ); }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=test'; }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function get_site_transient( $key ) { return $GLOBALS['updater_cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['updater_cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['updater_cache'][ $key ] ); }
function delete_transient( $key ) { unset( $GLOBALS['updater_cache'][ $key ] ); }
function wp_update_plugins() { $GLOBALS['updater_checks']++; }
function wp_remote_get( $url, $args ) {
	if ( false === strpos( $url, 'per_page=100' ) ) throw new RuntimeException( 'La búsqueda de Releases no cubre suficientes publicaciones.' );
	return array( 'code' => 200, 'body' => json_encode( array(
		array( 'draft' => false, 'prerelease' => false, 'html_url' => 'https://github.test/stable', 'assets' => array( array( 'name' => 'digitalisimo-backups-1.0.48.zip', 'browser_download_url' => 'https://github.test/backups.zip' ) ) ),
		array( 'draft' => false, 'prerelease' => true, 'html_url' => 'https://github.test/beta', 'assets' => array( array( 'name' => 'digitalisimo-backups-1.0.99.zip', 'browser_download_url' => 'https://github.test/beta.zip' ) ) ),
	) ) );
}
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function get_bloginfo( $key ) { return '6.8'; }
require __DIR__ . '/../digitalisimo-backups/includes/class-digitalisimo-updater.php';
Digitalisimo_Backups_Updater::register();
$file = 'digitalisimo-backups/digitalisimo-backups.php';
foreach ( array( 'plugin_action_links_' . $file, 'network_admin_plugin_action_links_' . $file, 'admin_post_digitalisimo_backups_check_updates', 'digitalisimo_backups_refresh_updates' ) as $hook ) if ( ! isset( $GLOBALS['updater_hooks'][ $hook ] ) ) throw new RuntimeException( 'Falta el hook ' . $hook );
if ( $GLOBALS['updater_scheduled'] !== array( 'digitalisimo_backups_five_minutes', 'digitalisimo_backups_refresh_updates' ) ) throw new RuntimeException( 'No se programó la consulta automática.' );
$network = Digitalisimo_Backups_Updater::action_link( array() );
if ( false === strpos( $network[0], 'Buscar actualizaciones' ) || false === strpos( $network[0], 'digitalisimo_context=network' ) ) throw new RuntimeException( 'Falta el enlace de red.' );
$GLOBALS['updater_network'] = false;
$GLOBALS['updater_network_admin'] = false;
$site = Digitalisimo_Backups_Updater::action_link( array() );
if ( false === strpos( $site[0], 'digitalisimo_context=site' ) ) throw new RuntimeException( 'Falta el enlace del sitio.' );
$checked = Digitalisimo_Backups_Updater::check( new stdClass() );
if ( ( $checked->response[ $file ]->new_version ?? '' ) !== '1.0.48' || ( $checked->response[ $file ]->package ?? '' ) !== 'https://github.test/backups.zip' ) throw new RuntimeException( 'La actualización estable no quedó disponible.' );
if ( ! Digitalisimo_Backups_Updater::auto_update( false, (object) array( 'plugin' => $file ) ) ) throw new RuntimeException( 'La actualización automática no está activa.' );
Digitalisimo_Backups_Updater::refresh();
if ( 1 !== $GLOBALS['updater_checks'] || isset( $GLOBALS['updater_cache']['digitalisimo_backups_release'] ) ) throw new RuntimeException( 'La comprobación no limpió la caché.' );
echo "DIGITALÍSIMO Backups: enlace, detección y actualización automática verificados.\n";
