<?php
namespace Digitalisimo\Elements\Woo\Swatches;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-data.php';

/**
 * Muestras en el formulario clásico de variaciones (V.6.3 a V.6.5).
 *
 * El filtro `woocommerce_dropdown_variation_attribute_options_html` (prioridad 20) devuelve el `<select>` de
 * WooCommerce **byte a byte igual**, dentro de un envoltorio, seguido de un grupo de radios nativos. Sólo actúa si el
 * atributo es global (`pa_*`), su tipo propio no es «Desplegable» y `$html` empieza por `<select` (si otro plugin ya lo
 * cambió, no se toca). El script oculta el `<select>` sólo cuando arranca: sin JS se ve el desplegable de siempre.
 *
 * - Los radios llevan `form="digi-sw-form-{uid}"`, un formulario vacío que el script crea fuera del de compra (un
 *   `<form>` no puede ir dentro de otro): siguen agrupados por `name` y no viajan con el pedido.
 * - Si el `<select>` lleva `required`, el primer radio también.
 * - La hoja y el script se encolan en `wp_enqueue_scripts` en la ficha de un variable con muestras (la hoja va en
 *   `<head>` y el cambio a los radios no mueve la ficha); fuera de la ficha (vista rápida, widgets «Añadir al carrito»),
 *   el filtro los encola al pintar.
 * - En un tema de bloques, el selector del bloque «Añadir al carrito con opciones» pinta sus propias muestras con las
 *   mismas metas y no pasa por este filtro: no hay doble pintado (V.6.5).
 */
final class Form {
	const FEATURE = 'swatches';

	/** @var int Contador de grupos de la petición. */
	private static $uid = 0;

	public static function init(): void {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'filter' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_product' ), 20 );
	}

	/** Encola la hoja y el script de las muestras (`digitalisimo-woo-swatches`, registrados por `Woo\Assets`). */
	public static function enqueue(): void {
		\Digitalisimo\Elements\Woo\Assets::enqueue( self::FEATURE );
	}

	/**
	 * En la ficha de un producto variable con algún atributo de variación con muestras, encola antes del cuerpo.
	 * Sin consultas: los atributos salen del producto ya cargado y la configuración de una opción con carga automática.
	 */
	public static function enqueue_product(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( $product instanceof \WC_Product && self::product_uses_swatches( $product ) ) {
			self::enqueue();
		}
	}

	/** El producto es variable y algún atributo global de variación tiene un tipo distinto de «Desplegable». */
	public static function product_uses_swatches( \WC_Product $product ): bool {
		if ( ! $product->is_type( 'variable' ) || ! Data::all() ) {
			return false;
		}
		foreach ( $product->get_attributes() as $name => $attribute ) {
			if ( is_object( $attribute ) && method_exists( $attribute, 'get_variation' ) && $attribute->get_variation() && $attribute->is_taxonomy() && 'select' !== Data::attribute( (string) $attribute->get_name() )['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filtro del desplegable de WooCommerce.
	 *
	 * @param string $html `<select>` de WooCommerce.
	 * @param array  $args Argumentos de `wc_dropdown_variation_attribute_options()`.
	 */
	public static function filter( $html, $args = array() ) {
		if ( ! is_string( $html ) || 0 !== strpos( $html, '<select' ) || ! is_array( $args ) ) {
			return $html;
		}
		$taxonomy = isset( $args['attribute'] ) && is_string( $args['attribute'] ) ? $args['attribute'] : '';
		$config   = Data::attribute( $taxonomy );
		$product  = isset( $args['product'] ) ? $args['product'] : null;
		if ( 'select' === $config['type'] || ! $product instanceof \WC_Product || ! taxonomy_exists( $taxonomy ) ) {
			return $html;
		}
		$items = self::items( $product, $taxonomy, $args );
		if ( ! $items ) {
			return $html;
		}
		self::enqueue();
		return self::wrap( $html, $taxonomy, $config, $items, $product, ! empty( $args['required'] ) );
	}

	/**
	 * Términos del atributo que ofrece el desplegable, en su orden: `{ slug, name, image, color, checked }`.
	 *
	 * @param \WC_Product $product  Producto.
	 * @param string      $taxonomy Taxonomía.
	 * @param array       $args     Argumentos del desplegable.
	 */
	private static function items( \WC_Product $product, string $taxonomy, array $args ): array {
		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		if ( ! $options && method_exists( $product, 'get_variation_attributes' ) ) {
			$attributes = $product->get_variation_attributes();
			$options    = isset( $attributes[ $taxonomy ] ) ? (array) $attributes[ $taxonomy ] : array();
		}
		if ( ! $options ) {
			return array();
		}
		$selected = isset( $args['selected'] ) && is_scalar( $args['selected'] ) ? sanitize_title( (string) $args['selected'] ) : '';
		$terms    = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'all' ) );
		$terms    = is_array( $terms ) ? array_values( array_filter( $terms, function ( $term ) use ( $options ) {
			return $term instanceof \WP_Term && in_array( $term->slug, $options, true );
		} ) ) : array();
		Data::prime( wp_list_pluck( $terms, 'term_id' ) );
		$items = array();
		foreach ( $terms as $term ) {
			$data    = Data::term( $term );
			$items[] = array(
				'slug'    => (string) $term->slug,
				'name'    => (string) apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $taxonomy, $product ),
				'image'   => $data['image'],
				'color'   => $data['color'],
				'checked' => '' !== $selected && $selected === $term->slug,
			);
		}
		return $items;
	}

	/**
	 * Envoltorio: el `<select>` intacto y el grupo de radios.
	 *
	 * @param string      $html     `<select>` de WooCommerce.
	 * @param string      $taxonomy Taxonomía.
	 * @param array       $config   `{ type, shape, size }`.
	 * @param array       $items    Términos.
	 * @param \WC_Product $product  Producto.
	 * @param bool        $required El `<select>` es obligatorio.
	 */
	public static function wrap( string $html, string $taxonomy, array $config, array $items, \WC_Product $product, bool $required = false ): string {
		$uid   = self::uid( $product->get_id() );
		$type  = in_array( $config['type'], array( 'visual', 'label' ), true ) ? $config['type'] : 'label';
		$shape = in_array( $config['shape'], Data::SHAPES, true ) ? $config['shape'] : 'circle';
		$size  = max( Data::SIZE_MIN, min( Data::SIZE_MAX, (int) $config['size'] ) );
		$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy, $product ) : $taxonomy;
		$out   = '<div class="digi-swatch-field" data-digi-swatches="' . esc_attr( $taxonomy ) . '">' . $html;
		$out  .= '<div class="digi-swatches digi-swatches--' . esc_attr( $shape ) . ' digi-swatches--' . esc_attr( $type ) . '" role="radiogroup" aria-label="' . esc_attr( wp_strip_all_tags( (string) $label ) ) . '" style="--digi-swatch-size-attr:' . (int) $size . 'px">';
		foreach ( array_values( $items ) as $index => $item ) {
			$out .= self::radio( $item, $type, $uid, $required && 0 === $index );
		}
		return $out . '</div></div>';
	}

