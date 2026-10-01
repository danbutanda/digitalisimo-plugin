<?php
/** Verifica que sólo CSS Elementor explícito puede diferirse. */
define( 'ABSPATH', __DIR__ );
$options = array( 'perf_css_defer' => 1, 'perf_css_defer_handles' => "widget-form\nwidget-divider\nwidget-heading", 'perf_caption_normal' => 0 );
$advanced = false;
class Digitalisimo_Integrations_Performance_Manager {
	public static function advanced_allowed() { global $advanced; return $advanced; }
	public static function frontend_safe() { global $advanced; return $advanced; }
}
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { global $options; return $options[ $key ] ?? 0; } }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function wp_styles() { global $styles; return $styles; }
$styles = (object) array( 'registered' => array( 'widget-form' => (object) array( 'extra' => array() ), 'elementor-pro-widget-form' => (object) array( 'extra' => array() ), 'widget-divider' => (object) array( 'extra' => array() ), 'widget-heading' => (object) array( 'extra' => array() ) ) );
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
$class = Digitalisimo_Integrations_Performance_CSS::class;
$tag = "<link rel='stylesheet' id='widget-form-css' href='https://example.test/wp-content/plugins/elementor-pro/assets/css/widget-form.min.css' media='all' />";
$href = 'https://example.test/wp-content/plugins/elementor-pro/assets/css/widget-form.min.css';
if ( $tag !== $class::defer_widget_css( $tag, 'widget-form', $href, 'all' ) ) throw new RuntimeException( 'El modo seguro debe dejar el CSS original.' );
$advanced = true;
$result = $class::defer_widget_css( $tag, 'widget-form', $href, 'all' );
if ( false === strpos( $result, 'media="print"' ) || false === strpos( $result, '<noscript>' . $tag . '</noscript>' ) ) throw new RuntimeException( 'Debe diferir únicamente el CSS elegido y conservar fallback.' );
$prefixed = "<link rel='stylesheet' id='elementor-pro-widget-form-css' href='$href' />";
$prefixed_result = $class::defer_widget_css( $prefixed, 'elementor-pro-widget-form', $href, 'all' );
if ( false === strpos( $prefixed_result, 'media="print"' ) || false === strpos( $prefixed_result, '<noscript>' . $prefixed . '</noscript>' ) ) throw new RuntimeException( 'Debe diferir handles prefijados aunque el tag original no declare media.' );
if ( $tag !== $class::defer_widget_css( $tag, 'widget-heading', str_replace( 'widget-form', 'widget-heading', $href ), 'all' ) ) throw new RuntimeException( 'Un widget crítico no puede diferirse aunque aparezca en la lista.' );
if ( $tag !== $class::defer_widget_css( $tag, 'widget-form', 'https://example.test/wp-content/plugins/otro/assets/css/widget-form.min.css', 'all' ) ) throw new RuntimeException( 'Un plugin ajeno no puede pasar por Elementor.' );
$styles->registered['widget-form']->extra['after'] = array( '.x{}' );
if ( $tag !== $class::defer_widget_css( $tag, 'widget-form', $href, 'all' ) ) throw new RuntimeException( 'CSS con reglas inline asociadas debe conservarse.' );
if ( "widget-form\nwidget-divider" !== $class::sanitize_handles( "widget-form, widget-divider\nwidget-heading\n<script>" ) ) throw new RuntimeException( 'La lista debe rechazar handles críticos y texto ajeno.' );
ob_start(); $class::caption_rule(); $silent = ob_get_clean();
if ( '' !== $silent ) throw new RuntimeException( 'La regla de captions debe estar apagada por defecto.' );
$options['perf_caption_normal'] = 1;
ob_start(); $class::caption_rule(); $caption = ob_get_clean();
if ( false === strpos( $caption, '.elementor-image-carousel-caption' ) ) throw new RuntimeException( 'La regla activada debe aparecer sólo en frontend seguro.' );
if ( 'a > b { color: red; }' !== $class::sanitize_custom_css( 'a > b { color: red; }' ) ) throw new RuntimeException( 'El CSS técnico seguro debe conservar selectores normales.' );
foreach ( array( '</style><script>alert(1)</script>', '@import url(https://example.test/a.css);', '.x{background:url(https://example.test/a.png)}' ) as $unsafe ) if ( '' !== $class::sanitize_custom_css( $unsafe ) ) throw new RuntimeException( 'El CSS técnico no debe aceptar HTML o solicitudes remotas.' );
$options['perf_custom_css'] = 'a > b { color: red; }';
ob_start(); $class::custom_css(); $custom = ob_get_clean();
if ( false === strpos( $custom, $options['perf_custom_css'] ) ) throw new RuntimeException( 'El CSS técnico configurado debe aparecer en frontend seguro.' );
$options['perf_custom_css_paused'] = 1;
ob_start(); $class::custom_css(); $paused = ob_get_clean();
if ( '' !== $paused ) throw new RuntimeException( 'El CSS técnico preparado debe permanecer en pausa hasta aprobación manual.' );
$options['perf_custom_css_paused'] = 0;
$advanced = false;
ob_start(); $class::custom_css(); $admin = ob_get_clean();
if ( '' !== $admin ) throw new RuntimeException( 'El CSS técnico no debe aparecer en admin o preview.' );
echo "CSS: diferido explícito y captions reversibles correctos.\n";
