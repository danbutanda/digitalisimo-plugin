<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-asset-list.php';

/**
 * Contenido «Diseño propio» de la vista rápida (V.5.2 y §5.2 a de la v1) y el entorno de producto común a los dos
 * modos.
 *
 * - Marcado que busca `wc_variations_image_update()` de WooCommerce para cambiar la imagen con la variación:
 *   `.product > .images > .woocommerce-product-gallery__image > img.wp-post-image`, con el formulario dentro del mismo
 *   `.product`. La imagen sale de `Image_Markup` (srcset, ALT de Medios y la carga que decida WordPress).
 * - La compra es el formulario nativo de WooCommerce (`woocommerce_template_single_add_to_cart()`), con la acción
 *   apuntando a la página de origen y el campo oculto `digi_qv=1` (V.5.5), ambos sólo durante el render.
 * - No dispara `woocommerce_single_product_summary`: sin JSON-LD duplicado ni otras salidas del resumen (V.13).
 * - El título visible lleva `data-digi-qv-title`: el script le pasa el `id` del título del diálogo, así hay una sola
 *   etiqueta de encabezado (D31).
 */
final class Render {
	/** Partes de «Diseño propio» y las que se pintan sin elegir (la parte `quickview` de Productos usa éstas). */
	const PARTS         = array( 'image', 'gallery', 'title', 'rating', 'price', 'excerpt', 'cart', 'meta', 'link' );
	const DEFAULT_PARTS = array( 'image', 'gallery', 'title', 'rating', 'price', 'excerpt', 'cart', 'link' );
	const TITLE_TAGS    = array( 'h2', 'h3', 'h4' );
	const IMAGE_SIZES   = array( 'woocommerce_single', 'large', 'medium_large', 'medium', 'full' );
	const LINK_TEXT     = 'Ver la ficha completa';
	const MAX_THUMBS    = 8;

	/** `sizes` de la imagen principal: la columna del diálogo. */
	const SIZES = '(max-width: 767px) 92vw, 28rem';

	/**
	 * Ajustes del contenido a partir del elemento hallado por la ruta, con los valores por defecto de los controles
	 * (el núcleo no guarda un ajuste igual a su valor por defecto). La parte `quickview` de Productos usa siempre
	 * «Diseño propio» con las partes por defecto.
	 *
	 * @param array $element Elemento (`widgetType`, `settings`).
	 * @return array{mode: string, template: int, parts: string[], title_tag: string, image_size: string, link_text: string}
	 */
	public static function settings( array $element ): array {
		$own = 'digitalisimo-woo-quick-view' === ( isset( $element['widgetType'] ) ? $element['widgetType'] : '' );
		$s   = $own && isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$parts = isset( $s['qv_parts'] ) && is_array( $s['qv_parts'] ) ? array_values( array_intersect( self::PARTS, array_map( 'strval', $s['qv_parts'] ) ) ) : self::DEFAULT_PARTS;
		$tag   = isset( $s['qv_title_tag'] ) && in_array( $s['qv_title_tag'], self::TITLE_TAGS, true ) ? (string) $s['qv_title_tag'] : 'h2';
		$size  = isset( $s['qv_image_size'] ) && in_array( $s['qv_image_size'], self::IMAGE_SIZES, true ) ? (string) $s['qv_image_size'] : 'woocommerce_single';
		$link  = isset( $s['qv_link_text'] ) && is_string( $s['qv_link_text'] ) && '' !== trim( $s['qv_link_text'] ) ? trim( wp_strip_all_tags( $s['qv_link_text'] ) ) : self::LINK_TEXT;
		$mode  = isset( $s['content_mode'] ) && 'template' === $s['content_mode'] ? 'template' : 'own';
		$tpl   = 'template' === $mode && isset( $s['digitalisimo_template_id'] ) && is_numeric( $s['digitalisimo_template_id'] ) ? (int) $s['digitalisimo_template_id'] : 0;
		return array( 'mode' => $mode, 'template' => $tpl, 'parts' => $parts, 'title_tag' => $tag, 'image_size' => $size, 'link_text' => $link );
	}

