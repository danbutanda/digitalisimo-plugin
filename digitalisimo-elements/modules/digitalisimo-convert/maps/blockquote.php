<?php
/**
 * Blockquote de PRO → Cita (`digitalisimo-blockquote`, modules/digitalisimo-widgets/class-blockquote.php).
 *
 * La Cita tiene un control por cada control del Blockquote, con el mismo nombre y los mismos valores, salvo los cuatro
 * del botón, que dejan de nombrar al servicio (`tweet_button*` → `share_*`). Las clases de las pieles, la vista y la
 * piel del botón van en el envoltorio como en PRO, así que el CSS personalizado del elemento sigue apuntando al mismo
 * nodo. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'blockquote',
	'target'   => 'digitalisimo-blockquote',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de los controles del Blockquote de PRO (Elementor no guarda lo que coincide con ellos).
	'defaults' => array(
		'blockquote_skin'            => 'border',
		'blockquote_content'         => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.Lorem ipsum dolor sit amet consectetur adipiscing elit dolor',
		'author_name'                => 'John Doe',
		'tweet_button'               => 'yes',
		'tweet_button_view'          => 'icon-text',
		'tweet_button_skin'          => 'classic',
		'tweet_button_label'         => 'Tweet',
		'url_type'                   => 'current_page',
		'author_gap'                 => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
		'quote_size'                 => array( 'unit' => 'px', 'size' => 1, 'sizes' => array() ),
		'button_transition_duration' => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
		'border_transition_duration' => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
		'box_transition_duration'    => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
	),
	'keep'     => array(
		'blockquote_skin',
		'blockquote_content',
		'author_name',
		'user_name',
		'url_type',
		'url',
		'alignment',
		'content_text_color',
		'content_gap',
		'author_text_color',
		'author_gap',
		'button_size',
		'button_border_radius',
		'button_background_color',
		'button_text_color',
		'button_background_color_hover',
		'button_text_color_hover',
		'button_transition_duration',
		'border_color',
		'border_width',
		'border_gap',
		'border_color_hover',
		'border_width_hover',
		'border_gap_hover',
		'border_transition_duration',
		'border_vertical_padding',
		'box_padding',
		'box_background_color',
		'box_border_radius',
		'box_background_color_hover',
		'box_border_radius_hover',
		'box_transition_duration',
		'quote_text_color',
		'quote_size',
		'quote_gap',
	),
	'rename'   => array(
		'tweet_button'       => 'share_button',
		'tweet_button_view'  => 'share_view',
		'tweet_button_skin'  => 'share_skin',
		'tweet_button_label' => 'share_label',
	),
	// Grupos con el mismo prefijo en la Cita: tipografías, bordes y sombras de la caja.
	'groups'   => array(
		'content_typography'   => 'content_typography',
		'author_typography'    => 'author_typography',
		'button_typography'    => 'button_typography',
		'box_border'           => 'box_border',
		'box_border_hover'     => 'box_border_hover',
		'box_box_shadow'       => 'box_box_shadow',
		'box_box_shadow_hover' => 'box_box_shadow_hover',
	),
	// La alineación pasa tal cual (`keep`); sólo se informa la de un dispositivo distinto del escritorio.
	'approx'   => array_fill_keys(
		array( 'alignment_widescreen', 'alignment_laptop', 'alignment_tablet_extra', 'alignment_tablet', 'alignment_mobile_extra', 'alignment_mobile' ),
		'En PRO la alineación de cada dispositivo se pintaba como clase en todos los tamaños a la vez; la Cita la aplica sólo en su dispositivo.'
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_content_style', 'heading_author_style' ),
	'classes'  => array(
		'.elementor-widget-blockquote'                => '.elementor-widget-digitalisimo-blockquote',
		'.elementor-blockquote'                       => '.digi-blockquote',
		'.elementor-blockquote__content'              => '.digi-blockquote__content',
		'.elementor-blockquote__author'               => '.digi-blockquote__author',
		'.elementor-blockquote__tweet-button'         => '.digi-blockquote__button',
		'.elementor-blockquote__tweet-label'          => '.digi-blockquote__label',
		'.e-q-footer'                                 => '.digi-blockquote__footer',
		'.elementor-blockquote--skin-border'          => '.digi-blockquote--skin-border',
		'.elementor-blockquote--skin-quotation'       => '.digi-blockquote--skin-quotation',
		'.elementor-blockquote--skin-boxed'           => '.digi-blockquote--skin-boxed',
		'.elementor-blockquote--skin-clean'           => '.digi-blockquote--skin-clean',
		'.elementor-blockquote--button-view-icon-text' => '.digi-blockquote--view-icon-text',
		'.elementor-blockquote--button-view-icon'     => '.digi-blockquote--view-icon',
		'.elementor-blockquote--button-view-text'     => '.digi-blockquote--view-text',
		'.elementor-blockquote--button-skin-classic'  => '.digi-blockquote--button-classic',
		'.elementor-blockquote--button-skin-bubble'   => '.digi-blockquote--button-bubble',
		'.elementor-blockquote--button-skin-link'     => '.digi-blockquote--button-link',
		'.elementor-blockquote--align-left'           => '.digi-blockquote--align-left',
		'.elementor-blockquote--align-center'         => '.digi-blockquote--align-center',
		'.elementor-blockquote--align-right'          => '.digi-blockquote--align-right',
	),
);
