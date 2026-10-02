<?php
/**
 * Auditoría de calidad: saneado de lo que envía el navegador, evaluación,
 * almacenamiento por sitio y URL, versión de configuración, inyección del
 * colector, protección táctil y clasificación decorativa.
 */
define( 'ABSPATH', __DIR__ );
define( 'DIGITALISIMO_INTEGRATIONS_VERSION', '9.9.9' );
define( 'DIGITALISIMO_INTEGRATIONS_URL', 'https://example.test/wp-content/plugins/digitalisimo-seo/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['blog'] = 1;
$GLOBALS['settings'] = array( 1 => array(), 2 => array() );
$GLOBALS['options'] = array();
$GLOBALS['site_transients'] = array();

class Digitalisimo_Integrations_Settings { const OPTION = 'seo'; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $GLOBALS['blog'] ][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_Performance_Cache {
	public static $store = array();
	public static function get( $name, &$found = null ) { $key = $GLOBALS['blog'] . ':' . $name; $found = array_key_exists( $key, self::$store ); return $found ? self::$store[ $key ] : false; }
	public static function set( $name, $value ) { self::$store[ $GLOBALS['blog'] . ':' . $name ] = $value; return true; }
	public static function delete( $name ) { unset( self::$store[ $GLOBALS['blog'] . ':' . $name ] ); return true; }
}
class Digitalisimo_Integrations_Performance_Images { public static function resolve_attachment( $attrs ) { return preg_match( '/wp-image-(\d+)/', (string) ( $attrs['class'] ?? '' ), $m ) ? (int) $m[1] : 0; } }
function get_current_blog_id() { return $GLOBALS['blog']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $GLOBALS['blog'] ][ $key ] ); return true; }
function set_site_transient( $key, $value ) { $GLOBALS['site_transients'][ $key ] = $value; return true; }
function get_site_transient( $key ) { return $GLOBALS['site_transients'][ $key ] ?? false; }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return (string) $v; }
function admin_url( $path = '' ) { return 'https://site' . $GLOBALS['blog'] . '.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function apply_filters( $hook, $value ) { return 'digitalisimo_performance_probe_image_report' === $hook ? array( array( 'src' => 'a.jpg', 'status' => 'RESPONSIVE' ) ) : $value; }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-quality-rules.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-quality-audit.php';
$q = Digitalisimo_Integrations_Quality_Audit::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }

// Selectores táctiles: sólo selectores simples; nada que cierre la regla o inyecte HTML.
check( ".elementor-social-icon\n.swiper-pagination-bullet\nnav > a" === $q::sanitize_selectors( ".elementor-social-icon\n.swiper-pagination-bullet, nav  >  a\n}body{display:none\n<script>\n.x{color:red}\n#\n" ), 'Selectores peligrosos o vacíos se descartan.' );
$css = $q::touch_rules( ".a\n.b" );
check( 0 === strpos( $css, ':where(.a,.b){position:relative}' ) && false !== strpos( $css, 'width:max(100%,24px)' ) && false !== strpos( $css, 'transform:translate(-50%,-50%)' ), 'El área táctil se agranda con especificidad cero y sin mover el control.' );
check( '' === $q::touch_rules( '' ), 'Sin selectores no se imprime CSS.' );

// Clasificación decorativa: alt="" sin tocar el resto de la etiqueta.
check( '<img src="a.jpg" class="x" alt="">' === $q::decorate( '<img src="a.jpg" alt="Foto del equipo" class="x">' ), 'alt descriptivo reemplazado por alt="".' );
check( '<img src="a.jpg" alt=""/>' === $q::decorate( '<img src="a.jpg" />' ), 'Sin alt se añade alt="" respetando el cierre.' );
$q::set_role( 12, 'decorative' );
$q::set_role( 13, 'informative' );
$q::set_role( 14, 'algo' );
check( array( 12 => 'decorative', 13 => 'informative' ) === $q::roles() && $q::has_decorative(), 'Sólo se guardan roles válidos.' );
check( array( 'alt' => '' ) === $q::attachment_attributes( array( 'alt' => 'Texto' ), (object) array( 'ID' => 12 ) ) && array( 'alt' => 'Texto' ) === $q::attachment_attributes( array( 'alt' => 'Texto' ), (object) array( 'ID' => 13 ) ), 'wp_get_attachment_image publica alt="" sólo en decorativas.' );
$GLOBALS['blog'] = 2;
check( ! $q::has_decorative() && '' === $q::role( 12 ), 'La clasificación pertenece a su sitio.' );
$GLOBALS['blog'] = 1;

