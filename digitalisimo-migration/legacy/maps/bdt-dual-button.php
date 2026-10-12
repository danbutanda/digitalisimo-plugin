<?php
/**
 * Element Pack Pro 9.9.1 `bdt-dual-button` → `digitalisimo-dual-button`.
 * Los eventos onclick con JavaScript libre no se trasladan: el enlace de cada botón se conserva.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-dual-button',
	'classes'  => array(
		'.bdt-dual-button a.bdt-btn-a' => '.digi-dual-button__action--a',
		'.bdt-dual-button a.bdt-btn-b' => '.digi-dual-button__action--b',
		'.bdt-dual-button .bdt-btn-a'  => '.digi-dual-button__action--a',
		'.bdt-dual-button .bdt-btn-b'  => '.digi-dual-button__action--b',
		'.bdt-dual-button a'           => '.digi-dual-button__action',
		'.bdt-dual-button span'        => '.digi-dual-button__middle',
		'.bdt-btn-a .bdt-btn-icon'     => '.digi-dual-button__action--a svg',
		'.bdt-btn-b .bdt-btn-icon'     => '.digi-dual-button__action--b svg',
		'.bdt-btn-a'                   => '.digi-dual-button__action--a',
		'.bdt-btn-b'                   => '.digi-dual-button__action--b',
		'.bdt-dual-button'             => '.digi-dual-button',
		'.bdt-element-align-wrapper'   => '.digi-dual-button',
	),
	'defaults' => array(
		'dual_button_size' => 'md',
		'middle_text'      => 'or',
		'button_a_text'    => 'Click Me',
		'button_a_link'    => array( 'url' => '#' ),
		'button_b_text'    => 'Read More',
		'button_b_link'    => array( 'url' => '#' ),
	),
	'rename'   => array( 'dual_button_size' => 'size', 'button_a_select_icon' => 'button_a_icon', 'button_b_select_icon' => 'button_b_icon' ),
	'values'   => array(
		'size'  => array( 'xs' => 'small', 'sm' => 'small', 'md' => 'medium', 'lg' => 'large', 'xl' => 'large' ),
		'align' => array( 'start' => 'flex-start', 'end' => 'flex-end', 'left' => 'flex-start', 'right' => 'flex-end', 'stretch' => 'center', 'justify' => 'center' ),
	),
	'drop'     => array( 'button_a_onclick', 'button_a_onclick_event', 'button_b_onclick', 'button_b_onclick_event' ),
);
