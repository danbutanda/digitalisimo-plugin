<?php
/**
 * DIGITALÍSIMO: lo que aportaba un tema hijo de Hello y si puede traerse sin ejecutar su PHP.
 *
 * El tema no tiene hijo y nunca carga el PHP de otro tema. Del hijo se trae, sin ejecutar nada, lo que su functions.php
 * encola en `wp_enqueue_scripts` con valores que se pueden leer sin correrlo (la plantilla oficial del hijo de Hello y
 * sus variantes: hojas, fuentes y scripts de su carpeta o de otra dirección, con su handle, sus dependencias, su
 * versión y su prioridad). El tema los vuelve a encolar desde la carpeta del hijo, en el mismo punto de la cascada, así
 * que su CSS, sus `url()` relativos y sus fuentes se ven igual. Todo lo demás (otros ganchos, filtros, shortcodes,
 * funciones que reemplazan las de Hello, plantillas, `theme.json`) es un «bloqueo»: se dice qué es y en qué línea, y su
 * functions.php puede quedar en «Código PHP» de DIGITALÍSIMO Tools, inactivo y con revisión obligatoria.
 *
 * Las funciones de este archivo no enganchan nada al cargarse: «Migrar» de DIGITALÍSIMO Migración carga este archivo de
 * la copia instalada del tema (sólo si no hay otra copia cargada) para decidir, antes de cambiar de tema, si el hijo
 * puede dejarse sin perder nada.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Plan del hijo: lo que se trae (`assets`), lo que no (`blockers`: archivo, línea y qué es) y el hash de su functions.php.
 * Sólo lee archivos del hijo; nunca los ejecuta.
 */
function digitalisimo_theme_child_plan( WP_Theme $child ) {
	$dir    = untrailingslashit( (string) $child->get_stylesheet_directory() );
	$parent = $child->parent();
	$plan   = array(
		'v'        => 1, // Formato del plan y de la opción `digitalisimo_theme_child`.
		'child'    => (string) $child->get_stylesheet(),
		'parent'   => (string) $child->get_template(),
		'hash'     => '',
		'assets'   => array(),
		'blockers' => array(),
		'risks'    => array(), // Lo que cambiaría de sentido si su functions.php corre con el tema propio (ver _risks()).
	);
	$code = digitalisimo_theme_child_php_code( $child );
	if ( '' !== $code ) {
		$plan['hash'] = hash( 'sha256', $code );
		$found        = digitalisimo_theme_child_functions_plan(
			$code,
			array(
				'version'        => (string) $child->get( 'Version' ),
				'parent_version' => $parent ? (string) $parent->get( 'Version' ) : '',
				'exists'         => static function ( $where, $path ) use ( $dir, $parent ) {
					$base = 'parent' === $where ? ( $parent ? untrailingslashit( (string) $parent->get_stylesheet_directory() ) : '' ) : $dir;
					return '' !== $base && is_file( $base . '/' . $path );
				},
				'mtime'          => static function ( $path ) use ( $dir ) {
					return is_file( $dir . '/' . $path ) ? (string) filemtime( $dir . '/' . $path ) : '';
				},
			)
		);
		$plan['assets']   = $found['assets'];
		$plan['blockers'] = $found['blockers'];
		$plan['risks']    = $found['risks'];
	}
	foreach ( digitalisimo_theme_child_other_files( $child ) as $blocker ) {
		$plan['blockers'][] = $blocker;
	}
	return $plan;
}

/** functions.php del hijo como lo guardaría Código PHP de Tools (sin BOM, con saltos \n y sin el `?>` final). */
function digitalisimo_theme_child_php_code( WP_Theme $child ) {
	$file = trailingslashit( (string) $child->get_stylesheet_directory() ) . 'functions.php';
	if ( ! is_file( $file ) || ! is_readable( $file ) || filesize( $file ) > 262144 ) {
		return is_file( $file ) ? "\0" : '';
	}
	$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( 0 === strpos( $code, "\xEF\xBB\xBF" ) ) {
		$code = substr( $code, 3 );
	}
	$code = str_replace( "\r\n", "\n", $code );
	$trim = preg_replace( '/\?>\s*\z/', '', $code );
	return is_string( $trim ) && $trim !== $code ? rtrim( $trim ) . "\n" : $code;
}

/**
 * Lo que no es functions.php: una plantilla de página propia que algún contenido usa, un archivo PHP con código
 * (plantillas que sustituyen las de Hello, archivos que incluye), un `theme.json` o plantillas de bloques. Un PHP que
 * sólo abre y comenta («Silence is golden») y una plantilla de página que ningún contenido usa no cuentan.
 */
function digitalisimo_theme_child_other_files( WP_Theme $child ) {
	$dir      = untrailingslashit( (string) $child->get_stylesheet_directory() );
	$blockers = array();
	if ( ! is_dir( $dir ) ) {
		return $blockers;
	}
	$templates = array();
	foreach ( digitalisimo_theme_child_page_templates( $child ) as $row ) {
		$templates[ (string) $row['template'] ] = (int) $row['pages'];
	}
	try {
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
		$seen  = 0;
		foreach ( $files as $file ) {
			if ( ++$seen > 2000 || count( $blockers ) >= 20 ) {
				break;
			}
			$rel = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) ) ), '/' );
			$ext = strtolower( (string) $file->getExtension() );
			if ( 'functions.php' === $rel || ! $file->isFile() ) {
				continue;
			}
			if ( 'theme.json' === $rel || preg_match( '#^(templates|parts|block-templates|block-template-parts)/#', $rel ) ) {
				$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => 'theme.json' === $rel ? 'estilos globales propios (theme.json)' : 'plantilla de bloques' );
				continue;
			}
			if ( ! in_array( $ext, array( 'php', 'phtml', 'inc' ), true ) ) {
				continue;
			}
			if ( isset( $templates[ $rel ] ) ) {
				// Una plantilla de página propia del hijo: cuenta si algún contenido la usa (aunque no tenga código, la
				// página se pinta con ella), nunca si nadie la usa.
				if ( $templates[ $rel ] > 0 ) {
					$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => sprintf( 'plantilla de página que usan %d contenidos', $templates[ $rel ] ) );
				}
				continue;
			}
			if ( digitalisimo_theme_child_php_is_empty( $file->isReadable() && $file->getSize() <= MB_IN_BYTES ? (string) file_get_contents( $file->getPathname() ) : '<?php x();' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				continue;
			}
			$blockers[] = array( 'file' => $rel, 'line' => 0, 'what' => preg_match( '#^(header|footer|index|single|page|archive|search|404|comments|sidebar|front-page|home)\.php$|^(template-parts|woocommerce)/#', $rel ) ? 'plantilla que sustituye la del tema' : 'archivo PHP propio' );
		}
	} catch ( UnexpectedValueException $e ) {
		$blockers[] = array( 'file' => '', 'line' => 0, 'what' => 'carpeta del hijo ilegible' );
	}
	return $blockers;
}

