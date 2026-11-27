<?php
defined( 'ABSPATH' ) || exit;

/**
 * El dominio antes de pagar (pedido del propietario del 2026-11-26 y pendientes B1 de la auditoría SEO).
 *
 * - **Hosting o correo:** antes de pagar se elige «Dominio nuevo» (se agrega el producto de dominio con su nombre) o «Uno
 *   que ya tengo» (se pide el nombre exacto, que queda en la línea del plan como dato «Dominio»; no se exige producto de
 *   dominio). Lo pide el panel del carrito y del checkout (clásicos, de bloques y los de DIGITALÍSIMO Elements) y los
 *   campos de la página del producto; la Store API lo recibe en el cuerpo de `cart/add-item`.
 * - **Registro (B1):** el producto de dominio lleva el nombre a registrar (campo del producto, del carrito o el
 *   `?domain_name=` del buscador) y se comprueba con namecheap.domains.check antes de pagar: disponible, no premium y de
 *   la extensión del producto. Ya no hace falta escribirlo en «Notas del pedido».
 * - **Transferencia:** el producto de transferencia pide el dominio y el código EPP antes de pagar; se valida el formato,
 *   que Namecheap lo dé por registrado, que su extensión se transfiera por la API (namecheap.domains.getTldList) y que
 *   no esté ya en nuestra cuenta. El EPP es un secreto: se cifra al entrar al carrito (la sesión nunca lo guarda en
 *   claro), pasa cifrado al pedido (`_digitalisimo_epp`, meta del pedido, nunca de la línea) con el mismo cifrado que la
 *   contraseña de la cuenta, y nunca sale en notas, correos, registros, la REST de WooCommerce (ni por el MCP de la
 *   tienda) ni exportaciones. Sólo quien administra la tienda lo ve, a propósito, con «Ver código EPP» en el pedido.
 * Un dominio «que ya tengo» o en transferencia pasa después por la misma revisión de propiedad del aprovisionamiento
 * (Digitalisimo_Hosting_Provisioning::check_owner(): revisión manual por omisión).
 */
class Digitalisimo_Hosting_Checkout {
	const META_EPP   = '_digitalisimo_epp';
	const NONCE      = 'digitalisimo_hosting_domain_choice';
	const EPP_ACTION = 'digitalisimo_hosting_epp';
	/** Código EPP: caracteres imprimibles sin espacios, de 6 a 64 (lo que dan los registradores). */
	const EPP_PATTERN = '/^[\x21-\x7E]{6,64}$/';
	/** Cuánto vale una consulta de Namecheap del carrito (no se repite en cada vista). */
	const CHECK_TTL = 600;

	/** @var array Resultados de esta petición (dominio => resultado). */
	private static $checked = array();

