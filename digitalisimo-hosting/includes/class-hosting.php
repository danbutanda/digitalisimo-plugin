<?php
defined( 'ABSPATH' ) || exit;
// Avisos de administración con la X y el cierre recordado por usuario (copia idéntica en los siete plugins).
require_once __DIR__ . '/class-digitalisimo-notices.php';

/** Dominios, hosting y el flujo WooCommerce que antes pertenecía a Ecommerce. */
class Digitalisimo_Hosting {
	const OPTION = 'digitalisimo_hosting_options';
	const LEGACY_OPTION = 'digitalisimo_integrations_options';
	const LEGACY_INHERIT = 'digitalisimo_module_network_inherit';
	const LEGACY_MIGRATED = 'digitalisimo_hosting_ecommerce_migrated';
	const LEGACY_NETWORK_MIGRATED = 'digitalisimo_hosting_ecommerce_network_migrated';
	/**
	 * Lo que muestra el campo de la clave con una guardada (pedido del propietario del 2026-11-22: «deben salir los *****
	 * para saber que ya hay algo guardado»; el mismo patrón que IA Tools, SEO y Backups). Enviada tal cual conserva la
	 * clave; la clave real nunca se imprime. «Quitar la clave» la borra.
	 */
	const MASK = '••••••••••••';

