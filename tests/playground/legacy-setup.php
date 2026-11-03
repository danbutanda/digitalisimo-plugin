<?php
/**
 * Prepara la red de Playground: plugins activos en red, subsitio y extensiones de Element Pack. La copia de Element Pack
 * es la que montó ep-lite-pin.sh (por defecto Lite 10.0.1 oficial, en `bdthemes-element-pack-lite/`), según `ep.json`.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$digi_ab_ep = json_decode( (string) @file_get_contents( '/wordpress/wp-content/digi-ab/ep.json' ), true );
$digi_ab_ep = is_array( $digi_ab_ep ) && ! empty( $digi_ab_ep['plugin'] ) ? $digi_ab_ep : array( 'plugin' => 'bdthemes-element-pack/bdthemes-element-pack.php', 'slug' => 'bdthemes-element-pack' );
// Sin salida a la red hacia los dominios del proveedor (vendors.json): Element Pack los consulta desde la administración.
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents(
	WPMU_PLUGIN_DIR . '/digi-ab-offline.php',
	'<?php
// Sólo Playground (legacy-ab.sh): corta las peticiones del servidor a los dominios del proveedor y anota los fatales.
add_filter( "pre_http_request", static function ( $pre, $args, $url ) {
	if ( false !== $pre ) { return $pre; }
	$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	$v    = json_decode( (string) @file_get_contents( "/wordpress/wp-content/digi-ab/vendors.json" ), true );
	foreach ( array_merge( (array) ( $v["hosts"] ?? array() ), (array) ( $v["future"] ?? array() ) ) as $vendor ) {
		if ( $host === $vendor || substr( $host, -strlen( "." . $vendor ) ) === "." . $vendor ) {
			return new WP_Error( "digi_ab_offline", "legacy-ab: sin salida a " . $host );
		}
	}
	return $pre;
}, PHP_INT_MAX, 3 );
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/digi-ab/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );
'
);
// Con CORE=bundled el plugin Elementor no está instalado: DIGITALÍSIMO Elements trae su núcleo.
foreach ( array( 'elementor/elementor.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php', $digi_ab_ep['plugin'] ) as $plugin ) {
	if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
		// Element Pack Lite redirige a su pantalla y sale en `activated_plugin`: se activa en silencio (no tiene gancho
		// de activación propio) para que la preparación siga hasta el final.
		$activated = activate_plugin( $plugin, '', true, $plugin === $digi_ab_ep['plugin'] );
		if ( is_wp_error( $activated ) ) {
			file_put_contents( '/wordpress/wp-content/digi-ab/setup-error.txt', $plugin . ': ' . $activated->get_error_message() . "\n", FILE_APPEND );
		}
	}
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
	$digi_ab_blog = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
	if ( is_wp_error( $digi_ab_blog ) || ! get_site( 2 ) ) {
		file_put_contents( '/wordpress/wp-content/digi-ab/setup-error.txt', 'Subsitio: ' . ( is_wp_error( $digi_ab_blog ) ? $digi_ab_blog->get_error_message() : 'no se creó (' . wp_json_encode( $digi_ab_blog ) . ')' ) . "\n", FILE_APPEND );
	}
}
// Element Pack trae módulos apagados por defecto: se encienden todos para comparar.
$modules = array();
foreach ( glob( WP_PLUGIN_DIR . '/' . $digi_ab_ep['slug'] . '/modules/*', GLOB_ONLYDIR ) as $dir ) {
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
