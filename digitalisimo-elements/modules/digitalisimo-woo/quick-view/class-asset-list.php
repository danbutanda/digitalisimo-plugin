<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

defined( 'ABSPATH' ) || exit;

/**
 * Recursos que la vista rápida carga al abrirse (V.5.3 y V.5.4): el cierre de dependencias de unos *handles*,
 * resuelto recursivamente desde `wp_scripts()->registered` / `wp_styles()->registered`, en orden de carga y con la URL
 * que imprimiría WordPress (`base_url`, `ver`, `script_loader_src`).
 *
 * - Un alias (sin `src`, como `jquery` o `jquery-blockui`) no se carga: sus dependencias ya están en la lista.
 * - Una dependencia sin registrar invalida la lista (WordPress tampoco imprimiría el script): `null`, y la vista rápida
 *   abre la ficha.
 * - Los parámetros localizados (`var nombre = {…};` de `wp_localize_script()`) viajan como objetos y el navegador los
 *   fija en `window` antes de cargar el archivo, sin `<script>` en línea. Un dato que no tiene esa forma invalida la
 *   lista.
 * - El navegador decide qué le falta con `script#{handle}-js` y `link#{handle}-css`: la ruta no conoce la página.
 * - Los paquetes del constructor y de sus scripts avanzados no se pueden cargar tarde (crean sus módulos una vez en
 *   `elementor/frontend/init`): van con `late: false` y, si la página no los tiene, la vista rápida abre la ficha.
 */
final class Asset_List {
	/** Scripts que sólo funcionan si la página ya los trae. */
	const NOT_LATE = array( 'elementor-webpack-runtime', 'elementor-frontend-modules', 'elementor-frontend', 'elementor-pro-webpack-runtime', 'elementor-pro-frontend', 'pro-elements-handlers' );

	/**
	 * Cierre ordenado (dependencias primero) de `$handles`, o `null` si falta alguna dependencia registrada.
	 *
	 * @param string[]          $handles Handles pedidos.
	 * @param \WP_Dependencies  $deps    `wp_scripts()` o `wp_styles()`.
	 */
	public static function closure( array $handles, $deps ): ?array {
		$order = array();
		$state = array();
		foreach ( $handles as $handle ) {
			if ( ! self::visit( (string) $handle, $deps, $order, $state ) ) {
				return null;
			}
		}
		return $order;
	}

	private static function visit( string $handle, $deps, array &$order, array &$state ): bool {
		if ( isset( $state[ $handle ] ) ) {
			return true; // Visitado o en curso (un ciclo no se recorre dos veces).
		}
		if ( ! isset( $deps->registered[ $handle ] ) ) {
			return false;
		}
		$state[ $handle ] = 1;
		foreach ( (array) $deps->registered[ $handle ]->deps as $dep ) {
			if ( ! self::visit( (string) $dep, $deps, $order, $state ) ) {
				return false;
			}
		}
		$order[] = $handle;
		return true;
	}

	/**
	 * Handles que la página ya tiene o tendrá al imprimir el pie: los impresos y el cierre de la cola.
	 *
	 * @param \WP_Dependencies $deps Registro.
	 * @return array<string,true>
	 */
	public static function present( $deps ): array {
		$present = array();
		foreach ( (array) $deps->done as $handle ) {
			$present[ (string) $handle ] = true;
		}
		$order = array();
		$state = array();
		foreach ( (array) $deps->queue as $handle ) {
			self::visit( (string) $handle, $deps, $order, $state );
		}
		foreach ( $order as $handle ) {
			$present[ $handle ] = true;
		}
		return $present;
	}

	/**
	 * Lista de scripts para el navegador: `[ { handle, src, ver, before, after, late } ]` y sus parámetros, o `null`.
	 *
	 * @param string[]          $handles Handles pedidos.
	 * @param \WP_Dependencies  $scripts `wp_scripts()`.
	 * @param array<string,true> $skip   Handles que no se devuelven (los que la página ya tiene).
	 * @param bool              $inline  Se admiten datos `before`/`after` (modo plantilla).
	 * @return array{scripts: array, params: array}|null
	 */
	public static function scripts( array $handles, $scripts, array $skip = array(), bool $inline = true ): ?array {
		$order = self::closure( $handles, $scripts );
		if ( null === $order ) {
			return null;
		}
		$list   = array();
		$params = array();
		foreach ( $order as $handle ) {
			if ( isset( $skip[ $handle ] ) ) {
				continue;
			}
			$dep    = $scripts->registered[ $handle ];
			$before = self::inline( $scripts, $handle, 'before' );
			$after  = self::inline( $scripts, $handle, 'after' );
			$data   = method_exists( $scripts, 'get_data' ) ? $scripts->get_data( $handle, 'data' ) : false;
			if ( is_string( $data ) && '' !== $data ) {
				$parsed = self::localized( $data );
				if ( null === $parsed ) {
					return null;
				}
				$params = array_merge( $params, $parsed );
			}
			if ( empty( $dep->src ) ) {
				if ( '' !== $before || '' !== $after ) {
					return null; // Código en línea sin archivo: no se puede reproducir.
				}
				continue;
			}
			if ( ! $inline && ( '' !== $before || '' !== $after ) ) {
				return null;
			}
			$entry = array( 'handle' => $handle, 'src' => self::src( $scripts, $handle, 'script_loader_src' ), 'ver' => self::version( $scripts, $dep ) );
			if ( '' !== $before ) {
				$entry['before'] = $before;
			}
			if ( '' !== $after ) {
				$entry['after'] = $after;
			}
			if ( in_array( $handle, self::NOT_LATE, true ) ) {
				$entry['late'] = false;
			}
			$list[] = $entry;
		}
		return array( 'scripts' => $list, 'params' => $params );
	}

