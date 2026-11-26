<?php
/** Activar y desactivar un plugin: Tools pide a LiteSpeed Cache que purgue lo guardado de la API. */
define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
activate_plugin( 'digi-extra/digi-extra.php' );
$after_activate = $GLOBALS['digi_ph_purge'] ?? array();
deactivate_plugins( 'digi-extra/digi-extra.php' );
file_put_contents( '/digi-ph/purge.json', wp_json_encode( array( 'activate' => $after_activate, 'all' => $GLOBALS['digi_ph_purge'] ?? array() ) ) );
