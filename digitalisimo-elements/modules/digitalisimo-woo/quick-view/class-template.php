<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-asset-list.php';
require_once __DIR__ . '/class-render.php';

/**
 * Modo «Plantilla de producto» de la vista rápida (V.5.4, R-I3, V-I7).
 *
 * 1. Antes del render se registran los recursos que una petición REST no registra: `WC_Frontend_Scripts::load_scripts()`
 *    y `register_styles()`/`register_scripts()` del front del constructor (que disparan los registros de PRO y de
 *    Elements).
 * 2. Foto de las colas de hojas y scripts y de los *callbacks* de `wp_footer`.
 * 3. `get_builder_content_for_display( $plantilla, true )` (CSS del documento en línea) con el producto como contenido
 *    actual y una consulta simulada, en la que se vuelve a llamar a `load_scripts()` para que WooCommerce encole lo de
 *    una ficha (el widget «Imágenes del producto» de PRO sólo lo encola en la vista previa de la biblioteca).
 * 4. Lo encolado durante el render viaja en `assets` con su cierre de dependencias y sus datos `before`/`after`; los
 *    parámetros localizados (también los de WooCommerce, que se localizan dentro de un búfer), como objetos; la salida
 *    de los *callbacks* de `wp_footer` añadidos durante el render (por ejemplo, `woocommerce_photoswipe` de «Imágenes del
 *    producto»), en `footer_html`; y las plantillas de variaciones, en `templates`.
 *
 * La lista blanca de dependencias de §4.2 vale para lo que declaran los widgets de F4-Woo, no para lo que pide una
 * plantilla del usuario. Si el cierre incluye algo que no se puede reproducir (código en línea sin archivo, una
 * dependencia sin registrar, un dato localizado sin la forma de `wp_localize_script()`), la respuesta pide abrir la
 * ficha (`fallback`). Los paquetes de PRO no se pueden cargar al abrir: la puerta de PRO recorre la plantilla por
 * `digitalisimo_template_id` y la página los trae (coste declarado en el editor y en el presupuesto).
 */
final class Template {
	/** Tipo de plantilla de la biblioteca que se ofrece y se acepta. */
	const TYPE = 'product';

