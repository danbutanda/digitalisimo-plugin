<?php
/**
 * Registro del servidor para la auditoría de marca (sólo prueba; lo instala setup.php como mu-plugin).
 *
 * Anota en http.jsonl, junto a este archivo, cada petición del servidor hacia un plugin de origen (vendors.json), con
 * su contexto (pantalla de la cookie digi_screen, AJAX, REST, cron) y su llamador:
 * - «salió»: ninguna guarda la detuvo; la prueba la corta aquí con WP_Error para no depender de internet. Falla.
 * - «bloqueada»: otra guarda ya devolvió una respuesta (prioridad PHP_INT_MAX: se ve lo que dejó pasar el producto).
 * - «permitida»: WordPress consulta wordpress.org por el plugin original instalado (actualizaciones y traducciones).
 * - «redirección» (wp_redirect o cabecera Location), «correo» (pre_wp_mail), «host permitido» (allowed_redirect_hosts)
 *   y «respuesta» (URL del proveedor en el cuerpo de una respuesta AJAX o REST: contenido latente).
 */
if ( defined( 'DIGI_HTTP_PROBE' ) ) {
	return;
}
define( 'DIGI_HTTP_PROBE', __DIR__ );

function digi_probe_vendors() {
	static $v = null;
	if ( null === $v ) {
		$v = json_decode( (string) @file_get_contents( __DIR__ . '/vendors.json' ), true );
		$v = is_array( $v ) ? $v : array();
	}
	return $v;
}

/** Misma clasificación que vendors.cjs. En el servidor un host de vídeo sólo cuenta con un vídeo del proveedor. */
function digi_probe_classify( $url, $caller = '' ) {
	$v = digi_probe_vendors();
	$s = str_replace( array( '\\/', '&amp;' ), array( '/', '&' ), trim( (string) $url ) );
	if ( 0 === strpos( $s, '//' ) ) {
		$s = 'https:' . $s;
	}
	$p = parse_url( $s );
	if ( ! is_array( $p ) || empty( $p['host'] ) || ! preg_match( '/^(https?|wss?)$/i', $p['scheme'] ?? '' ) ) {
		return '';
	}
	$host  = rtrim( strtolower( $p['host'] ), '.' );
	$under = static function ( $h ) use ( $host ) {
		return $host === $h || substr( $host, -strlen( $h ) - 1 ) === '.' . $h;
	};
	foreach ( $v['hosts'] ?? array() as $h ) {
		if ( $under( $h ) ) {
			return 'dominio del proveedor';
		}
	}
	foreach ( $v['future'] ?? array() as $h ) {
		if ( $under( $h ) ) {
			return 'dominio de un constructor de origen futuro';
		}
	}
	foreach ( $v['callerHosts'] ?? array() as $h => $rule ) {
		if ( $under( $h ) && ( '' === $caller || preg_match( "\x01" . $rule['caller'] . "\x01", $caller ) ) ) {
			return $rule['motivo'];
		}
	}
	$pq = rawurldecode( ( $p['path'] ?? '' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' ) );
	foreach ( $v['paths'] ?? array() as $rule ) {
		if ( preg_match( "\x01" . $rule['host'] . "\x01i", $host ) && preg_match( "\x01" . $rule['re'] . "\x01i", $pq ) ) {
			return $rule['motivo'];
		}
	}
	if ( ! empty( $v['videoHosts'] ) && preg_match( "\x01" . $v['videoHosts'] . "\x01i", $host ) ) {
		foreach ( $v['vendorVideos'] ?? array() as $id ) {
			if ( false !== strpos( $s, $id ) ) {
				return 'vídeo del proveedor (' . $id . ')';
			}
		}
	}
	return '';
}

/** Primer archivo de un plugin en la pila (el llamador) y primer archivo de WordPress fuera de la API HTTP. */
function digi_probe_trace() {
	$plugin = '';
	$core   = '';
	$skip   = '#/wp-includes/(class-wp-http|http|class-wp-hook|plugin|pluggable|class-wp-http-requests-hooks)\.php$|/wp-includes/Requests/#';
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $f ) {
		$file = $f['file'] ?? '';
		if ( '' === $file || __FILE__ === $file || false !== strpos( $file, '/wp-content/probe/' ) || preg_match( '#/mu-plugins/digi-[a-z-]+\.php$#', $file ) ) {
			continue;
		}
		if ( '' === $plugin && preg_match( '#/wp-content/(?:mu-)?plugins/(.+)$#', $file, $m ) ) {
			$plugin = $m[1] . ':' . ( $f['line'] ?? 0 );
		}
		if ( '' === $core && ! preg_match( $skip, $file ) && false === strpos( $file, '/wp-content/' ) ) {
			$core = preg_replace( '#^.*?/(wp-(?:includes|admin)/)#', '$1', $file ) . ':' . ( $f['line'] ?? 0 );
		}
	}
	return array( $plugin, $core );
}

function digi_probe_context() {
	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return 'cron:' . ( $GLOBALS['digi_cron_hook'] ?? '' );
	}
	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		$a = preg_replace( '/[^a-z0-9_\-]/i', '', (string) ( $_REQUEST['action'] ?? '' ) );
		if ( 'elementor_ajax' === $a && ! empty( $_REQUEST['actions'] ) ) {
			$list = json_decode( wp_unslash( (string) $_REQUEST['actions'] ), true );
			$a   .= '[' . implode( ',', array_unique( array_filter( array_map( static function ( $x ) {
				return is_array( $x ) ? (string) ( $x['action'] ?? '' ) : '';
			}, (array) $list ) ) ) ) . ']';
		}
		return 'ajax:' . $a;
	}
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( defined( 'REST_REQUEST' ) || false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' ) ) {
		return 'rest:' . $uri;
	}
	if ( defined( 'WP_CLI' ) || 'cli' === PHP_SAPI && '' === $uri ) {
		return 'preparación';
	}
	return ( is_admin() ? 'admin:' : 'front:' ) . $uri;
}

