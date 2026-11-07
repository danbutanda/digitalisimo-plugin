<?php
/**
 * CSS del sitio que nombra clases de los widgets de PRO (`modules/digitalisimo-convert/class-site-css.php`, corrección de
 * fidelidad de C5 y C6, docs/pro/c5.md y docs/pro/c6.md): detrás de cada regla que nombra clases de PRO va la misma regla
 * con los selectores traducidos por los mapas listos, así que el CSS del kit, del Personalizador, de la página y de otros
 * elementos sigue alcanzando a los widgets convertidos, como alcanzaba a los de PRO.
 * - con el envoltorio de un widget (`.elementor-widget-posts …`), sólo su mapa; sin él, cada mapa que nombra la clase
 *   (`.elementor-pagination` estaba en dos widgets de PRO: las dos traducciones);
 * - dentro de `@media`; `@keyframes`, comentarios y cadenas con llaves, intactos; variables CSS del mapa en las
 *   declaraciones;
 * - aditivo (la regla original no cambia), idempotente (una segunda pasada no duplica), `strip()` devuelve el original
 *   exacto y el CSS sin clases de PRO no cambia ni un byte;
 * - `tree()` sobre el `custom_css` de los elementos de un documento, a cualquier profundidad.
 * Con el código anterior no había nada: esas reglas dejaban de aplicarse tras «Migrar».
 *
 * Uso: php-wasm-cli tests/elements-convert-site-css.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la
 * línea final.
 */
define( 'ABSPATH', __DIR__ . '/' );
function esc_html__( $t ) { return $t; }
function __( $t ) { return $t; }
function esc_attr__( $t ) { return $t; }
function check_sc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

$root = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-convert/';
foreach ( array( 'catalog', 'registry', 'translator' ) as $file ) {
	require $root . 'class-' . $file . '.php';
}
check_sc( is_file( $root . 'class-site-css.php' ), 'Existe Convert\\Site_Css (class-site-css.php).' );
require $root . 'class-site-css.php';
use Digitalisimo\Elements\Convert\Registry;
use Digitalisimo\Elements\Convert\Site_Css;

$posts = Registry::map( 'posts' )['classes'];
$form  = Registry::map( 'form' )['classes'];
check_sc( '.elementor-widget-digitalisimo-posts' === ( $posts['.elementor-widget-posts'] ?? '' ) && '.digi-posts__title' === ( $posts['.elementor-post__title'] ?? '' ) && '.elementor-widget-digitalisimo-form' === ( $form['.elementor-widget-form'] ?? '' ), 'Premisa: los mapas de Entradas y del Formulario traducen su envoltorio y sus clases.' );

$css = ".elementor-widget-posts .elementor-post__title a{color:red}\n"
	. "@media (max-width:767px){.elementor-post__title{font-size:12px}}\n"
	. "@keyframes x{from{opacity:0}to{opacity:1}}\n"
	. ".foo{content:\"}\"}\n"
	. ".elementor-widget-form .elementor-field-group input,.mi-campo{border:0}\n"
	. ".elementor-widget-woocommerce-menu-cart{--cart-background-color:red}\n"
	. "/* nota */ .elementor-pagination .page-numbers{gap:2px}";
