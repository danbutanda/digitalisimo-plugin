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
	if ( is_multisite() ) { $site = wpmu_create_blog( 'playground.test', '/sub/', 'Sub', 1 ); digi_check( ! is_wp_error( $site ), 'Se crea el sitio /sub/' ); }
	return;
}
require '/wordpress/wp-load.php';
if ( $blog > 1 ) switch_to_blog( $blog );
wp_set_current_user( 1 );
global $wpdb;

if ( 'seed' === $step ) {
	// Datos sintéticos de Rank Math: opciones, metas, términos y su tabla de redirecciones.
	update_option( 'permalink_structure', '/%postname%/' ); // Con enlaces simples un cambio de slug no cambia la URL.
	flush_rewrite_rules( false );
	update_option( 'rank-math-options-titles', array( 'title_separator' => '-', 'knowledgegraph_type' => 'company', 'knowledgegraph_name' => 'Acme RM', 'pt_post_title' => '%title% %sep% %sitename% %sep% %currentyear%', 'noindex_search' => 'on', 'robots_global' => array( 'index' ), 'author_custom_robots' => 'on', 'author_robots' => array( 'noindex' ), 'social_url_facebook' => 'https://facebook.com/acme' ) );
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
	update_option( 'digi_probe_ids', array( 'post' => $post, 'edited' => $edited, 'term' => $term['term_id'] ) );
	digi_check( 6 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), 'Semilla de Rank Math creada' );
	return;
}

$ids = get_option( 'digi_probe_ids', array() );

if ( 'import' === $step ) {
	do_action( 'admin_init' );
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
	digi_check( '%title% %sep% %sitename%' === ( $o['seo_title_post'] ?? '' ), 'La plantilla pierde %currentyear% y su separador' );
	digi_check( 'https://facebook.com/acme' === ( $o['seo_social_profiles']['facebook'] ?? '' ), 'Perfil de Facebook importado' );
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

if ( 'screens' === $step ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	do_action( 'admin_init' );
	foreach ( array( 'digitalisimo-seo-redirections', 'digitalisimo-seo-monitor404', 'digitalisimo-seo-import', 'digitalisimo-seo-indexing', 'digitalisimo-seo-general', 'digitalisimo-seo-sitemap' ) as $page ) {
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
	foreach ( array( 'seo_redirect_enabled', 'seo_404_enabled', 'seo_verify_google', 'seo_noindex_paginated', 'seo_max_snippet', 'seo_sitemap_images' ) as $key ) digi_check( false !== strpos( $html, 'name="digitalisimo_network[' . $key . ']"' ), 'La red pinta ' . $key );
	digi_check( false === strpos( $html, 'Heredar de la red' ), 'La red no muestra Heredar de la red' );
	digi_check( false !== strpos( $html, 'digitalisimo_seo_rankmath_import_network' ) && false !== strpos( $html, 'digitalisimo_rankmath_nonce' ), 'La red ofrece importar en todos los sitios con su nonce propio' );
	// Guardado de red: mismas reglas que el sitio.
	$_POST = array( '_wpnonce' => wp_create_nonce( 'digitalisimo_network_seo' ), '_wp_http_referer' => '/wp-admin/network/', 'digitalisimo_network' => array( 'seo_verify_bing' => '<meta name="msvalidate.01" content="NET-1">', 'seo_max_snippet' => 'abc', 'seo_404_limit' => '5', 'seo_redirect_fallback_url' => 'javascript:alert(1)', 'seo_verify_custom' => "x|<b>\nfacebook-domain-verification|fb1", 'seo_noindex_paginated' => '1' ) );
	$_REQUEST = $_POST;
	add_filter( 'wp_redirect', function() { throw new Exception( 'redirect' ); } );
	try { Digitalisimo_Integrations_SEO_Suite::save_network(); } catch ( Exception $e ) {}
	$net = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
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