	/**
	 * Pinta `$render` con el producto como contenido actual: `global $product`, `setup_postdata()` y, en el modo
	 * plantilla, una consulta simulada del producto (así `is_product()` y los widgets de producto lo ven). La acción del
	 * formulario de compra apunta a `$return` y lleva `digi_qv=1`; todo vuelve como estaba al terminar.
	 *
	 * @param \WC_Product $product Producto.
	 * @param string      $return  Página de origen, ya validada.
	 * @param callable    $render  Pinta con `echo` o devuelve el HTML.
	 * @param bool        $query   Simular la consulta principal.
	 */
	public static function with_product( \WC_Product $product, string $return, callable $render, bool $query = false ): string {
		global $post, $wp_query;
		$saved_post    = isset( $post ) ? $post : null;
		$saved_query   = isset( $wp_query ) ? $wp_query : null;
		$had_product   = array_key_exists( 'product', $GLOBALS );
		$saved_product = $had_product ? $GLOBALS['product'] : null;
		$id            = (int) $product->get_id();
		if ( $query && class_exists( '\\WP_Query' ) ) {
			$wp_query = new \WP_Query( array( 'p' => $id, 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura al terminar.
			if ( $wp_query->have_posts() ) {
				$wp_query->the_post();
			}
		} else {
			$post = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura al terminar.
			if ( $post ) {
				setup_postdata( $post );
			}
		}
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- se restaura al terminar.
		$action = static function () use ( $return ) {
			return $return;
		};
		$field  = static function () use ( $product ) {
			if ( ! $product->is_type( 'external' ) ) {
				echo '<input type="hidden" name="digi_qv" value="1">';
			}
		};
		add_filter( 'woocommerce_add_to_cart_form_action', $action, PHP_INT_MAX );
		add_action( 'woocommerce_after_add_to_cart_button', $field, 20 );
		$level = ob_get_level();
		$html  = '';
		ob_start();
		try {
			$result = $render();
			$html   = (string) ob_get_clean() . ( is_string( $result ) ? $result : '' );
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'woocommerce_add_to_cart_form_action', $action, PHP_INT_MAX );
			remove_action( 'woocommerce_after_add_to_cart_button', $field, 20 );
			$wp_query = $saved_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post     = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $post instanceof \WP_Post ) {
				setup_postdata( $post );
			}
			if ( $had_product ) {
				$GLOBALS['product'] = $saved_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			} else {
				unset( $GLOBALS['product'] );
			}
		}
		return $html;
	}

	/**
	 * Respuesta de «Diseño propio». `assets` sólo trae los recursos propios que el render encoló (las muestras del
	 * formulario); los scripts de variaciones los pone la configuración del pie de la página (V.5.3).
	 *
	 * @param \WC_Product $product  Producto.
	 * @param array       $settings `settings()`.
	 * @param string      $return   Página de origen.
	 */
	public static function respond( \WC_Product $product, array $settings, string $return ): array {
		$styles  = function_exists( 'wp_styles' ) ? wp_styles() : null;
		$scripts = function_exists( 'wp_scripts' ) ? wp_scripts() : null;
		$before  = array( $styles ? $styles->queue : array(), $scripts ? $scripts->queue : array() );
		$html    = self::with_product(
			$product,
			$return,
			static function () use ( $product, $settings ) {
				return self::markup( $product, $settings );
			}
		);
		$variable = $product->is_type( 'variable' );
		$own      = static function ( $handle ) {
			return 0 === strpos( (string) $handle, 'digitalisimo-' );
		};
		$assets = array( 'styles' => array(), 'scripts' => array(), 'params' => array() );
		if ( $styles && $scripts ) {
			$new_styles  = array_values( array_filter( array_diff( $styles->queue, $before[0] ), $own ) );
			$new_scripts = array_values( array_filter( array_diff( $scripts->queue, $before[1] ), $own ) );
			$style_list  = Asset_List::styles( $new_styles, $styles );
			$script_list = Asset_List::scripts( $new_scripts, $scripts, array(), false );
			$assets      = array(
				'styles'  => null === $style_list ? array() : $style_list,
				'scripts' => null === $script_list ? array() : $script_list['scripts'],
				'params'  => null === $script_list ? array() : $script_list['params'],
			);
		}
		return array(
			'mode'             => 'own',
			'html'             => $html,
			'title'            => wp_strip_all_tags( (string) $product->get_name() ),
			'product'          => (int) $product->get_id(),
			'variable'         => $variable,
			'assets'           => $assets,
			'templates'        => $variable ? self::variation_templates() : '',
			'gallery_defaults' => $variable ? self::gallery_defaults( $product ) : '',
			'footer_html'      => '',
		);
	}