/** ¿Sólo abre PHP y comenta (o sale si se carga directamente)? */
function digitalisimo_theme_child_php_is_empty( $code ) {
	$tokens = digitalisimo_theme_child_tokens( $code );
	if ( null === $tokens ) {
		return false;
	}
	$i = 0;
	while ( $i < count( $tokens ) ) {
		if ( digitalisimo_theme_child_guard( $tokens, $i ) ) {
			continue;
		}
		if ( in_array( $tokens[ $i ][0], array( T_OPEN_TAG, T_CLOSE_TAG, ';' ), true ) ) {
			++$i;
			continue;
		}
		return false;
	}
	return true;
}

/** Plantillas de página que sólo existen en el hijo y cuántos contenidos las usan. */
function digitalisimo_theme_child_page_templates( WP_Theme $child ) {
	$own = array();
	foreach ( (array) $child->get_post_templates() as $list ) {
		$own += (array) $list;
	}
	$parent = $child->parent();
	if ( $parent ) {
		foreach ( (array) $parent->get_post_templates() as $list ) {
			foreach ( array_keys( (array) $list ) as $file ) {
				unset( $own[ $file ] );
			}
		}
	}
	$out = array();
	foreach ( array_keys( $own ) as $file ) {
		$ids   = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$out[] = array( 'template' => (string) $file, 'pages' => count( (array) $ids ) );
	}
	return $out;
}

/* ------------------------------------------------------------------ Lectura del functions.php por tokens */

