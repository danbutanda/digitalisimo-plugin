<?php
/** RM1: el importador de Rank Math traduce sus datos sin pisar lo editado ni aceptar objetos serializados. */
define( 'ABSPATH', __DIR__ );
function is_serialized( $data ) { return is_string( $data ) && preg_match( '/^(a|O|s|i|b|d|N):/', trim( $data ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function add_action() {}
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-variables.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-rankmath-import.php';
class Digitalisimo_Integrations_SEO_Suite { public static function sanitize_type_templates( $v ) { return $v; } }
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$i   = Digitalisimo_Integrations_RankMath_Import::class;
$ctx = array( 'sitedesc' => 'Lema', 'org_name' => 'Acme', 'keyword_max' => 3 );

// Plantillas: equivalentes, alias y variables sin destino.
check( array( '%title% %sep% %sitename%', array() ) === $i::translate_template( '%title% %sep% %sitename%', $ctx ), 'Las variables comunes se conservan.' );
check( array( '%description% · %sitedesc% %category% %date(Y)%', array() ) === $i::translate_template( '%term_description% · %sitedesc% %primary_taxonomy_terms% %date(Y)%', $ctx ), 'Los alias se traducen y las variables propias se conservan, también con argumento.' );
check( array( '%title%', array( 'wc_price' ) ) === $i::translate_template( '%title% %sep% %wc_price%', $ctx ), 'Una variable sin destino se quita junto con su separador.' );
check( array( '%title%', array( 'title' ) ) === $i::translate_template( '%title% %title(x)%', $ctx ), 'Una variable que no admite argumento se quita si lo trae.' );

// Contenidos.
$rm = array(
	'rank_math_title'                => '%title% %sep% %sitename%',
	'rank_math_description'          => 'Descripción propia',
	'rank_math_focus_keyword'        => 'uno, dos,tres,cuatro',
	'rank_math_canonical_url'        => 'https://sitio.test/canonica/',
	'rank_math_robots'               => serialize( array( 'noindex', 'nofollow', 'noarchive' ) ),
	'rank_math_advanced_robots'      => serialize( array( 'max-snippet' => '50', 'max-video-preview' => '-1', 'max-image-preview' => 'standard' ) ),
	'rank_math_pillar_content'       => 'on',
	'rank_math_facebook_title'       => 'Social',
	'rank_math_facebook_image'       => 'https://sitio.test/a.jpg',
	'rank_math_twitter_use_facebook' => 'off',
	'rank_math_twitter_title'        => 'Otro',
	'rank_math_breadcrumb_title'     => 'Miga',
	'rank_math_primary_category'     => '12',
	'rank_math_schema_Recipe'        => serialize( array( '@type' => 'Recipe' ) ),
	'rank_math_schema_BlogPosting'   => serialize( array( '@type' => 'BlogPosting', 'metadata' => array( 'isPrimary' => true ) ) ),
	'_rank_math_gtin_code'           => '0123',
);
foreach ( $rm as $key => $value ) $rm[ $key ] = $i::value( $value );
$post = $i::map_post( $rm, array(), $ctx );
$set  = $post['set'];
check( '%title% %sep% %sitename%' === $set['title'] && 'Descripción propia' === $set['description'], 'Título y descripción se importan.' );
check( 'uno,dos,tres' === $set['keywords'], 'Las keywords respetan el máximo del sitio.' );
check( '1' === $set['noindex'] && '1' === $set['nofollow'] && '1' === $set['noarchive'] && ! isset( $set['nosnippet'] ), 'Los robots se traducen uno a uno.' );
check( '50' === $set['max_snippet'] && ! isset( $set['max_video_preview'] ), 'Un límite -1 de Rank Math es su valor por defecto y no se importa.' );
check( 'on' === $set['pillar'] && 'Social' === $set['social_title'] && 'https://sitio.test/a.jpg' === $set['social_image'] && 'Miga' === $set['breadcrumb_title'], 'Pilar, social y miga se importan.' );
check( '12' === $set['primary_category'], 'La categoría principal se guarda por taxonomía.' );
check( 'Article' === $set['schema_type'], 'El schema principal gana y BlogPosting se lleva a Artículo.' );
$news = $i::map_post( array( 'rank_math_news_sitemap_robots' => 'noindex' ), array(), $ctx );
check( '1' === $news['set']['news_exclude'], 'La exclusión de Google News se importa.' );
foreach ( array( 'twitter_differs', 'image_preview', 'schema_other', 'gtin' ) as $note ) check( in_array( $note, $post['notes'], true ), 'Falta la nota ' . $note );

$edited = $i::map_post( $rm, array( 'title' => 'Mío', 'keywords' => 'propia', 'noindex' => '', 'schema_type' => 'WebPage' ), $ctx );
check( ! isset( $edited['set']['title'] ) && ! isset( $edited['set']['keywords'] ) && ! isset( $edited['set']['schema_type'] ), 'Nunca se pisa lo editado.' );
check( ! isset( $edited['set']['noindex'] ) && ! isset( $edited['set']['nofollow'] ), 'Una meta de robots ya guardada, aunque esté vacía, es una decisión del sitio.' );
$empty = $i::map_post( $rm, array( 'title' => '' ), $ctx );
check( isset( $empty['set']['title'] ), 'Un título vacío sí se rellena.' );

$gtin = $i::map_post( array( '_rank_math_gtin_code' => '4006-3813-3393-1' ), array(), $ctx );
check( '4006381333931' === $gtin['set']['_gtin'] && ! in_array( 'gtin', $gtin['notes'], true ), 'Un GTIN válido pasa a WooCommerce sin guiones.' );

// Términos.
$term = $i::map_term( array( 'rank_math_title' => '%term% %sep% %sitename%', 'rank_math_robots' => array( 'noindex' ), 'rank_math_canonical_url' => 'https://x.test/' ), array(), $ctx );
check( '%term% %sep% %sitename%' === $term['set']['title'] && '1' === $term['set']['noindex'] && in_array( 'term_canonical', $term['notes'], true ), 'Los términos se importan e informan lo que no tiene destino.' );

// Ajustes del sitio.
$defaults = array( 'seo_separator' => '|', 'noindex_authors' => 1, 'seo_title_post' => '%title% %sep% %sitename%', 'seo_organization_name' => '', 'seo_breadcrumbs' => 1 );
$site     = array( 'seo_separator' => '|', 'seo_organization_name' => 'Ya editado', 'seo_title_post' => '%title% %sep% %sitename%' );
$rmo = array(
	'titles'  => array( 'title_separator' => '-', 'knowledgegraph_type' => 'company', 'knowledgegraph_name' => 'Rank', 'robots_global' => array( 'index' ), 'author_custom_robots' => 'off', 'author_robots' => array( 'noindex' ), 'pt_post_title' => '%title% %sep% %wc_price%', 'pt_product_title' => '%title% %sep% %wc_price%', 'pt_servicio_description' => 'Servicio %title%', 'pt_post_default_rich_snippet' => 'article', 'pt_post_default_article_type' => 'NewsArticle', 'pt_servicio_default_rich_snippet' => 'service', 'pt_receta_default_rich_snippet' => 'recipe', 'pt_curso_default_rich_snippet' => 'off', 'pt_page_default_rich_snippet' => 'article', 'local_address' => array( 'streetAddress' => 'Calle 1', 'addressCountry' => 'mx' ), 'geo' => '19.43, -99.13', 'opening_hours' => array( array( 'day' => 'Monday', 'time' => '09:00-17:00' ) ), 'social_url_facebook' => 'https://facebook.com/acme' ),
	'general' => array( 'product_brand' => 'pa_marca', 'breadcrumbs_separator' => '»', 'breadcrumbs_archive_format' => 'Archives for %s', 'breadcrumbs_search_format' => 'Buscaste %s', 'breadcrumbs_remove_post_title' => 'on', 'breadcrumbs' => 'off', 'google_verify' => 'gcode', 'robots_txt_content' => "User-agent: *\nDisallow: /", 'redirections_fallback' => 'homepage', 'redirections_post_redirect' => 'on' ),
	'sitemap' => array( 'include_images' => 'on', 'exclude_posts' => '4, 5', 'news_sitemap_post_type' => array( 'post', 'noticia' ), 'news_sitemap_publication_name' => 'Diario Acme', 'news_sitemap_exclude_post_terms' => array( 'category' => array( 7, '9' ) ), 'html_sitemap' => 'on' ),
	'modules' => array( '404-monitor', 'news-sitemap' ),
);
$opt = $i::map_options( $rmo, $site, $defaults, $ctx );
$set = $opt['set'];
check( '-' === $set['seo_separator'] && 'Organization' === $set['seo_organization_type'], 'Un ajuste con el valor por defecto se importa.' );
check( ! isset( $set['seo_organization_name'] ) && in_array( 'option_kept', $opt['notes'], true ), 'Un ajuste personalizado se conserva.' );
check( 0 === $set['noindex_authors'], 'Sin robots propios de autor manda el robots global de Rank Math (index).' );
check( '%title%' === $set['seo_title_post'] && in_array( 'title_vars', $opt['notes'], true ), 'La plantilla se traduce e informa lo que quitó.' );
check( 'Calle 1' === $set['seo_local_address'] && 'mx' === $set['seo_local_country'] && '19.43' === $set['seo_local_latitude'] && '-99.13' === $set['seo_local_longitude'], 'Dirección y coordenadas se importan.' );
check( 0 === $set['seo_breadcrumbs'] && 'gcode' === $set['seo_verify_google'] && 1 === $set['seo_redirect_slug_watch'] && 1 === $set['seo_404_enabled'] && 1 === $set['seo_sitemap_images'] && '4,5' === $set['sitemap_exclude_ids'], 'Migas, verificación, vigilante, 404 y sitemap se importan.' );
check( ! isset( $set['seo_redirect_fallback_url'] ) && in_array( 'fallback_home', $opt['notes'], true ), 'Redirigir todos los 404 a la portada no se importa.' );
check( "User-agent: *\nDisallow: /" === $opt['robots_txt'] && in_array( 'robots_txt', $opt['notes'], true ), 'El robots.txt sólo se muestra para revisión.' );
check( array( 'https://facebook.com/acme' ) === $set['_profiles'], 'Los perfiles sociales pasan al clasificador.' );
check( '»' === $set['seo_breadcrumbs_separator'] && 'Buscaste %s' === $set['seo_breadcrumbs_search_format'] && 1 === $set['seo_breadcrumbs_hide_current'] && ! isset( $set['seo_breadcrumbs_archive_format'] ), 'Las migas se importan sin los textos por defecto en inglés.' );
check( array( 'product' => array( 'title' => '%title%' ), 'servicio' => array( 'description' => 'Servicio %title%' ) ) === $set['_type_templates'], 'Las plantillas de otros tipos se importan: ' . json_encode( $set['_type_templates'] ) );
check( array( 'post' => 'NewsArticle', 'servicio' => 'Service', 'curso' => 'none' ) === $set['_schema_defaults'] && in_array( 'schema_default', $opt['notes'], true ), 'Schema por defecto por tipo: ' . json_encode( $set['_schema_defaults'] ) );
check( 1 === $set['seo_sitemap_news_enabled'] && 'post,noticia' === $set['seo_sitemap_news_types'] && 'Diario Acme' === $set['seo_sitemap_news_name'] && '7,9' === $set['seo_sitemap_news_exclude_terms'] && in_array( 'html_sitemap', $opt['notes'], true ), 'Google News y el mapa HTML se importan o se informan.' );
check( 'pa_marca' === $set['seo_product_brand'], 'La taxonomía de marca se importa.' );
foreach ( array( 'local_hours', 'local_review' ) as $note ) check( in_array( $note, $opt['notes'], true ), 'Falta la nota ' . $note );
$author = $i::map_options( array( 'titles' => array( 'author_custom_robots' => 'on', 'author_robots' => array( 'noindex' ) ) ), array(), $defaults, $ctx );
check( 1 === $author['set']['noindex_authors'], 'Con robots propios de autor, manda su noindex.' );

// Redirecciones de la tabla de Rank Math.
$row = array( 'id' => 31, 'sources' => serialize( array( array( 'pattern' => 'vieja', 'comparison' => 'exact', 'ignore' => 'case' ), array( 'pattern' => '^x/(.*)', 'comparison' => 'regex' ) ) ), 'url_to' => 'https://sitio.test/nueva/', 'header_code' => '301', 'hits' => '7', 'status' => 'inactive', 'created' => '2025-01-02 03:04:05', 'updated' => '0000-00-00 00:00:00', 'last_accessed' => '0000-00-00 00:00:00' );
$map = $i::map_redirection( $row );
check( 'rankmath:31' === $map['import_key'] && 'inactive' === $map['status'] && 7 === $map['hits'] && 2 === count( $map['sources'] ) && 'case' === $map['sources'][0]['ignore'], 'Una redirección conserva orígenes, estado y visitas.' );
check( '2025-01-02 03:04:05' === $map['created'] && null === $map['last_accessed'], 'Las fechas vacías de MySQL no se copian.' );
check( null === $i::map_redirection( array( 'status' => 'trashed' ) + $row ), 'La papelera de Rank Math no se importa.' );
check( null === $i::map_redirection( array( 'sources' => 'O:8:"stdClass":1:{s:7:"pattern";s:1:"x";}' ) + $row ), 'Un objeto serializado nunca se instancia ni se importa.' );
check( null === $i::map_redirection( array( 'sources' => 'basura' ) + $row ), 'Unos orígenes ilegibles no se importan.' );

echo "Importador de Rank Math: plantillas, contenidos, términos, ajustes y redirecciones correctos.\n";
