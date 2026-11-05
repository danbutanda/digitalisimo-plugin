<?php
/**
 * Breadcrumbs de PRO (sólo existe con Yoast SEO) → Migas (Yoast o DIGITALÍSIMO SEO) (`digitalisimo-yoast-breadcrumbs`,
 * modules/digitalisimo-theme-elements/widgets/class-yoast-breadcrumbs.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones, así
 * que el mapa es `keep` y `groups`. Con Yoast pinta lo mismo que PRO (sus migas en la etiqueta elegida con
 * `id="breadcrumbs"`); sin Yoast, la ruta de DIGITALÍSIMO SEO en lugar de nada. La alineación se aplica con la propiedad
 * `text-align` del envoltorio en lugar de la clase `elementor-align-*` del núcleo: el resultado es el mismo. Formato:
 * cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

// Clase del envoltorio y las de alineación (`prefix_class`, también por dispositivo).
$digi_bc_classes = array( '.elementor-widget-breadcrumbs' => '.elementor-widget-digitalisimo-yoast-breadcrumbs' );
foreach ( array( '', '-widescreen', '-laptop', '-tablet_extra', '-tablet', '-mobile_extra', '-mobile' ) as $digi_bc_device ) {
	foreach ( array( 'left', 'center', 'right' ) as $digi_bc_align ) {
		$digi_bc_classes[ '.elementor' . $digi_bc_device . '-align-' . $digi_bc_align ] = '.digi-yoast-breadcrumbs' . $digi_bc_device . '-align-' . $digi_bc_align;
	}
}

return array(
	'source'  => 'breadcrumbs',
	'target'  => 'digitalisimo-yoast-breadcrumbs',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'align', 'html_tag', 'text_color', 'link_color', 'link_hover_color' ),
	'groups'  => array( 'typography' => 'typography' ),
	// Aviso del panel: no guarda valor.
	'ui'      => array( 'html_description' ),
	'classes' => $digi_bc_classes,
);
