<?php
defined( 'ABSPATH' ) || exit;

/**
 * La tarjeta de confirmación en palabras del dueño (caso plomerito 4 del 2026-11-27: «el asistente lo veo muy técnico,
 * debe ser fácil de usar para el usuario sin nada de conocimientos que sólo quiere un sitio o tienda web»; la tarjeta
 * mostraba JSON crudo, «action», «per_page», «Agrandar los datos (54 caracteres)» y una casilla de «no tiene vuelta
 * atrás» para una simple revisión).
 *
 * El texto lo arma ESTE servidor a partir de la herramienta, la acción y los datos que se ejecutarán (plantillas por
 * acción y un respaldo genérico llano), nunca el modelo: así la tarjeta no puede decir algo distinto de lo que hará.
 * - `text`: una frase, «Voy a cambiar en el producto «X» el precio de oferta a $7,500.».
 * - `undo`: sólo cuando de verdad se puede deshacer desde el Asistente («Puedes deshacerlo después.»).
 * - `final`: una frase simple cuando de verdad no tiene vuelta atrás (borrar, un correo al cliente, dinero).
 * - `ack`: la casilla extra, SÓLO en lo que borra o mueve dinero (el servidor la exige al confirmar).
 * - `warnings`: lo arriesgado de los datos, en llano (enlaces a otros sitios, código, invisibles, claves).
 * - `details`: para el soporte, plegado en «Ver detalles»: nombres legibles y valores completos, sin JSON.
 * Lo que no conoce sale con el respaldo genérico; si la entrada lleva algo que la plantilla no nombra, la frase lo dice
 * («y otros 2 datos (en «Ver detalles»)»), nunca lo calla.
 */
final class Digitalisimo_AI_Assistant_Card {
	/** Cómo se llama cada dato para el dueño (productos, variaciones, catálogo, cupones y lo común). */
	const FIELDS = array(
		'name'                => 'el nombre',
		'title'               => 'el título',
		'regular_price'       => 'el precio normal',
		'sale_price'          => 'el precio de oferta',
		'price'               => 'el precio',
		'description'         => 'la descripción',
		'short_description'   => 'la descripción corta',
		'sku'                 => 'el código del producto',
		'stock_quantity'      => 'las existencias',
		'manage_stock'        => 'el control de existencias',
		'stock_status'        => 'la disponibilidad',
		'backorders'          => 'la venta sin existencias',
		'weight'              => 'el peso',
		'dimensions'          => 'las medidas',
		'length'              => 'el largo',
		'width'               => 'el ancho',
		'height'              => 'el alto',
		'images'              => 'las fotos',
		'image'               => 'la foto',
		'categories'          => 'las categorías',
		'tags'                => 'las etiquetas',
		'brands'              => 'las marcas',
		'featured'            => 'si sale como destacado',
		'catalog_visibility'  => 'dónde se muestra',
		'date_on_sale_from'   => 'el inicio de la oferta',
		'date_on_sale_to'     => 'el fin de la oferta',
		'shipping_class'      => 'el tipo de envío',
		'tax_status'          => 'los impuestos',
		'tax_class'           => 'los impuestos',
		'attributes'          => 'las opciones (talla, color…)',
		'default_attributes'  => 'las opciones elegidas al abrirlo',
		'slug'                => 'la dirección',
		'button_text'         => 'el texto del botón',
		'external_url'        => 'el enlace a la otra tienda',
		'menu_order'          => 'el orden',
		'reviews_allowed'     => 'si acepta reseñas',
		'purchase_note'       => 'la nota después de comprar',
		'parent'              => 'la categoría de arriba',
		'display'             => 'cómo se muestra',
		'code'                => 'el código',
		'amount'              => 'el descuento',
		'discount_type'       => 'el tipo de descuento',
		'date_expires'        => 'la fecha en que vence',
		'usage_limit'         => 'cuántas veces se puede usar',
		'free_shipping'       => 'el envío gratis',
		'minimum_amount'      => 'la compra mínima',
		'maximum_amount'      => 'la compra máxima',
		'individual_use'      => 'si se puede juntar con otros cupones',
		'enabled'             => 'si está activo',
		'value'               => 'el valor',
		'status'              => 'el estado',
		'note'                => 'la nota',
		'reason'              => 'el motivo',
		'first_name'          => 'el nombre',
		'last_name'           => 'el apellido',
		'email'               => 'el correo',
		'billing'             => 'los datos de cobro',
		'shipping'            => 'los datos de envío',
		'settings'            => 'la configuración',
		'locations'           => 'los lugares',
		'meta_data'           => 'datos internos',
		'downloads'           => 'los archivos para descargar',
		// SEO.
		'keywords'            => 'las palabras clave',
		'canonical'           => 'la dirección principal',
		'noindex'             => 'si sale en Google',
		'nofollow'            => 'si Google sigue sus enlaces',
		'social_title'        => 'el título al compartir en redes',
		'social_description'  => 'la descripción al compartir en redes',
		'breadcrumb_title'    => 'su nombre en la ruta de navegación',
		'schema_type'         => 'el tipo de contenido que le dice a Google',
		'source'              => 'la dirección de antes',
		'target'              => 'la dirección nueva',
	);


	/** Nombres legibles de las claves de ruta, para «Ver detalles». */
	const DETAIL_NAMES = array(
		'action'       => 'Qué hace',
		'resource'     => 'Sobre qué',
		'product_id'   => 'Producto (número)',
		'variation_id' => 'Variación (número)',
		'term_id'      => 'Categoría o etiqueta (número)',
		'order_id'     => 'Pedido',
		'customer_id'  => 'Cliente (número)',
		'coupon_id'    => 'Cupón (número)',
		'post_id'      => 'Página (número)',
		'change_id'    => 'Cambio a deshacer',
		'force'        => 'Borrar para siempre',
		'per_page'     => 'Cuántos por página',
		'page'         => 'Página de resultados',
		'data'         => 'Datos',
		'items'        => 'Productos',
		'tracking'     => 'Rastreo',
		'operations'   => 'Partes',
	);

	/** Estados de un pedido, como los ve el dueño. */
	const ORDER_STATUS = array(
		'pending'    => 'Pendiente de pago',
		'processing' => 'Procesando',
		'on-hold'    => 'En espera',
		'completed'  => 'Completado',
		'cancelled'  => 'Cancelado',
		'refunded'   => 'Reembolsado',
		'failed'     => 'Fallido',
	);

