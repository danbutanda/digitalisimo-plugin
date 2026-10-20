<?php
/**
 * SEO reconoce las hojas del constructor también con el núcleo incluido en DIGITALÍSIMO Elements: las rutas salen
 * de las constantes que define el núcleo en uso, no del nombre de una carpeta. Sin esas constantes, todo sigue como
 * antes (sólo las dos carpetas históricas). Las constantes de PHP no se redefinen: el escenario con constantes usa
 * una carpeta renombrada; las pruebas performance-css, performance-debug y performance-diagnostics cubren la habitual.
 */
define( 'ABSPATH', __DIR__ );
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { return 0; } }
class Digitalisimo_Integrations_Performance_CSS_Audit { public static function allowed( $handle, $href ) { return false; } }
class Digitalisimo_Integrations_Performance_Manager { public static function plain_elementor_page() { return false; } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-debug.php';
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-asset-diagnostics.php';

function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$css   = Digitalisimo_Integrations_Performance_CSS::class;
$debug = Digitalisimo_Integrations_Performance_Debug::class;
$diagnostics = new ReflectionClass( Digitalisimo_Integrations_Asset_Diagnostics::class );
$origin = $diagnostics->getMethod( 'origin' );
$status = $diagnostics->getMethod( 'status' );
if ( PHP_VERSION_ID < 80100 ) { $origin->setAccessible( true ); $status->setAccessible( true ); }
$preload = function( $head ) {
	$delivery = Digitalisimo_Integrations_Asset_Diagnostics::inspect_delivery( $head, array(), array( 'rows' => array( array( 'url' => 'https://example.test/inter.woff2' ) ) ) );
	return $delivery['preloads'][0];
};
$font = '<link rel="preload" href="https://example.test/inter.woff2" as="font">';

$folder   = 'https://example.test/wp-content/plugins/digitalisimo-elements-4.3.0.157/';
$core     = $folder . 'elementor/assets/';
$pro      = $folder . 'assets/';
$historic = 'https://example.test/wp-content/plugins/elementor/assets/';
$historic_pro = 'https://example.test/wp-content/plugins/elementor-pro/assets/';

// 1. Sin constantes: sólo las carpetas históricas, como hasta ahora.
check( array( '/plugins/elementor/assets/', '/plugins/elementor-pro/assets/' ) === $css::builder_asset_paths(), 'Sin constantes sólo quedan los dos prefijos históricos.' );
check( $css::eligible( 'widget-heading', $historic . 'css/widget-heading.min.css' ) && $css::eligible( 'elementor-pro-widget-form', $historic_pro . 'css/widget-form.css' ), 'Las hojas de widgets del constructor aparte siguen siendo aptas.' );
check( ! $css::eligible( 'widget-heading', $core . 'css/widget-heading.min.css' ), 'Sin constantes, una carpeta que no es histórica no se reconoce.' );
check( 'NORMAL' === $debug::css_label( array( 'handle' => 'widget-form', 'src' => $pro . 'css/widget-form.min.css' ), true, false, array( 'widget-form' ) ), 'Sin constantes, la depuración no cambia.' );
check( 'DIFERIDO CONFIGURADO' === $debug::css_label( array( 'handle' => 'widget-form', 'src' => $historic_pro . 'css/widget-form.min.css' ), true, false, array( 'widget-form' ) ), 'La depuración sigue reconociendo la carpeta histórica.' );
check( 'Constructor externo' === $origin->invoke( null, $historic . 'js/frontend.min.js' ) && 'DIGITALÍSIMO' === $origin->invoke( null, $core . 'js/frontend.min.js' ), 'Sin constantes, el origen no cambia.' );
check( 'Constructor' === $status->invoke( null, 'otro', $historic . 'css/x.css', 'CSS' ) && 'Desconocido' === $status->invoke( null, 'otro', $core . 'css/x.css', 'CSS' ), 'Sin constantes, el estado no cambia.' );
check( 'DESPUÉS del CSS del constructor' === $preload( '<head><link rel="stylesheet" href="' . $historic . 'css/frontend.min.css">' . $font . '</head>' )['position'], 'El CSS histórico sigue marcando la posición de los preloads.' );

// 2. Núcleo incluido en una carpeta renombrada: las constantes del núcleo en uso mandan.
define( 'ELEMENTOR_ASSETS_URL', $core );
define( 'ELEMENTOR_PRO_ASSETS_URL', $pro );
$paths = $css::builder_asset_paths();
check( array( '/wp-content/plugins/digitalisimo-elements-4.3.0.157/elementor/assets/', '/wp-content/plugins/digitalisimo-elements-4.3.0.157/assets/', '/plugins/elementor/assets/', '/plugins/elementor-pro/assets/' ) === $paths, 'Los prefijos salen de las constantes, más los dos históricos: ' . implode( ', ', $paths ) );

// eligible(): sólo la hoja del widget nombrado, dentro del constructor.
check( $css::eligible( 'widget-heading', $core . 'css/widget-heading.min.css' ), 'Una hoja de widget del núcleo incluido es apta.' );
check( $css::eligible( 'elementor-pro-widget-form', $pro . 'css/widget-form.min.css?ver=4.3.0.157' ), 'Una hoja de widget de las funciones avanzadas es apta, con versión en la URL.' );
check( $css::eligible( 'widget-heading', $core . 'css/widget-heading.css' ), 'La hoja sin minificar también es apta.' );
check( $css::eligible( 'widget-heading', 'https://example.test/subsitio/wp-content/plugins/digitalisimo-elements-4.3.0.157/elementor/assets/css/widget-heading.min.css' ), 'En un subsitio por subdirectorio la ruta lleva el sitio delante y sigue siendo del constructor.' );
check( ! $css::eligible( 'elementor-frontend', $core . 'css/frontend.min.css' ) && ! $css::eligible( 'widget-heading', $core . 'css/frontend.min.css' ), 'frontend.min.css nunca es apta.' );
check( ! $css::eligible( 'widget-heading', $pro . 'css/widget-form.min.css' ), 'La hoja debe ser la del widget del handle.' );
check( ! $css::eligible( 'widget-heading', 'https://example.test/wp-content/plugins/otro-plugin/assets/css/widget-heading.min.css' ), 'Una hoja homónima de otro plugin no es apta.' );
check( ! $css::eligible( 'widget-heading', 'https://example.test/wp-content/plugins/digitalisimo-elements/elementor/assets/css/widget-heading.min.css' ), 'Ninguna carpeta va fija en el código: otra copia que no es el núcleo en uso no cuenta.' );
check( ! $css::eligible( 'widget-heading', $core . 'js/widget-heading.min.css' ) && ! $css::eligible( 'widget-heading', $core . 'css/widget-heading.min.css.map' ), 'Sólo cuenta css/widget-<x>(.min).css bajo el prefijo.' );
check( $css::eligible( 'widget-heading', $historic . 'css/widget-heading.min.css' ), 'Con constantes, las carpetas históricas siguen valiendo.' );

// Depuración: «DIFERIDO CONFIGURADO» y frontend crítico.
check( 'DIFERIDO CONFIGURADO' === $debug::css_label( array( 'handle' => 'widget-form', 'src' => $pro . 'css/widget-form.min.css' ), true, false, array( 'widget-form' ) ), 'La depuración debe marcar DIFERIDO CONFIGURADO en el núcleo incluido.' );
check( 'CRÍTICO' === $debug::css_label( array( 'handle' => 'widget-heading', 'src' => $core . 'css/widget-heading.min.css' ), true, false, array( 'widget-heading' ) ), 'Una hoja crítica por handle sigue siendo crítica en el núcleo incluido.' );
check( 'NORMAL' === $debug::css_label( array( 'handle' => 'widget-form', 'src' => $pro . 'css/widget-form.min.css' ), false, false, array( 'widget-form' ) ), 'Sin diferido configurado no se marca.' );
check( 'CRÍTICO' === $debug::css_label( array( 'handle' => 'frontend-x', 'src' => $pro . 'css/frontend.min.css' ), true, false, array() ), 'El frontend del núcleo incluido es crítico.' );
check( 'NORMAL' === $debug::css_label( array( 'handle' => 'widget-form', 'src' => 'https://example.test/wp-content/plugins/otro-plugin/assets/css/widget-form.min.css' ), true, false, array( 'widget-form' ) ), 'La depuración no marca hojas de otros plugins.' );

// Diagnóstico: «Constructor» para el núcleo incluido, «Constructor externo» sólo para las carpetas históricas.
check( 'Constructor' === $origin->invoke( null, $core . 'js/frontend.min.js' ) && 'Constructor' === $origin->invoke( null, $pro . 'css/widget-form.min.css' ), 'El diagnóstico debe clasificar el núcleo incluido como Constructor.' );
check( 'Constructor externo' === $origin->invoke( null, $historic . 'js/frontend.min.js' ) && 'Constructor externo' === $origin->invoke( null, $historic_pro . 'css/frontend.min.css' ), 'Constructor externo queda para las carpetas históricas.' );
check( 'DIGITALÍSIMO' === $origin->invoke( null, 'https://example.test/wp-content/plugins/digitalisimo-seo/assets/app.js' ) && 'Externo' === $origin->invoke( null, 'https://cdn.example.org/wp-content/plugins/digitalisimo-elements-4.3.0.157/assets/x.js' ), 'Los demás orígenes no cambian.' );
check( 'Constructor' === $status->invoke( null, 'otro', $core . 'css/x.css', 'CSS' ) && 'Crítico' === $status->invoke( null, 'elementor-frontend', $core . 'css/frontend.min.css', 'CSS' ), 'El estado reconoce el núcleo incluido sin cambiar los críticos.' );
$late = $preload( '<head><link rel="stylesheet" id="elementor-frontend-css" href="' . $core . 'css/frontend.min.css">' . $font . '</head>' );
$early = $preload( '<head>' . $font . '<link rel="stylesheet" id="elementor-frontend-css" href="' . $core . 'css/frontend.min.css"></head>' );
check( 'DESPUÉS del CSS del constructor' === $late['position'] && 'OK' !== $late['status'] && 'ANTES del CSS del constructor' === $early['position'] && 'OK' === $early['status'], 'La posición de los preloads se mide frente al CSS del núcleo incluido.' );

// Ningún nombre de proveedor nuevo: las rutas propias salen de constantes, no de dominios ni carpetas fijas.
$source = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-css.php' );
check( preg_match( '/function builder_asset_paths\(\).*?\n\t}\n/s', $source, $helper ) && false === strpos( $helper[0], 'digitalisimo' ) && ! preg_match( '#https?://|\.(?:com|net|org)\b#', $helper[0] ) && 2 === substr_count( $helper[0], "\$paths[] = '/plugins/" ), 'El ayudante sólo lee las constantes y añade las dos carpetas históricas.' );

echo "SEO: hojas del constructor reconocidas con el núcleo incluido, en carpeta renombrada y en subsitios; sin constantes, como antes.\n";