	/** Las dos plantillas `<script type="text/template">` del formulario de variaciones (no se ejecutan). */
	public static function variation_templates(): string {
		if ( ! function_exists( 'wc_get_template' ) ) {
			return '';
		}
		ob_start();
		wc_get_template( 'single-product/add-to-cart/variation.php' );
		return trim( (string) ob_get_clean() );
	}

	/** Estado inicial de la galería para `wc_variation_gallery_defaults[producto]`, si la función existe (10.8+). */
	public static function gallery_defaults( \WC_Product $product ): string {
		return function_exists( 'wc_get_product_gallery_html' ) ? (string) wc_get_product_gallery_html( $product ) : '';
	}

	/**
	 * Marcado de «Diseño propio».
	 *
	 * @param \WC_Product $product  Producto.
	 * @param array       $settings `settings()`.
	 */
	public static function markup( \WC_Product $product, array $settings ): string {
		$parts = $settings['parts'];
		$name  = wp_strip_all_tags( (string) $product->get_name() );
		$media = in_array( 'image', $parts, true );
		$html  = '<div class="digi-qv__product product type-product product-type-' . esc_attr( $product->get_type() ) . ( $media ? '' : ' digi-qv__product--single' ) . '">';
		if ( $media ) {
			$html .= self::media( $product, in_array( 'gallery', $parts, true ), $settings['image_size'], $name );
		}
		$html .= '<div class="digi-qv__summary summary entry-summary">';
		foreach ( $parts as $part ) {
			$html .= self::part( $part, $product, $settings, $name );
		}
		return $html . '</div></div>';
	}

