<?php
/** Prepara la red de Playground: plugins activos en red, subsitio y extensiones de Element Pack. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'elementor/elementor.php', 'digitalisimo-elements/pro-elements.php', 'bdthemes-element-pack/bdthemes-element-pack.php' ) as $plugin ) {
	activate_plugin( $plugin, '', true );
}
if ( ! get_site( 2 ) ) {
	wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
}
// Element Pack trae módulos apagados por defecto: se encienden todos para comparar.
$modules = array();
foreach ( glob( WP_PLUGIN_DIR . '/bdthemes-element-pack/modules/*', GLOB_ONLYDIR ) as $dir ) {
	$modules[ basename( $dir ) ] = 'on';
}
foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	foreach ( array( 'element_pack_active_modules', 'element_pack_elementor_extend', 'element_pack_third_party_widget' ) as $option ) {
		update_option( $option, $modules );
	}
	restore_current_blog();
}
