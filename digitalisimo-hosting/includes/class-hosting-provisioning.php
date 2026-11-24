<?php
defined( 'ABSPATH' ) || exit;

/**
 * Venta automática de hosting y dominios con WooCommerce (pedido del propietario del 2026-11-23).
 *
 * Al pagarse un pedido (pasa a «procesando» o «completado») se encola en Action Scheduler y, en segundo plano:
 *  1. crea la cuenta en Webuzo con el paquete del producto (`_digitalisimo_webuzo_plan`), el dominio del pedido, un
 *     usuario derivado del dominio y una contraseña fuerte;
 *  2. registra el dominio en Namecheap a nombre del cliente (datos de facturación), tras comprobar que el saldo alcanza,
 *     y lo apunta a los nameservers propios;
 *  3. envía al cliente el correo «Accesos de hosting» (WooCommerce → Ajustes → Correos) y deja una nota privada en el
 *     pedido, sin la contraseña.
 * Cada paso guarda su resultado en el pedido: reintentar no crea otra cuenta ni registra otra vez (antes de crear se
 * consulta si la cuenta o el dominio ya existen). Sólo se reintenta lo que no llegó (red, tiempo agotado); una clave
 * rechazada por Webuzo detiene TODO sin reintentar (ver Digitalisimo_Hosting_Webuzo). Saldo insuficiente, datos que
 * faltan o un error de la API dejan el pedido «en espera» con su nota y un correo al administrador.
 *
 * Cada servicio (dominio + plan) queda como una entrada privada `digi_hosting_service` con su vencimiento. A diario:
 * aviso al cliente 30 y 7 días antes con un pedido de renovación que paga con el enlace de pago de WooCommerce;
 * suspensión en Webuzo a los N días de gracia sin pago; al pagarse, renovación del dominio en Namecheap (con la misma
 * validación de saldo), reactivación y un año más.
 */
class Digitalisimo_Hosting_Provisioning {
	const GROUP          = 'digitalisimo-hosting';
	const HOOK_PROVISION = 'digitalisimo_hosting_provision';
	const HOOK_RENEW     = 'digitalisimo_hosting_renew';
	const HOOK_SUSPEND   = 'digitalisimo_hosting_suspend';
	const HOOK_UNSUSPEND = 'digitalisimo_hosting_unsuspend';
	const HOOK_RESEND    = 'digitalisimo_hosting_resend';
	const HOOK_DAILY     = 'digitalisimo_hosting_daily';
	const CPT            = 'digi_hosting_service';
	const META_STATE     = '_digitalisimo_provision';
	const META_SERVICE   = '_digitalisimo_service';
	const META_RENEWAL   = '_digitalisimo_renewal_of';
	const META_RENEW     = '_digitalisimo_renew';
	/** Lo detenido por una clave rechazada de Webuzo: se vuelve a encolar al guardar una clave nueva. */
	const WAITING        = 'digitalisimo_hosting_waiting';
	const MAX_ATTEMPTS   = 5;
	/** Espera entre reintentos por falla de red (segundos). */
	const BACKOFF        = array( 300, 900, 3600, 10800 );
	/** Campos del titular que Namecheap exige. */
	const REQUIRED_CONTACT = array( 'FirstName', 'LastName', 'Address1', 'City', 'StateProvince', 'PostalCode', 'Country', 'Phone', 'EmailAddress' );

