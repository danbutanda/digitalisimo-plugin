<?php
/**
 * Pasos de seo-analysis.sh. Cada paso es una petición PHP propia. Escribe sus comprobaciones en result.jsonl y los
 * avisos de PHP de SEO en fatal.log. El sitio reproduce los casos de la auditoría de digitalisimo.lat (2026-11-24).
 */
$step = getenv( 'DIGI_STEP' );
$dir  = '/wordpress/wp-content/probe';
set_error_handler( function( $no, $str, $file, $line ) use ( $dir ) {
	if ( ! ( error_reporting() & $no ) ) return false;
	if ( false !== strpos( $file, 'digitalisimo-seo' ) && ! ( $no & ( E_DEPRECATED | E_USER_DEPRECATED ) ) ) file_put_contents( $dir . '/fatal.log', "[$no] $str en $file:$line\n", FILE_APPEND );
	return false;
} );
register_shutdown_function( function() use ( $dir, $step ) {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( $dir . '/fatal.log', "[$step] {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND );
} );
function digi_check( $ok, $what ) {
	file_put_contents( '/wordpress/wp-content/probe/result.jsonl', json_encode( array( 'step' => getenv( 'DIGI_STEP' ), 'ok' => (bool) $ok, 'what' => $what ), JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
}
if ( 'render' === $step ) define( 'WP_ADMIN', true );
if ( 'setup' === $step ) {
	require '/wordpress/wp-load.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$result = activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php', '', is_multisite() );
	digi_check( ! is_wp_error( $result ), 'El plugin se activa' );
	if ( is_multisite() ) { $site = wpmu_create_blog( 'playground.test', '/sub/', 'Sub', 1 ); digi_check( ! is_wp_error( $site ), 'Se crea el sitio /sub/' ); }
	return;
}
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
// Una tienda: dos productos con la misma descripción del fabricante y sin enlaces escritos (sus enlaces son los de la
// tienda, las categorías y «relacionados», que la detección no ve).
if ( ! post_type_exists( 'product' ) ) register_post_type( 'product', array( 'public' => true, 'has_archive' => true, 'label' => 'Productos', 'rewrite' => array( 'slug' => 'producto' ) ) );
// Peticiones HTTP: se anotan; el dominio del canonical viejo no existe (como digital.digitalisimo.org en la auditoría).
$GLOBALS['digi_http'] = array();
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
	$GLOBALS['digi_http'][] = $url;
	if ( false !== strpos( $url, 'digital.digitalisimo.invalid' ) ) return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: digital.digitalisimo.invalid' );
	return $pre;
}, 10, 3 );
$A = 'Digitalisimo_Integrations_Site_Analysis';
$C = 'Digitalisimo_Integrations_SEO_Cleanup';
$ids_file = $dir . '/ids.json';
$ids      = file_exists( $ids_file ) ? (array) json_decode( file_get_contents( $ids_file ), true ) : array();

/** Pide una dirección como el front y devuelve la redirección (destino y código) o null. */
function digi_get( $path ) {
	$captured = null;
	$filter   = function( $location, $status ) use ( &$captured ) { $captured = array( $location, $status ); return false; };
	add_filter( 'wp_redirect', $filter, 10, 2 );
	$_SERVER['REQUEST_URI'] = $path;
	unset( $_SERVER['PATH_INFO'] );
	$_GET = array();
	$GLOBALS['wp_query'] = new WP_Query(); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['wp'] = new WP();
	$GLOBALS['wp']->main();
	remove_filter( 'wp_redirect', $filter, 10 );
	return $captured;
}
function digi_noindex( $path ) { digi_get( $path ); return ! empty( apply_filters( 'wp_robots', array() )['noindex'] ); }
function digi_sitemap() { ob_start(); Digitalisimo_Integrations_Sitemap::urls_xml( array( 'page', 'post' ) ); return ob_get_clean(); }
function digi_path( $id ) { return (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ); }

