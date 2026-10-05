<?php
/** Migración no destructiva de Ecommerce hacia Hosting, sin WordPress cargado. */
define( 'ABSPATH', __DIR__ );
$site = array();
$options = array(
	'digitalisimo_integrations_options' => array(
		'enable_woocommerce' => 1,
		'namecheap_api_user' => 'api-user',
		'namecheap_username' => 'account',
		'namecheap_api_key' => 'secret',
		'namecheap_client_ip' => '203.0.113.9',
		'domain_products' => ".mx|44",
		'hosting_product_ids' => '8, 9',
		'hosting_category_ids' => '12',
		'require_domain_hosting' => 1,
		'replace_hosting' => 1,
		'simplify_checkout' => 1,
	),
	'digitalisimo_module_network_inherit' => array(),
);
$site['digitalisimo_integrations_options'] = array( 'namecheap_api_user' => 'network-user', 'domain_products' => '.com|33' );
function is_multisite() { return true; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['site'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['site'][ $key ] = $value; return true; }
require __DIR__ . '/../digitalisimo-hosting/includes/class-hosting.php';
Digitalisimo_Hosting::migrate_legacy_options();
$fail = static function( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); };
$local = $GLOBALS['options']['digitalisimo_hosting_options'];
$network = $GLOBALS['site']['digitalisimo_hosting_options'];
$fail( 1 === $local['enabled'] && 'api-user' === $local['api_user'] && 'account' === $local['username'], 'No se migraron los datos Namecheap del sitio.' );
$fail( '8, 9' === $local['hosting_product_ids'] && 1 === $local['require_domain_hosting'] && 1 === $local['simplify_checkout'], 'No se migraron las reglas de compra de hosting.' );
$fail( 1 === $local['inherit_network'], 'La herencia predeterminada de Ecommerce debe conservarse.' );
$fail( 'network-user' === $network['api_user'] && '.com|33' === $network['domain_products'], 'No se migraron los valores de red.' );
$GLOBALS['options']['digitalisimo_hosting_options']['api_user'] = 'hosting-prioritario';
unset( $GLOBALS['options']['digitalisimo_hosting_ecommerce_migrated'] );
Digitalisimo_Hosting::migrate_legacy_options();
$fail( 'hosting-prioritario' === $GLOBALS['options']['digitalisimo_hosting_options']['api_user'], 'La migración no debe sobrescribir datos ya configurados en Hosting.' );
echo "OK Hosting migration\n";
