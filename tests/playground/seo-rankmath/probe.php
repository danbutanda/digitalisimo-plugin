<?php
/**
 * Pasos de seo-rankmath.sh. Cada paso es una petición PHP propia en el blog DIGI_BLOG.
 * Escribe sus comprobaciones en result.jsonl y los avisos de PHP en fatal.log.
 */
$step = getenv( 'DIGI_STEP' );
$blog = (int) getenv( 'DIGI_BLOG' );
$dir  = '/wordpress/wp-content/probe';
set_error_handler( function( $no, $str, $file, $line ) use ( $dir ) {
	if ( ! ( error_reporting() & $no ) ) return false; // Silenciado con @ a propósito.
	if ( false !== strpos( $file, 'digitalisimo-seo' ) && ! ( $no & ( E_DEPRECATED | E_USER_DEPRECATED ) ) ) file_put_contents( $dir . '/fatal.log', "[$no] $str en $file:$line\n", FILE_APPEND );
	return false;
} );
register_shutdown_function( function() use ( $dir, $step ) {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( $dir . '/fatal.log', "[$step] {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND );
} );
function digi_check( $ok, $what ) {
	file_put_contents( '/wordpress/wp-content/probe/result.jsonl', json_encode( array( 'step' => getenv( 'DIGI_STEP' ), 'ok' => (bool) $ok, 'what' => $what ) ) . "\n", FILE_APPEND );
}
if ( 'setup' === $step || 'single-setup' === $step ) {
	require '/wordpress/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$result = activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php', '', is_multisite() );
	digi_check( ! is_wp_error( $result ), 'El plugin se activa' );
	if ( 'single-setup' === $step && file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) { $woo = activate_plugin( 'woocommerce/woocommerce.php' ); digi_check( ! is_wp_error( $woo ), 'WooCommerce se activa' ); }
	if ( is_multisite() ) { $site = wpmu_create_blog( 'playground.test', '/sub/', 'Sub', 1 ); digi_check( ! is_wp_error( $site ), 'Se crea el sitio /sub/' ); }
	return;
}
require '/wordpress/wp-load.php';
if ( $blog > 1 ) switch_to_blog( $blog );
wp_set_current_user( 1 );
global $wpdb;

if ( 'seed' === $step ) {
	// Datos sintéticos de Rank Math: opciones, metas, términos y su tabla de redirecciones.
	// Con enlaces simples un cambio de slug no cambia la URL. Como en Ajustes → Enlaces permanentes: estructura y reglas a la vez.
	$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules( false );
	update_option( 'rank-math-options-titles', array( 'title_separator' => '-', 'knowledgegraph_type' => 'company', 'knowledgegraph_name' => 'Acme RM', 'pt_post_title' => '%title% %sep% %sitename% %sep% %wc_price%', 'noindex_search' => 'on', 'robots_global' => array( 'index' ), 'author_custom_robots' => 'on', 'author_robots' => array( 'noindex' ), 'social_url_facebook' => 'https://facebook.com/acme' ) );
	update_option( 'rank-math-options-general', array( 'breadcrumbs' => 'on', 'google_verify' => '<meta name="google-site-verification" content="rm-google-123" />', 'robots_txt_content' => "User-agent: *\nDisallow: /privado/", 'redirections_post_redirect' => 'on', '404_monitor_limit' => 500 ) );
	update_option( 'rank-math-options-sitemap', array( 'include_images' => 'on' ) );
	update_option( 'rank_math_modules', array( 'redirections', '404-monitor' ) );
	$upload = wp_upload_dir();
	$post = wp_insert_post( array( 'post_title' => 'Entrada RM', 'post_name' => 'entrada-rm', 'post_status' => 'publish', 'post_content' => '<p>Texto</p><img src="' . $upload['baseurl'] . '/foto.jpg"><img src="https://externo.test/x.jpg">' ) );
	update_post_meta( $post, 'rank_math_title', '%title% %sep% %sitename%' );
	update_post_meta( $post, 'rank_math_description', 'Descripción desde Rank Math' );
	update_post_meta( $post, 'rank_math_focus_keyword', 'alfa,beta' );
	update_post_meta( $post, 'rank_math_robots', array( 'index', 'noarchive' ) );
	update_post_meta( $post, 'rank_math_advanced_robots', array( 'max-snippet' => '80', 'max-video-preview' => '-1', 'max-image-preview' => 'large' ) );
	update_post_meta( $post, 'rank_math_facebook_title', 'Título social RM' );
	update_post_meta( $post, 'rank_math_breadcrumb_title', 'Miga RM' );
	update_post_meta( $post, 'rank_math_schema_BlogPosting', array( '@type' => 'BlogPosting', 'metadata' => array( 'isPrimary' => true ) ) );
	$edited = wp_insert_post( array( 'post_title' => 'Editada', 'post_status' => 'publish' ) );
	update_post_meta( $edited, 'rank_math_title', 'No debe entrar' );
	update_post_meta( $edited, 'rank_math_robots', array( 'noindex' ) );
	update_post_meta( $edited, 'digitalisimo_seo_title', 'Título propio' );
	update_post_meta( $edited, 'digitalisimo_seo_noindex', '' );
	$term = wp_insert_term( 'Categoría RM', 'category' );
	update_term_meta( $term['term_id'], 'rank_math_title', '%term% %sep% %sitename%' );
	update_term_meta( $term['term_id'], 'rank_math_robots', array( 'noindex' ) );
	$table = $wpdb->prefix . 'rank_math_redirections';
	$wpdb->query( "CREATE TABLE {$table} ( id bigint(20) unsigned NOT NULL auto_increment, sources longtext NOT NULL, url_to text NOT NULL, header_code smallint(4) unsigned NOT NULL, hits bigint(20) unsigned NOT NULL default 0, status varchar(25) NOT NULL default 'active', created datetime NOT NULL default '0000-00-00 00:00:00', updated datetime NOT NULL default '0000-00-00 00:00:00', last_accessed datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY KEY (id) )" );
	$rows = array(
		array( serialize( array( array( 'pattern' => 'vieja-pagina', 'comparison' => 'exact' ) ) ), '/entrada-rm/', 301, 'active' ),
		array( serialize( array( array( 'pattern' => '^blog/(\d+)/(.*)$', 'comparison' => 'regex' ) ) ), '/articulos/$2/', 301, 'active' ),
		array( serialize( array( array( 'pattern' => 'borrada', 'comparison' => 'exact' ) ) ), '', 410, 'active' ),
		array( serialize( array( array( 'pattern' => 'papelera', 'comparison' => 'exact' ) ) ), '/x/', 301, 'trashed' ),
		array( 'O:8:"stdClass":0:{}', '/y/', 301, 'active' ),
		array( serialize( array( array( 'pattern' => '(roto', 'comparison' => 'regex' ) ) ), '/z/', 301, 'active' ),
	);
	foreach ( $rows as $row ) $wpdb->insert( $table, array( 'sources' => $row[0], 'url_to' => $row[1], 'header_code' => $row[2], 'status' => $row[3], 'hits' => 3, 'created' => '2025-01-01 00:00:00', 'updated' => '2025-01-01 00:00:00' ) );
	$product = 0;
	if ( class_exists( 'WC_Product_Simple' ) ) {
		$item = new WC_Product_Simple(); $item->set_name( 'Tenis Acme' ); $item->set_regular_price( '1200' ); $item->set_status( 'publish' ); $product = $item->save();
		update_post_meta( $product, '_rank_math_gtin_code', '4006381333931' );
		if ( taxonomy_exists( 'product_brand' ) ) wp_set_object_terms( $product, 'Acme Sport', 'product_brand' );
	}
	update_option( 'digi_probe_ids', array( 'post' => $post, 'edited' => $edited, 'term' => $term['term_id'], 'product' => $product ) );
	digi_check( 6 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'Semilla de Rank Math creada' );
	return;
}

