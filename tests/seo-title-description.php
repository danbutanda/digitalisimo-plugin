<?php
/**
 * Plantillas de título y descripción (auditoría SEO del 2026-11-24, punto A3), sin WordPress:
 *   1. una variable vacía se lleva su separador: «%focuskw% %sep% %excerpt%» sin datos no deja «|», con la keyword sola
 *      no deja «Título |», y un separador que es parte de una palabra («Wi-Fi», «24/7») no se toca;
 *   2. el texto escrito a mano, sin variables, sale tal cual (también si empieza con «|»);
 *   3. sin descripción propia, una plantilla que queda vacía o que sólo repite el título se sustituye por un resumen
 *      del contenido de 155 caracteres o menos, sin shortcodes ni bloques pegados;
 *   4. el título escrito a mano no recibe el texto fijo de la plantilla.
 * Dominios y textos inventados.
 */
require_once __DIR__ . '/fixtures/php74.php';
define( 'ABSPATH', __DIR__ );

$GLOBALS['settings'] = array( 'seo_separator' => '|' );
$GLOBALS['meta']     = array();
$GLOBALS['post']     = null;

class Digitalisimo_Integrations_Settings {
	public static function get( $key, $fallback = '' ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
}
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $default = null ) { return Digitalisimo_Integrations_Settings::get( $key, $default ); }
	public static function post( $id, $key, $default = '' ) { return $default; }
	public static function foreign_seo() { return ''; }
}
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function strip_shortcodes( $value ) { return preg_replace( '/\[(caption|gallery)[^\]]*\](?:.*?\[\/\1\])?/s', '', (string) $value ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function get_post( $id ) { return $GLOBALS['post']; }
function get_the_title( $id ) { return $GLOBALS['post'] ? $GLOBALS['post']->post_title : ''; }
function get_bloginfo( $key ) { return 'description' === $key ? 'Lema del sitio de prueba.' : 'Sitio de Prueba'; }
function get_permalink( $id ) { return 'https://tienda-ejemplo.test/pagina/'; }
function get_option( $key, $default = false ) { return $default; }
function is_paged() { return false; }
function is_search() { return false; }
function wp_date( $format ) { return gmdate( $format ); }
function get_the_terms( $id, $tax ) { return array(); }
function is_protected_meta( $key ) { return false; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ); }

require __DIR__ . '/../digitalisimo-seo/includes/class-seo-variables.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';

$fails = 0;
function check( $ok, $message ) { global $fails; if ( $ok ) return; $fails++; fwrite( STDERR, "FALLA: {$message}\n" ); }
$V = 'Digitalisimo_Integrations_SEO_Variables';

// 1. Separadores de variables vacías.
$tpl = '%focuskw% %sep% %excerpt%';
check( '' === $V::replace( $tpl, array( 'excerpt' => '' ) ), 'Sin keyword ni extracto la plantilla no deja «|»: «' . $V::replace( $tpl, array( 'excerpt' => '' ) ) . '».' );
check( 'Oferta de temporada vigente.' === $V::replace( $tpl, array( 'excerpt' => 'Oferta de temporada vigente.' ) ), 'Sin keyword la descripción no empieza con «|»: «' . $V::replace( $tpl, array( 'excerpt' => 'Oferta de temporada vigente.' ) ) . '».' );
check( 'Hosting Ejemplo' === $V::replace( '%title% %sep% %excerpt%', array( 'title' => 'Hosting Ejemplo', 'excerpt' => '' ) ), 'Sin extracto no queda «Título |».' );
check( 'Hosting Ejemplo | Sitio de Prueba' === $V::replace( '%title% %sep% %sitename%', array( 'title' => 'Hosting Ejemplo' ) ), 'Una plantilla completa no cambia.' );
check( 'Uno | Dos' === $V::replace( '%title% | %excerpt% | %description%', array( 'title' => 'Uno', 'excerpt' => '', 'description' => 'Dos' ) ), 'Entre dos separadores, una variable vacía deja uno solo.' );
check( 'Wi-Fi gratis' === $V::replace( 'Wi-Fi gratis %excerpt%', array( 'excerpt' => '' ) ), 'Un guion dentro de una palabra no es un separador.' );
check( 'Soporte 24/7' === $V::replace( 'Soporte 24/7 - %excerpt%', array( 'excerpt' => '' ) ), '«24/7» se conserva; el guion suelto junto a la variable vacía se va.' );
foreach ( array( '-', '–', '·' ) as $choice ) {
	$GLOBALS['settings']['seo_separator'] = $choice;
	check( 'Uno' === $V::replace( '%title% %sep% %excerpt%', array( 'title' => 'Uno', 'excerpt' => '' ) ), "El separador elegible «{$choice}» también se quita." );
}
$GLOBALS['settings']['seo_separator'] = '|';
check( 'Cien % natural' === $V::replace( 'Cien % natural', array() ), 'Un % suelto no es una variable.' );

// 2. Lo escrito a mano, sin variables, sale tal cual.
check( '| Texto a mano' === $V::replace( '| Texto a mano', array() ), 'El texto escrito sin variables no se toca.' );

// 3. Resumen del contenido.
$html = '<!-- wp:paragraph --><p>Instalamos calentadores solares en casas y negocios.</p><!-- /wp:paragraph --><div><h2>Garantía</h2><p>Todos los equipos llevan garantía de cinco años y mantenimiento incluido el primer año, con visitas programadas.</p></div>[wpuf_account][formulario id="3"]<script>var x = "no";</script><style>p{color:red}</style><p>Pide tu cotización hoy mismo por WhatsApp y te respondemos en minutos con precio cerrado.</p>';
$summary = $V::summary( (object) array( 'post_content' => $html ) );
check( mb_strlen( $summary, 'UTF-8' ) <= 155, 'El resumen tiene 155 caracteres o menos: ' . mb_strlen( $summary, 'UTF-8' ) );
check( 0 === strpos( $summary, 'Instalamos calentadores solares en casas y negocios. Garantía Todos' ), 'Los bloques no se pegan: «' . $summary . '».' );
check( false === strpos( $summary, '[' ) && false === strpos( $summary, 'var x' ) && false === strpos( $summary, 'color' ), 'Sin shortcodes (tampoco los de plugins quitados), scripts ni estilos.' );
check( '…' === mb_substr( $summary, -1, 1, 'UTF-8' ) && ' ' !== mb_substr( $summary, -2, 1, 'UTF-8' ), 'Se corta en una palabra completa, con «…».' );
check( 'Texto corto.' === $V::summary( (object) array( 'post_content' => '<p>Texto corto.</p>' ) ), 'Un texto corto no se corta.' );

// snippet_for: descripción.
$GLOBALS['post'] = (object) array( 'ID' => 7, 'post_type' => 'page', 'post_title' => 'Calentadores Solares', 'post_excerpt' => '', 'post_content' => $html );
$GLOBALS['settings']['seo_title_page']       = '%title% 🤖 Garantizado Hoy';
$GLOBALS['settings']['seo_description_page'] = '%focuskw% %sep% %title%';
$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $GLOBALS['post'] );
check( $summary === $snippet['description'], 'Una plantilla que sólo repite el título se sustituye por el resumen: «' . $snippet['description'] . '».' );
$GLOBALS['settings']['seo_description_page'] = '%focuskw% %sep% %excerpt%';
$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $GLOBALS['post'] );
check( $summary === $snippet['description'], 'Sin extracto escrito, %excerpt% es el resumen de 155 caracteres: «' . $snippet['description'] . '».' );
$GLOBALS['post']->post_content = '';
$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $GLOBALS['post'] );
check( 'Lema del sitio de prueba.' === $snippet['description'], 'Sin contenido, la descripción no es «|»: «' . $snippet['description'] . '».' );
$GLOBALS['post']->post_content = $html;

// 4. Lo escrito a mano manda.
$GLOBALS['meta']['digitalisimo_seo_title']       = 'Calentadores solares en Ciudad Ejemplo';
$GLOBALS['meta']['digitalisimo_seo_description'] = '| Descripción escrita a propósito con su barra.';
$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $GLOBALS['post'] );
check( 'Calentadores solares en Ciudad Ejemplo' === $snippet['title'], 'El título escrito a mano no recibe el texto fijo de la plantilla: «' . $snippet['title'] . '».' );
check( '| Descripción escrita a propósito con su barra.' === $snippet['description'], 'La descripción escrita a mano se respeta tal cual.' );
$GLOBALS['meta'] = array();
$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $GLOBALS['post'] );
check( 'Calentadores Solares 🤖 Garantizado Hoy' === $snippet['title'], 'La plantilla del sitio se aplica (el aviso la señala aparte): «' . $snippet['title'] . '».' );

if ( $fails ) { fwrite( STDERR, "{$fails} falla(s).\n" ); exit( 1 ); }
echo "SEO: plantillas sin separadores colgando, resumen de 155 caracteres y lo escrito a mano intacto.\n";
