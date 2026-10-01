<?php
/** Comprueba la paridad de los selectores y que la comparativa reutilice la URL elegida. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['test_blog_id'] = 1;
function get_current_blog_id() { return $GLOBALS['test_blog_id']; }
function absint( $value ) { return abs( (int) $value ); }
function get_site( $id ) { return in_array( (int) $id, array( 1, 2 ), true ) ? (object) array( 'blog_id' => (int) $id ) : false; }
function get_home_url( $id, $path = '/' ) { return 2 === (int) $id ? 'https://example.test/subsite/' : 'https://example.test/'; }
function get_site_option( $key, $default = false ) { return $GLOBALS['test_network'][ $key ] ?? $default; }
function get_option( $key, $default = false ) { return $default; }
function is_multisite() { return true; }
function get_transient( $key ) { return $GLOBALS['test_result'] ?? false; }
function get_current_user_id() { return 1; }
function current_user_can( $capability ) { return true; }
function wp_unslash( $value ) { return $value; }
function esc_url_raw( $value ) { return $value; }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function selected( $actual, $expected, $echo = true ) { return (string) $actual === (string) $expected ? 'selected="selected"' : ''; }
function checked( $actual, $expected, $echo = true ) { return (string) $actual === (string) $expected ? 'checked="checked"' : ''; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_query_arg( $key, $value, $url = '' ) {
	if ( is_array( $key ) ) { $url = $value; $args = $key; } else $args = array( $key => $value );
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_nonce_field( $action ) {}
function submit_button( $label, $type = 'primary' ) { echo '<button>' . esc_html( $label ) . '</button>'; }
function switch_to_blog( $id ) { $GLOBALS['test_blog_id'] = (int) $id; }
function restore_current_blog() { $GLOBALS['test_blog_id'] = 1; }
class Digitalisimo_Integrations_Asset_Diagnostics {
	public static function site_for_url( $url, $network ) { return 0 === strpos( $url, 'https://example.test/subsite/' ) ? 2 : 1; }
	public static function capture_belongs_to_site( $result, $site_id ) { return is_array( $result ) && ! empty( $result['url'] ) && self::site_for_url( $result['url'], true ) === $site_id; }
}
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-resolver.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-measurements.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-console.php';
ob_start();
Digitalisimo_Integrations_SEO_Suite::performance_font_network_fields();
Digitalisimo_Integrations_SEO_Suite::performance_preload_network_fields();
Digitalisimo_Integrations_SEO_Suite::performance_tracking_network_fields();
$network_html = ob_get_clean();
foreach ( array( 'perf_font_guard_mode', 'perf_preload_mode', 'perf_tracking_mode' ) as $key ) {
	if ( ! preg_match( '/<select[^>]+id="network_' . $key . '"[^>]*>/', $network_html ) ) throw new RuntimeException( 'El modo ' . $key . ' debe ser un selector en red.' );
}
$GLOBALS['test_network'][ Digitalisimo_Integrations_Settings::OPTION ] = array( 'perf_safe_mode' => 1 );
$field = new ReflectionMethod( Digitalisimo_Integrations_SEO_Suite::class, 'f' );
ob_start();
$field->invoke( null, 'perf_safe_mode', 'Modo seguro', 'checkbox' );
$inherited_html = ob_get_clean();
if ( ! preg_match( '/id="perf_safe_mode"[^>]*checked="checked"[^>]*disabled/', $inherited_html ) || false === strpos( $inherited_html, 'digitalisimo-effective-state is-active' ) || false === strpos( $inherited_html, '>Activo</span>' ) ) throw new RuntimeException( 'Un valor heredado activo debe seguir marcado y mostrar «Activo».' );
$_GET = array( 'site_id' => '2', 'performance_url' => 'https://example.test/subsite/page/' );
ob_start();
Digitalisimo_Integrations_Performance_Measurements::render( true );
$measurement_html = ob_get_clean();
if ( false === strpos( $measurement_html, '<input type="hidden" name="url" value="https://example.test/subsite/page/">' ) || false !== strpos( $measurement_html, 'type="url" name="url"' ) || false === strpos( $measurement_html, '<details>' ) || false === strpos( $measurement_html, 'Puedes llenar sólo las métricas que tengas' ) ) throw new RuntimeException( 'La comparación debe usar la URL analizada y explicar que las métricas son opcionales.' );
$sections = new ReflectionMethod( Digitalisimo_Integrations_Performance_Console::class, 'sections' );
if ( ( $sections->invoke( null )['status'] ?? '' ) !== 'Diagnóstico de assets' || ( $sections->invoke( null )['measurements'] ?? '' ) !== 'PageSpeed manual' ) throw new RuntimeException( 'El diagnóstico y las mediciones manuales deben tener pestañas inequívocas.' );
$page_url = new ReflectionMethod( Digitalisimo_Integrations_Performance_Console::class, 'page_url' );
if ( false === strpos( $page_url->invoke( null, true, 'measurements' ), 'performance_url=https%3A%2F%2Fexample.test%2Fsubsite%2Fpage%2F' ) ) throw new RuntimeException( 'La navegación debe conservar la URL analizada.' );
unset( $_GET['performance_url'] );
$GLOBALS['test_result'] = array( 'url' => 'https://example.test/subsite/page/' );
ob_start();
Digitalisimo_Integrations_Performance_Measurements::render( true );
$measurement_html = ob_get_clean();
if ( false === strpos( $measurement_html, '<input type="hidden" name="url" value="https://example.test/subsite/page/">' ) ) throw new RuntimeException( 'La pestaña debe recuperar la última URL analizada.' );
echo "Interfaz de rendimiento: pestañas, selectores y URL compartida correctos.\n";
