<?php
/**
 * «Quick Access Bar» de PRO (`contact-buttons-var-6`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 6;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p6`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-6',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '6' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'contact_aria_label' => 'Floating Element',
		'advanced_horizontal_position' => 'center',
	),
	'keep'      => array(
		'contact_aria_label',
		'style_contact_button_size',
		'style_contact_button_color_icon',
		'style_contact_button_color_icon_hover',
		'style_contact_button_hover_animation',
		'style_contact_button_bar_bg_color',
		'style_contact_button_bar_corners',
		'style_contact_button_bar_padding',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(

	),
	'repeaters' => array(
		'contact_repeater' => array(
			'target'       => 'contact_repeater',
			'default_rows' => array( array( 'contact_icon_platform' => 'Telephone' ), array( 'contact_icon_platform' => 'Email' ), array( 'contact_icon_platform' => 'WhatsApp' ), array( 'contact_icon_platform' => 'Waze' ) ),
			'keep'         => array( 'contact_icon_platform', 'contact_icon_mail', 'contact_icon_mail_subject', 'contact_icon_mail_body', 'contact_icon_number', 'contact_icon_username', 'contact_icon_url', 'contact_icon_waze', 'contact_icon_viber_action' ),
		),
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'contact_section', 'contact_custom_panel_alert', 'style_contact_section', 'style_contact_buttons_heading', 'style_contact_button_color_tabs', 'style_contact_button_color_tabs_normal', 'style_contact_button_color_tabs_hover', 'style_contact_button_bar_heading', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__contact-icon-link' => '.digi-cb__contact-icon-link',
		'.e-contact-buttons__contact-links' => '.digi-cb__contact-links',
		'.e-contact-buttons-var-6' => '.digi-cb--p6',
		'.elementor-widget-contact-buttons-var-6' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
