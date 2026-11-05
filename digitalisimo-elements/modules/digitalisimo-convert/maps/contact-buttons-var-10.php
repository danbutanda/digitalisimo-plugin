<?php
/**
 * «Interactive» de PRO (`contact-buttons-var-10`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 10;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p10`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-10',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '10' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'contact_aria_label' => 'Floating Element',
		'advanced_horizontal_position' => 'start',
	),
	'keep'      => array(
		'contact_aria_label',
		'style_contact_button_size',
		'style_contact_button_text_color',
		'style_contact_button_bg_color',
		'style_contact_buttons_spacing',
		'style_contact_corners',
		'style_contact_icons_box_shadow_box_shadow',
		'style_contact_icons_box_shadow_box_shadow_position',
		'style_contact_padding',
		'style_contact_custom_animation_transition',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(
		'style_contact_typography' => 'style_contact_typography',
		'style_contact_icons_box_shadow' => 'style_contact_icons_box_shadow',
	),
	'repeaters' => array(
		'contact_repeater' => array(
			'target'       => 'contact_repeater',
			'default_rows' => array( array( 'contact_icon_platform' => 'WhatsApp' ), array( 'contact_icon_platform' => 'Email' ), array( 'contact_icon_platform' => 'SMS' ), array( 'contact_icon_platform' => 'VIBER' ), array( 'contact_icon_platform' => 'Messenger' ) ),
			'keep'         => array( 'contact_icon_platform', 'contact_title', 'contact_icon_mail', 'contact_icon_mail_subject', 'contact_icon_mail_body', 'contact_icon_number', 'contact_icon_username', 'contact_icon_url', 'contact_icon_waze', 'contact_icon_viber_action' ),
		),
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'contact_section', 'style_contact_section', 'style_contact_buttons_heading', 'style_contact_custom_animation_heading', 'style_contact_custom_animation_alert', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__contact-box-shadow' => '.digi-cb__contact-box-shadow',
		'.e-contact-buttons__contact-icon-container' => '.digi-cb__contact-icon-container',
		'.e-contact-buttons__contact-icon-link' => '.digi-cb__contact-icon-link',
		'.e-contact-buttons__contact-links' => '.digi-cb__contact-links',
		'.e-contact-buttons__contact-title' => '.digi-cb__contact-title',
		'.e-contact-buttons__links-container' => '.digi-cb__links-container',
		'.e-contact-buttons-var-10' => '.digi-cb--p10',
		'.elementor-widget-contact-buttons-var-10' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
