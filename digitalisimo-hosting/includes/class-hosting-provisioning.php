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
	const HOOK_VERIFY    = 'digitalisimo_hosting_verify';
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
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'refunded' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'refunded' ), 20 );
		add_action( self::HOOK_PROVISION, array( __CLASS__, 'run_provision' ) );
		add_action( self::HOOK_RENEW, array( __CLASS__, 'run_renew' ) );
		add_action( self::HOOK_SUSPEND, array( __CLASS__, 'run_suspend' ) );
		add_action( self::HOOK_UNSUSPEND, array( __CLASS__, 'run_unsuspend' ) );
		add_action( self::HOOK_RESEND, array( __CLASS__, 'run_resend' ) );
		add_action( self::HOOK_VERIFY, array( __CLASS__, 'run_verify' ) );
		add_action( self::HOOK_DAILY, array( __CLASS__, 'daily' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'emails' ) );
		add_action( 'digitalisimo_hosting_webuzo_blocked', array( __CLASS__, 'on_blocked' ) );
		add_action( 'digitalisimo_hosting_webuzo_unblocked', array( __CLASS__, 'on_unblocked' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		if ( class_exists( 'Digitalisimo_Hosting_Services_Page' ) ) Digitalisimo_Hosting_Services_Page::init();
		if ( class_exists( 'Digitalisimo_Hosting_Pricing' ) ) Digitalisimo_Hosting_Pricing::init();
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

	/**
	 * Métodos de pago que no confirman el cobro solos (contra entrega, transferencia, cheque, pedidos manuales sin método):
	 * con ellos sólo cuenta como pagado cuando el administrador marca el pedido «completado».
	 */
	public static function offline_methods() { return (array) apply_filters( 'digitalisimo_hosting_offline_methods', array( 'cod', 'bacs', 'cheque', '' ) ); }

	/**
	 * ¿El cobro está confirmado? Fuera de línea: sólo «completado» (lo marca el administrador). En línea: is_paid() y
	 * fecha de pago (la pone payment_complete() de la pasarela).
	 */
	public static function payment_confirmed( $order ) {
		$method = (string) $order->get_payment_method();
		// Pasarelas en línea de confianza aunque no dejen número de transacción (lista vacía por defecto).
		$trusted = in_array( $method, (array) apply_filters( 'digitalisimo_hosting_trusted_methods', array() ), true );
		// Fuera de línea, o una pasarela que no dejó comprobante (número de transacción de payment_complete()): sólo
		// cuenta cuando el administrador marca el pedido «completado». Así una pasarela fuera de línea de terceros que no
		// está en la lista tampoco aprovisiona por estar en «procesando».
		if ( in_array( $method, self::offline_methods(), true ) || ( ! $trusted && '' === (string) $order->get_transaction_id() ) ) return 'completed' === $order->get_status();
		return $order->is_paid() && (bool) $order->get_date_paid();
	}

	/** Desde cuándo está encendida la venta automática (se anota al encenderla; si falta, desde ahora). */
	public static function since() {
		$o = Digitalisimo_Hosting::settings();
		if ( ! empty( $o['provision_since'] ) ) return (int) $o['provision_since'];
		$local = (array) get_option( Digitalisimo_Hosting::OPTION, array() );
		$local['provision_since'] = time();
		update_option( Digitalisimo_Hosting::OPTION, $local, false );
		return $local['provision_since'];
	}

	/** Una nota una sola vez por motivo (no llenar el pedido de repeticiones). */
	private static function note_once( $order, $key, $text ) {
		if ( $order->get_meta( '_digitalisimo_note_' . $key ) ) return false;
		$order->update_meta_data( '_digitalisimo_note_' . $key, time() );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $text );
		$order->save();
		return true;
	}

	/**
	 * Reembolso o cancelación de un pedido ya entregado (cuenta creada, dominio registrado o renovación hecha): no se
	 * deshace nada solo (un dominio registrado no se devuelve); nota privada y aviso al administrador para decidir.
	 */
	public static function refunded( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$renewal = (int) $order->get_meta( self::META_RENEWAL );
		$state = $renewal ? (array) $order->get_meta( self::META_RENEW ) : self::state( $order );
		$delivered = ! empty( $state['done'] ) || ! empty( $state['extended'] ) || 'done' === ( $state['hosting']['status'] ?? '' ) || ! empty( $state['domain']['registered'] ) || ! empty( $state['domain'] ) && $renewal;
		if ( ! $delivered ) return;
		$svc = $renewal ? $renewal : (int) $order->get_meta( self::META_SERVICE );
		$what = $svc ? self::svc( $svc, 'domain' ) . ( self::svc( $svc, 'webuzo_user' ) ? ' (cuenta «' . self::svc( $svc, 'webuzo_user' ) . '»)' : '' ) : 'el servicio';
		if ( self::note_once( $order, 'refunded', 'el pedido pasó a «' . $order->get_status() . '» después de entregar ' . $what . '; el servicio sigue activo y el dominio registrado. Suspéndelo o dalo de baja a mano si corresponde.' ) ) {
			self::alert_admin( 'pedido #' . $order->get_id() . ' ' . $order->get_status() . ' con servicio entregado', 'El pedido #' . $order->get_id() . ' pasó a «' . $order->get_status() . '» y ya se había entregado ' . $what . '. No se deshizo nada solo: revisa si hay que suspender la cuenta.' . "\n\n" . self::order_link( $order ) );
			if ( $svc ) self::svc_log( $svc, 'Pedido #' . $order->get_id() . ' «' . $order->get_status() . '» después de entregarse.' );
		}
	}

	/** Pedido pagado: encola el aprovisionamiento o, si es un pedido de renovación, la renovación. */
	public static function paid( $order_id ) {
		if ( ! self::on() ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$renewal = (int) $order->get_meta( self::META_RENEWAL );
		$offline = in_array( (string) $order->get_payment_method(), self::offline_methods(), true );
		if ( $renewal || self::items( $order )['hosting'] || self::items( $order )['domains'] ) {
			if ( ! self::payment_confirmed( $order ) ) {
				self::note_once( $order, 'unpaid', $offline ? 'pago fuera de línea (' . ( $order->get_payment_method() ?: 'manual' ) . '): no se aprovisiona hasta que marques el pedido «completado» al confirmar el cobro.' : 'la pasarela no dejó el pago confirmado (sin fecha de pago o sin número de transacción); no se aprovisiona hasta que marques el pedido «completado» al confirmar el cobro.' );
				return;
			}
		}
		if ( $renewal ) {
			$state = (array) $order->get_meta( self::META_RENEW );
			if ( empty( $state['done'] ) ) {
				if ( empty( $state['paid'] ) ) { $state['paid'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save(); }
				// Pagada la renovación, ninguna suspensión pendiente (reintento o en espera de clave) puede ejecutarse.
				self::cancel_suspension( $renewal, 'renovación #' . $order->get_id() . ' pagada' );
				self::enqueue( self::HOOK_RENEW, $order->get_id() );
			}
			return;
		}
		$items = self::items( $order );
		if ( ! $items['hosting'] && ! $items['domains'] ) return;
		$state = self::state( $order );
		if ( ! empty( $state['done'] ) ) return;
		$created = $order->get_date_created();
		if ( empty( $state['paid'] ) && $created && $created->getTimestamp() < self::since() ) {
			self::note_once( $order, 'before', 'el pedido es anterior al encendido de la venta automática; no se aprovisiona solo (hazlo a mano o pulsa «Reintentar» si lo quieres).' );
			return;
		}
		// La marca de pago: sin ella ninguna tarea llama a Webuzo ni a Namecheap por este pedido.
		if ( empty( $state['paid'] ) ) { $state['paid'] = time(); self::save_state( $order, $state ); }
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

	/**
	 * Sufijos de segundo nivel conocidos (además de los configurados en «Productos por extensión»): se acepta
	 * negocio.com.mx, pero no un subdominio (www.cliente.lat, mail.cliente.com) de ninguna extensión.
	 */
	const SECOND_LEVEL = array( 'com.mx', 'org.mx', 'net.mx', 'edu.mx', 'gob.mx', 'nom.mx', 'com.ar', 'org.ar', 'net.ar', 'com.co', 'org.co', 'net.co', 'nom.co', 'com.pe', 'org.pe', 'net.pe', 'nom.pe', 'com.ve', 'com.ec', 'com.gt', 'com.sv', 'com.hn', 'com.ni', 'com.pa', 'com.do', 'com.uy', 'com.py', 'com.bo', 'com.br', 'net.br', 'org.br', 'co.uk', 'org.uk', 'me.uk', 'com.es', 'org.es', 'nom.es', 'com.au', 'net.au', 'org.au', 'co.nz', 'co.za', 'co.jp', 'co.in', 'com.cn' );

	/**
	 * array( sld, tld, extensión ) de un dominio registrable: una sola etiqueta delante de una extensión configurada, de
	 * un sufijo de segundo nivel conocido o de una extensión de un nivel. Un subdominio (más etiquetas) da null.
	 */
	public static function split( $domain ) {
		$domain = strtolower( (string) $domain );
		$exts = array_map( function ( $e ) { return ltrim( strtolower( $e ), '.' ); }, array_keys( Digitalisimo_Hosting::domain_products() ) );
		$exts = array_unique( array_merge( $exts, self::SECOND_LEVEL ) );
		usort( $exts, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		foreach ( $exts as $ext ) {
			if ( strlen( $domain ) > strlen( $ext ) + 1 && substr( $domain, -strlen( $ext ) - 1 ) === '.' . $ext ) {
				$sld = substr( $domain, 0, -strlen( $ext ) - 1 );
				return false === strpos( $sld, '.' ) ? array( $sld, $ext, '.' . $ext ) : null;
			}
		}
		$parts = explode( '.', $domain );
		return 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ? array( $parts[0], $parts[1], '.' . $parts[1] ) : null;
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
	/** Nombres que nunca se dan a un cliente (cuentas del sistema y del panel). */
	const RESERVED_USERS = array( 'root', 'admin', 'administrator', 'webuzo', 'mysql', 'postgres', 'nobody', 'daemon', 'bin', 'sys', 'sync', 'apache', 'nginx', 'www', 'wwwdata', 'ftp', 'mail', 'postfix', 'dovecot', 'exim', 'named', 'bind', 'cpanel', 'softaculous', 'test', 'user', 'support', 'backup', 'operator', 'guest', 'system', 'httpd', 'redis', 'memcached', 'lsadm', 'litespeed' );

	public static function username( $domain, array $taken, $prefix = '' ) {
		foreach ( self::RESERVED_USERS as $reserved ) $taken[ $reserved ] = true;
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
			'domain_product' => $dom ? $dom['product'] : 0, 'status' => 'pending', 'expires' => 0, 'created' => time(),
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
		if ( ! empty( $state['done'] ) || ! self::may_run( $order, $state ) ) return;
		$state = self::take_retry( $order, $state, self::META_STATE );
		try {
			$items  = self::items( $order );
			$domain = '';
			if ( $items['domains'] ) $domain = $items['domains'][0]['domain'];
			elseif ( $items['hosting'] ) $domain = $items['hosting'][0]['domain'];
			$service = self::service_for( $order, $items, $domain );
			$stop = self::check_order( $order, $items, $domain );
			// Primero el dominio y su dueño; la cuenta de Webuzo sólo después (nunca una cuenta para un dominio ajeno).
			if ( ! $stop ) $stop = self::check_owner( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_domain( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_hosting( $order, $state, $items, $domain, $service );
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

	/**
	 * ¿Puede una tarea gastar en este pedido? Sólo con la venta automática encendida, pagado (marca de `paid()`) y vivo:
	 * un pedido cancelado, reembolsado o fallido después de pagarse no crea cuentas ni registra dominios aunque quede un
	 * reintento en cola, se levante la parada de Webuzo o alguien pulse «Reintentar».
	 */
	/** «Reintentar» deja sólo una marca; la tarea la aplica sobre el estado recién leído (sin pisar uno más nuevo). */
	private static function take_retry( $order, array $state, $key ) {
		if ( ! $order->get_meta( '_digitalisimo_retry' ) ) return $state;
		unset( $state['attempts'], $state['blocked_noted'] );
		$order->update_meta_data( $key, $state );
		$order->delete_meta_data( '_digitalisimo_retry' );
		$order->save();
		return $state;
	}

	private static function may_run( $order, array $state ) {
		if ( ! self::on() || empty( $state['paid'] ) ) return false;
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed', 'trash', 'pending', 'checkout-draft' ), true ) ) {
			if ( empty( $state['dead_noted'] ) ) { $state['dead_noted'] = 1; $order->update_meta_data( $order->get_meta( self::META_RENEWAL ) ? self::META_RENEW : self::META_STATE, $state ); $order->add_order_note( 'DIGITALÍSIMO Hosting: el pedido está «' . $order->get_status() . '»; no se aprovisiona ni se renueva nada.' ); $order->save(); }
			return false;
		}
		return true;
	}

	/**
	 * ¿Es de este cliente el dominio? Si ya tiene un servicio aquí o ya está en nuestra cuenta de Namecheap, sólo lo usa
	 * el mismo cliente: el mismo usuario de WordPress (un pedido de invitado nunca coincide, porque su correo no está
	 * verificado). Si no, revisión manual: en espera, nota privada y sin llamar a Webuzo. Un dominio registrado fuera y
	 * que no es nuestro se puede hospedar (no apunta a nuestros nameservers hasta que su dueño los cambia).
	 */
	private static function check_owner( $order, array &$state, array $items, $domain, $service ) {
		// Sin caché: se revisa al empezar y otra vez justo antes de crear la cuenta (otro pedido pudo reclamar el dominio
		// mientras éste esperaba una clave de Webuzo o un reintento).
		$customer = (int) $order->get_customer_id();
		$known = false;
		foreach ( self::services() as $sid ) {
			if ( $sid === (int) $service || strtolower( (string) self::svc( $sid, 'domain' ) ) !== $domain ) continue;
			if ( ! self::claims( $sid ) ) continue;
			$owner = (int) self::svc( $sid, 'customer' );
			if ( ! $customer || $owner !== $customer ) return self::stop( 'owner', 'El dominio ' . $domain . ' ya tiene un servicio de otro cliente (servicio #' . $sid . ', pedido #' . (int) self::svc( $sid, 'order' ) . '): no se creó nada. Revísalo a mano.' );
			$known = true;
		}
		if ( ! $items['domains'] ) {
			// Hosting con dominio propio: ¿está en nuestra cuenta de Namecheap (lo vendimos nosotros)?
			$nc = Digitalisimo_Hosting_Namecheap::from_settings();
			if ( $nc->configured() ) {
				$info = $nc->info( $domain );
				if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
				if ( $info['ours'] && ! $known ) return self::stop( 'owner', 'El dominio ' . $domain . ' está en nuestra cuenta de Namecheap y no consta como de este cliente: no se creó la cuenta. Revísalo a mano.' );
			}
		}
		return null;
	}

	/**
	 * ¿Este servicio reclama su dominio? Los entregados (activo, suspendido, vencido) y los pedidos PAGADOS que compran
	 * el dominio aunque aún no se entreguen (p. ej. en espera por saldo en Namecheap). No reclaman: un pedido sólo de
	 * hosting que no se entregó, uno detenido por conflicto de dueño, ni uno cancelado o reembolsado.
	 */
	private static function claims( $sid ) {
		if ( self::svc( $sid, 'owner_conflict' ) ) return false;
		$status = (string) self::svc( $sid, 'status' );
		if ( in_array( $status, array( 'active', 'suspended', 'expired' ), true ) ) return true;
		if ( ! (int) self::svc( $sid, 'domain_product' ) ) return false;
		$o = wc_get_order( (int) self::svc( $sid, 'order' ) );
		if ( ! $o || in_array( $o->get_status(), array( 'cancelled', 'refunded', 'failed', 'trash' ), true ) ) return false;
		$st = self::state( $o );
		return ! empty( $st['paid'] );
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
		// Otra vez el dueño, justo antes de crear (nada guardado de antes cuenta).
		$owner = self::check_owner( $order, $state, $items, $domain, $service );
		if ( $owner ) return $owner;
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
			if ( $info['ours'] && empty( $state['domain']['create_sent'] ) ) {
				// Ya estaba en la cuenta y este pedido nunca pidió registrarlo: es de otro cliente (o de la casa). Adoptarlo
				// cambiaría sus nameservers y le daría su web y su correo al comprador.
				return self::stop( 'data', 'El dominio ' . $domain . ' ya está en la cuenta de Namecheap y no lo registró este pedido: no se tocó (ni sus nameservers). Revísalo a mano.' );
			}
			if ( $info['ours'] ) {
				$state['domain'] = array( 'registered' => time(), 'existed' => 1, 'create_sent' => $state['domain']['create_sent'] );
				self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el dominio ' . $domain . ' ya estaba en la cuenta de Namecheap; no se registró otra vez.' );
			} else {
				list( $contact, $missing ) = self::contact( $order );
				if ( $missing ) return self::stop( 'data', 'Faltan datos del titular del dominio en la facturación del pedido (' . implode( ', ', $missing ) . '). Complétalos en el pedido y reintenta.' );
				$price = $nc->price( $tld, 'REGISTER' );
				if ( 'ok' !== $price['kind'] ) return self::stop( self::nc_kind( $price ), $price['message'] );
				$guard = self::price_guard( $items['domains'][0]['item'], $price['value'], 'registrar ' . $domain, $order );
				if ( $guard ) return $guard;
				$balance = $nc->balance();
				if ( 'ok' !== $balance['kind'] ) return self::stop( self::nc_kind( $balance ), $balance['message'] );
				if ( $balance['value'] < $price['value'] ) return self::stop( 'funds', sprintf( 'Saldo insuficiente en Namecheap para registrar %s: hay %s USD y cuesta %s USD. Recarga la cuenta y pulsa «Reintentar» en WooCommerce → Servicios de hosting.', $domain, number_format( $balance['value'], 2, '.', '' ), number_format( $price['value'], 2, '.', '' ) ) );
				// Antes de pedirlo: si la respuesta se pierde, el reintento sabrá que este pedido lo registró.
				$state['domain'] = array( 'create_sent' => time() );
				self::save_state( $order, $state );
				$r = $nc->create( $domain, $contact, 1 );
				if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
				$state['domain'] = array( 'registered' => time(), 'id' => $r['domain_id'], 'create_sent' => $state['domain']['create_sent'] );
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

	/** Lo cobrado por el dominio (sin IVA, tras descuentos) contra su costo real de hoy (Digitalisimo_Hosting_Pricing::guard). */
	private static function price_guard( $item, $cost_usd, $label, $order = null ) {
		if ( ! class_exists( 'Digitalisimo_Hosting_Pricing' ) ) return null;
		$charged = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
		$currency = $order && is_callable( array( $order, 'get_currency' ) ) ? (string) $order->get_currency() : null;
		return Digitalisimo_Hosting_Pricing::guard( $charged, $cost_usd, $label, $currency );
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
		if ( 'sent' !== $sent ) self::alert_admin( 'accesos sin enviar (pedido #' . $order->get_id() . ')', 'El servicio ' . $domain . ' quedó listo pero el correo de accesos no salió (' . ( 'disabled' === $sent ? 'está desactivado en WooCommerce → Ajustes → Correos' : 'falló el envío' ) . '). Usa «Reenviar accesos» en WooCommerce → Servicios de hosting.' );
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
		if ( 'owner' === $kind && $service ) self::svc_set( $service, array( 'owner_conflict' => 1 ) );
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
		$row = Digitalisimo_Hosting_Webuzo::from_settings()->blocked();
		if ( ! $row ) return;
		$html = '<p><strong>DIGITALÍSIMO Hosting detuvo el aprovisionamiento:</strong> Webuzo rechazó la clave de API (' . esc_html( (string) $row['msg'] ) . '). No se hará ninguna llamada a Webuzo hasta que guardes una clave nueva en <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>.</p>';
		// Con la X de WordPress y el cierre recordado (ayudante de avisos de los siete plugins; id por sitio). Cerrado, un
		// error vuelve al día siguiente mientras la parada siga.
		if ( class_exists( 'Digitalisimo_Notices' ) ) Digitalisimo_Notices::show( Digitalisimo_Notices::site( 'hosting-webuzo-block' ), 'error', $html, array( 'hash' => (string) $row['t'], 'cap' => 'manage_woocommerce' ) );
		else echo '<div class="notice notice-error is-dismissible">' . $html . '</div>';
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
			try { self::watchdog( $id ); self::check_service( $id, $now, $grace ); } catch ( Throwable $e ) { self::svc_log( $id, 'Error en la revisión diaria: ' . $e->getMessage() ); }
		}
	}

	/**
	 * Un aprovisionamiento que quedó «pendiente» sin tarea en cola (PHP lo cortó por tiempo o memoria a media llamada):
	 * se vuelve a encolar (cada paso consulta antes de crear, no duplica) y se avisa al administrador.
	 */
	private static function watchdog( $id ) {
		if ( 'pending' !== self::svc( $id, 'status' ) || time() - (int) self::svc( $id, 'created' ) < HOUR_IN_SECONDS ) return;
		$order_id = (int) self::svc( $id, 'order' );
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_PROVISION, array( $order_id ), self::GROUP ) ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$state = self::state( $order );
		if ( ! empty( $state['done'] ) || ! empty( $state['watchdog'] ) && time() - (int) $state['watchdog'] < DAY_IN_SECONDS ) return;
		$state['watchdog'] = time(); self::save_state( $order, $state );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: el aprovisionamiento quedó a medias sin tarea en cola; se retoma (no duplica: cada paso consulta antes de crear).' );
		self::alert_admin( 'pedido #' . $order_id . ' retomado', 'El aprovisionamiento del pedido #' . $order_id . ' (' . self::svc( $id, 'domain' ) . ') quedó a medias, probablemente cortado por tiempo o memoria. Se volvió a encolar.' . "

" . self::order_link( $order ) );
		self::enqueue( self::HOOK_PROVISION, $order_id );
	}

	public static function check_service( $id, $now, $grace ) {
		$status = (string) self::svc( $id, 'status' );
		if ( ! in_array( $status, array( 'active', 'suspended' ), true ) ) return;
		$expires = (int) self::svc( $id, 'expires' );
		if ( ! $expires ) return;
		$days = (int) floor( ( $expires - $now ) / DAY_IN_SECONDS );
		if ( $days > 30 ) return;
		$renewal = self::renewal_order( $id );
		if ( ! $renewal ) return;
		// Pagada con cobro confirmado (aunque la renovación esté a medias, p. ej. sin saldo en Namecheap): ni avisos ni
		// suspensión. En «procesando» SIN cobro confirmado (contra entrega) no frena nada y se avisa al administrador.
		if ( self::renewal_confirmed( $renewal ) ) return;
		if ( in_array( $renewal->get_status(), array( 'processing', 'on-hold' ), true ) && self::note_once( $renewal, 'unconfirmed', 'renovación en «' . $renewal->get_status() . '» sin cobro confirmado (' . ( $renewal->get_payment_method() ?: 'manual' ) . '); márcala «completado» al cobrar. Mientras tanto el servicio vence y se suspende como si no estuviera pagada.' ) ) {
			self::alert_admin( 'renovación #' . $renewal->get_id() . ' sin cobro confirmado', 'La renovación de ' . self::svc( $id, 'domain' ) . ' está en «' . $renewal->get_status() . '» pero el cobro no está confirmado. Márcala «completado» cuando cobres; si no, el servicio vence y se suspende.' . "\n\n" . self::order_link( $renewal ) );
		}
		$sent = self::svc( $id, 'notices' ); $sent = is_array( $sent ) && (int) ( $sent['cycle'] ?? 0 ) === $expires ? $sent : array( 'cycle' => $expires, 'sent' => array() );
		$type = '';
		if ( $days > 7 && ! in_array( '30', $sent['sent'], true ) ) $type = '30';
		elseif ( $days <= 7 && $days >= 0 && ! in_array( '7', $sent['sent'], true ) ) $type = '7';
		if ( $type ) {
			$sent['sent'][] = $type; self::svc_set( $id, array( 'notices' => $sent ) );
			$ok = self::send_renewal( $renewal, $id, $type );
			$renewal->add_order_note( 'DIGITALÍSIMO Hosting: aviso de vencimiento (' . $type . ' días) ' . ( $ok ? 'enviado' : 'no enviado (correo desactivado o falló)' ) . ' a ' . self::svc( $id, 'email' ) . '.' );
		}
		// Una suspensión o reactivación pedida y aún sin confirmar: no se pide otra.
		$pt = self::svc( $id, 'pending_toggle' );
		if ( is_array( $pt ) && ! empty( $pt['op'] ) ) return;
		// Reactivado a mano por el administrador: se respeta su prórroga (visible en Servicios de hosting).
		if ( (int) self::svc( $id, 'reprieve_until' ) > $now ) return;
		if ( 'active' === $status && $now > $expires + $grace * DAY_IN_SECONDS ) {
			if ( self::svc( $id, 'webuzo_user' ) ) { self::svc_set( $id, array( 'suspend_reason' => 'auto', 'suspend_for' => $expires ) ); self::enqueue( self::HOOK_SUSPEND, $id ); }
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
			$item = 'domain_product' === $key && $item_id ? $order->get_item( $item_id ) : null;
			if ( $item ) { $item->add_meta_data( 'Dominio', (string) self::svc( $id, 'domain' ), true ); $item->save(); }
		}
		if ( $orig ) foreach ( (array) $orig->get_address( 'billing' ) as $key => $value ) { if ( is_callable( array( $order, 'set_billing_' . $key ) ) ) $order->{'set_billing_' . $key}( $value ); }
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
		if ( ! empty( $state['done'] ) || ! self::may_run( $order, $state ) ) return;
		$state = self::take_retry( $order, $state, self::META_RENEW );
		try {
			// El dominio y el hosting por separado: si el dominio no se puede renovar (saldo, costo, error de Namecheap), el
			// cliente que pagó igual recupera su hosting y su año; sólo el dominio queda en espera con aviso.
			$dstop = self::renew_domain( $order, $state, $id );
			$stop = null;
			$manual = 'manual' === self::svc( $id, 'suspend_reason' );
			if ( ! $stop && $manual && 'suspended' === self::svc( $id, 'status' ) && empty( $state['manual_noted'] ) ) { $state['manual_noted'] = 1; $order->add_order_note( 'DIGITALÍSIMO Hosting: la cuenta está suspendida a mano; el pago no la reactiva (usa «Reactivar» en Servicios de hosting).' ); }
			if ( ! $manual && 'suspended' === self::svc( $id, 'status' ) && self::svc( $id, 'webuzo_user' ) && empty( $state['unsuspended'] ) ) {
				// La reactivación va en su propia tarea: se pide sin skip y se confirma con la lista de cuentas.
				$state['unsuspended'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::svc_set( $id, array( 'unsuspend_reason' => 'paid', 'last_renewal_order' => $order->get_id() ) );
				self::enqueue( self::HOOK_UNSUSPEND, $id );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: se pidió reactivar la cuenta «' . self::svc( $id, 'webuzo_user' ) . '» en Webuzo (se confirma con la lista de cuentas).' );
			}
			if ( $stop ) { self::fail( $order, $state, $stop, self::HOOK_RENEW, $order->get_id(), $id ); return; }
			if ( empty( $state['extended'] ) ) {
				$old = (int) self::svc( $id, 'expires' );
				$new = strtotime( '+1 year', $old ? $old : time() );
				$state['extended'] = $new;
				$order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::cancel_suspension( $id, 'renovado con el pedido #' . $order->get_id() );
				self::svc_set( $id, $manual ? array( 'expires' => $new, 'renewal_order' => 0, 'notices' => array(), 'error' => '', 'reprieve_until' => 0 ) : array( 'expires' => $new, 'status' => 'suspended' === self::svc( $id, 'status' ) && self::svc( $id, 'webuzo_user' ) ? 'suspended' : 'active', 'renewal_order' => 0, 'last_renewal_order' => $order->get_id(), 'notices' => array(), 'error' => '', 'suspend_reason' => '', 'suspend_for' => 0, 'reprieve_until' => 0 ) );
				self::svc_log( $id, 'Renovado con el pedido #' . $order->get_id() . ' hasta el ' . self::date( $new ) . '.' );
			}
			if ( $dstop ) {
				self::svc_set( $id, array( 'domain_pending_order' => $order->get_id() ) );
				if ( empty( $state['domain_pending_noted'] ) ) { $state['domain_pending_noted'] = 1; $order->add_order_note( 'DIGITALÍSIMO Hosting: hosting renovado hasta el ' . self::date( (int) $state['extended'] ) . ( ! empty( $state['unsuspended'] ) ? ' y reactivado' : '' ) . '; la renovación del dominio queda pendiente.' ); }
				self::fail( $order, $state, $dstop, self::HOOK_RENEW, $order->get_id(), $id );
				return;
			}
			self::svc_set( $id, array( 'domain_pending_order' => 0 ) );
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
		$item = null;
		foreach ( $order->get_items() as $row ) if ( (int) $row->get_product_id() === (int) self::svc( $id, 'domain_product' ) ) { $item = $row; break; }
		if ( ! $item ) return self::stop( 'data', 'El pedido de renovación no trae la línea del dominio ' . $domain . ': no se renovó (se cobraría sin cobrarle al cliente).' );
		$guard = self::price_guard( $item, $price['value'], 'renovar ' . $domain, $order );
		if ( $guard ) return $guard;
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
		// Al ejecutarse (venga de la revisión diaria, de un reintento o de levantar una parada) se vuelve a comprobar que
		// sigue vencido y sin pagar; sólo la suspensión que el administrador pidió a mano no depende del pago.
		if ( $suspend && 'manual' !== self::svc( $id, 'suspend_reason' ) && ! self::overdue( $id ) ) { self::svc_set( $id, array( 'suspend_reason' => '', 'suspend_for' => 0, 'toggle_attempts' => 0 ) ); self::svc_log( $id, 'Suspensión cancelada: ya no está vencido sin pago.' ); return; }
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
		// Webuzo aceptó la orden («done»), pero eso no prueba nada (prueba real: con skip=1 contestaba éxito sin cambiar el
		// estado, y sin skip el cambio tarda ~45 s). Se confirma con la lista de cuentas en una tarea aparte.
		$pt = self::svc( $id, 'pending_toggle' ); $pt = is_array( $pt ) && ( $pt['op'] ?? '' ) === ( $suspend ? 'suspend' : 'unsuspend' ) ? $pt : array( 'sends' => 0 );
		self::svc_set( $id, array( 'pending_toggle' => array( 'op' => $suspend ? 'suspend' : 'unsuspend', 'sends' => (int) $pt['sends'] + 1, 'checks' => 0, 't' => time() ), 'error' => '', 'toggle_attempts' => 0 ) );
		self::svc_log( $id, 'Webuzo aceptó ' . ( $suspend ? 'suspender' : 'reactivar' ) . ' «' . $user . '»; se confirma con la lista de cuentas.' );
		self::enqueue( self::HOOK_VERIFY, $id, self::VERIFY_DELAYS[0], true );
	}

	/** Esperas para confirmar una suspensión o reactivación (segundos) y cuántas veces se reenvía si no cambió. */
	const VERIFY_DELAYS = array( 60, 120, 300, 600 );
	const MAX_SENDS     = 3;

	/**
	 * Confirma con `act=users` (la consulta de sólo lectura, cerrada) que la suspensión o reactivación pedida se aplicó:
	 * `status` = "suspended" o "". Si aún no, se vuelve a mirar más tarde; si tras las esperas no cambió, es una falla
	 * reintentable: se reenvía la orden (hasta 3 veces) y después se avisa al administrador.
	 */
	public static function run_verify( $id ) {
		if ( ! self::is_service( $id ) ) return;
		$pt = self::svc( $id, 'pending_toggle' );
		if ( ! is_array( $pt ) || empty( $pt['op'] ) ) return;
		$suspend = 'suspend' === $pt['op'];
		$user = (string) self::svc( $id, 'webuzo_user' );
		$now = Digitalisimo_Hosting_Webuzo::from_settings()->status_of( $user, $r );
		$checks = (int) ( $pt['checks'] ?? 0 ) + 1;
		if ( null === $now ) {
			if ( 'blocked' === $r['kind'] || 'auth' === $r['kind'] ) { self::wait( self::HOOK_VERIFY, $id ); return; }
			if ( 'ok' === $r['kind'] ) { self::svc_set( $id, array( 'pending_toggle' => array(), 'error' => 'La cuenta «' . $user . '» no aparece en Webuzo.' ) ); self::alert_admin( 'cuenta no encontrada en Webuzo', 'La cuenta «' . $user . '» de ' . self::svc( $id, 'domain' ) . ' no aparece en la lista de Webuzo.' ); return; }
			if ( $checks < count( self::VERIFY_DELAYS ) ) { $pt['checks'] = $checks; self::svc_set( $id, array( 'pending_toggle' => $pt ) ); self::enqueue( self::HOOK_VERIFY, $id, self::VERIFY_DELAYS[ $checks ], true ); }
			return;
		}
		if ( $suspend === ( 'suspended' === $now ) ) { self::svc_set( $id, array( 'pending_toggle' => array() ) ); self::confirmed( $id, $suspend ); return; }
		if ( $checks < count( self::VERIFY_DELAYS ) ) { $pt['checks'] = $checks; self::svc_set( $id, array( 'pending_toggle' => $pt ) ); self::enqueue( self::HOOK_VERIFY, $id, self::VERIFY_DELAYS[ $checks ], true ); return; }
		$text = 'Webuzo aceptó ' . ( $suspend ? 'suspender' : 'reactivar' ) . ' «' . $user . '» pero la cuenta sigue ' . ( 'suspended' === $now ? 'suspendida' : 'activa' ) . '.';
		if ( (int) $pt['sends'] < self::MAX_SENDS ) {
			$pt['checks'] = 0; self::svc_set( $id, array( 'pending_toggle' => $pt, 'error' => $text ) );
			self::svc_log( $id, $text . ' Se reenvía la orden (' . ( (int) $pt['sends'] + 1 ) . ' de ' . self::MAX_SENDS . ').' );
			self::enqueue( $suspend ? self::HOOK_SUSPEND : self::HOOK_UNSUSPEND, $id, self::VERIFY_DELAYS[0], true );
			return;
		}
		self::svc_set( $id, array( 'pending_toggle' => array(), 'error' => $text ) ); self::svc_log( $id, $text . ' Se dejó de insistir.' );
		self::alert_admin( ( $suspend ? 'no se suspendió ' : 'no se reactivó ' ) . self::svc( $id, 'domain' ), $text . ' Se envió ' . self::MAX_SENDS . ' veces; revísalo en Webuzo.' );
	}

	/** Lo que pasa cuando Webuzo ya muestra la cuenta suspendida o activa. */
	private static function confirmed( $id, $suspend ) {
		$user = (string) self::svc( $id, 'webuzo_user' );
		$auto = 'auto' === self::svc( $id, 'suspend_reason' );
		$manual_unsuspend = 'manual' === self::svc( $id, 'unsuspend_reason' );
		self::svc_set( $id, array( 'status' => $suspend ? 'suspended' : 'active', 'error' => '', 'toggle_attempts' => 0, 'suspended_at' => $suspend ? time() : 0 ) );
		if ( ! $suspend ) {
			$fields = array( 'suspend_reason' => '', 'suspend_for' => 0, 'unsuspend_reason' => '' );
			// Reactivación manual del administrador: prórroga de los días de gracia (mínimo 7) desde hoy (una sola gracia);
			// la revisión diaria no vuelve a suspender antes. Se ve en Servicios de hosting. Pagar no da prórroga.
			if ( $manual_unsuspend ) $fields['reprieve_until'] = time() + max( 7, (int) ( Digitalisimo_Hosting::settings()['grace_days'] ?? 7 ) ) * DAY_IN_SECONDS;
			self::svc_set( $id, $fields );
		}
		self::svc_log( $id, $suspend ? ( $auto ? 'Suspendido por falta de pago (confirmado en Webuzo).' : 'Suspendido a mano (confirmado en Webuzo).' ) : ( $manual_unsuspend ? 'Reactivado a mano (confirmado en Webuzo); prórroga hasta el ' . self::date( (int) self::svc( $id, 'reprieve_until' ) ) . '.' : 'Reactivado (confirmado en Webuzo).' ) );
		$renewal = wc_get_order( (int) ( self::svc( $id, 'renewal_order' ) ?: self::svc( $id, 'last_renewal_order' ) ) );
		if ( $renewal ) $renewal->add_order_note( 'DIGITALÍSIMO Hosting: cuenta «' . $user . '» ' . ( $suspend ? 'suspendida' : 'reactivada' ) . ' en Webuzo.' );
		if ( $suspend && 'manual' !== self::svc( $id, 'suspend_reason' ) && ! self::overdue( $id ) ) {
			// Pagó mientras la orden de suspender viajaba: se reactiva enseguida.
			self::svc_set( $id, array( 'unsuspend_reason' => 'paid' ) ); self::svc_log( $id, 'Ya pagó: se reactiva.' );
			self::enqueue( self::HOOK_UNSUSPEND, $id );
			return;
		}
		if ( $suspend && $auto && $renewal && ! self::renewal_confirmed( $renewal ) ) self::send_renewal( $renewal, $id, 'suspended' );
	}

	/**
	 * Cancela cualquier suspensión pendiente del servicio: la que espera un reintento, la que espera una clave nueva de
	 * Webuzo y la marca de la revisión diaria. Lo llama el pago de la renovación.
	 */
	public static function cancel_suspension( $id, $why ) {
		$had = (bool) self::svc( $id, 'suspend_for' ) || 'auto' === self::svc( $id, 'suspend_reason' );
		if ( 'manual' !== self::svc( $id, 'suspend_reason' ) ) self::svc_set( $id, array( 'suspend_reason' => '', 'suspend_for' => 0, 'toggle_attempts' => 0 ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) as_unschedule_all_actions( self::HOOK_SUSPEND, array( (int) $id ), self::GROUP );
		$w = get_option( self::WAITING, array() );
		if ( is_array( $w ) && isset( $w[ self::HOOK_SUSPEND . ':' . (int) $id ] ) ) { unset( $w[ self::HOOK_SUSPEND . ':' . (int) $id ] ); update_option( self::WAITING, $w, false ); $had = true; }
		if ( $had ) self::svc_log( $id, 'Suspensión pendiente cancelada: ' . $why . '.' );
	}

	/** ¿Sigue vencido sin pago, como cuando la revisión diaria decidió suspender (mismo vencimiento, renovación sin pagar)? */
	private static function overdue( $id ) {
		if ( ! (int) self::svc( $id, 'suspend_for' ) || (int) self::svc( $id, 'suspend_for' ) !== (int) self::svc( $id, 'expires' ) ) return false;
		$renewal = wc_get_order( (int) self::svc( $id, 'renewal_order' ) );
		return ! ( $renewal && self::renewal_confirmed( $renewal ) );
	}

	/** ¿La renovación tiene el cobro confirmado (marca de paid() o la regla de pago confirmado)? */
	private static function renewal_confirmed( $renewal ) {
		$s = (array) $renewal->get_meta( self::META_RENEW );
		return ! empty( $s['paid'] ) || self::payment_confirmed( $renewal );
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
		// Sólo al correo con el que se contrató el servicio, nunca al de facturación actual del pedido (que se puede editar).
		$data['to'] = (string) self::svc( $id, 'email' );
		if ( '' === $data['to'] ) return;
		$sent = self::send_access( $order, $data );
		$text = 'sent' === $sent ? 'Accesos reenviados a ' . $data['to'] . '.' : 'No se reenviaron los accesos (' . ( 'disabled' === $sent ? 'correo desactivado' : 'falló el envío' ) . ').';
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
			if ( empty( $state['done'] ) ) {
				// Sólo una marca: la tarea limpia los reintentos sobre el estado que lea en ese momento.
				if ( empty( $state['paid'] ) && self::payment_confirmed( $order ) ) { $state['paid'] = time(); self::save_state( $order, $state ); }
				$order->update_meta_data( '_digitalisimo_retry', time() ); $order->save();
				self::enqueue( self::HOOK_PROVISION, $order->get_id() ); $queued = true;
			}
		}
		$renewal = wc_get_order( (int) ( self::svc( $id, 'renewal_order' ) ?: self::svc( $id, 'domain_pending_order' ) ) );
		if ( $renewal ) {
			$s = (array) $renewal->get_meta( self::META_RENEW );
			if ( ! empty( $s['paid'] ) && empty( $s['done'] ) ) { $renewal->update_meta_data( '_digitalisimo_retry', time() ); $renewal->save(); self::enqueue( self::HOOK_RENEW, $renewal->get_id() ); $queued = true; }
		}
		if ( 'suspended' !== self::svc( $id, 'status' ) && self::svc( $id, 'error' ) && ! $queued && self::svc( $id, 'suspend_reason' ) ) { self::enqueue( self::HOOK_SUSPEND, $id ); $queued = true; }
		return $queued;
	}
}
