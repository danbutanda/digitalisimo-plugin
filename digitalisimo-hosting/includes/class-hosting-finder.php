<?php
defined( 'ABSPATH' ) || exit;

/**
 * Buscador de dominios estilo registrador (pedido del propietario del 2026-11-26; método «tienda de hosting y dominios»
 * del cerebro, tomado de cómo vende Namecheap).
 *
 * - **Una búsqueda, todas las extensiones:** el visitante escribe sólo el nombre (o nombre.ext) y se consultan a la vez
 *   todas las extensiones que tienen producto de dominio (los productos o variaciones marcados como «Dominio» en su
 *   editor: Digitalisimo_Hosting_Products, nunca IDs fijos), cada una con el registrador de su extensión: en UNA llamada a
 *   namecheap.domains.check (Digitalisimo_Hosting_Namecheap::check_many()) y, las que van con Dynadot, de una en una
 *   (Digitalisimo_Hosting_Dynadot::check_many(); una cuenta Regular busca un dominio por comando). Cada resultado se guarda unos minutos con la misma clave que usa el
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
	/** Búsquedas por minuto y por hora de un visitante (IP; en IPv6, su /64). */
	const RATE = 15;
	const RATE_HOUR = 60;
	/**
	 * Consultas del buscador a Namecheap por minuto y por hora en TODO el sitio (las que no salen de la caché): la cuota
	 * de la API es de la cuenta y la comparten el carrito y la venta automática. Filtro digitalisimo_hosting_finder_global.
	 */
	const GLOBAL_MINUTE = 30;
	const GLOBAL_HOUR   = 400;
	/** Una etiqueta de dominio: letras, números y guiones, sin guion al principio ni al final, hasta 63. */
	const LABEL = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';
	/** Las populares primero (sólo cuentan las que tienen producto; las demás siguen en el orden de la lista). */
	const DEFAULT_ORDER = array( '.com', '.mx', '.com.mx', '.net', '.lat' );
	const DEFAULT_REQUIREMENTS = array( '.mx' => 'Requisito: el titular debe dar sus datos reales y completos (persona o empresa).', '.com.mx' => 'Requisito: el titular debe dar sus datos reales y completos (persona o empresa).' );

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
		$pref = Digitalisimo_Hosting::settings()['finder_order'] ?? self::DEFAULT_ORDER;
		foreach ( is_array( $pref ) ? $pref : self::DEFAULT_ORDER as $e ) {
			$e = Digitalisimo_Hosting_Products::normalize( $e );
			if ( '' !== $e && isset( $map[ $e ] ) && ! in_array( $e, $order, true ) ) $order[] = $e;
		}
		foreach ( array_keys( $map ) as $e ) if ( ! in_array( $e, $order, true ) ) $order[] = $e;
		return $order;
	}

	/** Extensión => aviso de requisitos (una fila por extensión en los ajustes). */
	public static function requirements() {
		$raw = Digitalisimo_Hosting::settings()['finder_requirements'] ?? self::DEFAULT_REQUIREMENTS;
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $e => $text ) { $e = Digitalisimo_Hosting_Products::normalize( $e ); if ( '' !== $e && is_scalar( $text ) && '' !== trim( (string) $text ) ) $out[ $e ] = sanitize_text_field( (string) $text ); }
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

	/**
	 * El plan que se agrega de un producto de hosting: el mismo si es simple; si es variable, su variación anual (o la
	 * primera que se venda). 0 si no hay.
	 */
	private static function sellable_plan( $id ) {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : null;
		if ( ! $p ) return 0;
		if ( ! method_exists( $p, 'is_type' ) || ! $p->is_type( 'variable' ) ) return (int) $id;
		$best = 0;
		foreach ( Digitalisimo_Hosting_Products::catalog()['hosting'] as $h ) {
			if ( (int) $h['parent'] !== (int) $id ) continue;
			if ( 12 === (int) $h['period'] ) return (int) $h['id'];
			if ( ! $best ) $best = (int) $h['id'];
		}
		return $best;
	}

	/**
	 * «Se compran juntos»: los planes elegidos en los ajustes o, si no hay, los productos marcados como «Hosting»; hasta 4,
	 * publicados y con precio (de un producto variable, su variación anual).
	 */
	public static function together() {
		$o = Digitalisimo_Hosting::settings();
		$ids = array_values( array_filter( array_map( 'intval', is_array( $o['finder_upsell'] ?? null ) ? $o['finder_upsell'] : array() ) ) );
		if ( ! $ids ) foreach ( Digitalisimo_Hosting_Products::catalog()['hosting'] as $h ) { $k = $h['parent'] ? (int) $h['parent'] : (int) $h['id']; if ( ! in_array( $k, $ids, true ) ) $ids[] = $k; }
		$out = array(); $seen = array();
		foreach ( $ids as $pick ) {
			if ( count( $out ) >= 4 ) break;
			$id = self::sellable_plan( $pick );
			if ( ! $id || isset( $seen[ $id ] ) ) continue;
			$seen[ $id ] = true;
			$p = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $p || ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $id ) ) ) continue;
			if ( method_exists( $p, 'is_type' ) && ! $p->is_type( 'simple' ) && ! $p->is_type( 'variation' ) ) continue;
			$prices = self::prices( $id );
			if ( ! $prices ) continue;
			$desc = method_exists( $p, 'get_short_description' ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $p->get_short_description() ) ) ) : '';
			$out[] = array( 'id' => (int) $id, 'name' => (string) $p->get_name(), 'price_text' => $prices['price_text'], 'description' => function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 140 ) : substr( $desc, 0, 140 ) );
		}
		return $out;
	}

	// ── Disponibilidad (caché + una consulta en lote) ───────────────────────────────────────────────────────────

	/** Extensiones que rompieron un lote de Namecheap: no entran en el siguiente durante 6 horas. */
	const BAD_EXTS = 'digitalisimo_hosting_finder_bad_exts';

	/** Extensión => hasta cuándo queda fuera del lote. */
	public static function bad_exts() {
		$v = get_transient( self::BAD_EXTS );
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $e => $until ) if ( (int) $until > time() ) $out[ $e ] = (int) $until;
		return $out;
	}

	private static function mark_bad( $ext ) {
		$bad = self::bad_exts();
		$bad[ $ext ] = time() + 6 * HOUR_IN_SECONDS;
		set_transient( self::BAD_EXTS, $bad, 6 * HOUR_IN_SECONDS );
	}

	/** Nombres distintos seguidos que fallan en una extensión antes de apartarla (sin decir que la extensión no se atiende). */
	const BAD_STREAK = 3;
	const FAILS = 'digitalisimo_hosting_finder_ext_fails';

	/** ¿El registrador dice que no atiende la extensión (y no sólo que ese nombre falló)? */
	public static function tld_unsupported( $text ) { return (bool) preg_match( '/\b(tld|extension)\b.{0,40}(not\s+supported|unsupported|not\s+available|not\s+offered|invalid)|not\s+supported\s+(tld|extension)|unsupported\s+(tld|extension)|2030280/i', (string) $text ); }

	/**
	 * Un nombre que el registrador no pudo consultar: sólo ese nombre sale «no se pudo consultar». La extensión se aparta
	 * (6 horas) sólo si el error dice que no se atiende, o tras BAD_STREAK nombres distintos fallidos seguidos.
	 */
	private static function name_failed( $domain, $text ) {
		$ext = substr( $domain, strpos( $domain, '.' ) );
		if ( self::tld_unsupported( $text ) ) { self::mark_bad( $ext ); return; }
		$f = get_transient( self::FAILS ); $f = is_array( $f ) ? $f : array();
		$names = array_values( array_unique( array_merge( (array) ( $f[ $ext ] ?? array() ), array( $domain ) ) ) );
		$f[ $ext ] = array_slice( $names, -self::BAD_STREAK );
		if ( count( $names ) >= self::BAD_STREAK ) { self::mark_bad( $ext ); unset( $f[ $ext ] ); }
		set_transient( self::FAILS, $f, HOUR_IN_SECONDS );
	}

	/** Un nombre de la extensión respondió bien: la racha de fallos se reinicia. */
	private static function name_ok( $domain ) {
		$f = get_transient( self::FAILS );
		$ext = substr( $domain, strpos( $domain, '.' ) );
		if ( is_array( $f ) && isset( $f[ $ext ] ) ) { unset( $f[ $ext ] ); set_transient( self::FAILS, $f, HOUR_IN_SECONDS ); }
	}

	/**
	 * array( kind ok|error|busy, results => dominio => array( available, premium ) ). $trusted: la búsqueda trae el nonce
	 * válido del buscador (puede usar todo el cupo del sitio; sin él, sólo la mitad).
	 */
	public static function availability( array $domains, $trusted = true ) {
		$out = array(); $miss = array();
		foreach ( $domains as $d ) {
			$c = Digitalisimo_Hosting_Checkout::cached( $d );
			if ( $c ) $out[ $d ] = array( 'available' => ! empty( $c['available'] ), 'premium' => ! empty( $c['premium'] ) );
			else $miss[] = $d;
		}
		// Cada dominio con el registrador de su extensión: Namecheap en UNA consulta en lote; Dynadot (una cuenta Regular busca
		// un dominio por comando) de uno en uno, cada consulta con su cupo y con el mismo tope de tiempo. Un registrador sin
		// cuenta puesta no se consulta (sus filas salen «sin consultar»).
		$groups = array();
		foreach ( $miss as $d ) $groups[ Digitalisimo_Hosting_Registrars::id_for_domain( $d ) ][] = $d;
		$kind = 'ok';
		foreach ( $groups as $rid => $list ) {
			$nc = Digitalisimo_Hosting_Registrars::make( $rid );
			if ( ! $nc->configured() ) { $kind = 'error'; continue; }
			$r = 'dynadot' === $rid ? self::one_by_one( $nc, $list, $trusted ) : self::batch( $nc, $list, $trusted );
			foreach ( $r['results'] ?? array() as $d => $res ) { Digitalisimo_Hosting_Checkout::remember( $d, $res['available'], $res['premium'] ); $out[ $d ] = $res; }
			if ( 'ok' !== $r['kind'] ) { $kind = 'busy' === $r['kind'] ? 'busy' : 'error'; if ( 'busy' === $kind ) break; }
		}
		return array( 'kind' => $kind, 'results' => $out );
	}

	/** Segundos que puede tardar la consulta de una en una (20 s y nunca más que max_execution_time). */
	private static function budget() {
		$budget = (float) apply_filters( 'digitalisimo_hosting_finder_fallback_seconds', 20 );
		$max = (int) ini_get( 'max_execution_time' );
		if ( $max > 0 ) $budget = min( $budget, $max - ( microtime( true ) - (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ) ) - 5 );
		return $budget;
	}

	/**
	 * Namecheap: todo en una consulta. Si rechaza el lote entero (una extensión que no atiende), se consultan una por una,
	 * en el orden de la lista, mientras quede cupo y sin pasar del tiempo total; la que también falla sola queda fuera de
	 * los lotes siguientes por 6 horas.
	 */
	private static function batch( $nc, array $miss, $trusted ) {
		if ( ! self::global_slot( $trusted ) ) return array( 'kind' => 'busy', 'results' => array() );
		$r = $nc->check_many( $miss, Digitalisimo_Hosting_Checkout::TIMEOUT );
		if ( 'api' === $r['kind'] && count( $miss ) > 1 ) {
			$r = array( 'kind' => 'ok', 'results' => array() );
			$deadline = microtime( true ) + self::budget();
			foreach ( $miss as $d ) {
				$left = $deadline - microtime( true );
				if ( $left < 1 ) { $r['kind'] = 'error'; break; }
				if ( ! self::global_slot( $trusted ) ) { $r['kind'] = 'busy'; break; }
				$one = $nc->check_many( array( $d ), (int) max( 1, min( Digitalisimo_Hosting_Checkout::TIMEOUT, floor( $left ) ) ) );
				if ( 'ok' === $one['kind'] ) { $r['results'] += $one['results']; if ( isset( $one['results'][ $d ] ) ) self::name_ok( $d ); elseif ( isset( $one['errors'][ $d ] ) ) self::name_failed( $d, $one['errors'][ $d ] ); }
				elseif ( 'api' === $one['kind'] ) self::name_failed( $d, $one['message'] . ' ' . ( $one['number'] ?? '' ) );
				else { $r['kind'] = 'error'; break; }
			}
		}
		return $r;
	}

	/**
	 * Dynadot: un dominio por consulta (una cuenta Regular no acepta más), cada una con su cupo del sitio y sin pasar del
	 * mismo tope de tiempo. Una extensión que Dynadot no atiende queda fuera de los lotes siguientes por 6 horas.
	 */
	private static function one_by_one( $nc, array $miss, $trusted ) {
		$out = array( 'kind' => 'ok', 'results' => array() );
		$deadline = microtime( true ) + min( self::budget(), (float) Digitalisimo_Hosting_Checkout::TIMEOUT );
		foreach ( $miss as $d ) {
			$left = $deadline - microtime( true );
			if ( $left < 1 ) { $out['kind'] = 'error'; break; }
			if ( ! self::global_slot( $trusted ) ) { $out['kind'] = 'busy'; break; }
			$one = $nc->check_many( array( $d ), (int) max( 1, floor( $left ) ) );
			$out['results'] += (array) ( $one['results'] ?? array() );
			if ( 'ok' !== $one['kind'] ) { $out['kind'] = 'error'; break; }
			if ( isset( $one['results'][ $d ] ) ) self::name_ok( $d );
			else self::name_failed( $d, (string) ( $one['errors'][ $d ] ?? '' ) );
		}
		return $out;
	}

	/**
	 * ¿Queda cupo en el tope del sitio (por minuto y por hora) para otra consulta a un registrador? Lo cuenta si sí. Sin el
	 * nonce del buscador sólo se usa la mitad: una página en caché o un cliente sin navegador no deja sin cupo a los demás.
	 */
	private static function global_slot( $trusted = true ) {
		$limits = (array) apply_filters( 'digitalisimo_hosting_finder_global', array( 'minute' => self::GLOBAL_MINUTE, 'hour' => self::GLOBAL_HOUR ) );
		$keys = array( 'minute' => array( 'digitalisimo_hosting_finder_nc_m', MINUTE_IN_SECONDS ), 'hour' => array( 'digitalisimo_hosting_finder_nc_h', HOUR_IN_SECONDS ) );
		$now = array();
		foreach ( $keys as $k => $def ) {
			$v = get_transient( $def[0] );
			$now[ $k ] = is_array( $v ) ? $v : array( 'n' => 0, 'until' => time() + $def[1] );
			$limit = (int) ( $limits[ $k ] ?? PHP_INT_MAX );
			if ( ! $trusted ) $limit = (int) floor( $limit / 2 );
			if ( (int) $now[ $k ]['n'] >= $limit ) return false;
		}
		// La ventana empieza con la primera consulta y no se alarga con las siguientes.
		foreach ( $keys as $k => $def ) { $now[ $k ]['n']++; set_transient( $def[0], $now[ $k ], max( 1, (int) $now[ $k ]['until'] - time() ) ); }
		return true;
	}

	/** Dos variantes sencillas del nombre para sugerir cuando todo está ocupado: «mi» delante y «mx» detrás. */
	public static function variants( $label ) {
		$out = array();
		foreach ( array( 0 === strpos( $label, 'mi' ) ? $label . 'online' : 'mi' . $label, $label . 'mx' ) as $v ) if ( $v !== $label && strlen( $v ) <= 63 && ! in_array( $v, $out, true ) ) $out[] = $v;
		return $out;
	}

	// ── La búsqueda ─────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * La respuesta del buscador para lo escrito: array( query, notice, main, instead_of, reason, taken, rows, suggestions,
	 * alternatives, together, partial ) o WP_Error. Cada fila: domain, display, ext, status (available|taken|premium|
	 * unknown), product_id, precios en texto, requirement y, si está ocupado y hay producto de transferencia de su
	 * extensión, transfer (product_id, price_text). Sólo entran las extensiones cuyo producto se puede comprar.
	 */
	public static function search( $raw, $trusted = true ) {
		$p = self::parse( $raw );
		if ( '' !== $p['error'] ) return new WP_Error( 'digitalisimo_finder_invalid', $p['error'], array( 'status' => 400 ) );
		$map = Digitalisimo_Hosting::domain_products();
		$prices = array();
		foreach ( self::ordered() as $e ) { $pr = self::prices( $map[ $e ] ); if ( $pr ) $prices[ $e ] = $pr; }
		$order = array_keys( $prices );
		if ( ! $order ) return new WP_Error( 'digitalisimo_finder_empty', 'Por ahora no hay dominios a la venta.', array( 'status' => 503 ) );
		$typed_sold = '' !== $p['ext'] && isset( $prices[ $p['ext'] ] );
		$exts = array_slice( $typed_sold ? array_values( array_unique( array_merge( array( $p['ext'] ), $order ) ) ) : $order, 0, self::MAX_EXTS );
		// El orden de las filas es el configurado, aunque la escrita se haya puesto primero para que no la corte el tope.
		$exts = array_values( array_filter( $order, function ( $e ) use ( $exts ) { return in_array( $e, $exts, true ); } ) );
		$want = $typed_sold ? $p['ext'] : ( in_array( '.com', $exts, true ) ? '.com' : $exts[0] );
		$domains = array();
		foreach ( $exts as $e ) $domains[ $e ] = $p['label'] . $e;
		$ideas = array();
		foreach ( self::variants( $p['label'] ) as $v ) $ideas[] = $v . $want;
		// Sin la cuenta de ningún registrador de estas extensiones no se consulta nada.
		$ready = false;
		foreach ( array_unique( array_map( array( 'Digitalisimo_Hosting_Registrars', 'id_for_ext' ), $exts ) ) as $rid ) $ready = $ready || Digitalisimo_Hosting_Registrars::make( $rid )->configured();
		if ( ! $ready ) return new WP_Error( 'digitalisimo_finder_unavailable', 'La búsqueda de dominios no está disponible en este momento.', array( 'status' => 503 ) );
		$bad = self::bad_exts();
		$ask = array();
		foreach ( $domains as $e => $d ) if ( ! isset( $bad[ $e ] ) ) $ask[] = $d;
		if ( ! isset( $bad[ $want ] ) ) $ask = array_merge( $ask, $ideas );
		$av = self::availability( $ask, $trusted );
		if ( 'busy' === $av['kind'] && ! $av['results'] ) return new WP_Error( 'digitalisimo_finder_busy', 'Hay muchas búsquedas en este momento. Intenta de nuevo en un minuto.', array( 'status' => 429 ) );
		if ( 'ok' !== $av['kind'] && ! $av['results'] ) return new WP_Error( 'digitalisimo_finder_unavailable', 'No pudimos consultar los dominios en este momento. Intenta de nuevo en un minuto.', array( 'status' => 503 ) );
		$req = self::requirements();
		$transfers = Digitalisimo_Hosting_Products::transfers();
		$row_for = function ( $d, $e ) use ( $av, $p, $map, $prices, $req, $transfers ) {
			$res = $av['results'][ $d ] ?? null;
			$status = ! $res ? 'unknown' : ( ! $res['available'] ? 'taken' : ( $res['premium'] ? 'premium' : 'available' ) );
			$row = array( 'domain' => $d, 'display' => '' !== $p['display'] && $d === $p['label'] . $e ? $p['display'] . $e : '', 'ext' => $e, 'status' => $status, 'product_id' => (int) $map[ $e ], 'requirement' => $req[ $e ] ?? '', 'price_text' => $prices[ $e ]['price_text'], 'renewal_text' => $prices[ $e ]['renewal_text'] );
			if ( 'taken' === $status ) {
				$tid = (int) ( $transfers[ $e ] ?? $transfers['*'] ?? 0 );
				$tp = $tid ? self::prices( $tid ) : null;
				if ( $tp ) $row['transfer'] = array( 'product_id' => $tid, 'price_text' => $tp['price_text'] );
			}
			return $row;
		};
		$rows = array();
		foreach ( $domains as $e => $d ) $rows[ $e ] = $row_for( $d, $e );
		$main = $rows[ $want ];
		$instead = ''; $reason = '';
		if ( 'available' !== $main['status'] ) {
			// Por qué no se ofrece el que buscó: ocupado, premium o no se pudo consultar (nunca «ocupado» si no lo está).
			foreach ( $rows as $r ) if ( 'available' === $r['status'] ) { $instead = $main['domain']; $reason = $main['status']; $main = $r; break; }
		}
		// Todo ocupado: variantes sencillas del nombre con la extensión principal (consultadas en el mismo lote).
		$suggestions = array();
		if ( 'available' !== $main['status'] ) foreach ( $ideas as $d ) { $r = $row_for( $d, $want ); if ( 'available' === $r['status'] ) $suggestions[] = $r; }
		$alternatives = array();
		foreach ( array_merge( array_values( $rows ), $suggestions ) as $r ) if ( 'available' === $r['status'] && $r['domain'] !== $main['domain'] && count( $alternatives ) < 3 ) $alternatives[] = $r['domain'];
		$notice = '';
		if ( '' !== $p['ext'] && ! $typed_sold ) $notice = 'Aquí no vendemos dominios ' . $p['ext'] . '. Estas son las extensiones que sí tenemos para «' . $p['label'] . '».';
		return array(
			'query'        => $p['label'],
			'notice'       => $notice,
			'main'         => $main,
			'instead_of'   => $instead,
			'reason'       => $reason,
			// Si el principal está ocupado, su transferencia se ofrece junto a él.
			'taken'        => 'taken' === $reason ? $rows[ $want ] : null,
			'rows'         => array_values( array_filter( $rows, function ( $r ) use ( $main ) { return $r['domain'] !== $main['domain']; } ) ),
			'suggestions'  => $suggestions,
			'alternatives' => $alternatives,
			'together'     => self::together(),
			'partial'      => 'ok' !== $av['kind'],
		);
	}

	// ── Punto público (REST) ────────────────────────────────────────────────────────────────────────────────────

	public static function routes() {
		register_rest_route( 'digitalisimo-hosting/v1', '/finder', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest' ), 'permission_callback' => array( __CLASS__, 'permission' ), 'args' => array( 'q' => array( 'required' => true, 'type' => 'string' ) ) ) );
	}

	private static function nonce_ok( $request ) {
		$nonce = (string) $request->get_param( 'nonce' );
		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, self::NONCE );
	}

	/**
	 * El nonce del buscador o, en una página servida desde la caché con el nonce vencido, la cabecera propia del buscador
	 * desde este mismo sitio (Origin del sitio, con o sin www., o sin Origin): un navegador en otro sitio no puede mandar
	 * esa cabecera porque la REST no la permite en CORS. Sin nonce sólo se usa la mitad del cupo del sitio.
	 */
	public static function permission( $request ) {
		if ( self::nonce_ok( $request ) ) return true;
		if ( '1' === (string) $request->get_header( self::HEADER ) && self::same_origin( (string) $request->get_header( 'origin' ) ) ) return true;
		return new WP_Error( 'digitalisimo_finder_forbidden', 'Recarga la página e intenta de nuevo.', array( 'status' => 403 ) );
	}

	public static function same_origin( $origin ) {
		if ( '' === $origin ) return true;
		$site = wp_parse_url( home_url( '/' ) );
		$o = wp_parse_url( $origin );
		$host = function ( $u ) { return preg_replace( '/^www\./', '', strtolower( (string) ( $u['host'] ?? '' ) ) ); };
		return is_array( $o ) && is_array( $site ) && '' !== $host( $o ) && $host( $o ) === $host( $site );
	}

	public static function rest( $request ) {
		if ( ! self::on() ) return new WP_Error( 'digitalisimo_finder_off', 'La búsqueda de dominios no está disponible.', array( 'status' => 503 ) );
		$q = $request->get_param( 'q' );
		if ( ! is_string( $q ) ) return new WP_Error( 'digitalisimo_finder_invalid', 'Escribe el nombre que quieres para tu dominio.', array( 'status' => 400 ) );
		$busy = new WP_Error( 'digitalisimo_finder_rate', 'Hiciste muchas búsquedas seguidas. Espera un minuto e intenta de nuevo.', array( 'status' => 429 ) );
		if ( ! Digitalisimo_Hosting::rate_limit( 'domain-finder', (int) apply_filters( 'digitalisimo_hosting_finder_rate', self::RATE ) ) ) return $busy;
		if ( ! Digitalisimo_Hosting::rate_limit( 'domain-finder-hour', (int) apply_filters( 'digitalisimo_hosting_finder_rate_hour', self::RATE_HOUR ), HOUR_IN_SECONDS ) ) return new WP_Error( 'digitalisimo_finder_rate', 'Hiciste muchas búsquedas en la última hora. Intenta de nuevo más tarde.', array( 'status' => 429 ) );
		$r = self::search( $q, self::nonce_ok( $request ) );
		if ( is_wp_error( $r ) ) return $r;
		return rest_ensure_response( $r );
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
			// Las filas muestran el precio como la tienda (con o sin IVA): la barra suma igual.
			'taxIncluded' => 'yes' === get_option( 'woocommerce_calc_taxes' ) && 'incl' === get_option( 'woocommerce_tax_display_shop', 'excl' ),
		);
	}

	// ── Pintar el buscador ──────────────────────────────────────────────────────────────────────────────────────

	public static function register_assets() {
		if ( ! function_exists( 'wp_register_script' ) ) return;
		wp_register_style( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/finder.css', array(), DIGITALISIMO_HOSTING_VERSION );
		wp_register_script( self::HANDLE, DIGITALISIMO_HOSTING_URL . 'assets/finder.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
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
	 * etiqueta, la región de avisos (role=status, la que se anuncia) y la de resultados. Sin JavaScript, el formulario recarga con ?dominio=
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
			. '<div class="digitalisimo-finder__results" aria-busy="false"></div>'
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
		// WooCommerce pinta este bloque también con artículos (el navegador decide cuál mostrar): sólo si está vacío.
		if ( ! self::cart_empty() ) return $content;
		self::enqueue();
		// Dentro del bloque «carrito vacío» (antes de su cierre): fuera de él también se vería con el carrito lleno.
		$finder = '<div class="digitalisimo-finder-empty-cart"><h2>¿Buscas un dominio?</h2>' . self::html() . '</div>';
		$at = strrpos( $content, '</div>' );
		return false === $at ? $content . $finder : substr( $content, 0, $at ) . $finder . substr( $content, $at );
	}

	/** ¿El carrito real está vacío? (sin carrito cargado, se toma como vacío). */
	private static function cart_empty() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) return true;
		$cart = WC()->cart;
		return method_exists( $cart, 'is_empty' ) ? (bool) $cart->is_empty() : ! $cart->get_cart();
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
