<?php
/** Verifica que el diagnóstico sólo lea CSS local y agregue fuentes sin inventar datos. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CONTENT_DIR', __DIR__ . '/fixtures/wp-content' );
define( 'KB_IN_BYTES', 1024 );
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url() { return 'https://example.test/'; }
function wp_upload_dir() { return array( 'baseurl' => 'https://example.test/wp-content/uploads', 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function content_url() { return 'https://example.test/wp-content'; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function get_current_blog_id() { return 1; }
function get_home_url( $site_id ) { return 2 === $site_id ? 'https://example.test/subsite/' : 'https://example.test/'; }
function is_multisite() { return true; }
function get_site_by_path( $host, $path ) { if ( 'example.test' !== $host ) return false; return (object) array( 'blog_id' => 0 === strpos( $path, '/subsite/' ) ? 2 : 1 ); }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-asset-diagnostics.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-measurements.php';
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

$resources = $class->getProperty( 'resources' );
$resources->setValue( null, array( 'CSS:fonts' => array( 'type' => 'CSS', 'src' => 'https://example.test/wp-content/fonts.css' ) ) );
$fonts = $class->getMethod( 'inspect_fonts' )->invoke( null );
if ( 1 !== count( $fonts ) || 'Inter' !== $fonts[0]['family'] || array( '400', '700' ) !== array_map( 'strval', $fonts[0]['weights'] ) || array( 'woff2' ) !== $fonts[0]['formats'] || array( 'No declarado' ) !== $fonts[0]['display'] ) throw new RuntimeException( 'Debe agrupar fuentes, pesos, formato y ausencia de font-display: ' . json_encode( $fonts ) );
$metrics = Digitalisimo_Integrations_Performance_Measurements::sanitize_metrics( array( 'before' => array( 'score' => '61', 'lcp' => '7.4' ), 'after' => array( 'score' => '72' ) ) );
if ( 61.0 !== $metrics['before']['score'] || 7.4 !== $metrics['before']['lcp'] || 72.0 !== $metrics['after']['score'] ) throw new RuntimeException( 'La comparativa debe preservar antes y después.' );
try { Digitalisimo_Integrations_Performance_Measurements::sanitize_metrics( array( 'after' => array( 'score' => '101' ) ) ); throw new RuntimeException( 'Debe rechazar una puntuación mayor que 100.' ); } catch ( InvalidArgumentException $expected ) {}
echo "Diagnóstico: CSS local y fuentes verificados.\n";
