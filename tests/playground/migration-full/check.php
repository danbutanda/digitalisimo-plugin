<?php
/**
 * Estado del sitio (o de cada sitio de la red) tras «Migrar», en JSON, para drive.mjs (sólo Playground).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
header( 'Content-Type: application/json' );
wp_clean_plugins_cache( false );
$network = is_multisite();
if ( ! class_exists( 'Digitalisimo_Cache_Salt', false ) && is_file( WP_PLUGIN_DIR . '/digitalisimo-migration/includes/class-digitalisimo-cache-salt.php' ) ) {
	require_once WP_PLUGIN_DIR . '/digitalisimo-migration/includes/class-digitalisimo-cache-salt.php';
}
$last    = \Digitalisimo\Migration\Full::last( $network );
$out     = array(
	'installed' => array_values( array_keys( get_plugins() ) ),
	'sitewide'  => $network ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(),
	'themes'    => array_keys( wp_get_themes() ),
	'last'      => is_array( $last ) ? array_intersect_key( $last, array_flip( array( 'run', 'failed', 'error', 'deleted', 'kept', 'verified', 'verify', 'backup', 'installed', 'activated', 'upgraded', 'imports', 'retired', 'theme', 'theme_updated_now', 'theme_installed_now', 'notes', 'done' ) ) ) : null,
	'tools_old' => is_file( WP_CONTENT_DIR . '/probe/tools-old' ),
	'tools_version' => (string) ( get_plugins()['digitalisimo-tools/digitalisimo-tools.php']['Version'] ?? '' ),
	'history'   => array_values( array_map( static function ( $item ) { return basename( (string) ( ( (array) $item )['file'] ?? '' ) ); }, (array) get_option( 'digitalisimo_backups_history', array() ) ) ),
	'job'       => \Digitalisimo\Migration\Full::load( $network ),
	'blocked'   => is_file( WP_CONTENT_DIR . '/probe/http-blocked.log' ) ? array_values( array_unique( array_filter( explode( "\n", (string) file_get_contents( WP_CONTENT_DIR . '/probe/http-blocked.log' ) ) ) ) ) : array(),
	'redirect'  => json_decode( (string) @file_get_contents( WP_CONTENT_DIR . '/probe/activation-redirect.json' ), true ),
	'downloads' => is_file( WP_CONTENT_DIR . '/probe/downloads.log' ) ? array_values( array_filter( explode( "\n", (string) file_get_contents( WP_CONTENT_DIR . '/probe/downloads.log' ) ) ) ) : array(),
	'sites'     => array(),
	// Escenario salt: la sal en wp-config.php, la clave sin sal del otro sitio intacta y la caché global sin vaciar.
	'salt'      => array(
		'marks'    => substr_count( (string) file_get_contents( ABSPATH . 'wp-config.php' ), '/* DIGITALÍSIMO: sal propia de la caché de objetos */' ),
		'defined'  => defined( 'WP_CACHE_KEY_SALT' ) ? (string) WP_CACHE_KEY_SALT : '',
		'neighbor' => is_file( '/digi-cache/' . md5( $GLOBALS['table_prefix'] . 'options:alloptions' ) . '.txt' ) ? (string) ( unserialize( (string) file_get_contents( '/digi-cache/' . md5( $GLOBALS['table_prefix'] . 'options:alloptions' ) . '.txt' ) )['home'] ?? '' ) : '',
		'flushed'  => is_file( '/digi-cache/GLOBAL_FLUSH' ),
		'raw_home' => (string) ( wp_load_alloptions()['home'] ?? '' ),
		'db_home'  => (string) $GLOBALS['wpdb']->get_var( "SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'home'" ),
		'state'    => class_exists( 'Digitalisimo_Cache_Salt', false ) ? (string) ( Digitalisimo_Cache_Salt::last()['state'] ?? '' ) : 'sin clase',
		'notes'    => is_array( $last ) ? array_values( array_filter( (array) ( $last['notes'] ?? array() ), static function ( $n ) { return false !== stripos( (string) $n, 'caché de objetos' ); } ) ) : array(),
	),
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
		// Escenario rerun: el gancho y el shortcode del hijo siguen en la portada con el tema propio.
		'child_foot'    => false !== strpos( $body, 'id="hijo-foot"' ),
		// Sin el shortcode registrado WordPress deja «[hijo_saludo]» tal cual: el texto que devuelve prueba que corre (la
		// descripción de SEO puede seguir citando el contenido sin procesar).
		'shortcode'     => false !== strpos( $body, 'Hola desde el tema hijo' ),
		'tools_active'  => in_array( 'digitalisimo-tools/digitalisimo-tools.php', (array) get_option( 'active_plugins', array() ), true ),
		// Diagnóstico de Código PHP: el indicador (con carga automática) que mira el front, en la caché y en la base, y el final de la portada.
		'tools_flag'    => array( 'cache' => (string) ( wp_load_alloptions()['digitalisimo_tools_php_on'] ?? '' ), 'db' => (string) $GLOBALS['wpdb']->get_var( "SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'digitalisimo_tools_php_on'" ), 'run_db' => null !== $GLOBALS['wpdb']->get_var( "SELECT option_id FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'digitalisimo_tools_php_run'" ) ),
		'body_tail'     => substr( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '#<(script|style)\b(?![^>]*hijo)[^>]*>.*?</\1>#s', '', $body ) ) ), -400 ),
		'salt_state'    => class_exists( 'Digitalisimo_Cache_Salt', false ) ? (string) ( Digitalisimo_Cache_Salt::last()['state'] ?? '' ) : '',
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
