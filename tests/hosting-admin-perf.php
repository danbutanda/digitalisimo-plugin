<?php
/**
 * Hosting sin una consulta propia en cada petición (auditoría admin-perf, 2026-11-18), sin WordPress. La marca de la
 * migración desde Ecommerce se lee en cada petición (escritorio, heartbeat, REST y front) y se guardaba sin autoload:
 * una consulta cada vez. Ahora una migración nueva la guarda con autoload y una instalación que ya la tenía sin
 * autoload la pasa una sola vez (WordPress 6.4+), sin repetir la migración ni tocar los datos. Con WooCommerce, los
 * ajustes de Hosting (sin autoload: guardan la clave de Namecheap) se leían en `init` en cada petición; ahora sólo
 * cuando WooCommerce llama a un gancho del carrito o del pago, que siguen haciendo lo mismo.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array(); $GLOBALS['autoload'] = array(); $GLOBALS['set_autoload'] = array();
function is_multisite() { return false; }
/** Carrito de prueba: un plan de hosting (8) sin dominio y otro hosting (9). */
class Digi_Test_Cart { public $removed = array(); public $items = array( 'a' => array( 'product_id' => 8 ), 'b' => array( 'product_id' => 9 ) ); public function get_cart() { return $this->items; } public function remove_cart_item( $k ) { $this->removed[] = $k; } }
function WC() { static $wc = null; if ( ! $wc ) $wc = (object) array( 'cart' => new Digi_Test_Cart() ); return $wc; }
function wc_add_notice( $msg, $type ) { $GLOBALS['notices'][] = $msg; }
function has_term() { return false; }
function absint( $v ) { return abs( (int) $v ); }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ] = $cb; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ] = $cb; }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function wp_unslash( $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function get_option( $key, $default = false ) { $GLOBALS['reads'][ $key ] = ( $GLOBALS['reads'][ $key ] ?? 0 ) + 1; return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; $GLOBALS['autoload'][ $key ] = $autoload; return true; }
function wp_load_alloptions() { $out = array(); foreach ( $GLOBALS['options'] as $k => $v ) if ( true === ( $GLOBALS['autoload'][ $k ] ?? null ) ) $out[ $k ] = $v; return $out; }
function wp_set_option_autoload( $key, $autoload ) { $GLOBALS['set_autoload'][] = array( $key, $autoload ); $GLOBALS['autoload'][ $key ] = (bool) $autoload; return true; }
function fail( $message ) { $GLOBALS['failures'][] = $message; fwrite( STDERR, "FALLA: $message\n" ); }
require __DIR__ . '/../digitalisimo-hosting/includes/class-hosting.php';
$flag = 'digitalisimo_hosting_ecommerce_migrated';

// Instalación que ya migró, con la marca sin autoload: se pasa a autoload una vez.
$GLOBALS['options'][ $flag ] = 1700000000; $GLOBALS['autoload'][ $flag ] = false;
$GLOBALS['options']['digitalisimo_hosting_options'] = array( 'api_user' => 'conservado' );
Digitalisimo_Hosting::migrate_legacy_options();
Digitalisimo_Hosting::migrate_legacy_options();
if ( array( array( $flag, true ) ) !== $GLOBALS['set_autoload'] ) fail( 'La marca ya guardada sin autoload debe pasar a autoload una sola vez: ' . json_encode( $GLOBALS['set_autoload'] ) );
if ( 1700000000 !== $GLOBALS['options'][ $flag ] || array( 'api_user' => 'conservado' ) !== $GLOBALS['options']['digitalisimo_hosting_options'] ) fail( 'Pasarla a autoload no cambia la marca ni los datos de Hosting.' );

// Migración nueva: la marca se guarda ya con autoload.
$GLOBALS['options'] = array( 'digitalisimo_integrations_options' => array( 'namecheap_api_user' => 'usuario' ) ); $GLOBALS['autoload'] = array(); $GLOBALS['set_autoload'] = array();
Digitalisimo_Hosting::migrate_legacy_options();
if ( true !== ( $GLOBALS['autoload'][ $flag ] ?? null ) ) fail( 'Una migración nueva guarda la marca con autoload.' );
if ( 'usuario' !== ( $GLOBALS['options']['digitalisimo_hosting_options']['api_user'] ?? '' ) ) fail( 'La migración sigue copiando los datos de Ecommerce.' );

// Con WooCommerce: registrar los ganchos no lee los ajustes; aplicarlos sí, y respetan «activado».
$GLOBALS['reads'] = array(); $GLOBALS['hooks'] = array();
$GLOBALS['options']['digitalisimo_hosting_options'] = array( 'enabled' => 1 );
Digitalisimo_Hosting::cart_compatibility();
if ( ! empty( $GLOBALS['reads']['digitalisimo_hosting_options'] ) ) fail( 'Registrar los ganchos de WooCommerce leyó los ajustes de Hosting en init (una consulta en cada petición, también en el escritorio).' );
if ( ! isset( $GLOBALS['hooks']['woocommerce_add_cart_item_data'] ) ) fail( 'Los ganchos del carrito siguen registrados con WooCommerce.' );
$_REQUEST['digitalisimo_domain'] = 'ejemplo.mx';
$data = call_user_func( $GLOBALS['hooks']['woocommerce_add_cart_item_data'], array(), 5, 0 );
if ( 'ejemplo.mx' !== ( $data['digitalisimo_domain'] ?? '' ) ) fail( 'Activado, el dominio sigue entrando en el carrito.' );
$GLOBALS['options']['digitalisimo_hosting_options'] = array( 'enabled' => 0 );
$data = call_user_func( $GLOBALS['hooks']['woocommerce_add_cart_item_data'], array(), 5, 0 );
if ( isset( $data['digitalisimo_domain'] ) ) fail( 'Desactivado, el carrito no cambia.' );
$fields = call_user_func( $GLOBALS['hooks']['woocommerce_checkout_fields'] ?? function( $f ) { return $f; }, array( 'billing' => array( 'x' => 1 ) ) );
if ( array( 'billing' => array( 'x' => 1 ) ) !== $fields ) fail( 'Sin «simplificar el pago», los campos del pago no cambian.' );

// Las tres opciones encendidas: cada gancho hace lo suyo; apagadas, nada. Y siguen siendo métodos con nombre
// (un remove_filter( …, array( 'Digitalisimo_Hosting', … ) ) de un fragmento de Tools funciona).
foreach ( array( 'woocommerce_check_cart_items' => 'maybe_validate_cart', 'woocommerce_add_to_cart_validation' => 'maybe_replace_hosting', 'woocommerce_checkout_fields' => 'maybe_checkout_fields', 'woocommerce_add_cart_item_data' => 'maybe_cart_domain' ) as $hook => $method ) {
	if ( array( 'Digitalisimo_Hosting', $method ) !== ( $GLOBALS['hooks'][ $hook ] ?? null ) ) fail( "El gancho $hook debe ser el método con nombre Digitalisimo_Hosting::$method." );
}
$GLOBALS['options']['digitalisimo_hosting_options'] = array( 'enabled' => 1, 'require_domain_hosting' => 1, 'replace_hosting' => 1, 'simplify_checkout' => 1, 'hosting_product_ids' => '8, 9', 'domain_products' => ".mx|44" );
$GLOBALS['notices'] = array();
call_user_func( $GLOBALS['hooks']['woocommerce_check_cart_items'] );
if ( 1 !== count( $GLOBALS['notices'] ) ) fail( '«Exigir dominio y hosting»: un plan sin dominio debe avisar en el carrito.' );
$passed = call_user_func( $GLOBALS['hooks']['woocommerce_add_to_cart_validation'], true, 9, 1 );
if ( true !== $passed || array( 'a', 'b' ) !== WC()->cart->removed ) fail( '«Reemplazar hosting»: añadir otro plan quita los planes que ya estaban: ' . json_encode( WC()->cart->removed ) );
$fields = call_user_func( $GLOBALS['hooks']['woocommerce_checkout_fields'], array( 'billing' => array( 'x' => 1 ) ) );
if ( ! isset( $fields['billing']['billing_first_name'] ) || isset( $fields['billing']['x'] ) ) fail( '«Simplificar el pago»: los campos del pago se sustituyen.' );
$GLOBALS['options']['digitalisimo_hosting_options']['require_domain_hosting'] = 0; $GLOBALS['notices'] = array();
call_user_func( $GLOBALS['hooks']['woocommerce_check_cart_items'] );
if ( $GLOBALS['notices'] ) fail( 'Con «Exigir dominio y hosting» apagado, el carrito no avisa.' );

if ( ! empty( $GLOBALS['failures'] ) ) exit( 1 );
echo "Hosting: ni la marca de migración ni los ajustes de WooCommerce cuestan una consulta en cada petición.\n";
