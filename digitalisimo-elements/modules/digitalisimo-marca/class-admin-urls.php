<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * URL del escritorio y del editor con nombre propio (D-3 de docs/MARCA_PROPIA.md).
 *
 * Siempre que corre el núcleo incluido, las URL nuevas funcionan igual que las viejas: al cargar el archivo principal de
 * Elements, justo después del núcleo y antes de que él, sus guardas de widgets (`Unknown_Guard`, `Inert`), su App o
 * WordPress (`admin.php` lee `page` después de cargar los plugins) miren la petición, se traduce a la vieja en `$_GET`,
 * `$_POST`, `$_REQUEST` y `REQUEST_URI` (el modo seguro del núcleo, un mu-plugin, decide antes y sólo ve la vieja):
 * - `post.php?…&action=digitalisimo` → `action=elementor` (el editor);
 * - `page=digitalisimo-motor…` → `page=elementor…` (todas las pantallas del constructor);
 * - `digitalisimo_<x>` en claves y valores → `elementor_<x>` para los tipos y taxonomías registrados (`Map::types()`):
 *   `edit.php?post_type=digitalisimo_library&digitalisimo_library_type=popup`.
 * La URL vieja sigue funcionando sin cambios.
 *
 * Con la marca propia en vigor (`Toggle::active()`), ningún enlace muestra la vieja: las redirecciones salen con los
 * alias (`wp_redirect`) y un script en el escritorio y el editor reescribe la barra de direcciones (`history`), los
 * enlaces, los formularios GET con sus campos ocultos y lo que el editor o sus apps añaden después (MutationObserver).
 */
final class Admin_Urls {
	const PAGES = array( 'admin.php', 'edit.php', 'post.php', 'post-new.php', 'edit-tags.php', 'term.php', 'upload.php', 'options-general.php', 'admin-post.php', 'tools.php', 'index.php' );