	public static function init() {
		add_filter( 'woocommerce_store_api_add_to_cart_data', array( __CLASS__, 'store_api_data' ), 10, 2 );
		// El buscador agrega por la Store API: se valida en el servidor antes de agregar (nunca un dominio sin nombre).
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( __CLASS__, 'store_api_validate' ), 10, 2 );
		// Antes que «Reemplazar el plan» (10): un plan rechazado no se lleva el que ya estaba en el carrito.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'maybe_validate_add' ), 5, 6 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'maybe_product_fields' ) );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'maybe_panel' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'maybe_panel' ), 5 );
		// El checkout clásico con errores del carrito pinta checkout/cart-errors.php y no el formulario: el panel va ahí.
		add_action( 'woocommerce_cart_has_errors', array( __CLASS__, 'maybe_panel' ) );
		add_filter( 'render_block', array( __CLASS__, 'block_panel' ), 10, 2 );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_panel' ), 30 );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( __CLASS__, 'rest_strip' ), 99, 1 );
		add_filter( 'woocommerce_rest_api_v4_orders_item_response', array( __CLASS__, 'rest_strip' ), 99, 1 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30, 2 );
		add_action( 'admin_post_' . self::EPP_ACTION, array( __CLASS__, 'show_epp' ) );
		// Un pedido cancelado, reembolsado o fallido ya no necesita el código: se borra.
		foreach ( array( 'cancelled', 'refunded', 'failed' ) as $status ) add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'purge_epp' ), 5 );
	}

	/** Borra el EPP guardado de un pedido. */
	public static function purge_epp( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || ! $order->get_meta( self::META_EPP ) ) return;
		$order->delete_meta_data( self::META_EPP );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: el pedido está «' . $order->get_status() . '»; el código EPP se borró.' );
		$order->save();
	}

	private static function on() { $o = Digitalisimo_Hosting::settings(); return ! empty( $o['enabled'] ); }

	// ── Qué es cada producto ────────────────────────────────────────────────────────────────────────────────────

	/** register (dominio nuevo), transfer, hosting (plan de hosting o correo) o '': lo marcado en el producto o la variación. */
	public static function kind( $product_id ) { return Digitalisimo_Hosting_Products::kind( (int) $product_id ); }

	/** Nombre de dominio tal como se registra: minúsculas, sin protocolo, ruta, «www.» ni punto final. */
	public static function clean_domain( $raw ) {
		$d = strtolower( trim( sanitize_text_field( (string) $raw ) ) );
		$d = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $d );
		$d = preg_replace( '#[/?\#].*$#', '', $d );
		$d = preg_replace( '/^www\./', '', rtrim( $d, '.' ) );
		return substr( $d, 0, 253 );
	}

	/** ¿Es un dominio registrable (una etiqueta delante de su extensión)? */
	public static function registrable( $domain ) {
		return '' !== $domain && Digitalisimo_Hosting_Provisioning::valid_domain( $domain ) && null !== Digitalisimo_Hosting_Provisioning::split( $domain );
	}

	/** Extensión del dominio según split() («.com», «.com.mx»). */
	private static function ext_of( $domain ) { $s = Digitalisimo_Hosting_Provisioning::split( $domain ); return $s ? $s[2] : ''; }

	// ── Lo que llega al carrito ─────────────────────────────────────────────────────────────────────────────────

	/**
	 * Datos de la línea desde la petición clásica (formulario del producto o `?add-to-cart=…&domain_name=…`). El EPP sólo
	 * se acepta en el cuerpo de un POST, nunca en la URL (quedaría en los registros del servidor).
	 */
	public static function request_data() {
		$data = array();
		// phpcs:disable WordPress.Security.NonceVerification -- añadir al carrito no lleva nonce en WooCommerce.
		$domain = $_REQUEST['digitalisimo_domain'] ?? $_REQUEST['domain_name'] ?? '';
		$mode = (string) ( $_REQUEST['digitalisimo_domain_mode'] ?? '' );
		$epp = 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ? (string) ( $_POST['digitalisimo_epp'] ?? '' ) : '';
		// phpcs:enable
		if ( is_string( $domain ) && '' !== trim( $domain ) ) $data['digitalisimo_domain'] = self::clean_domain( wp_unslash( $domain ) );
		if ( in_array( $mode, array( 'new', 'own' ), true ) ) $data['digitalisimo_domain_mode'] = $mode;
		if ( '' !== $epp ) $data['digitalisimo_epp_raw'] = trim( wp_unslash( $epp ) );
		return $data;
	}

	/**
	 * Store API (checkout de bloques): `digitalisimo_domain`, `digitalisimo_domain_mode` y `digitalisimo_epp` en el cuerpo.
	 * El EPP sólo del cuerpo (JSON o formulario), nunca de la URL.
	 */
	public static function store_api_data( $add, $request ) {
		if ( ! self::on() || ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) return $add;
		$data = is_array( $add['cart_item_data'] ?? null ) ? $add['cart_item_data'] : array();
		$domain = $request->get_param( 'digitalisimo_domain' );
		$mode = (string) $request->get_param( 'digitalisimo_domain_mode' );
		$body = array_merge( method_exists( $request, 'get_body_params' ) ? (array) $request->get_body_params() : array(), method_exists( $request, 'get_json_params' ) ? (array) $request->get_json_params() : array() );
		$epp = $body['digitalisimo_epp'] ?? null;
		if ( is_string( $domain ) && '' !== trim( $domain ) ) $data['digitalisimo_domain'] = self::clean_domain( $domain );
		if ( in_array( $mode, array( 'new', 'own' ), true ) ) $data['digitalisimo_domain_mode'] = $mode;
		if ( is_string( $epp ) && '' !== trim( $epp ) ) $data['digitalisimo_epp_raw'] = trim( $epp );
		$add['cart_item_data'] = $data;
		return $add;
	}

	/**
	 * `woocommerce_add_cart_item_data`: lo que trae la petición (sin pisar lo que ya puso la Store API) y el EPP cifrado
	 * (la sesión del carrito nunca guarda el código en claro).
	 */
	public static function cart_item_data( $data, $product_id ) {
		$data = is_array( $data ) ? $data : array();
		foreach ( self::request_data() as $key => $value ) if ( ! isset( $data[ $key ] ) ) $data[ $key ] = $value;
		if ( isset( $data['digitalisimo_epp_raw'] ) ) {
			$raw = (string) $data['digitalisimo_epp_raw'];
			unset( $data['digitalisimo_epp_raw'] );
			if ( 'transfer' === self::kind( $product_id ) && preg_match( self::EPP_PATTERN, $raw ) ) {
				$enc = Digitalisimo_Hosting_Provisioning::encrypt( $raw );
				if ( '' !== $enc ) $data['digitalisimo_epp'] = $enc;
			}
		}
		// «Uno que ya tengo» sólo tiene sentido en un plan; en un dominio el nombre es el que se registra o transfiere.
		if ( 'hosting' !== self::kind( $product_id ) ) unset( $data['digitalisimo_domain_mode'] );
		// Con «Dominio nuevo», lo escrito en el campo de «Uno que ya tengo» no cuenta (el navegador pudo enviarlo igual).
		elseif ( 'new' === ( $data['digitalisimo_domain_mode'] ?? '' ) ) unset( $data['digitalisimo_domain'], $data['digitalisimo_domain_mode'] );
		elseif ( empty( $data['digitalisimo_domain'] ) && 'own' === ( $data['digitalisimo_domain_mode'] ?? '' ) ) unset( $data['digitalisimo_domain_mode'] );
		return $data;
	}

	/**
	 * Store API (`cart/add-item`, el buscador de dominios y el carrito de bloques): se valida en el servidor ANTES de
	 * agregar, con lo que trae el cuerpo. Un dominio nunca entra sin su nombre (ni uno ya agregado, ni más de un año por
	 * línea); el nombre se limpia y se comprueba como en el carrito (válido, de la extensión del producto, libre y no
	 * premium; la transferencia, registrada y transferible). El precio es siempre el del producto de WooCommerce.
	 */
	public static function store_api_validate( $product, $request ) {
		if ( ! self::on() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) return;
		// WooCommerce (CartController::validate_add_to_cart) manda un arreglo: id, quantity, variation y cart_item_data, éste
		// ya pasado por woocommerce_add_cart_item_data (nombre limpio, EPP cifrado). Se acepta también una petición REST.
		if ( is_array( $request ) ) { $data = is_array( $request['cart_item_data'] ?? null ) ? $request['cart_item_data'] : array(); $qty = (float) ( $request['quantity'] ?? 1 ); }
		elseif ( is_object( $request ) && method_exists( $request, 'get_param' ) ) { $data = self::store_api_data( array( 'cart_item_data' => array() ), $request )['cart_item_data']; $qty = (float) ( $request->get_param( 'quantity' ) ?? 1 ); }
		else return;
		// La variación misma (lo suyo manda y hereda lo demás del producto).
		$pid = (int) $product->get_id();
		$kind = self::kind( $pid );
		if ( '' === $kind ) return;
		$domain = self::clean_domain( $data['digitalisimo_domain'] ?? '' );
		$error = '';
		if ( 'hosting' !== $kind && '' === $domain ) $error = 'register' === $kind ? 'Busca primero el nombre de tu dominio: un dominio sólo se agrega con su nombre.' : 'Escribe el dominio que quieres transferir.';
		elseif ( 'hosting' !== $kind && $qty > 1 ) $error = 'Cada dominio se agrega una sola vez (un año).';
		elseif ( 'hosting' !== $kind && self::in_cart( $domain ) ) $error = 'El dominio ' . $domain . ' ya está en tu carrito.';
		else $error = self::line_error( $pid, $data, false );
		if ( '' === $error ) return;
		$class = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
		if ( class_exists( $class ) ) throw new $class( 'digitalisimo_hosting_domain', $error, 400 );
		throw new Exception( $error );
	}

	/** ¿Ya hay una línea de dominio (registro o transferencia) con ese nombre en el carrito? */
	private static function in_cart( $domain ) {
		foreach ( self::lines() as $line ) if ( 'hosting' !== $line['kind'] && $domain === (string) ( $line['data']['digitalisimo_domain'] ?? '' ) ) return true;
		return false;
	}

	public static function maybe_validate_add( $passed, $product_id, $quantity = 1, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
		if ( ! $passed || ! self::on() ) return $passed;
		$data = is_array( $cart_item_data ) && $cart_item_data ? $cart_item_data + self::request_data() : self::request_data();
		$error = self::line_error( (int) $variation_id ? (int) $variation_id : (int) $product_id, $data, false );
		if ( '' === $error ) return $passed;
		wc_add_notice( $error, 'error' );
		return false;
	}

	/**
	 * El error de una línea, o ''. $final: al pagar falta todo lo que falte (el nombre, el EPP); al añadir al carrito sólo
	 * se rechaza lo que vino mal (se completa en el carrito).
	 */
	public static function line_error( $product_id, array $data, $final ) {
		$kind = self::kind( $product_id );
		$domain = self::clean_domain( $data['digitalisimo_domain'] ?? '' );
		$name = self::product_name( $product_id );
		if ( 'register' === $kind ) {
			if ( '' === $domain ) return $final ? 'Escribe el dominio que quieres registrar con «' . $name . '».' : '';
			$ext = Digitalisimo_Hosting_Products::register_ext( $product_id );
			if ( ! self::registrable( $domain ) ) return '«' . $domain . '» no es un dominio válido. Escríbelo como minegocio' . $ext . ', sin espacios ni acentos.';
			if ( self::ext_of( $domain ) !== $ext ) return 'El dominio ' . $domain . ' no es ' . $ext . ': «' . $name . '» registra dominios ' . $ext . '.';
			return self::availability_error( $domain );
		}
		if ( 'transfer' === $kind ) {
			if ( '' === $domain ) return $final ? 'Escribe el dominio que quieres transferir con «' . $name . '».' : '';
			if ( ! self::registrable( $domain ) ) return '«' . $domain . '» no es un dominio válido. Escríbelo completo, por ejemplo minegocio.com.';
			$ext = Digitalisimo_Hosting_Products::transfer_ext( $product_id );
			if ( '*' !== $ext && self::ext_of( $domain ) !== $ext ) return 'El dominio ' . $domain . ' no es ' . $ext . ': «' . $name . '» transfiere dominios ' . $ext . '.';
			// «*» = cualquiera de las configuradas: sólo una extensión con producto de registro, que es con el que se renueva.
			if ( ! isset( Digitalisimo_Hosting::domain_products()[ self::ext_of( $domain ) ] ) ) return 'Los dominios ' . self::ext_of( $domain ) . ' no se transfieren aquí. Escríbenos y lo vemos contigo.';
			$has_epp = ! empty( $data['digitalisimo_epp'] ) || ! empty( $data['digitalisimo_epp_raw'] );
			if ( isset( $data['digitalisimo_epp_raw'] ) && ! preg_match( self::EPP_PATTERN, (string) $data['digitalisimo_epp_raw'] ) ) return 'El código de transferencia (EPP) no es válido: copia el código completo que te dio tu registrador actual (de 6 a 64 caracteres, sin espacios).';
			if ( $final && ! $has_epp ) return 'Escribe el código de transferencia (EPP) de ' . $domain . '. Te lo da tu registrador actual al desbloquear el dominio.';
			return self::transfer_error( $domain, $product_id );
		}
		// Con «Dominio nuevo» el campo de «Uno que ya tengo» se ignora: el dominio llega con su propio producto.
		if ( 'hosting' === $kind && 'new' !== ( $data['digitalisimo_domain_mode'] ?? '' ) && ( '' !== $domain || 'own' === ( $data['digitalisimo_domain_mode'] ?? '' ) ) ) {
			if ( '' === $domain ) return 'Escribe el nombre exacto del dominio que ya tienes para «' . $name . '».';
			if ( ! self::registrable( $domain ) ) return '«' . $domain . '» no es un dominio válido. Escribe el nombre exacto del dominio que ya tienes, por ejemplo minegocio.com (sin www).';
			$reject = Digitalisimo_Hosting_Provisioning::own_domain_rejected( $domain );
			if ( '' !== $reject ) return $reject;
			return self::own_error( $domain );
		}
		return '';
	}

	private static function product_name( $product_id ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null; return $p ? (string) $p->get_name() : '#' . (int) $product_id; }

	// ── Consultas a Namecheap (en caché unos minutos y con tope por visitante) ───────────────────────────────────

	/** Tiempo de espera de una consulta hecha mientras el visitante espera (el aprovisionamiento usa 60 s). */
	const TIMEOUT = 15;

	/**
	 * namecheap.domains.check: array( kind ok|error|config, available, premium, message ), en caché unos minutos (y una
	 * falla, un minuto: no se repite en cada vista mientras Namecheap no responde). $limit: tope por visitante del carrito
	 * (el buscador público lleva el suyo en la ruta REST).
	 */
	public static function lookup( $domain, $limit = true ) {
		if ( isset( self::$checked[ $domain ] ) ) return self::$checked[ $domain ];
		$key = 'digitalisimo_hosting_chk_' . md5( $domain );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) return self::$checked[ $domain ] = $cached;
		// El registrador de la extensión del dominio (el predeterminado o el de «Registrador por extensión»).
		$nc = Digitalisimo_Hosting_Registrars::for_domain( $domain );
		if ( ! $nc->configured() ) return self::$checked[ $domain ] = array( 'kind' => 'config' );
		if ( $limit && ! Digitalisimo_Hosting::rate_limit( 'domain-cart', 30 ) ) return array( 'kind' => 'error', 'message' => 'Demasiadas consultas de dominios. Intenta de nuevo en un minuto.' );
		$r = $nc->check( $domain, self::TIMEOUT );
		$out = 'ok' === $r['kind'] ? array( 'kind' => 'ok', 'available' => ! empty( $r['available'] ), 'premium' => ! empty( $r['premium'] ) ) : array( 'kind' => 'error', 'message' => 'No pudimos comprobar el dominio ' . $domain . ' en este momento. Intenta de nuevo en unos minutos.' );
		set_transient( $key, $out, 'ok' === $out['kind'] ? self::CHECK_TTL : MINUTE_IN_SECONDS );
		return self::$checked[ $domain ] = $out;
	}

	/**
	 * Lo que ya se sabe de un dominio sin consultar a Namecheap (esta petición o la caché de unos minutos), sólo si la
	 * consulta salió bien; null si no. El buscador y el carrito comparten la caché: agregar lo que se acaba de buscar no
	 * vuelve a consultar.
	 */
	public static function cached( $domain ) {
		$domain = (string) $domain;
		if ( isset( self::$checked[ $domain ] ) ) return 'ok' === ( self::$checked[ $domain ]['kind'] ?? '' ) ? self::$checked[ $domain ] : null;
		$cached = get_transient( 'digitalisimo_hosting_chk_' . md5( $domain ) );
		if ( is_array( $cached ) && 'ok' === ( $cached['kind'] ?? '' ) ) return self::$checked[ $domain ] = $cached;
		return null;
	}

	/** Guarda el resultado de una consulta hecha en lote (el buscador) con la misma clave y duración que lookup(). */
	public static function remember( $domain, $available, $premium ) {
		$out = array( 'kind' => 'ok', 'available' => (bool) $available, 'premium' => (bool) $premium );
		set_transient( 'digitalisimo_hosting_chk_' . md5( (string) $domain ), $out, self::CHECK_TTL );
		return self::$checked[ (string) $domain ] = $out;
	}

	/** Registro: disponible y no premium. Sin cuenta de Namecheap no se consulta (lo comprueba el aprovisionamiento). */
	private static function availability_error( $domain ) {
		$r = self::lookup( $domain );
		if ( 'config' === $r['kind'] ) return '';
		if ( 'ok' !== $r['kind'] ) return $r['message'];
		if ( ! $r['available'] ) return 'El dominio ' . $domain . ' ya está registrado. Elige otro nombre o, si es tuyo, contrata el hosting con «Uno que ya tengo» o transfiérelo.';
		if ( $r['premium'] ) return 'El dominio ' . $domain . ' es premium (tiene un precio especial) y no se vende aquí. Elige otro nombre.';
		return '';
	}

	/** «Uno que ya tengo»: si Namecheap lo da por libre, no existe (un error de escritura). Una falla de la consulta no frena. */
	private static function own_error( $domain ) {
		$r = self::lookup( $domain );
		if ( 'ok' === $r['kind'] && $r['available'] ) return 'El dominio ' . $domain . ' no está registrado todavía: revisa cómo se escribe o elige «Dominio nuevo» para registrarlo.';
		return '';
	}

	/**
	 * Transferencia: registrado, extensión transferible por la API, no está ya en nuestra cuenta y lo que cuesta el
	 * producto deja el margen mínimo sobre el costo de transferencia de Namecheap (o el de registro si Namecheap no da el
	 * de transferencia): el mismo freno de pérdida que el registro (Digitalisimo_Hosting_Pricing::guard()).
	 */
	private static function transfer_error( $domain, $product_id = 0 ) {
		$key = 'digitalisimo_hosting_trf_' . md5( $domain . '|' . (int) $product_id );
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) return $cached;
		// El registrador que recibiría el dominio: el de su extensión.
		$nc = Digitalisimo_Hosting_Registrars::for_domain( $domain );
		if ( ! $nc->configured() ) return 'Las transferencias de dominio no están disponibles en este momento: falta configurar la consulta de dominios.';
		$r = self::lookup( $domain );
		if ( 'ok' !== $r['kind'] ) return $r['message'] ?? 'No pudimos comprobar el dominio ' . $domain . '.';
		$error = '';
		if ( $r['available'] ) $error = 'El dominio ' . $domain . ' no está registrado, así que no se puede transferir. Puedes registrarlo como dominio nuevo.';
		$busy = 'No pudimos comprobar si ' . $domain . ' se puede transferir. Intenta de nuevo en unos minutos.';
		if ( '' === $error ) {
			$tlds = $nc->tlds( self::TIMEOUT );
			if ( 'ok' !== $tlds['kind'] ) { set_transient( $key, $busy, MINUTE_IN_SECONDS ); return $busy; }
			$tld = ltrim( self::ext_of( $domain ), '.' );
			if ( empty( $tlds['tlds'][ $tld ]['transfer'] ) ) $error = 'Los dominios .' . $tld . ' no se pueden transferir aquí. Escríbenos y lo vemos contigo.';
		}
		if ( '' === $error ) {
			// En cualquiera de nuestras cuentas de registrador (la de la extensión primero).
			foreach ( array( $nc->id() => $nc ) + Digitalisimo_Hosting_Registrars::configured_all() as $acc ) {
				$info = $acc->info( $domain, self::TIMEOUT );
				if ( 'ok' !== $info['kind'] ) { set_transient( $key, $busy, MINUTE_IN_SECONDS ); return $busy; }
				// Texto neutro: no se dice a nadie qué dominios están en nuestra cuenta.
				if ( ! empty( $info['ours'] ) ) { $error = 'El dominio ' . $domain . ' no se puede transferir aquí. Escríbenos y lo vemos contigo.'; break; }
			}
		}
		if ( '' === $error && class_exists( 'Digitalisimo_Hosting_Pricing' ) ) {
			$tld = ltrim( self::ext_of( $domain ), '.' );
			$cost = $nc->price( $tld, 'TRANSFER', self::TIMEOUT );
			if ( 'ok' !== $cost['kind'] ) $cost = $nc->price( $tld, 'REGISTER', self::TIMEOUT );
			if ( 'ok' !== $cost['kind'] ) { set_transient( $key, $busy, MINUTE_IN_SECONDS ); return $busy; }
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null;
			$charged = ! $product ? 0.0 : ( function_exists( 'wc_get_price_excluding_tax' ) ? (float) wc_get_price_excluding_tax( $product ) : (float) $product->get_price() );
			$guard = Digitalisimo_Hosting_Pricing::guard( $charged, (float) $cost['value'], 'transferir ' . $domain, function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null, $nc->label() );
			if ( $guard && 'price' !== $guard['kind'] ) { set_transient( $key, $busy, MINUTE_IN_SECONDS ); return $busy; }
			// El detalle (costos y margen) es para el administrador; al visitante, un texto neutro.
			if ( $guard ) $error = 'Por ahora no podemos transferir ' . $domain . ' a este precio. Escríbenos y lo vemos contigo.';
		}
		set_transient( $key, $error, self::CHECK_TTL );
		return $error;
	}

	// ── El carrito antes de pagar ───────────────────────────────────────────────────────────────────────────────

	/** Las líneas del carrito con su tipo: array( key => array( kind, product, data, name ) ). */
	public static function lines() {
		$out = array();
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) return $out;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$pid = Digitalisimo_Hosting_Products::item_id( $item );
			$kind = self::kind( $pid );
			if ( '' === $kind ) continue;
			$out[ $key ] = array( 'kind' => $kind, 'product' => $pid, 'data' => $item, 'name' => self::product_name( $pid ) );
		}
		return $out;
	}

	/**
	 * Errores que impiden pagar (`woocommerce_check_cart_items`: carrito, checkout clásico y Store API): cada dominio con
	 * su nombre válido (y su EPP), y cada plan con su dominio nuevo o el que ya tiene el cliente.
	 */
	public static function cart_errors() {
		$errors = array();
		$lines = self::lines();
		$hosting_need = 0; $hosting = 0; $domains = 0;
		foreach ( $lines as $line ) {
			if ( 'hosting' === $line['kind'] ) {
				$hosting++;
				$e = self::line_error( $line['product'], $line['data'], true );
				if ( '' !== $e ) $errors[] = $e;
				if ( '' === (string) ( $line['data']['digitalisimo_domain'] ?? '' ) ) $hosting_need++;
				continue;
			}
			$domains++;
			$e = self::line_error( $line['product'], $line['data'], true );
			if ( '' !== $e ) $errors[] = $e;
			// Un dominio es un año por línea: con más, la venta automática se detiene y el cobro queda por devolver.
			if ( (float) ( $line['data']['quantity'] ?? 1 ) > 1 ) $errors[] = 'Cada dominio se compra por un año: deja la cantidad de ' . ( '' !== (string) ( $line['data']['digitalisimo_domain'] ?? '' ) ? $line['data']['digitalisimo_domain'] : '«' . $line['name'] . '»' ) . ' en 1.';
		}
		$o = Digitalisimo_Hosting::settings();
		if ( $hosting_need && ! $domains && ( ! empty( $o['ask_domain'] ) || ! empty( $o['require_domain_hosting'] ) ) ) $errors[] = 'Elige el dominio de tu plan antes de pagar: «Dominio nuevo» (búscalo y agrégalo) o «Uno que ya tengo» (escribe su nombre exacto).';
		if ( ! empty( $o['require_domain_hosting'] ) && $domains && ! $hosting ) $errors[] = 'Agrega un plan de hosting para continuar: los dominios se venden junto con un plan.';
		// Sólo con lo demás en orden: ¿alcanza el saldo del registrador para lo que se va a cobrar?
		if ( ! $errors ) { $funds = self::funds_error( $lines ); if ( '' !== $funds ) $errors[] = $funds; }
		return array_values( array_unique( $errors ) );
	}

	// ── Saldo del registrador (antes de cobrar un dominio que después no se podría registrar) ─────────────────────

	/**
	 * Saldo (USD) del registrador que atiende esta extensión (Digitalisimo_Hosting_Registrars: el predeterminado o el de
	 * «Registrador por extensión»): array( kind ok, value, registrar ) o kind error|config. Con Dynadot, un saldo cero
	 * llega como 0 (Digitalisimo_Hosting_Dynadot::balance()), así que frena. En caché 5 minutos por registrador (una
	 * falla, 1 minuto).
	 */
	public static function registrar_balance( $ext ) {
		$rid = Digitalisimo_Hosting_Registrars::id_for_ext( $ext );
		$key = 'namecheap' === $rid ? 'digitalisimo_hosting_nc_balance' : 'digitalisimo_hosting_' . $rid . '_balance';
		$c = get_transient( $key );
		if ( is_array( $c ) ) return $c;
		$r = Digitalisimo_Hosting_Registrars::make( $rid );
		if ( ! $r->configured() ) return array( 'kind' => 'config', 'registrar' => $rid );
		$b = $r->balance( self::TIMEOUT );
		$out = 'ok' === $b['kind'] ? array( 'kind' => 'ok', 'value' => (float) $b['value'], 'registrar' => $rid ) : array( 'kind' => 'error', 'registrar' => $rid );
		set_transient( $key, $out, 'ok' === $out['kind'] ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Lo que cobra el registrador de la extensión por un año (USD) al registrar (REGISTER) o transferir (TRANSFER, o el
	 * registro si no lo informa): array( kind ok, value ) o kind error. Namecheap: users.getPricing; Dynadot: tld_price
	 * (el mismo registro que da su búsqueda). En caché una hora.
	 */
	public static function registrar_cost( $ext, $action ) {
		$rid = Digitalisimo_Hosting_Registrars::id_for_ext( $ext );
		$key = 'digitalisimo_hosting_' . ( 'namecheap' === $rid ? 'nc' : $rid ) . '_cost_' . md5( $ext . '|' . $action );
		$c = get_transient( $key );
		if ( is_array( $c ) ) return $c;
		$reg = Digitalisimo_Hosting_Registrars::make( $rid );
		if ( ! $reg->configured() ) return array( 'kind' => 'config' );
		$tld = ltrim( (string) $ext, '.' );
		$r = $reg->price( $tld, $action, self::TIMEOUT );
		if ( 'ok' !== $r['kind'] && 'TRANSFER' === $action ) $r = $reg->price( $tld, 'REGISTER', self::TIMEOUT );
		$out = 'ok' === $r['kind'] ? array( 'kind' => 'ok', 'value' => (float) $r['value'] ) : array( 'kind' => 'error' );
		set_transient( $key, $out, 'ok' === $out['kind'] ? HOUR_IN_SECONDS : MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * '' o el aviso para el comprador cuando el saldo del registrador no alcanza el costo de los dominios del carrito (por
	 * registrador). Sin saldo o sin costo que consultar no se frena: el aprovisionamiento deja el pedido en espera y avisa,
	 * como siempre. Al frenar, avisa al dueño (una vez cada 6 horas por registrador).
	 */
	private static function funds_error( array $lines ) {
		$need = array(); $have = array(); $names = array();
		foreach ( $lines as $line ) {
			if ( 'hosting' === $line['kind'] ) continue;
			$domain = (string) ( $line['data']['digitalisimo_domain'] ?? '' );
			$ext = self::ext_of( $domain );
			if ( '' === $domain || '' === $ext ) continue;
			$cost = self::registrar_cost( $ext, 'transfer' === $line['kind'] ? 'TRANSFER' : 'REGISTER' );
			$bal = self::registrar_balance( $ext );
			if ( 'ok' !== $cost['kind'] || 'ok' !== $bal['kind'] ) return '';
			$reg = (string) $bal['registrar'];
			$need[ $reg ] = ( $need[ $reg ] ?? 0 ) + (float) $cost['value'];
			$have[ $reg ] = (float) $bal['value'];
			$names[ $reg ][] = $domain;
		}
		foreach ( $need as $reg => $sum ) {
			if ( $have[ $reg ] + 0.005 >= $sum ) continue;
			$flag = 'digitalisimo_hosting_funds_alert_' . md5( $reg );
			if ( ! get_transient( $flag ) && class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) {
				set_transient( $flag, 1, 6 * HOUR_IN_SECONDS );
				Digitalisimo_Hosting_Provisioning::alert_admin( 'saldo del registrador insuficiente', sprintf( "Un cliente intentó pagar %s y el saldo de %s (%s USD) no alcanza su costo (%s USD).\n\nMientras no recargues la cuenta, el carrito no deja pagar esos dominios (el cliente ve un aviso para escribirte).", implode( ', ', $names[ $reg ] ), Digitalisimo_Hosting_Registrars::label( $reg ), number_format( $have[ $reg ], 2, '.', '' ), number_format( $sum, 2, '.', '' ) ) );
			}
			return 'Por ahora no podemos completar en línea la compra de ' . implode( ', ', $names[ $reg ] ) . '. Escríbenos y lo resolvemos contigo.';
		}
		return '';
	}

	public static function validate_cart() {
		foreach ( self::cart_errors() as $error ) wc_add_notice( $error, 'error' );
	}

	/** Lo que falta decidir en el carrito (para el panel). */
	private static function pending( array $lines ) {
		$ask = ! empty( Digitalisimo_Hosting::settings()['ask_domain'] );
		$domains = 0;
		foreach ( $lines as $l ) if ( 'hosting' !== $l['kind'] ) $domains++;
		$out = array();
		foreach ( $lines as $key => $l ) {
			$d = $l['data'];
			if ( 'hosting' === $l['kind'] && $ask && ! $domains && '' === (string) ( $d['digitalisimo_domain'] ?? '' ) ) $out[ $key ] = $l;
			elseif ( 'register' === $l['kind'] && '' === (string) ( $d['digitalisimo_domain'] ?? '' ) ) $out[ $key ] = $l;
			elseif ( 'transfer' === $l['kind'] && ( '' === (string) ( $d['digitalisimo_domain'] ?? '' ) || empty( $d['digitalisimo_epp'] ) ) ) $out[ $key ] = $l;
		}
		return $out;
	}

	public static function maybe_panel() { if ( self::on() ) echo self::panel_html(); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- panel_html() escapa todo.

	/** Carrito y checkout de bloques: el mismo panel delante del bloque. */
	public static function block_panel( $content, $block ) {
		if ( ! in_array( $block['blockName'] ?? '', array( 'woocommerce/cart', 'woocommerce/checkout' ), true ) || ! self::on() ) return $content;
		return self::panel_html() . $content;
	}

	/** El panel «Tu dominio», sólo si falta algo. Un formulario propio (nunca dentro del del checkout). */
	public static function panel_html() {
		$pending = self::pending( self::lines() );
		if ( ! $pending ) return '';
		$html = '<div class="digitalisimo-hosting-domain-panel"><form method="post" class="digitalisimo-hosting-domain-choice">';
		$html .= '<input type="hidden" name="_dhd_nonce" value="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '"><h3>Tu dominio</h3>';
		$search = false;
		foreach ( $pending as $key => $l ) {
			$k = esc_attr( $key );
			if ( 'hosting' === $l['kind'] ) {
				$search = true;
				$html .= '<fieldset class="digitalisimo-hosting-domain-mode"><legend>¿Qué dominio usará «' . esc_html( $l['name'] ) . '»?</legend>'
					. '<label><input type="radio" name="dhd[' . $k . '][mode]" value="new" checked> Dominio nuevo</label> '
					. '<label><input type="radio" name="dhd[' . $k . '][mode]" value="own"> Uno que ya tengo</label>'
					. '<p class="digitalisimo-hosting-own"><label>Nombre exacto del dominio que ya tienes <input type="text" name="dhd[' . $k . '][domain]" placeholder="minegocio.com" autocomplete="off" spellcheck="false"></label></p></fieldset>';
			} elseif ( 'register' === $l['kind'] ) {
				$html .= '<p><label>Dominio que quieres registrar con «' . esc_html( $l['name'] ) . '» <input type="text" name="dhd[' . $k . '][domain]" placeholder="minegocio' . esc_attr( Digitalisimo_Hosting_Products::register_ext( $l['product'] ) ) . '" autocomplete="off" spellcheck="false" required></label></p>';
			} else {
				$html .= '<fieldset><legend>Transferencia: «' . esc_html( $l['name'] ) . '»</legend>'
					. '<p><label>Dominio que quieres transferir <input type="text" name="dhd[' . $k . '][domain]" value="' . esc_attr( (string) ( $l['data']['digitalisimo_domain'] ?? '' ) ) . '" placeholder="minegocio.com" autocomplete="off" spellcheck="false" required></label></p>'
					. '<p><label>Código de transferencia (EPP) <input type="password" name="dhd[' . $k . '][epp]" autocomplete="off" spellcheck="false"' . ( empty( $l['data']['digitalisimo_epp'] ) ? ' required' : ' placeholder="Guardado · escribe otro sólo para cambiarlo"' ) . '></label></p></fieldset>';
			}
		}
		$html .= '<p><button type="submit" class="button" name="digitalisimo_hosting_domain_choice" value="1">Guardar dominio</button></p></form>';
		if ( $search ) $html .= '<div class="digitalisimo-hosting-new-domain"><p>Para un dominio nuevo, búscalo y agrégalo:</p>' . Digitalisimo_Hosting::search_widget() . '</div>';
		return $html . '</div>';
	}

	/** Guarda lo del panel en las líneas del carrito (con nonce) y vuelve a la misma página. */
	public static function handle_panel() {
		if ( empty( $_POST['digitalisimo_hosting_domain_choice'] ) || ! self::on() || ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) return;
		if ( ! wp_verify_nonce( (string) ( $_POST['_dhd_nonce'] ?? '' ), self::NONCE ) ) { wc_add_notice( 'La página caducó: vuelve a escribir tu dominio.', 'error' ); return; }
		$input = (array) wp_unslash( $_POST['dhd'] ?? array() );
		$saved = false;
		foreach ( $input as $key => $row ) {
			$key = (string) $key;
			$row = is_array( $row ) ? $row : array();
			if ( ! isset( WC()->cart->cart_contents[ $key ] ) ) continue;
			$item = WC()->cart->cart_contents[ $key ];
			$pid = Digitalisimo_Hosting_Products::item_id( $item );
			$kind = self::kind( $pid );
			$domain = self::clean_domain( $row['domain'] ?? '' );
			if ( 'hosting' === $kind ) {
				if ( 'own' !== ( $row['mode'] ?? '' ) ) { wc_add_notice( 'Para un dominio nuevo, búscalo con el buscador y agrégalo al carrito.', 'notice' ); continue; }
				$data = array( 'digitalisimo_domain' => $domain, 'digitalisimo_domain_mode' => 'own' );
			} elseif ( 'register' === $kind ) {
				$data = array( 'digitalisimo_domain' => $domain );
			} elseif ( 'transfer' === $kind ) {
				$data = array( 'digitalisimo_domain' => $domain );
				$epp = trim( (string) ( $row['epp'] ?? '' ) );
				if ( '' !== $epp ) $data['digitalisimo_epp_raw'] = $epp; elseif ( ! empty( $item['digitalisimo_epp'] ) ) $data['digitalisimo_epp'] = $item['digitalisimo_epp'];
			} else {
				continue;
			}
			$error = self::line_error( $pid, $data, true );
			if ( '' !== $error ) { wc_add_notice( $error, 'error' ); continue; }
			$item = array_merge( $item, self::cart_item_data( $data, $pid ) );
			WC()->cart->cart_contents[ $key ] = $item;
			$saved = true;
		}
		if ( $saved ) {
			WC()->cart->set_session();
			wc_add_notice( 'Dominio guardado.', 'success' );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) ) );
		exit;
	}

	/** Campos en la página del producto: el dominio (y el EPP) o la elección del plan. */
	public static function maybe_product_fields() {
		if ( ! self::on() ) return;
		global $product;
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) return;
		$kind = self::kind( (int) $product->get_id() );
		if ( 'register' === $kind ) {
			echo '<p class="digitalisimo-hosting-field"><label>Dominio que quieres registrar <input type="text" name="digitalisimo_domain" placeholder="minegocio' . esc_attr( Digitalisimo_Hosting_Products::register_ext( (int) $product->get_id() ) ) . '" autocomplete="off" spellcheck="false" required></label></p>';
		} elseif ( 'transfer' === $kind ) {
			echo '<p class="digitalisimo-hosting-field"><label>Dominio que quieres transferir <input type="text" name="digitalisimo_domain" placeholder="minegocio.com" autocomplete="off" spellcheck="false" required></label></p>';
			echo '<p class="digitalisimo-hosting-field"><label>Código de transferencia (EPP) <input type="password" name="digitalisimo_epp" autocomplete="off" spellcheck="false" required></label></p>';
		} elseif ( 'hosting' === $kind && ! empty( Digitalisimo_Hosting::settings()['ask_domain'] ) ) {
			echo '<fieldset class="digitalisimo-hosting-field digitalisimo-hosting-domain-mode"><legend>Dominio</legend>'
				. '<label><input type="radio" name="digitalisimo_domain_mode" value="new" checked> Dominio nuevo</label> '
				. '<label><input type="radio" name="digitalisimo_domain_mode" value="own"> Uno que ya tengo</label>'
				. '<p class="digitalisimo-hosting-own"><label>Nombre exacto del dominio que ya tienes <input type="text" name="digitalisimo_domain" placeholder="minegocio.com" autocomplete="off" spellcheck="false"></label></p></fieldset>';
		}
	}

	// ── El pedido ───────────────────────────────────────────────────────────────────────────────────────────────

	/** `woocommerce_checkout_create_order_line_item`: el EPP cifrado va al pedido (nunca a la línea). */
	public static function order_item( $item, $values, $order ) {
		if ( empty( $values['digitalisimo_epp'] ) || empty( $values['digitalisimo_domain'] ) || ! is_object( $order ) ) return;
		$all = $order->get_meta( self::META_EPP );
		$all = is_array( $all ) ? $all : array();
		$all[ (string) $values['digitalisimo_domain'] ] = (string) $values['digitalisimo_epp'];
		$order->update_meta_data( self::META_EPP, $all );
	}

	/** El EPP en claro de un dominio del pedido ('' si no hay o no se puede descifrar). Sólo para «Ver código EPP». */
	public static function epp( $order, $domain ) {
		$all = $order->get_meta( self::META_EPP );
		return is_array( $all ) && isset( $all[ $domain ] ) ? Digitalisimo_Hosting_Provisioning::decrypt( $all[ $domain ] ) : '';
	}

	/** La REST de pedidos de WooCommerce (v3, v4, y el MCP de la tienda, que la usa) nunca lleva el EPP, ni cifrado. */
	public static function rest_strip( $response ) {
		$strip = static function ( $meta ) {
			return array_values( array_filter( (array) $meta, static function ( $m ) {
				$key = is_object( $m ) ? ( $m->key ?? ( method_exists( $m, 'get_data' ) ? ( $m->get_data()['key'] ?? '' ) : '' ) ) : ( is_array( $m ) ? ( $m['key'] ?? '' ) : '' );
				return Digitalisimo_Hosting_Checkout::META_EPP !== $key;
			} ) );
		};
		if ( is_array( $response ) ) { if ( isset( $response['meta_data'] ) && is_array( $response['meta_data'] ) ) $response['meta_data'] = $strip( $response['meta_data'] ); return $response; }
		if ( ! is_object( $response ) || ! isset( $response->data ) || ! is_array( $response->data ) || empty( $response->data['meta_data'] ) || ! is_array( $response->data['meta_data'] ) ) return $response;
		$response->data['meta_data'] = $strip( $response->data['meta_data'] );
		return $response;
	}

	/** En el pedido: los dominios a transferir y «Ver código EPP» para quien administra la tienda. */
	public static function meta_box( $screen, $post = null ) {
		$order = $post instanceof WC_Order ? $post : ( is_object( $post ) && isset( $post->ID ) && function_exists( 'wc_get_order' ) ? wc_get_order( $post->ID ) : null );
		if ( ! $order || ! current_user_can( 'manage_woocommerce' ) ) return;
		$all = $order->get_meta( self::META_EPP );
		if ( ! is_array( $all ) || ! $all ) return;
		add_meta_box( 'digitalisimo-hosting-transfer', 'Transferencia de dominio', array( __CLASS__, 'meta_box_html' ), $screen, 'side', 'high', array( 'order' => $order ) );
	}

	public static function meta_box_html( $post, $box ) {
		$order = $box['args']['order'];
		echo '<p>El código EPP se guarda cifrado y no sale en notas, correos ni en la API. Úsalo para pedir la transferencia en el registrador que se indica junto a cada dominio.</p>';
		// El registrador anotado por el aprovisionamiento al tramitarla; si aún no corre, el de la extensión.
		$noted = class_exists( 'Digitalisimo_Hosting_Provisioning' ) ? (string) ( Digitalisimo_Hosting_Provisioning::state( $order )['transfer_registrar'] ?? '' ) : '';
		foreach ( array_keys( (array) $order->get_meta( self::META_EPP ) ) as $domain ) {
			$rid = Digitalisimo_Hosting_Registrars::valid( $noted ) ? $noted : Digitalisimo_Hosting_Registrars::id_for_domain( $domain );
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EPP_ACTION . '&order=' . (int) $order->get_id() . '&domain=' . rawurlencode( $domain ) ), self::EPP_ACTION . '|' . (int) $order->get_id() . '|' . $domain );
			echo '<p><strong>' . esc_html( $domain ) . '</strong> · en ' . esc_html( Digitalisimo_Hosting_Registrars::label( $rid ) ) . '<br><a class="button" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">Ver código EPP</a></p>';
		}
	}

	/** «Ver código EPP»: sólo con manage_woocommerce y el nonce de ese pedido y ese dominio; la página no se guarda en caché. */
	public static function show_epp() {
		$order_id = absint( $_GET['order'] ?? 0 );
		$domain = self::clean_domain( wp_unslash( (string) ( $_GET['domain'] ?? '' ) ) );
		if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'No tienes permiso para ver este código.', '', array( 'response' => 403 ) );
		check_admin_referer( self::EPP_ACTION . '|' . $order_id . '|' . $domain );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		$code = $order ? self::epp( $order, $domain ) : '';
		if ( function_exists( 'nocache_headers' ) ) nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		// Queda constancia de quién lo vio (nota privada, sin el código).
		if ( '' !== $code && method_exists( $order, 'add_order_note' ) ) { $user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null; $order->add_order_note( 'DIGITALÍSIMO Hosting: ' . ( $user && ! empty( $user->user_login ) ? $user->user_login : 'un administrador' ) . ' consultó el código EPP de ' . $domain . '.' ); }
		if ( '' === $code ) wp_die( 'Este pedido no tiene un código EPP que se pueda leer para ' . esc_html( $domain ) . ' (si cambiaron las sales de WordPress, pide el código otra vez al cliente).', 'Código EPP', array( 'response' => 404 ) );
		wp_die( '<p>Código de transferencia (EPP) de <strong>' . esc_html( $domain ) . '</strong>, pedido #' . (int) $order_id . ':</p><p><code style="font-size:1.4em;user-select:all">' . esc_html( $code ) . '</code></p><p>No lo copies en notas ni correos.</p>', 'Código EPP', array( 'response' => 200 ) );
	}
}