	public static function init() {
		self::migrate_legacy_options();
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'resultado_dominio', array( __CLASS__, 'result_shortcode' ) );
		add_shortcode( 'digitalisimo_hosting_resultado_dominio', array( __CLASS__, 'result_shortcode' ) );
		// Compatibilidad con páginas creadas cuando este flujo pertenecía a Ecommerce.
		add_shortcode( 'digitalisimo_domain_search', array( __CLASS__, 'legacy_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'cart_compatibility' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'store_notice' ) );
		// «Probar conexión» de cada registrador: consulta el saldo con la cuenta guardada.
		add_action( 'admin_post_' . self::TEST_ACTION, array( __CLASS__, 'test_registrar' ) );
		// Venta automática: cuentas de Webuzo, registro en Namecheap, accesos por correo, renovación y suspensión.
		if ( class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) Digitalisimo_Hosting_Provisioning::init();
		// Lo que es cada producto (Dominio u Hosting), detectado de sus marcas; la migración de los ajustes con IDs y «|».
		if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::init();
		// La pestaña «DIGITALÍSIMO Hosting» del editor de producto (simple, variable y cada variación).
		if ( class_exists( 'Digitalisimo_Hosting_Product_Editor' ) && is_admin() ) Digitalisimo_Hosting_Product_Editor::init();
		// Buscador de dominios estilo registrador: shortcode, elemento atómico, carrito vacío y su ruta pública.
		if ( class_exists( 'Digitalisimo_Hosting_Finder' ) ) Digitalisimo_Hosting_Finder::init();
	}

	private static function defaults() {
		return array(
			'inherit_network'       => 0,
			'enabled'               => 1,
			'api_user'              => '',
			'username'              => '',
			'api_key'               => '',
			'client_ip'             => '',
			// Registradores (pedido del propietario del 2026-11-27): el predeterminado y, opcional, otro por extensión
			// (.mx|dynadot, una línea por extensión). Dynadot sólo pide su clave de API (api3, sin secreto).
			'registrar'             => 'namecheap',
			// Extensión => registrador (sólo las que no van con el predeterminado): una fila por extensión detectada.
			'registrar_by_tld'      => array(),
			'dynadot_api_key'       => '',
			// Los productos de dominio y de hosting ya no se configuran aquí: se detectan de lo marcado en el editor de cada
			// producto (Digitalisimo_Hosting_Products; pedido del propietario del 2026-11-27).
			'require_domain_hosting'=> 0,
			// Al comprar hosting o correo, antes de pagar: «Dominio nuevo» o «uno que ya tengo» (con su nombre exacto).
			'ask_domain'            => 1,
			'replace_hosting'       => 0,
			'simplify_checkout'     => 0,
			'form_selector'         => '.elementor-form',
			'domain_field'          => 'form_fields[dominio]',
			'extension_field'       => 'form_fields[extension]',
			// Venta automática (apagada hasta que el administrador la enciende): al pagarse un pedido crea la cuenta en
			// Webuzo, registra el dominio en Namecheap y envía los accesos; después avisa, renueva y suspende.
			'provision_enabled'     => 0,
			// Hosting con dominio propio (sin comprar el dominio aquí): por omisión, revisión manual antes de crear la cuenta.
			'own_domain_auto'       => 0,
			'webuzo_url'            => '',
			'webuzo_api_user'       => 'root',
			'webuzo_api_key'        => '',
			'webuzo_insecure'       => 0,
			'nameservers'           => self::DEFAULT_NAMESERVERS,
			'webuzo_user_prefix'    => '',
			'grace_days'            => 7,
			// Precios de dominios en tiempo real (agregado del propietario del 2026-11-24): apagado hasta encenderlo.
			'pricing_enabled'       => 0,
			'banxico_token'         => '',
			'pricing_cushion'       => 3,
			'pricing_margin'        => 25,
			'pricing_fee'           => 3.6,
			'pricing_vat'           => 16,
			'pricing_min_margin'    => 10,
			'pricing_rounding'      => '10',
			// Buscador de dominios (pedido del propietario del 2026-11-26): orden de las extensiones (lista, las populares
			// primero), avisos de requisitos (extensión => texto) y los planes de «se compran juntos» (vacío = los de hosting).
			'finder_order'          => class_exists( 'Digitalisimo_Hosting_Finder' ) ? Digitalisimo_Hosting_Finder::DEFAULT_ORDER : array( '.com', '.mx', '.com.mx', '.net', '.lat' ),
			'finder_requirements'   => class_exists( 'Digitalisimo_Hosting_Finder' ) ? Digitalisimo_Hosting_Finder::DEFAULT_REQUIREMENTS : array(),
			'finder_upsell'         => array(),
		);
	}
	/** Los nameservers de DIGITALÍSIMO a los que apunta cada dominio registrado (pedido del propietario del 2026-11-23). */
	const DEFAULT_NAMESERVERS = 'ns1.digitalisimo.website, ns2.digitalisimo.website';
	/**
	 * Credenciales de Webuzo que pueden vivir en wp-config.php en lugar de la base de datos: con la constante definida,
	 * manda ella y la pantalla no pinta el campo.
	 */
	const CONSTANTS = array( 'webuzo_url' => 'DIGITALISIMO_WEBUZO_URL', 'webuzo_api_user' => 'DIGITALISIMO_WEBUZO_API_USER', 'webuzo_api_key' => 'DIGITALISIMO_WEBUZO_API_KEY' );
	/** Claves secretas: el campo lleva la máscara, nunca la clave, y «Quitar la clave» la borra. */
	const SECRET_KEYS = array( 'api_key', 'webuzo_api_key', 'banxico_token', 'dynadot_api_key' );
	/** «Probar conexión» de un registrador (admin-post, con nonce y manage_options). */
	const TEST_ACTION = 'digitalisimo_hosting_test_registrar';
	/** Porcentajes de la fórmula de precios de dominios (0 a 90, con decimales). */
	const PERCENT_KEYS = array( 'pricing_cushion', 'pricing_margin', 'pricing_fee', 'pricing_vat', 'pricing_min_margin' );

	private static function local() { return (array) get_option( self::OPTION, array() ); }
	private static function network() { return (array) get_site_option( self::OPTION, array() ); }
	/**
	 * Lo que un sitio puede heredar de la red: la cuenta de Namecheap. Lo propio de cada tienda (productos por extensión,
	 * IDs de productos y categorías de WooCommerce, casillas que cambian el carrito y el checkout, el formulario) es
	 * siempre del sitio, también con «Heredar» (revisión del 2026-11-22: los IDs de otra tienda no sirven en ésta).
	 */
	const NETWORK_KEYS = array( 'api_user', 'username', 'api_key', 'client_ip', 'dynadot_api_key', 'webuzo_url', 'webuzo_api_user', 'webuzo_api_key', 'webuzo_insecure', 'nameservers', 'banxico_token' );
	/** ¿Este sitio usa la cuenta de Namecheap de la red? Sólo si su administrador marcó «Heredar» (opcional). */
	private static function inherits() { return is_multisite() && ! empty( self::local()['inherit_network'] ); }
	/** Aviso único: el sitio heredaba la red y ya no hereda su tienda (lo marca la visita a la pantalla Hosting). */
	const STORE_NOTICE = 'digitalisimo_hosting_store_notice_seen';
	/** Lo de la tienda que la red define distinto del sitio: lo que un sitio con «Heredar» usaba hasta esta versión. */
	private static function store_changed() {
		if ( ! self::inherits() ) return false;
		$n = self::network(); $o = wp_parse_args( self::local(), self::defaults() );
		foreach ( array_diff( array_keys( self::defaults() ), self::NETWORK_KEYS, array( 'inherit_network' ) ) as $key ) {
			if ( ! array_key_exists( $key, $n ) || ! is_scalar( $n[ $key ] ) || ! is_scalar( $o[ $key ] ?? '' ) ) continue;
			if ( preg_replace( '/\r\n?/', "\n", (string) $n[ $key ] ) !== preg_replace( '/\r\n?/', "\n", (string) $o[ $key ] ) ) return true;
		}
		return false;
	}
	/**
	 * En el escritorio de un sitio que heredaba la red: desde ahora sólo hereda la cuenta de Namecheap, y su tienda
	 * (productos por extensión, IDs, checkout) es la suya, que puede ser distinta de la que usaba. Una vez, hasta que
	 * se abre la pantalla Hosting.
	 */
	public static function store_notice() {
		if ( ! is_multisite() || 'index.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! current_user_can( 'manage_options' ) || get_option( self::STORE_NOTICE ) || ! self::store_changed() ) return;
		// Con la X: cerrado, vuelve a los 7 días mientras nadie abra Hosting (al abrirlo deja de salir).
		Digitalisimo_Notices::show( 'hosting-store-' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ), 'warning', '<p><strong>DIGITALÍSIMO Hosting:</strong> este sitio ya no usa la configuración de tienda de la red (productos por extensión, IDs de productos y categorías, opciones del checkout); de la red sólo hereda la cuenta de Namecheap. <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Revisa la configuración de Hosting de este sitio</a>.</p>', array( 'hash' => 'store', 'cap' => 'manage_options' ) );
	}
	/** ¿La red tiene una cuenta de registrador (Namecheap o Dynadot) guardada? */
	private static function network_account() { $n = self::network(); return '' !== (string) ( $n['api_user'] ?? '' ) || '' !== (string) ( $n['api_key'] ?? '' ) || '' !== (string) ( $n['dynadot_api_key'] ?? '' ); }
	/** Lo que usa el sitio: lo suyo y, si hereda, la cuenta de Namecheap de la red en lugar de la suya (que se conserva). */
	private static function all() {
		$o = wp_parse_args( self::local(), self::defaults() );
		if ( self::inherits() ) { $n = self::network(); foreach ( self::NETWORK_KEYS as $key ) $o[ $key ] = (string) ( $n[ $key ] ?? '' ); }
		foreach ( self::CONSTANTS as $key => $constant ) if ( defined( $constant ) ) $o[ $key ] = (string) constant( $constant );
		// Lo que antes se escribía con «|» o comas es hoy una lista (lo convirtió la migración; un valor viejo no se lee).
		foreach ( array( 'registrar_by_tld', 'finder_order', 'finder_requirements', 'finder_upsell' ) as $key ) if ( ! is_array( $o[ $key ] ) ) $o[ $key ] = self::defaults()[ $key ];
		// Una clave de wp-config.php nunca viaja a una URL editable en pantalla: sin la URL también en wp-config.php,
		// Webuzo queda sin configurar.
		if ( defined( self::CONSTANTS['webuzo_api_key'] ) && ! defined( self::CONSTANTS['webuzo_url'] ) ) $o['webuzo_url'] = '';
		// Con la clave en wp-config.php, el usuario de la API y «no verificar el certificado» tampoco se editan en pantalla:
		// salen de sus constantes (DIGITALISIMO_WEBUZO_API_USER, DIGITALISIMO_WEBUZO_INSECURE) o quedan en root y verificado.
		if ( defined( self::CONSTANTS['webuzo_api_key'] ) ) {
			$o['webuzo_api_user'] = defined( self::CONSTANTS['webuzo_api_user'] ) ? (string) constant( self::CONSTANTS['webuzo_api_user'] ) : 'root';
			$o['webuzo_insecure'] = defined( 'DIGITALISIMO_WEBUZO_INSECURE' ) && DIGITALISIMO_WEBUZO_INSECURE ? 1 : 0;
		}
		return $o;
	}
	/** Los ajustes que usa este sitio (con la herencia de la red y las constantes de wp-config.php ya aplicadas). */
	public static function settings() { return self::all(); }
	/** Extensión => ID del producto (o variación) de WooCommerce que la registra, detectado de los productos marcados. */
	public static function domain_products() { return self::products(); }
	/** ¿El producto (o la variación) es un plan de hosting o correo? Lo marcado en su editor. */
	public static function is_hosting_product( $product_id ) { return self::is_hosting( (int) $product_id ); }

	/**
	 * Copia una sola vez los valores de Ecommerce. No borra las opciones antiguas:
	 * se conservan como respaldo y los valores ya definidos en Hosting ganan siempre.
	 */
	public static function migrate_legacy_options() {
		$map = array(
			'enable_woocommerce'     => 'enabled',
			'namecheap_api_user'     => 'api_user',
			'namecheap_username'     => 'username',
			'namecheap_api_key'      => 'api_key',
			'namecheap_client_ip'    => 'client_ip',
			// Con el formato viejo: la migración de productos (Digitalisimo_Hosting_Products::migrate(), en init) los convierte.
			'domain_products'        => 'domain_products',
			'hosting_product_ids'    => 'hosting_product_ids',
			'hosting_category_ids'   => 'hosting_category_ids',
			'require_domain_hosting' => 'require_domain_hosting',
			'replace_hosting'        => 'replace_hosting',
			'simplify_checkout'      => 'simplify_checkout',
		);

		if ( is_multisite() && ! get_site_option( self::LEGACY_NETWORK_MIGRATED ) ) {
			$network = (array) get_site_option( self::OPTION, array() );
			$legacy_network = (array) get_site_option( self::LEGACY_OPTION, array() );
			$merged = self::migrated_values( $network, $legacy_network, $map );
			if ( $merged !== $network ) update_site_option( self::OPTION, $merged );
			update_site_option( self::LEGACY_NETWORK_MIGRATED, time() );
		}

		if ( get_option( self::LEGACY_MIGRATED ) ) {
			// La marca se guardaba sin autoload: una consulta en cada petición (escritorio, heartbeat, REST y front).
			// Se pasa una vez a autoload; después WordPress la trae con el resto de opciones, sin consulta propia.
			if ( function_exists( 'wp_set_option_autoload' ) && ! array_key_exists( self::LEGACY_MIGRATED, wp_load_alloptions() ) ) wp_set_option_autoload( self::LEGACY_MIGRATED, true );
			return;
		}
		$local = self::local();
		$legacy = (array) get_option( self::LEGACY_OPTION, array() );
		$has_legacy = (bool) array_intersect_key( $legacy, $map );
		$merged = self::migrated_values( $local, $legacy, $map );
		if ( $has_legacy && is_multisite() && ! array_key_exists( 'inherit_network', $merged ) ) {
			$inherit = (array) get_option( self::LEGACY_INHERIT, array() );
			$merged['inherit_network'] = 1;
			foreach ( array_keys( $map ) as $key ) {
				if ( array_key_exists( $key, $inherit ) && empty( $inherit[ $key ] ) ) { $merged['inherit_network'] = 0; break; }
			}
		}
		if ( $merged !== $local ) update_option( self::OPTION, $merged, false );
		update_option( self::LEGACY_MIGRATED, time(), true );
	}

	private static function migrated_values( $target, $legacy, $map ) {
		foreach ( $map as $old => $new ) if ( array_key_exists( $old, $legacy ) && ! array_key_exists( $new, $target ) ) $target[ $new ] = $legacy[ $old ];
		return $target;
	}

	/** Contador efímero por visitante; no se guarda la IP ni se escribe en logs. */
	public static function rate_limit( $scope, $limit = 20, $ttl = MINUTE_IN_SECONDS ) {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		// IPv6: una conexión recibe un /64 entero; se cuenta por /64 para que cambiar de dirección no reinicie el tope.
		$bin = false !== strpos( $ip, ':' ) && function_exists( 'inet_pton' ) ? @inet_pton( $ip ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_string( $bin ) && 16 === strlen( $bin ) ) $ip = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		$key = 'digitalisimo_hosting_rate_' . md5( wp_salt( 'nonce' ) . '|' . sanitize_key( $scope ) . '|' . $ip );
		// Con la cuenta de la red, el límite es de toda la red (una cuenta, un tope), no de cada sitio.
		$shared = self::inherits();
		$v = $shared ? get_site_transient( $key ) : get_transient( $key );
		// Ventana fija: empieza con la primera petición y no se alarga con las siguientes ($ttl: un minuto, una hora…).
		$count = is_array( $v ) ? (int) ( $v['n'] ?? 0 ) : (int) $v;
		$until = is_array( $v ) && ! empty( $v['until'] ) ? (int) $v['until'] : time() + (int) $ttl;
		if ( $count >= $limit ) return false;
		$val = array( 'n' => $count + 1, 'until' => $until );
		$left = max( 1, $until - time() );
		if ( $shared ) set_site_transient( $key, $val, $left ); else set_transient( $key, $val, $left );
		return true;
	}

	/** Extensión => ID del producto que la registra: lo detectado de los productos marcados como Dominio. */
	private static function products() { return class_exists( 'Digitalisimo_Hosting_Products' ) ? Digitalisimo_Hosting_Products::map() : array(); }

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::migrate();
		if ( is_multisite() && ! get_option( self::STORE_NOTICE ) && self::store_changed() ) update_option( self::STORE_NOTICE, 1, false );
		$saved = isset( $_POST['digitalisimo_hosting_save'] ) && check_admin_referer( 'digitalisimo_hosting_save' );
		if ( $saved ) self::save( false );
		self::form( false, $saved );
	}
	public static function network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) return;
		$saved = isset( $_POST['digitalisimo_hosting_network_save'] ) && check_admin_referer( 'digitalisimo_hosting_network_save' );
		if ( $saved ) self::save( true );
		self::form( true, $saved );
	}

	/**
	 * Un solo dibujante para el sitio y la red. La red sólo define la cuenta de Namecheap que los sitios pueden heredar;
	 * el sitio, todo lo suyo y, con «Heredar», usa la cuenta de la red (sin ver sus datos) y conserva la propia.
	 */
	private static function form( $network, $saved = false ) {
		$o = $network ? wp_parse_args( self::network(), self::defaults() ) : self::all();
		$inherits = ! $network && self::inherits();
		echo '<div class="wrap"><h1>Digitalisimo · Hosting' . ( $network ? ' de red' : '' ) . '</h1><hr class="wp-header-end"><p>' . ( $network ? 'Cuenta de Namecheap que los sitios de la red pueden usar.' : 'Dominios, planes de hosting y compra con WooCommerce.' ) . '</p>';
		if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>Configuración ' . ( $network ? 'de red ' : '' ) . 'guardada.</p></div>';
		if ( ! $network ) self::test_notice();
		if ( $network ) echo '<p class="description">Un sitio la usa sólo si su administrador marca «Heredar de la red la cuenta de Namecheap» en su pantalla Hosting. Los productos, las categorías y el checkout de WooCommerce son de cada sitio y no se heredan.</p>';
		elseif ( is_multisite() && ! $inherits && self::network_account() ) echo '<div class="notice notice-info inline"><p>La red tiene una cuenta de Namecheap configurada. Para usarla en este sitio, marca «Heredar de la red la cuenta de Namecheap» y guarda.</p></div>';
		echo '<form method="post">';
		wp_nonce_field( $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		if ( ! $network ) self::check( 'enabled', 'Activar dominios y flujo WooCommerce', $o );
		if ( ! $network ) self::registrar_fields( $o );
		echo '<h2>Namecheap</h2>';
		if ( ! $network && is_multisite() ) echo '<p><label><input type="hidden" name="hosting[inherit_network]" value="0"><input type="checkbox" name="hosting[inherit_network]" value="1" ' . checked( $inherits, true, false ) . '> <strong>Heredar de la red la cuenta de Namecheap</strong></label><br><span class="description">' . ( $inherits ? 'Este sitio consulta dominios con la cuenta de la red. Al desmarcarla vuelve a usar la suya, que se conserva.' : 'Los productos, las categorías y el checkout son siempre de este sitio.' ) . '</span></p>';
		if ( $inherits ) echo '<p class="description">Usa la cuenta de Namecheap de la red.</p>';
		else { self::field( 'api_user', 'Usuario API Namecheap', $o ); self::field( 'username', 'Usuario Namecheap', $o ); self::field( 'api_key', 'Clave API Namecheap', $o, 'password' ); self::field( 'client_ip', 'IP autorizada por Namecheap', $o ); }
		if ( ! $network ) self::test_button( 'namecheap' );
		echo '<h2>Dynadot</h2>';
		if ( $inherits ) echo '<p class="description">Usa la cuenta de Dynadot de la red.</p>';
		else self::field( 'dynadot_api_key', 'Clave de la API de Dynadot', $o, 'password', 'Dynadot → Tools → API: la «Production Key» (la API «api3» no usa el secreto). En esa misma página, en la lista de IP permitidas, agrega la IP pública de este servidor (la misma que autorizaste en Namecheap).' );
		if ( ! $network ) self::test_button( 'dynadot' );
		echo '<h2>Webuzo (cuentas de hosting)</h2>';
		if ( class_exists( 'Digitalisimo_Hosting_Webuzo' ) ) Digitalisimo_Hosting_Webuzo::settings_banner( $network, $o );
		if ( $inherits ) echo '<p class="description">Usa el servidor Webuzo y los nameservers de la red.</p>';
		else {
			if ( defined( self::CONSTANTS['webuzo_api_key'] ) && ! defined( self::CONSTANTS['webuzo_url'] ) ) echo '<p><strong>URL del panel de administración de Webuzo</strong><br><span class="description">La clave está en wp-config.php: define también <code>DIGITALISIMO_WEBUZO_URL</code> allí. Mientras tanto Webuzo queda sin configurar (la clave nunca se manda a una URL editable aquí).</span></p>';
			else self::field( 'webuzo_url', 'URL del panel de administración de Webuzo', $o, 'text', 'Por ejemplo https://servidor.ejemplo.com:2005 (el puerto 2005 es el de administración; los clientes entran por el 2003).' );
			if ( defined( self::CONSTANTS['webuzo_api_key'] ) ) echo '<p class="description">Con la clave en wp-config.php, el usuario de la API (' . esc_html( $o['webuzo_api_user'] ) . ') y la verificación del certificado (' . ( empty( $o['webuzo_insecure'] ) ? 'activada' : 'desactivada' ) . ') también salen de wp-config.php (DIGITALISIMO_WEBUZO_API_USER, DIGITALISIMO_WEBUZO_INSECURE).</p>';
			else self::field( 'webuzo_api_user', 'Usuario de la API de Webuzo', $o, 'text', 'El usuario dueño de la clave de API (normalmente root).' );
			self::field( 'webuzo_api_key', 'Clave de la API de Webuzo', $o, 'password', 'Webuzo → Ajustes → API Keys. Si Webuzo la rechaza, el aprovisionamiento se detiene sin reintentar hasta que guardes una nueva: Webuzo bloquea la IP tras varios intentos fallidos.' );
			if ( ! defined( self::CONSTANTS['webuzo_api_key'] ) ) self::check( 'webuzo_insecure', 'No verificar el certificado del panel (sólo si usa un certificado autofirmado)', $o );
			self::field( 'nameservers', 'Nameservers de los dominios', $o, 'text', 'Separados por comas. Cada dominio registrado se apunta a ellos.' );
		}
		if ( ! $network ) {
			echo '<h2>Venta automática</h2>';
			self::check( 'provision_enabled', 'Crear la cuenta, registrar el dominio y enviar los accesos al pagarse un pedido', $o );
			self::check( 'own_domain_auto', 'Hosting con dominio propio: crear la cuenta de inmediato, sin revisión manual (nadie verifica que el dominio sea del cliente)', $o );
			echo '<p class="description"><strong>Pago confirmado:</strong> sólo se aprovisiona cuando la pasarela dejó el cobro confirmado (fecha de pago y número de transacción). Con transferencia, cheque, contra entrega o cualquier pasarela que no deje número de transacción, marca el pedido «completado» al cobrar. Un desarrollador puede ajustar las listas con los filtros <code>digitalisimo_hosting_offline_methods</code> y <code>digitalisimo_hosting_trusted_methods</code>. Los pedidos anteriores al encendido no se aprovisionan solos.</p>';
			echo '<p class="description">Los planes son los productos marcados como «Hosting» en su editor, con su paquete de Webuzo y su periodo de cobro (ver «Compra de hosting»). La lista de servicios está en WooCommerce → Servicios de hosting.</p>';
			self::field( 'webuzo_user_prefix', 'Prefijo de los usuarios de Webuzo', $o, 'text', 'Opcional, sólo letras y números (por ejemplo «prueba» para un ensayo).' );
			self::field( 'grace_days', 'Días de gracia antes de suspender', $o, 'number', 'Días después del vencimiento sin pago antes de suspender la cuenta en Webuzo. En los planes mensuales son 5 como máximo y en los trimestrales 15 (que la gracia no se coma el periodo).' );
			echo '<h2>Precios de dominios</h2>';
			self::check( 'pricing_enabled', 'Actualizar a diario el precio de cada producto de dominio con el costo de Namecheap y el tipo de cambio', $o );
			echo '<p class="description">Precio sin IVA = costo × (1 + colchón) × (1 + margen) ÷ (1 − comisión × (1 + IVA)), con el costo mayor entre registro y renovación. La pasarela cobra su comisión sobre el total con IVA; así el margen se cumple después de pagarla. Si una consulta falla, no se cambia ningún precio y se te avisa. Historial en WooCommerce → Servicios de hosting.</p>';
			foreach ( array( 'pricing_cushion' => 'Colchón sobre el tipo de cambio (%)', 'pricing_margin' => 'Margen (%)', 'pricing_fee' => 'Comisión de la pasarela (%)', 'pricing_vat' => 'IVA sobre el que cobra la pasarela (%)', 'pricing_min_margin' => 'Margen mínimo al registrar o renovar (%)' ) as $key => $label ) self::field( $key, $label, $o, 'percent', 'pricing_min_margin' === $key ? 'Si al registrar o renovar un dominio pagado su costo real ya no deja este margen, el pedido queda en espera en lugar de registrarse con pérdida.' : '' );
			$rounding = class_exists( 'Digitalisimo_Hosting_Pricing' ) ? Digitalisimo_Hosting_Pricing::ROUNDING : array( '10' => 'Múltiplo de 10' );
			echo '<p><label><strong>Redondeo hacia arriba</strong><br><select name="hosting[pricing_rounding]">';
			foreach ( $rounding as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . ( (string) $value === (string) ( $o['pricing_rounding'] ?? '10' ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
			echo '</select></label></p>';
		}
		if ( ! $inherits ) {
			if ( $network ) echo '<h2>Tipo de cambio</h2>';
			self::field( 'banxico_token', 'Token de consulta del SIE de Banxico', $o, 'password', 'Para el tipo de cambio FIX (serie SF43718). Se pide gratis en banxico.org.mx (SIE API → token). Sin él se usa Frankfurter como respaldo.' );
		}
		if ( ! $network ) {
			if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::settings_table();
			echo '<h2>Compra de hosting</h2>';
			if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::hosting_table();
			self::check( 'ask_domain', 'Al comprar hosting o correo, pedir antes de pagar «Dominio nuevo» o «uno que ya tengo» (con su nombre)', $o ); self::check( 'require_domain_hosting', 'Exigir dominio y hosting juntos (un dominio que ya tiene el cliente cuenta como dominio)', $o ); self::check( 'replace_hosting', 'Reemplazar el plan de hosting al elegir otro', $o ); self::check( 'simplify_checkout', 'Simplificar checkout', $o );
			echo '<h2>Buscador de dominios</h2>';
			echo '<p class="description">Ponlo con el shortcode <code>[digitalisimo_buscador_dominios]</code> o con el elemento «Buscador de dominios» del editor. Busca a la vez todas las extensiones con producto y agrega al carrito sin salir de la página. El carrito vacío también lo muestra.</p>';
			self::finder_fields( $o );
			echo '<h2>Formulario de DIGITALÍSIMO Elements</h2>';
			self::field( 'form_selector', 'Selector CSS del formulario', $o ); self::field( 'domain_field', 'Campo dominio', $o ); self::field( 'extension_field', 'Campo extensión', $o );
			echo '<p>Usa <code>[resultado_dominio]</code> en el widget Formulario de DIGITALÍSIMO Elements. El shortcode anterior <code>[digitalisimo_domain_search]</code> se conserva para páginas existentes.</p>';
		}
		submit_button( $network ? 'Guardar Hosting de red' : 'Guardar Hosting', 'primary', $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		echo '</form></div>';
	}

	/** Registrador predeterminado y por extensión (lo de cada tienda: nunca se hereda de la red). */
	private static function registrar_fields( $o ) {
		echo '<h2>Registrador de dominios</h2>';
		$ids = class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::IDS : array( 'namecheap' => 'Namecheap' );
		echo '<p><label><strong>Registrador predeterminado</strong><br><select name="hosting[registrar]">';
		foreach ( $ids as $id => $label ) echo '<option value="' . esc_attr( $id ) . '"' . ( $id === (string) ( $o['registrar'] ?? 'namecheap' ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><br><span class="description">Con él se consultan, registran, apuntan y renuevan los dominios. Un dominio ya registrado se renueva siempre con el registrador que lo tiene.</span></p>';
		$exts = self::detected_exts( array_keys( (array) $o['registrar_by_tld'] ) );
		echo '<fieldset><legend><strong>Registrador por extensión</strong></legend>';
		if ( ! $exts ) echo '<p class="description">Aparece una fila por extensión cuando marques productos como «Dominio».</p>';
		else {
			echo '<table class="form-table dh-rows" role="presentation"><tbody>';
			foreach ( $exts as $ext ) {
				$cur = (string) ( $o['registrar_by_tld'][ $ext ] ?? '' );
				$fid = 'dh-reg-' . sanitize_key( str_replace( '.', '-', $ext ) );
				echo '<tr><th scope="row"><label for="' . esc_attr( $fid ) . '">' . esc_html( $ext ) . '</label></th><td><select id="' . esc_attr( $fid ) . '" name="hosting[registrar_by_tld][' . esc_attr( $ext ) . ']"><option value="">Predeterminado (' . esc_html( class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::label( Digitalisimo_Hosting_Registrars::default_id( $o ) ) : 'Namecheap' ) . ')</option>';
				foreach ( $ids as $id => $label ) echo '<option value="' . esc_attr( $id ) . '"' . ( $id === $cur ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
				echo '</select></td></tr>';
			}
			echo '</tbody></table><p class="description">Las extensiones que van con otro registrador que el predeterminado.</p>';
		}
		echo '</fieldset>';
	}

	/** Las extensiones que se venden (detectadas) más las que ya tienen un valor guardado, sin repetir. */
	private static function detected_exts( array $extra = array() ) {
		$exts = class_exists( 'Digitalisimo_Hosting_Products' ) ? Digitalisimo_Hosting_Products::sold_exts() : array();
		foreach ( $extra as $e ) if ( is_string( $e ) && '' !== $e && ! in_array( $e, $exts, true ) ) $exts[] = $e;
		return $exts;
	}

	/**
	 * El buscador: el orden de las extensiones (una lista ordenable, con «Subir» y «Bajar» o arrastrando), un aviso de
	 * requisitos por extensión y «Se compran juntos» (desplegable múltiple de los planes de hosting detectados).
	 */
	private static function finder_fields( $o ) {
		$order = class_exists( 'Digitalisimo_Hosting_Finder' ) ? Digitalisimo_Hosting_Finder::ordered() : array();
		echo '<fieldset class="dh-order"><legend><strong>Orden de las extensiones</strong></legend><p class="description" id="dh-order-help">Las primeras salen arriba en los resultados. Usa «Subir» y «Bajar» o arrastra la fila.</p>';
		if ( ! $order ) echo '<p class="description">Aparecen aquí cuando marques productos como «Dominio».</p>';
		else {
			echo '<ol class="dh-order-list" data-dh-order aria-describedby="dh-order-help">';
			foreach ( $order as $ext ) echo '<li draggable="true" data-ext="' . esc_attr( $ext ) . '"><input type="hidden" name="hosting[finder_order][]" value="' . esc_attr( $ext ) . '"><span class="dh-order-name">' . esc_html( $ext ) . '</span> <button type="button" class="button button-small" data-dh-move="-1" aria-label="' . esc_attr( 'Subir ' . $ext ) . '">Subir</button> <button type="button" class="button button-small" data-dh-move="1" aria-label="' . esc_attr( 'Bajar ' . $ext ) . '">Bajar</button></li>';
			echo '</ol><p class="screen-reader-text" role="status" aria-live="polite" data-dh-order-status></p>';
		}
		echo '</fieldset>';
		$req = (array) $o['finder_requirements'];
		$exts = self::detected_exts( array_keys( $req ) );
		echo '<fieldset><legend><strong>Avisos de requisitos por extensión</strong></legend>';
		if ( ! $exts ) echo '<p class="description">Aparece una fila por extensión cuando marques productos como «Dominio».</p>';
		else {
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $exts as $ext ) { $fid = 'dh-req-' . sanitize_key( str_replace( '.', '-', $ext ) ); echo '<tr><th scope="row"><label for="' . esc_attr( $fid ) . '">' . esc_html( $ext ) . '</label></th><td><input type="text" class="large-text" id="' . esc_attr( $fid ) . '" name="hosting[finder_requirements][' . esc_attr( $ext ) . ']" value="' . esc_attr( (string) ( $req[ $ext ] ?? '' ) ) . '" maxlength="300"></td></tr>'; }
			echo '</tbody></table><p class="description">Se muestra junto a esa extensión en el buscador. Vacío: sin aviso.</p>';
		}
		echo '</fieldset>';
		$chosen = array_map( 'intval', (array) $o['finder_upsell'] );
		$options = array();
		if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) foreach ( Digitalisimo_Hosting_Products::catalog()['hosting'] as $h ) { $key = $h['parent'] ? $h['parent'] : $h['id']; if ( ! isset( $options[ $key ] ) ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( $key ) : null; $options[ $key ] = $p ? (string) $p->get_name() : $h['name']; } }
		foreach ( $chosen as $id ) if ( ! isset( $options[ $id ] ) ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null; if ( $p ) $options[ $id ] = (string) $p->get_name() . ' (no está marcado como Hosting)'; }
		echo '<p><label for="dh-upsell"><strong>«Se compran juntos»</strong></label><br><input type="hidden" name="hosting[finder_upsell][]" value="">';
		if ( ! $options ) echo '<span class="description">Aparecen aquí los productos marcados como «Hosting».</span>';
		else {
			echo '<select id="dh-upsell" name="hosting[finder_upsell][]" multiple size="' . (int) min( 6, max( 2, count( $options ) ) ) . '" aria-describedby="dh-upsell-help">';
			foreach ( $options as $id => $name ) echo '<option value="' . (int) $id . '"' . ( in_array( (int) $id, $chosen, true ) ? ' selected' : '' ) . '>' . esc_html( $name ) . '</option>';
			echo '</select><br><span class="description" id="dh-upsell-help">Se muestran hasta 4 al agregar un dominio. Sin elegir ninguno se muestran los planes de hosting. Con Ctrl (o ⌘) eliges varios.</span>';
		}
		echo '</p>';
		self::order_script();
	}

	/** El orden con teclado (Subir/Bajar, con aviso para lectores de pantalla) y arrastrando. Sin script queda el orden actual. */
	private static function order_script() {
		echo "<script>(function(){var l=document.querySelector('[data-dh-order]');if(!l)return;var st=document.querySelector('[data-dh-order-status]');function say(li){var n=[].indexOf.call(l.children,li)+1;if(st)st.textContent=li.getAttribute('data-ext')+': posición '+n+' de '+l.children.length;}l.addEventListener('click',function(e){var b=e.target.closest('[data-dh-move]');if(!b)return;var li=b.closest('li');var d=+b.getAttribute('data-dh-move');if(d<0&&li.previousElementSibling)l.insertBefore(li,li.previousElementSibling);else if(d>0&&li.nextElementSibling)l.insertBefore(li.nextElementSibling,li);b.focus();say(li);});var drag=null;l.addEventListener('dragstart',function(e){drag=e.target.closest('li');});l.addEventListener('dragover',function(e){e.preventDefault();var t=e.target.closest('li');if(!drag||!t||t===drag)return;var r=t.getBoundingClientRect();l.insertBefore(drag,(e.clientY-r.top)>r.height/2?t.nextSibling:t);});l.addEventListener('drop',function(e){e.preventDefault();if(drag)say(drag);drag=null;});})();</script>";
	}

	/** «Probar conexión»: consulta el saldo con la cuenta guardada (no la que esté escrita sin guardar). */
	private static function test_button( $id ) {
		if ( ! class_exists( 'Digitalisimo_Hosting_Registrars' ) || ! Digitalisimo_Hosting_Registrars::valid( $id ) || ! function_exists( 'wp_create_nonce' ) ) return;
		$url = admin_url( 'admin-post.php?action=' . self::TEST_ACTION . '&registrar=' . $id . '&_wpnonce=' . wp_create_nonce( self::TEST_ACTION ) );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">Probar conexión con ' . esc_html( Digitalisimo_Hosting_Registrars::label( $id ) ) . '</a> <span class="description">Consulta el saldo con la cuenta guardada (guarda antes si la cambiaste).</span></p>';
	}

	/** El resultado de «Probar conexión», una vez, para quien la pulsó. */
	private static function test_notice() {
		if ( ! function_exists( 'get_current_user_id' ) ) return;
		$key = 'digitalisimo_hosting_test_' . (int) get_current_user_id();
		$r = get_transient( $key );
		if ( ! is_array( $r ) ) return;
		delete_transient( $key );
		echo '<div class="notice notice-' . ( ! empty( $r['ok'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( (string) ( $r['text'] ?? '' ) ) . '</p></div>';
	}

	/**
	 * admin-post: consulta el saldo del registrador con la cuenta guardada y vuelve a la pantalla con el resultado (el saldo o
	 * el error, nunca la clave).
	 */
	public static function test_registrar() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permiso para esto.', 403 );
		check_admin_referer( self::TEST_ACTION );
		$id = sanitize_key( (string) ( $_REQUEST['registrar'] ?? '' ) );
		if ( ! class_exists( 'Digitalisimo_Hosting_Registrars' ) || ! Digitalisimo_Hosting_Registrars::valid( $id ) ) wp_die( 'Registrador desconocido.', 400 );
		$text = self::test_result( $id );
		set_transient( 'digitalisimo_hosting_test_' . (int) get_current_user_id(), $text, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-hosting' ) );
		exit;
	}

	/** array( ok, text ) de «Probar conexión»: el saldo en USD o el motivo, sin la clave. */
	public static function test_result( $id ) {
		$o = self::all();
		$r = Digitalisimo_Hosting_Registrars::make( $id, $o );
		$label = $r->label();
		if ( ! $r->configured() ) return array( 'ok' => 0, 'text' => $label . ': falta la cuenta (' . ( 'namecheap' === $id ? 'la clave, los usuarios o la IP' : 'la clave' ) . ') en esta pantalla. Guárdala y vuelve a probar.' );
		$b = $r->balance();
		if ( 'ok' === $b['kind'] ) return array( 'ok' => 1, 'text' => 'Conexión con ' . $label . ' correcta: saldo disponible ' . number_format( (float) $b['value'], 2, '.', ',' ) . ' USD.' );
		$why = 'auth' === $b['kind'] ? ' Revisa la clave y que la IP de este servidor esté autorizada en ' . $label . '.' : ( 'transient' === $b['kind'] ? ' Intenta de nuevo en un momento.' : '' );
		$text = 'No se pudo conectar con ' . $label . ': ' . rtrim( preg_replace( '/^' . preg_quote( $label, '/' ) . ':\s*/', '', (string) $b['message'] ), '.' ) . '.' . $why;
		foreach ( array( 'api_key', 'dynadot_api_key' ) as $secret ) { $v = (string) ( $o[ $secret ] ?? '' ); if ( '' !== $v ) $text = str_replace( $v, '••••', $text ); }
		return array( 'ok' => 0, 'text' => $text );
	}

	/** El campo de la clave: la máscara y «Guardada» con una guardada (nunca la clave), seleccionado entero al enfocarlo. */
	private static function field( $key, $label, $o, $type = 'text', $help = '' ) {
		if ( isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] ) ) {
			echo '<p><strong>' . esc_html( $label ) . '</strong><br><span class="description">Definida en wp-config.php (<code>' . esc_html( self::CONSTANTS[ $key ] ) . '</code>).</span></p>';
			return;
		}
		$secret = 'password' === $type; $saved = $secret && '' !== (string) ( $o[ $key ] ?? '' );
		$value = $secret ? ( $saved ? self::MASK : '' ) : ( $o[ $key ] ?? '' );
		$number = 'number' === $type || 'percent' === $type;
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="' . ( $number ? 'small-text' : 'regular-text' ) . '" type="' . ( $number ? 'number' : esc_attr( $type ) ) . '" name="hosting[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . ( $secret ? ' autocomplete="new-password" onfocus="this.select()"' : '' ) . ( $number ? ' min="0" max="90" step="' . ( 'percent' === $type ? '0.01' : '1' ) . '"' : '' ) . '></label>';
		if ( $saved ) echo ' <span class="description">Guardada · escribe una nueva sólo si quieres reemplazarla</span> <label><input type="checkbox" name="hosting[' . esc_attr( $key ) . '_remove]" value="1"> Quitar la clave</label>';
		if ( '' !== $help ) echo '<br><span class="description">' . esc_html( $help ) . '</span>';
		echo '</p>';
	}
	private static function area( $key, $label, $o, $help = '' ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea class="large-text code" rows="3" name="hosting[' . esc_attr( $key ) . ']">' . esc_textarea( $o[ $key ] ?? '' ) . '</textarea></label><br><span class="description">' . esc_html( $help ) . '</span></p>'; }
	private static function check( $key, $label, $o ) { echo '<p><label><input type="hidden" name="hosting[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="hosting[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label></p>'; }

	/**
	 * Lo escrito en el campo de la clave, sin la máscara: una clave real nunca lleva «•», así que se quitan todos (quien
	 * hace clic en el campo y pega la nueva la deja pegada a la máscara). Vacío = conservar la guardada. El mismo patrón
	 * que IA::unmask() de IA Tools.
	 */
	private static function unmask( $raw ) { return sanitize_text_field( str_replace( '•', '', (string) $raw ) ); }

	/**
	 * Guarda lo que trae el formulario y nada más: un campo que la pantalla no pintó (la cuenta propia mientras el sitio
	 * hereda, lo de la tienda en la red) conserva su valor. La red sólo guarda su cuenta de Namecheap; un sitio nunca
	 * copia la de la red.
	 */
	private static function save( $network ) { self::store( $network, (array) wp_unslash( $_POST['hosting'] ?? array() ) ); }

	/**
	 * Valida y guarda lo recibido (el formulario o la herramienta MCP de ajustes, con las mismas reglas) sobre lo guardado:
	 * sólo cambian las claves que llegan. Devuelve la opción guardada.
	 */
	private static function store( $network, array $in ) {
		$o = $network ? self::network() : self::local();
		// La clave de Webuzo que usaba esta pantalla antes de guardar (la heredada o la de wp-config.php también cuentan).
		$old_webuzo = $network ? (string) ( $o['webuzo_api_key'] ?? '' ) : (string) ( self::all()['webuzo_api_key'] ?? '' );
		$was_sale = ! $network && ! empty( $o['provision_enabled'] );
		// Encendida de verdad = módulo y venta (reencender el módulo con la venta ya marcada también retoma lo anotado).
		$was_on = $was_sale && ( ! array_key_exists( 'enabled', $o ) || ! empty( $o['enabled'] ) );
		// Con la clave en wp-config.php, URL, usuario y verificación del certificado no se aceptan desde el formulario.
		if ( defined( self::CONSTANTS['webuzo_api_key'] ) ) unset( $in['webuzo_url'], $in['webuzo_api_user'], $in['webuzo_insecure'] );
		$text = $network ? array( 'api_user', 'username', 'client_ip', 'webuzo_api_user' ) : array( 'api_user', 'username', 'client_ip', 'form_selector', 'domain_field', 'extension_field', 'webuzo_api_user' );
		foreach ( $text as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = sanitize_text_field( $in[ $key ] );
		// Las mismas reglas en el sitio y en la red: la URL del panel sólo https con host, los nameservers sólo nombres
		// de host válidos (vacío = los de DIGITALÍSIMO).
		if ( array_key_exists( 'webuzo_url', $in ) && ! defined( self::CONSTANTS['webuzo_api_key'] ) ) $o['webuzo_url'] = self::webuzo_url( $in['webuzo_url'] );
		if ( array_key_exists( 'nameservers', $in ) ) $o['nameservers'] = self::nameservers( $in['nameservers'] );
		if ( array_key_exists( 'webuzo_insecure', $in ) ) $o['webuzo_insecure'] = empty( $in['webuzo_insecure'] ) ? 0 : 1;
		if ( ! $network ) {
			if ( array_key_exists( 'registrar', $in ) && class_exists( 'Digitalisimo_Hosting_Registrars' ) && Digitalisimo_Hosting_Registrars::valid( (string) $in['registrar'] ) ) $o['registrar'] = (string) $in['registrar'];
			// Filas por extensión (nunca texto con «|»): extensión => registrador; vacío = el predeterminado.
			if ( array_key_exists( 'registrar_by_tld', $in ) && is_array( $in['registrar_by_tld'] ) && class_exists( 'Digitalisimo_Hosting_Registrars' ) ) {
				$map = array();
				foreach ( $in['registrar_by_tld'] as $ext => $id ) { $ext = Digitalisimo_Hosting_Products::normalize( $ext ); if ( '' !== $ext && Digitalisimo_Hosting_Registrars::valid( (string) $id ) ) $map[ $ext ] = (string) $id; }
				$o['registrar_by_tld'] = $map;
			}
			if ( array_key_exists( 'finder_order', $in ) && is_array( $in['finder_order'] ) ) {
				$list = array();
				foreach ( $in['finder_order'] as $ext ) { $ext = Digitalisimo_Hosting_Products::normalize( is_scalar( $ext ) ? (string) $ext : '' ); if ( '' !== $ext && ! in_array( $ext, $list, true ) ) $list[] = $ext; }
				$o['finder_order'] = $list;
			}
			if ( array_key_exists( 'finder_requirements', $in ) && is_array( $in['finder_requirements'] ) ) {
				$req = array();
				foreach ( $in['finder_requirements'] as $ext => $text ) { $ext = Digitalisimo_Hosting_Products::normalize( $ext ); $text = is_scalar( $text ) ? substr( sanitize_text_field( (string) $text ), 0, 300 ) : ''; if ( '' !== $ext && '' !== $text ) $req[ $ext ] = $text; }
				$o['finder_requirements'] = $req;
			}
			if ( array_key_exists( 'finder_upsell', $in ) && is_array( $in['finder_upsell'] ) ) $o['finder_upsell'] = array_values( array_unique( array_filter( array_map( 'absint', array_filter( $in['finder_upsell'], 'is_scalar' ) ) ) ) );
			foreach ( array( 'enabled', 'require_domain_hosting', 'ask_domain', 'replace_hosting', 'simplify_checkout', 'provision_enabled', 'own_domain_auto' ) as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
			if ( array_key_exists( 'webuzo_user_prefix', $in ) ) $o['webuzo_user_prefix'] = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $in['webuzo_user_prefix'] ) ), 0, 6 );
			if ( array_key_exists( 'grace_days', $in ) ) $o['grace_days'] = min( 90, absint( $in['grace_days'] ) );
			if ( array_key_exists( 'pricing_enabled', $in ) ) $o['pricing_enabled'] = empty( $in['pricing_enabled'] ) ? 0 : 1;
			foreach ( self::PERCENT_KEYS as $key ) if ( array_key_exists( $key, $in ) && is_numeric( str_replace( ',', '.', (string) $in[ $key ] ) ) ) $o[ $key ] = round( max( 0, min( 90, (float) str_replace( ',', '.', (string) $in[ $key ] ) ) ), 2 );
			if ( array_key_exists( 'pricing_rounding', $in ) && in_array( (string) $in['pricing_rounding'], array( '10', '9', 'end9', 'none' ), true ) ) $o['pricing_rounding'] = (string) $in['pricing_rounding'];
			// El formulario del sitio siempre la envía en una red; quien no la envía (la herramienta MCP) no la cambia.
			if ( ! is_multisite() || array_key_exists( 'inherit_network', $in ) ) $o['inherit_network'] = is_multisite() && ! empty( $in['inherit_network'] ) ? 1 : 0;
		}
		$webuzo_key = false;
		foreach ( self::SECRET_KEYS as $key ) {
			if ( ! array_key_exists( $key, $in ) ) continue;
			$typed = self::unmask( $in[ $key ] );
			if ( '' !== $typed ) { $o[ $key ] = $typed; $webuzo_key = $webuzo_key || 'webuzo_api_key' === $key; }
			elseif ( ! empty( $in[ $key . '_remove' ] ) ) $o[ $key ] = '';
		}
		// Al encender la venta automática se anota desde cuándo: los pedidos anteriores nunca se aprovisionan solos.
		if ( ! $network && ! $was_sale && ! empty( $o['provision_enabled'] ) ) $o['provision_since'] = time();
		if ( $network ) update_site_option( self::OPTION, $o ); else update_option( self::OPTION, $o, false );
		// La parada por clave rechazada es de la CLAVE: una clave distinta guardada ya no está detenida (y se retoma lo
		// detenido). La misma clave sólo con «Ya corregí la causa».
		if ( class_exists( 'Digitalisimo_Hosting_Webuzo' ) ) {
			$new_webuzo = $network ? (string) ( $o['webuzo_api_key'] ?? '' ) : (string) ( self::all()['webuzo_api_key'] ?? '' );
			// En una red la parada es de toda la red: sólo el superadministrador puede reintentar con la misma clave.
			if ( ! empty( $in['webuzo_unblock'] ) && ( ! is_multisite() || current_user_can( 'manage_network_options' ) ) ) { Digitalisimo_Hosting_Webuzo::forget( $new_webuzo ); Digitalisimo_Hosting_Webuzo::resume(); }
			// La parada de la clave reemplazada NO se olvida: si alguien vuelve a poner esa clave, sigue detenida.
			elseif ( $webuzo_key && '' !== $new_webuzo && ! hash_equals( $old_webuzo, $new_webuzo ) ) Digitalisimo_Hosting_Webuzo::resume();
		}
		if ( ! $network && ! empty( $o['provision_enabled'] ) && class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) {
			Digitalisimo_Hosting_Provisioning::ensure_daily();
			// Al reencenderla: las renovaciones cobradas que quedaron pendientes se encolan (las altas anteriores, no).
			if ( ! $was_on && ( ! array_key_exists( 'enabled', $o ) || ! empty( $o['enabled'] ) ) ) Digitalisimo_Hosting_Provisioning::sweep_renewals();
		}
		if ( ! $network && ! empty( $o['pricing_enabled'] ) && class_exists( 'Digitalisimo_Hosting_Pricing' ) ) Digitalisimo_Hosting_Pricing::ensure_schedule();
		if ( ! $network && class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::flush();
		return $o;
	}
	/** URL del panel de administración de Webuzo: sólo https con host (y puerto); cualquier otra cosa se descarta. */
	private static function webuzo_url( $raw ) {
		$url = trim( sanitize_text_field( (string) $raw ) );
		if ( '' === $url ) return '';
		if ( ! preg_match( '#^https://([a-z0-9.-]+|\[[0-9a-f:]+\])(:\d{1,5})?/?$#i', $url ) ) return '';
		// Ni loopback, ni link-local, ni red privada (salvo DIGITALISIMO_WEBUZO_ALLOW_PRIVATE): ahí se manda la clave root.
		if ( class_exists( 'Digitalisimo_Hosting_Webuzo' ) && ! Digitalisimo_Hosting_Webuzo::safe_url( $url ) ) return '';
		return rtrim( $url, '/' );
	}
	/** Nameservers separados por comas: sólo nombres de host; vacío = los de DIGITALÍSIMO. */
	private static function nameservers( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( (string) $raw ) ) as $ns ) if ( preg_match( '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $ns ) ) $out[] = $ns;
		return $out ? implode( ', ', array_slice( array_unique( $out ), 0, 5 ) ) : self::DEFAULT_NAMESERVERS;
	}

	public static function routes() {
		foreach ( array( 'digitalisimo-hosting/v1', 'digitalisimo/v1' ) as $namespace ) {
			register_rest_route( $namespace, '/check-domain', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'check_domain' ), 'permission_callback' => '__return_true', 'args' => array( 'domain' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
			register_rest_route( $namespace, '/product-price', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'price' ), 'permission_callback' => '__return_true' ) );
		}
	}

	/**
	 * ¿Está libre? Por el cliente de Namecheap (la clave en el cuerpo de un POST, nunca en la URL) y, si su extensión
	 * se vende aquí, con su producto y su precio en la misma respuesta.
	 */
	public static function check_domain( $request ) {
		$domain = strtolower( sanitize_text_field( (string) $request->get_param( 'domain' ) ) );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]{2,20}){1,2}$/', $domain ) ) return new WP_Error( 'invalid_domain', 'Dominio no válido.', array( 'status' => 400 ) );
		if ( ! self::rate_limit( 'domain-check' ) ) return new WP_Error( 'rate_limit', 'Demasiadas consultas. Intenta de nuevo en un minuto.', array( 'status' => 429 ) );
		$o = self::all();
		// El registrador de la extensión del dominio (el predeterminado o el de «Registrador por extensión»).
		$nc = class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::make( Digitalisimo_Hosting_Registrars::id_for_domain( $domain, $o ), $o ) : new Digitalisimo_Hosting_Namecheap( $o );
		if ( empty( $o['enabled'] ) || ! $nc->configured() ) return new WP_Error( 'not_configured', 'La consulta de dominios no está configurada.', array( 'status' => 503 ) );
		$r = class_exists( 'Digitalisimo_Hosting_Checkout' ) ? Digitalisimo_Hosting_Checkout::lookup( $domain, false ) : $nc->check( $domain, 15 );
		if ( 'ok' !== $r['kind'] ) return new WP_Error( 'namecheap_unavailable', 'No fue posible consultar el dominio. Intenta de nuevo en un momento.', array( 'status' => 503 ) );
		$out = array( 'domain' => $domain, 'available' => ! empty( $r['available'] ), 'premium' => ! empty( $r['premium'] ) );
		$split = class_exists( 'Digitalisimo_Hosting_Provisioning' ) ? Digitalisimo_Hosting_Provisioning::split( $domain ) : null;
		$map = self::products();
		$id = $split && isset( $map[ $split[2] ] ) ? (int) $map[ $split[2] ] : 0;
		if ( $id && function_exists( 'wc_get_product' ) && ( $product = wc_get_product( $id ) ) ) {
			$out['product_id'] = $id;
			$out['price'] = function_exists( 'wc_get_price_to_display' ) ? (string) wc_get_price_to_display( $product ) : (string) $product->get_price();
			$out['currency'] = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		}
		return rest_ensure_response( $out );
	}

	public static function price( $request ) {
		if ( ! function_exists( 'wc_get_product' ) ) return new WP_Error( 'woocommerce', 'WooCommerce no está activo.', array( 'status' => 503 ) );
		$product = wc_get_product( absint( $request->get_param( 'product_id' ) ) );
		if ( ! $product ) return new WP_Error( 'product_not_found', 'Producto no encontrado.', array( 'status' => 404 ) );
		return rest_ensure_response( array( 'price_html' => $product->get_price_html(), 'price' => $product->get_price(), 'currency' => get_woocommerce_currency_symbol() ) );
	}

	public static function assets() {
		if ( is_admin() || empty( self::all()['enabled'] ) ) return;
		self::enqueue_search();
	}

	/**
	 * Un solo script para el formulario de Elements (clásico y del editor atómico), [digitalisimo_domain_search] y el
	 * panel del carrito, con las extensiones de «Productos por extensión» (nunca una lista fija). Se puede llamar desde
	 * un shortcode que se pinta ya dentro del contenido (el script va al pie).
	 */
	public static function enqueue_search() {
		static $done = false;
		if ( $done || ! function_exists( 'wp_enqueue_script' ) ) return;
		$done = true;
		$o = self::all();
		wp_enqueue_style( 'digitalisimo-hosting-form', DIGITALISIMO_HOSTING_URL . 'assets/domain-search.css', array(), DIGITALISIMO_HOSTING_VERSION );
		wp_enqueue_script( 'digitalisimo-hosting-form', DIGITALISIMO_HOSTING_URL . 'assets/domain-form.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
		wp_localize_script( 'digitalisimo-hosting-form', 'digitalisimoHosting', array( 'endpoint' => rest_url( 'digitalisimo-hosting/v1/' ), 'selector' => $o['form_selector'], 'domainField' => $o['domain_field'], 'extensionField' => $o['extension_field'], 'products' => (object) self::products(), 'cart' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ) );
	}

	public static function result_shortcode() { if ( ! empty( self::all()['enabled'] ) ) self::enqueue_search(); return '<div class="digitalisimo-hosting-result" aria-live="polite"></div>'; }

	/**
	 * [digitalisimo_domain_search]: un bloque <div> (no un <form>), así funciona en cualquier página, también dentro de un
	 * formulario del editor atómico, donde un <form> anidado se perdería. Las extensiones son las configuradas.
	 */
	public static function legacy_shortcode() {
		if ( ! function_exists( 'wc_get_product' ) || empty( self::all()['enabled'] ) ) return '';
		return self::search_widget();
	}

	/** El buscador (shortcode y panel «Tu dominio» del carrito). */
	public static function search_widget() {
		self::enqueue_search();
		$options = '';
		foreach ( self::products() as $extension => $id ) $options .= '<option value="' . esc_attr( $extension ) . '" data-product-id="' . esc_attr( $id ) . '">' . esc_html( $extension ) . '</option>';
		if ( '' === $options ) return '<div class="digitalisimo-domain-search is-empty"><p>Por ahora no hay dominios a la venta.</p></div>';
		return '<div class="digitalisimo-domain-search" role="search"><label>Dominio <input type="text" name="domain" placeholder="minegocio" autocomplete="off" spellcheck="false" autocapitalize="none"></label><label>Extensión <select name="extension">' . $options . '</select></label><button type="button" class="digitalisimo-domain-search-button">Verificar dominio</button><div class="digitalisimo-domain-result" aria-live="polite"></div></div>';
	}

	/**
	 * Los ajustes se consultan cuando WooCommerce llama a cada gancho, no en `init`: antes se leían (una consulta, la
	 * opción va sin autoload porque guarda la clave de Namecheap) en cada petición del sitio con WooCommerce, también en
	 * cada pantalla del escritorio y en cada heartbeat, donde ninguno de estos ganchos corre.
	 */
	public static function cart_compatibility() {
		if ( class_exists( 'Digitalisimo_Integrations_WooCommerce' ) || ! function_exists( 'WC' ) ) return;
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'maybe_cart_domain' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'maybe_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'maybe_order_item_domain' ), 10, 4 );
		add_filter( 'digitalisimo_ai_context', array( __CLASS__, 'maybe_ai_context' ), 10, 3 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'maybe_validate_cart' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'maybe_replace_hosting' ), 10, 3 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'maybe_checkout_fields' ) );
		// El dominio antes de pagar: nuevo, uno que ya tengo o transferencia con su EPP.
		if ( class_exists( 'Digitalisimo_Hosting_Checkout' ) ) Digitalisimo_Hosting_Checkout::init();
	}
	/** Hosting activado y, si se pide, también la opción concreta (se lee al dispararse el gancho). */
	private static function on( $key = '' ) { $all = self::all(); return ! empty( $all['enabled'] ) && ( '' === $key || ! empty( $all[ $key ] ) ); }
	public static function maybe_cart_domain( $data, $product_id, $variation_id ) { return self::on() ? self::cart_domain( $data, $product_id, $variation_id ) : $data; }
	public static function maybe_cart_item_data( $data, $item ) { return self::on() ? self::cart_item_data( $data, $item ) : $data; }
	public static function maybe_order_item_domain( $item, $cart_item_key, $values, $order ) { if ( self::on() ) self::order_item_domain( $item, $cart_item_key, $values, $order ); }
	public static function maybe_ai_context( $context, $profile, $request = null ) { return self::on() ? self::ai_context( $context, $profile ) : $context; }
	public static function maybe_validate_cart() { if ( ! self::on() ) return; if ( class_exists( 'Digitalisimo_Hosting_Checkout' ) ) Digitalisimo_Hosting_Checkout::validate_cart(); elseif ( self::on( 'require_domain_hosting' ) ) self::validate_cart(); }
	public static function maybe_replace_hosting( $passed, $product_id, $quantity ) { return self::on( 'replace_hosting' ) ? self::replace_hosting( $passed, $product_id, $quantity ) : $passed; }
	public static function maybe_checkout_fields( $fields ) { return self::on( 'simplify_checkout' ) ? self::checkout_fields( $fields ) : $fields; }
	public static function cart_domain( $data, $product_id, $variation_id ) {
		if ( class_exists( 'Digitalisimo_Hosting_Checkout' ) ) return Digitalisimo_Hosting_Checkout::cart_item_data( $data, (int) $variation_id ? (int) $variation_id : (int) $product_id );
		$domain = $_REQUEST['digitalisimo_domain'] ?? $_REQUEST['domain_name'] ?? ''; if ( $domain ) $data['digitalisimo_domain'] = sanitize_text_field( wp_unslash( $domain ) ); return $data;
	}
	public static function cart_item_data( $data, $item ) {
		if ( empty( $item['digitalisimo_domain'] ) ) return $data;
		$kind = self::item_kind( $item );
		$data[] = array( 'name' => 'hosting' === $kind ? 'Dominio (ya lo tengo)' : ( 'transfer' === $kind ? 'Dominio a transferir' : 'Dominio' ), 'value' => $item['digitalisimo_domain'] );
		return $data;
	}
	public static function order_item_domain( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['digitalisimo_domain'] ) ) $item->add_meta_data( 'Dominio', $values['digitalisimo_domain'], true );
		if ( class_exists( 'Digitalisimo_Hosting_Checkout' ) ) Digitalisimo_Hosting_Checkout::order_item( $item, $values, $order );
	}
	public static function ai_context( $context, $profile ) { if ( 'chatbot' !== $profile || ! class_exists( 'WooCommerce' ) ) return $context; $products = wc_get_products( array( 'status' => 'publish', 'limit' => 8, 'return' => 'objects' ) ); foreach ( $products as $product ) $context .= "\n- " . $product->get_name() . ': ' . wp_strip_all_tags( $product->get_short_description() ) . ' ' . $product->get_price_html(); return $context; }
	private static function is_hosting( $product_id ) { return class_exists( 'Digitalisimo_Hosting_Products' ) && 'hosting' === Digitalisimo_Hosting_Products::kind( (int) $product_id ); }
	/** register, transfer, hosting o '' de una línea del carrito (la variación si la hay). */
	private static function item_kind( $item ) { return class_exists( 'Digitalisimo_Hosting_Products' ) ? Digitalisimo_Hosting_Products::kind( Digitalisimo_Hosting_Products::item_id( $item ) ) : ''; }
	public static function validate_cart() { $has_hosting = false; $has_domain = false; foreach ( WC()->cart->get_cart() as $item ) { $k = self::item_kind( $item ); $has_hosting = $has_hosting || 'hosting' === $k; $has_domain = $has_domain || 'register' === $k || 'transfer' === $k; } if ( $has_hosting xor $has_domain ) wc_add_notice( 'Agrega un dominio y un plan de hosting para continuar.', 'error' ); }
	public static function replace_hosting( $passed, $product_id, $quantity ) { if ( ! self::is_hosting( $product_id ) ) return $passed; foreach ( WC()->cart->get_cart() as $key => $item ) if ( 'hosting' === self::item_kind( $item ) ) WC()->cart->remove_cart_item( $key ); return $passed; }
	public static function checkout_fields( $fields ) {
		// Un dominio se registra a nombre del cliente y Namecheap exige su dirección completa: con un dominio en el
		// carrito, «simplificar» conserva los campos de dirección (como los pinta WooCommerce, obligatorios).
		$keep = array();
		// Sólo con la venta automática encendida (es la que registra a nombre del cliente): instalar no cambia el pago.
		if ( ! empty( self::all()['provision_enabled'] ) && self::cart_has_domain() ) foreach ( array( 'billing_last_name', 'billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' ) as $key ) if ( isset( $fields['billing'][ $key ] ) ) $keep[ $key ] = array( 'required' => true ) + $fields['billing'][ $key ];
		$fields = self::simple_checkout_fields( $fields );
		$fields['billing'] += $keep;
		return $fields;
	}
	private static function cart_has_domain() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) return false;
		foreach ( WC()->cart->get_cart() as $item ) { $k = self::item_kind( $item ); if ( 'register' === $k || 'transfer' === $k ) return true; }
		return false;
	}
	// ── Herramienta MCP de ajustes (DIGITALÍSIMO Elements: digitalisimo-hosting-settings) ───────────────────────────────

	/**
	 * Lo que la herramienta MCP puede escribir: lo de la tienda de este sitio (pedido del propietario del 2026-11-26: el
	 * agente que arma las landings configura Hosting sin el formulario).
	 */
	const MCP_WRITABLE = array( 'ask_domain', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'form_selector', 'domain_field', 'extension_field', 'webuzo_user_prefix', 'pricing_enabled', 'pricing_rounding', 'finder_order', 'finder_requirements', 'finder_upsell', 'registrar', 'registrar_by_tld' );
	/**
	 * Las claves que ya no existen (pedido del propietario del 2026-11-27: sin IDs ni «|»): los productos de dominio y de
	 * hosting se marcan en su editor o con digitalisimo-hosting-domain-product, y «Se compran juntos» es finder_upsell.
	 */
	const MCP_REMOVED = array(
		'domain_products'      => 'Ya no existe: los productos de dominio se detectan. Marca cada producto con la herramienta digitalisimo-hosting-domain-product (product_id, tld y kind) o en su editor (pestaña «DIGITALÍSIMO Hosting»); lo detectado sale en «domains».',
		'hosting_product_ids'  => 'Ya no existe: los planes son los productos marcados como «Hosting» en su editor; lo detectado sale en «hosting_products».',
		'hosting_category_ids' => 'Ya no existe: los planes son los productos marcados como «Hosting» en su editor; lo detectado sale en «hosting_products».',
		'finder_upsell_ids'    => 'Ya no existe: usa finder_upsell, una lista de product_id de los planes de «hosting_products» (vacía = todos).',
	);
	/**
	 * Lo que por MCP sólo se lee: la cuenta de Namecheap (y si se hereda de la red), el servidor y la clave de Webuzo, los
	 * nameservers, el token de Banxico y lo que enciende, apaga o adelanta la venta automática (el módulo `enabled`, que
	 * con la venta marcada la reanuda; la venta; el hosting con dominio propio sin revisión; los días de gracia antes de
	 * suspender) y los porcentajes de la fórmula de precios (colchón, margen, comisión, IVA y margen mínimo), que son el
	 * freno contra vender con pérdida: eso lo cambia una persona en el escritorio.
	 */
	const MCP_READONLY = array( 'api_user', 'username', 'api_key', 'client_ip', 'dynadot_api_key', 'inherit_network', 'webuzo_url', 'webuzo_api_user', 'webuzo_api_key', 'webuzo_insecure', 'nameservers', 'banxico_token', 'enabled', 'provision_enabled', 'own_domain_auto', 'grace_days', 'pricing_cushion', 'pricing_margin', 'pricing_fee', 'pricing_vat', 'pricing_min_margin' );
	const MCP_BOOL = array( 'enabled', 'ask_domain', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'pricing_enabled', 'webuzo_insecure', 'provision_enabled', 'own_domain_auto', 'inherit_network' );
	/** La cuenta de Namecheap: por MCP sólo se dice si está puesta, nunca su valor. */
	const NAMECHEAP_ACCOUNT = array( 'api_user', 'username', 'api_key', 'client_ip' );

	/** Valor de una clave para la respuesta MCP, con su tipo. */
	private static function mcp_value( $key, $value ) {
		if ( in_array( $key, self::MCP_BOOL, true ) ) return ! empty( $value );
		if ( in_array( $key, self::PERCENT_KEYS, true ) ) return (float) $value;
		if ( 'grace_days' === $key ) return (int) $value;
		// Listas por extensión: objetos y listas, nunca texto con «|» ni IDs sueltos.
		if ( 'registrar_by_tld' === $key || 'finder_requirements' === $key ) return (object) ( is_array( $value ) ? $value : array() );
		if ( 'finder_order' === $key ) return array_values( is_array( $value ) ? $value : array() );
		if ( 'finder_upsell' === $key ) {
			$out = array();
			foreach ( is_array( $value ) ? $value : array() as $id ) { $p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : null; $out[] = array( 'product_id' => (int) $id, 'name' => $p ? (string) $p->get_name() : '' ); }
			return $out;
		}
		return (string) $value;
	}

	/**
	 * Todos los ajustes que usa este sitio (con la herencia de la red y las constantes de wp-config.php aplicadas), sin
	 * ningún secreto: de las claves y de la cuenta de Namecheap sólo se dice si están puestas.
	 */
	public static function mcp_settings() {
		$o = self::all();
		$settings = array();
		$inherits = self::inherits();
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( in_array( $key, self::SECRET_KEYS, true ) || in_array( $key, self::NAMECHEAP_ACCOUNT, true ) ) continue;
			// Con «Heredar», el servidor Webuzo y los nameservers son de la red: como en la pantalla, no se muestran.
			if ( $inherits && in_array( $key, self::NETWORK_KEYS, true ) ) { $settings[ $key ] = null; continue; }
			$settings[ $key ] = self::mcp_value( $key, $o[ $key ] ?? '' );
		}
		$set = array();
		foreach ( array_unique( array_merge( self::NAMECHEAP_ACCOUNT, self::SECRET_KEYS ) ) as $key ) $set[ $key ] = '' !== (string) ( $o[ $key ] ?? '' );
		$constants = array();
		foreach ( self::CONSTANTS as $key => $constant ) $constants[ $constant ] = defined( $constant );
		return array(
			'ok'              => true,
			'inherited'       => $inherits ? array_values( array_diff( self::NETWORK_KEYS, self::NAMECHEAP_ACCOUNT, self::SECRET_KEYS ) ) : array(),
			'scope'           => array(
				'site'      => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
				'multisite' => is_multisite(),
				'writes'    => 'Sólo la opción de este sitio (digitalisimo_hosting_options); nunca la de la red.',
			),
			'settings'        => $settings,
			'secrets_set'     => $set,
			'namecheap'       => array(
				'configured' => $set['api_user'] && $set['username'] && $set['api_key'] && $set['client_ip'],
				'source'     => $inherits ? 'network' : 'site',
			),
			// Dynadot: sólo si la clave está puesta, nunca su valor.
			'dynadot'         => array(
				'configured' => $set['dynadot_api_key'],
				'source'     => $inherits ? 'network' : 'site',
			),
			'registrars'      => self::mcp_registrars( $o ),
			'wp_config'       => $constants,
			'writable'        => self::MCP_WRITABLE,
			'read_only'       => self::MCP_READONLY,
		) + ( class_exists( 'Digitalisimo_Hosting_Products' ) ? Digitalisimo_Hosting_Products::state() + array( 'tld_list' => self::mcp_tlds(), 'periods' => array_values( Digitalisimo_Hosting_Products::PERIODS ) ) : array() );
	}

	/** De dónde sale la lista de extensiones válidas (sin pedirla otra vez a los registradores). */
	private static function mcp_tlds() {
		if ( ! class_exists( 'Digitalisimo_Hosting_Lists' ) ) return null;
		$t = Digitalisimo_Hosting_Lists::tlds( false );
		return array( 'source' => $t['source'], 'count' => count( $t['list'] ), 'notice' => $t['notice'] );
	}

	/** Qué registrador atiende cada extensión que se vende (y cuáles tienen su cuenta puesta, nunca sus datos). */
	private static function mcp_registrars( array $o ) {
		if ( ! class_exists( 'Digitalisimo_Hosting_Registrars' ) ) return null;
		$R = 'Digitalisimo_Hosting_Registrars';
		$effective = array();
		foreach ( array_keys( self::products() ) as $ext ) $effective[ $ext ] = $R::id_for_ext( $ext, $o );
		$configured = array();
		foreach ( array_keys( $R::IDS ) as $id ) $configured[ $id ] = $R::make( $id, $o )->configured();
		return array( 'available' => array_keys( $R::IDS ), 'default' => $R::default_id( $o ), 'by_tld' => (object) $R::overrides( $o ), 'effective' => (object) $effective, 'configured' => $configured );
	}

	/**
	 * Las listas por MCP, con la extensión validada contra la lista de los registradores (o la base sin conexión):
	 *  - registrar_by_tld: {".mx": "dynadot"}; combina por extensión; "default" (o "") la regresa al predeterminado.
	 *  - finder_requirements: {".mx": "texto"}; combina por extensión; "" quita el aviso.
	 *  - finder_order: [".com", ".mx"] (reemplaza).
	 *  - finder_upsell: [product_id, …] de los planes marcados como Hosting (reemplaza; [] = todos).
	 * Devuelve el valor completo a guardar o WP_Error (y no se guarda nada).
	 */
	private static function mcp_list( $key, $value ) {
		$bad = function ( $m ) use ( $key ) { return new WP_Error( 'digitalisimo_hosting_invalid_value', $key . ': ' . $m . ' No se guardó nada.', array( 'status' => 400 ) ); };
		if ( is_object( $value ) ) $value = (array) $value;
		if ( ! is_array( $value ) ) return $bad( 'debe ser ' . ( 'finder_order' === $key || 'finder_upsell' === $key ? 'una lista' : 'un objeto por extensión, por ejemplo {".mx": "' . ( 'registrar_by_tld' === $key ? 'dynadot' : 'Requisito…' ) . '"}' ) . ' (el texto con «|» ya no se acepta).' );
		$ext_ok = function ( $raw ) { $e = Digitalisimo_Hosting_Products::normalize( is_scalar( $raw ) ? (string) $raw : '' ); return '' !== $e && ( ! class_exists( 'Digitalisimo_Hosting_Lists' ) || Digitalisimo_Hosting_Lists::valid_tld( $e ) || in_array( $e, Digitalisimo_Hosting_Products::sold_exts(), true ) ) ? $e : ''; };
		$saved = self::all()[ $key ];
		if ( 'registrar_by_tld' === $key ) {
			$map = is_array( $saved ) ? $saved : array();
			foreach ( $value as $raw => $id ) {
				$ext = $ext_ok( $raw );
				if ( '' === $ext ) return $bad( '«' . sanitize_text_field( (string) $raw ) . '» no es una extensión que vendan los registradores.' );
				$id = is_string( $id ) ? strtolower( trim( $id ) ) : '';
				if ( '' === $id || 'default' === $id ) { unset( $map[ $ext ] ); continue; }
				if ( ! class_exists( 'Digitalisimo_Hosting_Registrars' ) || ! Digitalisimo_Hosting_Registrars::valid( $id ) ) return $bad( 'el registrador de ' . $ext . ' debe ser ' . ( class_exists( 'Digitalisimo_Hosting_Registrars' ) ? '«' . implode( '», «', array_keys( Digitalisimo_Hosting_Registrars::IDS ) ) . '»' : '«namecheap»' ) . ' o «default».' );
				$map[ $ext ] = $id;
			}
			return $map;
		}
		if ( 'finder_requirements' === $key ) {
			$req = is_array( $saved ) ? $saved : array();
			foreach ( $value as $raw => $text ) {
				$ext = $ext_ok( $raw );
				if ( '' === $ext ) return $bad( '«' . sanitize_text_field( (string) $raw ) . '» no es una extensión que vendan los registradores.' );
				if ( ! is_scalar( $text ) && null !== $text ) return $bad( 'el aviso de ' . $ext . ' debe ser texto.' );
				$text = sanitize_text_field( (string) $text );
				if ( '' === $text ) unset( $req[ $ext ] ); else $req[ $ext ] = $text;
			}
			return $req;
		}
		if ( 'finder_order' === $key ) {
			$list = array();
			foreach ( array_values( $value ) as $raw ) { $ext = $ext_ok( $raw ); if ( '' === $ext ) return $bad( '«' . sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' ) . '» no es una extensión que vendan los registradores.' ); if ( ! in_array( $ext, $list, true ) ) $list[] = $ext; }
			return $list;
		}
		$ids = array();
		foreach ( array_values( $value ) as $raw ) {
			$id = is_array( $raw ) || is_object( $raw ) ? absint( ( (array) $raw )['product_id'] ?? 0 ) : absint( $raw );
			if ( ! $id || ! class_exists( 'Digitalisimo_Hosting_Products' ) || 'hosting' !== Digitalisimo_Hosting_Products::kind( $id ) ) return $bad( 'el producto ' . (int) $id . ' no está marcado como «Hosting» (mira hosting_products).' );
			if ( ! in_array( $id, $ids, true ) ) $ids[] = $id;
		}
		return $ids;
	}

	/**
	 * Combina $data con lo guardado en ESTE sitio (nunca en la red) y nunca reemplaza lo que no llega: misma validación que
	 * el formulario. Una clave desconocida, de sólo lectura o con un tipo que no corresponde detiene todo sin guardar
	 * nada. Devuelve el resultado guardado para comprobarlo.
	 *
	 * @return array|WP_Error
	 */
	public static function mcp_update( $data ) {
		if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'digitalisimo_hosting_forbidden', 'Configurar DIGITALÍSIMO Hosting exige la capacidad «manage_options».', array( 'status' => 403 ) );
		if ( ! is_array( $data ) || ! $data ) return new WP_Error( 'digitalisimo_hosting_invalid', 'data debe ser un objeto con las claves que cambian (por ejemplo {"registrar_by_tld": {".mx": "dynadot"}}).', array( 'status' => 400 ) );
		if ( class_exists( 'Digitalisimo_Hosting_Products' ) ) Digitalisimo_Hosting_Products::migrate();
		$removed = array_values( array_intersect( array_keys( $data ), array_keys( self::MCP_REMOVED ) ) );
		if ( $removed ) return new WP_Error( 'digitalisimo_hosting_removed_key', implode( ' ', array_map( function ( $k ) { return $k . ': ' . self::MCP_REMOVED[ $k ]; }, $removed ) ) . ' No se guardó nada.', array( 'status' => 400 ) );
		$known = array_keys( self::defaults() );
		$unknown = array_values( array_diff( array_keys( $data ), $known ) );
		if ( $unknown ) return new WP_Error( 'digitalisimo_hosting_unknown_key', 'Claves desconocidas: ' . implode( ', ', $unknown ) . '. Las que se pueden escribir: ' . implode( ', ', self::MCP_WRITABLE ) . '.', array( 'status' => 400 ) );
		$readonly = array_values( array_intersect( array_keys( $data ), self::MCP_READONLY ) );
		if ( $readonly ) return new WP_Error( 'digitalisimo_hosting_read_only', 'Por MCP no se pueden escribir: ' . implode( ', ', $readonly ) . ' (las cuentas de Namecheap y Dynadot, el servidor y las claves de Webuzo y Banxico, los nameservers y la venta automática se cambian en Digitalisimo → Hosting). No se guardó nada.', array( 'status' => 403 ) );
		$in = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, self::MCP_BOOL, true ) ) {
				$v = is_string( $value ) ? strtolower( trim( $value ) ) : $value;
				if ( true === $v || 1 === $v || '1' === $v || 'true' === $v || 'yes' === $v ) $in[ $key ] = 1;
				elseif ( false === $v || 0 === $v || '0' === $v || 'false' === $v || 'no' === $v || '' === $v ) $in[ $key ] = 0;
				else return new WP_Error( 'digitalisimo_hosting_invalid_value', $key . ' debe ser true o false. No se guardó nada.', array( 'status' => 400 ) );
				continue;
			}
			if ( in_array( $key, self::PERCENT_KEYS, true ) || 'grace_days' === $key ) {
				if ( ! is_numeric( is_string( $value ) ? str_replace( ',', '.', $value ) : $value ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', $key . ' debe ser un número. No se guardó nada.', array( 'status' => 400 ) );
				$in[ $key ] = is_string( $value ) ? str_replace( ',', '.', $value ) : $value;
				continue;
			}
			if ( 'registrar' === $key && ! ( class_exists( 'Digitalisimo_Hosting_Registrars' ) && is_string( $value ) && Digitalisimo_Hosting_Registrars::valid( $value ) ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'registrar admite ' . ( class_exists( 'Digitalisimo_Hosting_Registrars' ) ? '«' . implode( '» o «', array_keys( Digitalisimo_Hosting_Registrars::IDS ) ) . '»' : '«namecheap»' ) . '. No se guardó nada.', array( 'status' => 400 ) );
			if ( in_array( $key, array( 'registrar_by_tld', 'finder_order', 'finder_requirements', 'finder_upsell' ), true ) ) {
				$v = self::mcp_list( $key, $value );
				if ( is_wp_error( $v ) ) return $v;
				$in[ $key ] = $v;
				continue;
			}
			if ( 'pricing_rounding' === $key && ! in_array( (string) $value, array( '10', '9', 'end9', 'none' ), true ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', 'pricing_rounding admite «10», «9», «end9» o «none». No se guardó nada.', array( 'status' => 400 ) );
			if ( ! is_scalar( $value ) ) return new WP_Error( 'digitalisimo_hosting_invalid_value', $key . ' debe ser texto. No se guardó nada.', array( 'status' => 400 ) );
			$in[ $key ] = (string) $value;
		}
		$before = self::local();
		$saved = self::store( false, $in );
		$changed = array(); $adjusted = array();
		foreach ( array_keys( $in ) as $key ) {
			$was = array_key_exists( $key, $before ) ? $before[ $key ] : null;
			$now = $saved[ $key ] ?? null;
			if ( is_array( $now ) || is_array( $was ) ) {
				if ( wp_json_encode( $was ) !== wp_json_encode( $now ) || null === $was ) $changed[] = $key;
				if ( wp_json_encode( $in[ $key ] ) !== wp_json_encode( $now ) ) $adjusted[ $key ] = self::mcp_value( $key, $now );
				continue;
			}
			if ( (string) $was !== (string) $now || null === $was ) $changed[] = $key;
			$sent = in_array( $key, self::MCP_BOOL, true ) ? (int) $in[ $key ] : $in[ $key ];
			if ( in_array( $key, self::PERCENT_KEYS, true ) || 'grace_days' === $key ? (float) $sent !== (float) $now : preg_replace( '/\r\n?/', "\n", trim( (string) $sent ) ) !== preg_replace( '/\r\n?/', "\n", trim( (string) $now ) ) ) $adjusted[ $key ] = self::mcp_value( $key, $now );
		}
		$out = self::mcp_settings();
		$out['changed'] = $changed;
		$out['adjusted'] = (object) $adjusted;
		return $out;
	}

	private static function simple_checkout_fields( $fields ) { $fields['billing'] = array( 'billing_first_name' => array( 'label' => 'Nombre', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_phone' => array( 'label' => 'Teléfono', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_email' => array( 'label' => 'Correo electrónico', 'required' => true, 'class' => array( 'form-row-wide' ) ) ); unset( $fields['shipping'], $fields['order'] ); return $fields; }
}
