<?php
/**
 * Caché de página en un proceso PHP limpio por página, como ocurre en WordPress: la página con elementos que
 * varían por visitante (navegador) pide no guardarse ya en `template_redirect` (antes de la salida, a tiempo para
 * las cabeceras HTTP); la página sin condiciones, o sólo con condiciones de sesión, sigue en caché. Cada ejecución
 * atiende la siguiente página de la lista.
 */
define( 'WP_USE_THEMES', true );
require '/wordpress/wp-load.php';
$probe  = '/wordpress/wp-content/probe/';
$result = json_decode( (string) file_get_contents( $probe . 'result.json' ), true );
$result = is_array( $result ) ? $result : array();
// Sólo de sesión (cacheables: la caché de página nunca sirve a usuarios con sesión) frente a por visitante (navegador) en los tres motores.
// F7a (V.13 #9): cookie, «Visitó la página» y existencias de otros productos varían por visitante; campo, campo del
// usuario, autor y búsqueda dependen de la URL o de la sesión y la página sigue en caché.
$order  = array( 'sin-condiciones', 'propio-widget-disable', 'nativo-widget-disable', 'heredado-widget-disable', 'visitante-propio', 'visitante-nativo', 'visitante-heredado', 'f7a-cookie', 'f7a-visita', 'f7a-existencias', 'f7a-campo', 'f7a-usuario', 'f7a-autor', 'f7a-busqueda' );
$varies = array( 'visitante-propio', 'visitante-nativo', 'visitante-heredado', 'f7a-cookie', 'f7a-visita', 'f7a-existencias' );
$step   = file_exists( $probe . 'cache-step' ) ? (int) file_get_contents( $probe . 'cache-step' ) : 0;
file_put_contents( $probe . 'cache-step', (string) ( $step + 1 ) );
$slug = $order[ $step ] ?? null;
if ( null === $slug ) {
	exit;
}
$f7a      = (array) ( $result['_f7a'] ?? array() );
$visited  = (string) ( $f7a['visitada'] ?? '0' );
$products = array_map( 'strval', (array) ( $f7a['productos'] ?? array() ) );
$f7a_one  = static function ( array $condition ) {
	return array( 'e_display_conditions' => array( wp_json_encode( array( array( $condition ) ) ) ) );
};
// Ningún valor recibido o guardado (cookie, meta, término) aparece en el HTML, tampoco en esta petición limpia.
$secrets  = array( 'valor-cookie-secreto-k7', 'azul-meta-secreto-k7', 'termino-secreto-k7' );
$f7a_meta = array( 'f7a-campo' => array( 'digi_tono' => 'azul-meta-secreto-k7' ) );
$by_visitor = array(
	'visitante-propio'   => array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'show', 'digitalisimo_visibility_relation' => 'all', 'digitalisimo_visibility_rules' => array( array( '_id' => 'r1', 'rule_key' => 'browser', 'rule_operator' => 'is', 'rule_browser_value' => 'chrome' ) ) ),
	'visitante-nativo'   => array( 'e_display_conditions' => array( wp_json_encode( array( array( array( 'condition' => 'digitalisimo_browser', 'comparator' => 'is', 'browser' => 'chrome' ) ) ) ) ) ),
	'visitante-heredado' => array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => 'show', 'ep_display_conditions_relation' => 'all', 'ep_display_conditions' => array( array( '_id' => 'r1', 'ep_condition_key' => 'browser', 'ep_condition_operator' => 'is', 'ep_condition_browser_value' => 'chrome' ) ) ),
	'f7a-cookie'         => $f7a_one( array( 'condition' => 'digitalisimo_cookie', 'name' => 'digi_prueba', 'comparator' => 'is', 'value' => 'valor-cookie-secreto-k7' ) ),
	'f7a-visita'         => $f7a_one( array( 'condition' => 'digitalisimo_page_visit', 'comparator' => 'is_one_of', 'pages' => array( $visited ) ) ),
	'f7a-existencias'    => $f7a_one( array( 'condition' => 'digitalisimo_product_stock_of', 'products' => $products ? $products : array( $visited ), 'match' => 'any', 'comparator' => 'instock' ) ),
	'f7a-campo'          => $f7a_one( array( 'condition' => 'digitalisimo_field', 'source' => 'post', 'key' => 'digi_tono', 'comparator' => 'is', 'value' => 'azul-meta-secreto-k7' ) ),
	'f7a-usuario'        => $f7a_one( array( 'condition' => 'digitalisimo_user_field', 'key' => 'first_name', 'comparator' => 'is', 'value' => 'Ana' ) ),
	'f7a-autor'          => $f7a_one( array( 'condition' => 'digitalisimo_author', 'comparator' => 'role_in', 'roles' => array( 'administrator' ) ) ),
	'f7a-busqueda'       => $f7a_one( array( 'condition' => 'digitalisimo_search', 'comparator' => 'contains', 'value' => 'termino-secreto-k7' ) ),
);
if ( 'sin-condiciones' === $slug || isset( $by_visitor[ $slug ] ) ) {
	$page = get_page_by_path( 'digi-cond-' . $slug );
	$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'digi-cond-' . $slug, 'post_name' => 'digi-cond-' . $slug, 'post_author' => 1 ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	foreach ( $f7a_meta[ $slug ] ?? array() as $meta_key => $meta_value ) {
		update_post_meta( $id, $meta_key, $meta_value );
	}
	$settings = $by_visitor[ $slug ] ?? array();
	$data = array( array( 'id' => 'n1', 'elType' => 'container', 'settings' => (object) array(), 'elements' => array( array( 'id' => 'n2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) ( array( 'title' => 'Contenido ' . $slug ) + $settings ) ) ) ) );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
} else {
	$page = get_page_by_path( 'digi-cond-' . $slug );
	$id   = $page ? $page->ID : 0;
}
$problems = array();
if ( ! $id ) {
	$problems[] = 'no existe la página';
} else {
	wp_set_current_user( 0 );
	// La petición del visitante: consulta principal y template_redirect, como hace WordPress antes de cargar la plantilla
	// (sin la redirección canónica, que en una petición simulada terminaría el proceso).
	remove_action( 'template_redirect', 'redirect_canonical' );
	remove_action( 'template_redirect', 'wp_old_slug_redirect' );
	remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	$GLOBALS['wp']->main( 'page_id=' . $id );
	do_action( 'template_redirect' );
	$expected = in_array( $slug, $varies, true );
	if ( ! is_singular() || (int) get_queried_object_id() !== (int) $id ) {
		$problems[] = 'la consulta principal no apunta a la página';
	}
	if ( $expected !== defined( 'DONOTCACHEPAGE' ) ) {
		$problems[] = $expected ? 'la página con contenido por visitante no pidió quedar fuera de la caché de página antes de la salida' : 'la página sin condiciones por visitante (ninguna, o sólo de sesión) pidió quedar fuera de la caché de página';
	}
	$headers = function_exists( 'headers_list' ) ? implode( "\n", (array) headers_list() ) : '';
	if ( false !== stripos( $headers, 'content-type' ) && $expected !== ( false !== stripos( $headers, 'no-store' ) ) ) {
		$problems[] = $expected ? 'faltan las cabeceras HTTP de no-caché' : 'sobran cabeceras HTTP de no-caché';
	}
	// La decisión temprana no cambia lo que se pinta.
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	if ( '' === trim( $html ) ) {
		$problems[] = 'la página no pintó nada';
	}
	foreach ( $secrets as $secret ) {
		if ( false !== strpos( $html, $secret ) ) {
			$problems[] = 'el HTML contiene el valor ' . $secret;
		}
	}
}
$result['no-cache'][ $slug ] = $problems;
file_put_contents( $probe . 'result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
