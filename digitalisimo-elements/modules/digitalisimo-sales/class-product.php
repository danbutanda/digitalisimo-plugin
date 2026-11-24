<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * La landing de un producto de la tienda (`create-page` con `product_id`): la venta corta (02) llenada con los datos
 * reales del producto y de la oferta, sin que el modelo tenga que mapear placeholders.
 *
 * - `read()` lee el producto de la tienda (nombre, descripciones, imágenes, precios, atributos, peso y medidas, reseñas
 *   aprobadas, fecha de fin de la rebaja, si se puede comprar) y lo deja en un arreglo puro;
 * - `offer()` valida la oferta pedida (`price`, `regular_price`, `ends`, `label`) contra el producto;
 * - `page()` arma la plantilla: la portada con el nombre y la etiqueta de la oferta, los beneficios sacados de las listas
 *   de la descripción, «Conócelo» con la descripción y la galería, características (atributos, peso, medidas), reseñas
 *   reales (o sin testimonios), la oferta con el precio normal tachado, el de oferta, el ahorro y el botón que agrega el
 *   producto al carrito y lleva a pagar, la garantía si el producto la dice (o la política de reembolso del sitio) y
 *   preguntas frecuentes con lo que la tienda sabe (pago, envío, cierre). Los bloques de curso (módulos, lecciones,
 *   bonus, meses sin intereses, persona detrás) no se usan.
 *
 * Nada se inventa: lo que el producto o la tienda no dicen no se escribe (se quita o se informa como pendiente). El
 * precio sólo va en la oferta (el precio se revela tarde). El resto lo termina `Finisher` (logo, enlaces reales,
 * WhatsApp) al armar con `Builder`.
 */
final class Product {
	const TEMPLATE = 'venta-corta';

	/** Los widgets de la venta corta (02) que cambia, por su ID en las dos variantes. */
	const W = array(
		'h1'            => 'c86e9e0',
		'lead'          => '1aae8c5',
		'benefits_h'    => '995cf7c',
		'benefit_cols'  => array( '072e4a8', 'de426dd', '1800656' ),
		'benefit_boxes' => array( 'ffd811f', '9b6db45', '85f472e' ),
		'benefits_btn'  => 'c9f33ab',
		'about_img'     => '44b9b9a',
		'about_h'       => '24aa122',
		'about_sub'     => 'b6f8a41',
		'about_text'    => 'f6b28ec',
		'specs_col'     => 'b41d377',
		'specs_h'       => 'ca5845e',
		'specs_list'    => 'd5ed14f',
		'more_col'      => '509b6d1',
		'more_h'        => 'eecc2c6',
		'more_list'     => '0063275',
		'reviews'       => '49fde63',
		'offer_img'     => 'a2b8151',
		'offer_name'    => '7bbaa1d',
		'offer_list'    => 'de74a3f',
		'offer_regular' => '46fb68e',
		'offer_price'   => '0976b82',
		'offer_months'  => '03beff7',
		'offer_btn'     => '589cbf5',
		'offer_sub'     => 'd0c5dfe',
		'offer_close'   => '4545214',
		'offer_pay'     => '6856f2d',
		'offer_pay_img' => 'f8dcbe3',
		'g_title'       => '6d8c5f0',
		'g_text'        => 'c9d26cb',
		'g_divider'     => 'b73d0f0',
		'faq'           => '7713728',
		'wa_btn'        => '52c6da5',
		'foot_contact'  => '172fcdb',
		'foot_notice'   => '252648f',
	);

	const BUY_TEXT = '¡Sí, lo quiero ahora!';

	const VIEW_TEXT = 'Ver el producto';

