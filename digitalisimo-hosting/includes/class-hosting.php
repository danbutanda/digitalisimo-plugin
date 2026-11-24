<?php
defined( 'ABSPATH' ) || exit;

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
		// Venta automática: cuentas de Webuzo, registro en Namecheap, accesos por correo, renovación y suspensión.
		if ( class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) Digitalisimo_Hosting_Provisioning::init();
	}

	private static function defaults() {
		return array(
			'inherit_network'       => 0,
			'enabled'               => 1,
			'api_user'              => '',
			'username'              => '',
			'api_key'               => '',
			'client_ip'             => '',
			'domain_products'       => ".com|\n.mx|\n.org|",
			'hosting_product_ids'   => '',
			'hosting_category_ids'  => '',
			'require_domain_hosting'=> 0,
			'replace_hosting'       => 0,
			'simplify_checkout'     => 0,
			'form_selector'         => '.elementor-form',
			'domain_field'          => 'form_fields[dominio]',
			'extension_field'       => 'form_fields[extension]',
			// Venta automática (apagada hasta que el administrador la enciende): al pagarse un pedido crea la cuenta en
			// Webuzo, registra el dominio en Namecheap y envía los accesos; después avisa, renueva y suspende.
			'provision_enabled'     => 0,
			'webuzo_url'            => '',
			'webuzo_api_user'       => 'root',
			'webuzo_api_key'        => '',
			'webuzo_insecure'       => 0,
			'nameservers'           => self::DEFAULT_NAMESERVERS,
			'webuzo_user_prefix'    => '',
			'grace_days'            => 7,
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
	const SECRET_KEYS = array( 'api_key', 'webuzo_api_key' );

	private static function local() { return (array) get_option( self::OPTION, array() ); }
	private static function network() { return (array) get_site_option( self::OPTION, array() ); }
	/**
	 * Lo que un sitio puede heredar de la red: la cuenta de Namecheap. Lo propio de cada tienda (productos por extensión,
	 * IDs de productos y categorías de WooCommerce, casillas que cambian el carrito y el checkout, el formulario) es
	 * siempre del sitio, también con «Heredar» (revisión del 2026-11-22: los IDs de otra tienda no sirven en ésta).
	 */
	const NETWORK_KEYS = array( 'api_user', 'username', 'api_key', 'client_ip', 'webuzo_url', 'webuzo_api_user', 'webuzo_api_key', 'webuzo_insecure', 'nameservers' );
	/** ¿Este sitio usa la cuenta de Namecheap de la red? Sólo si su administrador marcó «Heredar» (opcional). */
	private static function inherits() { return is_multisite() && ! empty( self::local()['inherit_network'] ); }
	/** Aviso único: el sitio heredaba la red y ya no hereda su tienda (lo marca la visita a la pantalla Hosting). */
	const STORE_NOTICE = 'digitalisimo_hosting_store_notice_seen';
	/** Lo de la tienda que la red define distinto del sitio: lo que un sitio con «Heredar» usaba hasta esta versión. */
	private static function store_changed() {
		if ( ! self::inherits() ) return false;
		$n = self::network(); $o = wp_parse_args( self::local(), self::defaults() );
		foreach ( array_diff( array_keys( self::defaults() ), self::NETWORK_KEYS, array( 'inherit_network' ) ) as $key ) {
			if ( array_key_exists( $key, $n ) && preg_replace( '/\r\n?/', "\n", (string) $n[ $key ] ) !== preg_replace( '/\r\n?/', "\n", (string) $o[ $key ] ) ) return true;
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
		echo '<div class="notice notice-warning"><p><strong>DIGITALÍSIMO Hosting:</strong> este sitio ya no usa la configuración de tienda de la red (productos por extensión, IDs de productos y categorías, opciones del checkout); de la red sólo hereda la cuenta de Namecheap. <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Revisa la configuración de Hosting de este sitio</a>.</p></div>';
	}
	/** ¿La red tiene una cuenta de Namecheap guardada? */
	private static function network_account() { $n = self::network(); return '' !== (string) ( $n['api_user'] ?? '' ) || '' !== (string) ( $n['api_key'] ?? '' ); }
	/** Lo que usa el sitio: lo suyo y, si hereda, la cuenta de Namecheap de la red en lugar de la suya (que se conserva). */
	private static function all() {
		$o = wp_parse_args( self::local(), self::defaults() );
		if ( self::inherits() ) { $n = self::network(); foreach ( self::NETWORK_KEYS as $key ) $o[ $key ] = (string) ( $n[ $key ] ?? '' ); }
		foreach ( self::CONSTANTS as $key => $constant ) if ( defined( $constant ) ) $o[ $key ] = (string) constant( $constant );
		return $o;
	}
	/** Los ajustes que usa este sitio (con la herencia de la red y las constantes de wp-config.php ya aplicadas). */
	public static function settings() { return self::all(); }
	/** Extensión => ID del producto de WooCommerce que la vende (lo que el propietario liga en «Productos por extensión»). */
	public static function domain_products() { return self::products(); }
	/** ¿El producto es un plan de hosting según los IDs o las categorías de los ajustes? */
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
	private static function rate_limit( $scope, $limit = 20 ) {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$key = 'digitalisimo_hosting_rate_' . md5( wp_salt( 'nonce' ) . '|' . sanitize_key( $scope ) . '|' . $ip );
		// Con la cuenta de la red, el límite es de toda la red (una cuenta, un tope), no de cada sitio.
		$shared = self::inherits();
		$count = (int) ( $shared ? get_site_transient( $key ) : get_transient( $key ) );
		if ( $count >= $limit ) return false;
		if ( $shared ) set_site_transient( $key, $count + 1, MINUTE_IN_SECONDS ); else set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function products() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::all()['domain_products'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $parts ) && $parts[0] && absint( $parts[1] ) ) $out[ strtolower( $parts[0] ) ] = absint( $parts[1] );
		}
		return $out;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
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
		echo '<div class="wrap"><h1>Digitalisimo · Hosting' . ( $network ? ' de red' : '' ) . '</h1><p>' . ( $network ? 'Cuenta de Namecheap que los sitios de la red pueden usar.' : 'Dominios, planes de hosting y compra con WooCommerce.' ) . '</p>';
		if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>Configuración ' . ( $network ? 'de red ' : '' ) . 'guardada.</p></div>';
		if ( $network ) echo '<p class="description">Un sitio la usa sólo si su administrador marca «Heredar de la red la cuenta de Namecheap» en su pantalla Hosting. Los productos, las categorías y el checkout de WooCommerce son de cada sitio y no se heredan.</p>';
		elseif ( is_multisite() && ! $inherits && self::network_account() ) echo '<div class="notice notice-info inline"><p>La red tiene una cuenta de Namecheap configurada. Para usarla en este sitio, marca «Heredar de la red la cuenta de Namecheap» y guarda.</p></div>';
		echo '<form method="post">';
		wp_nonce_field( $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		if ( ! $network ) self::check( 'enabled', 'Activar dominios y flujo WooCommerce', $o );
		echo '<h2>Namecheap</h2>';
		if ( ! $network && is_multisite() ) echo '<p><label><input type="hidden" name="hosting[inherit_network]" value="0"><input type="checkbox" name="hosting[inherit_network]" value="1" ' . checked( $inherits, true, false ) . '> <strong>Heredar de la red la cuenta de Namecheap</strong></label><br><span class="description">' . ( $inherits ? 'Este sitio consulta dominios con la cuenta de la red. Al desmarcarla vuelve a usar la suya, que se conserva.' : 'Los productos, las categorías y el checkout son siempre de este sitio.' ) . '</span></p>';
		if ( $inherits ) echo '<p class="description">Usa la cuenta de Namecheap de la red.</p>';
		else { self::field( 'api_user', 'Usuario API Namecheap', $o ); self::field( 'username', 'Usuario Namecheap', $o ); self::field( 'api_key', 'Clave API Namecheap', $o, 'password' ); self::field( 'client_ip', 'IP autorizada por Namecheap', $o ); }
		echo '<h2>Webuzo (cuentas de hosting)</h2>';
		if ( class_exists( 'Digitalisimo_Hosting_Webuzo' ) ) Digitalisimo_Hosting_Webuzo::settings_banner( $network );
		if ( $inherits ) echo '<p class="description">Usa el servidor Webuzo y los nameservers de la red.</p>';
		else {
			self::field( 'webuzo_url', 'URL del panel de administración de Webuzo', $o, 'text', 'Por ejemplo https://servidor.ejemplo.com:2005 (el puerto 2005 es el de administración; los clientes entran por el 2003).' );
			self::field( 'webuzo_api_user', 'Usuario de la API de Webuzo', $o, 'text', 'El usuario dueño de la clave de API (normalmente root).' );
			self::field( 'webuzo_api_key', 'Clave de la API de Webuzo', $o, 'password', 'Webuzo → Ajustes → API Keys. Si Webuzo la rechaza, el aprovisionamiento se detiene sin reintentar hasta que guardes una nueva: Webuzo bloquea la IP tras varios intentos fallidos.' );
			self::check( 'webuzo_insecure', 'No verificar el certificado del panel (sólo si usa un certificado autofirmado)', $o );
			self::field( 'nameservers', 'Nameservers de los dominios', $o, 'text', 'Separados por comas. Cada dominio registrado se apunta a ellos.' );
		}
		if ( ! $network ) {
			echo '<h2>Venta automática</h2>';
			self::check( 'provision_enabled', 'Crear la cuenta, registrar el dominio y enviar los accesos al pagarse un pedido', $o );
			echo '<p class="description">Los planes se reconocen por el paquete de Webuzo del producto (campo personalizado <code>_digitalisimo_webuzo_plan</code>) o por los IDs y categorías de hosting de abajo. La lista de servicios está en WooCommerce → Servicios de hosting.</p>';
			self::field( 'webuzo_user_prefix', 'Prefijo de los usuarios de Webuzo', $o, 'text', 'Opcional, sólo letras y números (por ejemplo «prueba» para un ensayo).' );
			self::field( 'grace_days', 'Días de gracia antes de suspender', $o, 'number', 'Días después del vencimiento sin pago antes de suspender la cuenta en Webuzo.' );
		}
		if ( ! $network ) {
			self::area( 'domain_products', 'Productos por extensión', $o, 'Una línea por extensión: .com|123' );
			echo '<h2>Compra de hosting</h2>';
			self::area( 'hosting_product_ids', 'IDs de productos de hosting', $o, 'IDs separados por comas.' ); self::area( 'hosting_category_ids', 'IDs de categorías de hosting', $o, 'IDs separados por comas.' ); self::check( 'require_domain_hosting', 'Exigir dominio y hosting juntos', $o ); self::check( 'replace_hosting', 'Reemplazar el plan de hosting al elegir otro', $o ); self::check( 'simplify_checkout', 'Simplificar checkout', $o );
			echo '<h2>Formulario de DIGITALÍSIMO Elements</h2>';
			self::field( 'form_selector', 'Selector CSS del formulario', $o ); self::field( 'domain_field', 'Campo dominio', $o ); self::field( 'extension_field', 'Campo extensión', $o );
			echo '<p>Usa <code>[resultado_dominio]</code> en el widget Formulario de DIGITALÍSIMO Elements. El shortcode anterior <code>[digitalisimo_domain_search]</code> se conserva para páginas existentes.</p>';
		}
		submit_button( $network ? 'Guardar Hosting de red' : 'Guardar Hosting', 'primary', $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		echo '</form></div>';
	}

	/** El campo de la clave: la máscara y «Guardada» con una guardada (nunca la clave), seleccionado entero al enfocarlo. */
	private static function field( $key, $label, $o, $type = 'text', $help = '' ) {
		if ( isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] ) ) {
			echo '<p><strong>' . esc_html( $label ) . '</strong><br><span class="description">Definida en wp-config.php (<code>' . esc_html( self::CONSTANTS[ $key ] ) . '</code>).</span></p>';
			return;
		}
		$secret = 'password' === $type; $saved = $secret && '' !== (string) ( $o[ $key ] ?? '' );
		$value = $secret ? ( $saved ? self::MASK : '' ) : ( $o[ $key ] ?? '' );
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="' . ( 'number' === $type ? 'small-text' : 'regular-text' ) . '" type="' . esc_attr( $type ) . '" name="hosting[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . ( $secret ? ' autocomplete="new-password" onfocus="this.select()"' : '' ) . ( 'number' === $type ? ' min="0" max="90" step="1"' : '' ) . '></label>';
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
	private static function save( $network ) {
		$o = $network ? self::network() : self::local();
		$in = (array) wp_unslash( $_POST['hosting'] ?? array() );
		$text = $network ? array( 'api_user', 'username', 'client_ip', 'webuzo_api_user' ) : array( 'api_user', 'username', 'client_ip', 'form_selector', 'domain_field', 'extension_field', 'webuzo_api_user' );
		foreach ( $text as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = sanitize_text_field( $in[ $key ] );
		// Las mismas reglas en el sitio y en la red: la URL del panel sólo https con host, los nameservers sólo nombres
		// de host válidos (vacío = los de DIGITALÍSIMO).
		if ( array_key_exists( 'webuzo_url', $in ) ) $o['webuzo_url'] = self::webuzo_url( $in['webuzo_url'] );
		if ( array_key_exists( 'nameservers', $in ) ) $o['nameservers'] = self::nameservers( $in['nameservers'] );
		if ( array_key_exists( 'webuzo_insecure', $in ) ) $o['webuzo_insecure'] = empty( $in['webuzo_insecure'] ) ? 0 : 1;
		if ( ! $network ) {
			foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids' ) as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = sanitize_textarea_field( $in[ $key ] );
			foreach ( array( 'enabled', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'provision_enabled' ) as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
			if ( array_key_exists( 'webuzo_user_prefix', $in ) ) $o['webuzo_user_prefix'] = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $in['webuzo_user_prefix'] ) ), 0, 6 );
			if ( array_key_exists( 'grace_days', $in ) ) $o['grace_days'] = min( 90, absint( $in['grace_days'] ) );
			$o['inherit_network'] = is_multisite() && ! empty( $in['inherit_network'] ) ? 1 : 0;
		}
		$webuzo_key = false;
		foreach ( self::SECRET_KEYS as $key ) {
			if ( ! array_key_exists( $key, $in ) ) continue;
			$typed = self::unmask( $in[ $key ] );
			if ( '' !== $typed ) { $o[ $key ] = $typed; $webuzo_key = $webuzo_key || 'webuzo_api_key' === $key; }
			elseif ( ! empty( $in[ $key . '_remove' ] ) ) $o[ $key ] = '';
		}
		if ( $network ) update_site_option( self::OPTION, $o ); else update_option( self::OPTION, $o, false );
		// Una clave de Webuzo escrita a mano levanta la parada por «clave rechazada» (y vuelve a encolar lo detenido).
		if ( $webuzo_key && class_exists( 'Digitalisimo_Hosting_Webuzo' ) ) Digitalisimo_Hosting_Webuzo::unblock();
		if ( ! $network && ! empty( $o['provision_enabled'] ) && class_exists( 'Digitalisimo_Hosting_Provisioning' ) ) Digitalisimo_Hosting_Provisioning::ensure_daily();
	}
	/** URL del panel de administración de Webuzo: sólo https con host (y puerto); cualquier otra cosa se descarta. */
	private static function webuzo_url( $raw ) {
		$url = trim( sanitize_text_field( (string) $raw ) );
		if ( '' === $url ) return '';
		if ( ! preg_match( '#^https://([a-z0-9.-]+|\[[0-9a-f:]+\])(:\d{1,5})?/?$#i', $url ) ) return '';
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

	public static function check_domain( $request ) {
		$domain = strtolower( sanitize_text_field( $request->get_param( 'domain' ) ) );
		if ( ! preg_match( '/^[a-z0-9-]+\.[a-z]{2,20}$/', $domain ) ) return new WP_Error( 'invalid_domain', 'Dominio no válido.', array( 'status' => 400 ) );
		if ( ! self::rate_limit( 'domain-check' ) ) return new WP_Error( 'rate_limit', 'Demasiadas consultas. Intenta de nuevo en un minuto.', array( 'status' => 429 ) );
		$o = self::all();
		if ( empty( $o['enabled'] ) || ! $o['api_key'] || ! $o['api_user'] || ! $o['username'] || ! $o['client_ip'] ) return new WP_Error( 'not_configured', 'La consulta de dominios no está configurada.', array( 'status' => 503 ) );
		$response = wp_remote_get( add_query_arg( array( 'ApiUser' => $o['api_user'], 'ApiKey' => $o['api_key'], 'UserName' => $o['username'], 'ClientIp' => $o['client_ip'], 'Command' => 'namecheap.domains.check', 'DomainList' => $domain ), 'https://api.namecheap.com/xml.response' ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) return $response;
		$xml = simplexml_load_string( wp_remote_retrieve_body( $response ) );
		if ( ! $xml || ! isset( $xml->CommandResponse->DomainCheckResult ) ) return new WP_Error( 'namecheap_response', 'Respuesta inválida de Namecheap.', array( 'status' => 502 ) );
		return rest_ensure_response( array( 'available' => 'true' === strtolower( (string) $xml->CommandResponse->DomainCheckResult['Available'] ) ) );
	}

	public static function price( $request ) {
		if ( ! function_exists( 'wc_get_product' ) ) return new WP_Error( 'woocommerce', 'WooCommerce no está activo.', array( 'status' => 503 ) );
		$product = wc_get_product( absint( $request->get_param( 'product_id' ) ) );
		if ( ! $product ) return new WP_Error( 'product_not_found', 'Producto no encontrado.', array( 'status' => 404 ) );
		return rest_ensure_response( array( 'price_html' => $product->get_price_html(), 'price' => $product->get_price(), 'currency' => get_woocommerce_currency_symbol() ) );
	}

	public static function assets() {
		if ( is_admin() || empty( self::all()['enabled'] ) ) return;
		$o = self::all();
		wp_enqueue_style( 'digitalisimo-hosting-form', DIGITALISIMO_HOSTING_URL . 'assets/domain-search.css', array(), DIGITALISIMO_HOSTING_VERSION );
		wp_enqueue_script( 'digitalisimo-hosting-form', DIGITALISIMO_HOSTING_URL . 'assets/domain-form.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
		wp_localize_script( 'digitalisimo-hosting-form', 'digitalisimoHosting', array( 'endpoint' => rest_url( 'digitalisimo-hosting/v1/' ), 'selector' => $o['form_selector'], 'domainField' => $o['domain_field'], 'extensionField' => $o['extension_field'], 'products' => self::products(), 'cart' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ) );
	}

	private static function legacy_assets() {
		wp_enqueue_style( 'digitalisimo-domain-search', DIGITALISIMO_HOSTING_URL . 'assets/domain-search.css', array(), DIGITALISIMO_HOSTING_VERSION );
		wp_enqueue_script( 'digitalisimo-domain-search', DIGITALISIMO_HOSTING_URL . 'assets/domain-search.js', array(), DIGITALISIMO_HOSTING_VERSION, true );
		wp_localize_script( 'digitalisimo-domain-search', 'digitalisimoDomainSearch', array( 'endpoint' => rest_url( 'digitalisimo/v1/' ) ) );
	}

	public static function result_shortcode() { return '<div class="digitalisimo-hosting-result" aria-live="polite"></div>'; }
	public static function legacy_shortcode() {
		if ( ! function_exists( 'wc_get_product' ) || empty( self::all()['enabled'] ) ) return '';
		self::legacy_assets();
		$options = '';
		foreach ( self::products() as $extension => $id ) $options .= '<option value="' . esc_attr( $extension ) . '" data-product-id="' . esc_attr( $id ) . '">' . esc_html( $extension ) . '</option>';
		return '<form class="digitalisimo-domain-search"><label>Dominio <input required name="domain" pattern="[A-Za-z0-9-]+" autocomplete="off"></label><label>Extensión <select required name="extension">' . $options . '</select></label><button type="submit">Verificar dominio</button><div class="digitalisimo-domain-result" aria-live="polite"></div></form>';
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
	}
	/** Hosting activado y, si se pide, también la opción concreta (se lee al dispararse el gancho). */
	private static function on( $key = '' ) { $all = self::all(); return ! empty( $all['enabled'] ) && ( '' === $key || ! empty( $all[ $key ] ) ); }
	public static function maybe_cart_domain( $data, $product_id, $variation_id ) { return self::on() ? self::cart_domain( $data, $product_id, $variation_id ) : $data; }
	public static function maybe_cart_item_data( $data, $item ) { return self::on() ? self::cart_item_data( $data, $item ) : $data; }
	public static function maybe_order_item_domain( $item, $cart_item_key, $values, $order ) { if ( self::on() ) self::order_item_domain( $item, $cart_item_key, $values, $order ); }
	public static function maybe_ai_context( $context, $profile, $request = null ) { return self::on() ? self::ai_context( $context, $profile ) : $context; }
	public static function maybe_validate_cart() { if ( self::on( 'require_domain_hosting' ) ) self::validate_cart(); }
	public static function maybe_replace_hosting( $passed, $product_id, $quantity ) { return self::on( 'replace_hosting' ) ? self::replace_hosting( $passed, $product_id, $quantity ) : $passed; }
	public static function maybe_checkout_fields( $fields ) { return self::on( 'simplify_checkout' ) ? self::checkout_fields( $fields ) : $fields; }
	public static function cart_domain( $data, $product_id, $variation_id ) { $domain = $_REQUEST['digitalisimo_domain'] ?? $_REQUEST['domain_name'] ?? ''; if ( $domain ) $data['digitalisimo_domain'] = sanitize_text_field( wp_unslash( $domain ) ); return $data; }
	public static function cart_item_data( $data, $item ) { if ( ! empty( $item['digitalisimo_domain'] ) ) $data[] = array( 'name' => 'Dominio', 'value' => $item['digitalisimo_domain'] ); return $data; }
	public static function order_item_domain( $item, $cart_item_key, $values, $order ) { if ( ! empty( $values['digitalisimo_domain'] ) ) $item->add_meta_data( 'Dominio', $values['digitalisimo_domain'], true ); }
	public static function ai_context( $context, $profile ) { if ( 'chatbot' !== $profile || ! class_exists( 'WooCommerce' ) ) return $context; $products = wc_get_products( array( 'status' => 'publish', 'limit' => 8, 'return' => 'objects' ) ); foreach ( $products as $product ) $context .= "\n- " . $product->get_name() . ': ' . wp_strip_all_tags( $product->get_short_description() ) . ' ' . $product->get_price_html(); return $context; }
	private static function ids( $key ) { return array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) self::all()[ $key ] ) ) ); }
	private static function is_hosting( $product_id ) { $products = self::ids( 'hosting_product_ids' ); $categories = self::ids( 'hosting_category_ids' ); return in_array( $product_id, $products, true ) || ( $categories && has_term( $categories, 'product_cat', $product_id ) ); }
	public static function validate_cart() { $has_hosting = false; $has_domain = false; $domain_ids = array_values( self::products() ); foreach ( WC()->cart->get_cart() as $item ) { $id = $item['product_id']; $has_hosting = $has_hosting || self::is_hosting( $id ); $has_domain = $has_domain || in_array( $id, $domain_ids, true ); } if ( $has_hosting xor $has_domain ) wc_add_notice( 'Agrega un dominio y un plan de hosting para continuar.', 'error' ); }
	public static function replace_hosting( $passed, $product_id, $quantity ) { if ( ! self::is_hosting( $product_id ) ) return $passed; foreach ( WC()->cart->get_cart() as $key => $item ) if ( self::is_hosting( $item['product_id'] ) ) WC()->cart->remove_cart_item( $key ); return $passed; }
	public static function checkout_fields( $fields ) {
		// Un dominio se registra a nombre del cliente y Namecheap exige su dirección completa: con un dominio en el
		// carrito, «simplificar» conserva los campos de dirección (como los pinta WooCommerce, obligatorios).
		$keep = array();
		if ( self::cart_has_domain() ) foreach ( array( 'billing_last_name', 'billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode' ) as $key ) if ( isset( $fields['billing'][ $key ] ) ) $keep[ $key ] = array( 'required' => true ) + $fields['billing'][ $key ];
		$fields = self::simple_checkout_fields( $fields );
		$fields['billing'] += $keep;
		return $fields;
	}
	private static function cart_has_domain() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->cart ) return false;
		$domain_ids = array_values( self::products() );
		foreach ( WC()->cart->get_cart() as $item ) if ( in_array( (int) ( $item['product_id'] ?? 0 ), $domain_ids, true ) ) return true;
		return false;
	}
	private static function simple_checkout_fields( $fields ) { $fields['billing'] = array( 'billing_first_name' => array( 'label' => 'Nombre', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_phone' => array( 'label' => 'Teléfono', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_email' => array( 'label' => 'Correo electrónico', 'required' => true, 'class' => array( 'form-row-wide' ) ) ); unset( $fields['shipping'], $fields['order'] ); return $fields; }
}
