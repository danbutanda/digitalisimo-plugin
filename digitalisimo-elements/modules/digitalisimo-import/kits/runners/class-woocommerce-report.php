<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `woocommerce-settings` de K, sólo en «agregar» (§7.5): informa las páginas de la tienda que traía el kit
 * sin cambiar `woocommerce_*_page_id`. En «nuevo» queda el nativo, y las páginas las aplica traducidas la parte
 * `kit-settings` (§18).
 *
 * La página del kit se busca en todo lo importado (`Finish::post_map()`: contenido del constructor, entradas del WXR y
 * su deduplicación), no sólo en las páginas del constructor: las de WooCommerce suelen llegar por el WXR. Las claves son
 * las de los ajustes del kit (la de la página de resumen de compra es `woocommerce_purchase_summary_page_id`; su opción,
 * `elementor_woocommerce_purchase_summary_page_id`).
 */
class Woocommerce_Report extends Import_Runner_Base {
	/** Ajuste del kit => opción del sitio (`Settings_Woocommerce::on_save()`). */
	const KEYS = array(
		'woocommerce_cart_page_id'             => 'woocommerce_cart_page_id',
		'woocommerce_checkout_page_id'         => 'woocommerce_checkout_page_id',
		'woocommerce_myaccount_page_id'        => 'woocommerce_myaccount_page_id',
		'woocommerce_terms_page_id'            => 'woocommerce_terms_page_id',
		'woocommerce_purchase_summary_page_id' => 'elementor_woocommerce_purchase_summary_page_id',
		'woocommerce_shop_page_id'             => 'woocommerce_shop_page_id',
	);

	public static function get_name(): string {
		return 'woocommerce-settings';
	}

	public function should_import( array $data ) {
		return isset( $data['include'] ) && is_array( $data['include'] ) && in_array( 'settings', $data['include'], true )
			&& ! empty( $data['site_settings']['settings'] );
	}

	public function import( array $data, array $imported_data ) {
		$settings = (array) $data['site_settings']['settings'];
		$record   = Session::get( (string) ( $data['session_id'] ?? '' ) );
		$pages    = Finish::post_map( null === $record ? array() : $record, $imported_data );
		foreach ( self::KEYS as $key => $option ) {
			$value = isset( $settings[ $key ] ) ? $settings[ $key ] : null;
			if ( ( is_int( $value ) || ( is_string( $value ) && '' !== $value ) ) && isset( $pages[ $value ] ) ) {
				Context::report(
					'woocommerce.page',
					array(
						'option' => $option,
						'page'   => (int) $pages[ $value ],
					),
					Part_Context::INFO
				);
			}
		}
		return array( static::get_name() => false );
	}

	/** Nada que revertir: no cambió ninguna opción. */
	public function get_import_session_metadata(): array {
		return array(
			'previous_pages' => array(),
			'imported_pages' => array(),
		);
	}
}
