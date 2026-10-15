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
$order  = array( 'sin-condiciones', 'propio-widget-disable', 'nativo-widget-disable', 'heredado-widget-disable', 'visitante-propio', 'visitante-nativo', 'visitante-heredado' );
$varies = array( 'visitante-propio', 'visitante-nativo', 'visitante-heredado' );
$step   = file_exists( $probe . 'cache-step' ) ? (int) file_get_contents( $probe . 'cache-step' ) : 0;
file_put_contents( $probe . 'cache-step', (string) ( $step + 1 ) );
$slug = $order[ $step ] ?? null;
if ( null === $slug ) {
	exit;
}
$by_visitor = array(
	'visitante-propio'   => array( 'digitalisimo_visibility_enable' => 'yes', 'digitalisimo_visibility_to' => 'show', 'digitalisimo_visibility_relation' => 'all', 'digitalisimo_visibility_rules' => array( array( '_id' => 'r1', 'rule_key' => 'browser', 'rule_operator' => 'is', 'rule_browser_value' => 'chrome' ) ) ),
	'visitante-nativo'   => array( 'e_display_conditions' => array( wp_json_encode( array( array( array( 'condition' => 'digitalisimo_browser', 'comparator' => 'is', 'browser' => 'chrome' ) ) ) ) ) ),
	'visitante-heredado' => array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => 'show', 'ep_display_conditions_relation' => 'all', 'ep_display_conditions' => array( array( '_id' => 'r1', 'ep_condition_key' => 'browser', 'ep_condition_operator' => 'is', 'ep_condition_browser_value' => 'chrome' ) ) ),
);
if ( 'sin-condiciones' === $slug || isset( $by_visitor[ $slug ] ) ) {
	$page = get_page_by_path( 'digi-cond-' . $slug );
	$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'digi-cond-' . $slug, 'post_name' => 'digi-cond-' . $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
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
}
$result['no-cache'][ $slug ] = $problems;
file_put_contents( $probe . 'result.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
