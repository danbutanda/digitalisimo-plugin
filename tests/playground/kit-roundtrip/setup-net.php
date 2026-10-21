<?php
/**
 * Red de R6 en kit-roundtrip.sh (A9, CV-24): http://playground.test con el sitio 2 en /sub/ y el sitio 3 en /tercero/.
 *
 * - DIGITALÍSIMO Elements activo en toda la red (activate.php).
 * - Un administrador del sitio 2 que no es superadministrador: en una red no tiene unfiltered_html, así que no importa
 *   Custom Code y la carga de los marcadores pasa por kses (A4, CC-17).
 * - `upload_filetypes` de la red sin SVG: el análisis debe avisar de los tipos bloqueados (CC-17).
 * - Una página en los sitios 1 y 3 para comprobar que la importación en el 2 no los toca.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once __DIR__ . '/lib.php';

if ( ! is_multisite() ) {
	digi_k_fail( 'R6: el blueprint no activó la red' );
	return;
}
wp_set_current_user( 1 );
$sites = array( 'sub' => 2, 'tercero' => 3 );
foreach ( $sites as $path => $expected ) {
	$existing = get_blog_id_from_url( wp_parse_url( network_home_url(), PHP_URL_HOST ), '/' . $path . '/' );
	$blog     = $existing ? $existing : wpmu_create_blog( wp_parse_url( network_home_url(), PHP_URL_HOST ), '/' . $path . '/', 'Sitio ' . $path, 1 );
	if ( is_wp_error( $blog ) || (int) $blog !== $expected ) {
		digi_k_fail( "R6: el sitio /{$path}/ no quedó con el ID {$expected}" );
	} else {
		// El mismo tema que A y B (activate.php).
		switch_to_blog( (int) $blog );
		switch_theme( 'digitalisimo-theme' );
		restore_current_blog();
	}
}
update_site_option( 'upload_filetypes', 'jpg jpeg png gif webp avif mp3 mp4 webm ogg wav pdf csv json woff woff2 ttf otf' );

$admin = get_user_by( 'login', 'admin-sitio' );
$admin_id = $admin ? (int) $admin->ID : (int) wpmu_create_user( 'admin-sitio', 'admin-sitio-k', 'admin-sitio@example.com' );
add_user_to_blog( 2, $admin_id, 'administrator' );
digi_k_write( 'ids-net.json', array( 'site_admin' => $admin_id ) );

foreach ( array( 1, 3 ) as $blog ) {
	switch_to_blog( $blog );
	digi_k_clear_defaults();
	if ( ! get_page_by_path( 'digi-net-propia' ) ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página del sitio ' . $blog, 'post_name' => 'digi-net-propia' ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c0e0001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'e0e0001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sitio ' . $blog ), 'elements' => array() ) ) ) ) ) ) );
	}
	update_option( 'elementor_element_cache_ttl', 'disable' );
	restore_current_blog();
}
switch_to_blog( 2 );
digi_k_clear_defaults();
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'permalink_structure', '/%postname%/' );
restore_current_blog();
update_option( 'digi_k_ready', time() );
