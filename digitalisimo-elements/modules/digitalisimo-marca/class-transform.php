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

	/** JS en línea: identificadores conocidos, eventos, rutas, acciones de URL y dominios de texto. */
	public static function js( string $js ): string {
		if ( false === stripos( $js, 'elementor' ) ) {
			return $js;
		}
		$rules = self::rules();
		$ids   = array_merge( (array) ( $rules['js']['propiedades'] ?? array() ), (array) ( $rules['js']['globales'] ?? array() ) );
		uksort(
			$ids,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$js = self::paths( $js );
		// Los dominios de texto de las traducciones, antes que los identificadores (el global `elementor` también es una
		// palabra suelta): el espejo llama a `__()` con `dg` y `dg-pro`.
		$js = (string) preg_replace( '/(setLocaleData\([^;]*?,\s*["\'])elementor(-pro)?(["\'])/', '$1' . self::P . '$2$3', $js );
		// La forma que imprime wp_set_script_translations(): `( function( domain, translations ) {…} )( "elementor", {…} )`.
		$js = (string) preg_replace( '/(\}\s*\)\s*\(\s*["\'])elementor(-pro)?(["\']\s*,)/', '$1' . self::P . '$2$3', $js );
		foreach ( $ids as $from => $to ) {
			if ( false !== strpos( $js, $from ) ) {
				$js = (string) preg_replace( '/(?<![\w$])' . preg_quote( (string) $from, '/' ) . '(?![\w$])/', (string) $to, $js );
			}
		}
		foreach ( (array) ( $rules['js']['eventos_regla'] ?? array() ) as $r ) {
			$js = (string) preg_replace( '#(?<![\w./\\\\-])' . preg_quote( (string) $r['de'], '#' ) . '#', (string) $r['a'], $js );
		}
		// Rutas REST de PRO en la configuración (`…\/elementor-pro\/v1\/…`): el alias `digitalisimo-pro/v1` las atiende.
		$js = str_replace( array( 'elementor-pro/v1', 'elementor-pro\/v1' ), array( 'digitalisimo-pro/v1', 'digitalisimo-pro\/v1' ), $js );
		$js = str_replace( array( '#elementor-action', '%23elementor-action', 'data-elementor-' ), array( '#' . self::P . '-action', '%23' . self::P . '-action', 'data-' . self::P . '-' ), $js );
		return (string) preg_replace( '/(?<=[."\'\s])elementor(?=-[a-z])/', self::P, $js );
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
					return $open . self::html( $m[2] ) . $m[3];
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

	/** Una etiqueta de apertura con sus atributos. */
	public static function tag( string $tag ): string {
		if ( false === stripos( $tag, 'elementor' ) ) {
			return $tag;
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