// Saneado: tipos, límites y estructuras ajenas descartadas.
$payload = $q::sanitize_payload( array(
	'desktop' => array(
		'viewport' => array( 'w' => '1440', 'h' => 900, 'dpr' => 2 ),
		'links' => array_merge( array( array( 'href' => null, 'resolved' => '', 'name' => "Menú\x01<b>", 'visible' => 'true', 'evil' => 'x' ) ), array_fill( 0, 700, array( 'href' => '/a/', 'resolved' => 'https://site1.test/a/', 'name' => 'A', 'visible' => true ) ) ),
		'headings' => array( array( 'level' => '1', 'text' => 'Inicio', 'visible' => true ), 'basura' ),
		'images' => array( array( 'src' => 'https://site1.test/wp-content/uploads/a.jpg', 'cls' => 'wp-image-12', 'alt' => 'Foto', 'natural' => array( 1920, 1080 ), 'rect' => array( 400, 225 ), 'srcset' => '768w, 1024w, 1920w', 'width' => '1920', 'height' => '1080', 'visible' => true ), array( 'src' => 'b.jpg', 'cls' => 'wp-image-13', 'alt' => null, 'visible' => true, 'rect' => array( 50, 50 ), 'natural' => array( 50, 50 ) ) ),
		'targets' => array( array( 'x' => 0, 'y' => 0, 'w' => 8, 'h' => 8 ), array( 'x' => 10, 'y' => 0, 'w' => 8, 'h' => 8 ) ),
		'texts' => array( array( 'fg' => 'rgb(170, 170, 170)', 'bg' => 'rgb(255, 255, 255)', 'size' => 16, 'weight' => 400, 'text' => 'claro' ) ),
		'css' => array( 'form' => array( 'present' => true, 'above' => false ), 'Bad Name!' => array( 'present' => true ) ),
	),
	'mobile' => array( 'error' => 'iframe bloqueado' ),
) );
$desktop = $payload['desktop'];
check( 2.0 === $desktop['viewport']['dpr'] && 1440 === $desktop['viewport']['w'], 'El viewport se convierte a números.' );
check( 5.0 === $q::sanitize_payload( array( 'desktop' => array( 'viewport' => array( 'dpr' => 99 ) ) ) )['desktop']['viewport']['dpr'], 'La DPR se acota.' );
check( 600 === count( $desktop['links'] ) && null === $desktop['links'][0]['href'] && ! isset( $desktop['links'][0]['evil'] ) && false === strpos( $desktop['links'][0]['name'], "\x01" ), 'Enlaces acotados, sin claves ajenas ni caracteres de control; href ausente sigue siendo null.' );
check( 1 === count( $desktop['headings'] ) && 1 === $desktop['headings'][0]['level'], 'Filas que no son objetos se descartan.' );
check( null === $desktop['images'][1]['alt'] && 'Foto' === $desktop['images'][0]['alt'], 'alt ausente (null) se distingue de alt vacío.' );
check( array( 'form' ) === array_keys( $desktop['css'] ), 'Nombres de widget inválidos se descartan.' );
check( array( 'error' => 'iframe bloqueado' ) === $payload['mobile'], 'El error de la vista móvil se conserva.' );

// Evaluación: hallazgos, versión de configuración y captura del servidor.
$GLOBALS['settings'][1] = array( 'perf_css_defer' => 1, 'perf_css_defer_handles' => "widget-form" );
$server = array( 'styles' => array( 'widget-form' => 'https://site1.test/wp-content/plugins/elementor/assets/css/widget-form.min.css', 'hello-theme' => 'https://site1.test/wp-content/themes/hello/style.css' ), 'widgets' => array(), 'image_report' => array( array( 'src' => 'a.jpg' ) ) );
$record = $q::build( 'https://site1.test/servicios/', $payload, $server );
$f = $record['findings'];
check( 'ERROR' === $f['links'][0]['status'] && '(ausente)' === $f['links'][0]['href'], 'El enlace sin href llega como error.' );
check( 2 === count( $f['alt'] ) && 12 === $f['alt'][0]['attachment_id'] && 'decorative' === $f['alt'][0]['classification'] && 'ADVERTENCIA' === $f['alt'][0]['status'], 'Una decorativa que aún publica alt se advierte, con su adjunto.' );
check( 13 === $f['alt'][1]['attachment_id'] && 'informative' === $f['alt'][1]['classification'] && 'ERROR' === $f['alt'][1]['status'], 'Una imagen sin alt es un error y conserva su clasificación.' );
check( 2 === count( $f['touch'] ) && 'Escritorio' === $f['touch'][0]['viewport'], 'Sin vista móvil los objetivos se miden en escritorio y se indica.' );
check( 1 === count( $f['contrast'] ) && 'claro' === $f['contrast'][0]['text'], 'Contraste insuficiente detectado.' );
check( 'ADVERTENCIA' === $f['images'][0]['status'] && 1024 === $f['images'][0]['candidate'] && 'Escritorio' === $f['images'][0]['viewport'], 'Imagen de 1920 px pintada a 400 px × DPR 2: bastaría la de 1024.' );
check( 'ADVERTENCIA' === $f['images'][1]['status'] && false !== strpos( $f['images'][1]['issue'], 'width/height' ), 'Una imagen sin width/height se advierte.' );
$by_handle = array_column( $f['css'], null, 'handle' );
check( $by_handle['widget-form']['eligible'] && ! $by_handle['widget-form']['selected'] && 'NORMAL' === $by_handle['widget-form']['state'] && $by_handle['widget-form']['present'], 'La lista manual heredada no debe afirmar que la hoja está diferida.' );
check( ! $by_handle['hello-theme']['eligible'] && 'NO APLICABLE' === $by_handle['hello-theme']['status'], 'Una hoja del tema no es apta para diferir.' );
check( ! $record['mobile']['ok'] && 'iframe bloqueado' === $record['mobile']['error'] && 1 === $record['blog_id'], 'El registro guarda el sitio y el estado móvil.' );
check( array( 'H1 Inicio' ) === $record['tree']['desktop'], 'El árbol de encabezados se guarda.' );
check( array( array( 'src' => 'a.jpg' ) ) === $record['image_report'], 'El reporte de imágenes del servidor acompaña al registro.' );

