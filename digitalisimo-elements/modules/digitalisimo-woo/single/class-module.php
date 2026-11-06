<?php
namespace Digitalisimo\Elements\Woo\Single;

defined( 'ABSPATH' ) || exit;

/**
 * Ficha de producto de C7 (frente M, `docs/pro/c7.md`): lo que los quince widgets propios necesitan fuera de sí mismos.
 * Los widgets los registra `Woo\Registry` (están en su `WIDGETS`); este submódulo sólo arranca con WooCommerce.
 *
 * - **Panel de las plantillas de bucle de productos.** PRO pone sus widgets de la ficha en «Recomendados» y oculta las
 *   Imágenes del producto (`elementor/document/config`, 11). Aquí (12) los propios toman su lugar y los de PRO
 *   sustituidos quedan ocultos; `Convert\Panel` (20) vuelve a ocultarlos después, como en cualquier documento.
 * - **Vista previa del editor.** Al volver a pintar las Pestañas de datos o las Imágenes del producto, las inicia con el
 *   script de la ficha de WooCommerce (PRO lo hacía con un script en línea dentro del marcado del widget). Sólo en la vista previa
 *   y sólo sobre lo recién pintado: nunca dos veces la misma galería o las mismas pestañas.
 */
final class Module {
	/** Widgets de PRO de la ficha → propios. */
	const PAIRS = array(
		'woocommerce-product-title'                  => 'digitalisimo-woo-product-title',
		'woocommerce-product-price'                  => 'digitalisimo-woo-product-price',
		'woocommerce-product-rating'                 => 'digitalisimo-woo-product-rating',
		'woocommerce-product-stock'                  => 'digitalisimo-woo-product-stock',
		'woocommerce-product-meta'                   => 'digitalisimo-woo-product-meta',
		'woocommerce-product-short-description'      => 'digitalisimo-woo-product-short-description',
		'woocommerce-product-content'                => 'digitalisimo-woo-product-content',
		'woocommerce-product-additional-information' => 'digitalisimo-woo-product-additional-information',
		'woocommerce-product-data-tabs'              => 'digitalisimo-woo-product-data-tabs',
		'woocommerce-product-images'                 => 'digitalisimo-woo-product-images',
		'woocommerce-product-add-to-cart'            => 'digitalisimo-woo-product-add-to-cart',
		'woocommerce-product-related'                => 'digitalisimo-woo-product-related',
		'woocommerce-product-upsell'                 => 'digitalisimo-woo-product-upsell',
		'wc-single-elements'                         => 'digitalisimo-woo-single-elements',
		'wc-add-to-cart'                             => 'digitalisimo-woo-add-to-cart',
	);

	/** @var bool */
	private static $initialized = false;

	/** Con WooCommerce (desde `Woo\Module::BOOT`). Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'elementor/document/config', array( __CLASS__, 'document_config' ), 12, 2 );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'preview_script' ) );
	}

	/** Widgets de PRO sustituidos y ocultos (mapa listo y widget propio registrado). */
	private static function hidden(): array {
		$panel = '\Digitalisimo\Elements\Convert\Panel';
		return class_exists( $panel ) ? (array) call_user_func( array( $panel, 'hidden' ) ) : array();
	}

	/**
	 * `elementor/document/config` (12): en una plantilla de bucle de productos, los propios en «Recomendados» en lugar de
	 * los de PRO, y las Imágenes del producto propias ocultas como las de PRO.
	 *
	 * @param array $config  Configuración del documento.
	 * @param int   $post_id Documento.
	 */
	public static function document_config( $config, $post_id = 0 ) {
		if ( ! is_array( $config ) || empty( $config['panel']['widgets_settings'] ) || ! is_array( $config['panel']['widgets_settings'] ) ) {
			return $config;
		}
		$settings = $config['panel']['widgets_settings'];
		$loop     = false;
		foreach ( self::PAIRS as $pro => $own ) {
			if ( isset( $settings[ $pro ]['categories'] ) && in_array( 'recommended', (array) $settings[ $pro ]['categories'], true ) ) {
				$loop = true;
				break;
			}
		}
		if ( ! $loop ) {
			return $config;
		}
		$hidden = self::hidden();
		foreach ( self::PAIRS as $pro => $own ) {
			if ( ! isset( $settings[ $pro ] ) || ! is_array( $settings[ $pro ] ) ) {
				continue;
			}
			if ( 'woocommerce-product-images' === $pro ) {
				$config['panel']['widgets_settings'][ $own ] = array( 'show_in_panel' => false );
				continue;
			}
			if ( isset( $settings[ $pro ]['categories'] ) ) {
				$config['panel']['widgets_settings'][ $own ] = array(
					'categories'    => $settings[ $pro ]['categories'],
					'show_in_panel' => true,
				);
				if ( in_array( $pro, $hidden, true ) ) {
					$config['panel']['widgets_settings'][ $pro ]['show_in_panel'] = false;
				}
			}
		}
		return $config;
	}

	/** `elementor/preview/enqueue_scripts`: inicia la galería y las pestañas recién pintadas en la vista previa. */
	public static function preview_script(): void {
		if ( ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}
		wp_add_inline_script( 'elementor-frontend', self::preview_js() );
	}

	/** El script de la vista previa (jQuery es el de WooCommerce, que su script de la ficha necesita). */
	public static function preview_js(): string {
		return '(function(){window.addEventListener("elementor/frontend/init",function(){var h=window.elementorFrontend&&elementorFrontend.hooks;if(!h){return;}'
			. 'h.addAction("frontend/element_ready/digitalisimo-woo-product-images.default",function($e){var j=window.jQuery;if(!j||!j.fn.wc_product_gallery){return;}$e.find(".woocommerce-product-gallery").each(function(){if(!j(this).data("product_gallery")){j(this).wc_product_gallery();}});});'
			. 'h.addAction("frontend/element_ready/digitalisimo-woo-product-data-tabs.default",function($e){$e.find(".wc-tabs-wrapper, .woocommerce-tabs").each(function(){if(!this.querySelector(".wc-tabs li.active")){window.jQuery(this).trigger("init");}});$e.find("#rating").each(function(){if(!(this.previousElementSibling&&this.previousElementSibling.matches("p.stars"))){window.jQuery(this).trigger("init");}});});'
			. '});})();';
	}
}