/** Tokens sin espacios ni comentarios, como [tipo, texto, línea]; null si no es PHP o lleva HTML fuera de PHP. */
function digitalisimo_theme_child_tokens( $code ) {
	if ( ! function_exists( 'token_get_all' ) || 0 !== strpos( ltrim( (string) $code ), '<?php' ) ) {
		return null;
	}
	$out  = array();
	$line = 1;
	foreach ( token_get_all( (string) $code ) as $token ) {
		if ( is_array( $token ) ) {
			$line = $token[2];
			if ( in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( defined( 'T_ATTRIBUTE' ) && T_ATTRIBUTE === $token[0] ) {
				$out[] = array( '[', '#[', $line ); // Un atributo de PHP 8 cierra con «]»: se cuenta como corchete (y bloquea).
				continue;
			}
			if ( T_INLINE_HTML === $token[0] ) {
				if ( '' !== trim( $token[1] ) ) {
					$out[] = array( T_INLINE_HTML, $token[1], $line );
				}
				continue;
			}
			$out[] = array( $token[0], $token[1], $line );
		} else {
			$out[] = array( $token, $token, $line );
		}
	}
	return $out;
}

/** ¿Es la guarda «sin cargar WordPress, salir»? La consume. */
function digitalisimo_theme_child_guard( array $t, &$i ) {
	$text = '';
	$j    = $i;
	for ( $k = 0; $k < 16 && $j < count( $t ); $k++, $j++ ) {
		$text .= strtolower( $t[ $j ][1] );
		if ( preg_match( '/^(if\(!defined\([\'"]abspath[\'"]\)\)(\{(exit|die)(\(\))?;\}|(exit|die)(\(\))?;)|defined\([\'"]abspath[\'"]\)(\|\||or)(exit|die)(\(\))?;)$/', $text ) ) {
			$i = $j + 1;
			return true;
		}
	}
	return false;
}

/** Índice del token que cierra el que abre en $i ((, [ o {). */
function digitalisimo_theme_child_close( array $t, $i ) {
	$depth = 0;
	for ( $n = count( $t ); $i < $n; $i++ ) {
		$v = $t[ $i ][0];
		if ( in_array( $v, array( '(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
			++$depth;
		} elseif ( in_array( $v, array( ')', ']', '}' ), true ) && 0 === --$depth ) {
			return $i;
		}
	}
	return $n;
}

/** Fin (exclusivo) de la sentencia que empieza en $i: hasta el `;` de su nivel o hasta el cierre de su bloque. */
function digitalisimo_theme_child_statement_end( array $t, $i ) {
	$n = count( $t );
	while ( $i < $n ) {
		$v = $t[ $i ][0];
		if ( ';' === $v ) {
			return $i + 1;
		}
		if ( '(' === $v || '[' === $v ) {
			$i = digitalisimo_theme_child_close( $t, $i ) + 1;
			continue;
		}
		if ( '{' === $v ) {
			$i = digitalisimo_theme_child_close( $t, $i ) + 1;
			if ( $i < $n && in_array( $t[ $i ][0], array( T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY ), true ) ) {
				continue;
			}
			// Una función anónima asignada o pasada como argumento sigue hasta su `;`.
			if ( $i < $n && in_array( $t[ $i ][0], array( ')', ',', ';' ), true ) ) {
				continue;
			}
			return $i;
		}
		++$i;
	}
	return $n;
}

/** Argumentos de la llamada cuyo `(` está en $open: listas de tokens separadas por las comas de su nivel. */
function digitalisimo_theme_child_args( array $t, $open, &$end ) {
	$end   = digitalisimo_theme_child_close( $t, $open );
	$args  = array();
	$cur   = array();
	$depth = 0;
	for ( $i = $open + 1; $i < $end; $i++ ) {
		$v = $t[ $i ][0];
		if ( in_array( $v, array( '(', '[', '{' ), true ) ) {
			++$depth;
		} elseif ( in_array( $v, array( ')', ']', '}' ), true ) ) {
			--$depth;
		}
		if ( ',' === $v && 0 === $depth ) {
			$args[] = $cur;
			$cur    = array();
			continue;
		}
		$cur[] = $t[ $i ];
	}
	if ( $cur ) {
		$args[] = $cur;
	}
	return $args;
}

/** Texto corto de una sentencia para decir qué es (`add_action( 'wp_head' )`, `add_shortcode( 'x' )`…). */
function digitalisimo_theme_child_describe( array $t, $i, $end ) {
	$first = $t[ $i ];
	if ( T_STRING === $first[0] && isset( $t[ $i + 1 ] ) && '(' === $t[ $i + 1 ][0] ) {
		$args = digitalisimo_theme_child_args( $t, $i + 1, $close );
		$lit  = isset( $args[0] ) && 1 === count( $args[0] ) && T_CONSTANT_ENCAPSED_STRING === $args[0][0][0] ? ' ' . $args[0][0][1] . ' ' : '';
		return $first[1] . '(' . $lit . ')';
	}
	$text = '';
	for ( $k = $i; $k < $end && strlen( $text ) < 60; $k++ ) {
		$text .= ( '' === $text || preg_match( '/^\W$/', $t[ $k ][1] ) ? '' : ' ' ) . $t[ $k ][1];
	}
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60 ) : substr( $text, 0, 60 );
}

/**
 * Lee el functions.php de un hijo sin ejecutarlo. Pura respecto de `$ctx`: `version` (la del hijo), `parent_version`,
 * `exists( 'child'|'parent', $ruta )` y `mtime( $ruta )`. Devuelve `assets` (lo que encola, con su prioridad) y
 * `blockers` (archivo, línea y qué es).
 */
function digitalisimo_theme_child_functions_plan( $code, array $ctx = array() ) {
	$ctx = array_merge(
		array(
			'version'        => '',
			'parent_version' => '',
			'exists'         => static function () {
				return true;
			},
			'mtime'          => static function () {
				return '';
			},
		),
		$ctx
	);
	$out = array( 'assets' => array(), 'blockers' => array(), 'risks' => array() );
	$t   = digitalisimo_theme_child_tokens( $code );
	if ( "\0" === $code || null === $t ) {
		$out['blockers'][] = array( 'file' => 'functions.php', 'line' => 0, 'what' => 'no se pudo leer como PHP' );
		return $out;
	}
	$block  = static function ( $line, $what ) use ( &$out ) {
		$out['blockers'][] = array( 'file' => 'functions.php', 'line' => (int) $line, 'what' => (string) $what );
	};
	$consts = array();
	$funcs  = array();
	$hooks  = array();
	$n      = count( $t );
	$i      = 0;
	while ( $i < $n ) {
		$v = $t[ $i ][0];
		if ( in_array( $v, array( T_OPEN_TAG, T_CLOSE_TAG, ';' ), true ) ) {
			++$i;
			continue;
		}
		if ( T_INLINE_HTML === $v ) {
			$block( $t[ $i ][2], 'imprime HTML fuera de PHP' );
			++$i;
			continue;
		}
		if ( digitalisimo_theme_child_guard( $t, $i ) ) {
			continue;
		}
		$end = digitalisimo_theme_child_statement_end( $t, $i );
		// function nombre( … ) { … }
		if ( T_FUNCTION === $v && isset( $t[ $i + 1 ] ) && T_STRING === $t[ $i + 1 ][0] && isset( $t[ $i + 2 ] ) && '(' === $t[ $i + 2 ][0] ) {
			$close = digitalisimo_theme_child_close( $t, $i + 2 );
			$k     = $close + 1;
			while ( $k < $n && '{' !== $t[ $k ][0] ) {
				++$k; // Tipo de retorno.
			}
			$body_end                            = digitalisimo_theme_child_close( $t, $k );
			$funcs[ strtolower( $t[ $i + 1 ][1] ) ] = array( 'name' => $t[ $i + 1 ][1], 'start' => $i, 'from' => $k + 1, 'to' => $body_end, 'line' => $t[ $i ][2], 'used' => false );
			$i = $body_end + 1;
			continue;
		}
		if ( T_CONST === $v && isset( $t[ $i + 1 ], $t[ $i + 2 ] ) && T_STRING === $t[ $i + 1 ][0] && '=' === $t[ $i + 2 ][0] ) {
			$value = digitalisimo_theme_child_value( array_slice( $t, $i + 3, $end - $i - 4 ), $consts, array(), $ctx );
			if ( $value[0] ) {
				$consts[ $t[ $i + 1 ][1] ] = $value[1];
				$i                         = $end;
				continue;
			}
		}
		if ( T_DECLARE === $v ) {
			$i = $end;
			continue;
		}
		if ( T_STRING === $v && isset( $t[ $i + 1 ] ) && '(' === $t[ $i + 1 ][0] ) {
			$name = strtolower( $t[ $i ][1] );
			$args = digitalisimo_theme_child_args( $t, $i + 1, $close );
			if ( isset( $t[ $close + 1 ] ) && ';' === $t[ $close + 1 ][0] ) {
				if ( 'define' === $name && 2 <= count( $args ) ) {
					$key   = digitalisimo_theme_child_value( $args[0], $consts, array(), $ctx );
					$value = digitalisimo_theme_child_value( $args[1], $consts, array(), $ctx );
					if ( $key[0] && is_string( $key[1] ) && $value[0] && ! is_array( $value[1] ) ) {
						$consts[ $key[1] ] = $value[1];
						$i                 = $end;
						continue;
					}
				}
				if ( 'add_action' === $name && 2 <= count( $args ) && count( $args ) <= 4 ) {
					$hook     = digitalisimo_theme_child_value( $args[0], $consts, array(), $ctx );
					$priority = isset( $args[2] ) ? digitalisimo_theme_child_value( $args[2], $consts, array(), $ctx ) : array( true, 10 );
					$callback = $args[1];
					$target   = null;
					if ( 1 === count( $callback ) && T_CONSTANT_ENCAPSED_STRING === $callback[0][0] ) {
						$target = array( 'name' => strtolower( (string) digitalisimo_theme_child_value( $callback, $consts, array(), $ctx )[1] ) );
					} else {
						$at = 0;
						if ( isset( $callback[0] ) && T_STATIC === $callback[0][0] ) {
							$at = 1;
						}
						if ( isset( $callback[ $at ] ) && T_FUNCTION === $callback[ $at ][0] ) {
							$open = $at + 1;
							while ( $open < count( $callback ) && '{' !== $callback[ $open ][0] ) {
								if ( T_USE === $callback[ $open ][0] ) {
									$open = count( $callback ); // Una clausura con `use` lleva estado: no se lee.
									break;
								}
								++$open;
							}
							if ( $open < count( $callback ) ) {
								$target = array( 'tokens' => array_slice( $callback, $open + 1, digitalisimo_theme_child_close( $callback, $open ) - $open - 1 ), 'line' => $callback[ $at ][2] );
							}
						}
					}
					if ( $hook[0] && is_string( $hook[1] ) && $priority[0] && is_int( $priority[1] ) && null !== $target && in_array( $hook[1], array( 'wp_enqueue_scripts', 'after_setup_theme' ), true ) ) {
						$hooks[] = array( 'hook' => $hook[1], 'priority' => $priority[1], 'target' => $target, 'line' => $t[ $i ][2], 'range' => array( $i, $end ) );
						$i       = $end;
						continue;
					}
				}
			}
		}
		$block( $t[ $i ][2], digitalisimo_theme_child_describe( $t, $i, $end ) );
		$i = $end;
	}

	$assets = array();
	$clean  = array(); // Tramos de tokens que el tema trae solo (sin bloqueos): ahí las rutas del tema no importan.
	foreach ( $hooks as $hook ) {
		$target = $hook['target'];
		$before = count( $out['blockers'] );
		if ( isset( $target['name'] ) ) {
			if ( ! isset( $funcs[ $target['name'] ] ) ) {
				$block( $hook['line'], sprintf( "add_action( '%s', '%s' ) con una función que no está en functions.php", $hook['hook'], $target['name'] ) );
				continue;
			}
			$funcs[ $target['name'] ]['used'] = true;
			$fn                               = $funcs[ $target['name'] ];
			$body                             = array_slice( $t, $fn['from'], $fn['to'] - $fn['from'] );
			$where                            = $fn['name'] . '()';
		} else {
			$body  = $target['tokens'];
			$where = 'la función de ' . $hook['hook'];
		}
		digitalisimo_theme_child_body( $body, $hook, $where, $consts, $ctx, $assets, $block );
		if ( count( $out['blockers'] ) === $before ) {
			$clean[] = $hook['range'];
			if ( isset( $target['name'] ) ) {
				$clean[] = array( $funcs[ $target['name'] ]['start'], $funcs[ $target['name'] ]['to'] + 1 );
			}
		}
	}
	$out['risks'] = digitalisimo_theme_child_risks( $t, $clean );
	foreach ( $funcs as $fn ) {
		if ( ! $fn['used'] ) {
			$block( $fn['line'], 'define la función ' . $fn['name'] . '()' );
		}
	}
	// Un handle registrado y encolado después sin dirección: se encola el registrado.
	$by_handle = array();
	foreach ( $assets as $index => $asset ) {
		$key = $asset['t'] . ':' . $asset['h'];
		if ( '' === $asset['s'] && isset( $by_handle[ $key ] ) ) {
			$assets[ $by_handle[ $key ] ]['e'] = true;
			$assets[ $by_handle[ $key ] ]['p'] = min( $assets[ $by_handle[ $key ] ]['p'], $asset['p'] );
			unset( $assets[ $index ] );
			continue;
		}
		if ( '' === $asset['s'] ) {
			unset( $assets[ $index ] ); // Encola un handle de otro (de Hello o de un plugin): sin efecto que traer.
			continue;
		}
		$by_handle[ $key ] = $index;
	}
	$out['assets'] = array_values( $assets );
	return $out;
}

/**
 * Lo que en Código PHP de Tools, con el tema propio activo y dentro de la condición, haría otra cosa que en el hijo:
 * constantes y funciones de Hello (con el tema propio no existen: un error fatal en cada página), las rutas del tema
 * (`get_stylesheet_directory*()` y parecidas apuntarían a la carpeta del tema propio; no cuentan en lo que el tema ya trae
 * solo, donde su handle ya está registrado), funciones o clases usadas antes de declararse (dentro de un `if` no se
 * declaran por adelantado) y `global` de una variable de primer nivel (cada fragmento corre en su propio ámbito). Pura:
 * recibe los tokens del lector y los tramos ya traídos [desde, hasta). Devuelve bloqueos (archivo, línea, qué es).
 */
function digitalisimo_theme_child_risks( array $t, array $clean = array() ) {
	$n       = count( $t );
	$stack   = array();
	$pending = false;
	$in_body = array();
	$funcs   = array();
	$classes = array();
	$extends = array();
	$defined = array();
	$top     = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$v             = $t[ $i ][0];
		$in_body[ $i ] = in_array( 'body', $stack, true );
		$prev          = $i > 0 ? $t[ $i - 1 ][0] : null;
		if ( in_array( $v, array( T_FUNCTION, T_CLASS, T_TRAIT, T_INTERFACE ), true ) && T_DOUBLE_COLON !== $prev ) {
			$pending = true;
			$k       = isset( $t[ $i + 1 ] ) && '&' === $t[ $i + 1 ][0] ? $i + 2 : $i + 1;
			if ( ! $in_body[ $i ] && isset( $t[ $k ] ) && T_STRING === $t[ $k ][0] ) {
				$name = strtolower( $t[ $k ][1] );
				if ( T_FUNCTION === $v ) {
					$funcs += array( $name => $i );
				} else {
					$classes += array( $name => $i );
					if ( isset( $t[ $k + 1 ], $t[ $k + 2 ] ) && T_EXTENDS === $t[ $k + 1 ][0] && T_STRING === $t[ $k + 2 ][0] ) {
						$extends[] = array( $i, $t[ $k + 2 ][1], $t[ $k ][1] );
					}
				}
			}
		} elseif ( '{' === $v || T_CURLY_OPEN === $v || T_DOLLAR_OPEN_CURLY_BRACES === $v ) {
			$stack[] = $pending && '{' === $v ? 'body' : 'block';
			$pending = false;
		} elseif ( '}' === $v ) {
			array_pop( $stack );
		} elseif ( ';' === $v ) {
			$pending = false;
		} elseif ( T_STRING === $v && 'define' === strtolower( $t[ $i ][1] ) && isset( $t[ $i + 1 ], $t[ $i + 2 ] ) && '(' === $t[ $i + 1 ][0] && T_CONSTANT_ENCAPSED_STRING === $t[ $i + 2 ][0] ) {
			$defined[ trim( $t[ $i + 2 ][1], '\'"' ) ] = true;
		} elseif ( T_CONST === $v && isset( $t[ $i + 1 ] ) && T_STRING === $t[ $i + 1 ][0] ) {
			$defined[ $t[ $i + 1 ][1] ] = true;
		} elseif ( T_VARIABLE === $v && ! $in_body[ $i ] && isset( $t[ $i + 1 ] ) && '=' === $t[ $i + 1 ][0] ) {
			$top[ $t[ $i ][1] ] = true;
		}
	}
	$skip = array();
	foreach ( $clean as $range ) {
		for ( $i = (int) $range[0]; $i < (int) $range[1] && $i < $n; $i++ ) {
			$skip[ $i ] = true;
		}
	}
	$paths = '/^(get_(stylesheet|template)_directory(_uri)?|get_stylesheet_uri|get_(parent_)?theme_file_(uri|path))$/i';
	$names = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_EXTENDS, T_IMPLEMENTS, T_NEW );
	if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
		$names[] = T_NULLSAFE_OBJECT_OPERATOR;
	}
	$risks = array();
	$add   = static function ( $line, $what ) use ( &$risks ) {
		$risks[ $what ] = array( 'file' => 'functions.php', 'line' => (int) $line, 'what' => (string) $what );
	};
	for ( $i = 0; $i < $n; $i++ ) {
		list( $v, $text, $line ) = $t[ $i ];
		$prev = $i > 0 ? $t[ $i - 1 ][0] : null;
		$call = isset( $t[ $i + 1 ] ) && '(' === $t[ $i + 1 ][0];
		if ( T_STRING === $v && ! in_array( $prev, $names, true ) ) {
			$lower = strtolower( $text );
			if ( preg_match( '/^HELLO_ELEMENTOR_\w+$/', $text ) && ! $call && ! isset( $defined[ $text ] ) ) {
				$add( $line, sprintf( 'usa la constante %s de Hello, que con el tema DIGITALÍSIMO no existe', $text ) );
			} elseif ( $call && 0 === strpos( $lower, 'hello_elementor_' ) && ! isset( $funcs[ $lower ] ) ) {
				$add( $line, sprintf( 'llama a %s(), de Hello, que con el tema DIGITALÍSIMO no existe', $text ) );
			} elseif ( $call && empty( $skip[ $i ] ) && preg_match( $paths, $text ) ) {
				$add( $line, sprintf( 'usa %s(), que con el tema DIGITALÍSIMO apunta a su carpeta y no a la del hijo', $text ) );
			} elseif ( $call && ! $in_body[ $i ] && isset( $funcs[ $lower ] ) && $funcs[ $lower ] > $i ) {
				$add( $line, sprintf( 'llama a %s() antes de declararla (dentro de la condición todavía no existiría)', $text ) );
			}
		} elseif ( T_STRING === $v && T_NEW === $prev && ! $in_body[ $i ] && isset( $classes[ strtolower( $text ) ] ) && $classes[ strtolower( $text ) ] > $i ) {
			$add( $line, sprintf( 'crea un %s antes de declarar la clase (dentro de la condición todavía no existiría)', $text ) );
		} elseif ( T_GLOBAL === $v && $in_body[ $i ] ) {
			for ( $k = $i + 1; $k < $n && ';' !== $t[ $k ][0]; $k++ ) {
				if ( T_VARIABLE === $t[ $k ][0] && isset( $top[ $t[ $k ][1] ] ) ) {
					$add( $t[ $k ][2], sprintf( 'usa global %s, una variable de primer nivel del archivo (en Código PHP cada fragmento tiene su propio ámbito)', $t[ $k ][1] ) );
				}
			}
		}
	}
	foreach ( $extends as $row ) {
		if ( isset( $classes[ strtolower( $row[1] ) ] ) && $classes[ strtolower( $row[1] ) ] > $row[0] ) {
			$add( $t[ $row[0] ][2], sprintf( 'la clase %s extiende %s, declarada más abajo (dentro de la condición todavía no existiría)', $row[2], $row[1] ) );
		}
	}
	return array_values( $risks );
}

