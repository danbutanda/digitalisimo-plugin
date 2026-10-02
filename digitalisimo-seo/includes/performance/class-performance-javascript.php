<?php
defined( 'ABSPATH' ) || exit;

/**
 * Experimento: defer seguro de jQuery y de todo lo que depende de él.
 *
 * Nunca elimina, desregistra ni reemplaza jQuery, ni usa async. Pide la
 * estrategia «defer» con la API nativa de WordPress (6.3+) para jquery-core,
 * jquery-migrate y cada script encolado que depende de ellos; WordPress sólo
 * la concede si toda la cadena puede diferirse, lo que preserva el orden.
 *
 * WordPress no ve el código inline impreso por temas, plugins o un widget HTML,
 * ni los <script> escritos a mano. Por eso, antes de enviar la página, se
 * revisa el HTML final: si algo podría ejecutarse antes que jQuery y
 * necesitarlo, se retira el defer de toda la cadena en esa página. Y si aun así
 * el navegador registra «jQuery is not defined», el sitio vuelve a la carga
 * normal hasta que un administrador lo reactive.
 */
class Digitalisimo_Integrations_Performance_JavaScript {
	const FALLBACK = 'digitalisimo_performance_js_fallback';
	const AJAX     = 'digitalisimo_js_fallback';
	/** jquery es un alias sin archivo; quien depende de él depende de core y migrate. */
	const ROOTS = array( 'jquery', 'jquery-core', 'jquery-migrate' );

