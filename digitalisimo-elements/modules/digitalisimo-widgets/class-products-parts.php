<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Partes visibles del producto y número exacto de productos en el widget Productos de WooCommerce.
 *
 * Añade una sección «Partes del producto» (imagen, título y su etiqueta, extracto o resumen,
 * categorías, valoración, precio, botón de compra, «Leer más», insignia de oferta) y un límite de
 * productos independiente de filas por columnas. Durante el render de ese widget ajusta los ganchos
 * del bucle de WooCommerce y los deja como estaban al terminar; un widget que no usa estas opciones
 * se pinta exactamente igual que antes.
 */
final class Products_Parts {
	const PARTS_KEY = 'digitalisimo_wc_parts';
	const PARTS     = array(
		'image'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 ),
		'title'  => array( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 ),
		'rating' => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 ),
		'price'  => array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 ),
		'cart'   => array( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 ),
		'badge'  => array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 ),
	);
	const TITLE_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	private static $state = array();

	public static function init() {
		add_action( 'elementor/element/woocommerce-products/section_content/after_section_end', array( __CLASS__, 'register_controls' ) );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/widget/after_render', array( __CLASS__, 'after_render' ) );
	}

	public static function register_controls( $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_wc_parts_section', array( 'label' => 'Partes del producto' ) );
		$widget->add_control( 'digitalisimo_wc_custom', array( 'label' => 'Elegir qué partes mostrar', 'type' => $c::SWITCHER, 'default' => '' ) );
		$widget->add_control( self::PARTS_KEY, array(
			'label'     => 'Partes',
			'type'      => $c::SELECT2,
			'multiple'  => true,
			'default'   => array( 'image', 'title', 'rating', 'price', 'cart', 'badge' ),
			'options'   => array( 'image' => 'Imagen', 'title' => 'Título', 'excerpt' => 'Extracto del contenido', 'summary' => 'Resumen (extracto manual o contenido)', 'category' => 'Categorías', 'rating' => 'Valoración', 'price' => 'Precio', 'cart' => 'Botón de compra', 'readmore' => 'Leer más', 'badge' => 'Insignia de oferta' ),
			'condition' => array( 'digitalisimo_wc_custom' => 'yes' ),
		) );
		$widget->add_control( 'digitalisimo_wc_title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array_combine( self::TITLE_TAGS, self::TITLE_TAGS ), 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_wc_excerpt_length', array( 'label' => 'Palabras del extracto', 'type' => $c::NUMBER, 'default' => 10, 'min' => 1, 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_wc_readmore_text', array( 'label' => 'Texto de «Leer más»', 'type' => $c::TEXT, 'default' => 'Read More', 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_products_limit', array( 'label' => 'Número de productos', 'type' => $c::NUMBER, 'min' => 1, 'description' => 'Vacío: filas por columnas.' ) );
		$widget->end_controls_section();
	}

	private static function applies( $element ) {
		return $element instanceof \ElementorPro\Modules\Woocommerce\Widgets\Products;
	}

	public static function before_render( $element ) {
		if ( ! self::applies( $element ) ) {
			return;
		}
		$s     = (array) $element->get_settings_for_display();
		$limit = (int) ( $s['digitalisimo_products_limit'] ?? 0 );
		$state = array( 'removed' => array(), 'added' => array(), 'limit' => null );
		if ( $limit > 0 ) {
			$state['limit'] = static function ( $args ) use ( $limit ) {
				$args['posts_per_page'] = $limit;
				return $args;
			};
			add_filter( 'woocommerce_shortcode_products_query', $state['limit'], 20 );
		}
		if ( 'yes' === ( $s['digitalisimo_wc_custom'] ?? '' ) ) {
			$parts = array_map( 'strval', (array) ( $s[ self::PARTS_KEY ] ?? array() ) );
			$tag   = in_array( $s['digitalisimo_wc_title_tag'] ?? 'h2', self::TITLE_TAGS, true ) ? (string) $s['digitalisimo_wc_title_tag'] : 'h2';
			foreach ( self::PARTS as $part => $hook ) {
				$custom_title = 'title' === $part && 'h2' !== $tag;
				if ( ( ! in_array( $part, $parts, true ) || $custom_title ) && false !== has_action( $hook[0], $hook[1] ) ) {
					remove_action( $hook[0], $hook[1], $hook[2] );
					$state['removed'][] = $hook;
				}
			}
			if ( in_array( 'title', $parts, true ) && 'h2' !== $tag ) {
				$state['added'][] = array( 'woocommerce_shop_loop_item_title', static function () use ( $tag ) {
					echo '<' . $tag . ' class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . esc_html( get_the_title() ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de una lista cerrada.
				}, 10 );
			}
			if ( in_array( 'category', $parts, true ) ) {
				$state['added'][] = array( 'woocommerce_after_shop_loop_item_title', static function () {
					$list = wc_get_product_category_list( get_the_ID(), ', ' );
					echo $list ? '<div class="digi-product__categories">' . wp_kses_post( $list ) . '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post.
				}, 4 );
			}
			// «excerpt»: el contenido recortado; «summary»: el extracto manual si existe o el contenido recortado con «…».
			$summary = in_array( 'summary', $parts, true );
			if ( $summary || in_array( 'excerpt', $parts, true ) ) {
				$words            = max( 1, (int) ( $s['digitalisimo_wc_excerpt_length'] ?? 10 ) );
				$state['added'][] = array( 'woocommerce_after_shop_loop_item_title', static function () use ( $words, $summary ) {
					$text = $summary && has_excerpt() ? wp_strip_all_tags( (string) get_the_excerpt() ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) get_the_content() ) ), $words, $summary ? '…' : '' );
					echo '' !== trim( $text ) ? '<div class="digi-product__excerpt">' . esc_html( trim( $text ) ) . '</div>' : '';
				}, 6 );
			}
			if ( in_array( 'readmore', $parts, true ) ) {
				$label            = (string) ( $s['digitalisimo_wc_readmore_text'] ?? '' );
				$state['added'][] = array( 'woocommerce_after_shop_loop_item', static function () use ( $label ) {
					echo '<a class="button digi-product__readmore" href="' . esc_url( get_permalink() ) . '">' . esc_html( '' !== $label ? $label : __( 'Read more', 'woocommerce' ) ) . '</a>';
				}, 11 );
			}
			foreach ( $state['added'] as $hook ) {
				add_action( $hook[0], $hook[1], $hook[2] );
			}
		}
		self::$state[ spl_object_id( $element ) ] = $state;
	}

	public static function after_render( $element ) {
		$id = spl_object_id( $element );
		if ( ! isset( self::$state[ $id ] ) ) {
			return;
		}
		$state = self::$state[ $id ];
		unset( self::$state[ $id ] );
		foreach ( $state['added'] as $hook ) {
			remove_action( $hook[0], $hook[1], $hook[2] );
		}
		foreach ( $state['removed'] as $hook ) {
			add_action( $hook[0], $hook[1], $hook[2] );
		}
		if ( $state['limit'] ) {
			remove_filter( 'woocommerce_shortcode_products_query', $state['limit'], 20 );
		}
	}
}
