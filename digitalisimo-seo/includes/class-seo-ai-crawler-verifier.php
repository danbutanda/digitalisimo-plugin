<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verifica manualmente la procedencia de un crawler usando mecanismos oficiales.
 * Nunca se ejecuta durante peticiones públicas ni confía sólo en User-Agent.
 */
class Digitalisimo_Integrations_SEO_AI_Crawler_Verifier {
	const CACHE_PREFIX = 'digitalisimo_seo_ai_bot_verify_';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 32 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 32 );
	}
	public static function menu() { add_submenu_page( null, 'SEO AI · Verificar crawler', 'SEO AI · Verificar crawler', 'manage_options', 'digitalisimo-seo-ai-verify-crawler', array( __CLASS__, 'page' ) ); }
	public static function network_menu() { add_submenu_page( 'digitalisimo-network', 'SEO AI · Verificar crawler', 'SEO AI · Verificar crawler', 'manage_network_options', 'digitalisimo-network-seo-ai-verify-crawler', array( __CLASS__, 'page' ) ); }

	/** El registro es extensible sin dispersar reglas de confianza por el plugin. */
	public static function methods() {
		return apply_filters( 'digitalisimo_seo_ai_crawler_verification_methods', array(
			'google' => array( 'label' => 'Google', 'suffixes' => array( 'googlebot.com', 'google.com', 'googleusercontent.com' ), 'documentation' => 'https://developers.google.com/crawling/docs/crawlers-fetchers/verify-google-requests' ),
			'bing' => array( 'label' => 'Microsoft Bing', 'suffixes' => array( 'search.msn.com' ), 'documentation' => 'https://www.bing.com/webmasters/help/how-to-verify-bingbot-3905dc26' ),
		) );
	}
	private static function host_matches( $host, $suffixes ) {
		$host = strtolower( rtrim( (string) $host, '.' ) );
		foreach ( (array) $suffixes as $suffix ) {
			$suffix = strtolower( trim( (string) $suffix, '.' ) );
			if ( $host === $suffix || substr( $host, -strlen( '.' . $suffix ) ) === '.' . $suffix ) return true;
		}
		return false;
	}

	/** Reverse DNS + forward confirmation. Positivos: 7 días; negativos: 48 horas. */
	public static function verify( $ip, $method ) {
		$ip = trim( (string) $ip ); $methods = self::methods();
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || empty( $methods[ $method ] ) ) return array( 'valid' => false, 'message' => 'La IP o el método de verificación no son válidos.', 'cached' => false );
		$cache_key = self::CACHE_PREFIX . md5( $method . '|' . $ip ); $cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) { $cached['cached'] = true; return $cached; }
		$host = gethostbyaddr( $ip );
		$result = array( 'valid' => false, 'cached' => false, 'ip' => $ip, 'host' => $host, 'method' => $method, 'message' => '' );
		if ( ! $host || $host === $ip || ! self::host_matches( $host, $methods[ $method ]['suffixes'] ) ) {
			$result['message'] = 'El DNS inverso no corresponde a un dominio oficial de ' . $methods[ $method ]['label'] . '.'; set_transient( $cache_key, $result, 2 * DAY_IN_SECONDS ); return $result;
		}
		$forward = gethostbynamel( $host );
		if ( ! is_array( $forward ) || ! in_array( $ip, $forward, true ) ) {
			$result['message'] = 'El DNS directo no confirmó la misma dirección IP.'; set_transient( $cache_key, $result, 2 * DAY_IN_SECONDS ); return $result;
		}
		$result['valid'] = true; $result['message'] = 'Verificado mediante DNS inverso y confirmación DNS directa.'; set_transient( $cache_key, $result, 7 * DAY_IN_SECONDS ); return $result;
	}

	public static function page() {
		$network = is_network_admin(); if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$result = null;
		if ( isset( $_POST['digitalisimo_verify_crawler'] ) && check_admin_referer( 'digitalisimo_verify_crawler' ) ) $result = self::verify( sanitize_text_field( wp_unslash( $_POST['crawler_ip'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['crawler_method'] ?? '' ) ) );
		echo '<div class="wrap"><h1>SEO AI · Verificar crawler</h1><p>Comprueba una IP observada en tus logs. No altera reglas de acceso y no se ejecuta durante visitas públicas.</p><form method="post">'; wp_nonce_field( 'digitalisimo_verify_crawler' );
		echo '<p><label><strong>Dirección IP</strong><br><input class="regular-text" name="crawler_ip" placeholder="66.249.66.1" required></label></p><p><label><strong>Proveedor</strong><br><select name="crawler_method">';
		foreach ( self::methods() as $id => $method ) echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $method['label'] ) . '</option>';
		echo '</select></label></p>'; submit_button( 'Verificar IP', 'primary', 'digitalisimo_verify_crawler' ); echo '</form>';
		if ( is_array( $result ) ) { $class = ! empty( $result['valid'] ) ? 'notice-success' : 'notice-warning'; echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p><strong>' . ( ! empty( $result['valid'] ) ? 'Crawler verificado' : 'No verificado' ) . '.</strong> ' . esc_html( $result['message'] ) . ( ! empty( $result['host'] ) ? '<br>Host inverso: <code>' . esc_html( $result['host'] ) . '</code>' : '' ) . ( ! empty( $result['cached'] ) ? '<br><em>Resultado obtenido de caché.</em>' : '' ) . '</p></div>'; }
		echo '<h2>Fuentes oficiales</h2><ul>'; foreach ( self::methods() as $method ) echo '<li><a target="_blank" rel="noopener noreferrer" href="' . esc_url( $method['documentation'] ) . '">' . esc_html( $method['label'] ) . '</a></li>'; echo '</ul><p class="description">OpenAI y otros proveedores sólo se podrán verificar aquí cuando publiquen un mecanismo oficial apropiado; una coincidencia de User-Agent nunca se marca como verificación.</p></div>';
	}
}
