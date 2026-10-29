<?php
defined( 'ABSPATH' ) || exit;

/**
 * URL sin base (RM12 de docs/SEO_RANKMATH_OBJECTIVE.md): /producto-x/ en vez de /product/producto-x/,
 * /ropa/camisas/ en vez de /product-category/ropa/camisas/ y /noticias/ en vez de /category/noticias/.
 *
 * Opt-in por sitio, con paridad de red. Sirve sobre todo para conservar idénticas las URL de un sitio
 * que venía de Rank Math con la base quitada. Reglas:
 * - una página o entrada con la misma ruta siempre gana: ese enlace conserva su base;
 * - sólo se resuelve una ruta sin base cuando WordPress no encuentra la página o entrada que buscaba;
 * - la URL con base sigue funcionando y la redirección canónica de WordPress la lleva (301) a la nueva;
 * - una base con etiquetas (/shop/%product_cat%/) no se toca.
 */
class Digitalisimo_Integrations_URL_Bases {
	/** Opción => tipo de objeto. */
	const KEYS = array( 'seo_strip_product_base' => 'product', 'seo_strip_product_cat_base' => 'product_cat', 'seo_strip_category_base' => 'category' );
	const TAKEN = 'digitalisimo_seo_url_taken';
	/** Último estado aplicado a las 301 importadas (sitio o heredado de la red). */
	const STATE = 'digitalisimo_seo_url_bases_state';

