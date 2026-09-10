<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Ecommerce_Settings {
	public static function get( $key, $default = '' ) {
		$options = (array) get_option( 'digitalisimo_integrations_options', array() );
		$inherit = (array) get_option( 'digitalisimo_module_network_inherit', array() );
		if ( is_multisite() && ( ! array_key_exists( $key, $inherit ) || ! empty( $inherit[ $key ] ) ) ) {
			$network = (array) get_site_option( 'digitalisimo_integrations_options', array() );
			if ( array_key_exists( $key, $network ) ) return $network[ $key ];
		}
		return $options[ $key ] ?? $default;
	}
	public static function origin( $key ) {
		$inherit = (array) get_option( 'digitalisimo_module_network_inherit', array() );
		$network = (array) get_site_option( 'digitalisimo_integrations_options', array() );
		return is_multisite() && ( ! array_key_exists( $key, $inherit ) || ! empty( $inherit[ $key ] ) ) && array_key_exists( $key, $network ) ? 'Configuración de red' : 'Este sitio';
	}
}
