<?php
/**
 * Preparación del grupo `quickview` de woo-flow.sh (W4, W5 y W11; F4-Woo, V.5 y V.16.4). Idempotente.
 *
 * - **Imágenes:** tres PNG para la Camiseta (principal y galería: «Imágenes del producto» con galería funcional) y una
 *   por color para las variaciones de la Sudadera (elegir una combinación cambia la imagen). No crea productos: el
 *   catálogo de store.php queda con los mismos siete (W0.4).
 * - **Plantilla** `digi-wf-qv-plantilla` de la biblioteca, de tipo «Producto individual» y **sin condiciones** (no se
 *   aplica a ninguna ficha): «Imágenes del producto», título, precio y «Añadir al carrito» de PRO.
 * - **Páginas** sin el formulario de variaciones, cada una con el Menú del carrito (barra de envío y cupón del rasgo
 *   `Mini_Cart_Extras`), dos botones de vista rápida (Camiseta, simple; Sudadera, variable) y un enlace detrás:
 *   `digi-wf-qv` en «Diseño propio» y `digi-wf-qv-plantilla` en «Plantilla de producto».
 *
 * Devuelve `$out['quickview']` con las URL, los productos, los botones y las imágenes. Los fallos van también a
 * `$out['errors']`: `$fail` escribe en el arreglo que store.php sustituye por lo que devuelve esta función.
 */

defined( 'ABSPATH' ) || exit;

