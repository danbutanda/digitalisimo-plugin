<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Transformaciones de la marca propia sobre lo que sale (docs/MARCA_PROPIA.md, fase 2): el HTML del front, los bloques
 * `<style>` y `<script>` en línea, el CSS generado y los fragmentos HTML de AJAX y REST, con las reglas de
 * `marca/reglas.json` (las mismas que el espejo de scripts/marca-transform.mjs).
 *
 * Límites (lo que nunca se toca): `src`, `srcset`, `poster` y `href` salvo las URL de assets del constructor y
 * `#elementor-action`; el `action` de formularios; el texto visible del contenido. Una imagen `elementor-banner.jpg` o
 * un artículo que hable de Elementor salen como están. Sólo se cambian:
 * - atributos `class`, `id`, `for`, `aria-controls`, `aria-labelledby`, `aria-describedby` (cualquier «elementor»);
 * - nombres `data-elementor-*` → `data-dg-*` y valores conocidos (`elementor_library`, `#elementor-action…`);
 * - el valor de `<input name="action">` de los formularios del constructor (`elementor_pro_forms_send_form` →
 *   `digitalisimo_pro_forms_send_form`, que la petición traduce de vuelta);
 * - URL de assets: el núcleo (`elementor/` o `motor/`) → el espejo; `uploads/elementor/css|google-fonts/` → la copia;
 * - en `<style>`: todo el CSS; en `<script>` en línea: identificadores JS conocidos, eventos, rutas de assets y los
 *   dominios de texto de `wp.i18n.setLocaleData`.
 */
final class Transform {
	const P = 'dg';

	/** @var array|null */
	private static $rules = null;