	public static function init(): void {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		// Por si el archivo principal no la llamó (pruebas, cargas parciales): es idempotente.
		add_action( 'plugins_loaded', array( __CLASS__, 'translate_request' ), 0 );
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect' ), 99 );
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'print_script' ), 99 );
		add_action( 'elementor/editor/footer', array( __CLASS__, 'print_script' ), 99 );
		add_action( 'elementor/app/init', array( __CLASS__, 'app_script' ) );
	}

	/** ¿La petición es de una pantalla que puede llevar alias? */
	private static function screen(): string {
		global $pagenow;
		return is_string( $pagenow ) ? $pagenow : '';
	}

	/** Traduce los alias de la petición a los nombres viejos (siempre, con el núcleo incluido). */
	/** @var bool ¿Ya se tradujo esta petición? */
	public static $translated = false;

	public static function translate_request(): void {
		if ( self::$translated || ! function_exists( 'is_admin' ) || ! is_admin() || ! Toggle::bundled() ) {
			return;
		}
		self::$translated = true;
		$screen = self::screen();
		if ( ! in_array( $screen, self::PAGES, true ) ) {
			return;
		}
		$changed = false;
		foreach ( array( '_GET', '_POST', '_REQUEST' ) as $global ) {
			if ( ! isset( $GLOBALS[ $global ] ) || ! is_array( $GLOBALS[ $global ] ) ) {
				continue;
			}
			$new = self::translate_args( $GLOBALS[ $global ], $screen );
			if ( $new !== $GLOBALS[ $global ] ) {
				$GLOBALS[ $global ] = $new; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- traducción de alias antes de que WordPress lea la petición.
				$changed            = true;
			}
		}
		if ( $changed && isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = self::url_in( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sólo se cambian los alias por los nombres viejos.
		}
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function translate_args( array $args, string $screen = 'admin.php' ): array {
		$types = array_flip( Map::types() );
		$out   = array();
		foreach ( $args as $key => $value ) {
			$k = is_string( $key ) && isset( $types[ $key ] ) ? $types[ $key ] : $key;
			if ( is_string( $value ) ) {
				if ( 'page' === $k && 0 === strpos( $value, Map::PAGE_OUT ) ) {
					$value = 'elementor' . substr( $value, strlen( Map::PAGE_OUT ) );
				} elseif ( 'action' === $k && Map::ACTION_OUT === $value && 'post.php' === $screen ) {
					$value = 'elementor';
				} elseif ( isset( $types[ $value ] ) ) {
					$value = $types[ $value ];
				}
			}
			$out[ $k ] = $value;
		}
		return $out;
	}

	/** Una URL con alias → con los nombres viejos (para `REQUEST_URI`). */
	public static function url_in( string $url ): string {
		$types = array_flip( Map::types() );
		$url   = (string) preg_replace( '#([?&])page=' . preg_quote( Map::PAGE_OUT, '#' ) . '#', '$1page=elementor', $url );
		$url   = (string) preg_replace( '#([?&])action=' . Map::ACTION_OUT . '(?=&|$|\#)#', '$1action=elementor', $url );
		if ( $types ) {
			$url = (string) preg_replace_callback(
				'#(?<=[?&=])(digitalisimo_[a-z0-9_]+)(?=[=&]|$|\#)#',
				static function ( $m ) use ( $types ) {
					return $types[ $m[1] ] ?? $m[0];
				},
				$url
			);
		}
		return $url;
	}

	/** Una URL del escritorio con los nombres viejos → con alias. Las de otros sitios o del front, igual. */
	public static function url_out( string $url ): string {
		if ( false === stripos( $url, 'elementor' ) ) {
			return $url;
		}
		$parts = explode( '#', $url, 2 );
		$base  = $parts[0];
		$path  = (string) strtok( $base, '?' );
		if ( false === strpos( $base, '?' ) || ( false === strpos( $path, '/wp-admin/' ) && false === strpos( $path, '.php' ) && 0 !== strpos( $path, 'admin.php' ) ) ) {
			return $url;
		}
		$types = Map::types();
		$is_post = '' !== $path && 'post.php' === basename( $path );
		$base    = (string) preg_replace_callback(
			'#([?&]|&amp;)((?!amp;)[^=&\#]+)=([^&\#]*)#',
			static function ( $m ) use ( $types, $is_post ) {
				$key   = $types[ $m[2] ] ?? $m[2];
				$value = $m[3];
				if ( 'page' === $m[2] && 0 === strpos( $value, 'elementor' ) ) {
					$value = Map::PAGE_OUT . substr( $value, 9 );
				} elseif ( 'action' === $m[2] && 'elementor' === $value && $is_post ) {
					$value = Map::ACTION_OUT;
				} elseif ( isset( $types[ $value ] ) ) {
					$value = $types[ $value ];
				}
				return $m[1] . $key . '=' . $value;
			},
			$base
		);
		return $base . ( isset( $parts[1] ) ? '#' . $parts[1] : '' );
	}

	public static function redirect( $location ) {
		return is_string( $location ) && Toggle::active() ? self::url_out( $location ) : $location;
	}

	/** El mapa que usa el script (tipos, prefijo de página y acción). */
	public static function script_data(): array {
		return array(
			'types'  => Map::types(),
			'page'   => Map::PAGE_OUT,
			'action' => Map::ACTION_OUT,
		);
	}

	/** El script de los alias: barra de direcciones, enlaces, formularios y lo que se añada después. */
	public static function script(): string {
		$data = wp_json_encode( self::script_data() );
		return '(function(d){if(!window.URL||!window.history){return;}var T=d.types||{},P=d.page,A=d.action;'
			. 'function out(u){if(typeof u!=="string"||u.toLowerCase().indexOf("elementor")<0){return u;}var x;try{x=new URL(u,location.href);}catch(e){return u;}'
			. 'if(x.origin!==location.origin||!/\\.php$/.test(x.pathname)){return u;}var post=/\\/post\\.php$/.test(x.pathname),q=new URLSearchParams(),c=false;'
			. 'x.searchParams.forEach(function(v,k){var nk=T[k]||k,nv=v;if(k==="page"&&v.indexOf("elementor")===0){nv=P+v.slice(9);}else if(k==="action"&&v==="elementor"&&post){nv=A;}else if(T[v]){nv=T[v];}if(nk!==k||nv!==v){c=true;}q.append(nk,nv);});'
			. 'if(!c){return u;}x.search=q.toString();return x.href;}'
			. 'function fix(el){if(!el||el.nodeType!==1){return;}var list=el.matches&&el.matches("a[href],form")?[el]:[];if(el.querySelectorAll){list=list.concat([].slice.call(el.querySelectorAll("a[href],form")));}'
			. 'list.forEach(function(n){if(n.tagName==="A"){var h=n.getAttribute("href"),o=out(h);if(o!==h){n.setAttribute("href",o);}return;}'
			. 'var a=n.getAttribute("action");if(a){var o2=out(a);if(o2!==a){n.setAttribute("action",o2);}}if((n.getAttribute("method")||"get").toLowerCase()!=="get"){return;}'
			. '[].slice.call(n.querySelectorAll("input[type=hidden][name]")).forEach(function(i){var k=i.name,v=i.value;if(T[k]){i.name=T[k];}if(k==="page"&&v.indexOf("elementor")===0){i.value=P+v.slice(9);}else if(T[v]){i.value=T[v];}});});}'
			. '["pushState","replaceState"].forEach(function(m){var f=history[m];history[m]=function(s,t,u){return f.call(this,s,t,u===undefined||u===null?u:out(String(u)));};});'
			// Después de `load`: los scripts del núcleo ya leyeron la dirección y marcaron el menú con los nombres viejos.
			. 'function all(){var here=out(location.href);if(here!==location.href){history.replaceState(history.state,"",here);}fix(document.documentElement);'
			. 'if(window.MutationObserver){new MutationObserver(function(l){l.forEach(function(r){if(r.type==="attributes"){fix(r.target);}else{r.addedNodes.forEach(fix);}});}).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:["href","action"]});}}'
			. 'if(document.readyState==="complete"){setTimeout(all,0);}else{window.addEventListener("load",function(){setTimeout(all,0);});}'
			. 'window.addEventListener("hashchange",function(){var h=out(location.href);if(h!==location.href){history.replaceState(history.state,"",h);}});'
			. '})(' . $data . ');';
	}

	public static function print_script(): void {
		static $done = false;
		if ( $done || ! Toggle::active() ) {
			return;
		}
		$done = true;
		echo '<script id="digitalisimo-marca-urls">' . self::script() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON con wp_json_encode y código fijo.
	}

	/** La App (Theme Builder, kits) imprime su propia página: el mismo script en su pie. */
	public static function app_script(): void {
		add_action( 'admin_footer', array( __CLASS__, 'print_script' ), 99 );
		add_action( 'wp_footer', array( __CLASS__, 'print_script' ), 99 );
	}
}