	public static function init() {
		add_filter( 'post_type_link', array( __CLASS__, 'product_link' ), 20, 2 );
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 20, 3 );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ), 20 );
		foreach ( array( 'save_post', 'deleted_post', 'created_term', 'edited_term', 'delete_term' ) as $hook ) add_action( $hook, array( __CLASS__, 'forget' ) );
		add_action( 'update_option_permalink_structure', array( __CLASS__, 'forget' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_import_rules' ) );
	}

	public static function on( $kind ) {
		$key = array_search( $kind, self::KEYS, true );
		if ( ! $key || Digitalisimo_Integrations_SEO_Resolver::foreign_seo() ) return false;
		if ( 'category' !== $kind && ! function_exists( 'WC' ) ) return false;
		return (bool) Digitalisimo_Integrations_SEO_Resolver::option( $key, 0 ) && '' !== self::base( $kind );
	}

	/** Base actual de un tipo, sin barras; '' si usa etiquetas y no se puede quitar con seguridad. */
	public static function base( $kind ) {
		$woo = (array) get_option( 'woocommerce_permalinks', array() );
		if ( 'product' === $kind ) $base = (string) ( $woo['product_base'] ?? '' ) ?: 'product';
		elseif ( 'product_cat' === $kind ) $base = (string) ( $woo['category_base'] ?? '' ) ?: 'product-category';
		else $base = (string) get_option( 'category_base' ) ?: 'category';
		$base = trim( $base, '/' );
		return false !== strpos( $base, '%' ) ? '' : $base;
	}

	/**
	 * Quita la base de una URL. Pura: '' si la URL no la lleva.
	 */
	public static function strip( $url, $home, $base ) {
		$home = trailingslashit( (string) $home );
		$base = trim( (string) $base, '/' );
		if ( '' === $base || 0 !== strpos( (string) $url, $home . $base . '/' ) ) return '';
		return $home . substr( (string) $url, strlen( $home . $base . '/' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Rutas ocupadas por páginas y entradas.                               */
	/* ------------------------------------------------------------------ */

	/** @var array|null Rutas ocupadas de esta petición. */
	private static $taken = null;

	public static function forget() { self::$taken = null; delete_option( self::TAKEN ); }

	/** Rutas de primer nivel de páginas y, con /%postname%/, de entradas: una consulta y una opción sin autoload. */
	private static function taken() {
		if ( null !== self::$taken ) return self::$taken;
		$taken = get_option( self::TAKEN );
		if ( is_array( $taken ) ) return self::$taken = $taken;
		global $wpdb;
		$types = array( 'page' );
		if ( '/%postname%/' === (string) get_option( 'permalink_structure' ) ) $types[] = 'post';
		$in    = "'" . implode( "','", $types ) . "'";
		$taken = array_fill_keys( array_map( 'strval', $wpdb->get_col( "SELECT post_name FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_parent = 0 AND post_status IN ('publish','private','future','draft','pending') AND post_name <> ''" ) ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		update_option( self::TAKEN, $taken, false );
		return self::$taken = $taken;
	}

	/** ¿Una ruta sin base chocaría con una página o entrada? */
	private static function collides( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) return true;
		if ( false === strpos( $path, '/' ) ) return isset( self::taken()[ $path ] );
		return (bool) get_page_by_path( $path );
	}

	/* ------------------------------------------------------------------ */
	/* Enlaces.                                                             */
	/* ------------------------------------------------------------------ */

	public static function product_link( $link, $post ) {
		if ( ! $post || 'product' !== $post->post_type || ! self::on( 'product' ) ) return $link;
		$stripped = self::strip( $link, home_url( '/' ), self::base( 'product' ) );
		if ( '' === $stripped ) return $link;
		list( $path ) = Digitalisimo_Integrations_Redirections::relative( $stripped, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		return false === strpos( $path, '/' ) && ! self::collides( $path ) ? $stripped : $link;
	}

	public static function term_link( $link, $term, $taxonomy ) {
		if ( ! in_array( $taxonomy, array( 'product_cat', 'category' ), true ) || ! self::on( $taxonomy ) ) return $link;
		$stripped = self::strip( $link, home_url( '/' ), self::base( $taxonomy ) );
		if ( '' === $stripped ) return $link;
		list( $path ) = Digitalisimo_Integrations_Redirections::relative( $stripped, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( self::collides( $path ) ) return $link;
		// Un producto con la misma ruta también gana a la categoría.
		if ( false === strpos( $path, '/' ) && self::on( 'product' ) && get_page_by_path( $path, OBJECT, 'product' ) ) return $link;
		return $stripped;
	}

	/* ------------------------------------------------------------------ */
	/* Resolución de rutas sin base.                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Sólo cuando WordPress buscaba una página o entrada que no existe: entonces prueba un producto, una
	 * categoría de producto y una categoría de entradas, en ese orden, y sólo con su enlace sin base.
	 */
	public static function parse_request( $wp ) {
		if ( $wp instanceof WP ) $wp->query_vars = self::resolve( (array) $wp->query_vars, (string) $wp->request );
	}

	/** Variables de consulta para una ruta sin base, o las mismas si no aplica. */
	public static function resolve( $vars, $request ) {
		if ( is_admin() || ! ( self::on( 'product' ) || self::on( 'product_cat' ) || self::on( 'category' ) ) ) return $vars;
		if ( empty( $vars['pagename'] ) && empty( $vars['name'] ) && empty( $vars['error'] ) ) return $vars;
		if ( ! empty( $vars['pagename'] ) && get_page_by_path( $vars['pagename'] ) ) return $vars;
		if ( ! empty( $vars['name'] ) && get_page_by_path( $vars['name'], OBJECT, 'post' ) ) return $vars;
		$path  = trim( (string) $request, '/' );
		$paged = 0;
		if ( preg_match( '#^(.+)/page/(\d+)$#', $path, $m ) ) { $path = $m[1]; $paged = (int) $m[2]; }
		if ( '' === $path || preg_match( '#[^a-z0-9/_%-]#i', $path ) ) return $vars;
		if ( ! $paged && false === strpos( $path, '/' ) && self::on( 'product' ) ) {
			$product = get_page_by_path( $path, OBJECT, 'product' );
			if ( $product && 'publish' === $product->post_status && get_permalink( $product ) === home_url( '/' . $path . '/' ) ) return array( 'product' => $path, 'post_type' => 'product', 'name' => $path );
		}
		foreach ( array( 'product_cat' => 'product_cat', 'category' => 'category_name' ) as $taxonomy => $var ) {
			if ( ! self::on( $taxonomy ) ) continue;
			$leaf = basename( $path );
			$term = get_term_by( 'slug', $leaf, $taxonomy );
			if ( ! $term || trailingslashit( get_term_link( $term ) ) !== home_url( '/' . $path . '/' ) ) continue;
			$out = 'category' === $taxonomy ? array( 'category_name' => $path ) : array( 'product_cat' => $leaf );
			if ( $paged ) $out['paged'] = $paged;
			return $out;
		}
		return $vars;
	}

	/* ------------------------------------------------------------------ */
	/* Cambios de ajuste.                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Las 301 que creó el importador (URL sin base → con base) harían un bucle con la base quitada:
	 * se desactivan mientras la opción esté encendida y vuelven al apagarla. Compara un estado guardado,
	 * así también nota un cambio heredado de la red, y sólo escribe cuando cambia.
	 */
	public static function sync_import_rules() {
		global $wpdb;
		$state = array();
		foreach ( self::KEYS as $key => $kind ) $state[ $kind ] = (bool) Digitalisimo_Integrations_SEO_Resolver::option( $key, 0 );
		if ( get_option( self::STATE ) === $state ) return;
		update_option( self::STATE, $state, false );
		if ( ! class_exists( 'Digitalisimo_Integrations_Redirections' ) || (int) get_option( Digitalisimo_Integrations_Redirections::DB_OPTION ) < 1 ) return;
		$changed = false;
		foreach ( self::KEYS as $key => $kind ) {
			$status = $state[ $kind ] ? 'inactive' : 'active';
			$changed = (bool) $wpdb->query( $wpdb->prepare( 'UPDATE ' . Digitalisimo_Integrations_Redirections::table() . ' SET status = %s WHERE import_key LIKE %s AND status <> %s', $status, $wpdb->esc_like( 'rankmath-base:' . $kind . ':' ) . '%', $status ) ) || $changed; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $changed ) Digitalisimo_Integrations_Redirections::refresh_index();
	}
}
