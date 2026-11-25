<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * La landing de una oferta de TIENDA: un descuento en toda la tienda o en una categoría (`create-page` con
 * `store_offer`). Caso plomerito 3 (2026-11-25): «créame una landing de oferta navideña con todos los productos al 40 %»
 * salió con la plantilla de un curso («Lo que vas a lograr», «+ todos los bonus», la factura, el aviso de «resultados e
 * ingresos»), sin un solo producto y con el botón al carrito vacío.
 *
 * Se arma con datos reales y nada de cursos: la portada con la oferta y el botón a la tienda (o a la categoría), la
 * cuadrícula de productos reales con foto, precio normal tachado y precio con el descuento (si son muchos, los más
 * vendidos, y las categorías con imagen), las garantías y beneficios que el sitio ya publica en su pie o su cabecera,
 * las formas de pago y de envío activas, las preguntas frecuentes con esos datos y el cierre con el botón a la tienda.
 * Los colores van como globales de plantilla (`primary`, `accent`, `text`) y `Brand::apply()` los cambia por los del
 * sitio, como en las plantillas del kit.
 *
 * `page()` es pura (las pruebas la llaman sin WordPress); `catalog()` y `benefits()` leen la tienda y el sitio.
 */
final class Store {
	/** Hasta cuántos productos se muestran todos; con más, los más vendidos. */
	const ALL_UP_TO = 12;
	const TOP       = 8;
	const MAX_CATS  = 8;

	/** Textos de cursos e infoproductos que una landing de tienda nunca lleva. */
	const COURSE_TEXT = '/lo que vas a lograr|\+?\s*todos los bonus|\bbonus\b|\bm[óo]dulos?\b|\bfactura|resultados e ingresos|resultados no son t[íi]picos|no t[íi]picos|resultados dependen|inscripci[óo]n|clase en vivo|\breto de\b|tu (programa|curso)\b|\bcurso\b|transformaci[óo]n/iu';

	/** Iconos para las garantías que se arman con los datos de la tienda (no del pie). */
	const ICONS = array(
		'envio'      => 'fas fa-truck',
		'pago'       => 'fas fa-lock',
		'devolucion' => 'fas fa-undo',
		'whatsapp'   => 'fab fa-whatsapp',
	);

