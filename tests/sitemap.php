<?php
/** Regresión: la portada y las páginas no pueden desaparecer del índice de un subsitio. */
define( 'ABSPATH', __DIR__ );
$blog_id = 1;
$multisite = true;
$site_options = array(
	1 => array( 'sitemap_post_types' => 'dato-invalido', 'noindex_authors' => 1, 'sitemap_exclude_ids' => '7, 8', 'noindex_page_ids' => '9', 'noindex_page_slugs' => 'oculta' ),
	2 => array( 'sitemap_post_types' => "product\npost", 'noindex_authors' => 0 ),
	3 => array( 'sitemap_post_types' => '' ),
);
$network_options = array( 'sitemap_post_types' => "post\n", 'noindex_authors' => 1 );
$inherit = array( 1 => array( 'sitemap_post_types' => 0 ), 2 => array( 'sitemap_post_types' => 0, 'noindex_authors' => 0 ) );
function get_option( $key, $default = null ) { global $site_options, $inherit, $blog_id; if ( 'digitalisimo_integrations_options' === $key ) return $site_options[ $blog_id ] ?? array(); if ( 'digitalisimo_seo_network_inherit' === $key ) return $inherit[ $blog_id ] ?? array(); return $default; }
function get_site_option( $key, $default = null ) { global $network_options; return 'digitalisimo_integrations_options' === $key ? $network_options : $default; }
function is_multisite() { global $multisite; return $multisite; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ); }
function sanitize_title( $value ) { return sanitize_key( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_type_object( $name ) { return 'page' === $name ? (object) array( 'name' => 'page', 'public' => true ) : null; }
function is_post_type_viewable( $type ) { return ! empty( $type->public ); }
function get_posts( $args ) { return in_array( 'oculta', $args['post_name__in'], true ) ? array( 55 ) : array(); }
function wc_get_page_id( $type ) { return array( 'cart' => 30, 'checkout' => 31, 'myaccount' => 32 )[ $type ]; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { global $filters; $filters[ $hook ] = $callback; }
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-resolver.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
$seo = Digitalisimo_Integrations_SEO_Suite::class;
$public = (object) array( 'public' => true, 'exclude_from_search' => false );
$types = array( 'post' => $public, 'page' => $public, 'product' => $public, 'attachment' => $public, 'elementor_library' => $public, 'wp_template' => $public, 'interno' => (object) array( 'public' => false ), 'oculto' => (object) array( 'public' => true, 'exclude_from_search' => true ) );
if ( 'post,page,product' !== $seo::sanitize_sitemap_list( "post\npage; product,post" ) ) throw new RuntimeException( 'La lista debe aceptar comas y saltos de línea sin perder tipos.' );
$first = $seo::sitemap_posts( $types );
if ( array_keys( $first ) !== array( 'page' ) ) throw new RuntimeException( 'Un valor inválido no puede borrar el proveedor de páginas del sitio.' );
if ( false !== $seo::sitemap_provider( new stdClass(), 'users' ) ) throw new RuntimeException( 'Autores noindex no deben aparecer en el índice.' );
if ( false !== $seo::sitemap_provider( new stdClass(), 'taxonomies' ) ) throw new RuntimeException( 'Los archivos de términos no son documentos publicados.' );
$args = $seo::sitemap_query( array( 'post__not_in' => array( 6 ), 'meta_query' => array( array( 'key' => 'externo', 'value' => 'si' ) ) ), 'page' );
foreach ( array( 6, 7, 8, 9, 30, 31, 32, 55 ) as $id ) if ( ! in_array( $id, $args['post__not_in'], true ) ) throw new RuntimeException( 'Falta excluir un contenido noindex o un ID configurado: ' . $id );
if ( 'AND' !== $args['meta_query']['relation'] || 'externo' !== $args['meta_query'][0][0]['key'] ) throw new RuntimeException( 'Debe preservar filtros meta de otros plugins.' );
$blog_id = 2;
$second = $seo::sitemap_posts( $types );
if ( array_keys( $second ) !== array( 'post', 'page', 'product' ) ) throw new RuntimeException( 'Cada sitio debe usar sus propios tipos y conservar páginas.' );
if ( false !== $seo::sitemap_provider( new stdClass(), 'users' ) ) throw new RuntimeException( 'Los autores nunca deben figurar entre documentos publicados.' );
$blog_id = 4;
if ( array_keys( $seo::sitemap_posts( $types ) ) !== array( 'post', 'page' ) ) throw new RuntimeException( 'Un sitio nuevo debe heredar la lista de red incluso con saltos de línea.' );
$blog_id = 3; $multisite = false;
$single = $seo::sitemap_posts( $types );
if ( array_keys( $single ) !== array( 'post', 'page', 'product' ) ) throw new RuntimeException( 'Una instalación individual con lista vacía debe recuperar sus tipos públicos.' );
$site_options[3]['sitemap_post_types'] = 'post,page';
if ( array_keys( $seo::sitemap_posts( $types ) ) !== array( 'post', 'page', 'product' ) ) throw new RuntimeException( 'El valor predeterminado anterior debe incluir productos públicos.' );
$site_options[3]['sitemap_post_types'] = 'page';
if ( array_keys( $seo::sitemap_posts( $types ) ) !== array( 'page' ) ) throw new RuntimeException( 'Una selección explícita debe limitar los tipos indexados.' );
if ( $seo::sitemap_enabled( false ) ) throw new RuntimeException( 'SEO no puede activar el sitemap de un sitio privado.' );
$site_options[3]['sitemap_enabled'] = 0;
if ( $seo::sitemap_enabled( true ) ) throw new RuntimeException( 'El interruptor del sitemap debe desactivarlo.' );
$site_options[3]['enable_seo'] = 0;
$seo::init();
foreach ( array( 'wp_sitemaps_enabled', 'wp_sitemaps_add_provider', 'wp_sitemaps_post_types', 'wp_sitemaps_posts_query_args' ) as $hook ) if ( ! isset( $filters[ $hook ] ) ) throw new RuntimeException( 'Los filtros de sitemap deben seguir activos aunque SEO esté desactivado: ' . $hook );
echo "Sitemap: páginas, noindex, herencia y sitios independientes correctos.\n";
