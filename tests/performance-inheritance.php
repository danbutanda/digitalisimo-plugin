<?php
/** Resolución aislada de sitio, red y default para las opciones de rendimiento. */
define( 'ABSPATH', __DIR__ );
class Digitalisimo_Integrations_Settings {
	const OPTION = 'digitalisimo_integrations_options';
	public static function defaults() { return array( 'perf_font_guard_mode' => 'off', 'perf_preload_mode' => 'off', 'perf_tracking_enabled' => 0 ); }
}
function is_multisite() { return true; }
function get_option( $key, $default = array() ) { return $GLOBALS['sites'][ $GLOBALS['site'] ][ $key ] ?? $default; }
function get_site_option( $key, $default = array() ) { return $GLOBALS['network'][ $key ] ?? $default; }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-resolver.php';
$class = Digitalisimo_Integrations_SEO_Resolver::class;
$GLOBALS['site'] = 1;
$GLOBALS['network'] = array( Digitalisimo_Integrations_Settings::OPTION => array( 'perf_font_guard_mode' => 'manual', 'perf_preload_mode' => 'auto' ) );
$GLOBALS['sites'] = array(
	1 => array( Digitalisimo_Integrations_Settings::OPTION => array( 'perf_font_guard_mode' => 'auto' ), 'digitalisimo_seo_network_inherit' => array() ),
	2 => array( Digitalisimo_Integrations_Settings::OPTION => array( 'perf_preload_mode' => 'manual' ), 'digitalisimo_seo_network_inherit' => array( 'perf_preload_mode' => 0 ) ),
);
if ( 'manual' !== $class::option( 'perf_font_guard_mode' ) || 'network' !== $class::option_with_origin( 'perf_font_guard_mode' )['origin'] ) throw new RuntimeException( 'Sin override, el sitio debe heredar la red.' );
$GLOBALS['sites'][1]['digitalisimo_seo_network_inherit']['perf_font_guard_mode'] = 0;
if ( 'auto' !== $class::option( 'perf_font_guard_mode' ) || 'site' !== $class::option_with_origin( 'perf_font_guard_mode' )['origin'] ) throw new RuntimeException( 'El override del sitio debe prevalecer.' );
$GLOBALS['site'] = 2;
if ( 'manual' !== $class::option( 'perf_preload_mode' ) || 'manual' !== $class::option( 'perf_font_guard_mode' ) ) throw new RuntimeException( 'Los sitios deben resolver opciones independientemente.' );
if ( 0 !== $class::option( 'perf_tracking_enabled' ) || 'default' !== $class::option_with_origin( 'perf_tracking_enabled' )['origin'] ) throw new RuntimeException( 'Sin valor de red o sitio debe regir el default.' );
echo "Herencia de rendimiento: sitio, red y default independientes.\n";
