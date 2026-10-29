<?php
/**
 * Operaciones de sonda del grupo `filters` de woo-flow.sh (W9 y W10; F4-Woo, V.8), con `?digi_wf=<op>&token=…`:
 *
 * - `filters_catalog`: crea y publica (idempotente) los ocho productos del calzado, con la etiqueta `digi-wf-filtros`
 *   y el atributo `pa_talla-zapato`: simples con y sin existencias, dos variables con variaciones agotadas y uno sin
 *   talla. No van en la preparación porque W0.4 cuenta siete productos publicados justo después de ella. Con
 *   `woocommerce_attribute_lookup_direct_updates = yes`, regenera la tabla de atributos con el regenerador de
 *   WooCommerce (el de «Estado → Herramientas» y `wc palt regenerate`) cuando faltan filas, y devuelve las filas de los
 *   ocho, las acciones pendientes de Action Scheduler de la tabla y los ajustes.
 * - `filters_matrix`: en una sola petición, para cada configuración de `spec` (tabla de atributos y «Ocultar
 *   agotados») y cada caso, los productos de la tienda nativa (la consulta principal del archivo de la etiqueta con los
 *   parámetros de WooCommerce en `$_GET`: `WC_Query::pre_get_posts()` y sus `posts_clauses`, como en la página) y los del
 *   bucle por la ruta REST del refresco (`rest_do_request`, como visitante y con el almacén del módulo `loop-filter`
 *   vacío en cada caso). El escenario la contrasta con peticiones HTTP reales en los casos de referencia.
 * - `filters_config`: fija `woocommerce_attribute_lookup_enabled` (`lookup=yes|no`), `woocommerce_hide_out_of_stock_items`
 *   (`hide=yes|no`) y `woocommerce_catalog_rows` (`rows=N`; 0 la borra) y devuelve los valores anteriores.
 * - `filters_controls`: la configuración de los controles de los dos filtros como la recibe el editor (sin la carga
 *   optimizada del front, que quita el texto de los avisos): los avisos de los destinos y sus condiciones.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'filters_catalog'  => static function () {
		global $wpdb;
		$out = array( 'blog' => get_current_blog_id(), 'errors' => array(), 'created' => array(), 'products' => array() );
		if ( ! function_exists( 'wc_get_product' ) ) {
			$out['errors'][] = 'WooCommerce no está cargado';
			return $out;
		}
		$taxonomy = 'pa_talla-zapato';
		$attr_id  = (int) wc_attribute_taxonomy_id_by_name( $taxonomy );
		$terms    = array();
		foreach ( array( 'eu-38', 'eu-40', 'eu-42' ) as $slug ) {
			$term            = get_term_by( 'slug', $slug, $taxonomy );
			$terms[ $slug ] = $term ? (int) $term->term_id : 0;
		}
		$cat = static function ( string $slug ): int {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			return $term ? (int) $term->term_id : 0;
		};
		$tag = get_term_by( 'slug', 'digi-wf-filtros', 'product_tag' );
		if ( ! $attr_id || in_array( 0, $terms, true ) || ! $tag || ! $cat( 'digi-wf-calzado' ) ) {
			$out['errors'][] = 'faltan el atributo, sus términos, la etiqueta o las categorías de setup/filters.php';
			return $out;
		}
		$attribute = static function ( array $slugs, bool $variation ) use ( $attr_id, $taxonomy, $terms ): \WC_Product_Attribute {
			$a = new \WC_Product_Attribute();
			$a->set_id( $attr_id );
			$a->set_name( $taxonomy );
			$a->set_options( array_values( array_map( static function ( $s ) use ( $terms ) { return $terms[ $s ]; }, $slugs ) ) );
			$a->set_position( 0 );
			$a->set_visible( true );
			$a->set_variation( $variation );
			return $a;
		};
		// slug => [ nombre, categoría, precio o variaciones (talla => [ precio, existencias ]), existencias, tallas ].
		$catalog = array(
			'digi-wf-bota-alta'     => array( 'Bota alta de prueba', 'digi-wf-botas', '80', 'instock', array( 'eu-40' ) ),
			'digi-wf-bota-agotada'  => array( 'Bota agotada de prueba', 'digi-wf-botas', '95', 'outofstock', array( 'eu-40' ) ),
			'digi-wf-bota-corta'    => array( 'Bota corta de prueba', 'digi-wf-botas', '65', 'instock', array( 'eu-38', 'eu-40' ) ),
			'digi-wf-sandalia'      => array( 'Sandalia de prueba', 'digi-wf-sandalias', '30', 'instock', array( 'eu-38', 'eu-42' ) ),
			'digi-wf-sandalia-dos'  => array( 'Sandalia dos de prueba', 'digi-wf-sandalias', '35', 'instock', array( 'eu-42' ) ),
			'digi-wf-zapato-liso'   => array( 'Zapato liso de prueba', 'digi-wf-calzado', '40', 'instock', array() ),
			'digi-wf-deportiva'     => array( 'Deportiva de prueba', 'digi-wf-deportivas', array( 'eu-38' => array( '50', 'instock' ), 'eu-40' => array( '55', 'outofstock' ), 'eu-42' => array( '60', 'instock' ) ), '', array() ),
			'digi-wf-deportiva-dos' => array( 'Deportiva dos de prueba', 'digi-wf-deportivas', array( 'eu-40' => array( '70', 'instock' ), 'eu-42' => array( '72', 'outofstock' ) ), '', array() ),
		);
		$index = 0;
		foreach ( $catalog as $slug => $data ) {
			$index++;
			$found = get_posts( array( 'post_type' => 'product', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
			if ( $found ) {
				$id = (int) $found[0];
				if ( 'publish' !== get_post_status( $id ) ) {
					wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
				}
				$out['products'][ $slug ] = $id;
				continue;
			}
			$variable = is_array( $data[2] );
			$product  = $variable ? new \WC_Product_Variable() : new \WC_Product_Simple();
			$product->set_name( $data[0] );
			$product->set_slug( $slug );
			$product->set_status( 'publish' );
			$product->set_sku( strtoupper( $slug ) );
			$product->set_category_ids( array( $cat( $data[1] ) ) );
			$product->set_tag_ids( array( (int) $tag->term_id ) );
			$product->set_date_created( time() - ( 30 + $index ) * HOUR_IN_SECONDS );
			if ( $variable ) {
				$product->set_attributes( array( $attribute( array_keys( $data[2] ), true ) ) );
			} else {
				$product->set_regular_price( $data[2] );
				$product->set_manage_stock( false );
				$product->set_stock_status( $data[3] );
				if ( $data[4] ) {
					$product->set_attributes( array( $attribute( $data[4], false ) ) );
				}
			}
			$id = (int) $product->save();
			if ( $variable ) {
				foreach ( $data[2] as $size => $variation_data ) {
					$variation = new \WC_Product_Variation();
					$variation->set_parent_id( $id );
					$variation->set_attributes( array( $taxonomy => $size ) );
					$variation->set_regular_price( $variation_data[0] );
					$variation->set_sku( strtoupper( $slug . '-' . $size ) );
					$variation->set_manage_stock( false );
					$variation->set_stock_status( $variation_data[1] );
					$variation->set_status( 'publish' );
					$variation->save();
				}
				\WC_Product_Variable::sync( $id );
				wc_delete_product_transients( $id );
			}
			$out['products'][ $slug ] = $id;
			$out['created'][]         = $slug;
		}

		// Tabla de atributos: filas esperadas de los ocho (12: una por talla de cada simple y una por variación).
		$lookup = $wpdb->prefix . 'wc_product_attributes_lookup';
		$ids    = array_values( $out['products'] );
		$rows   = static function () use ( $wpdb, $lookup, $ids, $taxonomy ): array {
			$list = implode( ',', array_map( 'intval', $ids ) );
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT product_id, product_or_parent_id, term_id, is_variation_attribute, in_stock FROM {$lookup} WHERE product_or_parent_id IN ({$list}) AND taxonomy = %s ORDER BY product_or_parent_id, product_id, term_id", $taxonomy ), ARRAY_A ); // phpcs:ignore WordPress.DB
		};
		$before                  = $rows();
		$out['regenerated']      = false;
		$out['regenerate_error'] = '';
		if ( $out['created'] || 12 !== count( $before ) ) {
			$class = '\Automattic\WooCommerce\Internal\ProductAttributesLookup\DataRegenerator';
			try {
				$regenerator = wc_get_container()->get( $class );
				$regenerator->initiate_regeneration( false );
				$steps = 0;
				while ( $regenerator->do_regeneration_step() && ++$steps < 100 ) {
					// Un paso por cada cien productos, como `wc palt regenerate`.
				}
				$regenerator->finalize_regeneration( true );
				$out['regenerated'] = true;
			} catch ( \Throwable $e ) {
				$out['regenerate_error'] = $e->getMessage();
			}
		}
		$after         = $rows();
		$out['lookup'] = array();
		foreach ( $after as $row ) {
			$parent = array_search( (int) $row['product_or_parent_id'], $out['products'], true );
			$size   = array_search( (int) $row['term_id'], $terms, true );
			$out['lookup'][] = $parent . '/' . $size . '/' . ( (int) $row['is_variation_attribute'] ? 'var' : 'prod' ) . '/' . ( (int) $row['in_stock'] ? 'in' : 'out' );
		}
		$out['lookup_rows_before'] = count( $before );
		$out['lookup_rows']        = count( $after );
		$out['lookup_total']       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lookup}" ); // phpcs:ignore WordPress.DB
		$out['pending']            = array();
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( array( 'woocommerce_run_product_attribute_lookup_update_callback', 'woocommerce_run_product_attribute_lookup_regeneration_callback' ) as $hook ) {
				$out['pending'][ $hook ] = count( (array) as_get_scheduled_actions( array( 'hook' => $hook, 'status' => 'pending', 'per_page' => 50 ), 'ids' ) );
			}
		}
		foreach ( array( 'woocommerce_attribute_lookup_enabled', 'woocommerce_attribute_lookup_direct_updates', 'woocommerce_hide_out_of_stock_items', 'woocommerce_catalog_rows', 'woocommerce_catalog_columns' ) as $option ) {
			$out['options'][ $option ] = get_option( $option );
		}
		$out['stock'] = array();
		foreach ( $out['products'] as $slug => $id ) {
			$product              = wc_get_product( $id );
			$out['stock'][ $slug ] = $product ? $product->get_stock_status() : null;
		}
		$out['published'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB
		return $out;
	},

	'filters_matrix'   => static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		$spec = isset( $_GET['spec'] ) ? json_decode( (string) wp_unslash( $_GET['spec'] ), true ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! is_array( $spec ) || empty( $spec['page'] ) || empty( $spec['loop'] ) || empty( $spec['cases'] ) || empty( $spec['configs'] ) ) {
			return array( 'error' => 'spec' );
		}
		if ( ! class_exists( '\ElementorPro\Plugin' ) || ! class_exists( 'WC_Query' ) || ! function_exists( 'rest_do_request' ) ) {
			return array( 'error' => 'faltan PRO, WooCommerce o la API REST' );
		}
		$t0     = microtime( true );
		$saved  = array( 'get' => $_GET, 'request' => $_REQUEST, 'query' => $GLOBALS['wp_query'], 'the_query' => $GLOBALS['wp_the_query'], 'user' => get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification
		$module = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'loop-filter' );
		$store  = new \ReflectionProperty( get_class( $module ), 'filters' );
		$store->setAccessible( true );
		$chosen = new \ReflectionProperty( 'WC_Query', 'chosen_attributes' );
		$chosen->setAccessible( true );
		$ids_of = static function ( $html ): array {
			preg_match_all( '/class="[^"]*\be-loop-item-(\d+)\b/', (string) $html, $m );
			return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
		};
		// La tienda nativa: la consulta principal de un archivo de productos con los parámetros de WooCommerce en
		// `$_GET` (`WC_Query::pre_get_posts()` y sus `posts_clauses`, como en la página).
		$native = static function ( array $vars, array $get ) use ( $chosen ): array {
			$_GET     = $get; // phpcs:ignore WordPress.Security.NonceVerification
			$_REQUEST = $get; // phpcs:ignore WordPress.Security.NonceVerification
			$chosen->setValue( null, null );
			$query                   = new \WP_Query();
			$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$query->query( $vars );
			return array_values( array_map( static function ( $post ) { return is_object( $post ) ? (int) $post->ID : (int) $post; }, (array) $query->posts ) );
		};
		// El bucle por el refresco: la misma ruta REST que el coordinador, como visitante y con el almacén del módulo vacío.
		$loop = static function ( array $filters ) use ( $spec, $module, $store, $ids_of ): array {
			$_GET     = array(); // phpcs:ignore WordPress.Security.NonceVerification
			$_REQUEST = array(); // phpcs:ignore WordPress.Security.NonceVerification
			$store->setValue( $module, array() );
			$query                   = new \WP_Query();
			$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$request = new \WP_REST_Request( 'POST', '/elementor-pro/v1/refresh-loop' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'post_id' => (int) $spec['page'], 'widget_id' => (string) $spec['loop'], 'widget_filters' => (object) $filters, 'pagination_base_url' => (string) $spec['base'] ) ) );
			$response = rest_do_request( $request );
			$data     = $response->get_data();
			if ( $response->is_error() || ! is_array( $data ) || ! isset( $data['data'] ) ) {
				return array( 'error' => $response->get_status() . ' ' . wp_json_encode( $data ) );
			}
			return $ids_of( $data['data'] );
		};
		wp_set_current_user( 0 );
		$out = array( 'configs' => array() );
		try {
			foreach ( $spec['configs'] as $config ) {
				update_option( 'woocommerce_attribute_lookup_enabled', 'yes' === ( $config['lookup'] ?? '' ) ? 'yes' : 'no' );
				update_option( 'woocommerce_hide_out_of_stock_items', 'yes' === ( $config['hide'] ?? '' ) ? 'yes' : 'no' );
				$key   = get_option( 'woocommerce_attribute_lookup_enabled' ) . '/' . get_option( 'woocommerce_hide_out_of_stock_items' );
				$cases = array_merge( array( array( 'id' => 'base', 'get' => array(), 'filters' => array() ) ), $spec['cases'] );
				foreach ( $cases as $case ) {
					$vars                                    = isset( $case['vars'] ) && is_array( $case['vars'] ) ? $case['vars'] : array( 'product_tag' => (string) $spec['tag'] );
					$out['configs'][ $key ][ $case['id'] ] = array(
						'native' => $native( $vars, isset( $case['get'] ) && is_array( $case['get'] ) ? $case['get'] : array() ),
						'loop'   => $loop( isset( $case['filters'] ) && is_array( $case['filters'] ) ? $case['filters'] : array() ),
					);
				}
			}
		} catch ( \Throwable $e ) {
			$out['error'] = get_class( $e ) . ': ' . $e->getMessage() . ' en ' . basename( $e->getFile() ) . ':' . $e->getLine();
		} finally {
			$_GET                    = $saved['get']; // phpcs:ignore WordPress.Security.NonceVerification
			$_REQUEST                = $saved['request']; // phpcs:ignore WordPress.Security.NonceVerification
			$GLOBALS['wp_query']     = $saved['query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['wp_the_query'] = $saved['the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$chosen->setValue( null, null );
			wp_set_current_user( $saved['user'] );
		}
		$out['ms'] = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		return $out;
	},

	'filters_config'   => static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		$map    = array( 'lookup' => 'woocommerce_attribute_lookup_enabled', 'hide' => 'woocommerce_hide_out_of_stock_items', 'rows' => 'woocommerce_catalog_rows' );
		$before = array();
		$after  = array();
		foreach ( $map as $param => $option ) {
			$before[ $option ] = get_option( $option );
			if ( isset( $_GET[ $param ] ) ) {
				$value = sanitize_key( wp_unslash( $_GET[ $param ] ) );
				if ( 'rows' === $param ) {
					// 0: sin la opción, como antes de la prueba (WooCommerce usa su valor por defecto).
					if ( (int) $value > 0 ) {
						update_option( $option, (int) $value );
					} else {
						delete_option( $option );
					}
				} elseif ( in_array( $value, array( 'yes', 'no' ), true ) ) {
					update_option( $option, $value );
				}
			}
			$after[ $option ] = get_option( $option );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return array( 'before' => $before, 'after' => $after );
	},

	'filters_controls' => static function () {
		$out = array();
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return array( 'error' => 'sin constructor' );
		}
		// Como en el editor: sin la carga optimizada del front, que deja los avisos (RAW_HTML) sin su texto.
		$performance = '\Elementor\Core\Frontend\Performance';
		if ( class_exists( $performance ) ) {
			$prop = new \ReflectionProperty( $performance, 'is_frontend' );
			$prop->setAccessible( true );
			$out['optimized_before'] = $prop->getValue();
			$prop->setValue( null, false );
		}
		foreach ( array( 'digitalisimo-woo-price-filter', 'digitalisimo-woo-attribute-filter' ) as $name ) {
			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name );
			if ( ! $widget ) {
				$out[ $name ] = null;
				continue;
			}
			\Elementor\Plugin::$instance->controls_manager->delete_stack( $widget );
			$controls     = $widget->get_controls();
			$pick         = static function ( $id ) use ( $controls ) {
				return isset( $controls[ $id ] ) ? array( 'type' => $controls[ $id ]['type'] ?? '', 'raw' => $controls[ $id ]['raw'] ?? '', 'condition' => $controls[ $id ]['condition'] ?? null ) : null;
			};
			$out[ $name ] = array(
				'destination' => isset( $controls['destination'] ) ? array( 'default' => $controls['destination']['default'] ?? '', 'options' => $controls['destination']['options'] ?? array() ) : null,
				'loop_note'   => $pick( 'loop_note' ),
				'shop_note'   => $pick( 'shop_note' ),
			);
		}
		return $out;
	},
);
