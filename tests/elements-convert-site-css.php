<?php
/**
 * CSS del sitio que nombra clases de los listados y formularios de PRO (corrección de fidelidad de C5 y C6,
 * docs/pro/c5.md y docs/pro/c6.md), con los mapas reales: el CSS del kit, del Personalizador, de la página y de otros
 * elementos gana, detrás de cada regla que nombra clases de PRO, su copia con las clases propias, así que sigue
 * alcanzando a los widgets convertidos, como alcanzaba a los de PRO. Lo hace el mismo traductor del CSS «desde fuera» de
 * la corrección de C3–C4 (`Converter::external_css()`/`tree_css()`, `Translator::rewrite_external_css()`; el detalle del
 * traductor está en `elements-convert-fidelity.php`); aquí, con las clases de Entradas, Formulario, Menú del carrito y
 * paginación:
 * - con el envoltorio de un widget (`.elementor-widget-posts …`), su copia con el envoltorio y las clases propias; sin él,
 *   una copia por cada widget de PRO que usaba la clase (`.elementor-pagination` estaba en dos);
 * - dentro de `@media`; `@keyframes`, comentarios y cadenas con llaves, intactos; variables CSS del mapa en las
 *   declaraciones;
 * - aditivo (cada regla original sigue, en su orden), idempotente (una segunda pasada no duplica nada) y el CSS sin
 *   clases de PRO no cambia ni un byte;
 * - un estado que el propio expresa con un atributo o una pseudoclase (`.swiper-button-disabled`, `.elementor-active`,
 *   `.e-focus`), suelto, no se copia: alcanzaría a todo el sitio;
 * - `tree_css()` sobre el `custom_css` de los elementos de un documento, a cualquier profundidad.
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
foreach ( array( 'catalog', 'registry', 'translator', 'converter' ) as $file ) {
	require $root . 'class-' . $file . '.php';
}
use Digitalisimo\Elements\Convert\Converter;
use Digitalisimo\Elements\Convert\Registry;

/** ¿Siguen todas las reglas de `$original` en `$out`, en su orden y sin cambiar? (aditivo) */
function sc_additive( string $original, string $out ): bool {
	$at = 0;
	foreach ( preg_split( '/(?<=\})/', $original ) as $piece ) {
		if ( '' === trim( $piece ) ) {
			continue;
		}
		$pos = strpos( $out, $piece, $at );
		if ( false === $pos ) {
			return false;
		}
		$at = $pos + strlen( $piece );
	}
	return true;
}

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
$out = Converter::external_css( $css );
check_sc( str_starts_with( $out, '.elementor-widget-posts .elementor-post__title a{color:red}' ) && 1 === preg_match( '#^[^\n]*\{color:red\}[^\n]*\.elementor-widget-digitalisimo-posts \.digi-posts__title a\{color:red\}\n#', $out ), 'Con el envoltorio de Entradas: la regla original y, detrás, su copia con el envoltorio y las clases propias: ' . $out );
check_sc( str_contains( $out, '@media (max-width:767px){.elementor-post__title{font-size:12px}.digi-posts__title{font-size:12px}}' ), 'Dentro de @media, sin envoltorio: la clase propia.' );
check_sc( str_contains( $out, "@keyframes x{from{opacity:0}to{opacity:1}}\n.foo{content:\"}\"}\n" ), '@keyframes, comentarios y cadenas con llaves, intactos.' );
check_sc( str_contains( $out, ".elementor-widget-form .elementor-field-group input,.mi-campo{border:0}\n.elementor-widget-digitalisimo-form .elementor-field-group input,.mi-campo{border:0}" ), 'Formulario: la copia con el envoltorio propio va justo detrás de la regla original: ' . $out );
check_sc( 1 === preg_match( '#\.elementor-widget-woocommerce-menu-cart\{--cart-background-color:red\}\s*[^{}]+\{--mc-cbc:red\}#', $out ), 'Las variables CSS del mapa también se traducen en la copia: ' . $out );
check_sc( str_contains( $out, '.digi-loop__pagination .page-numbers{gap:2px}' ) && str_contains( $out, '.digi-search__pages .page-numbers{gap:2px}' ), 'Sin envoltorio y en varios widgets de PRO: una copia por cada uno: ' . $out );
check_sc( sc_additive( $css, $out ), 'Aditivo: cada regla original sigue igual y en su orden.' );
check_sc( Converter::external_css( $out ) === $out, 'Idempotente: una segunda pasada no duplica nada.' );

// Sin envoltorio, un estado que el propio expresa con un atributo o una pseudoclase no se copia solo: alcanzaría a todo
// el sitio (`[aria-disabled=true]{opacity:0}` ocultaría cualquier control desactivado). Atado a una clase del widget, sí.
$states     = ".swiper-button-disabled{opacity:0}\n.elementor-active{background:red}\n.e-gallery-item--hidden{opacity:.5}\n.e-focus{outline:1px solid}\n.elementor-nav-menu .elementor-active{color:blue}\n.elementor-widget-nav-menu .elementor-active{color:red}";
$states_out = Converter::external_css( $states );
check_sc( ! preg_match( '#(^|[}\n])\s*(\[aria-disabled=true\]|\[aria-expanded=true\]|\[aria-pressed="true"\]|\[hidden\]|:has\()#', $states_out ) && ! str_contains( $states_out, '.elementor-nav-menu [aria-pressed' ), 'Sin envoltorio, ningún atributo ni pseudoclase suelto alcanza a todo el sitio: ' . $states_out );
check_sc( 1 === preg_match( '#\.elementor-nav-menu \.elementor-active\{color:blue\}\s*\.digi-nav[\w-]* \[aria-expanded=true\]\{color:blue\}#', $states_out ) && str_contains( $states_out, '.elementor-widget-digitalisimo-nav-menu [aria-expanded=true]{color:red}' ), 'Atado a una clase del widget (o con su envoltorio), el estado sí se copia: ' . $states_out );
$plain = ".a{color:red}\n@media print{.b{display:none}}\n/* .elementor-post__title en un comentario */ .c{}";
check_sc( Converter::external_css( $plain ) === $plain, 'CSS sin clases de PRO en sus selectores: ni un byte cambia.' );

// tree_css(): el custom_css de un contenedor y de un contenedor anidado; el widget que no nombra PRO, igual.
$tree = array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array( 'custom_css' => 'selector .elementor-widget-posts .elementor-post__title{color:red}' ), 'elements' => array(
		array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'custom_css' => 'selector{color:blue}' ), 'elements' => array() ),
		array( 'id' => 'c2', 'elType' => 'container', 'settings' => array( 'custom_css' => '.elementor-widget-form{margin:0}' ), 'elements' => array() ),
	) ),
);
$changed = 0;
$new     = Converter::tree_css( $tree, null, $changed );
check_sc( 2 === $changed && str_contains( $new[0]['settings']['custom_css'], 'selector .elementor-widget-digitalisimo-posts .digi-posts__title{color:red}' ) && 'selector{color:blue}' === $new[0]['elements'][0]['settings']['custom_css'] && '.elementor-widget-form{margin:0}.elementor-widget-digitalisimo-form{margin:0}' === $new[0]['elements'][1]['settings']['custom_css'], 'tree_css(): el CSS de los elementos, a cualquier profundidad; el que no nombra PRO, igual: ' . json_encode( $new ) );
$again = 0;
check_sc( $new === Converter::tree_css( $new, null, $again ) && 0 === $again, 'tree_css() idempotente.' );

echo "DIGITALÍSIMO Elements: CSS del sitio con las clases de PRO de listados y formularios copiado a las propias (aditivo e idempotente) validado.\n";
