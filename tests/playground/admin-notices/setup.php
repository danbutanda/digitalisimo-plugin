<?php
/** Activa los siete plugins (en la red, para toda la red) y deja /digi-an/ready.json; un fallo, en setup-error.txt. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
try {
	$network = is_multisite();
	foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $plugin ) {
		$done = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $done ) ) throw new RuntimeException( $plugin . ': ' . $done->get_error_message() );
	}
	update_option( 'elementor_onboarded', true );
	file_put_contents( '/digi-an/ready.json', wp_json_encode( array( 'multisite' => $network, 'wp' => $GLOBALS['wp_version'], 'php' => PHP_VERSION ) ) );
} catch ( Throwable $e ) {
	file_put_contents( '/digi-an/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
