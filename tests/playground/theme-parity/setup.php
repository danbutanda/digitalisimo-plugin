<?php
/**
 * Preparación de theme-parity.sh (sólo Playground): Hello 3.5.1 desde el ZIP de referencia, los hijos sintéticos
 * (`child`, típico y trasladable; `child-php`, con PHP que no se puede trasladar), contenido con encabezados, enlaces,
 * botones y formularios, menús, CSS adicional guardado con el tema de cada sitio activo, mods del hijo y ajustes de
 * Hello. Lo que necesita el constructor (kit con colores y tipografías globales y su «Estilo del tema», plantillas de
 * cabecera y pie) lo hace después `?tp=prepare` (mu-probe.php), con DIGITALÍSIMO Elements ya activo: en VIA=migrar lo
 * instala «Migrar».
 *
 * Sitios: individual → 1: hijo típico. Red → 1: Hello sin hijo (cabecera clásica), 2: hijo típico, 3: hijo con PHP.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

$probe   = '/wordpress/wp-content/probe';
$conf    = json_decode( (string) file_get_contents( $probe . '/conf.json' ), true );
$network = 'network' === $conf['scenario'];
$migrar  = 'migrar' === $conf['via'];

function tp_error( $message ) {
	file_put_contents( '/wordpress/wp-content/probe/setup-error.txt', $message . "\n", FILE_APPEND );
}

@mkdir( WPMU_PLUGIN_DIR, 0777, true );
copy( $probe . '/mu-probe.php', WPMU_PLUGIN_DIR . '/tp-probe.php' );

// Temas: Hello (descomprimido y comprobado por el arnés) y los dos hijos sintéticos.
$copy = static function ( $from, $to ) use ( &$copy ) {
	@mkdir( $to, 0777, true );
	foreach ( scandir( $from ) as $name ) {
		if ( '.' !== $name && '..' !== $name ) {
			is_dir( "$from/$name" ) ? $copy( "$from/$name", "$to/$name" ) : copy( "$from/$name", "$to/$name" );
		}
	}
};
$copy( $probe . '/hello-elementor', get_theme_root() . '/hello-elementor' );
$copy( $probe . '/child', get_theme_root() . '/tp-hello-child' );
$copy( $probe . '/child-php', get_theme_root() . '/tp-hello-child-php' );
// La fuente local del hijo: cualquier woff2 de la instalación (sólo importa que sea la misma en las dos fases).
$fonts = array_merge( glob( get_theme_root() . '/twentytwenty*/assets/fonts/*/*.woff2' ) ?: array(), glob( ABSPATH . WPINC . '/fonts/*.woff2' ) ?: array() );
if ( $fonts ) {
	copy( $fonts[0], get_theme_root() . '/tp-hello-child/fonts/tp-local.woff2' );
}
search_theme_directories( true );
wp_clean_themes_cache();
foreach ( array( 'hello-elementor', 'tp-hello-child', 'tp-hello-child-php' ) as $stylesheet ) {
	if ( ! wp_get_theme( $stylesheet )->exists() ) {
		tp_error( "Falta el tema $stylesheet" );
	}
}
if ( ! $migrar && ! wp_get_theme( 'digitalisimo-theme' )->exists() ) {
	tp_error( 'Falta el tema digitalisimo-theme (montado)' );
}

// Plugins: Elements montado (Apariencia) o Migración sola (Migrar instala Backups, Elements y el tema).
$plugin = $migrar ? 'digitalisimo-migration/digitalisimo-migration.php' : 'digitalisimo-elements/pro-elements.php';
$done   = activate_plugin( $plugin, '', $network );
if ( is_wp_error( $done ) ) {
	tp_error( "No se activó $plugin: " . $done->get_error_message() );
}

$sites = array( 1 => $network ? 'hello' : 'child' );
if ( $network ) {
	foreach ( array( 'hijo' => 'child', 'hijo-php' => 'child-php' ) as $path => $kind ) {
		$id = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . $path . '/', 'Sitio ' . $kind, 1 );
		if ( is_wp_error( $id ) ) {
			tp_error( 'No se creó el sitio ' . $path . ': ' . $id->get_error_message() );
			continue;
		}
		$sites[ (int) $id ] = $kind;
	}
	foreach ( array( 'hello-elementor', 'tp-hello-child', 'tp-hello-child-php' ) as $stylesheet ) {
		WP_Theme::network_enable_theme( $stylesheet );
	}
}
update_site_option( 'tp_sites', $sites );

$long = 'Texto de prueba para comparar Hello con el tema DIGITALÍSIMO: los colores, las fuentes, los enlaces y los botones deben verse igual.';
foreach ( $sites as $site_id => $kind ) {
	if ( $network ) {
		switch_to_blog( $site_id );
	}
	update_option( 'blogname', 'TP ' . $kind );
	update_option( 'blogdescription', 'Paridad del tema' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'show_avatars', 0 );
	update_option( 'elementor_onboarded', true );
	update_option( 'elementor_google_font', '0' );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	// Con «Migrar» el servidor corre con 3 workers (su verificación pide la portada al propio servidor) y Playground no
	// siempre ve, en otro worker, la carpeta de CSS del constructor recién vaciada por el cambio de tema: el CSS de cada
	// documento va en la página (como eligen muchos sitios) para medir el tema y no ese tropiezo del ensayo.
	if ( $migrar ) {
		update_option( 'elementor_css_print_method', 'internal' );
	}
	update_option( 'tp_kind', $kind );
	// Hello sin hijo: con su cabecera clásica (sin el experimento de cabecera y pie, que la haría configurable).
	update_option( 'elementor_experiment-hello-theme-header-footer', 'hello' === $kind ? 'inactive' : 'default' );
	// Ajustes de Hello que el paso lee: sin su meta description (la de SEO no está en la prueba).
	update_option( 'hello_elementor_settings_description_meta_tag', 'true' );

	$html  = '<h2 class="tp-h2">Encabezado dos</h2><h3 class="tp-h3">Encabezado tres</h3>';
	$html .= '<p class="tp-links">' . esc_html( $long ) . ' <a href="' . esc_url( home_url( '/' ) ) . '">Enlace del contenido</a>.</p>';
	$html .= '<p><button type="button" class="tp-btn">Botón del contenido</button></p>';
	$html .= '<form class="tp-form" onsubmit="return false"><label class="tp-label" for="tp-input">Campo</label><input id="tp-input" class="tp-input" type="text" value="Valor"><textarea class="tp-textarea">Área</textarea><select class="tp-select"><option>Uno</option></select></form>';
	$html .= '<p class="tp-custom">Párrafo con el CSS adicional.</p><p class="tp-child-box">Caja del hijo</p><p class="tp-extra">Hoja extra del hijo</p><p class="tp-font">Fuente local del hijo</p>';
	if ( 'child-php' === $kind ) {
		$html .= '<p>[tp_child_sc]</p>';
	}
	$tree = array(
		array(
			'id'       => 'tpc0001',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array( 'id' => 'tpw0001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Portada de paridad', 'header_size' => 'h1' ), 'elements' => array() ),
				array( 'id' => 'tpw0002', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Subtítulo con el color primario', 'header_size' => 'h2' ), 'elements' => array() ),
				array( 'id' => 'tpw0003', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => $html ), 'elements' => array() ),
				array( 'id' => 'tpw0004', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Botón del constructor', 'link' => array( 'url' => home_url( '/tp-entrada/' ) ) ), 'elements' => array() ),
			),
		),
	);
	// El mismo texto que pintará el constructor (con su cabecera y su pie): con VIA=migrar, «Migrar» guarda la portada
	// antes de instalar Elements y compara el texto visible al final.
	$plain = '<p>Cabecera del constructor Contacto</p><h1>Portada de paridad</h1><h2>Subtítulo con el color primario</h2>' . $html . '<p>Botón del constructor</p><p>Pie del constructor con un enlace.</p>';
	$front = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Portada TP', 'post_name' => 'tp-portada', 'post_content' => $plain ) );
	update_post_meta( $front, '_elementor_edit_mode', 'builder' );
	update_post_meta( $front, '_elementor_template_type', 'wp-page' );
	update_post_meta( $front, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada TP', 'post_name' => 'tp-entrada', 'post_date' => '2026-01-10 10:00:00', 'post_content' => $html ) );
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página TP', 'post_name' => 'tp-pagina', 'post_content' => '<p>' . esc_html( $long ) . '</p>' . $html ) );
	update_option( 'tp_ids', array( 'front' => $front, 'post' => $post, 'page' => $page ) );
	wp_delete_post( 1, true );
	wp_delete_post( 2, true );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front );

	$menu = wp_create_nav_menu( 'Principal TP' );
	if ( ! is_wp_error( $menu ) ) {
		foreach ( array( 'Inicio' => home_url( '/' ), 'Entrada' => home_url( '/tp-entrada/' ), 'Página' => home_url( '/tp-pagina/' ) ) as $label => $url ) {
			wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => $label, 'menu-item-url' => $url, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
		}
	}

	$stylesheet = array( 'hello' => 'hello-elementor', 'child' => 'tp-hello-child', 'child-php' => 'tp-hello-child-php' )[ $kind ];
	switch_theme( $stylesheet );
	// Mods del tema de origen (menú, un mod propio) y CSS adicional guardado con ese tema activo.
	set_theme_mod( 'nav_menu_locations', array( 'menu-1' => (int) $menu ) );
	set_theme_mod( 'tp_mod', 'valor del ' . $kind );
	wp_update_custom_css_post( ".tp-custom { color: rgb(10, 120, 200); border-left: 4px solid rgb(10, 120, 200); padding-left: 8px; }\n", array( 'stylesheet' => $stylesheet ) );
	delete_option( 'rewrite_rules' );
	if ( $network ) {
		restore_current_blog();
	}
}
file_put_contents( $probe . '/setup.done', '1' );
