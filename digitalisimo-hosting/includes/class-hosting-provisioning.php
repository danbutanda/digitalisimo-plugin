<?php
defined( 'ABSPATH' ) || exit;

/**
 * Venta automática de hosting y dominios con WooCommerce (pedido del propietario del 2026-11-23).
 *
 * Al pagarse un pedido (pasa a «procesando» o «completado») se encola en Action Scheduler y, en segundo plano:
 *  1. crea la cuenta en Webuzo con el paquete del producto (`_digitalisimo_webuzo_plan`), el dominio del pedido, un
 *     usuario derivado del dominio y una contraseña fuerte;
 *  2. registra el dominio en el registrador de su extensión (Namecheap o Dynadot) a nombre del cliente (datos de facturación), tras comprobar que el saldo alcanza,
 *     y lo apunta a los nameservers propios;
 *  3. envía al cliente el correo «Accesos de hosting» (WooCommerce → Ajustes → Correos) y deja una nota privada en el
 *     pedido, sin la contraseña.
 * Cada paso guarda su resultado en el pedido: reintentar no crea otra cuenta ni registra otra vez (antes de crear se
 * consulta si la cuenta o el dominio ya existen). Sólo se reintenta lo que no llegó (red, tiempo agotado); una clave
 * rechazada por Webuzo detiene TODO sin reintentar (ver Digitalisimo_Hosting_Webuzo). Saldo insuficiente, datos que
 * faltan o un error de la API dejan el pedido «en espera» con su nota y un correo al administrador.
 *
 * Cada servicio (dominio + plan) queda como una entrada privada `digi_hosting_service` con su vencimiento. A diario:
 * aviso al cliente antes de vencer (30 y 7 días; en un plan mensual 7 y 3, en uno trimestral 15 y 7) con un pedido de
 * renovación que paga con el enlace de pago de WooCommerce; suspensión en Webuzo a los N días de gracia sin pago (como
 * mucho 5 en el mensual y 15 en el trimestral); al pagarse, reactivación y otro periodo del plan (un mes, tres, seis, un
 * año, dos o tres). El dominio es siempre anual: si vence en otra fecha que el hosting, tiene su propio pedido de
 * renovación (renovación en el registrador con la misma validación de saldo y un año más).
 */
