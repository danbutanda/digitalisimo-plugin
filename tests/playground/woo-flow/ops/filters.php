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
 * - `filters_config`: fija `woocommerce_attribute_lookup_enabled` (`lookup=yes|no`), `woocommerce_hide_out_of_stock_items`
 *   (`hide=yes|no`) y `woocommerce_catalog_rows` (`rows=N`) y devuelve los valores anteriores.
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
					update_option( $option, max( 1, (int) $value ) );
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
