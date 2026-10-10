<?php
/** Prepara la red de Playground: plugins activos en red, subsitio y extensiones de Element Pack. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'elementor/elementor.php', 'digitalisimo-elements/pro-elements.php', 'bdthemes-element-pack/bdthemes-element-pack.php' ) as $plugin ) {
	activate_plugin( $plugin, '', true );
}
// Plugins reales instalados con EXTRA_PLUGINS.
foreach ( array_filter( explode( ',', (string) getenv( 'DIGI_EXTRA' ) ) ) as $slug ) {
	foreach ( array_keys( get_plugins( '/' . $slug ) ) as $file ) {
		activate_plugin( $slug . '/' . $file, '', true );
		break;
	}
}
// Plugins de prueba montados con FAKE_PLUGINS=1 (sólo registran shortcodes).
foreach ( get_plugins() as $file => $plugin ) {
	if ( 0 === strpos( (string) $plugin['Name'], 'Digitalisimo A/B fake' ) ) {
		activate_plugin( $file, '', true );
	}
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
	// Menú de WordPress con un submenú para los widgets de navegación.
	if ( ! wp_get_nav_menu_object( 'principal' ) ) {
		$menu = wp_create_nav_menu( 'Principal' );
		$add  = static function ( $title, $path, $parent = 0 ) use ( $menu ) {
			return wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => $title, 'menu-item-url' => home_url( $path ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent ) );
		};
		$add( 'Inicio', '/' );
		$services = $add( 'Servicios', '/servicios/' );
		$add( 'Diseño web', '/servicios/web/', $services );
		$add( 'SEO', '/servicios/seo/', $services );
		$add( 'Contacto', '/contacto/' );
	}
	// Entradas con categoría y etiqueta para los widgets de entradas (marcador «__POST__»).
	if ( ! get_page_by_path( 'digi-ab-post-1', OBJECT, 'post' ) ) {
		$category = wp_create_category( 'Noticias' );
		foreach ( array( 1, 2, 3 ) as $n ) {
			wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada A/B ' . $n, 'post_name' => 'digi-ab-post-' . $n, 'post_content' => 'Contenido de la entrada ' . $n . ' para comparar.', 'post_category' => array( $category ), 'tags_input' => array( 'destacado' ), 'post_date' => '2026-01-0' . $n . ' 10:00:00' ) );
		}
	}
	// Plantilla de Elementor para los casos que muestran una plantilla (marcador «__TEMPLATE__»).
	if ( ! get_page_by_path( 'digi-ab-template', OBJECT, 'elementor_library' ) ) {
		$template = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'digi-ab-template', 'post_name' => 'digi-ab-template' ) );
		$heading  = array( array( 'id' => 'tpl0001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido de plantilla' ), 'elements' => array() ) );
		update_post_meta( $template, '_elementor_edit_mode', 'builder' );
		update_post_meta( $template, '_elementor_template_type', 'section' );
		update_post_meta( $template, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'tplsec1', 'elType' => 'container', 'settings' => array(), 'elements' => $heading ) ) ) ) );
	}
	restore_current_blog();
}
