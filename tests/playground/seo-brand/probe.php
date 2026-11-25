<?php
/**
 * Pasos de seo-brand.sh. Cada paso es una petición PHP propia en el blog DIGI_BLOG.
 * Escribe sus comprobaciones en result.jsonl y los avisos de PHP en fatal.log. Dominios y nombres inventados.
 */
$step = getenv( 'DIGI_STEP' );
$blog = (int) getenv( 'DIGI_BLOG' );
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
	file_put_contents( '/wordpress/wp-content/probe/result.jsonl', json_encode( array( 'step' => getenv( 'DIGI_STEP' ) . ':' . getenv( 'DIGI_BLOG' ), 'ok' => (bool) $ok, 'what' => $what ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND );
}
// El cron lo dispara el paso «cron», no una petición cualquiera.
define( 'DISABLE_WP_CRON', true );
// .invalid nunca resuelve (RFC 2606): es el dominio que ya no existe.
const DEAD = 'https://media.marca-anterior.invalid/wp-content/uploads/2023/11/Imagotipo.webp';
if ( 'notice' === $step ) define( 'WP_ADMIN', true );

if ( 'setup' === $step ) {
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
// Peticiones HTTP: se cuentan y el dominio inventado no existe (como el de la auditoría).
$GLOBALS['digi_http'] = array();
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
	$GLOBALS['digi_http'][] = $url;
	if ( false !== strpos( $url, 'marca-anterior.test' ) ) return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: media.marca-anterior.test' );
	return $pre;
}, 10, 3 );
$B = 'Digitalisimo_Integrations_Brand_Check';

/** Imagen PNG real en la biblioteca del sitio en curso. */
function digi_attachment( $name, $w, $h ) {
	$up   = wp_upload_dir();
	$file = $up['path'] . '/' . $name;
	wp_mkdir_p( $up['path'] );
	// Cabecera PNG con sus medidas (sin depender de GD): basta para getimagesize().
	file_put_contents( $file, "\x89PNG\r\n\x1a\n" . pack( 'N', 13 ) . 'IHDR' . pack( 'NN', $w, $h ) . "\x08\x06\x00\x00\x00" . "\0\0\0\0" );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit' ), $file );
	update_post_meta( $id, '_wp_attached_file', _wp_relative_upload_path( $file ) );
	return array( $id, $up['url'] . '/' . $name );
}
function digi_head( $page_id ) {
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) ); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; $GLOBALS['wp_query']->the_post();
	ob_start(); Digitalisimo_Integrations_SEO_Suite::head(); $head = ob_get_clean();
	return array( $head, Digitalisimo_Integrations_SEO_Suite::title( '' ) );
}
function digi_meta( $head, $attr, $name ) { return preg_match( '/<meta ' . $attr . '="' . preg_quote( $name, '/' ) . '" content="([^"]*)"/', $head, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null; }
function digi_graph( $head ) {
	if ( ! preg_match( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $head, $m ) ) return array();
	$data = json_decode( $m[1], true ); return $data['@graph'] ?? array();
}
function digi_node( $graph, $type ) { foreach ( $graph as $n ) if ( in_array( $type, (array) $n['@type'], true ) ) return $n; return null; }

if ( 'network-seed' === $step ) {
	// La red define el nombre de la organización con emoji: el sitio 2 lo hereda.
	$network = (array) get_site_option( 'digitalisimo_integrations_options', array() );
	update_site_option( 'digitalisimo_integrations_options', array_merge( $network, array( 'seo_organization_name' => 'Red Ejemplo 🥇' ) ) );
	digi_check( 'Red Ejemplo 🥇' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ), 'La red guarda el nombre de la organización' );
	return;
}