if ( 'seed' === $step ) {
	$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules( false );
	$base = 'Diseñamos páginas web profesionales para empresas que quieren vender más en internet. Cada sitio es rápido, seguro y fácil de administrar, con hosting incluido, certificado SSL, correo corporativo y soporte técnico por WhatsApp. Trabajamos con WordPress y DIGITALÍSIMO Elements para que puedas cambiar textos, fotos y precios sin depender de nadie. Antes de empezar revisamos tu competencia, tus palabras clave y lo que buscan tus clientes; después armamos la estructura, escribimos los textos y optimizamos cada página para Google. Entregamos el sitio con capacitación, manual en video y un mes de ajustes sin costo. También conectamos tu tienda en línea, tus formularios y tus redes sociales, medimos las visitas con analítica y te enviamos un reporte mensual con lo que funcionó y lo que conviene mejorar.';
	$text = function( $topic ) { $out = ''; foreach ( range( 1, 14 ) as $i ) $out .= ucfirst( $topic ) . ' punto ' . $i . ': ' . str_repeat( $topic . ' ' . $i . ' ', 3 ) . 'con ejemplos propios, número ' . ( $i * 7 ) . ', y una explicación distinta del ' . $topic . ' en el apartado ' . $i . '. '; return $out; };
	$page = function( $title, $slug, $content, $type = 'page' ) { return (int) wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) ); };
	$ids['a']     = $page( 'Páginas web', 'paginas-web', '<p>' . $base . ' Atendemos a todo México.</p>' );
	$ids['b']     = $page( 'Páginas web Torreón', 'paginas-web-torreon', '<p>' . $base . ' Atendemos en Torreón, Coahuila.</p>' );
	$ids['c']     = $page( 'Diseño de páginas web en Torreón', 'diseno-de-paginas-web-en-torreon', '<p>Diseño de páginas web en Torreón. ' . $base . '</p>' );
	// Una copia que el dueño ya sacó de Google: duplicado a propósito, no compite.
	$ids['d']     = $page( 'Páginas web (copia para anuncios)', 'paginas-web-anuncios', '<p>' . $base . ' Atendemos a todo México.</p>' );
	update_post_meta( $ids['d'], 'digitalisimo_seo_noindex', '1' );
	$ids['host']  = $page( 'Web hosting IA', 'web-hosting-ia', '<p>' . $text( 'hosting administrado' ) . '</p><p><a href="' . home_url( '/paginas-web/' ) . '">Páginas web</a></p>' );
	$ids['cont']  = $page( 'Contacto', 'contacto', '<p>' . $text( 'contacto directo' ) . '</p>' );
	$ids['que']   = $page( '¿Qué es una página web?', 'que-es-una-pagina-web', '<p>' . $text( 'definición de sitio' ) . '</p>' );
	$ids['bf']    = $page( 'Black Friday 2026', 'black-friday', '<p>' . $text( 'black friday inteligente' ) . '</p>' );
	$ids['home']  = $page( 'Inicio', 'inicio', '<p>' . $text( 'agencia digital' ) . '</p><p><a href="/paginas-web/">Páginas web</a> <a href="/que-es-una-pagina-web/">Qué es</a> <a href="/black-friday/">Black Friday</a></p>' );
	$cat = wp_insert_term( '[ubicacion_usuario] Noticias', 'category' );
	foreach ( array( 'n1' => 'china-prohibe-chips', 'n2' => 'blue-origin-viper', 'n3' => 'noticia-de-relleno' ) as $key => $slug ) {
		$ids[ $key ] = $page( 'Noticia ' . $key, $slug, '<p>' . $text( 'noticia ' . $key ) . '</p>', 'post' );
		if ( ! is_wp_error( $cat ) ) wp_set_post_categories( $ids[ $key ], array( (int) $cat['term_id'] ) );
	}
	$maker = 'Camiseta de algodón peinado de 180 gramos con costuras reforzadas, cuello redondo y corte clásico. Se lava a máquina en agua fría, no usar blanqueador, secar a la sombra y planchar a temperatura media por el revés. Tallas de la chica a la extra grande, colores firmes que no se destiñen con las lavadas y etiqueta que no pica. Ideal para uniformes, eventos, regalos corporativos y uso diario, con estampado opcional en serigrafía o vinil textil de alta duración. Empaque individual en bolsa compostable con guía de tallas impresa y garantía de satisfacción de treinta días desde la entrega.';
	$ids['p1'] = $page( 'Camiseta azul', 'camiseta-azul', '<p>' . $maker . '</p>', 'product' );
	$ids['p2'] = $page( 'Camiseta roja', 'camiseta-roja', '<p>' . $maker . '</p>', 'product' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ids['home'] );
	// B1: la miga de Inicio con los shortcodes de la auditoría y una variable que el sitio no conoce en un título SEO.
	$options = (array) get_option( 'digitalisimo_integrations_options', array() );
	$options['seo_breadcrumbs_home_label'] = '[descripcion_sitio] [contenido_pilar]';
	$options['seo_abilities_write'] = 1; // B5: el interruptor único de antes, encendido.
	// Como un sitio que se activó con la versión anterior: sus opciones guardadas no tienen las cuatro partes.
	foreach ( array( 'seo_abilities_write_meta', 'seo_abilities_write_schema', 'seo_abilities_write_index', 'seo_abilities_write_redirects' ) as $key ) unset( $options[ $key ] );
	update_option( 'digitalisimo_integrations_options', $options );
	digi_check( 1 === ( (array) get_option( 'digitalisimo_integrations_options' ) )['seo_abilities_write'], 'El interruptor único de antes queda guardado' );
	update_post_meta( $ids['host'], 'digitalisimo_seo_title', '%title% %sep% %seo_title%' );
	// B4: canonical a un dominio que ya no existe.
	update_post_meta( $ids['que'], 'digitalisimo_seo_canonical', 'https://digital.digitalisimo.invalid/paginas-web/' );
	// C1: la descripción para redes de El Buen Fin, con un plazo de hace 9 días; la página se editó hoy.
	$months = array( 1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
	$past   = time() - 9 * DAY_IN_SECONDS;
	update_post_meta( $ids['bf'], 'digitalisimo_seo_social_description', '| El Buen Fin · Termina el martes ' . gmdate( 'j', $past ) . ' de ' . $months[ (int) gmdate( 'n', $past ) ] . ' a las 23:59, hora del centro de México. Contrátalo' );
	// B3: el hosting se enlaza desde el menú y el contacto desde la plantilla del pie: no son huérfanas.
	$menu = wp_create_nav_menu( 'Principal' );
	wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $ids['host'], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	$footer = (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie', 'post_content' => '' ) );
	update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( array( array( 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Contacto', 'link' => array( 'url' => home_url( '/contacto/' ) ) ) ) ) ) ) );
	file_put_contents( $ids_file, json_encode( $ids ) );
	digi_check( count( array_filter( $ids ) ) === count( $ids ), 'Sitio sembrado: ' . count( $ids ) . ' contenidos' );
	return;
}

if ( 'baseline' === $step ) {
	// Sin pasar por el escritorio no hay análisis, y servir páginas no lo dispara ni hace peticiones.
	foreach ( array( 'a', 'b', 'c', 'que', 'bf', 'n1' ) as $key ) digi_check( null === digi_get( digi_path( $ids[ $key ] ) ), "Sin redirección en /$key antes de nada" );
	digi_check( ! digi_noindex( digi_path( $ids['b'] ) ), 'Sin noindex antes de nada' );
	digi_check( array() === $GLOBALS['digi_http'], 'Servir páginas no hace peticiones' );
	digi_check( '' === get_post_meta( $ids['a'], $A::META, true ) && array() === $A::results() && array() === $A::state(), 'Sin abrir el escritorio no se analiza nada (ni al servir ni al guardar)' );
	$sitemap = digi_sitemap();
	digi_check( false !== strpos( $sitemap, '/paginas-web-torreon/' ) && false !== strpos( $sitemap, '/china-prohibe-chips/' ), 'Todo está en el sitemap' );
	return;
}

if ( 'analyze' === $step ) {
	Digitalisimo_Integrations_Links::install();
	for ( $i = 0; $i < 10 && Digitalisimo_Integrations_Links::backlog( 1 ); $i++ ) Digitalisimo_Integrations_Links::run_batch();
	digi_check( ! Digitalisimo_Integrations_Links::backlog( 1 ), 'Enlaces internos analizados' );
	$A::restart();
	for ( $i = 0; $i < 20 && empty( $A::state()['done'] ); $i++ ) $A::run_batch();
	digi_check( ! empty( $A::state()['done'] ) && ! empty( $A::state()['dirty'] ), 'El repaso por lotes terminó' );
	$A::run_batch(); // El turno siguiente del cron reúne el resultado.
	digi_check( ! empty( $A::results()['at'] ) && empty( $A::state()['dirty'] ), 'Resultado reunido' );
	digi_check( array() === $GLOBALS['digi_http'], 'El repaso no hace peticiones (el canonical va en su propio cron)' );
	digi_check( (bool) wp_next_scheduled( $A::CANON_CRON ), 'La comprobación del canonical queda programada' );
	return;
}

if ( 'results' === $step ) {
	$r = $A::results();
	$site = array_map( function( $row ) { return $row[1]; }, (array) $r['b1_site'] );
	digi_check( in_array( '[descripcion_sitio]', $site, true ) && in_array( '[contenido_pilar]', $site, true ), 'B1: la miga de Inicio con shortcodes: ' . json_encode( $r['b1_site'], JSON_UNESCAPED_UNICODE ) );
	digi_check( isset( $r['b1_posts'][ $ids['host'] ] ) && '%seo_title%' === $r['b1_posts'][ $ids['host'] ][0][2], 'B1: la variable que no existe en el título SEO' );
	digi_check( 1 === count( (array) $r['b1_posts'] ), 'B1: ningún otro contenido marcado: ' . json_encode( array_keys( (array) $r['b1_posts'] ) ) );
	digi_check( in_array( '[ubicacion_usuario]', array_column( (array) $r['b1_terms'], 3 ), true ), 'B1: el shortcode en el nombre de la categoría' );
	$groups = (array) $r['clusters'];
	$products = array_values( array_filter( $groups, function( $g ) { return ! empty( $g['inform'] ); } ) );
	$pp = array( (int) $ids['p1'], (int) $ids['p2'] ); sort( $pp );
	digi_check( 1 === count( $products ) && $pp === $products[0]['ids'], 'B2: los productos con la misma descripción forman un grupo sólo informativo' );
	$groups = array_values( array_filter( $groups, function( $g ) { return empty( $g['inform'] ); } ) );
	digi_check( 1 === count( $groups ), 'B2: un solo grupo: ' . json_encode( array_map( function( $g ) { return $g['ids']; }, $groups ) ) );
	$group = $groups[0] ?? array( 'ids' => array(), 'keeper' => 0, 'keyword' => '', 'max' => 0 );
	$want  = array( $ids['a'], $ids['b'], $ids['c'] ); sort( $want );
	digi_check( $want === $group['ids'], 'B2: /paginas-web/, /paginas-web-torreon/ y /diseno-de-paginas-web-en-torreon/' );
	digi_check( $group['max'] >= 0.85, 'B2: casi iguales (' . round( $group['max'] * 100 ) . ' %)' );
	digi_check( (int) $ids['a'] === (int) $group['keeper'], 'B2: se propone conservar la que recibe enlaces (/paginas-web/)' );
	digi_check( 'páginas web' === $group['keyword'], 'B2: palabra en disputa «' . $group['keyword'] . '»' );
	digi_check( isset( $r['stale'][ $ids['bf'] ] ) && 'date' === $r['stale'][ $ids['bf'] ][0][0], 'C1: /black-friday/ con la descripción de El Buen Fin' );
	digi_check( 1 === count( (array) $r['stale'] ), 'C1: ninguna otra' );
	digi_check( isset( $r['canonical'][ $ids['que'] ] ) && $r['canonical'][ $ids['que'] ]['offsite'], 'B4: /que-es-una-pagina-web/ con canonical a otro dominio' );
	digi_check( 'offsite' === ( $A::canonical_issues()[ $ids['que'] ]['kind'] ?? '' ), 'B4: antes de comprobarlo, aviso de otro dominio' );
	$linked = array_map( 'intval', (array) $r['linked'] );
	digi_check( in_array( (int) $ids['host'], $linked, true ) && in_array( (int) $ids['cont'], $linked, true ), 'B3: el menú y la plantilla del pie cuentan como enlaces' );
	return;
}

if ( 'canonical' === $step ) {
	$A::run_canonical();
	digi_check( 1 === count( array_filter( $GLOBALS['digi_http'], function( $u ) { return false !== strpos( $u, 'digital.digitalisimo.invalid' ); } ) ), 'B4: una sola petición al canonical (un dominio que no existe no se reintenta con GET), desde el cron: ' . json_encode( $GLOBALS['digi_http'] ) );
	$issue = $A::canonical_issues()[ $ids['que'] ] ?? array();
	digi_check( 'pending' === ( $issue['kind'] ?? '' ), 'B4: la primera vez queda pendiente de confirmar' );
	digi_check( array() === $A::summary()['errors']['canonical_broken'], 'B4: aún no cuenta como error' );
	return;
}

if ( 'canonical-confirm' === $step ) {
	$state = $A::canonical_state();
	foreach ( $state as $url => $row ) $state[ $url ]['at'] = time() - 1900;
	update_option( $A::CANON, $state, false );
	$A::run_canonical();
	$issue = $A::canonical_issues()[ $ids['que'] ] ?? array();
	digi_check( 'gone' === ( $issue['kind'] ?? '' ) && false !== strpos( $issue['error'], 'Could not resolve host' ), 'B4: confirmado: el dominio no existe' );
	digi_check( 1 === count( $A::summary()['errors']['canonical_broken'] ), 'B4: ahora es un error' );
	$GLOBALS['digi_http'] = array();
	$A::run_canonical();
	digi_check( array() === $GLOBALS['digi_http'], 'B4: con la caché de una semana no se repite la petición' );
	return;
}

if ( 'render' === $step ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	set_current_screen( 'dashboard' );
	ob_start(); $A::notice(); $notice = ob_get_clean();
	digi_check( false !== strpos( $notice, 'notice-error' ) && false !== strpos( $notice, 'sin resolver' ) && false !== strpos( $notice, 'is-dismissible' ), 'Aviso de error en el Escritorio, con la X' );
	$status = Digitalisimo_Integrations_SEO_Abilities::get_site_status();
	digi_check( isset( $status['site_analysis'] ) && $status['site_analysis']['error_count'] >= 4 && 2 === count( $status['site_analysis']['warnings']['cannibalization'] ) && 1 === count( array_filter( $status['site_analysis']['warnings']['cannibalization'], function( $g ) { return $g['inform_only']; } ) ), 'MCP: get-site-status lleva el análisis: ' . json_encode( $status['site_analysis']['error_count'] ?? null ) );
	$_GET = array( 'page' => 'digitalisimo-seo-audit', 'audit' => 'site' );
	ob_start(); Digitalisimo_Integrations_SEO_Audit_Hub::render(); $html = ob_get_clean();
	digi_check( 1 === substr_count( $html, 'name="kind" value="301"' ) && false !== strpos( $html, 'Productos con texto casi igual' ), 'Los productos se muestran sin formulario de 301' );
	digi_check( false !== strpos( $html, 'Entradas del blog (4)' ) && false !== strpos( $html, 'Revisa cada una antes de marcarla' ) && false === strpos( $html, 'class="digitalisimo-orphan-post" name="ids[]" value="' . $ids['n1'] . '" checked' ), 'Las entradas del blog (las tres noticias y la de bienvenida de WordPress) van aparte, con el aviso y sin marcar' );
	foreach ( array( 'Análisis del sitio', '[descripcion_sitio]', '%seo_title%', 'Palabra clave en disputa', 'digital.digitalisimo.invalid', 'black-friday', 'Revisar lo seleccionado', 'Cambios aplicados' ) as $needle ) digi_check( false !== strpos( $html, esc_html( $needle ) ), "La pantalla muestra «$needle»" );
	return;
}

if ( 'cluster' === $step ) {
	$r     = $A::results();
	$group = array_values( array_filter( $r['clusters'], function( $g ) { return empty( $g['inform'] ); } ) )[0];
	$plan  = $C::plan( '301', $group['ids'], (string) $group['keeper'], 'cluster' );
	digi_check( ! is_wp_error( $plan ) && 2 === count( $plan['items'] ) && $plan['over_cap'], 'B2: plan de dos 301, que pide la casilla del tope en un sitio chico' );
	digi_check( null === digi_get( digi_path( $ids['b'] ) ), 'Revisar no cambia nada' );
	$batch = $C::apply( $plan );
	file_put_contents( $GLOBALS['dir'] . '/batch.txt', $batch );
	$go = digi_get( digi_path( $ids['b'] ) );
	digi_check( is_array( $go ) && 301 === $go[1] && false !== strpos( $go[0], '/paginas-web/' ), 'B2: /paginas-web-torreon/ responde 301 a /paginas-web/: ' . json_encode( $go ) );
	$go = digi_get( digi_path( $ids['c'] ) );
	digi_check( is_array( $go ) && 301 === $go[1], 'B2: /diseno-de-paginas-web-en-torreon/ también' );
	digi_check( null === digi_get( digi_path( $ids['a'] ) ) && ! digi_noindex( digi_path( $ids['a'] ) ), 'B2: la que se queda, intacta' );
	$sitemap = digi_sitemap();
	digi_check( false === strpos( $sitemap, '/paginas-web-torreon/' ) && false !== strpos( $sitemap, '/paginas-web/' ), 'B2: las redirigidas salen del sitemap' );
	$log = $C::log_entries();
	digi_check( 1 === count( $log ) && 2 === count( $log[0]['items'] ) && '' === $log[0]['items'][ $ids['b'] ]['noindex'], 'B2: respaldo guardado' );
	return;
}

if ( 'undo' === $step ) {
	$batch = trim( (string) file_get_contents( $GLOBALS['dir'] . '/batch.txt' ) );
	digi_check( true === $C::undo( $batch ), 'Deshacer' );
	digi_check( null === digi_get( digi_path( $ids['b'] ) ) && ! digi_noindex( digi_path( $ids['b'] ) ), 'Tras deshacer, /paginas-web-torreon/ vuelve a responder sin noindex' );
	digi_check( false !== strpos( digi_sitemap(), '/paginas-web-torreon/' ), 'Y vuelve al sitemap' );
	digi_check( 0 === (int) get_option( Digitalisimo_Integrations_Redirections::FLAG, 0 ), 'Sin reglas activas: el front ya no consulta el índice' );
	return;
}

if ( 'orphans' === $step ) {
	$orphans = $A::orphans();
	foreach ( array( 'n1', 'n2', 'n3', 'b', 'c' ) as $key ) digi_check( in_array( (int) $ids[ $key ], $orphans, true ), "B3: /$key es huérfana" );
	foreach ( array( 'home', 'host', 'cont', 'a', 'que', 'bf', 'p1', 'p2' ) as $key ) digi_check( ! in_array( (int) $ids[ $key ], $orphans, true ), "B3: /$key no es huérfana (portada, menú, pie o enlazada)" );
	$plan = $C::plan( '410', array( $ids['p1'], $ids['p2'] ) );
	digi_check( array() === $plan['items'] && 2 === count( $plan['skipped'] ), 'B3: un producto nunca entra en un lote, ni con «seleccionar todas»' );
	$plan = $C::plan( 'noindex', array( $ids['n1'], $ids['n2'], $ids['n3'], $ids['home'] ) );
	digi_check( 3 === count( $plan['items'] ) && 'Es la portada' === $plan['skipped'][0][1], 'B3: la portada nunca entra en un lote' );
	$batch = $C::apply( $plan );
	digi_check( digi_noindex( digi_path( $ids['n1'] ) ) && false === strpos( digi_sitemap(), '/china-prohibe-chips/' ), 'B3: noindex y fuera del sitemap' );
	digi_check( null === digi_get( digi_path( $ids['n1'] ) ), 'B3: con noindex la página sigue visible' );
	$C::undo( $batch );
	digi_check( ! digi_noindex( digi_path( $ids['n1'] ) ) && false !== strpos( digi_sitemap(), '/china-prohibe-chips/' ), 'B3: deshecho' );
	return;
}

if ( 'abilities' === $step ) {
	$W = 'Digitalisimo_Integrations_SEO_Abilities_Write';
	digi_check( $W::part_enabled( 'meta' ) && $W::part_enabled( 'redirects' ), 'B5: sin migrar, el interruptor único encendido abre las cuatro partes: ' . json_encode( array_intersect_key( (array) get_option( 'digitalisimo_integrations_options' ), array_flip( array( 'seo_abilities_write', 'seo_abilities_write_meta', 'seo_redirect_slug_watch' ) ) ) ) . ' de ' . count( (array) get_option( 'digitalisimo_integrations_options' ) ) . ' claves' );
	Digitalisimo_Integrations_Settings::migrate_ability_parts();
	$o = (array) get_option( 'digitalisimo_integrations_options' );
	digi_check( 1 === ( $o['seo_abilities_write_meta'] ?? null ) && 1 === ( $o['seo_abilities_write_redirects'] ?? null ), 'B5: migrado a las cuatro partes' );
	$o['seo_abilities_write_index'] = 0;
	update_option( 'digitalisimo_integrations_options', $o );
	if ( ! function_exists( 'wp_get_ability' ) ) { digi_check( true, 'B5: este WordPress no trae la API de abilities (se omite la llamada real)' ); return; }
	$ability = wp_get_ability( 'digitalisimo-seo/update-post-seo' );
	digi_check( (bool) $ability, 'B5: la ability está registrada' );
	if ( ! $ability ) return;
	$r = $ability->execute( array( 'post_id' => $ids['a'], 'title' => 'Páginas web | Agencia', 'noindex' => true ) );
	digi_check( is_wp_error( $r ) && '' === (string) get_post_meta( $ids['a'], 'digitalisimo_seo_title', true ), 'B5: con «Sitemap e indexación» cerrada no se cambia nada' );
	$r = $ability->execute( array( 'post_id' => $ids['a'], 'title' => 'Páginas web | Agencia' ) );
	digi_check( ! is_wp_error( $r ) && 'Páginas web | Agencia' === get_post_meta( $ids['a'], 'digitalisimo_seo_title', true ), 'B5: con «Títulos y descripciones» abierta, el título sí' );
	$log = $W::log_entries( 'meta' );
	digi_check( 1 === count( $log ) && 'mcp' === $log[0]['via'], 'B5: queda en el registro de su parte' );
	ob_start(); $W::render_log(); $html = ob_get_clean();
	digi_check( false !== strpos( $html, 'Títulos y descripciones' ) && false !== strpos( $html, 'Sitemap e indexación · cerrada' ), 'B5: el registro se pinta por parte' );
	return;
}

if ( 'network-abilities' === $step ) {
	$W = 'Digitalisimo_Integrations_SEO_Abilities_Write';
	update_site_option( 'digitalisimo_integrations_options', array( 'seo_abilities_write' => 1 ) );
	switch_to_blog( 2 );
	digi_check( $W::part_enabled( 'meta' ) && $W::part_enabled( 'index' ), 'B5 red: el sitio que hereda sigue al interruptor de la red' );
	update_option( 'digitalisimo_seo_network_inherit', array( 'seo_abilities_write' => 0 ) );
	update_option( 'digitalisimo_integrations_options', array( 'seo_abilities_write' => 0 ) );
	digi_check( ! $W::part_enabled( 'meta' ), 'B5 red: el que lo apagó por su cuenta, no' );
	restore_current_blog();
	Digitalisimo_Integrations_Settings::migrate_ability_parts();
	$net = (array) get_site_option( 'digitalisimo_integrations_options' );
	digi_check( 1 === ( $net['seo_abilities_write_meta'] ?? null ), 'B5 red: la red se migra' );
	switch_to_blog( 2 );
	digi_check( ! $W::part_enabled( 'meta' ), 'B5 red: tras migrar la red, el sitio que lo apagó sigue cerrado' );
	Digitalisimo_Integrations_Settings::migrate_ability_parts();
	digi_check( 0 === ( (array) get_option( 'digitalisimo_seo_network_inherit' ) )['seo_abilities_write_meta'] && ! $W::part_enabled( 'meta' ), 'B5 red: y su herencia personalizada pasa a las partes' );
	restore_current_blog();
	return;
}
