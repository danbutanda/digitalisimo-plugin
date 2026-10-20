<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Revisión y prueba previa de los fragmentos (§4.4, §4.5).
 *
 * - check(): revisión sin ejecutar (normalización ya hecha por Store::normalize_code()). Bloquea por tamaño, UTF-8, falta de
 *   `<?php` y error de sintaxis (`token_get_all( …, TOKEN_PARSE )`, que no ejecuta nada); avisa, sin bloquear, de lo que
 *   cambia respecto de `functions.php`.
 * - start(): crea la sesión de prueba y un token de un solo uso por destino, los dos firmados (Store::session_signature()).
 *   La prueba la lanza el navegador del administrador, no el servidor: no hay loopback ni esperas largas en una petición.
 * - claim(): en la petición de prueba (Runner::run), valida el token, define WP_SANDBOX_SCRAPING y DONOTCACHEPAGE y devuelve
 *   los candidatos; Guard reúne las señales y escribe el resultado al cerrar.
 * - evaluate() y judge(): aplican el criterio a los resultados; una redirección al mismo host se reintenta una sola vez.
 *
 * Transitorios (en el sitio, o de red en las sesiones de red): `test_<sha256(token)>` (120 s, un solo uso),
 * `session_<sha256(sid)>` (300 s) y `result_<sha256(token)>` (300 s, lo escribe Guard).
 */
final class Tester {
	const PARAM       = 'digitalisimo-php-test';
	const TOKEN_TTL   = 120;
	const SESSION_TTL = 300;
	const SAMPLE      = 4;
	const SAMPLE_POOL = 100;
	const MODES       = array( 'activate', 'resume_site', 'resume_all' );

	/** Superglobales y variables que no cuentan como «variables de nivel superior». */
	const SUPERGLOBALS = array( '$GLOBALS', '$this', '$_GET', '$_POST', '$_COOKIE', '$_REQUEST', '$_SERVER', '$_FILES', '$_ENV', '$_SESSION' );
	/** Funciones que interfieren con el guardián. */
	const HANDLERS = array( 'set_error_handler', 'set_exception_handler', 'register_shutdown_function', 'restore_error_handler', 'restore_exception_handler' );
	/** Ganchos de permisos o de acceso: una restricción escrita en un fragmento no protege. */
	const ACCESS_HOOKS = array( 'user_has_cap', 'map_meta_cap', 'file_mod_allowed', 'authenticate', 'wp_authenticate_user', 'editable_roles' );
	/** El patrón de SEO → Rendimiento (digitalisimo-seo/includes/performance/class-performance-migration.php). */
	const TRACKING = '/\b(?:GTM|GT|G)-[A-Z0-9]+\b/i';

	/* ------------------------------------------------------------------ *
	 * Revisión sin ejecutar (§4.5)
	 * ------------------------------------------------------------------ */

	/**
	 * Revisa el código ya normalizado. Nunca lo ejecuta.
	 *
	 * @param string    $code       Código normalizado.
	 * @param bool|null $tokenizer  null: según exista la extensión tokenizer (las pruebas pasan false).
	 * @return array { hash, php, ok, line, message, warnings[] }
	 */
	public static function check( $code, $tokenizer = null ) {
		$code = (string) $code;
		$out  = array( 'hash' => hash( 'sha256', $code ), 'php' => PHP_VERSION, 'ok' => false, 'line' => 0, 'message' => '', 'warnings' => array() );
		if ( strlen( $code ) > Store::MAX_CODE ) {
			$out['message'] = 'No se activó: el fragmento supera 64 KB.';
			return $out;
		}
		if ( '' !== $code && 1 !== preg_match( '//u', $code ) ) {
			$out['message'] = 'No se activó: el código no es UTF-8 válido. Vuelve a pegarlo desde un editor de texto.';
			return $out;
		}
		if ( 1 !== preg_match( '/^<\?php\s/', $code ) ) {
			$out['line']    = 1;
			$out['message'] = 'No se activó: el código debe empezar exactamente por <?php y un salto de línea.';
			return $out;
		}
		$tokenizer = null === $tokenizer ? ( function_exists( 'token_get_all' ) && defined( 'TOKEN_PARSE' ) ) : (bool) $tokenizer;
		if ( $tokenizer ) {
			try {
				$tokens = token_get_all( $code, TOKEN_PARSE );
			} catch ( \CompileError $e ) {
				$out['line']    = (int) $e->getLine();
				$out['message'] = sprintf( 'No se activó: error de sintaxis en la línea %d: %s.', $out['line'], rtrim( Store::one_line( $e->getMessage(), 300 ), '.' ) );
				return $out;
			}
			$out['warnings'] = self::warnings( $tokens );
		}
		// Sin tokenizer, la sintaxis la detecta la prueba (un error de compilación al cargarse).
		$out['ok'] = true;
		return $out;
	}