/** Cuerpo de una función enganchada: sólo encolados y registros con valores legibles. */
function digitalisimo_theme_child_body( array $body, array $hook, $where, array $consts, array $ctx, array &$assets, callable $block ) {
	$vars = array();
	$n    = count( $body );
	$i    = 0;
	while ( $i < $n ) {
		if ( ';' === $body[ $i ][0] ) {
			++$i;
			continue;
		}
		$end = digitalisimo_theme_child_statement_end( $body, $i );
		$ok  = false;
		if ( T_VARIABLE === $body[ $i ][0] && isset( $body[ $i + 1 ] ) && '=' === $body[ $i + 1 ][0] ) {
			$value = digitalisimo_theme_child_value( array_slice( $body, $i + 2, $end - $i - 3 ), $consts, $vars, $ctx );
			if ( $value[0] ) {
				$vars[ $body[ $i ][1] ] = $value[1];
				$ok                     = true;
			}
		} elseif ( T_STRING === $body[ $i ][0] && isset( $body[ $i + 1 ] ) && '(' === $body[ $i + 1 ][0] ) {
			$name = strtolower( $body[ $i ][1] );
			$args = digitalisimo_theme_child_args( $body, $i + 1, $close );
			$vals = array();
			if ( ! isset( $body[ $close + 1 ] ) || ';' !== $body[ $close + 1 ][0] ) {
				$args = array( array() ); // `wp_enqueue_style( … ) || otra_cosa();` no es un encolado limpio.
			}
			foreach ( $args as $arg ) {
				$value  = digitalisimo_theme_child_value( $arg, $consts, $vars, $ctx );
				$ok     = $value[0];
				$vals[] = $value[1];
				if ( ! $ok ) {
					break;
				}
			}
			if ( $ok && 'after_setup_theme' === $hook['hook'] ) {
				$ok = 'load_child_theme_textdomain' === $name; // Traducciones del hijo: sin efecto en lo que se ve.
			} elseif ( $ok && 'wp_enqueue_scripts' === $hook['hook'] && preg_match( '/^wp_(enqueue|register)_(style|script)$/', $name, $m ) && $vals ) {
				$asset = digitalisimo_theme_child_asset( 'style' === $m[2] ? 'style' : 'script', 'enqueue' === $m[1], $vals, (int) $hook['priority'], $ctx );
				if ( is_array( $asset ) ) {
					$assets[] = $asset;
				} else {
					$ok = false;
				}
			} else {
				$ok = false;
			}
		}
		if ( ! $ok ) {
			$block( $body[ $i ][2], 'en ' . $where . ': ' . digitalisimo_theme_child_describe( $body, $i, $end ) );
		}
		$i = $end;
	}
}

