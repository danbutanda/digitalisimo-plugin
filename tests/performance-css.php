<?php
/** CSS no crítico: sin evidencia no hay diferimiento, incluso si antes estaba en la lista manual. */
define( 'ABSPATH', __DIR__ );
define( 'WP_CONTENT_DIR', realpath( __DIR__ . '/../digitalisimo-seo' ) );
$options = array( 'perf_css_defer' => 1, 'perf_css_defer_exclusions' => '', 'perf_css_defer_handles' => 'widget-form', 'perf_caption_normal' => 0 );
$cache = array(); $blog_id = 1; $dependent = false; $frontend = true;
class Digitalisimo_Integrations_Performance_Manager {
	public static function frontend_safe() { global $frontend; return $frontend; }
	public static function has_queued_dependent( $styles, $handle ) { global $dependent; return $dependent; }
}
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { global $options; return $options[ $key ] ?? 0; } }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function wp_styles() { global $styles; return $styles; }
function is_singular() { return true; }
function is_user_logged_in() { return false; }
function get_queried_object_id() { return 42; }
function get_post( $id ) { return (object) array( 'post_status' => 'publish', 'post_content' => '' ); }
function get_post_meta( $id, $key, $single ) { return ''; }
function get_permalink( $id ) { return 'https://example.test/page/'; }
function get_current_blog_id() { global $blog_id; return $blog_id; }
function get_option( $key, $default = null ) { global $cache; return $cache ?: $default; }
function home_url() { return 'https://example.test/'; }
function doing_action( $name ) { return 'wp_head' === $name; }
function content_url() { return 'https://example.test/wp-content'; }
function esc_url_raw( $url ) { return $url; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function check_ajax_referer( $action, $field, $die ) { return true; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function wp_get_referer() { return 'https://example.test/page/'; }
function url_to_postid( $url ) { return 42; }
function update_option( $key, $value, $autoload = null ) { global $cache; $cache = $value; }
function wp_send_json_error( $message, $code = null ) { throw new RuntimeException( 'error:' . $message ); }
function wp_send_json_success() { throw new RuntimeException( 'success' ); }
$styles = (object) array( 'registered' => array( 'elementor-pro-widget-form' => (object) array( 'extra' => array() ) ) );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css-audit.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
$audit = Digitalisimo_Integrations_Performance_CSS_Audit::class;
$css = Digitalisimo_Integrations_Performance_CSS::class;
$handle = 'elementor-pro-widget-form';
$href = 'https://example.test/wp-content/assets/snippet-editor.css?ver=1';
$tag = '<link rel="stylesheet" href="' . $href . '" media="all" />';
$result = $css::defer_widget_css( $tag, $handle, $href, 'all' );
if ( false !== strpos( $result, 'media="print"' ) || false === strpos( $result, 'data-digitalisimo-css-handle' ) ) throw new RuntimeException( 'Sin auditoría sólo se marca la hoja y queda bloqueante.' );
$_COOKIE['digitalisimo_css_view'] = '390.844';
$page = hash( 'sha256', '1|https://example.test/page/' );
$signature = $audit::resource_signature( $href );
if ( ! $signature ) throw new RuntimeException( 'Debe reconocer el recurso CSS local.' );
$cache = array( 'schema' => 1, 'blog_id' => 1, 'pages' => array( $page => array( '390.844' => array( $handle => array( 'href' => $href, 'signature' => $signature, 'safe' => 1, 'passes' => 1, 'time' => time() ) ) ) ) );
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'Una sola medición no basta.' );
$cache['pages'][ $page ]['390.844'][ $handle ]['passes'] = 2;
if ( 'Cookie' !== $audit::vary_cookie( array() )['Vary'] ) throw new RuntimeException( 'La caché HTTP debe variar por cookie de viewport.' );
$result = $css::defer_widget_css( $tag, $handle, $href, 'all' );
if ( false === strpos( $result, 'media="print"' ) || false === strpos( $result, 'onload=' ) || false === strpos( $result, '<noscript>' . $tag . '</noscript>' ) ) throw new RuntimeException( 'La hoja aprobada debe diferirse con fallback completo.' );
$dependent = true;
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'Un dependiente encolado impide diferir.' );
$dependent = false;
$options['perf_css_defer_exclusions'] = $handle;
if ( $tag !== $css::defer_widget_css( $tag, $handle, $href, 'all' ) ) throw new RuntimeException( 'La exclusión manual conserva el enlace original.' );
$options['perf_css_defer_exclusions'] = '';
$cache['pages'][ $page ]['390.844'][ $handle ]['href'] = 'https://example.test/otro.css';
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'Una URL de recurso cambiada invalida el resultado.' );
$cache['pages'][ $page ]['390.844'][ $handle ]['href'] = $href;
$cache['pages'][ $page ]['390.844'][ $handle ]['signature'] = 'archivo-antiguo';
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'Un archivo CSS modificado invalida la medición.' );
$cache['pages'][ $page ]['390.844'][ $handle ]['signature'] = $signature;
$cache['pages'][ $page ]['390.844'][ $handle ]['time'] = time() - 3601;
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'La medición vencida no autoriza diferir.' );
$cache['pages'][ $page ]['390.844'][ $handle ]['time'] = time();
$blog_id = 2;
if ( false !== strpos( $css::defer_widget_css( $tag, $handle, $href, 'all' ), 'media="print"' ) ) throw new RuntimeException( 'El caché de un sitio no debe utilizarse en otro.' );
if ( '' !== $audit::view( '390.99' ) || '390.844' !== $audit::view( '390.844' ) ) throw new RuntimeException( 'Viewport inválido.' );
if ( "elementor-pro-widget-form\nmy-theme" !== $css::sanitize_exclusions( "elementor-pro-widget-form, my-theme\n<script>" ) ) throw new RuntimeException( 'Exclusiones mal saneadas.' );
$blog_id = 1; $cache = array();
$_POST = array( 'page' => $page, 'view' => '390.844', 'rows' => json_encode( array( array( 'handle' => $handle, 'href' => $href, 'safe' => 1 ) ) ) );
try { $audit::receive(); } catch ( RuntimeException $error ) { if ( 'success' !== $error->getMessage() ) throw $error; }
if ( 1 !== $cache['pages'][ $page ]['390.844'][ $handle ]['passes'] ) throw new RuntimeException( 'La primera visita no debe autorizar diferimiento.' );
try { $audit::receive(); } catch ( RuntimeException $error ) { if ( 'success' !== $error->getMessage() ) throw $error; }
if ( 2 !== $cache['pages'][ $page ]['390.844'][ $handle ]['passes'] ) throw new RuntimeException( 'Dos visitas seguras deben habilitar la hoja.' );
$_POST['page'] = str_repeat( 'a', 64 );
try { $audit::receive(); throw new RuntimeException( 'Se aceptó un reporte para otra página.' ); } catch ( RuntimeException $error ) { if ( 'error:Origen inválido.' !== $error->getMessage() ) throw $error; }
echo "CSS: medición, dependencia, URL, caducidad, exclusiones y Multisite correctos.\n";
