<?php
/**
 * «Vertical Links» de PRO (`contact-buttons-var-4`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 4;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p4`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-4',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'draft',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '4' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'chat_aria_label' => 'Links',
		'chat_button_icon' => array( 'value' => 'fas fa-ellipsis-v', 'library' => 'fa-solid' ),
	),
	'keep'      => array(
		'chat_aria_label',
		'chat_button_icon',
		'style_chat_button_size',
		'style_button_color_icon',
		'style_button_color_background',
		'style_button_color_icon_hover',
		'style_button_color_background_hover',
		'style_button_color_hover_animation',
		'style_button_color_icon_active',
		'style_button_color_background_active',
		'style_chat_button_animation',
		'style_chat_button_animation_duration',
		'style_chat_button_animation_delay',
		'style_chat_button_box_shadow_box_shadow',
		'style_chat_button_box_shadow_box_shadow_position',
		'style_contact_button_color_icon',
		'style_contact_button_color_background',
		'style_contact_button_color_icon_hover',
		'style_contact_button_color_background_hover',
		'style_contact_buttons_spacing',
		'style_contact_icons_box_shadow_box_shadow',
		'style_contact_icons_box_shadow_box_shadow_position',
		'style_contact_tooltip_text_color',
		'style_contact_tooltip_bg_color',
		'style_chat_box_entrance_animation',
		'style_chat_box_exit_animation',
		'style_chat_box_animation_duration',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(
		'style_chat_button_box_shadow' => 'style_chat_button_box_shadow',
		'style_contact_icons_box_shadow' => 'style_contact_icons_box_shadow',
		'style_contact_tooltip_typography' => 'style_contact_tooltip_typography',
	),
	'repeaters' => array(
		'contact_repeater' => array(
			'target'       => 'contact_repeater',
			'defaults'     => array( 'contact_tooltip' => 'Tooltip' ),
			'default_rows' => array( array( 'contact_icon_platform' => 'WhatsApp' ), array( 'contact_icon_platform' => 'Messenger' ), array( 'contact_icon_platform' => 'Email' ), array( 'contact_icon_platform' => 'Telephone' ) ),
			'keep'         => array( 'contact_icon_platform', 'contact_tooltip', 'contact_icon_mail', 'contact_icon_mail_subject', 'contact_icon_mail_body', 'contact_icon_number', 'contact_icon_username', 'contact_icon_url', 'contact_icon_waze', 'contact_icon_viber_action' ),
		),
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'chat_button_section', 'contact_section', 'style_chat_button', 'style_button_color_tabs', 'style_button_color_tabs_normal', 'style_button_color_tabs_hover', 'style_button_color_tabs_active', 'style_contact_section', 'style_contact_button_color_tabs', 'style_contact_button_color_tabs_normal', 'style_contact_button_color_tabs_hover', 'style_contact_tooltip_heading', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__chat-button' => '.digi-cb__chat-button',
		'.e-contact-buttons__chat-button-container' => '.digi-cb__chat-button-container',
		'.e-contact-buttons__chat-button-shadow' => '.digi-cb__chat-button-shadow',
		'.e-contact-buttons__close-button' => '.digi-cb__close-button',
		'.e-contact-buttons__contact' => '.digi-cb__contact',
		'.e-contact-buttons__contact-box-shadow' => '.digi-cb__contact-box-shadow',
		'.e-contact-buttons__contact-icon-container' => '.digi-cb__contact-icon-container',
		'.e-contact-buttons__contact-icon-link' => '.digi-cb__contact-icon-link',
		'.e-contact-buttons__contact-links' => '.digi-cb__contact-links',
		'.e-contact-buttons__contact-tooltip' => '.digi-cb__contact-tooltip',
		'.e-contact-buttons__content' => '.digi-cb__content',
		'.e-contact-buttons__content-wrapper' => '.digi-cb__content-wrapper',
		'.e-contact-buttons-var-4' => '.digi-cb--p4',
		'.elementor-widget-contact-buttons-var-4' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