if ( 'seed' === $step ) {
	list( $logo_id, $logo_url ) = digi_attachment( 'logo-oficial-' . $blog . '.png', 400, 400 );
	list( , $demo_url )         = digi_attachment( 'logo-marca-softaculous-demo.png', 200, 200 );
	set_theme_mod( 'custom_logo', $logo_id );
	update_option( 'digi_probe', array( 'logo' => $logo_url, 'demo' => $demo_url ) );
	$opts = (array) get_option( 'digitalisimo_integrations_options', array() );
	$opts = array_merge( $opts, array(
		'seo_logo'             => $demo_url,
		'seo_title_page'       => '%title% 🤖 Garantizado Hoy',
		'seo_description_page' => '%focuskw% %sep% %excerpt%',
		'seo_local_enabled'    => 1,
		'seo_local_type'       => 'Plumber',
		'seo_local_city'       => 'Ciudad Ejemplo',
		'seo_local_address'    => 'Calle Falsa 123',
		'seo_social_profiles'  => array( 'instagram' => 'https://instagram.com/marca.ejemplo', 'google_business' => 'https://g.page/marca-ejemplo' ),
	) );
	// La imagen social y el nombre SEO son de cada sitio (no se heredan); el nombre de la organización viene de la red.
	$opts = array_merge( $opts, array( 'seo_default_image' => DEAD, 'seo_site_name' => 'Marca Ejemplo 🥇' ) );
	update_option( 'digitalisimo_integrations_options', $opts );
	$content = '<!-- wp:paragraph --><p>Reparamos fugas y destapamos drenajes en casas y negocios.</p><!-- /wp:paragraph --><div><h2>Garantía</h2><p>Cada trabajo lleva garantía por escrito de noventa días y precio cerrado antes de empezar.</p></div>[wpuf_account]<p>Escríbenos y te respondemos en minutos.</p>';
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Plomería urgente', 'post_status' => 'publish', 'post_content' => $content ) );
	$manual = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Oferta', 'post_status' => 'publish', 'post_content' => '<p>Texto.</p>' ) );
	update_post_meta( $manual, 'digitalisimo_seo_title', 'Oferta de temporada en Ciudad Ejemplo' );
	update_post_meta( $manual, 'digitalisimo_seo_description', 'Descripción escrita a mano para la oferta vigente.' );
	update_option( 'digi_probe', array( 'logo' => $logo_url, 'demo' => $demo_url, 'page' => $page, 'manual' => $manual ) );
	digi_check( (bool) wp_next_scheduled( $B::HOOK ), 'Guardar los ajustes programa la comprobación en el cron' );
	return;
}
$probe = (array) get_option( 'digi_probe', array() );

if ( 'before' === $step ) {
	list( $head, $title ) = digi_head( $probe['page'] );
	digi_check( array() === $GLOBALS['digi_http'], 'El front no hace peticiones HTTP: ' . implode( ', ', $GLOBALS['digi_http'] ) );
	digi_check( DEAD === digi_meta( $head, 'property', 'og:image' ), 'Sin comprobar, og:image es la configurada (como antes)' );
	$org = digi_node( digi_graph( $head ), 'Plumber' ) ?: digi_node( digi_graph( $head ), 'Organization' );
	digi_check( $org && ! isset( $org['logo'] ), 'El logo de demo no se publica ni antes de comprobarlo' );
	return;
}

if ( 'cron' === $step ) {
	do_action( $B::HOOK );
	$state = $B::state();
	digi_check( in_array( DEAD, $GLOBALS['digi_http'], true ), 'El cron pide la imagen de otro dominio' );
	digi_check( ! preg_grep( '/playground\.test/', $GLOBALS['digi_http'] ), 'Las imágenes de la biblioteca se comprueban en el disco: ' . implode( ', ', $GLOBALS['digi_http'] ) );
	digi_check( 'gone' === ( $state['urls'][ DEAD ]['verdict'] ?? '' ) && null === ( $state['urls'][ DEAD ]['ok'] ?? 'x' ) && (bool) wp_next_scheduled( $B::HOOK, array( $B::RETRY ) ), 'La primera vez sólo queda pendiente y se reintenta en una hora: ' . wp_json_encode( $state['urls'][ DEAD ] ?? null ) );
	// Pasa una hora y corre el reintento: el fallo se confirma.
	$state['urls'][ DEAD ]['gone_since'] -= HOUR_IN_SECONDS;
	update_option( $B::OPTION, $state, true );
	do_action( $B::HOOK, $B::RETRY );
	$state = $B::state();
	digi_check( false === ( $state['urls'][ DEAD ]['ok'] ?? null ), 'Confirmado una hora después, la imagen del dominio inexistente queda como rota' );
	digi_check( $probe['logo'] === ( $state['fallback_logo'] ?? '' ) && 400 === (int) ( $state['urls'][ $probe['logo'] ]['width'] ?? 0 ), 'La reserva es el logo del tema, comprobado en el disco' );
	digi_check( (bool) wp_next_scheduled( $B::HOOK, array( $B::DAILY ) ), 'La comprobación se repite cada día' );
	global $wpdb;
	$autoload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $B::OPTION ) );
	digi_check( in_array( $autoload, array( 'on', 'yes', 'auto-on' ), true ), 'El resultado se guarda con autoload: ' . $autoload );
	return;
}

