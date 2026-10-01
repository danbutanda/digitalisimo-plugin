<?php
/** Tracking sólo opt-in, con bloqueo de duplicados y modos reversibles. */
define( 'ABSPATH', __DIR__ );
$safe = true; $conflict = ''; $options = array( 'perf_tracking_enabled' => 1, 'perf_gt_id' => 'GT-ABC123', 'perf_ga4_id' => 'G-XYZ123', 'perf_gtm_id' => 'GTM-ABC123', 'perf_tracking_mode' => 'interaction', 'perf_tracking_delay' => 12, 'perf_tracking_fallback' => 12 );
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { global $safe; return $safe; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { global $options; return $options[ $key ] ?? null; } }
class Digitalisimo_Integrations_Performance_Migration { public static function tracking_conflict() { global $conflict; return $conflict; } }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value, $flags ) { return json_encode( $value, $flags ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function get_option( $key, $default = false ) { global $site_kit_settings; return $site_kit_settings[ $key ] ?? $default; }
function wp_scripts() { global $scripts; return $scripts; }
$scripts = (object) array( 'queue' => array(), 'registered' => array() );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-tracking.php';
$class = Digitalisimo_Integrations_Performance_Tracking::class;
if ( 'GT-ABC123' !== $class::sanitize_id( 'gt-abc123', 'gt' ) || '' !== $class::sanitize_id( 'GTM-ABC123', 'ga4' ) ) throw new RuntimeException( 'Los IDs deben validarse por tipo.' );
if ( 120 !== $class::sanitize_seconds( 999 ) || 'normal' !== $class::sanitize_mode( 'unsafe' ) ) throw new RuntimeException( 'Modo y delay deben tener límites.' );
$conflict = 'Site Kit activo';
ob_start(); $class::head(); $blocked = ob_get_clean();
if ( '' !== $blocked ) throw new RuntimeException( 'No debe duplicar tracking de Site Kit o Custom Code.' );
$conflict = '';
$scripts->registered['other-tag'] = (object) array( 'src' => 'https://www.googletagmanager.com/gtag/js?id=G-XYZ123' ); $scripts->queue[] = 'other-tag';
if ( $class::ready() ) throw new RuntimeException( 'Un script Google ya encolado debe bloquear la salida.' );
$scripts->queue = array();
ob_start(); $class::head(); $head = ob_get_clean();
if ( false === strpos( $head, 'window,document,' ) || false === strpos( $head, '"mode":"interaction"' ) || false !== strpos( $head, 'mousemove' ) || false === strpos( $head, 'w.dataLayer=w.dataLayer||[]' ) || false === strpos( $head, 'c.fallback*1000' ) ) throw new RuntimeException( 'Debe crear dataLayer temprano y cargar una sola vez por interacción con fallback.' );
ob_start(); $class::body(); $body = ob_get_clean();
if ( false === strpos( $body, 'ns.html?id=GTM-ABC123' ) ) throw new RuntimeException( 'GTM necesita noscript en wp_body_open.' );
$safe = false;
if ( $class::ready() ) throw new RuntimeException( 'Editor, administración y previews no deben ejecutar tracking.' );
$safe = true; $options['perf_gt_id'] = ''; $options['perf_ga4_id'] = ''; $options['perf_gtm_id'] = '';
$site_kit_settings = array( 'googlesitekit_analytics-4_settings' => array( 'googleTagID' => 'GT-SITEKIT', 'measurementID' => 'G-SITEKIT' ), 'googlesitekit_tagmanager_settings' => array( 'containerID' => 'GTM-SITEKIT' ) );
ob_start(); $class::head(); $fallback = ob_get_clean();
if ( false === strpos( $fallback, 'GT-SITEKIT' ) || false === strpos( $fallback, 'G-SITEKIT' ) || false === strpos( $fallback, 'GTM-SITEKIT' ) ) throw new RuntimeException( 'Los IDs vacíos deben resolverse desde Site Kit del sitio actual.' );
echo "Tracking: IDs, duplicados, interacción y GTM protegidos.\n";
