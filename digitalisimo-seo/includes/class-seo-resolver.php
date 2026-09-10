<?php
defined( 'ABSPATH' ) || exit;

/** Resuelve valores SEO: contenido/termino → sitio → red Multisite → fallback. */
class Digitalisimo_Integrations_SEO_Resolver {
	public static function option( $key, $fallback = null ) {
		$site = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		if ( is_multisite() && self::inherits_network( $key ) ) {
			$network = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			if ( array_key_exists( $key, $network ) ) return $network[ $key ];
		}
		if ( array_key_exists( $key, $site ) ) return $site[ $key ];
		$defaults = Digitalisimo_Integrations_Settings::defaults();
		return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $fallback;
	}

	/** Los sitios nuevos heredan dinámicamente; sólo una sobrescritura explícita rompe la herencia. */
	public static function inherits_network( $key ) {
		if ( ! is_multisite() ) return false;
		$module_keys = array( 'enable_woocommerce', 'require_domain_hosting', 'replace_hosting', 'simplify_checkout', 'namecheap_api_user', 'namecheap_username', 'namecheap_api_key', 'namecheap_client_ip', 'domain_products', 'hosting_product_ids', 'hosting_category_ids', 'enable_ipinfo', 'ipinfo_tokens', 'ipinfo_cache_minutes' );
		if ( in_array( $key, $module_keys, true ) ) { $module_inherit = (array) get_option( 'digitalisimo_module_network_inherit', array() ); return ! array_key_exists( $key, $module_inherit ) || ! empty( $module_inherit[ $key ] ); }
		$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
		return ! array_key_exists( $key, $inherit ) || ! empty( $inherit[ $key ] );
	}

	public static function option_with_origin( $key, $fallback = null ) {
		if ( is_multisite() && self::inherits_network( $key ) ) {
			$network = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			if ( array_key_exists( $key, $network ) ) return array( 'value' => $network[ $key ], 'origin' => 'network', 'label' => 'Configuración de red' );
		}
		$site = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		if ( array_key_exists( $key, $site ) ) return array( 'value' => $site[ $key ], 'origin' => 'site', 'label' => 'Este sitio' );
		$defaults = Digitalisimo_Integrations_Settings::defaults();
		return array( 'value' => array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $fallback, 'origin' => 'default', 'label' => 'Predeterminado del plugin' );
	}

	public static function post( $post_id, $key, $fallback = '' ) {
		$value = get_post_meta( $post_id, 'digitalisimo_seo_' . $key, true );
		return ( '' !== $value && array() !== $value ) ? $value : self::option( $key, $fallback );
	}

	public static function term( $term_id, $key, $fallback = '' ) {
		$value = get_term_meta( $term_id, 'digitalisimo_seo_' . $key, true );
		return ( '' !== $value && array() !== $value ) ? $value : self::option( $key, $fallback );
	}
}
