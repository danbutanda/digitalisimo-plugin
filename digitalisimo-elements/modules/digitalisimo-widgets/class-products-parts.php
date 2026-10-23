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
 *
 * La presentación «Carrusel» envuelve la misma lista de productos en el motor de carruseles propio
 * (región, ventana desplazable con desplazamiento por ficha y flechas), con tantas fichas visibles como
 * columnas tenga el widget en cada dispositivo. La presentación «Tabla» (Products_Table) pinta esos mismos
 * productos en una tabla con encabezados.
 *
 * Partes de WooCommerce visual (F4-Woo, D24 y V.12.2): «Vista rápida», «Lista de deseos» y «Muestras de variación»,
 * fuera del valor por defecto y sólo con «Elegir qué partes mostrar». Cada una pinta el marcado de su renderizador
 * (`Woo\Quick_View\Trigger`, `Woo\Wishlist\Button`, `Woo\Swatches\Loop`) sólo si existe; sin él, los ganchos del bucle
 * quedan como sin la parte. Sus hojas y scripts se declaran en `assets` del control, con una condición `contains` por
 * parte, para que la caché de elementos no los pierda. La presentación «Tabla» usa sus propias columnas y no las pinta.
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

	/**
	 * Partes de F4-Woo: renderizador, gancho del bucle de WooCommerce, prioridad y capacidad de `Woo\Assets` (handle
	 * `digitalisimo-woo-<capacidad>`). La vista rápida y la lista de deseos van tras el botón de compra (10) y «Leer
	 * más» (11); las muestras, tras el precio (10).
	 */
	const WOO_PARTS = array(
		'quickview' => array( 'Digitalisimo\\Elements\\Woo\\Quick_View\\Trigger', 'woocommerce_after_shop_loop_item', 12, 'quick-view' ),
		'wishlist'  => array( 'Digitalisimo\\Elements\\Woo\\Wishlist\\Button', 'woocommerce_after_shop_loop_item', 13, 'wishlist' ),
		'swatches'  => array( 'Digitalisimo\\Elements\\Woo\\Swatches\\Loop', 'woocommerce_after_shop_loop_item_title', 15, 'swatches' ),
	);

	private static $state = array();

	public static function init() {
		add_action( 'elementor/element/woocommerce-products/section_content/after_section_end', array( __CLASS__, 'register_controls' ) );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/widget/after_render', array( __CLASS__, 'after_render' ) );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 2 );
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
			'options'   => array( 'image' => 'Imagen', 'title' => 'Título', 'excerpt' => 'Extracto del contenido', 'summary' => 'Resumen (extracto manual o contenido)', 'category' => 'Categorías', 'rating' => 'Valoración', 'price' => 'Precio', 'cart' => 'Botón de compra', 'readmore' => 'Leer más', 'badge' => 'Insignia de oferta', 'sku' => 'Referencia (SKU, en tabla)', 'tags' => 'Etiquetas (en tabla)', 'quantity' => 'Selector de cantidad (en tabla)', 'quickview' => 'Vista rápida', 'wishlist' => 'Lista de deseos', 'swatches' => 'Muestras de variación' ),
			'condition' => array( 'digitalisimo_wc_custom' => 'yes' ),
			'assets'    => self::woo_assets(),
		) );
		$widget->add_control( 'digitalisimo_wc_title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array_combine( self::TITLE_TAGS, self::TITLE_TAGS ), 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_wc_excerpt_length', array( 'label' => 'Palabras del extracto', 'type' => $c::NUMBER, 'default' => 10, 'min' => 1, 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_wc_readmore_text', array( 'label' => 'Texto de «Leer más»', 'type' => $c::TEXT, 'default' => 'Read More', 'condition' => array( 'digitalisimo_wc_custom' => 'yes' ) ) );
		$widget->add_control( 'digitalisimo_products_limit', array( 'label' => 'Número de productos', 'type' => $c::NUMBER, 'min' => 1, 'description' => 'Vacío: filas por columnas.' ) );
		$widget->add_control( 'digitalisimo_wc_layout', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'grid', 'options' => array( 'grid' => 'Rejilla', 'carousel' => 'Carrusel', 'table' => 'Tabla' ), 'separator' => 'before', 'description' => 'En carrusel se ven tantas fichas como columnas en cada dispositivo; en tabla, una fila por producto con las partes elegidas como columnas.' ) );
		$widget->add_control( 'digitalisimo_wc_navigation', array( 'label' => 'Flechas', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'digitalisimo_wc_layout' => 'carousel' ) ) );
		require_once __DIR__ . '/class-products-table.php';
		Products_Table::register_controls( $widget, $c );
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
			foreach ( self::woo_hooks( $parts, $element ) as $hook ) {
				$state['added'][] = $hook;
			}
			foreach ( $state['added'] as $hook ) {
				add_action( $hook[0], $hook[1], $hook[2] );
			}
		}
		self::$state[ spl_object_id( $element ) ] = $state;
	}

	/**
	 * Declaración `assets` del control de partes (V.12.2): la hoja y el script de cada parte de F4-Woo, sólo si la
	 * instancia elige sus partes y contiene esa. «Elegir qué partes mostrar» va primero: con él apagado, el núcleo da
	 * `null` a las partes y la condición termina antes de mirarlas.
	 */
	public static function woo_assets(): array {
		$assets = array( 'styles' => array(), 'scripts' => array() );
		foreach ( self::WOO_PARTS as $part => $spec ) {
			$conditions = array(
				'relation' => 'and',
				'terms'    => array(
					array( 'name' => 'digitalisimo_wc_custom', 'operator' => '===', 'value' => 'yes' ),
					array( 'name' => self::PARTS_KEY, 'operator' => 'contains', 'value' => $part ),
				),
			);
			foreach ( array( 'styles', 'scripts' ) as $type ) {
				$assets[ $type ][] = array( 'name' => 'digitalisimo-woo-' . $spec[3], 'conditions' => $conditions );
			}
		}
		return $assets;
	}

	/** ¿Pinta esta instancia la parte `$part`? («Elegir qué partes mostrar» y la parte elegida). Para el permiso de la vista rápida. */
	public static function has_part( array $settings, string $part ): bool {
		return 'yes' === ( $settings['digitalisimo_wc_custom'] ?? '' ) && in_array( $part, array_map( 'strval', (array) ( $settings[ self::PARTS_KEY ] ?? array() ) ), true );
	}

	/**
	 * Ganchos de las partes de F4-Woo elegidas cuyo renderizador existe. Sin renderizador (WooCommerce visual no está o
	 * el frente no lo entregó), ninguno: los ganchos quedan como sin la parte.
	 *
	 * @param string[] $parts   Partes elegidas.
	 * @param object   $element Widget Productos.
	 * @return array<int,array> Ganchos `array( gancho, callback, prioridad )`.
	 */
	public static function woo_hooks( array $parts, $element ): array {
		$hooks = array();
		$args  = null;
		foreach ( self::WOO_PARTS as $part => $spec ) {
			if ( ! in_array( $part, $parts, true ) || ! class_exists( $spec[0] ) || ! is_callable( array( $spec[0], 'markup' ) ) ) {
				continue;
			}
			if ( null === $args ) {
				// `post` y `widget` son los que lee `Quick_View\Trigger` (el documento y el ID del elemento del permiso
				// de `woo/quick-view`); `post_id` y `widget_id`, el mismo dato con los nombres del contrato de V.15.2.
				$post   = self::document_id();
				$widget = is_object( $element ) && method_exists( $element, 'get_id' ) ? (string) $element->get_id() : '';
				$args   = array(
					'context'   => 'products',
					'post'      => $post,
					'widget'    => $widget,
					'post_id'   => $post,
					'widget_id' => $widget,
				);
			}
			$class   = $spec[0];
			$hooks[] = array(
				$spec[1],
				static function () use ( $class, $part, $args ) {
					echo self::woo_markup( $class, $part, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado escapado por su renderizador.
				},
				$spec[2],
			);
		}
		return $hooks;
	}

	/** Marcado de una parte para el producto en curso del bucle; '' si no hay producto o el renderizador falla. */
	private static function woo_markup( string $class, string $part, array $args ): string {
		global $product;
		try {
			if ( 'swatches' === $part ) {
				return is_object( $product ) && class_exists( '\WC_Product' ) && $product instanceof \WC_Product ? (string) $class::markup( $product, $args ) : '';
			}
			$id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
			return $id > 0 ? (string) $class::markup( $id, $args ) : '';
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/** Documento que contiene el widget (el permiso de las rutas públicas lo busca ahí, V-M22). */
	private static function document_id(): int {
		$plugin    = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$documents = is_object( $plugin ) && isset( $plugin->documents ) && is_object( $plugin->documents ) ? $plugin->documents : null;
		$document  = $documents && method_exists( $documents, 'get_current' ) ? $documents->get_current() : null;
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			return (int) $document->get_main_id();
		}
		return function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	}

	/** Envuelve la lista de productos en el motor de carruseles; null si el HTML no tiene la lista esperada. */
	public static function carousel( $content, array $s ) {
		if ( ! preg_match( '#<ul class="products\b[^"]*"#', $content, $start, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$open = $start[0][1];
		$stop = strpos( $content, '<nav class="woocommerce-pagination' );
		$head = false === $stop ? $content : substr( $content, 0, $stop );
		$end  = strrpos( $head, '</ul>' );
		if ( false === $end || $end < $open ) {
			return null;
		}
		$list  = substr( $content, $open, $end + 5 - $open );
		$count = preg_match_all( '#<li\b[^>]*class="[^"]*\bproduct\b#', $list );
		$list  = preg_replace( '#^<ul class="products\b#', '<ul class="products digi-carousel__slides', $list, 1 );
		$vars  = '--digi-carousel-count:' . max( 1, $count );
		foreach ( array( '' => '', '_tablet' => '-tablet', '_mobile' => '-mobile' ) as $suffix => $var ) {
			$columns = absint( $s[ 'columns' . $suffix ] ?? 0 );
			if ( $columns ) {
				$vars .= ';--digi-products-visible' . $var . ':' . $columns;
			}
		}
		$controls = 'yes' === ( $s['digitalisimo_wc_navigation'] ?? 'yes' ) && $count > 1
			? '<div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div>'
			: '';
		return substr( $content, 0, $open )
			. '<section class="digi-carousel digi-products-carousel" role="region" aria-roledescription="carrusel" aria-label="Productos" style="' . esc_attr( $vars ) . '"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track>'
			. $list . '</div>' . $controls . '</section>'
			. substr( $content, $end + 5 );
	}

	public static function render_content( $content, $widget ) {
		if ( ! self::applies( $widget ) ) {
			return $content;
		}
		$s = (array) $widget->get_settings_for_display();
		if ( 'table' === ( $s['digitalisimo_wc_layout'] ?? 'grid' ) ) {
			return self::table( (string) $content, $s );
		}
		if ( 'carousel' !== ( $s['digitalisimo_wc_layout'] ?? 'grid' ) ) {
			return $content;
		}
		$carousel = self::carousel( (string) $content, $s );
		if ( null === $carousel ) {
			return $content;
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			if ( ! wp_style_is( 'digitalisimo-products-carousel', 'registered' ) ) {
				wp_register_style( 'digitalisimo-products-carousel', plugins_url( 'assets/css/products-carousel.css', DIGITALISIMO_ELEMENTS_FILE ), array( 'digitalisimo-carousel-engine' ), DIGITALISIMO_ELEMENTS_VERSION );
			}
			wp_enqueue_style( 'digitalisimo-carousel-engine' );
			wp_enqueue_style( 'digitalisimo-products-carousel' );
			wp_enqueue_script( 'digitalisimo-carousel-engine' );
		}
		return $carousel;
	}

	/** Sustituye la lista de productos por su tabla; sin productos reconocibles el contenido queda igual. */
	private static function table( $content, array $s ) {
		require_once __DIR__ . '/class-products-table.php';
		$table = Products_Table::html( Products_Table::ids( $content ), $s );
		if ( '' === $table ) {
			return $content;
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'digitalisimo-table' );
			if ( false !== strpos( $table, 'data-digi-table' ) || false !== strpos( $table, 'digi-products-table__quantity' ) ) {
				wp_enqueue_script( 'digitalisimo-table' );
			}
		}
		$pagination = preg_match( '#<nav class="woocommerce-pagination.*</nav>#s', $content, $m ) ? $m[0] : '';
		return $table . $pagination;
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
