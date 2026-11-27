<?php
defined( 'ABSPATH' ) || exit;

/**
 * Buscador de dominios estilo registrador (pedido del propietario del 2026-11-26; método «tienda de hosting y dominios»
 * del cerebro, tomado de cómo vende Namecheap).
 *
 * - **Una búsqueda, todas las extensiones:** el visitante escribe sólo el nombre (o nombre.ext) y se consultan a la vez
 *   todas las extensiones que tienen producto de dominio (resueltas por «Productos por extensión», `_digitalisimo_tld` o
 *   SKU: Digitalisimo_Hosting_Products, nunca IDs fijos), en UNA llamada a namecheap.domains.check
 *   (Digitalisimo_Hosting_Namecheap::check_many()). Cada resultado se guarda unos minutos con la misma clave que usa el
 *   carrito (Digitalisimo_Hosting_Checkout::cached()/remember()): agregar lo buscado no vuelve a consultar.
 * - **Resultado principal + una fila por extensión:** el principal es la extensión escrita (o .com); si está ocupado, la
 *   mejor alternativa libre en el orden configurado. Cada fila: disponible u ocupado, precio del primer año y renovación
 *   (del producto de WooCommerce, nunca del navegador), aviso de requisitos y, si está ocupado, «¿Es tuyo? Transfiérelo»
 *   con el producto de transferencia de esa extensión.
 * - **Agregar sin salir de la página:** el navegador usa la Store API de WooCommerce (`cart/add-item`) con el nombre; el
 *   servidor lo valida antes de agregar (Digitalisimo_Hosting_Checkout::store_api_validate()).
 * - **Seguridad del punto público:** POST con nonce (o, en una página guardada en caché con el nonce vencido, la cabecera
 *   propia del buscador desde el mismo sitio), límite por visitante, nombre validado (IDN, longitud, guiones), tope de
 *   extensiones por consulta y una respuesta sin nada de la cuenta de Namecheap.
 * - **Dónde se pone:** el shortcode [digitalisimo_buscador_dominios], el elemento «Buscador de dominios» del editor
 *   atómico (`e-digitalisimo-domain-finder`) y, solo, el carrito vacío (nunca un callejón sin salida).
 */
class Digitalisimo_Hosting_Finder {
	const NONCE  = 'digitalisimo_hosting_finder';
	const HANDLE = 'digitalisimo-hosting-finder';
	/** Cabecera propia del buscador: un navegador en otro sitio no la puede mandar sin el permiso CORS que la REST no da. */
	const HEADER = 'x-digitalisimo-finder';
	/** Tope de extensiones por consulta (las primeras del orden configurado; la escrita siempre entra). */
	const MAX_EXTS = 12;
	/** Búsquedas por minuto y visitante. */
	const RATE = 15;
	/**
	 * Consultas del buscador a Namecheap por minuto y por hora en TODO el sitio (las que no salen de la caché): la cuota
	 * de la API es de la cuenta y la comparten el carrito y la venta automática. Filtro digitalisimo_hosting_finder_global.
	 */
	const GLOBAL_MINUTE = 30;
	const GLOBAL_HOUR   = 400;
	/** Una etiqueta de dominio: letras, números y guiones, sin guion al principio ni al final, hasta 63. */
	const LABEL = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';
	/** Las populares primero (sólo cuentan las que tienen producto; las demás siguen en el orden de la lista). */
	const DEFAULT_ORDER = '.com, .mx, .com.mx, .net, .lat';
	const DEFAULT_REQUIREMENTS = ".mx|Requisito: el titular debe dar sus datos reales y completos (persona o empresa).\n.com.mx|Requisito: el titular debe dar sus datos reales y completos (persona o empresa).";