class Digitalisimo_Hosting_Provisioning {
	const GROUP          = 'digitalisimo-hosting';
	const HOOK_PROVISION = 'digitalisimo_hosting_provision';
	const HOOK_RENEW     = 'digitalisimo_hosting_renew';
	const HOOK_SUSPEND   = 'digitalisimo_hosting_suspend';
	const HOOK_UNSUSPEND = 'digitalisimo_hosting_unsuspend';
	const HOOK_RESEND    = 'digitalisimo_hosting_resend';
	const HOOK_VERIFY    = 'digitalisimo_hosting_verify';
	const HOOK_RECONCILE = 'digitalisimo_hosting_reconcile';
	const HOOK_DAILY     = 'digitalisimo_hosting_daily';
	const CPT            = 'digi_hosting_service';
	const META_STATE     = '_digitalisimo_provision';
	const META_SERVICE   = '_digitalisimo_service';
	const META_RENEWAL   = '_digitalisimo_renewal_of';
	const META_RENEW     = '_digitalisimo_renew';
	/** Qué renueva un pedido de renovación: array( hosting => bool, domain => bool, period => meses del hosting ). */
	const META_PARTS     = '_digitalisimo_renew_parts';
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
		add_action( 'init', array( __CLASS__, 'migrate_domain_expires' ), 30 );
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
		add_action( self::HOOK_RECONCILE, array( __CLASS__, 'run_reconcile' ) );
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
		// Sin interfaz ni REST pública; sus capacidades son todas manage_woocommerce (tampoco por XML-RPC).
		$caps = array();
		foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts', 'read' ) as $cap ) $caps[ $cap ] = 'manage_woocommerce';
		register_post_type( self::CPT, array( 'label' => 'Servicios de hosting', 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true, 'show_ui' => false, 'show_in_menu' => false, 'show_in_rest' => false, 'show_in_nav_menus' => false, 'rewrite' => false, 'query_var' => false, 'supports' => array( 'title' ), 'can_export' => true, 'capability_type' => 'digi_hosting_service', 'capabilities' => $caps, 'map_meta_cap' => false ) );
	}

	/** Sólo usa la API de pedidos de WooCommerce: compatible con el almacenamiento de pedidos de alto rendimiento. */
	public static function declare_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && defined( 'DIGITALISIMO_HOSTING_FILE' ) ) \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DIGITALISIMO_HOSTING_FILE, true );
	}

	private static function on() { $o = Digitalisimo_Hosting::settings(); return ! empty( $o['enabled'] ) && ! empty( $o['provision_enabled'] ); }

	/** ¿Están encendidos el módulo y la venta automática? (La pantalla de servicios lo consulta antes de cada botón.) */
	public static function sale_on() { return self::on(); }

	/**
	 * Lee otra vez de la base el servicio y los ajustes: otra petición pudo dar de baja el servicio o apagar la venta
	 * mientras esta tarea corría. Se descarta la copia de esta petición SIEMPRE: también con caché persistente (Redis,
	 * Memcached, LSMCD), porque los drop-ins guardan una copia en memoria por petición que no ve lo que escribió otra.
	 * Sólo estas claves (el servicio y las opciones), nunca toda la caché.
	 */
	private static function refresh( $service ) {
		if ( ! function_exists( 'wp_cache_delete' ) ) return;
		if ( $service ) wp_cache_delete( (int) $service, 'post_meta' );
		foreach ( array( 'alloptions', 'notoptions', Digitalisimo_Hosting::OPTION ) as $key ) wp_cache_delete( $key, 'options' );
	}

	/**
	 * Justo antes de cada llamada que cobra o crea (registrar o renovar un dominio, crear una cuenta): ¿sigue encendida
	 * la venta y vivo el servicio? Si no, no se llama.
	 */
	private static function may_spend( $service, $order = null ) {
		self::refresh( $service );
		if ( $service && 'cancelled' === self::svc( $service, 'status' ) ) return self::stop( 'cancelled', 'El servicio se dio de baja mientras corría la tarea' );
		if ( ! self::on() ) return self::stop( 'off', 'La venta automática se apagó mientras corría la tarea' );
		// El pedido, leído de la base: otra petición pudo reembolsarlo o cancelarlo.
		if ( $order && in_array( self::db_order_status( $order ), self::DEAD, true ) ) return self::stop( 'dead', 'El pedido se reembolsó o canceló mientras corría la tarea' );
		return null;
	}

	/** Estados de un pedido en los que ya no se gasta nada. */
	const DEAD = array( 'refunded', 'cancelled', 'failed', 'trash' );

	/**
	 * El estado del pedido directo de la base (sin la copia de la petición ni la caché de pedidos): la tabla de
	 * pedidos de WooCommerce (HPOS) o la de entradas. Sin $wpdb (pruebas), el del objeto.
	 */
	private static function db_order_status( $order ) {
		global $wpdb;
		$id = (int) $order->get_id();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) ) {
			$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			$v = $hpos ? $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}wc_orders WHERE id = %d", $id ) ) : $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			if ( is_string( $v ) && '' !== $v ) return 0 === strpos( $v, 'wc-' ) ? substr( $v, 3 ) : $v;
		}
		return (string) $order->get_status();
	}

	/**
	 * Escribe el estado del servicio sólo si en la base sigue siendo $expected (otra petición pudo darlo de baja entre
	 * la lectura y la escritura): un UPDATE condicional con $wpdb; sin él, una relectura inmediata.
	 */
	private static function set_status_if( $id, $expected, $new ) {
		global $wpdb;
		$id = (int) $id;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
			if ( (string) $new === (string) $expected ) $ok = (string) $expected === (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1", $id, '_dhs_status' ) );
			else $ok = (bool) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $new, $id, '_dhs_status', $expected ) );
			if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( $id, 'post_meta' );
			return $ok;
		}
		self::refresh( $id );
		if ( (string) self::svc( $id, 'status' ) !== (string) $expected ) return false;
		update_post_meta( $id, '_dhs_status', $new );
		return true;
	}

	/**
	 * La tarea se encontró a medio camino el servicio dado de baja o la venta apagada: no escribe el estado del servicio
	 * y, si ya se gastó algo (dominio registrado o renovado, cuenta creada), lo dice y avisa para reembolsar o cancelar.
	 * Con la venta apagada, lo que falta se retoma al encenderla.
	 */
	private static function interrupted( $order, array $state, $kind, $service, $is_renew ) {
		$domain = $service ? (string) self::svc( $service, 'domain' ) : '';
		$spent = array();
		if ( $is_renew ) {
			if ( ! empty( $state['domain'] ) ) $spent[] = 'el dominio ' . $domain . ' ya se renovó en ' . Digitalisimo_Hosting_Registrars::label( $service ? Digitalisimo_Hosting_Registrars::of_service( $service ) : '' );
		} else {
			if ( ! empty( $state['domain']['registered'] ) && empty( $state['domain']['existed'] ) ) $spent[] = 'el dominio ' . $domain . ' quedó registrado en ' . Digitalisimo_Hosting_Registrars::label( (string) ( $state['domain']['registrar'] ?? '' ) );
			if ( 'done' === ( $state['hosting']['status'] ?? '' ) ) $spent[] = 'la cuenta «' . ( $state['hosting']['user'] ?? '' ) . '» quedó creada en Webuzo';
		}
		$cancelled = 'cancelled' === $kind || 'dead' === $kind; // la baja del servicio o el pedido reembolsado/cancelado
		$why = 'cancelled' === $kind ? 'El servicio se dio de baja' : ( 'dead' === $kind ? 'El pedido pasó a «' . self::db_order_status( $order ) . '»' : 'La venta automática se apagó' );
		$text = $why . ' mientras corría la tarea: no se hizo nada más'
			. ( $spent ? ', pero ' . implode( ' y ', $spent ) . '.' : '.' )
			. ( $cancelled ? ( $spent ? ' Reembolsa el pedido o cancela a mano lo creado.' : ( $is_renew && 'cancelled' === $kind ? ' Reembolsa la renovación pagada.' : '' ) ) : ' Al encender la venta se retoma lo que falta.' );
		unset( $state['attempts'] );
		$order->update_meta_data( $is_renew ? self::META_RENEW : self::META_STATE, $state ); $order->save();
		if ( ! $cancelled && $service && ! $is_renew ) self::svc_set( $service, array( 'resume_provision' => time() ) );
		if ( $service ) self::svc_log( $service, $text );
		// Aviso al administrador cuando hay algo que reembolsar o cancelar: lo gastado, o una renovación pagada de un
		// servicio que se dio de baja a media tarea.
		if ( self::note_once( $order, 'interrupted_' . $kind . ( $spent ? '_spent' : '' ), $text ) && $cancelled && ( $spent || ( $is_renew && 'cancelled' === $kind ) ) ) self::alert_admin( ( $spent ? ( 'dead' === $kind ? 'pedido reembolsado con gasto: ' : 'dado de baja con gasto: ' ) : 'renovación pagada de un servicio dado de baja: ' ) . $domain, $text . "\n\nPedido: " . self::order_link( $order ) );
	}

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
		if ( ! self::on() ) {
			// Venta apagada: nada se aprovisiona; el pedido lo dice (al encenderla, «Reintentar» en Servicios de hosting).
			$o = Digitalisimo_Hosting::settings();
			if ( empty( $o['enabled'] ) || ! function_exists( 'wc_get_order' ) ) return;
			$order = wc_get_order( $order_id );
			$rid = $order ? (int) $order->get_meta( self::META_RENEWAL ) : 0;
			// La renovación de un servicio desplazado no se retomará al encender: se dice así y se avisa para reembolsar.
			if ( $rid && self::is_service( $rid ) && ! self::owns_account( $rid ) && self::payment_confirmed( $order ) ) { self::superseded_paid( $order, $rid ); return; }
			if ( $order && ( $order->get_meta( self::META_RENEWAL ) || self::items( $order )['hosting'] || self::items( $order )['domains'] || self::items( $order )['transfers'] ) ) self::note_once( $order, 'sale_off', 'la venta automática está apagada: este pedido no se aprovisiona ni se renueva solo. Al encenderla, las renovaciones cobradas se retoman y las altas quedan para revisar a mano.' );
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$renewal = (int) $order->get_meta( self::META_RENEWAL );
		$offline = in_array( (string) $order->get_payment_method(), self::offline_methods(), true );
		if ( $renewal || self::items( $order )['hosting'] || self::items( $order )['domains'] || self::items( $order )['transfers'] ) {
			if ( ! self::payment_confirmed( $order ) ) {
				self::note_once( $order, 'unpaid', $offline ? 'pago fuera de línea (' . ( $order->get_payment_method() ?: 'manual' ) . '): no se aprovisiona hasta que marques el pedido «completado» al confirmar el cobro.' : 'la pasarela no dejó el pago confirmado (sin fecha de pago o sin número de transacción); no se aprovisiona hasta que marques el pedido «completado» al confirmar el cobro.' );
				return;
			}
		}
		if ( $renewal && self::is_service( $renewal ) && ! self::owns_account( $renewal ) ) { self::superseded_paid( $order, $renewal ); return; }
		if ( $renewal && 'cancelled' === self::svc( $renewal, 'status' ) ) {
			// El servicio se dio de baja: la renovación pagada no renueva nada; hay que reembolsar.
			if ( self::note_once( $order, 'cancelled', 'el servicio ' . self::svc( $renewal, 'domain' ) . ' está dado de baja: esta renovación pagada NO se aplica. Reembólsala.' ) ) self::alert_admin( 'renovación pagada de un servicio dado de baja', 'Se pagó la renovación #' . $order->get_id() . ' de ' . self::svc( $renewal, 'domain' ) . ', que está dado de baja: no se renovó nada. Reembólsala al cliente.' . "\n\n" . self::order_link( $order ) );
			return;
		}
		if ( $renewal ) {
			$state = (array) $order->get_meta( self::META_RENEW );
			if ( empty( $state['done'] ) ) {
				if ( empty( $state['paid'] ) ) { $state['paid'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save(); }
				// Un pago manual cuyo dominio quedó detenido (saldo, datos) espera «Reintentar»: completar su pedido no lo
				// renueva solo (segunda revisión, R1).
				if ( ! empty( $state['manual'] ) && ! empty( $state['stopped'] ) ) return;
				// Pagada la renovación, ninguna suspensión pendiente (reintento o en espera de clave) puede ejecutarse.
				self::cancel_suspension( $renewal, 'renovación #' . $order->get_id() . ' pagada' );
				self::enqueue( self::HOOK_RENEW, $order->get_id() );
			}
			return;
		}
		$items = self::items( $order );
		if ( ! $items['hosting'] && ! $items['domains'] && ! $items['transfers'] ) return;
		$state = self::state( $order );
		if ( ! empty( $state['done'] ) ) return;
		$created = $order->get_date_created();
		if ( empty( $state['paid'] ) && $created && $created->getTimestamp() < self::since() ) {
			// Anterior al encendido: no se aprovisiona solo, pero queda a la vista con su «Reintentar» y se avisa.
			$domain = $items['domains'] ? $items['domains'][0]['domain'] : ( $items['transfers'] ? $items['transfers'][0]['domain'] : ( $items['hosting'] ? $items['hosting'][0]['domain'] : '' ) );
			$service = self::service_for( $order, $items, $domain );
			if ( $service ) self::svc_set( $service, array( 'status' => 'on-hold', 'error' => 'Pedido anterior al encendido de la venta automática: no se aprovisionó solo. Pulsa «Reintentar» para hacerlo.' ) );
			if ( self::note_once( $order, 'before', 'el pedido es anterior al encendido de la venta automática; no se aprovisiona solo. Está en WooCommerce → Servicios de hosting con «Reintentar».' ) ) self::alert_admin( 'pedido #' . $order->get_id() . ' anterior al encendido', 'El pedido #' . $order->get_id() . ' (' . $domain . ') se pagó después de encender la venta automática pero es anterior a ese momento: no se aprovisionó solo. Si corresponde, pulsa «Reintentar» en WooCommerce → Servicios de hosting.' . "\n\n" . self::order_link( $order ) );
			return;
		}
		// La marca de pago: sin ella ninguna tarea llama a Webuzo ni a Namecheap por este pedido.
		if ( empty( $state['paid'] ) ) { $state['paid'] = time(); self::save_state( $order, $state ); }
		// Detenido (saldo, datos…) y el administrador lo vuelve a marcar pagado: cuenta como «Reintentar» (una tarea sin
		// esa marca ya no repite un alta detenida; segunda revisión, N2).
		if ( ! empty( $state['stopped'] ) ) { $order->update_meta_data( '_digitalisimo_retry', time() ); $order->save(); }
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
	private static function save_state( $order, array $state ) { $order->update_meta_data( self::$state_meta, $state ); $order->save(); }
	/** Dónde guarda save_state(): el estado del pedido o, mientras se atiende un dominio más del pedido, el suyo. */
	private static $state_meta = self::META_STATE;

	/**
	 * Los planes de hosting y los dominios del pedido, según lo marcado en cada producto o variación (la variación manda):
	 * cada fila con su ID vendido (`id`), el paquete de Webuzo y el periodo de cobro del plan.
	 */
	public static function items( $order ) {
		$out = array( 'hosting' => array(), 'domains' => array(), 'transfers' => array() );
		$Pr = 'Digitalisimo_Hosting_Products';
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) continue;
			$pid    = (int) $item->get_product_id();
			$vid    = (int) $item->get_variation_id();
			$sid    = $vid ? $vid : $pid;
			$domain = strtolower( trim( (string) $item->get_meta( 'Dominio' ) ) );
			$row    = array( 'item' => $item, 'product' => $pid, 'variation' => $vid, 'id' => $sid, 'domain' => $domain, 'qty' => (int) $item->get_quantity() );
			$kind   = class_exists( $Pr ) ? $Pr::kind( $sid ) : '';
			if ( 'register' === $kind ) { $out['domains'][] = $row; continue; }
			// Transferencia (2026-11-26): el dominio y su EPP los pidió el carrito; la transferencia se hace a mano.
			if ( 'transfer' === $kind ) { $out['transfers'][] = $row; continue; }
			if ( 'hosting' === $kind ) $out['hosting'][] = $row + array( 'plan' => $Pr::plan( $sid ), 'period' => $Pr::period( $sid ), 'for' => strtolower( trim( (string) $item->get_meta( '_digitalisimo_for_domain' ) ) ) );
		}
		return $out;
	}

	/**
	 * Un pedido con varios dominios (el buscador deja agregar dos dominios y un plan): array( principal, demás, nota ). El
	 * principal es el plan con el dominio con el que se agregó (el buscador lo guarda en el artículo) o, sin esa
	 * asociación, con el primer dominio del pedido (queda anotado). Los demás dominios se registran o transfieren cada uno
	 * con su propio servicio (provision_extras()).
	 */
	public static function split_main( array $items ) {
		$doms = array();
		foreach ( $items['domains'] as $r ) $doms[] = $r + array( 'kind' => 'register' );
		foreach ( $items['transfers'] as $r ) $doms[] = $r + array( 'kind' => 'transfer' );
		if ( count( $doms ) < 2 ) return array( $items, array(), '' );
		$pick = 0; $note = '';
		$for = $items['hosting'] ? (string) ( $items['hosting'][0]['for'] ?? '' ) : '';
		$found = false;
		if ( '' !== $for ) foreach ( $doms as $i => $r ) if ( $r['domain'] === $for ) { $pick = $i; $found = true; break; }
		if ( $items['hosting'] && ! $found ) $note = 'el plan no traía con qué dominio se agregó: se usa el primero del pedido, ' . $doms[0]['domain'] . '.';
		$main = $items; $main['domains'] = array(); $main['transfers'] = array();
		$main[ 'register' === $doms[ $pick ]['kind'] ? 'domains' : 'transfers' ][] = $doms[ $pick ];
		unset( $doms[ $pick ] );
		return array( $main, array_values( $doms ), $note );
	}

	/** Dónde guarda su estado (en el pedido) un dominio más de un pedido con varios. */
	private static function extra_key( array $row ) { return '_digitalisimo_provision_x_' . substr( md5( $row['kind'] . ':' . (int) $row['id'] . ':' . $row['domain'] ), 0, 12 ); }

	/** ¿Es el servicio de un dominio más de un pedido con varios (no el del plan)? */
	public static function is_extra( $id ) { return (bool) self::svc( $id, 'extra' ); }

	/**
	 * Los demás dominios del pedido con su estado: dominio => array( row, state, service ). Con $pending, sólo los que no
	 * terminaron (ni se dieron de baja).
	 */
	public static function extras_state( $order, $pending = false ) {
		$out = array();
		foreach ( self::split_main( self::items( $order ) )[1] as $row ) {
			$st = $order->get_meta( self::extra_key( $row ) ); $st = is_array( $st ) ? $st : array();
			$sid = (int) ( $st['service'] ?? 0 );
			if ( $pending && ( ! empty( $st['done'] ) || ( $sid && 'cancelled' === self::svc( $sid, 'status' ) ) ) ) continue;
			$out[ (string) $row['domain'] ] = array( 'row' => $row, 'state' => $st, 'service' => $sid );
		}
		return $out;
	}

	/**
	 * Los demás dominios de un pedido con varios: cada uno con su registrador, su propio servicio de dominio, la misma
	 * revisión de dueño y las mismas guardas e idempotencia del registro (step_domain()). Uno que falla queda en espera
	 * sin detener a los demás ni al plan, con la misma salida que el dominio del plan (segunda revisión, N2):
	 *  - «Reintentar» y «Aprobar dominio» de su servicio lo retoman ($retry_mark: la marca de «Reintentar» del pedido
	 *    limpia sus intentos y su parada);
	 *  - una revisión manual marca `needs_review` (sale «Aprobar dominio») y un dominio de otro cliente `owner_conflict`;
	 *  - la red se reintenta con la espera creciente; agotados los intentos, o por otra causa, queda detenido (`stopped`) con
	 *    aviso al dueño, hasta «Reintentar»;
	 *  - nada se encola aquí: devuelve array( retry => segundos hasta el siguiente intento o 0, alerts => avisos por mandar )
	 *    y quien llama encola UNA tarea y manda UN aviso (antes eran dos tareas y un aviso por cada una).
	 */
	private static function provision_extras( $order, array $extras, $retry_mark = false ) {
		$retry = 0; $alerts = array();
		foreach ( $extras as $row ) {
			$mk = self::extra_key( $row );
			$st = $order->get_meta( $mk ); $st = is_array( $st ) ? $st : array();
			if ( ! empty( $st['done'] ) ) continue;
			if ( $retry_mark ) unset( $st['attempts'], $st['stopped'], $st['watchdog'] );
			$domain = (string) $row['domain'];
			$sid = (int) ( $st['service'] ?? 0 );
			if ( ! $sid || ! self::is_service( $sid ) ) {
				$sid = (int) wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => '' !== $domain ? $domain : 'Pedido #' . $order->get_id() ) );
				if ( ! $sid ) continue;
				self::svc_set( $sid, array( 'order' => $order->get_id(), 'customer' => (int) $order->get_customer_id(), 'email' => (string) $order->get_billing_email(), 'name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ), 'domain' => $domain, 'plan' => '', 'hosting_product' => 0, 'period' => 12, 'domain_product' => 'register' === $row['kind'] ? (int) $row['id'] : 0, 'status' => 'pending', 'expires' => 0, 'created' => time(), 'extra' => 1, 'extra_of' => (int) $order->get_meta( self::META_SERVICE ) ) );
				$st['service'] = $sid;
				$order->update_meta_data( $mk, $st ); $order->save();
			}
			if ( 'cancelled' === self::svc( $sid, 'status' ) ) continue;
			// Detenido (saldo, datos, revisión, dueño, reintentos agotados): espera «Reintentar» o «Aprobar dominio».
			if ( ! empty( $st['stopped'] ) ) { $order->update_meta_data( $mk, $st ); $order->save(); continue; }
			$one = array( 'hosting' => array(), 'domains' => 'register' === $row['kind'] ? array( $row ) : array(), 'transfers' => 'transfer' === $row['kind'] ? array( $row ) : array() );
			$stop = null;
			$split = self::split( $domain );
			if ( $row['qty'] > 1 ) $stop = self::stop( 'data', 'El pedido tiene una cantidad mayor que 1 de «' . $row['item']->get_name() . '»; se registra un año por dominio.' );
			elseif ( '' === $domain ) $stop = self::stop( 'data', 'Una línea de dominio del pedido no trae el nombre («' . $row['item']->get_name() . '»).' );
			elseif ( ! self::valid_domain( $domain ) || ! $split ) $stop = self::stop( 'data', 'El dominio «' . $domain . '» no es válido.' );
			elseif ( 'register' === $row['kind'] && Digitalisimo_Hosting_Products::register_ext( $row['id'] ) !== $split[2] ) $stop = self::stop( 'data', 'El dominio «' . $domain . '» no corresponde al producto de dominio pagado; no se registró.' );
			if ( ! $stop ) $stop = self::check_owner( $order, $st, $one, $domain, $sid );
			if ( ! $stop ) {
				self::$state_meta = $mk;
				try { $stop = self::step_domain( $order, $st, $one, $domain, $sid ); } finally { self::$state_meta = self::META_STATE; }
			}
			if ( $stop && in_array( $stop['kind'], array( 'cancelled', 'off', 'dead' ), true ) ) {
				// Baja, venta apagada o pedido reembolsado a media tarea: no cuenta como intento; con la venta apagada se retoma
				// al encenderla (como el dominio del plan).
				self::svc_set( $sid, array( 'error' => (string) $stop['message'] ) + ( 'off' === $stop['kind'] ? array( 'resume_provision' => time() ) : array() ) );
			} elseif ( $stop ) {
				$message = (string) $stop['message'];
				$wait = 0;
				if ( 'transient' === $stop['kind'] ) {
					$st['attempts'] = (int) ( $st['attempts'] ?? 0 ) + 1;
					if ( $st['attempts'] < self::MAX_ATTEMPTS ) $wait = self::BACKOFF[ min( $st['attempts'] - 1, count( self::BACKOFF ) - 1 ) ];
					else $message .= '. Se agotaron los reintentos automáticos';
				}
				$st['error'] = $message;
				$flags = array( 'status' => 'on-hold', 'error' => $message );
				if ( 'owner' === $stop['kind'] ) $flags['owner_conflict'] = 1;
				if ( 'review' === $stop['kind'] ) $flags['needs_review'] = 1;
				self::svc_set( $sid, $flags );
				if ( $wait ) {
					$retry = $retry ? min( $retry, $wait ) : $wait;
					self::note_once( $order, 'extra_' . md5( $mk . '|' . $message ), 'el dominio ' . $domain . ' quedó en espera (los demás siguen): ' . $message . '. Reintento automático ' . $st['attempts'] . ' de ' . ( self::MAX_ATTEMPTS - 1 ) . '.' );
				} else {
					$st['stopped'] = 'transient' === $stop['kind'] ? 'transient' : $stop['kind'];
					unset( $st['attempts'] );
					self::svc_log( $sid, $message );
					$action = 'review' === $stop['kind'] ? 'Si el dominio es del cliente, pulsa «Aprobar dominio»' : 'Pulsa «Reintentar» en su servicio cuando lo resuelvas';
					if ( self::note_once( $order, 'extra_' . md5( $mk . '|' . $message ), 'el dominio ' . $domain . ' quedó en espera (los demás siguen): ' . $message . '. ' . $action . ' en WooCommerce → Servicios de hosting.' ) ) $alerts[] = $domain . ': ' . $message . '. ' . $action . '.';
				}
			} else {
				$st['done'] = time(); unset( $st['error'], $st['attempts'], $st['stopped'] );
				$now = time();
				self::svc_set( $sid, array( 'status' => 'active', 'error' => '', 'needs_review' => 0, 'started' => $now, 'expires' => (int) strtotime( '+1 year', $now ), 'domain_expires' => (int) strtotime( '+1 year', $now ) ) );
				self::svc_log( $sid, 'Aprovisionado con el pedido #' . $order->get_id() . ' (uno de varios dominios del pedido).' );
			}
			$order->update_meta_data( $mk, $st ); $order->save();
		}
		return array( 'retry' => $retry, 'alerts' => $alerts );
	}

	/** La pasada por los demás dominios con el plan ya entregado (o detenido): una tarea de reintento, un aviso y el cierre. */
	private static function run_extras( $order, array $extras, $mark ) {
		if ( ! $extras ) return;
		$x = self::provision_extras( $order, $extras, $mark );
		if ( $x['retry'] ) self::enqueue( self::HOOK_PROVISION, $order->get_id(), $x['retry'], true );
		self::extras_alert( $order, $x['alerts'] );
		self::maybe_complete( $order );
	}

	/** Un solo aviso al dueño por los dominios más del pedido que quedaron detenidos en esta pasada. */
	private static function extras_alert( $order, array $alerts ) {
		if ( ! $alerts ) return;
		self::alert_admin( ( 1 === count( $alerts ) ? 'dominio en espera: ' . strtok( $alerts[0], ':' ) : count( $alerts ) . ' dominios en espera (pedido #' . $order->get_id() . ')' ), 'El pedido #' . $order->get_id() . ' trae varios dominios; quedaron en espera:' . "\n\n- " . implode( "\n- ", $alerts ) . "\n\nLos demás siguieron. El pedido no se completa hasta que terminen.\n\n" . self::order_link( $order ) . "\nServicios: " . admin_url( 'admin.php?page=digitalisimo-hosting-services' ) );
	}

	/**
	 * Con el plan (o el primer dominio) entregado: el pedido pasa a «completado» sólo cuando no queda ningún dominio más
	 * pendiente. Si alguno quedó detenido, el pedido queda «en espera» con la lista (N2).
	 */
	private static function maybe_complete( $order, $note = '' ) {
		$state = self::state( $order );
		if ( empty( $state['done'] ) ) return;
		$status = self::db_order_status( $order );
		if ( ! in_array( $status, array( 'processing', 'on-hold' ), true ) ) return;
		$pending = self::extras_state( $order, true );
		if ( ! $pending ) {
			$sid = (int) $order->get_meta( self::META_SERVICE );
			$exp = $sid ? (int) self::svc( $sid, 'expires' ) : 0;
			$order->update_status( 'completed', 'DIGITALÍSIMO Hosting: ' . ( '' !== $note ? $note : 'servicio entregado' . ( $exp ? '; vence el ' . self::date( $exp ) : '' ) ) . '.' );
			return;
		}
		$stopped = array_filter( $pending, function ( $x ) { return ! empty( $x['state']['stopped'] ); } );
		$list = implode( ', ', array_keys( $pending ) );
		if ( $stopped && 'on-hold' !== $status ) $order->update_status( 'on-hold', 'DIGITALÍSIMO Hosting: ' . ( '' !== $note ? $note . '; ' : '' ) . 'el pedido no se completa hasta que terminen los demás dominios (' . $list . '). Revísalos en WooCommerce → Servicios de hosting.' );
		else self::note_once( $order, 'extras_wait_' . md5( $list ), ( '' !== $note ? $note . '; ' : '' ) . 'el pedido se completa cuando terminen los demás dominios (' . $list . ').' );
	}

	// ── Periodos de cobro (pedido del propietario del 2026-11-27: mensual, trimestral, semestral, anual, bianual, trianual) ──

	/**
	 * $ts más $months meses. Un múltiplo de 12 son años exactos (como siempre); los demás conservan el día del mes y, si
	 * el mes siguiente es más corto, se quedan en su último día (31 de enero + 1 mes = 28 o 29 de febrero, no 3 de marzo).
	 */
	public static function add_months( $ts, $months ) {
		$ts = (int) $ts; $months = max( 1, (int) $months );
		if ( 0 === $months % 12 ) return (int) strtotime( '+' . ( $months / 12 ) . ' year', $ts );
		$y = (int) gmdate( 'Y', $ts ); $m = (int) gmdate( 'n', $ts ); $d = (int) gmdate( 'j', $ts );
		$total = $m - 1 + $months;
		$ny = $y + intdiv( $total, 12 ); $nm = $total % 12 + 1;
		$last = (int) gmdate( 't', gmmktime( 0, 0, 0, $nm, 1, $ny ) );
		return gmmktime( (int) gmdate( 'G', $ts ), (int) gmdate( 'i', $ts ), (int) gmdate( 's', $ts ), $nm, min( $d, $last ), $ny );
	}

	/** Días de los dos avisos antes de vencer, según el periodo: mensual 7 y 3; trimestral 15 y 7; los demás 30 y 7. */
	public static function notice_days( $months ) {
		$months = (int) $months;
		if ( 1 === $months ) return array( 7, 3 );
		if ( 3 === $months ) return array( 15, 7 );
		return array( 30, 7 );
	}

	/** Días de gracia del periodo: los del ajuste, como mucho 5 en el mensual y 15 en el trimestral. */
	public static function grace_for( $months, $grace ) {
		$grace = max( 0, (int) $grace );
		if ( 1 === (int) $months ) return min( $grace, 5 );
		if ( 3 === (int) $months ) return min( $grace, 15 );
		return $grace;
	}

	/** Periodo de cobro del hosting del servicio (meses; 12 en los servicios de antes). */
	public static function service_period( $id ) { $m = (int) self::svc( $id, 'period' ); return isset( Digitalisimo_Hosting_Products::PERIODS[ $m ] ) ? $m : 12; }

	/** ¿El servicio tiene cuenta de hosting (o plan)? */
	public static function has_hosting( $id ) { return (int) self::svc( $id, 'hosting_product' ) > 0 || '' !== (string) self::svc( $id, 'plan' ) || '' !== (string) self::svc( $id, 'webuzo_user' ); }

	/** Vencimiento del dominio del servicio (anual; en los servicios de antes, el mismo del hosting). 0 sin dominio. */
	public static function domain_due( $id ) {
		if ( ! (int) self::svc( $id, 'domain_product' ) ) return 0;
		$d = (int) self::svc( $id, 'domain_expires' );
		return $d ? $d : (int) self::svc( $id, 'expires' );
	}

	/**
	 * Un servicio de antes de esta versión no tiene `domain_expires`: su dominio vencía con el hosting y domain_due() lo
	 * lee de `expires`. Antes de mover `expires` (y en la migración) se fija ese vencimiento en `domain_expires`, para que
	 * domain_due() nunca lea un `expires` ya movido y sume otro año (segunda revisión, N1). true si lo escribió.
	 */
	public static function fill_domain_expires( $id ) {
		if ( ! (int) self::svc( $id, 'domain_product' ) || (int) self::svc( $id, 'domain_expires' ) ) return false;
		$exp = (int) self::svc( $id, 'expires' );
		if ( ! $exp ) return false;
		self::svc_set( $id, array( 'domain_expires' => $exp ) );
		return true;
	}

	/** Marca de la migración que rellena `domain_expires` en los servicios con dominio de antes de esta versión. */
	const DOMAIN_EXPIRES_MIGRATED = 'digitalisimo_hosting_domain_expires_migrated';

	/**
	 * Migración idempotente (una vez por sitio, en `init`): cada servicio con dominio y sin `domain_expires` recibe el
	 * vencimiento con el que se cobró hasta hoy (`expires`). Corrida otra vez no cambia nada; la revisión diaria rellena
	 * también uno que haya quedado sin la marca. Devuelve cuántos rellenó.
	 */
	public static function migrate_domain_expires() {
		if ( get_option( self::DOMAIN_EXPIRES_MIGRATED ) ) return 0;
		$n = 0;
		foreach ( self::services() as $id ) if ( self::fill_domain_expires( $id ) ) $n++;
		update_option( self::DOMAIN_EXPIRES_MIGRATED, time(), false );
		return $n;
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
			'plan' => $hosting ? $hosting['plan'] : '', 'hosting_product' => $hosting ? $hosting['id'] : 0, 'period' => $hosting ? (int) $hosting['period'] : 12,
			'domain_product' => $dom ? $dom['id'] : 0, 'status' => 'pending', 'expires' => 0, 'created' => time(),
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
		if ( ! self::may_run( $order, $state ) ) return;
		// «Reintentar» (de cualquier servicio del pedido) deja esta marca: también limpia los intentos de los demás dominios.
		$mark = (bool) $order->get_meta( '_digitalisimo_retry' );
		// El plan y su dominio ya se entregaron: sólo quedan (si los hay) los demás dominios del pedido.
		if ( ! empty( $state['done'] ) ) {
			if ( $mark ) { $order->delete_meta_data( '_digitalisimo_retry' ); $order->save(); }
			self::run_extras( $order, self::split_main( self::items( $order ) )[1], $mark );
			return;
		}
		$sid = (int) $order->get_meta( self::META_SERVICE );
		if ( $sid && 'cancelled' === self::svc( $sid, 'status' ) ) { self::note_once( $order, 'cancelled', 'el servicio está dado de baja: no se aprovisiona.' ); return; }
		$state = self::take_retry( $order, $state, self::META_STATE );
		// El plan detenido por algo que no es la red (saldo, datos, revisión, dueño) espera «Reintentar»: una tarea que
		// corre sólo por los demás dominios no lo vuelve a intentar (ni repite su aviso).
		if ( ! $mark && ! empty( $state['stopped'] ) ) { self::run_extras( $order, self::split_main( self::items( $order ) )[1], false ); return; }
		try {
			list( $items, $extras, $assoc ) = self::split_main( self::items( $order ) );
			if ( '' !== $assoc ) self::note_once( $order, 'assoc', $assoc );
			$domain = '';
			if ( $items['domains'] ) $domain = $items['domains'][0]['domain'];
			elseif ( $items['transfers'] ) $domain = $items['transfers'][0]['domain'];
			elseif ( $items['hosting'] ) $domain = $items['hosting'][0]['domain'];
			$service = self::service_for( $order, $items, $domain );
			$stop = self::check_order( $order, $items, $domain );
			// Primero el dominio y su dueño; la cuenta de Webuzo sólo después (nunca una cuenta para un dominio ajeno).
			if ( ! $stop ) $stop = self::check_owner( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_domain( $order, $state, $items, $domain, $service );
			if ( ! $stop ) $stop = self::step_hosting( $order, $state, $items, $domain, $service );
			// Los demás dominios del pedido, cada uno por su lado (uno que falla no detiene a los otros ni al plan), antes del
			// correo de accesos: el correo dice cómo quedó cada uno.
			$x = 'owner' !== ( $stop['kind'] ?? '' ) ? self::provision_extras( $order, $extras, $mark ) : array( 'retry' => 0, 'alerts' => array() );
			if ( ! $stop ) $stop = self::step_email( $order, $state, $items, $domain, $service );
			if ( $stop ) {
				// UNA tarea de reintento: la del plan si la red falló y le quedan intentos; si no, la de los demás dominios.
				$main_retry = 'transient' === $stop['kind'] && (int) ( $state['attempts'] ?? 0 ) + 1 < self::MAX_ATTEMPTS;
				if ( $x['retry'] && ! $main_retry ) self::enqueue( self::HOOK_PROVISION, $order->get_id(), $x['retry'], true );
				// UN aviso: el del pedido en espera lleva también los dominios detenidos (si el plan no avisa, van aparte).
				$alerting = ! $main_retry && ! in_array( $stop['kind'], array( 'blocked', 'auth', 'cancelled', 'off', 'dead' ), true );
				if ( $alerting && $x['alerts'] ) $stop['also'] = "\n\nTambién quedaron en espera otros dominios del pedido:\n- " . implode( "\n- ", $x['alerts'] );
				elseif ( $x['alerts'] ) self::extras_alert( $order, $x['alerts'] );
				self::fail( $order, $state, $stop, self::HOOK_PROVISION, $order->get_id(), $service );
				return;
			}
			if ( $x['retry'] ) self::enqueue( self::HOOK_PROVISION, $order->get_id(), $x['retry'], true );
			self::extras_alert( $order, $x['alerts'] );
			// Dado de baja mientras corría la última llamada: no se escribe el estado; se avisa lo gastado.
			self::refresh( $service );
			if ( $service && 'cancelled' === self::svc( $service, 'status' ) ) { self::interrupted( $order, $state, 'cancelled', $service, false ); return; }
			// Reembolsado o cancelado en otra petición: nunca se pasa a «completado».
			if ( in_array( self::db_order_status( $order ), self::DEAD, true ) ) { self::interrupted( $order, $state, 'dead', $service, false ); return; }
			// La escritura final es condicional: sólo si el servicio sigue en el estado leído (una baja entre medias gana).
			$cur = (string) self::svc( $service, 'status' );
			if ( 'cancelled' === $cur || ! self::set_status_if( $service, $cur, 'active' ) ) { self::interrupted( $order, $state, 'cancelled', $service, false ); return; }
			$state['done'] = time(); unset( $state['attempts'], $state['blocked_noted'] );
			self::save_state( $order, $state );
			// El hosting vence según su periodo (mensual = un mes); el dominio, siempre a un año. Sin hosting, el servicio vence
			// con el dominio.
			$now = time();
			$period = $items['hosting'] ? (int) $items['hosting'][0]['period'] : 12;
			$domain_exp = (int) strtotime( '+1 year', $now );
			$expires = $items['hosting'] ? self::add_months( $now, $period ) : $domain_exp;
			$set = array( 'expires' => $expires, 'period' => $items['hosting'] ? $period : 12, 'error' => '', 'started' => $now );
			if ( $items['domains'] || $items['transfers'] ) $set['domain_expires'] = $domain_exp;
			self::svc_set( $service, $set );
			self::svc_log( $service, 'Aprovisionado con el pedido #' . $order->get_id() . '.' );
			// «Completado» sólo sin dominios más pendientes (N2).
			self::maybe_complete( $order, 'servicio entregado; vence el ' . self::date( $expires ) );
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
		unset( $state['attempts'], $state['blocked_noted'], $state['stopped'] );
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
			// Hosting con dominio propio, que nadie verificó que sea del comprador.
			$reject = self::own_domain_rejected( $domain );
			if ( $reject ) return self::stop( 'data', $reject );
			if ( empty( Digitalisimo_Hosting::settings()['own_domain_auto'] ) && ! self::svc( $service, 'domain_approved' ) ) return self::stop( 'review', ! empty( $items['transfers'] ) ? 'Transferencia de ' . $domain . ( $items['hosting'] ? ' con hosting' : '' ) . ': por omisión se revisa a mano antes de tramitarla' . ( $items['hosting'] ? ' y de crear la cuenta' : '' ) . '. Si el dominio es del cliente, pulsa «Aprobar dominio» en WooCommerce → Servicios de hosting.' : 'Hosting con dominio propio (' . $domain . '): por omisión se revisa a mano antes de crear la cuenta. Si el dominio es del cliente, pulsa «Aprobar dominio» en WooCommerce → Servicios de hosting.' );
			// ¿Está en alguna de nuestras cuentas de registrador (lo vendimos nosotros)? Primero el registrador de su extensión y
			// después las demás cuentas puestas. Una respuesta que no se entiende de un registrador que NO atiende esta
			// extensión no frena (lo anota); la del que la atiende sigue frenando (el lado seguro).
			$assigned = Digitalisimo_Hosting_Registrars::id_for_domain( $domain );
			$accounts = Digitalisimo_Hosting_Registrars::configured_all();
			if ( isset( $accounts[ $assigned ] ) ) $accounts = array( $assigned => $accounts[ $assigned ] ) + $accounts;
			foreach ( $accounts as $rid => $nc ) {
				$info = $nc->info( $domain );
				if ( 'ok' !== $info['kind'] && $rid !== $assigned ) { self::note_once( $order, 'owner_skip_' . $rid, 'no se pudo confirmar en ' . $nc->label() . ' si ' . $domain . ' está en esa cuenta (' . $info['message'] . '); ' . $nc->label() . ' no atiende esa extensión, así que no frena.' ); continue; }
				if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
				if ( $info['ours'] && ! $known ) return self::stop( 'owner', 'El dominio ' . $domain . ' está en nuestra cuenta de ' . $nc->label() . ' y no consta como de este cliente: no se creó la cuenta. Revísalo a mano.' );
			}
		}
		return null;
	}

	/** Dominios de correo públicos: nadie hospeda gmail.com con un plan. */
	const PUBLIC_MAIL = array( 'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.es', 'outlook.com', 'outlook.es', 'live.com', 'live.com.mx', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'protonmail.com', 'proton.me', 'gmx.com', 'gmx.net', 'zoho.com', 'yandex.com', 'mail.com', 'prodigy.net.mx', 'telmex.com' );

	/** El dominio registrable de un nombre de host (ns1.digitalisimo.website → digitalisimo.website). */
	public static function registrable( $host ) {
		$labels = explode( '.', strtolower( trim( (string) $host, '.' ) ) );
		if ( count( $labels ) < 2 ) return '';
		$two = implode( '.', array_slice( $labels, -2 ) );
		return in_array( $two, self::SECOND_LEVEL, true ) && count( $labels ) >= 3 ? implode( '.', array_slice( $labels, -3 ) ) : $two;
	}

	/** Lo que nunca se hospeda como dominio propio: correo público, un sufijo sin etiqueta propia, nuestros dominios. */
	public static function own_domain_rejected( $domain ) {
		if ( in_array( $domain, self::PUBLIC_MAIL, true ) || preg_match( '/^yahoo\./', $domain ) || preg_match( '/^(hotmail|outlook|live)\./', $domain ) ) return 'El dominio ' . $domain . ' es de un servicio de correo público: no se hospeda. Revísalo con el cliente.';
		if ( in_array( $domain, self::SECOND_LEVEL, true ) ) return '«' . $domain . '» es un sufijo público, no un dominio: no se hospeda.';
		$ours = array( 'digitalisimo.website' );
		foreach ( self::nameservers() as $ns ) $ours[] = self::registrable( $ns );
		if ( function_exists( 'home_url' ) ) $ours[] = self::registrable( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( in_array( $domain, array_filter( $ours ), true ) ) return 'El dominio ' . $domain . ' es nuestro: no se hospeda para un cliente.';
		return '';
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
		$transfers = $items['transfers'] ?? array();
		// Varios dominios sí (cada uno con su servicio: provision_extras()); varios planes, no.
		if ( count( $items['hosting'] ) > 1 ) return self::stop( 'data', 'El pedido tiene más de un plan de hosting; la venta automática atiende un plan por pedido. Entrégalo a mano.' );
		foreach ( array_merge( $items['hosting'], $items['domains'], $transfers ) as $row ) if ( $row['qty'] > 1 ) return self::stop( 'data', 'El pedido tiene una cantidad mayor que 1 de «' . $row['item']->get_name() . '»; la venta automática registra un año por pedido.' );
		if ( '' === $domain ) return self::stop( 'data', 'El pedido no trae el dominio (dato «Dominio» del producto).' );
		if ( ! self::valid_domain( $domain ) || ! self::split( $domain ) ) return self::stop( 'data', 'El dominio «' . $domain . '» no es válido.' );
		if ( $items['domains'] ) {
			// El producto pagado debe ser el de la extensión del dominio: nadie registra un .io pagando el precio de un .com.
			$split = self::split( $domain );
			if ( Digitalisimo_Hosting_Products::register_ext( $items['domains'][0]['id'] ) !== $split[2] ) return self::stop( 'data', 'El dominio «' . $domain . '» no corresponde al producto de dominio pagado; no se registró.' );
		}
		return null;
	}

	private static function step_hosting( $order, array &$state, array $items, $domain, $service ) {
		if ( ! $items['hosting'] || 'done' === ( $state['hosting']['status'] ?? '' ) ) return null;
		$plan = $items['hosting'][0]['plan'];
		if ( ! preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $plan ) ) return self::stop( 'data', 'El producto «' . $items['hosting'][0]['item']->get_name() . '» no tiene el paquete de Webuzo: elígelo en su editor, pestaña «DIGITALÍSIMO Hosting».' );
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
		$halt = self::may_spend( $service, $order );
		if ( $halt ) return $halt;
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
		if ( ! empty( $items['transfers'] ) ) {
			// Antes de dar la transferencia por buena: producto de registro para renovarla y el mismo freno de pérdida que el
			// registro, con el costo de transferencia de Namecheap (o el de registro si no lo da).
			// Un reintento con otro dominio (la línea se corrigió a mano): se borra sólo el EPP de un dominio que ya no está en
			// las transferencias del pedido; el de las demás transferencias del mismo pedido se conserva (segunda revisión, N3).
			$epp = $order->get_meta( '_digitalisimo_epp' );
			if ( is_array( $epp ) ) {
				$lines = array();
				foreach ( self::items( $order )['transfers'] as $tr ) $lines[ (string) $tr['domain'] ] = 1;
				$keep = array_intersect_key( $epp, $lines );
				if ( count( $keep ) !== count( $epp ) ) {
					if ( $keep ) $order->update_meta_data( '_digitalisimo_epp', $keep ); else $order->delete_meta_data( '_digitalisimo_epp' );
					$order->save();
				}
			}
			if ( empty( $state['transfer_guarded'] ) ) {
				$split = self::split( $domain ); $map = Digitalisimo_Hosting::domain_products();
				if ( ! $split || ! isset( $map[ $split[2] ] ) ) return self::stop( 'data', 'No hay producto de registro de ' . ( $split ? $split[2] : $domain ) . ' con el que renovar el dominio transferido: no se tramita la transferencia de ' . $domain . '. Revísalo a mano.' );
				// El registrador que recibirá el dominio: el de su extensión (queda anotado en el servicio).
				$nc = Digitalisimo_Hosting_Registrars::make( (string) ( $state['transfer_registrar'] ?? Digitalisimo_Hosting_Registrars::id_for_ext( $split[2] ) ) );
				if ( ! $nc->configured() ) return self::stop( 'config', 'Faltan los datos de la cuenta de ' . $nc->label() . ' en Digitalisimo → Hosting.' );
				$state['transfer_registrar'] = $nc->id();
				$cost = $nc->price( $split[1], 'TRANSFER' );
				if ( 'ok' !== $cost['kind'] ) $cost = $nc->price( $split[1], 'REGISTER' );
				if ( 'ok' !== $cost['kind'] ) return self::stop( self::nc_kind( $cost ), $cost['message'] );
				$guard = self::price_guard( $items['transfers'][0]['item'], $cost['value'], 'transferir ' . $domain, $order, $nc->label() );
				if ( $guard ) return $guard;
				$state['transfer_guarded'] = time(); self::save_state( $order, $state );
			}
			// La transferencia se pide a mano en Namecheap con el código EPP («Ver código EPP» en el pedido): el código nunca
			// va a notas ni correos. Ya pasó la revisión de dueño (check_owner), como un dominio propio.
			if ( empty( $state['transfer_noted'] ) ) {
				$state['transfer_noted'] = 1; self::save_state( $order, $state );
				// El dominio transferido se renueva con el producto de registro de su extensión (su precio de renovación);
				// Namecheap lo renueva sólo cuando la transferencia terminó (transfer_settled()).
				$split = self::split( $domain ); $map = Digitalisimo_Hosting::domain_products();
				$reg = $split && isset( $map[ $split[2] ] ) ? (int) $map[ $split[2] ] : 0;
				$rid = Digitalisimo_Hosting_Registrars::valid( (string) ( $state['transfer_registrar'] ?? '' ) ) ? (string) $state['transfer_registrar'] : Digitalisimo_Hosting_Registrars::id_for_domain( $domain );
				$rlabel = Digitalisimo_Hosting_Registrars::label( $rid );
				self::svc_set( $service, array( 'transfer_pending' => 1, 'registrar' => $rid ) + ( $reg ? array( 'domain_product' => $reg ) : array() ) );
				self::svc_log( $service, 'Transferencia de ' . $domain . ' por hacer a mano.' );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: transferencia de ' . $domain . ' por hacer a mano en ' . $rlabel . ' con el código EPP del cliente (botón «Ver código EPP» en este pedido; nunca lo copies en notas ni correos). Al terminar, apúntalo a ' . implode( ' y ', self::nameservers() ) . '.' );
				self::alert_admin( 'Transferencia de dominio por hacer: ' . $domain, 'El pedido #' . $order->get_id() . ' pagó la transferencia de ' . $domain . '. Hazla en ' . $rlabel . ' con el código EPP («Ver código EPP» en el pedido): ' . self::order_link( $order ) );
			}
			return null;
		}
		if ( ! $items['domains'] ) {
			if ( empty( $state['own_domain_noted'] ) ) {
				$state['own_domain_noted'] = 1; self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el pedido no incluye registro de dominio (dominio propio del cliente). Debe apuntarlo a ' . implode( ' y ', self::nameservers() ) . '.' );
			}
			return null;
		}
		if ( 'done' === ( $state['domain']['status'] ?? '' ) ) return null;
		// El registrador del dominio: el que anotó un intento anterior de este pedido (aunque los ajustes cambien después; un
		// intento de antes de esta versión era de Namecheap) o el de su extensión.
		$rid = (string) ( $state['domain']['registrar'] ?? '' );
		if ( ! Digitalisimo_Hosting_Registrars::valid( $rid ) ) $rid = ! empty( $state['domain']['create_sent'] ) || ! empty( $state['domain']['registered'] ) ? Digitalisimo_Hosting_Registrars::DEFAULT_ID : Digitalisimo_Hosting_Registrars::id_for_domain( $domain );
		$nc = Digitalisimo_Hosting_Registrars::make( $rid );
		$rl = $nc->label();
		if ( ! $nc->configured() ) return self::stop( 'config', 'Faltan los datos de la cuenta de ' . $rl . ' en Digitalisimo → Hosting.' );
		list( $sld, $tld ) = self::split( $domain );
		if ( empty( $state['domain']['registered'] ) ) {
			$info = $nc->info( $domain );
			if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
			if ( $info['ours'] && empty( $state['domain']['create_sent'] ) ) {
				// Ya estaba en la cuenta y este pedido nunca pidió registrarlo: es de otro cliente (o de la casa). Adoptarlo
				// cambiaría sus nameservers y le daría su web y su correo al comprador.
				return self::stop( 'data', 'El dominio ' . $domain . ' ya está en la cuenta de ' . $rl . ' y no lo registró este pedido: no se tocó (ni sus nameservers). Revísalo a mano.' );
			}
			// Sólo se adopta si Namecheap lo da por creado después del primer intento de este pedido (mismo día o después).
			// (Namecheap da la fecha en hora del Este de EE. UU.: se admite el día anterior en UTC.)
			if ( $info['ours'] && (int) ( $info['created'] ?? 0 ) < (int) gmmktime( 0, 0, 0, (int) gmdate( 'n', (int) $state['domain']['create_sent'] ), (int) gmdate( 'j', (int) $state['domain']['create_sent'] ), (int) gmdate( 'Y', (int) $state['domain']['create_sent'] ) ) - DAY_IN_SECONDS ) {
				return self::stop( 'data', 'El dominio ' . $domain . ' está en la cuenta de ' . $rl . ' pero se creó antes del intento de este pedido: no se adoptó ni se tocaron sus nameservers. Revísalo a mano.' );
			}
			if ( $info['ours'] ) {
				$state['domain'] = array( 'registered' => time(), 'existed' => 1, 'create_sent' => $state['domain']['create_sent'], 'registrar' => $rid );
				self::save_state( $order, $state );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el dominio ' . $domain . ' ya estaba en la cuenta de ' . $rl . '; no se registró otra vez.' );
			} else {
				list( $contact, $missing ) = self::contact( $order );
				if ( $missing ) return self::stop( 'data', 'Faltan datos del titular del dominio en la facturación del pedido (' . implode( ', ', $missing ) . '). Complétalos en el pedido y reintenta.' );
				$price = $nc->price( $tld, 'REGISTER' );
				if ( 'ok' !== $price['kind'] ) return self::stop( self::nc_kind( $price ), $price['message'] );
				$guard = self::price_guard( $items['domains'][0]['item'], $price['value'], 'registrar ' . $domain, $order, $rl );
				if ( $guard ) return $guard;
				// Disponible y no premium (un premium cuesta otra cosa y exige IsPremiumDomain: no se registra a ciegas).
				$check = $nc->check( $domain );
				if ( 'ok' !== $check['kind'] ) return self::stop( self::nc_kind( $check ), $check['message'] );
				if ( ! $check['available'] ) return self::stop( 'data', 'El dominio ' . $domain . ' ya no está disponible en ' . $rl . ': no se registró.' );
				if ( $check['premium'] ) return self::stop( 'price', 'El dominio ' . $domain . ' es premium en ' . $rl . ' (precio especial): no se registra solo. Revísalo a mano.' );
				$balance = $nc->balance();
				if ( 'ok' !== $balance['kind'] ) return self::stop( self::nc_kind( $balance ), $balance['message'] );
				if ( $balance['value'] < $price['value'] ) return self::stop( 'funds', sprintf( 'Saldo insuficiente en ' . $rl . ' para registrar %s: hay %s USD y cuesta %s USD. Recarga la cuenta y pulsa «Reintentar» en WooCommerce → Servicios de hosting.', $domain, number_format( $balance['value'], 2, '.', '' ), number_format( $price['value'], 2, '.', '' ) ) );
				// Justo antes de cobrar: ¿sigue vivo el servicio y encendida la venta? (otra petición pudo cambiarlo)
				$halt = self::may_spend( $service, $order );
				if ( $halt ) return $halt;
				// Antes de pedirlo: si la respuesta se pierde, el reintento sabrá que este pedido lo registró.
				$state['domain'] = array( 'create_sent' => time(), 'registrar' => $rid );
				self::save_state( $order, $state );
				$r = $nc->create( $domain, $contact, 1 );
				if ( 'ok' !== $r['kind'] ) {
					// La marca sólo sobrevive si el intento quedó sin respuesta (pudo llegar); un error definitivo la borra,
					// así un «Reintentar» posterior nunca adopta un dominio que otro metió a la cuenta.
					if ( 'transient' !== $r['kind'] ) { unset( $state['domain']['create_sent'] ); self::save_state( $order, $state ); }
					return self::stop( self::nc_kind( $r ), $r['message'] );
				}
				$state['domain'] = array( 'registered' => time(), 'id' => $r['domain_id'], 'create_sent' => $state['domain']['create_sent'], 'registrar' => $rid );
				self::save_state( $order, $state );
				self::svc_set( $service, array( 'registrar' => $rid ) );
				// Dynadot no informa ID ni cargo: se dice lo que el registrador dio.
				$detail = array();
				if ( '' !== (string) $r['domain_id'] ) $detail[] = 'ID ' . $r['domain_id'];
				if ( '' !== (string) $r['charged'] ) $detail[] = 'cargo ' . $r['charged'] . ' USD';
				$order->add_order_note( 'DIGITALÍSIMO Hosting: dominio ' . $domain . ' registrado en ' . $rl . ' a nombre de ' . trim( $contact['FirstName'] . ' ' . $contact['LastName'] ) . ( $detail ? ' (' . implode( ', ', $detail ) . ')' : '' ) . '.' );
			}
		}
		$ns = self::nameservers();
		$r = $nc->set_dns( $sld, $tld, $ns );
		if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
		$state['domain']['status'] = 'done'; $state['domain']['dns'] = $ns;
		self::save_state( $order, $state );
		// El servicio recuerda su registrador: la renovación y las revisiones van siempre a esa cuenta.
		self::svc_set( $service, array( 'domain_registered' => 1, 'registrar' => $rid ) );
		$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $domain . ' apunta a ' . implode( ' y ', $ns ) . '.' );
		return null;
	}

	/** Lo cobrado por el dominio (sin IVA, tras descuentos) contra su costo real de hoy (Digitalisimo_Hosting_Pricing::guard). */
	private static function price_guard( $item, $cost_usd, $label, $order = null, $registrar = 'Namecheap' ) {
		if ( ! class_exists( 'Digitalisimo_Hosting_Pricing' ) ) return null;
		$charged = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
		$currency = $order && is_callable( array( $order, 'get_currency' ) ) ? (string) $order->get_currency() : null;
		return Digitalisimo_Hosting_Pricing::guard( $charged, $cost_usd, $label, $currency, $registrar );
	}

	/** Un error de clave del registrador (Namecheap o Dynadot) no detiene Webuzo: se trata como configuración (sin reintentar). */
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
			'has_transfer' => ! empty( $items['transfers'] ),
			'user'        => (string) ( $state['hosting']['user'] ?? '' ),
			'password'    => $items['hosting'] ? self::hosting_password( $order, $state ) : '',
			'panel_url'   => $items['hosting'] ? $api->panel_url() : '',
			'nameservers' => self::nameservers(),
			'extras'      => self::extras_summary( $order ),
		);
	}

	/**
	 * Cómo quedó cada uno de los demás dominios del pedido, para el correo de accesos: dominio => texto para el cliente
	 * (registrado, transferencia en trámite o en revisión). Nunca el motivo interno (saldo, errores de la API).
	 */
	public static function extras_summary( $order ) {
		$out = array();
		foreach ( self::extras_state( $order ) as $domain => $x ) {
			$sid = (int) $x['service'];
			if ( $sid && 'cancelled' === self::svc( $sid, 'status' ) ) continue;
			if ( empty( $x['state']['done'] ) ) $out[ $domain ] = 'en trámite: lo estamos revisando y te avisamos por correo si necesitamos algo';
			elseif ( 'transfer' === $x['row']['kind'] ) $out[ $domain ] = 'transferencia en trámite (puede tardar hasta 7 días); al llegar lo apuntamos a nuestros nameservers';
			else $out[ $domain ] = 'registrado y apuntado a nuestros nameservers';
		}
		return $out;
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
		// Baja o venta apagada a medio camino: el estado del servicio no se pisa (ni «en espera» ni reintentos).
		if ( $service ) self::refresh( $service );
		if ( $service && 'cancelled' === self::svc( $service, 'status' ) ) $kind = 'cancelled';
		if ( 'cancelled' === $kind || 'off' === $kind || 'dead' === $kind ) { self::interrupted( $order, $state, $kind, $service, self::HOOK_RENEW === $hook ); return; }
		if ( 'owner' === $kind && $service ) self::svc_set( $service, array( 'owner_conflict' => 1 ) );
		if ( 'review' === $kind && $service ) self::svc_set( $service, array( 'needs_review' => 1 ) );
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
		// Detenido por algo que no es la red (saldo, datos, costo, dueño, error de la API): el vigilante no lo reintenta
		// solo; queda «en espera» con su «Reintentar».
		if ( 'blocked' !== $kind && 'auth' !== $kind ) $state['stopped'] = $kind;
		$order->update_meta_data( $key, $state ); $order->save();
		if ( $service ) { self::svc_set( $service, array( 'status' => $is_renew ? self::svc( $service, 'status' ) : 'on-hold', 'error' => $message ) ); self::svc_log( $service, $message ); }
		$note = 'DIGITALÍSIMO Hosting: ' . $message;
		if ( 'on-hold' !== $order->get_status() ) $order->update_status( 'on-hold', $note );
		else $order->add_order_note( $note );
		if ( 'blocked' !== $kind && 'auth' !== $kind ) self::alert_admin( 'pedido #' . $order->get_id() . ' en espera', $message . (string) ( $stop['also'] ?? '' ) . "\n\nPedido: " . self::order_link( $order ) . "\nServicios: " . admin_url( 'admin.php?page=digitalisimo-hosting-services' ) );
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
		if ( ! self::on() ) return;
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
			try {
				self::fill_domain_expires( $id );
				self::watchdog( $id ); self::extra_watchdog( $id ); self::renewal_watchdog( $id ); self::reconcile_net( $id ); self::check_service( $id, $now, $grace );
				// Lo anotado con la venta apagada se retoma aunque se haya encendido sin pasar por los ajustes.
				if ( self::svc( $id, 'resume_provision' ) ) { self::svc_set( $id, array( 'resume_provision' => 0 ) ); self::retry( $id ); }
			} catch ( Throwable $e ) { self::svc_log( $id, 'Error en la revisión diaria: ' . $e->getMessage() ); }
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

	/**
	 * Un dominio más de un pedido (N2) que quedó a medias sin tarea en cola (se perdió, o PHP la cortó) y que no está
	 * detenido: se vuelve a encolar (una vez al día como mucho; cada paso consulta antes de registrar, no duplica) y se
	 * avisa. Lo detenido (saldo, datos, revisión, reintentos agotados) espera «Reintentar» o «Aprobar dominio».
	 */
	private static function extra_watchdog( $id ) {
		if ( ! self::is_extra( $id ) || ! in_array( (string) self::svc( $id, 'status' ), array( 'pending', 'on-hold' ), true ) ) return;
		if ( time() - (int) self::svc( $id, 'created' ) < HOUR_IN_SECONDS ) return;
		$order_id = (int) self::svc( $id, 'order' );
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_PROVISION, array( $order_id ), self::GROUP ) ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order || empty( self::state( $order )['paid'] ) || in_array( $order->get_status(), self::DEAD, true ) ) return;
		foreach ( self::extras_state( $order, true ) as $domain => $x ) {
			if ( (int) $x['service'] !== (int) $id ) continue;
			$st = $x['state'];
			if ( ! empty( $st['stopped'] ) || ( ! empty( $st['watchdog'] ) && time() - (int) $st['watchdog'] < DAY_IN_SECONDS ) ) return;
			$st['watchdog'] = time();
			$order->update_meta_data( self::extra_key( $x['row'] ), $st ); $order->save();
			$order->add_order_note( 'DIGITALÍSIMO Hosting: el dominio ' . $domain . ' quedó a medias sin tarea en cola; se retoma (no duplica: cada paso consulta antes de registrar).' );
			self::alert_admin( 'dominio ' . $domain . ' retomado', 'El dominio ' . $domain . ' del pedido #' . $order_id . ' quedó a medias sin tarea en cola (se perdió o se cortó). Se volvió a encolar.' . "\n\n" . self::order_link( $order ) );
			self::enqueue( self::HOOK_PROVISION, $order_id );
			return;
		}
	}

	/** La renovación pagada (cobro confirmado) y no terminada del servicio, o null. */
	public static function renewal_pending( $id ) {
		foreach ( array_unique( array_filter( array( (int) self::svc( $id, 'renewal_order' ), (int) self::svc( $id, 'domain_renewal_order' ), (int) self::svc( $id, 'domain_pending_order' ) ) ) ) as $rid ) {
			$r = wc_get_order( $rid );
			if ( ! $r || in_array( $r->get_status(), array( 'cancelled', 'refunded', 'failed', 'trash' ), true ) ) continue;
			$s = (array) $r->get_meta( self::META_RENEW );
			if ( empty( $s['done'] ) && self::renewal_confirmed( $r ) ) return $r;
		}
		return null;
	}

	/**
	 * Una renovación cobrada que no llegó a ejecutarse (se pagó con la venta apagada, o su tarea murió): se encola y se
	 * avisa al administrador (una vez al día como mucho). Los pedidos de alta anteriores al encendido NO pasan por aquí.
	 */
	public static function renewal_watchdog( $id ) {
		if ( 'cancelled' === self::svc( $id, 'status' ) ) return false;
		// El servicio viejo de un dominio recontratado no se renueva: su renovación pagada se detiene y se avisa para
		// reembolsar (una vez), en vez de retomarla.
		if ( ! self::owns_account( $id ) ) { $r = self::renewal_pending( $id ); if ( $r ) self::superseded_paid( $r, $id ); return false; }
		$r = self::renewal_pending( $id );
		if ( ! $r ) return false;
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_RENEW, array( $r->get_id() ), self::GROUP ) ) return false;
		$s = (array) $r->get_meta( self::META_RENEW );
		// Detenida por un motivo que no es la red (saldo, costo, datos): ya está en espera con su aviso y su «Reintentar».
		if ( ! empty( $s['stopped'] ) || ! empty( $s['blocked_noted'] ) ) return false;
		if ( empty( $s['paid'] ) ) $s['paid'] = time();
		$alert = empty( $s['watchdog'] ) || time() - (int) $s['watchdog'] >= DAY_IN_SECONDS;
		$s['watchdog'] = time();
		$r->update_meta_data( self::META_RENEW, $s ); $r->save();
		self::cancel_suspension( $id, 'renovación #' . $r->get_id() . ' cobrada' );
		self::enqueue( self::HOOK_RENEW, $r->get_id() );
		if ( $alert ) {
			$r->add_order_note( 'DIGITALÍSIMO Hosting: la renovación cobrada no se había ejecutado (venta apagada o tarea perdida); se encoló.' );
			self::alert_admin( 'renovación #' . $r->get_id() . ' retomada', 'La renovación cobrada de ' . self::svc( $id, 'domain' ) . ' no se había ejecutado (la venta estaba apagada o su tarea se perdió). Se volvió a encolar.' . "\n\n" . self::order_link( $r ) );
		}
		return true;
	}

	/** Al reencender la venta: las renovaciones cobradas pendientes se encolan (las altas anteriores, no). */
	public static function sweep_renewals() {
		if ( ! self::on() ) return 0;
		$n = 0;
		foreach ( self::services() as $id ) {
			if ( self::renewal_watchdog( $id ) ) $n++;
			self::reconcile_net( $id );
			// Lo aprobado o reintentado con la venta apagada, y el alta que la venta apagó a medio camino, se retoman.
			if ( self::svc( $id, 'resume_provision' ) ) { self::svc_set( $id, array( 'resume_provision' => 0 ) ); if ( self::retry( $id ) ) $n++; }
		}
		return $n;
	}

	/**
	 * Red de la reconciliación (revisión diaria y reencendido): un servicio suspendido por el plugin por falta de pago que
	 * ya no está vencido sin pago (pagó), o una reconciliación que quedó pendiente, se vuelve a reconciliar con la lista
	 * real de Webuzo. Una vez al día como mucho por servicio.
	 */
	private static function reconcile_net( $id ) {
		if ( 'cancelled' === self::svc( $id, 'status' ) || 'manual' === self::svc( $id, 'suspend_reason' ) || ! self::svc( $id, 'webuzo_user' ) ) return;
		$sent = self::svc( $id, 'suspend_sent' );
		$ours = 'suspended' === self::svc( $id, 'status' ) && is_array( $sent ) && 'auto' === ( $sent['reason'] ?? '' );
		// También se mira cada día una suspensión nuestra aún sin pagar: si alguien la reactivó en el panel de Webuzo, la
		// marca «auto» deja de valer (una suspensión posterior en el panel ya no sería nuestra).
		if ( ! $ours && ! self::svc( $id, 'reconcile_pending' ) ) return;
		if ( time() - (int) self::svc( $id, 'reconcile_net_t' ) < DAY_IN_SECONDS - HOUR_IN_SECONDS ) return;
		self::svc_set( $id, array( 'reconcile_net_t' => time() ) );
		self::enqueue( self::HOOK_RECONCILE, $id );
	}

	/** Baja del servicio (pantalla de servicios): ni renovaciones, ni avisos, ni suspensiones. No toca Webuzo ni Namecheap. */
	public static function cancel_service( $id ) {
		if ( ! self::is_service( $id ) ) return false;
		foreach ( array( self::HOOK_SUSPEND, self::HOOK_UNSUSPEND, self::HOOK_VERIFY, self::HOOK_RECONCILE ) as $hook ) if ( function_exists( 'as_unschedule_all_actions' ) ) as_unschedule_all_actions( $hook, array( (int) $id ), self::GROUP );
		// Lo que ya estaba en marcha también se descarta: el alta (si no se entregó) y la renovación en cola o en espera.
		// El servicio de un dominio más del pedido no desencola el alta del pedido (es la de los demás dominios y del plan).
		foreach ( array( array( self::HOOK_PROVISION, self::is_extra( $id ) ? 0 : (int) self::svc( $id, 'order' ) ), array( self::HOOK_RENEW, (int) self::svc( $id, 'renewal_order' ) ), array( self::HOOK_RENEW, (int) self::svc( $id, 'domain_renewal_order' ) ) ) as $ho ) if ( $ho[1] && function_exists( 'as_unschedule_all_actions' ) ) as_unschedule_all_actions( $ho[0], array( $ho[1] ), self::GROUP );
		$w = get_option( self::WAITING, array() );
		if ( is_array( $w ) ) { foreach ( array( self::HOOK_SUSPEND, self::HOOK_UNSUSPEND, self::HOOK_VERIFY, self::HOOK_RECONCILE ) as $hook ) unset( $w[ $hook . ':' . (int) $id ] ); unset( $w[ self::HOOK_PROVISION . ':' . ( self::is_extra( $id ) ? 0 : (int) self::svc( $id, 'order' ) ) ], $w[ self::HOOK_RENEW . ':' . (int) self::svc( $id, 'renewal_order' ) ], $w[ self::HOOK_RENEW . ':' . (int) self::svc( $id, 'domain_renewal_order' ) ] ); update_option( self::WAITING, $w, false ); }
		self::svc_set( $id, array( 'status' => 'cancelled', 'pending_toggle' => array(), 'suspend_reason' => '', 'suspend_for' => 0, 'error' => '', 'cancelled_at' => time() ) );
		self::svc_log( $id, 'Dado de baja: sin renovaciones ni suspensiones automáticas.' );
		// Un dominio más del pedido dado de baja ya no detiene el pedido: se revisa si queda algo pendiente para completarlo.
		if ( self::is_extra( $id ) && self::on() ) { $o = wc_get_order( (int) self::svc( $id, 'order' ) ); if ( $o && ! empty( self::state( $o )['done'] ) ) self::maybe_complete( $o ); }
		foreach ( array( 'renewal_order', 'domain_renewal_order' ) as $slot ) { $r = wc_get_order( (int) self::svc( $id, $slot ) ); if ( $r ) $r->add_order_note( 'DIGITALÍSIMO Hosting: el servicio se dio de baja; esta renovación ya no aplica.' ); }
		return true;
	}

	public static function check_service( $id, $now, $grace ) {
		$status = (string) self::svc( $id, 'status' );
		if ( ! in_array( $status, array( 'active', 'suspended' ), true ) ) return;
		if ( self::svc( $id, 'transfer_pending' ) ) self::transfer_settled( $id );
		// Una suspensión o reactivación pedida y aún sin confirmar: no se pide otra.
		$pt = self::svc( $id, 'pending_toggle' );
		if ( is_array( $pt ) && ! empty( $pt['op'] ) ) {
			// Caduca: una orden sin confirmar de hace más de un día ya no frena nada (la confirmación se agotó o se perdió).
			if ( time() - (int) ( $pt['t'] ?? 0 ) < DAY_IN_SECONDS ) return;
			self::svc_set( $id, array( 'pending_toggle' => array() ) ); self::svc_log( $id, 'Una orden de ' . $pt['op'] . ' sin confirmar caducó.' );
		}
		$expires = (int) self::svc( $id, 'expires' );
		if ( ! $expires ) return;
		$hosting = self::has_hosting( $id );
		$period = $hosting ? self::service_period( $id ) : 12;
		list( $first, $second ) = self::notice_days( $period );
		// El dominio va por su lado cuando vence en otra fecha que el hosting (hosting mensual, trianual…) o cuando ya tiene
		// su propio pedido vivo (aunque las fechas hayan llegado a coincidir: el cliente pagó tarde un mes).
		$dd = self::domain_due( $id );
		$coincide = $dd && abs( $dd - $expires ) < 2 * DAY_IN_SECONDS;
		$apart = $hosting && $dd && ! self::svc( $id, 'transfer_pending' ) && ( ! $coincide || self::live_order( (int) self::svc( $id, 'domain_renewal_order' ) ) );
		$host_soon = $expires - $now <= $first * DAY_IN_SECONDS;
		$dom_soon = $apart && $dd - $now <= 30 * DAY_IN_SECONDS;
		if ( ! $host_soon && ! $dom_soon ) return;
		// Recontratado el mismo dominio con una cuenta nueva: el servicio viejo no avisa, no cobra ni suspende.
		if ( ! self::owns_account( $id ) ) { self::superseded( $id ); return; }
		// Primero el pedido del hosting: si el dominio vence el mismo día y su pedido propio sigue sin pagar, lo absorbe (se
		// cancela y los dos se cobran juntos; segunda revisión, N4). Después, el dominio que sigue aparte.
		$renewal = $host_soon ? self::renewal_order( $id ) : null;
		if ( $dom_soon && ( ! $coincide || self::live_order( (int) self::svc( $id, 'domain_renewal_order' ) ) ) ) self::check_domain( $id, $now, $grace, $dd );
		if ( ! $host_soon ) return;
		$days = (int) floor( ( $expires - $now ) / DAY_IN_SECONDS );
		if ( ! $renewal ) return;
		// Pagada con cobro confirmado (aunque la renovación esté a medias, p. ej. sin saldo en Namecheap): ni avisos ni
		// suspensión. En «procesando» SIN cobro confirmado (contra entrega) no frena nada y se avisa al administrador.
		if ( self::renewal_confirmed( $renewal ) ) return;
		if ( in_array( $renewal->get_status(), array( 'processing', 'on-hold' ), true ) && self::note_once( $renewal, 'unconfirmed', 'renovación en «' . $renewal->get_status() . '» sin cobro confirmado (' . ( $renewal->get_payment_method() ?: 'manual' ) . '); márcala «completado» al cobrar. Mientras tanto el servicio vence y se suspende como si no estuviera pagada.' ) ) {
			self::alert_admin( 'renovación #' . $renewal->get_id() . ' sin cobro confirmado', 'La renovación de ' . self::svc( $id, 'domain' ) . ' está en «' . $renewal->get_status() . '» pero el cobro no está confirmado. Márcala «completado» cuando cobres; si no, el servicio vence y se suspende.' . "\n\n" . self::order_link( $renewal ) );
		}
		$sent = self::svc( $id, 'notices' ); $sent = is_array( $sent ) && (int) ( $sent['cycle'] ?? 0 ) === $expires ? $sent : array( 'cycle' => $expires, 'sent' => array() );
		$type = '';
		if ( $days > $second && ! in_array( (string) $first, $sent['sent'], true ) ) $type = (string) $first;
		elseif ( $days <= $second && $days >= 0 && ! in_array( (string) $second, $sent['sent'], true ) ) $type = (string) $second;
		if ( $type ) {
			$sent['sent'][] = $type; self::svc_set( $id, array( 'notices' => $sent ) );
			$ok = self::send_renewal( $renewal, $id, $type );
			$renewal->add_order_note( 'DIGITALÍSIMO Hosting: aviso de vencimiento (' . $type . ' días) ' . ( $ok ? 'enviado' : 'no enviado (correo desactivado o falló)' ) . ' a ' . self::svc( $id, 'email' ) . '.' );
		}
		// El dominio va en este pedido (vence el mismo día) y no se pagó: al pasar la gracia se avisa al dueño, como cuando
		// tiene su propio pedido (el aviso del dominio no se pierde al cobrarlo junto con el hosting; N4).
		if ( $hosting && $coincide && $now > $dd + $grace * DAY_IN_SECONDS && (int) self::svc( $id, 'domain_lapsed' ) !== $dd && ! empty( self::order_parts( $renewal, $id )['domain'] ) ) {
			self::svc_set( $id, array( 'domain_lapsed' => $dd ) );
			self::svc_log( $id, 'El dominio ' . self::svc( $id, 'domain' ) . ' venció el ' . self::date( $dd ) . ' sin pagar su renovación (va con el hosting en el pedido #' . $renewal->get_id() . ').' );
			self::alert_admin( 'dominio sin renovar: ' . self::svc( $id, 'domain' ), 'El dominio ' . self::svc( $id, 'domain' ) . ' venció el ' . self::date( $dd ) . ' y su renovación (junto con el hosting, pedido #' . $renewal->get_id() . ') no se pagó.' . "\n\n" . self::order_link( $renewal ) );
		}
		// Reactivado a mano por el administrador: se respeta su prórroga (visible en Servicios de hosting).
		if ( (int) self::svc( $id, 'reprieve_until' ) > $now ) return;
		if ( 'active' === $status && $now > $expires + ( $hosting ? self::grace_for( $period, $grace ) : $grace ) * DAY_IN_SECONDS ) {
			if ( self::svc( $id, 'webuzo_user' ) ) { self::svc_set( $id, array( 'suspend_reason' => 'auto', 'suspend_for' => $expires ) ); self::enqueue( self::HOOK_SUSPEND, $id ); }
			else { self::svc_set( $id, array( 'status' => 'expired' ) ); self::svc_log( $id, 'Vencido sin pago (dominio sin hosting).' ); }
		}
	}

	/**
	 * El dominio de un servicio con hosting que vence en otra fecha (siempre anual): su pedido de renovación, sus avisos a
	 * 30 y 7 días y, si no se paga, el aviso al administrador (el hosting pagado no se suspende por el dominio).
	 */
	private static function check_domain( $id, $now, $grace, $due ) {
		$r = self::domain_renewal_order( $id );
		if ( ! $r || self::renewal_confirmed( $r ) ) return;
		$days = (int) floor( ( $due - $now ) / DAY_IN_SECONDS );
		$sent = self::svc( $id, 'domain_notices' ); $sent = is_array( $sent ) && (int) ( $sent['cycle'] ?? 0 ) === $due ? $sent : array( 'cycle' => $due, 'sent' => array() );
		$type = '';
		if ( $days > 7 && ! in_array( '30', $sent['sent'], true ) ) $type = '30';
		elseif ( $days <= 7 && $days >= 0 && ! in_array( '7', $sent['sent'], true ) ) $type = '7';
		if ( $type ) {
			$sent['sent'][] = $type; self::svc_set( $id, array( 'domain_notices' => $sent ) );
			$ok = self::send_renewal( $r, $id, $type, 'domain', $due );
			$r->add_order_note( 'DIGITALÍSIMO Hosting: aviso de vencimiento del dominio (' . $type . ' días) ' . ( $ok ? 'enviado' : 'no enviado (correo desactivado o falló)' ) . ' a ' . self::svc( $id, 'email' ) . '.' );
		}
		if ( $now > $due + $grace * DAY_IN_SECONDS && (int) self::svc( $id, 'domain_lapsed' ) !== $due ) {
			self::svc_set( $id, array( 'domain_lapsed' => $due ) );
			self::svc_log( $id, 'El dominio ' . self::svc( $id, 'domain' ) . ' venció el ' . self::date( $due ) . ' sin pagar su renovación.' );
			self::alert_admin( 'dominio sin renovar: ' . self::svc( $id, 'domain' ), 'El dominio ' . self::svc( $id, 'domain' ) . ' venció el ' . self::date( $due ) . ' y su renovación #' . $r->get_id() . ' no se pagó. El hosting sigue activo mientras esté pagado.' . "\n\n" . self::order_link( $r ) );
		}
	}

	/** $what: hosting (el plan, con su periodo) o domain (el dominio, un año). */
	private static function send_renewal( $order, $id, $type, $what = 'hosting', $expires = 0 ) {
		$email = self::email( 'Digitalisimo_Hosting_Email_Renewal' );
		if ( ! $email || ! $email->is_enabled() ) return false;
		$hosting = 'domain' !== $what && self::has_hosting( $id );
		$grace = (int) ( Digitalisimo_Hosting::settings()['grace_days'] ?? 7 );
		return (bool) $email->trigger( $order, array( 'type' => $type, 'what' => $hosting ? 'hosting' : 'domain', 'with_domain' => $hosting && ! empty( self::order_parts( $order, $id )['domain'] ), 'period' => $hosting ? Digitalisimo_Hosting_Products::period_label( self::service_period( $id ) ) : '', 'domain' => (string) self::svc( $id, 'domain' ), 'expires' => $expires ? (int) $expires : (int) self::svc( $id, 'expires' ), 'pay_url' => $order->needs_payment() ? $order->get_checkout_payment_url() : '', 'grace' => $hosting ? self::grace_for( self::service_period( $id ), $grace ) : ( 'domain' === $what ? 0 : $grace ) ) );
	}

	/** ¿El pedido trae la línea del producto (o variación) $pid? */
	private static function order_has( $order, $pid ) {
		foreach ( $order->get_items() as $row ) if ( (int) $pid && ( Digitalisimo_Hosting_Products::item_id( $row ) === (int) $pid || (int) $row->get_product_id() === (int) $pid ) ) return true;
		return false;
	}

	/**
	 * Qué renueva el pedido: lo anotado al crearlo o, en un pedido de antes de esta versión, el hosting (con el periodo de
	 * la variación que trae, nunca un año fijo) y el dominio si trae su línea.
	 */
	public static function order_parts( $order, $id ) {
		$p = $order->get_meta( self::META_PARTS );
		if ( is_array( $p ) && isset( $p['hosting'] ) ) return array( 'hosting' => ! empty( $p['hosting'] ), 'domain' => ! empty( $p['domain'] ), 'period' => Digitalisimo_Hosting_Products::period_months( $p['period'] ?? 12 ) ? (int) $p['period'] : 12 );
		$period = 12;
		foreach ( $order->get_items() as $row ) { $iid = Digitalisimo_Hosting_Products::item_id( $row ); if ( 'hosting' === Digitalisimo_Hosting_Products::kind( $iid ) ) { $period = Digitalisimo_Hosting_Products::period( $iid ); break; } }
		return array( 'hosting' => self::has_hosting( $id ), 'domain' => self::order_has( $order, (int) self::svc( $id, 'domain_product' ) ), 'period' => $period );
	}

	/** Un pedido de renovación con los productos y precios vigentes (la variación del mismo periodo, con su precio). */
	private static function new_renewal( $id, array $products, array $parts, $label ) {
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
		$order->update_meta_data( self::META_PARTS, $parts );
		$order->calculate_totals();
		$order->save();
		$order->add_order_note( 'DIGITALÍSIMO Hosting: renovación de ' . self::svc( $id, 'domain' ) . ': ' . $label . '.' );
		return $order;
	}

	/** El producto del servicio, o null con el aviso (ya no existe). */
	private static function renewal_product( $id, $pid ) {
		$product = wc_get_product( (int) $pid );
		if ( ! $product ) { self::svc_log( $id, 'No se creó el pedido de renovación: el producto #' . (int) $pid . ' ya no existe.' ); self::alert_admin( 'renovación sin producto', 'El servicio ' . self::svc( $id, 'domain' ) . ' vence pronto y su producto #' . (int) $pid . ' ya no existe: crea su pedido de renovación a mano.' ); }
		return $product ? $product : null;
	}

	/**
	 * El pedido de renovación pendiente del hosting del servicio (lo crea con los productos y precios vigentes): el plan
	 * con su periodo y, si el dominio vence en la misma fecha (o el servicio es sólo el dominio), también el dominio.
	 */
	public static function renewal_order( $id ) {
		$existing = (int) self::svc( $id, 'renewal_order' );
		if ( $existing ) {
			$o = wc_get_order( $existing );
			if ( $o && ! in_array( $o->get_status(), array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) return $o;
		}
		$hosting = self::has_hosting( $id );
		$expires = (int) self::svc( $id, 'expires' );
		$dd = self::domain_due( $id );
		// El dominio entra con el hosting sólo si vence en la misma fecha (si no, tiene su propio pedido). Nunca se cobra dos
		// veces: un pedido vivo sólo del dominio que ya se pagó se respeta (éste no lo lleva); uno SIN pagar (pendiente o
		// fallido) se cancela y el dominio se cobra aquí, junto con el hosting (segunda revisión, N4).
		$same = ! $hosting || ( $dd && abs( $dd - $expires ) < 2 * DAY_IN_SECONDS );
		$solo = self::live_order( (int) self::svc( $id, 'domain_renewal_order' ) );
		$absorb = $hosting && $same && $solo && ! self::renewal_confirmed( $solo ) && in_array( $solo->get_status(), array( 'pending', 'failed' ), true );
		$with_domain = $same && ( ! $solo || $absorb );
		$products = array();
		$period = $hosting ? self::service_period( $id ) : 12;
		foreach ( array( 'hosting_product', 'domain_product' ) as $key ) {
			$pid = (int) self::svc( $id, $key );
			if ( ! $pid || ( 'domain_product' === $key && ! $with_domain ) ) continue;
			// Una transferencia que nunca llegó a la cuenta: no se cobra la renovación de un dominio que no podemos renovar.
			if ( 'domain_product' === $key && self::svc( $id, 'transfer_pending' ) ) {
				if ( (int) self::svc( $id, 'transfer_renew_alert' ) !== (int) self::svc( $id, 'expires' ) ) {
					self::svc_set( $id, array( 'transfer_renew_alert' => (int) self::svc( $id, 'expires' ) ) );
					self::svc_log( $id, 'Renovación sin el dominio: la transferencia de ' . self::svc( $id, 'domain' ) . ' no ha terminado.' );
					self::alert_admin( 'transferencia sin terminar al renovar', 'El servicio ' . self::svc( $id, 'domain' ) . ' vence pronto y la transferencia de su dominio no ha llegado a la cuenta de ' . Digitalisimo_Hosting_Registrars::label( Digitalisimo_Hosting_Registrars::of_service( $id ) ) . ': la renovación no cobra el dominio. Termina la transferencia o renuévalo a mano.' );
				}
				continue;
			}
			$product = self::renewal_product( $id, $pid );
			if ( ! $product ) return null;
			// El periodo que se cobra es el de la variación de hoy (su precio corresponde a ese periodo).
			if ( 'hosting_product' === $key && 'hosting' === Digitalisimo_Hosting_Products::kind( $pid ) ) $period = Digitalisimo_Hosting_Products::period( $pid );
			$products[ $key ] = $product;
		}
		if ( ! $products ) return null;
		$parts = array( 'hosting' => $hosting, 'domain' => isset( $products['domain_product'] ), 'period' => $period );
		$label = ( $hosting ? 'hosting ' . strtolower( Digitalisimo_Hosting_Products::period_label( $period ) ) : '' ) . ( $parts['domain'] ? ( $hosting ? ' y ' : '' ) . 'dominio por un año' : '' ) . ' (vence el ' . self::date( $expires ) . ')';
		$order = self::new_renewal( $id, $products, $parts, $label );
		if ( ! $order ) return null;
		self::svc_set( $id, array( 'renewal_order' => $order->get_id() ) );
		self::svc_log( $id, 'Pedido de renovación #' . $order->get_id() . ' creado.' );
		if ( $absorb && $parts['domain'] ) {
			$solo->update_status( 'cancelled', 'DIGITALÍSIMO Hosting: el dominio ' . self::svc( $id, 'domain' ) . ' vence el mismo día que el hosting: se cobra junto con él en la renovación #' . $order->get_id() . '. Este pedido ya no aplica.' );
			self::svc_set( $id, array( 'domain_renewal_order' => 0 ) );
			$order->add_order_note( 'DIGITALÍSIMO Hosting: incluye el dominio, que tenía su propio pedido #' . $solo->get_id() . ' sin pagar (se canceló).' );
			self::svc_log( $id, 'El pedido del dominio #' . $solo->get_id() . ' (sin pagar) se canceló: el dominio va en la renovación #' . $order->get_id() . '.' );
		}
		return $order;
	}

	/** Un pedido de renovación que sigue vivo: sin pagar o pagado y aún sin aplicar. null si no. */
	private static function live_order( $oid ) {
		$o = $oid ? wc_get_order( (int) $oid ) : null;
		if ( ! $o || in_array( $o->get_status(), array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) return null;
		$s = (array) $o->get_meta( self::META_RENEW );
		return 'completed' === $o->get_status() && ! empty( $s['done'] ) ? null : $o;
	}

	/**
	 * Renovado el dominio con $paid: el otro pedido de renovación que también lo cobraba y sigue sin pagar ya no aplica (el
	 * pedido sólo del dominio se cancela; del combinado se quita la línea del dominio).
	 */
	private static function drop_other_domain_order( $id, $paid ) {
		foreach ( array( 'renewal_order', 'domain_renewal_order' ) as $slot ) {
			$o = wc_get_order( (int) self::svc( $id, $slot ) );
			if ( ! $o || $o->get_id() === $paid->get_id() || ! in_array( $o->get_status(), array( 'pending', 'failed', 'on-hold' ), true ) || self::renewal_confirmed( $o ) ) continue;
			$parts = self::order_parts( $o, $id );
			if ( empty( $parts['domain'] ) ) continue;
			if ( empty( $parts['hosting'] ) ) {
				$o->update_status( 'cancelled', 'DIGITALÍSIMO Hosting: el dominio ' . self::svc( $id, 'domain' ) . ' ya se renovó con el pedido #' . $paid->get_id() . '; esta renovación no aplica.' );
				self::svc_set( $id, array( $slot => 0 ) );
				continue;
			}
			foreach ( $o->get_items() as $item_id => $row ) {
				if ( Digitalisimo_Hosting_Products::item_id( $row ) !== (int) self::svc( $id, 'domain_product' ) || ! method_exists( $o, 'remove_item' ) ) continue;
				$o->remove_item( $item_id );
				$o->update_meta_data( self::META_PARTS, array( 'domain' => false ) + $parts );
				$o->calculate_totals(); $o->save();
				$o->add_order_note( 'DIGITALÍSIMO Hosting: se quitó el dominio de esta renovación: ya se renovó con el pedido #' . $paid->get_id() . '.' );
			}
		}
	}

	/** El pedido de renovación del dominio cuando vence en otra fecha que el hosting (sólo el dominio, un año). */
	public static function domain_renewal_order( $id ) {
		$existing = (int) self::svc( $id, 'domain_renewal_order' );
		if ( $existing ) {
			$o = wc_get_order( $existing );
			if ( $o && ! in_array( $o->get_status(), array( 'cancelled', 'failed', 'refunded', 'trash' ), true ) ) return $o;
		}
		// Un pedido combinado vivo que ya cobra el dominio: no se crea otro.
		$combined = self::live_order( (int) self::svc( $id, 'renewal_order' ) );
		if ( $combined && ! empty( self::order_parts( $combined, $id )['domain'] ) ) return null;
		$product = self::renewal_product( $id, (int) self::svc( $id, 'domain_product' ) );
		if ( ! $product ) return null;
		$order = self::new_renewal( $id, array( 'domain_product' => $product ), array( 'hosting' => false, 'domain' => true, 'period' => 12 ), 'dominio por un año (vence el ' . self::date( self::domain_due( $id ) ) . ')' );
		if ( ! $order ) return null;
		self::svc_set( $id, array( 'domain_renewal_order' => $order->get_id() ) );
		self::svc_log( $id, 'Pedido de renovación del dominio #' . $order->get_id() . ' creado.' );
		return $order;
	}

	/** Una renovación sólo del dominio (vence en otra fecha que el hosting): un año más, sin tocar el hosting. */
	private static function run_renew_domain( $order, array $state, $id ) {
		try {
			$dstop = self::renew_domain( $order, $state, $id, 1, empty( $state['manual'] ) );
			self::refresh( $id );
			$halt = 'cancelled' === self::svc( $id, 'status' ) ? 'cancelled' : ( self::on() ? '' : 'off' );
			if ( $halt ) { self::interrupted( $order, $state, $halt, $id, true ); return; }
			if ( in_array( self::db_order_status( $order ), self::DEAD, true ) ) { self::interrupted( $order, $state, 'dead', $id, true ); return; }
			if ( $dstop ) {
				self::svc_set( $id, array( 'domain_pending_order' => $order->get_id() ) );
				self::fail( $order, $state, $dstop, self::HOOK_RENEW, $order->get_id(), $id );
				return;
			}
			// Sólo con la renovación confirmada por el registrador se mueve el vencimiento del dominio.
			if ( empty( $state['extended'] ) ) {
				$od = self::domain_due( $id );
				$nd = (int) strtotime( '+1 year', $od ? $od : time() );
				$state['extended'] = $nd;
				$order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::svc_set( $id, array( 'domain_expires' => $nd, 'domain_renewal_order' => 0, 'domain_notices' => array(), 'domain_lapsed' => 0 ) );
				self::svc_log( $id, 'Dominio renovado con el pedido #' . $order->get_id() . ' hasta el ' . self::date( $nd ) . '.' );
			}
			self::drop_other_domain_order( $id, $order );
			self::svc_set( $id, array( 'domain_pending_order' => 0 ) );
			$state['done'] = time(); unset( $state['attempts'], $state['blocked_noted'] );
			$order->update_meta_data( self::META_RENEW, $state ); $order->save();
			$email = self::email( 'Digitalisimo_Hosting_Email_Renewal' );
			if ( $email && $email->is_enabled() ) $email->trigger( $order, array( 'type' => 'renewed', 'what' => 'domain', 'domain' => (string) self::svc( $id, 'domain' ), 'expires' => (int) $state['extended'], 'pay_url' => '', 'grace' => 0 ) );
			if ( in_array( self::db_order_status( $order ), array( 'processing', 'on-hold' ), true ) ) $order->update_status( 'completed', 'DIGITALÍSIMO Hosting: dominio renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
			else $order->add_order_note( 'DIGITALÍSIMO Hosting: dominio renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
		} catch ( Throwable $e ) {
			self::fail( $order, $state, array( 'kind' => 'api', 'message' => 'Error interno: ' . $e->getMessage() ), self::HOOK_RENEW, $order->get_id(), $id );
		}
	}

	public static function run_renew( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		$id = (int) $order->get_meta( self::META_RENEWAL );
		if ( ! $id || ! self::is_service( $id ) ) return;
		$state = (array) $order->get_meta( self::META_RENEW );
		if ( ! empty( $state['done'] ) || ! self::may_run( $order, $state ) ) return;
		if ( 'cancelled' === self::svc( $id, 'status' ) ) {
			if ( self::note_once( $order, 'cancelled', 'el servicio ' . self::svc( $id, 'domain' ) . ' está dado de baja: esta renovación NO se aplica. Reembólsala si se pagó.' ) ) self::alert_admin( 'renovación de un servicio dado de baja', 'La renovación #' . $order->get_id() . ' de ' . self::svc( $id, 'domain' ) . ' no se aplicó: el servicio está dado de baja. Reembólsala si se pagó.' . "\n\n" . self::order_link( $order ) );
			return;
		}
		// La cuenta de Webuzo es hoy de un servicio más nuevo del mismo dominio (recontratado): éste no renueva nada.
		if ( ! self::owns_account( $id ) ) { self::superseded_paid( $order, $id ); return; }
		$state = self::take_retry( $order, $state, self::META_RENEW );
		$parts = self::order_parts( $order, $id );
		// Sólo el dominio de un servicio con hosting (vencen en fechas distintas): su propio camino, sin tocar el hosting.
		if ( empty( $parts['hosting'] ) && self::has_hosting( $id ) ) { self::run_renew_domain( $order, $state, $id ); return; }
		try {
			// El dominio y el hosting por separado: si el dominio no se puede renovar (saldo, costo, error de Namecheap), el
			// cliente que pagó igual recupera su hosting y su periodo; sólo el dominio queda en espera con aviso.
			$hosting = self::has_hosting( $id );
			// Sin hosting, el servicio es el dominio: se renuevan los años pagados (12, 24 o 36 meses).
			$years = $hosting ? 1 : max( 1, intdiv( (int) $parts['period'], 12 ) );
			// Un pago manual lo decidió el dueño (cobró por otro lado): sin el freno de pérdida contra el precio del pedido.
			$dstop = ! empty( $parts['domain'] ) ? self::renew_domain( $order, $state, $id, $years, empty( $state['manual'] ) ) : null;
			// Baja o venta apagada mientras corría: no se extiende ni se reactiva nada (lo ya cobrado se avisa).
			self::refresh( $id );
			$halt = 'cancelled' === self::svc( $id, 'status' ) ? 'cancelled' : ( self::on() ? '' : 'off' );
			if ( $halt ) { self::interrupted( $order, $state, $halt, $id, true ); return; }
			$stop = null;
			$manual = 'manual' === self::svc( $id, 'suspend_reason' );
			if ( ! $stop && $manual && 'suspended' === self::svc( $id, 'status' ) && empty( $state['manual_noted'] ) ) { $state['manual_noted'] = 1; $order->add_order_note( 'DIGITALÍSIMO Hosting: la cuenta está suspendida a mano; el pago no la reactiva (usa «Reactivar» en Servicios de hosting).' ); }
			if ( ! $manual && self::svc( $id, 'webuzo_user' ) && empty( $state['unsuspended'] ) ) {
				// Pagado: se reconcilia con la lista REAL de Webuzo (no con el estado guardado): si allá está suspendida, se
				// reactiva (sin skip, confirmado con la lista). Si una orden de suspender viajaba, se mira otra vez después.
				$state['unsuspended'] = time(); $order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::svc_set( $id, array( 'last_renewal_order' => $order->get_id() ) );
				self::enqueue( self::HOOK_RECONCILE, $id );
				if ( time() - (int) self::svc( $id, 'suspend_in_flight' ) < DAY_IN_SECONDS ) { self::enqueue( self::HOOK_RECONCILE, $id, 600, true ); self::svc_set( $id, array( 'suspend_in_flight' => 0 ) ); }
			}
			if ( $stop ) { self::fail( $order, $state, $stop, self::HOOK_RENEW, $order->get_id(), $id ); return; }
			// Un servicio que es sólo el dominio vence con el dominio: sin la renovación confirmada no se mueve nada.
			if ( ! $hosting && $dstop ) {
				self::svc_set( $id, array( 'domain_pending_order' => $order->get_id() ) );
				self::fail( $order, $state, $dstop, self::HOOK_RENEW, $order->get_id(), $id );
				return;
			}
			if ( empty( $state['extended'] ) ) {
				// Lo recién leído manda: un servicio dado de baja nunca vuelve a «activo» ni gana un año.
				// cancel_suspension() escribe en el servicio: va antes de la relectura, no entre ella y la escritura final.
				self::cancel_suspension( $id, 'renovado con el pedido #' . $order->get_id() );
				self::refresh( $id );
				$cur = (string) self::svc( $id, 'status' );
				if ( 'cancelled' === $cur ) { self::interrupted( $order, $state, 'cancelled', $id, true ); return; }
				if ( in_array( self::db_order_status( $order ), self::DEAD, true ) ) { self::interrupted( $order, $state, 'dead', $id, true ); return; }
				$to = $manual ? $cur : ( 'suspended' === $cur && self::svc( $id, 'webuzo_user' ) ? 'suspended' : 'active' );
				// Condicional: sólo si el servicio sigue en el estado leído (una baja entre medias gana y no se extiende).
				if ( ! self::set_status_if( $id, $cur, $to ) ) { self::interrupted( $order, $state, 'cancelled', $id, true ); return; }
				$old = (int) self::svc( $id, 'expires' );
				// El vencimiento del dominio se lee ANTES de mover `expires` (un servicio de antes lo lee de ahí): queda fijo en
				// el servicio y en el pedido, también para un reintento posterior del dominio (segunda revisión, N1).
				self::fill_domain_expires( $id );
				$state['domain_from'] = self::domain_due( $id );
				// El hosting suma su periodo (el de la variación que se pagó: un mes, tres, seis, uno, dos o tres años); sin
				// hosting, el servicio es el dominio y suma un año.
				$period = $hosting ? (int) $parts['period'] : 12 * $years;
				$new = self::add_months( $old ? $old : time(), $period );
				$extra = $hosting ? array( 'period' => $period ) : array();
				// Sin hosting, el vencimiento ES el del dominio (ya renovado: $dstop es null aquí).
				if ( ! $hosting && ! empty( $parts['domain'] ) ) { $extra += array( 'domain_expires' => $new, 'domain_lapsed' => 0 ); $state['domain_extended'] = $new; }
				$state['extended'] = $new;
				$order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::svc_set( $id, $extra + ( $manual ? array( 'expires' => $new, 'renewal_order' => 0, 'notices' => array(), 'error' => '', 'reprieve_until' => 0 ) : array( 'expires' => $new, 'renewal_order' => 0, 'last_renewal_order' => $order->get_id(), 'notices' => array(), 'error' => '', 'suspend_reason' => '', 'suspend_for' => 0, 'reprieve_until' => 0 ) ) );
				self::svc_log( $id, 'Renovado con el pedido #' . $order->get_id() . ' hasta el ' . self::date( $new ) . '.' );
			}
			// El dominio de un servicio con hosting: su vencimiento sólo se mueve cuando el registrador confirmó la renovación
			// (también en un reintento posterior), y la otra renovación del dominio que siga sin pagar ya no aplica.
			if ( ! $dstop && ! empty( $parts['domain'] ) && $hosting && empty( $state['domain_extended'] ) ) {
				$od = ! empty( $state['domain_from'] ) ? (int) $state['domain_from'] : self::domain_due( $id );
				$state['domain_extended'] = (int) strtotime( '+1 year', $od ? $od : time() );
				$order->update_meta_data( self::META_RENEW, $state ); $order->save();
				self::svc_set( $id, array( 'domain_expires' => $state['domain_extended'], 'domain_lapsed' => 0 ) );
			}
			if ( ! $dstop && ! empty( $parts['domain'] ) ) self::drop_other_domain_order( $id, $order );
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
			if ( in_array( self::db_order_status( $order ), array( 'processing', 'on-hold' ), true ) ) $order->update_status( 'completed', 'DIGITALÍSIMO Hosting: renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
			else $order->add_order_note( 'DIGITALÍSIMO Hosting: renovado hasta el ' . self::date( (int) $state['extended'] ) . '.' );
		} catch ( Throwable $e ) {
			self::fail( $order, $state, array( 'kind' => 'api', 'message' => 'Error interno: ' . $e->getMessage() ), self::HOOK_RENEW, $order->get_id(), $id );
		}
	}

	/**
	 * ¿Terminó la transferencia hecha a mano? Cuando el dominio ya está en la cuenta de Namecheap (sólo lectura,
	 * getInfo), el servicio lo da por registrado (se renueva con él), deja de estar pendiente y el código EPP se borra
	 * del pedido. Lo revisa la revisión diaria y la renovación.
	 */
	public static function transfer_settled( $id ) {
		if ( ! self::svc( $id, 'transfer_pending' ) || self::svc( $id, 'domain_registered' ) ) return false;
		$nc = Digitalisimo_Hosting_Registrars::make( Digitalisimo_Hosting_Registrars::of_service( $id ) );
		if ( ! $nc->configured() ) return false;
		$domain = (string) self::svc( $id, 'domain' );
		$info = $nc->info( $domain );
		if ( 'ok' !== $info['kind'] || empty( $info['ours'] ) ) return false;
		self::svc_set( $id, array( 'transfer_pending' => 0, 'domain_registered' => 1, 'registrar' => $nc->id() ) );
		self::svc_log( $id, 'Transferencia de ' . $domain . ' terminada: el dominio ya está en la cuenta de ' . $nc->label() . '.' );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) self::svc( $id, 'order' ) ) : null;
		if ( $order ) {
			// Sólo el código de ESTE dominio: el de otra transferencia del mismo pedido sigue haciendo falta (N3).
			$key = class_exists( 'Digitalisimo_Hosting_Checkout' ) ? Digitalisimo_Hosting_Checkout::META_EPP : '_digitalisimo_epp';
			$epp = $order->get_meta( $key );
			if ( is_array( $epp ) ) { unset( $epp[ $domain ] ); if ( $epp ) $order->update_meta_data( $key, $epp ); else $order->delete_meta_data( $key ); }
			elseif ( $epp ) $order->delete_meta_data( $key );
			$order->add_order_note( 'DIGITALÍSIMO Hosting: transferencia de ' . $domain . ' terminada (ya está en la cuenta de ' . $nc->label() . '); el código EPP se borró del pedido.' );
			$order->save();
		}
		return true;
	}

	/**
	 * Renueva el dominio en su registrador una sola vez ($years años): antes de la primera llamada se anota su vencimiento;
	 * si un intento anterior llegó (el vencimiento ya es posterior), no se vuelve a cobrar. Un candado por servicio evita
	 * que dos pedidos (o dos tareas) lo renueven a la vez; sin el vencimiento del registrador no se renueva (no se sabría si
	 * un intento anterior llegó). $guard: el freno de pérdida contra lo cobrado (el pago manual lo decide el dueño).
	 */
	private static function renew_domain( $order, array &$state, $id, $years = 1, $guard = true ) {
		self::transfer_settled( $id );
		if ( ! self::svc( $id, 'domain_registered' ) || ! empty( $state['domain'] ) ) return null;
		$lock = 'digitalisimo_hosting_domain_renew_lock_' . (int) $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 10 * MINUTE_IN_SECONDS ) return self::stop( 'transient', 'Otra renovación del dominio ' . self::svc( $id, 'domain' ) . ' está en curso; se reintenta después.' );
			update_option( $lock, time(), false );
		}
		try {
			return self::renew_domain_locked( $order, $state, $id, max( 1, (int) $years ), $guard );
		} finally {
			delete_option( $lock );
		}
	}

	private static function renew_domain_locked( $order, array &$state, $id, $years, $guard ) {
		$domain = (string) self::svc( $id, 'domain' );
		// Siempre con el registrador que tiene el dominio (el del servicio), aunque hoy su extensión vaya con otro.
		$nc = Digitalisimo_Hosting_Registrars::make( Digitalisimo_Hosting_Registrars::of_service( $id ) );
		$rl = $nc->label();
		$info = $nc->info( $domain );
		if ( 'ok' !== $info['kind'] ) return self::stop( self::nc_kind( $info ), $info['message'] );
		if ( ! $info['ours'] ) return self::stop( 'api', 'El dominio ' . $domain . ' ya no está en la cuenta de ' . $rl . '; no se renovó.' );
		if ( (int) $info['expires'] <= 0 ) return self::stop( 'data', $rl . ' no informó cuándo vence ' . $domain . ': no se renovó, para no cobrarlo dos veces si un intento anterior ya llegó. Revísalo en ' . $rl . ' y renuévalo a mano o pulsa «Reintentar».' );
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
		foreach ( $order->get_items() as $row ) if ( Digitalisimo_Hosting_Products::item_id( $row ) === (int) self::svc( $id, 'domain_product' ) || (int) $row->get_product_id() === (int) self::svc( $id, 'domain_product' ) ) { $item = $row; break; }
		if ( ! $item ) return self::stop( 'data', 'El pedido de renovación no trae la línea del dominio ' . $domain . ': no se renovó (se cobraría sin cobrarle al cliente).' );
		$cost = (float) $price['value'] * $years;
		if ( $guard ) { $g = self::price_guard( $item, $cost, 'renovar ' . $domain, $order, $rl ); if ( $g ) return $g; }
		$balance = $nc->balance();
		if ( 'ok' !== $balance['kind'] ) return self::stop( self::nc_kind( $balance ), $balance['message'] );
		if ( $balance['value'] < $cost ) return self::stop( 'funds', sprintf( 'Saldo insuficiente en ' . $rl . ' para renovar %s: hay %s USD y cuesta %s USD. Recarga la cuenta y pulsa «Reintentar» en WooCommerce → Servicios de hosting.', $domain, number_format( $balance['value'], 2, '.', '' ), number_format( $cost, 2, '.', '' ) ) );
		$halt = self::may_spend( $id, $order );
		if ( $halt ) return $halt;
		$r = $nc->renew( $domain, $years );
		if ( 'ok' !== $r['kind'] ) return self::stop( self::nc_kind( $r ), $r['message'] );
		$state['domain'] = time(); $state['domain_years'] = $years; $order->update_meta_data( self::META_RENEW, $state ); $order->save();
		$order->add_order_note( 'DIGITALÍSIMO Hosting: dominio ' . $domain . ' renovado en ' . $rl . ' ' . ( 1 === $years ? 'un año' : $years . ' años' ) . ( '' !== (string) $r['charged'] ? ' (cargo ' . $r['charged'] . ' USD)' : '' ) . '.' );
		return null;
	}

	// ── Pago manual (pedido del propietario del 2026-11-27: «permite pago manual solo en admin para si me pagan por otro
	// lado o en persona, yo habilitarles el año y no se les suspenda») ──────────────────────────────────────────────

	/** Medios de un pago registrado a mano. */
	const MANUAL_METHODS = array( 'cash' => 'Efectivo', 'transfer' => 'Transferencia', 'deposit' => 'Depósito', 'other' => 'Otro' );
	/** Bitácora de pagos manuales del sitio (las últimas 200): quién, cuándo, servicio, medio, periodo, monto y pedido. */
	const MANUAL_LOG = 'digitalisimo_hosting_manual_payments';

	/** ¿Puede registrar pagos manuales? Sólo quien administra la tienda o el sitio (nunca un cliente). */
	public static function can_manual_payment() { return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ); }

	/**
	 * Lo que muestra el formulario: el periodo de fábrica, si conviene renovar el dominio (vence dentro del periodo
	 * pagado), su costo en el registrador que lo tiene y si el saldo alcanza (una consulta de sólo lectura).
	 */
	public static function manual_preview( $id, $months = 0 ) {
		$hosting = self::has_hosting( $id );
		$months = Digitalisimo_Hosting_Products::period_months( $months ) && ( $hosting || 0 === (int) $months % 12 ) ? (int) $months : ( $hosting ? self::service_period( $id ) : 12 );
		$expires = (int) self::svc( $id, 'expires' );
		$new = self::add_months( $expires ? $expires : time(), $months );
		$dd = self::domain_due( $id );
		$out = array( 'months' => $months, 'from' => $expires, 'to' => $new, 'domain_due' => $dd, 'renew_domain' => false, 'cost' => null, 'balance' => null, 'registrar' => '', 'warning' => '' );
		if ( ! $dd || ! self::svc( $id, 'domain_registered' ) ) return $out;
		// Sólo el dominio: siempre se renueva con el pago; con hosting, si vence dentro del periodo pagado.
		$out['renew_domain'] = ! $hosting || $dd <= $new;
		$reg = Digitalisimo_Hosting_Registrars::make( Digitalisimo_Hosting_Registrars::of_service( $id ) );
		$out['registrar'] = $reg->label();
		if ( ! $reg->configured() ) { $out['warning'] = 'Falta la cuenta de ' . $reg->label() . ': el dominio no se podrá renovar desde aquí.'; return $out; }
		$split = self::split( (string) self::svc( $id, 'domain' ) );
		$price = $reg->price( $split ? $split[1] : '', 'RENEW', 15 );
		$bal = $reg->balance( 15 );
		if ( 'ok' === $price['kind'] ) $out['cost'] = (float) $price['value'] * ( $hosting ? 1 : intdiv( $months, 12 ) );
		if ( 'ok' === $bal['kind'] ) $out['balance'] = (float) $bal['value'];
		if ( null !== $out['cost'] && null !== $out['balance'] && $out['balance'] + 0.005 < $out['cost'] ) $out['warning'] = 'El saldo de ' . $reg->label() . ' (' . number_format( $out['balance'], 2, '.', ',' ) . ' USD) no alcanza para renovar el dominio (' . number_format( $out['cost'], 2, '.', ',' ) . ' USD): recárgalo antes o el dominio quedará pendiente.';
		elseif ( null === $out['cost'] || null === $out['balance'] ) $out['warning'] = 'No pudimos consultar el costo o el saldo en ' . $reg->label() . ' ahora.';
		return $out;
	}

	/**
	 * Registra un pago hecho por otro lado (efectivo, transferencia, depósito…): marca pagado el pedido de renovación
	 * pendiente o crea uno ya pagado, extiende el vencimiento el periodo pagado, cancela los avisos y la suspensión de ese
	 * periodo, reactiva lo suspendido por falta de pago y, si se pide, renueva el dominio en su registrador (con las mismas
	 * guardas: nunca dos veces, con aviso si falla). $a: months, method, amount, note, renew_domain, renewed_elsewhere, key.
	 *
	 * Un servicio que es SÓLO el dominio se paga por años (12, 24 o 36 meses) y su vencimiento es el del registrador: el
	 * dominio se renueva por omisión (también por MCP) esos años, y el vencimiento sólo se mueve cuando el registrador lo
	 * confirma o cuando el administrador marca «ya lo renové en el registrador» (queda anotado). Sin una cosa ni la otra, el
	 * dominio queda pendiente con aviso (nunca en silencio) y «Reintentar» lo renueva.
	 *
	 * Idempotente: la misma clave ($a['key']; sin clave, los mismos datos en 10 minutos) no extiende dos veces; la clave se
	 * anota «en curso» con el candado puesto y antes de extender, así un corte a media tarea tampoco extiende dos veces.
	 *
	 * @return array|WP_Error
	 */
	public static function manual_payment( $id, array $a ) {
		if ( ! self::can_manual_payment() ) return new WP_Error( 'digitalisimo_hosting_forbidden', 'Registrar un pago manual exige administrar la tienda (manage_woocommerce) o el sitio (manage_options).', array( 'status' => 403 ) );
		$id = (int) $id;
		if ( ! self::is_service( $id ) ) return new WP_Error( 'digitalisimo_hosting_service', 'No existe ese servicio.', array( 'status' => 404 ) );
		if ( 'cancelled' === self::svc( $id, 'status' ) ) return new WP_Error( 'digitalisimo_hosting_service', 'El servicio está dado de baja: no se registra un pago.', array( 'status' => 400 ) );
		$hosting = self::has_hosting( $id );
		$months = (int) ( $a['months'] ?? 0 );
		if ( $months && ! Digitalisimo_Hosting_Products::period_months( $months ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'El periodo debe ser 1, 3, 6, 12, 24 o 36 meses.', array( 'status' => 400 ) );
		if ( ! $months ) $months = $hosting ? self::service_period( $id ) : 12;
		if ( ! $hosting && 0 !== $months % 12 ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'Este servicio es sólo el dominio: se paga por años (12, 24 o 36 meses), que es como lo renueva el registrador.', array( 'status' => 400 ) );
		$method = (string) ( $a['method'] ?? '' );
		if ( ! isset( self::MANUAL_METHODS[ $method ] ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'El medio debe ser efectivo (cash), transferencia (transfer), depósito (deposit) u otro (other).', array( 'status' => 400 ) );
		$raw_amount = is_scalar( $a['amount'] ?? null ) ? trim( str_replace( ',', '.', (string) $a['amount'] ) ) : '';
		if ( '' !== $raw_amount && ( ! is_numeric( $raw_amount ) || (float) $raw_amount < 0 ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'El monto debe ser un número (o quedar vacío).', array( 'status' => 400 ) );
		$amount = '' === $raw_amount ? null : round( (float) $raw_amount, 2 );
		$note = substr( sanitize_text_field( (string) ( $a['note'] ?? '' ) ), 0, 300 );
		$domain = (string) self::svc( $id, 'domain' );
		$dp_id = (int) self::svc( $id, 'domain_product' );
		$registered = $dp_id && self::svc( $id, 'domain_registered' );
		$want_elsewhere = ! empty( $a['renewed_elsewhere'] );
		$want_renew = array_key_exists( 'renew_domain', $a ) && null !== $a['renew_domain'] ? (bool) $a['renew_domain'] : null;
		$rl = Digitalisimo_Hosting_Registrars::label( Digitalisimo_Hosting_Registrars::of_service( $id ) );
		// Un dominio que aún no llegó a nuestra cuenta (transferencia sin terminar) no se renueva ni se da por renovado: un
		// servicio que es sólo ese dominio no tiene nada que extender (segunda revisión, menor).
		if ( $dp_id && ! $registered && ( ! $hosting || $want_elsewhere || $want_renew ) ) return new WP_Error( 'digitalisimo_hosting_domain_pending', 'El dominio ' . $domain . ' aún no está en la cuenta de ' . $rl . ' (la transferencia no ha terminado): no se puede renovar ni dar por renovado. ' . ( $hosting ? 'Registra el pago del hosting sin la opción del dominio.' : 'Registra el pago cuando la transferencia llegue.' ) . ' No se registró nada.', array( 'status' => 400 ) );
		// Sólo el dominio: UNA de las dos opciones, renovarlo aquí (lo de fábrica, también por MCP sin decir nada) o «ya lo
		// renové yo». Sin ninguna no se registra nada: completar el pedido lo renovaría solo (segunda revisión, R1).
		if ( ! $hosting && $want_elsewhere && true === $want_renew ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'Elige una sola opción para el dominio: renovarlo con su registrador o «ya lo renové yo en el registrador». No se registró nada.', array( 'status' => 400 ) );
		if ( ! $hosting && ! $want_elsewhere && false === $want_renew ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'Este servicio es sólo el dominio: elige «Renovar el dominio con su registrador» o «Ya lo renové yo en el registrador» (renewed_elsewhere). No se registró nada.', array( 'status' => 400 ) );
		$elsewhere = $registered && $want_elsewhere;
		// Sólo el dominio: se renueva por omisión (salvo que lo haya renovado él en el registrador).
		$renew_domain = $registered && ! $elsewhere && ( null !== $want_renew ? $want_renew : ! $hosting );
		$years = $hosting ? 1 : intdiv( $months, 12 );
		// Idempotencia: la clave del formulario o, sin ella, los mismos datos en 10 minutos.
		$key = '' !== (string) ( $a['key'] ?? '' ) ? 'k:' . substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $a['key'] ), 0, 64 ) : 'h:' . md5( wp_json_encode( array( $id, $months, $method, $amount, $note, $renew_domain, $elsewhere ) ) );
		$dup = self::manual_duplicate( $id, $key );
		if ( $dup ) return $dup;
		// Un candado por servicio: dos envíos a la vez no extienden dos veces.
		$lock = 'digitalisimo_hosting_manual_lock_' . $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 2 * MINUTE_IN_SECONDS ) return new WP_Error( 'digitalisimo_hosting_busy', 'Ya se está registrando un pago de este servicio: espera unos segundos.', array( 'status' => 409 ) );
			update_option( $lock, time(), false );
		}
		try {
			// Con el candado puesto: se relee (otra petición pudo registrarlo) y la clave queda «en curso» ANTES de extender.
			self::refresh( $id );
			$dup = self::manual_duplicate( $id, $key );
			if ( $dup ) return $dup;
			// El vencimiento del dominio se lee ANTES de mover `expires` (un servicio de antes lo lee de ahí; N1).
			self::fill_domain_expires( $id );
			$old = (int) self::svc( $id, 'expires' );
			$od_before = self::domain_due( $id );
			// «Ya lo renové yo»: se comprueba en el registrador (sólo lectura) que alcanza la fecha pagada, antes de escribir
			// nada; el vencimiento que se guarda es el del registrador (N5).
			$registrar_due = 0;
			if ( $elsewhere ) {
				$target = $hosting ? (int) strtotime( '+1 year', $od_before ? $od_before : time() ) : self::add_months( $old ? $old : time(), $months );
				$registrar_due = self::registrar_due( $id, $target );
				if ( is_wp_error( $registrar_due ) ) return $registrar_due;
			}
			$seen = self::svc( $id, 'manual_keys' ); $seen = is_array( $seen ) ? $seen : array();
			$seen[ $key ] = array( 't' => time(), 'state' => 'running' );
			if ( count( $seen ) > 50 ) $seen = array_slice( $seen, -50, null, true );
			self::svc_set( $id, array( 'manual_keys' => $seen ) );
			$user = function_exists( 'wp_get_current_user' ) && wp_get_current_user() ? (string) wp_get_current_user()->user_login : '';
			$user = '' !== $user ? $user : 'un administrador';
			// El pedido: la renovación pendiente sin pagar, o uno nuevo ya pagado.
			$order = null;
			$existing = wc_get_order( (int) self::svc( $id, 'renewal_order' ) );
			if ( $existing && in_array( $existing->get_status(), array( 'pending', 'on-hold', 'failed' ), true ) && ! self::renewal_confirmed( $existing ) ) $order = $existing;
			$domain_part = $renew_domain || $elsewhere || ! $hosting;
			$label = ( $hosting ? 'hosting ' . strtolower( Digitalisimo_Hosting_Products::period_label( $months ) ) : 'dominio por ' . ( 1 === $years ? 'un año' : $years . ' años' ) ) . ( $hosting && ( $renew_domain || $elsewhere ) ? ' y dominio por un año' : '' );
			$dp = (int) self::svc( $id, 'domain_product' );
			if ( ! $order ) {
				$products = array();
				$hp = $hosting ? (int) self::svc( $id, 'hosting_product' ) : 0;
				if ( $hp && wc_get_product( $hp ) ) $products['hosting_product'] = wc_get_product( $hp );
				if ( $domain_part && $dp && wc_get_product( $dp ) ) $products['domain_product'] = wc_get_product( $dp );
				$order = self::new_renewal( $id, $products, array( 'hosting' => $hosting, 'domain' => $domain_part, 'period' => $months ), $label . ' (pago manual)' );
				if ( ! $order ) return new WP_Error( 'digitalisimo_hosting_order', 'No se pudo crear el pedido del pago.', array( 'status' => 500 ) );
			} elseif ( $domain_part && $dp && ! self::order_has( $order, $dp ) ) {
				$item_id = $order->add_product( wc_get_product( $dp ), 1 );
				$item = $item_id ? $order->get_item( $item_id ) : null;
				if ( $item ) { $item->add_meta_data( 'Dominio', (string) self::svc( $id, 'domain' ), true ); $item->save(); }
				$order->calculate_totals();
			}
			$order->update_meta_data( self::META_PARTS, array( 'hosting' => $hosting, 'domain' => $domain_part, 'period' => $months ) );
			$entry = array( 't' => time(), 'user' => $user, 'service' => $id, 'domain' => (string) self::svc( $id, 'domain' ), 'method' => $method, 'months' => $months, 'amount' => $amount, 'note' => $note, 'order' => (int) $order->get_id() );
			$cur = (string) self::svc( $id, 'status' );
			$manual_susp = 'manual' === self::svc( $id, 'suspend_reason' );
			$state = (array) $order->get_meta( self::META_RENEW );
			$state = array_merge( $state, array( 'paid' => time(), 'manual' => $entry, 'unsuspended' => time() ) );
			$order->update_meta_data( self::META_RENEW, $state ); $order->save();
			// El dominio con su registrador (las mismas guardas que la renovación: nunca dos veces; aviso si falla).
			$domain_result = $registered ? 'skipped' : 'none';
			$dstop = null;
			if ( $renew_domain ) {
				$dstop = self::renew_domain( $order, $state, $id, $years, false );
				$domain_result = $dstop ? 'failed' : 'renewed';
			} elseif ( $elsewhere ) {
				$domain_result = 'elsewhere';
				$order->add_order_note( 'DIGITALÍSIMO Hosting: ' . $user . ' indicó que ya renovó ' . self::svc( $id, 'domain' ) . ' directamente en el registrador (' . ( 1 === $years ? 'un año' : $years . ' años' ) . '); se comprobó en ' . $rl . ': vence el ' . self::date( $registrar_due ) . '. No se pidió la renovación.' );
			}
			$domain_ok = in_array( $domain_result, array( 'renewed', 'elsewhere' ), true );
			// La extensión: el hosting, siempre; un servicio que es sólo el dominio, sólo con el dominio confirmado.
			$new = $old;
			if ( $hosting || $domain_ok ) {
				// Sólo el dominio y renovado por el administrador: vence lo que dice el registrador (comprobado arriba).
				$new = ! $hosting && 'elsewhere' === $domain_result ? (int) $registrar_due : self::add_months( $old ? $old : time(), $months );
				self::cancel_suspension( $id, 'pago manual registrado por ' . $user );
				$set = array( 'expires' => $new, 'renewal_order' => 0, 'last_renewal_order' => $order->get_id(), 'notices' => array(), 'error' => '', 'reprieve_until' => 0, 'manual_paid_until' => $new );
				if ( ! $manual_susp ) $set += array( 'suspend_reason' => '', 'suspend_for' => 0 );
				if ( ! $hosting ) $set += array( 'domain_expires' => $new, 'domain_lapsed' => 0, 'domain_pending_order' => 0 );
				self::svc_set( $id, $set );
				$state['extended'] = $new;
				if ( 'expired' === $cur || ( 'suspended' === $cur && ! self::svc( $id, 'webuzo_user' ) ) ) self::set_status_if( $id, $cur, 'active' );
				// Suspendido por falta de pago: la reconciliación con la lista real de Webuzo lo reactiva (como al pagar).
				if ( 'suspended' === $cur && self::svc( $id, 'webuzo_user' ) && ! $manual_susp ) self::enqueue( self::HOOK_RECONCILE, $id );
			}
			if ( $hosting && $domain_ok ) {
				// Con el vencimiento leído antes de mover `expires` (N1); renovado por el administrador, el del registrador (N5).
				$state['domain_extended'] = 'elsewhere' === $domain_result ? (int) $registrar_due : (int) strtotime( '+1 year', $od_before ? $od_before : time() );
				self::svc_set( $id, array( 'domain_expires' => $state['domain_extended'], 'domain_lapsed' => 0, 'domain_pending_order' => 0 ) );
			}
			if ( $domain_ok ) { self::drop_other_domain_order( $id, $order ); self::svc_set( $id, array( 'domain_renewal_order' => 0, 'domain_notices' => array() ) ); }
			// El dominio sin renovar (falló): pendiente, con aviso. (Sólo el dominio sin pedir la renovación ni marcar «ya lo
			// renové» ya no llega aquí: se rechaza arriba.)
			$pending_domain = 'failed' === $domain_result;
			if ( $pending_domain ) {
				$why = $dstop ? $dstop['message'] : 'no se pidió la renovación ni se indicó que ya la hiciste en el registrador';
				// Detenido por algo que no es la red (saldo, datos): espera «Reintentar»; completar el pedido no lo vuelve a
				// intentar solo (paid()). Sin respuesta (red), sí se reintenta solo.
				if ( $dstop && 'transient' !== $dstop['kind'] ) $state['stopped'] = $dstop['kind'];
				self::svc_set( $id, array( 'domain_pending_order' => $order->get_id() ) );
				$order->add_order_note( 'DIGITALÍSIMO Hosting: el pago manual quedó registrado, pero el dominio ' . self::svc( $id, 'domain' ) . ' NO se renovó: ' . $why . '. Queda pendiente: «Reintentar» en Servicios de hosting lo renueva.' );
				self::alert_admin( 'dominio sin renovar tras un pago manual: ' . self::svc( $id, 'domain' ), 'Registraste el pago manual del pedido #' . $order->get_id() . ', pero el dominio ' . self::svc( $id, 'domain' ) . ' no se renovó: ' . $why . ( $hosting ? '' : '. Como el servicio es sólo el dominio, su vencimiento no cambió' ) . ".\n\nPulsa «Reintentar» en WooCommerce → Servicios de hosting cuando lo resuelvas.\n\n" . self::order_link( $order ) );
			}
			if ( ! $pending_domain ) $state['done'] = time();
			$order->update_meta_data( self::META_RENEW, $state );
			$order->update_meta_data( '_digitalisimo_manual_payment', $entry );
			if ( method_exists( $order, 'set_payment_method' ) ) $order->set_payment_method( 'digitalisimo_manual' );
			if ( method_exists( $order, 'set_payment_method_title' ) ) $order->set_payment_method_title( 'Pago manual: ' . strtolower( self::MANUAL_METHODS[ $method ] ) );
			$order->save();
			$text = 'Pago manual registrado por ' . $user . ': ' . strtolower( self::MANUAL_METHODS[ $method ] ) . ', ' . $label . ( null !== $amount ? ', monto ' . number_format( $amount, 2, '.', ',' ) : '' ) . ( '' !== $note ? ' (' . $note . ')' : '' ) . ( $new !== $old ? '; vence el ' . self::date( $new ) : '; el vencimiento no cambió hasta renovar el dominio' ) . ( 'elsewhere' === $domain_result ? '; dominio renovado por el administrador en el registrador' : '' ) . ( $manual_susp ? '. La cuenta sigue suspendida a mano: usa «Reactivar» si corresponde' : '' ) . '.';
			$log = get_option( self::MANUAL_LOG, array() ); $log = is_array( $log ) ? $log : array();
			array_unshift( $log, $entry + array( 'from' => $old, 'to' => $new, 'domain_renewal' => $domain_result ) );
			update_option( self::MANUAL_LOG, array_slice( $log, 0, 200 ), false );
			self::svc_log( $id, $text . ' Pedido #' . $order->get_id() . '.' );
			$result = array( 'ok' => true, 'duplicate' => false, 'service' => $id, 'order' => (int) $order->get_id(), 'from' => self::date( $old ), 'expires' => self::date( $new ), 'months' => $months, 'method' => $method, 'amount' => $amount, 'domain_renewal' => $domain_result, 'message' => $text );
			$seen = self::svc( $id, 'manual_keys' ); $seen = is_array( $seen ) ? $seen : array();
			$seen[ $key ] = array( 't' => time(), 'state' => 'done', 'result' => $result );
			self::svc_set( $id, array( 'manual_keys' => $seen ) );
			// Al final: un error de otro plugin al completar el pedido ya no deshace nada de lo anterior.
			$order->update_status( 'completed', 'DIGITALÍSIMO Hosting: ' . $text );
			/**
			 * Gancho para la suscripción automática (Mercado Pago, en otra rama): un pago manual mueve el vencimiento; quien
			 * cobre solo debe mover también la fecha del siguiente cobro.
			 */
			if ( function_exists( 'do_action' ) ) do_action( 'digitalisimo_hosting_manual_payment', $id, $result + array( 'expires_ts' => $new, 'from_ts' => $old, 'user' => $user ) );
			return $result;
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * «Ya lo renové yo en el registrador» se comprueba (consulta de sólo lectura): el vencimiento real del dominio en su
	 * registrador debe alcanzar la fecha que se paga ($target; dos días de margen porque el registrador da la fecha en su
	 * hora). Devuelve ese vencimiento o un WP_Error que dice qué falta; nada se escribe (segunda revisión, N5).
	 */
	private static function registrar_due( $id, $target ) {
		$domain = (string) self::svc( $id, 'domain' );
		$nc = Digitalisimo_Hosting_Registrars::make( Digitalisimo_Hosting_Registrars::of_service( $id ) );
		$rl = $nc->label();
		$fail = function ( $why ) { return new WP_Error( 'digitalisimo_hosting_domain_not_renewed', $why . ' No se registró nada.', array( 'status' => 400 ) ); };
		if ( ! $nc->configured() ) return $fail( 'Falta la cuenta de ' . $rl . ': no se puede comprobar que ' . $domain . ' ya está renovado.' );
		$info = $nc->info( $domain, 15 );
		if ( 'ok' !== $info['kind'] ) return $fail( 'No se pudo comprobar en ' . $rl . ' que ' . $domain . ' ya está renovado (' . rtrim( (string) $info['message'], '. ' ) . '). Inténtalo de nuevo en unos minutos.' );
		if ( empty( $info['ours'] ) ) return $fail( 'El dominio ' . $domain . ' no está en la cuenta de ' . $rl . ': no se puede dar por renovado.' );
		$due = (int) ( $info['expires'] ?? 0 );
		if ( $due <= 0 ) return $fail( $rl . ' no informó cuándo vence ' . $domain . ': no se puede comprobar la renovación.' );
		if ( $due < (int) $target - 2 * DAY_IN_SECONDS ) return $fail( 'En ' . $rl . ' el dominio ' . $domain . ' vence el ' . self::date( $due ) . ', y con lo que se paga debería vencer el ' . self::date( (int) $target ) . ': la renovación en el registrador no alcanza. Renuévalo allá o elige «Renovar el dominio con su registrador».' );
		return $due;
	}

	/** El resultado de un pago ya registrado (o a medias) con esa clave, o null. */
	private static function manual_duplicate( $id, $key ) {
		$seen = self::svc( $id, 'manual_keys' ); $seen = is_array( $seen ) ? $seen : array();
		$prev = $seen[ $key ] ?? null;
		if ( ! $prev || ( 'k:' !== substr( $key, 0, 2 ) && time() - (int) ( $prev['t'] ?? 0 ) >= 10 * MINUTE_IN_SECONDS ) ) return null;
		if ( 'running' === ( $prev['state'] ?? '' ) ) return array( 'ok' => true, 'duplicate' => true, 'service' => (int) $id, 'expires' => self::date( (int) self::svc( $id, 'expires' ) ), 'message' => 'Este pago ya se empezó a registrar y quedó a medias (otra petición o un error): no se extendió otra vez. Revisa el servicio y su pedido.' );
		return array( 'ok' => true, 'duplicate' => true, 'message' => 'Este pago ya se había registrado (no se extendió otra vez).' ) + (array) ( $prev['result'] ?? array() );
	}

	// ── Suspender, reactivar, reenviar ──────────────────────────────────────────────────────────────────────────

	public static function run_suspend( $id ) { self::toggle( $id, true ); }
	public static function run_unsuspend( $id ) { self::toggle( $id, false ); }

	private static function toggle( $id, $suspend ) {
		// Con la venta apagada no se llama a Webuzo (ni lo que quedó en cola).
		if ( ! self::on() || ! self::is_service( $id ) || 'cancelled' === self::svc( $id, 'status' ) ) return;
		$user = (string) self::svc( $id, 'webuzo_user' );
		$status = (string) self::svc( $id, 'status' );
		if ( '' === $user || ( $suspend && 'suspended' === $status ) || ( ! $suspend && 'suspended' !== $status ) ) return;
		// Al ejecutarse (venga de la revisión diaria, de un reintento o de levantar una parada) se vuelve a comprobar que
		// sigue vencido y sin pagar; sólo la suspensión que el administrador pidió a mano no depende del pago.
		if ( $suspend && 'manual' !== self::svc( $id, 'suspend_reason' ) && ! self::overdue( $id ) ) { self::svc_set( $id, array( 'suspend_reason' => '', 'suspend_for' => 0, 'toggle_attempts' => 0 ) ); self::svc_log( $id, 'Suspensión cancelada: ya no está vencido sin pago.' ); return; }
		// Un servicio viejo del mismo dominio no toca la cuenta de uno más nuevo (recontratación).
		if ( ! self::owns_account( $id ) ) { self::mismatch( $id, 'La cuenta «' . $user . '» es hoy de un servicio más nuevo del mismo dominio' ); return; }
		$api = Digitalisimo_Hosting_Webuzo::from_settings();
		// Sólo si la cuenta sigue siendo la del dominio del servicio (el nombre de usuario se pudo heredar).
		$r = $suspend ? $api->suspend( $user, (string) self::svc( $id, 'domain' ) ) : $api->unsuspend( $user, (string) self::svc( $id, 'domain' ) );
		$hook = $suspend ? self::HOOK_SUSPEND : self::HOOK_UNSUSPEND;
		$manual = 'manual' === self::svc( $id, 'suspend_reason' );
		// Quién la suspendió: una marca «auto» sólo vale si el cliente de Webuzo vio la cuenta activa y mandó la orden
		// (aunque la respuesta se pierda, pudo aplicarse). Es lo único que el pago reactiva solo.
		// Se guarda el motivo que Webuzo mostraba justo antes (Webuzo conserva un motivo viejo al reactivar, p. ej.
		// «Bandwidth Overuse» en una cuenta activa): sólo un motivo que cambió después delata otra suspensión.
		if ( $suspend && ! empty( $r['sent'] ) && in_array( $r['kind'], array( 'ok', 'transient' ), true ) ) self::svc_set( $id, array( 'suspend_sent' => array( 't' => time(), 'reason' => $manual ? 'manual' : 'auto', 'seen' => (string) ( $r['seen_reason'] ?? '' ) ) ) );
		if ( 'already' === $r['kind'] ) {
			$pt = self::svc( $id, 'pending_toggle' );
			$sent = self::svc( $id, 'suspend_sent' );
			// Una orden nuestra anterior ya se aplicó (se reenviaba porque aún no se veía, o se perdió su respuesta después
			// de salir): es la nuestra. Sólo cuenta una marca de esta tanda, aún sin confirmar.
			$ours = $suspend && is_array( $sent ) && in_array( $sent['reason'] ?? '', array( 'auto', 'manual' ), true ) && empty( $sent['confirmed'] ) && time() - (int) ( $sent['t'] ?? 0 ) < 2 * DAY_IN_SECONDS;
			if ( $ours || ( ! $suspend && is_array( $pt ) && 'unsuspend' === ( $pt['op'] ?? '' ) ) ) { self::svc_set( $id, array( 'pending_toggle' => array() ) ); self::confirmed( $id, $suspend ); return; }
			if ( ! $suspend ) { self::svc_log( $id, $r['message'] . '.' ); self::confirmed( $id, false ); return; }
			// Ya estaba suspendida y no por el plugin (p. ej. a mano en el panel, por abuso): queda suspendida, con la marca
			// «foreign», y el pago nunca la reactiva sola.
			$text = 'La cuenta «' . $user . '» ya estaba suspendida en Webuzo y no la suspendió el plugin: no se mandó nada y el pago no la reactivará sola.';
			self::svc_set( $id, array( 'status' => 'suspended', 'suspended_at' => time(), 'pending_toggle' => array(), 'toggle_attempts' => 0, 'suspend_sent' => array( 't' => time(), 'reason' => 'foreign' ), 'error' => $manual ? '' : $text ) );
			self::svc_log( $id, $text );
			if ( ! $manual && ! self::svc( $id, 'foreign_suspend_noted' ) ) { self::svc_set( $id, array( 'foreign_suspend_noted' => time() ) ); self::alert_admin( 'suspendida fuera del plugin: ' . self::svc( $id, 'domain' ), $text . ' Si corresponde reactivarla, usa «Reactivar» en WooCommerce → Servicios de hosting.' ); }
			return;
		}
		if ( 'mismatch' === $r['kind'] ) { self::mismatch( $id, $r['message'] ); return; }
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
		if ( ! self::on() || ! self::is_service( $id ) ) return;
		$pt = self::svc( $id, 'pending_toggle' );
		if ( ! is_array( $pt ) || empty( $pt['op'] ) ) return;
		$suspend = 'suspend' === $pt['op'];
		$user = (string) self::svc( $id, 'webuzo_user' );
		$now = Digitalisimo_Hosting_Webuzo::from_settings()->status_of( $user, $r, (string) self::svc( $id, 'domain' ) );
		$checks = (int) ( $pt['checks'] ?? 0 ) + 1;
		if ( null === $now ) {
			if ( ! empty( $r['mismatch'] ) ) { self::mismatch( $id, 'La cuenta «' . $user . '» de Webuzo es hoy del dominio ' . $r['mismatch'] . ', no de ' . self::svc( $id, 'domain' ) ); return; }
			if ( 'blocked' === $r['kind'] || 'auth' === $r['kind'] ) { self::wait( self::HOOK_VERIFY, $id ); return; }
			if ( 'ok' === $r['kind'] ) { self::svc_set( $id, array( 'pending_toggle' => array(), 'error' => 'La cuenta «' . $user . '» no aparece en Webuzo.' ) ); self::alert_admin( 'cuenta no encontrada en Webuzo', 'La cuenta «' . $user . '» de ' . self::svc( $id, 'domain' ) . ' no aparece en la lista de Webuzo.' ); return; }
			if ( $checks < count( self::VERIFY_DELAYS ) ) { $pt['checks'] = $checks; self::svc_set( $id, array( 'pending_toggle' => $pt ) ); self::enqueue( self::HOOK_VERIFY, $id, self::VERIFY_DELAYS[ $checks ], true ); return; }
			// Se agotaron las confirmaciones sin poder leer la lista (red): se limpia y se avisa; nada queda colgado.
			$text = 'No se pudo confirmar en Webuzo si se ' . ( $suspend ? 'suspendió' : 'reactivó' ) . ' «' . $user . '» (sin respuesta de la lista de cuentas).';
			self::svc_set( $id, array( 'pending_toggle' => array(), 'error' => $text ) ); self::svc_log( $id, $text );
			self::alert_admin( 'sin confirmar en Webuzo: ' . self::svc( $id, 'domain' ), $text . ' Revísalo en Webuzo; la revisión diaria lo intentará otra vez si sigue vencido.' );
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
		if ( ! $suspend ) self::svc_set( $id, array( 'suspend_sent' => array(), 'foreign_suspend_noted' => 0 ) );
		else { $sent = self::svc( $id, 'suspend_sent' ); if ( is_array( $sent ) && $sent ) { $sent['confirmed'] = time(); self::svc_set( $id, array( 'suspend_sent' => $sent ) ); } }
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
		// Una orden de suspender en camino deja de esperarse; la reconciliación con la lista real decide después.
		$pt = self::svc( $id, 'pending_toggle' );
		if ( is_array( $pt ) && 'suspend' === ( $pt['op'] ?? '' ) ) { self::svc_set( $id, array( 'pending_toggle' => array(), 'suspend_in_flight' => time() ) ); $had = true; }
		$w = get_option( self::WAITING, array() );
		if ( is_array( $w ) && isset( $w[ self::HOOK_SUSPEND . ':' . (int) $id ] ) ) { unset( $w[ self::HOOK_SUSPEND . ':' . (int) $id ] ); update_option( self::WAITING, $w, false ); $had = true; }
		if ( $had ) self::svc_log( $id, 'Suspensión pendiente cancelada: ' . $why . '.' );
	}

	/** La cuenta de Webuzo ya no es la del dominio del servicio: no se toca nada y se avisa. */
	private static function mismatch( $id, $text ) {
		self::svc_set( $id, array( 'pending_toggle' => array(), 'error' => $text, 'toggle_attempts' => 0 ) ); self::svc_log( $id, $text . '. No se tocó.' );
		self::alert_admin( 'cuenta de Webuzo de otro dominio: ' . self::svc( $id, 'domain' ), $text . '. No se suspendió ni se reactivó nada. Si el servicio ya no existe, dalo de baja en WooCommerce → Servicios de hosting.' );
	}

	/**
	 * Reconciliación con la lista REAL de Webuzo tras un pago: si la cuenta está suspendida allá (aunque aquí diga
	 * activa, p. ej. porque la orden de suspender viajaba), se reactiva; si está activa, se corrige el estado guardado.
	 */
	public static function run_reconcile( $id ) {
		if ( ! self::is_service( $id ) || 'cancelled' === self::svc( $id, 'status' ) ) return;
		// Con la venta apagada la tarea no se pierde: queda anotada y se retoma al reencenderla (o en la revisión diaria).
		if ( ! self::on() ) { self::svc_set( $id, array( 'reconcile_pending' => time() ) ); return; }
		$user = (string) self::svc( $id, 'webuzo_user' );
		if ( '' === $user || 'manual' === self::svc( $id, 'suspend_reason' ) ) { self::svc_set( $id, array( 'reconcile_pending' => 0 ) ); return; }
		if ( ! self::owns_account( $id ) ) { self::svc_set( $id, array( 'reconcile_pending' => 0 ) ); return; }
		$api = Digitalisimo_Hosting_Webuzo::from_settings();
		$now = $api->configured() ? $api->status_of( $user, $r, (string) self::svc( $id, 'domain' ) ) : null;
		if ( ! $api->configured() ) $r = array( 'kind' => 'config', 'message' => 'Webuzo no está configurado (falta la URL, el usuario o la clave)' );
		if ( null === $now ) {
			if ( ! empty( $r['mismatch'] ) ) { self::svc_set( $id, array( 'reconcile_pending' => 0 ) ); self::mismatch( $id, 'La cuenta «' . $user . '» de Webuzo es hoy del dominio ' . $r['mismatch'] . ', no de ' . self::svc( $id, 'domain' ) ); return; }
			if ( 'blocked' === $r['kind'] || 'auth' === $r['kind'] ) { self::svc_set( $id, array( 'reconcile_pending' => time() ) ); self::wait( self::HOOK_RECONCILE, $id ); return; }
			if ( 'transient' === $r['kind'] ) {
				$n = (int) self::svc( $id, 'reconcile_attempts' ) + 1; self::svc_set( $id, array( 'reconcile_attempts' => $n, 'reconcile_pending' => time() ) );
				if ( $n < self::MAX_ATTEMPTS ) { self::enqueue( self::HOOK_RECONCILE, $id, self::BACKOFF[ min( $n - 1, count( self::BACKOFF ) - 1 ) ], true ); return; }
			}
			// No se pudo leer la lista (intentos agotados, Webuzo sin configurar, otra falla): el cliente pagó y podría
			// seguir suspendido. Queda el motivo en el servicio y se avisa; la revisión diaria lo vuelve a intentar.
			$missing = 'ok' === $r['kind'];
			$why = $missing ? 'la cuenta ya no existe en Webuzo (¿se borró?)' : ( '' !== trim( (string) $r['message'] ) ? rtrim( (string) $r['message'], '. ' ) : 'Webuzo no respondió (' . $r['kind'] . ')' );
			$text = 'Pagado, pero no se pudo comprobar en Webuzo si la cuenta «' . $user . '» sigue suspendida: ' . $why . '.';
			// Una cuenta que ya no existe no se reintenta cada día; lo demás sí (la revisión diaria hace de red).
			self::svc_set( $id, array( 'error' => $text, 'reconcile_attempts' => 0, 'reconcile_pending' => $missing ? 0 : time() ) );
			// Un aviso por motivo, no uno cada día.
			if ( self::svc( $id, 'reconcile_alerted' ) !== md5( $why ) ) {
				self::svc_set( $id, array( 'reconcile_alerted' => md5( $why ) ) ); self::svc_log( $id, $text );
				self::alert_admin( 'pagado y sin reactivar: ' . self::svc( $id, 'domain' ), $text . ( $missing ? ' Revísalo en Webuzo.' : ' Revísalo en Webuzo; la revisión diaria lo volverá a intentar.' ) );
			}
			return;
		}
		self::svc_set( $id, array( 'reconcile_attempts' => 0, 'reconcile_pending' => 0, 'reconcile_alerted' => '' ) );
		$sent = self::svc( $id, 'suspend_sent' );
		$ours = is_array( $sent ) && 'auto' === ( $sent['reason'] ?? '' );
		// El plugin nunca escribe un motivo de suspensión. Si el de Webuzo cambió desde que el plugin mandó la suya (Webuzo
		// puso el suyo, p. ej. por ancho de banda), esa suspensión ya no es la del plugin. Un motivo que ya estaba antes
		// (Webuzo lo conserva al reactivar) no cuenta. (En el panel se suspende con el motivo vacío: contra eso protege la
		// consulta previa «already».)
		$now_reason = (string) ( $r['row']['reason'] ?? '' );
		if ( 'suspended' === $now && '' !== $now_reason && ( ! is_array( $sent ) || $now_reason !== (string) ( $sent['seen'] ?? '' ) ) ) $ours = false;
		if ( 'suspended' === $now && ! self::overdue( $id ) && $ours ) {
			// La suspendió el plugin por falta de pago (o la orden viajaba): pagada, se reactiva.
			self::svc_set( $id, array( 'status' => 'suspended', 'unsuspend_reason' => 'paid' ) );
			self::svc_log( $id, 'Pagado y suspendido en Webuzo por falta de pago: se reactiva.' );
			self::toggle( $id, false );
		} elseif ( 'suspended' === $now && ! $ours ) {
			// Suspendida en Webuzo por otra vía (p. ej. el administrador, por abuso): no se toca.
			if ( 'suspended' !== self::svc( $id, 'status' ) || ! self::svc( $id, 'foreign_suspend_noted' ) ) {
				$why = (string) ( $r['row']['reason'] ?? '' );
				$text = 'La cuenta «' . $user . '» está suspendida en Webuzo, pero no la suspendió el plugin (' . ( '' !== $why ? 'motivo en Webuzo: «' . $why . '»' : 'quizá a mano, en el panel' ) . '): no se reactiva sola.';
				self::svc_set( $id, array( 'status' => 'suspended', 'error' => $text, 'foreign_suspend_noted' => time(), 'suspend_sent' => array( 't' => time(), 'reason' => 'foreign' ) ) ); self::svc_log( $id, $text );
				self::alert_admin( 'suspendida fuera del plugin: ' . self::svc( $id, 'domain' ), $text . ' Si corresponde, usa «Reactivar» en WooCommerce → Servicios de hosting.' );
			}
		} elseif ( 'suspended' !== $now && 'suspended' === self::svc( $id, 'status' ) && ! ( is_array( self::svc( $id, 'pending_toggle' ) ) && 'unsuspend' === ( self::svc( $id, 'pending_toggle' )['op'] ?? '' ) ) ) {
			$fields = array( 'status' => 'active', 'suspend_sent' => array(), 'suspended_at' => 0 );
			$text = 'Webuzo la muestra activa: se corrige el estado.';
			if ( self::overdue( $id ) ) {
				// Reactivada en el panel de Webuzo sin pagar: cuenta como una reactivación manual (misma prórroga), así la
				// revisión diaria no la vuelve a suspender enseguida, y la marca «auto» deja de valer.
				$fields['reprieve_until'] = time() + max( 7, (int) ( Digitalisimo_Hosting::settings()['grace_days'] ?? 7 ) ) * DAY_IN_SECONDS;
				$fields['suspend_reason'] = ''; $fields['suspend_for'] = 0;
				$text = 'La cuenta se reactivó fuera del plugin (en el panel de Webuzo) sin pago: se trata como reactivación manual, con prórroga hasta el ' . self::date( $fields['reprieve_until'] ) . '.';
			}
			self::svc_set( $id, $fields ); self::svc_log( $id, $text );
		}
	}

	/**
	 * ¿Es este servicio el dueño actual de su cuenta de Webuzo? Si se volvió a contratar el mismo dominio y la cuenta
	 * (mismo usuario) es de un servicio más nuevo que no está dado de baja, el servicio viejo no la toca.
	 */
	public static function owns_account( $id ) {
		$user = (string) self::svc( $id, 'webuzo_user' ); $domain = strtolower( (string) self::svc( $id, 'domain' ) );
		if ( '' === $user ) return true; // sin cuenta de Webuzo (sólo dominio): nada que disputar
		$mine = (int) self::svc( $id, 'created' );
		foreach ( self::services() as $sid ) {
			if ( $sid === (int) $id || 'cancelled' === self::svc( $sid, 'status' ) ) continue;
			if ( (string) self::svc( $sid, 'webuzo_user' ) !== $user || strtolower( (string) self::svc( $sid, 'domain' ) ) !== $domain ) continue;
			if ( (int) self::svc( $sid, 'created' ) > $mine || ( (int) self::svc( $sid, 'created' ) === $mine && $sid > (int) $id ) ) return false;
		}
		return true;
	}

	/**
	 * Una renovación pagada de un servicio desplazado (la cuenta de Webuzo es de uno más nuevo del mismo dominio): no se
	 * aplica, queda detenida (el vigilante no la vuelve a encolar) y se avisa para reembolsar, una vez por pedido, venga
	 * del pago, de la tarea o del vigilante (también si se pagó con la venta apagada).
	 */
	private static function superseded_paid( $order, $id ) {
		$text = 'la cuenta de Webuzo de ' . self::svc( $id, 'domain' ) . ' es hoy de un servicio más nuevo: esta renovación pagada NO se aplica. Reembólsala y da de baja el servicio viejo.';
		$state = (array) $order->get_meta( self::META_RENEW );
		if ( empty( $state['stopped'] ) ) { $state['stopped'] = 'superseded'; $order->update_meta_data( self::META_RENEW, $state ); $order->save(); }
		self::superseded( $id );
		if ( self::note_once( $order, 'superseded', $text ) ) self::alert_admin( 'renovación pagada de un servicio viejo: ' . self::svc( $id, 'domain' ), ucfirst( $text ) . "\n\n" . self::order_link( $order ) );
	}

	/** El servicio viejo de un dominio recontratado: sin avisos ni renovaciones; se pide darlo de baja (una vez). */
	private static function superseded( $id, $extra = '' ) {
		if ( self::svc( $id, 'superseded_noted' ) ) return;
		$text = 'La cuenta «' . self::svc( $id, 'webuzo_user' ) . '» de Webuzo es hoy de un servicio más nuevo del mismo dominio (' . self::svc( $id, 'domain' ) . '): este servicio ya no manda avisos ni renueva el hosting. Dalo de baja en Servicios de hosting.' . ( '' !== $extra ? ' ' . $extra : '' );
		self::svc_set( $id, array( 'superseded_noted' => time(), 'error' => $text ) ); self::svc_log( $id, $text );
		self::alert_admin( 'servicio viejo de ' . self::svc( $id, 'domain' ) . ': darlo de baja', $text . "\n\nServicios: " . admin_url( 'admin.php?page=digitalisimo-hosting-services' ) );
	}

	private static function overdue_or_unpaid_is_false( $id ) { return ! self::overdue( $id ); }

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
		$items = self::split_main( self::items( $order ) )[0];
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
		if ( ! self::is_service( $id ) || 'cancelled' === self::svc( $id, 'status' ) ) return false;
		// Con la venta apagada nada se encola (se perdería): queda anotado y se retoma al encenderla.
		if ( ! self::on() ) { self::svc_set( $id, array( 'resume_provision' => time() ) ); return false; }
		$order = wc_get_order( (int) self::svc( $id, 'order' ) );
		$queued = false;
		if ( $order ) {
			$state = self::state( $order );
			// El alta a medias, o un dominio más del pedido sin terminar (sea éste su servicio o el del plan; N2).
			if ( empty( $state['done'] ) || self::extras_state( $order, true ) ) {
				// Sólo una marca: la tarea limpia los reintentos sobre el estado que lea en ese momento.
				if ( empty( $state['paid'] ) && self::payment_confirmed( $order ) ) { $state['paid'] = time(); self::save_state( $order, $state ); }
				$order->update_meta_data( '_digitalisimo_retry', time() ); $order->save();
				self::enqueue( self::HOOK_PROVISION, $order->get_id() ); $queued = true;
			}
		}
		foreach ( array_unique( array_filter( array( (int) self::svc( $id, 'renewal_order' ), (int) self::svc( $id, 'domain_renewal_order' ), (int) self::svc( $id, 'domain_pending_order' ) ) ) ) as $rid ) {
			$renewal = wc_get_order( $rid );
			if ( ! $renewal ) continue;
			$s = (array) $renewal->get_meta( self::META_RENEW );
			if ( empty( $s['done'] ) && self::renewal_confirmed( $renewal ) ) {
				if ( empty( $s['paid'] ) ) { $s['paid'] = time(); $renewal->update_meta_data( self::META_RENEW, $s ); }
				$renewal->update_meta_data( '_digitalisimo_retry', time() ); $renewal->save(); self::enqueue( self::HOOK_RENEW, $renewal->get_id() ); $queued = true;
			}
		}
		if ( 'suspended' !== self::svc( $id, 'status' ) && self::svc( $id, 'error' ) && ! $queued && self::svc( $id, 'suspend_reason' ) ) { self::enqueue( self::HOOK_SUSPEND, $id ); $queued = true; }
		return $queued;
	}
}
