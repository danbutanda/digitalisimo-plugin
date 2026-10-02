<?php
/** Rutas y XML reales del sitemap por sitio, sin arrancar WordPress. */
define( 'ABSPATH', __DIR__ );
$multisite = true;
$site = 'https://ejemplo.test/subsitio/';
$options = array( 'blog_public' => 1, 'show_on_front' => 'posts' );
$items = array(
	'page' => array( 10 => 'https://ejemplo.test/subsitio/servicios/', 11 => 'https://ejemplo.test/subsitio/privada/' ),
	'post' => array( 20 => 'https://ejemplo.test/subsitio/blog/mi-articulo-personalizado/' ),
	'product' => array( 30 => 'https://ejemplo.test/subsitio/tienda/camisa-azul/' ),
);
function is_multisite() { global $multisite; return $multisite; }
function home_url( $path = '/' ) { global $site; return rtrim( $site, '/' ) . $path; }
function get_option( $key, $default = null ) { global $options; return $options[ $key ] ?? $default; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function sanitize_title( $text ) { return strtolower( preg_replace( '/[^a-z0-9-]/', '-', $text ) ); }
function get_post_types( $args, $output ) { return array( 'page' => (object) array( 'label' => 'Páginas' ), 'post' => (object) array( 'label' => 'Entradas' ), 'product' => (object) array( 'label' => 'Productos' ) ); }
function get_posts( $args ) {
	global $items;
	$ids = array_keys( $items[ $args['post_type'] ] ?? array() );
	$ids = array_values( array_diff( $ids, $args['post__not_in'] ?? array() ) );
	return array_slice( $ids, ( $args['paged'] - 1 ) * $args['posts_per_page'], $args['posts_per_page'] );
}
function get_permalink( $id ) { global $items; foreach ( $items as $posts ) if ( isset( $posts[ $id ] ) ) return $posts[ $id ]; return false; }
function get_post( $id ) { return (object) array( 'ID' => $id ); }
function untrailingslashit( $text ) { return rtrim( $text, '/' ); }
function esc_xml( $text ) { return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); }
function apply_filters( $hook, $value, ...$args ) {
	return $value;
}
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $default; } }
class Digitalisimo_Integrations_SEO_Suite { public static function sitemap_posts( $types ) { return $types; } public static function sitemap_query( $args, $type ) { $args['post__not_in'] = array( 11 ); return $args; } }
require __DIR__ . '/../digitalisimo-seo/includes/class-sitemap.php';
$sitemap = Digitalisimo_Integrations_Sitemap::class;
$_SERVER['REQUEST_URI'] = '/subsitio/sitemap.xml';
if ( 'main' !== $sitemap::requested()['type'] ) throw new RuntimeException( 'El sitemap de un subsitio debe respetar su prefijo.' );
$_SERVER['REQUEST_URI'] = '/sitemap.xml';
if ( $sitemap::requested() ) throw new RuntimeException( 'Un subsitio no debe responder por la raíz de otro blog.' );
$_SERVER['REQUEST_URI'] = '/subsitio/paginas.xml';
if ( $sitemap::requested() ) throw new RuntimeException( 'Multisite no debe exponer sitemaps secundarios.' );
ob_start(); $sitemap::urls_xml( array_keys( $sitemap::filenames() ) ); $xml = ob_get_clean();
foreach ( array( '/subsitio/', '/subsitio/servicios/', '/subsitio/blog/mi-articulo-personalizado/', '/subsitio/tienda/camisa-azul/' ) as $url ) if ( false === strpos( $xml, $url ) ) throw new RuntimeException( 'Falta un permalink propio del sitio: ' . $url );
if ( false !== strpos( $xml, 'privada' ) || false !== strpos( $xml, '<sitemapindex' ) || false !== strpos( $xml, 'wp-sitemap' ) ) throw new RuntimeException( 'Multisite incluyó una URL excluida o un índice técnico.' );
$multisite = false; $site = 'https://individual.test/';
$_SERVER['REQUEST_URI'] = '/articulos.xml';
if ( 'post' !== $sitemap::requested()['type'] ) throw new RuntimeException( 'Debe existir el archivo amigable de artículos.' );
$_SERVER['REQUEST_URI'] = '/post-1.xml';
if ( $sitemap::requested() ) throw new RuntimeException( 'No debe existir un nombre técnico y numerado.' );
ob_start(); $sitemap::index_xml(); $index = ob_get_clean();
foreach ( array( 'paginas.xml', 'articulos.xml', 'productos.xml' ) as $filename ) if ( false === strpos( $index, $filename ) ) throw new RuntimeException( 'Falta el índice por tipo: ' . $filename );
if ( false !== strpos( $index, 'wp-sitemap' ) || false !== strpos( $index, 'post-1.xml' ) ) throw new RuntimeException( 'El índice contiene nombres técnicos.' );
ob_start(); $sitemap::urls_xml( array( 'post' ) ); $articles = ob_get_clean();
if ( false === strpos( $articles, 'blog/mi-articulo-personalizado/' ) || false !== strpos( $articles, '/servicios/' ) ) throw new RuntimeException( 'El archivo de artículos debe conservar sólo sus permalinks.' );
echo "Sitemap: Multisite único, índice legible, exclusiones y permalinks correctos.\n";