	/**
	 * Lista de hojas: `[ { handle, src, ver, media, after } ]`. Una hoja sin archivo con CSS en línea viaja sólo con
	 * `after`.
	 *
	 * @param string[]         $handles Handles pedidos.
	 * @param \WP_Dependencies $styles  `wp_styles()`.
	 */
	public static function styles( array $handles, $styles ): ?array {
		$order = self::closure( $handles, $styles );
		if ( null === $order ) {
			return null;
		}
		$list = array();
		foreach ( $order as $handle ) {
			$dep   = $styles->registered[ $handle ];
			$after = method_exists( $styles, 'get_data' ) ? $styles->get_data( $handle, 'after' ) : false;
			$after = is_array( $after ) ? implode( "\n", array_filter( $after, 'is_string' ) ) : '';
			if ( empty( $dep->src ) && '' === $after ) {
				continue;
			}
			$entry = array(
				'handle' => $handle,
				'src'    => empty( $dep->src ) ? '' : self::src( $styles, $handle, 'style_loader_src' ),
				'ver'    => self::version( $styles, $dep ),
				'media'  => is_string( $dep->args ) && '' !== $dep->args ? $dep->args : 'all',
			);
			if ( '' !== $after ) {
				$entry['after'] = $after;
			}
			$list[] = $entry;
		}
		return $list;
	}

	/**
	 * `var nombre = {…};` (una o varias, como las deja `WP_Scripts::localize()`) → `{ nombre: valor }`, o `null`.
	 *
	 * @param string $data Dato `data` del script.
	 */
	public static function localized( string $data ): ?array {
		$out = array();
		foreach ( preg_split( '/\R/', trim( $data ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 1 !== preg_match( '/^var\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*(.*);$/s', $line, $m ) ) {
				return null;
			}
			$value = json_decode( $m[2], true );
			if ( null === $value && 'null' !== strtolower( trim( $m[2] ) ) ) {
				return null;
			}
			$out[ $m[1] ] = $value;
		}
		return $out;
	}

	/** Código en línea `before`/`after` de un script, unido como lo imprime WordPress. */
	private static function inline( $scripts, string $handle, string $position ): string {
		$code = method_exists( $scripts, 'get_data' ) ? $scripts->get_data( $handle, $position ) : false;
		return is_array( $code ) ? trim( implode( "\n", array_filter( $code, 'is_string' ) ) ) : '';
	}

	/** Versión que añadiría WordPress: la del registro, la de por defecto si es `false`, ninguna si es `null`. */
	private static function version( $deps, $dep ): string {
		if ( null === $dep->ver ) {
			return '';
		}
		return (string) ( $dep->ver ? $dep->ver : ( isset( $deps->default_version ) ? $deps->default_version : '' ) );
	}

	/** URL absoluta con `ver`, como `WP_Scripts::do_item()` y `WP_Styles::_css_href()`. */
	private static function src( $deps, string $handle, string $filter ): string {
		$dep = $deps->registered[ $handle ];
		$src = (string) $dep->src;
		$content = isset( $deps->content_url ) ? (string) $deps->content_url : '';
		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( '' !== $content && 0 === strpos( $src, $content ) ) ) {
			$src = ( isset( $deps->base_url ) ? (string) $deps->base_url : '' ) . $src;
		}
		$ver = self::version( $deps, $dep );
		if ( '' !== $ver && function_exists( 'add_query_arg' ) ) {
			$src = add_query_arg( 'ver', $ver, $src );
		}
		$src = function_exists( 'apply_filters' ) ? (string) apply_filters( $filter, $src, $handle ) : $src;
		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $src ) : $src;
	}
}
