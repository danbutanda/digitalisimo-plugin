<?php
/** RM10 y RM13: variables de las plantillas y HTML de las migas, sin WordPress. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array( 'seo_site_name' => 'Acme', 'seo_separator' => '|' );
$GLOBALS['meta']    = array( 5 => array( 'digitalisimo_seo_keywords' => 'zapatos rojos, tenis', 'precio' => '99', '_interna' => 'x', 'digitalisimo_seo_title' => 'propio' ) );
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function get_bloginfo( $key ) { return 'description' === $key ? 'Lema del sitio' : 'WordPress'; }
function is_paged() { return false; }
function is_search() { return false; }
function wp_date( $format ) { return 'Y' === $format ? '2026' : 'octubre'; }
function get_option( $key ) { return 'j F Y'; }
function get_post( $id ) { return 5 === $id ? (object) array( 'ID' => 5, 'post_parent' => 0, 'post_type' => 'post', 'post_author' => 1 ) : null; }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function is_protected_meta( $key ) { return '_' === $key[0]; }
function get_the_date( $format ) { return '' === $format ? '1 de enero de 2026' : '2026'; }
function get_the_author_meta( $field, $id ) { return 'Ana'; }
function get_the_terms() { return false; }
function absint( $value ) { return abs( (int) $value ); }
function get_post_type_object( $type ) { return (object) array( 'labels' => (object) array( 'singular_name' => 'Entrada', 'name' => 'Entradas' ) ); }
function get_the_modified_date( $format ) { return '2 de enero de 2026'; }
function get_the_title( $id ) { return 'Padre'; }
function wp_list_pluck( $list, $field ) { return array_map( function( $o ) use ( $field ) { return $o->$field; }, $list ); }
function get_search_query() { return ''; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-variables.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-breadcrumbs.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$v   = Digitalisimo_Integrations_SEO_Variables::class;
$ctx = array( 'title' => 'Hola', 'id' => 5 );
check( 'Hola | Acme' === $v::replace( '%title% %sep% %sitename%', $ctx ), 'Variables de siempre.' );
check( 'zapatos rojos · Lema del sitio · 2026 · Ana · 2026' === $v::replace( '%focuskw% · %sitedesc% · %currentyear% · %name% · %date(Y)%', $ctx ), 'Variables nuevas, también con formato.' );
check( '99' === $v::replace( '%customfield(precio)%', $ctx ), 'Un campo público se publica.' );
check( '' === $v::replace( '%customfield(_interna)% %customfield(digitalisimo_seo_title)%', $ctx ), 'Un campo interno nunca se publica.' );
check( 'Hola' === $v::replace( '%title% %inventada% %title(x)%', $ctx ), 'Lo desconocido se quita, nunca se imprime literal.' );
check( '50% de descuento y 20% más' === $v::replace( '50% de descuento y 20% más', $ctx ), 'Un porcentaje suelto no es una variable.' );
check( '' === $v::replace( '%category% %pt_single%', array( 'title' => 'Sin contenido' ) ), 'Sin contenido, las variables del contenido quedan vacías.' );
foreach ( array_keys( $v::catalog() ) as $name ) check( ! preg_match( '/%' . $name . '%/', $v::replace( '%' . $name . '%', $ctx ) ), 'Ninguna variable se imprime literal: ' . $name );

$b     = Digitalisimo_Integrations_Breadcrumbs::class;
$items = array( array( 'name' => 'Inicio', 'url' => 'https://s.test/' ), array( 'name' => 'Blog <b>', 'url' => 'https://s.test/blog/' ), array( 'name' => 'Entrada', 'url' => 'https://s.test/blog/entrada/' ) );
$html  = $b::render( $items, array( 'separator' => '»', 'prefix' => 'Estás en:', 'show_home' => 1, 'hide_current' => 0 ) );
check( false !== strpos( $html, 'Estás en:' ) && 2 === substr_count( $html, '<a ' ) && false !== strpos( $html, '<span aria-current="page">Entrada</span>' ) && 2 === substr_count( $html, '»' ), 'Ruta completa: enlaces, separador propio y página actual sin enlace.' );
check( false !== strpos( $html, 'Blog &lt;b&gt;' ), 'Los nombres se escapan.' );
$short = $b::render( $items, array( 'separator' => '', 'show_home' => 0, 'hide_current' => 1 ) );
check( false === strpos( $short, 'Inicio' ) && false === strpos( $short, 'Entrada' ) && false !== strpos( $short, '<a href="https://s.test/blog/">' ) && false === strpos( $short, '›' ), 'Ocultar Inicio y la página actual deja un nivel enlazado.' );
check( '' !== $b::render( array( $items[0] ), array( 'show_home' => 0, 'hide_current' => 1 ) ), 'Nunca se vacía del todo: con un solo nivel se muestra.' );
check( 'Archivo de Ana' === $b::format( 'Archivo de %s', 'Ana' ) && 'Autor Ana' === $b::format( 'Autor', 'Ana' ), 'Formatos con y sin %s.' );
$schema = $b::schema_items( $items );
check( 3 === count( $schema ) && 3 === $schema[2]['position'] && 'https://s.test/blog/entrada/' === $schema[2]['item'], 'El schema conserva la ruta completa.' );
echo "Migas y variables: rutas, HTML, formatos y variables correctos.\n";
