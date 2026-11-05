<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `woocommerce-settings` de K, sólo en «agregar» (§7.5): informa las páginas de la tienda que traía el kit
 * sin cambiar `woocommerce_*_page_id`. En «nuevo» queda el nativo.
 */
class Woocommerce_Report extends Import_Runner_Base {
	const KEYS = array(
		'woocommerce_cart_page_id',
		'woocommerce_checkout_page_id',
		'woocommerce_myaccount_page_id',
		'woocommerce_terms_page_id',
		'elementor_woocommerce_purchase_summary_page_id',
		'woocommerce_shop_page_id',
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
		$pages    = isset( $imported_data['content']['page']['succeed'] ) && is_array( $imported_data['content']['page']['succeed'] ) ? $imported_data['content']['page']['succeed'] : array();
		foreach ( self::KEYS as $key ) {
			$value = isset( $settings[ $key ] ) ? $settings[ $key ] : null;
			if ( ( is_int( $value ) || is_string( $value ) ) && isset( $pages[ $value ] ) ) {
				Context::report(
					'woocommerce.page',
					array(
						'option' => $key,
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
