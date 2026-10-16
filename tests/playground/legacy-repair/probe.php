<?php
/**
 * Portada estática real con una regla heredada «ocultar en la página de inicio estática» y la opción de Element Pack
 * guardada sin la clave de la extensión (como quedó en sitios reales): el motor heredado oculta el título en la portada y
 * lo muestra en otra página; la conversión conserva el comportamiento; si un documento convertido perdió las reglas, la
 * reparación las devuelve desde la copia original; con la extensión apagada expresamente, la conversión guarda las reglas
 * sin aplicarlas.
 */
define( 'WP_USE_THEMES', true );
require '/wordpress/wp-load.php';
require_once '/wordpress/wp-content/plugins/digitalisimo-migration/legacy/class-migration.php';
use Digitalisimo\Elements\Legacy\Migration;

$problems = array();
$check    = static function ( $ok, $message ) use ( &$problems ) { if ( ! $ok ) { $problems[] = $message; } };
$render   = static function ( $id ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post']         = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	delete_post_meta( $id, '_elementor_element_cache' );
	\Elementor\Plugin::$instance->documents->get( $id, false );
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	wp_reset_postdata();
	return $html;
};
$legacy = array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => 'hide', 'ep_display_conditions_relation' => 'all', 'ep_display_conditions' => array( array( '_id' => 'r1', 'ep_condition_key' => 'static_page', 'ep_condition_operator' => 'is', 'ep_condition_static_page_value' => 'static' ) ) );
$data   = static function ( array $settings ) {
	return array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => (object) array(), 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) ( array( 'title' => 'TITULO-SOLO-INTERNAS' ) + $settings ) ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) array( 'title' => 'SIEMPRE' ) ),
	) ) );
};
$make = static function ( $slug, array $elements ) {
	$page = get_page_by_path( $slug );
	$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	delete_post_meta( $id, Migration::BACKUP );
	return (int) $id;
};
// Opción como quedó en sitios reales: guardada, sin la clave de Visibility Controls.
update_option( 'element_pack_elementor_extend', array( 'wrapper-link' => 'on' ) );
$front = $make( 'digi-portada', $data( $legacy ) );
$inner = $make( 'digi-interna', $data( $legacy ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
wp_set_current_user( 0 );

// 1. Motor heredado (documento sin convertir).
$html = $render( $front );
$check( false === strpos( $html, 'TITULO-SOLO-INTERNAS' ) && false !== strpos( $html, 'SIEMPRE' ), 'heredado: en la portada estática el título debe ocultarse (y lo demás verse)' );
$html = $render( $inner );
$check( false !== strpos( $html, 'TITULO-SOLO-INTERNAS' ), 'heredado: en una página interna el título debe verse' );

// 2. Conversión: mismo resultado con reglas propias.
Migration::convert_document( $front );
$converted = json_decode( (string) get_post_meta( $front, '_elementor_data', true ), true );
$w1        = $converted[0]['elements'][0]['settings'] ?? array();
$check( 'yes' === ( $w1['digitalisimo_visibility_enable'] ?? '' ) && 'static_page' === ( $w1['digitalisimo_visibility_rules'][0]['rule_key'] ?? '' ) && ! isset( $w1['ep_display_conditions'] ), 'conversión: las reglas pasan al formato propio activas' );
$html = $render( $front );
$check( false === strpos( $html, 'TITULO-SOLO-INTERNAS' ) && false !== strpos( $html, 'SIEMPRE' ), 'convertido: en la portada el título sigue oculto' );

// 3. Pérdida simulada (conversión de 1.0.10 o anterior) y reparación desde la copia original.
foreach ( array_keys( $w1 ) as $key ) { if ( 0 === strpos( $key, 'digitalisimo_visibility_' ) ) { unset( $w1[ $key ] ); } }
$converted[0]['elements'][0]['settings'] = (object) $w1;
update_post_meta( $front, '_elementor_data', wp_slash( wp_json_encode( $converted ) ) );
delete_post_meta( $front, '_elementor_element_cache' );
$html = $render( $front );
$check( false !== strpos( $html, 'TITULO-SOLO-INTERNAS' ), 'pérdida simulada: sin reglas el título aparece en la portada (estado que se repara)' );
$count = Migration::repair_conditions( $front );
$check( 1 === $count, 'reparación: debe recuperar las reglas de un elemento (' . $count . ')' );
$html = $render( $front );
$check( false === strpos( $html, 'TITULO-SOLO-INTERNAS' ) && false !== strpos( $html, 'SIEMPRE' ), 'reparado: en la portada el título vuelve a ocultarse' );
$check( 0 === Migration::repair_conditions( $front ), 'reparar de nuevo no cambia nada' );
$html = $render( $inner );
$check( false !== strpos( $html, 'TITULO-SOLO-INTERNAS' ), 'heredado: la página interna sigue mostrando el título' );

// 4. Extensión apagada expresamente: ni el motor heredado ni la conversión aplican, pero las reglas se conservan.
update_option( 'element_pack_elementor_extend', array( 'visibility-controls' => 'off' ) );
$off = $make( 'digi-portada-off', $data( $legacy ) );
update_option( 'page_on_front', $off );
$html = $render( $off );
$check( false !== strpos( $html, 'TITULO-SOLO-INTERNAS' ), 'extensión apagada: el motor heredado no aplica las reglas, como Element Pack' );
Migration::convert_document( $off );
$w1 = json_decode( (string) get_post_meta( $off, '_elementor_data', true ), true )[0]['elements'][0]['settings'] ?? array();
$check( '' === ( $w1['digitalisimo_visibility_enable'] ?? 'x' ) && 'static_page' === ( $w1['digitalisimo_visibility_rules'][0]['rule_key'] ?? '' ), 'extensión apagada: la conversión guarda las reglas desactivadas' );
$html = $render( $off );
$check( false !== strpos( $html, 'TITULO-SOLO-INTERNAS' ), 'extensión apagada: convertido, el título sigue visible' );

// 5. Lotes: un documento que sólo conserva un widget sin adaptador no bloquea la fila; los lotes toman los convertibles y lo cuentan aparte.
update_option( 'element_pack_elementor_extend', array( 'wrapper-link' => 'on' ) );
$blocked = $make( 'digi-bloqueado', array( array( 'id' => 'c9', 'elType' => 'container', 'settings' => (object) array(), 'elements' => array(
	array( 'id' => 'w9', 'elType' => 'widget', 'widgetType' => 'bdt-barcode', 'settings' => (object) array( 'barcode_text' => 'SIN-ADAPTADOR' ) ),
) ) ) );
$next   = $make( 'digi-siguiente', $data( $legacy ) );
$groups = Migration::classify();
$check( in_array( $blocked, $groups['blocked'], true ) && ! in_array( $blocked, $groups['convertible'], true ) && in_array( $next, $groups['convertible'], true ) && in_array( $blocked, Migration::pending(), true ), 'lotes: el documento con sólo un widget sin adaptador se cuenta aparte y el siguiente entra en el lote' );
$check( 0 === ( Migration::convert_document( $blocked )['native'] ?? -1 ), 'lotes: convertir el documento bloqueado no cambia nada' );
foreach ( Migration::convertible() as $id ) {
	Migration::convert_document( $id );
}
$groups = Migration::classify();
$check( array() === $groups['convertible'] && array( $blocked ) === $groups['blocked'], 'lotes: convertido todo lo convertible, sólo queda el documento bloqueado y no vuelve a entrar en un lote' );

file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $problems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
