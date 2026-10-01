<?php
/** Inventario de Site Kit aislado por sitio y sin exponer opciones sensibles. */
define( 'ABSPATH', __DIR__ );
$blog_id = 1;
$site_options = array(
	1 => array( 'googlesitekit_analytics-4_settings' => array( 'measurementID' => 'G-PRINCIPAL', 'googleTagID' => 'GT-PRINCIPAL', 'useSnippet' => true, 'secretToken' => 'NO-MOSTRAR' ) ),
	2 => array( 'googlesitekit_analytics-4_settings' => array( 'measurementID' => 'G-SUBSITIO', 'trackingDisabled' => array( 'loggedinUsers', 'unknown' ) ), 'googlesitekit_tagmanager_settings' => array( 'containerID' => 'GTM-SUBSITIO', 'useSnippet' => false ), 'googlesitekit_search-console_settings' => array( 'propertyID' => 'https://sub.example.test/' ) ),
);
class Digitalisimo_Integrations_Performance_Migration { public static function site_kit_active() { return true; } }
function get_option( $key, $default = false ) { global $site_options, $blog_id; return $site_options[ $blog_id ][ $key ] ?? $default; }
function get_current_blog_id() { global $blog_id; return $blog_id; }
function get_site( $id ) { return in_array( (int) $id, array( 1, 2 ), true ) ? (object) array( 'blog_id' => $id ) : null; }
function switch_to_blog( $id ) { global $blog_id; $blog_id = (int) $id; }
function restore_current_blog() { global $blog_id; $blog_id = 1; }
function home_url( $path ) { global $blog_id; return 'https://site' . $blog_id . '.example.test' . $path; }
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-tracking.php';
$class = Digitalisimo_Integrations_Performance_Tracking::class;
ob_start(); $class::render_site_kit( false ); $site = ob_get_clean();
if ( false === strpos( $site, 'G-PRINCIPAL' ) || false !== strpos( $site, 'G-SUBSITIO' ) || false !== strpos( $site, 'NO-MOSTRAR' ) ) throw new RuntimeException( 'El inventario del sitio filtró valores ajenos o sensibles.' );
$_GET['site_id'] = 2;
ob_start(); $class::render_site_kit( true ); $network = ob_get_clean();
if ( false === strpos( $network, 'G-SUBSITIO' ) || false === strpos( $network, 'GTM-SUBSITIO' ) || false === strpos( $network, 'https://sub.example.test/' ) || false !== strpos( $network, 'G-PRINCIPAL' ) || false !== strpos( $network, 'unknown' ) || 1 !== get_current_blog_id() ) throw new RuntimeException( 'La red debe mostrar y restaurar sólo el sitio seleccionado.' );
echo "Site Kit: valores permitidos, aislamiento por sitio y restauración de contexto.\n";
