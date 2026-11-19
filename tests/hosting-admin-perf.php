<?php
/**
 * Hosting sin una consulta propia en cada petición (auditoría admin-perf, 2026-11-18), sin WordPress. La marca de la
 * migración desde Ecommerce se lee en cada petición (escritorio, heartbeat, REST y front) y se guardaba sin autoload:
 * una consulta cada vez. Ahora una migración nueva la guarda con autoload y una instalación que ya la tenía sin
 * autoload la pasa una sola vez (WordPress 6.4+), sin repetir la migración ni tocar los datos.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array(); $GLOBALS['autoload'] = array(); $GLOBALS['set_autoload'] = array();
function is_multisite() { return false; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
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
if ( ! empty( $GLOBALS['failures'] ) ) exit( 1 );
echo "Hosting: la marca de migración no cuesta una consulta en cada petición.\n";
