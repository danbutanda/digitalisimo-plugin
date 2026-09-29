<?php
defined( 'ABSPATH' ) || exit;

/**
 * Verifica manualmente la procedencia de un crawler usando mecanismos oficiales.
 * Nunca se ejecuta durante peticiones públicas ni confía sólo en User-Agent.
 */
class Digitalisimo_Integrations_SEO_AI_Crawler_Verifier {
	const CACHE_PREFIX = 'digitalisimo_seo_ai_bot_verify_';
	const CANDIDATES_OPTION = 'digitalisimo_seo_ai_bot_candidates';
	const BLOCKED_OPTION = 'digitalisimo_seo_ai_blocked_bots';
	const PROCESS_HOOK = 'digitalisimo_seo_ai_process_bot_candidates';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 32 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 32 );
		add_action( 'wp', array( __CLASS__, 'observe_public_request' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'block_fake_bot' ), 0 );
		add_action( self::PROCESS_HOOK, array( __CLASS__, 'process_candidates' ) );
	}
	public static function menu() { add_submenu_page( null, 'SEO AI · Verificar crawler', 'SEO AI · Verificar crawler', 'manage_options', 'digitalisimo-seo-ai-verify-crawler', array( __CLASS__, 'page' ) ); }
	public static function network_menu() {}

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
	/** Sólo interpreta identificadores de bots conocidos; nunca usa X-Forwarded-For, que puede falsificarse. */
	private static function claimed_method() { $ua = strtolower( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ); if ( false !== strpos( $ua, 'googlebot' ) || false !== strpos( $ua, 'google-inspectiontool' ) || false !== strpos( $ua, 'googleother' ) ) return 'google'; if ( false !== strpos( $ua, 'bingbot' ) || false !== strpos( $ua, 'adidxbot' ) ) return 'bing'; return ''; }
	private static function request_ip() { $ip = trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ); return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : ''; }
	private static function candidates() { $items = get_option( self::CANDIDATES_OPTION, array() ); return is_array( $items ) ? $items : array(); }
	private static function schedule_processing() { if ( ! wp_next_scheduled( self::PROCESS_HOOK ) ) wp_schedule_single_event( time() + 30, self::PROCESS_HOOK ); }
	/** La petición pública sólo registra una IP nueva; el DNS se consulta después desde WP-Cron. */
	public static function observe_public_request() { if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || ! ( $method = self::claimed_method() ) || ! ( $ip = self::request_ip() ) ) return; $items = self::candidates(); $id = md5( $method . '|' . $ip ); if ( isset( $items[ $id ] ) ) return; $items[ $id ] = array( 'ip' => $ip, 'method' => $method, 'status' => 'pending', 'first_seen' => time(), 'checked' => 0 ); update_option( self::CANDIDATES_OPTION, array_slice( $items, -100, null, true ), false ); self::schedule_processing(); }
	/** Verifica pocos elementos por ejecución para no concentrar consultas DNS. */
	public static function process_candidates() { $items = self::candidates(); $processed = 0; foreach ( $items as $id => $item ) { if ( $processed >= 5 || 'pending' !== ( $item['status'] ?? '' ) ) continue; $result = self::verify( $item['ip'] ?? '', $item['method'] ?? '' ); $items[ $id ] = array_merge( $item, array( 'status' => ! empty( $result['valid'] ) ? 'verified' : 'invalid', 'checked' => time(), 'host' => $result['host'] ?? '', 'message' => $result['message'] ?? '' ) ); $processed++; } update_option( self::CANDIDATES_OPTION, array_slice( $items, -100, null, true ), false ); foreach ( $items as $item ) if ( 'pending' === ( $item['status'] ?? '' ) ) { self::schedule_processing(); break; } }
	/** El bloqueo se decide en administración; en visitas públicas sólo se consulta una lista local pequeña. */
	public static function block_fake_bot() { if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! ( $ip = self::request_ip() ) ) return; $blocked = (array) get_option( self::BLOCKED_OPTION, array() ); if ( empty( $blocked[ $ip ] ) ) return; status_header( 403 ); nocache_headers(); wp_die( esc_html__( 'Acceso denegado.', 'digitalisimo-integrations' ), '', array( 'response' => 403 ) ); }
	private static function update_block( $ip, $block ) { if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return; $items = (array) get_option( self::BLOCKED_OPTION, array() ); if ( $block ) $items[ $ip ] = array( 'time' => time() ); else unset( $items[ $ip ] ); update_option( self::BLOCKED_OPTION, $items, false ); }
	private static function observations_html() { $items = array_reverse( self::candidates(), true ); $blocked = (array) get_option( self::BLOCKED_OPTION, array() ); echo '<h2>Visitas que declararon ser buscadores</h2><p class="description">Se detectan automáticamente en solicitudes que llegan a WordPress. La comprobación DNS se ejecuta en segundo plano y usa caché; no lee ni procesa los logs completos del servidor.</p>'; if ( ! $items ) { echo '<p>No hay IPs pendientes o verificadas todavía.</p>'; return; } echo '<table class="widefat striped"><thead><tr><th>IP</th><th>Declara ser</th><th>Resultado</th><th>Host / detalle</th><th>Acción</th></tr></thead><tbody>'; foreach ( $items as $item ) { $ip = (string) ( $item['ip'] ?? '' ); $status = (string) ( $item['status'] ?? 'pending' ); $label = 'verified' === $status ? '✓ Verificado' : ( 'invalid' === $status ? '⚠ Falso o no verificable' : 'Pendiente' ); echo '<tr><td><code>' . esc_html( $ip ) . '</code></td><td>' . esc_html( self::methods()[ $item['method'] ?? '' ]['label'] ?? 'Desconocido' ) . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $item['host'] ?? '' ) . ( ! empty( $item['message'] ) ? '<br><small>' . esc_html( $item['message'] ) . '</small>' : '' ) . '</td><td>'; if ( 'invalid' === $status && empty( $blocked[ $ip ] ) ) { echo '<form method="post">'; wp_nonce_field( 'digitalisimo_block_fake_bot' ); echo '<input type="hidden" name="crawler_block_ip" value="' . esc_attr( $ip ) . '"><button class="button button-secondary" name="digitalisimo_block_fake_bot" value="block">Bloquear IP</button></form>'; } elseif ( ! empty( $blocked[ $ip ] ) ) { echo '<form method="post">'; wp_nonce_field( 'digitalisimo_block_fake_bot' ); echo '<input type="hidden" name="crawler_block_ip" value="' . esc_attr( $ip ) . '"><button class="button button-secondary" name="digitalisimo_block_fake_bot" value="unblock">Quitar bloqueo</button></form>'; } else echo '—'; echo '</td></tr>'; } echo '</tbody></table>'; }

	public static function page() {
		$network = is_network_admin(); if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$result = null;
		if ( isset( $_POST['digitalisimo_block_fake_bot'] ) && check_admin_referer( 'digitalisimo_block_fake_bot' ) ) self::update_block( sanitize_text_field( wp_unslash( $_POST['crawler_block_ip'] ?? '' ) ), 'block' === sanitize_key( wp_unslash( $_POST['digitalisimo_block_fake_bot'] ) ) );
		if ( isset( $_POST['digitalisimo_verify_crawler'] ) && check_admin_referer( 'digitalisimo_verify_crawler' ) ) $result = self::verify( sanitize_text_field( wp_unslash( $_POST['crawler_ip'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['crawler_method'] ?? '' ) ) );
		echo '<div class="wrap digitalisimo-admin-shell"><h1>SEO AI · Verificar crawler</h1><p>Detecta solicitudes que afirman ser Google o Bing, las verifica en segundo plano y permite bloquear manualmente sólo las que resulten falsas. También puedes comprobar una IP concreta.</p><form method="post">'; wp_nonce_field( 'digitalisimo_verify_crawler' );
		echo '<p><label><strong>Dirección IP</strong><br><input class="regular-text" name="crawler_ip" placeholder="66.249.66.1" required></label></p><p><label><strong>Proveedor</strong><br><select name="crawler_method">';
		foreach ( self::methods() as $id => $method ) echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $method['label'] ) . '</option>';
		echo '</select></label></p>'; submit_button( 'Verificar IP', 'primary', 'digitalisimo_verify_crawler' ); echo '</form>';
		if ( is_array( $result ) ) { $class = ! empty( $result['valid'] ) ? 'notice-success' : 'notice-warning'; echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p><strong>' . ( ! empty( $result['valid'] ) ? 'Crawler verificado' : 'No verificado' ) . '.</strong> ' . esc_html( $result['message'] ) . ( ! empty( $result['host'] ) ? '<br>Host inverso: <code>' . esc_html( $result['host'] ) . '</code>' : '' ) . ( ! empty( $result['cached'] ) ? '<br><em>Resultado obtenido de caché.</em>' : '' ) . '</p></div>'; }
		if ( ! $network ) self::observations_html(); else echo '<p class="description">La detección y los bloqueos se consultan dentro de cada sitio de la red.</p>';
		echo '<h2>Fuentes oficiales</h2><ul>'; foreach ( self::methods() as $method ) echo '<li><a target="_blank" rel="noopener noreferrer" href="' . esc_url( $method['documentation'] ) . '">' . esc_html( $method['label'] ) . '</a></li>'; echo '</ul><p class="description">OpenAI y otros proveedores sólo se podrán verificar aquí cuando publiquen un mecanismo oficial apropiado; una coincidencia de User-Agent nunca se marca como verificación.</p></div>';
	}
}
