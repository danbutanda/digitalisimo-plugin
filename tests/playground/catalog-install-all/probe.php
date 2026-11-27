<?php
/**
 * «Instalar todos» en un WordPress real: se sigue la cadena de admin-post del botón con nonces reales, el instalador real
 * de WordPress (Plugin_Upgrader) y ZIP locales en lugar de GitHub (filtro upgrader_pre_download). Escenario «tools»: el
 * catálogo de Tools instala los tres que faltan. Escenario «seo»: el de SEO, con el ZIP de IA Tools ausente, instala los
 * demás y lo informa como fallido. En los dos, DIGITALÍSIMO Hosting (privado desde el 2026-11-27) viene en el índice y
 * tiene ZIP, pero no aparece en la tabla, «Instalar todos» no lo descarga y una petición a mano con su slug muere con
 * «no disponible»; en «seo» ya está instalado a mano y la tabla tampoco lo muestra.
 */
define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$scenario = trim( (string) file_get_contents( '/wordpress/wp-content/probe/scenario' ) );
$notices  = array();
set_error_handler( static function ( $no, $str, $file, $line ) use ( &$notices ) { if ( false !== strpos( $file, 'digitalisimo' ) ) $notices[] = basename( $file ) . ':' . $line . ' ' . $str; return false; } );
wp_set_current_user( 1 );
$problems = array();
$class    = 'seo' === $scenario ? 'Digitalisimo_Plugin_Catalog' : 'Digitalisimo\\Tools\\Plugin_Catalog';
$action   = 'seo' === $scenario ? Digitalisimo_Plugin_Catalog::INSTALL_ALL_ACTION : \Digitalisimo\Tools\Plugin_Catalog::INSTALL_ALL;
$versions = array( 'digitalisimo-seo' => '1.0.254', 'digitalisimo-ia-tools' => '1.0.60', 'digitalisimo-hosting' => '1.0.30', 'digitalisimo-backups' => '1.0.81', 'digitalisimo-tools' => '1.0.38', 'digitalisimo-elements' => '4.3.0.187', 'digitalisimo-migration' => '1.0.28' );
$entries  = array();
foreach ( $versions as $slug => $version ) $entries[ $slug ] = array( 'version' => $version, 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/' . $slug . '-' . $version . '.zip' );
set_site_transient( 'digitalisimo_plugins_manifest', array( 'tag' => 'v2026.11.18.999', 'plugins' => $entries ), 600 );
$class::clear();
$downloads = array();
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$problems ) { $problems[] = 'petición HTTP inesperada: ' . $url; return new WP_Error( 'probe', 'sin red' ); }, 10, 3 );
add_filter( 'upgrader_pre_download', static function ( $reply, $package ) use ( &$downloads ) {
	$downloads[] = basename( $package );
	$local = '/wordpress/wp-content/probe/zips/' . basename( $package );
	if ( ! file_exists( $local ) ) return new WP_Error( 'probe_missing', 'ZIP ausente' );
	$tmp = wp_tempnam( basename( $package ) ); copy( $local, $tmp ); return $tmp;
}, 10, 2 );
add_filter( 'wp_redirect', static function ( $location ) { throw new RuntimeException( $location ); } );
add_filter( 'wp_die_handler', static function () { return static function ( $message ) { throw new LogicException( is_string( $message ) ? $message : 'wp_die' ); }; } );
function probe_query( $url ) { $query = array(); parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query ); return $query; }
function probe_page( $class, $get ) { $_GET = $get; $_REQUEST = $get; ob_start(); $class::page(); return (string) ob_get_clean(); }

