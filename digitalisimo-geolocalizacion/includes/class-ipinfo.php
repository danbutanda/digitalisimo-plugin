<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Integrations_IPInfo {
	public static function init() { if ( Digitalisimo_Geolocation_Settings::get( 'enable_ipinfo' ) ) add_shortcode( 'ubicacion_usuario', array( __CLASS__, 'shortcode' ) ); add_filter( 'digitalisimo_ai_context', array( __CLASS__, 'ai_context' ), 10, 3 ); }
	public static function ai_context( $context, $profile ) { if ( 'chatbot' !== $profile ) return $context; $settings = (array) get_option( 'digitalisimo_integrations_options', array() ); $parts = array_filter( array( $settings['seo_local_city'] ?? '', $settings['seo_local_region'] ?? '', $settings['seo_local_country'] ?? '' ) ); return $parts ? $context . "\nUbicación comercial declarada: " . implode( ', ', array_map( 'sanitize_text_field', $parts ) ) : $context; }
	public static function shortcode() {
		$tokens = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) Digitalisimo_Geolocation_Settings::get( 'ipinfo_tokens' ) ) ) ) ); if ( ! $tokens ) return '';
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ); if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return '';
		$cache_key = 'digitalisimo_ipinfo_' . md5( $ip ); $cached = get_transient( $cache_key ); if ( false !== $cached ) return esc_html( $cached );
		$index = absint( get_option( 'digitalisimo_ipinfo_token_index', 0 ) ) % count( $tokens ); $response = wp_remote_get( 'https://ipinfo.io/' . rawurlencode( $ip ) . '/json?token=' . rawurlencode( $tokens[ $index ] ), array( 'timeout' => 10 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return '';
		$data = json_decode( wp_remote_retrieve_body( $response ), true ); $parts = array_filter( array( $data['city'] ?? '', $data['region'] ?? '', $data['country'] ?? '' ) ); if ( ! $parts ) return '';
		$value = 'en ' . implode( ', ', array_map( 'sanitize_text_field', $parts ) ); $minutes = absint( Digitalisimo_Geolocation_Settings::get( 'ipinfo_cache_minutes', 60 ) ); if ( $minutes ) set_transient( $cache_key, $value, MINUTE_IN_SECONDS * $minutes ); return esc_html( $value );
	}
}
