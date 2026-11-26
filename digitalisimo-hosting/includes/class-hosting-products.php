<?php
defined( 'ABSPATH' ) || exit;

/**
 * Productos de dominio por extensión (pedido del propietario del 2026-11-26).
 *
 * «Productos por extensión» (`domain_products`) liga cada extensión con su producto de WooCommerce: `.com|123`. Una línea
 * sin ID (`.com|`, como el valor de fábrica) o una lista vacía ya no dejan el buscador sin productos: se busca el
 * producto de dominio publicado
 *  1. marcado con esa extensión (meta `_digitalisimo_tld` = `.com`; la pone la herramienta MCP
 *     `digitalisimo-hosting-domain-product` o el campo personalizado del producto), o
 *  2. con el SKU convenido (`dominio-com`, `dominio.com`, `domain-com` o `domain.com`).
 * Una línea con ID manda siempre (es la decisión del administrador). Con la lista vacía se usan las extensiones de los
 * productos marcados y, por SKU, las de fábrica (.com, .net y .mx).
 *
 * El producto de transferencia se marca con `_digitalisimo_domain_kind` = `transfer` (y, si sólo transfiere una
 * extensión, su `_digitalisimo_tld`; `*` o vacío = cualquiera de las configuradas) o tiene el SKU
 * `transferencia-dominio`, `transfer-domain`, `transferencia-com`… Nunca entra en «Productos por extensión».
 *
 * La resolución se guarda en un transitorio (una consulta por extensión sin ID, no en cada página) que se invalida al
 * guardar Hosting, al cambiar un producto o su marca.
 */
class Digitalisimo_Hosting_Products {
	const META_TLD  = '_digitalisimo_tld';
	const META_KIND = '_digitalisimo_domain_kind';
	const CACHE     = 'digitalisimo_hosting_domain_products';
	/** Extensiones de fábrica (sin .org). */
	const DEFAULTS = array( '.com', '.net', '.mx' );
	const TLD_PATTERN = '/^\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)?$/';

	/** @var array|null Resolución de esta petición: array( raw, report ). */
	private static $memo = null;

