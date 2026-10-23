<?php
/**
 * Dobles de WooCommerce 11.2.1 para `tests/elements-woo-kit.php`, con las reglas que importan a las partes de kits:
 *
 * - `wc_create_attribute()` vuelve a `select` un tipo que el sitio no ofrece (`wc-visual` sólo con la función
 *   encendida) y no registra la taxonomía (WooCommerce lo hace en el `init` siguiente), como
 *   `W:includes/wc-attribute-functions.php:554-556`;
 * - `WC_Product::set_sku()` lanza `WC_Data_Exception` con un SKU que ya usa otro producto
 *   (`W:includes/abstracts/abstract-wc-product.php:866-882`);
 * - `WC_Data_Store::has_callable()` dice si existe `refresh_product_lookup_table()` (10.8+).
 *
 * El estado vive en `$GLOBALS['wc']` (lo vacía `woo_kit_reset()`) y en el WordPress en memoria de los stubs de K.
 */

// Lo que mira la guarda del frente A (`Woo\Module::woocommerce_ok()`).
define( 'WC_VERSION', '11.2.1' );
class WooCommerce {
}
function WC() {
	return new WooCommerce();
}

/** Atributo que ya existe en el sitio (con su taxonomía registrada, como tras el `init` de WooCommerce). */
function woo_attribute( string $slug, string $label, string $type = 'select', string $orderby = 'menu_order', int $public = 0 ): int {
	$id                                = $GLOBALS['wc']['next_attr']++;
	$GLOBALS['wc']['attributes'][ $id ] = (object) array(
		'attribute_id'      => $id,
		'attribute_name'    => $slug,
		'attribute_label'   => $label,
		'attribute_type'    => $type,
		'attribute_orderby' => $orderby,
		'attribute_public'  => $public,
	);
	register_taxonomy( 'pa_' . $slug, array( 'product' ), array() );
	return $id;
}

/** Producto del sitio de un tipo (`simple`, `variable`), con metas. */
function woo_product( int $id, string $type, array $meta = array() ): int {
	kit_stub_post( array( 'ID' => $id, 'post_type' => 'product' ) );
	$GLOBALS['wc']['types'][ $id ] = $type;
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}

/** Variación del sitio con sus datos y sus metas sueltas (`[ clave, valor ]`). */
function woo_variation( int $id, int $parent, array $data, array $meta = array() ): int {
	$data = array_merge( WC_Product_Variation::DEFAULTS, $data, array( 'parent_id' => $parent, 'meta' => $meta ) );
	kit_stub_post(
		array(
			'ID'          => $id,
			'post_type'   => 'product_variation',
			'post_parent' => $parent,
			'post_status' => $data['status'],
			'menu_order'  => $data['menu_order'],
		)
	);
	if ( $data['image_id'] ) {
		update_post_meta( $id, '_thumbnail_id', (string) $data['image_id'] );
	}
	$GLOBALS['wc']['variations'][ $id ] = $data;
	if ( '' !== $data['sku'] ) {
		$GLOBALS['wc']['skus'][ $data['sku'] ] = $id;
	}
	return $id;
}