// Versión de configuración: cambia con los ajustes que afectan lo medido.
$version = $q::config_version();
$GLOBALS['settings'][1]['perf_img_enabled'] = 1;
check( $version !== $q::config_version(), 'Cambiar un ajuste medido cambia la versión.' );
$GLOBALS['settings'][1]['perf_unrelated'] = 1;
$version = $q::config_version();
$GLOBALS['settings'][1]['perf_unrelated'] = 2;
check( $version === $q::config_version(), 'Un ajuste ajeno no la cambia.' );

// Almacenamiento: por sitio y URL, acotado, con caché propia.
$q::save( $record );
check( $q::get( 'https://site1.test/servicios/' )['url'] === 'https://site1.test/servicios/', 'El registro se recupera por URL.' );
$GLOBALS['blog'] = 2;
check( null === $q::get( 'https://site1.test/servicios/' ) && ! $q::index(), 'Otro sitio no ve los resultados ni el índice.' );
$GLOBALS['blog'] = 1;
for ( $i = 0; $i < 12; $i++ ) $q::save( array( 'url' => 'https://site1.test/p' . $i . '/', 'time' => time() + 10 + $i ) + $record );
$index = $q::index();
check( 10 === count( $index ) && 'https://site1.test/p11/' === reset( $index )['url'], 'Se conservan las 10 auditorías más recientes, la última primero.' );
check( false === get_option( 'digitalisimo_quality_audit_' . md5( 'https://site1.test/p0/' ) ), 'Los registros que salen del índice se borran.' );
check( ! isset( $q::get( 'https://site1.test/p11/' )['marker'] ), 'Lectura previa en caché.' );
$q::save( array( 'url' => 'https://site1.test/p11/', 'time' => time() + 100, 'marker' => 'nuevo' ) + $record );
check( 'nuevo' === $q::get( 'https://site1.test/p11/' )['marker'], 'Guardar invalida la caché de esa URL.' );

// Inyección del colector antes del último </body>, con su configuración en un atributo.
$set = function( $name, $value ) use ( $q ) { $p = new ReflectionProperty( $q, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( null, $value ); };
$set( 'collecting', true );
$set( 'token', 'abc' );
$set( 'probe', array( 'url' => 'https://site1.test/servicios/', 'complete_url' => 'https://site1.test/wp-admin/admin-post.php?action=digitalisimo_quality_complete&token=abc' ) );
$set( 'styles', array( 'elementor-widget-form' => 'x.css', 'hello' => 'y.css' ) );
$html = $q::inject( '<html><body><p>Hola</p></body></html>' );
check( (bool) preg_match( '~<p>Hola</p><script id="digitalisimo-quality-audit" src="[^"]+quality-audit\.js\?ver=9\.9\.9" data-config="([^"]+)"></script></body>~', $html, $m ), 'El script se inserta antes de </body>.' );
$config = json_decode( base64_decode( html_entity_decode( $m[1] ) ), true );
check( array( 'form' ) === $config['widgets'] && 'abc' === $config['token'] && false !== strpos( $config['frame'], 'digitalisimo_quality_frame=1' ) && 'https://site1.test/wp-admin/admin-ajax.php' === $config['endpoint'], 'La configuración lleva widgets con hoja propia, token, vista móvil y AJAX del sitio auditado.' );
check( array( array( 'src' => 'a.jpg', 'status' => 'RESPONSIVE' ) ) === get_site_transient( 'digitalisimo_quality_server_abc' )['image_report'], 'La captura del servidor queda asociada al token.' );
check( '<p>sin cierre</p>' === $q::inject( '<p>sin cierre</p>' ), 'Una respuesta sin </body> no se toca.' );
$set( 'collecting', false );
check( '<html><body></body></html>' === $q::inject( '<html><body></body></html>' ), 'Fuera de una auditoría no se inyecta nada.' );

echo "Auditoría: saneado, evaluación, aislamiento por sitio, versión, inyección y clasificación correctos.\n";