function digi_probe_log( $kind, $url, $why, $extra = array() ) {
	static $seen = array();
	list( $plugin, $core ) = digi_probe_trace();
	$key = $kind . '|' . $url . '|' . $plugin . '|' . digi_probe_context();
	if ( isset( $seen[ $key ] ) ) {
		return;
	}
	$seen[ $key ] = true;
	$line = array(
		'kind'    => $kind,
		'url'     => substr( (string) $url, 0, 500 ),
		'why'     => $why,
		'context' => substr( digi_probe_context(), 0, 300 ),
		'caller'  => $plugin,
		'core'    => $core,
		'screen'  => rawurldecode( (string) ( $_COOKIE['digi_screen'] ?? '' ) ),
		'locale'  => (string) ( $_COOKIE['digi_locale'] ?? '' ),
	) + $extra;
	file_put_contents( __DIR__ . '/http.jsonl', json_encode( $line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND | LOCK_EX );
}

function digi_probe_urls( $text ) {
	preg_match_all( '#(?:https?:)?(?:\\\\?/){2}[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}[^\s"\'<>()\\\\]*(?:\\\\/[^\s"\'<>()\\\\]*)*#i', (string) $text, $m );
	return array_unique( $m[0] );
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		list( $plugin, $core ) = digi_probe_trace();
		$why = digi_probe_classify( $url, $plugin );
		if ( '' === $why ) {
			return $pre;
		}
		$method = is_array( $args ) ? ( $args['method'] ?? 'GET' ) : 'GET';
		// WordPress pide a wordpress.org actualizaciones y traducciones de los plugins instalados (el original incluido).
		if ( '' === $plugin && preg_match( '#^wp-(includes/update\.php|admin/includes/(class-language-pack-upgrader|class-wp-upgrader|class-plugin-upgrader|update|translation-install|plugin-install|plugin)\.php)#', $core ) && preg_match( '#(^|\.)(wordpress|w)\.org$#i', (string) parse_url( $url, PHP_URL_HOST ) ) ) {
			digi_probe_log( 'permitida', $url, 'WordPress consulta el plugin original instalado', array( 'method' => $method ) );
			return $pre;
		}
		digi_probe_log( false === $pre ? 'salió' : 'bloqueada', $url, $why, array( 'method' => $method ) );
		return false === $pre ? new WP_Error( 'digi_probe', 'Auditoría de marca: petición a un plugin de origen' ) : $pre;
	},
	PHP_INT_MAX,
	3
);

add_filter(
	'wp_redirect',
	static function ( $location ) {
		$why = digi_probe_classify( (string) $location );
		if ( '' !== $why ) {
			$GLOBALS['digi_probe_redirected'][ (string) $location ] = true;
			digi_probe_log( 'redirección', (string) $location, $why );
		}
		return $location;
	},
	PHP_INT_MAX
);

add_filter(
	'allowed_redirect_hosts',
	static function ( $hosts ) {
		foreach ( (array) $hosts as $h ) {
			$why = digi_probe_classify( 'https://' . $h . '/' );
			if ( '' !== $why ) {
				digi_probe_log( 'host permitido', (string) $h, $why );
			}
		}
		return $hosts;
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		$text = (string) ( $atts['subject'] ?? '' ) . "\n" . ( is_array( $atts['message'] ?? '' ) ? implode( "\n", $atts['message'] ) : (string) ( $atts['message'] ?? '' ) ) . "\n" . ( is_array( $atts['headers'] ?? '' ) ? implode( "\n", $atts['headers'] ) : (string) ( $atts['headers'] ?? '' ) );
		foreach ( digi_probe_urls( $text ) as $u ) {
			$why = digi_probe_classify( $u );
			if ( '' !== $why ) {
				digi_probe_log( 'correo', $u, $why, array( 'subject' => substr( (string) ( $atts['subject'] ?? '' ), 0, 200 ) ) );
			}
		}
		return $short_circuit;
	},
	PHP_INT_MAX,
	2
);

// Redirecciones que no pasan por wp_redirect (header() directo) y URL del proveedor en respuestas AJAX o REST.
$digi_probe_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
if ( ! preg_match( '#/wp-content/probe/#', $digi_probe_uri ) ) {
	ob_start(
		static function ( $buffer ) {
			if ( function_exists( 'headers_list' ) ) {
				foreach ( headers_list() as $h ) {
					if ( preg_match( '/^location:\s*(.+)$/i', $h, $m ) && empty( $GLOBALS['digi_probe_redirected'][ trim( $m[1] ) ] ) ) {
						$why = digi_probe_classify( trim( $m[1] ) );
						if ( '' !== $why ) {
							digi_probe_log( 'redirección', trim( $m[1] ), $why, array( 'via' => 'cabecera Location' ) );
						}
					}
				}
			}
			$context = digi_probe_context();
			if ( preg_match( '/^(ajax|rest):/', $context ) && '' !== $buffer ) {
				foreach ( digi_probe_urls( $buffer ) as $u ) {
					$why = digi_probe_classify( $u );
					if ( '' !== $why ) {
						digi_probe_log( 'respuesta', str_replace( '\\/', '/', $u ), $why );
					}
				}
			}
			return $buffer;
		}
	);
}
