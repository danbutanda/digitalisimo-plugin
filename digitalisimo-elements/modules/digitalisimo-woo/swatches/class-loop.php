<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-data.php';
require_once __DIR__ . '/class-form.php';

/**
 * Muestras en un bucle de productos (V.6.3): enlaces a la ficha que preseleccionan la variación con
 * `attribute_pa_*` (WooCommerce lo lee de `$_REQUEST`), con `rel="nofollow"` como los filtros de WooCommerce (V.13).
 *
 * - Sólo productos variables y atributos globales marcados «Usado para variaciones» con un tipo distinto de
 *   «Desplegable». Una combinación agotada no se oculta aquí: la ficha la deshabilita.
 * - **0 consultas por producto:** los términos de cada atributo se leen una vez por página (con el orden de
 *   WooCommerce, y sus metas y los adjuntos de sus imágenes precargados); de cada producto sólo se usan los ID de
 *   términos de su atributo, que vienen con el producto. «Cambiar la imagen al pasar el cursor» lee además las
 *   variaciones de cada producto variable (declarado en el presupuesto).
 * - Un *slug* que ya no existe (atributo renombrado o borrado) no pinta nada: falla cerrado.
 *
 * Es el renderizador que usan el widget `digitalisimo-woo-swatches` y la parte `swatches` de Productos (frente G).
 */
final class Loop {
	/** @var array<string,\WP_Term[]> Términos de cada atributo de la página, en el orden de WooCommerce. */
	private static $terms = array();

	/**
	 * @param \WC_Product $product Producto.
	 * @param array       $args    attribute ('' = el primero con muestras), max (0 = todos; 5 por defecto),
	 *                             shape ('' = la del atributo), hover (bool), class.
	 * @return string '' si no hay nada que pintar.
	 */
	public static function markup( \WC_Product $product, array $args = array() ): string {
		if ( ! $product->is_type( 'variable' ) ) {
			return '';
		}
		$taxonomy = self::taxonomy( $product, isset( $args['attribute'] ) ? (string) $args['attribute'] : '' );
		if ( '' === $taxonomy ) {
			return '';
		}
		$config = Data::attribute( $taxonomy );
		$ids    = self::option_ids( $product, $taxonomy );
		$terms  = array_values( array_filter( self::terms( $taxonomy ), function ( $term ) use ( $ids ) {
			return in_array( (int) $term->term_id, $ids, true );
		} ) );
		if ( ! $terms ) {
			return '';
		}
		$max     = isset( $args['max'] ) ? max( 0, (int) $args['max'] ) : 5;
		$shape   = isset( $args['shape'] ) && in_array( $args['shape'], Data::SHAPES, true ) ? (string) $args['shape'] : $config['shape'];
		$hover   = ! empty( $args['hover'] ) ? self::variation_images( $product, $taxonomy ) : array();
		$name    = wp_strip_all_tags( (string) $product->get_name() );
		$label   = function_exists( 'wc_attribute_label' ) ? wp_strip_all_tags( (string) wc_attribute_label( $taxonomy, $product ) ) : $taxonomy;
		$link    = (string) $product->get_permalink();
		$visible = $max ? array_slice( $terms, 0, $max ) : $terms;
		$class   = 'digi-swatches digi-swatches--links digi-swatches--' . $shape . ' digi-swatches--' . $config['type'] . ( empty( $args['class'] ) ? '' : ' ' . sanitize_html_class( (string) $args['class'] ) );
		$html    = '<ul class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( $label . ': opciones' ) . '" style="--digi-swatch-size-attr:' . (int) $config['size'] . 'px"' . ( $hover ? ' data-digi-sw-hover' : '' ) . '>';
		foreach ( $visible as $term ) {
			$html .= self::item( $term, $config['type'], add_query_arg( 'attribute_' . sanitize_title( $taxonomy ), $term->slug, $link ), $name, isset( $hover[ $term->slug ] ) ? $hover[ $term->slug ] : '' );
		}
		$more = count( $terms ) - count( $visible );
		if ( $more > 0 ) {
			$html .= '<li><a class="digi-swatch digi-swatch--more digi-swatch--text" href="' . esc_url( $link ) . '" rel="nofollow">+' . (int) $more . '<span class="screen-reader-text"> opciones más de ' . esc_html( $name ) . '</span></a></li>';
		}
		return $html . '</ul>';
	}

