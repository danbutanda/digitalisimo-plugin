<?php
/**
 * Sitio de partida de migration-full.sh (sólo Playground): Hello Elementor 3.5.1 oficial activo, Element Pack Pro,
 * Rank Math y UpdraftPlus de prueba (sólo su cabecera, como los de f8: sin código del proveedor), DIGITALÍSIMO Migración
 * sola activa y contenido con metas de Rank Math y, en borradores, una Cita y un Carrito de WooCommerce de PRO. En una red (escenario
 * `network`), además un segundo sitio con lo mismo, Element Pack y UpdraftPlus activados en toda la red y Rank Math por
 * sitio.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$probe    = '/wordpress/wp-content/probe';
$scenario = trim( (string) file_get_contents( $probe . '/scenario' ) );
$network  = 'network' === $scenario;

// El mu-plugin de la prueba: ZIP locales, índice de la Release y sin peticiones fuera del sitio.
@mkdir( WP_CONTENT_DIR . '/mu-plugins', 0777, true );
copy( $probe . '/mu-probe.php', WP_CONTENT_DIR . '/mu-plugins/digi-migration-full-probe.php' );

// Plugins de prueba con sólo la cabecera.
$fakes = array(
	'bdthemes-element-pack/bdthemes-element-pack.php' => "Plugin Name: Element Pack Pro\n * Version: 10.0.1",
	'seo-by-rank-math/rank-math.php'                  => "Plugin Name: Rank Math SEO\n * Version: 1.0.250",
	'updraftplus/updraftplus.php'                     => "Plugin Name: UpdraftPlus - Backup/Restore\n * Version: 1.26.14",
);
foreach ( $fakes as $file => $header ) {
	@mkdir( dirname( WP_PLUGIN_DIR . '/' . $file ), 0777, true );
	$code = "<?php\n/**\n * {$header}\n * Description: Plugin de prueba de migration-full.sh: sólo la cabecera.\n */\ndefined( 'ABSPATH' ) || exit;\n";
	if ( 'seo-by-rank-math/rank-math.php' === $file ) {
		// Como Rank Math: con su constante, DIGITALÍSIMO SEO no publica señales mientras esté activo.
		$code .= "if ( ! defined( 'RANK_MATH_VERSION' ) ) define( 'RANK_MATH_VERSION', '1.0.250' );\n";
	}
	file_put_contents( WP_PLUGIN_DIR . '/' . $file, $code );
}
@mkdir( WP_CONTENT_DIR . '/updraft', 0777, true );

// Hello Elementor 3.5.1 oficial (descomprimido por migration-full.sh tras comprobar su suma).
$copy = static function ( $from, $to ) use ( &$copy ) {
	@mkdir( $to, 0777, true );
	foreach ( scandir( $from ) as $name ) {
		if ( '.' === $name || '..' === $name ) {
			continue;
		}
		is_dir( "$from/$name" ) ? $copy( "$from/$name", "$to/$name" ) : copy( "$from/$name", "$to/$name" );
	}
};
$copy( $probe . '/hello-elementor', WP_CONTENT_DIR . '/themes/hello-elementor' );

$sites = array( get_current_blog_id() );
if ( $network ) {
	$second = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'segundo/', 'Segundo sitio', 1 );
	$sites[] = (int) $second;
	WP_Theme::network_enable_theme( 'hello-elementor' );
	activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php', '', true );
	activate_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php', '', true );
	activate_plugin( 'updraftplus/updraftplus.php', '', true );
} else {
	activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' );
	activate_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php' );
	activate_plugin( 'updraftplus/updraftplus.php' );
}

foreach ( $sites as $site_id ) {
	if ( $network ) {
		switch_to_blog( $site_id );
	}
	activate_plugin( 'seo-by-rank-math/rank-math.php' );
	switch_theme( 'hello-elementor' );
	update_option( 'blogname', 1 === (int) $site_id ? 'Vivero Las Flores' : 'Segundo Vivero' );
	update_option( 'rank-math-options-titles', array( 'title_separator' => '-' ) );
	$text = 'Plantas de interior, macetas artesanales y envíos semanales a toda la ciudad desde nuestro vivero familiar.';
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sobre nosotros', 'post_content' => '<p>' . $text . '</p>' ) );
	update_post_meta( $page, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'e4f5a6b', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>' . $text . '</p>' ), 'elements' => array() ) ) ) ) ) ) );
	update_post_meta( $page, 'rank_math_title', 'Sobre el vivero familiar' );
	update_post_meta( $page, 'rank_math_description', 'Plantas y macetas artesanales con envío semanal.' );
	update_option( 'digi_probe_page', (int) $page );
	// Widgets de PRO en borradores (fuera de la comprobación de la portada): una Cita, que pasa a la propia, y un Carrito
	// de WooCommerce sin WooCommerce, que se queda y va a «Necesita tu atención» con el enlace a su editor.
	foreach ( array( 'Cita de prueba' => array( 'blockquote', array( 'blockquote_content' => 'Las plantas también escuchan.', 'author_name' => 'Vivero' ) ), 'Carrito de prueba' => array( 'woocommerce-cart', array() ) ) as $title => $widget ) {
		$draft = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title ) );
		update_post_meta( $draft, '_elementor_edit_mode', 'builder' );
		update_post_meta( $draft, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c' . $draft, 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'w' . $draft, 'elType' => 'widget', 'widgetType' => $widget[0], 'settings' => $widget[1], 'elements' => array() ) ) ) ) ) ) );
		update_option( 'digi_probe_' . $widget[0], (int) $draft );
	}
	if ( $network ) {
		restore_current_blog();
	}
}
file_put_contents( $probe . '/setup.done', '1' );
