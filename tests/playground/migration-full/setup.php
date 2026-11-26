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
	// Widgets de PRO en borradores (fuera de la comprobación de la portada): una Cita, que pasa a la propia, y, en un
	// sitio individual, un Carrito de WooCommerce sin WooCommerce junto a otra Cita: el documento se queda como estaba y
	// va a «Necesita tu atención» con el enlace a su editor (y, con contenido pendiente, Element Pack no se borra). En la
	// red no hay nada pendiente: ahí se comprueba que se borra.
	$quote = array( 'blockquote', array( 'blockquote_content' => 'Las plantas también escuchan.', 'author_name' => 'Vivero' ) );
	$pro   = array( 'Cita de prueba' => array( 'blockquote' => $quote ) );
	if ( ! $network ) {
		$pro['Carrito de prueba'] = array( 'woocommerce-cart' => array( 'woocommerce-cart', array() ), 'blockquote' => $quote );
	}
	foreach ( $pro as $title => $widgets ) {
		$draft    = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title ) );
		$elements = array();
		foreach ( array_values( $widgets ) as $i => $widget ) {
			$elements[] = array( 'id' => 'w' . $draft . $i, 'elType' => 'widget', 'widgetType' => $widget[0], 'settings' => $widget[1], 'elements' => array() );
		}
		update_post_meta( $draft, '_elementor_edit_mode', 'builder' );
		update_post_meta( $draft, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c' . $draft, 'elType' => 'container', 'settings' => array(), 'elements' => $elements ) ) ) ) );
		update_option( 'digi_probe_' . array_keys( $widgets )[0], (int) $draft );
	}
	if ( $network ) {
		restore_current_blog();
	}
}
if ( 'child' === $scenario ) {
	// Caso real del 2026-11-20: el tema propio 1.0.0 ya instalado (sin lector del hijo), un hijo de Hello con la plantilla
	// oficial más PHP de wp_head y wp_footer, y DIGITALÍSIMO Tools activo (dueño de Código PHP).
	$zip = new ZipArchive();
	$zip->open( $probe . '/theme-old.zip' );
	$zip->extractTo( WP_CONTENT_DIR . '/themes' );
	$zip->close();
	@mkdir( WP_CONTENT_DIR . '/themes/hello-hijo', 0777, true );
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/style.css', "/*\nTheme Name: Hello Hijo\nTemplate: hello-elementor\nVersion: 2.0.0\n*/\nbody{background:#fafafa}\n" );
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/functions.php', "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\ndefine( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );\nfunction hello_elementor_child_scripts_styles() {\n\twp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );\n}\nadd_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );\nadd_action( 'wp_head', function () {\n\techo '<style id=\"hijo-head\" data-hello=\"' . esc_attr( HELLO_ELEMENTOR_VERSION ) . '\">.hijo{color:#c00}</style>';\n} );\nadd_action( 'wp_footer', function () {\n\techo '<script id=\"hijo-foot\">window.hijo=1;</script>';\n} );\n" );
	wp_clean_themes_cache();
	switch_theme( 'hello-hijo' );
	activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php' );
}
if ( 'rerun' === $scenario ) {
	// Caso real de digitalisimo.lat (2026-11-23). Sin el tema propio ni DIGITALÍSIMO Tools instalados: Migrar los descarga.
	@mkdir( WP_CONTENT_DIR . '/themes/hello-hijo', 0777, true );
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/style.css', "/*\nTheme Name: Hello Elementor Child\nTemplate: hello-elementor\nVersion: 2.0.0\n*/\nbody{background:#fafafa}\n" );
	// La plantilla oficial del hijo más PHP propio: un gancho y un shortcode que usa la portada.
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/functions.php', "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\ndefine( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );\nfunction hello_elementor_child_scripts_styles() {\n\twp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );\n}\nadd_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );\nadd_action( 'wp_footer', function () {\n\techo '<script id=\"hijo-foot\">window.hijo=1;</script>';\n} );\nadd_shortcode( 'hijo_saludo', function () {\n\treturn '<span id=\"hijo-saludo\">Hola desde el tema hijo</span>';\n} );\n" );
	wp_clean_themes_cache();
	switch_theme( 'hello-hijo' );
	$home = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio', 'post_content' => '<p>Vivero Las Flores: plantas de interior y macetas artesanales.</p>[hijo_saludo]' ) );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $home );
	// El informe «completado» que dejó Migración 1.0.32 el 19-nov (con la forma de entonces): el hijo se quedaba.
	update_option( 'digitalisimo_migration_full_last', array(
		'v' => 1, 'version' => '1.0.32', 'network' => false, 'run' => 'm19nov', 'started' => 1763604000, 'updated' => 1763604600, 'finished' => 1763604600,
		'user' => 1, 'caps' => array(), 'stage' => 'finish', 'done' => true, 'failed' => '', 'error' => '', 'attempts' => 0, 'cursor' => 0, 'changed_at' => 0,
		'plan' => array( 'rows' => array(), 'needs' => array( 'digitalisimo-backups', 'digitalisimo-elements' ), 'theme' => array() ), 'sites' => array(),
		'backup' => array( 'job' => 'j19', 'file' => 'digitalisimo-backup-digitalisimo-lat-20261119-200445-abc123.zip', 'size' => 1048576, 'sha256' => str_repeat( 'a', 64 ), 'ok' => true ),
		'installed' => array(), 'activated' => array(), 'content' => array(), 'imports' => array(), 'retired' => array(),
		'theme' => array( 1 => array( 'from' => 'hello-hijo', 'name' => 'Hello Elementor Child', 'hello' => true, 'child' => true, 'own' => false, 'safe' => false, 'switched' => false, 'reason' => 'El sitio usa un tema hijo de Hello («Hello Elementor Child»): su CSS y su PHP no se trasladan solos (el PHP va al editor de fragmentos de DIGITALÍSIMO Tools). Se queda; cámbialo en Apariencia cuando los hayas pasado.' ) ),
		'verify' => array(), 'deleted' => array(), 'kept' => array(), 'notes' => array(),
	), false );
	if ( is_file( $probe . '/tools-old.zip' ) ) {
		// TOOLS_OLD=1: Tools 1.0.43 instalado y activo (sin la vía de Migrar para activar el functions.php del hijo).
		$zip = new ZipArchive();
		$zip->open( $probe . '/tools-old.zip' );
		$zip->extractTo( WP_PLUGIN_DIR );
		$zip->close();
		wp_clean_plugins_cache( false );
		activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php' );
		file_put_contents( $probe . '/tools-old', '1' );
	}
	// Y su referencia de la portada de esa pasada (sin versión, de Migración 1.0.32): no debe compararse con ella (revisión
	// adversarial NUEVA 3), o la portada de hoy, con otro contenido, haría volver atrás sin motivo.
	update_option( 'digitalisimo_migration_full_baseline', array( 'started' => 1763604000, 'checks' => array( array( 1, home_url( '/' ) ) ) ), false );
	update_option( 'digitalisimo_migration_full_snap_' . md5( '1763604000|1|' . home_url( '/' ) ), array( 'status' => 200, 'text' => 'Portada del 19 de noviembre con otro contenido completamente distinto al de hoy, de muchas palabras', 'words' => 18 ), false );
	// La caché de objetos persistente (al final: esta petición no la usa).
	@mkdir( '/digi-cache', 0777, true );
	copy( $probe . '/object-cache-nosalt.php', WP_CONTENT_DIR . '/object-cache.php' );
}
if ( 'network' === $scenario && is_file( $probe . '/tools-old.zip' ) ) {
	// TOOLS_OLD=1 en la red: un hijo de Hello con PHP (un gancho) en el segundo sitio y Tools 1.0.43 activado en toda la red.
	@mkdir( WP_CONTENT_DIR . '/themes/hello-hijo', 0777, true );
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/style.css', "/*\nTheme Name: Hello Elementor Child\nTemplate: hello-elementor\nVersion: 2.0.0\n*/\nbody{background:#fafafa}\n" );
	file_put_contents( WP_CONTENT_DIR . '/themes/hello-hijo/functions.php', "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\nadd_action( 'wp_footer', function () {\n\techo '<script id=\"hijo-foot\">window.hijo=1;</script>';\n} );\n" );
	wp_clean_themes_cache();
	WP_Theme::network_enable_theme( 'hello-hijo' );
	switch_to_blog( $sites[1] );
	switch_theme( 'hello-hijo' );
	restore_current_blog();
	$zip = new ZipArchive();
	$zip->open( $probe . '/tools-old.zip' );
	$zip->extractTo( WP_PLUGIN_DIR );
	$zip->close();
	wp_clean_plugins_cache( false );
	activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php', '', true );
	file_put_contents( $probe . '/tools-old', '1' );
}
if ( 'salt' === $scenario ) {
	// Incidente de plomerito: la caché de objetos compartida (drop-in de prueba) ya tiene, sin sal, las opciones de otro
	// sitio del servidor; desde la petición siguiente este WordPress lee su dirección.
	require $probe . '/seed-neighbor.php';
}
file_put_contents( $probe . '/setup.done', '1' );
