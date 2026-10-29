<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-asset-list.php';

/**
 * Productos variables en la vista rápida (V.5.3, V-B3).
 *
 * En una petición REST, WooCommerce no registra sus scripts ni localiza sus parámetros (`load_scripts()` va en
 * `wp_enqueue_scripts`; `localize_printed_scripts()`, al imprimir). Por eso la configuración se calcula **en la
 * página**, en `wp_print_footer_scripts` con prioridad 6 (después de la localización de WooCommerce, en 5), y sólo si
 * el script de la vista rápida está encolado y `wc-add-to-cart-variation` está registrado y no encolado:
 *
 * 1. un filtro de prioridad máxima en `wc_add_to_cart_variation_params` guarda una copia de los parámetros y los
 *    devuelve tal cual;
 * 2. se encola el script, se llama a `WC_Frontend_Scripts::localize_printed_scripts()` dentro de un búfer que se
 *    descarta (la plantilla que imprime viaja en la respuesta de la ruta) y se desencola;
 * 3. las dependencias se resuelven recursivamente desde `wp_scripts()->registered` (así vale `wc-jquery-blockui` o su
 *    alias de otras versiones), sin lo que la página ya tiene.
 *
 * Una página sin vista rápida no paga nada. Si algo falla, no hay configuración y la vista rápida de un variable abre
 * la ficha (falla hacia la navegación).
 */
final class Variation {
	const SCRIPT = 'wc-add-to-cart-variation';
	const PARAMS = 'wc_add_to_cart_variation_params';

	/**
	 * `{ scripts: [ { handle, src } ], params: { wc_add_to_cart_variation_params: {…}, … } }`, o `null`. `src` lleva
	 * `?ver=` y es relativa a la raíz cuando el archivo está en este sitio.
	 */
	public static function footer_config(): ?array {
		if ( ! function_exists( 'wp_scripts' ) || ! class_exists( 'WC_Frontend_Scripts' ) || ! method_exists( 'WC_Frontend_Scripts', 'localize_printed_scripts' ) ) {
			return null;
		}
		$scripts = wp_scripts();
		if ( ! isset( $scripts->registered[ self::SCRIPT ] ) || wp_script_is( self::SCRIPT, 'enqueued' ) ) {
			return null; // Sin registrar no hay qué cargar; encolado, la página ya trae todo.
		}
		$captured = null;
		$capture  = static function ( $params ) use ( &$captured ) {
			$captured = $params;
			return $params;
		};
		add_filter( self::PARAMS, $capture, PHP_INT_MAX );
		$level = ob_get_level();
		try {
			wp_enqueue_script( self::SCRIPT );
			ob_start();
			\WC_Frontend_Scripts::localize_printed_scripts();
		} catch ( \Throwable $error ) {
			$captured = null;
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			wp_dequeue_script( self::SCRIPT );
			remove_filter( self::PARAMS, $capture, PHP_INT_MAX );
		}
		if ( ! is_array( $captured ) ) {
			return null;
		}
		$list = Asset_List::scripts( array( self::SCRIPT ), $scripts, Asset_List::present( $scripts ), false );
		if ( null === $list ) {
			return null;
		}
		$params = $list['params'];
		unset( $params[ self::PARAMS ] );
		$params[ self::PARAMS ] = $captured;
		// Va en línea en cada página con la vista rápida: sin `ver` (ya viaja en `src`) y sin origen cuando el archivo
		// está en este mismo sitio, para que quepa en 1 KB (V.16.5) con cualquier dominio. Un CDN conserva su URL.
		$host = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) : '';
		$out  = array();
		foreach ( $list['scripts'] as $entry ) {
			$src = (string) $entry['src'];
			if ( '' !== $host && $host === (string) wp_parse_url( $src, PHP_URL_HOST ) ) {
				$src = wp_make_link_relative( $src );
			}
			$out[] = array( 'handle' => $entry['handle'], 'src' => $src );
		}
		return array( 'scripts' => $out, 'params' => $params );
	}
}
