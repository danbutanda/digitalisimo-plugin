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

// 6. Revisiones: no entran en los lotes; una página sobrescrita por la conversión de una revisión (hasta 1.0.13) se detecta
//    y se recupera desde su última revisión guardada; convertir una revisión escribe en la revisión, no en la página.
$rev = static function ( $parent, array $elements, $suffix ) {
	$id = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $parent, 'post_name' => $parent . '-' . $suffix, 'post_title' => 'rev' ) );
	update_metadata( 'post', $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	return (int) $id;
};
$page = $make( 'digi-con-revisiones', $data( $legacy ) );
$old  = $data( $legacy );
$old[0]['elements'][0]['settings'] = (object) ( array( 'title' => 'TITULO-VIEJO' ) + $legacy );
$r_old    = $rev( $page, $old, 'revision-v1' );
$r_latest = $rev( $page, $data( $legacy ), 'revision-v1' );
$check( ! in_array( $r_old, Migration::pending(), true ) && ! in_array( $r_latest, Migration::pending(), true ) && in_array( $page, Migration::convertible(), true ) && in_array( $r_old, Migration::legacy_revisions(), true ), 'revisiones: no entran en pendientes; la página sí, y las revisiones heredadas se conocen' );
Migration::convert_document( $page );
$check( false !== strpos( (string) get_post_meta( $page, '_elementor_data', true ), 'TITULO-SOLO-INTERNAS' ) && array() === Migration::overwritten_parents(), 'revisiones: convertida la página, ninguna sobrescrita' );
// Lo que hacía 1.0.13 con una revisión: update_post_meta() escribe en el padre.
list( $converted_old ) = Migration::convert_data( json_decode( (string) get_metadata( 'post', $r_old, '_elementor_data', true ), true ), $r_old );
update_post_meta( $r_old, '_elementor_data', wp_slash( wp_json_encode( $converted_old ) ) );
$check( false !== strpos( (string) get_post_meta( $page, '_elementor_data', true ), 'TITULO-VIEJO' ), 'revisiones: simulación del fallo, la página tiene el contenido de la revisión vieja' );
$over = Migration::overwritten_parents();
$check( isset( $over[ $page ] ) && $r_old === $over[ $page ]['revision'] && ! $over[ $page ]['autosave'], 'revisiones: la página sobrescrita se detecta con la revisión que la pisó' );
$check( 'revision' === Migration::recover_from_revision( $page, $r_old ), 'revisiones: la recuperación desde la última revisión guardada responde' );
$current = (string) get_post_meta( $page, '_elementor_data', true );
$check( false !== strpos( $current, 'TITULO-SOLO-INTERNAS' ) && false === strpos( $current, 'TITULO-VIEJO' ) && false === strpos( $current, 'ep_display_conditions' ) && false !== strpos( $current, 'digitalisimo_visibility_enable' ) && array() === Migration::overwritten_parents(), 'revisiones: la página vuelve a su última versión guardada, convertida, y deja de estar sobrescrita' );
update_option( 'page_on_front', $page );
$html = $render( $page );
$check( false === strpos( $html, 'TITULO-SOLO-INTERNAS' ) && false !== strpos( $html, 'SIEMPRE' ), 'revisiones: recuperada, la portada oculta el título' );
$before = (string) get_post_meta( $page, '_elementor_data', true );
Migration::convert_document( $r_latest );
$check( $before === (string) get_post_meta( $page, '_elementor_data', true ) && false === strpos( (string) get_metadata( 'post', $r_latest, '_elementor_data', true ), 'ep_display_conditions' ), 'revisiones: convertir una revisión escribe en la revisión y no toca la página' );

// 7. Sin revisión guardada (plantillas: sólo autoguardados): la página sobrescrita se recupera desde su copia original, salvo
//    que esa copia sea la del autoguardado; entonces puede darse por revisada.
$tpl = $make( 'digi-pie', $data( $legacy ) );
Migration::convert_document( $tpl );
$auto = $rev( $tpl, $old, 'autosave-v1' );
list( $converted_auto ) = Migration::convert_data( json_decode( (string) get_metadata( 'post', $auto, '_elementor_data', true ), true ), $auto );
update_post_meta( $auto, '_elementor_data', wp_slash( wp_json_encode( $converted_auto ) ) );
$over = Migration::overwritten_parents();
$check( isset( $over[ $tpl ] ) && $auto === $over[ $tpl ]['revision'] && $over[ $tpl ]['autosave'] && 0 === Migration::latest_revision( $tpl ) && 'backup' === Migration::recovery_source( $tpl, $auto )[0], 'sin revisión: la página sobrescrita por un autoguardado se detecta y se recuperará desde su copia original' );
$check( 'backup' === Migration::recover_from_revision( $tpl, $auto ) && false !== strpos( (string) get_post_meta( $tpl, '_elementor_data', true ), 'TITULO-SOLO-INTERNAS' ) && ! isset( Migration::overwritten_parents()[ $tpl ] ), 'sin revisión: recuperada desde la copia original, deja de estar sobrescrita' );
update_post_meta( $auto, '_elementor_data', wp_slash( wp_json_encode( $converted_auto ) ) );
update_metadata( 'post', $tpl, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => (string) get_metadata( 'post', $auto, '_elementor_data', true ) ) ) ) );
$check( '' === Migration::recovery_source( $tpl, $auto )[0] && '' === Migration::recover_from_revision( $tpl, $auto ) && isset( Migration::overwritten_parents()[ $tpl ] ), 'sin revisión ni copia distinta del autoguardado: no hay de dónde recuperar y sigue listada' );
Migration::acknowledge( $tpl );
$check( ! isset( Migration::overwritten_parents()[ $tpl ] ), 'dada por revisada, deja de listarse' );

file_put_contents( '/wordpress/wp-content/probe/result.json', wp_json_encode( $problems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