	/** Contraseña recién creada cuando no se pudo cifrar (sólo en memoria, sólo en esta petición). */
	private static $plain = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compat' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'paid' ), 20 );
		add_action( self::HOOK_PROVISION, array( __CLASS__, 'run_provision' ) );
		add_action( self::HOOK_RENEW, array( __CLASS__, 'run_renew' ) );
		add_action( self::HOOK_SUSPEND, array( __CLASS__, 'run_suspend' ) );
		add_action( self::HOOK_UNSUSPEND, array( __CLASS__, 'run_unsuspend' ) );
		add_action( self::HOOK_RESEND, array( __CLASS__, 'run_resend' ) );
		add_action( self::HOOK_DAILY, array( __CLASS__, 'daily' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'emails' ) );
		add_action( 'digitalisimo_hosting_webuzo_blocked', array( __CLASS__, 'on_blocked' ) );
		add_action( 'digitalisimo_hosting_webuzo_unblocked', array( __CLASS__, 'on_unblocked' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		if ( class_exists( 'Digitalisimo_Hosting_Services_Page' ) ) Digitalisimo_Hosting_Services_Page::init();
	}

	/** Servicios: entradas privadas, sin pantalla propia de WordPress (la lista vive en WooCommerce → Servicios de hosting). */
	public static function register() {
		register_post_type( self::CPT, array( 'label' => 'Servicios de hosting', 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false, 'supports' => array( 'title' ), 'can_export' => true ) );
	}

	/** Sólo usa la API de pedidos de WooCommerce: compatible con el almacenamiento de pedidos de alto rendimiento. */
	public static function declare_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && defined( 'DIGITALISIMO_HOSTING_FILE' ) ) \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DIGITALISIMO_HOSTING_FILE, true );
	}

	private static function on() { $o = Digitalisimo_Hosting::settings(); return ! empty( $o['enabled'] ) && ! empty( $o['provision_enabled'] ); }

	public static function ensure_daily() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK_DAILY ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_DAILY );
	}

	/** Pedido pagado: encola el aprovisionamiento o, si es un pedido de renovación, la renovación. */
	public static function paid( $order_id ) {
		if ( ! self::on() ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		if ( $order->get_meta( self::META_RENEWAL ) ) {
			$state = (array) $order->get_meta( self::META_RENEW );
			if ( empty( $state['done'] ) ) {
				if ( empty( $state['paid'] ) ) { $state['paid'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save(); }
				self::enqueue( self::HOOK_RENEW, $order->get_id() );
			}
			return;
		}
		$items = self::items( $order );
		if ( ! $items['hosting'] && ! $items['domains'] ) return;
		$state = self::state( $order );
		if ( ! empty( $state['done'] ) ) return;
		self::enqueue( self::HOOK_PROVISION, $order->get_id() );
		self::ensure_daily();
	}

	/** Encola una tarea (una sola pendiente por tarea y objeto). Con $delay, programada; $retry: desde la propia tarea. */
	public static function enqueue( $hook, $id, $delay = 0, $retry = false ) {
		$args = array( (int) $id );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			// as_has_scheduled_action() también cuenta la tarea en curso: un reintento desde ella no se descarta.
			if ( ! $retry && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, self::GROUP ) ) return;
			if ( $delay > 0 ) as_schedule_single_action( time() + $delay, $hook, $args, self::GROUP );
			else as_enqueue_async_action( $hook, $args, self::GROUP );
			return;
		}
		if ( ! wp_next_scheduled( $hook, $args ) ) wp_schedule_single_event( time() + max( 0, $delay ), $hook, $args );
	}

	// ── Lectura del pedido ──────────────────────────────────────────────────────────────────────────────────────

	public static function state( $order ) { $s = $order->get_meta( self::META_STATE ); return is_array( $s ) ? $s : array(); }
	private static function save_state( $order, array $state ) { $order->update_meta_data( self::META_STATE, $state ); $order->save(); }

	/** Los planes de hosting y los dominios del pedido. */
	public static function items( $order ) {
		$domain_ids = array_values( Digitalisimo_Hosting::domain_products() );
		$out = array( 'hosting' => array(), 'domains' => array() );
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) continue;
			$pid    = (int) $item->get_product_id();
			$vid    = (int) $item->get_variation_id();
			$domain = strtolower( trim( (string) $item->get_meta( 'Dominio' ) ) );
			$row    = array( 'item' => $item, 'product' => $pid, 'variation' => $vid, 'domain' => $domain, 'qty' => (int) $item->get_quantity() );
			if ( in_array( $pid, $domain_ids, true ) ) { $out['domains'][] = $row; continue; }
			$plan = self::plan_of( $vid, $pid );
			if ( '' !== $plan || Digitalisimo_Hosting::is_hosting_product( $pid ) ) $out['hosting'][] = $row + array( 'plan' => $plan );
		}
		return $out;
	}

	/** El paquete de Webuzo del producto (o de su producto padre). */
	private static function plan_of( $vid, $pid ) {
		$plan = $vid ? (string) get_post_meta( $vid, '_digitalisimo_webuzo_plan', true ) : '';
		if ( '' === $plan ) $plan = (string) get_post_meta( $pid, '_digitalisimo_webuzo_plan', true );
		return trim( $plan );
	}

	public static function valid_domain( $domain ) { return (bool) preg_match( '/^(?=.{4,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', (string) $domain ); }

	/** array( sld, tld, extensión ) según las extensiones configuradas (la más larga primero: .com.mx antes que .mx). */
	public static function split( $domain ) {
		$exts = array_keys( Digitalisimo_Hosting::domain_products() );
		usort( $exts, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		foreach ( $exts as $ext ) {
			$ext = '.' . ltrim( strtolower( $ext ), '.' );
			if ( strlen( $domain ) > strlen( $ext ) && substr( $domain, -strlen( $ext ) ) === $ext ) {
				$sld = substr( $domain, 0, -strlen( $ext ) );
				return false === strpos( $sld, '.' ) ? array( $sld, ltrim( $ext, '.' ), $ext ) : null;
			}
		}
		$p = strpos( $domain, '.' );
		return $p ? array( substr( $domain, 0, $p ), substr( $domain, $p + 1 ), substr( $domain, $p ) ) : null;
	}

	/** Titular del dominio con los datos de facturación del pedido y la lista de lo que falta. */
	public static function contact( $order ) {
		$country = strtoupper( (string) $order->get_billing_country() );
		$state   = (string) $order->get_billing_state();
		// El registro de .mx valida el estado por su nombre: «JAL» de WooCommerce se manda como «Jalisco».
		if ( $country && $state && function_exists( 'WC' ) && isset( WC()->countries ) ) {
			$states = WC()->countries->get_states( $country );
			if ( is_array( $states ) && isset( $states[ $state ] ) ) $state = html_entity_decode( (string) $states[ $state ], ENT_QUOTES, 'UTF-8' );
		}
		$first = trim( (string) $order->get_billing_first_name() );
		$last  = trim( (string) $order->get_billing_last_name() );
		if ( '' === $last && false !== strpos( $first, ' ' ) ) { $parts = preg_split( '/\s+/', $first, 2 ); $first = $parts[0]; $last = $parts[1]; }
		$c = array(
			'FirstName'        => $first,
			'LastName'         => $last,
			'OrganizationName' => trim( (string) $order->get_billing_company() ),
			'Address1'         => trim( (string) $order->get_billing_address_1() ),
			'Address2'         => trim( (string) $order->get_billing_address_2() ),
			'City'             => trim( (string) $order->get_billing_city() ),
			'StateProvince'    => trim( $state ),
			'PostalCode'       => trim( (string) $order->get_billing_postcode() ),
			'Country'          => $country,
			'Phone'            => self::phone( (string) $order->get_billing_phone(), $country ),
			'EmailAddress'     => trim( (string) $order->get_billing_email() ),
		);
		$missing = array();
		foreach ( self::REQUIRED_CONTACT as $key ) if ( '' === $c[ $key ] ) $missing[] = $key;
		return array( $c, $missing );
	}

	/** Teléfono en el formato de Namecheap: +<lada del país>.<número>. */
	public static function phone( $raw, $country ) {
		$digits = preg_replace( '/\D/', '', $raw );
		if ( '' === $digits ) return '';
		$code = '';
		if ( $country && function_exists( 'WC' ) && isset( WC()->countries ) && method_exists( WC()->countries, 'get_country_calling_code' ) ) {
			$code = WC()->countries->get_country_calling_code( $country );
			if ( is_array( $code ) ) $code = (string) reset( $code );
		}
		if ( '' === (string) $code ) {
			$map = array( 'MX' => '52', 'US' => '1', 'CA' => '1', 'ES' => '34', 'CO' => '57', 'AR' => '54', 'CL' => '56', 'PE' => '51', 'GT' => '502', 'EC' => '593' );
			$code = $map[ $country ] ?? '';
		}
		$code = preg_replace( '/\D/', '', (string) $code );
		$intl = '+' === substr( ltrim( $raw ), 0, 1 ) || '00' === substr( $digits, 0, 2 );
		if ( '00' === substr( $digits, 0, 2 ) ) $digits = substr( $digits, 2 );
		if ( $intl ) {
			if ( '' !== $code && 0 === strpos( $digits, $code ) ) return '+' . $code . '.' . substr( $digits, strlen( $code ) );
			return '';
		}
		if ( '' === $code ) return '';
		if ( 0 === strpos( $digits, $code ) && strlen( $digits ) > 10 ) $digits = substr( $digits, strlen( $code ) );
		return '+' . $code . '.' . ltrim( $digits, '0' );
	}

	// ── Contraseñas ─────────────────────────────────────────────────────────────────────────────────────────────

	/** 18 caracteres con minúsculas, mayúsculas, dígitos y símbolos, de random_int(). */
	public static function password() {
		$sets = array( 'abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!#%+=-_@' );
		$chars = array();
		foreach ( $sets as $set ) $chars[] = $set[ random_int( 0, strlen( $set ) - 1 ) ];
		$all = implode( '', $sets );
		while ( count( $chars ) < 18 ) $chars[] = $all[ random_int( 0, strlen( $all ) - 1 ) ];
		for ( $i = count( $chars ) - 1; $i > 0; $i-- ) { $j = random_int( 0, $i ); $t = $chars[ $i ]; $chars[ $i ] = $chars[ $j ]; $chars[ $j ] = $t; }
		return implode( '', $chars );
	}

	/** Clave de cifrado derivada de las sales de WordPress (nunca se guarda). */
	private static function key() { return hash( 'sha256', 'digitalisimo-hosting|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true ); }

	public static function encrypt( $plain ) {
		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', array_map( 'strtolower', openssl_get_cipher_methods() ), true ) ) {
			$iv = random_bytes( 12 ); $tag = '';
			$c  = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $c ) return 'g1:' . base64_encode( $iv . $tag . $c );
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) { $n = random_bytes( 24 ); return 's1:' . base64_encode( $n . sodium_crypto_secretbox( $plain, $n, self::key() ) ); }
		return '';
	}

	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		$raw = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw ) return '';
		if ( 0 === strpos( $stored, 'g1:' ) && function_exists( 'openssl_decrypt' ) && strlen( $raw ) > 28 ) {
			$p = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $p ? '' : $p;
		}
		if ( 0 === strpos( $stored, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) && strlen( $raw ) > 24 ) {
			$p = sodium_crypto_secretbox_open( substr( $raw, 24 ), substr( $raw, 0, 24 ), self::key() );
			return false === $p ? '' : $p;
		}
		return '';
	}

	/** Usuario de Webuzo derivado del dominio (con el prefijo opcional): letras y números, empieza con letra, 12 como máximo. */
	public static function username( $domain, array $taken, $prefix = '' ) {
		$sld  = explode( '.', $domain )[0];
		$base = preg_replace( '/[^a-z0-9]/', '', strtolower( $prefix . $sld ) );
		if ( '' === $base || ! preg_match( '/^[a-z]/', $base ) ) $base = 'u' . $base;
		$base = substr( $base, 0, 12 );
		if ( ! isset( $taken[ $base ] ) ) return $base;
		for ( $i = 2; $i < 1000; $i++ ) { $c = substr( $base, 0, 12 - strlen( (string) $i ) ) . $i; if ( ! isset( $taken[ $c ] ) ) return $c; }
		return '';
	}

	// ── Servicios ───────────────────────────────────────────────────────────────────────────────────────────────

	public static function svc( $id, $key ) { return get_post_meta( (int) $id, '_dhs_' . $key, true ); }
	public static function svc_set( $id, array $values ) { foreach ( $values as $k => $v ) update_post_meta( (int) $id, '_dhs_' . $k, $v ); }
	public static function svc_log( $id, $text ) {
		$log = self::svc( $id, 'log' ); $log = is_array( $log ) ? $log : array();
		$log[] = array( time(), (string) $text );
		self::svc_set( $id, array( 'log' => array_slice( $log, -40 ) ) );
	}
	public static function is_service( $id ) { $p = get_post( (int) $id ); return $p && self::CPT === $p->post_type; }

	public static function services() {
		return array_map( 'intval', get_posts( array( 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true ) ) );
	}

	/** El servicio del pedido (lo crea la primera vez, en «pendiente»). */
	private static function service_for( $order, array $items, $domain ) {
		$id = (int) $order->get_meta( self::META_SERVICE );
		if ( $id && self::is_service( $id ) ) return $id;
		$id = (int) wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => '' !== $domain ? $domain : 'Pedido #' . $order->get_id() ) );
		if ( ! $id ) return 0;
		$hosting = $items['hosting'][0] ?? null; $dom = $items['domains'][0] ?? null;
		self::svc_set( $id, array(
			'order' => $order->get_id(), 'customer' => (int) $order->get_customer_id(), 'email' => (string) $order->get_billing_email(),
			'name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ), 'domain' => $domain,
			'plan' => $hosting ? $hosting['plan'] : '', 'hosting_product' => $hosting ? ( $hosting['variation'] ?: $hosting['product'] ) : 0,
			'domain_product' => $dom ? $dom['product'] : 0, 'status' => 'pending', 'expires' => 0,
		) );
		$order->update_meta_data( self::META_SERVICE, $id );
		$order->save();
		return $id;
	}

	// ── Aprovisionamiento ───────────────────────────────────────────────────────────────────────────────────────

	public static function run_provision( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::META_RENEWAL ) ) return;
		$state = self::state( $order );
		if ( ! empty( $state['done'] ) ) return;
		try {
			$items  = self::items( $order );
			$domain = '';
			if ( $items['domains'] ) $domain = $items['domains'][0]['domain'];
			elseif ( $items['hosting'] ) $domain = $items['hosting'][0]['domain'];
			$service = self::service_for( $order, $items, $domain );
			$stop = self::check_order( $order, $items, $domain );
			if ( ! $stop ) $stop = self::step_hosting( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_domain( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_email( $order, $state, $items, $domain, $service );
			if ( $stop ) { self::fail( $order, $state, $stop, self::HOOK_PROVISION, $order->get_id(), $service ); return; }
			$state['done'] = time(); unset( $state['attempts'], $state['blocked_noted'] );
			self::save_state( $order, $state );
			$expires = strtotime( '+1 year', time() );
			self::svc_set( $service, array( 'status' => 'active', 'expires' => $expires, 'error' => '', 'started' => time() ) );
			self::svc_log( $service, 'Aprovisionado con el pedido #' . $order->get_id() . '.' );
			if ( in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) $order->update_status( 'completed', 'DIGITALÍSIMO Hosting: servicio entregado; vence el ' . self::date( $expires ) . '.' );
		} catch ( Throwable $e ) {
			self::fail( $order, $state, array( 'kind' => 'api', 'message' => 'Error interno: ' . $e->getMessage() ), self::HOOK_PROVISION, $order->get_id(), (int) $order->get_meta( self::META_SERVICE ) );
		}
	}

	/** Lo que no se puede resolver solo: varios planes o dominios, cantidades, dominio inválido o de otra extensión. */
	private static function check_order( $order, array $items, $domain ) {
		if ( count( $items['hosting'] ) > 1 || count( $items['domains'] ) > 1 ) return self::stop( 'data', 'El pedido tiene más de un plan o más de un dominio; la venta automática atiende uno de cada uno por pedido. Entrégalo a mano.' );
		foreach ( array_merge( $items['hosting'], $items['domains'] ) as $row ) if ( $row['qty'] > 1 ) return self::stop( 'data', 'El pedido tiene una cantidad mayor que 1 de «' . $row['item']->get_name() . '»; la venta automática registra un año por pedido.' );
		if ( '' === $domain ) return self::stop( 'data', 'El pedido no trae el dominio (dato «Dominio» del producto).' );
		if ( ! self::valid_domain( $domain ) || ! self::split( $domain ) ) return self::stop( 'data', 'El dominio «' . $domain . '» no es válido.' );
		if ( $items['domains'] ) {
			// El producto pagado debe ser el de la extensión del dominio: nadie registra un .io pagando el precio de un .com.
			$split = self::split( $domain ); $map = Digitalisimo_Hosting::domain_products();
			$expected = (int) ( $map[ $split[2] ] ?? 0 );
			if ( ! $expected || $expected !== (int) $items['domains'][0]['product'] ) return self::stop( 'data', 'El dominio «' . $domain . '» no corresponde al producto de dominio pagado; no se registró.' );
		}
		return null;
	}

	private static function step_hosting( $order, array &$state, array $items, $domain, $service ) {
		if ( ! $items['hosting'] || 'done' === ( $state['hosting']['status'] ?? '' ) ) return null;
		$plan = $items['hosting'][0]['plan'];
		if ( ! preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $plan ) ) return self::stop( 'data', 'El producto «' . $items['hosting'][0]['item']->get_name() . '» no tiene el paquete de Webuzo (campo _digitalisimo_webuzo_plan).' );
		$api = Digitalisimo_Hosting_Webuzo::from_settings();
		$list = $api->users();
		if ( 'ok' !== $list['kind'] ) return self::stop( $list['kind'], 'Webuzo (lista de cuentas): ' . $list['message'] );
		$users = $list['users'];
		$mine  = (string) ( $state['hosting']['user'] ?? '' );
		foreach ( $users as $u ) {
			if ( $u['domain'] !== $domain ) continue;
			if ( '' !== $mine && $u['user'] === $mine ) {
				// La cuenta ya existe (un intento anterior llegó a Webuzo aunque no se vio la respuesta): no se crea otra.
				$state['hosting']['status'] = 'done';
				self::hosting_done( $order, $state, $u['user'], $plan, $service, $api, true );
				return null;
			}
			return self::stop( 'api', 'El dominio ' . $domain . ' ya tiene la cuenta «' . $u['user'] . '» en Webuzo y no la creó este pedido; no se creó otra. Revísalo a mano.' );
		}
		if ( '' === $mine || isset( $users[ $mine ] ) ) {
			$prefix = (string) ( Digitalisimo_Hosting::settings()['webuzo_user_prefix'] ?? '' );
			$mine = self::username( $domain, $users, $prefix );
			if ( '' === $mine ) return self::stop( 'data', 'No se encontró un usuario libre para ' . $domain . '.' );
			$password = self::password();
			$secret = self::encrypt( $password );
			if ( '' === $secret ) self::$plain[ $order->get_id() ] = $password;
			$state['hosting'] = array( 'status' => 'pending', 'user' => $mine, 'plan' => $plan, 'pass' => $secret );
			self::save_state( $order, $state );
		}
		$password = self::hosting_password( $order, $state );
		if ( '' === $password ) return self::stop( 'data', 'No se pudo recuperar la contraseña generada para la cuenta (cifrado no disponible).' );
		$r = $api->create_user( $mine, $domain, $password, (string) $order->get_billing_email(), $plan );
		$r['message'] = str_replace( $password, '••••', (string) $r['message'] );
		if ( 'ok' !== $r['kind'] ) return self::stop( $r['kind'], 'Webuzo (crear la cuenta ' . $mine . '): ' . $r['message'] );
		$state['hosting']['status'] = 'done';
		self::hosting_done( $order, $state, $mine, $plan, $service, $api, false );
		return null;
	}

	private static function hosting_done( $order, array &$state, $user, $plan, $service, $api, $existed ) {
		$state['hosting']['created'] = time();
		self::save_state( $order, $state );
		self::svc_set( $service, array( 'webuzo_user' => $user, 'plan' => $plan, 'webuzo_server' => $api->server() ) );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: cuenta de Webuzo ' . ( $existed ? 'encontrada (la había creado un intento anterior de este pedido)' : 'creada' ) . ' · usuario «' . $user . '», paquete ' . $plan . ', panel ' . $api->panel_url() . '. La contraseña sólo se envía al cliente.' );
	}

	private static function hosting_password( $order, array $state ) {
		$stored = (string) ( $state['hosting']['pass'] ?? '' );
		if ( '' !== $stored ) return self::decrypt( $stored );
		return (string) ( self::$plain[ $order->get_id() ] ?? '' );
	}

	private static function nameservers() {
		$raw = (string) ( Digitalisimo_Hosting::settings()['nameservers'] ?? '' );
		$ns = array_values( array_filter( array_map( 'trim', explode( ',', '' !== trim( $raw ) ? $raw : Digitalisimo_Hosting::DEFAULT_NAMESERVERS ) ) ) );
		return $ns;
	}

	private static function step_domain( $order, array &$state, array $items, $domain, $service ) {
		if ( ! $items['domains'] ) {
			if ( empty( $state['own_domain_noted'] ) ) {
				$state['own_domain_noted'] = 1; self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el pedido no incluye registro de dominio (dominio propio del cliente). Debe apuntarlo a ' . implode( ' y ', self::nameservers() ) . '.' );
			}
			return null;
		}
		if ( 'done' === ( $state['domain']['status'] ?? '' ) ) return null;
		$nc = Digitalisimo_Hosting_Namecheap::from_settings();
		if ( ! $nc->configured() ) return self::stop( 'config', 'Faltan los datos de la cuenta de Namecheap en Digitalisimo → Hosting.' );
		list( $sld, $tld ) = self::split( $domain );
		if ( empty( $state['domain']['registered'] ) ) {
			$info = $nc->info( $domain );
			if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
			if ( $info['ours'] ) {
				$state['domain'] = array( 'registered' => time(), 'existed' => 1 );
				self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el dominio ' . $domain . ' ya estaba en la cuenta de Namecheap; no se registró otra vez.' );
			} else {
				list( $contact, $missing ) = self::contact( $order );
				if ( $missing ) return self::stop( 'data', 'Faltan datos del titular del dominio en la facturación del pedido (' . implode( ', ', $missing ) . '). Complétalos en el pedido y reintenta.' );
				$price = $nc->price( $tld, 'REGISTER' );
				if ( 'ok' !== $price['kind'] ) return self::stop( self::nc_kind( $price ), $price['message'] );
				$balance = $nc->balance();
				if ( 'ok' !== $balance['kind'] ) return self::stop( self::nc_kind( $balance ), $balance['message'] );
				if ( $balance['value'] < $price['value'] ) return self::stop( 'funds', sprintf( 'Saldo insuficiente en Namecheap para registrar %s: hay %s USD y cuesta %s USD. Recarga la cuenta y pulsa «Reintentar» en WooCommerce → Servicios de hosting.', $domain, number_format( $balance['value'], 2, '.', '' ), number_format( $price['value'], 2, '.', '' ) ) );
				$r = $nc->create( $domain, $contact, 1 );
				if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
				$state['domain'] = array( 'registered' => time(), 'id' => $r['domain_id'] );
				self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: dominio ' . $domain . ' registrado en Namecheap a nombre de ' . trim( $contact['FirstName'] . ' ' . $contact['LastName'] ) . ' (ID ' . $r['domain_id'] . ', cargo ' . $r['charged'] . ' USD).' );
			}
		}
		$ns = self::nameservers();
		$r = $nc->set_dns( $sld, $tld, $ns );
		if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
		$state['domain']['status'] = 'done'; $state['domain']['dns'] = $ns;
		self::save_state( $order, $state );
		self::svc_set( $service, array( 'domain_registered' => 1 ) );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $domain . ' apunta a ' . implode( ' y ', $ns ) . '.' );
		return null;
	}

	/** Un error de clave de Namecheap no detiene Webuzo: se trata como configuración (sin reintentar). */
	private static function nc_kind( array $r ) { return 'auth' === $r['kind'] ? 'config' : $r['kind']; }

	private static function step_email( $order, array &$state, array $items, $domain, $service ) {
		if ( ! empty( $state['email'] ) ) return null;
		$data = self::access_data( $order, $state, $items, $domain );
		$sent = self::send_access( $order, $data );
		// La contraseña cifrada pasa al servicio (para «Reenviar accesos») y deja de estar en el pedido.
		if ( ! empty( $state['hosting']['pass'] ) ) { self::svc_set( $service, array( 'pass' => $state['hosting']['pass'] ) ); unset( $state['hosting']['pass'] ); }
		$state['email'] = $sent;
		self::save_state( $order, $state );
		$order->add_order_note( 'sent' === $sent ? 'DIGITALÍSIMO Hosting: correo de accesos enviado a ' . $order->get_billing_email() . '.' : ( 'disabled' === $sent ? 'DIGITALÍSIMO Hosting: el correo «Accesos de hosting» está desactivado en WooCommerce → Ajustes → Correos; entrega los accesos a mano (WooCommerce → Servicios de hosting → Reenviar accesos).' : 'DIGITALÍSIMO Hosting: no se pudo enviar el correo de accesos; usa «Reenviar accesos» en WooCommerce → Servicios de hosting.' ) );
		return null;
	}

	private static function access_data( $order, array $state, array $items, $domain ) {
		$api = Digitalisimo_Hosting_Webuzo::from_settings();
		return array(
			'domain'      => $domain,
			'has_hosting' => (bool) $items['hosting'],
			'has_domain'  => (bool) $items['domains'],
			'user'        => (string) ( $state['hosting']['user'] ?? '' ),
			'password'    => $items['hosting'] ? self::hosting_password( $order, $state ) : '',
			'panel_url'   => $items['hosting'] ? $api->panel_url() : '',
			'nameservers' => self::nameservers(),
		);
	}

	/** Envía el correo «Accesos de hosting»: sent, disabled o failed. */
	private static function send_access( $order, array $data ) {
		$email = self::email( 'Digitalisimo_Hosting_Email_Access' );
		if ( ! $email ) return 'failed';
		if ( ! $email->is_enabled() ) return 'disabled';
		return $email->trigger( $order, $data ) ? 'sent' : 'failed';
	}

	private static function email( $class ) {
		if ( ! function_exists( 'WC' ) || ! is_callable( array( WC(), 'mailer' ) ) ) return null;
		$emails = WC()->mailer()->get_emails();
		return $emails[ $class ] ?? null;
	}

	public static function emails( $emails ) {
		require_once __DIR__ . '/class-hosting-emails.php';
		$emails['Digitalisimo_Hosting_Email_Access']  = new Digitalisimo_Hosting_Email_Access();
		$emails['Digitalisimo_Hosting_Email_Renewal'] = new Digitalisimo_Hosting_Email_Renewal();
		return $emails;
	}

	// ── Fallas ──────────────────────────────────────────────────────────────────────────────────────────────────

	private static function stop( $kind, $message ) { return array( 'kind' => $kind, 'message' => $message ); }

	/**
	 * Qué hacer con un paso que no terminó. transient: reintento con espera creciente (5 como máximo); blocked/auth:
	 * en espera de una clave nueva de Webuzo, sin reintentar; lo demás (saldo, datos, error de la API): «en espera» con
	 * la nota y un correo al administrador.
	 */
	private static function fail( $order, array $state, array $stop, $hook, $id, $service ) {
		$kind = $stop['kind']; $message = $stop['message'];
		$is_renew = self::HOOK_RENEW === $hook;
		$key = $is_renew ? self::META_RENEW : self::META_STATE;
		if ( 'transient' === $kind ) {
			$state['attempts'] = (int) ( $state['attempts'] ?? 0 ) + 1;
			if ( $state['attempts'] < self::MAX_ATTEMPTS ) {
				$delay = self::BACKOFF[ min( $state['attempts'] - 1, count( self::BACKOFF ) - 1 ) ];
				$order->update_meta_data( $key, $state ); $order->save();
				$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $message . '. Reintento automático ' . $state['attempts'] . ' de ' . ( self::MAX_ATTEMPTS - 1 ) . ' en ' . round( $delay / 60 ) . ' min.' );
				self::enqueue( $hook, $id, $delay, true );
				if ( $service ) self::svc_set( $service, array( 'error' => $message ) );
				return;
			}
			$message .= '. Se agotaron los reintentos automáticos';
		}
		if ( 'blocked' === $kind || 'auth' === $kind ) {
			self::wait( $hook, $id );
			if ( ! empty( $state['blocked_noted'] ) && 'blocked' === $kind ) return;
			$state['blocked_noted'] = 1;
			$message = 'auth' === $kind ? 'Webuzo rechazó la clave de API: se detuvo TODO el aprovisionamiento sin reintentar (Webuzo bloquea la IP tras varios intentos). ' . $message : $message;
		}
		unset( $state['attempts'] );
		$order->update_meta_data( $key, $state ); $order->save();
		if ( $service ) { self::svc_set( $service, array( 'status' => $is_renew ? self::svc( $service, 'status' ) : 'on-hold', 'error' => $message ) ); self::svc_log( $service, $message ); }
		$note = 'DIGITALÍSIMO Hosting: ' . $message;
		if ( 'on-hold' !== $order->get_status() ) $order->update_status( 'on-hold', $note );
		else $order->add_order_note( $note );
		if ( 'blocked' !== $kind && 'auth' !== $kind ) self::alert_admin( 'pedido #' . $order->get_id() . ' en espera', $message . "\n\nPedido: " . self::order_link( $order ) . "\nServicios: " . admin_url( 'admin.php?page=digitalisimo-hosting-services' ) );
	}

	private static function wait( $hook, $id ) {
		$w = get_option( self::WAITING, array() ); $w = is_array( $w ) ? $w : array();
		$w[ $hook . ':' . (int) $id ] = array( $hook, (int) $id );
		update_option( self::WAITING, $w, false );
	}

	public static function on_blocked( $row ) {
		self::alert_admin( 'Webuzo rechazó la clave: aprovisionamiento detenido', "Webuzo rechazó la clave de la API (" . ( $row['msg'] ?? '' ) . ").\n\nPara no gastar los intentos que Webuzo permite antes de bloquear la IP, DIGITALÍSIMO Hosting no hará ninguna llamada más a Webuzo (ni crear cuentas, ni suspender, ni reactivar) hasta que escribas y guardes una clave nueva en Digitalisimo → Hosting. Los pedidos detenidos se retoman solos al guardarla.\n\n" . admin_url( 'admin.php?page=digitalisimo-hosting' ) );
	}

	/** Clave nueva: se vuelve a encolar lo detenido de este sitio. */
	public static function on_unblocked( $had = true ) {
		$w = get_option( self::WAITING, array() );
		if ( ! is_array( $w ) || ! $w ) return;
		delete_option( self::WAITING );
		foreach ( $w as $row ) {
			if ( self::HOOK_PROVISION === $row[0] || self::HOOK_RENEW === $row[0] ) {
				$order = wc_get_order( $row[1] );
				if ( $order ) { $key = self::HOOK_RENEW === $row[0] ? self::META_RENEW : self::META_STATE; $s = (array) $order->get_meta( $key ); unset( $s['blocked_noted'], $s['attempts'] ); $order->update_meta_data( $key, $s ); $order->save(); }
			}
			self::enqueue( $row[0], $row[1] );
		}
	}

	public static function alert_admin( $subject, $body ) {
		$to = (string) get_option( 'admin_email' );
		if ( '' === $to ) return;
		wp_mail( $to, '[' . wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) . '] DIGITALÍSIMO Hosting: ' . $subject, $body );
	}

	private static function order_link( $order ) { return method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ); }

	public static function date( $ts ) { return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d', (int) $ts ) : gmdate( 'Y-m-d', (int) $ts ); }

	/** El aviso de la parada en el escritorio, la lista de pedidos y la de servicios (en Hosting lo pinta la pantalla). */
	public static function admin_notice() {
		$page = (string) ( $GLOBALS['pagenow'] ?? '' );
		$sub  = (string) ( $_GET['page'] ?? '' );
		$here = 'index.php' === $page || ( 'admin.php' === $page && in_array( $sub, array( 'digitalisimo-hosting-services', 'wc-orders' ), true ) ) || ( 'edit.php' === $page && 'shop_order' === ( $_GET['post_type'] ?? '' ) );
		if ( ! $here || ! current_user_can( 'manage_woocommerce' ) ) return;
		$row = Digitalisimo_Hosting_Webuzo::any_block();
		if ( ! $row ) return;
		$html = '<p><strong>DIGITALÍSIMO Hosting detuvo el aprovisionamiento:</strong> Webuzo rechazó la clave de API (' . esc_html( (string) $row['msg'] ) . '). No se hará ninguna llamada a Webuzo hasta que guardes una clave nueva en <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>.</p>';
		if ( class_exists( 'Digitalisimo_Notices' ) ) Digitalisimo_Notices::show( 'hosting-webuzo-block', 'error', $html, array( 'hash' => (string) $row['t'], 'cap' => 'manage_woocommerce' ) );
		else echo '<div class="notice notice-error">' . $html . '</div>';
	}

	// ── Renovación ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Una vez al día: avisos de 30 y 7 días con su pedido de renovación, suspensión tras la gracia y lo detenido. */
	public static function daily( $now = null ) {
		if ( ! self::on() ) return;
		$now = $now ? (int) $now : time();
		$w = get_option( self::WAITING, array() );
		if ( $w && ! Digitalisimo_Hosting_Webuzo::from_settings()->blocked() ) self::on_unblocked();
		$grace = (int) ( Digitalisimo_Hosting::settings()['grace_days'] ?? 7 );
		foreach ( self::services() as $id ) {
			try { self::check_service( $id, $now, $grace ); } catch ( Throwable $e ) { self::svc_log( $id, 'Error en la revisión diaria: ' . $e->getMessage() ); }
		}
	}

	public static function check_service( $id, $now, $grace ) {
		$status = (string) self::svc( $id, 'status' );
		if ( ! in_array( $status, array( 'active', 'suspended' ), true ) ) return;
		$expires = (int) self::svc( $id, 'expires' );
		if ( ! $expires ) return;
		$days = (int) floor( ( $expires - $now ) / DAY_IN_SECONDS );
		if ( $days > 30 ) return;
		$renewal = self::renewal_order( $id );
		// Pagada (aunque la renovación esté a medias, p. ej. sin saldo en Namecheap): ni avisos ni suspensión.
		$paid = $renewal ? (array) $renewal->get_meta( self::META_RENEW ) : array();
		if ( ! $renewal || in_array( $renewal->get_status(), array( 'processing', 'completed' ), true ) || ! empty( $paid['paid'] ) ) return;
		$sent = self::svc( $id, 'notices' ); $sent = is_array( $sent ) && (int) ( $sent['cycle'] ?? 0 ) === $expires ? $sent : array( 'cycle' => $expires, 'sent' => array() );
		$type = '';
		if ( $days > 7 && ! in_array( '30', $sent['sent'], true ) ) $type = '30';
		elseif ( $days <= 7 && $days >= 0 && ! in_array( '7', $sent['sent'], true ) ) $type = '7';
		if ( $type ) {
			$sent['sent'][] = $type; self::svc_set( $id, array( 'notices' => $sent ) );
			$ok = self::send_renewal( $renewal, $id, $type );
			$renewal->add_order_note( 'DIGITALÍSIMO Hosting: aviso de vencimiento (' . $type . ' días) ' . ( $ok ? 'enviado' : 'no enviado (correo desactivado o falló)' ) . ' a ' . self::svc( $id, 'email' ) . '.' );
		}
		if ( 'active' === $status && $now > $expires + $grace * DAY_IN_SECONDS ) {
			if ( self::svc( $id, 'webuzo_user' ) ) { self::svc_set( $id, array( 'suspend_reason' => 'auto' ) ); self::enqueue( self::HOOK_SUSPEND, $id ); }
			else { self::svc_set( $id, array( 'status' => 'expired' ) ); self::svc_log( $id, 'Vencido sin pago (dominio sin hosting).' ); }
		}
	}

	private static function send_renewal( $order, $id, $type ) {
		$email = self::email( 'Digitalisimo_Hosting_Email_Renewal' );
		if ( ! $email || ! $email->is_enabled() ) return false;
		return (bool) $email->trigger( $order, array( 'type' => $type, 'domain' => (string) self::svc( $id, 'domain' ), 'expires' => (int) self::svc( $id, 'expires' ), 'pay_url' => $order->needs_payment() ? $order->get_checkout_payment_url() : '', 'grace' => (int) ( Digitalisimo_Hosting::settings()['grace_days'] ?? 7 ) ) );
	}

	/** El pedido de renovación pendiente del servicio (lo crea con los productos y precios vigentes). */
	public static function renewal_order( $id ) {
		$existing = (int) self::svc( $id, 'renewal_order' );
		if ( $existing ) {
			$o = wc_get_order( $existing );
			if ( $o && ! in_array( $o->get_status(), array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) return $o;
		}
		$products = array();
		foreach ( array( 'hosting_product', 'domain_product' ) as $key ) {
			$pid = (int) self::svc( $id, $key );
			if ( ! $pid ) continue;
			$product = wc_get_product( $pid );
			if ( ! $product ) { self::svc_log( $id, 'No se creó el pedido de renovación: el producto #' . $pid . ' ya no existe.' ); self::alert_admin( 'renovación sin producto', 'El servicio ' . self::svc( $id, 'domain' ) . ' vence pronto y su producto #' . $pid . ' ya no existe: crea su pedido de renovación a mano.' ); return null; }
			$products[ $key ] = $product;
		}
		if ( ! $products ) return null;
		$orig  = wc_get_order( (int) self::svc( $id, 'order' ) );
		$order = wc_create_order( array( 'customer_id' => (int) self::svc( $id, 'customer' ), 'created_via' => 'digitalisimo-hosting' ) );
		if ( is_wp_error( $order ) || ! $order ) return null;
		foreach ( $products as $key => $product ) {
			$item_id = $order->add_product( $product, 1 );
			if ( 'domain_product' === $key && $item_id && function_exists( 'wc_add_order_item_meta' ) ) wc_add_order_item_meta( $item_id, 'Dominio', (string) self::svc( $id, 'domain' ) );
		}
		if ( $orig ) $order->set_address( $orig->get_address( 'billing' ), 'billing' );
		else $order->set_billing_email( (string) self::svc( $id, 'email' ) );
		$order->update_meta_data( self::META_RENEWAL, $id );
		$order->calculate_totals();
		$order->save();
		$order->add_order_note( 'DIGITALÍSIMO Hosting: renovación anual de ' . self::svc( $id, 'domain' ) . ' (vence el ' . self::date( (int) self::svc( $id, 'expires' ) ) . ').' );
		self::svc_set( $id, array( 'renewal_order' => $order->get_id() ) );
		self::svc_log( $id, 'Pedido de renovación #' . $order->get_id() . ' creado.' );
		return $order;
	}

	public static function run_renew( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$id = (int) $order->get_meta( self::META_RENEWAL );
		if ( ! $id || ! self::is_service( $id ) ) return;
		$state = (array) $order->get_meta( self::META_RENEW );
		if ( ! empty( $state['done'] ) ) return;
		try {
			$stop = self::renew_domain( $order, $state, $id );
			if ( ! $stop && 'suspended' === self::svc( $id, 'status' ) && self::svc( $id, 'webuzo_user' ) && empty( $state['unsuspended'] ) ) {
				$r = Digitalisimo_Hosting_Webuzo::from_settings()->unsuspend( (string) self::svc( $id, 'webuzo_user' ) );
				if ( 'ok' !== $r['kind'] ) $stop = self::stop( $r['kind'], 'Webuzo (reactivar ' . self::svc( $id, 'webuzo_user' ) . '): ' . $r['message'] );
				else { $state['unsuspended'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save(); self::svc_set( $id, array( 'status' => 'active' ) ); $order->add_order_note( 'DIGITALÍSIMO Hosting: cuenta «' . self::svc( $id, 'webuzo_user' ) . '» reactivada en Webuzo.' ); }
			}
			if ( $stop ) { self::fail( $order, $state, $stop, self::HOOK_RENEW, $order->get_id(), $id ); return; }
			if ( empty( $state['extended'] ) ) {
				$old = (int) self::svc( $id, 'expires' );
				$new = strtotime( '+1 year', $old ? $old : time() );
				$state['extended'] = $new;
				self::svc_set( $id, array( 'expires' => $new, 'status' => 'active', 'renewal_order' => 0, 'notices' => array(), 'error' => '', 'suspend_reason' => '' ) );
				self::svc_log( $id, 'Renovado con el pedido #' . $order->get_id() . ' hasta el ' . self::date( $new ) . '.' );
			}
			$state['done'] = time(); unset( $state['attempts'], $state['blocked_noted'] );
			$order->update_meta_data( self::META_RENEW, $state ); $order->save();
			$email = self::email( 'Digitalisimo_Hosting_Email_Renewal' );
			if ( $email && $email->is_enabled() ) $email->trigger( $order, array( 'type' => 'renewed', 'domain' => (string) self::svc( $id, 'domain' ), 'expires' => (int) $state['extended'], 'pay_url' => '', 'grace' => 0 ) );
			if ( in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) $order->update_status( 'completed', 'DIGITALÍSIMO Hosting: renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
			else $order->add_order_note( 'DIGITALÍSIMO Hosting: renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
		} catch ( Throwable $e ) {
			self::fail( $order, $state, array( 'kind' => 'api', 'message' => 'Error interno: ' . $e->getMessage() ), self::HOOK_RENEW, $order->get_id(), $id );
		}
	}

	/**
	 * Renueva el dominio en Namecheap una sola vez: antes de la primera llamada se anota su vencimiento; si un intento
	 * anterior llegó (el vencimiento ya es posterior), no se vuelve a cobrar.
	 */
	private static function renew_domain( $order, array &$state, $id ) {
		if ( ! self::svc( $id, 'domain_registered' ) || ! empty( $state['domain'] ) ) return null;
		$domain = (string) self::svc( $id, 'domain' );
		$nc = Digitalisimo_Hosting_Namecheap::from_settings();
		$info = $nc->info( $domain );
		if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
		if ( ! $info['ours'] ) return self::stop( 'api', 'El dominio ' . $domain . ' ya no está en la cuenta de Namecheap; no se renovó.' );
		if ( ! isset( $state['domain_before'] ) ) { $state['domain_before'] = (int) $info['expires']; $order->update_meta_data( self::META_RENEW, $state ); $order->save(); }
		elseif ( (int) $info['expires'] > (int) $state['domain_before'] ) {
			$state['domain'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save();
			$order->add_order_note( 'DIGITALÍSIMO Hosting: el dominio ' . $domain . ' ya estaba renovado (intento anterior); no se cobró otra vez.' );
			return null;
		}
		$split = self::split( $domain );
		$price = $nc->price( $split ? $split[1] : '', 'RENEW' );
		if ( 'ok' !== $price['kind'] ) return self::stop( self::nc_kind( $price ), $price['message'] );
		$balance = $nc->balance();
		if ( 'ok' !== $balance['kind'] ) return self::stop( self::nc_kind( $balance ), $balance['message'] );
		if ( $balance['value'] < $price['value'] ) return self::stop( 'funds', sprintf( 'Saldo insuficiente en Namecheap para renovar %s: hay %s USD y cuesta %s USD. Recarga la cuenta y pulsa «Reintentar» en WooCommerce → Servicios de hosting.', $domain, number_format( $balance['value'], 2, '.', '' ), number_format( $price['value'], 2, '.', '' ) ) );
		$r = $nc->renew( $domain, 1 );
		if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
		$state['domain'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save();
		$order->add_order_note( 'DIGITALÍSIMO Hosting: dominio ' . $domain . ' renovado en Namecheap un año (cargo ' . $r['charged'] . ' USD).' );
		return null;
	}

	// ── Suspender, reactivar, reenviar ──────────────────────────────────────────────────────────────────────────

	public static function run_suspend( $id ) { self::toggle( $id, true ); }
	public static function run_unsuspend( $id ) { self::toggle( $id, false ); }

	private static function toggle( $id, $suspend ) {
		if ( ! self::is_service( $id ) ) return;
		$user = (string) self::svc( $id, 'webuzo_user' );
		$status = (string) self::svc( $id, 'status' );
		if ( '' === $user || ( $suspend && 'suspended' === $status ) || ( ! $suspend && 'suspended' !== $status ) ) return;
		$api = Digitalisimo_Hosting_Webuzo::from_settings();
		$r = $suspend ? $api->suspend( $user ) : $api->unsuspend( $user );
		$hook = $suspend ? self::HOOK_SUSPEND : self::HOOK_UNSUSPEND;
		if ( 'ok' !== $r['kind'] ) {
			$text = 'Webuzo (' . ( $suspend ? 'suspender' : 'reactivar' ) . ' ' . $user . '): ' . $r['message'];
			self::svc_set( $id, array( 'error' => $text ) ); self::svc_log( $id, $text );
			if ( 'transient' === $r['kind'] ) {
				$n = (int) self::svc( $id, 'toggle_attempts' ) + 1; self::svc_set( $id, array( 'toggle_attempts' => $n ) );
				if ( $n < self::MAX_ATTEMPTS ) { self::enqueue( $hook, $id, self::BACKOFF[ min( $n - 1, count( self::BACKOFF ) - 1 ) ], true ); return; }
			}
			if ( 'blocked' === $r['kind'] || 'auth' === $r['kind'] ) { self::wait( $hook, $id ); return; }
			self::alert_admin( ( $suspend ? 'no se suspendió ' : 'no se reactivó ' ) . self::svc( $id, 'domain' ), $text );
			return;
		}
		$auto = 'auto' === self::svc( $id, 'suspend_reason' );
		self::svc_set( $id, array( 'status' => $suspend ? 'suspended' : 'active', 'error' => '', 'toggle_attempts' => 0, 'suspended_at' => $suspend ? time() : 0 ) );
		self::svc_log( $id, $suspend ? ( $auto ? 'Suspendido por falta de pago.' : 'Suspendido a mano.' ) : 'Reactivado a mano.' );
		$renewal = wc_get_order( (int) self::svc( $id, 'renewal_order' ) );
		if ( $renewal ) $renewal->add_order_note( 'DIGITALÍSIMO Hosting: cuenta «' . $user . '» ' . ( $suspend ? 'suspendida' : 'reactivada' ) . ' en Webuzo.' );
		if ( $suspend && $auto && $renewal && ! in_array( $renewal->get_status(), array( 'processing', 'completed' ), true ) ) self::send_renewal( $renewal, $id, 'suspended' );
	}

	/** Reenvía el correo de accesos con la contraseña inicial guardada (cifrada) en el servicio. */
	public static function run_resend( $id ) {
		if ( ! self::is_service( $id ) ) return;
		$order = wc_get_order( (int) self::svc( $id, 'order' ) );
		if ( ! $order ) return;
		$items = self::items( $order );
		$state = self::state( $order );
		$state['hosting']['pass'] = (string) self::svc( $id, 'pass' );
		$data = self::access_data( $order, $state, $items, (string) self::svc( $id, 'domain' ) );
		$sent = self::send_access( $order, $data );
		$text = 'sent' === $sent ? 'Accesos reenviados a ' . $order->get_billing_email() . '.' : 'No se reenviaron los accesos (' . ( 'disabled' === $sent ? 'correo desactivado' : 'falló el envío' ) . ').';
		self::svc_log( $id, $text );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $text );
	}

	/** «Reintentar» de la pantalla de servicios: lo que esté a medias (aprovisionamiento o renovación pagada). */
	public static function retry( $id ) {
		if ( ! self::is_service( $id ) ) return false;
		$order = wc_get_order( (int) self::svc( $id, 'order' ) );
		$queued = false;
		if ( $order ) {
			$state = self::state( $order );
			if ( empty( $state['done'] ) ) { unset( $state['attempts'], $state['blocked_noted'] ); self::save_state( $order, $state ); self::enqueue( self::HOOK_PROVISION, $order->get_id() ); $queued = true; }
		}
		$renewal = wc_get_order( (int) self::svc( $id, 'renewal_order' ) );
		if ( $renewal ) {
			$s = (array) $renewal->get_meta( self::META_RENEW );
			if ( ! empty( $s['paid'] ) && empty( $s['done'] ) ) { unset( $s['attempts'], $s['blocked_noted'] ); $renewal->update_meta_data( self::META_RENEW, $s ); $renewal->save(); self::enqueue( self::HOOK_RENEW, $renewal->get_id() ); $queued = true; }
		}
		if ( 'suspended' !== self::svc( $id, 'status' ) && self::svc( $id, 'error' ) && ! $queued && self::svc( $id, 'suspend_reason' ) ) { self::enqueue( self::HOOK_SUSPEND, $id ); $queued = true; }
		return $queued;
	}
}
