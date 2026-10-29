<?php
/**
 * Preparación del grupo `resilience` de woo-flow.sh (W0.8 y W0.9 sin WooCommerce, W3 caché de página, W13 vuelta atrás y
 * guarda hacia delante; F4-Woo, V.10 y V.16.4). Corre en la petición `?digi_wf=setup` de cada sitio, con WooCommerce
 * cargado y el usuario 1. Idempotente: busca cada documento por su *slug* y vuelve a escribir sus datos.
 *
 * No crea productos ni atributos (el catálogo de `store.php` basta) ni cambia ajustes: la configuración de muestras que
 * piden W3 y W13 la ponen y la quitan sus operaciones (`ops/resilience.php`) en el sitio de la corrida.
 *
 * - Plantilla de bucle `digi-wf-res-bucle` (productos): título dinámico, «Muestras de variación», «Botón de lista de
 *   deseos» y «Vista rápida» del producto actual.
 * - `digi-wf-res-sin-woo` y `digi-wf-res-editor` (W0.8): un encabezado y los siete widgets de F4-Woo en el primer nivel,
 *   con ajustes que no son los de por defecto; la primera para el md5 con las peticiones del editor y la segunda para el
 *   editor en Chrome.
 * - `digi-wf-res-cache` (W3): los siete widgets (las muestras, en la plantilla del Loop Grid `resloop`), dos botones de
 *   lista de deseos de productos concretos, los filtros de precio y de atributo sobre el bucle y la barra de envío.
 * - `digi-wf-res-vuelta` (W13): los siete widgets en el primer nivel, un Loop Grid `resl002` con el filtro de precio, el
 *   widget Productos de PRO con las partes nuevas (vista rápida, lista y muestras) y el Menú del carrito con la barra y
 *   el cupón (`Mini_Cart_Extras`).
 * - `digi-wf-res-futuro` (W13.3): un widget `digitalisimo-futuro-res`, un tipo que no existe (de una versión futura).
 */

defined( 'ABSPATH' ) || exit;

