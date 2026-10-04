<?php
/** Actualizador y marca del derivado, sin WordPress cargado. */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.4' );
define( 'MINUTE_IN_SECONDS', 60 );
$cache = array();
$hooks = array();
$multisite = false;
$active_plugins = array();
$network_active_plugins = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
function is_multisite() { return $GLOBALS['multisite']; }
function get_option( $key, $default = false ) { return 'active_plugins' === $key ? $GLOBALS['active_plugins'] : $default; }
function get_site_option( $key, $default = false ) { return 'active_sitewide_plugins' === $key ? $GLOBALS['network_active_plugins'] : $default; }
function is_main_site() { return true; }
function is_network_admin() { return false; }
function current_user_can() { return true; }
function wp_next_scheduled() { return true; }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function delete_transient() {}
function wp_remote_get() { return array( 'code' => 200, 'body' => json_encode( array( array( 'assets' => array( array( 'name' => 'digitalisimo-elements-4.3.0.5.zip', 'browser_download_url' => 'https://github.com/example/digitalisimo-elements-4.3.0.5.zip' ) ), 'html_url' => 'https://github.com/example/release' ) ) ) ); }
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url_raw( $url ) { return $url; }
function esc_url( $url ) { return $url; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function get_bloginfo() { return '6.8'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url ) { return $url . '&_wpnonce=test'; }
function plugins_url( $path ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . $path; }
require __DIR__ . '/../digitalisimo-elements/digitalisimo-updater.php';
require __DIR__ . '/../digitalisimo-elements/digitalisimo-brand.php';
require __DIR__ . '/../digitalisimo-elements/digitalisimo-compat.php';
$assert = function( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); };
$assert( ! digitalisimo_elements_conflicting_plugin_active(), 'Debe cargar cuando no existe otra versión Pro activa.' );
$active_plugins = array( 'elementor-pro/elementor-pro.php' );
$assert( digitalisimo_elements_conflicting_plugin_active(), 'Debe detectar Elementor Pro del sitio antes de cargar módulos.' );
$active_plugins = array();
$multisite = true;
$network_active_plugins = array( 'pro-elements/pro-elements.php' => time() );
$assert( digitalisimo_elements_conflicting_plugin_active(), 'Debe detectar PRO Elements activo para la red.' );
$multisite = false;
$network_active_plugins = array();
Digitalisimo_Elements_Updater::init();
$transient = Digitalisimo_Elements_Updater::check( (object) array() );
$file = Digitalisimo_Elements_Updater::FILE;
$assert( 'digitalisimo-elements/pro-elements.php' === $file, 'El actualizador debe identificar el archivo real.' );
$assert( '4.3.0.5' === $transient->response[ $file ]->new_version, 'Debe detectar una versión superior propia.' );
$assert( true === $transient->response[ $file ]->autoupdate, 'La oferta debe indicar actualización automática.' );
$assert( true === Digitalisimo_Elements_Updater::auto_update( false, (object) array( 'plugin' => $file ) ), 'WordPress debe instalar automáticamente la actualización de Elements.' );
$assert( false === Digitalisimo_Elements_Updater::auto_update( false, (object) array( 'plugin' => 'otro/plugin.php' ) ), 'No debe activar actualizaciones automáticas de otro plugin.' );
$assert( false !== strpos( Digitalisimo_Elements_Updater::action_link( array() )[0], 'digitalisimo_context=site' ), 'La acción debe conservar el contexto del sitio.' );
$offer = Digitalisimo_Elements_Updater::uri_update( false, array(), $file, array() );
$assert( '4.3.0.5' === $offer['version'] && true === $offer['autoupdate'], 'Update URI debe entregar el paquete propio con actualización automática.' );
$assert( false === Digitalisimo_Elements_Updater::uri_update( false, array(), 'otro/plugin.php', array() ), 'No debe modificar otros plugins.' );
ob_start(); Digitalisimo_Elements_Brand::page(); $html = ob_get_clean();
$assert( false !== strpos( $html, 'logo-blanco.webp' ) && false !== strpos( $html, 'icono.png' ) && false !== strpos( $html, 'PRO Elements 4.3.0' ), 'La página debe mostrar ambos logos y atribución.' );
echo "DIGITALÍSIMO Elements: actualización, contexto y atribución correctos.\n";