if ( 'after' === $step ) {
	list( $head, $title ) = digi_head( $probe['page'] );
	digi_check( array() === $GLOBALS['digi_http'], 'El front sigue sin peticiones HTTP' );
	digi_check( $probe['logo'] === digi_meta( $head, 'property', 'og:image' ) && $probe['logo'] === digi_meta( $head, 'name', 'twitter:image' ), 'og:image y twitter:image usan el logo del tema: ' . digi_meta( $head, 'property', 'og:image' ) );
	digi_check( false === strpos( $head, 'marca-anterior' ) && false === strpos( $head, 'softaculous' ), 'Ni el dominio inexistente ni el logo de demo salen en la página' );
	$graph = digi_graph( $head );
	$org   = digi_node( $graph, 'Plumber' ) ?: digi_node( $graph, 'Organization' );
	digi_check( $org && $probe['logo'] === ( $org['logo']['url'] ?? '' ), 'El logo del schema es el del tema: ' . wp_json_encode( $org['logo'] ?? null ) );
	digi_check( 'Marca Ejemplo' === ( digi_node( $graph, 'WebSite' )['name'] ?? '' ) && 'Marca Ejemplo' === digi_meta( $head, 'property', 'og:site_name' ), 'Nombre sin emoji en WebSite y og:site_name: ' . digi_meta( $head, 'property', 'og:site_name' ) );
	if ( is_multisite() ) digi_check( $org && 'Red Ejemplo' === ( $org['name'] ?? '' ), 'El nombre de la organización heredado de la red, sin emoji: ' . ( $org['name'] ?? '' ) );
	digi_check( $org && ! isset( $org['telephone'] ) && ! isset( $org['openingHoursSpecification'] ), 'Sin teléfono ni horario configurados no se inventan' );
	digi_check( $org && array( 'https://instagram.com/marca.ejemplo', 'https://g.page/marca-ejemplo' ) === ( $org['sameAs'] ?? null ), 'sameAs con redes y Google Business' );
	$desc = (string) digi_meta( $head, 'name', 'description' );
	digi_check( '' !== $desc && 0 !== strpos( $desc, '|' ) && mb_strlen( $desc ) <= 155 && 0 === strpos( $desc, 'Reparamos fugas' ) && false === strpos( $desc, '[' ), 'Descripción generada del contenido, sin «|» y ≤ 155: ' . $desc );
	digi_check( 'Plomería urgente 🤖 Garantizado Hoy' === $title, 'La plantilla del sitio se aplica tal cual (se avisa aparte): ' . $title );
	return;
}

if ( 'manual' === $step ) {
	list( $head, $title ) = digi_head( $probe['manual'] );
	digi_check( 'Oferta de temporada en Ciudad Ejemplo' === $title, 'El título escrito a mano no lleva el sufijo: ' . $title );
	digi_check( 'Descripción escrita a mano para la oferta vigente.' === digi_meta( $head, 'name', 'description' ), 'La descripción escrita a mano se respeta' );
	return;
}

