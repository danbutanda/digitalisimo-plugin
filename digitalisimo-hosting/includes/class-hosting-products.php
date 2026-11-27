<?php
defined( 'ABSPATH' ) || exit;

/**
 * Qué es cada producto de WooCommerce para DIGITALÍSIMO Hosting (pedido del propietario del 2026-11-27: «¿por qué usamos
 * IDs de productos? … debe ser un listado desplegable de lo que existe o detectarlos»).
 *
 * Cada producto (simple o variable) y cada variación lleva su tipo en el editor de producto:
 *  - **Dominio** (`_digitalisimo_hosting_type` = domain): la extensión exacta (`_digitalisimo_tld` = `.com`, `.com.mx`; en
 *    una transferencia `*` = cualquiera) y la acción (`_digitalisimo_domain_kind` = register o transfer). Siempre anual.
 *  - **Hosting** (= hosting): el paquete de Webuzo (`_digitalisimo_webuzo_plan`) y el periodo de cobro en meses
 *    (`_digitalisimo_hosting_period`: 1, 3, 6, 12, 24 o 36).
 *  - **Ninguno** (= none).
 * Una variación sin valor propio hereda el del producto. Lo que antes se escribía en los ajustes (`.com|123`, IDs de
 * hosting, de categorías y de «Se compran juntos») se DETECTA de estos productos; la migración (migrate()) convirtió una
 * vez la configuración vieja en estas marcas y ya no se lee.
 *
 * El catálogo detectado se guarda en un transitorio (una consulta por petición como mucho) que se invalida al guardar
 * un producto, una variación o una de estas marcas.
 */
