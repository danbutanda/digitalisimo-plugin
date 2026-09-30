<?php
defined( 'ABSPATH' ) || exit;

/** Caché privada del módulo; nunca vacía la caché global de WordPress o Redis. */
class Digitalisimo_Integrations_Performance_Cache {
	const GROUP = 'digitalisimo_performance';
	const GENERATION = 'digitalisimo_performance_cache_generation';
	const NETWORK_GENERATION = 'digitalisimo_performance_network_generation';

	public static function init() {
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'invalidate' ) );
		add_action( 'update_option_digitalisimo_seo_network_inherit', array( __CLASS__, 'invalidate' ) );
		if ( is_multisite() ) add_action( 'update_site_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'invalidate_network' ) );
	}

	private static function key( $name ) {
		$generation = max( 1, (int) get_option( self::GENERATION, 1 ) );
		$network_generation = is_multisite() ? max( 1, (int) get_site_option( self::NETWORK_GENERATION, 1 ) ) : 1;
		return get_current_blog_id() . ':' . $generation . ':' . $network_generation . ':' . sanitize_key( $name );
	}

	public static function get( $name, &$found = null ) {
		return wp_cache_get( self::key( $name ), self::GROUP, false, $found );
	}

	public static function set( $name, $value, $ttl = 900 ) {
		return wp_cache_set( self::key( $name ), $value, self::GROUP, max( 1, min( 3600, (int) $ttl ) ) );
	}

	public static function delete( $name ) {
		return wp_cache_delete( self::key( $name ), self::GROUP );
	}

	/** Cambia sólo el espacio de claves de este sitio. Las entradas viejas expiran por TTL. */
	public static function invalidate() {
		$generation = max( 1, (int) get_option( self::GENERATION, 1 ) );
		foreach ( array( 'kit', 'fonts', 'whitelist', 'resources', 'woff2', 'settings' ) as $name ) self::delete( $name );
		update_option( self::GENERATION, $generation + 1, false );
		return $generation + 1;
	}

	/** Cambiar defaults de red invalida las lecturas heredadas sin recorrer todos los sitios. */
	public static function invalidate_network() {
		$generation = max( 1, (int) get_site_option( self::NETWORK_GENERATION, 1 ) );
		update_site_option( self::NETWORK_GENERATION, $generation + 1 );
		return $generation + 1;
	}

	/** La inspección de archivos sólo ocurre en administración. */
	public static function status() {
		$dropin = WP_CONTENT_DIR . '/object-cache.php';
		$present = is_file( $dropin );
		$header = $present && is_readable( $dropin ) ? file_get_contents( $dropin, false, null, 0, 16384 ) : '';
		return array(
			'external' => (bool) wp_using_ext_object_cache(),
			'dropin' => $present,
			'redis' => is_string( $header ) && (bool) preg_match( '/\bredis\b/i', $header ),
		);
	}
}