	public static function init() {
		// Un producto, su SKU o su marca cambian: la resolución guardada ya no vale.
		foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) add_action( $hook, array( __CLASS__, 'flush' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'meta_changed' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function meta_changed( $meta_id, $object_id, $key ) {
		if ( in_array( $key, array( self::META_TLD, self::META_KIND, '_sku' ), true ) ) self::flush();
	}

	public static function flush() {
		self::$memo = null;
		if ( function_exists( 'delete_transient' ) ) delete_transient( self::CACHE );
	}

	/** «.COM», «com» y « .com » son «.com»; '' si no es una extensión. */
	public static function normalize( $ext ) {
		$ext = strtolower( trim( (string) $ext ) );
		if ( '' === $ext ) return '';
		if ( '.' !== $ext[0] ) $ext = '.' . $ext;
		return preg_match( self::TLD_PATTERN, $ext ) ? $ext : '';
	}

	/** Las líneas de «Productos por extensión»: extensión => ID (0 si la línea no trae ID). */
	public static function parse( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			if ( '' === trim( $line ) ) continue;
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$ext = self::normalize( $parts[0] );
			if ( '' === $ext || isset( $out[ $ext ] ) ) continue;
			$out[ $ext ] = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
		}
		return $out;
	}

	/** Extensión => ID resuelto (sólo las que tienen producto). */
	public static function map( $raw ) {
		$out = array();
		foreach ( self::report( $raw )['resolved'] as $ext => $row ) $out[ $ext ] = (int) $row['product_id'];
		return $out;
	}

	/**
	 * La resolución completa: `resolved` (extensión => product_id, source config|meta|sku y name), `unresolved` (las
	 * extensiones sin producto) y `transfer` (extensión o `*` => product_id).
	 */
	public static function report( $raw ) {
		$raw = (string) $raw;
		if ( self::$memo && self::$memo[0] === $raw ) return self::$memo[1];
		$cached = function_exists( 'get_transient' ) ? get_transient( self::CACHE ) : false;
		if ( is_array( $cached ) && ( $cached['raw'] ?? null ) === md5( $raw ) && isset( $cached['report'] ) ) {
			self::$memo = array( $raw, $cached['report'] );
			return $cached['report'];
		}
		$lines = self::parse( $raw );
		$auto = null;
		if ( ! $lines ) {
			$marked = array_keys( self::marked( 'register' ) );
			$lines = array_fill_keys( array_values( array_unique( array_merge( $marked, self::DEFAULTS ) ) ), 0 );
		}
		$resolved = array(); $unresolved = array();
		foreach ( $lines as $ext => $id ) {
			$source = 'config';
			if ( ! $id ) list( $id, $source ) = self::find( $ext );
			if ( $id ) $resolved[ $ext ] = array( 'product_id' => (int) $id, 'source' => $source, 'name' => self::name( $id ) );
			else $unresolved[] = $ext;
		}
		$report = array( 'resolved' => $resolved, 'unresolved' => $unresolved, 'transfer' => self::transfer_map( array_keys( $lines ) ) );
		self::$memo = array( $raw, $report );
		if ( function_exists( 'set_transient' ) ) set_transient( self::CACHE, array( 'raw' => md5( $raw ), 'report' => $report ), DAY_IN_SECONDS );
		return $report;
	}

	private static function name( $id ) {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : null;
		return $p && method_exists( $p, 'get_name' ) ? (string) $p->get_name() : '';
	}

	/** ¿Se puede vender? Publicado (o sin estado conocido, como en las pruebas sin WordPress). */
	private static function sellable( $id ) {
		if ( ! $id ) return false;
		if ( function_exists( 'get_post_status' ) ) return 'publish' === get_post_status( (int) $id );
		return (bool) ( function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : false );
	}

	/** Producto de registro de una extensión: array( id, 'meta'|'sku' ) o array( 0, '' ). */
	public static function find( $ext ) {
		$ext = self::normalize( $ext );
		if ( '' === $ext ) return array( 0, '' );
		$marked = self::marked( 'register' );
		if ( ! empty( $marked[ $ext ] ) ) return array( (int) $marked[ $ext ], 'meta' );
		$slug = str_replace( '.', '-', ltrim( $ext, '.' ) );
		foreach ( array_unique( array( 'dominio-' . $slug, 'dominio' . $ext, 'domain-' . $slug, 'domain' . $ext ) ) as $sku ) {
			$id = self::by_sku( $sku );
			if ( $id && self::sellable( $id ) && 'transfer' !== self::kind_meta( $id ) ) return array( $id, 'sku' );
		}
		return array( 0, '' );
	}

	private static function by_sku( $sku ) { return function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0; }

	private static function kind_meta( $id ) { $k = (string) get_post_meta( (int) $id, self::META_KIND, true ); return 'transfer' === $k ? 'transfer' : ''; }

	/** Productos publicados marcados con una extensión: extensión => ID (el de menor ID si hay varios). */
	public static function marked( $kind = 'register' ) {
		if ( ! function_exists( 'get_posts' ) ) return array();
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => self::META_TLD ) );
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			$is_transfer = 'transfer' === self::kind_meta( $id );
			if ( ( 'transfer' === $kind ) !== $is_transfer ) continue;
			$raw = trim( (string) get_post_meta( $id, self::META_TLD, true ) );
			$ext = '*' === $raw || ( '' === $raw && $is_transfer ) ? '*' : self::normalize( $raw );
			if ( '' !== $ext && ! isset( $out[ $ext ] ) ) $out[ $ext ] = $id;
		}
		return $out;
	}

	/** Productos de transferencia: extensión (o `*`) => ID. */
	private static function transfer_map( array $exts ) {
		$out = self::marked( 'transfer' );
		// Los marcados sólo con el tipo (sin extensión) no los devuelve la consulta por META_TLD: también por el tipo.
		if ( function_exists( 'get_posts' ) && ! isset( $out['*'] ) ) {
			foreach ( (array) get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 20, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => self::META_KIND, 'meta_value' => 'transfer' ) ) as $id ) {
				if ( '' === trim( (string) get_post_meta( (int) $id, self::META_TLD, true ) ) ) { $out['*'] = (int) $id; break; }
			}
		}
		foreach ( $exts as $ext ) {
			if ( isset( $out[ $ext ] ) ) continue;
			$id = self::by_sku( 'transferencia-' . str_replace( '.', '-', ltrim( $ext, '.' ) ) );
			if ( $id && self::sellable( $id ) ) $out[ $ext ] = $id;
		}
		if ( ! isset( $out['*'] ) ) foreach ( array( 'transferencia-dominio', 'transfer-domain', 'transferencia' ) as $sku ) { $id = self::by_sku( $sku ); if ( $id && self::sellable( $id ) ) { $out['*'] = $id; break; } }
		return $out;
	}

	/** ¿Es un producto de transferencia de dominio? Devuelve la extensión que transfiere (`*` = cualquiera) o ''. */
	public static function transfer_ext( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || ! class_exists( 'Digitalisimo_Hosting' ) ) return '';
		$map = self::report( (string) Digitalisimo_Hosting::settings()['domain_products'] )['transfer'];
		$ext = array_search( $product_id, $map, true );
		if ( false !== $ext ) return (string) $ext;
		if ( 'transfer' === self::kind_meta( $product_id ) ) { $raw = trim( (string) get_post_meta( $product_id, self::META_TLD, true ) ); $n = self::normalize( $raw ); return '' !== $n ? $n : '*'; }
		return '';
	}

	/** Extensión que registra un producto de dominio («.com») o ''. */
	public static function register_ext( $product_id ) {
		if ( ! class_exists( 'Digitalisimo_Hosting' ) ) return '';
		$ext = array_search( (int) $product_id, Digitalisimo_Hosting::domain_products(), true );
		return false === $ext ? '' : (string) $ext;
	}

	/**
	 * Marca un producto de WooCommerce como el de una extensión (herramienta MCP digitalisimo-hosting-domain-product).
	 * $kind: register (registro, por defecto) o transfer. $tld '' (o null) quita la marca. Exige manage_woocommerce o
	 * manage_options. Devuelve el producto con su marca y cómo queda la resolución, o WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public static function mark( $product_id, $tld, $kind = 'register' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) return new WP_Error( 'digitalisimo_hosting_forbidden', 'Marcar productos de dominio exige «manage_woocommerce» o «manage_options».', array( 'status' => 403 ) );
		$product_id = absint( $product_id );
		$product = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) return new WP_Error( 'digitalisimo_hosting_product', 'No existe el producto ' . $product_id . ' en esta tienda.', array( 'status' => 404 ) );
		$kind = 'transfer' === $kind ? 'transfer' : 'register';
		$tld = null === $tld ? '' : trim( (string) $tld );
		if ( '' === $tld && 'register' === $kind ) {
			delete_post_meta( $product_id, self::META_TLD );
			delete_post_meta( $product_id, self::META_KIND );
		} else {
			$ext = 'transfer' === $kind && ( '' === $tld || '*' === $tld ) ? '*' : self::normalize( $tld );
			if ( '' === $ext ) return new WP_Error( 'digitalisimo_hosting_tld', 'Extensión no válida: «' . $tld . '». Usa por ejemplo .com, .net, .mx o .com.mx.', array( 'status' => 400 ) );
			update_post_meta( $product_id, self::META_TLD, $ext );
			if ( 'transfer' === $kind ) update_post_meta( $product_id, self::META_KIND, 'transfer' ); else delete_post_meta( $product_id, self::META_KIND );
		}
		self::flush();
		return array( 'ok' => true, 'product' => self::describe( $product_id ) ) + self::state();
	}

	/** Un producto con su marca. */
	public static function describe( $product_id ) {
		$p = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null;
		$tld = (string) get_post_meta( (int) $product_id, self::META_TLD, true );
		return array(
			'id'     => (int) $product_id,
			'name'   => $p ? (string) $p->get_name() : '',
			'sku'    => $p && method_exists( $p, 'get_sku' ) ? (string) $p->get_sku() : '',
			'status' => function_exists( 'get_post_status' ) ? (string) get_post_status( (int) $product_id ) : '',
			'tld'    => $tld,
			'kind'   => '' === $tld && '' === self::kind_meta( $product_id ) ? '' : ( 'transfer' === self::kind_meta( $product_id ) ? 'transfer' : 'register' ),
		);
	}

	/** Lo marcado y la resolución con los ajustes del sitio (para las respuestas MCP). */
	public static function state() {
		$marked = array();
		foreach ( array( 'register', 'transfer' ) as $kind ) foreach ( self::marked( $kind ) as $ext => $id ) $marked[] = self::describe( $id );
		return array( 'marked' => $marked, 'domain_products' => self::report( (string) Digitalisimo_Hosting::settings()['domain_products'] ) );
	}

	/** Debajo de «Productos por extensión»: qué producto usa cada extensión y cuál falta. */
	public static function settings_summary( $raw ) {
		if ( ! function_exists( 'wc_get_product' ) ) return;
		$r = self::report( $raw );
		$parts = array();
		foreach ( $r['resolved'] as $ext => $row ) $parts[] = $ext . ' → #' . $row['product_id'] . ( '' !== $row['name'] ? ' ' . $row['name'] : '' ) . ( 'config' === $row['source'] ? '' : ' (' . ( 'meta' === $row['source'] ? 'marcado' : 'por SKU' ) . ')' );
		foreach ( $r['unresolved'] as $ext ) $parts[] = $ext . ' → sin producto';
		echo '<p class="description"><strong>En uso:</strong> ' . esc_html( $parts ? implode( ' · ', $parts ) : 'ningún producto de dominio' ) . '</p>';
	}

	/**
	 * Aviso (con la X) cuando el buscador quedaría sin productos o le falta alguna extensión: en el Escritorio, Plugins,
	 * la lista de productos y la pantalla Hosting, para quien administra el sitio.
	 */
	public static function admin_notice() {
		if ( ! function_exists( 'wc_get_product' ) || ! current_user_can( 'manage_options' ) || ! class_exists( 'Digitalisimo_Notices' ) ) return;
		$page = $GLOBALS['pagenow'] ?? '';
		$screen = 'index.php' === $page || 'plugins.php' === $page || ( 'edit.php' === $page && 'product' === ( $_GET['post_type'] ?? '' ) ) || ( 'admin.php' === $page && 'digitalisimo-hosting' === ( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $screen ) return;
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['enabled'] ) ) return;
		$r = self::report( (string) $o['domain_products'] );
		if ( ! $r['unresolved'] && $r['resolved'] ) return;
		$link = '<a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>';
		$text = $r['resolved']
			? 'Estas extensiones de «Productos por extensión» no tienen producto de dominio: <strong>' . esc_html( implode( ', ', $r['unresolved'] ) ) . '</strong>. El buscador no las ofrece hasta que les pongas el ID en ' . $link . ' o marques su producto (campo personalizado <code>_digitalisimo_tld</code>, o el SKU <code>dominio-com</code>).'
			: 'La lista «Productos por extensión» no resuelve ningún producto: el buscador de dominios no puede vender ninguno. Pon el ID de cada producto de dominio en ' . $link . ' (por ejemplo <code>.com|123</code>) o márcalo con su extensión (campo personalizado <code>_digitalisimo_tld</code> = <code>.com</code>, o el SKU <code>dominio-com</code>).';
		Digitalisimo_Notices::show( Digitalisimo_Notices::site( 'hosting-domain-products' ), 'warning', '<p><strong>DIGITALÍSIMO Hosting:</strong> ' . $text . '</p>', array( 'hash' => md5( wp_json_encode( array( array_keys( $r['resolved'] ), $r['unresolved'] ) ) ), 'cap' => 'manage_options' ) );
	}
}