$out = Site_Css::rewrite( $css );
$want = ".elementor-widget-posts .elementor-post__title a{color:red}/*digitalisimo-pro*/.elementor-widget-digitalisimo-posts .digi-posts__title a{color:red}\n";
check_sc( str_starts_with( $out, $want ), 'Con el envoltorio de Entradas: la regla original y, detrás, la traducida con su mapa: ' . $out );
check_sc( str_contains( $out, "@media (max-width:767px){.elementor-post__title{font-size:12px}/*digitalisimo-pro*/.digi-posts__title{font-size:12px}}" ), 'Dentro de @media, sin envoltorio: la clase traducida.' );
check_sc( str_contains( $out, "@keyframes x{from{opacity:0}to{opacity:1}}\n.foo{content:\"}\"}\n" ), '@keyframes, comentarios y cadenas con llaves, intactos.' );
check_sc( str_contains( $out, ".elementor-widget-form .elementor-field-group input,.mi-campo{border:0}/*digitalisimo-pro*/.elementor-widget-digitalisimo-form .elementor-field-group input{border:0}" ), 'Formulario: sólo el selector con clases de PRO gana su traducción (el resto de la lista no se repite).' );
check_sc( 1 === preg_match( '#\.elementor-widget-woocommerce-menu-cart\{--cart-background-color:red\}/\*digitalisimo-pro\*/[^{]+\{--mc-cbc:red\}#', $out ), 'Las variables CSS del mapa también se traducen en la regla nueva: ' . $out );
check_sc( 1 === preg_match( '#/\* nota \*/ \.elementor-pagination \.page-numbers\{gap:2px\}/\*digitalisimo-pro\*/([^{]+)\{gap:2px\}#', $out, $m ) && count( explode( ',', $m[1] ) ) >= 2 && str_contains( $m[1], '.digi-loop__pagination .page-numbers' ), 'Sin envoltorio y en varios widgets de PRO: una traducción por cada uno: ' . ( $m[1] ?? '' ) );
check_sc( Site_Css::rewrite( $out ) === $out, 'Idempotente: una segunda pasada no duplica nada.' );
check_sc( Site_Css::strip( $out ) === $css && Site_Css::marked( $out ) && ! Site_Css::marked( $css ), 'strip() devuelve el CSS original exacto (para --revert).' );
// Sin envoltorio, un estado que el propio expresa con un atributo o una pseudoclase no se traduce solo: alcanzaría a todo
// el sitio (`[aria-disabled=true]{opacity:0}` ocultaría cualquier control desactivado). Atado a una clase del widget, sí.
$states = ".swiper-button-disabled{opacity:0}\n.elementor-active{background:red}\n.e-gallery-item--hidden{opacity:.5}\n.e-focus{outline:1px solid}\n.elementor-nav-menu .elementor-active{color:blue}\n.elementor-widget-nav-menu .elementor-active{color:red}";
$states_out = Site_Css::rewrite( $states );
check_sc( ! preg_match( '#\*/(\[aria-disabled=true\]|\[aria-expanded=true\]|\[aria-pressed="true"\]|\[hidden\]|:has\()#', $states_out ) && ! str_contains( $states_out, '.elementor-nav-menu [aria-pressed' ), 'Sin envoltorio, ningún atributo ni pseudoclase suelto alcanza a todo el sitio: ' . $states_out );
check_sc( 1 === preg_match( '#\.elementor-nav-menu \.elementor-active\{color:blue\}/\*digitalisimo-pro\*/\.digi-nav[\w-]* \[aria-expanded=true\]\{color:blue\}#', $states_out ) && str_contains( $states_out, '/*digitalisimo-pro*/.elementor-widget-digitalisimo-nav-menu [aria-expanded=true]{color:red}' ), 'Atado a una clase del widget (o con su envoltorio), el estado sí se traduce: ' . $states_out );
$plain = ".a{color:red}\n@media print{.b{display:none}}\n/* .elementor-post__title en un comentario */ .c{}";
check_sc( Site_Css::rewrite( $plain ) === $plain, 'CSS sin clases de PRO en sus selectores: ni un byte cambia.' );
// Lo editado después de la pasada se conserva al quitar las reglas añadidas.
$edited = str_replace( 'color:red}/*', 'color:blue}/*', $out ) . "\n.nueva{margin:0}";
check_sc( ! str_contains( Site_Css::strip( $edited ), 'digitalisimo-pro' ) && str_contains( Site_Css::strip( $edited ), '.elementor-widget-posts .elementor-post__title a{color:blue}' ) && str_contains( Site_Css::strip( $edited ), '.nueva{margin:0}' ), 'strip() sólo quita las reglas añadidas: lo editado después se conserva.' );

// tree(): el custom_css de un contenedor y de un widget anidados.
$tree = array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array( 'custom_css' => 'selector .elementor-widget-posts .elementor-post__title{color:red}' ), 'elements' => array(
		array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'custom_css' => 'selector{color:blue}' ), 'elements' => array() ),
		array( 'id' => 'c2', 'elType' => 'container', 'settings' => array( 'custom_css' => '.elementor-widget-form{margin:0}' ), 'elements' => array() ),
	) ),
);
list( $new, $changed ) = Site_Css::tree( $tree );
check_sc( 2 === $changed && str_contains( $new[0]['settings']['custom_css'], '/*digitalisimo-pro*/selector .elementor-widget-digitalisimo-posts .digi-posts__title{color:red}' ) && 'selector{color:blue}' === $new[0]['elements'][0]['settings']['custom_css'] && str_contains( $new[0]['elements'][1]['settings']['custom_css'], '.elementor-widget-digitalisimo-form{margin:0}' ), 'tree(): el CSS de los elementos, a cualquier profundidad; el que no nombra PRO, igual.' );
check_sc( array( $new, 0 ) === Site_Css::tree( $new ), 'tree() idempotente.' );

echo "DIGITALÍSIMO Elements: CSS del sitio con las clases de PRO traducidas a las propias (aditivo, idempotente y reversible) validado.\n";