	/** Plantilla publicada de la biblioteca, del tipo «Producto individual». */
	public static function valid( int $id ): bool {
		if ( $id <= 0 || ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( $id );
		return is_object( $post )
			&& 'elementor_library' === $post->post_type
			&& 'publish' === $post->post_status
			&& self::TYPE === (string) get_post_meta( $id, '_elementor_template_type', true );
	}

	/**
	 * Respuesta del modo plantilla, `array( 'fallback' => true )` si no se puede reproducir, o `null` si la plantilla no
	 * es válida (la ruta usa entonces «Diseño propio»: una referencia sin destino nunca amplía nada).
	 *
	 * @param \WC_Product $product     Producto.
	 * @param int         $template_id Plantilla.
	 * @param string      $return      Página de origen.
	 */
	public static function respond( \WC_Product $product, int $template_id, string $return ): ?array {
		$frontend = self::frontend();
		if ( ! self::valid( $template_id ) || null === $frontend || ! function_exists( 'wp_styles' ) ) {
			return null;
		}
		if ( class_exists( 'WC_Frontend_Scripts' ) && method_exists( 'WC_Frontend_Scripts', 'load_scripts' ) ) {
			\WC_Frontend_Scripts::load_scripts();
		}
		foreach ( array( 'register_styles', 'register_scripts' ) as $method ) {
			if ( method_exists( $frontend, $method ) ) {
				$frontend->$method();
			}
		}
		$styles  = wp_styles();
		$scripts = wp_scripts();
		$before  = array( $styles->queue, $scripts->queue, self::footer_ids() );
		$html    = Render::with_product(
			$product,
			$return,
			static function () use ( $frontend, $template_id ) {
				// Con el producto como consulta principal, WooCommerce encola lo de una ficha (`wc-single-product`, la
				// galería, PhotoSwipe y su `wp_footer`): el widget «Imágenes del producto» de PRO no lo encola en el
				// front, sólo en la vista previa de la biblioteca.
				if ( class_exists( 'WC_Frontend_Scripts' ) && method_exists( 'WC_Frontend_Scripts', 'load_scripts' ) ) {
					\WC_Frontend_Scripts::load_scripts();
				}
				return (string) $frontend->get_builder_content_for_display( $template_id, true );
			},
			true
		);
		$new_styles  = array_values( array_diff( $styles->queue, $before[0] ) );
		$new_scripts = array_values( array_diff( $scripts->queue, $before[1] ) );
		$printed     = self::localize();
		$footer      = self::footer_html( $before[2] );
		$style_list  = Asset_List::styles( $new_styles, $styles );
		$script_list = Asset_List::scripts( $new_scripts, $scripts );
		if ( null === $style_list || null === $script_list ) {
			return array( 'fallback' => true );
		}
		$variable = $product->is_type( 'variable' );
		return array(
			'mode'             => 'template',
			'html'             => $html,
			'title'            => wp_strip_all_tags( (string) $product->get_name() ),
			'product'          => (int) $product->get_id(),
			'variable'         => $variable,
			'assets'           => array( 'styles' => $style_list, 'scripts' => $script_list['scripts'], 'params' => $script_list['params'] ),
			'templates'        => false !== strpos( $printed, 'tmpl-variation-template' ) ? $printed : ( $variable ? Render::variation_templates() : '' ),
			'gallery_defaults' => $variable ? Render::gallery_defaults( $product ) : '',
			'footer_html'      => $footer,
		);
	}

	/** Front del constructor, o `null`. */
	private static function frontend() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->frontend ) ) {
			return null;
		}
		$frontend = \Elementor\Plugin::$instance->frontend;
		return is_object( $frontend ) && method_exists( $frontend, 'get_builder_content_for_display' ) ? $frontend : null;
	}

	/**
	 * Localiza los scripts de WooCommerce encolados (fija su dato `data`) dentro de un búfer y devuelve lo impreso: las
	 * plantillas de variaciones, si el render encoló su script.
	 */
	private static function localize(): string {
		if ( ! class_exists( 'WC_Frontend_Scripts' ) || ! method_exists( 'WC_Frontend_Scripts', 'localize_printed_scripts' ) ) {
			return '';
		}
		$level   = ob_get_level();
		$printed = '';
		ob_start();
		try {
			\WC_Frontend_Scripts::localize_printed_scripts();
			$printed = (string) ob_get_clean();
		} catch ( \Throwable $error ) {
			$printed = '';
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
		return trim( $printed );
	}

	/** Identificadores (`prioridad|id`) de los *callbacks* de `wp_footer`. */
	private static function footer_ids(): array {
		global $wp_filter;
		$ids = array();
		if ( isset( $wp_filter['wp_footer'] ) && is_object( $wp_filter['wp_footer'] ) && isset( $wp_filter['wp_footer']->callbacks ) ) {
			foreach ( $wp_filter['wp_footer']->callbacks as $priority => $callbacks ) {
				foreach ( array_keys( (array) $callbacks ) as $id ) {
					$ids[ $priority . '|' . $id ] = true;
				}
			}
		}
		return $ids;
	}

	/**
	 * Salida de los *callbacks* de `wp_footer` que el render añadió, en su orden de prioridad.
	 *
	 * @param array<string,true> $before `footer_ids()` antes del render.
	 */
	private static function footer_html( array $before ): string {
		global $wp_filter;
		if ( ! isset( $wp_filter['wp_footer'] ) || ! is_object( $wp_filter['wp_footer'] ) || ! isset( $wp_filter['wp_footer']->callbacks ) ) {
			return '';
		}
		$callbacks = $wp_filter['wp_footer']->callbacks;
		ksort( $callbacks );
		$html = '';
		foreach ( $callbacks as $priority => $list ) {
			foreach ( (array) $list as $id => $callback ) {
				if ( isset( $before[ $priority . '|' . $id ] ) || empty( $callback['function'] ) || ! is_callable( $callback['function'] ) ) {
					continue;
				}
				$level = ob_get_level();
				ob_start();
				try {
					call_user_func( $callback['function'] );
					$html .= (string) ob_get_clean();
				} catch ( \Throwable $error ) {
					unset( $error );
				} finally {
					while ( ob_get_level() > $level ) {
						ob_end_clean();
					}
				}
			}
		}
		return trim( $html );
	}
}