/**
 * Un encolado o registro como entrada de la opción (claves cortas), o null si su dirección o sus datos no se pueden
 * traer. Un archivo del hijo que no existe se trae igual: con Hello su handle existía (y lo que dependía de él se
 * imprimía), así que en el tema queda como alias sin archivo.
 */
function digitalisimo_theme_child_asset( $type, $enqueue, array $vals, $priority, array $ctx ) {
	$handle = $vals[0];
	if ( ! is_string( $handle ) || ! preg_match( '/^[A-Za-z0-9_.\-]{1,100}$/D', $handle ) ) {
		return null;
	}
	$src   = $vals[1] ?? '';
	$where = '';
	$path  = '';
	if ( '' === $src || false === $src || null === $src ) {
		$src = '';
	} elseif ( ! is_string( $src ) ) {
		return null;
	} elseif ( preg_match( '#^\x01(child|parent)/(.+)$#', $src, $m ) ) {
		$where = $m[1];
		$path  = $m[2];
		if ( false !== strpos( $path, '..' ) || false !== strpos( $path, "\x01" ) || ! preg_match( '#^[A-Za-z0-9_./\-]+$#', $path ) ) {
			return null;
		}
		$src = $path;
	} elseif ( preg_match( '#^(https?:)?//[^/\s"\'<>]+[^\s"\'<>]*$#i', $src ) && false === strpos( $src, "\x01" ) ) {
		$where = 'url';
	} else {
		return null; // Relativa a la raíz del sitio o desconocida.
	}
	$deps = $vals[2] ?? array();
	if ( ! is_array( $deps ) ) {
		return null;
	}
	foreach ( $deps as $dep ) {
		if ( ! is_string( $dep ) || ! preg_match( '/^[A-Za-z0-9_.\-]{1,100}$/D', $dep ) ) {
			return null;
		}
	}
	$ver = array_key_exists( 3, $vals ) ? $vals[3] : false; // null (sin versión) es un valor, no una ausencia.
	if ( is_int( $ver ) || is_float( $ver ) ) {
		$ver = (string) $ver;
	}
	if ( ! is_string( $ver ) && false !== $ver && null !== $ver ) {
		return null;
	}
	$extra = $vals[4] ?? ( 'style' === $type ? 'all' : false );
	if ( 'style' === $type && ! is_string( $extra ) ) {
		return null;
	}
	if ( 'script' === $type && ! is_bool( $extra ) && ! ( is_array( $extra ) && array() === array_diff( array_keys( $extra ), array( 'in_footer', 'strategy' ) ) ) ) {
		return null;
	}
	return array(
		't' => $type,
		'h' => $handle,
		'w' => $where,
		's' => $src,
		'd' => array_values( $deps ),
		'v' => $ver,
		'm' => $extra,
		'p' => (int) $priority,
		'e' => (bool) $enqueue,
	);
}

