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
	$plan = array(
		'v'        => 1, // Formato del plan y de la opción `digitalisimo_theme_child`.
		'child'    => (string) $child->get_stylesheet(),
		'parent'   => (string) $child->get_template(),
		'hash'     => '',
		'assets'   => array(),
		'blockers' => array(),
		'risks'    => array(), // Lo que cambiaría de sentido si su functions.php corre con el tema propio (ver _risks()).
		'refs'     => array(), // Archivos del hijo a los que apuntan sus rutas reescritas en Código PHP (`?`: no se sabe cuál).
	);
	$code = digitalisimo_theme_child_php_code( $child );
	if ( '' !== $code ) {
		$plan['hash'] = hash( 'sha256', $code );
		$found        = digitalisimo_theme_child_functions_plan( $code, digitalisimo_theme_child_ctx( $child ) );
		$plan['assets']   = $found['assets'];
		$plan['blockers'] = $found['blockers'];
		$plan['risks']    = $found['risks'];
		$plan['refs']     = $found['refs'];
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

/**
 * Tokens sin espacios ni comentarios, como [tipo, texto, línea]; null si no es PHP o lleva HTML fuera de PHP. En $raw, el
 * índice de cada uno en token_get_all().
 */
function digitalisimo_theme_child_tokens( $code, &$raw = null ) {
	$raw = array(); // Índice de cada token devuelto en token_get_all() (para reescribir el código con sus espacios).
	if ( ! function_exists( 'token_get_all' ) || 0 !== strpos( ltrim( (string) $code ), '<?php' ) ) {
		return null;
	}
	$out  = array();
	$line = 1;
	foreach ( token_get_all( (string) $code ) as $at => $token ) {
		$count = count( $out );
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
		if ( count( $out ) > $count ) {
			$raw[] = $at;
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
 * `blockers` (archivo, línea y qué es). En $clean, los tramos de tokens que el tema ya trae solo.
 */
function digitalisimo_theme_child_functions_plan( $code, array $ctx = array(), &$clean = null ) {
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
	$out   = array( 'assets' => array(), 'blockers' => array(), 'risks' => array(), 'refs' => array() );
	$clean = array();
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
	// $clean: tramos de tokens que el tema trae solo (sin bloqueos): ahí las rutas del tema no importan.
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
	foreach ( digitalisimo_theme_child_rewrites( $t ) as $at => $rewrite ) {
		$carried = false; // En lo que el tema ya trae solo, el archivo es un encolado de la lista.
		foreach ( $clean as $range ) {
			$carried = $carried || ( $at >= $range[0] && $at < $range[1] );
		}
		if ( '' !== $rewrite[1] && ! $carried ) {
			$out['refs'][ $rewrite[1] ] = $rewrite[1];
		}
	}
	$out['refs'] = array_values( $out['refs'] );
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
	$add   = static function ( $line, $what, $name = '' ) use ( &$risks ) {
		$risks[ $what ] = array( 'file' => 'functions.php', 'line' => (int) $line, 'what' => (string) $what, 'name' => (string) $name );
	};
	// Lo que el tema cubre (digitalisimo_theme_child_compat_covers()): las constantes y funciones de Hello que declara en
	// paridad (dentro de una función: en el primer nivel del hijo tampoco existían con Hello) y las rutas del hijo y
	// __DIR__/__FILE__ que la envoltura del fragmento reescribe (digitalisimo_theme_child_rewrites()).
	$rewrites = null === digitalisimo_theme_child_wrap_plan( $t ) ? array() : digitalisimo_theme_child_rewrites( $t ); // Sin envoltura no se reescribe nada.
	// Lo que lee el tema activo (con el tema propio, el propio y no el hijo): plantillas y datos del tema. Los ajustes del
	// Personalizador (get_theme_mod) no: el paso los trae al tema propio. Fuera de lo ya traído y dentro de lo ya traído:
	// lo traído sólo tiene valores legibles, nunca estas llamadas.
	$theme = '/^(get_template_part|locate_template|load_template|get_stylesheet|get_template|wp_get_theme|is_child_theme|get_theme_root(_uri)?)$/i';
	for ( $i = 0; $i < $n; $i++ ) {
		list( $v, $text, $line ) = $t[ $i ];
		$prev      = $i > 0 ? $t[ $i - 1 ][0] : null;
		$call      = isset( $t[ $i + 1 ] ) && '(' === $t[ $i + 1 ][0];
		$qualified = false;
		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $v && 1 === substr_count( $text, '\\' ) ) {
			list( $v, $text, $qualified ) = array( T_STRING, ltrim( $text, '\\' ), true ); // `\NOMBRE` (PHP 8).
		} elseif ( T_STRING === $v && T_NS_SEPARATOR === $prev && ( $i < 2 || ! in_array( $t[ $i - 2 ][0], array( T_STRING, T_NAMESPACE ), true ) ) ) {
			list( $qualified, $prev ) = array( true, $i >= 2 ? $t[ $i - 2 ][0] : null ); // `\NOMBRE` (PHP 7).
		}
		if ( ( T_DIR === $v || T_FILE === $v ) && ! isset( $rewrites[ $i ] ) ) {
			$add( $line, sprintf( 'usa %s donde no puede cambiarse por la carpeta del hijo (en una constante, un valor por defecto o una propiedad)', $text ), $text );
		} elseif ( T_VARIABLE === $v && '$GLOBALS' === $text ) {
			$add( $line, 'usa $GLOBALS (en Código PHP cada fragmento tiene su propio ámbito)', '$GLOBALS' );
		} elseif ( T_STRING === $v && ! in_array( $prev, $names, true ) ) {
			$lower = strtolower( $text );
			$next  = isset( $t[ $i + 1 ] ) ? $t[ $i + 1 ][0] : null;
			$covered = digitalisimo_theme_child_compat_covers( array( 'name' => $text . ( $call ? '()' : '' ), 'top' => ! $in_body[ $i ] ) );
			if ( ! $call && preg_match( '/^(HELLO|EHP)_\w+$/', $text ) && ! isset( $defined[ $text ] ) ) {
				if ( ! $covered ) {
					$add( $line, sprintf( $in_body[ $i ] ? 'usa la constante %s de Hello, que con el tema DIGITALÍSIMO no existe' : 'usa la constante %s de Hello en el primer nivel, donde todavía no existe', $text ), $text );
				}
			} elseif ( ! $call && $qualified && T_DOUBLE_COLON !== $next && preg_match( '/^[A-Z][A-Z0-9_]*$/', $text ) ) {
				$add( $line, sprintf( 'usa la constante \\%s, que con el tema DIGITALÍSIMO puede no existir', $text ), $text );
			} elseif ( $call && 0 === strpos( $lower, 'hello_' ) && ! isset( $funcs[ $lower ] ) ) {
				if ( ! $covered ) {
					$add( $line, sprintf( $in_body[ $i ] ? 'llama a %s(), de Hello, que con el tema DIGITALÍSIMO no existe' : 'llama a %s(), de Hello, en el primer nivel, donde todavía no existe', $text ), $text . '()' );
				}
			} elseif ( $call && preg_match( $theme, $text ) ) {
				$add( $line, sprintf( 'usa %s(), que con el tema DIGITALÍSIMO lee el tema propio y no el hijo', $text ), $text . '()' );
			} elseif ( $call && empty( $skip[ $i ] ) && ! isset( $rewrites[ $i ] ) && preg_match( $paths, $text ) ) {
				$add( $line, sprintf( 'usa %s(), que con el tema DIGITALÍSIMO apunta a su carpeta y no a la del hijo', $text ), $text . '()' );
			} elseif ( $call && ! $in_body[ $i ] && isset( $funcs[ $lower ] ) && $funcs[ $lower ] > $i ) {
				$add( $line, sprintf( 'llama a %s() antes de declararla (dentro de la condición todavía no existiría)', $text ), $text . '()' );
			}
		} elseif ( T_STRING === $v && T_NEW === $prev && ! $in_body[ $i ] && isset( $classes[ strtolower( $text ) ] ) && $classes[ strtolower( $text ) ] > $i ) {
			$add( $line, sprintf( 'crea un %s antes de declarar la clase (dentro de la condición todavía no existiría)', $text ), $text );
		} elseif ( T_GLOBAL === $v && $in_body[ $i ] ) {
			for ( $k = $i + 1; $k < $n && ';' !== $t[ $k ][0]; $k++ ) {
				if ( T_VARIABLE === $t[ $k ][0] && isset( $top[ $t[ $k ][1] ] ) ) {
					$add( $t[ $k ][2], sprintf( 'usa global %s, una variable de primer nivel del archivo (en Código PHP cada fragmento tiene su propio ámbito)', $t[ $k ][1] ), $t[ $k ][1] );
				}
			}
		}
	}
	foreach ( $extends as $row ) {
		if ( isset( $classes[ strtolower( $row[1] ) ] ) && $classes[ strtolower( $row[1] ) ] > $row[0] ) {
			$add( $t[ $row[0] ][2], sprintf( 'la clase %s extiende %s, declarada más abajo (dentro de la condición todavía no existiría)', $row[2], $row[1] ), $row[2] );
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

/* ------------------------------------------------------------------ Compatibilidad con Hello (inc/hello-compat.php) */

/**
 * Lo que el tema cubre del PHP de un hijo de Hello en Código PHP de Tools: las constantes y funciones de Hello 3.5.1 que
 * declara en paridad (inc/hello-compat.php, en `after_setup_theme` tras Código PHP), las rutas del hijo que la envoltura
 * reescribe y los ajustes del Personalizador, que el paso trae al tema propio.
 */
function digitalisimo_theme_child_compat_names() {
	return array(
		// Sólo la versión: las rutas de Hello (HELLO_THEME_PATH…) apuntarían a archivos que el tema no tiene y EHP_THEME_SLUG
		// la usa Hello Plus para saber si hay un tema Hello: quedan como riesgo (y el hijo y Hello se quedan).
		'constants' => array( 'HELLO_ELEMENTOR_VERSION' ),
		'functions' => array( 'hello_elementor_get_setting', 'hello_show_or_hide', 'hello_get_header_layout_class', 'hello_get_footer_layout_class', 'hello_get_header_display', 'hello_get_footer_display', 'hello_header_footer_experiment_active', 'hello_elementor_display_header_footer', 'hello_elementor_check_hide_title', 'hello_elementor_body_open', 'hello_elementor_content_width', 'hello_elementor_register_elementor_locations', 'hello_elementor_setup', 'hello_elementor_scripts_styles', 'hello_elementor_add_description_meta_tag', 'hello_elementor_customizer' ),
		'paths'     => array( 'get_stylesheet_directory', 'get_stylesheet_directory_uri', 'get_stylesheet_uri', 'get_theme_file_uri', 'get_theme_file_path' ),
		'theme'     => array( 'get_theme_mod', 'get_theme_mods' ),
	);
}

/**
 * ¿Cubre el tema este riesgo del lector? Así Migración deja de bloquear por él. `$risk` es una fila de riesgos (con
 * `name` y, si se sabe, `top`: usado en el primer nivel del archivo) o el nombre: `HELLO_ELEMENTOR_VERSION`,
 * `hello_elementor_get_setting()`, `get_stylesheet_directory_uri()`. Una constante o función de Hello en el primer nivel
 * no se cubre (se declaran después de Código PHP, como con Hello, que se cargaba después del hijo). `__DIR__` y
 * `__FILE__` sólo con `rewritable` (la envoltura los reescribe salvo en constantes, valores por defecto y propiedades).
 */
function digitalisimo_theme_child_compat_covers( $risk ) {
	$name  = is_array( $risk ) ? (string) ( $risk['name'] ?? '' ) : (string) $risk;
	$top   = is_array( $risk ) && ! empty( $risk['top'] );
	$name  = ltrim( trim( $name ), '\\' );
	$call  = '()' === substr( $name, -2 );
	$base  = $call ? substr( $name, 0, -2 ) : $name;
	$names = digitalisimo_theme_child_compat_names();
	if ( in_array( $base, array( '__DIR__', '__FILE__' ), true ) ) {
		return is_array( $risk ) && ! empty( $risk['rewritable'] );
	}
	if ( ! $call && in_array( $base, $names['constants'], true ) ) {
		return ! $top;
	}
	if ( $call && in_array( strtolower( $base ), $names['functions'], true ) ) {
		return ! $top;
	}
	return $call && ( in_array( strtolower( $base ), $names['paths'], true ) || in_array( strtolower( $base ), $names['theme'], true ) );
}

/**
 * Lo que la envoltura del fragmento reescribe para que apunte al hijo (o a su copia) y no al tema propio:
 * `get_stylesheet_directory(_uri)()`, `get_stylesheet_uri()`, `get_theme_file_(uri|path)()`, y `__DIR__`/`__FILE__`
 * donde una llamada es válida (no en `const`, `static`, valores por defecto ni propiedades). Pura: recibe los tokens del
 * lector y devuelve [índice => [kind, ref]], con `ref` el archivo del hijo al que apunta (o `?` si no se puede leer).
 */
function digitalisimo_theme_child_rewrites( array $t ) {
	$map     = array( 'get_stylesheet_directory' => 'dir', 'get_stylesheet_directory_uri' => 'dir_uri', 'get_stylesheet_uri' => 'style', 'get_theme_file_uri' => 'file_uri', 'get_theme_file_path' => 'file_path' );
	$names   = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_NEW, T_NS_SEPARATOR );
	$fn      = defined( 'T_FN' ) ? T_FN : -1;
	$out     = array();
	$stack   = array();
	$pending = '';
	$want    = false;
	$params  = -1;
	if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
		$names[] = T_NULLSAFE_OBJECT_OPERATOR;
	}
	// El archivo al que apunta una ruta: `ruta() . '/archivo'` (el literal termina en una comilla, `?`, `#`, un espacio o
	// sin más concatenación detrás). Sin literal o con una carpeta: `?` (no se sabe qué archivo usa).
	$suffix = static function ( $after ) use ( $t ) {
		if ( isset( $t[ $after ], $t[ $after + 1 ] ) && '.' === $t[ $after ][0] && T_CONSTANT_ENCAPSED_STRING === $t[ $after + 1 ][0] ) {
			$text = ltrim( stripcslashes( substr( $t[ $after + 1 ][1], 1, -1 ) ), '/' );
			$cut  = strcspn( $text, "\"'?# <>)" );
			$path = substr( $text, 0, $cut );
			$open = strlen( $text ) === $cut && '.' === ( $t[ $after + 2 ][0] ?? null );
			return $open || '' === $path || '/' === substr( $path, -1 ) ? '?' : $path;
		}
		return '?';
	};
	// ¿Va en una expresión constante (`static $x = …`, `const X = …`, también tras `if (): ` sin llaves)? Ahí una llamada no
	// es válida: no se reescribe (y sigue siendo riesgo).
	$constant = static function ( $at ) use ( $t ) {
		$depth = 0;
		for ( $k = $at - 1; $k >= 0; $k-- ) {
			$v = $t[ $k ][0];
			if ( ')' === $v || ']' === $v ) {
				++$depth;
			} elseif ( ( '(' === $v || '[' === $v ) && $depth ) {
				--$depth;
			} elseif ( ! $depth && in_array( $v, array( ';', '{', '}' ), true ) ) {
				return false;
			}
			if ( T_CONST === $v || ( T_STATIC === $v && T_VARIABLE === ( $t[ $k + 1 ][0] ?? null ) ) ) {
				return true;
			}
		}
		return false;
	};
	// __FILE__: `dirname( __FILE__ ) . '/x'` es como __DIR__; `basename( __FILE__ )` no usa ningún archivo; lo demás
	// (plugin_dir_url( __FILE__ ), filemtime( __FILE__ )…) no se sabe: `?`.
	$file_ref = static function ( $at ) use ( $t, $suffix ) {
		$fn = $at >= 2 && '(' === $t[ $at - 1 ][0] && T_STRING === $t[ $at - 2 ][0] && ')' === ( $t[ $at + 1 ][0] ?? null ) ? strtolower( $t[ $at - 2 ][1] ) : '';
		return 'dirname' === $fn ? $suffix( $at + 2 ) : ( 'basename' === $fn ? '' : '?' );
	};
	for ( $i = 0, $n = count( $t ); $i < $n; $i++ ) {
		list( $v, $text ) = $t[ $i ];
		$prev             = $i ? $t[ $i - 1 ][0] : null;
		if ( ( T_FUNCTION === $v || $fn === $v ) && T_DOUBLE_COLON !== $prev && T_OBJECT_OPERATOR !== $prev ) {
			list( $pending, $want ) = array( 'func', true );
		} elseif ( in_array( $v, array( T_CLASS, T_TRAIT, T_INTERFACE ), true ) && T_DOUBLE_COLON !== $prev ) {
			$pending = 'class';
		} elseif ( '(' === $v && $want ) {
			list( $params, $want ) = array( max( $params, digitalisimo_theme_child_close( $t, $i ) ), false );
		} elseif ( in_array( $v, array( '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
			$stack[] = '{' === $v && '' !== $pending ? $pending : 'block';
			$pending = '';
		} elseif ( '}' === $v ) {
			array_pop( $stack );
		} elseif ( ';' === $v ) {
			$pending = '';
		} elseif ( T_DIR === $v || T_FILE === $v ) {
			if ( $i > $params && 'class' !== end( $stack ) && ! $constant( $i ) ) {
				$out[ $i ] = T_DIR === $v ? array( 'dir', $suffix( $i + 1 ) ) : array( 'file', $file_ref( $i ) );
			}
		} else {
			$fq   = defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $v && 1 === substr_count( $text, '\\' );
			$name = strtolower( ltrim( (string) $text, '\\' ) );
			$ns   = T_NS_SEPARATOR === $prev && $i >= 2 && in_array( $t[ $i - 2 ][0], array( T_STRING, T_NAMESPACE ), true );
			if ( ( T_STRING === $v || $fq ) && isset( $map[ $name ], $t[ $i + 1 ] ) && '(' === $t[ $i + 1 ][0] && ! $ns && ( ! in_array( $prev, $names, true ) || T_NS_SEPARATOR === $prev ) ) {
				$close = digitalisimo_theme_child_close( $t, $i + 1 );
				$kind  = $map[ $name ];
				if ( $close === $i + 3 && T_ELLIPSIS === $t[ $i + 2 ][0] ) {
					continue; // `get_stylesheet_directory_uri(...)` (llamable de PHP 8.1): no se reescribe; sigue siendo riesgo.
				}
				$ref   = 'style' === $kind ? 'style.css' : '';
				if ( 'file_uri' === $kind || 'file_path' === $kind ) {
					$args = digitalisimo_theme_child_args( $t, $i + 1, $close );
					$ref  = ! $args ? '?' : ( 1 === count( $args ) && 1 === count( $args[0] ) && T_CONSTANT_ENCAPSED_STRING === $args[0][0][0] ? ltrim( stripcslashes( substr( $args[0][0][1], 1, -1 ) ), '/' ) : '?' );
				} elseif ( 'dir' === $kind || 'dir_uri' === $kind ) {
					$ref = $suffix( $close + 1 );
				}
				$out[ $i ] = array( $kind, $ref );
			}
		}
	}
	return $out;
}

/** Las rutas que se guardan del hijo: como mucho 50; con más, la última es `?` (la copia queda incompleta). */
function digitalisimo_theme_child_saved_refs( array $refs ) {
	return count( $refs ) > 50 ? array_merge( array_slice( array_values( $refs ), 0, 49 ), array( '?' ) ) : array_values( $refs );
}

/** Nombre de carpeta de tema válido para la copia. */
function digitalisimo_theme_child_slug_ok( $slug ) {
	return is_string( $slug ) && preg_match( '/^[A-Za-z0-9_\-][A-Za-z0-9_.\-]{0,99}$/D', $slug ); // Sin «/»: nunca sale de la carpeta.
}

/** Carpeta (o dirección, con $url) de la copia de un tema en uploads: uploads/digitalisimo-theme/child/<tema>. */
function digitalisimo_theme_child_copy_dir( $slug = '', $url = false ) {
	$uploads = wp_upload_dir( null, false );
	if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || ( '' !== $slug && ! digitalisimo_theme_child_slug_ok( $slug ) ) ) {
		return '';
	}
	$base = untrailingslashit( (string) ( $url ? $uploads['baseurl'] : $uploads['basedir'] ) ) . '/digitalisimo-theme/child';
	return '' === $slug ? $base : $base . '/' . $slug;
}

/** ¿La copia de los archivos de este hijo está completa (digitalisimo_theme_child_copy_assets())? */
function digitalisimo_theme_child_copy_ready( $slug ) {
	$saved = get_option( 'digitalisimo_theme_child' );
	return is_array( $saved ) && ! empty( $saved['copied'] ) && (string) ( $saved['child'] ?? '' ) === (string) $slug;
}

/** ¿La copia de un archivo está al día con su original (si el original sigue ahí, no es más nuevo que la copia)? */
function digitalisimo_theme_child_copy_fresh( $copy, $source ) {
	return is_file( $copy ) && ( ! file_exists( $source ) || filemtime( $source ) <= filemtime( $copy ) );
}

/**
 * Dónde vive un archivo del hijo (o, con $file '', su carpeta): en la copia si está completa, si no en la carpeta del
 * hijo; un archivo que el hijo no tiene se busca en Hello (como get_theme_file_uri()). [ruta, dirección].
 */
function digitalisimo_theme_child_locate( $slug, $file = '' ) {
	$slug = (string) $slug;
	$file = ltrim( (string) $file, '/' );
	if ( ! digitalisimo_theme_child_slug_ok( $slug ) || false !== strpos( $file, '..' ) ) {
		return array( '', '' );
	}
	$saved  = get_option( 'digitalisimo_theme_child' );
	$parent = is_array( $saved ) && digitalisimo_theme_child_slug_ok( $saved['parent'] ?? '' ) ? (string) $saved['parent'] : 'hello-elementor';
	$ready  = digitalisimo_theme_child_copy_ready( $slug );
	$places = array();
	foreach ( array( $slug, $parent ) as $theme ) {
		$source = get_theme_root( $theme ) . '/' . $theme;
		// La copia sólo si está al día: la carpeta, sólo sin el hijo; un archivo, si el del hijo no es más nuevo.
		if ( $ready && ( '' === $file ? ! is_dir( $source ) : digitalisimo_theme_child_copy_fresh( digitalisimo_theme_child_copy_dir( $theme ) . '/' . $file, $source . '/' . $file ) ) ) {
			$places[] = array( digitalisimo_theme_child_copy_dir( $theme ), digitalisimo_theme_child_copy_dir( $theme, true ) );
		}
		$places[] = array( $source, get_theme_root_uri( $theme ) . '/' . $theme );
		if ( '' === $file ) {
			break;
		}
	}
	foreach ( $places as $place ) {
		if ( '' !== $place[0] && ( '' === $file ? is_dir( $place[0] ) : is_file( $place[0] . '/' . $file ) ) ) {
			return array( $place[0] . ( '' !== $file ? '/' . $file : '' ), $place[1] . ( '' !== $file ? '/' . $file : '' ) );
		}
	}
	return array( $places[0][0] . ( '' !== $file ? '/' . $file : '' ), $places[0][1] . ( '' !== $file ? '/' . $file : '' ) );
}

/** get_stylesheet_directory() del hijo en su fragmento de Código PHP (la envoltura lo reescribe así). */
function digitalisimo_theme_child_dir( $slug ) {
	return digitalisimo_theme_child_locate( $slug )[0];
}

/** get_stylesheet_directory_uri() del hijo en su fragmento. */
function digitalisimo_theme_child_dir_uri( $slug ) {
	return digitalisimo_theme_child_locate( $slug )[1];
}

/** get_theme_file_uri() (y get_stylesheet_uri(), con style.css) del hijo en su fragmento. */
function digitalisimo_theme_child_file_uri( $slug, $file = '' ) {
	return digitalisimo_theme_child_locate( $slug, $file )[1];
}

/** get_theme_file_path() (y __FILE__, con functions.php) del hijo en su fragmento. */
function digitalisimo_theme_child_file_path( $slug, $file = '' ) {
	return digitalisimo_theme_child_locate( $slug, $file )[0];
}

/* ------------------------------------------------------------------ Copia de los archivos del hijo en uploads */

/** Extensiones que se copian (nunca PHP ni nada ejecutable en el servidor). */
function digitalisimo_theme_child_copy_ext( $path ) {
	return (bool) preg_match( '/\.(css|js|mjs|map|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico|bmp|json)$/iD', (string) $path );
}

/** Ruta relativa normalizada dentro del tema, o null si sale de él o no es válida. */
function digitalisimo_theme_child_norm( $path ) {
	$out = array();
	foreach ( explode( '/', str_replace( '\\', '/', (string) $path ) ) as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}
		if ( '..' === $part ) {
			if ( ! $out ) {
				return null;
			}
			array_pop( $out );
			continue;
		}
		if ( ! preg_match( '/^[A-Za-z0-9_.\-@+~ ]+$/D', $part ) ) {
			return null;
		}
		$out[] = $part;
	}
	return $out ? implode( '/', $out ) : null;
}

/**
 * Lo que hay que copiar: lo que el hijo encola desde su carpeta o la de Hello, los archivos a los que apuntan sus rutas
 * reescritas y, dentro de cada hoja, sus `url()` e `@import` relativos (recursivo). [archivos por tema, problemas].
 */
function digitalisimo_theme_child_copy_list( array $saved ) {
	$child   = (string) ( $saved['child'] ?? '' );
	$parent  = (string) ( $saved['parent'] ?? '' );
	$files   = array();
	$issues  = array();
	$queue   = array();
	$theme_dir = static function ( $theme ) {
		return get_theme_root( $theme ) . '/' . $theme;
	};
	foreach ( (array) ( $saved['assets'] ?? array() ) as $asset ) {
		$where = (string) ( $asset['w'] ?? '' );
		if ( in_array( $where, array( 'child', 'parent' ), true ) && '' !== (string) ( $asset['s'] ?? '' ) ) {
			$queue[] = array( 'parent' === $where ? $parent : $child, (string) $asset['s'], 'encolado' );
		}
	}
	foreach ( (array) ( $saved['refs'] ?? array() ) as $ref ) {
		if ( '?' === $ref ) {
			$issues[] = 'una ruta del hijo que no se puede leer sin ejecutar su PHP';
			continue;
		}
		$rel_ref = ltrim( (string) $ref, '/' ); // En el hijo, salvo que sólo esté en Hello (como get_theme_file_uri()).
		$in      = ! digitalisimo_theme_child_slug_ok( $parent ) || file_exists( $theme_dir( $child ) . '/' . $rel_ref ) || ! file_exists( $theme_dir( $parent ) . '/' . $rel_ref ) ? $child : $parent;
		$queue[] = array( $in, (string) $ref, 'ruta' );
	}
	$seen = 0;
	while ( $queue && $seen < 500 ) {
		list( $theme, $path, $why ) = array_shift( $queue );
		++$seen;
		$rel = digitalisimo_theme_child_norm( $path );
		if ( ! digitalisimo_theme_child_slug_ok( $theme ) || null === $rel ) {
			$issues[] = sprintf( '%s fuera de la carpeta del tema', $path );
			continue;
		}
		if ( isset( $files[ $theme ][ $rel ] ) ) {
			continue;
		}
		$abs = $theme_dir( $theme ) . '/' . $rel;
		if ( is_link( $abs ) || ( is_file( $abs ) && 0 !== strpos( (string) realpath( $abs ), (string) realpath( $theme_dir( $theme ) ) . DIRECTORY_SEPARATOR ) ) ) {
			$issues[] = sprintf( '%s/%s es un enlace simbólico o sale de la carpeta del tema: no se copia', $theme, $rel );
			continue;
		}
		if ( is_dir( $abs ) ) {
			$issues[] = sprintf( '%s/%s es una carpeta: no se sabe qué archivos usa', $theme, $rel );
			continue;
		}
		if ( ! is_file( $abs ) ) {
			// Una hoja encolada que falta ya estaba rota en el hijo (o el tema se borró: lo dice la comprobación de la copia).
			// Una ruta de su PHP a un archivo que no está en un tema que sí está: no se sabe qué usa.
			if ( 'ruta' === $why && is_dir( $theme_dir( $theme ) ) ) {
				$issues[] = sprintf( '%s/%s no existe: no se sabe qué usa', $theme, $rel );
			}
			continue;
		}
		if ( ! digitalisimo_theme_child_copy_ext( $rel ) ) {
			$issues[] = sprintf( '%s/%s no es un archivo estático que pueda copiarse', $theme, $rel );
			continue;
		}
		$files[ $theme ][ $rel ] = $abs;
		if ( ! preg_match( '/\.css$/iD', $rel ) ) {
			continue;
		}
		if ( filesize( $abs ) > 4 * MB_IN_BYTES ) {
			$issues[] = sprintf( '%s/%s pesa más de 4 MB: no se leen sus url()', $theme, $rel );
			continue;
		}
		$css = (string) file_get_contents( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		preg_match_all( '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)|@import\s+([\'"])([^\'"]+)\3/i', $css, $found, PREG_SET_ORDER );
		$dir = dirname( $rel );
		foreach ( $found as $match ) {
			$ref = trim( '' !== ( $match[2] ?? '' ) ? $match[2] : (string) ( $match[4] ?? '' ) );
			if ( '' === $ref || preg_match( '#^([a-z][a-z0-9+.\-]*:|//|\#|/)#i', $ref ) ) {
				continue; // data:, http(s):, la raíz del sitio o un ancla: no dependen de la carpeta del tema.
			}
			$ref  = rawurldecode( (string) preg_replace( '/[?#].*$/', '', $ref ) );
			$next = digitalisimo_theme_child_norm( ( '.' === $dir ? '' : $dir . '/' ) . $ref );
			if ( null === $next ) {
				$issues[] = sprintf( '%s/%s apunta fuera de la carpeta del tema (%s)', $theme, $rel, $ref );
				continue;
			}
			$queue[] = array( $theme, $next, 'css' );
		}
	}
	if ( $queue ) {
		$issues[] = 'demasiados archivos encadenados';
	}
	return array( $files, array_values( array_unique( $issues ) ) );
}

/** Borra una carpeta de la copia (sólo dentro de uploads/digitalisimo-theme/child). */
function digitalisimo_theme_child_rmdir( $dir ) {
	$base = digitalisimo_theme_child_copy_dir();
	if ( '' === $base || 0 !== strpos( (string) $dir, $base . '/' ) || ! is_dir( $dir ) || is_link( $dir ) ) {
		return;
	}
	foreach ( (array) scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$path = $dir . '/' . $entry;
		is_dir( $path ) && ! is_link( $path ) ? digitalisimo_theme_child_rmdir( $path ) : @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Copia a uploads/digitalisimo-theme/child/<tema>/ lo que el hijo encolaba (y Hello, si el hijo encolaba algo suyo),
 * con sus fuentes e imágenes relativas, para que el tema lo sirva de ahí y el hijo y Hello puedan borrarse. Idempotente:
 * si la copia ya coincide (sha256 de cada archivo) no escribe; si el hijo cambió, la rehace en una carpeta temporal y la
 * cambia de una vez. Nada se sirve de la copia hasta que está completa (`copied` en la opción del hijo). Devuelve
 * `status` (`none`, `unchanged`, `copied`, `incomplete`), `files` y `issues`.
 */
function digitalisimo_theme_child_copy_assets() {
	$saved = get_option( 'digitalisimo_theme_child' );
	if ( ! is_array( $saved ) || ! digitalisimo_theme_child_slug_ok( $saved['child'] ?? '' ) ) {
		return array( 'status' => 'none', 'files' => 0, 'issues' => array() );
	}
	list( $files, $issues ) = digitalisimo_theme_child_copy_list( $saved );
	$manifest               = get_option( 'digitalisimo_theme_child_copy' );
	$manifest               = is_array( $manifest ) && ( $manifest['child'] ?? '' ) === $saved['child'] ? $manifest : array( 'v' => 1, 'child' => $saved['child'], 'files' => array() );
	$base                   = digitalisimo_theme_child_copy_dir();
	$changed                = false;
	if ( '' === $base || ! wp_mkdir_p( $base ) ) {
		$issues[] = 'no se pudo crear la carpeta de la copia en uploads';
		$files    = array();
	}
	foreach ( $files as $theme => $list ) {
		$want = array();
		foreach ( $list as $rel => $abs ) {
			$want[ $rel ] = (string) hash_file( 'sha256', $abs );
		}
		ksort( $want );
		$final = $base . '/' . $theme;
		if ( ( $manifest['files'][ $theme ] ?? null ) === $want && digitalisimo_theme_child_copy_intact( $final, $want ) ) {
			continue;
		}
		$tmp = $base . '/.tmp-' . $theme . '-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$ok  = wp_mkdir_p( $tmp );
		foreach ( $list as $rel => $abs ) {
			$ok = $ok && wp_mkdir_p( dirname( $tmp . '/' . $rel ) ) && @copy( $abs, $tmp . '/' . $rel ) && hash_file( 'sha256', $tmp . '/' . $rel ) === $want[ $rel ]; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$old = $base . '/.old-' . $theme . '-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		if ( $ok && is_dir( $final ) ) {
			$ok = @rename( $final, $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( $ok && ! @rename( $tmp, $final ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@rename( $old, $final ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$ok = false;
		}
		digitalisimo_theme_child_rmdir( $tmp );
		digitalisimo_theme_child_rmdir( $old );
		if ( ! $ok ) {
			$issues[] = sprintf( 'no se pudo copiar %s a uploads', $theme );
			unset( $manifest['files'][ $theme ] );
			continue;
		}
		$manifest['files'][ $theme ] = $want;
		$changed                     = true;
	}
	// Un tema borrado (el hijo o Hello) no se vuelve a leer: vale su copia si sigue intacta.
	foreach ( (array) $manifest['files'] as $theme => $want ) {
		if ( ! isset( $files[ $theme ] ) && ! is_dir( get_theme_root( $theme ) . '/' . $theme ) && ! digitalisimo_theme_child_copy_intact( $base . '/' . $theme, (array) $want ) ) {
			$issues[] = sprintf( 'la copia de %s está incompleta y el tema ya no está', $theme );
		}
	}
	$manifest['issues'] = array_slice( array_values( array_unique( $issues ) ), 0, 20 );
	$count              = 0;
	foreach ( (array) $manifest['files'] as $want ) {
		$count += count( (array) $want );
	}
	update_option( 'digitalisimo_theme_child_copy', $manifest, false );
	$ready = ! $manifest['issues'] && '' !== $base;
	$was   = $saved;
	unset( $saved['copied'], $saved['cv'] );
	if ( $ready ) {
		$saved['copied'] = 1;
		$saved['cv']     = substr( hash( 'sha256', (string) json_encode( $manifest['files'] ) ), 0, 8 ); // Versión de la copia (en `ver`).
	}
	if ( $saved !== $was ) {
		update_option( 'digitalisimo_theme_child', $saved, true );
	}
	return array(
		'status' => ! $ready ? 'incomplete' : ( $changed ? 'copied' : ( $count ? 'unchanged' : 'none' ) ),
		'files'  => $count,
		'issues' => $manifest['issues'],
	);
}

/** ¿Están en la carpeta todos los archivos con su sha256? */
function digitalisimo_theme_child_copy_intact( $dir, array $want ) {
	foreach ( $want as $rel => $hash ) {
		$path = $dir . '/' . $rel;
		if ( ! is_file( $path ) || hash_file( 'sha256', $path ) !== $hash ) {
			return false;
		}
	}
	return '' !== (string) $dir;
}

/**
 * ¿Ya viven en la copia las hojas, fuentes, imágenes y scripts del hijo (y lo de Hello que el hijo encolaba)? Si es
 * true, borrar el hijo y Hello no cambia nada de lo que se ve. True también si no hay nada que cargar del hijo. Vuelve a
 * comprobar cada archivo (sha256) y que el hijo no haya cambiado desde la copia. En $issues, por qué no.
 */
function digitalisimo_theme_child_assets_copied( &$issues = null ) {
	$issues = array();
	$saved  = get_option( 'digitalisimo_theme_child' );
	if ( ! is_array( $saved ) || ( empty( $saved['assets'] ) && empty( $saved['refs'] ) ) ) {
		return true;
	}
	$manifest = get_option( 'digitalisimo_theme_child_copy' );
	if ( empty( $saved['copied'] ) || ! is_array( $manifest ) || ( $manifest['child'] ?? '' ) !== ( $saved['child'] ?? '' ) ) {
		$issues = is_array( $manifest ) && ( $manifest['child'] ?? '' ) === ( $saved['child'] ?? '' ) && ! empty( $manifest['issues'] ) ? (array) $manifest['issues'] : array( 'los archivos del hijo todavía no se copiaron' );
		return false;
	}
	$issues = (array) ( $manifest['issues'] ?? array() );
	list( $files, $more ) = digitalisimo_theme_child_copy_list( $saved );
	$issues = array_merge( $issues, $more );
	$base   = digitalisimo_theme_child_copy_dir();
	foreach ( $files as $theme => $list ) {
		foreach ( $list as $rel => $abs ) {
			if ( ( $manifest['files'][ $theme ][ $rel ] ?? '' ) !== hash_file( 'sha256', $abs ) ) {
				$issues[] = sprintf( '%s/%s cambió o falta en la copia', $theme, $rel );
			}
		}
	}
	foreach ( (array) ( $manifest['files'] ?? array() ) as $theme => $want ) {
		if ( ! digitalisimo_theme_child_copy_intact( $base . '/' . $theme, (array) $want ) ) {
			$issues[] = sprintf( 'la copia de %s está incompleta', $theme );
		}
	}
	$issues = array_values( array_unique( $issues ) );
	return ! $issues;
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
			$ver = digitalisimo_theme_child_asset_ver( $saved, (array) $asset, $url );
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

/** Dirección de una entrada: en su copia de uploads, o en la carpeta del hijo (o de Hello) si sigue ahí; la externa tal cual. */
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
	// Con la copia completa en uploads (digitalisimo_theme_child_copy_assets()), desde ahí: el hijo y Hello pueden borrarse.
	if ( ! empty( $saved['copied'] ) && digitalisimo_theme_child_slug_ok( $theme ) && digitalisimo_theme_child_copy_fresh( digitalisimo_theme_child_copy_dir( $theme ) . '/' . $src, get_theme_root( $theme ) . '/' . $theme . '/' . $src ) ) {
		return digitalisimo_theme_child_copy_dir( $theme, true ) . '/' . $src;
	}
	if ( ! is_file( get_theme_root( $theme ) . '/' . $theme . '/' . $src ) ) {
		return ''; // El hijo ya no está: no se imprime un enlace roto.
	}
	return get_theme_root_uri( $theme ) . '/' . $theme . '/' . $src;
}

/** La versión de una entrada: la del hijo y, servida desde la copia, más la versión de la copia (para la caché). */
function digitalisimo_theme_child_asset_ver( array $saved, array $asset, $url ) {
	$ver = $asset['v'] ?? false;
	if ( is_string( $url ) && ! empty( $saved['cv'] ) && 0 === strpos( $url, digitalisimo_theme_child_copy_dir( '', true ) . '/' ) ) {
		$ver = ( is_string( $ver ) && '' !== $ver ? $ver . '-' : '' ) . $saved['cv'];
	}
	return $ver;
}

/** El hijo cuyas hojas carga el tema (o '' si no hay ninguno). */
function digitalisimo_theme_child_carried() {
	$record = function_exists( 'digitalisimo_theme_switch_record' ) ? digitalisimo_theme_switch_record() : array();
	$saved  = empty( $record['carry'] ) ? array() : get_option( 'digitalisimo_theme_child' );
	return is_array( $saved ) && ! empty( $saved['child'] ) ? (string) $saved['child'] : '';
}

if ( ! function_exists( 'digitalisimo_theme_notice' ) ) {
	/**
	 * Aviso del tema con la X de WordPress (aquí y en Temas y Plugins, inc/fallbacks.php). Si un plugin DIGITALÍSIMO
	 * cargó `Digitalisimo_Notices`, el cierre queda recordado para el usuario (una advertencia vuelve a los 7 días, un
	 * error al día, lo informativo no vuelve mientras diga lo mismo); si no, se cierra sólo en esa página: el tema no
	 * lleva JavaScript propio.
	 */
	function digitalisimo_theme_notice( $id, $type, $content, $cap ) {
		if ( class_exists( 'Digitalisimo_Notices', false ) ) {
			Digitalisimo_Notices::show( $id, $type, $content, array( 'cap' => $cap ) );
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- llega escapado.
	}
}

/** En Temas: el tema carga las hojas del hijo desde su carpeta (no lo borres) o el hijo ya no está. */
function digitalisimo_theme_child_notice() {
	if ( ! current_user_can( 'switch_themes' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	$child  = $screen && in_array( $screen->id, array( 'themes', 'themes-network' ), true ) ? digitalisimo_theme_child_carried() : '';
	if ( '' === $child || digitalisimo_theme_child_copy_ready( $child ) ) {
		return; // Con la copia completa, el hijo puede borrarse sin perder nada.
	}
	if ( is_dir( get_theme_root( $child ) . '/' . $child ) ) {
		/* translators: %s: folder of the child theme. */
		digitalisimo_theme_notice( 'theme-child-kept', 'info', '<p>' . esc_html( sprintf( __( 'The DIGITALÍSIMO theme loads the stylesheets, fonts and scripts of the child theme %s from its folder: do not delete that theme.', 'digitalisimo-theme' ), $child ) ) . '</p>', 'switch_themes' );
		return;
	}
	/* translators: %s: folder of the child theme. */
	digitalisimo_theme_notice( 'theme-child-deleted', 'error', '<p>' . esc_html( sprintf( __( 'The child theme %s was deleted: the DIGITALÍSIMO theme can no longer load its stylesheets, fonts and scripts. Reinstall it to get them back.', 'digitalisimo-theme' ), $child ) ) . '</p>', 'switch_themes' );
}

/** Al borrar el hijo cuyas hojas carga el tema, queda en la bitácora del paso. */
function digitalisimo_theme_child_deleted( $stylesheet, $deleted = true ) {
	if ( ! $deleted || (string) $stylesheet !== digitalisimo_theme_child_carried() || digitalisimo_theme_child_copy_ready( $stylesheet ) || ! function_exists( 'digitalisimo_theme_switch_log' ) ) {
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
	$wrap   = digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ), (string) $child->get_stylesheet(), digitalisimo_theme_child_ctx( $child ) );
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
 * Cómo envolver el archivo en la condición, sobre los tokens del lector: null si no se puede (namespace, declare,
 * __halt_compiler, o un `const` de primer nivel con varias constantes). Los `use` de primer nivel se sacan delante de la
 * condición (sólo dan nombres cortos) y cada `const X = …;` de primer nivel pasa a `define( 'X', … );` dentro de ella.
 * Devuelve `use` (tramos [desde, hasta)) y `const` ([const, nombre, =, ;]).
 */
function digitalisimo_theme_child_wrap_plan( array $t ) {
	$plan  = array( 'use' => array(), 'const' => array() );
	$depth = 0;
	$n     = count( $t );
	for ( $i = 0; $i < $n; $i++ ) {
		$v = $t[ $i ][0];
		if ( in_array( $v, array( '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) {
			++$depth;
			continue;
		}
		if ( '}' === $v ) {
			--$depth;
			continue;
		}
		if ( $depth ) {
			continue;
		}
		$start = 0 === $i || in_array( $t[ $i - 1 ][0], array( ';', '{', '}', T_OPEN_TAG ), true );
		if ( in_array( $v, array( T_NAMESPACE, T_DECLARE, T_HALT_COMPILER ), true ) ) {
			return null;
		}
		if ( T_USE === $v && $start ) {
			$end           = digitalisimo_theme_child_statement_end( $t, $i );
			$plan['use'][] = array( $i, $end );
			$i             = $end - 1;
		} elseif ( T_CONST === $v ) {
			$end   = digitalisimo_theme_child_statement_end( $t, $i );
			$value = array_merge( array( array( '(', '(', 0 ) ), array_slice( $t, $i + 3, max( 0, $end - $i - 4 ) ), array( array( ')', ')', 0 ) ) );
			if ( ! $start || ! isset( $t[ $i + 2 ] ) || T_STRING !== $t[ $i + 1 ][0] || '=' !== $t[ $i + 2 ][0] || ';' !== $t[ $end - 1 ][0] || 1 !== count( digitalisimo_theme_child_args( $value, 0, $close ) ) ) {
				return null;
			}
			$plan['const'][] = array( $i, $i + 1, $i + 2, $end - 1 );
			$i               = $end - 1;
		}
	}
	return $plan;
}

/** El contexto del lector para este hijo (versiones, archivos y fechas), el mismo que usa digitalisimo_theme_child_plan(). */
function digitalisimo_theme_child_ctx( WP_Theme $child ) {
	$dir    = untrailingslashit( (string) $child->get_stylesheet_directory() );
	$parent = $child->parent();
	return array(
		'version'        => (string) $child->get( 'Version' ),
		'parent_version' => $parent ? (string) $parent->get( 'Version' ) : '',
		'exists'         => static function ( $where, $path ) use ( $dir, $parent ) {
			$base = 'parent' === $where ? ( $parent ? untrailingslashit( (string) $parent->get_stylesheet_directory() ) : '' ) : $dir;
			return '' !== $base && is_file( $base . '/' . $path );
		},
		'mtime'          => static function ( $path ) use ( $dir ) {
			return is_file( $dir . '/' . $path ) ? (string) filemtime( $dir . '/' . $path ) : '';
		},
	);
}

/**
 * Los cambios de la envoltura sobre los tokens de token_get_all() (índice => texto): las rutas del hijo reescritas
 * (digitalisimo_theme_child_rewrites()), salvo en lo que el tema ya trae solo (leído con el contexto real del hijo,
 * $ctx), así que el fragmento de la plantilla oficial queda igual que con 1.0.1.
 */
function digitalisimo_theme_child_rewrite_changes( $code, array $t, array $raw, $slug, array $ctx ) {
	if ( ! digitalisimo_theme_child_slug_ok( $slug ) ) {
		return array();
	}
	digitalisimo_theme_child_functions_plan( $code, $ctx, $clean );
	$rewrites = digitalisimo_theme_child_rewrites( $t );
	foreach ( array_keys( $rewrites ) as $at ) {
		foreach ( (array) $clean as $range ) {
			if ( $at >= $range[0] && $at < $range[1] ) {
				unset( $rewrites[ $at ] );
			}
		}
	}
	$slug_php = var_export( $slug, true );
	$names    = array( 'dir' => 'digitalisimo_theme_child_dir', 'dir_uri' => 'digitalisimo_theme_child_dir_uri', 'style' => 'digitalisimo_theme_child_file_uri', 'file_uri' => 'digitalisimo_theme_child_file_uri', 'file_path' => 'digitalisimo_theme_child_file_path' );
	$change   = array();
	foreach ( $rewrites as $i => $rewrite ) {
		if ( 'dir' === $rewrite[0] && ( T_DIR === $t[ $i ][0] ) ) {
			$change[ $raw[ $i ] ] = 'digitalisimo_theme_child_dir( ' . $slug_php . ' )';
		} elseif ( 'file' === $rewrite[0] ) {
			$change[ $raw[ $i ] ] = 'digitalisimo_theme_child_file_path( ' . $slug_php . ", 'functions.php' )";
		} else {
			$change[ $raw[ $i ] ]     = ( 0 === strpos( (string) $t[ $i ][1], '\\' ) ? '\\' : '' ) . $names[ $rewrite[0] ];
			$empty                    = isset( $t[ $i + 2 ] ) && ')' === $t[ $i + 2 ][0];
			$change[ $raw[ $i + 1 ] ] = '( ' . $slug_php . ( 'style' === $rewrite[0] ? ", 'style.css'" : '' ) . ( $empty ? ' ' : ',' ); // El espacio de después sigue.
		}
	}
	return $change;
}

/**
 * El functions.php del hijo dentro de una condición que sólo lo deja correr con el tema DIGITALÍSIMO activo: con el hijo
 * activo el fragmento no hace nada (sus funciones ni se declaran), así que puede revisarse y activarse en Tools antes de
 * cambiar de tema, sigue corriendo en cuanto el hijo deja de cargarse y se apaga solo si se vuelve al hijo. Con $slug
 * (y el contexto del hijo, $ctx), sus rutas apuntan a su copia o a su carpeta; los `use` de primer nivel van delante y
 * cada `const` pasa a define(). '' si el archivo no admite ir dentro de un bloque (digitalisimo_theme_child_wrap_plan()).
 * Sin nada que cambiar, sale byte a byte igual que en 1.0.1.
 */
function digitalisimo_theme_child_wrap_php( $code, $name, $slug = '', array $ctx = array() ) {
	$code = (string) $code;
	if ( ! preg_match( '/^<\?php\s/', $code ) || ! function_exists( 'token_get_all' ) ) {
		return '';
	}
	$t    = digitalisimo_theme_child_tokens( $code, $raw );
	$plan = null === $t ? null : digitalisimo_theme_child_wrap_plan( $t );
	if ( null === $plan ) {
		return '';
	}
	$change = digitalisimo_theme_child_rewrite_changes( $code, $t, $raw, (string) $slug, $ctx );
	$all    = token_get_all( $code );
	$uses   = '';
	foreach ( $plan['use'] as $range ) {
		$text = '';
		for ( $at = $raw[ $range[0] ]; $at <= $raw[ $range[1] - 1 ]; $at++ ) {
			$text         .= is_array( $all[ $at ] ) ? $all[ $at ][1] : $all[ $at ];
			$change[ $at ] = '';
		}
		$uses .= $text . "\n";
	}
	foreach ( $plan['const'] as $c ) {
		$change[ $raw[ $c[0] ] ] = 'define(';
		$change[ $raw[ $c[1] ] ] = var_export( (string) $t[ $c[1] ][1], true );
		$change[ $raw[ $c[2] ] ] = ',';
		$change[ $raw[ $c[3] ] ] = ');';
	}
	$out  = '';
	$last = null;
	foreach ( $all as $at => $token ) {
		$out .= $change[ $at ] ?? ( is_array( $token ) ? $token[1] : $token );
		if ( '' !== ( $change[ $at ] ?? null ) && ( ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
			$last = is_array( $token ) ? $token[0] : $token;
		}
	}
	$body  = (string) preg_replace( '/^<\?php\s/', '', $out, 1 );
	$label = (string) preg_replace( '/[^A-Za-z0-9 _.-]/', '', (string) $name ); // Nada que pueda cerrar el comentario o el PHP.
	return "<?php\n// functions.php del tema hijo «" . $label . "», tal cual, dentro de esta condición: sólo corre con el tema DIGITALÍSIMO\n"
		. "// activo. Con el hijo activo no hace nada, así que puede activarse antes de cambiar de tema y se apaga si se vuelve al hijo.\n"
		. $uses
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
	$wrap = digitalisimo_theme_child_wrap_php( $code, (string) $child->get( 'Name' ), (string) $child->get_stylesheet(), digitalisimo_theme_child_ctx( $child ) );
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