class Digitalisimo_Hosting_Products {
	const META_TYPE   = '_digitalisimo_hosting_type';
	const META_TLD    = '_digitalisimo_tld';
	const META_KIND   = '_digitalisimo_domain_kind';
	const META_PLAN   = '_digitalisimo_webuzo_plan';
	const META_PERIOD = '_digitalisimo_hosting_period';
	const CACHE       = 'digitalisimo_hosting_catalog_v2';
	/** Periodos de cobro del hosting (meses => nombre). El dominio siempre es anual. */
	const PERIODS = array( 1 => 'Mensual', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual', 24 => 'Bianual', 36 => 'Trianual' );
	const TLD_PATTERN = '/^\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)?$/';
	/** Migración de la configuración con IDs y «|» (una vez por sitio): array( settings, products ) con la fecha. */
	const MIGRATED = 'digitalisimo_hosting_products_migrated';
	/** Copia de la configuración vieja, tal como estaba, antes de migrarla (no se lee: queda de respaldo). */
	const BACKUP = 'digitalisimo_hosting_legacy_settings';
	/** Extensiones que vendía la configuración de fábrica (líneas sin ID). */
	const LEGACY_DEFAULTS = array( '.com', '.net', '.mx' );

	/** @var array Catálogo de esta petición, por sitio (en una red, switch_to_blog() cambia de tienda). */
	private static $memo = array();
	/** Marca de la migración en un plan que quedó «Anual» sin que el dueño lo eligiera (se quita al guardarlo en el editor). */
	const META_PERIOD_MIGRATED = '_digitalisimo_period_migrated';

	private static function site() { return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1; }

	public static function init() {
		// Un producto, su SKU o su marca cambian: el catálogo guardado ya no vale.
		foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_update_product_variation', 'woocommerce_new_product_variation', 'trashed_post', 'untrashed_post', 'deleted_post', 'transition_post_status' ) as $hook ) add_action( $hook, array( __CLASS__, 'flush' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( __CLASS__, 'meta_changed' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		// Con WooCommerce ya cargado (sus tipos de entrada se registran en init 5).
		add_action( 'init', array( __CLASS__, 'migrate' ), 20 );
	}

	public static function meta_changed( $meta_id, $object_id, $key ) {
		if ( in_array( $key, array( self::META_TYPE, self::META_TLD, self::META_KIND, self::META_PLAN, self::META_PERIOD, '_sku', '_price', '_regular_price' ), true ) ) self::flush();
	}

	public static function flush() {
		self::$memo = array();
		if ( function_exists( 'delete_transient' ) ) delete_transient( self::CACHE );
	}

	/** «.COM», «com» y « .com » son «.com»; '' si no es una extensión. */
	public static function normalize( $ext ) {
		$ext = strtolower( trim( (string) $ext ) );
		if ( '' === $ext ) return '';
		if ( '.' !== $ext[0] ) $ext = '.' . $ext;
		return preg_match( self::TLD_PATTERN, $ext ) ? $ext : '';
	}

	/** Meses de un periodo válido, o 0. */
	public static function period_months( $raw ) { $m = (int) $raw; return isset( self::PERIODS[ $m ] ) ? $m : 0; }

	/** «Mensual», «Anual»… */
	public static function period_label( $months ) { return self::PERIODS[ (int) $months ] ?? self::PERIODS[12]; }

	// ── Lo que es cada producto ───────────────────────────────────────────────────────────────────────────────────

	private static function product( $id ) { return $id && function_exists( 'wc_get_product' ) ? wc_get_product( (int) $id ) : null; }

	/** El producto padre de una variación (0 si no es variación). */
	public static function parent_of( $id ) {
		$p = self::product( $id );
		if ( $p && method_exists( $p, 'get_parent_id' ) ) return (int) $p->get_parent_id();
		$post = function_exists( 'get_post' ) ? get_post( (int) $id ) : null;
		return $post && ! empty( $post->post_parent ) && 'product_variation' === ( $post->post_type ?? '' ) ? (int) $post->post_parent : 0;
	}

	/** Lo marcado en el propio producto o variación, sin heredar. */
	public static function own( $id ) {
		$id = (int) $id;
		$type = (string) get_post_meta( $id, self::META_TYPE, true );
		return array(
			'type'   => in_array( $type, array( 'domain', 'hosting', 'none' ), true ) ? $type : '',
			'tld'    => trim( (string) get_post_meta( $id, self::META_TLD, true ) ),
			'kind'   => 'transfer' === (string) get_post_meta( $id, self::META_KIND, true ) ? 'transfer' : ( 'register' === (string) get_post_meta( $id, self::META_KIND, true ) ? 'register' : '' ),
			'plan'   => trim( (string) get_post_meta( $id, self::META_PLAN, true ) ),
			'period' => self::period_months( get_post_meta( $id, self::META_PERIOD, true ) ),
		);
	}

	/**
	 * Lo que es el producto o la variación, ya con lo heredado: array( type domain|hosting|'' , tld, action
	 * register|transfer, plan, period ). Una variación toma de su producto lo que no tiene propio. Un producto que nunca
	 * pasó por el editor (sin tipo) se reconoce por las marcas que ya usaba Hosting: la extensión o el paquete de Webuzo.
	 */
	public static function config( $id ) {
		$id = (int) $id;
		$own = self::own( $id );
		$parent = self::parent_of( $id );
		$base = $parent ? self::config( $parent ) : null;
		$type = $own['type'];
		// Sin tipo propio («Igual que el producto»): manda el tipo que el producto eligió en su editor; si el producto nunca
		// lo eligió, las marcas que ya usaba Hosting (extensión o paquete) y, si no hay, lo que resulte del producto.
		if ( '' === $type ) {
			$parent_type = $parent ? self::own( $parent )['type'] : '';
			if ( '' !== $parent_type ) $type = $parent_type;
			elseif ( '' !== $own['tld'] || 'transfer' === $own['kind'] ) $type = 'domain';
			elseif ( '' !== $own['plan'] ) $type = 'hosting';
			else $type = $base && '' !== $base['type'] ? $base['type'] : 'none';
		}
		$tld = '' !== $own['tld'] ? $own['tld'] : ( $base ? $base['tld'] : '' );
		$kind = '' !== $own['kind'] ? $own['kind'] : ( $base ? $base['action'] : 'register' );
		$plan = '' !== $own['plan'] ? $own['plan'] : ( $base ? $base['plan'] : '' );
		$period = $own['period'] ? $own['period'] : ( $base ? $base['period'] : 12 );
		$out = array( 'type' => '', 'tld' => '', 'action' => '', 'plan' => '', 'period' => 0 );
		if ( 'domain' === $type ) {
			$action = 'transfer' === $kind ? 'transfer' : 'register';
			$ext = 'transfer' === $action && ( '' === $tld || '*' === $tld ) ? '*' : self::normalize( $tld );
			$out = array( 'type' => 'domain', 'tld' => $ext, 'action' => $action, 'plan' => '', 'period' => 12 );
		} elseif ( 'hosting' === $type ) {
			$out = array( 'type' => 'hosting', 'tld' => '', 'action' => '', 'plan' => $plan, 'period' => self::period_months( $period ) ? (int) $period : 12 );
		}
		return $out;
	}

	/** register (dominio nuevo), transfer, hosting (plan de hosting o correo) o ''. */
	public static function kind( $id ) {
		$c = self::config( $id );
		if ( 'hosting' === $c['type'] ) return 'hosting';
		if ( 'domain' !== $c['type'] || '' === $c['tld'] ) return '';
		return $c['action'];
	}

	/** El ID que vende una línea de carrito o de pedido: la variación si la hay, si no el producto. */
	public static function item_id( $item ) {
		if ( is_object( $item ) && method_exists( $item, 'get_variation_id' ) ) return (int) $item->get_variation_id() ?: (int) $item->get_product_id();
		if ( is_array( $item ) ) return (int) ( $item['variation_id'] ?? 0 ) ?: (int) ( $item['product_id'] ?? 0 );
		return 0;
	}

	/** Extensión que registra un producto de dominio («.com») o ''. */
	public static function register_ext( $id ) { $c = self::config( $id ); return 'domain' === $c['type'] && 'register' === $c['action'] ? $c['tld'] : ''; }

	/** ¿Es un producto de transferencia de dominio? La extensión que transfiere (`*` = cualquiera) o ''. */
	public static function transfer_ext( $id ) { $c = self::config( $id ); return 'domain' === $c['type'] && 'transfer' === $c['action'] ? $c['tld'] : ''; }

	/** Periodo de cobro (meses) de un plan de hosting; 12 si no es hosting. */
	public static function period( $id ) { $c = self::config( $id ); return 'hosting' === $c['type'] ? (int) $c['period'] : 12; }

	/** Paquete de Webuzo de un plan de hosting (con lo heredado del producto). */
	public static function plan( $id ) { $c = self::config( $id ); return 'hosting' === $c['type'] ? (string) $c['plan'] : ''; }

	// ── El catálogo detectado ─────────────────────────────────────────────────────────────────────────────────────

	/** ¿Se puede vender? Publicado (y, una variación, también su producto). */
	private static function sellable( $id ) {
		if ( ! $id ) return false;
		if ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( (int) $id ) ) return false;
		$parent = self::parent_of( $id );
		if ( $parent && function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $parent ) ) return false;
		return (bool) self::product( $id );
	}

	private static function name( $id ) { $p = self::product( $id ); return $p && method_exists( $p, 'get_name' ) ? (string) $p->get_name() : ''; }

	private static function is_variable( $p ) { return $p && method_exists( $p, 'is_type' ) && $p->is_type( 'variable' ); }

	/** IDs con alguna marca de Hosting (productos y variaciones), sin repetir. */
	private static function marked_ids() {
		if ( ! function_exists( 'get_posts' ) ) return array();
		$ids = array();
		foreach ( array( 'product', 'product_variation' ) as $type ) {
			foreach ( array( self::META_TYPE, self::META_TLD, self::META_KIND, self::META_PLAN ) as $key ) {
				foreach ( (array) get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => $key ) ) as $id ) $ids[ (int) $id ] = true;
			}
		}
		ksort( $ids );
		return array_keys( $ids );
	}

	/**
	 * Lo que se vende, detectado de los productos marcados: domains (filas id, parent, tld, action, name), hosting (id,
	 * parent, plan, period, name), map (extensión => ID del registro), transfer (extensión o `*` => ID), duplicates
	 * (misma extensión y acción en más de un producto: tld, action, ids) e incomplete (marcados a los que les falta algo).
	 * Con varios productos de la misma extensión y acción manda el de menor ID (y se avisa).
	 */
	public static function catalog() {
		if ( isset( self::$memo[ self::site() ] ) ) return self::$memo[ self::site() ];
		$cached = function_exists( 'get_transient' ) ? get_transient( self::CACHE ) : false;
		if ( is_array( $cached ) && isset( $cached['map'], $cached['domains'], $cached['hosting'] ) ) return self::$memo[ self::site() ] = $cached;
		$candidates = array();
		foreach ( self::marked_ids() as $id ) {
			$p = self::product( $id );
			if ( self::is_variable( $p ) && method_exists( $p, 'get_children' ) ) { foreach ( (array) $p->get_children() as $child ) $candidates[ (int) $child ] = true; }
			else $candidates[ $id ] = true;
		}
		ksort( $candidates );
		$out = array( 'domains' => array(), 'hosting' => array(), 'map' => array(), 'transfer' => array(), 'duplicates' => array(), 'incomplete' => array() );
		$seen = array();
		foreach ( array_keys( $candidates ) as $id ) {
			if ( ! self::sellable( $id ) ) continue;
			$c = self::config( $id );
			$parent = self::parent_of( $id );
			if ( 'domain' === $c['type'] ) {
				if ( '' === $c['tld'] ) { $out['incomplete'][] = array( 'id' => $id, 'name' => self::name( $id ), 'missing' => 'la extensión' ); continue; }
				$out['domains'][] = array( 'id' => $id, 'parent' => $parent, 'tld' => $c['tld'], 'action' => $c['action'], 'name' => self::name( $id ) );
				$seen[ $c['tld'] . '|' . $c['action'] ][] = $id;
				if ( 'register' === $c['action'] && ! isset( $out['map'][ $c['tld'] ] ) ) $out['map'][ $c['tld'] ] = $id;
				if ( 'transfer' === $c['action'] && ! isset( $out['transfer'][ $c['tld'] ] ) ) $out['transfer'][ $c['tld'] ] = $id;
			} elseif ( 'hosting' === $c['type'] ) {
				$out['hosting'][] = array( 'id' => $id, 'parent' => $parent, 'plan' => $c['plan'], 'period' => (int) $c['period'], 'name' => self::name( $id ) );
				if ( '' === $c['plan'] ) $out['incomplete'][] = array( 'id' => $id, 'name' => self::name( $id ), 'missing' => 'el paquete de Webuzo' );
			}
		}
		foreach ( $seen as $key => $ids ) {
			if ( count( $ids ) < 2 ) continue;
			list( $tld, $action ) = explode( '|', $key, 2 );
			$out['duplicates'][] = array( 'tld' => $tld, 'action' => $action, 'ids' => $ids, 'names' => array_map( array( __CLASS__, 'name' ), $ids ) );
		}
		if ( function_exists( 'set_transient' ) ) set_transient( self::CACHE, $out, DAY_IN_SECONDS );
		return self::$memo[ self::site() ] = $out;
	}

	/** Extensión => ID del producto que registra esa extensión (lo que antes era «Productos por extensión»). */
	public static function map() { return self::catalog()['map']; }

	/** Productos de transferencia: extensión (o `*`) => ID. */
	public static function transfers() { return self::catalog()['transfer']; }

	/** Las extensiones que se venden (registro o transferencia de una extensión concreta), sin `*`. */
	public static function sold_exts() {
		$c = self::catalog();
		$exts = array_keys( $c['map'] );
		foreach ( array_keys( $c['transfer'] ) as $e ) if ( '*' !== $e && ! in_array( $e, $exts, true ) ) $exts[] = $e;
		return $exts;
	}

	/** Lo que choca con este producto: otra fila con su misma extensión y acción. array( tld, action, others => ids ). */
	public static function conflicts_of( $id ) {
		$out = array();
		foreach ( self::catalog()['duplicates'] as $d ) if ( in_array( (int) $id, $d['ids'], true ) ) $out[] = array( 'tld' => $d['tld'], 'action' => $d['action'], 'others' => array_values( array_diff( $d['ids'], array( (int) $id ) ) ) );
		return $out;
	}

	/** «.com (registro): Dominio .com y Dominio .com promo» para los avisos. */
	public static function duplicate_text( array $d ) {
		return $d['tld'] . ' (' . ( 'transfer' === $d['action'] ? 'transferencia' : 'registro' ) . '): «' . implode( '» y «', array_map( 'strval', $d['names'] ) ) . '»';
	}

	// ── MCP: marcar un producto de dominio ────────────────────────────────────────────────────────────────────────

	/**
	 * Marca un producto de WooCommerce como Dominio de una extensión (herramienta MCP digitalisimo-hosting-domain-product).
	 * $kind: register (registro, por defecto) o transfer. $tld '' (o null) en un registro quita la marca (el producto
	 * queda «Ninguno»). La extensión se valida contra la lista de los registradores (o la lista base sin conexión).
	 * Exige manage_woocommerce o manage_options.
	 *
	 * @return array|WP_Error
	 */
	public static function mark( $product_id, $tld, $kind = 'register' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) return new WP_Error( 'digitalisimo_hosting_forbidden', 'Marcar productos de dominio exige «manage_woocommerce» o «manage_options».', array( 'status' => 403 ) );
		$product_id = absint( $product_id );
		$product = self::product( $product_id );
		if ( ! $product ) return new WP_Error( 'digitalisimo_hosting_product', 'No existe el producto ' . $product_id . ' en esta tienda.', array( 'status' => 404 ) );
		$kind = 'transfer' === $kind ? 'transfer' : 'register';
		$tld = null === $tld ? '' : trim( (string) $tld );
		if ( '' === $tld && 'register' === $kind ) {
			if ( 'domain' === self::config( $product_id )['type'] ) update_post_meta( $product_id, self::META_TYPE, 'none' );
			delete_post_meta( $product_id, self::META_TLD );
			delete_post_meta( $product_id, self::META_KIND );
		} else {
			$ext = 'transfer' === $kind && ( '' === $tld || '*' === $tld ) ? '*' : self::normalize( $tld );
			if ( '' === $ext || ( '*' !== $ext && class_exists( 'Digitalisimo_Hosting_Lists' ) && ! Digitalisimo_Hosting_Lists::valid_tld( $ext ) ) ) return new WP_Error( 'digitalisimo_hosting_tld', 'Extensión no válida o que los registradores no venden: «' . $tld . '». Usa por ejemplo .com, .net, .mx o .com.mx.', array( 'status' => 400 ) );
			update_post_meta( $product_id, self::META_TYPE, 'domain' );
			update_post_meta( $product_id, self::META_TLD, $ext );
			update_post_meta( $product_id, self::META_KIND, $kind );
		}
		self::flush();
		return array( 'ok' => true, 'product' => self::describe( $product_id ) ) + self::state();
	}

	/** Un producto con lo que es (sin IDs de configuración). */
	public static function describe( $product_id ) {
		$p = self::product( $product_id );
		$c = self::config( $product_id );
		$row = array(
			'product_id' => (int) $product_id,
			'name'       => $p ? (string) $p->get_name() : '',
			'sku'        => $p && method_exists( $p, 'get_sku' ) ? (string) $p->get_sku() : '',
			'status'     => function_exists( 'get_post_status' ) ? (string) get_post_status( (int) $product_id ) : '',
			'type'       => '' === $c['type'] ? 'none' : $c['type'],
		);
		if ( 'domain' === $c['type'] ) $row += array( 'tld' => $c['tld'], 'kind' => $c['action'], 'billing' => 'anual' );
		if ( 'hosting' === $c['type'] ) $row += array( 'plan' => $c['plan'], 'period' => self::period_label( $c['period'] ), 'period_months' => (int) $c['period'] );
		return $row;
	}

	/** Lo detectado, para las respuestas MCP: dominios, planes de hosting y avisos (sin IDs sueltos ni «|»). */
	public static function state() {
		$c = self::catalog();
		$domains = array();
		foreach ( $c['domains'] as $d ) {
			$pr = class_exists( 'Digitalisimo_Hosting_Finder' ) && function_exists( 'wc_get_product' ) ? Digitalisimo_Hosting_Finder::prices( $d['id'] ) : null;
			$domains[] = array( 'tld' => $d['tld'], 'kind' => $d['action'], 'product_id' => $d['id'], 'name' => $d['name'], 'price' => $pr ? $pr['price_text'] : null, 'in_use' => 'register' === $d['action'] ? $c['map'][ $d['tld'] ] === $d['id'] : $c['transfer'][ $d['tld'] ] === $d['id'] );
		}
		$hosting = array();
		foreach ( $c['hosting'] as $h ) $hosting[] = array( 'product_id' => $h['id'], 'name' => $h['name'], 'plan' => $h['plan'], 'period' => self::period_label( $h['period'] ), 'period_months' => $h['period'] );
		$dups = array();
		foreach ( $c['duplicates'] as $d ) $dups[] = array( 'tld' => $d['tld'], 'kind' => $d['action'], 'products' => array_map( function ( $id, $name ) { return array( 'product_id' => (int) $id, 'name' => (string) $name ); }, $d['ids'], $d['names'] ), 'message' => 'Hay más de un producto para ' . self::duplicate_text( $d ) . '. Se usa el primero; deja uno solo.' );
		$inc = array();
		foreach ( $c['incomplete'] as $i ) $inc[] = array( 'product_id' => $i['id'], 'name' => $i['name'], 'missing' => $i['missing'] );
		return array( 'domains' => $domains, 'hosting_products' => $hosting, 'duplicates' => $dups, 'incomplete' => $inc );
	}

	// ── Ajustes: lo detectado y los avisos ────────────────────────────────────────────────────────────────────────

	private static function edit_link( $id ) {
		$parent = self::parent_of( $id );
		$url = admin_url( 'post.php?post=' . (int) ( $parent ? $parent : $id ) . '&action=edit' );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( '' !== self::name( $id ) ? self::name( $id ) : '#' . (int) $id ) . '</a>';
	}

	/**
	 * «Productos por extensión»: ya no es un campo; la lista de lo detectado (extensión → producto con enlace, registro o
	 * transferencia, precio) con los avisos de duplicados y de extensiones que el registrador no vende.
	 */
	public static function settings_table() {
		$c = self::catalog();
		echo '<h2>Productos por extensión</h2>';
		echo '<p class="description">Se detectan solos: en el editor de cada producto, en la pestaña «DIGITALÍSIMO Hosting», elige «Dominio» y su extensión. Aquí sólo se muestran.</p>';
		if ( ! $c['domains'] ) { echo '<div class="notice notice-warning inline"><p>Todavía no hay productos de dominio. Abre el producto que vende cada extensión y, en la pestaña «DIGITALÍSIMO Hosting», elige «Dominio».</p></div>'; }
		else {
			$known = class_exists( 'Digitalisimo_Hosting_Lists' ) ? Digitalisimo_Hosting_Lists::tlds() : null;
			echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th scope="col">Extensión</th><th scope="col">Producto</th><th scope="col">Venta</th><th scope="col">Precio</th></tr></thead><tbody>';
			foreach ( $c['domains'] as $d ) {
				$pr = class_exists( 'Digitalisimo_Hosting_Finder' ) ? Digitalisimo_Hosting_Finder::prices( $d['id'] ) : null;
				$in_use = 'register' === $d['action'] ? $c['map'][ $d['tld'] ] === $d['id'] : $c['transfer'][ $d['tld'] ] === $d['id'];
				echo '<tr><td><strong>' . esc_html( '*' === $d['tld'] ? 'Cualquier extensión' : $d['tld'] ) . '</strong></td><td>' . self::edit_link( $d['id'] ) . ( $in_use ? '' : ' <span class="description">(repetido: no se usa)</span>' ) . '</td><td>' . esc_html( 'transfer' === $d['action'] ? 'Transferencia' : 'Registro' ) . ' · un año</td><td>' . esc_html( $pr ? $pr['price_text'] : 'sin precio' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- edit_link() escapa.
			}
			echo '</tbody></table>';
			foreach ( $c['duplicates'] as $d ) echo '<div class="notice notice-error inline"><p><strong>Repetido:</strong> ' . esc_html( 'hay más de un producto para ' . self::duplicate_text( $d ) . '. Se usa el primero; deja uno solo marcado.' ) . '</p></div>';
			if ( $known && 'registrar' === $known['source'] ) {
				$missing = array_values( array_diff( self::sold_exts(), $known['list'] ) );
				if ( $missing ) echo '<div class="notice notice-warning inline"><p>' . esc_html( 'Los registradores configurados no tienen precio para: ' . implode( ', ', $missing ) . '. Esas extensiones no se podrán registrar hasta que un registrador las venda.' ) . '</p></div>';
			}
		}
		foreach ( $c['incomplete'] as $i ) echo '<div class="notice notice-warning inline"><p>' . esc_html( '«' . $i['name'] . '» está marcado, pero le falta ' . $i['missing'] . '.' ) . '</p></div>';
	}

	/** Los planes que la migración dejó «Anual» y nadie ha guardado todavía en su editor (producto o variación). */
	public static function migrated_periods() {
		if ( ! function_exists( 'get_posts' ) ) return array();
		$ids = array();
		foreach ( array( 'product', 'product_variation' ) as $type ) foreach ( (array) get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => self::META_PERIOD_MIGRATED ) ) as $id ) if ( 'hosting' === self::kind( (int) $id ) ) $ids[] = (int) $id;
		sort( $ids );
		return array_values( array_unique( $ids ) );
	}

	/** «Compra de hosting»: los planes detectados (sin IDs): nombre con enlace, paquete de Webuzo y periodo. */
	public static function hosting_table() {
		$c = self::catalog();
		$review = self::migrated_periods();
		if ( $review ) {
			$links = array();
			foreach ( $review as $id ) $links[] = self::edit_link( $id );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- edit_link() escapa.
			echo '<div class="notice notice-warning inline"><p><strong>Revisa el periodo de cobro de estos planes.</strong> Al actualizar quedaron como «Anual», que es como se cobraban. Si alguno se vende por mes, trimestre, semestre o varios años, ábrelo y elige su periodo en la pestaña «DIGITALÍSIMO Hosting» (en un producto variable, una variación por periodo). El aviso se quita al guardar cada uno: ' . implode( ', ', $links ) . '.</p></div>';
		}
		echo '<p class="description">Los planes de hosting y correo se detectan solos: en el editor de producto, pestaña «DIGITALÍSIMO Hosting», elige «Hosting», el paquete de Webuzo y el periodo de cobro (en un producto variable, una variación por periodo).</p>';
		if ( ! $c['hosting'] ) { echo '<div class="notice notice-warning inline"><p>Todavía no hay productos de hosting marcados.</p></div>'; return; }
		echo '<table class="widefat striped" style="max-width:820px"><thead><tr><th scope="col">Plan</th><th scope="col">Paquete de Webuzo</th><th scope="col">Cobro</th></tr></thead><tbody>';
		foreach ( $c['hosting'] as $h ) echo '<tr><td>' . self::edit_link( $h['id'] ) . '</td><td>' . esc_html( '' !== $h['plan'] ? $h['plan'] : 'falta elegirlo' ) . '</td><td>' . esc_html( self::period_label( $h['period'] ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- edit_link() escapa.
		echo '</tbody></table>';
	}

	/**
	 * Aviso (con la X) cuando el buscador no tiene productos de dominio o hay dos productos para la misma extensión y
	 * acción: en el Escritorio, Plugins, los productos (lista y editor) y la pantalla Hosting.
	 */
	public static function admin_notice() {
		if ( ! function_exists( 'wc_get_product' ) || ! current_user_can( 'manage_options' ) || ! class_exists( 'Digitalisimo_Notices' ) ) return;
		$page = $GLOBALS['pagenow'] ?? '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$screen = 'index.php' === $page || 'plugins.php' === $page || ( 'edit.php' === $page && 'product' === ( $_GET['post_type'] ?? '' ) ) || 'post.php' === $page || ( 'admin.php' === $page && 'digitalisimo-hosting' === ( $_GET['page'] ?? '' ) );
		// phpcs:enable
		if ( ! $screen ) return;
		$o = Digitalisimo_Hosting::settings();
		if ( empty( $o['enabled'] ) ) return;
		$c = self::catalog();
		$parts = array();
		foreach ( $c['duplicates'] as $d ) $parts[] = 'hay más de un producto para ' . esc_html( self::duplicate_text( $d ) ) . ' (se usa el primero)';
		// Sin dominios sólo avisa a quien ya vende hosting.
		if ( ! $c['map'] && $c['hosting'] ) $parts[] = 'ningún producto está marcado como «Dominio»: el buscador no puede vender dominios';
		if ( ! $parts ) return;
		$link = '<a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-hosting' ) ) . '">Digitalisimo → Hosting</a>';
		Digitalisimo_Notices::show( Digitalisimo_Notices::site( 'hosting-domain-products' ), 'warning', '<p><strong>DIGITALÍSIMO Hosting:</strong> ' . implode( '; ', $parts ) . '. Revísalo en la pestaña «DIGITALÍSIMO Hosting» de cada producto (resumen en ' . $link . ').</p>', array( 'hash' => md5( wp_json_encode( array( $c['duplicates'], array_keys( $c['map'] ) ) ) ), 'cap' => 'manage_options' ) );
	}

	// ── Migración de la configuración con IDs y «|» ───────────────────────────────────────────────────────────────

	/** Las líneas viejas de «Productos por extensión» (`.com|123`): extensión => ID (0 si la línea no trae ID). */
	public static function legacy_lines( $raw ) {
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

	/** Avisos de requisitos viejos (`.mx|Requisito…`, uno por línea): extensión => texto. */
	public static function legacy_requirements( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$e = self::normalize( $parts[0] );
			if ( '' !== $e && '' !== ( $parts[1] ?? '' ) ) $out[ $e ] = sanitize_text_field( $parts[1] );
		}
		return $out;
	}

	/** Orden viejo (`.com, .mx`): la lista de extensiones. */
	public static function legacy_order( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\s,;|]+/', (string) $raw ) as $e ) { $e = self::normalize( $e ); if ( '' !== $e && ! in_array( $e, $out, true ) ) $out[] = $e; }
		return $out;
	}

	/** IDs separados por comas → enteros únicos. */
	private static function legacy_ids( $raw ) { return array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw ) ) ) ) ); }

	/** El estado de la migración de este sitio: array( settings => fecha, products => fecha ). */
	private static function migrated() { $m = get_option( self::MIGRATED, array() ); return is_array( $m ) ? $m : array(); }

	/**
	 * Convierte una vez, por sitio, la configuración con IDs y «|» en marcas de los productos y en filas de los ajustes, con
	 * el mismo comportamiento que tenía:
	 *  - ajustes (con o sin WooCommerce): «Registrador por extensión», «Avisos de requisitos» y «Orden» pasan a filas por
	 *    extensión; los IDs de «Se compran juntos» a la lista de productos elegidos. Lo viejo se copia a BACKUP y se quita.
	 *  - productos (con WooCommerce): el producto de cada línea de «Productos por extensión» (por ID, por la marca o por el
	 *    SKU convenido, como se resolvía), los de transferencia (marca o SKU), los IDs y categorías de hosting y los que
	 *    tienen paquete de Webuzo quedan marcados con su tipo (el hosting de antes, anual). Un producto marcado con una
	 *    extensión que la lista con IDs no usaba queda «Ninguno» (no se vendía). Nunca toca un producto que ya tiene tipo.
	 * Idempotente: con la marca de hecho no corre; corrida otra vez no cambia nada.
	 */
	public static function migrate() {
		$m = self::migrated();
		if ( ! empty( $m['settings'] ) && ! empty( $m['products'] ) ) return false;
		$changed = false;
		if ( empty( $m['settings'] ) ) { self::migrate_settings(); $m['settings'] = time(); $changed = true; }
		if ( empty( $m['products'] ) && function_exists( 'wc_get_product' ) ) { self::migrate_products(); $m['products'] = time(); $changed = true; }
		if ( $changed ) update_option( self::MIGRATED, $m, true );
		return $changed;
	}

	private static function migrate_settings() {
		$o = get_option( Digitalisimo_Hosting::OPTION, array() );
		$o = is_array( $o ) ? $o : array();
		$before = $o;
		$backup = get_option( self::BACKUP, array() );
		$backup = is_array( $backup ) ? $backup : array();
		foreach ( array( 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'finder_upsell_ids', 'registrar_by_tld', 'finder_requirements', 'finder_order' ) as $key ) if ( array_key_exists( $key, $o ) && ! is_array( $o[ $key ] ) && ! array_key_exists( $key, $backup ) ) $backup[ $key ] = (string) $o[ $key ];
		if ( array_key_exists( 'registrar_by_tld', $o ) && ! is_array( $o['registrar_by_tld'] ) ) $o['registrar_by_tld'] = class_exists( 'Digitalisimo_Hosting_Registrars' ) ? Digitalisimo_Hosting_Registrars::parse( (string) $o['registrar_by_tld'] )['map'] : array();
		if ( array_key_exists( 'finder_requirements', $o ) && ! is_array( $o['finder_requirements'] ) ) $o['finder_requirements'] = self::legacy_requirements( $o['finder_requirements'] );
		if ( array_key_exists( 'finder_order', $o ) && ! is_array( $o['finder_order'] ) ) $o['finder_order'] = self::legacy_order( $o['finder_order'] );
		if ( array_key_exists( 'finder_upsell_ids', $o ) && ! isset( $o['finder_upsell'] ) ) $o['finder_upsell'] = self::legacy_ids( $o['finder_upsell_ids'] );
		// Lo que pasa a los productos queda en la copia (la lee migrate_products) y TAMBIÉN en los ajustes, tal cual: el código
		// nuevo no lo lee y así volver a una versión anterior no pierde nada.
		if ( $backup ) { $backup['_at'] = $backup['_at'] ?? time(); update_option( self::BACKUP, $backup, false ); }
		if ( $o !== $before ) update_option( Digitalisimo_Hosting::OPTION, $o, false );
	}

	/** El tipo que ya tiene el producto (lo marcado en el editor o por esta migración manda). */
	private static function typed( $id ) { return '' !== self::own( $id )['type']; }

	private static function set_domain( $id, $ext, $action ) {
		if ( ! $id || self::typed( $id ) ) return;
		update_post_meta( $id, self::META_TYPE, 'domain' );
		update_post_meta( $id, self::META_TLD, $ext );
		update_post_meta( $id, self::META_KIND, $action );
	}

	private static function set_hosting( $id ) {
		if ( ! $id || self::typed( $id ) ) return;
		update_post_meta( $id, self::META_TYPE, 'hosting' );
		// El hosting de antes se cobraba y renovaba por año; queda marcado para que el dueño revise el periodo.
		if ( ! self::own( $id )['period'] ) { update_post_meta( $id, self::META_PERIOD, 12 ); update_post_meta( $id, self::META_PERIOD_MIGRATED, 1 ); }
	}

	/** Producto por SKU, publicado. */
	private static function by_sku( $sku ) {
		$id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $sku ) : 0;
		return $id && function_exists( 'get_post_status' ) && 'publish' === get_post_status( $id ) ? $id : ( $id && ! function_exists( 'get_post_status' ) ? $id : 0 );
	}

	/** Productos publicados con una meta (sin tipo todavía). */
	private static function with_meta( $key ) {
		if ( ! function_exists( 'get_posts' ) ) return array();
		$ids = array();
		foreach ( array( 'product', 'product_variation' ) as $type ) foreach ( (array) get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true, 'meta_key' => $key ) ) as $id ) $ids[] = (int) $id;
		sort( $ids );
		return array_values( array_unique( $ids ) );
	}

	private static function migrate_products() {
		$backup = get_option( self::BACKUP, array() );
		$backup = is_array( $backup ) ? $backup : array();
		// Sin la clave guardada se usaba la lista de fábrica (.com, .net y .mx sin IDs).
		$raw = array_key_exists( 'domain_products', $backup ) ? (string) $backup['domain_products'] : ".com|\n.net|\n.mx|";
		// La lista de fábrica anterior a 1.0.35 (.com, .mx y .org sin IDs) valía como la de fábrica.
		if ( '.com|.mx|.org|' === preg_replace( '/\s+/', '', $raw ) ) $raw = ".com|\n.net|\n.mx|";
		$lines = self::legacy_lines( $raw );
		$auto = ! $lines;
		// Lo marcado con la meta (sin tipo): registro y transferencia, como lo leía la versión anterior.
		$marked_reg = array(); $marked_trf = array();
		foreach ( self::with_meta( self::META_TLD ) as $id ) {
			if ( self::typed( $id ) ) continue;
			$raw_tld = trim( (string) get_post_meta( $id, self::META_TLD, true ) );
			if ( 'transfer' === (string) get_post_meta( $id, self::META_KIND, true ) ) { $e = '*' === $raw_tld || '' === $raw_tld ? '*' : self::normalize( $raw_tld ); if ( '' !== $e && ! isset( $marked_trf[ $e ] ) ) $marked_trf[ $e ] = $id; continue; }
			$e = self::normalize( $raw_tld );
			if ( '' !== $e && ! isset( $marked_reg[ $e ] ) ) $marked_reg[ $e ] = $id;
		}
		foreach ( self::with_meta( self::META_KIND ) as $id ) if ( ! self::typed( $id ) && 'transfer' === (string) get_post_meta( $id, self::META_KIND, true ) && '' === trim( (string) get_post_meta( $id, self::META_TLD, true ) ) && ! isset( $marked_trf['*'] ) ) $marked_trf['*'] = $id;
		$exts = $auto ? array_values( array_unique( array_merge( array_keys( $marked_reg ), self::LEGACY_DEFAULTS ) ) ) : array_keys( $lines );
		$used = array();
		foreach ( $exts as $ext ) {
			$id = $auto ? 0 : (int) $lines[ $ext ];
			if ( $id && ! self::product( $id ) ) $id = 0;
			if ( ! $id && isset( $marked_reg[ $ext ] ) ) $id = $marked_reg[ $ext ];
			if ( ! $id ) {
				$slug = str_replace( '.', '-', ltrim( $ext, '.' ) );
				foreach ( array_unique( array( 'dominio-' . $slug, 'dominio' . $ext, 'domain-' . $slug, 'domain' . $ext ) ) as $sku ) { $s = self::by_sku( $sku ); if ( $s && 'transfer' !== (string) get_post_meta( $s, self::META_KIND, true ) ) { $id = $s; break; } }
			}
			if ( ! $id ) continue;
			// La línea con ID mandaba aunque el producto tuviera otra marca: queda como registro de esa extensión.
			if ( ! self::typed( $id ) ) { delete_post_meta( $id, self::META_KIND ); self::set_domain( $id, $ext, 'register' ); }
			$used[ $id ] = true;
		}
		// Marcados con una extensión que la lista no usaba (o repetidos): no se vendían; quedan «Ninguno».
		foreach ( $marked_reg as $ext => $id ) if ( ! isset( $used[ $id ] ) && ! self::typed( $id ) ) update_post_meta( $id, self::META_TYPE, 'none' );
		foreach ( self::with_meta( self::META_TLD ) as $id ) if ( ! self::typed( $id ) && 'transfer' !== (string) get_post_meta( $id, self::META_KIND, true ) && ! isset( $used[ $id ] ) ) update_post_meta( $id, self::META_TYPE, 'none' );
		// Transferencias: las marcadas y, como respaldo, los SKU convenidos.
		foreach ( $marked_trf as $ext => $id ) self::set_domain( $id, $ext, 'transfer' );
		foreach ( $exts as $ext ) if ( ! isset( $marked_trf[ $ext ] ) ) { $id = self::by_sku( 'transferencia-' . str_replace( '.', '-', ltrim( $ext, '.' ) ) ); if ( $id ) self::set_domain( $id, $ext, 'transfer' ); }
		if ( ! isset( $marked_trf['*'] ) ) foreach ( array( 'transferencia-dominio', 'transfer-domain', 'transferencia' ) as $sku ) { $id = self::by_sku( $sku ); if ( $id ) { self::set_domain( $id, '*', 'transfer' ); break; } }
		// Hosting: los IDs, las categorías y lo que ya tenía paquete de Webuzo (también una variación).
		foreach ( self::legacy_ids( $backup['hosting_product_ids'] ?? '' ) as $id ) if ( self::product( $id ) ) self::set_hosting( $id );
		$cats = self::legacy_ids( $backup['hosting_category_ids'] ?? '' );
		if ( $cats && function_exists( 'get_posts' ) ) foreach ( (array) get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 500, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => true, 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cats ) ) ) ) as $id ) self::set_hosting( (int) $id );
		foreach ( self::with_meta( self::META_PLAN ) as $id ) if ( '' !== trim( (string) get_post_meta( $id, self::META_PLAN, true ) ) ) self::set_hosting( $id );
		self::flush();
	}
}