/**
 * Valor de una expresión sin ejecutarla: literales, constantes del archivo, variables ya leídas, concatenación, listas
 * y las pocas funciones del tema que la plantilla del hijo usa. Las direcciones de su carpeta llevan el marcador
 * "\x01child" ("\x01parent" para la de Hello). Devuelve [ok, valor].
 */
function digitalisimo_theme_child_value( array $e, array $consts, array $vars, array $ctx ) {
	$fail = array( false, null );
	$n    = count( $e );
	if ( ! $n ) {
		return $fail;
	}
	// Concatenación en su nivel.
	$parts = array( array() );
	$depth = 0;
	foreach ( $e as $token ) {
		if ( in_array( $token[0], array( '(', '[' ), true ) ) {
			++$depth;
		} elseif ( in_array( $token[0], array( ')', ']' ), true ) ) {
			--$depth;
		}
		if ( '.' === $token[0] && 0 === $depth ) {
			$parts[] = array();
			continue;
		}
		$parts[ count( $parts ) - 1 ][] = $token;
	}
	if ( count( $parts ) > 1 ) {
		$text = '';
		foreach ( $parts as $part ) {
			$value = digitalisimo_theme_child_value( $part, $consts, $vars, $ctx );
			if ( ! $value[0] || is_array( $value[1] ) || is_bool( $value[1] ) || null === $value[1] ) {
				return $fail;
			}
			$text .= (string) $value[1];
		}
		return array( true, $text );
	}
	$first = $e[0];
	$low   = strtolower( (string) $first[1] );
	if ( 1 === $n ) {
		switch ( $first[0] ) {
			case T_CONSTANT_ENCAPSED_STRING:
				$raw = substr( $first[1], 1, -1 );
				if ( "'" === $first[1][0] ) {
					return array( true, str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $raw ) );
				}
				return array( true, stripcslashes( $raw ) ); // Una cadena con variables no llega como un solo token: no se lee.
			case T_LNUMBER:
				return array( true, (int) $first[1] );
			case T_DNUMBER:
				return array( true, (float) $first[1] );
			case T_VARIABLE:
				return array_key_exists( $first[1], $vars ) ? array( true, $vars[ $first[1] ] ) : $fail;
			case T_STRING:
				if ( in_array( $low, array( 'true', 'false', 'null' ), true ) ) {
					return array( true, array( 'true' => true, 'false' => false, 'null' => null )[ $low ] );
				}
				if ( array_key_exists( $first[1], $consts ) ) {
					return array( true, $consts[ $first[1] ] );
				}
				if ( 'HELLO_ELEMENTOR_VERSION' === $first[1] && '' !== (string) $ctx['parent_version'] ) {
					return array( true, (string) $ctx['parent_version'] );
				}
				return $fail;
		}
		return $fail;
	}
	// Listas: array( … ) y [ … ], con o sin claves.
	if ( ( T_ARRAY === $first[0] && '(' === ( $e[1][0] ?? '' ) && digitalisimo_theme_child_close( $e, 1 ) === $n - 1 ) || ( '[' === $first[0] && digitalisimo_theme_child_close( $e, 0 ) === $n - 1 ) ) {
		$open  = T_ARRAY === $first[0] ? 1 : 0;
		$items = digitalisimo_theme_child_args( $e, $open, $close );
		$list  = array();
		foreach ( $items as $item ) {
			$arrow = null;
			foreach ( $item as $k => $token ) {
				if ( T_DOUBLE_ARROW === $token[0] ) {
					$arrow = $k;
					break;
				}
			}
			$value = digitalisimo_theme_child_value( null === $arrow ? $item : array_slice( $item, $arrow + 1 ), $consts, $vars, $ctx );
			if ( ! $value[0] ) {
				return $fail;
			}
			if ( null === $arrow ) {
				$list[] = $value[1];
				continue;
			}
			$key = digitalisimo_theme_child_value( array_slice( $item, 0, $arrow ), $consts, $vars, $ctx );
			if ( ! $key[0] || ! is_scalar( $key[1] ) ) {
				return $fail;
			}
			$list[ $key[1] ] = $value[1];
		}
		return array( true, $list );
	}
	// Llamadas sin efectos que la plantilla del hijo usa.
	if ( T_STRING === $first[0] && '(' === ( $e[1][0] ?? '' ) ) {
		$close = digitalisimo_theme_child_close( $e, 1 );
		$args  = digitalisimo_theme_child_args( $e, 1, $close );
		if ( $close === $n - 1 ) {
			switch ( $low ) {
				case 'get_stylesheet_directory_uri':
					return $args ? $fail : array( true, "\x01child" );
				case 'get_template_directory_uri':
					return $args ? $fail : array( true, "\x01parent" );
				case 'get_stylesheet_uri':
					return $args ? $fail : array( true, "\x01child/style.css" );
				case 'get_stylesheet_directory':
					return $args ? $fail : array( true, "\x02child" );
				case 'get_theme_file_uri':
				case 'get_parent_theme_file_uri':
					$file = 1 === count( $args ) ? digitalisimo_theme_child_value( $args[0], $consts, $vars, $ctx ) : array( true, '' );
					if ( ! $file[0] || ! is_string( $file[1] ) ) {
						return $fail;
					}
					$path = ltrim( $file[1], '/' );
					$in   = 'get_theme_file_uri' === $low && call_user_func( $ctx['exists'], 'child', $path ) ? 'child' : 'parent';
					return array( true, "\x01" . $in . ( '' !== $path ? '/' . $path : '' ) );
				case 'filemtime':
					$file = 1 === count( $args ) ? digitalisimo_theme_child_value( $args[0], $consts, $vars, $ctx ) : $fail;
					if ( ! $file[0] || ! is_string( $file[1] ) || 0 !== strpos( $file[1], "\x02child/" ) ) {
						return $fail;
					}
					$mtime = (string) call_user_func( $ctx['mtime'], substr( $file[1], 7 ) );
					return '' !== $mtime ? array( true, $mtime ) : $fail;
			}
		}
		// wp_get_theme()->get( 'Version' ): la versión del hijo.
		if ( 'wp_get_theme' === $low && ! $args && isset( $e[ $close + 1 ], $e[ $close + 2 ], $e[ $close + 3 ] ) && T_OBJECT_OPERATOR === $e[ $close + 1 ][0] && 'get' === strtolower( $e[ $close + 2 ][1] ) && '(' === $e[ $close + 3 ][0] ) {
			$inner = digitalisimo_theme_child_close( $e, $close + 3 );
			$what  = digitalisimo_theme_child_args( $e, $close + 3, $inner );
			$key   = 1 === count( $what ) ? digitalisimo_theme_child_value( $what[0], $consts, $vars, $ctx ) : $fail;
			if ( $inner === $n - 1 && $key[0] && 'Version' === $key[1] ) {
				return array( true, (string) $ctx['version'] );
			}
		}
	}
	return $fail;
}