	/** Lo que el respaldo genérico entiende como «borra» o «mueve dinero» en una acción desconocida. */
	const DELETE_VERBS = '/(^|[_\/-])(delete|remove|trash|purge|destroy|erase|drop|wipe|archive|borrar|eliminar)([_\/-]|$)/i';
	const MONEY_VERBS  = '/(^|[_\/-])(refund|charge|pay|payment|purchase|buy|checkout|capture|cobrar|pagar|comprar|reembols)/i';
	const MAIL_VERBS   = '/(^|[_\/-])(send|mail|email|notify|correo|enviar)([_\/-]|$)/i';

	/**
	 * La tarjeta de una acción.
	 *
	 * @param array      $entry      Entrada del catálogo (Digitalisimo_AI_Assistant_Tools::catalog()).
	 * @param mixed      $input      La entrada que se ejecutará.
	 * @param string     $kind       write o destructive.
	 * @param array|null $simulation Lo que devolvió la simulación (`dry_run`), si la hubo.
	 * @return array{text:string,undo:bool,final:string,ack:bool,ack_label:string,warnings:string[],details:array}
	 */
	public static function build( array $entry, $input, $kind = 'destructive', $simulation = null ) {
		$input = is_array( $input ) ? $input : array();
		$name  = (string) ( $entry['name'] ?? '' );
		$card  = null;
		try {
			$card = self::template( $name, $input, is_array( $simulation ) ? $simulation : null );
		} catch ( \Throwable $e ) {
			$card = null;
		}
		if ( ! is_array( $card ) || '' === trim( (string) ( $card['text'] ?? '' ) ) ) {
			$card = self::generic( $entry, $input );
		}
		$card = array_merge( array( 'undo' => false, 'final' => '', 'ack' => false, 'ack_label' => '' ), $card );
		if ( $card['ack'] && '' === $card['ack_label'] ) {
			$card['ack_label'] = 'Sí, entiendo y quiero hacerlo.';
		}
		$stats            = Digitalisimo_AI_Assistant_Tools::card_stats( $input );
		$card['warnings'] = self::warnings( $stats );
		$card['details']  = self::details( $entry, $input, is_array( $simulation ) ? $simulation : null );
		$card['text']     = self::sentence( (string) $card['text'] );
		return $card;
	}