$ids = get_option( 'digi_probe_ids', array() );

if ( 'import' === $step ) {
	Digitalisimo_Integrations_Settings::register_settings(); Digitalisimo_Integrations_Redirections::maybe_install(); Digitalisimo_Integrations_404_Monitor::maybe_install(); // Lo que hace admin_init para SEO, sin los ganchos de otros plugins.
	Digitalisimo_Integrations_RankMath_Import::start();
	Digitalisimo_Integrations_RankMath_Import::run_batch();
	$job = Digitalisimo_Integrations_RankMath_Import::job();
	digi_check( 'done' === $job['status'], 'La importación termina en un lote: ' . ( $job['status'] ?? '' ) . ' ' . ( $job['error'] ?? '' ) );
	digi_check( 3 === (int) ( $job['done']['redirections'] ?? 0 ), 'Se importan 3 redirecciones válidas: ' . json_encode( $job['done'] ) );
	digi_check( isset( $job['notes']['redirect_trashed'], $job['notes']['redirect_invalid'] ) && 2 === $job['notes']['redirect_invalid']['count'], 'La papelera, el objeto serializado y la regex rota quedan en el informe' );
	digi_check( isset( $job['notes']['robots_txt'] ) && false !== strpos( $job['robots_txt'], '/privado/' ), 'El robots.txt queda para revisión' );
	$p = $ids['post'];
	digi_check( '%title% %sep% %sitename%' === get_post_meta( $p, 'digitalisimo_seo_title', true ), 'Título importado' );
	digi_check( 'alfa,beta' === get_post_meta( $p, 'digitalisimo_seo_keywords', true ), 'Keywords importadas' );
	digi_check( '1' === get_post_meta( $p, 'digitalisimo_seo_noarchive', true ) && '' === get_post_meta( $p, 'digitalisimo_seo_noindex', true ), 'Robots importados (noarchive sí, noindex no)' );
	digi_check( '80' === get_post_meta( $p, 'digitalisimo_seo_max_snippet', true ), 'max-snippet importado' );
	digi_check( 'Article' === get_post_meta( $p, 'digitalisimo_seo_schema_type', true ), 'Tipo de schema importado' );
	digi_check( 'Título propio' === get_post_meta( $ids['edited'], 'digitalisimo_seo_title', true ) && '' === get_post_meta( $ids['edited'], 'digitalisimo_seo_noindex', true ), 'Lo editado no se pisa' );
	digi_check( '%term% %sep% %sitename%' === get_term_meta( $ids['term'], 'digitalisimo_seo_title', true ) && '1' === get_term_meta( $ids['term'], 'digitalisimo_seo_noindex', true ), 'Término importado' );
	$o = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	digi_check( '-' === ( $o['seo_separator'] ?? '' ) && 'Acme RM' === ( $o['seo_organization_name'] ?? '' ) && 'rm-google-123' === ( $o['seo_verify_google'] ?? '' ) && 1 === ( $o['seo_404_enabled'] ?? 0 ) && 500 === ( $o['seo_404_limit'] ?? 0 ) && 1 === ( $o['noindex_authors'] ?? null ), 'Ajustes importados: ' . json_encode( array_intersect_key( $o, array_flip( array( 'seo_separator', 'seo_organization_name', 'seo_verify_google', 'seo_404_enabled', 'seo_404_limit', 'noindex_authors' ) ) ) ) );
	digi_check( '%title% %sep% %sitename%' === ( $o['seo_title_post'] ?? '' ), 'La plantilla pierde la variable sin destino y su separador' );
	digi_check( 'https://facebook.com/acme' === ( $o['seo_social_profiles']['facebook'] ?? '' ), 'Perfil de Facebook importado' );
	if ( ! empty( $ids['product'] ) ) digi_check( '4006381333931' === wc_get_product( $ids['product'] )->get_global_unique_id() && 1 === (int) ( $job['done']['gtin'] ?? 0 ), 'El GTIN de Rank Math pasa al campo de WooCommerce' );
	if ( is_multisite() ) { $inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() ); digi_check( isset( $inherit['seo_separator'] ) && 0 === $inherit['seo_separator'], 'En la red, lo importado deja de heredar' ); }
	// Repetir no duplica.
	Digitalisimo_Integrations_RankMath_Import::start();
	Digitalisimo_Integrations_RankMath_Import::run_batch();
	$again = Digitalisimo_Integrations_RankMath_Import::job();
	digi_check( 0 === (int) ( $again['done']['redirections'] ?? 0 ) && 3 === (int) ( $again['notes']['redirect_existing']['count'] ?? 0 ), 'Volver a importar no duplica redirecciones' );
	digi_check( 3 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Digitalisimo_Integrations_Redirections::table() ), 'Tres reglas en la tabla propia' );
	return;
}