$html = probe_page( $class, array() );
if ( false !== strpos( $html, 'DIGITALÍSIMO Hosting' ) || false !== strpos( $html, 'digitalisimo-hosting' ) ) $problems[] = 'la tabla muestra Hosting';
// Una petición armada a mano con slug=digitalisimo-hosting y su nonce real no lo instala.
$single = 'seo' === $scenario ? Digitalisimo_Plugin_Catalog::INSTALL_ACTION : \Digitalisimo\Tools\Plugin_Catalog::ACTION;
$forged = array( 'action' => $single, 'slug' => 'digitalisimo-hosting', 'digitalisimo_context' => 'site', '_wpnonce' => wp_create_nonce( $single . '_digitalisimo-hosting' ) );
$_GET = $forged; $_REQUEST = $forged; $died = '';
try { $class::install(); } catch ( LogicException $e ) { $died = $e->getMessage(); } catch ( RuntimeException $e ) { $died = 'redirigió a ' . $e->getMessage(); }
if ( ! preg_match( '/no (está )?disponible/u', $died ) ) $problems[] = 'install con slug=digitalisimo-hosting no respondió «no disponible»: ' . $died;
if ( $downloads ) $problems[] = 'install con slug=digitalisimo-hosting descargó: ' . implode( ',', $downloads );
$label = 'Instalar todos (3)';
if ( false === strpos( $html, $label ) ) $problems[] = 'el botón no dice «' . $label . '»';
$url = preg_match( '#<a class="button button-primary button-hero" href="([^"]+)"#', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
if ( '' === $url ) $problems[] = 'sin botón «Instalar todos»';
$steps = 0;
while ( $url && false !== strpos( $url, 'admin-post.php' ) && $steps < 12 ) {
	$steps++;
	$query = probe_query( $url ); $_GET = $query; $_REQUEST = $query;
	if ( $action !== ( $query['action'] ?? '' ) ) { $problems[] = 'acción inesperada en el paso ' . $steps; break; }
	try { $class::install_all(); $problems[] = 'el paso ' . $steps . ' no redirigió'; $url = ''; }
	catch ( RuntimeException $redirect ) { $url = $redirect->getMessage(); }
}
$expected = array( 'digitalisimo-ia-tools-1.0.60.zip', 'digitalisimo-elements-4.3.0.187.zip', 'digitalisimo-migration-1.0.28.zip' );
if ( $downloads !== $expected ) $problems[] = 'descargas en otro orden: ' . implode( ',', $downloads );
if ( in_array( 'digitalisimo-hosting-1.0.30.zip', $downloads, true ) ) $problems[] = '«Instalar todos» descargó Hosting';
if ( 4 !== $steps ) $problems[] = 'pasos: ' . $steps . ' (se esperaban 4)';
$final = probe_query( $url );
if ( 'digitalisimo-plugins' !== ( $final['page'] ?? '' ) || 'done' !== ( $final['digitalisimo_install_all'] ?? '' ) || false === strpos( $url, '/wp-admin/admin.php' ) ) $problems[] = 'no volvió al catálogo con el resumen: ' . $url;
$files = array( 'digitalisimo-ia-tools' => 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting' => 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-elements' => 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration' => 'digitalisimo-migration/digitalisimo-migration.php' );
foreach ( $files as $slug => $file ) {
	// Hosting sólo existe en «seo», instalado a mano antes de la prueba; IA Tools falla en «seo» por su ZIP ausente.
	$should = 'digitalisimo-hosting' === $slug ? 'seo' === $scenario : ! ( 'seo' === $scenario && 'digitalisimo-ia-tools' === $slug );
	if ( $should !== file_exists( WP_PLUGIN_DIR . '/' . $file ) ) $problems[] = $file . ( $should ? ' no quedó instalado' : ' no debía instalarse' );
}
$html = probe_page( $class, $final );
if ( false === strpos( $html, 'notice-success' ) || false === strpos( $html, 'DIGITALÍSIMO Elements' ) ) $problems[] = 'el resumen no lista lo instalado';
if ( 'seo' === $scenario ) {
	if ( false === strpos( $html, 'No se pudo instalar: DIGITALÍSIMO IA Tools' ) ) $problems[] = 'el resumen no informa el fallo de IA Tools';
	if ( false === strpos( $html, 'Instalar el plugin que falta' ) ) $problems[] = 'falta el botón en singular para IA Tools';
} elseif ( false !== strpos( $html, 'notice-error' ) || false !== strpos( $html, 'button-hero' ) ) $problems[] = 'sin fallos no debe haber error ni botón';
if ( false !== strpos( $html, 'DIGITALÍSIMO Hosting' ) || false !== strpos( $html, 'digitalisimo-hosting' ) ) $problems[] = 'la tabla del resumen muestra Hosting';
foreach ( $files as $file ) if ( is_plugin_active( $file ) ) $problems[] = $file . ' quedó activo (sólo se instala)';
if ( $notices ) $problems[] = 'avisos PHP: ' . implode( ' | ', array_slice( $notices, 0, 5 ) );
$result = file_exists( '/wordpress/wp-content/probe/result.json' ) ? (array) json_decode( file_get_contents( '/wordpress/wp-content/probe/result.json' ), true ) : array();
$result[ $scenario ] = $problems;
file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $result ) );
