<?php
/** Element Pack Pro 9.9.1 `bdt-call-out` → `digitalisimo-call-out`. */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-call-out',
	'classes'  => array(
		'.bdt-ep-callout-button-icon' => '.digi-call-out__button svg',
		'.bdt-ep-callout-button-wrap' => '.digi-call-out',
		'.bdt-ep-callout-button'      => '.digi-call-out__button',
		'.bdt-ep-callout-title'       => '.digi-call-out__title',
		'.bdt-ep-callout-description' => '.digi-call-out__description',
		'.bdt-ep-callout'             => '.digi-call-out',
	),
	'strip'    => array( 'title', 'description' ),
	'defaults' => array(
		'title'       => 'This is your call to action title',
		'description' => 'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart.',
		'title_size'  => 'h3',
		'button_text' => 'Click Here',
		'link'        => array( 'url' => '#' ),
	),
	'rename'   => array( 'callout_icon' => 'button_icon' ),
	'values'   => array( 'title_size' => array( 'h1' => 'h2', 'p' => 'div', 'span' => 'div' ) ),
	'drop'     => array( 'deprecated_widget_note', 'button_align', 'icon_align' ),
);
