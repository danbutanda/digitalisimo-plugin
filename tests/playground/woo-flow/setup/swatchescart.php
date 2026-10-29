<?php
/**
 * Preparación del grupo `swatchescart` de woo-flow.sh (W6 muestras, W7 barra de envío, W8 cupón; F4-Woo, V.16.4).
 * Corre en la petición `?digi_wf=setup` de cada sitio, con WooCommerce cargado y el usuario 1. Idempotente.
 *
 * - Atributos `pa_tono` («Muestra visual», con la meta `color` de WooCommerce en cada término) y `pa_medida`
 *   («Etiqueta»), configurados en `digitalisimo_elements_swatches` con `Swatches\Data::put()`. Con un tema de bloques
 *   (THEME=block) se activa la función `wc-visual-attribute` y `pa_tono` se crea de tipo `wc-visual`: el bloque «Añadir
 *   al carrito con opciones» pinta sus muestras con esas mismas metas (W6.4).
 * - Productos: `digi-wf-sc-muestras` (tono × medida con una combinación ausente: WooCommerce quita opciones),
 *   `digi-wf-sc-ajax` (31 variaciones: modo AJAX, con `required` en sus desplegables por el mu-plugin) y el simple
 *   `digi-wf-sc-diez` (10 €, para fijar el total del carrito en W7 y W8).
 * - Páginas del constructor: `digi-wf-sc-bucle` (Loop Grid de productos con el widget «Muestras de variación» en su
 *   plantilla) y `digi-wf-sc-minicarrito` (Menú del carrito con la barra y el cupón, y el widget «Barra de envío
 *   gratis»). Con un tema de bloques, además `digi-wf-sc-convivencia`: el bloque «Producto» con «Añadir al carrito con
 *   opciones» y, en la misma página, una plantilla del constructor con el widget «Añadir al carrito» de PRO.
 * - mu-plugin `digi-wf-swatchescart.php` en WPMU_PLUGIN_DIR: `required` en los desplegables del producto de 31
 *   variaciones y los casos de W7/W8, cada uno activado por una opción del sitio (`ops/swatchescart.php` los cambia y los
 *   restablece). Sin opciones, no cambia nada.
 */

defined( 'ABSPATH' ) || exit;

