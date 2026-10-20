<?php
/**
 * Preparación de la prueba A/B del tema (theme-switch.sh): instala Hello 3.5.1 desde la copia del arnés y el hijo
 * sintético, activa DIGITALÍSIMO Elements (núcleo incluido), SEO y Tools, deja el sitio en inglés y crea el contenido
 * común a todos los escenarios. Cada escenario se configura después con `?digi-ab=scenario` (mu-ab.php).
 * El tema DIGITALÍSIMO llega montado desde el paquete construido por el arnés, nunca desde el repositorio.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

const DIGI_PROBE = '/wordpress/wp-content/probe';

function digi_setup_error( $message ) {
	file_put_contents( DIGI_PROBE . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

wp_set_current_user( 1 );
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
foreach ( array( 'mu-ab.php' => 'digi-ab.php', 'mu-fragment.php' => 'digi-ab-fragment.php', 'dep.css' => 'digi-ab-dep.css' ) as $from => $to ) {
	if ( ! copy( DIGI_PROBE . '/' . $from, WPMU_PLUGIN_DIR . '/' . $to ) ) {
		digi_setup_error( "No se pudo copiar $from a mu-plugins" );
	}
}

// Temas: Hello desde el ZIP de referencia copiado por el arnés y el hijo sintético. El tema propio está montado.
WP_Filesystem();
if ( ! wp_get_theme( 'hello-elementor' )->exists() ) {
	$unzipped = unzip_file( DIGI_PROBE . '/hello.zip', get_theme_root() );
	if ( is_wp_error( $unzipped ) ) {
		digi_setup_error( 'No se pudo instalar Hello: ' . $unzipped->get_error_message() );
	}
}
if ( ! is_dir( get_theme_root() . '/digi-hello-child' ) ) {
	wp_mkdir_p( get_theme_root() . '/digi-hello-child' );
	$copied = copy_dir( DIGI_PROBE . '/child', get_theme_root() . '/digi-hello-child' );
	if ( is_wp_error( $copied ) ) {
		digi_setup_error( 'No se pudo copiar el hijo: ' . $copied->get_error_message() );
	}
}
search_theme_directories( true );
wp_clean_themes_cache();
foreach ( array( 'hello-elementor', 'digi-hello-child', 'digitalisimo-theme' ) as $stylesheet ) {
	$theme = wp_get_theme( $stylesheet );
	if ( ! $theme->exists() || $theme->errors() ) {
		digi_setup_error( "El tema $stylesheet no está disponible" . ( $theme->errors() ? ': ' . $theme->errors()->get_error_message() : '' ) );
	}
}

// Plugins: WooCommerce lo instala el blueprint (WOO=1); aquí el constructor, SEO y Tools.
foreach ( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-tools/digitalisimo-tools.php' ) as $plugin ) {
	$activated = activate_plugin( $plugin );
	if ( is_wp_error( $activated ) ) {
		digi_setup_error( "No se pudo activar $plugin: " . $activated->get_error_message() );
	}
}

// Sitio en inglés, sin avatares remotos ni fuentes de Google, sin asistentes que redirijan la administración.
update_option( 'WPLANG', '' );
update_option( 'blogname', 'DIGI AB' );
update_option( 'blogdescription', 'Lema de prueba AB' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'show_avatars', 0 );
update_option( 'thread_comments', 1 );
update_option( 'comments_notify', 0 );
update_option( 'moderation_notify', 0 );
update_option( 'posts_per_page', 2 );
update_option( 'elementor_onboarded', true );
update_option( 'elementor_google_font', '0' );
update_option( 'elementor_element_cache_ttl', 'disable' );
delete_transient( 'elementor_activation_redirect' );
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );

// Adjunto de prueba: el marcador del núcleo incluido copiado a Medios (con srcset si el servidor genera tamaños).
$image_id = (int) get_option( 'digi_ab_image' );
if ( ! $image_id || ! get_post( $image_id ) ) {
	$source = WP_PLUGIN_DIR . '/digitalisimo-elements/elementor/assets/images/placeholder.png';
	$upload = wp_upload_bits( 'digi-ab.png', null, (string) file_get_contents( $source ) );
	if ( empty( $upload['error'] ) ) {
		$image_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen AB', 'post_status' => 'inherit' ), $upload['file'] );
		update_post_meta( $image_id, '_wp_attachment_image_alt', 'Imagen AB' );
		$metadata = wp_generate_attachment_metadata( $image_id, $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $image_id, $metadata );
		}
		update_option( 'digi_ab_image', $image_id );
	} else {
		digi_setup_error( 'No se pudo subir la imagen de prueba: ' . $upload['error'] );
	}
}
$image = array( 'id' => $image_id, 'url' => (string) wp_get_attachment_url( $image_id ) );

/** Árbol del constructor: un contenedor con los widgets indicados. */
function digi_tree( $key, array $widgets ) {
	$children = array();
	foreach ( $widgets as $index => $widget ) {
		$children[] = array(
			'id'         => substr( md5( $key . $index ), 0, 7 ),
			'elType'     => $widget['elType'] ?? 'widget',
			'widgetType' => $widget['type'] ?? null,
			'settings'   => (object) ( $widget['settings'] ?? array() ),
			'elements'   => $widget['elements'] ?? array(),
		);
	}
	return array( array( 'id' => substr( md5( $key ), 0, 7 ), 'elType' => 'container', 'settings' => (object) array(), 'elements' => $children ) );
}