if ( 'front' === $step ) {
	$captured = array();
	add_filter( 'wp_redirect', function( $location, $status ) use ( &$captured ) { $captured[] = array( $location, $status ); return false; }, 10, 2 );
	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	foreach ( array( 'vieja-pagina/' => '/entrada-rm/', 'blog/2024/hola?utm=1' => '/articulos/hola/?utm=1' ) as $path => $expected ) {
		$captured = array();
		$_SERVER['REQUEST_URI'] = $home_path . $path;
		Digitalisimo_Integrations_Redirections::maybe_redirect();
		digi_check( isset( $captured[0] ) && home_url( $expected ) === $captured[0][0] && 301 === $captured[0][1], 'Redirige ' . $path . ' → ' . json_encode( $captured ) );
	}
	$_SERVER['REQUEST_URI'] = $home_path . 'borrada';
	$GLOBALS['wp_query'] = new WP_Query();
	Digitalisimo_Integrations_Redirections::maybe_redirect();
	digi_check( $GLOBALS['wp_query']->is_404(), '410 marca la petición como no encontrada' );
	$hits = (int) $wpdb->get_var( 'SELECT SUM(hits) FROM ' . Digitalisimo_Integrations_Redirections::table() );
	digi_check( $hits >= 12, 'Las visitas se suman a las importadas: ' . $hits );
	// Vigilante: cambiar el enlace crea una 301 y volver al anterior no deja un bucle.
	wp_update_post( array( 'ID' => $ids['post'], 'post_name' => 'entrada-nueva' ) );
	$index = get_option( Digitalisimo_Integrations_Redirections::INDEX );
	digi_check( isset( $index['exact']['entrada-rm'] ), 'Cambiar el slug crea la regla del enlace anterior' );
	wp_update_post( array( 'ID' => $ids['post'], 'post_name' => 'entrada-rm' ) );
	$index = get_option( Digitalisimo_Integrations_Redirections::INDEX );
	digi_check( ! isset( $index['exact']['entrada-rm'] ) && isset( $index['exact']['entrada-nueva'] ), 'Volver al slug anterior desactiva la regla que lo redirigía' );
	// Sitemap con imágenes propias.
	ob_start(); Digitalisimo_Integrations_Sitemap::urls_xml( array( 'post' ) ); $xml = ob_get_clean();
	digi_check( false !== strpos( $xml, 'xmlns:image=' ) && false !== strpos( $xml, '/foto.jpg</image:loc>' ) && false === strpos( $xml, 'externo.test' ), 'El sitemap publica las imágenes propias' );
	digi_check( false !== @simplexml_load_string( $xml ), 'El sitemap sigue siendo XML válido' );
	// Robots de la entrada importada.
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $ids['post'] ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; $GLOBALS['wp_query']->the_post();
	ob_start(); wp_robots(); $robots = ob_get_clean();
	digi_check( false !== strpos( $robots, 'noarchive' ) && false !== strpos( $robots, 'max-snippet:80' ) && false === strpos( $robots, 'noindex' ), 'wp_robots: ' . trim( wp_strip_all_tags( $robots ) . $robots ) );
	ob_start(); Digitalisimo_Integrations_SEO_Suite::head(); $head = ob_get_clean();
	digi_check( false !== strpos( $head, 'Entrada RM - ' ), 'El título importado resuelve sus variables' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => 0 ) ); $GLOBALS['wp_query']->is_home = true; $GLOBALS['wp_query']->is_front_page = true;
	add_filter( 'pre_option_show_on_front', function() { return 'posts'; } );
	ob_start(); Digitalisimo_Integrations_Verification::output(); $verify = ob_get_clean();
	digi_check( false !== strpos( $verify, 'content="rm-google-123"' ), 'La verificación se publica en la portada' );
	return;
}