	private static $requested = array();
	private static $active    = false;
	private static $report    = array();
	private static $snippet   = '';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'request_defer' ), PHP_INT_MAX );
		// Elementor encola scripts de widgets mientras pinta el contenido, después del <head>.
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'request_defer' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'error_guard' ), 1 );
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'record_failure' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( __CLASS__, 'record_failure' ) );
		add_action( 'admin_post_digitalisimo_performance_js_reset', array( __CLASS__, 'reset_action' ) );
		add_filter( 'digitalisimo_performance_probe_html', array( __CLASS__, 'probe_html' ) );
		add_filter( 'digitalisimo_performance_probe_js_report', array( __CLASS__, 'probe_report' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Ajustes y estado
	 * ------------------------------------------------------------------ */

	public static function sanitize_mode( $value ) {
		return 'defer' === $value ? 'defer' : 'off';
	}

	/** La API de estrategias de carga llegó con WordPress 6.3. */
	public static function supported() {
		return function_exists( 'get_bloginfo' ) && version_compare( (string) get_bloginfo( 'version' ), '6.3', '>=' );
	}

	public static function fallback() {
		$state = get_option( self::FALLBACK, array() );
		return is_array( $state ) && ! empty( $state['at'] ) ? $state : array();
	}

	/** Activo sólo si se eligió, hay soporte, no hubo errores y la petición es pública. */
	public static function enabled() {
		return 'defer' === self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_js_jquery_mode' ) ) && self::supported() && ! self::fallback();
	}

	private static function active_request() {
		return self::enabled() && Digitalisimo_Integrations_Performance_Manager::frontend_safe() && ! is_feed() && ! is_embed();
	}

	/* ------------------------------------------------------------------ *
	 * Árbol de dependencias y estrategia nativa
	 * ------------------------------------------------------------------ */

	/** ¿El script depende, directa o indirectamente, de jQuery? */
	public static function depends_on_jquery( $registry, $handle, $seen = array() ) {
		if ( in_array( $handle, self::ROOTS, true ) ) return true;
		if ( isset( $seen[ $handle ] ) || ! isset( $registry->registered[ $handle ] ) ) return false;
		$seen[ $handle ] = true;
		foreach ( (array) $registry->registered[ $handle ]->deps as $dependency ) if ( self::depends_on_jquery( $registry, $dependency, $seen ) ) return true;
		return false;
	}

	/** Scripts que se imprimirán: la cola más sus dependencias. */
	public static function printed_handles( $registry ) {
		$handles = array();
		$walk = function( $handle ) use ( &$walk, &$handles, $registry ) {
			if ( isset( $handles[ $handle ] ) || ! isset( $registry->registered[ $handle ] ) ) return;
			$handles[ $handle ] = true;
			foreach ( (array) $registry->registered[ $handle ]->deps as $dependency ) $walk( $dependency );
		};
		foreach ( (array) $registry->queue as $handle ) $walk( $handle );
		return array_keys( $handles );
	}

	/** La cadena de jQuery: las raíces y todo lo que depende de ellas. */
	public static function chain( $registry ) {
		$chain = array();
		foreach ( self::printed_handles( $registry ) as $handle ) if ( self::depends_on_jquery( $registry, $handle ) ) $chain[] = $handle;
		return $chain;
	}

	/**
	 * Pide defer para toda la cadena. Un script que ya trae su propia
	 * estrategia (incluida async) no se toca. WordPress decide después, con su
	 * propio árbol, si cada uno puede diferirse sin romper el orden.
	 */
	public static function request_defer() {
		if ( ! self::active_request() || ! function_exists( 'wp_scripts' ) ) return;
		$registry = wp_scripts();
		foreach ( self::chain( $registry ) as $handle ) {
			if ( isset( self::$requested[ $handle ] ) || '' !== (string) $registry->get_data( $handle, 'strategy' ) ) continue;
			$registry->add_data( $handle, 'strategy', 'defer' );
			self::$requested[ $handle ] = true;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Revisión del HTML final
	 * ------------------------------------------------------------------ */

	public static function start() {
		if ( ! self::active_request() ) return;
		self::$active = true;
		ob_start( array( __CLASS__, 'buffer' ) );
	}

	public static function buffer( $html, $phase = 0 ) {
		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) return $html;
		try {
			return is_string( $html ) && false !== stripos( $html, '<script' ) && preg_match( '/<html[\s>]/i', $html ) ? self::process( $html, wp_scripts(), self::$requested ) : $html;
		} catch ( Throwable $error ) {
			return $html;
		}
	}

	public static function probe_html( $html ) {
		return self::$active && is_string( $html ) ? self::process( $html, wp_scripts(), self::$requested ) : $html;
	}

	public static function probe_report( $report ) {
		return self::$active ? self::$report : $report;
	}

	/** Etiquetas <script> en orden de documento, con sus atributos y su contenido. */
	public static function scripts_in( $html ) {
		$scripts = array();
		if ( ! preg_match_all( '~<script\b([^>]*)>(.*?)</script\s*>~is', (string) $html, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) return $scripts;
		foreach ( $found as $match ) {
			$attrs = array();
			if ( preg_match_all( '/([a-zA-Z][\w:-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', $match[1][0], $pairs, PREG_SET_ORDER ) ) {
				foreach ( $pairs as $pair ) {
					$name = strtolower( $pair[1] );
					if ( ! isset( $attrs[ $name ] ) ) $attrs[ $name ] = $pair[2] ?? '' ?: ( $pair[3] ?? '' ?: ( $pair[4] ?? '' ) );
				}
			}
			$type = strtolower( trim( (string) ( $attrs['type'] ?? '' ) ) );
			$id   = (string) ( $attrs['id'] ?? '' );
			$scripts[] = array(
				'tag'        => $match[0][0],
				'offset'     => $match[0][1],
				'attrs'      => $attrs,
				'external'   => isset( $attrs['src'] ),
				'handle'     => preg_match( '/^(.+)-js$/', $id, $m ) ? $m[1] : '',
				'id'         => $id,
				'content'    => $match[2][0],
				'executable' => in_array( $type, array( '', 'text/javascript', 'application/javascript', 'text/ecmascript', 'application/ecmascript' ), true ),
				'deferred'   => isset( $attrs['defer'] ) || isset( $attrs['async'] ),
			);
		}
		return $scripts;
	}

	/** Código inline que usa jQuery o $ y se ejecutaría al leerse. */
	public static function inline_uses_jquery( $content ) {
		return (bool) preg_match( '/\bjQuery\b|(?<![\w$.])\$\s*\(|(?<![\w$.])\$\.[A-Za-z_]/', (string) $content );
	}

	/**
	 * Motivo por el que diferir sería inseguro en esta página, o cadena vacía.
	 * Sólo cuenta lo que aparece después del primer script diferido de la
	 * cadena: lo anterior ya se ejecutaba antes de jQuery.
	 */
	public static function unsafe_reason( $scripts, $registry, $requested ) {
		$first = null;
		foreach ( $scripts as $index => $script ) {
			if ( $script['external'] && isset( $requested[ $script['handle'] ] ) && isset( $script['attrs']['defer'] ) ) { $first = $index; break; }
		}
		if ( null === $first ) return '';
		foreach ( array_slice( $scripts, $first + 1 ) as $script ) {
			if ( ! $script['executable'] ) continue;
			if ( ! $script['external'] ) {
				// wp_localize_script y traducciones sólo declaran datos.
				if ( preg_match( '/-js-(?:extra|translations)$/', $script['id'] ) || 'digitalisimo-js-guard' === $script['id'] ) continue;
				if ( self::inline_uses_jquery( $script['content'] ) ) self::$snippet = trim( substr( preg_replace( '/\s+/', ' ', $script['content'] ), 0, 400 ) );
				if ( self::inline_uses_jquery( $script['content'] ) ) return 'Código inline que usa jQuery' . ( '' !== $script['id'] ? ' (' . $script['id'] . ')' : '' ) . ' se ejecutaría antes que jQuery.';
				continue;
			}
			if ( $script['deferred'] || 'module' === strtolower( (string) ( $script['attrs']['type'] ?? '' ) ) ) continue;
			if ( '' === $script['handle'] || ! isset( $registry->registered[ $script['handle'] ] ) ) return 'Script sin registrar en WordPress, cargado de forma bloqueante después de jQuery: ' . self::short_src( $script['attrs']['src'] ?? '' ) . '.';
			if ( self::depends_on_jquery( $registry, $script['handle'] ) ) return 'El script «' . $script['handle'] . '» depende de jQuery y WordPress lo dejó bloqueante.';
		}
		return '';
	}

	private static function short_src( $src ) {
		$path = (string) parse_url( (string) $src, PHP_URL_PATH );
		return '' !== $path ? basename( $path ) : (string) $src;
	}

	/** Deshace la conversión de inline «before» cuando la página vuelve a carga normal. */
	private static function restore_inline( $html, $scripts, $converted ) {
		foreach ( $scripts as $script ) {
			if ( ! in_array( $script['id'], $converted, true ) || 0 !== strpos( (string) ( $script['attrs']['src'] ?? '' ), 'data:text/javascript;base64,' ) ) continue;
			$code = base64_decode( substr( $script['attrs']['src'], strlen( 'data:text/javascript;base64,' ) ), true );
			if ( false !== $code ) $html = str_replace( $script['tag'], '<script id="' . esc_attr( $script['id'] ) . '">' . $code . '</script>', $html );
		}
		return $html;
	}

	/** Vuelve a la carga normal sólo en lo que pidió este experimento. */
	public static function strip_defer( $html, $scripts, $requested ) {
		foreach ( $scripts as $script ) {
			if ( ! $script['external'] || ! isset( $requested[ $script['handle'] ] ) || ! isset( $script['attrs']['defer'] ) ) continue;
			$open    = substr( $script['tag'], 0, strpos( $script['tag'], '>' ) + 1 );
			$cleaned = preg_replace( array( '/\sdata-wp-strategy\s*=\s*(["\'])defer\1/i', '/\sdefer(?:\s*=\s*(["\'])(?:defer)?\1)?(?=[\s>\/])/i' ), '', $open );
			$html    = str_replace( $open, $cleaned, $html );
		}
		return $html;
	}

	/**
	 * ¿La política de seguridad del sitio admite scripts con src data:? Sin CSP,
	 * o con una CSP que no restringe scripts, sí. Se miran la cabecera y la
	 * etiqueta <meta>; una política sólo de informe no bloquea.
	 */
	public static function csp_allows_data_scripts( $html, $headers = null ) {
		$policies = array();
		foreach ( null === $headers ? ( function_exists( 'headers_list' ) ? headers_list() : array() ) : $headers as $header ) {
			if ( preg_match( '/^content-security-policy\s*:\s*(.+)$/i', (string) $header, $m ) ) $policies[] = $m[1];
		}
		if ( preg_match_all( '/<meta[^>]+http-equiv\s*=\s*["\']content-security-policy["\'][^>]*content\s*=\s*["\']([^"\']*)["\']/i', (string) $html, $found ) ) $policies = array_merge( $policies, $found[1] );
		foreach ( $policies as $policy ) {
			$directives = array();
			foreach ( explode( ';', html_entity_decode( $policy, ENT_QUOTES, 'UTF-8' ) ) as $directive ) {
				$parts = preg_split( '/\s+/', trim( $directive ) );
				if ( $parts && '' !== $parts[0] ) $directives[ strtolower( array_shift( $parts ) ) ] = array_map( 'strtolower', $parts );
			}
			foreach ( array( 'script-src-elem', 'script-src', 'default-src' ) as $name ) {
				if ( ! isset( $directives[ $name ] ) ) continue;
				if ( ! in_array( 'data:', $directives[ $name ], true ) ) return false;
				break;
			}
		}
		return true;
	}

	/**
	 * Un inline «before» que WordPress adjunta a un script diferido de la
	 * cadena se ejecutaría al leerse, antes que jQuery. Se convierte en un
	 * script diferido en la misma posición: los diferidos corren en orden de
	 * documento, así que se ejecuta después de jQuery y justo antes de su script,
	 * como hasta ahora. Sólo si la CSP del sitio admite src data:.
	 *
	 * @return array{0:string,1:array} HTML y lista de inline convertidos.
	 */
	public static function defer_before_inline( $html, $scripts, $requested ) {
		$converted = array();
		if ( ! self::csp_allows_data_scripts( $html ) ) return array( $html, $converted );
		$deferred = array();
		foreach ( $scripts as $script ) if ( $script['external'] && isset( $requested[ $script['handle'] ] ) && isset( $script['attrs']['defer'] ) ) $deferred[ $script['handle'] ] = true;
		foreach ( $scripts as $script ) {
			if ( $script['external'] || ! $script['executable'] || ! preg_match( '/^(.+)-js-before$/', $script['id'], $m ) || empty( $deferred[ $m[1] ] ) ) continue;
			$replacement = '<script id="' . esc_attr( $script['id'] ) . '" src="data:text/javascript;base64,' . base64_encode( $script['content'] ) . '" defer></script>';
			$position    = strpos( $html, $script['tag'] );
			if ( false === $position ) continue;
			$html        = substr_replace( $html, $replacement, $position, strlen( $script['tag'] ) );
			$converted[] = $script['id'];
		}
		return array( $html, $converted );
	}

	public static function process( $html, $registry, $requested ) {
		$scripts = self::scripts_in( $html );
		self::$snippet = '';
		list( $html, $converted ) = self::defer_before_inline( $html, $scripts, $requested );
		if ( $converted ) $scripts = self::scripts_in( $html );
		$reason  = self::unsafe_reason( $scripts, $registry, $requested );
		if ( '' !== $reason ) {
			$html    = self::strip_defer( self::restore_inline( $html, $scripts, $converted ), $scripts, $requested );
			$scripts = self::scripts_in( $html );
			$converted = array();
		}
		self::$report = self::report( $scripts, $registry, $requested, $reason );
		self::$report['converted'] = $converted;
		return $html;
	}

	/* ------------------------------------------------------------------ *
	 * Debug
	 * ------------------------------------------------------------------ */

	/** Estrategia efectiva de cada script de la cadena, con el motivo cuando no se difiere. */
	public static function report( $scripts, $registry, $requested, $page_reason ) {
		$printed = array();
		foreach ( $scripts as $script ) if ( $script['external'] && '' !== $script['handle'] ) $printed[ $script['handle'] ] = $script;
		$rows = array();
		foreach ( array_keys( $printed ) as $handle ) {
			if ( ! isset( $registry->registered[ $handle ] ) || ! self::depends_on_jquery( $registry, $handle ) ) continue;
			$attrs     = $printed[ $handle ]['attrs'];
			$effective = isset( $attrs['defer'] ) ? 'defer' : ( isset( $attrs['async'] ) ? 'async' : 'normal' );
			$own       = isset( $requested[ $handle ] ) ? 'defer' : ( (string) $registry->get_data( $handle, 'strategy' ) ?: 'normal' );
			$jquery    = array_values( array_filter( (array) $registry->registered[ $handle ]->deps, function( $dependency ) use ( $registry ) { return self::depends_on_jquery( $registry, $dependency ); } ) );
			$reason    = '';
			if ( 'defer' !== $effective && isset( $requested[ $handle ] ) ) {
				if ( '' !== $page_reason ) $reason = $page_reason;
				elseif ( $registry->get_data( $handle, 'after' ) ) $reason = 'Tiene código inline «after»: WordPress no lo difiere.';
				else {
					$blocking = array();
					foreach ( $printed as $other => $script ) if ( in_array( $handle, self::all_dependencies( $registry, $other ), true ) && ! isset( $script['attrs']['defer'] ) && ! isset( $script['attrs']['async'] ) ) $blocking[] = $other;
					$reason = $blocking ? 'Un dependiente sigue bloqueante: ' . implode( ', ', array_slice( $blocking, 0, 3 ) ) . '.' : 'WordPress no lo consideró elegible.';
				}
			}
			$rows[] = array( 'handle' => $handle, 'dependency' => $jquery ? implode( ', ', $jquery ) : '—', 'requested' => $own, 'effective' => $effective, 'status' => 'defer' === $effective ? 'SEGURO PARA DEFER' : ( isset( $requested[ $handle ] ) ? 'NO DIFERIDO' : 'SIN CAMBIOS' ), 'reason' => $reason );
		}
		return array( 'page' => '' === $page_reason ? 'Cadena diferida' : 'Carga normal en esta página', 'reason' => $page_reason, 'snippet' => '' !== $page_reason ? self::$snippet : '', 'rows' => $rows );
	}

	private static function all_dependencies( $registry, $handle, $seen = array() ) {
		if ( isset( $seen[ $handle ] ) || ! isset( $registry->registered[ $handle ] ) ) return array();
		$seen[ $handle ] = true;
		$all = array();
		foreach ( (array) $registry->registered[ $handle ]->deps as $dependency ) {
			$all[] = $dependency;
			if ( 'jquery' === $dependency ) array_push( $all, 'jquery-core', 'jquery-migrate' );
			$all = array_merge( $all, self::all_dependencies( $registry, $dependency, $seen ) );
		}
		return array_values( array_unique( $all ) );
	}

	/* ------------------------------------------------------------------ *
	 * Red de seguridad en el navegador
	 * ------------------------------------------------------------------ */

	/**
	 * Un error «jQuery is not defined» que la revisión del servidor no pudo
	 * prever (por ejemplo, un script inyectado por JavaScript) apaga el
	 * experimento en este sitio. Se imprime antes que cualquier otro script.
	 */
	public static function error_guard() {
		if ( ! self::active_request() ) return;
		$endpoint = admin_url( 'admin-ajax.php' );
		echo '<script id="digitalisimo-js-guard">(function(){var s=0;function h(e){var m=String((e&&(e.message||(e.reason&&e.reason.message)))||"");if(s||!/(jQuery|\\$) is not defined|Can\'t find variable: (jQuery|\\$)|(jQuery|\\$) is not a function/.test(m))return;s=1;try{var d=new FormData();d.append("action","' . esc_js( self::AJAX ) . '");d.append("message",m.slice(0,200));d.append("url",location.href.slice(0,300));navigator.sendBeacon(' . wp_json_encode( $endpoint ) . ',d);}catch(x){}}window.addEventListener("error",h);window.addEventListener("unhandledrejection",h);})();</script>' . "\n";
	}

	/** Mensaje de error que justifica volver a la carga normal. */
	public static function is_dependency_error( $message ) {
		return (bool) preg_match( '/(jQuery|\$) is not defined|Can\'t find variable: (jQuery|\$)|(jQuery|\$) is not a function/', (string) $message );
	}

	/**
	 * Lo llama el navegador de un visitante: sin sesión ni nonce. Lo único que
	 * puede hacer es apagar un experimento, una sola vez, y sólo con un mensaje
	 * de dependencia válido.
	 */
	public static function record_failure() {
		$message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( ! self::enabled() || ! self::is_dependency_error( $message ) ) wp_die( '', '', array( 'response' => 204 ) );
		update_option( self::FALLBACK, array( 'at' => current_time( 'mysql' ), 'message' => substr( $message, 0, 200 ), 'url' => esc_url_raw( substr( (string) wp_unslash( $_POST['url'] ?? '' ), 0, 300 ) ) ), false );
		wp_die( '', '', array( 'response' => 204 ) );
	}

	public static function reset_action() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		check_admin_referer( 'digitalisimo_performance_js_reset_' . $site_id );
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try { delete_option( self::FALLBACK ); } finally { if ( $switched ) restore_current_blog(); }
		wp_safe_redirect( $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=javascript&site_id=' . $site_id ) : admin_url( 'admin.php?page=digitalisimo-performance&section=javascript' ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Rendimiento → JavaScript
	 * ------------------------------------------------------------------ */

	public static function render( $network ) {
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$mode     = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_js_jquery_mode' ) );
			$fallback = self::fallback();
			echo '<h2>Estado en ' . esc_html( home_url( '/' ) ) . '</h2><table class="widefat striped" style="max-width:760px"><tbody>';
			echo '<tr><th>Modo elegido</th><td>' . esc_html( 'defer' === $mode ? 'Defer seguro experimental' : 'Desactivada' ) . '</td></tr>';
			echo '<tr><th>Soporte de WordPress</th><td>' . esc_html( self::supported() ? 'Sí (estrategias de carga nativas, 6.3+)' : 'No: requiere WordPress 6.3 o superior' ) . '</td></tr>';
			echo '<tr><th>Estado efectivo</th><td>' . esc_html( $fallback ? 'Carga normal por error detectado en el navegador' : ( self::enabled() ? 'Activo para visitantes' : 'Inactivo' ) ) . '</td></tr>';
			echo '</tbody></table>';
			if ( $fallback ) {
				echo '<div class="notice notice-warning inline"><p>Un visitante registró <code>' . esc_html( $fallback['message'] ) . '</code> en <code>' . esc_html( $fallback['url'] ) . '</code> (' . esc_html( $fallback['at'] ) . '). El sitio volvió a la carga normal de jQuery. Corrige la causa y reactiva el experimento.</p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="digitalisimo_performance_js_reset"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . esc_attr( $site_id ) . '">';
				wp_nonce_field( 'digitalisimo_performance_js_reset_' . $site_id );
				submit_button( 'Reactivar experimento', 'secondary', 'submit', false );
				echo '</form></div>';
			}
		} finally { if ( $switched ) restore_current_blog(); }

		$captured = get_transient( 'digitalisimo_perf_result_' . get_current_user_id() );
		if ( ! Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( $captured, $site_id ) ) $captured = array();
		$report = (array) ( $captured['js_report'] ?? array() );
		echo '<h2>Debug de la cadena jQuery</h2>';
		if ( empty( $captured['url'] ) || empty( $report['rows'] ) ) {
			echo '<p>Sin captura con el experimento activo. Actívalo, guarda y analiza una URL en «Diagnóstico de assets»: aquí aparecerá la estrategia efectiva de cada script.</p>';
		} else {
			echo '<p>Página: <a href="' . esc_url( $captured['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $captured['url'] ) . '</a> · <strong>' . esc_html( $report['page'] ) . '</strong>' . ( ! empty( $report['reason'] ) ? ' — ' . esc_html( $report['reason'] ) : '' ) . '</p>';
			if ( ! empty( $report['converted'] ) ) echo '<p>Inline «before» de WordPress ejecutado en su orden con defer: <code>' . esc_html( implode( ', ', $report['converted'] ) ) . '</code>.</p>';
			if ( ! empty( $report['snippet'] ) ) echo '<p>Código que lo impide (primeros 400 caracteres):</p><pre style="white-space:pre-wrap;max-width:900px">' . esc_html( $report['snippet'] ) . '</pre>';
			echo '<table class="widefat striped"><thead><tr><th>Handle</th><th>Dependencia jQuery</th><th>Estrategia solicitada</th><th>Estrategia efectiva</th><th>Estado</th></tr></thead><tbody>';
			foreach ( $report['rows'] as $row ) echo '<tr><td><code>' . esc_html( $row['handle'] ) . '</code></td><td>' . esc_html( $row['dependency'] ) . '</td><td>' . esc_html( $row['requested'] ) . '</td><td>' . esc_html( $row['effective'] ) . '</td><td>' . esc_html( $row['status'] ) . ( $row['reason'] ? '<br><span class="description">' . esc_html( $row['reason'] ) . '</span>' : '' ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '<h2>Validación antes de dejarlo activo</h2><ol><li>Consola del navegador sin errores en portada, blog y páginas con formularios.</li><li>Menú móvil, sticky, formularios, pestañas, acordeones, contadores, carruseles, lightbox y Call To Action.</li><li>WooCommerce: carrito y checkout, si está activo.</li><li>Varias corridas de Lighthouse móvil antes y después: FCP, LCP, Element render delay y TBT, registradas en «PageSpeed manual».</li></ol>';
	}
}