return static function ( array $out, callable $fail ): array {
	$errors = array();
	$note   = static function ( $message ) use ( &$errors, $fail ) {
		$errors[] = 'quickview: ' . $message;
		$fail( 'quickview: ' . $message );
	};
	$qv       = array( 'errors' => array() );
	$camiseta = (int) ( $out['products']['digi-wf-camiseta'] ?? 0 );
	$sudadera = (int) ( $out['products']['digi-wf-sudadera'] ?? 0 );
	if ( ! $camiseta || ! $sudadera || ! defined( 'ELEMENTOR_VERSION' ) ) {
		$note( 'faltan la Camiseta, la Sudadera o el constructor' );
		$out['errors']    = array_merge( $out['errors'] ?? array(), $errors );
		$out['quickview'] = $qv;
		return $out;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Imágenes: PNG de un color con un cuadro blanco (GD); sin GD, el marcador de WooCommerce con otro nombre.
	$image = static function ( string $slug, array $rgb ) use ( $note ): int {
		$found = get_posts( array( 'post_type' => 'attachment', 'name' => $slug, 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( $found ) {
			return (int) $found[0];
		}
		$bytes = '';
		if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' ) ) {
			$im = imagecreatetruecolor( 600, 600 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
			imagefilledrectangle( $im, 220, 220, 380, 380, imagecolorallocate( $im, 255, 255, 255 ) );
			ob_start();
			imagepng( $im );
			$bytes = (string) ob_get_clean();
			imagedestroy( $im );
		}
		if ( '' === $bytes && function_exists( 'WC' ) ) {
			$bytes = (string) @file_get_contents( WC()->plugin_path() . '/assets/images/placeholder.png' ); // phpcs:ignore
		}
		$upload = wp_upload_bits( $slug . '.png', null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			$note( "imagen $slug: " . $upload['error'] );
			return 0;
		}
		$id = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $slug, 'post_name' => $slug, 'post_status' => 'inherit' ), $upload['file'] );
		if ( ! $id ) {
			$note( "imagen $slug: no se creó el adjunto" );
			return 0;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Imagen ' . $slug );
		return $id;
	};
	$images = array(
		'camiseta-1' => $image( 'digi-wf-qv-camiseta-1', array( 36, 94, 255 ) ),
		'camiseta-2' => $image( 'digi-wf-qv-camiseta-2', array( 16, 150, 90 ) ),
		'camiseta-3' => $image( 'digi-wf-qv-camiseta-3', array( 230, 120, 20 ) ),
		'rojo'       => $image( 'digi-wf-qv-sudadera-rojo', array( 200, 30, 40 ) ),
		'azul'       => $image( 'digi-wf-qv-sudadera-azul', array( 20, 60, 200 ) ),
		'verde'      => $image( 'digi-wf-qv-sudadera-verde', array( 20, 140, 60 ) ),
	);
	$qv['images'] = array();
	foreach ( $images as $key => $id ) {
		$qv['images'][ $key ] = array( 'id' => $id, 'url' => $id ? (string) wp_get_attachment_url( $id ) : '' );
	}

	// Camiseta: imagen principal y dos de galería.
	$product = wc_get_product( $camiseta );
	if ( $product && $images['camiseta-1'] && (int) $product->get_image_id() !== $images['camiseta-1'] ) {
		$product->set_image_id( $images['camiseta-1'] );
		$product->set_gallery_image_ids( array_values( array_filter( array( $images['camiseta-2'], $images['camiseta-3'] ) ) ) );
		$product->save();
	}
	// Sudadera: una imagen por color en cada variación (el producto sigue sin imagen propia).
	$variable           = wc_get_product( $sudadera );
	$qv['variations']   = array();
	if ( $variable && $variable->is_type( 'variable' ) ) {
		foreach ( $variable->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$attrs = $variation->get_attributes(); // Slugs de los términos: pa_color => rojo.
			$color = (string) ( $attrs['pa_color'] ?? '' );
			if ( ! empty( $images[ $color ] ) && (int) $variation->get_image_id() !== $images[ $color ] ) {
				$variation->set_image_id( $images[ $color ] );
				$variation->save();
			}
			$qv['variations'][] = array( 'id' => (int) $child_id, 'color' => $color, 'talla' => (string) ( $attrs['pa_talla'] ?? '' ), 'price' => (string) $variation->get_price(), 'image' => (int) $variation->get_image_id() );
		}
		wc_delete_product_transients( $sudadera );
	} else {
		$note( 'la Sudadera no es un producto variable' );
	}

	$save = static function ( int $id, array $tree ): void {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $id, '_elementor_css' );
		delete_post_meta( $id, '_elementor_element_cache' );
	};
	$widget = static function ( string $id, string $type, array $settings = array() ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => (object) $settings, 'elements' => array() );
	};
	$container = static function ( string $id, array $children ): array {
		return array( 'id' => $id, 'elType' => 'container', 'settings' => (object) array(), 'elements' => $children );
	};

	// Plantilla de producto (sin condiciones: no cambia ninguna ficha).
	$tpl_slug = 'digi-wf-qv-plantilla';
	$found    = get_posts( array( 'post_type' => 'elementor_library', 'name' => $tpl_slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$template = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Plantilla de la vista rápida', 'post_name' => $tpl_slug ) );
	if ( ! $template ) {
		$note( 'no se creó la plantilla de producto' );
	} else {
		update_post_meta( $template, '_elementor_template_type', 'product' );
		wp_set_object_terms( $template, 'product', 'elementor_library_type' );
		$save(
			$template,
			array(
				$container(
					'a7c0e01',
					array(
						$widget( 'a7c0e02', 'woocommerce-product-images' ),
						$widget( 'a7c0e03', 'woocommerce-product-title' ),
						$widget( 'a7c0e04', 'woocommerce-product-price' ),
						$widget( 'a7c0e05', 'woocommerce-product-add-to-cart' ),
					)
				),
			)
		);
	}

	// Páginas: Menú del carrito con barra y cupón, dos botones y un enlace detrás (el foco tiene adónde escaparse).
	$menu_cart = array( 'digitalisimo_cart_bar' => 'yes', 'digitalisimo_cart_coupon' => 'yes' );
	$pages     = array(
		'digi-wf-qv'           => array( 'Vista rápida: diseño propio', 'b', array() ),
		'digi-wf-qv-plantilla' => array( 'Vista rápida: plantilla de producto', 'c', array( 'content_mode' => 'template', 'digitalisimo_template_id' => (string) $template ) ),
	);
	$qv['pages']   = array();
	$qv['buttons'] = array();
	foreach ( $pages as $slug => $def ) {
		list( $title, $seed, $mode ) = $def;
		$page = get_page_by_path( $slug );
		$id   = $page ? (int) $page->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) );
		if ( ! $id ) {
			$note( "no se creó la página $slug" );
			continue;
		}
		$simple_id   = "a7c{$seed}011";
		$variable_id = "a7c{$seed}012";
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_wp_page_template', 'default' );
		$save(
			$id,
			array(
				$container(
					"a7c{$seed}001",
					array(
						$widget( "a7c{$seed}002", 'heading', array( 'title' => $title ) ),
						$widget( "a7c{$seed}003", 'woocommerce-menu-cart', $menu_cart ),
						$widget( $simple_id, 'digitalisimo-woo-quick-view', array_merge( array( 'product_source' => 'custom', 'product_id' => (string) $camiseta ), $mode ) ),
						$widget( $variable_id, 'digitalisimo-woo-quick-view', array_merge( array( 'product_source' => 'custom', 'product_id' => (string) $sudadera ), $mode ) ),
						$widget( "a7c{$seed}004", 'text-editor', array( 'editor' => '<p>Detrás de los botones: <a href="' . esc_url( home_url( '/' ) ) . '">un enlace de la página</a>.</p>' ) ),
					)
				),
			)
		);
		$qv['pages'][ $slug ]   = (string) get_permalink( $id );
		$qv['buttons'][ $slug ] = array( 'post' => $id, 'simple' => $simple_id, 'variable' => $variable_id );
	}

	$qv['template']  = $template;
	$qv['products']  = array(
		'simple'   => array( 'id' => $camiseta, 'name' => (string) get_the_title( $camiseta ), 'url' => (string) get_permalink( $camiseta ), 'price' => (string) wc_get_product( $camiseta )->get_price() ),
		'variable' => array( 'id' => $sudadera, 'name' => (string) get_the_title( $sudadera ), 'url' => (string) get_permalink( $sudadera ) ),
	);
	$qv['gd']       = function_exists( 'imagecreatetruecolor' );
	$qv['errors']   = $errors;
	$out['errors']  = array_values( array_unique( array_merge( $out['errors'] ?? array(), $errors ) ) );
	$out['quickview'] = $qv;
	return $out;
};
