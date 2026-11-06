<?php
/**
 * «Animated Classic» de PRO (`contact-buttons-var-9`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 9;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p9`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-9',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '9' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'chat_aria_label' => 'Floating Element',
		'chat_button_display_text' => 'Get in touch',
		'style_chat_button_size' => 'medium',
	),
	'keep'      => array(
		'chat_aria_label',
		'chat_button_platform',
		'chat_button_mail',
		'chat_button_mail_subject',
		'chat_button_mail_body',
		'chat_button_number',
		'chat_button_username',
		'chat_button_viber_action',
		'chat_button_waze',
		'chat_button_url',
		'chat_button_display_text',
		'style_chat_button_size',
		'style_platform_control_select',
		'style_button_color_icon',
		'style_button_color_background',
		'style_chat_button_drop_shadow_box_shadow',
		'style_chat_button_drop_shadow_box_shadow_position',
		'style_chat_button_padding',
		'style_chat_button_custom_animation_transition',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(
		'style_top_bar_title_typography' => 'style_top_bar_title_typography',
		'style_chat_button_drop_shadow' => 'style_chat_button_drop_shadow',
	),
	'repeaters' => array(
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'chat_button_section', 'style_chat_button', 'style_chat_button_custom_animation_heading', 'style_chat_button_custom_animation_alert', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__chat-button' => '.digi-cb__chat-button',
		'.e-contact-buttons__chat-button-container' => '.digi-cb__chat-button-container',
		'.e-contact-buttons__chat-button-drop-shadow' => '.digi-cb__chat-button-drop-shadow',
		'.e-contact-buttons__chat-button-icon-container' => '.digi-cb__chat-button-icon-container',
		'.e-contact-buttons__chat-button-text' => '.digi-cb__chat-button-text',
		'.e-contact-buttons-var-9' => '.digi-cb--p9',
		'.elementor-widget-contact-buttons-var-9' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
