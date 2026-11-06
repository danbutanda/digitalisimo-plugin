<?php
/**
 * Clases de PRO → propias comunes a los dos «Añadir al carrito» (`woocommerce-product-add-to-cart` y `wc-add-to-cart`),
 * para el CSS personalizado del elemento: disposición, alineación en cada dispositivo y envoltura de cantidad y botón.
 * No es un mapa (`Registry` ignora `maps/_*.php`): los dos mapas lo suman a sus clases.
 */
defined( 'ABSPATH' ) || exit;

$digi_atc_classes = array(
	'.elementor-add-to-cart--layout-stacked' => '.digi-atc--layout-stacked',
	'.elementor-add-to-cart--layout-auto'    => '.digi-atc--layout-auto',
	'.e-atc-qty-button-holder'               => '.digi-atc__holder',
);
foreach ( array( '', '-widescreen', '-laptop', '-tablet_extra', '-tablet', '-mobile_extra', '-mobile' ) as $digi_atc_device ) {
	foreach ( array( 'left', 'center', 'right', 'justify' ) as $digi_atc_value ) {
		$digi_atc_classes[ '.elementor-add-to-cart' . $digi_atc_device . '--align-' . $digi_atc_value ] = '.digi-atc' . $digi_atc_device . '--align-' . $digi_atc_value;
	}
}

return $digi_atc_classes;
