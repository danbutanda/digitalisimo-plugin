<?php
/**
 * Share Buttons de PRO → Botones para compartir (`digitalisimo-share-buttons`,
 * modules/digitalisimo-content/widgets/class-share-buttons.php).
 *
 * El widget propio repite los controles del de PRO con el mismo nombre, los mismos valores por defecto, opciones y
 * condiciones (también el repetidor de redes), así que el mapa es `keep` y `groups`. El marcado y las clases son propios:
 * cada botón es un enlace normal a la URL de compartir de su red (PRO pintaba `div role="button"` y abría una ventana con
 * su script), las clases de piel, forma, vista y color pasan a `digi-share--*` en el envoltorio, como en PRO, y la
 * rejilla sigue con las clases del núcleo. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_sb_classes = array(
	'.elementor-widget-share-buttons'       => '.elementor-widget-digitalisimo-share-buttons',
	'.elementor-share-btn'                  => '.digi-share__btn',
	'.elementor-share-btn__icon'            => '.digi-share__icon',
	'.elementor-share-btn__text'            => '.digi-share__text',
	'.elementor-share-btn__title'           => '.digi-share__title',
	'.elementor-share-buttons--view-icon-text' => '.digi-share--view-icon-text',
	'.elementor-share-buttons--view-icon'   => '.digi-share--view-icon',
	'.elementor-share-buttons--view-text'   => '.digi-share--view-text',
	'.elementor-share-buttons--color-official' => '.digi-share--color-official',
	'.elementor-share-buttons--color-custom' => '.digi-share--color-custom',
);
foreach ( array( 'gradient', 'minimal', 'framed', 'boxed', 'flat' ) as $digi_sb_skin ) {
	$digi_sb_classes[ '.elementor-share-buttons--skin-' . $digi_sb_skin ] = '.digi-share--skin-' . $digi_sb_skin;
}
foreach ( array( 'square', 'rounded', 'circle' ) as $digi_sb_shape ) {
	$digi_sb_classes[ '.elementor-share-buttons--shape-' . $digi_sb_shape ] = '.digi-share--shape-' . $digi_sb_shape;
}
foreach ( array( 'facebook', 'twitter', 'linkedin', 'pinterest', 'reddit', 'vk', 'odnoklassniki', 'tumblr', 'digg', 'skype', 'stumbleupon', 'mix', 'telegram', 'pocket', 'xing', 'whatsapp', 'email', 'print', 'x-twitter', 'threads' ) as $digi_sb_network ) {
	$digi_sb_classes[ '.elementor-share-btn_' . $digi_sb_network ] = '.digi-share__btn--' . $digi_sb_network;
}

return array(
	'source'    => 'share-buttons',
	'target'    => 'digitalisimo-share-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos); los del widget propio son los mismos.
	'defaults'  => array(
		'view'           => 'icon-text',
		'show_label'     => 'yes',
		'skin'           => 'gradient',
		'shape'          => 'square',
		'columns'        => '0',
		'share_url_type' => 'current_page',
		'color_source'   => 'official',
	),
	'keep'      => array(
		'view',
		'show_label',
		'skin',
		'shape',
		'columns',
		'alignment',
		'share_url_type',
		'share_url',
		'column_gap',
		'row_gap',
		'button_size',
		'icon_size',
		'button_height',
		'border_size',
		'color_source',
		'primary_color',
		'secondary_color',
		'primary_color_hover',
		'secondary_color_hover',
	),
	'groups'    => array(
		'typography' => 'typography',
	),
	'drop'      => array(
		'text_padding' => 'En PRO el relleno del texto apuntaba a `a.elementor-button`, que su marcado no tiene: no tenía efecto. El widget propio lo aplica al texto, así que no se traslada para no cambiar el aspecto.',
	),
	'repeaters' => array(
		'share_buttons' => array(
			'target'       => 'share_buttons',
			'defaults'     => array( 'button' => 'facebook' ),
			'default_rows' => array( array( 'button' => 'facebook' ), array( 'button' => 'x-twitter' ), array( 'button' => 'linkedin' ) ),
			'keep'         => array( 'button', 'text' ),
		),
	),
	'classes'   => $digi_sb_classes,
);