function wc_sanitize_taxonomy_name( $taxonomy ) {
	return urldecode( sanitize_title( urldecode( (string) $taxonomy ) ) );
}
function wc_get_attribute_slug_max_byte_length() {
	return 28;
}
function wc_attribute_taxonomy_name( $name ) {
	return 'pa_' . wc_sanitize_taxonomy_name( $name );
}
function wc_get_attribute_taxonomies() {
	return array_values( $GLOBALS['wc']['attributes'] );
}
function wc_attribute_taxonomy_id_by_name( $name ) {
	$name = (string) preg_replace( '/^pa_/', '', wc_sanitize_taxonomy_name( $name ) );
	foreach ( $GLOBALS['wc']['attributes'] as $id => $attribute ) {
		if ( $attribute->attribute_name === $name ) {
			return (int) $id;
		}
	}
	return 0;
}
function wc_get_attribute( $id ) {
	$attribute = $GLOBALS['wc']['attributes'][ (int) $id ] ?? null;
	if ( ! $attribute ) {
		return null;
	}
	return (object) array(
		'id'           => (int) $attribute->attribute_id,
		'name'         => $attribute->attribute_label,
		'slug'         => 'pa_' . $attribute->attribute_name,
		'type'         => $attribute->attribute_type,
		'order_by'     => $attribute->attribute_orderby,
		'has_archives' => (bool) $attribute->attribute_public,
	);
}
function wc_create_attribute( $args ) {
	$slug                       = (string) preg_replace( '/^pa_/', '', wc_sanitize_taxonomy_name( $args['slug'] ?? '' ) );
	$GLOBALS['wc']['created'][] = array(
		'slug'         => $slug,
		'type'         => $args['type'] ?? '',
		'has_archives' => (bool) ( $args['has_archives'] ?? false ),
	);
	if ( $GLOBALS['wc']['fail_create'] === $slug ) {
		return new WP_Error( 'cannot_create_attribute', 'No.' );
	}
	$types = $GLOBALS['wc']['visual'] ? array( 'select', 'wc-visual' ) : array( 'select' );
	$id    = $GLOBALS['wc']['next_attr']++;

	$GLOBALS['wc']['attributes'][ $id ] = (object) array(
		'attribute_id'      => $id,
		'attribute_name'    => $slug,
		'attribute_label'   => $args['name'],
		'attribute_type'    => in_array( $args['type'] ?? '', $types, true ) ? $args['type'] : 'select',
		'attribute_orderby' => in_array( $args['order_by'] ?? '', array( 'menu_order', 'name', 'name_num', 'id' ), true ) ? $args['order_by'] : 'menu_order',
		'attribute_public'  => isset( $args['has_archives'] ) ? (int) $args['has_archives'] : 0,
	);
	return $id;
}
function wc_delete_attribute( $id ) {
	$attribute = $GLOBALS['wc']['attributes'][ (int) $id ] ?? null;
	if ( ! $attribute ) {
		return false;
	}
	$taxonomy = 'pa_' . $attribute->attribute_name;
	foreach ( $GLOBALS['wp']['terms'] as $term_id => $term ) {
		if ( $term->taxonomy === $taxonomy ) {
			unset( $GLOBALS['wp']['terms'][ $term_id ] );
		}
	}
	unset( $GLOBALS['wc']['attributes'][ (int) $id ], $GLOBALS['wc']['registered'][ $taxonomy ] );
	$GLOBALS['wc']['deleted'][] = (int) $id;
	return true;
}
function wc_get_product( $id ) {
	$post = get_post( is_object( $id ) ? $id->ID : (int) $id );
	if ( ! $post ) {
		return false;
	}
	if ( 'product_variation' === $post->post_type ) {
		return new WC_Product_Variation( (int) $post->ID );
	}
	return 'product' === $post->post_type ? new Woo_Kit_Product( (int) $post->ID ) : false;
}
function wc_delete_product_transients( $id = 0 ) {
	$GLOBALS['wc']['transients'][] = (int) $id;
}
function wc_format_decimal( $value ) {
	return (string) $value;
}
function wc_stock_amount( $value ) {
	return (int) $value;
}
function wc_clean( $value ) {
	return sanitize_text_field( $value );
}

class WC_Data_Exception extends Exception {
}

/** Producto que no es una variación: tipo y `save()`. */
class Woo_Kit_Product {
	private $id;
	public function __construct( int $id ) {
		$this->id = $id;
	}
	public function get_id() {
		return $this->id;
	}
	public function is_type( $type ) {
		return ( $GLOBALS['wc']['types'][ $this->id ] ?? 'simple' ) === $type;
	}
	public function save() {
		$GLOBALS['wc']['saved'][] = $this->id;
		return $this->id;
	}
}

class WC_Product_Variable {
	public static function sync( $product, $save = true ) {
		$GLOBALS['wc']['synced'][] = is_object( $product ) ? (int) $product->get_id() : (int) $product;
	}
}

