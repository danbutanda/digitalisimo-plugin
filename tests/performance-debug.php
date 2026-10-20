<?php
/** Categorías de diagnóstico sin modificar el frontend ni exponer datos privados. */
define( 'ABSPATH', __DIR__ );
class Digitalisimo_Integrations_Performance_Font_Guard { public static function allows_face( $face ) { return ! in_array( $face['family'] ?? '', array( 'Denied', 'Pending' ), true ); } public static function icon_family( $family ) { return 'Font Awesome 5 Free' === $family; } public static function ready_for_face( $face ) { return 'Denied' === ( $face['family'] ?? '' ); } }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-debug.php';
$debug = Digitalisimo_Integrations_Performance_Debug::class;
if ( 'ICON FONT' !== $debug::font_label( array( 'family' => 'Font Awesome 5 Free' ), array() ) || 'BLOQUEADO' !== $debug::font_label( array( 'family' => 'Denied' ), array() ) || 'PERMITIDO' !== $debug::font_label( array( 'family' => 'Inter' ), array() ) ) throw new RuntimeException( 'Las familias deben mostrar su estado sin bloquear iconos.' );
if ( 'POLÍTICA PENDIENTE' !== $debug::font_label( array( 'family' => 'Pending' ), array() ) ) throw new RuntimeException( 'Una copia CSS vencida no debe figurar como bloqueo efectivo.' );
if ( 'CRÍTICO' !== $debug::css_label( array( 'handle' => 'widget-heading', 'src' => '/plugins/elementor/assets/css/widget-heading.min.css' ), true, false, array( 'widget-heading' ) ) ) throw new RuntimeException( 'CSS crítico no se marca como diferido.' );
if ( 'DIFERIDO CONFIGURADO' !== $debug::css_label( array( 'handle' => 'widget-form', 'src' => '/plugins/elementor-pro/assets/css/widget-form.min.css' ), true, false, array( 'widget-form' ) ) ) throw new RuntimeException( 'El CSS candidato debe identificarse.' );
if ( 'NORMAL' !== $debug::css_label( array( 'handle' => 'widget-form', 'src' => '/plugins/elementor-pro/assets/css/widget-form.min.css' ), true, true, array( 'widget-form' ) ) ) throw new RuntimeException( 'Modo seguro debe conservar el CSS normal.' );
// Núcleo incluido: las rutas salen de las constantes del constructor en uso.
$bundled_form = array( 'handle' => 'widget-form', 'src' => 'https://example.test/wp-content/plugins/digitalisimo-elements/assets/css/widget-form.min.css' );
$bundled_front = array( 'handle' => 'frontend-x', 'src' => 'https://example.test/wp-content/plugins/digitalisimo-elements/elementor/assets/css/frontend.min.css' );
if ( 'NORMAL' !== $debug::css_label( $bundled_form, true, false, array( 'widget-form' ) ) ) throw new RuntimeException( 'Sin constantes no cambia la clasificación.' );
define( 'ELEMENTOR_ASSETS_URL', 'https://example.test/wp-content/plugins/digitalisimo-elements/elementor/assets/' );
define( 'ELEMENTOR_PRO_ASSETS_URL', 'https://example.test/wp-content/plugins/digitalisimo-elements/assets/' );
if ( 'DIFERIDO CONFIGURADO' !== $debug::css_label( $bundled_form, true, false, array( 'widget-form' ) ) ) throw new RuntimeException( 'El CSS de un widget del núcleo incluido debe identificarse como diferido configurado.' );
if ( 'NORMAL' !== $debug::css_label( $bundled_form, true, true, array( 'widget-form' ) ) ) throw new RuntimeException( 'Modo seguro conserva también el CSS del núcleo incluido.' );
if ( 'CRÍTICO' !== $debug::css_label( $bundled_front, true, false, array() ) || 'CRÍTICO' !== $debug::css_label( array( 'handle' => 'frontend-y', 'src' => 'https://example.test/wp-content/plugins/digitalisimo-elements/assets/css/frontend.min.css' ), true, false, array() ) ) throw new RuntimeException( 'El frontend del núcleo incluido es crítico.' );
if ( 'NORMAL' !== $debug::css_label( array( 'handle' => 'widget-form', 'src' => 'https://example.test/wp-content/plugins/otro-plugin/assets/css/widget-form.min.css' ), true, false, array( 'widget-form' ) ) ) throw new RuntimeException( 'La hoja homónima de otro plugin no es del constructor.' );
echo "Debug: iconos, fuentes y CSS crítico/diferido clasificados, también con el núcleo incluido.\n";
