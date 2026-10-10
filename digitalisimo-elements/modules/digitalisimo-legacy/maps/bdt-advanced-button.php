<?php
/** Element Pack Pro 9.9.1 `bdt-advanced-button` → `digitalisimo-advanced-button` (mismos nombres de controles). */
defined( 'ABSPATH' ) || exit;

$effects = array();
foreach ( range( 'a', 'i' ) as $letter ) {
	$effects[ '.bdt-ep-button-effect-' . $letter ] = '.digi-advanced-button--effect-' . $letter;
}

return array(
	'target'   => 'digitalisimo-advanced-button',
	'classes'  => $effects + array(
		'.bdt-ep-button-badge-inner'     => '.digi-advanced-button__badge',
		'.bdt-ep-button-badge'           => '.digi-advanced-button__badge',
		'.bdt-ep-button-icon-inner'      => '.digi-advanced-button__icon',
		'.bdt-ep-button-icon'            => '.digi-advanced-button__icon',
		'.bdt-ep-button-content-wrapper' => '.digi-advanced-button__content',
		'.bdt-ep-button-text'            => '.digi-advanced-button__text',
		'.bdt-ep-button-wrapper'         => '.digi-advanced-button__wrap',
		'.bdt-ep-button'                 => '.digi-advanced-button',
	),
	'defaults' => array(
		'text'          => 'Click me',
		'link'          => array( 'url' => '#' ),
		'button_size'   => 'md',
		'icon_align'    => 'right',
		'icon_indent'   => array( 'size' => 8 ),
		'badge_text'    => 'Badge',
		'badge_align'   => 'right',
		'badge_indent'  => array( 'size' => 8 ),
		'button_effect' => 'a',
		'align'         => '',
		'button_css_id' => '',
		'button_border_style' => 'solid',
		'button_border_width' => array( 'top' => 3, 'right' => 3, 'bottom' => 3, 'left' => 3 ),
		'button_border_color' => '#666',
	),
	// Sin alineación Element Pack dejaba el botón a la izquierda.
	'values'   => array( 'align' => array( '' => 'left' ) ),
	'drop'     => array( 'icon_align_choose' ),
);
