<?php
defined( 'ABSPATH' ) || exit;

/** Dominios, hosting y el flujo WooCommerce que antes pertenecía a Ecommerce. */
class Digitalisimo_Hosting {
	const OPTION = 'digitalisimo_hosting_options';
	const LEGACY_OPTION = 'digitalisimo_integrations_options';
	const LEGACY_INHERIT = 'digitalisimo_module_network_inherit';
	const LEGACY_MIGRATED = 'digitalisimo_hosting_ecommerce_migrated';
	const LEGACY_NETWORK_MIGRATED = 'digitalisimo_hosting_ecommerce_network_migrated';

	public static function init() {
		self::migrate_legacy_options();
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'resultado_dominio', array( __CLASS__, 'result_shortcode' ) );
		add_shortcode( 'digitalisimo_hosting_resultado_dominio', array( __CLASS__, 'result_shortcode' ) );
		// Compatibilidad con páginas creadas cuando este flujo pertenecía a Ecommerce.
		add_shortcode( 'digitalisimo_domain_search', array( __CLASS__, 'legacy_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'cart_compatibility' ), 20 );
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
		);
	}

	private static function local() { return (array) get_option( self::OPTION, array() ); }
	private static function all() {
		$local = self::local();
		$source = ( is_multisite() && ! empty( $local['inherit_network'] ) ) ? (array) get_site_option( self::OPTION, array() ) : $local;
		return wp_parse_args( $source, self::defaults() );
	}
	private static function origin_label() { return is_multisite() && ! empty( self::local()['inherit_network'] ) ? 'Configuración de red' : 'Este sitio'; }

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