	/** Una opción: radio nativo con la cara (imagen o color) y el nombre del término como nombre accesible. */
	private static function radio( array $item, string $type, string $uid, bool $required ): string {
		$face  = '';
		$style = '';
		if ( 'visual' === $type && $item['image'] ) {
			$face = self::image( (int) $item['image'] );
		} elseif ( 'visual' === $type && '' !== $item['color'] ) {
			$style = ' style="--digi-swatch-color:' . esc_attr( $item['color'] ) . '"';
		}
		$visual = 'visual' === $type && ( '' !== $face || '' !== $style );
		$html   = '<label class="digi-swatch' . ( $visual ? '' : ' digi-swatch--text' ) . '"' . $style . '>';
		$html  .= '<input type="radio" name="digi_sw_' . esc_attr( $uid ) . '" form="digi-sw-form-' . esc_attr( $uid ) . '" value="' . esc_attr( $item['slug'] ) . '"' . ( $item['checked'] ? ' checked' : '' ) . ( $required ? ' required' : '' ) . '>';
		if ( $visual ) {
			$html .= '<span class="digi-swatch__face" aria-hidden="true">' . $face . '</span>';
		}
		$html .= '<span class="digi-swatch__label' . ( $visual ? ' screen-reader-text' : '' ) . '">' . esc_html( $item['name'] ) . '</span>';
		return $html . '<span class="digi-swatch__na screen-reader-text"></span></label>';
	}

	/** Imagen de la muestra por el generador único de imágenes, decorativa (el nombre ya está en el texto). */
	public static function image( int $id ): string {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Image_Markup' ) ) {
			require_once dirname( __DIR__, 2 ) . '/digitalisimo-widgets/class-image-markup.php';
		}
		return \Digitalisimo\Elements\Image_Markup::img( array( 'id' => $id ), array( 'size' => 'thumbnail', 'decorative' => true, 'class' => 'digi-swatch__img', 'sizes' => '64px' ) );
	}

	/**
	 * Identificador del grupo, único en la página: en la ruta de la vista rápida lleva otro prefijo para no coincidir
	 * con un formulario de la página. El script lo vuelve a asignar al arrancar.
	 *
	 * @param int $product_id Producto.
	 */
	private static function uid( int $product_id ): string {
		self::$uid++;
		$rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		return ( $rest ? 'q' : 'p' ) . $product_id . '-' . self::$uid;
	}
}