	/**
	 * El producto como arreglo puro, o un error si no existe o no está publicado.
	 *
	 * @return array|\WP_Error
	 */
	public static function read( int $id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return self::error( 'digitalisimo_sales_no_store', 'product_id necesita la tienda activa en este sitio.' );
		}
		$p = $id > 0 ? wc_get_product( $id ) : null;
		if ( ! $p || ! is_object( $p ) ) {
			return self::error( 'digitalisimo_sales_product', sprintf( 'No existe el producto %d en la tienda.', $id ) );
		}
		if ( 'publish' !== $p->get_status() ) {
			return self::error( 'digitalisimo_sales_product', sprintf( 'El producto %d no está publicado (%s): la landing llevaría a una ficha que nadie puede ver.', $id, $p->get_status() ) );
		}
		$password = method_exists( $p, 'get_post_password' ) ? (string) $p->get_post_password() : ( function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_password', $id ) : '' );
		if ( '' !== $password ) {
			return self::error( 'digitalisimo_sales_product', sprintf( 'El producto %d está protegido con contraseña (privado): la landing llevaría a una ficha que el público no puede ver.', $id ) );
		}
		$images = array();
		foreach ( array_merge( array( (int) $p->get_image_id() ), array_map( 'intval', (array) $p->get_gallery_image_ids() ) ) as $image_id ) {
			if ( $image_id > 0 && ! isset( $images[ $image_id ] ) && function_exists( 'wp_get_attachment_image_url' ) ) {
				$url = (string) wp_get_attachment_image_url( $image_id, 'large' );
				if ( '' !== $url ) {
					$images[ $image_id ] = array(
						'id'  => $image_id,
						'url' => $url,
						'alt' => function_exists( 'get_post_meta' ) ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
					);
				}
			}
		}
		$attributes = array();
		foreach ( (array) $p->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_visible' ) || ! $attribute->get_visible() ) {
				continue;
			}
			$name   = (string) $attribute->get_name();
			$values = $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ? wc_get_product_terms( $id, $name, array( 'fields' => 'names' ) ) : (array) $attribute->get_options();
			$label  = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( $name, $p ) : $name;
			$values = array_filter( array_map( 'strval', (array) $values ) );
			if ( '' !== $label && $values ) {
				$attributes[] = array(
					'name'  => $label,
					'value' => implode( ', ', $values ),
				);
			}
		}
		$reviews = array();
		if ( function_exists( 'get_comments' ) ) {
			foreach ( (array) get_comments(
				array(
					'post_id' => $id,
					'status'  => 'approve',
					'type'    => 'review',
					'parent'  => 0, // Sin las respuestas de la tienda a una reseña.
					'number'  => 20,
				)
			) as $comment ) {
				if ( ! empty( $comment->comment_parent ) ) {
					continue;
				}
				$rating = function_exists( 'get_comment_meta' ) ? (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true ) : 0;
				if ( $rating < 4 ) {
					continue; // Sólo reseñas con calificación de 4 o 5 estrellas: sin calificación no es una recomendación.
				}
				$reviews[] = array(
					'content'  => (string) ( $comment->comment_content ?? '' ),
					'author'   => (string) ( $comment->comment_author ?? '' ),
					'rating'   => $rating,
					'verified' => function_exists( 'get_comment_meta' ) ? (bool) get_comment_meta( (int) $comment->comment_ID, 'verified', true ) : false,
				);
			}
		}
		$sale_to = method_exists( $p, 'get_date_on_sale_to' ) ? $p->get_date_on_sale_to() : null;
		$type    = (string) $p->get_type();
		$regular = self::number( $p->get_regular_price() );
		$sale    = self::number( $p->get_sale_price() );
		$price   = self::number( $p->get_price() );
		$from    = false;
		if ( 'variable' === $type ) {
			// El precio más bajo y, sólo si es de esa misma variación, su precio normal: nunca el normal de una variación con
			// el rebajado de otra. Con variaciones de distinto precio, «Desde».
			$regular = null;
			$sale    = null;
			$prices  = method_exists( $p, 'get_variation_prices' ) ? (array) $p->get_variation_prices( true ) : array();
			$active  = array_filter( array_map( array( self::class, 'number' ), (array) ( $prices['price'] ?? array() ) ) );
			if ( $active ) {
				// El más barato y, si empatan, la variación de ID menor (orden estable también en PHP 7.4).
				$vid = null;
				foreach ( $active as $id_var => $amount ) {
					if ( null === $vid || $amount < $active[ $vid ] || ( $amount === $active[ $vid ] && (int) $id_var < (int) $vid ) ) {
						$vid = $id_var;
					}
				}
				$price   = $active[ $vid ];
				$normal  = self::number( $prices['regular_price'][ $vid ] ?? null );
				$regular = $normal;
				$sale    = null !== $normal && $normal > $price ? $price : null;
				$from    = count( array_unique( array_map( 'strval', $active ) ) ) > 1;
			} else {
				$from = true;
			}
		}
		return array(
			'id'            => $id,
			'type'          => $type,
			'name'          => (string) $p->get_name(),
			'short'         => (string) $p->get_short_description(),
			'description'   => (string) $p->get_description(),
			'permalink'     => function_exists( 'get_permalink' ) ? (string) get_permalink( $id ) : '',
			'regular_price' => $regular,
			'sale_price'    => $sale,
			'price'         => $price,
			'price_from'    => $from,
			'virtual'       => ( method_exists( $p, 'is_virtual' ) && $p->is_virtual() ) || ( method_exists( $p, 'is_downloadable' ) && $p->is_downloadable() ),
			'sale_to'       => $sale_to && is_object( $sale_to ) && method_exists( $sale_to, 'getTimestamp' ) ? (int) $sale_to->getTimestamp() : 0,
			'images'        => array_values( $images ),
			'attributes'    => $attributes,
			'weight'        => method_exists( $p, 'has_weight' ) && $p->has_weight() ? trim( $p->get_weight() . ' ' . ( function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_weight_unit' ) : '' ) ) : '',
			'dimensions'    => method_exists( $p, 'has_dimensions' ) && $p->has_dimensions() && function_exists( 'wc_format_dimensions' ) ? html_entity_decode( (string) wc_format_dimensions( $p->get_dimensions( false ) ), ENT_QUOTES, 'UTF-8' ) : '',
			'purchasable'   => (bool) $p->is_purchasable(),
			'in_stock'      => (bool) $p->is_in_stock(),
			'reviews'       => $reviews,
		);
	}

	/** Un precio de la entrada o de la tienda (1997, «1,997», «1997.50») como número, o null. */
	public static function number( $value ): ?float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value > 0 ? (float) $value : null;
		}
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value || ! preg_match( Builder::PRICE, $value ) ) {
			return null;
		}
		$number = (float) str_replace( ',', '', $value );
		return $number > 0 ? $number : null;
	}

	/** «10,000» o «1,997.50». */
	public static function money( float $value ): string {
		return number_format( $value, abs( $value - round( $value ) ) < 0.005 ? 0 : 2, '.', ',' );
	}

	/**
	 * La oferta validada: `price` (el de la landing), `regular` (el tachado, o null), `ends` («AAAA-MM-DD HH:MM» o ''),
	 * `label`, `sources` (de dónde salió cada dato) y `store_price` (lo que cobra hoy la tienda).
	 *
	 * @param array $product `read()`.
	 * @param mixed $offer   La entrada `offer`.
	 * @param array $env     `timezone` (\DateTimeZone) y `now`.
	 * @return array|\WP_Error
	 */
	public static function offer( array $product, $offer, array $env ) {
		if ( null !== $offer && ! is_array( $offer ) ) {
			return self::error( 'digitalisimo_sales_offer', 'offer debe ser un objeto con price, regular_price, ends y label.' );
		}
		$offer   = (array) $offer;
		$unknown = array_diff( array_keys( $offer ), array( 'price', 'regular_price', 'ends', 'label' ) );
		if ( $unknown ) {
			return self::error( 'digitalisimo_sales_offer', sprintf( 'offer: parámetros desconocidos: %s. Admite price, regular_price, ends y label.', implode( ', ', $unknown ) ) );
		}
		$sources = array();
		foreach ( array( 'price', 'regular_price' ) as $key ) {
			if ( isset( $offer[ $key ] ) && '' !== $offer[ $key ] && null === self::number( $offer[ $key ] ) ) {
				return self::error( 'digitalisimo_sales_offer', sprintf( 'offer.%s: sólo la cifra, mayor que cero (p. ej. 7500 o 7,500); la página ya escribe la moneda.', $key ) );
			}
		}
		$price   = isset( $offer['price'] ) && '' !== $offer['price'] ? self::number( $offer['price'] ) : null;
		$regular = isset( $offer['regular_price'] ) && '' !== $offer['regular_price'] ? self::number( $offer['regular_price'] ) : null;
		if ( null !== $price ) {
			$sources['precio de oferta'] = 'la oferta que se pidió';
		} elseif ( null !== $product['sale_price'] ) {
			$price                       = $product['sale_price'];
			$sources['precio de oferta'] = 'el precio rebajado del producto en la tienda';
		} else {
			$price = $product['price'] ?? $product['regular_price'];
			if ( null !== $price ) {
				$sources['precio'] = 'el precio del producto en la tienda';
			}
		}
		if ( null === $price ) {
			return self::error( 'digitalisimo_sales_offer', 'El producto no tiene precio en la tienda: pasa offer.price.' );
		}
		$mixed = ! empty( $product['price_from'] ) && isset( $offer['price'] ); // Un precio pedido para un producto con variaciones de distinto precio.
		if ( null === $regular && isset( $offer['price'] ) && ! $mixed && null !== $product['regular_price'] && $price > $product['regular_price'] ) {
			return self::error( 'digitalisimo_sales_offer', sprintf( 'El precio de oferta (%s) es mayor que el precio normal del producto (%s): revisa la cifra o pasa regular_price.', self::money( $price ), self::money( (float) $product['regular_price'] ) ) );
		}
		if ( null !== $regular ) {
			$sources['precio normal'] = 'la oferta que se pidió';
		} elseif ( ! $mixed && null !== $product['regular_price'] && $product['regular_price'] > $price ) {
			$regular                  = $product['regular_price'];
			$sources['precio normal'] = 'el precio normal del producto en la tienda';
		}
		if ( null !== $regular && $regular <= $price ) {
			if ( isset( $offer['regular_price'] ) || isset( $offer['price'] ) ) {
				return self::error( 'digitalisimo_sales_offer', sprintf( 'El precio de oferta (%s) debe ser menor que el precio normal (%s).', self::money( $price ), self::money( $regular ) ) );
			}
			$regular = null;
		}
		$ends = '';
		if ( isset( $offer['ends'] ) && '' !== $offer['ends'] ) {
			$raw = is_string( $offer['ends'] ) ? trim( $offer['ends'] ) : '';
			if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $raw ) ) {
				$raw .= ' 23:59';
			}
			if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/', $raw ) ) {
				return self::error( 'digitalisimo_sales_offer', 'offer.ends: «AAAA-MM-DD HH:MM» (o «AAAA-MM-DD», hasta las 23:59) en la hora del sitio.' );
			}
			$ends                   = $raw;
			$sources['fin de la oferta'] = 'la oferta que se pidió';
		} elseif ( ! empty( $product['sale_to'] ) && (int) $product['sale_to'] > (int) ( $env['now'] ?? time() ) && ! isset( $offer['price'] ) ) {
			$tz   = isset( $env['timezone'] ) && $env['timezone'] instanceof \DateTimeZone ? $env['timezone'] : new \DateTimeZone( 'UTC' );
			$ends = ( new \DateTimeImmutable( '@' . (int) $product['sale_to'] ) )->setTimezone( $tz )->format( 'Y-m-d H:i' );
			$sources['fin de la oferta'] = 'la fecha de fin de la rebaja del producto en la tienda';
		}
		$label = '';
		if ( isset( $offer['label'] ) && '' !== $offer['label'] ) {
			$label = is_string( $offer['label'] ) ? trim( sanitize_text_field( $offer['label'] ) ) : '';
			if ( '' === $label || strlen( $label ) > 80 || '' !== Builder::external_url( $label, '' ) || Builder::looks_like_price( $label ) ) {
				return self::error( 'digitalisimo_sales_offer', 'offer.label: un nombre corto de la promoción (p. ej. «Black Friday»), sin enlaces ni precios.' );
			}
			$sources['etiqueta'] = 'la oferta que se pidió';
		}
		return array(
			'price'       => $price,
			'regular'     => $regular,
			'ends'        => $ends,
			'label'       => $label,
			'sources'     => $sources,
			'store_price' => $product['price'],
			'from'        => ! empty( $product['price_from'] ) && ! isset( $offer['price'] ),
		);
	}

	/** Texto plano de un fragmento de HTML (sin etiquetas, entidades decodificadas, espacios juntos). */
	public static function plain( string $html ): string {
		$text = html_entity_decode( strip_tags( (string) preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** Los elementos de las listas de un texto (`<li>` o renglones con viñeta), sin repetir. */
	public static function list_items( string $html ): array {
		$items = array();
		if ( preg_match_all( '#<li[^>]*>(.*?)</li>#is', $html, $all ) ) {
			foreach ( $all[1] as $item ) {
				$items[] = self::plain( $item );
			}
		} else {
			$text = (string) preg_replace( '#<br\s*/?>|</p>|</div>#i', "\n", $html );
			foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
				$line = trim( html_entity_decode( strip_tags( $line ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( preg_match( '/^(?:[-*•·▪✓✔►➤]|\d+[.)])\s*(.+)$/u', $line, $m ) ) {
					$items[] = self::plain( $m[1] );
				}
			}
		}
		$out = array();
		foreach ( $items as $item ) {
			$item = rtrim( $item, ' .;' );
			if ( self::is_heading( $item, true ) ) {
				continue;
			}
			if ( '' !== $item && strlen( $item ) <= 300 && ! in_array( mb_strtolower_safe( $item ), array_map( __NAMESPACE__ . '\\mb_strtolower_safe', $out ), true ) ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * ¿Un renglón es un encabezado suelto de la descripción («Beneficios», «Características:», «Detalles del producto»)?
	 * Corto (hasta cinco palabras), sin punto final ni verbo conjugado común, o terminado en dos puntos. Un encabezado no
	 * es un beneficio ni un párrafo: no se copia.
	 */
	public static function is_heading( string $text, bool $item = false ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return true;
		}
		if ( preg_match( '/:\s*$/u', $text ) && count( preg_split( '/\s+/u', $text ) ) <= 6 ) {
			return true;
		}
		$words = preg_split( '/\s+/u', rtrim( $text, '.!?' ) );
		if ( count( $words ) > 4 || preg_match( '/[.!?…]$/u', $text ) || preg_match( '/[0-9]/', $text ) ) {
			return false;
		}
		// Un verbo frecuente en una frase corta la hace contenido («Filtra el agua», «Incluye cartucho»), no un título.
		if ( preg_match( '/\b(es|son|tiene|tienen|incluye|incluyen|filtra|elimina|ahorra|protege|funciona|sirve|mide|pesa|trae|viene|permite|ofrece|cuenta|garantiza|reduce|mejora|eleva|evita|dura)\b/iu', $text ) && count( $words ) > 1 ) {
			return false;
		}
		if ( preg_match( '/^(beneficios?|caracter[ií]sticas?|ventajas?|especificaciones?( t[eé]cnicas)?|detalles?( del producto)?|descripci[oó]n|incluye|contenido( de la caja)?|qu[eé] incluye|modo de uso|instrucciones|ficha t[eé]cnica|informaci[oó]n( adicional)?|medidas|dimensiones|materiales?|garant[ií]a|notas?|importante|ideal para|usos?)(\s+(principales|clave|del producto))?$/iu', rtrim( $text, '.!?' ) ) ) {
			return true;
		}
		// En un párrafo (no en una lista: «Acero inoxidable» es una característica), un título corto sin artículos.
		return ! $item && count( $words ) <= 3 && ! preg_match( '/[,;]/', $text ) && 1 === preg_match( '/^\p{Lu}/u', $text ) && ! preg_match( '/\b(el|la|los|las|un|una|de|del|con|sin|para|por|y)\b/iu', $text );
	}

	/** Los párrafos de un texto que no son listas ni encabezados sueltos, en texto plano. */
	public static function paragraphs( string $html ): array {
		$html = (string) preg_replace( '#<(ul|ol)[^>]*>.*?</\1>#is', "\n", $html );
		$html = (string) preg_replace( '#<h[1-6][^>]*>.*?</h[1-6]>#is', "\n", $html ); // Los encabezados no son párrafos.
		$html = (string) preg_replace( '#<br\s*/?>|</p>|</div>|</h[1-6]>#i', "\n", $html );
		$out  = array();
		foreach ( preg_split( '/\n\s*\n|\n/', $html ) as $part ) {
			$text = self::plain( $part );
			if ( '' !== $text && ! preg_match( '/^(?:[-*•·▪✓✔►➤]|\d+[.)])\s/u', $text ) && ! self::is_heading( $text ) ) {
				$out[] = $text;
			}
		}
		return $out;
	}

	/** La primera oración de un texto (hasta unos 180 caracteres). */
	public static function first_sentence( string $text ): string {
		$text = trim( $text );
		if ( preg_match( '/^(.{20,180}?[.!?])(\s|$)/u', $text, $m ) ) {
			return $m[1];
		}
		return function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 180 ? rtrim( mb_substr( $text, 0, 177 ) ) . '…' : $text;
	}

	/** Lo que el producto dice de su garantía (una oración o un atributo), o ''. */
	public static function warranty( array $product ): string {
		foreach ( $product['attributes'] as $attribute ) {
			if ( preg_match( '/garant[ií]a/iu', $attribute['name'] ) && ! self::denies_warranty( $attribute['value'] ) && ! self::denies_warranty( $attribute['name'] . ': ' . $attribute['value'] ) ) {
				return $attribute['name'] . ': ' . $attribute['value'];
			}
		}
		$text = self::plain( (string) preg_replace( '#</(p|li|div|h[1-6])>|<br\s*/?>#i', ". \n", $product['short'] . "\n" . $product['description'] ) );
		foreach ( preg_split( '/(?<=[.!?])\s+/u', $text ) as $sentence ) {
			$sentence = trim( $sentence, " .\t\n" );
			if ( preg_match( '/garant[ií]a/iu', $sentence ) && strlen( $sentence ) <= 240 && ! self::denies_warranty( $sentence ) ) {
				return $sentence . '.';
			}
		}
		return '';
	}

	/**
	 * ¿El texto niega la garantía? Sólo si la negación va sobre la garantía: «no incluye garantía», «sin garantía»,
	 * «ningún tipo de garantía», «garantía: ninguna», «garantía: -», «la garantía no aplica». No cuentan «envío sin costo
	 * y garantía de 12 meses» ni «no te preocupes, tienes garantía» (la negación es de otra palabra).
	 */
	public static function denies_warranty( string $text ): bool {
		$glue = '(?:incluye|incluyen|tiene|tienen|cuenta|cuentan|con|ofrece|ofrecemos|aplica|hay|de|del|la|el|una|un|su|sus|tipo|ningún|alguna|ningun[ao]?|se|da|damos|otorga)';
		$before = '/\b(?:no|sin|ni|nunca|ning[uú]n[ao]?|carece\s+de|excluye)\s+(?:' . $glue . '\s+){0,3}garant[ií]as?\b/iu';
		$after  = '/\bgarant[ií]as?\s*(?::|-|=)?\s*(?:-+\s*$|n\/a\b|ning[uú]n[ao]?\b|nula\b|anulada\b|no\b(?!\s+te\b))/iu';
		$value  = '/^\s*(?:no|sin|ning[uú]n[ao]?|n\/a|-+|0)\s*\.?\s*$/iu';
		return 1 === preg_match( $before, $text ) || 1 === preg_match( $after, $text ) || 1 === preg_match( $value, $text );
	}

	/** «Título» y «descripción» de un beneficio («Título: descripción», o una frase corta). */
	private static function split_benefit( string $item ): array {
		if ( preg_match( '/^([^:]{3,60}):\s*(.{3,})$/u', $item, $m ) ) {
			return array( trim( $m[1] ), trim( $m[2] ) );
		}
		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $item ) : strlen( $item ) ) <= 70 ) {
			return array( $item, '' );
		}
		if ( preg_match( '/^(.{12,70}?)[,;–—-]\s+(.+)$/u', $item, $m ) ) {
			return array( trim( $m[1] ), trim( $m[2] ) );
		}
		return array( self::first_sentence( $item ), $item );
	}

	/**
	 * Arma la venta corta con el producto y la oferta.
	 *
	 * @param array $template La plantilla 02 (formato 0.4, con `content`).
	 * @param array $product  `read()`.
	 * @param array $offer    `offer()`.
	 * @param array $site     `Site::read()`.
	 * @return array|\WP_Error `template` (con `content` cambiado), `omit` (bloques que no aplican), `replacements`,
	 *                        `close_date`, `filled` (de dónde salió cada dato), `pending`, `buy_url` y `warnings`.
	 */
	public static function page( array $template, array $product, array $offer, array $site ) {
		$ids = array();
		self::collect_ids( $template['content'], $ids );
		foreach ( self::W as $role => $id ) {
			foreach ( (array) $id as $one ) {
				if ( ! isset( $ids[ $one ] ) ) {
					return self::error( 'digitalisimo_sales_product_template', sprintf( 'La plantilla de la venta corta cambió (falta %s, %s): actualiza Product::W.', $role, $one ), 500 );
				}
			}
		}
		$name     = trim( str_replace( array( '[', ']' ), array( '(', ')' ), $product['name'] ) );
		if ( '' === $name ) {
			return self::error( 'digitalisimo_sales_product', 'El producto no tiene nombre.' );
		}
		// Un corchete del producto no debe parecer un placeholder (Finisher quita lo que lo lleve).
		$e        = static function ( string $text ): string {
			return esc_html_safe( str_replace( array( '[', ']' ), array( '(', ')' ), $text ) );
		};
		$cur      = (string) ( $site['currency'] ?? 'MXN' );
		$sym      = (string) ( $site['currency_symbol'] ?? '$' );
		$fmt      = static function ( float $value ) use ( $cur, $sym ): string {
			return $sym . self::money( $value ) . ( '' !== $cur ? ' ' . $cur : '' );
		};
		$filled   = array(
			array(
				'field'  => 'nombre del producto',
				'source' => sprintf( 'producto %d de la tienda', $product['id'] ),
			),
		);
		foreach ( $offer['sources'] as $field => $source ) {
			$filled[] = array(
				'field'  => $field,
				'source' => $source,
			);
		}
		$pending  = array();
		$omit     = array();
		$warnings = array();
		$edits    = array();

		// Beneficios: las listas de la descripción corta y de la larga.
		$benefits = self::list_items( $product['short'] . "\n" . $product['description'] );
		$specs    = array();
		foreach ( $product['attributes'] as $attribute ) {
			if ( ! preg_match( '/garant[ií]a/iu', $attribute['name'] ) ) {
				$specs[] = $attribute['name'] . ': ' . $attribute['value'];
			}
		}
		if ( '' !== $product['weight'] ) {
			$specs[] = 'Peso: ' . $product['weight'];
		}
		if ( '' !== $product['dimensions'] && 'N/A' !== $product['dimensions'] ) {
			$specs[] = 'Medidas: ' . $product['dimensions'];
		}
		$paragraphs = array_merge( self::paragraphs( $product['short'] ), self::paragraphs( $product['description'] ) );
		$paragraphs = array_values( array_unique( $paragraphs ) );
		$lead       = $paragraphs ? self::first_sentence( $paragraphs[0] ) : '';

		// La compra: con el producto simple y disponible, al carrito y a pagar; si no, a su ficha.
		$checkout = (string) ( $site['links']['/pago/'] ?? '' );
		$cart     = (string) ( $site['links']['/carrito/'] ?? '' );
		$base     = '' !== $checkout ? $checkout : $cart;
		$direct   = 'simple' === $product['type'] && $product['purchasable'] && $product['in_stock'] && '' !== $base;
		$buy_url  = $direct ? $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'add-to-cart=' . (int) $product['id'] : (string) $product['permalink'];
		if ( ! $direct ) {
			$warnings[] = ! $product['in_stock'] ? 'El producto está agotado: el botón lleva a su ficha.' : 'El botón lleva a la ficha del producto (con opciones, o sin carrito o pago publicados en el sitio).';
		}
		$buy_text = $direct ? self::BUY_TEXT : self::VIEW_TEXT;
		$filled[] = array(
			'field'  => 'botón de compra',
			'source' => $direct ? 'agrega el producto al carrito y lleva a pagar' : 'la ficha del producto',
		);

		// 1 · La portada.
		$label             = $offer['label'];
		$edits[ self::W['h1'] ] = static function ( $el ) use ( $name, $label, $e ) {
			$el['settings']['title'] = $e( $name ) . ( '' !== $label ? '<br><span>' . $e( $label ) . '</span>' : '' );
			return $el;
		};
		$edits[ self::W['lead'] ] = static function ( $el ) use ( $lead, $e ) {
			if ( '' === $lead ) {
				return null;
			}
			$el['settings']['title'] = $e( $lead );
			return $el;
		};
		if ( '' !== $lead ) {
			$filled[] = array(
				'field'  => 'antetítulo',
				'source' => 'la descripción del producto',
			);
		}

		// 2 · Beneficios (hasta tres) o fuera.
		$top = array_slice( $benefits, 0, 3 );
		if ( ! $top ) {
			$omit[] = 'beneficios';
		} else {
			$filled[] = array(
				'field'  => 'beneficios',
				'source' => 'las listas de la descripción del producto',
			);
			$edits[ self::W['benefits_h'] ] = static function ( $el ) {
				$el['settings']['title'] = 'Lo que obtienes';
				return $el;
			};
			foreach ( self::W['benefit_boxes'] as $i => $box ) {
				if ( ! isset( $top[ $i ] ) ) {
					$edits[ self::W['benefit_cols'][ $i ] ] = static function () {
						return null;
					};
					continue;
				}
				list( $title, $desc ) = self::split_benefit( $top[ $i ] );
				$edits[ $box ]        = static function ( $el ) use ( $title, $desc, $e ) {
					$el['settings']['title_text']       = $e( $title );
					$el['settings']['description_text'] = $e( $desc );
					return $el;
				};
			}
			$edits[ self::W['benefits_btn'] ] = static function ( $el ) {
				$el['settings']['text'] = 'Ver la oferta';
				return $el;
			};
		}

		// 2b · «Conócelo»: la descripción y la galería del producto (en lugar de la persona detrás).
		$images = $product['images'];
		$about  = array_slice( $paragraphs, 0, 3 );
		if ( ! $images && ! $about ) {
			$omit[] = 'quien-esta-detras';
		} else {
			$edits[ self::W['about_h'] ]   = static function ( $el ) {
				$el['settings']['title'] = 'Conócelo';
				return $el;
			};
			$edits[ self::W['about_sub'] ] = static function ( $el ) use ( $name, $e ) {
				$el['settings']['title'] = $e( $name );
				return $el;
			};
			$edits[ self::W['about_text'] ] = static function ( $el ) use ( $about, $e ) {
				if ( ! $about ) {
					return null;
				}
				$el['settings']['editor'] = '<p>' . implode( '</p><p>', array_map( $e, $about ) ) . '</p>';
				return $el;
			};
			$edits[ self::W['about_img'] ] = static function ( $el ) use ( $images, $name ) {
				if ( ! $images ) {
					return null;
				}
				if ( 1 === count( $images ) ) {
					$el['settings']['image']          = self::image( $images[0], $name );
					$el['settings']['caption_source'] = '';
					return $el;
				}
				// La galería entera: un carrusel de imágenes, una a la vez, con puntos.
				return array(
					'id'         => $el['id'],
					'elType'     => 'widget',
					'widgetType' => 'image-carousel',
					'settings'   => array(
						'carousel'            => array_map(
							static function ( $image ) {
								return array(
									'id'  => (int) $image['id'],
									'url' => (string) $image['url'],
								);
							},
							$images
						),
						'thumbnail_size'      => 'large',
						'slides_to_show'      => '1',
						'navigation'          => 'dots',
						'image_stretch'       => 'yes',
						'autoplay'            => 'yes',
						'infinite'            => 'yes',
						'image_border_radius' => array(
							'unit'     => 'px',
							'top'      => '12',
							'right'    => '12',
							'bottom'   => '12',
							'left'     => '12',
							'isLinked' => true,
						),
					),
					'elements'   => array(),
				);
			};
			if ( $images ) {
				$filled[] = array(
					'field'  => 'imágenes',
					'source' => sprintf( '%d imágenes del producto (destacada y galería)', count( $images ) ),
				);
			}
			if ( $about ) {
				$filled[] = array(
					'field'  => 'descripción',
					'source' => 'la descripción del producto',
				);
			}
		}

		// 3 · Características (atributos, peso, medidas) y el resto de la lista de la descripción.
		$more = array_slice( $benefits, 3, 8 );
		if ( ! $specs && ! $more ) {
			$omit[] = 'para-quien-es';
		} else {
			$list = static function ( array $items ) use ( $e ): array {
				$out = array();
				foreach ( $items as $i => $item ) {
					$out[] = array(
						'text'          => $e( $item ),
						'selected_icon' => array(
							'value'   => 'fas fa-check-circle',
							'library' => 'fa-solid',
						),
						'_id'           => sprintf( 'p%06d', $i + 1 ),
					);
				}
				return $out;
			};
			$section = static function ( string $title, array $items ) use ( $list ) {
				return array(
					static function ( $el ) use ( $title ) {
						$el['settings']['title'] = $title;
						return $el;
					},
					static function ( $el ) use ( $items, $list ) {
						$el['settings']['icon_list'] = $list( $items );
						return $el;
					},
				);
			};
			if ( $specs ) {
				list( $edits[ self::W['specs_h'] ], $edits[ self::W['specs_list'] ] ) = $section( 'Características', array_slice( $specs, 0, 8 ) );
				$filled[] = array(
					'field'  => 'características',
					'source' => 'los atributos, el peso y las medidas del producto',
				);
			} else {
				$edits[ self::W['specs_col'] ] = static function () {
					return null;
				};
			}
			if ( $more ) {
				list( $edits[ self::W['more_h'] ], $edits[ self::W['more_list'] ] ) = $section( 'Además', $more );
			} else {
				$edits[ self::W['more_col'] ] = static function () {
					return null;
				};
			}
		}

		// 4 · Reseñas reales y aprobadas del producto, o sin testimonios.
		$reviews = array();
		foreach ( $product['reviews'] as $review ) {
			$text = self::plain( $review['content'] );
			if ( '' !== $text && '' !== trim( $review['author'] ) ) {
				$reviews[] = $review + array( 'text' => $text );
			}
		}
		$reviews = array_slice( $reviews, 0, 3 );
		if ( ! $reviews ) {
			$omit[] = 'testimonios';
		} else {
			$filled[] = array(
				'field'  => 'testimonios',
				'source' => sprintf( '%d reseñas aprobadas del producto', count( $reviews ) ),
			);
			$edits[ self::W['reviews'] ] = static function ( $el ) use ( $reviews, $e ) {
				$slides = array();
				foreach ( $reviews as $i => $review ) {
					$stars    = $review['rating'] > 0 ? str_repeat( '★', min( 5, $review['rating'] ) ) : '';
					$slides[] = array(
						'content' => $e( function_exists( 'mb_strlen' ) && mb_strlen( $review['text'] ) > 400 ? rtrim( mb_substr( $review['text'], 0, 397 ) ) . '…' : $review['text'] ),
						'name'    => $e( $review['author'] ),
						'title'   => trim( $stars . ( $review['verified'] ? ' · Compra verificada' : '' ), ' ·' ),
						'image'   => array(
							'url' => '',
							'id'  => '',
						),
						'_id'     => sprintf( 'r%06d', $i + 1 ),
					);
				}
				$el['settings']['slides']          = $slides;
				$el['settings']['slides_per_view'] = (string) min( 3, count( $slides ) );
				return $el;
			};
		}

		// 5 · La oferta.
		$price   = (float) $offer['price'];
		$regular = $offer['regular'];
		$first   = $images[0] ?? null;
		$edits[ self::W['offer_img'] ]  = static function ( $el ) use ( $first, $name ) {
			if ( ! $first ) {
				return null;
			}
			$el['settings']['image'] = self::image( $first, $name );
			return $el;
		};
		$edits[ self::W['offer_name'] ] = static function ( $el ) use ( $name, $e ) {
			$el['settings']['title'] = $e( $name );
			return $el;
		};
		$offer_items = array_slice( $benefits ? array_map( static function ( $b ) { return self::split_benefit( $b )[0]; }, $benefits ) : $specs, 0, 4 );
		$edits[ self::W['offer_list'] ] = static function ( $el ) use ( $offer_items, $e ) {
			if ( ! $offer_items ) {
				return null;
			}
			$items = array();
			foreach ( $offer_items as $i => $item ) {
				$items[] = array(
					'text'          => $e( $item ),
					'selected_icon' => array(
						'value'   => 'fas fa-check-circle',
						'library' => 'fa-solid',
					),
					'_id'           => sprintf( 'o%06d', $i + 1 ),
				);
			}
			$el['settings']['icon_list'] = $items;
			return $el;
		};
		$edits[ self::W['offer_regular'] ] = static function ( $el ) use ( $regular, $fmt ) {
			if ( null === $regular ) {
				return null;
			}
			$el['settings']['editor'] = '<p style="text-align:center">Precio normal: <del>' . $fmt( (float) $regular ) . '</del></p>';
			return $el;
		};
		$from = ! empty( $offer['from'] );
		$edits[ self::W['offer_price'] ] = static function ( $el ) use ( $price, $regular, $fmt, $from ) {
			$el['settings']['title'] = ( $from ? ( null === $regular ? 'Desde: ' : 'Hoy, desde: ' ) : ( null === $regular ? 'Precio: ' : 'Hoy: ' ) ) . '<span>' . $fmt( $price ) . '</span>';
			return $el;
		};
		$edits[ self::W['offer_months'] ] = static function () {
			return null; // Meses sin intereses: sólo si la pasarela los da, y eso no se sabe desde aquí.
		};
		$edits[ self::W['offer_btn'] ] = static function ( $el ) use ( $buy_url, $buy_text ) {
			$el['settings']['text']                = $buy_text;
			$el['settings']['link']['url']         = $buy_url;
			$el['settings']['link']['is_external'] = '';
			return $el;
		};
		$edits[ self::W['offer_sub'] ] = static function ( $el ) use ( $price, $regular, $fmt ) {
			if ( null === $regular ) {
				return null;
			}
			$saved                    = (float) $regular - $price;
			$el['settings']['editor'] = '<p style="text-align:center">Ahorras ' . $fmt( $saved ) . ' (' . (int) round( $saved * 100 / (float) $regular ) . ' %)</p>';
			return $el;
		};
		$ends = $offer['ends'];
		$hour = Site::timezone_label( (string) ( $site['timezone'] ?? '' ) ); // La zona real del sitio, la de close_date.
		$edits[ self::W['offer_close'] ] = static function ( $el ) use ( $ends, $hour ) {
			if ( '' === $ends ) {
				return null;
			}
			$el['settings']['editor'] = '<p style="text-align:center">⏰ Este precio termina el [día de cierre] de [mes de cierre] a las [hora de cierre] (' . $hour . ').</p>';
			return $el;
		};
		$plainify = static function ( $items ): array {
			return array_values(
				array_map(
					static function ( $item ) {
						return str_replace( array( '[', ']' ), array( '(', ')' ), (string) $item );
					},
					(array) $items
				)
			);
		};
		$gateways = $plainify( $site['gateways'] ?? array() );
		$edits[ self::W['offer_pay'] ] = static function ( $el ) use ( $gateways, $e ) {
			if ( ! $gateways ) {
				return null;
			}
			// Neutro: entre los medios activos puede haber transferencia o pago contra entrega, que no son «en línea».
			$el['settings']['editor'] = '<p style="text-align:center">💳 Formas de pago: ' . $e( self::enumerate( $gateways ) ) . '</p>';
			return $el;
		};
		$edits[ self::W['offer_pay_img'] ] = static function () {
			return null;
		};

		// 6 · Garantía: la del producto, o la política de reembolso real del sitio; si no hay, se informa.
		$warranty = self::warranty( $product );
		$refund   = '' !== (string) ( $site['links']['/politica-de-reembolso/'] ?? '' );
		if ( '' !== $warranty ) {
			$filled[] = array(
				'field'  => 'garantía',
				'source' => 'la descripción o los atributos del producto',
			);
			$edits[ self::W['g_title'] ] = static function ( $el ) {
				$el['settings']['title'] = 'Garantía';
				return $el;
			};
			$edits[ self::W['g_text'] ]  = static function ( $el ) use ( $warranty, $e ) {
				$el['settings']['editor'] = '<p style="text-align:center">' . $e( $warranty ) . '</p>';
				return $el;
			};
		} elseif ( $refund ) {
			$filled[] = array(
				'field'  => 'garantía',
				'source' => 'la política de reembolso del sitio',
			);
			$edits[ self::W['g_title'] ] = static function ( $el ) {
				$el['settings']['title'] = 'Cambios y devoluciones';
				return $el;
			};
			$edits[ self::W['g_text'] ]  = static function ( $el ) {
				$el['settings']['editor'] = '<p style="text-align:center">Consulta las condiciones en nuestra <a href="/politica-de-reembolso/">política de reembolso</a>.</p>';
				return $el;
			};
		} else {
			$pending[] = array(
				'block'    => 'garantia-y-preguntas',
				'required' => true,
				'widget'   => 'heading',
				'field'    => 'title',
				'missing'  => 'la garantía: ni el producto ni el sitio (política de reembolso) la dicen; agrégala al producto o al sitio, o escríbela en el editor',
			);
			foreach ( array( 'g_title', 'g_text', 'g_divider' ) as $role ) {
				$edits[ self::W[ $role ] ] = static function () {
					return null;
				};
			}
		}

		// Preguntas frecuentes con lo que la tienda sabe.
		// Envíos: sólo los que llevan el producto (sin recoger en tienda) y sólo si el producto se envía (no virtual ni
		// descargable). «Gratis» sólo con un envío gratis sin condiciones; uno con mínimo o cupón se dice con su condición.
		// Los métodos de varias zonas con el mismo nombre y la misma condición se nombran una vez.
		$options = array();
		$free    = 0;
		$paid    = 0;
		foreach ( empty( $product['virtual'] ) ? (array) ( $site['shipping'] ?? array() ) : array() as $method ) {
			$method = is_array( $method ) ? $method : array( 'title' => (string) $method );
			if ( in_array( (string) ( $method['method'] ?? '' ), Site::PICKUP, true ) ) {
				continue;
			}
			$condition = (string) ( $method['condition'] ?? '' );
			$text      = str_replace( array( '[', ']' ), array( '(', ')' ), (string) $method['title'] ) . ( '' !== $condition ? ' (' . $condition . ')' : '' );
			if ( isset( $options[ $text ] ) ) {
				continue;
			}
			$options[ $text ] = true;
			if ( ! empty( $method['free'] ) ) {
				++$free;
			} else {
				++$paid;
			}
		}
		$shipping = array_keys( $options );
		$tabs     = array(
			array( '¿Cómo lo compro?', $direct ? 'Pulsa «' . self::BUY_TEXT . '»: el producto se agrega a tu carrito y, al terminar tu pedido, eliges la forma de pago.' : 'Pulsa «' . self::VIEW_TEXT . '» y elige tus opciones en la tienda.' ),
		);
		if ( $gateways ) {
			$tabs[] = array( '¿Cómo puedo pagar?', 'Con ' . self::enumerate( $gateways ) . '.' );
		}
		if ( $shipping ) {
			if ( 0 === $paid ) {
				$tabs[] = array( '¿Hacen envíos?', 'Sí, el envío es gratis' . ( count( $shipping ) > 1 || 'envío gratis' !== mb_strtolower_safe( $shipping[0] ) ? ' (' . self::enumerate( $shipping ) . ')' : '' ) . '.' );
			} elseif ( $free ) {
				$tabs[] = array( '¿Hacen envíos?', 'Sí. Opciones de envío: ' . self::enumerate( $shipping ) . '. Hay envío gratis; el costo de las demás opciones se muestra al pagar.' );
			} else {
				$tabs[] = array( '¿Hacen envíos?', 'Sí. Opciones de envío: ' . self::enumerate( $shipping ) . '. El costo se calcula al pagar.' );
			}
		}
		if ( '' !== $ends ) {
			$tabs[] = array( '¿Hasta cuándo es este precio?', 'Hasta el [día de cierre] de [mes de cierre] a las [hora de cierre] (' . $hour . ').' );
		}
		$tabs[] = array( '¿Y si tengo otra duda?', 'Escríbenos por WhatsApp.' );
		// Los textos son del kit (con los placeholders de cierre, que llena Builder) y los nombres de la tienda, sin corchetes.
		$edits[ self::W['faq'] ] = static function ( $el ) use ( $tabs ) {
			$out = array();
			foreach ( $tabs as $i => $tab ) {
				$out[] = array(
					'tab_title'   => $tab[0],
					'tab_content' => '<p>' . esc_html_safe( $tab[1] ) . '</p>',
					'_id'         => sprintf( 'f%06d', $i + 1 ),
				);
			}
			$el['settings']['tabs'] = $out;
			return $el;
		};

		// El mensaje del botón de WhatsApp con el nombre del producto, escrito aquí (no como reemplazo: un nombre como «Bomba
		// 0.50 HP» o con «MXN» no es un precio y no debe pasar por la regla del precio fuera de la oferta).
		$edits[ self::W['wa_btn'] ] = static function ( $el ) use ( $name ) {
			$url = (string) ( $el['settings']['link']['url'] ?? '' );
			if ( false !== strpos( $url, '[producto]' ) ) {
				$el['settings']['link']['url'] = str_replace( '[producto]', rawurlencode( $name ), $url );
			}
			return $el;
		};

		// Pie: contacto sin correo de muestra y el aviso legal sin la parte de cursos (resultados e ingresos).
		$edits[ self::W['foot_contact'] ] = static function ( $el ) {
			$el['settings']['editor'] = '<p style="text-align:center">[Marca] · ¿Dudas? Escríbenos por WhatsApp</p>';
			return $el;
		};
		$edits[ self::W['foot_notice'] ] = static function ( $el ) {
			$el['settings']['editor'] = (string) preg_replace( '/Los resultados dependen.*?no típicos\.\s*/su', '', (string) $el['settings']['editor'] );
			return $el;
		};

		$template['content'] = self::apply( $template['content'], $edits );
		if ( null !== $offer['store_price'] && abs( (float) $offer['store_price'] - $price ) >= 0.005 ) {
			$warnings[] = sprintf( 'La tienda cobra hoy %s por este producto y la landing anuncia %s: pon el precio rebajado en el producto (con su fecha de fin) para que el carrito cobre lo mismo antes de publicar.', $fmt( (float) $offer['store_price'] ), $fmt( $price ) );
		}
		return array(
			'template'     => $template,
			'omit'         => array_values( array_unique( $omit ) ),
			'replacements' => array(),
			'close_date'   => $ends,
			'filled'       => $filled,
			'pending'      => $pending,
			'buy_url'      => $buy_url,
			'warnings'     => $warnings,
		);
	}

	/** El ajuste `image` de un widget con una imagen del producto. */
	private static function image( array $image, string $name ): array {
		return array(
			'url'    => (string) $image['url'],
			'id'     => (int) $image['id'],
			'alt'    => '' !== (string) ( $image['alt'] ?? '' ) ? (string) $image['alt'] : $name,
			'source' => 'library',
		);
	}

	/** «a, b y c». */
	private static function enumerate( array $items ): string {
		$items = array_values( $items );
		$last  = array_pop( $items );
		return $items ? implode( ', ', $items ) . ' y ' . $last : (string) $last;
	}

	/** Aplica los cambios por ID (un callable que devuelve el elemento o null para quitarlo) y reparte los anchos. */
	private static function apply( array $elements, array $edits ): array {
		$out  = array();
		$lost = false;
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id = (string) ( $element['id'] ?? '' );
			if ( isset( $edits[ $id ] ) ) {
				$element = call_user_func( $edits[ $id ], $element );
				if ( null === $element ) {
					$lost = true;
					continue;
				}
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) && $element['elements'] ) {
				$before              = count( $element['elements'] );
				$element['elements'] = self::apply( $element['elements'], $edits );
				if ( count( $element['elements'] ) < $before && $element['elements'] ) {
					$element = Finisher::rebalance( $element );
				}
			}
			$out[] = $element;
		}
		return $out;
	}

	private static function collect_ids( array $elements, array &$ids ): void {
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				if ( isset( $element['id'] ) ) {
					$ids[ (string) $element['id'] ] = true;
				}
				self::collect_ids( (array) ( $element['elements'] ?? array() ), $ids );
			}
		}
	}

	private static function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}

/** `mb_strtolower()` si existe. */
function mb_strtolower_safe( string $text ): string {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}
