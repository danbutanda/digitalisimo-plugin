<?php
/**
 * Red de prueba para «Migrar toda la red»: Elementor (plugin) activado en toda la red, Element Pack activado por sitio
 * en los dos sitios, DIGITALÍSIMO Elements y DIGITALÍSIMO Migración en toda la red. En cada sitio, páginas con widgets de
 * Element Pack (casos de tests/legacy) y con alias de widgets fusionados (con CSS propio limitado al elemento), una
 * revisión con contenido de Element Pack y, en el subsitio, CSS adicional que nombra las clases de un alias (ese alias
 * no debe convertirse allí). `?digi-migrate-probe=1` devuelve el estado de cada sitio en JSON.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$out = '/wordpress/wp-content/probe';
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
// Errores fatales de cualquier petición y sonda de estado (sin sesión: es una prueba local).
file_put_contents( WPMU_PLUGIN_DIR . '/digi-migrate-probe.php', <<<'PHP'
<?php
register_shutdown_function( static function () {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/probe/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . ( $_POST['action'] ?? '' ) . "\n" . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line'] . "\n\n", FILE_APPEND );
	}
} );
add_action( 'init', static function () {
	if ( empty( $_GET['digi-migrate-probe'] ) ) {
		return;
	}
	global $wpdb;
	$pages = array();
	foreach ( get_posts( array( 'post_type' => array( 'page', 'revision' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
		$raw = (string) get_metadata( 'post', $post->ID, '_elementor_data', true );
		if ( '' === $raw ) {
			continue;
		}
		preg_match_all( '/"widgetType":"([a-z0-9-]+)"/', $raw, $m );
		$key           = 'revision' === $post->post_type ? 'revision-of-' . get_post( $post->post_parent )->post_name . '-' . $post->ID : $post->post_name;
		$pages[ $key ] = array( 'widgets' => array_values( array_unique( $m[1] ) ), 'backup' => '' !== (string) get_metadata( 'post', $post->ID, '_digitalisimo_ep_backup', true ), 'legacy_keys' => (bool) preg_match( '/"(ep_|element_pack_|bdt_)[a-z0-9_]*"/', $raw ) );
	}
	// CSS generado por Elementor: estado guardado de cada documento y si su archivo existe.
	$css     = array();
	$uploads = wp_upload_dir();
	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_css'" ) as $row ) {
		$meta                       = maybe_unserialize( $row->meta_value );
		$file                       = $uploads['basedir'] . '/elementor/css/post-' . $row->post_id . '.css';
		$css[ (int) $row->post_id ] = ( is_array( $meta ) ? (string) ( $meta['status'] ?? '' ) : '?' ) . ( file_exists( $file ) ? ' · archivo' : ' · sin archivo' );
	}
	wp_send_json( array(
		'blog'      => get_current_blog_id(),
		'active'    => (array) get_option( 'active_plugins', array() ),
		'network'   => array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ),
		'bundled'   => defined( 'DIGITALISIMO_ELEMENTS_CORE' ),
		'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		'ep_loaded' => defined( 'BDTEP_VER' ),
		'job'       => get_option( 'digitalisimo_migration_job', null ),
		'lock'      => get_option( 'digitalisimo_migration_lock', null ),
		'report'    => get_option( 'digitalisimo_migration_report', null ),
		'network_job' => get_site_option( 'digitalisimo_migration_network_job', null ),
		'pages'     => $pages,
		'css'       => $css,
		'kit'       => (int) get_option( 'elementor_active_kit' ),
		'mode'      => get_option( 'digitalisimo_elements_core_mode' ),
	) );
} );
PHP
);
// Plugins: Elementor, DIGITALÍSIMO Elements y Migración en toda la red; Element Pack en cada sitio.
foreach ( array( 'elementor/elementor.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ) as $plugin ) {
	$result = activate_plugin( $plugin, '', true );
	if ( is_wp_error( $result ) ) {
		file_put_contents( $out . '/setup-error.txt', $plugin . ': ' . $result->get_error_message() . "\n", FILE_APPEND );
	}
}
if ( ! get_site( 2 ) ) {
	wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
}
$modules = array();
foreach ( glob( WP_PLUGIN_DIR . '/bdthemes-element-pack/modules/*', GLOB_ONLYDIR ) as $dir ) {
	$modules[ basename( $dir ) ] = 'on';
}
$fixture = static function ( $widget, $case ) {
	$cases    = json_decode( (string) file_get_contents( '/wordpress/wp-content/digi-legacy-fixtures/' . $widget . '.json' ), true );
	$settings = (array) ( $cases[ $case ] ?? array() );
	foreach ( array_keys( $settings ) as $key ) {
		if ( '_' === $key[0] ) {
			unset( $settings[ $key ] );
		}
	}
	return $settings;
};
$image   = home_url( '/wp-content/plugins/elementor/assets/images/placeholder.png' );
$replace = static function ( $settings ) use ( $image ) {
	return json_decode( str_replace( '__IMAGE__', $image, (string) wp_json_encode( $settings ) ), true );
};
$pages = array(
	// Element Pack: se convierten al formato propio. `text` indica si su texto debe coincidir (sin diferencias declaradas).
	'digi-mg-accordion'       => array( 'widget' => 'bdt-accordion', 'settings' => $fixture( 'bdt-accordion', 'custom' ), 'text' => true ),
	'digi-mg-creative-button' => array( 'widget' => 'bdt-creative-button', 'settings' => $fixture( 'bdt-creative-button', 'custom' ), 'text' => true ),
	'digi-mg-brand-carousel'  => array( 'widget' => 'bdt-brand-carousel', 'settings' => $fixture( 'bdt-brand-carousel', 'custom' ), 'text' => false ),
	// Alias de widgets fusionados, con CSS propio que nombra sus clases de antes.
	'digi-mg-alias-logo-grid' => array( 'widget' => 'digitalisimo-logo-grid', 'settings' => array( 'layout' => 'border', 'custom_css' => 'selector .digi-logo-grid__image{outline:3px solid rgb(10, 20, 30)}', 'logo_list' => array( array( '_id' => 'q1', 'image' => array( 'url' => '__IMAGE__' ), 'name' => 'Logo A', 'description' => 'Detalle', 'link' => array( 'url' => 'https://example.test/a' ), 'logo_tooltip' => 'yes' ), array( '_id' => 'q2', 'image' => array( 'url' => '__IMAGE__' ), 'name' => 'Logo B' ) ) ) ),
	'digi-mg-alias-creative-button' => array( 'widget' => 'digitalisimo-creative-button', 'settings' => array( 'text' => 'Creativo', 'link' => array( 'url' => 'https://example.test/' ), 'effect' => 'lift', 'custom_css' => 'selector .digi-creative-button{letter-spacing:3px}' ) ),
	'digi-mg-alias-content-switcher' => array( 'widget' => 'digitalisimo-content-switcher', 'settings' => array( 'switcher_items' => array( array( '_id' => 's1', 'title' => 'Mensual', 'content' => '<p>Precio mensual</p>' ), array( '_id' => 's2', 'title' => 'Anual', 'content' => '<p>Precio anual</p>', 'switcher_active' => 'yes' ) ) ) ),
	'digi-mg-alias-icon-mobile-menu' => array( 'widget' => 'digitalisimo-icon-mobile-menu', 'settings' => array( 'menu_style' => 'style-4', 'menu_items' => array( array( '_id' => 'm1', 'menu_text' => 'Inicio', 'menu_icon' => array( 'value' => 'fas fa-home', 'library' => 'fa-solid' ), 'link' => array( 'url' => 'https://example.test/' ) ), array( '_id' => 'm2', 'menu_text' => 'Buscar', 'menu_icon' => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ), 'link' => array( 'url' => 'https://example.test/buscar' ) ) ) ) ),
	'digi-mg-alias-brand-grid' => array( 'widget' => 'digitalisimo-brand-grid', 'settings' => array( 'brand_event' => 'always', 'brand_items' => array( array( '_id' => 'g1', 'image' => array( 'url' => '__IMAGE__' ), 'brand_name' => 'Marca A', 'link' => array( 'url' => 'https://example.test/a' ) ), array( '_id' => 'g2', 'image' => array( 'url' => '__IMAGE__' ), 'brand_name' => 'Marca B' ) ) ) ),
);
$manifest = array();
foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	$activated = activate_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php', '', false );
	if ( is_wp_error( $activated ) ) {
		file_put_contents( $out . '/setup-error.txt', 'Element Pack en el sitio ' . $blog . ': ' . $activated->get_error_message() . "\n", FILE_APPEND );
	}
	foreach ( array( 'element_pack_active_modules', 'element_pack_elementor_extend', 'element_pack_third_party_widget' ) as $option ) {
		update_option( $option, $modules );
	}
	update_option( 'elementor_element_cache_ttl', 'disable' );
	update_option( 'elementor_onboarded', true );
	foreach ( $pages as $slug => $page ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
		$data = array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'container', 'settings' => (object) array(), 'elements' => array(
			array( 'id' => 'w' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => $page['widget'], 'settings' => (object) $replace( $page['settings'] ), 'elements' => array() ),
		) ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		// Una revisión de Elementor con el mismo contenido de Element Pack (sus metadatos van al id de la revisión).
		// Dos: una anterior (pasa por la comprobación de páginas sobrescritas con el motor real) y la última.
		if ( 'digi-mg-accordion' === $slug ) {
			foreach ( array( 1, 2 ) as $n ) {
				$revision = wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $id, 'post_name' => $id . '-revision-v' . $n, 'post_title' => $slug ) );
				update_metadata( 'post', $revision, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			}
		}
	}
	// En el subsitio, CSS adicional que nombra una clase de la Cuadrícula de marcas: ese alias se queda como está.
	if ( 2 === $blog && ! wp_get_custom_css() ) {
		wp_update_custom_css_post( '.digi-brand-grid__logo{border-bottom:2px solid rgb(200, 0, 0)}' );
	}
	restore_current_blog();
}
foreach ( $pages as $slug => $page ) {
	$manifest[ $slug ] = array( 'widget' => $page['widget'], 'text' => ! empty( $page['text'] ), 'alias' => 0 === strpos( $page['widget'], 'digitalisimo-' ) );
}
file_put_contents( $out . '/pages.json', wp_json_encode( $manifest ) );
update_site_option( 'digi_migrate_ready', 1 );
