<?php
/**
 * «Classic» de PRO (`contact-buttons-var-5`) → Botones de contacto (`digitalisimo-contact-buttons`, presentación 5;
 * modules/digitalisimo-floating/widgets/class-contact-buttons.php).
 * 
 * El widget propio hereda la base del núcleo y repite la unión de los controles de sus variantes con el mismo nombre,
 * valor por defecto y condición, así que el mapa es `keep` y `groups` más la presentación fija. Las clases del marcado
 * pasan de `e-contact-buttons__*` a `digi-cb__*` (y la de la variante a `digi-cb--p5`) para el CSS personalizado del elemento.
 * Formato: cabecera de `class-translator.php`. Generado desde los controles de PRO y del widget propio en WordPress
 * (la herramienta y los volcados se describen en `docs/pro/c8.md`).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'    => 'contact-buttons-var-5',
	'target'    => 'digitalisimo-contact-buttons',
	'status'    => 'ready',
	'panel'     => 'hide',
	// La presentación de la variante; el resto de controles tiene el mismo nombre en el widget propio.
	'set'       => array( 'presentation' => '5' ),
	// Valores por defecto de PRO que el widget propio no tiene iguales (textos en español o el de otra presentación).
	'defaults'  => array(
		'chat_aria_label' => 'Chat',
		'chat_button_show_dot' => '',
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
		'chat_button_show_dot',
		'style_chat_button_size',
		'style_button_color_select',
		'style_button_color_icon',
		'style_button_color_background',
		'style_button_color_select_hover',
		'style_button_color_icon_hover',
		'style_button_color_background_hover',
		'style_button_color_hover_animation',
		'style_chat_button_animation',
		'style_chat_button_animation_duration',
		'style_chat_button_animation_delay',
		'style_chat_button_box_shadow_box_shadow',
		'style_chat_button_box_shadow_box_shadow_position',
		'advanced_horizontal_position',
		'advanced_horizontal_offset',
		'advanced_vertical_position',
		'advanced_vertical_offset',
		'advanced_custom_css_id',
		'advanced_custom_css_classes',
	),
	'groups'    => array(
		'style_chat_button_box_shadow' => 'style_chat_button_box_shadow',
	),
	'repeaters' => array(
	),
	// Controles de interfaz (secciones, pestañas y encabezados): no guardan valores.
	'ui'        => array( 'chat_button_section', 'style_chat_button', 'style_button_color_tabs', 'style_button_color_tabs_normal', 'style_button_color_tabs_hover', 'advanced_layout_section', 'advanced_responsive_section', 'advanced_custom_controls_section' ),
	'classes'   => array(
		'.e-contact-buttons' => '.digi-cb',
		'.e-contact-buttons__chat-button' => '.digi-cb__chat-button',
		'.e-contact-buttons__chat-button-container' => '.digi-cb__chat-button-container',
		'.e-contact-buttons__chat-button-shadow' => '.digi-cb__chat-button-shadow',
		'.e-contact-buttons-var-5' => '.digi-cb--p5',
		'.elementor-widget-contact-buttons-var-5' => '.elementor-widget-digitalisimo-contact-buttons',
	),
);