/* ------------------------------------------------------------------ En el tema activo */

/**
 * Engancha, en las prioridades del hijo, el encolado de lo que su functions.php encolaba. Se llama al cargar el tema
 * (antes de que inc/assets.php enganche sus hojas): a igual prioridad el hijo iba antes que Hello, como aquí. Sólo con
 * `carry` en el registro del paso.
 */
function digitalisimo_theme_child_hooks() {
	// El registro (con carga automática) dice si hay algo que traer: un sitio sin hijo no consulta otra opción.
	$record = function_exists( 'digitalisimo_theme_switch_record' ) ? digitalisimo_theme_switch_record() : array();
	if ( empty( $record['carry'] ) ) {
		return;
	}
	$saved = get_option( 'digitalisimo_theme_child' );
	if ( ! is_array( $saved ) || empty( $saved['assets'] ) || ! is_array( $saved['assets'] ) ) {
		return;
	}
	$priorities = array();
	foreach ( $saved['assets'] as $asset ) {
		if ( is_array( $asset ) && ! empty( $asset['e'] ) ) {
			$priorities[ (int) ( $asset['p'] ?? 10 ) ] = true;
		}
	}
	foreach ( array_keys( $priorities ) as $priority ) {
		add_action(
			'wp_enqueue_scripts',
			static function () use ( $priority ) {
				digitalisimo_theme_child_enqueue( $priority );
			},
			$priority
		);
	}
}

/**
 * Registra (la primera vez, todas las entradas: un handle que el hijo registraba en una prioridad posterior queda
 * registrado antes) y encola lo del hijo de esta prioridad, desde su carpeta, sólo en paridad. Si el archivo ya no está,
 * el handle queda como alias sin archivo con sus dependencias. Registrar antes que nadie hace que un fragmento de Tools
 * con el mismo functions.php (que apuntaría a la carpeta de este tema) no cambie la dirección del handle.
 */
function digitalisimo_theme_child_enqueue( $priority ) {
	static $registered = null;
	if ( ! function_exists( 'digitalisimo_theme_parity' ) || ! digitalisimo_theme_parity() ) {
		return;
	}
	$saved = get_option( 'digitalisimo_theme_child' );
	if ( ! is_array( $saved ) || empty( $saved['assets'] ) ) {
		return;
	}
	if ( null === $registered ) {
		$registered = array();
		foreach ( (array) $saved['assets'] as $index => $asset ) {
			$url = digitalisimo_theme_child_asset_url( $saved, (array) $asset );
			if ( '' === $url ) {
				$url = false; // Sin archivo: alias con sus dependencias, para que lo que depende de él se siga imprimiendo.
			}
			$ver = $asset['v'] ?? false;
			if ( 'script' === ( $asset['t'] ?? '' ) ) {
				wp_register_script( (string) $asset['h'], $url, (array) ( $asset['d'] ?? array() ), $ver, $asset['m'] ?? false );
			} else {
				wp_register_style( (string) $asset['h'], $url, (array) ( $asset['d'] ?? array() ), $ver, is_string( $asset['m'] ?? null ) ? $asset['m'] : 'all' );
			}
			$registered[ $index ] = true;
		}
	}
	foreach ( (array) $saved['assets'] as $index => $asset ) {
		if ( empty( $registered[ $index ] ) || empty( $asset['e'] ) || (int) ( $asset['p'] ?? 10 ) !== (int) $priority ) {
			continue;
		}
		'script' === ( $asset['t'] ?? '' ) ? wp_enqueue_script( (string) $asset['h'] ) : wp_enqueue_style( (string) $asset['h'] );
	}
}

/** Dirección de una entrada: en la carpeta del hijo (o de Hello) si el archivo sigue ahí; la externa tal cual. */
function digitalisimo_theme_child_asset_url( array $saved, array $asset ) {
	$where = (string) ( $asset['w'] ?? '' );
	$src   = (string) ( $asset['s'] ?? '' );
	if ( 'url' === $where ) {
		return $src;
	}
	$theme = 'parent' === $where ? (string) ( $saved['parent'] ?? '' ) : (string) ( $saved['child'] ?? '' );
	if ( '' === $theme || '' === $src || false !== strpos( $src, '..' ) || ! in_array( $where, array( 'child', 'parent' ), true ) ) {
		return '';
	}
	if ( ! is_file( get_theme_root( $theme ) . '/' . $theme . '/' . $src ) ) {
		return ''; // El hijo ya no está: no se imprime un enlace roto.
	}
	return get_theme_root_uri( $theme ) . '/' . $theme . '/' . $src;
}

/** El hijo cuyas hojas carga el tema (o '' si no hay ninguno). */
function digitalisimo_theme_child_carried() {
	$record = function_exists( 'digitalisimo_theme_switch_record' ) ? digitalisimo_theme_switch_record() : array();
	$saved  = empty( $record['carry'] ) ? array() : get_option( 'digitalisimo_theme_child' );
	return is_array( $saved ) && ! empty( $saved['child'] ) ? (string) $saved['child'] : '';
}

/** En Temas: el tema carga las hojas del hijo desde su carpeta (no lo borres) o el hijo ya no está. */
function digitalisimo_theme_child_notice() {
	if ( ! current_user_can( 'switch_themes' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	$child  = $screen && in_array( $screen->id, array( 'themes', 'themes-network' ), true ) ? digitalisimo_theme_child_carried() : '';
	if ( '' === $child ) {
		return;
	}
	if ( is_dir( get_theme_root( $child ) . '/' . $child ) ) {
		/* translators: %s: folder of the child theme. */
		echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'The DIGITALÍSIMO theme loads the stylesheets, fonts and scripts of the child theme %s from its folder: do not delete that theme.', 'digitalisimo-theme' ), $child ) ) . '</p></div>';
		return;
	}
	/* translators: %s: folder of the child theme. */
	echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'The child theme %s was deleted: the DIGITALÍSIMO theme can no longer load its stylesheets, fonts and scripts. Reinstall it to get them back.', 'digitalisimo-theme' ), $child ) ) . '</p></div>';
}

/** Al borrar el hijo cuyas hojas carga el tema, queda en la bitácora del paso. */
function digitalisimo_theme_child_deleted( $stylesheet, $deleted = true ) {
	if ( ! $deleted || (string) $stylesheet !== digitalisimo_theme_child_carried() || ! function_exists( 'digitalisimo_theme_switch_log' ) ) {
		return;
	}
	$log             = digitalisimo_theme_switch_log();
	$log['warnings'] = digitalisimo_theme_merge_warnings(
		$log['warnings'] ?? array(),
		array(
			array(
				'code'    => 'child_deleted',
				'message' => sprintf( 'Se borró el tema hijo %s: el tema ya no carga sus hojas, fuentes y scripts. Reinstálalo para recuperarlos.', (string) $stylesheet ),
			),
		)
	);
	update_option( 'digitalisimo_theme_switch_log', $log, false );
}

