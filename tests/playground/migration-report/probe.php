<?php
/**
 * Informe de migración en Multisite: página con un widget sin adaptador (bdt-weather) y otro compatible
 * en el subsitio; escribe en result.txt el texto de la pantalla de red y la del sitio, y sus enlaces.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'digitalisimo-elements/pro-elements.php', '', true );
if ( ! get_site( 2 ) ) { wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 ); }
wp_set_current_user( 1 );
switch_to_blog( 2 );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Clima' ) );
update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'a1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'bdt-weather', 'settings' => array(), 'elements' => array() ),
	array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'bdt-accordion', 'settings' => array(), 'elements' => array() ),
) ) ) ) ) );
restore_current_blog();
require_once WP_PLUGIN_DIR . '/digitalisimo-elements/modules/digitalisimo-legacy/class-migration.php';
ob_start(); \Digitalisimo\Elements\Legacy\Migration::network_page(); $net = ob_get_clean();
switch_to_blog( 2 ); ob_start(); \Digitalisimo\Elements\Legacy\Migration::page(); $site = ob_get_clean(); restore_current_blog();
file_put_contents( __DIR__ . "/result.txt", "RED:\n" . trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_replace( '</td>', ' | ', $net ) ) ) ) . "\n\nSITIO:\n" . trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_replace( "</td>", " | ", $site ) ) ) ) . "\n" );
preg_match_all( "/href=\"([^\"]+)\">Abrir/", $net, $m ); file_put_contents( __DIR__ . "/result.txt", "ENLACES: " . implode( " ", $m[1] ) . "\n", FILE_APPEND );
