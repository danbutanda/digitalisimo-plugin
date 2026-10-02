<?php
/**
 * Defer seguro de jQuery: árbol de dependencias, estrategia nativa, revisión
 * del HTML final, fallback por página y red de seguridad del navegador.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['settings'] = array( 'perf_js_jquery_mode' => 'defer' );
$GLOBALS['options']  = array();

class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return $GLOBALS['settings'][ $key ] ?? ''; } }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Fake_Scripts {
	public $registered = array();
	public $queue      = array();
	public function add( $handle, $deps = array(), $extra = array() ) { $this->registered[ $handle ] = (object) array( 'deps' => $deps, 'src' => 'jquery' === $handle ? false : "/$handle.js", 'extra' => $extra ); }
	public function get_data( $handle, $key ) { return $this->registered[ $handle ]->extra[ $key ] ?? false; }
	public function add_data( $handle, $key, $value ) { $this->registered[ $handle ]->extra[ $key ] = $value; return true; }
}
function wp_scripts() { return $GLOBALS['scripts']; }
function get_bloginfo( $key ) { return $GLOBALS['wp_version'] ?? '6.6'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function is_feed() { return false; }
function is_embed() { return false; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function wp_unslash( $v ) { return $v; }
function esc_url_raw( $v ) { return $v; }
function current_time() { return '2026-10-02 12:00:00'; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
class Died extends Exception {}
function wp_die() { throw new Died(); }

// Árbol real de la portada de digitalisimo.mx (visitante).
$s = new Fake_Scripts();
$s->add( 'jquery-core' ); $s->add( 'jquery-migrate' ); $s->add( 'jquery', array( 'jquery-core', 'jquery-migrate' ) );
$s->add( 'jquery-ui-core', array( 'jquery' ) ); $s->add( 'jquery-ui-position', array( 'jquery-ui-core' ) );
$s->add( 'elementor-webpack-runtime' ); $s->add( 'elementor-frontend-modules', array( 'elementor-webpack-runtime', 'jquery' ) );
$s->add( 'elementor-frontend', array( 'elementor-frontend-modules', 'jquery-ui-position' ) );
$s->add( 'wp-hooks' ); $s->add( 'wp-i18n', array( 'wp-hooks' ) );
$s->add( 'elementor-pro-frontend', array( 'elementor-frontend-modules', 'wp-i18n' ) );
$s->add( 'pro-elements-handlers', array( 'elementor-frontend' ) );
$s->add( 'smartmenus', array( 'jquery' ) ); $s->add( 'e-sticky', array( 'jquery' ) ); $s->add( 'jquery-numerator', array( 'jquery' ) );
$s->add( 'swiper' ); $s->add( 'hello-theme-frontend' );
$s->add( 'propio-async', array( 'jquery' ), array( 'strategy' => 'async' ) );
$s->queue = array( 'elementor-frontend', 'elementor-pro-frontend', 'pro-elements-handlers', 'smartmenus', 'e-sticky', 'jquery-numerator', 'swiper', 'hello-theme-frontend', 'propio-async' );
$GLOBALS['scripts'] = $s;

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-javascript.php';
$js = Digitalisimo_Integrations_Performance_JavaScript::class;
function check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }

// Árbol: toda la cadena de jQuery y nada más.
$chain = $js::chain( $s );
foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core', 'jquery-ui-position', 'elementor-frontend-modules', 'elementor-frontend', 'elementor-pro-frontend', 'pro-elements-handlers', 'smartmenus', 'e-sticky', 'jquery-numerator' ) as $handle ) check( in_array( $handle, $chain, true ), $handle . ' depende de jQuery y debe estar en la cadena.' );
foreach ( array( 'swiper', 'hello-theme-frontend', 'wp-i18n', 'wp-hooks', 'elementor-webpack-runtime' ) as $handle ) check( ! in_array( $handle, $chain, true ), $handle . ' no depende de jQuery.' );

// Estrategia nativa: defer para la cadena; lo que ya tenía estrategia no se toca.
$js::request_defer();
check( 'defer' === $s->get_data( 'jquery-core', 'strategy' ) && 'defer' === $s->get_data( 'smartmenus', 'strategy' ) && 'defer' === $s->get_data( 'elementor-frontend', 'strategy' ), 'La cadena recibe defer por la API de WordPress.' );
check( 'async' === $s->get_data( 'propio-async', 'strategy' ) && false === $s->get_data( 'swiper', 'strategy' ), 'Nunca async, nunca fuera de la cadena, nunca sobre una estrategia existente.' );
$requested = ( new ReflectionProperty( $js, 'requested' ) )->getValue();
check( ! isset( $requested['propio-async'] ) && isset( $requested['jquery-core'] ), 'Sólo se registra como pedido lo que este experimento cambió.' );

// Sin soporte nativo o desactivado, no se pide nada.
$GLOBALS['wp_version'] = '6.2';
check( ! $js::enabled(), 'Requiere WordPress 6.3 o superior.' );
$GLOBALS['wp_version'] = '6.6';
$GLOBALS['settings']['perf_js_jquery_mode'] = 'off';
check( ! $js::enabled(), 'Desactivada no hace nada.' );
$GLOBALS['settings']['perf_js_jquery_mode'] = 'defer';
check( 'off' === $js::sanitize_mode( 'async' ) && 'defer' === $js::sanitize_mode( 'defer' ), 'Sólo existen desactivada y defer.' );

// HTML como lo imprime WordPress 6.3+ con la cadena diferida.
function tag( $handle, $deferred = true ) { return '<script src="/' . $handle . '.js" id="' . $handle . '-js"' . ( $deferred ? ' defer data-wp-strategy="defer"' : '' ) . '></script>'; }
$head = '<!doctype html><html><head><script>window.dataLayer=window.dataLayer||[];</script>' . tag( 'jquery-core' ) . tag( 'jquery-migrate' ) . tag( 'elementor-webpack-runtime', false ) . tag( 'elementor-frontend-modules' ) . '</head><body>';
$foot = '<script id="elementor-frontend-js-extra">var elementorFrontendConfig={"currency":"$","price":"$ 10"};</script>' . tag( 'jquery-ui-core' ) . tag( 'jquery-ui-position' ) . tag( 'elementor-frontend' ) . tag( 'smartmenus' ) . tag( 'swiper', false ) . tag( 'hello-theme-frontend', false ) . '<script type="application/ld+json">{"x":"jQuery"}</script></body></html>';
$safe = $head . '<p>Contenido</p>' . $foot;
check( $safe === $js::process( $safe, $s, $requested ), 'Una página sin riesgos conserva la cadena diferida.' );
$report = ( new ReflectionProperty( $js, 'report' ) )->getValue();
$rows = array_column( $report['rows'], null, 'handle' );
check( 'Cadena diferida' === $report['page'] && 'SEGURO PARA DEFER' === $rows['jquery-core']['status'] && 'defer' === $rows['jquery-migrate']['effective'] && 'elementor-frontend-modules, jquery-ui-position' === $rows['elementor-frontend']['dependency'], 'El debug muestra jquery-core, jquery-migrate y cada dependiente con su estrategia.' );
check( ! isset( $rows['swiper'] ), 'Swiper no está en la cadena.' );

// Código inline que usa jQuery al leerse: esa página vuelve a la carga normal.
$own   = '<script src="/otro.js" id="otro-js" defer></script>';
$risky = $head . $own . '<script>jQuery(function($){ $(".menu").addClass("x"); });</script>' . $foot;
$out   = $js::process( $risky, $s, $requested );
check( false === strpos( $out, 'id="jquery-core-js" defer' ) && false === strpos( $out, 'id="smartmenus-js" defer' ) && false === strpos( $out, 'data-wp-strategy' ), 'Se retira el defer de toda la cadena.' );
check( false !== strpos( $out, 'id="otro-js" defer' ), 'Un defer que no pidió este experimento se conserva.' );
$report = ( new ReflectionProperty( $js, 'report' ) )->getValue();
check( false !== strpos( $report['snippet'], 'addClass' ), 'El debug muestra el código que impide diferir.' );
check( 'Carga normal en esta página' === $report['page'] && false !== strpos( $report['reason'], 'inline' ) && 'NO DIFERIDO' === array_column( $report['rows'], null, 'handle' )['jquery-core']['status'], 'El debug explica el fallback.' );

// Lo que va antes del primer script diferido ya se ejecutaba sin jQuery: no cuenta.
$before = str_replace( '<head>', '<head><script>if(window.jQuery){}</script>', $safe );
check( $before === $js::process( $before, $s, $requested ), 'Un inline anterior a jQuery no provoca fallback.' );

// Un script bloqueante sin registrar después de jQuery puede usarlo sin declararlo.
$unknown = $head . '<script src="https://cdn.test/viejo.js"></script>' . $foot;
check( false === strpos( $js::process( $unknown, $s, $requested ), 'id="jquery-core-js" defer' ), 'Un script desconocido bloqueante provoca fallback.' );
check( false !== strpos( ( new ReflectionProperty( $js, 'report' ) )->getValue()['reason'], 'viejo.js' ), 'El motivo nombra el script.' );
$async_unknown = $head . '<script async src="https://www.googletagmanager.com/gtag/js?id=G-X"></script><script type="module" src="/m.js"></script>' . $foot;
check( $async_unknown === $js::process( $async_unknown, $s, $requested ), 'Scripts async o módulos no frenan el defer.' );

// Un dependiente de jQuery que WordPress dejó bloqueante (p. ej. con inline «after»).
$s->add_data( 'jquery-numerator', 'after', array( 'x' ) );
$blocking = $head . tag( 'jquery-numerator', false ) . $foot;
check( false === strpos( $js::process( $blocking, $s, $requested ), 'id="jquery-core-js" defer' ), 'Un dependiente bloqueante después de jQuery provoca fallback.' );
check( false !== strpos( ( new ReflectionProperty( $js, 'report' ) )->getValue()['reason'], 'jquery-numerator' ), 'El motivo nombra el dependiente.' );

// El inline «before» que WordPress añade a jQuery UI (caso real de digitalisimo.mx).
$before_tag = '<script id="jquery-ui-core-js-before">' . "\njQuery.uiBackCompat = true;\n//# sourceURL=jquery-ui-core-js-before\n" . '</script>';
$ui = $head . '<p>x</p>' . str_replace( tag( 'jquery-ui-core' ), $before_tag . tag( 'jquery-ui-core' ), $foot );
$out = $js::process( $ui, $s, $requested );
$report = ( new ReflectionProperty( $js, 'report' ) )->getValue();
check( 'Cadena diferida' === $report['page'] && array( 'jquery-ui-core-js-before' ) === $report['converted'], 'El inline «before» de la cadena ya no impide diferir.' );
check( false !== strpos( $out, 'id="jquery-core-js" defer' ) && false === strpos( $out, 'jQuery.uiBackCompat = true;' . "\n//" ), 'jQuery sigue diferido y el inline ya no se ejecuta al leerse.' );
preg_match( '/<script id="jquery-ui-core-js-before" src="data:text\/javascript;base64,([^"]+)" defer><\/script>(<script[^>]*id="jquery-ui-core-js")/', $out, $m );
check( ! empty( $m ) && "\njQuery.uiBackCompat = true;\n//# sourceURL=jquery-ui-core-js-before\n" === base64_decode( $m[1] ), 'Se ejecuta diferido, con el mismo código, justo antes de jQuery UI.' );
check( strpos( $out, 'id="jquery-core-js"' ) < strpos( $out, 'id="jquery-ui-core-js-before"' ), 'Y después de jQuery en el orden de documento.' );
// Con una CSP que no admite data:, no se convierte: carga normal, inline intacto.
$csp = str_replace( '<head>', '<head><meta http-equiv="Content-Security-Policy" content="script-src \'self\' https://www.googletagmanager.com">', $ui );
$out = $js::process( $csp, $s, $requested );
check( false !== strpos( $out, $before_tag ) && false === strpos( $out, 'id="jquery-core-js" defer' ), 'Sin data: en la CSP, la página vuelve a carga normal con el inline original.' );
check( $js::csp_allows_data_scripts( '', array( "Content-Security-Policy: default-src 'self'; script-src 'self' data:" ) ) && ! $js::csp_allows_data_scripts( '', array( "Content-Security-Policy: default-src 'self'" ) ) && $js::csp_allows_data_scripts( '', array( "Content-Security-Policy: img-src 'self'", "Content-Security-Policy-Report-Only: script-src 'self'" ) ), 'La CSP se interpreta por script-src, luego default-src; el modo informe no bloquea.' );
// Si otra cosa obliga a volver a carga normal, el inline vuelve a ser inline.
$mixed = str_replace( '<p>x</p>', '<p>x</p><script>jQuery(".a").hide();</script>', $ui );
$out = $js::process( $mixed, $s, $requested );
check( false !== strpos( $out, $before_tag ) && false === strpos( $out, 'base64' ) && false === strpos( $out, 'id="jquery-core-js" defer' ), 'En el fallback se restaura el inline original.' );

// Detección de uso de jQuery en código inline.
foreach ( array( 'jQuery(document)' => true, '$.ajax({})' => true, '$( ".a" )' => true, 'var t = `${x}`;' => false, 'foo$(1)' => false, 'a.$b()' => false, 'var p = "$ 10";' => false ) as $code => $expected ) check( $expected === $js::inline_uses_jquery( $code ), 'Detección de jQuery en: ' . $code );

// strip_defer quita exactamente el defer y la marca de WordPress.
$tags = $js::scripts_in( tag( 'smartmenus' ) );
check( '<script src="/smartmenus.js" id="smartmenus-js"></script>' === $js::strip_defer( tag( 'smartmenus' ), $tags, array( 'smartmenus' => true ) ), 'El script vuelve a su forma original.' );

// Red de seguridad del navegador: sólo un error de dependencia apaga el experimento.
try { $_POST = array( 'message' => 'Uncaught TypeError: x is undefined' ); $js::record_failure(); } catch ( Died $e ) {}
check( ! $js::fallback() && $js::enabled(), 'Un error ajeno a jQuery no apaga el experimento.' );
try { $_POST = array( 'message' => "Can't find variable: jQuery", 'url' => 'https://site.test/' ); $js::record_failure(); } catch ( Died $e ) {}
check( "Can't find variable: jQuery" === $js::fallback()['message'] && ! $js::enabled(), 'Un «jQuery no definido» devuelve el sitio a la carga normal.' );
$s->registered['smartmenus']->extra = array();
$GLOBALS['options'] = array();
foreach ( array( 'jQuery is not defined', '$ is not defined', 'jQuery is not a function' ) as $message ) check( $js::is_dependency_error( $message ), 'Mensaje reconocido: ' . $message );

echo "JavaScript: cadena jQuery, defer nativo, fallback por página y red de seguridad correctos.\n";