function digi_page( $slug, $title, array $args = array() ) {
	$existing = get_page_by_path( $slug, OBJECT, $args['post_type'] ?? 'page' );
	if ( $existing ) {
		return $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => $args['post_type'] ?? 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $args['content'] ?? '',
			'post_excerpt' => $args['excerpt'] ?? '',
			'post_date'    => $args['date'] ?? '2026-01-05 10:00:00',
			'post_author'  => 1,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		digi_setup_error( "No se pudo crear $slug: " . $id->get_error_message() );
		return 0;
	}
	if ( ! empty( $args['template'] ) ) {
		update_post_meta( $id, '_wp_page_template', $args['template'] );
	}
	if ( ! empty( $args['tree'] ) ) {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'post' === ( $args['post_type'] ?? 'page' ) ? 'wp-post' : 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $args['tree'] ) ) );
	}
	if ( ! empty( $args['page_settings'] ) ) {
		update_post_meta( $id, '_elementor_page_settings', $args['page_settings'] );
	}
	return $id;
}

$long = '<p>' . implode( '</p><p>', array_fill( 0, 4, 'Texto de prueba para comparar el tema anterior con el tema DIGITALÍSIMO con el mismo contenido.' ) ) . '</p>';

$front = digi_page(
	'digi-ts-portada',
	'Portada AB',
	array(
		'tree' => digi_tree(
			'portada',
			array(
				array( 'type' => 'heading', 'settings' => array( 'title' => 'Portada de prueba', 'header_size' => 'h2' ) ),
				array( 'type' => 'image', 'settings' => array( 'image' => $image ) ),
				array( 'type' => 'button', 'settings' => array( 'text' => 'Botón de prueba', 'link' => array( 'url' => home_url( '/digi-ts-constructor/' ) ) ) ),
				array( 'type' => 'digitalisimo-slider-optimizado', 'settings' => array( 'gallery_images' => array_fill( 0, 6, $image ) ) ),
			)
		),
	)
);
$blog = digi_page( 'digi-ts-blog', 'Blog AB' );
digi_page(
	'digi-ts-constructor',
	'Página del constructor',
	array(
		'tree' => digi_tree(
			'constructor',
			array(
				array( 'type' => 'heading', 'settings' => array( 'title' => 'Página construida', 'header_size' => 'h2' ) ),
				array( 'type' => 'text-editor', 'settings' => array( 'editor' => '<p class="digi-ts-marker">Párrafo con el CSS adicional del tema anterior.</p><p class="digi-ts-dep">Párrafo con la hoja de un tercero que depende de Hello.</p>' . $long ) ),
				array( 'type' => 'button', 'settings' => array( 'text' => 'Ir al blog', 'link' => array( 'url' => home_url( '/digi-ts-blog/' ) ) ) ),
			)
		),
	)
);
digi_page( 'digi-ts-canvas', 'Página Canvas', array( 'template' => 'elementor_canvas', 'tree' => digi_tree( 'canvas', array( array( 'type' => 'heading', 'settings' => array( 'title' => 'Lienzo sin cabecera' ) ), array( 'type' => 'text-editor', 'settings' => array( 'editor' => $long ) ) ) ) ) );
digi_page( 'digi-ts-ancho-completo', 'Página de ancho completo', array( 'template' => 'elementor_header_footer', 'tree' => digi_tree( 'full', array( array( 'type' => 'heading', 'settings' => array( 'title' => 'Ancho completo' ) ), array( 'type' => 'text-editor', 'settings' => array( 'editor' => $long ) ) ) ) ) );
digi_page( 'digi-ts-corta', 'Página corta', array( 'template' => 'elementor_header_footer', 'tree' => digi_tree( 'short', array( array( 'type' => 'heading', 'settings' => array( 'title' => 'Corta', 'header_size' => 'h2' ) ) ) ) ) );
digi_page(
	'digi-ts-carrusel',
	'Página con carrusel',
	array(
		'tree' => digi_tree(
			'carrusel',
			array(
				array(
					'type'     => 'nested-carousel',
					'settings' => array(
						'carousel_items' => array(
							array( '_id' => 'dab0001', 'slide_title' => 'Diapositiva 1' ),
							array( '_id' => 'dab0002', 'slide_title' => 'Diapositiva 2' ),
							array( '_id' => 'dab0003', 'slide_title' => 'Diapositiva 3' ),
						),
					),
					'elements' => array_map(
						static function ( $n ) {
							return array( 'id' => 'dabs' . $n . '00', 'elType' => 'container', 'isInner' => true, 'settings' => (object) array( '_title' => 'Diapositiva ' . $n ), 'elements' => array( array( 'id' => 'dabh' . $n . '00', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => (object) array( 'title' => 'Contenido de la diapositiva ' . $n ), 'elements' => array() ) ) );
						},
						array( 1, 2, 3 )
					),
				),
			)
		),
	)
);
digi_page( 'digi-ts-sin-titulo', 'Página sin título por fragmento', array( 'content' => '<p>El fragmento de Hello oculta el título de esta página.</p>' . $long ) );
digi_page( 'digi-ts-hijo', 'Página del hijo', array( 'content' => '<p>Antes del shortcode.</p>[digi_child_sc]<p>Después del shortcode.</p>' ) );