	/** Avisos de la revisión (§4.5, «Avisa, sin bloquear»), a partir de los tokens. */
	private static function warnings( array $tokens ) {
		$n        = count( $tokens );
		$stack    = array();   // 'func', 'class' u 'other' por cada llave abierta.
		$pending  = null;      // 'func' o 'class' entre la cabecera y su llave.
		$scoped   = 0;         // Llaves de función o clase abiertas.
		$vars     = array();
		$globals  = array();
		$arrow    = array();
		$paths    = false;
		$handlers = array();
		$exit     = false;
		$buffers  = 0;
		$tracking = array();
		$access   = false;
		$t_fn     = defined( 'T_FN' ) ? T_FN : -1;
		$t_enum   = defined( 'T_ENUM' ) ? T_ENUM : -1;
		$t_fqn    = defined( 'T_NAME_FULLY_QUALIFIED' ) ? T_NAME_FULLY_QUALIFIED : -1;
		$t_nullsafe = defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ? T_NULLSAFE_OBJECT_OPERATOR : -1;
		for ( $i = 0; $i < $n; $i++ ) {
			$t    = $tokens[ $i ];
			$id   = is_array( $t ) ? $t[0] : null;
			$text = is_array( $t ) ? $t[1] : $t;
			$top  = 0 === $scoped && null === $pending;
			if ( null === $id ) {
				if ( '{' === $text ) {
					$stack[] = $pending ?: 'other';
					if ( $pending ) $scoped++;
					$pending = null;
				} elseif ( '}' === $text ) {
					$frame = array_pop( $stack );
					if ( 'func' === $frame || 'class' === $frame ) $scoped = max( 0, $scoped - 1 );
				} elseif ( ';' === $text && 'func' === $pending ) {
					$pending = null; // Método abstracto o de interfaz.
				}
				continue;
			}
			switch ( $id ) {
				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
					$stack[] = 'other';
					break;
				case T_FUNCTION:
					// Los parámetros son del cuerpo; las variables de `use ( … )` se leen del ámbito exterior.
					$i = self::skip_params( $tokens, $i );
					$j = self::next( $tokens, $i );
					if ( null !== $j && is_array( $tokens[ $j ] ) && T_USE === $tokens[ $j ][0] ) {
						$end = self::skip_params( $tokens, $j );
						if ( $top ) for ( $k = $j; $k <= $end; $k++ ) if ( is_array( $tokens[ $k ] ) && T_VARIABLE === $tokens[ $k ][0] ) $vars[ $tokens[ $k ][1] ] = true;
						$i = $end;
					}
					$pending = 'func';
					break;
				case $t_fn:
					// Función flecha: su cuerpo lee las variables del ámbito exterior, salvo sus parámetros.
					$end = self::skip_params( $tokens, $i );
					for ( $k = $i; $k <= $end; $k++ ) if ( is_array( $tokens[ $k ] ) && T_VARIABLE === $tokens[ $k ][0] ) $arrow[ $tokens[ $k ][1] ] = true;
					$i = $end;
					break;
				case T_CLASS:
					$p = self::prev( $tokens, $i );
					if ( null !== $p && is_array( $tokens[ $p ] ) && T_DOUBLE_COLON === $tokens[ $p ][0] ) break; // Foo::class
					$pending = 'class';
					break;
				case T_INTERFACE:
				case T_TRAIT:
				case $t_enum:
					$pending = 'class';
					break;
				case T_VARIABLE:
					if ( $top && ! in_array( $text, self::SUPERGLOBALS, true ) ) $vars[ $text ] = true;
					break;
				case T_GLOBAL:
					if ( ! $top ) break;
					for ( $k = $i + 1; $k < $n && ';' !== $tokens[ $k ]; $k++ ) if ( is_array( $tokens[ $k ] ) && T_VARIABLE === $tokens[ $k ][0] ) $globals[ $tokens[ $k ][1] ] = true;
					$i = $k;
					break;
				case T_FILE:
				case T_DIR:
					$paths = true;
					break;
				case T_INCLUDE:
				case T_INCLUDE_ONCE:
				case T_REQUIRE:
				case T_REQUIRE_ONCE:
					$j = self::next( $tokens, $i );
					if ( null !== $j && '(' === $tokens[ $j ] ) $j = self::next( $tokens, $j );
					if ( null !== $j && is_array( $tokens[ $j ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $j ][0] ) {
						$target = substr( $tokens[ $j ][1], 1, -1 );
						if ( '' === $target || ( '/' !== $target[0] && ! preg_match( '#^[A-Za-z]:[\\\\/]|^[a-z][a-z0-9+.-]*://#i', $target ) ) ) $paths = true;
					}
					break;
				case T_EXIT:
					if ( $top ) $exit = true;
					break;
				case T_STRING:
				case $t_fqn:
					$name = strtolower( ltrim( $text, '\\' ) );
					$j    = self::next( $tokens, $i );
					$call = null !== $j && '(' === $tokens[ $j ];
					$p    = self::prev( $tokens, $i );
					$member = null !== $p && is_array( $tokens[ $p ] ) && in_array( $tokens[ $p ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, $t_nullsafe ), true );
					if ( ! $call || $member ) break;
					if ( in_array( $name, self::HANDLERS, true ) ) $handlers[ $name ] = true;
					if ( $top && 'ob_start' === $name ) $buffers++;
					if ( $top && in_array( $name, array( 'ob_end_clean', 'ob_end_flush', 'ob_get_clean', 'ob_get_flush' ), true ) ) $buffers--;
					if ( 'define' === $name ) {
						$k = self::next( $tokens, $j );
						if ( null !== $k && is_array( $tokens[ $k ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $k ][0] && 0 === stripos( substr( $tokens[ $k ][1], 1, -1 ), 'DISALLOW_' ) ) $access = true;
					}
					break;
				case T_CONSTANT_ENCAPSED_STRING:
					if ( in_array( strtolower( substr( $text, 1, -1 ) ), self::ACCESS_HOOKS, true ) ) $access = true;
					if ( preg_match_all( self::TRACKING, $text, $m ) ) foreach ( $m[0] as $found ) $tracking[ strtoupper( $found ) ] = true;
					break;
				case T_ENCAPSED_AND_WHITESPACE:
				case T_INLINE_HTML:
					if ( preg_match_all( self::TRACKING, $text, $m ) ) foreach ( $m[0] as $found ) $tracking[ strtoupper( $found ) ] = true;
					break;
			}
		}
		$out = array();
		if ( $paths ) $out[] = '__FILE__, __DIR__ y los include o require con ruta relativa apuntan ahora a digitalisimo-php://, no a un archivo. Usa rutas absolutas, por ejemplo get_stylesheet_directory() . \'/inc/archivo.php\'.';
		$vars = array_keys( array_diff_key( $vars, $globals, $arrow ) );
		if ( $vars ) {
			$shown = array_slice( $vars, 0, 5 );
			$first = substr( $shown[0], 1 );
			$out[] = sprintf( 'Las variables fuera de una función ya no son globales: %s%s. Escribe global %s; al principio o usa $GLOBALS[\'%s\'].', implode( ', ', $shown ), count( $vars ) > 5 ? sprintf( ' y %d más', count( $vars ) - 5 ) : '', implode( ', ', $shown ), $first );
		}
		if ( $handlers ) $out[] = sprintf( '%s interfiere con el guardián de los fragmentos: un error podría no detectarse ni desactivar el fragmento.', implode( '(), ', array_keys( $handlers ) ) . '()' );
		if ( $exit ) $out[] = 'exit o die fuera de una función corta la carga de cada página. Ponlo dentro de un gancho, por ejemplo template_redirect.';
		if ( $buffers > 0 ) $out[] = 'ob_start() fuera de una función deja abierto un búfer de salida. Ciérralo en el mismo fragmento o úsalo dentro de un gancho.';
		if ( $tracking ) $out[] = sprintf( 'El código incluye etiquetas de seguimiento de Google (%s). Muévelo a SEO → Rendimiento.', implode( ', ', array_slice( array_keys( $tracking ), 0, 5 ) ) );
		if ( $access ) $out[] = 'Una restricción de permisos o de acceso escrita en un fragmento no protege: quien puede detener los fragmentos, o usa un enlace de rescate, la desactiva. Ponla en wp-config.php o en un plugin.';
		return $out;
	}

	/** Índice del siguiente token significativo (sin espacios ni comentarios), o null. */
	private static function next( array $tokens, $i ) {
		for ( $j = $i + 1, $n = count( $tokens ); $j < $n; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
			return $j;
		}
		return null;
	}

	private static function prev( array $tokens, $i ) {
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
			return $j;
		}
		return null;
	}

	/** Desde $i, salta hasta el `)` que cierra el primer `(`; devuelve su índice (o $i si no hay paréntesis antes de `{`, `;` o `=>`). */
	private static function skip_params( array $tokens, $i ) {
		$n = count( $tokens );
		for ( $j = $i + 1; $j < $n; $j++ ) {
			if ( '(' === $tokens[ $j ] ) break;
			if ( in_array( $tokens[ $j ], array( '{', ';' ), true ) ) return $i;
		}
		$depth = 0;
		for ( ; $j < $n; $j++ ) {
			if ( '(' === $tokens[ $j ] ) $depth++;
			elseif ( ')' === $tokens[ $j ] && 0 === --$depth ) return $j;
		}
		return $n - 1;
	}

	/**
	 * Si activar $hash de este fragmento exige la casilla «Revisé este código»: importado sin revisar, firma no válida, un
	 * borrador que no guardó Tools desde el editor (importado, o cambiado fuera de Tools: su firma no vale), o una versión del
	 * historial sin una firma válida («Nueva versión del origen», sin firma, alterada en la BD o activada con claves que ya no
	 * valen). Sólo una versión activada con una firma válida lo evita.
	 */
	public static function needs_review( $scope, $id, $hash ) {
		$scope = Store::scope( $scope );
		$index = Store::index( $scope );
		if ( ! isset( $index['items'][ $id ] ) ) return false;
		if ( $index['items'][ $id ]['review'] ) return true;
		if ( 'signature' === Store::state( $scope, $id, $index ) ) return true;
		$item = Store::item( $scope, $id );
		if ( ! $item ) return false;
		$valid   = false;
		$flagged = hash_equals( hash( 'sha256', $item['code'] ), (string) $hash ) && 'saved' !== Store::draft_status( $scope, $id, $item );
		foreach ( $item['history'] as $entry ) {
			if ( ! is_array( $entry ) || ( $entry['hash'] ?? '' ) !== $hash ) continue;
			if ( ! Store::history_entry_valid( $scope, $id, $entry ) ) $flagged = true;
			if ( ! empty( $entry['activated'] ) ) {
				if ( Store::history_signature_valid( $scope, $id, $entry ) ) $valid = true;
				else $flagged = true;
			}
			if ( in_array( $entry['event'] ?? '', array( 'origin', 'unsigned' ), true ) ) $flagged = true;
		}
		return $flagged && ! $valid;
	}

	/* ------------------------------------------------------------------ *
	 * Destinos y muestra de red (§4.4, «Destinos»)
	 * ------------------------------------------------------------------ */

	/**
	 * Destinos de una prueba: [ { url, kind: admin|front, blog, label } ].
	 * - Sitio y «Reanudar en este sitio»: profile.php y la portada del sitio actual.
	 * - Red y «Reanudar en todos los sitios»: profile.php de la red, la portada del principal y hasta 4 portadas más.
	 */
	public static function targets( $network ) {
		$blog = (int) get_current_blog_id();
		if ( ! $network || ! is_multisite() ) {
			return array(
				array( 'url' => admin_url( 'profile.php' ), 'kind' => 'admin', 'blog' => $blog, 'label' => 'el escritorio' ),
				array( 'url' => home_url( '/' ), 'kind' => 'front', 'blog' => $blog, 'label' => 'la portada' ),
			);
		}
		$main = (int) get_main_site_id();
		$out  = array(
			array( 'url' => network_admin_url( 'profile.php' ), 'kind' => 'admin', 'blog' => $blog, 'label' => 'el escritorio de la red' ),
			array( 'url' => get_home_url( $main, '/' ), 'kind' => 'front', 'blog' => $main, 'label' => 'la portada' ),
		);
		foreach ( self::network_sample() as $other ) {
			$url   = get_home_url( $other, '/' );
			$parts = wp_parse_url( $url );
			$out[] = array( 'url' => $url, 'kind' => 'front', 'blog' => (int) $other, 'label' => 'la portada de ' . ( $parts['host'] ?? '' ) . untrailingslashit( $parts['path'] ?? '' ) );
		}
		return $out;
	}

	/**
	 * Hasta 4 sitios activos, entre los 100 actualizados más recientemente, con una `stylesheet` distinta de la del principal
	 * y entre sí, leída con una sola consulta UNION sobre sus tablas de opciones.
	 */
	public static function network_sample() {
		global $wpdb;
		if ( ! is_multisite() ) return array();
		$main  = (int) get_main_site_id();
		// Sólo los sitios de esta red: get_sites() sin network_id devuelve también los de otras redes de la instalación.
		$blogs = array_values( array_diff( Store::network_blogs( array( 'number' => self::SAMPLE_POOL, 'orderby' => 'last_updated', 'order' => 'DESC' ) ), array( $main ) ) );
		if ( ! $blogs ) return array();
		$sheets = array();
		$select = static function ( $blog ) use ( $wpdb ) {
			return $wpdb->prepare( 'SELECT %d AS blog_id, option_name, option_value FROM `' . $wpdb->get_blog_prefix( $blog ) . 'options` WHERE option_name LIKE %s', $blog, 'stylesheet' ); // phpcs:ignore WordPress.DB
		};
		// Una sola consulta UNION; si falla porque a un sitio le falta su tabla, se repite sitio por sitio sin ése.
		foreach ( Store::union_rows( array_merge( array( $main ), $blogs ), $select ) as $row ) $sheets[ (int) $row['blog_id'] ] = (string) $row['option_value'];
		$first = $sheets[ $main ] ?? '';
		$seen  = array( $first => true );
		$out  = array();
		foreach ( $blogs as $blog ) {
			$sheet = $sheets[ $blog ] ?? '';
			if ( '' === $sheet || isset( $seen[ $sheet ] ) ) continue;
			$seen[ $sheet ] = true;
			$out[]          = $blog;
			if ( count( $out ) >= self::SAMPLE ) break;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Sesión y tokens
	 * ------------------------------------------------------------------ */

	private static function put( $store, $name, $value, $ttl ) {
		return 'network' === $store && is_multisite() ? set_site_transient( $name, $value, $ttl ) : set_transient( $name, $value, $ttl );
	}

	private static function read( $store, $name ) {
		return 'network' === $store && is_multisite() ? get_site_transient( $name ) : get_transient( $name );
	}

	private static function drop( $store, $name ) {
		return 'network' === $store && is_multisite() ? delete_site_transient( $name ) : delete_transient( $name );
	}

	/** Firma de un token: liga su hash, destino, almacén, red, sitio, candidatos, usuario y caducidad. */
	private static function token_signature( $th, array $t ) {
		return Store::session_signature( array( 'token' => $th, 'kind' => $t['kind'], 'scope' => $t['store'], 'network' => $t['network'], 'blog' => $t['blog'], 'candidates' => $t['candidates'], 'user' => $t['user'], 'exp' => $t['exp'] ) );
	}

	/** Firma de la sesión: la del token con `session:<modo>` como destino, el `sid` como token y el ámbito y el id de la petición. */
	private static function session_signature( $sh, array $s ) {
		return Store::session_signature( array( 'token' => $sh, 'kind' => 'session:' . $s['mode'] . ':' . $s['scope'] . ':' . $s['id'], 'scope' => $s['store'], 'network' => $s['network'], 'blog' => $s['blog'], 'candidates' => $s['candidates'], 'user' => $s['user'], 'exp' => $s['exp'] ) );
	}

	/** Crea un token para $target y devuelve la URL de prueba. */
	private static function issue( array &$session, array $target ) {
		$token = bin2hex( random_bytes( 16 ) );
		$th    = hash( 'sha256', $token );
		$data  = array(
			'kind'       => $target['kind'],
			'store'      => $session['store'],
			'network'    => $session['network'],
			'blog'       => (int) $target['blog'],
			'candidates' => $session['candidates'],
			'user'       => 'admin' === $target['kind'] ? $session['user'] : 0,
			'exp'        => time() + self::TOKEN_TTL,
		);
		$data['sig'] = self::token_signature( $th, $data );
		self::put( $session['store'], Store::TRANSIENT . 'test_' . $th, $data, self::TOKEN_TTL );
		$url = add_query_arg( self::PARAM, $token, $target['url'] );
		$session['targets'][] = array( 'token' => $th, 'kind' => $target['kind'], 'url' => $target['url'], 'blog' => (int) $target['blog'], 'label' => $target['label'], 'user' => $data['user'] );
		return array( 'url' => $url, 'kind' => $target['kind'] );
	}

	/**
	 * Paso 1, último punto: crea la sesión y los tokens.
	 *
	 * @param array $args { mode: activate|resume_site|resume_all, scope (de la petición), id, store: site|network,
	 *                      candidates: [ { scope, id, hash, source: draft|run } ], targets: self::targets() }
	 * @return array { sid, urls:[ { url, kind } ] }
	 */
	public static function start( array $args ) {
		$sid     = bin2hex( random_bytes( 16 ) );
		$session = array(
			'mode'       => in_array( $args['mode'] ?? '', self::MODES, true ) ? $args['mode'] : 'activate',
			'scope'      => Store::scope( (string) ( $args['scope'] ?? 'site' ) ),
			'id'         => (string) ( $args['id'] ?? '' ),
			'store'      => 'network' === ( $args['store'] ?? 'site' ) && is_multisite() ? 'network' : 'site',
			'network'    => (int) get_current_network_id(),
			'blog'       => (int) get_current_blog_id(),
			'candidates' => array(),
			'targets'    => array(),
			'user'       => (int) get_current_user_id(),
			'exp'        => time() + self::SESSION_TTL,
			'retried'    => false,
		);
		foreach ( (array) ( $args['candidates'] ?? array() ) as $c ) {
			$session['candidates'][] = array( 'scope' => Store::scope( (string) ( $c['scope'] ?? 'site' ) ), 'id' => (string) ( $c['id'] ?? '' ), 'hash' => (string) ( $c['hash'] ?? '' ), 'source' => 'run' === ( $c['source'] ?? '' ) ? 'run' : 'draft' );
		}
		$urls = array();
		foreach ( (array) ( $args['targets'] ?? array() ) as $target ) $urls[] = self::issue( $session, $target );
		$session['sig'] = self::session_signature( hash( 'sha256', $sid ), $session );
		self::put( $session['store'], Store::TRANSIENT . 'session_' . hash( 'sha256', $sid ), $session, self::SESSION_TTL );
		return array( 'sid' => $sid, 'urls' => $urls );
	}

	/**
	 * Sesión de $sid si existe, su firma vale, no caducó y es del usuario actual en este sitio y red; null si no.
	 */
	public static function session( $sid ) {
		if ( ! is_string( $sid ) || ! preg_match( '/^[0-9a-f]{32}$/D', $sid ) ) return null;
		$sh   = hash( 'sha256', $sid );
		$name = Store::TRANSIENT . 'session_' . $sh;
		$data = get_transient( $name );
		if ( ! is_array( $data ) && is_multisite() ) $data = get_site_transient( $name );
		if ( ! is_array( $data ) || ! isset( $data['mode'], $data['store'], $data['candidates'], $data['targets'], $data['sig'] ) ) return null;
		if ( ! Store::verify_session( array( 'token' => $sh, 'kind' => 'session:' . $data['mode'] . ':' . $data['scope'] . ':' . $data['id'], 'scope' => $data['store'], 'network' => $data['network'], 'blog' => $data['blog'], 'candidates' => $data['candidates'], 'user' => $data['user'], 'exp' => $data['exp'] ), $data['sig'] ) ) return null;
		if ( (int) $data['exp'] < time() || (int) $data['network'] !== (int) get_current_network_id() || (int) $data['blog'] !== (int) get_current_blog_id() || (int) $data['user'] !== (int) get_current_user_id() ) return null;
		return $data;
	}

	/** Borra la sesión, los tokens sin usar y los resultados. */
	public static function finish( $sid ) {
		$session = self::session( $sid );
		if ( ! $session ) return;
		foreach ( $session['targets'] as $target ) {
			self::drop( $session['store'], Store::TRANSIENT . 'test_' . $target['token'] );
			self::drop( $session['store'], Store::TRANSIENT . 'result_' . $target['token'] );
		}
		self::drop( $session['store'], Store::TRANSIENT . 'session_' . hash( 'sha256', $sid ) );
	}

	/* ------------------------------------------------------------------ *
	 * Petición de prueba (dentro de Runner::run)
	 * ------------------------------------------------------------------ */

	/**
	 * Valida el token de la petición: es de un solo uso (se borra al leerlo), su firma vale, no caducó, el sitio y la red
	 * coinciden y, en la portada, no hay sesión. En el escritorio, los candidatos sólo se cargan con la sesión del usuario
	 * que pidió la prueba; con otra (o ninguna) el contexto llega sin candidatos y el criterio lo rechaza.
	 *
	 * @return array|null { token, kind, store, user, candidates:[ { scope, id, title, code, hash, rev } ] }
	 */
	public static function claim( $raw ) {
		if ( ! is_string( $raw ) || ! preg_match( '/^[0-9a-f]{32}$/D', $raw ) ) return null;
		$th    = hash( 'sha256', $raw );
		$name  = Store::TRANSIENT . 'test_' . $th;
		$store = 'site';
		$data  = get_transient( $name );
		if ( ! is_array( $data ) && is_multisite() ) {
			$data  = get_site_transient( $name );
			$store = 'network';
		}
		if ( ! is_array( $data ) ) return null;
		self::drop( $store, $name );
		foreach ( array( 'kind', 'store', 'network', 'blog', 'candidates', 'user', 'exp', 'sig' ) as $key ) if ( ! array_key_exists( $key, $data ) ) return null;
		if ( $store !== $data['store'] || ! in_array( $data['kind'], array( 'admin', 'front' ), true ) || ! is_array( $data['candidates'] ) ) return null;
		if ( ! Store::verify_session( array_merge( $data, array( 'token' => $th, 'scope' => $data['store'] ) ), $data['sig'] ) ) return null;
		if ( (int) $data['exp'] < time() || (int) $data['network'] !== (int) get_current_network_id() || (int) $data['blog'] !== (int) get_current_blog_id() ) return null;
		$user = (int) get_current_user_id();
		if ( 'front' === $data['kind'] && 0 !== $user ) return null;
		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) define( 'WP_SANDBOX_SCRAPING', true );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		if ( function_exists( 'nocache_headers' ) && ! headers_sent() ) nocache_headers();
		// El borrador sólo corre en el escritorio con la sesión de quien pidió la prueba: sin ella (una URL filtrada, otro
		// usuario) no se carga ningún candidato, pero el resultado se escribe igual, con su usuario, y el criterio responde
		// «la prueba no pudo entrar al escritorio con tu sesión».
		$candidates = array();
		if ( 'front' === $data['kind'] || $user === (int) $data['user'] ) {
			foreach ( $data['candidates'] as $c ) {
				$found = self::candidate_code( (array) $c );
				if ( $found ) $candidates[] = $found;
			}
		}
		return array( 'token' => $th, 'kind' => $data['kind'], 'store' => $store, 'user' => (int) $data['user'], 'candidates' => $candidates );
	}

	/** Código actual de un candidato (borrador o versión activa), sólo si su hash sigue siendo el probado. */
	private static function candidate_code( array $c ) {
		$scope = Store::scope( (string) ( $c['scope'] ?? 'site' ) );
		$id    = (string) ( $c['id'] ?? '' );
		$hash  = (string) ( $c['hash'] ?? '' );
		if ( ! Store::valid_id( $id ) || '' === $hash ) return null;
		$rev = 0;
		if ( 'run' === ( $c['source'] ?? '' ) ) {
			$run = Store::run( $scope );
			if ( ! isset( $run['items'][ $id ] ) ) return null;
			$code  = $run['items'][ $id ]['code'];
			$title = $run['items'][ $id ]['title'];
			$rev   = (int) ( $run['items'][ $id ]['rev'] ?? 0 );
		} else {
			$item = Store::item( $scope, $id );
			if ( ! $item ) return null;
			$code  = $item['code'];
			$title = Store::index( $scope )['items'][ $id ]['title'] ?? '';
		}
		if ( ! hash_equals( $hash, hash( 'sha256', $code ) ) ) return null;
		return array( 'scope' => $scope, 'id' => $id, 'title' => $title, 'code' => $code, 'hash' => $hash, 'rev' => $rev );
	}

	/* ------------------------------------------------------------------ *
	 * Criterio (§4.4, «Criterio para pasar»)
	 * ------------------------------------------------------------------ */

	/**
	 * Paso 3: lee los resultados de la sesión y aplica el criterio.
	 *
	 * @param string $sid  Sesión.
	 * @param array  $seen sha256(token) → código HTTP que vio el navegador (sólo lo puede leer en su mismo origen). Una portada
	 *                     sin resultado que respondió 401 o 403 está tras la autenticación del servidor (staging con usuario y
	 *                     contraseña): WordPress ni arrancó, así que pasa sin probar, con aviso, si el escritorio pasó.
	 * @return array { status: pass|fail|retry|pending, message, line, warnings[], ms, maintenance, private, retry:[ { url, kind } ], session }
	 */
	public static function evaluate( $sid, array $seen = array() ) {
		$session = self::session( $sid );
		if ( ! $session ) return array( 'status' => 'fail', 'message' => 'La prueba caducó o no es tuya. Vuelve a pulsar «Guardar y activar».', 'line' => 0, 'warnings' => array() );
		foreach ( $session['candidates'] as $c ) {
			if ( ! self::candidate_code( $c ) ) return array( 'status' => 'fail', 'message' => Store::CHANGED_MESSAGE, 'line' => 0, 'warnings' => array(), 'session' => $session );
		}
		$warnings = array();
		$retry    = array();
		$pending  = null;
		$ms       = 0;
		$maint    = false;
		$private  = false;
		foreach ( $session['targets'] as $i => $target ) {
			$result = self::read( $session['store'], Store::TRANSIENT . 'result_' . $target['token'] );
			$http   = (int) ( $seen[ $target['token'] ] ?? 0 );
			if ( ! is_array( $result ) && 'front' === $target['kind'] && in_array( $http, array( 401, 403 ), true ) ) {
				$warnings[] = sprintf( '%s pide usuario y contraseña del servidor (HTTP %d): no se pudo probar. Se probó el escritorio.', self::capital( (string) $target['label'] ), $http );
				$private    = true;
				continue;
			}
			$verdict = self::judge( $target, is_array( $result ) ? $result : null, $session['candidates'], ! $session['retried'] );
			if ( 'fail' === $verdict['status'] ) return array_merge( $verdict, array( 'session' => $session ) );
			if ( 'pending' === $verdict['status'] ) {
				$pending = $pending ?: $verdict;
				continue;
			}
			if ( 'retry' === $verdict['status'] ) {
				$retry[ $i ] = $verdict['retry_url'];
				continue;
			}
			$warnings = array_merge( $warnings, $verdict['warnings'] );
			$ms       = max( $ms, (int) ( $result['ms'] ?? 0 ) );
			$maint    = $maint || ! empty( $verdict['maintenance'] );
			$private  = $private || ! empty( $verdict['private'] );
		}
		if ( $pending ) return array_merge( $pending, array( 'session' => $session ) );
		if ( $retry ) {
			$urls = array();
			foreach ( $retry as $i => $url ) {
				$target = $session['targets'][ $i ];
				unset( $session['targets'][ $i ] );
				$urls[] = self::issue( $session, array( 'url' => $url, 'kind' => $target['kind'], 'blog' => $target['blog'], 'label' => $target['label'] ) );
			}
			$session['targets'] = array_values( $session['targets'] );
			$session['retried'] = true;
			self::put( $session['store'], Store::TRANSIENT . 'session_' . hash( 'sha256', $sid ), $session, self::SESSION_TTL );
			return array( 'status' => 'retry', 'retry' => $urls, 'message' => '', 'line' => 0, 'warnings' => array(), 'session' => $session );
		}
		return array( 'status' => 'pass', 'message' => '', 'line' => 0, 'warnings' => array_values( array_unique( $warnings ) ), 'ms' => $ms, 'maintenance' => $maint, 'private' => $private, 'session' => $session );
	}

	/** Primera letra en mayúscula («la portada» → «La portada»). */
	private static function capital( $text ) {
		$text = (string) $text;
		if ( ! function_exists( 'mb_substr' ) ) return ucfirst( $text );
		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}

	/**
	 * Criterio para un destino, sin efectos: { status: pass|fail|retry|pending, message, line, warnings[], maintenance, retry_url }.
	 *
	 * @param array      $target     { kind, url, label, user }
	 * @param array|null $result     Lo que escribió Guard, o null si no llegó.
	 * @param array      $candidates [ { scope, id, hash } ]
	 * @param bool       $can_retry  Si aún se puede reintentar una redirección al mismo host.
	 */
	public static function judge( array $target, $result, array $candidates, $can_retry = true ) {
		$place  = (string) ( $target['label'] ?? '' );
		$admin  = 'admin' === ( $target['kind'] ?? '' );
		$fail   = static function ( $message, $line = 0 ) {
			return array( 'status' => 'fail', 'message' => $message, 'line' => (int) $line, 'warnings' => array() );
		};
		// Sin resultado: «pending» (el paso 3 espera una vez y vuelve a mirar) y después falla; nunca se activa a ciegas.
		if ( ! is_array( $result ) ) return array( 'status' => 'pending', 'message' => sprintf( 'No se pudo probar: %s no respondió a la prueba en 30 segundos. Por seguridad no se activó. Si tu caché o tu CDN guardan las direcciones con %s, exclúyelas. También pasa si un error fatal ocurre al terminar la petición (en una función de cierre de otro plugin o del fragmento): revisa el registro de errores de PHP.', $place, self::PARAM ), 'line' => 0, 'warnings' => array() );
		$ids = array();
		foreach ( $candidates as $c ) $ids[ (string) ( $c['id'] ?? '' ) ] = true;
		$fatal = is_array( $result['fatal'] ?? null ) ? $result['fatal'] : null;
		if ( $fatal ) {
			$message = rtrim( Store::one_line( (string) ( $fatal['message'] ?? '' ), 300 ), '.' );
			$line    = (int) ( $fatal['line'] ?? 0 );
			if ( ! empty( $fatal['attributed'] ) ) {
				return $fail( $line ? sprintf( 'No se activó: al probarlo en %s falló en la línea %d: %s.', $place, $line, $message ) : sprintf( 'No se activó: al probarlo en %s falló: %s.', $place, $message ), isset( $ids[ (string) ( $fatal['id'] ?? '' ) ] ) ? $line : 0 );
			}
			return $fail( sprintf( 'No se activó: el sitio dio un error que no viene de este fragmento: %s en %s. Corrígelo antes de activar.', $message, Store::one_line( (string) ( $fatal['file'] ?? '' ), 200 ) . ( empty( $fatal['line'] ) ? '' : ':' . (int) $fatal['line'] ) ) );
		}
		foreach ( (array) ( $result['caught'] ?? array() ) as $caught ) {
			$line    = (int) ( $caught['line'] ?? 0 );
			$message = rtrim( Store::one_line( ( $caught['class'] ?? 'Error' ) . ': ' . ( $caught['message'] ?? '' ), 300 ), '.' );
			return $fail( $line ? sprintf( 'No se activó: al probarlo en %s falló en la línea %d: %s.', $place, $line, $message ) : sprintf( 'No se activó: al probarlo en %s falló: %s.', $place, $message ), isset( $ids[ (string) ( $caught['id'] ?? '' ) ] ) ? $line : 0 );
		}
		$redirect = (string) ( $result['redirect'] ?? '' );
		if ( $admin && (int) ( $result['user'] ?? 0 ) !== (int) ( $target['user'] ?? -1 ) ) {
			if ( false !== strpos( $redirect, 'upgrade.php' ) ) return $fail( 'No se activó: el escritorio pide actualizar la base de datos. Actualízala y vuelve a intentarlo.' );
			return $fail( 'No se activó: la prueba no pudo entrar al escritorio con tu sesión.' );
		}
		$bytes = (int) ( $result['output'] ?? 0 );
		if ( $bytes > 0 ) return $fail( sprintf( 'No se activó: el fragmento imprime %d caracteres al cargarse. Suele ser texto fuera de una función; ponlo dentro de un gancho, por ejemplo wp_footer.', $bytes ) );
		$loaded = array();
		foreach ( (array) ( $result['loaded'] ?? array() ) as $l ) $loaded[ ( $l['scope'] ?? '' ) . ':' . ( $l['id'] ?? '' ) . ':' . ( $l['hash'] ?? '' ) ] = true;
		foreach ( $candidates as $c ) {
			if ( ! isset( $loaded[ ( $c['scope'] ?? '' ) . ':' . ( $c['id'] ?? '' ) . ':' . ( $c['hash'] ?? '' ) ] ) ) return $fail( sprintf( 'No se activó: la prueba no llegó a cargar el fragmento en %s.', $place ) );
		}
		$warnings = array();
		foreach ( (array) ( $result['notices'] ?? array() ) as $notice ) $warnings[] = sprintf( 'Aviso de PHP en la línea %d: %s', (int) ( $notice['line'] ?? 0 ), Store::one_line( (string) ( $notice['message'] ?? '' ), 300 ) );
		if ( ! empty( $result['buffer_left'] ) ) $warnings[] = 'El fragmento deja abierto un búfer de salida (ob_start sin cerrar): no se pudo medir lo que imprime al cargarse.';
		$footer = $admin ? 'admin_footer' : 'wp_footer';
		if ( $footer === ( $result['footer'] ?? '' ) ) return array( 'status' => 'pass', 'message' => '', 'line' => 0, 'warnings' => $warnings, 'maintenance' => false );
		if ( 503 === (int) ( $result['status'] ?? 0 ) ) return array( 'status' => 'pass', 'message' => '', 'line' => 0, 'warnings' => $warnings, 'maintenance' => true );
		// Una portada privada (redirige al acceso, como Force Login o un sitio en staging, o responde 401/403 sin un fatal) no
		// se puede probar entera sin sesión: el fragmento ya se cargó sin errores, así que pasa con aviso. El escritorio sí se
		// prueba siempre, y un destino que falle hace fallar toda la prueba.
		$status = (int) ( $result['status'] ?? 0 );
		if ( ! $admin && ( ! empty( $result['redirect_login'] ) || in_array( $status, array( 401, 403 ), true ) ) ) {
			$warnings[] = sprintf( '%s no es pública (%s): el fragmento se cargó sin errores, pero la página no se pudo probar entera. Si no lo esperabas, desactiva el fragmento.', self::capital( $place ), ! empty( $result['redirect_login'] ) ? 'redirige al acceso' : 'responde ' . $status );
			return array( 'status' => 'pass', 'message' => '', 'line' => 0, 'warnings' => $warnings, 'maintenance' => false, 'private' => true );
		}
		if ( '' !== $redirect ) {
			if ( $admin && false !== strpos( $redirect, 'upgrade.php' ) ) return $fail( 'No se activó: el escritorio pide actualizar la base de datos. Actualízala y vuelve a intentarlo.' );
			$url  = self::absolute( $redirect, (string) $target['url'] );
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' === $host || strtolower( $host ) !== strtolower( (string) wp_parse_url( (string) $target['url'], PHP_URL_HOST ) ) ) {
				return $fail( sprintf( 'No se activó: %s redirige a %s. Prueba desde ese dominio.', $place, '' === $host ? 'otra dirección' : $host ) );
			}
			if ( $can_retry && '' !== ( $safe = self::validate_redirect( $url, $host ) ) ) return array( 'status' => 'retry', 'message' => '', 'line' => 0, 'warnings' => $warnings, 'retry_url' => remove_query_arg( self::PARAM, $safe ) );
		}
		return $fail( sprintf( 'No se activó: %s no terminó de pintarse (no llegó a %s). Puede ser exit, die o una redirección fuera de una función; usa un gancho como template_redirect.', $place, $footer ) );
	}

	/** Convierte una redirección relativa en absoluta con el esquema y el host del destino. */
	private static function absolute( $location, $base ) {
		$location = trim( (string) $location );
		if ( preg_match( '#^https?://#i', $location ) ) return $location;
		$parts  = wp_parse_url( $base );
		$origin = ( $parts['scheme'] ?? 'http' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		if ( 0 === strpos( $location, '//' ) ) return ( $parts['scheme'] ?? 'http' ) . ':' . $location;
		if ( 0 === strpos( $location, '/' ) ) return $origin . $location;
		$path = (string) ( $parts['path'] ?? '/' );
		return $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $location;
	}

	/** wp_validate_redirect() con el host del destino permitido sólo durante la comprobación. */
	private static function validate_redirect( $url, $host ) {
		$allow = static function ( $hosts ) use ( $host ) {
			$hosts   = (array) $hosts;
			$hosts[] = $host;
			return $hosts;
		};
		add_filter( 'allowed_redirect_hosts', $allow );
		$safe = (string) wp_validate_redirect( $url, '' );
		remove_filter( 'allowed_redirect_hosts', $allow );
		return $safe;
	}
}