	public static function init() {
		add_shortcode( 'digitalisimo_buscador_dominios', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'digitalisimo_domain_finder', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'footer_config' ), 5 );
		// Carrito vacío: el clásico y el de bloques muestran el buscador.
		add_action( 'woocommerce_cart_is_empty', array( __CLASS__, 'empty_cart' ), 20 );
		add_filter( 'render_block', array( __CLASS__, 'empty_cart_block' ), 10, 2 );
		// Elemento del editor atómico (sólo con el núcleo de DIGITALÍSIMO Elements y los elementos atómicos activos).
		add_action( 'elementor/widgets/register', array( __CLASS__, 'atomic_widget' ), 20 );
	}

	private static function on() { $o = Digitalisimo_Hosting::settings(); return ! empty( $o['enabled'] ); }

	// ── Lo que escribió el visitante ─────────────────────────────────────────────────────────────────────────────

	/**
	 * array( 'label' => 'minegocio', 'ext' => '.mx' (la escrita, se venda o no; '' si no escribió), 'display' => lo que se
	 * muestra (Unicode en un nombre con acentos o ñ), 'error' => '' ). Un nombre con acentos o ñ (IDN) se convierte a su
	 * forma xn-- con idn_to_ascii (UTS #46); sin la extensión intl de PHP se pide sin acentos.
	 */
	public static function parse( $raw ) {
		$raw = (string) $raw;
		$fail = function ( $m ) { return array( 'label' => '', 'ext' => '', 'display' => '', 'error' => $m ); };
		if ( strlen( $raw ) > 300 ) return $fail( 'El nombre es demasiado largo: un dominio tiene como máximo 63 letras antes de la extensión.' );
		$q = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $raw ), 'UTF-8' ) : strtolower( trim( $raw ) );
		$q = Digitalisimo_Hosting_Checkout::clean_domain( preg_replace( '/\s+/u', '', $q ) );
		if ( '' === $q ) return $fail( 'Escribe el nombre que quieres para tu dominio, por ejemplo minegocio.' );
		$display = $q;
		if ( preg_match( '/[^\x00-\x7F]/', $q ) ) {
			$ascii = function_exists( 'idn_to_ascii' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) ? idn_to_ascii( $q, defined( 'IDNA_NONTRANSITIONAL_TO_ASCII' ) ? IDNA_NONTRANSITIONAL_TO_ASCII : 0, INTL_IDNA_VARIANT_UTS46 ) : false;
			if ( ! is_string( $ascii ) || '' === $ascii || preg_match( '/[^\x00-\x7F]/', $ascii ) ) return $fail( 'Escribe el nombre sin acentos, ñ ni símbolos: sólo letras, números y guiones.' );
			$q = strtolower( $ascii );
		}
		$sold = array_keys( Digitalisimo_Hosting::domain_products() );
		usort( $sold, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		$label = $q; $ext = '';
		foreach ( $sold as $e ) {
			if ( strlen( $q ) > strlen( $e ) && substr( $q, -strlen( $e ) ) === $e && false === strpos( substr( $q, 0, -strlen( $e ) ), '.' ) ) { $label = substr( $q, 0, -strlen( $e ) ); $ext = $e; break; }
		}
		if ( '' === $ext && false !== strpos( $q, '.' ) ) {
			$label = substr( $q, 0, strpos( $q, '.' ) );
			$ext = Digitalisimo_Hosting_Products::normalize( substr( $q, strpos( $q, '.' ) ) );
			if ( '' === $ext ) return $fail( 'Escribe sólo el nombre (minegocio) o el nombre con su extensión (minegocio.mx).' );
			// Dos niveles sólo si es un sufijo conocido (.com.mx); si no, es un subdominio (sub.minegocio.com).
			if ( false !== strpos( ltrim( $ext, '.' ), '.' ) && ! in_array( ltrim( $ext, '.' ), Digitalisimo_Hosting_Provisioning::SECOND_LEVEL, true ) ) return $fail( 'Escribe sólo el nombre (minegocio) o el nombre con su extensión (minegocio.mx), sin subdominios.' );
		}
		if ( false !== strpos( $label, '.' ) ) return $fail( 'Escribe sólo el nombre (minegocio) o el nombre con su extensión (minegocio.mx), sin subdominios.' );
		if ( strlen( $label ) > 63 ) return $fail( 'El nombre es demasiado largo: un dominio tiene como máximo 63 letras antes de la extensión.' );
		if ( ! preg_match( self::LABEL, $label ) ) return $fail( '' === $label ? 'Escribe el nombre que quieres para tu dominio, por ejemplo minegocio.' : 'Usa sólo letras, números y guiones, sin espacios ni símbolos, y sin guion al principio ni al final.' );
		// Guiones en las posiciones 3 y 4 sólo en un nombre internacional válido (xn--).
		if ( '--' === substr( $label, 2, 2 ) && 0 !== strpos( $label, 'xn--' ) ) return $fail( 'El nombre no puede llevar dos guiones seguidos en la tercera y cuarta posición.' );
		if ( 0 === strpos( $label, 'xn--' ) && function_exists( 'idn_to_utf8' ) && defined( 'INTL_IDNA_VARIANT_UTS46' ) && false === idn_to_utf8( $label, 0, INTL_IDNA_VARIANT_UTS46 ) ) return $fail( 'El nombre internacional no es válido.' );
		if ( $display === $q || ! preg_match( '/[^\x00-\x7F]/', $display ) ) $display = '';
		else $display = preg_replace( '/\..*$/', '', $display );
		return array( 'label' => $label, 'ext' => $ext, 'display' => $display, 'error' => '' );
	}

	// ── Extensiones, precios y avisos ───────────────────────────────────────────────────────────────────────────

	/** Las extensiones con producto en el orden configurado (populares primero); las demás, en el de la lista. */
	public static function ordered() {
		$map = Digitalisimo_Hosting::domain_products();
		$order = array();
		foreach ( preg_split( '/[\s,;|]+/', (string) ( Digitalisimo_Hosting::settings()['finder_order'] ?? self::DEFAULT_ORDER ) ) as $e ) {
			$e = Digitalisimo_Hosting_Products::normalize( $e );
			if ( '' !== $e && isset( $map[ $e ] ) && ! in_array( $e, $order, true ) ) $order[] = $e;
		}
		foreach ( array_keys( $map ) as $e ) if ( ! in_array( $e, $order, true ) ) $order[] = $e;
		return $order;
	}

	/** Extensión => aviso de requisitos («.mx|Requisito: …», una por línea). */
	public static function requirements() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( Digitalisimo_Hosting::settings()['finder_requirements'] ?? self::DEFAULT_REQUIREMENTS ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$e = Digitalisimo_Hosting_Products::normalize( $parts[0] );
			if ( '' !== $e && '' !== ( $parts[1] ?? '' ) ) $out[ $e ] = sanitize_text_field( $parts[1] );
		}
		return $out;
	}

	/** Un monto con el símbolo y los separadores de la tienda («$1,470»). */
	public static function money( $amount ) {
		$amount = (float) $amount;
		$dec = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$dec = abs( $amount - round( $amount ) ) < 0.005 ? 0 : $dec;
		$sym = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		$num = number_format( $amount, $dec, function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.', function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',' );
		return $sym . $num;
	}

	/** Precio que ve el comprador (primer año) y el de renovación, del producto de WooCommerce. null si no se vende. */
	public static function prices( $product_id ) {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null;
		if ( ! $p || '' === (string) $p->get_price() ) return null;
		// Sólo lo que se puede comprar: publicado (un producto privado o en borrador lo rechazaría la Store API).
		if ( function_exists( 'get_post_status' ) && false !== get_post_status( (int) $product_id ) && 'publish' !== get_post_status( (int) $product_id ) ) return null;
		if ( method_exists( $p, 'is_purchasable' ) && ! $p->is_purchasable() ) return null;
		$now = function_exists( 'wc_get_price_to_display' ) ? (float) wc_get_price_to_display( $p ) : (float) $p->get_price();
		$regular = method_exists( $p, 'get_regular_price' ) ? (string) $p->get_regular_price() : '';
		$renew = '' !== $regular ? ( function_exists( 'wc_get_price_to_display' ) ? (float) wc_get_price_to_display( $p, array( 'price' => $regular ) ) : (float) $regular ) : $now;
		return array( 'price' => $now, 'price_text' => self::money( $now ), 'renewal' => max( $now, $renew ), 'renewal_text' => self::money( max( $now, $renew ) ) );
	}

	/** «Se compran juntos»: los planes elegidos (o los de hosting), hasta 4, sólo productos simples publicados con precio. */
	public static function together() {
		$o = Digitalisimo_Hosting::settings();
		$raw = trim( (string) ( $o['finder_upsell_ids'] ?? '' ) );
		if ( '' === $raw ) $raw = (string) ( $o['hosting_product_ids'] ?? '' );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $raw ) ) ) ) );
		if ( ! $ids && '' !== trim( (string) ( $o['hosting_category_ids'] ?? '' ) ) && function_exists( 'get_posts' ) ) {
			$cats = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $o['hosting_category_ids'] ) ) );
			if ( $cats ) $ids = array_map( 'intval', (array) get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 4, 'fields' => 'ids', 'orderby' => 'menu_order', 'order' => 'ASC', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ) ) ) ) );
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( count( $out ) >= 4 ) break;
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $p || ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $id ) ) ) continue;
			if ( method_exists( $p, 'is_type' ) && ! $p->is_type( 'simple' ) ) continue;
			$prices = self::prices( $id );
			if ( ! $prices ) continue;
			$desc = method_exists( $p, 'get_short_description' ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $p->get_short_description() ) ) ) : '';
			$out[] = array( 'id' => (int) $id, 'name' => (string) $p->get_name(), 'price_text' => $prices['price_text'], 'description' => function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 140 ) : substr( $desc, 0, 140 ) );
		}
		return $out;
	}

	// ── Disponibilidad (caché + una consulta en lote) ───────────────────────────────────────────────────────────

	/** array( kind ok|error, results => dominio => array( available, premium ) ). */
	public static function availability( array $domains ) {
		$out = array(); $miss = array();
		foreach ( $domains as $d ) {
			$c = Digitalisimo_Hosting_Checkout::cached( $d );
			if ( $c ) $out[ $d ] = array( 'available' => ! empty( $c['available'] ), 'premium' => ! empty( $c['premium'] ) );
			else $miss[] = $d;
		}
		if ( $miss ) {
			if ( ! self::global_slot() ) return array( 'kind' => 'busy', 'results' => $out );
			$nc = Digitalisimo_Hosting_Namecheap::from_settings();
			$r = $nc->check_many( $miss, Digitalisimo_Hosting_Checkout::TIMEOUT );
			// Si Namecheap rechaza el lote entero (una extensión que no atiende), se consultan una por una, en el orden de
			// la lista y mientras quede cupo: las demás siguen saliendo.
			if ( 'api' === $r['kind'] && count( $miss ) > 1 ) {
				$r = array( 'kind' => 'ok', 'results' => array() );
				foreach ( $miss as $d ) {
					if ( ! self::global_slot() ) { $r['kind'] = 'busy'; break; }
					$one = $nc->check_many( array( $d ), Digitalisimo_Hosting_Checkout::TIMEOUT );
					if ( 'ok' === $one['kind'] ) $r['results'] += $one['results'];
					elseif ( 'api' !== $one['kind'] ) { $r['kind'] = 'error'; break; }
				}
			}
			foreach ( $r['results'] ?? array() as $d => $res ) { Digitalisimo_Hosting_Checkout::remember( $d, $res['available'], $res['premium'] ); $out[ $d ] = $res; }
			if ( 'ok' !== $r['kind'] ) return array( 'kind' => 'busy' === $r['kind'] ? 'busy' : 'error', 'results' => $out );
		}
		return array( 'kind' => 'ok', 'results' => $out );
	}

	/** ¿Queda cupo en el tope del sitio (por minuto y por hora) para otra consulta a Namecheap? Lo cuenta si sí. */
	private static function global_slot() {
		$limits = (array) apply_filters( 'digitalisimo_hosting_finder_global', array( 'minute' => self::GLOBAL_MINUTE, 'hour' => self::GLOBAL_HOUR ) );
		$keys = array( 'minute' => array( 'digitalisimo_hosting_finder_nc_m', MINUTE_IN_SECONDS ), 'hour' => array( 'digitalisimo_hosting_finder_nc_h', HOUR_IN_SECONDS ) );
		$now = array();
		foreach ( $keys as $k => $def ) {
			$v = get_transient( $def[0] );
			$now[ $k ] = is_array( $v ) ? $v : array( 'n' => 0, 'until' => time() + $def[1] );
			if ( (int) $now[ $k ]['n'] >= (int) ( $limits[ $k ] ?? PHP_INT_MAX ) ) return false;
		}
		// La ventana empieza con la primera consulta y no se alarga con las siguientes.
		foreach ( $keys as $k => $def ) { $now[ $k ]['n']++; set_transient( $def[0], $now[ $k ], max( 1, (int) $now[ $k ]['until'] - time() ) ); }
		return true;
	}

	// ── La búsqueda ─────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * La respuesta del buscador para lo escrito: array( query, notice, main, rows, alternatives, together ) o WP_Error.
	 * Cada fila: domain, display, ext, status (available|taken|premium|unknown), product_id, precios en texto, requirement
	 * y, si está ocupado y hay producto de transferencia de su extensión, transfer (product_id, price_text).
	 */
	public static function search( $raw ) {
		$p = self::parse( $raw );
		if ( '' !== $p['error'] ) return new WP_Error( 'digitalisimo_finder_invalid', $p['error'], array( 'status' => 400 ) );
		$map = Digitalisimo_Hosting::domain_products();
		$order = self::ordered();
		if ( ! $order ) return new WP_Error( 'digitalisimo_finder_empty', 'Por ahora no hay dominios a la venta.', array( 'status' => 503 ) );
		$typed_sold = '' !== $p['ext'] && isset( $map[ $p['ext'] ] );
		$exts = array_slice( $typed_sold ? array_values( array_unique( array_merge( array( $p['ext'] ), $order ) ) ) : $order, 0, self::MAX_EXTS );
		// El orden de las filas es el configurado, aunque la escrita se haya puesto primero para que no la corte el tope.
		$exts = array_values( array_filter( $order, function ( $e ) use ( $exts ) { return in_array( $e, $exts, true ); } ) );
		$domains = array();
		foreach ( $exts as $e ) $domains[ $e ] = $p['label'] . $e;
		$nc = Digitalisimo_Hosting_Namecheap::from_settings();
		if ( ! $nc->configured() ) return new WP_Error( 'digitalisimo_finder_unavailable', 'La búsqueda de dominios no está disponible en este momento.', array( 'status' => 503 ) );
		$av = self::availability( array_values( $domains ) );
		if ( 'busy' === $av['kind'] && ! $av['results'] ) return new WP_Error( 'digitalisimo_finder_busy', 'Hay muchas búsquedas en este momento. Intenta de nuevo en un minuto.', array( 'status' => 429 ) );
		if ( 'ok' !== $av['kind'] && ! $av['results'] ) return new WP_Error( 'digitalisimo_finder_unavailable', 'No pudimos consultar los dominios en este momento. Intenta de nuevo en un minuto.', array( 'status' => 503 ) );
		$req = self::requirements();
		$transfers = Digitalisimo_Hosting_Products::report( (string) Digitalisimo_Hosting::settings()['domain_products'] )['transfer'];
		$rows = array();
		foreach ( $domains as $e => $d ) {
			$res = $av['results'][ $d ] ?? null;
			$status = ! $res ? 'unknown' : ( ! $res['available'] ? 'taken' : ( $res['premium'] ? 'premium' : 'available' ) );
			$row = array( 'domain' => $d, 'display' => '' !== $p['display'] ? $p['display'] . $e : '', 'ext' => $e, 'status' => $status, 'product_id' => (int) $map[ $e ], 'requirement' => $req[ $e ] ?? '' );
			$prices = self::prices( $map[ $e ] );
			if ( ! $prices ) { $row['status'] = 'available' === $status ? 'unknown' : $status; $row['product_id'] = 0; }
			else $row += array( 'price_text' => $prices['price_text'], 'renewal_text' => $prices['renewal_text'] );
			if ( 'taken' === $status ) {
				$tid = (int) ( $transfers[ $e ] ?? $transfers['*'] ?? 0 );
				$tp = $tid ? self::prices( $tid ) : null;
				if ( $tp ) $row['transfer'] = array( 'product_id' => $tid, 'price_text' => $tp['price_text'] );
			}
			$rows[ $e ] = $row;
		}
		$want = $typed_sold ? $p['ext'] : ( isset( $rows['.com'] ) ? '.com' : $exts[0] );
		$main = $rows[ $want ];
		$instead = ''; $reason = '';
		if ( 'available' !== $main['status'] ) {
			// Por qué no se ofrece el que buscó: ocupado, premium o no se pudo consultar (nunca «ocupado» si no lo está).
			foreach ( $rows as $r ) if ( 'available' === $r['status'] ) { $instead = $main['domain']; $reason = $main['status']; $main = $r; break; }
		}
		$alternatives = array();
		foreach ( $rows as $r ) if ( 'available' === $r['status'] && $r['domain'] !== $main['domain'] && count( $alternatives ) < 3 ) $alternatives[] = $r['domain'];
		$notice = '';
		if ( '' !== $p['ext'] && ! $typed_sold ) $notice = 'Aquí no vendemos dominios ' . $p['ext'] . '. Estas son las extensiones que sí tenemos para «' . $p['label'] . '».';
		$out = array(
			'query'        => $p['label'],
			'notice'       => $notice,
			'main'         => $main,
			'instead_of'   => $instead,
			'reason'       => $reason,
			// Si el principal está ocupado, su transferencia y las libres se ofrecen junto a él.
			'taken'        => 'taken' === $reason ? $rows[ $want ] : null,
			'rows'         => array_values( array_filter( $rows, function ( $r ) use ( $main ) { return $r['domain'] !== $main['domain']; } ) ),
			'alternatives' => $alternatives,
			'together'     => self::together(),
			'partial'      => 'ok' !== $av['kind'],
		);
		return $out;
	}

	// ── Punto público (REST) ────────────────────────────────────────────────────────────────────────────────────

	public static function routes() {
		register_rest_route( 'digitalisimo-hosting/v1', '/finder', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest' ), 'permission_callback' => array( __CLASS__, 'permission' ), 'args' => array( 'q' => array( 'required' => true, 'type' => 'string' ) ) ) );
	}

	/**
	 * El nonce del buscador o, en una página servida desde la caché con el nonce vencido, la cabecera propia del buscador
	 * desde este mismo sitio (Origin igual al del sitio o sin Origin): un navegador en otro sitio no puede mandar esa
	 * cabecera porque la REST no la permite en CORS.
	 */
	public static function permission( $request ) {
		$nonce = (string) $request->get_param( 'nonce' );
		if ( '' !== $nonce && wp_verify_nonce( $nonce, self::NONCE ) ) return true;
		if ( '1' === (string) $request->get_header( self::HEADER ) && self::same_origin( (string) $request->get_header( 'origin' ) ) ) return true;
		return new WP_Error( 'digitalisimo_finder_forbidden', 'Recarga la página e intenta de nuevo.', array( 'status' => 403 ) );
	}

	private static function same_origin( $origin ) {
		if ( '' === $origin ) return true;
		$site = wp_parse_url( home_url( '/' ) );
		$o = wp_parse_url( $origin );
		return is_array( $o ) && is_array( $site ) && strtolower( (string) ( $o['host'] ?? '' ) ) === strtolower( (string) ( $site['host'] ?? '' ) );
	}

	public static function rest( $request ) {
		if ( ! self::on() ) return new WP_Error( 'digitalisimo_finder_off', 'La búsqueda de dominios no está disponible.', array( 'status' => 503 ) );
		$q = $request->get_param( 'q' );
		if ( ! is_string( $q ) ) return new WP_Error( 'digitalisimo_finder_invalid', 'Escribe el nombre que quieres para tu dominio.', array( 'status' => 400 ) );
		if ( ! Digitalisimo_Hosting::rate_limit( 'domain-finder', (int) apply_filters( 'digitalisimo_hosting_finder_rate', self::RATE ) ) ) return new WP_Error( 'digitalisimo_finder_rate', 'Hiciste muchas búsquedas seguidas. Espera un minuto e intenta de nuevo.', array( 'status' => 429 ) );
		$r = self::search( $q );
		if ( is_wp_error( $r ) ) return $r;
		return rest_ensure_response( $r );
	}

	// ── Pintar el buscador ──────────────────────────────────────────────────────────────────────────────────────

	public static function register_assets() {
		if ( ! function_exists( 'wp_register_script' ) ) return;
		wp_register_style( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/finder.css', array(), DIGITALISIMO_HOSTING_VERSION );
		wp_register_script( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/finder.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
	}

	/** Lo que necesita el script (rutas y nonces; nada de precios ni de la cuenta de Namecheap). */
	public static function config() {
		return array(
			'endpoint'   => rest_url( 'digitalisimo-hosting/v1/finder' ),
			'nonce'      => wp_create_nonce( self::NONCE ),
			'store'      => rest_url( 'wc/store/v1/' ),
			'storeNonce' => wp_create_nonce( 'wc_store_api' ),
			'checkout'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
			'cart'       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
		);
	}

	/** Pone la configuración delante del script si alguien lo usó en la página (shortcode, elemento atómico o carrito). */
	public static function footer_config() {
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( self::HANDLE, 'enqueued' ) ) return;
		wp_add_inline_script( self::HANDLE, 'window.digitalisimoFinder=' . wp_json_encode( self::config() ) . ';', 'before' );
		if ( ! wp_style_is( self::HANDLE, 'enqueued' ) ) wp_enqueue_style( self::HANDLE );
	}

	public static function enqueue() {
		if ( ! function_exists( 'wp_enqueue_script' ) ) return;
		if ( function_exists( 'wp_script_is' ) && ! wp_script_is( self::HANDLE, 'registered' ) ) self::register_assets();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * El marcado del buscador (el mismo que pinta la plantilla del elemento atómico): un formulario de búsqueda con su
	 * etiqueta, la región de avisos y la de resultados (aria-live). Sin JavaScript, el formulario recarga con ?dominio=
	 * y el script lo busca al cargar.
	 */
	public static function html( array $a = array() ) {
		static $n = 0;
		$id = 'dhf-' . ( ++$n );
		$placeholder = trim( (string) ( $a['placeholder'] ?? '' ) );
		$button = trim( (string) ( $a['button'] ?? '' ) );
		$title = trim( (string) ( $a['title'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo rellena el campo de búsqueda.
		$value = isset( $_GET['dominio'] ) && is_string( $_GET['dominio'] ) ? substr( sanitize_text_field( wp_unslash( $_GET['dominio'] ) ), 0, 253 ) : '';
		return '<div class="digitalisimo-finder" data-digitalisimo-finder>'
			. '<form class="digitalisimo-finder__form" role="search" method="get" action="">'
			. '<label class="digitalisimo-finder__label" for="' . esc_attr( $id ) . '">' . esc_html( '' !== $title ? $title : 'Busca tu dominio' ) . '</label>'
			. '<div class="digitalisimo-finder__bar">'
			. '<input id="' . esc_attr( $id ) . '" class="digitalisimo-finder__input" type="search" name="dominio" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( '' !== $placeholder ? $placeholder : 'minegocio' ) . '" inputmode="url" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="253" required aria-describedby="' . esc_attr( $id ) . '-hint">'
			. '<button class="digitalisimo-finder__submit" type="submit">' . esc_html( '' !== $button ? $button : 'Buscar' ) . '</button>'
			. '</div>'
			. '<p class="digitalisimo-finder__hint" id="' . esc_attr( $id ) . '-hint">Escribe sólo el nombre (minegocio) o el nombre con su extensión (minegocio.mx).</p>'
			. '</form>'
			. '<div class="digitalisimo-finder__status" role="status" aria-live="polite"></div>'
			. '<div class="digitalisimo-finder__results" aria-live="polite" aria-busy="false"></div>'
			. '</div>';
	}

	/** [digitalisimo_buscador_dominios placeholder="minegocio" boton="Buscar" titulo="Busca tu dominio"] */
	public static function shortcode( $atts = array() ) {
		if ( ! self::on() || ! function_exists( 'wc_get_product' ) ) return '';
		$atts = is_array( $atts ) ? $atts : array();
		self::enqueue();
		return self::html( array( 'placeholder' => $atts['placeholder'] ?? '', 'button' => $atts['boton'] ?? ( $atts['button'] ?? '' ), 'title' => $atts['titulo'] ?? ( $atts['title'] ?? '' ) ) );
	}

	/** Carrito vacío (clásico): el buscador debajo del aviso. */
	public static function empty_cart() {
		if ( ! self::on() ) return;
		self::enqueue();
		echo '<div class="digitalisimo-finder-empty-cart"><h2>¿Buscas un dominio?</h2>' . self::html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapa todo.
	}

	/** Carrito vacío (bloques): el buscador dentro del bloque «carrito vacío». */
	public static function empty_cart_block( $content, $block ) {
		if ( 'woocommerce/empty-cart-block' !== ( $block['blockName'] ?? '' ) || ! self::on() ) return $content;
		self::enqueue();
		// Dentro del bloque «carrito vacío» (antes de su cierre): fuera de él también se vería con el carrito lleno.
		$finder = '<div class="digitalisimo-finder-empty-cart"><h2>¿Buscas un dominio?</h2>' . self::html() . '</div>';
		$at = strrpos( $content, '</div>' );
		return false === $at ? $content . $finder : substr( $content, 0, $at ) . $finder . substr( $content, $at );
	}

	// ── Elemento del editor atómico ─────────────────────────────────────────────────────────────────────────────

	/** Se registra sólo con el núcleo atómico cargado y los elementos atómicos activos; si no, no carga nada. */
	public static function atomic_widget( $manager ) {
		if ( ! class_exists( '\\Elementor\\Modules\\AtomicWidgets\\Elements\\Base\\Atomic_Widget_Base' ) || ! is_object( $manager ) ) return false;
		$plugin = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance : null;
		if ( ! is_object( $plugin ) || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) || ! $plugin->experiments->is_feature_active( 'e_atomic_elements' ) ) return false;
		if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( 'e-digitalisimo-domain-finder' ) ) return false;
		if ( ! self::on() ) return false;
		self::register_assets();
		require_once __DIR__ . '/atomic/class-atomic-domain-finder.php';
		$manager->register( new \Digitalisimo\Hosting\Atomic_Domain_Finder() );
		return true;
	}
}