// Entradas: la de prueba (extracto, etiquetas, miniatura y comentarios anidados), la más reciente con el título oculto
// por el constructor (primera de los archivos) y tres más: con 2 entradas por página, la página 2 del blog tiene
// los dos enlaces de la paginación (FR-18).
$category = term_exists( 'digi-ts-cat', 'category' );
$category = $category ? (int) $category['term_id'] : (int) wp_insert_term( 'Categoría AB', 'category', array( 'slug' => 'digi-ts-cat', 'description' => 'Descripción de la categoría de prueba.' ) )['term_id'];
$post = digi_page( 'digi-ts-entrada', 'Entrada de prueba', array( 'post_type' => 'post', 'date' => '2026-01-10 10:00:00', 'excerpt' => 'Extracto escrito de la entrada de prueba.', 'content' => $long ) );
$hidden = digi_page( 'digi-ts-entrada-oculta', 'Entrada con el título oculto', array( 'post_type' => 'post', 'date' => '2026-01-12 10:00:00', 'content' => $long, 'page_settings' => array( 'hide_title' => 'yes' ) ) );
$second = digi_page( 'digi-ts-entrada-2', 'Segunda entrada de prueba', array( 'post_type' => 'post', 'date' => '2026-01-08 10:00:00', 'content' => $long ) );
$third = digi_page( 'digi-ts-entrada-3', 'Tercera entrada de prueba', array( 'post_type' => 'post', 'date' => '2026-01-06 10:00:00', 'content' => $long ) );
$fourth = digi_page( 'digi-ts-entrada-4', 'Cuarta entrada de prueba', array( 'post_type' => 'post', 'date' => '2026-01-04 10:00:00', 'content' => $long ) );
foreach ( array( $post, $hidden, $second, $third, $fourth ) as $id ) {
	if ( $id ) {
		wp_set_post_categories( $id, array( $category ) );
	}
}
if ( $post ) {
	wp_set_post_tags( $post, array( 'etiqueta-uno', 'etiqueta-dos' ) );
	set_post_thumbnail( $post, $image_id );
	if ( ! get_comments( array( 'post_id' => $post, 'count' => true ) ) ) {
		$parent = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_author' => 'Ana', 'comment_author_email' => 'ana@example.test', 'comment_content' => 'Primer comentario de prueba.', 'comment_approved' => 1, 'comment_date' => '2026-01-11 09:00:00', 'comment_date_gmt' => '2026-01-11 09:00:00' ) );
		wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_author' => 'Luis', 'comment_author_email' => 'luis@example.test', 'comment_content' => 'Respuesta anidada de prueba.', 'comment_parent' => $parent, 'comment_approved' => 1, 'comment_date' => '2026-01-11 10:00:00', 'comment_date_gmt' => '2026-01-11 10:00:00' ) );
	}
}
wp_delete_post( 1, true ); // «Hello world!» de la instalación.
wp_delete_post( 2, true ); // «Sample Page».
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
update_option( 'page_for_posts', $blog );