/* ------------------------------------------------------------------ PHP del hijo en Código PHP de Tools */

/**
 * ¿Está el functions.php del hijo, sin cambios, activo en Código PHP de DIGITALÍSIMO Tools (de sitio o de red)? Entonces
 * su PHP sigue corriendo con el tema propio y lo que bloqueaba deja de hacerlo.
 */
function digitalisimo_theme_child_php_covered( WP_Theme $child ) {
	$code = digitalisimo_theme_child_php_code( $child );
	if ( '' === $code || "\0" === $code ) {
		return false;
	}
	$hashes = array( hash( 'sha256', $code ) );
	$wrap   = digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ) );
	if ( '' !== $wrap ) {
		$hashes[] = hash( 'sha256', $wrap );
	}
	foreach ( (array) apply_filters( 'digitalisimo_tools_php_sources', array(), array() ) as $row ) {
		if ( is_array( $row ) && ! empty( $row['active'] ) && in_array( (string) ( $row['hash'] ?? '' ), $hashes, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * El functions.php del hijo dentro de una condición que sólo lo deja correr con el tema DIGITALÍSIMO activo: con el hijo
 * activo el fragmento no hace nada (sus funciones ni se declaran), así que puede revisarse y activarse en Tools antes de
 * cambiar de tema, sigue corriendo en cuanto el hijo deja de cargarse y se apaga solo si se vuelve al hijo. '' si el
 * archivo no admite ir dentro de un bloque (namespace, use, declare, const de primer nivel o __halt_compiler).
 */
function digitalisimo_theme_child_wrap_php( $code, $name ) {
	$code = (string) $code;
	if ( ! preg_match( '/^<\?php\s/', $code ) || ! function_exists( 'token_get_all' ) ) {
		return '';
	}
	$depth = 0;
	$last  = null;
	foreach ( token_get_all( $code ) as $token ) {
		$type = is_array( $token ) ? $token[0] : $token;
		if ( in_array( $type, array( '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
			++$depth;
		} elseif ( '}' === $type ) {
			--$depth;
		}
		if ( 0 === $depth && in_array( $type, array( T_NAMESPACE, T_USE, T_DECLARE, T_CONST, T_HALT_COMPILER ), true ) ) {
			return '';
		}
		if ( ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$last = $type;
		}
	}
	$body  = (string) preg_replace( '/^<\?php\s/', '', $code, 1 );
	$label = str_replace( array( '*/', '?>', '<?', "\r", "\n" ), '', (string) $name );
	return "<?php\n// functions.php del tema hijo «" . $label . "», tal cual, dentro de esta condición: sólo corre con el tema DIGITALÍSIMO\n"
		. "// activo. Con el hijo activo no hace nada, así que puede activarse antes de cambiar de tema y se apaga si se vuelve al hijo.\n"
		. "if ( function_exists( 'digitalisimo_theme_parity' ) ) {\n" . rtrim( $body ) . "\n"
		. ( in_array( $last, array( T_INLINE_HTML, T_CLOSE_TAG ), true ) ? "<?php\n}\n" : "}\n" );
}

/**
 * Deja el functions.php del hijo en Código PHP de Tools, inactivo y con revisión obligatoria (Tools lo impone), para que
 * el administrador lo revise y lo active. Sólo con bloqueos en ese archivo y con Tools activo. Devuelve lo que contestó
 * Tools (`created`, `unchanged`, `updated`, `conflict`, `skipped`) o '' si no se importó.
 */
function digitalisimo_theme_child_import_php( WP_Theme $child, array $plan ) {
	$php = array();
	foreach ( (array) ( $plan['blockers'] ?? array() ) as $blocker ) {
		if ( 'functions.php' === ( $blocker['file'] ?? '' ) ) {
			$php[] = digitalisimo_theme_child_blocker_text( $blocker );
		}
	}
	$code = digitalisimo_theme_child_php_code( $child );
	if ( ! $php || ! empty( $plan['risks'] ) || '' === $code || "\0" === $code || ! preg_match( '/^<\?php\s/', $code ) || ! has_filter( 'digitalisimo_tools_php_import' ) ) {
		return '';
	}
	// Con la condición del tema propio, el fragmento puede activarse antes del cambio sin chocar con el hijo.
	$wrap = digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ) );
	$code = '' !== $wrap ? $wrap : $code;
	$notes  = array_slice( array_merge( array( 'Lo que el tema DIGITALÍSIMO no trae solo: ' . implode( '; ', array_slice( $php, 0, 10 ) ) . '.' ), '' !== $wrap ? array( 'Va dentro de una condición que sólo lo deja correr con el tema DIGITALÍSIMO activo: puedes activarlo antes de cambiar de tema (con el hijo activo no hace nada) y se apaga solo si vuelves al hijo.' ) : array( 'Actívalo sólo después de cambiar al tema DIGITALÍSIMO: con el hijo activo chocaría con su functions.php.' ), $plan['assets'] ? array( 'Las hojas y scripts que encolaba ya los carga el tema DIGITALÍSIMO desde la carpeta del hijo.' ) : array() ), 0, 5 );
	$result = apply_filters(
		'digitalisimo_tools_php_import',
		null,
		array(
			array(
				'title'    => 'PHP del tema hijo ' . (string) $child->get( 'Name' ),
				'code'     => $code,
				'original' => $code,
				'origin'   => array(
					'source'             => 'digitalisimo-theme',
					'id'                 => (string) $child->get_stylesheet(),
					'version'            => (string) $child->get( 'Version' ),
					'site'               => home_url( '/' ),
					'hash'               => 'child-' . hash( 'sha256', $code ),
					'executed_in_origin' => true,
					'reason'             => 'Era el functions.php del tema hijo; el tema DIGITALÍSIMO no carga el PHP de otro tema.',
				),
				'notes'    => $notes,
			),
		),
		array( 'from' => 'digitalisimo-theme' )
	);
	return is_array( $result ) && $result ? (string) reset( $result ) : '';
}

/** Un bloqueo en una línea de texto: «functions.php, línea 12: add_action( 'wp_head' )». */
function digitalisimo_theme_child_blocker_text( array $blocker ) {
	$file = (string) ( $blocker['file'] ?? '' );
	$line = (int) ( $blocker['line'] ?? 0 );
	return ( '' !== $file ? $file . ( $line ? ', línea ' . $line : '' ) . ': ' : '' ) . (string) ( $blocker['what'] ?? '' );
}

/** Los bloqueos en una frase (como mucho $max, con «y N más»). */
function digitalisimo_theme_child_blockers_text( array $blockers, $max = 6 ) {
	$texts = array_map( 'digitalisimo_theme_child_blocker_text', array_slice( $blockers, 0, $max ) );
	$more  = count( $blockers ) - count( $texts );
	return implode( '; ', $texts ) . ( $more > 0 ? sprintf( '; y %d más', $more ) : '' );
}