	/** El texto que se muestra y se guarda, en una frase que empieza en mayúscula y termina en punto. */
	private static function sentence( $text ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			return '';
		}
		if ( ! preg_match( '/[.!?…»”)]$/u', $text ) ) {
			$text .= '.';
		}
		return $text;
	}

	/* ------------------------------------------------------------------ */
	/* Plantillas por herramienta y acción.                                 */
	/* ------------------------------------------------------------------ */

	private static function template( $name, array $input, $simulation ) {
		$action = strtolower( (string) ( $input['action'] ?? '' ) );
		switch ( $name ) {
			case 'digitalisimo-woo/products':
				return self::products( $action, $input, $simulation );
			case 'digitalisimo-woo/catalog':
				return self::catalog( $action, $input );
			case 'digitalisimo-woo/orders':
				return self::orders( $action, $input );
			case 'digitalisimo-woo/customers':
				return self::customers( $action, $input );
			case 'digitalisimo-woo/coupons':
				return self::coupons( $action, $input );
			case 'digitalisimo-woo/store-settings':
				return self::store_settings( $action, $input );
			case 'digitalisimo-woo/skydropx':
				return self::skydropx( $action, $input );
			case 'digitalisimo-hosting/settings':
				return 'update' === $action ? array( 'text' => 'Voy a cambiar ' . self::count_words( is_array( $input['data'] ?? null ) ? count( $input['data'] ) : 0, 'ajuste', 'ajustes' ) . ' de la venta de dominios y hosting de tu sitio' ) : null;
			case 'digitalisimo-hosting/domain-product':
				return self::domain_product( $action, $input );
			case 'digitalisimo-seo/update-post-seo':
				return self::seo_update( $input );
			case 'digitalisimo-seo/revert-post-seo':
				return array( 'text' => 'Voy a regresar a como estaba lo que Google muestra de ' . self::page( $input['post_id'] ?? 0 ) );
			case 'digitalisimo-seo/create-redirection':
				return self::redirection( $input );
			case 'digitalisimo-sales/create-page':
			case 'elementor/create-page':
				return self::create_page( $input );
			case 'elementor/publish-document':
				return array( 'text' => 'Voy a publicar ' . self::page( $input['post_id'] ?? ( $input['document_id'] ?? 0 ) ) . ': se verá en tu sitio en cuanto lo confirmes' );
			case 'elementor/update-page-settings':
				return self::page_settings( $input );
			case 'elementor/build-composition':
				$replace = 'replace_children' === ( $input['mode'] ?? '' );
				return array( 'text' => ( $replace ? 'Voy a reemplazar contenido de ' : 'Voy a agregar contenido a ' ) . self::page( $input['post_id'] ?? 0 ) );
			case 'elementor/manage-elements':
				return self::elements( $input );
			case 'elementor/create-preview-link':
				return array( 'text' => 'Voy a crear un enlace para que cualquiera que lo tenga pueda ver ' . self::page( $input['post_id'] ?? 0 ) . ' sin entrar a tu escritorio' );
			case 'elementor/manage-classes':
			case 'elementor/manage-default-styles':
			case 'elementor/manage-global-variable':
			case 'elementor/reorder-classes':
			case 'elementor/manage-component':
				return self::styles( $name, $input );
		}
		return null;
	}

	/** Productos y variaciones. */
	private static function products( $action, array $input, $simulation ) {
		$variation = 'variation' === strtolower( (string) ( $input['resource'] ?? '' ) );
		$data      = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		$what      = $variation ? 'una variación de ' . self::product( $input['product_id'] ?? 0 ) : 'el producto ' . self::product( $input['product_id'] ?? 0 );
		switch ( $action ) {
			case 'update':
				$parts = self::changes( $data, self::sim_from( $simulation ) );
				return array(
					'text' => 'Voy a cambiar en ' . $what . ' ' . $parts,
					'undo' => self::woo_undo( 'products', 'update', $data ),
				);
			case 'create':
				$label = '' !== (string) ( $data['name'] ?? '' ) ? ' «' . self::clip( (string) $data['name'], 60 ) . '»' : '';
				$draft = in_array( (string) ( $data['status'] ?? 'draft' ), array( 'draft', 'pending' ), true );
				$price = isset( $data['regular_price'] ) && '' !== (string) $data['regular_price'] ? ' de ' . self::money( $data['regular_price'] ) : '';
				return array(
					'text' => $variation ? 'Voy a crear una variación nueva' . $price . ' en ' . self::product( $input['product_id'] ?? 0 ) : 'Voy a crear el producto' . $label . $price . ( $draft ? ' en borrador: no se verá en tu tienda hasta que lo publiques' : ' y se verá en tu tienda' ),
					'undo' => self::woo_undo( 'products', 'create', $data ),
				);
			case 'delete':
				return self::deletion( $what, ! empty( $input['force'] ) || $variation );
			case 'batch':
				return self::batch( $data, 'producto', 'productos' );
			case 'undo':
				return array( 'text' => 'Voy a deshacer el último cambio que hice en tu tienda (el que me pediste deshacer)' );
		}
		return null;
	}

	/** Categorías, etiquetas, marcas, atributos, clases de envío y reseñas. */
	private static function catalog( $action, array $input ) {
		$names    = array(
			'category'       => array( 'la categoría', 'categoría' ),
			'tag'            => array( 'la etiqueta', 'etiqueta' ),
			'brand'          => array( 'la marca', 'marca' ),
			'attribute'      => array( 'la opción de producto', 'opción de producto' ),
			'attribute_term' => array( 'el valor de opción', 'valor de opción' ),
			'shipping_class' => array( 'el tipo de envío', 'tipo de envío' ),
			'review'         => array( 'la reseña', 'reseña' ),
		);
		$resource = strtolower( (string) ( $input['resource'] ?? 'category' ) );
		$n        = $names[ $resource ] ?? array( 'el elemento del catálogo', 'elemento del catálogo' );
		$data     = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		$label    = '' !== (string) ( $data['name'] ?? '' ) ? ' «' . self::clip( (string) $data['name'], 60 ) . '»' : '';
		$current  = self::term( $input['term_id'] ?? 0 );
		switch ( $action ) {
			case 'create':
				return array(
					'text' => 'Voy a crear ' . $n[0] . $label,
					'undo' => 'review' !== $resource && self::woo_undo( 'catalog', 'create', $data ),
				);
			case 'update':
				return array(
					'text' => 'Voy a cambiar en ' . $n[0] . ( '' !== $current ? ' «' . $current . '»' : '' ) . ' ' . self::changes( $data, array() ),
					'undo' => 'review' !== $resource && self::woo_undo( 'catalog', 'update', $data ),
				);
			case 'delete':
				return self::deletion( $n[0] . ( '' !== $current ? ' «' . $current . '»' : '' ), 'review' !== $resource || ! empty( $input['force'] ) );
			case 'undo':
				return array( 'text' => 'Voy a deshacer el último cambio que hice en tu catálogo (el que me pediste deshacer)' );
		}
		return null;
	}

	/** Pedidos, sus notas y reembolsos. */
	private static function orders( $action, array $input ) {
		$resource = strtolower( (string) ( $input['resource'] ?? 'order' ) );
		$order    = isset( $input['order_id'] ) ? 'el pedido ' . (int) $input['order_id'] : 'un pedido';
		$data     = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		if ( 'refund' === $resource ) {
			if ( 'create' === $action ) {
				$amount = isset( $data['amount'] ) ? ' de ' . self::money( $data['amount'] ) : '';
				$api    = ! empty( $input['api_refund'] ) || ! empty( $data['api_refund'] );
				return array(
					'text'      => 'Voy a registrar un reembolso' . $amount . ' en ' . $order,
					'final'     => $api ? 'Se le devuelve el dinero al cliente por su forma de pago: eso no tiene vuelta atrás.' : 'Sólo queda anotado: no se le devuelve dinero al cliente por su forma de pago.',
					'ack'       => true,
					'ack_label' => $api ? 'Sí, entiendo que se le devuelve el dinero.' : 'Sí, entiendo y quiero anotarlo.',
				);
			}
			if ( 'delete' === $action ) {
				return self::deletion( 'el reembolso anotado en ' . $order, true );
			}
			return null;
		}
		if ( 'note' === $resource ) {
			if ( 'create' === $action ) {
				$customer = ! empty( $data['customer_note'] );
				return array(
					'text'  => 'Voy a agregar ' . ( $customer ? 'una nota para el cliente' : 'una nota interna' ) . ' en ' . $order . ( isset( $data['note'] ) ? ': «' . self::clip( (string) $data['note'], 120 ) . '»' : '' ),
					'final' => $customer ? 'Al cliente le llega por correo: eso no se puede deshacer.' : '',
				);
			}
			if ( 'delete' === $action ) {
				return self::deletion( 'una nota de ' . $order, true );
			}
			return null;
		}
		switch ( $action ) {
			case 'update':
				if ( isset( $data['status'] ) && 1 === count( $data ) ) {
					$status = strtolower( (string) preg_replace( '/^wc-/', '', (string) $data['status'] ) );
					$label  = self::ORDER_STATUS[ $status ] ?? (string) $data['status'];
					$out    = array( 'text' => 'Voy a marcar ' . $order . ' como «' . $label . '»' );
					if ( in_array( $status, array( 'processing', 'completed', 'on-hold', 'refunded', 'cancelled' ), true ) ) {
						$out['final'] = 'Tu tienda le avisa al cliente por correo: eso no se puede deshacer.';
					}
					if ( 'refunded' === $status ) {
						$out['ack']       = true;
						$out['ack_label'] = 'Sí, entiendo que queda como reembolsado.';
					}
					return $out;
				}
				return array( 'text' => 'Voy a cambiar en ' . $order . ' ' . self::changes( $data, array() ) );
			case 'create':
				return array(
					'text'  => 'Voy a crear un pedido nuevo' . ( ! empty( $data['set_paid'] ) ? ' marcado como pagado' : '' ),
					'final' => 'Tu tienda puede avisarle al cliente por correo.',
				);
			case 'delete':
				return self::deletion( $order, ! empty( $input['force'] ) );
		}
		return null;
	}

	private static function customers( $action, array $input ) {
		$data = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		$who  = isset( $input['customer_id'] ) ? self::user( $input['customer_id'] ) : '';
		switch ( $action ) {
			case 'create':
				return array(
					'text'  => 'Voy a crear la cuenta de cliente' . ( isset( $data['email'] ) ? ' de ' . self::clip( (string) $data['email'], 80 ) : '' ),
					'final' => 'Al cliente le llega un correo de bienvenida: eso no se puede deshacer.',
				);
			case 'update':
				return array( 'text' => 'Voy a cambiar en la cuenta de ' . ( '' !== $who ? '«' . $who . '»' : 'ese cliente' ) . ' ' . self::changes( $data, array() ) );
			case 'delete':
				return self::deletion( 'la cuenta de ' . ( '' !== $who ? '«' . $who . '»' : 'ese cliente' ), true );
		}
		return null;
	}

	private static function coupons( $action, array $input ) {
		$data  = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		$code  = '' !== (string) ( $data['code'] ?? '' ) ? '«' . self::clip( (string) $data['code'], 40 ) . '»' : '';
		$saved = isset( $input['coupon_id'] ) ? self::title( $input['coupon_id'] ) : '';
		$which = '' !== $saved ? 'el cupón «' . $saved . '»' : 'el cupón';
		switch ( $action ) {
			case 'create':
				$off = '';
				if ( isset( $data['amount'] ) ) {
					$off = 'percent' === ( $data['discount_type'] ?? 'fixed_cart' ) ? ' de ' . self::number( $data['amount'] ) . ' % de descuento' : ' de ' . self::money( $data['amount'] ) . ' de descuento';
				}
				return array(
					'text' => 'Voy a crear el cupón ' . ( '' !== $code ? $code : '' ) . $off,
					'undo' => self::woo_undo( 'coupons', 'create', $data ),
				);
			case 'update':
				return array(
					'text' => 'Voy a cambiar en ' . $which . ' ' . self::changes( $data, array() ),
					'undo' => self::woo_undo( 'coupons', 'update', $data ),
				);
			case 'delete':
				return self::deletion( $which, ! empty( $input['force'] ) );
			case 'batch':
				return self::batch( $data, 'cupón', 'cupones' );
			case 'undo':
				return array( 'text' => 'Voy a deshacer el último cambio que hice en tus cupones (el que me pediste deshacer)' );
		}
		return null;
	}

	private static function store_settings( $action, array $input ) {
		$resource = strtolower( (string) ( $input['resource'] ?? '' ) );
		$data     = is_array( $input['data'] ?? null ) ? $input['data'] : array();
		if ( 'payment_gateway' === $resource && 'update' === $action ) {
			$gateway = self::clip( (string) ( $input['gateway_id'] ?? '' ), 40 );
			if ( array_key_exists( 'enabled', $data ) && 1 === count( $data ) ) {
				return array( 'text' => ( self::truthy( $data['enabled'] ) ? 'Voy a activar' : 'Voy a desactivar' ) . ' la forma de pago «' . self::gateway( $gateway ) . '» en tu tienda' );
			}
			return array( 'text' => 'Voy a cambiar la forma de pago «' . self::gateway( $gateway ) . '» de tu tienda: ' . self::changes( $data, array() ) );
		}
		$names = array(
			'setting'                => 'un ajuste de tu tienda',
			'shipping_zone'          => 'una zona de envío',
			'shipping_zone_location' => 'los lugares de una zona de envío',
			'shipping_zone_method'   => 'una forma de envío',
			'tax_class'              => 'un tipo de impuesto',
			'tax_rate'               => 'una tasa de impuesto',
		);
		$what = $names[ $resource ] ?? 'la configuración de tu tienda';
		switch ( $action ) {
			case 'create':
				return array( 'text' => 'Voy a crear ' . $what . ( isset( $data['name'] ) ? ' «' . self::clip( (string) $data['name'], 60 ) . '»' : '' ) );
			case 'update':
				return array( 'text' => 'Voy a cambiar ' . $what . ( $data ? ': ' . self::changes( $data, array() ) : '' ) );
			case 'delete':
				return self::deletion( $what, true );
			case 'batch':
				return array( 'text' => 'Voy a cambiar varios ajustes de tu tienda a la vez' );
		}
		return null;
	}

	private static function skydropx( $action, array $input ) {
		switch ( $action ) {
			case 'update_products':
				$items  = is_array( $input['items'] ?? null ) ? $input['items'] : array();
				$fields = array();
				foreach ( $items as $item ) {
					foreach ( array( 'weight' => 'el peso', 'length' => 'las medidas', 'width' => 'las medidas', 'height' => 'las medidas', 'shipping_class' => 'el tipo de envío' ) as $key => $label ) {
						if ( is_array( $item ) && array_key_exists( $key, $item ) && ! in_array( $label, $fields, true ) ) {
							$fields[] = $label;
						}
					}
				}
				$one = 1 === count( $items ) && is_array( $items[0] ?? null );
				return array( 'text' => 'Voy a poner ' . ( $fields ? self::join( $fields ) : 'los datos de envío' ) . ' a ' . ( $one ? self::product( $items[0]['product_id'] ?? 0 ) : self::count_words( count( $items ), 'producto', 'productos' ) ) . ' para que Skydropx pueda cotizar su envío' );
			case 'set_tracking':
				$tracking = is_array( $input['tracking'] ?? null ) ? $input['tracking'] : array();
				$mail     = ! empty( $input['customer_note'] );
				return array(
					'text'  => 'Voy a anotar el número de rastreo' . ( isset( $tracking['number'] ) ? ' «' . self::clip( (string) $tracking['number'], 60 ) . '»' : '' ) . ( isset( $tracking['company'] ) ? ' de ' . self::clip( (string) $tracking['company'], 40 ) : '' ) . ' en el pedido ' . (int) ( $input['order_id'] ?? 0 ) . ( isset( $input['order_status'] ) ? ' y marcarlo como «' . ( self::ORDER_STATUS[ preg_replace( '/^wc-/', '', (string) $input['order_status'] ) ] ?? (string) $input['order_status'] ) . '»' : '' ),
					'final' => $mail ? 'Al cliente le llega un correo con el aviso: eso no se puede deshacer.' : '',
				);
			case 'resync':
				return array( 'text' => 'Voy a volver a mandarle a Skydropx el pedido ' . (int) ( $input['order_id'] ?? 0 ) );
		}
		return null;
	}

	private static function domain_product( $action, array $input ) {
		$product = self::product( $input['product_id'] ?? 0 );
		if ( 'mark' === $action ) {
			$tld = trim( (string) ( $input['tld'] ?? '' ) );
			$ext = '' === $tld || '*' === $tld ? 'cualquier dominio' : 'los dominios ' . self::clip( $tld, 20 );
			return array( 'text' => 'Voy a usar ' . $product . ' para vender ' . ( 'transfer' === ( $input['kind'] ?? '' ) ? 'la transferencia de ' : '' ) . $ext );
		}
		if ( 'unmark' === $action ) {
			return array( 'text' => 'Voy a dejar de usar ' . $product . ' para vender dominios' );
		}
		return null;
	}

	/** SEO de una página: lo que Google muestra de ella. */
	private static function seo_update( array $input ) {
		$page  = self::page( $input['post_id'] ?? 0 );
		$parts = array();
		$known = array( 'post_id' );
		if ( array_key_exists( 'noindex', $input ) ) {
			$known[] = 'noindex';
			$parts[] = self::truthy( $input['noindex'] ) ? 'ocultar de Google ' . $page : 'volver a mostrar en Google ' . $page;
		}
		$texts = array();
		foreach ( array( 'title' => 'el título que muestra Google', 'description' => 'la descripción que muestra Google', 'keywords' => 'las palabras clave', 'social_title' => 'el título al compartirla en redes', 'social_description' => 'la descripción al compartirla en redes', 'breadcrumb_title' => 'su nombre en la ruta de navegación' ) as $key => $label ) {
			if ( array_key_exists( $key, $input ) ) {
				$known[] = $key;
				$value   = trim( (string) $input[ $key ] );
				$texts[] = '' === $value ? 'regresar ' . $label . ' a lo automático' : $label . ' a «' . self::clip( $value, 160 ) . '»';
			}
		}
		if ( $texts ) {
			$parts[] = 'cambiar en ' . $page . ' ' . self::join( $texts );
		}
		if ( array_key_exists( 'nofollow', $input ) ) {
			$known[] = 'nofollow';
			$parts[] = self::truthy( $input['nofollow'] ) ? 'pedirle a Google que no siga los enlaces de ' . $page : 'dejar que Google siga los enlaces de ' . $page;
		}
		if ( array_key_exists( 'canonical', $input ) ) {
			$known[] = 'canonical';
			$value   = trim( (string) $input['canonical'] );
			$parts[] = '' === $value ? 'regresar a lo automático la dirección principal de ' . $page : 'decirle a Google que la dirección principal de ' . $page . ' es ' . self::clip( $value, 120 );
		}
		if ( array_key_exists( 'schema_type', $input ) ) {
			$known[] = 'schema_type';
			$types   = array( '' => 'automático', 'Article' => 'artículo', 'WebPage' => 'página', 'FAQPage' => 'preguntas frecuentes', 'Service' => 'servicio', 'Product' => 'producto', 'LocalBusiness' => 'negocio local', 'Person' => 'persona' );
			$parts[] = 'decirle a Google que ' . $page . ' es de tipo «' . ( $types[ (string) $input['schema_type'] ] ?? (string) $input['schema_type'] ) . '»';
		}
		if ( ! $parts ) {
			return null;
		}
		$other = count( array_diff( array_keys( $input ), $known ) );
		return array(
			'text' => 'Voy a ' . self::join( $parts ) . ( $other ? ' y ' . self::count_words( $other, 'otro dato', 'otros datos', '' ) . ' (en «Ver detalles»)' : '' ),
			'undo' => true,
		);
	}

	private static function redirection( array $input ) {
		$from = self::address( (string) ( $input['source'] ?? '' ) );
		$code = (int) ( $input['code'] ?? 301 );
		if ( in_array( $code, array( 410, 451 ), true ) ) {
			return array( 'text' => 'Voy a avisarle a Google que la dirección ' . $from . ' ya no existe' );
		}
		$to   = self::address( (string) ( $input['target'] ?? '' ) );
		$mode = (string) ( $input['comparison'] ?? 'exact' );
		$head = array(
			'start'    => 'todas las direcciones que empiezan con ',
			'end'      => 'todas las direcciones que terminan en ',
			'contains' => 'todas las direcciones que contienen ',
			'regex'    => 'las direcciones que cumplen el patrón ',
		);
		return array( 'text' => 'Voy a hacer que quien entre a ' . ( $head[ $mode ] ?? '' ) . $from . ' llegue a ' . $to );
	}

	private static function create_page( array $input ) {
		if ( isset( $input['post_id'] ) && is_numeric( $input['post_id'] ) ) {
			return array( 'text' => 'Voy a rehacer tu borrador ' . self::page( $input['post_id'] ) . ' encima de los cambios que le hiciste' );
		}
		$title = trim( (string) ( $input['title'] ?? '' ) );
		return array( 'text' => 'Voy a crear la página ' . ( '' !== $title ? '«' . self::clip( $title, 80 ) . '» ' : '' ) . 'en borrador: no se verá en tu sitio hasta que la publiques' );
	}

	private static function page_settings( array $input ) {
		$page     = self::page( $input['post_id'] ?? 0 );
		$settings = is_array( $input['settings'] ?? null ) ? $input['settings'] : array_diff_key( $input, array( 'post_id' => 1 ) );
		$status   = (string) ( $settings['post_status'] ?? ( $settings['status'] ?? '' ) );
		if ( 'publish' === $status ) {
			return array( 'text' => 'Voy a publicar ' . $page . ': se verá en tu sitio en cuanto lo confirmes' );
		}
		return array( 'text' => 'Voy a cambiar en ' . $page . ' ' . self::changes( $settings, array() ) );
	}

	private static function elements( array $input ) {
		$ops     = is_array( $input['operations'] ?? null ) ? $input['operations'] : array();
		$deletes = 0;
		foreach ( $ops as $op ) {
			if ( is_array( $op ) && 'delete' === ( $op['action'] ?? ( $op['op'] ?? '' ) ) ) {
				++$deletes;
			}
		}
		$page = self::page( $input['post_id'] ?? 0 );
		$text = 'Voy a cambiar ' . self::count_words( count( $ops ) - $deletes, 'parte', 'partes', 'una' ) . ' de ' . $page;
		if ( $deletes ) {
			$text = ( count( $ops ) > $deletes ? $text . ' y quitar ' : 'Voy a quitar ' ) . self::count_words( $deletes, 'parte', 'partes', 'una' ) . ( count( $ops ) > $deletes ? '' : ' de ' . $page );
			return array(
				'text'      => $text,
				'ack'       => true,
				'ack_label' => 'Sí, entiendo que se quita de la página.',
			);
		}
		return array( 'text' => $text );
	}

	private static function styles( $name, array $input ) {
		$deletes = 0;
		foreach ( (array) ( $input['operations'] ?? array() ) as $op ) {
			if ( is_array( $op ) && in_array( (string) ( $op['action'] ?? '' ), array( 'delete', 'archive' ), true ) ) {
				++$deletes;
			}
		}
		$text = 'elementor/manage-component' === $name ? 'Voy a cambiar un bloque que se repite en varias páginas de tu sitio' : 'Voy a cambiar los estilos generales de tu sitio: se nota en todas las páginas que los usan';
		if ( $deletes ) {
			return array( 'text' => $text . ' (y quitar ' . self::count_words( $deletes, 'estilo o bloque', 'estilos o bloques' ) . ')', 'ack' => true, 'ack_label' => 'Sí, entiendo que se quita.' );
		}
		return array( 'text' => $text );
	}

	/** Lo que el servidor no sabe nombrar: una frase llana con lo que se sabe, y lo demás en «Ver detalles». */
	private static function generic( array $entry, array $input ) {
		$label  = trim( (string) preg_replace( '/^[^:]{1,40}:\s*/u', '', (string) ( $entry['label'] ?? '' ) ) );
		$where  = 0 === strpos( (string) ( $entry['name'] ?? '' ), 'digitalisimo-woo/' ) || 0 === strpos( (string) ( $entry['name'] ?? '' ), 'woocommerce/' ) ? 'tu tienda' : 'tu sitio';
		$verb   = strtolower( (string) ( $input['action'] ?? '' ) . ' ' . (string) ( $entry['name'] ?? '' ) );
		$out    = array( 'text' => 'Voy a hacer un cambio en ' . $where . ( '' !== $label ? ' («' . self::clip( lcfirst( $label ), 80 ) . '»)' : '' ) . '. Lo que lleva está en «Ver detalles»' );
		if ( preg_match( self::DELETE_VERBS, $verb ) ) {
			$out['final']     = 'Borra algo de ' . $where . ': puede no tener vuelta atrás.';
			$out['ack']       = true;
			$out['ack_label'] = 'Sí, entiendo que se borra.';
		} elseif ( preg_match( self::MONEY_VERBS, $verb ) ) {
			$out['final']     = 'Tiene que ver con dinero: puede no tener vuelta atrás.';
			$out['ack']       = true;
			$out['ack_label'] = 'Sí, entiendo y quiero hacerlo.';
		} elseif ( preg_match( self::MAIL_VERBS, $verb ) ) {
			$out['final'] = 'Manda un aviso o un correo: eso no se puede deshacer.';
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Piezas.                                                              */
	/* ------------------------------------------------------------------ */

	/** Borrar: una frase simple y la casilla. */
	private static function deletion( $what, $permanent ) {
		return array(
			'text'      => 'Voy a borrar ' . $what,
			'final'     => $permanent ? 'Se borra para siempre: no se puede recuperar.' : 'Va a la papelera: de ahí se puede recuperar.',
			'ack'       => true,
			'ack_label' => $permanent ? 'Sí, entiendo que se borra para siempre.' : 'Sí, entiendo que se borra.',
		);
	}

	/** Un lote de productos o cupones: cuántos se crean, cambian y borran (lo que se borra en lote es para siempre). */
	private static function batch( array $data, $one, $many ) {
		$parts  = array();
		$create = is_array( $data['create'] ?? null ) ? count( $data['create'] ) : 0;
		$update = is_array( $data['update'] ?? null ) ? $data['update'] : array();
		$delete = is_array( $data['delete'] ?? null ) ? count( $data['delete'] ) : 0;
		if ( $create ) {
			$parts[] = 'crear ' . self::count_words( $create, $one, $many );
		}
		if ( $update ) {
			$fields = array();
			foreach ( $update as $item ) {
				foreach ( is_array( $item ) ? array_keys( $item ) : array() as $key ) {
					if ( 'id' !== $key && ! in_array( $key, $fields, true ) ) {
						$fields[] = $key;
					}
				}
			}
			$labels  = array_map( array( __CLASS__, 'field' ), array_slice( $fields, 0, 4 ) );
			$parts[] = 'cambiar ' . ( $labels ? self::join( array_unique( $labels ) ) . ( count( $fields ) > 4 ? ' y otros datos' : '' ) . ' de ' : '' ) . self::count_words( count( $update ), $one, $many );
		}
		if ( $delete ) {
			$parts[] = 'borrar ' . self::count_words( $delete, $one, $many );
		}
		$out = array( 'text' => 'Voy a ' . ( $parts ? self::join( $parts ) : 'cambiar varios ' . $many . ' a la vez' ) );
		if ( $delete ) {
			$out['final']     = 'Lo que se borra en grupo se borra para siempre: no se puede recuperar.';
			$out['ack']       = true;
			$out['ack_label'] = 'Sí, entiendo que se borra para siempre.';
		}
		return $out;
	}

	/**
	 * «el precio de oferta a $7,500 y el peso a 4 kg» (con «de $10,000» si la simulación dio el valor de antes). Lo que
	 * no tiene nombre se cuenta: «y otros 2 datos (en «Ver detalles»)».
	 */
	private static function changes( array $data, array $from ) {
		$parts = array();
		$other = 0;
		foreach ( $data as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, array( 'id' ), true ) ) {
				continue;
			}
			if ( ! isset( self::FIELDS[ $key ] ) ) {
				++$other;
				continue;
			}
			$parts[] = self::change( $key, $value, $from[ $key ] ?? null );
		}
		if ( ! $parts ) {
			return $other ? self::count_words( $other, 'dato', 'datos' ) . ' (en «Ver detalles»)' : 'unos datos (en «Ver detalles»)';
		}
		return self::join( $parts ) . ( $other ? ' y ' . self::count_words( $other, 'otro dato', 'otros datos', '' ) . ' (en «Ver detalles»)' : '' );
	}

	private static function change( $key, $value, $before ) {
		$label = self::FIELDS[ $key ];
		$was   = null !== $before && is_scalar( $before ) && '' !== (string) $before ? self::value( $key, $before ) : '';
		if ( in_array( $key, array( 'sale_price', 'regular_price', 'price', 'date_on_sale_from', 'date_on_sale_to', 'date_expires' ), true ) && ( null === $value || '' === $value ) ) {
			return 'quitar ' . $label;
		}
		if ( 'status' === $key ) {
			$states = array( 'publish' => 'publicado', 'draft' => 'borrador', 'pending' => 'pendiente de revisión', 'private' => 'privado' );
			return 'el estado a «' . ( $states[ (string) $value ] ?? (string) $value ) . '»';
		}
		$shown = self::value( $key, $value );
		if ( null === $shown ) {
			return $label . ( is_string( $value ) && '' !== $value ? ' por un texto nuevo' : '' );
		}
		return $label . ( '' !== $was && $was !== $shown ? ' de ' . $was : '' ) . ' a ' . $shown;
	}

	/** Un valor como lo lee el dueño, o null si no cabe en la frase (va en «Ver detalles»). */
	private static function value( $key, $value ) {
		if ( in_array( $key, array( 'regular_price', 'sale_price', 'price', 'minimum_amount', 'maximum_amount' ), true ) && is_numeric( str_replace( ',', '', (string) $value ) ) ) {
			return self::money( $value );
		}
		if ( 'weight' === $key && is_numeric( $value ) ) {
			return self::number( $value ) . ' ' . self::unit( 'woocommerce_weight_unit', 'kg' );
		}
		if ( 'dimensions' === $key && is_array( $value ) ) {
			$size = array();
			foreach ( array( 'length', 'width', 'height' ) as $side ) {
				if ( isset( $value[ $side ] ) && '' !== (string) $value[ $side ] ) {
					$size[] = self::number( $value[ $side ] );
				}
			}
			return $size ? implode( ' × ', $size ) . ' ' . self::unit( 'woocommerce_dimension_unit', 'cm' ) : null;
		}
		if ( in_array( $key, array( 'length', 'width', 'height' ), true ) && is_numeric( $value ) ) {
			return self::number( $value ) . ' ' . self::unit( 'woocommerce_dimension_unit', 'cm' );
		}
		if ( 'stock_status' === $key ) {
			$states = array( 'instock' => '«disponible»', 'outofstock' => '«agotado»', 'onbackorder' => '«sobre pedido»' );
			return $states[ (string) $value ] ?? '«' . self::clip( (string) $value, 40 ) . '»';
		}
		if ( is_bool( $value ) || in_array( $key, array( 'featured', 'manage_stock', 'enabled', 'reviews_allowed', 'free_shipping', 'individual_use', 'noindex', 'nofollow' ), true ) ) {
			return self::truthy( $value ) ? 'sí' : 'no';
		}
		if ( in_array( $key, array( 'images', 'categories', 'tags', 'brands', 'attributes', 'downloads' ), true ) && is_array( $value ) ) {
			return (string) count( $value ) . ' en total';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return self::number( $value );
		}
		if ( is_string( $value ) ) {
			$plain = trim( (string) preg_replace( '/\s+/u', ' ', self::strip( $value ) ) );
			return '' !== $plain && self::length( $plain ) <= 80 && false === strpos( $value, "\n" ) ? '«' . $plain . '»' : null;
		}
		return null;
	}

	/** Lo que dio la simulación de un update, campo => valor de antes (sin secretos). */
	private static function sim_from( $simulation ) {
		$out     = array();
		$would   = is_array( $simulation['would'] ?? null ) ? $simulation['would'] : ( is_array( $simulation ) ? $simulation : array() );
		$changes = is_array( $would['changes'] ?? null ) ? $would['changes'] : array();
		foreach ( $changes as $change ) {
			if ( is_array( $change ) && isset( $change['field'] ) && empty( $change['masked'] ) && array_key_exists( 'from', $change ) ) {
				$out[ (string) $change['field'] ] = $change['from'];
			}
		}
		return $out;
	}

	/**
	 * ¿Se puede deshacer desde el Asistente? Sólo en la tienda de Elements con su registro de cambios: productos, catálogo
	 * y cupones creados o cambiados sin metadatos ni opciones, y con la escritura de esa herramienta permitida (en «Sólo
	 * lectura» el Asistente escribe pero no deshace: no se promete).
	 */
	private static function woo_undo( $tool, $action, array $data ) {
		$settings = 'Digitalisimo\\Elements\\Woo\\Mcp\\Settings';
		$changes  = 'Digitalisimo\\Elements\\Woo\\Mcp\\Changes';
		if ( ! class_exists( $settings ) || ! class_exists( $changes ) || ! method_exists( $settings, 'can_write' ) || ! in_array( $tool, (array) constant( $changes . '::TOOLS' ), true ) ) {
			return false;
		}
		foreach ( array( 'meta_data', 'email_restrictions', 'used_by', 'type', 'attributes', 'default_attributes' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return false;
			}
		}
		try {
			return in_array( $action, array( 'create', 'update' ), true ) && (bool) call_user_func( array( $settings, 'can_write' ), $tool );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/** Lo arriesgado de los datos, en llano. */
	private static function warnings( array $st ) {
		$out = array();
		if ( $st['hosts'] ) {
			$out[] = 'Lleva enlaces a otros sitios: ' . implode( ', ', array_slice( array_keys( $st['hosts'] ), 0, 6 ) ) . '. Si no los pediste tú, di que no.';
		}
		if ( $st['code'] ) {
			$out[] = 'Lleva código que se ejecutaría en tu página. Si no lo pediste tú, di que no.';
		}
		if ( $st['invisible'] ) {
			$out[] = 'Lleva letras invisibles o muchos espacios seguidos (marcados en «Ver detalles»). Si no lo pediste tú, di que no.';
		}
		if ( $st['hidden'] ) {
			$out[] = 'Lleva contraseñas o claves (no se muestran). Si no las pediste tú, di que no.';
		}
		return $out;
	}

	/**
	 * «Ver detalles», para el soporte: qué herramienta, qué hace y cada dato con un nombre legible y su valor completo
	 * (las claves secretas ocultas y los invisibles marcados), más lo que dijo la simulación. Sin JSON.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private static function details( array $entry, array $input, $simulation ) {
		$rows   = array( array( 'Herramienta', (string) ( $entry['label'] ?? '' ) ) );
		$shown  = Digitalisimo_AI_Assistant_Tools::card_values( $input );
		self::flatten( $shown, '', $rows );
		if ( is_array( $simulation ) ) {
			$would   = is_array( $simulation['would'] ?? null ) ? $simulation['would'] : array();
			$changes = is_array( $would['changes'] ?? null ) ? $would['changes'] : array();
			foreach ( array_slice( $changes, 0, 40 ) as $change ) {
				if ( is_array( $change ) && isset( $change['field'] ) ) {
					$rows[] = array( 'Ahora · ' . self::detail_name( (string) $change['field'] ), self::plain( $change['from'] ?? '' ) . ' → ' . self::plain( $change['to'] ?? '' ) );
				}
			}
			if ( isset( $simulation['allowed'] ) && false === $simulation['allowed'] && isset( $simulation['reason'] ) ) {
				$rows[] = array( 'Aviso', (string) $simulation['reason'] );
			}
		}
		return array_slice( $rows, 0, 200 );
	}

	private static function flatten( $value, $path, array &$rows ) {
		if ( count( $rows ) >= 200 ) {
			return;
		}
		if ( is_array( $value ) ) {
			if ( ! $value ) {
				$rows[] = array( '' !== $path ? $path : 'Datos', '(vacío)' );
				return;
			}
			$list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( $list && ! array_filter( $value, 'is_array' ) ) {
				$rows[] = array( '' !== $path ? $path : 'Datos', implode( ', ', array_map( array( __CLASS__, 'plain' ), $value ) ) );
				return;
			}
			foreach ( $value as $key => $item ) {
				$name = is_int( $key ) ? '#' . ( $key + 1 ) : self::detail_name( (string) $key );
				self::flatten( $item, '' !== $path ? $path . ' › ' . $name : $name, $rows );
			}
			return;
		}
		$rows[] = array( '' !== $path ? $path : 'Dato', self::plain( $value ) );
	}

	private static function detail_name( $key ) {
		if ( isset( self::DETAIL_NAMES[ $key ] ) ) {
			return self::DETAIL_NAMES[ $key ];
		}
		if ( isset( self::FIELDS[ $key ] ) ) {
			return self::ucfirst( (string) preg_replace( '/^(el|la|los|las|si|su|unos?|unas?) /u', '', self::FIELDS[ $key ] ) );
		}
		return self::ucfirst( str_replace( array( '_', '-' ), ' ', $key ) );
	}

	private static function plain( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'sí' : 'no';
		}
		if ( null === $value ) {
			return '(nada)';
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( __CLASS__, 'plain' ), array_slice( $value, 0, 20 ) ) );
		}
		return '' === (string) $value ? '(vacío)' : (string) $value;
	}

	/* ------------------------------------------------------------------ */
	/* Nombres y formatos.                                                  */
	/* ------------------------------------------------------------------ */

	private static function field( $key ) {
		return self::FIELDS[ (string) $key ] ?? 'otros datos';
	}

	private static function product( $id ) {
		$id   = (int) $id;
		$name = '';
		if ( $id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $id );
			$name    = $product ? (string) $product->get_name() : '';
		}
		if ( '' === $name ) {
			$name = self::title( $id );
		}
		return '' !== $name ? '«' . self::clip( $name, 80 ) . '»' : 'ese producto';
	}

	private static function page( $id ) {
		$title = self::title( $id );
		return '' !== $title ? 'la página «' . self::clip( $title, 80 ) . '»' : 'esa página';
	}

	private static function title( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! function_exists( 'get_post' ) ) {
			return '';
		}
		$post = get_post( $id );
		return $post && isset( $post->post_title ) ? trim( self::strip( (string) $post->post_title ) ) : '';
	}

	private static function term( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! function_exists( 'get_term' ) ) {
			return '';
		}
		$term = get_term( $id );
		return is_object( $term ) && ! is_wp_error( $term ) && isset( $term->name ) ? self::clip( (string) $term->name, 60 ) : '';
	}

	private static function user( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return '';
		}
		$user = get_userdata( $id );
		return $user && isset( $user->display_name ) ? self::clip( (string) $user->display_name, 60 ) : '';
	}

	private static function gateway( $id ) {
		$names = array( 'bacs' => 'Transferencia bancaria', 'cod' => 'Pago contra entrega', 'cheque' => 'Cheque', 'paypal' => 'PayPal', 'ppcp-gateway' => 'PayPal', 'stripe' => 'Tarjeta (Stripe)', 'woo-mercado-pago-basic' => 'Mercado Pago', 'woo-mercado-pago-custom' => 'Mercado Pago (tarjeta)' );
		if ( function_exists( 'WC' ) && is_object( WC() ) && method_exists( WC(), 'payment_gateways' ) ) {
			try {
				$all = WC()->payment_gateways()->payment_gateways();
				if ( isset( $all[ $id ] ) && is_object( $all[ $id ] ) && method_exists( $all[ $id ], 'get_title' ) && '' !== (string) $all[ $id ]->get_title() ) {
					return self::clip( self::strip( (string) $all[ $id ]->get_title() ), 60 );
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		return $names[ $id ] ?? $id;
	}

	private static function address( $path ) {
		$path = trim( $path );
		if ( '' === $path ) {
			return '(ninguna)';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			return (string) preg_replace( '#^https?://(www\.)?#i', '', $path );
		}
		$host = function_exists( 'home_url' ) ? (string) preg_replace( '#^https?://(www\.)?#i', '', rtrim( home_url( '/' ), '/' ) ) : '';
		return $host . '/' . ltrim( $path, '/' );
	}

	private static function unit( $option, $fallback ) {
		$unit = function_exists( 'get_option' ) ? (string) get_option( $option, $fallback ) : $fallback;
		$map  = array( 'lbs' => 'libras', 'oz' => 'onzas', 'in' => 'pulgadas', 'yd' => 'yardas' );
		return $map[ $unit ] ?? ( '' !== $unit ? $unit : $fallback );
	}

	private static function money( $value ) {
		$n = (float) str_replace( ',', '', (string) $value );
		return '$' . number_format( $n, floor( $n ) == $n ? 0 : 2, '.', ',' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	private static function number( $value ) {
		$n = (float) str_replace( ',', '', (string) $value );
		return floor( $n ) == $n ? number_format( $n, 0, '.', ',' ) : rtrim( rtrim( number_format( $n, 2, '.', ',' ), '0' ), '.' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	private static function truthy( $value ) {
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'si', 'sí', 'on' ), true );
	}

	private static function count_words( $n, $one, $many, $article = 'un' ) {
		$n = (int) $n;
		return 1 === $n ? ltrim( $article . ' ' . $one ) : $n . ' ' . $many;
	}

	private static function join( array $parts ) {
		$parts = array_values( array_filter( array_map( 'strval', $parts ), 'strlen' ) );
		if ( count( $parts ) < 2 ) {
			return (string) ( $parts[0] ?? '' );
		}
		$last = array_pop( $parts );
		return implode( ', ', $parts ) . ' y ' . $last;
	}

	private static function clip( $text, $max ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
		return self::length( $text ) > $max ? rtrim( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1, 'UTF-8' ) : substr( $text, 0, $max - 1 ) ) . '…' : $text;
	}

	private static function strip( $text ) {
		return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $text ) : strip_tags( (string) $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	private static function ucfirst( $text ) {
		$text = (string) $text;
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' ) : ucfirst( $text );
	}
}