		if ( get_option( self::LEGACY_MIGRATED ) ) return;
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
		update_option( self::LEGACY_MIGRATED, time(), false );
	}

	private static function migrated_values( $target, $legacy, $map ) {
		foreach ( $map as $old => $new ) if ( array_key_exists( $old, $legacy ) && ! array_key_exists( $new, $target ) ) $target[ $new ] = $legacy[ $old ];
		return $target;
	}

	/** Contador efímero por visitante; no se guarda la IP ni se escribe en logs. */
	private static function rate_limit( $scope, $limit = 20 ) {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$key = 'digitalisimo_hosting_rate_' . md5( wp_salt( 'nonce' ) . '|' . sanitize_key( $scope ) . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) return false;
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
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

	private static function form( $network, $saved = false ) {
		$o = $network ? wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() ) : self::all();
		echo '<div class="wrap"><h1>Digitalisimo · Hosting' . ( $network ? ' de red' : '' ) . '</h1><p>Dominios, planes de hosting y compra con WooCommerce.</p>';
		if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>Configuración ' . ( $network ? 'de red ' : '' ) . 'guardada.</p></div>';
		echo '<form method="post">';
		wp_nonce_field( $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		if ( ! $network && is_multisite() ) echo '<p class="notice notice-info inline"><label><input type="hidden" name="hosting[inherit_network]" value="0"><input type="checkbox" name="hosting[inherit_network]" value="1" ' . checked( ! empty( self::local()['inherit_network'] ), true, false ) . '> <strong>Heredar configuración de la red</strong></label><br><span class="description">Valor efectivo: ' . esc_html( self::origin_label() ) . '. Al desactivar herencia, este sitio puede personalizar su configuración.</span></p>';
		self::check( 'enabled', 'Activar dominios y flujo WooCommerce', $o );
		echo '<h2>Namecheap</h2>';
		self::field( 'api_user', 'Usuario API Namecheap', $o ); self::field( 'username', 'Usuario Namecheap', $o ); self::field( 'api_key', 'Clave API Namecheap', $o, 'password' ); self::field( 'client_ip', 'IP autorizada por Namecheap', $o ); self::area( 'domain_products', 'Productos por extensión', $o, 'Una línea por extensión: .com|123' );
		echo '<h2>Compra de hosting</h2>';
		self::area( 'hosting_product_ids', 'IDs de productos de hosting', $o, 'IDs separados por comas.' ); self::area( 'hosting_category_ids', 'IDs de categorías de hosting', $o, 'IDs separados por comas.' ); self::check( 'require_domain_hosting', 'Exigir dominio y hosting juntos', $o ); self::check( 'replace_hosting', 'Reemplazar el plan de hosting al elegir otro', $o ); self::check( 'simplify_checkout', 'Simplificar checkout', $o );
		echo '<h2>Formulario Elementor</h2>';
		self::field( 'form_selector', 'Selector CSS del formulario', $o ); self::field( 'domain_field', 'Campo dominio', $o ); self::field( 'extension_field', 'Campo extensión', $o );
		echo '<p>Usa <code>[resultado_dominio]</code> en formularios Elementor. El shortcode anterior <code>[digitalisimo_domain_search]</code> se conserva para páginas existentes.</p>';
		submit_button( $network ? 'Guardar Hosting de red' : 'Guardar Hosting', 'primary', $network ? 'digitalisimo_hosting_network_save' : 'digitalisimo_hosting_save' );
		echo '</form></div>';
	}

	private static function field( $key, $label, $o, $type = 'text' ) { $value = 'password' === $type ? '' : ( $o[ $key ] ?? '' ); echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="regular-text" type="' . esc_attr( $type ) . '" name="hosting[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"></label>' . ( 'password' === $type && ! empty( $o[ $key ] ) ? ' <span class="description">Ya configurada; déjala vacía para conservarla.</span>' : '' ) . '</p>'; }
	private static function area( $key, $label, $o, $help = '' ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea class="large-text code" rows="3" name="hosting[' . esc_attr( $key ) . ']">' . esc_textarea( $o[ $key ] ?? '' ) . '</textarea></label><br><span class="description">' . esc_html( $help ) . '</span></p>'; }
	private static function check( $key, $label, $o ) { echo '<p><label><input type="hidden" name="hosting[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="hosting[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label></p>'; }

	private static function save( $network ) {
		$old = $network ? (array) get_site_option( self::OPTION, array() ) : self::local();
		$in = (array) wp_unslash( $_POST['hosting'] ?? array() );
		$o = $old;
		foreach ( array( 'api_user', 'username', 'client_ip', 'form_selector', 'domain_field', 'extension_field' ) as $key ) $o[ $key ] = sanitize_text_field( $in[ $key ] ?? '' );
		foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids' ) as $key ) $o[ $key ] = sanitize_textarea_field( $in[ $key ] ?? '' );
		foreach ( array( 'enabled', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout' ) as $key ) $o[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		if ( ! $network ) $o['inherit_network'] = empty( $in['inherit_network'] ) ? 0 : 1;
		if ( ! empty( $in['api_key'] ) ) $o['api_key'] = sanitize_text_field( $in['api_key'] );
		if ( $network ) update_site_option( self::OPTION, $o ); else update_option( self::OPTION, $o, false );
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

	public static function cart_compatibility() {
		if ( class_exists( 'Digitalisimo_Integrations_WooCommerce' ) || ! function_exists( 'WC' ) || empty( self::all()['enabled'] ) ) return;
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_domain' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_domain' ), 10, 4 );
		add_filter( 'digitalisimo_ai_context', array( __CLASS__, 'ai_context' ), 10, 3 );
		if ( self::all()['require_domain_hosting'] ) add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart' ) );
		if ( self::all()['replace_hosting'] ) add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'replace_hosting' ), 10, 3 );
		if ( self::all()['simplify_checkout'] ) add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ) );
	}
	public static function cart_domain( $data, $product_id, $variation_id ) { $domain = $_REQUEST['digitalisimo_domain'] ?? $_REQUEST['domain_name'] ?? ''; if ( $domain ) $data['digitalisimo_domain'] = sanitize_text_field( wp_unslash( $domain ) ); return $data; }
	public static function cart_item_data( $data, $item ) { if ( ! empty( $item['digitalisimo_domain'] ) ) $data[] = array( 'name' => 'Dominio', 'value' => $item['digitalisimo_domain'] ); return $data; }
	public static function order_item_domain( $item, $cart_item_key, $values, $order ) { if ( ! empty( $values['digitalisimo_domain'] ) ) $item->add_meta_data( 'Dominio', $values['digitalisimo_domain'], true ); }
	public static function ai_context( $context, $profile ) { if ( 'chatbot' !== $profile || ! class_exists( 'WooCommerce' ) ) return $context; $products = wc_get_products( array( 'status' => 'publish', 'limit' => 8, 'return' => 'objects' ) ); foreach ( $products as $product ) $context .= "\n- " . $product->get_name() . ': ' . wp_strip_all_tags( $product->get_short_description() ) . ' ' . $product->get_price_html(); return $context; }
	private static function ids( $key ) { return array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) self::all()[ $key ] ) ) ); }
	private static function is_hosting( $product_id ) { $products = self::ids( 'hosting_product_ids' ); $categories = self::ids( 'hosting_category_ids' ); return in_array( $product_id, $products, true ) || ( $categories && has_term( $categories, 'product_cat', $product_id ) ); }
	public static function validate_cart() { $has_hosting = false; $has_domain = false; $domain_ids = array_values( self::products() ); foreach ( WC()->cart->get_cart() as $item ) { $id = $item['product_id']; $has_hosting = $has_hosting || self::is_hosting( $id ); $has_domain = $has_domain || in_array( $id, $domain_ids, true ); } if ( $has_hosting xor $has_domain ) wc_add_notice( 'Agrega un dominio y un plan de hosting para continuar.', 'error' ); }
	public static function replace_hosting( $passed, $product_id, $quantity ) { if ( ! self::is_hosting( $product_id ) ) return $passed; foreach ( WC()->cart->get_cart() as $key => $item ) if ( self::is_hosting( $item['product_id'] ) ) WC()->cart->remove_cart_item( $key ); return $passed; }
	public static function checkout_fields( $fields ) { $fields['billing'] = array( 'billing_first_name' => array( 'label' => 'Nombre', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_phone' => array( 'label' => 'Teléfono', 'required' => true, 'class' => array( 'form-row-wide' ) ), 'billing_email' => array( 'label' => 'Correo electrónico', 'required' => true, 'class' => array( 'form-row-wide' ) ) ); unset( $fields['shipping'], $fields['order'] ); return $fields; }
}