return static function ( array $out, callable $fail ): array {
	$res = array( 'pages' => array(), 'ids' => array(), 'errors' => array() );
	$err = static function ( $message ) use ( $fail, &$res ) {
		$res['errors'][] = $message;
		$fail( 'resilience: ' . $message );
	};
	if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
		$err( 'el constructor no está cargado' );
		$out['resilience'] = $res;
		return $out;
	}
	$products = isset( $out['products'] ) && is_array( $out['products'] ) ? $out['products'] : array();
	foreach ( array( 'digi-wf-camiseta', 'digi-wf-gorra', 'digi-wf-sudadera' ) as $slug ) {
		if ( empty( $products[ $slug ] ) ) {
			$err( "falta el producto $slug de store.php" );
			$out['resilience'] = $res;
			return $out;
		}
	}
	$camiseta = (string) (int) $products['digi-wf-camiseta'];
	$gorra    = (string) (int) $products['digi-wf-gorra'];
	$sudadera = (string) (int) $products['digi-wf-sudadera'];

	$find = static function ( string $type, string $slug, string $title ): int {
		$found = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( $found ) {
			return (int) $found[0];
		}
		return (int) wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) );
	};
	$save = static function ( int $id, array $tree, string $template_type ) {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $template_type );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $id, '_elementor_css' );
		delete_post_meta( $id, '_elementor_element_cache' );
	};
	$widget = static function ( string $id, string $type, array $settings ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array(), 'isInner' => false );
	};
	$container = static function ( string $id, array $elements ): array {
		return array( 'id' => $id, 'elType' => 'container', 'settings' => array( 'content_width' => 'full' ), 'elements' => $elements, 'isInner' => false );
	};
	$heading = static function ( string $id, string $title ) use ( $widget ): array {
		return $widget( $id, 'heading', array( 'title' => $title, 'header_size' => 'h3' ) );
	};

	// Los siete widgets con ajustes propios (ninguno igual al valor por defecto de su control). `$p` distingue páginas.
	$seven = static function ( string $p, string $loop ) use ( $widget, $camiseta, $sudadera ): array {
		return array(
			$widget( "res{$p}w01", 'digitalisimo-woo-shipping-bar', array( 'text_progress' => 'Te faltan {restante} para el envío gratis (res)', 'text_done' => 'Envío gratis conseguido (res)', 'bar_color' => '#225588', 'bar_height' => array( 'unit' => 'px', 'size' => 9, 'sizes' => array() ) ) ),
			$widget( "res{$p}w02", 'digitalisimo-woo-wishlist', array( 'layout' => 'grid', 'columns' => '2', 'title' => 'Mis deseos (res)', 'clear' => 'yes', 'empty_text' => 'Tu lista está vacía (res).' ) ),
			$widget( "res{$p}w03", 'digitalisimo-woo-wishlist-button', array( 'product_source' => 'custom', 'product_id' => $camiseta, 'text' => 'Guardar camiseta (res)', 'color' => '#aa3300' ) ),
			$widget( "res{$p}w04", 'digitalisimo-woo-quick-view', array( 'product_source' => 'custom', 'product_id' => $sudadera, 'button_text' => 'Vistazo (res)', 'qv_title_tag' => 'h3', 'qv_link_text' => 'Ficha completa (res)' ) ),
			$widget( "res{$p}w05", 'digitalisimo-woo-swatches', array( 'max_visible' => 3, 'shape' => 'pill', 'border_color' => '#336699' ) ),
			$widget( "res{$p}w06", 'digitalisimo-woo-price-filter', array( 'selected_element' => $loop, 'bounds' => 'fixed', 'bound_min' => 5, 'bound_max' => 100, 'step' => 5, 'display' => 'fields', 'legend' => 'Precio (res)' ) ),
			$widget( "res{$p}w07", 'digitalisimo-woo-attribute-filter', array( 'selected_element' => $loop, 'attribute' => 'pa_color', 'display' => 'buttons', 'legend' => 'Color (res)', 'clear_text' => 'Quitar color (res)' ) ),
		);
	};

	// Plantilla de bucle de productos.
	$loop_tpl = $find( 'elementor_library', 'digi-wf-res-bucle', 'Bucle de resiliencia' );
	wp_set_object_terms( $loop_tpl, 'loop-item', 'elementor_library_type' );
	update_post_meta( $loop_tpl, '_elementor_source', 'product' );
	$save(
		$loop_tpl,
		array(
			$container(
				'rest100',
				array(
					$widget( 'rest1h0', 'heading', array( 'title' => 'Producto', 'header_size' => 'h3', '__dynamic__' => array( 'title' => '[elementor-tag id="rest1d0" name="post-title" settings="%7B%7D"]' ) ) ),
					$widget( 'rest1w1', 'digitalisimo-woo-swatches', array( 'max_visible' => 4 ) ),
					$widget( 'rest1w2', 'digitalisimo-woo-wishlist-button', array( 'text' => 'Guardar (bucle)' ) ),
					$widget( 'rest1w3', 'digitalisimo-woo-quick-view', array( 'button_text' => 'Vistazo (bucle)' ) ),
				)
			),
		),
		'loop-item'
	);
	$res['ids']['loop_template'] = $loop_tpl;
	$loop = static function ( string $id ) use ( $widget, $loop_tpl ): array {
		return $widget( $id, 'loop-grid', array( '_skin' => 'product', 'template_id' => (string) $loop_tpl, 'posts_per_page' => 12 ) );
	};

	$docs = array(
		// W0.8: los siete en el primer nivel; una página para el md5 (peticiones del editor) y otra para el editor en Chrome.
		'digi-wf-res-sin-woo' => array( 'Sin WooCommerce (resiliencia)', array( $container( 'res0c00', array_merge( array( $heading( 'res0h00', 'Página con los siete widgets' ) ), $seven( '0', '' ) ) ) ) ),
		'digi-wf-res-editor'  => array( 'Editor sin WooCommerce (resiliencia)', array( $container( 'resec00', array_merge( array( $heading( 'reseh00', 'Página para el editor' ) ), $seven( 'e', '' ) ) ) ) ),
		// W3: los siete; las muestras en la plantilla del bucle.
		'digi-wf-res-cache'   => array(
			'Caché de página (resiliencia)',
			array(
				$container(
					'res1c00',
					array(
						$heading( 'res1h00', 'Página para la caché' ),
						$widget( 'res1w01', 'digitalisimo-woo-shipping-bar', array( 'bar_color' => '#225588' ) ),
						$widget( 'res1w02', 'digitalisimo-woo-wishlist', array( 'title' => 'Mis deseos' ) ),
						$widget( 'res1w03', 'digitalisimo-woo-wishlist-button', array( 'product_source' => 'custom', 'product_id' => $camiseta, 'text' => 'Guardar camiseta' ) ),
						$widget( 'res1w04', 'digitalisimo-woo-wishlist-button', array( 'product_source' => 'custom', 'product_id' => $gorra, 'text' => 'Guardar gorra' ) ),
						$widget( 'res1w05', 'digitalisimo-woo-quick-view', array( 'product_source' => 'custom', 'product_id' => $sudadera ) ),
						$widget( 'res1w06', 'digitalisimo-woo-price-filter', array( 'selected_element' => 'resloop' ) ),
						$widget( 'res1w07', 'digitalisimo-woo-attribute-filter', array( 'selected_element' => 'resloop', 'attribute' => 'pa_color' ) ),
						$loop( 'resloop' ),
					)
				),
			),
		),
		// W13: los siete, un bucle con el filtro de precio, Productos con las partes nuevas y el Menú del carrito.
		'digi-wf-res-vuelta'  => array(
			'Vuelta atrás (resiliencia)',
			array(
				$container(
					'res2c00',
					array_merge(
						array( $heading( 'res2h00', 'v0' ) ),
						$seven( '2', 'resl002' ),
						array(
							$loop( 'resl002' ),
							$widget( 'res2p01', 'woocommerce-products', array( 'columns' => 3, 'rows' => 1, 'paginate' => '', 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'title', 'price', 'cart', 'quickview', 'wishlist', 'swatches' ) ) ),
							$widget( 'res2m01', 'woocommerce-menu-cart', array( 'digitalisimo_cart_bar' => 'yes', 'digitalisimo_cart_coupon' => 'yes' ) ),
						)
					)
				),
			),
		),
		// W13.3: un widget propio de una versión futura.
		'digi-wf-res-futuro'  => array( 'Widget futuro (resiliencia)', array( $container( 'res3c00', array( $heading( 'res3h00', 'Widget de una versión futura' ), $widget( 'res3f01', 'digitalisimo-futuro-res', array( 'texto' => 'Hola desde el futuro', 'color' => '#123456', 'lista' => array( 'a', 'b' ) ) ) ) ) ) ),
	);
	foreach ( $docs as $slug => $doc ) {
		$id = $find( 'page', $slug, $doc[0] );
		if ( ! $id ) {
			$err( "no se pudo crear la página $slug" );
			continue;
		}
		$save( $id, $doc[1], 'wp-page' );
		$res['ids'][ $slug ]   = $id;
		$res['pages'][ $slug ] = get_permalink( $id );
	}
	$res['products']  = array( 'camiseta' => (int) $camiseta, 'gorra' => (int) $gorra, 'sudadera' => (int) $sudadera );
	$res['variable']  = get_permalink( (int) $sudadera );
	$out['resilience'] = $res;
	return $out;
};
