<?php
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'DIGITALISIMO_TOOLS_VERSION', '1.0.17' );
define( 'MINUTE_IN_SECONDS', 60 );
$test_transients = array();
$test_hooks = array();
$test_version = '1.0.17';
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { global $test_hooks; $test_hooks[ $name ][] = $priority; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
function get_file_data() { return array( 'Version' => '1.0.15' ); }
function is_multisite() { return true; }
function is_main_site() { return true; }
function is_network_admin() { return true; }
function current_user_can() { return true; }
function wp_next_scheduled() { return true; }
function get_site_transient( $key ) { global $test_transients; return $test_transients[ $key ] ?? false; }
function set_site_transient( $key, $value ) { global $test_transients; $test_transients[ $key ] = $value; }
function delete_site_transient( $key ) { global $test_transients; unset( $test_transients[ $key ] ); }
function delete_transient() {}
function wp_remote_get() {
	global $test_version;
	return array( 'code' => 200, 'body' => json_encode( array( array( 'assets' => array( array( 'name' => 'digitalisimo-tools-' . $test_version . '.zip', 'browser_download_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v-test/digitalisimo-tools-' . $test_version . '.zip' ) ), 'html_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v-test' ) ) ) );
}
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url_raw( $url ) { return $url; }
function get_bloginfo() { return '6.8'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url ) { return $url . '&_wpnonce=test'; }
function esc_url( $url ) { return $url; }
require __DIR__ . '/../digitalisimo-seo/includes/class-tools-update-bridge.php';
require __DIR__ . '/../digitalisimo-tools/includes/class-updater.php';
Digitalisimo_Tools_Update_Bridge::init();
\Digitalisimo\Tools\Updater::init();
$assert = function ( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); };
$assert( in_array( 100, $test_hooks['site_transient_update_plugins'] ?? array(), true ), 'El puente debe ejecutarse después del actualizador antiguo de Tools.' );
$stale = (object) array( 'response' => array(), 'no_update' => array( Digitalisimo_Tools_Update_Bridge::FILE => (object) array( 'new_version' => '1.0.15' ) ) );
$fresh = Digitalisimo_Tools_Update_Bridge::inject( $stale );
$assert( '1.0.17' === $fresh->response[ Digitalisimo_Tools_Update_Bridge::FILE ]->new_version && ! isset( $fresh->no_update[ Digitalisimo_Tools_Update_Bridge::FILE ] ), 'El puente debe recuperar la actualización que ocultó Tools 1.0.15.' );
$links = Digitalisimo_Tools_Update_Bridge::action_link( array() );
$assert( false !== strpos( $links[0] ?? '', 'Buscar actualizaciones' ) && false !== strpos( $links[0], 'digitalisimo_context=network' ), 'La instalación antigua necesita un enlace manual con contexto de red.' );
$test_version = '1.0.18';
delete_site_transient( Digitalisimo_Tools_Update_Bridge::CACHE );
delete_site_transient( \Digitalisimo\Tools\Updater::CACHE_KEY );
$transient = \Digitalisimo\Tools\Updater::check( (object) array() );
$assert( '1.0.18' === $transient->response[ \Digitalisimo\Tools\Updater::FILE ]->new_version, 'Tools nuevo debe detectar una versión superior.' );
$assert( false !== strpos( \Digitalisimo\Tools\Updater::action_link( array() )[0] ?? '', 'Buscar actualizaciones' ), 'Tools nuevo debe mostrar su enlace propio.' );
echo "Actualizador Tools: puente antiguo, nueva versión y enlace de red correctos.\n";