return static function ( array $out, callable $fail ): array {
	$res = array( 'errors' => array() );
	$err = static function ( $message ) use ( $fail, &$res ) {
		$res['errors'][] = $message;
		$fail( 'swatchescart: ' . $message );
	};
	$data_class = '\\Digitalisimo\\Elements\\Woo\\Swatches\\Data';
	$res['f4woo'] = class_exists( $data_class );
	$res['block'] = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

	// mu-plugin de los casos (uno para toda la red; cada sitio lo activa con sus opciones).
	$mu   = WPMU_PLUGIN_DIR . '/digi-wf-swatchescart.php';
	$code = <<<'MU'
<?php
/**
 * Plugin Name: woo-flow · swatchescart (sólo Playground)
 * Description: casos de W6–W8 de tests/playground/woo-flow.sh. Cada uno se activa con una opción del sitio que cambia
 * woo-flow/ops/swatchescart.php; sin ellas, sólo pone `required` en los desplegables del producto de 31 variaciones.
 */
defined( 'ABSPATH' ) || exit;

// W6: `required` en los desplegables de un producto (WooCommerce no lo pone; un tema o un plugin pueden hacerlo).
add_filter(
	'woocommerce_dropdown_variation_attribute_options_args',
	static function ( $args ) {
		$id = (int) get_option( 'digi_wf_sc_required_product' );
		if ( $id && isset( $args['product'] ) && $args['product'] instanceof WC_Product && $id === (int) $args['product']->get_id() ) {
			$args['required'] = true;
		}
		return $args;
	}
);

// Playground (un solo proceso, máquina cargada) puede tardar más de los 5 s que cart-fragments.js espera a
// `get_refreshed_fragments`: el mini carrito y la barra se quedarían sin fragmentos por tiempo, no por lo que se prueba.
add_filter(
	'woocommerce_get_script_data',
	static function ( $params, $handle ) {
		if ( 'wc-cart-fragments' === $handle && is_array( $params ) ) {
			$params['request_timeout'] = 120000;
		}
		return $params;
	},
	10,
	2
);

// W7: filtros de terceros sobre el envío.
switch ( (string) get_option( 'digi_wf_sc_case', '' ) ) {
	case 'rates': // Un tercero quita el envío gratuito de las tarifas.
		add_filter(
			'woocommerce_package_rates',
			static function ( $rates ) {
				foreach ( (array) $rates as $key => $rate ) {
					if ( is_object( $rate ) && 'free_shipping' === $rate->get_method_id() ) {
						unset( $rates[ $key ] );
					}
				}
				return $rates;
			}
		);
		break;
	case 'available': // Un tercero niega el envío gratuito.
		add_filter( 'woocommerce_shipping_free_shipping_is_available', '__return_false' );
		break;
	case 'instance': // Un plugin de divisas convierte el importe mínimo (×2).
		add_filter(
			'woocommerce_shipping_free_shipping_instance_option',
			static function ( $value, $key ) {
				return 'min_amount' === $key && is_numeric( $value ) ? (string) ( 2 * (float) $value ) : $value;
			},
			10,
			2
		);
		break;
	case 'packages': // Dos paquetes (como un plugin de varios almacenes).
		add_filter(
			'woocommerce_cart_shipping_packages',
			static function ( $packages ) {
				if ( 1 === count( $packages ) ) {
					$packages[] = reset( $packages );
				}
				return $packages;
			}
		);
		break;
}

// W8: el reloj de los nonces del servidor avanza (otro `nonce_life` cambia el tick, como si hubieran pasado días): los
// nonces que ya tiene la página caducan y los que se emiten después son válidos.
if ( get_option( 'digi_wf_sc_clock' ) ) {
	add_filter(
		'nonce_life',
		static function () {
			return 2 * HOUR_IN_SECONDS;
		}
	);
}
// W8: todo nonce de `apply_coupon` llega caducado (para comprobar que sólo se reintenta una vez).
if ( get_option( 'digi_wf_sc_nonce_fail' ) ) {
	add_action(
		'wc_ajax_apply_coupon',
		static function () {
			$_POST['security']    = 'caducado';
			$_REQUEST['security'] = 'caducado';
		},
		1
	);
}

MU;
	if ( ! file_exists( $mu ) || md5_file( $mu ) !== md5( $code ) ) {
		file_put_contents( $mu, $code );
	}

	// Con un tema de bloques, la función de muestras de WooCommerce (su opción, leída en cada llamada).
	if ( $res['block'] ) {
		update_option( 'woocommerce_feature_wc_visual_attribute_enabled', 'yes' );
	}

	// Atributos y términos.
	$attributes = array(
		'tono'   => array( 'Tono', $res['block'] ? 'wc-visual' : 'select', array( 'rojo' => array( 'Rojo', '#c0392b' ), 'azul' => array( 'Azul', '#2155cf' ), 'verde' => array( 'Verde', '#1e8449' ), 'negro' => array( 'Negro', '#111111' ) ) ),
		'medida' => array( 'Medida', 'select', array( 'xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', 'xxl' => 'XXL', '3xl' => '3XL', '4xl' => '4XL' ) ),
	);
	$terms = array();
	foreach ( $attributes as $slug => $def ) {
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
			$created = wc_create_attribute( array( 'name' => $def[0], 'slug' => $slug, 'type' => $def[1], 'order_by' => 'menu_order', 'has_archives' => false ) );
			if ( is_wp_error( $created ) ) {
				$err( "atributo $slug: " . $created->get_error_message() );
				continue;
			}
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
		}
		$order = 0;
		foreach ( $def[2] as $term_slug => $term_def ) {
			$name = is_array( $term_def ) ? $term_def[0] : $term_def;
			$term = term_exists( $term_slug, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy, array( 'slug' => $term_slug ) );
			}
			if ( is_wp_error( $term ) ) {
				$err( "término $taxonomy/$term_slug: " . $term->get_error_message() );
				continue;
			}
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			update_term_meta( $term_id, 'order', $order++ );
			if ( is_array( $term_def ) ) {
				// El formato de `save_term_visual()` de WooCommerce 11.2.1, por la ayuda de Elements si existe.
				if ( $res['f4woo'] ) {
					$data_class::save_term( $term_id, $term_def[1], 0 );
				} else {
					update_term_meta( $term_id, 'color', $term_def[1] );
				}
			}
			$terms[ $taxonomy ][ $term_slug ] = $term_id;
		}
	}
	$res['attribute_types'] = array();
	foreach ( wc_get_attribute_taxonomies() as $attribute ) {
		if ( isset( $attributes[ $attribute->attribute_name ] ) ) {
			$res['attribute_types'][ $attribute->attribute_name ] = $attribute->attribute_type;
		}
	}
	if ( $res['block'] && 'wc-visual' !== ( $res['attribute_types']['tono'] ?? '' ) ) {
		$err( 'THEME=block: pa_tono no quedó de tipo wc-visual (' . wp_json_encode( $res['attribute_types'] ) . ')' );
	}
	$res['colors'] = array();
	foreach ( $attributes['tono'][2] as $term_slug => $term_def ) {
		$res['colors'][ $term_slug ] = $term_def[1];
	}

	// Tipo propio de cada atributo (opt-in del administrador, V.6.2): tono visual y medida etiqueta.
	if ( $res['f4woo'] ) {
		$data_class::put( 'tono', array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 ) );
		$data_class::put( 'medida', array( 'type' => 'label', 'shape' => 'pill', 'size' => 32 ) );
		$data_class::flush();
		$res['swatches'] = $data_class::all();
	}

	// Productos.
	$variable = static function ( $slug, $name, $price, array $combos, int $hours ) use ( $terms, $err ) {
		$existing = get_page_by_path( $slug, OBJECT, 'product' );
		if ( $existing ) {
			return (int) $existing->ID;
		}
		if ( empty( $terms['pa_tono'] ) || empty( $terms['pa_medida'] ) ) {
			$err( "$slug necesita pa_tono y pa_medida" );
			return 0;
		}
		$used = array( 'pa_tono' => array(), 'pa_medida' => array() );
		foreach ( $combos as $combo ) {
			$used['pa_tono'][ $combo[0] ]   = $terms['pa_tono'][ $combo[0] ];
			$used['pa_medida'][ $combo[1] ] = $terms['pa_medida'][ $combo[1] ];
		}
		$list     = array();
		$position = 0;
		foreach ( $used as $taxonomy => $ids ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array_values( $ids ) );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$list[] = $attribute;
		}
		$product = new WC_Product_Variable();
		$product->set_name( $name );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_sku( strtoupper( $slug ) );
		$product->set_attributes( $list );
		$product->set_date_created( time() - $hours * HOUR_IN_SECONDS );
		$id = (int) $product->save();
		foreach ( $combos as $combo ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $id );
			$variation->set_attributes( array( 'pa_tono' => $combo[0], 'pa_medida' => $combo[1] ) );
			$variation->set_regular_price( (string) $price );
			$variation->set_sku( strtoupper( $slug . '-' . $combo[0] . '-' . $combo[1] ) );
			$variation->set_status( 'publish' );
			$variation->save();
		}
		WC_Product_Variable::sync( $id );
		wc_delete_product_transients( $id );
		return $id;
	};
	// Tono × medida S/M sin «verde M»: elegir M quita «verde» (V.6.4, modo nativo).
	$muestras = $variable( 'digi-wf-sc-muestras', 'Camisa con muestras', 20, array( array( 'rojo', 's' ), array( 'rojo', 'm' ), array( 'azul', 's' ), array( 'azul', 'm' ), array( 'verde', 's' ) ), 20 );
	// 4 tonos × 8 medidas sin «negro 4XL»: 31 variaciones, más que `woocommerce_ajax_variation_threshold` (30).
	$combos = array();
	foreach ( array_keys( $attributes['tono'][2] ) as $tono ) {
		foreach ( array_keys( $attributes['medida'][2] ) as $medida ) {
			if ( 'negro' !== $tono || '4xl' !== $medida ) {
				$combos[] = array( $tono, $medida );
			}
		}
	}
	$ajax = $variable( 'digi-wf-sc-ajax', 'Abrigo con 31 variaciones', 25, $combos, 21 );
	update_option( 'digi_wf_sc_required_product', $ajax, false );
	$diez = get_page_by_path( 'digi-wf-sc-diez', OBJECT, 'product' );
	if ( $diez ) {
		$diez = (int) $diez->ID;
	} else {
		$simple = new WC_Product_Simple();
		$simple->set_name( 'Pañuelo de diez euros' );
		$simple->set_slug( 'digi-wf-sc-diez' );
		$simple->set_status( 'publish' );
		$simple->set_regular_price( '10' );
		$simple->set_sku( 'DIGI-WF-SC-DIEZ' );
		$simple->set_date_created( time() - 22 * HOUR_IN_SECONDS );
		$diez = (int) $simple->save();
	}
	$res['products'] = array( 'muestras' => $muestras, 'ajax' => $ajax, 'diez' => $diez, 'sudadera' => (int) ( $out['products']['digi-wf-sudadera'] ?? 0 ) );
	$res['variations'] = array();
	foreach ( array( 'muestras', 'ajax' ) as $key ) {
		$product                    = $res['products'][ $key ] ? wc_get_product( $res['products'][ $key ] ) : null;
		$res['variations'][ $key ] = $product ? count( $product->get_children() ) : 0;
	}
	if ( 5 !== $res['variations']['muestras'] || 31 !== $res['variations']['ajax'] ) {
		$err( 'variaciones: ' . wp_json_encode( $res['variations'] ) . ' (se esperaban 5 y 31)' );
	}
	$res['ajax_threshold'] = (int) apply_filters( 'woocommerce_ajax_variation_threshold', 30, wc_get_product( $ajax ) );

	// Envío gratuito de la zona «España» (lo cambian los casos de W7).
	$zone = WC_Shipping_Zones::get_zone( (int) get_option( 'digi_wf_zone' ) );
	foreach ( $zone ? $zone->get_shipping_methods() : array() as $method ) {
		if ( 'free_shipping' === $method->id ) {
			$res['free_instance'] = (int) $method->get_instance_id();
		}
	}
	if ( empty( $res['free_instance'] ) ) {
		$err( 'la zona España no tiene «Envío gratuito»' );
	}

	// Documentos del constructor.
	$eid  = static function ( $seed ) {
		return substr( md5( 'digi-wf-sc-' . $seed ), 0, 7 );
	};
	$save = static function ( $id, array $tree, string $type ) {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $type );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	};
	$post = static function ( $type, $slug, $title, $content = '' ) {
		$found = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		return $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	};
	$widget = static function ( $seed, $type, array $settings ) use ( $eid ) {
		return array( 'id' => $eid( $seed ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => (object) $settings, 'elements' => array() );
	};
	$container = static function ( $seed, array $elements ) use ( $eid ) {
		return array( 'id' => $eid( $seed ), 'elType' => 'container', 'settings' => (object) array(), 'elements' => $elements );
	};

	// Plantilla de bucle de productos con el widget de muestras.
	$card = $post( 'elementor_library', 'digi-wf-sc-tarjeta', 'Tarjeta con muestras' );
	wp_set_object_terms( $card, 'loop-item', 'elementor_library_type' );
	update_post_meta( $card, '_elementor_source', 'product' );
	$save(
		$card,
		array(
			$container(
				'card',
				array(
					$widget( 'card-title', 'heading', array( 'title' => 'Producto', '__dynamic__' => array( 'title' => '[elementor-tag id="' . $eid( 'card-tag' ) . '" name="post-title" settings="%7B%7D"]' ) ) ),
					$widget( 'card-swatches', 'digitalisimo-woo-swatches', array( 'max_visible' => 0 ) ),
				)
			),
		),
		'loop-item'
	);
	$pages = array();
	$loop  = $post( 'page', 'digi-wf-sc-bucle', 'Bucle con muestras' );
	$save( $loop, array( $container( 'loop', array( $widget( 'loop-grid', 'loop-grid', array( '_skin' => 'product', 'template_id' => $card, 'posts_per_page' => 20, 'columns' => 3 ) ) ) ) ), 'wp-page' );
	$pages['bucle'] = get_permalink( $loop );

	// Mini carrito con la barra y el cupón «Arriba», y el widget de la barra suelto.
	$cart = $post( 'page', 'digi-wf-sc-minicarrito', 'Mini carrito con barra y cupón' );
	$save(
		$cart,
		array(
			$container(
				'cart',
				array(
					$widget( 'menu-cart', 'woocommerce-menu-cart', array( 'digitalisimo_cart_bar' => 'yes', 'digitalisimo_cart_coupon' => 'yes' ) ),
					$widget( 'ship-bar', 'digitalisimo-woo-shipping-bar', array() ),
				)
			),
		),
		'wp-page'
	);
	$pages['minicarrito'] = get_permalink( $cart );
	$res['menu_cart_id']  = $eid( 'menu-cart' );

	// THEME=block: el bloque «Añadir al carrito con opciones» y el widget clásico de PRO en la misma página (V.6.5).
	if ( $res['block'] ) {
		$tpl = $post( 'elementor_library', 'digi-wf-sc-clasico', 'Añadir al carrito clásico' );
		wp_set_object_terms( $tpl, 'section', 'elementor_library_type' );
		$save( $tpl, array( $container( 'atc', array( $widget( 'atc', 'wc-add-to-cart', array( 'product_id' => (string) $muestras, 'show_quantity' => 'yes' ) ) ) ) ), 'section' );
		$content  = '<!-- wp:woocommerce/single-product {"productId":' . (int) $muestras . '} -->' . "\n";
		$content .= '<div class="wp-block-woocommerce-single-product woocommerce"><!-- wp:woocommerce/add-to-cart-with-options /--></div>' . "\n";
		$content .= '<!-- /wp:woocommerce/single-product -->' . "\n\n";
		$content .= '<!-- wp:shortcode -->' . "\n" . '[elementor-template id="' . (int) $tpl . '"]' . "\n" . '<!-- /wp:shortcode -->';
		$both = $post( 'page', 'digi-wf-sc-convivencia', 'Bloque y widget clásico', $content );
		if ( get_post_field( 'post_content', $both ) !== $content ) {
			wp_update_post( array( 'ID' => $both, 'post_content' => $content ) );
		}
		$pages['convivencia'] = get_permalink( $both );
		$res['feature']       = get_option( 'woocommerce_feature_wc_visual_attribute_enabled' );
		$res['types']         = array_keys( wc_get_attribute_types() );
	}

	// La bandera de fragmentos (V.12.4) la recalculan los ganchos de metas; se asegura aquí.
	$flags = '\\Digitalisimo\\Elements\\Woo\\Flags';
	if ( class_exists( $flags ) && method_exists( $flags, 'refresh' ) ) {
		$flags::refresh();
		$res['fragments_flag'] = (int) get_option( $flags::FRAGMENTS );
	}

	$res['pages'] = $pages;
	$res['urls']  = array();
	foreach ( $res['products'] as $key => $id ) {
		$res['urls'][ $key ] = $id ? get_permalink( $id ) : '';
	}
	$out['swatchescart'] = $res;
	return $out;
};
