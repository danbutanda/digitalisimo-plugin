<?php
/** Element Pack Pro 9.9.1 `bdt-accordion` → `digitalisimo-accordion`. */
defined( 'ABSPATH' ) || exit;

return array(
	'target'    => 'digitalisimo-accordion',
	// Clases de Element Pack → selectores del widget propio, para sus controles de estilo.
	'classes'   => array(
		'.bdt-ep-accordion-item.bdt-open' => '.digi-accordion__item[open]',
		'.bdt-ep-accordion-item'          => '.digi-accordion__item',
		'.bdt-ep-accordion-title'         => '.digi-accordion__summary',
		'.bdt-ep-accordion-content'       => '.digi-accordion__content',
		'.bdt-ep-accordion-icon-closed'   => '.digi-accordion__icon-closed',
		'.bdt-ep-accordion-icon-opened'   => '.digi-accordion__icon-open',
		'.bdt-ep-accordion-icon'          => '.digi-accordion__marker',
		'.bdt-ep-accordion-custom-icon'   => '.digi-accordion__custom-icon',
		'.bdt-ep-title-text'              => '.digi-accordion__title',
		'.bdt-ep-accordion-container'     => '.digi-accordion',
		'.bdt-ep-accordion'               => '.digi-accordion',
	),
	'defaults'  => array(
		'title_html_tag'        => 'div',
		'accordion_icon'        => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
		'accordion_active_icon' => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
		'icon_align'            => 'right',
		'active_item'           => '',
	),
	'rename'    => array( 'always_active_all_items' => 'open_all_initially' ),
	'values'    => array(
		// El título vive dentro de <summary>: sólo admite texto o H2–H6.
		'title_html_tag' => array( 'div' => 'span', 'p' => 'span', 'span' => 'span', 'h1' => 'h2' ),
		// Vacío dejaba todos cerrados; 0 hace lo mismo en el widget propio.
		'active_item'    => static function ( $value ) {
			return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
		},
	),
	'drop'      => array( 'view', 'collapsible', 'close_all_items_on_mobile', 'active_hash', 'active_scrollspy', 'hash_top_offset', 'hash_scrollspy_time', 'schema_activity', 'schema_override_seo', 'accordion_animation', 'duration', 'transition', 'custom_transition' ),
	'repeaters' => array(
		'tabs' => array(
			'defaults'     => array( 'tab_title' => 'Accordion Title', 'source' => 'custom', 'tab_content' => 'Accordion Content' ),
			'default_rows' => array(
				array( 'tab_title' => 'Accordion #1', 'tab_content' => 'I am item content. Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' ),
				array( 'tab_title' => 'Accordion #2', 'tab_content' => 'I am item content. Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' ),
				array( 'tab_title' => 'Accordion #3', 'tab_content' => 'I am item content. Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' ),
			),
		),
	),
);