// Menús: principal (menu-1) y de pie (menu-2), las ubicaciones de Hello que el tema también registra.
foreach ( array( 'digi-ab-principal' => array( 'Principal AB', array( 'Inicio' => home_url( '/' ), 'Blog' => home_url( '/digi-ts-blog/' ), 'Constructor' => home_url( '/digi-ts-constructor/' ) ) ), 'digi-ab-pie' => array( 'Pie AB', array( 'Entrada' => home_url( '/digi-ts-entrada/' ), 'Ancho completo' => home_url( '/digi-ts-ancho-completo/' ) ) ) ) as $slug => $menu ) {
	if ( wp_get_nav_menu_object( $slug ) ) {
		continue;
	}
	$menu_id = wp_create_nav_menu( $menu[0] );
	if ( is_wp_error( $menu_id ) ) {
		digi_setup_error( 'No se pudo crear el menú ' . $slug . ': ' . $menu_id->get_error_message() );
		continue;
	}
	wp_update_term( $menu_id, 'nav_menu', array( 'slug' => $slug ) );
	foreach ( $menu[1] as $label => $url ) {
		wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $label, 'menu-item-url' => $url, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
	}
}

// WooCommerce (WOO=1): páginas de la tienda y un producto simple sin plantilla del constructor de temas.
if ( class_exists( 'WooCommerce' ) ) {
	if ( class_exists( 'WC_Install' ) && wc_get_page_id( 'shop' ) <= 0 ) {
		WC_Install::create_pages();
	}
	if ( ! get_page_by_path( 'digi-ts-producto', OBJECT, 'product' ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto AB' );
		$product->set_slug( 'digi-ts-producto' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '25' );
		$product->set_short_description( 'Descripción corta del producto de prueba.' );
		$product->set_description( 'Descripción larga del producto de prueba.' );
		$product->save();
	}
}

switch_theme( 'hello-elementor' );
// Las reglas se rehacen en la próxima petición, con la estructura nueva y los tipos de contenido ya registrados.
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
delete_option( 'rewrite_rules' );
update_option( 'digi_ab_ready', time() );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) ) {
	digi_setup_error( 'digitalisimo-elements no quedó activo' );
}
