<?php
/**
 * Sitio para la prueba del diseño compartido: los siete plugins activos y un mu-plugin que, con la cookie
 * `digi_ui_plugins` (archivos separados por comas), deja activo sólo ese conjunto en la petición. Así cada plugin se
 * prueba solo, como si fuera el único instalado, y todos juntos, sin reiniciar el servidor.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
try {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ui-plugins.php', '<?php add_filter( "option_active_plugins", static function ( $plugins ) { $c = isset( $_COOKIE["digi_ui_plugins"] ) ? (string) $_COOKIE["digi_ui_plugins"] : ""; if ( "" === $c ) { return $plugins; } $only = array_filter( explode( ",", $c ) ); return array_values( array_intersect( (array) $plugins, $only ) ); }, 1 );' );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . "\n" . $e["message"] . " @ " . $e["file"] . ":" . $e["line"] . "\n\n", FILE_APPEND ); } } );' );
	$plugins = array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-migration/digitalisimo-migration.php' );
	foreach ( $plugins as $plugin ) {
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $plugin . ': ' . $result->get_error_message() );
		}
	}
	update_option( 'elementor_onboarded', true );
	file_put_contents( '/wordpress/wp-content/probe/ready.txt', 'ok' );
} catch ( Throwable $e ) {
	file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', $e->getMessage() );
}