if ( 'notice' === $step ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	set_current_screen( 'dashboard' );
	ob_start(); do_action( 'admin_notices' ); $html = ob_get_clean();
	digi_check( false !== strpos( $html, 'data-digitalisimo-notice="seo-brand' ) && false !== strpos( $html, 'is-dismissible' ), 'El aviso de marca sale en el Escritorio, con la X' );
	foreach ( array( 'marca-anterior.test', 'softaculous', 'Garantizado Hoy', 'el teléfono', 'el horario', 'emoji' ) as $needle ) digi_check( false !== strpos( $html, esc_html( $needle ) ), 'El aviso menciona «' . $needle . '»' );
	digi_check( array() === $GLOBALS['digi_http'], 'El aviso no hace peticiones HTTP' );
	if ( class_exists( 'Digitalisimo_Integrations_SEO_Abilities' ) ) {
		$status = Digitalisimo_Integrations_SEO_Abilities::get_site_status();
		$codes  = array_column( (array) ( $status['brand_warnings'] ?? array() ), 'code' );
		digi_check( in_array( 'image_broken', $codes, true ) && in_array( 'logo_demo', $codes, true ) && in_array( 'template_literal', $codes, true ) && in_array( 'local_missing', $codes, true ), 'get-site-status del MCP lleva los avisos: ' . implode( ', ', $codes ) );
	}
	return;
}

if ( 'rankmath' === $step ) {
	// Rank Math de verdad (instalado de wordpress.org por el blueprint): la salida es suya, así que no se comprueba ni se avisa.
	digi_check( defined( 'RANK_MATH_VERSION' ) && 'Rank Math' === Digitalisimo_Integrations_SEO_Resolver::foreign_seo(), 'Rank Math está activo: ' . ( defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : 'no' ) );
	file_put_contents( '/wordpress/wp-content/probe/rankmath-version.txt', defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '' );
	$before = $B::state();
	digi_check( false === $B::refresh(), 'Con Rank Math no se programa la comprobación' );
	do_action( $B::HOOK );
	digi_check( array() === $GLOBALS['digi_http'] && $before === $B::state(), 'Con Rank Math el cron no hace peticiones ni cambia el resultado: ' . implode( ', ', $GLOBALS['digi_http'] ) );
	digi_check( array() === $B::warnings(), 'Con Rank Math no hay avisos de marca' );
	if ( class_exists( 'Digitalisimo_Integrations_SEO_Abilities' ) ) digi_check( array() === ( Digitalisimo_Integrations_SEO_Abilities::get_site_status()['brand_warnings'] ?? null ), 'get-site-status del MCP no devuelve avisos de marca con Rank Math' );
	list( $head ) = digi_head( $probe['page'] );
	digi_check( '' === trim( $head ), 'Con Rank Math DIGITALÍSIMO SEO no imprime su head (ni og:image ni schema): ' . substr( $head, 0, 200 ) );
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	set_current_screen( 'dashboard' );
	ob_start(); $B::notice(); $html = ob_get_clean();
	digi_check( false === strpos( $html, 'seo-brand' ), 'Con Rank Math no sale el aviso de marca en el Escritorio' );
	return;
}

if ( 'clean' === $step ) {
	// Un sitio sin imágenes de marca ni SEO local: nada que comprobar ni avisar.
	update_option( 'digitalisimo_integrations_options', array() );
	// En un tema de bloques el logo del tema también vive en la opción site_logo (WordPress los sincroniza).
	remove_theme_mod( 'custom_logo' );
	delete_option( 'site_logo' );
	delete_option( $B::OPTION );
	wp_clear_scheduled_hook( $B::HOOK );
	wp_clear_scheduled_hook( $B::HOOK, array( $B::DAILY ) );
	$refresh = $B::refresh();
	digi_check( false === $refresh && ! wp_next_scheduled( $B::HOOK ), 'Sin imágenes de marca no se programa nada: ' . wp_json_encode( array( $refresh, wp_next_scheduled( $B::HOOK ), $B::configured() ) ) );
	list( $head ) = digi_head( $probe['page'] );
	digi_check( false === strpos( $head, 'og:image' ) && false === strpos( $head, '"logo"' ) && false === strpos( $head, 'openingHours' ), 'Sin configurar no hay imagen, logo ni horario' );
	digi_check( ! in_array( 'image_broken', array_column( $B::warnings(), 'code' ), true ), 'Sin imágenes no hay avisos de imagen' );
	return;
}
