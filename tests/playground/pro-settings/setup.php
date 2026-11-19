<?php
// Sitio de la prueba de ajustes de los widgets de PRO (tests/playground/pro-settings.sh), con la versión PUBLICADA de
// DIGITALÍSIMO Elements y de DIGITALÍSIMO Migración activa (carpetas `-publicado`): una página con los widgets y las
// claves del informe real del 2026-11-18 (sintéticos) y otra con un widget de WooCommerce de PRO sin WooCommerce.
// Un mu-plugin hace de «otro plugin» de condiciones de visualización: con `display_condition_enable` encendido, el
// widget no se pinta (como lo haría ese plugin, que registra sus controles en todos los widgets).
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-no-autoupdate.php', "<?php\n// Arnés de pruebas: sin actualizaciones automáticas (borrarían el plugin montado).\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
file_put_contents( WPMU_PLUGIN_DIR . '/digi-otras-condiciones.php', "<?php\n// Arnés de pruebas: otro plugin de condiciones de visualización (oculta el widget con su interruptor encendido).\nadd_filter( 'elementor/frontend/widget/should_render', static function ( \$should, \$widget ) {\n\t\$settings = (array) \$widget->get_data( 'settings' );\n\treturn 'yes' === ( \$settings['display_condition_enable'] ?? '' ) ? false : \$should;\n}, 10, 2 );\n" );
add_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );
$result     = activate_plugin( 'digitalisimo-elements-publicado/pro-elements.php' );
$activation = is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'ok';
// Migración declara «Requires Plugins: digitalisimo-elements» (la carpeta): con la publicada en otra carpeta, se activa
// en la lista directamente; la petición siguiente la carga.
update_option( 'active_plugins', array_values( array_unique( array_merge( (array) get_option( 'active_plugins' ), array( 'digitalisimo-migration-publicado/digitalisimo-migration.php' ) ) ) ) );
update_option( 'elementor_element_cache_ttl', 'disable' );

$w = static function ( $type, $id, array $settings ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
};
// Valores por defecto que Element Pack guardaba en los widgets con sus extensiones apagadas.
$ep = array(
	'ep_widget_cf_colors'               => '#26ccff,#a25afd',
	'ep_widget_cf_shapes'               => array( 'circle' ),
	'ep_notation_list'                  => array( array( '_id' => 'n1', 'ep_notation_type' => 'underline' ) ),
	'ep_sound_effects_element_selector' => '',
	'element_pack_widget_tooltip_text'  => 'This is Tooltip',
	'ep_display_conditions'             => array( array( '_id' => 'd1', 'ep_condition_key' => 'login_status' ) ),
);
$rules = array( array( '_id' => 'u1', 'display_condition_key' => 'browser', 'display_condition_browser' => 'chrome' ) );
$page  = array(
	array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		$w( 'blockquote', 'q1', array( 'blockquote_content' => 'Cita visible', 'author_name' => 'Ana', 'content_width' => 'boxed', 'display_condition_list' => $rules ) + $ep ),
		$w( 'blockquote', 'q2', array( 'blockquote_content' => 'Cita oculta por otro plugin', 'display_condition_enable' => 'yes', 'display_condition_list' => $rules ) ),
		$w( 'price-list', 'l1', array( 'price_list' => array(
			array( '_id' => 'p1', 'title' => 'Plan básico', 'price' => '$9', 'old_price' => '$12', 'item_badge' => 'Oferta', 'link' => array( 'url' => '#' ) ),
			array( '_id' => 'p2', 'title' => 'Plan pro', 'price' => '$19', 'link' => array( 'url' => '#' ) ),
		) ) ),
		$w( 'form', 'f1', array( 'form_name' => 'Contacto', 'form_fields' => array(
			array( '_id' => 'f1a', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Nombre', 'dynamic' => array( 'active' => 'yes' ) ),
			array( '_id' => 'f1b', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => 'true' ),
		) ) + $ep ),
		$w( 'heading', 'h1', array( 'title' => 'Encabezado' ) ),
	) ),
);
$ids = array();
$ids['pagina'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ajustes de PRO', 'post_name' => 'ps-pagina' ) );
update_post_meta( $ids['pagina'], '_elementor_edit_mode', 'builder' );
update_post_meta( $ids['pagina'], '_elementor_data', wp_slash( wp_json_encode( $page ) ) );
$ids['woo'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Carrito', 'post_name' => 'ps-carrito' ) );
update_post_meta( $ids['woo'], '_elementor_edit_mode', 'builder' );
update_post_meta( $ids['woo'], '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( $w( 'woocommerce-cart', 'wc1', array() ), $w( 'blockquote', 'wq', array( 'blockquote_content' => 'Cita junto al carrito' ) ) ) ) ) ) ) );
update_option( 'ps_ids', $ids );
file_put_contents( '/wordpress/wp-content/probe/steps.jsonl', wp_json_encode( array( 'step' => 'setup', 'ids' => $ids, 'active' => get_option( 'active_plugins' ), 'activation' => $activation ) ) . "\n", FILE_APPEND );