class WC_Data_Store {
	public static function load( $type ) {
		return new self();
	}
	public function has_callable( string $method ): bool {
		return 'refresh_product_lookup_table' === $method && $GLOBALS['wc']['direct'];
	}
	public function refresh_product_lookup_table( int $id ): void {
		$GLOBALS['wc']['lookup'][] = $id;
	}
}

/** Variación: propiedades con `get_*`/`set_*`, metas sueltas, SKU único, `save()` y `delete()`. */
class WC_Product_Variation {
	const DEFAULTS = array(
		'parent_id'         => 0,
		'status'            => 'publish',
		'menu_order'        => 0,
		'attributes'        => array(),
		'regular_price'     => '',
		'sale_price'        => '',
		'date_on_sale_from' => null,
		'date_on_sale_to'   => null,
		'sku'               => '',
		'manage_stock'      => false,
		'stock_quantity'    => null,
		'stock_status'      => 'instock',
		'backorders'        => 'no',
		'weight'            => '',
		'length'            => '',
		'width'             => '',
		'height'            => '',
		'shipping_class_id' => 0,
		'tax_class'         => 'parent',
		'description'       => '',
		'virtual'           => false,
		'downloadable'      => false,
		'downloads'         => array(),
		'image_id'          => 0,
		'meta'              => array(),
	);

	private $id   = 0;
	private $data = self::DEFAULTS;

	public function __construct( $id = 0 ) {
		if ( $id && isset( $GLOBALS['wc']['variations'][ (int) $id ] ) ) {
			$this->id   = (int) $id;
			$this->data = array_merge( self::DEFAULTS, $GLOBALS['wc']['variations'][ (int) $id ] );
		}
	}

	public function get_id() {
		return $this->id;
	}

	public function __call( $name, $args ) {
		if ( 0 === strpos( $name, 'get_' ) ) {
			$key   = substr( $name, 4 );
			$value = $this->data[ $key ] ?? null;
			if ( in_array( $key, array( 'date_on_sale_from', 'date_on_sale_to' ), true ) ) {
				return null === $value ? null : new DateTime( '@' . (int) $value );
			}
			return $value;
		}
		if ( 0 === strpos( $name, 'set_' ) ) {
			$this->data[ substr( $name, 4 ) ] = $args[0];
			return null;
		}
		throw new BadMethodCallException( $name );
	}

	public function set_props( $props ) {
		foreach ( $props as $key => $value ) {
			$this->{'set_' . $key}( $value );
		}
		return true;
	}

	public function set_sku( $sku ) {
		$sku = (string) $sku;
		if ( '' !== $sku && isset( $GLOBALS['wc']['skus'][ $sku ] ) && $GLOBALS['wc']['skus'][ $sku ] !== $this->id ) {
			throw new WC_Data_Exception( 'product_invalid_sku' );
		}
		$this->data['sku'] = $sku;
	}

	public function add_meta_data( $key, $value, $unique = false ) {
		$this->data['meta'][] = array( $key, $value );
	}

	public function get_meta_data() {
		return array_map(
			function ( $pair ) {
				return (object) array( 'key' => $pair[0], 'value' => $pair[1] );
			},
			$this->data['meta']
		);
	}

	public function save() {
		if ( ! $this->id ) {
			$this->id = kit_stub_post(
				array(
					'post_type'   => 'product_variation',
					'post_parent' => (int) $this->data['parent_id'],
					'post_status' => $this->data['status'],
					'menu_order'  => (int) $this->data['menu_order'],
				)
			);
		}
		$GLOBALS['wc']['variations'][ $this->id ] = $this->data;
		if ( '' !== $this->data['sku'] ) {
			$GLOBALS['wc']['skus'][ $this->data['sku'] ] = $this->id;
		}
		return $this->id;
	}

	public function delete( $force = false ) {
		unset( $GLOBALS['wp']['posts'][ $this->id ], $GLOBALS['wc']['variations'][ $this->id ] );
		return true;
	}
}