	public static function rules(): array {
		if ( null === self::$rules ) {
			$file        = dirname( __DIR__, 2 ) . '/marca/reglas.json';
			$php         = dirname( __DIR__, 2 ) . '/marca/reglas.php';
			$data        = is_readable( $php ) ? include $php : json_decode( (string) @file_get_contents( $file ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo local del plugin.
			self::$rules = is_array( $data ) ? $data : array();
		}
		return self::$rules;
	}

	/** Para pruebas. */
	public static function set_rules( ?array $rules ): void {
		self::$rules = $rules;
	}

	public static function css( string $css ): string {
		if ( false === stripos( $css, 'elementor' ) ) {
			return $css;
		}
		$css = (string) preg_replace_callback(
			'#/\*.*?\*/#s',
			static function ( $m ) {
				return false !== stripos( $m[0], 'elementor' ) ? '' : $m[0];
			},
			$css
		);
		// Los url() se apartan: sólo cambian las rutas de assets y del CSS generado; un medio `elementor-banner.jpg` no.
		$urls = array();
		$css  = (string) preg_replace_callback(
			'#url\(\s*([\'"]?)([^\'")]*)\1\s*\)#i',
			static function ( $m ) use ( &$urls ) {
				$urls[] = 'url(' . $m[1] . self::paths( $m[2] ) . $m[1] . ')';
				return "\x01" . ( count( $urls ) - 1 ) . "\x01";
			},
			$css
		);
		$css = str_replace( array( 'data-elementor-', '--elementor-' ), array( 'data-' . self::P . '-', '--' . self::P . '-' ), $css );
		$css = (string) preg_replace( '/elementor/i', self::P, $css );
		return (string) preg_replace_callback(
			'#\x01(\d+)\x01#',
			static function ( $m ) use ( $urls ) {
				return $urls[ (int) $m[1] ];
			},
			$css
		);
	}

	/** Claves cuyo valor es contenido del cliente: nunca se transforma (D-6: el contenido no cambia de palabras). */
	const CONTENT_KEYS = array( 'elements', 'title', 'post_title', 'post_excerpt', 'excerpt', 'content', 'post_content', 'custom_css', 'description', 'caption', 'alt' );

	/** @var array<string,string>|null identificadores JS de las reglas (los más largos primero) */
	private static $ids = null;

	/** @return array<string,string> */
	private static function ids(): array {
		if ( null === self::$ids ) {
			$rules = self::rules();
			$ids   = array_merge( (array) ( $rules['js']['propiedades'] ?? array() ), (array) ( $rules['js']['globales'] ?? array() ), (array) ( $rules['js']['dataset'] ?? array() ) );
			uksort(
				$ids,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			self::$ids = $ids;
		}
		return self::$ids;
	}

	/** Un identificador de código JS (las mismas reglas que transformIdent de scripts/marca-transform.mjs). */
	public static function ident( string $id, bool $dataset = false ): string {
		if ( false === stripos( $id, 'elementor' ) ) {
			return $id;
		}
		// `el.dataset.elementorOpenLightbox` es el atributo `data-elementor-open-lightbox`: como el HTML, `data-dg-*`.
		if ( $dataset ) {
			return str_replace( array( 'elementor', 'Elementor' ), array( self::P, 'Dg' ), $id );
		}
		$ids = self::ids();
		if ( isset( $ids[ $id ] ) ) {
			return (string) $ids[ $id ];
		}
		$id = str_replace( 'ELEMENTOR', 'DIGITALISIMO', $id );
		return (string) preg_replace_callback(
			'/elementor/i',
			static function ( $m ) {
				return 'E' === $m[0][0] ? 'Digitalisimo' : 'digitalisimo';
			},
			$id
		);
	}

	/**
	 * Una cadena (las mismas reglas que transformStr de scripts/marca-transform.mjs, más las rutas de assets): clases
	 * `elementor-x` → `dg-x`, ganchos, eventos y espacios REST `elementor/…` → `digitalisimo/…`, `elementor_x` →
	 * `digitalisimo_x`, dominios de texto `elementor` → `dg`, los alias de URL del escritorio y «Elementor» →
	 * «DIGITALÍSIMO». Las URL (de medios, de páginas) sólo cambian en las rutas de assets y en su consulta.
	 */
	public static function str( string $s ): string {
		if ( false === stripos( $s, 'elementor' ) ) {
			return $s;
		}
		if ( self::is_url( $s ) ) {
			return self::url_str( $s );
		}
		$p = self::P;
		$s = (string) preg_replace( '/(#|%23)elementor-action/', '$1' . $p . '-action', $s );
		$s = str_replace( 'data-elementor-', 'data-' . $p . '-', $s );
		$s = self::query( $s );
		$s = (string) preg_replace( '#(?<![\w-])elementor(-[a-z]+)?(?=\\\\?/)#', 'digitalisimo$1', $s );
		$s = str_replace( 'elementor_', 'digitalisimo_', $s );
		foreach ( self::ids() as $from => $to ) {
			if ( 'elementor' === $from || 'elementor_' === $from || false === strpos( $s, (string) $from ) ) {
				continue;
			}
			$s = (string) preg_replace( '/(?<![\w$])' . preg_quote( (string) $from, '/' ) . '(?![\w$])/', (string) $to, $s );
		}
		$s = (string) preg_replace( '/(?<![\w$])elementor(?![a-zA-Z0-9_$])/', $p, $s );
		$s = (string) preg_replace( '/(?<![\w$])Elementor(?![\w$])/', 'DIGITALÍSIMO', $s );
		$s = str_replace( 'ELEMENTOR', 'DIGITALISIMO', $s );
		$s = (string) preg_replace( '/elementor(?=[A-Z])/', 'digitalisimo', $s );
		$s = str_replace( 'Elementor', 'Digitalisimo', $s );
		return (string) preg_replace( '/elementor/i', $p, $s );
	}

	/** Los alias de la consulta de una URL del escritorio y de la API (D-3). */
	private static function query( string $s ): string {
		$s = (string) preg_replace( '/([?&;]page=)elementor/', '$1' . Map::PAGE_OUT, $s );
		$s = (string) preg_replace( '/([?&;]action=)elementor(?![\w-])/', '$1' . Map::ACTION_OUT, $s );
		$s = (string) preg_replace( '/([?&;])elementor-preview=/', '$1' . self::P . '-preview=', $s );
		$s = (string) preg_replace( '/([?&;=]|%3D)elementor_/', '$1digitalisimo_', $s );
		// Rutas de la API: /wp-json/elementor…/ y rest_route=/elementor…/ (también codificada).
		return (string) preg_replace( '#(/wp-json(?:\\\\)?/|rest_route=(?:/|%2F))elementor(-[a-z]+|_[a-z0-9_]+)?(?=\\\\?/|%2F|$|[?&\#])#i', '$1digitalisimo$2', $s );
	}

	/** ¿Parece una URL (absoluta, relativa al sitio o un archivo .php con consulta)? */
	private static function is_url( string $s ): bool {
		return (bool) preg_match( '#^(?:https?:)?(?:\\\\?/){2}[^\s]+$|^(?:\\\\?/)[\w.%~-][^\s]*$|^[\w.-]+\.php(\?[^\s]*)?$#i', $s );
	}

	/** Una URL: las rutas de assets del paquete y del CSS generado, y su consulta. Los medios y las páginas, igual. */
	private static function url_str( string $s ): string {
		$s = self::paths( $s );
		$s = self::query( $s );
		return (string) preg_replace( '/(#|%23)elementor-action/', '$1' . self::P . '-action', $s );
	}

	/**
	 * JS en línea. Una declaración de configuración (`var X = {json};`, la forma de `Utils::print_js_config()` y de
	 * `wp_localize_script()`) se trata como datos (`json_out()`: sin tocar el contenido del cliente); el resto, por
	 * contexto con el analizador léxico (`lex()`): identificadores, cadenas, plantillas y expresiones regulares.
	 */
	public static function js( string $js ): string {
		if ( false === stripos( $js, 'elementor' ) ) {
			return $js;
		}
		if ( preg_match( '/^(\s*(?:\/\*.*?\*\/\s*)?var\s+)([A-Za-z_$][\w$]*)(\s*=\s*)(\{.*\}|\[.*\])(\s*;?\s*(?:\/\*.*?\*\/\s*)?)$/s', $js, $m ) ) {
			$data = json_decode( $m[4], true );
			if ( null !== $data ) {
				$json = self::json( self::json_out( $data ) );
				if ( is_string( $json ) ) {
					return $m[1] . self::ident( $m[2] ) . $m[3] . $json . $m[5];
				}
			}
		}
		return self::lex( self::paths( $js ) );
	}

	/** JSON como lo imprime WordPress (con `json_encode` si WordPress no está cargado, en las pruebas). */
	private static function json( $data ) {
		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- sin WordPress (pruebas).
	}

	/** Datos que salen hacia el JS (configuración, respuestas de AJAX y de la API). */
	public static function json_out( $value, string $key = '', int $depth = 0 ) {
		if ( $depth > 64 ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			if ( false === stripos( $value, 'elementor' ) && false === strpos( $value, 'lemen' ) ) {
				return $value;
			}
			// Plantillas (Twig de los componentes atómicos, Underscore): su código y su HTML.
			if ( preg_match( '/\{[{%]|<#/', $value ) ) {
				$value = self::html( self::template_code( $value ) );
				// Un selector de control (`{{WRAPPER}} .elementor-x`) no es HTML: sus clases por las reglas.
				return false !== stripos( $value, 'elementor' ) && false === strpos( $value, '<' ) ? self::str( $value ) : $value;
			}
			if ( false !== strpos( $value, '<' ) && preg_match( '/<[a-z!\/]/i', $value ) ) {
				return self::html( $value );
			}
			return self::str( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$nk         = is_string( $k ) ? self::str( $k ) : $k;
			$out[ $nk ] = is_string( $k ) && in_array( $k, self::CONTENT_KEYS, true ) ? self::content( $v ) : self::json_out( $v, (string) $k, $depth + 1 );
		}
		return $out;
	}

	/**
	 * Contenido del cliente dentro de datos: el texto no cambia; sólo el HTML que el constructor pinta (atributos) y
	 * las URL de assets. Así un documento que el editor lee y vuelve a guardar no cambia.
	 */
	private static function content( $value ) {
		return $value;
	}

	/** Analizador léxico de JS (el mismo de lexJs en scripts/marca-transform.mjs). */
	public static function lex( string $src ): string {
		$n      = strlen( $src );
		$out    = '';
		$i      = 0;
		$prev   = '';
		$prev2  = '';
		$braces = array();
		$kw     = array( 'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await' );
		$tpl    = static function () use ( &$i, &$out, &$prev, &$braces, $src, $n ) {
			$j     = $i;
			$chunk = '';
			while ( $j < $n ) {
				$c = $src[ $j ];
				if ( '\\' === $c ) {
					$chunk .= substr( $src, $j, 2 );
					$j     += 2;
					continue;
				}
				if ( '`' === $c ) {
					$out .= self::str( $chunk ) . '`';
					$i    = $j + 1;
					$prev = 'p:`';
					return;
				}
				if ( '$' === $c && $j + 1 < $n && '{' === $src[ $j + 1 ] ) {
					$out     .= self::str( $chunk ) . '${';
					$i        = $j + 2;
					$braces[] = 't';
					$prev     = 'p:{';
					return;
				}
				$chunk .= $c;
				++$j;
			}
			$out .= self::str( $chunk );
			$i    = $n;
		};
		while ( $i < $n ) {
			$c  = $src[ $i ];
			$c2 = $i + 1 < $n ? $src[ $i + 1 ] : '';
			if ( '/' === $c && '/' === $c2 ) {
				$j    = strpos( $src, "\n", $i );
				$j    = false === $j ? $n : $j;
				$text = substr( $src, $i, $j - $i );
				$out .= false !== stripos( $text, 'elementor' ) ? '' : $text;
				$i    = $j;
				continue;
			}
			if ( '/' === $c && '*' === $c2 ) {
				$j    = strpos( $src, '*/', $i + 2 );
				$j    = false === $j ? $n : $j + 2;
				$text = substr( $src, $i, $j - $i );
				$out .= false !== stripos( $text, 'elementor' ) ? ' ' : $text;
				$i    = $j;
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$j = $i + 1;
				while ( $j < $n && $src[ $j ] !== $c && "\n" !== $src[ $j ] ) {
					$j += '\\' === $src[ $j ] ? 2 : 1;
				}
				$out .= $c . self::str( substr( $src, $i + 1, $j - $i - 1 ) ) . ( $j < $n ? $src[ $j ] : '' );
				$i    = $j + 1;
				$prev = 'str';
				continue;
			}
			if ( '`' === $c ) {
				$out .= '`';
				++$i;
				$tpl();
				continue;
			}
			if ( '/' === $c ) {
				$regex_ok = '' === $prev || ( 'num' !== $prev && 'str' !== $prev && ( 0 === strpos( $prev, 'id:' ) ? in_array( substr( $prev, 3 ), $kw, true ) : ! in_array( $prev, array( 'p:)', 'p:]', 'p:}' ), true ) ) );
				if ( $regex_ok ) {
					$j   = $i + 1;
					$cls = false;
					while ( $j < $n && "\n" !== $src[ $j ] ) {
						$d = $src[ $j ];
						if ( '\\' === $d ) {
							$j += 2;
							continue;
						}
						if ( '[' === $d ) {
							$cls = true;
						} elseif ( ']' === $d ) {
							$cls = false;
						} elseif ( '/' === $d && ! $cls ) {
							break;
						}
						++$j;
					}
					if ( $j < $n && '/' === $src[ $j ] ) {
						$k = $j + 1;
						while ( $k < $n && ctype_alpha( $src[ $k ] ) ) {
							++$k;
						}
						$out .= '/' . self::str( substr( $src, $i + 1, $j - $i - 1 ) ) . substr( $src, $j, $k - $j );
						$i    = $k;
						$prev = 'num';
						continue;
					}
				}
			}
			if ( ctype_alpha( $c ) || '_' === $c || '$' === $c || ord( $c ) > 127 ) {
				$j = $i + 1;
				while ( $j < $n && ( ctype_alnum( $src[ $j ] ) || '_' === $src[ $j ] || '$' === $src[ $j ] || ord( $src[ $j ] ) > 127 ) ) {
					++$j;
				}
				$id    = substr( $src, $i, $j - $i );
				$out  .= self::ident( $id, 'p:.' === $prev && 'id:dataset' === $prev2 );
				$prev2 = $prev;
				$prev  = 'id:' . $id;
				$i    = $j;
				continue;
			}
			if ( ctype_digit( $c ) ) {
				$j = $i + 1;
				while ( $j < $n && ( ctype_alnum( $src[ $j ] ) || '_' === $src[ $j ] || '.' === $src[ $j ] ) ) {
					++$j;
				}
				$out .= substr( $src, $i, $j - $i );
				$prev = 'num';
				$i    = $j;
				continue;
			}
			if ( '{' === $c ) {
				$braces[] = 'b';
			} elseif ( '}' === $c && 't' === array_pop( $braces ) ) {
				$out .= '}';
				++$i;
				$tpl();
				continue;
			}
			$out .= $c;
			if ( ! ctype_space( $c ) ) {
				$prev2 = $prev;
				$prev  = 'p:' . $c;
			}
			++$i;
		}
		return $out;
	}

	/** URL de assets del constructor → espejo y CSS generado → su copia (también con `\/` de JSON). */
	public static function paths( string $text ): string {
		if ( class_exists( Package::class ) && Package::transformed() ) {
			return self::packaged_paths( $text );
		}
		$from = array();
		$to   = array();
		foreach ( array( 'elementor', 'motor' ) as $dir ) {
			$from[] = '/plugins/digitalisimo-elements/' . $dir . '/assets/';
			$to[]   = '/plugins/digitalisimo-elements/marca/motor/';
		}
		foreach ( array( 'css', 'google-fonts' ) as $kind ) {
			$from[] = '/uploads/elementor/' . $kind . '/';
			$to[]   = '/uploads/digitalisimo-elements/' . $kind . '/';
		}
		foreach ( $from as $i => $f ) {
			$from[] = str_replace( '/', '\/', $f );
			$to[]   = str_replace( '/', '\/', $to[ $i ] );
		}
		$text = str_replace( $from, $to, $text );
		// Archivos del espejo con «elementor» en el nombre (eicons: elementor-icons.css → dg-icons.css).
		return (string) preg_replace_callback(
			'#(/plugins/digitalisimo-elements/marca/motor/[\w./\\\\-]*?)elementor([\w.-]*\.(?:css|js))#i',
			static function ( $m ) {
				return $m[1] . self::P . $m[2];
			},
			$text
		);
	}

	/**
	 * En el paquete transformado: `…/digitalisimo-elements/<ruta>` con el nombre publicado (Package::rename), salvo PHP;
	 * el CSS generado, a su copia. También con `\/` de JSON.
	 */
	private static function packaged_paths( string $text ): string {
		$text = (string) preg_replace_callback(
			'#(/plugins(?:\\\\)?/digitalisimo-elements(?:\\\\)?/)((?:[\w.-]+(?:\\\\)?/)*[\w.-]*)#',
			static function ( $m ) {
				$sep  = false !== strpos( $m[1], '\\' ) ? '\\/' : '/';
				$rel  = str_replace( '\\/', '/', $m[2] );
				$done = preg_match( '/\.php$/', $rel ) ? $rel : Package::rename( $rel );
				return $m[1] . str_replace( '/', $sep, $done );
			},
			$text
		);
		foreach ( array( 'css', 'google-fonts' ) as $kind ) {
			$text = str_replace( array( '/uploads/elementor/' . $kind . '/', '\/uploads\/elementor\/' . $kind . '\/' ), array( '/uploads/digitalisimo-elements/' . $kind . '/', '\/uploads\/digitalisimo-elements\/' . $kind . '\/' ), $text );
		}
		return $text;
	}

	/** Una URL de un atributo src/href: sólo las de assets. Los medios y las demás páginas salen igual. */
	public static function url( string $url ): string {
		if ( preg_match( '/^(#|%23)elementor-action/i', $url ) ) {
			return (string) preg_replace( '/elementor-action/', self::P . '-action', $url, 1 );
		}
		$packaged = class_exists( Package::class ) && Package::transformed() && preg_match( '#/plugins/digitalisimo-elements/[^?\#]*elementor#i', $url );
		if ( ! $packaged && ! preg_match( '#/plugins/digitalisimo-elements/(elementor|motor)/assets/|/uploads/elementor/(css|google-fonts)/#', $url ) ) {
			return $url;
		}
		return self::paths( $url );
	}

	/** El HTML de una página o de un fragmento. */
	public static function html( string $html ): string {
		if ( false === stripos( $html, 'elementor' ) ) {
			return $html;
		}
		$html = (string) preg_replace_callback(
			'#(<style\b[^>]*>)(.*?)(</style>)#is',
			static function ( $m ) {
				return self::tag( $m[1] ) . self::css( $m[2] ) . $m[3];
			},
			$html
		);
		$html = (string) preg_replace_callback(
			'#(<script\b[^>]*>)(.*?)(</script>)#is',
			static function ( $m ) {
				$open = self::tag( $m[1] );
				// Sólo JS y la configuración en JSON; nunca un `src`, el schema (`ld+json`, texto del contenido) ni plantillas.
				$type = preg_match( '#\btype\s*=\s*["\']([^"\']+)["\']#i', $m[1], $t ) ? strtolower( $t[1] ) : 'text/javascript';
				// Plantillas del editor (`<script type="text/template">`): son HTML.
				if ( in_array( $type, array( 'text/template', 'text/html', 'text/x-template' ), true ) ) {
					return $open . self::html( self::template_code( $m[2] ) ) . $m[3];
				}
				if ( preg_match( '/\bsrc\s*=/i', $m[1] ) || ! in_array( $type, array( 'text/javascript', 'module', 'application/javascript', 'application/json' ), true ) ) {
					return $open . $m[2] . $m[3];
				}
				return $open . self::js( $m[2] ) . $m[3];
			},
			$html
		);
		return (string) preg_replace_callback(
			'#<(?!/|script\b|style\b)([a-zA-Z][a-zA-Z0-9-]*)(\s[^<>]*?)?(/?)>#s',
			static function ( $m ) {
				return self::tag( $m[0] );
			},
			$html
		);
	}

	/**
	 * Las formas con que el paquete enmascara la palabra en lo que el servidor lee (scripts/marca-empaquetar.mjs):
	 * `&#101;lementor` (HTML, SVG), `('elemen' ~ 'tor…')` (Twig) y el separador invisible (guías del MCP).
	 */
	public static function unmask( string $text ): string {
		if ( false === strpos( $text, 'lemen' ) ) {
			return $text;
		}
		$text = (string) preg_replace_callback(
			'/&#(101|69|x65|x45);(?=lementor)/i',
			static function ( $m ) {
				return in_array( strtolower( $m[1] ), array( '101', 'x65' ), true ) ? 'e' : 'E';
			},
			$text
		);
		$text = (string) preg_replace( '/\(\'([^\']*?[Ee]lemen)\' ~ \'(tor[^\']*)\'\)/', "'$1$2'", $text );
		return Map::unmask( $text );
	}

	/** El código de una plantilla de Underscore o de Twig (`<# … #>`, `{% … %}`, `{{ … }}`) por el analizador léxico. */
	public static function template_code( string $tpl ): string {
		$tpl = self::unmask( $tpl );
		if ( false === stripos( $tpl, 'elementor' ) ) {
			return $tpl;
		}
		return (string) preg_replace_callback(
			'/(<#|\{%)(.*?)(#>|%\})|(\{\{\{?)(.*?)(\}?\}\})/s',
			static function ( $m ) {
				return '' !== $m[1] ? $m[1] . self::lex( $m[2] ) . $m[3] : $m[4] . self::lex( $m[5] ) . $m[6];
			},
			$tpl
		);
	}

	/** Una etiqueta de apertura con sus atributos. */
	public static function tag( string $tag ): string {
		if ( false === stripos( $tag, 'elementor' ) ) {
			// Lo que el paquete enmascara con una entidad (`&#101;lementor`, separadores y plantillas): en las clases e ids
			// se trata como la palabra.
			if ( ! preg_match( '/&#(?:101|69|x65|x45);lementor/i', $tag ) ) {
				return $tag;
			}
			$tag = (string) preg_replace_callback(
				'#(\s(?:class|id|for|aria-controls|aria-labelledby|aria-describedby)\s*=\s*)("[^"]*"|\'[^\']*\')#i',
				static function ( $m ) {
					return $m[1] . self::unmask( $m[2] );
				},
				$tag
			);
			if ( false === stripos( $tag, 'elementor' ) ) {
				return $tag;
			}
		}
		$is_action = (bool) preg_match( '/\bname\s*=\s*["\']action["\']/i', $tag ) && (bool) preg_match( '/^<input\b/i', $tag );
		return (string) preg_replace_callback(
			'#(\s)([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*("[^"]*"|\'[^\']*\'))?#',
			static function ( $a ) use ( $is_action ) {
				$name  = $a[2];
				$value = $a[4] ?? '';
				$lname = strtolower( $name );
				if ( 0 === strpos( $lname, 'data-elementor-' ) ) {
					$name = 'data-' . self::P . '-' . substr( $name, 15 );
				}
				if ( '' !== $value && false !== stripos( $value, 'elementor' ) ) {
					$q     = $value[0];
					$inner = substr( $value, 1, -1 );
					if ( in_array( $lname, array( 'class', 'id', 'for', 'aria-controls', 'aria-labelledby', 'aria-describedby' ), true ) ) {
						$inner = (string) preg_replace( '/elementor/i', self::P, $inner );
					} elseif ( in_array( $lname, array( 'href', 'src' ), true ) ) {
						$inner = self::url( $inner );
					} elseif ( 'value' === $lname && $is_action && preg_match( '/^elementor_[a-z0-9_]+$/', $inner ) ) {
						$inner = 'digitalisimo_' . substr( $inner, 10 );
					} elseif ( 0 === strpos( $lname, 'data-' ) ) {
						if ( preg_match( '/^(#|%23)elementor-action/i', $inner ) ) {
							$inner = self::url( $inner );
						} elseif ( 'elementor_library' === $inner ) {
							$inner = 'digitalisimo_library';
						}
					}
					$value = $q . $inner . $q;
				}
				return $a[1] . $name . ( '' !== $value ? '=' . $value : ( $a[3] ?? '' ) );
			},
			$tag
		);
	}

	/** Fragmentos en una estructura (respuestas REST o AJAX): cada cadena que parece HTML. */
	public static function deep_html( $value, int $depth = 0 ) {
		if ( $depth > 32 ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return false !== strpos( $value, '<' ) ? self::html( $value ) : $value;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::deep_html( $v, $depth + 1 );
			}
		}
		return $value;
	}

	/** Apariciones de «elementor» (sin distinguir mayúsculas). */
	public static function count( string $text ): int {
		return (int) preg_match_all( '/elementor/i', $text );
	}
}