	/* ------------------------------------------------------------------ */
	/* Entrada.                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * `store_offer` validado: ['percent', 'category', 'label', 'ends' («AAAA-MM-DD HH:MM» en la hora de la tienda o ''),
	 * 'headline', 'subtitle'], o un error.
	 *
	 * @return array|\WP_Error
	 */
	public static function offer( $input, array $env ) {
		if ( ! is_array( $input ) ) {
			return self::error( 'store_offer debe ser un objeto: percent (el descuento, p. ej. 40), y si quieres category, label, ends, headline y subtitle.' );
		}
		$unknown = array_diff( array_keys( $input ), array( 'percent', 'category', 'label', 'ends', 'headline', 'subtitle' ) );
		if ( $unknown ) {
			return self::error( sprintf( 'store_offer: parámetros desconocidos: %s. Admite percent, category, label, ends, headline y subtitle.', implode( ', ', $unknown ) ) );
		}
		$percent = Product::number( is_string( $input['percent'] ?? null ) ? str_replace( '%', '', $input['percent'] ) : ( $input['percent'] ?? null ) );
		if ( null === $percent || $percent < 1 || $percent > 90 ) {
			return self::error( 'store_offer.percent: el descuento en porcentaje, de 1 a 90 (p. ej. 40).' );
		}
		$out = array(
			'percent'  => round( $percent, 2 ),
			'category' => '',
			'label'    => '',
			'ends'     => '',
			'headline' => '',
			'subtitle' => '',
		);
		if ( isset( $input['category'] ) && '' !== $input['category'] ) {
			if ( ! is_string( $input['category'] ) && ! is_int( $input['category'] ) ) {
				return self::error( 'store_offer.category: el nombre, la dirección o el número de una categoría de la tienda.' );
			}
			$out['category'] = trim( sanitize_text_field( (string) $input['category'] ) );
		}
		foreach ( array(
			'label'    => 80,
			'headline' => 120,
			'subtitle' => 300,
		) as $key => $max ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				continue;
			}
			$text = is_string( $input[ $key ] ) ? trim( sanitize_text_field( $input[ $key ] ) ) : '';
			if ( '' === $text || strlen( $text ) > $max || '' !== Builder::external_url( $text, '' ) || false !== strpos( $text, '[' ) || preg_match( self::COURSE_TEXT, $text ) ) {
				return self::error( sprintf( 'store_offer.%s: un texto corto de la oferta (hasta %d caracteres), sin enlaces, corchetes ni frases de cursos.', $key, $max ) );
			}
			$out[ $key ] = $text;
		}
		if ( isset( $input['ends'] ) && '' !== $input['ends'] ) {
			$raw = is_string( $input['ends'] ) ? trim( $input['ends'] ) : '';
			if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $raw ) ) {
				$raw .= ' 23:59';
			}
			$tz = isset( $env['timezone'] ) && $env['timezone'] instanceof \DateTimeZone ? $env['timezone'] : new \DateTimeZone( 'UTC' );
			$at = preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/', $raw ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $raw, $tz ) : false;
			if ( ! $at || $at->format( 'Y-m-d H:i' ) !== $raw ) {
				return self::error( 'store_offer.ends: «AAAA-MM-DD HH:MM» (o «AAAA-MM-DD», hasta las 23:59) en la hora de la tienda.' );
			}
			if ( $at->getTimestamp() <= (int) ( $env['now'] ?? time() ) ) {
				return self::error( 'store_offer.ends ya pasó: el fin de la oferta debe ser futuro.' );
			}
			$out['ends'] = $raw;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Lectura de la tienda y del sitio (WordPress).                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Los productos y las categorías para la oferta: ['products' => [['id','name','url','image','regular','from']],
	 * 'categories' => [['name','url','image','count']], 'total', 'scope' ('all'|'category'), 'scope_name', 'url' (tienda
	 * o categoría), 'top' (bool: sólo los más vendidos)], o un error.
	 *
	 * @return array|\WP_Error
	 */
	public static function catalog( string $category ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return self::error( 'store_offer necesita la tienda activa en este sitio.' );
		}
		$term = null;
		if ( '' !== $category ) {
			$term = self::find_category( $category );
			if ( ! $term ) {
				return self::error( sprintf( 'No encontré la categoría «%s» en la tienda: usa el nombre o la dirección de una categoría que exista, o quita category para toda la tienda.', $category ) );
			}
		}
		$args = array(
			'status'     => 'publish',
			'visibility' => 'catalog',
			'limit'      => -1,
			'return'     => 'ids',
		);
		if ( $term ) {
			$args['category'] = array( (string) $term->slug );
		}
		$ids   = array_map( 'intval', (array) wc_get_products( $args ) );
		$total = count( $ids );
		if ( 0 === $total ) {
			return self::error( $term ? 'Esa categoría no tiene productos publicados.' : 'La tienda no tiene productos publicados.' );
		}
		$top = $total > self::ALL_UP_TO;
		if ( $top ) {
			$args['limit']   = self::TOP * 2;
			$args['orderby'] = 'popularity';
			$args['order']   = 'DESC';
			$ids             = array_map( 'intval', (array) wc_get_products( $args ) );
		} else {
			$args['orderby'] = 'menu_order';
			$args['order']   = 'ASC';
			$ids             = array_map( 'intval', (array) wc_get_products( $args ) );
		}
		$products = array();
		foreach ( $ids as $id ) {
			$item = self::product( $id );
			if ( $item ) {
				$products[] = $item;
			}
		}
		// Primero los que tienen foto.
		usort(
			$products,
			static function ( $a, $b ) {
				return (int) ( '' === $a['image']['url'] ) <=> (int) ( '' === $b['image']['url'] );
			}
		);
		$products = array_slice( $products, 0, $top ? self::TOP : self::ALL_UP_TO );

		$shop = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $shop_id ) ) {
			$shop = function_exists( 'get_post_type_archive_link' ) ? (string) get_post_type_archive_link( 'product' ) : '';
		}
		$url = $term && function_exists( 'get_term_link' ) ? get_term_link( $term ) : $shop;
		return array(
			'products'   => $products,
			'categories' => $term ? array() : self::categories( $top ),
			'total'      => $total,
			'scope'      => $term ? 'category' : 'all',
			'scope_name' => $term ? (string) $term->name : '',
			'url'        => is_string( $url ) ? $url : $shop,
			'top'        => $top,
		);
	}

	/** Un producto para la cuadrícula, o null si no tiene precio. */
	private static function product( int $id ): ?array {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $p || ! is_object( $p ) || '' !== (string) get_post_field( 'post_password', $id ) ) {
			return null;
		}
		$regular = Product::number( $p->get_regular_price() );
		$from    = false;
		if ( 'variable' === $p->get_type() && method_exists( $p, 'get_variation_regular_price' ) ) {
			$min     = Product::number( $p->get_variation_regular_price( 'min' ) );
			$max     = Product::number( $p->get_variation_regular_price( 'max' ) );
			$regular = $min;
			$from    = null !== $min && null !== $max && $max > $min;
		}
		if ( null === $regular ) {
			$regular = Product::number( $p->get_price() );
		}
		if ( null === $regular || $regular <= 0 ) {
			return null;
		}
		$image_id = (int) $p->get_image_id();
		$url      = $image_id > 0 && function_exists( 'wp_get_attachment_image_url' ) ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
		return array(
			'id'      => $id,
			'name'    => (string) $p->get_name(),
			'url'     => function_exists( 'get_permalink' ) ? (string) get_permalink( $id ) : '',
			'image'   => array(
				'id'  => '' !== $url ? $image_id : 0,
				'url' => $url,
				'alt' => '' !== $url && function_exists( 'get_post_meta' ) ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
			),
			'regular' => $regular,
			'from'    => $from,
		);
	}

	/** Una categoría de producto por id, dirección o nombre. */
	private static function find_category( string $value ) {
		if ( ! function_exists( 'get_term_by' ) ) {
			return null;
		}
		$candidates = array();
		if ( ctype_digit( $value ) ) {
			$candidates[] = get_term_by( 'id', (int) $value, 'product_cat' );
		}
		$candidates[] = get_term_by( 'slug', sanitize_title( $value ), 'product_cat' );
		$candidates[] = get_term_by( 'name', $value, 'product_cat' );
		foreach ( $candidates as $term ) {
			if ( $term && is_object( $term ) && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
		// Sin acentos ni mayúsculas.
		$want = self::fold( $value );
		$all  = function_exists( 'get_terms' ) ? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		) : array();
		foreach ( is_array( $all ) ? $all : array() as $term ) {
			if ( is_object( $term ) && ( self::fold( (string) $term->name ) === $want || 0 === strpos( self::fold( (string) $term->name ), $want ) ) ) {
				return $term;
			}
		}
		return null;
	}

	/** Las categorías con productos e imagen (la suya o la de un producto), las de más productos primero. */
	private static function categories( bool $wanted ): array {
		if ( ! $wanted || ! function_exists( 'get_terms' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 30,
			)
		);
		$out     = array();
		$default = function_exists( 'get_option' ) ? (int) get_option( 'default_product_cat' ) : 0;
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! is_object( $term ) || (int) $term->term_id === $default ) {
				continue;
			}
			$image = function_exists( 'get_term_meta' ) ? (int) get_term_meta( (int) $term->term_id, 'thumbnail_id', true ) : 0;
			$url   = $image > 0 && function_exists( 'wp_get_attachment_image_url' ) ? (string) wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' ) : '';
			if ( '' === $url && function_exists( 'wc_get_products' ) ) {
				$one = (array) wc_get_products(
					array(
						'status'   => 'publish',
						'limit'    => 1,
						'category' => array( (string) $term->slug ),
						'return'   => 'objects',
					)
				);
				if ( $one && is_object( $one[0] ) && (int) $one[0]->get_image_id() > 0 ) {
					$image = (int) $one[0]->get_image_id();
					$url   = (string) wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' );
				}
			}
			$link = function_exists( 'get_term_link' ) ? get_term_link( $term ) : '';
			if ( '' === $url || ! is_string( $link ) ) {
				continue;
			}
			$out[] = array(
				'name'  => (string) $term->name,
				'url'   => $link,
				'image' => array(
					'id'  => $image,
					'url' => $url,
					'alt' => (string) $term->name,
				),
				'count' => (int) $term->count,
			);
			if ( count( $out ) >= self::MAX_CATS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Las garantías y beneficios que el sitio ya publica: las cajas con icono (título y texto) del pie y la cabecera en uso
	 * y de la portada. [['title', 'text', 'icon' => ['value', 'library']]], hasta cuatro.
	 */
	public static function benefits( array $docs ): array {
		$out = array();
		foreach ( array( 'footer', 'header', 'front' ) as $where ) {
			foreach ( (array) ( $docs[ $where ] ?? array() ) as $tree ) {
				self::boxes( (array) $tree, $out, 0 );
			}
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return array_slice( array_values( $out ), 0, 4 );
	}

	private static function boxes( array $elements, array &$out, int $depth ): void {
		if ( $depth > 30 ) {
			return;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$s = is_array( $el['settings'] ?? null ) ? $el['settings'] : array();
			if ( in_array( (string) ( $el['widgetType'] ?? '' ), array( 'icon-box', 'image-box' ), true ) ) {
				$title = self::plain( (string) ( $s['title_text'] ?? '' ) );
				$text  = self::plain( (string) ( $s['description_text'] ?? '' ) );
				$key   = strtolower( $title );
				if ( '' !== $title && strlen( $title ) <= 80 && strlen( $text ) <= 300 && ! isset( $out[ $key ] ) && '' === Builder::external_url( $title . ' ' . $text, '' ) ) {
					$icon        = is_array( $s['selected_icon'] ?? null ) && is_string( $s['selected_icon']['value'] ?? null ) ? $s['selected_icon'] : array(
						'value'   => 'fas fa-check-circle',
						'library' => 'fa-solid',
					);
					$out[ $key ] = array(
						'title' => $title,
						'text'  => $text,
						'icon'  => is_string( $icon['value'] ) && preg_match( '/^(fa[srb]?|fa-[a-z]+) fa-[a-z0-9-]+$/', $icon['value'] ) ? array(
							'value'   => $icon['value'],
							'library' => (string) ( $icon['library'] ?? 'fa-solid' ),
						) : array(
							'value'   => 'fas fa-check-circle',
							'library' => 'fa-solid',
						),
					);
				}
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::boxes( $el['elements'], $out, $depth + 1 );
			}
		}
	}

	/** La primera frase de la política de devoluciones publicada, o ''. */
	public static function refund_line( string $url ): string {
		if ( '' === $url || ! function_exists( 'url_to_postid' ) ) {
			return '';
		}
		$id   = (int) url_to_postid( $url );
		$post = $id > 0 && function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! $post ) {
			return '';
		}
		$text = self::plain( (string) $post->post_content );
		if ( '' === $text && function_exists( 'get_post_meta' ) ) {
			$text = self::plain( (string) get_post_meta( $id, '_elementor_data', true ) );
		}
		return Product::first_sentence( $text );
	}

	/* ------------------------------------------------------------------ */
	/* La página (pura).                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Los elementos de la landing. `$offer` de `offer()`, `$catalog` de `catalog()`, `$site` de `Site::read()` (más
	 * `benefits` y `refund_line`), `$env` con `timezone`, `site_timezone` y `variant` (`containers` o `sections`).
	 *
	 * @return array ['elements', 'title', 'button_url', 'filled', 'warnings', 'shown']
	 */
	public static function page( array $offer, array $catalog, array $site, array $env ): array {
		$b        = new Store_Layout( (string) ( $env['variant'] ?? 'containers' ) );
		$cur      = (string) ( $site['currency'] ?? 'MXN' );
		$sym      = (string) ( $site['currency_symbol'] ?? '$' );
		$money    = static function ( float $v ) use ( $sym, $cur ): string {
			return $sym . Product::money( $v ) . ( '' !== $cur ? ' ' . $cur : '' );
		};
		$pct      = rtrim( rtrim( number_format( (float) $offer['percent'], 2, '.', '' ), '0' ), '.' );
		$scope    = 'category' === $catalog['scope'] ? 'en ' . $catalog['scope_name'] : 'en toda la tienda';
		$label    = (string) $offer['label'];
		$url      = (string) $catalog['url'];
		$cta      = 'category' === $catalog['scope'] ? 'Ver ' . $catalog['scope_name'] : 'Ver la tienda';
		$filled   = array(
			array(
				'field'  => 'productos',
				'source' => sprintf( '%d productos reales de la tienda%s', count( $catalog['products'] ), $catalog['top'] ? ' (los más vendidos)' : '' ),
			),
			array(
				'field'  => 'botón principal',
				'source' => 'category' === $catalog['scope'] ? 'la categoría de la tienda' : 'la tienda',
			),
		);
		$warnings = array();
		$due      = '';
		$ends_txt = '';
		if ( '' !== $offer['ends'] ) {
			$tz       = $env['timezone'] instanceof \DateTimeZone ? $env['timezone'] : new \DateTimeZone( 'UTC' );
			$site_tz  = isset( $env['site_timezone'] ) && $env['site_timezone'] instanceof \DateTimeZone ? $env['site_timezone'] : $tz;
			$at       = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $offer['ends'], $tz );
			$due      = $at->setTimezone( $site_tz )->format( 'Y-m-d H:i' );
			$ends_txt = sprintf( 'hasta el %d de %s', (int) $at->format( 'j' ), Builder::MONTHS[ (int) $at->format( 'n' ) - 1 ] );
		}

		// 1 · Portada: la oferta y el botón a la tienda.
		$headline = '' !== $offer['headline'] ? $offer['headline'] : $pct . ' % de descuento ' . $scope;
		$subtitle = '' !== $offer['subtitle'] ? $offer['subtitle'] : trim( ( 'category' === $catalog['scope'] ? 'Todos los productos de ' . $catalog['scope_name'] : 'Todos los productos' ) . ' con ' . $pct . ' % de descuento' . ( '' !== $ends_txt ? ', ' . $ends_txt : '' ) . '.' );
		$hero     = array();
		if ( '' !== $label ) {
			$hero[] = $b->heading( self::e( $label ), 'p', 'accent', 18, 'center', 700, array( 'typography_text_transform' => 'uppercase', 'typography_letter_spacing' => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ) ) );
		}
		$hero[] = $b->heading( self::e( $headline ), 'h1', 'white', 52, 'center', 800 );
		$hero[] = $b->heading( self::e( $subtitle ), 'p', 'white', 20, 'center', 400 );
		if ( '' !== $due ) {
			$hero[] = $b->countdown( $due );
		}
		$hero[]   = $b->button( $cta, $url, 'center', 'lg' );
		$elements = array( $b->section( $hero, 'primary', 80, 'hero' ) );

		// 2 · Los productos reales con su precio normal tachado y el de oferta.
		$cards = array();
		foreach ( $catalog['products'] as $p ) {
			$sale  = round( $p['regular'] * ( 1 - $offer['percent'] / 100 ), 2 );
			$card  = array();
			if ( '' !== $p['image']['url'] ) {
				$card[] = $b->image( $p['image'], $p['name'], $p['url'] );
			}
			$card[]  = $b->heading( self::e( $p['name'] ), 'h3', 'text', 17, 'center', 600, array(), $p['url'] );
			$card[]  = $b->text( '<p><del>' . ( $p['from'] ? 'Desde ' : '' ) . self::e( $money( $p['regular'] ) ) . '</del></p>', 'muted', 'center', 15 );
			$card[]  = $b->heading( ( $p['from'] ? 'Desde ' : '' ) . self::e( $money( $sale ) ), 'p', 'primary', 24, 'center', 800 );
			$card[]  = $b->button( 'Ver producto', $p['url'], 'center', 'sm' );
			$cards[] = $card;
		}
		$title_products = $catalog['top'] ? sprintf( 'Los más vendidos con %s %% de descuento', $pct ) : sprintf( 'Productos con %s %% de descuento', $pct );
		$elements[]     = $b->section(
			array(
				$b->heading( self::e( $title_products ), 'h2', 'primary', 36, 'center', 700 ),
				$b->grid( $cards, 4, 'card' ),
				$b->button( $catalog['top'] ? 'Ver todos los productos' : $cta, $url, 'center', 'md' ),
			),
			'white',
			70,
			'productos'
		);

		// 3 · Las categorías con imagen (sólo si son muchos productos).
		if ( $catalog['categories'] ) {
			$cells = array();
			foreach ( $catalog['categories'] as $cat ) {
				$cells[] = array(
					$b->image( $cat['image'], $cat['name'], $cat['url'] ),
					$b->heading( self::e( $cat['name'] ), 'h3', 'text', 17, 'center', 600, array(), $cat['url'] ),
				);
			}
			$elements[] = $b->section(
				array(
					$b->heading( 'Compra por categoría', 'h2', 'primary', 32, 'center', 700 ),
					$b->grid( $cells, 4, 'plain' ),
				),
				'light',
				60,
				'categorias'
			);
			$filled[] = array(
				'field'  => 'categorías',
				'source' => 'las categorías de la tienda con su imagen',
			);
		}

		// 4 · Garantías y beneficios: los del sitio (pie o cabecera) o, si no publica ninguno, los de sus datos reales.
		$benefits = (array) ( $site['benefits'] ?? array() );
		if ( $benefits ) {
			$filled[] = array(
				'field'  => 'garantías y beneficios',
				'source' => 'las que el sitio ya publica en su pie o su cabecera',
			);
		} else {
			$benefits = self::own_benefits( $site );
		}
		if ( $benefits ) {
			$cells = array();
			foreach ( $benefits as $item ) {
				$cells[] = array( $b->icon_box( $item['icon'], $item['title'], $item['text'] ) );
			}
			$elements[] = $b->section(
				array(
					$b->heading( 'Compra con confianza', 'h2', 'primary', 32, 'center', 700 ),
					$b->grid( $cells, min( 4, count( $cells ) ), 'plain' ),
				),
				$catalog['categories'] ? 'white' : 'light',
				60,
				'garantias'
			);
		}

		// 5 · Formas de pago y envío reales.
		$pay  = array_values( array_filter( array_map( 'strval', (array) ( $site['gateways'] ?? array() ) ) ) );
		$ship = array();
		foreach ( (array) ( $site['shipping'] ?? array() ) as $method ) {
			$ship[] = trim( (string) $method['title'] . ( ! empty( $method['condition'] ) ? ' (' . $method['condition'] . ')' : '' ) );
		}
		$ship = array_values( array_unique( array_filter( $ship ) ) );
		if ( $pay || $ship ) {
			$cols = array();
			if ( $pay ) {
				$cols[] = array(
					$b->heading( 'Formas de pago', 'h3', 'primary', 22, 'left', 700 ),
					$b->icon_list( $pay, 'fas fa-credit-card' ),
				);
			}
			if ( $ship ) {
				$cols[] = array(
					$b->heading( 'Envíos', 'h3', 'primary', 22, 'left', 700 ),
					$b->icon_list( $ship, 'fas fa-truck' ),
				);
			}
			$elements[] = $b->section( array( $b->grid( $cols, count( $cols ), 'plain' ) ), 'white', 50, 'pago-y-envio' );
			$filled[]   = array(
				'field'  => 'pagos y envíos',
				'source' => 'los medios de pago y los envíos activos de la tienda',
			);
		}

		// 6 · Preguntas frecuentes con datos reales.
		$faq   = array();
		$faq[] = array( '¿El descuento aplica en todos los productos?', 'category' === $catalog['scope'] ? sprintf( 'Aplica en todos los productos de %s.', self::e( $catalog['scope_name'] ) ) : 'Sí, en todos los productos de la tienda.' );
		if ( '' !== $ends_txt ) {
			$faq[] = array( '¿Hasta cuándo es la oferta?', ucfirst( $ends_txt ) . '.' );
		}
		if ( $pay ) {
			$faq[] = array( '¿Cómo puedo pagar?', self::e( self::enumerate( $pay ) ) . '.' );
		}
		if ( $ship ) {
			$faq[] = array( '¿Hacen envíos?', 'Sí: ' . self::e( self::enumerate( $ship ) ) . '.' );
		}
		$refund_url = (string) ( $site['links']['/politica-de-reembolso/'] ?? '' );
		if ( '' !== $refund_url ) {
			$line  = (string) ( $site['refund_line'] ?? '' );
			$faq[] = array( '¿Puedo devolver un producto?', ( '' !== $line ? self::e( $line ) . ' ' : '' ) . '<a href="' . self::attr( $refund_url ) . '">Consulta la política de devoluciones</a>.' );
		}
		$whatsapp = (string) ( $site['whatsapp'] ?? '' );
		if ( '' !== $whatsapp ) {
			$faq[] = array( '¿Tienes dudas?', '<a href="https://wa.me/' . self::attr( $whatsapp ) . '">Escríbenos por WhatsApp</a> y te ayudamos.' );
		} elseif ( '' !== (string) ( $site['contact_url'] ?? '' ) ) {
			$faq[] = array( '¿Tienes dudas?', '<a href="' . self::attr( (string) $site['contact_url'] ) . '">Escríbenos</a> y te ayudamos.' );
		}
		$elements[] = $b->section(
			array(
				$b->heading( 'Preguntas frecuentes', 'h2', 'primary', 32, 'center', 700 ),
				$b->accordion( $faq ),
			),
			'light',
			60,
			'preguntas'
		);

		// 7 · Cierre: el botón a la tienda (y WhatsApp si el sitio lo publica).
		$close = array(
			$b->heading( self::e( '' !== $ends_txt ? sprintf( 'Aprovecha el %s %% de descuento %s', $pct, $ends_txt ) : sprintf( 'Aprovecha el %s %% de descuento', $pct ) ), 'h2', 'white', 34, 'center', 800 ),
			$b->button( $cta, $url, 'center', 'lg' ),
		);
		if ( '' !== $whatsapp ) {
			$close[] = $b->text( '<p><a href="https://wa.me/' . self::attr( $whatsapp ) . '">¿Dudas? Escríbenos por WhatsApp</a></p>', 'white', 'center', 16 );
		}
		$elements[] = $b->section( $close, 'primary', 70, 'cierre' );

		$warnings[] = sprintf( 'La tienda sigue cobrando el precio normal: la landing anuncia %s %% de descuento. Para que el carrito cobre lo mismo, aplica el descuento en la tienda (por ejemplo, con un cupón o con el precio rebajado de cada producto) antes de publicar.', $pct );
		$title      = trim( ( '' !== $label ? $label . ': ' : '' ) . $pct . ' % de descuento ' . $scope );
		return array(
			'elements'   => $elements,
			'title'      => $title,
			'button_url' => $url,
			'filled'     => $filled,
			'warnings'   => $warnings,
			'shown'      => count( $catalog['products'] ),
		);
	}

	/** Garantías armadas con los datos reales de la tienda cuando el sitio no publica las suyas. */
	private static function own_benefits( array $site ): array {
		$out = array();
		foreach ( (array) ( $site['shipping'] ?? array() ) as $m ) {
			if ( ! empty( $m['free'] ) || ! empty( $m['condition'] ) ) {
				$out[] = array(
					'title' => 'Envío gratis',
					'text'  => ! empty( $m['condition'] ) ? ucfirst( (string) $m['condition'] ) . '.' : 'En tus compras.',
					'icon'  => array( 'value' => self::ICONS['envio'], 'library' => 'fa-solid' ),
				);
				break;
			}
		}
		if ( ! $out && ! empty( $site['shipping'] ) ) {
			$out[] = array(
				'title' => 'Envíos a domicilio',
				'text'  => 'Te lo llevamos a tu casa.',
				'icon'  => array( 'value' => self::ICONS['envio'], 'library' => 'fa-solid' ),
			);
		}
		if ( ! empty( $site['gateways'] ) ) {
			$out[] = array(
				'title' => 'Formas de pago',
				'text'  => self::enumerate( array_slice( (array) $site['gateways'], 0, 3 ) ) . '.',
				'icon'  => array( 'value' => self::ICONS['pago'], 'library' => 'fa-solid' ),
			);
		}
		if ( '' !== (string) ( $site['links']['/politica-de-reembolso/'] ?? '' ) ) {
			$out[] = array(
				'title' => 'Devoluciones',
				'text'  => '' !== (string) ( $site['refund_line'] ?? '' ) ? (string) $site['refund_line'] : 'Consulta nuestra política de devoluciones.',
				'icon'  => array( 'value' => self::ICONS['devolucion'], 'library' => 'fa-solid' ),
			);
		}
		if ( '' !== (string) ( $site['whatsapp'] ?? '' ) ) {
			$out[] = array(
				'title' => 'Atención por WhatsApp',
				'text'  => 'Te ayudamos a elegir.',
				'icon'  => array( 'value' => self::ICONS['whatsapp'], 'library' => 'fa-brands' ),
			);
		}
		return array_slice( $out, 0, 4 );
	}

	/** Los textos visibles de unos elementos (para comprobar que no queda nada de cursos). */
	public static function texts( array $elements, array &$out = array(), int $depth = 0 ): array {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) || $depth > 40 ) {
				continue;
			}
			foreach ( (array) ( $el['settings'] ?? array() ) as $key => $value ) {
				if ( is_string( $value ) && in_array( $key, array( 'title', 'editor', 'text', 'title_text', 'description_text', 'heading', 'description', 'message_after_expire' ), true ) ) {
					$out[] = self::plain( $value );
				} elseif ( is_array( $value ) && in_array( $key, array( 'tabs', 'icon_list' ), true ) ) {
					foreach ( $value as $item ) {
						foreach ( array( 'tab_title', 'tab_content', 'text' ) as $k ) {
							if ( is_array( $item ) && isset( $item[ $k ] ) && is_string( $item[ $k ] ) ) {
								$out[] = self::plain( $item[ $k ] );
							}
						}
					}
				}
			}
			self::texts( (array) ( $el['elements'] ?? array() ), $out, $depth + 1 );
		}
		return $out;
	}

	/** Las frases de cursos que quedan en una página (vacío si ninguna). */
	public static function course_texts( array $elements ): array {
		$found = array();
		foreach ( self::texts( $elements ) as $text ) {
			if ( preg_match_all( self::COURSE_TEXT, $text, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$found[] = trim( $hit );
				}
			}
		}
		return array_values( array_unique( $found ) );
	}

	private static function plain( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( str_replace( array( '<br>', '<br/>', '<br />', '</p>' ), ' ', $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	private static function fold( string $text ): string {
		$text = function_exists( 'remove_accents' ) ? remove_accents( $text ) : $text;
		return strtolower( trim( $text ) );
	}

	private static function e( string $text ): string {
		return esc_html_safe( str_replace( array( '[', ']' ), array( '(', ')' ), $text ) );
	}

	private static function attr( string $text ): string {
		return esc_attr_safe( $text );
	}

	private static function enumerate( array $items ): string {
		$items = array_values( $items );
		$last  = array_pop( $items );
		return $items ? implode( ', ', $items ) . ' y ' . $last : (string) $last;
	}

	private static function error( string $message ): \WP_Error {
		return new \WP_Error( 'digitalisimo_sales_store_offer', $message, array( 'status' => 400 ) );
	}
}

/**
 * Las piezas de la landing de tienda en las dos variantes del constructor (contenedores, o secciones y columnas). Los
 * colores van como globales de plantilla (`globals/colors?id=primary|accent|text`) o neutros; `Brand::apply()` los
 * cambia por los del sitio. Los IDs son de siete hexadecimales, únicos en la página.
 */
final class Store_Layout {
	private $variant;
	private $used = array();

	public function __construct( string $variant ) {
		$this->variant = 'sections' === $variant ? 'sections' : 'containers';
	}

	private function id(): string {
		do {
			$id = substr( md5( uniqid( '', true ) . count( $this->used ) . mt_rand() ), 0, 7 );
		} while ( isset( $this->used[ $id ] ) || ctype_digit( $id ) );
		$this->used[ $id ] = true;
		return $id;
	}

	/** Un color: los papeles de la marca como global de plantilla; los neutros, literales. */
	private function color( array &$s, string $key, string $role ): void {
		$neutral = array(
			'white' => '#FFFFFF',
			'light' => '#F5F5F5',
			'muted' => '#8A8A8A',
			'line'  => '#E6E6E6',
		);
		if ( isset( $neutral[ $role ] ) ) {
			$s[ $key ] = $neutral[ $role ];
			return;
		}
		$s[ $key ]                   = '';
		$s['__globals__'][ $key ]    = 'globals/colors?id=' . $role;
	}

	private static function px( $size ): array {
		return array(
			'unit'  => 'px',
			'size'  => $size,
			'sizes' => array(),
		);
	}

	private static function box( $t, $r = null, $b = null, $l = null ): array {
		return array(
			'unit'     => 'px',
			'top'      => (string) $t,
			'right'    => (string) ( null === $r ? $t : $r ),
			'bottom'   => (string) ( null === $b ? $t : $b ),
			'left'     => (string) ( null === $l ? ( null === $r ? $t : $r ) : $l ),
			'isLinked' => null === $r,
		);
	}

	private function widget( string $type, array $settings ): array {
		return array(
			'id'         => $this->id(),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	public function heading( string $html, string $tag, string $role, int $size, string $align, int $weight, array $extra = array(), string $link = '' ): array {
		$s = array(
			'title'                       => $html,
			'header_size'                 => $tag,
			'align'                       => $align,
			'typography_typography'       => 'custom',
			'typography_font_size'        => self::px( $size ),
			'typography_font_size_mobile' => self::px( max( 14, (int) round( $size * 0.72 ) ) ),
			'typography_font_weight'      => (string) $weight,
			'typography_line_height'      => array(
				'unit'  => 'em',
				'size'  => $size >= 30 ? 1.15 : 1.35,
				'sizes' => array(),
			),
		) + $extra;
		$this->color( $s, 'title_color', $role );
		if ( '' !== $link ) {
			$s['link'] = array(
				'url'         => $link,
				'is_external' => '',
				'nofollow'    => '',
			);
		}
		return $this->widget( 'heading', $s );
	}

	public function text( string $html, string $role, string $align, int $size ): array {
		$s = array(
			'editor'                => $html,
			'align'                 => $align,
			'typography_typography' => 'custom',
			'typography_font_size'  => self::px( $size ),
		);
		$this->color( $s, 'text_color', $role );
		$this->color( $s, 'link_color', 'white' === $role ? 'white' : 'primary' );
		return $this->widget( 'text-editor', $s );
	}

	public function button( string $text, string $url, string $align, string $size ): array {
		$s = array(
			'text'          => $text,
			'link'          => array(
				'url'         => $url,
				'is_external' => '',
				'nofollow'    => '',
			),
			'align'         => $align,
			'size'          => $size,
			'border_radius' => self::box( 8 ),
			'text_padding'  => 'lg' === $size ? self::box( 18, 36 ) : ( 'sm' === $size ? self::box( 10, 18 ) : self::box( 14, 28 ) ),
			'typography_typography'  => 'custom',
			'typography_font_weight' => '700',
		);
		$this->color( $s, 'background_color', 'accent' );
		$this->color( $s, 'button_text_color', 'white' );
		return $this->widget( 'button', $s );
	}

	public function image( array $image, string $alt, string $link ): array {
		return $this->widget(
			'image',
			array(
				'image'         => array(
					'url'    => (string) $image['url'],
					'id'     => (int) $image['id'],
					'alt'    => '' !== (string) ( $image['alt'] ?? '' ) ? (string) $image['alt'] : $alt,
					'source' => 'library',
				),
				'image_size'    => 'woocommerce_thumbnail',
				'align'         => 'center',
				'link_to'       => 'custom',
				'link'          => array(
					'url'         => $link,
					'is_external' => '',
					'nofollow'    => '',
				),
				'image_border_radius' => self::box( 8 ),
				'width'         => array(
					'unit'  => '%',
					'size'  => 100,
					'sizes' => array(),
				),
				'height'        => self::px( 220 ),
				'object-fit'    => 'contain',
			)
		);
	}

	public function countdown( string $due ): array {
		$s = array(
			'countdown_type'       => 'due_date',
			'due_date'             => $due,
			'custom_labels'        => 'yes',
			'label_days'           => 'Días',
			'label_hours'          => 'Horas',
			'label_minutes'        => 'Minutos',
			'label_seconds'        => 'Segundos',
			'box_border_radius'    => self::box( 10 ),
			'box_spacing'          => self::px( 10 ),
			'container_width'      => array(
				'unit'  => '%',
				'size'  => 60,
				'sizes' => array(),
			),
			'expire_actions'       => array( 'message' ),
			'message_after_expire' => 'La oferta ya terminó.',
		);
		$this->color( $s, 'box_background_color', 'white' );
		$this->color( $s, 'digits_color', 'primary' );
		$this->color( $s, 'label_color', 'text' );
		return $this->widget( 'countdown', $s );
	}

	public function icon_box( array $icon, string $title, string $text ): array {
		$s = array(
			'selected_icon'    => $icon,
			'title_text'       => esc_html_safe( $title ),
			'description_text' => esc_html_safe( $text ),
			'position'         => 'top',
			'title_size'       => 'h3',
			'icon_size'        => self::px( 40 ),
		);
		$this->color( $s, 'primary_color', 'primary' );
		$this->color( $s, 'title_color', 'text' );
		$this->color( $s, 'description_color', 'text' );
		return $this->widget( 'icon-box', $s );
	}

	public function icon_list( array $items, string $icon ): array {
		$list = array();
		foreach ( $items as $item ) {
			$list[] = array(
				'_id'           => $this->id(),
				'text'          => esc_html_safe( (string) $item ),
				'selected_icon' => array(
					'value'   => $icon,
					'library' => 'fa-solid',
				),
			);
		}
		$s = array(
			'icon_list'                  => $list,
			'icon_typography_typography' => 'custom',
			'icon_typography_font_size'  => self::px( 17 ),
		);
		$this->color( $s, 'icon_color', 'primary' );
		$this->color( $s, 'text_color', 'text' );
		return $this->widget( 'icon-list', $s );
	}

	public function accordion( array $pairs ): array {
		$tabs = array();
		foreach ( $pairs as $pair ) {
			$tabs[] = array(
				'_id'         => $this->id(),
				'tab_title'   => $pair[0],
				'tab_content' => '<p>' . $pair[1] . '</p>',
			);
		}
		$s = array(
			'tabs'                 => $tabs,
			'selected_icon'        => array(
				'value'   => 'fas fa-plus',
				'library' => 'fa-solid',
			),
			'selected_active_icon' => array(
				'value'   => 'fas fa-minus',
				'library' => 'fa-solid',
			),
			'icon_align'           => 'right',
			'title_padding'        => self::box( 18, 22 ),
			'content_padding'      => self::box( 4, 22, 20, 22 ),
			// Los enlaces de las respuestas con el color de la marca, no el del tema.
			'custom_css'           => 'selector .elementor-tab-content a{color:var(--e-global-color-primary);font-weight:600;}',
		);
		$this->color( $s, 'border_color', 'line' );
		$this->color( $s, 'title_background', 'white' );
		$this->color( $s, 'content_background_color', 'white' );
		$this->color( $s, 'title_color', 'text' );
		$this->color( $s, 'tab_active_color', 'primary' );
		$this->color( $s, 'content_color', 'text' );
		return $this->widget( 'accordion', $s );
	}

	/** Una sección de primer nivel con fondo (`primary`, `white` o `light`) y los widgets o filas adentro. */
	public function section( array $children, string $bg, int $pad, string $anchor ): array {
		$flat = array();
		foreach ( $children as $child ) {
			if ( isset( $child['__rows'] ) ) {
				foreach ( $child['__rows'] as $row ) {
					$flat[] = $row;
				}
			} else {
				$flat[] = $child;
			}
		}
		$children = $flat;
		$s        = array(
			'background_background' => 'classic',
			'_element_id'           => $anchor,
		);
		$this->color( $s, 'background_color', $bg );
		if ( 'containers' === $this->variant ) {
			$s += array(
				'content_width'  => 'boxed',
				'boxed_width'    => self::px( 1180 ),
				'flex_direction' => 'column',
				'flex_gap'       => array(
					'unit'     => 'px',
					'size'     => 20,
					'column'   => '20',
					'row'      => '20',
					'isLinked' => true,
				),
				'padding'        => self::box( $pad, 20 ),
				'padding_mobile' => self::box( (int) round( $pad * 0.6 ), 16 ),
			);
			return array(
				'id'       => $this->id(),
				'elType'   => 'container',
				'settings' => $s,
				'elements' => array_values( $children ),
				'isInner'  => false,
			);
		}
		$s += array(
			'content_width'  => self::px( 1180 ),
			'padding'        => self::box( $pad, 20 ),
			'padding_mobile' => self::box( (int) round( $pad * 0.6 ), 16 ),
		);
		return array(
			'id'       => $this->id(),
			'elType'   => 'section',
			'settings' => $s,
			'elements' => array( $this->column( $children, 100, array() ) ),
			'isInner'  => false,
		);
	}

	private function column( array $children, $size, array $style, bool $inner = false ): array {
		return array(
			'id'       => $this->id(),
			'elType'   => 'column',
			'settings' => array( '_column_size' => $size, '_inline_size' => null ) + $style,
			'elements' => array_values( $children ),
			'isInner'  => $inner,
		);
	}

	/** Celdas en filas de `$per_row` (en celular, de dos o de una); `card` les pone fondo blanco, borde y relleno. */
	public function grid( array $cells, int $per_row, string $style ): array {
		$per_row = max( 1, min( 4, $per_row ) );
		$card    = array();
		if ( 'card' === $style ) {
			$card = array(
				'background_background' => 'classic',
				'background_color'      => '#FFFFFF',
				'border_border'         => 'solid',
				'border_width'          => self::box( 1 ),
				'border_color'          => '#E6E6E6',
				'border_radius'         => self::box( 12 ),
				'padding'               => self::box( 16 ),
			);
		}
		if ( 'containers' === $this->variant ) {
			$width = round( 100 / $per_row - ( $per_row > 1 ? 2.2 : 0 ), 2 );
			$items = array();
			foreach ( $cells as $cell ) {
				$items[] = array(
					'id'       => $this->id(),
					'elType'   => 'container',
					'settings' => array(
						'content_width'         => 'full',
						'flex_direction'        => 'column',
						'flex_justify_content'  => 'flex-start',
						'width'                 => array(
							'unit'  => '%',
							'size'  => $width,
							'sizes' => array(),
						),
						'width_tablet'          => array(
							'unit'  => '%',
							'size'  => $per_row > 2 ? 47 : $width,
							'sizes' => array(),
						),
						'width_mobile'          => array(
							'unit'  => '%',
							'size'  => 'card' === $style && $per_row > 2 ? 47 : 100,
							'sizes' => array(),
						),
						'flex_gap'              => array(
							'unit'     => 'px',
							'size'     => 8,
							'column'   => '8',
							'row'      => '8',
							'isLinked' => true,
						),
					) + ( $card ? $card : array( 'padding' => self::box( 8 ) ) ),
					'elements' => array_values( $cell ),
					'isInner'  => true,
				);
			}
			return array(
				'id'       => $this->id(),
				'elType'   => 'container',
				'settings' => array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_justify_content' => 'center',
					'flex_align_items'     => 'stretch',
					'flex_gap'             => array(
						'unit'     => 'px',
						'size'     => 20,
						'column'   => '20',
						'row'      => '20',
						'isLinked' => true,
					),
					'padding'              => self::box( 0 ),
				),
				'elements' => $items,
				'isInner'  => true,
			);
		}
		// Secciones: una sección interior por fila (la última, centrada con su ancho).
		$rows = array();
		foreach ( array_chunk( $cells, $per_row ) as $chunk ) {
			$cols = array();
			foreach ( $chunk as $cell ) {
				$cols[] = $this->column( $cell, (int) floor( 100 / $per_row ), $card ? $card : array(), true );
			}
			$rows[] = array(
				'id'       => $this->id(),
				'elType'   => 'section',
				'settings' => array( 'gap' => 'extended', 'structure' => (string) ( count( $cols ) * 10 ), 'margin' => self::box( 0, 0, 20, 0 ) ),
				'elements' => $cols,
				'isInner'  => true,
			);
		}
		// Varias filas: la sección de primer nivel lleva una columna; las filas van dentro, una tras otra.
		return 1 === count( $rows ) ? $rows[0] : array(
			'__rows' => $rows,
		);
	}
}