if ( 'crumbs' === $step ) {
	// RM13: categoría principal con antecesores, página del blog y ajustes de visualización.
	$parent = wp_insert_term( 'Calzado', 'category' );
	$child  = wp_insert_term( 'Tenis', 'category', array( 'parent' => $parent['term_id'] ) );
	$other  = wp_insert_term( 'Ofertas', 'category' );
	$blog   = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Blog', 'post_status' => 'publish' ) );
	$front  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Portada', 'post_status' => 'publish' ) );
	update_option( 'show_on_front', 'page' ); update_option( 'page_for_posts', $blog ); update_option( 'page_on_front', $front );
	$post = wp_insert_post( array( 'post_title' => 'Tenis rojos', 'post_status' => 'publish', 'post_category' => array( $other['term_id'], $child['term_id'] ) ) );
	update_post_meta( $post, 'digitalisimo_seo_primary_category', (string) $child['term_id'] );
	update_post_meta( $post, 'digitalisimo_seo_breadcrumb_title', 'Rojos' );
	$names = wp_list_pluck( Digitalisimo_Integrations_Breadcrumbs::for_post( get_post( $post ) ), 'name' );
	digi_check( array( 'Inicio', 'Blog', 'Calzado', 'Tenis', 'Rojos' ) === $names, 'Ruta de una entrada con categoría principal: ' . implode( ' › ', $names ) );
	digi_check( 'Tenis' === Digitalisimo_Integrations_SEO_Variables::replace( '%category%', array( 'id' => $post ) ), '%category% usa la categoría principal' );
	delete_post_meta( $post, 'digitalisimo_seo_primary_category' );
	digi_check( in_array( Digitalisimo_Integrations_SEO_Variables::replace( '%category%', array( 'id' => $post ) ), array( 'Ofertas', 'Tenis' ), true ), 'Sin elegir, %category% usa la primera asignada' );
	update_post_meta( $post, 'digitalisimo_seo_primary_category', (string) $child['term_id'] );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $post ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$html = do_shortcode( '[digitalisimo_breadcrumbs]' );
	digi_check( false !== strpos( $html, '<span aria-current="page">Rojos</span>' ) && 4 === substr_count( $html, '<a ' ), 'El shortcode pinta la misma ruta: ' . wp_strip_all_tags( $html ) );
	ob_start(); Digitalisimo_Integrations_Schema_Graph::head(); $graph = ob_get_clean();
	digi_check( false !== strpos( $graph, '"BreadcrumbList"' ) && false !== strpos( $graph, '"name":"Calzado"' ), 'El BreadcrumbList del schema incluye la categoría' );
	$o = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	update_option( Digitalisimo_Integrations_Settings::OPTION, array_merge( $o, array( 'seo_breadcrumbs' => 1, 'seo_breadcrumbs_separator' => '/', 'seo_breadcrumbs_show_home' => 0, 'seo_breadcrumbs_hide_current' => 1, 'seo_breadcrumbs_blog_page' => 0, 'seo_breadcrumbs_prefix' => 'Estás en:' ) ) );
	if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', array_fill_keys( array( 'seo_breadcrumbs', 'seo_breadcrumbs_separator', 'seo_breadcrumbs_show_home', 'seo_breadcrumbs_hide_current', 'seo_breadcrumbs_blog_page', 'seo_breadcrumbs_prefix' ), 0 ) + (array) get_option( 'digitalisimo_seo_network_inherit', array() ) );
	$html = do_shortcode( '[digitalisimo_breadcrumbs]' );
	digi_check( 'Estás en: Calzado / Tenis' === trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ), 'Ajustes de visualización: ' . wp_strip_all_tags( $html ) );
	$GLOBALS['wp_query'] = new WP_Query( array( 'cat' => $child['term_id'] ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	digi_check( array( 'Inicio', 'Calzado', 'Tenis' ) === wp_list_pluck( Digitalisimo_Integrations_Breadcrumbs::trail(), 'name' ), 'Ruta de una categoría con su antecesor' );
	$GLOBALS['wp_query'] = new WP_Query( array( 's' => 'zapato' ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	digi_check( array( 'Inicio', 'Resultados de «zapato»' ) === wp_list_pluck( Digitalisimo_Integrations_Breadcrumbs::trail(), 'name' ), 'Ruta de una búsqueda' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => 999999 ) ); $GLOBALS['wp_query']->set_404(); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	digi_check( array( 'Inicio', 'Página no encontrada' ) === wp_list_pluck( Digitalisimo_Integrations_Breadcrumbs::trail(), 'name' ), 'Ruta de un 404' );
	update_option( 'show_on_front', 'posts' ); delete_option( 'page_for_posts' ); delete_option( 'page_on_front' );
	// RM10: plantilla de un tipo propio y variables en el título de un término.
	register_post_type( 'servicio', array( 'public' => true, 'label' => 'Servicios' ) );
	$o = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	update_option( Digitalisimo_Integrations_Settings::OPTION, array_merge( $o, array( 'seo_type_templates' => array( 'servicio' => array( 'title' => 'Servicio: %title% %sep% %sitename% %wc_price%' ) ) ) ) );
	if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', array( 'seo_type_templates' => 0 ) + (array) get_option( 'digitalisimo_seo_network_inherit', array() ) );
	$service = wp_insert_post( array( 'post_type' => 'servicio', 'post_title' => 'Diseño web', 'post_status' => 'publish' ) );
	$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'servicio', 'p' => $service ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; $GLOBALS['wp_query']->the_post();
	$title = Digitalisimo_Integrations_SEO_Suite::title( '' );
	digi_check( 0 === strpos( $title, 'Servicio: Diseño web ' . Digitalisimo_Integrations_SEO_Resolver::option( 'seo_separator' ) . ' ' ) && false === strpos( $title, '%' ), 'Plantilla del tipo propio y variable desconocida quitada: ' . $title );
	return;
}

if ( 'schema' === $step ) {
	// RM8: entidad por defecto, sección y keywords reales, marca del producto y ruta de una categoría.
	$o = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	update_option( Digitalisimo_Integrations_Settings::OPTION, array_merge( $o, array( 'seo_schema_type_defaults' => array( 'post' => 'NewsArticle' ) ) ) );
	if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', array( 'seo_schema_type_defaults' => 0 ) + (array) get_option( 'digitalisimo_seo_network_inherit', array() ) );
	$cat  = get_term_by( 'name', 'Tenis', 'category' );
	$post = wp_insert_post( array( 'post_title' => 'Noticia', 'post_status' => 'publish', 'post_category' => array( $cat->term_id ) ) );
	update_post_meta( $post, 'digitalisimo_seo_keywords', 'tenis,ofertas' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $post ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	ob_start(); Digitalisimo_Integrations_Schema_Graph::head(); $graph = ob_get_clean();
	digi_check( false !== strpos( $graph, '"@type":"NewsArticle"' ) && false !== strpos( $graph, '"articleSection":"Tenis"' ) && false !== strpos( $graph, '"keywords":"tenis, ofertas"' ), 'NewsArticle por defecto con sección y keywords' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'cat' => $cat->term_id ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	ob_start(); Digitalisimo_Integrations_Schema_Graph::head(); $graph = ob_get_clean();
	digi_check( false !== strpos( $graph, '"name":"Calzado"' ) && false !== strpos( $graph, '"BreadcrumbList"' ), 'El BreadcrumbList de una categoría incluye su antecesor' );
	if ( ! empty( $ids['product'] ) ) {
		$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'product', 'p' => $ids['product'] ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		ob_start(); Digitalisimo_Integrations_Schema_Graph::head(); $graph = ob_get_clean();
		digi_check( false !== strpos( $graph, '"brand":{"@type":"Brand","name":"Acme Sport"}' ), 'El producto publica su marca' );
		WC()->structured_data->generate_product_data( wc_get_product( $ids['product'] ) );
		$woo = WC()->structured_data->get_data();
		digi_check( false !== strpos( wp_json_encode( $woo ), '4006381333931' ), 'WooCommerce publica el GTIN importado' );
	}
	return;
}

if ( 'news' === $step ) {
	// RM4: sitemap de noticias, Googlebot-News y mapa del sitio en HTML.
	$o = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	$excluded = wp_insert_term( 'Opinión', 'category' );
	update_option( Digitalisimo_Integrations_Settings::OPTION, array_merge( $o, array( 'seo_sitemap_news_enabled' => 1, 'seo_sitemap_news_name' => 'Diario Acme', 'seo_sitemap_news_exclude_terms' => (string) $excluded['term_id'] ) ) );
	if ( is_multisite() ) update_option( 'digitalisimo_seo_network_inherit', array_fill_keys( array( 'seo_sitemap_news_enabled', 'seo_sitemap_news_name', 'seo_sitemap_news_exclude_terms' ), 0 ) + (array) get_option( 'digitalisimo_seo_network_inherit', array() ) );
	$fresh  = wp_insert_post( array( 'post_title' => 'Noticia fresca & <b>nueva</b>', 'post_status' => 'publish' ) );
	$old    = wp_insert_post( array( 'post_title' => 'Noticia vieja', 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 4 * DAY_IN_SECONDS ) ) );
	$hidden = wp_insert_post( array( 'post_title' => 'Noticia excluida', 'post_status' => 'publish' ) );
	update_post_meta( $hidden, 'digitalisimo_seo_news_exclude', '1' );
	$opinion = wp_insert_post( array( 'post_title' => 'Columna de opinión', 'post_status' => 'publish', 'post_category' => array( $excluded['term_id'] ) ) );
	ob_start(); Digitalisimo_Integrations_Sitemap::news_xml(); $xml = ob_get_clean();
	digi_check( false !== @simplexml_load_string( $xml ), 'El sitemap de noticias es XML válido' );
	digi_check( false !== strpos( $xml, get_permalink( $fresh ) ) && false === strpos( $xml, get_permalink( $old ) ) && false === strpos( $xml, get_permalink( $hidden ) ) && false === strpos( $xml, get_permalink( $opinion ) ), 'Sólo lo reciente, sin excluidos ni categorías excluidas' );
	digi_check( false !== strpos( $xml, '<news:name>Diario Acme</news:name>' ) && false !== strpos( $xml, '<news:language>en</news:language>' ) && false !== strpos( $xml, 'Noticia fresca &amp; nueva' ), 'Publicación, idioma y título escapado' );
	digi_check( false !== strpos( Digitalisimo_Integrations_Sitemap::robots( "User-agent: *\n", true ), 'Sitemap: ' . home_url( '/news-sitemap.xml' ) ), 'robots.txt anuncia el sitemap de noticias' );
	$path = (string) wp_parse_url( home_url( '/news-sitemap.xml' ), PHP_URL_PATH );
	$_SERVER['REQUEST_URI'] = $path;
	digi_check( array( 'type' => 'news', 'slash' => false ) === Digitalisimo_Integrations_Sitemap::requested(), 'La ruta del sitemap de noticias se reconoce' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $hidden ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	ob_start(); Digitalisimo_Integrations_Sitemap::news_robots(); $meta = ob_get_clean();
	digi_check( '<meta name="Googlebot-News" content="noindex">' === trim( $meta ), 'Un contenido excluido sólo se oculta a Googlebot-News' );
	$private = wp_insert_post( array( 'post_title' => 'Privada', 'post_status' => 'publish' ) );
	update_post_meta( $private, 'digitalisimo_seo_noindex', '1' );
	$html = do_shortcode( '[digitalisimo_sitemap type="post"]' );
	digi_check( false !== strpos( $html, 'digitalisimo-html-sitemap' ) && false !== strpos( $html, get_permalink( $old ) ) && false === strpos( $html, get_permalink( $private ) ) && false === strpos( $html, '<h2>Páginas' ), 'El mapa HTML lista sólo el tipo pedido, sin noindex' );
	return;
}

if ( 'links' === $step ) {
	// RM7: recuentos, huérfanas, pilar sin enlace, backlog en cron y sugerencias.
	Digitalisimo_Integrations_Links::install();
	$pillar = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Guía de SEO', 'post_status' => 'publish', 'post_content' => '<p>Guía completa.</p>' ) );
	$target = wp_insert_post( array( 'post_title' => 'Auditoría técnica', 'post_status' => 'publish', 'post_content' => '<p>Sin enlaces.</p>' ) );
	update_post_meta( $target, 'digitalisimo_seo_keywords', 'auditoría técnica' );
	$source = wp_insert_post( array( 'post_title' => 'Fuente', 'post_status' => 'publish', 'post_content' => '<p><a href="' . get_permalink( $target ) . '">uno</a> <a href="' . get_permalink( $target ) . '#x">otra vez</a> <a href="https://externo.test/">fuera</a> <a href="#ancla">ancla</a></p><p>Hablamos de auditoría técnica y de la guía de seo.</p>' ) );
	update_post_meta( $source, '_related_pillar', (string) $pillar );
	wp_update_post( array( 'ID' => $source ) ); // El guardado registra los enlaces.
	$c = Digitalisimo_Integrations_Links::counts( array( $source, $target, $pillar ) );
	digi_check( 2 === $c[ $source ]['internal'] && 1 === $c[ $source ]['external'] && 1 === $c[ $target ]['incoming'] && 0 === $c[ $pillar ]['incoming'], 'Recuentos al guardar: ' . json_encode( $c ) );
	$orphans = Digitalisimo_Integrations_Links::orphans();
	digi_check( in_array( $pillar, $orphans, true ) && ! in_array( $target, $orphans, true ), 'El pilar sin enlaces entrantes es huérfano; el enlazado no' );
	$gaps = Digitalisimo_Integrations_Links::pillar_gaps();
	digi_check( in_array( (string) $source, array_column( $gaps, 'post_id' ), true ), 'El secundario que no enlaza a su pilar se informa' );
	ob_start(); Digitalisimo_Integrations_Links::metabox_html( get_post( $source ) ); $box = ob_get_clean();
	digi_check( false !== strpos( $box, 'Guía de SEO' ) && false !== strpos( $box, 'tu contenido pilar' ) && false === strpos( $box, '«auditoría técnica»' ), 'El editor sugiere el pilar y no lo ya enlazado' );
	$third = wp_insert_post( array( 'post_title' => 'Tercera', 'post_status' => 'publish', 'post_content' => '<p>Una auditoría técnica cada año.</p>' ) );
	ob_start(); Digitalisimo_Integrations_Links::metabox_html( get_post( $third ) ); $box = ob_get_clean();
	digi_check( false !== strpos( $box, '«auditoría técnica» aparece en el texto' ), 'El editor sugiere el contenido cuya keyword aparece sin enlace' );
	wp_delete_post( $target, true );
	digi_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Digitalisimo_Integrations_Links::table() . ' WHERE target_post_id = %d', $target ) ), 'Borrar un contenido deja rotos (destino 0) los enlaces que lo apuntaban' );
	// Backlog: lo existente se analiza en cron.
	delete_post_meta_by_key( Digitalisimo_Integrations_Links::META ); delete_option( Digitalisimo_Integrations_Links::DONE );
	digi_check( count( Digitalisimo_Integrations_Links::backlog( 500 ) ) > 3, 'Lo existente queda pendiente' );
	Digitalisimo_Integrations_Links::run_batch();
	digi_check( array() === Digitalisimo_Integrations_Links::backlog( 1 ) && get_option( Digitalisimo_Integrations_Links::DONE ), 'El lote analiza lo pendiente y lo marca terminado' );
	$_GET['page'] = 'digitalisimo-seo-audit'; $_GET['audit'] = 'links';
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	ob_start(); Digitalisimo_Integrations_SEO_Audit_Hub::render(); $audit = ob_get_clean();
	digi_check( false !== strpos( $audit, 'Páginas huérfanas' ) && false !== strpos( $audit, 'Guía de SEO' ), 'Auditoría → Enlaces internos lista las huérfanas' );
	unset( $_GET['audit'] );
	return;
}

if ( 'analysis' === $step ) {
	// RM9: puntuación al publicar, caja del editor, columna y canibalización.
	$content = '<p>Una guía de marketing digital para pymes.</p>' . str_repeat( '<p>' . str_repeat( 'Consejos prácticos para vender más en internet. ', 12 ) . '</p>', 6 ) . '<h2>Marketing digital paso a paso</h2><p>Cierre.</p>';
	$a = wp_insert_post( array( 'post_title' => 'Marketing digital para pymes', 'post_name' => 'marketing-digital', 'post_status' => 'draft', 'post_content' => $content ) );
	update_post_meta( $a, 'digitalisimo_seo_keywords', 'marketing digital' );
	digi_check( '' === get_post_meta( $a, Digitalisimo_Integrations_Content_Analysis::SCORE, true ), 'Un borrador no guarda puntuación' );
	wp_update_post( array( 'ID' => $a, 'post_status' => 'publish' ) );
	$score = get_post_meta( $a, Digitalisimo_Integrations_Content_Analysis::SCORE, true );
	digi_check( '' !== $score && (int) $score >= 50, 'Publicar guarda la puntuación: ' . $score );
	$b = wp_insert_post( array( 'post_title' => 'Otra sobre Marketing Digital', 'post_status' => 'publish', 'post_content' => '<p>Corta.</p>' ) );
	update_post_meta( $b, 'digitalisimo_seo_keywords', 'Márketing digital, otra' );
	ob_start(); Digitalisimo_Integrations_Content_Analysis::metabox_html( get_post( $a ) ); $box = ob_get_clean();
	digi_check( false !== strpos( $box, 'Otra sobre Marketing Digital' ) && false !== strpos( $box, '/100' ), 'El editor avisa de otra página con la misma keyword (sin tildes ni mayúsculas)' );
	$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( get_post( $a ) );
	digi_check( 0 === strpos( $snippet['title'], 'Marketing digital para pymes' ), 'El análisis mide el título que se publica: ' . $snippet['title'] );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $a ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	digi_check( $snippet['title'] === Digitalisimo_Integrations_SEO_Suite::title( '' ), 'snippet_for() y el head dan el mismo título' );
	Digitalisimo_Integrations_Content_Analysis::columns();
	ob_start(); do_action( 'manage_post_posts_custom_column', 'digitalisimo_score', $a ); $cell = ob_get_clean();
	digi_check( false !== strpos( $cell, (string) $score . '/100' ), 'La columna muestra la puntuación guardada' );
	return;
}

if ( 'bulk' === $step ) {
	// RM14: edición rápida y masiva, filtro del listado y CSV de ida y vuelta.
	$a = wp_insert_post( array( 'post_title' => 'Rápida', 'post_status' => 'publish' ) );
	$b = wp_insert_post( array( 'post_title' => 'Masiva', 'post_status' => 'publish' ) );
	update_post_meta( $a, 'digitalisimo_seo_title', 'Viejo' );
	$_POST = array( 'digitalisimo_seo_quick_nonce' => wp_create_nonce( 'digitalisimo_seo_quick' ), 'digitalisimo_seo_quick' => array( 'seo_title' => 'Nuevo título', 'seo_description' => 'Nueva descripción', 'keywords' => 'uno, dos', 'noindex' => '1', 'nofollow' => '0' ) );
	wp_update_post( array( 'ID' => $a ) );
	digi_check( 'Nuevo título' === get_post_meta( $a, 'digitalisimo_seo_title', true ) && 'uno, dos' === get_post_meta( $a, 'digitalisimo_seo_keywords', true ) && '1' === get_post_meta( $a, 'digitalisimo_seo_noindex', true ) && '' === get_post_meta( $a, 'digitalisimo_seo_nofollow', true ), 'La edición rápida guarda título, keywords y robots' );
	$_POST['digitalisimo_seo_quick'] = array( 'seo_title' => '', 'seo_description' => 'Nueva descripción', 'keywords' => 'uno, dos', 'noindex' => '0', 'nofollow' => '0' );
	wp_update_post( array( 'ID' => $a ) );
	digi_check( '' === get_post_meta( $a, 'digitalisimo_seo_title', true ) && '' === get_post_meta( $a, 'digitalisimo_seo_noindex', true ), 'Vaciar un campo en la edición rápida lo vacía' );
	$_POST['digitalisimo_seo_quick_nonce'] = 'malo'; $_POST['digitalisimo_seo_quick']['seo_title'] = 'Intruso';
	wp_update_post( array( 'ID' => $a ) );
	digi_check( '' === get_post_meta( $a, 'digitalisimo_seo_title', true ), 'Sin nonce válido no se guarda nada' );
	$_POST = array(); $_REQUEST = array( 'digitalisimo_seo_bulk_nonce' => wp_create_nonce( 'digitalisimo_seo_bulk' ), 'digitalisimo_seo_bulk' => array( 'noindex' => '1', 'nofollow' => '' ) );
	foreach ( array( $a, $b ) as $id ) wp_update_post( array( 'ID' => $id ) );
	digi_check( '1' === get_post_meta( $a, 'digitalisimo_seo_noindex', true ) && '1' === get_post_meta( $b, 'digitalisimo_seo_noindex', true ) && '' === get_post_meta( $b, 'digitalisimo_seo_nofollow', true ), 'La edición masiva activa noindex y deja nofollow «sin cambios»' );
	$_REQUEST = array();
	$found = get_posts( array( 'post_type' => 'post', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => Digitalisimo_Integrations_SEO_Bulk::filter_meta( 'noindex' ) ) );
	digi_check( in_array( $a, $found, true ) && in_array( $b, $found, true ), 'El filtro «Con noindex» encuentra los dos' );
	// CSV: exportar, editar y volver a importar.
	$mem = fopen( 'php://memory', 'w+' ); Digitalisimo_Integrations_SEO_Bulk::write_csv( $mem ); rewind( $mem ); $csv = stream_get_contents( $mem ); fclose( $mem );
	digi_check( 0 === strpos( $csv, "\xEF\xBB\xBFid,type,status,url,seo_title" ) && false !== strpos( $csv, (string) $b . ',post,publish,' ), 'La exportación empieza con BOM y cabecera y lista los contenidos' );
	$rows = array( implode( ',', Digitalisimo_Integrations_SEO_Bulk::COLUMNS ), $b . ',post,publish,,"Título desde CSV",,,,0,,"\'=FORMULA",,', '999999,post,publish,,Fantasma,,,,,,,,', 'texto,,,,,,,,,,,,' );
	$file = trailingslashit( wp_upload_dir()['basedir'] ) . 'digitalisimo-seo/tmp/prueba-' . wp_generate_password( 8, false ) . '.csv';
	wp_mkdir_p( dirname( $file ) ); file_put_contents( $file, implode( "\n", $rows ) . "\n" );
	update_option( Digitalisimo_Integrations_SEO_Bulk::JOB, array( 'status' => 'running', 'file' => $file, 'offset' => 0, 'user' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => array(), 'tick' => time() ), false );
	Digitalisimo_Integrations_SEO_Bulk::run_batch();
	$job = get_option( Digitalisimo_Integrations_SEO_Bulk::JOB );
	digi_check( 'done' === $job['status'] && 1 === $job['updated'] && 2 === $job['skipped'] && ! file_exists( $file ), 'Importación: una fila aplicada, dos omitidas y el archivo borrado: ' . json_encode( $job ) );
	digi_check( 'Título desde CSV' === get_post_meta( $b, 'digitalisimo_seo_title', true ) && '' === get_post_meta( $b, 'digitalisimo_seo_noindex', true ) && '=FORMULA' === get_post_meta( $b, 'digitalisimo_seo_social_title', true ), 'La fila importada cambia lo indicado y conserva lo vacío' );
	$outside = WP_CONTENT_DIR . '/fuera.csv'; file_put_contents( $outside, "id\n1\n" );
	update_option( Digitalisimo_Integrations_SEO_Bulk::JOB, array( 'status' => 'running', 'file' => $outside, 'offset' => 0, 'user' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => array() ), false );
	Digitalisimo_Integrations_SEO_Bulk::run_batch();
	digi_check( 'error' === get_option( Digitalisimo_Integrations_SEO_Bulk::JOB )['status'] && file_exists( $outside ), 'Un archivo fuera de la carpeta de trabajo nunca se lee ni se borra' );
	unlink( $outside );
	return;
}

if ( 'screens' === $step ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	Digitalisimo_Integrations_Settings::register_settings(); Digitalisimo_Integrations_Redirections::maybe_install(); Digitalisimo_Integrations_404_Monitor::maybe_install(); // Lo que hace admin_init para SEO, sin los ganchos de otros plugins.
	foreach ( array( 'digitalisimo-seo-redirections', 'digitalisimo-seo-monitor404', 'digitalisimo-seo-import', 'digitalisimo-seo-indexing', 'digitalisimo-seo-general', 'digitalisimo-seo-sitemap', 'digitalisimo-seo-breadcrumbs', 'digitalisimo-seo-titles', 'digitalisimo-seo-schema' ) as $page ) {
		$_GET['page'] = $page;
		ob_start(); Digitalisimo_Integrations_SEO_Suite::page(); $html = ob_get_clean();
		digi_check( strlen( $html ) > 500 && false === stripos( $html, 'rankmath.com' ), 'Pantalla ' . $page );
	}
	$_GET['page'] = 'digitalisimo-seo-redirections';
	ob_start(); Digitalisimo_Integrations_SEO_Suite::page(); $html = ob_get_clean();
	digi_check( false !== strpos( $html, 'vieja-pagina' ) && false !== strpos( $html, 'name="sources"' ), 'El gestor lista las reglas y ofrece el formulario' );
	digi_check( 2 === substr_count( $html, 'name="digitalisimo_integrations_options[seo_redirect_enabled]"' ), 'La casilla de redirecciones se pinta una vez (oculta + casilla)' );
	digi_check( is_multisite() === ( false !== strpos( $html, 'Heredar de la red' ) ), 'Heredar de la red sólo en un sitio de una red' );
	// Guardar una regla desde el formulario (admin-post) y rechazar una regex rota.
	$_POST = array( 'sources' => "/^(roto\n", 'comparison' => 'regex', 'url_to' => '/a/', 'header_code' => 301, 'status' => 'active' );
	$saved = Digitalisimo_Integrations_Redirections::save( array( 'sources' => array( array( 'pattern' => '(roto', 'comparison' => 'regex' ) ), 'url_to' => '/a/' ) );
	digi_check( is_wp_error( $saved ), 'Una regex rota no se guarda' );
	$loop = Digitalisimo_Integrations_Redirections::save( array( 'sources' => array( array( 'pattern' => '/aqui/', 'comparison' => 'exact' ) ), 'url_to' => '/aqui/' ) );
	digi_check( is_wp_error( $loop ) && 'loop' === $loop->get_error_code(), 'Una regla hacia sí misma no se guarda' );
	return;
}

if ( 'network' === $step ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	set_current_screen( 'dashboard-network' );
	digi_check( is_network_admin(), 'Pantalla de red' );
	ob_start(); Digitalisimo_Integrations_SEO_Suite::network_page(); $html = ob_get_clean();
	foreach ( array( 'seo_redirect_enabled', 'seo_404_enabled', 'seo_verify_google', 'seo_noindex_paginated', 'seo_max_snippet', 'seo_sitemap_images', 'seo_breadcrumbs_separator', 'seo_breadcrumbs_taxonomy', 'seo_title_taxonomy', 'seo_product_brand', 'seo_sitemap_news_enabled', 'seo_sitemap_news_exclude_terms', 'seo_links_counter' ) as $key ) digi_check( false !== strpos( $html, 'name="digitalisimo_network[' . $key . ']"' ), 'La red pinta ' . $key );
	digi_check( false === strpos( $html, 'Heredar de la red' ), 'La red no muestra Heredar de la red' );
	digi_check( false !== strpos( $html, 'digitalisimo_seo_rankmath_import_network' ) && false !== strpos( $html, 'digitalisimo_rankmath_nonce' ), 'La red ofrece importar en todos los sitios con su nonce propio' );
	// Guardado de red: mismas reglas que el sitio.
	$_POST = array( '_wpnonce' => wp_create_nonce( 'digitalisimo_network_seo' ), '_wp_http_referer' => '/wp-admin/network/', 'digitalisimo_network' => array( 'seo_verify_bing' => '<meta name="msvalidate.01" content="NET-1">', 'seo_max_snippet' => 'abc', 'seo_404_limit' => '5', 'seo_redirect_fallback_url' => 'javascript:alert(1)', 'seo_verify_custom' => "x|<b>\nfacebook-domain-verification|fb1", 'seo_noindex_paginated' => '1', 'seo_breadcrumbs_prefix' => '<b>Ruta</b>', 'seo_type_templates' => array( 'servicio' => array( 'title' => '<i>%title%</i>' ), 'Mal tipo!' => 'x' ) ) );
	$_REQUEST = $_POST;
	add_filter( 'wp_redirect', function() { throw new Exception( 'redirect' ); } );
	try { Digitalisimo_Integrations_SEO_Suite::save_network(); } catch ( Exception $e ) {}
	$net = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
	digi_check( false !== strpos( $html, 'digitalisimo_network[seo_schema_type_defaults][post]' ), 'La red pinta la entidad por defecto de cada tipo' );
	digi_check( 'Ruta' === $net['seo_breadcrumbs_prefix'] && array( 'servicio' => array( 'title' => '%title%' ) ) === $net['seo_type_templates'], 'save_network sanea migas y plantillas por tipo: ' . json_encode( array( $net['seo_breadcrumbs_prefix'] ?? null, $net['seo_type_templates'] ?? null ) ) );
	digi_check( 'NET-1' === $net['seo_verify_bing'] && '' === $net['seo_max_snippet'] && 50 === $net['seo_404_limit'] && '' === $net['seo_redirect_fallback_url'] && 'facebook-domain-verification|fb1' === $net['seo_verify_custom'] && 1 === $net['seo_noindex_paginated'], 'save_network sanea las claves nuevas: ' . json_encode( array_intersect_key( $net, array_flip( array( 'seo_verify_bing', 'seo_max_snippet', 'seo_404_limit', 'seo_redirect_fallback_url', 'seo_verify_custom', 'seo_noindex_paginated' ) ) ) ) );
	return;
}

if ( 'inherit' === $step ) {
	// El sitio /sub/ nunca personalizó estas claves: hereda de la red.
	digi_check( 'NET-1' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_verify_bing' ), 'El sitio hereda la verificación de la red' );
	digi_check( 1 === (int) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_noindex_paginated' ), 'El sitio hereda noindex en paginados' );
	digi_check( 0 === (int) get_option( Digitalisimo_Integrations_Redirections::FLAG, 0 ), 'El sitio /sub/ no tiene reglas: el front no consulta nada' );
	digi_check( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'digitalisimo_redirections' ) ) ), 'Ninguna tabla se crea en un sitio sólo por visitarlo' );
	return;
}