	private static function part( string $part, \WC_Product $product, array $settings, string $name ): string {
		switch ( $part ) {
			case 'title':
				$tag = $settings['title_tag'];
				return '<' . $tag . ' class="digi-qv__title product_title" data-digi-qv-title>' . esc_html( $name ) . '</' . $tag . '>';
			case 'rating':
				$enabled = ! function_exists( 'wc_review_ratings_enabled' ) || wc_review_ratings_enabled();
				$count   = (int) $product->get_rating_count();
				return $enabled && $count > 0 && function_exists( 'wc_get_rating_html' ) ? '<div class="woocommerce-product-rating">' . wc_get_rating_html( $product->get_average_rating(), $count ) . '</div>' : '';
			case 'price':
				$price = (string) $product->get_price_html();
				return '' !== $price ? '<p class="price">' . $price . '</p>' : '';
			case 'excerpt':
				$excerpt = (string) apply_filters( 'woocommerce_short_description', (string) get_post_field( 'post_excerpt', $product->get_id() ) );
				return '' !== trim( wp_strip_all_tags( $excerpt ) ) ? '<div class="digi-qv__excerpt woocommerce-product-details__short-description">' . wp_kses_post( $excerpt ) . '</div>' : '';
			case 'cart':
				if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
					return '';
				}
				ob_start();
				woocommerce_template_single_add_to_cart();
				return (string) ob_get_clean();
			case 'meta':
				return self::meta( $product );
			case 'link':
				return '<p class="digi-qv__more"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $settings['link_text'] ) . '<span class="screen-reader-text"> de ' . esc_html( $name ) . '</span></a></p>';
		}
		return '';
	}

	/** Referencia (con las clases que actualiza el script de variaciones) y categorías. */
	private static function meta( \WC_Product $product ): string {
		$html = '';
		$sku  = (string) $product->get_sku();
		if ( '' !== $sku && ( ! function_exists( 'wc_product_sku_enabled' ) || wc_product_sku_enabled() ) ) {
			$html .= '<span class="sku_wrapper">Referencia: <span class="sku">' . esc_html( $sku ) . '</span></span> ';
		}
		$categories = function_exists( 'wc_get_product_category_list' ) ? (string) wc_get_product_category_list( $product->get_id(), ', ' ) : '';
		if ( '' !== $categories ) {
			$html .= '<span class="posted_in">Categorías: ' . wp_kses_post( $categories ) . '</span>';
		}
		return '' !== $html ? '<div class="product_meta">' . $html . '</div>' : '';
	}

	/**
	 * Imagen principal y, con «Galería», botones de miniatura (`aria-pressed`, «Ver imagen N de M») que cambian la
	 * principal; sin zoom ni lightbox.
	 */
	private static function media( \WC_Product $product, bool $gallery, string $size, string $name ): string {
		if ( ! class_exists( '\\Digitalisimo\\Elements\\Image_Markup' ) ) {
			require_once dirname( __DIR__, 2 ) . '/digitalisimo-widgets/class-image-markup.php';
		}
		$markup = '\\Digitalisimo\\Elements\\Image_Markup';
		$main   = (int) $product->get_image_id();
		$img    = $main ? $markup::img( array( 'id' => $main ), array( 'size' => $size, 'class' => 'wp-post-image', 'alt' => $name, 'sizes' => self::SIZES ) ) : '';
		$wrap   = 'woocommerce-product-gallery__image';
		if ( '' === $img ) {
			$src  = function_exists( 'wc_placeholder_img_src' ) ? (string) wc_placeholder_img_src( $size ) : '';
			$img  = '' !== $src ? $markup::img( array( 'url' => $src ), array( 'class' => 'wp-post-image', 'alt_exact' => '' ) ) : '';
			$wrap = 'woocommerce-product-gallery__image--placeholder';
		}
		$html = '<div class="digi-qv__media images"><div class="' . $wrap . '">' . $img . '</div>';
		$ids  = $main ? array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( $main ), (array) $product->get_gallery_image_ids() ) ) ) ) ) : array();
		$ids  = array_slice( $ids, 0, self::MAX_THUMBS );
		if ( $gallery && count( $ids ) > 1 ) {
			$total  = count( $ids );
			$thumbs = '';
			foreach ( $ids as $index => $id ) {
				$src = wp_get_attachment_image_src( $id, $size );
				if ( ! is_array( $src ) || empty( $src[0] ) ) {
					continue;
				}
				$thumbs .= '<button type="button" class="digi-qv__thumb" aria-pressed="' . ( 0 === $index ? 'true' : 'false' ) . '"'
					. ' data-src="' . esc_url( $src[0] ) . '" data-srcset="' . esc_attr( (string) wp_get_attachment_image_srcset( $id, $size ) ) . '"'
					. ' data-width="' . (int) $src[1] . '" data-height="' . (int) $src[2] . '" data-alt="' . esc_attr( $markup::alt( array( 'id' => $id ), $name ) ) . '">'
					. $markup::img( array( 'id' => $id ), array( 'size' => 'woocommerce_gallery_thumbnail', 'decorative' => true ) )
					. '<span class="screen-reader-text">Ver imagen ' . ( $index + 1 ) . ' de ' . $total . '</span></button>';
			}
			if ( '' !== $thumbs ) {
				$html .= '<div class="digi-qv__thumbs" role="group" aria-label="' . esc_attr( 'Imágenes de ' . $name ) . '">' . $thumbs . '</div>';
			}
		}
		return $html . '</div>';
	}
}
