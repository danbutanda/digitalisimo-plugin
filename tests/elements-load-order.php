<?php
/** Reproduce el orden de carga de una red con Elementor activo para la red. */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$hooks = array();
$elementor_loaded = false;
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function register_activation_hook( $file, $callback ) { $GLOBALS['activation'] = $callback; }
function register_deactivation_hook( $file, $callback ) { $GLOBALS['deactivation'] = $callback; }
function is_multisite() { return true; }
function is_admin() { return true; }
function is_main_site() { return true; }
function wp_next_scheduled() { return true; }
function get_option( $name, $default = false ) { return 'active_plugins' === $name ? array( 'digitalisimo-elements/pro-elements.php' ) : $default; }
function get_site_option( $name, $default = false ) { return 'active_sitewide_plugins' === $name ? array( 'elementor/elementor.php' => 1 ) : $default; }
function did_action( $name ) { return 'elementor/loaded' === $name && $GLOBALS['elementor_loaded'] ? 1 : 0; }
function plugin_basename( $file ) { return 'digitalisimo-elements/pro-elements.php'; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugins_url( $path, $file = '' ) { return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
require __DIR__ . '/../digitalisimo-elements/pro-elements.php';
$assert = function( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); };
$assert( ! defined( 'DIGITALISIMO_ELEMENTS_CORE' ) && ! defined( 'ELEMENTOR_VERSION' ), 'Con Elementor activo para la red, el núcleo incluido no se carga.' );
$assert( array( 'Digitalisimo_Elements_Core', 'activate' ) === ( $GLOBALS['activation'] ?? null ), 'La activación del plugin ejecuta la del núcleo incluido cuando hace falta.' );
$assert( array( 'Digitalisimo_Elements_Admin_Load', 'deactivate' ) === ( $GLOBALS['deactivation'] ?? null ), 'Al desactivar se borra la lista de opciones que se piden juntas.' );
$assert( in_array( 'pro_elements_plugin_load_plugin', $hooks['plugins_loaded'] ?? array(), true ), 'La comprobación debe esperar a plugins_loaded.' );
$assert( ! in_array( 'pro_elements_plugin_fail_load', $hooks['admin_notices'] ?? array(), true ), 'No debe avisar que falta Elementor antes de terminar la carga.' );
$elementor_loaded = true;
define( 'ELEMENTOR_VERSION', '3.0.0' );
pro_elements_plugin_load_plugin();
$assert( ! in_array( 'pro_elements_plugin_fail_load', $hooks['admin_notices'] ?? array(), true ), 'Elementor activo para la red no debe marcarse como ausente.' );
$assert( in_array( 'pro_elements_plugin_fail_load_out_of_date', $hooks['admin_notices'] ?? array(), true ), 'Debe llegar a validar la versión de Elementor al estar cargado.' );
$hooks['admin_notices'] = array();
$elementor_loaded = false;
pro_elements_plugin_load_plugin();
$assert( in_array( 'pro_elements_plugin_fail_load', $hooks['admin_notices'], true ), 'Debe avisar si Elementor realmente no carga.' );
// El aviso se puede cerrar (queja del propietario del 2026-11-22): el problema queda en «Salud del sitio».
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
$assert( in_array( 'digitalisimo_elements_health_tests', $hooks['site_status_tests'] ?? array(), true ), 'Si Elements no carga, lo dice «Salud del sitio».' );
$health = digitalisimo_elements_health();
$assert( 'critical' === $health['status'] && false !== strpos( $health['description'], 'elementor/' ) && isset( digitalisimo_elements_health_tests( array() )['direct']['digitalisimo_elements_loaded'] ), 'La prueba de «Salud del sitio» es crítica y dice qué falta.' );
echo "DIGITALÍSIMO Elements: orden de carga Multisite correcto.\n";
