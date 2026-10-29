<?php
/**
 * Sitio A de kit-roundtrip.sh (§13.3): el origen de la ida y vuelta.
 *
 * - Borra el contenido por defecto (CV-15) y crea el de cases.json guardando cada documento con Document::save(),
 *   como el editor: así A y B pasan por los mismos on_save y la firma compara lo mismo.
 * - Medios, usuaria, términos con metas, entradas, menús, separadores digi-* y máscaras, fuente e iconos propios,
 *   ajustes y valores por defecto del kit, plantillas (sección, loop, popup y Theme Builder en cinco ubicaciones),
 *   páginas con los tres motores de condiciones, una página por widget propio (44, el Slider Optimizado y los 7 alias)
 *   y una por rasgo. Con WOO=1 (F4-Woo, V.11.5): categoría con imagen; atributos globales pa_color, pa_talla-zapato y
 *   pa_acabado (wc-visual, con la función encendida) con sus metas de muestra y su configuración de muestras;
 *   productos simples, variables con atributo local y global (variaciones con imagen y SKU) y un variable en
 *   borrador; plantillas de producto y de bucle de productos, los siete widgets de F4-Woo y la página de filtros.
 * - Vuelca get_controls() de todo widget registrado (propios, PRO y núcleo), de los elementos y de las condiciones,
 *   para el inventario en tiempo de ejecución (§9.2), y las referencias que anotó al resolver las fichas (refs.json).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/lib.php';

$digi_opts = digi_k_options();
wp_set_current_user( 1 );

// activate.php (paso anterior del blueprint) activó los plugins: en esta petición ya están cargados.
if ( ! class_exists( '\\Elementor\\Plugin' ) || ! did_action( 'elementor/loaded' ) ) {
	digi_k_fail( 'El constructor no está cargado en la preparación de A' );
	return;
}
// A y B con la misma identidad: la identidad nunca viaja (§4.1 #7) y así no ensucia la comparación de capturas.
update_option( 'blogname', 'Sitio del kit' );
update_option( 'blogdescription', 'Ida y vuelta de K' );
// La subida de SVG sin filtrar decide si los SVG de la biblioteca viajan (D15): A los permite.
update_option( 'elementor_unfiltered_files_upload', '1' );

digi_k_clear_defaults();
$cases  = digi_k_read_json( __DIR__ . '/cases.json' );
$budget = digi_k_read_json( __DIR__ . '/front-budget-cases.json' );
if ( ! $cases ) {
	digi_k_fail( 'cases.json no es JSON válido' );
	return;
}
$refs    = new Digi_K_Refs();
$ids     = array();
$uploads = wp_upload_dir();

// Usuaria (los usuarios no viajan en el kit: sus referencias nunca tienen destino).
foreach ( (array) $cases['users'] as $user ) {
	if ( ! get_user_by( 'login', $user['login'] ) ) {
		wp_insert_user( array( 'user_login' => $user['login'], 'user_pass' => wp_generate_password(), 'user_email' => $user['email'], 'display_name' => $user['display_name'], 'role' => $user['role'] ) );
	}
}

// Medios.
foreach ( (array) $cases['media'] as $name => $spec ) {
	$refs->media[ $name ] = digi_k_attachment( $name, $spec );
	$ids[ 'media:' . $name ] = $refs->media[ $name ];
}

// Términos con sus metas (parte term-meta).
foreach ( (array) $cases['terms'] as $term ) {
	$found = get_term_by( 'slug', $term['slug'], $term['taxonomy'] );
	$id    = $found ? (int) $found->term_id : 0;
	if ( ! $id ) {
		$made = wp_insert_term( $term['name'], $term['taxonomy'], array( 'slug' => $term['slug'] ) );
		$id   = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	}
	foreach ( (array) ( $term['meta'] ?? array() ) as $key => $value ) {
		update_term_meta( $id, $key, $refs->resolve( $value, 'term_meta.' . $key ) );
	}
	$ids[ 'term:' . $term['taxonomy'] . ':' . $term['slug'] ] = $id;
}

// Separadores y máscaras propios (uploads/digitalisimo-elements/).
foreach ( (array) $cases['files'] as $relative => $svg ) {
	$file = trailingslashit( $uploads['basedir'] ) . 'digitalisimo-elements/' . $relative;
	wp_mkdir_p( dirname( $file ) );
	// En la forma que deja el saneador del núcleo: K los escribe saneados en B (D15) y el sha256 debe coincidir.
	if ( class_exists( '\\Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer' ) ) {
		$clean = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $svg );
		$svg   = is_string( $clean ) && '' !== $clean ? $clean : $svg;
	}
	file_put_contents( $file, $svg );
}

// Fuente propia: un adjunto woff2 y la entrada elementor_font con su font-face (como al guardarla en el editor).
$font_id = 0;
if ( post_type_exists( 'elementor_font' ) ) {
	$found = get_page_by_path( sanitize_title( $cases['font']['title'] ), OBJECT, 'elementor_font' );
	if ( $found ) {
		$font_id = (int) $found->ID;
	} else {
		$file_id = digi_k_attachment( 'fuente', array( 'woff2' => true, 'title' => $cases['font']['title'] ) );
		$font_id = (int) wp_insert_post( array( 'post_type' => 'elementor_font', 'post_status' => 'publish', 'post_title' => $cases['font']['title'], 'post_name' => sanitize_title( $cases['font']['title'] ) ) );
		$faces   = array( array( 'font_weight' => $cases['font']['weight'], 'font_style' => $cases['font']['style'], 'woff2' => array( 'id' => $file_id, 'url' => wp_get_attachment_url( $file_id ) ) ) );
		$module  = class_exists( '\ElementorPro\Plugin' ) ? \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'assets-manager' ) : null;
		$fonts   = $module && method_exists( $module, 'get_assets_manager' ) ? $module->get_assets_manager( 'font' ) : null;
		$custom  = $fonts && method_exists( $fonts, 'get_font_type_object' ) ? $fonts->get_font_type_object( 'custom' ) : null;
		if ( $custom && method_exists( $custom, 'save_meta' ) ) {
			$custom->save_meta( $font_id, array( 'font_face' => $faces ) );
		} else {
			update_post_meta( $font_id, 'elementor_font_files', $faces );
			update_post_meta( $font_id, 'elementor_font_face', "@font-face{font-family:'" . $cases['font']['title'] . "';font-weight:" . $cases['font']['weight'] . ';src:url(\'' . wp_get_attachment_url( $file_id ) . '\') format(\'woff2\');}' );
		}
		// El tipo de una fuente es un término de la taxonomía elementor_font_type, como lo guarda el gestor de fuentes
		// (E:modules/assets-manager/asset-types/fonts-manager.php:520), no una meta.
		wp_set_object_terms( $font_id, 'custom', 'elementor_font_type' );
		// Como al subirla desde el editor de fuentes: el archivo queda adjunto a la fuente (get_attached_media() lo exporta).
		wp_update_post( array( 'ID' => $file_id, 'post_parent' => $font_id ) );
		$refs->media['fuente'] = $file_id;
	}
	$ids['font'] = $font_id;
} else {
	digi_k_fail( 'No existe el tipo elementor_font: la fuente propia no se creó' );
}

// Conjunto de iconos propio con formato Fontello, guardado como lo hace la subida de PRO.
if ( post_type_exists( 'elementor_icons' ) ) {
	$set   = $cases['icons'];
	$found = get_page_by_path( $set['name'], OBJECT, 'elementor_icons' );
	if ( $found ) {
		$ids['icons'] = (int) $found->ID;
	} else {
		$tmp = trailingslashit( get_temp_dir() ) . 'digi-k-icons-' . wp_generate_password( 6, false ) . '/';
		wp_mkdir_p( $tmp . 'css' );
		wp_mkdir_p( $tmp . 'font' );
		$glyphs = array();
		$css    = '';
		foreach ( $set['glyphs'] as $i => $glyph ) {
			$glyphs[] = array( 'uid' => md5( $glyph ), 'css' => $glyph, 'code' => 59392 + $i, 'src' => 'custom_icons' );
			$css     .= '.' . $set['prefix'] . $glyph . ':before{content:"\\e8' . sprintf( '%02x', $i ) . "\"}\n";
		}
		file_put_contents( $tmp . 'config.json', wp_json_encode( array( 'name' => $set['name'], 'css_prefix_text' => $set['prefix'], 'css_use_suffix' => false, 'hinting' => true, 'units_per_em' => 1000, 'ascent' => 850, 'glyphs' => $glyphs ) ) );
		file_put_contents( $tmp . 'css/' . $set['name'] . '.css', "@font-face{font-family:'" . $set['name'] . "';src:url('../font/" . $set['name'] . ".woff2') format('woff2')}\n[class^=\"" . $set['prefix'] . '"]:before{font-family:\'' . $set['name'] . "'}\n" . $css );
		file_put_contents( $tmp . 'font/' . $set['name'] . '.woff2', digi_k_media_bytes( array( 'woff2' => true ) )[0] );
		file_put_contents( $tmp . 'demo.html', '<!doctype html><title>demo</title>' );
		file_put_contents( $tmp . 'README.txt', 'Conjunto sintético del arnés.' );
		file_put_contents( $tmp . 'LICENSE.txt', 'CC0' );
		$post_id = (int) wp_insert_post( array( 'post_type' => 'elementor_icons', 'post_status' => 'publish', 'post_title' => $set['name'], 'post_name' => $set['name'] ) );
		$class   = '\ElementorPro\Modules\AssetsManager\AssetTypes\Icons\IconSets\Fontello';
		try {
			$icon_set = new $class( $tmp );
			if ( ! $icon_set->is_valid() ) {
				throw new RuntimeException( 'el conjunto Fontello sintético no es válido' );
			}
			$icon_set->handle_new_icon_set();
			$icon_set->move_files( $post_id );
			update_post_meta( $post_id, \ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons::META_KEY, wp_json_encode( $icon_set->build_config() ) );
			\ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons::clear_icon_list_option();
		} catch ( \Throwable $e ) {
			digi_k_fail( 'Iconos propios: ' . $e->getMessage() );
		}
		$ids['icons'] = $post_id;
	}
}

// Entradas (contenido del WXR) con imagen destacada, términos y autora.
foreach ( (array) $cases['posts'] as $post ) {
	$found = get_page_by_path( $post['slug'], OBJECT, $post['type'] );
	if ( $found ) {
		$ids[ 'post:' . $post['type'] . ':' . $post['slug'] ] = (int) $found->ID;
		continue;
	}
	$refs->doc = $post['type'] . ':' . sanitize_title( $post['title'] );
	$id        = (int) wp_insert_post( array(
		'post_type'    => $post['type'],
		'post_status'  => 'publish',
		'post_title'   => $post['title'],
		'post_name'    => $post['slug'],
		'post_author'  => $refs->user_id( $post['author'] ?? '' ) ?: 1,
		'post_content' => $refs->resolve( $post['content'] ?? '', 'post_content' ),
		'post_date'    => '2026-01-15 10:00:00',
	) );
	foreach ( (array) ( $post['terms'] ?? array() ) as $taxonomy => $slugs ) {
		wp_set_object_terms( $id, $slugs, $taxonomy );
	}
	if ( ! empty( $post['thumbnail'] ) ) {
		set_post_thumbnail( $id, $refs->media_id( $post['thumbnail'] ) );
	}
	foreach ( (array) ( $post['comments'] ?? array() ) as $comment ) {
		wp_insert_comment( array( 'comment_post_ID' => $id, 'comment_author' => $comment['author'], 'comment_author_email' => $comment['email'], 'comment_author_IP' => $comment['ip'], 'comment_content' => $comment['content'], 'comment_approved' => 1 ) );
	}
	if ( ! empty( $post['password'] ) ) {
		wp_update_post( array( 'ID' => $id, 'post_password' => $post['password'] ) );
	}
	$ids[ 'post:' . $post['type'] . ':' . $post['slug'] ] = $id;
}

// Custom Code (§7.8 #5): ubicación, prioridad, código y condiciones; en B llega siempre en borrador (D8).
foreach ( (array) ( $cases['snippets'] ?? array() ) as $snippet ) {
	if ( get_posts( array( 'post_type' => 'elementor_snippet', 'title' => $snippet['title'], 'post_status' => 'any', 'fields' => 'ids' ) ) ) {
		continue;
	}
	$id = (int) wp_insert_post( array( 'post_type' => 'elementor_snippet', 'post_status' => 'publish', 'post_title' => $snippet['title'] ) );
	update_post_meta( $id, '_elementor_location', $snippet['location'] );
	update_post_meta( $id, '_elementor_priority', $snippet['priority'] );
	update_post_meta( $id, '_elementor_code', wp_slash( $snippet['code'] ) );
	update_post_meta( $id, '_elementor_conditions', $snippet['conditions'] );
	$ids[ 'snippet:' . $snippet['title'] ] = $id;
}

// Opciones que nunca viajan en un kit (not-exported.json): deben existir en A para que la comprobación no sea vacía.
foreach ( (array) ( $cases['options'] ?? array() ) as $name => $value ) {
	update_option( $name, $value );
}

// WooCommerce (WOO=1), antes de los documentos que nombran sus términos y productos: categoría con imagen (meta de
// término), atributos globales con sus términos, metas de muestra y configuración de muestras (F4-Woo, V.11.1), y
// productos simples y variables con sus variaciones (parte woo-variations, V.11.2).
if ( ! empty( $digi_opts['woo'] ) && class_exists( 'WooCommerce' ) ) {
	// Medios que sólo nombran las variaciones y las metas `image` de los términos de atributo: viajan sólo por
	// Media_Runner (woo-variations) y por la referencia term_meta acotada a pa_* (V.11.4).
	foreach ( (array) ( $cases['woo']['media'] ?? array() ) as $name => $spec ) {
		$refs->media[ $name ]    = digi_k_attachment( $name, $spec );
		$ids[ 'media:' . $name ] = $refs->media[ $name ];
	}
	digi_k_woo_quiet();
	// La función «Color swatches for attributes» de WooCommerce encendida en A (en B, apagada: V.11.5).
	update_option( 'woocommerce_feature_wc_visual_attribute_enabled', 'yes' );
	foreach ( (array) ( $cases['woo']['attributes'] ?? array() ) as $attribute_spec ) {
		$ids[ 'attribute:' . $attribute_spec['slug'] ] = digi_k_woo_attribute( $attribute_spec, $refs, $ids );
	}
	foreach ( (array) $cases['woo']['product_categories'] as $cat ) {
		$found = get_term_by( 'slug', $cat['slug'], 'product_cat' );
		$id    = $found ? (int) $found->term_id : (int) ( wp_insert_term( $cat['name'], 'product_cat', array( 'slug' => $cat['slug'] ) )['term_id'] ?? 0 );
		update_term_meta( $id, 'thumbnail_id', $refs->media_id( $cat['image'] ) );
		$ids[ 'term:product_cat:' . $cat['slug'] ] = $id;
	}
	foreach ( (array) $cases['woo']['products'] as $spec ) {
		if ( get_page_by_path( $spec['slug'], OBJECT, 'product' ) ) {
			continue;
		}
		$product = 'variable' === $spec['type'] ? new WC_Product_Variable() : new WC_Product_Simple();
		$product->set_name( $spec['title'] );
		$product->set_slug( $spec['slug'] );
		// «status»: un variable en borrador no viaja y sus variaciones cuentan en not_exported.variations (V.11.4).
		$product->set_status( $spec['status'] ?? 'publish' );
		$product->set_category_ids( array( (int) $ids[ 'term:product_cat:' . $spec['category'] ] ) );
		if ( ! empty( $spec['image'] ) ) {
			$product->set_image_id( $refs->media_id( $spec['image'] ) );
		}
		$attributes = array();
		if ( ! empty( $spec['attribute'] ) ) {
			// Atributo local (sin taxonomía), usado para variaciones.
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( $spec['attribute']['name'] );
			$attribute->set_options( $spec['attribute']['options'] );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$attributes[] = $attribute;
		}
		foreach ( (array) ( $spec['attributes'] ?? array() ) as $global ) {
			// Atributo global (pa_*): sus términos por slug, como los guarda la pantalla de producto.
			$term_ids = array();
			foreach ( (array) $global['terms'] as $term_slug ) {
				$term_ids[] = $refs->term_id( $global['taxonomy'], $term_slug );
			}
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $global['taxonomy'] ) );
			$attribute->set_name( $global['taxonomy'] );
			$attribute->set_options( array_filter( $term_ids ) );
			$attribute->set_visible( true );
			$attribute->set_variation( ! empty( $global['variation'] ) );
			$attributes[] = $attribute;
			if ( in_array( 0, $term_ids, true ) || ! wc_attribute_taxonomy_id_by_name( $global['taxonomy'] ) ) {
				digi_k_fail( "WooCommerce: el producto {$spec['slug']} nombra un término o un atributo que no existe en {$global['taxonomy']}" );
			}
		}
		if ( $attributes ) {
			$product->set_attributes( $attributes );
		}
		if ( 'variable' !== $spec['type'] ) {
			$product->set_regular_price( $spec['price'] );
		}
		$product_id = $product->save();
		foreach ( (array) ( $spec['variations'] ?? array() ) as $variation_spec ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( $variation_spec['attributes'] );
			$variation->set_regular_price( $variation_spec['price'] );
			if ( ! empty( $variation_spec['sku'] ) ) {
				$variation->set_sku( $variation_spec['sku'] );
			}
			if ( ! empty( $variation_spec['image'] ) ) {
				$variation->set_image_id( $refs->media_id( $variation_spec['image'] ) );
			}
			$variation->save();
		}
		if ( ! empty( $spec['variations'] ) ) {
			WC_Product_Variable::sync( $product_id );
		}
		$ids[ 'post:product:' . $spec['slug'] ] = $product_id;
	}
}


// Documentos en dos pasos: primero se reservan todos (vacíos) y después se guardan con las fichas ya resueltas, así
// un documento puede nombrarse a sí mismo o a otro creado después (la portada, la hija, los popups).
$documents = array();
foreach ( (array) $cases['pages'] as $page ) {
	// «woo»: la página nombra productos (condición de existencias de F7a) y sólo se crea con WOO=1.
	if ( ! empty( $page['woo'] ) && empty( $digi_opts['woo'] ) ) {
		continue;
	}
	$documents[] = array( 'type' => 'wp-page', 'post_type' => 'page', 'slug' => $page['slug'], 'title' => $page['title'], 'parent' => $page['parent'] ?? '', 'elements' => $page['elements'], 'settings' => $page['settings'] ?? array(), 'front' => ! empty( $page['front'] ), 'meta' => (array) ( $page['meta'] ?? array() ) );
}
$templates = (array) $cases['templates'];
if ( ! empty( $digi_opts['woo'] ) ) {
	$templates = array_merge( $templates, (array) ( $cases['woo']['templates'] ?? array() ) );
}
foreach ( $templates as $template ) {
	$documents[] = array( 'type' => $template['type'], 'post_type' => 'elementor_library', 'slug' => $template['slug'], 'title' => $template['title'], 'elements' => $template['elements'], 'settings' => $template['settings'] ?? array(), 'conditions' => $template['conditions'] ?? array() );
}
foreach ( (array) $cases['widgets'] as $type => $case ) {
	// «woo»: widget de WooCommerce (F4-Woo, V.9): sin WOO=1 su tipo no existe y la página no se crea.
	if ( ! empty( $case['woo'] ) && empty( $digi_opts['woo'] ) ) {
		continue;
	}
	$slug        = 'digi-k-w-' . preg_replace( '/^digitalisimo-/', '', $type );
	$widget      = array( 'id' => digi_k_eid( 'e', $slug ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => digi_k_widget_settings( $case, $budget ), 'elements' => array() );
	$documents[] = array( 'type' => 'wp-page', 'post_type' => 'page', 'slug' => $slug, 'title' => 'Widget ' . $type, 'elements' => array( array( 'id' => digi_k_eid( 'c', $slug ), 'elType' => 'container', 'settings' => array(), 'elements' => array( $widget ) ) ) );
}
foreach ( (array) $cases['traits'] as $trait => $case ) {
	if ( ! empty( $case['woo'] ) && empty( $digi_opts['woo'] ) ) {
		continue;
	}
	$slug        = 'digi-k-r-' . $trait;
	$widget      = array( 'id' => digi_k_eid( 'e', $slug ), 'elType' => 'widget', 'widgetType' => $case['widget'], 'settings' => (array) ( $case['settings'] ?? array() ), 'elements' => array() );
	$documents[] = array( 'type' => 'wp-page', 'post_type' => 'page', 'slug' => $slug, 'title' => 'Rasgo ' . $trait, 'elements' => array( array( 'id' => digi_k_eid( 'c', $slug ), 'elType' => 'container', 'settings' => (array) ( $case['container'] ?? array() ), 'elements' => array( $widget ) ) ) );
}
foreach ( $documents as $doc ) {
	$parent = '' !== ( $doc['parent'] ?? '' ) ? $refs->post_id( 'page', $doc['parent'] ) : 0;
	$ids[ 'post:' . $doc['post_type'] . ':' . $doc['slug'] ] = digi_k_reserve( $doc['type'], $doc['post_type'], $doc['slug'], $doc['title'], $parent ? array( 'post_parent' => $parent ) : array() );
}

// Menús y sus elementos (las páginas ya existen).
foreach ( (array) $cases['menus'] as $menu ) {
	$menu_obj = wp_get_nav_menu_object( $menu['slug'] );
	if ( $menu_obj ) {
		$ids[ 'menu:' . $menu['slug'] ] = (int) $menu_obj->term_id;
		continue;
	}
	$menu_id = wp_create_nav_menu( $menu['name'] );
	wp_update_term( $menu_id, 'nav_menu', array( 'slug' => $menu['slug'] ) );
	$ids[ 'menu:' . $menu['slug'] ] = (int) $menu_id;
	$refs->doc = 'nav_menu:' . $menu['slug'];
	foreach ( (array) $menu['items'] as $position => $item ) {
		$args = array( 'menu-item-title' => $item['title'], 'menu-item-status' => 'publish', 'menu-item-position' => $position + 1 );
		if ( 'custom' === $item['type'] ) {
			$args += array( 'menu-item-type' => 'custom', 'menu-item-url' => $refs->resolve( $item['url'], 'url' ) );
		} elseif ( 'page' === $item['type'] ) {
			$args += array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $refs->post_id( 'page', $item['object'] ) );
		} else {
			$args += array( 'menu-item-type' => 'taxonomy', 'menu-item-object' => $item['type'], 'menu-item-object-id' => $refs->term_id( $item['type'], $item['object'] ) );
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
}
// Los menús no se asignan a las ubicaciones del tema: son ajustes del tema (theme_mods), que un kit nunca trae ni
// cambia (D5). Con una ubicación asignada en A, las páginas sin cabecera del Theme Builder pintarían el menú en A y
// no en B, y la captura mediría el tema. Los menús se ven por el widget de menú de la cabecera del kit.

// Presets de estilo del sitio (F7a, V.10): la opción digitalisimo_elements_presets, sembrada con Store::replace() con
// los controles de estilo visibles (Performance::set_use_style_controls( true ), como en el editor) y antes de guardar
// los documentos, porque Guard vacía al guardar el vínculo a un preset que no existe. Fecha y huella fijas: R1
// compara la opción entera de A y de B, con `hash` y `updated`.
if ( ! empty( $cases['kit']['presets'] ) ) {
	$store       = '\\Digitalisimo\\Elements\\Design\\Presets\\Store';
	$performance = '\\Elementor\\Core\\Frontend\\Performance';
	if ( ! class_exists( $store ) || ! method_exists( $store, 'replace' ) ) {
		digi_k_fail( 'Presets de estilo: falta Digitalisimo\\Elements\\Design\\Presets\\Store::replace() (módulo digitalisimo-design de F7a)' );
	} elseif ( ! method_exists( $performance, 'set_use_style_controls' ) || ! method_exists( $performance, 'is_use_style_controls' ) ) {
		digi_k_fail( 'Presets de estilo: el núcleo no tiene Performance::set_use_style_controls()' );
	} else {
		$previous = (bool) $performance::is_use_style_controls();
		$performance::set_use_style_controls( true );
		try {
			$store::replace( array( 'v' => 1, 'presets' => (array) $cases['kit']['presets'] ) );
		} finally {
			$performance::set_use_style_controls( $previous );
		}
		$seeded = $store::all();
		foreach ( $store::dropped() as $drop ) {
			digi_k_fail( 'Presets de estilo: Store::replace() descartó ' . wp_json_encode( $drop ) );
		}
		foreach ( (array) $cases['kit']['presets'] as $slug => $preset ) {
			$inert = ! empty( $seeded[ $slug ]['inert'] );
			if ( ! isset( $seeded[ $slug ] ) ) {
				digi_k_fail( "Presets de estilo: falta {$slug} tras sembrar" );
			} elseif ( $inert !== ( 'acme-tarjeta' === $preset['type'] ) ) {
				digi_k_fail( "Presets de estilo: {$slug} " . ( $inert ? 'quedó inerte' : 'no quedó inerte' ) . " (tipo {$preset['type']})" );
			} elseif ( (int) $seeded[ $slug ]['updated'] !== (int) $preset['updated'] ) {
				digi_k_fail( "Presets de estilo: {$slug} no conservó su fecha" );
			}
		}
		// Lo que el saneado dejó de cada preset (si quitó una clave, la huella es la de lo que quedó).
		digi_k_write( 'presets-seed.json', $seeded );
	}
}

// Ajustes y valores por defecto del kit (se guardan con el documento del kit, como desde «Ajustes del sitio»).
$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
if ( $kit && $kit->get_id() ) {
	$refs->doc    = 'kit';
	$kit_settings = array_replace( (array) $kit->get_settings(), (array) $refs->resolve( (array) $cases['kit']['settings'], 'kit_settings' ) );
	$kit->save( array( 'settings' => $kit_settings ) );
	$kit->update_json_meta( '_elementor_elements_default_values', (array) $refs->resolve( (array) $cases['kit']['defaults'], 'defaults' ) );
	$ids['kit'] = (int) $kit->get_id();
} else {
	digi_k_fail( 'No hay kit activo en A' );
}

// Contenido de cada documento, condiciones del Theme Builder, disparadores del popup y portada.
foreach ( $documents as $doc ) {
	// La firma reconoce cada documento por su tipo y su título (el importador no conserva el slug).
	$refs->doc = $doc['post_type'] . ':' . sanitize_title( $doc['title'] );
	$elements  = $refs->elements( (array) $doc['elements'] );
	$settings  = (array) $refs->resolve( (array) $doc['settings'], 'page_settings' );
	$triggers  = $settings['triggers'] ?? null;
	unset( $settings['triggers'] );
	$id = digi_k_document( $doc['type'], $doc['post_type'], $doc['slug'], $doc['title'], $elements, $settings );
	if ( $triggers && 'popup' === $doc['type'] ) {
		$document = \Elementor\Plugin::$instance->documents->get( $id, false );
		if ( $document && method_exists( $document, 'save_display_settings_data' ) ) {
			$document->save_display_settings_data( array( 'triggers' => $triggers, 'timing' => array() ) );
		}
	}
	if ( ! empty( $doc['conditions'] ) ) {
		digi_k_conditions( $id, array_map( static function ( $condition, $i ) use ( $refs ) {
			return (string) $refs->resolve( $condition, 'conditions.' . $i );
		}, $doc['conditions'], array_keys( $doc['conditions'] ) ) );
	}
	if ( ! empty( $doc['front'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
	}
	// Metas públicas del documento (las lee la condición «Campo personalizado» de F7a y viajan con el documento).
	foreach ( (array) ( $doc['meta'] ?? array() ) as $meta_key => $meta_value ) {
		update_post_meta( $id, $meta_key, $meta_value );
	}
}

// Metas efímeras que nunca viajan (not-exported.json): el bloqueo del editor y la caché del CSS de la portada.
$front = (int) get_option( 'page_on_front' );
if ( $front ) {
	update_post_meta( $front, '_edit_lock', time() . ':1' );
	update_post_meta( $front, '_edit_last', 1 );
	if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		\Elementor\Core\Files\CSS\Post::create( $front )->update();
	}
}

flush_rewrite_rules( false );
\Elementor\Plugin::$instance->files_manager->clear_cache();

// Inventario en tiempo de ejecución (§9.2): controles de todo widget, de los elementos y de las condiciones.
$describe = static function ( $controls ) {
	$out = array();
	foreach ( (array) $controls as $name => $control ) {
		$row = array( 'type' => (string) ( $control['type'] ?? '' ) );
		if ( ! empty( $control['autocomplete']['object'] ) ) {
			$row['object'] = (string) $control['autocomplete']['object'];
		}
		if ( isset( $control['options'] ) && is_array( $control['options'] ) ) {
			$row['options'] = count( $control['options'] );
		}
		if ( ! empty( $control['fields'] ) && is_array( $control['fields'] ) ) {
			$row['fields'] = array();
			foreach ( $control['fields'] as $field_name => $field ) {
				$field_row = array( 'type' => (string) ( $field['type'] ?? '' ) );
				if ( ! empty( $field['autocomplete']['object'] ) ) {
					$field_row['object'] = (string) $field['autocomplete']['object'];
				}
				$row['fields'][ (string) ( $field['name'] ?? $field_name ) ] = $field_row;
			}
		}
		$out[ (string) $name ] = $row;
	}
	return $out;
};
$origin_of = static function ( $object ) {
	$class = get_class( $object );
	if ( 0 === strpos( $class, 'Digitalisimo\\' ) ) {
		return 'propio';
	}
	return 0 === strpos( $class, 'ElementorPro\\' ) ? 'pro' : ( 0 === strpos( $class, 'Elementor\\' ) ? 'núcleo' : 'otro' );
};
// Cómo traduce el núcleo los IDs de un widget al importar (N:core/base/document.php:1318-1337): con su propio
// on_import_replace_dynamic_content (`reemplazo`), con el rasgo On_Import de PRO (`rasgo`, que filtra por nombre:
// E:base/on-import-trait.php:86-108), con un método propio (`propio`, como Loop Grid o Nav Menu) o con el de
// Element_Base, que sólo mira las etiquetas dinámicas (`ninguno`).
$import_of = static function ( $object ) {
	if ( method_exists( $object, 'has_own_method' ) && $object->has_own_method( 'on_import_replace_dynamic_content' ) ) {
		return 'reemplazo';
	}
	if ( ! method_exists( $object, 'on_import_update_dynamic_content' ) ) {
		return 'ninguno';
	}
	$file = str_replace( '\\', '/', (string) ( new ReflectionMethod( $object, 'on_import_update_dynamic_content' ) )->getFileName() );
	if ( false !== strpos( $file, '/on-import-trait.php' ) ) {
		return 'rasgo';
	}
	return preg_match( '#/elementor/includes/base/(element-base|controls-stack)\.php$#', $file ) ? 'ninguno' : 'propio';
};
$inventory = array( 'widgets' => array(), 'elements' => array(), 'conditions' => array() );
foreach ( \Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $name => $widget ) {
	try {
		$inventory['widgets'][ $name ] = array( 'origin' => $origin_of( $widget ), 'class' => get_class( $widget ), 'import' => $import_of( $widget ), 'controls' => $describe( $widget->get_controls() ) );
	} catch ( \Throwable $e ) {
		$inventory['widgets'][ $name ] = array( 'error' => $e->getMessage() );
	}
}
foreach ( \Elementor\Plugin::$instance->elements_manager->get_element_types() as $name => $element ) {
	try {
		$inventory['elements'][ $name ] = array( 'origin' => $origin_of( $element ), 'controls' => $describe( $element->get_controls() ) );
	} catch ( \Throwable $e ) {
		$inventory['elements'][ $name ] = array( 'error' => $e->getMessage() );
	}
}
if ( class_exists( '\ElementorPro\Modules\DisplayConditions\Module' ) ) {
	try {
		$module = \ElementorPro\Modules\DisplayConditions\Module::instance();
		foreach ( (array) $module->get_conditions_manager()->get_conditions_config() as $name => $config ) {
			$inventory['conditions'][ $name ] = array( 'controls' => $describe( $config['controls'] ?? array() ) );
		}
	} catch ( \Throwable $e ) {
		$inventory['conditions'] = array( 'error' => $e->getMessage() );
	}
}
digi_k_write( 'controls.json', $inventory );

// Registro de referencias de K, si ya existe (frentes E y C): compare.mjs lo cruza con el inventario.
if ( class_exists( '\Digitalisimo\Elements\Import\Kits\References' ) ) {
	$registry = array( 'all' => \Digitalisimo\Elements\Import\Kits\References::all(), 'derived' => array() );
	foreach ( array_keys( $inventory['widgets'] ) as $name ) {
		try {
			$registry['derived'][ $name ] = \Digitalisimo\Elements\Import\Kits\References::for_type( $name );
		} catch ( \Throwable $e ) {
			$registry['derived'][ $name ] = array( 'error' => $e->getMessage() );
		}
	}
	digi_k_write( 'references.json', $registry );
}

if ( $refs->missing ) {
	digi_k_fail( 'Fichas de cases.json sin resolver: ' . implode( ', ', array_unique( $refs->missing ) ) );
}
digi_k_write( 'refs.json', $refs->refs );
digi_k_write( 'ids.json', $ids );
if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) ) {
	digi_k_fail( 'DIGITALÍSIMO Elements no quedó activo' );
}
update_option( 'digi_k_ready', time() );
