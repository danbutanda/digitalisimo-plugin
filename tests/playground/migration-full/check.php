<?php
/**
 * Estado del sitio (o de cada sitio de la red) tras «Migrar», en JSON, para drive.mjs (sólo Playground).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
header( 'Content-Type: application/json' );
wp_clean_plugins_cache( false );
$network = is_multisite();
$last    = \Digitalisimo\Migration\Full::last( $network );
$out     = array(
	'installed' => array_values( array_keys( get_plugins() ) ),
	'sitewide'  => $network ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(),
	'themes'    => array_keys( wp_get_themes() ),
	'last'      => is_array( $last ) ? array_intersect_key( $last, array_flip( array( 'failed', 'error', 'deleted', 'kept', 'verified', 'verify', 'backup', 'installed', 'activated', 'imports', 'retired', 'theme', 'theme_updated_now', 'theme', 'notes', 'done' ) ) ) : null,
	'job'       => \Digitalisimo\Migration\Full::load( $network ),
	'blocked'   => is_file( WP_CONTENT_DIR . '/probe/http-blocked.log' ) ? array_values( array_unique( array_filter( explode( "\n", (string) file_get_contents( WP_CONTENT_DIR . '/probe/http-blocked.log' ) ) ) ) ) : array(),
	'redirect'  => json_decode( (string) @file_get_contents( WP_CONTENT_DIR . '/probe/activation-redirect.json' ), true ),
	'downloads' => is_file( WP_CONTENT_DIR . '/probe/downloads.log' ) ? array_values( array_filter( explode( "\n", (string) file_get_contents( WP_CONTENT_DIR . '/probe/downloads.log' ) ) ) ) : array(),
	'sites'     => array(),
	// Respaldos de la migración en el historial de Backups (en una red, el del sitio principal).
	'backups'   => count( array_filter( (array) get_option( 'digitalisimo_backups_history', array() ), static function ( $item ) {
		return 'pre-migration' === (string) ( ( (array) $item )['policy'] ?? '' );
	} ) ),
);
foreach ( $network ? get_sites( array( 'fields' => 'ids' ) ) : array( get_current_blog_id() ) as $site_id ) {
	if ( $network ) {
		switch_to_blog( (int) $site_id );
	}
	$page     = (int) get_option( 'digi_probe_page' );
	$response = wp_remote_get( home_url( '/' ), array( 'timeout' => 30 ) );
	$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
	$out['sites'][ $site_id ] = array(
		'active'     => array_values( (array) get_option( 'active_plugins', array() ) ),
		'stylesheet' => get_stylesheet(),
		'seo_title'  => (string) get_post_meta( $page, 'digitalisimo_seo_title', true ),
		'seo_desc'   => (string) get_post_meta( $page, 'digitalisimo_seo_description', true ),
		'home'       => is_wp_error( $response ) ? $response->get_error_message() : (int) wp_remote_retrieve_response_code( $response ),
		'home_text'  => false !== strpos( $body, 'Vivero' ) || false !== strpos( $body, 'Segundo' ),
		'fatal'      => false !== stripos( $body, 'critical error' ) || false !== stripos( $body, 'Fatal error' ),
		'content'    => is_array( get_option( 'fake_runner_report' ) ) ? 'fake' : ( is_array( get_option( 'digitalisimo_migration_report' ) ) ? 'done' : 'missing' ),
		'quote'      => (int) get_option( 'digi_probe_blockquote' ),
		'cart'       => (int) get_option( 'digi_probe_woocommerce-cart' ),
		'cart_types' => array_column( (array) ( json_decode( (string) get_post_meta( (int) get_option( 'digi_probe_woocommerce-cart' ), '_elementor_data', true ), true )[0]['elements'] ?? array() ), 'widgetType' ),
		'theme_version' => (string) wp_get_theme( 'digitalisimo-theme' )->get( 'Version' ),
		'child_head'    => false !== strpos( $body, 'id="hijo-head" data-hello="3.5.1"' ) && false !== strpos( $body, 'id="hijo-foot"' ),
		'child_copy'    => (bool) preg_match( '#uploads/(?:sites/\d+/)?digitalisimo-theme/child/hello-hijo/style\.css#', $body ),
		'child_sheet'   => (bool) preg_match( '#themes/hello-hijo/style\.css#', $body ),
		'snippets'      => array_values( array_map( static function ( $row ) { return array( 'title' => $row['title'], 'active' => $row['active'] ); }, (array) apply_filters( 'digitalisimo_tools_php_sources', array(), array( 'scope' => 'site' ) ) ) ),
		'quote_type' => (string) ( json_decode( (string) get_post_meta( (int) get_option( 'digi_probe_blockquote' ), '_elementor_data', true ), true )[0]['elements'][0]['widgetType'] ?? '' ),
	);
	if ( $network ) {
		restore_current_blog();
	}
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
