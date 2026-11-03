<?php
/**
 * P1 · Prime Slider Pro sintético (f8.sh, F8 frente J): documentos con los 35 IDs de Pro (casos `defaults` y `custom`
 * de tests/legacy/prime-slider-<id>.json) en un sitio con la copia oficial de Lite activa y **sin Pro instalado**
 * (la copia de Pro de reference/ nunca se instala: licencia anulada). Los widgets de Pro no están registrados, así
 * que la conversión rellena con los valores estáticos de B (Defaults::fill sin el widget registrado). Reutiliza las
 * funciones de p0.php (`digi_f8_ps_*`).
 */

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/p0.php';

if ( 'P1' === ( digi_f8_mode()['scenario'] ?? '' ) ) {
	function digi_f8_activate( array $mode ) {
		digi_f8_ps_activate( $mode );
	}
	function digi_f8_content( array $mode ) {
		if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Install' ) && ! get_option( 'woocommerce_shop_page_id' ) ) {
			WC_Install::install();
			WC_Install::create_pages();
		}
		digi_f8_ps_documents( digi_f8_ps_inventory( 'pro' ), array( 'defaults', 'custom' ), 'pro', $mode );
	}
	function digi_f8_ops() {
		return digi_f8_ps_ops();
	}
}
