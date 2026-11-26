<?php
/**
 * Al desinstalar DIGITALÍSIMO IA Tools (docs/IA_USAGE.md): se quitan siempre las tareas programadas del registro de
 * uso de IA; las tablas, los ajustes y los precios del registro sólo se borran en los sitios cuyo ajuste «Borrar el
 * registro de uso al desinstalar» está marcado (propio o heredado de la red). Desmarcado (de fábrica), el historial se
 * conserva: borrar y volver a instalar el plugin no pierde lo gastado. El resto de las opciones de IA Tools no se tocan.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Limpia el registro de uso del sitio en curso según su ajuste. */
function digitalisimo_ai_usage_uninstall_site( array $network ) {
	global $wpdb;
	wp_clear_scheduled_hook( 'digitalisimo_ai_usage_purge' );
	wp_clear_scheduled_hook( 'digitalisimo_ai_usage_alert' );
	$local   = get_option( 'digitalisimo_ai_usage_settings', array() );
	$local   = is_array( $local ) ? $local : array();
	$inherit = is_multisite() && ( ! array_key_exists( 'inherit_network', $local ) || ! empty( $local['inherit_network'] ) );
	$o       = $inherit ? $network : $local;
	if ( empty( $o['purge_on_uninstall'] ) ) {
		return;
	}
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'digitalisimo_ai_usage' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'digitalisimo_ai_usage_daily' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tabla propia.
	foreach ( array( 'digitalisimo_ai_usage_settings', 'digitalisimo_ai_usage_prices', 'digitalisimo_ai_usage_alerts', 'digitalisimo_ai_usage_db', 'digitalisimo_ai_usage_repriced' ) as $option ) {
		delete_option( $option );
	}
	delete_metadata( 'user', 0, 'digitalisimo_ai_usage_dismissed_' . get_current_blog_id(), '', true );
}

$digitalisimo_ai_usage_network = is_multisite() ? get_site_option( 'digitalisimo_ai_usage_settings', array() ) : array();
$digitalisimo_ai_usage_network = is_array( $digitalisimo_ai_usage_network ) ? $digitalisimo_ai_usage_network : array();
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $digitalisimo_ai_usage_blog ) {
		switch_to_blog( (int) $digitalisimo_ai_usage_blog );
		digitalisimo_ai_usage_uninstall_site( $digitalisimo_ai_usage_network );
		restore_current_blog();
	}
	if ( ! empty( $digitalisimo_ai_usage_network['purge_on_uninstall'] ) ) {
		foreach ( array( 'digitalisimo_ai_usage_settings', 'digitalisimo_ai_usage_prices', 'digitalisimo_ai_usage_prices_ver' ) as $digitalisimo_ai_usage_option ) {
			delete_site_option( $digitalisimo_ai_usage_option );
		}
	}
} else {
	digitalisimo_ai_usage_uninstall_site( array() );
}
