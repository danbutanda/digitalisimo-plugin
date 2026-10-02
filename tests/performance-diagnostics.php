<?php
/** Verifica que el diagnóstico sólo lea CSS local y agregue fuentes sin inventar datos. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CONTENT_DIR', __DIR__ . '/fixtures/wp-content' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * 1024 );
define( 'MINUTE_IN_SECONDS', 60 );
function apply_filters( $hook, $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url() { return 'https://example.test/'; }
function wp_upload_dir() { return array( 'baseurl' => 'https://example.test/wp-content/uploads', 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function content_url() { return 'https://example.test/wp-content'; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ); }
function get_current_blog_id() { return 1; }
function get_home_url( $site_id ) { return 2 === $site_id ? 'https://example.test/subsite/' : 'https://example.test/'; }
function is_multisite() { return true; }
function get_site_by_path( $host, $path ) { if ( 'example.test' !== $host ) return false; return (object) array( 'blog_id' => 0 === strpos( $path, '/subsite/' ) ? 2 : 1 ); }
function wp_scripts() { return (object) array( 'queue' => array(), 'registered' => array() ); }
function wp_styles() { return (object) array( 'queue' => array(), 'registered' => array() ); }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['site_transients'][ $key ] = $value; return true; }
function wp_redirect( $url ) { $GLOBALS['redirected_to'] = $url; return true; }
function get_option( $key, $default = false ) { return $default; }
class Digitalisimo_Integrations_Performance_Preloads { const OPTION = 'preload_manifest'; }
class Digitalisimo_Integrations_Performance_CSS { public static function configured( $handle, $href ) { return 'elementor-pro-widget-form' === $handle; } }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-asset-diagnostics.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-measurements.php';
$delivery = Digitalisimo_Integrations_Asset_Diagnostics::inspect_delivery(
	'<head><link rel="preload" href="https://example.test/inter.woff2" as="font"><link rel="stylesheet" id="elementor-pro-widget-form-css" href="https://example.test/widget-form.css" media="print" onload="this.media=\'all\'"><link rel="stylesheet" href="https://example.test/wp-content/plugins/elementor/assets/css/frontend.css"></head>',
	array( array( 'type' => 'CSS', 'handle' => 'elementor-pro-widget-form', 'src' => 'https://example.test/widget-form.css' ) ),
	array( 'rows' => array( array( 'url' => 'https://example.test/inter.woff2' ) ) )
);
if ( 'OK' !== $delivery['preloads'][0]['status'] || 'DIFERIDO' !== $delivery['css'][0]['status'] ) throw new RuntimeException( 'La verificación debe reconocer preload temprano y CSS realmente diferido.' );
$normal = Digitalisimo_Integrations_Asset_Diagnostics::inspect_delivery( '<head><link rel="stylesheet" id="elementor-pro-widget-form-css" href="https://example.test/widget-form.css" media="all"></head>', array( array( 'type' => 'CSS', 'handle' => 'elementor-pro-widget-form', 'src' => 'https://example.test/widget-form.css' ) ), array() );
if ( 'ERROR: OPTIMIZACIÓN NO APLICADA' !== $normal['css'][0]['status'] ) throw new RuntimeException( 'Un CSS aún bloqueante debe marcarse como error.' );
function wp_die( $message ) { throw new InvalidArgumentException( $message ); }
$class = new ReflectionClass( Digitalisimo_Integrations_Asset_Diagnostics::class );
$path = $class->getMethod( 'local_css_path' );
$inside = $path->invoke( null, 'https://example.test/wp-content/fonts.css?ver=1' );
if ( realpath( WP_CONTENT_DIR . '/fonts.css' ) !== $inside ) throw new RuntimeException( 'Debe aceptar CSS local del sitio.' );
if ( '' !== $path->invoke( null, 'https://other.test/wp-content/fonts.css' ) ) throw new RuntimeException( 'Debe rechazar un host externo.' );
if ( '' !== $path->invoke( null, 'https://example.test/wp-content/%2e%2e/unsafe.css' ) ) throw new RuntimeException( 'Debe rechazar rutas fuera de wp-content.' );
if ( 1 !== Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( 'https://example.test/page/', false ) ) throw new RuntimeException( 'Debe aceptar una página del sitio actual.' );
if ( 0 !== Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( 'https://example.test/subsite/page/', false ) ) throw new RuntimeException( 'Un administrador de sitio no puede analizar otro sitio de la red.' );
if ( 2 !== Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( 'https://example.test/subsite/page/', true ) ) throw new RuntimeException( 'La administración de red puede seleccionar un subsitio.' );
if ( 0 !== Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( 'https://other.test/page/', true ) ) throw new RuntimeException( 'Debe rechazar dominios ajenos.' );
if ( ! Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( array( 'site_id' => 2, 'url' => 'https://example.test/subsite/page/' ), 2 ) ) throw new RuntimeException( 'Una captura del subsitio debe corresponder a su ID.' );
if ( Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( array( 'site_id' => 2, 'url' => 'https://example.test/subsite/page/' ), 1 ) ) throw new RuntimeException( 'La captura del subsitio no puede mostrarse en el sitio principal aunque comparta host.' );
if ( Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( array( 'site_id' => 1, 'url' => 'https://example.test/subsite/page/' ), 2 ) ) throw new RuntimeException( 'Un ID incorrecto no debe mostrarse aunque coincida la URL.' );
if ( ! Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( array( 'url' => 'https://example.test/subsite/page/' ), 2 ) ) throw new RuntimeException( 'Las capturas anteriores deben validarse por la ruta del sitio.' );
if ( 'Plugin: example-plugin' !== $class->getMethod( 'origin' )->invoke( null, 'https://example.test/wp-content/plugins/example-plugin/app.js' ) ) throw new RuntimeException( 'El diagnóstico debe identificar el plugin propietario del asset.' );
foreach ( array( '<div class="wc-block-cart"></div>', '<div class="widget_shopping_cart"></div>', '<a class="add_to_cart_button"></a>' ) as $markup ) {
	if ( ! $class->getMethod( 'woocommerce_markup' )->invoke( null, $markup ) ) throw new RuntimeException( 'Debe detectar WooCommerce en bloques, widgets o botones de plantillas.' );
}
if ( $class->getMethod( 'woocommerce_markup' )->invoke( null, '<div class="elementor-widget-heading"></div>' ) ) throw new RuntimeException( 'No debe detectar WooCommerce en HTML ajeno.' );

$resources = $class->getProperty( 'resources' );
$resources->setValue( null, array( 'CSS:fonts' => array( 'type' => 'CSS', 'src' => 'https://example.test/wp-content/fonts.css' ) ) );
$fonts = $class->getMethod( 'inspect_fonts' )->invoke( null );
if ( 1 !== count( $fonts ) || 'Inter' !== $fonts[0]['family'] || array( '400', '700' ) !== array_map( 'strval', $fonts[0]['weights'] ) || array( 'woff2' ) !== $fonts[0]['formats'] || array( 'No declarado' ) !== $fonts[0]['display'] ) throw new RuntimeException( 'Debe agrupar fuentes, pesos, formato y ausencia de font-display: ' . json_encode( $fonts ) );
$resources->setValue( null, array() );
$class->getMethod( 'inspect_html_resources' )->invoke( null, '<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><link rel="preload" as="font" href="https://example.test/wp-content/inter.woff2">' );
$detected = $resources->getValue();
if ( 2 !== count( $detected ) || ! in_array( 'Script externo', array_column( $detected, 'type' ), true ) || ! in_array( 'Fuente', array_column( $detected, 'type' ), true ) ) throw new RuntimeException( 'Debe registrar scripts externos y fuentes preloaded presentes en HTML.' );
$images = $class->getMethod( 'inspect_html_images' )->invoke( null, '<img src="https://example.test/a.jpg" width="1024" height="640" srcset="a-640.jpg 640w" sizes="100vw"><img src="https://example.test/b.png">' );
if ( 2 !== count( $images ) || 'Mantener; comprobar tamaño visual en navegador' !== $images[0]['recommendation'] || 'Revisar dimensiones reales del adjunto' !== $images[1]['recommendation'] ) throw new RuntimeException( 'Debe diagnosticar atributos de imagen sin inventar dimensiones.' );
$registry = (object) array( 'registered' => array( 'carousel-handler' => (object) array( 'deps' => array( 'swiper' ) ), 'swiper' => (object) array( 'deps' => array() ), 'unrelated' => (object) array( 'deps' => array() ) ) );
if ( ! $class->getMethod( 'swiper_dependency' )->invoke( null, 'carousel-handler', $registry ) || $class->getMethod( 'swiper_dependency' )->invoke( null, 'unrelated', $registry ) ) throw new RuntimeException( 'Swiper debe detectarse por dependencias transitivas, sin falsos positivos para handles ajenos.' );
$metrics = Digitalisimo_Integrations_Performance_Measurements::sanitize_metrics( array( 'before' => array( 'score' => '61', 'lcp' => '7.4' ), 'after' => array( 'score' => '72' ) ) );
if ( 61.0 !== $metrics['before']['score'] || 7.4 !== $metrics['before']['lcp'] || 72.0 !== $metrics['after']['score'] ) throw new RuntimeException( 'La comparativa debe preservar antes y después.' );
try { Digitalisimo_Integrations_Performance_Measurements::sanitize_metrics( array( 'after' => array( 'score' => '101' ) ) ); throw new RuntimeException( 'Debe rechazar una puntuación mayor que 100.' ); } catch ( InvalidArgumentException $expected ) {}
$class->getProperty( 'probe_context' )->setValue( null, array( 'site_id' => 2, 'user_id' => 7, 'url' => 'https://example.test/subsite/page/', 'network' => true, 'complete_url' => 'https://example.test/wp-admin/admin-post.php?action=complete' ) );
$class->getProperty( 'probe_token' )->setValue( null, 'probe123' );
$class->getProperty( 'collecting' )->setValue( null, true );
$class->getProperty( 'buffer_level' )->setValue( null, ob_get_level() );
ob_start();
echo '<img src="https://example.test/image.jpg" width="200" height="100">';
Digitalisimo_Integrations_Asset_Diagnostics::finish_probe();
$browser_result = $GLOBALS['site_transients']['digitalisimo_perf_browser_result_probe123'] ?? array();
if ( 7 !== ( $browser_result['user_id'] ?? 0 ) || 2 !== ( $browser_result['site_id'] ?? 0 ) || 'https://example.test/wp-admin/admin-post.php?action=complete' !== ( $GLOBALS['redirected_to'] ?? '' ) || 1 !== count( $browser_result['result']['images'] ?? array() ) ) throw new RuntimeException( 'La captura del navegador debe volver al administrador original y conservar usuario y sitio.' );
echo "Diagnóstico: CSS local y fuentes verificados.\n";