	/** Un enlace: la cara (imagen o color) o el nombre visible, y «{producto}, {término}» como nombre accesible. */
	private static function item( \WP_Term $term, string $type, string $url, string $product, string $hover ): string {
		$data  = Data::term( $term );
		$face  = '';
		$style = '';
		if ( 'visual' === $type && $data['image'] ) {
			$face = Form::image( (int) $data['image'] );
		} elseif ( 'visual' === $type && '' !== $data['color'] ) {
			$style = ' style="--digi-swatch-color:' . esc_attr( $data['color'] ) . '"';
		}
		$visual = '' !== $face || '' !== $style;
		$html   = '<li><a class="digi-swatch' . ( $visual ? '' : ' digi-swatch--text' ) . '" href="' . esc_url( $url ) . '" rel="nofollow"' . $style . ( '' !== $hover ? ' data-digi-sw-img="' . esc_attr( $hover ) . '"' : '' ) . '>';
		$html  .= $visual ? '<span class="digi-swatch__face" aria-hidden="true">' . $face . '</span>' : '<span class="digi-swatch__label" aria-hidden="true">' . esc_html( $data['label'] ) . '</span>';
		return $html . '<span class="screen-reader-text">' . esc_html( $product . ', ' . $data['label'] ) . '</span></a></li>';
	}

	/**
	 * Atributo que se pinta: el pedido si es de variación y tiene muestras, o el primero del producto que las tenga.
	 *
	 * @param \WC_Product $product   Producto.
	 * @param string      $requested `pa_*` o ''.
	 */
	public static function taxonomy( \WC_Product $product, string $requested = '' ): string {
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! $attribute->is_taxonomy() || ! $attribute->get_variation() ) {
				continue;
			}
			$name = (string) $attribute->get_name();
			if ( ( '' === $requested || $requested === $name ) && 'select' !== Data::attribute( $name )['type'] && taxonomy_exists( $name ) ) {
				return $name;
			}
		}
		return '';
	}

	/** IDs de los términos que el producto usa en el atributo (vienen con el producto, sin consulta). */
	private static function option_ids( \WC_Product $product, string $taxonomy ): array {
		$attributes = $product->get_attributes();
		$attribute  = isset( $attributes[ $taxonomy ] ) ? $attributes[ $taxonomy ] : null;
		return is_object( $attribute ) ? array_map( 'intval', (array) $attribute->get_options() ) : array();
	}

	/** Términos del atributo, una vez por página, con el orden de WooCommerce y sus metas y adjuntos precargados. */
	private static function terms( string $taxonomy ): array {
		if ( ! isset( self::$terms[ $taxonomy ] ) ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			$terms = is_array( $terms ) ? array_values( array_filter( $terms, function ( $term ) {
				return $term instanceof \WP_Term;
			} ) ) : array();
			Data::prime( wp_list_pluck( $terms, 'term_id' ) );
			self::$terms[ $taxonomy ] = $terms;
		}
		return self::$terms[ $taxonomy ];
	}

	/**
	 * Imagen de la primera variación de cada término, como JSON `{ src, srcset, sizes }` para el cambio al pasar el
	 * cursor. Una lectura por variación (objetos en caché de WooCommerce).
	 */
	private static function variation_images( \WC_Product $product, string $taxonomy ): array {
		$key    = 'attribute_' . sanitize_title( $taxonomy );
		$images = array();
		foreach ( $product->get_children() as $child ) {
			$variation = wc_get_product( $child );
			if ( ! $variation instanceof \WC_Product || ! $variation->get_image_id() ) {
				continue;
			}
			$attributes = $variation->get_variation_attributes();
			$slug       = isset( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
			if ( '' === $slug || isset( $images[ $slug ] ) ) {
				continue;
			}
			$id  = (int) $variation->get_image_id();
			$src = wp_get_attachment_image_src( $id, 'woocommerce_thumbnail' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$images[ $slug ] = (string) wp_json_encode( array( 'src' => $src[0], 'srcset' => (string) wp_get_attachment_image_srcset( $id, 'woocommerce_thumbnail' ), 'sizes' => (string) wp_get_attachment_image_sizes( $id, 'woocommerce_thumbnail' ) ) );
			}
		}
		return $images;
	}

	/** Vacía la memoria de la petición (pruebas). */
	public static function flush(): void {
		self::$terms = array();
	}
}
